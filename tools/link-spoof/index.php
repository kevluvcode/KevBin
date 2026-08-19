<?php
require_once __DIR__ . '/../../functions.php';

start_session();
$GLOBALS['_meta'] = [
    'description' => 'URL obfuscation and multi-shortener chain — see how links can be disguised through encoding, Unicode tricks and cascading shorteners. Educational tool.',
    'keywords' => 'URL obfuscation, link spoof, URL shortener, redirect chain, phishing awareness',
];
page_header('Link Spoof / Obfuscator');

$result = null;
$error = '';

function ls_curl(string $url, array $opts = []): array {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_TIMEOUT, 8);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
    curl_setopt($ch, CURLOPT_MAXREDIRS, 3);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Safari/537.36');
    foreach ($opts as $k => $v) {
        curl_setopt($ch, $k, $v);
    }
    $body = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $err  = curl_error($ch);
    curl_close($ch);
    return ['body' => (string)$body, 'status' => $code, 'error' => $err];
}

function shorten_tinyurl(string $url): array {
    $r = ls_curl('https://tinyurl.com/api-create.php?url=' . urlencode($url));
    $short = trim($r['body']);
    if ($r['status'] >= 200 && $r['status'] < 400 && str_starts_with($short, 'http')) {
        return ['ok' => true, 'short' => $short, 'status' => $r['status']];
    }
    return ['ok' => false, 'error' => $short !== '' ? substr($short, 0, 120) : 'empty response', 'status' => $r['status']];
}

function shorten_isgd(string $url): array {
    $r = ls_curl('https://is.gd/create.php?format=simple&url=' . urlencode($url));
    $short = trim($r['body']);
    if ($r['status'] >= 200 && $r['status'] < 400 && str_starts_with($short, 'http')) {
        return ['ok' => true, 'short' => $short, 'status' => $r['status']];
    }
    return ['ok' => false, 'error' => $short !== '' ? substr($short, 0, 120) : 'empty response', 'status' => $r['status']];
}

function shorten_vgd(string $url): array {
    $r = ls_curl('https://v.gd/create.php?format=simple&url=' . urlencode($url));
    $short = trim($r['body']);
    if ($r['status'] >= 200 && $r['status'] < 400 && str_starts_with($short, 'http')) {
        return ['ok' => true, 'short' => $short, 'status' => $r['status']];
    }
    return ['ok' => false, 'error' => $short !== '' ? substr($short, 0, 120) : 'empty response', 'status' => $r['status']];
}

function shorten_dagd(string $url): array {
    $r = ls_curl('https://da.gd/s?url=' . urlencode($url));
    $short = trim($r['body']);
    if ($r['status'] >= 200 && $r['status'] < 400 && str_starts_with($short, 'http')) {
        return ['ok' => true, 'short' => $short, 'status' => $r['status']];
    }
    return ['ok' => false, 'error' => $short !== '' ? substr($short, 0, 120) : 'empty response', 'status' => $r['status']];
}

function shorten_shrtr(string $url): array {
    $r = ls_curl('https://shrtr.top/api/v1/shorten', [
        CURLOPT_POST       => true,
        CURLOPT_POSTFIELDS => json_encode(['url' => $url]),
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
    ]);
    $data = json_decode($r['body'], true);
    if ($data !== null && !empty($data['short_url'])) {
        return ['ok' => true, 'short' => $data['short_url'], 'status' => $r['status']];
    }
    $err = $data['title'] ?? $data['detail'] ?? ($r['error'] ?: 'failed');
    return ['ok' => false, 'error' => $err, 'status' => $r['status']];
}

function shorten_zip1(string $url): array {
    $r = ls_curl('https://zip1.io/api/create', [
        CURLOPT_POST       => true,
        CURLOPT_POSTFIELDS => json_encode(['url' => $url]),
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
    ]);
    $data = json_decode($r['body'], true);
    if ($data !== null && !empty($data['short_url'])) {
        return ['ok' => true, 'short' => $data['short_url'], 'status' => $r['status']];
    }
    $err = $data['message'] ?? $data['error'] ?? ($r['error'] ?: 'failed');
    return ['ok' => false, 'error' => $err, 'status' => $r['status']];
}

