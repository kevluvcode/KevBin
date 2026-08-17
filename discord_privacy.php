<?php
require_once __DIR__ . '/functions.php';

start_session();
$cfg = $GLOBALS['CFG'];

page_header('Discord Privacy Policy');
?>
<div class="container" style="max-width: 800px;">
    <h1 class="h4 mb-3">Discord Privacy Policy</h1>
    <div class="card"><div class="card-body">
        <p>This page explains how <?= e($cfg['site_name']) ?> handles information when you sign in
        with Discord. It supplements the <a class="link-light" href="privacy.php">main Privacy Policy</a>.</p>

        <h2 class="h6 mt-4">1. Data we receive from Discord</h2>
        <p>When you sign in with Discord, we receive only what Discord's OAuth consent screen allows:
        your Discord user ID, username and avatar. We do not request or store email addresses,
        messages, friend lists, or any other Discord data.</p>

        <h2 class="h6 mt-4">2. How the data is used</h2>
        <ul>
            <li>to identify your account when you log in,</li>
            <li>to display your username and avatar on your profile, and</li>
            <li>to link your site account to the Discord identity you approved.</li>
        </ul>
        <p>We do not sell, trade or share this data with third parties.</p>

        <h2 class="h6 mt-4">3. Authentication via Cloudflare Worker</h2>
        <p>Because the hosting provider blocks outbound requests, the OAuth token exchange runs in
        a Cloudflare Worker bridge. Discord sends the code to that Worker, which exchanges it for
        your public profile and forwards only that profile to <?= e($cfg['site_name']) ?>. Your
        Discord credentials never touch our server.</p>

        <h2 class="h6 mt-4">4. Storage and retention</h2>
        <p>Your Discord user ID, username and avatar are stored in the site database so you can
        sign in again. You can remove the connection or delete your account at any time from
        Settings; deletion removes this data.</p>

        <h2 class="h6 mt-4">5. Discord community (kevrunscord.shop)</h2>
        <p>If you join the related Discord community, Discord's own privacy policy applies to your
        activity on Discord. This page only covers sign-in with <?= e($cfg['site_name']) ?>.</p>

        <h2 class="h6 mt-4">6. Contact</h2>
        <p>Questions about this policy can be sent through the <a class="link-light" href="legal.php">Legal / contact page</a>.</p>
    </div></div>
</div>
<?php page_footer(); ?>
