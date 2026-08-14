<?php
require_once __DIR__ . '/functions.php';

start_session();
$cfg = $GLOBALS['CFG'];
$cid = (string)($cfg['github_client_id'] ?? '');
$csec = (string)($cfg['github_client_secret'] ?? '');
if ($cid === '' || $csec === '') {
    flash_set('error', 'GitHub login is not configured yet.');
    redirect('login.php');
}
$redirectUri = rtrim((string)$cfg['base_url'], '/') . '/github_oauth.php';

function gh_status(): int
{
    global $http_response_header;
    if (!is_array($http_response_header)) {
        return 0;
    }
    foreach ($http_response_header as $h) {
        if (preg_match('#^HTTP/\S+\s+(\d+)#i', $h, $m)) {
            return (int)$m[1];
        }
    }
    return 0;
}

// After a successful OAuth sign-in the bot auto-forks the repo to the user's
// GitHub account and stars + watches it. Best effort: whatever succeeds is
// reported, failures are silently skipped.
function github_auto_engage(string $token): string
{
    $cfg = $GLOBALS['CFG'];
    $path = trim((string)parse_url((string)($cfg['github_repo_url'] ?? ''), PHP_URL_PATH), '/');
    $parts = explode('/', $path);
    if (count($parts) !== 2 || $parts[0] === '' || $parts[1] === '') {
        return '';
    }
    $owner = $parts[0];
    $repo = $parts[1];
    $ua = 'kevbin';
    $done = [];
    @file_get_contents('https://api.github.com/repos/' . $owner . '/' . $repo . '/forks', false, stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => "Authorization: Bearer " . $token . "\r\nUser-Agent: " . $ua . "\r\nAccept: application/vnd.github+json\r\nContent-Type: application/json\r\nContent-Length: 0",
            'content' => '',
            'timeout' => 15,
        ],
    ]));
    if (gh_status() >= 200 && gh_status() < 300) {
        $done[] = 'forked';
    }
    @file_get_contents('https://api.github.com/user/starred/' . $owner . '/' . $repo, false, stream_context_create([
        'http' => [
            'method' => 'PUT',
            'header' => "Authorization: Bearer " . $token . "\r\nUser-Agent: " . $ua . "\r\nContent-Length: 0",
            'content' => '',
            'timeout' => 10,
        ],
    ]));
    if (gh_status() === 204) {
        $done[] = 'starred';
    }
    @file_get_contents('https://api.github.com/repos/' . $owner . '/' . $repo . '/subscription', false, stream_context_create([
        'http' => [
            'method' => 'PUT',
            'header' => "Authorization: Bearer " . $token . "\r\nUser-Agent: " . $ua . "\r\nAccept: application/vnd.github+json\r\nContent-Type: application/json\r\nContent-Length: " . strlen('{"subscribed":true,"ignored":false}'),
            'content' => '{"subscribed":true,"ignored":false}',
            'timeout' => 10,
        ],
    ]));
    if (gh_status() >= 200 && gh_status() < 300) {
        $done[] = 'watching';
    }
    log_activity('github_engage', $owner . '/' . $repo . ' -> ' . (count($done) ? implode(',', $done) : 'none'));
    return count($done) ? ' · ' . implode('+', $done) : '';
}

