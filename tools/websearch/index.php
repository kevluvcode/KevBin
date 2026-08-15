<?php
require_once __DIR__ . '/../../functions.php';

start_session();
$cfg = $GLOBALS['CFG'];

$q = trim((string)($_POST['q'] ?? ''));
$error = null;
$enginesOut = [];
$merged = [];
$searched = false;

$engineDefs = [
    'ddg'       => ['label' => 'DuckDuckGo',    'badge' => 'bg-info text-dark'],
    'mojeek'    => ['label' => 'Mojeek',        'badge' => 'bg-success'],
    'wikipedia' => ['label' => 'Wikipedia',     'badge' => 'bg-warning text-dark'],
    'github'    => ['label' => 'GitHub',        'badge' => 'bg-primary'],
    'ia'        => ['label' => 'DDG Answers',   'badge' => 'bg-secondary'],
    'searx'     => ['label' => 'SearXNG',       'badge' => 'bg-danger'],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $searched = true;
    if (!csrf_verify()) {
        $error = 'Invalid CSRF token. Reload the page and try again.';
    } elseif (!rate_limit_check('websearch', 30, (int)$cfg['rate_window_seconds'])) {
        $error = 'Rate limit reached — wait a few minutes between searches.';
    } elseif ($q === '' || strlen($q) > 200) {
        $error = 'Enter a query (max 200 characters).';
    }
    if ($error === null) {
        try {
            $enginesOut = search_everything($q);
            $merged = merge_results($enginesOut);
        } catch (Throwable $t) {
            error_log('[websearch] ' . $t->getMessage());
            $error = 'The search engine could not be reached from this server right now. Try again in a moment.';
        }
    }
}

// ——— Fetchers ———

// Parallel HTTP fetch (curl_multi when available, else sequential fallback).
function http_get_multi(array $urls, int $timeout = 7): array
{
    $urls = array_values(array_unique(array_filter($urls, 'is_string')));
    $out = [];
    if ($urls === []) {
        return $out;
    }
    if (!function_exists('curl_init') || !function_exists('curl_multi_exec')) {
        foreach ($urls as $u) {
            $out[$u] = http_get($u, $timeout);
        }
        return $out;
    }
    $mh = curl_multi_init();
    $chs = [];
    foreach ($urls as $u) {
        $ch = curl_init($u);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_CONNECTTIMEOUT => $timeout,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (KevBin Metasearch)',
        ]);
        curl_multi_add_handle($mh, $ch);
        $chs[$u] = $ch;
    }
    $running = null;
    do {
        $status = curl_multi_exec($mh, $running);
        if ($running > 0) {
            curl_multi_select($mh, 0.2);
        }
    } while ($running > 0 && $status === CURLM_OK);
    foreach ($chs as $u => $ch) {
        $code = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $body = curl_multi_getcontent($ch);
        $out[$u] = (is_string($body) && $code < 400) ? $body : null;
        curl_multi_remove_handle($mh, $ch);
        curl_close($ch);
    }
    curl_multi_close($mh);
    return $out;
}

function parse_ddg_html(string $html): array
{
    $out = [];
    if (strpos($html, 'result__a') === false) {
        return $out;
    }
    $doc = new DOMDocument();
    libxml_use_internal_errors(true);
    if (!$doc->loadHTML($html)) {
        return $out;
    }
    $x = new DOMXPath($doc);
    $anchors = $x->query('//a[contains(concat(" ", normalize-space(@class), " "), " result__a ")]');
    foreach ($anchors as $a) {
        $href = trim((string)$a->getAttribute('href'));
        $url = '';
        if (preg_match('~[?&]uddg=([^&]+)~', $href, $m)) {
            $url = rawurldecode($m[1]);
        } elseif (preg_match('~^https?://~i', $href)) {
            $url = $href;
        }
        if ($url === '' || !preg_match('~^https?://~i', $url) || stripos($url, 'duckduckgo.com') !== false) {
            continue;
        }
        $title = trim(preg_replace('/\s+/', ' ', $a->textContent));
        if ($title === '') {
            continue;
        }
        $snippet = '';
        $body = ($a->parentNode !== null && $a->parentNode->parentNode !== null) ? $a->parentNode->parentNode : null;
        if ($body !== null) {
            foreach ($x->query('.//a[contains(concat(" ", normalize-space(@class), " "), " result__snippet ")]', $body) as $s) {
                $snippet = trim(preg_replace('/\s+/', ' ', $s->textContent));
                break;
            }
        }
        $out[] = ['title' => $title, 'url' => $url, 'host' => (string)parse_url($url, PHP_URL_HOST), 'snippet' => $snippet];
        if (count($out) >= 20) {
            break;
        }
    }
    return $out;
}

