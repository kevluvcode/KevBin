<?php
require_once __DIR__ . '/../../functions.php';

start_session();
$GLOBALS['_meta'] = [
    'description' => 'Free Discord webhook sender with rich embed support. Send messages, embeds with titles, descriptions, colors, fields, footers and timestamps to any Discord webhook URL — routed through a Cloudflare Worker for reliability.',
    'keywords' => 'Discord webhook, webhook sender, Discord embed, Discord bot, webhook message',
];
page_header('Discord Webhook Sender');

$result = null;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify_or_fail();
    if (!rate_limit_check('webhook', 10, 60)) {
        $error = 'Too many requests. Wait a minute.';
    } else {
        $cfg = $GLOBALS['CFG'];
        $bridgeUrl = rtrim((string)($cfg['worker_url'] ?? $cfg['discord_bridge_url'] ?? ''), '/');
        if ($bridgeUrl === '') {
            $error = 'Worker bridge not configured.';
        } else {
            $webhookUrl = trim((string)($_POST['url'] ?? ''));
            if (!preg_match('#^https://discord\.com/api/webhooks/#', $webhookUrl)) {
                $error = 'Enter a valid Discord webhook URL (https://discord.com/api/webhooks/...)';
            } else {
                $payload = [];

                // Basic content
                $content = trim((string)($_POST['content'] ?? ''));
                if ($content !== '') {
                    $payload['content'] = mb_substr($content, 0, 2000);
                }

                // Optional overrides
                $username = trim((string)($_POST['username'] ?? ''));
                if ($username !== '') $payload['username'] = mb_substr($username, 0, 80);
                $avatarUrl = trim((string)($_POST['avatar_url'] ?? ''));
                if ($avatarUrl !== '') $payload['avatar_url'] = $avatarUrl;

                // Thread ID (optional)
                $threadId = trim((string)($_POST['thread_id'] ?? ''));
                if ($threadId !== '' && ctype_digit($threadId)) {
                    $payload['thread_id'] = $threadId;
                }

                // Embed builder
                $embeds = [];
                $embedTitle    = trim((string)($_POST['embed_title'] ?? ''));
                $embedDesc     = trim((string)($_POST['embed_desc'] ?? ''));
                $embedColor    = trim((string)($_POST['embed_color'] ?? ''));
                $embedFooter   = trim((string)($_POST['embed_footer'] ?? ''));
                $embedAuthor   = trim((string)($_POST['embed_author'] ?? ''));
                $embedUrl      = trim((string)($_POST['embed_url'] ?? ''));
                $embedTimestamp = trim((string)($_POST['embed_timestamp'] ?? ''));
                $fieldNames    = array_filter(array_map('trim', explode("\n", (string)($_POST['field_names'] ?? ''))));
                $fieldValues   = array_filter(array_map('trim', explode("\n", (string)($_POST['field_values'] ?? ''))));
                $fieldInlines  = array_map('trim', explode("\n", (string)($_POST['field_inlines'] ?? '')));

                if ($embedTitle !== '' || $embedDesc !== '') {
                    $embed = [];
                    if ($embedTitle !== '') $embed['title'] = $embedTitle;
                    if ($embedDesc !== '')  $embed['description'] = $embedDesc;
                    if ($embedUrl !== '')   $embed['url'] = $embedUrl;
                    if ($embedColor !== '') {
                        $hex = ltrim($embedColor, '#');
                        if (preg_match('/^[0-9a-fA-F]{6}$/', $hex)) {
                            $embed['color'] = hexdec($hex);
                        }
                    }
                    if ($embedFooter !== '') $embed['footer'] = ['text' => $embedFooter];
                    if ($embedAuthor !== '') $embed['author'] = ['name' => $embedAuthor];
                    if ($embedTimestamp !== '') {
                        $ts = strtotime($embedTimestamp);
                        if ($ts !== false) $embed['timestamp'] = date('c', $ts);
                    }
                    // Fields
                    if (count($fieldNames) > 0) {
                        $fields = [];
                        for ($i = 0; $i < min(count($fieldNames), count($fieldValues), 25); $i++) {
                            $fields[] = [
                                'name'   => mb_substr($fieldNames[$i], 0, 256),
                                'value'  => mb_substr($fieldValues[$i] ?? '', 0, 1024),
                                'inline' => isset($fieldInlines[$i]) && strtolower($fieldInlines[$i]) === 'yes',
                            ];
                        }
                        $embed['fields'] = $fields;
                    }
                    $embeds[] = $embed;
                }

                if (empty($payload['content']) && empty($embeds)) {
                    $error = 'Enter a message or fill in at least an embed title or description.';
                } else {
                    if (!empty($embeds)) $payload['embeds'] = $embeds;
                    $payload['url'] = $webhookUrl;

                    // Send via worker
                    $ch = curl_init($bridgeUrl . '/webhook/send');
                    curl_setopt_array($ch, [
                        CURLOPT_POST           => true,
                        CURLOPT_POSTFIELDS     => json_encode($payload),
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
                            $result = ['success' => true, 'status' => $data['status'] ?? 200, 'response' => $data['response'] ?? []];
                        } elseif ($data !== null && isset($data['ok']) && !$data['ok']) {
                            $result = ['success' => false, 'status' => $data['status'] ?? 0, 'response' => $data['response'] ?? [], 'error' => $data['error'] ?? 'Unknown error'];
                        } elseif ($data !== null && isset($data['error'])) {
                            $error = 'Worker error: ' . $data['error'];
                        } else {
                            $error = 'Unexpected worker response (HTTP ' . $httpCode . '). Raw: ' . mb_substr((string)$resp, 0, 300);
                        }
                    }
                }
            }
        }
    }
}
?>
<div class="container" style="max-width: 900px;">
    <h1 class="h4 mb-2 reveal in-view">Discord Webhook Sender</h1>
    <p class="text-secondary mb-3 reveal in-view">Send messages and rich embeds to any Discord channel via a webhook URL. Supports titles, descriptions, colored embeds, fields, footers, authors, timestamps and thread posting. Routed through a Cloudflare Worker for reliability.</p>

    <?php if ($error !== ''): ?>
        <div class="alert alert-danger"><?= e($error) ?></div>
    <?php endif; ?>
    <?php if ($result !== null && $result['success']): ?>
        <div class="alert alert-success">Message sent! (HTTP <?= (int)$result['status'] ?>)</div>
    <?php elseif ($result !== null && !$result['success']): ?>
        <div class="alert alert-danger">Discord rejected the request (HTTP <?= (int)$result['status'] ?>): <?= e($result['error'] ?? 'unknown') ?>
            <?php if (!empty($result['response']['message'])): ?> — <?= e((string)$result['response']['message']) ?><?php endif; ?>
        </div>
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
            <div class="mb-0 mt-2">
                <label class="form-label small text-secondary">Thread ID (optional — posts into a forum thread)</label>
                <input class="form-control" name="thread_id" placeholder="1234567890"
                    value="<?= e(trim((string)($_POST['thread_id'] ?? ''))) ?>">
            </div>
        </div></div>

        <div class="card mb-3 reveal in-view"><div class="card-body">
            <h2 class="h6 mb-3">Message</h2>
            <div class="mb-0">
                <label class="form-label small text-secondary">Content (plain text, max 2000 chars)</label>
                <textarea class="form-control" name="content" rows="4" maxlength="2000"
                    placeholder="Hello from KevBin!"><?= e(trim((string)($_POST['content'] ?? ''))) ?></textarea>
            </div>
        </div></div>

        <div class="card mb-3 reveal in-view"><div class="card-body">
            <h2 class="h6 mb-3">Embed (optional)</h2>
            <div class="row g-2 mb-2">
                <div class="col-md-8">
                    <label class="form-label small text-secondary">Title</label>
                    <input class="form-control" name="embed_title" placeholder="Embed Title"
                        value="<?= e(trim((string)($_POST['embed_title'] ?? ''))) ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label small text-secondary">Color (hex, e.g. #FF0000)</label>
                    <input class="form-control" name="embed_color" placeholder="#58A6FF"
                        value="<?= e(trim((string)($_POST['embed_color'] ?? ''))) ?>">
                </div>
            </div>
            <div class="mb-2">
                <label class="form-label small text-secondary">Description</label>
                <textarea class="form-control" name="embed_desc" rows="3"
                    placeholder="Embed body text..."><?= e(trim((string)($_POST['embed_desc'] ?? ''))) ?></textarea>
            </div>
            <div class="row g-2 mb-2">
                <div class="col-md-4">
                    <label class="form-label small text-secondary">Author name</label>
                    <input class="form-control" name="embed_author" placeholder="Author"
                        value="<?= e(trim((string)($_POST['embed_author'] ?? ''))) ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label small text-secondary">Footer text</label>
                    <input class="form-control" name="embed_footer" placeholder="Footer"
                        value="<?= e(trim((string)($_POST['embed_footer'] ?? ''))) ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label small text-secondary">Timestamp</label>
                    <input class="form-control" name="embed_timestamp" placeholder="now or 2024-01-01 12:00"
                        value="<?= e(trim((string)($_POST['embed_timestamp'] ?? ''))) ?>">
                </div>
            </div>
            <div class="mb-0">
                <label class="form-label small text-secondary">URL (links the title)</label>
                <input class="form-control" name="embed_url" placeholder="https://..."
                    value="<?= e(trim((string)($_POST['embed_url'] ?? ''))) ?>">
            </div>
        </div></div>

        <div class="card mb-3 reveal in-view"><div class="card-body">
            <h2 class="h6 mb-3">Embed Fields (optional, one per line)</h2>
            <div class="row g-2">
                <div class="col-md-4">
                    <label class="form-label small text-secondary">Field names (one per line)</label>
                    <textarea class="form-control" name="field_names" rows="4" placeholder="Status&#10;Version"><?= e(trim((string)($_POST['field_names'] ?? ''))) ?></textarea>
                </div>
                <div class="col-md-4">
                    <label class="form-label small text-secondary">Field values (one per line)</label>
                    <textarea class="form-control" name="field_values" rows="4" placeholder="Online&#10;2.1.0"><?= e(trim((string)($_POST['field_values'] ?? ''))) ?></textarea>
                </div>
                <div class="col-md-4">
                    <label class="form-label small text-secondary">Inline? (one per line: yes/no)</label>
                    <textarea class="form-control" name="field_inlines" rows="4" placeholder="yes&#10;no"><?= e(trim((string)($_POST['field_inlines'] ?? ''))) ?></textarea>
                </div>
            </div>
        </div></div>

        <div class="text-center mb-4">
            <button type="submit" class="btn btn-primary btn-lg">Send Message</button>
        </div>
    </form>

    <p class="text-secondary small mb-4">Your webhook URL is sent directly to the Cloudflare Worker and is never stored on our server. The Worker forwards the payload to Discord and discards it immediately.</p>
</div>
<?php page_footer(); ?>
