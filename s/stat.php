<?php
require_once __DIR__ . '/../functions.php';

start_session();
header('Cache-Control: no-store, no-cache, must-revalidate');
header('X-Robots-Tag: noindex');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit;
}

$raw = (string)file_get_contents('php://input');
$data = json_decode($raw, true);
if (!is_array($data)) {
    http_response_code(400);
    exit;
}

$clickId = (int)($data['id'] ?? 0);
$token = (string)($data['tok'] ?? '');
$f = isset($data['f']) && is_array($data['f']) ? $data['f'] : [];

if ($clickId <= 0 || $token === '') {
    http_response_code(400);
    exit;
}
if (!rate_limit_check('link_beacon', 30, 60)) {
    http_response_code(429);
    exit;
}

$ip = client_ip();
$expected = hash_hmac('sha256', $clickId . '|' . $ip, recovery_salt());
if (!hash_equals($expected, $token)) {
    http_response_code(403);
    exit;
}

$screen = mb_substr((string)($f['screen'] ?? ''), 0, 24);
$tzone = mb_substr((string)($f['tz'] ?? ''), 0, 64);
$lang = mb_substr((string)($f['lang'] ?? ''), 0, 32);
$dpr = mb_substr((string)($f['dpr'] ?? ''), 0, 8);
$hw = mb_substr((string)($f['hw'] ?? ''), 0, 8);
$touch = !empty($f['touch']) ? 1 : 0;
$fp = mb_substr($raw, 0, 2000);

try {
    $stmt = db()->prepare(
        'UPDATE link_clicks
         SET screen = COALESCE(NULLIF(?, \'\'), screen),
             timezone = COALESCE(NULLIF(?, \'\'), timezone),
             language = COALESCE(NULLIF(?, \'\'), language),
             dpr = COALESCE(NULLIF(?, \'\'), dpr),
             hw_concurrency = COALESCE(NULLIF(?, \'\'), hw_concurrency),
             touch = ?,
             fp = COALESCE(NULLIF(?, \'\'), fp)
         WHERE id = ? AND ip = ?'
    );
    $stmt->execute([$screen, $tzone, $lang, $dpr, $hw, $touch, $fp, $clickId, $ip]);
} catch (Throwable $t) {
    error_log('[stat.php] ' . $t->getMessage());
}

http_response_code(204);
exit;