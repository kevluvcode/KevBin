<?php
require_once __DIR__ . '/../../functions.php';

start_session();
page_header('Timestamp Converter');
?>
<div class="container" style="max-width: 900px;">
    <h1 class="h4 mb-1 reveal in-view">⏱ Timestamp Converter</h1>
    <p class="text-secondary mb-4 reveal in-view">Convert between Unix timestamps and human dates. All conversions happen locally in your browser.</p>

    <div class="row g-4">
        <div class="col-md-6 reveal">
            <div class="card h-100"><div class="card-body">
                <h2 class="h6 mb-2">Unix → Date</h2>
                <div class="d-flex gap-2 mb-2">
                    <input id="ts-in" class="form-control" style="font-family:'JetBrains Mono',monospace;font-size:.85rem;" value="1780000000" oninput="tsToDate()">
                    <button class="btn btn-outline-light btn-sm text-nowrap" onclick="tsNow()">Now</button>
                </div>
                <div id="ts-date" class="p-2" style="background:#0b0b0b;border:1px solid var(--line);border-radius:10px;font-family:'JetBrains Mono',monospace;font-size:.85rem;"></div>
            </div></div>
        </div>
        <div class="col-md-6 reveal">
            <div class="card h-100"><div class="card-body">
                <h2 class="h6 mb-2">Date → Unix</h2>
                <div class="d-flex gap-2 mb-2">
                    <input id="dt-in" class="form-control" type="datetime-local" style="font-size:.85rem;" oninput="dtToTs()">
                    <button class="btn btn-outline-light btn-sm text-nowrap" onclick="dtNow()">Now</button>
                </div>
                <div id="dt-ts" class="p-2" style="background:#0b0b0b;border:1px solid var(--line);border-radius:10px;font-family:'JetBrains Mono',monospace;font-size:.85rem;"></div>
            </div></div>
        </div>
        <div class="col-12 reveal">
            <div class="card"><div class="card-body">
                <h2 class="h6 mb-2">Presets (your local time)</h2>
                <div class="d-flex gap-2 flex-wrap" id="presets"></div>
            </div></div>
        </div>
    </div>
</div>
<script>
function tsToDate() {
    var v = $('ts-in').value.trim();
    var out = $('ts-date');
    if (!/^-?\d+$/.test(v)) { out.textContent = 'Enter an integer timestamp.'; return; }
    var t = parseInt(v, 10);
    if (String(v).length > 13) { // nanoseconds
        t = t / 1e6;
    } else if (String(v).length >= 12) { // milliseconds
        t = t;
    } else {
        t = t * 1000;
    }
    var d = new Date(t);
    if (isNaN(d.getTime())) { out.textContent = 'Invalid timestamp.'; return; }
    out.innerHTML = 'UTC: <b>' + d.toUTCString() + '</b><br>Local: <b>' + d.toLocaleString() + '</b>';
}
function dtToTs() {
    var v = $('dt-in').value;
    var out = $('dt-ts');
    if (!v) { out.textContent = 'Pick a date and time.'; return; }
    var ms = new Date(v).getTime();
    out.innerHTML = 'Milliseconds: <b>' + ms + '</b><br>Seconds: <b>' + Math.floor(ms / 1000) + '</b>';
}
function tsNow() { $('ts-in').value = Math.floor(Date.now() / 1000); tsToDate(); }
function dtNow() {
    var d = new Date();
    d.setMinutes(d.getMinutes() - d.getTimezoneOffset());
    $('dt-in').value = d.toISOString().slice(0, 16);
    dtToTs();
}
(function () {
    var now = new Date();
    var presets = [
        ['Now', Math.floor(Date.now() / 1000)],
        ['Today 00:00', Math.floor(new Date(now.getFullYear(), now.getMonth(), now.getDate()).getTime() / 1000)],
        ['Start of year', Math.floor(new Date(now.getFullYear(), 0, 1).getTime() / 1000)],
        ['1 hour ago', Math.floor((Date.now() - 3600000) / 1000)],
        ['1 day ago', Math.floor((Date.now() - 86400000) / 1000)],
        ['Y2K (2000-01-01)', 946684800],
        ['2038-01-19 end of 32-bit', 2147483647],
    ];
    var box = $('presets');
    presets.forEach(function (p) {
        var b = document.createElement('button');
        b.className = 'btn btn-outline-light btn-sm';
        b.textContent = p[0] + ' — ' + p[1];
        b.onclick = function () { $('ts-in').value = p[1]; tsToDate(); };
        box.appendChild(b);
    });
})();
dtNow();
tsToDate();
function $(id) { return document.getElementById(id); }
</script>
<?php page_footer(); ?>