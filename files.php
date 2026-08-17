<?php
require_once __DIR__ . '/functions.php';

start_session();
$cfg = $GLOBALS['CFG'];

// Storage dir (relative to this script's directory = htdocs).
$uploadsDir = __DIR__ . '/' . rtrim((string)($cfg['uploads_dir'] ?? 'uploads/'), '/') . '/';
$maxBytes = upload_byte_limit();
$maxBytesMb = (int)ceil($maxBytes / 1048576);
$blockedExts = array_filter(array_map('strtolower', array_map('trim', explode(',', (string)($cfg['upload_blocked_exts'] ?? 'php,js,html')))));
$blockedNames = array_filter(array_map('strtolower', array_map('trim', explode(',', (string)($cfg['upload_blocked_names'] ?? 'index.html,index.htm,index.php,index.phtml')))));
$extList = []; // legacy whitelist — empty means "all types allowed except the blocked ones"

// Every extension is accepted except the blocked ones (plus a few exact
// filenames that hosts would treat as directory indexes).
function blocked_upload(string $filename, array $blockedExts, array $blockedNames): bool
{
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    if ($ext !== '' && in_array($ext, $blockedExts, true)) {
        return true;
    }
    return in_array(strtolower(basename($filename)), $blockedNames, true);
}

function upload_mime_map(): array
{
    return [
        'png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg',
        'gif' => 'image/gif', 'webp' => 'image/webp',
        'mp3' => 'audio/mpeg', 'ogg' => 'audio/ogg', 'wav' => 'audio/wav',
        'mp4' => 'video/mp4', 'webm' => 'video/webm',
        'pdf' => 'application/pdf', 'txt' => 'text/plain', 'md' => 'text/markdown',
        'json' => 'application/json', 'csv' => 'text/csv', 'log' => 'text/plain',
        'xml' => 'application/xml', 'yaml' => 'application/yaml',
        'zip' => 'application/zip', 'gz' => 'application/gzip',
        'doc' => 'application/msword',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'xls' => 'application/vnd.ms-excel',
        'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'ppt' => 'application/vnd.ms-powerpoint',
        'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
    ];
}

function upload_dir_ready(string $dir): bool
{
    if (!is_dir($dir)) {
        if (!@mkdir($dir, 0755, true)) {
            return false;
        }
    }
    if (!is_writable($dir)) {
        @chmod($dir, 0755);
    }
    return is_dir($dir) && is_writable($dir);
}

function ensure_upload_htaccess(string $dir): void
{
    $file = $dir . '.htaccess';
    $rule = "Options -Indexes -MultiViews\n"
        . "<IfModule mod_authz_core.c>\n"
        . "    <FilesMatch \"(?i)\\.(php|php3|php4|php5|php7|php8|phps|phar|phtml|pht|cgi|pl|py|sh|bash|asp|aspx|jsp|jspx|shtml|inc|conf|ini)$\">\n"
        . "        Require all denied\n"
        . "    </FilesMatch>\n"
        . "    <FilesMatch \"(?i)^\\.\">\n"
        . "        Require all denied\n"
        . "    </FilesMatch>\n"
        . "</IfModule>\n"
        . "<IfModule mod_authz_host.c>\n"
        . "    <FilesMatch \"(?i)\\.(php|php3|php4|php5|php7|php8|phps|phar|phtml|pht|cgi|pl|py|sh|bash|asp|aspx|jsp|jspx|shtml|inc|conf|ini)$\">\n"
        . "        Order allow,deny\n"
        . "        Deny from all\n"
        . "    </FilesMatch>\n"
        . "</IfModule>\n"
        . "php_flag engine off\n";
    @file_put_contents($file, $rule);
}

function mirror_sites(): array
{
    return ['catbox', 'litterbox', 'tmpfiles', 'uguu'];
}

function mirror_labels(): array
{
    return [
        'catbox'    => 'Catbox',
        'litterbox' => 'Litterbox (72h)',
        'tmpfiles'  => 'Tmpfiles.org',
        'uguu'      => 'Uguu.se',
    ];
}

