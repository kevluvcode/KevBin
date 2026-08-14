<?php
require_once __DIR__ . '/../../functions.php';

start_session();
page_header('Color Converter');
?>
<div class="container" style="max-width: 900px;">
    <h1 class="h4 mb-1 reveal in-view">Color Converter</h1>
    <p class="text-secondary mb-4 reveal in-view">Convert colors between HEX, RGB, HSL and CMYK. Pick a color or drop in any format — outputs update live.</p>

    <div class="card reveal in-view">
        <div class="card-body">
            <div class="d-flex flex-wrap gap-2 align-items-center mb-3">
                <input type="color" id="color-pick" class="form-control form-control-color" value="#5865f2" style="width:60px;height:40px;" oninput="fromHex(this.value)">
                <input id="color-input" class="form-control" style="max-width:180px;font-family:'JetBrains Mono',monospace;font-size:.9rem;" value="#5865f2" oninput="fromInput(this.value)">
                <span class="form-text">type #hex, rgb(), hsl() or cmyk() — or use the picker</span>
            </div>

            <div id="color-swab" class="mb-3" style="height:70px;border-radius:12px;border:1px solid var(--line);"></div>

            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label small text-secondary">HEX</label>
                    <div class="input-group"><input id="c-hex" class="form-control" readonly style="font-family:'JetBrains Mono',monospace;font-size:.85rem;"><button class="btn btn-outline-light btn-sm" onclick="copyField('c-hex')">Copy</button></div>
                </div>
                <div class="col-md-6">
                    <label class="form-label small text-secondary">RGB</label>
                    <div class="input-group"><input id="c-rgb" class="form-control" readonly style="font-family:'JetBrains Mono',monospace;font-size:.85rem;"><button class="btn btn-outline-light btn-sm" onclick="copyField('c-rgb')">Copy</button></div>
                </div>
                <div class="col-md-6">
                    <label class="form-label small text-secondary">HSL</label>
                    <div class="input-group"><input id="c-hsl" class="form-control" readonly style="font-family:'JetBrains Mono',monospace;font-size:.85rem;"><button class="btn btn-outline-light btn-sm" onclick="copyField('c-hsl')">Copy</button></div>
                </div>
                <div class="col-md-6">
                    <label class="form-label small text-secondary">CMYK</label>
                    <div class="input-group"><input id="c-cmyk" class="form-control" readonly style="font-family:'JetBrains Mono',monospace;font-size:.85rem;"><button class="btn btn-outline-light btn-sm" onclick="copyField('c-cmyk')">Copy</button></div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function $(id) { return document.getElementById(id); }
function copyField(id) { var t = $(id); t.select(); document.execCommand('copy'); }

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
function pad(n) { return ('0' + n.toString(16)).slice(-2); }

function setAll(r, g, b) {
    var hex = '#' + pad(r) + pad(g) + pad(b);
    var hsl = rgbToHsl(r, g, b);
    var cmyk = rgbToCmyk(r, g, b);
    $('c-hex').value = hex.toUpperCase();
    $('c-rgb').value = 'rgb(' + r + ', ' + g + ', ' + b + ')';
    $('c-hsl').value = 'hsl(' + hsl.h + ', ' + hsl.s + '%, ' + hsl.l + '%)';
    $('c-cmyk').value = 'cmyk(' + cmyk.c + '%, ' + cmyk.m + '%, ' + cmyk.y + '%, ' + cmyk.k + '%)';
    $('color-swab').style.background = hex;
    $('color-pick').value = '#' + pad(r) + pad(g) + pad(b);
    $('color-input').value = hex.toUpperCase();
}

function fromHex(h) {
    var rgb = hexToRgb(h);
    if (rgb) setAll(rgb.r, rgb.g, rgb.b);
}
function fromInput(v) {
    var s = v.trim().toLowerCase();
    var m;
    if ((m = s.match(/^#?([0-9a-f]{6})$/i))) { fromHex('#' + m[1]); return; }
    if ((m = s.match(/^#?([0-9a-f]{3})$/i))) { fromHex('#' + m[1]); return; }
    if ((m = s.match(/rgba?\(\s*(\d+)\s*,\s*(\d+)\s*,\s*(\d+)/))) { setAll(+m[1], +m[2], +m[3]); return; }
    if ((m = s.match(/hsla?\(\s*(\d+)\s*,\s*(\d+)%\s*,\s*(\d+)%/))) {
        var h = +m[1], s2 = +m[2] / 100, l2 = +m[3] / 100;
        function hue(p, q, t) {
            if (t < 0) t += 1; if (t > 1) t -= 1;
            if (t < 1 / 6) return p + (q - p) * 6 * t;
            if (t < 1 / 2) return q;
            if (t < 2 / 3) return p + (q - p) * (2 / 3 - t) * 6;
            return p;
        }
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