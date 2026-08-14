<?php
require_once __DIR__ . '/functions.php';

start_session();

$id = (int)($_GET['id'] ?? 0);
if ($id < 1) {
    friendly_error('User not found.', 404);
}

$stmt = db()->prepare(
    'SELECT id, username, role, status, pfp, banner, profile_color, created_at,
            bio, location, website, discord, telegram, twitter, youtube, alias, profile_views,
            tagline, pronouns, skills, bg_image,
            occupation, education, languages, hobbies, quote, birthdate, status_msg,
            github, twitch, tiktok, instagram, reddit, snapchat, bluesky, threads, linkedin,
            bg_mode, bg_fit, bg_color, bg_gradient, bg_veil, bg_blur
     FROM users WHERE id = ?'
);
$stmt->execute([$id]);
$user = $stmt->fetch();

if (!$user) {
    friendly_error('User not found.', 404);
}

$me = current_user();
$pdo = db();

// Profile view counter (don't count the owner's own visits)
if ($me === null || (int)$me['id'] !== $id) {
    $pdo->prepare('UPDATE users SET profile_views = profile_views + 1 WHERE id = ?')->execute([$id]);
}

$voteSummary = vote_summary('profile', (string)$id);
$comments = fetch_comments('profile', (string)$id);
$myProfileVote = $me !== null ? my_vote('profile', (string)$id, (int)$me['id']) : 0;

// SEO / social metadata
$profileDesc = trim((string)$user['bio']);
if ($profileDesc === '') {
    $profileDesc = 'Profile of ' . $user['username'] . ' on ' . $GLOBALS['CFG']['site_name'] . '.';
}
set_meta([
    'title' => $user['username'],
    'description' => mb_substr($profileDesc, 0, 160),
    'type' => 'profile',
    'url' => $GLOBALS['CFG']['base_url'] . 'profile.php?id=' . (int)$id,
    'image' => (string)$user['pfp'],
]);

$pastes = $pdo->prepare(
    'SELECT p.id, p.title, p.created_at, p.views, p.pin, p.paste_color
     FROM pastes p
     WHERE p.user_id = ? AND (p.expires_at IS NULL OR p.expires_at > UTC_TIMESTAMP())
     ORDER BY p.created_at DESC
     LIMIT 100'
);
$pastes->execute([$id]);
$pastes = $pastes->fetchAll();

$pinned = $pdo->prepare(
    'SELECT p.id, p.title, p.created_at, p.views, p.pin, p.paste_color
     FROM pins pi
     JOIN pastes p ON p.id = pi.paste_id
     WHERE pi.user_id = ? AND (p.expires_at IS NULL OR p.expires_at > UTC_TIMESTAMP())
     ORDER BY pi.created_at DESC
     LIMIT 50'
);
$pinned->execute([$id]);
$pinned = $pinned->fetchAll();

$color = clean_hex_color($user['profile_color']);
$nameStyle = $color !== '' ? 'color:' . $color . ';' : '';

// ——— custom background (image / gradient / solid, with overlay + blur) ———
$bgMode = (string)($user['bg_mode'] ?? 'none');
if ($bgMode === 'none' && !empty($user['bg_image'])) {
    $bgMode = 'image'; // legacy: pre-v2.0 profiles used bg_image with no mode
}
$bgCss = '';
if ($bgMode === 'image' && !empty($user['bg_image'])) {
    $img = addcslashes((string)$user['bg_image'], "'\0..\37\\");
    $fit = (($user['bg_fit'] ?? 'cover') === 'repeat') ? 'repeat' : 'center/cover no-repeat fixed';
    $bgCss = "background:url('{$img}') {$fit};";
    if ((int)($user['bg_blur'] ?? 0) === 1) {
        $bgCss .= 'filter:brightness(.5) blur(4px);transform:scale(1.05);';
    }
} elseif ($bgMode === 'gradient' && !empty($user['bg_gradient'])) {
    $bgCss = 'background:' . e((string)$user['bg_gradient']) . ';background-attachment:fixed;';
} elseif ($bgMode === 'color') {
    $c = clean_hex_color((string)($user['bg_color'] ?? ''));
    if ($c !== '') {
        $bgCss = 'background:' . $c . ';';
    }
}
$bgVeil = max(0, min(90, (int)($user['bg_veil'] ?? 55)));

// ——— age from birthdate ———
$age = null;
if (!empty($user['birthdate'])) {
    $bd = DateTime::createFromFormat('Y-m-d', (string)$user['birthdate'], new DateTimeZone('UTC'));
    if ($bd) {
        $age = (new DateTime('now', new DateTimeZone('UTC')))->diff($bd)->y;
    }
}

