<?php
require_once __DIR__ . '/functions.php';

start_session();
$cfg = $GLOBALS['CFG'];

page_header('Discord Terms of Service');
?>
<div class="container" style="max-width: 800px;">
    <h1 class="h4 mb-3">Discord Terms of Service</h1>
    <div class="card"><div class="card-body">
        <p>These terms apply when you sign in to <?= e($cfg['site_name']) ?> with your Discord
        account. By signing in with Discord you agree to the <a class="link-light" href="tos.php">site Terms of Service</a>,
        the <a class="link-light" href="privacy.php">site Privacy Policy</a> and this document.</p>

        <h2 class="h6 mt-4">1. What sign-in with Discord means</h2>
        <p>Discord sign-in is optional. It lets you log in without a password by connecting your
        Discord identity. We use the official Discord OAuth flow and never receive or store your
        Discord password.</p>

        <h2 class="h6 mt-4">2. Acceptable use</h2>
        <p>All content rules from the main Terms of Service apply to accounts created through
        Discord. You may not use Discord sign-in to bypass bans, evade moderation or misrepresent
        your identity.</p>

        <h2 class="h6 mt-4">3. Community and moderation</h2>
        <p>If you join the associated Discord community (kevrunscord.shop), the server rules and
        Discord's Terms of Service also apply. Moderation actions on <?= e($cfg['site_name']) ?>
        are handled by the site operator and are separate from Discord server moderation.</p>

        <h2 class="h6 mt-4">4. Account ownership</h2>
        <p>Your Discord-linked account belongs to you. If you disconnect or delete your Discord
        connection, your <?= e($cfg['site_name']) ?> account and its pastes remain governed by the
        main Terms of Service. If you delete your site account, Discord is not notified and your
        Discord profile is unaffected.</p>

        <h2 class="h6 mt-4">5. Changes and contact</h2>
        <p>We may update these terms as features change. Questions can be sent through the
        <a class="link-light" href="legal.php">Legal / contact page</a>.</p>
    </div></div>
</div>
<?php page_footer(); ?>
