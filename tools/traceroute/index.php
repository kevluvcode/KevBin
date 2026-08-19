<?php
require_once __DIR__ . '/../../functions.php';

start_session();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['target'])) {
    header('Content-Type: application/json');

    if (!csrf_verify()) {
        echo json_encode(['error' => 'Invalid CSRF token.']);
        exit;
    }
    if (!rate_limit_check('traceroute', 3, 60)) {
        echo json_encode(['error' => 'Rate limit reached. Wait a moment.']);
        exit;
    }

    $target = trim($_POST['target']);
    $protocol = strtolower(trim($_POST['protocol'] ?? 'tcp'));
    $maxHops = (int)($_POST['max_hops'] ?? 30);

    if (!in_array($protocol, ['tcp', 'udp', 'icmp'])) {
        $protocol = 'tcp';
    }
    if ($maxHops < 1 || $maxHops > 50) {
        $maxHops = 30;
    }

    $target = preg_replace('#^(https?://)#', '', $target);
    $target = explode('/', $target)[0];
    $target = explode(':', $target)[0];

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

    $resolvedIp = $target;
    if ($isDomain) {
        $ips = @gethostbynamel($target);
        if (is_array($ips) && count($ips) > 0) {
            $resolvedIp = $ips[0];
        } else {
            echo json_encode(['error' => 'Could not resolve hostname.']);
            exit;
        }
    }

    log_activity('tool_traceroute', $target . ' (' . $resolvedIp . ')');

    $result = execute_traceroute($target, $protocol, $maxHops);
    echo json_encode($result);
    exit;
}

