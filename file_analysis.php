<?php
// KevBin File Analysis — free for everyone. Upload a file and get hashes, MIME,
// size, entropy, text/binary detection, magic-number signature, extracted
// details (image dimensions, archive contents, PDF metadata, strings) and a
// heuristic malware scan. The file is analysed in memory and NEVER stored —
// it's deleted the moment the analysis finishes.
require_once __DIR__ . '/functions.php';

start_session();
$cfg = $GLOBALS['CFG'];

$maxMb = (int)($cfg['file_analysis_max_mb'] ?? 200);
if ($maxMb < 1) {
    $maxMb = 200;
}
$maxBytes = $maxMb * 1024 * 1024;
// Try to lift the host's PHP upload limits for this script. If the host ignores
// ini_set (common on shared hosting) the user just sees a friendly error below.
@ini_set('upload_max_filesize', $maxMb . 'M');
@ini_set('post_max_size', ($maxMb + 20) . 'M');

$result = null;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify_or_fail();
    if (!rate_limit_check('file_analysis', 12, 300)) {
        $error = 'Too many analyses from your IP. Try again in 5 minutes.';
    } elseif (!isset($_FILES['file'])) {
        $error = 'Choose a file to analyse.';
    } elseif (($_FILES['file']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_INI_SIZE) {
        $error = 'The host rejected the upload — your PHP config caps upload_max_filesize/post_max_size below the ' . $maxMb . ' MB limit. Upload a smaller file.';
    } elseif (($_FILES['file']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_FORM_SIZE) {
        $error = 'File exceeds the ' . $maxMb . ' MB limit.';
    } elseif (($_FILES['file']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        $error = 'Choose a file to analyse.';
    } elseif ($_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        $error = 'Upload failed (error ' . (int)$_FILES['file']['error'] . ').';
    } elseif ((int)$_FILES['file']['size'] > $maxBytes) {
        $error = 'File too big. The analysis limit is ' . $maxMb . ' MB.';
    } else {
        $data = @file_get_contents($_FILES['file']['tmp_name']);
        if ($data === false || $data === '') {
            $error = 'Could not read the uploaded file.';
        } else {
            $result = analyze_file(basename((string)($_FILES['file']['name'] ?? 'file')), $data, (string)$_FILES['file']['tmp_name']);
        }
    }
}

$severityBadge = [
    'Low'  => '<span class="badge bg-success">LOW</span>',
    'Medium' => '<span class="badge bg-warning text-dark">MEDIUM</span>',
    'High' => '<span class="badge bg-danger">HIGH</span>',
];

page_header('File Analysis');
?>
<div class="container" style="max-width: 1050px;">
    <div class="text-center mb-4">
        <h1 class="h3 mb-2">File Analysis</h1>
        <p class="text-secondary mb-0">Upload a file (up to <?= (int)$maxMb ?> MB) and we compute hashes, MIME, entropy, text/binary status, magic signature, extract metadata (images, archives, PDFs, strings) and run a <strong>heuristic malware scan</strong>. The file is read in memory and <strong>deleted right after</strong> — never stored. Free for everyone.</p>
    </div>

    <?php if ($error !== ''): ?>
        <div class="alert alert-danger"><?= e($error) ?></div>
    <?php endif; ?>

    <div class="card mb-4">
        <div class="card-body">
            <form method="post" enctype="multipart/form-data" action="file_analysis.php" onsubmit="var b=this.querySelector('button[type=submit]');if(b){b.disabled=true;b.textContent='Analysing…';}">
                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                <div class="mb-3">
                    <input type="file" name="file" id="fa-file" class="form-control" required>
                    <div class="form-text">Up to <?= (int)$maxMb ?> MB. The file is wiped from the server the moment analysis finishes.</div>
                </div>
                <button type="submit" class="btn btn-primary">Analyse file</button>
            </form>
        </div>
    </div>

    <?php if ($result !== null): ?>
    <div class="card mb-4">
        <div class="card-header fw-bold"><?= e($result['name']) ?></div>
        <div class="card-body">
            <table class="table table-sm align-middle" style="font-size:.9rem;">
                <tbody>
                    <tr><th style="width:220px;">Size</th><td><?= e(format_bytes($result['size'])) ?></td></tr>
                    <tr><th>MIME type</th><td><code><?= e($result['mime']) ?></code> <span class="badge bg-<?= $result['is_text'] ? 'success' : 'warning' ?> text-dark ms-1"><?= $result['is_text'] ? 'TEXT' : 'BINARY' ?></span></td></tr>
                    <?php if (!empty($result['signature'])): ?>
                    <tr><th>Detected signature</th><td><?= e($result['signature']) ?></td></tr>
                    <?php endif; ?>
                    <tr><th>Entropy</th><td><?= number_format($result['entropy'], 3) ?> bits/byte <?= $result['entropy'] > 7.5 ? '<span class="text-warning ms-1" title="Near-maximum entropy usually means compressed or encrypted data.">(compressed / encrypted)</span>' : '' ?></td></tr>
                    <?php if ($result['is_text']): ?>
                    <tr><th>Lines</th><td><?= number_format($result['lines']) ?></td></tr>
                    <tr><th>Words</th><td><?= number_format($result['words']) ?></td></tr>
                    <tr><th>Characters</th><td><?= number_format($result['chars']) ?></td></tr>
                    <?php endif; ?>
                    <tr>
                        <th>Hashes</th>
                        <td>
                            <div>SHA-256 <code class="ms-1" style="word-break:break-all;"><?= e($result['sha256']) ?></code></div>
                            <div class="mt-1">SHA-1 <code class="ms-1" style="word-break:break-all;"><?= e($result['sha1']) ?></code></div>
                            <div class="mt-1">MD5 <code class="ms-1" style="word-break:break-all;"><?= e($result['md5']) ?></code></div>
                        </td>
                    </tr>
                    <tr><th>First 32 bytes</th><td><pre class="bg-black p-2 rounded mb-0" style="font-size:.75rem;overflow-x:auto;"><?= e($result['hexdump']) ?></pre></td></tr>
                </tbody>
            </table>
        </div>
    </div>

    <?php if (count($result['malware']['findings']) > 0 || $result['malware']['score'] > 0): ?>
    <div class="card mb-4">
        <div class="card-header fw-bold d-flex align-items-center gap-2">Malware scan <?= $severityBadge[$result['malware']['severity']] ?? '' ?> <span class="text-secondary fw-normal small">(heuristic — not a real antivirus)</span></div>
        <div class="card-body">
            <p class="small text-secondary mb-2">Heuristic score: <strong><?= (int)$result['malware']['score'] ?></strong> / 10. The analyzer flags dangerous code patterns (web shells, packed executables, downloader commands, Office macros, large encoded blobs). It is <strong>not</strong> a substitute for a real AV — false positives and misses are possible.</p>
            <ul class="mb-0">
                <?php foreach ($result['malware']['findings'] as $f): ?>
                    <li class="<?= $f['level'] === 'high' ? 'text-danger' : ($f['level'] === 'med' ? 'text-warning' : '') ?>"><?= e($f['msg']) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>
    <?php endif; ?>

    <?php if (count($result['extract']) > 0): ?>
    <div class="card mb-4">
        <div class="card-header fw-bold">Extracted details</div>
        <div class="card-body">
            <?php foreach ($result['extract'] as $section): ?>
                <h6 class="mb-2"><?= e($section['title']) ?></h6>
                <?php if (isset($section['table']) && count($section['table']) > 0): ?>
                    <div class="table-responsive"><table class="table table-sm" style="font-size:.85rem;">
                        <thead><tr><?php foreach ($section['cols'] as $c): ?><th><?= e($c) ?></th><?php endforeach; ?></tr></thead>
                        <tbody>
                        <?php foreach ($section['table'] as $row): ?>
                            <tr><?php foreach ($row as $cell): ?><td class="text-break"><?= e((string)$cell) ?></td><?php endforeach; ?></tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table></div>
                <?php elseif (isset($section['lines'])): ?>
                    <ul class="small">
                        <?php foreach ($section['lines'] as $ln): ?>
                            <li><?= e((string)$ln) ?></li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <p class="text-secondary small"><?= e((string)$section['empty']) ?></p>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <?php endif; ?>

    <p class="text-secondary small mt-4">Everything runs server-side with built-in PHP only — no file is uploaded to any third party, nothing is stored, and the upload is wiped immediately after analysis.</p>
</div>
<?php
page_footer();

function analyze_file(string $name, string $data, string $tmpPath): array
{
    $size = strlen($data);

    $mime = 'application/octet-stream';
    if (class_exists('finfo')) {
        $fi = @new finfo(FILEINFO_MIME_TYPE);
        $detected = $fi ? $fi->buffer($data) : false;
        if (is_string($detected) && $detected !== '') {
            $mime = $detected;
        }
    }

    $freq = array_fill(0, 256, 0);
    for ($i = 0; $i < $size; $i++) {
        $freq[ord($data[$i])]++;
    }
    $entropy = 0.0;
    foreach ($freq as $c) {
        if ($c > 0) {
            $p = $c / $size;
            $entropy -= $p * log($p, 2);
        }
    }

    $printable = 0;
    for ($i = 0; $i < $size; $i++) {
        $o = ord($data[$i]);
        if ($o === 9 || $o === 10 || $o === 13 || ($o >= 32 && $o <= 126) || $o >= 128) {
            $printable++;
        }
    }
    $isText = $size > 0 && ($printable / $size) > 0.95;

    $lines = $size === 0 ? 0 : substr_count($data, "\n") + 1;
    $words = $isText ? count(preg_split('/\s+/', trim($data))) : 0;

    $sig = detect_signature($data);

    $extract = extract_details($name, $data, $tmpPath, $mime);

    // Triage-style PE (Windows executable) analysis.
    $pe = is_pe($data) ? pe_analysis($data) : null;
    if ($pe !== null) {
        $extract['sections'] = array_merge($extract['sections'], pe_report_sections($pe));
    }

    $head = substr($data, 0, 32);
    $hex = '';
    $ascii = '';
    for ($i = 0; $i < strlen($head); $i++) {
        if ($i > 0 && $i % 16 === 0) {
            $hex .= "\n";
        }
        $o = ord($head[$i]);
        $hex .= sprintf('%02X ', $o);
        $ascii .= ($o >= 32 && $o <= 126) ? chr($o) : '.';
    }
    $hexdump = $hex . '    ' . $ascii;

    $hasMacro = !empty($extract['has_macro']);
    $malware = malware_scan($name, $data, $entropy, $hasMacro);

    // PE-specific malware indicators (Triage-style).
    if ($pe !== null && !isset($pe['error'])) {
        $peInd = 0;
        $packNames = ['upx0','upx1','upx!','.themida','.vmp0','.vmp1','.aspack','.mpress','.nsp0','.nsp1'];
        $suspDlls = ['ntdll','wininet','winhttp','urlmon','ws2_32','crypt32','advapi32','user32','kernel32'];
        foreach (($pe['sections_table'] ?? []) as $sec) {
            $sn = strtolower($sec['name']);
            if (in_array($sn, $packNames, true)) {
                $malware['findings'][] = ['msg' => 'PE packer section: ' . $sec['name'], 'level' => 'med'];
                $peInd += 2;
            }
            if ($sec['entropy'] > 7.5) {
                $malware['findings'][] = ['msg' => 'Section "' . $sec['name'] . '" high entropy (' . number_format($sec['entropy'], 2) . ') — possible packing', 'level' => 'med'];
                $peInd += 2;
            }
            if (($sec['flags'] & 0x60000000) === 0x60000000) {
                $malware['findings'][] = ['msg' => 'Section "' . $sec['name'] . '" has EXEC+WRITE — suspicious', 'level' => 'high'];
                $peInd += 3;
            }
        }
        if (isset($pe['timestamp']) && $pe['timestamp'] > time() + 86400) {
            $malware['findings'][] = ['msg' => 'Future compile timestamp (' . ($pe['timestamp_str'] ?? '') . ') — common in packed binaries', 'level' => 'med'];
            $peInd++;
        }
        foreach (($pe['imports'] ?? []) as $imp) {
            $dllLower = strtolower(pathinfo($imp['dll'], PATHINFO_FILENAME));
            if (in_array($dllLower, $suspDlls, true)) {
                $malware['findings'][] = ['msg' => 'Suspicious DLL: ' . $imp['dll'] . ' (' . count($imp['funcs']) . ' imports)', 'level' => 'med'];
                $peInd++;
                break;
            }
        }
        $malware['score'] = min(10, $malware['score'] + $peInd);
        $malware['severity'] = $malware['score'] >= 6 ? 'High' : ($malware['score'] >= 3 ? 'Medium' : 'Low');
    }

    return [
        'name' => $name,
        'size' => $size,
        'mime' => $mime,
        'entropy' => $entropy,
        'is_text' => $isText,
        'lines' => $lines,
        'words' => $words,
        'signature' => $sig,
        'sha256' => hash('sha256', $data),
        'sha1' => hash('sha1', $data),
        'md5' => hash('md5', $data),
        'hexdump' => $hexdump,
        'extract' => $extract['sections'],
        'malware' => $malware,
    ];
}

// ---------------------------------------------------------------- extract ---
function extract_details(string $name, string $data, string $tmpPath, string $mime): array
{
    $sections = [];
    $hasMacro = false;
    $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));

    // Image dimensions.
    if (in_array($ext, ['png', 'jpg', 'jpeg', 'gif', 'webp', 'bmp', 'ico'], true) || strpos($mime, 'image/') === 0) {
        $dims = @getimagesizefromstring($data);
        if (is_array($dims)) {
            $typeNames = [1 => 'GIF', 2 => 'JPEG', 3 => 'PNG', 4 => 'SWF', 6 => 'BMP', 15 => 'WBMP', 16 => 'XBM', 18 => 'WEBP', 19 => 'AVIF'];
            $sections[] = [
                'title' => 'Image',
                'table' => [[$dims[0] . ' × ' . $dims[1], ($typeNames[$dims[2]] ?? 'type ' . $dims[2]), ($dims['channels'] ?? '') . ' channel(s)', ($dims['bits'] ?? '') . ' bit']],
                'cols' => ['Dimensions', 'Format', 'Channels', 'Bit depth'],
            ];
        }
    }

    // ZIP archives (incl. docx/xlsx/pptx/odt) — list contents, look for macros.
    if (class_exists('ZipArchive') && $tmpPath !== '' && is_file($tmpPath)) {
        $za = new ZipArchive();
        if (@$za->open($tmpPath) === true) {
            $rows = [];
            $count = $za->numFiles;
            for ($i = 0; $i < min($count, 300); $i++) {
                $st = $za->statIndex($i);
                $n = (string)($st['name'] ?? '');
                if (preg_match('/vbaProject\.bin$/i', $n) || stripos($n, 'macro') !== false || preg_match('/word\/vba/i', $n)) {
                    $hasMacro = true;
                }
                $rows[] = [$n, $st['size'] ?? 0, $st['comp_size'] ?? 0, $st['crc'] ?? 0];
            }
            $kind = 'ZIP archive';
            if ($count > 0) {
                $first = (string)($za->getNameIndex(0) ?? '');
                if (preg_match('#^(word/|xl/|ppt/|\[Content_Types\]\.xml)#i', $first)) {
                    $kind = 'Office document (Word/Excel/PowerPoint)';
                } elseif (stripos($first, 'META-INF/') === 0) {
                    $kind = 'OpenDocument';
                }
            }
            $sections[] = [
                'title' => $kind . ' — ' . $count . ' entr' . ($count === 1 ? 'y' : 'ies') . ($hasMacro ? ' <span class="badge bg-danger ms-1">CONTAINS MACRO</span>' : ''),
                'table' => $rows,
                'cols' => ['Name', 'Size', 'Compressed', 'CRC32'],
            ];
        }
    }

    // TAR archives.
    if (substr($data, 257, 5) === 'ustar') {
        $rows = [];
        $off = 0;
        while ($off + 512 <= strlen($data)) {
            $block = substr($data, $off, 512);
            $off += 512;
            if ($block === str_repeat("\0", 512)) {
                break;
            }
            $fn = rtrim(substr($block, 0, 100), "\0");
            if ($fn === '') {
                break;
            }
            $oct = rtrim(substr($block, 124, 12), "\0 ");
            $fSize = octdec($oct !== '' ? $oct : '0');
            $type = substr($block, 156, 1);
            $rows[] = [$fn, $fSize, ($type === '5' ? 'dir' : ($type === '0' || $type === '' ? 'file' : 'type ' . $type))];
            $off += (int)(($fSize + 511) / 512) * 512;
        }
        $sections[] = ['title' => 'TAR archive — ' . count($rows) . ' entries', 'table' => $rows, 'cols' => ['Name', 'Size', 'Type']];
    }

    // GZIP header.
    if (substr($data, 0, 2) === "\x1F\x8B") {
        $flags = ord($data[3]);
        $mtime = unpack('V', substr($data, 4, 4))[1];
        $os = ord($data[9]);
        $fn = '';
        if ($flags & 0x08) {
            $end = strpos($data, "\0", 10);
            if ($end !== false) {
                $fn = substr($data, 10, $end - 10);
            }
        }
        $isize = strlen($data) >= 4 ? unpack('V', substr($data, -4))[1] : 0;
        $osNames = [0 => 'FAT', 3 => 'Unix', 7 => 'Mac', 10 => 'NTFS', 13 => 'Atari', 255 => 'unknown'];
        $sections[] = [
            'title' => 'GZIP header',
            'lines' => [
                'Compressed: ' . format_bytes(strlen($data)) . ' · Original size: ' . ($isize > 0 ? format_bytes($isize) : '>4 GB or unknown') . ($isize > 0 ? ' (≈' . round($isize / max(1, strlen($data)), 2) . ':1)' : ''),
                'Header timestamp: ' . ($mtime > 0 ? gmdate('Y-m-d H:i:s', $mtime) . ' UTC' : 'none'),
                'Filename stored in header: ' . ($fn !== '' ? $fn : 'none'),
                'Operating system: ' . ($osNames[$os] ?? ('unknown (' . $os . ')')),
            ],
        ];
    }

    // PDF metadata.
    if (substr($data, 0, 5) === '%PDF-') {
        $head = substr($data, 0, 262144);
        $fields = [];
        foreach (['Title', 'Author', 'Creator', 'Producer', 'Subject', 'Keywords'] as $f) {
            if (preg_match('/\/' . $f . '\s*\(([^)]{1,200})\)/', $head, $m)) {
                $fields[] = [$f, $m[1]];
            }
        }
        $pageCount = max(1, substr_count($head, '/Type /Page') - substr_count($head, '/Type /Pages'));
        $fields[] = ['Pages', (string)$pageCount];
        $fields[] = ['Version', (string)substr($data, 1, 7)];
        $sections[] = ['title' => 'PDF metadata', 'table' => $fields, 'cols' => ['Field', 'Value']];
    }

    // Strings extraction (printable runs >= 4 chars), first ~1 MB — skipped for
    // PE files which get a richer per-section extraction via pe_extract_strings().
    if (!is_pe($data)) {
        $strings = [];
        if (preg_match_all('/[\x20-\x7E]{4,}/', substr($data, 0, 1048576), $ms)) {
            $strings = array_slice($ms[0], 0, 150);
        }
        if (count($strings) > 0) {
            $sections[] = ['title' => 'Strings (first ' . count($strings) . ' of up to 1 MB)', 'table' => array_map(static function ($s) { return [$s]; }, $strings), 'cols' => ['String']];
        }
    }

    return ['sections' => $sections, 'has_macro' => $hasMacro];
}

