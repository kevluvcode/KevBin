<?php
require_once __DIR__ . '/functions.php';

start_session();
require_login();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    friendly_error('405 Method Not Allowed', 405);
}
csrf_verify_or_fail();

$cfg = $GLOBALS['CFG'];
if (!rate_limit_check('vote', (int)($cfg['vote_rate_limit'] ?? 60), (int)$cfg['rate_window_seconds'])) {
    friendly_error('Rate limit reached: too many votes. Try again in a bit.', 429);
}

$type = (string)($_POST['type'] ?? '');
$target = (string)($_POST['target'] ?? '');
$voteDir = (int)($_POST['vote'] ?? 0);
$return = safe_return();

if (!in_array($type, ['paste', 'profile'], true)) {
    friendly_error('Invalid vote target type.', 400);
}
if ($voteDir !== 1 && $voteDir !== -1) {
    friendly_error('Invalid vote.', 400);
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

$me = current_user();
$pdo = db();

// If the user already cast the same vote, remove it; if opposite, switch to the new vote.
$stmt = $pdo->prepare('SELECT vote FROM votes WHERE entity_type = ? AND entity_id = ? AND user_id = ?');
$stmt->execute([$type, $target, (int)$me['id']]);
$current = $stmt->fetch();

if ($current !== false && (int)$current['vote'] === $voteDir) {
    $pdo->prepare('DELETE FROM votes WHERE entity_type = ? AND entity_id = ? AND user_id = ?')
        ->execute([$type, $target, (int)$me['id']]);
    log_activity('vote_remove', $type . ':' . $target . ' by ' . $me['username']);
    flash_set('success', 'Vote removed.');
} else {
    $pdo->prepare(
        'INSERT INTO votes (entity_type, entity_id, user_id, vote)
         VALUES (?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE vote = VALUES(vote)'
    )->execute([$type, $target, (int)$me['id'], $voteDir]);
    log_activity('vote', $type . ':' . $target . ' ' . ($voteDir === 1 ? 'like' : 'dislike') . ' by ' . $me['username']);
    flash_set('success', $voteDir === 1 ? 'Liked.' : 'Disliked.');
}

redirect($return);