<?php
require_once __DIR__ . '/../../functions.php';

start_session();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');

    if (!csrf_verify()) {
        echo json_encode(['error' => 'Invalid CSRF token.']);
        exit;
    }
    if (!rate_limit_check('honeypot', 5, 60)) {
        echo json_encode(['error' => 'Rate limit reached. Wait a moment.']);
        exit;
    }

    $url = trim((string)($_POST['url'] ?? ''));
    if ($url === '') {
        echo json_encode(['error' => 'Enter a URL to scan.']);
        exit;
    }

    if (!preg_match('#^https?://#i', $url)) {
        $url = 'http://' . $url;
    }
    $url = filter_var($url, FILTER_VALIDATE_URL);
    if (!$url) {
        echo json_encode(['error' => 'Invalid URL.']);
        exit;
    }

    $host = parse_url($url, PHP_URL_HOST);
    if (!$host) {
        echo json_encode(['error' => 'Could not parse host from URL.']);
        exit;
    }

    log_activity('tool_honeypot', $host);

    $result = run_honeypot_scan($url, $host);
    echo json_encode($result);
    exit;
}

page_header('Honeypot Detector');

$cfg = $GLOBALS['CFG'];
?>
<style>
.hp-result { display: none; }
.hp-result.visible { display: block; }
.hp-meter-wrap { width: 100%; height: 18px; background: rgba(255,255,255,.06); border-radius: 9px; overflow: hidden; margin-top: .5rem; }
.hp-meter-bar { height: 100%; border-radius: 9px; transition: width .6s ease, background .4s ease; }
.hp-score { font-size: 2.4rem; font-weight: 700; line-height: 1; }
.hp-label { font-size: .78rem; color: var(--dim); text-transform: uppercase; letter-spacing: 1px; }
.hp-tag { display: inline-block; padding: 2px 9px; border-radius: 6px; font-size: .78rem; margin: 2px; font-weight: 500; }
.hp-tag.safe { background: rgba(38,208,124,.12); border: 1px solid rgba(38,208,124,.3); color: #26d07c; }
.hp-tag.warn { background: rgba(255,193,7,.1); border: 1px solid rgba(255,193,7,.3); color: #ffc107; }
.hp-tag.danger { background: rgba(231,76,60,.1); border: 1px solid rgba(231,76,60,.3); color: #e74c3c; }
.hp-tag.info { background: rgba(88,101,242,.1); border: 1px solid rgba(88,101,242,.3); color: #6872f2; }
.hp-row { display: flex; justify-content: space-between; align-items: center; padding: .38rem 0; border-bottom: 1px solid rgba(255,255,255,.04); }
.hp-row:last-child { border-bottom: none; }
.hp-row-label { color: var(--dim); font-size: .82rem; }
.hp-row-value { font-size: .86rem; font-weight: 500; text-align: right; max-width: 60%; word-break: break-all; }
.hp-path-table { width: 100%; }
.hp-path-table td { padding: .3rem .5rem; font-size: .82rem; border-bottom: 1px solid rgba(255,255,255,.04); }
.hp-path-table td:first-child { font-family: monospace; white-space: nowrap; }
.hp-trap { padding: .45rem .55rem; border-radius: 8px; border: 1px solid rgba(255,255,255,.06); margin-bottom: .4rem; font-size: .82rem; display: flex; justify-content: space-between; align-items: center; gap: .75rem; }
.spinner-border-sm { width: 1rem; height: 1rem; border-width: .15em; }
</style>

<div class="container" style="max-width: 960px;">
    <h1 class="h4 mb-1 reveal in-view">&#128270; Honeypot Detector</h1>
    <p class="text-secondary mb-3 reveal in-view">Scans a website for honeypot and bot-trap indicators: hidden form fields, off-screen CSS traps, JS behavioral traps (clickjacking, keylogging, clipboard hijacks, headless fingerprints), cross-origin data exfiltration, scareware/social-engineering landmines, WAF signatures, server fingerprinting, and tracking scripts.</p>

    <div class="card reveal in-view"><div class="card-body">
        <form id="hpForm" class="mb-0">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <div class="row g-2">
                <div class="col-md-8">
                    <input class="form-control" name="url" maxlength="2048" placeholder="https://example.com" required>
                </div>
                <div class="col-md-4">
                    <button class="btn btn-primary w-100" type="submit" id="hpBtn">
                        <span id="btnLabel">Scan</span>
                        <span id="btnSpin" class="d-none"><span class="spinner-border spinner-border-sm" role="status"></span> Scanning...</span>
                    </button>
                </div>
            </div>
        </form>
    </div></div>

    <div id="hpProgress" class="mt-3 d-none reveal in-view">
        <div class="card"><div class="card-body text-center py-3">
            <div class="text-secondary mb-2" id="progressText">Initializing scan...</div>
            <div class="hp-meter-wrap"><div class="hp-meter-bar" id="progressBar" style="width:0%;background:var(--accent1);"></div></div>
        </div></div>
    </div>

    <div id="hpError" class="alert alert-danger mt-4 d-none reveal in-view"></div>

    <div id="hpResults" class="hp-result mt-4">
        <div class="row g-3 mb-3">
            <div class="col-md-4">
                <div class="card reveal in-view"><div class="card-body text-center">
                    <div class="hp-label">Security Score</div>
                    <div class="hp-score mt-1" id="scoreVal">--</div>
                    <div class="hp-meter-wrap"><div class="hp-meter-bar" id="scoreBar" style="width:0%"></div></div>
                    <div id="scoreGrade" class="text-secondary small mt-1"></div>
                </div></div>
            </div>
            <div class="col-md-4">
                <div class="card reveal in-view"><div class="card-body text-center">
                    <div class="hp-label">Checks Passed</div>
                    <div class="hp-score mt-1" id="checksPassed">--</div>
                    <div id="checksDetail" class="text-secondary small mt-1"></div>
                </div></div>
            </div>
            <div class="col-md-4">
                <div class="card reveal in-view"><div class="card-body text-center">
                    <div class="hp-label">Indicators Found</div>
                    <div class="hp-score mt-1" id="indicatorsCount">--</div>
                    <div id="indicatorsDetail" class="text-secondary small mt-1"></div>
                </div></div>
            </div>
        </div>

        <div class="card mb-3 reveal in-view"><div class="card-body">
            <h2 class="h6 mb-3">&#128274; Hidden Form Fields</h2>
            <div id="hiddenFieldsContent"></div>
        </div></div>

        <div class="card mb-3 reveal in-view"><div class="card-body">
            <h2 class="h6 mb-3">&#129302; Advanced Trap Analysis</h2>
            <div id="advancedTrapsContent"></div>
        </div></div>

        <div class="card mb-3 reveal in-view"><div class="card-body">
            <h2 class="h6 mb-3">&#128375; Honey Paths</h2>
            <div id="honeyPathsContent"></div>
        </div></div>

        <div class="card mb-3 reveal in-view"><div class="card-body">
            <h2 class="h6 mb-3">&#128421; Server Fingerprints</h2>
            <div id="serverFingerprints"></div>
        </div></div>

        <div class="card mb-3 reveal in-view"><div class="card-body">
            <h2 class="h6 mb-3">&#128737; WAF Detection</h2>
            <div id="wafDetection"></div>
        </div></div>

        <div class="card mb-3 reveal in-view"><div class="card-body">
            <h2 class="h6 mb-3">&#128200; Analytics & Tracking</h2>
            <div id="analyticsContent"></div>
        </div></div>

        <div class="card mb-3 reveal in-view"><div class="card-body">
            <h2 class="h6 mb-3">&#128161; Recommendations</h2>
            <div id="recommendationsContent"></div>
        </div></div>
    </div>
</div>

<script>
(function () {
    var form = document.getElementById('hpForm');
    var btn = document.getElementById('hpBtn');
    var btnLabel = document.getElementById('btnLabel');
    var btnSpin = document.getElementById('btnSpin');
    var errBox = document.getElementById('hpError');
    var results = document.getElementById('hpResults');
    var progWrap = document.getElementById('hpProgress');
    var progressText = document.getElementById('progressText');
    var progressBar = document.getElementById('progressBar');

    var steps = [
        'Fetching page content...',
        'Checking hidden form fields...',
        'Scanning hidden containers & CSS traps...',
        'Analyzing JS behavioral traps...',
        'Detecting cross-origin data flows...',
        'Checking server fingerprints & WAF...',
        'Probing robots.txt & honey paths...',
        'Scanning for scareware / spam pages...',
        'Checking analytics & tracking...',
        'Compiling results...'
    ];

    var progressTimer = null;
    var progressIdx = 0;

    function startProgress() {
        progressIdx = 0;
        progressBar.style.width = '0%';
        progressText.textContent = steps[0];
        progWrap.classList.remove('d-none');
        progressTimer = setInterval(function () {
            progressIdx++;
            if (progressIdx < steps.length) {
                progressText.textContent = steps[progressIdx];
                progressBar.style.width = Math.min(95, Math.round((progressIdx / (steps.length - 1)) * 95)) + '%';
            }
        }, 700);
    }

    function stopProgress(pct) {
        clearInterval(progressTimer);
        progressBar.style.width = (pct || 100) + '%';
        progressText.textContent = 'Scan complete!';
        setTimeout(function () { progWrap.classList.add('d-none'); }, 600);
    }

    form.addEventListener('submit', function (e) {
        e.preventDefault();
        errBox.classList.add('d-none');
        results.classList.remove('visible');
        btn.disabled = true;
        btnLabel.classList.add('d-none');
        btnSpin.classList.remove('d-none');
        startProgress();

        var fd = new FormData(form);
        fetch('index.php', { method: 'POST', body: fd })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                stopProgress(100);
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
                stopProgress(0);
                errBox.textContent = 'Network error. Try again.';
                errBox.classList.remove('d-none');
                resetBtn();
            });
    });

    function resetBtn() {
        btn.disabled = false;
        btnLabel.classList.remove('d-none');
        btnSpin.classList.add('d-none');
    }

    function esc(s) {
        var d = document.createElement('div');
        d.appendChild(document.createTextNode(s || ''));
        return d.innerHTML;
    }

    function renderResults(d) {
        var score = d.score || 0;
        var scoreEl = document.getElementById('scoreVal');
        var barEl = document.getElementById('scoreBar');
        var gradeEl = document.getElementById('scoreGrade');

        scoreEl.textContent = score;
        barEl.style.width = score + '%';
        if (score >= 80) {
            barEl.style.background = '#26d07c';
            scoreEl.style.color = '#26d07c';
            gradeEl.textContent = 'Good — few honeypot indicators detected';
        } else if (score >= 50) {
            barEl.style.background = '#ffc107';
            scoreEl.style.color = '#ffc107';
            gradeEl.textContent = 'Moderate — some indicators found';
        } else {
            barEl.style.background = '#e74c3c';
            scoreEl.style.color = '#e74c3c';
            gradeEl.textContent = 'Low — many honeypot indicators present';
        }

        document.getElementById('checksPassed').textContent = (d.checks_passed || 0) + '/' + (d.checks_total || 18);
        document.getElementById('checksDetail').textContent = 'Out of ' + (d.checks_total || 18) + ' checks';
        document.getElementById('indicatorsCount').textContent = d.indicators_total || 0;
        document.getElementById('indicatorsDetail').textContent = 'Findings across all checks';

        renderHiddenFields(d.hidden_fields || []);
        renderAdvancedTraps(d.advanced_traps || []);
        renderHoneyPaths(d.honey_paths || []);
        renderServerFingerprints(d.server_fingerprints || {});
        renderWafDetection(d.waf || {});
        renderAnalytics(d.analytics || {});
        renderRecommendations(d.recommendations || []);
    }

    function renderHiddenFields(fields) {
        var el = document.getElementById('hiddenFieldsContent');
        if (fields.length === 0) {
            el.innerHTML = '<div class="text-secondary small">No suspicious hidden form fields detected.</div>';
            return;
        }
        var html = '<div class="table-responsive"><table class="table table-sm table-dark align-middle mb-0"><thead><tr><th>Form Action</th><th>Field Name</th><th>Type</th><th>Risk</th></tr></thead><tbody>';
        fields.forEach(function (f) {
            var cls = f.suspicious ? 'danger' : 'safe';
            var lbl = f.suspicious ? 'Suspicious' : 'Normal';
            html += '<tr><td class="small"><code>' + esc(f.action || '(self)') + '</code></td><td><code>' + esc(f.name) + '</code></td><td class="small">' + esc(f.type || 'hidden') + '</td><td><span class="hp-tag ' + cls + '">' + lbl + '</span></td></tr>';
        });
        html += '</tbody></table></div>';
        el.innerHTML = html;
    }

    function renderAdvancedTraps(traps) {
        var el = document.getElementById('advancedTrapsContent');
        if (!traps.length) {
            el.innerHTML = '<div class="text-secondary small">No advanced behavioral traps detected.</div>';
            return;
        }
        var groups = {};
        traps.forEach(function (t) {
            if (!groups[t.group]) groups[t.group] = [];
            groups[t.group].push(t);
        });
        var html = '';
        Object.keys(groups).forEach(function (g) {
            html += '<div class="mb-2"><span class="hp-tag info">' + esc(g) + '</span></div>';
            groups[g].forEach(function (t) {
                var cls = t.severity;
                var lbl = t.severity === 'danger' ? 'Trap' : (t.severity === 'warn' ? 'Suspicious' : 'Info');
                html += '<div class="hp-trap"><span>' + esc(t.label) + '</span><span class="hp-tag ' + cls + '">' + lbl + '</span></div>';
            });
        });
        el.innerHTML = html;
    }

    function renderHoneyPaths(paths) {
        var el = document.getElementById('honeyPathsContent');
        if (paths.length === 0) {
            el.innerHTML = '<div class="text-secondary small">No honeypot paths tested.</div>';
            return;
        }
        var html = '<table class="hp-path-table"><tbody>';
        paths.forEach(function (p) {
            var cls = p.status >= 400 ? 'safe' : (p.status >= 300 ? 'warn' : 'danger');
            var lbl = p.status >= 400 ? 'Blocked' : (p.status >= 300 ? 'Redirect' : 'Accessible');
            html += '<tr><td>' + esc(p.path) + '</td><td>' + esc(p.status + ' ' + p.status_text) + '</td><td><span class="hp-tag ' + cls + '">' + lbl + '</span></td></tr>';
        });
        html += '</tbody></table>';
        el.innerHTML = html;
    }

    function renderServerFingerprints(fp) {
        var el = document.getElementById('serverFingerprints');
        var rows = [
            ['Server Header', fp.server || 'Not disclosed'],
            ['X-Powered-By', fp.x_powered_by || 'Not disclosed'],
            ['X-AspNet-Version', fp.x_aspnet_version || 'Not disclosed'],
            ['X-Generator', fp.x_generator || 'Not disclosed'],
            ['Technology', fp.technology || 'Unknown']
        ];
        var html = '';
        rows.forEach(function (r) {
            var val = r[1];
            var cls = (val === 'Not disclosed' || val === 'Unknown') ? 'safe' : 'info';
            html += '<div class="hp-row"><span class="hp-row-label">' + r[0] + '</span><span class="hp-row-value">' + (cls === 'safe' ? '<span class="text-secondary">' + esc(val) + '</span>' : '<span class="hp-tag ' + cls + '">' + esc(val) + '</span>') + '</span></div>';
        });
        el.innerHTML = html;
    }

    function renderWafDetection(waf) {
        var el = document.getElementById('wafDetection');
        if (!waf.detected) {
            el.innerHTML = '<div class="text-secondary small">No WAF signatures detected in response headers.</div>';
            return;
        }
        var tags = '';
        (waf.signatures || []).forEach(function (s) {
            tags += '<span class="hp-tag info">' + esc(s) + '</span> ';
        });
        el.innerHTML = '<div class="mb-2"><span class="hp-tag safe">&#9989; WAF Detected</span></div>' + tags;
    }

    function renderAnalytics(a) {
        var el = document.getElementById('analyticsContent');
        if (!a.found) {
            el.innerHTML = '<div class="text-secondary small">No analytics or tracking scripts detected.</div>';
            return;
        }
        var tags = '';
        (a.scripts || []).forEach(function (s) {
            tags += '<span class="hp-tag warn">' + esc(s) + '</span> ';
        });
        el.innerHTML = '<div class="mb-2"><span class="hp-tag warn">&#128200; Tracking Found</span></div>' + tags;
    }

    function renderRecommendations(recs) {
        var el = document.getElementById('recommendationsContent');
        if (recs.length === 0) {
            el.innerHTML = '<div class="text-secondary small">No specific recommendations at this time.</div>';
            return;
        }
        var html = '';
        recs.forEach(function (r) {
            var icon = r.type === 'danger' ? '&#9888;' : (r.type === 'warn' ? '&#9881;' : '&#9989;');
            var cls = r.type === 'danger' ? 'danger' : (r.type === 'warn' ? 'warn' : 'safe');
            html += '<div class="hp-row"><span class="hp-row-label"><span class="hp-tag ' + cls + '">' + icon + '</span> ' + esc(r.message) + '</span></div>';
        });
        el.innerHTML = html;
    }
})();
</script>
<?php page_footer(); ?>

<?php
function run_honeypot_scan(string $url, string $host): array {
    $parsed = parse_url($url);
    $base = $parsed['scheme'] . '://' . $parsed['host'];
    if (isset($parsed['port'])) {
        $base .= ':' . $parsed['port'];
    }
    $base = rtrim($base, '/');

    $pageHtml = '';
    $pageHeaders = [];
    $responseCode = 0;

    $ch = curl_init($base . '/');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 14,
        CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 5,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => 0,
        CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
        CURLOPT_HEADERFUNCTION => function ($curl, $header) use (&$pageHeaders) {
            $parts = explode(':', $header, 2);
            if (count($parts) === 2) {
                $pageHeaders[strtolower(trim($parts[0]))] = trim($parts[1]);
            }
            return strlen($header);
        },
    ]);
    $pageHtml = curl_exec($ch);
    $responseCode = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $finalUrl = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
    $totalTime = curl_getinfo($ch, CURLINFO_TOTAL_TIME);
    curl_close($ch);

    $score = 100;
    $checksPassed = 0;
    $checksTotal = 18;
    $hiddenFields = [];
    $honeyPaths = [];
    $advancedTraps = [];
    $serverFp = [
        'server' => '',
        'x_powered_by' => '',
        'x_aspnet_version' => '',
        'x_generator' => '',
        'technology' => '',
    ];
    $waf = ['detected' => false, 'signatures' => []];
    $analytics = ['found' => false, 'scripts' => []];
    $recommendations = [];
    $indicatorsTotal = 0;

    $hiddenFieldData = check_hidden_form_fields($pageHtml, $base);
    $hiddenFields = $hiddenFieldData['fields'];
    $suspiciousCount = $hiddenFieldData['suspicious_count'];
    if ($suspiciousCount > 0) {
        $score -= min(15, $suspiciousCount * 5);
        $indicatorsTotal += $suspiciousCount;
        $recommendations[] = ['type' => 'danger', 'message' => $suspiciousCount . ' suspicious hidden form field(s) detected. These may be honeypot traps for bots.'];
    } else {
        $checksPassed++;
    }

    $hiddenLinkCheck = check_hidden_links($pageHtml);
    $hiddenLinkCount = $hiddenLinkCheck['count'];
    if ($hiddenLinkCount > 0) {
        $score -= min(10, $hiddenLinkCount * 3);
        $indicatorsTotal += $hiddenLinkCount;
        $recommendations[] = ['type' => 'warn', 'message' => $hiddenLinkCount . ' hidden link(s) found (display:none or zero-size). Could be link honeypots.'];
    } else {
        $checksPassed++;
    }

    // ---- CSS / off-screen traps ----
    $cssTrapData = check_css_traps($pageHtml);
    foreach ($cssTrapData['patterns'] as $p) {
        $advancedTraps[] = array_merge($p, ['group' => 'CSS Traps']);
        $indicatorsTotal++;
        $score -= severity_score($p['severity']);
    }
    $checksPassed++;

    // ---- Hidden containers with interactive elements ----
    $hiddenContData = check_hidden_containers($pageHtml);
    foreach ($hiddenContData['containers'] as $c) {
        $advancedTraps[] = ['label' => $c, 'severity' => 'warn', 'group' => 'Hidden Containers'];
        $indicatorsTotal++;
        $score -= 4;
    }
    $checksPassed++;

    // ---- Trap anchors (href="#" with event handlers) ----
    $trapLinkData = check_trap_links($pageHtml);
    foreach ($trapLinkData['traps'] as $t) {
        $advancedTraps[] = ['label' => $t, 'severity' => 'warn', 'group' => 'Trap Links'];
        $indicatorsTotal++;
        $score -= 3;
    }
    $checksPassed++;

    // ---- JS behavioral traps ----
    $jsTrapData = check_js_traps($pageHtml);
    foreach ($jsTrapData['patterns'] as $p) {
        $advancedTraps[] = array_merge($p, ['group' => 'JS Traps']);
        $indicatorsTotal++;
        $score -= severity_score($p['severity']);
    }
    $checksPassed++;

    // ---- Cross-origin form exfiltration ----
    $crossFormData = check_cross_origin_forms($pageHtml, $host);
    foreach ($crossFormData['forms'] as $f) {
        $advancedTraps[] = ['label' => 'Form posts to foreign host "' . $f['host'] . '" (action: ' . mb_substr($f['action'], 0, 60) . ')', 'severity' => 'danger', 'group' => 'Data Exfiltration'];
        $indicatorsTotal++;
        $score -= 8;
    }
    $checksPassed++;

    // ---- Auto-redirect / meta refresh ----
    $autoRedirData = check_auto_redirect($pageHtml);
    foreach ($autoRedirData['patterns'] as $p) {
        $advancedTraps[] = array_merge($p, ['group' => 'Auto-Redirect']);
        $indicatorsTotal++;
        $score -= severity_score($p['severity']);
    }
    $checksPassed++;

    // ---- Social engineering / scareware ----
    $socialData = check_social_engineering($pageHtml);
    foreach ($socialData['patterns'] as $p) {
        $advancedTraps[] = array_merge($p, ['group' => 'Social Engineering']);
        $indicatorsTotal++;
        $score -= severity_score($p['severity']);
    }
    $checksPassed++;

    if (count($advancedTraps) > 0 && $crossFormData['found']) {
        $recommendations[] = ['type' => 'danger', 'message' => 'Forms submit data to domains other than the host. Possible stealth data exfiltration; do not enter credentials or payment info.'];
    }
    if (count($socialData['patterns']) > 0) {
        $recommendations[] = ['type' => 'danger', 'message' => 'Scareware / fake-warning / prize-lure language detected. This page may attempt social-engineering scams (fake AV alerts, tech-support phone scams, fake downloads).'];
    }
    if ($jsTrapData['keylog'] || $jsTrapData['clipboard']) {
        $recommendations[] = ['type' => 'danger', 'message' => 'JavaScript captures keystrokes or clipboard contents. This is a strong indicator of credential/phishing honeypot behavior.'];
    }
    if ($crossFormData['found']) {
        $recommendations[] = ['type' => 'danger', 'message' => 'Form action targets a different domain. Verify the destination before submitting any sensitive data.'];
    }

    $headerCheck = check_response_headers($pageHeaders);
    $headerIssues = $headerCheck['issues'];
    if (count($headerIssues) > 0) {
        $score -= min(10, count($headerIssues) * 2);
        $indicatorsTotal += count($headerIssues);
    } else {
        $checksPassed++;
    }

    $rateLimitCheck = check_rate_limit_headers($pageHeaders);
    if ($rateLimitCheck['present']) {
        $checksPassed++;
    } else {
        $recommendations[] = ['type' => 'info', 'message' => 'No rate limiting headers detected. The site may not implement rate limiting.'];
        $checksPassed++;
    }

    $robotsData = check_robots_txt($base);
    $honeyPathFromRobots = $robotsData['paths'];
    if (count($honeyPathFromRobots) > 0) {
        $indicatorsTotal += count($honeyPathFromRobots);
    }
    $checksPassed++;

    $loginFormCheck = check_login_forms($pageHtml);
    if ($loginFormCheck['found']) {
        $indicatorsTotal += $loginFormCheck['count'];
        $recommendations[] = ['type' => 'info', 'message' => $loginFormCheck['count'] . ' login form(s) found on the page. Potential authentication trap.'];
    }
    $checksPassed++;

    $serverCheck = check_server_headers($pageHeaders);
    $serverFp = $serverCheck['fingerprints'];
    if ($serverCheck['reveals_info']) {
        $score -= 5;
        $indicatorsTotal++;
        $recommendations[] = ['type' => 'warn', 'message' => 'Server technology is disclosed in response headers. Consider hiding server information.'];
    } else {
        $checksPassed++;
    }

    $wafCheck = check_waf_signatures($pageHeaders, $pageHtml);
    $waf = $wafCheck;
    $checksPassed++;

    $honeyPathData = check_common_honeypot_paths($base);
    $honeyPaths = array_merge($honeyPathFromRobots, $honeyPathData['paths']);
    $accessible = array_filter($honeyPathData['paths'], function ($p) {
        return $p['status'] >= 200 && $p['status'] < 300;
    });
    if (count($accessible) > 0) {
        $score -= min(20, count($accessible) * 5);
        $indicatorsTotal += count($accessible);
        $recommendations[] = ['type' => 'danger', 'message' => count($accessible) . ' common honeypot path(s) returned 200. These may be decoy endpoints.'];
    }
    $checksPassed++;

    $analyticsCheck = check_analytics($pageHtml);
    $analytics = $analyticsCheck;
    if ($analytics['found']) {
        $indicatorsTotal += count($analytics['scripts']);
        $recommendations[] = ['type' => 'warn', 'message' => count($analytics['scripts']) . ' analytics/tracking script(s) detected.'];
    }
    $checksPassed++;

    $score = max(0, min(100, $score));

    if ($score >= 80 && $indicatorsTotal === 0) {
        $recommendations[] = ['type' => 'safe', 'message' => 'No significant honeypot indicators detected. The site appears clean.'];
    }

    return [
        'url' => $url,
        'host' => $host,
        'score' => $score,
        'checks_passed' => $checksPassed,
        'checks_total' => $checksTotal,
        'indicators_total' => $indicatorsTotal,
        'final_url' => $finalUrl ?? '',
        'response_code' => $responseCode,
        'load_time' => round($totalTime, 2),
        'hidden_fields' => $hiddenFields,
        'advanced_traps' => $advancedTraps,
        'honey_paths' => $honeyPaths,
        'server_fingerprints' => $serverFp,
        'waf' => $waf,
        'analytics' => $analytics,
        'recommendations' => $recommendations,
    ];
}

