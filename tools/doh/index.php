<?php
require_once __DIR__ . '/../../functions.php';

start_session();
$cfg = $GLOBALS['CFG'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax'])) {
    header('Content-Type: application/json');
    $domain = trim((string)($_POST['domain'] ?? ''));
    $recordType = strtoupper(trim((string)($_POST['record_type'] ?? 'A')));
    $validTypes = ['A', 'AAAA', 'MX', 'NS', 'TXT', 'CNAME', 'SOA', 'PTR', 'SRV', 'CAA', 'DNSKEY', 'DS'];
    if (!in_array($recordType, $validTypes, true)) {
        $recordType = 'A';
    }

    if (!csrf_verify()) {
        echo json_encode(['error' => 'Invalid CSRF token.']);
        exit;
    }
    if (!rate_limit_check('doh', 10, (int)$cfg['rate_window_seconds'])) {
        echo json_encode(['error' => 'Rate limit reached.']);
        exit;
    }
    if ($domain === '' || strlen($domain) > 253 || !preg_match('/^(?!-)(?:[a-zA-Z0-9]([a-zA-Z0-9\-]{0,61}[a-zA-Z0-9])?\.)*[a-zA-Z]{2,}$/', $domain)) {
        echo json_encode(['error' => 'Invalid domain.']);
        exit;
    }

    log_activity('tool_doh_php', $domain . ' ' . $recordType);

    $phpRecords = [];
    $dnsTypeMap = [
        'A' => 'DNS_A', 'AAAA' => 'DNS_AAAA', 'MX' => 'DNS_MX', 'NS' => 'DNS_NS',
        'TXT' => 'DNS_TXT', 'CNAME' => 'DNS_CNAME', 'SOA' => 'DNS_SOA', 'PTR' => 'DNS_PTR',
    ];

    try {
        if (isset($dnsTypeMap[$recordType]) && defined($dnsTypeMap[$recordType])) {
            $r = @dns_get_record($domain, constant($dnsTypeMap[$recordType]));
            if (is_array($r)) {
                $phpRecords = $r;
            }
        } else {
            $r = @dns_get_record($domain);
            if (is_array($r)) {
                $phpRecords = array_values(array_filter($r, function ($rec) use ($recordType) {
                    return strtoupper((string)($rec['type'] ?? '')) === $recordType;
                }));
            }
        }
    } catch (Throwable $t) {
        echo json_encode(['error' => 'PHP DNS lookup failed: ' . $t->getMessage()]);
        exit;
    }

    $entries = [];
    foreach ($phpRecords as $rec) {
        $entries[] = [
            'host' => $rec['host'] ?? '',
            'ttl' => (int)($rec['ttl'] ?? 0),
            'type' => $rec['type'] ?? '',
            'class' => $rec['class'] ?? 'IN',
            'ip' => $rec['ip'] ?? null,
            'ipv6' => $rec['ipv6'] ?? null,
            'target' => $rec['target'] ?? null,
            'txt' => $rec['txt'] ?? null,
            'entries' => $rec['entries'] ?? null,
            'mname' => $rec['mname'] ?? null,
            'rname' => $rec['rname'] ?? null,
            'serial' => $rec['serial'] ?? null,
            'tag' => $rec['tag'] ?? null,
            'value' => $rec['value'] ?? null,
            'flags' => $rec['flags'] ?? null,
        ];
    }

    echo json_encode(['records' => $entries]);
    exit;
}

$domain = trim((string)($_GET['q'] ?? ''));
$recordType = strtoupper(trim((string)($_GET['type'] ?? 'A')));
$provider = (string)($_GET['provider'] ?? 'cloudflare');
$validTypes = ['A', 'AAAA', 'MX', 'NS', 'TXT', 'CNAME', 'SOA', 'PTR', 'SRV', 'CAA', 'DNSKEY', 'DS'];
if (!in_array($recordType, $validTypes, true)) {
    $recordType = 'A';
}
if (!in_array($provider, ['cloudflare', 'google', 'quad9'], true)) {
    $provider = 'cloudflare';
}

