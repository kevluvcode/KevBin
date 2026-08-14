<?php
require_once __DIR__ . '/../../functions.php';

start_session();
page_header('Cron Explainer');
?>
<div class="container" style="max-width: 900px;">
    <h1 class="h4 mb-1 reveal in-view">🕒 Cron Explainer</h1>
    <p class="text-secondary mb-4 reveal in-view">Paste a 5-field cron expression and see what it means in plain English plus the next times it fires. Computed locally.</p>

    <div class="card reveal">
        <div class="card-body">
            <h2 class="h6 mb-2">Cron expression <span class="text-secondary small">minute hour day-of-month month day-of-week</span></h2>
            <div class="d-flex gap-2 flex-wrap mb-3">
                <input id="cron-in" class="form-control" style="max-width:320px;font-family:'JetBrains Mono',monospace;font-size:.85rem;" value="*/15 * * * *" oninput="parseCron()">
                <div class="d-flex gap-1 flex-wrap" id="cron-presets"></div>
            </div>
            <div id="cron-out">
                <div class="text-secondary small mb-2">Every minute · next runs:</div>
                <div id="cron-list" class="p-2" style="background:#0b0b0b;border:1px solid var(--line);border-radius:10px;font-family:'JetBrains Mono',monospace;font-size:.85rem;"></div>
            </div>
        </div>
    </div>
</div>
<script>
function parseCron() {
    var expr = $('cron-in').value.trim().split(/\s+/);
    if (expr.length !== 5) {
        $('cron-list').textContent = 'Expected 5 fields (minute hour day-of-month month day-of-week).';
        return;
    }
    var fields = ['minute', 'hour', 'day of month', 'month', 'day of week'];
    var sets = [];
    for (var i = 0; i < 5; i++) {
        var s = expand(expr[i], i);
        if (s === null) { $('cron-list').textContent = 'Invalid field: ' + expr[i]; return; }
        sets.push(s);
    }
    var desc = describe(expr, sets, fields);
    var next = nextRuns(sets, 6);
    $('cron-list').innerHTML = '';
    var d = document.createElement('div');
    d.textContent = desc;
    $('cron-list').appendChild(d);
    next.forEach(function (t, idx) {
        var row = document.createElement('div');
        row.textContent = (idx + 1) + '. ' + t;
        $('cron-list').appendChild(row);
    });
}
function expand(field, idx) {
    var ranges = [[0,59],[0,23],[1,31],[1,12],[0,6]];
    var names = idx === 4 ? {sun:0,mon:1,tue:2,wed:3,thu:4,fri:5,sat:6}
        : idx === 3 ? {jan:1,feb:2,mar:3,apr:4,may:5,jun:6,jul:7,aug:8,sep:9,oct:10,nov:11,dec:12} : null;
    var lo = ranges[idx][0], hi = ranges[idx][1];
    var out = new Set();
    var parts = field.split(',');
    for (var p = 0; p < parts.length; p++) {
        var part = parts[p].trim();
        if (part === '') return null;
        var step = 1;
        var base = part;
        if (base.indexOf('/') !== -1) { var sp = base.split('/'); base = sp[0]; step = parseInt(sp[1], 10); }
        if (names && names[base.toLowerCase()] !== undefined) base = String(names[base.toLowerCase()]);
        if (base === '*') {
            for (var v = lo; v <= hi; v += step) out.add(v);
        } else {
            var rr = base.split('-');
            var a = parseInt(rr[0], 10), b = rr.length > 1 ? parseInt(rr[1], 10) : a;
            if (isNaN(a) || isNaN(b) || a < lo || b > hi || a > b) return null;
            for (var v2 = a; v2 <= b; v2 += step) out.add(v2);
        }
    }
    return out.size > 0 ? out : null;
}
function describe(expr, sets, fields) {
    var words = ['January','February','March','April','May','June','July','August','September','October','November','December'];
    var days = ['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'];
    var bits = [];
    if (sets[0].size === 60) bits.push('every minute');
    else if (sets[0].size === 1) bits.push('at minute ' + [...sets[0]][0]);
    else bits.push('at minutes ' + [...sets[0]].sort((a,b)=>a-b).join(', '));
    if (sets[1].size === 24) bits.push('every hour');
    else if (sets[1].size === 1) bits.push('at ' + pad([...sets[1]][0]) + ':00');
    else bits.push('at hours ' + [...sets[1]].sort((a,b)=>a-b).map(pad).join(', '));
    if (sets[2].size === 31) bits.push('every day');
    else bits.push('on days ' + [...sets[2]].sort((a,b)=>a-b).join(', '));
    if (sets[3].size === 12) { /* every month */ }
    else bits.push('in ' + [...sets[3]].sort((a,b)=>a-b).map(function (m) { return words[m - 1]; }).join(', '));
    if (sets[4].size === 7) bits.push('any weekday');
    else bits.push('on ' + [...sets[4]].sort((a,b)=>a-b).map(function (d) { return days[d]; }).join(', '));
    return bits.join(', ');
}
function pad(n) { return String(n).padStart(2, '0'); }
function nextRuns(sets, count) {
    var now = new Date();
    var found = [];
    var d = new Date(now);
    d.setSeconds(0, 0);
    d.setMinutes(d.getMinutes() - 1);
    for (var i = 0; i < 2000000 && found.length < count; i++) {
        d.setMinutes(d.getMinutes() + 1);
        if (!sets[0].has(d.getMinutes())) continue;
        if (!sets[1].has(d.getHours())) continue;
        if (!sets[2].has(d.getDate())) continue;
        if (!sets[3].has(d.getMonth() + 1)) continue;
        if (!sets[4].has(d.getDay())) continue;
        found.push(d.toLocaleString());
    }
    return found.length ? found : ['Never within the next ~4 years.'];
}
(function () {
    var presets = [
        ['*/5 * * * *', 'every 5 minutes'], ['0 * * * *', 'hourly'], ['0 0 * * *', 'daily at midnight'],
        ['0 9 * * 1-5', 'weekdays 9am'], ['0 2 * * 0', 'sunday 2am'], ['*/15 9-17 * * 1-5', 'work hours']
    ];
    var box = $('cron-presets');
    presets.forEach(function (p) {
        var b = document.createElement('button');
        b.className = 'btn btn-outline-light btn-sm';
        b.textContent = p[0] + ' · ' + p[1];
        b.onclick = function () { $('cron-in').value = p[0]; parseCron(); };
        box.appendChild(b);
    });
})();
parseCron();
function $(id) { return document.getElementById(id); }
</script>
<?php page_footer(); ?>