function severity_score(string $severity): int {
    switch ($severity) {
        case 'danger': return 5;
        case 'warn': return 3;
        default: return 1;
    }
}

function check_hidden_form_fields(string $html, string $baseUrl): array {
    $fields = [];
    $suspicious = 0;

    if (empty($html)) {
        return ['fields' => $fields, 'suspicious_count' => 0];
    }

    $formPattern = '/<form\b[^>]*>(.*?)<\/form>/is';
    if (preg_match_all($formPattern, $html, $forms)) {
        foreach ($forms[0] as $formHtml) {
            $actionMatch = [];
            preg_match('/action=["\']([^"\']*)["\']/i', $formHtml, $actionMatch);
            $action = $actionMatch[1] ?? '';

            $inputPattern = '/<input\b[^>]*>/is';
            if (preg_match_all($inputPattern, $formHtml, $inputs)) {
                foreach ($inputs[0] as $inputTag) {
                    $typeMatch = [];
                    $nameMatch = [];
                    $valueMatch = [];
                    $autocompleteMatch = [];
                    preg_match('/type=["\']([^"\']*)["\']/i', $inputTag, $typeMatch);
                    preg_match('/name=["\']([^"\']*)["\']/i', $inputTag, $nameMatch);
                    preg_match('/value=["\']([^"\']*)["\']/i', $inputTag, $valueMatch);
                    preg_match('/autocomplete=["\']([^"\']*)["\']/i', $inputTag, $autocompleteMatch);

                    $type = strtolower($typeMatch[1] ?? 'text');
                    $name = $nameMatch[1] ?? '';
                    $value = $valueMatch[1] ?? '';
                    $autocomplete = strtolower($autocompleteMatch[1] ?? '');

                    if ($type === 'hidden' && $name !== '') {
                        $isSuspicious = false;
                        $nameLower = strtolower($name);
                        $valueLower = strtolower($value);

                        if (preg_match('/honeypot|trap|bot|spam|fax|phone|website|url|referrer|captcha/i', $nameLower)) {
                            $isSuspicious = true;
                        }
                        if ($type === 'hidden' && $value === '' && preg_match('/^(url|website|homepage|referrer|phone|fax)/i', $name)) {
                            $isSuspicious = true;
                        }
                        // invisible trap pepper names
                        if (preg_match('/^[a-z0-9]{16,}$/i', $name) && $value === '') {
                            $isSuspicious = true;
                        }
                        $styleMatch = [];
                        preg_match('/style=["\']([^"\']*)["\']/i', $inputTag, $styleMatch);
                        $style = $styleMatch[1] ?? '';
                        if (preg_match('/visibility\s*:\s*hidden|display\s*:\s*none|opacity\s*:\s*0|position\s*:\s*absolute/i', $style)) {
                            $isSuspicious = true;
                        }
                        $ariaHidden = stripos($inputTag, 'aria-hidden="true"') !== false;
                        $tabIndex = stripos($inputTag, 'tabindex="-1"') !== false;
                        if ($ariaHidden && $tabIndex) {
                            $isSuspicious = true;
                        }
                        // hidden field combined with autocomplete off = bot-bait pattern
                        if ($autocomplete === 'off' && $value === '' && preg_match('/^(foo|bar|baz|test|verify|confirm|free|deal|offer)/i', $name)) {
                            $isSuspicious = true;
                        }

                        if ($isSuspicious) {
                            $suspicious++;
                        }

                        $fields[] = [
                            'name' => $name,
                            'type' => $type,
                            'value' => mb_substr($value, 0, 50),
                            'action' => $action,
                            'suspicious' => $isSuspicious,
                        ];
                    }
                }
            }
        }
    }

    return ['fields' => $fields, 'suspicious_count' => $suspicious];
}