if ($domain !== '') {
    $entry = ['domain' => $domain, 'type' => $recordType, 'provider' => $provider, 'time' => date('H:i:s')];
    if (!isset($_SESSION['doh_history'])) {
        $_SESSION['doh_history'] = [];
    }
    array_unshift($_SESSION['doh_history'], $entry);
    $_SESSION['doh_history'] = array_slice($_SESSION['doh_history'], 0, 10);
}

page_header('DNS over HTTPS Lookup');
?>
<style>
.doh-provider-grid{display:flex;gap:8px;flex-wrap:wrap}
.doh-provider-btn{flex:1;min-width:120px;padding:8px 12px;border-radius:8px;background:var(--panel-2);border:1px solid var(--line);color:var(--dim);cursor:pointer;text-align:center;font-size:.82rem;font-weight:600;transition:all .15s}
.doh-provider-btn:hover{border-color:var(--accent1);color:var(--fg)}
.doh-provider-btn.active{border-color:var(--accent1);color:var(--accent1);background:rgba(88,101,242,.08)}
.doh-type-grid{display:flex;gap:6px;flex-wrap:wrap}
.doh-type-btn{padding:4px 10px;border-radius:6px;background:var(--panel-2);border:1px solid var(--line);color:var(--dim);cursor:pointer;font-size:.78rem;font-weight:600;font-family:monospace;transition:all .15s}
.doh-type-btn:hover{border-color:var(--accent2);color:var(--fg)}
.doh-type-btn.active{border-color:var(--accent2);color:var(--accent2);background:rgba(56,189,248,.08)}
.doh-ip-card{display:inline-block;padding:3px 10px;border-radius:6px;background:rgba(56,189,248,.08);border:1px solid rgba(56,189,248,.25);font-family:monospace;font-size:.85rem;margin:2px}
.doh-mx-item{display:flex;align-items:center;gap:10px;padding:6px 0;border-bottom:1px solid rgba(255,255,255,.04)}
.doh-mx-item:last-child{border-bottom:none}
.doh-mx-pri{font-size:.75rem;font-weight:700;color:var(--accent1);min-width:28px}
.doh-ns-item{display:inline-block;padding:3px 10px;border-radius:6px;background:rgba(88,101,242,.08);border:1px solid rgba(88,101,242,.25);font-family:monospace;font-size:.82rem;margin:2px}
.doh-status-bar{display:flex;gap:10px;flex-wrap:wrap;margin:10px 0;font-size:.82rem}
.doh-status-item{padding:3px 10px;border-radius:6px;background:var(--panel-2);border:1px solid var(--line)}
.doh-copy-btns{display:flex;gap:6px;margin-top:8px}
.doh-copy-btn{padding:4px 12px;border-radius:6px;background:var(--panel-2);border:1px solid var(--line);color:var(--dim);cursor:pointer;font-size:.78rem;transition:all .15s}
.doh-copy-btn:hover{border-color:var(--accent1);color:var(--fg)}
.doh-spinner{display:inline-block;width:1rem;height:1rem;border:2px solid rgba(88,101,242,.25);border-top-color:var(--accent1);border-radius:50%;animation:spin .6s linear infinite;margin-right:6px;vertical-align:middle}
@keyframes spin{to{transform:rotate(360deg)}}
.doh-history-row{cursor:pointer;padding:6px 10px;border-radius:6px;transition:background .15s;font-size:.84rem}
.doh-history-row:hover{background:rgba(88,101,242,.08)}
.doh-history-domain{color:var(--fg);font-weight:600}
.doh-history-meta{color:var(--dim);font-size:.75rem}
</style>

