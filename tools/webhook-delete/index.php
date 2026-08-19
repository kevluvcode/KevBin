<?php
require_once __DIR__ . '/../../functions.php';

start_session();
$GLOBALS['_meta'] = [
    'description' => 'Delete a Discord webhook by URL — permanently removes the webhook and its messages. Routed through a Cloudflare Worker.',
    'keywords' => 'Discord webhook, delete webhook, remove webhook',
];
page_header('Discord Webhook Deleter');

$result = null;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify_or_fail();
    if (!rate_limit_check('webhook-delete', 5, 60)) {
        $error = 'Too many requests. Wait a minute.';
    } else {
        $cfg = $GLOBALS['CFG'];
        $bridgeUrl = rtrim((string)($cfg['worker_url'] ?? ''), '/');
        if ($bridgeUrl === '') {
            $error = 'Worker bridge not configured.';
        } else {
            $webhookUrl = trim((string)($_POST['url'] ?? ''));
            if (!preg_match('#^https://discord\.com/api/webhooks/#', $webhookUrl)) {
                $error = 'Enter a valid Discord webhook URL (https://discord.com/api/webhooks/...)';
            } else {
                $ch = curl_init($bridgeUrl . '/webhook/delete');
                curl_setopt_array($ch, [
                    CURLOPT_POST           => true,
                    CURLOPT_POSTFIELDS     => json_encode(['url' => $webhookUrl]),
                    CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_TIMEOUT        => 15,
                    CURLOPT_CONNECTTIMEOUT => 5,
                ]);
                $resp = curl_exec($ch);
                $httpCode = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
                $curlErr = curl_error($ch);
                curl_close($ch);

                if ($curlErr !== '') {
                    $error = 'Worker request failed: ' . $curlErr;
                } else {
                    $data = json_decode((string)$resp, true);
                    if ($data !== null && isset($data['ok']) && $data['ok']) {
                        $result = $data;
                    } elseif ($data !== null && isset($data['error'])) {
                        $error = 'Worker error: ' . $data['error'];
                    } else {
                        $error = 'Unexpected response (HTTP ' . $httpCode . ')';
                    }
                }
            }
        }
    }
}
?>
<div class="container" style="max-width: 700px;">
    <h1 class="h4 mb-2 reveal in-view">Discord Webhook Deleter</h1>
    <p class="text-secondary mb-3 reveal in-view">Permanently delete a Discord webhook by its URL. This action is <strong>irreversible</strong> — all messages sent through the webhook remain, but no new messages can be sent.</p>

    <div class="alert alert-danger small mb-3">
        <strong>Warning:</strong> Deleting a webhook is permanent and cannot be undone. Make sure you have the correct URL.
    </div>

    <?php if ($error !== ''): ?>
        <div class="alert alert-danger"><?= e($error) ?></div>
    <?php endif; ?>
    <?php if ($result !== null): ?>
        <div class="alert alert-success">
            Webhook deleted successfully (HTTP <?= (int)($result['status'] ?? 204) ?>).
            <?php if (!empty($result['name'])): ?>
                <br>Name: <strong><?= e($result['name']) ?></strong>
            <?php endif; ?>
            <?php if (!empty($result['channel_id'])): ?>
                <br>Channel ID: <code><?= e($result['channel_id']) ?></code>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <form method="post" action="index.php" onsubmit="return confirm('Are you sure you want to permanently delete this webhook?');var b=this.querySelector('button[type=submit]');if(b){b.disabled=true;b.textContent='Deleting…';}">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">

        <div class="card mb-3 reveal in-view"><div class="card-body">
            <h2 class="h6 mb-3">Webhook to Delete</h2>
            <div class="mb-0">
                <label class="form-label small text-secondary">Webhook URL</label>
                <input class="form-control" name="url" placeholder="https://discord.com/api/webhooks/..." required
                    value="<?= e(trim((string)($_POST['url'] ?? ''))) ?>">
                <div class="form-text">The full webhook URL including the token after the last /</div>
            </div>
        </div></div>

        <div class="text-center mb-4">
            <button type="submit" class="btn btn-danger btn-lg">Delete Webhook</button>
        </div>
    </form>

    <p class="text-secondary small mb-4">This sends a DELETE request to Discord's API via the Cloudflare Worker. The webhook URL is never stored.</p>
</div>
<?php page_footer(); ?>
