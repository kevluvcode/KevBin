<?php
require_once __DIR__ . '/../../functions.php';

start_session();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['target'])) {
    header('Content-Type: application/json');

    if (!csrf_verify()) {
        echo json_encode(['error' => 'Invalid CSRF token.']);
        exit;
    }
    if (!rate_limit_check('whois', 5, 60)) {
        echo json_encode(['error' => 'Rate limit reached. Wait a moment.']);
        exit;
    }

    $target = trim($_POST['target']);
    $target = preg_replace('#^(https?://)#', '', $target);
    $target = explode('/', $target)[0];
    $target = explode(':', $target)[0];
    $target = strtolower($target);

    if ($target === '') {
        echo json_encode(['error' => 'Please enter a domain or IP address.']);
        exit;
    }

    $isIPv4 = (bool)filter_var($target, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4);
    $isIPv6 = (bool)filter_var($target, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6);
    $isIP = $isIPv4 || $isIPv6;
    $isDomain = !$isIP && preg_match('/^(?!-)(?:[a-zA-Z0-9-]{1,63}\.)+[a-zA-Z]{2,63}$/', $target);

    if (!$isIP && !$isDomain) {
        echo json_encode(['error' => 'Invalid domain or IP address.']);
        exit;
    }

    log_activity('tool_whois', $target);

    if ($isIP) {
        if ($isIPv6) {
            $server = 'whois.iana.org';
        } else {
            $server = 'whois.arin.net';
        }
    } else {
        $server = 'whois.iana.org';
    }

    $raw = whoisQuery($server, $target);
    if ($raw === null) {
        echo json_encode(['error' => 'Failed to connect to WHOIS server (' . e($server) . '). Try again later.']);
        exit;
    }

    if (trim($raw) === '' || stripos($raw, 'No match') !== false || stripos($raw, 'No Data Found') !== false) {
        echo json_encode(['error' => 'No WHOIS data found for ' . e($target) . '.']);
        exit;
    }

    $parsed = parseWhoisResponse($raw, $target, $isIP);
    $parsed['raw'] = $raw;
    $parsed['server'] = $server;
    $parsed['type'] = $isIP ? 'ip' : 'domain';
    $parsed['target'] = $target;

    echo json_encode($parsed);
    exit;
}

page_header('WHOIS Lookup');
?>
<style>
.whois-result { display: none; }
.whois-result.visible { display: block; }
.whois-stat { display: flex; justify-content: space-between; align-items: flex-start; padding: .45rem 0; border-bottom: 1px solid rgba(255,255,255,.04); }
.whois-stat:last-child { border-bottom: none; }
.whois-label { color: var(--dim); font-size: .82rem; min-width: 140px; flex-shrink: 0; }
.whois-value { font-size: .88rem; font-weight: 500; text-align: right; word-break: break-word; }
.ns-tag { display: inline-block; background: rgba(88,101,242,.12); border: 1px solid rgba(88,101,242,.3); border-radius: 6px; padding: 3px 10px; margin: 3px; font-size: .82rem; font-family: monospace; }
.status-tag { display: inline-flex; align-items: center; gap: 6px; background: rgba(255,193,7,.1); border: 1px solid rgba(255,193,7,.3); border-radius: 6px; padding: 4px 10px; margin: 3px; font-size: .8rem; }
.raw-box { position: relative; }
.raw-box pre { background: rgba(0,0,0,.35); border: 1px solid var(--line); border-radius: 8px; padding: 1rem; max-height: 400px; overflow: auto; font-size: .78rem; white-space: pre-wrap; word-break: break-all; margin: 0; color: var(--dim); }
.copy-raw { position: absolute; top: .5rem; right: .5rem; }
.external-links a { display: inline-flex; align-items: center; gap: 5px; padding: 6px 14px; border-radius: 8px; border: 1px solid var(--line); background: var(--panel-2); color: var(--fg); text-decoration: none; font-size: .82rem; margin-right: 6px; margin-bottom: 6px; transition: .15s; }
.external-links a:hover { border-color: var(--accent1); background: rgba(88,101,242,.08); }
.gdpr-warn { background: rgba(255,193,7,.08); border: 1px solid rgba(255,193,7,.25); border-radius: 8px; padding: .65rem 1rem; font-size: .82rem; color: var(--dim); margin-bottom: 1rem; }
</style>