function check_hidden_links(string $html): array {
    $count = 0;

    if (empty($html)) {
        return ['count' => 0];
    }

    $linkPattern = '/<a\b[^>]*>(.*?)<\/a>/is';
    if (preg_match_all($linkPattern, $html, $links)) {
        foreach ($links[0] as $linkTag) {
            $styleMatch = [];
            preg_match('/style=["\']([^"\']*)["\']/i', $linkTag, $styleMatch);
            $style = strtolower($styleMatch[1] ?? '');

            if (preg_match('/display\s*:\s*none|visibility\s*:\s*hidden|opacity\s*:\s*0|font-size\s*:\s*0|width\s*:\s*0|height\s*:\s*0/i', $style)) {
                $count++;
            }

            if (preg_match('/class=["\'][^"\']*sr-only[^"\']*["\']/i', $linkTag) && preg_match('/position\s*:\s*absolute/i', $style)) {
                $count++;
            }
        }
    }

    $divPattern = '/<div\b[^>]*>(.*?)<\/div>/is';
    if (preg_match_all($divPattern, $html, $divs)) {
        foreach ($divs[0] as $divTag) {
            $styleMatch = [];
            preg_match('/style=["\']([^"\']*)["\']/i', $divTag, $styleMatch);
            $style = strtolower($styleMatch[1] ?? '');

            if (preg_match('/display\s*:\s*none/i', $style)) {
                $innerLinks = [];
                preg_match_all('/<a\b[^>]*href=["\']([^"\']*)["\']/i', $divTag, $innerLinks);
                $count += count($innerLinks[0]);
            }
        }
    }

    return ['count' => $count];
}

