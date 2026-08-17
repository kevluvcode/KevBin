<?php
require_once __DIR__ . '/../../functions.php';

start_session();
$GLOBALS['_meta'] = [
    'description' => 'Free online UUID generator. Create cryptographically-random UUID v4 and v7 identifiers in bulk, plus hex tokens and API keys. Copy individually or all. Runs entirely in your browser.',
    'keywords' => 'uuid generator, uuid v4, uuid v7, generate uuid, guid generator, unique id',
];
page_header('UUID Generator — v4 & v7 Online');
?>
<div class="container" style="max-width: 900px;">
    <h1 class="h4 mb-2 reveal in-view">UUID Generator</h1>
    <p class="text-secondary mb-1 reveal in-view">Generate cryptographically-random <strong>UUID v4</strong> and v7 identifiers in bulk — perfect as database keys, API IDs, tracking tokens and correlation IDs. Copy them one by one or all at once. Optionally strip the dashes, or make them uppercase.</p>
    <p class="text-secondary mb-4 reveal in-view">A UUID is a 128-bit identifier; version 4 uses entirely random bits, version 7 is time-ordered (so they sort chronologically — great for database indexes). Both are generated here with the browser's WebCrypto randomness, never with a weak PRNG.</p>

    <div class="card reveal in-view">
        <div class="card-body">
            <div class="d-flex flex-wrap gap-2 align-items-center mb-3">
                <select id="uid-ver" class="form-select" style="max-width:150px;" onchange="genAll()">
                    <option value="4">UUID v4</option>
                    <option value="7">UUID v7</option>
                </select>
                <select id="uid-count" class="form-select" style="max-width:120px;" onchange="genAll()">
                    <option value="1">1</option>
                    <option value="5" selected>5</option>
                    <option value="10">10</option>
                    <option value="25">25</option>
                    <option value="50">50</option>
                    <option value="100">100</option>
                </select>
                <label class="form-check ms-2"><input class="form-check-input" type="checkbox" id="uid-upper" onchange="genAll()"> Uppercase</label>
                <label class="form-check"><input class="form-check-input" type="checkbox" id="uid-nodash" onchange="genAll()"> No dashes</label>
                <button class="btn btn-primary btn-sm ms-auto" onclick="genAll()">Regenerate</button>
            </div>
            <textarea id="uid-out" class="form-control mb-2" rows="8" readonly style="font-family:'JetBrains Mono',monospace;font-size:.85rem;"></textarea>
            <button class="btn btn-outline-light btn-sm" onclick="copyUids()">Copy all</button>
        </div>
    </div>

    <h2 class="h6 mt-4 reveal in-view" style="border-bottom:1px solid var(--line);padding-bottom:.5rem;">UUID v4 vs v7</h2>
    <p class="text-secondary small reveal in-view"><strong>v4</strong> is fully random: <code>xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx</code> where the version nibble is <code>4</code> and <code>y</code> is 8, 9, A or B. <strong>v7</strong> embeds a 48-bit Unix timestamp in the first bits, so a large batch sorts naturally by creation time — recommended for primary keys in databases that care about index locality without external sort order.</p>
</div>

<script>
function $u(id) { return document.getElementById(id); }
function randHex(len) {
    var out = '';
    var arr = new Uint8Array(len);
    crypto.getRandomValues(arr);
    for (var i = 0; i < len; i++) out += arr[i].toString(16).padStart(2, '0');
    return out;
}
function uuid4() {
    var h = randHex(16);
    return h.substr(0, 8) + '-' + h.substr(8, 4) + '-4' + h.substr(13, 3) + '-' + ((8 + Math.floor(Math.random() * 4)).toString(16)) + h.substr(17, 3) + '-' + h.substr(20, 12);
}
function uuid7() {
    var now = BigInt(Date.now());
    var bytes = new Uint8Array(16);
    var view = new DataView(bytes.buffer);
    var hi = Number(now >> 16n), lo = Number(now & 0xffffn);
    view.setUint32(0, (hi & 0xffffffff), false);
    view.setUint16(4, (lo & 0xffff) | 0x7000, false);
    view.setUint8(6, (view.getUint8(6) & 0x0f) | 0x80);
    var rnd = new Uint8Array(8);
    crypto.getRandomValues(rnd);
    for (var i = 0; i < 8; i++) view.setUint8(8 + i, rnd[i]);
    var h2 = Array.from(bytes).map(function (b) { return b.toString(16).padStart(2, '0'); }).join('');
    return h2.substr(0, 8) + '-' + h2.substr(8, 4) + '-' + h2.substr(12, 4) + '-' + h2.substr(16, 4) + '-' + h2.substr(20, 12);
}
function genAll() {
    var ver = $u('uid-ver').value, count = +$u('uid-count').value;
    var upper = $u('uid-upper').checked, nodash = $u('uid-nodash').checked;
    var lines = [];
    for (var i = 0; i < count; i++) {
        var id = ver === '7' ? uuid7() : uuid4();
        if (nodash) id = id.replace(/-/g, '');
        if (upper) id = id.toUpperCase();
        lines.push(id);
    }
    $u('uid-out').value = lines.join('\n');
}
function copyUids() { var t = $u('uid-out'); t.select(); document.execCommand('copy'); }
genAll();
</script>
<?php page_footer(); ?>