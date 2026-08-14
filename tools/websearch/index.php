<?php
require_once __DIR__ . '/../../functions.php';

start_session();
$cfg = $GLOBALS['CFG'];

$q = trim((string)($_POST['q'] ?? ''));
$error = null;
$results = [];
$searched = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $searched = true;
    if (!csrf_verify()) {
        $error = 'Invalid CSRF token. Reload the page and try again.';
    } elseif (!rate_limit_check('websearch', 20, (int)$cfg['rate_window_seconds'])) {
        $error = 'Rate limit reached — wait a few minutes between searches.';
    } elseif ($q === '' || strlen($q) > 200) {
        $error = 'Enter a query (max 200 characters).';
    }
    if ($error === null) {
        $html = http_get('https://html.duckduckgo.com/html/?q=' . rawurlencode($q) . '&kl=wt-wt', 10);
        if ($html === null) {
            $error = 'The search engine did not respond (it may be down or blocked from this host). Try again in a moment.';
        } else {
            $results = parse_ddg_html($html);
        }
    }
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
        $out[] = [
            'title' => $title,
            'url' => $url,
            'host' => (string)parse_url($url, PHP_URL_HOST),
            'snippet' => $snippet,
        ];
        if (count($out) >= 30) {
            break;
        }
    }
    return $out;
}

page_header('Web Search');
?>
<div class="container" style="max-width: 900px;">
    <h1 class="h4 mb-1 reveal in-view">🌐 Clearnet Web Search</h1>
    <p class="text-secondary mb-3 reveal in-view">A private search engine for the open web. Your query is sent from this server to <strong>DuckDuckGo</strong> (no tracking, no account) and the results are rendered here — your IP is never shared with the search index.</p>

    <div class="card reveal in-view"><div class="card-body">
        <form method="post" action="index.php" class="mb-0">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <div class="input-group">
                <input class="form-control" name="q" placeholder="Search the clearnet…" value="<?= $searched ? e($q) : '' ?>" maxlength="200" autofocus required>
                <button class="btn btn-primary" type="submit">Search</button>
            </div>
        </form>
    </div></div>

    <?php if ($error !== null): ?>
        <div class="alert alert-danger mt-4 reveal in-view"><?= e($error) ?></div>
    <?php endif; ?>

    <?php if ($searched && $error === null): ?>
        <div class="d-flex justify-content-between align-items-center mt-4 mb-2 reveal in-view">
            <span class="text-secondary small"><?= count($results) === 0 ? 'No results' : number_format(count($results)) . ' results' ?> for “<?= e($q) ?>”</span>
            <span class="text-secondary small">via DuckDuckGo</span>
        </div>

        <?php if (count($results) === 0): ?>
            <div class="alert alert-secondary reveal in-view">Nothing found — try different keywords.</div>
        <?php endif; ?>

        <div class="d-grid gap-3">
            <?php foreach ($results as $r): ?>
                <div class="card reveal"><div class="card-body">
                    <h2 class="h6 mb-1"><a href="<?= e($r['url']) ?>" target="_blank" rel="noopener nofollow"><?= e($r['title']) ?></a></h2>
                    <div class="text-secondary small mb-1" style="word-break:break-all;"><?= e($r['host']) ?></div>
                    <?php if ($r['snippet'] !== ''): ?>
                        <p class="text-secondary small mb-0"><?= e($r['snippet']) ?></p>
                    <?php endif; ?>
                </div></div>
            <?php endforeach; ?>
        </div>

        <p class="text-secondary small mt-4 mb-0">Result links open the original third-party site; KevBin does not control their content. Searches are rate-limited. For legal research and educational use only.</p>
    <?php endif; ?>
</div>
<?php page_footer(); ?>
