<?php
require_once __DIR__ . '/../../functions.php';

start_session();
$cfg = $GLOBALS['CFG'];
$me = current_user();
$manageKey = trim((string)($_GET['m'] ?? ''));

// ---- helpers ---------------------------------------------------------

function lt_row(mixed $v): string
{
    return $v !== null && $v !== '' ? (string)$v : '—';
}

function lt_badge(int $on, string $label, string $color): string
{
    if (!$on) {
        return '';
    }
    return '<span class="lt-badge" style="background:' . $color . '1a;border:1px solid ' . $color . '66;color:' . $color . '">' . $label . '</span>';
}

function lt_fetch_link(string $code): ?array
{
    $stmt = db()->prepare('SELECT id, code, target_url, title, tracking, clicks, last_click, created_at, user_id, manage_key FROM links WHERE code = ?');
    $stmt->execute([$code]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function lt_stats(array $link): array
{
    $lid = (int)$link['id'];
    $out = [
        'clicks' => 0, 'unique_ip' => 0, 'countries' => [], 'devices' => [],
        'browsers' => [], 'isp' => [], 'hourly' => [], 'bots' => 0, 'recent' => [],
    ];
    try {
        $out['clicks'] = (int)db()->query('SELECT COUNT(*) FROM link_clicks WHERE link_id = ' . $lid)->fetchColumn();
        $out['unique_ip'] = (int)db()->query('SELECT COUNT(DISTINCT ip) FROM link_clicks WHERE link_id = ' . $lid . ' AND ip IS NOT NULL')->fetchColumn();
        foreach ([['countries', 'country', 5], ['devices', 'device', 4], ['browsers', 'browser', 4], ['isp', 'isp', 4]] as [$key, $col, $limit]) {
            $s = db()->prepare("SELECT $col AS k, COUNT(*) AS c FROM link_clicks WHERE link_id = ? AND $col IS NOT NULL GROUP BY $col ORDER BY c DESC LIMIT " . (int)$limit);
            $s->execute([$lid]);
            $out[$key] = $s->fetchAll();
        }
        $s = db()->prepare("SELECT DATE_FORMAT(created_at, '%Y-%m-%d %H:00') AS h, COUNT(*) AS c FROM link_clicks WHERE link_id = ? AND created_at > DATE_SUB(UTC_TIMESTAMP(), INTERVAL 7 DAY) GROUP BY h ORDER BY h ASC LIMIT 168");
        $s->execute([$lid]);
        $out['hourly'] = $s->fetchAll();
        $s = db()->prepare('SELECT COALESCE(SUM(is_bot),0) AS bots FROM link_clicks WHERE link_id = ?');
        $s->execute([$lid]);
        $out['bots'] = (int)(($s->fetch())['bots'] ?? 0);
        $s = db()->prepare('SELECT ip, ua, referer, country, region, city, isp, browser, browser_version, os, os_version, device, is_bot, language, screen, timezone, dpr, touch, hw_concurrency, created_at FROM link_clicks WHERE link_id = ? ORDER BY created_at DESC LIMIT 10');
        $s->execute([$lid]);
        $out['recent'] = $s->fetchAll();
    } catch (Throwable $t) {
        // aggregates are non-fatal
    }
    return $out;
}

// ---- actions ---------------------------------------------------------

$created = null;
$view = null;      // link row currently being shown
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify_or_fail();

    // Delete an existing tracker (owner or manage key).
    if (!empty($_POST['delete_code'])) {
        $dc = (string)$_POST['delete_code'];
        if (preg_match('/^[A-Za-z0-9]{3,16}$/', $dc)) {
            $row = lt_fetch_link($dc);
            if ($row && link_manage_ok($row, $me, $manageKey)) {
                db()->prepare('DELETE FROM link_clicks WHERE link_id = ?')->execute([(int)$row['id']]);
                db()->prepare('DELETE FROM links WHERE id = ?')->execute([(int)$row['id']]);
                log_activity('link_delete', $dc);
                flash_set('success', 'Tracker link deleted.');
            }
        }
        redirect('index.php');
    }

    if (!rate_limit_check('link', (int)$cfg['link_rate_limit'], (int)$cfg['rate_window_seconds'])) {
        friendly_error('Rate limit reached: max ' . $cfg['link_rate_limit'] . ' tracker links per 10 minutes per IP.', 429);
    }
    $target = trim((string)($_POST['target'] ?? ''));
    $custom = trim((string)($_POST['code'] ?? ''));
    $title = mb_substr(trim((string)($_POST['title'] ?? '')), 0, 120);

    $parts = parse_url($target);
    $scheme = is_array($parts) ? strtolower((string)($parts['scheme'] ?? '')) : '';
    if (strpbrk($target, "\r\n\0") !== false) {
        $error = 'URL contains invalid characters.';
    } elseif (!in_array($scheme, ['http', 'https'], true) || empty($parts['host'] ?? '')) {
        $error = 'Enter a valid URL starting with http:// or https://';
    } elseif (mb_strlen($target) > 2048) {
        $error = 'URL is too long (max 2048 chars).';
    } elseif ($custom !== '' && !preg_match('/^[A-Za-z0-9]{3,16}$/', $custom)) {
        $error = 'Custom code must be 3–16 letters/numbers (no spaces or symbols).';
    } else {
        $pdo = db();
        $code = $custom !== '' ? $custom : generate_link_code(5);
        $stmt = $pdo->prepare('SELECT 1 FROM links WHERE code = ?');
        $stmt->execute([$code]);
        if ($stmt->fetch()) {
            $error = 'That code is already taken. Try another one or leave it blank for a random code.';
        } else {
            $key = $me !== null ? null : bin2hex(random_bytes(16));
            $pdo->prepare('INSERT INTO links (code, user_id, manage_key, target_url, title, tracking) VALUES (?, ?, ?, ?, ?, 1)')
                ->execute([$code, $me !== null ? (int)$me['id'] : null, $key, $target, $title !== '' ? $title : null]);
            log_activity('tool_link_tracker', $code . ' -> ' . mb_substr($target, 0, 100));
            $created = [
                'code' => $code,
                'key' => $key,
                'url' => link_short_url($code),
            ];
            $view = lt_fetch_link($code);
        }
    }
} elseif (($code = trim((string)($_GET['code'] ?? ''))) !== '') {
    if (preg_match('/^[A-Za-z0-9]{3,16}$/', $code)) {
        $v = lt_fetch_link($code);
        if ($v && link_manage_ok($v, $me, $manageKey)) {
            $view = $v;
        }
    }
}

$stats = $view !== null ? lt_stats($view) : null;

page_header('Link Tracker');
?>
<style>
.lt-badge { display:inline-block; padding:1px 7px; border-radius:5px; font-size:.68rem; font-weight:600; margin:1px; text-transform:uppercase; letter-spacing:.3px; }
.lt-stat { text-align:center; padding:.9rem .4rem; background:rgba(255,255,255,.03); border:1px solid var(--line); border-radius:10px; }
.lt-stat .v { font-size:1.35rem; font-weight:700; }
.lt-stat .k { font-size:.7rem; color:var(--dim); text-transform:uppercase; letter-spacing:.8px; margin-top:2px; }
.lt-bar { background:rgba(255,255,255,.06); border-radius:4px; height:8px; overflow:hidden; margin-top:3px; }
.lt-bar > div { height:100%; background:linear-gradient(90deg,var(--accent1),var(--accent2)); }
.lt-hours { display:flex; align-items:flex-end; gap:2px; height:64px; }
.lt-hours div { flex:1; background:rgba(88,101,242,.5); border-radius:2px 2px 0 0; position:relative; }
.lt-hours div:hover { background:#6872f2; }
.lt-hours div span { position:absolute; bottom:calc(100% + 3px); left:50%; transform:translateX(-50%); font-size:.6rem; color:var(--dim); white-space:nowrap; display:none; }
.lt-hours div:hover span { display:block; }
</style>
<div class="container" style="max-width: 1200px;">
    <h1 class="h4 mb-1 reveal in-view">📈 Link Tracker</h1>
    <p class="text-secondary mb-4 reveal in-view">Create a redirect link that quietly records every click — visitor IP, approximate location &amp; ISP, browser, OS, device, screen, timezone, language and a fingerprint beacon — then watch the analytics build up here in real time.</p>

    <div class="row g-4">
        <div class="col-lg-4">
            <div class="card reveal in-view"><div class="card-body">
                <h2 class="h6 mb-3">New tracked link</h2>
                <?php if ($error !== null): ?>
                    <div class="alert alert-danger py-2"><?= e($error) ?></div>
                <?php endif; ?>
                <form method="post" action="index.php">
                    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                    <div class="mb-3">
                        <label class="form-label small text-secondary mb-1">Redirect destination</label>
                        <input class="form-control" name="target" placeholder="https://example.com/page" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small text-secondary mb-1">Custom code (optional)</label>
                        <input class="form-control" name="code" maxlength="16" placeholder="my-link" pattern="[A-Za-z0-9]{3,16}">
                        <div class="form-text">3–16 letters/numbers. Leave empty for a random code.</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small text-secondary mb-1">Title (optional)</label>
                        <input class="form-control" name="title" maxlength="120" placeholder="What is this link for?">
                    </div>
                    <button class="btn btn-primary w-100" type="submit">Create tracker link</button>
                </form>
                <div class="alert alert-secondary small mt-3 mb-0">
                    Tracking is always on for tracker links. Location is approximate (ISP-level), not the visitor's exact address.
                    <strong>Only log links you own or are authorized to monitor.</strong>
                </div>
            </div></div>
        </div>

        <div class="col-lg-8">
            <?php if ($view === null): ?>
                <div class="card reveal in-view"><div class="card-body">
                    <h2 class="h6 mb-2">Your tracker links</h2>
                    <p class="text-secondary small mb-3">Create one on the left — or, if you created one anonymously earlier, paste its code + manage key below to pull up its analytics here.</p>
                    <form method="get" action="index.php" class="row g-2">
                        <div class="col-md-5"><input class="form-control" name="code" placeholder="Code (e.g. aB3xK)" pattern="[A-Za-z0-9]{3,16}"></div>
                        <div class="col-md-5"><input class="form-control" name="m" placeholder="Manage key (if anonymous)"></div>
                        <div class="col-md-2"><button class="btn btn-outline-light w-100" type="submit">View</button></div>
                    </form>
                    <?php if ($me !== null): ?>
                        <a class="btn btn-sm btn-outline-light mt-3" href="<?= e(url('links.php')) ?>">Open full "My Links" manager →</a>
                    <?php endif; ?>
                </div></div>
            <?php else: ?>
                <?php
                $shortUrl = link_short_url($view['code']);
                $manageQ = $me === null && $view['manage_key'] !== null ? '&m=' . urlencode($view['manage_key']) : '';
                $hours = $stats['hourly'];
                $hourMx = max(1, max(array_map(function ($r) { return (int)$r['c']; }, $hours) ?: [1]));
                ?>
                <div class="card reveal in-view"><div class="card-body">
                    <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
                        <div>
                            <h2 class="h6 mb-1"><?= $view['title'] !== null ? e($view['title']) : 'Tracker link' ?> <span class="text-secondary small">/s/<?= e($view['code']) ?></span></h2>
                            <div class="small text-secondary" style="word-break:break-all">→ <?= e($view['target_url']) ?></div>
                        </div>
                        <div class="d-flex gap-2">
                            <a class="btn btn-sm btn-outline-light" href="<?= e(url('link_info.php?code=' . $view['code'] . $manageQ)) ?>">Full dashboard</a>
                            <form method="post" action="index.php?code=<?= e($view['code']) ?><?= e($manageQ) ?>"
                                onsubmit="return confirm('Delete this link and all its click data?');">
                                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                                <input type="hidden" name="delete_code" value="<?= e($view['code']) ?>">
                                <button class="btn btn-sm btn-outline-danger">Delete</button>
                            </form>
                        </div>
                    </div>
                    <div class="input-group input-group-sm mb-2">
                        <input id="lt-short" class="form-control" readonly value="<?= e($shortUrl) ?>">
                        <button class="btn btn-outline-light" onclick="ltCopy()">Copy</button>
                    </div>
                    <?php if ($me === null && $view['manage_key'] !== null): ?>
                        <div class="alert alert-secondary small py-2 mb-2">
                            Anonymous manage link (save it — it is the only way back in):
                            <code class="d-block mt-1"><?= e($cfg['base_url'] . 'tools/link-tracker/?code=' . $view['code'] . '&m=' . $view['manage_key']) ?></code>
                        </div>
                    <?php endif; ?>

                    <div class="row g-3 mb-3">
                        <div class="col-md-3 col-6"><div class="lt-stat"><div class="v"><?= number_format((int)$stats['clicks']) ?></div><div class="k">Clicks</div></div></div>
                        <div class="col-md-3 col-6"><div class="lt-stat"><div class="v"><?= number_format((int)$stats['unique_ip']) ?></div><div class="k">Unique IPs</div></div></div>
                        <div class="col-md-3 col-6"><div class="lt-stat"><div class="v"><?= count($stats['countries']) ?></div><div class="k">Countries</div></div></div>
                        <div class="col-md-3 col-6"><div class="lt-stat"><div class="v"><?= $stats['clicks'] > 0 ? round($stats['bots'] / $stats['clicks'] * 100) : 0 ?>%</div><div class="k">Bots / auto</div></div></div>
                    </div>

                    <?php if ((int)$stats['clicks'] === 0): ?>
                        <p class="text-secondary small mb-0">No clicks yet — share <code>/s/<?= e($view['code']) ?></code> and they'll show up here.</p>
                    <?php else: ?>
                        <div class="row g-3 mb-3">
                            <div class="col-lg-4 col-md-6">
                                <div class="small text-secondary mb-1">Top countries</div>
                                <?php $mx = max(1, (int)($stats['countries'][0]['c'] ?? 1)); foreach ($stats['countries'] as $r): ?>
                                    <div class="mb-2">
                                        <div class="d-flex justify-content-between small"><span><?= e(lt_row($r['k'])) ?></span><span><?= (int)$r['c'] ?></span></div>
                                        <div class="lt-bar"><div style="width:<?= round((int)$r['c'] / $mx * 100) ?>%"></div></div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <div class="col-lg-4 col-md-6">
                                <div class="small text-secondary mb-1">Devices</div>
                                <?php foreach ($stats['devices'] as $r): ?>
                                    <div class="d-flex justify-content-between small mb-1"><span><?= e(lt_row($r['k'])) ?></span><span><?= (int)$r['c'] ?></span></div>
                                <?php endforeach; ?>
                                <div class="small text-secondary mt-3 mb-1">Browsers</div>
                                <?php foreach ($stats['browsers'] as $r): ?>
                                    <div class="d-flex justify-content-between small mb-1"><span><?= e(lt_row($r['k'])) ?></span><span><?= (int)$r['c'] ?></span></div>
                                <?php endforeach; ?>
                            </div>
                            <div class="col-lg-4 col-md-12">
                                <div class="small text-secondary mb-1">Last 7 days (hourly)</div>
                                <div class="lt-hours">
                                    <?php foreach ($hours as $r): ?>
                                        <div style="height:<?= max(3, round((int)$r['c'] / $hourMx * 100)) ?>%"><span><?= e(substr((string)$r['h'], 11, 2) . ':00') ?></span></div>
                                    <?php endforeach; ?>
                                </div>
                                <div class="text-secondary small mt-1"><?= count($hours) ?> hours of activity</div>
                            </div>
                        </div>

                        <h3 class="h6 mb-2">Latest clicks</h3>
                        <div class="table-responsive">
                            <table class="table table-dark table-hover align-middle">
                                <thead><tr><th>Time (UTC)</th><th>IP</th><th>Location</th><th>Device</th><th>Signals</th></tr></thead>
                                <tbody>
                                <?php foreach ($stats['recent'] as $c): ?>
                                    <?php
                                    $locStr = trim(implode(', ', array_filter([$c['city'], $c['region'], $c['country']])));
                                    $ico = $c['device'] === 'Phone' ? '📱' : ($c['device'] === 'Tablet' ? '📟' : ($c['device'] === 'Bot' ? '🤖' : '🖥'));
                                    $browserFull = trim(lt_row($c['browser']) . ($c['browser_version'] ? ' ' . $c['browser_version'] : ''));
                                    $osFull = trim(lt_row($c['os']) . ($c['os_version'] ? ' ' . $c['os_version'] : ''));
                                    $sig = [];
                                    if ($c['screen']) $sig[] = '🖥 ' . $c['screen'];
                                    if ($c['timezone']) $sig[] = '🕐 ' . $c['timezone'];
                                    if ($c['language']) $sig[] = '🌐 ' . $c['language'];
                                    if ((int)$c['touch']) $sig[] = 'touch';
                                    if ($c['hw_concurrency']) $sig[] = $c['hw_concurrency'] . ' core';
                                    ?>
                                    <tr>
                                        <td class="small text-nowrap"><?= e(gmdate('Y-m-d H:i:s', strtotime($c['created_at'] . ' UTC'))) ?></td>
                                        <td><code><?= e((string)$c['ip']) ?></code></td>
                                        <td class="small"><?= $locStr !== '' ? e($locStr) : '<span class="text-secondary">unknown</span>' ?></td>
                                        <td class="small"><span><?= $ico ?></span> <?= e($browserFull) ?><div class="text-secondary" style="font-size:.72rem"><?= e($osFull) ?> · <?= e(lt_row($c['device'])) ?><?= (int)$c['is_bot'] ? ' · bot' : '' ?></div></td>
                                        <td class="small text-secondary"><?= $sig ? implode('<br>', array_map('e', $sig)) : '—' ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <p class="text-secondary small mt-2 mb-0">Approximate location from ISP data; fingerprint signals can be absent for bots, link previews and curl.</p>
                    <?php endif; ?>
                </div></div>
            <?php endif; ?>
        </div>
    </div>

    <div class="alert alert-secondary mt-4 reveal">
        <strong>Heads up:</strong> this is an analytics-style tracker. Only log visits to links you own or
        are authorized to monitor, and keep the location "approximate" — it comes from the visitor's ISP
        info, not their exact address. The fingerprint beacon runs on the redirect page and records
        screen / timezone / canvas-style signals; visitors are never logged beyond the click record.
    </div>
</div>
<script>
function ltCopy() {
    var t = document.getElementById('lt-short');
    t.select();
    if (navigator.clipboard) navigator.clipboard.writeText(t.value); else document.execCommand('copy');
}
</script>
<?php page_footer(); ?>