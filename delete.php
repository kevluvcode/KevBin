<?php
require_once __DIR__ . '/functions.php';

start_session();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    friendly_error('405 Method Not Allowed', 405);
}
csrf_verify_or_fail();

if (!is_staff()) {
    friendly_error('Staff login required.', 403);
}

$id = (string)($_POST['id'] ?? '');
if (!preg_match('/^[A-Za-z0-9]{1,12}$/', $id)) {
    friendly_error('Paste not found.', 404);
}

$pdo = db();
$stmt = $pdo->prepare('SELECT id, title FROM pastes WHERE id = ?');
$stmt->execute([$id]);
$paste = $stmt->fetch();

if (!$paste) {
    friendly_error('Paste not found.', 404);
}

$pdo->prepare('DELETE FROM pins WHERE paste_id = ?')->execute([$id]);
$pdo->prepare('DELETE FROM notifications WHERE paste_id = ?')->execute([$id]);
$pdo->prepare('DELETE FROM pastes WHERE id = ?')->execute([$id]);
log_activity('delete', $id . ' "' . $paste['title'] . '" by ' . (current_user()['username'] ?? 'staff'));
flash_set('success', 'Paste deleted.');
redirect('list.php');