function shorten_kevbin(string $url): array {
    $cfg = $GLOBALS['CFG'];
    $baseUrl = rtrim((string)($cfg['base_url'] ?? ''), '/');

    $r = ls_curl($baseUrl . '/api.php?action=link.create', [
        CURLOPT_POST       => true,
        CURLOPT_POSTFIELDS => http_build_query(['action' => 'link.create', 'url' => $url]),
        CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
    ]);

    $body = $r['body'];

    if (str_contains($body, 'toNumbers') && str_contains($body, 'slowAES')) {
        $cookie = solve_aes_challenge($body);
        if ($cookie !== null) {
            preg_match('/location\.href="([^"]+)"/', $body, $m);
            $redirUrl = $m[1] ?? ($baseUrl . '/api.php?action=link.create');
            $r2 = ls_curl($redirUrl, [
                CURLOPT_POST       => true,
                CURLOPT_POSTFIELDS => http_build_query(['action' => 'link.create', 'url' => $url]),
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/x-www-form-urlencoded',
                    'Cookie: __test=' . $cookie,
                ],
            ]);
            $body = $r2['body'];
        }
    }

    $data = json_decode($body, true);
    if ($data !== null && !empty($data['ok']) && !empty($data['short'])) {
        return ['ok' => true, 'short' => $data['short'], 'status' => $r['status']];
    }
    $err = $data['error'] ?? ($r['error'] ?: 'failed');
    return ['ok' => false, 'error' => $err, 'status' => $r['status']];
}

function solve_aes_challenge(string $html): ?string {
    if (!preg_match_all('/toNumbers\("([0-9a-f]+)"\)/', $html, $m)) return null;
    if (count($m[1]) < 3) return null;
    $a = hex2bin($m[1][0]);
    $b = hex2bin($m[1][1]);
    $c = hex2bin($m[1][2]);
    if ($a === false || $b === false || $c === false) return null;

    $cipher = openssl_decrypt($c, 'aes-128-cbc', $b, OPENSSL_RAW_DATA, $a);
    if ($cipher === false) return null;
    $pad = ord($cipher[strlen($cipher) - 1]);
    if ($pad >= 1 && $pad <= 16) {
        $cipher = substr($cipher, 0, -$pad);
    }
    return bin2hex($cipher);
}

