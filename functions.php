<?php
require_once __DIR__ . '/db.php';

if (!defined('APP_INITIALIZED')) {
    define('APP_INITIALIZED', true);

    function start_session(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            ini_set('session.use_strict_mode', '1');
            $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
                || (int)($_SERVER['SERVER_PORT'] ?? 0) === 443;
            session_set_cookie_params([
                'lifetime' => 0,
                'path' => '/',
                'domain' => '',
                'httponly' => true,
                'secure' => $secure,
                'samesite' => 'Lax',
            ]);
            session_start();
        }
    }

    function is_public_ip(string $ip): bool
    {
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
            return false;
        }
        $parts = array_map('intval', explode('.', $ip));
        if (count($parts) !== 4) {
            return true;
        }
        if ($parts[0] === 127 || $parts[0] === 0) {
            return false;
        }
        if ($parts[0] === 169 && $parts[1] === 254) {
            return false;
        }
        if ($parts[0] === 100 && $parts[1] >= 64 && $parts[1] <= 127) {
            return false;
        }
        if ($parts[0] === 198 && ($parts[1] === 18 || $parts[1] === 19)) {
            return false;
        }
        return true;
    }

    function public_ip_for(string $host): ?string
    {
        $host = trim($host);
        if ($host === '') {
            return null;
        }
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return is_public_ip($host) ? $host : null;
        }
        $ips = @gethostbynamel($host);
        if (!is_array($ips) || count($ips) === 0) {
            return null;
        }
        foreach ($ips as $ip) {
            if (is_public_ip($ip)) {
                return $ip;
            }
        }
        return null;
    }

    function url_allowed_public(string $url): bool
    {
        $scheme = strtolower((string)parse_url($url, PHP_URL_SCHEME));
        if (!in_array($scheme, ['http', 'https'], true)) {
            return false;
        }
        $host = parse_url($url, PHP_URL_HOST);
        if (!is_string($host) || $host === '') {
            return false;
        }
        return public_ip_for($host) !== null;
    }

    function e(?string $value): string
    {
        return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    function client_ip(): string
    {
        return (string)($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
    }

    function redirect(string $url): void
    {
        header('Location: ' . $url, true, 302);
        exit;
    }

    function flash_set(string $type, string $msg): void
    {
        $_SESSION['flash'] = ['type' => $type, 'msg' => $msg];
    }

    function flash_get(): ?array
    {
        if (isset($_SESSION['flash'])) {
            $f = $_SESSION['flash'];
            unset($_SESSION['flash']);
            return $f;
        }
        return null;
    }

    function csrf_token(): string
    {
        if (empty($_SESSION['csrf'])) {
            $_SESSION['csrf'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf'];
    }

    function csrf_verify(): bool
    {
        $token = (string)($_POST['csrf'] ?? $_POST['csrf_token'] ?? '');
        return isset($_SESSION['csrf']) && hash_equals($_SESSION['csrf'], $token);
    }

    function csrf_verify_or_fail(): void
    {
        if (!csrf_verify()) {
            friendly_error('Invalid CSRF token. Please go back and try again.', 400);
        }
    }

    function rate_limit_check(string $bucket, int $limit, int $windowSeconds): bool
    {
        $ip = client_ip();
        $pdo = db();
        $cut = gmdate('Y-m-d H:i:s', time() - $windowSeconds);
        $pdo->prepare(
            'INSERT INTO rate_limits (ip, bucket, hits, window_start)
             VALUES (?, ?, 1, UTC_TIMESTAMP())
             ON DUPLICATE KEY UPDATE
               hits = IF(window_start < ?, 1, hits + 1),
               window_start = IF(window_start < ?, UTC_TIMESTAMP(), window_start)'
        )->execute([$ip, $bucket, $cut, $cut]);
        $stmt = $pdo->prepare('SELECT hits FROM rate_limits WHERE ip = ? AND bucket = ?');
        $stmt->execute([$ip, $bucket]);
        $row = $stmt->fetch();
        return (int)($row['hits'] ?? 0) <= $limit;
    }

    function log_activity(string $action, string $detail = ''): void
    {
        try {
            $pdo = db();
            $pdo->prepare(
                'INSERT INTO activity_log (ts, ip, action, detail) VALUES (UTC_TIMESTAMP(), ?, ?, ?)'
            )->execute([client_ip(), $action, mb_substr($detail, 0, 500)]);
        } catch (Throwable $t) {
            error_log('[log_activity] ' . $t->getMessage());
        }
    }

    function friendly_error(string $msg, int $status = 500): void
    {
        http_response_code($status);
        log_activity('error_' . $status, $msg);
        page_header('Error');
        echo '<div class="container py-5"><div class="alert alert-danger">'
            . e($msg)
            . '</div><a href="' . e($GLOBALS['CFG']['base_url']) . '" class="btn btn-outline-light">Back to home</a></div>';
        page_footer();
        exit;
    }

    function is_installed(): bool
    {
        try {
            db()->query('SELECT 1 FROM pastes LIMIT 1');
            return true;
        } catch (Throwable $t) {
            return false;
        }
    }

    function admin_exists(): bool
    {
        try {
            $row = db()->query("SELECT 1 FROM users WHERE role = 'admin' LIMIT 1")->fetch();
            return $row !== false;
        } catch (Throwable $t) {
            return false;
        }
    }

    function watermark_text(?string $keyword = ''): string
    {
        $cfg = $GLOBALS['CFG'];
        if (empty($cfg['watermark'])) {
            return '';
        }
        $kw = '';
        if (!empty($cfg['watermark_keyword']) && $keyword !== null && $keyword !== '') {
            $kw = ' — keyword: ' . $keyword;
        }
        return "\n\n----\nCreated on " . $cfg['site_name'] . $kw . ' — ' . $cfg['base_url'];
    }

    function current_user(): ?array
    {
        static $cache = false;
        if ($cache !== false) {
            return $cache;
        }
        $cache = null;
        if (empty($_SESSION['user_id'])) {
            return null;
        }
        try {
            $stmt = db()->prepare(
                'SELECT id, username, role, status, pfp, banner, profile_color, created_at,
                        bio, location, website, discord, telegram, twitter, youtube, alias,
                        occupation, education, languages, hobbies, quote, birthdate, status_msg,
                        github, twitch, tiktok, instagram, reddit, snapchat, bluesky, threads, linkedin,
                        bg_image, bg_mode, bg_fit, bg_color, bg_gradient, bg_veil, bg_blur,
                        ui_mode, ui_color, ui_gradient, ui_layout, accent_color
                 FROM users WHERE id = ?'
            );
            $stmt->execute([(int)$_SESSION['user_id']]);
            $cache = $stmt->fetch() ?: null;
        } catch (Throwable $t) {
            $cache = null;
        }
        return $cache;
    }

    function require_login(): void
    {
        if (current_user() === null) {
            flash_set('error', 'You need to be logged in to do that.');
            redirect('login.php');
        }
    }

    function require_admin(): void
    {
        $u = current_user();
        if ($u === null) {
            flash_set('error', 'Admin login required.');
            redirect('login.php');
        }
        if ($u['role'] !== 'admin') {
            friendly_error('Admin access required.', 403);
        }
    }

    function is_admin(): bool
    {
        $u = current_user();
        return $u !== null && $u['role'] === 'admin';
    }

    function is_staff(): bool
    {
        $u = current_user();
        return $u !== null && ($u['role'] === 'admin' || $u['role'] === 'moderator');
    }

    function is_moderator(): bool
    {
        $u = current_user();
        return $u !== null && $u['role'] === 'moderator';
    }

    // Gate for the admin/panel area: admins get full access, moderators (staff)
    // get the request/abuse-reports queue only.
    function require_staff(): void
    {
        $u = current_user();
        if ($u === null) {
            flash_set('error', 'Staff login required.');
            redirect('login.php');
        }
        if ($u['role'] !== 'admin' && $u['role'] !== 'moderator') {
            friendly_error('Staff access required.', 403);
        }
    }

    // Absolute site URL for any internal path ('list.php', 'tools/', ...).
    function url(string $path = ''): string
    {
        $base = rtrim((string)($GLOBALS['CFG']['base_url'] ?? ''), '/');
        if ($base === '') {
            return $path;
        }
        if ($path === '') {
            return $base;
        }
        return $base . '/' . ltrim($path, '/');
    }

    function is_suspended(): bool
    {
        $u = current_user();
        return $u !== null && $u['status'] !== 'active';
    }

    function ip_is_banned(): bool
    {
        static $cache = null;
        if ($cache !== null) {
            return $cache;
        }
        $cache = false;
        try {
            $stmt = db()->prepare('SELECT 1 FROM banned_ips WHERE ip = ? LIMIT 1');
            $stmt->execute([client_ip()]);
            $cache = $stmt->fetch() !== false;
        } catch (Throwable $t) {
            $cache = false;
        }
        return $cache;
    }

    function ban_ip(string $ip, string $reason = '', ?int $by = null): bool
    {
        try {
            db()->prepare('INSERT INTO banned_ips (ip, reason, banned_by) VALUES (?, ?, ?)')
                ->execute([mb_substr($ip, 0, 45), mb_substr($reason, 0, 255), $by]);
            return true;
        } catch (Throwable $t) {
            return false;
        }
    }

    function unban_ip(string $ip): bool
    {
        try {
            db()->prepare('DELETE FROM banned_ips WHERE ip = ?')->execute([$ip]);
            return true;
        } catch (Throwable $t) {
            return false;
        }
    }

    function clean_hex_color(?string $color): string
    {
        if ($color === null || preg_match('/^#[0-9a-fA-F]{6}$/', $color) !== 1) {
            return '';
        }
        return strtolower($color);
    }

    function user_display_name(array $user): string
    {
        return $user['username'];
    }


    function paste_colors(): array
    {
    return [
        // Reds
        '#e74c3c', '#c0392b', '#ff0000', '#ff3333', '#ff6666',
        '#ff7f7f', '#dc143c', '#b22222', '#8b0000', '#ff4d4d',

        // Oranges
        '#e67e22', '#f39c12', '#ff8c00', '#ffa500', '#ff7a00',
        '#ff9500', '#ff6b00', '#ff5722', '#ff4500', '#ffb347',

        // Yellows
        '#f1c40f', '#f39c12', '#ffd700', '#ffff00', '#ffea00',
        '#fff000', '#ffcc00', '#ffdf00', '#fada5e', '#ffe066',

        // Greens
        '#2ecc71', '#27ae60', '#00ff00', '#00cc44', '#32cd32',
        '#228b22', '#008000', '#006400', '#7fff00', '#00fa9a',
        '#00ff7f', '#3cb371', '#98fb98', '#90ee90', '#66ff66',

        // Teals
        '#1abc9c', '#16a085', '#00ffff', '#00ced1', '#20b2aa',
        '#40e0d0', '#48d1cc', '#00bfa5', '#00e5c3', '#2de2e6',

        // Blues
        '#3498db', '#2980b9', '#0000ff', '#0066ff', '#0080ff',
        '#00aaff', '#1e90ff', '#4169e1', '#4682b4', '#00bfff',
        '#87ceeb', '#6495ed', '#4facfe', '#5dade2', '#0077ff',

        // Purples
        '#9b59b6', '#8e44ad', '#800080', '#9400d3', '#9932cc',
        '#ba55d3', '#8a2be2', '#6a0dad', '#7b2cbf', '#a855f7',
        '#c084fc', '#d946ef', '#bf40bf', '#e066ff', '#b026ff',

        // Pinks
        '#ff69b4', '#ff1493', '#ff00ff', '#f500a8', '#ff4081',
        '#ff6ec7', '#ff85c1', '#ffb6c1', '#ff77aa', '#ff00aa',

        // Browns
        '#8b4513', '#a0522d', '#d2691e', '#cd853f', '#deb887',
        '#f4a460', '#c68642', '#795548', '#6f4e37', '#964b00',

        // Grays
        '#95a5a6', '#7f8c8d', '#808080', '#696969', '#a9a9a9',
        '#c0c0c0', '#d3d3d3', '#f5f5f5', '#36454f', '#708090',

        // Dark colors
        '#111111', '#1a1a1a', '#222222', '#333333', '#000000',
        '#0f0f0f', '#121212', '#181818', '#202020', '#282828',

        // Light colors
        '#ffffff', '#fffafa', '#f8f9fa', '#f0f8ff', '#f5f5dc',
        '#fff0f5', '#f0fff0', '#fffff0', '#f0ffff', '#faf0ff',

        // Special / neon
        '#39ff14', '#00ffcc', '#00ffff', '#ff00ff', '#ff0099',
        '#ccff00', '#7fff00', '#00ff66', '#ff6600', '#ff0066',
        '#6600ff', '#0066ff', '#00ffff', '#ffff00', '#ff00ff',
    ];
    }



    function color_select(string $name, string $selected = ''): string
    {
        $html = '<select class="form-select" name="' . e($name) . '">';
        $html .= '<option value="">Default</option>';
        foreach (paste_colors() as $c) {
            $sel = $c === $selected ? ' selected' : '';
            $html .= '<option value="' . $c . '"' . $sel . '>' . $c . '</option>';
        }
        return $html . '</select>';
    }

    function paste_border_style(?string $color): string
    {
        $c = clean_hex_color($color);
        return $c !== '' ? ' style="border-left: 6px solid ' . $c . ';"' : '';
    }

    // ——— Follow system ———

    function is_following(int $followerId, int $followedId): bool
    {
        try {
            $stmt = db()->prepare('SELECT 1 FROM follows WHERE follower_id = ? AND followed_id = ? LIMIT 1');
            $stmt->execute([$followerId, $followedId]);
            return $stmt->fetch() !== false;
        } catch (Throwable $t) {
            return false;
        }
    }

    function follower_count(int $userId): int
    {
        try {
            $stmt = db()->prepare('SELECT COUNT(*) FROM follows WHERE followed_id = ?');
            $stmt->execute([$userId]);
            return (int)$stmt->fetchColumn();
        } catch (Throwable $t) {
            return 0;
        }
    }

    function following_count(int $userId): int
    {
        try {
            $stmt = db()->prepare('SELECT COUNT(*) FROM follows WHERE follower_id = ?');
            $stmt->execute([$userId]);
            return (int)$stmt->fetchColumn();
        } catch (Throwable $t) {
            return 0;
        }
    }

    // Follows a user (logged-in current user), returns false on failure / self-follow.
    function follow_user(int $targetId): bool
    {
        $me = current_user();
        if ($me === null || (int)$me['id'] === $targetId) {
            return false;
        }
        try {
            $already = is_following((int)$me['id'], $targetId);
            db()->prepare('INSERT IGNORE INTO follows (follower_id, followed_id) VALUES (?, ?)')
                ->execute([(int)$me['id'], $targetId]);
            if (!$already) {
                push_notification($targetId, 'follow', (int)$me['id'], null, $me['username'] . ' started following you');
            }
            return true;
        } catch (Throwable $t) {
            return false;
        }
    }

    function unfollow_user(int $targetId): bool
    {
        $me = current_user();
        if ($me === null) {
            return false;
        }
        try {
            db()->prepare('DELETE FROM follows WHERE follower_id = ? AND followed_id = ?')
                ->execute([(int)$me['id'], $targetId]);
            return true;
        } catch (Throwable $t) {
            return false;
        }
    }

    // ——— Notifications ———
    // Types: 'follow' (someone followed you), 'paste' (new paste from someone you follow).

    function push_notification(int $userId, string $type, ?int $actorId, ?string $pasteId, string $message): void
    {
        try {
            db()->prepare(
                'INSERT INTO notifications (user_id, type, actor_id, paste_id, message) VALUES (?, ?, ?, ?, ?)'
            )->execute([$userId, $type, $actorId, $pasteId !== null && $pasteId !== '' ? $pasteId : null, mb_substr($message, 0, 255)]);
        } catch (Throwable $t) {
            error_log('[push_notification] ' . $t->getMessage());
        }
    }

    // Notifies every follower of $authorId that a new paste was published
    // (only called when the paste's notify_followers option is enabled).
    function notify_followers(int $authorId, string $pasteId, string $title): void
    {
        try {
            $me = current_user();
            $username = ($me['username'] ?? '') !== '' ? $me['username'] : 'Someone';
            $stmt = db()->prepare('SELECT follower_id FROM follows WHERE followed_id = ?');
            $stmt->execute([$authorId]);
            $msg = mb_substr($username . ' posted a new paste: ' . $title, 0, 255);
            $ins = db()->prepare('INSERT INTO notifications (user_id, type, actor_id, paste_id, message) VALUES (?, ?, ?, ?, ?)');
            foreach ($stmt->fetchAll() as $f) {
                $ins->execute([(int)$f['follower_id'], 'paste', $authorId, $pasteId, $msg]);
            }
        } catch (Throwable $t) {
            error_log('[notify_followers] ' . $t->getMessage());
        }
    }

    function fetch_notifications(int $userId, int $limit = 50): array
    {
        try {
            $stmt = db()->prepare(
                'SELECT n.id, n.type, n.message, n.is_read, n.created_at, n.paste_id,
                        u.id AS actor_id, u.username AS actor_name, u.profile_color AS actor_color
                 FROM notifications n
                 LEFT JOIN users u ON u.id = n.actor_id
                 WHERE n.user_id = ?
                 ORDER BY n.created_at DESC
                 LIMIT ' . max(1, min($limit, 200))
            );
            $stmt->execute([$userId]);
            return $stmt->fetchAll();
        } catch (Throwable $t) {
            return [];
        }
    }

    function unread_notifications(int $userId): int
    {
        try {
            $stmt = db()->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0');
            $stmt->execute([$userId]);
            return (int)$stmt->fetchColumn();
        } catch (Throwable $t) {
            return 0;
        }
    }

    function mark_notifications_read(int $userId): void
    {
        try {
            db()->prepare('UPDATE notifications SET is_read = 1 WHERE user_id = ?')->execute([$userId]);
        } catch (Throwable $t) {
            // best effort
        }
    }

    /*
     * SEO / social metadata.
     * Call set_meta([...]) before page_header() on pages with dynamic content
     * (paste view, profile, ...) to override site-wide defaults.
     * Keys: title, description, keywords, image, type, url
     */
    function set_meta(array $meta): void
    {
        $g = $GLOBALS['_meta'] ?? [];
        foreach ($meta as $k => $v) {
            $g[$k] = (string)$v;
        }
        $GLOBALS['_meta'] = $g;
    }

    // After a POST action, go back to an internal page only (never an external URL).
    function safe_return(string $fallback = 'index.php'): string
    {
        $r = (string)($_POST['return'] ?? $_GET['return'] ?? $_SERVER['HTTP_REFERER'] ?? '');
        if ($r === '') {
            return $fallback;
        }
        $allowed = ['view.php', 'profile.php', 'index.php', 'list.php', 'search.php', 'settings.php'];
        foreach ($allowed as $a) {
            if (strncmp($r, $a, strlen($a)) === 0) {
                return $r;
            }
        }
        return $fallback;
    }

    function vote_summary(string $type, string $entityId): array
    {
        $likes = 0;
        $dislikes = 0;
        try {
            $stmt = db()->prepare(
                'SELECT vote, COUNT(*) AS c FROM votes WHERE entity_type = ? AND entity_id = ? GROUP BY vote'
            );
            $stmt->execute([$type, $entityId]);
            foreach ($stmt->fetchAll() as $r) {
                if ((int)$r['vote'] === 1) {
                    $likes = (int)$r['c'];
                } elseif ((int)$r['vote'] === -1) {
                    $dislikes = (int)$r['c'];
                }
            }
        } catch (Throwable $t) {
            // votes table missing -> treat as no votes
        }
        return ['likes' => $likes, 'dislikes' => $dislikes];
    }

    function my_vote(string $type, string $entityId, int $userId): int
    {
        try {
            $stmt = db()->prepare(
                'SELECT vote FROM votes WHERE entity_type = ? AND entity_id = ? AND user_id = ? LIMIT 1'
            );
            $stmt->execute([$type, $entityId, $userId]);
            $row = $stmt->fetch();
            return $row ? (int)$row['vote'] : 0;
        } catch (Throwable $t) {
            return 0;
        }
    }

    function comment_count(string $type, string $entityId): int
    {
        try {
            $stmt = db()->prepare('SELECT COUNT(*) FROM comments WHERE entity_type = ? AND entity_id = ?');
            $stmt->execute([$type, $entityId]);
            return (int)$stmt->fetchColumn();
        } catch (Throwable $t) {
            return 0;
        }
    }

    function fetch_comments(string $type, string $entityId, int $limit = 200): array
    {
        try {
            $stmt = db()->prepare(
                'SELECT c.id, c.body, c.created_at, u.id AS user_id, u.username, u.profile_color, u.pfp
                 FROM comments c
                 JOIN users u ON u.id = c.user_id
                 WHERE c.entity_type = ? AND c.entity_id = ?
                 ORDER BY c.created_at ASC
                 LIMIT ' . $limit
            );
            $stmt->execute([$type, $entityId]);
            return $stmt->fetchAll();
        } catch (Throwable $t) {
            return [];
        }
    }

    // Renders the like/dislike button group in Bootstrap. $myVote is -1, 0 or 1.
    function table_exists(PDO $pdo, string $table): bool
    {
        $s = $pdo->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?');
        $s->execute([$table]);
        return (int)$s->fetchColumn() > 0;
    }

    function column_exists(PDO $pdo, string $table, string $column): bool
    {
        $s = $pdo->prepare('SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?');
        $s->execute([$table, $column]);
        return (int)$s->fetchColumn() > 0;
    }

    // Applies any missing schema (votes/comments tables, users.alias/profile_views,
    // pastes.description/tags) — idempotent and safe to run on every page render.
    function schema_ensure(bool $force = false): void
    {
        static $ran = false;
        if ($ran && !$force) {
            return;
        }
        $ran = true;
        $cfg = $GLOBALS['CFG'] ?? [];
        if (empty($cfg['auto_migrate'])) {
            return;
        }
        try {
            $pdo = db();
            // Not installed yet — nothing to migrate. Checked first so this is a
            // cheap no-op on the setup page instead of a list of failing ALTERs.
            if (!table_exists($pdo, 'pastes')) {
                return;
            }
            // Abuse/DMCA/legal request queue (filled by legal.php). Run FIRST and
            // in its own try/catch so a failing migration elsewhere can never
            // prevent the request queue from existing (admin/staff need it).
            try {
                if (!table_exists($pdo, 'reports')) {
                    $pdo->exec(
                        'CREATE TABLE IF NOT EXISTS reports (
                            id INT AUTO_INCREMENT PRIMARY KEY,
                            type VARCHAR(20) NOT NULL DEFAULT \'abuse\',
                            name VARCHAR(100) NOT NULL,
                            contact VARCHAR(120) NOT NULL,
                            target_url VARCHAR(255) NOT NULL,
                            details TEXT NOT NULL,
                            status VARCHAR(20) NOT NULL DEFAULT \'open\',
                            ip VARCHAR(45) NULL,
                            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                            resolution TEXT NULL,
                            resolved_by INT NULL,
                            resolved_at DATETIME NULL,
                            KEY idx_reports_status (status, created_at)
                        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
                    );
                    log_activity('schema_migrate', 'created reports table');
                } else {
                    foreach ([
                        'ip' => 'VARCHAR(45) NULL',
                        'resolution' => 'TEXT NULL',
                        'resolved_by' => 'INT NULL',
                        'resolved_at' => 'DATETIME NULL',
                    ] as $col => $def) {
                        if (!column_exists($pdo, 'reports', $col)) {
                            $pdo->exec('ALTER TABLE reports ADD COLUMN ' . $col . ' ' . $def);
                        }
                    }
                    // Also make sure every column legal.php writes to exists; if a
                    // column is missing the INSERT would fail for real users.
                    foreach ([
                        'type' => 'VARCHAR(20) NOT NULL DEFAULT \'abuse\'',
                        'name' => 'VARCHAR(100) NOT NULL',
                        'contact' => 'VARCHAR(120) NOT NULL',
                        'target_url' => 'VARCHAR(255) NOT NULL',
                        'details' => 'TEXT NOT NULL',
                        'status' => 'VARCHAR(20) NOT NULL DEFAULT \'open\'',
                    ] as $col => $def) {
                        if (!column_exists($pdo, 'reports', $col)) {
                            $pdo->exec('ALTER TABLE reports ADD COLUMN ' . $col . ' ' . $def);
                        }
                    }
                }
            } catch (Throwable $t) {
                error_log('[schema_ensure reports] ' . $t->getMessage());
            }
            if (!table_exists($pdo, 'votes') || !table_exists($pdo, 'comments')) {
                $pdo->exec(
                    'CREATE TABLE IF NOT EXISTS votes (
                        id INT AUTO_INCREMENT PRIMARY KEY,
                        entity_type VARCHAR(10) NOT NULL,
                        entity_id VARCHAR(40) NOT NULL,
                        user_id INT NOT NULL,
                        vote TINYINT NOT NULL DEFAULT 1,
                        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                        UNIQUE KEY uq_vote (entity_type, entity_id, user_id),
                        KEY idx_vote_entity (entity_type, entity_id),
                        KEY idx_vote_user (user_id)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
                );
                $pdo->exec(
                    'CREATE TABLE IF NOT EXISTS comments (
                        id INT AUTO_INCREMENT PRIMARY KEY,
                        entity_type VARCHAR(10) NOT NULL,
                        entity_id VARCHAR(40) NOT NULL,
                        user_id INT NOT NULL,
                        body TEXT NOT NULL,
                        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                        KEY idx_comment_entity (entity_type, entity_id, created_at),
                        KEY idx_comment_user (user_id)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
                );
                log_activity('schema_migrate', 'created votes/comments tables');
            }
            $userCols = [
                'alias' => 'VARCHAR(50) NULL',
                'profile_views' => 'INT UNSIGNED NOT NULL DEFAULT 0',
                'tagline' => 'VARCHAR(120) NULL',
                'pronouns' => 'VARCHAR(40) NULL',
                'skills' => 'VARCHAR(255) NULL',
                'bg_image' => 'VARCHAR(255) NULL',
                // v2.0: richer profiles + custom backgrounds.
                'occupation' => 'VARCHAR(120) NULL',
                'education' => 'VARCHAR(255) NULL',
                'languages' => 'VARCHAR(255) NULL',
                'hobbies' => 'VARCHAR(255) NULL',
                'quote' => 'VARCHAR(280) NULL',
                'birthdate' => 'DATE NULL',
                'status_msg' => 'VARCHAR(160) NULL',
                'github' => 'VARCHAR(100) NULL',
                'twitch' => 'VARCHAR(100) NULL',
                'tiktok' => 'VARCHAR(100) NULL',
                'instagram' => 'VARCHAR(100) NULL',
                'reddit' => 'VARCHAR(100) NULL',
                'snapchat' => 'VARCHAR(100) NULL',
                'bluesky' => 'VARCHAR(100) NULL',
                'threads' => 'VARCHAR(100) NULL',
                'linkedin' => 'VARCHAR(255) NULL',
                'bg_mode' => 'VARCHAR(10) NOT NULL DEFAULT \'none\'',
                'bg_fit' => 'VARCHAR(10) NOT NULL DEFAULT \'cover\'',
                'bg_color' => 'VARCHAR(7) NULL',
                'bg_gradient' => 'VARCHAR(300) NULL',
                'bg_veil' => 'TINYINT UNSIGNED NOT NULL DEFAULT 55',
                'bg_blur' => 'TINYINT(1) NOT NULL DEFAULT 0',
                // v2.x: account recovery key (salted SHA-256) + encrypted registration IP.
                'recovery_hash' => 'VARCHAR(128) NULL',
                'recovery_ip' => 'VARCHAR(255) NULL',
                // v2.x: per-user site-wide UI theme (background + layout + accent).
                'ui_mode' => 'VARCHAR(10) NOT NULL DEFAULT \'none\'',
                'ui_color' => 'VARCHAR(7) NULL',
                'ui_gradient' => 'VARCHAR(300) NULL',
                'ui_layout' => 'VARCHAR(10) NOT NULL DEFAULT \'default\'',
                'accent_color' => 'VARCHAR(7) NULL',
            ];
            foreach ($userCols as $name => $def) {
                if (!column_exists($pdo, 'users', $name)) {
                    $pdo->exec('ALTER TABLE users ADD COLUMN ' . $name . ' ' . $def);
                }
            }
            $pasteCols = [
                'description' => 'VARCHAR(255) NULL',
                'tags' => 'VARCHAR(255) NULL',
                'password_hash' => 'VARCHAR(255) NULL',
                'notify_followers' => 'TINYINT(1) NOT NULL DEFAULT 1',
            ];
            foreach ($pasteCols as $name => $def) {
                if (!column_exists($pdo, 'pastes', $name)) {
                    $pdo->exec('ALTER TABLE pastes ADD COLUMN ' . $name . ' ' . $def);
                }
            }
            // Followers + notifications (social features).
            if (!table_exists($pdo, 'follows') || !table_exists($pdo, 'notifications')) {
                $pdo->exec(
                    'CREATE TABLE IF NOT EXISTS follows (
                        id INT AUTO_INCREMENT PRIMARY KEY,
                        follower_id INT NOT NULL,
                        followed_id INT NOT NULL,
                        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                        UNIQUE KEY uq_follow (follower_id, followed_id),
                        KEY idx_follow_followed (followed_id),
                        KEY idx_follow_follower (follower_id)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
                );
                $pdo->exec(
                    'CREATE TABLE IF NOT EXISTS notifications (
                        id INT AUTO_INCREMENT PRIMARY KEY,
                        user_id INT NOT NULL,
                        type VARCHAR(20) NOT NULL,
                        actor_id INT NULL,
                        paste_id VARCHAR(12) NULL,
                        message VARCHAR(255) NOT NULL,
                        is_read TINYINT(1) NOT NULL DEFAULT 0,
                        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                        KEY idx_notif_user (user_id, is_read, created_at),
                        KEY idx_notif_paste (paste_id)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
                );
                log_activity('schema_migrate', 'created follows/notifications tables');
            }
            // Short-link / click-tracker system (links, link_clicks, geo_cache).
            if (!table_exists($pdo, 'links')) {
                $pdo->exec(
                    'CREATE TABLE IF NOT EXISTS links (
                        id INT AUTO_INCREMENT PRIMARY KEY,
                        code VARCHAR(16) NOT NULL,
                        user_id INT NULL,
                        manage_key VARCHAR(32) NULL,
                        target_url VARCHAR(2048) NOT NULL,
                        title VARCHAR(120) NULL,
                        tracking TINYINT(1) NOT NULL DEFAULT 1,
                        clicks INT UNSIGNED NOT NULL DEFAULT 0,
                        last_click DATETIME NULL,
                        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                        UNIQUE KEY uq_links_code (code),
                        KEY idx_links_user (user_id)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
                );
                $pdo->exec(
                    'CREATE TABLE IF NOT EXISTS link_clicks (
                        id INT AUTO_INCREMENT PRIMARY KEY,
                        link_id INT NOT NULL,
                        ip VARCHAR(45) NULL,
                        ua VARCHAR(255) NULL,
                        referer VARCHAR(512) NULL,
                        country VARCHAR(64) NULL,
                        region VARCHAR(64) NULL,
                        city VARCHAR(64) NULL,
                        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                        KEY idx_clicks_link (link_id, created_at)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
                );
                $pdo->exec(
                    'CREATE TABLE IF NOT EXISTS geo_cache (
                        ip VARCHAR(45) PRIMARY KEY,
                        country VARCHAR(64) NULL,
                        region VARCHAR(64) NULL,
                        city VARCHAR(64) NULL,
                        fetched_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
                );
                log_activity('schema_migrate', 'created links/link_clicks/geo_cache tables');
            }
            // Richer IP lookup data (ISP + VPN/proxy/Tor/crawler flags) for tracker analytics.
            $geoCols = [
                'isp' => 'VARCHAR(120) NULL',
                'asn' => 'VARCHAR(120) NULL',
                'is_proxy' => 'TINYINT(1) NOT NULL DEFAULT 0',
                'is_vpn' => 'TINYINT(1) NOT NULL DEFAULT 0',
                'is_tor' => 'TINYINT(1) NOT NULL DEFAULT 0',
                'is_crawler' => 'TINYINT(1) NOT NULL DEFAULT 0',
            ];
            foreach ($geoCols as $col => $def) {
                if (table_exists($pdo, 'geo_cache') && !column_exists($pdo, 'geo_cache', $col)) {
                    $pdo->exec('ALTER TABLE geo_cache ADD COLUMN ' . $col . ' ' . $def);
                }
            }
            // Online presence (live "N online" counter).
            if (!table_exists($pdo, 'online')) {
                $pdo->exec(
                    'CREATE TABLE IF NOT EXISTS online (
                        token VARCHAR(64) PRIMARY KEY,
                        ip VARCHAR(45) NOT NULL,
                        last_seen DATETIME NOT NULL,
                        KEY idx_online_seen (last_seen)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
                );
                log_activity('schema_migrate', 'created online table');
            }
        } catch (Throwable $t) {
            error_log('[schema_ensure] ' . $t->getMessage());
        }
    }

    // ——— Online presence (live counter + heartbeat) ———

    // Visitors seen within this window count as "online".
    function online_window_seconds(): int { return 60; }
    // Rows older than this are purged on each heartbeat.
    function online_stale_seconds(): int { return 90; }

    // Records this visitor's presence (session token) and returns the live count.
    function online_ping(?string $token = null): int
    {
        try {
            $pdo = db();
            $ip = client_ip();
            $tok = ($token !== null && $token !== '' && strlen($token) <= 64)
                ? $token
                : (function_exists('session_id') && session_id() !== '' ? session_id() : $ip);
            $pdo->prepare('INSERT INTO online (token, ip, last_seen) VALUES (?, ?, UTC_TIMESTAMP())
                ON DUPLICATE KEY UPDATE last_seen = UTC_TIMESTAMP()')
                ->execute([$tok, $ip]);
            $pdo->exec('DELETE FROM online WHERE last_seen < DATE_SUB(UTC_TIMESTAMP(), INTERVAL ' . online_stale_seconds() . ' SECOND)');
            return online_count();
        } catch (Throwable $t) {
            return 0;
        }
    }

    function online_count(): int
    {
        try {
            $pdo = db();
            $s = $pdo->prepare('SELECT COUNT(*) FROM online WHERE last_seen >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL ' . online_window_seconds() . ' SECOND)');
            $s->execute();
            return (int)$s->fetchColumn();
        } catch (Throwable $t) {
            return 0;
        }
    }

    // ——— Short-link / click-tracker helpers ———

    function generate_link_code(int $length = 5): string
    {
        $alphabet = 'abcdefghijkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $max = strlen($alphabet) - 1;
        $pdo = db();
        $guard = 0;
        do {
            $code = '';
            for ($i = 0; $i < $length; $i++) {
                $code .= $alphabet[random_int(0, $max)];
            }
            $stmt = $pdo->prepare('SELECT 1 FROM links WHERE code = ?');
            $stmt->execute([$code]);
            $guard++;
        } while ($stmt->fetch() && $guard < 1000);
        return $code;
    }

    // Fetches a URL server-side (JSON/HTML). Returns null on any failure.
    function http_get(string $url, int $timeout = 6): ?string
    {
        $url = filter_var($url, FILTER_VALIDATE_URL);
        if ($url === false) {
            return null;
        }
        $scheme = strtolower((string)parse_url($url, PHP_URL_SCHEME));
        if (!in_array($scheme, ['http', 'https'], true)) {
            return null;
        }
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => $timeout,
                CURLOPT_CONNECTTIMEOUT => $timeout,
                CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_USERAGENT => 'Mozilla/5.0 (KevBin Link Checker)',
            ]);
            $out = curl_exec($ch);
            $code = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            curl_close($ch);
            return is_string($out) && (int)$code < 400 ? $out : null;
        }
        $ctx = stream_context_create([
            'http' => [
                'timeout' => $timeout,
                'ignore_errors' => false,
                'user_agent' => 'Mozilla/5.0 (KevBin Link Checker)',
            ],
            'ssl' => ['verify_peer' => false, 'verify_peer_name' => false],
        ]);
        $out = @file_get_contents($url, false, $ctx);
        return is_string($out) ? $out : null;
    }

    // ——— Helpers ———
    // Geo info for an IP: DB-cached (30 days), else ip-api.com, else empty.
    function lookup_ip_geo(string $ip): array
    {
        $geo = ['country' => null, 'region' => null, 'city' => null, 'isp' => null, 'asn' => null, 'is_proxy' => 0, 'is_vpn' => 0, 'is_tor' => 0, 'is_crawler' => 0];
        if (!filter_var($ip, FILTER_VALIDATE_IP)) {
            return $geo;
        }
        try {
            $pdo = db();
            $stmt = $pdo->prepare('SELECT country, region, city, isp, asn, is_proxy, is_vpn, is_tor, is_crawler FROM geo_cache WHERE ip = ? AND fetched_at > DATE_SUB(UTC_TIMESTAMP(), INTERVAL 30 DAY)');
            $stmt->execute([$ip]);
            $row = $stmt->fetch();
            if ($row) {
                return [
                    'country' => $row['country'] !== null ? $row['country'] : null,
                    'region' => $row['region'] !== null ? $row['region'] : null,
                    'city' => $row['city'] !== null ? $row['city'] : null,
                    'isp' => $row['isp'] !== null ? $row['isp'] : null,
                    'asn' => $row['asn'] !== null ? $row['asn'] : null,
                    'is_proxy' => (int)$row['is_proxy'],
                    'is_vpn' => (int)$row['is_vpn'],
                    'is_tor' => (int)$row['is_tor'],
                    'is_crawler' => (int)$row['is_crawler'],
                ];
            }
            // ipwho.is gives geoposition + ISP/ASN and proxy/VPN/Tor/crawler flags in one call.
            $json = http_get('https://ipwho.is/' . rawurlencode($ip), 5);
            $data = $json !== null ? json_decode($json, true) : null;
            if (is_array($data) && isset($data['success']) && (bool)$data['success']) {
                $sec = $data['security'] ?? [];
                $conn = $data['connection'] ?? [];
                $geo = [
                    'country' => $data['country'] ?? null,
                    'region' => $data['region'] ?? null,
                    'city' => $data['city'] ?? null,
                    'isp' => $data['connection']['isp'] ?? ($data['isp'] ?? null),
                    'asn' => $conn['asn'] ?? ($data['connection']['org'] ?? null),
                    'is_proxy' => isset($sec['proxy']) && $sec['proxy'] ? 1 : 0,
                    'is_vpn' => isset($sec['vpn']) && $sec['vpn'] ? 1 : 0,
                    'is_tor' => isset($sec['tor']) && $sec['tor'] ? 1 : 0,
                    'is_crawler' => isset($sec['crawler']) && $sec['crawler'] ? 1 : 0,
                ];
            } else {
                // Fallback: classic ip-api.com geo lookup.
                $json = http_get('http://ip-api.com/json/' . rawurlencode($ip) . '?fields=status,country,regionName,city', 4);
                $data = $json !== null ? json_decode($json, true) : null;
                if (is_array($data) && ($data['status'] ?? '') === 'success') {
                    $geo = [
                        'country' => $data['country'] ?? null,
                        'region' => $data['regionName'] ?? null,
                        'city' => $data['city'] ?? null,
                        'isp' => null, 'asn' => null,
                        'is_proxy' => 0, 'is_vpn' => 0, 'is_tor' => 0, 'is_crawler' => 0,
                    ];
                }
            }
            $pdo->prepare(
                'INSERT INTO geo_cache (ip, country, region, city, isp, asn, is_proxy, is_vpn, is_tor, is_crawler)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE country = VALUES(country), region = VALUES(region), city = VALUES(city),
                    isp = VALUES(isp), asn = VALUES(asn), is_proxy = VALUES(is_proxy), is_vpn = VALUES(is_vpn),
                    is_tor = VALUES(is_tor), is_crawler = VALUES(is_crawler), fetched_at = UTC_TIMESTAMP()'
            )->execute([$ip, $geo['country'], $geo['region'], $geo['city'], $geo['isp'], $geo['asn'],
                (int)$geo['is_proxy'], (int)$geo['is_vpn'], (int)$geo['is_tor'], (int)$geo['is_crawler']]);
            error_log('[lookup_ip_geo] done ' . $ip);
        } catch (Throwable $t) {
            error_log('[lookup_ip_geo] ' . $t->getMessage());
        }
        return $geo;
    }

    // Turns a User-Agent string into [browser, os, device, is_bot].
    function parse_user_agent(?string $ua): array
    {
        $ua = (string)$ua;
        $browser = 'Unknown';
        $os = 'Unknown';
        $device = 'Computer';
        $isBot = false;

        if ($ua === '') {
            return ['browser' => $browser, 'os' => $os, 'device' => $device, 'is_bot' => $isBot];
        }
        $u = strtolower($ua);

        foreach (['googlebot', 'bingbot', 'duckduckbot', 'yandex', 'baiduspider', 'slurp', 'petalbot', 'applebot'] as $bot) {
            if (strpos($u, $bot) !== false) {
                $isBot = true;
                $browser = strtoupper(preg_replace('/bot.*/', '', $bot));
                $os = 'Bot';
                $device = 'Bot';
                return ['browser' => $browser, 'os' => $os, 'device' => $device, 'is_bot' => $isBot];
            }
        }
        if (preg_match('~(curl|wget|python-requests|okhttp|java/|go-http-client|libwww|powershell|node\.js)~i', $ua)) {
            return ['browser' => 'Script / CLI', 'os' => 'Automated', 'device' => 'Bot', 'is_bot' => true];
        }
        if (strpos($u, 'chrome') !== false) {
            $browser = 'Chrome';
        } elseif (strpos($u, 'firefox') !== false) {
            $browser = 'Firefox';
        } elseif (strpos($u, 'safari') !== false && strpos($u, 'chrome') === false) {
            $browser = 'Safari';
        } elseif (strpos($u, 'edg') !== false) {
            $browser = 'Edge';
        } elseif (strpos($u, 'opera') !== false || strpos($u, 'opr') !== false) {
            $browser = 'Opera';
        } elseif (strpos($u, 'msie') !== false || strpos($u, 'trident') !== false) {
            $browser = 'Internet Explorer';
        }

        if (strpos($u, 'windows') !== false) {
            $os = 'Windows';
        } elseif (strpos($u, 'android') !== false) {
            $os = 'Android';
        } elseif (strpos($u, 'iphone') !== false || strpos($u, 'ipad') !== false || strpos($u, 'ios') !== false) {
            $os = 'iOS';
        } elseif (strpos($u, 'mac os') !== false) {
            $os = 'macOS';
        } elseif (strpos($u, 'linux') !== false) {
            $os = 'Linux';
        } elseif (strpos($u, 'crkey') !== false) {
            $os = 'ChromeOS';
        }

        if (strpos($u, 'mobile') !== false) {
            $device = 'Phone';
        } elseif (strpos($u, 'ipad') !== false || preg_match('~tablet|silk~i', $ua)) {
            $device = 'Tablet';
        } elseif (strpos($u, 'bot') !== false || preg_match('~spider|crawl|headless~i', $ua)) {
            $device = 'Bot';
            $isBot = true;
        }

        return ['browser' => $browser, 'os' => $os, 'device' => $device, 'is_bot' => $isBot];
    }

    function link_manage_ok(array $link, ?array $me, ?string $key): bool
    {
        if (!empty($link['user_id']) && $me !== null && (int)$link['user_id'] === (int)$me['id']) {
            return true;
        }
        return (bool)$link['manage_key'] && $key !== '' && hash_equals((string)$link['manage_key'], (string)$key);
    }

    function link_short_url(string $code): string
    {
        $base = (string)($GLOBALS['CFG']['base_url'] ?? '');
        return rtrim($base, '/') . '/s/' . rawurlencode($code);
    }

    function vote_buttons(string $type, string $entityId, int $myVote, array $summary, string $return): string
    {
        $me = current_user();
        $btn = function (int $dir, string $label, int $count) use ($type, $entityId, $myVote, $return, $me): string {
            $on = $myVote === $dir;
            $cls = $on ? ($dir === 1 ? 'btn-success' : 'btn-danger') : 'btn-outline-light';
            if ($me === null) {
                return '<a class="btn btn-sm ' . $cls . '" href="login.php?return=' . urlencode($return)
                    . '" title="Log in to vote">' . $label . ' <span class="badge text-bg-dark">' . (int)$count . '</span></a>';
            }
            $title = $on ? 'Remove your vote' : ($dir === 1 ? 'Like' : 'Dislike');
            $h = '<form method="post" action="vote.php" class="d-inline">
                <input type="hidden" name="csrf" value="' . e(csrf_token()) . '">
                <input type="hidden" name="type" value="' . e($type) . '">
                <input type="hidden" name="target" value="' . e($entityId) . '">
                <input type="hidden" name="vote" value="' . $dir . '">
                <input type="hidden" name="return" value="' . e($return) . '">';
            $h .= '<button type="submit" class="btn btn-sm ' . $cls . '" title="' . e($title) . '">' . $label . ' <span class="badge text-bg-dark">' . (int)$count . '</span></button>';
            $h .= '</form>';
            return $h;
        };
        return '<div class="d-inline-flex align-items-center gap-1">
                ' . $btn(1, '👍', $summary['likes']) . '
                ' . $btn(-1, '👎', $summary['dislikes']) . '
            </div>';
    }

    // ——— Custom captcha (session-held code, SHA-256 verified, one-shot) ———
    function recovery_salt(): string
    {
        $salt = (string)($GLOBALS['CFG']['recovery_salt'] ?? '');
        if ($salt === '') {
            $salt = 'kevbin-recovery-salt';
        }
        return $salt;
    }

    function recovery_key_hash(string $key): string
    {
        // Normalize: strip dashes/spaces so the key can be typed with or without them.
        $norm = strtolower(preg_replace('/[^a-f0-9]/i', '', $key));
        return hash_hmac('sha256', $norm, recovery_salt());
    }

    function user_update_recovery_key(int $userId): string
    {
        $key = strtoupper(bin2hex(random_bytes(32)));
        $key = implode('-', str_split($key, 4));
        $encIp = null;
        if (function_exists('openssl_encrypt') && in_array('aes-256-ctr', openssl_get_cipher_methods(), true)) {
            $encIp = base64_encode(openssl_encrypt(
                client_ip(),
                'aes-256-ctr',
                hash('sha256', recovery_salt(), true),
                OPENSSL_RAW_DATA,
                substr(hash('sha256', recovery_salt() . ':' . (int)$userId), 0, 16)
            ));
        }
        db()->prepare('UPDATE users SET recovery_hash = ?, recovery_ip = ? WHERE id = ?')
            ->execute([recovery_key_hash($key), $encIp, (int)$userId]);
        return $key;
    }

    function verify_recovery_key(string $username, string $key): ?array
    {
        $stmt = db()->prepare('SELECT id, username, role, status FROM users WHERE username = ?');
        $stmt->execute([$username]);
        $user = $stmt->fetch();
        if (!is_array($user) || empty($user['id'])) {
            return null;
        }
        $stmt2 = db()->prepare('SELECT recovery_hash FROM users WHERE id = ?');
        $stmt2->execute([(int)$user['id']]);
        $hash = (string)$stmt2->fetchColumn();
        if ($hash === '' || !hash_equals($hash, recovery_key_hash($key))) {
            return null;
        }
        return $user;
    }

    function captcha_issue(bool $force = false): void
    {
        if ($force || !isset($_SESSION['captcha_h']) || !isset($_SESSION['captcha_t'])
            || time() - (int)$_SESSION['captcha_t'] > 600) {
            $chars = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789'; // no I/L/O/0/1
            $code = '';
            $len = strlen($chars) - 1;
            for ($i = 0; $i < 5; $i++) {
                $code .= $chars[random_int(0, $len)];
            }
            $_SESSION['captcha_code'] = $code;
            $_SESSION['captcha_h'] = hash('sha256', strtolower($code));
            $_SESSION['captcha_t'] = time();
        }
    }

    function captcha_current(): string
    {
        captcha_issue();
        return (string)($_SESSION['captcha_code'] ?? '');
    }

    function captcha_ok(string $answer): bool
    {
        $stored = (string)($_SESSION['captcha_h'] ?? '');
        unset($_SESSION['captcha_h'], $_SESSION['captcha_code'], $_SESSION['captcha_t']);
        if ($stored === '') {
            return false;
        }
        return hash_equals($stored, hash('sha256', strtolower(trim($answer))));
    }

    function send_security_headers(): void
    {
        if (headers_sent()) {
            return;
        }
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: SAMEORIGIN');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header('Permissions-Policy: geolocation=(), microphone=(), camera=(), payment=(), usb=()');
        header('Cross-Origin-Opener-Policy: same-origin');
        header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; img-src 'self' data: http: https:; font-src 'self'; connect-src 'self'; media-src 'self'; object-src 'none'; base-uri 'self'; form-action 'self'; frame-ancestors 'self'");
        if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
            header('Strict-Transport-Security: max-age=15552000');
        }
    }

    // CSS overrides that apply the signed-in user's own site-wide UI theme.
    function user_theme_css(?array $me = null): string
    {
        $me = $me ?? current_user();
        if ($me === null) {
            return '';
        }
        $css = [];
        $mode = (string)($me['ui_mode'] ?? 'none');
        $layout = (string)($me['ui_layout'] ?? 'default');
        $bg = '';
        $panel = 'rgba(13,13,15,.82)';
        $panel2 = 'rgba(20,20,24,.85)';
        if ($mode === 'color') {
            $c = clean_hex_color((string)($me['ui_color'] ?? ''));
            if ($c !== '') {
                $bg = $c;
                $panel = 'rgba(10,10,12,.72)';
                $panel2 = 'rgba(16,16,20,.78)';
            }
        } elseif ($mode === 'gradient') {
            $g = trim((string)($me['ui_gradient'] ?? ''));
            if ($g !== '' && preg_match('#^[a-z]*-gradient\([\w\s(),.#%+_\-/]*\)$#i', $g)) {
                $bg = $g;
                $panel = 'rgba(8,8,10,.72)';
                $panel2 = 'rgba(14,14,18,.78)';
            }
        }
        if ($bg !== '') {
            $css[] = ':root { --rich-black: transparent; --panel: ' . $panel . '; --panel-2: ' . $panel2 . '; --line: rgba(255,255,255,.14); }';
            $css[] = 'body { background: ' . $bg . ' !important; }';
            $css[] = 'body::before { content: ""; position: fixed; inset: 0; background: rgba(0,0,0,.45); z-index: -1; pointer-events: none; }';
            $css[] = 'main, .container { position: relative; }';
        }
        $accent = clean_hex_color((string)($me['accent_color'] ?? ''));
        if ($accent !== '') {
            $css[] = ':root { --accent1: ' . $accent . '; --accent2: ' . $accent . '; }';
        }
        if ($layout === 'compact') {
            $css[] = '.container { max-width: 900px; }';
        } elseif ($layout === 'wide') {
            $css[] = '.container { max-width: 1440px; }';
        }
        return implode("\n", $css);
    }

    function page_header(string $title): void
    {
        send_security_headers();
        $cfg = $GLOBALS['CFG'];
        $me = current_user();

        // IP ban enforcement (admins are never blocked)
        if (ip_is_banned() && !is_admin()) {
            http_response_code(403);
            exit('<!DOCTYPE html><html lang="en"><head><meta charset="utf-8"><title>Banned</title></head>'
                . '<body style="background:#070707;color:#f2f2f2;font-family:sans-serif;display:flex;'
                . 'align-items:center;justify-content:center;height:100vh;margin:0;">'
                . '<div style="text-align:center"><h1>403 Banned</h1><p>This IP address is banned from '
                . e($cfg['site_name']) . '.</p></div></body></html>');
        }

        $unsafeFlash = flash_get();
        $M = $GLOBALS['_meta'] ?? [];
        $site = (string)$cfg['site_name'];
        $base = (string)$cfg['base_url'];
        $desc = (string)($M['description'] ?? ($cfg['meta_description'] ?? 'Paste & share text online. Free, anonymous and super secure.'));
        $keywords = (string)($M['keywords'] ?? '');
        $ogTitle = (string)($M['title'] ?? $title);
        $ogType = (string)($M['type'] ?? 'website');
        $ogUrl = (string)($M['url'] ?? $base);
        $ogImage = (string)($M['image'] ?? ($cfg['logo_url'] ?? ''));
        $twitterHandle = (string)($cfg['twitter_handle'] ?? '');
        $gVerification = (string)($cfg['google_site_verification'] ?? '');
        ?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="dark">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title . ' - ' . $site) ?></title>
<meta name="description" content="<?= e($desc) ?>">
<?php if ($keywords !== ''): ?><meta name="keywords" content="<?= e($keywords) ?>"><?php endif; ?>
<?php if ($gVerification !== ''): ?><meta name="google-site-verification" content="<?= e($gVerification) ?>"><?php endif; ?>
<meta name="robots" content="index, follow">
<link rel="canonical" href="<?= e($ogUrl) ?>">
<meta name="theme-color" content="#5865f2">
<meta property="og:site_name" content="<?= e($site) ?>">
<meta property="og:type" content="<?= e($ogType) ?>">
<meta property="og:title" content="<?= e($ogTitle) ?>">
<meta property="og:description" content="<?= e($desc) ?>">
<meta property="og:url" content="<?= e($ogUrl) ?>">
<?php if ($ogImage !== ''): ?><meta property="og:image" content="<?= e($ogImage) ?>"><?php endif; ?>
<meta name="twitter:card" content="<?= $ogImage !== '' ? 'summary_large_image' : 'summary' ?>">
<meta name="twitter:title" content="<?= e($ogTitle) ?>">
<meta name="twitter:description" content="<?= e($desc) ?>">
<?php if ($ogImage !== ''): ?><meta name="twitter:image" content="<?= e($ogImage) ?>"><?php endif; ?>
<?php if ($twitterHandle !== ''): ?><meta name="twitter:site" content="<?= e($twitterHandle) ?>"><?php endif; ?>
<link rel="stylesheet" href="/assets/bootstrap.min.css">
<style>
    @font-face { font-family: 'Space Grotesk'; font-style: normal; font-weight: 100 900; font-display: swap; src: url(/assets/fonts/spacegrotesk-var.woff2) format('woff2'); }
    @font-face { font-family: 'JetBrains Mono'; font-style: normal; font-weight: 100 900; font-display: swap; src: url(/assets/fonts/jetbrainsmono-var.woff2) format('woff2'); }
    :root {
        --rich-black: #070707;
        --panel: #101010;
        --panel-2: #151515;
        --line: rgba(255,255,255,.09);
        --text: #f2f2f2;
        --dim: #8f8f8f;
        --accent1: #5865f2;
        --accent2: #9146ff;
    }
    html { scroll-behavior: smooth; }
    body {
        background: var(--rich-black); color: var(--text);
        font-family: "Space Grotesk", sans-serif;
        opacity: 0; animation: bodyfade .45s ease forwards;
    }
    h1,h2,h3,h4,h5,.navbar-brand { font-family: "Space Grotesk", sans-serif; }
    pre.paste-content, code { font-family: "JetBrains Mono", monospace; }
    pre.paste-content { background: #0b0b0b; border: 1px solid var(--line); padding: 1rem; border-radius: 10px; white-space: pre-wrap; word-break: break-word; }
    .navbar {
        background: rgba(7,7,7,.75);
        backdrop-filter: blur(12px);
        border-bottom: 1px solid var(--line);
        position: sticky; top: 0; z-index: 1000;
    }
    .navbar-brand { font-size: 1.35rem; font-weight: 700; letter-spacing: 1px; }
    .nav-link { font-weight: 500; }
    .card {
        background: var(--panel); border: 1px solid var(--line); color: var(--text);
        border-radius: 14px; transition: transform .18s ease, box-shadow .18s ease, border-color .18s ease;
    }
    .card:hover { transform: translateY(-2px); box-shadow: 0 10px 30px rgba(0,0,0,.55); border-color: rgba(83,101,242,.45); }
    .list-group-item { background: var(--panel-2); border: 1px solid var(--line); color: var(--text); }
    .list-group-item:hover { transform: translateX(3px); border-color: rgba(83,101,242,.5); transition: all .15s ease; }
    .form-control, .form-select { background: #181818; border-color: var(--line); color: var(--text); }
    .form-control:focus, .form-select:focus { background: #181818; color: var(--text); border-color: var(--accent1); box-shadow: 0 0 0 .25rem rgba(88,101,242,.25); }
    .form-text { color: #777; }
    .btn { border-radius: 8px; font-weight: 600; transition: transform .1s ease, filter .15s ease; }
    .btn:active { transform: scale(.97); }
    .btn-primary { background: linear-gradient(135deg, var(--accent1), var(--accent2)); border: none; }
    .btn-primary:hover { filter: brightness(1.15); }
    .btn-outline-light { border-color: var(--line); color: var(--text); }
    .btn-outline-light:hover { background: rgba(255,255,255,.08); color: #fff; }
    .alert { border-radius: 10px; border: 1px solid var(--line); }
    .page-link, .page-item.active .page-link { background-color: var(--panel-2); border-color: var(--line); color: var(--text); }
    .page-item.active .page-link { background: linear-gradient(135deg, var(--accent1), var(--accent2)); }
    .text-secondary { color: var(--dim) !important; }
    a { color: var(--accent1); text-decoration: none; }
    a:hover { color: var(--accent2); }
    .pfp { width: 64px; height: 64px; border-radius: 50%; object-fit: cover; }
    .pfp-sm { width: 32px; height: 32px; border-radius: 50%; object-fit: cover; }
    .banner { width: 100%; height: 160px; object-fit: cover; border-radius: 12px; }

    /* long-word safety: unbroken strings (URLs, usernames, base64...) must wrap
       instead of inflating flex/grid items and shoving padding gaps around */
    .container, .card-body, .list-group-item, h1, h2, h3, h4, h5, h6, p, a, small,
    .navbar-brand, .nav-link, td, th, blockquote, .form-label, .form-text, .badge {
        overflow-wrap: anywhere;
    }
    .flex-grow-1 { min-width: 0; }
    img { max-width: 100%; }
    .btn { overflow-wrap: anywhere; }

    /* mobile: nav collapse panel, safe-area, touch targets, table scrolling */
    .navbar { padding-top: calc(.5rem + env(safe-area-inset-top)); }
    .navbar-toggler { border-color: var(--line); }
    .navbar-toggler:focus, .navbar-toggler:active { box-shadow: 0 0 0 .25rem rgba(88,101,242,.25); }
    @media (max-width: 991.98px) {
        .navbar-collapse { background: rgba(10,10,10,.97); border: 1px solid var(--line);
            border-radius: 12px; padding: .6rem .75rem; margin-top: .5rem; }
    }
    @media (max-width: 767.98px) {
        .banner { height: 110px; }
        .table { display: block; width: 100%; max-width: 100%; overflow-x: auto;
            -webkit-overflow-scrolling: touch; white-space: nowrap; }
        .btn-sm { min-height: 34px; }
        h1 { font-size: 1.3rem; }
        h2 { font-size: 1.1rem; }
        .profile-actions { align-items: flex-start !important; width: 100%; }
        .profile-actions .d-inline-flex { flex-wrap: wrap; }
    }
    .profile-head { flex-wrap: wrap; }
    @media (max-width: 575.98px) {
        .profile-head .flex-grow-1 { flex-basis: 100%; }
    }
    body { -webkit-tap-highlight-color: transparent; }

    /* smooth reveal on scroll (AOS-style) */
    .reveal { opacity: 0; transform: translateY(28px); transition: opacity .6s ease, transform .6s ease; }
    .reveal.in-view { opacity: 1; transform: none; }

    @keyframes bodyfade { from { opacity: 0; } to { opacity: 1; } }

    /* loading screen */
    #loader { position: fixed; inset: 0; background: var(--rich-black); display: flex; flex-direction: column;
        align-items: center; justify-content: center; gap: 18px; z-index: 9999;
        transition: opacity .4s ease, visibility .4s ease; }
    #loader.done { opacity: 0; visibility: hidden; }
    .loader-logo { font-size: 1.7rem; font-weight: 800; letter-spacing: 3px; color: var(--text);
        background: linear-gradient(90deg, var(--accent1), var(--accent2)); -webkit-background-clip: text; background-clip: text; color: transparent; }
    .loader-bar { width: 220px; height: 3px; background: #1c1c1c; border-radius: 99px; overflow: hidden; }
    .loader-bar span { display: block; height: 100%; width: 0; background: linear-gradient(90deg, var(--accent1), var(--accent2));
        animation: loadbar 3s ease forwards; }
    @keyframes loadbar { to { width: 100%; } }

    .glow-ring { width: 52px; height: 52px; border-radius: 50%;
        background: conic-gradient(from 0deg, var(--accent1), var(--accent2), transparent 70%);
        -webkit-mask: radial-gradient(farthest-side, transparent 62%, #000 64%);
        mask: radial-gradient(farthest-side, transparent 62%, #000 64%);
        animation: spin 1s linear infinite; }
    @keyframes spin { to { transform: rotate(360deg); } }

    ::selection { background: var(--accent1); color: #fff; }
    ::-webkit-scrollbar { width: 10px; height: 10px; }
    ::-webkit-scrollbar-track { background: #0c0c0c; }
    ::-webkit-scrollbar-thumb { background: #2a2a2a; border-radius: 99px; }
</style>
<?php if (user_theme_css() !== ''): ?>
<style id="user-theme"><?= user_theme_css() ?></style>
<?php endif; ?>
</head>
<body>
<div id="loader">
    <div class="glow-ring"></div>
    <div class="loader-logo"><?= e($cfg['site_name']) ?></div>
    <div class="loader-bar"><span></span></div>
</div>
<nav class="navbar navbar-expand-lg mb-4">
    <div class="container">
        <a class="navbar-brand fw-bold" href="<?= e($cfg['base_url']) ?>"><?= e($cfg['site_name']) ?></a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#site-nav"
            aria-controls="site-nav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="site-nav">
            <ul class="navbar-nav me-auto">
                <li class="nav-item"><a class="nav-link" href="<?= e(url('index.php?new=1')) ?>">New Paste</a></li>
                <li class="nav-item"><a class="nav-link" href="<?= e(url('list.php')) ?>">Browse</a></li>
                <li class="nav-item"><a class="nav-link" href="<?= e(url('search.php')) ?>">Search</a></li>
                <li class="nav-item"><a class="nav-link" href="<?= e(url('users.php')) ?>">Users</a></li>
                <li class="nav-item"><a class="nav-link" href="<?= e(url('short.php')) ?>">Shorten</a></li>
                <li class="nav-item"><a class="nav-link" href="<?= e(url('links.php')) ?>">My Links</a></li>
                <li class="nav-item"><a class="nav-link" href="<?= e(url('tools/')) ?>">Tools</a></li>
            </ul>
            <ul class="navbar-nav">
                <li class="nav-item">
                    <span class="nav-link" id="online-badge" title="People online right now (updates live)">
                        <span style="display:inline-block;width:8px;height:8px;border-radius:50%;background:#26d07c;margin-right:6px;box-shadow:0 0 6px #26d07c;"></span>
                        <span id="online-count"><?= online_ping() ?></span> online
                    </span>
                </li>
                <li class="nav-item">
                    <span class="nav-link text-success" title="No tracking, no history — we only store pastes you publish.">&#128274; Secure &amp; Untraceable</span>
                </li>
                <?php if ($me !== null): ?>
                    <li class="nav-item"><a class="nav-link" href="<?= e(url('profile.php?id=' . (int)$me['id'])) ?>"
                        style="color:<?= e(clean_hex_color($me['profile_color']) !== '' ? clean_hex_color($me['profile_color']) : '#ffffff') ?>"><?= e($me['username']) ?></a></li>
                    <li class="nav-item"><a class="nav-link" href="<?= e(url('settings.php')) ?>">Settings</a></li>
                    <?php $unreadCount = unread_notifications((int)$me['id']); ?>
                    <li class="nav-item"><a class="nav-link" href="<?= e(url('notifications.php')) ?>">Notifications<?php if ($unreadCount > 0): ?> <span class="badge bg-danger"><?= (int)$unreadCount ?></span><?php endif; ?></a></li>
                    <?php if (is_staff()): ?>
                        <li class="nav-item"><a class="nav-link" href="<?= e(url('admin.php')) ?>"><?= is_admin() ? 'Admin' : 'Staff' ?></a></li>
                    <?php endif; ?>
                    <li class="nav-item">
                        <form method="post" action="<?= e(url('logout.php')) ?>" class="m-0 p-0">
                            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                            <button type="submit" class="btn btn-link nav-link p-0 border-0 align-baseline">Logout</button>
                        </form>
                    </li>
                <?php else: ?>
                    <li class="nav-item"><a class="nav-link" href="<?= e(url('register.php')) ?>">Register</a></li>
                    <li class="nav-item"><a class="nav-link" href="<?= e(url('login.php')) ?>">Login</a></li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>
<?php if ($unsafeFlash): ?>
<div class="container">
    <div class="alert alert-<?= e($unsafeFlash['type'] === 'error' ? 'danger' : 'success') ?>"><?= e($unsafeFlash['msg']) ?></div>
</div>
<?php endif; ?>
        <?php
    }

    function page_footer(): void
    {
        ?>
<footer class="container py-4 text-center text-secondary">
    <small><?= e($GLOBALS['CFG']['site_name']) ?> — education project ·
        <a class="link-secondary" href="<?= e(url('tos.php')) ?>">Terms of Service</a> ·
        <a class="link-secondary" href="<?= e(url('privacy.php')) ?>">Privacy Policy</a> ·
        <a class="link-secondary" href="<?= e(url('legal.php')) ?>">DMCA / Law Enforcement</a> ·
        <a class="link-secondary" href="<?= e(url('api_docs.php')) ?>">API</a>
    </small>
</footer>
<script src="/assets/bootstrap.bundle.min.js"></script>
<script>
    // --- Anti-console / anti-DevTools protection (site-wide, hard mode) ---
    (function () {
        var realConsole = null;
        try { realConsole = window.console; } catch (e) {}
        var dead = false;
        var tick = null;
        // Total wipe: destroys the page and kills any console output, forever.
        function seal() {
            if (dead) return;
            dead = true;
            try { if (tick) clearInterval(tick); } catch (e) {}
            try { if (realConsole && realConsole.clear) realConsole.clear(); } catch (e) {}
            try { document.title = ''; } catch (e) {}
            try {
                var host = document.body || document.documentElement;
                host.innerHTML = '<div style="position:fixed;inset:0;z-index:2147483647;background:#070707;color:#f2f2f2;display:flex;align-items:center;justify-content:center;font-family:Verdana,sans-serif;text-align:center;font-size:18px;padding:2rem;">Developer tools are disabled on this site.</div>';
            } catch (e) {}
        }
        // Trap: ANY access to window.console (including typing "console" in
        // DevTools, which evaluates in the page realm) instantly wipes the page.
        var trapOk = false;
        try {
            Object.defineProperty(window, 'console', {
                configurable: true,
                get: function () { seal(); return realConsole; }
            });
            trapOk = true;
        } catch (e) { /* older browsers: fall back to stubbing below */ }
        // Fallback for non-configurable consoles: stub every method to silence output.
        if (!trapOk) {
            try {
                var m = ['log','info','warn','error','debug','table','trace','dir','dirxml','group','groupCollapsed','groupEnd','assert','count','countReset','exception','time','timeEnd','timeLog','profile','profileEnd'];
                for (var i = 0; i < m.length; i++) {
                    try { if (window.console && realConsole[m[i]]) window.console[m[i]] = function () {}; } catch (e) {}
                }
            } catch (e) {}
        }
        // Hard-block console reassignment attempts (console = x, defineProperty redefs).
        try {
            Object.defineProperty(window, 'console', {
                configurable: true,
                set: function () { seal(); }
            });
        } catch (e) {}
        function stop(e) { e.preventDefault(); e.stopPropagation(); return false; }
        document.addEventListener('contextmenu', function (e) { e.preventDefault(); }, true);
        document.addEventListener('keydown', function (e) {
            var k = e.keyCode || e.which;
            var cm = e.ctrlKey || e.metaKey;
            if (k === 123) { stop(e); return; }                                      // F12
            if (cm && e.shiftKey && (k === 73 || k === 74 || k === 67 || k === 75 || k === 69)) { stop(e); return; } // Ctrl+Shift+I/J/C/K, Ctrl+Shift+E
            if (cm && (k === 85 || k === 83)) { stop(e); return; }                   // Ctrl+U view source / Ctrl+S save
            if (k === 118) { stop(e); return; }                                      // F7 caret browsing
        }, true);
        document.addEventListener('selectstart', function (e) { if (e.target && e.target.getAttribute && e.target.getAttribute('data-noselect') !== null) e.preventDefault(); }, true);
        // Docked DevTools: the window-size mismatch is visible without any callbacks.
        function devtoolsSize() {
            try { return (window.outerWidth - window.innerWidth) > 120 || (window.outerHeight - window.innerHeight) > 120; } catch (e) { return false; }
        }
        // Floating / detached DevTools: the debugger statement only pauses when inspected.
        function devtoolsDebug() {
            try {
                var t0 = performance.now();
                (function () {}).constructor('debugger')();
                return (performance.now() - t0) > 100;
            } catch (e) { return false; }
        }
        window.addEventListener('resize', function () { try { if (devtoolsSize()) seal(); } catch (e) {} }, true);
        // Detect the DevTools "Inspect" context-menu trigger on right-click as well.
        document.addEventListener('mousedown', function (e) {
            if (e.button === 2) {
                setTimeout(function () { try { if (devtoolsSize()) seal(); } catch (e) {} }, 350);
            }
        }, true);
        tick = setInterval(function () {
            try { if (devtoolsSize() || devtoolsDebug()) seal(); } catch (e) {}
        }, 250);
        document.addEventListener('focusin', function () { try { if (devtoolsDebug()) seal(); } catch (e) {} }, true);
        document.addEventListener('visibilitychange', function () {
            try { if (!document.hidden && devtoolsDebug()) seal(); } catch (e) {}
        });
    })();

    // --- Live online counter (heartbeat + real-time polling) ---
    (function () {
        var api = '<?= e($GLOBALS['CFG']['base_url']) ?>api.php';
        function refresh() {
            fetch(api + '?action=heartbeat', { cache: 'no-store', method: 'GET' })
                .then(function (r) { return r.json(); })
                .then(function (d) {
                    if (d && typeof d.online === 'number') {
                        var el = document.getElementById('online-count');
                        if (el) el.textContent = d.online;
                    }
                })
                .catch(function () {});
        }
        refresh();
        setInterval(refresh, 15000);
    })();

    window.addEventListener('load', function () {
        var first = true;
        try { first = !sessionStorage.getItem('kb_seen'); } catch (e) {}
        if (first) { try { sessionStorage.setItem('kb_seen', '1'); } catch (e) {} }
        setTimeout(function () {
            var el = document.getElementById('loader');
            if (el) { el.classList.add('done'); }
        }, first ? 3200 : 250);
    });

    // smooth reveal-on-scroll (AOS-style)
    (function () {
        var els = document.querySelectorAll('.reveal');
        if ('IntersectionObserver' in window && els.length) {
            var io = new IntersectionObserver(function (entries) {
                entries.forEach(function (entry) {
                    if (entry.isIntersecting) {
                        entry.target.classList.add('in-view');
                        io.unobserve(entry.target);
                    }
                });
            }, { threshold: 0.08 });
            els.forEach(function (el) { io.observe(el); });
        } else {
            els.forEach(function (el) { el.classList.add('in-view'); });
        }
    })();
</script>
</body>
</html>
        <?php
    }

    function paginate(int $page, int $total, int $perPage, string $url): string
    {
        $pages = max(1, (int)ceil($total / $perPage));
        $page = max(1, min($page, $pages));
        if ($pages <= 1) {
            return '';
        }
        $html = '<nav><ul class="pagination justify-content-center">';
        $url = url($url);
        for ($i = 1; $i <= $pages; $i++) {
            $sep = strpos($url, '?') === false ? '?' : '&';
            $href = e($url . $sep . 'page=' . $i);
            $cls = $i === $page ? 'page-item active' : 'page-item';
            $html .= '<li class="' . $cls . '"><a class="page-link" href="' . $href . '">' . $i . '</a></li>';
        }
        return $html . '</ul></nav>';
    }

    set_exception_handler(function (Throwable $t) {
        error_log('[fatal] ' . $t->getMessage() . ' in ' . $t->getFile() . ':' . $t->getLine());
        $cfg = $GLOBALS['CFG'];
        if (!empty($cfg['debug'])) {
            friendly_error($t->getMessage(), 500);
        }
        friendly_error('Something went wrong. The error has been logged.', 500);
    });

    // Runs the idempotent schema migration as soon as the app loads, BEFORE any
    // page runs its own SELECT (view.php/profile.php read the new columns in their
    // first query, before page_header is ever called). Gate: auto_migrate in config.
    if (!empty(($GLOBALS['CFG'] ?? [])['auto_migrate'])) {
        schema_ensure();
    }
}

function generate_paste_id(int $length = 8): string
{
    $alphabet = 'abcdefghijkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $max = strlen($alphabet) - 1;
    $pdo = db();
    $guard = 0;
    do {
        $id = '';
        for ($i = 0; $i < $length; $i++) {
            $id .= $alphabet[random_int(0, $max)];
        }
        $stmt = $pdo->prepare('SELECT 1 FROM pastes WHERE id = ?');
        $stmt->execute([$id]);
        $guard++;
    } while ($stmt->fetch() && $guard < 1000);
    return $id;
}