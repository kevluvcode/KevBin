<?php
require_once __DIR__ . '/functions.php';

start_session();
$cfg = $GLOBALS['CFG'];

$needsInstall = !is_installed();
$needsAdmin = !admin_exists();

if (!$needsInstall && !$needsAdmin) {
    redirect('index.php');
}

$error = null;
$done = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify_or_fail();
    $username = trim((string)($_POST['username'] ?? ''));
    $password = (string)($_POST['password'] ?? '');
    $confirm = (string)($_POST['confirm'] ?? '');

    if (!preg_match('/^[A-Za-z0-9_]{3,20}$/', $username)) {
        $error = 'Username must be 3-20 characters (letters, numbers, underscore).';
    } elseif (mb_strlen($password) < 8) {
        $error = 'Password must be at least 8 characters.';
    } elseif ($password !== $confirm) {
        $error = 'Passwords do not match.';
    } else {
        try {
        $pdo = db();
        if ($needsInstall) {
            // Base schema — same shape the app expects (idempotent).
            $pdo->exec('CREATE TABLE IF NOT EXISTS users (
                id INT AUTO_INCREMENT PRIMARY KEY,
                username VARCHAR(50) NOT NULL UNIQUE,
                password VARCHAR(255) NOT NULL,
                role VARCHAR(20) NOT NULL DEFAULT \'user\',
                status VARCHAR(20) NOT NULL DEFAULT \'active\',
                pfp VARCHAR(255) NULL,
                banner VARCHAR(255) NULL,
                profile_color VARCHAR(7) NULL,
                alias VARCHAR(50) NULL,
                profile_views INT UNSIGNED NOT NULL DEFAULT 0,
                bio TEXT NULL,
                location VARCHAR(100) NULL,
                website VARCHAR(255) NULL,
                discord VARCHAR(100) NULL,
                telegram VARCHAR(100) NULL,
                twitter VARCHAR(100) NULL,
                youtube VARCHAR(255) NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
            $pdo->exec('CREATE TABLE IF NOT EXISTS pastes (
                id VARCHAR(12) PRIMARY KEY,
                title VARCHAR(120) NOT NULL,
                description VARCHAR(255) NULL,
                tags VARCHAR(255) NULL,
                password_hash VARCHAR(255) NULL,
                content LONGTEXT NOT NULL,
                author VARCHAR(50) NOT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                expires_at DATETIME NULL,
                edit_key VARCHAR(32) NULL,
                user_id INT NULL,
                paste_color VARCHAR(7) NULL,
                views INT UNSIGNED NOT NULL DEFAULT 0,
                pin TINYINT(1) NOT NULL DEFAULT 0,
                KEY idx_pastes_created (created_at),
                KEY idx_pastes_user (user_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
            $pdo->exec('CREATE TABLE IF NOT EXISTS pins (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                paste_id VARCHAR(12) NOT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uq_pin (user_id, paste_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
            $pdo->exec('CREATE TABLE IF NOT EXISTS rate_limits (
                id INT AUTO_INCREMENT PRIMARY KEY,
                ip VARCHAR(45) NOT NULL,
                bucket VARCHAR(32) NOT NULL,
                hits INT NOT NULL DEFAULT 0,
                window_start DATETIME NOT NULL,
                UNIQUE KEY uq_bucket (ip, bucket)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
            $pdo->exec('CREATE TABLE IF NOT EXISTS activity_log (
                id INT AUTO_INCREMENT PRIMARY KEY,
                ts DATETIME NOT NULL,
                ip VARCHAR(45) NULL,
                action VARCHAR(50) NOT NULL,
                detail VARCHAR(500) NOT NULL DEFAULT \'\'
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
            $pdo->exec('CREATE TABLE IF NOT EXISTS banned_ips (
                id INT AUTO_INCREMENT PRIMARY KEY,
                ip VARCHAR(45) NOT NULL,
                reason VARCHAR(255) NOT NULL DEFAULT \'\',
                banned_by INT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uq_banned_ip (ip),
                KEY idx_created (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        }
        // Apply upgrade #2 (votes, comments, aliases, metadata, password lock).
        schema_ensure(true);

        $stmt = $pdo->prepare('INSERT INTO users (username, password, role, status, created_at)
                               VALUES (?, ?, \'admin\', \'active\', UTC_TIMESTAMP())');
        $stmt->execute([$username, password_hash($password, PASSWORD_DEFAULT)]);
        log_activity('setup_admin', 'admin account created: ' . $username);
        flash_set('success', 'Setup complete. Log in with your admin account.');
        redirect('login.php');
        } catch (Throwable $t) {
        $error = 'Setup failed: ' . $t->getMessage();
        }
    }
}

page_header('Setup');
?>
<div class="container" style="max-width: 600px;">
    <div class="card">
        <div class="card-body">
            <h1 class="h4 mb-3">KevBin Setup</h1>
            <?php if ($needsInstall): ?>
                <div class="alert alert-secondary mb-3">Database is empty — base tables and the
                votes/comments/metadata schema will be created now.</div>
            <?php endif; ?>
            <?php if ($error !== null): ?>
                <div class="alert alert-danger"><?= e($error) ?></div>
            <?php endif; ?>
            <form method="post" action="setup.php">
                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                <div class="mb-3">
                    <label class="form-label">Admin username</label>
                    <input class="form-control" name="username" maxlength="20" required
                        pattern="[A-Za-z0-9_]{3,20}" autocomplete="username">
                </div>
                <div class="mb-3">
                    <label class="form-label">Admin password</label>
                    <input class="form-control" type="password" name="password" minlength="8" required
                        autocomplete="new-password">
                </div>
                <div class="mb-3">
                    <label class="form-label">Confirm password</label>
                    <input class="form-control" type="password" name="confirm" minlength="8" required
                        autocomplete="new-password">
                </div>
                <button class="btn btn-primary" type="submit">Complete setup</button>
            </form>
        </div>
    </div>
</div>
<?php page_footer(); ?>