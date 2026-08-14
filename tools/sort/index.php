<?php
require_once __DIR__ . '/../../functions.php';

start_session();
page_header('Alphabetizer');
?>
<div class="container" style="max-width: 900px;">
    <h1 class="h4 mb-1 reveal in-view">🔤 Alphabetizer &amp; Line Toolbox</h1>
    <p class="text-secondary mb-4 reveal in-view">Sort lines forward or backward, shuffle, reverse, dedupe, trim blanks and renumber. Handy for lists, playlists, keyword dumps and checklists. All local in your browser.</p>

    <div class="card mb-4 reveal in-view"><div class="card-body">
        <div class="row g-2 align-items-center mb-2">
            <div class="col-md-3">
                <select id="sort-mode" class="form-select">
                    <option value="alpha">A → Z</option>
                    <option value="rev">Z → A</option>
                    <option value="length">Shortest first</option>
                    <option value="lengthrev">Longest first</option>
                    <option value="shuffle">Shuffle</option>
                    <option value="reverse">Reverse lines</option>
                </select>
            </div>
            <div class="col-md-3">
                <select id="sort-case" class="form-select">
                    <option value="insensitive">Ignore case</option>
                    <option value="sensitive">Case sensitive</option>
                    <option value="natural">Natural (a2 &lt; a10)</option>
                </select>
            </div>
            <div class="col-md-6 d-flex gap-2 flex-wrap">
                <button class="btn btn-primary" onclick="doSort()">Sort</button>
                <button class="btn btn-outline-light" onclick="dedupe()">Dedupe</button>
                <button class="btn btn-outline-light" onclick="trimBlanks()">Trim blanks</button>
                <button class="btn btn-outline-light" onclick="number()">Number lines</button>
                <button class="btn btn-outline-light" onclick="copyOut()">Copy</button>
            </div>
        </div>
        <textarea id="sort-in" class="form-control mb-2" rows="10" style="font-family:'JetBrains Mono',monospace;font-size:.8rem;" placeholder="one item per line">banana
Apple
cherry
2
10</textarea>
        <textarea id="sort-out" class="form-control" rows="10" readonly style="font-family:'JetBrains Mono',monospace;font-size:.8rem;"></textarea>
        <div class="text-secondary small mt-2" id="sort-stat"></div>
    </div></div>

    <div class="alert alert-secondary reveal in-view">
        <small>💡 "Natural" sorting understands numbers inside text, so <code>a2</code> comes before
        <code>a10</code>. The renumber button adds <code>1. </code> prefixes to every line.</small>
    </div>
</div>

<script>
function $i(id) { return document.getElementById(id); }
function lines() { return $i('sort-in').value.split('\n'); }
function setOut(arr) {
    var out = arr.join('\n').replace(/\n{2,}/g, '\n');
    $i('sort-out').value = out;
    $i('sort-stat').textContent = 'in: ' + $i('sort-in').value.split('\n').length + ' lines → out: ' + out.split('\n').length + ' lines';
}
function doSort() {
    var mode = $i('sort-mode').value;
    var cs = $i('sort-case').value;
    var arr = lines();
    if (mode === 'alpha' || mode === 'rev') {
        arraySort(arr, cs);
        if (mode === 'rev') arr.reverse();
    } else if (mode === 'length') {
        arr.sort(function (a, b) { return a.length - b.length; });
    } else if (mode === 'lengthrev') {
        arr.sort(function (a, b) { return b.length - a.length; });
    } else if (mode === 'shuffle') {
        var a = new Uint32Array(arr.length);
        crypto.getRandomValues(a);
        var out = arr.slice();
        for (var i = out.length - 1; i > 0; i--) { var j = a[i] % (i + 1); var t = out[i]; out[i] = out[j]; out[j] = t; }
        out.length = arr.length;
        arr = out;
    } else {
        arr.reverse();
    }
    setOut(arr);
}
function collator(natural) { return new Intl.Collator(undefined, { numeric: natural, sensitivity: 'base' }); }
function arraySort(arr, cs) {
    if (cs === 'sensitive') { arr.sort(); return; }
    var cmp = collator(cs === 'natural');
    arr.sort(cmp.compare);
}
function dedupe() { var seen = {}; var out = []; lines().forEach(function (l) { var k = l.toLowerCase(); if (!seen[k]) { seen[k] = 1; out.push(l); } }); setOut(out); }
function trimBlanks() { setOut(lines().filter(function (l) { return l.trim() !== ''; })); }
function number() { setOut(lines().filter(function (l, i) { return l.trim() !== ''; }).map(function (l, i) { return (i + 1) + '. ' + l; })); }
function copyOut() { var t = $i('sort-out'); t.select(); if (navigator.clipboard) navigator.clipboard.writeText(t.value); else document.execCommand('copy'); }
</script>
<?php page_footer(); ?>