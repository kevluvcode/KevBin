<?php
require_once __DIR__ . '/../../functions.php';

start_session();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');

    if (!csrf_verify()) {
        echo json_encode(['error' => 'Invalid CSRF token.']);
        exit;
    }
    if (!rate_limit_check('csp-analyzer', 10, 60)) {
        echo json_encode(['error' => 'Rate limit reached. Please wait a moment.']);
        exit;
    }

    $url = trim((string)($_POST['url'] ?? ''));
    $csp = trim((string)($_POST['csp'] ?? ''));

    if ($url === '' && $csp === '') {
        echo json_encode(['error' => 'Please provide a URL or paste a CSP header.']);
        exit;
    }

    if ($url !== '' && $csp !== '') {
        $csp = '';
    }

    if ($url !== '') {
        $parsed = filter_var($url, FILTER_VALIDATE_URL);
        if ($parsed === false) {
            echo json_encode(['error' => 'Invalid URL. Include the protocol (http:// or https://).']);
            exit;
        }
        $scheme = strtolower((string)parse_url($parsed, PHP_URL_SCHEME));
        if (!in_array($scheme, ['http', 'https'], true)) {
            echo json_encode(['error' => 'Only http:// and https:// URLs are supported.']);
            exit;
        }
        if (!url_allowed_public($parsed)) {
            echo json_encode(['error' => 'That URL is blocked. Only public internet addresses can be inspected.']);
            exit;
        }
        if (!function_exists('curl_init')) {
            echo json_encode(['error' => 'curl is not available on this server.']);
            exit;
        }

        log_activity('tool_csp-analyzer', $parsed);

        $headers = fetch_csp_headers($parsed);
        echo json_encode($headers);
        exit;
    }

    log_activity('tool_csp-analyzer', 'raw-csp');
    echo json_encode([
        'url' => '',
        'headers' => parse_csp_string($csp),
        'raw_input' => $csp,
    ]);
    exit;
}

page_header('CSP Analyzer');