function mirror_endpoints(): array
{
    return [
        'catbox'    => ['url' => 'https://catbox.moe/user/api.php', 'method' => 'POST', 'fields' => ['reqtype' => 'fileupload'], 'file' => 'fileToUpload', 'ret' => 'raw'],
        'litterbox' => ['url' => 'https://litterbox.catbox.moe/resources/internals/api.php', 'method' => 'POST', 'fields' => ['reqtype' => 'fileupload', 'time' => '72h'], 'file' => 'fileToUpload', 'ret' => 'raw'],
        'tmpfiles'  => ['url' => 'https://tmpfiles.org/api/v1/upload', 'method' => 'POST', 'fields' => [], 'file' => 'file', 'ret' => 'tmpfiles'],
        'uguu'      => ['url' => 'https://uguu.se/upload.php', 'method' => 'POST', 'fields' => [], 'file' => 'files[]', 'ret' => 'uguu'],
        'pomf'      => ['url' => 'https://pomf2.lain.la/upload.php', 'method' => 'POST', 'fields' => [], 'file' => 'files[]', 'ret' => 'pomf'],
        'fileio'    => ['url' => 'https://file.io/', 'method' => 'POST', 'fields' => [], 'file' => 'file', 'ret' => 'fileio'],
        '0x0'       => ['url' => 'https://0x0.st', 'method' => 'POST', 'fields' => [], 'file' => 'file', 'ret' => 'raw'],
        'telegraph' => ['url' => 'https://telegra.ph/upload', 'method' => 'POST', 'fields' => [], 'file' => 'file', 'ret' => 'telegraph'],
    ];
}

function mirror_parse(string $ret, string $body): string
{
    $body = trim($body);
    if ($ret === 'raw') {
        return preg_match('#^https?://\S+$#i', $body) ? $body : '';
    }
    if ($ret === 'tmpfiles') {
        $j = json_decode($body, true);
        if (isset($j['data']['url'])) {
            return preg_replace('#^https?://tmpfiles\.org/#', 'https://tmpfiles.org/dl/', (string)$j['data']['url']);
        }
        return '';
    }
    if ($ret === 'uguu' || $ret === 'pomf') {
        $j = json_decode($body, true);
        if (($j['success'] ?? false) && !empty($j['files'][0]['url'])) {
            return (string)$j['files'][0]['url'];
        }
        return '';
    }
    if ($ret === 'fileio') {
        $j = json_decode($body, true);
        if (($j['success'] ?? false) && !empty($j['link'])) {
            return (string)$j['link'];
        }
        return '';
    }
    if ($ret === 'telegraph') {
        $j = json_decode($body, true);
        if (is_array($j) && !empty($j[0]['src'])) {
            $src = (string)$j[0]['src'];
            return preg_match('#^https?://#i', $src) ? $src : 'https://telegra.ph' . $src;
        }
        return '';
    }
    return '';
}

// Randomizes a filename for external hosters (keeps the extension) so hosted
// URLs can't be guessed. e.g. "static1.png" -> "jasdomn129.png".
function random_upload_name(string $original): string
{
    $ext = strtolower(pathinfo($original, PATHINFO_EXTENSION));
    $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    $len = strlen($chars);
    $base = '';
    $nameLen = random_int(8, 12);
    for ($i = 0; $i < $nameLen; $i++) {
        $base .= $chars[random_int(0, $len - 1)];
    }
    return $base . ($ext !== '' ? '.' . $ext : '');
}

