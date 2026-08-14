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

$w = 150;
$h = 52;

if (function_exists('imagecreatetruecolor')) {
    // ——— GD render: noise, wavy lines, random char positions ———
    header('Content-Type: image/png');
    $img = imagecreatetruecolor($w, $h);
    imagefilledrectangle($img, 0, 0, $w, $h, imagecolorallocate($img, 12, 12, 12));
    for ($i = 0; $i < 5; $i++) {
        $c = imagecolorallocate($img, random_int(40, 90), random_int(40, 90), random_int(60, 110));
        imageline($img, random_int(0, $w), random_int(0, $h), random_int(0, $w), random_int(0, $h), $c);
    }
    for ($i = 0; $i < 140; $i++) {
        $c = imagecolorallocate($img, random_int(50, 160), random_int(50, 160), random_int(50, 160));
        imagesetpixel($img, random_int(0, $w - 1), random_int(0, $h - 1), $c);
    }
    $x = 14;
    $y = 24;
    for ($i = 0; $i < strlen($code); $i++) {
        $c = imagecolorallocate($img, random_int(180, 255), random_int(180, 255), random_int(180, 255));
        imagestring($img, 5, $x + random_int(-2, 2), $y + random_int(-2, 2), $code[$i], $c);
        $x += 27;
    }
    imagepng($img);
    imagedestroy($img);
} else {
    // ——— dependency-free SVG fallback: scattered, rotated characters ———
    header('Content-Type: image/svg+xml');
    $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="' . $w . '" height="' . $h . '" viewBox="0 0 ' . $w . ' ' . $h . '">'
        . '<rect width="' . $w . '" height="' . $h . '" fill="#0c0c0c"/>';
    for ($i = 0; $i < 5; $i++) {
        $svg .= '<line x1="' . random_int(0, $w) . '" y1="' . random_int(0, $h) . '" x2="' . random_int(0, $w)
            . '" y2="' . random_int(0, $h) . '" stroke="#333" stroke-width="1"/>';
    }
    $x = 12;
    $y = 36;
    for ($i = 0; $i < strlen($code); $i++) {
        $rot = random_int(-10, 10);
        $cy = $y + random_int(-2, 2);
        $svg .= '<text x="' . $x . '" y="' . $cy . '" font-family="monospace" font-size="24" font-weight="bold"'
            . ' fill="#e6e6e6" transform="rotate(' . $rot . ' ' . $x . ' ' . $cy . ')">' . htmlspecialchars($code[$i], ENT_QUOTES, 'UTF-8') . '</text>';
        $x += 30;
    }
    echo $svg . '</svg>';
}