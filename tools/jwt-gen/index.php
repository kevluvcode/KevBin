<?php
require_once __DIR__ . '/../../functions.php';

start_session();
$GLOBALS['_meta'] = [
    'description' => 'Generate, edit and decode JSON Web Tokens (JWT) online. Create HS256/HS384/HS512 signed tokens with custom headers and payloads, decode existing JWTs, and verify HMAC signatures — all client-side in your browser.',
    'keywords' => 'jwt generator, jwt editor, create jwt, json web token, hs256, jwt signer, jwt encoder, jwt decoder',
];
page_header('JWT Generator & Editor — Create, Edit and Decode JSON Web Tokens');
?>
<div class="container" style="max-width: 1000px;">
    <h1 class="h4 mb-1 reveal in-view">JWT Generator & Editor</h1>
    <p class="text-secondary mb-4 reveal in-view">Create, sign, edit and decode JSON Web Tokens entirely in your browser. Choose your HMAC secret, pick an algorithm, and the token updates live. Paste an existing JWT to decode it or verify its signature.</p>

    <div class="card mb-4 reveal in-view"><div class="card-body">
        <h2 class="h6 mb-3">Generate a JWT</h2>
        <div class="row g-3">
            <div class="col-md-6">
                <div class="d-flex align-items-center justify-content-between mb-1">
                    <label class="form-label small text-secondary mb-0">Header (JSON)</label>
                    <select id="preset-headers" class="form-select form-select-sm" style="width:auto;font-size:.75rem;" onchange="applyHeaderPreset()">
                        <option value="">Presets</option>
                        <option value="alg-typ">HS256 + JWT</option>
                        <option value="none">alg: none</option>
                        <option value="rs256">RS256 (RSA)</option>
                    </select>
                </div>
                <textarea id="jwt-header" class="form-control" rows="5" style="font-family:'JetBrains Mono',monospace;font-size:.8rem;" oninput="generateJWT()">{ "alg": "HS256", "typ": "JWT" }</textarea>
            </div>
            <div class="col-md-6">
                <div class="d-flex align-items-center justify-content-between mb-1">
                    <label class="form-label small text-secondary mb-0">Payload (JSON)</label>
                    <div class="d-flex gap-1">
                        <select id="preset-payloads" class="form-select form-select-sm" style="width:auto;font-size:.75rem;" onchange="applyPayloadPreset()">
                            <option value="">Common Payloads</option>
                            <option value="login">Login Token</option>
                            <option value="apikey">API Key</option>
                            <option value="reset">Password Reset</option>
                        </select>
                        <button class="btn btn-outline-light btn-sm" style="font-size:.7rem;" onclick="insertTimestamp('iat')">Now (iat)</button>
                        <button class="btn btn-outline-light btn-sm" style="font-size:.7rem;" onclick="insertTimestamp('exp')">Now (exp)</button>
                    </div>
                </div>
                <textarea id="jwt-payload" class="form-control" rows="5" style="font-family:'JetBrains Mono',monospace;font-size:.8rem;" oninput="generateJWT()">{ "sub": "1234567890", "name": "John Doe", "iat": 1700000000 }</textarea>
            </div>
        </div>

        <div class="row g-3 mt-2">
            <div class="col-md-5">
                <label class="form-label small text-secondary">HMAC Secret Key</label>
                <input id="jwt-secret" class="form-control" value="your-secret-key" style="font-family:'JetBrains Mono',monospace;font-size:.85rem;" oninput="generateJWT()">
            </div>
            <div class="col-md-3">
                <label class="form-label small text-secondary">Algorithm</label>
                <select id="jwt-alg" class="form-select" onchange="generateJWT()">
                    <option value="HS256">HS256</option>
                    <option value="HS384">HS384</option>
                    <option value="HS512">HS512</option>
                </select>
            </div>
            <div class="col-md-4 d-flex align-items-end gap-2">
                <button class="btn btn-primary" onclick="generateJWT()">Generate</button>
                <button class="btn btn-outline-light" onclick="copyToken()">Copy Token</button>
            </div>
        </div>

        <div class="mt-3">
            <div class="d-flex align-items-center justify-content-between mb-1">
                <label class="form-label small text-secondary mb-0">Generated JWT</label>
                <span id="expiry-badge" class="badge"></span>
            </div>
            <textarea id="jwt-output" class="form-control" rows="3" readonly style="font-family:'JetBrains Mono',monospace;font-size:.78rem;background:#181818;color:#c9d1d9;"></textarea>
        </div>
    </div></div>

    <div class="card mb-4 reveal in-view"><div class="card-body">
        <h2 class="h6 mb-3">Decode a JWT</h2>
        <label class="form-label small text-secondary">Paste a JWT token</label>
        <textarea id="decode-in" class="form-control mb-3" rows="3" style="font-family:'JetBrains Mono',monospace;font-size:.8rem;" placeholder="eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJzdWIiOiIxMjMifQ.abc..." oninput="decodeJWT()"></textarea>

        <div class="row g-3 mb-3">
            <div class="col-md-8">
                <label class="form-label small text-secondary">Verification Secret</label>
                <input id="decode-secret" class="form-control" style="font-family:'JetBrains Mono',monospace;font-size:.85rem;" oninput="decodeJWT()">
            </div>
            <div class="col-md-4 d-flex align-items-end">
                <button class="btn btn-outline-primary" onclick="verifyJWT()">Verify Signature</button>
            </div>
        </div>

        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label small text-secondary">Decoded Header</label>
                <textarea id="decode-header" class="form-control" rows="6" readonly style="font-family:'JetBrains Mono',monospace;font-size:.8rem;"></textarea>
            </div>
            <div class="col-md-6">
                <label class="form-label small text-secondary">Decoded Payload</label>
                <textarea id="decode-payload" class="form-control" rows="6" readonly style="font-family:'JetBrains Mono',monospace;font-size:.8rem;"></textarea>
            </div>
        </div>
        <div id="decode-sig" class="mt-2"></div>
        <div id="decode-time" class="mt-2"></div>
    </div></div>

    <div class="alert alert-secondary reveal in-view">
        <strong>How it works:</strong> A JWT has three Base64URL-encoded parts: <code>header.payload.signature</code>. The signature is HMAC-SHA256/384/512 of the encoded header and payload using your secret key. Everything runs locally in your browser — nothing is sent to any server.
    </div>