/**
 * Off-screen positioning and clipping tricks commonly used to hide
 * honeypot fields, links, and decoy content from humans.
 */
function check_css_traps(string $html): array {
    $patterns = [];
    if (empty($html)) {
        return ['patterns' => []];
    }

    $cssText = '';
    preg_match_all('/<style[^>]*>(.*?)<\/style>/is', $html, $styleBlocks);
    foreach ($styleBlocks[1] ?? [] as $block) {
        $cssText .= "\n" . $block;
    }
    preg_match_all('/style=(["\'])(.*?)\1/is', $html, $attrStyles);
    foreach ($attrStyles[2] ?? [] as $a) {
        $cssText .= "\n" . $a;
    }

    $rules = [
        ['/left\s*:\s*-\d{3,}px/i', 'Off-screen positioning (left: -Npx)', 'warn'],
        ['/top\s*:\s*-\d{3,}px/i', 'Off-screen positioning (top: -Npx)', 'warn'],
        ['/margin-left\s*:\s*-\d{3,}px/i', 'Off-screen margin trick (margin-left: -Npx)', 'warn'],
        ['/text-indent\s*:\s*-\d{4,}px/i', 'Text-indent trap (-9999px text) hides link text', 'warn'],
        ['/clip\s*:\s*rect\s*\(\s*0\s+0\s+0\s+0/i', 'Clipped element (clip rect 0,0,0,0) hides content', 'danger'],
        ['/clip-path\s*:\s*inset\s*\(\s*100%|clip-path\s*:\s*circle\s*\(\s*0/i', 'Clipped element (clip-path hides content)', 'danger'],
        ['/position\s*:\s*absolute\s*[^;]*;\s*width\s*:\s*1px\s*;\s*height\s*:\s*1px/i', '1x1 pixel trap element (offscreen clickable)', 'danger'],
        ['/opacity\s*:\s*0\s*[^;]*;\s*position/i', 'Invisible positioned element (opacity:0)', 'warn'],
    ];

    foreach ($rules as $rule) {
        if (preg_match($rule[0], $cssText)) {
            $patterns[] = ['label' => $rule[1], 'severity' => $rule[2]];
        }
    }

    return ['patterns' => $patterns];
}

