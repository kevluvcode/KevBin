<?php
// KevBin Discord OAuth sign-in via a Cloudflare Worker bridge.
//
// WHY A BRIDGE: InfinityFree's free hosting blocks ALL outbound HTTP, so this
// server can't call discord.com directly (and Discord's token exchange requires
// your client_secret server-side). Instead, discord_oauth.php talks to a tiny
// Cloudflare Worker (config 'discord_bridge_url') that holds the secret and
// performs the code -> profile exchange. The browser only ever talks to this
// page and to the bridge; the secret never reaches the browser or this host.
//
// The bridge fetches EVERY dataset the granted scopes allow and returns them
// under $data['datasets']: user (identify), guilds (guilds), connections
// (connections). ('relationships.read' would add friends/DM partners but is a
// RESTRICTED scope Discord rejects for normal apps, so it isn't requested.)
//
// ?export=1 (used by dashboard.php) requests those extra read scopes and stashes
// the datasets in the session so dashboard.php can list everything and offer a
// JSON download. Regular ?code login keeps the minimal identify+guilds.join
// scope and only links/logs in the user.
//
// Setup: fill in config.php 'discord_client_id' and 'discord_bridge_url', then
// deploy the worker folder in kvbin-discord-bridge/. See its README.

require_once __DIR__ . '/functions.php';

start_session();
$cfg = $GLOBALS['CFG'];
$cid = (string)($cfg['discord_client_id'] ?? '');
$bridge = (string)($cfg['discord_bridge_url'] ?? '');
if ($cid === '' || $bridge === '') {
    flash_set('error', 'Discord login is not configured yet.');
    redirect('login.php');
}
$bridge = rtrim($bridge, '/');
// Discord requires an HTTPS, pre-registered redirect URI.
$redirectUri = rtrim((string)$cfg['base_url'], '/') . '/discord_oauth.php';

