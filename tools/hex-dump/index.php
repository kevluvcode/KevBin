<?php
require_once __DIR__ . '/../../functions.php';

start_session();
$GLOBALS['_meta'] = [
    'description' => 'Free online hex dump tool. Render any text, file contents or binary data as a classic xxd-style hexdump with byte offsets, hex columns and an ASCII column, with configurable bytes per row.',
    'keywords' => 'hex dump, hexdump, xxd, hex viewer, hexadecimal, binary viewer',
];
page_header('Hex Dump — View Data as Hex & ASCII');
?>
<div class="container" style="max-width: 980px;">
    <h1 class="h4 mb-2 reveal in-view">Hex Dump</h1>
    <p class="text-secondary mb-1 reveal in-view">Turn any text into a classic <code>xxd</code>-style hex dump: byte offsets, two-byte hex groups and an ASCII column on the right. Perfect for debugging binary payloads, inspecting file magic bytes and understanding how your data is actually stored.</p>
    <p class="text-secondary mb-4 reveal in-view">A hex dump shows the raw bytes of a value rather than its rendered glyphs — so a space, a newline or a non-printable control character becomes visible as an exact hexadecimal value. Every 16 bytes form one row, with a running offset so you can pinpoint any byte’s position.</p>

    <div class="card reveal in-view">
        <div class="card-body">
            <div class="d-flex flex-wrap gap-2 align-items-center mb-2">
                <button class="btn btn-primary btn-sm" onclick="runDump()">Dump</button>
                <select id="dump-per" class="form-select" style="max-width:130px;" onchange="runDump()">
                    <option value="8">8 bytes/row</option>
                    <option value="16" selected>16 bytes/row</option>
                    <option value="32">32 bytes/row</option>
                </select>
                <button class="btn btn-outline-light btn-sm" onclick="copyDump()">Copy dump</button>
            </div>
            <label class="form-label small text-secondary">Input (text or hex)</label>
            <textarea id="dump-in" class="form-control mb-2" rows="4" style="font-family:'JetBrains Mono',monospace;font-size:.85rem;" placeholder="Hello KevBin &#10;Line 2 with tabs&#9;here"></textarea>
            <label class="form-label small text-secondary">Hex dump</label>
            <textarea id="dump-out" class="form-control" rows="10" readonly style="font-family:'JetBrains Mono',monospace;font-size:.75rem;white-space:pre;overflow-x:auto;"></textarea>
        </div>
    </div>

    <h2 class="h6 mt-4 reveal in-view" style="border-bottom:1px solid var(--line);padding-bottom:.5rem;">Reading a hex dump</h2>
    <p class="text-secondary small reveal in-view">The left column is the byte offset (hex) from the start of the data. The middle columns are the bytes themselves, two per byte-pair. The right column maps each byte to its printable ASCII character (dots for non-printable bytes). File formats often start with recognizable "magic" sequences — for example PNG files begin with the bytes <code>89 50 4E 47</code>. You can paste hex like <code>89 50 4E 47</code> too; it will be parsed as bytes rather than text.</p>
</div>

<script>
function $d(id) { return document.getElementById(id); }
function hexStringToBytes(s) {
    var cleaned = s.replace(/[^0-9a-fA-F]/g, '');
    if (cleaned.length === 0) return null;
    if (cleaned.length % 2 !== 0) return null;
    var bytes = [];
    for (var i = 0; i < cleaned.length; i += 2) bytes.push(parseInt(cleaned.substr(i, 2), 16));
    return { bytes: bytes, hex: true };
}
function runDump() {
    var raw = $d('dump-in').value;
    var per = +$d('dump-per').value;
    var out = '';
    var bytes, looksHex = /^[0-9a-fA-F\s]+$/.test(raw) && raw.trim().length > 0 && raw.trim().length % 2 === 0 && raw.replace(/\s/g,'').length > 2;
    if (looksHex && (hexStringToBytes(raw) !== null)) {
        var h = hexStringToBytes(raw);
        bytes = h.bytes;
    } else {
        bytes = new TextEncoder().encode(raw);
    }
    for (var off = 0; off < bytes.length; off += per) {
        var hex = '', ascii = '';
        for (var i = 0; i < per; i++) {
            var b = bytes[off + i];
            if (b === undefined) { hex += '   '; ascii += ' '; continue; }
            hex += String(b).toString(16).padStart(2, '0') + ' ';
            ascii += (b >= 32 && b < 127) ? String.fromCharCode(b) : '.';
            if (i === Math.ceil(per / 2) - 1) hex += ' ';
        }
        out += off.toString(16).padStart(8, '0') + ':  ' + hex.trimEnd().padEnd(per * 3 + (per % 16 === 0 ? 0 : 1)) + '  ' + ascii + '\n';
    }
    $d('dump-out').value = out || '(empty)';
}
function copyDump() { var t = $d('dump-out'); t.select(); document.execCommand('copy'); }
runDump();
</script>
<?php page_footer(); ?>