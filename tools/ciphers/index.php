<?php
require_once __DIR__ . '/../../functions.php';

start_session();
page_header('Classic Ciphers');
?>
<div class="container" style="max-width: 900px;">
    <h1 class="h4 mb-1 reveal in-view">🏛 Classic Ciphers</h1>
    <p class="text-secondary mb-3 reveal in-view">Hand-rolled implementations of the historical ciphers that built modern cryptography. Everything runs <strong>100% in your browser</strong>. Use them to learn, to solve CTF challenges, or to exchange playful secrets — never for anything that actually needs security (use the QR tool + a proper password instead).</p>

    <div class="alert alert-secondary reveal in-view">
        <strong>How these work:</strong> <strong>Atbash</strong> reverses the alphabet (a↔z, b↔y…). <strong>Caesar</strong> shifts each letter by a fixed amount — one of the easiest to break. <strong>Vigenère</strong> shifts each letter by a repeating key word, defeating simple frequency analysis. <strong>Beaufort</strong> is a variant of Vigenère with the roles of key and plaintext swapped (self-inverse — encrypt and decrypt are the same). <strong>Affine</strong> does <code>y = (a·x + b) mod 26</code> — the multiplicative factor must be coprime with 26. <strong>Rail Fence</strong> scrambles by writing text in a zig-zag across N rails. All here operate on A–Z only (other characters pass through unchanged).
    </div>

    <div class="card reveal in-view"><div class="card-body">
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label">Plaintext / ciphertext</label>
                <textarea id="cip-in" class="form-control" rows="6" style="font-family:'JetBrains Mono',monospace; font-size:.85rem;" placeholder="THEQUICKBROWNFOX…">ATTACK AT DAWN</textarea>
            </div>
            <div class="col-md-6">
                <label class="form-label">Result</label>
                <textarea id="cip-out" class="form-control" rows="6" readonly style="font-family:'JetBrains Mono',monospace; font-size:.85rem;"></textarea>
            </div>
        </div>

        <div class="row g-3 mt-1">
            <div class="col-md-5">
                <label class="form-label">Key (for Vigenère / Beaufort / Affine a / Rail Fence rails)</label>
                <input id="cip-key" class="form-control" value="KEY">
            </div>
            <div class="col-md-2">
                <label class="form-label">Shift (Caesar)</label>
                <input id="cip-shift" type="number" min="0" max="25" class="form-control" value="3">
            </div>
            <div class="col-md-3">
                <label class="form-label">Affine b</label>
                <input id="cip-b" type="number" min="0" max="25" class="form-control" value="5">
            </div>
            <div class="col-md-2">
                <label class="form-label">&nbsp;</label>
                <select id="cip-cipher" class="form-select" onchange="runCipher()">
                    <option value="vigenere">Vigenère</option>
                    <option value="beaufort">Beaufort</option>
                    <option value="atbash">Atbash</option>
                    <option value="affine">Affine</option>
                    <option value="caesar">Caesar</option>
                    <option value="rail">Rail Fence</option>
                </select>
            </div>
        </div>

        <div class="mt-3 d-flex flex-wrap gap-2">
            <button class="btn btn-primary btn-sm" onclick="runCipher(true)">Encrypt</button>
            <button class="btn btn-outline-light btn-sm" onclick="runCipher(false)">Decrypt</button>
            <button class="btn btn-outline-light btn-sm" onclick="copyCipOut()">Copy result</button>
            <span id="cip-note" class="text-secondary small align-self-center"></span>
        </div>
    </div></div>
</div>

