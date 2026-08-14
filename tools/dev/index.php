<?php
require_once __DIR__ . '/../../functions.php';

start_session();
page_header('Dev Tools');
?>
<div class="container" style="max-width: 1100px;">
    <h1 class="h4 mb-1 reveal in-view">Dev Tools</h1>
    <p class="text-secondary mb-4 reveal in-view">Developer utilities: JSON formatting, regex testing, number base conversion, URL parsing and a markdown preview. All computed locally.</p>

    <div class="row g-4">

        <div class="col-md-6 reveal">
            <div class="card h-100"><div class="card-body">
                <h2 class="h6 mb-2">🧾 JSON Formatter</h2>
                <p class="text-secondary small">Format / validate / minify JSON. Errors reported inline.</p>
                <textarea id="json-in" class="form-control mb-2" rows="7" style="font-family:'JetBrains Mono',monospace;font-size:.8rem;">{"name":"kevbin","tools":["osint","diff","hash"],"offline":true}</textarea>
                <div class="d-flex gap-2 flex-wrap align-items-center">
                    <button class="btn btn-primary btn-sm" onclick="jsonFormat(2)">Format</button>
                    <button class="btn btn-outline-light btn-sm" onclick="jsonFormat(4)">Format (4 sp)</button>
                    <button class="btn btn-outline-light btn-sm" onclick="jsonMin()">Minify</button>
                    <button class="btn btn-outline-light btn-sm" onclick="jsonValid()">Validate</button>
                    <button class="btn btn-outline-light btn-sm" onclick="copyId('json-out')">Copy</button>
                </div>
                <textarea id="json-out" class="form-control mt-2" rows="8" readonly style="font-family:'JetBrains Mono',monospace;font-size:.8rem;"></textarea>
                <div id="json-msg" class="text-secondary small mt-1"></div>
            </div></div>
        </div>

        <div class="col-md-6 reveal">
            <div class="card h-100"><div class="card-body">
                <h2 class="h6 mb-2">🎯 Regex Tester</h2>
                <p class="text-secondary small">Test a JavaScript regex against your text. Live matches shown below.</p>
                <input id="rx-pattern" class="form-control mb-1" style="font-family:'JetBrains Mono',monospace;font-size:.85rem;" value="\b(word\w*)\b" placeholder="pattern here">
                <div class="mb-2 d-flex gap-3">
                    <div class="form-check"><input class="form-check-input" type="checkbox" id="rx-g" checked><label class="form-check-label small" for="rx-g">g</label></div>
                    <div class="form-check"><input class="form-check-input" type="checkbox" id="rx-i"><label class="form-check-label small" for="rx-i">i</label></div>
                    <div class="form-check"><input class="form-check-input" type="checkbox" id="rx-m"><label class="form-check-label small" for="rx-m">m</label></div>
                </div>
                <textarea id="rx-text" class="form-control mb-2" rows="5" style="font-family:'JetBrains Mono',monospace;font-size:.8rem;" oninput="runRegex()">Testing a keyword pattern catches words here.</textarea>
                <div id="rx-out" class="p-2 text-secondary small" style="font-family:'JetBrains Mono',monospace;font-size:.8rem;background:#0b0b0b;border:1px solid var(--line);border-radius:10px;min-height:60px;white-space:pre-wrap;"></div>
            </div></div>
        </div>

        <div class="col-md-6 reveal">
            <div class="card h-100"><div class="card-body">
                <h2 class="h6 mb-2">🔢 Base Converter</h2>
                <p class="text-secondary small">Convert between binary, octal, decimal and hexadecimal.</p>
                <div class="d-flex gap-2 align-items-center mb-2">
                    <select id="base-from" class="form-select form-select-sm" style="max-width:150px;">
                        <option value="2">Binary</option>
                        <option value="8">Octal</option>
                        <option value="10" selected>Decimal</option>
                        <option value="16">Hexadecimal</option>
                    </select>
                    <input id="base-val" class="form-control" style="font-family:'JetBrains Mono',monospace;font-size:.85rem;" value="255" oninput="convBase()">
                </div>
                <div id="base-grid" class="row g-2"></div>
            </div></div>
        </div>

        <div class="col-md-6 reveal">
            <div class="card h-100"><div class="card-body">
                <h2 class="h6 mb-2">🧭 URL Parser</h2>
                <p class="text-secondary small">Break any URL into its parts and decode query parameters.</p>
                <input id="url-in" class="form-control mb-2" style="font-family:'JetBrains Mono',monospace;font-size:.85rem;"
                    value="https://kevbin.ct.ws/tools/?tab=text&count=5#results" oninput="parseUrl(this.value)">
                <div id="url-out" class="row g-2"></div>
            </div></div>
        </div>

        <div class="col-md-6 reveal">
            <div class="card h-100"><div class="card-body">
                <h2 class="h6 mb-2">🔤 HTML Entities</h2>
                <p class="text-secondary small">Escape &amp;&lt;&gt; tags to safe HTML entity codes, or unescape them back. Handy when pasting code into markup.</p>
                <textarea id="html-in" class="form-control mb-2" rows="6" style="font-family:'JetBrains Mono',monospace;font-size:.8rem;">This <b>bold</b> & "quoted" → use &amp; &lt; &gt; for safety</textarea>
                <div class="d-flex gap-2 flex-wrap mb-2">
                    <button class="btn btn-primary btn-sm" onclick="htmlEnc()">Escape</button>
                    <button class="btn btn-outline-light btn-sm" onclick="htmlDec()">Unescape</button>
                    <button class="btn btn-outline-light btn-sm" onclick="copyId('html-out')">Copy</button>
                </div>
                <textarea id="html-out" class="form-control" rows="6" readonly style="font-family:'JetBrains Mono',monospace;font-size:.8rem;"></textarea>
            </div></div>
        </div>

        <div class="col-md-12 reveal">
            <div class="card"><div class="card-body">
                <h2 class="h6 mb-2">📝 Markdown Preview</h2>
                <p class="text-secondary small">Write markdown on the left, watch it render live on the right (tables, code, headings, links, lists supported).</p>
                <div class="row g-3">
                    <div class="col-md-6">
                        <textarea id="md-in" class="form-control" rows="12" style="font-family:'JetBrains Mono',monospace;font-size:.8rem;" oninput="renderMd()"># KevBin Dev Tools

