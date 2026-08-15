<?php
require_once __DIR__ . '/../../functions.php';

start_session();

$engines = [
    'torch' => [
        'name' => 'Torch',
        'onion' => 'http://xmh57jrzrnw6insl.onion/',
        'search' => 'http://xmh57jrzrnw6insl.onion/search.cgi?q=',
        'desc' => 'The oldest and largest onion index — huge number of hidden services, keyword search only.',
        'type' => 'index',
    ],
    'ddg' => [
        'name' => 'DuckDuckGo (Onion)',
        'onion' => 'https://duckduckgogg42xjoc72x3sjasowoarfbgcmvfimaftt6twagswzczad.onion/',
        'search' => 'https://duckduckgogg42xjoc72x3sjasowoarfbgcmvfimaftt6twagswzczad.onion/?q=',
        'desc' => 'The privacy search engine\'s own Tor service — clean results and zero tracking, right over the onion network.',
        'type' => 'meta',
    ],
    'ahmia' => [
        'name' => 'Ahmia',
        'onion' => 'http://juhanurmihxlp77nkq76byazcldy2hlmovfu2epvl5ankdibsot4csyd.onion/',
        'search' => 'http://juhanurmihxlp77nkq76byazcldy2hlmovfu2epvl5ankdibsot4csyd.onion/search/?q=',
        'desc' => 'A curated, abuse-free index of hidden services (it bans CSAM and scam spam). Also lists which onions are alive.',
        'type' => 'index',
    ],
    'haystak' => [
        'name' => 'Haystak',
        'onion' => 'http://haystak5njsmn2hqkewecpaxi7ltjsjxj2vcsna24xey7o6gg7z3wbyd.onion/',
        'search' => 'http://haystak5njsmn2hqkewecpaxi7ltjsjxj2vcsna24xey7o6gg7z3wbyd.onion/?q=',
        'desc' => 'Privacy-focused search engine claiming to index billions of pages across hidden services. No logs, no tracking.',
        'type' => 'uncensored',
    ],
    'candle' => [
        'name' => 'Candle',
        'onion' => 'http://gjobqjj7wyczbqie.onion/',
        'search' => 'http://gjobqjj7wyczbqie.onion/',
        'desc' => 'Lightweight classic onion index — one of the longest-running directories, simple and fast.',
        'type' => 'index',
    ],
    'phobos' => [
        'name' => 'Phobos',
        'onion' => 'http://phobosxilamwcg75xt22id7aywkz5366ujzkbjmm2t2qvwuqej6avc5id.onion/',
        'search' => 'http://phobosxilamwcg75xt22id7aywkz5366ujzkbjmm2t2qvwuqej6avc5id.onion/search?q=',
        'desc' => 'Independent onion index with a clean interface, advanced filters and an up/down status check.',
        'type' => 'index',
    ],
    'tor66' => [
        'name' => 'Tor66',
        'onion' => 'http://tor66sewebgixwcq.onion/',
        'search' => 'http://tor66sewebgixwcq.onion/search?q=',
        'desc' => 'General-purpose hidden service index with a no-nonsense layout and keyword search.',
        'type' => 'index',
    ],
    'onionland' => [
        'name' => 'OnionLand Search',
        'onion' => 'http://3bbad7fauom4d6sgpcalyiim2mq06zub6nq6g3bw7zvffs2i4lyvqdyd.onion/',
        'search' => 'http://3bbad7fauom4d6sgpcalyiim2mq06zub6nq6g3bw7zvffs2i4lyvqdyd.onion/search?q=',
        'desc' => 'Search engine for the dark web with "crawled links only" indexing — returns live, verified onion services.',
        'type' => 'uncensored',
    ],
    'excavator' => [
        'name' => 'Excavator',
        'onion' => 'http://2fd6cemt4gmccflhm6imv4voe3m7w6hjb6mmpaomhaxmnr66prvb5hqd.onion/',
        'search' => 'http://2fd6cemt4gmccflhm6imv4voe3m7w6hjb6mmpaomhaxmnr66prvb5hqd.onion/',
        'desc' => 'Onion crawler that indexes hidden services without editorial filtering.',
        'type' => 'uncensored',
    ],
    'darksearch' => [
        'name' => 'DarkSearch',
        'onion' => 'http://darksch4hfzth6q4k7l7p4h6w7bl6y6p6e5k7m4l4g5l5l6t5g2q5b5yd.onion/',
        'search' => 'http://darksch4hfzth6q4k7l7p4h6w7bl6y6p6e5k7m4l4g5l5l6t5g2q5b5yd.onion/',
        'desc' => 'Dark web search engine with live uptime checks and onion database, also reachable on the clearnet.',
        'type' => 'index',
    ],
    'notevil' => [
        'name' => 'Not Evil',
        'onion' => 'http://hss3uro2hsxfogfq.onion/',
        'search' => 'http://hss3uro2hsxfogfq.onion/?q=',
        'desc' => 'Community-run, uncensored onion index built on a forks of the old TorSearch project. No logs kept.',
        'type' => 'uncensored',
    ],
    'searxng' => [
        'name' => 'SearXNG (Busshi, Onion)',
        'onion' => 'http://busshasi4eqiuzlmwsrp46a4o4zqgqxh3hfp3wnaqa6hx3qznxb6khyd.onion/',
        'search' => 'http://busshasi4eqiuzlmwsrp46a4o4zqgqxh3hfp3wnaqa6hx3qznxb6khyd.onion/search?q=',
        'desc' => 'Self-hostable metasearch over Tor. Aggregates many engines at once, keeps no history — uncensored by design.',
        'type' => 'uncensored',
    ],
];

