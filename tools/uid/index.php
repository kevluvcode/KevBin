<?php
require_once __DIR__ . '/../../functions.php';

start_session();
page_header('UID Generator');
?>
<div class="container" style="max-width: 960px;">
    <h1 class="h4 mb-1 reveal in-view">UID Generator</h1>
    <p class="text-secondary mb-4 reveal in-view">UUID v1 / v4 / v5 / v7, random tokens, passwords, passphrases and hex keys — generated locally in your browser using crypto-grade randomness.</p>

    <div class="alert alert-secondary reveal in-view">
        <strong>Generating securely & explained:</strong> these tools use the browser's <code>crypto.getRandomValues()</code> — the same source OS-level security uses — so outputs are not predictable. A <strong>UUID v4</strong> is a 128-bit random identifier. <strong>v1</strong> is time-based (timestamp + clock + node). <strong>v5</strong> is a deterministic SHA-1 hash of a name + namespace (same inputs always produce the same UUID). <strong>v7</strong> is time-ordered random — like v4 but sortable by creation time. A <strong>token</strong> is just a long random string for API/auth secrets.
    </div>

    <div class="row g-4">
        <div class="col-md-7 reveal in-view"><div class="card h-100"><div class="card-body">
            <label class="form-label">UUID generator</label>
            <div class="row g-2 mb-2">
                <div class="col-6 col-md-4">
                    <select id="uuid-ver" class="form-select" onchange="uuidOpts()">
                        <option value="v4">v4 — random</option>
                        <option value="v1">v1 — timestamp</option>
                        <option value="v7">v7 — time-ordered</option>
                        <option value="v5">v5 — name (SHA-1)</option>
                    </select>
                </div>
                <div class="col-6 col-md-3">
                    <input id="uuid-count" type="number" class="form-control" value="1" min="1" max="100" title="How many to generate">
                </div>
                <div class="col-12 col-md-5 d-flex align-items-center gap-3">
                    <label class="form-check-label small mb-0"><input type="checkbox" class="form-check-input me-1" id="uuid-up">UPPERCASE</label>
                    <label class="form-check-label small mb-0"><input type="checkbox" class="form-check-input me-1" id="uuid-nodash">No dashes</label>
                </div>
            </div>
            <div id="uuid-v5-opts" style="display:none;" class="mb-2">
                <div class="row g-2">
                    <div class="col-md-6">
                        <input id="uuid-ns" class="form-control" value="6ba7b810-9dad-11d1-80b4-00c04fd430c8" title="Namespace UUID (DNS default)">
                    </div>
                    <div class="col-md-6">
                        <input id="uuid-name" class="form-control" placeholder="Name / value to hash">
                    </div>
                </div>
                <div class="form-text">Namespace + name are hashed with SHA-1 — same pair always yields the same UUID.</div>
            </div>
            <div class="d-flex gap-2 flex-wrap mb-2">
                <button class="btn btn-primary" onclick="uidBatch()">Generate</button>
                <button class="btn btn-outline-light" onclick="copyIds()">Copy</button>
                <button class="btn btn-outline-light" onclick="exportUid('txt')">Export .txt</button>
                <button class="btn btn-outline-light" onclick="exportUid('json')">Export .json</button>
            </div>
            <textarea id="uuid-list" class="form-control" rows="6" readonly style="font-family:'JetBrains Mono',monospace;font-size:.8rem;"></textarea>
        </div></div></div>

        <div class="col-md-5 reveal in-view"><div class="card h-100"><div class="card-body">
            <label class="form-label">Token (hex)</label>
            <div class="input-group mb-2">
                <input id="token-out" class="form-control" readonly style="font-family:'JetBrains Mono',monospace;font-size:.85rem;">
                <button class="btn btn-primary" onclick="genToken()">Generate</button>
                <button class="btn btn-outline-light" onclick="copyId('token-out')">Copy</button>
                <button class="btn btn-outline-light" onclick="exportField('token-out','token.txt')">⇩</button>
            </div>
            <label class="form-label mt-2">Secret key (base64)</label>
            <div class="input-group mb-2">
                <input id="key-out" class="form-control" readonly style="font-family:'JetBrains Mono',monospace;font-size:.85rem;">
                <button class="btn btn-primary" onclick="genKey()">Generate</button>
                <button class="btn btn-outline-light" onclick="copyId('key-out')">Copy</button>
                <button class="btn btn-outline-light" onclick="exportField('key-out','key.txt')">⇩</button>
            </div>
            <label class="form-label mt-2">Password</label>
            <div class="input-group mb-2">
                <input id="pw-out" class="form-control" readonly style="font-family:'JetBrains Mono',monospace;font-size:.85rem;">
                <button class="btn btn-primary" onclick="genPw()">Generate</button>
                <button class="btn btn-outline-light" onclick="copyId('pw-out')">Copy</button>
                <button class="btn btn-outline-light" onclick="exportField('pw-out','password.txt')">⇩</button>
            </div>
            <label class="form-label mt-2">Memorable passphrase</label>
            <div class="input-group mb-1">
                <input id="pp-out" class="form-control" readonly style="font-family:'JetBrains Mono',monospace;font-size:.85rem;">
                <button class="btn btn-primary" onclick="genPass()">Generate</button>
                <button class="btn btn-outline-light" onclick="copyId('pp-out')">Copy</button>
                <button class="btn btn-outline-light" onclick="exportField('pp-out','passphrase.txt')">⇩</button>
            </div>
            <div class="form-text">words + numbers, easier to remember, still strong. ⇩ downloads the value.</div>
        </div></div></div>
    </div>