/**
 * Find interactive elements (inputs/links/buttons) living inside
 * hidden containers (hidden attr, aria-hidden, display:none, opacity:0).
 */
function check_hidden_containers(string $html): array {
    $containers = [];
    if (empty($html)) {
        return ['containers' => []];
    }

    if (preg_match_all('/<input\b[^>]*>/i', $html, $m, PREG_OFFSET_CAPTURE)) {
        foreach ($m[0] as $hit) {
            $pos = $hit[1];
            $ctx = substr($html, max(0, $pos - 700), 700 + strlen($hit[0]));
            if (preg_match('/\bhidden(?:=|")|aria-hidden\s*=\s*["\']?true|display\s*:\s*none|visibility\s*:\s*hidden|opacity\s*:\s*0/i', $ctx)) {
                $containers[] = 'Input inside hidden container (possible invisible field trap)';
                break;
            }
        }
    }

    if (preg_match_all('/<a\b[^>]*href=["\']([^"\']*)["\'][^>]*>/i', $html, $m, PREG_OFFSET_CAPTURE)) {
        foreach ($m[0] as $hit) {
            $pos = $hit[1];
            $ctx = substr($html, max(0, $pos - 700), 700 + strlen($hit[0]));
            if (preg_match('/\bhidden(?:=|")|aria-hidden\s*=\s*["\']?true|display\s*:\s*none/i', $ctx)) {
                $containers[] = 'Link inside hidden container (invisible link trap)';
                break;
            }
        }
    }

    if (preg_match_all('/<button\b[^>]*>/i', $html, $m, PREG_OFFSET_CAPTURE)) {
        foreach ($m[0] as $hit) {
            $pos = $hit[1];
            $ctx = substr($html, max(0, $pos - 700), 700 + strlen($hit[0]));
            if (preg_match('/\bhidden(?:=|")|aria-hidden\s*=\s*["\']?true|display\s*:\s*none/i', $ctx)) {
                $containers[] = 'Button inside hidden container (invisible click trap)';
                break;
            }
        }
    }

    return ['containers' => array_slice(array_unique($containers), 0, 5)];
}