// Pushes the file's bytes to an external file-hoster and returns the share URL.
// Returns '' with $err set on failure.
function mirror_upload(string $site, string $filename, string $data, string &$err = ''): string
{
    $ep = mirror_endpoints()[$site] ?? null;
    if ($ep === null || !function_exists('curl_init')) {
        $err = 'Unavailable on this server.';
        return '';
    }
    $tmp = tempnam(sys_get_temp_dir(), 'kb');
    if ($tmp === false || !@file_put_contents($tmp, $data)) {
        $err = 'Could not stage the file.';
        if ($tmp !== false) {
            @unlink($tmp);
        }
        return '';
    }
    $mime = 'application/octet-stream';
    if (function_exists('finfo_open')) {
        $fi = finfo_open(FILEINFO_MIME_TYPE);
        $mime = $fi ? (finfo_file($fi, $tmp) ?: $mime) : $mime;
        if ($fi) {
            finfo_close($fi);
        }
    }
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $ep['url']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 45);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)');
    if ($ep['method'] === 'PUT') {
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: ' . $mime]);
    } else {
        $fields = $ep['fields'];
        $fields[$ep['file']] = new CURLFile($tmp, $mime, $filename);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $fields);
    }
    $body = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);
    @unlink($tmp);
    $url = mirror_parse($ep['ret'], (string)$body);
    if ($url === '') {
        $err = $code . ($body !== '' ? ' ' . substr(trim((string)$body), 0, 160) : '');
    }
    return $url;
}

// Pushes a single staged temp file to one hoster. Returns
// ['url' => share-url-or-empty, 'err' => error-text].
function mirror_single_push(string $site, string $tmp, string $mime, string $filename): array
{
    $ep = mirror_endpoints()[$site] ?? null;
    if ($ep === null) {
        return ['url' => '', 'err' => 'Unknown hoster.'];
    }
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $ep['url']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 8);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)');
    $fields = $ep['fields'];
    $fields[$ep['file']] = new CURLFile($tmp, $mime, $filename);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $fields);
    $body = (string)curl_exec($ch);
    $curlErr = curl_error($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);
    $url = mirror_parse($ep['ret'], $body);
    if ($url !== '') {
        return ['url' => $url, 'err' => ''];
    }
    $err = $curlErr !== '' ? $curlErr : ($code . ($body !== '' ? ' ' . substr(trim($body), 0, 160) : ''));
    return ['url' => '', 'err' => $err];
}

// Pushes to every configured hoster (sequentially — InfinityFree has no
// curl_multi extension). Returns ['ok' => [site => url], 'fail' => [site => err]].
function mirror_upload_all(string $filename, string $data, string $mime): array
{
    $sites = mirror_sites();
    $isImage = strpos($mime, 'image/') === 0;
    $tmp = tempnam(sys_get_temp_dir(), 'kb');
    if ($tmp === false || !@file_put_contents($tmp, $data)) {
        if ($tmp !== false) {
            @unlink($tmp);
        }
        return ['ok' => [], 'fail' => array_fill_keys($sites, 'Could not stage the file.')];
    }
    $ok = [];
    $fail = [];
    foreach ($sites as $site) {
        if ($site === 'telegraph' && !$isImage) {
            $fail[$site] = 'Skipped (images only).';
            continue;
        }
        $res = mirror_single_push($site, $tmp, $mime, $filename);
        if ($res['url'] !== '') {
            $ok[$site] = $res['url'];
        } else {
            $fail[$site] = $res['err'];
        }
    }
    @unlink($tmp);
    return ['ok' => $ok, 'fail' => $fail];
}

$me = current_user();
$flash = null;
$newUpload = null;
$myFiles = [];

