<?php
require_once __DIR__ . '/functions.php';

start_session();
require_login();

$u = current_user();
$gradPresets = [
    'Midnight'  => 'linear-gradient(135deg, #0f0c29, #302b63, #24243e)',
    'Sunset'    => 'linear-gradient(135deg, #2b1055, #7597de)',
    'Ember'     => 'linear-gradient(135deg, #1a0b2e, #5b0a2b)',
    'Ocean'     => 'linear-gradient(135deg, #0a1c3c, #0b5e7a)',
    'Matrix'    => 'linear-gradient(135deg, #041f14, #0d5c2e)',
    'Blood'     => 'linear-gradient(135deg, #200b0b, #721212)',
    'Gold'      => 'linear-gradient(135deg, #241b02, #7a5c00)',
    'Cyber'     => 'linear-gradient(135deg, #0a0a23, #3a1d6e)',
    'Storm'     => 'linear-gradient(135deg, #101828, #3b4a63)',
    'Rose'      => 'linear-gradient(135deg, #2a0f1e, #7a2348)',
    'Teal'      => 'linear-gradient(135deg, #062a2a, #0f6b5a)',
    'Grape'     => 'linear-gradient(135deg, #1d1138, #6b46c1)',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify_or_fail();

    if (isset($_POST['ack_key'])) {
        unset($_SESSION['pending_recovery_key']);
        flash_set('success', 'Recovery key acknowledged. It stays valid until you generate a new one.');
        redirect('settings.php');
    }

    if (!empty($_POST['new_recovery_key'])) {
        $_SESSION['pending_recovery_key'] = user_update_recovery_key((int)$u['id']);
        log_activity('recovery_key_generated', $u['username']);
        flash_set('success', 'New recovery key generated. Save it now — the old one no longer works.');
        redirect('settings.php#recovery');
    }

    if (!empty($_POST['change_password'])) {
        $current = (string)($_POST['current_password'] ?? '');
        $new = (string)($_POST['new_password'] ?? '');
        $confirm = (string)($_POST['confirm_password'] ?? '');
        $stmt = db()->prepare('SELECT password FROM users WHERE id = ?');
        $stmt->execute([(int)$u['id']]);
        $hash = (string)($stmt->fetchColumn() ?: '');
        if (!password_verify($current, $hash)) {
            flash_set('error', 'Current password is incorrect.');
            redirect('settings.php');
        }
        if (strlen($new) < 4) {
            flash_set('error', 'New password must be at least 4 characters.');
            redirect('settings.php');
        }
        if ($new !== $confirm) {
            flash_set('error', 'New passwords do not match.');
            redirect('settings.php');
        }
        db()->prepare('UPDATE users SET password = ? WHERE id = ?')
            ->execute([password_hash($new, PASSWORD_BCRYPT), (int)$u['id']]);
        session_regenerate_id(true);
        log_activity('password_change', $u['username']);
        flash_set('success', 'Password changed.');
        redirect('settings.php');
    }

    $cfg = $GLOBALS['CFG'];
    if (!rate_limit_check('profile', 10, (int)$cfg['rate_window_seconds'])) {
        friendly_error('Rate limit reached: max 10 profile edits per 10 minutes per IP.', 429);
    }

    $pfp = trim((string)($_POST['pfp'] ?? ''));
    $banner = trim((string)($_POST['banner'] ?? ''));
    $bgImage = trim((string)($_POST['bg_image'] ?? ''));
    $bgMode = in_array(($_POST['bg_mode'] ?? 'none'), ['none', 'image', 'gradient', 'color'], true) ? $_POST['bg_mode'] : 'none';
    $bgFit = ($_POST['bg_fit'] ?? 'cover') === 'repeat' ? 'repeat' : 'cover';
    $bgColor = clean_hex_color((string)($_POST['bg_color'] ?? ''));
    $bgGradient = trim((string)($_POST['bg_gradient'] ?? ''));
    $bgVeil = max(0, min(90, (int)($_POST['bg_veil'] ?? 55)));
    $bgBlur = !empty($_POST['bg_blur']) ? 1 : 0;

    $uiMode = in_array(($_POST['ui_mode'] ?? 'none'), ['none', 'color', 'gradient'], true) ? (string)$_POST['ui_mode'] : 'none';
    $uiColor = clean_hex_color((string)($_POST['ui_color'] ?? ''));
    $uiGradient = trim((string)($_POST['ui_gradient'] ?? ''));
    $uiLayout = in_array(($_POST['ui_layout'] ?? 'default'), ['default', 'compact', 'wide'], true) ? (string)$_POST['ui_layout'] : 'default';
    $uiAccent = clean_hex_color((string)($_POST['accent_color'] ?? ''));

    $color = clean_hex_color((string)($_POST['profile_color'] ?? ''));
    $alias = trim((string)($_POST['alias'] ?? ''));
    $tagline = trim((string)($_POST['tagline'] ?? ''));
    $statusMsg = trim((string)($_POST['status_msg'] ?? ''));
    $occupation = trim((string)($_POST['occupation'] ?? ''));
    $education = trim((string)($_POST['education'] ?? ''));
    $location = trim((string)($_POST['location'] ?? ''));
    $birthdate = trim((string)($_POST['birthdate'] ?? ''));
    $pronouns = trim((string)($_POST['pronouns'] ?? ''));
    $languages = trim((string)($_POST['languages'] ?? ''));
    $hobbies = trim((string)($_POST['hobbies'] ?? ''));
    $skills = trim((string)($_POST['skills'] ?? ''));
    $quote = trim((string)($_POST['quote'] ?? ''));
    $bio = trim((string)($_POST['bio'] ?? ''));
    $website = trim((string)($_POST['website'] ?? ''));
    $linkedin = trim((string)($_POST['linkedin'] ?? ''));

    $handleKeys = ['github', 'twitch', 'tiktok', 'instagram', 'reddit', 'snapchat', 'bluesky', 'threads', 'discord', 'telegram', 'twitter'];
    $handles = [];
    foreach ($handleKeys as $k) {
        $handles[$k] = preg_replace('/^@+/', '', trim((string)($_POST[$k] ?? '')));
    }

    $fail = function (string $msg) {
        flash_set('error', $msg);
        redirect('settings.php');
    };

    $urlFields = ['pfp' => $pfp, 'banner' => $banner, 'bg_image' => $bgImage, 'website' => $website, 'linkedin' => $linkedin];
    foreach ($urlFields as $label => $v) {
        if ($v !== '' && (filter_var($v, FILTER_VALIDATE_URL) === false || strpos($v, 'http') !== 0)) {
            $fail('' . $label . ' must be a valid http(s) URL or empty.');
        }
    }
    foreach ($handles as $k => $v) {
        if ($v !== '' && !preg_match('#^[A-Za-z0-9_.-]{1,100}$#', $v)) {
            $fail('' . ucfirst($k) . ' handle can only use letters, numbers, dots, dashes and underscores.');
        }
    }
    if ($bgGradient !== '' && !preg_match('#^[a-z]*-gradient\([\w\s(),.#%+_\-/]*\)$#i', $bgGradient)) {
        $fail('Background gradient must look like linear-gradient(135deg, #000000, #ffffff) — safe CSS values only.');
    }
    if ($uiGradient !== '' && !preg_match('#^[a-z]*-gradient\([\w\s(),.#%+_\-/]*\)$#i', $uiGradient)) {
        $fail('UI background gradient must look like linear-gradient(135deg, #000000, #ffffff) — safe CSS values only.');
    }
    if ($birthdate !== '') {
        $d = DateTime::createFromFormat('Y-m-d', $birthdate);
        if (!$d || $d->format('Y-m-d') !== $birthdate) {
            $fail('Birthdate must be a real date in YYYY-MM-DD format.');
        }
        if ($d > new DateTime('today')) {
            $fail('Birthdate cannot be in the future.');
        }
    }

    $birthdateVal = $birthdate !== '' ? $birthdate : null;

    try {
        db()->prepare(
            'UPDATE users
             SET pfp = ?, banner = ?, bg_image = ?, bg_mode = ?, bg_fit = ?, bg_color = ?, bg_gradient = ?, bg_veil = ?, bg_blur = ?,
                 profile_color = ?, alias = ?, tagline = ?, status_msg = ?, occupation = ?, education = ?, location = ?, birthdate = ?,
                 pronouns = ?, languages = ?, hobbies = ?, skills = ?, quote = ?, bio = ?,
                 website = ?, discord = ?, telegram = ?, twitter = ?, youtube = ?, github = ?, twitch = ?, tiktok = ?, instagram = ?,
                 reddit = ?, snapchat = ?, bluesky = ?, threads = ?, linkedin = ?,
                 ui_mode = ?, ui_color = ?, ui_gradient = ?, ui_layout = ?, accent_color = ?
             WHERE id = ?'
        )->execute([
            mb_substr($pfp, 0, 255),
            mb_substr($banner, 0, 255),
            mb_substr($bgImage, 0, 255),
            $bgMode,
            $bgFit,
            $bgColor,
            mb_substr($bgGradient, 0, 300),
            $bgVeil,
            $bgBlur,
            $color,
            mb_substr($alias, 0, 50),
            mb_substr($tagline, 0, 120),
            mb_substr($statusMsg, 0, 160),
            mb_substr($occupation, 0, 120),
            mb_substr($education, 0, 255),
            mb_substr($location, 0, 100),
            $birthdateVal,
            mb_substr($pronouns, 0, 40),
            mb_substr($languages, 0, 255),
            mb_substr($hobbies, 0, 255),
            mb_substr($skills, 0, 255),
            mb_substr($quote, 0, 280),
            mb_substr($bio, 0, 2000),
            mb_substr($website, 0, 255),
            mb_substr($handles['discord'], 0, 100),
            mb_substr($handles['telegram'], 0, 100),
            mb_substr($handles['twitter'], 0, 100),
            mb_substr((string)($_POST['youtube'] ?? ''), 0, 255),
            mb_substr($handles['github'], 0, 100),
            mb_substr($handles['twitch'], 0, 100),
            mb_substr($handles['tiktok'], 0, 100),
            mb_substr($handles['instagram'], 0, 100),
            mb_substr($handles['reddit'], 0, 100),
            mb_substr($handles['snapchat'], 0, 100),
            mb_substr($handles['bluesky'], 0, 100),
            mb_substr($handles['threads'], 0, 100),
            mb_substr($linkedin, 0, 255),
            $uiMode,
            $uiColor,
            mb_substr($uiGradient, 0, 300),
            $uiLayout,
            $uiAccent,
            (int)$u['id'],
        ]);
    } catch (Throwable $t) {
        flash_set('error', 'Could not save profile. Schema upgrade maybe not applied (see schema_upgrade.sql).');
        redirect('settings.php');
    }
    log_activity('profile_update', $u['username']);
    flash_set('success', 'Profile updated.');
    redirect('settings.php');
}

