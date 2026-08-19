<?php
require_once __DIR__ . '/../../functions.php';

start_session();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');

    if (!csrf_verify()) {
        echo json_encode(['error' => 'Invalid CSRF token.']);
        exit;
    }
    if (!rate_limit_check('secheaders', 6, 60)) {
        echo json_encode(['error' => 'Rate limit reached. Wait a moment.']);
        exit;
    }

    $url = trim((string)($_POST['url'] ?? ''));
    if ($url === '') {
        echo json_encode(['error' => 'Enter a URL.']);
        exit;
    }
    if (!preg_match('#^https?://#i', $url)) {
        $url = 'http://' . $url;
    }
    $url = filter_var($url, FILTER_VALIDATE_URL);
    if (!$url || !parse_url($url, PHP_URL_HOST)) {
        echo json_encode(['error' => 'Invalid URL.']);
        exit;
    }
    log_activity('tool_security_headers', parse_url($url, PHP_URL_HOST));

    $headers = [];
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_TIMEOUT => 14,
        CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 5,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => 0,
        CURLOPT_NOBODY => true,
        CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
        CURLOPT_HEADERFUNCTION => function ($curl, $header) use (&$headers) {
            $parts = explode(':', $header, 2);
            if (count($parts) === 2) {
                $headers[strtolower(trim($parts[0]))] = trim($parts[1]);
            }
            return strlen($header);
        },
    ]);
    curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $final = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
    curl_close($ch);

    $tests = [];
    $score = 0;
    $max = 0;

    function sec_test(array &$tests, string $label, bool $ok, string $detail, int $weight, string $help, string $raw = '') {
        $tests[] = [
            'label' => $label,
            'ok' => $ok,
            'detail' => $detail,
            'weight' => $weight,
            'help' => $help,
            'raw' => mb_substr($raw, 0, 400),
        ];
    }

    foreach ([
        'content-security-policy' => ['Content-Security-Policy', 'Controls what scripts/styles/content the page may load — the single most powerful XSS defense.'],
        'strict-transport-security' => ['Strict-Transport-Security', 'Forces the browser to only use HTTPS (HSTS). Must be set over HTTPS to have effect.'],
        'x-frame-options' => ['X-Frame-Options', 'Prevents the page being framed by other sites (clickjacking).'],
        'x-content-type-options' => ['X-Content-Type-Options', 'Stops browsers from MIME-sniffing — blocks some drive-by content-type attacks.'],
        'referrer-policy' => ['Referrer-Policy', 'Controls how much of the URL leaks in the Referer header to other sites.'],
        'permissions-policy' => ['Permissions-Policy', 'Restricts which browser features (camera, mic, geolocation) the page and frames can use.'],
    ] as $k => $cfg) {
        $max += 15;
        if (isset($headers[$k]) && $headers[$k] !== '') {
            $score += 15;
            sec_test($tests, $cfg[0], true, 'Present', 15, $cfg[1], $headers[$k]);
        } else {
            sec_test($tests, $cfg[0], false, 'Missing', 15, $cfg[1]);
        }
    }

    foreach (['cross-origin-opener-policy' => 'Cross-Origin-Opener-Policy', 'cross-origin-embedder-policy' => 'Cross-Origin-Embedder-Policy', 'cross-origin-resource-policy' => 'Cross-Origin-Resource-Policy'] as $k => $label) {
        $max += 5;
        if (isset($headers[$k]) && $headers[$k] !== '') {
            $score += 5;
            sec_test($tests, $label, true, 'Present', 5, 'Cross-origin isolation header restricting how other origins can interact.', $headers[$k]);
        } else {
            sec_test($tests, $label, false, 'Missing', 5, 'Cross-origin isolation header restricting how other origins can interact.');
        }
    }

    // Leaky / dangerous headers
    foreach (['server' => 'Server', 'x-powered-by' => 'X-Powered-By', 'x-aspnet-version' => 'X-AspNet-Version', 'via' => 'Via'] as $k => $label) {
        if (isset($headers[$k]) && $headers[$k] !== '') {
            sec_test($tests, $label . ' (info disclosure)', false, 'Present: ' . $headers[$k], 3, 'Reveals server technology and versions to attackers. Consider hiding it.', $headers[$k]);
        }
        // note: no score penalty counted in max for these
    }

    $grade = 'F';
    $pct = $max > 0 ? round(($score / $max) * 100) : 0;
    if ($pct >= 90) $grade = 'A';
    elseif ($pct >= 75) $grade = 'B';
    elseif ($pct >= 60) $grade = 'C';
    elseif ($pct >= 40) $grade = 'D';

    echo json_encode([
        'url' => $url,
        'final_url' => $final,
        'code' => $code,
        'grade' => $grade,
        'pct' => $pct,
        'tests' => $tests,
        'headers' => array_map(function ($v) { return mb_substr($v, 0, 300); }, $headers),
    ]);
    exit;
}

