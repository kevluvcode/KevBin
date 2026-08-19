<?php
require_once __DIR__ . '/functions.php';

start_session();
$cfg = $GLOBALS['CFG'];
$me = current_user();
$manageKey = trim((string)($_GET['m'] ?? ''));

// Create a new tracker.
$created = null;
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create') {
    csrf_verify_or_fail();
    if (!rate_limit_check('tracker', 8, 60)) {
        friendly_error('Rate limit reached: max 8 trackers per minute per IP.', 429);
    }
    $title = mb_substr(trim((string)($_POST['title'] ?? '')), 0, 120);
    try {
        $pdo = db();
        $code = generate_tracker_code(6);
        $manageKeyNew = $me !== null ? null : bin2hex(random_bytes(16));
        $pdo->prepare('INSERT INTO trackers (code, title, user_id, manage_key) VALUES (?, ?, ?, ?)')
            ->execute([$code, $title !== '' ? $title : null, $me !== null ? (int)$me['id'] : null, $manageKeyNew]);
        log_activity('tracker_create', $code . ($title !== '' ? ' "' . $title . '"' : ''));
        $created = [
            'code' => $code,
            'key' => $manageKeyNew,
            'pixel' => tracker_pixel_url($code),
        ];
    } catch (Throwable $t) {
        $error = 'Could not create tracker: ' . $t->getMessage();
    }
}

// Delete.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    csrf_verify_or_fail();
    $code = (string)($_POST['code'] ?? '');
    if (preg_match('/^[A-Za-z0-9]{3,16}$/', $code)) {
        $stmt = db()->prepare('SELECT id, user_id, manage_key FROM trackers WHERE code = ?');
        $stmt->execute([$code]);
        $row = $stmt->fetch();
        $own = $row && (($me !== null && (int)$row['user_id'] === (int)$me['id'])
            || ($me === null && $manageKey !== '' && hash_equals((string)$row['manage_key'], $manageKey)));
        if ($row && $own) {
            db()->prepare('DELETE FROM tracker_views WHERE tracker_id = ?')->execute([(int)$row['id']]);
            db()->prepare('DELETE FROM trackers WHERE id = ?')->execute([(int)$row['id']]);
            log_activity('tracker_delete', $code);
            flash_set('success', 'Tracker deleted.');
            redirect($manageKey !== '' ? 'trackers.php?m=' . urlencode($manageKey) : 'trackers.php');
        }
    }
    flash_set('error', 'Could not delete that tracker.');
    redirect($manageKey !== '' ? 'trackers.php?m=' . urlencode($manageKey) : 'trackers.php');
}

$myTrackers = [];
if ($me !== null) {
    try {
        $myTrackers = db()->query(
            'SELECT code, title, views, last_view, created_at
             FROM trackers WHERE user_id = ' . (int)$me['id'] . '
             ORDER BY created_at DESC LIMIT 200'
        )->fetchAll();
    } catch (Throwable $t) {
        $myTrackers = [];
    }
}

