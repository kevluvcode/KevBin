<?php
require_once __DIR__ . '/functions.php';

start_session();
$cfg = $GLOBALS['CFG'];
$me = current_user();
$manageKey = trim((string)($_GET['m'] ?? ''));

$code = (string)($_GET['code'] ?? '');
if (!preg_match('/^[A-Za-z0-9]{3,16}$/', $code)) {
    friendly_error('Tracker not found.', 404);
}

$tracker = null;
// Access: logged-in owner, or manage key.
if ($me !== null) {
    $stmt = db()->prepare('SELECT * FROM trackers WHERE code = ? AND user_id = ?');
    $stmt->execute([$code, (int)$me['id']]);
    $tracker = $stmt->fetch();
} elseif ($manageKey !== '') {
    $stmt = db()->prepare('SELECT * FROM trackers WHERE code = ? AND manage_key = ?');
    $stmt->execute([$code, $manageKey]);
    $tracker = $stmt->fetch();
}
if (!$tracker) {
    friendly_error('Tracker not found.', 404);
}

$tid = (int)$tracker['id'];
$views = [];
try {
    $stmt = db()->prepare(
        'SELECT ip, ua, referer, country, region, city, isp, asn,
                is_proxy, is_vpn, is_tor, is_crawler,
                browser, browser_version, os, os_version, device, is_bot, language, created_at
         FROM tracker_views WHERE tracker_id = ? ORDER BY created_at DESC LIMIT 250'
    );
    $stmt->execute([$tid]);
    $views = $stmt->fetchAll();
} catch (Throwable $t) {
    $views = [];
}

$stats = ['top_countries' => [], 'top_browsers' => [], 'top_devices' => [], 'top_isp' => [], 'bots' => 0];
try {
    foreach ([
        'top_countries' => ['country', 6],
        'top_browsers' => ['browser', 6],
        'top_devices' => ['device', 5],
        'top_isp' => ['isp', 5],
    ] as $key => [$col, $limit]) {
        $s = db()->prepare("SELECT $col AS k, COUNT(*) AS c FROM tracker_views WHERE tracker_id = ? AND $col IS NOT NULL GROUP BY $col ORDER BY c DESC LIMIT " . (int)$limit);
        $s->execute([$tid]);
        $stats[$key] = $s->fetchAll();
    }
    $s = db()->prepare('SELECT COALESCE(SUM(is_bot),0) AS bots, COUNT(*) AS total FROM tracker_views WHERE tracker_id = ?');
    $s->execute([$tid]);
    $row = $s->fetch();
    $stats['bots'] = $row ? (int)$row['bots'] : 0;
} catch (Throwable $t) {
}

$pixel = tracker_pixel_url($code);
$backParams = $me !== null ? 'trackers.php' : 'trackers.php?m=' . urlencode($manageKey);

function tv_row(mixed $v): string
{
    return $v !== null && $v !== '' ? (string)$v : '—';
}

