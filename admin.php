<?php
require_once __DIR__ . '/functions.php';

start_session();
require_staff();

$me = current_user();
$pdo = db();
$isAdmin = is_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify_or_fail();
    $action = (string)($_POST['action'] ?? '');

    // ——— Role management (admins only) ———
    if (in_array($action, ['promotemod', 'promoteadmin', 'demote', 'suspend', 'reinstate', 'deleteuser'], true)) {
        if (!$isAdmin) {
            flash_set('error', 'Only admins can manage users.');
            redirect('admin.php?tab=requests');
        }
        $target = (int)($_POST['user_id'] ?? 0);
        $stmt = $pdo->prepare('SELECT id, username, role FROM users WHERE id = ?');
        $stmt->execute([$target]);
        $targetUser = $stmt->fetch();
        if (!$targetUser) {
            flash_set('error', 'User not found.');
        } elseif ($action === 'deleteuser') {
            if ($target === (int)$me['id']) {
                flash_set('error', 'You cannot delete yourself.');
            } else {
                $pdo->prepare('UPDATE pastes SET user_id = NULL WHERE user_id = ?')->execute([$target]);
                $pdo->prepare('DELETE FROM pins WHERE user_id = ?')->execute([$target]);
                $pdo->prepare('DELETE FROM users WHERE id = ?')->execute([$target]);
                log_activity('admin_delete_user', $targetUser['username']);
                flash_set('success', 'User deleted. Their pastes are now anonymous.');
            }
        } elseif ($target === (int)$me['id']) {
            flash_set('error', 'You cannot change your own role.');
        } elseif ($action === 'promoteadmin') {
            $pdo->prepare("UPDATE users SET role = 'admin' WHERE id = ?")->execute([$target]);
            log_activity('admin_promote', $targetUser['username']);
            flash_set('success', $targetUser['username'] . ' is now an admin.');
        } elseif ($action === 'promotemod') {
            $pdo->prepare("UPDATE users SET role = 'moderator' WHERE id = ?")->execute([$target]);
            log_activity('moderator_promote', $targetUser['username']);
            flash_set('success', $targetUser['username'] . ' is now a moderator.');
        } elseif ($action === 'demote') {
            $pdo->prepare("UPDATE users SET role = 'user' WHERE id = ?")->execute([$target]);
            log_activity('admin_demote', $targetUser['username']);
            flash_set('success', $targetUser['username'] . ' is now a regular user.');
        } elseif ($action === 'suspend') {
            $pdo->prepare("UPDATE users SET status = 'suspended' WHERE id = ?")->execute([$target]);
            log_activity('admin_suspend', $targetUser['username']);
            flash_set('success', $targetUser['username'] . ' suspended.');
        } elseif ($action === 'reinstate') {
            $pdo->prepare("UPDATE users SET status = 'active' WHERE id = ?")->execute([$target]);
            log_activity('admin_reinstate', $targetUser['username']);
            flash_set('success', $targetUser['username'] . ' reinstated.');
        }
        redirect('admin.php?tab=users');
    }

    // ——— Paste actions (admins only) ———
    if (in_array($action, ['pastepin', 'pasteunpin', 'pastedelete', 'pastecolor'], true)) {
        if (!$isAdmin) {
            flash_set('error', 'Only admins can manage pastes.');
            redirect('admin.php?tab=requests');
        }
        $pasteId = (string)($_POST['paste_id'] ?? '');
        if (!preg_match('/^[A-Za-z0-9]{1,12}$/', $pasteId)) {
            flash_set('error', 'Invalid paste.');
            redirect('admin.php#pastes');
        }
        if ($action === 'pastepin') {
            $pdo->prepare('UPDATE pastes SET pin = 1 WHERE id = ?')->execute([$pasteId]);
            log_activity('admin_pin_paste', $pasteId);
            flash_set('success', 'Paste pinned.');
        } elseif ($action === 'pasteunpin') {
            $pdo->prepare('UPDATE pastes SET pin = 0 WHERE id = ?')->execute([$pasteId]);
            log_activity('admin_unpin_paste', $pasteId);
            flash_set('success', 'Paste unpinned.');
        } elseif ($action === 'pastedelete') {
            $pdo->prepare('DELETE FROM pins WHERE paste_id = ?')->execute([$pasteId]);
            $pdo->prepare('DELETE FROM pastes WHERE id = ?')->execute([$pasteId]);
            log_activity('admin_delete_paste', $pasteId);
            flash_set('success', 'Paste deleted.');
        } elseif ($action === 'pastecolor') {
            $color = clean_hex_color((string)($_POST['paste_color'] ?? ''));
            $pdo->prepare('UPDATE pastes SET paste_color = ? WHERE id = ?')->execute([$color, $pasteId]);
            log_activity('admin_paste_color', $pasteId . ' ' . ($color !== '' ? $color : 'default'));
            flash_set('success', 'Paste color updated.');
        }
        redirect('admin.php?tab=pastes');
    }

    // ——— IP bans (admins only) ———
    if (in_array($action, ['banip', 'unbanip'], true)) {
        if (!$isAdmin) {
            flash_set('error', 'Only admins can manage IP bans.');
            redirect('admin.php?tab=requests');
        }
        $ip = trim((string)($_POST['ip'] ?? ''));
        if ($ip === '') {
            $ip = client_ip();
        }
        if ($action === 'banip') {
            $reason = trim((string)($_POST['reason'] ?? ''));
            if (filter_var($ip, FILTER_VALIDATE_IP) === false) {
                flash_set('error', 'Invalid IP address.');
            } elseif (ban_ip($ip, $reason, (int)$me['id'])) {
                log_activity('admin_ban_ip', $ip . ' ' . $reason);
                flash_set('success', 'IP ' . $ip . ' banned.');
            } else {
                flash_set('error', 'That IP is already banned.');
            }
        } else {
            if (unban_ip($ip)) {
                log_activity('admin_unban_ip', $ip);
                flash_set('success', 'IP ' . $ip . ' unbanned.');
            } else {
                flash_set('error', 'Could not unban.');
            }
        }
        redirect('admin.php?tab=bans');
    }

    // ——— Request queue: DMCA / takedown / privacy / abuse (admins + moderators) ———
    if (in_array($action, ['reportresolve', 'reportreopen', 'reportdelete'], true)) {
        $target = (int)($_POST['report_id'] ?? 0);
        $stmt = $pdo->prepare('SELECT id, type, name, target_url FROM reports WHERE id = ?');
        $stmt->execute([$target]);
        $rep = $stmt->fetch();
        if (!$rep) {
            flash_set('error', 'Request not found.');
        } elseif ($action === 'reportresolve') {
            $resolution = mb_substr(trim((string)($_POST['resolution'] ?? '')), 0, 1000);
            $pdo->prepare(
                "UPDATE reports SET status = 'resolved', resolution = ?, resolved_by = ?, resolved_at = UTC_TIMESTAMP() WHERE id = ?"
            )->execute([$resolution === '' ? null : $resolution, (int)$me['id'], $target]);
            log_activity('report_resolved', $rep['type'] . ' #' . $target . ' ' . $rep['target_url']);
            flash_set('success', 'Request #' . $target . ' marked as resolved.');
        } elseif ($action === 'reportreopen') {
            $pdo->prepare("UPDATE reports SET status = 'open', resolution = NULL, resolved_by = NULL, resolved_at = NULL WHERE id = ?")
                ->execute([$target]);
            log_activity('report_reopened', $rep['type'] . ' #' . $target);
            flash_set('success', 'Request #' . $target . ' reopened.');
        } elseif ($action === 'reportdelete') {
            $pdo->prepare('DELETE FROM reports WHERE id = ?')->execute([$target]);
            log_activity('report_deleted', $rep['type'] . ' #' . $target);
            flash_set('success', 'Request #' . $target . ' deleted.');
        }
        redirect('admin.php?tab=requests');
    }

    flash_set('error', 'Unknown action.');
    redirect('admin.php');
}

