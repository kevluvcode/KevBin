<?php
require_once __DIR__ . '/../../functions.php';

start_session();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['urls'])) {
    header('Content-Type: application/json');

    if (!csrf_verify()) {
        echo json_encode(['error' => 'Invalid CSRF token.']);
        exit;
    }
    if (!rate_limit_check('httpstatus', 3, 60)) {
        echo json_encode(['error' => 'Rate limit reached. Max 3 batch checks per 60 seconds.']);
        exit;
    }

    $raw = trim((string)$_POST['urls']);
    if ($raw === '') {
        echo json_encode(['error' => 'Please enter at least one URL.']);
        exit;
    }

    $lines = array_filter(array_map('trim', explode("\n", $raw)));
    $lines = array_unique($lines);
    $lines = array_values($lines);
    $count = count($lines);

    if ($count === 0) {
        echo json_encode(['error' => 'No valid URLs found.']);
        exit;
    }
    if ($count > 50) {
        echo json_encode(['error' => 'Maximum 50 URLs per batch. You entered ' . $count . '.']);
        exit;
    }

    $validLines = [];
    foreach ($lines as $line) {
        $line = preg_replace('#^https?://#i', '', $line);
        $line = explode(' ', $line)[0];
        $line = explode("\t", $line)[0];
        if ($line !== '' && preg_match('#^[a-zA-Z0-9]#', $line)) {
            $validLines[] = $line;
        }
    }

    if (count($validLines) === 0) {
        echo json_encode(['error' => 'No valid URLs found.']);
        exit;
    }

    log_activity('tool_httpstatus', count($validLines) . ' URLs');

    $results = [];
    foreach ($validLines as $urlInput) {
        $url = $urlInput;
        if (!preg_match('#^https?://#i', $url)) {
            $url = 'http://' . $url;
        }

        $result = check_url($url);
        $results[] = $result;
    }

    echo json_encode(['results' => $results]);
    exit;
}

function check_url(string $url): array {
    $entry = [
        'original_url' => $url,
        'final_url' => '',
        'status_code' => 0,
        'status_text' => '',
        'redirect_count' => 0,
        'response_time' => 0,
        'content_type' => '',
        'content_length' => '',
        'server' => '',
        'title' => '',
        'redirect_chain' => [],
        'error' => '',
    ];

    if (!function_exists('curl_init')) {
        $entry['error'] = 'curl not available';
        return $entry;
    }

    $maxRedirects = 10;
    $hopUrl = $url;
    $chain = [];
    $finalCode = 0;
    $finalUrl = $url;
    $totalTime = 0;

    for ($i = 0; $i <= $maxRedirects; $i++) {
        $start = microtime(true);
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $hopUrl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 5,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_NOBODY => false,
            CURLOPT_HEADER => false,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/126.0.0.0 Safari/537.36',
            CURLOPT_ACCEPT_ENCODING => '',
            CURLOPT_HTTPHEADER => ['Connection: close'],
        ]);

        $responseHeaders = [];
        curl_setopt($ch, CURLOPT_HEADERFUNCTION, function ($curl, $headerLine) use (&$responseHeaders) {
            $responseHeaders[] = $headerLine;
            return strlen($headerLine);
        });

        $body = curl_exec($ch);
        $elapsed = round((microtime(true) - $start) * 1000);
        $curlErr = curl_error($ch);
        $info = curl_getinfo($ch);
        curl_close($ch);

        if ($body === false || $curlErr !== '') {
            if ($i === 0) {
                $entry['error'] = $curlErr !== '' ? $curlErr : 'Connection failed';
                $entry['final_url'] = $hopUrl;
                $entry['response_time'] = $elapsed;
            }
            break;
        }

        $code = (int)($info['http_code'] ?? 0);
        $totalTime += $elapsed;
        $finalCode = $code;
        $finalUrl = $hopUrl;

        $chainEntry = [
            'url' => $hopUrl,
            'code' => $code,
        ];
        $chain[] = $chainEntry;

        $contentType = '';
        $server = '';
        $contentLength = '';
        foreach ($responseHeaders as $h) {
            if (stripos($h, 'content-type:') === 0) {
                $contentType = trim(substr($h, 13));
            }
            if (stripos($h, 'server:') === 0) {
                $server = trim(substr($h, 7));
            }
            if (stripos($h, 'content-length:') === 0) {
                $contentLength = trim(substr($h, 15));
            }
        }

        $entry['content_type'] = $contentType;
        $entry['server'] = $server;
        $entry['content_length'] = $contentLength;

        if ($code >= 300 && $code < 400) {
            $location = '';
            foreach ($responseHeaders as $h) {
                if (stripos($h, 'location:') === 0) {
                    $location = trim(substr($h, 9));
                    break;
                }
            }
            if ($location === '') {
                break;
            }
            if (preg_match('#^https?://#i', $location)) {
                $hopUrl = $location;
            } else {
                $parsedBase = parse_url($hopUrl);
                $hopUrl = $parsedBase['scheme'] . '://' . $parsedBase['host'];
                if (isset($parsedBase['port'])) {
                    $hopUrl .= ':' . $parsedBase['port'];
                }
                $hopUrl .= '/' . ltrim($location, '/');
            }
            $entry['redirect_count']++;
            continue;
        }

        $first10k = substr((string)$body, 0, 10240);
        $titleMatch = [];
        if (preg_match('#<title[^>]*>(.*?)</title>#is', $first10k, $titleMatch)) {
            $entry['title'] = trim(html_entity_decode($titleMatch[1], ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        }
        break;
    }

    $entry['final_url'] = $finalUrl;
    $entry['status_code'] = $finalCode;
    $entry['status_text'] = get_status_text($finalCode);
    $entry['response_time'] = $totalTime;
    $entry['redirect_chain'] = $chain;

    return $entry;
}

