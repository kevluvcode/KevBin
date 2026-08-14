<?php
require_once __DIR__ . '/../../functions.php';

start_session();
page_header('JWT Inspector');
?>
<div class="container" style="max-width: 1000px;">
    <h1 class="h4 mb-1 reveal in-view">🔑 JWT Inspector</h1>
    <p class="text-secondary mb-4 reveal in-view">Paste a JWT (or a Cookie/Authorization value containing one) to decode header + payload, check expiry and verify the HMAC signature with a secret. All local in your browser.</p>

    <div class="card mb-4 reveal in-view"><div class="card-body">
        <label class="form-label">Token</label>
        <textarea id="jwt-in" class="form-control mb-2" rows="4" style="font-family:'JetBrains Mono',monospace;font-size:.8rem;" placeholder="eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJzdWIiOiIxMjMifQ.abc..."></textarea>
        <div class="row g-2 align-items-center">
            <div class="col-md-5">
                <input id="jwt-secret" class="form-control" placeholder="HMAC secret (leave empty to skip verify)" style="font-family:'JetBrains Mono',monospace;">
            </div>
            <div class="col-md-3">
                <select id="jwt-alg" class="form-select">
                    <option value="HS256">HS256</option>
                    <option value="HS384">HS384</option>
                    <option value="HS512">HS512</option>
                </select>
            </div>
            <div class="col-md-4 d-flex gap-2">
                <button class="btn btn-primary" onclick="inspect()">Inspect</button>
                <button class="btn btn-outline-light" onclick="sample()">Sample token</button>
            </div>
        </div>
    </div></div>

    <div id="jwt-out"></div>
</div>