page_header('Settings');
?>
<div class="container" style="max-width: 820px;">
    <div class="row g-4">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <h1 class="h4 mb-3">Profile settings</h1>
                    <form method="post" action="settings.php">
                        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">

                        <h2 class="h6 text-secondary mt-4 mb-2" style="letter-spacing:1px;">IDENTITY</h2>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Username / alias</label>
                                <input class="form-control" name="alias" maxlength="50" value="<?= e((string)($u['alias'] ?? '')) ?>">
                                <div class="form-text">Display name — leave empty to just use your username.</div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Name color</label>
                                <input class="form-control form-control-color" type="color" name="profile_color"
                                    value="<?= e(clean_hex_color($u['profile_color']) !== '' ? clean_hex_color($u['profile_color']) : '#ffffff') ?>">
                                <div class="form-text">Your name color everywhere on the site.</div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Profile picture URL</label>
                                <input class="form-control" name="pfp" maxlength="255" value="<?= e((string)($u['pfp'] ?? '')) ?>">
                                <div class="form-text">Any image URL, e.g. from imgur.</div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Banner URL</label>
                                <input class="form-control" name="banner" maxlength="255" value="<?= e((string)($u['banner'] ?? '')) ?>">
                                <div class="form-text">Wide image at the top of your profile.</div>
                            </div>
                        </div>

                        <h2 class="h6 text-secondary mt-4 mb-2" style="letter-spacing:1px;">ABOUT YOU</h2>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Tagline</label>
                                <input class="form-control" name="tagline" maxlength="120" value="<?= e((string)($u['tagline'] ?? '')) ?>">
                                <div class="form-text">One line under your name.</div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Status message</label>
                                <input class="form-control" name="status_msg" maxlength="160" value="<?= e((string)($u['status_msg'] ?? '')) ?>">
                                <div class="form-text">e.g. "working on a big project until Friday".</div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Occupation / job</label>
                                <input class="form-control" name="occupation" maxlength="120" value="<?= e((string)($u['occupation'] ?? '')) ?>">
                                <div class="form-text">Student, dev, pastry chef...</div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Education</label>
                                <input class="form-control" name="education" maxlength="255" value="<?= e((string)($u['education'] ?? '')) ?>">
                                <div class="form-text">School, degree, course, whatever.</div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Birthdate (optional)</label>
                                <input class="form-control" type="date" name="birthdate"
                                    value="<?= e((string)($u['birthdate'] ?? '')) ?>">
                                <div class="form-text">Shows your age on your profile. Hidden if left empty.</div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Location</label>
                                <input class="form-control" name="location" maxlength="100" value="<?= e((string)($u['location'] ?? '')) ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Pronouns</label>
                                <input class="form-control" name="pronouns" maxlength="40" value="<?= e((string)($u['pronouns'] ?? '')) ?>">
                                <div class="form-text">he/him, she/her, they/them...</div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Languages</label>
                                <input class="form-control" name="languages" maxlength="255" value="<?= e((string)($u['languages'] ?? '')) ?>">
                                <div class="form-text">Comma-separated: English, Spanish, Lua...</div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Hobbies</label>
                                <input class="form-control" name="hobbies" maxlength="255" value="<?= e((string)($u['hobbies'] ?? '')) ?>">
                                <div class="form-text">Comma-separated: gaming, drawing, archery...</div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Skills / interests</label>
                                <input class="form-control" name="skills" maxlength="255" value="<?= e((string)($u['skills'] ?? '')) ?>">
                                <div class="form-text">Comma-separated tags.</div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Favorite quote</label>
                                <input class="form-control" name="quote" maxlength="280" value="<?= e((string)($u['quote'] ?? '')) ?>">
                            </div>
                            <div class="col-12">
                                <label class="form-label">Bio</label>
                                <textarea class="form-control" name="bio" rows="4" maxlength="2000"><?= e((string)($u['bio'] ?? '')) ?></textarea>
                                <div class="form-text">Max 2000 chars.</div>
                            </div>
                        </div>

                        <h2 class="h6 text-secondary mt-4 mb-2" style="letter-spacing:1px;">SOCIALS</h2>
                        <div class="row g-3">
                            <div class="col-md-6"><label class="form-label">Website</label>
                                <input class="form-control" name="website" maxlength="255" value="<?= e((string)($u['website'] ?? '')) ?>"></div>
                            <div class="col-md-6"><label class="form-label">LinkedIn URL</label>
                                <input class="form-control" name="linkedin" maxlength="255" value="<?= e((string)($u['linkedin'] ?? '')) ?>"></div>
                            <div class="col-md-6"><label class="form-label">Discord</label>
                                <input class="form-control" name="discord" maxlength="100" value="<?= e((string)($u['discord'] ?? '')) ?>">
                                <div class="form-text">Username (no user#1234 format).</div></div>
                            <div class="col-md-6"><label class="form-label">Telegram</label>
                                <input class="form-control" name="telegram" maxlength="100" value="<?= e((string)($u['telegram'] ?? '')) ?>">
                                <div class="form-text">@username</div></div>
                            <div class="col-md-6"><label class="form-label">Twitter / X</label>
                                <input class="form-control" name="twitter" maxlength="100" value="<?= e((string)($u['twitter'] ?? '')) ?>">
                                <div class="form-text">@username</div></div>
                            <div class="col-md-6"><label class="form-label">YouTube channel URL</label>
                                <input class="form-control" name="youtube" maxlength="255" value="<?= e((string)($u['youtube'] ?? '')) ?>"></div>
                            <div class="col-md-6"><label class="form-label">GitHub</label>
                                <input class="form-control" name="github" maxlength="100" value="<?= e((string)($u['github'] ?? '')) ?>"></div>
                            <div class="col-md-6"><label class="form-label">Twitch</label>
                                <input class="form-control" name="twitch" maxlength="100" value="<?= e((string)($u['twitch'] ?? '')) ?>"></div>
                            <div class="col-md-6"><label class="form-label">TikTok</label>
                                <input class="form-control" name="tiktok" maxlength="100" value="<?= e((string)($u['tiktok'] ?? '')) ?>"></div>
                            <div class="col-md-6"><label class="form-label">Instagram</label>
                                <input class="form-control" name="instagram" maxlength="100" value="<?= e((string)($u['instagram'] ?? '')) ?>"></div>
                            <div class="col-md-6"><label class="form-label">Reddit</label>
                                <input class="form-control" name="reddit" maxlength="100" value="<?= e((string)($u['reddit'] ?? '')) ?>"></div>
                            <div class="col-md-6"><label class="form-label">Snapchat</label>
                                <input class="form-control" name="snapchat" maxlength="100" value="<?= e((string)($u['snapchat'] ?? '')) ?>"></div>
                            <div class="col-md-6"><label class="form-label">Bluesky</label>
                                <input class="form-control" name="bluesky" maxlength="100" value="<?= e((string)($u['bluesky'] ?? '')) ?>"></div>
                            <div class="col-md-6"><label class="form-label">Threads</label>
                                <input class="form-control" name="threads" maxlength="100" value="<?= e((string)($u['threads'] ?? '')) ?>"></div>
                        </div>

                        <h2 class="h6 text-secondary mt-4 mb-2" style="letter-spacing:1px;">PROFILE BACKGROUND</h2>
                        <?php $bgMode = (string)($u['bg_mode'] ?? 'none'); ?>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Background type</label>
                                <select class="form-select" name="bg_mode" id="bg_mode" onchange="bgMode(this.value)">
                                    <option value="none" <?= $bgMode === 'none' ? 'selected' : '' ?>>None (default dark)</option>
                                    <option value="image" <?= $bgMode === 'image' ? 'selected' : '' ?>>Image</option>
                                    <option value="gradient" <?= $bgMode === 'gradient' ? 'selected' : '' ?>>Gradient</option>
                                    <option value="color" <?= $bgMode === 'color' ? 'selected' : '' ?>>Solid color</option>
                                </select>
                            </div>
                            <div class="col-md-6" id="bg-img-row" style="display:none;">
                                <label class="form-label">Background image URL</label>
                                <input class="form-control" name="bg_image" maxlength="255" value="<?= e((string)($u['bg_image'] ?? '')) ?>">
                                <div class="form-text">Shown behind your whole profile.</div>
                            </div>
                            <div class="col-md-6" id="bg-grad-row" style="display:none;">
                                <label class="form-label">Gradient (click a preset or type your own)</label>
                                <input class="form-control mb-2" name="bg_gradient" id="bg_gradient" maxlength="300"
                                    value="<?= e((string)($u['bg_gradient'] ?? '')) ?>" placeholder="linear-gradient(135deg, #0f0c29, #302b63)">
                                <div class="d-flex flex-wrap gap-1">
                                    <?php foreach ($gradPresets as $name => $grad): ?>
                                        <button type="button" class="btn btn-sm btn-outline-light" style="font-size:.7rem;"
                                            onclick="document.getElementById('bg_gradient').value='<?= e($grad, true) ?>'"><?= e($name) ?></button>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <div class="col-md-6" id="bg-color-row" style="display:none;">
                                <label class="form-label">Solid background color</label>
                                <input class="form-control form-control-color" type="color" name="bg_color"
                                    value="<?= e(clean_hex_color($u['bg_color']) !== '' ? clean_hex_color($u['bg_color']) : '#202020') ?>">
                            </div>
                            <div class="col-md-6" id="bg-style-row">
                                <label class="form-label">Dark overlay (keeps text readable): <span id="veil-label"><?= (int)($u['bg_veil'] ?? 55) ?>%</span></label>
                                <input type="range" class="form-range" name="bg_veil" min="0" max="90" step="5"
                                    value="<?= (int)($u['bg_veil'] ?? 55) ?>" oninput="document.getElementById('veil-label').textContent=this.value+'%'">
                                <div class="d-flex gap-3 mt-2">
                                    <label class="form-check-label small"><input type="checkbox" class="form-check-input me-1" name="bg_fit" value="repeat" <?= (($u['bg_fit'] ?? 'cover') === 'repeat') ? 'checked' : '' ?>>Tile / repeat image</label>
                                    <label class="form-check-label small"><input type="checkbox" class="form-check-input me-1" name="bg_blur" value="1" <?= !empty($u['bg_blur']) ? 'checked' : '' ?>>Blur image</label>
                                </div>
                            </div>
                        </div>

                        <h2 class="h6 text-secondary mt-4 mb-2" style="letter-spacing:1px;">YOUR SITE LOOK (UI THEME)</h2>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Site background</label>
                                <select class="form-select" name="ui_mode" id="ui_mode" onchange="uiMode(this.value)">
                                    <option value="none" <?= ($u['ui_mode'] ?? 'none') === 'none' ? 'selected' : '' ?>>Default (rich black)</option>
                                    <option value="color" <?= ($u['ui_mode'] ?? 'none') === 'color' ? 'selected' : '' ?>>Solid color</option>
                                    <option value="gradient" <?= ($u['ui_mode'] ?? 'none') === 'gradient' ? 'selected' : '' ?>>Gradient</option>
                                </select>
                                <div class="form-text">Applies to the whole site while you're logged in — everyone else still sees the default.</div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Site layout</label>
                                <select class="form-select" name="ui_layout">
                                    <option value="default" <?= ($u['ui_layout'] ?? 'default') === 'default' ? 'selected' : '' ?>>Default width</option>
                                    <option value="compact" <?= ($u['ui_layout'] ?? 'default') === 'compact' ? 'selected' : '' ?>>Compact (narrow)</option>
                                    <option value="wide" <?= ($u['ui_layout'] ?? 'default') === 'wide' ? 'selected' : '' ?>>Wide</option>
                                </select>
                                <div class="form-text">How wide page content stretches.</div>
                            </div>
                            <div class="col-md-6" id="ui-color-row" style="display:none;">
                                <label class="form-label">Background color</label>
                                <input class="form-control form-control-color" type="color" name="ui_color"
                                    value="<?= e(clean_hex_color($u['ui_color']) !== '' ? clean_hex_color($u['ui_color']) : '#0d1b2a') ?>">
                            </div>
                            <div class="col-md-6" id="ui-grad-row" style="display:none;">
                                <label class="form-label">Background gradient (click a preset or type your own)</label>
                                <input class="form-control mb-2" name="ui_gradient" id="ui_gradient" maxlength="300"
                                    value="<?= e((string)($u['ui_gradient'] ?? '')) ?>" placeholder="linear-gradient(135deg, #0f0c29, #302b63)">
                                <div class="d-flex flex-wrap gap-1">
                                    <?php foreach ($gradPresets as $name => $grad): ?>
                                        <button type="button" class="btn btn-sm btn-outline-light" style="font-size:.7rem;"
                                            onclick="document.getElementById('ui_gradient').value='<?= e($grad, true) ?>'"><?= e($name) ?></button>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Accent color (links &amp; buttons)</label>
                                <input class="form-control form-control-color" type="color" name="accent_color"
                                    value="<?= e(clean_hex_color($u['accent_color']) !== '' ? clean_hex_color($u['accent_color']) : '#5865f2') ?>">
                                <div class="form-text">Recolors links, primary buttons and highlights site-wide.</div>
                            </div>
                        </div>

                        <div class="mt-4 d-flex gap-2">
                            <button class="btn btn-primary" type="submit">Save profile</button>
                            <a class="btn btn-outline-light" href="profile.php?id=<?= (int)$u['id'] ?>">View profile</a>
                        </div>
                    </form>
                </div>
            </div>

            <div class="col-12" id="recovery">
                <div class="card">
                    <div class="card-body">
                        <h2 class="h4 mb-3">🔑 Recovery key</h2>
                        <p class="text-secondary small">If you forget your password you can reset it with this key at <code>forgot.php</code> — there is no email. Store it somewhere safe; anyone with it can take over the account.</p>
                        <?php $pendingRecovery = (string)($_SESSION['pending_recovery_key'] ?? ''); ?>
                        <?php if ($pendingRecovery !== ''): ?>
                            <div class="alert alert-warning small">Your new key is shown below <strong>once</strong> — copy it or download it now.</div>
                            <div class="d-flex align-items-center gap-2 mb-3">
                                <code id="recovery-key" class="form-control text-center" style="font-family:'JetBrains Mono',monospace;font-size:1rem;user-select:all;"><?= e($pendingRecovery) ?></code>
                                <button type="button" class="btn btn-outline-light" id="copy-key" onclick="copyRecoveryKey()">Copy</button>
                            </div>
                            <div class="d-flex gap-2 mb-4">
                                <button type="button" class="btn btn-outline-light flex-fill" onclick="downloadRecoveryKey()">⬇ Download as file</button>
                            </div>
                            <script>
                            var _rk = document.getElementById('recovery-key').textContent;
                            function copyRecoveryKey() {
                                if (navigator.clipboard && navigator.clipboard.writeText) {
                                    navigator.clipboard.writeText(_rk).then(function () {
                                        document.getElementById('copy-key').textContent = 'Copied!';
                                        setTimeout(function () { document.getElementById('copy-key').textContent = 'Copy'; }, 1500);
                                    });
                                } else {
                                    var t = document.getElementById('recovery-key');
                                    t.focus(); t.select();
                                    try { document.execCommand('copy'); } catch (e) {}
                                }
                            }
                            function downloadRecoveryKey() {
                                var blob = new Blob(['KevBin recovery key for account: ' + '<?= e($u['username']) ?>' + '\n\n' + _rk + '\n\nIf you lose your password you can reset it at forgot.php with this key.\n'], { type: 'text/plain' });
                                var a = document.createElement('a');
                                a.href = URL.createObjectURL(blob);
                                a.download = 'kevbin-recovery-key.txt';
                                document.body.appendChild(a);
                                a.click();
                                setTimeout(function () { URL.revokeObjectURL(a.href); a.remove(); }, 1000);
                            }
                            </script>
                            <form method="post" action="settings.php#recovery">
                                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                                <input type="hidden" name="ack_key" value="1">
                                <button class="btn btn-warning" type="submit">I saved it — dismiss</button>
                            </form>
                        <?php else: ?>
                            <p class="text-secondary small mb-3">Your account currently has a recovery key. Generating a new one invalidates the old key instantly.</p>
                            <form method="post" action="settings.php#recovery">
                                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                                <input type="hidden" name="new_recovery_key" value="1">
                                <button class="btn btn-warning" type="submit">Generate new recovery key</button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        <h2 class="h4 mb-3">Change password</h2>
                        <form method="post" action="settings.php">
                            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                            <input type="hidden" name="change_password" value="1">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">Current password</label>
                                    <input class="form-control" type="password" name="current_password" autocomplete="current-password">
                                </div>
                            </div>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">New password</label>
                                    <input class="form-control" type="password" name="new_password" autocomplete="new-password">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Repeat new password</label>
                                    <input class="form-control" type="password" name="confirm_password" autocomplete="new-password">
                                </div>
                            </div>
                            <div class="mt-3">
                                <button class="btn btn-warning" type="submit">Change password</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<script>
function bgMode(m) {
    ['bg-img-row', 'bg-grad-row', 'bg-color-row'].forEach(function (id) {
        document.getElementById(id).style.display = 'none';
    });
    var map = { image: 'bg-img-row', gradient: 'bg-grad-row', color: 'bg-color-row' };
    if (map[m]) document.getElementById(map[m]).style.display = '';
}
bgMode(document.getElementById('bg_mode').value);
function uiMode(m) {
    ['ui-color-row', 'ui-grad-row'].forEach(function (id) {
        document.getElementById(id).style.display = 'none';
    });
    var map = { color: 'ui-color-row', gradient: 'ui-grad-row' };
    if (map[m]) document.getElementById(map[m]).style.display = '';
}
uiMode(document.getElementById('ui_mode').value);
</script>
<?php page_footer(); ?>