// ——— profile socials ———
$socials = [];
if (!empty($user['github'])) $socials[] = ['GitHub', 'https://github.com/' . ltrim((string)$user['github'], '@'), 'btn-outline-light'];
if (!empty($user['twitch'])) $socials[] = ['Twitch', 'https://www.twitch.tv/' . ltrim((string)$user['twitch'], '@'), 'btn-outline-light'];
if (!empty($user['tiktok'])) $socials[] = ['TikTok', 'https://www.tiktok.com/@' . ltrim((string)$user['tiktok'], '@'), 'btn-outline-light'];
if (!empty($user['instagram'])) $socials[] = ['Instagram', 'https://instagram.com/' . ltrim((string)$user['instagram'], '@'), 'btn-outline-light'];
if (!empty($user['reddit'])) $socials[] = ['Reddit', 'https://www.reddit.com/user/' . ltrim((string)$user['reddit'], '@'), 'btn-outline-light'];
if (!empty($user['snapchat'])) $socials[] = ['Snapchat', 'https://www.snapchat.com/add/' . ltrim((string)$user['snapchat'], '@'), 'btn-outline-light'];
if (!empty($user['bluesky'])) $socials[] = ['Bluesky', 'https://bsky.app/profile/' . ltrim((string)$user['bluesky'], '@'), 'btn-outline-light'];
if (!empty($user['threads'])) $socials[] = ['Threads', 'https://www.threads.net/@' . ltrim((string)$user['threads'], '@'), 'btn-outline-light'];
if (!empty($user['telegram'])) $socials[] = ['Telegram', 'https://t.me/' . ltrim((string)$user['telegram'], '@'), 'btn-outline-light'];
if (!empty($user['twitter'])) $socials[] = ['Twitter / X', 'https://x.com/' . ltrim((string)$user['twitter'], '@'), 'btn-outline-light'];
if (!empty($user['website'])) $socials[] = ['Website', (string)$user['website'], 'btn-outline-light'];
if (!empty($user['linkedin'])) $socials[] = ['LinkedIn', (string)$user['linkedin'], 'btn-outline-light'];
if (!empty($user['youtube'])) $socials[] = ['YouTube', (string)$user['youtube'], 'btn-outline-danger'];

// ——— comma-list badge group ———
$badgeGroup = function (string $label, string $value): string {
    $items = array_values(array_filter(array_map('trim', explode(',', $value)), 'strlen'));
    if (count($items) === 0) {
        return '';
    }
    $h = '<div class="mb-2"><span class="text-secondary small me-2">' . e($label) . ':</span>';
    foreach ($items as $it) {
        $h .= '<span class="badge bg-secondary me-1 mb-1">' . e(mb_substr($it, 0, 40)) . '</span>';
    }
    return $h . '</div>';
};

$discordHandle = (string)($user['discord'] ?? '');
$hasAbout = !empty($user['bio']) || !empty($user['status_msg']) || !empty($user['quote'])
    || !empty($user['occupation']) || !empty($user['education']) || !empty($user['birthdate'])
    || !empty($user['pronouns']) || !empty($user['languages']) || !empty($user['hobbies'])
    || !empty($user['skills']) || count($socials) > 0 || $discordHandle !== '';

