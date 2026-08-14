<?php
require_once __DIR__ . '/../../functions.php';

start_session();
$url = trim((string)($_GET['q'] ?? $_POST['url'] ?? ''));
$result = null;
$error = null;
$targetUrl = '';

if ($url !== '') {
    $parsed = filter_var($url, FILTER_VALIDATE_URL);
    if ($parsed !== false) {
        $scheme = strtolower((string)parse_url($parsed, PHP_URL_SCHEME));
        if (in_array($scheme, ['http', 'https'], true)) {
            if (!url_allowed_public($parsed)) {
                $error = 'That URL is blocked: only public internet addresses can be inspected.';
            } elseif (function_exists('curl_init')) {
                $targetUrl = $parsed;
                $result = [
                    'requested' => $parsed,
                    'final' => $parsed,
                    'status' => 0,
                    'redirects' => 0,
                    'content_type' => '',
                    'server' => '',
                    'headers' => [],
                ];
                $ch = curl_init();
                curl_setopt_array($ch, [
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_TIMEOUT => 10,
                    CURLOPT_CONNECTTIMEOUT => 8,
                    CURLOPT_FOLLOWLOCATION => false,
                    CURLOPT_NOBODY => true,
                    CURLOPT_SSL_VERIFYPEER => false,
                    CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/126.0.0.0 Safari/537.36',
                ]);
                $hdr = [];
                curl_setopt($ch, CURLOPT_HEADERFUNCTION, function ($line) use (&$hdr) {
                    $hdr[] = rtrim($line);
                    return strlen($line);
                });
                // Manual hop walk with DNS pinning: every host is resolved once through
                // our own lookup (public_ip_for) and curl is forced to connect to that
                // exact public IP via CURLOPT_RESOLVE — immune to DNS rebinding.
                $hop = $parsed;
                $result['headers'][] = '> ' . $hop;
                for ($i = 0; $i < 7; $i++) {
                    $hp = parse_url($hop);
                    $host = (string)($hp['host'] ?? '');
                    $hScheme = strtolower((string)($hp['scheme'] ?? ''));
                    $port = (int)($hp['port'] ?? ($hScheme === 'https' ? 443 : 80));
                    $ip = public_ip_for($host);
                    if ($ip === null) {
                        $result['headers'][] = '← ' . $hop;
                        $result['headers'][] = '   ! blocked: host did not resolve to a public IP';
                        break;
                    }
                    curl_setopt($ch, CURLOPT_URL, $hop);
                    curl_setopt($ch, CURLOPT_RESOLVE, [$host . ':' . $port . ':' . $ip]);
                    $hdr = [];
                    curl_exec($ch);
                    $info = curl_getinfo($ch);
                    $code = (int)($info['http_code'] ?? 0);
                    $loc = (string)($info['redirect_url'] ?? '');
                    $result['status'] = $code;
                    $result['final'] = $hop;
                    $result['content_type'] = (string)($info['content_type'] ?? '');
                    $result['headers'][] = '← ' . $code . ' — ' . $hop;
                    $result['headers'] = array_merge($result['headers'], $hdr);
                    if ($code >= 300 && $code < 400) {
                        if ($loc === '') {
                            $result['headers'][] = '→ (redirect without a Location header — stopped)';
                            break;
                        }
                        if (!url_allowed_public($loc)) {
                            $result['headers'][] = '→ (blocked: non-public redirect target)';
                            break;
                        }
                        $result['headers'][] = '→ ' . $loc;
                        $result['redirects']++;
                        $hop = $loc;
                        continue;
                    }
                    break;
                }
                curl_close($ch);
                // TLS certificate details from the final host — also resolved and
                // pinned through our own lookup, so the probe cannot be rebinding-missed.
                try {
                    $fScheme = strtolower((string)parse_url($result['final'], PHP_URL_SCHEME));
                    $fHost = (string)parse_url($result['final'], PHP_URL_HOST);
                    if ($fScheme === 'https' && $fHost !== '') {
                        $fip = public_ip_for($fHost);
                        if ($fip !== null) {
                            $ctx = stream_context_create([
                                'ssl' => ['capture_peer_cert' => true, 'verify_peer' => false, 'verify_peer_name' => false, 'SNI_enabled' => true, 'peer_name' => $fHost],
                                'http' => ['timeout' => 8, 'header' => "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/126.0.0.0 Safari/537.36\r\n"],
                            ]);
                            $fp = @stream_socket_client('ssl://' . $fip . ':443', $errno, $errstr, 8, STREAM_CLIENT_CONNECT, $ctx);
                            if ($fp) {
                                $opts = stream_context_get_options($ctx)['ssl'];
                                if (!empty($opts['peer_certificate'])) {
                                    $cp = openssl_x509_parse($opts['peer_certificate']);
                                    if (is_array($cp)) {
                                        $result['cert'] = [
                                            'subject' => (string)($cp['subject']['CN'] ?? ''),
                                            'issuer' => (string)($cp['issuer']['CN'] ?? (string)($cp['issuer']['O'] ?? '')),
                                            'valid_from' => isset($cp['validFrom_time_t']) ? gmdate('Y-m-d H:i', (int)$cp['validFrom_time_t']) . ' UTC' : '',
                                            'valid_to' => isset($cp['validTo_time_t']) ? gmdate('Y-m-d H:i', (int)$cp['validTo_time_t']) . ' UTC' : '',
                                            'sans' => (string)($cp['extensions']['subjectAltName'] ?? ''),
                                        ];
                                    }
                                }
                                fclose($fp);
                            }
                        }
                    }
                } catch (Throwable $t) {
                    // no TLS (http://) or openssl missing — fine.
                }
                log_activity('tool_headers', $parsed);
            } elseif (ini_get('allow_url_fopen')) {
                $result = probe_via_streams($parsed);
                log_activity('tool_headers', $parsed);
            } else {
                $error = 'curl is not available and stream wrappers are disabled on this host.';
            }
        } else {
            $error = 'Only http:// and https:// URLs are allowed.';
        }
    } else {
        $error = 'That does not look like a valid URL (include https://).';
    }
}