// ——— Download ———
if (isset($_GET['d'])) {
    $id = (string)$_GET['d'];
    $stmt = db()->prepare('SELECT * FROM uploads WHERE id = ?');
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    if ($row === false) {
        friendly_error('File not found.', 404);
    }
    if ($row['expires_at'] !== null && strtotime((string)$row['expires_at']) <= time()) {
        friendly_error('This file has expired.', 410);
    }
    $data = (string)($row['file_data'] ?? '');
    $mirror = (string)($row['mirror_url'] ?? '');
    $path = $uploadsDir . $row['stored_name'];
    if ($data === '') {
        // Legacy row still holding the bytes on disk — read it and migrate it
        // into the DB so nothing is ever served back out of the public folder.
        if (is_file($path)) {
            $data = @file_get_contents($path);
        }
        if ($data !== '' && $data !== false) {
            try {
                db()->prepare('UPDATE uploads SET file_data = ?, size = ? WHERE id = ?')
                    ->execute([$data, strlen($data), $id]);
                @unlink($path);
            } catch (Throwable $t) {
                // keep the disk copy if the DB update fails
            }
        }
    }
    if ($data === '') {
        // File lives only on an external hoster — bounce the visitor there.
        if ($mirror === '') {
            friendly_error('File is missing.', 404);
        }
        try {
            db()->prepare('UPDATE uploads SET downloads = downloads + 1 WHERE id = ?')->execute([$id]);
        } catch (Throwable $t) {
            // download counter is best-effort
        }
        redirect($mirror);
    }
    $mime = (string)($row['mime'] ?? 'application/octet-stream');
    // Never let uploaded content masquerade as HTML/SVG/script so it can run in
    // the browser on this origin — force those to a plain octet-stream download.
    if (preg_match('#^(text/html|application/xhtml\+xml|image/svg\+xml|application/(x-)?httpd-php|text/x-php|application/php|application/x-sh|text/x-sh|text/javascript|application/x-javascript|application/x-msdos-program)#i', $mime)) {
        $mime = 'application/octet-stream';
    }
    $inline = in_array(explode('/', $mime)[0] ?? '', ['image', 'text', 'audio', 'video'], true) || $mime === 'application/pdf';
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    header('Content-Type: ' . $mime);
    header('Content-Length: ' . strlen($data));
    header('Content-Disposition: ' . ($inline ? 'inline' : 'attachment') . '; filename="' . str_replace('"', '', (string)$row['filename']) . '"');
    header('Cache-Control: public, max-age=3600');
    header('X-Content-Type-Options: nosniff');
    header("Content-Security-Policy: default-src 'none'; sandbox; base-uri 'none'");
    try {
        db()->prepare('UPDATE uploads SET downloads = downloads + 1 WHERE id = ?')->execute([$id]);
    } catch (Throwable $t) {
        // download counter is best-effort
    }
    echo $data;
    exit;
}

// ——— Share to an external file-hoster (mirror link) ———
if (isset($_GET['share'])) {
    csrf_verify_or_fail();
    if (!rate_limit_check('file-share', 5, (int)$cfg['rate_window_seconds'])) {
        header('Content-Type: application/json');
        echo json_encode(['ok' => false, 'url' => '', 'error' => 'Rate limit reached. Wait a few minutes.']);
        exit;
    }
    $id = (string)$_GET['share'];
    $site = (string)($_GET['site'] ?? '');
    $ok = false;
    $url = '';
    $err = 'Unknown site.';
    if (in_array($site, mirror_sites(), true)) {
        $err = '';
        $stmt = db()->prepare('SELECT id, filename, file_data FROM uploads WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if ($row === false) {
            $err = 'File not found.';
        } elseif ($row['file_data'] === null || $row['file_data'] === '') {
            // Mirror-only file (auto-mirrored on upload): no local bytes to push.
            $s = db()->prepare('SELECT url FROM file_shares WHERE file_id = ? AND site = ? LIMIT 1');
            $s->execute([$id, $site]);
            $existing = $s->fetchColumn();
            if ($existing) {
                $ok = true;
                $url = (string)$existing;
            } else {
                $err = 'Stored only on external mirrors — no local copy to push to ' . $site . '.';
            }
        } else {
            $url = mirror_upload($site, random_upload_name((string)$row['filename']), (string)$row['file_data'], $err);
            if ($url !== '') {
                $ok = true;
                try {
                    db()->prepare(
                        'INSERT INTO file_shares (file_id, site, url, created_at)
                         VALUES (?, ?, ?, UTC_TIMESTAMP())'
                    )->execute([$id, $site, $url]);
                } catch (Throwable $t) {
                    // the mirror link still works even if we can't log it
                }
                log_activity('file_shared', $id . ' → ' . $site . ' by ' . ($me['username'] ?? 'Anonymous'));
            }
        }
    }
    header('Content-Type: application/json');
    echo json_encode(['ok' => $ok, 'url' => $url, 'error' => $err]);
    exit;
}