/**
 * Anchors that are click traps: href="#" combined with event handlers
 * that hijack navigation, or anchors with inline hover/drag handlers.
 */
function check_trap_links(string $html): array {
    $traps = [];
    if (empty($html)) {
        return ['traps' => []];
    }

    if (preg_match_all('/<a\b[^>]*href=["\']([^"\']*)["\'][^>]*>/i', $html, $m)) {
        foreach ($m[0] as $i => $tag) {
            $href = strtolower(trim($m[1][$i]));
            if ($href === '' || $href === '#') {
                if (preg_match('/on(?:click|mousedown|mouseover|focus|keydown|dblclick)\s*=/i', $tag)) {
                    $traps[] = 'Trap anchor (href="#" with interaction handler)';
                }
            }
            if (preg_match('/on(?:mousedown|mouseover)\s*=\s*["\'][^"\']*location/i', $tag)) {
                $traps[] = 'Anchor redirects on hover/mousedown';
            }
        }
    }

    if (preg_match('/javascript\s*:\s*void\s*\(?\s*0?\s*\)?/i', $html)) {
        $traps[] = 'JavaScript:void(0) links present (may be JS trap anchors)';
    }

    return ['traps' => array_slice(array_unique($traps), 0, 8)];
}

/**
 * JS behavioral traps: clickjacking, keyboard listeners, clipboard
 * hijacking, headless fingerprints, crypto-address swaps, popups.
 */
function check_js_traps(string $html): array {
    $patterns = [];
    $keylog = false;
    $clipboard = false;
    if (empty($html)) {
        return ['patterns' => [], 'keylog' => false, 'clipboard' => false];
    }

    $rules = [
        '/(?:mousedown|mouseover|mouseup|focus)\s*=\s*["\'][^"\']*(?:window\.)?location[^"\']*["\']/i' => ['Redirect on user interaction (mouse/focus -> location)', 'danger'],
        '/addEventListener\(\s*["\'](?:mousedown|mouseover|focus)["\'][\s\S]{0,200}location/i' => ['Redirect on user interaction (addEventListener -> location)', 'danger'],
        '/setTimeout\(\s*function\s*\(\s*\)\s*\{\s*(?:window\.)?location/is' => ['Countdown redirect (setTimeout -> location)', 'warn'],
        '/setInterval\(\s*["\']location/is' => ['Recurring location redirect (setInterval)', 'warn'],
        '/(?:document\.)?addEventListener\(\s*["\'](?:keydown|keypress|keyup)["\']|onkey(?:down|press|up)\s*=/i' => ['Keystroke listener (keydown/keypress/keyup) — may capture input', 'danger'],
        '/(?:document\.)?addEventListener\(\s*["\']input["\']/i' => ['Document-wide input listener — may capture field data', 'warn'],
        '/(?:navigator\.clipboard\.writeText|clipboardData|execCommand\(\s*["\']copy["\'])/i' => ['Clipboard write hijack (writeText / execCommand copy)', 'danger'],
        '/(?:document\.)?addEventListener\(\s*["\']copy["\']|oncopy\s*=/i' => ['Clipboard interception (copy event)', 'danger'],
        '/Notification\s*\.\s*requestPermission/i' => ['Notification permission request (possible notification-bomb trap)', 'warn'],
        '/window\.open\s*\([^)]*["\'][^"\']+["\']/i' => ['Pop-up window opener (window.open)', 'warn'],
        '/navigator\.webdriver/i' => ['Headless-browser fingerprint check (navigator.webdriver)', 'info'],
        '/(?:phantom\.js|headlesschrome|HeadlessChrome|selenium)/i' => ['Headless-bot detection strings present in scripts', 'info'],
        '/addEventListener\(\s*["\'](?:copy|cut)["\'][\s\S]{0,240}(?:bitcoin|btc|ethereum|eth|usdt|wallet)/i' => ['Clipboard crypto-address swap attempt (paste-jacking)', 'danger'],
        '/document\.addEventListener\(\s*["\']contextmenu["\']/i' => ['Right-click disabled / context-menu trap', 'info'],
    ];

    foreach ($rules as $re => $meta) {
        if (preg_match($re, $html)) {
            $patterns[] = ['label' => $meta[0], 'severity' => $meta[1]];
            if (strpos($meta[0], 'Keystroke') !== false) $keylog = true;
            if (strpos($meta[0], 'lipboard') !== false) $clipboard = true;
        }
    }

    return ['patterns' => array_slice($patterns, 0, 12), 'keylog' => $keylog, 'clipboard' => $clipboard];
}

/**
 * Forms that submit to a different host than the scanned site.
 * Unlike normal hidden-field checks this catches external exfiltration.
 */
function check_cross_origin_forms(string $html, string $host): array {
    $forms = [];
    if (empty($html)) {
        return ['found' => false, 'forms' => []];
    }

    if (preg_match_all('/<form\b[^>]*>/is', $html, $matches)) {
        foreach ($matches[0] as $tag) {
            if (!preg_match('/action\s*=\s*["\']([^"\']*)["\']/i', $tag, $m)) {
                continue;
            }
            $action = trim($m[1]);
            if ($action === '') {
                continue;
            }
            if (!preg_match('#^https?://#i', $action)) {
                continue; // relative = same host
            }
            $aHost = parse_url($action, PHP_URL_HOST);
            if ($aHost && strcasecmp($aHost, $host) !== 0) {
                $forms[] = ['action' => $action, 'host' => $aHost];
            }
        }
    }

    return ['found' => count($forms) > 0, 'forms' => array_slice($forms, 0, 5)];
}

/**
 * Meta refresh and JS-based auto-redirects that can bounce bots
 * to phishing/scam destinations while humans see clean content.
 */
function check_auto_redirect(string $html): array {
    $patterns = [];
    if (empty($html)) {
        return ['patterns' => []];
    }

    if (preg_match('/<meta[^>]+http-equiv\s*=\s*["\']?refresh["\']?[^>]*content\s*=\s*["\']?([^"\'>]*)/i', $html, $m)) {
        $patterns[] = ['label' => 'Meta-refresh redirect to: ' . trim(mb_substr($m[1], 0, 80)), 'severity' => 'warn'];
    }
    if (preg_match('/setTimeout\(\s*(?:function\s*\(\s*\)\s*\{?\s*)?(?:window\.)?(?:location|document\.location)/i', $html)) {
        $patterns[] = ['label' => 'JavaScript auto-redirect (location inside setTimeout)', 'severity' => 'warn'];
    }
    if (preg_match('/onload\s*=\s*["\'][^"\']*location[^"\']*["\']/i', $html)) {
        $patterns[] = ['label' => 'Redirect on page load (onload -> location)', 'severity' => 'warn'];
    }
    if (preg_match('/<script[^>]*>[\s\S]{0,400}reload\s*\([\s\S]{0,200}(' . rand(0, 100) . '|\d+)\s*\)/i', $html)) {
        // not reliable; skip
    }

    return ['patterns' => $patterns];
}

