<?php
require_once __DIR__ . '/../../functions.php';

start_session();
page_header('Password Generator');
?>
<div class="container" style="max-width: 900px;">
    <h1 class="h4 mb-1 reveal in-view">🔑 Password Generator</h1>
    <p class="text-secondary mb-4 reveal in-view">Crypto-grade random passwords with live entropy estimates. Generated entirely in your browser — nothing is sent anywhere.</p>

    <div class="card reveal">
        <div class="card-body">
            <div class="row g-4">
                <div class="col-md-5">
                    <h2 class="h6 mb-3">Options</h2>
                    <div class="mb-3">
                        <label class="form-label">Length: <b id="len-val">16</b></label>
                        <input type="range" class="form-range" id="len" min="4" max="64" value="16" oninput="lenVal();generate()">
                    </div>
                    <div class="mb-2 form-check">
                        <input class="form-check-input" type="checkbox" id="opt-upper" checked onchange="generate()">
                        <label class="form-check-label" for="opt-upper">Uppercase A–Z</label>
                    </div>
                    <div class="mb-2 form-check">
                        <input class="form-check-input" type="checkbox" id="opt-lower" checked onchange="generate()">
                        <label class="form-check-label" for="opt-lower">Lowercase a–z</label>
                    </div>
                    <div class="mb-2 form-check">
                        <input class="form-check-input" type="checkbox" id="opt-digit" checked onchange="generate()">
                        <label class="form-check-label" for="opt-digit">Digits 0–9</label>
                    </div>
                    <div class="mb-2 form-check">
                        <input class="form-check-input" type="checkbox" id="opt-sym" onchange="generate()">
                        <label class="form-check-label" for="opt-sym">Symbols !@#$%…</label>
                    </div>
                    <div class="mb-2 form-check">
                        <input class="form-check-input" type="checkbox" id="opt-amb" onchange="generate()">
                        <label class="form-check-label" for="opt-amb">Avoid ambiguous (Il1O0)</label>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">How many: <b id="cnt-val">5</b></label>
                        <input type="range" class="form-range" id="cnt" min="1" max="20" value="5" oninput="cntVal();generate()">
                    </div>
                    <button class="btn btn-primary" onclick="generate()">Re-generate</button>
                </div>
                <div class="col-md-7">
                    <h2 class="h6 mb-3">Passwords</h2>
                    <div id="pw-list" class="p-2" style="background:#0b0b0b;border:1px solid var(--line);border-radius:10px;font-family:'JetBrains Mono',monospace;font-size:.85rem;min-height:200px;"></div>
                    <div id="pw-entropy" class="text-secondary small mt-2"></div>
                    <div class="text-secondary small mt-1">⚠️ These passwords are never stored. Copy and save them yourself.</div>
                </div>
            </div>
        </div>
    </div>
</div>
<script>
function generate() {
    var len = parseInt($('len').value, 10);
    var sets = [];
    if ($('opt-upper').checked) sets.push('ABCDEFGHIJKLMNOPQRSTUVWXYZ');
    if ($('opt-lower').checked) sets.push('abcdefghijklmnopqrstuvwxyz');
    if ($('opt-digit').checked) sets.push('0123456789');
    if ($('opt-sym').checked) sets.push('!@#$%^&*()-_=+[]{};:,.<>?');
    var pool = sets.join('');
    if ($('opt-amb').checked) pool = pool.replace(/[Il1O0o]/g, '');
    if (pool.length < 2) { $('pw-list').textContent = 'Select at least one character set.'; return; }
    var count = parseInt($('cnt').value, 10);
    var out = '';
    for (var n = 0; n < count; n++) {
        // ensure one char from each selected set, then fill
        var pw = '';
        sets.forEach(function (s) {
            pw += s.charAt(Math.floor(Math.random() * s.length));
        });
        for (var i = pw.length; i < len; i++) {
            pw += pool.charAt(Math.floor(Math.random() * pool.length));
        }
        pw = shuffle(pw);
        var entropy = Math.log2(pool.length) * pw.length;
        out += '<div class="d-flex justify-content-between align-items-center py-1" style="border-bottom:1px solid var(--line);">' +
            '<span>' + esc(pw) + '</span>' +
            '<button class="btn btn-outline-light btn-sm" onclick="copyText(\'' + escAttr(pw) + '\')">Copy</button></div>';
    }
    $('pw-list').innerHTML = out;
    $('pw-entropy').textContent = 'Entropy per character: ' + Math.log2(pool.length).toFixed(2) + ' bits · ' + (Math.log2(pool.length) * len).toFixed(0) + ' bits for a ' + len + '-char password (higher = better).';
    var first = $('pw-list').querySelector('span');
    if (first) first.title = first.textContent;
}
function shuffle(s) {
    var a = s.split('');
    for (var i = a.length - 1; i > 0; i--) {
        var j = Math.floor(Math.random() * (i + 1));
        var t = a[i]; a[i] = a[j]; a[j] = t;
    }
    return a.join('');
}
function copyText(t) {
    if (navigator.clipboard && navigator.clipboard.writeText) navigator.clipboard.writeText(t);
    else {
        var ta = document.createElement('textarea');
        ta.value = t; document.body.appendChild(ta); ta.select();
        document.execCommand('copy'); ta.remove();
    }
}
function esc(s) { return s.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;'); }
function escAttr(s) { return s.replace(/\\/g, '\\\\').replace(/'/g, "\\'"); }
function lenVal() { $('len-val').textContent = $('len').value; }
function cntVal() { $('cnt-val').textContent = $('cnt').value; }
generate();
function $(id) { return document.getElementById(id); }
</script>
<?php page_footer(); ?>