function parse_mojeek_html(string $html): array
{
    $out = [];
    $doc = new DOMDocument();
    libxml_use_internal_errors(true);
    if (!$doc->loadHTML($html)) {
        return $out;
    }
    $x = new DOMXPath($doc);
    $anchors = $x->query('//ul[contains(@class,"results")]//li//h2/a[@href] | //h2/a[@href]');
    foreach ($anchors as $a) {
        $url = trim((string)$a->getAttribute('href'));
        if ($url === '' || !preg_match('~^https?://~i', $url)) {
            continue;
        }
        $title = trim(preg_replace('/\s+/', ' ', $a->textContent));
        if ($title === '') {
            continue;
        }
        $snippet = '';
        $li = $a;
        while ($li !== null && $li->nodeName !== 'li') {
            $li = $li->parentNode;
        }
        if ($li !== null) {
            foreach ($x->query('.//p[contains(@class,"s")] | .//p', $li) as $p) {
                $snip = trim(preg_replace('/\s+/', ' ', $p->textContent));
                if ($snip !== '') {
                    $snippet = $snip;
                    break;
                }
            }
        }
        $out[] = ['title' => $title, 'url' => $url, 'host' => (string)parse_url($url, PHP_URL_HOST), 'snippet' => $snippet];
        if (count($out) >= 12) {
            break;
        }
    }
    return $out;
}

