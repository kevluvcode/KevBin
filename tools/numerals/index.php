<?php
require_once __DIR__ . '/../../functions.php';

start_session();
page_header('Numeral Converter');
?>
<div class="container" style="max-width: 720px;">
    <h1 class="h4 mb-1 reveal in-view">🔢 Numeral Converter</h1>
    <p class="text-secondary mb-3 reveal in-view">Convert between binary, octal, decimal and hexadecimal — live as you type. Negative numbers use the value directly (not two's complement).</p>

    <div class="card reveal"><div class="card-body">
        <div class="row g-3 mb-3">
            <div class="col-md-4">
                <label class="form-label">Base</label>
                <select class="form-select" id="in-base">
                    <option value="2">Binary (2)</option>
                    <option value="8">Octal (8)</option>
                    <option value="10" selected>Decimal (10)</option>
                    <option value="16">Hexadecimal (16)</option>
                </select>
            </div>
            <div class="col-md-8">
                <label class="form-label">Value</label>
                <input class="form-control" id="in-value" placeholder="e.g. 255" autofocus style="font-family:'JetBrains Mono',monospace;">
            </div>
        </div>
        <?php
        $rows = [
            ['bin', 'Binary (base 2)', '#0b'],
            ['oct', 'Octal (base 8)', '#0o'],
            ['dec', 'Decimal (base 10)', ''],
            ['hex', 'Hexadecimal (base 16)', '0x'],
        ];
        ?>
        <table class="table table-striped mb-3">
            <thead><tr><th style="width:34%;">Base</th><th>Value</th></tr></thead>
            <tbody id="out-body">
                <tr><td>Binary (base 2)</td><td id="out-bin" class="font-monospace">-</td></tr>
                <tr><td>Octal (base 8)</td><td id="out-oct" class="font-monospace">-</td></tr>
                <tr><td>Decimal (base 10)</td><td id="out-dec" class="font-monospace">-</td></tr>
                <tr><td>Hexadecimal (base 16)</td><td id="out-hex" class="font-monospace">-</td></tr>
            </tbody>
        </table>
        <div class="text-secondary small">Uppercase hex? <input type="checkbox" id="hex-up" class="form-check-input ms-1"></div>
    </div></div>

    <div class="card mt-3 reveal"><div class="card-body">
        <h2 class="h6 mb-2">What's the biggest I can use?</h2>
        <p class="text-secondary small mb-0">JavaScript handles integers up to 9,007,199,254,740,991 (&plusmn;2^53&minus;1) exactly. Past that the output may round.</p>
    </div></div>
</div>
<script>
(function () {
    var inBase = document.getElementById('in-base');
    var inValue = document.getElementById('in-value');
    var up = document.getElementById('hex-up');
    function convert() {
        var raw = inValue.value.trim();
        var base = parseInt(inBase.value, 10);
        var dec = null;
        if (raw !== '') {
            var cleaned = raw.replace(/^[#0][box]/i, '');
            var neg = cleaned.startsWith('-');
            var s = neg ? cleaned.slice(1) : cleaned;
            var rev = { '2': 2, '8': 8, '10': 10, '16': 16 };
            var useBase = rev[base] || 10;
            if (useBase === 10 || useBase === 16) {
                var valid = useBase === 16 ? /^[0-9a-fA-F]+$/ : /^[0-9]+$/;
                if (!valid.test(s)) { dec = null; } else { dec = parseInt(s, useBase); }
            } else {
                var valid8 = useBase === 8 ? /^[0-7]+$/ : /^[01]+$/;
                if (!valid8.test(s)) { dec = null; } else { dec = parseInt(s, useBase); }
            }
            if (neg && dec !== null) { dec = -dec; }
        }
        var hexUp = up.checked;
        var out = { bin: '-', oct: '-', dec: '-', hex: '-' };
        if (dec !== null && Number.isFinite(dec)) {
            var neg2 = dec < 0;
            var abs = Math.abs(dec);
            out.bin = (neg2 ? '-' : '') + abs.toString(2);
            out.oct = (neg2 ? '-' : '') + abs.toString(8);
            out.dec = String(dec);
            var h = abs.toString(16);
            out.hex = (neg2 ? '-' : '') + (hexUp ? h.toUpperCase() : h);
        }
        ['bin', 'oct', 'dec', 'hex'].forEach(function (k) {
            document.getElementById('out-' + k).textContent = out[k];
        });
    }
    inBase.addEventListener('change', convert);
    inValue.addEventListener('input', convert);
    up.addEventListener('change', convert);
})();
</script>
<?php page_footer(); ?>