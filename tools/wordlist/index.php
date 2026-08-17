<?php
require_once __DIR__ . '/../../functions.php';

start_session();
page_header('Wordlist Generator');
?>
<div class="container" style="max-width: 1000px;">
    <h1 class="h4 mb-1 reveal in-view">ðŸ“š Wordlist Generator</h1>
    <p class="text-secondary mb-3 reveal in-view">Build a <strong>password / wordlist candidate set</strong> the way real cracking tools do: base words are mutated with 1337-speak, case changes, keyboard walks, date patterns, separators and multi-word combos. Everything runs locally in your browser. See how your own passwords would fare against a dictionary attack â€” then switch to a passphrase. <strong>For education and testing your own accounts only.</strong></p>

    <div class="card reveal in-view"><div class="card-body">
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label">Base word(s) â€” one per line</label>
                <textarea id="wl-base" class="form-control" rows="4" style="font-family:'JetBrains Mono',monospace;font-size:.85rem;">password
hunter2

</textarea>
                <label class="form-label mt-2 d-flex justify-content-between"><span>Extra word pool (for combos) <span class="text-secondary fw-normal">â€” &lt; fewer lines = reuse base words</span></span></label>
                <textarea id="wl-pool" class="form-control" rows="3" style="font-family:'JetBrains Mono',monospace;font-size:.85rem;" placeholder="cat&#10;dog&#10;star"></textarea>
            </div>
            <div class="col-md-6">
                <label class="form-label">Transformations</label>
                <div class="d-grid" style="grid-template-columns:1fr 1fr;gap:.35rem 1rem;">
                    <label class="form-check-label small"><input type="checkbox" id="wl-leet" class="form-check-input me-1" checked> 1337 speak</label>
                    <label class="form-check-label small"><input type="checkbox" id="wl-case" class="form-check-input me-1" checked> Case variations</label>
                    <label class="form-check-label small"><input type="checkbox" id="wl-caps" class="form-check-input me-1" checked> Capitalize first</label>
                    <label class="form-check-label small"><input type="checkbox" id="wl-reverse" class="form-check-input me-1"> Reverse</label>
                    <label class="form-check-label small"><input type="checkbox" id="wl-double" class="form-check-input me-1"> Double letters (aa, abâ†’aabb)</label>
                    <label class="form-check-label small"><input type="checkbox" id="wl-truncate" class="form-check-input me-1"> Truncate (drop 1â€“3 last)</label>
                    <label class="form-check-label small"><input type="checkbox" id="wl-walk" class="form-check-input me-1"> Keyboard walks</label>
                    <label class="form-check-label small"><input type="checkbox" id="wl-years" class="form-check-input me-1" checked> Year & date suffixes</label>
                </div>
                <label class="form-label mt-3">Append digits (0â€“N)</label>
                <input id="wl-digits" type="number" min="0" max="9999" class="form-control wl-num" value="100">
                <label class="form-label mt-2">Symbol suffixes (space = each)</label>
                <input id="wl-symbols" class="form-control" value="!@#$%^*">
                <label class="form-label mt-2">Separators for combos</label>
                <input id="wl-sep" class="form-control" value=" -_.@">
                <label class="form-label mt-2 d-flex justify-content-between"><span>Combos</span></label>
                <div class="d-flex gap-2">
                    <input id="wl-combo" type="number" min="0" max="3" class="form-control wl-num" value="2" title="How many words to join">
                    <span class="text-secondary small pt-2">words joined</span>
                </div>
            </div>
        </div>

        <div class="mt-3 d-flex flex-wrap gap-2 align-items-center">
            <button class="btn btn-primary btn-sm" onclick="genWordlist()">Generate</button>
            <button class="btn btn-outline-light btn-sm" onclick="downloadWordlist()">Download .txt</button>
            <span id="wl-count" class="text-secondary small"></span>
            <label class="form-check-label small ms-auto"><input type="checkbox" id="wl-unlimited" class="form-check-input me-1"> no cap (may take a while)</label>
        </div>

        <textarea id="wl-out" class="form-control mt-3" rows="12" readonly spellcheck="false" style="font-family:'JetBrains Mono',monospace;font-size:.8rem;"></textarea>
    </div></div>

    <p class="text-secondary small mt-3 mb-0">Techniques mirror hashcat/lstc-style rule engines: leet, years (19xx/20xx and MM-DD variants), keyboard adjacency walks, doubling/truncation, and multi-word joins with separators. If any variant of your real password shows up â€” change it. Recommended: 4 random dictionary words as a passphrase, kept in a password manager.</p>
</div>

<script>
var LEET = { a: '4', e: '3', i: '1', o: '0', s: '5', t: '7', g: '9', l: '1', b: '8', z: '2' };
var KEYS = { q: 'was', w: 'qeasd', e: 'wrsdf', r: 'etdfg', t: 'ryfgh', y: 'tughj', u: 'yihjk', i: 'uojkl', o: 'ipkl', p: 'ol', a: 'qwsxz', s: 'awedxz', d: 'serfcv', f: 'drtgvb', g: 'ftyhnb', h: 'gyujn', j: 'huikm', k: 'jiol', l: 'kop', z: 'asx', x: 'zsdc', c: 'xdfv', v: 'cfgb', b: 'vghn', n: 'bhjm', m: 'njk' };

function leetify(w) { return w.replace(/[aegilbozs]/gi, function (c) { return LEET[c.toLowerCase()] || c; }); }
function capFirst(w) { return w.charAt(0).toUpperCase() + w.slice(1); }
function alTernating(w) { return w.split('').map(function (c, i) { return i % 2 ? c : c.toUpperCase(); }).join(''); }

