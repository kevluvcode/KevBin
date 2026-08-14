<?php
require_once __DIR__ . '/functions.php';

http_response_code(404);
start_session();
page_header('404 Not Found');
?>
<div class="container text-center py-5" style="max-width: 640px;">
    <div class="display-1 fw-bold" style="background:linear-gradient(135deg,var(--accent1),var(--accent2));-webkit-background-clip:text;background-clip:text;color:transparent;">404</div>
    <h1 class="h4 mt-2 mb-3">Page not found</h1>
    <p class="text-secondary mb-4">The page you're looking for doesn't exist, was moved, or has
    expired — just like a paste that nobody saved the edit link for.</p>
    <div class="mb-4">
        <a class="btn btn-primary" href="<?= e($GLOBALS['CFG']['base_url']) ?>">🏠 Back to main site</a>
        <a class="btn btn-outline-light" href="list.php">Browse pastes</a>
        <a class="btn btn-outline-light" href="search.php">Search</a>
    </div>
    <p class="text-secondary small">If you think this is a mistake, try the
    <a class="link-secondary" href="<?= e($GLOBALS['CFG']['base_url']) ?>">homepage</a> or
    check the URL for typos.</p>
</div>
<?php page_footer(); ?>