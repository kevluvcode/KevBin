<?php
require_once __DIR__ . '/../../functions.php';

start_session();

const LB_MAX_HOPS = 30;
const LB_BODY_CAP = 524288; // first 512 KB is plenty to find meta-refresh / JS redirects

// ---- helpers ---------------------------------------------------------

// Block private/reserved destinations (SSRF guard) — mirrors site-viewer.
function lb_host_ok(string $host): bool
{
    $ip = @gethostbyname($host);
    if ($ip === $host) {
        $ip = '';
    }
    if ($ip === '' || filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
        return false;
    }
    $bad = ['0.', '10.', '127.', '169.254.', '172.16.', '172.17.', '172.18.', '172.19.', '172.20.', '172.21.', '172.22.', '172.23.', '172.24.', '172.25.', '172.26.', '172.27.', '172.28.', '172.29.', '172.30.', '172.31.', '192.168.', '100.64.', '224.', '225.', '226.', '227.', '228.', '229.', '230.', '231.', '232.', '233.', '234.', '235.', '236.', '237.', '238.', '239.', '240.', '241.', '242.', '243.', '244.', '245.', '246.', '247.', '248.', '249.', '250.', '251.', '252.', '253.', '254.', '255.'];
    foreach ($bad as $p) {
        if (str_starts_with($ip, $p)) {
            return false;
        }
    }
    return true;
}

function lb_abs(string $u, string $base): string
{
    if (preg_match('#^https?://#i', $u)) {
        return $u;
    }
    if (str_starts_with($u, '//')) {
        return (parse_url($base, PHP_URL_SCHEME)) . ':' . $u;
    }
    $parts = parse_url($base);
    $scheme = $parts['scheme'] ?? 'https';
    $host = $parts['host'] ?? '';
    $port = isset($parts['port']) ? ':' . $parts['port'] : '';
    $path = isset($parts['path']) ? $parts['path'] : '/';
    if (str_starts_with($u, '/')) {
        return $scheme . '://' . $host . $port . $u;
    }
    $dir = substr($path, 0, strrpos($path, '/') + 1);
    if ($dir === '') {
        $dir = '/';
    }
    return $scheme . '://' . $host . $port . $dir . $u;
}

function lb_find_hop(string $body, string $base): ?array
{
    // Classic link-wall door #1: <meta http-equiv="refresh" content="0; url=...">
    if (preg_match('~<meta[^>]*http-equiv\s*=\s*["\']?refresh["\']?[^>]*>~i', $body, $mm)) {
        if (preg_match('~content\s*=\s*["\']([^"\']*)["\']~i', $mm[0], $cm)) {
            if (preg_match('~url\s*=\s*([^;]+)~i', $cm[1], $um)) {
                $t = trim($um[1], " \t\r\n\"'");
                if ($t !== '' && $t !== $base) {
                    $abs = lb_abs($t, $base);
                    if (preg_match('#^https?://#i', $abs)) {
                        return ['type' => 'meta', 'url' => $abs];
                    }
                }
            }
        }
    }
    // Classic link-wall door #2: JavaScript location assignment (runs silently in a real browser).
    $js = [
        '~location\.replace\s*\(\s*(["\'])(.*?)\1~i',
        '~location\.href\s*=\s*(["\'])(.*?)\1~i',
        '~window\.location\.href\s*=\s*(["\'])(.*?)\1~i',
        '~window\.location\s*=\s*(["\'])(.*?)\1~i',
    ];
    foreach ($js as $rx) {
        if (preg_match($rx, $body, $m)) {
            $t = $m[2] ?? '';
            if ($t === '' || $t === $base) {
                continue;
            }
            $abs = lb_abs($t, $base);
            if (preg_match('#^https?://#i', $abs)) {
                return ['type' => 'js', 'url' => $abs];
            }
        }
    }
    return null;
}

function lb_step(string $url, array &$err): array
{
    $hdrs = [];
    $loc = null;
    $srv = null;
    $ctype = null;
    $body = '';
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 12,
        CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => 0,
        CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
        CURLOPT_HEADERFUNCTION => function ($curl, $h) use (&$hdrs, &$loc, &$srv, &$ctype) {
            $hp = explode(':', $h, 2);
            if (count($hp) === 2) {
                $k = strtolower(trim($hp[0]));
                $v = trim($hp[1]);
                $hdrs[$k] = $v;
                if ($k === 'location') $loc = $v;
                if ($k === 'server') $srv = $v;
                if ($k === 'content-type') $ctype = $v;
            }
            return strlen($h);
        },
        CURLOPT_WRITEFUNCTION => function ($curl, $data) use (&$body) {
            if (strlen($body) + strlen($data) > LB_BODY_CAP) {
                return 0;
            }
            $body .= $data;
            return strlen($data);
        },
    ]);
    curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $final = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL) ?: $url;
    curl_close($ch);

    return [
        'code' => $code,
        'location' => $loc,
        'server' => $srv,
        'ctype' => $ctype,
        'body' => $body,
        'final' => $final,
    ];
}

