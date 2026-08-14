<?php
require_once __DIR__ . '/functions.php';

http_response_code(403);
start_session();
page_header('403 Forbidden');
?>
<div class="container text-center py-5" style="max-width: 640px;">
    <div class="display-1 fw-bold" style="background:linear-gradient(135deg,var(--accent1),var(--accent2));-webkit-background-clip:text;background-clip:text;color:transparent;">403</div>
    <h1 class="h4 mt-2 mb-3">Access denied</h1>
    <p class="text-secondary mb-4">You don't have permission to view this page. It's locked,
    protected, or the address points somewhere you're not allowed to go.</p>
    <div class="d-flex gap-2 justify-content-center flex-wrap">
        <a class="btn btn-primary" href="<?= e($GLOBALS['CFG']['base_url']) ?>">🏠 Back to main site</a>
        <a class="btn btn-outline-light" href="javascript:history.back()">← Go back</a>
    </div>
</div>
<?php page_footer(); ?>