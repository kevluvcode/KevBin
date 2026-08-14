<?php
require_once __DIR__ . '/functions.php';

start_session();
require_login();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    friendly_error('405 Method Not Allowed', 405);
}
csrf_verify_or_fail();

$pasteId = (string)($_POST['paste_id'] ?? '');
if (!preg_match('/^[A-Za-z0-9]{1,12}$/', $pasteId)) {
    friendly_error('Paste not found.', 404);
}

$stmt = db()->prepare('SELECT id, title FROM pastes WHERE id = ?');
$stmt->execute([$pasteId]);
$paste = $stmt->fetch();
if (!$paste) {
    friendly_error('Paste not found.', 404);
}

$me = current_user();
$pdo = db();
$exists = $pdo->prepare('SELECT 1 FROM pins WHERE user_id = ? AND paste_id = ?');
$exists->execute([(int)$me['id'], $pasteId]);

if ($exists->fetch()) {
    $pdo->prepare('DELETE FROM pins WHERE user_id = ? AND paste_id = ?')->execute([(int)$me['id'], $pasteId]);
    log_activity('unpin', $pasteId . ' by ' . $me['username']);
    flash_set('success', 'Unpinned from your profile.');
} else {
    $pdo->prepare(
        'INSERT INTO pins (user_id, paste_id, created_at) VALUES (?, ?, UTC_TIMESTAMP())'
    )->execute([(int)$me['id'], $pasteId]);
    log_activity('pin', $pasteId . ' by ' . $me['username']);
    flash_set('success', 'Pinned to your profile.');
}

redirect('view.php?id=' . urlencode($pasteId));