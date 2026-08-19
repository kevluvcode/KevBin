<?php
require_once __DIR__ . '/functions.php';

start_session();
$cfg = $GLOBALS['CFG'];
$me = current_user();
$manageKey = trim((string)($_GET['m'] ?? ''));

$code = (string)($_GET['code'] ?? '');
if (!preg_match('/^[A-Za-z0-9]{3,16}$/', $code)) {
    friendly_error('Link not found.', 404);
}

$stmt = db()->prepare(
    'SELECT id, code, target_url, title, tracking, clicks, last_click, created_at, user_id, manage_key
     FROM links WHERE code = ?'
);
$stmt->execute([$code]);
$link = $stmt->fetch();

if (!$link || !link_manage_ok($link, $me, $manageKey)) {
    friendly_error('You do not have permission to view this link.', 403);
}

// Delete action.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify_or_fail();
    db()->prepare('DELETE FROM link_clicks WHERE link_id = ?')->execute([(int)$link['id']]);
    db()->prepare('DELETE FROM links WHERE id = ?')->execute([(int)$link['id']]);
    log_activity('link_delete', $code);
    flash_set('success', 'Link deleted.');
    if ($me !== null) {
        redirect('links.php');
    }
    redirect('links.php?m=' . urlencode($manageKey));
}

$clicks = [];
$clickTotal = 0;
$lid = (int)$link['id'];

if ((int)$link['tracking'] === 1) {
    try {
        $stmt = db()->prepare(
            'SELECT ip, ua, referer, country, region, city, isp, asn,
                    is_proxy, is_vpn, is_tor, is_crawler, lat, lon,
                    browser, browser_version, os, os_version, device, is_bot,
                    language, screen, timezone, dpr, touch, hw_concurrency, fp, created_at
             FROM link_clicks WHERE link_id = ? ORDER BY created_at DESC LIMIT 200'
        );
        $stmt->execute([$lid]);
        $clicks = $stmt->fetchAll();
        $clickTotal = (int)db()->query('SELECT COUNT(*) FROM link_clicks WHERE link_id = ' . $lid)->fetchColumn();
    } catch (Throwable $t) {
        $clicks = [];
    }
}

$stats = ['top_countries' => [], 'top_browsers' => [], 'top_devices' => [], 'top_isp' => [], 'hourly' => [], 'bots' => 0];
if ((int)$link['tracking'] === 1 && $clickTotal > 0) {
    try {
        foreach ([
            'top_countries' => ['country', 6],
            'top_browsers' => ['browser', 6],
            'top_devices' => ['device', 5],
            'top_isp' => ['isp', 5],
        ] as $key => [$col, $limit]) {
            $s = db()->prepare("SELECT $col AS k, COUNT(*) AS c FROM link_clicks WHERE link_id = ? AND $col IS NOT NULL GROUP BY $col ORDER BY c DESC LIMIT " . (int)$limit);
            $s->execute([$lid]);
            $stats[$key] = $s->fetchAll();
        }
        $s = db()->prepare("SELECT DATE_FORMAT(created_at, '%Y-%m-%d %H:00') AS h, COUNT(*) AS c FROM link_clicks WHERE link_id = ? AND created_at > DATE_SUB(UTC_TIMESTAMP(), INTERVAL 7 DAY) GROUP BY h ORDER BY h ASC LIMIT 168");
        $s->execute([$lid]);
        $stats['hourly'] = $s->fetchAll();
        $s = db()->prepare('SELECT COALESCE(SUM(is_bot),0) AS bots, COUNT(*) AS total FROM link_clicks WHERE link_id = ?');
        $s->execute([$lid]);
        $row = $s->fetch();
        $stats['bots'] = $row ? (int)$row['bots'] : 0;
    } catch (Throwable $t) {
        // aggregates are non-fatal
    }
}

$shortUrl = link_short_url($code);

function li_row(mixed $v): string
{
    return $v !== null && $v !== '' ? (string)$v : '—';
}

function li_badge(int $on, string $label, string $color): string
{
    if (!$on) {
        return '';
    }
    return '<span class="li-badge" style="background:' . $color . '1a;border:1px solid ' . $color . '66;color:' . $color . '">' . $label . '</span>';
}