function keyWalk(len) {
    var pool = Object.keys(KEYS);
    var s = pool[(Math.random() * pool.length) | 0];
    var out = s;
    for (var i = 1; i < len; i++) {
        var nxt = KEYS[s];
        s = nxt[(Math.random() * nxt.length) | 0];
        out += s;
    }
    return out;
}

function genWordlist() {
    var bases = document.getElementById('wl-base').value.split('\n').map(function (s) { return s.trim(); }).filter(Boolean);
    if (!bases.length) { document.getElementById('wl-out').value = ''; document.getElementById('wl-count').textContent = ''; return; }
    var pool = document.getElementById('wl-pool').value.split('\n').map(function (s) { return s.trim(); }).filter(Boolean);
    if (!pool.length) pool = bases;

    var leet = document.getElementById('wl-leet').checked;
    var caze = document.getElementById('wl-case').checked;
    var caps = document.getElementById('wl-caps').checked;
    var reverse = document.getElementById('wl-reverse').checked;
    var dbl = document.getElementById('wl-double').checked;
    var trunc = document.getElementById('wl-truncate').checked;
    var walk = document.getElementById('wl-walk').checked;
    var years = document.getElementById('wl-years').checked;
    var digits = Math.min(9999, Math.max(0, parseInt(document.getElementById('wl-digits').value, 10) || 0));
    var symbols = document.getElementById('wl-symbols').value.split(/\s+/).filter(Boolean);
    var seps = document.getElementById('wl-sep').value.length ? document.getElementById('wl-sep').value.split('') : [' '];
    var comboN = Math.min(3, Math.max(0, parseInt(document.getElementById('wl-combo').value, 10) || 0));
    var unlimited = document.getElementById('wl-unlimited').checked;
    var CAP = unlimited ? 1000000 : 200000;

    var seen = {};
    var out = [];
    function push(s) { if (s && !seen[s] && out.length < CAP) { seen[s] = 1; out.push(s); } }

    // base variants for one input word
    function variants(b) {
        var v = [];
        v.push(b);
        if (leet) v.push(leetify(b));
        if (caze) v.push(b.toLowerCase(), b.toUpperCase(), capFirst(b), alTernating(b));
        if (caps) v.push(capFirst(b), b.toUpperCase() + '!', capFirst(b) + '1');
        if (reverse) v.push(b.split('').reverse().join(''));
        if (dbl) v.push(b.split('').map(function (c) { return c + c; }).join(''));
        if (trunc) {
            if (b.length > 2) v.push(b.slice(0, -1), b.slice(0, -2), b.slice(0, -3));
        }
        return v.filter(function (x) { return x; });
    }

    var yearsSet = ['19', '20', '2010', '2011', '2012', '2013', '2014', '2015', '2016', '2017', '2018', '2019', '2020', '2021', '2022', '2023', '2024', '2025', '2026', '123', '321', '007', '69', '420'];
    var dates = ['0101', '1231', '1313', '010', '022', '112', '211', '911', '2001', '1945'];
    function appendSuffix(v) {
        if (digits > 0) for (var d = 0; d <= digits; d++) push(v + d);
        if (years) { yearsSet.forEach(function (y) { push(v + y); }); dates.forEach(function (y) { push(v + y); }); }
        symbols.forEach(function (s) {
            push(v + s);
            if (digits > 0) for (var d = 0; d <= Math.min(digits, 20); d++) push(v + s + d);
        });
    }

    bases.forEach(function (b) {
        var v = variants(b);
        v.forEach(push);
        v.forEach(appendSuffix);
        // letters + digits swapped (p@ssw0rd123 style)
        if (leet) v.forEach(function (x) { var l = leetify(x); push(l + '1'); push(l + '123'); });
    });

    // keyboard walks
    if (walk) { for (var i = 0; i < 400; i++) push(keyWalk(4 + Math.floor(Math.random() * 5))); }

    // multi-word combos from pool
    if (comboN >= 2 && pool.length >= 2) {
        var pl = pool.slice(0, 60); // safety bound for pool size
        for (var a = 0; a < pl.length && out.length < CAP; a++) {
            for (var b2 = 0; b2 < pl.length && out.length < CAP; b2++) {
                if (a === b2) continue;
                seps.forEach(function (sep) {
                    var joined = pl[a] + sep + pl[b2];
                    push(joined);
                    if (comboN === 3 && out.length < CAP) {
                        for (var c = 0; c < pl.length; c++) {
                            if (c === a || c === b2) continue;
                            push(pl[a] + sep + pl[b2] + sep + pl[c]);
                            if (out.length >= CAP) return;
                        }
                    }
                });
            }
        }
    }

    // deterministic order not required
    var finish = out;
    document.getElementById('wl-out').value = finish.join('\n') + (finish.length ? '\n' : '');
    document.getElementById('wl-count').textContent = finish.length.toLocaleString() + ' candidates' + (finish.length >= CAP ? ' (cap hit)' : '');
}
function downloadWordlist() {
    var txt = document.getElementById('wl-out').value;
    if (!txt) return;
    var blob = new Blob([txt], { type: 'text/plain' });
    var a = document.createElement('a');
    a.href = URL.createObjectURL(blob);
    a.download = 'kevbin_wordlist.txt';
    document.body.appendChild(a); a.click();
    setTimeout(function () { URL.revokeObjectURL(a.href); a.remove(); }, 400);
}
genWordlist();
</script>
<?php page_footer(); ?>