</div>

<script>
var $j2 = function (id) { return document.getElementById(id); };

function b64urlEncode(str) {
    return btoa(unescape(encodeURIComponent(str)))
        .replace(/=+$/, '')
        .replace(/\+/g, '-')
        .replace(/\//g, '_');
}

function b64urlDecode(seg) {
    var b64 = seg.replace(/-/g, '+').replace(/_/g, '/');
    while (b64.length % 4) b64 += '=';
    try { return decodeURIComponent(escape(atob(b64))); }
    catch (e) { return atob(b64); }
}

function b64urlDecodeBytes(seg) {
    var b64 = seg.replace(/-/g, '+').replace(/_/g, '/');
    while (b64.length % 4) b64 += '=';
    var bin = atob(b64);
    var out = new Uint8Array(bin.length);
    for (var i = 0; i < bin.length; i++) out[i] = bin.charCodeAt(i);
    return out;
}

async function hmacSign(alg, secret, data) {
    var hashName = alg === 'HS256' ? 'SHA-256' : alg === 'HS384' ? 'SHA-384' : 'SHA-512';
    var key = await crypto.subtle.importKey(
        'raw',
        new TextEncoder().encode(secret),
        { name: 'HMAC', hash: { name: hashName } },
        false,
        ['sign']
    );
    var sig = await crypto.subtle.sign('HMAC', key, new TextEncoder().encode(data));
    return btoa(String.fromCharCode.apply(null, new Uint8Array(sig)))
        .replace(/=+$/, '')
        .replace(/\+/g, '-')
        .replace(/\//g, '_');
}

function esc(x) {
    return String(x).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
}

function generateJWT() {
    var headerText = $j2('jwt-header').value.trim();
    var payloadText = $j2('jwt-payload').value.trim();
    var secret = $j2('jwt-secret').value;
    var alg = $j2('jwt-alg').value;
    var output = $j2('jwt-output');
    var badge = $j2('expiry-badge');

    badge.textContent = '';
    badge.className = 'badge';

    try {
        JSON.parse(headerText);
    } catch (e) {
        output.value = 'Invalid header JSON: ' + e.message;
        return;
    }

    try {
        JSON.parse(payloadText);
    } catch (e) {
        output.value = 'Invalid payload JSON: ' + e.message;
        return;
    }

    var encHeader = b64urlEncode(headerText);
    var encPayload = b64urlEncode(payloadText);
    var signingInput = encHeader + '.' + encPayload;

    hmacSign(alg, secret, signingInput).then(function (sig) {
        output.value = signingInput + '.' + sig;
        updateExpiryBadge(payloadText);
    });
}

function updateExpiryBadge(payloadText) {
    var badge = $j2('expiry-badge');
    try {
        var payload = JSON.parse(payloadText);
        if (payload.exp) {
            var now = Math.floor(Date.now() / 1000);
            var diff = payload.exp - now;
            if (diff <= 0) {
                badge.textContent = 'EXPIRED';
                badge.className = 'badge text-bg-danger';
            } else if (diff < 300) {
                badge.textContent = 'Expiring in ' + diff + 's';
                badge.className = 'badge text-bg-warning text-dark';
            } else if (diff < 3600) {
                badge.textContent = 'Expiring in ' + Math.floor(diff / 60) + 'm';
                badge.className = 'badge text-bg-info';
            } else {
                badge.textContent = 'Valid for ' + Math.floor(diff / 3600) + 'h ' + Math.floor((diff % 3600) / 60) + 'm';
                badge.className = 'badge text-bg-success';
            }
        } else if (payload.iat) {
            var elapsed = Math.floor(Date.now() / 1000) - payload.iat;
            if (elapsed < 0) {
                badge.textContent = 'Not yet valid';
                badge.className = 'badge text-bg-warning text-dark';
            } else {
                badge.textContent = 'No exp set · issued ' + Math.floor(elapsed / 60) + 'm ago';
                badge.className = 'badge text-bg-secondary';
            }
        }
    } catch (e) {}
}

function insertTimestamp(field) {
    var ta = $j2('jwt-payload');
    var now = Math.floor(Date.now() / 1000);
    try {
        var obj = JSON.parse(ta.value);
        obj[field] = now;
        ta.value = JSON.stringify(obj, null, 2);
        generateJWT();
    } catch (e) {
        alert('Payload JSON is invalid — fix it first, then click the button.');
    }
}

function copyToken() {
    var ta = $j2('jwt-output');
    if (!ta.value) return;
    ta.select();
    var done = function () {
        var btn = document.querySelector('[onclick="copyToken()"]');
        var old = btn.textContent;
        btn.textContent = '\u2705 Copied';
        setTimeout(function () { btn.textContent = old; }, 1500);
    };
    if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(ta.value).then(done, function () { document.execCommand('copy'); done(); });
    } else {
        document.execCommand('copy');
        done();
    }
}

function applyHeaderPreset() {
    var val = $j2('preset-headers').value;
    if (!val) return;
    var presets = {
        'alg-typ': { alg: 'HS256', typ: 'JWT' },
        'none': { alg: 'none', typ: 'JWT' },
        'rs256': { alg: 'RS256', typ: 'JWT' }
    };
    if (presets[val]) $j2('jwt-header').value = JSON.stringify(presets[val], null, 2);
    $j2('preset-headers').value = '';
    generateJWT();
}

function applyPayloadPreset() {
    var val = $j2('preset-payloads').value;
    if (!val) return;
    var now = Math.floor(Date.now() / 1000);
    var presets = {
        'login': { sub: 'user123', name: 'User', role: 'admin', iat: now, exp: now + 3600 },
        'apikey': { key: 'ak_' + Math.random().toString(36).substring(2, 14), scope: 'read write', iat: now },
        'reset': { email: 'user@example.com', reset: true, iat: now, exp: now + 1800 }
    };
    if (presets[val]) $j2('jwt-payload').value = JSON.stringify(presets[val], null, 2);
    $j2('preset-payloads').value = '';
    generateJWT();
}

function decodeJWT() {
    var raw = $j2('decode-in').value.trim();
    var hEl = $j2('decode-header');
    var pEl = $j2('decode-payload');
    var sigEl = $j2('decode-sig');
    var timeEl = $j2('decode-time');
    hEl.value = '';
    pEl.value = '';
    sigEl.innerHTML = '';
    timeEl.innerHTML = '';
    if (!raw) return;

    var m = raw.match(/Bearer\s+(\S+)/i) || raw.match(/eyJ[A-Za-z0-9_-]{6,}\.[A-Za-z0-9_-]{6,}\.[A-Za-z0-9_-]{6,}/);
    if (m) raw = typeof m[1] !== 'undefined' ? m[1] : m[0];

    var parts = raw.split('.');
    if (parts.length < 2 || parts.length > 4) {
        hEl.value = 'Not a valid JWT — expected dot-separated parts.';
        return;
    }

    try {
        var header = JSON.parse(b64urlDecode(parts[0]));
        var payload = JSON.parse(b64urlDecode(parts[1]));
        hEl.value = JSON.stringify(header, null, 2);
        pEl.value = JSON.stringify(payload, null, 2);

        if (parts.length === 2) {
            sigEl.innerHTML = '<span class="badge text-bg-warning text-dark">UNSIGNED</span> <span class="text-secondary small">(no signature part)</span>';
        } else {
            var sigBytes = b64urlDecodeBytes(parts[parts.length - 1]);
            var hexPreview = [];
            for (var i = 0; i < sigBytes.length && i < 8; i++) hexPreview.push(sigBytes[i].toString(16).padStart(2, '0'));
            sigEl.innerHTML = '<span class="badge text-bg-secondary">Signature present</span> <code class="small text-secondary">' + esc(parts[parts.length - 1].substring(0, 40)) + (parts[parts.length - 1].length > 40 ? '...' : '') + '</code>';
        }

        var timeRows = '';
        ['exp', 'iat', 'nbf'].forEach(function (claim) {
            if (typeof payload[claim] === 'number' && payload[claim] > 1000000000) {
                var d = new Date(payload[claim] * 1000);
                var badge = '';
                if (claim === 'exp') {
                    var diff = payload[claim] - Math.floor(Date.now() / 1000);
                    if (diff <= 0) badge = '<span class="badge text-bg-danger ms-2">EXPIRED</span>';
                    else if (diff < 300) badge = '<span class="badge text-bg-warning text-dark ms-2">Expiring soon</span>';
                    else badge = '<span class="badge text-bg-success ms-2">Valid</span>';
                }
                timeRows += '<tr><td class="text-secondary">' + esc(claim) + '</td><td>' + esc(d.toISOString()) + '</td><td>' + badge + '</td></tr>';
            }
        });
        if (timeRows) {
            timeEl.innerHTML = '<table class="table table-sm table-dark align-middle mb-0"><thead><tr><th class="text-secondary">Claim</th><th class="text-secondary">Value</th><th class="text-secondary">Status</th></tr></thead><tbody>' + timeRows + '</tbody></table>';
        }

        var alg = header.alg || 'none';
        if ((alg === 'none' || alg === 'None' || alg === 'NONE') && parts.length >= 3) {
            sigEl.innerHTML += '<div class="mt-2"><span class="badge text-bg-danger">SECURITY WARNING</span> <span class="text-secondary small">alg:none with a signature — token integrity is NOT secured!</span></div>';
        }
    } catch (e) {
        hEl.value = 'Decode error: ' + e.message;
    }
}

function verifyJWT() {
    var raw = $j2('decode-in').value.trim();
    var sigEl = $j2('decode-sig');
    var secret = $j2('decode-secret').value;

    if (!secret) {
        sigEl.innerHTML = '<span class="badge text-bg-secondary">Enter a secret to verify</span>';
        return;
    }

    var m = raw.match(/Bearer\s+(\S+)/i) || raw.match(/eyJ[A-Za-z0-9_-]{6,}\.[A-Za-z0-9_-]{6,}\.[A-Za-z0-9_-]{6,}/);
    if (m) raw = typeof m[1] !== 'undefined' ? m[1] : m[0];

    var parts = raw.split('.');
    if (parts.length < 3) {
        sigEl.innerHTML = '<span class="badge text-bg-danger">Cannot verify — need 3 parts (header.payload.signature)</span>';
        return;
    }

    var header;
    try { header = JSON.parse(b64urlDecode(parts[0])); }
    catch (e) { sigEl.innerHTML = '<span class="badge text-bg-danger">Invalid header</span>'; return; }

    var alg = header.alg;
    if (!/^HS(256|384|512)$/.test(alg)) {
        sigEl.innerHTML = '<span class="badge text-bg-secondary">' + esc(alg) + ' is not HMAC — cannot verify in-browser</span>';
        return;
    }

    var signingInput = parts[0] + '.' + parts[1];
    var algosToTry = [alg];
    if ($j2('decode-in').hasAttribute('data-try-all')) {
        algosToTry = ['HS256', 'HS384', 'HS512'];
    }

    Promise.all(algosToTry.map(function (a) {
        return hmacSign(a, secret, signingInput).then(function (computed) {
            return { alg: a, match: computed === parts[parts.length - 1], computed: computed };
        });
    })).then(function (results) {
        var matched = results.find(function (r) { return r.match; });
        if (matched) {
            sigEl.innerHTML = '<span class="badge text-bg-success">Signature VALID (' + matched.alg + ')</span> <span class="text-secondary small">— this secret signs this token.</span>';
        } else {
            var tried = results.map(function (r) { return r.alg; }).join(', ');
            sigEl.innerHTML = '<span class="badge text-bg-danger">NO MATCH</span> <span class="text-secondary small">— tried ' + esc(tried) + '. Wrong secret or algorithm.</span>' +
                '<div class="text-secondary small mt-1">Computed (' + esc(results[0].alg) + '): <code>' + esc(results[0].computed) + '</code></div>';
        }
    });
}

generateJWT();
</script>
<?php page_footer(); ?>