// ------------------------------------------------------------- malware scan ---
function malware_scan(string $name, string $data, float $entropy, bool $hasMacro): array
{
    $findings = [];
    $score = 0;
    $sample = substr($data, 0, 2097152);
    $lower = strtolower($sample);

    // Web-shell / PHP dangerous function calls.
    $funcs = ['eval', 'assert', 'system', 'shell_exec', 'exec', 'passthru', 'proc_open', 'popen', 'pcntl_exec'];
    foreach ($funcs as $fn) {
        if (preg_match('/\b' . $fn . '\s*\(/i', $sample)) {
            $findings[] = ['msg' => 'Dangerous function call: ' . $fn . '()', 'level' => 'high'];
            $score += 3;
        }
    }
    // Command execution fed by request variables = classic web shell.
    if (preg_match('/\b(eval|assert|system|shell_exec|exec|passthru|popen|proc_open)\s*\(\s*\$_/i', $sample)) {
        $findings[] = ['msg' => 'Command execution fed directly by request data ($_GET/$_POST/$_REQUEST) — web-shell pattern', 'level' => 'high'];
        $score += 3;
    }
    // Encoded-payload decoders.
    if (preg_match('/\b(base64_decode|gzinflate|gzuncompress|str_rot13|gzdecode)\s*\(/i', $sample)) {
        $findings[] = ['msg' => 'Common obfuscation decoder used by shells: base64_decode / gzinflate / str_rot13 / etc.', 'level' => 'med'];
        $score += 2;
    }
    if (preg_match('/preg_replace\s*\(\s*["\']\/[^"\']*\/e/i', $sample)) {
        $findings[] = ['msg' => 'preg_replace with the /e modifier (executes code) — deprecated web-shell technique', 'level' => 'high'];
        $score += 3;
    }
    // Known web-shell / backdoor signatures.
    $shellNames = ['c99shell', 'r57shell', 'b374k', 'p0wny', 'filesman', 'kallaspy', 'phpspy', 'wso ', 'cpanel.php', 'china chopper', 'antichat', 'injected by', 'kaspersky lab'];
    foreach ($shellNames as $s) {
        if (stripos($lower, $s) !== false) {
            $findings[] = ['msg' => 'Known web-shell/backdoor signature: "' . $s . '"', 'level' => 'high'];
            $score += 3;
        }
    }

    // Packed / protected executables.
    $packers = ['upx0', 'upx1', 'upx!', 'themida', 'vmprotect', '.aspack', 'mpress', 'pecompact', '.nsp0'];
    foreach ($packers as $p) {
        if (stripos($lower, $p) !== false) {
            $findings[] = ['msg' => 'Executable packer/protector signature: ' . $p, 'level' => 'med'];
            $score += 2;
        }
    }

    // Office macro.
    if ($hasMacro) {
        $findings[] = ['msg' => 'Embedded VBA macro (vbaProject.bin) — macros are the #1 malware vector in Office files', 'level' => 'med'];
        $score += 2;
    }

    // Downloader / persistence command patterns (scripts, batch).
    $dl = ['powershell', 'invoke-webrequest', 'invoke-expression', 'iwr ', 'certutil -urlcache', 'bitsadmin', 'wscript', 'mshta', 'rundll32', 'schtasks', 'reg add', 'wget ', 'curl -o', 'powershell.exe -enc', 'downloadstring', 'net user', 'cmd /c'];
    foreach ($dl as $d) {
        if (stripos($lower, $d) !== false) {
            $findings[] = ['msg' => 'Suspicious command pattern: "' . trim($d) . '"', 'level' => 'med'];
            $score += 1;
        }
    }

    // Large base64 / hex blobs.
    if (preg_match('/[A-Za-z0-9+\/]{2000,}={0,2}/', $sample)) {
        $findings[] = ['msg' => 'Very large base64 blob (possible encoded payload)', 'level' => 'med'];
        $score += 2;
    }
    if (preg_match('/[0-9a-fA-F]{2000,}/', $sample)) {
        $findings[] = ['msg' => 'Very large hex blob (possible encoded payload)', 'level' => 'med'];
        $score += 1;
    }
    // High entropy on a small-to-medium file that isn't a known compressed format.
    $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    $compressedExts = ['zip', 'gz', 'xz', 'bz2', '7z', 'rar', 'jpg', 'jpeg', 'png', 'webp', 'mp3', 'mp4', 'flac', 'ogg'];
    if ($entropy > 7.9 && strlen($data) < 5000000 && !in_array($ext, $compressedExts, true)) {
        $findings[] = ['msg' => 'Near-maximum entropy (7.9+) on a non-compressed type — consistent with encryption or packed payload', 'level' => 'med'];
        $score += 2;
    }

    $score = min(10, $score);
    $severity = $score >= 6 ? 'High' : ($score >= 3 ? 'Medium' : 'Low');
    return ['score' => $score, 'severity' => $severity, 'findings' => $findings];
}

