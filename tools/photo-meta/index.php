<?php
require_once __DIR__ . '/../../functions.php';

start_session();
$GLOBALS['_meta'] = [
    'description' => 'Free online photo metadata extractor. Upload a JPEG, PNG, TIFF or WebP image and instantly view all EXIF, IPTC and XMP data — camera model, lens, GPS coordinates, date taken, shutter speed, aperture and more.',
    'keywords' => 'photo metadata, EXIF viewer, IPTC extractor, XMP parser, GPS coordinates, camera info, image metadata, photo forensics',
];
page_header('Photo Metadata Extractor');

$result = null;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify_or_fail();
    if (!rate_limit_check('photo_meta', 20, 300)) {
        $error = 'Too many requests. Try again in 5 minutes.';
    } elseif (!isset($_FILES['file'])) {
        $error = 'Choose an image to analyse.';
    } elseif (($_FILES['file']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        $error = 'Upload failed (error ' . (int)($_FILES['file']['error'] ?? 0) . ').';
    } elseif ((int)$_FILES['file']['size'] > 50 * 1024 * 1024) {
        $error = 'File too big (50 MB limit).';
    } else {
        $data = @file_get_contents($_FILES['file']['tmp_name']);
        $name = basename((string)($_FILES['file']['name'] ?? 'image'));
        if ($data === false || $data === '') {
            $error = 'Could not read the uploaded file.';
        } else {
            $result = extract_photo_meta($name, $data, (string)$_FILES['file']['tmp_name']);
            if ($result === null) {
                $error = 'Could not identify this as a supported image format (JPEG, PNG, TIFF, WebP).';
            }
        }
    }
}
?>
<div class="container" style="max-width: 1050px;">
    <div class="text-center mb-4">
        <h1 class="h3 mb-2">Photo Metadata Extractor</h1>
        <p class="text-secondary mb-0">Upload an image (JPEG, PNG, TIFF, WebP) to view all embedded metadata: EXIF camera settings, GPS location, IPTC copyright/keywords, XMP data, ICC profiles and more. Nothing is stored — analysed in memory and wiped immediately.</p>
    </div>

    <?php if ($error !== ''): ?>
        <div class="alert alert-danger"><?= e($error) ?></div>
    <?php endif; ?>

    <div class="card mb-4">
        <div class="card-body">
            <form method="post" enctype="multipart/form-data" onsubmit="var b=this.querySelector('button[type=submit]');if(b){b.disabled=true;b.textContent='Analysing…';}">
                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                <div class="mb-3">
                    <input type="file" name="file" class="form-control" accept="image/*" required>
                </div>
                <button type="submit" class="btn btn-primary">Extract Metadata</button>
            </form>
        </div>
    </div>

    <?php if ($result !== null): ?>
    <div class="card mb-4">
        <div class="card-header fw-bold"><?= e($result['name']) ?></div>
        <div class="card-body">
            <table class="table table-sm align-middle" style="font-size:.9rem;">
                <tbody>
                    <tr><th style="width:180px;">Format</th><td><code><?= e($result['format']) ?></code></td></tr>
                    <tr><th>Dimensions</th><td><?= number_format($result['width']) ?> × <?= number_format($result['height']) ?> px</td></tr>
                    <?php if ($result['bit_depth']): ?>
                    <tr><th>Bit depth</th><td><?= (int)$result['bit_depth'] ?>-bit</td></tr>
                    <?php endif; ?>
                    <tr><th>File size</th><td><?= e(format_bytes($result['file_size'])) ?></td></tr>
                    <?php if ($result['color_type']): ?>
                    <tr><th>Color type</th><td><?= e($result['color_type']) ?></td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <?php if (!empty($result['exif'])): ?>
    <div class="card mb-4">
        <div class="card-header fw-bold">EXIF Data</div>
        <div class="card-body">
            <div class="table-responsive"><table class="table table-sm" style="font-size:.85rem;">
                <thead><tr><th>Tag</th><th>Value</th></tr></thead>
                <tbody>
                <?php foreach ($result['exif'] as $tag => $val): ?>
                    <tr>
                        <td class="text-secondary"><code><?= e($tag) ?></code></td>
                        <td class="text-break"><?= e((string)$val) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table></div>
        </div>
    </div>
    <?php endif; ?>

    <?php if (!empty($result['gps'])): ?>
    <div class="card mb-4">
        <div class="card-header fw-bold">GPS Location</div>
        <div class="card-body">
            <table class="table table-sm" style="font-size:.85rem;">
                <tbody>
                    <tr><th>Latitude</th><td><?= number_format($result['gps']['lat'], 6) ?></td></tr>
                    <tr><th>Longitude</th><td><?= number_format($result['gps']['lon'], 6) ?></td></tr>
                    <?php if (isset($result['gps']['alt'])): ?>
                    <tr><th>Altitude</th><td><?= number_format($result['gps']['alt'], 1) ?> m</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
            <a class="btn btn-outline-light btn-sm mt-2" target="_blank" rel="noopener"
               href="https://www.google.com/maps?q=<?= $result['gps']['lat'] ?>,<?= $result['gps']['lon'] ?>">
                Open in Google Maps
            </a>
        </div>
    </div>
    <?php endif; ?>

    <?php if (!empty($result['iptc'])): ?>
    <div class="card mb-4">
        <div class="card-header fw-bold">IPTC Data</div>
        <div class="card-body">
            <div class="table-responsive"><table class="table table-sm" style="font-size:.85rem;">
                <thead><tr><th>Tag</th><th>Value</th></tr></thead>
                <tbody>
                <?php foreach ($result['iptc'] as $tag => $val): ?>
                    <tr>
                        <td class="text-secondary"><code><?= e($tag) ?></code></td>
                        <td class="text-break"><?= e((string)$val) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table></div>
        </div>
    </div>
    <?php endif; ?>

    <?php if (!empty($result['xmp'])): ?>
    <div class="card mb-4">
        <div class="card-header fw-bold">XMP Data</div>
        <div class="card-body">
            <div class="table-responsive"><table class="table table-sm" style="font-size:.85rem;">
                <thead><tr><th>Key</th><th>Value</th></tr></thead>
                <tbody>
                <?php foreach ($result['xmp'] as $key => $val): ?>
                    <tr>
                        <td class="text-secondary"><code><?= e($key) ?></code></td>
                        <td class="text-break"><?= e((string)$val) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table></div>
        </div>
    </div>
    <?php endif; ?>

    <?php if (!empty($result['icc'])): ?>
    <div class="card mb-4">
        <div class="card-header fw-bold">ICC Profile</div>
        <div class="card-body">
            <table class="table table-sm" style="font-size:.85rem;">
                <tbody>
                    <tr><th>Profile name</th><td><?= e($result['icc']['name'] ?? 'Unknown') ?></td></tr>
                    <tr><th>Profile size</th><td><?= number_format($result['icc']['size'] ?? 0) ?> bytes</td></tr>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <p class="text-secondary small mb-4">Metadata shown is raw — tags are displayed as-is from the image. Some cameras write non-standard tags. GPS coordinates are only present if the camera/phone had location services enabled.</p>
    <?php endif; ?>