page_header('Security Headers Checker');
?>
<style>
.sh-body h2.h5 { margin-top: 1rem; }
.sh-row { display:flex; gap:.7rem; align-items:flex-start; padding:.55rem 0; border-bottom:1px solid rgba(255,255,255,.04); }
.sh-row:last-child { border-bottom:none; }
.sh-badge { flex:0 0 auto; width:70px; text-align:center; padding:3px 0; border-radius:6px; font-size:.72rem; font-weight:700; }
.ok { background:rgba(38,208,124,.12); color:#26d07c; border:1px solid rgba(38,208,124,.35); }
.bad { background:rgba(231,76,60,.12); color:#e74c3c; border:1px solid rgba(231,76,60,.4); }
.sh-main { flex:1 1 auto; min-width:0; }
.sh-label { font-weight:600; font-size:.88rem; }
.sh-detail { font-size:.78rem; color:#94b0c9; }
.cpn { cursor:pointer; }
.cpn:hover { text-decoration:underline; }
</style>
<div class="container" style="max-width: 880px;">
    <h1 class="h4 mb-2 reveal in-view">&#128737; Security Headers Checker</h1>
    <p class="text-secondary mb-3 reveal in-view">Fetches a URL and grades its HTTP security headers (CSP, HSTS, X-Frame-Options, COOP/CORP and more) against a 100-point baseline, with copyable fix instructions.</p>

    <div class="card reveal in-view"><div class="card-body">
        <form id="shForm" class="mb-0">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <div class="row g-2">
                <div class="col-md-8">
                    <input class="form-control" name="url" placeholder="https://example.com" required>
                </div>
                <div class="col-md-4">
                    <button class="btn btn-primary w-100" type="submit">Scan</button>
                </div>
            </div>
        </form>
    </div></div>

    <div id="shErr" class="alert alert-danger mt-3 d-none"></div>

    <div class="card mt-3 d-none" id="shCard"><div class="card-body sh-body">
        <div class="mb-3 d-flex align-items-center gap-3">
            <div style="font-size:3rem;font-weight:800;line-height:1" id="shGrade">—</div>
            <div>
                <div class="text-secondary" id="shPct">0%</div>
                <div class="text-secondary small">baseline score</div>
                <div class="text-secondary small" id="shUrl"></div>
            </div>
        </div>
        <h2 class="h5">Results</h2>
        <div id="shList"></div>
        <h2 class="h5">Raw Headers <button class="btn btn-sm btn-outline-secondary" type="button" id="shCopy">Copy</button></h2>
        <pre id="shRaw" class="p-3" style="font-size:.72rem;max-height:260px;overflow:auto;background:rgba(255,255,255,.03);border:1px solid var(--line);border-radius:8px;white-space:pre-wrap;word-break:break-all">—</pre>
    </div></div>
</div>
<script>
(function(){
    var form=document.getElementById('shForm');
    var err=document.getElementById('shErr');
    var card=document.getElementById('shCard');
    form.addEventListener('submit',function(e){
        e.preventDefault();
        err.classList.add('d-none');
        card.classList.add('d-none');
        fetch('index.php',{method:'POST',body:new FormData(form)})
            .then(function(r){return r.json();})
            .then(function(d){
                if(d.error){ err.textContent=d.error; err.classList.remove('d-none'); return; }
                var g=document.getElementById('shGrade');
                g.textContent=d.grade;
                g.style.color=d.grade==='A'?'#26d07c':(d.grade==='B'?'#85e0a3':(d.grade==='C'?'#ffc107':'#e74c3c'));
                document.getElementById('shPct').textContent=d.pct+'%';
                document.getElementById('shUrl').textContent=(d.code? 'HTTP '+d.code+' — ' : '')+d.url;
                var list=document.getElementById('shList');
                list.innerHTML=d.tests.map(function(t){
                    var cls=t.ok?'ok':'bad', lbl=t.ok?'PRESENT':'MISSING';
                    return '<div class="sh-row"><span class="sh-badge '+cls+'">'+lbl+'</span><div class="sh-main">'+
                        '<div class="sh-label">'+esc(t.label)+'</div>'+
                        '<div class="sh-detail">'+esc(t.detail)+'</div>'+
                        (t.raw?'<div class="sh-detail cpn" onclick="copyText(this)" title="click to copy" style="font-family:monospace">'+esc(t.raw)+'</div>':'')+
                        '<div class="sh-detail mt-1" style="color:var(--dim)">Fix: '+esc(t.help)+'</div></div></div>';
                }).join('');
                var raw='';
                Object.keys(d.headers).forEach(function(k){ raw+=k+': '+d.headers[k]+'\n'; });
                document.getElementById('shRaw').textContent=raw||'(no headers captured)';
                card.classList.remove('d-none');
            })
            .catch(function(){ err.textContent='Network error. Try again.'; err.classList.remove('d-none'); });
    });
    document.getElementById('shCopy').addEventListener('click',function(){
        var t=document.getElementById('shRaw').textContent;
        navigator.clipboard.writeText(t).catch(function(){});
    });
    window.copyText=function(el){ navigator.clipboard.writeText(el.textContent).catch(function(){}); };
    function esc(s){ var e=document.createElement('div'); e.appendChild(document.createTextNode(s==null?'':String(s))); return e.innerHTML; }
})();
</script>
<?php page_footer(); ?>