function detect_signature(string $data): string
{
    $h = substr($data, 0, 16);
    if ($h === '') {
        return 'Empty file';
    }
    $checks = [
        'GIF image' => "\x47\x49\x46\x38",
        'PNG image' => "\x89PNG",
        'JPEG image' => "\xFF\xD8\xFF",
        'WebP image' => "RIFF",
        'BMP image' => "BM",
        'PDF document' => "%PDF-",
        'ZIP archive' => "PK\x03\x04",
        'ZIP archive (empty)' => "PK\x05\x06",
        'ZIP self-extracting' => "PK\x07\x08",
        'GZIP archive' => "\x1F\x8B",
        'BZIP2 archive' => "BZh",
        'XZ archive' => "\xFD7zXZ\x00",
        '7-Zip archive' => "7z\xBC\xAF\x27\x1C",
        'RAR archive' => "Rar!\x1A\x07",
        'SQLite database' => "SQLite format 3\x00",
        'ELF executable' => "\x7FELF",
        'Windows PE executable' => "MZ",
        'Java class' => "\xCA\xFE\xBA\xBE",
        'FLAC audio' => "fLaC",
        'MP3 audio' => "ID3",
        'Zstandard' => "\x28\xB5\x2F\xFD",
        'LZ4' => "\x04\x22\x4D\x18",
    ];
    foreach ($checks as $label => $magic) {
        if (strncmp($h, $magic, strlen($magic)) === 0) {
            return $label;
        }
    }
    if (substr($data, 257, 5) === 'ustar') {
        return 'TAR archive';
    }
    if (substr($h, 8, 4) === 'WEBP') {
        return 'WebP image';
    }
    return '';
}

