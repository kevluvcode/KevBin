<?php
require_once __DIR__ . '/functions.php';

start_session();

$id = (string)($_GET['id'] ?? '');
if (!preg_match('/^[A-Za-z0-9]{1,12}$/', $id)) {
    friendly_error('Paste not found.', 404);
}

$stmt = db()->prepare(
    'SELECT p.id, p.title, p.description, p.tags, p.password_hash, p.content, p.author, p.created_at, p.expires_at, p.views,
            p.edit_key, p.user_id, p.pin, p.paste_color
     FROM pastes p
     LEFT JOIN users u ON u.id = p.user_id
     WHERE p.id = ? AND (p.expires_at IS NULL OR p.expires_at > UTC_TIMESTAMP())'
);
$stmt->execute([$id]);
$paste = $stmt->fetch();

if (!$paste) {
    friendly_error('Paste not found or it has expired.', 404);
}

$cfg = $GLOBALS['CFG'];

// Password lock: content stays hidden until unlocked via session once per browser.
$locked = !empty($paste['password_hash']);
$unlocked = !empty($_SESSION['paste_unlocked'][$id]);
if ($locked && !$unlocked) {
    $lockError = null;
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['paste_password']) && csrf_verify()) {
        if (!rate_limit_check('paste_unlock', (int)($cfg['unlock_rate_limit'] ?? 20), (int)$cfg['rate_window_seconds'])) {
            friendly_error('Too many attempts. Wait a few minutes and try again.', 429);
        }
        if (password_verify((string)$_POST['paste_password'], $paste['password_hash'])) {
            $_SESSION['paste_unlocked'][$id] = true;
            $unlocked = true;
            redirect('view.php?id=' . urlencode($id));
        }
        $lockError = 'Wrong password. Try again.';
    }
    if (!$unlocked) {
        page_header('Locked paste — ' . $paste['title']);
        ?>
        <div class="container" style="max-width: 560px;">
            <div class="card">
                <div class="card-body">
                    <h1 class="h4 mb-1">🔒 This paste is password-protected</h1>
                    <p class="text-secondary mb-3">Enter the password to view "<?= e($paste['title']) ?>".
                    Once unlocked on this browser, you won't be asked again.</p>
                    <?php if ($lockError !== null): ?>
                        <div class="alert alert-danger"><?= e($lockError) ?></div>
                    <?php endif; ?>
                    <form method="post" action="view.php?id=<?= e($id) ?>">
                        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                        <input class="form-control mb-2" type="password" name="paste_password" maxlength="200"
                            autocomplete="current-password" placeholder="Password" autofocus required>
                        <div class="d-flex gap-2">
                            <button class="btn btn-primary" type="submit">Unlock</button>
                            <a class="btn btn-outline-light" href="index.php">Back</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <?php
        page_footer();
        exit;
    }
}

$owner = null;
if ($paste['user_id'] !== null) {
    $stmt = db()->prepare('SELECT id, username, profile_color, pfp, status FROM users WHERE id = ?');
    $stmt->execute([(int)$paste['user_id']]);
    $owner = $stmt->fetch() ?: null;
}

$me = current_user();
$myPin = false;
if ($me !== null) {
    $pinStmt = db()->prepare('SELECT 1 FROM pins WHERE user_id = ? AND paste_id = ?');
    $pinStmt->execute([(int)$me['id'], $id]);
    $myPin = $pinStmt->fetch() !== false;
}

$voteSummary = vote_summary('paste', $id);
$comments = fetch_comments('paste', $id);
$myCommentVote = $me !== null ? my_vote('paste', $id, (int)$me['id']) : 0;

// SEO / Discord / social metadata
$pasteDesc = trim((string)$paste['description']);
if ($pasteDesc === '') {
    $pasteDesc = trim(preg_replace('/\s+/', ' ', (string)$paste['content']));
    $pasteDesc = mb_substr($pasteDesc, 0, 160);
}
set_meta([
    'title' => $paste['title'],
    'description' => $pasteDesc,
    'keywords' => (string)$paste['tags'],
    'type' => 'article',
    'url' => $cfg['base_url'] . 'view.php?id=' . urlencode($id),
]);

if (isset($_GET['raw'])) {
    header('Content-Type: text/plain; charset=utf-8');
    echo $paste['content'] . watermark_text($paste['id']);
    exit;
}

db()->prepare('UPDATE pastes SET views = views + 1 WHERE id = ?')->execute([$id]);

