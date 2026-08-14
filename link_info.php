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
if ((int)$link['tracking'] === 1) {
    try {
        $stmt = db()->prepare(
            'SELECT ip, ua, referer, country, region, city, created_at
             FROM link_clicks WHERE link_id = ? ORDER BY created_at DESC LIMIT 200'
        );
        $stmt->execute([(int)$link['id']]);
        $clicks = $stmt->fetchAll();
        $clickTotal = (int)db()->query('SELECT COUNT(*) FROM link_clicks WHERE link_id = ' . (int)$link['id'])->fetchColumn();
    } catch (Throwable $t) {
        $clicks = [];
    }
}

$shortUrl = link_short_url($code);
page_header('Link details');
?>
<div class="container" style="max-width: 1100px;">
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
                                <th>Time (UTC)</th><th>IP</th><th>Location</th><th>Device / browser</th><th>Referrer</th>
                            </tr></thead>
                            <tbody>
                            <?php foreach ($clicks as $c): ?>
                                <tr>
                                    <td class="small text-nowrap"><?= e(gmdate('Y-m-d H:i:s', strtotime($c['created_at'] . ' UTC'))) ?></td>
                                    <td><code><?= e((string)$c['ip']) ?></code></td>
                                    <td class="small">
                                        <?php if (!empty($c['city']) || !empty($c['country'])): ?>
                                            <?= e(trim(implode(', ', array_filter([$c['city'], $c['region'], $c['country']])))) ?>
                                        <?php else: ?><span class="text-secondary">unknown</span><?php endif; ?>
                                    </td>
                                    <td class="small text-secondary" style="max-width:260px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="<?= e((string)$c['ua']) ?>">
                                        <?= $c['ua'] ? e(mb_substr((string)$c['ua'], 0, 60)) : '—' ?>
                                    </td>
                                    <td class="small text-secondary" style="max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="<?= e((string)$c['referer']) ?>">
                                        <?= $c['referer'] ? e(mb_substr((string)$c['referer'], 0, 50)) : 'direct' ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <p class="text-secondary small mt-2 mb-0">Location comes from the visitor's ISP data and is approximate by nature. Click logs are deleted when the link is deleted.</p>
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