</div>
<?php
page_footer();

// ─── Metadata extraction functions ──────────────────────────────────

function extract_photo_meta(string $name, string $data, string $tmpPath): ?array {
    $size = strlen($data);
    if ($size < 8) return null;

    $result = [
        'name'     => $name,
        'format'   => 'Unknown',
        'width'    => 0,
        'height'   => 0,
        'bit_depth'=> 0,
        'color_type'=> '',
        'file_size'=> $size,
        'exif'     => [],
        'gps'      => null,
        'iptc'     => [],
        'xmp'      => [],
        'icc'      => null,
    ];

    // Detect format
    $magic = substr($data, 0, 8);
    if (substr($magic, 0, 2) === "\xFF\xD8") {
        $result['format'] = 'JPEG';
        parse_jpeg($data, $result, $tmpPath);
    } elseif (substr($magic, 0, 4) === "\x89PNG") {
        $result['format'] = 'PNG';
        parse_png($data, $result);
    } elseif (substr($magic, 0, 4) === 'II\x2A\x00' || substr($magic, 0, 4) === 'MM\x00\x2A') {
        $result['format'] = 'TIFF';
        parse_tiff($data, $result, $tmpPath);
    } elseif (str_contains($data, 'RIFF') && str_contains(substr($data, 8, 12), 'WEBP')) {
        $result['format'] = 'WebP';
        parse_webp($data, $result);
    } else {
        return null;
    }

    // Fallback: use getimagesize if available and dimensions missing
    if ($result['width'] === 0 && function_exists('getimagesizefromstring')) {
        $info = @getimagesizefromstring($data);
        if ($info) {
            $result['width'] = $info[0];
            $result['height'] = $info[1];
        }
    }

    return $result;
}