$clearnet = [
    'brave' => [
        'name' => 'Brave Search',
        'url' => 'https://search.brave.com/search?q=',
        'desc' => 'Independent crawler index (not Google/Bing) with its own ranking and minimal filtering. Free, private-by-default.',
    ],
    'mojeek' => [
        'name' => 'Mojeek',
        'url' => 'https://www.mojeek.com/search?q=',
        'desc' => 'Independent UK crawler that builds its own index — no Google/Bing results, no personalisation, no censorship of results.',
    ],
    'marginalia' => [
        'name' => 'Marginalia Search',
        'url' => 'https://search.marginalia.nu/search?query=',
        'desc' => 'Search engine for the old, weird and forgotten web. Deliberately surfaces small sites that big engines bury.',
    ],
    'stract' => [
        'name' => 'Stract',
        'url' => 'https://stract.com/search?q=',
        'desc' => 'Open-source, fully independent search index. No accounts, no tracking, uncensored results.',
    ],
    'searxbe' => [
        'name' => 'SearXNG (searx.be)',
        'url' => 'https://searx.be/search?q=',
        'desc' => 'Public SearXNG metasearch instance — aggregates dozens of engines at once, results are not filtered or logged.',
    ],
    'searxbus' => [
        'name' => 'SearXNG (bus-hit.me)',
        'url' => 'https://search.bus-hit.me/search?q=',
        'desc' => 'Another popular public SearXNG instance for uncensored metasearch across engines.',
    ],
    'yandex' => [
        'name' => 'Yandex',
        'url' => 'https://yandex.com/search/?text=',
        'desc' => 'Russian search giant — its own index and far lighter content filtering than US engines.',
    ],
    'startpage' => [
        'name' => 'Startpage',
        'url' => 'https://www.startpage.com/sp/search?query=',
        'desc' => 'Google results through a private proxy — uncensored by Google\'s signed-in filters, no history stored.',
    ],
    'metager' => [
        'name' => 'MetaGer',
        'url' => 'https://metager.org/meta/meta.ger3?eingabe=',
        'desc' => 'German non-profit metasearch — combines many engines, no profiling, results not removed by default.',
    ],
    'presearch' => [
        'name' => 'Presearch',
        'url' => 'https://presearch.com/search?q=',
        'desc' => 'Decentralised, community-owned search — node-based, no central filtering, rewards its users.',
    ],
];