/**
 * curl-less fallback: same shape of result, walking redirects with
 * $http_response_header. Used when curl is missing but allow_url_fopen is on.
 */
function probe_via_streams(string $start): ?array
{
    $ua = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/126.0.0.0 Safari/537.36';
    $headers = ['> ' . $start];
    $hop = $start;
    $lastCode = 0;
    $resp = [];
    for ($i = 0; $i < 6; $i++) {
        $ctx = stream_context_create([
            'http' => ['method' => 'GET', 'timeout' => 8, 'ignore_errors' => true, 'max_redirects' => 0, 'user_agent' => $ua],
            'ssl' => ['verify_peer' => false, 'verify_peer_name' => false],
        ]);
        @file_get_contents($hop, false, $ctx);
        $resp = $http_response_header ?? [];
        $headers[] = '← ' . $hop;
        foreach ($resp as $line) {
            $headers[] = '   ' . $line;
        }
        $code = 0;
        $location = '';
        foreach ($resp as $line) {
            if (preg_match('#^HTTP/\S+\s+(\d+)#i', $line, $m)) {
                $code = (int)$m[1];
            } elseif (stripos($line, 'location:') === 0) {
                $location = trim(substr($line, 9));
            }
        }
        $lastCode = $code;
        if ($code >= 300 && $code < 400 && $location !== '') {
            $location = filter_var($location, FILTER_VALIDATE_URL) ? $location
                : rtrim($hop, '/') . '/' . ltrim($location, '/');
            if (!url_allowed_public($location)) {
                $headers[] = '→ (blocked: non-public redirect target)';
                break;
            }
            $headers[] = '→ ' . $location;
            $hop = $location;
            continue;
        }
        break;
    }
    $contentType = '';
    foreach ($resp as $line) {
        if (stripos($line, 'content-type:') === 0) {
            $contentType = trim(substr($line, 13));
            break;
        }
    }
    return [
        'requested' => $start,
        'final' => $hop,
        'status' => $lastCode,
        'redirects' => count(array_filter($headers, function ($h) { return str_starts_with($h, '→ '); })),
        'content_type' => $contentType,
        'server' => '',
        'headers' => $headers,
    ];
}

