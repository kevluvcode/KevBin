<?php
require_once __DIR__ . '/../../functions.php';

start_session();

$domain = trim((string)($_POST['domain'] ?? ''));

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $domain !== '') {
    header('Content-Type: application/json');

    if (!csrf_verify()) {
        echo json_encode(['error' => 'Invalid CSRF token.']);
        exit;
    }
    if (!rate_limit_check('sslcert', 3, (int)($GLOBALS['CFG']['rate_window_seconds'] ?? 120))) {
        echo json_encode(['error' => 'Rate limit reached. Wait a moment.']);
        exit;
    }

    $domain = strtolower(trim($domain, '/: '));
    $domain = preg_replace('#^(https?://)#', '', $domain);
    $domain = explode('/', $domain)[0];
    $domain = explode(':', $domain)[0];

    if (!preg_match('/^(?!-)(?:[a-zA-Z0-9-]{1,63}\.){1,}[a-zA-Z]{2,63}$/', $domain)) {
        echo json_encode(['error' => 'Invalid domain name.']);
        exit;
    }

    $ip = @gethostbynamel($domain);
    if (!is_array($ip) || count($ip) === 0) {
        echo json_encode(['error' => 'Could not resolve domain.']);
        exit;
    }

    log_activity('tool_sslcert', $domain);

    $result = fetch_ssl_data($domain);
    echo json_encode($result);
    exit;
}

page_header('SSL Certificate Analyzer');