page_header('Tracker details');
?>
<style>
.li-badge { display:inline-block; padding:1px 7px; border-radius:5px; font-size:.68rem; font-weight:600; margin:1px; text-transform:uppercase; letter-spacing:.3px; }
.li-stat { text-align:center; padding:.9rem .4rem; background:rgba(255,255,255,.03); border:1px solid var(--line); border-radius:10px; }
.li-stat .v { font-size:1.35rem; font-weight:700; }
.li-stat .l { font-size:.72rem; color:var(--dim); text-transform:uppercase; letter-spacing:.5px; }
</style>
<div class="container" style="max-width: 1250px;">
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <h1 class="h4 mb-0 reveal in-view">👁 Tracker <code><?= e($code) ?></code></h1>
        <a class="btn btn-outline-light btn-sm reveal in-view" href="<?= e($backParams) ?>">← Back</a>
    </div>

    <div class="card mb-3 reveal in-view"><div class="card-body py-2">
        <div class="small text-secondary">Embed this pixel (1&times;1, invisible):</div>
        <div class="input-group mt-1">
            <input class="form-control form-control-sm" readonly value="<?= e('<img src="' . $pixel . '" width="1" height="1" alt="" />') ?>">
            <button class="btn btn-outline-light btn-sm" type="button" onclick="var t=this.previousElementSibling;t.select();navigator.clipboard&&navigator.clipboard.writeText(t.value);">Copy</button>
        </div>
    </div></div>

    <div class="row g-3 mb-3 reveal in-view">
        <div class="col-3"><div class="li-stat"><div class="v"><?= (int)$tracker['views'] ?></div><div class="l">Views</div></div></div>
        <div class="col-3"><div class="li-stat"><div class="v"><?= count($views) ?></div><div class="l">Detailed records</div></div></div>
        <div class="col-3"><div class="li-stat"><div class="v text-danger"><?= (int)$stats['bots'] ?></div><div class="l">Suspected bots</div></div></div>
        <div class="col-3"><div class="li-stat"><div class="v"><?= $tracker['last_view'] ? e(gmdate('Y-m-d H:i', strtotime($tracker['last_view'] . ' UTC'))) : '—' ?></div><div class="l">Last view (UTC)</div></div></div>
    </div>

    <div class="row g-3 mb-3 reveal in-view">
        <?php
        $tiles = [
            'Countries' => $stats['top_countries'],
            'Browsers' => $stats['top_browsers'],
            'Devices' => $stats['top_devices'],
            'ISPs' => $stats['top_isp'],
        ];
        foreach ($tiles as $label => $rows): ?>
            <div class="col-md-6 col-lg-3">
                <div class="card h-100"><div class="card-body">
                    <h2 class="h6 mb-2"><?= e($label) ?></h2>
                    <?php if (count($rows) === 0): ?>
                        <p class="text-secondary small mb-0">—</p>
                    <?php else: ?>
                        <table class="table table-sm tbl mb-0 small">
                            <?php foreach ($rows as $r): ?>
                                <tr><td class="border-0 py-1"><?= e(tv_row($r['k'])) ?></td>
                                    <td class="border-0 py-1 text-end"><?= (int)$r['c'] ?></td></tr>
                            <?php endforeach; ?>
                        </table>
                    <?php endif; ?>
                </div></div>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="card reveal in-view"><div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-2">
            <h2 class="h6 mb-0">Visit log</h2>
        </div>
        <div class="table-responsive">
            <table class="table table-dark table-hover align-middle">
                <thead><tr>
                    <th>When (UTC)</th><th>IP</th><th>Location / ISP</th><th>Device</th><th>Browser / OS</th><th>Flags</th><th>Referer</th>
                </tr></thead>
                <tbody>
                <?php if (count($views) === 0): ?>
                    <tr><td colspan="7" class="text-secondary small">No visits recorded yet. Embed the pixel and reload the page it lives on.</td></tr>
                <?php endif; ?>
                <?php foreach ($views as $v): ?>
                    <tr>
                        <td class="small text-nowrap"><?= e(gmdate('Y-m-d H:i:s', strtotime($v['created_at'] . ' UTC'))) ?></td>
                        <td class="small"><code><?= e(tv_row($v['ip'])) ?></code></td>
                        <td class="small">
                            <?php
                            $parts = [];
                            if ($v['city']) $parts[] = $v['city'];
                            if ($v['region']) $parts[] = $v['region'];
                            if ($v['country']) $parts[] = $v['country'];
                            echo e(implode(', ', $parts) ?: '—');
                            ?>
                            <?php if ($v['isp']): ?><div class="text-secondary" style="font-size:.72rem"><?= e($v['isp']) ?><?= $v['asn'] ? ' · AS' . e((string)$v['asn']) : '' ?></div><?php endif; ?>
                        </td>
                        <td class="small"><?= e(tv_row($v['device'])) ?></td>
                        <td class="small"><?= e(tv_row($v['browser'])) ?><?= $v['browser_version'] ? ' ' . e($v['browser_version']) : '' ?><div class="text-secondary" style="font-size:.72rem"><?= e(tv_row($v['os'])) ?><?= $v['os_version'] ? ' ' . e($v['os_version']) : '' ?></div></td>
                        <td>
                            <?php
                            if ((int)$v['is_proxy']) echo '<span class="li-badge" style="background:#ffc1071a;border:1px solid #ffc10766;color:#ffc107">Proxy</span>';
                            if ((int)$v['is_vpn']) echo '<span class="li-badge" style="background:#e67e221a;border:1px solid #e67e2266;color:#e67e22">VPN</span>';
                            if ((int)$v['is_tor']) echo '<span class="li-badge" style="background:#e74c3c1a;border:1px solid #e74c3c66;color:#e74c3c">TOR</span>';
                            if ((int)$v['is_bot']) echo '<span class="li-badge" style="background:#5865f21a;border:1px solid #5865f266;color:#8b93ff">Bot</span>';
                            ?>
                        </td>
                        <td class="small text-secondary" style="max-width:220px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="<?= e((string)$v['referer']) ?>"><?= e(tv_row($v['referer'])) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div></div>
</div>
<?php page_footer(); ?>