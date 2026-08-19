<?php
require_once __DIR__ . '/../../functions.php';

start_session();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['url'])) {
    header('Content-Type: application/json');

    if (!csrf_verify()) {
        echo json_encode(['error' => 'Invalid CSRF token.']);
        exit;
    }
    if (!rate_limit_check('corstester', 10, 60)) {
        echo json_encode(['error' => 'Rate limit reached. Wait a moment.']);
        exit;
    }

    $url = trim((string)($_POST['url'] ?? ''));
    $origin = trim((string)($_POST['origin'] ?? ''));
    $method = strtoupper(trim((string)($_POST['method'] ?? 'GET')));
    $customHeaders = trim((string)($_POST['headers'] ?? ''));
    $body = (string)($_POST['body'] ?? '');

    if ($url === '') {
        echo json_encode(['error' => 'URL is required.']);
        exit;
    }

    if (!in_array($method, ['GET', 'POST', 'PUT', 'DELETE', 'OPTIONS'], true)) {
        echo json_encode(['error' => 'Invalid HTTP method.']);
        exit;
    }

    $parsed = filter_var($url, FILTER_VALIDATE_URL);
    if ($parsed === false) {
        echo json_encode(['error' => 'Invalid URL. Include the protocol (https://).']);
        exit;
    }

    $scheme = strtolower((string)parse_url($parsed, PHP_URL_SCHEME));
    if (!in_array($scheme, ['http', 'https'], true)) {
        echo json_encode(['error' => 'Only http:// and https:// URLs are allowed.']);
        exit;
    }

    if (!function_exists('curl_init')) {
        echo json_encode(['error' => 'cURL is not available on this server.']);
        exit;
    }

    $headers = [];
    if ($origin !== '') {
        $headers[] = 'Origin: ' . $origin;
    }
    if ($customHeaders !== '') {
        $lines = explode("\n", $customHeaders);
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line !== '' && strpos($line, ':') !== false) {
                $headers[] = $line;
            }
        }
    }

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $parsed,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HEADER => false,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => 0,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/126.0.0.0 Safari/537.36',
    ]);

    if (!empty($headers)) {
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    }

    if (in_array($method, ['POST', 'PUT'], true) && $body !== '') {
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
    }

    $responseHeaders = [];
    curl_setopt($ch, CURLOPT_HEADERFUNCTION, function ($ch, $line) use (&$responseHeaders) {
        $responseHeaders[] = rtrim($line);
        return strlen($line);
    });

    $responseBody = curl_exec($ch);
    $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($responseBody === false) {
        echo json_encode(['error' => 'Request failed: ' . ($curlError ?: 'Unknown error')]);
        exit;
    }

    log_activity('tool_corstester', $parsed);

    $parsedHeaders = [];
    foreach ($responseHeaders as $h) {
        if (preg_match('#^HTTP/[\d.]+\s+(\d+)\s*(.*)$#i', $h, $m)) {
            $parsedHeaders['_status_line'] = $m[0];
            $httpCode = (int)$m[1];
        } elseif (strpos($h, ':') !== false) {
            $colonPos = strpos($h, ':');
            $key = trim(substr($h, 0, $colonPos));
            $val = trim(substr($h, $colonPos + 1));
            if (!isset($parsedHeaders[$key])) {
                $parsedHeaders[$key] = $val;
            } else {
                if (!is_array($parsedHeaders[$key])) {
                    $parsedHeaders[$key] = [$parsedHeaders[$key]];
                }
                $parsedHeaders[$key][] = $val;
            }
        }
    }

    echo json_encode([
        'success' => true,
        'url' => $parsed,
        'origin_sent' => $origin,
        'method' => $method,
        'status' => $httpCode,
        'headers' => $parsedHeaders,
        'body' => mb_substr($responseBody, 0, 2000),
    ]);
    exit;
}

