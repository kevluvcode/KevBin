<?php
require_once __DIR__ . '/../../functions.php';

start_session();

$engines = [
    'torch' => [
        'name' => 'Torch',
        'onion' => 'http://xmh57jrzrnw6insl.onion/',
        'search' => 'http://xmh57jrzrnw6insl.onion/search.cgi?q=',
        'desc' => 'The oldest and largest onion index — huge number of hidden services, keyword search only.',
    ],
    'ddg' => [
        'name' => 'DuckDuckGo (Onion)',
        'onion' => 'https://duckduckgogg42xjoc72x3sjasowoarfbgcmvfimaftt6twagswzczad.onion/',
        'search' => 'https://duckduckgogg42xjoc72x3sjasowoarfbgcmvfimaftt6twagswzczad.onion/?q=',
        'desc' => 'The privacy search engine\'s own Tor service — clean results and zero tracking, right over the onion network.',
    ],
    'ahmia' => [
        'name' => 'Ahmia',
        'onion' => 'http://juhanurmihxlp77nkq76byazcldy2hlmovfu2epvl5ankdibsot4csyd.onion/',
        'search' => 'http://juhanurmihxlp77nkq76byazcldy2hlmovfu2epvl5ankdibsot4csyd.onion/search/?q=',
        'desc' => 'A curated, abuse-free index of hidden services (it bans CSAM and scam spam). Also lists which onions are alive.',
    ],
];

page_header('Dark Web Search');
?>
<div class="container" style="max-width: 950px;">
    <h1 class="h4 mb-1 reveal in-view">🌑 Dark Web Search</h1>
    <p class="text-secondary mb-3 reveal in-view">A gateway to the Tor network's search engines. Your query goes <strong>directly from your Tor Browser</strong> to the onion service — nothing is sent through this server, so your searches stay yours. All .onion links only work inside <a href="https://www.torproject.org/download/" target="_blank" rel="noopener">Tor Browser</a>.</p>

    <div class="alert alert-warning reveal in-view mb-4">
        <strong>How to search:</strong> install <a href="https://www.torproject.org/download/" target="_blank" rel="noopener">Tor Browser</a>, open this page inside it, type your query below and press Search. The onion index opens and runs the search for you. Regular browsers (Chrome, Firefox, Edge) <strong>cannot</strong> open .onion links.
    </div>

    <div class="card reveal in-view"><div class="card-body">
        <form id="onion-form" class="row g-2 align-items-end">
            <div class="col-md-4">
                <label class="form-label small text-secondary mb-1" for="engine">Engine</label>
                <select class="form-select" id="engine">
                    <?php foreach ($engines as $key => $e): ?>
                        <option value="<?= e($key) ?>"><?= e($e['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-6">
                <label class="form-label small text-secondary mb-1" for="query">Query</label>
                <input class="form-control" id="query" placeholder="Search the dark web…" maxlength="200" autofocus>
            </div>
            <div class="col-md-2 d-grid">
                <button class="btn btn-primary" type="submit">Search ✦</button>
            </div>
        </form>
        <p class="text-secondary small mb-0 mt-2">Opens: <span id="target-url" class="text-info" style="word-break:break-all;"></span></p>
    </div></div>

    <h2 class="h6 mb-3 mt-4 reveal in-view" style="border-bottom:1px solid var(--line);padding-bottom:.5rem;">🔎 Index engines</h2>
    <div class="row row-cols-1 row-cols-md-2 g-4 mb-4">
        <?php foreach ($engines as $key => $e): ?>
            <div class="col reveal">
                <div class="card h-100"><div class="card-body d-flex flex-column">
                    <div class="d-flex align-items-center justify-content-between mb-1">
                        <h3 class="h6 mb-0"><?= e($e['name']) ?></h3>
                        <a class="btn btn-outline-light btn-sm" href="<?= e($e['onion']) ?>" target="_blank" rel="noopener">Open ↗</a>
                    </div>
                    <p class="text-secondary small flex-grow-1 mb-2"><?= e($e['desc']) ?></p>
                    <code class="small" style="word-break:break-all;"><?= e($e['onion']) ?></code>
                </div></div>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="card reveal"><div class="card-body">
        <h2 class="h6 mb-2">⚠️ Dark web safety & legality</h2>
        <p class="text-secondary small mb-0">The Tor network is anonymous — and so are the people on it. Only search for what you are legally allowed to research, never follow links to illegal material, and treat everything you find as untrusted. Onion addresses change when services move; always verify an address from more than one source. KevBin provides this directory for legal research, journalism and security education only.</p>
    </div></div>
</div>

<script>
(function () {
    var engines = <?= json_encode($engines, JSON_UNESCAPED_SLASHES) ?>;
    var form = document.getElementById('onion-form');
    var sel = document.getElementById('engine');
    var q = document.getElementById('query');
    var target = document.getElementById('target-url');
    function updateTarget() {
        target.textContent = engines[sel.value].search + (q.value ? encodeURIComponent(q.value) : '…');
    }
    sel.addEventListener('change', updateTarget);
    q.addEventListener('input', updateTarget);
    form.addEventListener('submit', function (e) {
        e.preventDefault();
        var term = q.value.trim();
        if (!term) { q.focus(); return; }
        window.open(engines[sel.value].search + encodeURIComponent(term), '_blank', 'noopener');
    });
    updateTarget();
})();
</script>
<?php page_footer(); ?>