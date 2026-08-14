<?php
require_once __DIR__ . '/../../functions.php';

start_session();
page_header('Character Inspector');
?>
<div class="container" style="max-width: 800px;">
    <h1 class="h4 mb-1 reveal in-view">🔤 Character Inspector</h1>
    <p class="text-secondary mb-4 reveal in-view">Explore any character: code points, UTF-8 bytes, HTML entity and more. Instant, local.</p>

    <div class="card reveal">
        <div class="card-body">
            <div class="row g-3 mb-3">
                <div class="col-md-6">
                    <label class="form-label">Character</label>
                    <input id="cc-char" class="form-control" maxlength="8" value="😀" style="font-size:1rem;" oninput="inspectChar()">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Code point (dec or hex like 0x1F600)</label>
                    <input id="cc-code" class="form-control" style="font-family:'JetBrains Mono',monospace;font-size:.85rem;" value="0x1F600" oninput="inspectCode()">
                </div>
            </div>
            <div id="cc-out" class="p-2" style="background:#0b0b0b;border:1px solid var(--line);border-radius:10px;font-family:'JetBrains Mono',monospace;font-size:.85rem;min-height:120px;"></div>
        </div>
    </div>
</div>
<script>
function utf8Bytes(s) {
    var out = [];
    for (var i = 0; i < s.length; i++) {
        var code = s.codePointAt(i);
        if (code > 0xffff) i++;
        var bytes = [];
        if (code < 0x80) bytes = [code];
        else if (code < 0x800) bytes = [0xC0 | (code >> 6), 0x80 | (code & 63)];
        else if (code < 0x10000) bytes = [0xE0 | (code >> 12), 0x80 | ((code >> 6) & 63), 0x80 | (code & 63)];
        else bytes = [0xF0 | (code >> 18), 0x80 | ((code >> 12) & 63), 0x80 | ((code >> 6) & 63), 0x80 | (code & 63)];
        out.push(bytes.map(function (b) { return b.toString(16).padStart(2, '0'); }).join(' '));
    }
    return out.join(' ');
}
function describe(code) {
    var names = {
        0x20: 'SPACE', 0x09: 'TAB', 0x0a: 'LINE FEED', 0x0d: 'CARRIAGE RETURN',
        0x1F600: 'GRINNING FACE', 0x2764: 'HEAVY BLACK HEART'
    };
    return names[code] || '';
}
function render(code) {
    var cp = 'U+' + code.toString(16).toUpperCase().padStart(4, '0');
    var ch = String.fromCodePoint(code);
    var html =
        '<div>Character: <b style="font-size:1.6rem;">' + esc(ch) + '</b></div>' +
        '<div>Code point: <b>' + cp + '</b> (dec ' + code + ', hex 0x' + code.toString(16) + ')</div>' +
        '<div>UTF-8 bytes: <b>' + utf8Bytes(ch) + '</b></div>' +
        '<div>HTML entity: <b>&#' + code + ';</b>' + (code <= 0xFFFF ? ' <b>&#x' + code.toString(16).toUpperCase() + ';</b>' : '') + '</div>' +
        '<div>Length (UTF-16 code units): <b>' + ch.length + '</b></div>' +
        (describe(code) ? '<div>Name: <b>' + describe(code) + '</b></div>' : '');
    $('cc-out').innerHTML = html;
}
function inspectChar() {
    var s = $('cc-char').value;
    if (!s) { $('cc-out').textContent = 'Type a character above.'; return; }
    var code = s.codePointAt(0);
    $('cc-code').value = '0x' + code.toString(16);
    render(code);
}
function inspectCode() {
    var v = $('cc-code').value.trim();
    var code;
    if (/^0x/i.test(v)) code = parseInt(v, 16);
    else code = parseInt(v, 10);
    if (isNaN(code) || code < 0 || code > 0x10FFFF) { $('cc-out').textContent = 'Enter a valid code point (0 – 0x10FFFF).'; return; }
    try {
        $('cc-char').value = String.fromCodePoint(code);
        render(code);
    } catch (e) { $('cc-out').textContent = 'Invalid code point: ' + e.message; }
}
function esc(s) {
    return s.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
}
inspectChar();
function $(id) { return document.getElementById(id); }
</script>
<?php page_footer(); ?>