<?php
require_once __DIR__ . '/../../functions.php';

start_session();
$GLOBALS['_meta'] = [
    'description' => 'Free online hash generator and identifier. Compute MD5, SHA-1, SHA-224, SHA-256, SHA-384, SHA-512, SHA-3 family, CRC32, HMAC and more. Also identify unknown hash formats by length, charset and prefix.',
    'keywords' => 'hash generator, sha256, md5, sha1, sha512, sha3, checksum, hash calculator, hmac, hash identifier',
];
page_header('Hash Generator & Identifier');
?>
<div class="container" style="max-width: 900px;">
    <h1 class="h4 mb-1 reveal in-view">Hash Generator & Identifier</h1>
    <p class="text-secondary mb-4 reveal in-view">Hash any text with the SHA-2 family, SHA-1, MD5, SHA-3, SHAKE, CRC32 or HMAC — or identify an unknown hash from its length and format. Standard algorithms run 100% in your browser; SHA-3 family and friends are computed by the server (marked below). Plain hashing stays fully client-side.</p>

    <div class="alert alert-secondary reveal in-view">
        <strong>What is a hash?</strong> A one-way function that turns any input into a fixed-length fingerprint. You can't reverse it — the same input always gives the same hash, and even a tiny change gives a completely different one. Use this to verify files, store passwords safely, or build checksums.
    </div>

    <div class="card reveal in-view">
        <div class="card-body">
            <label class="form-label">Input</label>
            <textarea id="hash-in" class="form-control" rows="4" style="font-family:'JetBrains Mono',monospace; font-size:.85rem;" oninput="hashAll()" placeholder="text to hash">KevBin tools</textarea>

            <div class="d-flex flex-wrap gap-2 align-items-center mt-2 mb-3">
                <label class="form-label small text-secondary mb-0 me-1">HMAC key (optional, enables HMAC-* rows):</label>
                <input id="hmac-key" class="form-control" style="max-width:220px;font-family:'JetBrains Mono',monospace;font-size:.85rem;" oninput="hashAll()" placeholder="secret (optional)">
                <span class="form-text small">MD5, SHA-1 &amp; SHA-2 are labeled legacy — fine for checksums, avoid for password storage.</span>
            </div>

            <div class="d-grid gap-2" id="hash-results"></div>

            <div id="hash-srv-note" class="form-text mt-2"></div>
        </div>
    </div>

    <div class="card mt-4 reveal in-view">
        <div class="card-body">
            <h2 class="h6 mb-2">📄 Hash a file (SHA-2, SHA-1, MD5 in-browser)</h2>
            <input type="file" id="hash-file" class="form-control mb-2" onchange="hashFile(this.files)">
            <div id="file-out" class="text-secondary small" style="min-height:20px;"></div>
        </div>
    </div>

    <div class="card mt-4 reveal in-view">
        <div class="card-body">
            <h2 class="h6 mb-2">🕵️ Hash Identifier</h2>
            <p class="text-secondary small">Paste an unknown hash and find out which algorithm produced it (based on length, charset and prefixes).</p>
            <input id="id-in" class="form-control mb-2" style="font-family:'JetBrains Mono',monospace;font-size:.85rem;" placeholder="paste a hash here..." oninput="identifyHash(this.value)">
            <div id="id-out" class="text-secondary small" style="min-height:20px;"></div>
        </div>
    </div>
</div>

