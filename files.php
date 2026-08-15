<?php
require_once __DIR__ . '/functions.php';

start_session();
$cfg = $GLOBALS['CFG'];

// Storage dir (relative to this script's directory = htdocs).
$uploadsDir = __DIR__ . '/' . rtrim((string)($cfg['uploads_dir'] ?? 'uploads/'), '/') . '/';
$maxBytes = (int)($cfg['upload_max_mb'] ?? 20) * 1024 * 1024;
// Uploaded bytes are stored as a BLOB in the DB, and the whole INSERT has to fit
// inside the host's max_allowed_packet (10 MB on this host), so cap at 9 MB.
$maxBytes = min($maxBytes, 9 * 1024 * 1024);
$maxBytesMb = (int)ceil($maxBytes / 1048576);
$extList = array_filter(array_map('trim', explode(',', strtolower((string)($cfg['upload_exts'] ?? 'png,jpg,jpeg,gif,webp,mp3,ogg,wav,mp4,webm,pdf,txt,md,json,csv,log,xml,yaml,zip,gz,doc,docx,xls,xlsx,ppt,pptx')))));

function allowed_file_ext(string $filename, array $allowed): ?string
{
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    return in_array($ext, $allowed, true) ? $ext : null;
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
    return ['catbox', 'litterbox', 'tmpfiles'];
}

function mirror_labels(): array
{
    return [
        'catbox'    => 'Catbox',
        'litterbox' => 'Litterbox (72h)',
        'tmpfiles'  => 'Tmpfiles.org',
    ];
}

