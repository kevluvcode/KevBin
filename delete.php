<?php
require_once __DIR__ . '/functions.php';

start_session();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    friendly_error('405 Method Not Allowed', 405);
}
csrf_verify_or_fail();

$me = current_user();

$id = (string)($_POST['id'] ?? '');
if (!preg_match('/^[A-Za-z0-9]{1,12}$/', $id)) {
    friendly_error('Paste not found.', 404);
}

$pdo = db();
$stmt = $pdo->prepare('SELECT id, title, user_id FROM pastes WHERE id = ?');
$stmt->execute([$id]);
$paste = $stmt->fetch();

if (!$paste) {
    friendly_error('Paste not found.', 404);
}

// Staff: full immediate delete.
if (is_staff()) {
    $pdo->prepare('DELETE FROM pins WHERE paste_id = ?')->execute([$id]);
    $pdo->prepare('DELETE FROM notifications WHERE paste_id = ?')->execute([$id]);
    $pdo->prepare('DELETE FROM pastes WHERE id = ?')->execute([$id]);
    log_activity('delete', $id . ' "' . $paste['title'] . '" by ' . (current_user()['username'] ?? 'staff'));
    flash_set('success', 'Paste deleted.');
    redirect('list.php');
}

// Non-staff: only the paste owner may request deletion, and it needs approval.
if ($me === null || (int)$paste['user_id'] !== (int)$me['id']) {
    friendly_error('Only the paste owner or staff can request deletion.', 403);
}

$pdo->prepare(
    'INSERT INTO moderation_queue
        (action_type, target_type, ref_id, scope, slug, title, old_content, note, requested_by)
     VALUES (\'delete\', \'paste\', ?, \'paste\', ?, ?, ?, ?, ?)'
)->execute([
    $id,
    $id,
    $paste['title'],
    (string)$paste['title'],
    'Owner-requested deletion of paste "' . $paste['title'] . '"',
    (int)$me['id'],
]);
log_activity('moderation_queued', 'paste delete ' . $id . ' by ' . $me['username']);
flash_set('success', 'Deletion request submitted. Staff will review it.');
redirect('view.php?id=' . urlencode($id));