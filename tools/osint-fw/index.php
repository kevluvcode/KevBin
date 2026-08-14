<?php
require_once __DIR__ . '/../../functions.php';

start_session();
$entries = require __DIR__ . '/data.php';

$cats = [];
foreach ($entries as $e) {
    $top = strtok($e[2], '>');
    if ($top !== false) { $top = trim($top); }
    if ($top === '' || $top === false) { $top = 'Other'; }
    $cats[$top] = ($cats[$top] ?? 0) + 1;
}
ksort($cats);

$liveCount = 0;
foreach ($entries as $e) { if ($e[6] === 'live') { $liveCount++; } }

page_header('OSINT Framework Searcher');
?>
<div class="container">
    <h1 class="h4 mb-1 reveal in-view">🔎 OSINT Framework Searcher</h1>
    <p class="text-secondary mb-3 reveal in-view">Every tool and resource from <a href="https://osintframework.com/" target="_blank" rel="noopener">osintframework.com</a> — <?= number_format(count($entries)) ?> entries across <?= count($cats) ?> categories, searchable and filterable. Click any result to open it on its own site.</p>

    <div class="card mb-3 reveal"><div class="card-body">
        <div class="row g-2 mb-2">
            <div class="col-md-6">
                <input class="form-control" id="q" placeholder="Search name, category, description… (e.g. phone, twitter, map, dork)" autofocus>
            </div>
            <div class="col-md-3">
                <select class="form-select" id="cat">
                    <option value="">All categories (<?= number_format(count($entries)) ?>)</option>
                    <?php foreach ($cats as $name => $n): ?>
                        <option value="<?= e($name) ?>"><?= e($name) ?> (<?= number_format($n) ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <select class="form-select" id="price">
                    <option value="">Any pricing</option>
                    <option value="free">Free</option>
                    <option value="freemium">Freemium</option>
                    <option value="paid">Paid</option>
                </select>
            </div>
        </div>
        <div class="d-flex align-items-center gap-3 flex-wrap">
            <span class="text-secondary small">Badges:</span>
            <label class="form-check form-check-inline small mb-0"><input class="form-check-input" type="checkbox" id="bT"><span class="ms-1">T — local install</span></label>
            <label class="form-check form-check-inline small mb-0"><input class="form-check-input" type="checkbox" id="bD"><span class="ms-1">D — Google dork</span></label>
            <label class="form-check form-check-inline small mb-0"><input class="form-check-input" type="checkbox" id="bR"><span class="ms-1">R — registration</span></label>
            <label class="form-check form-check-inline small mb-0"><input class="form-check-input" type="checkbox" id="bM"><span class="ms-1">M — manual URL edit</span></label>
            <label class="form-check form-check-inline small mb-0"><input class="form-check-input" type="checkbox" id="hideDead" checked><span class="ms-1">hide dead/deprecated</span></label>
        </div>
    </div></div>

    <div class="d-flex justify-content-between align-items-center mb-2 reveal">
        <div class="text-secondary small" id="stats"></div>
        <div class="text-secondary small" id="tally"></div>
    </div>
    <div id="results" class="row row-cols-1 row-cols-md-2 row-cols-xl-3 g-3"></div>
</div>
<script>
(function () {
    var DATA = <?= json_encode($entries, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
    var LIVE_TOTAL = <?= (int)$liveCount ?>;
    var q = document.getElementById('q');
    var cat = document.getElementById('cat');
    var price = document.getElementById('price');
    var bT = document.getElementById('bT'), bD = document.getElementById('bD'), bR = document.getElementById('bR'), bM = document.getElementById('bM');
    var hideDead = document.getElementById('hideDead');
    var results = document.getElementById('results');
    var stats = document.getElementById('stats');
    var tally = document.getElementById('tally');

    var BADGE_COLOR = { T: 'text-bg-info', D: 'text-bg-warning', R: 'text-bg-danger', M: 'text-bg-secondary' };

    function esc(s) {
        return s.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    function render() {
        var query = q.value.trim().toLowerCase();
        var c = cat.value;
        var p = price.value;
        var want = { T: bT.checked, D: bD.checked, R: bR.checked, M: bM.checked };
        var anyBadge = want.T || want.D || want.R || want.M;
        var hide = hideDead.checked;

        var out = [];
        for (var i = 0; i < DATA.length; i++) {
            var e = DATA[i];
            var name = e[0], url = e[1], path = e[2], badges = e[3], pr = e[4], desc = e[5], status = e[6];
            if (hide && status !== 'live') { continue; }
            if (c && path.indexOf(c) !== 0 && path !== c) { continue; }
            if (p && pr !== p) { continue; }
            if (anyBadge) {
                var ok = false;
                for (var k in want) { if (want[k] && badges.indexOf(k) !== -1) { ok = true; break; } }
                if (!ok) { continue; }
            }
            if (query) {
                var hay = (name + ' ' + path + ' ' + desc).toLowerCase();
                var terms = query.split(/\s+/);
                var all = true;
                for (var t = 0; t < terms.length; t++) {
                    if (terms[t] && hay.indexOf(terms[t]) === -1) { all = false; break; }
                }
                if (!all) { continue; }
            }
            out.push(e);
        }

        var html = '';
        for (var j = 0; j < out.length; j++) {
            var x = out[j];
            var href = /^https?:\/\//.test(x[1]) ? x[1] : ('https://' + x[1]);
            var badgeHtml = '';
            for (var b = 0; b < x[3].length; b++) {
                var ch = x[3][b];
                badgeHtml += '<span class="badge ' + (BADGE_COLOR[ch] || 'text-bg-light') + ' me-1">' + ch + '</span>';
            }
            var priceChip = x[4] === 'free' ? '' : '<span class="badge text-bg-light ms-auto">' + esc(x[4]) + '</span>';
            html += '<div class="col"><div class="card h-100"><div class="card-body d-flex flex-column">'
                + '<div class="d-flex align-items-start gap-2"><h3 class="h6 mb-1 flex-grow-1">' + esc(x[0]) + '</h3>' + priceChip + '</div>'
                + '<div class="text-secondary small mb-2" style="font-size:.72rem;">' + esc(x[2]) + '</div>'
                + (x[5] ? '<p class="text-secondary small flex-grow-1 mb-2">' + esc(x[5]) + '</p>' : '')
                + '<div class="d-flex align-items-center justify-content-between">' + badgeHtml
                + '<a class="btn btn-outline-light btn-sm" href="' + esc(href) + '" target="_blank" rel="noopener nofollow">Open ↗</a>'
                + '</div></div></div></div>';
        }

        stats.textContent = out.length === DATA.length
            ? 'Showing all ' + DATA.length.toLocaleString() + ' entries'
            : 'Showing ' + out.length.toLocaleString() + ' of ' + DATA.length.toLocaleString();
        tally.textContent = (LIVE_TOTAL || 0) ? 'live: ' + LIVE_TOTAL.toLocaleString() + ' · dead/deprecated: ' + (DATA.length - LIVE_TOTAL).toLocaleString() : '';
        results.innerHTML = html || '<div class="col-12"><div class="alert alert-secondary mb-0">Nothing matches — try removing a filter.</div></div>';
    }

    q.addEventListener('input', render);
    cat.addEventListener('change', render);
    price.addEventListener('change', render);
    [bT, bD, bR, bM, hideDead].forEach(function (el) { el.addEventListener('change', render); });
    render();
})();
</script>
<p class="text-secondary small mt-3 mb-0">Data: <a href="https://osintframework.com/" target="_blank" rel="noopener">OSINT Framework</a> by @jnordine (<a href="https://github.com/lockfale/osint-framework" target="_blank" rel="noopener">GitHub</a>, CC BY 4.0). This is a searchable mirror of its public directory — every link opens the original third-party site; KevBin is not responsible for their content.</p>
<?php page_footer(); ?>