// --- PE analysis helpers ---------------------------------------------------

function is_pe(string $d): bool
{
    return strlen($d) > 64 && substr($d, 0, 2) === 'MZ'
        && @substr($d, unpack('V', substr($d, 60, 4))[1], 4) === 'PE';
}

function pe_u16(string $d, int $o): int
{
    return strlen($d) >= $o + 2 ? unpack('v', substr($d, $o, 2))[1] : 0;
}

function pe_u32(string $d, int $o): int
{
    return strlen($d) >= $o + 4 ? unpack('V', substr($d, $o, 4))[1] : 0;
}

function pe_rva_off(string $d, int $rva, array $secs, int $hdr): int
{
    if ($rva < $hdr) return $rva;
    foreach ($secs as $s) {
        $end = $s['va'] + max($s['vs'], $s['rs']);
        if ($rva >= $s['va'] && $rva < $end) return $s['ptr'] + ($rva - $s['va']);
    }
    return 0;
}

function pe_cstr(string $d, int $o, int $mx = 512): string
{
    if ($o < 0 || $o >= strlen($d)) return '';
    $e = strpos($d, "\0", $o);
    if ($e === false || $e - $o > $mx) $e = min($o + $mx, strlen($d));
    return substr($d, $o, $e - $o);
}

function pe_section_flags(int $f): string
{
    $r = [];
    if ($f & 0x00000020) $r[] = 'CODE';
    if ($f & 0x00000040) $r[] = 'DATA';
    if ($f & 0x20000000) $r[] = 'EXEC';
    if ($f & 0x40000000) $r[] = 'READ';
    if ($f & 0x80000000) $r[] = 'WRITE';
    return implode('|', $r);
}

