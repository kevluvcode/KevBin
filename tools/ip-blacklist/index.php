<?php
require_once __DIR__ . '/../../functions.php';

start_session();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ip = trim((string)($_POST['ip'] ?? ''));
    $cfg = $GLOBALS['CFG'];

    if (!csrf_verify()) {
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Invalid CSRF token. Reload and try again.']);
        exit;
    }
    if (!rate_limit_check('ipblacklist', 5, 60)) {
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Rate limit reached — max 5 checks per 60 seconds.']);
        exit;
    }
    if (!filter_var($ip, FILTER_VALIDATE_IP)) {
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Invalid IP address format.']);
        exit;
    }

    log_activity('tool_ipblacklist', $ip);

    $octets = explode('.', $ip);
    $reversed = implode('.', array_reverse($octets));

    $dnsbls = [
        'dnsbl-1.uceprotect.net',
        'dnsbl-2.uceprotect.net',
        'dnsbl-3.uceprotect.net',
        'zen.spamhaus.org',
        'bl.spamcop.net',
        'dnsbl.sorbs.net',
        'spam.dnsbl.sorbs.net',
        'dnsbl.dronebl.org',
        'cbl.abuseat.org',
        'dnsbl.ipv6.xenntoo.net',
        'rbl.interserver.net',
        'b.barracudacentral.org',
        'dnsbl.cobion.com',
        'dnsbl.antispam.de',
        'ix.dnsbl.manitu.net',
        'voodoo.comodo.com',
        'relays.dnsbl.sorbs.net',
        'icmp.dnsbl.sorbs.net',
        'http.dnsbl.sorbs.net',
        'socks.dnsbl.sorbs.net',
        'smtp.dnsbl.sorbs.net',
        'web.dnsbl.sorbs.net',
        'fresh.spamrats.com',
        'noptr.spamrats.com',
        'spam.spamrats.com',
    ];

    $rdns = @gethostbyaddr($ip);
    if ($rdns === $ip) {
        $rdns = '';
    }

    $results = [];
    $listedCount = 0;
    $startTime = microtime(true);

    foreach ($dnsbls as $bl) {
        $query = $reversed . '.' . $bl;
        $listed = false;
        $responseIp = '';
        $responseText = '';

        if (checkdnsrr($query, 'A')) {
            $listed = true;
            $listedCount++;
            $ips = @gethostbynamel($query);
            $responseIp = is_array($ips) ? implode(', ', $ips) : $query;
        } elseif (checkdnsrr($query, 'TXT')) {
            $listed = true;
            $listedCount++;
            $txt = dns_get_record($query, DNS_TXT);
            if (!empty($txt[0]['txt'])) {
                $responseText = $txt[0]['txt'];
            }
        }

        if ($listed) {
            $responseText = $responseText !== '' ? $responseText : 'Listed on ' . $bl;
        }

        $results[] = [
            'blacklist_name' => $bl,
            'listed' => $listed,
            'response_ip' => $responseIp,
            'response_text' => $responseText,
        ];
    }

    $elapsed = round((microtime(true) - $startTime) * 1000);

    header('Content-Type: application/json');
    echo json_encode([
        'ip' => $ip,
        'rdns' => $rdns,
        'total' => count($dnsbls),
        'listed_count' => $listedCount,
        'elapsed_ms' => $elapsed,
        'results' => $results,
    ]);
    exit;
}

