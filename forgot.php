<?php
require_once __DIR__ . '/functions.php';

start_session();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify_or_fail();
    if (!rate_limit_check('forgot', 5, 600)) {
        friendly_error('Too many reset attempts from your IP. Try again in 10 minutes.', 429);
    }
    $username = trim((string)($_POST['username'] ?? ''));
    $key = trim((string)($_POST['recovery_key'] ?? ''));
    $password = (string)($_POST['password'] ?? '');
    $confirm = (string)($_POST['confirm'] ?? '');
    if (strlen($password) < 6 || strlen($password) > 200) {
        flash_set('error', 'New password must be 6-200 characters.');
        redirect('forgot.php');
    }
    if ($password !== $confirm) {
        flash_set('error', 'Passwords do not match.');
        redirect('forgot.php');
    }
    $user = verify_recovery_key($username, $key);
    if ($user === null) {
        log_activity('recovery_failed', $username);
        flash_set('error', 'Unknown username or invalid recovery key.');
        redirect('forgot.php');
    }
    if ($user['status'] !== 'active') {
        flash_set('error', 'This account is suspended. Contact staff.');
        redirect('forgot.php');
    }
    db()->prepare('UPDATE users SET password = ? WHERE id = ?')
        ->execute([password_hash($password, PASSWORD_BCRYPT), (int)$user['id']]);
    // Rotate the recovery key so a leaked one stops working after a reset.
    user_update_recovery_key((int)$user['id']);
    session_regenerate_id(true);
    if ((int)($_SESSION['user_id'] ?? 0) === (int)$user['id']) {
        unset($_SESSION['user_id']);
    }
    log_activity('password_recovery', $username);
    flash_set('success', 'Password reset. Log in with your new password — your recovery key was rotated.');
    redirect('login.php');
}

if (current_user() !== null) {
    redirect('index.php');
}

page_header('Forgot password');?>
<div class="container" style="max-width: 440px;">
    <div class="card">
        <div class="card-body">
            <h1 class="h4 mb-3">Reset password</h1>
            <p class="text-secondary small">Enter your username and the recovery key you saved when you registered. No email involved.</p>
            <form method="post" action="forgot.php">
                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                <div class="mb-3">
                    <label class="form-label">Username</label>
                    <input class="form-control" name="username" required maxlength="100" autofocus>
                </div>
                <div class="mb-3">
                    <label class="form-label">Recovery key</label>
                    <input class="form-control" name="recovery_key" required maxlength="220" autocomplete="off"
                        placeholder="ABCD-EFGH-JKLM-..." style="font-family:'JetBrains Mono',monospace;">
                    <div class="form-text">Found in Settings → Recovery key, or in the file you downloaded at registration.</div>
                </div>
                <div class="mb-3">
                    <label class="form-label">New password (6-200 chars)</label>
                    <input class="form-control" type="password" name="password" required minlength="6" maxlength="200">
                </div>
                <div class="mb-3">
                    <label class="form-label">Confirm new password</label>
                    <input class="form-control" type="password" name="confirm" required minlength="6" maxlength="200">
                </div>
                <button class="btn btn-primary w-100" type="submit">Reset password</button>
                <p class="text-secondary small mt-3 mb-0">Remembered it? <a class="link-light" href="login.php">Back to login</a></p>
            </form>
        </div>
    </div>
</div>
<?php page_footer(); ?>