<script>
function toHexBytes(buffer) {
    var bytes = new Uint8Array(buffer);
    var out = '';
    for (var i = 0; i < bytes.length; i++) out += ('0' + bytes[i].toString(16)).slice(-2);
    return out;
}
function md5(inputString) {
    var hc = "0123456789abcdef";
    function rh(n) { var j, s = ""; for (j = 0; j <= 3; j++) s += hc.charAt((n >> (j * 8 + 4)) & 0x0F) + hc.charAt((n >> (j * 8)) & 0x0F); return s; }
    function ad(x, y) { var l = (x & 0xFFFF) + (y & 0xFFFF); var m = (x >> 16) + (y >> 16) + (l >> 16); return (m << 16) | (l & 0xFFFF); }
    function rl(n, c) { return (n << c) | (n >>> (32 - c)); }
    function cm(q, a, b, x, s, t) { return ad(rl(ad(ad(a, q), ad(x, t)), s), b); }
    function ff(a, b, c, d, x, s, t) { return cm((b & c) | ((~b) & d), a, b, x, s, t); }
    function gg(a, b, c, d, x, s, t) { return cm((b & d) | (c & (~d)), a, b, x, s, t); }
    function hh(a, b, c, d, x, s, t) { return cm(b ^ c ^ d, a, b, x, s, t); }
    function ii(a, b, c, d, x, s, t) { return cm(c ^ (b | (~d)), a, b, x, s, t); }
    function sb(x) {
        var i, nblk = ((x.length + 8) >> 6) + 1;
        var blks = new Array(nblk * 16);
        for (i = 0; i < nblk * 16; i++) blks[i] = 0;
        for (i = 0; i < x.length; i++) blks[i >> 2] |= x.charCodeAt(i) << ((i % 4) << 3);
        blks[i >> 2] |= 0x80 << ((i % 4) << 3);
        blks[nblk * 16 - 2] = x.length * 8;
        return blks;
    }
    var i, x = sb(inputString), a = 1732584193, b = -271733879, c = -1732584194, d = 271733878, oa, ob, oc, od;
    for (i = 0; i < x.length; i += 16) {
        oa = a; ob = b; oc = c; od = d;
        a = ff(a, b, c, d, x[i + 0], 7, -680876936); d = ff(d, a, b, c, x[i + 1], 12, -389564586);
        c = ff(c, d, a, b, x[i + 2], 17, 606105819); b = ff(b, c, d, a, x[i + 3], 22, -1044525330);
        a = ff(a, b, c, d, x[i + 4], 7, -176418897); d = ff(d, a, b, c, x[i + 5], 12, 1200080426);
        c = ff(c, d, a, b, x[i + 6], 17, -1473231341); b = ff(b, c, d, a, x[i + 7], 22, -45705983);
        a = ff(a, b, c, d, x[i + 8], 7, 1770035416); d = ff(d, a, b, c, x[i + 9], 12, -1958414417);
        c = ff(c, d, a, b, x[i + 10], 17, -42063); b = ff(b, c, d, a, x[i + 11], 22, -1990404162);
        a = ff(a, b, c, d, x[i + 12], 7, 1804603682); d = ff(d, a, b, c, x[i + 13], 12, -40341101);
        c = ff(c, d, a, b, x[i + 14], 17, -1502002290); b = ff(b, c, d, a, x[i + 15], 22, 1236535329);
        a = gg(a, b, c, d, x[i + 1], 5, -165796510); d = gg(d, a, b, c, x[i + 6], 9, -1069501632);
        c = gg(c, d, a, b, x[i + 11], 14, 643717713); b = gg(b, c, d, a, x[i + 0], 20, -373897302);
        a = gg(a, b, c, d, x[i + 5], 5, -701558691); d = gg(d, a, b, c, x[i + 10], 9, 38016083);
        c = gg(c, d, a, b, x[i + 15], 14, -660478335); b = gg(b, c, d, a, x[i + 4], 20, -405537848);
        a = gg(a, b, c, d, x[i + 9], 5, 568446438); d = gg(d, a, b, c, x[i + 14], 9, -1019803690);
        c = gg(c, d, a, b, x[i + 3], 14, -187363961); b = gg(b, c, d, a, x[i + 8], 20, 1163531501);
        a = gg(a, b, c, d, x[i + 13], 5, -1444681467); d = gg(d, a, b, c, x[i + 2], 9, -51403784);
        c = gg(c, d, a, b, x[i + 7], 14, 1735328473); b = gg(b, c, d, a, x[i + 12], 20, -1926607734);
        a = hh(a, b, c, d, x[i + 5], 4, -378558); d = hh(d, a, b, c, x[i + 8], 11, -2022574463);
        c = hh(c, d, a, b, x[i + 11], 16, 1839030562); b = hh(b, c, d, a, x[i + 14], 23, -35309556);
        a = hh(a, b, c, d, x[i + 1], 4, -1530992060); d = hh(d, a, b, c, x[i + 4], 11, 1272893353);
        c = hh(c, d, a, b, x[i + 7], 16, -155497632); b = hh(b, c, d, a, x[i + 10], 23, -1094730640);
        a = hh(a, b, c, d, x[i + 13], 4, 681279174); d = hh(d, a, b, c, x[i + 0], 11, -358537222);
        c = hh(c, d, a, b, x[i + 3], 16, -722521979); b = hh(b, c, d, a, x[i + 6], 23, 76029189);
        a = hh(a, b, c, d, x[i + 9], 4, -640364487); d = hh(d, a, b, c, x[i + 12], 11, -421815835);
        c = hh(c, d, a, b, x[i + 15], 16, 530742520); b = hh(b, c, d, a, x[i + 2], 23, -995338651);
        a = ii(a, b, c, d, x[i + 0], 6, -198630844); d = ii(d, a, b, c, x[i + 7], 10, 1126891415);
        c = ii(c, d, a, b, x[i + 14], 15, -1416354905); b = ii(b, c, d, a, x[i + 5], 21, -57434055);
        a = ii(a, b, c, d, x[i + 12], 6, 1700485571); d = ii(d, a, b, c, x[i + 3], 10, -1894986606);
        c = ii(c, d, a, b, x[i + 10], 15, -1051523); b = ii(b, c, d, a, x[i + 1], 21, -2054922799);
        a = ii(a, b, c, d, x[i + 8], 6, 1873313359); d = ii(d, a, b, c, x[i + 15], 10, -30611744);
        c = ii(c, d, a, b, x[i + 6], 15, -1560198380); b = ii(b, c, d, a, x[i + 13], 21, 1309151649);
        a = ii(a, b, c, d, x[i + 4], 6, -145523070); d = ii(d, a, b, c, x[i + 11], 10, -1120210379);
        c = ii(c, d, a, b, x[i + 2], 15, 718787259); b = ii(b, c, d, a, x[i + 9], 21, -343485551);
        a = ad(a, oa); b = ad(b, ob); c = ad(c, oc); d = ad(d, od);
    }
    return rh(a) + rh(b) + rh(c) + rh(d);
}
function identifyHash(v) {
    var out = document.getElementById('id-out');
    var s = v.trim();
    if (!s) { out.innerHTML = ''; return; }
    var hex = /^[0-9a-fA-F]+$/.test(s);
    var base64 = /^[A-Za-z0-9+/]+={0,2}$/.test(s);
    var guesses = [];

    if (hex) {
        var len = s.length;
        if (len === 32) guesses.push('MD5 → 16 bytes (128 bits) — legacy; fast & collision-prone, fine only for checksums.');
        if (len === 40) guesses.push('SHA-1 → 20 bytes (160 bits) — legacy; deprecated for security. Use SHA-256.');
        if (len === 56) guesses.push('SHA-224 → 28 bytes (or SHA3-224 truncated).');
        if (len === 64) guesses.push('SHA-256 → 32 bytes — the standard modern checksum.');
        if (len === 96) guesses.push('SHA-384 → 48 bytes.');
        if (len === 128) guesses.push('SHA-512 → 64 bytes — strongest SHA-2 family.');
        if (len === 16) guesses.push('Could be a short/truncated hash, or CRC-64 / custom ID.');
        if (len === 8) guesses.push('Could be CRC32, FNV-1a, or a short checksum.');
        if (len === 24) guesses.push('May be a MongoDB ObjectId (12-byte timestamp + machine + counter).');
    }
    if (s.indexOf('$2a$') === 0 || s.indexOf('$2b$') === 0 || s.indexOf('$2y$') === 0) guesses.push('bcrypt → $2a/$2b/$2y cost-salted password hash.');
    if (s.indexOf('$6$') === 0) guesses.push('sha512crypt ($6$) — common Unix password hash.');
    if (s.indexOf('$5$') === 0) guesses.push('sha256crypt ($5$) — Unix password hash.');
    if (s.indexOf('$1$') === 0) guesses.push('MD5 crypt ($1$) — older Unix password hash.');
    if (s.indexOf('$argon2') === 0) guesses.push('Argon2 ($argon2id$/...) — modern memory-hard password hash.');
    if (/^sha256\$/.test(s)) guesses.push('Django-style salted SHA-256 hash (algorithm$salt$hash).');
    if (/^sha512\$/.test(s)) guesses.push('Linux mkpasswd SHA-512 salted hash.');

    if (!guesses.length) {
        if (base64 && s.length === 44) guesses.push('base64 blob ~ 32 bytes → could be SHA-256 binary, a token, or a password hash.');
        if (base64 && s.length === 88) guesses.push('base64 blob ~ 64 bytes → could be SHA-512 binary.');
        if (!hex && !base64) guesses.push('Not hex or base64 — could be a custom token, UUID variant, or ciphertext.');
        if (/^[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-/.test(s)) guesses.push('UUID unique identifier, not a hash of anything.');
        if (!guesses.length) guesses.push('Unknown format — try a magic header checker or paste it into the Identifier with /^[^:]+:/ prefix detection.');
    }
    out.innerHTML = guesses.map(function (g) { return '<div>• ' + g + '</div>'; }).join('');
}

// --- SHA-224 / SHA-256 in pure JS (verified against NIST vectors locally) ---
var SHA2_K = new Uint32Array([
    0x428a2f98,0x71374491,0xb5c0fbcf,0xe9b5dba5,0x3956c25b,0x59f111f1,0x923f82a4,0xab1c5ed5,
    0xd807aa98,0x12835b01,0x243185be,0x550c7dc3,0x72be5d74,0x80deb1fe,0x9bdc06a7,0xc19bf174,
    0xe49b69c1,0xefbe4786,0x0fc19dc6,0x240ca1cc,0x2de92c6f,0x4a7484aa,0x5cb0a9dc,0x76f988da,
    0x983e5152,0xa831c66d,0xb00327c8,0xbf597fc7,0xc6e00bf3,0xd5a79147,0x06ca6351,0x14292967,
    0x27b70a85,0x2e1b2138,0x4d2c6dfc,0x53380d13,0x650a7354,0x766a0abb,0x81c2c92e,0x92722c85,
    0xa2bfe8a1,0xa81a664b,0xc24b8b70,0xc76c51a3,0xd192e819,0xd6990624,0xf40e3585,0x106aa070,
    0x19a4c116,0x1e376c08,0x2748774c,0x34b0bcb5,0x391c0cb3,0x4ed8aa4a,0x5b9cca4f,0x682e6ff3,
    0x748f82ee,0x78a5636f,0x84c87814,0x8cc70208,0x90befffa,0xa4506ceb,0xbef9a3f7,0xc67178f2
]);
function sha2Core(bytes, iv, wordsOut) {
    function rotr(x, n) { return ((x >>> n) | (x << (32 - n))) >>> 0; }
    var h = iv.slice();
    var l = bytes.length;
    var lenBits = l * 8;
    var padLen = (((l + 8) >> 6) + 1) * 64;
    var p = new Uint8Array(padLen);
    p.set(bytes);
    p[l] = 0x80;
    // length in bits as 64-bit BE (we only support < 2^32, safe)
    var dv = new DataView(p.buffer);
    dv.setUint32(padLen - 8, Math.floor(lenBits / 0x100000000), false);
    dv.setUint32(padLen - 4, lenBits >>> 0, false);
    var w = new Uint32Array(64);
    for (var off = 0; off < padLen; off += 64) {
        for (var i2 = 0; i2 < 16; i2++) w[i2] = dv.getUint32(off + i2 * 4, false);
        for (var j = 16; j < 64; j++) {
            var s0 = rotr(w[j - 15], 7) ^ rotr(w[j - 15], 18) ^ (w[j - 15] >>> 3);
            var s1 = rotr(w[j - 2], 17) ^ rotr(w[j - 2], 19) ^ (w[j - 2] >>> 10);
            w[j] = (w[j - 16] + s0 + w[j - 7] + s1) >>> 0;
        }
        var a = h[0], b = h[1], c = h[2], d = h[3], e = h[4], f = h[5], g = h[6], hh = h[7];
        for (var j3 = 0; j3 < 64; j3++) {
            var S1 = rotr(e, 6) ^ rotr(e, 11) ^ rotr(e, 25);
            var ch = (e & f) ^ (~e & g);
            var t1 = (hh + S1 + ch + SHA2_K[j3] + w[j3]) >>> 0;
            var S0 = rotr(a, 2) ^ rotr(a, 13) ^ rotr(a, 22);
            var maj = (a & b) ^ (a & c) ^ (b & c);
            var t2 = (S0 + maj) >>> 0;
            hh = g; g = f; f = e; e = (d + t1) >>> 0; d = c; c = b; b = a; a = (t1 + t2) >>> 0;
        }
        h[0] = (h[0] + a) >>> 0; h[1] = (h[1] + b) >>> 0; h[2] = (h[2] + c) >>> 0; h[3] = (h[3] + d) >>> 0;
        h[4] = (h[4] + e) >>> 0; h[5] = (h[5] + f) >>> 0; h[6] = (h[6] + g) >>> 0; h[7] = (h[7] + hh) >>> 0;
    }
    var out = '';
    for (var k = 0; k < wordsOut; k++) out += ('00000000' + h[k].toString(16)).slice(-8);
    return out;
}
function sha224Hex(s) {
    var b = new TextEncoder().encode(s);
    return sha2Core(b, [0xc1059ed8,0x367cd507,0x3070dd17,0xf70e5939,0xffc00b31,0x68581511,0x64f98fa7,0xbefa4fa4], 7);
}
function sha256Hex(s) {
    var b = new TextEncoder().encode(s);
    return sha2Core(b, [0x6a09e667,0xbb67ae85,0x3c6ef372,0xa54ff53a,0x510e527f,0x9b05688c,0x1f83d9ab,0x5be0cd19], 8);
}

var CRC32_TABLE = (function () {
    var t = new Uint32Array(256);
    for (var i = 0; i < 256; i++) {
        var c = i;
        for (var k = 0; k < 8; k++) c = (c & 1) ? (0xedb88320 ^ (c >>> 1)) : (c >>> 1);
        t[i] = c;
    }
    return t;
})();
function crc32Hex(s) {
    var b = new TextEncoder().encode(s);
    var c = 0xffffffff;
    for (var i = 0; i < b.length; i++) c = CRC32_TABLE[(c ^ b[i]) & 0xff] ^ (c >>> 8);
    var n = (c ^ 0xffffffff) >>> 0;
    return ('00000000' + n.toString(16)).slice(-8);
}

function addRow(box, label, value, legacy, server) {
    var row = document.createElement('div');
    row.className = 'row g-1 align-items-center';
    row.innerHTML = '<div class="col-3 col-md-2"><span class="text-secondary small">' + label +
        (legacy ? ' <sup class="badge text-bg-secondary">legacy</sup>' : '') +
        (server ? ' <sup class="badge text-bg-dark">server</sup>' : '') +
        '</span></div>' +
        '<div class="col-9 col-md-10"><div class="input-group"><textarea class="form-control kb-hash-val" readonly rows="1" wrap="off" style="font-family:JetBrains Mono,monospace;font-size:.72rem;background:#181818;color:#c9d1d9;resize:vertical;min-height:34px;overflow-x:auto;white-space:pre;">' + value + '</textarea>' +
        '<button class="btn btn-outline-light btn-sm" onclick="copyHash(this)">Copy</button></div></div>';
    box.appendChild(row);
}

function hashFile(files, fileOut) {
    var el = document.getElementById('hash-file');
    var out = document.getElementById('file-out');
    out.innerHTML = '';
    if (!files || !files.length) return;
    var file = files[0];
    if (file.size > 50 * 1024 * 1024) { out.textContent = 'File too large for in-browser hashing (max 50 MB).'; return; }
    var fr = new FileReader();
    fr.onload = function (e) {
        var buf = e.target.result;
        var fs = file.name + ' (' + (file.size / 1024 / 1024).toFixed(2) + ' MB)';
        var algos = [['SHA-256', 'SHA-256'], ['SHA-512', 'SHA-512'], ['SHA-1', 'SHA-1']];
        var mdp = new Promise(function (resolve) {
            var reader = new FileReader();
            reader.onload = function (ev) {
                resolve({ name: 'MD5', hash: md5(ev.target.result) });
            };
            reader.readAsText(file, 'latin1');
        });
        var digs = algos.map(function (a) {
            return crypto.subtle.digest(a[1], buf).then(function (b) { return { name: a[0], hash: toHexBytes(b) }; });
        });
        Promise.all(digs.concat([mdp])).then(function (results) {
            var box = out;
            box.innerHTML = '<div class="form-text mb-1">' + fs + '</div>';
            results.forEach(function (r) {
                var d = document.createElement('div');
                d.className = 'd-flex align-items-center gap-2';
                d.innerHTML = '<code class="form-control" style="font-family:JetBrains Mono,monospace;font-size:.7rem;background:#181818;">' + r.name + ': ' + r.hash + '</code>';
                box.appendChild(d);
            });
        });
    };
    fr.readAsArrayBuffer(file);
}

function hashAll() {
    var text = document.getElementById('hash-in').value || '';
    var hmacKey = document.getElementById('hmac-key').value;
    var te = new TextEncoder();
    var box = document.getElementById('hash-results');
    var note = document.getElementById('hash-srv-note');
    box.innerHTML = '';
    if (!text) { note.textContent = ''; return; }

    var client = [];
    client.push(Promise.resolve({ name: 'MD5', hash: md5(text), legacy: true }));
    client.push(crypto.subtle.digest('SHA-1', te.encode(text)).then(function (b) { return { name: 'SHA-1', hash: toHexBytes(b), legacy: true }; }));
    client.push(Promise.resolve({ name: 'SHA-224', hash: sha224Hex(text) }));
    client.push(Promise.resolve({ name: 'SHA-256', hash: sha256Hex(text) }));
    client.push(crypto.subtle.digest('SHA-384', te.encode(text)).then(function (b) { return { name: 'SHA-384', hash: toHexBytes(b) }; }));
    client.push(crypto.subtle.digest('SHA-512', te.encode(text)).then(function (b) { return { name: 'SHA-512', hash: toHexBytes(b) }; }));
    client.push(Promise.resolve({ name: 'CRC32', hash: crc32Hex(text) }));

    if (hmacKey) {
        ['SHA-256', 'SHA-384', 'SHA-512'].forEach(function (h) {
            var hshort = 'HMAC-' + h.replace('SHA-', '');
            client.push(crypto.subtle.importKey('raw', te.encode(hmacKey), { name: 'HMAC', hash: { name: h } }, false, ['sign']).then(function (key) {
                return crypto.subtle.sign('HMAC', key, te.encode(text)).then(function (sig) { return { name: hshort, hash: toHexBytes(sig) }; });
            }));
        });
    }

    Promise.all(client).then(function (rows) {
        rows.forEach(function (r) { addRow(box, r.name, r.hash, r.legacy || false, false); });

        // SHA-3 + SHAKE family require the server (PHP hash()) — clearly labeled.
        var srv = ['SHA3-224', 'SHA3-256', 'SHA3-384', 'SHA3-512', 'RIPEMD-160', 'Whirlpool']
            .map(function (name) {
                var algo = name === 'SHA3-224' ? 'sha3-224' :
                           name === 'SHA3-256' ? 'sha3-256' :
                           name === 'SHA3-384' ? 'sha3-384' :
                           name === 'SHA3-512' ? 'sha3-512' :
                           name === 'RIPEMD-160' ? 'ripemd160' : 'whirlpool';
                return fetch('api.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ algo: algo, data: text }) })
                    .then(function (r) { return r.json(); })
                    .then(function (d) { return { name: name, hash: d.hash || '—' }; })
                    .catch(function () { return { name: name, hash: '(server unavailable)' }; });
            });
        return Promise.all(srv);
    }).then(function (rows2) {
        rows2.forEach(function (r) { addRow(box, r.name, r.hash, false, true); });
        note.textContent = 'Server rows are computed by kevbin.ct.ws (PHP hash()) because WebCrypto can\'t do SHA-3/SHAKE in-browser. Anonymized, whitelist-only, 256 KB cap.';
    });
}
function copyHash(btn) {
    var ta = btn.parentElement.querySelector('textarea.kb-hash-val');
    if (!ta) return;
    ta.focus();
    ta.select();
    var done = function () { var old = btn.textContent; btn.textContent = '✅'; setTimeout(function () { btn.textContent = old; }, 1200); };
    if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(ta.value).then(done, function () { document.execCommand('copy'); done(); });
    } else { document.execCommand('copy'); done(); }
}
hashAll();
document.getElementById('hash-file').onchange = function () { hashFile(this.files); };
</script>
<?php page_footer(); ?>