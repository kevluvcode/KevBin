<?php
require_once __DIR__ . '/../functions.php';

// Analytics tracking pixel. Anonymous, session-free, cookie-free: an <img> embed
// loads this file, we log one visit (enriched with IP/UA/geo/device) and reply
// with a transparent 1x1 GIF. Works in emails, signatures and any page that
// allows remote images.

header('Content-Type: image/gif');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('X-Content-Type-Options: nosniff');

$gif = base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7', true);
if ($gif === false) {
    $gif = "\x47\x49\x46\x38\x39\x61\x01\x00\x01\x00\x80\x00\x00\x00\x00\x00\xff\xff\xff\x21\xf9\x04\x00\x00\x00\x00\x00\x2c\x00\x00\x00\x00\x01\x00\x01\x00\x00\x02\x02\x44\x01\x00\x3b";
}

$code = (string)($_GET['c'] ?? '');
$measured = false;
if (preg_match('/^[A-Za-z0-9]{3,16}$/', $code)) {
    try {
        $stmt = db()->prepare('SELECT id FROM trackers WHERE code = ?');
        $stmt->execute([$code]);
        $tr = $stmt->fetch();
        if ($tr) {
            // Throttle detail-logging per IP but still count the hit.
            $detailed = rate_limit_check('px_track', 90, 60);
            tracker_record_view((int)$tr['id'], $detailed);
            $measured = true;
        }
    } catch (Throwable $t) {
        error_log('[t/px.php] ' . $t->getMessage());
    }
}

header('X-Tracked: ' . ($measured ? '1' : '0'));
echo $gif;
exit;