// ——— Delete ———
if (isset($_GET['del'])) {
    csrf_verify_or_fail();
    $id = (string)$_GET['del'];
    $key = (string)($_GET['key'] ?? '');
    $stmt = db()->prepare('SELECT * FROM uploads WHERE id = ?');
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    if ($row === false) {
        friendly_error('File not found.', 404);
    }
    $owner = (int)($row['user_id'] ?? 0);
    $mine = $me !== null && $owner > 0 && $owner === (int)$me['id'];
    $byKey = $row['delete_key'] !== null && $row['delete_key'] !== '' && hash_equals((string)$row['delete_key'], $key);
    if (!$mine && !$byKey) {
        friendly_error('Not allowed to delete this file.', 403);
    }
    $path = $uploadsDir . $row['stored_name'];
    if (is_file($path)) {
        @unlink($path);
    }
    db()->prepare('DELETE FROM uploads WHERE id = ?')->execute([$id]);
    log_activity('file_deleted', $id);
    flash_set('success', 'File deleted.');
    redirect('files.php' . ($me !== null ? '?my=1' : ''));
}

// ——— Upload ———
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify_or_fail();
    if (!rate_limit_check('file-upload', upload_rate_limit(), (int)$cfg['rate_window_seconds'])) {
        $flash = ['type' => 'error', 'msg' => 'Rate limit reached for uploads from your IP. Try again in a few minutes.'];
    } elseif (!isset($_FILES['file']) || (int)$_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        $flash = ['type' => 'error', 'msg' => 'No file was received. If it is over the server limit, try a smaller file.'];
    } else {
        $up = $_FILES['file'];
        if ((int)$up['size'] < 1 || (int)$up['size'] > $maxBytes) {
            $flash = ['type' => 'error', 'msg' => 'File size must be between 1 byte and ' . $maxBytesMb . ' MB.'];
        } else {
            $ext = strtolower(pathinfo((string)$up['name'], PATHINFO_EXTENSION));
            // No type/name blocking: files are stored on external hosters only,
            // never on KevBin, so every file type is accepted.
            $data = @file_get_contents((string)$up['tmp_name']);
                if ($data === false || strlen($data) !== (int)$up['size']) {
                    $flash = ['type' => 'error', 'msg' => 'Could not read the uploaded file. Try again.'];
                } else {
                    $id = bin2hex(random_bytes(8));
                    $stored = bin2hex(random_bytes(16)) . ($ext !== '' ? '.' . $ext : '');
                    $mimeMap = upload_mime_map();
                    $mime = $mimeMap[$ext] ?? 'application/octet-stream';
                    if (function_exists('finfo_open')) {
                        $fi = finfo_open(FILEINFO_MIME_TYPE);
                        $real = $fi ? finfo_file($fi, (string)$up['tmp_name']) : false;
                        if ($fi) {
                            finfo_close($fi);
                        }
                        if ($real && preg_match('/^[a-z0-9\-\.\/\+]+$/i', $real)) {
                            $mime = $real;
                        }
                    }
                    $delKey = bin2hex(random_bytes(8));
                    $userId = $me !== null ? (int)$me['id'] : null;
                    // Files are auto-mirrored to external hosters and NEVER stored
                    // locally (InfinityFree's 5 GB storage cap) — only links go in the DB.
                    // The name is randomized on the hosters so URLs can't be guessed.
                    $mirrors = mirror_upload_all(random_upload_name(basename((string)$up['name'])), $data, $mime);
                    if ($mirrors['ok'] === []) {
                        $fl = [];
                        foreach ($mirrors['fail'] as $st => $msg) {
                            $fl[] = $st . ': ' . $msg;
                        }
                        $flash = ['type' => 'error', 'msg' => 'No external hoster accepted this file, so it was not stored on KevBin. ' . implode(' — ', array_slice($fl, 0, 3)) . '.'];
                    } else {
                        $primarySite = array_key_exists('catbox', $mirrors['ok']) ? 'catbox' : (string)array_key_first($mirrors['ok']);
                        $primaryUrl = $mirrors['ok'][$primarySite];
                        try {
                            db()->prepare(
                                'INSERT INTO uploads (id, user_id, filename, stored_name, mime, size, delete_key, file_data, mirror_url, created_at)
                                 VALUES (?, ?, ?, ?, ?, ?, ?, NULL, ?, UTC_TIMESTAMP())'
                            )->execute([$id, $userId, basename((string)$up['name']), $stored, $mime, strlen($data), $delKey, $primaryUrl]);
                            foreach ($mirrors['ok'] as $st => $u) {
                                db()->prepare(
                                    'INSERT INTO file_shares (file_id, site, url, created_at)
                                     VALUES (?, ?, ?, UTC_TIMESTAMP())'
                                )->execute([$id, $st, $u]);
                            }
                        } catch (Throwable $t) {
                            error_log('[files] ' . $t->getMessage());
                            $flash = ['type' => 'error', 'msg' => 'Could not record the file links in the database. Try again.'];
                        }
                        if ($flash === null) {
                            $newUpload = [
                                'id' => $id,
                                'name' => basename((string)$up['name']),
                                'size' => strlen($data),
                                'mime' => $mime,
                                'delKey' => $delKey,
                                'url' => (string)($cfg['base_url'] ?? '') . 'files.php?d=' . $id,
                                'mirrors' => $mirrors['ok'],
                            ];
                            log_activity('file_upload', $id . ' ' . basename((string)$up['name']) . ' → ' . implode(',', array_keys($mirrors['ok'])) . ' by ' . ($me['username'] ?? 'Anonymous'));
                        }
                    }
                }
        }
    }
}

