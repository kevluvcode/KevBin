<?php
require_once __DIR__ . '/functions.php';

start_session();

$code = (string)($_GET['code'] ?? '');
if (!preg_match('/^[A-Za-z0-9]{3,16}$/', $code)) {
    friendly_error('Link not found.', 404);
}

$pdo = db();
$stmt = $pdo->prepare('SELECT id, code, target_url, tracking, clicks FROM links WHERE code = ?');
$stmt->execute([$code]);
$link = $stmt->fetch();

if (!$link) {
    friendly_error('Link not found.', 404);
}

$target = (string)$link['target_url'];
if (strpbrk($target, "\r\n\0") !== false) {
    friendly_error('This link is no longer available.', 403);
}
if (!in_array(strtolower((string)parse_url($target, PHP_URL_SCHEME)), ['http', 'https'], true)) {
    friendly_error('This link is no longer available.', 403);
}

header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');

// Record the visit before redirecting (tracking links log IP/UA/referer/geo).
if ((int)$link['tracking'] === 1) {
    $ip = client_ip();
    $ua = mb_substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255);
    $referer = mb_substr((string)($_SERVER['HTTP_REFERER'] ?? ''), 0, 512);
    $geo = lookup_ip_geo($ip);
    try {
        $pdo->prepare(
            'INSERT INTO link_clicks (link_id, ip, ua, referer, country, region, city) VALUES (?, ?, ?, ?, ?, ?, ?)'
        )->execute([(int)$link['id'], $ip, $ua !== '' ? $ua : null, $referer !== '' ? $referer : null, $geo['country'], $geo['region'], $geo['city']]);
    } catch (Throwable $t) {
        error_log('[s.php] ' . $t->getMessage());
    }
}

try {
    $pdo->prepare('UPDATE links SET clicks = clicks + 1, last_click = UTC_TIMESTAMP() WHERE id = ?')
        ->execute([(int)$link['id']]);
} catch (Throwable $t) {
    error_log('[s.php] ' . $t->getMessage());
}

header('Location: ' . $target, true, 302);
exit;