// ── JPEG parser ──────────────────────────────────────────────────────
function parse_jpeg(string $data, array &$result, string $tmpPath): void {
    $len = strlen($data);
    $pos = 2; // skip SOI

    // Read EXIF via PHP's exif module if possible
    if (function_exists('exif_read_data')) {
        $tmpFile = $tmpPath . '.exif.jpg';
        @file_put_contents($tmpFile, $data);
        $exif = @exif_read_data($tmpFile, 0, true);
        @unlink($tmpFile);

        if ($exif !== false) {
            // EXIF IFD0 (main image)
            if (isset($exif['IFD0'])) {
                foreach ($exif['IFD0'] as $tag => $val) {
                    if (is_string($val) || is_numeric($val)) {
                        $result['exif'][$tag] = $val;
                    }
                }
            }
            // EXIF sub IFD
            if (isset($exif['EXIF'])) {
                foreach ($exif['EXIF'] as $tag => $val) {
                    if (is_string($val) || is_numeric($val)) {
                        $result['exif'][$tag] = $val;
                    }
                }
            }
            // GPS
            if (isset($exif['GPS'])) {
                $result['gps'] = parse_gps($exif['GPS']);
            }
            // Dimensions from EXIF
            if (isset($exif['COMPUTED']['ImageWidth'])) {
                $result['width'] = (int)$exif['COMPUTED']['ImageWidth'];
                $result['height'] = (int)$exif['COMPUTED']['ImageHeight'];
            }
            // IPTC from APP13
            if (isset($exif['APP13'])) {
                parse_iptc_from_app13($exif['APP13'], $result['iptc']);
            }
        }
    }

    // Parse JPEG markers for dimensions if still missing + XMP + ICC
    while ($pos + 1 < $len) {
        if (ord($data[$pos]) !== 0xFF) break;
        $marker = ord($data[$pos + 1]);
        if ($marker === 0xD9) break; // EOI
        if ($marker === 0x00 || $marker === 0xFF) { $pos++; continue; }

        $pos += 2;
        if ($pos + 1 >= $len) break;
        $segLen = unpack('n', substr($data, $pos, 2))[1];
        $segData = substr($data, $pos + 2, $segLen - 2);

        // SOF — dimensions
        if (in_array($marker, [0xC0, 0xC1, 0xC2]) && strlen($segData) >= 7) {
            $result['bit_depth'] = ord($segData[0]);
            $result['height'] = unpack('n', substr($segData, 1, 2))[1];
            $result['width'] = unpack('n', substr($segData, 3, 2))[1];
            $nc = ord($segData[5]);
            $result['color_type'] = $nc === 1 ? 'Grayscale' : ($nc === 3 ? 'YCbCr' : ($nc === 4 ? 'CMYK' : 'Other (' . $nc . ')'));
        }

        // APP1 — EXIF/XMP
        if ($marker === 0xE1 && $segLen > 2) {
            if (substr($segData, 0, 29) === 'http://ns.adobe.com/xap/1.0/') {
                parse_xmp(substr($segData, 29), $result['xmp']);
            }
            if (substr($segData, 0, 6) === 'Exif\x00\x00') {
                parse_exif_tiff(substr($segData, 6), $result['exif'], $result['gps']);
            }
        }

        // APP2 — ICC profile
        if ($marker === 0xE2 && substr($segData, 0, 12) === 'ICC_PROFILE\x00') {
            if (!isset($result['icc'])) $result['icc'] = ['data' => '', 'name' => '', 'size' => 0];
            $result['icc']['data'] .= substr($segData, 14);
            $result['icc']['size'] += strlen($segData) - 14;
            // Extract name from ICC data
            if (strlen($result['icc']['data']) > 44 && $result['icc']['name'] === '') {
                $result['icc']['name'] = trim(substr($result['icc']['data'], 36, 8));
            }
        }

        $pos += $segLen;
    }
}

