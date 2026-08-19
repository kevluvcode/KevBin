<?php
require_once __DIR__ . '/../../functions.php';

start_session();
$cfg = $GLOBALS['CFG'];

$host = trim((string)($_POST['host'] ?? ''));
$portsMode = (string)($_POST['scope'] ?? 'common');
$error = null;
$results = [];
$targetIp = null;
$done = false;

// Common + well-known ports worth a quick connectivity probe.
$portLists = [
    'common' => [21, 22, 23, 25, 53, 80, 110, 135, 139, 143, 443, 445, 993, 995, 1433, 1521, 3306, 3389, 5432, 5900, 8000, 8080, 8443, 8888, 9200, 27017],
    'top20' => [21, 22, 23, 25, 53, 80, 110, 139, 143, 443, 445, 993, 995, 3306, 3389, 5900, 8000, 8080, 8443, 8888],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $done = true;
    if (!csrf_verify()) {
        $error = 'Invalid CSRF token. Reload the page and try again.';
    } elseif (!rate_limit_check('portscan', 2, (int)$cfg['rate_window_seconds'])) {
        $error = 'Rate limit reached — wait a few minutes between scans.';
    } elseif ($host === '' || strlen($host) > 253) {
        $error = 'Enter a hostname or IP address.';
    } else {
        $targetIp = public_ip_for($host);
        if ($targetIp === null) {
            $error = 'Could not resolve a public IP for that host. Scanning private / LAN / reserved ranges is not allowed — only publicly routable targets.';
        } elseif (!isset($portLists[$portsMode])) {
            $error = 'Unknown scope.';
        } else {
            log_activity('tool_portscan', $host . ' (' . $targetIp . ')');
            $ports = $portLists[$portsMode];
            $bridgeUrl = rtrim((string)($cfg['worker_url'] ?? $cfg['discord_bridge_url'] ?? ''), '/');
            $useWorker = $bridgeUrl !== '';
            $start = microtime(true);
            foreach ($ports as $port) {
                if (microtime(true) - $start > 20) {
                    break;
                }
                if ($useWorker) {
                    // Route through Cloudflare Worker TCP proxy
                    $result = worker_tcp_probe($bridgeUrl, $targetIp, $port, 3000);
                    $results[] = $result;
                } else {
                    $sock = @fsockopen($targetIp, $port, $errno, $errstr, 1.0);
                    if (is_resource($sock)) {
                        fclose($sock);
                        $results[] = ['port' => $port, 'state' => 'open', 'service' => (string)getservbyport($port, 'tcp')];
                    } elseif ((int)$errno === 10061) {
                        $results[] = ['port' => $port, 'state' => 'closed', 'service' => ''];
                    } else {
                        $results[] = ['port' => $port, 'state' => 'filtered', 'service' => ''];
                    }
                }
            }
        }
    }
}

page_header('Port Scanner');
?>
<div class="container" style="max-width: 900px;">
    <h1 class="h4 mb-1 reveal in-view">🔓 TCP Port Scanner</h1>
    <p class="text-secondary mb-3 reveal in-view">Performs a lightweight <strong>TCP connect() probe</strong> of common ports on a public host you name. This is a <strong>connect scan on the server's network</strong> — one connection per port, then we move on. Ideal for checking whether your own server or firewall is exposing services. Not a hostile/async scanner: no flooding, no banner grabbing, no SYN (requires raw sockets and is out of scope here).</p>

    <div class="alert alert-warning reveal in-view"><strong>⚠️ Legal use only.</strong> Only scan hosts you own, operate, or have explicit written permission to test. Unauthorized scanning of third-party networks may violate laws and your hosting provider's terms (it can look like an attack from the server's shared IP). Every scan is rate-limited and logged.</div>

    <div class="card reveal in-view"><div class="card-body">
        <form method="post" action="index.php" class="mb-0">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <div class="row g-2">
                <div class="col-md-6">
                    <input class="form-control" name="host" maxlength="253" placeholder="Hostname or IP, e.g. example.com" value="<?= $done ? e($host) : '' ?>" required>
                </div>
                <div class="col-md-3">
                    <select class="form-select" name="scope">
                        <option value="common"<?= $portsMode === 'common' ? ' selected' : '' ?>>Common (<?= count($portLists['common']) ?> ports)</option>
                        <option value="top20"<?= $portsMode === 'top20' ? ' selected' : '' ?>>Top 20 web ports</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <button class="btn btn-danger w-100" type="submit">Scan 🚀</button>
                </div>
            </div>
        </form>
    </div></div>

    <?php if ($error !== null): ?>
        <div class="alert alert-danger mt-4 reveal in-view"><?= e($error) ?></div>
    <?php endif; ?>

    <?php if ($done && $error === null && count($results) > 0): ?>
        <?php
        $open = count(array_filter($results, fn($r) => $r['state'] === 'open'));
        $closed = count(array_filter($results, fn($r) => $r['state'] === 'closed'));
        $filtered = count(array_filter($results, fn($r) => $r['state'] === 'filtered'));
        ?>
        <div class="d-flex justify-content-between align-items-center mt-4 mb-3 reveal in-view">
            <span class="small"><strong><?= e($host) ?></strong> → <code><?= e((string)$targetIp) ?></code></span>
            <span class="small text-secondary"><?= $open ?> open · <?= $closed ?> closed · <?= $filtered ?> filtered</span>
        </div>
        <div class="card reveal in-view"><div class="table-responsive">
            <table class="table table-sm table-dark align-middle mb-0">
                <thead><tr><th>Port</th><th>Service</th><th>State</th></tr></thead>
                <tbody>
                <?php foreach ($results as $r): ?>
                    <tr>
                        <td><code><?= (int)$r['port'] ?></code></td>
                        <td class="small"><?= e((string)$r['service']) ?></td>
                        <td>
                            <?php if ($r['state'] === 'open'): ?>
                                <span class="badge bg-success">open</span>
                            <?php elseif ($r['state'] === 'closed'): ?>
                                <span class="badge bg-secondary">closed</span>
                            <?php else: ?>
                                <span class="badge bg-warning text-dark">filtered</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div></div>
        <p class="text-secondary small mt-2 mb-0">"Filtered" means the host did not answer in time — a firewall dropping packets looks closed too. Upload the results to a paste if you want to keep them.</p>
    <?php elseif ($done && $error === null): ?>
        <div class="alert alert-secondary mt-4 reveal in-view">No ports were probed (unexpected). Try again.</div>
    <?php endif; ?>
</div>
<?php page_footer(); ?>

<?php
/**
 * Probe a single TCP port via the Cloudflare Worker proxy.
 */
function worker_tcp_probe(string $bridgeUrl, string $host, int $port, int $timeoutMs = 3000): array {
    $ch = curl_init($bridgeUrl . '/proxy/tcp');
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode(['host' => $host, 'port' => $port, 'timeout_ms' => $timeoutMs]),
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_CONNECTTIMEOUT => 5,
    ]);
    $resp = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);

    if ($code === 200 && $resp !== false) {
        $data = json_decode((string)$resp, true);
        if (is_array($data)) {
            return [
                'port'    => $port,
                'state'   => $data['state'] ?? 'filtered',
                'service' => $data['service'] ?? ((string)getservbyport($port, 'tcp')),
            ];
        }
    }
    return ['port' => $port, 'state' => 'filtered', 'service' => ''];
}