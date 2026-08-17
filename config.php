<?php
// =====================================================================
//  KevBin — configuration file
// =====================================================================
//  Copy this file to config.php and fill in your own credentials.
//  NEVER commit real credentials to a public repository.
// =====================================================================
$CFG = [
    'db_host' => 'YOUR_DB_HOST',
    'db_name' => 'YOUR_DB_NAME',
    'db_user' => 'YOUR_DB_USER',
    'db_pass' => 'YOUR_DB_PASS',

    'base_url' => 'https://yourdomain.com/',
    'site_name' => 'KevBin',
    'debug' => false,

    'auto_migrate' => true,

    'recovery_salt' => 'GENERATE_A_RANDOM_64_CHAR_HEX_STRING',

    // GitHub OAuth sign-in.
    'github_client_id' => '',
    'github_client_secret' => '',

    // Discord OAuth sign-in via a Cloudflare Worker bridge (the worker holds the
    // client_secret — it never lives in this repo).
    'discord_client_id' => '',
    'discord_bridge_url' => 'https://YOUR_WORKER_STILL_THUNDER.workers.dev',
    // Worker for tools (proxy, webhook sender, port scanner, etc.)
    'worker_url' => 'https://YOUR_WORKER_AUTUMN_TERM.workers.dev',
    // Scopes asked on the Dashboard "Connect Discord & fetch everything" flow.
    'discord_export_scope' => 'identify guilds connections',

    'github_repo_url' => '',

    // Support / donations.
    'bitcoin_address' => '',
    'donation_contact' => '',

    // Premium tiers (shown on support.php, granted by verified BTC payment or
    // manually by admins). 'days' = 0 means lifetime (never expires).
    'premium_tiers' => [
        'supporter' => ['name' => 'Supporter', 'price_usd' => 5,  'days' => 30,  'chars' => 500000,  'badge' => 'SUPPORTER'],
        'pro'       => ['name' => 'Pro',       'price_usd' => 10, 'days' => 30,  'chars' => 1000000, 'badge' => 'PRO'],
        'lifetime'  => ['name' => 'Lifetime',  'price_usd' => 40, 'days' => 0,   'chars' => 1000000, 'badge' => 'LIFETIME'],
    ],

    // BTC auto-verification (mempool.space, fallback blockstream.info).
    'btc_scan_interval' => 300,
    'btc_scan_timeout' => 12,
    'btc_wallet_floor_sats' => 1000,

    // Free-tier limits.
    'max_content_chars' => 100000,

    'meta_description' => 'Paste & share text online. Free, anonymous and super secure.',
    'logo_url' => '',
    'twitter_handle' => '',
    'google_site_verification' => '',

    'watermark' => true,
    'watermark_keyword' => true,

    'captcha' => true,
    'captcha_timeout' => 900,
    'captcha_attempts' => 3,

    'max_title_chars' => 120,
    'max_author_chars' => 50,
    'max_content_chars' => 100000,
    'premium_content_mult' => 5,
    'upload_max_mb' => 20,
    'premium_upload_mult' => 4,
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
    'file_upload_rate_limit' => 10,
    'rate_window_seconds' => 600,
    'per_page' => 20,

    'unlock_session_hours' => 6,

    'uploads_dir' => 'uploads/',
    'upload_max_mb' => 20,
    'file_analysis_max_mb' => 200,
    'upload_exts' => '',
    'upload_blocked_exts' => 'php,js,html',
    'upload_blocked_names' => 'index.html,index.htm,index.php,index.phtml',
];
