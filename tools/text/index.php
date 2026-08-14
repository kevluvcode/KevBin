<?php
require_once __DIR__ . '/../../functions.php';

start_session();
page_header('Text Tools');
?>
<div class="container" style="max-width: 1100px;">
    <h1 class="h4 mb-1 reveal in-view">Text Tools</h1>
    <p class="text-secondary mb-4 reveal in-view">Everyday text utilities: case conversion, analyzers, lorem ipsum and a diff checker. All runs locally in your browser.</p>

    <div class="row g-4">

        <div class="col-md-6 reveal">
            <div class="card h-100"><div class="card-body">
                <h2 class="h6 mb-2">🅰️ Case Converter</h2>
                <p class="text-secondary small">Convert text between UPPER, lower, Title, Sentence, camelCase, PascalCase, snake_case and kebab-case in one click.</p>
                <textarea id="case-in" class="form-control mb-2" rows="4" style="font-family:'JetBrains Mono',monospace;font-size:.85rem;" placeholder="the quick brown fox"></textarea>
                <div class="d-flex gap-2 flex-wrap align-items-center">
                    <select id="case-mode" class="form-select form-select-sm" style="max-width:200px;">
                        <option value="upper">UPPERCASE</option>
                        <option value="lower">lowercase</option>
                        <option value="title">Title Case</option>
                        <option value="sentence">Sentence case</option>
                        <option value="camel">camelCase</option>
                        <option value="pascal">PascalCase</option>
                        <option value="snake">snake_case</option>
                        <option value="kebab">kebab-case</option>
                        <option value="alt">aLtErNaTiNg</option>
                    </select>
                    <button class="btn btn-primary btn-sm" onclick="runCase()">Convert</button>
                    <button class="btn btn-outline-light btn-sm" onclick="copyId('case-out')">Copy</button>
                </div>
                <textarea id="case-out" class="form-control mt-2" rows="3" readonly style="font-family:'JetBrains Mono',monospace;font-size:.85rem;"></textarea>
            </div></div>
        </div>

        <div class="col-md-6 reveal">
            <div class="card h-100"><div class="card-body">
                <h2 class="h6 mb-2">📊 Text Analyzer</h2>
                <p class="text-secondary small">Characters, words, sentences, lines, paragraphs, reading time and the most-used words.</p>
                <textarea id="ana-in" class="form-control mb-2" rows="6" style="font-family:'JetBrains Mono',monospace;font-size:.85rem;" oninput="analyze()">KevBin text tools run locally in your browser.
No data ever leaves this page.</textarea>
                <div class="row g-2 text-center" id="ana-grid"></div>
            </div></div>
        </div>

        <div class="col-md-6 reveal">
            <div class="card h-100"><div class="card-body">
                <h2 class="h6 mb-2">📝 Lorem Ipsum</h2>
                <p class="text-secondary small">Generate placeholder text for mockups and design.</p>
                <div class="d-flex gap-2 align-items-center mb-2">
                    <label class="form-label small mb-0">Paragraphs</label>
                    <input id="lorem-count" type="number" class="form-control form-control-sm" style="max-width:90px;" value="3" min="1" max="20">
                    <button class="btn btn-primary btn-sm" onclick="genLorem()">Generate</button>
                    <button class="btn btn-outline-light btn-sm" onclick="copyId('lorem-out')">Copy</button>
                </div>
                <textarea id="lorem-out" class="form-control" rows="6" readonly style="font-family:'JetBrains Mono',monospace;font-size:.85rem;"></textarea>
            </div></div>
        </div>

        <div class="col-md-6 reveal">
            <div class="card h-100"><div class="card-body">
                <h2 class="h6 mb-2">🧩 Diff Checker</h2>
                <p class="text-secondary small">Compare two texts line by line. Green = added, red = removed, gray = unchanged.</p>
                <div class="row g-2 mb-2">
                    <div class="col-6"><textarea id="diff-a" class="form-control" rows="5" style="font-family:'JetBrains Mono',monospace;font-size:.8rem;" placeholder="original text">hello world</textarea></div>
                    <div class="col-6"><textarea id="diff-b" class="form-control" rows="5" style="font-family:'JetBrains Mono',monospace;font-size:.8rem;" placeholder="changed text">hello mars</textarea></div>
                </div>
                <button class="btn btn-primary btn-sm" onclick="runDiff()">Compare</button>
                <pre id="diff-out" class="mt-2" style="font-family:'JetBrains Mono',monospace;font-size:.8rem;background:#0b0b0b;border:1px solid var(--line);border-radius:10px;padding:.75rem;white-space:pre-wrap;"></pre>
            </div></div>
        </div>

    </div>
</div>

<script>
function $(id) { return document.getElementById(id); }
function copyId(id) { var t = $(id); t.select(); document.execCommand('copy'); }