$cfg = $GLOBALS['CFG'];
?>
<style>
.csp-hidden { display: none; }
.csp-visible { display: block; }
.card-status { display: inline-flex; align-items: center; gap: 6px; font-size: .82rem; font-weight: 600; padding: 3px 10px; border-radius: 6px; }
.badge-green { background: rgba(38,208,124,.15); color: #26d07c; border: 1px solid rgba(38,208,124,.35); }
.badge-yellow { background: rgba(255,193,7,.12); color: #ffc107; border: 1px solid rgba(255,193,7,.35); }
.badge-red { background: rgba(231,76,60,.12); color: #e74c3c; border: 1px solid rgba(231,76,60,.35); }
.badge-gray { background: rgba(255,255,255,.06); color: #888; border: 1px solid rgba(255,255,255,.1); }
.strength-bar { height: 8px; border-radius: 4px; background: rgba(255,255,255,.08); overflow: hidden; margin-top: 6px; }
.strength-fill { height: 100%; border-radius: 4px; transition: width .4s ease; }
.strength-none { width: 0; }
.strength-weak { width: 20%; background: #e74c3c; }
.strength-moderate { width: 50%; background: #ffc107; }
.strength-strong { width: 75%; background: #38d07c; }
.strength-very-strong { width: 100%; background: linear-gradient(90deg, #38d07c, #00e5ff); }
.issue-card { border-left: 3px solid; padding: .75rem 1rem; margin-bottom: .5rem; border-radius: 0 8px 8px 0; background: var(--panel-2); }
.issue-critical { border-color: #e74c3c; }
.issue-warning { border-color: #ffc107; }
.issue-info { border-color: #4dabf7; }
.issue-ok { border-color: #38d07c; }
.directive-table { width: 100%; }
.directive-table th { font-size: .78rem; text-transform: uppercase; letter-spacing: .5px; color: var(--dim); border-bottom: 1px solid rgba(255,255,255,.08); padding: .5rem .6rem; }
.directive-table td { padding: .5rem .6rem; border-bottom: 1px solid rgba(255,255,255,.04); font-size: .88rem; vertical-align: top; }
.source-tag { display: inline-block; padding: 2px 8px; border-radius: 4px; margin: 2px; font-size: .8rem; font-family: monospace; }
.source-safe { background: rgba(38,208,124,.1); color: #26d07c; border: 1px solid rgba(38,208,124,.25); }
.source-unsafe { background: rgba(231,76,60,.1); color: #e74c3c; border: 1px solid rgba(231,76,60,.25); }
.source-warn { background: rgba(255,193,7,.1); color: #ffc107; border: 1px solid rgba(255,193,7,.25); }
.source-neutral { background: rgba(255,255,255,.06); color: #ccc; border: 1px solid rgba(255,255,255,.1); }
.rec-item { padding: .6rem .9rem; margin-bottom: .4rem; border-radius: 8px; background: var(--panel-2); font-size: .9rem; line-height: 1.5; }
.rec-item code { background: rgba(255,255,255,.08); padding: 2px 6px; border-radius: 4px; font-size: .82rem; }
.example-csp { background: rgba(0,0,0,.3); border: 1px solid rgba(255,255,255,.08); border-radius: 8px; padding: 1rem; font-family: monospace; font-size: .82rem; white-space: pre-wrap; word-break: break-all; line-height: 1.7; max-height: 300px; overflow-y: auto; }
.tab-btn { cursor: pointer; padding: .5rem 1rem; border-radius: 8px 8px 0 0; background: transparent; border: none; color: var(--dim); font-size: .85rem; font-weight: 500; transition: all .2s; }
.tab-btn.active { background: var(--panel-2); color: #fff; }
.tab-content { display: none; }
.tab-content.active { display: block; }
.spinner-border-sm { width: 1rem; height: 1rem; border-width: .15em; }
.input-toggle { display: flex; gap: 0; margin-bottom: .75rem; }
.input-toggle button { flex: 1; padding: .5rem; border: 1px solid rgba(255,255,255,.1); background: transparent; color: var(--dim); font-size: .85rem; cursor: pointer; transition: all .2s; }
.input-toggle button:first-child { border-radius: 8px 0 0 8px; }
.input-toggle button:last-child { border-radius: 0 8px 8px 0; }
.input-toggle button.active { background: rgba(88,101,242,.2); color: #886af6; border-color: rgba(88,101,242,.4); }
</style>

<div class="container" style="max-width: 1000px;">
    <h1 class="h4 mb-1 reveal in-view">&#128737; CSP Analyzer</h1>
    <p class="text-secondary mb-3 reveal in-view">Inspect Content-Security-Policy headers from any URL or analyze a raw CSP string. Identifies weaknesses, security issues, and provides hardening recommendations.</p>

    <div class="card mb-4 reveal in-view"><div class="card-body">
        <form id="cspForm">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <div class="input-toggle">
                <button type="button" class="active" data-mode="url">URL Lookup</button>
                <button type="button" data-mode="paste">Paste CSP Header</button>
            </div>
            <div id="inputUrl" class="row g-2 align-items-center">
                <div class="col-md-8">
                    <input class="form-control" name="url" id="cspUrl" maxlength="512" placeholder="https://example.com">
                </div>
                <div class="col-md-4">
                    <button class="btn btn-primary w-100" type="submit" id="cspBtn">
                        <span id="cspBtnLabel">Analyze</span>
                        <span id="cspBtnSpin" class="d-none"><span class="spinner-border spinner-border-sm" role="status"></span></span>
                    </button>
                </div>
            </div>
            <div id="inputPaste" class="csp-hidden">
                <textarea class="form-control" name="csp" id="cspRaw" rows="4" placeholder="default-src 'self'; script-src 'self' https://cdn.example.com; style-src 'self' 'unsafe-inline'" style="font-family:monospace; font-size:.85rem;"></textarea>
                <div class="mt-2 text-end">
                    <button class="btn btn-primary" type="submit" id="cspBtn2">
                        <span id="cspBtnLabel2">Analyze</span>
                        <span id="cspBtnSpin2" class="d-none"><span class="spinner-border spinner-border-sm" role="status"></span></span>
                    </button>
                </div>
            </div>
        </form>
        <div id="cspError" class="alert alert-danger small mt-3 mb-0 d-none"></div>
    </div></div>

    <div id="cspResults" class="csp-hidden">
        <div class="card mb-3 reveal in-view"><div class="card-body">
            <h2 class="h6 mb-2">Overview</h2>
            <div class="row g-3" id="overviewCards"></div>
            <div id="strengthMeter" class="mt-3"></div>
        </div></div>

        <div class="card mb-3 reveal in-view"><div class="card-body">
            <div class="d-flex gap-2 mb-3" id="analysisTabs">
                <button class="tab-btn active" data-tab="issues">Security Issues</button>
                <button class="tab-btn" data-tab="directives">Directive Breakdown</button>
                <button class="tab-btn" data-tab="recommendations">Recommendations</button>
            </div>
            <div id="tab-issues" class="tab-content active"></div>
            <div id="tab-directives" class="tab-content"></div>
            <div id="tab-recommendations" class="tab-content"></div>
        </div></div>

        <div id="reportOnlySection" class="card mb-3 reveal in-view d-none"><div class="card-body">
            <h2 class="h6 mb-2">Report-Only Mode</h2>
            <div id="reportOnlyContent"></div>
        </div></div>
    </div>
</div>

<script>
(function () {
    var form = document.getElementById('cspForm');
    var errBox = document.getElementById('cspError');
    var results = document.getElementById('cspResults');
    var mode = 'url';
    var toggleBtns = document.querySelectorAll('.input-toggle button');
    var inputUrl = document.getElementById('inputUrl');
    var inputPaste = document.getElementById('inputPaste');

    toggleBtns.forEach(function (btn) {
        btn.addEventListener('click', function () {
            toggleBtns.forEach(function (b) { b.classList.remove('active'); });
            btn.classList.add('active');
            mode = btn.dataset.mode;
            if (mode === 'url') {
                inputUrl.classList.remove('csp-hidden');
                inputPaste.classList.add('csp-hidden');
            } else {
                inputUrl.classList.add('csp-hidden');
                inputPaste.classList.remove('csp-hidden');
            }
        });
    });

    var tabs = document.querySelectorAll('.tab-btn');
    tabs.forEach(function (tab) {
        tab.addEventListener('click', function () {
            tabs.forEach(function (t) { t.classList.remove('active'); });
            tab.classList.add('active');
            document.querySelectorAll('.tab-content').forEach(function (c) { c.classList.remove('active'); });
            document.getElementById('tab-' + tab.dataset.tab).classList.add('active');
        });
    });

    form.addEventListener('submit', function (e) {
        e.preventDefault();
        errBox.classList.add('d-none');
        results.classList.add('csp-hidden');
        setSpin(true);

        var fd = new FormData(form);
        fetch('index.php', { method: 'POST', body: fd })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                if (d.error) {
                    errBox.textContent = d.error;
                    errBox.classList.remove('d-none');
                    setSpin(false);
                    return;
                }
                var analysis = analyzeCSP(d);
                renderAnalysis(analysis, d);
                results.classList.remove('csp-hidden');
                setSpin(false);
                results.querySelectorAll('.reveal').forEach(function (el) {
                    el.classList.add('in-view');
                });
            })
            .catch(function () {
                errBox.textContent = 'Network error. Try again.';
                errBox.classList.remove('d-none');
                setSpin(false);
            });
    });

    function setSpin(on) {
        var ids = [['cspBtn', 'cspBtnLabel', 'cspBtnSpin'], ['cspBtn2', 'cspBtnLabel2', 'cspBtnSpin2']];
        ids.forEach(function (g) {
            document.getElementById(g[0]).disabled = on;
            document.getElementById(g[1]).textContent = on ? 'Analyzing...' : 'Analyze';
            document.getElementById(g[2]).classList[on ? 'remove' : 'add']('d-none');
        });
    }

    var ALL_DIRECTIVES = [
        'default-src','script-src','style-src','img-src','font-src','connect-src',
        'media-src','object-src','frame-src','child-src','worker-src','manifest-src',
        'base-uri','form-action','frame-ancestors'
    ];
    var BOOL_DIRECTIVES = ['upgrade-insecure-requests','block-all-mixed-content'];

    function parseCSP(raw) {
        if (!raw || !raw.trim()) return null;
        var directives = {};
        var parts = raw.split(';').map(function (s) { return s.trim(); }).filter(function (s) { return s.length > 0; });
        parts.forEach(function (part) {
            var tokens = part.split(/\s+/);
            if (tokens.length === 0) return;
            var name = tokens[0].toLowerCase();
            var values = tokens.slice(1);
            if (BOOL_DIRECTIVES.indexOf(name) !== -1) {
                directives[name] = [];
            } else {
                directives[name] = values;
            }
        });
        return directives;
    }

    function analyzeCSP(data) {
        var cspHeaders = data.headers || {};
        var mainCsp = cspHeaders['content-security-policy'] || '';
        var reportOnly = cspHeaders['content-security-policy-report-only'] || '';
        var legacyCsp = cspHeaders['x-content-security-policy'] || '';

        var directives = parseCSP(mainCsp);
        var reportDirectives = parseCSP(reportOnly);

        var present = [];
        var missing = [];
        var issues = [];
        var directiveDetails = [];

        if (!mainCsp && !reportOnly && !legacyCsp) {
            issues.push({ level: 'critical', title: 'No CSP Header Found', desc: 'This site does not send any Content-Security-Policy header. Without CSP, the browser has no restrictions on resource loading, making XSS and data injection attacks easier.' });
            return {
                present: [],
                missing: ALL_DIRECTIVES.slice(),
                boolPresent: [],
                issues: issues,
                directiveDetails: [],
                strength: 'none',
                strengthLabel: 'None',
                mainCsp: '',
                reportOnly: reportOnly,
                reportDirectives: reportDirectives,
                legacyCsp: legacyCsp,
                directiveCount: 0,
                totalDirectives: ALL_DIRECTIVES.length + BOOL_DIRECTIVES.length
            };
        }

        if (legacyCsp) {
            issues.push({ level: 'info', title: 'Legacy X-Content-Security-Policy Header', desc: 'An X-Content-Security-Policy header was found. This is an obsolete IE header and should not be relied upon. Use the standard Content-Security-Policy header instead.' });
        }

        if (directives) {
            var keys = Object.keys(directives);
            ALL_DIRECTIVES.forEach(function (d) {
                if (directives.hasOwnProperty(d)) {
                    present.push(d);
                } else {
                    missing.push(d);
                }
            });

            var boolPresent = [];
            BOOL_DIRECTIVES.forEach(function (d) {
                if (directives.hasOwnProperty(d)) {
                    boolPresent.push(d);
                }
            });

            if (!directives.hasOwnProperty('script-src')) {
                issues.push({ level: 'warning', title: 'Missing script-src Directive', desc: 'No script-src directive found. Scripts fall back to default-src, which may be too permissive for inline script protection.' });
            }

            keys.forEach(function (name) {
                if (ALL_DIRECTIVES.indexOf(name) !== -1) {
                    var values = directives[name] || [];
                    var detail = { name: name, values: [], statuses: [] };
                    if (values.length === 0) {
                        detail.values.push("'none'");
                        detail.statuses.push('safe');
                    } else {
                        values.forEach(function (v) {
                            var status = 'safe';
                            var vl = v.toLowerCase();
                            if (vl === "'unsafe-inline'") {
                                status = 'unsafe';
                                if (name === 'script-src') {
                                    issues.push({ level: 'critical', title: "'unsafe-inline' in script-src", desc: 'Allows inline JavaScript execution. This significantly weakens XSS protection. Use nonces or hashes instead.' });
                                } else if (name === 'style-src') {
                                    issues.push({ level: 'warning', title: "'unsafe-inline' in style-src", desc: 'Allows inline styles. While less risky than inline scripts, it can still be exploited in some XSS scenarios.' });
                                }
                            } else if (vl === "'unsafe-eval'") {
                                status = 'unsafe';
                                issues.push({ level: 'critical', title: "'unsafe-eval' in " + name, desc: 'Allows eval()-like code execution. This dramatically increases XSS attack surface. Avoid eval() and use alternatives.' });
                            } else if (vl === "'unsafe-hashes'") {
                                status = 'warn';
                                issues.push({ level: 'warning', title: "'unsafe-hashes' in " + name, desc: 'Uses unsafe-hashes. While better than unsafe-inline, it limits CSP flexibility. Consider using nonces instead.' });
                            } else if (vl === '*' || vl === 'http:' || vl === 'https:' || vl === 'data:' || vl === 'blob:') {
                                if (vl === '*') {
                                    status = 'unsafe';
                                    if (name === 'script-src') {
                                        issues.push({ level: 'critical', title: "Wildcard '*' in script-src", desc: 'Allows scripts from any origin. This effectively disables script CSP protection.' });
                                    } else {
                                        issues.push({ level: 'warning', title: "Wildcard '*' in " + name, desc: 'Wildcard source allows any origin. Consider restricting to specific trusted domains.' });
                                    }
                                } else if (vl === 'data:' && name === 'script-src') {
                                    status = 'unsafe';
                                    issues.push({ level: 'critical', title: "'data:' URI in script-src", desc: 'Allows scripts via data: URIs, which can be used to bypass CSP and execute arbitrary code.' });
                                } else if (vl === 'blob:' && name === 'script-src') {
                                    status = 'unsafe';
                                    issues.push({ level: 'critical', title: "'blob:' URI in script-src", desc: 'Allows scripts via blob: URIs. Combined with XSS, this can be exploited for code execution.' });
                                } else {
                                    status = 'warn';
                                }
                            } else if (vl === "'self'" || vl === 'none') {
                                status = 'safe';
                            } else if (vl.indexOf("'nonce-") === 0 || vl.indexOf("'sha256-") === 0 || vl.indexOf("'sha384-") === 0 || vl.indexOf("'sha512-") === 0) {
                                status = 'safe';
                            } else if (vl.indexOf('http') === 0 || vl.indexOf('//') === 0) {
                                status = 'safe';
                            }
                            detail.values.push(v);
                            detail.statuses.push(status);
                        });
                    }
                    directiveDetails.push(detail);
                }
            });

            if (!directives.hasOwnProperty('report-uri') && !directives.hasOwnProperty('report-to') && !reportOnly) {
                issues.push({ level: 'info', title: 'No Report Endpoint Configured', desc: 'Neither report-uri nor report-to is set, and no CSP-Report-Only header was found. Consider adding violation reporting to monitor policy breaches.' });
            }
        }

        var critCount = issues.filter(function (i) { return i.level === 'critical'; }).length;
        var warnCount = issues.filter(function (i) { return i.level === 'warning'; }).length;
        var hasScriptSrc = directives && directives.hasOwnProperty('script-src');
        var hasDefaultSrc = directives && directives.hasOwnProperty('default-src');

        var score = 0;
        if (mainCsp) score += 1;
        score += present.length * 0.5;
        if (hasScriptSrc && !hasUnsafe(directives['script-src'])) score += 2;
        if (directives && directives.hasOwnProperty('object-src')) score += 0.5;
        if (directives && directives.hasOwnProperty('base-uri')) score += 0.5;
        if (directives && directives.hasOwnProperty('form-action')) score += 0.5;
        if (directives && directives.hasOwnProperty('frame-ancestors')) score += 0.5;
        if (critCount > 0) score -= critCount * 1.5;
        if (warnCount > 0) score -= warnCount * 0.5;
        if (hasNonceOrHash(directives)) score += 1;

        var strength, strengthLabel;
        if (score <= 0.5) { strength = 'none'; strengthLabel = 'None'; }
        else if (score <= 2) { strength = 'weak'; strengthLabel = 'Weak'; }
        else if (score <= 4) { strength = 'moderate'; strengthLabel = 'Moderate'; }
        else if (score <= 6) { strength = 'strong'; strengthLabel = 'Strong'; }
        else { strength = 'very-strong'; strengthLabel = 'Very Strong'; }

        return {
            present: present,
            missing: missing,
            boolPresent: boolPresent || [],
            issues: issues,
            directiveDetails: directiveDetails,
            strength: strength,
            strengthLabel: strengthLabel,
            mainCsp: mainCsp,
            reportOnly: reportOnly,
            reportDirectives: reportDirectives,
            legacyCsp: legacyCsp,
            directiveCount: present.length + (boolPresent ? boolPresent.length : 0),
            totalDirectives: ALL_DIRECTIVES.length + BOOL_DIRECTIVES.length
        };
    }

    function hasUnsafe(arr) {
        if (!arr) return false;
        return arr.some(function (v) {
            var vl = v.toLowerCase();
            return vl === "'unsafe-inline'" || vl === "'unsafe-eval'" || vl === '*';
        });
    }

    function hasNonceOrHash(dirs) {
        if (!dirs) return false;
        var vals = dirs['script-src'] || [];
        return vals.some(function (v) {
            return v.indexOf("'nonce-") === 0 || v.indexOf("'sha") === 0;
        });
    }

    function esc(s) {
        var d = document.createElement('div');
        d.appendChild(document.createTextNode(s || ''));
        return d.innerHTML;
    }

    function renderAnalysis(a, data) {
        renderOverview(a);
        renderIssues(a);
        renderDirectives(a);
        renderRecommendations(a);
        renderReportOnly(a);
    }

    function renderOverview(a) {
        var cardsHtml = '';
        cardsHtml += '<div class="col-md-3"><div class="card"><div class="card-body text-center">';
        cardsHtml += '<div class="text-secondary small">CSP Status</div>';
        cardsHtml += '<div style="font-size:1.5rem;font-weight:700;" class="' + (a.mainCsp ? 'text-success' : 'text-danger') + '">' + (a.mainCsp ? 'Present' : 'Missing') + '</div>';
        cardsHtml += '</div></div></div>';

        cardsHtml += '<div class="col-md-3"><div class="card"><div class="card-body text-center">';
        cardsHtml += '<div class="text-secondary small">Directives Set</div>';
        cardsHtml += '<div style="font-size:1.5rem;font-weight:700;">' + a.directiveCount + ' <span class="text-secondary" style="font-size:.9rem;">/ ' + a.totalDirectives + '</span></div>';
        cardsHtml += '</div></div></div>';

        cardsHtml += '<div class="col-md-3"><div class="card"><div class="card-body text-center">';
        cardsHtml += '<div class="text-secondary small">Issues Found</div>';
        var crits = a.issues.filter(function (i) { return i.level === 'critical'; }).length;
        var warns = a.issues.filter(function (i) { return i.level === 'warning'; }).length;
        var issueColor = crits > 0 ? 'text-danger' : (warns > 0 ? 'text-warning' : 'text-success');
        cardsHtml += '<div style="font-size:1.5rem;font-weight:700;" class="' + issueColor + '">' + a.issues.length + '</div>';
        if (a.issues.length > 0) {
            cardsHtml += '<div class="text-secondary small">' + crits + ' critical, ' + warns + ' warnings</div>';
        }
        cardsHtml += '</div></div></div>';

        cardsHtml += '<div class="col-md-3"><div class="card"><div class="card-body text-center">';
        cardsHtml += '<div class="text-secondary small">Missing Directives</div>';
        cardsHtml += '<div style="font-size:1.5rem;font-weight:700;" class="' + (a.missing.length > 5 ? 'text-warning' : 'text-success') + '">' + a.missing.length + '</div>';
        cardsHtml += '</div></div></div>';

        document.getElementById('overviewCards').innerHTML = cardsHtml;

        var strengthMap = { 'none': 'No CSP', 'weak': 'Weak', 'moderate': 'Moderate', 'strong': 'Strong', 'very-strong': 'Very Strong' };
        var colorMap = { 'none': '#e74c3c', 'weak': '#e74c3c', 'moderate': '#ffc107', 'strong': '#38d07c', 'very-strong': '#00e5ff' };
        var smHtml = '<div class="d-flex justify-content-between align-items-center mb-1">';
        smHtml += '<span class="small fw-bold">CSP Strength: <span style="color:' + colorMap[a.strength] + '">' + a.strengthLabel + '</span></span>';
        smHtml += '</div>';
        smHtml += '<div class="strength-bar"><div class="strength-fill strength-' + a.strength + '"></div></div>';
        document.getElementById('strengthMeter').innerHTML = smHtml;
    }

    function renderIssues(a) {
        var html = '';
        if (a.issues.length === 0) {
            html += '<div class="issue-card issue-ok"><strong class="text-success">No critical issues found.</strong> <span class="text-secondary">Your CSP configuration looks good.</span></div>';
        } else {
            a.issues.forEach(function (issue) {
                var cls = issue.level === 'critical' ? 'issue-critical' : (issue.level === 'warning' ? 'issue-warning' : 'issue-info');
                var icon = issue.level === 'critical' ? '&#9888;' : (issue.level === 'warning' ? '&#9888;' : '&#8505;');
                var badge = issue.level === 'critical' ? 'badge-red' : (issue.level === 'warning' ? 'badge-yellow' : 'badge-gray');
                html += '<div class="issue-card ' + cls + '">';
                html += '<div class="d-flex align-items-center gap-2 mb-1">';
                html += '<span class="card-status ' + badge + '">' + icon + ' ' + esc(issue.level.charAt(0).toUpperCase() + issue.level.slice(1)) + '</span>';
                html += '<strong>' + esc(issue.title) + '</strong>';
                html += '</div>';
                html += '<div class="text-secondary small">' + esc(issue.desc) + '</div>';
                html += '</div>';
            });
        }
        document.getElementById('tab-issues').innerHTML = html;
    }

    function renderDirectives(a) {
        var html = '';
        if (a.directiveDetails.length === 0) {
            html += '<div class="text-secondary small">No directives to display.</div>';
        } else {
            html += '<div style="overflow-x:auto;"><table class="directive-table">';
            html += '<thead><tr><th>Directive</th><th>Values</th><th>Status</th><th>Explanation</th></tr></thead><tbody>';
            a.directiveDetails.forEach(function (d) {
                var allSafe = d.statuses.every(function (s) { return s === 'safe'; });
                var hasUnsafe = d.statuses.some(function (s) { return s === 'unsafe'; });
                var rowStatus = hasUnsafe ? 'badge-red' : (allSafe ? 'badge-green' : 'badge-yellow');
                var rowStatusText = hasUnsafe ? 'Insecure' : (allSafe ? 'Secure' : 'Caution');

                var valuesHtml = '';
                d.values.forEach(function (v, i) {
                    var s = d.statuses[i];
                    var cls = s === 'safe' ? 'source-safe' : (s === 'unsafe' ? 'source-unsafe' : (s === 'warn' ? 'source-warn' : 'source-neutral'));
                    valuesHtml += '<span class="source-tag ' + cls + '">' + esc(v) + '</span>';
                });

                html += '<tr>';
                html += '<td><code class="fw-bold">' + esc(d.name) + '</code></td>';
                html += '<td>' + valuesHtml + '</td>';
                html += '<td><span class="card-status ' + rowStatus + '">' + rowStatusText + '</span></td>';
                html += '<td class="text-secondary small">' + esc(explainDirective(d.name, d.values, d.statuses)) + '</td>';
                html += '</tr>';
            });
            html += '</tbody></table></div>';
        }
        document.getElementById('tab-directives').innerHTML = html;
    }

    function explainDirective(name, values, statuses) {
        var explanations = {
            'default-src': 'Fallback for any directive not explicitly set. Should be restrictive.',
            'script-src': 'Controls which scripts can execute. Most critical directive for XSS prevention.',
            'style-src': 'Controls which stylesheets can be loaded. Less critical but still important.',
            'img-src': 'Controls image sources. Lower risk but can be used for tracking.',
            'font-src': 'Controls font loading sources.',
            'connect-src': 'Controls AJAX, WebSocket, and EventSource connections.',
            'media-src': 'Controls audio and video element sources.',
            'object-src': 'Controls plugin sources (Flash, Java). Should usually be set to \'none\'.',
            'frame-src': 'Controls iframe sources. Important for clickjacking prevention.',
            'child-src': 'Legacy directive for workers and frames. Use worker-src and frame-src instead.',
            'worker-src': 'Controls Web Worker and Service Worker sources.',
            'manifest-src': 'Controls web app manifest sources.',
            'base-uri': 'Controls the allowed URLs for the <base> element. Should be \'self\' or \'none\'.',
            'form-action': 'Controls where forms can submit data to.',
            'frame-ancestors': 'Controls who can embed this page in iframes. Replaces X-Frame-Options.',
            'upgrade-insecure-requests': 'Automatically upgrades HTTP requests to HTTPS.',
            'block-all-mixed-content': 'Blocks all mixed (HTTP on HTTPS) content.'
        };
        var base = explanations[name] || 'CSP directive.';
        var hasUnsafe = statuses.some(function (s) { return s === 'unsafe'; });
        if (hasUnsafe) {
            return base + ' WARNING: Contains insecure source values.';
        }
        return base;
    }

    function renderRecommendations(a) {
        var recs = [];
        if (!a.mainCsp) {
            recs.push('Deploy a Content-Security-Policy header. Start with a report-only mode to test before enforcing.');
        }
        if (a.missing.indexOf('script-src') !== -1) {
            recs.push('Add a script-src directive. Use nonces or hashes for inline scripts: <code>script-src \'self\' \'nonce-{random}\'</code>');
        }
        if (a.missing.indexOf('object-src') !== -1) {
            recs.push('Add <code>object-src \'none\'</code> to block plugins like Flash and Java.');
        }
        if (a.missing.indexOf('base-uri') !== -1) {
            recs.push('Add <code>base-uri \'self\'</code> to prevent base tag hijacking.');
        }
        if (a.missing.indexOf('form-action') !== -1) {
            recs.push('Add <code>form-action \'self\'</code> to control where forms can submit.');
        }
        if (a.missing.indexOf('frame-ancestors') !== -1) {
            recs.push('Add <code>frame-ancestors \'self\'</code> to prevent clickjacking (or use CSP level 3 report-only).');
        }
        if (a.missing.indexOf('default-src') !== -1 && a.missing.indexOf('script-src') === -1) {
            recs.push('Add a restrictive <code>default-src \'none\'</code> to ensure all unspecified directives block resources.');
        }
        var hasScriptUnsafe = a.directiveDetails.some(function (d) {
            return d.name === 'script-src' && d.statuses.some(function (s) { return s === 'unsafe'; });
        });
        if (hasScriptUnsafe) {
            recs.push('Remove unsafe-inline and unsafe-eval from script-src. Use nonces, hashes, or external scripts.');
        }
        if (a.boolPresent.indexOf('block-all-mixed-content') === -1 && a.boolPresent.indexOf('upgrade-insecure-requests') === -1) {
            recs.push('Consider adding <code>upgrade-insecure-requests</code> to auto-upgrade HTTP to HTTPS.');
        }
        if (!a.reportOnly && a.mainCsp) {
            recs.push('Consider deploying CSP in <code>Content-Security-Policy-Report-Only</code> mode first to catch violations without breaking functionality.');
        }
        if (recs.length === 0) {
            recs.push('Your CSP is well-configured. Continue monitoring with report-uri and review periodically.');
        }

        var html = '';
        recs.forEach(function (r) {
            html += '<div class="rec-item">' + r + '</div>';
        });

        html += '<div class="mt-3"><strong class="small">Example Hardened CSP:</strong></div>';
        var example = "default-src 'none';\nscript-src 'self' 'nonce-{random}' https://trusted.cdn.com;\nstyle-src 'self' 'nonce-{random}';\nimg-src 'self' data: https:;\nfont-src 'self';\nconnect-src 'self' https://api.example.com;\nobject-src 'none';\nbase-uri 'self';\nform-action 'self';\nframe-ancestors 'self';\nupgrade-insecure-requests;";
        html += '<div class="example-csp mt-2">' + esc(example) + '</div>';

        document.getElementById('tab-recommendations').innerHTML = html;
    }

    function renderReportOnly(a) {
        var section = document.getElementById('reportOnlySection');
        var content = document.getElementById('reportOnlyContent');
        if (!a.reportOnly) {
            section.classList.add('d-none');
            return;
        }
        section.classList.remove('d-none');

        var html = '<div class="issue-card issue-info mb-3">';
        html += '<strong>What is CSP-Report-Only?</strong> ';
        html += '<span class="text-secondary small">The browser will report policy violations without blocking resources. This allows you to test your CSP before enforcement.</span>';
        html += '</div>';

        html += '<div class="mb-2"><strong class="small">Report-Only CSP Header:</strong></div>';
        html += '<div class="example-csp mb-3">' + esc(a.reportOnly) + '</div>';

        if (a.reportDirectives && Object.keys(a.reportDirectives).length > 0) {
            html += '<div class="mb-2"><strong class="small">Parsed Report-Only Directives:</strong></div>';
            html += '<table class="directive-table"><thead><tr><th>Directive</th><th>Values</th></tr></thead><tbody>';
            Object.keys(a.reportDirectives).forEach(function (name) {
                var vals = a.reportDirectives[name];
                var valStr = vals.length > 0 ? vals.join(' ') : '<em class="text-secondary">none</em>';
                html += '<tr><td><code>' + esc(name) + '</code></td><td><code>' + valStr + '</code></td></tr>';
            });
            html += '</tbody></table>';
        }

        content.innerHTML = html;
    }
})();
</script>
<?php page_footer(); ?>

<?php
function fetch_csp_headers(string $url): array
{
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 5,
        CURLOPT_NOBODY => false,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/126.0.0.0 Safari/537.36',
        CURLOPT_URL => $url,
    ]);

    $rawHeaders = [];
    curl_setopt($ch, CURLOPT_HEADERFUNCTION, function ($curl, $header) use (&$rawHeaders) {
        $rawHeaders[] = rtrim($header);
        return strlen($header);
    });

    curl_exec($ch);
    curl_close($ch);

    $parsed = [];
    $parsed['content-security-policy'] = '';
    $parsed['content-security-policy-report-only'] = '';
    $parsed['x-content-security-policy'] = '';

    foreach ($rawHeaders as $line) {
        $lower = strtolower($line);
        if (preg_match('#^content-security-policy\s*:\s*(.+)$#i', $line, $m)) {
            $parsed['content-security-policy'] = trim($m[1]);
        } elseif (preg_match('#^content-security-policy-report-only\s*:\s*(.+)$#i', $line, $m)) {
            $parsed['content-security-policy-report-only'] = trim($m[1]);
        } elseif (preg_match('#^x-content-security-policy\s*:\s*(.+)$#i', $line, $m)) {
            $parsed['x-content-security-policy'] = trim($m[1]);
        }
    }

    return [
        'url' => $url,
        'headers' => $parsed,
    ];
}

function parse_csp_string(string $raw): array
{
    $normalized = preg_replace('#\s+#', ' ', trim($raw));
    $normalized = preg_replace('#;\s*#', ';', $normalized);
    $normalized = strtolower($normalized);

    return [
        'content-security-policy' => $normalized,
        'content-security-policy-report-only' => '',
        'x-content-security-policy' => '',
    ];
}
