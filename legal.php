<?php
require_once __DIR__ . '/functions.php';

start_session();
$cfg = $GLOBALS['CFG'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify_or_fail();
    if (!rate_limit_check('report', (int)$cfg['report_rate_limit'], (int)$cfg['rate_window_seconds'])) {
        friendly_error('Rate limit reached: max ' . $cfg['report_rate_limit'] . ' requests per 10 minutes per IP.', 429);
    }
    $type = (string)($_POST['type'] ?? '');
    $allowedTypes = ['dmca', 'takedown', 'privacy', 'abuse'];
    if (!in_array($type, $allowedTypes, true)) {
        $type = 'abuse';
    }
    $name = mb_substr(trim((string)($_POST['name'] ?? '')), 0, 100);
    $contact = mb_substr(trim((string)($_POST['contact'] ?? '')), 0, 120);
    $targetUrl = mb_substr(trim((string)($_POST['target_url'] ?? '')), 0, 255);
    $details = trim((string)($_POST['details'] ?? ''));

    if ($name === '' || $contact === '' || $targetUrl === '' || $details === '') {
        flash_set('error', 'All fields are required: name, contact, target URL, and details.');
        redirect('legal.php');
    }
    if (mb_strlen($details) > 5000) {
        flash_set('error', 'Details too long (max 5000 chars).');
        redirect('legal.php');
    }

    db()->prepare(
        'INSERT INTO reports (type, name, contact, target_url, details, status, created_at, ip)
         VALUES (?, ?, ?, ?, ?, \'open\', UTC_TIMESTAMP(), ?)'
    )->execute([$type, $name, $contact, $targetUrl, $details, client_ip()]);

    log_activity('report_' . $type, $targetUrl !== '' ? $targetUrl : $name);
    flash_set('success', 'Request received. We will review it and respond as required by law.');
    redirect('legal.php');
}

$openCount = 0;
try {
    $openCount = (int)db()->query("SELECT COUNT(*) FROM reports WHERE status = 'open'")->fetchColumn();
} catch (Throwable $t) {
}

// Allow report links (e.g. from files.php) to pre-fill the type and target URL.
$preType = (string)($_GET['type'] ?? '');
if (!in_array($preType, ['dmca', 'takedown', 'privacy', 'abuse'], true)) {
    $preType = '';
}
$preTarget = mb_substr(trim((string)($_GET['target'] ?? '')), 0, 255);

page_header('DMCA / Law Enforcement');
?>
<div class="container" style="max-width: 800px;">
    <h1 class="h4 mb-3">DMCA / Law Enforcement Requests</h1>
    <div class="card mb-4"><div class="card-body">
        <h2 class="h6">Copyright takedowns (DMCA)</h2>
        <p>If you are a rights holder and believe content on <?= e($cfg['site_name']) ?> infringes
        your copyright, submit a request using the form below. Include the exact paste URL(s),
        identification of the copyrighted work, and your statement of good-faith belief that the
        use is not authorized.</p>

        <h2 class="h6 mt-4">Law enforcement requests</h2>
        <p>Law enforcement agencies may submit requests through the same form. Please include the
        legal basis of the request, the specific paste identifier(s), and official contact data.
        We respond to valid, formally submitted requests in compliance with applicable law.</p>

        <h2 class="h6 mt-4">Privacy and removal requests</h2>
        <p>Individuals who believe their personal information was posted without consent can file
        a removal request. Include the exact paste URL and an explanation. We review such requests
        on a case-by-case basis.</p>
        <p class="text-secondary small mb-0">Open requests in queue: <?= $openCount ?></p>
    </div></div>

    <div class="card"><div class="card-body">
        <h2 class="h6 mb-3">Submit a request</h2>
        <form method="post" action="legal.php">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Request type</label>
                    <select class="form-select" name="type">
                        <option value="dmca" <?= $preType === 'dmca' ? 'selected' : '' ?>>DMCA copyright takedown</option>
                        <option value="takedown" <?= $preType === 'takedown' ? 'selected' : '' ?>>General takedown / removal</option>
                        <option value="privacy" <?= $preType === 'privacy' ? 'selected' : '' ?>>Privacy / personal data</option>
                        <option value="abuse" <?= $preType === 'abuse' ? 'selected' : '' ?>>Abuse</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Your name / agency</label>
                    <input class="form-control" name="name" required maxlength="100">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Contact</label>
                    <input class="form-control" name="contact" required maxlength="120">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Target paste URL</label>
                    <input class="form-control" name="target_url" required maxlength="255" value="<?= e($preTarget) ?>">
                </div>
                <div class="col-12">
                    <label class="form-label">Details</label>
                    <textarea class="form-control" name="details" rows="6" required></textarea>
                </div>
                <div class="col-12">
                    <button class="btn btn-primary" type="submit">Submit request</button>
                </div>
            </div>
        </form>
    </div></div>
</div>
<?php page_footer(); ?>