function pe_flag_string(string $s): string
{
    $f = [];
    if (preg_match('#https?://#i', $s)) $f[] = 'URL';
    if (preg_match('/\b\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}\b/', $s)) $f[] = 'IP';
    if (preg_match('/RSA|AES|DES|MD5|SHA[0-9]|BASE64|ENCRYPT|DECRYPT|BCrypt|CryptImport|CryptEncrypt|CryptDecrypt|CryptGenKey/i', $s)) $f[] = 'Crypto';
    if (preg_match('/eval|exec|system|shell_exec|cmd\.exe|powershell|invoke-expression|invoke-webrequest|WScript|CScript|MSHTA|rundll32|regsvr32|certutil/i', $s)) $f[] = 'Injection';
    if (preg_match('/IsDebuggerPresent|CheckRemoteDebugger|NtQueryInformation|OutputDebugString|NtSetInformationThread|GetTickCount|QueryPerformanceCounter|rdtsc/i', $s)) $f[] = 'Anti-debug';
    if (preg_match('/SetWindowsHookEx|GetAsyncKeyState|GetKeyState|GetForegroundWindow|keylog|keyboard|SetWinEventHook/i', $s)) $f[] = 'Keylog';
    if (preg_match('/WinHTTP|WinInet|URLDownload|InternetOpen|InternetOpenUrl|HttpSend|HttpOpen|HttpQuery|InternetRead|WSAStartup|socket\(|connect\(|bind\(|listen\(/i', $s)) $f[] = 'Network';
    if (preg_match('/HKEY_|RegOpenKey|RegSetValue|CurrentVersion\\\\Run|schtasks|CreateService|ServiceInstall|ShellExecute/i', $s)) $f[] = 'Persistence';
    if (preg_match('/VirtualAlloc|VirtualProtect|WriteProcessMemory|NtUnmap|CreateRemoteThread|OpenProcess|NtAllocate|NtWrite|MapViewOfFile/i', $s)) $f[] = 'Injection';
    if (preg_match('/UPX|ASPack|VMProtect|Themida|PECompact|NSIS|InnoSetup|Armadillo|Obsidium|ExeCryptor/i', $s)) $f[] = 'Packer';
    if (preg_match('/\\\\(AppData|Local|Roaming)\\\\(Google\\\\Chrome|Mozilla\\\\Firefox|Microsoft\\\\Edge|Opera|Brave|Vivaldi|Telegram|Discord|Steam|Wallet|Electrum)/i', $s)) $f[] = 'Stealer';
    if (preg_match('/password|passwd|credential|token|cookie|login|wallet|seed|private.?key|mnemonic/i', $s)) $f[] = 'Credential';
    if (preg_match('/clipboard|GetClipboardData|OpenClipboard|SetClipboard/i', $s)) $f[] = 'Clipboard';
    if (preg_match('/screenshot|BitBlt|GetDC|CaptureBlt|GdiplusStartup/i', $s)) $f[] = 'Screenshot';
    if (preg_match('/discord(?:app)?\.com|discord\.gg|discord\.com\/api|webhooks.*discord/i', $s)) $f[] = 'Discord';
    if (preg_match('/api\.telegram\.org|telegram\.me|t\.me/i', $s)) $f[] = 'Telegram';
    if (preg_match('/\\\\|:[\\\\/]|[A-Z]:\\\\|\/tmp\/|\/etc\/|\/var\//i', $s)) $f[] = 'Path';
    if (preg_match('/\.(exe|dll|sys|bat|cmd|ps1|vbs|com|scr)\b/i', $s)) $f[] = 'Executable';
    return implode(', ', $f);
}

function pe_imphash(array $imports): string
{
    $parts = [];
    foreach ($imports as $imp) {
        $dll = strtolower(pathinfo($imp['dll'], PATHINFO_FILENAME));
        foreach ($imp['funcs'] as $fn) {
            $parts[] = $dll . '.' . (strpos($fn, 'ord_') === 0 ? $fn : strtolower($fn));
        }
    }
    sort($parts);
    return $parts !== [] ? md5(implode(',', $parts)) : '';
}

function pe_extract_strings(string $data): array
{
    $ascii = [];
    if (preg_match_all('/[\x20-\x7E]{5,}/', $data, $ms)) {
        foreach ($ms[0] as $s) {
            if (in_array($s, ['', "\r", "\n"], true)) continue;
            $ascii[] = $s;
            if (count($ascii) >= 600) break;
        }
    }
    $utf16 = [];
    if (preg_match_all('/(?:[\x20-\x7E]\x00){5,}/', $data, $ms)) {
        foreach ($ms[0] as $s) {
            $u = @mb_convert_encoding($s, 'UTF-8', 'UTF-16LE');
            $utf16[] = ($u !== false && $u !== '') ? $u : bin2hex($s);
            if (count($utf16) >= 250) break;
        }
    }
    return ['ascii' => $ascii, 'utf16' => $utf16];
}

function pe_extract_urls(string $d): array
{
    $urls = [];
    $seen = [];
    if (preg_match_all('/https?:\/\/[^\x00-\x1f\s"\'<>]{5,500}/i', $d, $ms)) {
        foreach ($ms[0] as $u) {
            $clean = rtrim($u, ".,;:!?)");
            if (!isset($seen[$clean]) && strlen($clean) >= 8) {
                $seen[$clean] = true;
                $urls[] = $clean;
            }
        }
    }
    if (preg_match_all('/ftp:\/\/[^\x00-\x1f\s"\'<>]{5,300}/i', $d, $ms)) {
        foreach ($ms[0] as $u) {
            $clean = rtrim($u, ".,;:!?)");
            if (!isset($seen[$clean]) && strlen($clean) >= 8) {
                $seen[$clean] = true;
                $urls[] = $clean;
            }
        }
    }
    return array_slice($urls, 0, 200);
}