page_header($user['username']);
?>
<div class="container" style="max-width: 900px;">
    <?php if ($bgCss !== ''): ?>
        <style>
            .profile-bg { position: fixed; inset: 0; z-index: -2; <?= $bgCss ?> }
            <?php if ($bgVeil > 0): ?>.profile-veil { position: fixed; inset: 0; z-index: -1; background: rgba(0,0,0,.<?= $bgVeil ?>); }<?php endif; ?>
        </style>
        <div class="profile-bg"></div>
        <?php if ($bgVeil > 0): ?><div class="profile-veil"></div><?php endif; ?>
    <?php endif; ?>
    <?php if ($user['banner']): ?>
        <img class="banner mb-3" src="<?= e($user['banner']) ?>" alt="banner">
    <?php endif; ?>
    <div class="card mb-4"><div class="card-body d-flex align-items-center gap-3 profile-head">
        <?php if ($user['pfp']): ?>
            <img class="pfp" src="<?= e($user['pfp']) ?>" alt="pfp">
        <?php else: ?>
            <div class="pfp d-flex align-items-center justify-content-center bg-secondary fs-3">?</div>
        <?php endif; ?>
        <div class="flex-grow-1">
            <h1 class="h4 mb-1" style="<?= e($nameStyle) ?>"><?= e($user['username']) ?><?php if (!empty($user['alias']) && $user['alias'] !== $user['username']): ?> <span class="text-secondary fw-normal fs-6">(<?= e($user['alias']) ?>)</span><?php endif; ?></h1>
            <?php if (!empty($user['tagline'])): ?><div class="small mb-1" style="color:var(--dim);font-style:italic;">"<?= e($user['tagline']) ?>"</div><?php endif; ?>
            <div class="text-secondary small">
                <?php if ($user['role'] === 'admin'): ?>
                    <span class="badge bg-danger">ADMIN</span>
                <?php endif; ?>
                <?php if ($user['status'] !== 'active'): ?>
                    <span class="badge bg-warning">SUSPENDED</span>
                <?php endif; ?>
                joined <?= $user['created_at'] ? e(gmdate('Y-m-d', strtotime($user['created_at'] . ' UTC'))) : '—' ?>
                <?php if ($user['location']): ?> · <?= e($user['location']) ?><?php endif; ?>
                · <?= (int)$user['profile_views'] ?> profile views
                · <?= (int)follower_count($id) ?> followers · <?= (int)following_count($id) ?> following
            </div>
        </div>
        <div class="d-flex flex-column align-items-end gap-2 profile-actions">
            <?= vote_buttons('profile', (string)$id, $myProfileVote, $voteSummary, 'profile.php?id=' . (int)$id) ?>
            <?php if ($me !== null && (int)$me['id'] === $id): ?>
                <a class="btn btn-outline-light" href="settings.php">Edit profile</a>
            <?php elseif ($me !== null): ?>
                <form method="post" action="follow.php">
                    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="target" value="<?= (int)$id ?>">
                    <?php $following = is_following((int)$me['id'], $id); ?>
                    <button class="btn <?= $following ? 'btn-outline-light' : 'btn-primary' ?>" type="submit"
                        name="action" value="<?= $following ? 'unfollow' : 'follow' ?>">
                        <?= $following ? 'Unfollow' : 'Follow' ?>
                    </button>
                </form>
            <?php endif; ?>
        </div>
    </div></div>

    <?php if ($hasAbout): ?>
        <div class="card mb-4"><div class="card-body">
            <h2 class="h6 mb-2">About</h2>
            <?php if (!empty($user['status_msg'])): ?>
                <div class="alert alert-secondary py-2 mb-3 small"><strong>Status:</strong> <?= e($user['status_msg']) ?></div>
            <?php endif; ?>
            <?php if ($user['bio']): ?><p class="mb-2"><?= nl2br(e($user['bio'])) ?></p><?php endif; ?>
            <?php if (!empty($user['quote'])): ?>
                <blockquote class="small mb-3" style="color:var(--dim);border-left:3px solid var(--line);padding-left:.9rem;">"<?= e($user['quote']) ?>"</blockquote>
            <?php endif; ?>
            <div class="text-secondary small mb-2">
                <?php if (!empty($user['occupation'])): ?>💼 <?= e($user['occupation']) ?><?php endif; ?>
                <?php if (!empty($user['occupation']) && !empty($user['education'])): ?> · <?php endif; ?>
                <?php if (!empty($user['education'])): ?>🎓 <?= e($user['education']) ?><?php endif; ?>
                <?php if (!empty($user['birthdate'])): ?> · 🎂 born <?= e(gmdate('Y', strtotime((string)$user['birthdate']))) ?><?php if ($age !== null): ?> (<?= (int)$age ?>)<?php endif; ?><?php endif; ?>
            </div>
            <?php if (!empty($user['pronouns'])): ?><div class="text-secondary small mb-2">Pronouns: <?= e($user['pronouns']) ?></div><?php endif; ?>
            <?= $badgeGroup('Languages', (string)($user['languages'] ?? '')) ?>
            <?= $badgeGroup('Hobbies', (string)($user['hobbies'] ?? '')) ?>
            <?= $badgeGroup('Skills', (string)($user['skills'] ?? '')) ?>
            <div class="d-flex flex-wrap gap-2">
                <?php if ($discordHandle !== ''): ?>
                    <span class="btn btn-sm btn-outline-light">Discord: <?= e($discordHandle) ?></span>
                <?php endif; ?>
                <?php foreach ($socials as $s): ?>
                    <a class="btn btn-sm <?= e($s[2]) ?>" target="_blank" rel="noopener" href="<?= e($s[1]) ?>"><?= e($s[0]) ?></a>
                <?php endforeach; ?>
            </div>
        </div></div>
    <?php endif; ?>

    <h2 class="h5 mb-3">Pinned (<?= count($pinned) ?>)</h2>
    <?php if (count($pinned) === 0): ?>
        <div class="alert alert-secondary">Nothing pinned yet.</div>
    <?php else: ?>
        <ul class="list-group mb-4">
            <?php foreach ($pinned as $p): ?>
                <li class="list-group-item d-flex justify-content-between align-items-center"<?= paste_border_style($p['paste_color']) ?>>
                    <a class="text-decoration-none fw-semibold" href="view.php?id=<?= e($p['id']) ?>"><?= e($p['title']) ?></a>
                    <?php if ($p['pin']): ?><span class="badge bg-primary">PINNED</span><?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>

    <h2 class="h5 mb-3">Pastes (<?= count($pastes) ?>)</h2>
    <?php if (count($pastes) === 0): ?>
        <div class="alert alert-secondary">No pastes yet.</div>
    <?php else: ?>
        <ul class="list-group mb-4">
            <?php foreach ($pastes as $p): ?>
                <li class="list-group-item d-flex justify-content-between align-items-center"<?= paste_border_style($p['paste_color']) ?>>
                    <a class="text-decoration-none fw-semibold" href="view.php?id=<?= e($p['id']) ?>"><?= e($p['title']) ?></a>
                    <span class="text-secondary small">
                        <?= (int)$p['views'] ?> views · <?= e(gmdate('Y-m-d H:i', strtotime($p['created_at'] . ' UTC'))) ?>
                    </span>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>

    <h2 class="h5 mb-3">Comments (<?= count($comments) ?>)</h2>
    <div class="card mb-4">
        <div class="card-body">
            <?php if ($me !== null): ?>
                <form method="post" action="comment.php" class="mb-3">
                    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="action" value="add">
                    <input type="hidden" name="type" value="profile">
                    <input type="hidden" name="target" value="<?= (int)$id ?>">
                    <input type="hidden" name="return" value="profile.php?id=<?= (int)$id ?>">
                    <label class="form-label small">Leave a comment</label>
                    <textarea class="form-control mb-2" name="body" rows="3" maxlength="2000" placeholder="Write something to <?= e($user['username']) ?>..."></textarea>
                    <button class="btn btn-primary btn-sm" type="submit">Post comment</button>
                </form>
            <?php else: ?>
                <div class="alert alert-secondary mb-3">
                    <a class="text-info" href="login.php">Log in</a> to like, dislike and comment on this profile.
                </div>
            <?php endif; ?>
            <?php if (count($comments) === 0): ?>
                <div class="text-secondary small">No comments yet.</div>
            <?php else: ?>
                <?php foreach ($comments as $c): ?>
                    <div class="d-flex gap-3 py-2" style="border-bottom:1px solid var(--line);">
                        <?php if ($c['pfp']): ?>
                            <img class="pfp-sm" src="<?= e($c['pfp']) ?>" alt="pfp">
                        <?php else: ?>
                            <div class="pfp-sm d-flex align-items-center justify-content-center bg-secondary">?</div>
                        <?php endif; ?>
                        <div class="flex-grow-1">
                            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                                <span class="small">
                                    <a class="fw-semibold" style="color:<?= e(clean_hex_color($c['profile_color']) !== '' ? clean_hex_color($c['profile_color']) : '#ffffff') ?>"
                                        href="profile.php?id=<?= (int)$c['user_id'] ?>"><?= e($c['username']) ?></a>
                                    · <?= e(gmdate('Y-m-d H:i', strtotime($c['created_at'] . ' UTC'))) ?> UTC
                                </span>
                                <?php if ($me !== null && ((int)$c['user_id'] === (int)$me['id'] || is_staff())): ?>
                                    <form method="post" action="comment.php" onsubmit="return confirm('Delete this comment?');">
                                        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="type" value="profile">
                                        <input type="hidden" name="target" value="<?= (int)$id ?>">
                                        <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
                                        <input type="hidden" name="return" value="profile.php?id=<?= (int)$id ?>">
                                        <button class="btn btn-sm btn-outline-danger">Delete</button>
                                    </form>
                                <?php endif; ?>
                            </div>
                            <div class="mt-1 small"><?= nl2br(e($c['body'])) ?></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php page_footer(); ?>