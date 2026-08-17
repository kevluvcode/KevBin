<?php
require_once __DIR__ . '/functions.php';

start_session();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify_or_fail();
    $cfg = $GLOBALS['CFG'];
    if (!rate_limit_check('login', (int)$cfg['login_rate_limit'], (int)$cfg['rate_window_seconds'])) {
        friendly_error('Too many login attempts. Try again in 10 minutes.', 429);
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
                <button class="btn btn-primary w-100" type="submit">Log in</button>
                <?php if (!empty($GLOBALS['CFG']['github_client_id']) && !empty($GLOBALS['CFG']['github_client_secret'])): ?>
                    <div class="my-3 position-relative text-center">
                        <hr class="my-3" style="border-color:var(--line);">
                        <span class="px-2 small text-secondary">or</span>
                    </div>
                    <a class="btn btn-outline-light w-100" href="github_oauth.php">
                        <svg width="14" height="14" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true" style="vertical-align:-2px;margin-right:4px;"><path d="M8 0C3.58 0 0 3.58 0 8c0 3.54 2.29 6.53 5.47 7.59.4.07.55-.17.55-.38 0-.19-.01-.82-.01-1.49-2.01.37-2.53-.49-2.69-.94-.09-.23-.48-.94-.82-1.13-.28-.15-.68-.52-.01-.53.63-.01 1.08.58 1.23.82.72 1.21 1.87.87 2.33.66.07-.52.28-.87.51-1.07-1.78-.2-3.64-.89-3.64-3.95 0-.87.31-1.59.82-2.15-.08-.2-.36-1.02.08-2.12 0 0 .67-.21 2.2.82.64-.18 1.32-.27 2-.27s1.36.09 2 .27c1.53-1.04 2.2-.82 2.2-.82.44 1.1.16 1.92.08 2.12.51.56.82 1.27.82 2.15 0 3.07-1.87 3.75-3.65 3.95.29.25.54.73.54 1.48 0 1.07-.01 1.93-.01 2.2 0 .21.15.46.55.38A8.01 8.01 0 0 0 16 8c0-4.42-3.58-8-8-8Z"/></svg>
                        Continue with GitHub
                    </a>
                <?php endif; ?>
                <?php if (!empty($GLOBALS['CFG']['discord_client_id']) && !empty($GLOBALS['CFG']['discord_bridge_url'])): ?>
                    <div class="my-3 position-relative text-center">
                        <hr class="my-3" style="border-color:var(--line);">
                        <span class="px-2 small text-secondary">or</span>
                    </div>
                    <a class="btn btn-outline-light w-100" href="discord_oauth.php">
                        <svg width="18" height="14" viewBox="0 0 127.14 96.36" fill="currentColor" aria-hidden="true" style="vertical-align:-2px;margin-right:4px;"><path d="M107.7,8.07A105.15,105.15,0,0,0,81.47,0a72.06,72.06,0,0,0-3.36,6.83A97.68,97.68,0,0,0,49,6.83,72.37,72.37,0,0,0,45.64,0,105.89,105.89,0,0,0,19.39,8.09C2.79,32.65-1.71,56.6.54,80.21h0A105.73,105.73,0,0,0,32.71,96.36,77.7,77.7,0,0,0,39.6,85.25a68.42,68.42,0,0,1-10.85-5.18c.91-.66,1.8-1.34,2.66-2a75.57,75.57,0,0,0,64.32,0c.87.71,1.76,1.39,2.66,2a68.68,68.68,0,0,1-10.87,5.19,77,77,0,0,0,6.89,11.1A105.25,105.25,0,0,0,126.6,80.22h0C129.24,52.84,122.09,29.11,107.7,8.07ZM42.45,65.69C36.18,65.69,31,60,31,53s5-12.74,11.43-12.74S54,46,53.89,53,48.84,65.69,42.45,65.69Zm42.24,0C78.41,65.69,73.25,60,73.25,53s5-12.74,11.44-12.74S96.23,46,96.12,53,91.08,65.69,84.69,65.69Z"/></svg>
                        Continue with Discord
                    </a>
                <?php endif; ?>
                <p class="text-secondary small mt-3 mb-0">No account?
                    <a class="link-light" href="register.php">Register here</a></p>
            </form>
        </div>
    </div>
</div>
<?php page_footer(); ?>