page_header('Link details');
?>
<style>
.li-badge { display:inline-block; padding:1px 7px; border-radius:5px; font-size:.68rem; font-weight:600; margin:1px; text-transform:uppercase; letter-spacing:.3px; }
.li-stat { text-align:center; padding:.9rem .4rem; background:rgba(255,255,255,.03); border:1px solid var(--line); border-radius:10px; }
.li-stat .v { font-size:1.35rem; font-weight:700; }
.li-stat .k { font-size:.7rem; color:var(--dim); text-transform:uppercase; letter-spacing:.8px; margin-top:2px; }
.li-bar { background:rgba(255,255,255,.06); border-radius:4px; height:8px; overflow:hidden; margin-top:3px; }
.li-bar > div { height:100%; background:linear-gradient(90deg,var(--accent1),var(--accent2)); }
.li-fp pre { max-height:220px; overflow:auto; font-size:.72rem; }
.li-hours { display:flex; align-items:flex-end; gap:2px; height:64px; }
.li-hours div { flex:1; background:rgba(88,101,242,.5); border-radius:2px 2px 0 0; position:relative; }
.li-hours div:hover { background:#6872f2; }
.li-hours div span { position:absolute; bottom:calc(100% + 3px); left:50%; transform:translateX(-50%); font-size:.6rem; color:var(--dim); white-space:nowrap; display:none; }
.li-hours div:hover span { display:block; }
.dev-ico { font-size:1.05rem; margin-right:.3rem; }
</style>
<div class="container" style="max-width: 1200px;">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <h1 class="h4 mb-0">📍 Link details — <code>/s/<?= e($code) ?></code></h1>
        <div class="d-flex gap-2">
            <a class="btn btn-outline-light btn-sm" href="<?= e($me !== null ? 'links.php' : 'links.php?m=' . urlencode($manageKey)) ?>">← Back</a>
            <form method="post" action="link_info.php?code=<?= e($code) ?><?= $manageKey !== '' ? '&m=' . urlencode($manageKey) : '' ?>"
                onsubmit="return confirm('Delete this link and all its click data?');">
                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                <button class="btn btn-danger btn-sm">Delete link</button>
            </form>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-md-4">
            <div class="card"><div class="card-body">
                <h2 class="h6 mb-2">Short link</h2>
                <div class="input-group input-group-sm mb-3">
                    <input id="li-short" class="form-control" readonly value="<?= e($shortUrl) ?>">
                    <button class="btn btn-outline-light" onclick="copyLi()">Copy</button>
                </div>
                <?php if ($link['title'] !== null): ?>
                    <div class="text-secondary small mb-1">Title: <strong><?= e($link['title']) ?></strong></div>
                <?php endif; ?>
                <div class="text-secondary small">Created: <?= e(gmdate('Y-m-d H:i', strtotime($link['created_at'] . ' UTC'))) ?> UTC</div>
                <div class="text-secondary small">Tracking: <strong><?= (int)$link['tracking'] === 1 ? 'ON' : 'OFF' ?></strong></div>
            </div></div>
        </div>
        <div class="col-md-4">
            <div class="card"><div class="card-body text-center">
                <div class="text-secondary small">Total clicks</div>
                <div style="font-size:2.4rem;font-weight:700;"><?= number_format((int)$link['clicks']) ?></div>
                <div class="text-secondary small">Last: <?= $link['last_click'] ? e(gmdate('Y-m-d H:i', strtotime($link['last_click'] . ' UTC'))) . ' UTC' : 'never' ?></div>
            </div></div>
        </div>
        <div class="col-md-4">
            <div class="card"><div class="card-body">
                <h2 class="h6 mb-2">Redirects to</h2>
                <div class="small" style="word-break:break-all;"><?= e($link['target_url']) ?></div>
                <a class="btn btn-sm btn-primary mt-3" href="<?= e(link_short_url($code)) ?>" target="_blank" rel="noopener">Open short link</a>
            </div></div>
        </div>
    </div>

    <?php if ((int)$link['tracking'] === 1 && $clickTotal > 0): ?>
        <div class="row g-3 mb-4">
            <div class="col-md-3 col-6"><div class="li-stat"><div class="v"><?= number_format($clickTotal) ?></div><div class="k">Clicks</div></div></div>
            <div class="col-md-3 col-6"><div class="li-stat"><div class="v"><?= count($stats['top_countries']) ?></div><div class="k">Countries</div></div></div>
            <div class="col-md-3 col-6"><div class="li-stat"><div class="v"><?= count($stats['top_browsers']) ?></div><div class="k">Browsers</div></div></div>
            <div class="col-md-3 col-6"><div class="li-stat"><div class="v"><?= number_format($clickTotal > 0 ? round($stats['bots'] / $clickTotal * 100) : 0) ?>%</div><div class="k">Bots / automated</div></div></div>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-lg-3 col-md-6">
                <div class="card"><div class="card-body">
                    <h2 class="h6 mb-2">Top countries</h2>
                    <?php if (count($stats['top_countries']) === 0): ?><p class="text-secondary small mb-0">—</p><?php endif; ?>
                    <?php $mx = max(1, (int)($stats['top_countries'][0]['c'] ?? 1)); foreach ($stats['top_countries'] as $r): ?>
                        <div class="mb-2">
                            <div class="d-flex justify-content-between small"><span><?= e(li_row($r['k'])) ?></span><span><?= (int)$r['c'] ?></span></div>
                            <div class="li-bar"><div style="width:<?= round((int)$r['c'] / $mx * 100) ?>%"></div></div>
                        </div>
                    <?php endforeach; ?>
                </div></div>
            </div>
            <div class="col-lg-3 col-md-6">
                <div class="card"><div class="card-body">
                    <h2 class="h6 mb-2">Top browsers</h2>
                    <?php if (count($stats['top_browsers']) === 0): ?><p class="text-secondary small mb-0">—</p><?php endif; ?>
                    <?php $mx = max(1, (int)($stats['top_browsers'][0]['c'] ?? 1)); foreach ($stats['top_browsers'] as $r): ?>
                        <div class="mb-2">
                            <div class="d-flex justify-content-between small"><span><?= e(li_row($r['k'])) ?></span><span><?= (int)$r['c'] ?></span></div>
                            <div class="li-bar"><div style="width:<?= round((int)$r['c'] / $mx * 100) ?>%"></div></div>
                        </div>
                    <?php endforeach; ?>
                </div></div>
            </div>
            <div class="col-lg-3 col-md-6">
                <div class="card"><div class="card-body">
                    <h2 class="h6 mb-2">Top devices & ISPs</h2>
                    <div class="small text-secondary mb-1">Device</div>
                    <?php foreach ($stats['top_devices'] as $r): ?>
                        <div class="d-flex justify-content-between small mb-1"><span><?= e(li_row($r['k'])) ?></span><span><?= (int)$r['c'] ?></span></div>
                    <?php endforeach; ?>
                    <div class="small text-secondary mt-2 mb-1">ISP</div>
                    <?php foreach (array_slice($stats['top_isp'], 0, 3) as $r): ?>
                        <div class="d-flex justify-content-between small mb-1"><span style="max-width:80%;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= e(li_row($r['k'])) ?></span><span><?= (int)$r['c'] ?></span></div>
                    <?php endforeach; ?>
                </div></div>
            </div>
            <div class="col-lg-3 col-md-6">
                <div class="card"><div class="card-body">
                    <h2 class="h6 mb-2">Last 7 days (hourly)</h2>
                    <div class="li-hours">
                        <?php $mx = max(1, max(array_map(function ($r) { return (int)$r['c']; }, $stats['hourly']) ?: [1])); foreach ($stats['hourly'] as $r): ?>
                            <div style="height:<?= max(3, round((int)$r['c'] / $mx * 100)) ?>%"><span><?= e(substr((string)$r['h'], 11, 2) . ':00') ?></span></div>
                        <?php endforeach; ?>
                    </div>
                    <div class="text-secondary small mt-1"><?= count($stats['hourly']) ?> hours of activity</div>
                </div></div>
            </div>
        </div>
    <?php endif; ?>

    <?php if ((int)$link['tracking'] === 1): ?>
        <div class="card">
            <div class="card-body">
                <h2 class="h6 mb-3">Click log (<?= number_format($clickTotal) ?> total, showing <?= count($clicks) ?> newest)</h2>
                <?php if (count($clicks) === 0): ?>
                    <p class="text-secondary small mb-0">No clicks recorded yet. Share the short link and they'll show up here.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-dark table-hover align-middle">
                            <thead><tr>
                                <th>Time (UTC)</th><th>IP</th><th>Location</th><th>Network</th><th>Device</th><th>Signals</th><th>Referrer</th>
                            </tr></thead>
                            <tbody>
                            <?php foreach ($clicks as $c): ?>
                                <?php
                                $loc = array_filter([$c['city'], $c['region'], $c['country']]);
                                $locStr = trim(implode(', ', $loc));
                                $mapUrl = '';
                                if ($c['lat'] !== null && $c['lon'] !== null) {
                                    $mapUrl = 'https://www.google.com/maps?q=' . urlencode($c['lat'] . ',' . $c['lon']);
                                }
                                $refHost = $c['referer'] ? (parse_url((string)$c['referer'], PHP_URL_HOST) ?: $c['referer']) : '';
                                $ico = $c['device'] === 'Phone' ? '📱' : ($c['device'] === 'Tablet' ? '📟' : ($c['device'] === 'Bot' ? '🤖' : '🖥'));
                                $browserFull = trim(li_row($c['browser']) . ($c['browser_version'] ? ' ' . $c['browser_version'] : ''));
                                $osFull = trim(li_row($c['os']) . ($c['os_version'] ? ' ' . $c['os_version'] : ''));
                                ?>
                                <tr>
                                    <td class="small text-nowrap"><?= e(gmdate('Y-m-d H:i:s', strtotime($c['created_at'] . ' UTC'))) ?></td>
                                    <td><code><?= e((string)$c['ip']) ?></code></td>
                                    <td class="small">
                                        <?php if ($locStr !== ''): ?>
                                            <?= e($locStr) ?><?php if ($mapUrl): ?> <a href="<?= e($mapUrl) ?>" target="_blank" rel="noopener" class="small" title="Open in Google Maps">🗺</a><?php endif; ?>
                                        <?php else: ?><span class="text-secondary">unknown</span><?php endif; ?>
                                        <?= li_badge((int)$c['is_vpn'], 'VPN', '#ffc107') ?>
                                        <?= li_badge((int)$c['is_proxy'], 'proxy', '#ff9f43') ?>
                                        <?= li_badge((int)$c['is_tor'], 'Tor', '#e74c3c') ?>
                                        <?= li_badge((int)$c['is_crawler'], 'crawler', '#6872f2') ?>
                                    </td>
                                    <td class="small">
                                        <?= e(li_row($c['isp'])) ?>
                                        <?php if ($c['asn']): ?><div class="text-secondary" style="font-size:.72rem">AS<?= e((string)$c['asn']) ?></div><?php endif; ?>
                                    </td>
                                    <td class="small">
                                        <span class="dev-ico"><?= $ico ?></span><?= e($browserFull) ?>
                                        <div class="text-secondary" style="font-size:.72rem"><?= e($osFull) ?> · <?= e(li_row($c['device'])) ?><?= (int)$c['is_bot'] ? ' · bot' : '' ?></div>
                                    </td>
                                    <td class="small text-secondary" style="max-width:190px">
                                        <?php
                                        $sig = [];
                                        if ($c['screen']) $sig[] = '🖥 ' . $c['screen'];
                                        if ($c['timezone']) $sig[] = '🕐 ' . $c['timezone'];
                                        if ($c['language']) $sig[] = '🌐 ' . $c['language'];
                                        if ($c['dpr']) $sig[] = 'dpr ' . $c['dpr'];
                                        if ((int)$c['touch']) $sig[] = 'touch';
                                        if ($c['hw_concurrency']) $sig[] = $c['hw_concurrency'] . ' core';
                                        echo $sig ? implode('<br>', array_map('e', $sig)) : '—';
                                        ?>
                                        <?php if ($c['fp']): ?>
                                            <details class="mt-1"><summary class="small" style="cursor:pointer;color:var(--accent1)">fingerprint</summary><div class="li-fp"><pre class="text-secondary mb-0"><?= e((string)$c['fp']) ?></pre></div></details>
                                        <?php endif; ?>
                                    </td>
                                    <td class="small text-secondary" style="max-width:180px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="<?= e((string)$c['referer']) ?>">
                                        <?= $refHost !== '' ? e($refHost) : 'direct' ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <p class="text-secondary small mt-2 mb-0">Location comes from the visitor's ISP data and is approximate by nature. Screen / timezone / touch / canvas fields are collected by a fingerprint beacon and may be absent for bots, link previews and curl. Click logs are deleted when the link is deleted.</p>
                <?php endif; ?>
            </div>
        </div>
    <?php else: ?>
        <div class="alert alert-secondary">Click tracking is off for this link — only the total count is kept.</div>
    <?php endif; ?>
</div>
<script>
function copyLi() {
    var t = document.getElementById('li-short');
    t.select();
    if (navigator.clipboard) navigator.clipboard.writeText(t.value); else document.execCommand('copy');
}
</script>
<?php page_footer(); ?>