function pe_discord_hunt(string $d): array
{
    $findings = [];
    $seen = [];
    if (preg_match_all('/https?:\/\/discord(?:app)?\.com\/api\/webhooks\/\d+\/[A-Za-z0-9_-]{60,}/', $d, $ms)) {
        foreach (array_unique($ms[0]) as $u) {
            $trunc = substr($u, 0, 80);
            if (!isset($seen[$trunc])) { $seen[$trunc] = true; $findings[] = ['type' => 'Webhook URL', 'value' => $trunc]; }
        }
    }
    if (preg_match_all('/[MN][A-Za-z\d]{23,}\.[\w-]{6}\.[\w-]{27,}/', $d, $ms)) {
        foreach (array_unique($ms[0]) as $t) {
            if (!isset($seen[$t]) && strlen($t) < 200) { $seen[$t] = true; $findings[] = ['type' => 'Discord token', 'value' => substr($t, 0, 80)]; }
        }
    }
    if (preg_match_all('/mfa\.[A-Za-z0-9_-]{20,80}/', $d, $ms)) {
        foreach (array_unique($ms[0]) as $t) {
            if (!isset($seen[$t])) { $seen[$t] = true; $findings[] = ['type' => 'MFA token', 'value' => $t]; }
        }
    }
    if (preg_match_all('/discord\.gg\/[A-Za-z0-9_-]+/', $d, $ms)) {
        foreach (array_unique($ms[0]) as $inv) {
            if (!isset($seen[$inv])) { $seen[$inv] = true; $findings[] = ['type' => 'Invite link', 'value' => $inv]; }
        }
    }
    return array_slice($findings, 0, 50);
}

function pe_decrypt_strings(array $strings): array
{
    $results = [];
    $seen = [];
    foreach ($strings['ascii'] as $s) {
        if (strlen($s) >= 24 && preg_match('/^[A-Za-z0-9+\/]+={0,2}$/', $s)) {
            $decoded = @base64_decode($s, true);
            if ($decoded !== false && strlen($decoded) > 4) {
                $pr = preg_match_all('/[\x20-\x7E]/', $decoded) / max(1, strlen($decoded));
                if ($pr > 0.5 || preg_match('/[\x20-\x7E]{10,}/', $decoded)) {
                    $k = 'b64:' . substr($s, 0, 24);
                    if (!isset($seen[$k])) { $seen[$k] = true; $results[] = ['encoded' => substr($s, 0, 80), 'decoded' => substr($decoded, 0, 500), 'method' => 'Base64']; }
                }
            }
        }
        if (strlen($s) >= 20 && preg_match('/^[0-9a-fA-F]+$/', $s) && strlen($s) % 2 === 0) {
            $decoded = @hex2bin($s);
            if ($decoded !== false && strlen($decoded) > 4) {
                $pr = preg_match_all('/[\x20-\x7E]/', $decoded) / max(1, strlen($decoded));
                if ($pr > 0.5) {
                    $k = 'hex:' . substr($s, 0, 24);
                    if (!isset($seen[$k])) { $seen[$k] = true; $results[] = ['encoded' => substr($s, 0, 80), 'decoded' => substr($decoded, 0, 500), 'method' => 'Hex']; }
                }
            }
        }
        $np = preg_match_all('/[^\x20-\x7E]/', $s);
        if (strlen($s) >= 12 && $np > strlen($s) * 0.15) {
            $best = 0; $bk = 0; $bd = '';
            for ($k = 1; $k < 256; $k++) {
                $d = '';
                for ($j = 0; $j < strlen($s); $j++) $d .= chr(ord($s[$j]) ^ $k);
                $sc = preg_match_all('/[\x20-\x7E]/', $d);
                if ($sc > $best) { $best = $sc; $bk = $k; $bd = $d; }
            }
            if ($best > strlen($s) * 0.65 && $bk > 0) {
                $kx = 'xor:' . substr($s, 0, 16) . ':' . $bk;
                if (!isset($seen[$kx]) && !preg_match('/^[\x20-\x7E]+$/', $s)) {
                    $seen[$kx] = true;
                    $results[] = ['encoded' => substr($s, 0, 80), 'decoded' => substr($bd, 0, 500), 'method' => sprintf('XOR (0x%02X)', $bk)];
                }
            }
        }
    }
    return array_slice($results, 0, 50);
}

function pe_categorize_imports(array $imports): array
{
    $cats = [
        'Crypto' => ['bcrypt','crypt32','ncrypt','advapi32'],
        'Network' => ['winhttp','wininet','ws2_32','urlmon','iphlpapi'],
        'Process' => ['kernel32'],
        'Registry' => ['advapi32'],
        'File I/O' => ['kernel32','shlwapi'],
        'Anti-debug' => ['kernel32','ntdll'],
        'UI / Input' => ['user32','gdi32'],
        'COM / Shell' => ['ole32','oleaut32','shell32','comctl32'],
    ];
    $result = [];
    foreach ($imports as $imp) {
        $dll = strtolower(pathinfo($imp['dll'], PATHINFO_FILENAME));
        $assigned = false;
        foreach ($cats as $cat => $dlls) {
            if (in_array($dll, $dlls, true)) {
                $result[$cat][] = $imp['dll'] . ' (' . count($imp['funcs']) . ')';
                $assigned = true;
                break;
            }
        }
        if (!$assigned) {
            $result['Other'][] = $imp['dll'] . ' (' . count($imp['funcs']) . ')';
        }
    }
    return $result;
}

