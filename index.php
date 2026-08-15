<?php
require_once __DIR__ . '/functions.php';

start_session();

if (!is_installed()) {
    flash_set('error', 'Database tables are missing. Run setup.php first.');
    redirect('setup.php');
}

$pdo = db();
$cfg = $GLOBALS['CFG'];

// ?new=1 shows the paste form; the default landing page is the paste list.
$isNew = isset($_GET['new']);

if ($isNew) {
    $recent = $pdo->query(
        'SELECT p.id, p.title, p.created_at, p.views, p.user_id, p.author,
                u.username AS owner_name, u.profile_color AS owner_color
         FROM pastes p
         LEFT JOIN users u ON u.id = p.user_id
         WHERE p.expires_at IS NULL OR p.expires_at > UTC_TIMESTAMP()
         ORDER BY p.created_at DESC
         LIMIT 5'
    )->fetchAll();

    captcha_issue(true);

    page_header('New Paste');
    ?>
    <div class="container" style="max-width: 1100px;">
        <div class="alert alert-secondary d-flex align-items-center gap-2 mb-4">
            <span>🔒</span>
            <span>No account, no tracking, no history. We only store what you put here — nothing else.</span>
        </div>

        <div class="row g-4">
            <div class="col-lg-8">
                <div class="card mb-4">
                    <div class="card-body">
                        <h1 class="h4 mb-3">Create a paste</h1>
                        <form method="post" action="upload.php">
                            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                            <div class="mb-3">
                                <label class="form-label">Title</label>
                                <input class="form-control" name="title" maxlength="120" value="<?= e((string)($_POST['title'] ?? '')) ?>">
                            </div>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">Author</label>
                                    <input class="form-control" type="text" value="<?= e(current_user()['username'] ?? 'Anonymous') ?>" readonly>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Expiration</label>
                                    <select class="form-select" name="expiry">
                                        <option value="">Never</option>
                                        <option value="3600">1 hour</option>
                                        <option value="86400">1 day</option>
                                        <option value="604800">1 week</option>
                                        <option value="2592000">1 month</option>
                                        <option value="25920000">1 year "beta"</option>
                                    </select>
                                </div>
                            </div>
                            <div class="mt-3">
                                <label class="form-label">Content</label>
                                <textarea class="form-control" name="content" rows="12" required></textarea>
                            </div>
                            <div class="row g-3 mt-1">
                                <div class="col-md-6">
                                    <label class="form-label">Description (optional)</label>
                                    <textarea class="form-control" name="description" rows="2" maxlength="255" placeholder="Short summary — shown in search results & Discord embeds"></textarea>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Tags (comma separated)</label>
                                    <input class="form-control" name="tags" maxlength="255" placeholder="lua, script, guide">
                                    <div class="form-text">Used for search keywords and as tags on the paste.</div>
                                </div>
                            </div>
                            <div class="mt-3">
                                <label class="form-label">Password lock (optional)</label>
                                <input class="form-control" type="password" name="password" maxlength="200"
                                    autocomplete="new-password" placeholder="Leave empty for a public paste">
                                <div class="form-text">Set a password and only people who know it can view this paste.</div>
                            </div>
                            <div class="mt-3">
                                <label class="form-label">Security check</label>
                                <div class="d-flex align-items-center gap-2 mb-2">
                                    <img src="captcha.php?v=<?= time() ?>" alt="captcha" width="160" height="56"
                                        style="border-radius:8px;border:1px solid var(--line);">
                                    <button type="button" class="btn btn-sm btn-outline-light" onclick="this.previousElementSibling.src='captcha.php?rot=1&v='+Date.now()"
                                        title="New captcha">↻</button>
                                </div>
                                <input class="form-control" name="captcha" maxlength="6" required autocomplete="off"
                                    placeholder="Type the characters above">
                            </div>
                            <div class="mt-3 d-flex align-items-center gap-2 flex-wrap">
                                <button class="btn btn-primary">Publish paste</button>
                                <span class="text-secondary small">Posts are keyword-watermarked and can be edited once using the edit link.</span>
                            </div>
                            <input type="hidden" name="paste_color" id="paste_color" value="">
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card">
                    <div class="card-body">
                        <h2 class="h6 mb-3">Paste options</h2>
                        <?php if (current_user() !== null): ?>
                            <div class="form-check mb-3">
                                <input class="form-check-input" type="checkbox" name="notify_followers" id="notify_followers" checked>
                                <label class="form-check-label" for="notify_followers">Notify my followers about this paste</label>
                            </div>
                        <?php endif; ?>
                        <div class="mb-3">
                            <label class="form-label">Accent color</label>
                            <div class="d-flex gap-2 flex-wrap" id="colorSwatches"></div>
                            <div class="form-text mt-2">Pick a color to highlight this paste. Click the selected swatch again to clear.</div>
                        </div>
                        <hr class="my-3" style="border-color:var(--line);">
                        <h2 class="h6 mb-2">Tips</h2>
                        <ul class="text-secondary small mb-0 ps-3">
                            <li>Click any username to open that profile.</li>
                            <li>The edit link works once — save it before you publish.</li>
                            <li>Your author name is your account name, or "Anonymous" if you don't log in.</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        <?php if (count($recent) > 0): ?>
            <h2 class="h5 mb-3">Recent pastes</h2>
            <ul class="list-group">
                <?php foreach ($recent as $p): ?>
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        <a class="text-decoration-none fw-semibold" href="view.php?id=<?= e($p['id']) ?>"><?= e($p['title']) ?></a>
                        <span class="text-secondary small">
                            <?php if ($p['user_id']): ?>
                                <a class="link-light" style="color:<?= e(clean_hex_color($p['owner_color']) !== '' ? clean_hex_color($p['owner_color']) : '#ffffff') ?>"
                                    href="profile.php?id=<?= (int)$p['user_id'] ?>"><?= e($p['owner_name']) ?></a>
                            <?php else: ?><?= e($p['author']) ?><?php endif; ?>
                            · <?= (int)$p['views'] ?> views ·
                            <?= e(gmdate('Y-m-d H:i', strtotime($p['created_at'] . ' UTC'))) ?>
                        </span>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
    <script>
    (function () {
        var colors = <?= json_encode(paste_colors(), JSON_UNESCAPED_SLASHES) ?>;
        var box = document.getElementById('colorSwatches');
        var hidden = document.getElementById('paste_color');
        if (!box || !hidden) return;
        colors.forEach(function (c) {
            var b = document.createElement('button');
            b.type = 'button';
            b.title = c;
            b.style.cssText = 'width:26px;height:26px;border-radius:8px;border:2px solid var(--line);background:' + c + ';cursor:pointer;transition:transform .1s ease;';
            b.onclick = function () {
                if (hidden.value === c) {
                    hidden.value = '';
                    b.style.borderColor = 'var(--line)';
                } else {
                    hidden.value = c;
                    box.querySelectorAll('button').forEach(function (x) { x.style.borderColor = 'var(--line)'; });
                    b.style.borderColor = '#fff';
                }
            };
            box.appendChild(b);
        });
    })();
    </script>
    <?php
} else {
    // ——— default landing page: the paste list ———
    $perPage = (int)$cfg['per_page'];
    $page = max(1, (int)($_GET['page'] ?? 1));
    $offset = ($page - 1) * $perPage;

    $total = (int)$pdo->query(
        'SELECT COUNT(*) FROM pastes WHERE expires_at IS NULL OR expires_at > UTC_TIMESTAMP()'
    )->fetchColumn();

    $pinned = $pdo->query(
        'SELECT p.id, p.title, p.created_at, p.views, p.user_id, p.author, p.paste_color,
                u.username AS owner_name, u.profile_color AS owner_color
         FROM pastes p
         LEFT JOIN users u ON u.id = p.user_id
         WHERE p.pin = 1 AND (p.expires_at IS NULL OR p.expires_at > UTC_TIMESTAMP())
         ORDER BY p.created_at DESC'
    )->fetchAll();

    $stmt = $pdo->prepare(
        'SELECT p.id, p.title, p.created_at, p.views, p.user_id, p.author,
                u.username AS owner_name, u.profile_color AS owner_color
         FROM pastes p
         LEFT JOIN users u ON u.id = p.user_id
         WHERE p.expires_at IS NULL OR p.expires_at > UTC_TIMESTAMP()
         ORDER BY p.created_at DESC
         LIMIT ? OFFSET ?'
    );
    $stmt->bindValue(1, $perPage, PDO::PARAM_INT);
    $stmt->bindValue(2, $offset, PDO::PARAM_INT);
    $stmt->execute();
    $pastes = $stmt->fetchAll();

    page_header('Pastes');
    ?>
    <div class="container" style="max-width: 900px;">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
            <h1 class="h4 mb-0">All pastes (<?= $total ?>)</h1>
            <a class="btn btn-primary" href="index.php?new=1">+ New paste</a>
        </div>

        <?php if (count($pinned) > 0): ?>
            <h2 class="h6 text-secondary mb-2">📌 Pinned</h2>
            <ul class="list-group mb-4">
                <?php foreach ($pinned as $p): ?>
                    <li class="list-group-item d-flex justify-content-between align-items-center"<?= paste_border_style($p['paste_color']) ?>>
                        <div>
                            <span class="badge bg-primary me-1">PINNED</span>
                            <a class="text-decoration-none fw-semibold" href="view.php?id=<?= e($p['id']) ?>"><?= e($p['title']) ?></a>
                        </div>
                        <span class="text-secondary small">
                            <?php if ($p['user_id']): ?>
                                <a class="link-light" style="color:<?= e(clean_hex_color($p['owner_color']) !== '' ? clean_hex_color($p['owner_color']) : '#ffffff') ?>"
                                    href="profile.php?id=<?= (int)$p['user_id'] ?>"><?= e($p['owner_name']) ?></a>
                            <?php else: ?><?= e($p['author']) ?><?php endif; ?>
                            · <?= (int)$p['views'] ?> views ·
                            <?= e(gmdate('Y-m-d H:i', strtotime($p['created_at'] . ' UTC'))) ?>
                        </span>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>

        <?php if (count($pastes) === 0 && count($pinned) === 0): ?>
            <div class="alert alert-secondary">No pastes yet. Be the first!</div>
        <?php else: ?>
            <ul class="list-group mb-4">
                <?php foreach ($pastes as $p): ?>
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        <a class="text-decoration-none fw-semibold" href="view.php?id=<?= e($p['id']) ?>"><?= e($p['title']) ?></a>
                        <span class="text-secondary small">
                            <?php if ($p['user_id']): ?>
                                <a class="link-light" style="color:<?= e(clean_hex_color($p['owner_color']) !== '' ? clean_hex_color($p['owner_color']) : '#ffffff') ?>"
                                    href="profile.php?id=<?= (int)$p['user_id'] ?>"><?= e($p['owner_name']) ?></a>
                            <?php else: ?><?= e($p['author']) ?><?php endif; ?>
                            · <?= (int)$p['views'] ?> views ·
                            <?= e(gmdate('Y-m-d H:i', strtotime($p['created_at'] . ' UTC'))) ?>
                        </span>
                    </li>
                <?php endforeach; ?>
            </ul>
            <?= paginate($page, $total, $perPage, 'index.php') ?>
        <?php endif; ?>
    </div>
    <?php
}

page_footer();