page_header('Trackers');
?>
<div class="container" style="max-width: 1000px;">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <h1 class="h4 mb-0 reveal in-view">👁 Trackers</h1>
    </div>

    <div class="alert alert-secondary reveal in-view">
        <strong>Analytics-style beacons.</strong> A tracker is a 1&times;1 invisible pixel URL you embed in a
        page, signature or email. Every time it loads, one visit is recorded — IP, user-agent, browser,
        OS, device, referrer and approximate location (via the same IP intel as our link tracker).
        Only track pages and recipients you are authorized to monitor; viewers are never logged beyond the
        visit record.
    </div>

    <div class="card reveal in-view"><div class="card-body">
        <h2 class="h6 mb-3">New tracker</h2>
        <?php if ($error): ?><div class="alert alert-danger py-2"><?= e($error) ?></div><?php endif; ?>
        <form method="post" class="row g-2 align-items-end">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="action" value="create">
            <div class="col-md-7">
                <label class="form-label small text-secondary mb-1">Label (optional)</label>
                <input class="form-control" name="title" maxlength="120" placeholder="e.g. Newsletter issue 12 open tracking">
            </div>
            <div class="col-md-3">
                <button class="btn btn-primary w-100" type="submit">+ Create tracker</button>
            </div>
        </form>
        <?php if ($created): ?>
            <div class="alert alert-success mt-3 mb-0">
                <strong>Tracker ready.</strong> Paste this anywhere an image is allowed:
                <div class="input-group mt-2">
                    <input class="form-control" readonly id="tr-pixel" value="<?= e('<img src="' . $created['pixel'] . '" width="1" height="1" alt="" />') ?>">
                    <button class="btn btn-outline-light" type="button" onclick="copyPixel()">Copy</button>
                </div>
                <div class="small text-secondary mt-2">Direct URL: <code><?= e($created['pixel']) ?></code></div>
                <?php if ($created['key']): ?>
                    <div class="small mt-2">Your anonymous manage link (keeps the tracker tied to you):
                    <code><?= e($cfg['base_url'] . 'trackers.php?m=' . $created['key']) ?></code></div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div></div>

    <?php if ($me === null && $manageKey === ''): ?>
        <div class="card reveal">
            <div class="card-body text-center py-5">
                <h2 class="h5 mb-3">Manage your trackers</h2>
                <p class="text-secondary mb-4">Create a tracker above as a guest and you'll get a private
                manage link. Otherwise <a class="text-info" href="login.php">log in</a> and your trackers
                live here permanently.</p>
                <a class="btn btn-primary" href="login.php">Log in</a>
            </div>
        </div>
    <?php else: ?>
        <div class="card reveal"><div class="card-body">
            <h2 class="h6 mb-3"><?= $me !== null ? 'Your trackers' : 'Trackers (manage key)' ?></h2>
            <?php
            $rows = $myTrackers;
            if ($me === null && $manageKey !== '') {
                $stmt = db()->prepare('SELECT code, title, views, last_view, created_at FROM trackers WHERE manage_key = ? ORDER BY created_at DESC LIMIT 100');
                $stmt->execute([$manageKey]);
                $rows = $stmt->fetchAll();
            } ?>
            <?php if (count($rows) === 0): ?>
                <p class="text-secondary small mb-0">No trackers yet. Create your first one above.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-dark table-hover align-middle">
                        <thead><tr><th>Code</th><th>Label</th><th>Views</th><th>Last view</th><th></th></tr></thead>
                        <tbody>
                        <?php foreach ($rows as $t): ?>
                            <tr>
                                <td><a href="trackers_info.php?code=<?= e($t['code']) ?><?= $me === null ? '&m=' . e($manageKey) : '' ?>"><code><?= e($t['code']) ?></code></a></td>
                                <td class="small"><?= e($t['title'] !== null ? $t['title'] : '—') ?></td>
                                <td><?= (int)$t['views'] ?></td>
                                <td class="small"><?= $t['last_view'] ? e(gmdate('Y-m-d H:i', strtotime($t['last_view'] . ' UTC'))) . ' UTC' : '—' ?></td>
                                <td class="text-end">
                                    <a class="btn btn-sm btn-outline-light" href="trackers_info.php?code=<?= e($t['code']) ?><?= $me === null ? '&m=' . e($manageKey) : '' ?>">Details</a>
                                    <form method="post" action="trackers.php<?= $me === null ? '?m=' . e($manageKey) : '' ?>" onsubmit="return confirm('Delete this tracker and all its visit data?');" class="d-inline">
                                        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="code" value="<?= e($t['code']) ?>">
                                        <button class="btn btn-sm btn-outline-danger">Delete</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div></div>
    <?php endif; ?>
</div>
<script>
function copyPixel() { var t = document.getElementById('tr-pixel'); t.select(); if (navigator.clipboard) navigator.clipboard.writeText(t.value); else document.execCommand('copy'); }
</script>
<?php page_footer(); ?>