<?php
require_once __DIR__ . '/functions.php';

start_session();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    friendly_error('405 Method Not Allowed', 405);
}
csrf_verify_or_fail();

$me = current_user();
if ($me === null) {
    flash_set('error', 'You need to be logged in to follow people.');
    redirect('login.php');
}

$action = (string)($_POST['action'] ?? '');
$target = (int)($_POST['target'] ?? 0);
$return = safe_return('profile.php?id=' . $target);

if ($target < 1 || (int)$me['id'] === $target) {
    flash_set('error', 'You cannot follow yourself.');
    redirect($return);
}

$stmt = db()->prepare('SELECT 1 FROM users WHERE id = ?');
$stmt->execute([$target]);
if (!$stmt->fetch()) {
    flash_set('error', 'User not found.');
    redirect('users.php');
}

if (!rate_limit_check('follow', (int)$GLOBALS['CFG']['follow_rate_limit'], (int)$GLOBALS['CFG']['rate_window_seconds'])) {
    flash_set('error', 'Rate limit reached. Try again later.');
    redirect($return);
}

if ($action === 'follow') {
    if (follow_user($target)) {
        flash_set('success', 'You are now following this user.');
    } else {
        flash_set('error', 'Could not follow this user.');
    }
} elseif ($action === 'unfollow') {
    unfollow_user($target);
    flash_set('success', 'You are no longer following this user.');
} else {
    flash_set('error', 'Unknown action.');
}
redirect($return);