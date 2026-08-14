<?php
require_once __DIR__ . '/../../functions.php';

start_session();
page_header('Hex Dump');
?>
<div class="container" style="max-width: 1000px;">
    <h1 class="h4 mb-1 reveal in-view">📦 Hex Dump</h1>
    <p class="text-secondary mb-4 reveal in-view">View any text as a classic hexdump with offsets, hex bytes and the ASCII column. Local only.</p>

    <div class="card reveal">
        <div class="card-body">
            <div class="row g-3 mb-3">
                <div class="col-md-6">
                    <label class="form-label">Input text</label>
                    <textarea id="hd-in" class="form-control" rows="10" style="font-family:'JetBrains Mono',monospace;font-size:.8rem;" oninput="hexDump()">Hello KevBin!
This is a hex dump test 0123456789.</textarea>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Row width</label>
                    <select id="hd-width" class="form-select" onchange="hexDump()">
                        <option value="8">8 bytes</option>
                        <option value="16" selected>16 bytes</option>
                        <option value="32">32 bytes</option>
                    </select>
                    <label class="form-label mt-3">Output</label>
                    <div style="background:#0b0b0b;border:1px solid var(--line);border-radius:10px;">
                        <textarea id="hd-out" class="form-control" rows="10" readonly style="font-family:'JetBrains Mono',monospace;font-size:.75rem;background:transparent;border:none;"></textarea>
                    </div>
                </div>
            </div>
            <div id="hd-stats" class="text-secondary small"></div>
        </div>
    </div>
</div>
<script>
function hexDump() {
    var s = $('hd-in').value;
    var width = parseInt($('hd-width').value, 10);
    var bytes = [];
    for (var i = 0; i < s.length; i++) {
        var c = s.charCodeAt(i);
        bytes.push(c & 0xff);
        if (c > 0x7f) bytes.push((c >> 8) & 0xff);
    }
    var lines = [];
    for (var off = 0; off < bytes.length; off += width) {
        var chunk = bytes.slice(off, off + width);
        var hex = chunk.map(function (b) { return b.toString(16).toUpperCase().padStart(2, '0'); }).join(' ');
        hex = hex.padEnd(width * 3 - 1, ' ');
        var ascii = chunk.map(function (b) { return b >= 32 && b < 127 ? String.fromCharCode(b) : '.'; }).join('');
        lines.push(off.toString(16).toUpperCase().padStart(8, '0') + '  ' + hex + '  |' + ascii + '|');
    }
    $('hd-out').value = lines.length ? lines.join('\n') : '(empty input)';
    $('hd-stats').textContent = bytes.length + ' bytes (' + s.length + ' chars counted as ' + bytes.length + ' bytes) · ' + lines.length + ' rows';
}
hexDump();
function $(id) { return document.getElementById(id); }
</script>
<?php page_footer(); ?>