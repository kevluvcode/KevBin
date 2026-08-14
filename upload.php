<?php
require_once __DIR__ . '/functions.php';

start_session();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    friendly_error('405 Method Not Allowed', 405);
}
csrf_verify_or_fail();

if (!captcha_ok((string)($_POST['captcha'] ?? ''))) {
    flash_set('error', 'Wrong captcha answer. Try again.');
    redirect('index.php?new=1');
}

$cfg = $GLOBALS['CFG'];
if (!rate_limit_check('upload', (int)$cfg['upload_rate_limit'], (int)$cfg['rate_window_seconds'])) {
    friendly_error('Rate limit reached: max ' . $cfg['upload_rate_limit'] . ' uploads per 10 minutes per IP.', 429);
}

$me = current_user();
$title = trim((string)($_POST['title'] ?? ''));
$author = $me !== null ? $me['username'] : 'Anonymous';
$content = (string)($_POST['content'] ?? '');
$expiry = (string)($_POST['expiry'] ?? '');
$pasteColor = clean_hex_color((string)($_POST['paste_color'] ?? ''));
$description = trim((string)($_POST['description'] ?? ''));
$tags = trim((string)($_POST['tags'] ?? ''));
$lockPassword = trim((string)($_POST['password'] ?? ''));
if (mb_strlen($lockPassword) > 200) {
    $lockPassword = mb_substr($lockPassword, 0, 200);
}
$passwordHash = $lockPassword !== '' ? password_hash($lockPassword, PASSWORD_DEFAULT) : null;

if ($title === '') {
    $title = 'Untitled';
}
$title = mb_substr($title, 0, (int)$cfg['max_title_chars']);
$author = mb_substr($author, 0, (int)$cfg['max_author_chars']);
$description = mb_substr($description, 0, 255);
$tags = mb_substr(preg_replace('/\s*,\s*/', ',', $tags), 0, 255);

if (mb_strlen($content) < 1) {
    flash_set('error', 'Content cannot be empty.');
    redirect('index.php');
}
if (mb_strlen($content) > (int)$cfg['max_content_chars']) {
    flash_set('error', 'Content too long (max ' . $cfg['max_content_chars'] . ' chars).');
    redirect('index.php');
}

$allowed = ['3600', '86400', '604800', '2592000', '25920000'];
if (!in_array($expiry, $allowed, true)) {
    $expiry = '';
}

$id = generate_paste_id(8);
$editKey = bin2hex(random_bytes(8));
$userId = $me !== null ? (int)$me['id'] : null;
$notifyFollowers = $userId !== null && isset($_POST['notify_followers']);
$pdo = db();
$pdo->prepare(
    'INSERT INTO pastes (id, title, description, tags, password_hash, content, author, created_at, expires_at, edit_key, user_id, paste_color, notify_followers)
     VALUES (?, ?, ?, ?, ?, ?, ?, UTC_TIMESTAMP(), IF(? = \'\', NULL, DATE_ADD(UTC_TIMESTAMP(), INTERVAL ? SECOND)), ?, ?, ?, ?)'
)->execute([$id, $title, $description !== '' ? $description : null, $tags !== '' ? $tags : null, $passwordHash, $content, $author, $expiry, $expiry !== '' ? (int)$expiry : 0, $editKey, $userId, $pasteColor, $notifyFollowers ? 1 : 0]);

log_activity('upload', $id . ' by ' . $author);

if ($notifyFollowers) {
    notify_followers($userId, $id, $title);
}

$viewUrl = $cfg['base_url'] . 'view.php?id=' . $id;
$editUrl = $cfg['base_url'] . 'edit.php?id=' . $id . '&key=' . $editKey;

page_header('Paste created');
?>
<div class="container" style="max-width: 700px;">
    <div class="alert alert-success">
        <h1 class="h5 mb-3">Paste published</h1>
        <p><strong>Title:</strong> <?= e($title) ?></p>
        <?php if ($pasteColor !== ''): ?>
            <p><strong>Color:</strong> <span style="color:<?= e($pasteColor) ?>"><?= e($pasteColor) ?></span></p>
        <?php endif; ?>
        <p class="mb-1"><strong>View link:</strong><br>
            <code><?= e($viewUrl) ?></code></p>
        <p class="mb-1"><strong>Edit link — save this, it works once only:</strong><br>
            <code><?= e($editUrl) ?></code></p>
        <?php if ($passwordHash !== null): ?>
            <p class="mb-1">🔒 <strong>Password-protected:</strong> viewers must enter the password to see the content.</p>
        <?php endif; ?>
        <p class="text-secondary small mb-0">If you ever want to edit or delete this paste, the edit link
        above is the only way — we don't keep accounts for anonymous pastes.</p>
    </div>
    <div class="d-flex gap-2">
        <a class="btn btn-primary" href="view.php?id=<?= e($id) ?>">View paste</a>
        <a class="btn btn-outline-light" href="index.php">Create another</a>
    </div>
</div>
<?php page_footer(); ?>