// Pushes the file's bytes to an external file-hoster and returns the share URL.
// Returns '' with $err set on failure.
function mirror_upload(string $site, string $filename, string $data, string &$err = ''): string
{
    $endpoints = [
        'catbox'    => ['url' => 'https://catbox.moe/user/api.php', 'method' => 'POST', 'fields' => ['reqtype' => 'fileupload'], 'file' => 'fileToUpload', 'ret' => 'raw'],
        'litterbox' => ['url' => 'https://litterbox.catbox.moe/resources/internals/api.php', 'method' => 'POST', 'fields' => ['reqtype' => 'fileupload', 'time' => '72h'], 'file' => 'fileToUpload', 'ret' => 'raw'],
        'tmpfiles'  => ['url' => 'https://tmpfiles.org/api/v1/upload', 'method' => 'POST', 'fields' => [], 'file' => 'file', 'ret' => 'tmpfiles'],
    ];
    $ep = $endpoints[$site] ?? null;
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
    $body = trim((string)$body);
    $url = '';
    if ($ep['ret'] === 'raw') {
        if (preg_match('#^https?://\S+$#i', $body)) {
            $url = $body;
        }
    } elseif ($ep['ret'] === 'tmpfiles') {
        $j = json_decode($body, true);
        if (isset($j['data']['url'])) {
            $url = preg_replace('#^https?://tmpfiles\.org/#', 'https://tmpfiles.org/dl/', (string)$j['data']['url']);
        }
    }
    if ($url === '') {
        $err = $code . ($body !== '' ? ' ' . substr($body, 0, 160) : '');
    }
    return $url;
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
    $path = $uploadsDir . $row['stored_name'];
    if ($data === '') {
        // Legacy row still holding the bytes on disk — read it and migrate it
        // into the DB so nothing is ever served back out of the public folder.
        if (is_file($path)) {
            $data = @file_get_contents($path);
        }
        if ($data === false || $data === '') {
            friendly_error('File is missing.', 404);
        }
        try {
            db()->prepare('UPDATE uploads SET file_data = ?, size = ? WHERE id = ?')
                ->execute([$data, strlen($data), $id]);
            @unlink($path);
        } catch (Throwable $t) {
            // keep the disk copy if the DB update fails
        }
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
            $err = 'File has no content.';
        } else {
            $url = mirror_upload($site, (string)$row['filename'], (string)$row['file_data'], $err);
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
    if (!captcha_ok((string)($_POST['captcha'] ?? ''))) {
        $flash = ['type' => 'error', 'msg' => 'Wrong captcha answer. Try again.'];
    } elseif (!rate_limit_check('file-upload', (int)($cfg['file_upload_rate_limit'] ?? 10), (int)$cfg['rate_window_seconds'])) {
        $flash = ['type' => 'error', 'msg' => 'Rate limit reached for uploads from your IP. Try again in a few minutes.'];
    } elseif (!isset($_FILES['file']) || (int)$_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        $flash = ['type' => 'error', 'msg' => 'No file was received. If it is over the server limit, try a smaller file.'];
    } else {
        $up = $_FILES['file'];
        if ((int)$up['size'] < 1 || (int)$up['size'] > $maxBytes) {
            $flash = ['type' => 'error', 'msg' => 'File size must be between 1 byte and ' . $maxBytesMb . ' MB.'];
        } else {
            $ext = allowed_file_ext((string)$up['name'], $extList);
            if ($ext === null) {
                $flash = ['type' => 'error', 'msg' => 'File type not allowed. Allowed: ' . implode(', ', $extList) . '.'];
            } else {
                $data = @file_get_contents((string)$up['tmp_name']);
                if ($data === false || strlen($data) !== (int)$up['size']) {
                    $flash = ['type' => 'error', 'msg' => 'Could not read the uploaded file. Try again.'];
                } else {
                    $id = bin2hex(random_bytes(8));
                    $stored = bin2hex(random_bytes(16)) . '.' . $ext;
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
                    try {
                        db()->prepare(
                            'INSERT INTO uploads (id, user_id, filename, stored_name, mime, size, delete_key, file_data, created_at)
                             VALUES (?, ?, ?, ?, ?, ?, ?, ?, UTC_TIMESTAMP())'
                        )->execute([$id, $userId, basename((string)$up['name']), $stored, $mime, strlen($data), $delKey, $data]);
                    } catch (Throwable $t) {
                        error_log('[files] ' . $t->getMessage());
                        $flash = ['type' => 'error', 'msg' => 'Could not store the file in the database. If it is close to the size cap, try a smaller file.'];
                    }
                    if ($flash === null) {
                        $newUpload = [
                            'id' => $id,
                            'name' => basename((string)$up['name']),
                            'size' => strlen($data),
                            'mime' => $mime,
                            'delKey' => $delKey,
                            'url' => (string)($cfg['base_url'] ?? '') . 'files.php?d=' . $id,
                        ];
                        log_activity('file_upload', $id . ' ' . basename((string)$up['name']) . ' by ' . ($me['username'] ?? 'Anonymous'));
                    }
                }
            }
        }
    }
}

// ——— My files ———
if ($me !== null && isset($_GET['my'])) {
    $stmt = db()->prepare('SELECT id, filename, mime, size, delete_key, downloads, created_at FROM uploads WHERE user_id = ? ORDER BY created_at DESC LIMIT 100');
    $stmt->execute([(int)$me['id']]);
    $myFiles = $stmt->fetchAll();
}

$ssFlash = flash_get();

captcha_issue(true);
page_header('Files');
?>
<div class="container" style="max-width: 800px;">
    <h1 class="h4 mb-1 reveal in-view">📁 File Uploads</h1>
    <p class="text-secondary mb-3 reveal in-view">Upload a file and get a shareable, direct link. Files are stored in the database (never in the public folder), served with the right content type, and keep working exactly as uploaded. Size cap: <strong><?= e((string)$maxBytesMb) ?> MB</strong>.</p>

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
            <div class="d-flex align-items-center gap-2 mb-3">
                <label class="form-label small text-secondary mb-0 me-1">Captcha</label>
                <img src="captcha.php?v=<?= time() ?>" alt="captcha" width="160" height="56" id="cap-img"
                    style="border-radius:6px;border:1px solid var(--line);">
                <button type="button" class="btn btn-sm btn-outline-light" onclick="var i=document.getElementById('cap-img');i.src='captcha.php?rot=1&v='+Date.now();"
                    title="New captcha">↻</button>
                <input class="form-control" name="captcha" maxlength="6" required autocomplete="off"
                    style="width:130px" aria-label="Captcha answer">
            </div>
            <button class="btn btn-primary" type="submit">Upload ⬆</button>
            <span class="text-secondary small ms-2">Allowed: <?= e(implode(', ', $extList)) ?></span>
        </form>
    </div></div>

    <?php if ($newUpload !== null): ?>
        <div class="alert alert-success mt-4 reveal in-view">
            <h2 class="h5 mb-2">File uploaded ✔</h2>
            <p class="mb-2"><strong><?= e($newUpload['name']) ?></strong> · <?= number_format($newUpload['size']) ?> bytes · <?= e($newUpload['mime']) ?></p>
            <p class="mb-1"><strong>Direct link:</strong><br><code><?= e($newUpload['url']) ?></code></p>
            <p class="mb-0">🔑 <strong>Delete key (save it to remove this file later):</strong> <code><?= e($newUpload['delKey']) ?></code><br>
            <a href="<?= e(url('files.php?del=' . $newUpload['id'] . '&key=' . $newUpload['delKey'])) ?>" rel="noopener">Delete this file now</a></p>

            <hr style="border-color:var(--line);">
            <p class="mb-2"><strong>🌐 Share to a file-hoster (mirror link)</strong> <small class="text-secondary">— push this file to an external site and get a second link</small></p>
            <div class="d-flex flex-wrap gap-2 mb-2">
                <?php foreach (mirror_labels() as $site => $label): ?>
                    <button type="button" class="btn btn-sm btn-outline-light" onclick="kbShare('<?= e($newUpload['id']) ?>','<?= e($site) ?>',this)"><?= e($label) ?></button>
                <?php endforeach; ?>
            </div>
            <ul class="list-group" id="share-list-<?= e($newUpload['id']) ?>"></ul>
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
                            <div class="d-flex flex-wrap gap-2 mb-2">
                                <?php foreach (mirror_labels() as $site => $label): ?>
                                    <button type="button" class="btn btn-sm btn-outline-light" onclick="kbShare('<?= e($mf['id']) ?>','<?= e($site) ?>',this)"><?= e($label) ?></button>
                                <?php endforeach; ?>
                            </div>
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
            <li>Only allowed file types are accepted; executable files (.php, .sh, macros) are blocked.</li>
            <li>Files are stored in the database and served only through <code>files.php?d=…</code> — uploaded content is never executed or served as a page.</li>
            <li>Anonymous uploads can only be deleted with the delete key shown after uploading.</li>
            <li>Logged-in uploads are listed under “My files” and can be deleted there.</li>
            <li>Mirroring sends your file to an external hoster — respect their terms, and note some have size/expiry limits.</li>
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