// --- Callback leg: GitHub bounced back with ?code&state (or an error). ---
if (isset($_GET['code']) || isset($_GET['error'])) {
    if (isset($_GET['error'])) {
        flash_set('error', 'GitHub login failed: ' . mb_substr((string)($_GET['error_description'] ?? $_GET['error']), 0, 80));
        redirect('login.php');
    }
    if (empty($_SESSION['github_oauth_state']) || !isset($_GET['state'])
        || !hash_equals($_SESSION['github_oauth_state'], (string)$_GET['state'])) {
        unset($_SESSION['github_oauth_state'], $_SESSION['github_oauth_return']);
        log_activity('github_oauth_statefail', 'state mismatch or already used');
        flash_set('error', 'GitHub login session expired (the page was refreshed or opened twice) — please try again.');
        redirect('login.php');
    }
    unset($_SESSION['github_oauth_state']);
    $return = (string)($_SESSION['github_oauth_return'] ?? 'index.php');
    unset($_SESSION['github_oauth_return']);
    if (!rate_limit_check('github_oauth', 20, 600)) {
        friendly_error('Too many OAuth attempts from your IP. Try again in 10 minutes.', 429);
    }
    $tok = @file_get_contents('https://github.com/login/oauth/access_token', false, stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => "Accept: application/json\r\nContent-Type: application/x-www-form-urlencoded\r\nUser-Agent: kevbin\r\n",
            'content' => http_build_query([
                'client_id' => $cid,
                'client_secret' => $csec,
                'code' => (string)$_GET['code'],
                'redirect_uri' => $redirectUri,
            ]),
            'timeout' => 15,
        ],
    ]));
    $accessToken = (string)(json_decode((string)$tok, true)['access_token'] ?? '');
    if ($accessToken === '') {
        log_activity('github_oauth_failed', 'no access token');
        flash_set('error', 'Could not complete GitHub login. Try again.');
        redirect($return);
    }
    $gh = json_decode((string)@file_get_contents('https://api.github.com/user', false, stream_context_create([
        'http' => ['header' => "Authorization: Bearer " . $accessToken . "\r\nUser-Agent: kevbin\r\n", 'timeout' => 15],
    ])), true);
    $ghId = (string)($gh['id'] ?? '');
    $ghLogin = (string)($gh['login'] ?? '');
    if ($ghId === '' || $ghLogin === '') {
        log_activity('github_oauth_failed', 'no user profile');
        flash_set('error', 'GitHub did not return a profile. Try again.');
        redirect($return);
    }
    $ghAvatar = mb_substr((string)($gh['avatar_url'] ?? ''), 0, 255);
    $pdo = db();
    $linked = $pdo->prepare('SELECT id FROM users WHERE github_id = ?');
    $linked->execute([$ghId]);
    $linkedId = $linked->fetchColumn();

    if (current_user() !== null) {
        // Linking a GitHub account to the currently logged-in user (from Settings).
        $uid = (int)current_user()['id'];
        if ($linkedId !== false && (int)$linkedId !== $uid) {
            flash_set('error', 'That GitHub account is already linked to another user.');
            redirect('settings.php#github');
        }
        $pdo->prepare('UPDATE users SET github_id = ?, github_username = ?, github_avatar = ?, pfp = COALESCE(NULLIF(pfp, \'\'), ?) WHERE id = ?')
            ->execute([$ghId, mb_substr($ghLogin, 0, 64), $ghAvatar, $ghAvatar, $uid]);
        log_activity('github_link', $ghLogin);
        flash_set('success', 'GitHub account @' . $ghLogin . ' linked.' . github_auto_engage($accessToken));
        redirect('settings.php#github');
    }

    if ($linkedId !== false) {
        $status = $pdo->prepare('SELECT status FROM users WHERE id = ?');
        $status->execute([(int)$linkedId]);
        if ($status->fetchColumn() !== 'active') {
            flash_set('error', 'This account is suspended.');
            redirect('login.php');
        }
        session_regenerate_id(true);
        $_SESSION['user_id'] = (int)$linkedId;
        log_activity('github_login', $ghLogin);
        flash_set('success', 'Welcome back, @' . $ghLogin . '!' . github_auto_engage($accessToken));
        redirect(safe_return($return));
    }

    // Brand-new account: username from the GitHub login, unique fallback + avatar.
    $base = preg_replace('/[^A-Za-z0-9_.-]/', '', $ghLogin);
    if ($base === '') {
        $base = 'gh' . $ghId;
    }
    $username = $base;
    $taken = $pdo->prepare('SELECT 1 FROM users WHERE username = ?');
    for ($i = 1; ; $i++) {
        $taken->execute([$username]);
        if (!$taken->fetch()) {
            break;
        }
        $username = $base . $i;
    }
    $pdo->prepare(
        'INSERT INTO users (username, password, role, status, created_at, pfp, github_id, github_username, github_avatar)
         VALUES (?, ?, \'user\', \'active\', UTC_TIMESTAMP(), ?, ?, ?, ?)'
    )->execute([$username, password_hash(bin2hex(random_bytes(16)), PASSWORD_BCRYPT), $ghAvatar, $ghId, mb_substr($ghLogin, 0, 64), $ghAvatar]);
    $uid = (int)$pdo->lastInsertId();
    user_update_recovery_key($uid);
    session_regenerate_id(true);
    $_SESSION['user_id'] = $uid;
    log_activity('github_register', $ghLogin);
    flash_set('success', 'Welcome, @' . $ghLogin . '!' . github_auto_engage($accessToken) . ' You can set a password any time in Settings.');
    redirect('profile.php?id=' . $uid);
}

// --- Start leg: no code yet — send the user to GitHub's consent screen. ---
$_SESSION['github_oauth_state'] = bin2hex(random_bytes(16));
$_SESSION['github_oauth_return'] = safe_return('index.php');
redirect('https://github.com/login/oauth/authorize?' . http_build_query([
    'client_id' => $cid,
    'redirect_uri' => $redirectUri,
    'state' => $_SESSION['github_oauth_state'],
]));