<script>
function cleanKey(s) {
    return s.toUpperCase().replace(/[^A-Z]/g, '');
}
function cip(t, fn) {
    var out = '';
    for (var i = 0; i < t.length; i++) {
        var c = t[i];
        if (/[a-zA-Z]/.test(c)) {
            var base = c <= 'Z' ? 65 : 97;
            out += String.fromCharCode(fn(c.toUpperCase().charCodeAt(0) - 65) + base);
        } else {
            out += c;
        }
    }
    return out;
}
function vigenere(t, key, decrypt) {
    var k = cleanKey(key); if (!k) return '(need a key)';
    var ki = 0;
    return cip(t, function (x) {
        var s = k.charCodeAt(ki++ % k.length) - 65;
        return (x + (decrypt ? 26 - s : s)) % 26;
    });
}
function beaufort(t, key) {
    var k = cleanKey(key); if (!k) return '(need a key)';
    var ki = 0;
    // Encrypt/decrypt identical for Beaufort: y = (k - x) mod 26.
    return cip(t, function (x) { return ((k.charCodeAt(ki++ % k.length) - 65) - x + 26) % 26; });
}
function atbash(t) {
    return cip(t, function (x) { return 25 - x; });
}
function affine(t, a, b, decrypt) {
    a = ((a % 26) + 26) % 26;
    b = ((b % 26) + 26) % 26;
    var inv = modularInverse(a, 26);
    if (inv === null) return '(a=±' + a + ' has no inverse mod 26 — pick an a coprime with 26 like 1,3,5,7,9,11,15,17,19,21,23,25)';
    return cip(t, function (x) {
        var y = (a * x + b) % 26;
        if (decrypt) y = (inv * (x - b) + 26) % 26;
        return y;
    });
}
function modularInverse(a, m) {
    a = ((a % m) + m) % m;
    for (var x = 1; x < m; x++) if ((a * x) % m === 1) return x;
    return null;
}
function caesar(t, shift, decrypt) {
    shift = ((parseInt(shift, 10) || 0) % 26 + 26) % 26;
    return cip(t, function (x) { return (x + (decrypt ? 26 - shift : shift)) % 26; });
}
function railFence(t, rails, decrypt) {
    rails = Math.max(2, parseInt(rails, 10) || 2);
    // Positions of each character under a zig-zag across `rails` rows.
    var positions = [];
    var row = 0, dir = 1;
    for (var i = 0; i < t.length; i++) { positions.push(row); row += dir; if (row === rails - 1 || row === 0) dir = -dir; }
    if (!decrypt) {
        var fence = [];
        for (var r = 0; r < rails; r++) fence.push([]);
        for (var j = 0; j < t.length; j++) fence[positions[j]].push(t[j]);
        var s = '';
        for (var r2 = 0; r2 < rails; r2++) s += fence[r2].join('');
        return s;
    }
    // De-rail: rebuild row lengths, then read back in order.
    var lengths = [];
    for (var r3 = 0; r3 < rails; r3++) lengths.push(0);
    for (var k0 = 0; k0 < t.length; k0++) lengths[positions[k0]]++;
    var idx = 0, rows = [];
    for (var r4 = 0; r4 < rails; r4++) {
        rows.push(t.substr(idx, lengths[r4])); idx += lengths[r4];
    }
    var read = [];
    for (var k1 = 0; k1 < rails; k1++) read.push(0);
    var out = '';
    for (var k2 = 0; k2 < t.length; k2++) out += rows[positions[k2]][read[positions[k2]]++];
    return out;
}
function runCipher(encrypt) {
    var type = document.getElementById('cip-cipher').value;
    var t = document.getElementById('cip-in').value;
    var key = document.getElementById('cip-key').value;
    var shift = document.getElementById('cip-shift').value;
    var b = document.getElementById('cip-b').value;
    var out = '', note = '';
    switch (type) {
        case 'vigenere': out = vigenere(t, key, encrypt === false); note = 'Vigenère' + (encrypt === false ? ' (decrypt)' : ' → ' + cleanKey(key).length + '-letter key'); break;
        case 'beaufort': out = beaufort(t, key); note = 'Beaufort (self-inverse)'; break;
        case 'atbash': out = atbash(t); note = 'Atbash (self-inverse)'; break;
        case 'affine': out = affine(t, parseInt(shift, 10) || 1, parseInt(b, 10) || 0, encrypt === false); note = 'Affine'; break;
        case 'caesar': out = caesar(t, shift, encrypt === false); note = 'Caesar shift ' + (encrypt === false ? 26 - (((parseInt(shift,10)||0)%26+26)%26) : ((parseInt(shift,10)||0)%26+26)%26); break;
        case 'rail': out = railFence(t, key, encrypt === false); note = 'Rail Fence, ' + Math.max(2, parseInt(key,10)||2) + ' rails'; break;
    }
    document.getElementById('cip-out').value = out;
    document.getElementById('cip-note').textContent = note;
}
function copyCipOut() {
    var el = document.getElementById('cip-out');
    el.select(); document.execCommand('copy');
}
runCipher(true);
</script>
<?php page_footer(); ?>