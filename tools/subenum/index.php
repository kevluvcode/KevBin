<?php
require_once __DIR__ . '/../../functions.php';

start_session();
$cfg = $GLOBALS['CFG'];

$domain = trim((string)($_POST['domain'] ?? ''));
$error = null;
$subdomains = [];
$done = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $done = true;
    if (!csrf_verify()) {
        $error = 'Invalid CSRF token. Reload the page and try again.';
    } elseif (!rate_limit_check('subenum', 5, (int)$cfg['rate_window_seconds'])) {
        $error = 'Rate limit reached — wait a few minutes between lookups.';
    } elseif (!preg_match('/^(?!-)(?:[a-zA-Z0-9-]{1,63}\.)+[a-zA-Z]{2,63}$/', $domain)) {
        $error = 'That does not look like a valid domain name.';
    } else {
        log_activity('tool_subenum', $domain);
        // crt.sh = Certificate Transparency log search. Passive enumeration via
        // publicly issued TLS certs — no active scanning of the target host.
        $json = http_get('https://crt.sh/?q=' . rawurlencode('%' . $domain) . '&output=json', 12);
        if ($json === null || $json === '') {
            $error = 'crt.sh did not respond (blocked, rate-limited or down). Try again in a moment.';
        } else {
            $data = json_decode($json, true);
            if (!is_array($data)) {
                $error = 'crt.sh returned an unexpected response. No certificates found or the service is having issues.';
            } else {
                $seen = [];
                foreach ($data as $row) {
                    $names = (array)($row['name_value'] ?? []);
                    foreach ($names as $name) {
                        foreach (explode("\n", (string)$name) as $n) {
                            $n = strtolower(trim($n));
                            if ($n === '' || strlen($n) > 190 || strpos($n, '*') !== false) {
                                continue;
                            }
                            if (substr($n, -strlen($domain)) !== '.' . $domain && $n !== $domain) {
                                continue;
                            }
                            if (!isset($seen[$n])) {
                                $seen[$n] = true;
                                $subdomains[] = $n;
                            }
                        }
                    }
                }
                natcasesort($subdomains);
                $subdomains = array_values($subdomains);
                if (count($subdomains) === 0) {
                    $error = 'No subdomains found in Certificate Transparency logs (or all entries were wildcards).';
                }
            }
        }
    }
}

page_header('Subdomain Finder');
?>
<div class="container" style="max-width: 900px;">
    <h1 class="h4 mb-1 reveal in-view">📡 Subdomain Finder</h1>
    <p class="text-secondary mb-3 reveal in-view">Enumerates subdomains using <strong>Certificate Transparency logs</strong> (<a href="https://crt.sh" target="_blank" rel="noopener">crt.sh</a>) — every TLS certificate ever issued for a domain is public, so its subject / SAN names leak subdomains. This is <strong>purely passive</strong>: the query goes to crt.sh, never to the target host. Great for building an asset inventory of your own organization before a pen-test.</p>

    <div class="alert alert-warning reveal in-view"><strong>⚠️ Legal use only.</strong> This is passive OSINT from public certificate logs. If you use the results against systems you don't own or lack authorization to test, you're on your own legally.</div>

    <div class="card reveal in-view"><div class="card-body">
        <form method="post" action="index.php" class="mb-0">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <div class="input-group">
                <input class="form-control" name="domain" maxlength="253" placeholder="example.com" value="<?= $done ? e($domain) : '' ?>" required>
                <button class="btn btn-primary" type="submit">Find subdomains</button>
            </div>
        </form>
    </div></div>

    <?php if ($error !== null): ?>
        <div class="alert alert-<?= $done && count($subdomains) === 0 ? 'secondary' : 'danger' ?> mt-4 reveal in-view"><?= e($error) ?></div>
    <?php endif; ?>

    <?php if ($done && $error === null && count($subdomains) > 0): ?>
        <div class="d-flex justify-content-between align-items-center mt-4 mb-3 reveal in-view">
            <span class="small"><strong><?= e($domain) ?></strong> → <?= count($subdomains) ?> subdomain<?= count($subdomains) === 1 ? '' : 's' ?></span>
            <button class="btn btn-outline-light btn-sm" onclick="copySubdomains()">Copy all</button>
        </div>
        <div class="card reveal in-view"><div class="list-group list-group-flush" id="sub-list" style="max-height:480px;overflow:auto;">
            <?php foreach ($subdomains as $s): ?>
                <a class="list-group-item list-group-item-action d-flex justify-content-between align-items-center py-2" href="https://<?= e($s) ?>" target="_blank" rel="noopener">
                    <code class="small"><?= e($s) ?></code>
                    <span class="badge bg-secondary">↗</span>
                </a>
            <?php endforeach; ?>
        </div></div>
        <p class="text-secondary small mt-2 mb-0">Wildcard certs (e.g. <code>*.example.com</code>) are skipped — they'd flood the list with fake hosts. Click any name to open it. Note: this shows certs, so a subdomain may exist without a cert (or vice-versa).</p>
    <?php endif; ?>
</div>
<script>
function copySubdomains() {
    var items = document.querySelectorAll('#sub-list code');
    var text = Array.prototype.map.call(items, function (el) { return el.textContent; }).join('\n');
    navigator.clipboard.writeText(text).then(function () {
        var b = document.querySelector('[onclick="copySubdomains()"]');
        if (b) { b.textContent = 'Copied ✓'; setTimeout(function () { b.textContent = 'Copy all'; }, 1500); }
    });
}
</script>
<?php page_footer(); ?>