<div class="container" style="max-width: 960px;">
    <h1 class="h4 mb-1 reveal in-view">&#128269; DNS over HTTPS Lookup</h1>
    <p class="text-secondary mb-3 reveal in-view">Query DNS records via encrypted HTTPS using Cloudflare, Google, or Quad9 resolvers. A server-side <code>dns_get_record()</code> runs for comparison.</p>

    <div class="card mb-3 reveal in-view"><div class="card-body">
        <form id="dohForm" class="mb-0">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="record_type" id="recordTypeInput" value="<?= e($recordType) ?>">
            <input type="hidden" name="provider" id="providerInput" value="<?= e($provider) ?>">
            <div class="row g-2 mb-3">
                <div class="col-md-8">
                    <input class="form-control" name="domain" maxlength="253" placeholder="example.com" value="<?= e($domain) ?>" required id="dohDomain">
                </div>
                <div class="col-md-4">
                    <button class="btn btn-primary w-100" type="submit" id="dohBtn">
                        <span id="dohBtnLabel">Query</span>
                        <span id="dohBtnSpin" class="d-none"><span class="doh-spinner"></span></span>
                    </button>
                </div>
            </div>
            <div class="mb-2"><span class="text-secondary small fw-semibold">Record Type</span></div>
            <div class="doh-type-grid mb-3" id="typeGrid">
                <?php foreach ($validTypes as $t): ?>
                    <button type="button" class="doh-type-btn<?= $t === $recordType ? ' active' : '' ?>" data-type="<?= e($t) ?>"><?= e($t) ?></button>
                <?php endforeach; ?>
            </div>
            <div class="mb-2"><span class="text-secondary small fw-semibold">DNS Provider</span></div>
            <div class="doh-provider-grid mb-0" id="providerGrid">
                <button type="button" class="doh-provider-btn<?= $provider === 'cloudflare' ? ' active' : '' ?>" data-provider="cloudflare">&#9729; Cloudflare<br><span style="font-size:.7rem;font-weight:400;">1.1.1.1</span></button>
                <button type="button" class="doh-provider-btn<?= $provider === 'google' ? ' active' : '' ?>" data-provider="google">&#128309; Google<br><span style="font-size:.7rem;font-weight:400;">8.8.8.8</span></button>
                <button type="button" class="doh-provider-btn<?= $provider === 'quad9' ? ' active' : '' ?>" data-provider="quad9">&#128737; Quad9<br><span style="font-size:.7rem;font-weight:400;">9.9.9.9</span></button>
            </div>
        </form>
    </div></div>

    <div id="dohError" class="alert alert-danger mt-3 d-none reveal in-view"></div>

    <div id="dohResults" class="d-none">
        <div class="doh-status-bar mt-3" id="dohStatusBar"></div>
        <div class="card mt-2 mb-3 reveal in-view"><div class="card-body">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <h2 class="h6 mb-0">Answer Records</h2>
                <span class="badge bg-secondary" id="dohAnswerCount">0</span>
            </div>
            <div id="dohAnswerSection"></div>
            <div class="doh-copy-btns d-none" id="dohCopyBtns">
                <button type="button" class="doh-copy-btn" id="dohCopyJson">&#128203; Copy JSON</button>
                <button type="button" class="doh-copy-btn" id="dohCopyDig">&#128203; Copy dig output</button>
            </div>
        </div></div>
        <div class="card mb-3 reveal in-view d-none" id="dohPhpCard"><div class="card-body">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <h2 class="h6 mb-0">Server-Side PHP Comparison (dns_get_record)</h2>
                <span class="badge bg-secondary" id="dohCompareBadge"></span>
            </div>
            <div id="dohPhpSection"></div>
        </div></div>
    </div>

    <?php if (!empty($_SESSION['doh_history'])): ?>
        <div class="card mb-3 reveal in-view"><div class="card-body">
            <h2 class="h6 mb-2">Recent Queries</h2>
            <?php foreach ($_SESSION['doh_history'] as $h): ?>
                <div class="doh-history-row" data-domain="<?= e($h['domain']) ?>" data-type="<?= e($h['type']) ?>" data-provider="<?= e($h['provider']) ?>">
                    <span class="doh-history-domain"><?= e($h['domain']) ?></span>
                    <span class="badge bg-secondary ms-1" style="font-size:.7rem;"><?= e($h['type']) ?></span>
                    <span class="badge bg-dark ms-1" style="font-size:.7rem;"><?= e($h['provider']) ?></span>
                    <span class="doh-history-meta ms-1"><?= e($h['time']) ?></span>
                </div>
            <?php endforeach; ?>
        </div></div>
    <?php endif; ?>
