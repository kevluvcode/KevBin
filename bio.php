<?php
require_once __DIR__ . '/functions.php';

start_session();

$slug = trim((string)($_GET['u'] ?? ''));
if (!preg_match('/^[A-Za-z0-9_-]{3,40}$/', $slug)) {
    friendly_error('Bio not found.', 404);
}

$stmt = db()->prepare('SELECT id, display_name, bio_text, avatar_url, background, accent, buttons, clicks FROM bios WHERE slug = ?');
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

// Background presets.
function bio_bg(string $key): string
{
    $p = [
        'aurora' => 'linear-gradient(150deg,#0b0e17 0%,#11162b 45%,#1b1f38 100%)',
        'sunset' => 'linear-gradient(160deg,#1a0f24 0%,#3d1a4a 60%,#5c2240 100%)',
        'ocean'  => 'linear-gradient(160deg,#071a2c 0%,#0e3a5e 60%,#0d2b46 100%)',
        'mint'   => 'linear-gradient(160deg,#061f1a 0%,#0f3d33 60%,#0a2b24 100%)',
        'candy'  => 'linear-gradient(160deg,#2b0a2e 0%,#5c1654 60%,#3d1050 100%)',
        'night'  => 'linear-gradient(160deg,#05070d 0%,#101528 60%,#0a0f1e 100%)',
    ];
    return $p[$key] ?? $p['aurora'];
}

function bio_initials(string $name): string
{
    $words = preg_split('/\s+/', trim($name), -1, PREG_SPLIT_NO_EMPTY) ?: [];
    $init = '';
    foreach (array_slice($words, 0, 2) as $w) {
        $init .= mb_strtoupper(mb_substr($w, 0, 1));
    }
    return $init !== '' ? $init : '?';
}

$bg = bio_bg((string)$bio['background']);
$accent = preg_match('/^#[0-9a-fA-F]{6}$/', (string)$bio['accent']) ? (string)$bio['accent'] : '#5865f2';
$name = (string)$bio['display_name'];
$bioText = $bio['bio_text'] !== null && trim((string)$bio['bio_text']) !== ''
    ? nl2br(e(trim((string)$bio['bio_text'])))
    : '';
$avatar = (string)($bio['avatar_url'] ?? '');
if ($avatar !== '' && !preg_match('#^https?://#i', $avatar)) {
    $avatar = '';
}

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

$desc = mb_substr(trim(preg_replace('/\s+/', ' ', strip_tags((string)$bio['bio_text']))), 0, 160);
header('Content-Type: text/html; charset=utf-8');
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="description" content="<?= e($desc) ?>">
<meta property="og:title" content="<?= e($name) ?>">
<meta property="og:description" content="<?= e($desc) ?>">
<?php if ($avatar !== ''): ?><meta property="og:image" content="<?= e($avatar) ?>"><?php endif; ?>
<title><?= e($name) ?></title>
<style>
* { box-sizing:border-box; margin:0; padding:0; }
body {
    min-height:100vh;
    background: <?= $bg ?>;
    color:#eef1f8;
    font-family:system-ui,-apple-system,"Segoe UI",Roboto,Helvetica,Arial,sans-serif;
    display:grid;
    place-items:center;
    padding:2rem 1rem;
}
.bio-card { width:100%; max-width:480px; text-align:center; }
.avatar {
    width:108px; height:108px; border-radius:50%;
    object-fit:cover;
    border:3px solid <?= $accent ?>;
    box-shadow:0 0 0 6px rgba(255,255,255,.06), 0 8px 30px rgba(0,0,0,.45);
    margin:0 auto 1.1rem;
    display:block;
}
.avatar-fallback {
    width:108px; height:108px; border-radius:50%;
    display:grid; place-items:center;
    background:<?= $accent ?>;
    color:#0b0e17; font-size:2.4rem; font-weight:800;
    margin:0 auto 1.1rem;
    box-shadow:0 0 0 6px rgba(255,255,255,.06), 0 8px 30px rgba(0,0,0,.45);
}
.name {
    font-size:1.75rem; font-weight:800; letter-spacing:.2px;
    background:linear-gradient(90deg,#fff,<?= $accent ?>);
    -webkit-background-clip:text; background-clip:text; -webkit-text-fill-color:transparent;
    margin-bottom:.6rem;
}
.bio-text { font-size:.98rem; line-height:1.6; color:#c9d1e0; margin:0 auto 1.6rem; max-width:420px; }
.btns { display:flex; flex-direction:column; gap:.75rem; }
.btn-link {
    display:block; text-decoration:none;
    padding:.85rem 1rem; border-radius:14px;
    background:<?= $accent ?>1a;
    border:1px solid <?= $accent ?>66;
    color:#fff; font-weight:600; font-size:.95rem;
    transition:background .18s ease, transform .15s ease, box-shadow .18s ease;
}
.btn-link:hover { background:<?= $accent ?>33; transform:translateY(-1px); box-shadow:0 6px 18px rgba(0,0,0,.35); }
.foot { margin-top:2.2rem; font-size:.72rem; color:rgba(255,255,255,.38); }
.foot a { color:rgba(255,255,255,.55); text-decoration:none; }
</style>
</head>
<body>
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
<footer class="foot">Made with <a href="<?= e((string)($GLOBALS['CFG']['base_url'] ?? '#')) ?>" rel="noopener">KevBin</a></footer>
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
</body>
</html>