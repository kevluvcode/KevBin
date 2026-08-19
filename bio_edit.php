<?php
require_once __DIR__ . '/functions.php';

start_session();
$cfg = $GLOBALS['CFG'];
$me = current_user();
$manageKey = trim((string)($_GET['m'] ?? ''));

function bio_manage_ok(array $bio, ?array $me, ?string $key): bool
{
    return link_manage_ok($bio, $me, $key);
}

const BIO_BG_KEYS = ['aurora', 'sunset', 'ocean', 'mint', 'candy', 'night'];

function bio_bg_label(string $key): string
{
    $l = ['aurora' => 'Aurora', 'sunset' => 'Sunset', 'ocean' => 'Ocean', 'mint' => 'Mint', 'candy' => 'Candy', 'night' => 'Night'];
    return $l[$key] ?? $key;
}

function bio_fetch_by_slug(string $slug): ?array
{
    $stmt = db()->prepare('SELECT id, slug, display_name, bio_text, avatar_url, background, accent, buttons, clicks, created_at, user_id, manage_key FROM bios WHERE slug = ?');
    $stmt->execute([$slug]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function bio_decode_buttons(?string $json): array
{
    if ($json === null) {
        return [];
    }
    $arr = json_decode($json, true);
    if (!is_array($arr)) {
        return [];
    }
    $out = [];
    foreach ($arr as $b) {
        if (is_array($b) && isset($b['label'], $b['url'])) {
            $out[] = ['label' => (string)$b['label'], 'url' => (string)$b['url']];
        }
    }
    return $out;
}

// ---- actions ---------------------------------------------------------

$error = null;
$flash = (string)($_GET['ok'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify_or_fail();

    // Delete.
    if (!empty($_POST['del_slug'])) {
        $ds = (string)$_POST['del_slug'];
        if (preg_match('/^[A-Za-z0-9_-]{3,40}$/', $ds)) {
            $row = bio_fetch_by_slug($ds);
            if ($row && bio_manage_ok($row, $me, $manageKey)) {
                db()->prepare('DELETE FROM bios WHERE id = ?')->execute([(int)$row['id']]);
                log_activity('bio_delete', $ds);
                flash_set('success', 'Bio deleted.');
            }
        }
        redirect('bio_edit.php' . ($manageKey !== '' ? '?m=' . urlencode($manageKey) : ''));
    }

    // Save / create.
    if (!rate_limit_check('bio', 10, 600)) {
        friendly_error('Rate limit reached. Wait a moment.', 429);
    }

    $id = (int)($_POST['id'] ?? 0);
    $name = mb_substr(trim((string)($_POST['display_name'] ?? '')), 0, 80);
    $slug = strtolower(trim((string)($_POST['slug'] ?? '')));
    $bioText = mb_substr(trim((string)($_POST['bio_text'] ?? '')), 0, 1000);
    $avatar = mb_substr(trim((string)($_POST['avatar_url'] ?? '')), 0, 500);
    $bg = (string)($_POST['background'] ?? 'aurora');
    if (!in_array($bg, BIO_BG_KEYS, true)) {
        $bg = 'aurora';
    }
    $accent = (string)($_POST['accent'] ?? '#5865f2');
    if (!preg_match('/^#[0-9a-fA-F]{6}$/', $accent)) {
        $accent = '#5865f2';
    }

    // Buttons: pair label[] + url[] rows, drop empties, cap at 12.
    $btnLabels = is_array($_POST['btn_label'] ?? null) ? $_POST['btn_label'] : [];
    $btnUrls = is_array($_POST['btn_url'] ?? null) ? $_POST['btn_url'] : [];
    $buttons = [];
    $btnError = null;
    for ($i = 0, $n = max(count($btnLabels), count($btnUrls)); $i < $n; $i++) {
        $label = mb_substr(trim((string)($btnLabels[$i] ?? '')), 0, 80);
        $url = trim((string)($btnUrls[$i] ?? ''));
        if ($label === '' && $url === '') {
            continue;
        }
        if (strpbrk($url, "\r\n\0") !== false) {
            $btnError = 'Button URL contains invalid characters.';
            break;
        }
        if (!preg_match('#^https?://#i', $url)) {
            $btnError = 'Button URLs must start with http:// or https://';
            break;
        }
        if (mb_strlen($url) > 2048) {
            $btnError = 'A button URL is too long (max 2048 chars).';
            break;
        }
        $buttons[] = ['label' => $label !== '' ? $label : 'Visit', 'url' => $url];
        if (count($buttons) >= 12) {
            break;
        }
    }
    if ($btnError !== null) {
        $error = $btnError;
    }

    if ($name === '') {
        $error = 'Enter a display name.';
    } elseif (!preg_match('/^[A-Za-z0-9_-]{3,40}$/', $slug)) {
        $error = 'Custom URL must be 3–40 characters of letters, numbers, dash or underscore.';
    } elseif ($avatar !== '' && !preg_match('#^https?://#i', $avatar)) {
        $error = 'Avatar must be an http(s) image URL (or leave it blank).';
    }

    if ($error === null) {
        $pdo = db();
        // Slug uniqueness (excluding this bio when editing).
        $stmt = $pdo->prepare('SELECT id FROM bios WHERE slug = ? AND id <> ?');
        $stmt->execute([$slug, $id]);
        if ($stmt->fetch()) {
            $error = 'That custom URL is already taken. Try another one.';
        } else {
            $json = json_encode($buttons, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if ($id > 0) {
                $stmt = $pdo->prepare('SELECT id, user_id, manage_key FROM bios WHERE id = ?');
                $stmt->execute([$id]);
                $existing = $stmt->fetch();
                if (!$existing || !bio_manage_ok($existing, $me, $manageKey)) {
                    friendly_error('You do not have permission to edit this bio.', 403);
                }
                $pdo->prepare('UPDATE bios SET slug = ?, display_name = ?, bio_text = ?, avatar_url = ?, background = ?, accent = ?, buttons = ? WHERE id = ?')
                    ->execute([$slug, $name, $bioText !== '' ? $bioText : null, $avatar !== '' ? $avatar : null, $bg, $accent, $json, $id]);
                log_activity('bio_update', $slug);
            } else {
                $key = $me !== null ? null : bin2hex(random_bytes(16));
                $pdo->prepare('INSERT INTO bios (slug, user_id, manage_key, display_name, bio_text, avatar_url, background, accent, buttons) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)')
                    ->execute([$slug, $me !== null ? (int)$me['id'] : null, $key, $name, $bioText !== '' ? $bioText : null, $avatar !== '' ? $avatar : null, $bg, $accent, $json]);
                log_activity('bio_create', $slug);
                if ($me === null && $key !== null) {
                    $manageKey = $key;
                }
            }
            flash_set('success', 'Bio saved.');
            redirect('bio_edit.php?slug=' . urlencode($slug) . ($me === null && $manageKey !== '' ? '&m=' . urlencode($manageKey) : ''));
        }
    }
}

// ---- data for render -------------------------------------------------

$myBios = [];
if ($me !== null) {
    try {
        $myBios = db()->query(
            'SELECT slug, display_name, clicks, last_click, created_at FROM bios WHERE user_id = ' . (int)$me['id'] . ' ORDER BY updated_at DESC LIMIT 50'
        )->fetchAll();
    } catch (Throwable $t) {
        $myBios = [];
    }
} elseif ($manageKey !== '') {
    $stmt = db()->prepare('SELECT slug, display_name, clicks, last_click, created_at FROM bios WHERE manage_key = ? ORDER BY updated_at DESC LIMIT 50');
    $stmt->execute([$manageKey]);
    $myBios = $stmt->fetchAll();
}

$edit = null;
$editSlug = trim((string)($_GET['slug'] ?? ''));
if ($editSlug !== '' && preg_match('/^[A-Za-z0-9_-]{3,40}$/', $editSlug)) {
    $cand = bio_fetch_by_slug($editSlug);
    if ($cand && bio_manage_ok($cand, $me, $manageKey)) {
        $edit = $cand;
    }
}

$form = $edit !== null
    ? [
        'id' => (int)$edit['id'],
        'name' => (string)$edit['display_name'],
        'slug' => (string)$edit['slug'],
        'bio_text' => (string)($edit['bio_text'] ?? ''),
        'avatar' => (string)($edit['avatar_url'] ?? ''),
        'bg' => in_array((string)($edit['background'] ?? ''), BIO_BG_KEYS, true) ? $edit['background'] : 'aurora',
        'accent' => preg_match('/^#[0-9a-fA-F]{6}$/', (string)($edit['accent'] ?? '')) ? $edit['accent'] : '#5865f2',
        'buttons' => bio_decode_buttons($edit['buttons']),
    ]
    : ['id' => 0, 'name' => '', 'slug' => '', 'bio_text' => '', 'avatar' => '', 'bg' => 'aurora', 'accent' => '#5865f2', 'buttons' => []];

page_header('My bios');
?>
<style>
.bio-chip { display:inline-block; width:64px; height:34px; border-radius:9px; border:2px solid transparent; cursor:pointer; vertical-align:middle; margin:2px 4px 2px 0; }
.bio-chip.sel { border-color:#fff; }
.swatch { display:inline-block; width:22px; height:22px; border-radius:50%; border:2px solid transparent; cursor:pointer; margin:2px 4px 2px 0; }
.swatch.sel { border-color:#fff; }
.btn-rows .btn-row { margin-bottom:.55rem; }
</style>
<div class="container" style="max-width: 1100px;">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <h1 class="h4 mb-0 reveal in-view">👤 My bios</h1>
        <a class="btn btn-primary btn-sm reveal in-view" href="<?= e(url('bio_edit.php')) ?>">+ New bio page</a>
    </div>

    <?php if ($me === null && $manageKey === ''): ?>
        <div class="card reveal">
            <div class="card-body text-center py-5">
                <h2 class="h5 mb-3">Make your own guns.lol-style bio page</h2>
                <p class="text-secondary mb-4">Get a custom URL like <code>/b/yourname</code> with your display name,
                bio text, avatar and a stack of link buttons. Works without an account — you get a private manage
                link. Or <a class="text-info" href="login.php">log in</a> so your bios live in your account.</p>
                <div class="d-flex gap-2 justify-content-center flex-wrap">
                    <a class="btn btn-primary" href="login.php">Log in</a>
                    <a class="btn btn-outline-light" href="register.php">Register</a>
                    <a class="btn btn-outline-light" href="bio_edit.php">Start building now</a>
                </div>
            </div>
        </div>
    <?php else: ?>
        <div class="card reveal">
            <div class="card-body">
                <h2 class="h6 mb-3"><?= $me !== null ? 'Your bio pages' : 'Bio pages (manage key)' ?></h2>
                <?php if ($manageKey !== '' && $me === null): ?>
                    <div class="alert alert-secondary small">Anonymous manage mode — bios created under your private manage key.</div>
                <?php endif; ?>
                <?php if (count($myBios) === 0): ?>
                    <p class="text-secondary small mb-0">No bio pages yet — create your first one below.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-dark table-hover align-middle">
                            <thead><tr><th>Name</th><th>URL</th><th>Views</th><th>Last view</th><th></th></tr></thead>
                            <tbody>
                            <?php foreach ($myBios as $b): ?>
                                <tr>
                                    <td class="small"><?= e($b['display_name']) ?></td>
                                    <td><a href="<?= e(url('bio.php?u=' . $b['slug'])) ?>"><code>/b/<?= e($b['slug']) ?></code></a></td>
                                    <td><?= (int)$b['clicks'] ?></td>
                                    <td class="small"><?= $b['last_click'] ? e(gmdate('Y-m-d H:i', strtotime($b['last_click'] . ' UTC'))) . ' UTC' : '—' ?></td>
                                    <td class="text-end text-nowrap">
                                        <a class="btn btn-sm btn-outline-light" href="<?= e(url('bio_edit.php?slug=' . $b['slug'] . ($manageKey !== '' ? '&m=' . urlencode($manageKey) : ''))) ?>">Edit</a>
                                        <a class="btn btn-sm btn-outline-light" href="<?= e(url('bio.php?u=' . $b['slug'])) ?>" target="_blank" rel="noopener">View</a>
                                        <form method="post" action="bio_edit.php<?= $manageKey !== '' ? '?m=' . urlencode($manageKey) : '' ?>" class="d-inline" onsubmit="return confirm('Delete this bio page?');">
                                            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                                            <input type="hidden" name="del_slug" value="<?= e($b['slug']) ?>">
                                            <button class="btn btn-sm btn-outline-danger">Delete</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>

    <div class="card mt-4 reveal">
        <div class="card-body">
            <h2 class="h6 mb-3"><?= $form['id'] > 0 ? 'Edit bio page' : 'New bio page' ?></h2>

            <?php if ($error !== null): ?>
                <div class="alert alert-danger py-2"><?= e($error) ?></div>
            <?php endif; ?>

            <form method="post" action="bio_edit.php<?= $manageKey !== '' ? '?m=' . urlencode($manageKey) : '' ?>">
                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="id" value="<?= (int)$form['id'] ?>">

                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label class="form-label small text-secondary mb-1">Display name *</label>
                        <input class="form-control" name="display_name" maxlength="80" value="<?= e($form['name']) ?>" required placeholder="Your name / username">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small text-secondary mb-1">Custom URL (slug) *</label>
                        <div class="input-group">
                            <span class="input-group-text" id="slug-pre"><?= e(rtrim((string)$cfg['base_url'], '/')) ?>/b/</span>
                            <input class="form-control" name="slug" maxlength="40" value="<?= e($form['slug']) ?>" required placeholder="yourname" pattern="[A-Za-z0-9_-]{3,40}">
                        </div>
                        <div class="form-text">3–40 characters: letters, numbers, dash, underscore.</div>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label small text-secondary mb-1">Bio text</label>
                    <textarea class="form-control" name="bio_text" maxlength="1000" rows="4" placeholder="Short intro, links welcome, emojis welcome..."><?= e($form['bio_text']) ?></textarea>
                </div>

                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label class="form-label small text-secondary mb-1">Avatar image URL</label>
                        <input class="form-control" name="avatar_url" maxlength="500" value="<?= e($form['avatar']) ?>" placeholder="https://example.com/me.png">
                        <div class="form-text">Leave blank to show your initials instead.</div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small text-secondary mb-1">Accent color</label>
                        <div>
                            <input type="color" class="form-control form-control-color d-inline-block" name="accent" id="accent-in" value="<?= e($form['accent']) ?>" style="width:48px;height:34px;vertical-align:middle">
                            <span id="accent-out" class="small text-secondary ms-1"><?= e($form['accent']) ?></span>
                        </div>
                        <div class="mt-1" id="swatches"></div>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label small text-secondary mb-1">Background</label>
                    <div id="bg-chips">
                        <?php foreach (BIO_BG_KEYS as $bk): ?>
                            <label title="<?= e(bio_bg_label($bk)) ?>" style="display:inline-block;margin-right:6px;">
                                <input type="radio" name="background" value="<?= e($bk) ?>" class="d-none"<?= $form['bg'] === $bk ? ' checked' : '' ?>>
                                <span class="bio-chip<?= $form['bg'] === $bk ? ' sel' : '' ?>" style="background:linear-gradient(150deg,#0b0e17,#11162b 45%,#1b1f38)"></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                    <div class="form-text">Choose one of six gradient backgrounds.</div>
                </div>

                <div class="mb-3">
                    <label class="form-label small text-secondary mb-1">Link buttons</label>
                    <div class="btn-rows" id="btn-rows">
                        <?php foreach ($form['buttons'] as $b): ?>
                            <div class="row g-2 btn-row">
                                <div class="col-5"><input class="form-control" name="btn_label[]" maxlength="80" value="<?= e($b['label']) ?>" placeholder="Label"></div>
                                <div class="col-5"><input class="form-control" name="btn_url[]" maxlength="2048" value="<?= e($b['url']) ?>" placeholder="https://..."></div>
                                <div class="col-2"><button type="button" class="btn btn-sm btn-outline-danger w-100 btn-del">✕</button></div>
                            </div>
                        <?php endforeach; ?>
                        <div class="row g-2 btn-row" id="btn-empty"></div>
                    </div>
                    <button type="button" class="btn btn-sm btn-outline-light mt-2" id="btn-add">+ Add button</button>
                </div>

                <div class="d-flex gap-2">
                    <button class="btn btn-primary" type="submit"><?= $form['id'] > 0 ? 'Save changes' : 'Create bio page' ?></button>
                    <a class="btn btn-outline-light" href="<?= e(url('bio_edit.php' . ($manageKey !== '' ? '?m=' . urlencode($manageKey) : ''))) ?>">Cancel</a>
                    <?php if ($form['id'] > 0): ?>
                        <a class="btn btn-outline-light" href="<?= e(url('bio.php?u=' . $form['slug'])) ?>" target="_blank" rel="noopener">Preview</a>
                    <?php endif; ?>
                </div>

                <?php if ($form['id'] > 0): ?>
                    <div class="alert alert-secondary small mt-3 mb-0">
                        Your page URL: <a href="<?= e(url('bio.php?u=' . $form['slug'])) ?>"><code><?= e(url('b/' . $form['slug'])) ?></code></a>
                    </div>
                <?php endif; ?>
            </form>
        </div>
    </div>
</div>
<script>
(function(){
    var accentIn=document.getElementById('accent-in');
    var accentOut=document.getElementById('accent-out');
    var swatches=['#5865f2','#2ecc71','#e74c3c','#f1c40f','#9b59b6','#ff7ac6','#1abc9c','#e67e22'];
    var wrap=document.getElementById('swatches');
    swatches.forEach(function(c){
        var s=document.createElement('span');
        s.className='swatch'+(c===accentIn.value?' sel':'');
        s.style.background=c;
        s.title=c;
        s.onclick=function(){ accentIn.value=c; accentOut.textContent=c; document.querySelectorAll('.swatch').forEach(function(x){x.classList.remove('sel');}); s.classList.add('sel'); };
        wrap.appendChild(s);
    });
    accentIn.addEventListener('input',function(){ accentOut.textContent=accentIn.value; document.querySelectorAll('.swatch').forEach(function(x){x.classList.remove('sel');}); });

    var chips=document.querySelectorAll('#bg-chips input[type=radio]');
    chips.forEach(function(r){ r.addEventListener('change',function(){ document.querySelectorAll('.bio-chip').forEach(function(c){c.classList.remove('sel');}); if(r.checked) r.nextElementSibling.classList.add('sel'); }); });

    function rowHTML(label,url){
        var d=document.createElement('div');
        d.className='row g-2 btn-row';
        d.innerHTML='<div class="col-5"><input class="form-control" name="btn_label[]" maxlength="80" placeholder="Label" value="'+(label||'')+'"></div>'+
                    '<div class="col-5"><input class="form-control" name="btn_url[]" maxlength="2048" placeholder="https://..." value="'+(url||'')+'"></div>'+
                    '<div class="col-2"><button type="button" class="btn btn-sm btn-outline-danger w-100 btn-del">✕</button></div>';
        return d;
    }
    document.getElementById('btn-add').addEventListener('click',function(){
        var host=document.getElementById('btn-rows');
        host.insertBefore(rowHTML('',''), document.getElementById('btn-empty'));
    });
    document.getElementById('btn-rows').addEventListener('click',function(e){
        if(e.target.classList.contains('btn-del')){
            var row=e.target.closest('.btn-row');
            if(row && row.id!=='btn-empty') row.remove();
        }
    });
})();
</script>
<?php page_footer(); ?>