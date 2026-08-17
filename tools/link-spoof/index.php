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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify_or_fail();
    if (!rate_limit_check('link-spoof', 5, 60)) {
        $error = 'Too many requests. Wait a minute.';
    } else {
        $cfg = $GLOBALS['CFG'];
        $bridgeUrl = rtrim((string)($cfg['worker_url'] ?? ''), '/');
        if ($bridgeUrl === '') {
            $error = 'Worker bridge not configured.';
        } else {
            $targetUrl = trim((string)($_POST['url'] ?? ''));
            if ($targetUrl === '' || (!str_starts_with($targetUrl, 'http://') && !str_starts_with($targetUrl, 'https://'))) {
                $error = 'Enter a valid URL starting with http:// or https://';
            } else {
                $obfuscations = $_POST['obfuscations'] ?? [];
                $shorteners = $_POST['shorteners'] ?? ['tinyurl', 'isgd', 'vgd'];
                $rounds = max(1, min(5, (int)($_POST['rounds'] ?? 3)));

                $payload = [
                    'url'           => $targetUrl,
                    'obfuscations'  => array_values($obfuscations),
                    'shorteners'    => array_values($shorteners),
                    'rounds'        => $rounds,
                ];

                $ch = curl_init($bridgeUrl . '/link-spoof');
                curl_setopt_array($ch, [
                    CURLOPT_POST           => true,
                    CURLOPT_POSTFIELDS     => json_encode($payload),
                    CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_TIMEOUT        => 60,
                    CURLOPT_CONNECTTIMEOUT => 10,
                ]);
                $resp = curl_exec($ch);
                $httpCode = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
                $curlErr = curl_error($ch);
                curl_close($ch);

                if ($curlErr !== '') {
                    $error = 'Worker request failed: ' . $curlErr;
                } else {
                    $data = json_decode((string)$resp, true);
                    if ($data !== null && isset($data['ok']) && $data['ok']) {
                        $result = $data;
                    } elseif ($data !== null && isset($data['error'])) {
                        $error = 'Worker error: ' . $data['error'];
                    } else {
                        $error = 'Unexpected response (HTTP ' . $httpCode . ')';
                    }
                }
            }
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
                        <span class="input-group-text" style="min-width:120px;"><?= e($v['type'] ?? '') ?></span>
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
                    <thead><tr><th>#</th><th>Shortener</th><th>Short URL</th><th>HTTP</th><th></th></tr></thead>
                    <tbody>
                    <?php foreach ($result['chain'] as $i => $c): ?>
                        <tr class="<?= ($c['ok'] ?? false) ? '' : 'table-danger' ?>">
                            <td><?= $i + 1 ?></td>
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
                    <div class="small text-secondary mb-1">Final shortened URL (chain <?= (int)($result['total_rounds'] ?? 0) ?>x shortened):</div>
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
                            <input class="form-check-input" type="checkbox" name="shorteners[]" value="dagd" id="s3">
                            <label class="form-check-label small" for="s3">da.gd</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="shorteners[]" value="vgd" id="s4" checked>
                            <label class="form-check-label small" for="s4">v.gd</label>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <label class="form-label small text-secondary">Chain rounds (1–5)</label>
                    <input class="form-control" type="number" name="rounds" min="1" max="5" value="<?= e((string)($_POST['rounds'] ?? 3)) ?>">
                    <div class="form-text">Each round feeds the shortened URL back through all shorteners again.</div>
                </div>
            </div>
        </div></div>

        <div class="text-center mb-4">
            <button type="submit" class="btn btn-warning btn-lg">Obfuscate &amp; Shorten</button>
        </div>
    </form>

    <p class="text-secondary small mb-4">All work happens through a Cloudflare Worker. No URLs are stored. Used for security awareness and phishing education.</p>
</div>
<?php page_footer(); ?>