// Moderators only see the request queue.
if (!$isAdmin && ($_GET['tab'] ?? '') !== 'requests') {
    redirect('admin.php?tab=requests');
}

$tab = (string)($_GET['tab'] ?? ($isAdmin ? 'users' : 'requests'));

// ——— Quarter data ———
$reports = [];
try {
    $reports = $pdo->query(
        "SELECT r.id, r.type, r.name, r.contact, r.target_url, r.details, r.status, r.ip,
                r.created_at, r.resolution, r.resolved_at, u.username AS resolved_by_name
         FROM reports r
         LEFT JOIN users u ON u.id = r.resolved_by
         ORDER BY (r.status = 'open') DESC, r.created_at ASC"
    )->fetchAll();
} catch (Throwable $t) {
    // reports table missing (old install before auto_migrate) — harmless.
}

$users = [];
$pastes = [];
$bannedIps = [];
if ($isAdmin) {
    $users = $pdo->query(
        'SELECT u.id, u.username, u.role, u.status, u.created_at,
                (SELECT COUNT(*) FROM pastes p WHERE p.user_id = u.id) AS paste_count
         FROM users u ORDER BY u.role DESC, u.username ASC'
    )->fetchAll();

    $pastes = $pdo->query(
        'SELECT p.id, p.title, p.author, p.user_id, p.views, p.pin, p.paste_color, p.created_at,
                (SELECT username FROM users WHERE id = p.user_id) AS owner_name
         FROM pastes p
         ORDER BY p.created_at DESC
         LIMIT 200'
    )->fetchAll();

    try {
        $bannedIps = $pdo->query(
            'SELECT b.id, b.ip, b.reason, b.banned_by, b.created_at, u.username AS banned_by_name
             FROM banned_ips b
             LEFT JOIN users u ON u.id = b.banned_by
             ORDER BY b.created_at DESC'
        )->fetchAll();
    } catch (Throwable $e) {
        // banned_ips table not present yet (run schema_upgrade.sql)
    }
}