/**
 * Social-engineering landmines: scareware, fake prizes, fake OS
 * security alerts, tech-support phone scams, fake downloads/captchas.
 */
function check_social_engineering(string $html): array {
    $patterns = [];
    if (empty($html)) {
        return ['patterns' => []];
    }

    $rules = [
        '/(your (?:computer|device|phone|android|iphone|system|pc)(?:\s+is|\s+has\s+been)?\s+(?:infected|locked|compromised|at\s+risk|damaged|blocked))/i' => ['Scareware: bogus device-infection alert', 'danger'],
        '/(windows\s+(?:defender|security|firewall)[\s\S]{0,80}(?:alert|warning|protection|detected|found|remove))/i' => ['Fake OS security alert (Windows Defender scam)', 'danger'],
        '/(call\s+(?:us|this\s+number|now)\s+(?:at\s+)?[\d\-() ]{7,}|microsoft\s+(?:support|technician)|\+1[-\s]?\d{3})/i' => ['Tech-support phone scam trap', 'danger'],
        '/(you\s+(?:have|are\s+the)\s+(?:lucky|1st|first|selected)|claim\s+your\s+(?:prize|reward|gift)|you\s+won|congratulations[\s\S]{0,60}(?:winner|prize))/i' => ['Fake prize / winner lure', 'warn'],
        '/(upgrade\s+to\s+(?:premium|pro|plus|vip|gold)|unlock\s+(?:premium|pro|vip)|subscribe\s+now\s+to\s+(?:continue|watch|download|unlock|access))/i' => ['Forced subscription/upgrade pressure', 'warn'],
        '/(click\s+(?:ok|yes|here|allow|accept)\s+to\s+(?:continue|proceed|download|unlock|watch|access|verify))/i' => ['Click-here-to-proceed pressure language', 'info'],
        '/(are\s+you\s+a\s+(?:robot|human|bot)|prove\s+you[\s\'"]*(?:are\s+a\s*)?human|verify\s+you\s+are\s+human|press\s+and\s+hold)/i' => ['Fake CAPTCHA / human-verification trap', 'warn'],
        '/(accept\s+cookies\s+(?:to\s+continue|and\s+continue)|allow\s+notifications\s+to|subscribe\s+to\s+continue)/i' => ['Accept-cookie / notification trap language', 'info'],
        '/(download\s+(?:now|button|free)[\s\S]{0,60}(?:ad\b|\bpromo|fake)|fake\s+(?:download|update|alert)|your\s+download\s+(?:will|is|starts)|file\s+(?:ready|available)\s+to\s+download)/i' => ['Fake download button / update alert', 'warn'],
    ];

    foreach ($rules as $re => $meta) {
        if (preg_match($re, $html)) {
            $patterns[] = ['label' => $meta[0], 'severity' => $meta[1]];
        }
    }

    return ['patterns' => array_slice($patterns, 0, 10)];
}

function check_response_headers(array $headers): array {
    $issues = [];

    $securityHeaders = [
        'x-frame-options' => 'X-Frame-Options',
        'x-content-type-options' => 'X-Content-Type-Options',
        'x-xss-protection' => 'X-XSS-Protection',
        'content-security-policy' => 'Content-Security-Policy',
        'strict-transport-security' => 'Strict-Transport-Security',
    ];

    foreach ($securityHeaders as $key => $label) {
        if (!isset($headers[$key])) {
            $issues[] = 'Missing ' . $label;
        }
    }

    if (isset($headers['x-powered-by'])) {
        $issues[] = 'X-Powered-By header exposes technology info';
    }
    if (isset($headers['server'])) {
        $issues[] = 'Server header discloses version info';
    }

    return ['issues' => $issues];
}

function check_rate_limit_headers(array $headers): array {
    $present = false;
    $rateHeaders = ['x-ratelimit-limit', 'x-ratelimit-remaining', 'retry-after', 'x-rate-limit-limit', 'x-rate-limit-remaining', 'ratelimit-limit'];

    foreach ($rateHeaders as $key) {
        if (isset($headers[$key])) {
            $present = true;
            break;
        }
    }

    return ['present' => $present];
}

function check_robots_txt(string $base): array {
    $paths = [];

    $ch = curl_init($base . '/robots.txt');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 6,
        CURLOPT_CONNECTTIMEOUT => 4,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => 0,
        CURLOPT_USERAGENT => 'Mozilla/5.0 (compatible; HoneypotDetector/1.0)',
    ]);
    $robots = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);

    if ($code === 200 && !empty($robots)) {
        $lines = explode("\n", $robots);
        $honeypotPatterns = ['/admin', '/wp-admin', '/wp-login', '/phpmyadmin', '/phpMyAdmin', '/.env', '/.git', '/backup', '/db', '/database', '/debug', '/test', '/staging', '/.htaccess', '/config', '/setup', '/install', '/cgi-bin'];

        foreach ($lines as $line) {
            $line = trim($line);
            if (preg_match('/^Disallow:\s*(.+)/i', $line, $m)) {
                $path = trim($m[1]);
                foreach ($honeypotPatterns as $pattern) {
                    if (stripos($path, $pattern) !== false) {
                        $paths[] = ['path' => $path, 'status' => 0, 'status_text' => 'In robots.txt'];
                        break;
                    }
                }
            }
        }
    }

    return ['paths' => $paths];
}

function check_login_forms(string $html): array {
    $found = false;
    $count = 0;

    if (empty($html)) {
        return ['found' => false, 'count' => 0];
    }

    if (preg_match_all('/<form\b[^>]*>(.*?)<\/form>/is', $html, $forms)) {
        foreach ($forms[0] as $formHtml) {
            $hasPassword = preg_match('/type=["\']password["\']/i', $formHtml);
            $hasLogin = preg_match('/(login|signin|log.?in|sign.?in|email|username)/i', $formHtml);
            if ($hasPassword && $hasLogin) {
                $found = true;
                $count++;
            }
        }
    }

    return ['found' => $found, 'count' => $count];
}

function check_server_headers(array $headers): array {
    $fingerprints = [
        'server' => '',
        'x_powered_by' => '',
        'x_aspnet_version' => '',
        'x_generator' => '',
        'technology' => 'Unknown',
    ];
    $revealsInfo = false;

    if (isset($headers['server']) && $headers['server'] !== '') {
        $fingerprints['server'] = $headers['server'];
        $revealsInfo = true;
    }
    if (isset($headers['x-powered-by']) && $headers['x-powered-by'] !== '') {
        $fingerprints['x_powered_by'] = $headers['x-powered-by'];
        $revealsInfo = true;
    }
    if (isset($headers['x-aspnet-version']) && $headers['x-aspnet-version'] !== '') {
        $fingerprints['x_aspnet_version'] = $headers['x-aspnet-version'];
        $revealsInfo = true;
    }
    if (isset($headers['x-generator']) && $headers['x-generator'] !== '') {
        $fingerprints['x_generator'] = $headers['x-generator'];
    }

    $tech = 'Unknown';
    $server = strtolower($headers['server'] ?? '');
    $xpb = strtolower($headers['x-powered-by'] ?? '');

    if (strpos($server, 'cloudflare') !== false) {
        $tech = 'Cloudflare';
    } elseif (strpos($server, 'nginx') !== false) {
        $tech = 'Nginx';
    } elseif (strpos($server, 'apache') !== false) {
        $tech = 'Apache';
    } elseif (strpos($server, 'litespeed') !== false) {
        $tech = 'LiteSpeed';
    } elseif (strpos($server, 'iis') !== false) {
        $tech = 'IIS';
    } elseif (strpos($xpb, 'php') !== false) {
        $tech = 'PHP';
    } elseif (strpos($xpb, 'express') !== false || strpos($xpb, 'node') !== false) {
        $tech = 'Node.js';
    } elseif (strpos($xpb, 'asp.net') !== false) {
        $tech = 'ASP.NET';
    } elseif (strpos($xpb, 'ruby') !== false) {
        $tech = 'Ruby';
    } elseif (strpos($xpb, 'python') !== false || strpos($xpb, 'django') !== false) {
        $tech = 'Python';
    }

    $fingerprints['technology'] = $tech;

    return ['fingerprints' => $fingerprints, 'reveals_info' => $revealsInfo];
}