// --- Callback leg: Discord (via the bridge) bounced back with ?code&state ---
if (isset($_GET['code']) || isset($_GET['error'])) {
    if (isset($_GET['error'])) {
        flash_set('error', 'Discord login failed: ' . mb_substr((string)($_GET['error_description'] ?? $_GET['error']), 0, 80));
        redirect('login.php');
    }
    if (empty($_SESSION['discord_oauth_state']) || !isset($_GET['state'])
        || !hash_equals($_SESSION['discord_oauth_state'], (string)$_GET['state'])) {
        unset($_SESSION['discord_oauth_state'], $_SESSION['discord_oauth_return'], $_SESSION['discord_export_mode']);
        log_activity('discord_oauth_statefail', 'state mismatch or already used');
        flash_set('error', 'Discord login session expired (the page was refreshed or opened twice) — please try again.');
        redirect('login.php');
    }
    unset($_SESSION['discord_oauth_state']);
    $exportMode = !empty($_SESSION['discord_export_mode']);
    unset($_SESSION['discord_export_mode']);
    $return = (string)($_SESSION['discord_oauth_return'] ?? 'index.php');
    unset($_SESSION['discord_oauth_return']);
    if (!rate_limit_check('discord_oauth', 20, 600)) {
        friendly_error('Too many OAuth attempts from your IP. Try again in 10 minutes.', 429);
    }

    // Ask the bridge to swap the code for every dataset it can read.
    $payload = json_encode([
        'code' => (string)$_GET['code'],
        'redirect_uri' => $redirectUri,
    ]);
    $ctx = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => "Content-Type: application/json\r\nAccept: application/json\r\nUser-Agent: kevbin\r\n",
            'content' => $payload,
            'timeout' => 15,
        ],
        'ssl' => ['verify_peer' => false, 'verify_peer_name' => false],
    ]);
    $resp = @file_get_contents($bridge . '/oauth/exchange', false, $ctx);
    $data = json_decode((string)$resp, true);

    $ds = is_array($data) ? (array)($data['datasets'] ?? []) : [];
    $dc = is_array($ds) ? (array)($ds['user'] ?? []) : [];
    $dcId = (string)($dc['id'] ?? '');
    $dcName = (string)($dc['username'] ?? '');
    $dcGlobal = (string)($dc['global_name'] ?? '');
    $dcAvatar = (string)($dc['avatar'] ?? '');
    $joinedGuilds = is_array($data) ? (array)($data['joined_guilds'] ?? []) : [];
    $joinedNote = count($joinedGuilds) > 0 ? ' You were also added to ' . count($joinedGuilds) . ' Discord server' . (count($joinedGuilds) > 1 ? 's' : '') . '.' : '';

    // Dashboard export: stash EVERYTHING the API returned so dashboard.php can
    // list each dataset (profile, servers + ids, friends/DM partners, connections).
    if ($exportMode && $dcId !== '') {
        $_SESSION['dc_export'] = [
            'at' => time(),
            'user' => [
                'id' => $dcId,
                'username' => $dcName,
                'global_name' => $dcGlobal,
                'avatar' => ($dcAvatar === '') ? '' : 'https://cdn.discordapp.com/avatars/' . $dcAvatar,
            ],
            'guilds' => array_values(array_map(static function ($g) {
                return [
                    'id' => (string)($g['id'] ?? ''),
                    'name' => (string)($g['name'] ?? ''),
                    'icon' => (string)($g['icon'] ?? ''),
                    'owner' => !empty($g['owner']),
                ];
            }, (array)($ds['guilds'] ?? []))),
            'relationships' => array_values(array_map(static function ($r) {
                $u = is_array($r['user'] ?? null) ? $r['user'] : [];
                return [
                    'type' => (int)($r['type'] ?? 0),
                    'id' => (string)($u['id'] ?? ''),
                    'username' => (string)($u['username'] ?? ''),
                    'global_name' => (string)($u['global_name'] ?? ''),
                    'avatar' => (string)($u['avatar'] ?? ''),
                ];
            }, (array)($ds['relationships'] ?? []))),
            'connections' => array_values(array_map(static function ($c) {
                return [
                    'type' => (string)($c['type'] ?? ''),
                    'name' => (string)($c['name'] ?? ''),
                    'verified' => !empty($c['verified']),
                ];
            }, (array)($ds['connections'] ?? []))),
        ];
    }
    if ($dcId === '' || $dcName === '') {
        log_activity('discord_oauth_failed', 'no profile from bridge');
        flash_set('error', 'Could not complete Discord login. Try again.');
        redirect($return);
    }
    $dcAvatar = ($dcAvatar === '') ? '' : 'https://cdn.discordapp.com/avatars/' . $dcAvatar . '.png';

    $pdo = db();
    $linked = $pdo->prepare('SELECT id FROM users WHERE discord_id = ?');
    $linked->execute([$dcId]);
    $linkedId = $linked->fetchColumn();

    if (current_user() !== null) {
        // Linking a Discord account to the currently logged-in user (from Settings).
        $uid = (int)current_user()['id'];
        if ($linkedId !== false && (int)$linkedId !== $uid) {
            flash_set('error', 'That Discord account is already linked to another user.');
            redirect($exportMode ? 'dashboard.php' : 'settings.php#discord');
        }
        $pdo->prepare('UPDATE users SET discord_id = ?, discord_username = ?, discord_avatar = ?, pfp = COALESCE(NULLIF(pfp, \'\'), ?) WHERE id = ?')
            ->execute([$dcId, mb_substr($dcName, 0, 64), $dcAvatar, $dcAvatar, $uid]);
        log_activity('discord_link', $dcName);
        flash_set('success', 'Discord account @' . $dcName . ' linked.' . $joinedNote);
        redirect($exportMode ? 'dashboard.php' : 'settings.php#discord');
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
        log_activity('discord_login', $dcName);
        flash_set('success', 'Welcome back, @' . $dcName . '!' . $joinedNote);
        redirect($exportMode ? 'dashboard.php' : safe_return($return));
    }

    // Brand-new account: username from Discord (may collide), unique fallback + avatar.
    $base = preg_replace('/[^A-Za-z0-9_.-]/', '', $dcName);
    if ($base === '') {
        $base = 'dc' . $dcId;
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
        'INSERT INTO users (username, password, role, status, created_at, pfp, discord_id, discord_username, discord_avatar)
         VALUES (?, ?, \'user\', \'active\', UTC_TIMESTAMP(), ?, ?, ?, ?)'
    )->execute([$username, password_hash(bin2hex(random_bytes(16)), PASSWORD_BCRYPT), $dcAvatar, $dcId, mb_substr($dcName, 0, 64), $dcAvatar]);
    $uid = (int)$pdo->lastInsertId();
    $_SESSION['pending_recovery_key'] = user_update_recovery_key($uid);
    session_regenerate_id(true);
    $_SESSION['user_id'] = $uid;
    log_activity('discord_register', $dcName);
    flash_set('success', 'Welcome, @' . $dcName . '! You can set a password any time in Settings.' . $joinedNote);
    redirect($exportMode ? 'dashboard.php' : 'register.php?key=1');
}

// --- Start leg: no code yet — send the user to the bridge's authorize URL. ---
$exportMode = isset($_GET['export']) && $_GET['export'] === '1';
$exportScope = (string)($cfg['discord_export_scope'] ?? 'identify guilds guilds.join connections');
$_SESSION['discord_oauth_state'] = bin2hex(random_bytes(16));
$_SESSION['discord_oauth_return'] = safe_return($exportMode ? 'dashboard.php' : 'index.php');
$_SESSION['discord_export_mode'] = $exportMode ? 1 : 0;
redirect($bridge . '/oauth/authorize?' . http_build_query([
    'redirect_uri' => $redirectUri,
    'state' => $_SESSION['discord_oauth_state'],
    'scope' => $exportMode ? $exportScope : 'identify guilds',
]));