// ——— My files ———
if ($me !== null && isset($_GET['my'])) {
    $stmt = db()->prepare('SELECT id, filename, mime, size, delete_key, downloads, created_at, file_data IS NOT NULL AS has_local FROM uploads WHERE user_id = ? ORDER BY created_at DESC LIMIT 100');
    $stmt->execute([(int)$me['id']]);
    $myFiles = $stmt->fetchAll();
    $myShares = [];
    if ($myFiles !== []) {
        $ids = array_column($myFiles, 'id');
        $in = implode(',', array_fill(0, count($ids), '?'));
        $stmt = db()->prepare("SELECT file_id, site, url FROM file_shares WHERE file_id IN ($in)");
        $stmt->execute($ids);
        foreach ($stmt->fetchAll() as $shr) {
            $myShares[$shr['file_id']][] = $shr;
        }
    }
}

$ssFlash = flash_get();

page_header('Files');
?>
<div class="container" style="max-width: 800px;">
    <h1 class="h4 mb-1 reveal in-view">📁 File Uploads</h1>
    <p class="text-secondary mb-3 reveal in-view">Upload a file and get a shareable, direct link. Files are auto-mirrored to external hosters and never stored on KevBin, so our storage stays free. Size cap: <strong><?= e((string)$maxBytesMb) ?> MB</strong>.</p>

    <?php if ($flash !== null): ?>
        <div class="alert alert-<?= $flash['type'] === 'error' ? 'danger' : 'success' ?> reveal in-view"><?= e($flash['msg']) ?></div>
    <?php endif; ?>
    <?php if ($ssFlash !== null): ?>
        <div class="alert alert-<?= $ssFlash['type'] === 'error' ? 'danger' : 'success' ?> reveal in-view"><?= e($ssFlash['msg']) ?></div>
    <?php endif; ?>

    <div class="card reveal in-view"><div class="card-body">
        <form method="post" action="files.php" enctype="multipart/form-data" class="mb-0">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <div class="mb-3">
                <label class="form-label small text-secondary" for="file">Choose a file</label>
                <input class="form-control" type="file" name="file" id="file" required>
            </div>
            <button class="btn btn-primary" type="submit">Upload ⬆</button>
            <span class="text-secondary small ms-2">All file types allowed — nothing is blocked.</span>
        </form>
    </div></div>

    <?php if ($newUpload !== null): ?>
        <div class="alert alert-success mt-4 reveal in-view">
            <h2 class="h5 mb-2">File uploaded ✔</h2>
            <p class="mb-2"><strong><?= e($newUpload['name']) ?></strong> · <?= number_format($newUpload['size']) ?> bytes · <?= e($newUpload['mime']) ?></p>
            <p class="mb-1"><strong>Direct link:</strong><br><code><?= e($newUpload['url']) ?></code></p>
            <p class="mb-0">🔑 <strong>Delete key (save it to remove this file later):</strong> <code><?= e($newUpload['delKey']) ?></code><br>
            <a href="<?= e(url('files.php?del=' . $newUpload['id'] . '&key=' . $newUpload['delKey'])) ?>" rel="noopener">Delete this file now</a> ·
            <a href="<?= e(url('legal.php?type=abuse&target=' . urlencode('files.php?d=' . $newUpload['id']))) ?>" rel="noopener">Report abuse</a></p>

            <hr style="border-color:var(--line);">
            <p class="mb-2"><strong>🌐 Mirrored to external hosters</strong> <small class="text-secondary">— your file is NOT stored on KevBin, only these links</small></p>
            <?php if ($newUpload['mirrors'] !== []): ?>
                <ul class="list-group mb-0">
                    <?php foreach ($newUpload['mirrors'] as $st => $u): ?>
                        <li class="list-group-item d-flex align-items-center justify-content-between gap-2">
                            <span class="text-truncate"><strong><?= e(mirror_labels()[$st] ?? $st) ?></strong><br><a href="<?= e($u) ?>" target="_blank" rel="noopener" class="small text-secondary"><?= e($u) ?></a></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <p class="text-secondary small mb-0">No hoster accepted the file.</p>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <?php if ($me !== null && isset($_GET['my'])): ?>
        <h2 class="h6 mt-4 mb-2 reveal in-view" style="border-bottom:1px solid var(--line);padding-bottom:.5rem;">🗂 Your files (<?= count($myFiles) ?>)</h2>
        <?php if (count($myFiles) === 0): ?>
            <div class="alert alert-secondary reveal in-view">You have no uploaded files yet.</div>
        <?php else: ?>
            <div class="list-group reveal in-view">
                <?php foreach ($myFiles as $mf): ?>
                    <div class="list-group-item">
                        <div class="d-flex align-items-center justify-content-between gap-2">
                            <div class="text-truncate">
                                <a href="<?= e(url('files.php?d=' . $mf['id'])) ?>" target="_blank" rel="noopener"><strong><?= e($mf['filename']) ?></strong></a>
                                <span class="text-secondary small ms-2"><?= number_format((int)$mf['size']) ?>B · <?= (int)$mf['downloads'] ?> dl · <?= e(substr((string)$mf['created_at'], 0, 16)) ?></span>
                            </div>
                            <div class="d-flex align-items-center gap-2 flex-shrink-0">
                                <button type="button" class="btn btn-sm btn-outline-light" onclick="var p=document.getElementById('share-panel-<?= e($mf['id']) ?>');p.classList.toggle('d-none');this.textContent=(p.classList.contains('d-none')?'🔗 Mirror':'✕ Close');">🔗 Mirror</button>
                                <a class="btn btn-sm btn-outline-danger" href="<?= e(url('files.php?del=' . $mf['id'] . '&csrf=' . csrf_token())) ?>">Delete</a>
                            </div>
                        </div>
                        <div class="d-none mt-2" id="share-panel-<?= e($mf['id']) ?>">
                            <?php $shares = $myShares[$mf['id']] ?? []; ?>
                            <?php if ($shares !== []): ?>
                                <div class="d-flex flex-wrap gap-2 mb-2">
                                    <?php foreach ($shares as $shr): ?>
                                        <a class="btn btn-sm btn-outline-light" href="<?= e($shr['url']) ?>" target="_blank" rel="noopener" title="<?= e($shr['url']) ?>"><?= e(mirror_labels()[$shr['site']] ?? $shr['site']) ?></a>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                            <?php if ((int)$mf['has_local'] === 1): ?>
                                <div class="d-flex flex-wrap gap-2 mb-2">
                                    <?php foreach (mirror_labels() as $site => $label): ?>
                                        <button type="button" class="btn btn-sm btn-outline-light" onclick="kbShare('<?= e($mf['id']) ?>','<?= e($site) ?>',this)"><?= e($label) ?></button>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <p class="text-secondary small mb-0">Stored only on external mirrors — use the links above.</p>
                            <?php endif; ?>
                            <ul class="list-group" id="share-list-<?= e($mf['id']) ?>"></ul>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    <?php elseif ($me !== null && !isset($_GET['my'])): ?>
        <p class="mt-3 reveal in-view"><a class="btn btn-outline-light btn-sm" href="files.php?my=1">🗂 View my files</a></p>
    <?php endif; ?>

    <div class="card mt-4 reveal"><div class="card-body">
        <h2 class="h6 mb-2">📌 Notes</h2>
        <ul class="text-secondary small mb-0">
            <li>Every file type is accepted — files live on external hosters only, never on KevBin.</li>
            <li>Filenames are randomized on upload (e.g. <code>static1.png</code> → <code>jasdomn129.png</code>), so hosted URLs can't be guessed.</li>
            <li>Files are NEVER stored on KevBin — on upload they're auto-mirrored to Catbox, Litterbox, Tmpfiles and Uguu, and only the links are kept in our database.</li>
            <li><code>files.php?d=…</code> redirects to the first mirror link. Legacy files uploaded before mirroring are still served from the database.</li>
            <li>Deleting a file only removes the links here — external copies may persist per that hoster's terms.</li>
            <li>Anonymous uploads can only be deleted with the delete key shown after uploading.</li>
            <li>Logged-in uploads are listed under “My files” and can be deleted there.</li>
        </ul>
    </div></div>
