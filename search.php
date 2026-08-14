<?php
require_once __DIR__ . '/functions.php';

start_session();
$cfg = $GLOBALS['CFG'];
$perPage = (int)$cfg['per_page'];
$page = max(1, (int)($_GET['page'] ?? 1));
$q = trim((string)($_GET['q'] ?? ''));
$offset = ($page - 1) * $perPage;

$pdo = db();
$total = 0;
$results = [];

if ($q !== '') {
    $like = '%' . addcslashes($q, '%_\\') . '%';
    $countSql = 'SELECT COUNT(*) FROM pastes
                 WHERE (expires_at IS NULL OR expires_at > UTC_TIMESTAMP())
                   AND (title LIKE ? OR content LIKE ?)';
    $stmt = $pdo->prepare($countSql);
    $stmt->execute([$like, $like]);
    $total = (int)$stmt->fetchColumn();

    $sql = 'SELECT p.id, p.title, p.author, p.created_at, p.views, p.user_id,
                   u.username AS owner_name, u.profile_color AS owner_color
            FROM pastes p
            LEFT JOIN users u ON u.id = p.user_id
            WHERE (p.expires_at IS NULL OR p.expires_at > UTC_TIMESTAMP())
              AND (p.title LIKE ? OR p.content LIKE ?)
            ORDER BY p.created_at DESC
            LIMIT ? OFFSET ?';
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(1, $like);
    $stmt->bindValue(2, $like);
    $stmt->bindValue(3, $perPage, PDO::PARAM_INT);
    $stmt->bindValue(4, $offset, PDO::PARAM_INT);
    $stmt->execute();
    $results = $stmt->fetchAll();
}

page_header('Search');
?>
<div class="container" style="max-width: 900px;">
    <h1 class="h4 mb-3">Search pastes</h1>
    <form method="get" action="search.php" class="mb-4">
        <div class="input-group">
            <input class="form-control" name="q" placeholder="Search titles and content..."
                value="<?= e($q) ?>" maxlength="100" autofocus>
            <button class="btn btn-primary" type="submit">Search</button>
        </div>
    </form>

    <?php if ($_SERVER['REQUEST_METHOD'] === 'GET' && $q !== ''): ?>
        <p class="text-secondary">
            <?= $total ?> result<?= $total === 1 ? '' : 's' ?> for
            <strong><?= e($q) ?></strong>
        </p>
        <?php if ($total === 0): ?>
            <div class="alert alert-secondary">Nothing found.</div>
        <?php else: ?>
            <ul class="list-group mb-4">
                <?php foreach ($results as $p): ?>
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
            <?= paginate($page, $total, $perPage, 'search.php?q=' . urlencode($q)) ?>
        <?php endif; ?>
    <?php endif; ?>
</div>
<?php page_footer(); ?>