// ── PNG parser ───────────────────────────────────────────────────────
function parse_png(string $data, array &$result): void {
    if (strlen($data) < 24) return;
    $result['width'] = unpack('N', substr($data, 16, 4))[1];
    $result['height'] = unpack('N', substr($data, 20, 4))[1];
    $result['bit_depth'] = ord($data[24]);
    $ct = ord($data[25]);
    $types = [0 => 'Grayscale', 2 => 'RGB', 3 => 'Indexed', 4 => 'Grayscale+Alpha', 6 => 'RGBA'];
    $result['color_type'] = $types[$ct] ?? "Type $ct";

    // Walk chunks for tEXt/iTXt/zTXt and ICCP
    $pos = 33; // after IHDR
    while ($pos + 8 < strlen($data)) {
        $chunkLen = unpack('N', substr($data, $pos, 4))[1];
        $chunkType = substr($data, $pos + 4, 4);
        $chunkData = substr($data, $pos + 8, $chunkLen);

        if ($chunkType === 'tEXt' || $chunkType === 'iTXt') {
            $sep = strpos($chunkData, "\0");
            if ($sep !== false) {
                $key = substr($chunkData, 0, $sep);
                $val = substr($chunkData, $sep + 1);
                $result['exif'][$key] = $val;
            }
        }

        if ($chunkType === 'iCCP') {
            $sep = strpos($chunkData, "\0");
            if ($sep !== false) {
                $result['icc'] = [
                    'name' => substr($chunkData, 0, $sep),
                    'size' => $chunkLen - $sep - 2,
                    'data' => substr($chunkData, $sep + 2),
                ];
            }
        }

        if ($chunkType === 'IEND') break;
        $pos += 12 + $chunkLen;
    }
}

// ── WebP parser ──────────────────────────────────────────────────────
function parse_webp(string $data, array &$result): void {
    $pos = 12; // RIFF header
    while ($pos + 8 < strlen($data)) {
        $chunkId = substr($data, $pos, 4);
        $chunkSize = unpack('V', substr($data, $pos + 4, 4))[1];
        $chunkData = substr($data, $pos + 8, $chunkSize);

        if ($chunkId === 'VP8 ' && strlen($chunkData) >= 10) {
            $result['width'] = unpack('v', substr($chunkData, 6, 2))[1] & 0x3FFF;
            $result['height'] = unpack('v', substr($chunkData, 8, 2))[1] & 0x3FFF;
            $result['bit_depth'] = 8;
            $result['color_type'] = 'YCbCr (lossy)';
        }
        if ($chunkId === 'VP8L' && strlen($chunkData) >= 5) {
            $bits = unpack('V', substr($chunkData, 1, 4))[1];
            $result['width'] = ($bits & 0x3FFF) + 1;
            $result['height'] = (($bits >> 14) & 0x3FFF) + 1;
            $result['bit_depth'] = 8;
            $result['color_type'] = 'RGBA (lossless)';
        }
        if ($chunkId === 'EXIF' && $chunkSize > 0) {
            $result['xmp']['EXIF'] = 'Present (' . $chunkSize . ' bytes)';
        }
        if ($chunkId === 'XMP ' && $chunkSize > 0) {
            parse_xmp($chunkData, $result['xmp']);
        }

        $pos += 8 + $chunkSize;
        if ($chunkSize % 2 !== 0) $pos++; // padding
    }
}

// ── TIFF parser (for standalone TIFF files) ──────────────────────────
function parse_tiff(string $data, array &$result, string $tmpPath): void {
    if (function_exists('exif_read_data')) {
        $tmpFile = $tmpPath . '.exif.tiff';
        @file_put_contents($tmpFile, $data);
        $exif = @exif_read_data($tmpFile, 0, true);
        @unlink($tmpFile);
        if ($exif !== false) {
            if (isset($exif['IFD0'])) {
                foreach ($exif['IFD0'] as $tag => $val) {
                    if (is_string($val) || is_numeric($val)) $result['exif'][$tag] = $val;
                }
            }
            if (isset($exif['EXIF'])) {
                foreach ($exif['EXIF'] as $tag => $val) {
                    if (is_string($val) || is_numeric($val)) $result['exif'][$tag] = $val;
                }
            }
            if (isset($exif['GPS'])) $result['gps'] = parse_gps($exif['GPS']);
        }
    }
    if (function_exists('getimagesizefromstring')) {
        $info = @getimagesizefromstring($data);
        if ($info) {
            $result['width'] = $info[0];
            $result['height'] = $info[1];
        }
    }
}

