<?php
require_once __DIR__ . '/../../functions.php';

start_session();
page_header('Date Duration');
?>
<div class="container" style="max-width: 800px;">
    <h1 class="h4 mb-1 reveal in-view">⏳ Date Duration</h1>
    <p class="text-secondary mb-4 reveal in-view">Difference between two dates in every time unit, or add/subtract a duration to a date. All local.</p>

    <div class="card reveal">
        <div class="card-body">
            <div class="row g-3 mb-3">
                <div class="col-md-5">
                    <label class="form-label">From</label>
                    <input id="du-a" class="form-control" type="datetime-local" oninput="calcDur()">
                </div>
                <div class="col-md-5">
                    <label class="form-label">To</label>
                    <input id="du-b" class="form-control" type="datetime-local" oninput="calcDur()">
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button class="btn btn-outline-light btn-sm" onclick="swapDur()" style="width:100%">⇅ Swap</button>
                </div>
            </div>
            <div id="du-out" class="p-2" style="background:#0b0b0b;border:1px solid var(--line);border-radius:10px;min-height:110px;font-family:'JetBrains Mono',monospace;font-size:.85rem;"></div>

            <hr style="border-color:var(--line);">
            <h2 class="h6 mb-3">Add / subtract a duration</h2>
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Date</label>
                    <input id="du-base" class="form-control" type="datetime-local" oninput="calcAdd()">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Days</label>
                    <input id="du-days" class="form-control" type="number" value="0" oninput="calcAdd()">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Hours</label>
                    <input id="du-hrs" class="form-control" type="number" value="0" oninput="calcAdd()">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Minutes</label>
                    <input id="du-mins" class="form-control" type="number" value="0" oninput="calcAdd()">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Seconds</label>
                    <input id="du-secs" class="form-control" type="number" value="0" oninput="calcAdd()">
                </div>
            </div>
            <div id="du-addout" class="p-2 mt-3" style="background:#0b0b0b;border:1px solid var(--line);border-radius:10px;font-family:'JetBrains Mono',monospace;font-size:.85rem;"></div>
        </div>
    </div>
</div>
<script>
function toLocal(d) {
    var out = new Date(d);
    out.setMinutes(out.getMinutes() - out.getTimezoneOffset());
    return out.toISOString().slice(0, 16);
}
function calcDur() {
    var a = new Date($('du-a').value), b = new Date($('du-b').value);
    var out = $('du-out');
    if (isNaN(a) || isNaN(b)) { out.textContent = 'Pick both dates.'; return; }
    var ms = Math.abs(b - a);
    var s = Math.floor(ms / 1000);
    var m = Math.floor(s / 60), h = Math.floor(m / 60), d = Math.floor(h / 24);
    var yrs = d / 365.25;
    var html = 'Difference: <b>' + d.toLocaleString() + ' days</b><br>' +
        'In seconds: <b>' + s.toLocaleString() + '</b><br>' +
        'In minutes: <b>' + m.toLocaleString() + '</b> · in hours: <b>' + h.toLocaleString() + '</b><br>' +
        'In years (365.25 d): <b>' + yrs.toFixed(2) + '</b><br>' +
        'Broken down: <b>' + d + 'd ' + (h % 24) + 'h ' + (m % 60) + 'm ' + (s % 60) + 's</b>';
    out.innerHTML = html;
}
function calcAdd() {
    var base = new Date($('du-base').value);
    var out = $('du-addout');
    if (isNaN(base)) { out.textContent = 'Pick a date first.'; return; }
    var days = parseInt($('du-days').value, 10) || 0;
    var hrs = parseInt($('du-hrs').value, 10) || 0;
    var mins = parseInt($('du-mins').value, 10) || 0;
    var secs = parseInt($('du-secs').value, 10) || 0;
    var res = new Date(base.getTime() + days * 86400000 + hrs * 3600000 + mins * 60000 + secs * 1000);
    out.innerHTML = 'Result: <b>' + res.toLocaleString() + '</b> (local)';
}
(function () {
    var now = new Date();
    $('du-a').value = toLocal(new Date(now.getTime() - 7 * 86400000));
    $('du-b').value = toLocal(now);
    $('du-base').value = toLocal(now);
    calcDur();
    calcAdd();
})();
function swapDur() {
    var t = $('du-a').value;
    $('du-a').value = $('du-b').value;
    $('du-b').value = t;
    calcDur();
}
function $(id) { return document.getElementById(id); }
</script>
<?php page_footer(); ?>