<?php
require_once __DIR__ . '/functions.php';

start_session();

$preview = !empty($_GET['preview']);

function bio_initials(string $name): string
{
    $words = preg_split('/\s+/', trim($name), -1, PREG_SPLIT_NO_EMPTY) ?: [];
    $init = '';
    foreach (array_slice($words, 0, 2) as $w) {
        $init .= mb_strtoupper(mb_substr($w, 0, 1));
    }
    return $init !== '' ? $init : '?';
}

// Builds the full <style> block from a validated style array.
function bio_style_css(array $s): string
{
    $accent = $s['accent'];
    $btnColor = $s['btn_color'] !== '' ? $s['btn_color'] : $accent;
    switch ($s['btn_style']) {
        case 'solid':
            $btnBase = "background:{$btnColor};border:1px solid {$btnColor};color:{$s['btn_text_color']};";
            $btnHover = "background:{$btnColor}cc;transform:translateY(-1px);box-shadow:0 6px 18px rgba(0,0,0,.35);";
            break;
        case 'outline':
            $btnBase = "background:transparent;border:2px solid {$btnColor};color:{$btnColor};";
            $btnHover = "background:{$btnColor}22;transform:translateY(-1px);box-shadow:0 6px 18px rgba(0,0,0,.25);";
            break;
        default:
            $btnBase = "background:{$btnColor}1a;border:1px solid {$btnColor}66;color:{$s['btn_text_color']};";
            $btnHover = "background:{$btnColor}33;transform:translateY(-1px);box-shadow:0 6px 18px rgba(0,0,0,.35);";
    }
    $shape = ['circle' => '50%', 'rounded' => '18px', 'square' => '10px'][$s['avatar_shape']] ?? '50%';
    $borderColor = $s['avatar_border_color'] !== '' ? $s['avatar_border_color'] : $accent;
    $glow = $s['avatar_glow'] ? '0 0 0 6px rgba(255,255,255,.06), 0 8px 30px rgba(0,0,0,.45)' : '0 8px 30px rgba(0,0,0,.45)';
    $radius = (int)$s['btn_radius'] . 'px';
    $nameSize = round($s['name_size'], 2) . 'rem';
    $bioSize = round($s['bio_size'], 2) . 'rem';
    $btnSize = round($s['btn_size'], 2) . 'rem';
    $gap = round($s['btn_gap'], 2) . 'rem';
    $width = (int)$s['card_width'] . 'px';
    $align = $s['align'] === 'left' ? 'left' : 'center';

    switch ($s['bg_type']) {
        case 'gradient':
            $bg = 'linear-gradient(' . (int)$s['bg_angle'] . 'deg,' . $s['bg_grad1'] . ',' . $s['bg_grad2'] . ')';
            break;
        case 'solid':
            $bg = $s['bg_solid'];
            break;
        case 'image':
            $ov = round($s['bg_overlay'], 2);
            $bg = "linear-gradient(rgba(5,7,13,{$ov}), rgba(5,7,13,{$ov})), url(\"" . addslashes($s['bg_image']) . "\") center / cover no-repeat fixed";
            break;
        default:
            $presets = bio_bg_presets();
            $bg = $presets[$s['bg_preset']]['css'] ?? $presets['aurora']['css'];
    }

    if ($s['name_gradient']) {
        $nameColor = "background:linear-gradient(90deg,{$s['name_color']},{$accent});-webkit-background-clip:text;background-clip:text;-webkit-text-fill-color:transparent;";
    } else {
        $nameColor = "color:{$s['name_color']};";
    }

    return <<<CSS
* { box-sizing:border-box; margin:0; padding:0; }
body { min-height:100vh; background:{$bg}; color:#eef1f8; font-family:system-ui,-apple-system,"Segoe UI",Roboto,Helvetica,Arial,sans-serif; display:grid; place-items:center; padding:2rem 1rem; }
.bio-card { width:100%; max-width:{$width}; text-align:{$align}; }
.avatar { width:108px; height:108px; border-radius:{$shape}; object-fit:cover; border:{$s['avatar_border']}px solid {$borderColor}; box-shadow:{$glow}; margin:0 auto 1.1rem; display:block; }
.avatar-fallback { width:108px; height:108px; border-radius:{$shape}; display:grid; place-items:center; background:{$accent}; color:#0b0e17; font-size:2.4rem; font-weight:800; margin:0 auto 1.1rem; box-shadow:{$glow}; }
.name { font-size:{$nameSize}; font-weight:800; letter-spacing:.2px; {$nameColor} margin-bottom:.6rem; }
.bio-text { font-size:{$bioSize}; line-height:1.6; color:{$s['bio_color']}; margin:0 auto 1.6rem; max-width:420px; text-align:{$align}; }
.btns { display:flex; flex-direction:column; gap:{$gap}; }
.btn-link { display:block; text-decoration:none; padding:.85rem 1rem; border-radius:{$radius}; {$btnBase} font-weight:600; font-size:{$btnSize}; text-align:{$align}; transition:background .18s ease, transform .15s ease, box-shadow .18s ease; }
.btn-link:hover { {$btnHover} }
.foot { margin-top:2.2rem; font-size:.72rem; color:rgba(255,255,255,.38); }
.foot a { color:rgba(255,255,255,.55); text-decoration:none; }
.pv-banner { position:fixed; top:0; left:0; right:0; z-index:999; background:#f0b400; color:#0b0e17; font-size:.78rem; font-weight:700; padding:.35rem; text-align:center; }
CSS;
}

if ($preview) {
    // Live preview from the bio editor: no DB writes, no view bump, noindex.
    $name = mb_substr(trim((string)($_POST['display_name'] ?? '')), 0, 80);
    $bioTextRaw = mb_substr(trim((string)($_POST['bio_text'] ?? '')), 0, 1000);
    $avatar = trim((string)($_POST['avatar_url'] ?? ''));
    if ($avatar !== '' && !preg_match('#^https?://#i', $avatar)) {
        $avatar = '';
    }
    $s = bio_style_defaults();
    $sj = json_decode((string)($_POST['style'] ?? ''), true);
    if (is_array($sj)) {
        $s = bio_style_clean($sj);
    }
    $buttons = [];
    $bj = json_decode((string)($_POST['buttons'] ?? ''), true);
    if (is_array($bj)) {
        foreach ($bj as $b) {
            if (is_array($b) && isset($b['label'], $b['url'])
                && trim((string)$b['url']) !== ''
                && preg_match('#^https?://#i', (string)$b['url'])) {
                $buttons[] = ['label' => mb_substr((string)$b['label'], 0, 80), 'url' => (string)$b['url']];
            }
        }
    }
    header('Content-Type: text/html; charset=utf-8');
} else {
    $slug = trim((string)($_GET['u'] ?? ''));
    if (!preg_match('/^[A-Za-z0-9_-]{3,40}$/', $slug)) {
        friendly_error('Bio not found.', 404);
    }

    $stmt = db()->prepare('SELECT id, display_name, bio_text, avatar_url, background, accent, buttons, clicks, style FROM bios WHERE slug = ?');
    $stmt->execute([$slug]);
    $bio = $stmt->fetch();
    if (!$bio) {
        friendly_error('Bio not found.', 404);
    }

    // Bump the view counter (best-effort, non-fatal).
    try {
        db()->prepare('UPDATE bios SET clicks = clicks + 1, last_click = UTC_TIMESTAMP() WHERE id = ?')->execute([(int)$bio['id']]);
    } catch (Throwable $t) {
        error_log('[bio.php] ' . $t->getMessage());
    }

    $name = (string)$bio['display_name'];
    $bioTextRaw = $bio['bio_text'] !== null ? (string)$bio['bio_text'] : '';
    $avatar = (string)($bio['avatar_url'] ?? '');
    if ($avatar !== '' && !preg_match('#^https?://#i', $avatar)) {
        $avatar = '';
    }
    $s = bio_style_from_row($bio);
    $buttons = [];
    if ($bio['buttons'] !== null) {
        $arr = json_decode((string)$bio['buttons'], true);
        if (is_array($arr)) {
            foreach ($arr as $b) {
                if (is_array($b) && isset($b['label'], $b['url'])
                    && trim((string)$b['url']) !== ''
                    && preg_match('#^https?://#i', (string)$b['url'])) {
                    $buttons[] = ['label' => mb_substr((string)$b['label'], 0, 80), 'url' => (string)$b['url']];
                }
            }
        }
    }
}

$bioText = $bioTextRaw !== ''
    ? nl2br(e($bioTextRaw))
    : '';
$desc = mb_substr(trim(preg_replace('/\s+/', ' ', strip_tags($bioTextRaw))), 0, 160);
$css = bio_style_css($s);
$footerLine = $s['footer_text'] !== '' ? ' · ' . e($s['footer_text']) : '';
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<?php if ($preview): ?><meta name="robots" content="noindex,nofollow"><?php else: ?>
<meta name="description" content="<?= e($desc) ?>">
<meta property="og:title" content="<?= e($name) ?>">
<meta property="og:description" content="<?= e($desc) ?>">
<?php if ($avatar !== ''): ?><meta property="og:image" content="<?= e($avatar) ?>"><?php endif; ?>
<?php endif; ?>
<title><?= e($name) ?></title>
<style><?= $css ?></style>
</head>
<body>
<?php if ($preview): ?><div class="pv-banner">LIVE PREVIEW — this isn't saved yet</div><?php endif; ?>
<main class="bio-card">
    <?php if ($avatar !== ''): ?>
        <img class="avatar" src="<?= e($avatar) ?>" alt="" loading="lazy" referrerpolicy="no-referrer">
    <?php else: ?>
        <div class="avatar-fallback"><?= e(bio_initials($name)) ?></div>
    <?php endif; ?>

    <h1 class="name"><?= e($name) ?></h1>

    <?php if ($bioText !== ''): ?>
        <div class="bio-text"><?= $bioText ?></div>
    <?php endif; ?>

    <?php if (count($buttons) > 0): ?>
        <div class="btns">
            <?php foreach ($buttons as $b): ?>
                <a class="btn-link" href="<?= e($b['url']) ?>" target="_blank" rel="noopener"><?= e($b['label']) ?></a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</main>
<footer class="foot">Made with <a href="<?= e((string)($GLOBALS['CFG']['base_url'] ?? '#')) ?>" rel="noopener">KevBin</a><?= $footerLine ?></footer>
<?php if (!is_admin()): ?>
<script>
// Anti-DevTools + anti-debugger: any sign the console/DevTools/debugger opened reloads the page.
(function () {
    var triggered = false;
    function nuke() { if (triggered) return; triggered = true; try { location.reload(); } catch (e) {} }
    function stop(e) { e.preventDefault(); e.stopPropagation(); nuke(); return false; }
    document.addEventListener('keydown', function (e) {
        var k = e.keyCode || e.which, cm = e.ctrlKey || e.metaKey;
        if (k === 123) { stop(e); return; }
        if (cm && e.shiftKey && (k === 73 || k === 74 || k === 67 || k === 75 || k === 69)) { stop(e); return; }
        if (cm && (k === 85 || k === 83)) { stop(e); return; }
    }, true);
    document.addEventListener('contextmenu', function (e) { e.preventDefault(); }, true);
    function sizeCheck() { try { if (window.outerWidth - window.innerWidth > 160 || window.outerHeight - window.innerHeight > 160) nuke(); } catch (e) {} }
    sizeCheck();
    window.addEventListener('resize', sizeCheck);
    setInterval(sizeCheck, 500);
    setInterval(function () { var t = new Date().getTime(); debugger; if (new Date().getTime() - t > 100) nuke(); }, 700);
    setInterval(function () { try { var c = window.console || {}, t0 = new Date().getTime(); c.log('%c', ''); if (new Date().getTime() - t0 > 50) nuke(); } catch (e) {} }, 1500);
})();
</script>
<?php endif; ?>
</body>
</html>