<div class="container" style="max-width: 1000px;">
    <h1 class="h4 mb-1 reveal in-view">🔍 WHOIS Lookup</h1>
    <p class="text-secondary mb-3 reveal in-view">Query WHOIS registration data for any domain name or IP address. Extracts registrar info, name servers, status codes, registrant details, and more.</p>

    <div class="card reveal in-view"><div class="card-body">
        <form id="whoisForm" class="mb-0">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <div class="row g-2">
                <div class="col-md-8">
                    <input class="form-control" name="target" maxlength="255" placeholder="example.com or 8.8.8.8" required>
                </div>
                <div class="col-md-4">
                    <button class="btn btn-primary w-100" type="submit" id="whoisBtn">
                        <span id="btnLabel">Lookup</span>
                        <span id="btnSpin" class="d-none"><span class="spinner-border spinner-border-sm" role="status"></span></span>
                    </button>
                </div>
            </div>
        </form>
    </div></div>

    <div id="whoisError" class="alert alert-danger mt-4 d-none reveal in-view"></div>

    <div id="whoisResults" class="whois-result mt-4">

        <div class="gdpr-warn reveal in-view">
            <strong>⚠ Privacy Notice:</strong> Personal registrant data (name, email, phone) may be redacted due to GDPR and ICANN policy. This is normal and not an error.
        </div>

        <div class="card mb-3 reveal in-view"><div class="card-body">
            <h2 class="h6 mb-3">📋 Domain Overview</h2>
            <div id="overviewCard"></div>
        </div></div>

        <div class="card mb-3 reveal in-view"><div class="card-body">
            <h2 class="h6 mb-3">🌐 Name Servers</h2>
            <div id="nsCard"></div>
        </div></div>

        <div class="card mb-3 reveal in-view"><div class="card-body">
            <h2 class="h6 mb-3">🔒 Domain Status</h2>
            <div id="statusCard"></div>
        </div></div>

        <div class="card mb-3 reveal in-view"><div class="card-body">
            <h2 class="h6 mb-3">👤 Registrant Info</h2>
            <div id="registrantCard"></div>
        </div></div>

        <div class="card mb-3 reveal in-view"><div class="card-body">
            <h2 class="h6 mb-3">📝 Raw WHOIS Output</h2>
            <div class="external-links mb-3">
                <a href="https://who.is/whois/" target="_blank" rel="noopener">🔗 who.is</a>
                <a href="https://whois.domaintools.com/" target="_blank" rel="noopener">🔗 DomainTools</a>
                <a href="https://lookup.icann.org/en/lookup" target="_blank" rel="noopener">🔗 ICANN</a>
            </div>
            <div class="raw-box">
                <button class="btn btn-sm btn-outline-secondary copy-raw" id="copyRawBtn" type="button">📋 Copy</button>
                <pre id="rawOutput"></pre>
            </div>
        </div></div>
    </div>
</div>

