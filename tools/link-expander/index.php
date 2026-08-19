<?php
require_once __DIR__ . '/../../functions.php';

start_session();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');

    if (!csrf_verify()) {
        echo json_encode(['error' => 'Invalid CSRF token.']);
        exit;
    }
    if (!rate_limit_check('linkexp', 6, 60)) {
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
    $in = filter_var($url, FILTER_VALIDATE_URL);
    if (!$in || !parse_url($in, PHP_URL_HOST)) {
        echo json_encode(['error' => 'Invalid URL.']);
        exit;
    }
    log_activity('tool_link_expander', parse_url($in, PHP_URL_HOST));

    $hops = [];
    $visited = [];
    $current = $in;
    $max = 10;

    while (count($hops) < $max) {
        $key = strtolower($current);
        if (isset($visited[$key])) {
            $hops[] = ['url' => $current, 'status' => 0, 'server' => null, 'redirect' => null, 'ip' => null, 'note' => 'Loop detected — already visited.'];
            break;
        }
        $visited[$key] = true;

        $hdrs = [];
        $srv = null;
        $loc = null;
        $body = '';
        $ch = curl_init($current);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 12,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_HEADER => false,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            CURLOPT_HEADERFUNCTION => function ($curl, $h) use (&$hdrs, &$srv, &$loc) {
                $p = explode(':', $h, 2);
                if (count($p) === 2) {
                    $k = strtolower(trim($p[0]));
                    $v = trim($p[1]);
                    $hdrs[$k] = $v;
                    if ($k === 'server') $srv = $v;
                    if ($k === 'location') $loc = $v;
                }
                return strlen($h);
            },
            CURLOPT_WRITEFUNCTION => function ($curl, $data) use (&$body) {
                $body .= $data;
                return strlen($body) > 131072 ? 0 : strlen($data);
            },
        ]);
        $ip = null;
        $dns = @dns_get_record(parse_url($current, PHP_URL_HOST), DNS_A);
        if ($dns && isset($dns[0]['ip'])) {
            $ip = $dns[0]['ip'];
        }
        curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);

        $hops[] = [
            'url' => $current,
            'status' => $code,
            'server' => $srv,
            'redirect' => $loc,
            'ip' => $ip,
            'ctype' => isset($hdrs['content-type']) ? $hdrs['content-type'] : null,
            'note' => $code === 0 ? 'No response / timeout.' : null,
        ];

        if ($code >= 300 && $code < 400 && $loc) {
            $current = filter_var($loc, FILTER_VALIDATE_URL) ? $loc : rtrim($current, '/') . $loc;
        } else {
            break;
        }
    }

    echo json_encode([
        'input' => $in,
        'final' => $hops[count($hops) - 1]['url'],
        'total_hops' => count($hops),
        'hops' => $hops,
    ]);
    exit;
}

page_header('Link Expander');
?>
<style>
.lx-step { border-left:3px solid #5865f2; padding:8px 14px; margin-left:6px; margin-bottom:10px; background:rgba(88,101,242,.06); }
.lx-step-ok { border-left-color:#2ecc71; }
.lx-step-red { border-left-color:#e74c3c; }
</style>
<div class="container" style="max-width: 860px;">
    <h1 class="h4 mb-2 reveal in-view">&#129147; Link Expander</h1>
    <p class="text-secondary mb-3 reveal in-view">Follow a suspicious short URL step by step to reveal where it really goes. Shows every redirect hop, final destination, server, resolved IP and loop protection.</p>

    <div class="card reveal in-view"><div class="card-body">
        <form id="lxForm" class="mb-0">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <div class="row g-2">
                <div class="col-md-8">
                    <input class="form-control" name="url" placeholder="https://bit.ly/..." required>
                </div>
                <div class="col-md-4">
                    <button class="btn btn-primary w-100" type="submit">Expand</button>
                </div>
            </div>
        </form>
    </div></div>

    <div id="lxErr" class="alert alert-danger mt-3 d-none"></div>
    <div class="card mt-3 d-none" id="lxCard"><div class="card-body" id="lxBody"></div></div>
</div>
<script>
(function(){
    var form=document.getElementById('lxForm');
    var err=document.getElementById('lxErr');
    var card=document.getElementById('lxCard');
    form.addEventListener('submit',function(e){
        e.preventDefault();
        err.classList.add('d-none');
        card.classList.add('d-none');
        fetch('index.php',{method:'POST',body:new FormData(form)})
            .then(function(r){return r.json();})
            .then(function(d){
                if(d.error){ err.textContent=d.error; err.classList.remove('d-none'); return; }
                var h='<div class="mb-3 small text-secondary">Started from <code>'+esc(d.input)+'</code> and followed <strong>'+d.total_hops+'</strong> hop'+(d.total_hops===1?'':'s')+'</div>';
                d.hops.forEach(function(step,i){
                    var cls=step.status>=300&&step.status<400?'lx-step':'lx-step-ok';
                    if(step.status===0) cls='lx-step-red';
                    h+='<div class="'+cls+'">';
                    h+='<span class="badge '+(step.status===0?'bg-danger':step.status>=300&&step.status<400?'bg-warning text-dark':'bg-success')+'">#'+(i+1)+'</span> ';
                    h+='<code class="fs-9">'+esc(step.url)+'</code>';
                    h+='<div class="small text-secondary mt-1">';
                    h+='HTTP <strong>'+step.status+'</strong>';
                    if(step.server) h+=' · server: <code>'+esc(step.server)+'</code>';
                    if(step.ctype) h+=' · type: <code>'+esc(step.ctype)+'</code>';
                    if(step.ip) h+=' · IP: <code>'+esc(step.ip)+'</code>';
                    if(step.redirect) h+='<br>Location: <code class="text-warning">'+esc(step.redirect)+'</code>';
                    if(step.note) h+='<span class="text-danger"> · '+esc(step.note)+'</span>';
                    h+='</div></div>';
                });
                h+='<div class="mt-3 small"><strong>Final destination:</strong> <code class="text-success">'+esc(d.final)+'</code></div>';
                document.getElementById('lxBody').innerHTML=h;
                card.classList.remove('d-none');
            })
            .catch(function(){ err.textContent='Network error. Try again.'; err.classList.remove('d-none'); });
    });
    function esc(s){ var e=document.createElement('div'); e.appendChild(document.createTextNode(s==null?'':String(s))); return e.innerHTML; }
})();
</script>
<?php page_footer(); ?>