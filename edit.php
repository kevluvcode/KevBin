<?php
require_once __DIR__ . '/functions.php';

start_session();
$cfg = $GLOBALS['CFG'];
$me = current_user();

$id = (string)($_GET['id'] ?? '');
$key = (string)($_GET['key'] ?? '');

if (!preg_match('/^[A-Za-z0-9]{1,12}$/', $id)) {
    friendly_error('Invalid edit link.', 403);
}

$stmt = db()->prepare(
    'SELECT id, title, description, tags, password_hash, content, author, expires_at, edit_key, paste_color, user_id
     FROM pastes
     WHERE id = ?'
);
$stmt->execute([$id]);
$paste = $stmt->fetch();

if (!$paste) {
    friendly_error('Paste not found.', 404);
}

$isAdmin = is_admin();
$isStaff = is_staff();
$isOwner = $me !== null && (int)$me['id'] === (int)$paste['user_id'];
$validKey = is_string($paste['edit_key']) && $key !== '' && hash_equals((string)$paste['edit_key'], $key);

if (!$isStaff && !$validKey) {
    friendly_error('Invalid edit key. You do not have permission to edit this paste.', 403);
}

if ($paste['expires_at'] && $paste['expires_at'] <= gmdate('Y-m-d H:i:s')) {
    flash_set('error', 'This paste already expired and cannot be edited.');
    redirect('list.php');
}

$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify_or_fail();
    if (!rate_limit_check('edit', (int)$cfg['edit_rate_limit'], (int)$cfg['rate_window_seconds'])) {
        friendly_error('Rate limit reached: max ' . $cfg['edit_rate_limit'] . ' edits per 10 minutes per IP.', 429);
    }
    $newTitle = trim((string)($_POST['title'] ?? ''));
    $newContent = (string)($_POST['content'] ?? '');
    $newColor = $isStaff ? clean_hex_color((string)($_POST['paste_color'] ?? '')) : (string)$paste['paste_color'];
    $newDescription = trim((string)($_POST['description'] ?? ''));
    $newTags = trim((string)($_POST['tags'] ?? ''));
    $newLockPassword = trim((string)($_POST['password'] ?? ''));
    if (mb_strlen($newLockPassword) > 200) {
        $newLockPassword = mb_substr($newLockPassword, 0, 200);
    }
    if (isset($_POST['remove_password'])) {
        $newPasswordHash = null;
    } elseif ($newLockPassword !== '') {
        $newPasswordHash = password_hash($newLockPassword, PASSWORD_DEFAULT);
    } else {
        $newPasswordHash = $paste['password_hash'];
    }

    if ($newTitle === '') {
        $newTitle = 'Untitled';
    }
    $newTitle = mb_substr($newTitle, 0, (int)$cfg['max_title_chars']);

    if (mb_strlen($newContent) < 1) {
        $error = 'Content cannot be empty.';
    } elseif (mb_strlen($newContent) > content_char_limit()) {
        $error = 'Content too long (max ' . content_char_limit() . ' chars).';
    } elseif ($isStaff) {
        db()->prepare('UPDATE pastes SET title = ?, description = ?, tags = ?, password_hash = ?, content = ?, paste_color = ? WHERE id = ?')
            ->execute([
                $newTitle,
                $newDescription !== '' ? mb_substr($newDescription, 0, 255) : null,
                $newTags !== '' ? mb_substr(preg_replace('/\s*,\s*/', ',', $newTags), 0, 255) : null,
                $newPasswordHash,
                $newContent,
                $newColor,
                $id,
            ]);
        log_activity('edit', $id . ' by ' . ($me['username'] ?? 'key'));
        flash_set('success', 'Paste updated.');
        redirect('view.php?id=' . urlencode($id));
    } else {
        // Non-staff edit (via edit key): route through the approval queue.
        $meta = json_encode([
            'title' => $newTitle,
            'description' => $newDescription,
            'tags' => $newTags,
            'color' => $newColor,
        ], JSON_UNESCAPED_UNICODE);
        db()->prepare(
            'INSERT INTO moderation_queue
                (action_type, target_type, ref_id, scope, slug, title, old_content, new_content, note, requested_by)
             VALUES (\'edit\', \'paste\', ?, ?, ?, ?, ?, ?, ?, ?)'
        )->execute([
            $id,
            'paste',
            $id,
            $newTitle,
            (string)$paste['content'],
            $newContent,
            $meta,
            $me !== null ? (int)$me['id'] : null,
        ]);
        log_activity('moderation_queued', 'paste edit ' . $id);
        flash_set('success', 'Your edit has been submitted for staff approval.');
        redirect('view.php?id=' . urlencode($id));
    }
}

$keyArg = $isStaff ? '' : '&key=' . urlencode($key);

page_header('Edit paste');
?>
<div class="container" style="max-width: 900px;">
    <div class="card">
        <div class="card-body">
            <h1 class="h4 mb-3">Edit paste — <?= e($paste['title']) ?></h1>
            <?php if ($error !== null): ?>
                <div class="alert alert-danger"><?= e($error) ?></div>
            <?php endif; ?>
            <form method="post" action="edit.php?id=<?= e($id) ?><?= e($keyArg) ?>">
                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Title</label>
                        <input class="form-control" name="title" maxlength="120"
                            value="<?= e($paste['title']) ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Author</label>
                        <input class="form-control" type="text" value="<?= e($paste['author']) ?>" readonly>
                    </div>
                    <?php if ($isStaff): ?>
                        <div class="col-md-6">
                            <label class="form-label">Paste color (staff only)</label>
                            <?= color_select('paste_color', (string)$paste['paste_color']) ?>
                        </div>
                    <?php endif; ?>
                    <div class="col-12">
                        <label class="form-label">Content</label>
                        <textarea class="form-control" name="content" rows="14" required><?= e($paste['content']) ?></textarea>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Description</label>
                        <textarea class="form-control" name="description" rows="2" maxlength="255"><?= e((string)($paste['description'] ?? '')) ?></textarea>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Tags (comma separated)</label>
                        <input class="form-control" name="tags" maxlength="255" value="<?= e((string)($paste['tags'] ?? '')) ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Password lock (optional)</label>
                        <input class="form-control" type="password" name="password" maxlength="200"
                            autocomplete="new-password" placeholder="<?= !empty($paste['password_hash']) ? 'Locked — leave blank to keep password' : 'Leave blank for a public paste' ?>">
                        <div class="form-check mt-2">
                            <input class="form-check-input" type="checkbox" name="remove_password" id="remove_password">
                            <label class="form-check-label" for="remove_password">Remove password lock</label>
                        </div>
                    </div>
                    <div class="col-12">
                        <button class="btn btn-primary" type="submit">Save changes</button>
                        <a class="btn btn-outline-light" href="view.php?id=<?= e($id) ?>">Cancel</a>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
<?php page_footer(); ?>