</div>

<script>
function randBytes(n) {
    var a = new Uint8Array(n);
    crypto.getRandomValues(a);
    return a;
}
function hexBytes(a) {
    return Array.from(a, function (b) { return ('0' + b.toString(16)).slice(-2); }).join('');
}
function uuidV1() {
    var t = BigInt(Date.now()) * 10000n + 0x01b21dd213814000n;
    var b = new Uint8Array(16);
    for (var i = 7; i >= 0; i--) { b[i] = Number(t & 0xffn); t >>= 8n; }
    var r = randBytes(8);
    b[8] = (r[0] & 0x3f) | 0x80;
    b[9] = r[1];
    b[10] = r[2];
    b[11] = r[3];
    for (var j = 12; j < 16; j++) b[j] = r[j - 4];
    return b;
}
function uuidV4() {
    var b = randBytes(16);
    b[6] = (b[6] & 0x0f) | 0x40;
    b[8] = (b[8] & 0x3f) | 0x80;
    return b;
}
function uuidV7() {
    var b = randBytes(16);
    var t = BigInt(Date.now());
    b[0] = Number((t >> 40n) & 0xffn);
    b[1] = Number((t >> 32n) & 0xffn);
    b[2] = Number((t >> 24n) & 0xffn);
    b[3] = Number((t >> 16n) & 0xffn);
    b[4] = Number((t >> 8n) & 0xffn);
    b[5] = Number(t & 0xffn);
    b[6] = (b[6] & 0x0f) | 0x70;
    b[8] = (b[8] & 0x3f) | 0x80;
    return b;
}
function parseUuidHex(s) {
    var h = s.replace(/-/g, '');
    var b = new Uint8Array(16);
    for (var i = 0; i < 16; i++) b[i] = parseInt(h.substr(i * 2, 2), 16);
    return b;
}
function uuidV5(name, nsHex) {
    var ns = parseUuidHex(nsHex || '6ba7b810-9dad-11d1-80b4-00c04fd430c8');
    var data = new Uint8Array(ns.length + name.length);
    data.set(ns, 0);
    for (var i = 0; i < name.length; i++) data[ns.length + i] = name.charCodeAt(i);
    return crypto.subtle.digest('SHA-1', data).then(function (buf) {
        var b = new Uint8Array(buf.slice(0, 16));
        b[6] = (b[6] & 0x0f) | 0x50;
        b[8] = (b[8] & 0x3f) | 0x80;
        return b;
    });
}
function formatUuid(bytes, upper, noDash) {
    var s = hexBytes(bytes);
    var u = s.slice(0, 8) + '-' + s.slice(8, 12) + '-' + s.slice(12, 16) + '-' + s.slice(16, 20) + '-' + s.slice(20, 32);
    if (noDash) u = u.replace(/-/g, '');
    if (upper) u = u.toUpperCase();
    return u;
}
function uidBatch() {
    var box = document.getElementById('uuid-list');
    var ver = document.getElementById('uuid-ver').value;
    var n = Math.max(1, Math.min(100, parseInt(document.getElementById('uuid-count').value, 10) || 1));
    var upper = document.getElementById('uuid-up').checked;
    var noDash = document.getElementById('uuid-nodash').checked;
    var out = [];
    function flush() { box.value = out.join('\n'); }
    function one() {
        switch (ver) {
            case 'v1': out.push(formatUuid(uuidV1(), upper, noDash)); break;
            case 'v7': out.push(formatUuid(uuidV7(), upper, noDash)); break;
            case 'v4': default: out.push(formatUuid(uuidV4(), upper, noDash)); break;
        }
        return true;
    }
    if (ver === 'v5') {
        var name = document.getElementById('uuid-name').value || 'kevbin';
        var ns = document.getElementById('uuid-ns').value;
        if (!/^[0-9a-fA-F-]{32,36}$/.test(ns)) { box.value = 'Namespace must be a UUID (e.g. 6ba7b810-9dad-11d1-80b4-00c04fd430c8).'; return; }
        uuidV5(name, ns).then(function (b) {
            for (var i = 0; i < n; i++) out.push(formatUuid(b, upper, noDash));
            flush();
        });
        return;
    }
    for (var i = 0; i < n; i++) one();
    flush();
}
function uuidOpts() {
    document.getElementById('uuid-v5-opts').style.display = document.getElementById('uuid-ver').value === 'v5' ? '' : 'none';
}
function exportUid(kind) {
    var val = document.getElementById('uuid-list').value;
    if (!val) return;
    var content, name;
    if (kind === 'json') {
        content = JSON.stringify({ version: document.getElementById('uuid-ver').value, count: val.split('\n').length, uuids: val.split('\n') }, null, 2);
        name = 'uuids.json';
    } else {
        content = val;
        name = 'uuids.txt';
    }
    downloadFile(name, content);
}
function exportField(id, name) {
    var el = document.getElementById(id);
    if (!el.value) return;
    downloadFile(name, el.value);
}
function downloadFile(name, content) {
    var blob = new Blob([content], { type: 'text/plain;charset=utf-8' });
    var a = document.createElement('a');
    a.href = URL.createObjectURL(blob);
    a.download = name;
    document.body.appendChild(a);
    a.click();
    setTimeout(function () { URL.revokeObjectURL(a.href); a.remove(); }, 500);
}
function randHex(bytes) { return hexBytes(randBytes(bytes)); }
function genToken() { document.getElementById('token-out').value = randHex(32); }
function genKey() {
    var a = randBytes(32);
    document.getElementById('key-out').value = btoa(String.fromCharCode.apply(null, a));
}
function genPw() {
    var chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghjkmnpqrstuvwxyz23456789!@#$%^&*-_+=?';
    var a = randBytes(24);
    var out = '';
    for (var i = 0; i < a.length; i++) out += chars[a[i] % chars.length];
    document.getElementById('pw-out').value = out;
}
var PASS_WORDS = ['apple','river','mountain','forest','ocean','falcon','thunder','crystal','ember','raven','juniper','willow','comet','harbor','lantern','meadow','nebula','onyx','phoenix','quartz','rook','sable','tundra','violet','winter','zephyr','anchor','beacon','canyon','drift'];
function genPass() {
    var a = randBytes(4);
    var words = [];
    for (var i = 0; i < 4; i++) {
        var w = PASS_WORDS[a[i] % PASS_WORDS.length];
        words.push(w.charAt(0).toUpperCase() + w.slice(1));
    }
    var num = randBytes(2);
    document.getElementById('pp-out').value = words.join('-') + (num[1] % 97 + 10);
}
function copyId(id) {
    var el = document.getElementById(id);
    el.value = el.value; // prefill for empty
    el.select();
    if (navigator.clipboard) navigator.clipboard.writeText(el.value); else document.execCommand('copy');
}
function copyIds() {
    var box = document.getElementById('uuid-list');
    if (!box.value) uidBatch();
    box.select();
    if (navigator.clipboard) navigator.clipboard.writeText(box.value); else document.execCommand('copy');
}
uuidOpts(); uidBatch(); genToken(); genKey(); genPw(); genPass();
</script>
<?php page_footer(); ?>