function get_status_text(int $code): string {
    $texts = [
        100 => 'Continue', 101 => 'Switching Protocols', 102 => 'Processing',
        200 => 'OK', 201 => 'Created', 202 => 'Accepted', 203 => 'Non-Authoritative', 204 => 'No Content', 205 => 'Reset Content', 206 => 'Partial Content',
        300 => 'Multiple Choices', 301 => 'Moved Permanently', 302 => 'Found', 303 => 'See Other', 304 => 'Not Modified', 305 => 'Use Proxy', 307 => 'Temporary Redirect', 308 => 'Permanent Redirect',
        400 => 'Bad Request', 401 => 'Unauthorized', 403 => 'Forbidden', 404 => 'Not Found', 405 => 'Method Not Allowed', 406 => 'Not Acceptable', 407 => 'Proxy Auth Required', 408 => 'Request Timeout', 409 => 'Conflict', 410 => 'Gone', 411 => 'Length Required', 412 => 'Precondition Failed', 413 => 'Payload Too Large', 414 => 'URI Too Long', 415 => 'Unsupported Media Type', 416 => 'Range Not Satisfiable', 417 => 'Expectation Failed', 418 => "I'm a Teapot", 422 => 'Unprocessable Entity', 423 => 'Locked', 424 => 'Failed Dependency', 425 => 'Too Early', 426 => 'Upgrade Required', 428 => 'Precondition Required', 429 => 'Too Many Requests', 431 => 'Request Header Fields Too Large', 451 => 'Unavailable For Legal Reasons',
        500 => 'Internal Server Error', 501 => 'Not Implemented', 502 => 'Bad Gateway', 503 => 'Service Unavailable', 504 => 'Gateway Timeout', 505 => 'HTTP Version Not Supported', 506 => 'Variant Also Negotiates', 507 => 'Insufficient Storage', 508 => 'Loop Detected', 510 => 'Not Extended', 511 => 'Network Auth Required',
    ];
    return $texts[$code] ?? ($code >= 100 && $code < 600 ? 'Unknown' : 'Error');
}