<script>
(function () {
    var form = document.getElementById('whoisForm');
    var btn = document.getElementById('whoisBtn');
    var btnLabel = document.getElementById('btnLabel');
    var btnSpin = document.getElementById('btnSpin');
    var errBox = document.getElementById('whoisError');
    var results = document.getElementById('whoisResults');

    form.addEventListener('submit', function (e) {
        e.preventDefault();
        errBox.classList.add('d-none');
        results.classList.remove('visible');
        btn.disabled = true;
        btnLabel.textContent = 'Looking up...';
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
        btnLabel.textContent = 'Lookup';
        btnSpin.classList.add('d-none');
    }

    function renderResults(d) {
        renderOverview(d);
        renderNS(d);
        renderStatus(d);
        renderRegistrant(d);
        renderRaw(d);
    }

    function renderOverview(d) {
        var rows = [];
        if (d.type === 'domain') {
            rows.push(['Domain Name', d.domain_name || d.target]);
            rows.push(['Registrar', d.registrar || '---']);
            rows.push(['Registration Date', d.creation_date || '---']);
            rows.push(['Expiration Date', d.expiration_date || '---']);
            rows.push(['Updated Date', d.updated_date || '---']);
            rows.push(['WHOIS Server', d.server || '---']);
            rows.push(['DNSSEC', d.dnssec || '---']);
        } else {
            rows.push(['IP Address', d.target]);
            rows.push(['Network Range', d.network_range || '---']);
            rows.push(['CIDR', d.cidr || '---']);
            rows.push(['Organization', d.org_name || '---']);
            rows.push(['Country', d.country || '---']);
            rows.push(['Registration Date', d.registration_date || '---']);
            rows.push(['WHOIS Server', d.server || '---']);
        }
        var html = '';
        rows.forEach(function (r) {
            html += '<div class="whois-stat"><span class="whois-label">' + r[0] + '</span><span class="whois-value">' + esc(r[1]) + '</span></div>';
        });
        document.getElementById('overviewCard').innerHTML = html;
    }

    function renderNS(d) {
        var ns = d.name_servers || [];
        if (ns.length === 0) {
            document.getElementById('nsCard').innerHTML = '<div class="text-secondary small">No name servers found.</div>';
            return;
        }
        var html = '<div>' + ns.length + ' name server' + (ns.length === 1 ? '' : 's') + ' found</div><div class="mt-2">';
        ns.forEach(function (n) {
            html += '<span class="ns-tag">' + esc(n) + '</span>';
        });
        html += '</div>';
        document.getElementById('nsCard').innerHTML = html;
    }

    function renderStatus(d) {
        var statuses = d.statuses || [];
        if (statuses.length === 0) {
            document.getElementById('statusCard').innerHTML = '<div class="text-secondary small">No status codes found.</div>';
            return;
        }
        var html = '';
        statuses.forEach(function (s) {
            html += '<div class="status-tag"><strong>' + esc(s.code) + '</strong><span class="text-secondary">' + esc(s.explanation) + '</span></div>';
        });
        document.getElementById('statusCard').innerHTML = html;
    }

    function renderRegistrant(d) {
        var info = d.registrant || {};
        var rows = [];
        rows.push(['Organization', info.organization || '---']);
        rows.push(['Country', info.country || '---']);
        rows.push(['State/Province', info.state || '---']);
        rows.push(['DNSSEC', info.dnssec || d.dnssec || '---']);
        var html = '';
        rows.forEach(function (r) {
            html += '<div class="whois-stat"><span class="whois-label">' + r[0] + '</span><span class="whois-value">' + esc(r[1]) + '</span></div>';
        });
        html += '<div class="mt-2 text-secondary small">Personal data (name, email, phone) redacted for privacy.</div>';
        document.getElementById('registrantCard').innerHTML = html;
    }

    function renderRaw(d) {
        var raw = d.raw || '';
        document.getElementById('rawOutput').textContent = raw;
        document.getElementById('copyRawBtn').onclick = function () {
            navigator.clipboard.writeText(raw).then(function () {
                document.getElementById('copyRawBtn').textContent = '✅ Copied!';
                setTimeout(function () {
                    document.getElementById('copyRawBtn').textContent = '📋 Copy';
                }, 2000);
            });
        };
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
function whoisQuery(string $server, string $target): ?string {
    $errno = 0;
    $errstr = '';
    $fp = @fsockopen($server, 43, $errno, $errstr, 8);
    if (!is_resource($fp)) {
        return null;
    }
    stream_set_timeout($fp, 8);
    fwrite($fp, $target . "\r\n");
    $response = '';
    while (!feof($fp)) {
        $chunk = fread($fp, 8192);
        if ($chunk === false || $chunk === '') {
            break;
        }
        $response .= $chunk;
    }
    fclose($fp);
    return $response;
}

function parseWhoisResponse(string $raw, string $target, bool $isIP): array {
    $result = [
        'domain_name' => '',
        'registrar' => '',
        'creation_date' => '',
        'expiration_date' => '',
        'updated_date' => '',
        'name_servers' => [],
        'statuses' => [],
        'registrant' => [
            'organization' => '',
            'country' => '',
            'state' => '',
        ],
        'dnssec' => '',
        'network_range' => '',
        'cidr' => '',
        'org_name' => '',
        'country' => '',
        'registration_date' => '',
    ];

    $lines = explode("\n", $raw);
    $fieldMap = [];

    foreach ($lines as $line) {
        $line = rtrim($line, "\r\n");
        if ($line === '' || $line[0] === '%') {
            continue;
        }
        if (preg_match('/^([^:]+):\s*(.*)$/', $line, $m)) {
            $key = strtolower(trim($m[1]));
            $val = trim($m[2]);
            if (!isset($fieldMap[$key])) {
                $fieldMap[$key] = [];
            }
            $fieldMap[$key][] = $val;
        }
    }

    if ($isIP) {
        $result['network_range'] = firstVal($fieldMap, ['network range', 'netrange', 'route']);
        $result['cidr'] = firstVal($fieldMap, ['cidr', 'route']);
        $result['org_name'] = firstVal($fieldMap, ['orgname', 'org-name', 'organization', 'netname']);
        $result['country'] = firstVal($fieldMap, ['country', 'netcountry']);
        $result['registration_date'] = firstVal($fieldMap, ['registration date', 'created', 'regdate', 'date']);
        return $result;
    }

    $result['domain_name'] = firstVal($fieldMap, ['domain name', 'domain']);
    if ($result['domain_name'] === '' && $target !== '') {
        $result['domain_name'] = $target;
    }
    $result['registrar'] = firstVal($fieldMap, ['registrar', 'sponsoring registrar']);
    $result['creation_date'] = firstVal($fieldMap, ['creation date', 'created', 'created date', 'registration date']);
    $result['expiration_date'] = firstVal($fieldMap, ['expiration date', 'registry expiry date', 'expiry date', 'expires', 'paid-till', 'valid date']);
    $result['updated_date'] = firstVal($fieldMap, ['updated date', 'last updated', 'last modified', 'last-update of whois']);

    $nsRaw = $fieldMap['name server'] ?? $fieldMap['nserver'] ?? $fieldMap['nameserver'] ?? [];
    $result['name_servers'] = array_values(array_unique(array_map(function ($ns) {
        return strtolower(trim($ns, '. '));
    }, array_filter($nsRaw, function ($v) {
        return $v !== '' && stripos($v, 'redacted') === false;
    }))));

    $statusRaw = $fieldMap['domain status'] ?? $fieldMap['status'] ?? [];
    $statusExplanations = [
        'clienttransferprohibited' => 'Domain cannot be transferred without authorization from the registrant.',
        'clientupdateprohibited' => 'Domain cannot be updated without authorization from the registrant.',
        'clientdeleteprohibited' => 'Domain cannot be deleted without authorization from the registrant.',
        'clienthold' => 'Domain is suspended by the registrar.',
        'clientrenewprohibited' => 'Domain cannot be renewed without authorization from the registrant.',
        'servertransferprohibited' => 'Domain cannot be transferred. Server-level lock.',
        'serverupdateprohibited' => 'Domain cannot be updated. Server-level lock.',
        'serverdeleteprohibited' => 'Domain cannot be deleted. Server-level lock.',
        'serverhold' => 'Domain is suspended by the registry.',
        'autorenewperiod' => 'Domain is in the auto-renew grace period.',
        'addperiod' => 'Domain is in the add grace period after initial registration.',
        'pendingdelete' => 'Domain is pending deletion from the registry.',
        'pendingtransfer' => 'Domain is being transferred.',
        'pendingupdate' => 'Domain update is pending.',
        'redemptionperiod' => 'Domain is in the redemption period and can be restored.',
        'ok' => 'Domain is active and in good standing.',
        'inactive' => 'Domain is not activated in DNS.',
        'pendingrestore' => 'Domain restoration is pending.',
        'registry-lock' => 'Domain is locked at the registry level.',
        'serverregistrarlock' => 'Registrar lock applied at the server level.',
    ];

    foreach ($statusRaw as $s) {
        $s = trim($s);
        if ($s === '' || stripos($s, 'redacted') !== false) {
            continue;
        }
        $parts = explode(' ', $s);
        $code = strtolower(trim($parts[0], '[]. '));
        $explanation = $statusExplanations[$code] ?? 'Status code active on this domain.';
        $result['statuses'][] = [
            'code' => $parts[0],
            'explanation' => $explanation,
        ];
    }

    $result['registrant']['organization'] = firstVal($fieldMap, ['registrant organization', 'registrant org', 'org', 'organization']);
    $result['registrant']['country'] = firstVal($fieldMap, ['registrant country', 'registrant c', 'country']);
    $result['registrant']['state'] = firstVal($fieldMap, ['registrant state/province', 'registrant st', 'registrant state', 'state', 'state/province']);

    $privNames = ['redacted for privacy', 'redacted', 'privacy protect', 'whoisguard', 'domains by proxy', 'contactprivacy', 'withheld for privacy', 'data protected', 'not disclosed', 'identity protect'];
    foreach ($result['registrant'] as $k => $v) {
        if ($v !== '') {
            $vl = strtolower($v);
            foreach ($privNames as $pn) {
                if (strpos($vl, $pn) !== false) {
                    $result['registrant'][$k] = '[Redacted for Privacy]';
                    break;
                }
            }
        }
    }

    $result['dnssec'] = firstVal($fieldMap, ['dnssec', 'dnssec: signed delegation', 'dnssec: unsigned delegation']);
    if ($result['dnssec'] === '') {
        if (stripos($raw, 'signedDelegation') !== false || stripos($raw, 'signed delegation') !== false) {
            $result['dnssec'] = 'Signed Delegation';
        } elseif (stripos($raw, 'unsignedDelegation') !== false || stripos($raw, 'unsigned delegation') !== false) {
            $result['dnssec'] = 'Unsigned Delegation';
        }
    }

    return $result;
}

function firstVal(array $map, array $keys): string {
    foreach ($keys as $k) {
        if (isset($map[$k]) && is_array($map[$k]) && count($map[$k]) > 0) {
            return $map[$k][0];
        }
    }
    return '';
}