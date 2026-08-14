<?php
require_once __DIR__ . '/functions.php';

start_session();
$cfg = $GLOBALS['CFG'];

page_header('Privacy Policy');
?>
<div class="container" style="max-width: 800px;">
    <h1 class="h4 mb-3">Privacy Policy</h1>
    <div class="card"><div class="card-body">
        <h2 class="h6 mt-3">1. What we collect</h2>
        <p>We store the paste content, title and author name that you submit, together with the
        server-side timestamp. No email address or account is required to use the service; the
        only account is the administrative login.</p>

        <h2 class="h6 mt-4">2. Logs and rate limiting</h2>
        <p>For security and abuse prevention we record the User Agents, of each request in a rate
        limit table, and log actions such as uploads, failed logins, deletions and abuse reports
        in an activity log. This data is used only to operate the service.</p>

        <h2 class="h6 mt-4">3. Cookies and sessions</h2>
        <p>If you use the administrative login, a session cookie is set. It is HTTP-only and
        SameSite-protected. There are no third-party cookies, trackers or analytics.</p>

        <h2 class="h6 mt-4">4. Retention</h2>
        <p>Pastes are kept until they expire or are deleted by the operator. Abuse reports are
        retained as long as needed to respond to and document requests.</p>

        <h2 class="h6 mt-4">5. Disclosures</h2>
        <p>We do not sell or share your data. We may disclose limited information in response to a
        valid legal request, consistent with applicable law, through the process described on our
        <a class="link-light" href="legal.php">Law Enforcement / DMCA page</a>.</p>

        <h2 class="h6 mt-4">6. Contact</h2>
        <p>Questions about this policy can be submitted through our
        <a class="link-light" href="legal.php">Law Enforcement / DMCA page</a>.</p>
    </div></div>
</div>
<?php page_footer(); ?>