// ── GPS from EXIF array ──────────────────────────────────────────────
function parse_gps(array $gps): ?array {
    $lat = $lon = null;
    if (isset($gps['GPSLatitude']) && isset($gps['GPSLatitudeRef'])) {
        $lat = dms_to_decimal($gps['GPSLatitude'], $gps['GPSLatitudeRef']);
    }
    if (isset($gps['GPSLongitude']) && isset($gps['GPSLongitudeRef'])) {
        $lon = dms_to_decimal($gps['GPSLongitude'], $gps['GPSLongitudeRef']);
    }
    if ($lat === null || $lon === null) return null;
    $result = ['lat' => $lat, 'lon' => $lon];
    if (isset($gps['GPSAltitude'])) {
        $alt = is_array($gps['GPSAltitude']) ? $gps['GPSAltitude'][0] / max(1, $gps['GPSAltitude'][1]) : (float)$gps['GPSAltitude'];
        if (isset($gps['GPSAltitudeRef']) && $gps['GPSAltitudeRef'] > 0) $alt = -$alt;
        $result['alt'] = $alt;
    }
    return $result;
}

function dms_to_decimal(array $dms, string $ref): ?float {
    if (count($dms) < 3) return null;
    $d = is_array($dms[0]) ? $dms[0][0] / max(1, $dms[0][1]) : (float)$dms[0];
    $m = is_array($dms[1]) ? $dms[1][0] / max(1, $dms[1][1]) : (float)$dms[1];
    $s = is_array($dms[2]) ? $dms[2][0] / max(1, $dms[2][1]) : (float)$dms[2];
    $dec = $d + ($m / 60) + ($s / 3600);
    if (strtoupper($ref) === 'S' || strtoupper($ref) === 'W') $dec = -$dec;
    return $dec;
}

