<?php
require_once __DIR__ . '/../../functions.php';

start_session();
$cfg = $GLOBALS['CFG'];

// ——— Server-side lookups (self-POST, action = which lookup) ———
$osintAction = (string)($_POST['osint_action'] ?? '');
$osintInput = trim((string)($_POST['osint_input'] ?? ''));
$osintError = null;
$osintResult = [];

if ($osintAction !== '') {
    if (!csrf_verify()) {
        $osintError = 'Invalid CSRF token. Reload the page and try again.';
    } elseif (!rate_limit_check('osint', 30, (int)$cfg['rate_window_seconds'])) {
        $osintError = 'Rate limit reached — wait a few minutes between lookups.';
    } elseif ($osintInput === '') {
        $osintError = 'Enter something to look up first.';
    } else {
        $osintResult = run_osint_lookup($osintAction, $osintInput);
        if (isset($osintResult['__error'])) {
            $osintError = $osintResult['__error'];
            unset($osintResult['__error']);
        }
    }
}

function osint_field(string $label, string $value): string
{
    return '<tr><td class="text-secondary small" style="width:38%;">' . e($label) . '</td><td class="small" style="word-break:break-all;">' . e($value) . '</td></tr>';
}

function run_osint_lookup(string $action, string $input): array
{
    $rows = [];
    switch ($action) {
        case 'ip':
            if (!filter_var($input, FILTER_VALIDATE_IP)) {
                return ['__error' => 'Not a valid IPv4/IPv6 address.'];
            }
            $json = http_get('http://ip-api.com/json/' . rawurlencode($input) . '?fields=status,message,country,countryCode,regionName,city,isp,org,as,lat,lon,timezone,proxy,hosting,mobile', 6);
            $d = $json !== null ? json_decode($json, true) : null;
            if (!is_array($d) || ($d['status'] ?? '') !== 'success') {
                return ['__error' => 'Lookup failed' . (is_array($d) && isset($d['message']) ? ': ' . $d['message'] : '.')];
            }
            $rows[] = osint_field('IP address', $d['query'] ?? $input);
            foreach (['country' => 'Country', 'countryCode' => 'Country code', 'regionName' => 'Region', 'city' => 'City',
                'isp' => 'ISP', 'org' => 'Organization', 'as' => 'AS number', 'lat' => 'Latitude', 'lon' => 'Longitude',
                'timezone' => 'Timezone'] as $k => $label) {
                if (!empty($d[$k])) {
                    $rows[] = osint_field($label, $d[$k]);
                }
            }
            foreach (['proxy' => 'Proxy/VPN', 'hosting' => 'Datacenter/hosting', 'mobile' => 'Mobile network'] as $k => $label) {
                $rows[] = osint_field($label, !empty($d[$k]) ? 'yes' : 'no');
            }
            return $rows;

        case 'whois':
            if (preg_match('/^[A-Za-z0-9.-]+\.[A-Za-z]{2,24}$/', $input) !== 1) {
                return ['__error' => 'Enter a domain like example.com (no scheme, no path).'];
            }
            $json = http_get('https://rdap.org/domain/' . rawurlencode(strtolower($input)), 8);
            $d = $json !== null ? json_decode($json, true) : null;
            if (!is_array($d)) {
                return ['__error' => 'WHOIS lookup failed — domain may not exist, or RDAP is unreachable.'];
            }
            $rows[] = osint_field('Domain', $d['ldhName'] ?? $d['handle'] ?? $input);
            foreach (($d['events'] ?? []) as $ev) {
                $rows[] = osint_field(ucfirst((string)($ev['eventAction'] ?? 'event')), $ev['eventDate'] ?? '');
            }
            foreach (($d['status'] ?? []) as $st) {
                $rows[] = osint_field('Status', $st);
            }
            $registrant = '';
            foreach (($d['entities'] ?? []) as $ent) {
                foreach (($ent['roles'] ?? []) as $role) {
                    if ($role === 'registrant') {
                        foreach (($ent['vcardArray'] ?? [[], []])[1] as $v) {
                            if (($v[0] ?? '') === 'fn') {
                                $registrant = $v[3] ?? '';
                            }
                        }
                    }
                }
            }
            if ($registrant !== '') {
                $rows[] = osint_field('Registrant', $registrant);
            }
            foreach (($d['nameservers'] ?? []) as $ns) {
                $rows[] = osint_field('Name server', $ns['ldhName'] ?? '');
            }
            return $rows;

        case 'crt':
            if (preg_match('/^[A-Za-z0-9.-]+\.[A-Za-z]{2,24}$/', $input) !== 1) {
                return ['__error' => 'Enter a domain like example.com.'];
            }
            $json = http_get('https://crt.sh/?q=' . rawurlencode('%' . strtolower($input)) . '&output=json', 10);
            $d = $json !== null ? json_decode($json, true) : null;
            if (!is_array($d)) {
                return ['__error' => 'crt.sh did not return data (network blocked or no certificates).'];
            }
            $names = [];
            foreach ($d as $cert) {
                foreach (preg_split('/\s+/', (string)($cert['name_value'] ?? '')) as $n) {
                    $n = trim($n, " \t\n\r\0\x0B*.");
                    if ($n !== '' && !in_array($n, $names, true)) {
                        $names[] = $n;
                    }
                }
            }
            sort($names);
            if (count($names) === 0) {
                return ['__error' => 'No certificates found for that domain.'];
            }
            $rows[] = osint_field('Certificates found', count($names));
            $rows[] = '<tr><td colspan="2" class="small">' . implode(', ', array_map('e', array_slice($names, 0, 120))) . (count($names) > 120 ? ', …' : '') . '</td></tr>';
            return $rows;

        case 'email':
            if (filter_var($input, FILTER_VALIDATE_EMAIL) === false) {
                return ['__error' => 'Not a valid email address.'];
            }
            $domain = strtolower((string)substr($input, (int)strrpos($input, '@') + 1));
            $rows[] = osint_field('Email', $input);
            $rows[] = osint_field('Domain', $domain);
            if (!function_exists('dns_get_record')) {
                $rows[] = osint_field('DNS records', 'DNS lookup not available on this server');
                return $rows;
            }
            $mx = @dns_get_record($domain, DNS_MX);
            if (is_array($mx) && count($mx)) {
                foreach ($mx as $r) {
                    $rows[] = osint_field('MX record', trim((string)$r['target'], '.') . ' (priority ' . (int)$r['pri'] . ')');
                }
            } else {
                $rows[] = osint_field('MX records', 'none');
            }
            $a = @dns_get_record($domain, DNS_A);
            if (is_array($a) && count($a)) {
                foreach ($a as $r) {
                    $rows[] = osint_field('A record', $r['ip']);
                }
            }
            $txt = @dns_get_record($domain, DNS_TXT);
            if (is_array($txt) && count($txt)) {
                foreach (array_slice($txt, 0, 5) as $r) {
                    $rows[] = osint_field('TXT record', mb_substr((string)$r['txt'], 0, 120));
                }
            }
            return $rows;
    }
    return ['__error' => 'Unknown lookup type.'];
}