// ---- POST handler ----------------------------------------------------

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');

    if (!csrf_verify()) {
        echo json_encode(['error' => 'Invalid CSRF token.']);
        exit;
    }
    if (!rate_limit_check('linkbyp', 10, 60)) {
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
    $host = $url ? parse_url($url, PHP_URL_HOST) : null;
    if (!$url || !$host) {
        echo json_encode(['error' => 'Invalid URL.']);
        exit;
    }
    if (!lb_host_ok($host)) {
        echo json_encode(['error' => 'That host resolves to a private/reserved address and cannot be checked here.']);
        exit;
    }
    log_activity('tool_link_bypass', $host);

    $hops = [];
    $visited = [];
    $current = $url;
    $err = [];

    for ($i = 0; $i < LB_MAX_HOPS; $i++) {
        $key = strtolower($current);
        if (isset($visited[$key])) {
            $hops[] = ['type' => 'loop', 'url' => $current, 'note' => 'Loop detected — already visited this URL.'];
            break;
        }
        $visited[$key] = true;

        $step = lb_step($current, $err);
        $advance = null;
        $hopType = 'final';
        $note = null;

        if ($step['code'] === 0) {
            $hops[] = ['type' => 'error', 'url' => $current, 'status' => 0, 'note' => 'No response / timeout.'];
            break;
        }

        if ($step['code'] >= 300 && $step['code'] < 400 && $step['location'] !== null) {
            // Plain HTTP redirect — the "cheap" door used by most shorteners.
            $loc = $step['location'];
            $advance = preg_match('#^https?://#i', $loc) ? $loc : rtrim($current, '/') . $loc;
            $hopType = 'http';
            $note = 'Standard HTTP redirect (' . $step['code'] . ').';
        } elseif ($step['code'] === 200 || $step['code'] === 302 || $step['code'] === 303) {
            // Look for the door itself in the returned HTML.
            $next = lb_find_hop($step['body'], $step['final']);
            if ($next !== null) {
                $advance = $next['url'];
                $hopType = $next['type'];
                $note = $hopType === 'meta'
                    ? 'Meta-refresh door in the HTML (link walls use this to bounce you).'
                    : 'JavaScript redirect in the page (a browser would run it silently).';
            }
        }

        $hops[] = [
            'type' => $hopType,
            'url' => $current,
            'status' => $step['code'],
            'server' => $step['server'],
            'ctype' => $step['ctype'],
            'next' => $advance,
            'note' => $note,
        ];

        if ($advance === null) {
            break;
        }
        $nextHost = parse_url($advance, PHP_URL_HOST);
        if (!$nextHost || !lb_host_ok($nextHost)) {
            $hops[count($hops) - 1]['note'] = 'Destination resolves to a private/reserved address — stopped.';
            break;
        }
        $current = $advance;
    }

    $finalHop = $hops[count($hops) - 1] ?? null;
    $title = '';
    if ($finalHop !== null && ($finalHop['type'] ?? '') === 'final' && !empty($step['body'])) {
        if (preg_match('~<title[^>]*>(.*?)</title>~is', $step['body'], $mt)) {
            $title = trim(strip_tags($mt[1]));
        }
    }

    echo json_encode([
        'input' => $url,
        'rounds' => count($hops),
        'final' => $finalHop['url'] ?? $url,
        'title' => mb_substr($title, 0, 300),
        'hops' => $hops,
    ]);
    exit;
}

