<?php
require_once __DIR__ . '/functions.php';

start_session();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify_or_fail();
    $cfg = $GLOBALS['CFG'];
    if (!rate_limit_check('login', (int)$cfg['login_rate_limit'], (int)$cfg['rate_window_seconds'])) {
        friendly_error('Too many login attempts. Try again in 10 minutes.', 429);
    }
    if (!captcha_ok((string)($_POST['captcha'] ?? ''))) {
        flash_set('error', 'Wrong captcha answer. Try again.');
        redirect('login.php');
    }
    $username = trim((string)($_POST['username'] ?? ''));
    $password = (string)($_POST['password'] ?? '');
    $stmt = db()->prepare('SELECT id, username, password, role, status FROM users WHERE username = ?');
    $stmt->execute([$username]);
    $user = $stmt->fetch();
    if (!$user || !password_verify($password, $user['password'])) {
        log_activity('login_failed', $username);
        flash_set('error', 'Invalid username or password.');
        redirect('login.php');
    }
    if ($user['status'] !== 'active') {
        log_activity('login_suspended', $username);
        flash_set('error', 'This account is suspended.');
        redirect('login.php');
    }
    session_regenerate_id(true);
    $_SESSION['user_id'] = (int)$user['id'];
    log_activity('login', $username);
    flash_set('success', 'Welcome back, ' . $user['username'] . '.');
    redirect(safe_return());
}

if (current_user() !== null) {
    redirect('index.php');
}

captcha_issue(true);

page_header('Login');
?>
<div class="container" style="max-width: 420px;">
    <div class="card">
        <div class="card-body">
            <h1 class="h4 mb-3">Log in</h1>
            <form method="post" action="login.php">
                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="return" value="<?= e(safe_return()) ?>">
                <div class="mb-3">
                    <label class="form-label">Username</label>
                    <input class="form-control" name="username" required autofocus maxlength="50">
                </div>
                <div class="mb-3">
                    <label class="form-label">Password</label>
                    <input class="form-control" type="password" name="password" required>
                    <div class="form-text"><a class="link-light" href="forgot.php">Forgot password?</a></div>
                </div>
                <div class="mb-3">
                    <label class="form-label">Security check</label>
                    <div class="d-flex align-items-center gap-2 mb-2">
                        <img src="captcha.php?v=<?= time() ?>" alt="captcha" width="150" height="52"
                            style="border-radius:8px;border:1px solid var(--line);">
                        <button type="button" class="btn btn-sm btn-outline-light" onclick="this.previousElementSibling.src='captcha.php?rot=1&v='+Date.now()"
                            title="New captcha">↻</button>
                    </div>
                    <input class="form-control" name="captcha" maxlength="6" required autocomplete="off"
                        placeholder="Type the characters above">
                </div>
                <button class="btn btn-primary w-100" type="submit">Log in</button>
                <p class="text-secondary small mt-3 mb-0">No account?
                    <a class="link-light" href="register.php">Register here</a></p>
            </form>
        </div>
    </div>
</div>
<?php page_footer(); ?>