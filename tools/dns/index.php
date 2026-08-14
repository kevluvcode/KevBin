<?php
require_once __DIR__ . '/../../functions.php';

start_session();
$host = trim((string)($_GET['q'] ?? $_POST['host'] ?? ''));
$records = [];

if ($host !== '' && preg_match('/^(?!-)(?:[a-zA-Z0-9-]{1,63}\.)+[a-zA-Z]{2,63}$/', $host)) {
    $labels = ['A' => 'IPv4 address', 'AAAA' => 'IPv6 address', 'CNAME' => 'Canonical name', 'MX' => 'Mail exchanger', 'NS' => 'Name servers', 'TXT' => 'Text records', 'SOA' => 'Start of authority', 'CAA' => 'Certification authority auth', 'PTR' => 'Reverse pointer'];
    foreach ($labels as $type => $label) {
        try {
            $r = @dns_get_record($host, constant('DNS_' . $type));
            if (is_array($r) && count($r) > 0) {
                $records[$type] = ['label' => $label, 'rows' => $r];
            }
        } catch (Throwable $t) {
            // DNS_* constant missing on this host — skip.
        }
    }
    log_activity('tool_dns', $host);
}

page_header('DNS Lookup');
?>
<div class="container" style="max-width: 1000px;">
    <h1 class="h4 mb-1 reveal in-view">🌐 DNS Lookup</h1>
    <p class="text-secondary mb-4 reveal in-view">Resolve A / AAAA / CNAME / MX / NS / TXT / SOA / CAA / PTR records for any domain. Results come straight from the PHP resolver on this server.</p>

    <div class="card mb-4 reveal in-view"><div class="card-body">
        <form method="get" action="index.php" class="row g-2 align-items-center">
            <div class="col-md-8">
                <input class="form-control" name="q" maxlength="253" placeholder="example.com" value="<?= e($host) ?>">
            </div>
            <div class="col-md-4">
                <button class="btn btn-primary w-100">Look up</button>
            </div>
        </form>
        <?php if ($host !== '' && !preg_match('/^(?!-)(?:[a-zA-Z0-9-]{1,63}\.)+[a-zA-Z]{2,63}$/', $host)): ?>
            <div class="alert alert-danger small mt-3 mb-0">That does not look like a valid domain name.</div>
        <?php endif; ?>
    </div></div>

    <?php if ($host !== '' && count($records) === 0): ?>
        <div class="alert alert-secondary">No public records found (or the DNS extension is disabled on this host). Try <code>info.example.com</code> or a real domain.</div>
    <?php endif; ?>

    <?php foreach ($records as $type => $info): ?>
        <div class="card mb-3 reveal">
            <div class="card-body">
                <h2 class="h6 mb-2"><?= e($type) ?> — <?= e($info['label']) ?> (<?= count($info['rows']) ?>)</h2>
                <table class="table table-sm table-dark align-middle mb-0">
                    <tbody>
                    <?php foreach ($info['rows'] as $r): ?>
                        <tr>
                            <?php if ($type === 'A'): ?><td><code><?= e((string)($r['ip'] ?? '')) ?></code></td>
                            <?php elseif ($type === 'AAAA'): ?><td><code><?= e((string)($r['ipv6'] ?? '')) ?></code></td>
                            <?php elseif ($type === 'MX'): ?><td><code><?= e((string)($r['target'] ?? '')) ?></code> (priority <?= (int)($r['pri'] ?? 0) ?>)</td>
                            <?php elseif ($type === 'NS'): ?><td><code><?= e((string)($r['target'] ?? '')) ?></code></td>
                            <?php elseif ($type === 'CNAME'): ?><td><code><?= e((string)($r['target'] ?? '')) ?></code></td>
                            <?php elseif ($type === 'TXT'): ?><td class="small" style="word-break:break-all;"><?= e((string)($r['txt'] ?? $r['entries'][0] ?? '')) ?></td>
                            <?php elseif ($type === 'SOA'): ?><td class="small">mname <?= e((string)($r['mname'] ?? '')) ?> · rname <?= e((string)($r['rname'] ?? '')) ?> · serial <?= (int)($r['serial'] ?? 0) ?></td>
                            <?php elseif ($type === 'CAA'): ?><td class="small"><?= e((string)($r['tag'] ?? '')) ?> "<?= e((string)($r['value'] ?? '')) ?>" (flag <?= (int)($r['flags'] ?? 0) ?>)</td>
                            <?php elseif ($type === 'PTR'): ?><td><code><?= e((string)($r['target'] ?? '')) ?></code></td>
                            <?php else: ?><td class="small"><?= e(substr(json_encode($r), 0, 200)) ?></td><?php endif; ?>
                            <td class="text-secondary small text-nowrap">ttl <?= (int)($r['ttl'] ?? 0) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endforeach; ?>
</div>
<?php page_footer(); ?>