page_header('Link Bypasser');
?>
<style>
.lb-step { border-left:3px solid #5865f2; padding:8px 14px; margin-left:6px; margin-bottom:10px; background:rgba(88,101,242,.06); }
.lb-http { border-left-color:#f1c40f; }
.lb-meta { border-left-color:#e67e22; }
.lb-js { border-left-color:#9b59b6; }
.lb-final { border-left-color:#2ecc71; }
.lb-bad { border-left-color:#e74c3c; }
</style>
<div class="container" style="max-width: 860px;">
    <h1 class="h4 mb-2 reveal in-view">&#128279;&#65039; Link Bypasser <span class="text-secondary" style="font-size:.85rem">(redirect-wall tracer)</span></h1>
    <p class="text-secondary mb-3 reveal in-view">Paste any URL and this tool walks the whole chain the way a browser would — following <em>HTTP redirects</em>, then looking inside each page for the two other "doors" link walls use: <strong>meta-refresh</strong> and <strong>JavaScript <code>location</code></strong> redirects. It reveals where the link really wants to take you, and explains each step. Educational only — pages protected by real anti-bot checks or captchas will (correctly) stop still.</p>

    <div class="card reveal in-view"><div class="card-body">
        <form id="lbForm" class="mb-0">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <div class="row g-2">
                <div class="col-md-8">
                    <input class="form-control" name="url" placeholder="https://example.com/..." required>
                </div>
                <div class="col-md-4">
                    <button class="btn btn-primary w-100" type="submit">Trace chain</button>
                </div>
            </div>
        </form>
    </div></div>

    <div id="lbErr" class="alert alert-danger mt-3 d-none"></div>
    <div class="card mt-3 d-none" id="lbCard"><div class="card-body" id="lbBody"></div></div>

    <div class="card mt-3 reveal in-view"><div class="card-body">
        <h2 class="h6 mb-2">⚠️ How it works &amp; limits</h2>
        <ul class="small text-secondary ps-3 mb-0">
            <li>Fetches each hop server-side, capped at 512 KB, up to 30 rounds with loop detection.</li>
            <li>Anti-bot challenges (Cloudflare, hCaptcha...), paywalls and login gates are <em>not</em> bypassed — they exist to stop exactly this, and that's fine.</li>
            <li>Use it to understand suspicious links and shortener walls. Only follow links you're allowed to look at.</li>
        </ul>
    </div></div>
</div>
<script>
(function(){
    var form=document.getElementById('lbForm');
    var err=document.getElementById('lbErr');
    var card=document.getElementById('lbCard');
    var badges={http:'bg-warning text-dark',meta:'bg-warning text-dark',js:'bg-primary',final:'bg-success',loop:'bg-secondary',error:'bg-danger'};
    var side={http:'lb-http',meta:'lb-meta',js:'lb-js',final:'lb-final',loop:'lb-bad',error:'lb-bad'};
    form.addEventListener('submit',function(e){
        e.preventDefault();
        err.classList.add('d-none'); card.classList.add('d-none');
        var btn=form.querySelector('button'); btn.disabled=true;
        fetch('index.php',{method:'POST',body:new FormData(form)})
            .then(function(r){return r.json();})
            .then(function(d){
                btn.disabled=false;
                if(d.error){ err.textContent=d.error; err.classList.remove('d-none'); return; }
                var h='<div class="mb-3 small text-secondary">Started from <code>'+esc(d.input)+'</code> — <strong>'+d.rounds+'</strong> step'+(d.rounds===1?'':'s')+' in the chain.</div>';
                d.hops.forEach(function(s,i){
                    h+='<div class="lb-step '+(side[s.type]||'')+'">';
                    h+='<span class="badge '+(badges[s.type]||'bg-secondary')+'">#'+(i+1)+'</span> ';
                    h+='<span class="text-secondary small">'+esc(lbl(s.type))+'</span>';
                    h+='<div class="mt-1"><code class="fs-9">'+esc(s.url)+'</code></div>';
                    h+='<div class="small text-secondary mt-1">';
                    if(s.status) h+='HTTP <strong>'+s.status+'</strong>';
                    if(s.server) h+=' · server: <code>'+esc(s.server)+'</code>';
                    if(s.ctype) h+=' · type: <code>'+esc(s.ctype)+'</code>';
                    if(s.note) h+='<div>💡 '+esc(s.note)+'</div>';
                    if(s.next) h+='<div class="mt-1">&#129046; Next door: <code class="text-warning">'+esc(s.next)+'</code></div>';
                    h+='</div></div>';
                });
                if(d.title) h+='<div class="mt-3 small"><strong>Final destination title:</strong> <em>'+esc(d.title)+'</em></div>';
                h+='<div class="mt-2 small"><strong>End of chain:</strong> <code class="text-success">'+esc(d.final)+'</code></div>';
                document.getElementById('lbBody').innerHTML=h;
                card.classList.remove('d-none');
            })
            .catch(function(){ btn.disabled=false; err.textContent='Network error. Try again.'; err.classList.remove('d-none'); });
    });
    function lbl(t){ return {http:'HTTP redirect',meta:'Meta-refresh door',js:'JavaScript redirect',final:'Final destination',loop:'Loop',error:'Error'}[t]||t; }
    function esc(s){ var e=document.createElement('div'); e.appendChild(document.createTextNode(s==null?'':String(s))); return e.innerHTML; }
})();
</script>
<?php page_footer(); ?>