</div>
<script>
const KB_CSRF = '<?= e(csrf_token()) ?>';
function kbShare(id, site, btn) {
    const list = document.getElementById('share-list-' + id);
    if (!list || btn.disabled) return;
    btn.disabled = true;
    const old = btn.textContent;
    btn.textContent = '…';
    fetch('files.php?share=' + encodeURIComponent(id) + '&site=' + encodeURIComponent(site) + '&csrf=' + encodeURIComponent(KB_CSRF), { method: 'POST', credentials: 'same-origin' })
        .then(function (r) { return r.json(); })
        .then(function (j) {
            if (j.ok && j.url) {
                const esc = String(j.url).replace(/&/g, '&amp;').replace(/</g, '&lt;');
                const li = document.createElement('li');
                li.className = 'list-group-item d-flex align-items-center justify-content-between gap-2';
                li.innerHTML = '<span class="text-truncate"><a href="' + esc + '" target="_blank" rel="noopener"><strong>Mirror link</strong><br><small class="text-secondary">' + esc + '</small></a></span>'
                    + '<button type="button" class="btn btn-sm btn-outline-secondary" title="Remove" onclick="this.parentNode.remove()">✕</button>';
                list.appendChild(li);
                btn.textContent = '✓ ' + old.split('(')[0].trim();
            } else {
                btn.textContent = '✗';
                btn.classList.remove('btn-outline-light');
                btn.classList.add('btn-outline-danger');
                btn.title = 'Error: ' + (j.error || 'failed');
            }
            btn.disabled = false;
        })
        .catch(function () { btn.textContent = '✗'; btn.classList.add('btn-outline-danger'); btn.classList.remove('btn-outline-light'); btn.disabled = false; });
}
</script>
<?php page_footer(); ?>