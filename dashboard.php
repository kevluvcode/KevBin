<?php
// KevBin Dashboard — free for everyone.
//
// Your personal hub: quick actions (new paste, upload files, file analysis),
// your account stats, and the Discord data export. The export connects via
// discord_oauth.php?export=1 which asks Discord for the read scopes the API
// actually grants (identify, guilds, connections, guilds.join) and lists every
// dataset returned: your profile, your servers + their IDs, and your
// connections — all downloadable as JSON. Note: Discord offers NO OAuth scope
// for reading messages or your friends list ('messages.read' doesn't exist and
// 'relationships.read' is a restricted scope rejected for normal apps), so
// those two can't be exported via the API.
require_once __DIR__ . '/functions.php';

start_session();
$cfg = $GLOBALS['CFG'];
$me = current_user();

// JSON download of the latest Discord export.
if (isset($_GET['dl']) && $me !== null && !empty($_SESSION['dc_export']) && is_array($_SESSION['dc_export'])) {
    $exp = $_SESSION['dc_export'];
    $out = [
        'exported_at' => gmdate('c', (int)($exp['at'] ?? time())),
        'kevbin_user' => $me['username'],
        'discord' => $exp,
    ];
    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename="discord-export-' . gmdate('Ymd-His', (int)($exp['at'] ?? time())) . '.json"');
    echo json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

// Clear the cached export.
if ($me !== null && isset($_GET['clear'])) {
    unset($_SESSION['dc_export']);
    redirect('dashboard.php');
}

$exp = ($me !== null && !empty($_SESSION['dc_export']) && is_array($_SESSION['dc_export'])) ? $_SESSION['dc_export'] : null;
$expUser = is_array($exp) ? (array)($exp['user'] ?? []) : [];
$expGuilds = is_array($exp) ? (array)($exp['guilds'] ?? []) : [];
$expRels = is_array($exp) ? (array)($exp['relationships'] ?? []) : [];
$expConns = is_array($exp) ? (array)($exp['connections'] ?? []) : [];

$stats = ['pastes' => 0, 'links' => 0, 'uploads' => 0];
if ($me !== null) {
    try {
        $pdo = db();
        $uid = (int)$me['id'];
        $st = $pdo->prepare('SELECT COUNT(*) FROM pastes WHERE user_id = ?');
        $st->execute([$uid]);
        $stats['pastes'] = (int)$st->fetchColumn();
        $st = $pdo->prepare('SELECT COUNT(*) FROM links WHERE user_id = ?');
        $st->execute([$uid]);
        $stats['links'] = (int)$st->fetchColumn();
        $st = $pdo->prepare('SELECT COUNT(*) FROM uploads WHERE user_id = ?');
        $st->execute([$uid]);
        $stats['uploads'] = (int)$st->fetchColumn();
    } catch (Throwable $t) {
        $stats = ['pastes' => 0, 'links' => 0, 'uploads' => 0];
    }
}

$tier = premium_tier($me);
$relLabels = [
    0 => 'DM partner',
    1 => 'Friend',
    2 => 'Blocked',
    3 => 'Incoming request',
    4 => 'Outgoing request',
    5 => 'Ignored',
];

page_header('Dashboard');
?>
<div class="container" style="max-width: 1050px;">
    <div class="text-center mb-4">
        <h1 class="h3 mb-2">Your free dashboard</h1>
        <p class="text-secondary mb-0">Everything core on KevBin is <strong>free</strong> — pastes, links, file uploads, file analysis and the full tools suite. This page is your hub.</p>
    </div>

    <?php if ($me === null): ?>
    <div class="alert alert-info text-center">
        <strong>Log in or register to unlock your stats and the Discord data export.</strong> Creating an account is free and takes seconds.
        <div class="mt-2">
            <a class="btn btn-sm btn-primary" href="<?= e(url('login.php')) ?>">Log in</a>
            <a class="btn btn-sm btn-outline-light" href="<?= e(url('register.php')) ?>">Register</a>
        </div>
    </div>
    <?php else: ?>
    <div class="card mb-4">
        <div class="card-body d-flex align-items-center gap-3 flex-wrap">
            <div style="width:52px;height:52px;border-radius:50%;overflow:hidden;flex:0 0 52px;background:linear-gradient(135deg,#5865f2,#9b59b6);display:flex;align-items:center;justify-content:center;font-weight:700;color:#fff;">
                <?php if (!empty($me['pfp'])): ?>
                    <img src="<?= e($me['pfp']) ?>" alt="" style="width:52px;height:52px;object-fit:cover;" loading="lazy">
                <?php else: ?>
                    <?= e(mb_strtoupper(mb_substr($me['username'], 0, 1))) ?>
                <?php endif; ?>
            </div>
            <div class="flex-grow-1">
                <div class="fw-bold">@<?= e($me['username']) ?>
                    <?php if ($tier !== ''): ?><?= premium_badge($me) ?><?php endif; ?>
                </div>
                <div class="text-secondary small">Member since <?= e(gmdate('Y-m-d', strtotime((string)$me['created_at'] . ' UTC'))) ?> · ID #<?= (int)$me['id'] ?></div>
                <?php if ($tier === ''): ?>
                    <div class="text-secondary small mt-1">You're on the free tier — <a href="support.php">see premium perks</a> (optional, everything core stays free).</div>
                <?php endif; ?>
            </div>
            <div class="text-center px-3" style="min-width:70px;"><div class="h4 mb-0"><?= number_format($stats['pastes']) ?></div><div class="small text-secondary">Pastes</div></div>
            <div class="text-center px-3" style="min-width:70px;"><div class="h4 mb-0"><?= number_format($stats['links']) ?></div><div class="small text-secondary">Links</div></div>
            <div class="text-center px-3" style="min-width:70px;"><div class="h4 mb-0"><?= number_format($stats['uploads']) ?></div><div class="small text-secondary">Files</div></div>
        </div>
    </div>
    <?php endif; ?>

    <h2 class="h5 mb-3">Quick actions <span class="badge bg-success align-middle">FREE</span></h2>
    <div class="row g-3 mb-4">
        <div class="col-sm-6 col-lg-4">
            <div class="card h-100"><div class="card-body">
                <h3 class="h6 mb-1">New Paste</h3>
                <p class="text-secondary small mb-2">Paste &amp; share text. Free, anonymous, untraceable.</p>
                <a class="btn btn-sm btn-outline-light" href="<?= e(url('index.php?new=1')) ?>">Open editor</a>
            </div></div>
        </div>
        <div class="col-sm-6 col-lg-4">
            <div class="card h-100 border-warning"><div class="card-body">
                <h3 class="h6 mb-1">Upload Files</h3>
                <p class="text-secondary small mb-2">Host any file (all types except php/js/html) — auto-mirrored to external hosters, never stored on KevBin.</p>
                <form method="post" action="files.php" enctype="multipart/form-data" class="d-flex gap-2 flex-wrap">
                    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                    <input class="form-control form-control-sm" style="max-width:220px;" type="file" name="file" required>
                    <button class="btn btn-sm btn-warning" type="submit">Upload ⬆</button>
                </form>
            </div></div>
        </div>
        <div class="col-sm-6 col-lg-4">
            <div class="card h-100"><div class="card-body">
                <h3 class="h6 mb-1">File Analysis</h3>
                <p class="text-secondary small mb-2">Hashes, MIME, entropy, text/binary and magic-number signature — file never stored.</p>
                <a class="btn btn-sm btn-outline-light" href="<?= e(url('file_analysis.php')) ?>">Analyse a file</a>
            </div></div>
        </div>
        <div class="col-sm-6 col-lg-4">
            <div class="card h-100"><div class="card-body">
                <h3 class="h6 mb-1">Shorten URLs</h3>
                <p class="text-secondary small mb-2">Tiny links with click stats.</p>
                <a class="btn btn-sm btn-outline-light" href="<?= e(url('short.php')) ?>">Shorten</a>
            </div></div>
        </div>
        <div class="col-sm-6 col-lg-4">
            <div class="card h-100"><div class="card-body">
                <h3 class="h6 mb-1">Link Spoof / Obfuscator</h3>
                <p class="text-secondary small mb-2">Obfuscate URLs with encoding tricks and chain through multiple shorteners. Phishing awareness.</p>
                <a class="btn btn-sm btn-outline-light" href="<?= e(url('tools/link-spoof/')) ?>">Open</a>
            </div></div>
        </div>
        <div class="col-sm-6 col-lg-4">
            <div class="card h-100"><div class="card-body">
                <h3 class="h6 mb-1">Browse &amp; Search</h3>
                <p class="text-secondary small mb-2">Find public pastes or explore the wiki.</p>
                <a class="btn btn-sm btn-outline-light" href="<?= e(url('browse.php')) ?>">Browse</a>
                <a class="btn btn-sm btn-outline-light ms-1" href="<?= e(url('search.php')) ?>">Search</a>
            </div></div>
        </div>
        <div class="col-sm-6 col-lg-4">
            <div class="card h-100"><div class="card-body">
                <h3 class="h6 mb-1">100+ Free Tools</h3>
                <p class="text-secondary small mb-2">Hashing, encoders, OSINT, dev tools and more.</p>
                <a class="btn btn-sm btn-outline-light" href="<?= e(url('tools/')) ?>">Open tools</a>
            </div></div>
        </div>
    </div>

    <h2 class="h5 mb-3">Discord data export <span class="badge bg-success align-middle">FREE</span></h2>
    <div class="card mb-5">
        <div class="card-body">
            <?php if ($me === null): ?>
                <p class="mb-2">Connect your Discord to fetch and download <strong>your own</strong> data: profile, servers + their IDs and connections. <a href="login.php">Log in</a> to get started.</p>
                <p class="text-secondary small mb-0">Note: Discord offers no OAuth scope for reading messages or your friends list (<code>messages.read</code> doesn't exist and <code>relationships.read</code> is restricted), so only what the API actually grants is exported here.</p>
            <?php elseif ($exp !== null && !empty($expUser['id'])): ?>
                <div class="d-flex align-items-center gap-3 flex-wrap mb-3">
                    <?php if (!empty($expUser['avatar'])): ?>
                        <img src="<?= e($expUser['avatar']) ?>" alt="" style="width:56px;height:56px;border-radius:50%;object-fit:cover;" loading="lazy">
                    <?php endif; ?>
                    <div class="flex-grow-1">
                        <div class="fw-bold"><?= e($expUser['global_name'] !== '' ? $expUser['global_name'] : $expUser['username']) ?> <span class="text-secondary fw-normal">@<?= e($expUser['username']) ?></span></div>
                        <div class="text-secondary small">Discord ID: <code><?= e($expUser['id']) ?></code> · exported <?= e(gmdate('Y-m-d H:i', (int)($exp['at'] ?? 0))) ?> UTC</div>
                    </div>
                    <div class="text-center px-3" style="min-width:70px;"><div class="h4 mb-0"><?= count($expGuilds) ?></div><div class="small text-secondary">Servers</div></div>
                    <div class="text-center px-3" style="min-width:70px;"><div class="h4 mb-0"><?= count($expRels) ?></div><div class="small text-secondary">Relationships</div></div>
                    <div class="text-center px-3" style="min-width:70px;"><div class="h4 mb-0"><?= count($expConns) ?></div><div class="small text-secondary">Connections</div></div>
                </div>

                <?php if (count($expGuilds) > 0): ?>
                <h6 class="mt-3 mb-2">Servers (<?= count($expGuilds) ?>)</h6>
                <div class="table-responsive"><table class="table table-sm align-middle">
                    <thead><tr><th>Server</th><th>Server ID</th><th></th></tr></thead>
                    <tbody>
                    <?php foreach ($expGuilds as $g): ?>
                        <tr>
                            <td><?php if (!empty($g['icon'])): ?><img src="https://cdn.discordapp.com/icons/<?= e($g['id']) ?>/<?= e($g['icon']) ?>.png?size=32" alt="" style="width:24px;height:24px;border-radius:50%;margin-right:8px;vertical-align:-6px;" loading="lazy"><?php endif; ?><?= e($g['name']) ?></td>
                            <td><code><?= e($g['id']) ?></code></td>
                            <td class="text-secondary small"><?= !empty($g['owner']) ? 'owner' : '' ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table></div>
                <?php endif; ?>

                <?php if (count($expRels) > 0): ?>
                <h6 class="mt-3 mb-2">Friends &amp; DM partners (<?= count($expRels) ?>)</h6>
                <div class="table-responsive" style="max-height:300px;overflow-y:auto;"><table class="table table-sm align-middle">
                    <thead><tr><th>Display name</th><th>Username</th><th>User ID</th><th>Type</th></tr></thead>
                    <tbody>
                    <?php foreach ($expRels as $r): ?>
                        <tr>
                            <td><?= e($r['global_name'] !== '' ? $r['global_name'] : $r['username']) ?></td>
                            <td><?= $r['username'] !== '' ? '@' . e($r['username']) : '<span class="text-secondary">—</span>' ?></td>
                            <td><code><?= e($r['id']) ?></code></td>
                            <td><?= e($relLabels[(int)$r['type']] ?? ('Type ' . (int)$r['type'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table></div>
                <?php endif; ?>

                <?php if (count($expConns) > 0): ?>
                <h6 class="mt-3 mb-2">Connected accounts (<?= count($expConns) ?>)</h6>
                <div class="table-responsive"><table class="table table-sm align-middle">
                    <thead><tr><th>Service</th><th>Account</th><th>Verified</th></tr></thead>
                    <tbody>
                    <?php foreach ($expConns as $c): ?>
                        <tr>
                            <td><?= e($c['type']) ?></td>
                            <td><?= e($c['name']) ?></td>
                            <td><?= $c['verified'] ? '<span class="badge bg-success">verified</span>' : '<span class="badge bg-secondary">not verified</span>' ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table></div>
                <?php endif; ?>

                <?php if (count($expGuilds) === 0 && count($expRels) === 0 && count($expConns) === 0): ?>
                    <p class="text-secondary mb-2">The API returned your profile but no servers, relationships or connections (this happens when a scope wasn't granted or you have none).</p>
                <?php endif; ?>

                <div class="d-flex gap-2 flex-wrap mt-3">
                    <a class="btn btn-sm btn-success" href="<?= e(url('dashboard.php?dl=1')) ?>">⬇ Download JSON</a>
                    <a class="btn btn-sm btn-outline-light" href="<?= e(url('discord_oauth.php?export=1')) ?>">Refresh data</a>
                    <a class="btn btn-sm btn-outline-secondary" href="<?= e(url('dashboard.php?clear=1')) ?>" onclick="return confirm('Clear the cached export from this session?');">Clear</a>
                </div>
                <p class="text-secondary small mt-2 mb-0">This is <strong>your</strong> data, fetched straight from Discord for your own account and only stored in your browser session until you clear it.</p>
            <?php else: ?>
                <p class="mb-2">One click on the Discord button and the Dashboard will ask for the read scopes the Discord API actually grants:</p>
                <ul class="small mb-3">
                    <li><strong>identify</strong> — your profile (username, display name, user ID, avatar)</li>
                    <li><strong>guilds</strong> — every server you're in, with its server ID</li>
                    <li><strong>connections</strong> — your connected accounts (e.g. Spotify, GitHub)</li>
                    <li><strong>guilds.join</strong> — lets you be added to KevBin's server (as on normal login)</li>
                </ul>
                <p class="mb-2">Then it fetches <strong>everything the API returns</strong> and lists every dataset below, with a one-click JSON download. Nothing is posted to Discord, and only your own account's data is read.</p>
                <a class="btn btn-primary" href="<?= e(url('discord_oauth.php?export=1')) ?>">Connect Discord &amp; fetch everything</a>
                <p class="text-secondary small mt-3 mb-0">Heads-up: Discord offers no <code>messages.read</code> OAuth scope, and <code>relationships.read</code> (friends list) + <code>guilds.members.read</code> are restricted scopes that Discord rejects for normal apps — so message and friends-list export aren't possible through the API. This page lists every dataset that is actually fetchable: your profile, your servers + IDs, and your connections.</p>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php
page_footer();
