<?php
require_once __DIR__ . '/../../functions.php';

start_session();
$GLOBALS['_meta'] = [
    'description' => 'Free online Base32, Base58 and Base85 encoder and decoder. Supports RFC 4648 Base32, Base32-Hex, Bitcoin & Ripple Base58, Ascii85 and Z85. Fully client-side with UTF-8 and hex view.',
    'keywords' => 'base32, base58, base85, ascii85, z85, encoder, decoder, bitcoin, ripple, hex',
];
page_header('Base-N Encoder / Decoder');
?>
<div class="container" style="max-width: 900px;">
    <h1 class="h4 mb-1 reveal in-view">Base-N Encoder / Decoder</h1>
    <p class="text-secondary mb-4 reveal in-view">Encode or decode Base32, Base58, and Base85 variants — all conversions run 100% in your browser with full UTF-8 support.</p>

    <div class="alert alert-secondary reveal in-view">
        <strong>What is Base-N encoding?</strong> A way to represent binary data as text using a limited alphabet. <strong>Base32</strong> (RFC 4648) uses A-Z and 2-7, common in TOTP seeds. <strong>Base58</strong> drops confusing characters (0/O/l/I) — used in Bitcoin addresses. <strong>Base85</strong> packs more data per character — used in PDF and ZeroMQ. None of these are encryption; anyone can reverse them.
    </div>

    <div class="card reveal in-view">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Input <span class="text-secondary small">(text or hex with 0x prefix)</span></label>
                    <textarea id="bn-in" class="form-control" rows="8" style="font-family:'JetBrains Mono',monospace; font-size:.85rem;" placeholder="Enter text to encode or encoded string to decode">Hello World!</textarea>
                    <div id="bn-in-hex" class="form-text mt-1" style="font-family:'JetBrains Mono',monospace; font-size:.75rem; word-break:break-all; min-height:20px; color:#8b949e;"></div>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Output</label>
                    <textarea id="bn-out" class="form-control" rows="8" readonly style="font-family:'JetBrains Mono',monospace; font-size:.85rem;"></textarea>
                    <div id="bn-out-hex" class="form-text mt-1" style="font-family:'JetBrains Mono',monospace; font-size:.75rem; word-break:break-all; min-height:20px; color:#8b949e;"></div>
                </div>
            </div>

            <div class="row g-3 mt-1">
                <div class="col-md-4">
                    <label class="form-label">Encoding</label>
                    <select id="bn-type" class="form-select">
                        <option value="base32">Base32 (RFC 4648)</option>
                        <option value="base32hex">Base32-Hex (RFC 4648)</option>
                        <option value="base58btc">Base58 Bitcoin</option>
                        <option value="base58rip">Base58 Ripple</option>
                        <option value="base85a">Base85 Ascii85</option>
                        <option value="base85z">Base85 Z85</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Direction</label>
                    <div class="btn-group w-100" role="group">
                        <input type="radio" class="btn-check" name="bn-dir" id="bn-encode" value="encode" checked>
                        <label class="btn btn-outline-light btn-sm" for="bn-encode">Encode</label>
                        <input type="radio" class="btn-check" name="bn-dir" id="bn-decode" value="decode">
                        <label class="btn btn-outline-light btn-sm" for="bn-decode">Decode</label>
                    </div>
                </div>
                <div class="col-md-3 d-flex align-items-end">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="bn-auto">
                        <label class="form-check-label small text-secondary" for="bn-auto">Auto-convert</label>
                    </div>
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button class="btn btn-primary btn-sm w-100" onclick="bnConvert()">Convert</button>
                </div>
            </div>

            <div class="mt-3 d-flex flex-wrap gap-2">
                <button class="btn btn-outline-light btn-sm" onclick="bnSwap()">&#8644; Swap</button>
                <button class="btn btn-outline-light btn-sm" onclick="bnCopy()">Copy Output</button>
                <button class="btn btn-outline-light btn-sm" onclick="bnClear()">Clear</button>
            </div>

            <div id="bn-error" class="text-danger small mt-2" style="display:none;"></div>
        </div>
    </div>
