<?php
require_once __DIR__ . '/functions.php';

start_session();
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

// CORS preflight.
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// Allow POST only for specific actions.
$isPost = $_SERVER['REQUEST_METHOD'] === 'POST';
$postAction = $isPost ? (string)($_POST['action'] ?? $_GET['action'] ?? '') : '';
$allowedPost = ['link.create'];
if ($isPost && !in_array($postAction, $allowedPost, true)) {
    http_response_code(405);
    echo json_encode(['error' => 'POST is only allowed for: ' . implode(', ', $allowedPost)]);
    exit;
}

function api_out(array $data): void
{
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function api_err(string $msg, int $status = 400): void
{
    http_response_code($status);
    api_out(['error' => $msg]);
}

// Read-only public API: everything here is free to use, but nothing can be
// posted/created and user browsing is intentionally not exposed.
// Public API docs point to the Cloudflare Worker proxy, but api.php still
// serves the database directly (the worker proxies through here).
send_security_headers();

$action = $isPost ? $postAction : (string)($_GET['action'] ?? 'pastes');
$base = $GLOBALS['CFG']['base_url'];

try {
    $pdo = db();
} catch (Throwable $t) {
    api_err('Database unavailable.', 500);
}

// Public tool listings (mirrors tools/index.php).
const TOOLS = [
    ['category' => 'Code & Security', 'name' => 'Lua Obfuscator', 'path' => 'tools/lua-obfuscator/'],
    ['category' => 'Code & Security', 'name' => 'Hash Generator', 'path' => 'tools/hash/'],
    ['category' => 'Code & Security', 'name' => 'UID Generator', 'path' => 'tools/uid/'],
    ['category' => 'Code & Security', 'name' => 'JWT Decoder', 'path' => 'tools/jwt-decoder/'],
    ['category' => 'Code & Security', 'name' => 'Password Strength', 'path' => 'tools/passcheck/'],
    ['category' => 'Code & Security', 'name' => 'Cipher Decrypter', 'path' => 'tools/decrypt/'],
    ['category' => 'Code & Security', 'name' => 'Minifier', 'path' => 'tools/minify/'],
    ['category' => 'Code & Security', 'name' => 'Classic Ciphers', 'path' => 'tools/ciphers/'],
    ['category' => 'Developer', 'name' => 'Dev Toolkit', 'path' => 'tools/dev/'],
    ['category' => 'Developer', 'name' => 'Encoders', 'path' => 'tools/encoders/'],
    ['category' => 'Developer', 'name' => 'Unit Converter', 'path' => 'tools/convert/'],
    ['category' => 'Developer', 'name' => 'Color Converter', 'path' => 'tools/color-converter/'],
    ['category' => 'Developer', 'name' => 'Subnet Calculator', 'path' => 'tools/subnet-calculator/'],
    ['category' => 'Developer', 'name' => 'HTTP Request Builder', 'path' => 'tools/reqbuild/'],
    ['category' => 'Text', 'name' => 'Text Tools', 'path' => 'tools/text/'],
    ['category' => 'Text', 'name' => 'Random Generator', 'path' => 'tools/random/'],
    ['category' => 'Text', 'name' => 'ASCII Art Generator', 'path' => 'tools/asciiart/'],
    ['category' => 'Text', 'name' => 'Alphabetizer', 'path' => 'tools/sort/'],
    ['category' => 'Handy Utilities', 'name' => 'Password Generator', 'path' => 'tools/password/'],
    ['category' => 'Handy Utilities', 'name' => 'Wordlist Generator', 'path' => 'tools/wordlist/'],
    ['category' => 'Handy Utilities', 'name' => 'QR Code Generator', 'path' => 'tools/qr/'],
    ['category' => 'Handy Utilities', 'name' => 'Timestamp Converter', 'path' => 'tools/timestamp/'],
    ['category' => 'Handy Utilities', 'name' => 'Cron Parser', 'path' => 'tools/cron-parser/'],
    ['category' => 'Handy Utilities', 'name' => 'Slug Generator', 'path' => 'tools/slug/'],
    ['category' => 'Handy Utilities', 'name' => 'CSV / JSON Converter', 'path' => 'tools/csv/'],
    ['category' => 'Handy Utilities', 'name' => 'CSS Gradient Generator', 'path' => 'tools/gradient/'],
    ['category' => 'Handy Utilities', 'name' => 'Contrast Checker', 'path' => 'tools/contrast/'],
    ['category' => 'Handy Utilities', 'name' => 'Character Inspector', 'path' => 'tools/charcode/'],
    ['category' => 'Handy Utilities', 'name' => 'Hex Dump', 'path' => 'tools/hex-dump/'],
    ['category' => 'Handy Utilities', 'name' => 'Date Duration', 'path' => 'tools/duration/'],
    ['category' => 'Handy Utilities', 'name' => 'Image / Base64', 'path' => 'tools/imgbase64/'],
    ['category' => 'OSINT & Research', 'name' => 'OSINT Toolkit', 'path' => 'tools/osint/'],
    ['category' => 'OSINT & Research', 'name' => 'Username Search', 'path' => 'tools/username/'],
    ['category' => 'OSINT & Research', 'name' => 'Subdomain Finder', 'path' => 'tools/subenum/'],
    ['category' => 'OSINT & Research', 'name' => 'Reverse IP Lookup', 'path' => 'tools/revip/'],
    ['category' => 'OSINT & Research', 'name' => 'ASN / BGP Lookup', 'path' => 'tools/asnintel/'],
    ['category' => 'OSINT & Research', 'name' => 'MAC Vendor Lookup', 'path' => 'tools/macfind/'],
    ['category' => 'OSINT & Research', 'name' => 'Port Scanner', 'path' => 'tools/portscan/'],
    ['category' => 'OSINT & Research', 'name' => 'DNS Lookup', 'path' => 'tools/dns/'],
    ['category' => 'OSINT & Research', 'name' => 'Header Inspector', 'path' => 'tools/headers/'],
    ['category' => 'Developer', 'name' => 'Discord Webhook Sender', 'path' => 'tools/webhook/'],
    ['category' => 'Developer', 'name' => 'Discord Webhook Spammer', 'path' => 'tools/webhook-spam/'],
    ['category' => 'Developer', 'name' => 'Discord Webhook Deleter', 'path' => 'tools/webhook-delete/'],
    ['category' => 'Developer', 'name' => 'Link Spoof / Obfuscator', 'path' => 'tools/link-spoof/'],
    ['category' => 'Handy Utilities', 'name' => 'Photo Metadata', 'path' => 'tools/photo-meta/'],
];

switch ($action) {
    case 'pastes': // Public paste feed (read-only). Supports ?limit= and ?offset= for pagination.
        $limit = max(1, min(100, (int)($_GET['limit'] ?? 20)));
        $offset = max(0, (int)($_GET['offset'] ?? 0));
        $stmt = $pdo->prepare(
            'SELECT p.id, p.title, p.author, p.created_at, p.views, p.user_id,
                    u.username AS owner_name, u.profile_color AS owner_color
             FROM pastes p
             LEFT JOIN users u ON u.id = p.user_id
             WHERE (p.expires_at IS NULL OR p.expires_at > UTC_TIMESTAMP())
               AND p.password_hash IS NULL
             ORDER BY p.created_at DESC
             LIMIT ? OFFSET ?'
        );
        $stmt->bindValue(1, $limit, PDO::PARAM_INT);
        $stmt->bindValue(2, $offset, PDO::PARAM_INT);
        $stmt->execute();
        $items = [];
        foreach ($stmt->fetchAll() as $row) {
            $items[] = [
                'id' => $row['id'],
                'title' => $row['title'],
                'author' => $row['user_id'] !== null ? ($row['owner_name'] ?? $row['author']) : $row['author'],
                'owner_id' => $row['user_id'] !== null ? (int)$row['user_id'] : null,
                'owner_color' => $row['owner_color'] ?? '',
                'created_at' => $row['created_at'],
                'views' => (int)$row['views'],
                'url' => $base . 'view.php?id=' . rawurlencode($row['id']),
            ];
        }
        api_out([
            'site' => $GLOBALS['CFG']['site_name'],
            'generated_at' => gmdate('c'),
            'limit' => $limit,
            'offset' => $offset,
            'count' => count($items),
            'pastes' => $items,
        ]);

    case 'paste': // Read a single public paste (password-protected ones are not exposed).
        $id = (string)($_GET['id'] ?? '');
        if ($id === '' || strlen($id) > 40 || !preg_match('/^[A-Za-z0-9]+$/', $id)) {
            api_err('Missing or invalid "id".', 400);
        }
        $stmt = $pdo->prepare(
            'SELECT id, title, author, content, created_at, views, user_id, password_hash
             FROM pastes
             WHERE id = ? AND (expires_at IS NULL OR expires_at > UTC_TIMESTAMP())'
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if (!$row) {
            api_err('Paste not found or expired.', 404);
        }
        if (!empty($row['password_hash'])) {
            api_err('This paste is password-protected and is not available via the API.', 403);
        }
        $pdo->prepare('UPDATE pastes SET views = views + 1 WHERE id = ?')->execute([$id]);
        api_out([
            'site' => $GLOBALS['CFG']['site_name'],
            'generated_at' => gmdate('c'),
            'paste' => [
                'id' => $row['id'],
                'title' => $row['title'],
                'author' => $row['author'],
                'content' => $row['content'],
                'created_at' => $row['created_at'],
                'views' => (int)$row['views'] + 1,
            ],
        ]);

    case 'stats': // Site counters (user browsing itself is not exposed).
        $users = (int)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
        $pastes = (int)$pdo->query('SELECT COUNT(*) FROM pastes')->fetchColumn();
        $links = (int)$pdo->query('SELECT COUNT(*) FROM links')->fetchColumn();
        api_out([
            'site' => $GLOBALS['CFG']['site_name'],
            'generated_at' => gmdate('c'),
            'stats' => [
                'users' => $users,
                'pastes' => $pastes,
                'links' => $links,
                'tools' => count(TOOLS),
                'online' => online_count(),
                'online_window_seconds' => online_window_seconds(),
            ],
        ]);

    case 'online': // Live online count (real-time widget friendly).
        api_out([
            'site' => $GLOBALS['CFG']['site_name'],
            'generated_at' => gmdate('c'),
            'online' => online_count(),
            'window_seconds' => online_window_seconds(),
        ]);

    case 'heartbeat': // Presence ping (used by the site navbar; returns live count).
        api_out([
            'site' => $GLOBALS['CFG']['site_name'],
            'generated_at' => gmdate('c'),
            'online' => online_ping(session_id() ?: null),
        ]);

    case 'tools': // All public tools — usable by anyone, no account needed.
        $list = [];
        foreach (TOOLS as $t) {
            $list[] = [
                'name' => $t['name'],
                'category' => $t['category'],
                'url' => $base . $t['path'],
            ];
        }
        api_out([
            'site' => $GLOBALS['CFG']['site_name'],
            'generated_at' => gmdate('c'),
            'count' => count($list),
            'tools' => $list,
        ]);

    case 'user': // Count-only. Browsing users/profiles is not available through the API.
        api_out([
            'site' => $GLOBALS['CFG']['site_name'],
            'generated_at' => gmdate('c'),
            'user_count' => (int)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn(),
            'note' => 'User browsing is not available via the API.',
        ]);

    case 'updates': // Public update log.
        $stmt = $pdo->query('SELECT id, title, content, created_at FROM updates ORDER BY created_at DESC LIMIT 20');
        $items = [];
        foreach ($stmt->fetchAll() as $row) {
            $items[] = [
                'id' => (int)$row['id'],
                'title' => $row['title'],
                'content' => $row['content'],
                'created_at' => $row['created_at'],
            ];
        }
        api_out([
            'site' => $GLOBALS['CFG']['site_name'],
            'generated_at' => gmdate('c'),
            'updates' => $items,
        ]);

    case 'link.get': // Look up a short link by code.
        $code = (string)($_GET['code'] ?? $_GET['slug'] ?? '');
        if ($code === '' || !preg_match('/^[A-Za-z0-9]{3,16}$/', $code)) {
            api_err('Missing or invalid "code".', 400);
        }
        $stmt = $pdo->prepare('SELECT code, target_url, title, clicks, created_at FROM links WHERE code = ?');
        $stmt->execute([$code]);
        $row = $stmt->fetch();
        if (!$row) {
            api_err('Link not found.', 404);
        }
        api_out([
            'ok' => true,
            'code' => $row['code'],
            'url' => $row['target_url'],
            'title' => $row['title'],
            'clicks' => (int)$row['clicks'],
            'created_at' => $row['created_at'],
        ]);

    case 'link.create': // Create a short link (POST only, no auth required for anonymous links).
        if (!$isPost) {
            api_err('link.create requires POST.', 405);
        }
        $targetUrl = trim((string)($_POST['url'] ?? ''));
        if ($targetUrl === '' || !preg_match('#^https?://#i', $targetUrl)) {
            api_err('Missing or invalid "url" — must start with http:// or https://', 400);
        }
        $targetUrl = mb_substr($targetUrl, 0, 2048);

        $customCode = trim((string)($_POST['code'] ?? $_POST['slug'] ?? ''));
        if ($customCode !== '') {
            if (!preg_match('/^[A-Za-z0-9]{3,16}$/', $customCode)) {
                api_err('Custom code must be 3-16 alphanumeric characters.', 400);
            }
            $stmt = $pdo->prepare('SELECT 1 FROM links WHERE code = ?');
            $stmt->execute([$customCode]);
            if ($stmt->fetch()) {
                api_err('That custom code is already taken.', 409);
            }
            $code = $customCode;
        } else {
            // Generate a random 6-char code.
            $code = generate_link_code(6);
        }

        $manageKey = bin2hex(random_bytes(16));
        try {
            $pdo->prepare('INSERT INTO links (code, user_id, manage_key, target_url, title, tracking, clicks, created_at) VALUES (?, NULL, ?, ?, \'\', 1, 0, UTC_TIMESTAMP())')
                ->execute([$code, $manageKey, $targetUrl]);
        } catch (Throwable $t) {
            api_err('Failed to create link: ' . $t->getMessage(), 500);
        }

        $shortUrl = link_short_url($code);
        api_out([
            'ok' => true,
            'code' => $code,
            'short' => $shortUrl,
            'target' => $targetUrl,
            'manage_key' => $manageKey,
        ]);

    default:
        api_err('Unknown action. Available: pastes, paste, stats, online, tools, user.', 404);
}