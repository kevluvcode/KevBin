<?php
require_once __DIR__ . '/../../functions.php';

start_session();
$GLOBALS['_meta'] = [
    'description' => 'Decode and inspect JSON Web Tokens (JWT) online. View header, payload and signature, verify HS256/HS384/HS512 HMAC signatures and expiry claims. Free, in your browser.',
    'keywords' => 'jwt decoder, jwt inspector, decode jwt, json web token, jwt verify, hs256, jwt debugger',
];
page_header('JWT Decoder — Decode & Inspect JSON Web Tokens');
?>
<div class="container" style="max-width: 980px;">
    <h1 class="h4 mb-2 reveal in-view">JWT Decoder & Inspector</h1>
    <p class="text-secondary mb-1 reveal in-view">Decode any JWT to read its header, payload and signature instantly. Paste a token to see the algorithm, issuer, subject, expiry and custom claims — and verify an <strong>HS256 / HS384 / HS512</strong> HMAC signature against a secret you provide. Everything runs in your browser.</p>
    <p class="text-secondary mb-4 reveal in-view">JSON Web Tokens are used for authentication and session handling across the web. The middle part of a JWT is just Base64-encoded JSON — anyone can read it, which is why you should never put secrets inside a token. This tool makes it obvious what a token actually contains.</p>

    <div class="card reveal in-view">
        <div class="card-body">
            <label class="form-label small text-secondary">JWT (eyJ…)</label>
            <textarea id="jwt-in" class="form-control mb-3" rows="3" style="font-family:'JetBrains Mono',monospace;font-size:.85rem;" placeholder="eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJzdWIiOiIxMjM0NTY3ODkwIn0.SflKxwRJSMeKKF2QT4fwpMeJf36POk6yJV_adQssw5c" oninput="decodeJWT()"></textarea>

            <div class="row g-3 mb-3">
                <div class="col-md-9">
                    <label class="form-label small text-secondary">HS* verification secret (symmetric algos only)</label>
                    <input id="jwt-secret" class="form-control" style="font-family:'JetBrains Mono',monospace;font-size:.85rem;" oninput="decodeJWT()">
                </div>
                <div class="col-md-3 d-flex align-items-end">
                    <div id="jwt-sig" class="badge d-block"></div>
                </div>
            </div>

            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label small text-secondary">Header</label>
                    <textarea id="jwt-header" class="form-control" rows="6" readonly style="font-family:'JetBrains Mono',monospace;font-size:.8rem;"></textarea>
                </div>
                <div class="col-md-6">
                    <label class="form-label small text-secondary">Payload (claims)</label>
                    <textarea id="jwt-payload" class="form-control" rows="6" readonly style="font-family:'JetBrains Mono',monospace;font-size:.8rem;"></textarea>
                </div>
            </div>
            <div class="form-text mt-2">Standard claims decoded: <code>iss</code> issuer, <code>sub</code> subject, <code>aud</code> audience, <code>exp</code> expiry, <code>nbf</code> not-before, <code>iat</code> issued-at, <code>jti</code> token id.</div>
        </div>
    </div>

    <h2 class="h6 mt-4 reveal in-view" style="border-bottom:1px solid var(--line);padding-bottom:.5rem;">How JWT works</h2>
    <p class="text-secondary small reveal in-view">A JWT has three dot-separated parts: <strong>header</strong> (algorithm + token type), <strong>payload</strong> (claims), and a <strong>signature</strong> computed over <code>header.payload</code>. Tokens signed with <code>alg: none</code> or a weak HS* secret are a known attack vector — if you can read a token that shouldn't be readable, or verify it with a guessable secret, that's a red flag. This decoder is the standard first step in any API security review.</p>
</div>

<script>
function $j2(id) { return document.getElementById(id); }
function b64urlToJson(seg) {
    var b64 = seg.replace(/-/g, '+').replace(/_/g, '/');
    while (b64.length % 4) b64 += '=';
    var bin = atob(b64);
    var bytes = new Uint8Array(bin.length);
    for (var i = 0; i < bin.length; i++) bytes[i] = bin.charCodeAt(i);
    return new TextDecoder().decode(bytes);
}
function decodeJWT() {
    var tok = $j2('jwt-in').value.trim();
    var hEl = $j2('jwt-header'), pEl = $j2('jwt-payload'), sEl = $j2('jwt-sig');
    hEl.value = ''; pEl.value = ''; sEl.textContent = ''; sEl.className = 'badge d-block';
    if (!tok) return;
    var parts = tok.split('.');
    if (parts.length !== 3) { hEl.value = 'Not a valid JWT — expected 3 dot-separated parts (header.payload.signature).'; return; }
    try {
        var header = JSON.parse(b64urlToJson(parts[0]));
        var payload = JSON.parse(b64urlToJson(parts[1]));
        hEl.value = JSON.stringify(header, null, 2);
        pEl.value = JSON.stringify(payload, null, 2);

        var notes = [];
        ['exp', 'nbf', 'iat'].forEach(function (cl) {
            if (typeof payload[cl] === 'number' && payload[cl] > 1000000000) {
                var d = new Date(payload[cl] * 1000);
                notes.push(cl + ' → ' + d.toISOString() + (cl === 'exp' && payload[cl] * 1000 < Date.now() ? ' (EXPIRED)' : ''));
            }
        });
        if (payload.exp && payload.exp * 1000 < Date.now()) notes.push('⚠ Token has expired.');
        if ((header.alg || '').toLowerCase() === 'none') notes.push('⚠ alg:none — token integrity is NOT secured!');

        var alg = header.alg || 'none';
        var secret = $j2('jwt-secret').value;
        if (/^HS(256|384|512)$/.test(alg) && secret) {
            var keyP = crypto.subtle.importKey('raw', new TextEncoder().encode(secret), { name: 'HMAC', hash: { name: alg === 'HS256' ? 'SHA-256' : alg === 'HS384' ? 'SHA-384' : 'SHA-512' } }, false, ['sign', 'verify']);
            var data = new TextEncoder().encode(parts[0] + '.' + parts[1]);
            var sigBytes = new Uint8Array(b64urlToByteArray(parts[2]));
            keyP.then(function (key) { return crypto.subtle.verify('HMAC', key, sigBytes, data); })
                .then(function (ok) {
                    sEl.textContent = ok ? '✅ Signature VALID' : '❌ Signature INVALID';
                    sEl.className = 'badge d-block ' + (ok ? 'text-bg-success' : 'text-bg-danger');
                })
                .catch(function () { sEl.textContent = 'Signature could not be verified'; sEl.className = 'badge d-block text-bg-secondary'; });
        } else if (/^HS(256|384|512)$/.test(alg)) {
            sEl.textContent = 'Enter a secret to verify the ' + alg + ' signature';
            sEl.className = 'badge d-block text-bg-secondary';
        } else if (alg !== 'none') {
            sEl.textContent = alg + ' signature (asymmetric) — not verifiable in-browser';
            sEl.className = 'badge d-block text-bg-secondary';
        }
        if (notes.length) {
            sEl.textContent = (sEl.textContent ? sEl.textContent + ' · ' : '') + notes.join(' · ');
        }
    } catch (e) { hEl.value = 'decode error: ' + e.message; }
}
function b64urlToByteArray(seg) {
    var b64 = seg.replace(/-/g, '+').replace(/_/g, '/');
    while (b64.length % 4) b64 += '=';
    var bin = atob(b64);
    var out = new Array(bin.length);
    for (var i = 0; i < bin.length; i++) out[i] = bin.charCodeAt(i);
    return out;
}
decodeJWT();
</script>
<?php page_footer(); ?>