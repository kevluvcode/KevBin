<?php
// =====================================================================
//  KevBin — configuration file
//  =====================================================================
//  This is the ONLY file you must edit after installing.
//  Best host: InfinityFree (https://www.infinityfree.com) — free MySQL.
//  The official demo site is https://kevbin.ct.ws/ — change base_url
//  below to your own domain.
//
//  NEVER commit real credentials to a public repository.
// =====================================================================
$CFG = [
    // Your InfinityFree (or other) MySQL details go here.
    'db_host' => 'sqlXXX.infinityfree.com', // your MySQL hostname
    'db_name' => 'if0_00000000_yourdbname', // database name
    'db_user' => 'if0_00000000',            // database username
    'db_pass' => 'your_database_password',  // database password

    // Change this to your own domain (keep the trailing slash).
    'base_url' => 'https://kevbin.ct.ws/',  // official site — replace with yours
    'site_name' => 'KevBin',
    'debug' => false,

    // Let the app apply the votes/comments/metadata schema automatically when it is
    // first needed (idempotent — safe to leave on). Set to false to skip; you can
    // always run schema_upgrade2.sql by hand instead.
    'auto_migrate' => true,

    // Custom salt used to hash account-recovery keys at rest (never change it
    // after accounts exist, or stored recovery keys stop matching).
    // Generate your own with:  php -r "echo bin2hex(random_bytes(32));"
    'recovery_salt' => 'CHANGE_ME_64_HEX_CHARACTERS',

    // GitHub OAuth sign-in (optional). Create a GitHub App with the OAuth flow,
    // set its redirect URI to https://your-site/github_oauth.php and paste your
    // own credentials here. Leave the secret empty to hide the buttons.
    'github_client_id' => 'YOUR_GITHUB_CLIENT_ID',
    'github_client_secret' => '',

    // "Fork / Star / Watch" buttons target (top-right of every page).
    'github_repo_url' => 'https://github.com/YOUR_USERNAME/kevbin',

    // SEO / social share metadata (used in <meta>/OpenGraph/Twitter tags on every page)
    'meta_description' => 'Paste & share text online. Free, anonymous and super secure.',
    'logo_url' => '',                       // e.g. https://kevbin.ct.ws/logo.png (used as og:image)
    'twitter_handle' => '',                 // e.g. @kevbin
    'google_site_verification' => '',       // the token inside Google's google-site-verification meta tag

    'watermark' => true,
    'watermark_keyword' => true,

    'max_title_chars' => 120,
    'max_author_chars' => 50,
    'max_content_chars' => 100000,

    'upload_rate_limit' => 10,
    'login_rate_limit' => 5,
    'report_rate_limit' => 5,
    'edit_rate_limit' => 10,
    'vote_rate_limit' => 60,
    'comment_rate_limit' => 20,
    'follow_rate_limit' => 30,
    'unlock_rate_limit' => 20,
    'link_rate_limit' => 10,
    'api_proxy_rate_limit' => 120,
    'rate_window_seconds' => 600,
    'per_page' => 20,
];