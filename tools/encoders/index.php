<?php
require_once __DIR__ . '/../../functions.php';

start_session();
page_header('Encoders');
?>
<div class="container" style="max-width: 900px;">
    <h1 class="h4 mb-1 reveal in-view">Encoders & Decoders</h1>
    <p class="text-secondary mb-4 reveal in-view">Everything runs locally in your browser — instant encode/decode with copy buttons.</p>

    <div class="alert alert-secondary reveal in-view">
        <strong>What's the difference between encoding and encryption?</strong> Encoding (Base64, hex, URL, ROT13) is a reversible format conversion — anyone can decode it instantly; it's for transporting text, not secrecy. Encryption is scrambled with a key and designed to be unreadable without it. Don't rely on encoders to hide anything. <strong>Base64</strong> packs binary into text characters; <strong>hex</strong> shows each byte as two digits; <strong>URL encoding</strong> escapes unsafe characters for query strings; <strong>ROT13</strong> shifts letters by 13; <strong>binary</strong> is raw 1s and 0s.
    </div>

    <div class="card reveal in-view">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Input</label>
                    <textarea id="enc-in" class="form-control" rows="8" style="font-family:'JetBrains Mono',monospace; font-size:.85rem;" oninput="liveEncode()" placeholder="text to encode or decode">Hello KevBin</textarea>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Output</label>
                    <textarea id="enc-out" class="form-control" rows="8" readonly style="font-family:'JetBrains Mono',monospace; font-size:.85rem;"></textarea>
                </div>
            </div>

            <div class="mt-3 d-flex flex-wrap gap-2">
                <button class="btn btn-outline-light btn-sm" onclick="run('b64e')">Base64 Encode</button>
                <button class="btn btn-outline-light btn-sm" onclick="run('b64d')">Base64 Decode</button>
                <button class="btn btn-outline-light btn-sm" onclick="run('hexe')">Hex Encode</button>
                <button class="btn btn-outline-light btn-sm" onclick="run('hexd')">Hex Decode</button>
                <button class="btn btn-outline-light btn-sm" onclick="run('urle')">URL Encode</button>
                <button class="btn btn-outline-light btn-sm" onclick="run('urld')">URL Decode</button>
                <button class="btn btn-outline-light btn-sm" onclick="run('bine')">Binary Encode</button>
                <button class="btn btn-outline-light btn-sm" onclick="run('bind')">Binary Decode</button>
                <button class="btn btn-outline-light btn-sm" onclick="run('rot13')">ROT13</button>
                <button class="btn btn-outline-light btn-sm" onclick="run('morse')">Morse Encode</button>
                <button class="btn btn-outline-light btn-sm" onclick="run('morsed')">Morse Decode</button>
                <button class="btn btn-outline-light btn-sm" onclick="run('leet')">Leet Speak</button>
                <button class="btn btn-primary btn-sm" onclick="copyOut()">Copy output</button>
            </div>

            <div class="mt-2">
                <span class="text-secondary small">Select output to see it inline:</span>
                <pre id="enc-live" class="paste-content mt-2" style="display:none;"></pre>
            </div>
        </div>
    </div>
</div>

