<?php
require_once __DIR__ . '/functions.php';

start_session();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify_or_fail();
    $cfg = $GLOBALS['CFG'];
    if (!rate_limit_check('register', 10, (int)$cfg['rate_window_seconds'])) {
        friendly_error('Too many registrations from your IP. Try again in 10 minutes.', 429);
    }
    if (!captcha_ok((string)($_POST['captcha'] ?? ''))) {
        flash_set('error', 'Wrong captcha answer. Try again.');
        redirect('register.php');
    }
    $username = trim((string)($_POST['username'] ?? ''));
    $password = (string)($_POST['password'] ?? '');
    $confirm = (string)($_POST['confirm'] ?? '');
    if (strlen($username) < 2 || strlen($username) > 100
        || !preg_match('/^[A-Za-z0-9_.-]+$/', $username)) {
        flash_set('error', 'Username must be 2-100 chars using letters, numbers, dot, dash, underscore.');
        redirect('register.php');
    }
    if (strlen($password) < 4) {
        flash_set('error', 'Password must be at least 4 characters.');
        redirect('register.php');
    }
    if ($password !== $confirm) {
        flash_set('error', 'Passwords do not match.');
        redirect('register.php');
    }
    $pdo = db();
    $taken = $pdo->prepare('SELECT 1 FROM users WHERE username = ?');
    $taken->execute([$username]);
    if ($taken->fetch()) {
        flash_set('error', 'That username is already taken.');
        redirect('register.php');
    }
    try {
        $hash = password_hash($password, PASSWORD_BCRYPT);
        $pdo->prepare(
            'INSERT INTO users (username, password, role, status, created_at)
             VALUES (?, ?, \'user\', \'active\', UTC_TIMESTAMP())'
        )->execute([$username, $hash]);
        $userId = (int)$pdo->lastInsertId();
        session_regenerate_id(true);
        $_SESSION['user_id'] = $userId;
        $_SESSION['pending_recovery_key'] = user_update_recovery_key($userId);
        log_activity('register', $username);
        flash_set('success', 'Welcome, ' . $username . '! Your account is ready.');
        redirect('register.php?key=1');
    } catch (Throwable $t) {
        log_activity('register_failed', $username);
        flash_set('error', 'Could not create the account. Try a different username.');
        redirect('register.php');
    }
}

if (isset($_POST['ack_key'])) {
    csrf_verify_or_fail();
    unset($_SESSION['pending_recovery_key']);
    redirect('profile.php?id=' . (int)($_SESSION['user_id'] ?? 0));
}

$pendingKey = (string)($_SESSION['pending_recovery_key'] ?? '');

if (current_user() !== null && $pendingKey === '') {
    redirect('index.php');
}

captcha_issue(true);

page_header('Register');
?>
<?php if ($pendingKey !== ''): ?>
<div class="container" style="max-width: 560px;">
    <?php if ($unsafeFlash = flash_get()): ?>
        <div class="alert alert-<?= e($unsafeFlash['type'] === 'error' ? 'danger' : 'success') ?>"><?= e($unsafeFlash['msg']) ?></div>
    <?php endif; ?>
    <div class="card">
        <div class="card-body">
            <h1 class="h4 mb-3">🔑 Save your recovery key</h1>
            <p class="text-secondary small">This is the <strong>only</strong> way to reset your password if you ever forget it — there is no email. It is shown once, so save it now:</p>
            <div class="d-flex align-items-center gap-2 mb-3">
                <code id="recovery-key" class="form-control text-center" style="font-family:'JetBrains Mono',monospace;font-size:1.05rem;user-select:all;"><?= e($pendingKey) ?></code>
                <button type="button" class="btn btn-outline-light" id="copy-key" onclick="copyKey()">Copy</button>
            </div>
            <div class="d-flex gap-2 mb-4">
                <button type="button" class="btn btn-outline-light flex-fill" onclick="downloadKey()">⬇ Download as file</button>
                <form method="post" action="register.php" class="flex-fill">
                    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="ack_key" value="1">
                    <button class="btn btn-primary w-100" type="submit">I saved it — continue</button>
                </form>
            </div>
            <p class="text-secondary small mb-0">You can also generate a new key later from Settings → Change password.</p>
        </div>
    </div>
</div>
<script>
var _rk = document.getElementById('recovery-key').textContent;
function copyKey() {
    if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(_rk).then(function () {
            document.getElementById('copy-key').textContent = 'Copied!';
            setTimeout(function () { document.getElementById('copy-key').textContent = 'Copy'; }, 1500);
        });
    } else {
        var t = document.getElementById('recovery-key');
        t.focus(); t.select();
        try { document.execCommand('copy'); } catch (e) {}
    }
}
function downloadKey() {
    var blob = new Blob(['KevBin recovery key for account registration\n\n' + _rk + '\n\nIf you lose your password you can reset it at forgot.php with this key.\n'], { type: 'text/plain' });
    var a = document.createElement('a');
    a.href = URL.createObjectURL(blob);
    a.download = 'kevbin-recovery-key.txt';
    document.body.appendChild(a);
    a.click();
    setTimeout(function () { URL.revokeObjectURL(a.href); a.remove(); }, 1000);
}
</script>
<?php else: ?>
<div class="container" style="max-width: 420px;">
    <div class="card">
        <div class="card-body">
            <h1 class="h4 mb-3">Create account</h1>
            <p class="text-secondary small">No email required. Username + password is all you need.</p>
            <form method="post" action="register.php">
                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                <div class="mb-3">
                    <label class="form-label">Username</label>
                    <input class="form-control" name="username" required maxlength="100" autofocus>
                </div>
                <div class="mb-3">
                    <label class="form-label">Password (min 4 chars)</label>
                    <input class="form-control" type="password" name="password" required minlength="4">
                </div>
                <div class="mb-3">
                    <label class="form-label">Confirm password</label>
                    <input class="form-control" type="password" name="confirm" required minlength="4">
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
                <button class="btn btn-primary w-100" type="submit">Register</button>
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
            </form>
        </div>
    </div>
</div>
<?php endif; ?>
<?php page_footer(); ?>