function parse_wikipedia_json(string $json): array
{
    $out = [];
    $data = json_decode($json, true);
    $hits = $data['query']['search'] ?? [];
    $i = 0;
    foreach ($hits as $h) {
        $title = (string)($h['title'] ?? '');
        if ($title === '') {
            continue;
        }
        $slug = str_replace(' ', '_', $title);
        $url = 'https://en.wikipedia.org/wiki/' . rawurlencode($slug);
        $snippet = html_entity_decode(strip_tags((string)($h['snippet'] ?? '')), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $out[] = ['title' => $title, 'url' => $url, 'host' => 'en.wikipedia.org', 'snippet' => trim(preg_replace('/\s+/', ' ', $snippet))];
        $i++;
        if ($i >= 6) {
            break;
        }
    }
    return $out;
}

function parse_github_json(string $json): array
{
    $out = [];
    $data = json_decode($json, true);
    foreach (($data['items'] ?? []) as $r) {
        $name = (string)($r['full_name'] ?? '');
        if ($name === '') {
            continue;
        }
        $stars = (int)($r['stargazers_count'] ?? 0);
        $lang = (string)($r['language'] ?? '');
        $snippet = trim((string)($r['description'] ?? ''));
        if ($lang !== '') {
            $snippet = ($snippet !== '' ? $snippet . ' — ' : '') . 'Language: ' . $lang;
        }
        if ($stars > 0) {
            $snippet = ($snippet !== '' ? $snippet . ' — ' : '') . number_format($stars) . ' ★';
        }
        $out[] = ['title' => $name, 'url' => (string)($r['html_url'] ?? 'https://github.com/' . $name), 'host' => 'github.com', 'snippet' => $snippet];
        if (count($out) >= 6) {
            break;
        }
    }
    return $out;
}

function parse_ddg_ia_json(string $json): array
{
    $out = [];
    $data = json_decode($json, true);
    $push = function (array &$list, string $text, string $url, string $title) {
        $url = $url !== '' ? $url : '';
        if ($url !== '' && $title !== '') {
            $list[] = ['title' => $title, 'url' => $url, 'host' => (string)parse_url($url, PHP_URL_HOST), 'snippet' => mb_substr($text, 0, 400)];
        }
    };
    $abstract = trim((string)($data['AbstractText'] ?? ''));
    if ($abstract !== '' && !empty($data['AbstractURL'])) {
        $push($out, $abstract, (string)$data['AbstractURL'], (string)($data['Heading'] ?? parse_url((string)$data['AbstractURL'], PHP_URL_HOST)));
    }
    foreach (($data['RelatedTopics'] ?? []) as $t) {
        if (!empty($t['Topics']) && is_array($t['Topics'])) {
            foreach ($t['Topics'] as $sub) {
                $parts = explode(' - ', (string)($sub['Text'] ?? ''), 2);
                $push($out, (string)($sub['Text'] ?? ''), (string)($sub['FirstURL'] ?? ''), trim($parts[0] ?? ''));
            }
        } else {
            $parts = explode(' - ', (string)($t['Text'] ?? ''), 2);
            $push($out, (string)($t['Text'] ?? ''), (string)($t['FirstURL'] ?? ''), trim($parts[0] ?? ''));
        }
        if (count($out) >= 6) {
            break;
        }
    }
    return $out;
}

function parse_searxng_json(string $json): array
{
    $out = [];
    $data = json_decode($json, true);
    foreach (($data['results'] ?? []) as $r) {
        $url = (string)($r['url'] ?? '');
        $title = trim((string)($r['title'] ?? ''));
        if ($url === '' || $title === '') {
            continue;
        }
        if (!preg_match('~^https?://~i', $url)) {
            continue;
        }
        $out[] = ['title' => $title, 'url' => $url, 'host' => (string)parse_url($url, PHP_URL_HOST), 'snippet' => trim(preg_replace('/\s+/', ' ', (string)($r['content'] ?? '')))];
        if (count($out) >= 15) {
            break;
        }
    }
    return $out;
}

// ——— Runner ———

function search_everything(string $q): array
{
    $qEnc = rawurlencode($q);
    $jobs = [];
    $tasks = [
        'ddg' => 'https://html.duckduckgo.com/html/?q=' . $qEnc . '&kl=wt-wt',
        'mojeek' => 'https://www.mojeek.com/search?q=' . $qEnc,
        'wikipedia' => 'https://en.wikipedia.org/w/api.php?action=query&list=search&srsearch=' . $qEnc . '&format=json&origin=*&srlimit=8',
        'github' => 'https://api.github.com/search/repositories?q=' . $qEnc . '&per_page=8',
        'ia' => 'https://api.duckduckgo.com/?q=' . $qEnc . '&format=json&no_html=1&no_redirect=1',
        'searx1' => 'https://searx.be/search?q=' . $qEnc . '&format=json',
        'searx2' => 'https://priv.au/search?q=' . $qEnc . '&format=json',
        'searx3' => 'https://search.bus-hit.me/search?q=' . $qEnc . '&format=json',
    ];
    $bodies = http_get_multi(array_values($tasks), 7);
    $parsers = [
        'ddg' => 'parse_ddg_html',
        'mojeek' => 'parse_mojeek_html',
        'wikipedia' => 'parse_wikipedia_json',
        'github' => 'parse_github_json',
        'ia' => 'parse_ddg_ia_json',
    ];
    $out = [];
    foreach ($parsers as $engine => $fn) {
        $body = $bodies[$tasks[$engine]] ?? null;
        $out[$engine] = [
            'engine' => $engine,
            'ok' => false,
            'count' => 0,
            'results' => [],
        ];
        if ($body !== null) {
            try {
                $out[$engine]['results'] = $fn($body);
            } catch (Throwable $t) {
                error_log('[websearch ' . $engine . '] ' . $t->getMessage());
            }
            $out[$engine]['ok'] = true;
            $out[$engine]['count'] = count($out[$engine]['results']);
        }
    }
    $searxBody = $bodies[$tasks['searx1']] ?? null;
    if ($searxBody === null) {
        $searxBody = $bodies[$tasks['searx2']] ?? null;
    }
    if ($searxBody === null) {
        $searxBody = $bodies[$tasks['searx3']] ?? null;
    }
    $out['searx'] = [
        'engine' => 'searx',
        'ok' => $searxBody !== null,
        'count' => 0,
        'results' => [],
    ];
    if ($searxBody !== null) {
        try {
            $out['searx']['results'] = parse_searxng_json($searxBody);
        } catch (Throwable $t) {
            error_log('[websearch searx] ' . $t->getMessage());
        }
        $out['searx']['count'] = count($out['searx']['results']);
    }
    return $out;
}

function normalize_result_key(string $url): string
{
    $parts = parse_url($url);
    if (!is_array($parts) || empty($parts['host'])) {
        return strtolower(trim($url));
    }
    $host = strtolower((string)$parts['host']);
    if (strpos($host, 'www.') === 0) {
        $host = substr($host, 4);
    }
    $path = isset($parts['path']) ? strtolower(rtrim((string)$parts['path'], '/')) : '';
    return $host . '|' . $path;
}

function merge_results(array $enginesOut): array
{
    $merged = [];
    $index = [];
    foreach ($enginesOut as $e) {
        foreach ($e['results'] as $r) {
            $key = normalize_result_key($r['url']);
            if ($key === '') {
                continue;
            }
            if (isset($index[$key])) {
                $merged[$index[$key]]['engines'][] = $e['engine'];
                continue;
            }
            $index[$key] = count($merged);
            $ins = $r;
            $ins['engines'] = [$e['engine']];
            $merged[] = $ins;
            if (count($merged) >= 45) {
                return $merged;
            }
        }
    }
    return $merged;
}

page_header('Web Search');
?>
<div class="container" style="max-width: 900px;">
    <h1 class="h4 mb-1 reveal in-view">🌐 Clearnet Web Search</h1>
    <p class="text-secondary mb-3 reveal in-view">A private <strong>metasearch</strong> engine for the open web. Your query is sent from this server to <strong><?= count($engineDefs) ?> independent engines at once</strong> — DuckDuckGo, Mojeek, Wikipedia, GitHub, DuckDuckGo Answers and SearXNG — the results are fetched in parallel, deduplicated and combined here. No accounts, no tracking, your IP is never shared with the indexes.</p>

    <div class="card reveal in-view"><div class="card-body">
        <form method="post" action="index.php" class="mb-0">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <div class="input-group">
                <input class="form-control" name="q" placeholder="Search the clearnet (combined)…" value="<?= $searched ? e($q) : '' ?>" maxlength="200" autofocus required>
                <button class="btn btn-primary" type="submit">Search ✦</button>
            </div>
        </form>
        <div class="d-flex flex-wrap gap-2 mt-3">
            <?php foreach ($engineDefs as $id => $def): ?>
                <span class="badge <?= $def['badge'] ?>"><?= e($def['label']) ?></span>
            <?php endforeach; ?>
        </div>
    </div></div>

    <?php if ($error !== null): ?>
        <div class="alert alert-danger mt-4 reveal in-view"><?= e($error) ?></div>
    <?php endif; ?>

    <?php if ($searched && $error === null): ?>
        <?php
        $total = count($merged);
        $responded = 0;
        $found = 0;
        foreach ($enginesOut as $e) {
            if ($e['ok']) {
                $responded++;
            }
            if ($e['count'] > 0) {
                $found++;
            }
        }
        ?>
        <div class="d-flex justify-content-between align-items-center mt-4 mb-2 reveal in-view">
            <span class="text-secondary small"><?= $total === 0 ? 'No combined results' : number_format($total) . ' combined results' ?> for “<?= e($q) ?>”</span>
            <span class="text-secondary small"><?= $responded ?> of <?= count($engineDefs) ?> engines responded · <?= $found ?> with results</span>
        </div>

        <div class="d-flex flex-wrap gap-2 mb-3 reveal in-view">
            <?php foreach ($enginesOut as $e): ?>
                <?php $def = $engineDefs[$e['engine']] ?? ['label' => $e['engine'], 'badge' => 'bg-secondary']; ?>
                <?php if ($e['ok']): ?>
                    <span class="badge bg-secondary">✓ <?= e($def['label']) ?> · <?= $e['count'] ?></span>
                <?php else: ?>
                    <span class="badge bg-dark text-secondary" title="Engine unreachable or blocked from this host">✗ <?= e($def['label']) ?> · no response</span>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>

        <?php if ($total === 0): ?>
            <div class="alert alert-secondary reveal in-view">Nothing found — try different keywords.</div>
        <?php endif; ?>

        <div class="d-grid gap-3">
            <?php foreach ($merged as $r): ?>
                <div class="card reveal"><div class="card-body">
                    <h2 class="h6 mb-1"><a href="<?= e($r['url']) ?>" target="_blank" rel="noopener nofollow"><?= e($r['title']) ?></a></h2>
                    <div class="text-secondary small mb-1" style="word-break:break-all;"><?= e($r['host']) ?>
                        <?php foreach ($r['engines'] as $eg): ?>
                            <?php $def = $engineDefs[$eg] ?? ['label' => $eg, 'badge' => 'bg-secondary']; ?>
                            <span class="badge <?= $def['badge'] ?> ms-1" title="Found by this engine">via <?= e($def['label']) ?></span>
                        <?php endforeach; ?>
                    </div>
                    <?php if ($r['snippet'] !== ''): ?>
                        <p class="text-secondary small mb-0"><?= e(mb_substr($r['snippet'], 0, 500)) ?></p>
                    <?php endif; ?>
                </div></div>
            <?php endforeach; ?>
        </div>

        <p class="text-secondary small mt-4 mb-0">Result links open the original third-party site; KevBin does not control their content. Engines that don't respond (blocked, rate-limited or down) are skipped — the rest still answer. Searches are rate-limited. For legal research and educational use only.</p>
    <?php endif; ?>
</div>
<?php page_footer(); ?>