function shorten_url(string $url, string $service): array {
    $map = [
        'tinyurl' => 'shorten_tinyurl',
        'isgd'    => 'shorten_isgd',
        'vgd'     => 'shorten_vgd',
        'dagd'    => 'shorten_dagd',
        'shrtr'   => 'shorten_shrtr',
        'zip1'    => 'shorten_zip1',
        'kevbin'  => 'shorten_kevbin',
    ];
    if (!isset($map[$service])) {
        return ['ok' => false, 'service' => $service, 'error' => 'unknown service', 'status' => 0];
    }
    $fn = $map[$service];
    $result = $fn($url);
    $result['service'] = $service;
    return $result;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify_or_fail();
    if (!rate_limit_check('link-spoof', 5, 60)) {
        $error = 'Too many requests. Wait a minute.';
    } else {
        $targetUrl = trim((string)($_POST['url'] ?? ''));
        if ($targetUrl === '' || (!str_starts_with($targetUrl, 'http://') && !str_starts_with($targetUrl, 'https://'))) {
            $error = 'Enter a valid URL starting with http:// or https://';
        } else {
            $obfuscations = array_values($_POST['obfuscations'] ?? []);
            $shorteners   = array_values($_POST['shorteners'] ?? ['tinyurl', 'isgd', 'vgd']);
            $rounds       = max(1, min(15, (int)($_POST['rounds'] ?? 3)));

            $parsed = parse_url($targetUrl);
            $host = $parsed['host'] ?? '';
            $path = ($parsed['path'] ?? '/') . (isset($parsed['query']) ? '?' . $parsed['query'] : '');

            $variants = [];
            if (in_array('percent', $obfuscations)) {
                $encoded = implode('', array_map(fn($c) => $c === '.' ? '.' : '%' . bin2hex($c), str_split($host)));
                $variants[] = ['type' => 'Percent Encoded', 'url' => ($parsed['scheme'] ?? 'https') . '://' . $encoded . $path];
            }
            if (in_array('unicode', $obfuscations)) {
                $map2 = ['a'=>'а','e'=>'е','o'=>'о','p'=>'р','c'=>'с','i'=>'і'];
                $uni = implode('', array_map(fn($c) => $map2[$c] ?? $c, str_split($host)));
                $variants[] = ['type' => 'Unicode Homoglyphs', 'url' => ($parsed['scheme'] ?? 'https') . '://' . $uni . $path];
            }
            if (in_array('subdomain', $obfuscations)) {
                $variants[] = ['type' => 'Fake Subdomain', 'url' => ($parsed['scheme'] ?? 'https') . '://' . $host . '.' . $host . $path];
            }
            if (in_array('double_encode', $obfuscations)) {
                $double = rawurlencode(rawurlencode($targetUrl));
                $variants[] = ['type' => 'Double Encoded', 'url' => ($parsed['scheme'] ?? 'https') . '://' . $host . '/redirect?url=' . $double];
            }
            if (in_array('at_trick', $obfuscations)) {
                $variants[] = ['type' => '@ Trick', 'url' => ($parsed['scheme'] ?? 'https') . '://' . $host . '@' . $host . $path];
            }
            if (in_array('ip_decimal', $obfuscations)) {
                $parts = explode('.', $host);
                if (count($parts) === 4 && array_reduce($parts, fn($ok, $p) => $ok && is_numeric($p) && (int)$p >= 0 && (int)$p <= 255, true)) {
                    $dec = (int)$parts[0] * 16777216 + (int)$parts[1] * 65536 + (int)$parts[2] * 256 + (int)$parts[3];
                    $variants[] = ['type' => 'IP Decimal', 'url' => ($parsed['scheme'] ?? 'https') . '://' . $dec . $path];
                }
            }

            $chain = [];
            $current = $targetUrl;

            for ($round = 0; $round < $rounds; $round++) {
                foreach ($shorteners as $svc) {
                    $r = shorten_url($current, $svc);
                    $chain[] = array_merge($r, ['round' => $round + 1]);
                    if ($r['ok'] && !empty($r['short'])) {
                        $current = $r['short'];
                    }
                }
            }

            $result = [
                'original'           => $targetUrl,
                'obfuscated_variants' => $variants,
                'chain'              => $chain,
                'final'              => $current,
                'total_rounds'       => $rounds,
            ];
        }
    }
}
?>
<div class="container" style="max-width: 900px;">
    <h1 class="h4 mb-2 reveal in-view">Link Spoof / Obfuscator</h1>
    <p class="text-secondary mb-3 reveal in-view">See how URLs can be disguised through encoding tricks, Unicode manipulation and cascading URL shorteners. Useful for understanding phishing techniques and building awareness.</p>

    <div class="alert alert-warning small mb-3">
        <strong>Educational use only.</strong> This tool demonstrates how link obfuscation works so you can recognise and defend against phishing. Never use this to deceive others.
    </div>

    <?php if ($error !== ''): ?>
        <div class="alert alert-danger"><?= e($error) ?></div>
    <?php endif; ?>

    <?php if ($result !== null): ?>
        <div class="card mb-3 border-success"><div class="card-body">
            <h2 class="h6 mb-3 text-success">Original URL</h2>
            <div class="mb-3">
                <input class="form-control form-control-sm font-monospace" readonly value="<?= e($result['original'] ?? '') ?>">
            </div>

            <?php if (!empty($result['obfuscated_variants'])): ?>
                <h2 class="h6 mb-2">Obfuscated Variants</h2>
                <div class="mb-3">
                <?php foreach ($result['obfuscated_variants'] as $v): ?>
                    <div class="input-group input-group-sm mb-1">
                        <span class="input-group-text" style="min-width:140px;"><?= e($v['type'] ?? '') ?></span>
                        <input class="form-control form-control-sm font-monospace" readonly value="<?= e($v['url'] ?? '') ?>">
                        <button class="btn btn-outline-secondary btn-sm" onclick="navigator.clipboard.writeText(this.previousElementSibling.value);this.textContent='Copied!';setTimeout(()=>this.textContent='Copy',1500)">Copy</button>
                    </div>
                <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($result['chain'])): ?>
                <h2 class="h6 mb-2">Shortener Chain</h2>
                <div class="table-responsive">
                <table class="table table-sm table-striped small mb-0">
                    <thead><tr><th>#</th><th>Round</th><th>Shortener</th><th>Short URL</th><th>HTTP</th><th></th></tr></thead>
                    <tbody>
                    <?php foreach ($result['chain'] as $i => $c): ?>
                        <tr class="<?= ($c['ok'] ?? false) ? '' : 'table-danger' ?>">
                            <td><?= $i + 1 ?></td>
                            <td><?= (int)($c['round'] ?? 0) ?></td>
                            <td><?= e($c['service'] ?? '') ?></td>
                            <td class="font-monospace text-break"><?= e($c['short'] ?? $c['error'] ?? 'failed') ?></td>
                            <td><?= (int)($c['status'] ?? 0) ?></td>
                            <td>
                                <?php if (!empty($c['short'])): ?>
                                    <button class="btn btn-outline-secondary btn-sm py-0" onclick="navigator.clipboard.writeText('<?= e($c['short']) ?>');this.textContent='Copied!';setTimeout(()=>this.textContent='Copy',1500)">Copy</button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                </div>
            <?php endif; ?>

            <?php if (!empty($result['final'])): ?>
                <div class="mt-3 p-3 bg-dark rounded">
                    <div class="small text-secondary mb-1">Final shortened URL (chain <?= (int)($result['total_rounds'] ?? 0) ?> rounds):</div>
                    <div class="input-group">
                        <input class="form-control font-monospace" readonly value="<?= e($result['final']) ?>">
                        <button class="btn btn-success" onclick="navigator.clipboard.writeText(this.previousElementSibling.value);this.textContent='Copied!';setTimeout(()=>this.textContent='Copy Final URL',1500)">Copy Final URL</button>
                    </div>
                </div>
            <?php endif; ?>
        </div></div>
    <?php endif; ?>

    <form method="post" action="index.php" onsubmit="var b=this.querySelector('button[type=submit]');if(b){b.disabled=true;b.textContent='Processing…';}">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">

        <div class="card mb-3 reveal in-view"><div class="card-body">
            <h2 class="h6 mb-3">Target URL</h2>
            <div class="mb-0">
                <label class="form-label small text-secondary">Enter the URL to obfuscate</label>
                <input class="form-control" name="url" placeholder="https://example.com" required
                    value="<?= e(trim((string)($_POST['url'] ?? ''))) ?>">
            </div>
        </div></div>

        <div class="card mb-3 reveal in-view"><div class="card-body">
            <h2 class="h6 mb-3">Obfuscation Layers</h2>
            <div class="row g-2">
                <div class="col-md-4">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="obfuscations[]" value="percent" id="ob1" checked>
                        <label class="form-check-label small" for="ob1">Percent encoding (%XX)</label>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="obfuscations[]" value="unicode" id="ob2" checked>
                        <label class="form-check-label small" for="ob2">Unicode lookalikes</label>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="obfuscations[]" value="subdomain" id="ob3" checked>
                        <label class="form-check-label small" for="ob3">Fake subdomains</label>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="obfuscations[]" value="double_encode" id="ob4">
                        <label class="form-check-label small" for="ob4">Double encoding</label>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="obfuscations[]" value="at_trick" id="ob5" checked>
                        <label class="form-check-label small" for="ob5">@ domain trick</label>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="obfuscations[]" value="ip_decimal" id="ob6">
                        <label class="form-check-label small" for="ob6">IP decimal conversion</label>
                    </div>
                </div>
            </div>
        </div></div>

        <div class="card mb-3 reveal in-view"><div class="card-body">
            <h2 class="h6 mb-3">Shorteners &amp; Chain</h2>
            <div class="row g-2 mb-3">
                <div class="col-md-6">
                    <label class="form-label small text-secondary">Shorteners (in order)</label>
                    <div class="d-flex flex-wrap gap-2">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="shorteners[]" value="tinyurl" id="s1" checked>
                            <label class="form-check-label small" for="s1">TinyURL</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="shorteners[]" value="isgd" id="s2" checked>
                            <label class="form-check-label small" for="s2">is.gd</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="shorteners[]" value="vgd" id="s3" checked>
                            <label class="form-check-label small" for="s3">v.gd</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="shorteners[]" value="dagd" id="s4">
                            <label class="form-check-label small" for="s4">da.gd</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="shorteners[]" value="shrtr" id="s5">
                            <label class="form-check-label small" for="s5">Shrtr</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="shorteners[]" value="kevbin" id="s6">
                            <label class="form-check-label small" for="s6">KevBin</label>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <label class="form-label small text-secondary">Chain rounds (1–15)</label>
                    <input class="form-control" type="number" name="rounds" min="1" max="15" value="<?= e((string)($_POST['rounds'] ?? 3)) ?>">
                    <div class="form-text">Each round feeds the shortened URL back through all shorteners again.</div>
                </div>
            </div>
        </div></div>

        <div class="text-center mb-4">
            <button type="submit" class="btn btn-warning btn-lg">Obfuscate &amp; Shorten</button>
        </div>
    </form>

    <p class="text-secondary small mb-4">Shortening runs directly from the server via each shortener's public API. No URLs are stored. Used for security awareness and phishing education.</p>
</div>
<?php page_footer(); ?>