$cfg = $GLOBALS['CFG'];
?>
<style>
    .cert-result { display: none; }
    .cert-result.visible { display: block; }
    .chain-line { width: 3px; background: linear-gradient(180deg, var(--accent1), var(--accent2)); margin: 0 auto; height: 20px; }
    .chain-node { position: relative; border: 1px solid var(--line); border-radius: 12px; padding: 1rem 1.15rem; background: var(--panel-2); }
    .chain-node.root { border-color: rgba(255,193,7,.4); }
    .chain-node.intermediate { border-color: rgba(88,101,242,.4); }
    .chain-node.leaf { border-color: rgba(38,208,124,.4); }
    .chain-badge { font-size: .7rem; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; margin-bottom: .4rem; }
    .stat-row { display: flex; justify-content: space-between; align-items: center; padding: .35rem 0; border-bottom: 1px solid rgba(255,255,255,.04); }
    .stat-row:last-child { border-bottom: none; }
    .stat-label { color: var(--dim); font-size: .82rem; }
    .stat-value { font-size: .88rem; font-weight: 500; text-align: right; }
    .sans-tag { display: inline-block; background: rgba(88,101,242,.15); border: 1px solid rgba(88,101,242,.3); border-radius: 6px; padding: 2px 8px; margin: 2px; font-size: .8rem; }
    .sans-tag.wildcard { background: rgba(255,193,7,.12); border-color: rgba(255,193,7,.35); }
    .days-safe { color: #26d07c; }
    .days-warn { color: #ffc107; }
    .days-danger { color: #e74c3c; }
    .status-badge { display: inline-flex; align-items: center; gap: 5px; font-size: .82rem; font-weight: 600; }
    .status-dot { width: 8px; height: 8px; border-radius: 50%; display: inline-block; }
    .spinner-border-sm { width: 1rem; height: 1rem; border-width: .15em; }
</style>

<div class="container" style="max-width: 960px;">
    <h1 class="h4 mb-1 reveal in-view">&#128274; SSL Certificate Analyzer</h1>
    <p class="text-secondary mb-3 reveal in-view">Connects to any domain on port 443 and inspects its TLS certificate: validity, issuer, chain, SANs, key strength, TLS version, and more.</p>

    <div class="card reveal in-view"><div class="card-body">
        <form id="sslForm" class="mb-0">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <div class="row g-2">
                <div class="col-md-8">
                    <input class="form-control" name="domain" maxlength="253" placeholder="example.com" required>
                </div>
                <div class="col-md-4">
                    <button class="btn btn-primary w-100" type="submit" id="sslBtn">
                        <span id="btnLabel">Analyze</span>
                        <span id="btnSpin" class="d-none"><span class="spinner-border spinner-border-sm" role="status"></span></span>
                    </button>
                </div>
            </div>
        </form>
    </div></div>

    <div id="sslError" class="alert alert-danger mt-4 d-none reveal in-view"></div>

    <div id="sslResults" class="cert-result mt-4">
        <div class="row g-3 mb-3">
            <div class="col-md-4">
                <div class="card reveal in-view"><div class="card-body text-center">
                    <div class="text-secondary small">Days until expiry</div>
                    <div id="daysLeft" style="font-size:2.2rem;font-weight:700;"></div>
                    <div id="expiryDate" class="text-secondary small"></div>
                </div></div>
            </div>
            <div class="col-md-4">
                <div class="card reveal in-view"><div class="card-body text-center">
                    <div class="text-secondary small">Certificate Type</div>
                    <div id="certType" style="font-size:1.6rem;font-weight:700;"></div>
                    <div id="certIssuer" class="text-secondary small"></div>
                </div></div>
            </div>
            <div class="col-md-4">
                <div class="card reveal in-view"><div class="card-body text-center">
                    <div class="text-secondary small">Key Strength</div>
                    <div id="keyInfo" style="font-size:1.6rem;font-weight:700;"></div>
                    <div id="sigAlgo" class="text-secondary small"></div>
                </div></div>
            </div>
        </div>

        <div class="card mb-3 reveal in-view"><div class="card-body">
            <h2 class="h6 mb-3">&#128196; Certificate Overview</h2>
            <div id="overviewStats"></div>
        </div></div>

        <div class="card mb-3 reveal in-view"><div class="card-body">
            <h2 class="h6 mb-3">&#127760; Domain Coverage</h2>
            <div id="domainCoverage"></div>
        </div></div>

        <div class="card mb-3 reveal in-view"><div class="card-body">
            <h2 class="h6 mb-3">&#128272; Security Details</h2>
            <div id="securityDetails"></div>
        </div></div>

        <div class="card mb-3 reveal in-view"><div class="card-body">
            <h2 class="h6 mb-3">&#128279; Certificate Chain</h2>
            <div id="certChain"></div>
        </div></div>

        <div class="card mb-3 reveal in-view"><div class="card-body">
            <h2 class="h6 mb-3">&#128268; Connection Details</h2>
            <div id="connDetails"></div>
        </div></div>
    </div>
</div>

<script>
(function () {
    var form = document.getElementById('sslForm');
    var btn = document.getElementById('sslBtn');
    var btnLabel = document.getElementById('btnLabel');
    var btnSpin = document.getElementById('btnSpin');
    var errBox = document.getElementById('sslError');
    var results = document.getElementById('sslResults');

    form.addEventListener('submit', function (e) {
        e.preventDefault();
        errBox.classList.add('d-none');
        results.classList.remove('visible');
        btn.disabled = true;
        btnLabel.textContent = 'Analyzing...';
        btnSpin.classList.remove('d-none');

        var fd = new FormData(form);
        fetch('index.php', { method: 'POST', body: fd })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                if (d.error) {
                    errBox.textContent = d.error;
                    errBox.classList.remove('d-none');
                    resetBtn();
                    return;
                }
                renderResults(d);
                results.classList.add('visible');
                resetBtn();
                results.querySelectorAll('.reveal').forEach(function (el) {
                    el.classList.add('in-view');
                });
            })
            .catch(function () {
                errBox.textContent = 'Network error. Try again.';
                errBox.classList.remove('d-none');
                resetBtn();
            });
    });

    function resetBtn() {
        btn.disabled = false;
        btnLabel.textContent = 'Analyze';
        btnSpin.classList.add('d-none');
    }

    function renderResults(d) {
        var daysEl = document.getElementById('daysLeft');
        var expEl = document.getElementById('expiryDate');
        var ctEl = document.getElementById('certType');
        var ciEl = document.getElementById('certIssuer');
        var kiEl = document.getElementById('keyInfo');
        var saEl = document.getElementById('sigAlgo');

        var days = d.days_until_expiry;
        daysEl.textContent = days;
        daysEl.className = 'days-' + (days > 30 ? 'safe' : days >= 7 ? 'warn' : 'danger');
        expEl.textContent = 'Expires: ' + d.valid_to;

        ctEl.textContent = d.cert_type;
        ciEl.textContent = 'Issuer: ' + d.issuer_cn;

        kiEl.textContent = d.key_type + ' ' + d.key_bits + ' bit';
        kiEl.className = (d.key_type === 'RSA' && d.key_bits < 2048) ? 'days-danger' : 'days-safe';
        saEl.textContent = d.signature_algorithm;

        renderOverview(d);
        renderDomainCoverage(d);
        renderSecurityDetails(d);
        renderChain(d);
        renderConnection(d);
    }

    function renderOverview(d) {
        var rows = [
            ['Common Name', d.subject_cn],
            ['Organization', d.subject_o || '---'],
            ['Org Unit', d.subject_ou || '---'],
            ['Country', d.subject_c || '---'],
            ['State', d.subject_st || '---'],
            ['Locality', d.subject_l || '---'],
            ['Serial Number', d.serial],
            ['Version', d.version],
            ['Valid From', d.valid_from],
            ['Valid To', d.valid_to],
            ['Days Remaining', '<span class="days-' + (d.days_until_expiry > 30 ? 'safe' : d.days_until_expiry >= 7 ? 'warn' : 'danger') + '">' + d.days_until_expiry + ' days</span>'],
            ['Certificate Type', d.cert_type],
        ];
        var html = '';
        rows.forEach(function (r) {
            html += '<div class="stat-row"><span class="stat-label">' + r[0] + '</span><span class="stat-value">' + r[1] + '</span></div>';
        });
        document.getElementById('overviewStats').innerHTML = html;
    }

    function renderDomainCoverage(d) {
        var html = '<div class="stat-row"><span class="stat-label">Common Name</span><span class="stat-value"><code>' + esc(d.subject_cn) + '</code></span></div>';
        var sans = d.sans || [];
        if (sans.length > 0) {
            html += '<div class="mt-2 mb-1"><span class="stat-label">Subject Alternative Names (' + sans.length + ')</span></div><div>';
            sans.forEach(function (s) {
                var wc = s.indexOf('*.') === 0;
                html += '<span class="sans-tag' + (wc ? ' wildcard' : '') + '">' + (wc ? '&#127760; ' : '') + esc(s) + '</span>';
            });
            html += '</div>';
        }
        var covers = d.covers_domain;
        var icon = covers ? '&#9989;' : '&#10060;';
        var cls = covers ? 'text-success' : 'text-danger';
        var label = covers ? 'Certificate covers ' + esc(d.domain) : 'Certificate does NOT cover ' + esc(d.domain);
        html += '<div class="mt-3 status-badge ' + cls + '">' + icon + ' ' + label + '</div>';
        if (d.wildcard_count > 0) {
            html += '<div class="mt-2 text-secondary small">&#9733; Includes ' + d.wildcard_count + ' wildcard ' + (d.wildcard_count === 1 ? 'domain' : 'domains') + '</div>';
        }
        document.getElementById('domainCoverage').innerHTML = html;
    }

    function renderSecurityDetails(d) {
        var keyOk = !(d.key_type === 'RSA' && d.key_bits < 2048);
        var rows = [
            ['Key Algorithm', d.key_type],
            ['Key Size', '<span class="' + (keyOk ? 'days-safe' : 'days-danger') + '">' + d.key_bits + ' bits</span>'],
            ['Key Strength', keyOk ? '<span class="days-safe">&#9989; Strong</span>' : '<span class="days-danger">&#9888; Weak &mdash; upgrade to 2048+</span>'],
            ['Signature Algorithm', d.signature_algorithm],
            ['OCSP Stapling', d.ocsp_stapling ? '<span class="days-safe">&#9989; Supported</span>' : '<span class="text-secondary">&#10060; Not detected</span>'],
            ['Certificate Chain Depth', d.chain_depth],
        ];
        var html = '';
        rows.forEach(function (r) {
            html += '<div class="stat-row"><span class="stat-label">' + r[0] + '</span><span class="stat-value">' + r[1] + '</span></div>';
        });
        document.getElementById('securityDetails').innerHTML = html;
    }

    function renderChain(d) {
        var chain = d.chain || [];
        if (chain.length === 0) {
            document.getElementById('certChain').innerHTML = '<div class="text-secondary small">No chain data available.</div>';
            return;
        }
        var html = '';
        chain.forEach(function (c, i) {
            var role = i === 0 ? 'leaf' : (i === chain.length - 1 ? 'root' : 'intermediate');
            var badgeColor = role === 'root' ? 'text-warning' : (role === 'intermediate' ? 'text-primary' : 'text-success');
            var expired = c.expired;
            var selfSigned = c.self_signed;
            var alertHtml = '';
            if (expired) alertHtml += ' <span class="days-danger" style="font-size:.75rem;">&#9888; EXPIRED</span>';
            if (selfSigned) alertHtml += ' <span class="text-warning" style="font-size:.75rem;">&#9733; Self-signed</span>';

            if (i > 0) html += '<div class="chain-line"></div>';
            html += '<div class="chain-node ' + role + '">';
            html += '<div class="chain-badge ' + badgeColor + '">' + role + alertHtml + '</div>';
            html += '<div class="stat-row"><span class="stat-label">Subject</span><span class="stat-value" style="font-size:.8rem;">' + esc(c.subject) + '</span></div>';
            html += '<div class="stat-row"><span class="stat-label">Issuer</span><span class="stat-value" style="font-size:.8rem;">' + esc(c.issuer) + '</span></div>';
            html += '<div class="stat-row"><span class="stat-label">Valid</span><span class="stat-value" style="font-size:.8rem;">' + esc(c.valid_from) + ' &rarr; ' + esc(c.valid_to) + '</span></div>';
            html += '</div>';
        });
        document.getElementById('certChain').innerHTML = html;
    }

    function renderConnection(d) {
        var rows = [
            ['TLS Version', '<span class="days-safe">' + esc(d.tls_version) + '</span>'],
            ['HTTP/2 Support', d.http2 ? '<span class="days-safe">&#9989; Yes</span>' : '<span class="text-secondary">&#10060; No / Not detected</span>'],
            ['HSTS Header', d.hsts ? '<span class="days-safe">' + esc(d.hsts) + '</span>' : '<span class="text-secondary">Not set</span>'],
            ['Certificate Transparency', d.ct ? '<span class="days-safe">&#9989; CT logs detected</span>' : '<span class="text-secondary">&#10060; No CT extensions found</span>'],
        ];
        var html = '';
        rows.forEach(function (r) {
            html += '<div class="stat-row"><span class="stat-label">' + r[0] + '</span><span class="stat-value">' + r[1] + '</span></div>';
        });
        document.getElementById('connDetails').innerHTML = html;
    }

    function esc(s) {
        var d = document.createElement('div');
        d.appendChild(document.createTextNode(s || ''));
        return d.innerHTML;
    }
})();
</script>
<?php page_footer(); ?>

<?php
function fetch_ssl_data(string $domain): array {
    $result = [
        'domain' => $domain,
        'error' => null,
        'subject_cn' => '', 'subject_o' => '', 'subject_ou' => '', 'subject_c' => '',
        'subject_st' => '', 'subject_l' => '',
        'issuer_cn' => '', 'issuer_o' => '', 'issuer_c' => '',
        'serial' => '', 'version' => '',
        'valid_from' => '', 'valid_to' => '', 'days_until_expiry' => 0,
        'cert_type' => 'DV',
        'sans' => [], 'covers_domain' => false, 'wildcard_count' => 0,
        'key_type' => '', 'key_bits' => 0,
        'signature_algorithm' => '',
        'ocsp_stapling' => false,
        'chain_depth' => 0,
        'chain' => [],
        'tls_version' => '',
        'http2' => false,
        'hsts' => '',
        'ct' => false,
    ];

    try {
        $ctx = stream_context_create([
            'ssl' => [
                'capture_peer_cert' => true,
                'capture_peer_cert_chain' => true,
                'verify_peer' => false,
                'verify_peer_name' => false,
            ],
        ]);

        $stream = @stream_socket_client(
            'ssl://' . $domain . ':443',
            $errno,
            $errstr,
            8,
            STREAM_CLIENT_CONNECT,
            $ctx
        );

        if (!is_resource($stream)) {
            $result['error'] = 'Could not connect to ' . e($domain) . ' on port 443: ' . e($errstr);
            return $result;
        }

        $params = stream_context_get_params($ctx);
        $cert = $params['options']['ssl']['peer_certificate'] ?? null;

        if (!$cert) {
            $result['error'] = 'No certificate returned from ' . e($domain) . '.';
            fclose($stream);
            return $result;
        }

        $certData = openssl_x509_parse($cert);
        if (!is_array($certData)) {
            $result['error'] = 'Could not parse certificate data.';
            fclose($stream);
            return $result;
        }

        $subject = $certData['subject'] ?? [];
        $result['subject_cn'] = $subject['CN'] ?? '';
        $result['subject_o'] = $subject['O'] ?? '';
        $result['subject_ou'] = $subject['OU'] ?? '';
        $result['subject_c'] = $subject['C'] ?? '';
        $result['subject_st'] = $subject['ST'] ?? '';
        $result['subject_l'] = $subject['L'] ?? '';

        $issuer = $certData['issuer'] ?? [];
        $result['issuer_cn'] = $issuer['CN'] ?? '';
        $result['issuer_o'] = $issuer['O'] ?? '';
        $result['issuer_c'] = $issuer['C'] ?? '';

        $result['serial'] = $certData['serialNumberHex'] ?? ($certData['serialNumber'] ?? '');
        $result['version'] = 'v' . ($certData['version'] ?? 3);

        if (isset($certData['validFrom_time_t'])) {
            $result['valid_from'] = gmdate('Y-m-d H:i:s', (int)$certData['validFrom_time_t']) . ' UTC';
        }
        if (isset($certData['validTo_time_t'])) {
            $result['valid_to'] = gmdate('Y-m-d H:i:s', (int)$certData['validTo_time_t']) . ' UTC';
            $diff = (int)$certData['validTo_time_t'] - time();
            $result['days_until_expiry'] = (int)ceil($diff / 86400);
        }

        $issuerCn = strtolower($result['issuer_cn']);
        $issuerO = strtolower($result['issuer_o']);
        if (strpos($issuerCn, 'let\'s encrypt') !== false || strpos($issuerCn, 'zeroSSL') !== false || strpos($issuerCn, 'buypass') !== false || strpos($issuerCn, 'google trust') !== false) {
            $result['cert_type'] = 'DV';
        } elseif (strpos($issuerCn, 'digi cert') !== false || strpos($issuerO, 'digi cert') !== false || strpos($issuerCn, 'sectigo') !== false || strpos($issuerCn, 'comodo') !== false || strpos($issuerCn, 'globalsign') !== false) {
            $result['cert_type'] = 'OV/EV';
        } else {
            $result['cert_type'] = 'DV';
        }

        $sanStr = $certData['extensions']['subjectAltName'] ?? '';
        $sans = [];
        if ($sanStr !== '') {
            $sanParts = explode(',', $sanStr);
            foreach ($sanParts as $sp) {
                $sp = trim($sp);
                $sp = preg_replace('#^DNS:#i', '', $sp);
                if ($sp !== '') $sans[] = $sp;
            }
        }
        $result['sans'] = $sans;
        $result['wildcard_count'] = count(array_filter($sans, function ($s) { return strpos($s, '*.') === 0; }));
        $inputNorm = strtolower($domain);
        $covers = false;
        foreach ($sans as $s) {
            $sn = strtolower($s);
            if ($sn === $inputNorm) {
                $covers = true;
                break;
            }
            if (strpos($sn, '*.') === 0) {
                $wild = substr($sn, 2);
                $parts = explode('.', $inputNorm);
                array_shift($parts);
                if (implode('.', $parts) === $wild) {
                    $covers = true;
                    break;
                }
            }
        }
        $result['covers_domain'] = $covers;

        $pubKey = openssl_pkey_get_public($cert);
        if ($pubKey) {
            $details = openssl_pkey_get_details($pubKey);
            if (is_array($details)) {
                $result['key_type'] = $details['type'] === OPENSSL_KEYTYPE_RSA ? 'RSA' : 'ECDSA';
                $result['key_bits'] = $details['bits'] ?? 0;
            }
            openssl_pkey_free($pubKey);
        }

        $result['signature_algorithm'] = $certData['signatureTypeSN'] ?? ($certData['signatureTypeLN'] ?? 'Unknown');

        $chainCerts = $params['options']['ssl']['peer_certificate_chain'] ?? [];
        $chain = [];
        if (is_array($chainCerts)) {
            foreach ($chainCerts as $cc) {
                $cd = openssl_x509_parse($cc);
                if (is_array($cd)) {
                    $cn = $cd['subject']['CN'] ?? ($cd['subject']['O'] ?? 'Unknown');
                    $ci = $cd['issuer']['CN'] ?? ($cd['issuer']['O'] ?? 'Unknown');
                    $vf = isset($cd['validFrom_time_t']) ? gmdate('Y-m-d H:i', (int)$cd['validFrom_time_t']) : '?';
                    $vt = isset($cd['validTo_time_t']) ? gmdate('Y-m-d H:i', (int)$cd['validTo_time_t']) : '?';
                    $exp = isset($cd['validTo_time_t']) && (int)$cd['validTo_time_t'] < time();
                    $selfSigned = (strtolower($cn) === strtolower($ci));
                    $chain[] = [
                        'subject' => $cn,
                        'issuer' => $ci,
                        'valid_from' => $vf,
                        'valid_to' => $vt,
                        'expired' => $exp,
                        'self_signed' => $selfSigned,
                    ];
                }
            }
        }
        $certCn = $result['subject_cn'];
        $certIssuerCn = $result['issuer_cn'];
        $foundLeaf = false;
        foreach ($chain as $idx => $ch) {
            if (strtolower($ch['subject']) === strtolower($certCn)) {
                $foundLeaf = true;
                break;
            }
        }
        if (!$foundLeaf) {
            $leafExpired = isset($certData['validTo_time_t']) && (int)$certData['validTo_time_t'] < time();
            $leafSelf = (strtolower($certCn) === strtolower($certIssuerCn));
            array_unshift($chain, [
                'subject' => $certCn,
                'issuer' => $certIssuerCn,
                'valid_from' => isset($certData['validFrom_time_t']) ? gmdate('Y-m-d H:i', (int)$certData['validFrom_time_t']) : '?',
                'valid_to' => isset($certData['validTo_time_t']) ? gmdate('Y-m-d H:i', (int)$certData['validTo_time_t']) : '?',
                'expired' => $leafExpired,
                'self_signed' => $leafSelf,
            ]);
        }
        $result['chain'] = $chain;
        $result['chain_depth'] = count($chain);
        $result['ocsp_stapling'] = !empty($certData['extensions']['authorityInfoAccess']) && stripos($certData['extensions']['authorityInfoAccess'], 'OCSP') !== false;

        $result['ct'] = false;
        if (!empty($certData['extensions'])) {
            foreach ($certData['extensions'] as $k => $v) {
                if (stripos($k, 'ct') !== false || stripos((string)$v, 'CT') !== false || stripos((string)$v, 'certificate transparency') !== false) {
                    $result['ct'] = true;
                    break;
                }
            }
        }
        if (!$result['ct'] && !empty($certData['extensions']['1.3.6.1.4.1.11129.2.4.2'])) {
            $result['ct'] = true;
        }
        if (!$result['ct'] && !empty($certData['extensions']['1.3.6.1.4.1.11129.2.4.3'])) {
            $result['ct'] = true;
        }

        $tlsOptions = stream_context_get_options($ctx);
        $result['tls_version'] = isset($tlsOptions['ssl']) ? ($tlsOptions['ssl']['crypto_method'] ?? '') : '';
        if (function_exists('stream_socket_get_peer_certificate')) {
            $peerCert = stream_socket_get_peer_certificate($stream);
            if (is_array($peerCert) && !empty($peerCert['protocol'])) {
                $result['tls_version'] = $peerCert['protocol'];
            }
        }
        if ($result['tls_version'] === '') {
            $meta = @stream_get_meta_data($stream);
            if (is_array($meta) && !empty($meta['crypto'])) {
                $result['tls_version'] = $meta['crypto']['protocol'] ?? 'Unknown';
            }
        }
        if ($result['tls_version'] === '' || $result['tls_version'] === 'Unknown') {
            $result['tls_version'] = 'TLS (version unknown)';
        }

        fclose($stream);

        $result['http2'] = check_http2($domain);
        $result['hsts'] = check_hsts($domain);

    } catch (Throwable $t) {
        $result['error'] = 'Analysis failed: ' . $t->getMessage();
    }

    return $result;
}

function check_http2(string $domain): bool {
    if (!function_exists('curl_init')) {
        return false;
    }
    $ch = curl_init('https://' . $domain . '/');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 5,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_NOBODY => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => 0,
        CURLOPT_USERAGENT => 'SSL-Analyzer/1.0',
    ]);
    curl_exec($ch);
    $version = curl_getinfo($ch, CURLINFO_HTTP_VERSION);
    curl_close($ch);
    return (int)$version === 3;
}

function check_hsts(string $domain): string {
    $ctx = stream_context_create([
        'http' => ['method' => 'GET', 'timeout' => 5, 'header' => "Host: {$domain}\r\nUser-Agent: SSL-Analyzer/1.0\r\n", 'ignore_errors' => true, 'follow_location' => false],
        'ssl' => ['verify_peer' => false, 'verify_peer_name' => false],
    ]);
    @file_get_contents('https://' . $domain . '/', false, $ctx);
    $headers = $http_response_header ?? [];
    foreach ($headers as $h) {
        if (stripos($h, 'strict-transport-security:') === 0) {
            return trim(substr($h, 25));
        }
    }
    return '';
}
