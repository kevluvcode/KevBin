<?php
require_once __DIR__ . '/../../functions.php';

start_session();
$cfg = $GLOBALS['CFG'];

$ip = trim((string)($_POST['ip'] ?? ''));
$error = null;
$domains = [];
$done = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $done = true;
    if (!csrf_verify()) {
        $error = 'Invalid CSRF token. Reload the page and try again.';
    } elseif (!rate_limit_check('revip', 6, (int)$cfg['rate_window_seconds'])) {
        $error = 'Rate limit reached — wait a few minutes between lookups.';
    } elseif (!filter_var($ip, FILTER_VALIDATE_IP) || !is_public_ip($ip)) {
        $error = 'Enter a valid public IPv4/IPv6 address.';
    } else {
        log_activity('tool_revip', $ip);
        // Reverse IP via HackerTarget (free, no key, from this server). This is
        // passive: it just answers "which domains point at this IP".
        $resp = http_get('https://api.hackertarget.com/reverseiplookup/?q=' . rawurlencode($ip), 8);
        if ($resp === null || $resp === '') {
            $error = 'The lookup API did not respond (network blocked or rate-limited). Try again in a moment.';
        } elseif (stripos($resp, 'error') !== false && count(explode("\n", $resp)) === 1) {
            $error = trim($resp);
        } else {
            foreach (explode("\n", trim($resp)) as $d) {
                $d = strtolower(trim($d));
                if ($d !== '' && strlen($d) < 190 && preg_match('/^[a-z0-9.*-]+(\.[a-z0-9-]+)+$/i', $d)) {
                    $domains[] = $d;
                }
            }
            if (count($domains) === 0) {
                $error = 'No domains found pointing at this IP (or only internal/hidden ones).';
            }
        }
    }
}

page_header('Reverse IP Lookup');
?>
<div class="container" style="max-width: 900px;">
    <h1 class="h4 mb-1 reveal in-view">🔄 Reverse IP Lookup</h1>
    <p class="text-secondary mb-3 reveal in-view">Asks the public DNS/passive database: <em>"which domain names resolve to this IP?"</em> Useful for spotting what else lives on a shared IP you administer (before an audit) or understanding hosting sprawl. <strong>Passive and public</strong> — the query goes to a lookup API, never knocks on the target.</p>

    <div class="alert alert-warning reveal in-view"><strong>⚠️ Legal use only.</strong> Results describe publicly resolvable DNS. Use them to inventory <strong>your own</strong> hosting; don't poke at a competitor's infrastructure you don't control.</div>

    <div class="card reveal in-view"><div class="card-body">
        <form method="post" action="index.php" class="mb-0">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <div class="input-group">
                <input class="form-control" name="ip" maxlength="45" placeholder="e.g. 8.8.8.8" value="<?= $done ? e($ip) : '' ?>" required>
                <button class="btn btn-primary" type="submit">Look up</button>
            </div>
        </form>
    </div></div>

    <?php if ($error !== null): ?>
        <div class="alert alert-danger mt-4 reveal in-view"><?= e($error) ?></div>
    <?php endif; ?>

    <?php if ($done && $error === null && count($domains) > 0): ?>
        <div class="d-flex justify-content-between align-items-center mt-4 mb-3 reveal in-view">
            <span class="small"><code><?= e($ip) ?></code> → <?= count($domains) ?> domain<?= count($domains) === 1 ? '' : 's' ?></span>
            <button class="btn btn-outline-light btn-sm" onclick="copyRev()">Copy all</button>
        </div>
        <div class="card reveal in-view"><div class="list-group list-group-flush" id="rev-list" style="max-height:460px;overflow:auto;">
            <?php foreach ($domains as $d): ?>
                <a class="list-group-item list-group-item-action d-flex justify-content-between align-items-center py-2" href="https://<?= e($d) ?>" target="_blank" rel="noopener">
                    <code class="small"><?= e($d) ?></code>
                    <span class="badge bg-secondary">↗</span>
                </a>
            <?php endforeach; ?>
        </div></div>
        <p class="text-secondary small mt-2 mb-0">Names are deduplicated and lower-cased. An empty result often means shared hosting (the IP hosts many sites the passive DB hasn't logged) or the IP belongs to a CDN.</p>
    <?php endif; ?>
</div>
<script>
function copyRev() {
    var items = document.querySelectorAll('#rev-list code');
    var text = Array.prototype.map.call(items, function (el) { return el.textContent; }).join('\n');
    navigator.clipboard.writeText(text).then(function () {
        var b = document.querySelector('[onclick="copyRev()"]');
        if (b) { b.textContent = 'Copied ✓'; setTimeout(function () { b.textContent = 'Copy all'; }, 1500); }
    });
}
</script>
<?php page_footer(); ?>