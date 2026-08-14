<?php
require_once __DIR__ . '/functions.php';

start_session();
require_login();

$me = current_user();

mark_notifications_read((int)$me['id']);
$notifs = fetch_notifications((int)$me['id'], 100);

page_header('Notifications');
?>
<div class="container" style="max-width: 800px;">
    <h1 class="h4 mb-3 reveal in-view">Notifications</h1>
    <?php if (count($notifs) === 0): ?>
        <div class="alert alert-secondary reveal in-view">
            Nothing here yet. Follow a few people and their new posts will show up.
        </div>
    <?php else: ?>
        <div class="list-group reveal in-view">
            <?php foreach ($notifs as $n): ?>
                <div class="list-group-item d-flex align-items-center gap-2 flex-wrap">
                    <div class="flex-grow-1">
                        <?php if ($n['type'] === 'follow' && !empty($n['actor_id'])): ?>
                            <a class="fw-semibold"
                                style="color:<?= e(clean_hex_color($n['actor_color']) !== '' ? clean_hex_color($n['actor_color']) : '#ffffff') ?>"
                                href="<?= e(url('profile.php?id=' . (int)$n['actor_id'])) ?>"><?= e($n['actor_name']) ?></a>
                            started following you
                        <?php elseif ($n['type'] === 'paste' && !empty($n['paste_id'])): ?>
                            <?= e($n['message']) ?>
                            <a class="text-decoration-none" href="<?= e(url('view.php?id=' . $n['paste_id'])) ?>">View paste</a>
                        <?php else: ?>
                            <?= e($n['message']) ?>
                        <?php endif; ?>
                        <span class="text-secondary small ms-2"><?= e(gmdate('Y-m-d H:i', strtotime($n['created_at'] . ' UTC'))) ?> UTC</span>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
<?php page_footer(); ?>