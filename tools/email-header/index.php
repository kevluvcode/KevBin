<?php
require_once __DIR__ . '/../../functions.php';

start_session();
$GLOBALS['_meta'] = [
    'description' => 'Analyze email headers to trace the delivery path, check SPF/DKIM/DMARC authentication, inspect security and spam details. Everything runs in your browser.',
    'keywords' => 'email header analyzer, email trace, SPF check, DKIM check, DMARC check, email routing, spam score',
];
page_header('Email Header Analyzer');
?>
<style>
    .header-textarea {
        font-family: 'JetBrains Mono', monospace;
        font-size: .82rem;
        min-height: 180px;
        resize: vertical;
        background: var(--bs-body-bg, #1a1d23);
        color: var(--bs-body-color, #e0e0e0);
    }
    .results-area { display: none; }
    .results-area.active { display: block; }
    .analysis-card {
        background: var(--bs-body-bg, #1a1d23);
        border: 1px solid rgba(255,255,255,.08);
        border-radius: 10px;
        margin-bottom: 1.25rem;
        overflow: hidden;
    }
    .analysis-card .card-header-custom {
        padding: .75rem 1rem;
        font-weight: 600;
        font-size: .9rem;
        border-bottom: 1px solid rgba(255,255,255,.08);
        display: flex;
        align-items: center;
        gap: .5rem;
    }
    .analysis-card .card-body-custom { padding: 1rem; }
    .kv-row {
        display: flex;
        gap: .75rem;
        padding: .4rem 0;
        border-bottom: 1px solid rgba(255,255,255,.04);
        align-items: flex-start;
    }
    .kv-row:last-child { border-bottom: none; }
    .kv-label {
        min-width: 160px;
        max-width: 160px;
        font-size: .78rem;
        color: #8b949e;
        font-weight: 500;
        text-transform: uppercase;
        letter-spacing: .03em;
        padding-top: 1px;
    }
    .kv-value {
        font-size: .85rem;
        word-break: break-word;
        flex: 1;
    }
    .kv-value code {
        background: rgba(255,255,255,.06);
        padding: .15em .4em;
        border-radius: 4px;
        font-size: .82rem;
    }
    .badge-spf, .badge-dkim, .badge-dmarc, .badge-arc {
        padding: .3em .65em;
        border-radius: 6px;
        font-size: .78rem;
        font-weight: 600;
        display: inline-flex;
        align-items: center;
        gap: .35em;
    }
    .badge-pass { background: rgba(35,134,54,.25); color: #3fb950; border: 1px solid rgba(63,185,80,.3); }
    .badge-fail { background: rgba(218,54,51,.2); color: #f85149; border: 1px solid rgba(248,81,73,.3); }
    .badge-warn { background: rgba(187,128,9,.2); color: #e3b341; border: 1px solid rgba(227,179,65,.3); }
    .badge-neutral { background: rgba(139,148,158,.15); color: #8b949e; border: 1px solid rgba(139,148,158,.3); }
    .badge-unknown { background: rgba(139,148,158,.1); color: #6e7681; border: 1px solid rgba(110,118,129,.25); }

    .route-timeline {
        position: relative;
        padding-left: 2rem;
    }
    .route-timeline::before {
        content: '';
        position: absolute;
        left: .65rem;
        top: .5rem;
        bottom: .5rem;
        width: 2px;
        background: rgba(255,255,255,.12);
    }
    .route-hop {
        position: relative;
        padding: .6rem .75rem;
        margin-bottom: .5rem;
        background: rgba(255,255,255,.03);
        border-radius: 8px;
        border: 1px solid rgba(255,255,255,.06);
    }
    .route-hop:last-child { margin-bottom: 0; }
    .route-hop::before {
        content: '';
        position: absolute;
        left: -1.65rem;
        top: 50%;
        transform: translateY(-50%);
        width: 10px;
        height: 10px;
        border-radius: 50%;
        background: #388bfd;
        border: 2px solid var(--bs-body-bg, #1a1d23);
        z-index: 1;
    }
    .route-hop.origin::before { background: #3fb950; }
    .route-hop.relay::before { background: #388bfd; }
    .route-hop.final::before { background: #f85149; }
    .route-hop-server { font-size: .85rem; font-weight: 600; margin-bottom: .2rem; }
    .route-hop-detail { font-size: .78rem; color: #8b949e; }
    .route-hop-delay {
        display: inline-block;
        margin-top: .3rem;
        font-size: .72rem;
        background: rgba(56,139,253,.12);
        color: #58a6ff;
        padding: .15em .5em;
        border-radius: 4px;
    }
    .route-label {
        font-size: .7rem;
        text-transform: uppercase;
        letter-spacing: .05em;
        font-weight: 700;
        padding: .15em .45em;
        border-radius: 4px;
        margin-left: .5rem;
    }
    .label-sender { background: rgba(63,185,80,.15); color: #3fb950; }
    .label-relay { background: rgba(56,139,253,.15); color: #58a6ff; }
    .label-recipient { background: rgba(248,81,73,.15); color: #f85149; }

    .spam-meter {
        height: 8px;
        background: rgba(255,255,255,.08);
        border-radius: 4px;
        overflow: hidden;
        margin-top: .4rem;
    }
    .spam-meter-fill {
        height: 100%;
        border-radius: 4px;
        transition: width .4s ease;
    }
    .no-results-msg {
        text-align: center;
        padding: 3rem 1rem;
        color: #6e7681;
        font-size: .9rem;
    }
</style>

<div class="container" style="max-width: 960px;">
    <h1 class="h4 mb-1 reveal in-view">📧 Email Header Analyzer</h1>
    <p class="text-secondary mb-4 reveal in-view">Paste raw email headers to trace the delivery path, verify authentication (SPF / DKIM / DMARC), check encryption, and inspect spam signals. Everything runs entirely in your browser &mdash; no headers are sent to any server.</p>

    <div class="analysis-card reveal in-view">
        <div class="card-header-custom" style="background:rgba(255,255,255,.03);">
            <span>Paste Email Headers</span>
        </div>
        <div class="card-body-custom">
            <textarea id="hdr-input" class="form-control header-textarea mb-3" rows="10" placeholder="Paste the full raw email headers here...&#10;&#10;Typically found in your email client under &#34;Show Original&#34;, &#34;View Source&#34;, or &#34;Show Headers&#34;.&#10;Looks like:&#10;Return-Path: &lt;sender@example.com&gt;&#10;Received: from mail.example.com (192.168.1.1)&#10;        by mx.recipient.com; Mon, 18 Aug 2026 10:30:00 +0000&#10;DKIM-Signature: v=1; a=rsa-sha256; d=example.com; ..."></textarea>
            <div class="d-flex gap-2">
                <button id="btn-analyze" class="btn btn-primary" onclick="analyzeHeaders()">Analyze</button>
                <button class="btn btn-outline-secondary" onclick="clearAll()">Clear</button>
            </div>
        </div>
    </div>

    <div id="results" class="results-area">
        <div id="card-basic" class="analysis-card reveal"></div>
        <div id="card-routing" class="analysis-card reveal"></div>
        <div id="card-auth" class="analysis-card reveal"></div>
        <div id="card-security" class="analysis-card reveal"></div>
        <div id="card-technical" class="analysis-card reveal"></div>
        <div id="card-spam" class="analysis-card reveal"></div>
    </div>
    <div id="no-results" class="no-results-msg reveal in-view">Paste email headers above and click <strong>Analyze</strong> to begin.</div>
</div>

<script>
(function(){
    window.analyzeHeaders = function() {
        var raw = document.getElementById('hdr-input').value;
        if (!raw.trim()) return;

        var headers = parseHeaders(raw);
        var sections = extractSections(headers);

        renderBasic(sections);
        renderRouting(sections);
        renderAuth(sections);
        renderSecurity(sections);
        renderTechnical(sections);
        renderSpam(sections);

        document.getElementById('results').classList.add('active');
        document.getElementById('no-results').style.display = 'none';
        document.querySelectorAll('.analysis-card.reveal').forEach(function(el, i) {
            setTimeout(function() { el.classList.add('in-view'); }, 60 * i);
        });
    };

    window.clearAll = function() {
        document.getElementById('hdr-input').value = '';
        document.getElementById('results').classList.remove('active');
        document.getElementById('no-results').style.display = '';
        document.querySelectorAll('.analysis-card').forEach(function(el) { el.innerHTML = ''; el.classList.remove('in-view'); });
    };

    function parseHeaders(raw) {
        var lines = raw.split(/\r?\n/);
        var result = {};
        var currentKey = null;
        for (var i = 0; i < lines.length; i++) {
            var line = lines[i];
            if (/^\s+/.test(line) && currentKey) {
                result[currentKey].raw += '\n' + line;
                result[currentKey].value += ' ' + line.replace(/^\s+/, '');
            } else if (/^([A-Za-z0-9\-]+)\s*:\s*(.*)/.test(line)) {
                var m = line.match(/^([A-Za-z0-9\-]+)\s*:\s*([\s\S]*)/);
                var key = m[1].toLowerCase();
                var val = m[2];
                if (!result[key]) {
                    result[key] = { key: m[1], value: val, raw: val };
                } else {
                    if (!Array.isArray(result[key].arr)) {
                        result[key] = { key: result[key].key, value: result[key].value, raw: result[key].raw, arr: [result[key].value] };
                    }
                    result[key].arr.push(val);
                }
                currentKey = key;
            } else {
                currentKey = null;
            }
        }
        for (var k in result) {
            if (result[k].arr) {
                result[k].arr.unshift(result[k].value);
            }
        }
        return result;
    }

    function getAll(headers, name) {
        var h = headers[name.toLowerCase()];
        if (!h) return [];
        if (h.arr) return h.arr;
        return [h.value];
    }

    function getFirst(headers, name) {
        var vals = getAll(headers, name);
        return vals.length ? vals[0] : '';
    }

    function extractSections(headers) {
        var s = {};
        s.messageId = getFirst(headers, 'Message-ID');
        s.date = getFirst(headers, 'Date');
        s.subject = getFirst(headers, 'Subject');
        s.from = getFirst(headers, 'From');
        s.to = getFirst(headers, 'To');
        s.cc = getFirst(headers, 'CC');
        s.bcc = getFirst(headers, 'BCC');
        s.receivedAll = getAll(headers, 'Received');
        s.authResults = getFirst(headers, 'Authentication-Results');
        s.arcResults = getFirst(headers, 'ARC-Authentication-Results');
        s.spf = '';
        s.dkim = '';
        s.dmarc = '';
        s.arc = [];
        var allAuth = s.authResults + ' ' + s.arcResults;
        var spfMatch = allAuth.match(/spf=([\w.]+)/i);
        s.spf = spfMatch ? spfMatch[1].toLowerCase() : '';
        var dkimMatch = allAuth.match(/dkim=([\w.]+)\s+header\.?=([^\s;]+)/i);
        s.dkimResult = dkimMatch ? dkimMatch[1].toLowerCase() : '';
        s.dkimDomain = dkimMatch ? dkimMatch[2] : '';
        var dkimSig = getFirst(headers, 'DKIM-Signature');
        if (!s.dkimResult && dkimSig) {
            var dMatch = dkimMatch ? dkimMatch : dkimSig.match(/\bd=([^\s;]+)/i);
            s.dkimResult = dkimSig ? 'signed' : '';
            s.dkimDomain = dMatch ? dMatch[1] || dMatch[2] || '' : '';
        }
        var dmarcMatch = allAuth.match(/dmarc=([\w.]+)/i);
        s.dmarc = dmarcMatch ? dmarcMatch[1].toLowerCase() : '';
        var dmarcPolicy = getFirst(headers, 'X-Google-DKIM-Message-Signature') || '';
        s.dmarcPolicy = '';
        var dmarcTag = allAuth.match(/p=([\w]+)/i);
        if (dmarcTag) s.dmarcPolicy = dmarcTag[1];
        if (s.arcResults) {
            var arcTags = s.arcResults.match(/arc=\w+/gi) || [];
            s.arc = arcTags.map(function(t){ return t.split('=')[1]; });
        }
        var arcAll = getAll(headers, 'ARC-Authentication-Results');
        arcAll.forEach(function(a) {
            var am = a.match(/arc=(\w+)/gi);
            if (am) am.forEach(function(x){ s.arc.push(x.split('=')[1]); });
        });
        s.tls = '';
        s.tlsCipher = '';
        s.receivedAll.forEach(function(r) {
            if (/TLS=/i.test(r) || /with TLS/i.test(r)) {
                var tlsM = r.match(/TLSv[\d.]+/i) || r.match(/TLS=([\w.]+)/i);
                if (tlsM && !s.tls) s.tls = tlsM[0] || tlsM[1] || '';
                var ciphM = r.match(/cipher=([\w\-]+)/i) || r.match(/with ([\w\-]+) cipher/i);
                if (ciphM && !s.tlsCipher) s.tlsCipher = ciphM[1];
            }
        });
        if (!s.tls) {
            var secMatch = getFirst(headers, 'X-MS-Exchange-Organization-SCL');
            var encMatch = getFirst(headers, 'X-MS-Exchange-Organization-AuthSource');
        }
        s.mailer = getFirst(headers, 'X-Mailer') || getFirst(headers, 'User-Agent');
        s.contentType = getFirst(headers, 'Content-Type');
        s.mimeVersion = getFirst(headers, 'MIME-Version');
        s.priority = getFirst(headers, 'Priority') || getFirst(headers, 'Importance') || getFirst(headers, 'X-Priority');
        s.returnPath = getFirst(headers, 'Return-Path');
        s.listUnsub = getFirst(headers, 'List-Unsubscribe') || getFirst(headers, 'List-Unsubscribe-Post');
        s.xOriginatingIP = getFirst(headers, 'X-Originating-IP');
        s.spamScore = getFirst(headers, 'X-Spam-Score') || getFirst(headers, 'X-SpamLevel');
        s.spamStatus = getFirst(headers, 'X-Spam-Status');
        s.spamFlag = getFirst(headers, 'X-Spam-Flag');
        s.spamReport = getFirst(headers, 'X-Spam-Report');
        s.razorResult = getFirst(headers, 'X-RazorScore') || getFirst(headers, 'X-Razor-Result');
        s.bayesScore = getFirst(headers, 'X-Bayes');
        return s;
    }

    function esc(s) {
        var div = document.createElement('div');
        div.textContent = s;
        return div.innerHTML;
    }

    function kv(label, val) {
        if (!val) return '';
        return '<div class="kv-row"><div class="kv-label">' + esc(label) + '</div><div class="kv-value">' + val + '</div></div>';
    }

    function kvCode(label, val) {
        if (!val) return '';
        return kv(label, '<code>' + esc(val) + '</code>');
    }

    function badge(text, type) {
        return '<span class="badge-' + type + '">' + esc(text) + '</span>';
    }

    function parseNameEmail(str) {
        var m = str.match(/"?([^"<]*)"?\s*(?:<)?([^>]+@[^>]+)>?/);
        if (m) return { name: m[1].trim(), email: m[2].trim() };
        m = str.match(/(?:<)?([^>]+@[^>\s]+)>?/);
        if (m) return { name: '', email: m[1] };
        return { name: str, email: '' };
    }

    function formatDate(dateStr) {
        if (!dateStr) return '';
        try {
            var d = new Date(dateStr);
            if (isNaN(d.getTime())) return esc(dateStr);
            var utc = d.toISOString();
            var local = d.toLocaleString();
            var tz = d.toTimeString().match(/\(([^)]+)\)/);
            return esc(dateStr) + '<br><span class="text-secondary small">UTC: ' + esc(utc) +
                   '<br>Local: ' + esc(local) +
                   (tz ? '<br>TZ: ' + esc(tz[1]) : '') + '</span>';
        } catch(e) { return esc(dateStr); }
    }

    function parseReceivedHeader(str) {
        var hop = {};
        hop.raw = str;
        var fromM = str.match(/from\s+([^\s;(]+)/i);
        hop.from = fromM ? fromM[1] : '';
        var ipM = str.match(/\(([^\)]*)\)/);
        if (ipM) {
            var inner = ipM[1];
            var ipAddr = inner.match(/[\d.]+/) || inner.match(/[\da-fA-F:]+/);
            hop.ip = ipAddr ? ipAddr[0] : inner;
            if (inner.indexOf(hop.from) === -1 && inner.indexOf(hop.ip) === -1) {
                hop.extra = inner;
            }
        } else {
            hop.ip = '';
        }
        var byM = str.match(/by\s+([^\s;(]+)/i);
        hop.by = byM ? byM[1] : '';
        var viaM = str.match(/via\s+([^\s;(]+)/i);
        hop.via = viaM ? viaM[1] : '';
        var dateM = str.match(/;\s*(.+)/);
        hop.dateStr = dateM ? dateM[1].trim() : '';
        try {
            hop.date = hop.dateStr ? new Date(hop.dateStr) : null;
        } catch(e) { hop.date = null; }
        var withM = str.match(/with\s+([^\s;(]+)/i);
        hop.protocol = withM ? withM[1] : '';
        return hop;
    }

    function renderBasic(s) {
        var el = document.getElementById('card-basic');
        var html = '<div class="card-header-custom" style="background:rgba(63,185,80,.08);color:#3fb950;">📋 Basic Info</div><div class="card-body-custom">';
        html += kvCode('Message-ID', s.messageId);
        html += kv('Date / Time', s.date ? formatDate(s.date) : '');
        var fromParsed = parseNameEmail(s.from);
        var fromHtml = fromParsed.email ? '<code>' + esc(fromParsed.email) + '</code>' : '';
        if (fromParsed.name) fromHtml = esc(fromParsed.name) + (fromHtml ? ' &lt;' + fromHtml + '&gt;' : fromHtml);
        else fromHtml = '<code>' + esc(s.from) + '</code>';
        html += kv('From', fromHtml);
        html += kv('Subject', esc(s.subject || '(none)'));
        html += kv('To', s.to ? esc(s.to) : '');
        html += kv('CC', s.cc ? esc(s.cc) : '');
        html += kv('BCC', s.bcc ? esc(s.bcc) : '');
        html += '</div>';
        el.innerHTML = html;
    }

    function renderRouting(s) {
        var el = document.getElementById('card-routing');
        var hops = s.receivedAll.map(parseReceivedHeader);
        hops.reverse();

        var html = '<div class="card-header-custom" style="background:rgba(56,139,253,.08);color:#58a6ff;">🔀 Routing (' + hops.length + ' hops)</div><div class="card-body-custom">';

        if (hops.length === 0) {
            html += '<div class="text-secondary small">No Received headers found.</div></div>';
            el.innerHTML = html;
            return;
        }

        html += '<div class="route-timeline">';
        for (var i = 0; i < hops.length; i++) {
            var h = hops[i];
            var cls = i === 0 ? 'origin' : (i === hops.length - 1 ? 'final' : 'relay');
            var label = i === 0 ? '<span class="route-label label-sender">Sender</span>' :
                        (i === hops.length - 1 ? '<span class="route-label label-recipient">Recipient</span>' :
                         '<span class="route-label label-relay">Relay</span>');

            html += '<div class="route-hop ' + cls + '">';
            html += '<div class="route-hop-server">' + esc(h.from || h.by || 'Unknown') + label + '</div>';
            var details = [];
            if (h.ip) details.push('IP: <code>' + esc(h.ip) + '</code>');
            if (h.by && h.by !== h.from) details.push('by <code>' + esc(h.by) + '</code>');
            if (h.via) details.push('via ' + esc(h.via));
            if (h.protocol) details.push('Proto: ' + esc(h.protocol));
            if (h.extra) details.push('Info: ' + esc(h.extra));
            html += '<div class="route-hop-detail">' + details.join(' &middot; ') + '</div>';
            if (h.dateStr) {
                html += '<div class="route-hop-detail">📅 ' + esc(h.dateStr) + '</div>';
            }
            if (i > 0 && hops[i].date && hops[i-1].date) {
                var diffMs = hops[i].date.getTime() - hops[i-1].date.getTime();
                if (diffMs >= 0) {
                    var secs = Math.round(diffMs / 1000);
                    var delayText = secs < 60 ? secs + 's' : (secs < 3600 ? Math.floor(secs/60) + 'm ' + (secs%60) + 's' : Math.floor(secs/3600) + 'h ' + Math.floor((secs%3600)/60) + 'm');
                    html += '<div class="route-hop-delay">⏱ Delay: ' + delayText + '</div>';
                }
            }
            html += '</div>';
        }
        html += '</div></div>';
        el.innerHTML = html;
    }

    function authBadge(result, label) {
        var type = 'unknown';
        var display = result || 'none';
        if (!result || result === 'none' || result === 'none (null)') {
            type = 'neutral';
            display = 'Not checked';
        } else if (/^(pass|ok|signed)/i.test(result)) {
            type = 'pass';
        } else if (/^(fail|hard.?fail|permerror)/i.test(result)) {
            type = 'fail';
        } else if (/^(softfail|neutral|temperror|skipped)/i.test(result)) {
            type = 'warn';
        }
        return '<span class="badge-' + type + '">' + esc(label) + ': ' + esc(display) + '</span>';
    }

    function renderAuth(s) {
        var el = document.getElementById('card-auth');
        var html = '<div class="card-header-custom" style="background:rgba(227,179,65,.08);color:#e3b341;">🔐 Authentication</div><div class="card-body-custom">';
        html += '<div style="display:flex;flex-wrap:wrap;gap:.5rem;margin-bottom:.75rem;">';
        html += authBadge(s.spf, 'SPF');
        html += authBadge(s.dkimResult, 'DKIM');
        html += authBadge(s.dmarc, 'DMARC');
        html += '</div>';

        var spfExplain = {
            'pass': 'The sending server is authorized to send email for this domain.',
            'fail': 'The sending server is NOT authorized. This may indicate spoofing.',
            'softfail': 'The sending server is not authorized but the policy is not strict. Treat with suspicion.',
            'neutral': 'The domain neither authorizes nor prohibits the sending server.',
            'temperror': 'A temporary error occurred during SPF validation.',
            'permerror': 'A permanent error occurred (e.g., DNS failure or invalid TXT record).',
            'none': 'SPF was not checked or no record exists.'
        };
        html += kv('SPF Explanation', '<span class="text-secondary small">' + esc(spfExplain[s.spf] || 'SPF record not found or not evaluated.') + '</span>');

        if (s.dkimResult && s.dkimResult !== 'none') {
            html += kv('DKIM Domain', s.dkimDomain ? '<code>' + esc(s.dkimDomain) + '</code>' : '');
            html += kv('DKIM Signing', '<span class="text-secondary small">' + esc(
                s.dkimResult === 'pass' || s.dkimResult === 'signed' ? 'The message has a valid DKIM signature from ' + (s.dkimDomain || 'the domain') + '.' :
                s.dkimResult === 'fail' ? 'The DKIM signature did not validate. The message may have been modified or forged.' :
                'DKIM status: ' + s.dkimResult
            ) + '</span>');
        } else {
            var hasDkimSig = getAll({}, '').length;
            html += kv('DKIM Signing', '<span class="text-secondary small">No DKIM-Signature header found, or DKIM was not evaluated.</span>');
        }

        if (s.dmarc) {
            html += kv('DMARC Policy', s.dmarcPolicy ? '<code>' + esc(s.dmarcPolicy) + '</code>' : '');
            var dmarcExplain = {
                'pass': 'The message passed DMARC alignment checks. The From domain\'s DMARC policy is satisfied.',
                'fail': 'The message failed DMARC. The SPF and/or DKIM results did not align with the visible From domain.',
                'none': 'DMARC policy is set to "none" (monitoring only, no enforcement).'
            };
            html += kv('DMARC Explanation', '<span class="text-secondary small">' + esc(dmarcExplain[s.dmarc] || 'DMARC status: ' + s.dmarc) + '</span>');
        } else {
            html += kv('DMARC', '<span class="text-secondary small">No DMARC authentication result found.</span>');
        }

        if (s.arc.length > 0) {
            html += kv('ARC Results', '<span class="text-secondary small">' + esc(s.arc.join(', ')) + '</span>');
        }

        var warnings = [];
        if (!s.spf || s.spf === 'none') warnings.push('⚠ No SPF record found — domain does not restrict who can send mail.');
        if (!s.dkimResult || s.dkimResult === 'none') warnings.push('⚠ No DKIM signature — message authenticity cannot be verified.');
        if (!s.dmarc || s.dmarc === 'none') warnings.push('⚠ No DMARC policy — no domain-level anti-spoofing policy in effect.');
        if (s.spf === 'fail') warnings.push('🔴 SPF failure — the sending IP is NOT authorized for this domain.');
        if (s.dkimResult === 'fail') warnings.push('🔴 DKIM signature invalid — the message may have been tampered with.');
        if (s.dmarc === 'fail') warnings.push('🔴 DMARC failure — the message does not pass domain alignment checks.');

        if (warnings.length) {
            html += '<div style="margin-top:.75rem;padding:.6rem .75rem;border-radius:8px;background:rgba(248,81,73,.08);border:1px solid rgba(248,81,73,.2);">';
            html += '<div class="small fw-semibold mb-1" style="color:#f85149;">Warnings</div>';
            warnings.forEach(function(w) { html += '<div class="small text-secondary">' + esc(w) + '</div>'; });
            html += '</div>';
        }

        html += '</div>';
        el.innerHTML = html;
    }

    function renderSecurity(s) {
        var el = document.getElementById('card-security');
        var html = '<div class="card-header-custom" style="background:rgba(248,81,73,.08);color:#f85149;">🛡 Security</div><div class="card-body-custom">';

        var tlsVersions = [];
        var ciphers = [];
        s.receivedAll.forEach(function(r) {
            var vM = r.match(/TLSv[\d.]+/gi);
            if (vM) vM.forEach(function(v){ if (tlsVersions.indexOf(v) === -1) tlsVersions.push(v); });
            var cM = r.match(/cipher=([^\s;,]+)/gi);
            if (cM) cM.forEach(function(c) {
                var cv = c.split('=')[1] || c;
                if (ciphers.indexOf(cv) === -1) ciphers.push(cv);
            });
        });
        var hasTLS = tlsVersions.length > 0 || s.tls;

        html += kv('TLS Used', hasTLS ?
            '<span style="color:#3fb950;">✅ Yes — message was encrypted in transit</span>' :
            '<span style="color:#e3b341;">⚠ Could not confirm TLS (may still be encrypted; check individual Received hops)</span>');
        if (tlsVersions.length) html += kvCode('TLS Version(s)', tlsVersions.join(', '));
        else if (s.tls) html += kvCode('TLS Version', s.tls);
        if (ciphers.length) html += kvCode('Cipher Suite(s)', ciphers.join(', '));
        else if (s.tlsCipher) html += kvCode('Cipher Suite', s.tlsCipher);

        var secWarnings = [];
        var spf = s.spf;
        var dkim = s.dkimResult;
        var dmarc = s.dmarc;
        if (!spf || spf === 'none') secWarnings.push('No SPF authentication — sender identity not verified.');
        if (!dkim || dkim === 'none') secWarnings.push('No DKIM signature — message integrity not verified.');
        if (!dmarc || dmarc === 'none') secWarnings.push('No DMARC policy — no domain-level spoofing protection.');
        if (!hasTLS) secWarnings.push('No TLS information found in Received headers — encryption in transit is unconfirmed.');
        if (s.returnPath && s.from) {
            var rpDomain = (s.returnPath.match(/@([^>]+)/) || [])[1] || '';
            var fromDomain = (s.from.match(/@([^>]+)/) || [])[1] || '';
            if (rpDomain && fromDomain && rpDomain.toLowerCase() !== fromDomain.toLowerCase()) {
                secWarnings.push('Return-Path domain (' + rpDomain + ') differs from From domain (' + fromDomain + ').');
            }
        }

        if (secWarnings.length) {
            html += '<div style="margin-top:.75rem;padding:.6rem .75rem;border-radius:8px;background:rgba(227,179,65,.08);border:1px solid rgba(227,179,65,.2);">';
            html += '<div class="small fw-semibold mb-1" style="color:#e3b341;">Security Notes</div>';
            secWarnings.forEach(function(w) { html += '<div class="small text-secondary">⚠ ' + esc(w) + '</div>'; });
            html += '</div>';
        } else if (hasTLS) {
            html += '<div style="margin-top:.75rem;padding:.6rem .75rem;border-radius:8px;background:rgba(63,185,80,.08);border:1px solid rgba(63,185,80,.2);">';
            html += '<div class="small" style="color:#3fb950;">✅ TLS encryption detected. Authentication checks passed.</div>';
            html += '</div>';
        }

        html += '</div>';
        el.innerHTML = html;
    }

    function renderTechnical(s) {
        var el = document.getElementById('card-technical');
        var html = '<div class="card-header-custom" style="background:rgba(139,148,158,.08);color:#8b949e;">⚙ Technical Details</div><div class="card-body-custom">';
        html += kvCode('X-Mailer / User-Agent', s.mailer);
        html += kvCode('Content-Type', s.contentType);
        html += kvCode('MIME-Version', s.mimeVersion);
        html += kv('Priority / Importance', s.priority ? esc(s.priority) : '');
        html += kvCode('Return-Path', s.returnPath);
        if (s.listUnsub) {
            var unsubLinks = s.listUnsub.match(/<([^>]+)>/g);
            var unsubHtml = '';
            if (unsubLinks) {
                unsubHtml = unsubLinks.map(function(l) {
                    var url = l.replace(/[<>]/g, '');
                    return '<code style="word-break:break-all;">' + esc(url) + '</code>';
                }).join('<br>');
            } else {
                unsubHtml = '<code>' + esc(s.listUnsub) + '</code>';
            }
            html += kv('List-Unsubscribe', unsubHtml);
        }
        html += kvCode('X-Originating-IP', s.xOriginatingIP);
        html += '</div>';
        el.innerHTML = html;
    }

    function renderSpam(s) {
        var el = document.getElementById('card-spam');
        var html = '<div class="card-header-custom" style="background:rgba(218,54,51,.08);color:#f85149;">🚫 Spam Analysis</div><div class="card-body-custom">';

        var score = 0;
        var hasScore = false;
        if (s.spamScore) {
            score = parseFloat(s.spamScore);
            if (!isNaN(score)) hasScore = true;
        }
        if (!hasScore && s.spamStatus) {
            var m = s.spamScore.match ? s.spamScore : '';
            var sfM = s.spamStatus.match(/score=([\d.+-]+)/);
            if (sfM) { score = parseFloat(sfM[1]); hasScore = true; }
        }
        if (!hasScore && s.spamScore) {
            var scM = s.spamScore.match(/([\d.]+)/);
            if (scM) { score = parseFloat(scM[1]); hasScore = true; }
        }

        if (hasScore) {
            var meterPct = Math.min(score * 5, 100);
            var meterColor = score < 3 ? '#3fb950' : (score < 5 ? '#e3b341' : score < 10 ? '#d29922' : '#f85149');
            var verdict = score < 3 ? 'Likely not spam' : (score < 5 ? 'Possibly spam' : score < 10 ? 'Likely spam' : 'Very likely spam');
            html += kvCode('Spam Score', s.spamScore);
            html += '<div class="spam-meter"><div class="spam-meter-fill" style="width:' + meterPct + '%;background:' + meterColor + ';"></div></div>';
            html += '<div class="small mt-1" style="color:' + meterColor + ';">' + esc(verdict) + ' (scores below 3 are generally clean; above 5 is suspicious)</div>';
        } else {
            html += kv('Spam Score', '<span class="text-secondary small">No X-Spam-Score header found.</span>');
        }

        if (s.spamStatus) html += kv('SpamAssassin Status', '<code class="small">' + esc(s.spamStatus) + '</code>');
        if (s.spamFlag) html += kvCode('Spam Flag', s.spamFlag);
        if (s.spamReport) html += kv('Spam Report', '<pre style="white-space:pre-wrap;margin:0;font-size:.78rem;max-height:200px;overflow:auto;background:rgba(0,0,0,.2);padding:.5rem;border-radius:4px;">' + esc(s.spamReport) + '</pre>');
        if (s.razorResult) html += kvCode('Razor Result', s.razorResult);
        if (s.bayesScore) html += kvCode('Bayesian Score', s.bayesScore);

        if (!hasScore && !s.spamStatus && !s.spamFlag && !s.spamReport) {
            html += '<div class="text-secondary small">No SpamAssassin or spam-related headers detected in these headers. This does not necessarily mean the email is safe &mdash; spam filtering may occur at a different layer.</div>';
        }

        html += '</div>';
        el.innerHTML = html;
    }
})();
</script>
<?php page_footer(); ?>
