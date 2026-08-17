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

    // ——— Premium / supporter management (admins only) ———
    if (in_array($action, ['premiumgrant', 'premiumrevoke'], true)) {
        if (!$isAdmin) {
            flash_set('error', 'Only admins can manage premium.');
            redirect('admin.php?tab=users');
        }
        $target = (int)($_POST['user_id'] ?? 0);
        $stmt = $pdo->prepare('SELECT id, username FROM users WHERE id = ?');
        $stmt->execute([$target]);
        $targetUser = $stmt->fetch();
        if (!$targetUser) {
            flash_set('error', 'User not found.');
        } elseif ($action === 'premiumgrant') {
            $plan = (string)($_POST['plan'] ?? 'supporter');
            if ($plan === 'monthly') {
                $plan = 'supporter'; // legacy
            }
            if (!in_array($plan, ['supporter', 'pro', 'lifetime'], true)) {
                $plan = 'supporter';
            }
            $months = max(1, min(120, (int)($_POST['months'] ?? 1)));
            $expires = $plan === 'lifetime' ? null : gmdate('Y-m-d H:i:s', time() + $months * 30 * 86400);
            $pdo->prepare('UPDATE users SET premium_plan = ?, premium_expires_at = ? WHERE id = ?')
                ->execute([$plan, $expires, $target]);
            log_activity('premium_grant', $targetUser['username'] . ' ' . $plan . ($expires !== null ? ' ' . $months . 'mo' : ' lifetime'));
            flash_set('success', $targetUser['username'] . ' granted ' . $plan . ' premium' . ($expires !== null ? ' for ' . $months . ' month(s).' : ' (lifetime).'));
        } else {
            $pdo->prepare("UPDATE users SET premium_plan = '', premium_expires_at = NULL WHERE id = ?")
                ->execute([$target]);
            log_activity('premium_revoke', $targetUser['username']);
            flash_set('success', $targetUser['username'] . ' premium revoked.');
        }
        redirect('admin.php?tab=users');
    }

    // ——— BTC payment records (admins only) ———
    if (in_array($action, ['paymentverify', 'paymentreject', 'paymentdelete', 'paymentscan'], true)) {
        if (!$isAdmin) {
            flash_set('error', 'Only admins can manage payments.');
            redirect('admin.php?tab=payments');
        }
        if ($action === 'paymentscan') {
            $stats = scan_btc_payments();
            flash_set('success', 'Scan complete: ' . $stats['processed'] . ' payment(s) verified' . ($stats['scanned'] ? ', wallet checked.' : ' (block explorer unreachable from this host).'));
            redirect('admin.php?tab=payments');
        }
        $pid = (int)($_POST['payment_id'] ?? 0);
        $stmt = $pdo->prepare('SELECT * FROM premium_payments WHERE id = ?');
        $stmt->execute([$pid]);
        $pay = $stmt->fetch();
        if (!$pay) {
            flash_set('error', 'Payment not found.');
        } elseif ($action === 'paymentverify') {
            if ((string)$pay['username'] === '') {
                flash_set('error', 'That payment has no claimant yet — wait for the user to claim it on support.php.');
            } else {
                $tier = (string)$pay['plan'];
                if (!isset(premium_tiers()[$tier])) {
                    $tier = 'supporter';
                }
                $days = (int)(premium_tiers()[$tier]['days'] ?? 30);
                $pdo->prepare("UPDATE premium_payments SET status = 'verified', verified_at = UTC_TIMESTAMP() WHERE id = ?")->execute([$pid]);
                grant_premium((string)$pay['username'], $tier, $days > 0 ? $days : null);
                flash_set('success', 'Payment verified manually — ' . $pay['username'] . ' granted ' . $tier . '.');
            }
        } elseif ($action === 'paymentreject') {
            $pdo->prepare("UPDATE premium_payments SET status = 'rejected' WHERE id = ?")->execute([$pid]);
            flash_set('success', 'Payment rejected.');
        } elseif ($action === 'paymentdelete') {
            $pdo->prepare('DELETE FROM premium_payments WHERE id = ?')->execute([$pid]);
            flash_set('success', 'Payment record deleted.');
        }
        redirect('admin.php?tab=payments');
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

    // ——— Moderation queue: approve / reject non-staff edits & deletions (admins only) ———
    if (in_array($action, ['modapprove', 'modreject'], true)) {
        if (!$isAdmin) {
            flash_set('error', 'Only admins can approve edits.');
            redirect('admin.php?tab=modqueue');
        }
        $mqId = (int)($_POST['mq_id'] ?? 0);
        $stmt = $pdo->prepare('SELECT * FROM moderation_queue WHERE id = ?');
        $stmt->execute([$mqId]);
        $mq = $stmt->fetch();
        if (!$mq || $mq['status'] !== 'pending') {
            flash_set('error', 'Request not found or already handled.');
            redirect('admin.php?tab=modqueue');
        }
        if ($action === 'modreject') {
            $pdo->prepare("UPDATE moderation_queue SET status = 'rejected', reviewed_by = ?, reviewed_at = UTC_TIMESTAMP() WHERE id = ?")
                ->execute([(int)$me['id'], $mqId]);
            log_activity('moderation_rejected', $mq['target_type'] . ' ' . $mq['action_type'] . ' #' . $mqId);
            flash_set('success', 'Request rejected.');
            redirect('admin.php?tab=modqueue');
        }

        $pdo->beginTransaction();
        try {
            if ($mq['target_type'] === 'wiki' && $mq['action_type'] === 'edit') {
                // Apply the pending wiki edit. Insert the page if it doesn't exist.
                $slug = $mq['slug'];
                $title = $mq['title'];
                $content = $mq['new_content'];
                $scope = $mq['scope'] ?? 'community';
                $ownerId = null;
                if ($scope === 'personal' && $mq['requested_by'] !== null) {
                    $ownerId = (int)$mq['requested_by'];
                }
                $pageId = $mq['ref_id'] !== null ? (int)$mq['ref_id'] : 0;
                if ($pageId > 0) {
                    // Use the page's stored owner_id so we don't steal ownership.
                    $pageRow = $pdo->prepare('SELECT owner_id FROM wiki_pages WHERE id = ?');
                    $pageRow->execute([$pageId]);
                    $pr = $pageRow->fetch();
                    if ($pr !== false) {
                        $ownerId = $pr['owner_id'];
                    }
                    $pdo->prepare('UPDATE wiki_pages SET slug = ?, title = ?, content = ?, updated_at = UTC_TIMESTAMP() WHERE id = ?')
                        ->execute([$slug, $title, $content, $pageId]);
                } else {
                    $pdo->prepare(
                        'INSERT INTO wiki_pages (scope, owner_id, slug, title, content, created_at, updated_at)
                         VALUES (?, ?, ?, ?, ?, UTC_TIMESTAMP(), UTC_TIMESTAMP())'
                    )->execute([$scope, $ownerId, $slug, $title, $content]);
                    $pageId = (int)$pdo->lastInsertId();
                }
                $pdo->prepare(
                    'INSERT INTO wiki_revisions (page_id, user_id, content, note, created_at)
                     VALUES (?, ?, ?, ?, UTC_TIMESTAMP())'
                )->execute([$pageId, $mq['requested_by'], $content, mb_substr((string)$mq['note'], 0, 255)]);
                log_activity('moderation_approved', 'wiki edit ' . $scope . '/' . $slug);
                flash_set('success', 'Wiki edit approved and applied.');
            } elseif ($mq['target_type'] === 'wiki' && $mq['action_type'] === 'delete') {
                $pageId = $mq['ref_id'] !== null ? (int)$mq['ref_id'] : 0;
                if ($pageId > 0) {
                    $pdo->prepare('DELETE FROM wiki_revisions WHERE page_id = ?')->execute([$pageId]);
                    $pdo->prepare('DELETE FROM wiki_pages WHERE id = ?')->execute([$pageId]);
                }
                log_activity('moderation_approved', 'wiki delete ' . ($mq['scope'] ?? '') . '/' . ($mq['slug'] ?? ''));
                flash_set('success', 'Wiki page deletion approved.');
            } elseif ($mq['target_type'] === 'paste' && $mq['action_type'] === 'edit') {
                $pid = (string)$mq['ref_id'];
                $meta = json_decode((string)$mq['note'], true);
                if (!is_array($meta)) {
                    $meta = [];
                }
                $pdo->prepare(
                    'UPDATE pastes SET title = ?, description = ?, tags = ?, paste_color = ?, content = ? WHERE id = ?'
                )->execute([
                    mb_substr((string)($meta['title'] ?? $mq['title'] ?? 'Untitled'), 0, 120),
                    $meta['description'] !== '' ? mb_substr((string)$meta['description'], 0, 255) : null,
                    $meta['tags'] !== '' ? mb_substr((string)$meta['tags'], 0, 255) : null,
                    clean_hex_color((string)($meta['color'] ?? '')),
                    (string)$mq['new_content'],
                    $pid,
                ]);
                log_activity('moderation_approved', 'paste edit ' . $pid);
                flash_set('success', 'Paste edit approved and applied.');
            } elseif ($mq['target_type'] === 'paste' && $mq['action_type'] === 'delete') {
                $pid = (string)$mq['ref_id'];
                $pdo->prepare('DELETE FROM pins WHERE paste_id = ?')->execute([$pid]);
                $pdo->prepare('DELETE FROM notifications WHERE paste_id = ?')->execute([$pid]);
                $pdo->prepare('DELETE FROM pastes WHERE id = ?')->execute([$pid]);
                log_activity('moderation_approved', 'paste delete ' . $pid);
                flash_set('success', 'Paste deletion approved.');
            } else {
                throw new RuntimeException('Unknown moderation request type.');
            }
            $pdo->prepare("UPDATE moderation_queue SET status = 'approved', reviewed_by = ?, reviewed_at = UTC_TIMESTAMP() WHERE id = ?")
                ->execute([(int)$me['id'], $mqId]);
            $pdo->commit();
        } catch (Throwable $t) {
            $pdo->rollBack();
            log_error('error', 'Moderation approve failed: ' . $t->getMessage(), __FILE__, __LINE__, (int)$me['id']);
            flash_set('error', 'Could not apply the request: ' . $t->getMessage());
        }
        redirect('admin.php?tab=modqueue');
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
    $userSearch = trim((string)($_GET['q'] ?? ''));
    if ($userSearch !== '') {
        $userLike = '%' . $userSearch . '%';
        $usersStmt = $pdo->prepare(
            'SELECT u.id, u.username, u.role, u.status, u.created_at, u.premium_plan, u.premium_expires_at,
                    (SELECT COUNT(*) FROM pastes p WHERE p.user_id = u.id) AS paste_count
             FROM users u
             WHERE u.username LIKE ? OR CAST(u.id AS CHAR) = ?
             ORDER BY u.role DESC, u.username ASC'
        );
        $usersStmt->execute([$userLike, $userSearch]);
        $users = $usersStmt->fetchAll();
    } else {
        $users = $pdo->query(
            'SELECT u.id, u.username, u.role, u.status, u.created_at, u.premium_plan, u.premium_expires_at,
                    (SELECT COUNT(*) FROM pastes p WHERE p.user_id = u.id) AS paste_count
             FROM users u ORDER BY u.role DESC, u.username ASC'
        )->fetchAll();
    }

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

    $payments = [];
    try {
        $payments = $pdo->query('SELECT * FROM premium_payments ORDER BY created_at DESC LIMIT 100')->fetchAll();
    } catch (Throwable $e) {
        // premium_payments table not present yet (schema_ensure creates it)
    }
}

$openCount = 0;
foreach ($reports as $r) {
    if ($r['status'] === 'open') {
        $openCount++;
    }
}

$modQueue = [];
$pendingCount = 0;
if ($isAdmin) {
    $modQueue = $pdo->query(
        "SELECT m.*, u.username AS requester_name
         FROM moderation_queue m
         LEFT JOIN users u ON u.id = m.requested_by
         ORDER BY (m.status = 'pending') DESC, m.created_at ASC
         LIMIT 200"
    )->fetchAll();
    foreach ($modQueue as $mq) {
        if ($mq['status'] === 'pending') {
            $pendingCount++;
        }
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
            <li class="nav-item"><a class="nav-link <?= $tab === 'modqueue' ? 'active' : '' ?>" href="<?= e(url('admin.php?tab=modqueue')) ?>">Approvals (<?= $pendingCount ?>)</a></li>
            <li class="nav-item"><a class="nav-link <?= $tab === 'payments' ? 'active' : '' ?>" href="<?= e(url('admin.php?tab=payments')) ?>">Payments (<?= count($payments) ?>)</a></li>
        <?php endif; ?>
        <li class="nav-item"><a class="nav-link <?= $tab === 'requests' ? 'active' : '' ?>" href="<?= e(url('admin.php?tab=requests')) ?>">Requests (<?= $openCount ?>)</a></li>
        <li class="nav-item"><a class="nav-link" href="<?= e(url('admin_errors.php')) ?>">Error Log</a></li>
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

    <?php elseif ($tab === 'modqueue'): ?>
        <div class="list-group reveal in-view">
            <?php if (count($modQueue) === 0): ?>
                <div class="alert alert-secondary">No moderation requests yet. Non-staff wiki/paste edits and deletions land here for approval.</div>
            <?php else: ?>
                <?php foreach ($modQueue as $mq): ?>
                    <?php
                    $mqLabel = [
                        'wiki-edit' => 'Wiki page edit',
                        'wiki-delete' => 'Wiki page deletion',
                        'paste-edit' => 'Paste edit',
                        'paste-delete' => 'Paste deletion',
                    ];
                    $mqType = $mq['target_type'] . '-' . $mq['action_type'];
                    $mqBadge = $mq['status'] === 'pending'
                        ? '<span class="badge bg-warning text-dark">PENDING</span>'
                        : ($mq['status'] === 'approved'
                            ? '<span class="badge bg-success">APPROVED</span>'
                            : '<span class="badge bg-secondary">REJECTED</span>');
                    $reqBy = e($mq['requester_name'] ?? ('user #' . (int)$mq['requested_by']));
                    ?>
                    <div class="list-group-item">
                        <div class="d-flex align-items-center gap-2 flex-wrap">
                            <span class="fw-semibold">#<?= (int)$mq['id'] ?></span>
                            <?= $mqBadge ?>
                            <span class="badge bg-secondary"><?= e($mqLabel[$mqType] ?? $mqType) ?></span>
                            <?php if ($mq['title'] !== null && $mq['title'] !== ''): ?>
                                <span class="text-truncate" style="max-width: 320px;"><?= e($mq['title']) ?></span>
                            <?php endif; ?>
                            <span class="text-secondary small ms-auto">
                                by <?= $reqBy ?> ·
                                <?= e(gmdate('Y-m-d H:i', strtotime($mq['created_at'] . ' UTC'))) ?> UTC
                            </span>
                        </div>
                        <?php if ($mq['target_type'] === 'wiki' && $mq['note'] !== null && $mq['note'] !== '' && $mq['action_type'] !== 'delete'): ?>
                            <p class="small text-secondary mb-1">Note: <?= e($mq['note']) ?></p>
                        <?php endif; ?>
                        <?php if ($mq['action_type'] === 'edit' && $mq['new_content'] !== null && $mq['new_content'] !== ''): ?>
                            <?php if ($mq['target_type'] === 'paste' && $mq['note'] !== null && $mq['note'] !== ''): ?>
                                <?php $mqMeta = json_decode((string)$mq['note'], true);
                                if (is_array($mqMeta) && ($mqMeta['description'] ?? '' !== '' || $mqMeta['tags'] ?? '' !== '')): ?>
                                    <p class="small text-secondary mb-1">
                                        Desc: <?= e((string)($mqMeta['description'] ?? '')) ?> ·
                                        Tags: <?= e((string)($mqMeta['tags'] ?? '')) ?>
                                    </p>
                                <?php endif; ?>
                            <?php endif; ?>
                            <details class="small">
                                <summary class="text-secondary">Old → New content</summary>
                                <pre class="my-1 p-2 bg-body-tertiary rounded" style="white-space:pre-wrap;max-height:180px;overflow:auto;"><?= e($mq['old_content']) ?></pre>
                                <hr class="my-1">
                                <pre class="my-1 p-2 bg-body-tertiary rounded" style="white-space:pre-wrap;max-height:300px;overflow:auto;"><?= e($mq['new_content']) ?></pre>
                            </details>
                        <?php endif; ?>
                        <?php if ($mq['status'] === 'pending'): ?>
                            <form method="post" action="<?= e(url('admin.php?tab=modqueue')) ?>" class="d-inline-flex gap-2 mt-2">
                                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                                <input type="hidden" name="mq_id" value="<?= (int)$mq['id'] ?>">
                                <button class="btn btn-sm btn-success" type="submit" name="action" value="modapprove"
                                    onclick="return confirm('Approve and apply this change?');">Approve</button>
                                <button class="btn btn-sm btn-outline-danger" type="submit" name="action" value="modreject"
                                    onclick="return confirm('Reject this request?');">Reject</button>
                            </form>
                        <?php endif; ?>
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

    <?php elseif ($tab === 'payments'): ?>
        <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
            <p class="text-secondary small mb-0">BTC payments claimed on support.php are auto-verified against the block explorer. Override manually here if needed.</p>
            <form method="post" action="<?= e(url('admin.php?tab=payments')) ?>" class="d-inline">
                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                <button class="btn btn-sm btn-outline-light" type="submit" name="action" value="paymentscan">Scan now</button>
            </form>
        </div>
        <?php if (count($payments) === 0): ?>
            <div class="alert alert-secondary">No payments yet.</div>
        <?php else: ?>
            <div class="table-responsive">
            <table class="table align-middle">
                <thead><tr>
                    <th>Status</th><th>TXID</th><th>User</th><th>Plan</th><th>Sats</th><th>Claimed</th><th></th>
                </tr></thead>
                <tbody>
                <?php foreach ($payments as $p): ?>
                    <?php
                    $ppStatus = (string)($p['status'] ?? '');
                    $ppBadge = [
                        'pending'  => '<span class="badge bg-warning text-dark">PENDING</span>',
                        'detected' => '<span class="badge bg-info text-dark">DETECTED</span>',
                        'verified' => '<span class="badge bg-success">VERIFIED</span>',
                        'rejected' => '<span class="badge bg-danger">REJECTED</span>',
                    ][$ppStatus] ?? '<span class="badge bg-secondary">' . e($ppStatus) . '</span>';
                    $ppPlan = premium_tiers()[(string)($p['plan'] ?? '')]['name'] ?? (ucfirst((string)($p['plan'] ?? '')) ?: '—');
                    ?>
                    <tr>
                        <td><?= $ppBadge ?></td>
                        <td><code class="small"><?= e(substr((string)$p['txid'], 0, 16)) ?>…</code></td>
                        <td><?= (string)($p['username'] ?? '') !== '' ? e((string)$p['username']) : '<span class="text-secondary">—</span>' ?></td>
                        <td><?= e($ppPlan) ?></td>
                        <td><?= isset($p['amount_sats']) && $p['amount_sats'] !== null ? number_format((int)$p['amount_sats']) : '—' ?></td>
                        <td class="small text-secondary"><?= e(substr((string)$p['created_at'], 0, 16)) ?></td>
                        <td class="text-end" style="white-space:nowrap;">
                            <?php if ($ppStatus !== 'verified'): ?>
                                <form method="post" action="<?= e(url('admin.php?tab=payments')) ?>" class="d-inline">
                                    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                                    <input type="hidden" name="payment_id" value="<?= (int)$p['id'] ?>">
                                    <button class="btn btn-sm btn-success" type="submit" name="action" value="paymentverify">Verify</button>
                                </form>
                            <?php endif; ?>
                            <?php if ($ppStatus !== 'rejected'): ?>
                                <form method="post" action="<?= e(url('admin.php?tab=payments')) ?>" class="d-inline">
                                    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                                    <input type="hidden" name="payment_id" value="<?= (int)$p['id'] ?>">
                                    <button class="btn btn-sm btn-outline-danger" type="submit" name="action" value="paymentreject">Reject</button>
                                </form>
                            <?php endif; ?>
                            <form method="post" action="<?= e(url('admin.php?tab=payments')) ?>" class="d-inline">
                                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                                <input type="hidden" name="payment_id" value="<?= (int)$p['id'] ?>">
                                <button class="btn btn-sm btn-outline-secondary" type="submit" name="action" value="paymentdelete" onclick="return confirm('Delete this payment record?');">Delete</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            </div>
        <?php endif; ?>

    <?php elseif ($tab === 'users'): ?>
        <form method="get" action="<?= e(url('admin.php')) ?>" class="d-flex gap-2 mb-3">
            <input type="hidden" name="tab" value="users">
            <input class="form-control" type="search" name="q" maxlength="100"
                value="<?= e((string)($_GET['q'] ?? '')) ?>" placeholder="Search by username or user ID…">
            <button class="btn btn-outline-light" type="submit">Search</button>
            <?php if (trim((string)($_GET['q'] ?? '')) !== ''): ?>
                <a class="btn btn-outline-secondary" href="<?= e(url('admin.php?tab=users')) ?>">Clear</a>
            <?php endif; ?>
        </form>
        <?php if (count($users) === 0): ?>
            <div class="alert alert-secondary">No users match your search.</div>
        <?php else: ?>
        <div class="list-group">
            <?php foreach ($users as $u): ?>
                <div class="list-group-item d-flex align-items-center gap-2 flex-wrap">
                    <div class="flex-grow-1">
                        <a class="text-decoration-none fw-semibold" href="<?= e(url('profile.php?id=' . (int)$u['id'])) ?>"><?= e($u['username']) ?></a>
                        <?php if ($u['role'] === 'admin'): ?><span class="badge bg-danger">ADMIN</span><?php endif; ?>
                        <?php if ($u['role'] === 'moderator'): ?><span class="badge bg-info">MODERATOR</span><?php endif; ?>
                        <?php if ($u['status'] !== 'active'): ?><span class="badge bg-warning">SUSPENDED</span><?php endif; ?>
                        <?php if (user_is_premium($u)): ?><?= premium_badge($u) ?><?php endif; ?>
                        <span class="text-secondary small ms-2"><?= (int)$u['paste_count'] ?> pastes</span>
                    </div>
                    <form method="post" action="<?= e(url('admin.php?tab=users')) ?>" class="d-inline">
                        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                        <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
                        <?php if (user_is_premium($u)): ?>
                            <button class="btn btn-sm btn-outline-warning" type="submit" name="action" value="premiumrevoke">Revoke Premium</button>
                        <?php else: ?>
                            <button class="btn btn-sm btn-warning" type="submit" name="action" value="premiumgrant">Grant Premium</button>
                            <input class="form-control-sm d-inline-block" style="width:80px;" type="number" name="months" value="1" min="1" max="120" title="Months (lifetime ignores this)">
                            <select class="form-select-sm d-inline-block" style="width:auto;" name="plan" title="Plan type">
                                <option value="supporter">Supporter</option>
                                <option value="pro">Pro</option>
                                <option value="lifetime">Lifetime</option>
                            </select>
                        <?php endif; ?>
                    </form>
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
        <?php endif; ?>

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