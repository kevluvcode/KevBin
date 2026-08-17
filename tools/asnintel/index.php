<?php
require_once __DIR__ . '/../../functions.php';

start_session();
$cfg = $GLOBALS['CFG'];

$q = trim((string)($_POST['q'] ?? ''));
$error = null;
$result = null;
$done = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $done = true;
    if (!csrf_verify()) {
        $error = 'Invalid CSRF token. Reload the page and try again.';
    } elseif (!rate_limit_check('asnintel', 8, (int)$cfg['rate_window_seconds'])) {
        $error = 'Rate limit reached — wait a few minutes between lookups.';
    } elseif ($q === '' || strlen($q) > 190) {
        $error = 'Enter an IP address or AS number to search.';
    } else {
        log_activity('tool_asnintel', $q);
        if (filter_var($q, FILTER_VALIDATE_IP) && is_public_ip($q)) {
            // ipwho.is gives ASN + org + geo in one call (public API, used by the tracker too).
            $json = http_get('https://ipwho.is/' . rawurlencode($q), 6);
            $data = $json !== null ? json_decode($json, true) : null;
            if (is_array($data) && ($data['success'] ?? false)) {
                $conn = $data['connection'] ?? [];
                $result = [
                    'query' => $q,
                    'asn' => (string)($conn['asn'] ?? ''),
                    'org' => (string)($conn['org'] ?? ($data['connection']['isp'] ?? '')),
                    'isp' => (string)($data['connection']['isp'] ?? ''),
                    'country' => (string)($data['country'] ?? ''),
                    'region' => (string)($data['region'] ?? ''),
                    'city' => (string)($data['city'] ?? ''),
                    'type' => (string)($data['type'] ?? ''),
                ];
            } else {
                $error = 'Could not resolve the IP to an AS/org (lookup API unreachable).';
            }
        } else {
            $asN = null;
            if (preg_match('/^as(\d+)$/i', $q, $m)) {
                $asN = (int)$m[1];
            } elseif (is_numeric($q) && (int)$q > 0) {
                $asN = (int)$q;
            }
            $second = null;
            if ($asN !== null) {
                $json = http_get('https://stat.ripe.net/data/as-overview/data.json?resource=AS' . $asN, 8);
                $data = $json !== null ? json_decode($json, true) : null;
                $overview = $data['data'] ?? null;
                if (is_array($overview)) {
                    $result = [
                        'query' => 'AS' . $asN,
                        'asn' => 'AS' . ($overview['asn'] ?? $asN),
                        'org' => (string)($overview['holder'] ?? ''),
                        'country' => (string)($overview['country'] ?? ''),
                        'first_seen' => (string)($overview['first_seen'] ?? ''),
                        'age' => (string)($overview['age'] ?? ''),
                    ];
                } else {
                    $error = 'Could not find AS' . $asN . ' in the RIPE database.';
                }
            } else {
                $error = 'Could not find that AS number. Use "AS13335" or an IP address.';
            }
        }
    }
}

page_header('ASN / BGP Lookup');
?>
<div class="container" style="max-width: 900px;">
    <h1 class="h4 mb-1 reveal in-view">🌍 ASN &amp; BGP Lookup</h1>
    <p class="text-secondary mb-3 reveal in-view">Find which <strong>autonomous system</strong> (AS / network operator) announces an IP, or who holds an AS number. Data comes from <strong>RIPEStat</strong> (regional registry public data) and <strong>ipwho.is</strong> — passive lookups only. Great for mapping who actually operates the network behind a domain you're investigating, and for seeing if two IPs are on the same operator (useful when checking if the "same person" runs multiple sites).</p>

    <div class="card reveal in-view"><div class="card-body">
        <form method="post" action="index.php" class="mb-0">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <div class="input-group">
                <input class="form-control" name="q" maxlength="190" placeholder="IP (1.2.3.4) or AS number (AS13335)" value="<?= $done ? e($q) : '' ?>" required>
                <button class="btn btn-primary" type="submit">Look up</button>
            </div>
        </form>
    </div></div>

    <?php if ($error !== null): ?>
        <div class="alert alert-danger mt-4 reveal in-view"><?= e($error) ?></div>
    <?php endif; ?>

    <?php if ($done && $error === null && is_array($result)): ?>
        <div class="card mt-4 reveal in-view"><div class="card-body">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <div class="text-secondary small">Query</div>
                    <code class="fs-5"><?= e((string)$result['query']) ?></code>
                </div>
                <?php if (!empty($result['asn'])): ?>
                    <span class="badge bg-primary"><?= e($result['asn']) ?></span>
                <?php endif; ?>
            </div>
            <hr>
            <div class="row g-3">
                <?php if (($result['org'] ?? '') !== ''): ?>
                    <div class="col-md-6"><div class="text-secondary small">Organization / holder</div><div class="fs-6"><?= e((string)$result['org']) ?></div></div>
                <?php endif; ?>
                <?php if (($result['isp'] ?? '') !== ''): ?>
                    <div class="col-md-6"><div class="text-secondary small">ISP</div><div class="fs-6"><?= e((string)$result['isp']) ?></div></div>
                <?php endif; ?>
                <?php if (($result['country'] ?? '') !== ''): ?>
                    <div class="col-md-4"><div class="text-secondary small">Country</div><div class="fs-6"><?= e((string)$result['country']) ?></div></div>
                <?php endif; ?>
                <?php if (($result['region'] ?? '') !== ''): ?>
                    <div class="col-md-4"><div class="text-secondary small">Region</div><div class="fs-6"><?= e((string)$result['region']) ?></div></div>
                <?php endif; ?>
                <?php if (($result['city'] ?? '') !== ''): ?>
                    <div class="col-md-4"><div class="text-secondary small">City</div><div class="fs-6"><?= e((string)$result['city']) ?></div></div>
                <?php endif; ?>
                <?php if (($result['type'] ?? '') !== ''): ?>
                    <div class="col-md-4"><div class="text-secondary small">Type</div><div class="fs-6"><?= e((string)$result['type']) ?></div></div>
                <?php endif; ?>
                <?php if (($result['first_seen'] ?? '') !== ''): ?>
                    <div class="col-md-4"><div class="text-secondary small">First seen in registry</div><div class="fs-6"><?= e((string)$result['first_seen']) ?> (<?= (int)($result['age'] ?? 0) ?> days)</div></div>
                <?php endif; ?>
            </div>
        </div></div>
        <p class="text-secondary small mt-3 mb-0">Registry data is a few days delayed and reflects RIPE's view; always verify against <a href="https://stat.ripe.net/" target="_blank" rel="noopener">stat.ripe.net</a> directly. For legal research and security testing of systems you own.</p>
    <?php endif; ?>
</div>
<?php page_footer(); ?>