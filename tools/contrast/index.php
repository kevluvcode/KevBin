<?php
require_once __DIR__ . '/../../functions.php';

start_session();
page_header('Contrast Checker');
?>
<div class="container" style="max-width: 800px;">
    <h1 class="h4 mb-1 reveal in-view">👁 Contrast Checker</h1>
    <p class="text-secondary mb-4 reveal in-view">Check the WCAG contrast ratio between two colors and whether text passes AA / AAA. Instant, local, private.</p>

    <div class="card reveal">
        <div class="card-body">
            <div class="row g-4">
                <div class="col-md-4">
                    <label class="form-label">Foreground (text)</label>
                    <input id="ct-fg" class="form-control" type="color" value="#f2f2f2" oninput="checkContrast()">
                    <input id="ct-fg-hex" class="form-control mt-2" style="font-family:'JetBrains Mono',monospace;font-size:.85rem;" value="#f2f2f2" oninput="fromHex('fg')">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Background</label>
                    <input id="ct-bg" class="form-control" type="color" value="#070707" oninput="checkContrast()">
                    <input id="ct-bg-hex" class="form-control mt-2" style="font-family:'JetBrains Mono',monospace;font-size:.85rem;" value="#070707" oninput="fromHex('bg')">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Ratio</label>
                    <div id="ct-ratio" class="p-3 text-center" style="background:#0b0b0b;border:1px solid var(--line);border-radius:10px;font-size:1.5rem;font-weight:700;"></div>
                </div>
            </div>
            <div class="row g-3 mt-3" id="ct-results"></div>
            <div class="mt-3 p-3" id="ct-preview" style="border:1px solid var(--line);border-radius:10px;text-align:center;">
                <span id="ct-sample">The quick brown fox jumps over the lazy dog.</span>
            </div>
        </div>
    </div>
</div>
<script>
function hexToRgb(hex) {
    var h = hex.replace('#', '');
    if (h.length === 3) h = h.split('').map(function (c) { return c + c; }).join('');
    return [parseInt(h.slice(0, 2), 16), parseInt(h.slice(2, 4), 16), parseInt(h.slice(4, 6), 16)];
}
function lum(rgb) {
    var c = rgb.map(function (v) {
        v = v / 255;
        return v <= 0.03928 ? v / 12.92 : Math.pow((v + 0.055) / 1.055, 2.4);
    });
    return 0.2126 * c[0] + 0.7152 * c[1] + 0.0722 * c[2];
}
function ratio(fg, bg) {
    var a = lum(fg), b = lum(bg);
    var hi = Math.max(a, b), lo = Math.min(a, b);
    return (hi + 0.05) / (lo + 0.05);
}
function checkContrast() {
    var fg = hexToRgb($('ct-fg').value), bg = hexToRgb($('ct-bg').value);
    $('ct-fg-hex').value = $('ct-fg').value;
    $('ct-bg-hex').value = $('ct-bg').value;
    var r = ratio(fg, bg);
    $('ct-ratio').textContent = r.toFixed(2) + ':1';
    var rows = [
        ['AA normal text', r >= 4.5],
        ['AA large text', r >= 3.0],
        ['AAA normal text', r >= 7.0],
        ['AAA large text', r >= 4.5],
    ];
    var html = '';
    rows.forEach(function (x) {
        html += '<div class="col-md-6"><div class="p-2 d-flex justify-content-between align-items-center" style="background:' +
            (x[1] ? '#0f2e1a' : '#2e0f0f') + ';border:1px solid var(--line);border-radius:10px;">' +
            '<span>' + x[0] + '</span><span class="badge ' + (x[1] ? 'text-bg-success' : 'text-bg-danger') + '">' + (x[1] ? 'PASS' : 'FAIL') + '</span></div></div>';
    });
    $('ct-results').innerHTML = html;
    $('ct-sample').style.color = $('ct-fg').value;
    $('ct-preview').style.background = $('ct-bg').value;
}
function fromHex(which) {
    var val = $('ct-' + which + '-hex').value.trim();
    if (/^#?[0-9a-fA-F]{6}$/.test(val)) {
        if (val[0] !== '#') val = '#' + val;
        $('ct-' + which).value = val;
        checkContrast();
    }
}
checkContrast();
function $(id) { return document.getElementById(id); }
</script>
<?php page_footer(); ?>