$openCount = 0;
foreach ($reports as $r) {
    if ($r['status'] === 'open') {
        $openCount++;
    }
}

page_header($isAdmin ? 'Admin' : 'Staff');
?>
<div class="container" style="max-width: 1100px;">
    <h1 class="h4 mb-3 reveal in-view"><?= $isAdmin ? 'Admin panel' : 'Staff panel' ?></h1>
    <ul class="nav nav-tabs mb-3 reveal in-view">
        <?php if ($isAdmin): ?>
            <li class="nav-item"><a class="nav-link <?= $tab === 'users' ? 'active' : '' ?>" href="<?= e(url('admin.php?tab=users')) ?>">Users</a></li>
            <li class="nav-item"><a class="nav-link <?= $tab === 'pastes' ? 'active' : '' ?>" href="<?= e(url('admin.php?tab=pastes')) ?>#pastes">Pastes</a></li>
            <li class="nav-item"><a class="nav-link <?= $tab === 'bans' ? 'active' : '' ?>" href="<?= e(url('admin.php?tab=bans')) ?>">IP Bans (<?= count($bannedIps) ?>)</a></li>
        <?php endif; ?>
        <li class="nav-item"><a class="nav-link <?= $tab === 'requests' ? 'active' : '' ?>" href="<?= e(url('admin.php?tab=requests')) ?>">Requests (<?= $openCount ?>)</a></li>
    </ul>

    <?php if ($tab === 'requests'): ?>
        <div class="list-group reveal in-view">
            <?php if (count($reports) === 0): ?>
                <div class="alert alert-secondary">No requests yet.</div>
            <?php else: ?>
                <?php foreach ($reports as $r): ?>
                    <?php
                    $typeLabel = [
                        'dmca' => 'DMCA copyright takedown',
                        'takedown' => 'General takedown',
                        'privacy' => 'Privacy / personal data',
                        'abuse' => 'Abuse',
                    ];
                    $statusBadge = $r['status'] === 'open'
                        ? '<span class="badge bg-danger">OPEN</span>'
                        : '<span class="badge bg-success">RESOLVED</span>';
                    ?>
                    <div class="list-group-item">
                        <div class="d-flex align-items-center gap-2 flex-wrap">
                            <span class="fw-semibold">#<?= (int)$r['id'] ?></span>
                            <?= $statusBadge ?>
                            <span class="badge bg-secondary"><?= e($typeLabel[$r['type']] ?? $r['type']) ?></span>
                            <?php if (preg_match('~^https?://~i', $r['target_url'])): ?>
                                <a class="text-decoration-none small" target="_blank" rel="noopener"
                                   href="<?= e($r['target_url']) ?>"><?= e($r['target_url']) ?></a>
                            <?php else: ?>
                                <span class="text-secondary small"><?= e($r['target_url']) ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="text-secondary small mt-1">
                            from <?= e($r['name']) ?> (<?= e($r['contact']) ?>) ·
                            <?= e(gmdate('Y-m-d H:i', strtotime($r['created_at'] . ' UTC'))) ?> UTC ·
                            IP <?= e($r['ip'] ?? '—') ?>
                        </div>
                        <p class="small mt-2 mb-2" style="white-space:pre-wrap;"><?= e($r['details']) ?></p>
                        <?php if ($r['status'] === 'resolved'): ?>
                            <div class="alert alert-success py-2 small mb-2">
                                Resolved <?= e(gmdate('Y-m-d H:i', strtotime($r['resolved_at'] . ' UTC'))) ?> UTC
                                by <?= e($r['resolved_by_name'] ?? '—') ?>
                                <?php if ($r['resolution']): ?> — “<?= e($r['resolution']) ?>”<?php endif; ?>
                            </div>
                        <?php endif; ?>
                        <form method="post" action="<?= e(url('admin.php?tab=requests')) ?>" class="d-inline-flex gap-2 flex-wrap align-items-center">
                            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                            <input type="hidden" name="report_id" value="<?= (int)$r['id'] ?>">
                            <?php if ($r['status'] === 'open'): ?>
                                <input class="form-control form-control-sm" name="resolution" maxlength="1000"
                                       placeholder="Resolution note (optional)" style="max-width: 320px;">
                                <button class="btn btn-sm btn-success" type="submit" name="action" value="reportresolve">Resolve</button>
                            <?php else: ?>
                                <button class="btn btn-sm btn-outline-warning" type="submit" name="action" value="reportreopen">Reopen</button>
                            <?php endif; ?>
                            <button class="btn btn-sm btn-outline-danger" type="submit" name="action" value="reportdelete"
                                onclick="return confirm('Delete this request permanently?');">Delete</button>
                        </form>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

    <?php elseif ($tab === 'bans'): ?>
        <div class="card mb-4 reveal in-view"><div class="card-body">
            <h2 class="h6 mb-3">Ban an IP address</h2>
            <form method="post" action="<?= e(url('admin.php?tab=bans')) ?>" class="row g-2">
                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="action" value="banip">
                <div class="col-md-4">
                    <label class="form-label">IP address</label>
                    <input class="form-control" name="ip" maxlength="45" value="<?= e(client_ip()) ?>" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Reason (optional)</label>
                    <input class="form-control" name="reason" maxlength="255">
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button class="btn btn-danger w-100" type="submit">Ban IP</button>
                </div>
            </form>
        </div></div>

        <div class="list-group reveal in-view">
            <?php if (count($bannedIps) === 0): ?>
                <div class="alert alert-secondary">No IPs are currently banned.</div>
            <?php else: ?>
                <?php foreach ($bannedIps as $b): ?>
                    <div class="list-group-item d-flex align-items-center gap-2 flex-wrap">
                        <div class="flex-grow-1">
                            <span class="fw-semibold"><?= e($b['ip']) ?></span>
                            <?php if ($b['reason']): ?><span class="text-secondary small ms-2">— <?= e($b['reason']) ?></span><?php endif; ?>
                            <div class="text-secondary small">
                                banned by <?= e($b['banned_by_name'] ?? 'system') ?> ·
                                <?= e(gmdate('Y-m-d H:i', strtotime($b['created_at'] . ' UTC'))) ?> UTC
                            </div>
                        </div>
                        <form method="post" action="<?= e(url('admin.php?tab=bans')) ?>" class="d-inline">
                            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                            <input type="hidden" name="action" value="unbanip">
                            <input type="hidden" name="ip" value="<?= e($b['ip']) ?>">
                            <button class="btn btn-sm btn-outline-success" type="submit">Unban</button>
                        </form>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

    <?php elseif ($tab === 'users'): ?>
        <div class="list-group">
            <?php foreach ($users as $u): ?>
                <div class="list-group-item d-flex align-items-center gap-2 flex-wrap">
                    <div class="flex-grow-1">
                        <a class="text-decoration-none fw-semibold" href="<?= e(url('profile.php?id=' . (int)$u['id'])) ?>"><?= e($u['username']) ?></a>
                        <?php if ($u['role'] === 'admin'): ?><span class="badge bg-danger">ADMIN</span><?php endif; ?>
                        <?php if ($u['role'] === 'moderator'): ?><span class="badge bg-info">MODERATOR</span><?php endif; ?>
                        <?php if ($u['status'] !== 'active'): ?><span class="badge bg-warning">SUSPENDED</span><?php endif; ?>
                        <span class="text-secondary small ms-2"><?= (int)$u['paste_count'] ?> pastes</span>
                    </div>
                    <form method="post" action="<?= e(url('admin.php?tab=users')) ?>" class="d-inline">
                        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                        <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
                        <?php if ($u['role'] !== 'admin'): ?>
                            <button class="btn btn-sm btn-outline-danger" type="submit" name="action" value="promoteadmin">Promote to Admin</button>
                        <?php else: ?>
                            <button class="btn btn-sm btn-outline-danger" type="submit" name="action" value="demote">Demote</button>
                        <?php endif; ?>
                    </form>
                    <?php if ($u['role'] === 'user'): ?>
                        <form method="post" action="<?= e(url('admin.php?tab=users')) ?>" class="d-inline">
                            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                            <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
                            <button class="btn btn-sm btn-outline-info" type="submit" name="action" value="promotemod">Make Moderator</button>
                        </form>
                    <?php elseif ($u['role'] === 'moderator'): ?>
                        <form method="post" action="<?= e(url('admin.php?tab=users')) ?>" class="d-inline">
                            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                            <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
                            <button class="btn btn-sm btn-outline-warning" type="submit" name="action" value="demote">Demote</button>
                        </form>
                    <?php endif; ?>
                    <form method="post" action="<?= e(url('admin.php?tab=users')) ?>" class="d-inline">
                        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                        <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
                        <button class="btn btn-sm btn-outline-warning" type="submit" name="action" value="<?= $u['status'] === 'active' ? 'suspend' : 'reinstate' ?>">
                            <?= $u['status'] === 'active' ? 'Suspend' : 'Reinstate' ?>
                        </button>
                    </form>
                    <?php if ((int)$u['id'] !== (int)$me['id']): ?>
                        <form method="post" action="<?= e(url('admin.php?tab=users')) ?>" class="d-inline"
                            onsubmit="return confirm('Delete this user and keep their pastes as anonymous?');">
                            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                            <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
                            <button class="btn btn-sm btn-danger" type="submit" name="action" value="deleteuser">Delete</button>
                        </form>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>

    <?php else: ?>
        <div id="pastes" class="list-group">
            <?php foreach ($pastes as $p): ?>
                <div class="list-group-item d-flex align-items-center gap-2 flex-wrap">
                    <div class="flex-grow-1"<?= paste_border_style($p['paste_color']) ?>>
                        <a class="text-decoration-none fw-semibold" href="<?= e(url('view.php?id=' . $p['id'])) ?>"><?= e($p['title']) ?></a>
                        <?php if ($p['pin']): ?><span class="badge bg-primary">PINNED</span><?php endif; ?>
                        <span class="text-secondary small ms-2">
                            by <?= e($p['owner_name'] !== null ? $p['owner_name'] : $p['author']) ?> ·
                            <?= (int)$p['views'] ?> views
                        </span>
                    </div>
                    <form method="post" action="<?= e(url('admin.php?tab=pastes')) ?>#pastes" class="d-inline">
                        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                        <input type="hidden" name="action" value="<?= $p['pin'] ? 'pasteunpin' : 'pastepin' ?>">
                        <input type="hidden" name="paste_id" value="<?= e($p['id']) ?>">
                        <button class="btn btn-sm btn-outline-primary" type="submit">
                            <?= $p['pin'] ? 'Unpin' : 'Pin' ?>
                        </button>
                    </form>
                    <form method="post" action="<?= e(url('admin.php?tab=pastes')) ?>#pastes" class="d-inline">
                        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                        <input type="hidden" name="action" value="pastecolor">
                        <input type="hidden" name="paste_id" value="<?= e($p['id']) ?>">
                        <?= color_select('paste_color', (string)$p['paste_color']) ?>
                        <button class="btn btn-sm btn-outline-info" type="submit">Color</button>
                    </form>
                    <a class="btn btn-sm btn-outline-light" href="<?= e(url('edit.php?id=' . $p['id'])) ?>">Edit</a>
                    <form method="post" action="<?= e(url('admin.php?tab=pastes')) ?>#pastes" class="d-inline"
                        onsubmit="return confirm('Delete this paste?');">
                        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                        <input type="hidden" name="action" value="pastedelete">
                        <input type="hidden" name="paste_id" value="<?= e($p['id']) ?>">
                        <button class="btn btn-sm btn-danger" type="submit">Delete</button>
                    </form>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
<?php page_footer(); ?>