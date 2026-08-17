<?php
require_once __DIR__ . '/../../functions.php';

start_session();
$GLOBALS['_meta'] = [
    'description' => 'Free online JSON formatter and validator. Pretty-print minified JSON with clean indentation, minify it back, and get instant syntax error reports. 100% in your browser.',
    'keywords' => 'json formatter, json validator, pretty print json, beautify json, json parser, online json tool',
];
page_header('JSON Formatter & Validator — Pretty Print Minified JSON');
?>
<div class="container" style="max-width: 980px;">
    <h1 class="h4 mb-2 reveal in-view">JSON Formatter & Validator</h1>
    <p class="text-secondary mb-1 reveal in-view">Paste any JSON — minified, copied straight from an API or config file — and this free tool will pretty-print it with clean indentation, validate it, and flag the exact line of any syntax error. Switch to a 2- or 4-space indent, or minify back down. Nothing is uploaded; it all runs in your browser.</p>
    <p class="text-secondary mb-4 reveal in-view">JSON (JavaScript Object Notation) is the most common data format on the web — API responses, database dumps and configs all use it. When an endpoint returns a single unreadable line, a formatter turns it into a browsable structure in seconds.</p>

    <div class="card reveal in-view">
        <div class="card-body">
            <div class="d-flex flex-wrap gap-2 align-items-center mb-2">
                <select id="js-indent" class="form-select" style="max-width:120px;">
                    <option value="2">2 spaces</option>
                    <option value="4" selected>4 spaces</option>
                    <option value="1">1 tab</option>
                </select>
                <button class="btn btn-primary btn-sm" onclick="fmtJson()">Format</button>
                <button class="btn btn-outline-light btn-sm" onclick="minJson()">Minify</button>
                <button class="btn btn-outline-light btn-sm" onclick="validateJson()">Validate only</button>
                <button class="btn btn-outline-light btn-sm" onclick="clearJson()">Clear</button>
            </div>
            <label class="form-label small text-secondary">Input JSON</label>
            <textarea id="js-in" class="form-control mb-2" rows="6" style="font-family:'JetBrains Mono',monospace;font-size:.85rem;" placeholder='{"hello":"world","array":[1,2,3]}'></textarea>
            <label class="form-label small text-secondary">Formatted JSON</label>
            <div class="input-group">
                <textarea id="js-out" class="form-control" rows="10" readonly style="font-family:'JetBrains Mono',monospace;font-size:.85rem;"></textarea>
                <button class="btn btn-outline-light" onclick="copyJson()">Copy</button>
            </div>
            <div id="js-msg" class="form-text mt-2"></div>
        </div>
    </div>

    <h2 class="h6 mt-4 reveal in-view" style="border-bottom:1px solid var(--line);padding-bottom:.5rem;">Why use a JSON formatter?</h2>
    <p class="text-secondary small reveal in-view">APIs and logs usually emit JSON on a single line to save bandwidth — great for machines, terrible for humans. Formatting adds indentation so objects and arrays become visibly nested, making it trivial to spot missing commas, unbalanced braces and wrong types before they break your code. The validator reports the line and column of the first syntax error so you can fix it fast.</p>
    <h2 class="h6 mt-4 reveal in-view" style="border-bottom:1px solid var(--line);padding-bottom:.5rem;">Example</h2>
    <p class="text-secondary small reveal in-view">Paste <code>{"user":"KevBin","tags":["dev","security"],"meta":{"free":true}}</code> and click <strong>Format</strong> — it becomes a clearly indented, readable object tree that you can copy straight into a linter or documentation.</p>
</div>

<script>
function $j(id) { return document.getElementById(id); }
function parseJson() {
    return JSON.parse($j('js-in').value);
}
function setMsg(text, ok) { var m = $j('js-msg'); m.textContent = text; m.className = 'form-text mt-2 ' + (ok ? 'text-success' : 'text-danger'); }
function fmtJson() {
    try { var el = JSON.parse($j('js-in').value || '{}'); $j('js-out').value = JSON.stringify(el, null, ($j('js-indent').value === '1' ? '\t' : +$j('js-indent').value)); setMsg('✅ Valid JSON — formatted.', true); }
    catch (e) { setMsg('❌ ' + e.message, false); }
}
function minJson() {
    try { $j('js-out').value = JSON.stringify(JSON.parse($j('js-in').value || '{}')); setMsg('✅ Valid JSON — minified.', true); }
    catch (e) { setMsg('❌ ' + e.message, false); }
}
function validateJson() {
    try { JSON.parse($j('js-in').value); setMsg('✅ Valid JSON.', true); }
    catch (e) { setMsg('❌ ' + e.message, false); }
}
function clearJson() { $j('js-in').value = ''; $j('js-out').value = ''; $j('js-msg').textContent = ''; }
function copyJson() { var t = $j('js-out'); t.select(); document.execCommand('copy'); }
</script>
<?php page_footer(); ?>