page_header('Header Inspector');
?>
<div class="container" style="max-width: 1000px;">
    <h1 class="h4 mb-1 reveal in-view">🧪 HTTP Header &amp; TLS Inspector</h1>
    <p class="text-secondary mb-4 reveal in-view">Probe any URL server-side: response code, full header chain through redirects, and the TLS certificate details if you hit an https endpoint.</p>

    <div class="card mb-4 reveal in-view"><div class="card-body">
        <form method="get" action="index.php" class="row g-2 align-items-center">
            <div class="col-md-8">
                <input class="form-control" name="q" maxlength="512" placeholder="https://example.com/some/path" value="<?= e($url !== '' ? $url : 'https://example.com') ?>">
            </div>
            <div class="col-md-4">
                <button class="btn btn-primary w-100">Inspect</button>
            </div>
        </form>
        <?php if ($error !== null): ?>
            <div class="alert alert-danger small mt-3 mb-0"><?= e($error) ?></div>
        <?php endif; ?>
    </div></div>

    <?php if ($result !== null): ?>
        <div class="row g-3 mb-3">
            <div class="col-md-4">
                <div class="card"><div class="card-body text-center">
                    <div class="text-secondary small">Final status</div>
                    <div style="font-size:2rem;font-weight:700;" class="<?= $result['status'] < 400 ? 'text-success' : 'text-danger' ?>"><?= (int)$result['status'] ?></div>
                    <div class="text-secondary small">redirects: <?= (int)$result['redirects'] ?></div>
                </div></div>
            </div>
            <div class="col-md-8">
                <div class="card"><div class="card-body">
                    <div class="text-secondary small mb-1">Final URL</div>
                    <div class="small" style="word-break:break-all;"><?= e($result['final']) ?></div>
                    <?php if ($result['content_type'] !== ''): ?><div class="text-secondary small mt-2">Content-Type: <code><?= e($result['content_type']) ?></code></div><?php endif; ?>
                </div></div>
            </div>
        </div>

        <?php if (!empty($result['cert'])): ?>
            <div class="card mb-3 reveal">
                <div class="card-body">
                    <h2 class="h6 mb-2">🔒 TLS certificate</h2>
                    <table class="table table-sm table-dark align-middle mb-0">
                        <tr><td class="text-secondary">Subject</td><td><code><?= e($result['cert']['subject']) ?></code></td></tr>
                        <tr><td class="text-secondary">Issuer</td><td><?= e($result['cert']['issuer']) ?></td></tr>
                        <tr><td class="text-secondary">Valid from</td><td><?= e($result['cert']['valid_from']) ?></td></tr>
                        <tr><td class="text-secondary">Valid until</td><td><?= e($result['cert']['valid_to']) ?></td></tr>
                        <?php if (!empty($result['cert']['sans'])): ?><tr><td class="text-secondary">SANs</td><td class="small" style="word-break:break-all;"><?= e($result['cert']['sans']) ?></td></tr><?php endif; ?>
                    </table>
                </div>
            </div>
        <?php endif; ?>

        <div class="card reveal">
            <div class="card-body">
                <h2 class="h6 mb-2">Response chain</h2>
                <pre class="small mb-0" style="white-space:pre-wrap;word-break:break-word;max-height:460px;overflow:auto;"><?= e(implode("\n", $result['headers'])) ?></pre>
            </div>
        </div>
    <?php endif; ?>
</div>
<?php page_footer(); ?>