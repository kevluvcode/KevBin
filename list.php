<?php
require_once __DIR__ . '/functions.php';

start_session();
$cfg = $GLOBALS['CFG'];
$perPage = (int)$cfg['per_page'];
$page = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * $perPage;

$pdo = db();
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

page_header('Browse');
?>
<div class="container" style="max-width: 900px;">
    <h1 class="h4 mb-3">All pastes (<?= $total ?>)</h1>

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
        <?= paginate($page, $total, $perPage, 'list.php') ?>
    <?php endif; ?>
</div>
<?php page_footer(); ?>