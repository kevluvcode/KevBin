<?php
require_once __DIR__ . '/functions.php';

start_session();
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

// CORS preflight.
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
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
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    api_err('This API is read-only. POST/PUT/DELETE are not allowed.', 405);
}
send_security_headers();

$action = (string)($_GET['action'] ?? 'pastes');
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
    ['category' => 'Code & Security', 'name' => 'JWT Inspector', 'path' => 'tools/jwt/'],
    ['category' => 'Code & Security', 'name' => 'Password Strength', 'path' => 'tools/passcheck/'],
    ['category' => 'Code & Security', 'name' => 'Cipher Decrypter', 'path' => 'tools/decrypt/'],
    ['category' => 'Code & Security', 'name' => 'Minifier', 'path' => 'tools/minify/'],
    ['category' => 'Developer', 'name' => 'Dev Toolkit', 'path' => 'tools/dev/'],
    ['category' => 'Developer', 'name' => 'Encoders', 'path' => 'tools/encoders/'],
    ['category' => 'Developer', 'name' => 'Unit Converter', 'path' => 'tools/convert/'],
    ['category' => 'Developer', 'name' => 'Color Converter', 'path' => 'tools/color/'],
    ['category' => 'Developer', 'name' => 'IP / Subnet Calc', 'path' => 'tools/net/'],
    ['category' => 'Text', 'name' => 'Text Tools', 'path' => 'tools/text/'],
    ['category' => 'Text', 'name' => 'Random Generator', 'path' => 'tools/random/'],
    ['category' => 'Text', 'name' => 'Alphabetizer', 'path' => 'tools/sort/'],
    ['category' => 'Handy Utilities', 'name' => 'Password Generator', 'path' => 'tools/password/'],
    ['category' => 'Handy Utilities', 'name' => 'QR Code Generator', 'path' => 'tools/qr/'],
    ['category' => 'Handy Utilities', 'name' => 'Timestamp Converter', 'path' => 'tools/timestamp/'],
    ['category' => 'Handy Utilities', 'name' => 'Cron Explainer', 'path' => 'tools/crontab/'],
    ['category' => 'Handy Utilities', 'name' => 'Slug Generator', 'path' => 'tools/slug/'],
    ['category' => 'Handy Utilities', 'name' => 'CSV / JSON Converter', 'path' => 'tools/csv/'],
    ['category' => 'Handy Utilities', 'name' => 'CSS Gradient Generator', 'path' => 'tools/gradient/'],
    ['category' => 'Handy Utilities', 'name' => 'Contrast Checker', 'path' => 'tools/contrast/'],
    ['category' => 'Handy Utilities', 'name' => 'Character Inspector', 'path' => 'tools/charcode/'],
    ['category' => 'Handy Utilities', 'name' => 'Hex Dump', 'path' => 'tools/hexdump/'],
    ['category' => 'Handy Utilities', 'name' => 'Date Duration', 'path' => 'tools/duration/'],
    ['category' => 'Handy Utilities', 'name' => 'Image / Base64', 'path' => 'tools/imgbase64/'],
    ['category' => 'OSINT & Research', 'name' => 'OSINT Toolkit', 'path' => 'tools/osint/'],
    ['category' => 'OSINT & Research', 'name' => 'Username Search', 'path' => 'tools/username/'],
    ['category' => 'OSINT & Research', 'name' => 'DNS Lookup', 'path' => 'tools/dns/'],
    ['category' => 'OSINT & Research', 'name' => 'Header Inspector', 'path' => 'tools/headers/'],
];

switch ($action) {
    case 'pastes': // Public paste feed (read-only).
        $limit = max(1, min(100, (int)($_GET['limit'] ?? 20)));
        $stmt = $pdo->prepare(
            'SELECT p.id, p.title, p.author, p.created_at, p.views, p.user_id,
                    u.username AS owner_name, u.profile_color AS owner_color
             FROM pastes p
             LEFT JOIN users u ON u.id = p.user_id
             WHERE (p.expires_at IS NULL OR p.expires_at > UTC_TIMESTAMP())
               AND p.password_hash IS NULL
             ORDER BY p.created_at DESC
             LIMIT ?'
        );
        $stmt->bindValue(1, $limit, PDO::PARAM_INT);
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

    default:
        api_err('Unknown action. Available: pastes, paste, stats, online, tools, user.', 404);
}