function pe_analysis(string $d): array
{
    $len = strlen($d);
    $err = ['error' => 'Not a valid PE file'];
    if ($len < 64 || substr($d, 0, 2) !== 'MZ') return $err;

    $e_lfanew = pe_u32($d, 60);
    if ($e_lfanew + 24 > $len || substr($d, $e_lfanew, 4) !== "PE\0\0") return $err;

    $coff = $e_lfanew + 4;
    $machine     = pe_u16($d, $coff);
    $numSections = pe_u16($d, $coff + 2);
    $timestamp   = pe_u32($d, $coff + 4);
    $sizeOptHdr  = pe_u16($d, $coff + 16);
    $coffChars   = pe_u16($d, $coff + 18);

    $opt = $coff + 20;
    if ($opt + $sizeOptHdr > $len) return $err;
    $magic = pe_u16($d, $opt);
    $is64  = ($magic === 0x20B);
    if ($magic !== 0x10B && !$is64) return $err;

    $entryRva    = pe_u32($d, $opt + 16);
    $headersSize = pe_u32($d, $opt + 60);
    $subsystem   = pe_u16($d, $opt + 68);
    $dllChars    = pe_u16($d, $opt + 70);
    $sizeOfImage = pe_u32($d, $opt + 56);

    if ($is64) {
        $imgBase     = sprintf('0x%016X', pe_u32($d, $opt + 24) | ((int)pe_u32($d, $opt + 28) << 32));
        $numDataDirs = pe_u32($d, $opt + 108);
        $ddOff       = $opt + 112;
    } else {
        $imgBase     = sprintf('0x%08X', pe_u32($d, $opt + 24));
        $numDataDirs = pe_u32($d, $opt + 92);
        $ddOff       = $opt + 96;
    }

    $sectOff = $opt + $sizeOptHdr;
    $sections = [];
    for ($i = 0; $i < $numSections; $i++) {
        $so = $sectOff + $i * 40;
        if ($so + 40 > $len) break;
        $name  = rtrim(substr($d, $so, 8), "\0");
        $vs    = pe_u32($d, $so + 8);
        $va    = pe_u32($d, $so + 12);
        $rs    = pe_u32($d, $so + 16);
        $ptr   = pe_u32($d, $so + 20);
        $flags = pe_u32($d, $so + 36);
        $sections[] = compact('name', 'va', 'vs', 'rs', 'ptr', 'flags');
    }

    $entrySection = '';
    foreach ($sections as $sec) {
        if ($entryRva >= $sec['va'] && $entryRva < $sec['va'] + max($sec['vs'], $sec['rs'])) {
            $entrySection = $sec['name'];
            break;
        }
    }

    // --- Imports ---
    $imports = [];
    if ($numDataDirs > 1) {
        $impRva = pe_u32($d, $ddOff + 8);
        if ($impRva > 0) {
            $impOff = pe_rva_off($d, $impRva, $sections, $headersSize);
            $dc = 0;
            while ($dc < 200 && $impOff > 0 && $impOff + ($dc + 1) * 20 <= $len) {
                $eo = $impOff + $dc * 20;
                $nameRva = pe_u32($d, $eo + 12);
                $origFt  = pe_u32($d, $eo);
                $ft      = pe_u32($d, $eo + 16);
                if ($nameRva === 0 && $ft === 0) break;
                $dllName = pe_cstr($d, pe_rva_off($d, $nameRva, $sections, $headersSize));
                if ($dllName === '') { $dc++; continue; }
                $ftOff = pe_rva_off($d, $origFt ?: $ft, $sections, $headersSize);
                $funcs = [];
                $fc = 0;
                while ($fc < 1000 && $ftOff > 0) {
                    $esz = $is64 ? 8 : 4;
                    if ($ftOff + ($fc + 1) * $esz > $len) break;
                    if ($is64) {
                        $lo = pe_u32($d, $ftOff + $fc * 8);
                        $hi = pe_u32($d, $ftOff + $fc * 8 + 4);
                        if ($lo === 0 && $hi === 0) break;
                        if ($hi & 0x80000000) {
                            $funcs[] = 'ord_' . ($lo & 0xFFFF);
                        } else {
                            $ho = pe_rva_off($d, $lo, $sections, $headersSize);
                            $hn = pe_cstr($d, $ho + 2);
                            $funcs[] = $hn !== '' ? $hn : sprintf('ord_%d', pe_u16($d, $ho));
                        }
                    } else {
                        $entry = pe_u32($d, $ftOff + $fc * 4);
                        if ($entry === 0) break;
                        if ($entry & 0x80000000) {
                            $funcs[] = 'ord_' . ($entry & 0xFFFF);
                        } else {
                            $ho = pe_rva_off($d, $entry, $sections, $headersSize);
                            $hn = pe_cstr($d, $ho + 2);
                            $funcs[] = $hn !== '' ? $hn : sprintf('ord_%d', pe_u16($d, $ho));
                        }
                    }
                    $fc++;
                }
                $imports[] = ['dll' => $dllName, 'funcs' => $funcs];
                $dc++;
            }
        }
    }

    // --- Exports ---
    $exports = [];
    if ($numDataDirs > 0) {
        $expRva = pe_u32($d, $ddOff);
        if ($expRva > 0) {
            $eo = pe_rva_off($d, $expRva, $sections, $headersSize);
            if ($eo > 0 && $eo + 40 <= $len) {
                $expBase   = pe_u32($d, $eo + 16);
                $numFuncs  = pe_u32($d, $eo + 20);
                $numNames  = pe_u32($d, $eo + 24);
                $addrNames = pe_u32($d, $eo + 32);
                $addrOrds  = pe_u32($d, $eo + 36);
                $namesOff  = pe_rva_off($d, $addrNames, $sections, $headersSize);
                $ordsOff   = pe_rva_off($d, $addrOrds, $sections, $headersSize);
                for ($i = 0; $i < min($numNames, 500); $i++) {
                    if ($namesOff <= 0 || $ordsOff <= 0) break;
                    if ($namesOff + ($i + 1) * 4 > $len || $ordsOff + ($i + 1) * 2 > $len) break;
                    $nOff = pe_rva_off($d, pe_u32($d, $namesOff + $i * 4), $sections, $headersSize);
                    $ord  = pe_u16($d, $ordsOff + $i * 2);
                    $exports[] = ['name' => pe_cstr($d, $nOff), 'ordinal' => $expBase + $ord];
                }
            }
        }
    }

    // --- Section entropy ---
    $secRows = [];
    foreach ($sections as $s) {
        $raw = '';
        if ($s['ptr'] > 0 && $s['rs'] > 0 && $s['ptr'] + min($s['rs'], 131072) <= $len) {
            $raw = substr($d, $s['ptr'], min($s['rs'], 131072));
        }
        $ent = 0.0;
        if (strlen($raw) > 0) {
            $freq = array_fill(0, 256, 0);
            for ($j = 0; $j < strlen($raw); $j++) $freq[ord($raw[$j])]++;
            foreach ($freq as $c) { if ($c > 0) { $p = $c / strlen($raw); $ent -= $p * log($p, 2); } }
        }
        $secRows[] = [
            'name' => $s['name'], 'virtual_size' => $s['vs'], 'raw_size' => $s['rs'],
            'entropy' => $ent, 'flags' => $s['flags'], 'flags_str' => pe_section_flags($s['flags']),
        ];
    }

    // --- Strings ---
    $strings = pe_extract_strings($d);
    $flagged = [];
    foreach ($strings['ascii'] as $s) {
        $fl = pe_flag_string($s);
        if ($fl !== '') $flagged[] = ['str' => $s, 'flags' => $fl];
    }

    // --- URLs ---
    $urls = pe_extract_urls($d);

    // --- Discord hunt ---
    $discord = pe_discord_hunt($d);

    // --- Decrypt attempts ---
    $decrypted = pe_decrypt_strings($strings);

    // --- Import categories ---
    $importCats = pe_categorize_imports($imports);

    $tsStr = $timestamp > 0 ? gmdate('Y-m-d H:i:s', $timestamp) : 'unknown';
    $subsysNames = [1=>'Native',2=>'Windows GUI',3=>'Windows CUI',5=>'OS/2 CUI',7=>'POSIX CUI',9=>'Win CE GUI',10=>'EFI App',11=>'EFI Boot Driver',12=>'EFI Runtime',13=>'EFI ROM',14=>'Xbox',16=>'Boot App'];
    $machineNames = [0x014c=>'x86',0x8664=>'x86-64',0xaa64=>'ARM64',0x01c0=>'ARM',0x01c4=>'ARM NT',0x0200=>'IA-64',0x0ebc=>'EFI Byte Code',0x01f0=>'PowerPC'];

    return [
        'kind' => $is64 ? 'PE32+' : 'PE32',
        'machine' => $machineNames[$machine] ?? sprintf('0x%04X', $machine),
        'subsystem' => $subsysNames[$subsystem] ?? sprintf('0x%04X', $subsystem),
        'num_sections' => $numSections,
        'size_of_image' => $sizeOfImage,
        'timestamp' => $timestamp,
        'timestamp_str' => $tsStr,
        'entry_rva' => $entryRva,
        'entry_section' => $entrySection,
        'image_base' => $imgBase,
        'dll_chars' => $dllChars,
        'is_dll' => ($coffChars & 0x2000) !== 0,
        'sections_table' => $secRows,
        'imports' => $imports,
        'imphash' => pe_imphash($imports),
        'import_categories' => $importCats,
        'exports' => $exports,
        'strings' => $strings,
        'flagged_strings' => $flagged,
        'urls' => $urls,
        'discord' => $discord,
        'decrypted' => $decrypted,
    ];
}

