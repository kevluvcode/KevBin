<?php
require_once __DIR__ . '/../../functions.php';

start_session();
$GLOBALS['_meta'] = [
    'description' => 'Educational Discord webhook spammer — send multiple messages to a webhook URL for testing rate limits and load behavior. Routed through a Cloudflare Worker.',
    'keywords' => 'Discord webhook, spam test, rate limit, Discord API testing',
];
page_header('Discord Webhook Spammer');

$result = null;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify_or_fail();
    if (!rate_limit_check('webhook-spam', 5, 60)) {
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
                $content = trim((string)($_POST['content'] ?? ''));
                $count = max(1, min(20, (int)($_POST['count'] ?? 5)));
                $delayMs = max(200, min(5000, (int)($_POST['delay'] ?? 1000)));
                $username = trim((string)($_POST['username'] ?? ''));
                $avatarUrl = trim((string)($_POST['avatar_url'] ?? ''));

                if ($content === '') {
                    $error = 'Enter a message to send.';
                } else {
                    $payload = [
                        'url'     => $webhookUrl,
                        'content' => mb_substr($content, 0, 2000),
                        'count'   => $count,
                        'delay'   => $delayMs,
                    ];
                    if ($username !== '') $payload['username'] = mb_substr($username, 0, 80);
                    if ($avatarUrl !== '') $payload['avatar_url'] = $avatarUrl;

                    $ch = curl_init($bridgeUrl . '/webhook/spam');
                    curl_setopt_array($ch, [
                        CURLOPT_POST           => true,
                        CURLOPT_POSTFIELDS     => json_encode($payload),
                        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
                        CURLOPT_RETURNTRANSFER => true,
                        CURLOPT_TIMEOUT        => 60,
                        CURLOPT_CONNECTTIMEOUT => 10,
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
}
?>
<div class="container" style="max-width: 900px;">
    <h1 class="h4 mb-2 reveal in-view">Discord Webhook Spammer</h1>
    <p class="text-secondary mb-3 reveal in-view">Send multiple messages to a Discord webhook URL for <strong>educational testing</strong> — understand rate limits, flood behavior and Discord API responses. Messages are sent sequentially through a Cloudflare Worker.</p>

    <div class="alert alert-warning small mb-3">
        <strong>Educational use only.</strong> This tool is for testing your own webhooks and understanding Discord's rate-limit behavior. Abuse of Discord webhooks violates Discord's Terms of Service.
    </div>

    <?php if ($error !== ''): ?>
        <div class="alert alert-danger"><?= e($error) ?></div>
    <?php endif; ?>
    <?php if ($result !== null): ?>
        <div class="alert alert-success">
            Sent <?= (int)$result['sent'] ?> / <?= (int)$result['total'] ?> messages successfully.
            <?php if (!empty($result['failed'])): ?>
                <br><strong>Failed:</strong> <?= (int)count($result['failed']) ?> message(s).
            <?php endif; ?>
            <?php if (!empty($result['rate_limited'])): ?>
                <br><strong>Rate limited:</strong> <?= (int)count($result['rate_limited']) ?> message(s).
            <?php endif; ?>
        </div>
        <?php if (!empty($result['details'])): ?>
            <div class="card mb-3"><div class="card-body" style="max-height:300px;overflow:auto;">
                <h2 class="h6 mb-2">Message Log</h2>
                <table class="table table-sm table-striped small mb-0">
                    <thead><tr><th>#</th><th>Status</th><th>HTTP</th><th>Time</th><th>Detail</th></tr></thead>
                    <tbody>
                    <?php foreach ($result['details'] as $i => $d): ?>
                        <tr class="<?= ($d['ok'] ?? false) ? 'table-success' : (($d['rate_limited'] ?? false) ? 'table-warning' : 'table-danger') ?>">
                            <td><?= $i + 1 ?></td>
                            <td><?= ($d['ok'] ?? false) ? '✅' : (($d['rate_limited'] ?? false) ? '⏳' : '❌') ?></td>
                            <td><?= (int)($d['status'] ?? 0) ?></td>
                            <td><?= (int)($d['ms'] ?? 0) ?>ms</td>
                            <td class="text-truncate" style="max-width:300px;"><?= e($d['detail'] ?? '') ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div></div>
        <?php endif; ?>
    <?php endif; ?>

    <form method="post" action="index.php" onsubmit="var b=this.querySelector('button[type=submit]');if(b){b.disabled=true;b.textContent='Sending…';}">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">

        <div class="card mb-3 reveal in-view"><div class="card-body">
            <h2 class="h6 mb-3">Webhook</h2>
            <div class="mb-3">
                <label class="form-label small text-secondary">Webhook URL</label>
                <input class="form-control" name="url" placeholder="https://discord.com/api/webhooks/..." required
                    value="<?= e(trim((string)($_POST['url'] ?? ''))) ?>">
            </div>
            <div class="row g-2">
                <div class="col-md-6">
                    <label class="form-label small text-secondary">Override username (optional)</label>
                    <input class="form-control" name="username" placeholder="KevBin Bot"
                        value="<?= e(trim((string)($_POST['username'] ?? ''))) ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label small text-secondary">Override avatar URL (optional)</label>
                    <input class="form-control" name="avatar_url" placeholder="https://..."
                        value="<?= e(trim((string)($_POST['avatar_url'] ?? ''))) ?>">
                </div>
            </div>
        </div></div>

        <div class="card mb-3 reveal in-view"><div class="card-body">
            <h2 class="h6 mb-3">Messages</h2>
            <div class="mb-3">
                <label class="form-label small text-secondary">Message content</label>
                <textarea class="form-control" name="content" rows="3" maxlength="2000" required
                    placeholder="Test message from KevBin Spammer"><?= e(trim((string)($_POST['content'] ?? ''))) ?></textarea>
            </div>
            <div class="row g-2">
                <div class="col-md-6">
                    <label class="form-label small text-secondary">Number of messages (1–20)</label>
                    <input class="form-control" type="number" name="count" min="1" max="20" value="<?= e((string)($_POST['count'] ?? 5)) ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label small text-secondary">Delay between messages (ms, 200–5000)</label>
                    <input class="form-control" type="number" name="delay" min="200" max="5000" step="100" value="<?= e((string)($_POST['delay'] ?? 1000)) ?>">
                </div>
            </div>
        </div></div>

        <div class="text-center mb-4">
            <button type="submit" class="btn btn-warning btn-lg">Spam Webhook</button>
        </div>
    </form>

    <p class="text-secondary small mb-4">Your webhook URL is sent to the Cloudflare Worker which sends messages sequentially, respects Discord rate limits (429 responses), and returns a full log. Nothing is stored.</p>
</div>
<?php page_footer(); ?>
