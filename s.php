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

// Tracking links log IP / UA / referer / geo / device / browser / hints,
// then serve a 0-second interstitial that collects a JS fingerprint beacon
// (screen, timezone, touch, concurrency, canvas...) before redirecting.
if ((int)$link['tracking'] === 1) {
    $ip = client_ip();
    $ua = mb_substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255);
    $referer = mb_substr((string)($_SERVER['HTTP_REFERER'] ?? ''), 0, 512);
    $env = collect_click_env();
    $geo = lookup_ip_geo($ip);
    try {
        $stmt = $pdo->prepare(
            'INSERT INTO link_clicks
                (link_id, ip, ua, referer, country, region, city, isp, asn,
                 is_proxy, is_vpn, is_tor, is_crawler, lat, lon,
                 browser, browser_version, os, os_version, device, is_bot,
                 language, dpr, touch, hw_concurrency)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            (int)$link['id'],
            $ip,
            $ua !== '' ? $ua : null,
            $referer !== '' ? $referer : null,
            $geo['country'], $geo['region'], $geo['city'],
            $geo['isp'], $geo['asn'],
            (int)$geo['is_proxy'], (int)$geo['is_vpn'], (int)$geo['is_tor'], (int)$geo['is_crawler'],
            $geo['lat'], $geo['lon'],
            $env['browser'] !== 'Unknown' ? $env['browser'] : null,
            $env['browser_version'] !== '' ? $env['browser_version'] : null,
            $env['os'] !== 'Unknown' ? $env['os'] : null,
            $env['os_version'] !== '' ? $env['os_version'] : null,
            $env['device'],
            (int)$env['is_bot'],
            $env['language'], $env['dpr'], (int)$env['touch'], $env['hw_concurrency'],
        ]);
        $clickId = (int)$pdo->lastInsertId();
    } catch (Throwable $t) {
        error_log('[s.php] ' . $t->getMessage());
        $clickId = 0;
    }
} else {
    $clickId = 0;
}

try {
    $pdo->prepare('UPDATE links SET clicks = clicks + 1, last_click = UTC_TIMESTAMP() WHERE id = ?')
        ->execute([(int)$link['id']]);
} catch (Throwable $t) {
    error_log('[s.php] ' . $t->getMessage());
}

// Untracked links keep the instant 302.
if ((int)$link['tracking'] !== 1) {
    header('Location: ' . $target, true, 302);
    exit;
}

$base = rtrim((string)($GLOBALS['CFG']['base_url'] ?? ''), '/');
$statUrl = $base . '/s/stat.php';
$token = $clickId > 0
    ? hash_hmac('sha256', $clickId . '|' . client_ip(), recovery_salt())
    : '';
$targetAttr = htmlspecialchars($target, ENT_QUOTES, 'UTF-8');
$targetJs = json_encode($target);
$statJs = json_encode($statUrl);
$tokenJs = json_encode($token);
$clickJs = json_encode($clickId);

header('Content-Type: text/html; charset=utf-8');
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="robots" content="noindex, nofollow">
<meta http-equiv="refresh" content="0;url=<?= $targetAttr ?>">
<title>Redirecting&hellip;</title>
<style>body{margin:0;background:#0b0e17;color:#cbd5e1;font-family:system-ui,-apple-system,Segoe UI,Roboto,sans-serif;height:100vh;display:grid;place-items:center;text-align:center}main{padding:1rem}a{color:#6872f2}</style>
</head>
<body>
<main><p>Redirecting&hellip; <a href="<?= $targetAttr ?>">Click here if you are not redirected</a>.</p></main>
<script>
try{
    var d = {
        tz: (Intl.DateTimeFormat().resolvedOptions().timeZone || ''),
        screen: (screen.width + 'x' + screen.height + (screen.pixelDepth ? ':' + screen.pixelDepth : '')),
        dpr: (window.devicePixelRatio || 1),
        touch: (('ontouchstart' in window) || (navigator.maxTouchPoints > 0)) ? 1 : 0,
        lang: (navigator.language || ''),
        langs: (navigator.languages || []).join(','),
        hw: (navigator.hardwareConcurrency || 0),
        mem: (navigator.deviceMemory || 0),
        pdf: (navigator.mimeTypes && navigator.mimeTypes['application/pdf']) ? 1 : 0,
        plugins: (navigator.plugins ? navigator.plugins.length : 0),
        platform: (navigator.platform || ''),
        canvas: (function(){ try{ var c=document.createElement('canvas');c.width=160;c.height=30;var g=c.getContext('2d');g.textBaseline='top';g.font='14px Arial';g.fillStyle='#f00';g.fillText('KevBin'+String.fromCharCode(9617,9622,9608),2,2);return c.toDataURL();}catch(e){return '';} })(),
        webgl: (function(){ try{ var gl=document.createElement('canvas').getContext('webgl'); if(!gl) return ''; var e=gl.getExtension('WEBGL_debug_renderer_info'); return gl.getParameter(e?e.UNMASKED_RENDERER_WEBGL:0x1F01)||''; }catch(e){ return ''; } })()
    };
    var blob = new Blob([JSON.stringify({ id: <?= $clickJs ?>, tok: <?= $tokenJs ?>, f: d })], { type: 'application/json' });
    if (navigator.sendBeacon) { navigator.sendBeacon(<?= $statJs ?>, blob); }
    else { fetch(<?= $statJs ?>, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: blob }).catch(function(){}); }
}catch(e){}
</script>
</body>
</html>
<?php
exit;