function check_waf_signatures(array $headers, string $html): array {
    $result = ['detected' => false, 'signatures' => []];

    $wafHeaders = [
        'cf-ray' => 'Cloudflare',
        'cf-cache-status' => 'Cloudflare',
        'x-sucuri-id' => 'Sucuri',
        'x-sucuri-cache' => 'Sucuri',
        'x-cdn' => 'CDN',
        'x-akamai-transformed' => 'Akamai',
        'x-amz-cf-id' => 'AWS CloudFront',
        'x-amz-cf-pop' => 'AWS CloudFront',
        'x-aws-waf' => 'AWS WAF',
        'x-content-security-policy' => 'WAF (CSP)',
        'x-protected-by' => 'WAF',
        'x-sucuri-block' => 'Sucuri WAF',
        'x-waf-event-id' => 'WAF',
        'x-mod-security' => 'ModSecurity',
        'server' => null,
    ];

    foreach ($wafHeaders as $header => $wafName) {
        if ($wafName !== null && isset($headers[$header])) {
            $result['detected'] = true;
            if (!in_array($wafName, $result['signatures'])) {
                $result['signatures'][] = $wafName;
            }
        }
    }

    $serverVal = strtolower($headers['server'] ?? '');
    if (strpos($serverVal, 'cloudflare') !== false) {
        $result['detected'] = true;
        if (!in_array('Cloudflare', $result['signatures'])) {
            $result['signatures'][] = 'Cloudflare';
        }
    }

    $wafBodyPatterns = [
        '/mod_security|ModSecurity/i' => 'ModSecurity',
        '/incapsula|Incapsula/i' => 'Incapsula/Imperva',
        '/AkamaiGhost|akamai/i' => 'Akamai',
        '/X-WAF|XWAF/i' => 'X-WAF',
        '/BigIP|BIG-IP|F5/i' => 'F5 BIG-IP',
        '/Barracuda/i' => 'Barracuda',
        '/FortiWeb|FortiGuard/i' => 'Fortinet',
        '/DenyAll/i' => 'DenyAll',
    ];

    foreach ($wafBodyPatterns as $pattern => $wafName) {
        if (preg_match($pattern, $html)) {
            $result['detected'] = true;
            if (!in_array($wafName, $result['signatures'])) {
                $result['signatures'][] = $wafName;
            }
        }
    }

    return $result;
}

function check_common_honeypot_paths(string $base): array {
    $paths = [];
    $honeypotPaths = ['/admin', '/wp-admin', '/wp-login.php', '/.env', '/.env.bak', '/.git', '/.git/config', '/phpmyadmin', '/phpMyAdmin', '/backup', '/db', '/database', '/debug', '/test', '/staging', '/.htaccess', '/config', '/setup', '/install', '/cgi-bin', '/server-status', '/server-info', '/wp-json', '/xmlrpc.php', '/.ssh', '/.aws', '/credentials', '/phpinfo.php'];

    foreach ($honeypotPaths as $path) {
        $ch = curl_init($base . $path);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 4,
            CURLOPT_CONNECTTIMEOUT => 2,
            CURLOPT_NOBODY => false,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
            CURLOPT_WRITEFUNCTION => function ($curl, $data) {
                return strlen($data);
            },
        ]);
        curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $finalUrl = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
        curl_close($ch);

        $statusTexts = [
            200 => 'OK', 301 => 'Moved', 302 => 'Found', 303 => 'See Other',
            304 => 'Not Modified', 307 => 'Temporary', 308 => 'Permanent',
            400 => 'Bad Request', 401 => 'Unauthorized', 403 => 'Forbidden',
            404 => 'Not Found', 405 => 'Method Not Allowed', 406 => 'Not Acceptable',
            410 => 'Gone', 429 => 'Rate Limited', 500 => 'Server Error',
            502 => 'Bad Gateway', 503 => 'Unavailable', 504 => 'Timeout',
        ];

        $pathShown = $path;
        if ($finalUrl && $finalUrl !== $base . $path) {
            $finalParsed = parse_url($finalUrl);
            $pathShown = $finalParsed['path'] ?? $path;
        }

        $paths[] = [
            'path' => $pathShown,
            'status' => $code,
            'status_text' => $statusTexts[$code] ?? 'Unknown',
        ];
    }

    return ['paths' => $paths];
}

function check_analytics(string $html): array {
    $result = ['found' => false, 'scripts' => []];

    if (empty($html)) {
        return $result;
    }

    $patterns = [
        '/google-analytics\.com|googletagmanager\.com|gtag/i' => 'Google Analytics',
        '/analytics\.js|ga\.js|urchin\.js/i' => 'Google Analytics (Legacy)',
        '/facebook\.net\/en_US\/fbevents|connect\.facebook\.net/i' => 'Facebook Pixel',
        '/hotjar\.com/i' => 'Hotjar',
        '/clarity\.ms/i' => 'Microsoft Clarity',
        '/segment\.com\/analytics/i' => 'Segment',
        '/mixpanel\.com/i' => 'Mixpanel',
        '/amplitude\.com/i' => 'Amplitude',
        '/heap\.io/i' => 'Heap Analytics',
        '/fullstory\.com/i' => 'FullStory',
        '/mouseflow\.com/i' => 'Mouseflow',
        '/cdn\.cookielaw\.org/i' => 'OneTrust (Cookie Consent)',
        '/optimizely\.com/i' => 'Optimizely',
        '/doubleclick\.net/i' => 'DoubleClick',
        '/newrelic\.com/i' => 'New Relic',
        '/sentry\.io/i' => 'Sentry',
        '/script\.bingo/i' => 'Bing UET',
        '/snap\.licdn\.com/i' => 'LinkedIn Insight',
        '/ads-twitter\.com|static\.ads-twitter/i' => 'Twitter Pixel',
        '/tiktok\.com\/i18n\/pixel/i' => 'TikTok Pixel',
        '/pinterest\.com\/ads/i' => 'Pinterest Tag',
        '/Matomo|matomo/i' => 'Matomo',
        '/plausible\.io/i' => 'Plausible',
        '/fathom\.analytics/i' => 'Fathom',
        '/umami\.is/i' => 'Umami',
    ];

    foreach ($patterns as $pattern => $name) {
        if (preg_match($pattern, $html)) {
            if (!in_array($name, $result['scripts'])) {
                $result['scripts'][] = $name;
            }
        }
    }

    $result['found'] = count($result['scripts']) > 0;
    return $result;
}