Markdown formatting:

- **bold**, *italic*, `inline code`
- [links](https://kevbin.ct.ws)
- lists & > blockquotes

| Tool | Works |
|------|-------|
| JSON | yes |
| Regex | yes |

```lua
print("hello from lua")
```</textarea>
                    </div>
                    <div class="col-md-6">
                        <div id="md-out" class="p-3" style="background:#0b0b0b;border:1px solid var(--line);border-radius:10px;min-height:280px;"></div>
                    </div>
                </div>
            </div></div>
        </div>

    </div>
</div>

<script>
function $(id) { return document.getElementById(id); }
function copyId(id) { var t = $(id); t.select(); document.execCommand('copy'); }
function esc(s) { return s.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;'); }

function htmlEnc() {
    var s = $('html-in').value;
    var out = '';
    for (var i = 0; i < s.length; i++) {
        var c = s[i];
        var code = s.charCodeAt(i);
        if (c === '&') out += '&amp;';
        else if (c === '<') out += '&lt;';
        else if (c === '>') out += '&gt;';
        else if (c === '"') out += '&quot;';
        else if (c === "'") out += '&#39;';
        else if (code > 126) out += '&#x' + code.toString(16) + ';';
        else out += c;
    }
    $('html-out').value = out;
}
function htmlDec() {
    var named = { amp: '&', lt: '<', gt: '>', quot: '"', apos: "'", nbsp: ' ', copy: '\u00a9', reg: '\u00ae', trade: '\u2122', hellip: '\u2026' };
    var s = $('html-in').value;
    $('html-out').value = s.replace(/&#x([0-9a-f]+);/gi, function (m, h) { return String.fromCodePoint(parseInt(h, 16)); })
            .replace(/&#([0-9]+);/g, function (m, d) { return String.fromCodePoint(parseInt(d, 10)); })
            .replace(/&([a-z]+);/g, function (m, n) { return named[n] !== undefined ? named[n] : m; });
}

function jsonFormat(sp) {
    try {
        var v = JSON.parse($('json-in').value);
        $('json-out').value = JSON.stringify(v, null, sp === 2 ? 2 : 4);
        $('json-msg').textContent = 'Valid JSON.';
    } catch (e) { $('json-msg').textContent = 'Invalid JSON: ' + e.message; }
}
function jsonMin() {
    try { $('json-out').value = JSON.stringify(JSON.parse($('json-in').value)); $('json-msg').textContent = 'Minified.'; }
    catch (e) { $('json-msg').textContent = 'Invalid JSON: ' + e.message; }
}
function jsonValid() {
    try { JSON.parse($('json-in').value); $('json-msg').textContent = '✅ Valid JSON.'; }
    catch (e) { $('json-msg').textContent = '❌ Invalid JSON: ' + e.message; }
}

function runRegex() {
    var pat = $('rx-pattern').value;
    var flags = '';
    if ($('rx-g').checked) flags += 'g';
    if ($('rx-i').checked) flags += 'i';
    if ($('rx-m').checked) flags += 'm';
    var out = $('rx-out');
    try {
        var re = new RegExp(pat, flags);
        var text = $('rx-text').value;
        var matches = text.match(re);
        if (!matches) { out.textContent = 'No matches.'; return; }
        if (matches.length > 20) matches = matches.slice(0, 20);
        out.textContent = matches.length + ' match(es):\n' + matches.join('\n');
    } catch (e) {
        out.textContent = 'Invalid regex: ' + e.message;
    }
}

function convBase() {
    var from = parseInt($('base-from').value, 10);
    var v = $('base-val').value.trim();
    var num;
    try {
        num = parseInt(v.replace(/^0x/i, ''), from);
    } catch (e) { num = NaN; }
    if (isNaN(num)) { $('base-grid').innerHTML = '<div class="col-12 text-danger small">Invalid number for base ' + from + '.</div>'; return; }
    $('base-grid').innerHTML =
        '<div class="col-12"><div class="text-secondary small">Binary</div><div class="form-control code" style="font-family:JetBrains Mono,monospace;font-size:.8rem;">' + (num >>> 0).toString(2) + '</div></div>' +
        '<div class="col-12"><div class="text-secondary small">Octal</div><div class="form-control code" style="font-family:JetBrains Mono,monospace;font-size:.8rem;">' + (num >>> 0).toString(8) + '</div></div>' +
        '<div class="col-12"><div class="text-secondary small">Decimal</div><div class="form-control code" style="font-family:JetBrains Mono,monospace;font-size:.8rem;">' + (num >>> 0).toString(10) + '</div></div>' +
        '<div class="col-12"><div class="text-secondary small">Hexadecimal</div><div class="form-control code" style="font-family:JetBrains Mono,monospace;font-size:.8rem;">0x' + (num >>> 0).toString(16).toUpperCase() + '</div></div>';
}

function parseUrl(u) {
    var out = $('url-out');
    var p;
    try { p = new URL(u); } catch (e) {
        out.innerHTML = '<div class="col-12 text-danger small">Invalid URL.</div>';
        return;
    }
    var parts = [
        ['Protocol', p.protocol], ['Username', p.username || '—'], ['Host', p.host], ['Hostname', p.hostname],
        ['Port', p.port || '—'], ['Path', p.pathname], ['Hash', p.hash || '—'], ['Origin', p.origin]
    ];
    var html = '';
    parts.forEach(function (x) {
        html += '<div class="col-12 col-md-6"><div class="text-secondary small">' + x[0] + '</div>' +
            '<div class="form-control code mb-1" style="font-family:JetBrains Mono,monospace;font-size:.8rem;">' + esc(x[1]) + '</div></div>';
    });
    // query params
    var params = new URLSearchParams(p.search);
    if (params.toString()) {
        html += '<div class="col-12"><div class="text-secondary small mt-2">Query parameters</div></div>';
        params.forEach(function (val, key) {
            html += '<div class="col-12"><div class="form-control code mb-1" style="font-family:JetBrains Mono,monospace;font-size:.8rem;">' +
                esc(key) + ' = ' + esc(val) + '</div></div>';
        });
    }
    out.innerHTML = html;
}

function renderMd() {
    var src = $('md-in').value;
    var html = '';
    var lines = src.split('\n');
    var inCode = false;
    var codeBuf = [];
    var inTable = false;
    var tableBuf = [];
    var listOpen = false;
    var out = [];
    function flushList() {
        if (listOpen) { out.push('</ul>'); listOpen = false; }
    }
    function flushTable() {
        if (inTable && tableBuf.length) {
            var header = tableBuf[0], aligns = tableBuf[1], rows = tableBuf.slice(2);
            var cols = header.split('|').slice(1, -1);
            out.push('<table class="table table-sm table-dark" style="max-width:100%;"><thead><tr>');
            cols.forEach(function (c) { out.push('<th>' + mInline(c) + '</th>'); });
            out.push('</tr></thead><tbody>');
            rows.forEach(function (r) {
                var cells = r.split('|').slice(1, -1);
                out.push('<tr>');
                cells.forEach(function (cell, idx) { out.push('<td>' + mInline(cell) + '</td>'); });
                out.push('</tr>');
            });
            if (rows.length && rows[0].trim().length === 0) rows = [];
            out.push('</tbody></table>');
            tableBuf = [];
        }
    }
    for (var i = 0; i < lines.length; i++) {
        var l = lines[i];
        var t = l.trim();
        if (t.indexOf('```') === 0) {
            flushList();
            if (inCode) {
                out.push('<pre style="background:#0b0b0b;padding:.75rem;border:1px solid var(--line);border-radius:8px;font-family:JetBrains Mono,monospace;font-size:.8rem;">' + esc(codeBuf.join('\n')) + '</pre>');
                codeBuf = [];
                inCode = false;
            } else {
                inCode = true;
            }
            continue;
        }
        if (inCode) { codeBuf.push(l); continue; }
        if (/^\|/.test(t) && /\|/.test(t.slice(1))) {
            flushList();
            if (inTable && /^\|?\s*:?-{2,}/.test(t)) { tableBuf.push(t); continue; }
            if (!inTable || tableBuf.length < 2) { inTable = true; tableBuf.push(t); continue; }
            flushTable();
            inTable = true;
            tableBuf = [t];
            continue;
        } else {
            flushTable();
        }
        if (/^\s*[-*]\s+/.test(t)) {
            if (!listOpen) { out.push('<ul>'); listOpen = true; }
            out.push('<li>' + mInline(t.replace(/^\s*[-*]\s+/, '')) + '</li>');
            continue;
        } else {
            flushList();
        }
        if (t === '') { if (out[out.length - 1] !== '') out.push('<p></p>'); continue; }
        if (/^#{1,6}\s/.test(t)) {
            var lvl = t.match(/^#+/)[0].length;
            out.push('<h' + Math.min(lvl + 2, 6) + '>' + mInline(t.replace(/^#+\s*/, '')) + '</h' + Math.min(lvl + 2, 6) + '>');
        } else if (/^>\s?/.test(t)) {
            out.push('<blockquote class="border-start ps-3 text-secondary">' + mInline(t.replace(/^>\s?/, '')) + '</blockquote>');
        } else if (/^(-{3,}|\*{3,})$/.test(t)) {
            out.push('<hr>');
        } else {
            out.push('<p>' + mInline(t) + '</p>');
        }
    }
    flushList(); flushTable();
    if (inCode) out.push('<pre style="background:#0b0b0b;padding:.75rem;border:1px solid var(--line);border-radius:8px;font-family:JetBrains Mono,monospace;font-size:.8rem;">' + esc(codeBuf.join('\n')) + '</pre>');
    $('md-out').innerHTML = out.join('\n');
}
function mInline(s) {
    return esc(s)
        .replace(/`([^`]+)`/g, '<code style="background:#1b1b1b;padding:1px 6px;border-radius:6px;">$1</code>')
        .replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>')
        .replace(/\*([^*]+)\*/g, '<em>$1</em>')
        .replace(/\[([^\]]+)\]\(([^)]+)\)/g, '<a href="$2" target="_blank" rel="noopener">$1</a>');
}

jsonFormat(2);
runRegex();
convBase();
parseUrl($('url-in').value);
renderMd();
</script>
<?php page_footer(); ?>