</div>

<script>
(function () {
    var form = document.getElementById('dohForm');
    var btn = document.getElementById('dohBtn');
    var btnLabel = document.getElementById('dohBtnLabel');
    var btnSpin = document.getElementById('dohBtnSpin');
    var errBox = document.getElementById('dohError');
    var results = document.getElementById('dohResults');
    var typeInput = document.getElementById('recordTypeInput');
    var providerInput = document.getElementById('providerInput');
    var typeGrid = document.getElementById('typeGrid');
    var providerGrid = document.getElementById('providerGrid');
    var domainInput = document.getElementById('dohDomain');
    var csrfToken = document.querySelector('[name=csrf]').value;
    var lastDohData = null;

    var providerUrls = {
        cloudflare: function (d, t) { return 'https://cloudflare-dns.com/dns-query?name=' + encodeURIComponent(d) + '&type=' + encodeURIComponent(t); },
        google: function (d, t) { return 'https://dns.google/resolve?name=' + encodeURIComponent(d) + '&type=' + encodeURIComponent(t); },
        quad9: function (d, t) { return 'https://dns.quad9.net:5053/dns-query?name=' + encodeURIComponent(d) + '&type=' + encodeURIComponent(t); }
    };

    typeGrid.addEventListener('click', function (e) {
        var b = e.target.closest('.doh-type-btn');
        if (!b) return;
        typeGrid.querySelectorAll('.doh-type-btn').forEach(function (x) { x.classList.remove('active'); });
        b.classList.add('active');
        typeInput.value = b.getAttribute('data-type');
        if (domainInput.value.trim().indexOf('.') !== -1) runQuery();
    });

    providerGrid.addEventListener('click', function (e) {
        var b = e.target.closest('.doh-provider-btn');
        if (!b) return;
        providerGrid.querySelectorAll('.doh-provider-btn').forEach(function (x) { x.classList.remove('active'); });
        b.classList.add('active');
        providerInput.value = b.getAttribute('data-provider');
        if (domainInput.value.trim().indexOf('.') !== -1) runQuery();
    });

    form.addEventListener('submit', function (e) { e.preventDefault(); runQuery(); });

    var autoTimer = null;
    domainInput.addEventListener('input', function () {
        clearTimeout(autoTimer);
        autoTimer = setTimeout(function () {
            var d = domainInput.value.trim();
            if (d.length > 3 && d.indexOf('.') !== -1) runQuery();
        }, 700);
    });

    document.querySelectorAll('.doh-history-row').forEach(function (row) {
        row.addEventListener('click', function () {
            domainInput.value = row.getAttribute('data-domain');
            var t = row.getAttribute('data-type');
            var p = row.getAttribute('data-provider');
            typeInput.value = t;
            providerInput.value = p;
            typeGrid.querySelectorAll('.doh-type-btn').forEach(function (b) { b.classList.toggle('active', b.getAttribute('data-type') === t); });
            providerGrid.querySelectorAll('.doh-provider-btn').forEach(function (b) { b.classList.toggle('active', b.getAttribute('data-provider') === p); });
            runQuery();
        });
    });

    document.getElementById('dohCopyJson').addEventListener('click', function () {
        if (lastDohData) copyToClipboard(JSON.stringify(lastDohData, null, 2));
    });

    document.getElementById('dohCopyDig').addEventListener('click', function () {
        if (lastDohData) copyToClipboard(formatDig(lastDohData));
    });

    function runQuery() {
        var domain = domainInput.value.trim();
        var type = typeInput.value;
        var provider = providerInput.value;
        if (!domain) return;

        errBox.classList.add('d-none');
        results.classList.add('d-none');
        document.getElementById('dohPhpCard').classList.add('d-none');
        btn.disabled = true;
        btnLabel.textContent = 'Querying...';
        btnSpin.classList.remove('d-none');

        var t0 = performance.now();

        fetchDoH(domain, type, provider)
            .then(function (dohResult) {
                var elapsed = Math.round(performance.now() - t0);
                lastDohData = dohResult;
                renderDohResults(dohResult, type, provider, elapsed);
                results.classList.remove('d-none');
                resetBtn();
                results.querySelectorAll('.reveal').forEach(function (el) { el.classList.add('in-view'); });
                fetchPhpComparison(domain, type);
            })
            .catch(function (err) {
                errBox.textContent = 'DoH query failed: ' + (err.message || 'Network error');
                errBox.classList.remove('d-none');
                resetBtn();
            });
    }

    function fetchDoH(domain, type, provider) {
        var url = providerUrls[provider](domain, type);
        var opts = {};
        if (provider === 'cloudflare') {
            opts.headers = { 'Accept': 'application/dns-json' };
        }
        return fetch(url, opts).then(function (r) {
            if (!r.ok) throw new Error('HTTP ' + r.status);
            return r.json();
        });
    }

    function fetchPhpComparison(domain, type) {
        var fd = new FormData();
        fd.append('ajax', '1');
        fd.append('csrf', csrfToken);
        fd.append('domain', domain);
        fd.append('record_type', type);

        fetch('index.php', { method: 'POST', body: fd })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data.error) {
                    renderPhpError(data.error);
                    return;
                }
                var records = data.records || [];
                renderPhpSection(records, type);
                var dohCount = lastDohData ? (lastDohData.Answer || []).length : 0;
                var phpCount = records.length;
                var badge = document.getElementById('dohCompareBadge');
                document.getElementById('dohPhpCard').classList.remove('d-none');
                document.getElementById('dohPhpCard').querySelectorAll('.reveal').forEach(function (el) { el.classList.add('in-view'); });
                if (phpCount === dohCount) {
                    badge.textContent = 'Match (' + phpCount + ' records)';
                    badge.className = 'badge bg-success';
                } else if (phpCount > 0 || dohCount > 0) {
                    badge.textContent = 'Diff \u2014 PHP: ' + phpCount + ' | DoH: ' + dohCount;
                    badge.className = 'badge bg-warning text-dark';
                } else {
                    badge.textContent = 'No records';
                    badge.className = 'badge bg-secondary';
                }
            })
            .catch(function () {
                renderPhpError('Could not reach server for comparison.');
            });
    }

    function renderDohResults(d, type, provider, elapsed) {
        var statusHTML = '';
        var rc = d.Status !== undefined ? statusToString(d.Status) : 'Unknown';
        statusHTML += '<div class="doh-status-item">Response: <code>' + esc(rc) + '</code></div>';
        statusHTML += '<div class="doh-status-item">Provider: <code>' + esc(provider) + '</code></div>';
        statusHTML += '<div class="doh-status-item">Time: <code>' + elapsed + 'ms</code></div>';
        var ad = d.AD !== undefined ? (d.AD ? 'Secure' : 'Insecure') : 'N/A';
        statusHTML += '<div class="doh-status-item">DNSSEC: <code>' + esc(ad) + '</code></div>';
        document.getElementById('dohStatusBar').innerHTML = statusHTML;

        var answers = d.Answer || [];
        document.getElementById('dohAnswerCount').textContent = answers.length;

        if (answers.length === 0) {
            document.getElementById('dohAnswerSection').innerHTML = '<div class="text-secondary small">No records returned.</div>';
            document.getElementById('dohCopyBtns').classList.add('d-none');
            return;
        }

        document.getElementById('dohCopyBtns').classList.remove('d-none');

        if (type === 'MX') {
            renderMX(answers);
        } else if (type === 'NS') {
            renderNS(answers);
        } else if (type === 'A' || type === 'AAAA') {
            renderIP(answers);
        } else {
            renderTable(answers);
        }
    }

    function renderMX(answers) {
        var sorted = answers.slice().sort(function (a, b) { return (a.MX || 99999) - (b.MX || 99999); });
        var html = '';
        sorted.forEach(function (r) {
            html += '<div class="doh-mx-item">';
            html += '<span class="doh-mx-pri">' + (r.MX || 0) + '</span>';
            html += '<span class="doh-ip-card">' + esc(r.data || '') + '</span>';
            html += '<span class="text-secondary small">TTL ' + (r.TTL || 0) + '</span>';
            html += '</div>';
        });
        document.getElementById('dohAnswerSection').innerHTML = html;
    }

    function renderNS(answers) {
        var html = '<div style="display:flex;flex-wrap:wrap;gap:4px;">';
        answers.forEach(function (r) {
            html += '<span class="doh-ns-item">' + esc(r.data || '') + '</span>';
        });
        html += '</div>';
        var ttl = answers[0] ? answers[0].TTL || 0 : 0;
        html += '<div class="text-secondary small mt-2">TTL: ' + ttl + '</div>';
        document.getElementById('dohAnswerSection').innerHTML = html;
    }

    function renderIP(answers) {
        var html = '<div style="display:flex;flex-wrap:wrap;gap:6px;">';
        answers.forEach(function (r) {
            html += '<span class="doh-ip-card">' + esc(r.data || '') + '</span>';
        });
        html += '</div>';
        var ttl = answers[0] ? answers[0].TTL || 0 : 0;
        html += '<div class="text-secondary small mt-2">TTL: ' + ttl + '</div>';
        document.getElementById('dohAnswerSection').innerHTML = html;
    }

    function renderTable(answers) {
        var html = '<div class="table-responsive"><table class="table table-sm table-dark align-middle mb-0">';
        html += '<thead><tr><th>Name</th><th>TTL</th><th>Class</th><th>Type</th><th>Data</th></tr></thead><tbody>';
        answers.forEach(function (r) {
            html += '<tr>';
            html += '<td class="small">' + esc(r.name || '') + '</td>';
            html += '<td class="small">' + (r.TTL || 0) + '</td>';
            html += '<td class="small">IN</td>';
            html += '<td class="small fw-bold">' + esc(dnsTypeInt(r.type)) + '</td>';
            html += '<td class="small" style="word-break:break-all;">' + esc(r.data || '') + '</td>';
            html += '</tr>';
        });
        html += '</tbody></table></div>';
        document.getElementById('dohAnswerSection').innerHTML = html;
    }

    function renderPhpSection(records, type) {
        if (records.length === 0) {
            document.getElementById('dohPhpSection').innerHTML = '<div class="text-secondary small">No records from PHP resolver.</div>';
            return;
        }
        var html = '<div class="table-responsive"><table class="table table-sm table-dark align-middle mb-0">';
        html += '<thead><tr><th>Name</th><th>TTL</th><th>Type</th><th>Data</th></tr></thead><tbody>';
        records.forEach(function (r) {
            var data = r.ip || r.ipv6 || r.target || '';
            if (r.txt) data = r.txt;
            if (r.entries && r.entries.length) data = r.entries.join(' ');
            if (r.mname) data = 'mname: ' + r.mname + ' rname: ' + (r.rname || '') + ' serial: ' + (r.serial || 0);
            if (r.tag) data = r.tag + ' "' + (r.value || '') + '" flags ' + (r.flags || 0);
            if (!data) data = JSON.stringify(r).substring(0, 120);
            html += '<tr>';
            html += '<td class="small">' + esc(r.host || '') + '</td>';
            html += '<td class="small">' + (r.ttl || 0) + '</td>';
            html += '<td class="small fw-bold">' + esc(r.type || '') + '</td>';
            html += '<td class="small" style="word-break:break-all;">' + esc(data) + '</td>';
            html += '</tr>';
        });
        html += '</tbody></table></div>';
        document.getElementById('dohPhpSection').innerHTML = html;
    }

    function renderPhpError(msg) {
        document.getElementById('dohPhpCard').classList.remove('d-none');
        document.getElementById('dohPhpCard').querySelectorAll('.reveal').forEach(function (el) { el.classList.add('in-view'); });
        document.getElementById('dohCompareBadge').textContent = 'Error';
        document.getElementById('dohCompareBadge').className = 'badge bg-warning text-dark';
        document.getElementById('dohPhpSection').innerHTML = '<div class="text-secondary small">' + esc(msg) + '</div>';
    }

    function statusToString(code) {
        var m = { 0: 'NOERROR', 1: 'FORMERR', 2: 'SERVFAIL', 3: 'NXDOMAIN', 4: 'NOTIMP', 5: 'REFUSED' };
        return m[code] || 'RCODE ' + code;
    }

    function dnsTypeInt(code) {
        var m = { 1: 'A', 28: 'AAAA', 15: 'MX', 2: 'NS', 16: 'TXT', 5: 'CNAME', 6: 'SOA', 12: 'PTR', 33: 'SRV', 257: 'CAA', 48: 'DNSKEY', 43: 'DS' };
        return m[code] || 'TYPE' + code;
    }

    function formatDig(d) {
        var lines = [';; ->>HEADER<<- opcode: QUERY, status: ' + statusToString(d.Status || 0) + ', id: 0'];
        lines.push('');
        lines.push(';; QUESTION SECTION:');
        lines.push(';' + (d._domain || d.Question[0].name || '') + '.\t\tIN\t' + (d._type || 'A'));
        lines.push('');
        var answers = d.Answer || [];
        if (answers.length > 0) {
            lines.push(';; ANSWER SECTION:');
            answers.forEach(function (r) {
                lines.push((r.name || '') + '.\t' + (r.TTL || 0) + '\tIN\t' + dnsTypeInt(r.type) + '\t' + (r.data || ''));
            });
        }
        if (d.Authority && d.Authority.length > 0) {
            lines.push('');
            lines.push(';; AUTHORITY SECTION:');
            d.Authority.forEach(function (r) {
                lines.push((r.name || '') + '.\t' + (r.TTL || 0) + '\tIN\t' + dnsTypeInt(r.type) + '\t' + (r.data || ''));
            });
        }
        lines.push('');
        lines.push(';; Query time: ' + (d._queryTime || 0) + ' msec');
        lines.push(';; SERVER: ' + (d._provider || 'unknown'));
        return lines.join('\n');
    }

    function copyToClipboard(text) {
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(text);
        } else {
            var ta = document.createElement('textarea');
            ta.value = text;
            ta.style.cssText = 'position:fixed;opacity:0';
            document.body.appendChild(ta);
            ta.select();
            document.execCommand('copy');
            document.body.removeChild(ta);
        }
    }

    function resetBtn() {
        btn.disabled = false;
        btnLabel.textContent = 'Query';
        btnSpin.classList.add('d-none');
    }

    function esc(s) {
        var d = document.createElement('div');
        d.appendChild(document.createTextNode(s || ''));
        return d.innerHTML;
    }
})();
</script>
<?php page_footer(); ?>