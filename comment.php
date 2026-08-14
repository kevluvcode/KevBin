<?php
require_once __DIR__ . '/functions.php';

start_session();
require_login();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    friendly_error('405 Method Not Allowed', 405);
}
csrf_verify_or_fail();

$cfg = $GLOBALS['CFG'];
$action = (string)($_POST['action'] ?? 'add');
$type = (string)($_POST['type'] ?? '');
$target = (string)($_POST['target'] ?? '');
$me = current_user();

if (!in_array($type, ['paste', 'profile'], true)) {
    friendly_error('Invalid comment target type.', 400);
}

// Validate the target actually exists
if ($type === 'paste') {
    if (!preg_match('/^[A-Za-z0-9]{1,12}$/', $target)) {
        friendly_error('Paste not found.', 404);
    }
    $stmt = db()->prepare('SELECT 1 FROM pastes WHERE id = ?');
    $stmt->execute([$target]);
    $exists = $stmt->fetch() !== false;
} else {
    if (!ctype_digit($target) || (int)$target < 1) {
        friendly_error('Profile not found.', 404);
    }
    $stmt = db()->prepare('SELECT 1 FROM users WHERE id = ?');
    $stmt->execute([(int)$target]);
    $exists = $stmt->fetch() !== false;
}
if (!$exists) {
    friendly_error('Target not found.', 404);
}

$return = safe_return();

if ($action === 'delete') {
    $id = (int)($_POST['id'] ?? 0);
    $stmt = db()->prepare('SELECT id, user_id FROM comments WHERE id = ? AND entity_type = ? AND entity_id = ?');
    $stmt->execute([$id, $type, $target]);
    $comment = $stmt->fetch();
    if (!$comment) {
        friendly_error('Comment not found.', 404);
    }
    if ((int)$comment['user_id'] !== (int)$me['id'] && !is_staff()) {
        friendly_error('You can only delete your own comments.', 403);
    }
    db()->prepare('DELETE FROM comments WHERE id = ?')->execute([$id]);
    log_activity('comment_delete', $type . ':' . $target . ' comment #' . $id . ' by ' . $me['username']);
    flash_set('success', 'Comment deleted.');
} else {
    $body = trim((string)($_POST['body'] ?? ''));
    if ($body === '') {
        flash_set('error', 'Comment cannot be empty.');
        redirect($return);
    }
    if (mb_strlen($body) > 2000) {
        flash_set('error', 'Comment too long (max 2000 chars).');
        redirect($return);
    }
    if (!rate_limit_check('comment', (int)($cfg['comment_rate_limit'] ?? 20), (int)$cfg['rate_window_seconds'])) {
        friendly_error('Rate limit reached: too many comments. Try again in a bit.', 429);
    }
    db()->prepare(
        'INSERT INTO comments (entity_type, entity_id, user_id, body, created_at)
         VALUES (?, ?, ?, ?, UTC_TIMESTAMP())'
    )->execute([$type, $target, (int)$me['id'], mb_substr($body, 0, 2000)]);
    log_activity('comment', $type . ':' . $target . ' by ' . $me['username']);
    flash_set('success', 'Comment posted.');
}

redirect($return);