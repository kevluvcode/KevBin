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

function bio_fetch_by_slug(string $slug): ?array
{
    $stmt = db()->prepare('SELECT id, slug, display_name, bio_text, avatar_url, background, accent, buttons, clicks, created_at, user_id, manage_key, style FROM bios WHERE slug = ?');
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

function bio_style_from_post(array $post): array
{
    $in = [];
    foreach ($post as $k => $v) {
        if (is_string($k) && strpos($k, 'st_') === 0) {
            $in[substr($k, 3)] = $v;
        }
    }
    $in['name_gradient'] = !empty($post['st_name_gradient']);
    $in['avatar_glow'] = !empty($post['st_avatar_glow']);
    $in['btn_color'] = !empty($post['st_btn_color_auto']) ? '' : (string)($in['btn_color'] ?? '');
    $in['avatar_border_color'] = !empty($post['st_avatar_border_color_auto']) ? '' : (string)($in['avatar_border_color'] ?? '');
    return bio_style_clean($in);
}

// ---- actions ---------------------------------------------------------

$error = null;

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
    $style = bio_style_from_post($_POST);

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
        $error = 'Custom URL must be 3â€“40 characters of letters, numbers, dash or underscore.';
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
            $styleJson = json_encode($style, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $bg = $style['bg_preset'];
            $accent = $style['accent'];
            if ($id > 0) {
                $stmt = $pdo->prepare('SELECT id, user_id, manage_key FROM bios WHERE id = ?');
                $stmt->execute([$id]);
                $existing = $stmt->fetch();
                if (!$existing || !bio_manage_ok($existing, $me, $manageKey)) {
                    friendly_error('You do not have permission to edit this bio.', 403);
                }
                $pdo->prepare('UPDATE bios SET slug = ?, display_name = ?, bio_text = ?, avatar_url = ?, background = ?, accent = ?, buttons = ?, style = ? WHERE id = ?')
                    ->execute([$slug, $name, $bioText !== '' ? $bioText : null, $avatar !== '' ? $avatar : null, $bg, $accent, $json, $styleJson, $id]);
                log_activity('bio_update', $slug);
            } else {
                $key = $me !== null ? null : bin2hex(random_bytes(16));
                $pdo->prepare('INSERT INTO bios (slug, user_id, manage_key, display_name, bio_text, avatar_url, background, accent, buttons, style) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)')
                    ->execute([$slug, $me !== null ? (int)$me['id'] : null, $key, $name, $bioText !== '' ? $bioText : null, $avatar !== '' ? $avatar : null, $bg, $accent, $json, $styleJson]);
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
        'buttons' => bio_decode_buttons($edit['buttons']),
    ]
    : ['id' => 0, 'name' => '', 'slug' => '', 'bio_text' => '', 'avatar' => '', 'buttons' => []];

$s = $edit !== null ? bio_style_from_row($edit) : bio_style_defaults();
if ($error !== null && !empty($_POST)) {
    $s = bio_style_from_post($_POST);
}

$presets = bio_bg_presets();

page_header('My bios');
?>
<style>
.bio-chip { display:inline-block; width:64px; height:34px; border-radius:9px; border:2px solid transparent; cursor:pointer; vertical-align:middle; margin:2px 4px 2px 0; }
.bio-chip.sel { border-color:#fff; }
.swatch { display:inline-block; width:22px; height:22px; border-radius:50%; border:2px solid transparent; cursor:pointer; margin:2px 4px 2px 0; }
.swatch.sel { border-color:#fff; }
.btn-rows .btn-row { margin-bottom:.55rem; }
.bio-seg label { cursor:pointer; }
.bio-seg input:checked + span { background:var(--bs-primary); color:#fff; }
.bio-seg span { display:inline-block; padding:.3rem .75rem; border:1px solid rgba(255,255,255,.15); border-radius:.5rem; margin-right:.35rem; font-size:.82rem; }
.bio-opt-head { font-size:.78rem; letter-spacing:.4px; text-transform:uppercase; color:var(--bs-secondary); font-weight:700; margin:1.25rem 0 .55rem; }
.pv-frame { width:100%; height:640px; border:1px solid rgba(255,255,255,.12); border-radius:.75rem; background:#0b0e17; }
@media (max-width: 1199px){ .pv-frame { height:480px; } }
</style>
<div class="container" style="max-width: 1440px;">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <h1 class="h4 mb-0 reveal in-view">ðŸ‘¤ My bios</h1>
        <a class="btn btn-primary btn-sm reveal in-view" href="<?= e(url('bio_edit.php')) ?>">+ New bio page</a>
    </div>

    <?php if ($me === null && $manageKey === ''): ?>
        <div class="card reveal">
            <div class="card-body text-center py-5">
                <h2 class="h5 mb-3">Make your own guns.lol-style bio page</h2>
                <p class="text-secondary mb-4">Get a custom URL like <code>/b/yourname</code> with your display name,
                bio text, avatar, link buttons and full styling â€” custom background, link colors, button shapes,
                avatar border and more. Works without an account â€” you get a private manage link. Or <a class="text-info" href="login.php">log in</a> so your bios live in your account.</p>
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
                    <div class="alert alert-secondary small">Anonymous manage mode â€” bios created under your private manage key.</div>
                <?php endif; ?>
                <?php if (count($myBios) === 0): ?>
                    <p class="text-secondary small mb-0">No bio pages yet â€” create your first one below.</p>
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
                                    <td class="small"><?= $b['last_click'] ? e(gmdate('Y-m-d H:i', strtotime($b['last_click'] . ' UTC'))) . ' UTC' : 'â€”' ?></td>
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

    <div class="row g-4 mt-1">
        <div class="col-xl-7">
            <div class="card reveal">
                <div class="card-body">
                    <h2 class="h6 mb-3"><?= $form['id'] > 0 ? 'Edit bio page' : 'New bio page' ?></h2>

                    <?php if ($error !== null): ?>
                        <div class="alert alert-danger py-2"><?= e($error) ?></div>
                    <?php endif; ?>

                    <form method="post" action="bio_edit.php<?= $manageKey !== '' ? '?m=' . urlencode($manageKey) : '' ?>" class="pv-sync">
                        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                        <input type="hidden" name="id" value="<?= (int)$form['id'] ?>">

                        <div class="row g-3 mb-2">
                            <div class="col-md-6">
                                <label class="form-label small text-secondary mb-1">Display name *</label>
                                <input class="form-control" name="display_name" maxlength="80" value="<?= e($form['name']) ?>" required placeholder="Your name / username">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small text-secondary mb-1">Custom URL (slug) *</label>
                                <div class="input-group">
                                    <span class="input-group-text"><?= e(rtrim((string)$cfg['base_url'], '/')) ?>/b/</span>
                                    <input class="form-control" name="slug" maxlength="40" value="<?= e($form['slug']) ?>" required placeholder="yourname" pattern="[A-Za-z0-9_-]{3,40}">
                                </div>
                                <div class="form-text">3â€“40 characters: letters, numbers, dash, underscore.</div>
                            </div>
                        </div>

                        <div class="mb-2">
                            <label class="form-label small text-secondary mb-1">Bio text</label>
                            <textarea class="form-control" name="bio_text" maxlength="1000" rows="3" placeholder="Short intro, links welcome, emojis welcome..."><?= e($form['bio_text']) ?></textarea>
                        </div>

                        <div class="row g-3 mb-2">
                            <div class="col-md-6">
                                <label class="form-label small text-secondary mb-1">Avatar image URL</label>
                                <input class="form-control" name="avatar_url" maxlength="500" value="<?= e($form['avatar']) ?>" placeholder="https://example.com/me.png">
                                <div class="form-text">Leave blank to show your initials instead.</div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-2">
                                    <label class="form-label small text-secondary mb-1">Avatar shape</label>
                                    <select class="form-select" name="st_avatar_shape">
                                        <option value="circle"<?= $s['avatar_shape'] === 'circle' ? ' selected' : '' ?>>Circle</option>
                                        <option value="rounded"<?= $s['avatar_shape'] === 'rounded' ? ' selected' : '' ?>>Rounded square</option>
                                        <option value="square"<?= $s['avatar_shape'] === 'square' ? ' selected' : '' ?>>Square</option>
                                    </select>
                                </div>
                                <div class="row g-2">
                                    <div class="col-7">
                                        <label class="form-label small text-secondary mb-1">Border width (px)</label>
                                        <input type="range" class="form-range" name="st_avatar_border" min="0" max="12" step="1" value="<?= (int)$s['avatar_border'] ?>">
                                    </div>
                                    <div class="col-5">
                                        <label class="form-label small text-secondary mb-1">Border color</label>
                                        <div class="d-flex align-items-center gap-2">
                                            <input type="color" class="form-control form-control-color" name="st_avatar_border_color" style="width:44px;height:32px;" value="<?= e($s['avatar_border_color'] !== '' ? $s['avatar_border_color'] : '#ffffff') ?>">
                                            <span class="form-check mb-0">
                                                <input class="form-check-input" type="checkbox" name="st_avatar_border_color_auto" id="ab-auto"<?= $s['avatar_border_color'] === '' ? ' checked' : '' ?>>
                                                <label class="form-check-label small" for="ab-auto">accent</label>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                                <div class="form-check mt-2">
                                    <input class="form-check-input" type="checkbox" name="st_avatar_glow" id="av-glow"<?= $s['avatar_glow'] ? ' checked' : '' ?>>
                                    <label class="form-check-label small" for="av-glow">Glow ring around avatar</label>
                                </div>
                            </div>
                        </div>

                        <div class="bio-opt-head">Background</div>
                        <div class="bio-seg mb-2">
                            <label><input type="radio" name="st_bg_type" value="preset" class="d-none"<?= $s['bg_type'] === 'preset' ? ' checked' : '' ?>><span>Preset</span></label>
                            <label><input type="radio" name="st_bg_type" value="gradient" class="d-none"<?= $s['bg_type'] === 'gradient' ? ' checked' : '' ?>><span>Gradient</span></label>
                            <label><input type="radio" name="st_bg_type" value="solid" class="d-none"<?= $s['bg_type'] === 'solid' ? ' checked' : '' ?>><span>Solid</span></label>
                            <label><input type="radio" name="st_bg_type" value="image" class="d-none"<?= $s['bg_type'] === 'image' ? ' checked' : '' ?>><span>Image</span></label>
                        </div>

                        <div id="bg-preset-wrap">
                            <label class="form-label small text-secondary mb-1">Choose a gradient</label>
                            <div>
                                <?php foreach ($presets as $pk => $pv): ?>
                                    <label title="<?= e($pv['label']) ?>" style="display:inline-block;margin-right:6px;">
                                        <input type="radio" name="st_bg_preset" value="<?= e($pk) ?>" class="d-none"<?= $s['bg_preset'] === $pk ? ' checked' : '' ?>>
                                        <span class="bio-chip<?= $s['bg_preset'] === $pk ? ' sel' : '' ?>" style="background:<?= $pv['css'] ?>"></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <div id="bg-grad-wrap" class="row g-3 <?= $s['bg_type'] === 'gradient' ? '' : 'd-none' ?>">
                            <div class="col-4"><label class="form-label small text-secondary mb-1">Color 1</label><input type="color" class="form-control form-control-color w-100" name="st_bg_grad1" style="height:36px;" value="<?= e($s['bg_grad1']) ?>"></div>
                            <div class="col-4"><label class="form-label small text-secondary mb-1">Color 2</label><input type="color" class="form-control form-control-color w-100" name="st_bg_grad2" style="height:36px;" value="<?= e($s['bg_grad2']) ?>"></div>
                            <div class="col-4">
                                <label class="form-label small text-secondary mb-1">Angle: <span id="bg-angle-out"><?= (int)$s['bg_angle'] ?>Â°</span></label>
                                <input type="range" class="form-range" name="st_bg_angle" min="0" max="360" step="5" value="<?= (int)$s['bg_angle'] ?>">
                            </div>
                        </div>

                        <div id="bg-solid-wrap" class="<?= $s['bg_type'] === 'solid' ? '' : 'd-none' ?>">
                            <label class="form-label small text-secondary mb-1">Solid color</label>
                            <input type="color" class="form-control form-control-color" name="st_bg_solid" style="width:64px;height:34px;" value="<?= e($s['bg_solid']) ?>">
                        </div>

                        <div id="bg-image-wrap" class="<?= $s['bg_type'] === 'image' ? '' : 'd-none' ?>">
                            <div class="mb-2">
                                <label class="form-label small text-secondary mb-1">Image URL (covers the whole page)</label>
                                <input class="form-control" name="st_bg_image" maxlength="500" value="<?= e($s['bg_image']) ?>" placeholder="https://example.com/wallpaper.jpg">
                            </div>
                            <label class="form-label small text-secondary mb-1">Dark overlay: <span id="bg-overlay-out"><?= round($s['bg_overlay'] * 100) ?>%</span></label>
                            <input type="range" class="form-range" name="st_bg_overlay" min="0" max="0.9" step="0.05" value="<?= $s['bg_overlay'] ?>">
                        </div>

                        <div class="bio-opt-head">Colors</div>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label small text-secondary mb-1">Accent color</label>
                                <div class="d-flex align-items-center gap-2">
                                    <input type="color" class="form-control form-control-color" name="st_accent" id="accent-in" style="width:48px;height:34px;" value="<?= e($s['accent']) ?>">
                                    <span id="accent-out" class="small text-secondary"><?= e($s['accent']) ?></span>
                                </div>
                                <div class="mt-1" id="swatches"></div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-2">
                                    <label class="form-label small text-secondary mb-1">Name text color</label>
                                    <div class="d-flex align-items-center gap-2">
                                        <input type="color" class="form-control form-control-color" name="st_name_color" style="width:44px;height:32px;" value="<?= e($s['name_color']) ?>">
                                        <span class="form-check mb-0">
                                            <input class="form-check-input" type="checkbox" name="st_name_gradient" id="name-grad"<?= $s['name_gradient'] ? ' checked' : '' ?>>
                                            <label class="form-check-label small" for="name-grad">fade into accent</label>
                                        </span>
                                    </div>
                                </div>
                                <label class="form-label small text-secondary mb-1">Name size: <span id="name-size-out"><?= $s['name_size'] ?>rem</span></label>
                                <input type="range" class="form-range" name="st_name_size" min="1.1" max="3" step="0.05" value="<?= $s['name_size'] ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small text-secondary mb-1">Bio text color</label>
                                <input type="color" class="form-control form-control-color" name="st_bio_color" style="width:48px;height:34px;" value="<?= e($s['bio_color']) ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small text-secondary mb-1">Bio text size: <span id="bio-size-out"><?= $s['bio_size'] ?>rem</span></label>
                                <input type="range" class="form-range" name="st_bio_size" min="0.8" max="1.4" step="0.02" value="<?= $s['bio_size'] ?>">
                            </div>
                            <div class="col-12">
                                <label class="form-label small text-secondary mb-1">Footer line (optional, under the link buttons)</label>
                                <input class="form-control" name="st_footer_text" maxlength="200" value="<?= e($s['footer_text']) ?>" placeholder="e.g. contact me on discord @kevin">
                            </div>
                        </div>

                        <div class="bio-opt-head">Link buttons</div>
                        <div class="row g-3 mb-2">
                            <div class="col-md-4">
                                <label class="form-label small text-secondary mb-1">Style</label>
                                <select class="form-select" name="st_btn_style">
                                    <option value="soft"<?= $s['btn_style'] === 'soft' ? ' selected' : '' ?>>Soft tint</option>
                                    <option value="solid"<?= $s['btn_style'] === 'solid' ? ' selected' : '' ?>>Solid</option>
                                    <option value="outline"<?= $s['btn_style'] === 'outline' ? ' selected' : '' ?>>Outline</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small text-secondary mb-1">Button color</label>
                                <div class="d-flex align-items-center gap-2">
                                    <input type="color" class="form-control form-control-color" name="st_btn_color" style="width:44px;height:32px;" value="<?= e($s['btn_color'] !== '' ? $s['btn_color'] : $s['accent']) ?>">
                                    <span class="form-check mb-0">
                                        <input class="form-check-input" type="checkbox" name="st_btn_color_auto" id="btn-auto"<?= $s['btn_color'] === '' ? ' checked' : '' ?>>
                                        <label class="form-check-label small" for="btn-auto">accent</label>
                                    </span>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small text-secondary mb-1">Button text color</label>
                                <input type="color" class="form-control form-control-color" name="st_btn_text_color" style="width:48px;height:34px;" value="<?= e($s['btn_text_color']) ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small text-secondary mb-1">Corner radius: <span id="btn-radius-out"><?= (int)$s['btn_radius'] ?>px</span></label>
                                <input type="range" class="form-range" name="st_btn_radius" min="0" max="40" step="1" value="<?= (int)$s['btn_radius'] ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small text-secondary mb-1">Button size: <span id="btn-size-out"><?= $s['btn_size'] ?>rem</span></label>
                                <input type="range" class="form-range" name="st_btn_size" min="0.8" max="1.2" step="0.02" value="<?= $s['btn_size'] ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small text-secondary mb-1">Spacing: <span id="btn-gap-out"><?= $s['btn_gap'] ?>rem</span></label>
                                <input type="range" class="form-range" name="st_btn_gap" min="0.25" max="1.5" step="0.05" value="<?= $s['btn_gap'] ?>">
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label small text-secondary mb-1">Your link buttons</label>
                            <div class="btn-rows" id="btn-rows">
                                <?php foreach ($form['buttons'] as $b): ?>
                                    <div class="row g-2 btn-row">
                                        <div class="col-5"><input class="form-control" name="btn_label[]" maxlength="80" value="<?= e($b['label']) ?>" placeholder="Label"></div>
                                        <div class="col-5"><input class="form-control" name="btn_url[]" maxlength="2048" value="<?= e($b['url']) ?>" placeholder="https://..."></div>
                                        <div class="col-2"><button type="button" class="btn btn-sm btn-outline-danger w-100 btn-del">âœ•</button></div>
                                    </div>
                                <?php endforeach; ?>
                                <div class="row g-2 btn-row" id="btn-empty"></div>
                            </div>
                            <button type="button" class="btn btn-sm btn-outline-light mt-2" id="btn-add">+ Add button</button>
                        </div>

                        <div class="bio-opt-head">Role badges</div>
                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" role="switch" name="st_show_badges" id="st-show-badges"<?= $s['show_badges'] ? ' checked' : '' ?>>
                                    <label class="form-check-label small" for="st-show-badges">Show site role badges (admin / moderator / premium)</label>
                                </div>
                                <div class="form-text small">Shown automatically when this bio belongs to your account. Guests see it too.</div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small text-secondary mb-1">Badge style</label>
                                <select class="form-select" name="st_badge_style">
                                    <option value="glassy"<?= $s['badge_style'] === 'glassy' ? ' selected' : '' ?>>Glassy</option>
                                    <option value="solid"<?= $s['badge_style'] === 'solid' ? ' selected' : '' ?>>Solid</option>
                                    <option value="outline"<?= $s['badge_style'] === 'outline' ? ' selected' : '' ?>>Outline</option>
                                </select>
                            </div>
                        </div>

                        <div class="bio-opt-head">Layout</div>
                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label class="form-label small text-secondary mb-1">Card width</label>
                                <select class="form-select" name="st_card_width">
                                    <?php foreach ([420, 460, 500, 540] as $w): ?>
                                        <option value="<?= $w ?>"<?= (int)$s['card_width'] === $w ? ' selected' : '' ?>><?= $w ?>px</option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small text-secondary mb-1">Text alignment</label>
                                <select class="form-select" name="st_align">
                                    <option value="center"<?= $s['align'] === 'center' ? ' selected' : '' ?>>Center</option>
                                    <option value="left"<?= $s['align'] === 'left' ? ' selected' : '' ?>>Left</option>
                                </select>
                            </div>
                        </div>

                        <div class="d-flex gap-2 flex-wrap">
                            <button class="btn btn-primary" type="submit"><?= $form['id'] > 0 ? 'Save changes' : 'Create bio page' ?></button>
                            <a class="btn btn-outline-light" href="<?= e(url('bio_edit.php' . ($manageKey !== '' ? '?m=' . urlencode($manageKey) : ''))) ?>">Cancel</a>
                            <?php if ($form['id'] > 0): ?>
                                <a class="btn btn-outline-light" href="<?= e(url('bio.php?u=' . $form['slug'])) ?>" target="_blank" rel="noopener">Open live page</a>
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

        <div class="col-xl-5">
            <div class="card reveal" style="position:sticky;top:1rem;">
                <div class="card-body">
                    <h2 class="h6 mb-2">Live preview <span class="text-secondary small fw-normal">(updates as you type)</span></h2>
                    <iframe class="pv-frame" name="pframe" title="Bio preview"></iframe>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Hidden preview form posted into the iframe -->
<form id="pv" action="<?= e(url('bio.php?preview=1')) ?>" method="post" target="pframe" class="d-none">
    <input type="hidden" name="display_name">
    <input type="hidden" name="bio_text">
    <input type="hidden" name="avatar_url">
    <input type="hidden" name="style">
    <input type="hidden" name="buttons">
</form>

<script>
(function(){
    // ---- accent swatches ----
    var accentIn = document.getElementById('accent-in');
    var accentOut = document.getElementById('accent-out');
    var swatches = ['#5865f2', '#2ecc71', '#e74c3c', '#f1c40f', '#9b59b6', '#ff7ac6', '#1abc9c', '#e67e22'];
    var wrap = document.getElementById('swatches');
    swatches.forEach(function (c) {
        var sp = document.createElement('span');
        sp.className = 'swatch' + (c.toLowerCase() === accentIn.value.toLowerCase() ? ' sel' : '');
        sp.style.background = c;
        sp.title = c;
        sp.onclick = function () {
            accentIn.value = c;
            accentOut.textContent = c;
            document.querySelectorAll('.swatch').forEach(function (x) { x.classList.remove('sel'); });
            sp.classList.add('sel');
            syncPreview();
        };
        wrap.appendChild(sp);
    });
    accentIn.addEventListener('input', function () { accentOut.textContent = accentIn.value; });

    // ---- background preset chips ----
    var chips = document.querySelectorAll('#bg-preset-wrap input[type=radio]');
    chips.forEach(function (r) {
        r.addEventListener('change', function () {
            document.querySelectorAll('#bg-preset-wrap .bio-chip').forEach(function (c) { c.classList.remove('sel'); });
            if (r.checked) r.nextElementSibling.classList.add('sel');
            setBgType('preset');
        });
    });

    // ---- background type toggles ----
    var segs = document.querySelectorAll('input[name=st_bg_type]');
    function setBgType(t) {
        document.getElementById('bg-preset-wrap').classList.toggle('d-none', t !== 'preset');
        document.getElementById('bg-grad-wrap').classList.toggle('d-none', t !== 'gradient');
        document.getElementById('bg-solid-wrap').classList.toggle('d-none', t !== 'solid');
        document.getElementById('bg-image-wrap').classList.toggle('d-none', t !== 'image');
    }
    segs.forEach(function (r) { r.addEventListener('change', function () { setBgType(r.value); }); });

    // ---- range readouts ----
    function bindOut(id, field, fmt) {
        var el = document.getElementById(id);
        var input = document.querySelector('[name=' + field + ']');
        if (!el || !input) return;
        el.textContent = fmt(input.value);
        input.addEventListener('input', function () { el.textContent = fmt(input.value); });
    }
    bindOut('bg-angle-out', 'st_bg_angle', function (v) { return v + 'Â°'; });
    bindOut('bg-overlay-out', 'st_bg_overlay', function (v) { return Math.round(parseFloat(v) * 100) + '%'; });
    bindOut('name-size-out', 'st_name_size', function (v) { return parseFloat(v).toFixed(2) + 'rem'; });
    bindOut('bio-size-out', 'st_bio_size', function (v) { return parseFloat(v).toFixed(2) + 'rem'; });
    bindOut('btn-radius-out', 'st_btn_radius', function (v) { return v + 'px'; });
    bindOut('btn-size-out', 'st_btn_size', function (v) { return parseFloat(v).toFixed(2) + 'rem'; });
    bindOut('btn-gap-out', 'st_btn_gap', function (v) { return parseFloat(v).toFixed(2) + 'rem'; });

    // ---- button rows ----
    function rowHTML(label, url) {
        var d = document.createElement('div');
        d.className = 'row g-2 btn-row';
        d.innerHTML = '<div class="col-5"><input class="form-control" name="btn_label[]" maxlength="80" placeholder="Label" value="' + (label || '') + '"></div>' +
            '<div class="col-5"><input class="form-control" name="btn_url[]" maxlength="2048" placeholder="https://..." value="' + (url || '') + '"></div>' +
            '<div class="col-2"><button type="button" class="btn btn-sm btn-outline-danger w-100 btn-del">âœ•</button></div>';
        return d;
    }
    document.getElementById('btn-add').addEventListener('click', function () {
        document.getElementById('btn-rows').insertBefore(rowHTML('', ''), document.getElementById('btn-empty'));
        syncPreview();
    });
    document.getElementById('btn-rows').addEventListener('click', function (e) {
        if (e.target.classList.contains('btn-del')) {
            var row = e.target.closest('.btn-row');
            if (row && row.id !== 'btn-empty') { row.remove(); syncPreview(); }
        }
    });

    // ---- live preview sync ----
    function currentStyle() {
        var s = {};
        document.querySelectorAll('[name^=st_]').forEach(function (el) {
            var name = el.name.slice(3);
            if (el.type === 'checkbox') { s[name] = el.checked; return; }
            if (el.type === 'number' || el.type === 'range') { s[name] = parseFloat(el.value); return; }
            s[name] = el.value;
        });
        if (document.getElementById('btn-auto').checked) s.btn_color = '';
        if (document.getElementById('ab-auto').checked) s.avatar_border_color = '';
        return s;
    }
    function currentButtons() {
        var out = [];
        document.querySelectorAll('#btn-rows .btn-row').forEach(function (row) {
            if (row.id === 'btn-empty') return;
            var label = row.querySelector('[name="btn_label[]"]').value.trim();
            var url = row.querySelector('[name="btn_url[]"]').value.trim();
            if (url) out.push({ label: label || 'Visit', url: url });
        });
        return out;
    }
    function syncPreview() {
        var pv = document.getElementById('pv');
        var main = document.querySelector('.pv-sync');
        if (!pv || !main) return;
        pv.querySelector('[name="display_name"]').value = main.querySelector('[name="display_name"]').value;
        pv.querySelector('[name="bio_text"]').value = main.querySelector('[name="bio_text"]').value;
        pv.querySelector('[name="avatar_url"]').value = main.querySelector('[name="avatar_url"]').value;
        pv.querySelector('[name="style"]').value = JSON.stringify(currentStyle());
        pv.querySelector('[name="buttons"]').value = JSON.stringify(currentButtons());
        pv.submit();
    }
    var pvTimer = null;
    document.addEventListener('input', function (e) {
        if (!e.target.closest('.pv-sync')) return;
        clearTimeout(pvTimer);
        pvTimer = setTimeout(syncPreview, 350);
    });
    document.addEventListener('change', function (e) {
        if (!e.target.closest('.pv-sync')) return;
        clearTimeout(pvTimer);
        pvTimer = setTimeout(syncPreview, 350);
    });

    window.syncPreview = syncPreview;
    window.addEventListener('load', function () { setTimeout(syncPreview, 150); });
})();
</script>
<?php page_footer(); ?>
