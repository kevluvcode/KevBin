<?php
require_once __DIR__ . '/../../functions.php';

start_session();
page_header('Hash Generator');
?>
<div class="container" style="max-width: 900px;">
    <h1 class="h4 mb-1 reveal in-view">Hash Generator</h1>
    <p class="text-secondary mb-4 reveal in-view">MD5, SHA-1, SHA-256, SHA-512 computed locally via WebCrypto (MD5 in pure JS). Also identify an unknown hash format. Nothing is sent to the server.</p>

    <div class="alert alert-secondary reveal in-view">
        <strong>What is a hash?</strong> A one-way function that turns any input into a fixed-length fingerprint. You can't reverse it — the same input always gives the same hash, and even a tiny change gives a completely different one. Use this to verify files, store passwords safely, or build checksums.
    </div>

    <div class="card reveal in-view">
        <div class="card-body">
            <label class="form-label">Input</label>
            <textarea id="hash-in" class="form-control" rows="4" style="font-family:'JetBrains Mono',monospace; font-size:.85rem;" oninput="hashAll()" placeholder="text to hash">KevBin tools</textarea>

            <div class="d-grid gap-2 mt-3" id="hash-results"></div>
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
        a = ff(a, b, c, d, x[i + 0], 7, -680876936);
        d = ff(d, a, b, c, x[i + 1], 12, -389564586);
        c = ff(c, d, a, b, x[i + 2], 17, 606105819);
        b = ff(b, c, d, a, x[i + 3], 22, -1044525330);
        a = ff(a, b, c, d, x[i + 4], 7, -176418897);
        d = ff(d, a, b, c, x[i + 5], 12, 1200080426);
        c = ff(c, d, a, b, x[i + 6], 17, -1473231341);
        b = ff(b, c, d, a, x[i + 7], 22, -45705983);
        a = ff(a, b, c, d, x[i + 8], 7, 1770035416);
        d = ff(d, a, b, c, x[i + 9], 12, -1958414417);
        c = ff(c, d, a, b, x[i + 10], 17, -42063);
        b = ff(b, c, d, a, x[i + 11], 22, -1990404162);
        a = ff(a, b, c, d, x[i + 12], 7, 1804603682);
        d = ff(d, a, b, c, x[i + 13], 12, -40341101);
        c = ff(c, d, a, b, x[i + 14], 17, -1502002290);
        b = ff(b, c, d, a, x[i + 15], 22, 1236535329);

        a = gg(a, b, c, d, x[i + 1], 5, -165796510);
        d = gg(d, a, b, c, x[i + 6], 9, -1069501632);
        c = gg(c, d, a, b, x[i + 11], 14, 643717713);
        b = gg(b, c, d, a, x[i + 0], 20, -373897302);
        a = gg(a, b, c, d, x[i + 5], 5, -701558691);
        d = gg(d, a, b, c, x[i + 10], 9, 38016083);
        c = gg(c, d, a, b, x[i + 15], 14, -660478335);
        b = gg(b, c, d, a, x[i + 4], 20, -405537848);
        a = gg(a, b, c, d, x[i + 9], 5, 568446438);
        d = gg(d, a, b, c, x[i + 14], 9, -1019803690);
        c = gg(c, d, a, b, x[i + 3], 14, -187363961);
        b = gg(b, c, d, a, x[i + 8], 20, 1163531501);
        a = gg(a, b, c, d, x[i + 13], 5, -1444681467);
        d = gg(d, a, b, c, x[i + 2], 9, -51403784);
        c = gg(c, d, a, b, x[i + 7], 14, 1735328473);
        b = gg(b, c, d, a, x[i + 12], 20, -1926607734);

        a = hh(a, b, c, d, x[i + 5], 4, -378558);
        d = hh(d, a, b, c, x[i + 8], 11, -2022574463);
        c = hh(c, d, a, b, x[i + 11], 16, 1839030562);
        b = hh(b, c, d, a, x[i + 14], 23, -35309556);
        a = hh(a, b, c, d, x[i + 1], 4, -1530992060);
        d = hh(d, a, b, c, x[i + 4], 11, 1272893353);
        c = hh(c, d, a, b, x[i + 7], 16, -155497632);
        b = hh(b, c, d, a, x[i + 10], 23, -1094730640);
        a = hh(a, b, c, d, x[i + 13], 4, 681279174);
        d = hh(d, a, b, c, x[i + 0], 11, -358537222);
        c = hh(c, d, a, b, x[i + 3], 16, -722521979);
        b = hh(b, c, d, a, x[i + 6], 23, 76029189);
        a = hh(a, b, c, d, x[i + 9], 4, -640364487);
        d = hh(d, a, b, c, x[i + 12], 11, -421815835);
        c = hh(c, d, a, b, x[i + 15], 16, 530742520);
        b = hh(b, c, d, a, x[i + 2], 23, -995338651);

        a = ii(a, b, c, d, x[i + 0], 6, -198630844);
        d = ii(d, a, b, c, x[i + 7], 10, 1126891415);
        c = ii(c, d, a, b, x[i + 14], 15, -1416354905);
        b = ii(b, c, d, a, x[i + 5], 21, -57434055);
        a = ii(a, b, c, d, x[i + 12], 6, 1700485571);
        d = ii(d, a, b, c, x[i + 3], 10, -1894986606);
        c = ii(c, d, a, b, x[i + 10], 15, -1051523);
        b = ii(b, c, d, a, x[i + 1], 21, -2054922799);
        a = ii(a, b, c, d, x[i + 8], 6, 1873313359);
        d = ii(d, a, b, c, x[i + 15], 10, -30611744);
        c = ii(c, d, a, b, x[i + 6], 15, -1560198380);
        b = ii(b, c, d, a, x[i + 13], 21, 1309151649);
        a = ii(a, b, c, d, x[i + 4], 6, -145523070);
        d = ii(d, a, b, c, x[i + 11], 10, -1120210379);
        c = ii(c, d, a, b, x[i + 2], 15, 718787259);
        b = ii(b, c, d, a, x[i + 9], 21, -343485551);

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
        if (len === 32) guesses.push('MD5 → 16 bytes (128 bits) — very common, fast, no longer collision-safe.');
        if (len === 40) guesses.push('SHA-1 → 20 bytes (160 bits) — deprecated for security; use SHA-256.');
        if (len === 56) guesses.push('SHA-224 → 28 bytes');
        if (len === 64) guesses.push('SHA-256 → 32 bytes — the standard modern checksum.');
        if (len === 96) guesses.push('SHA-384 → 48 bytes');
        if (len === 128) guesses.push('SHA-512 → 64 bytes — strongest SHA-2 family.');
        if (len === 16) guesses.push('Could be a short/truncated hash or custom ID.');
        if (len === 24 && /^[0-9a-fA-F]{24}$/.test(s)) guesses.push('May be a MongoDB ObjectId (12-byte timestamp + machine + counter).');
    }
    if (s.indexOf('$2a$') === 0 || s.indexOf('$2b$') === 0 || s.indexOf('$2y$') === 0) guesses.push('bcrypt → $2a/$2b/$2y cost-salted password hash.');
    if (s.indexOf('$6$') === 0) guesses.push('sha512crypt ($6$) — common Unix password hash.');
    if (s.indexOf('$5$') === 0) guesses.push('sha256crypt ($5$) — Unix password hash.');
    if (s.indexOf('$1$') === 0) guesses.push('MD5 crypt ($1$) — older Unix password hash.');
    if (s.indexOf('$argon2') === 0) guesses.push('Argon2 ($argon2id$/...) — modern memory-hard password hash.');

    if (!guesses.length) {
        if (base64 && s.length === 44) guesses.push('base64 blob ~ 32 bytes → could be SHA-256 binary, a token, or a password hash.');
        if (base64 && s.length === 88) guesses.push('base64 blob ~ 64 bytes → could be SHA-512 binary.');
        if (!hex && !base64) guesses.push('Not hex or base64 — could be a custom token, UUID variant, or ciphertext.');
        if (/^[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-/.test(s)) guesses.push('UUID unique identifier, not a hash of anything.');
        if (!guesses.length) guesses.push('Unknown format — try pasteing it into an online magic header checker.');
    }
    out.innerHTML = guesses.map(function (g) { return '<div>• ' + g + '</div>'; }).join('');
}

function hashAll() {
    var text = document.getElementById('hash-in').value || '';
    var te = new TextEncoder();
    var box = document.getElementById('hash-results');
    box.innerHTML = '';

    var algo = ['SHA-1', 'SHA-256', 'SHA-512'].map(function (a) {
        return crypto.subtle.digest(a, te.encode(text)).then(function (buf) {
            return { name: a, hash: toHexBytes(buf) };
        });
    });

    var md5p = Promise.resolve().then(function () {
        return { name: 'MD5', hash: md5(text) };
    });

    Promise.all([Promise.all(algo), md5p]).then(function (results) {
        results[1].concat(results[0]).forEach(function (r) {
            var row = document.createElement('div');
            row.className = 'row g-1 align-items-center';
            row.innerHTML = '<div class="col-3 col-md-2"><span class="text-secondary small">' + r.name + '</span></div>' +
                '<div class="col-9 col-md-10"><code class="form-control code-out" readonly style="font-family:JetBrains Mono,monospace;font-size:.75rem;background:#181818;color:#c9d1d9;">' + r.hash + '</code></div>';
            box.appendChild(row);
        });
    });
}
</script>
<?php page_footer(); ?>