page_header('HTTP Status Code Checker');
?>
<style>
.http-status-result{display:none}.http-status-result.visible{display:block}
.summary-grid{display:flex;flex-wrap:wrap;gap:.75rem;margin-bottom:1rem}
.summary-item{flex:1 1 auto;min-width:100px;background:var(--panel-2);border:1px solid var(--line);border-radius:10px;padding:.75rem 1rem;text-align:center}
.summary-number{font-size:1.5rem;font-weight:700;line-height:1.2}
.summary-label{font-size:.72rem;color:var(--dim);text-transform:uppercase;letter-spacing:.5px;margin-top:2px}
.c-green{color:#26d07c}.c-blue{color:#5865f2}.c-yellow{color:#f0c104}.c-red{color:#e74c3c}.c-gray{color:#888}
.status-badge{display:inline-block;padding:2px 10px;border-radius:6px;font-weight:600;font-size:.82rem}
.badge-2xx{background:rgba(38,208,124,.15);border:1px solid rgba(38,208,124,.35);color:#26d07c}
.badge-3xx{background:rgba(88,101,242,.15);border:1px solid rgba(88,101,242,.35);color:#5865f2}
.badge-4xx{background:rgba(240,193,4,.15);border:1px solid rgba(240,193,4,.35);color:#f0c104}
.badge-5xx{background:rgba(231,76,60,.15);border:1px solid rgba(231,76,60,.35);color:#e74c3c}
.badge-err{background:rgba(136,136,136,.15);border:1px solid rgba(136,136,136,.35);color:#888}
.url-cell{max-width:250px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-size:.82rem}
.final-url-cell{max-width:250px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-size:.82rem;color:var(--dim)}
.title-cell{max-width:180px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-size:.82rem}
.redirect-arrow{color:var(--accent1);margin-right:4px}
.results-table thead th{cursor:pointer;user-select:none;white-space:nowrap;position:relative}
.results-table thead th:hover{color:var(--accent1)}
.results-table thead th .sort-indicator{margin-left:4px;font-size:.7rem;opacity:.5}
.results-table thead th.sorted .sort-indicator{opacity:1;color:var(--accent1)}
.filter-bar{display:flex;gap:.5rem;flex-wrap:wrap;align-items:center;margin-bottom:1rem}
.filter-bar select,.filter-bar input{max-width:160px;font-size:.82rem}
.progress-container{display:none;margin:1.5rem 0}
.progress-bar-track{width:100%;height:6px;background:var(--panel-2);border-radius:3px;overflow:hidden}
.progress-bar-fill{height:100%;width:0%;background:linear-gradient(90deg,var(--accent1),var(--accent2));border-radius:3px;transition:width .3s}
.progress-text{font-size:.82rem;color:var(--dim);margin-top:.35rem;text-align:center}
.btn-group-tools .btn{font-size:.82rem;padding:.35rem .75rem}
.table-responsive{max-height:600px;overflow:auto}
</style>

<div class="container" style="max-width:1200px;">
    <h1 class="h4 mb-1 reveal in-view">&#128269; HTTP Status Code Checker (Batch)</h1>
    <p class="text-secondary mb-3 reveal in-view">Check HTTP status codes for multiple URLs at once. Follows redirects, records response times, and shows detailed results in a sortable table. Max 50 URLs per batch.</p>

    <div class="card reveal in-view"><div class="card-body">
        <form id="httpStatusForm" class="mb-0">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <div class="mb-3">
                <label class="form-label small text-secondary">Enter URLs (one per line, max 50)</label>
                <textarea class="form-control" name="urls" id="urlInput" rows="6" placeholder="https://example.com&#10;https://httpbin.org/status/404&#10;github.com&#10;google.com" required></textarea>
                <div class="d-flex justify-content-between align-items-center mt-2">
                    <small class="text-secondary"><span id="urlCount">0</span> URL(s) entered</small>
                    <div class="btn-group-tools d-flex gap-1">
                        <button type="button" class="btn btn-outline-secondary btn-sm" id="copyUrlsBtn">Copy URLs</button>
                        <button type="button" class="btn btn-outline-secondary btn-sm" id="clearBtn">Clear</button>
                    </div>
                </div>
            </div>
            <button class="btn btn-primary w-100" type="submit" id="checkBtn">
                <span id="btnLabel">Check All</span>
                <span id="btnSpin" class="d-none"><span class="spinner-border spinner-border-sm" role="status"></span></span>
            </button>
        </form>
    </div></div>

    <div class="progress-container" id="progressContainer">
        <div class="progress-bar-track"><div class="progress-bar-fill" id="progressBar"></div></div>
        <div class="progress-text" id="progressText">Checking 0 / 0...</div>
    </div>

    <div id="httpError" class="alert alert-danger mt-4 d-none reveal in-view"></div>

    <div id="httpResults" class="http-status-result mt-4">
        <div class="summary-grid" id="summaryGrid"></div>

        <div class="filter-bar reveal in-view">
            <label class="small text-secondary">Filter status:</label>
            <select id="filterStatus" class="form-select form-select-sm">
                <option value="all">All</option>
                <option value="2xx">2xx</option>
                <option value="3xx">3xx</option>
                <option value="4xx">4xx</option>
                <option value="5xx">5xx</option>
                <option value="err">Errors</option>
            </select>
            <label class="small text-secondary ms-2">Search:</label>
            <input type="text" class="form-control form-control-sm" id="filterSearch" placeholder="URL, title...">
            <div class="ms-auto d-flex gap-1">
                <button type="button" class="btn btn-outline-secondary btn-sm" id="exportCsvBtn">Export CSV</button>
            </div>
        </div>

        <div class="card reveal in-view"><div class="table-responsive">
            <table class="table table-sm table-dark align-middle mb-0 results-table" id="resultsTable">
                <thead>
                    <tr>
                        <th data-col="index"># <span class="sort-indicator">&#9650;</span></th>
                        <th data-col="original_url">URL <span class="sort-indicator">&#9650;</span></th>
                        <th data-col="status_code">Status <span class="sort-indicator">&#9650;</span></th>
                        <th data-col="status_text">Text <span class="sort-indicator">&#9650;</span></th>
                        <th data-col="final_url">Final URL <span class="sort-indicator">&#9650;</span></th>
                        <th data-col="redirect_count">Redirects <span class="sort-indicator">&#9650;</span></th>
                        <th data-col="response_time">Time (ms) <span class="sort-indicator">&#9650;</span></th>
                        <th data-col="content_length">Size <span class="sort-indicator">&#9650;</span></th>
                        <th data-col="server">Server <span class="sort-indicator">&#9650;</span></th>
                        <th data-col="title">Title <span class="sort-indicator">&#9650;</span></th>
                    </tr>
                </thead>
                <tbody id="resultsBody"></tbody>
            </table>
        </div></div>

        <div class="card mt-3 reveal in-view"><div class="card-body">
            <h2 class="h6 mb-2">&#128204; Redirect Chain Details</h2>
            <div id="redirectDetails" class="small" style="max-height:300px;overflow:auto;"></div>
        </div></div>
    </div>
</div>

<script>
(function () {
    var form = document.getElementById('httpStatusForm');
    var btn = document.getElementById('checkBtn');
    var btnLabel = document.getElementById('btnLabel');
    var btnSpin = document.getElementById('btnSpin');
    var errBox = document.getElementById('httpError');
    var results = document.getElementById('httpResults');
    var urlInput = document.getElementById('urlInput');
    var urlCount = document.getElementById('urlCount');
    var progressContainer = document.getElementById('progressContainer');
    var progressBar = document.getElementById('progressBar');
    var progressText = document.getElementById('progressText');
    var resultsBody = document.getElementById('resultsBody');
    var redirectDetails = document.getElementById('redirectDetails');
    var allResults = [];
    var sortCol = 'index';
    var sortAsc = true;

    urlInput.addEventListener('input', function () {
        var lines = urlInput.value.split('\n').filter(function (l) { return l.trim() !== ''; });
        urlCount.textContent = lines.length;
    });

    document.getElementById('copyUrlsBtn').addEventListener('click', function () {
        navigator.clipboard.writeText(urlInput.value).then(function () {
            document.getElementById('copyUrlsBtn').textContent = 'Copied!';
            setTimeout(function () { document.getElementById('copyUrlsBtn').textContent = 'Copy URLs'; }, 1500);
        });
    });

    document.getElementById('clearBtn').addEventListener('click', function () {
        urlInput.value = '';
        urlCount.textContent = '0';
        results.classList.remove('visible');
        errBox.classList.add('d-none');
        progressContainer.style.display = 'none';
    });

    form.addEventListener('submit', function (e) {
        e.preventDefault();
        errBox.classList.add('d-none');
        results.classList.remove('visible');
        btn.disabled = true;
        btnLabel.textContent = 'Checking...';
        btnSpin.classList.remove('d-none');
        progressContainer.style.display = 'block';
        progressBar.style.width = '0%';
        progressText.textContent = 'Starting...';

        var fd = new FormData(form);
        fetch('index.php', { method: 'POST', body: fd })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                if (d.error) {
                    errBox.textContent = d.error;
                    errBox.classList.remove('d-none');
                    resetBtn();
                    progressContainer.style.display = 'none';
                    return;
                }
                allResults = d.results || [];
                progressBar.style.width = '100%';
                progressText.textContent = 'Complete!';
                setTimeout(function () { progressContainer.style.display = 'none'; }, 500);
                renderAll();
                results.classList.add('visible');
                resetBtn();
                results.querySelectorAll('.reveal').forEach(function (el) { el.classList.add('in-view'); });
            })
            .catch(function () {
                errBox.textContent = 'Network error. Try again.';
                errBox.classList.remove('d-none');
                resetBtn();
                progressContainer.style.display = 'none';
            });
    });

    function resetBtn() {
        btn.disabled = false;
        btnLabel.textContent = 'Check All';
        btnSpin.classList.add('d-none');
    }

    function renderAll() {
        renderSummary();
        renderTable();
        renderRedirects();
    }

    function renderSummary() {
        var total = allResults.length;
        var c2 = 0, c3 = 0, c4 = 0, c5 = 0, cerr = 0;
        var totalMs = 0;
        var countMs = 0;
        allResults.forEach(function (r) {
            var c = r.status_code;
            if (c >= 200 && c < 300) c2++;
            else if (c >= 300 && c < 400) c3++;
            else if (c >= 400 && c < 500) c4++;
            else if (c >= 500 && c < 600) c5++;
            else cerr++;
            if (r.response_time > 0) { totalMs += r.response_time; countMs++; }
        });
        var avgMs = countMs > 0 ? Math.round(totalMs / countMs) : 0;
        var html = '';
        html += '<div class="summary-item"><div class="summary-number">' + total + '</div><div class="summary-label">Total Checked</div></div>';
        html += '<div class="summary-item"><div class="summary-number c-green">' + c2 + '</div><div class="summary-label">2xx Success</div></div>';
        html += '<div class="summary-item"><div class="summary-number c-blue">' + c3 + '</div><div class="summary-label">3xx Redirects</div></div>';
        html += '<div class="summary-item"><div class="summary-number c-yellow">' + c4 + '</div><div class="summary-label">4xx Client Errors</div></div>';
        html += '<div class="summary-item"><div class="summary-number c-red">' + c5 + '</div><div class="summary-label">5xx Server Errors</div></div>';
        html += '<div class="summary-item"><div class="summary-number c-gray">' + cerr + '</div><div class="summary-label">Errors</div></div>';
        html += '<div class="summary-item"><div class="summary-number">' + avgMs + '<small style="font-size:.7rem;">ms</small></div><div class="summary-label">Avg Response Time</div></div>';
        document.getElementById('summaryGrid').innerHTML = html;
    }

    function renderTable() {
        var sorted = allResults.slice().map(function (r, i) { r._index = i + 1; return r; });
        sorted.sort(function (a, b) {
            var va = a[sortCol], vb = b[sortCol];
            if (sortCol === 'index') { va = a._index; vb = b._index; }
            if (sortCol === 'status_code' || sortCol === 'redirect_count' || sortCol === 'response_time') {
                va = parseInt(va) || 0;
                vb = parseInt(vb) || 0;
                return sortAsc ? va - vb : vb - va;
            }
            va = (va || '').toString().toLowerCase();
            vb = (vb || '').toString().toLowerCase();
            if (va < vb) return sortAsc ? -1 : 1;
            if (va > vb) return sortAsc ? 1 : -1;
            return 0;
        });

        var filterVal = document.getElementById('filterStatus').value;
        var searchVal = document.getElementById('filterSearch').value.toLowerCase();

        if (filterVal !== 'all' || searchVal !== '') {
            sorted = sorted.filter(function (r) {
                if (filterVal !== 'all') {
                    var c = r.status_code;
                    var match = false;
                    if (filterVal === '2xx' && c >= 200 && c < 300) match = true;
                    else if (filterVal === '3xx' && c >= 300 && c < 400) match = true;
                    else if (filterVal === '4xx' && c >= 400 && c < 500) match = true;
                    else if (filterVal === '5xx' && c >= 500 && c < 600) match = true;
                    else if (filterVal === 'err' && (c < 100 || c >= 600 || r.error)) match = true;
                    if (!match) return false;
                }
                if (searchVal !== '') {
                    var hay = ((r.original_url || '') + ' ' + (r.title || '') + ' ' + (r.final_url || '') + ' ' + (r.server || '')).toLowerCase();
                    if (hay.indexOf(searchVal) === -1) return false;
                }
                return true;
            });
        }

        var html = '';
        sorted.forEach(function (r) {
            var badgeClass = getBadgeClass(r.status_code);
            var urlDisplay = esc(r.original_url || '');
            var urlTruncated = urlDisplay.length > 40 ? urlDisplay.substring(0, 37) + '...' : urlDisplay;
            var finalDisplay = '';
            if (r.redirect_count > 0 && r.final_url && r.final_url !== r.original_url) {
                var fUrl = esc(r.final_url);
                var fTrunc = fUrl.length > 35 ? fUrl.substring(0, 32) + '...' : fUrl;
                finalDisplay = '<span class="redirect-arrow">&#8594;</span>' + fTrunc;
            }
            var titleDisplay = esc(r.title || '');
            var titleTrunc = titleDisplay.length > 30 ? titleDisplay.substring(0, 27) + '...' : titleDisplay;
            var sizeDisplay = formatSize(r.content_length);
            var errDisplay = r.error ? ' <span class="text-danger small">' + esc(r.error) + '</span>' : '';

            html += '<tr class="result-row" data-original="' + esc(r.original_url) + '">';
            html += '<td>' + r._index + '</td>';
            html += '<td class="url-cell" title="' + urlDisplay + '">' + urlTruncated + '</td>';
            html += '<td><span class="status-badge ' + badgeClass + '">' + r.status_code + '</span>' + errDisplay + '</td>';
            html += '<td class="small">' + esc(r.status_text) + '</td>';
            html += '<td class="final-url-cell" title="' + (r.final_url ? esc(r.final_url) : '') + '">' + (r.redirect_count > 0 ? finalDisplay : '<span class="text-secondary">&mdash;</span>') + '</td>';
            html += '<td>' + r.redirect_count + '</td>';
            html += '<td>' + r.response_time + '</td>';
            html += '<td>' + sizeDisplay + '</td>';
            html += '<td class="small">' + esc(r.server || '') + '</td>';
            html += '<td class="title-cell" title="' + titleDisplay + '">' + (titleTrunc || '<span class="text-secondary">&mdash;</span>') + '</td>';
            html += '</tr>';
        });
        resultsBody.innerHTML = html || '<tr><td colspan="10" class="text-center text-secondary">No results match filter.</td></tr>';
    }

    function renderRedirects() {
        var html = '';
        allResults.forEach(function (r, idx) {
            if (r.redirect_chain && r.redirect_chain.length > 1) {
                html += '<div class="mb-2"><strong>#' + (idx + 1) + ' ' + esc(r.original_url) + '</strong><br>';
                r.redirect_chain.forEach(function (hop, hi) {
                    var arrow = hi < r.redirect_chain.length - 1 ? ' <span class="redirect-arrow">&#8594;</span> ' : '';
                    var badge = getBadgeClass(hop.code);
                    html += '<span class="status-badge ' + badge + '" style="font-size:.7rem;">' + hop.code + '</span> ';
                    html += '<span style="font-size:.78rem;">' + esc(hop.url) + '</span>' + arrow + '<br>';
                });
                html += '</div>';
            }
        });
        if (html === '') {
            html = '<span class="text-secondary">No redirects detected in any URL.</span>';
        }
        redirectDetails.innerHTML = html;
    }

    document.getElementById('filterStatus').addEventListener('change', renderTable);
    document.getElementById('filterSearch').addEventListener('input', renderTable);

    document.querySelectorAll('#resultsTable thead th').forEach(function (th) {
        th.addEventListener('click', function () {
            var col = th.getAttribute('data-col');
            if (!col) return;
            if (sortCol === col) { sortAsc = !sortAsc; }
            else { sortCol = col; sortAsc = true; }
            document.querySelectorAll('#resultsTable thead th').forEach(function (t) { t.classList.remove('sorted'); });
            th.classList.add('sorted');
            th.querySelector('.sort-indicator').innerHTML = sortAsc ? '&#9650;' : '&#9660;';
            renderTable();
        });
    });

    document.getElementById('exportCsvBtn').addEventListener('click', function () {
        var rows = [['#', 'URL', 'Status Code', 'Status Text', 'Final URL', 'Redirects', 'Time (ms)', 'Size (bytes)', 'Server', 'Title']];
        allResults.forEach(function (r, i) {
            rows.push([
                i + 1,
                r.original_url,
                r.status_code,
                r.status_text,
                r.final_url,
                r.redirect_count,
                r.response_time,
                r.content_length,
                r.server,
                r.title
            ]);
        });
        var csv = rows.map(function (row) {
            return row.map(function (cell) {
                var s = (cell || '').toString();
                if (s.indexOf(',') !== -1 || s.indexOf('"') !== -1 || s.indexOf('\n') !== -1) {
                    return '"' + s.replace(/"/g, '""') + '"';
                }
                return s;
            }).join(',');
        }).join('\n');
        var blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
        var a = document.createElement('a');
        a.href = URL.createObjectURL(blob);
        a.download = 'http-status-' + new Date().toISOString().slice(0, 10) + '.csv';
        a.click();
        URL.revokeObjectURL(a.href);
    });

    function getBadgeClass(code) {
        if (code >= 200 && code < 300) return 'badge-2xx';
        if (code >= 300 && code < 400) return 'badge-3xx';
        if (code >= 400 && code < 500) return 'badge-4xx';
        if (code >= 500 && code < 600) return 'badge-5xx';
        return 'badge-err';
    }

    function formatSize(bytes) {
        if (!bytes || bytes === '') return '<span class="text-secondary">&mdash;</span>';
        var b = parseInt(bytes);
        if (isNaN(b)) return bytes;
        if (b < 1024) return b + ' B';
        if (b < 1048576) return (b / 1024).toFixed(1) + ' KB';
        return (b / 1048576).toFixed(2) + ' MB';
    }

    function esc(s) {
        var d = document.createElement('div');
        d.appendChild(document.createTextNode(s || ''));
        return d.innerHTML;
    }
})();
</script>
<?php page_footer(); ?>