function runCase() {
    var v = $('case-in').value;
    var m = $('case-mode').value;
    var out = v;
    if (m === 'upper') out = v.toUpperCase();
    else if (m === 'lower') out = v.toLowerCase();
    else if (m === 'title') out = v.replace(/\b\w/g, function (c) { return c.toUpperCase(); });
    else if (m === 'sentence') out = v.toLowerCase().replace(/(^\s*\w|[.!?]\s+\w)/g, function (c) { return c.toUpperCase(); });
    else if (m === 'camel') out = v.toLowerCase().replace(/(?:^|\s)(\w)/g, function (_, c, off) { return off === 0 ? c.toLowerCase() : c.toUpperCase(); }).replace(/\s+/g, '');
    else if (m === 'pascal') out = v.toLowerCase().replace(/(?:^|\s)(\w)/g, function (_, c) { return c.toUpperCase(); }).replace(/\s+/g, '');
    else if (m === 'snake') out = v.trim().toLowerCase().replace(/[^a-z0-9]+/g, '_').replace(/^_+|_+$/g, '');
    else if (m === 'kebab') out = v.trim().toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '');
    else if (m === 'alt') {
        out = '';
        for (var i = 0; i < v.length; i++) out += i % 2 ? v[i].toUpperCase() : v[i].toLowerCase();
    }
    $('case-out').value = out;
}

function analyze() {
    var v = $('ana-in').value;
    var chars = v.length;
    var charsNoSpace = v.replace(/\s/g, '').length;
    var words = v.trim() ? (v.trim().match(/\S+/g) || []).length : 0;
    var sentences = (v.match(/[.!?]+(\s|$)/g) || []).length;
    if (sentences === 0 && v.trim()) sentences = 1;
    var lines = v ? v.split('\n').length : 0;
    var paragraphs = v ? v.split(/\n\s*\n/).filter(function (p) { return p.trim(); }).length : 0;
    var minutes = Math.max(1, Math.round(words / 200));
    var freq = {};
    (v.toLowerCase().match(/[a-z']+/g) || []).forEach(function (w) { freq[w] = (freq[w] || 0) + 1; });
    var top = Object.keys(freq).sort(function (a, b) { return freq[b] - freq[a]; }).slice(0, 5);
    var stats = [
        { l: 'Characters', v: chars }, { l: 'No spaces', v: charsNoSpace }, { l: 'Words', v: words },
        { l: 'Sentences', v: sentences }, { l: 'Lines', v: lines }, { l: 'Paragraphs', v: paragraphs },
        { l: 'Read time', v: minutes + ' min' }
    ];
    var grid = $('ana-grid');
    grid.innerHTML = '';
    stats.forEach(function (s) {
        grid.innerHTML += '<div class="col-6 col-md-3"><div class="card"><div class="card-body py-2">' +
            '<div class="text-secondary small">' + s.l + '</div><div class="fw-bold">' + s.v + '</div></div></div></div>';
    });
    var topTxt = top.length ? ('Top words: <em>' + top.join(', ') + '</em>') : 'Top words: —';
    grid.innerHTML += '<div class="col-12 text-secondary small mt-2">' + topTxt + '</div>';
}

var LOREM = ['lorem','ipsum','dolor','sit','amet','consectetur','adipiscing','elit','sed','do','eiusmod','tempor','incididunt','ut','labore','et','dolore','magna','aliqua','enim','ad','minim','veniam','quis','nostrud','exercitation','ullamco','laboris','nisi','aliquip','ex','ea','commodo','consequat','duis','aute','irure','in','reprehenderit','voluptate','velit','esse','cillum','fugiat','nulla','pariatur','excepteur','sint','occaecat','cupidatat','proident','culpa','officia','deserunt','mollit','anim','id','est','laborum'];
function genLorem() {
    var n = Math.min(20, Math.max(1, parseInt($('lorem-count').value, 10) || 3));
    var paras = [];
    for (var p = 0; p < n; p++) {
        var words = [];
        var count = 20 + Math.floor(Math.random() * 25);
        for (var w = 0; w < count; w++) words.push(LOREM[Math.floor(Math.random() * LOREM.length)]);
        var para = words.join(' ');
        para = para.charAt(0).toUpperCase() + para.slice(1) + '.';
        paras.push(para);
    }
    $('lorem-out').value = paras.join('\n\n');
}

function runDiff() {
    var a = $('diff-a').value.split('\n');
    var b = $('diff-b').value.split('\n');
    var out = [];
    // simple LCS diff
    var m1 = a.length, m2 = b.length;
    var dp = [];
    for (var i = 0; i <= m1; i++) dp.push(new Array(m2 + 1).fill(0));
    for (var i = m1 - 1; i >= 0; i--) {
        for (var j = m2 - 1; j >= 0; j--) {
            if (a[i] === b[j]) dp[i][j] = dp[i + 1][j + 1] + 1;
            else dp[i][j] = Math.max(dp[i + 1][j], dp[i][j + 1]);
        }
    }
    var i = 0, j = 0;
    while (i < m1 && j < m2) {
        if (a[i] === b[j]) { out.push('<span style="color:#8f8f8f">  ' + esc(a[i]) + '</span>'); i++; j++; }
        else if (dp[i + 1][j] >= dp[i][j + 1]) { out.push('<span style="background:rgba(248,81,73,.15)">- ' + esc(a[i]) + '</span>'); i++; }
        else { out.push('<span style="background:rgba(63,185,80,.15)">+ ' + esc(b[j]) + '</span>'); j++; }
    }
    while (i < m1) { out.push('<span style="background:rgba(248,81,73,.15)">- ' + esc(a[i]) + '</span>'); i++; }
    while (j < m2) { out.push('<span style="background:rgba(63,185,80,.15)">+ ' + esc(b[j]) + '</span>'); j++; }
    function esc(s) { return s.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;'); }
    $('diff-out').innerHTML = out.join('\n');
}

runCase();
analyze();
genLorem();
</script>
<?php page_footer(); ?>