// ── EXIF binary parser (for when exif extension isn't available) ─────
function parse_exif_tiff(string $data, array &$exif, ?array &$gps): void {
    if (strlen($data) < 8) return;
    $ii = substr($data, 0, 2) === 'II'; // Intel byte order
    $readU16 = fn($o) => $ii ? unpack('v', substr($data, $o, 2))[1] : unpack('n', substr($data, $o, 2))[1];
    $readU32 = fn($o) => $ii ? unpack('V', substr($data, $o, 4))[1] : unpack('N', substr($data, $o, 4))[1];
    $readVal = function($type, $offset) use ($data, $ii, $readU16, $readU32) {
        switch ($type) {
            case 1: return ord($data[$offset]); // BYTE
            case 2: // ASCII
                $end = strpos($data, "\0", $offset);
                return substr($data, $offset, $end !== false ? $end - $offset : 10);
            case 3: return $readU16($offset); // SHORT
            case 4: return $readU32($offset); // LONG
            case 5: // RATIONAL
                $n = $readU32($offset);
                $d = $readU32($offset + 4);
                return $d > 0 ? [$n, $d] : $n;
            case 10: // SRATIONAL
                $n = $ii ? unpack('V', substr($data, $offset, 4))[1] : unpack('N', substr($data, $offset, 4))[1];
                $d = $ii ? unpack('V', substr($data, $offset + 4, 4))[1] : unpack('N', substr($data, $offset + 4, 4))[1];
                return $d > 0 ? [$n, $d] : $n;
            default: return null;
        }
    };

    $tagNames = [
        0x010F => 'Make', 0x0110 => 'Model', 0x0112 => 'Orientation',
        0x0131 => 'Software', 0x0132 => 'DateTime', 0x013B => 'Artist',
        0x8298 => 'Copyright', 0x829A => 'ExposureTime', 0x829D => 'FNumber',
        0x8827 => 'ISOSpeedRatings', 0x9003 => 'DateTimeOriginal',
        0x9004 => 'DateTimeDigitized', 0x9201 => 'ShutterSpeedValue',
        0x9202 => 'ApertureValue', 0x920A => 'FocalLength',
        0xA001 => 'ColorSpace', 0xA002 => 'PixelXDimension', 0xA003 => 'PixelYDimension',
        0xA405 => 'FocalLengthIn35mmFilm', 0xA420 => 'ImageUniqueID',
        0xA430 => 'CameraOwnerName', 0xA431 => 'BodySerialNumber',
        0xA433 => 'LensMake', 0xA434 => 'LensModel',
        0xA460 => 'WhiteBalance', 0xA461 => 'BrightnessValue',
        0xA462 => 'ExposureBiasValue', 0xA465 => 'LensSerialNumber',
    ];

    $gpsTags = [
        0x0001 => 'GPSLatitudeRef', 0x0002 => 'GPSLatitude',
        0x0003 => 'GPSLongitudeRef', 0x0004 => 'GPSLongitude',
        0x0005 => 'GPSAltitudeRef', 0x0006 => 'GPSAltitude',
        0x0007 => 'GPSTimeStamp', 0x001D => 'GPSDateStamp',
    ];

    // Find IFD0
    $ifd0Offset = $readU32(4);
    if ($ifd0Offset + 2 > strlen($data)) return;
    $numEntries = $readU16($ifd0Offset);
    $gpsIfdOffset = null;
    $exifIfdOffset = null;

    for ($i = 0; $i < min($numEntries, 200); $i++) {
        $entryOff = $ifd0Offset + 2 + ($i * 12);
        if ($entryOff + 12 > strlen($data)) break;
        $tag = $readU16($entryOff);
        $type = $readU16($entryOff + 2);
        $count = $readU32($entryOff + 4);
        $valOff = $entryOff + 8;

        $valSize = [0, 1, 1, 2, 4, 8, 1, 2, 4, 8, 4, 8][$type] ?? 1;
        $totalSize = $valSize * $count;
        $dataOffset = $totalSize > 4 ? $readU32($valOff) : $valOff;

        if ($tag === 0x8825) { $gpsIfdOffset = $readU32($valOff); continue; }
        if ($tag === 0x8769) { $exifIfdOffset = $readU32($valOff); continue; }

        if (isset($tagNames[$tag]) && $dataOffset + $totalSize <= strlen($data)) {
            $v = $readVal($type, $dataOffset);
            if ($v !== null) $exif[$tagNames[$tag]] = rational_to_string($tagNames[$tag], $v);
        }
    }

    // GPS IFD
    if ($gpsIfdOffset !== null && $gpsIfdOffset + 2 <= strlen($data)) {
        $gpsData = [];
        $numGps = $readU16($gpsIfdOffset);
        for ($i = 0; $i < min($numGps, 50); $i++) {
            $e = $gpsIfdOffset + 2 + ($i * 12);
            if ($e + 12 > strlen($data)) break;
            $tag = $readU16($e);
            $type = $readU16($e + 2);
            $count = $readU32($e + 4);
            $dataOff = $type === 5 ? $readU32($e + 8) : $e + 8;
            if (isset($gpsTags[$tag]) && $dataOff + ($count * ($type === 5 ? 8 : 1)) <= strlen($data)) {
                $gpsData[$gpsTags[$tag]] = $readVal($type, $dataOff);
            }
        }
        if (!empty($gpsData)) $gps = parse_gps($gpsData);
    }
}

function rational_to_string(string $tag, $val): string {
    if (!is_array($val)) return (string)$val;
    $n = $val[0]; $d = $val[1];
    if ($d === 0) return (string)$n;
    if (in_array($tag, ['ExposureTime'])) {
        return $n >= $d ? (string)round($n / $d) . 's' : '1/' . round($d / $n);
    }
    if (in_array($tag, ['FNumber', 'ApertureValue'])) {
        return 'f/' . round($n / $d, 1);
    }
    if ($tag === 'FocalLength') {
        return round($n / $d, 1) . ' mm';
    }
    if ($tag === 'ShutterSpeedValue') {
        $v = pow(2, $n / $d);
        return $v >= 1 ? round($v) . 's' : '1/' . round(1 / $v);
    }
    return round($n / $d, 2);
}

