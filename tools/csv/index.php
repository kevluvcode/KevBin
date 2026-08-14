<?php
require_once __DIR__ . '/../../functions.php';

start_session();
page_header('CSV / JSON Converter');
?>
<div class="container" style="max-width: 1000px;">
    <h1 class="h4 mb-1 reveal in-view">🔄 CSV ↔ JSON Converter</h1>
    <p class="text-secondary mb-4 reveal in-view">Turn CSV or TSV into JSON and back. Handles quoted fields, commas inside quotes and headers. All done locally.</p>

    <div class="card reveal">
        <div class="card-body">
            <div class="row g-3 mb-3">
                <div class="col-md-4">
                    <label class="form-label">Delimiter</label>
                    <select id="csv-delim" class="form-select" onchange="csvToJson()">
                        <option value=",">Comma (CSV)</option>
                        <option value=";">Semicolon</option>
                        <option value="\t">Tab (TSV)</option>
                        <option value="|">Pipe</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Direction</label>
                    <select id="csv-dir" class="form-select" onchange="swap()">
                        <option value="csv2json">CSV → JSON</option>
                        <option value="json2csv">JSON → CSV</option>
                    </select>
                </div>
                <div class="col-md-4 d-flex align-items-end">
                    <button class="btn btn-outline-light btn-sm" onclick="swap()" style="width:100%">⇅ Swap input/output</button>
                </div>
            </div>
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Input</label>
                    <textarea id="csv-in" class="form-control" rows="16" style="font-family:'JetBrains Mono',monospace;font-size:.8rem;" oninput="csvToJson()">name,age,city
Alice,30,New York
"Bob, Jr.",25,"Paris, France"
Cara,28,"Berlin"</textarea>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Output</label>
                    <textarea id="csv-out" class="form-control" rows="16" readonly style="font-family:'JetBrains Mono',monospace;font-size:.8rem;"></textarea>
                </div>
            </div>
            <div id="csv-msg" class="text-secondary small mt-2"></div>
        </div>
    </div>
</div>
<script>
function parseCsv(text, delim) {
    var rows = [];
    var row = [];
    var field = '';
    var inQuotes = false;
    for (var i = 0; i < text.length; i++) {
        var c = text[i];
        if (inQuotes) {
            if (c === '"') {
                if (text[i + 1] === '"') { field += '"'; i++; }
                else inQuotes = false;
            } else field += c;
        } else {
            if (c === '"') inQuotes = true;
            else if (c === delim) { row.push(field); field = ''; }
            else if (c === '\n' || c === '\r') {
                if (field !== '' || row.length) { row.push(field); rows.push(row); }
                field = '';
                row = [];
                if (c === '\r' && text[i + 1] === '\n') i++;
            } else field += c;
        }
    }
    if (field !== '' || row.length) { row.push(field); rows.push(row); }
    return rows.filter(function (r) { return r.some(function (c) { return c.trim() !== ''; }); });
}
function csvToJson() {
    var delim = $('csv-delim').value === '\\t' ? '\t' : $('csv-delim').value;
    var text = $('csv-in').value.replace(/\r\n?/g, '\n');
    var rows = parseCsv(text, delim);
    if (!rows.length) { $('csv-out').value = ''; $('csv-msg').textContent = 'No rows.'; return; }
    var headers = rows[0].map(function (h, i) { return h.trim() === '' ? 'col' + (i + 1) : h.trim(); });
    var out = rows.slice(1).map(function (r) {
        var obj = {};
        headers.forEach(function (h, i) {
            var v = r[i];
            var num = v !== '' && v !== null && !isNaN(Number(v)) && v.trim() !== '' ? Number(v) : v;
            obj[h] = (num !== null && v !== undefined && Number.isFinite(num)) ? num : (v === undefined ? '' : v);
        });
        return obj;
    });
    $('csv-out').value = JSON.stringify(out, null, 2);
    $('csv-msg').textContent = headers.length + ' columns · ' + out.length + ' rows parsed.';
}
function jsonToCsv() {
    var data;
    try { data = JSON.parse($('csv-in').value); } catch (e) { $('csv-out').value = ''; $('csv-msg').textContent = 'Invalid JSON: ' + e.message; return; }
    var arr = Array.isArray(data) ? data : [data];
    var headers = [];
    arr.forEach(function (o) { Object.keys(o).forEach(function (k) { if (headers.indexOf(k) === -1) headers.push(k); }); });
    var delim = $('csv-delim').value === '\\t' ? '\t' : $('csv-delim').value;
    function cell(v) {
        v = v === null || v === undefined ? '' : String(v);
        return /[",\n\r\t]/.test(v) ? '"' + v.replace(/"/g, '""') + '"' : v;
    }
    var lines = [headers.map(cell).join(delim)];
    arr.forEach(function (o) { lines.push(headers.map(function (h) { return cell(o[h]); }).join(delim)); });
    $('csv-out').value = lines.join('\n');
    $('csv-msg').textContent = headers.length + ' columns · ' + arr.length + ' rows written.';
}
function swap() {
    var dir = $('csv-dir').value;
    var tmp = $('csv-in').value;
    $('csv-in').value = $('csv-out').value;
    $('csv-out').value = tmp;
    if (dir === 'csv2json') jsonToCsv(); else csvToJson();
}
$('csv-dir').onchange = function () { $('csv-dir').value === 'csv2json' ? csvToJson() : jsonToCsv(); };
csvToJson();
function $(id) { return document.getElementById(id); }
</script>
<?php page_footer(); ?>