<script>
function b64dec(s) {
    s = s.replace(/-/g, '+').replace(/_/g, '/');
    while (s.length % 4) s += '=';
    try { return decodeURIComponent(escape(atob(s))); } catch (e) { return atob(s); }
}
function b64raw(s) {
    s = s.replace(/-/g, '+').replace(/_/g, '/');
    while (s.length % 4) s += '=';
    return atob(s);
}
function esc(x) { return String(x).replace(/&/g,'&amp;').replace(/</g,'&lt;'); }
async function hmac(alg, secret, data) {
    const key = await crypto.subtle.importKey('raw', new TextEncoder().encode(secret),
        { name: 'HMAC', hash: { name: alg === 'HS256' ? 'SHA-256' : (alg === 'HS384' ? 'SHA-384' : 'SHA-512') } }, false, ['sign']);
    const sig = await crypto.subtle.sign('HMAC', key, new TextEncoder().encode(data));
    return btoa(String.fromCharCode.apply(null, new Uint8Array(sig))).replace(/=+$/,'').replace(/\+/g,'-').replace(/\//g,'_');
}
function inspect() {
    var raw = document.getElementById('jwt-in').value.trim();
    var m = raw.match(/Bearer\s+(\S+)/i) || raw.match(/eyJ[A-Za-z0-9_-]{6,}\.[A-Za-z0-9_-]{6,}\.[A-Za-z0-9_-]{6,}(?:\.[A-Za-z0-9_-]+)?/);
    if (m) raw = typeof m[1] !== 'undefined' ? m[1] : m[0];
    var parts = raw.split('.');
    if (parts.length < 2 || parts.length > 4) {
        document.getElementById('jwt-out').innerHTML = '<div class="alert alert-danger">That does not look like a JWT (need dot-separated parts).</div>';
        return;
    }
    var out = '';
    try {
        var header = JSON.parse(b64dec(parts[0]));
        var payload = JSON.parse(b64dec(parts[1]));
        out += card(header, 'Header');
        out += card(payload, 'Payload');
        var claims = [];
        if (payload.exp) claims.push(['expires', new Date(payload.exp * 1000).toISOString(), (Date.now() / 1000) > payload.exp ? 'EXPIRED' : 'OK']);
        if (payload.iat) claims.push(['issued at', new Date(payload.iat * 1000).toISOString(), '']);
        if (payload.nbf) claims.push(['not before', new Date(payload.nbf * 1000).toISOString(), (Date.now() / 1000) < payload.nbf - 60 ? 'NOT YET VALID' : 'OK']);
        if (claims.length) {
            out += '<div class="card mb-3"><div class="card-body"><h2 class="h6 mb-2">Time claims</h2><table class="table table-sm table-dark align-middle mb-0">';
            claims.forEach(function (c) {
                out += '<tr><td class="text-secondary">' + c[0] + '</td><td>' + esc(c[1]) + '</td><td>' + (c[2] ? (c[2] === 'EXPIRED' ? '<span class="badge bg-danger">EXPIRED</span>' : '<span class="badge bg-success">' + c[2] + '</span>') : '') + '</td></tr>';
            });
            out += '</table></div></div>';
        }
        out += '<div class="card mb-3"><div class="card-body"><h2 class="h6 mb-2">Signature</h2>';
        if (parts.length === 2) {
            out += '<span class="badge bg-warning text-dark">UNSIGNED</span> <span class="text-secondary small">(2-part JWT, no signature)</span>';
        } else {
            var sig = parts.slice(-1)[0];
            var sigBytes = b64raw(sig);
            out += '<code class="small">' + esc(sig) + '</code>';
            if (sigBytes.length >= 4) {
                var bytes = [];
                for (var i = 0; i < sigBytes.length && i < 8; i++) bytes.push(sigBytes.charCodeAt(i).toString(16).padStart(2, '0'));
                out += '<div class="text-secondary small mt-1">raw bytes: ' + bytes.join(' ') + '…</div>';
            }
        }
        out += '</div></div>';
    } catch (e) {
        out = '<div class="alert alert-danger">Could not decode: ' + esc(e.message) + '</div>';
    }
    document.getElementById('jwt-out').innerHTML = out;

    var secret = document.getElementById('jwt-secret').value;
    var alg = document.getElementById('jwt-alg').value;
    if (secret && parts.length >= 3) {
        var signing = parts[0] + '.' + parts[1];
        hmac(alg, secret, signing).then(function (expect) {
            var have = parts.slice(-1)[0];
            var ok = have === expect;
            var box = document.createElement('div');
            box.className = 'card mb-3';
            box.innerHTML = '<div class="card-body"><h2 class="h6 mb-2">HMAC ' + alg.toUpperCase() + ' verification</h2>' +
                (ok
                    ? '<span class="badge bg-success">SIGNATURE MATCHES</span> — this secret signs this token.'
                    : '<span class="badge bg-danger">NO MATCH</span> — wrong secret or algorithm.')
                + '<div class="text-secondary small mt-2">computed: <code>' + esc(expect) + '</code></div></div>';
            document.getElementById('jwt-out').appendChild(box);
        });
    }
}
function card(obj, title) {
    var rows = '';
    Object.keys(obj).forEach(function (k) {
        var v = obj[k];
        var nice = (typeof v === 'object' && v !== null) ? JSON.stringify(v) : String(v);
        rows += '<tr><td class="text-secondary text-nowrap">' + esc(k) + '</td><td style="word-break:break-all;">' + esc(nice) + '</td></tr>';
    });
    return '<div class="card mb-3"><div class="card-body"><h2 class="h6 mb-2">' + title + '</h2><table class="table table-sm table-dark align-middle mb-0">' + rows + '</table></div></div>';
}
function sample() {
    var p = btoa(unescape(encodeURIComponent(JSON.stringify({ alg: 'HS256', typ: 'JWT' })))).replace(/=+$/,'').replace(/\+/g,'-').replace(/\//g,'_');
    var b = btoa(unescape(encodeURIComponent(JSON.stringify({ sub: '1234567', name: 'John Doe', iat: Math.floor(Date.now()/1000) - 300, exp: Math.floor(Date.now()/1000) + 3600 })))).replace(/=+$/,'').replace(/\+/g,'-').replace(/\//g,'_');
    document.getElementById('jwt-in').value = p + '.' + b + '.dummy-signature';
    inspect();
}
</script>
<?php page_footer(); ?>