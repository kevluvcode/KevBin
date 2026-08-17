<?php
require_once __DIR__ . '/../../functions.php';

start_session();
$GLOBALS['_meta'] = [
    'description' => 'Free online color converter. Convert colors between HEX, RGB, HSL and CMYK with a live swatch preview and one-click copy — plus WCAG contrast hints. 100% in your browser.',
    'keywords' => 'color converter, hex to rgb, hex to hsl, rgb to hex, color code converter, color picker',
];
page_header('Color Converter — HEX, RGB, HSL & CMYK');
?>
<div class="container" style="max-width: 900px;">
    <h1 class="h4 mb-2 reveal in-view">Color Converter</h1>
    <p class="text-secondary mb-1 reveal in-view">Convert any color between <strong>HEX</strong>, <strong>RGB</strong>, <strong>HSL</strong> and <strong>CMYK</strong> in real time — type or paste a value, or grab one from the color picker. A live swatch preview shows exactly what you're working with, and every format gets a one-click copy button.</p>
    <p class="text-secondary mb-4 reveal in-view">Designers and developers constantly juggle color notations: CSS uses HEX and RGB, design tools favour HSL, print uses CMYK. This free converter removes the guesswork — no sign-up, nothing you enter is uploaded.</p>

    <div class="card reveal in-view">
        <div class="card-body">
            <div class="d-flex flex-wrap gap-2 align-items-center mb-3">
                <input type="color" id="cc-pick" class="form-control form-control-color" value="#5865f2" style="width:60px;height:40px;" oninput="fromHex(this.value)">
                <input id="cc-input" class="form-control" style="max-width:220px;font-family:'JetBrains Mono',monospace;font-size:.9rem;" value="#5865f2" oninput="fromInput(this.value)">
                <span class="form-text">type #hex, rgb(), hsl() or cmyk() — or use the picker</span>
            </div>
            <div id="cc-swab" class="mb-3" style="height:70px;border-radius:12px;border:1px solid var(--line);"></div>

            <div class="row g-3">
                <div class="col-md-6"><label class="form-label small text-secondary">HEX</label><div class="input-group"><input id="cc-hex" class="form-control" readonly style="font-family:'JetBrains Mono',monospace;font-size:.85rem;"><button class="btn btn-outline-light btn-sm" onclick="copyCc('cc-hex')">Copy</button></div></div>
                <div class="col-md-6"><label class="form-label small text-secondary">RGB</label><div class="input-group"><input id="cc-rgb" class="form-control" readonly style="font-family:'JetBrains Mono',monospace;font-size:.85rem;"><button class="btn btn-outline-light btn-sm" onclick="copyCc('cc-rgb')">Copy</button></div></div>
                <div class="col-md-6"><label class="form-label small text-secondary">HSL</label><div class="input-group"><input id="cc-hsl" class="form-control" readonly style="font-family:'JetBrains Mono',monospace;font-size:.85rem;"><button class="btn btn-outline-light btn-sm" onclick="copyCc('cc-hsl')">Copy</button></div></div>
                <div class="col-md-6"><label class="form-label small text-secondary">CMYK</label><div class="input-group"><input id="cc-cmyk" class="form-control" readonly style="font-family:'JetBrains Mono',monospace;font-size:.85rem;"><button class="btn btn-outline-light btn-sm" onclick="copyCc('cc-cmyk')">Copy</button></div></div>
            </div>
            <div id="cc-contrast" class="form-text mt-3"></div>
        </div>
    </div>

    <h2 class="h6 mt-4 reveal in-view" style="border-bottom:1px solid var(--line);padding-bottom:.5rem;">HEX vs RGB vs HSL vs CMYK</h2>
    <p class="text-secondary small reveal in-view"><strong>HEX</strong> (<code>#RRGGBB</code>) is RGB in hexadecimal — the most common CSS notation. <strong>RGB</strong> gives each red/green/blue channel a 0–255 value for screens. <strong>HSL</strong> (hue, saturation, lightness) is the most human-friendly: it separates the colour wheel position from how washed-out and how bright the colour is. <strong>CMYK</strong> (cyan, magenta, yellow, key/black) is the print model — the conversion here is a standard approximation, so for professional print work always proof against a colour profile.</p>
</div>

<script>
function $cc(id) { return document.getElementById(id); }
function copyCc(id) { var t = $cc(id); t.select(); document.execCommand('copy'); }

function hexToRgb(hex) {
    hex = hex.replace('#', '');
    if (hex.length === 3) hex = hex.split('').map(function (c) { return c + c; }).join('');
    var n = parseInt(hex, 16);
    if (isNaN(n)) return null;
    return { r: (n >> 16) & 255, g: (n >> 8) & 255, b: n & 255 };
}
function rgbToHsl(r, g, b) {
    r /= 255; g /= 255; b /= 255;
    var max = Math.max(r, g, b), min = Math.min(r, g, b);
    var h, s, l = (max + min) / 2;
    if (max === min) { h = s = 0; }
    else {
        var d = max - min;
        s = l > 0.5 ? d / (2 - max - min) : d / (max + min);
        switch (max) {
            case r: h = (g - b) / d + (g < b ? 6 : 0); break;
            case g: h = (b - r) / d + 2; break;
            default: h = (r - g) / d + 4;
        }
        h /= 6;
    }
    return { h: Math.round(h * 360), s: Math.round(s * 100), l: Math.round(l * 100) };
}
function rgbToCmyk(r, g, b) {
    var c = 1 - r / 255, m = 1 - g / 255, y = 1 - b / 255;
    var k = Math.min(c, m, y);
    if (k >= 1) return { c: 0, m: 0, y: 0, k: 100 };
    return {
        c: Math.round((c - k) / (1 - k) * 100),
        m: Math.round((m - k) / (1 - k) * 100),
        y: Math.round((y - k) / (1 - k) * 100),
        k: Math.round(k * 100)
    };
}
function relLum(r, g, b) {
    function f(v) { v /= 255; return v <= 0.03928 ? v / 12.92 : Math.pow((v + 0.055) / 1.055, 2.4); }
    return 0.2126 * f(r) + 0.7152 * f(g) + 0.0722 * f(b);
}
function pad(n) { return ('0' + n.toString(16)).slice(-2); }

function setAll(r, g, b) {
    var hex = '#' + pad(r) + pad(g) + pad(b);
    var hsl = rgbToHsl(r, g, b);
    var cmyk = rgbToCmyk(r, g, b);
    $cc('cc-hex').value = hex.toUpperCase();
    $cc('cc-rgb').value = 'rgb(' + r + ', ' + g + ', ' + b + ')';
    $cc('cc-hsl').value = 'hsl(' + hsl.h + ', ' + hsl.s + '%, ' + hsl.l + '%)';
    $cc('cc-cmyk').value = 'cmyk(' + cmyk.c + '%, ' + cmyk.m + '%, ' + cmyk.y + '%, ' + cmyk.k + '%)';
    $cc('cc-swab').style.background = hex;
    $cc('cc-pick').value = '#' + pad(r) + pad(g) + pad(b);
    $cc('cc-input').value = hex.toUpperCase();

    var l1 = relLum(255, 255, 255), l2 = relLum(r, g, b);
    var ratio = (Math.max(l1, l2) + 0.05) / (Math.min(l1, l2) + 0.05);
    var badge = ratio >= 4.5 ? 'AA — passes' : ratio >= 3 ? 'AA large / AAA large only' : 'fails';
    $cc('cc-contrast').textContent = 'White text on this colour: contrast ' + ratio.toFixed(2) + ':1 — WCAG ' + badge + ' (normal text).';
}
function fromHex(h) { var rgb = hexToRgb(h); if (rgb) setAll(rgb.r, rgb.g, rgb.b); }
function fromInput(v) {
    var s = v.trim().toLowerCase();
    var m;
    if ((m = s.match(/^#?([0-9a-f]{6})$/i))) { fromHex('#' + m[1]); return; }
    if ((m = s.match(/^#?([0-9a-f]{3})$/i))) { fromHex('#' + m[1]); return; }
    if ((m = s.match(/rgba?\(\s*(\d+)\s*,\s*(\d+)\s*,\s*(\d+)/))) { setAll(+m[1], +m[2], +m[3]); return; }
    if ((m = s.match(/hsla?\(\s*(\d+)\s*,\s*(\d+)%\s*,\s*(\d+)%/))) {
        var h = +m[1], s2 = +m[2] / 100, l2 = +m[3] / 100;
        function hue(p, q, t) { if (t < 0) t += 1; if (t > 1) t -= 1; if (t < 1 / 6) return p + (q - p) * 6 * t; if (t < 1 / 2) return q; if (t < 2 / 3) return p + (q - p) * (2 / 3 - t) * 6; return p; }
        var q2 = l2 < 0.5 ? l2 * (1 + s2) : l2 + s2 - l2 * s2;
        var p2 = 2 * l2 - q2;
        setAll(Math.round(hue(p2, q2, h / 360 + 1 / 3) * 255), Math.round(hue(p2, q2, h / 360) * 255), Math.round(hue(p2, q2, h / 360 - 1 / 3) * 255));
        return;
    }
    if ((m = s.match(/cmyk\(\s*(\d+)%\s*,\s*(\d+)%\s*,\s*(\d+)%\s*,\s*(\d+)%/))) {
        var c2 = +m[1] / 100, mg = +m[2] / 100, y2 = +m[3] / 100, k2 = +m[4] / 100;
        var r2 = Math.round(255 * (1 - c2) * (1 - k2));
        var g2 = Math.round(255 * (1 - mg) * (1 - k2));
        var b2 = Math.round(255 * (1 - y2) * (1 - k2));
        setAll(r2, g2, b2);
    }
}
fromHex('#5865f2');
</script>
<?php page_footer(); ?>