page_header($paste['title']);
?>
<div class="container" style="max-width: 900px;">
    <div class="d-flex justify-content-between align-items-start mb-3 flex-wrap gap-2">
        <div>
            <h1 class="h4 mb-1"><?= e($paste['title']) ?>
                <?php if ($paste['pin']): ?><span class="badge bg-primary">PINNED</span><?php endif; ?>
                <?php if ($locked): ?><span class="badge bg-warning text-dark">🔒 LOCKED</span><?php endif; ?>
            </h1>
            <div class="text-secondary">
                by
                <?php if ($owner): ?>
                    <a class="link-light"
                        style="color:<?= e(clean_hex_color($owner['profile_color']) !== '' ? clean_hex_color($owner['profile_color']) : '#ffffff') ?>"
                        href="profile.php?id=<?= (int)$owner['id'] ?>"><?= e($owner['username']) ?></a>
                <?php else: ?>
                    <?= e($paste['author']) ?>
                <?php endif; ?>
                · <?= e(gmdate('Y-m-d H:i', strtotime($paste['created_at'] . ' UTC'))) ?> UTC ·
                <?= (int)$paste['views'] ?> views ·
                <?= count($comments) ?> comments
                <?php if ($paste['expires_at']): ?>
                    · expires <?= e(gmdate('Y-m-d H:i', strtotime($paste['expires_at'] . ' UTC'))) ?> UTC
                <?php endif; ?>
            </div>
            <?php if (!empty($paste['description'])): ?>
                <div class="mt-1 small text-secondary"><?= e($paste['description']) ?></div>
            <?php endif; ?>
            <?php if (!empty($paste['tags'])): ?>
                <div class="mt-1">
                    <?php foreach (array_filter(array_map('trim', explode(',', (string)$paste['tags']))) as $tag): ?>
                        <span class="badge bg-primary-subtle text-primary-emphasis me-1">#<?= e($tag) ?></span>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <?= vote_buttons('paste', $id, $myCommentVote, $voteSummary, 'view.php?id=' . urlencode($id)) ?>
            <a class="btn btn-outline-light btn-sm" href="view.php?id=<?= e($paste['id']) ?>&raw=1">Raw</a>
            <?php if ($me !== null): ?>
                <form method="post" action="pin.php">
                    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="paste_id" value="<?= e($paste['id']) ?>">
                    <button class="btn btn-sm <?= $myPin ? 'btn-warning' : 'btn-outline-warning' ?>" type="submit">
                        <?= $myPin ? 'Unpin' : 'Pin' ?>
                    </button>
                </form>
            <?php endif; ?>
            <?php if (is_admin()): ?>
                <form method="post" action="admin.php#pastes">
                    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="action" value="<?= $paste['pin'] ? 'pasteunpin' : 'pastepin' ?>">
                    <input type="hidden" name="paste_id" value="<?= e($paste['id']) ?>">
                    <button class="btn btn-sm btn-outline-primary" type="submit">
                        <?= $paste['pin'] ? 'Unpin (featured)' : 'Feature pin' ?>
                    </button>
                </form>
                <a class="btn btn-sm btn-outline-light" href="edit.php?id=<?= e($paste['id']) ?>">Edit</a>
            <?php endif; ?>
            <?php if (is_staff()): ?>
                <form method="post" action="delete.php" onsubmit="return confirm('Delete this paste? Staff action.');">
                    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="id" value="<?= e($paste['id']) ?>">
                    <button class="btn btn-danger btn-sm" type="submit">Delete</button>
                </form>
            <?php endif; ?>
        </div>
    </div>
    <pre class="paste-content rounded"<?= paste_border_style($paste['paste_color']) ?>><?= e($paste['content'] . watermark_text($paste['id'])) ?></pre>
    <div class="text-secondary small mt-3">
        Link: <code><?= e($GLOBALS['CFG']['base_url'] . 'view.php?id=' . $paste['id']) ?></code><br>
        Keyword: <code><?= e($paste['id']) ?></code> — included in the paste watermark. Without the
        edit key this paste cannot be changed or removed, Unless It Is DMCA'ED Or Taken Down By Feds
    </div>

    <div class="card mt-4">
        <div class="card-body">
            <h2 class="h5 mb-3">Comments (<?= count($comments) ?>)</h2>

            <?php if ($me !== null): ?>
                <form method="post" action="comment.php" class="mb-4">
                    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="action" value="add">
                    <input type="hidden" name="type" value="paste">
                    <input type="hidden" name="target" value="<?= e($paste['id']) ?>">
                    <input type="hidden" name="return" value="view.php?id=<?= e($paste['id']) ?>">
                    <label class="form-label small">Add a comment</label>
                    <textarea class="form-control mb-2" name="body" rows="3" maxlength="2000" placeholder="Leave a comment..."></textarea>
                    <button class="btn btn-primary btn-sm" type="submit">Post comment</button>
                </form>
            <?php else: ?>
                <div class="alert alert-secondary mb-4">
                    <a class="text-info" href="login.php">Log in</a> to like, dislike and comment on this paste.
                </div>
            <?php endif; ?>

            <?php if (count($comments) === 0): ?>
                <div class="text-secondary small">No comments yet.</div>
            <?php else: ?>
                <?php foreach ($comments as $c): ?>
                    <div class="d-flex gap-3 py-2" style="border-bottom:1px solid var(--line);">
                        <?php if ($c['pfp']): ?>
                            <img class="pfp-sm" src="<?= e($c['pfp']) ?>" alt="pfp">
                        <?php else: ?>
                            <div class="pfp-sm d-flex align-items-center justify-content-center bg-secondary">?</div>
                        <?php endif; ?>
                        <div class="flex-grow-1">
                            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                                <span class="small">
                                    <a class="fw-semibold" style="color:<?= e(clean_hex_color($c['profile_color']) !== '' ? clean_hex_color($c['profile_color']) : '#ffffff') ?>"
                                        href="profile.php?id=<?= (int)$c['user_id'] ?>"><?= e($c['username']) ?></a>
                                    · <?= e(gmdate('Y-m-d H:i', strtotime($c['created_at'] . ' UTC'))) ?> UTC
                                </span>
                                <?php if ($me !== null && ((int)$c['user_id'] === (int)$me['id'] || is_staff())): ?>
                                    <form method="post" action="comment.php" onsubmit="return confirm('Delete this comment?');">
                                        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="type" value="paste">
                                        <input type="hidden" name="target" value="<?= e($paste['id']) ?>">
                                        <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
                                        <input type="hidden" name="return" value="view.php?id=<?= e($paste['id']) ?>">
                                        <button class="btn btn-sm btn-outline-danger">Delete</button>
                                    </form>
                                <?php endif; ?>
                            </div>
                            <div class="mt-1 small"><?= nl2br(e($c['body'])) ?></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php page_footer(); ?>