$viewerIp = client_ip();
page_header('IP Blacklist Checker');
?>
<style>
    .bl-results { display: none; }
    .bl-results.visible { display: block; }
    .bl-summary { display: flex; gap: 1rem; flex-wrap: wrap; }
    .bl-stat { flex: 1; min-width: 120px; text-align: center; padding: .75rem; border-radius: 10px; background: var(--panel-2); border: 1px solid var(--line); }
    .bl-stat-num { font-size: 1.8rem; font-weight: 700; }
    .bl-stat-label { font-size: .78rem; color: var(--dim); margin-top: .15rem; }
    .bl-row-clean td { border-left: 3px solid #26d07c !important; }
    .bl-row-listed td { border-left: 3px solid #e74c3c !important; }
    .progress-wrap { height: 4px; background: rgba(255,255,255,.06); border-radius: 2px; margin-top: .75rem; overflow: hidden; display: none; }
    .progress-wrap.active { display: block; }
    .progress-fill { height: 100%; width: 0%; background: linear-gradient(90deg, #5865f2, #9b59b6); border-radius: 2px; transition: width .3s; }
</style>

<div class="container" style="max-width: 960px;">
    <h1 class="h4 mb-1 reveal in-view">&#128683; IP Blacklist Checker</h1>
    <p class="text-secondary mb-3 reveal in-view">Checks an IP address against <strong>25 DNS-based blacklists</strong> (DNSBL) and performs a reverse DNS lookup. Useful for diagnosing mail delivery issues or verifying your server's reputation.</p>

    <div class="alert alert-warning reveal in-view"><strong>⚠️ Use responsibly.</strong> Only check IPs you own or manage. This tool makes DNS queries from the server to public blacklists — it does not attack or contact the target IP.</div>

    <div class="card reveal in-view"><div class="card-body">
        <form id="blForm" class="mb-0">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <div class="input-group">
                <input class="form-control" name="ip" maxlength="45" placeholder="e.g. <?= e($viewerIp) ?>" value="" required>
                <button class="btn btn-primary" type="submit" id="blBtn">
                    <span id="blBtnLabel">Check IP</span>
                    <span id="blBtnSpin" class="d-none"><span class="spinner-border spinner-border-sm" role="status"></span></span>
                </button>
            </div>
        </form>
        <div class="progress-wrap" id="blProgress"><div class="progress-fill" id="blProgressFill"></div></div>
    </div></div>

    <div id="blError" class="alert alert-danger mt-4 d-none reveal in-view"></div>

    <div id="blResults" class="bl-results mt-4">
        <div class="bl-summary mb-3 reveal in-view" id="blSummary"></div>

        <div class="card mb-3 reveal in-view"><div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm table-dark align-middle mb-0">
                    <thead>
                        <tr>
                            <th style="min-width:200px;">Blacklist</th>
                            <th style="width:100px;">Status</th>
                            <th>Details</th>
                        </tr>
                    </thead>
                    <tbody id="blTable"></tbody>
                </table>
            </div>
        </div></div>

        <div class="card reveal in-view"><div class="card-body" id="blRdns"></div></div>
    </div>
</div>

<script>
(function () {
    var form = document.getElementById('blForm');
    var btn = document.getElementById('blBtn');
    var btnLabel = document.getElementById('blBtnLabel');
    var btnSpin = document.getElementById('blBtnSpin');
    var errBox = document.getElementById('blError');
    var results = document.getElementById('blResults');
    var progress = document.getElementById('blProgress');
    var progressFill = document.getElementById('blProgressFill');
    var ipInput = form.querySelector('input[name="ip"]');

    ipInput.value = '<?= e($viewerIp) ?>';

    form.addEventListener('submit', function (e) {
        e.preventDefault();
        errBox.classList.add('d-none');
        results.classList.remove('visible');
        btn.disabled = true;
        btnLabel.textContent = 'Checking...';
        btnSpin.classList.remove('d-none');
        progress.classList.add('active');
        progressFill.style.width = '15%';

        var fd = new FormData(form);

        var progTimer = setInterval(function () {
            var w = parseFloat(progressFill.style.width);
            if (w < 90) {
                progressFill.style.width = (w + Math.random() * 3) + '%';
            }
        }, 200);

        fetch('index.php', { method: 'POST', body: fd })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                clearInterval(progTimer);
                progressFill.style.width = '100%';
                if (d.error) {
                    errBox.textContent = d.error;
                    errBox.classList.remove('d-none');
                    resetBtn();
                    setTimeout(function () { progress.classList.remove('active'); }, 400);
                    return;
                }
                renderResults(d);
                results.classList.add('visible');
                resetBtn();
                setTimeout(function () { progress.classList.remove('active'); }, 600);
                results.querySelectorAll('.reveal').forEach(function (el) {
                    el.classList.add('in-view');
                });
            })
            .catch(function () {
                clearInterval(progTimer);
                errBox.textContent = 'Network error. Try again.';
                errBox.classList.remove('d-none');
                resetBtn();
                progress.classList.remove('active');
            });
    });

    function resetBtn() {
        btn.disabled = false;
        btnLabel.textContent = 'Check IP';
        btnSpin.classList.add('d-none');
    }

    function esc(s) {
        var d = document.createElement('div');
        d.appendChild(document.createTextNode(s || ''));
        return d.innerHTML;
    }

    function renderResults(d) {
        var summaryEl = document.getElementById('blSummary');
        var tableEl = document.getElementById('blTable');
        var rdnsEl = document.getElementById('blRdns');

        var clean = d.total - d.listed_count;
        var pct = d.total > 0 ? Math.round(d.listed_count / d.total * 100) : 0;

        summaryEl.innerHTML =
            '<div class="bl-stat"><div class="bl-stat-num" style="color:#e74c3c;">' + d.listed_count + '</div><div class="bl-stat-label">Listed</div></div>' +
            '<div class="bl-stat"><div class="bl-stat-num" style="color:#26d07c;">' + clean + '</div><div class="bl-stat-label">Clean</div></div>' +
            '<div class="bl-stat"><div class="bl-stat-num" style="color:var(--accent1);">' + d.total + '</div><div class="bl-stat-label">Blacklists</div></div>' +
            '<div class="bl-stat"><div class="bl-stat-num">' + d.elapsed_ms + 'ms</div><div class="bl-stat-label">Scan Time</div></div>';

        var rows = '';
        for (var i = 0; i < d.results.length; i++) {
            var r = d.results[i];
            var cls = r.listed ? 'bl-row-listed' : 'bl-row-clean';
            var badge = r.listed
                ? '<span class="badge bg-danger">Listed</span>'
                : '<span class="badge bg-success">Clean</span>';
            var detail = '';
            if (r.listed) {
                if (r.response_ip) detail = '<code class="small">' + esc(r.response_ip) + '</code>';
                else if (r.response_text) detail = '<span class="small text-secondary">' + esc(r.response_text) + '</span>';
            } else {
                detail = '<span class="text-secondary small">—</span>';
            }
            rows += '<tr class="' + cls + '"><td><code>' + esc(r.blacklist_name) + '</code></td><td>' + badge + '</td><td>' + detail + '</td></tr>';
        }
        tableEl.innerHTML = rows;

        if (d.rdns) {
            rdnsEl.innerHTML = '<strong>Reverse DNS:</strong> <code>' + esc(d.rdns) + '</code>';
        } else {
            rdnsEl.innerHTML = '<strong>Reverse DNS:</strong> <span class="text-secondary">No PTR record found for ' + esc(d.ip) + '</span>';
        }
    }
})();
</script>
<?php page_footer(); ?>
