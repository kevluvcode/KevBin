<?php
require_once __DIR__ . '/functions.php';

start_session();
$cfg = $GLOBALS['CFG'];
$me = current_user();
$manageKey = trim((string)($_GET['m'] ?? ''));

// Delete action (logged-in owner or manage key).
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify_or_fail();
    $deleteCode = (string)($_POST['code'] ?? '');
    if (preg_match('/^[A-Za-z0-9]{3,16}$/', $deleteCode)) {
        $stmt = db()->prepare('SELECT id, user_id, manage_key FROM links WHERE code = ?');
        $stmt->execute([$deleteCode]);
        $row = $stmt->fetch();
        if ($row && link_manage_ok($row, $me, $manageKey)) {
            db()->prepare('DELETE FROM link_clicks WHERE link_id = ?')->execute([(int)$row['id']]);
            db()->prepare('DELETE FROM links WHERE id = ?')->execute([(int)$row['id']]);
            log_activity('link_delete', $deleteCode);
            flash_set('success', 'Link deleted.');
            redirect($manageKey !== '' ? 'links.php?m=' . urlencode($manageKey) : 'links.php', );
        }
    }
    flash_set('error', 'Could not delete that link.');
    redirect($manageKey !== '' ? 'links.php?m=' . urlencode($manageKey) : 'links.php');
}

$myLinks = [];
if ($me !== null) {
    try {
        $myLinks = db()->query(
            'SELECT code, target_url, title, tracking, clicks, last_click, created_at
             FROM links WHERE user_id = ' . (int)$me['id'] . '
             ORDER BY created_at DESC
             LIMIT 200'
        )->fetchAll();
    } catch (Throwable $t) {
        $myLinks = [];
    }
}

$keyedLink = null;
if ($manageKey !== '' && $me === null) {
    $keyedLink = ['code' => (string)($_GET['code'] ?? '')];
}

page_header('My links');
?>
<div class="container" style="max-width: 1000px;">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <h1 class="h4 mb-0 reveal in-view">📍 My links</h1>
        <a class="btn btn-primary btn-sm reveal in-view" href="short.php">+ New short link</a>
    </div>

    <?php if ($me === null && $manageKey === ''): ?>
        <div class="card reveal">
            <div class="card-body text-center py-5">
                <h2 class="h5 mb-3">Log in to manage your links</h2>
                <p class="text-secondary mb-4">Create a link anonymously from the
                <a class="text-info" href="short.php">shorten page</a> and you'll get a private
                manage link. Otherwise, <a class="text-info" href="login.php">log in</a> and your
                links live here permanently.</p>
                <div class="d-flex gap-2 justify-content-center flex-wrap">
                    <a class="btn btn-primary" href="login.php">Log in</a>
                    <a class="btn btn-outline-light" href="register.php">Register</a>
                    <a class="btn btn-outline-light" href="short.php">Shorten a link</a>
                </div>
            </div>
        </div>
    <?php elseif ($manageKey !== '' && $me === null): ?>
        <?php if ($manageKey !== ''): ?>
            <div class="alert alert-secondary">
                Anonymous manage mode — you're viewing links registered to your private manage key.
                Share <code><?= e($cfg['base_url'] . 'links.php?m=' . $manageKey) ?></code> nowhere;
                it grants delete rights.
            </div>
        <?php endif; ?>
        <div class="card reveal">
            <div class="card-body">
                <h2 class="h6 mb-3">Your links (manage key)</h2>
                <?php
                $stmt = db()->prepare('SELECT code, target_url, title, tracking, clicks, last_click, created_at, manage_key FROM links WHERE manage_key = ? ORDER BY created_at DESC LIMIT 100');
                $stmt->execute([$manageKey]);
                $rows = $stmt->fetchAll();
                ?>
                <?php if (count($rows) === 0): ?>
                    <p class="text-secondary small">No links under this manage key yet.</p>
                <?php else: ?>
                    <table class="table table-dark table-hover align-middle">
                        <thead><tr>
                            <th>Code</th><th>Target</th><th>Clicks</th><th>Last click</th><th></th>
                        </tr></thead>
                        <tbody>
                        <?php foreach ($rows as $l): ?>
                            <tr>
                                <td><a href="link_info.php?code=<?= e($l['code']) ?>&m=<?= e($manageKey) ?>"><code><?= e($l['code']) ?></code></a></td>
                                <td class="small text-secondary" style="max-width:300px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
                                    <?= e(mb_substr((string)$l['target_url'], 0, 60)) ?><?= mb_strlen((string)$l['target_url']) > 60 ? '…' : '' ?>
                                </td>
                                <td><?= (int)$l['clicks'] ?> <?= (int)$l['tracking'] === 1 ? '<span class="badge bg-primary">tracked</span>' : '' ?></td>
                                <td class="small"><?= $l['last_click'] ? e(gmdate('Y-m-d H:i', strtotime($l['last_click'] . ' UTC'))) . ' UTC' : '—' ?></td>
                                <td class="text-end">
                                    <form method="post" action="links.php?m=<?= e($manageKey) ?>" onsubmit="return confirm('Delete this link and all its click data?');" class="d-inline">
                                        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                                        <input type="hidden" name="code" value="<?= e($l['code']) ?>">
                                        <button class="btn btn-sm btn-outline-danger">Delete</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    <?php else: ?>
        <div class="card reveal">
            <div class="card-body">
                <h2 class="h6 mb-3">Your links</h2>
                <?php if (count($myLinks) === 0): ?>
                    <p class="text-secondary small mb-0">No links yet — create your first one with
                    the button above or the <a class="text-info" href="short.php">shorten page</a>.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-dark table-hover align-middle">
                            <thead><tr>
                                <th>Short link</th><th>Target</th><th>Clicks</th><th>Last click</th><th></th>
                            </tr></thead>
                            <tbody>
                            <?php foreach ($myLinks as $l): ?>
                                <tr>
                                    <td><a href="link_info.php?code=<?= e($l['code']) ?>"><code>/s/<?= e($l['code']) ?></code></a></td>
                                    <td class="small text-secondary" style="max-width:280px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
                                        <?= e(mb_substr((string)$l['target_url'], 0, 60)) ?><?= mb_strlen((string)$l['target_url']) > 60 ? '…' : '' ?>
                                    </td>
                                    <td>
                                        <?= (int)$l['clicks'] ?>
                                        <?php if ((int)$l['tracking'] === 1): ?><span class="badge bg-primary">tracked</span><?php endif; ?>
                                    </td>
                                    <td class="small"><?= $l['last_click'] ? e(gmdate('Y-m-d H:i', strtotime($l['last_click'] . ' UTC'))) . ' UTC' : '—' ?></td>
                                    <td class="text-end">
                                        <a class="btn btn-sm btn-outline-light" href="link_info.php?code=<?= e($l['code']) ?>">Details</a>
                                        <form method="post" action="links.php" onsubmit="return confirm('Delete this link and all its click data?');" class="d-inline">
                                            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                                            <input type="hidden" name="code" value="<?= e($l['code']) ?>">
                                            <button class="btn btn-sm btn-outline-danger">Delete</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</div>
<?php page_footer(); ?>