<script>
function enc(t) { return btoa(unescape(encodeURIComponent(t))); }
function dec(t) {
    var s = t.replace(/\s+/g, '');
    return decodeURIComponent(escape(atob(s)));
}
function toHex(t) {
    return Array.from(t).map(function (c) { return ('0' + c.charCodeAt(0).toString(16)).slice(-2); }).join(' ');
}
function fromHex(t) {
    var hex = t.replace(/\s+/g, '');
    if (hex.length % 2) throw new Error('Invalid hex length');
    var out = '';
    for (var i = 0; i < hex.length; i += 2) out += String.fromCharCode(parseInt(hex.substr(i, 2), 16));
    return out;
}
function toBin(t) {
    return Array.from(t).map(function (c) { return c.charCodeAt(0).toString(2).padStart(8, '0'); }).join(' ');
}
function fromBin(t) {
    var bin = t.replace(/\s+/g, '');
    var out = '';
    for (var i = 0; i + 8 <= bin.length; i += 8) out += String.fromCharCode(parseInt(bin.substr(i, 8), 2));
    return out;
}
function toRot(t) {
    return t.replace(/[a-zA-Z]/g, function (c) {
        var base = c <= 'Z' ? 65 : 97;
        return String.fromCharCode(((c.charCodeAt(0) - base + 13) % 26) + base);
    });
}
var MORSE = {
    A: '.-', B: '-...', C: '-.-.', D: '-..', E: '.', F: '..-.', G: '--.', H: '....',
    I: '..', J: '.---', K: '-.-', L: '.-..', M: '--', N: '-.', O: '---', P: '.--.',
    Q: '--.-', R: '.-.', S: '...', T: '-', U: '..-', V: '...-', W: '.--', X: '-..-',
    Y: '-.--', Z: '--..',
    '0': '-----', '1': '.----', '2': '..---', '3': '...--', '4': '....-', '5': '.....',
    '6': '-....', '7': '--...', '8': '---..', '9': '----.',
    '.': '.-.-.-', ',': '--..--', '?': '..--..', "'": '.----.', '!': '-.-.--', '/': '-..-.',
    '(': '-.--.', ')': '-.--.-', '&': '.-...', ':': '---...', ';': '-.-.-.', '=': '-...-',
    '+': '.-.-.', '-': '-....-', '_': '..--.-', '"': '.-..-.', '$': '...-..-', '@': '.--.-.'
};
function toMorse(t) {
    return t.toUpperCase().split(/\s+/).map(function (word) {
        return Array.from(word).map(function (c) { return MORSE[c] || ''; }).filter(Boolean).join(' ');
    }).filter(Boolean).join(' / ');
}
function fromMorse(t) {
    var rev = {};
    Object.keys(MORSE).forEach(function (k) { rev[MORSE[k]] = k; });
    return t.split('/').map(function (word) {
        return word.trim().split(/\s+/).filter(Boolean).map(function (m) { return rev[m] || '?'; }).join('');
    }).join(' ');
}
function toLeet(t) {
    return Array.from(t.toUpperCase()).map(function (c) {
        var map = { A: '4', B: '8', E: '3', G: '6', I: '1', L: '1', O: '0', S: '5', T: '7', Z: '2' };
        return map[c] || c.toLowerCase();
    }).join('');
}
function liveEncode() {
    try {
        document.getElementById('enc-out').value = enc(document.getElementById('enc-in').value);
    } catch (e) { document.getElementById('enc-out').value = ''; }
}
function run(mode) {
    var inV = document.getElementById('enc-in').value;
    var out;
    try {
        switch (mode) {
            case 'b64e': out = enc(inV); break;
            case 'b64d': out = dec(inV); break;
            case 'hexe': out = toHex(inV); break;
            case 'hexd': out = fromHex(inV); break;
            case 'urle': out = encodeURIComponent(inV); break;
            case 'urld': out = decodeURIComponent(inV); break;
            case 'bine': out = toBin(inV); break;
            case 'bind': out = fromBin(inV); break;
            case 'rot13': out = toRot(inV); break;
            case 'morse': out = toMorse(inV); break;
            case 'morsed': out = fromMorse(inV); break;
            case 'leet': out = toLeet(inV); break;
        }
        var el = document.getElementById('enc-out');
        el.value = out;
        var live = document.getElementById('enc-live');
        live.textContent = out;
        live.style.display = 'block';
    } catch (e) {
        document.getElementById('enc-out').value = 'Error: ' + e.message;
    }
}
function copyOut() {
    var t = document.getElementById('enc-out');
    t.select(); document.execCommand('copy');
}
</script>
<?php page_footer(); ?>