<?php
require_once __DIR__ . '/functions.php';

start_session();
$cfg = $GLOBALS['CFG'];
$me = current_user();

$created = null;
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify_or_fail();
    if (!rate_limit_check('link', (int)$cfg['link_rate_limit'], (int)$cfg['rate_window_seconds'])) {
        friendly_error('Rate limit reached: max ' . $cfg['link_rate_limit'] . ' short links per 10 minutes per IP.', 429);
    }
    $target = trim((string)($_POST['target'] ?? ''));
    $custom = trim((string)($_POST['code'] ?? ''));
    $title = mb_substr(trim((string)($_POST['title'] ?? '')), 0, 120);
    $tracking = !empty($_POST['tracking']);

    $parts = parse_url($target);
    $scheme = is_array($parts) ? strtolower((string)($parts['scheme'] ?? '')) : '';
    if (strpbrk($target, "\r\n\0") !== false) {
        $error = 'URL contains invalid characters.';
    } elseif (!in_array($scheme, ['http', 'https'], true) || empty($parts['host'] ?? '')) {
        $error = 'Enter a valid URL starting with http:// or https://';
    } elseif (mb_strlen($target) > 2048) {
        $error = 'URL is too long (max 2048 chars).';
    } elseif ($custom !== '' && !preg_match('/^[A-Za-z0-9]{3,16}$/', $custom)) {
        $error = 'Custom code must be 3–16 letters/numbers (no spaces or symbols).';
    } else {
        $pdo = db();
        $code = $custom !== '' ? $custom : generate_link_code(5);
        $stmt = $pdo->prepare('SELECT 1 FROM links WHERE code = ?');
        $stmt->execute([$code]);
        if ($stmt->fetch()) {
            $error = 'That code is already taken. Try another one or leave it blank for a random code.';
        } else {
            $manageKey = $me !== null ? null : bin2hex(random_bytes(16));
            $pdo->prepare(
                'INSERT INTO links (code, user_id, manage_key, target_url, title, tracking) VALUES (?, ?, ?, ?, ?, ?)'
            )->execute([$code, $me !== null ? (int)$me['id'] : null, $manageKey, $target, $title !== '' ? $title : null, $tracking ? 1 : 0]);
            log_activity('link_create', $code . ' -> ' . mb_substr($target, 0, 100));
            $created = [
                'code' => $code,
                'key' => $manageKey,
                'tracking' => $tracking,
                'url' => link_short_url($code),
            ];
        }
    }
}

page_header('Shorten a link');
?>
<div class="container" style="max-width: 800px;">
    <h1 class="h4 mb-1 reveal in-view">🔗 Shorten a link</h1>
    <p class="text-secondary mb-4 reveal in-view">Create a short KevBin link. With tracking on, every click records the visitor's IP, location, ISP &amp; ASN, proxy/VPN/Tor flags, browser, OS, device, screen, timezone, language and a canvas fingerprint.</p>

    <?php if ($created !== null): ?>
        <div class="card mb-4 reveal in-view">
            <div class="card-body">
                <div class="alert alert-success mb-3">
                    <h2 class="h5 mb-2">Link created</h2>
                    <p class="mb-1"><strong>Short link:</strong></p>
                    <div class="input-group mb-2">
                        <input id="short-out" class="form-control" readonly value="<?= e($created['url']) ?>">
                        <button class="btn btn-outline-light" onclick="copyShort()">Copy</button>
                    </div>
                    <p class="text-secondary small mb-0">
                        Short code: <code><?= e($created['code']) ?></code> ·
                        Tracking: <strong><?= $created['tracking'] ? 'ON' : 'OFF' ?></strong>
                    </p>
                </div>
                <?php if ($me === null && $created['key'] !== null): ?>
                    <div class="alert alert-secondary mb-0">
                        You created this link anonymously, so here is your private manage link —
                        <strong>save it</strong>, it is the only way to see clicks or delete the link:
                        <div class="input-group mt-2">
                            <input class="form-control" readonly value="<?= e($cfg['base_url'] . 'links.php?m=' . $created['key']) ?>">
                            <button class="btn btn-outline-light" onclick="copyManage()">Copy</button>
                        </div>
                    </div>
                <?php else: ?>
                    <a class="btn btn-primary" href="links.php">View my links</a>
                <?php endif; ?>
                <a class="btn btn-outline-light ms-2" href="short.php">Create another</a>
            </div>
        </div>
    <?php endif; ?>

    <div class="card reveal">
        <div class="card-body">
            <h2 class="h6 mb-3">New short link</h2>
            <?php if ($error !== null): ?>
                <div class="alert alert-danger"><?= e($error) ?></div>
            <?php endif; ?>
            <form method="post" action="short.php">
                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                <div class="mb-3">
                    <label class="form-label">Target URL</label>
                    <input class="form-control" name="target" placeholder="https://example.com/long/page?with=args" required>
                </div>
                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label class="form-label">Custom code (optional)</label>
                        <input class="form-control" name="code" maxlength="16" placeholder="my-link" pattern="[A-Za-z0-9]{3,16}">
                        <div class="form-text">3–16 letters/numbers. Leave empty for a random code.</div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Title (optional)</label>
                        <input class="form-control" name="title" maxlength="120" placeholder="My awesome link">
                    </div>
                </div>
                <div class="form-check mb-3">
                    <input class="form-check-input" type="checkbox" name="tracking" id="tracking" checked>
                    <label class="form-check-label" for="tracking">Track clicks (IP, device, approximate location)</label>
                </div>
                <div class="d-flex gap-2">
                    <button class="btn btn-primary" type="submit">Create link</button>
                    <a class="btn btn-outline-light" href="links.php">My links</a>
                </div>
            </form>
        </div>
    </div>

    <div class="alert alert-secondary mt-4 reveal">
        <strong>Heads up:</strong> this is an analytics-style tracker. Only log visits to links you
        own or are authorized to monitor, and keep the location "approximate" — it comes from the
        visitor's ISP info, not their exact address. The fingerprint beacon runs on a redirect page
        and records screen/timezone/canvas-style signals; visitors are never logged beyond the click record.
    </div>
</div>
<script>
function copyShort() {
    var t = document.getElementById('short-out');
    t.select();
    if (navigator.clipboard) navigator.clipboard.writeText(t.value); else document.execCommand('copy');
}
function copyManage() {
    var t = document.querySelector('#short-out') === null ? null : null;
    var inputs = document.querySelectorAll('.input-group input[readonly]');
    var last = inputs[inputs.length - 1];
    if (!last) return;
    last.select();
    if (navigator.clipboard) navigator.clipboard.writeText(last.value); else document.execCommand('copy');
}
</script>
<?php page_footer(); ?>