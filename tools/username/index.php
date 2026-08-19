<?php
require_once __DIR__ . '/../../functions.php';

start_session();
$me = current_user();
$username = trim((string)($_GET['u'] ?? ($_POST['username'] ?? '')));
$checked = [];
$statusNote = null;

if ($username !== '') {
    // Build the profile URL for a username on common platforms (these are all
    // public profile links — the tool does NOT log in or scrape anything).
    $sites = [
        ['GitHub', 'https://github.com/{u}'],
        ['Reddit', 'https://www.reddit.com/user/{u}'],
        ['X / Twitter', 'https://x.com/{u}'],
        ['Instagram', 'https://instagram.com/{u}'],
        ['TikTok', 'https://tiktok.com/@{u}'],
        ['YouTube', 'https://youtube.com/@{u}'],
        ['Twitch', 'https://twitch.tv/{u}'],
        ['Steam', 'https://steamcommunity.com/id/{u}'],
        ['Telegram', 'https://t.me/{u}'],
        ['Discord (via discord.bio)', 'https://discord.bio/{u}'],
        ['Pinterest', 'https://pinterest.com/{u}'],
        ['DeviantArt', 'https://deviantart.com/{u}'],
        ['Flickr', 'https://flickr.com/people/{u}'],
        ['SoundCloud', 'https://soundcloud.com/{u}'],
        ['Bandcamp', 'https://bandcamp.com/{u}'],
        ['Roblox', 'https://roblox.com/user.aspx?username={u}'],
        ['Pastebin', 'https://pastebin.com/u/{u}'],
        ['HackerNews', 'https://news.ycombinator.com/user?id={u}'],
        ['Keybase', 'https://keybase.io/{u}'],
        ['GitLab', 'https://gitlab.com/{u}'],
        ['Dribbble', 'https://dribbble.com/{u}'],
        ['Behance', 'https://behance.net/{u}'],
        ['Mastodon (mastodon.social)', 'https://mastodon.social/@{u}'],
        ['VK', 'https://vk.com/{u}'],
        ['Facebook', 'https://facebook.com/{u}'],
        ['Medium', 'https://medium.com/@{u}'],
        ['Spotify', 'https://open.spotify.com/user/{u}'],
        ['Replit', 'https://replit.com/@{u}'],
        ['Chess.com', 'https://chess.com/member/{u}'],
        ['HackTheBox', 'https://app.hackthebox.com/users/{u}'],
    ];

    if (!preg_match('/^[A-Za-z0-9_.]{2,32}$/', $username)) {
        $statusNote = 'Usernames: 2-32 chars, letters/numbers/._ only.';
    } else {
        foreach ($sites as [$name, $url]) {
            $checked[] = ['name' => $name, 'url' => str_replace('{u}', rawurlencode($username), $url)];
        }
        if ($me === null) {
            $statusNote = 'Log in to run the online availability check (it pings each site server-side).';
        } else {
            // Ping each site: a 200/302 to the profile page = account exists
            // (best-effort — some sites block bots; those get marked "unknown").
            // Route through Cloudflare Worker HTTP proxy if available.
            $cfg = $GLOBALS['CFG'];
            $bridgeUrl = rtrim((string)($cfg['worker_url'] ?? $cfg['discord_bridge_url'] ?? ''), '/');
            $useWorker = $bridgeUrl !== '' && function_exists('curl_init');
            foreach ($checked as &$c) {
                $c['status'] = 'unknown';
                $url = filter_var($c['url'], FILTER_VALIDATE_URL);
                if ($url === false) { continue; }
                $scheme = strtolower((string)parse_url($url, PHP_URL_SCHEME));
                if (!in_array($scheme, ['http', 'https'], true)) { continue; }

                if ($useWorker) {
                    // Route through worker HTTP proxy
                    $ch = curl_init($bridgeUrl . '/proxy/http');
                    curl_setopt_array($ch, [
                        CURLOPT_POST           => true,
                        CURLOPT_POSTFIELDS     => json_encode(['url' => $url, 'method' => 'HEAD', 'timeout_ms' => 8000]),
                        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
                        CURLOPT_RETURNTRANSFER => true,
                        CURLOPT_TIMEOUT        => 12,
                        CURLOPT_CONNECTTIMEOUT => 5,
                    ]);
                    $resp = curl_exec($ch);
                    $data = json_decode((string)$resp, true);
                    curl_close($ch);
                    $code = is_array($data) ? (int)($data['status'] ?? 0) : 0;
                } elseif (function_exists('curl_init')) {
                    $ch = curl_init($url);
                    curl_setopt_array($ch, [
                        CURLOPT_RETURNTRANSFER => true,
                        CURLOPT_TIMEOUT => 5,
                        CURLOPT_CONNECTTIMEOUT => 4,
                        CURLOPT_FOLLOWLOCATION => true,
                        CURLOPT_MAXREDIRS => 3,
                        CURLOPT_NOBODY => true,
                        CURLOPT_SSL_VERIFYPEER => false,
                        CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Safari/537.36',
                    ]);
                    $code = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
                    curl_exec($ch);
                    curl_close($ch);
                } else {
                    $ctx = stream_context_create([
                        'http' => ['method' => 'HEAD', 'timeout' => 5, 'ignore_errors' => true,
                            'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/126.0.0.0 Safari/537.36'],
                        'ssl' => ['verify_peer' => false, 'verify_peer_name' => false],
                    ]);
                    $fp = @fopen($url, 'r', false, $ctx);
                    if ($fp) {
                        $meta = stream_get_meta_data($fp);
                        $code = isset($meta['wrapper_data'][0]) ? (int)substr($meta['wrapper_data'][0], 9, 3) : 0;
                        fclose($fp);
                    } else {
                        $code = 0;
                    }
                }
                if ($code === 200 || $code === 301 || $code === 302 || $code === 303) {
                    $c['status'] = 'taken';
                } elseif ($code === 404 || $code === 410 || $code === 429) {
                    $c['status'] = $code === 429 ? 'unknown' : 'free';
                } else {
                    $c['status'] = $code >= 400 ? 'free' : 'unknown';
                }
            }
            unset($c);
            log_activity('tool_username', $username);
        }
    }
}

