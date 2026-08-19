<?php
if (isset($_GET['ip']) && $_GET['ip'] === '1') {
    header('Content-Type: application/json');
    header('Cache-Control: no-store');
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    foreach (['HTTP_CF_CONNECTING_IP', 'HTTP_X_REAL_IP', 'HTTP_X_FORWARDED_FOR'] as $h) {
        if (!empty($_SERVER[$h])) {
            $parts = explode(',', $_SERVER[$h]);
            $ip = trim($parts[0]);
            break;
        }
    }
    echo json_encode(['ip' => $ip]);
    exit;
}

require_once __DIR__ . '/../../functions.php';

start_session();
$GLOBALS['_meta'] = [
    'description' => 'Free advanced WebRTC leak test. Auto-scans every configured STUN server in parallel and cross-checks all leaked addresses against your server-visible IP to detect VPN and proxy leaks. Run entirely in your browser — nothing is stored.',
    'keywords' => 'webrtc leak test, webrtc ip leak, browser ip leak, vpn leak test, webrtc vulnerability, ip address leak, multi stun scan, mdnskdns',
];
page_header('WebRTC Leak Test');
?>
<div class="container" style="max-width: 900px;">
    <h1 class="h4 mb-2 reveal in-view">WebRTC Leak Test</h1>
    <p class="text-secondary mb-1 reveal in-view">One click runs a full auto-scan: every configured STUN server is probed in parallel, every candidate address is collected and cross-checked against your server-visible HTTP IP. VPN users: this reveals whether your VPN is leaking your real IP through STUN, even when you think you're protected.</p>
    <p class="text-secondary mb-4 reveal in-view">WebRTC (Web Real-Time Communication) can bypass VPN tunnels by sending STUN requests directly to discover your public IP. This test automates the whole battery — multiple STUN endpoints, mDNS status, local network enumeration, IPv6 exposure and relay detection — and tells you in one verdict whether your browsing path is leaking.</p>

    <div class="card mb-4 reveal in-view"><div class="card-body">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
            <div>
                <h2 class="h6 mb-2">Full Auto-Scan</h2>
                <p class="text-secondary small mb-0">Runs every STUN server at once, compares all discovered IPs against your server-visible IP, and reports leaks per server. Nothing is stored.</p>
            </div>
            <button class="btn btn-primary btn-lg" id="wrt-start" onclick="startTest()">Start Auto-Scan</button>
        </div>
        <div class="form-check form-check-inline mt-3">
            <input class="form-check-input" type="checkbox" id="wrt-http" checked>
            <label class="form-check-label small" for="wrt-http">Compare against server-visible IP (HTTP)</label>
        </div>
        <div class="form-check form-check-inline">
            <input class="form-check-input" type="checkbox" id="wrt-multi" checked>
            <label class="form-check-label small" for="wrt-multi">Scan all STUN servers (parallel)</label>
        </div>
        <div class="alert alert-warning small mb-0 mt-3"><strong>Privacy notice:</strong> This test sends real STUN requests to every server listed below (Google, Cloudflare, Mozilla, Twilio and others). Each STUN server sees your IP as part of the protocol. If "compare against server-visible IP" is enabled, one HTTPS request also goes back to KevBin, which returns only the public IP it sees for you — nothing is logged or stored. Do not run this if sending STUN traffic conflicts with your threat model.</div>
    </div></div>

    <div id="wrt-results" style="display:none;">
        <div id="wrt-verdict" class="mb-4"></div>

        <div class="row g-3 mb-4" id="wrt-cards"></div>

        <div class="card mb-4 reveal in-view"><div class="card-body">
            <h2 class="h6 mb-3">Per-STUN-Server Results</h2>
            <div class="table-responsive">
                <table class="table table-sm table-dark align-middle mb-0" id="wrt-servers"></table>
            </div>
        </div></div>

        <div class="card mb-4 reveal in-view"><div class="card-body">
            <h2 class="h6 mb-2">Scan Log</h2>
            <pre id="wrt-log" class="mb-0 p-3 small" style="background:#0b0b0b;border:1px solid var(--line);border-radius:8px;font-family:'JetBrains Mono',monospace;white-space:pre-wrap;word-break:break-word;max-height:260px;overflow:auto;font-size:.78rem;"></pre>
        </div></div>

        <div class="card mb-4 reveal in-view"><div class="card-body">
            <h2 class="h6 mb-3">About WebRTC Leaks</h2>
            <p class="text-secondary small mb-2">A WebRTC leak occurs when your browser sends STUN (Session Traversal Utilities for NAT) requests to discover your public IP address, bypassing any VPN or proxy tunnel. This happens because WebRTC operates at the browser level, not the OS level — most VPN clients only route regular traffic, not peer-to-peer connections.</p>
            <p class="text-secondary small mb-0">Even if you are connected to a VPN, a WebRTC leak can reveal your real public IP to any website that triggers a WebRTC connection. This is a well-documented vulnerability in browsers that support WebRTC (Chrome, Firefox, Edge, Opera, Brave). mDNS masking (a <code>.local</code> hostname in candidates) hides your real local address from the remote peer, but it does not stop <code>srflx</code> candidates from exposing your public IP.</p>
        </div></div>

        <div class="card mb-4 reveal in-view"><div class="card-body">
            <h2 class="h6 mb-3">How to Fix WebRTC Leaks</h2>
            <div class="row g-2">
                <div class="col-md-6">
                    <h3 class="h6 text-secondary">Firefox</h3>
                    <p class="text-secondary small mb-2">Type <code>about:config</code> in the address bar, search for <code>media.peerconnection.enabled</code> and set it to <code>false</code>. This fully disables WebRTC.</p>
                </div>
                <div class="col-md-6">
                    <h3 class="h6 text-secondary">Chrome / Edge</h3>
                    <p class="text-secondary small mb-2">Chrome does not expose a simple toggle. Install an extension like <strong>WebRTC Leak Shield</strong> or <strong>uBlock Origin</strong> (with WebRTC blocking enabled in settings).</p>
                </div>
                <div class="col-md-6">
                    <h3 class="h6 text-secondary">Brave</h3>
                    <p class="text-secondary small mb-2">Go to <strong>Settings → Shields → Fingerprinting protection</strong> and set to <strong>Aggressive</strong>. Brave blocks WebRTC leaks by default in private windows.</p>
                </div>
                <div class="col-md-6">
                    <h3 class="h6 text-secondary">Opera</h3>
                    <p class="text-secondary small mb-2">Go to <strong>Settings → Advanced → Privacy & security → WebRTC</strong> and select <strong>Disable non-proxied UDP</strong>.</p>
                </div>
            </div>
        </div></div>

        <div class="card mb-4 reveal in-view"><div class="card-body">
            <h2 class="h6 mb-2">Raw Candidate Output</h2>
            <div class="text-end mb-2"><button class="btn btn-outline-light btn-sm" onclick="var c=document.getElementById('wrt-raw');c.style.display=c.style.display==='none'?'block':'none';this.textContent=c.style.display==='none'?'Show':'Hide';">Show</button></div>
            <pre id="wrt-raw" class="mb-0 p-3 small" style="display:none;background:#0b0b0b;border:1px solid var(--line);border-radius:8px;font-family:'JetBrains Mono',monospace;white-space:pre-wrap;word-break:break-all;max-height:400px;overflow:auto;font-size:.8rem;"></pre>
        </div></div>

        <div class="card mb-4 reveal in-view"><div class="card-body">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <h2 class="h6 mb-0">Test History (this session)</h2>
                <button class="btn btn-outline-light btn-sm" onclick="clearHistory()">Clear</button>
            </div>
            <div id="wrt-history" class="small text-secondary">No previous tests in this session.</div>
        </div></div>
    </div>

    <div id="wrt-status" class="text-secondary small mt-2"></div>
