<?php
require_once __DIR__ . '/functions.php';

start_session();
send_security_headers();
if (isset($_GET['rot'])) {
    captcha_issue(true);
}
$code = captcha_current();

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

$w = 160;
$h = 56;

if (function_exists('imagecreatetruecolor') && function_exists('imagerotate')) {
    // ——— GD render: bright gradient + wave distortion + rotated chars + light noise ———
    header('Content-Type: image/png');
    $img = imagecreatetruecolor($w, $h);

    // Bright vertical gradient (soft pastel base, easy to read against).
    for ($y = 0; $y < $h; $y++) {
        $t = $y / $h;
        $r = (int)(235 + 12 * $t);
        $g = (int)(238 + 6 * $t);
        $b = (int)(248 - 8 * $t);
        imageline($img, 0, $y, $w, $y, imagecolorallocate($img, $r, $g, $b));
    }

    // Layer 1: thin, light crossing lines (barely visible).
    for ($i = 0; $i < 4; $i++) {
        $c = imagecolorallocate($img, random_int(120, 150), random_int(140, 170), random_int(180, 210));
        imagesetthickness($img, 1);
        imageline($img, random_int(0, $w), random_int(0, $h), random_int(0, $w), random_int(0, $h), $c);
    }

    // Layer 2: two gentle pastel "wavy" strokes.
    for ($pass = 0; $pass < 2; $pass++) {
        $c = imagecolorallocate($img, random_int(150, 185), random_int(170, 200), random_int(205, 235));
        $phase = random_int(0, 628) / 100;
        $yBase = random_int(14, $h - 14);
        $prevX = 0;
        $prevY = $yBase + (int)(4 * sin($phase));
        for ($x = 4; $x <= $w; $x += 4) {
            $y = $yBase + (int)(4 * sin($x / 12 + $phase));
            imageline($img, $prevX, $prevY, $x, $y, $c);
            $prevX = $x;
            $prevY = $y;
        }
    }

    // Layer 3: one or two light arcs.
    for ($i = 0; $i < 2; $i++) {
        $c = imagecolorallocate($img, random_int(160, 195), random_int(175, 205), random_int(205, 235));
        imagearc($img, random_int(0, $w), random_int(0, $h), random_int(40, 100), random_int(40, 100),
            random_int(0, 360), random_int(0, 360), $c);
    }

    // Layer 4: sparse, light pixel noise.
    for ($i = 0; $i < 110; $i++) {
        $c = imagecolorallocate($img, random_int(130, 180), random_int(150, 195), random_int(190, 230));
        imagesetpixel($img, random_int(0, $w - 1), random_int(0, $h - 1), $c);
    }
    for ($i = 0; $i < 4; $i++) {
        $c = imagecolorallocate($img, random_int(160, 200), random_int(175, 210), random_int(205, 235));
        imagefilledrectangle($img, random_int(0, $w - 1), random_int(0, $h - 1),
            random_int(0, $w - 1), random_int(0, $h - 1), $c);
    }
    imagesetthickness($img, 1);

    // Characters: each drawn on its own tile, rotated slightly, then stamped.
    // Dark, bold chars on the bright background so they stay legible.
    $palette = [
        [35, 55, 90], [90, 45, 55], [45, 90, 60], [80, 60, 110], [140, 90, 35],
    ];
    $tile = imagecreatetruecolor(34, 44);
    $trans = imagecolorallocate($tile, 0, 0, 0);
    imagecolortransparent($tile, $trans);
    $count = strlen($code);
    $step = (int)floor(($w - 22) / $count);
    $x = 11;
    for ($i = 0; $i < $count; $i++) {
        imagealphablending($tile, false);
        imagefilledrectangle($tile, 0, 0, 34, 44, $trans);
        // subtle light shadow first
        $sh = imagecolorallocate($tile, 230, 233, 244);
        imagestring($tile, 5, 4 + random_int(-1, 1), 10 + random_int(-1, 1), $code[$i], $sh);
        $pc = $palette[random_int(0, count($palette) - 1)];
        $fc = imagecolorallocate($tile, $pc[0], $pc[1], $pc[2]);
        imagestring($tile, 5, 2 + random_int(-2, 2), 8 + random_int(-2, 2), $code[$i], $fc);
        $rot = imagerotate($tile, random_int(-13, 13), $trans);
        $tw = imagesx($rot);
        $th2 = imagesy($rot);
        $y = random_int(6, 12);
        imagecopy($img, $rot, $x, $y, 0, 0, $tw, $th2);
        $x += $step;
    }
    imagedestroy($tile);

    // Final pass: gentle horizontal sine-wave distortion (kept small for readability).
    $wavy = imagecreatetruecolor($w, $h);
    $phase = random_int(0, 628) / 100;
    for ($y = 0; $y < $h; $y++) {
        $dx = (int)(3 * sin($y / 8 + $phase));
        imagecopy($wavy, $img, $dx, $y, 0, $y, $w, 1);
    }
    imagepng($wavy);
    imagedestroy($img);
    imagedestroy($wavy);
} else {
    // ——— dependency-free SVG fallback: bright bg, dark rotated chars, light waves ———
    header('Content-Type: image/svg+xml');
    $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="' . $w . '" height="' . $h . '" viewBox="0 0 ' . $w . ' ' . $h . '">'
        . '<defs><linearGradient id="bg" x1="0" y1="0" x2="0" y2="1">'
        . '<stop offset="0" stop-color="#eef0fa"/><stop offset="1" stop-color="#e8ecf6"/></linearGradient></defs>'
        . '<rect width="' . $w . '" height="' . $h . '" fill="url(#bg)"/>';
    for ($i = 0; $i < 5; $i++) {
        $svg .= '<line x1="' . random_int(0, $w) . '" y1="' . random_int(0, $h) . '" x2="' . random_int(0, $w)
            . '" y2="' . random_int(0, $h) . '" stroke="#' . sprintf('%02x', random_int(130, 160))
            . sprintf('%02x', random_int(150, 180)) . sprintf('%02x', random_int(200, 235)) . '" stroke-width="1"/>';
    }
    for ($i = 0; $i < 2; $i++) {
        $d = 'M 0 ' . random_int(12, $h - 12) . ' Q ' . ($w / 2) . ' ' . random_int(4, $h - 10) . ' ' . $w . ' ' . random_int(12, $h - 12);
        $svg .= '<path d="' . $d . '" fill="none" stroke="#c8d2ee" stroke-width="1"/>';
    }
    $palette = ['#23375a', '#5a2d37', '#2d5a3c', '#503c6e', '#8c5a23'];
    $count = strlen($code);
    $step = (int)floor(($w - 22) / $count);
    $x = 11;
    for ($i = 0; $i < $count; $i++) {
        $rot = random_int(-13, 13);
        $cy = random_int(36, 46);
        $svg .= '<text x="' . $x . '" y="' . $cy . '" font-family="monospace" font-size="' . random_int(24, 28)
            . '" font-weight="bold" fill="' . $palette[random_int(0, count($palette) - 1)]
            . '" transform="rotate(' . $rot . ' ' . $x . ' ' . $cy . ')">'
            . htmlspecialchars($code[$i], ENT_QUOTES, 'UTF-8') . '</text>';
        $x += $step;
    }
    for ($i = 0; $i < 2; $i++) {
        $svg .= '<circle cx="' . random_int(0, $w) . '" cy="' . random_int(0, $h) . '" r="' . random_int(16, 42)
            . '" fill="none" stroke="#c4ceec" stroke-width="1"/>';
    }
    echo $svg . '</svg>';
}