page_header('Username Search');
?>
<div class="container" style="max-width: 1000px;">
    <h1 class="h4 mb-1 reveal in-view">🔎 Username Search</h1>
    <p class="text-secondary mb-4 reveal in-view">Where is a username registered? Get the profile link on 30 platforms, plus a live availability check when you're logged in.</p>

    <div class="card mb-4 reveal in-view"><div class="card-body">
        <form method="get" action="index.php" class="row g-2 align-items-center">
            <div class="col-md-6">
                <input class="form-control" name="u" maxlength="32" placeholder="username" value="<?= e($username) ?>" required>
            </div>
            <div class="col-md-4">
                <button class="btn btn-primary w-100">Find profile links</button>
            </div>
            <div class="col-md-2 text-end">
                <a class="btn btn-outline-light" href="username.php">Reset</a>
            </div>
        </form>
        <?php if ($statusNote !== null): ?>
            <div class="alert alert-secondary small mt-3 mb-0"><?= e($statusNote) ?></div>
        <?php endif; ?>
    </div></div>

    <?php if (count($checked) > 0): ?>
        <div class="table-responsive reveal in-view">
            <table class="table table-dark table-hover align-middle">
                <thead><tr><th>Platform</th><th>Profile link</th><?php if ($me !== null): ?><th>Status</th><?php endif; ?></tr></thead>
                <tbody>
                <?php foreach ($checked as $c): ?>
                    <tr>
                        <td class="small fw-semibold"><?= e($c['name']) ?></td>
                        <td class="small"><a class="text-decoration-none" target="_blank" rel="noopener" href="<?= e($c['url']) ?>"><?= e($c['url']) ?></a></td>
                        <?php if ($me !== null): ?>
                            <td>
                                <?php if (($c['status'] ?? 'unknown') === 'taken'): ?><span class="badge bg-danger">taken</span>
                                <?php elseif (($c['status'] ?? 'unknown') === 'free'): ?><span class="badge bg-success">free</span>
                                <?php else: ?><span class="badge bg-secondary" title="The site blocked the check">unknown</span><?php endif; ?>
                            </td>
                        <?php endif; ?>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="alert alert-secondary small reveal in-view">
            "Taken" means the profile page responded normally. "Free" means it returned a
            not-found. Some platforms block automated checks — those show as "unknown",
            not as free. For legal research only.
        </div>
    <?php endif; ?>
</div>
<?php page_footer(); ?>