function pe_report_sections(array $pe): array
{
    if (isset($pe['error'])) return [];
    $out = [];

    // --- Discord Artifacts (top priority) ---
    if (!empty($pe['discord'])) {
        $dRows = [];
        foreach ($pe['discord'] as $d) { $dRows[] = [$d['type'], $d['value']]; }
        $out[] = [
            'title' => '<span class="text-danger fw-bold">Discord Artifacts Found (' . count($dRows) . ')</span>',
            'table' => $dRows, 'cols' => ['Type', 'Value'],
        ];
    }

    // --- PE Header ---
    $dllFlag = ($pe['dll_chars'] ?? 0) & 0x2000 ? 'Yes' : 'No';
    $out[] = [
        'title' => 'PE Header — ' . $pe['kind'],
        'table' => [
            ['Machine', $pe['machine']],
            ['Subsystem', $pe['subsystem']],
            ['Sections', $pe['num_sections']],
            ['Image size', number_format($pe['size_of_image']) . ' bytes'],
            ['Compile time', $pe['timestamp_str']],
            ['Entry point', sprintf('0x%08X', $pe['entry_rva']) . ' (' . ($pe['entry_section'] ?: '?') . ')'],
            ['Image base', $pe['image_base']],
            ['DLL', $dllFlag],
            ['Import hash', $pe['imphash']],
        ],
        'cols' => ['Field', 'Value'],
    ];

    // --- Sections ---
    $secTable = [];
    foreach ($pe['sections_table'] as $s) {
        $secTable[] = [$s['name'], number_format($s['virtual_size']), number_format($s['raw_size']), number_format($s['entropy'], 2), $s['flags_str']];
    }
    if (count($secTable) > 0) {
        $out[] = [
            'title' => 'Sections (' . count($secTable) . ')',
            'table' => $secTable,
            'cols' => ['Name', 'Virtual size', 'Raw size', 'Entropy', 'Flags'],
        ];
    }

    // --- Imports by category ---
    if (!empty($pe['import_categories'])) {
        $catRows = [];
        foreach ($pe['import_categories'] as $cat => $dlls) {
            $catRows[] = [$cat, implode(', ', $dlls)];
        }
        $out[] = [
            'title' => 'Imports by Category (' . count($pe['imports']) . ' DLLs)',
            'table' => $catRows,
            'cols' => ['Category', 'DLLs'],
        ];
    }

    // --- Full Imports ---
    $impTable = [];
    foreach ($pe['imports'] as $imp) {
        $all = implode(', ', $imp['funcs']);
        if (strlen($all) > 600) $all = substr($all, 0, 600) . ' ... (+' . (count($imp['funcs']) - 20) . ' more)';
        $impTable[] = [$imp['dll'], count($imp['funcs']), $all];
    }
    if (count($impTable) > 0) {
        $out[] = [
            'title' => 'All Imports (' . count($impTable) . ' DLLs, ' . array_sum(array_map(function($i) { return $i[1]; }, $impTable)) . ' functions)',
            'table' => $impTable,
            'cols' => ['DLL', 'Count', 'Functions'],
        ];
    }

    // --- Exports ---
    $expTable = [];
    foreach ($pe['exports'] as $ex) {
        $expTable[] = [$ex['name'], $ex['ordinal']];
    }
    if (count($expTable) > 0) {
        $out[] = [
            'title' => 'Exports (' . count($expTable) . ')',
            'table' => $expTable,
            'cols' => ['Name', 'Ordinal'],
        ];
    }

    // --- Extracted URLs ---
    if (!empty($pe['urls'])) {
        $urlRows = [];
        foreach ($pe['urls'] as $u) { $urlRows[] = [$u]; }
        $out[] = [
            'title' => 'Extracted URLs (' . count($urlRows) . ')',
            'table' => array_slice($urlRows, 0, 100),
            'cols' => ['URL'],
        ];
    }

    // --- Decrypted / Decoded Strings ---
    if (!empty($pe['decrypted'])) {
        $decRows = [];
        foreach ($pe['decrypted'] as $dr) {
            $decRows[] = [$dr['method'], $dr['encoded'], substr($dr['decoded'], 0, 200)];
        }
        $out[] = [
            'title' => 'Decoded Strings (' . count($decRows) . ')',
            'table' => $decRows,
            'cols' => ['Method', 'Encoded (truncated)', 'Decoded (truncated)'],
        ];
    }

    // --- Flagged Strings ---
    $flRows = [];
    foreach ($pe['flagged_strings'] as $fs) {
        $flRows[] = [substr($fs['str'], 0, 120), $fs['flags']];
    }
    if (count($flRows) > 0) {
        $out[] = [
            'title' => 'Flagged Strings (' . count($flRows) . ')',
            'table' => array_slice($flRows, 0, 300),
            'cols' => ['String', 'Flags'],
        ];
    }

    // --- Raw Strings ---
    $allStr = [];
    foreach ($pe['strings']['ascii'] as $s) { $allStr[] = [$s]; }
    foreach ($pe['strings']['utf16'] as $s) { $allStr[] = [$s . ' (UTF-16)']; }
    if (count($allStr) > 0) {
        $out[] = [
            'title' => 'All Strings (' . count($allStr) . ' found)',
            'table' => array_slice($allStr, 0, 500),
            'cols' => ['String'],
        ];
    }

    return $out;
}

function format_bytes(int $bytes): string
{
    if ($bytes < 1024) {
        return $bytes . ' B';
    }
    $units = ['KB', 'MB', 'GB'];
    $v = $bytes / 1024;
    foreach ($units as $u) {
        if ($v < 1024) {
            return number_format($v, 2) . ' ' . $u;
        }
        $v /= 1024;
    }
    return number_format($v, 2) . ' TB';
}