page_header('OSINT Toolkit');
?>
<div class="container" style="max-width: 1100px;">
    <h1 class="h4 mb-1 reveal in-view">OSINT Toolkit</h1>
    <p class="text-secondary mb-4 reveal in-view">Gather publicly available information by username, email, phone, domain or IP. The new "Live lookups" section queries public registries directly from this server.</p>

    <div class="alert alert-secondary reveal in-view">
        <strong>What is OSINT?</strong> Open-Source INTelligence — finding information that is already public: profiles, certificates, DNS records, breached-lookup services, search engines. This page does not scrape or hack anything; it opens relevant public resources for your search term so you can hunt manually. <strong>Use it legally:</strong> only research your own assets or things you're authorized to investigate, and respect privacy laws.
    </div>

    <?php if ($osintError !== null): ?>
        <div class="alert alert-danger reveal in-view"><?= e($osintError) ?></div>
    <?php endif; ?>

    <h2 class="h6 mb-3 reveal in-view" style="border-bottom:1px solid var(--line);padding-bottom:.5rem;">⚡ Live lookups (server-side)</h2>
    <div class="row row-cols-1 row-cols-md-2 g-4 mb-4">

        <div class="col reveal">
            <div class="card h-100"><div class="card-body">
                <h2 class="h6 mb-3">🖥️ IP intelligence</h2>
                <form method="post" action="index.php" class="mb-2">
                    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="osint_action" value="ip">
                    <div class="input-group">
                        <input class="form-control" name="osint_input" placeholder="8.8.8.8" value="<?= $osintAction === 'ip' ? e($osintInput) : '' ?>" required>
                        <button class="btn btn-outline-light" type="submit">Look up</button>
                    </div>
                </form>
                <?php if ($osintAction === 'ip' && count($osintResult)): ?><table class="table table-sm table-dark mb-0"><?= implode('', $osintResult) ?></table><?php endif; ?>
            </div></div>
        </div>

        <div class="col reveal">
            <div class="card h-100"><div class="card-body">
                <h2 class="h6 mb-3">🌐 Domain WHOIS (RDAP)</h2>
                <form method="post" action="index.php" class="mb-2">
                    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="osint_action" value="whois">
                    <div class="input-group">
                        <input class="form-control" name="osint_input" placeholder="example.com" value="<?= $osintAction === 'whois' ? e($osintInput) : '' ?>" required>
                        <button class="btn btn-outline-light" type="submit">Look up</button>
                    </div>
                </form>
                <?php if ($osintAction === 'whois' && count($osintResult)): ?><table class="table table-sm table-dark mb-0"><?= implode('', $osintResult) ?></table><?php endif; ?>
            </div></div>
        </div>

        <div class="col reveal">
            <div class="card h-100"><div class="card-body">
                <h2 class="h6 mb-3">🔐 Certificate transparency (crt.sh)</h2>
                <form method="post" action="index.php" class="mb-2">
                    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="osint_action" value="crt">
                    <div class="input-group">
                        <input class="form-control" name="osint_input" placeholder="example.com" value="<?= $osintAction === 'crt' ? e($osintInput) : '' ?>" required>
                        <button class="btn btn-outline-light" type="submit">Find subdomains</button>
                    </div>
                </form>
                <?php if ($osintAction === 'crt' && count($osintResult)): ?><table class="table table-sm table-dark mb-0"><?= implode('', $osintResult) ?></table><?php endif; ?>
            </div></div>
        </div>

        <div class="col reveal">
            <div class="card h-100"><div class="card-body">
                <h2 class="h6 mb-3">✉️ Email domain check</h2>
                <form method="post" action="index.php" class="mb-2">
                    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="osint_action" value="email">
                    <div class="input-group">
                        <input class="form-control" name="osint_input" type="email" placeholder="user@example.com" value="<?= $osintAction === 'email' ? e($osintInput) : '' ?>" required>
                        <button class="btn btn-outline-light" type="submit">Check</button>
                    </div>
                </form>
                <?php if ($osintAction === 'email' && count($osintResult)): ?><table class="table table-sm table-dark mb-0"><?= implode('', $osintResult) ?></table><?php endif; ?>
            </div></div>
        </div>

    </div>

    <h2 class="h6 mb-3 reveal in-view" style="border-bottom:1px solid var(--line);padding-bottom:.5rem;">🔎 Search engines & profiles (opens in new tab)</h2>
    <div class="row row-cols-1 row-cols-md-2 g-4">

        <div class="col reveal">
            <div class="card h-100"><div class="card-body">
                <h2 class="h6 mb-3">🔗 Username lookups</h2>
                <form class="osint-form" onsubmit="runOsint('username', event, [['Sherlock','https://sherlock-project.github.io/?username='],['Maigret','https://maigret.app/?q='],['namecheckr','https://www.namecheckr.com/check/'],['instantusername','https://instantusername.com/#/name/'],['whoisxml-people','https://people.whoxy.com/']])">
                    <div class="input-group mb-2">
                        <input class="form-control" placeholder="username" required>
                        <button class="btn btn-outline-light" type="submit">Search</button>
                    </div>
                </form>
                <div class="results d-grid gap-1"></div>
            </div></div>
        </div>

        <div class="col reveal">
            <div class="card h-100"><div class="card-body">
                <h2 class="h6 mb-3">✉️ Email lookups</h2>
                <form class="osint-form" onsubmit="runOsint('email', event, [['Hunter','https://hunter.io/search/'],['EmailRep','https://emailrep.io/'],['Social-email-search','https://tools.emailhippo.com/email-search/'],['GHunt (docs)','https://help.ghunt.io/']])">
                    <div class="input-group mb-2">
                        <input class="form-control" type="email" placeholder="user@example.com" required>
                        <button class="btn btn-outline-light" type="submit">Search</button>
                    </div>
                </form>
                <div class="results d-grid gap-1"></div>
            </div></div>
        </div>

        <div class="col reveal">
            <div class="card h-100"><div class="card-body">
                <h2 class="h6 mb-3">📱 Phone lookups</h2>
                <form class="osint-form" onsubmit="runOsint('phone', event, [['Truecaller (web)','https://www.truecaller.com/search/'],['Numlookup','https://www.numlookup.com/'],['Reverse Phone Lookup','https://www.spytox.com/'],['FreeCarrierLookup','https://freecarrierlookup.com/']])">
                    <div class="input-group mb-2">
                        <input class="form-control" placeholder="+1234567890" required>
                        <button class="btn btn-outline-light" type="submit">Search</button>
                    </div>
                </form>
                <div class="results d-grid gap-1"></div>
            </div></div>
        </div>

        <div class="col reveal">
            <div class="card h-100"><div class="card-body">
                <h2 class="h6 mb-3">🌐 Domain lookups</h2>
                <form class="osint-form" onsubmit="runOsint('domain', event, [['crt.sh (certificates)','https://crt.sh/?q=%25'],['DNSlytics','https://dnslytics.com/domain/'],['Onyphe','https://onyphe.io/search?q=hostname%3A'],['Shodan','https://www.shodan.io/search?query=hostname%3A'],['VirusTotal','https://www.virustotal.com/']])">
                    <div class="input-group mb-2">
                        <input class="form-control" placeholder="example.com" required>
                        <button class="btn btn-outline-light" type="submit">Search</button>
                    </div>
                </form>
                <div class="results d-grid gap-1"></div>
            </div></div>
        </div>

        <div class="col reveal">
            <div class="card h-100"><div class="card-body">
                <h2 class="h6 mb-3">🖥️ IP lookups</h2>
                <form class="osint-form" onsubmit="runOsint('ip', event, [['Shodan','https://www.shodan.io/host/'],['MXToolbox','https://mxtoolbox.com/SuperTool.aspx?action=lookup'],['BGPView','https://bgpview.io/search/'],['PublicWWW','https://publicwww.com/websites/'],['SpyOnIt','https://www.spyonit.com/']])">
                    <div class="input-group mb-2">
                        <input class="form-control" placeholder="8.8.8.8" required>
                        <button class="btn btn-outline-light" type="submit">Search</button>
                    </div>
                </form>
                <div class="results d-grid gap-1"></div>
            </div></div>
        </div>

        <div class="col reveal">
            <div class="card h-100"><div class="card-body">
                <h2 class="h6 mb-3">🎮 Gaming & social profiles</h2>
                <div class="d-grid gap-2">
                    <?php
                    $profileLinks = [
                        ['TikTok', 'https://www.tiktok.com/@'],
                        ['Instagram', 'https://www.instagram.com/'],
                        ['X / Twitter', 'https://x.com/'],
                        ['YouTube', 'https://www.youtube.com/@'],
                        ['Twitch', 'https://www.twitch.tv/'],
                        ['Discord ID — lookup', 'https://discordlookup.com/user/'],
                        ['Steam community', 'https://steamcommunity.com/id/'],
                        ['Roblox', 'https://www.roblox.com/user.aspx?username='],
                        ['Epic Games', 'https://www.epicgames.com/'],
                        ['PlayStation Network', 'https://psnprofiles.com/'],
                        ['Xbox Live', 'https://xboxgamertag.com/search/'],
                        ['GitHub', 'https://github.com/'],
                        ['Reddit', 'https://www.reddit.com/user/'],
                        ['Spotify', 'https://open.spotify.com/search/'],
                        ['Snapchat', 'https://www.snapchat.com/add/'],
                        ['Telegram', 'https://t.me/'],
                    ];
                    foreach ($profileLinks as $l):
                    ?>
                    <a class="btn btn-outline-light btn-sm text-start" href="<?= e($l[1]) ?>" target="_blank" rel="noopener"><?= e($l[0]) ?></a>
                    <?php endforeach; ?>
                </div>
            </div></div>
        </div>

        <div class="col reveal">
            <div class="card h-100"><div class="card-body">
                <h2 class="h6 mb-3">🗂 Hash & breach lookups</h2>
                <div class="d-grid gap-2">
                    <?php
                    $hashLinks = [
                        ['Hash identifier (hashes.org)', 'https://hashes.org/search.php'],
                        ['Hashtoolkit', 'https://hashtoolkit.com/'],
                        ['MD5Decrypt', 'https://md5decrypt.net/'],
                        ['dCode hash tools', 'https://www.dcode.fr/hash-function'],
                        ['Have I Been Pwned', 'https://haveibeenpwned.com/'],
                        ['DeHashed (paid)', 'https://dehashed.com/'],
                    ];
                    foreach ($hashLinks as $l):
                    ?>
                    <a class="btn btn-outline-light btn-sm text-start" href="<?= e($l[1]) ?>" target="_blank" rel="noopener"><?= e($l[0]) ?></a>
                    <?php endforeach; ?>
                </div>
            </div></div>
        </div>

        <div class="col reveal">
            <div class="card h-100"><div class="card-body">
                <h2 class="h6 mb-3">🌍 Manual lookup kit</h2>
                <div class="d-grid gap-2">
                    <?php
                    $manualLinks = [
                        ['WhatIsMyIP', 'https://whatismyipaddress.com/'],
                        ['Google dork builder', 'https://www.exploit-db.com/google-hacking-database'],
                        ['Wayback Machine', 'https://web.archive.org/'],
                        ['Email extractor (Hunter)', 'https://hunter.io/'],
                        ['DNS checker', 'https://dnschecker.org/'],
                    ];
                    foreach ($manualLinks as $l):
                    ?>
                    <a class="btn btn-outline-light btn-sm text-start" href="<?= e($l[1]) ?>" target="_blank" rel="noopener"><?= e($l[0]) ?></a>
                    <?php endforeach; ?>
                </div>
            </div></div>
        </div>

    </div>

    <p class="text-secondary small mt-4">Live lookups are rate-limited and hit public registries (ip-api.com, rdap.org, crt.sh). For legal, educational and research purposes only. Please respect privacy and applicable law.</p>
</div>

<script>
function runOsint(type, event, sources) {
    event.preventDefault();
    var form = event.target;
    var value = form.querySelector('input').value.trim();
    var box = form.closest('.card').querySelector('.results');
    box.innerHTML = '';
    sources.forEach(function (s) {
        var a = document.createElement('a');
        var url = s[1];
        if (url.indexOf('%25') !== -1) url = url.replace('%25', encodeURIComponent(value));
        else url += encodeURIComponent(value);
        a.href = url;
        a.target = '_blank';
        a.rel = 'noopener';
        a.className = 'btn btn-sm btn-primary';
        a.textContent = '→ ' + s[0];
        box.appendChild(a);
    });
}
</script>

<style>
    .osint-form input { font-family: "JetBrains Mono", monospace; }
    .osint-form .results a { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
</style>
<?php page_footer(); ?>