page_header('Traceroute Visualizer');
?>
<style>
.traceroute-result { display: none; }
.traceroute-result.visible { display: block; }
.timeline { position: relative; padding-left: 2rem; }
.timeline::before { content: ''; position: absolute; left: 15px; top: 0; bottom: 0; width: 2px; background: linear-gradient(180deg, var(--accent1), var(--accent2), rgba(255,255,255,.1)); border-radius: 1px; }
.hop-card { position: relative; margin-bottom: .75rem; padding: .75rem 1rem; background: var(--panel-2); border: 1px solid var(--line); border-radius: 10px; transition: border-color .2s; }
.hop-card:hover { border-color: var(--accent1); }
.hop-dot { position: absolute; left: -2rem; top: 50%; transform: translate(50%, -50%); width: 12px; height: 12px; border-radius: 50%; border: 2px solid var(--panel-1); z-index: 1; }
.hop-dot.green { background: #26d07c; box-shadow: 0 0 6px rgba(38,208,124,.4); }
.hop-dot.yellow { background: #ffc107; box-shadow: 0 0 6px rgba(255,193,7,.4); }
.hop-dot.red { background: #e74c3c; box-shadow: 0 0 6px rgba(231,76,60,.4); }
.hop-dot.gray { background: #555; box-shadow: none; }
.hop-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: .35rem; }
.hop-number { font-size: .7rem; font-weight: 700; color: var(--accent1); background: rgba(88,101,242,.12); border-radius: 4px; padding: 1px 6px; min-width: 28px; text-align: center; }
.hop-ip { font-family: monospace; font-size: .85rem; font-weight: 600; }
.hop-hostname { font-size: .78rem; color: var(--dim); margin-left: .5rem; }
.hop-rtt { font-family: monospace; font-size: .78rem; color: var(--dim); }
.hop-rtt.fast { color: #26d07c; }
.hop-rtt.medium { color: #ffc107; }
.hop-rtt.slow { color: #e74c3c; }
.sparkline { display: flex; align-items: flex-end; gap: 1px; height: 16px; margin-top: .25rem; }
.spark-bar { width: 3px; border-radius: 1px; min-height: 1px; transition: height .3s; }
.spark-bar.green { background: #26d07c; }
.spark-bar.yellow { background: #ffc107; }
.spark-bar.red { background: #e74c3c; }
.spark-bar.gray { background: #555; }
.latency-chart { display: flex; align-items: flex-end; gap: 3px; height: 120px; padding: .5rem 0; }
.latency-bar { flex: 1; border-radius: 3px 3px 0 0; min-width: 4px; position: relative; transition: height .3s; cursor: default; }
.latency-bar:hover { opacity: .8; }
.latency-bar .bar-label { position: absolute; bottom: 100%; left: 50%; transform: translateX(-50%); font-size: .6rem; color: var(--dim); white-space: nowrap; padding-bottom: 2px; display: none; }
.latency-bar:hover .bar-label { display: block; }
.summary-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap: .75rem; }
.summary-stat { text-align: center; padding: 1rem .5rem; background: var(--panel-2); border: 1px solid var(--line); border-radius: 10px; }
.summary-stat .value { font-size: 1.5rem; font-weight: 700; }
.summary-stat .label { font-size: .75rem; color: var(--dim); margin-top: .25rem; }
.summary-stat.safe .value { color: #26d07c; }
.summary-stat.warn .value { color: #ffc107; }
.summary-stat.danger .value { color: #e74c3c; }
.raw-toggle { cursor: pointer; color: var(--accent1); font-size: .85rem; user-select: none; }
.raw-toggle:hover { text-decoration: underline; }
.raw-output { display: none; background: rgba(0,0,0,.35); border: 1px solid var(--line); border-radius: 8px; padding: 1rem; max-height: 300px; overflow: auto; font-size: .78rem; white-space: pre-wrap; word-break: break-all; color: var(--dim); margin-top: .5rem; font-family: monospace; }
.raw-output.visible { display: block; }
.location-tag { display: inline-block; font-size: .7rem; background: rgba(88,101,242,.1); border: 1px solid rgba(88,101,242,.2); border-radius: 4px; padding: 1px 6px; color: var(--dim); margin-left: .5rem; }
.progress-track { height: 3px; background: var(--line); border-radius: 2px; overflow: hidden; margin-top: .75rem; display: none; }
.progress-track.visible { display: block; }
.progress-fill { height: 100%; background: linear-gradient(90deg, var(--accent1), var(--accent2)); border-radius: 2px; transition: width .3s; width: 0%; }
.hop-progress-text { font-size: .75rem; color: var(--dim); margin-top: .35rem; display: none; }
.hop-progress-text.visible { display: block; }
</style>

<div class="container" style="max-width: 1000px;">
    <h1 class="h4 mb-1 reveal in-view">🌐 Traceroute Visualizer</h1>
    <p class="text-secondary mb-3 reveal in-view">Trace the network path to any domain or IP. Shows each hop, latency, and visual path. Supports TCP, UDP, and ICMP protocols.</p>

    <div class="card reveal in-view"><div class="card-body">
        <form id="traceForm" class="mb-0">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <div class="row g-2">
                <div class="col-md-5">
                    <input class="form-control" name="target" maxlength="253" placeholder="example.com or 8.8.8.8" required>
                </div>
                <div class="col-md-3">
                    <select class="form-select" name="protocol">
                        <option value="tcp" selected>TCP</option>
                        <option value="udp">UDP</option>
                        <option value="icmp">ICMP</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <input class="form-control" name="max_hops" type="number" min="1" max="50" value="30" placeholder="Max hops">
                </div>
                <div class="col-md-2">
                    <button class="btn btn-primary w-100" type="submit" id="traceBtn">
                        <span id="btnLabel">Trace</span>
                        <span id="btnSpin" class="d-none"><span class="spinner-border spinner-border-sm" role="status"></span></span>
                    </button>
                </div>
            </div>
        </form>
        <div class="progress-track" id="progressTrack"><div class="progress-fill" id="progressFill"></div></div>
        <div class="hop-progress-text" id="progressText">Starting traceroute...</div>
    </div></div>

    <div id="traceError" class="alert alert-danger mt-4 d-none reveal in-view"></div>

    <div id="traceResults" class="traceroute-result mt-4">
        <div class="summary-grid mb-4" id="summaryCard"></div>

        <div class="card mb-3 reveal in-view"><div class="card-body">
            <h2 class="h6 mb-3">📍 Hop Path</h2>
            <div class="timeline" id="hopTimeline"></div>
        </div></div>

        <div class="card mb-3 reveal in-view"><div class="card-body">
            <h2 class="h6 mb-3">📊 Latency Chart</h2>
            <div class="latency-chart" id="latencyChart"></div>
            <div class="d-flex justify-content-between mt-1" style="font-size:.65rem;color:var(--dim);">
                <span>Hop 1</span>
                <span id="latencyChartEnd"></span>
            </div>
        </div></div>

        <div class="card mb-3 reveal in-view"><div class="card-body">
            <span class="raw-toggle" id="rawToggle">▸ Show Raw Output</span>
            <div class="raw-output" id="rawOutput"></div>
        </div></div>
    </div>
</div>

<script>
(function () {
    var form = document.getElementById('traceForm');
    var btn = document.getElementById('traceBtn');
    var btnLabel = document.getElementById('btnLabel');
    var btnSpin = document.getElementById('btnSpin');
    var errBox = document.getElementById('traceError');
    var results = document.getElementById('traceResults');
    var progressTrack = document.getElementById('progressTrack');
    var progressFill = document.getElementById('progressFill');
    var progressText = document.getElementById('progressText');
    var rawToggle = document.getElementById('rawToggle');
    var rawOutput = document.getElementById('rawOutput');
    var hopCount = 0;

    rawToggle.addEventListener('click', function () {
        var open = rawOutput.classList.toggle('visible');
        rawToggle.textContent = open ? '▾ Hide Raw Output' : '▸ Show Raw Output';
    });

    form.addEventListener('submit', function (e) {
        e.preventDefault();
        errBox.classList.add('d-none');
        results.classList.remove('visible');
        btn.disabled = true;
        btnLabel.textContent = 'Tracing...';
        btnSpin.classList.remove('d-none');
        progressTrack.classList.add('visible');
        progressText.classList.add('visible');
        progressFill.style.width = '5%';
        hopCount = 0;
        progressText.textContent = 'Resolving target...';

        var fd = new FormData(form);
        var maxHops = parseInt(fd.get('max_hops')) || 30;

        var animInterval = setInterval(function () {
            if (hopCount < maxHops) {
                var pct = Math.min(5 + (hopCount / maxHops) * 85, 90);
                progressFill.style.width = pct + '%';
                progressText.textContent = 'Probing hop ' + (hopCount + 1) + ' of ' + maxHops + '...';
            }
        }, 400);

        fetch('index.php', { method: 'POST', body: fd })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                clearInterval(animInterval);
                if (d.error) {
                    errBox.textContent = d.error;
                    errBox.classList.remove('d-none');
                    resetBtn();
                    return;
                }
                progressFill.style.width = '100%';
                progressText.textContent = 'Complete!';
                setTimeout(function () {
                    progressTrack.classList.remove('visible');
                    progressText.classList.remove('visible');
                }, 600);
                renderResults(d);
                results.classList.add('visible');
                resetBtn();
                results.querySelectorAll('.reveal').forEach(function (el) {
                    el.classList.add('in-view');
                });
            })
            .catch(function () {
                clearInterval(animInterval);
                errBox.textContent = 'Network error. Try again.';
                errBox.classList.remove('d-none');
                resetBtn();
            });
    });

    function resetBtn() {
        btn.disabled = false;
        btnLabel.textContent = 'Trace';
        btnSpin.classList.add('d-none');
    }

    function renderResults(d) {
        renderSummary(d);
        renderTimeline(d);
        renderLatencyChart(d);
        renderRaw(d);
        hopCount = (d.hops || []).length;
    }

    function renderSummary(d) {
        var hops = d.hops || [];
        var latencies = [];
        var timeouts = 0;
        hops.forEach(function (h) {
            h.rtts.forEach(function (r) {
                if (r > 0) latencies.push(r);
                else timeouts++;
            });
        });
        var total = latencies.length + timeouts;
        var avgLat = latencies.length > 0 ? (latencies.reduce(function (a, b) { return a + b; }, 0) / latencies.length) : 0;
        var maxLat = latencies.length > 0 ? Math.max.apply(null, latencies) : 0;
        var lossPct = total > 0 ? ((timeouts / total) * 100) : 0;
        var avgClass = avgLat < 50 ? 'safe' : avgLat < 200 ? 'warn' : 'danger';
        var maxClass = maxLat < 50 ? 'safe' : maxLat < 200 ? 'warn' : 'danger';
        var lossClass = lossPct < 5 ? 'safe' : lossPct < 20 ? 'warn' : 'danger';

        var geoPath = [];
        hops.forEach(function (h) {
            if (h.location && geoPath.indexOf(h.location) === -1) {
                geoPath.push(h.location);
            }
        });

        var html = '';
        html += '<div class="summary-stat"><div class="value">' + esc(d.resolved_ip || '---') + '</div><div class="label">Target IP</div></div>';
        html += '<div class="summary-stat"><div class="value">' + hops.length + '</div><div class="label">Total Hops</div></div>';
        html += '<div class="summary-stat ' + avgClass + '"><div class="value">' + avgLat.toFixed(1) + ' ms</div><div class="label">Avg Latency</div></div>';
        html += '<div class="summary-stat ' + maxClass + '"><div class="value">' + maxLat.toFixed(1) + ' ms</div><div class="label">Max Latency</div></div>';
        html += '<div class="summary-stat ' + lossClass + '"><div class="value">' + lossPct.toFixed(1) + '%</div><div class="label">Packet Loss</div></div>';
        if (geoPath.length > 0) {
            html += '<div class="summary-stat" style="grid-column:1/-1;"><div class="value" style="font-size:1rem;">' + esc(geoPath.join(' → ')) + '</div><div class="label">Geographic Path</div></div>';
        }
        document.getElementById('summaryCard').innerHTML = html;
    }

    function renderTimeline(d) {
        var hops = d.hops || [];
        if (hops.length === 0) {
            document.getElementById('hopTimeline').innerHTML = '<div class="text-secondary small">No hops returned.</div>';
            return;
        }
        var maxRtt = 0;
        hops.forEach(function (h) {
            h.rtts.forEach(function (r) { if (r > maxRtt) maxRtt = r; });
        });
        if (maxRtt === 0) maxRtt = 1;

        var html = '';
        hops.forEach(function (h) {
            var avg = h.avg_rtt;
            var dotClass = avg === 0 ? 'gray' : avg < 50 ? 'green' : avg < 200 ? 'yellow' : 'red';
            var hasTimeout = h.rtts.indexOf(0) !== -1;
            var locHtml = h.location ? '<span class="location-tag">' + esc(h.location) + '</span>' : '';

            html += '<div class="hop-card">';
            html += '<div class="hop-dot ' + dotClass + '"></div>';
            html += '<div class="hop-header">';
            html += '<div><span class="hop-number">#' + h.ttl + '</span>';
            html += '<span class="hop-ip">' + esc(h.ip) + '</span>';
            if (h.hostname && h.hostname !== h.ip) {
                html += '<span class="hop-hostname">' + esc(h.hostname) + '</span>';
            }
            html += locHtml + '</div>';
            html += '<span class="hop-rtt ' + dotClass + '">' + (avg > 0 ? avg.toFixed(1) + ' ms' : '*') + '</span>';
            html += '</div>';

            html += '<div class="sparkline">';
            var bars = maxRtt > 0 ? 8 : 1;
            for (var b = 0; b < bars; b++) {
                var rIdx = Math.floor(b * h.rtts.length / bars);
                var rVal = h.rtts[rIdx] || 0;
                var barH = rVal > 0 ? Math.max(1, Math.round((rVal / maxRtt) * 14)) : 1;
                var barC = rVal === 0 ? 'gray' : rVal < 50 ? 'green' : rVal < 200 ? 'yellow' : 'red';
                html += '<div class="spark-bar ' + barC + '" style="height:' + barH + 'px;" title="' + rVal.toFixed(1) + ' ms"></div>';
            }
            html += '</div>';

            var probeStr = h.rtts.map(function (r) { return r > 0 ? r.toFixed(1) + ' ms' : '*'; }).join(' / ');
            html += '<div class="mt-1" style="font-size:.7rem;color:var(--dim);">Probes: ' + esc(probeStr);
            if (hasTimeout) html += ' <span style="color:#e74c3c;">&#9888; timeout</span>';
            html += '</div>';
            html += '</div>';
        });
        document.getElementById('hopTimeline').innerHTML = html;
    }

    function renderLatencyChart(d) {
        var hops = d.hops || [];
        var container = document.getElementById('latencyChart');
        var endEl = document.getElementById('latencyChartEnd');
        if (hops.length === 0) {
            container.innerHTML = '<div class="text-secondary small">No data.</div>';
            return;
        }
        var maxRtt = 0;
        hops.forEach(function (h) {
            if (h.avg_rtt > maxRtt) maxRtt = h.avg_rtt;
            h.rtts.forEach(function (r) { if (r > maxRtt) maxRtt = r; });
        });
        if (maxRtt === 0) maxRtt = 1;

        var html = '';
        hops.forEach(function (h) {
            var rtt = h.avg_rtt;
            var pct = rtt > 0 ? Math.max(3, (rtt / maxRtt) * 100) : 3;
            var color = rtt === 0 ? '#555' : rtt < 50 ? '#26d07c' : rtt < 200 ? '#ffc107' : '#e74c3c';
            html += '<div class="latency-bar" style="height:' + pct + '%;background:' + color + ';" title="Hop #' + h.ttl + ': ' + (rtt > 0 ? rtt.toFixed(1) + ' ms' : 'timeout') + '">';
            html += '<span class="bar-label">#' + h.ttl + '</span>';
            html += '</div>';
        });
        container.innerHTML = html;
        endEl.textContent = 'Hop ' + hops.length;
    }

    function renderRaw(d) {
        var raw = d.raw_output || '';
        rawOutput.textContent = raw;
    }

    function esc(s) {
        var el = document.createElement('div');
        el.appendChild(document.createTextNode(s || ''));
        return el.innerHTML;
    }
})();
</script>

<?php page_footer(); ?>

<?php

function execute_traceroute(string $target, string $protocol, int $maxHops): array {
    $hops = [];
    $rawLines = [];

    $cmd = find_traceroute_cmd($protocol);
    if ($cmd !== null) {
        $result = run_system_traceroute($cmd, $target, $maxHops);
        if ($result !== null) {
            return $result;
        }
    }

    // On shared hosts without a system traceroute binary (and where exec()
    // is disabled) we fall back to a TCP-port reachability probe — still a
    // useful connectivity check, performed with fsockopen which is allowed.
    if (!function_exists('exec') || !exec_available()) {
        $result = tcp_traceroute($target, $maxHops);
        if (count($result['hops']) > 0) {
            $fallbackNote = "\nNote: system traceroute unavailable; result is a TCP port-80 reachability probe (no true ICMP/UDP path trace).";
            $result['raw_output'] .= $fallbackNote;
            $result['note'] = 'fallback-tcp-probe';
            return $result;
        }
        return [
            'target' => $target,
            'resolved_ip' => $target,
            'protocol' => $protocol,
            'hops' => [],
            'raw_output' => 'Traceroute unavailable. System traceroute binary not found and the TCP probe failed.',
            'error' => 'Traceroute not available on this server for the selected protocol.',
        ];
    }

    if ($protocol === 'tcp') {
        $result = tcp_traceroute($target, $maxHops);
        return $result;
    }

    return [
        'target' => $target,
        'resolved_ip' => $target,
        'protocol' => $protocol,
        'hops' => [],
        'raw_output' => 'Traceroute not available. System traceroute binary not found and raw socket traceroute requires elevated privileges.',
        'error' => 'Traceroute not available on this server for the selected protocol.',
    ];
}

function exec_available(): bool {
    if (!function_exists('exec')) {
        return false;
    }
    $disabled = strtolower((string)ini_get('disable_functions'));
    if ($disabled !== '') {
        $list = array_map('trim', explode(',', $disabled));
        if (in_array('exec', $list, true)) {
            return false;
        }
    }
    return true;
}

function find_traceroute_cmd(string $protocol): ?string {
    if (!exec_available()) {
        return null;
    }
    $isWin = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
    if ($isWin) {
        return 'tracert';
    }
    $bin = @trim((string)exec('which traceroute 2>/dev/null'));
    if ($bin !== '' && file_exists($bin)) {
        return $bin;
    }
    $bin = @trim((string)exec('which /usr/sbin/traceroute 2>/dev/null'));
    if ($bin !== '' && file_exists($bin)) {
        return $bin;
    }
    $sbinPaths = ['/usr/sbin/traceroute', '/sbin/traceroute', '/usr/bin/traceroute'];
    foreach ($sbinPaths as $p) {
        if (file_exists($p)) {
            return $p;
        }
    }
    return null;
}

function run_system_traceroute(?string $cmd, string $target, int $maxHops): ?array {
    if ($cmd === null || !exec_available()) {
        return null;
    }

    $isWin = (stripos($cmd, 'tracert') !== false);
    if ($isWin) {
        $cmdLine = $cmd . ' -d -w 3000 -h ' . $maxHops . ' ' . escapeshellarg($target);
    } else {
        $cmdLine = $cmd . ' -n -w 3 -m ' . $maxHops . ' ' . escapeshellarg($target);
    }

    $output = [];
    $exitCode = 0;
    exec($cmdLine . ' 2>&1', $output, $exitCode);

    $rawOutput = implode("\n", $output);
    $hops = parse_traceroute_output($output, $isWin);

    if (count($hops) === 0) {
        return null;
    }

    $resolvedIp = $target;
    $ips = @gethostbynamel($target);
    if (is_array($ips) && count($ips) > 0) {
        $resolvedIp = $ips[0];
    }

    return [
        'target' => $target,
        'resolved_ip' => $resolvedIp,
        'protocol' => 'system',
        'hops' => $hops,
        'raw_output' => $rawOutput,
    ];
}

function parse_traceroute_output(array $lines, bool $isWindows): array {
    $hops = [];
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || stripos($line, 'traceroute') === 0 || stripos($line, 'Tracing route') === 0 || stripos($line, 'Trace complete') !== false || stripos($line, 'over a maximum') !== false) {
            continue;
        }

        if ($isWindows) {
            if (preg_match('/^\s*(\d+)\s+(.*?)\s*$/', $line, $m)) {
                $ttl = (int)$m[1];
                $rest = trim($m[2]);
                if ($rest === '' || $rest === '*' || stripos($rest, 'Request timed out') !== false || stripos($rest, 'Destination host unreachable') !== false) {
                    $hops[] = [
                        'ttl' => $ttl,
                        'ip' => '*',
                        'hostname' => '',
                        'rtts' => [0.0, 0.0, 0.0],
                        'avg_rtt' => 0.0,
                        'location' => '',
                    ];
                } else {
                    $ips = [];
                    $rtts = [];
                    if (preg_match_all('/(\d+)\s*ms/', $rest, $rtm)) {
                        $rtts = array_map('floatval', $rtm[1]);
                    }
                    if (preg_match_all('/(\d+\.\d+\.\d+\.\d+)/', $rest, $ipm)) {
                        $ips = $ipm[1];
                    }
                    $ip = count($ips) > 0 ? $ips[0] : '*';
                    while (count($rtts) < 3) {
                        $rtts[] = count($ips) > 0 ? 0.0 : 0.0;
                    }
                    $rtts = array_slice($rtts, 0, 3);
                    $validRtts = array_filter($rtts, function ($r) { return $r > 0; });
                    $avg = count($validRtts) > 0 ? array_sum($validRtts) / count($validRtts) : 0.0;
                    $host = '';
                    if ($ip !== '*') {
                        $host = @gethostbyaddr($ip);
                        if ($host === $ip) $host = '';
                    }
                    $hops[] = [
                        'ttl' => $ttl,
                        'ip' => $ip,
                        'hostname' => $host,
                        'rtts' => $rtts,
                        'avg_rtt' => round($avg, 1),
                        'location' => '',
                    ];
                }
            }
        } else {
            if (preg_match('/^\s*(\d+)\s+([\d\.\*]+)\s+([\d\.\*]+)\s+([\d\.\*]+)\s*$/', $line, $m)) {
                $ttl = (int)$m[1];
                $ip = $m[2];
                $rtts = [];
                for ($i = 2; $i <= 4; $i++) {
                    if ($m[$i] === '*') {
                        $rtts[] = 0.0;
                    } else {
                        $rtts[] = floatval($m[$i]);
                    }
                }
                $validRtts = array_filter($rtts, function ($r) { return $r > 0; });
                $avg = count($validRtts) > 0 ? array_sum($validRtts) / count($validRtts) : 0.0;
                $host = '';
                if ($ip !== '*') {
                    $host = @gethostbyaddr($ip);
                    if ($host === $ip) $host = '';
                }
                $hops[] = [
                    'ttl' => $ttl,
                    'ip' => $ip,
                    'hostname' => $host,
                    'rtts' => $rtts,
                    'avg_rtt' => round($avg, 1),
                    'location' => '',
                ];
            } elseif (preg_match('/^\s*(\d+)\s+([\d\.\*]+)\s+([\d\.\*]+)\s+([\d\.\*]+)\s+\(([\d\.\*]+)\)\s*$/', $line, $m)) {
                $ttl = (int)$m[1];
                $host = $m[2];
                $ip = $m[5];
                $rtts = [];
                $rtts[] = $m[3] === '*' ? 0.0 : floatval($m[3]);
                $rtts[] = $m[4] === '*' ? 0.0 : floatval($m[4]);
                $rtts[] = 0.0;
                $validRtts = array_filter($rtts, function ($r) { return $r > 0; });
                $avg = count($validRtts) > 0 ? array_sum($validRtts) / count($validRtts) : 0.0;
                $hops[] = [
                    'ttl' => $ttl,
                    'ip' => $ip,
                    'hostname' => $host,
                    'rtts' => $rtts,
                    'avg_rtt' => round($avg, 1),
                    'location' => '',
                ];
            } elseif (preg_match('/^\s*(\d+)\s+([\w\.\-]+)\s+\(([\d\.\*]+)\)\s+([\d\.\*]+)\s+([\d\.\*]+)\s+([\d\.\*]+)\s*$/', $line, $m)) {
                $ttl = (int)$m[1];
                $host = $m[2];
                $ip = $m[3];
                $rtts = [];
                for ($i = 4; $i <= 6; $i++) {
                    $rtts[] = $m[$i] === '*' ? 0.0 : floatval($m[$i]);
                }
                $validRtts = array_filter($rtts, function ($r) { return $r > 0; });
                $avg = count($validRtts) > 0 ? array_sum($validRtts) / count($validRtts) : 0.0;
                $hops[] = [
                    'ttl' => $ttl,
                    'ip' => $ip,
                    'hostname' => $host,
                    'rtts' => $rtts,
                    'avg_rtt' => round($avg, 1),
                    'location' => '',
                ];
            } elseif (preg_match('/^\s*(\d+)\s+([\w\.\-]+)\s+([\d\.\*]+)\s+([\d\.\*]+)\s+([\d\.\*]+)\s*$/', $line, $m)) {
                $ttl = (int)$m[1];
                $host = $m[2];
                $ip = $host;
                $rtts = [];
                for ($i = 3; $i <= 5; $i++) {
                    $rtts[] = $m[$i] === '*' ? 0.0 : floatval($m[$i]);
                }
                $validRtts = array_filter($rtts, function ($r) { return $r > 0; });
                $avg = count($validRtts) > 0 ? array_sum($validRtts) / count($validRtts) : 0.0;
                $hops[] = [
                    'ttl' => $ttl,
                    'ip' => $ip,
                    'hostname' => $host,
                    'rtts' => $rtts,
                    'avg_rtt' => round($avg, 1),
                    'location' => '',
                ];
            } elseif (preg_match('/^\s*(\d+)\s+\*\s+\*\s+\*\s*$/', $line, $m)) {
                $hops[] = [
                    'ttl' => (int)$m[1],
                    'ip' => '*',
                    'hostname' => '',
                    'rtts' => [0.0, 0.0, 0.0],
                    'avg_rtt' => 0.0,
                    'location' => '',
                ];
            }
        }
    }
    return $hops;
}

function tcp_traceroute(string $target, int $maxHops): array {
    $hops = [];
    $rawLines = [];
    $reached = false;

    for ($ttl = 1; $ttl <= $maxHops; $ttl++) {
        if ($reached) {
            break;
        }

        $hopData = [
            'ttl' => $ttl,
            'ip' => '*',
            'hostname' => '',
            'rtts' => [0.0, 0.0, 0.0],
            'avg_rtt' => 0.0,
            'location' => '',
        ];

        $rtts = [];
        for ($probe = 0; $probe < 3; $probe++) {
            $start = microtime(true);
            $sock = @fsockopen($target, 80, $errno, $errstr, 3);
            $elapsed = (microtime(true) - $start) * 1000;

            if (is_resource($sock)) {
                fclose($sock);
                $rtts[] = round($elapsed, 1);
                if ($probe === 0) {
                    $hopData['ip'] = $target;
                    $host = @gethostbyaddr($target);
                    $hopData['hostname'] = ($host !== $target) ? $host : '';
                }
            } else {
                $rtts[] = 0.0;
                if ($probe === 0 && ($errno === 113 || $errno === 101)) {
                    break;
                }
            }
        }

        while (count($rtts) < 3) {
            $rtts[] = 0.0;
        }
        $hopData['rtts'] = $rtts;

        $validRtts = array_filter($rtts, function ($r) { return $r > 0; });
        $hopData['avg_rtt'] = count($validRtts) > 0 ? round(array_sum($validRtts) / count($validRtts), 1) : 0.0;

        $hops[] = $hopData;

        $rttStr = implode(', ', array_map(function ($r) { return $r > 0 ? $r . ' ms' : '*'; }, $rtts));
        $rawLines[] = sprintf('%2d  %-18s  %s', $ttl, $hopData['ip'], $rttStr);

        if ($hopData['ip'] !== '*') {
            $reached = true;
        }
    }

    return [
        'target' => $target,
        'resolved_ip' => $target,
        'protocol' => 'tcp',
        'hops' => $hops,
        'raw_output' => implode("\n", $rawLines),
    ];
}
