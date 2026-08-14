<?php
require_once __DIR__ . '/../../functions.php';

start_session();
page_header('Unit Converter');
?>
<div class="container" style="max-width: 760px;">
    <h1 class="h4 mb-1 reveal in-view">Unit Converter</h1>
    <p class="text-secondary mb-4 reveal in-view">Length, mass, temperature, data, time and speed — instant conversion, all in your browser.</p>

    <div class="alert alert-secondary reveal in-view">
        <strong>How it works:</strong> every unit is stored as a factor against one base unit (metre, gram, byte, second, m/s). To convert you multiply the value into the base, then divide out of the target unit. Temperature is the exception — Celsius, Fahrenheit and Kelvin use real offset formulas, so pick that category for 0&deg;C = 32&deg;F = 273.15 K checks.
    </div>

    <div class="card reveal in-view">
        <div class="card-body">
            <label class="form-label">Category</label>
            <div class="d-flex flex-wrap gap-2 mb-3">
                <button class="btn btn-primary btn-sm cat-btn" data-cat="length">Length</button>
                <button class="btn btn-outline-light btn-sm cat-btn" data-cat="mass">Mass</button>
                <button class="btn btn-outline-light btn-sm cat-btn" data-cat="temp">Temperature</button>
                <button class="btn btn-outline-light btn-sm cat-btn" data-cat="data">Data size</button>
                <button class="btn btn-outline-light btn-sm cat-btn" data-cat="time">Time</button>
                <button class="btn btn-outline-light btn-sm cat-btn" data-cat="speed">Speed</button>
            </div>

            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">From</label>
                    <input id="cval" type="number" class="form-control" style="font-family:'JetBrains Mono',monospace;" value="1" oninput="convert()">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Unit</label>
                    <select id="cfrom" class="form-select" onchange="convert()"></select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">To unit</label>
                    <select id="cto" class="form-select" onchange="convert()"></select>
                </div>
            </div>

            <div class="mt-3 text-center">
                <div class="display-6 fw-normal" id="cresult" style="font-family:'JetBrains Mono',monospace;">1 m</div>
                <div class="text-secondary small" id="cequiv"></div>
            </div>

            <div class="mt-3 d-flex gap-2 flex-wrap">
                <button class="btn btn-outline-light btn-sm" onclick="swap()">Swap units</button>
                <button class="btn btn-outline-light btn-sm" onclick="copyResult()">Copy result</button>
            </div>
        </div>
    </div>
</div>

<script>
var UNITS = {
    length: {
        base: 'm',
        list: [['m','metre',1],['km','kilometre',1000],['cm','centimetre',0.01],['mm','millimetre',0.001],['mi','mile',1609.344],['yd','yard',0.9144],['ft','foot',0.3048],['in','inch',0.0254],['nmi','nautical mile',1852]]
    },
    mass: {
        base: 'g',
        list: [['g','gram',1],['kg','kilogram',1000],['mg','milligram',0.001],['t','tonne',1000000],['lb','pound',453.59237],['oz','ounce',28.3495231],['st','stone',6350.29318]]
    },
    temp: {
        base: 'C',
        list: [['C','Celsius'],['F','Fahrenheit'],['K','Kelvin']]
    },
    data: {
        base: 'B',
        list: [['B','byte',1],['KB','kilobyte',1000],['MB','megabyte',1000000],['GB','gigabyte',1000000000],['TB','terabyte',1000000000000],['KiB','kibibyte',1024],['MiB','mebibyte',1048576],['GiB','gibibyte',1073741824],['TiB','tebibyte',1099511627776],['bit','bit',0.125]]
    },
    time: {
        base: 's',
        list: [['s','second',1],['ms','millisecond',0.001],['min','minute',60],['h','hour',3600],['day','day',86400],['wk','week',604800],['mo','month (avg)',2629746],['yr','year (Julian)',31557600]]
    },
    speed: {
        base: 'm/s',
        list: [['m/s','m/s',1],['km/h','km/h',1/3.6],['mph','mph',0.44704],['kn','knot',0.514444],['ft/s','ft/s',0.3048]]
    }
};

var cur = 'length';

function buildSelects() {
    var u = UNITS[cur];
    var f = document.getElementById('cfrom');
    var t = document.getElementById('cto');
    f.innerHTML = ''; t.innerHTML = '';
    u.list.forEach(function (item, i) {
        var o1 = document.createElement('option');
        o1.value = i; o1.textContent = item[0] + ' — ' + item[1];
        var o2 = o1.cloneNode(true);
        if (i === 0) o1.selected = true;
        if (i === 1) o2.selected = true;
        f.appendChild(o1);
        t.appendChild(o2);
    });
    convert();
}

function toBase(v, idx) {
    var u = UNITS[cur];
    if (cur === 'temp') {
        if (idx === 0) return v;                       // C
        if (idx === 1) return (v - 32) * 5 / 9;        // F -> C
        return v - 273.15;                              // K -> C
    }
    return v * u.list[idx][2];
}
function fromBase(base, idx) {
    var u = UNITS[cur];
    if (cur === 'temp') {
        if (idx === 0) return base;
        if (idx === 1) return base * 9 / 5 + 32;
        return base + 273.15;
    }
    return base / u.list[idx][2];
}

function fmt(n) {
    if (!isFinite(n)) return '∞';
    if (Math.abs(n) >= 1e15 || (Math.abs(n) < 1e-9 && n !== 0)) return n.toExponential(6);
    var s = Number(n.toPrecision(12)).toString();
    return s;
}

function convert() {
    var v = parseFloat(document.getElementById('cval').value);
    if (isNaN(v)) v = 0;
    var fi = parseInt(document.getElementById('cfrom').value, 10);
    var ti = parseInt(document.getElementById('cto').value, 10);
    var u = UNITS[cur];
    var out = fromBase(toBase(v, fi), ti);
    document.getElementById('cresult').textContent = fmt(v) + ' ' + u.list[fi][0] + ' = ' + fmt(out) + ' ' + u.list[ti][0];
    var eq = [];
    [0, 1, 2].forEach(function (i) {
        if (i !== fi) eq.push(fmt(fromBase(toBase(1, fi), i)) + ' ' + u.list[i][0]);
    });
    document.getElementById('cequiv').textContent = '1 ' + u.list[fi][0] + ' ≈ ' + eq.join('  ·  ');
}

function setCat(cat) {
    cur = cat;
    document.querySelectorAll('.cat-btn').forEach(function (b) {
        b.classList.toggle('btn-primary', b.getAttribute('data-cat') === cat);
        b.classList.toggle('btn-outline-light', b.getAttribute('data-cat') !== cat);
    });
    buildSelects();
}
function swap() {
    var f = document.getElementById('cfrom');
    var t = document.getElementById('cto');
    var tmp = f.value; f.value = t.value; t.value = tmp;
    convert();
}
function copyResult() {
    var r = document.getElementById('cresult');
    var a = document.createElement('textarea');
    a.value = r.textContent;
    document.body.appendChild(a);
    a.select();
    document.execCommand('copy');
    a.remove();
}
document.querySelectorAll('.cat-btn').forEach(function (b) {
    b.addEventListener('click', function () { setCat(b.getAttribute('data-cat')); });
});
buildSelects();
</script>
<?php page_footer(); ?>