</div>

<script>
var $w = function(id) { return document.getElementById(id); };
var rawCandidates = [];
var testHistory = [];
var logs = [];

var STUN_SERVERS = [
    { url: 'stun:stun.l.google.com:19302', label: 'Google (l)' },
    { url: 'stun:stun1.l.google.com:19302', label: 'Google (1)' },
    { url: 'stun:stun2.l.google.com:19302', label: 'Google (2)' },
    { url: 'stun:stun3.l.google.com:19302', label: 'Google (3)' },
    { url: 'stun:stun4.l.google.com:19302', label: 'Google (4)' },
    { url: 'stun:stun.cloudflare.com:3478', label: 'Cloudflare' },
    { url: 'stun:stun.services.mozilla.com', label: 'Mozilla' },
    { url: 'stun:global.stun.twilio.com:3478', label: 'Twilio' },
    { url: 'stun:stun.ekiga.net', label: 'Ekiga' },
    { url: 'stun:stun.ideasip.com', label: 'Ideasip' },
    { url: 'stun:stun.schlund.de', label: 'Schlund' },
    { url: 'stun:stun.iptel.org', label: 'iptel' },
    { url: 'stun:stun01.kurento.org:3478', label: 'Kurento' }
];

function escapeHtml(s) {
    return s.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

function unique(arr) {
    var seen = {};
    var out = [];
    for (var i = 0; i < arr.length; i++) {
        if (!seen[arr[i]]) { seen[arr[i]] = true; out.push(arr[i]); }
    }
    return out;
}

function log(msg) {
    logs.push('[' + new Date().toLocaleTimeString() + '] ' + msg);
    var el = $w('wrt-log');
    if (el) el.textContent = logs.join('\n');
    var status = $w('wrt-status');
    if (status) status.textContent = msg;
}

function isPrivateIP(ip) {
    if (ip.indexOf(':') !== -1) {
        if (ip === '::1') return true;
        if (ip.indexOf('fc') === 0 || ip.indexOf('fd') === 0) return true;
        if (ip.indexOf('fe80') === 0) return true;
        return false;
    }
    var parts = ip.split('.');
    if (parts.length !== 4) return false;
    var a = parseInt(parts[0], 10), b = parseInt(parts[1], 10);
    if (a === 10) return true;
    if (a === 172 && b >= 16 && b <= 31) return true;
    if (a === 192 && b === 168) return true;
    if (a === 127) return true;
    if (a === 0) return true;
    if (a === 169 && b === 254) return true;
    if (a === 100 && b >= 64 && b <= 127) return true;
    if (a === 198 && (b === 18 || b === 19)) return true;
    return false;
}

function parseCandidate(str) {
    var result = { ip: null, hostname: null, port: null, protocol: null, type: null, raw: str };
    var parts = str.split(' ');
    if (parts.length >= 6 && parts[0].indexOf('candidate:') === 0) {
        result.protocol = (parts[2] || '').toUpperCase() === 'TCP' ? 'TCP' : 'UDP';
        var addr = parts[4];
        result.port = parseInt(parts[5], 10);
        if (isNaN(result.port)) result.port = null;
        if (/^[0-9a-f\.\:]+$/i.test(addr)) result.ip = addr;
        else if (addr) result.hostname = addr;
        for (var i = 0; i < parts.length - 1; i++) {
            if (parts[i] === 'typ') { result.type = parts[i + 1]; break; }
        }
    }
    return result;
}

function scanServer(server, timeoutMs) {
    return new Promise(function(resolve) {
        var out = { label: server.label, url: server.url, candidates: [], raw: [], error: null, timedOut: false };
        var pc;
        try {
            pc = new RTCPeerConnection({ iceServers: [{ urls: server.url }] });
        } catch (e) {
            out.error = e.message;
            resolve(out);
            return;
        }
        try {
            pc.createDataChannel('wrtc-scan');
        } catch (e) {
            out.error = e.message;
            try { pc.close(); } catch (e2) {}
            resolve(out);
            return;
        }

        var done = false;

        function finish() {
            if (done) return;
            done = true;
            clearTimeout(timer);
            try { pc.close(); } catch (e) {}
            resolve(out);
        }

        var timer = setTimeout(function() {
            out.timedOut = true;
            finish();
        }, timeoutMs);

        pc.onicecandidate = function(evt) {
            if (done) return;
            if (!evt.candidate) { finish(); return; }
            var raw = evt.candidate.candidate;
            if (raw.indexOf('endOfCandidates') !== -1) return;
            out.raw.push(raw);
            var c = parseCandidate(raw);
            if (c.ip || c.hostname) out.candidates.push(c);
        };
        pc.onicegatheringstatechange = function() {
            if (!done && pc.iceGatheringState === 'complete') finish();
        };

        pc.createOffer().then(function(offer) {
            return pc.setLocalDescription(offer);
        }).catch(function(err) {
            if (!done) { out.error = err.message; finish(); }
        });
    });
}

function withTimeout(promise, ms, fallback) {
    return Promise.race([
        promise,
        new Promise(function(resolve) { setTimeout(function() { resolve(fallback); }, ms); })
    ]);
}

function startTest() {
    var btn = $w('wrt-start');
    var status = $w('wrt-status');
    btn.disabled = true;
    btn.textContent = 'Scanning…';
    status.textContent = 'Preparing full auto-scan…';
    status.className = 'text-secondary small mt-2';
    $w('wrt-results').style.display = 'none';
    logs = [];
    rawCandidates = [];

    var wantHttp = $w('wrt-http').checked;
    var servers = $w('wrt-multi').checked ? STUN_SERVERS.slice() : STUN_SERVERS.slice(0, 1);

    log('Auto-scan started against ' + servers.length + ' STUN server(s).');
    log('Multi-server mode: ' + (servers.length > 1 ? 'enabled' : 'disabled'));

    var httpPromise = wantHttp ? withTimeout(
        fetch('?ip=1').then(function(r) {
            return r.json().catch(function() { return null; });
        }).then(function(j) {
            return j && j.ip ? j.ip : null;
        }).catch(function() { return null; }),
        5000,
        null
    ) : Promise.resolve(null);

    httpPromise.then(function(httpIp) {
        if (wantHttp) {
            if (httpIp) log('Server-visible HTTP IP: ' + httpIp);
            else log('Could not fetch server-visible IP — skipping comparison.');
        } else {
            log('HTTP IP comparison disabled.');
        }

        var total = servers.length;
        status.textContent = 'Scanning ' + total + ' STUN server(s)…';

        var scans = servers.map(function(server, idx) {
            log('[' + (idx + 1) + '/' + total + '] Connecting to ' + server.label + ' (' + server.url + ')…');
            return scanServer(server, 8000).then(function(r) {
                status.textContent = 'Collected ' + r.candidates.length + ' candidate(s) from ' + server.label + ' (' + (idx + 1) + '/' + total + ')…';
                return r;
            });
        });

        Promise.all(scans).then(function(allResults) {
            render(allResults, httpIp, wantHttp);
        });
    });
}

function render(allResults, httpIp, wantHttp) {
    var perServer = [];
    var allPublic = [], allPrivate = [], allRelay = [];
    var mdnsSeen = false;

    allResults.forEach(function(r) {
        var pubs = [], privs = [], relays = [];
        var sMdns = false;
        r.candidates.forEach(function(c) {
            if (c.hostname && c.hostname.indexOf('.local') !== -1) { sMdns = true; mdnsSeen = true; return; }
            if (!c.ip) return;
            if (c.type === 'relay') relays.push(c.ip);
            else if (isPrivateIP(c.ip)) privs.push(c.ip);
            else pubs.push(c.ip);
        });
        perServer.push({
            label: r.label,
            url: r.url,
            count: r.candidates.length,
            public: unique(pubs),
            private: unique(privs),
            relay: unique(relays),
            mdns: sMdns,
            error: r.error,
            timedOut: r.timedOut
        });
        allPublic = allPublic.concat(pubs);
        allPrivate = allPrivate.concat(privs);
        allRelay = allRelay.concat(relays);
    });

    allPublic = unique(allPublic);
    allPrivate = unique(allPrivate);
    allRelay = unique(allRelay);

    var leaked = [];
    var potential = false;
    if (allPublic.length === 0) {
        // blocked or masked — handled in verdict
    } else if (wantHttp && httpIp) {
        allPublic.forEach(function(ip) {
            if (ip !== httpIp) leaked.push(ip);
        });
    } else if (allPublic.length > 1) {
        leaked = allPublic.slice(1);
        potential = true;
    }
    var leakedSet = unique(leaked);

    // verdict banner
    var verdictHtml;
    if (allPublic.length === 0) {
        var masked = mdnsSeen;
        verdictHtml = '<div class="alert ' + (masked ? 'alert-success' : 'alert-info') + ' mb-0">' +
            '<strong>' + (masked ? 'No leak detected — mDNS masking active.' : 'No public IP exposed.') + '</strong> ' +
            'WebRTC produced no public addresses' + (masked ? '; host candidates were anonymized with .local mDNS names.' : ' (it may be disabled or blocked).') +
            '</div>';
    } else if (leaked.length > 0) {
        verdictHtml = '<div class="alert alert-danger mb-0"><strong>WebRTC leak detected.</strong> ' + leaked.length +
            ' public IP(s) exposed that do not match your server-visible IP: <code>' + escapeHtml(leaked.join(', ')) +
            '</code>. WebRTC is not following the same path as your normal browsing traffic (VPN/proxy leak).</div>';
    } else if (potential) {
        verdictHtml = '<div class="alert alert-warning mb-0"><strong>Possible leak.</strong> Multiple distinct public IPs were exposed (' +
            escapeHtml(allPublic.join(', ')) + ') but no server-IP comparison was available to confirm. Re-run with the HTTP comparison enabled.</div>';
    } else {
        verdictHtml = '<div class="alert alert-success mb-0"><strong>No leak detected.</strong> WebRTC exposed a single public IP (<code>' +
            escapeHtml(allPublic.join(', ')) + '</code>)' +
            (wantHttp && httpIp ? ' matching your server-visible IP.' : '.') + '</div>';
    }
    $w('wrt-verdict').innerHTML = verdictHtml;

    // summary cards
    var cards = '';

    cards += '<div class="col-md-4"><div class="card h-100 reveal in-view"><div class="card-body">';
    cards += '<h3 class="h6 text-secondary mb-2">HTTP-Visible IP</h3>';
    if (wantHttp) {
        if (httpIp) {
            cards += '<p class="mb-1"><code style="font-size:1rem;">' + escapeHtml(httpIp) + '</code></p>';
            cards += '<p class="text-secondary small mb-0">Your normal browsing path, as seen by this server.</p>';
        } else {
            cards += '<p class="mb-0 text-secondary">Comparison unavailable — lookup endpoint did not respond.</p>';
        }
    } else {
        cards += '<p class="mb-0 text-secondary">Comparison disabled for this run.</p>';
    }
    cards += '</div></div></div>';

    cards += '<div class="col-md-4"><div class="card h-100 reveal in-view"><div class="card-body">';
    cards += '<h3 class="h6 text-secondary mb-2">WebRTC Public IPs</h3>';
    if (allPublic.length > 0) {
        cards += '<p class="mb-1">' + allPublic.map(function(ip) {
            var isLeak = leakedSet.indexOf(ip) !== -1;
            return '<code style="font-size:1rem;" class="' + (isLeak ? 'text-danger' : '') + '">' + escapeHtml(ip) + '</code>';
        }).join(', ') + '</p>';
        cards += '<p class="text-secondary small mb-0">' +
            (leaked.length > 0
                ? '<span class="text-danger">' + leaked.length + ' leaked via different path</span>'
                : (wantHttp && httpIp ? 'Single public address — consistent with HTTP path' : 'Single public address — clean')) +
            '</p>';
    } else {
        cards += '<p class="mb-0 text-secondary">None exposed.</p>';
    }
    cards += '</div></div></div>';

    cards += '<div class="col-md-4"><div class="card h-100 reveal in-view"><div class="card-body">';
    cards += '<h3 class="h6 text-secondary mb-2">Protection</h3>';
    cards += '<p class="mb-1">mDNS masking: <span class="' + (mdnsSeen ? 'text-success' : 'text-danger') + '">' + (mdnsSeen ? 'Active' : 'Off') + '</span></p>';
    cards += '<p class="mb-1 text-secondary small">Private IPs: ' + (allPrivate.length > 0 ? '<code>' + escapeHtml(allPrivate.join(', ')) + '</code>' : 'none detected') + '</p>';
    var support = typeof RTCPeerConnection !== 'undefined';
    cards += '<p class="mb-0 text-secondary small">WebRTC: ' + (support ? '<span class="text-success">Supported</span>' : '<span class="text-danger">Unsupported</span>') + '</p>';
    cards += '</div></div></div>';

    cards += '<div class="col-md-6"><div class="card h-100 reveal in-view"><div class="card-body">';
    cards += '<h3 class="h6 text-secondary mb-2">Local Network IPs</h3>';
    if (allPrivate.length > 0) cards += '<p class="mb-0"><code>' + escapeHtml(allPrivate.join(', ')) + '</code></p>';
    else cards += '<p class="mb-0 text-secondary">None detected.</p>';
    cards += '</div></div></div>';

    cards += '<div class="col-md-6"><div class="card h-100 reveal in-view"><div class="card-body">';
    cards += '<h3 class="h6 text-secondary mb-2">Relay (TURN) IPs</h3>';
    if (allRelay.length > 0) cards += '<p class="mb-0"><code>' + escapeHtml(allRelay.join(', ')) + '</code></p>';
    else cards += '<p class="mb-0 text-secondary">No TURN relay configured — none detected.</p>';
    cards += '</div></div></div>';

    $w('wrt-cards').innerHTML = cards;

    // per-server table
    var th = '<thead><tr><th>STUN Server</th><th>Candidates</th><th>Public IPs</th><th>Private IPs</th><th>mDNS</th><th>Status</th></tr></thead>';
    var tb = '<tbody>';
    perServer.forEach(function(s) {
        var st;
        if (s.error) {
            st = '<span class="text-warning">Error</span>';
        } else if (s.public.length === 0) {
            st = s.mdns ? '<span class="text-success">Masked (mDNS)</span>' : '<span class="text-secondary">Blocked / none</span>';
        } else {
            var l = s.public.filter(function(ip) { return leakedSet.indexOf(ip) !== -1; });
            st = l.length > 0 ? '<span class="text-danger">LEAK</span>' : '<span class="text-success">Clean</span>';
        }
        tb += '<tr>';
        tb += '<td><code>' + escapeHtml(s.url) + '</code><div class="text-secondary small">' + escapeHtml(s.label) + '</div></td>';
        tb += '<td>' + s.count + '</td>';
        tb += '<td>' + (s.public.length ? s.public.map(function(ip) {
            var isL = leakedSet.indexOf(ip) !== -1;
            return '<code class="' + (isL ? 'text-danger' : '') + '">' + escapeHtml(ip) + '</code>';
        }).join(', ') : '<span class="text-secondary">—</span>') + '</td>';
        tb += '<td>' + (s.private.length ? s.private.map(function(ip) { return '<code>' + escapeHtml(ip) + '</code>'; }).join(', ') : '<span class="text-secondary">—</span>') + '</td>';
        tb += '<td>' + (s.mdns ? '<span class="text-success">Yes</span>' : '<span class="text-secondary">No</span>') + '</td>';
        tb += '<td>' + st + '</td>';
        tb += '</tr>';
    });
    tb += '</tbody>';
    $w('wrt-servers').innerHTML = th + tb;

    // raw output grouped by server
    rawCandidates = [];
    allResults.forEach(function(r) {
        if (r.raw.length) {
            rawCandidates.push('### ' + r.label + ' (' + r.url + ')');
            rawCandidates = rawCandidates.concat(r.raw);
        }
    });
    $w('wrt-raw').textContent = rawCandidates.length > 0 ? rawCandidates.join('\n') : 'No candidates gathered.';

    // history
    var resultKind = allPublic.length === 0 ? 'Blocked' : (leaked.length > 0 ? 'Leaked' : 'Clean');
    var now = new Date();
    testHistory.push({
        time: now.toLocaleTimeString(),
        publicIP: allPublic.length > 0 ? allPublic.join(', ') : '—',
        privateIPs: allPrivate.length > 0 ? allPrivate.join(', ') : '—',
        result: resultKind
    });
    saveHistory();
    renderHistory();

    $w('wrt-results').style.display = 'block';

    var status = $w('wrt-status');
    if (leaked.length > 0 || allPublic.length === 0) {
        status.innerHTML = 'Scan complete — verdict: <span class="' + (leaked.length > 0 ? 'text-danger fw-bold' : 'text-secondary') + '">' + resultKind + '</span> (' + totalCandidates(allResults) + ' candidates across ' + allResults.length + ' servers).';
    } else {
        status.innerHTML = 'Scan complete — verdict: <span class="text-success fw-bold">' + resultKind + '</span> (' + totalCandidates(allResults) + ' candidates across ' + allResults.length + ' servers).';
    }

    var btn = $w('wrt-start');
    btn.disabled = false;
    btn.textContent = 'Start Auto-Scan';

    log('Scan complete — verdict: ' + resultKind + '.');
}

function totalCandidates(allResults) {
    var n = 0;
    allResults.forEach(function(r) { n += r.candidates.length; });
    return n;
}

function loadHistory() {
    try {
        var stored = sessionStorage.getItem('wrt_test_history');
        if (stored) testHistory = JSON.parse(stored);
    } catch(e) {}
}

function saveHistory() {
    try {
        sessionStorage.setItem('wrt_test_history', JSON.stringify(testHistory));
    } catch(e) {}
}

function renderHistory() {
    var el = $w('wrt-history');
    if (testHistory.length === 0) {
        el.innerHTML = 'No previous tests in this session.';
        return;
    }
    var html = '<table class="table table-sm table-dark align-middle mb-0"><thead><tr><th>Time</th><th>Public IP</th><th>Private IPs</th><th>Result</th></tr></thead><tbody>';
    for (var i = testHistory.length - 1; i >= 0; i--) {
        var h = testHistory[i];
        var cls;
        if (h.result === 'Leaked') cls = 'text-danger';
        else if (h.result === 'Clean') cls = 'text-success';
        else cls = 'text-secondary';
        html += '<tr><td class="text-secondary">' + escapeHtml(h.time) + '</td><td><code>' + escapeHtml(h.publicIP || '—') + '</code></td><td><code>' + escapeHtml(h.privateIPs || '—') + '</code></td><td class="' + cls + '">' + escapeHtml(h.result) + '</td></tr>';
    }
    html += '</tbody></table>';
    el.innerHTML = html;
}

function clearHistory() {
    testHistory = [];
    saveHistory();
    renderHistory();
}

loadHistory();
renderHistory();
</script>
<?php page_footer(); ?>