// ── IPTC from APP13 / Photoshop IRB ─────────────────────────────────
function parse_iptc_from_app13(string $app13, array &$iptc): void {
    $pos = 0;
    $len = strlen($app13);
    while ($pos + 5 < $len) {
        if (substr($app13, $pos, 13) === 'Photoshop 3.0') {
            $pos += 13;
            while ($pos + 4 < $len && ord($app13[$pos]) !== 0x1C) $pos++;
        }
        if ($pos + 5 >= $len) break;
        if (ord($app13[$pos]) !== 0x1C) break;
        $rec = ord($app13[$pos + 1]);
        $tag = ord($app13[$pos + 2]);
        $dataLen = unpack('n', substr($app13, $pos + 3, 2))[1];
        $pos += 5;
        if ($rec === 1 && $pos + $dataLen <= $len) {
            $val = substr($app13, $pos, $dataLen);
            $iptcNames = [
                0x5A => 'Record Version', 0x00 => 'Object Type',
                0x05 => 'Title', 0x0A => 'Urgency', 0x0C => 'Category',
                0x14 => 'Supplemental Category', 0x19 => 'Fixture Identifier',
                0x50 => 'Copyright', 0x5D => 'Instructions',
                0x5F => 'Creator', 0x60 => 'City', 0x65 => 'Country',
                0x67 => 'Original Transmission Reference',
                0x6C => 'Headline', 0x73 => 'Credit', 0x76 => 'Source',
                0x78 => 'Writer/Editor',
            ];
            $name = $iptcNames[$tag] ?? "Record $rec, Tag 0x" . bin2hex(chr($tag));
            $iptc[$name] = trim($val);
        }
        $pos += $dataLen;
    }
}

// ── XMP parser (lightweight) ────────────────────────────────────────
function parse_xmp(string $xml, array &$xmp): void {
    // Simple regex extraction for common XMP fields
    $patterns = [
        'dc:creator'        => '/<dc:creator[^>]*>(.*?)<\/dc:creator>/s',
        'dc:title'          => '/<dc:title[^>]*>(.*?)<\/dc:title>/s',
        'dc:description'    => '/<dc:description[^>]*>(.*?)<\/dc:description>/s',
        'dc:subject'        => '/<dc:subject[^>]*>(.*?)<\/dc:subject>/s',
        'xmp:CreateDate'    => '/<xmp:CreateDate[^>]*>(.*?)<\/xmp:CreateDate>/s',
        'xmp:ModifyDate'    => '/<xmp:ModifyDate[^>]*>(.*?)<\/xmp:ModifyDate>/s',
        'xmp:CreatorTool'   => '/<xmp:CreatorTool[^>]*>(.*?)<\/xmp:CreatorTool>/s',
        'xmp:Rating'        => '/<xmp:Rating[^>]*>(.*?)<\/xmp:Rating>/s',
        'tiff:Make'         => '/<tiff:Make[^>]*>(.*?)<\/tiff:Make>/s',
        'tiff:Model'        => '/<tiff:Model[^>]*>(.*?)<\/tiff:Model>/s',
        'aux:LensInfo'      => '/<aux:LensInfo[^>]*>(.*?)<\/aux:LensInfo>/s',
        'aux:Lens'          => '/<aux:Lens[^>]*>(.*?)<\/aux:Lens>/s',
        'aux:LensMake'      => '/<aux:LensMake[^>]*>(.*?)<\/aux:LensMake>/s',
        'aux:SerialNumber'  => '/<aux:SerialNumber[^>]*>(.*?)<\/aux:SerialNumber>/s',
        'aux:ImageNumber'   => '/<aux:ImageNumber[^>]*>(.*?)<\/aux:ImageNumber>/s',
        'photoshop:Credit'  => '/<photoshop:Credit[^>]*>(.*?)<\/photoshop:Credit>/s',
        'photoshop:City'    => '/<photoshop:City[^>]*>(.*?)<\/photoshop:City>/s',
        'photoshop:State'   => '/<photoshop:State[^>]*>(.*?)<\/photoshop:State>/s',
        'photoshop:Country' => '/<photoshop:Country[^>]*>(.*?)<\/photoshop:Country>/s',
    ];

    foreach ($patterns as $key => $pat) {
        if (preg_match($pat, $xml, $m)) {
            $val = trim(strip_tags($m[1]));
            if ($val !== '') $xmp[$key] = $val;
        }
    }

    // Also grab any rdf:li values
    if (preg_match_all('/<rdf:li[^>]*>(.*?)<\/rdf:li>/s', $xml, $matches)) {
        $items = array_map(fn($s) => trim(strip_tags($s)), $matches[1]);
        $items = array_filter($items);
        if (!empty($items)) {
            $xmp['rdf:li_items'] = implode('; ', array_slice($items, 0, 20));
        }
    }
}