page_header('CORS Tester');
?>
<style>
    .cors-result { display: none; }
    .cors-result.visible { display: block; }
    .cors-highlight { background: rgba(88, 101, 242, .12); border: 1px solid rgba(88, 101, 242, .35); border-radius: 8px; padding: .75rem 1rem; margin-bottom: .5rem; }
    .cors-header-name { font-weight: 600; color: var(--accent1); font-size: .82rem; }
    .cors-header-val { color: var(--text); font-size: .9rem; word-break: break-all; }
    .cors-header-missing { color: var(--dim); font-style: italic; }
    .status-code-badge { display: inline-block; font-weight: 700; font-size: 1.1rem; padding: .25rem .75rem; border-radius: 6px; }
    .status-2xx { background: rgba(38, 208, 124, .15); color: #26d07c; border: 1px solid rgba(38, 208, 124, .35); }
    .status-3xx { background: rgba(88, 101, 242, .15); color: #656efc; border: 1px solid rgba(88, 101, 242, .35); }
    .status-4xx { background: rgba(255, 193, 7, .15); color: #ffc107; border: 1px solid rgba(255, 193, 7, .35); }
    .status-5xx { background: rgba(231, 76, 60, .15); color: #e74c3c; border: 1px solid rgba(231, 76, 60, .35); }
    .assess-row { display: flex; justify-content: space-between; align-items: center; padding: .4rem 0; border-bottom: 1px solid rgba(255,255,255,.04); }
    .assess-row:last-child { border-bottom: none; }
    .assess-label { color: var(--dim); font-size: .82rem; }
    .assess-value { font-size: .88rem; font-weight: 500; text-align: right; }
    .rating-badge { display: inline-block; font-weight: 700; font-size: .95rem; padding: .3rem .85rem; border-radius: 6px; }
    .rating-secure { background: rgba(38, 208, 124, .15); color: #26d07c; border: 1px solid rgba(38, 208, 124, .35); }
    .rating-misconfigured { background: rgba(255, 193, 7, .15); color: #ffc107; border: 1px solid rgba(255, 193, 7, .35); }
    .rating-dangerous { background: rgba(231, 76, 60, .15); color: #e74c3c; border: 1px solid rgba(231, 76, 60, .35); }
    .prefill-chip { display: inline-block; background: var(--panel-2); border: 1px solid var(--line); border-radius: 6px; padding: 4px 10px; font-size: .78rem; cursor: pointer; transition: all .15s; }
    .prefill-chip:hover { border-color: var(--accent1); color: var(--accent1); }
    .body-area, .headers-area { font-family: monospace; font-size: .85rem; }
    .spinner-border-sm { width: 1rem; height: 1rem; border-width: .15em; }
</style>

<div class="container" style="max-width: 960px;">
    <h1 class="h4 mb-1 reveal in-view">&#127760; CORS Tester</h1>
    <p class="text-secondary mb-3 reveal in-view">Test Cross-Origin Resource Sharing policies on any endpoint. Sends a real request with a custom Origin header and inspects the CORS response headers.</p>

    <div class="card reveal in-view"><div class="card-body">
        <form id="corsForm" class="mb-0">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">

            <div class="row g-2 mb-2">
                <div class="col-md-9">
                    <label class="form-label small text-secondary">Target URL</label>
                    <input class="form-control" name="url" id="corsUrl" maxlength="2048" placeholder="https://api.example.com/data" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label small text-secondary">Method</label>
                    <select class="form-select" name="method" id="corsMethod">
                        <option value="GET">GET</option>
                        <option value="POST">POST</option>
                        <option value="PUT">PUT</option>
                        <option value="DELETE">DELETE</option>
                        <option value="OPTIONS">OPTIONS</option>
                    </select>
                </div>
            </div>

            <div class="row g-2 mb-2">
                <div class="col-md-12">
                    <label class="form-label small text-secondary">Origin Header <span class="text-secondary">(sent as Origin)</span></label>
                    <input class="form-control" name="origin" id="corsOrigin" maxlength="512" value="https://kevbin.ct.ws" placeholder="https://example.com">
                </div>
            </div>

            <div class="mb-2">
                <label class="form-label small text-secondary">Custom Headers <span class="text-secondary">(one per line, header: value)</span></label>
                <textarea class="form-control headers-area" name="headers" id="corsHeaders" rows="3" placeholder="Authorization: Bearer token123&#10;X-Custom: value"></textarea>
            </div>

            <div class="mb-3">
                <label class="form-label small text-secondary">Request Body <span class="text-secondary">(for POST / PUT)</span></label>
                <textarea class="form-control body-area" name="body" id="corsBody" rows="3" placeholder='{"key": "value"}'></textarea>
            </div>

            <button class="btn btn-primary w-100" type="submit" id="corsBtn">
                <span id="corsBtnLabel">Send Request</span>
                <span id="corsBtnSpin" class="d-none"><span class="spinner-border spinner-border-sm" role="status"></span></span>
            </button>
        </form>
    </div></div>

    <div class="mt-3 mb-3 reveal in-view">
        <span class="text-secondary small me-2">Presets:</span>
        <span class="prefill-chip me-1 mb-1" data-url="https://api.example.com/data" data-method="GET">Test example.com</span>
        <span class="prefill-chip me-1 mb-1" data-url="https://api.example.com/data" data-method="OPTIONS" data-origin="https://kevbin.ct.ws">Preflight (OPTIONS)</span>
        <span class="prefill-chip me-1 mb-1" data-url="http://localhost:3000/api" data-method="GET">localhost:3000</span>
        <span class="prefill-chip me-1 mb-1" data-url="" data-method="GET" id="presetOwn">Test your own API</span>
    </div>

    <div id="corsError" class="alert alert-danger mt-4 d-none reveal in-view"></div>

    <div id="corsResults" class="cors-result mt-4">

        <div class="row g-3 mb-3">
            <div class="col-md-6">
                <div class="card reveal in-view"><div class="card-body">
                    <div class="text-secondary small mb-1">Request Summary</div>
                    <div id="reqSummary"></div>
                </div></div>
            </div>
            <div class="col-md-6">
                <div class="card reveal in-view"><div class="card-body text-center">
                    <div class="text-secondary small">Response Status</div>
                    <div id="statusBadge" class="mt-1"></div>
                </div></div>
            </div>
        </div>

        <div class="card mb-3 reveal in-view"><div class="card-body">
            <h2 class="h6 mb-3">&#128273; CORS Headers</h2>
            <div id="corsHeadersCard"></div>
        </div></div>

        <div class="card mb-3 reveal in-view"><div class="card-body">
            <h2 class="h6 mb-3">&#128737; Security Assessment</h2>
            <div id="securityAssess"></div>
        </div></div>

        <div class="card mb-3 reveal in-view"><div class="card-body">
            <h2 class="h6 mb-3">&#128203; All Response Headers</h2>
            <div style="max-height:360px;overflow:auto;">
                <table class="table table-sm table-dark align-middle mb-0" id="allHeadersTable">
                    <thead><tr><th style="width:220px;">Header</th><th>Value</th></tr></thead>
                    <tbody id="allHeadersBody"></tbody>
                </table>
            </div>
        </div></div>

        <div class="card mb-3 reveal in-view"><div class="card-body">
            <h2 class="h6 mb-3">&#128196; Response Body Preview</h2>
            <pre class="small mb-0" style="white-space:pre-wrap;word-break:break-all;max-height:300px;overflow:auto;background:var(--panel-2);padding:.75rem;border-radius:6px;"><code id="respBody"></code></pre>
        </div></div>
    </div>
</div>

<script>
(function () {
    var form = document.getElementById('corsForm');
    var btn = document.getElementById('corsBtn');
    var btnLabel = document.getElementById('corsBtnLabel');
    var btnSpin = document.getElementById('corsBtnSpin');
    var errBox = document.getElementById('corsError');
    var results = document.getElementById('corsResults');

    document.querySelectorAll('.prefill-chip').forEach(function (chip) {
        chip.addEventListener('click', function () {
            var url = chip.getAttribute('data-url');
            var method = chip.getAttribute('data-method');
            var origin = chip.getAttribute('data-origin');
            if (chip.id === 'presetOwn') {
                document.getElementById('corsUrl').value = '';
                document.getElementById('corsUrl').focus();
            } else {
                document.getElementById('corsUrl').value = url;
                document.getElementById('corsMethod').value = method;
                if (origin) document.getElementById('corsOrigin').value = origin;
            }
        });
    });

    form.addEventListener('submit', function (e) {
        e.preventDefault();
        errBox.classList.add('d-none');
        results.classList.remove('visible');
        btn.disabled = true;
        btnLabel.textContent = 'Sending...';
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
                results.scrollIntoView({ behavior: 'smooth', block: 'start' });
            })
            .catch(function () {
                errBox.textContent = 'Network error. Try again.';
                errBox.classList.remove('d-none');
                resetBtn();
            });
    });

    function resetBtn() {
        btn.disabled = false;
        btnLabel.textContent = 'Send Request';
        btnSpin.classList.add('d-none');
    }

    function esc(s) {
        var d = document.createElement('div');
        d.appendChild(document.createTextNode(s || ''));
        return d.innerHTML;
    }

    function getHeader(headers, name) {
        var lower = name.toLowerCase();
        for (var k in headers) {
            if (k.toLowerCase() === lower) {
                return headers[k];
            }
        }
        return null;
    }

    function renderResults(d) {
        var summaryHtml = '<div style="font-size:.85rem;">';
        summaryHtml += '<div style="margin-bottom:.3rem;"><span class="text-secondary">URL:</span> <code style="word-break:break-all;">' + esc(d.url) + '</code></div>';
        summaryHtml += '<div style="margin-bottom:.3rem;"><span class="text-secondary">Method:</span> <strong>' + esc(d.method) + '</strong></div>';
        summaryHtml += '<div><span class="text-secondary">Origin Sent:</span> <code>' + esc(d.origin_sent || '(none)') + '</code></div>';
        summaryHtml += '</div>';
        document.getElementById('reqSummary').innerHTML = summaryHtml;

        var sc = d.status;
        var scClass = sc >= 200 && sc < 300 ? 'status-2xx' : sc >= 300 && sc < 400 ? 'status-3xx' : sc >= 400 && sc < 500 ? 'status-4xx' : 'status-5xx';
        document.getElementById('statusBadge').innerHTML = '<span class="status-code-badge ' + scClass + '">' + sc + '</span>';

        renderCorsHeaders(d);
        renderSecurityAssessment(d);

        var tbody = document.getElementById('allHeadersBody');
        var rows = '';
        for (var k in d.headers) {
            if (k === '_status_line') continue;
            var val = Array.isArray(d.headers[k]) ? d.headers[k].join(', ') : d.headers[k];
            rows += '<tr><td class="text-secondary"><code>' + esc(k) + '</code></td><td style="word-break:break-all;">' + esc(val) + '</td></tr>';
        }
        tbody.innerHTML = rows;

        document.getElementById('respBody').textContent = d.body || '(empty response body)';
    }

    function renderCorsHeaders(d) {
        var h = d.headers;
        var corsKeys = [
            'Access-Control-Allow-Origin',
            'Access-Control-Allow-Methods',
            'Access-Control-Allow-Headers',
            'Access-Control-Allow-Credentials',
            'Access-Control-Expose-Headers',
            'Access-Control-Max-Age',
            'Vary'
        ];
        var html = '';
        corsKeys.forEach(function (key) {
            var val = getHeader(h, key);
            var isMissing = val === null;
            var displayVal = isMissing ? '<span class="cors-header-missing">Not set</span>' : esc(Array.isArray(val) ? val.join(', ') : val);
            var extra = '';
            if (key === 'Access-Control-Allow-Origin' && !isMissing) {
                if (val === '*') {
                    extra = '<div class="small mt-1" style="color:#ffc107;">&#9888; Wildcard origin &mdash; allows all origins</div>';
                } else if (val === d.origin_sent) {
                    extra = '<div class="small mt-1" style="color:#ffc107;">&#9888; Origin is reflected back</div>';
                } else {
                    extra = '<div class="small mt-1" style="color:#26d07c;">&#9989; Specific origin allowed</div>';
                }
            }
            if (key === 'Access-Control-Allow-Credentials' && !isMissing) {
                var lc = (Array.isArray(val) ? val.join('') : val).toLowerCase();
                if (lc === 'true') {
                    extra = '<div class="small mt-1" style="color:#656efc;">&#9733; Credentials are permitted</div>';
                }
            }
            if (key === 'Vary' && !isMissing) {
                var varyLower = (Array.isArray(val) ? val.join('') : val).toLowerCase();
                if (varyLower.indexOf('origin') !== -1) {
                    extra = '<div class="small mt-1" style="color:#26d07c;">&#9989; Includes Origin &mdash; prevents caching issues</div>';
                } else {
                    extra = '<div class="small mt-1" style="color:#ffc107;">&#9888; Does not include Origin</div>';
                }
            }
            html += '<div class="cors-highlight">';
            html += '<div class="cors-header-name">' + esc(key) + '</div>';
            html += '<div class="cors-header-val">' + displayVal + '</div>';
            html += extra;
            html += '</div>';
        });
        document.getElementById('corsHeadersCard').innerHTML = html;
    }

    function renderSecurityAssessment(d) {
        var h = d.headers;
        var allowOrigin = getHeader(h, 'Access-Control-Allow-Origin');
        var allowCreds = getHeader(h, 'Access-Control-Allow-Credentials');
        var allowMethods = getHeader(h, 'Access-Control-Allow-Methods');
        var varyOrigin = false;

        var varyVal = getHeader(h, 'Vary');
        if (varyVal) {
            var varyStr = (Array.isArray(varyVal) ? varyVal.join('') : varyVal).toLowerCase();
            varyOrigin = varyStr.indexOf('origin') !== -1;
        }

        var originReflected = allowOrigin !== null && allowOrigin !== '*' && allowOrigin === d.origin_sent;
        var credsTrue = allowCreds !== null && (Array.isArray(allowCreds) ? allowCreds.join('') : allowCreds).toLowerCase() === 'true';
        var wildcardCreds = allowOrigin === '*' && credsTrue;
        var overlyPermissive = allowOrigin === '*' && !credsTrue;
        var noVary = allowOrigin !== null && allowOrigin !== '*' && !varyOrigin;

        var issues = [];

        if (wildcardCreds) {
            issues.push({ type: 'danger', label: 'Credentials allowed with wildcard origin', detail: 'Browsers block this, but it signals a misconfiguration.' });
        }
        if (originReflected) {
            issues.push({ type: 'warning', label: 'Origin is reflected back', detail: 'Any site can read the response. Ensure this is intended.' });
        }
        if (overlyPermissive) {
            issues.push({ type: 'warning', label: 'Wildcard origin without credentials', detail: 'Any site can read the response.' });
        }
        if (noVary) {
            issues.push({ type: 'warning', label: 'Vary header missing Origin', detail: 'CDNs may cache responses incorrectly across origins.' });
        }
        if (allowOrigin === null && d.status !== 0) {
            issues.push({ type: 'info', label: 'No CORS headers returned', detail: 'Requests from different origins will be blocked by the browser.' });
        }
        if (allowMethods) {
            var methods = (Array.isArray(allowMethods) ? allowMethods.join('') : allowMethods).toUpperCase();
            if (methods.indexOf('PUT') !== -1 || methods.indexOf('DELETE') !== -1) {
                issues.push({ type: 'info', label: 'State-changing methods allowed', detail: 'Methods include PUT/DELETE: ' + esc(allowMethods) });
            }
        }

        var rating = 'Secure';
        var ratingClass = 'rating-secure';
        if (wildcardCreds || originReflected) {
            rating = 'Dangerous';
            ratingClass = 'rating-dangerous';
        } else if (overlyPermissive || noVary || (allowOrigin !== null && allowOrigin !== '*' && issues.length > 0)) {
            rating = 'Misconfigured';
            ratingClass = 'rating-misconfigured';
        } else if (issues.length > 0) {
            rating = 'Misconfigured';
            ratingClass = 'rating-misconfigured';
        }

        var html = '<div class="mb-3"><span class="rating-badge ' + ratingClass + '">' + esc(rating) + '</span></div>';

        html += '<div class="assess-row"><span class="assess-label">Origin reflected?</span><span class="assess-value">' + (originReflected ? '<span style="color:#ffc107;">&#9888; Yes</span>' : '<span style="color:#26d07c;">No</span>') + '</span></div>';
        html += '<div class="assess-row"><span class="assess-label">Credentials + wildcard?</span><span class="assess-value">' + (wildcardCreds ? '<span style="color:#e74c3c;">&#9888; Yes (danger)</span>' : '<span style="color:#26d07c;">No</span>') + '</span></div>';
        html += '<div class="assess-row"><span class="assess-label">Overly permissive?</span><span class="assess-value">' + (overlyPermissive ? '<span style="color:#ffc107;">&#9888; Yes</span>' : '<span style="color:#26d07c;">No</span>') + '</span></div>';
        html += '<div class="assess-row"><span class="assess-label">Vary: Origin present?</span><span class="assess-value">' + (allowOrigin !== null ? (varyOrigin ? '<span style="color:#26d07c;">&#9989; Yes</span>' : '<span style="color:#ffc107;">&#9888; No</span>') : '<span class="text-secondary">N/A</span>') + '</span></div>';

        if (issues.length > 0) {
            html += '<div class="mt-3">';
            issues.forEach(function (iss) {
                var color = iss.type === 'danger' ? '#e74c3c' : iss.type === 'warning' ? '#ffc107' : '#656efc';
                var icon = iss.type === 'danger' ? '&#9888;' : iss.type === 'warning' ? '&#9888;' : '&#9733;';
                html += '<div style="padding:.4rem 0;border-bottom:1px solid rgba(255,255,255,.04);">';
                html += '<div style="color:' + color + ';font-weight:600;font-size:.85rem;">' + icon + ' ' + esc(iss.label) + '</div>';
                html += '<div class="text-secondary small">' + esc(iss.detail) + '</div>';
                html += '</div>';
            });
            html += '</div>';
        }

        document.getElementById('securityAssess').innerHTML = html;
    }
})();
</script>
<?php page_footer(); ?>