page_header('Dark Web Search');
?>
<div class="container" style="max-width: 950px;">
    <h1 class="h4 mb-1 reveal in-view">🌑 Dark Web Search</h1>
    <p class="text-secondary mb-3 reveal in-view">A gateway to the Tor network's search engines. Your query goes <strong>directly from your Tor Browser</strong> to the onion service — nothing is sent through this server, so your searches stay yours. All .onion links only work inside <a href="https://www.torproject.org/download/" target="_blank" rel="noopener">Tor Browser</a>.</p>

    <div class="alert alert-warning reveal in-view mb-4">
        <strong>How to search:</strong> install <a href="https://www.torproject.org/download/" target="_blank" rel="noopener">Tor Browser</a>, open this page inside it, type your query below and press Search. The onion index opens and runs the search for you. Regular browsers (Chrome, Firefox, Edge) <strong>cannot</strong> open .onion links — use the <strong>clearnet engines</strong> below instead, they work in any browser.
    </div>

    <div class="card reveal in-view"><div class="card-body">
        <form id="onion-form" class="row g-2 align-items-end">
            <div class="col-12">
                <div class="btn-group btn-group-sm w-100" role="group" aria-label="Network mode">
                    <input type="radio" class="btn-check" name="netmode" id="mode-tor" checked>
                    <label class="btn btn-outline-light" for="mode-tor">🌑 Tor engines (.onion)</label>
                    <input type="radio" class="btn-check" name="netmode" id="mode-clear">
                    <label class="btn btn-outline-light" for="mode-clear">🌐 Clearnet engines (any browser)</label>
                </div>
            </div>
            <div class="col-md-4">
                <label class="form-label small text-secondary mb-1" for="engine">Engine</label>
                <select class="form-select" id="engine"></select>
            </div>
            <div class="col-md-6">
                <label class="form-label small text-secondary mb-1" for="query">Query</label>
                <input class="form-control" id="query" placeholder="Search the (dark) web…" maxlength="200" autofocus>
            </div>
            <div class="col-md-2 d-grid">
                <button class="btn btn-primary" type="submit">Search ✦</button>
            </div>
        </form>
        <p class="text-secondary small mb-0 mt-2">Opens: <span id="target-url" class="text-info" style="word-break:break-all;"></span></p>
    </div></div>

    <h2 class="h6 mb-3 mt-4 reveal in-view" style="border-bottom:1px solid var(--line);padding-bottom:.5rem;">🔎 Tor index engines <span class="badge bg-secondary ms-1">12</span></h2>
    <div class="row row-cols-1 row-cols-md-2 g-4 mb-4">
        <?php foreach ($engines as $key => $e): ?>
            <div class="col reveal">
                <div class="card h-100"><div class="card-body d-flex flex-column">
                    <div class="d-flex align-items-center justify-content-between mb-1">
                        <h3 class="h6 mb-0"><?= e($e['name']) ?></h3>
                        <?php if (($e['type'] ?? '') === 'uncensored'): ?>
                            <span class="badge bg-warning text-dark" title="Indexes content without editorial removal">UNCENSORED</span>
                        <?php endif; ?>
                        <a class="btn btn-outline-light btn-sm" href="<?= e($e['onion']) ?>" target="_blank" rel="noopener">Open ↗</a>
                    </div>
                    <p class="text-secondary small flex-grow-1 mb-2"><?= e($e['desc']) ?></p>
                    <code class="small" style="word-break:break-all;"><?= e($e['onion']) ?></code>
                </div></div>
            </div>
        <?php endforeach; ?>
    </div>

    <h2 class="h6 mb-3 mt-4 reveal in-view" style="border-bottom:1px solid var(--line);padding-bottom:.5rem;">🌐 Uncensored clearnet search engines <span class="badge bg-secondary ms-1">10</span></h2>
    <p class="text-secondary small mb-3">These work in any browser — no Tor required. "Uncensored" means they run their own index (or aggregate many), keep no history and do not silently remove results the way the big engines do. Still, the law applies: only search for what you're allowed to research.</p>
    <div class="row row-cols-1 row-cols-md-2 g-4 mb-4">
        <?php foreach ($clearnet as $key => $e): ?>
            <div class="col reveal">
                <div class="card h-100"><div class="card-body d-flex flex-column">
                    <div class="d-flex align-items-center justify-content-between mb-1">
                        <h3 class="h6 mb-0"><?= e($e['name']) ?></h3>
                        <a class="btn btn-outline-light btn-sm" href="<?= e($e['url']) ?>" target="_blank" rel="noopener">Open ↗</a>
                    </div>
                    <p class="text-secondary small flex-grow-1 mb-2"><?= e($e['desc']) ?></p>
                    <code class="small" style="word-break:break-all;"><?= e($e['url']) ?>…</code>
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
    var clearnet = <?= json_encode($clearnet, JSON_UNESCAPED_SLASHES) ?>;
    var form = document.getElementById('onion-form');
    var sel = document.getElementById('engine');
    var q = document.getElementById('query');
    var target = document.getElementById('target-url');
    var modeTor = document.getElementById('mode-tor');
    var modeClear = document.getElementById('mode-clear');
    var current = engines;

    function fill() {
        var keys = Object.keys(current);
        sel.innerHTML = '';
        keys.forEach(function (k) {
            var o = document.createElement('option');
            o.value = k;
            o.textContent = current[k].name;
            sel.appendChild(o);
        });
        updateTarget();
    }
    function updateTarget() {
        var e = current[sel.value];
        target.textContent = e.search ? e.search + (q.value ? encodeURIComponent(q.value) : '…') : (e.url + (q.value ? encodeURIComponent(q.value) : '…'));
    }
    modeTor.addEventListener('change', function () { current = engines; fill(); });
    modeClear.addEventListener('change', function () { current = clearnet; fill(); });
    sel.addEventListener('change', updateTarget);
    q.addEventListener('input', updateTarget);
    form.addEventListener('submit', function (e) {
        e.preventDefault();
        var term = q.value.trim();
        if (!term) { q.focus(); return; }
        var base = current[sel.value].search || current[sel.value].url;
        window.open(base + encodeURIComponent(term), '_blank', 'noopener');
    });
    fill();
})();
</script>
<?php page_footer(); ?>
