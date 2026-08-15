<?php
require_once __DIR__ . '/functions.php';

start_session();
require_admin();

$me = current_user();
$pdo = db();
$isAdmin = is_admin();

// ——— clear logs (admins only) ———
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify_or_fail();
    $action = (string)($_POST['action'] ?? '');
    if ($action === 'clear') {
        $pdo->exec('DELETE FROM error_logs');
        log_activity('admin_clear_errors', 'cleared the error log');
        flash_set('success', 'Error log cleared.');
        redirect('admin_errors.php');
    }
    if ($action === 'clearolder' && is_admin()) {
        $pdo->exec("DELETE FROM error_logs WHERE created_at < DATE_SUB(UTC_TIMESTAMP(), INTERVAL 7 DAY)");
        log_activity('admin_clear_errors', 'cleared errors older than 7 days');
        flash_set('success', 'Errors older than 7 days cleared.');
        redirect('admin_errors.php');
    }
    flash_set('error', 'Unknown action.');
    redirect('admin_errors.php');
}

// ——— data ———
$total = (int)$pdo->query('SELECT COUNT(*) FROM error_logs')->fetchColumn();
$perPage = 50;
$page = max(1, (int)($_GET['page'] ?? 1));
$pages = max(1, (int)ceil($total / $perPage));
$page = min($page, $pages);
$offset = ($page - 1) * $perPage;

$logs = $pdo->prepare(
    'SELECT el.*, u.username AS user_name
     FROM error_logs el
     LEFT JOIN users u ON u.id = el.user_id
     ORDER BY el.id DESC
     LIMIT ' . (int)$perPage . ' OFFSET ' . (int)$offset
);
$logs->execute();
$logs = $logs->fetchAll();

$fatalCount = (int)$pdo->query("SELECT COUNT(*) FROM error_logs WHERE level = 'fatal'")->fetchColumn();

page_header('Error Log');
?>
<div class="container" style="max-width: 1100px;">
    <div class="d-flex flex-wrap align-items-center gap-2 mb-1">
        <h1 class="h4 mb-0 reveal in-view">Error log</h1>
        <span class="text-secondary small reveal in-view"><?= (int)$total ?> total · <?= (int)$fatalCount ?> fatal</span>
    </div>
    <p class="text-secondary small mb-3 reveal in-view">
        Every page/site error that reaches the handler is stored here automatically.
        <a class="link-secondary" href="<?= e(url('admin.php')) ?>">← Back to admin panel</a>
    </p>

    <?php if ($logs === []): ?>
        <div class="alert alert-secondary reveal in-view">No errors logged. Good job — nothing has crashed recently.</div>
    <?php else: ?>
        <div class="list-group mb-4 reveal in-view">
            <?php foreach ($logs as $e): ?>
                <div class="list-group-item py-2">
                    <div class="d-flex flex-wrap align-items-center gap-2">
                        <span class="badge <?= $e['level'] === 'fatal' ? 'text-bg-danger' : 'text-bg-warning' ?>"><?= e($e['level']) ?></span>
                        <span class="font-monospace small text-break"><?= e($e['message']) ?></span>
                        <?php if ($e['file']): ?>
                            <span class="text-secondary small font-monospace ms-auto"><?= e($e['file']) ?><?= $e['line'] ? ':' . (int)$e['line'] : '' ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="d-flex flex-wrap gap-2 mt-1 text-secondary small">
                        <span><?= e(gmdate('Y-m-d H:i:s', strtotime($e['created_at'] . ' UTC'))) ?> UTC</span>
                        <?php if ($e['ip']): ?><span>IP <?= e($e['ip']) ?></span><?php endif; ?>
                        <?php if ($e['user_name']): ?><span>by <a class="link-secondary" href="<?= e(url('profile.php?id=' . (int)$e['user_id'])) ?>"><?= e($e['user_name']) ?></a></span><?php endif; ?>
                        <?php if ($e['url']): ?><span class="text-break"><?= e($e['url']) ?></span><?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <?= paginate($page, $total, $perPage, 'admin_errors.php') ?>
    <?php endif; ?>

    <?php if (is_admin()): ?>
        <div class="d-flex flex-wrap gap-2 mb-2">
            <form method="post" action="<?= e(url('admin_errors.php')) ?>" class="d-inline"
                  onsubmit="return confirm('Delete ALL logged errors?');">
                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="action" value="clear">
                <button class="btn btn-sm btn-outline-danger">Clear all errors</button>
            </form>
            <form method="post" action="<?= e(url('admin_errors.php')) ?>" class="d-inline"
                  onsubmit="return confirm('Delete errors older than 7 days?');">
                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="action" value="clearolder">
                <button class="btn btn-sm btn-outline-secondary">Clear errors older than 7 days</button>
            </form>
        </div>
    <?php endif; ?>
</div>
<?php page_footer(); ?>