</div>

<script>
(function() {
    var BASE32_ALPH = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    var BASE32HEX_ALPH = '0123456789ABCDEFGHIJKLMNOPQRSTUV';
    var BASE58_BTC_ALPH = '123456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz';
    var BASE58_RIP_ALPH = 'rpshnaf39wBUDNEGHJKLM4PQRST7VWXYZ2bcdeCg65jkm8oFqi1rtuvUxy';
    var BASE85Z_ALPH = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ.-:+=^!/*?&<>()[]{}@%$#';

    function textToBytes(s) {
        return new TextEncoder().encode(s);
    }

    function bytesToText(b) {
        return new TextDecoder().decode(new Uint8Array(b));
    }

    function bytesToHex(b) {
        var h = '';
        for (var i = 0; i < b.length; i++) h += ('0' + b[i].toString(16)).slice(-2);
        return h;
    }

    function hexToBytes(h) {
        h = h.replace(/^0x/i, '').replace(/\s+/g, '');
        if (h.length % 2 !== 0) throw new Error('Invalid hex string length');
        var b = [];
        for (var i = 0; i < h.length; i += 2) b.push(parseInt(h.substr(i, 2), 16));
        return new Uint8Array(b);
    }

    function parseInput(s) {
        s = s.trim();
        if (/^0x[0-9a-fA-F]*$/.test(s)) return hexToBytes(s);
        return textToBytes(s);
    }

    function invertAlphabet(alph) {
        var map = {};
        for (var i = 0; i < alph.length; i++) map[alph[i]] = i;
        return map;
    }

    function base32Encode(bytes, alph) {
        var bits = '';
        for (var i = 0; i < bytes.length; i++) bits += ('00000000' + bytes[i].toString(2)).slice(-8);
        while (bits.length % 5 !== 0) bits += '0';
        var out = '';
        for (var i = 0; i < bits.length; i += 5) out += alph[parseInt(bits.substr(i, 5), 2)];
        return out;
    }

    function base32Decode(s, alph) {
        var map = invertAlphabet(alph);
        s = s.toUpperCase().replace(/=+$/, '').replace(/\s+/g, '');
        for (var i = 0; i < s.length; i++) {
            if (!(s[i] in map)) throw new Error('Invalid character in Base32 input: ' + s[i]);
        }
        var bits = '';
        for (var i = 0; i < s.length; i++) bits += ('00000' + map[s[i]].toString(2)).slice(-5);
        var bytes = [];
        for (var i = 0; i + 8 <= bits.length; i += 8) bytes.push(parseInt(bits.substr(i, 8), 2));
        return new Uint8Array(bytes);
    }

    function base58Encode(bytes, alph) {
        var num = 0;
        for (var i = 0; i < bytes.length; i++) num = num * 256 + bytes[i];
        var out = '';
        while (num > 0) { out = alph[num % 58] + out; num = Math.floor(num / 58); }
        for (var i = 0; i < bytes.length && bytes[i] === 0; i++) out = alph[0] + out;
        return out;
    }

    function base58Decode(s, alph) {
        var map = invertAlphabet(alph);
        var num = 0;
        for (var i = 0; i < s.length; i++) {
            if (!(s[i] in map)) throw new Error('Invalid character in Base58 input: ' + s[i]);
            num = num * 58 + map[s[i]];
        }
        var bytes = [];
        while (num > 0) { bytes.unshift(num & 255); num = Math.floor(num / 256); }
        for (var i = 0; i < s.length && s[i] === alph[0]; i++) bytes.unshift(0);
        return new Uint8Array(bytes);
    }

    function base85aEncode(bytes) {
        var pad = (4 - (bytes.length % 4)) % 4;
        var padded = new Uint8Array(bytes.length + pad);
        padded.set(bytes);
        var out = '<~';
        for (var i = 0; i < padded.length; i += 4) {
            var n = (padded[i] << 24) | (padded[i + 1] << 16) | (padded[i + 2] << 8) | padded[i + 3];
            if (n === 0) { out += 'y'; continue; }
            var digits = [];
            for (var j = 0; j < 5; j++) { digits.unshift(n % 85 + 33); n = Math.floor(n / 85); }
            out += String.fromCharCode.apply(null, digits);
        }
        out += '~>';
        return out;
    }

    function base85aDecode(s) {
        s = s.replace(/<~/g, '').replace(/~>/g, '').replace(/\s+/g, '');
        var out = [];
        var buf = '';
        for (var i = 0; i < s.length; i++) {
            if (s[i] === 'y') {
                if (buf.length > 0) throw new Error('Unexpected y in Ascii85');
                out.push(0, 0, 0, 0);
                continue;
            }
            buf += s[i];
            if (buf.length === 5) {
                var n = 0;
                for (var j = 0; j < 5; j++) n = n * 85 + (buf.charCodeAt(j) - 33);
                if (n > 0xFFFFFFFF) throw new Error('Ascii85 overflow');
                out.push((n >>> 24) & 255, (n >>> 16) & 255, (n >>> 8) & 255, n & 255);
                buf = '';
            }
        }
        if (buf.length > 0) {
            while (buf.length < 5) buf += String.fromCharCode(117);
            var n = 0;
            for (var j = 0; j < 5; j++) n = n * 85 + (buf.charCodeAt(j) - 33);
            var tmp = [(n >>> 24) & 255, (n >>> 16) & 255, (n >>> 8) & 255, n & 255];
            var excess = 5 - buf.length;
            for (var j = 0; j < 4 - excess; j++) out.push(tmp[j]);
        }
        return new Uint8Array(out);
    }

    function base85zEncode(bytes) {
        var pad = (4 - (bytes.length % 4)) % 4;
        var padded = new Uint8Array(bytes.length + pad);
        padded.set(bytes);
        var out = '';
        for (var i = 0; i < padded.length; i += 4) {
            var n = (padded[i] << 24) | (padded[i + 1] << 16) | (padded[i + 2] << 8) | padded[i + 3];
            if (n === 0) { out += BASE85Z_ALPH[0]; out += BASE85Z_ALPH[0]; out += BASE85Z_ALPH[0]; out += BASE85Z_ALPH[0]; out += BASE85Z_ALPH[0]; continue; }
            var digits = [];
            for (var j = 0; j < 5; j++) { digits.unshift(BASE85Z_ALPH[n % 85]); n = Math.floor(n / 85); }
            out += digits.join('');
        }
        return out;
    }

    function base85zDecode(s) {
        var map = invertAlphabet(BASE85Z_ALPH);
        s = s.replace(/\s+/g, '');
        var out = [];
        var buf = '';
        for (var i = 0; i < s.length; i++) {
            buf += s[i];
            if (buf.length === 5) {
                var n = 0;
                for (var j = 0; j < 5; j++) {
                    if (!(buf[j] in map)) throw new Error('Invalid character in Z85 input: ' + buf[j]);
                    n = n * 85 + map[buf[j]];
                }
                out.push((n >>> 24) & 255, (n >>> 16) & 255, (n >>> 8) & 255, n & 255);
                buf = '';
            }
        }
        if (buf.length > 0) throw new Error('Incomplete Z85 block (expected multiple of 5 characters)');
        return new Uint8Array(out);
    }

    function updateHexViews(bytes, direction) {
        var inHex = document.getElementById('bn-in-hex');
        var outHex = document.getElementById('bn-out-hex');
        if (direction === 'encode') {
            inHex.textContent = 'Hex: ' + bytesToHex(bytes);
            outHex.textContent = '';
        } else {
            inHex.textContent = '';
            outHex.textContent = 'Hex: ' + bytesToHex(bytes);
        }
    }

    window.bnConvert = function() {
        var err = document.getElementById('bn-error');
        err.style.display = 'none';
        err.textContent = '';
        try {
            var type = document.getElementById('bn-type').value;
            var dir = document.querySelector('input[name="bn-dir"]:checked').value;
            var input = document.getElementById('bn-in').value;
            var result;
            if (dir === 'encode') {
                var bytes = parseInput(input);
                updateHexViews(bytes, 'encode');
                switch (type) {
                    case 'base32': result = base32Encode(bytes, BASE32_ALPH); break;
                    case 'base32hex': result = base32Encode(bytes, BASE32HEX_ALPH); break;
                    case 'base58btc': result = base58Encode(bytes, BASE58_BTC_ALPH); break;
                    case 'base58rip': result = base58Encode(bytes, BASE58_RIP_ALPH); break;
                    case 'base85a': result = base85aEncode(bytes); break;
                    case 'base85z': result = base85zEncode(bytes); break;
                }
            } else {
                switch (type) {
                    case 'base32': result = bytesToText(base32Decode(input, BASE32_ALPH)); break;
                    case 'base32hex': result = bytesToText(base32Decode(input, BASE32HEX_ALPH)); break;
                    case 'base58btc': result = bytesToText(base58Decode(input, BASE58_BTC_ALPH)); break;
                    case 'base58rip': result = bytesToText(base58Decode(input, BASE58_RIP_ALPH)); break;
                    case 'base85a': result = bytesToText(base85aDecode(input)); break;
                    case 'base85z': result = bytesToText(base85zDecode(input)); break;
                }
                var outBytes = textToBytes(result);
                updateHexViews(outBytes, 'decode');
            }
            document.getElementById('bn-out').value = result;
        } catch (e) {
            err.textContent = e.message || 'Conversion failed';
            err.style.display = 'block';
            document.getElementById('bn-out').value = '';
            document.getElementById('bn-in-hex').textContent = '';
            document.getElementById('bn-out-hex').textContent = '';
        }
    };

    window.bnSwap = function() {
        var inp = document.getElementById('bn-in');
        var out = document.getElementById('bn-out');
        var tmp = inp.value;
        inp.value = out.value;
        out.value = tmp;
        var dir = document.querySelector('input[name="bn-dir"]:checked');
        if (dir.value === 'encode') {
            document.getElementById('bn-decode').checked = true;
        } else {
            document.getElementById('bn-encode').checked = true;
        }
        var ih = document.getElementById('bn-in-hex').textContent;
        var oh = document.getElementById('bn-out-hex').textContent;
        document.getElementById('bn-in-hex').textContent = oh;
        document.getElementById('bn-out-hex').textContent = ih;
        if (document.getElementById('bn-auto').checked) bnConvert();
    };

    window.bnCopy = function() {
        var el = document.getElementById('bn-out');
        if (!el.value) return;
        el.select();
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(el.value);
        } else {
            document.execCommand('copy');
        }
    };

    window.bnClear = function() {
        document.getElementById('bn-in').value = '';
        document.getElementById('bn-out').value = '';
        document.getElementById('bn-in-hex').textContent = '';
        document.getElementById('bn-out-hex').textContent = '';
        document.getElementById('bn-error').style.display = 'none';
    };

    document.getElementById('bn-in').addEventListener('input', function() {
        if (document.getElementById('bn-auto').checked) bnConvert();
    });
    document.getElementById('bn-type').addEventListener('change', function() {
        if (document.getElementById('bn-auto').checked) bnConvert();
    });
    document.querySelectorAll('input[name="bn-dir"]').forEach(function(r) {
        r.addEventListener('change', function() {
            if (document.getElementById('bn-auto').checked) bnConvert();
        });
    });

    bnConvert();
})();
</script>
<?php page_footer(); ?>