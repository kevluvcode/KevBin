<?php
require_once __DIR__ . '/db.php';

// Decoder for obfuscated HTML chunks stored in the source files (XOR + base64).
// The HTML parts of the templates are encoded so the raw markup is not readable
// in the source; every page decodes its own chunks at render time via h().
function h(string $s): string
{
    static $key = 'k3vB1n!~h7ml_enc@2026';
    $raw = base64_decode($s, true);
    if ($raw === false) {
        return '';
    }
    $klen = strlen($key);
    $n = strlen($raw);
    $out = '';
    for ($i = 0; $i < $n; $i++) {
        $out .= $raw[$i] ^ $key[$i % $klen];
    }
    return $out;
}

if (!defined('APP_INITIALIZED')) {
    define('APP_INITIALIZED', true);

    function start_session(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            ini_set('session.use_strict_mode', '1');
            $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
                || (int)($_SERVER['SERVER_PORT'] ?? 0) === 443
                || (strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https');
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
        $token = (string)($_POST['csrf'] ?? $_POST['csrf_token'] ?? $_GET['csrf'] ?? '');
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

    // Persists an error into the error_logs table (admin viewer); falls back to
    // the PHP error log if the DB write fails for any reason.
    function log_error(string $level, string $message, string $file = '', int $line = 0, int $userId = 0): void
    {
        try {
            $pdo = db();
            $url = (string)($_SERVER['REQUEST_URI'] ?? '');
            if (strlen($url) > 512) {
                $url = mb_substr($url, 0, 512);
            }
            $pdo->prepare(
                'INSERT INTO error_logs (level, message, file, line, url, ip, user_id, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, UTC_TIMESTAMP())'
            )->execute([
                mb_substr($level, 0, 16),
                mb_substr($message, 0, 5000),
                $file !== '' ? mb_substr($file, 0, 255) : null,
                $line > 0 ? $line : null,
                $url,
                client_ip(),
                $userId > 0 ? $userId : null,
            ]);
        } catch (Throwable $t) {
            error_log('[log_error] ' . $message . ' in ' . $file . ':' . $line . ' (' . $t->getMessage() . ')');
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
        $logo = "\n\n----\n"
            . " __ __ _______    ______  _____   __\n"
            . " / //_// ____/ |  / / __ )/  _/ | / /\n"
            . " / ,<  / __/  | | / / __  |/ //  |/ /\n"
            . " / /| |/ /___  | |/ / /_/ // // /|  /\n"
            . "/_/ |_/_____/  |___/_____/___/_/ |_/  \n"
            . "----\nCreated on " . $cfg['site_name'] . $kw . ' — ' . $cfg['base_url'];
        return $logo;
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
                        ui_mode, ui_color, ui_gradient, ui_layout, accent_color,
                        github_id, github_username, github_avatar,
                        discord_id, discord_username, discord_avatar,
                        premium_plan, premium_expires_at
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

    // ——— Premium tiers ———
    // Stored on users: premium_plan ('supporter'|'pro'|'lifetime'|'') and
    // premium_expires_at (NULL = never, or a UTC datetime). Tiers come from
    // config['premium_tiers']. Grants are automatic (verified BTC payment, see
    // support.php + the btc scan below) or manual (admin.php).

    function premium_tiers(): array
    {
        return (array)($GLOBALS['CFG']['premium_tiers'] ?? [
            'supporter' => ['name' => 'Supporter', 'price_usd' => 5,  'days' => 30,  'chars' => 500000,  'badge' => 'SUPPORTER'],
            'pro'       => ['name' => 'Pro',       'price_usd' => 10, 'days' => 30,  'chars' => 1000000, 'badge' => 'PRO'],
            'lifetime'  => ['name' => 'Lifetime',  'price_usd' => 40, 'days' => 0,   'chars' => 1000000, 'badge' => 'LIFETIME'],
        ]);
    }

    function tier_rank(string $tier): int
    {
        $order = ['supporter' => 1, 'pro' => 2, 'lifetime' => 3];
        return $order[$tier] ?? 0;
    }

    // Active tier for a user row (or the logged-in user). Returns '' when not
    // premium or the plan has lapsed.
    function premium_tier(?array $u = null): string
    {
        if ($u === null) {
            $u = current_user();
        }
        if ($u === null) {
            return '';
        }
        $plan = (string)($u['premium_plan'] ?? '');
        if ($plan === 'monthly') {
            $plan = 'supporter'; // legacy plan value
        }
        if ($plan === '' || !isset(premium_tiers()[$plan])) {
            return '';
        }
        $expires = (string)($u['premium_expires_at'] ?? '');
        if ($expires !== '') {
            $expTs = strtotime($expires . ' UTC');
            if ($expTs !== false && $expTs < time()) {
                return '';
            }
        }
        return $plan;
    }

    function is_premium(): bool
    {
        return premium_tier() !== '';
    }

    // True when the given user row (as fetched by a query that includes
    // premium_plan/premium_expires_at) currently has an active plan.
    function user_is_premium(?array $u): bool
    {
        return premium_tier($u) !== '';
    }

    // HTML badge shown next to a premium user's name. Returns '' when not premium.
    function premium_badge(?array $u = null): string
    {
        $tier = premium_tier($u);
        if ($tier === '') {
            return '';
        }
        $tiers = premium_tiers();
        $label = (string)($tiers[$tier]['badge'] ?? strtoupper($tier));
        $cls = 'bg-warning text-dark';
        if ($tier === 'pro') {
            $cls = 'bg-info text-dark';
        } elseif ($tier === 'lifetime') {
            $cls = 'bg-success';
        }
        return ' <span class="badge ' . $cls . '" title="KevBin ' . e($label) . ' — thank you!">&#9733; ' . e($label) . '</span>';
    }

    // Perk: how many chars a paste may be for the current user.
    function content_char_limit(): int
    {
        $base = (int)($GLOBALS['CFG']['max_content_chars'] ?? 100000);
        $tier = premium_tier();
        if ($tier !== '') {
            $tiers = premium_tiers();
            $chars = (int)($tiers[$tier]['chars'] ?? 0);
            if ($chars > 0) {
                return max($base, $chars);
            }
        }
        return max(1, $base);
    }

    // Perk: max upload size in bytes for the current user.
    function upload_byte_limit(): int
    {
        $base = (int)($GLOBALS['CFG']['upload_max_mb'] ?? 20);
        if (is_premium()) {
            $base *= (int)($GLOBALS['CFG']['premium_upload_mult'] ?? 4);
        }
        // Host cap: uploaded bytes are a DB BLOB, the INSERT must fit inside the
        // host's max_allowed_packet (10 MB here), so never exceed 9 MB.
        return min($base * 1024 * 1024, 9 * 1024 * 1024);
    }

    // Perk: higher upload rate allowance for premium users.
    function upload_rate_limit(): int
    {
        $base = (int)($GLOBALS['CFG']['upload_rate_limit'] ?? 10);
        return is_premium() ? max(1, $base * 3) : $base;
    }

    // ——— BTC payment auto-verification ———
    // Payments are claimed by TXID on support.php, then verified against a public
    // block explorer (mempool.space, fallback blockstream.info) by a throttled
    // scan that piggybacks on real page loads (free hosts have no cron). Admins
    // can also verify/override in admin.php. Outbound HTTP works on this host
    // (Discord/GitHub OAuth already use it).

    function site_option_get(string $key): ?string
    {
        try {
            $stmt = db()->prepare('SELECT value FROM site_options WHERE k = ?');
            $stmt->execute([$key]);
            $v = $stmt->fetchColumn();
            return $v === false ? null : (string)$v;
        } catch (Throwable $t) {
            return null;
        }
    }

    function site_option_set(string $key, string $value): void
    {
        try {
            db()->prepare('INSERT INTO site_options (k, value) VALUES (?, ?) ON DUPLICATE KEY UPDATE value = VALUES(value)')
                ->execute([$key, $value]);
        } catch (Throwable $t) {
            // best-effort (table missing on first run before schema_ensure)
        }
    }

    function btc_http_get(string $url, int $timeout = 12): string
    {
        $out = '';
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => $timeout,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_USERAGENT => 'kevbin/1.0',
                CURLOPT_SSL_VERIFYPEER => false,
            ]);
            $out = (string)curl_exec($ch);
            curl_close($ch);
        } else {
            $ctx = stream_context_create([
                'http' => ['method' => 'GET', 'header' => "User-Agent: kevbin\r\n", 'timeout' => $timeout, 'ignore_errors' => true],
                'ssl' => ['verify_peer' => false, 'verify_peer_name' => false],
            ]);
            $out = (string)@file_get_contents($url, false, $ctx);
        }
        return $out;
    }

    function btc_price_usd(): float
    {
        $price = 0.0;
        $j = json_decode(btc_http_get('https://mempool.space/api/v1/prices'), true);
        if (is_array($j) && !empty($j['USD'])) {
            $price = (float)$j['USD'];
        }
        if ($price <= 0) {
            $j2 = json_decode(btc_http_get('https://blockstream.info/api/price'), true);
            if (is_array($j2) && !empty($j2['USD'])) {
                $price = (float)$j2['USD'];
            }
        }
        if ($price <= 0) {
            $price = (float)(site_option_get('btc_price') ?? 60000);
        }
        if ($price >= 1000) {
            site_option_set('btc_price', (string)$price);
        }
        return $price;
    }

    // Minimum sats a verified payment must deliver to our wallet for a tier.
    function btc_min_sats(string $tier): int
    {
        $tiers = premium_tiers();
        $usd = (int)($tiers[$tier]['price_usd'] ?? 5);
        $price = btc_price_usd();
        if ($price <= 0) {
            $price = 60000;
        }
        return max(1, (int)ceil($usd / $price * 1e8));
    }

    // Look up a tx on the public explorer and return how many sats it sent to
    // our wallet (0 = nothing to us). null = could not reach any explorer.
    function btc_fetch_tx(string $txid): ?array
    {
        $addr = (string)($GLOBALS['CFG']['bitcoin_address'] ?? '');
        if ($addr === '' || !preg_match('/^[a-f0-9]{64}$/i', $txid)) {
            return null;
        }
        foreach (['https://mempool.space/api/tx/' . $txid, 'https://blockstream.info/api/tx/' . $txid] as $u) {
            $j = json_decode(btc_http_get($u), true);
            if (!is_array($j) || (string)($j['txid'] ?? '') !== $txid) {
                continue;
            }
            $received = 0;
            foreach ((array)($j['vout'] ?? []) as $vo) {
                if (isset($vo['scriptpubkey_address']) && strtolower((string)$vo['scriptpubkey_address']) === strtolower($addr)) {
                    $received += (int)($vo['value'] ?? 0);
                }
            }
            return [
                'confirmed' => !empty($j['status']['confirmed']),
                'received_sats' => $received,
            ];
        }
        return null;
    }

    // Set (or upgrade) a user's premium tier. $days <= 0 means lifetime.
    // Monthly renewals extend from the later of now / current expiry.
    function grant_premium(string $username, string $tier, ?int $days): void
    {
        try {
            $pdo = db();
            $stmt = $pdo->prepare('SELECT id, premium_plan, premium_expires_at FROM users WHERE username = ?');
            $stmt->execute([$username]);
            $u = $stmt->fetch();
            if (!$u) {
                return;
            }
            $cur = (string)($u['premium_plan'] ?? '');
            $final = tier_rank($tier) >= tier_rank($cur) ? $tier : $cur;
            $expires = null;
            if ($days === null || $days <= 0 || $final === 'lifetime') {
                $final = 'lifetime';
            } else {
                $curTs = 0;
                if (!empty($u['premium_expires_at'])) {
                    $ts = strtotime((string)$u['premium_expires_at'] . ' UTC');
                    if ($ts !== false) {
                        $curTs = $ts;
                    }
                }
                $expires = gmdate('Y-m-d H:i:s', max(time(), $curTs) + $days * 86400);
            }
            $pdo->prepare('UPDATE users SET premium_plan = ?, premium_expires_at = ? WHERE username = ?')
                ->execute([$final, $expires, $username]);
            log_activity('premium_grant', $username . ' ' . $final . ($expires !== null ? ' until ' . $expires : ' lifetime'));
        } catch (Throwable $t) {
            error_log('[grant_premium] ' . $t->getMessage());
        }
    }

    // Record a user's claim of a TXID for a tier and try to verify it right away.
    // Returns ['ok'=>bool, 'msg'=>string, 'verified'=>bool].
    function claim_btc_payment(int $userId, string $username, string $txid, string $tier): array
    {
        $txid = strtolower(trim($txid));
        if (!preg_match('/^[a-f0-9]{64}$/', $txid)) {
            return ['ok' => false, 'msg' => 'That does not look like a valid Bitcoin transaction ID (64 hex characters).', 'verified' => false];
        }
        $tiers = premium_tiers();
        if (!isset($tiers[$tier])) {
            return ['ok' => false, 'msg' => 'Unknown plan.', 'verified' => false];
        }
        $pdo = db();
        $stmt = $pdo->prepare('SELECT id, username, status FROM premium_payments WHERE txid = ?');
        $stmt->execute([$txid]);
        $existing = $stmt->fetch();
        if ($existing && ($existing['status'] === 'verified' || $existing['status'] === 'rejected')) {
            return ['ok' => false, 'msg' => 'That transaction has already been ' . $existing['status'] . '. If this is a mistake, contact an admin.', 'verified' => false];
        }
        if ($existing) {
            $pdo->prepare("UPDATE premium_payments SET user_id = ?, username = ?, plan = ?, status = 'pending' WHERE id = ?")
                ->execute([$userId, $username, $tier, (int)$existing['id']]);
            $pid = (int)$existing['id'];
        } else {
            $pdo->prepare("INSERT INTO premium_payments (txid, user_id, username, plan, status, created_at) VALUES (?, ?, ?, ?, 'pending', UTC_TIMESTAMP())")
                ->execute([$txid, $userId, $username, $tier]);
            $pid = (int)$pdo->lastInsertId();
        }
        $tx = btc_fetch_tx($txid);
        if ($tx === null) {
            return ['ok' => true, 'msg' => 'Claim recorded. It will be verified automatically within a few minutes (or by an admin).', 'verified' => false];
        }
        $pdo->prepare('UPDATE premium_payments SET amount_sats = ? WHERE id = ?')->execute([$tx['received_sats'], $pid]);
        if (!$tx['confirmed']) {
            return ['ok' => true, 'msg' => 'That transaction exists but is not confirmed yet — it will be verified automatically once confirmed.', 'verified' => false];
        }
        $minSats = btc_min_sats($tier);
        if ($tx['received_sats'] < $minSats) {
            $pdo->prepare("UPDATE premium_payments SET status = 'rejected' WHERE id = ?")->execute([$pid]);
            return ['ok' => false, 'msg' => 'Payment not recognized: that transaction does not send enough to our wallet (need at least ' . number_format($minSats) . ' sats).', 'verified' => false];
        }
        $days = (int)($tiers[$tier]['days'] ?? 30);
        $pdo->prepare("UPDATE premium_payments SET status = 'verified', verified_at = UTC_TIMESTAMP() WHERE id = ?")->execute([$pid]);
        grant_premium($username, $tier, $days > 0 ? $days : null);
        $name = (string)($tiers[$tier]['name'] ?? $tier);
        return ['ok' => true, 'msg' => 'Payment verified — your ' . $name . ' plan is now active!' . ($days > 0 ? ' (' . $days . ' days).' : ' (lifetime).'), 'verified' => true];
    }

    // Scan pending claims against the explorer + auto-detect incoming payments.
    function scan_btc_payments(): array
    {
        $stats = ['processed' => 0, 'scanned' => false];
        try {
            $pdo = db();
            $tiers = premium_tiers();
            $stmt = $pdo->prepare("SELECT id, txid, username, plan FROM premium_payments WHERE status = 'pending'");
            $stmt->execute();
            foreach ($stmt->fetchAll() as $row) {
                $tx = btc_fetch_tx((string)$row['txid']);
                if ($tx === null) {
                    continue;
                }
                $pdo->prepare('UPDATE premium_payments SET amount_sats = ? WHERE id = ?')->execute([$tx['received_sats'], (int)$row['id']]);
                if (!$tx['confirmed']) {
                    continue;
                }
                $minSats = btc_min_sats((string)$row['plan']);
                if ($tx['received_sats'] < $minSats) {
                    $pdo->prepare("UPDATE premium_payments SET status = 'rejected' WHERE id = ?")->execute([(int)$row['id']]);
                    continue;
                }
                $days = (int)($tiers[$row['plan']]['days'] ?? 30);
                $pdo->prepare("UPDATE premium_payments SET status = 'verified', verified_at = UTC_TIMESTAMP() WHERE id = ?")->execute([(int)$row['id']]);
                grant_premium((string)$row['username'], (string)$row['plan'], $days > 0 ? $days : null);
                $stats['processed']++;
            }
            // Auto-detect incoming confirmed payments to the wallet so later
            // claims verify instantly.
            $addr = (string)($GLOBALS['CFG']['bitcoin_address'] ?? '');
            if ($addr !== '') {
                foreach (['https://mempool.space/api/address/' . $addr . '/txs', 'https://blockstream.info/api/address/' . $addr . '/txs'] as $u) {
                    $j = json_decode(btc_http_get($u), true);
                    if (!is_array($j)) {
                        continue;
                    }
                    $floor = (int)($GLOBALS['CFG']['btc_wallet_floor_sats'] ?? 1000);
                    foreach ($j as $tx) {
                        if (!isset($tx['txid'], $tx['status']['confirmed']) || !$tx['status']['confirmed']) {
                            continue;
                        }
                        $received = 0;
                        foreach ((array)($tx['vout'] ?? []) as $vo) {
                            if (isset($vo['scriptpubkey_address']) && strtolower((string)$vo['scriptpubkey_address']) === strtolower($addr)) {
                                $received += (int)($vo['value'] ?? 0);
                            }
                        }
                        if ($received < $floor) {
                            continue;
                        }
                        $chk = $pdo->prepare('SELECT id FROM premium_payments WHERE txid = ?');
                        $chk->execute([strtolower((string)$tx['txid'])]);
                        if (!$chk->fetch()) {
                            $pdo->prepare("INSERT INTO premium_payments (txid, user_id, username, plan, amount_sats, status, created_at) VALUES (?, NULL, '', '', ?, 'detected', UTC_TIMESTAMP())")
                                ->execute([strtolower((string)$tx['txid']), $received]);
                        }
                    }
                    $stats['scanned'] = true;
                    break; // first explorer that answered
                }
            }
        } catch (Throwable $t) {
            error_log('[btc_scan] ' . $t->getMessage());
        }
        return $stats;
    }

    // Throttled auto-scan hook — piggybacks on real page loads (no cron on the
    // free host). Runs at most once per btc_scan_interval seconds.
    function maybe_run_btc_scan(): void
    {
        if ((string)($GLOBALS['CFG']['bitcoin_address'] ?? '') === '') {
            return;
        }
        $interval = (int)($GLOBALS['CFG']['btc_scan_interval'] ?? 300);
        $last = (int)(site_option_get('last_btc_scan') ?? 0);
        if (time() - $last < $interval) {
            return;
        }
        site_option_set('last_btc_scan', (string)time());
        scan_btc_payments();
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

    function block_bad_user_agents(): void
    {
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
        if ($ua === '') {
            http_response_code(403);
            echo '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8"><title>Access Denied</title></head>'
                . '<body style="background:#070707;color:#f2f2f2;font-family:sans-serif;display:flex;'
                . 'align-items:center;justify-content:center;height:100vh;margin:0;">'
                . '<div style="text-align:center"><h1>403 Access Denied</h1><p>Your request has been blocked.</p></div></body></html>';
            exit;
        }
        $blocked = [
            'python', 'go-http', 'java/', 'curl/', 'wget', 'httpclient', 'libwww',
            'scrapie', 'mj12bot', 'ahrefsbot', 'semrushbot', 'dotbot', 'blexbot',
            'pycurl', 'node-fetch', 'headlesschrome', 'phantomjs',
            'httrack', 'webcopier', 'teleport', 'extractorpro', 'offline explorer',
            'website quester', 'webzip', 'webstripper', 'websauger', 'webleacher',
            'webreaper', 'webcollector', 'websucker', 'realdownload', 'getright',
            'mass downloader', 'download demon', 'fast-download', 'getweb!',
            'go!zilla', 'go!gopro', 'leechget', 'netxtreme', 'mr. file downloader',
            'fresh download', 'flashget', 'star downloader', 'smart downloader',
            'reget', 'jetcar', 'prozilla', 'msieftp', 'n ipp', 'webking online',
            'auto spider', 'didpa-http',
            'mozilla/5.0 (compatible; msie 6.0; windows nt 5.1; sv1)',
            'masscan', 'nmap', 'sqlmap', 'nikto', 'dirbuster', 'gobuster',
            'ffuf', 'nuclei', 'httpx', 'subfinder',
        ];
        $uaLower = strtolower($ua);
        foreach ($blocked as $bad) {
            if (strpos($uaLower, $bad) !== false) {
                http_response_code(403);
                echo '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8"><title>Access Denied</title></head>'
                    . '<body style="background:#070707;color:#f2f2f2;font-family:sans-serif;display:flex;'
                    . 'align-items:center;justify-content:center;height:100vh;margin:0;">'
                    . '<div style="text-align:center"><h1>403 Access Denied</h1><p>Your request has been blocked.</p></div></body></html>';
                exit;
            }
        }
    }

    function ip_challenge_check(): void
    {
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        $ext = strtolower(pathinfo($uri, PATHINFO_EXTENSION));
        $skipExtensions = ['css', 'js', 'png', 'jpg', 'ico', 'woff', 'woff2', 'svg', 'gif'];
        if (in_array($ext, $skipExtensions, true)) {
            return;
        }
        if ($uri === '/favicon.ico') {
            return;
        }
        if (is_admin()) {
            return;
        }
        start_session();
        if (!empty($_SESSION['challenge_passed']) && !empty($_SESSION['challenge_time'])) {
            if (time() - $_SESSION['challenge_time'] < 300) {
                return;
            }
            unset($_SESSION['challenge_passed'], $_SESSION['challenge_time']);
        }
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['challenge_answer'], $_POST['challenge_nonce'])) {
            $answer = (string)$_POST['challenge_answer'];
            $nonce = (string)$_POST['challenge_nonce'];
            if (empty($_SESSION['challenge_nonce']) || $nonce !== $_SESSION['challenge_nonce']) {
                http_response_code(403);
                echo '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8"><title>Challenge Failed</title></head>'
                    . '<body style="background:#070707;color:#f2f2f2;font-family:sans-serif;display:flex;'
                    . 'align-items:center;justify-content:center;height:100vh;margin:0;">'
                    . '<div style="text-align:center"><h1>Challenge Failed</h1><p>Invalid or expired challenge. Please go back and try again.</p></div></body></html>';
                exit;
            }
            $hash = hash('sha256', 'kevbin_challenge_' . $answer);
            if (strpos($hash, '0000') === 0) {
                unset($_SESSION['challenge_nonce']);
                $_SESSION['challenge_passed'] = true;
                $_SESSION['challenge_time'] = time();
                $_SESSION['req_count'] = [];
                return;
            } else {
                http_response_code(403);
                echo '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8"><title>Challenge Failed</title></head>'
                    . '<body style="background:#070707;color:#f2f2f2;font-family:sans-serif;display:flex;'
                    . 'align-items:center;justify-content:center;height:100vh;margin:0;">'
                    . '<div style="text-align:center"><h1>Challenge Failed</h1><p>Incorrect answer. Please go back and try again.</p></div></body></html>';
                exit;
            }
        }
        if (!isset($_SESSION['req_count']) || !is_array($_SESSION['req_count'])) {
            $_SESSION['req_count'] = [];
        }
        $now = time();
        $_SESSION['req_count'][] = $now;
        $_SESSION['req_count'] = array_filter($_SESSION['req_count'], function ($t) use ($now) {
            return ($now - $t) < 300;
        });
        $_SESSION['req_count'] = array_values($_SESSION['req_count']);
        $recent60 = array_filter($_SESSION['req_count'], function ($t) use ($now) {
            return ($now - $t) < 60;
        });
        $showChallenge = false;
        if (count($_SESSION['req_count']) > 60) {
            $showChallenge = true;
        } elseif (count($recent60) > 30) {
            $showChallenge = true;
        }
        if ($showChallenge) {
            $nonce = bin2hex(random_bytes(16));
            $_SESSION['challenge_nonce'] = $nonce;
            http_response_code(403);
            echo '<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Checking your browser...</title>
<style>
*{margin:0;padding:0;box-sizing:border-box}
body{background:#070707;color:#f2f2f2;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;display:flex;align-items:center;justify-content:center;height:100vh;overflow:hidden}
.container{text-align:center;max-width:500px;padding:2rem}
.spinner{width:48px;height:48px;border:4px solid rgba(88,101,242,0.2);border-top-color:#5865f2;border-radius:50%;animation:spin 1s linear infinite;margin:0 auto 1.5rem}
@keyframes spin{to{transform:rotate(360deg)}}
h1{font-size:1.5rem;margin-bottom:0.5rem;font-weight:600}
p{color:#a0a0a0;font-size:0.95rem;line-height:1.5}
.progress{width:100%;height:4px;background:rgba(88,101,242,0.2);border-radius:2px;margin-top:1.5rem;overflow:hidden}
.progress-bar{height:100%;background:#5865f2;border-radius:2px;width:0%;transition:width 0.3s}
.status{color:#5865f2;font-size:0.85rem;margin-top:0.75rem}
</style>
</head>
<body>
<div class="container">
<div class="spinner"></div>
<h1>Checking your browser...</h1>
<p>This process is automatic. Your browser will redirect shortly.</p>
<div class="progress"><div class="progress-bar" id="pb"></div></div>
<div class="status" id="status">Initializing verification...</div>
</div>
<script>
(function(){
    var pb=document.getElementById("pb");
    var st=document.getElementById("status");
    var target="' . htmlspecialchars($nonce) . '";
    pb.style.width="20%";
    st.textContent="Running computation...";
    async function solve(){
        for(var n=0;n<10000000;n++){
            var str="kevbin_challenge_"+n;
            var buf=new TextEncoder().encode(str);
            var hash=await crypto.subtle.digest("SHA-256",buf);
            var hex=Array.from(new Uint8Array(hash)).map(function(b){return b.toString(16).padStart(2,"0")}).join("");
            if(hex.indexOf("0000")===0){
                pb.style.width="100%";
                st.textContent="Verification complete. Redirecting...";
                var form=document.createElement("form");
                form.method="POST";
                form.action="";
                var a1=document.createElement("input");
                a1.type="hidden";a1.name="challenge_answer";a1.value=n.toString();
                var a2=document.createElement("input");
                a2.type="hidden";a2.name="challenge_nonce";a2.value=target;
                form.appendChild(a1);
                form.appendChild(a2);
                document.body.appendChild(form);
                form.submit();
                return;
            }
            if(n%5000===0){pb.style.width=Math.min(80,20+Math.floor(n/1000000*60))+"%";}
        }
    }
    setTimeout(solve,100);
})();
</script>
</body>
</html>';
            exit;
        }
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
            // Approval queue: pending edits to wiki pages, pastes or deletions
            // made by regular (non-staff) users. Staff approve or reject them.
            if (!table_exists($pdo, 'moderation_queue')) {
                $pdo->exec('CREATE TABLE IF NOT EXISTS moderation_queue (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    action_type VARCHAR(20) NOT NULL,
                    target_type VARCHAR(20) NOT NULL,
                    ref_id VARCHAR(40) NULL,
                    scope VARCHAR(20) NULL,
                    slug VARCHAR(190) NULL,
                    title VARCHAR(255) NULL,
                    old_content MEDIUMTEXT NULL,
                    new_content MEDIUMTEXT NULL,
                    note VARCHAR(1000) NULL,
                    requested_by INT NULL,
                    status VARCHAR(20) NOT NULL DEFAULT \'pending\',
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    reviewed_by INT NULL,
                    reviewed_at DATETIME NULL,
                    KEY idx_modqueue_status (status, created_at),
                    KEY idx_modqueue_target (target_type, ref_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
                log_activity('schema_migrate', 'created moderation_queue table');
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
                // v2.x: GitHub OAuth login (id, login, avatar).
                'github_id' => 'VARCHAR(64) NULL',
                'github_username' => 'VARCHAR(64) NULL',
                'github_avatar' => 'VARCHAR(255) NULL',
                // v2.x: Discord OAuth login (id, username, avatar) via Cloudflare bridge.
                'discord_id' => 'VARCHAR(64) NULL',
                'discord_username' => 'VARCHAR(64) NULL',
                'discord_avatar' => 'VARCHAR(255) NULL',
                // v2.x: Premium / supporter plan ('monthly'|'lifetime'|'') + expiry.
                'premium_plan' => 'VARCHAR(20) NOT NULL DEFAULT \'\'',
                'premium_expires_at' => 'DATETIME NULL',
            ];
            foreach ($userCols as $name => $def) {
                if (!column_exists($pdo, 'users', $name)) {
                    $pdo->exec('ALTER TABLE users ADD COLUMN ' . $name . ' ' . $def);
                }
            }
            // v2.x: Premium payments (BTC auto-verification) + tiny key/value store.
            $pdo->exec('CREATE TABLE IF NOT EXISTS site_options (
                k VARCHAR(64) PRIMARY KEY,
                value TEXT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
            $pdo->exec('CREATE TABLE IF NOT EXISTS premium_payments (
                id INT AUTO_INCREMENT PRIMARY KEY,
                txid CHAR(64) NOT NULL,
                user_id INT NULL,
                username VARCHAR(50) NOT NULL DEFAULT \'\',
                plan VARCHAR(20) NOT NULL DEFAULT \'\',
                amount_sats BIGINT NULL,
                status VARCHAR(20) NOT NULL DEFAULT \'pending\',
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                verified_at DATETIME NULL,
                UNIQUE KEY uq_pp_txid (txid),
                KEY idx_pp_status (status)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
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
            foreach (['lat' => 'VARCHAR(24) NULL', 'lon' => 'VARCHAR(24) NULL'] as $col => $def) {
                if (table_exists($pdo, 'geo_cache') && !column_exists($pdo, 'geo_cache', $col)) {
                    $pdo->exec('ALTER TABLE geo_cache ADD COLUMN ' . $col . ' ' . $def);
                }
            }
            // Enriched per-click analytics columns (device/browser, screen, timezone, IP intel).
            $clickCols = [
                'browser' => 'VARCHAR(40) NULL',
                'browser_version' => 'VARCHAR(24) NULL',
                'os' => 'VARCHAR(40) NULL',
                'os_version' => 'VARCHAR(24) NULL',
                'device' => 'VARCHAR(24) NULL',
                'is_bot' => 'TINYINT(1) NOT NULL DEFAULT 0',
                'language' => 'VARCHAR(32) NULL',
                'screen' => 'VARCHAR(24) NULL',
                'timezone' => 'VARCHAR(64) NULL',
                'dpr' => 'VARCHAR(8) NULL',
                'touch' => 'TINYINT(1) NOT NULL DEFAULT 0',
                'hw_concurrency' => 'VARCHAR(8) NULL',
                'fp' => 'TEXT NULL',
                'isp' => 'VARCHAR(120) NULL',
                'asn' => 'VARCHAR(120) NULL',
                'is_proxy' => 'TINYINT(1) NOT NULL DEFAULT 0',
                'is_vpn' => 'TINYINT(1) NOT NULL DEFAULT 0',
                'is_tor' => 'TINYINT(1) NOT NULL DEFAULT 0',
                'is_crawler' => 'TINYINT(1) NOT NULL DEFAULT 0',
                'lat' => 'VARCHAR(24) NULL',
                'lon' => 'VARCHAR(24) NULL',
            ];
            foreach ($clickCols as $col => $def) {
                if (table_exists($pdo, 'link_clicks') && !column_exists($pdo, 'link_clicks', $col)) {
                    $pdo->exec('ALTER TABLE link_clicks ADD COLUMN ' . $col . ' ' . $def);
                }
            }
            // Standalone analytics trackers (image-pixel beacons + visit logs).
            if (!table_exists($pdo, 'trackers')) {
                $pdo->exec('CREATE TABLE IF NOT EXISTS trackers (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    code VARCHAR(16) NOT NULL,
                    title VARCHAR(120) NULL,
                    user_id INT NULL,
                    manage_key VARCHAR(32) NULL,
                    views INT UNSIGNED NOT NULL DEFAULT 0,
                    last_view DATETIME NULL,
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    UNIQUE KEY uq_trackers_code (code),
                    KEY idx_trackers_user (user_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
                log_activity('schema_migrate', 'created trackers table');
            }
            if (!table_exists($pdo, 'tracker_views')) {
                $pdo->exec('CREATE TABLE IF NOT EXISTS tracker_views (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    tracker_id INT NOT NULL,
                    ip VARCHAR(45) NULL,
                    ua VARCHAR(255) NULL,
                    referer VARCHAR(512) NULL,
                    country VARCHAR(64) NULL,
                    region VARCHAR(64) NULL,
                    city VARCHAR(64) NULL,
                    isp VARCHAR(120) NULL,
                    asn VARCHAR(120) NULL,
                    is_proxy TINYINT(1) NOT NULL DEFAULT 0,
                    is_vpn TINYINT(1) NOT NULL DEFAULT 0,
                    is_tor TINYINT(1) NOT NULL DEFAULT 0,
                    is_crawler TINYINT(1) NOT NULL DEFAULT 0,
                    browser VARCHAR(40) NULL,
                    browser_version VARCHAR(24) NULL,
                    os VARCHAR(40) NULL,
                    os_version VARCHAR(24) NULL,
                    device VARCHAR(24) NULL,
                    is_bot TINYINT(1) NOT NULL DEFAULT 0,
                    language VARCHAR(32) NULL,
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    KEY idx_tviews_tracker (tracker_id, created_at)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
                log_activity('schema_migrate', 'created tracker_views table');
            }
            // Online presence (live "N online" counter) — keyed by IP so a single
            // person with many tabs/visits still counts as one.
            if (!table_exists($pdo, 'online')) {
                $pdo->exec(
                    'CREATE TABLE IF NOT EXISTS online (
                        ip VARCHAR(45) PRIMARY KEY,
                        last_seen DATETIME NOT NULL,
                        KEY idx_online_seen (last_seen)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
                );
                log_activity('schema_migrate', 'created online table');
            } elseif (column_exists($pdo, 'online', 'token')) {
                // Legacy token-keyed presence table — rebuild it keyed by IP so the
                // live counter counts unique visitors, not unique sessions.
                try {
                    $pdo->exec('ALTER TABLE online DROP PRIMARY KEY, ADD PRIMARY KEY (ip), DROP COLUMN token');
                    log_activity('schema_migrate', 'online table re-keyed by ip');
                } catch (Throwable $t) {
                    error_log('[schema_ensure online] ' . $t->getMessage());
                }
            }
            // Wiki: site documentation, community wiki and personal wikis.
            if (!table_exists($pdo, 'wiki_pages')) {
                $pdo->exec(
                    'CREATE TABLE IF NOT EXISTS wiki_pages (
                        id INT AUTO_INCREMENT PRIMARY KEY,
                        scope ENUM(\'site\',\'community\',\'personal\') NOT NULL DEFAULT \'community\',
                        owner_id INT NULL,
                        slug VARCHAR(190) NOT NULL,
                        title VARCHAR(255) NOT NULL,
                        content MEDIUMTEXT NOT NULL,
                        locked TINYINT(1) NOT NULL DEFAULT 0,
                        views INT NOT NULL DEFAULT 0,
                        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                        UNIQUE KEY uq_wiki_slug (scope, owner_id, slug),
                        KEY idx_wiki_scope (scope, updated_at)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
                );
                $pdo->exec(
                    'CREATE TABLE IF NOT EXISTS wiki_revisions (
                        id INT AUTO_INCREMENT PRIMARY KEY,
                        page_id INT NOT NULL,
                        user_id INT NULL,
                        content MEDIUMTEXT NOT NULL,
                        note VARCHAR(255) NULL,
                        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                        KEY idx_wiki_rev (page_id, id)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
                );
                log_activity('schema_migrate', 'created wiki_pages/wiki_revisions tables');
            }
            // User file uploads (files.php) — files are auto-mirrored to external
            // hosters, so the DB only ever holds links (mirror_url), never the bytes.
            if (!table_exists($pdo, 'uploads')) {
                schema_create_uploads($pdo);
                log_activity('schema_migrate', 'created uploads table');
            } else {
                if (!column_exists($pdo, 'uploads', 'file_data')) {
                    $pdo->exec('ALTER TABLE uploads ADD COLUMN file_data LONGBLOB NULL');
                    log_activity('schema_migrate', 'added uploads.file_data');
                }
                if (!column_exists($pdo, 'uploads', 'mirror_url')) {
                    $pdo->exec('ALTER TABLE uploads ADD COLUMN mirror_url VARCHAR(500) NULL AFTER file_data');
                    log_activity('schema_migrate', 'added uploads.mirror_url');
                }
            }
            // Log of mirror links pushed to external file-hosters (files.php).
            if (!table_exists($pdo, 'file_shares')) {
                $pdo->exec('CREATE TABLE IF NOT EXISTS file_shares (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    file_id VARCHAR(16) NOT NULL,
                    site VARCHAR(20) NOT NULL,
                    url VARCHAR(500) NOT NULL,
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    KEY idx_fs_file (file_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
                log_activity('schema_migrate', 'created file_shares table');
            }
            // Personal bio pages (guns.lol-style): custom slug URL + name + buttons.
            if (!table_exists($pdo, 'bios')) {
                $pdo->exec(
                    'CREATE TABLE IF NOT EXISTS bios (
                        id INT AUTO_INCREMENT PRIMARY KEY,
                        slug VARCHAR(40) NOT NULL,
                        user_id INT NULL,
                        manage_key VARCHAR(32) NULL,
                        display_name VARCHAR(80) NOT NULL,
                        bio_text VARCHAR(1000) NULL,
                        avatar_url VARCHAR(500) NULL,
                        background VARCHAR(24) NOT NULL DEFAULT \'aurora\',
                        accent VARCHAR(7) NOT NULL DEFAULT \'#5865f2\',
                        buttons TEXT NULL,
                        clicks INT UNSIGNED NOT NULL DEFAULT 0,
                        last_click DATETIME NULL,
                        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                        UNIQUE KEY uq_bios_slug (slug),
                        KEY idx_bios_user (user_id)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
                );
                log_activity('schema_migrate', 'created bios table');
            }
            // Persistent error log (admin viewer page reads this).
            if (!table_exists($pdo, 'error_logs')) {
                $pdo->exec('CREATE TABLE IF NOT EXISTS error_logs (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    level VARCHAR(16) NOT NULL DEFAULT \'error\',
                    message TEXT NOT NULL,
                    file VARCHAR(255) NULL,
                    line INT NULL,
                    url VARCHAR(512) NULL,
                    ip VARCHAR(45) NULL,
                    user_id INT NULL,
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    KEY idx_error_created (created_at)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
                log_activity('schema_migrate', 'created error_logs table');
            }
            // Seed a friendly community wiki home page on first install.
            if (table_exists($pdo, 'wiki_pages')) {
                $wikiCount = (int)$pdo->query(
                    "SELECT COUNT(*) FROM wiki_pages WHERE scope = 'community' AND owner_id IS NULL"
                )->fetchColumn();
                if ($wikiCount === 0) {
                    $home = "= Welcome to the Wiki\n\n"
                        . "This is the community wiki — a shared knowledge base, docs hub and how-to guide. "
                        . "Anyone with an account can create and edit pages, and every edit is saved in the page history.\n\n"
                        . "== Getting started\n\n"
                        . "* Click **+ New page** to create a page\n"
                        . "* Edit any page with the **✏ Edit** button\n"
                        . "* Link between pages with `[[Page Name]]`\n\n"
                        . "== Markup cheat sheet\n\n"
                        . "| Markup | Result |\n"
                        . "| `= Heading` | h1 heading |\n"
                        . "| `== Sub-heading` | h2 heading |\n"
                        . "| `**bold**` | **bold** |\n"
                        . "| `` `code` `` | `code` |\n"
                        . "| `> quote` | a blockquote |\n"
                        . "| `* item` | list item |\n"
                        . "| `---` | horizontal rule |\n"
                        . "| `[label](https://example.com)` | external link |\n\n"
                        . "== Rules\n\n"
                        . "> Be helpful, stay on-topic, no spam. Admins can lock pages that get out of hand.\n\n"
                        . "Happy writing — check the **🕘 History** tab of any page to see how it evolved.";
                    $pdo->prepare(
                        "INSERT INTO wiki_pages (scope, owner_id, slug, title, content, created_at, updated_at)
                         VALUES ('community', NULL, 'home', 'Welcome', ?, UTC_TIMESTAMP(), UTC_TIMESTAMP())"
                    )->execute([$home]);
                    $homeId = (int)$pdo->lastInsertId();
                    $pdo->prepare(
                        "INSERT INTO wiki_revisions (page_id, user_id, content, note, created_at)
                         VALUES (?, NULL, ?, 'Initial page', UTC_TIMESTAMP())"
                    )->execute([$homeId, $home]);
                    log_activity('schema_migrate', 'seeded community wiki home page');
                }
            }
        } catch (Throwable $t) {
            error_log('[schema_ensure] ' . $t->getMessage());
        }
    }

    // ——— Online presence (live counter + heartbeat) ———

    // Uploads table DDL (shared by the auto-migrator and fresh-install setup).
    function schema_create_uploads($pdo): void
    {
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS uploads (
                id VARCHAR(16) PRIMARY KEY,
                user_id INT NULL,
                filename VARCHAR(255) NOT NULL,
                stored_name VARCHAR(48) NOT NULL,
                mime VARCHAR(120) NOT NULL,
                size BIGINT UNSIGNED NOT NULL,
                file_data LONGBLOB NULL,
                mirror_url VARCHAR(500) NULL,
                delete_key VARCHAR(32) NULL,
                downloads INT UNSIGNED NOT NULL DEFAULT 0,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                expires_at DATETIME NULL,
                UNIQUE KEY uq_up_stored (stored_name),
                KEY idx_up_user (user_id, created_at),
                KEY idx_up_created (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
    }

    // Full fresh-install schema. setup.php calls this once so EVERYTHING the app
    // needs exists on a brand-new database (the auto-migrator can't, because it
    // bails out early when the pastes table is still missing).
    function schema_create_all(): void
    {
        $pdo = db();
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
            tagline VARCHAR(120) NULL,
            pronouns VARCHAR(40) NULL,
            skills VARCHAR(255) NULL,
            bg_image VARCHAR(255) NULL,
            occupation VARCHAR(120) NULL,
            education VARCHAR(255) NULL,
            languages VARCHAR(255) NULL,
            hobbies VARCHAR(255) NULL,
            quote VARCHAR(280) NULL,
            birthdate DATE NULL,
            status_msg VARCHAR(160) NULL,
            github VARCHAR(100) NULL,
            twitch VARCHAR(100) NULL,
            tiktok VARCHAR(100) NULL,
            instagram VARCHAR(100) NULL,
            reddit VARCHAR(100) NULL,
            snapchat VARCHAR(100) NULL,
            bluesky VARCHAR(100) NULL,
            threads VARCHAR(100) NULL,
            linkedin VARCHAR(255) NULL,
            bg_mode VARCHAR(10) NOT NULL DEFAULT \'none\',
            bg_fit VARCHAR(10) NOT NULL DEFAULT \'cover\',
            bg_color VARCHAR(7) NULL,
            bg_gradient VARCHAR(300) NULL,
            bg_veil TINYINT UNSIGNED NOT NULL DEFAULT 55,
            bg_blur TINYINT(1) NOT NULL DEFAULT 0,
            recovery_hash VARCHAR(128) NULL,
            recovery_ip VARCHAR(255) NULL,
            ui_mode VARCHAR(10) NOT NULL DEFAULT \'none\',
            ui_color VARCHAR(7) NULL,
            ui_gradient VARCHAR(300) NULL,
            ui_layout VARCHAR(10) NOT NULL DEFAULT \'default\',
            accent_color VARCHAR(7) NULL,
            github_id VARCHAR(64) NULL,
            github_username VARCHAR(64) NULL,
            github_avatar VARCHAR(255) NULL,
            discord_id VARCHAR(64) NULL,
            discord_username VARCHAR(64) NULL,
            discord_avatar VARCHAR(255) NULL,
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
            notify_followers TINYINT(1) NOT NULL DEFAULT 1,
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
        $pdo->exec('CREATE TABLE IF NOT EXISTS reports (
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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $pdo->exec('CREATE TABLE IF NOT EXISTS votes (
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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $pdo->exec('CREATE TABLE IF NOT EXISTS comments (
            id INT AUTO_INCREMENT PRIMARY KEY,
            entity_type VARCHAR(10) NOT NULL,
            entity_id VARCHAR(40) NOT NULL,
            user_id INT NOT NULL,
            body TEXT NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            KEY idx_comment_entity (entity_type, entity_id, created_at),
            KEY idx_comment_user (user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $pdo->exec('CREATE TABLE IF NOT EXISTS follows (
            id INT AUTO_INCREMENT PRIMARY KEY,
            follower_id INT NOT NULL,
            followed_id INT NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_follow (follower_id, followed_id),
            KEY idx_follow_followed (followed_id),
            KEY idx_follow_follower (follower_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $pdo->exec('CREATE TABLE IF NOT EXISTS notifications (
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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $pdo->exec('CREATE TABLE IF NOT EXISTS links (
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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $pdo->exec('CREATE TABLE IF NOT EXISTS link_clicks (
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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $pdo->exec('CREATE TABLE IF NOT EXISTS geo_cache (
            ip VARCHAR(45) PRIMARY KEY,
            country VARCHAR(64) NULL,
            region VARCHAR(64) NULL,
            city VARCHAR(64) NULL,
            isp VARCHAR(120) NULL,
            asn VARCHAR(120) NULL,
            is_proxy TINYINT(1) NOT NULL DEFAULT 0,
            is_vpn TINYINT(1) NOT NULL DEFAULT 0,
            is_tor TINYINT(1) NOT NULL DEFAULT 0,
            is_crawler TINYINT(1) NOT NULL DEFAULT 0,
            fetched_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $pdo->exec('CREATE TABLE IF NOT EXISTS online (
            ip VARCHAR(45) PRIMARY KEY,
            last_seen DATETIME NOT NULL,
            KEY idx_online_seen (last_seen)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $pdo->exec('CREATE TABLE IF NOT EXISTS wiki_pages (
            id INT AUTO_INCREMENT PRIMARY KEY,
            scope ENUM(\'site\',\'community\',\'personal\') NOT NULL DEFAULT \'community\',
            owner_id INT NULL,
            slug VARCHAR(190) NOT NULL,
            title VARCHAR(255) NOT NULL,
            content MEDIUMTEXT NOT NULL,
            locked TINYINT(1) NOT NULL DEFAULT 0,
            views INT NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_wiki_slug (scope, owner_id, slug),
            KEY idx_wiki_scope (scope, updated_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $pdo->exec('CREATE TABLE IF NOT EXISTS wiki_revisions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            page_id INT NOT NULL,
            user_id INT NULL,
            content MEDIUMTEXT NOT NULL,
            note VARCHAR(255) NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            KEY idx_wiki_rev (page_id, id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        schema_create_uploads($pdo);
        $pdo->exec('CREATE TABLE IF NOT EXISTS file_shares (
            id INT AUTO_INCREMENT PRIMARY KEY,
            file_id VARCHAR(16) NOT NULL,
            site VARCHAR(20) NOT NULL,
            url VARCHAR(500) NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            KEY idx_fs_file (file_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $pdo->exec('CREATE TABLE IF NOT EXISTS error_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            level VARCHAR(16) NOT NULL DEFAULT \'error\',
            message TEXT NOT NULL,
            file VARCHAR(255) NULL,
            line INT NULL,
            url VARCHAR(512) NULL,
            ip VARCHAR(45) NULL,
            user_id INT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            KEY idx_error_created (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $pdo->exec('CREATE TABLE IF NOT EXISTS moderation_queue (
            id INT AUTO_INCREMENT PRIMARY KEY,
            action_type VARCHAR(20) NOT NULL,
            target_type VARCHAR(20) NOT NULL,
            ref_id VARCHAR(40) NULL,
            scope VARCHAR(20) NULL,
            slug VARCHAR(190) NULL,
            title VARCHAR(255) NULL,
            old_content MEDIUMTEXT NULL,
            new_content MEDIUMTEXT NULL,
            note VARCHAR(1000) NULL,
            requested_by INT NULL,
            status VARCHAR(20) NOT NULL DEFAULT \'pending\',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            reviewed_by INT NULL,
            reviewed_at DATETIME NULL,
            KEY idx_modqueue_status (status, created_at),
            KEY idx_modqueue_target (target_type, ref_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        // Seed a friendly community wiki home page.
        $wikiCount = (int)$pdo->query(
            "SELECT COUNT(*) FROM wiki_pages WHERE scope = 'community' AND owner_id IS NULL"
        )->fetchColumn();
        if ($wikiCount === 0) {
            $home = "= Welcome to the Wiki\n\n"
                . "This is the community wiki — a shared knowledge base, docs hub and how-to guide. "
                . "Anyone with an account can create and edit pages, and every edit is saved in the page history.\n\n"
                . "== Getting started\n\n"
                . "* Click **+ New page** to create a page\n"
                . "* Edit any page with the **✏ Edit** button\n"
                . "* Link between pages with `[[Page Name]]`\n\n"
                . "== Rules\n\n"
                . "> Be helpful, stay on-topic, no spam. Admins can lock pages that get out of hand.";
            $pdo->prepare(
                "INSERT INTO wiki_pages (scope, owner_id, slug, title, content, created_at, updated_at)
                 VALUES ('community', NULL, 'home', 'Welcome', ?, UTC_TIMESTAMP(), UTC_TIMESTAMP())"
            )->execute([$home]);
            $homeId = (int)$pdo->lastInsertId();
            $pdo->prepare(
                "INSERT INTO wiki_revisions (page_id, user_id, content, note, created_at)
                 VALUES (?, NULL, ?, 'Initial page', UTC_TIMESTAMP())"
            )->execute([$homeId, $home]);
            log_activity('schema_create_all', 'seeded community wiki home page');
        }
        log_activity('schema_create_all', 'base schema created');
    }

    // ——— Online presence (live counter + heartbeat) ———

    // Visitors seen within this window count as "online".
    function online_window_seconds(): int { return 60; }
    // Rows older than this are purged on each heartbeat.
    function online_stale_seconds(): int { return 90; }

    // Records this visitor's presence (keyed by IP) and returns the live count.
    function online_ping(?string $token = null): int
    {
        try {
            $pdo = db();
            $ip = client_ip();
            $pdo->prepare('INSERT INTO online (ip, last_seen) VALUES (?, UTC_TIMESTAMP())
                ON DUPLICATE KEY UPDATE last_seen = UTC_TIMESTAMP()')
                ->execute([$ip]);
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

    function generate_tracker_code(int $length = 6): string
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
            $stmt = $pdo->prepare('SELECT 1 FROM trackers WHERE code = ?');
            $stmt->execute([$code]);
            $guard++;
        } while ($stmt->fetch() && $guard < 1000);
        return $code;
    }

    function tracker_pixel_url(string $code): string
    {
        $base = (string)($GLOBALS['CFG']['base_url'] ?? '');
        return rtrim($base, '/') . '/t/px/' . rawurlencode($code) . '.gif';
    }

    // Logs one hit on an analytics tracker. When $detailed is false the raw hit is
    // still counted, but per-Visit details are skipped (rate-limit throttle).
    function tracker_record_view(int $trackerId, bool $detailed = true): void
    {
        try {
            $pdo = db();
            if ($detailed) {
                $ip = client_ip();
                $ua = mb_substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255);
                $referer = mb_substr((string)($_SERVER['HTTP_REFERER'] ?? ''), 0, 512);
                $env = collect_click_env();
                $geo = lookup_ip_geo($ip);
                $pdo->prepare(
                    'INSERT INTO tracker_views
                        (tracker_id, ip, ua, referer, country, region, city, isp, asn,
                         is_proxy, is_vpn, is_tor, is_crawler,
                         browser, browser_version, os, os_version, device, is_bot, language)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
                )->execute([
                    $trackerId,
                    $ip,
                    $ua !== '' ? $ua : null,
                    $referer !== '' ? $referer : null,
                    $geo['country'], $geo['region'], $geo['city'],
                    $geo['isp'], $geo['asn'],
                    (int)$geo['is_proxy'], (int)$geo['is_vpn'], (int)$geo['is_tor'], (int)$geo['is_crawler'],
                    $env['browser'] !== 'Unknown' ? $env['browser'] : null,
                    $env['browser_version'] !== '' ? $env['browser_version'] : null,
                    $env['os'] !== 'Unknown' ? $env['os'] : null,
                    $env['os_version'] !== '' ? $env['os_version'] : null,
                    $env['device'],
                    (int)$env['is_bot'],
                    $env['language'],
                ]);
            }
            $pdo->prepare('UPDATE trackers SET views = views + 1, last_view = UTC_TIMESTAMP() WHERE id = ?')->execute([$trackerId]);
        } catch (Throwable $t) {
            error_log('[tracker_record_view] ' . $t->getMessage());
        }
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
        $geo = ['country' => null, 'region' => null, 'city' => null, 'isp' => null, 'asn' => null, 'is_proxy' => 0, 'is_vpn' => 0, 'is_tor' => 0, 'is_crawler' => 0, 'lat' => null, 'lon' => null];
        if (!filter_var($ip, FILTER_VALIDATE_IP)) {
            return $geo;
        }
        try {
            $pdo = db();
            $stmt = $pdo->prepare('SELECT country, region, city, isp, asn, is_proxy, is_vpn, is_tor, is_crawler, lat, lon FROM geo_cache WHERE ip = ? AND fetched_at > DATE_SUB(UTC_TIMESTAMP(), INTERVAL 30 DAY)');
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
                    'lat' => $row['lat'] !== null ? $row['lat'] : null,
                    'lon' => $row['lon'] !== null ? $row['lon'] : null,
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
                    'lat' => $data['latitude'] ?? null,
                    'lon' => $data['longitude'] ?? null,
                ];
            } else {
                // Fallback: classic ip-api.com geo lookup.
                $json = http_get('http://ip-api.com/json/' . rawurlencode($ip) . '?fields=status,country,regionName,city,lat,lon', 4);
                $data = $json !== null ? json_decode($json, true) : null;
                if (is_array($data) && ($data['status'] ?? '') === 'success') {
                    $geo = [
                        'country' => $data['country'] ?? null,
                        'region' => $data['regionName'] ?? null,
                        'city' => $data['city'] ?? null,
                        'isp' => null, 'asn' => null,
                        'is_proxy' => 0, 'is_vpn' => 0, 'is_tor' => 0, 'is_crawler' => 0,
                        'lat' => $data['lat'] ?? null,
                        'lon' => $data['lon'] ?? null,
                    ];
                }
            }
            $pdo->prepare(
                'INSERT INTO geo_cache (ip, country, region, city, isp, asn, is_proxy, is_vpn, is_tor, is_crawler, lat, lon)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE country = VALUES(country), region = VALUES(region), city = VALUES(city),
                    isp = VALUES(isp), asn = VALUES(asn), is_proxy = VALUES(is_proxy), is_vpn = VALUES(is_vpn),
                    is_tor = VALUES(is_tor), is_crawler = VALUES(is_crawler), lat = VALUES(lat), lon = VALUES(lon),
                    fetched_at = UTC_TIMESTAMP()'
            )->execute([$ip, $geo['country'], $geo['region'], $geo['city'], $geo['isp'], $geo['asn'],
                (int)$geo['is_proxy'], (int)$geo['is_vpn'], (int)$geo['is_tor'], (int)$geo['is_crawler'],
                $geo['lat'], $geo['lon']]);
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

        $browserVersion = '';
        $bvPatterns = [
            'Chrome' => '~Chrome/(\d+)~i',
            'Firefox' => '~Firefox/(\d+)~i',
            'Edge' => '~Edg(?:e|A)?/(\d+)~i',
            'Opera' => '~(?:OPR|Opera)[/ ]?(\d+)~i',
            'Safari' => '~Version/(\d+(?:\.\d+)?)~i',
            'Internet Explorer' => '~(?:MSIE (\d+)|rv:(\d+))~i',
        ];
        if (isset($bvPatterns[$browser]) && preg_match($bvPatterns[$browser], $ua, $m)) {
            $browserVersion = $m[1] !== '' ? $m[1] : ($m[2] ?? '');
        }

        $osVersion = '';
        if ($os === 'Windows') {
            if (preg_match('~Windows NT ([\d\.]+)~i', $ua, $m)) {
                $nt = $m[1];
                $map = ['6.3' => '8.1', '6.2' => '8', '6.1' => '7', '6.0' => 'Vista', '5.1' => 'XP', '5.0' => '2000', '10.0' => '10'];
                $osVersion = $map[$nt] ?? $nt;
            }
        } elseif ($os === 'Android' && preg_match('~Android ([\d\.]+)~i', $ua, $m)) {
            $osVersion = $m[1];
        } elseif ($os === 'iOS' && preg_match('~OS (\d+)[_\.](\d+)~i', $ua, $m)) {
            $osVersion = $m[1] . '.' . $m[2];
        } elseif ($os === 'macOS' && preg_match('~Mac OS X ([\d_\.]+)~i', $ua, $m)) {
            $osVersion = str_replace('_', '.', $m[1]);
        }

        return ['browser' => $browser, 'browser_version' => $browserVersion, 'os' => $os, 'os_version' => $osVersion, 'device' => $device, 'is_bot' => $isBot];
    }

    // Collects the server-side device/browser/language signals for a click,
    // merging a UA parse with Chrome Client Hints headers (higher accuracy).
    function collect_click_env(): array
    {
        $ua = (string)($_SERVER['HTTP_USER_AGENT'] ?? '');
        $parsed = parse_user_agent($ua);

        // Chrome/Edge send sec-ch-* hints with full browser version + platform.
        $fullVersionList = (string)($_SERVER['HTTP_SEC_CH_UA_FULL_VERSION_LIST'] ?? '');
        if ($fullVersionList !== '' && preg_match_all('~"([^"]+)";v="([^"]+)"~', $fullVersionList, $mm, PREG_SET_ORDER)) {
            $best = $mm[0];
            foreach ($mm as $entry) {
                $name = strtolower($entry[1]);
                if (strpos($name, 'chrome') !== false && strpos($name, 'chromium') === false) {
                    $best = ['', $entry[1], $entry[2]];
                    break;
                }
                if (strpos($name, 'edg') !== false) {
                    $best = ['', $entry[1], $entry[2]];
                    break;
                }
            }
            if ($best[1] !== '' && $best[2] !== '') {
                $mapped = ['Google Chrome' => 'Chrome', 'Microsoft Edge' => 'Edge', 'HeadlessChrome' => 'Chrome'];
                $parsed['browser'] = $mapped[$best[1]] ?? $best[1];
                $parsed['browser_version'] = $best[2];
            }
        }
        if (($platform = (string)($_SERVER['HTTP_SEC_CH_UA_PLATFORM'] ?? '')) !== '' && preg_match('~"([^"]+)"~', $platform, $m)) {
            $mapped = ['Windows' => 'Windows', 'macOS' => 'macOS', 'Linux' => 'Linux', 'Android' => 'Android', 'iOS' => 'iOS'];
            $parsed['os'] = $mapped[$m[1]] ?? $parsed['os'];
        }
        if ($parsed['device'] === 'Computer' && strpos($ua, ' Mobi') !== false) {
            $parsed['device'] = 'Phone';
        }
        if (stripos($ua, 'tablet') !== false || stripos($ua, 'ipad') !== false) {
            $parsed['device'] = 'Tablet';
        }

        $language = '';
        $acceptLang = (string)($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '');
        if ($acceptLang !== '') {
            foreach (explode(',', $acceptLang) as $chunk) {
                $tag = trim(explode(';', $chunk)[0]);
                if ($tag !== '') {
                    $language = mb_substr($tag, 0, 32);
                    break;
                }
            }
        }

        return [
            'browser' => $parsed['browser'],
            'browser_version' => $parsed['browser_version'],
            'os' => $parsed['os'],
            'os_version' => $parsed['os_version'],
            'device' => $parsed['device'],
            'is_bot' => $parsed['is_bot'] ? 1 : 0,
            'language' => $language !== '' ? $language : null,
            'screen' => null,
            'timezone' => null,
            'dpr' => isset($_SERVER['HTTP_SEC_CH_DPR']) ? mb_substr($_SERVER['HTTP_SEC_CH_DPR'], 0, 8) : null,
            'touch' => (($_SERVER['HTTP_SEC_CH_UA_MOBILE'] ?? '') === '?1') ? 1 : 0,
            'hw_concurrency' => isset($_SERVER['HTTP_SEC_CH_DEVICE_MEMORY']) ? mb_substr($_SERVER['HTTP_SEC_CH_DEVICE_MEMORY'], 0, 8) : null,
        ];
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

    // ——— Custom captcha removed — the site has no captcha anywhere.

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
        // 48 bytes → 96 hex characters, grouped in fours = a long, high-entropy key.
        $key = strtoupper(bin2hex(random_bytes(48)));
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

    // ——— Custom captcha removed — the site has no captcha anywhere.

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
        // A page may tighten/loosen its own CSP for local tools (e.g. the in-browser
        // Lua VM needs 'unsafe-eval' because Fengari compiles Lua to JS functions).
        $csp = (string)($GLOBALS['_csp'] ?? '');
        if ($csp === '') {
            $csp = "default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; img-src 'self' data: http: https:; font-src 'self'; connect-src 'self'; media-src 'self'; object-src 'none'; base-uri 'self'; form-action 'self'; frame-ancestors 'self'";
        }
        header('Content-Security-Policy: ' . $csp);
        // The site is always served over HTTPS (also when proxied by Cloudflare),
        // so HSTS is safe to advertise in production.
        $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (int)($_SERVER['SERVER_PORT'] ?? 0) === 443
            || (strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https');
        if ($secure) {
            header('Strict-Transport-Security: max-age=31536000; includeSubDomains; preload');
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
            $css[] = '@media (min-width: 992px) { .container { max-width: 1000px; } }';
        } elseif ($layout === 'wide') {
            $css[] = '@media (min-width: 992px) { .container { max-width: 1700px; } }';
        }
        return implode("\n", $css);
    }

    // Perceivable, indexable SEO helpers for tool pages. Each entry key is the tool
    // slug (path after /tools/), used to auto-inject unique <title>/description/
    // keywords + JSON-LD BreadcrumbList on the matching page. Fallback descriptions
    // are applied to any other /tools/<slug>/ page so nothing ships without a meta.
    function tool_seo(string $slug): array
    {
        $catalog = [
            'base64-decoder' => ['Base64 Decoder — Encode & Decode Base64 Online', 'Free online Base64 encoder/decoder. Encode any text or binary to Base64 and decode back with a single click. 100% in your browser — nothing is uploaded.'],
            'json-formatter' => ['JSON Formatter & Validator — Pretty Print Minified JSON', 'Format, validate, minify and fix JSON online. Beautify unreadable JSON with proper indentation and get instant syntax error reports — all in your browser.'],
            'jwt-decoder' => ['JWT Decoder — Decode JSON Web Tokens Online', 'Decode and inspect JWT header, payload and signature online. Verify HMAC signatures and view expiry claims — a must-have for API debugging and security testing.'],
            'subnet-calculator' => ['IP Subnet Calculator — CIDR, Network & Host Ranges', 'Calculate network, broadcast, usable host range, masks and CIDR notation for any IPv4 address instantly, with a clear binary breakdown and explanations.'],
            'cron-parser' => ['Cron Expression Parser & Explainer', 'Turn any cron expression into plain English and compute the next run times. Validate 5- and 6-field cron syntax with a free online cron explainer.'],
            'hex-dump' => ['Hex Dump — View Data as Hex & ASCII', 'Render any text into a classic hexdump with byte offsets, hex columns and an ASCII column. Great for debugging binary payloads and file headers.'],
            'uuid-generator' => ['UUID Generator — v4 & v7 Online', 'Generate cryptographically-random UUID v4 and v7 identifiers in bulk. Copy individually or all at once, plus hex tokens and API keys.'],
            'regex-tester' => ['Regex Tester — Test & Debug Regular Expressions', 'Test PCRE/ECMAScript regular expressions live against your text with match highlighting, group capture details and an in-browser regex builder.'],
            'color-converter' => ['Color Converter — HEX, RGB, HSL & CMYK', 'Convert colors between HEX, RGB, HSL and CMYK with a live swatch preview and copy buttons. Free online color code converter.'],
        ];
        $all = 'Developer, security and research toolkit. ';
        if (isset($catalog[$slug])) {
            return ['title' => $catalog[$slug][0], 'description' => $catalog[$slug][1], 'keywords' => $slug . ', online tool, free, kevbin'];
        }
        return [
            'title' => '',
            'description' => $all . 'Free online developer, security and research tools.',
            'keywords' => 'dev tools, security tools, online tools, kevbin',
        ];
    }

    function tool_slug_from_request(): ?string
    {
        $reqPath = strtok((string)($_SERVER['REQUEST_URI'] ?? '/'), '?') ?: '/';
        if (preg_match('#^/tools/([^/]+)/?$#', $reqPath, $m)) {
            return $m[1];
        }
        return null;
    }

    function page_header(string $title): void
    {
        send_security_headers();
        $cfg = $GLOBALS['CFG'];
        $me = current_user();

        // IP ban enforcement (admins are never blocked)
        block_bad_user_agents();
        ip_challenge_check();
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

        // Auto-inject per-tool SEO meta so every /tools/<slug>/ page is uniquely
        // indexable, documented and shareable even if the tool file sets no _meta.
        $toolSlug = tool_slug_from_request();
        if ($toolSlug !== null) {
            $toolSeo = tool_seo($toolSlug);
            if (($M['description'] ?? '') === '' || !isset($M['description'])) {
                $desc = $toolSeo['description'];
            }
            if ($keywords === '') {
                $keywords = $toolSeo['keywords'];
            }
            if (!isset($M['title'])) {
                $title = $toolSeo['title'] !== '' ? $toolSeo['title'] : $title;
            }
        }

        $ogTitle = (string)($M['title'] ?? $title);
        $ogType = (string)($M['type'] ?? 'website');
        // Canonical/OG URL defaults to the CURRENT page, not the homepage, so every
        // tool/doc page is separately indexable. Override via $GLOBALS['_meta']['url'].
        $reqPath = (string)($_SERVER['REQUEST_URI'] ?? '/');
        $reqPath = strtok($reqPath, '?') ?: '/';
        $ogUrl = (string)($M['url'] ?? url(ltrim($reqPath, '/')));
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
<script type="application/ld+json">
<?= json_encode([
            '@context' => 'https://schema.org',
            '@graph' => [
                [
                    '@type' => 'WebSite',
                    '@id' => $base . '#website',
                    'url' => $base,
                    'name' => $site,
                    'description' => $desc,
                    'inLanguage' => 'en',
                ],
                [
                    '@type' => 'Organization',
                    '@id' => $base . '#organization',
                    'name' => $site,
                    'url' => $base,
                    'logo' => $ogImage !== '' ? $ogImage : ($base . 'assets/logo.png'),
                ],
            ],
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>
</script>
<?php if ($toolSlug !== null): ?>
<script type="application/ld+json">
<?= json_encode([
            '@context' => 'https://schema.org',
            '@type' => 'WebApplication',
            'name' => html_entity_decode(strip_tags($ogTitle)),
            'url' => $ogUrl,
            'description' => $desc,
            'applicationCategory' => 'DeveloperApplication',
            'operatingSystem' => 'Any',
            'offers' => ['@type' => 'Offer', 'price' => '0', 'priceCurrency' => 'USD'],
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>
</script>
<script type="application/ld+json">
<?= json_encode([
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => [
                ['@type' => 'ListItem', 'position' => 1, 'name' => 'Home', 'item' => $base],
                ['@type' => 'ListItem', 'position' => 2, 'name' => 'Tools', 'item' => $base . 'tools/'],
                ['@type' => 'ListItem', 'position' => 3, 'name' => html_entity_decode(strip_tags($ogTitle)), 'item' => $ogUrl],
            ],
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>
</script>
<?php endif; ?>
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
        --radius: 18px;
        --radius-sm: 12px;
        --ease: cubic-bezier(.22,1,.36,1);
    }
    html { scroll-behavior: smooth; }
    body {
        background:
            radial-gradient(1100px 600px at 85% -10%, rgba(88,101,242,.10), transparent 60%),
            radial-gradient(900px 500px at -10% 10%, rgba(145,70,255,.08), transparent 60%),
            var(--rich-black);
        color: var(--text);
        font-family: "Space Grotesk", sans-serif;
        opacity: 0; animation: bodyfade .45s ease forwards;
        -webkit-font-smoothing: antialiased; text-rendering: optimizeLegibility;
    }
    /* compact + super-wide UI */
    html { font-size: 16px; }
    body { font-size: .95rem; }
    h1 { font-size: 1.7rem; } h2 { font-size: 1.45rem; } h3 { font-size: 1.22rem; }
    h4 { font-size: 1.08rem; } h5 { font-size: .95rem; } h6 { font-size: .85rem; }
    @media (min-width: 992px) { .container { max-width: 1600px; } }
    .navbar { padding-top: .25rem; padding-bottom: .25rem; }
    .navbar-brand { font-size: 1.1rem; }
    .nav-link { padding-top: .3rem; padding-bottom: .3rem; font-size: .95rem; }
    .btn { padding: .38rem .85rem; font-size: .9rem; }
    .btn-sm { padding: .2rem .55rem; font-size: .82rem; }
    .form-control, .form-select { padding: .4rem .7rem; font-size: .92rem; }
    .card-body { padding: 1.15rem; }
    .list-group-item { padding: .6rem 1rem; }
    .table { font-size: .9rem; }
    small, .form-text { font-size: .8rem; }
    h1,h2,h3,h4,h5,.navbar-brand { font-family: "Space Grotesk", sans-serif; }
    pre.paste-content, code { font-family: "JetBrains Mono", monospace; }
    pre.paste-content { background: #0b0b0b; border: 1px solid var(--line); padding: 1rem; border-radius: 16px; white-space: pre-wrap; word-break: break-word; }
    .navbar {
        background: rgba(7,7,7,.72);
        backdrop-filter: blur(16px) saturate(1.4);
        -webkit-backdrop-filter: blur(16px) saturate(1.4);
        border-bottom: 1px solid var(--line);
        position: sticky; top: 0; z-index: 1000;
    }
    .navbar-brand { font-size: 1.35rem; font-weight: 700; letter-spacing: 1px; }
    .nav-link { font-weight: 500; }
    .nav-item { position: relative; }
    .nav-link::after {
        content: ''; position: absolute; left: 0; right: 0; bottom: 2px; height: 2px;
        background: linear-gradient(90deg, var(--accent1), var(--accent2));
        border-radius: 99px; transform: scaleX(0); transform-origin: left;
        transition: transform .25s var(--ease);
    }
    .nav-link:hover::after { transform: scaleX(1); }
    .card {
        background: var(--panel); border: 1px solid var(--line); color: var(--text);
        border-radius: var(--radius); transition: transform .3s var(--ease), box-shadow .3s var(--ease), border-color .3s ease, background .3s ease;
    }
    .card:hover { transform: translateY(-4px); box-shadow: 0 20px 50px rgba(0,0,0,.6), 0 0 0 1px rgba(83,101,242,.2), 0 0 24px rgba(88,101,242,.08); border-color: rgba(83,101,242,.55); }
    .list-group-item { background: var(--panel-2); border: 1px solid var(--line); color: var(--text); border-radius: var(--radius-sm); margin-bottom: 4px; transition: transform .2s var(--ease), border-color .2s ease, background .2s ease, box-shadow .2s ease; }
    .list-group-item:hover { transform: translateX(4px); border-color: rgba(83,101,242,.5); background: var(--panel); box-shadow: 0 6px 18px rgba(0,0,0,.4); }
    .form-control, .form-select { background: #181818; border-color: var(--line); color: var(--text); border-radius: var(--radius-sm); transition: border-color .2s ease, box-shadow .2s ease, background .2s ease, transform .2s var(--ease); }
    .form-control:focus, .form-select:focus { background: #181818; color: var(--text); border-color: var(--accent1); box-shadow: 0 0 0 .28rem rgba(88,101,242,.22); transform: translateY(-1px); }
    .form-text { color: #777; }
    .btn { border-radius: var(--radius-sm); font-weight: 600; transition: transform .18s var(--ease), box-shadow .25s ease, filter .25s ease, background .25s ease, border-color .25s ease, color .25s ease, opacity .2s ease; }
    .btn:not(:active):hover { transform: translateY(-2px); }
    .btn:active { transform: translateY(0) scale(.96); }
    .btn-primary { background: linear-gradient(135deg, var(--accent1), var(--accent2)); border: none; position: relative; overflow: hidden; }
    .btn-primary::after {
        content: ''; position: absolute; top: 0; left: -60%; width: 50%; height: 100%;
        background: linear-gradient(90deg, transparent, rgba(255,255,255,.28), transparent);
        transform: skewX(-20deg); animation: btnshine 3.2s ease-in-out infinite;
    }
    @keyframes btnshine { 0% { left: -60%; } 55% { left: 130%; } 100% { left: 130%; } }
    .btn-primary:hover { filter: brightness(1.18); box-shadow: 0 10px 26px rgba(88,101,242,.4), 0 0 0 1px rgba(88,101,242,.3); }
    .btn-outline-light { border-color: var(--line); color: var(--text); }
    .btn-outline-light:hover { background: rgba(255,255,255,.08); color: #fff; border-color: rgba(255,255,255,.32); box-shadow: 0 6px 18px rgba(0,0,0,.35); }
    .alert { border-radius: 16px; border: 1px solid var(--line); animation: alertIn .35s var(--ease); }
    @keyframes alertIn { from { opacity: 0; transform: translateY(-8px) scale(.98); } to { opacity: 1; transform: none; } }
    .badge { border-radius: 99px; font-weight: 600; }
    .nav-link { transition: color .18s ease, background .18s ease; border-radius: 8px; }
    .page-link, .page-item.active .page-link { background-color: var(--panel-2); border-color: var(--line); color: var(--text); }
    .page-item.active .page-link { background: linear-gradient(135deg, var(--accent1), var(--accent2)); }
    .text-secondary { color: var(--dim) !important; }
    a { color: var(--accent1); text-decoration: none; transition: color .18s ease; }
    a:hover { color: var(--accent2); }
    a:focus-visible, button:focus-visible, input:focus-visible, select:focus-visible, textarea:focus-visible { outline: 2px solid var(--accent1); outline-offset: 2px; border-radius: 8px; }
    .pfp { width: 64px; height: 64px; border-radius: 50%; object-fit: cover; }
    .pfp-sm { width: 32px; height: 32px; border-radius: 50%; object-fit: cover; }
    .banner { width: 100%; height: 160px; object-fit: cover; border-radius: var(--radius); }

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
    .navbar-toggler { border-color: var(--line); border-radius: 10px; transition: transform .2s var(--ease), border-color .2s ease; }
    .navbar-toggler:hover { transform: scale(1.05); }
    .navbar-toggler:focus, .navbar-toggler:active { box-shadow: 0 0 0 .25rem rgba(88,101,242,.25); }
    @media (max-width: 991.98px) {
        .navbar-collapse { background: rgba(10,10,10,.97); border: 1px solid var(--line);
            border-radius: 16px; padding: .6rem .75rem; margin-top: .5rem;
            animation: alertIn .3s var(--ease); }
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
    .reveal { opacity: 0; transform: translateY(30px) scale(.985); transition: opacity .7s var(--ease), transform .7s var(--ease); will-change: opacity, transform; }
    .reveal.in-view { opacity: 1; transform: none; }

    @keyframes bodyfade { from { opacity: 0; } to { opacity: 1; } }

    /* tables: rounded glass cards, hover rows */
    .table { border-collapse: separate; border-spacing: 0; }
    .table thead th {
        background: rgba(255,255,255,.03); color: var(--dim); font-weight: 600;
        border-bottom: 1px solid var(--line);
    }
    .table thead th:first-child { border-top-left-radius: 14px; }
    .table thead th:last-child { border-top-right-radius: 14px; }
    .table tbody tr { transition: background .2s ease, transform .2s var(--ease); }
    .table tbody tr:hover { background: rgba(88,101,242,.06); }
    .table td { border-top: 1px solid rgba(255,255,255,.05); }
    .table-responsive { border-radius: 16px; }

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
    ::-webkit-scrollbar-thumb { background: #2a2a2a; border-radius: 99px; border: 2px solid #0c0c0c; }
    ::-webkit-scrollbar-thumb:hover { background: #3a3a3a; }
    .page-link, .page-item.active .page-link { border-radius: 12px; transition: transform .18s var(--ease), background .18s ease, box-shadow .18s ease; margin: 0 2px; }
    .page-item:not(.active) .page-link:hover { transform: translateY(-3px); box-shadow: 0 6px 16px rgba(0,0,0,.4); }
    .page-item.active .page-link { box-shadow: 0 6px 18px rgba(88,101,242,.35); }
    .dropdown-menu { border-radius: 16px; border: 1px solid var(--line); box-shadow: 0 18px 44px rgba(0,0,0,.55); animation: alertIn .25s var(--ease); }
    .dropdown-item { border-radius: 10px; margin: 2px 6px; transition: background .15s ease, transform .15s var(--ease), padding-left .2s var(--ease); }
    .dropdown-item:hover { transform: translateX(3px); }
    th, td { transition: background .15s ease; }

    @keyframes floaty { 0%,100% { transform: translateY(0); } 50% { transform: translateY(-6px); } }
    .navbar-brand { animation: floaty 5s ease-in-out infinite; }

    /* code blocks & pre rounder */
    code { background: rgba(255,255,255,.07); padding: 1px 6px; border-radius: 6px; }

    /* nav-tabs / pills get rounded + active glow */
    .nav-tabs .nav-link, .navbar .btn { border-radius: 99px; }
    .nav-tabs .nav-link { transition: color .2s ease, background .2s ease, border-color .2s ease; }
    .nav-tabs .nav-link.active { background: rgba(88,101,242,.15); border-color: var(--line) rgba(255,255,255,.1) transparent; }

    /* smooth image reveal */
    img { opacity: 1; transition: transform .3s var(--ease), opacity .3s ease; }
    .card img.lazyload, img[lazy] { transition: opacity .4s ease; }

    /* nice focus ring with soft glow */
    .btn:focus-visible { box-shadow: 0 0 0 3px rgba(88,101,242,.4); }
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
                <li class="nav-item"><a class="nav-link" href="<?= e(url('links.php')) ?>">My Links</a></li>
                <li class="nav-item"><a class="nav-link" href="<?= e(url('trackers.php')) ?>">Trackers</a></li>
                <li class="nav-item"><a class="nav-link" href="<?= e(url('bio_edit.php')) ?>">Bios</a></li>
                <li class="nav-item"><a class="nav-link" href="<?= e(url('dashboard.php')) ?>">Dashboard</a></li>
                <li class="nav-item"><a class="nav-link" href="<?= e(url('files.php')) ?>">Files</a></li>
                <li class="nav-item"><a class="nav-link" href="<?= e(url('tools/')) ?>">Tools</a></li>
                <li class="nav-item"><a class="nav-link" href="<?= e(url('wiki.php')) ?>">Wiki</a></li>
                <li class="nav-item"><a class="nav-link text-warning" href="<?= e(url('support.php')) ?>">Support</a></li>
            </ul>
            <ul class="navbar-nav">
                <?php $ghRepo = (string)($cfg['github_repo_url'] ?? ''); ?>
                <?php if ($ghRepo !== ''): ?>
                    <li class="nav-item d-flex align-items-center gap-1 me-2 my-1">
                        <a class="btn btn-sm btn-outline-light" href="<?= e($ghRepo) ?>/fork" target="_blank" rel="noopener" title="Fork the KevBin source on GitHub">
                            <svg width="12" height="12" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true" style="vertical-align:-2px;margin-right:3px;"><path d="M5 5.372v.878c0 .414.336.75.75.75h4.5a.75.75 0 0 0 .75-.75v-.878a2.25 2.25 0 1 1 1.5 0v.878a2.25 2.25 0 0 1-2.25 2.25h-1.5v2.128a2.251 2.251 0 1 1-1.5 0V8.5h-1.5A2.25 2.25 0 0 1 3.5 6.25v-.878a2.25 2.25 0 1 1 1.5 0ZM5 3.25a.75.75 0 1 0-1.5 0 .75.75 0 0 0 1.5 0Zm6.75.75a.75.75 0 1 0 0-1.5.75.75 0 0 0 0 1.5Zm-3 8.75a.75.75 0 1 0-1.5 0 .75.75 0 0 0 1.5 0Z"/></svg>
                            Fork
                        </a>
                        <a class="btn btn-sm btn-outline-light" href="<?= e($ghRepo) ?>" target="_blank" rel="noopener" title="Star the KevBin repo">★</a>
                        <a class="btn btn-sm btn-outline-light" href="<?= e($ghRepo) ?>" target="_blank" rel="noopener" title="Watch the KevBin repo">👁</a>
                    </li>
                <?php endif; ?>
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
        $toolSlug = tool_slug_from_request();
        ?>
<footer class="container py-4 text-center text-secondary">
    <?php if ($toolSlug !== null): ?>
    <div class="mb-3">
        <button type="button" class="btn btn-outline-light btn-sm" id="kb-share-tool">
            🔗 Share this tool
        </button>
        <a class="btn btn-outline-light btn-sm ms-2" href="<?= e(url('tools/')) ?>">All KevBin tools</a>
    </div>
    <?php endif; ?>
    <small>Made with <a class="link-secondary" href="<?= e(url()) ?>">KevBin</a> — education project ·
        <a class="link-secondary" href="<?= e(url('tos.php')) ?>">Terms of Service</a> ·
        <a class="link-secondary" href="<?= e(url('privacy.php')) ?>">Privacy Policy</a> ·
        <a class="link-secondary" href="<?= e(url('legal.php')) ?>">DMCA / Law Enforcement</a> ·
        <a class="link-secondary" href="<?= e(url('discord_tos.php')) ?>">Discord Terms</a> ·
        <a class="link-secondary" href="<?= e(url('discord_privacy.php')) ?>">Discord Privacy</a> ·
        <a class="link-secondary" href="<?= e(url('api_docs.php')) ?>">API</a> ·
        <a class="link-secondary" href="<?= e(url('dashboard.php')) ?>">Dashboard</a> ·
        <a class="link-secondary" href="<?= e(url('file_analysis.php')) ?>">File Analysis</a> ·
        <a class="link-secondary" href="<?= e(url('support.php')) ?>">Support</a>
    </small>
</footer>
<script src="/assets/bootstrap.bundle.min.js"></script>
<?php if ($toolSlug !== null): ?>
<script>
    // --- Share this tool (clipboard fallback always, WebShare API when available) ---
    (function () {
        var btn = document.getElementById('kb-share-tool');
        if (!btn) return;
        var shareData = { title: document.title, url: location.href };
        btn.addEventListener('click', function () {
            if (navigator.share) {
                navigator.share(shareData).catch(function () {});
                return;
            }
            var ok = function () { var old = btn.textContent; btn.textContent = '✅ Copied!'; setTimeout(function () { btn.textContent = old; }, 1600); };
            var fail = function () { window.prompt('Copy this URL:', location.href); };
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(location.href).then(ok, fail);
            } else {
                fail();
            }
        });
    })();
</script>
<?php endif; ?>
<script>
    // --- Simple console-open blocker (keyboard shortcuts only, non-destructive) ---
    // Blocks the common DevTools shortcuts. Nothing else: no debugger traps,
    // no timing checks, no window-size detection, no page wipes, no obfuscation.
    (function () {
        function stop(e) { e.preventDefault(); e.stopPropagation(); return false; }
        document.addEventListener('keydown', function (e) {
            var k = e.keyCode || e.which;
            var cm = e.ctrlKey || e.metaKey;
            if (k === 123) { stop(e); return; }                                      // F12
            if (cm && e.shiftKey && (k === 73 || k === 74 || k === 67 || k === 75 || k === 69)) { stop(e); return; } // Ctrl+Shift+I/J/C/K, Ctrl+Shift+E
            if (cm && (k === 85 || k === 83)) { stop(e); return; }                   // Ctrl+U view source / Ctrl+S save
        }, true);
        document.addEventListener('contextmenu', function (e) { e.preventDefault(); }, true);
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
        $uid = 0;
        if (session_status() === PHP_SESSION_ACTIVE && isset($_SESSION['user_id'])) {
            $uid = (int)$_SESSION['user_id'];
        }
        log_error('fatal', $t->getMessage(), $t->getFile(), $t->getLine(), $uid);
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

    // BTC payment auto-scan (throttled, piggybacks on real page loads). Safe to
    // run even on a fresh install: every helper catches its own failures.
    maybe_run_btc_scan();
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