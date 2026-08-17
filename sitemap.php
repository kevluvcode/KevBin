<?php
require_once __DIR__ . '/functions.php';

$base = (string)($GLOBALS['CFG']['base_url'] ?? 'https://kevbin.ct.ws/');
$base = rtrim($base, '/');

// Main site pages included in the sitemap.
$pages = [
    '', 'index.php', 'tools/', 'browse.php', 'random.php', 'files.php',
    'tos.php', 'privacy.php', 'legal.php', 'api_docs.php',
    'discord_tos.php', 'discord_privacy.php',
];

// Every tool subfolder becomes its own entry automatically.
$tools = [];
foreach (glob(__DIR__ . '/tools/*', GLOB_ONLYDIR) as $dir) {
    $slug = basename($dir);
    if ($slug === '' || $slug[0] === '_') {
        continue;
    }
    $tools[] = 'tools/' . $slug . '/';
}

header('Content-Type: application/xml; charset=utf-8');
echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
<?php foreach ([$pages, $tools] as $group): foreach ($group as $p): ?>
  <url>
    <loc><?= e($base . '/' . ltrim($p, '/')) ?></loc>
    <changefreq>weekly</changefreq>
    <priority><?= $p === '' || $p === 'index.php' || $p === 'tools/' ? '0.9' : '0.7' ?></priority>
  </url>
<?php endforeach; endforeach; ?>
</urlset>