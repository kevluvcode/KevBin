<?php
require_once __DIR__ . '/functions.php';

start_session();
$cfg = $GLOBALS['CFG'];
$perPage = (int)$cfg['per_page'];
$page = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * $perPage;

$pdo = db();
$total = (int)$pdo->query(
    'SELECT COUNT(*) FROM users'
)->fetchColumn();

$stmt = $pdo->prepare(
    'SELECT u.id, u.username, u.role, u.status, u.pfp, u.profile_color, u.created_at, u.alias, u.profile_views,
            (SELECT COUNT(*) FROM pastes p WHERE p.user_id = u.id) AS paste_count
     FROM users u
     ORDER BY u.created_at ASC
     LIMIT ? OFFSET ?'
);
$stmt->bindValue(1, $perPage, PDO::PARAM_INT);
$stmt->bindValue(2, $offset, PDO::PARAM_INT);
$stmt->execute();
$users = $stmt->fetchAll();

page_header('Users');
?>
<div class="container" style="max-width: 900px;">
    <h1 class="h4 mb-3">Users (<?= $total ?>)</h1>
    <?php if (count($users) === 0): ?>
        <div class="alert alert-secondary">No users yet.</div>
    <?php else: ?>
        <ul class="list-group mb-4">
            <?php foreach ($users as $u): ?>
                <li class="list-group-item d-flex align-items-center">
                    <?php if ($u['pfp'] !== '' && $u['pfp'] !== null): ?>
                        <img class="pfp-sm me-3" src="<?= e($u['pfp']) ?>" alt="pfp">
                    <?php else: ?>
                        <div class="pfp-sm me-3 d-flex align-items-center justify-content-center bg-secondary">?</div>
                    <?php endif; ?>
                    <div class="flex-grow-1">
                        <a class="text-decoration-none fw-semibold"
                            style="color:<?= e(clean_hex_color($u['profile_color']) !== '' ? clean_hex_color($u['profile_color']) : '#ffffff') ?>"
                            href="profile.php?id=<?= (int)$u['id'] ?>">
                            <?= e($u['username']) ?><?php if (!empty($u['alias']) && $u['alias'] !== $u['username']): ?> <span class="text-secondary fw-normal">(<?= e($u['alias']) ?>)</span><?php endif; ?>
                        </a>
                        <?php if ($u['role'] === 'admin'): ?>
                            <span class="badge bg-danger ms-1">ADMIN</span>
                        <?php endif; ?>
                        <?php if ($u['status'] !== 'active'): ?>
                            <span class="badge bg-warning ms-1">SUSPENDED</span>
                        <?php endif; ?>
                    </div>
                    <span class="text-secondary small">
                        <?= (int)$u['paste_count'] ?> pastes ·
                        <?= (int)$u['profile_views'] ?> views ·
                        joined <?= $u['created_at'] ? e(gmdate('Y-m-d', strtotime($u['created_at'] . ' UTC'))) : '—' ?>
                    </span>
                </li>
            <?php endforeach; ?>
        </ul>
        <?= paginate($page, $total, $perPage, 'users.php') ?>
    <?php endif; ?>
</div>
<?php page_footer(); ?>