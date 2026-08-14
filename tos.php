<?php
require_once __DIR__ . '/functions.php';

start_session();
$cfg = $GLOBALS['CFG'];

page_header('Terms of Service');
?>
<div class="container" style="max-width: 800px;">
    <h1 class="h4 mb-3">Terms of Service</h1>
    <div class="card"><div class="card-body">
        <h2 class="h6 mt-3">1. Acceptance of terms</h2>
        <p>By accessing or using <?= e($cfg['site_name']) ?> you agree to be bound by these Terms of
        Service and our <a class="link-light" href="privacy.php">Privacy Policy</a>.</p>

        <h2 class="h6 mt-4">2. Acceptable use</h2>
        <p>You agree not to use the service to post, host or distribute content that:</p>
        <ul>
            <li>violates any applicable law or regulation,</li>
            <li>infringes copyright, trademark or other intellectual property rights,</li>
            <li>contains malicious code, exploits or instructions for illegal activity, or</li>
            <li>impersonates another person or entity.</li>
        </ul>
        <p>We may remove any paste that violates these terms, at our sole discretion and without
        notice. This is an educational project operated as-is.</p>

        <h2 class="h6 mt-4">3. Your pastes</h2>
        <p>You are solely responsible for the content you post. Content is shown publicly once
        published. Pastes with an expiration date are automatically deleted when they expire.
        Deleting a paste may be performed by the operator at any time.</p>

        <h2 class="h6 mt-4">4. Copyright and takedowns</h2>
        <p>If you believe content on the service infringes your rights, file a request through
        our <a class="link-light" href="legal.php">Law Enforcement / DMCA page</a>.</p>

        <h2 class="h6 mt-4">5. No warranty / liability</h2>
        <p>The service is provided &quot;as is&quot; without warranties of any kind. To the maximum
        extent permitted by law, the operator is not liable for any damages arising from use of
        the service or its content.</p>
    </div></div>
</div>
<?php page_footer(); ?>