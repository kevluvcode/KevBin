<?php
require_once __DIR__ . '/../../functions.php';

start_session();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');

    if (!csrf_verify()) {
        echo json_encode(['error' => 'Invalid CSRF token.']);
        exit;
    }
    if (!rate_limit_check('geoip', 10, 60)) {
        echo json_encode(['error' => 'Rate limit reached. Wait a moment.']);
        exit;
    }

    $ip = trim((string)($_POST['ip'] ?? ''));
    if ($ip === '') {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        $auto = true;
    } else {
        $auto = false;
    }
    if (filter_var($ip, FILTER_VALIDATE_IP) === false) {
        echo json_encode(['error' => 'Invalid IP address.']);
        exit;
    }
    log_activity('tool_geoip', $ip);

    $ch = curl_init('https://ipwho.is/' . rawurlencode($ip));
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 12,
        CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => 0,
        CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
    ]);
    $body = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);

    $data = json_decode((string)$body, true);
    if (!is_array($data) || empty($data['success'])) {
        $msg = isset($data['message']) && is_string($data['message'])
            ? ': ' . $data['message']
            : '';
        echo json_encode(['error' => 'GeoIP lookup failed' . $msg . '.']);
        exit;
    }

    $flags = [];
    foreach (['is_proxy', 'is_tor', 'is_vpn', 'is_anonymizer', 'is_abuser', 'is_cloud', 'is_datacenter'] as $k) {
        if (!empty($data['security'][$k])) {
            $flags[] = str_replace('is_', '', $k);
        }
    }

    echo json_encode([
        'auto' => $auto,
        'ip' => $data['ip'] ?? $ip,
        'success' => true,
        'type' => $data['type'] ?? null,
        'continent' => ($data['continent'] ?? null),
        'country' => ($data['country'] ?? null),
        'country_code' => ($data['country_code'] ?? null),
        'flag' => ($data['flag'] ?? null),
        'region' => ($data['region'] ?? null),
        'city' => ($data['city'] ?? null),
        'postal' => ($data['postal'] ?? null),
        'lat' => ($data['latitude'] ?? null),
        'lon' => ($data['longitude'] ?? null),
        'timezone' => ($data['timezone'] ?? null),
        'utc_offset' => ($data['utc_offset'] ?? null),
        'isp' => ($data['connection']['isp'] ?? null),
        'org' => ($data['connection']['org'] ?? null),
        'asn' => ($data['connection']['asn'] ?? null),
        'domain' => ($data['connection']['domain'] ?? null),
        'threats' => $flags,
        'error' => null,
    ]);
    exit;
}

page_header('GeoIP Lookup');
?>
<style>
.gip-line { display:flex; justify-content:space-between; padding:.4rem 0; border-bottom:1px solid rgba(255,255,255,.04); gap:1rem; }
.gip-line:last-child { border-bottom:none; }
.gip-k { color:var(--dim); font-size:.82rem; }
.gip-v { font-size:.88rem; font-weight:500; text-align:right; word-break:break-all; }
.gip-tag { display:inline-block; padding:2px 9px; border-radius:6px; font-size:.76rem; margin:2px; background:rgba(231,76,60,.1); border:1px solid rgba(231,76,60,.35); color:#e74c3c; text-transform:capitalize; }
.gip-clean { color:#26d07c; font-size:.9rem; }
</style>
<div class="container" style="max-width: 800px;">
    <h1 class="h4 mb-2 reveal in-view">&#128205; GeoIP Lookup</h1>
    <p class="text-secondary mb-3 reveal in-view">Resolves location, ISP, autonomous-system and threat flags for any IPv4/IPv6 address using ipwho.is. Leave blank to check your own public IP.</p>

    <div class="card reveal in-view"><div class="card-body">
        <form id="gipForm" class="mb-0">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <div class="row g-2">
                <div class="col-md-8">
                    <input class="form-control" name="ip" placeholder="8.8.8.8 (blank = your IP)">
                </div>
                <div class="col-md-4">
                    <button class="btn btn-primary w-100" type="submit">Lookup</button>
                </div>
            </div>
        </form>
    </div></div>

    <div id="gipErr" class="alert alert-danger mt-3 d-none"></div>

    <div class="card mt-3 d-none" id="gipCard"><div class="card-body">
        <div class="mb-3">
            <span class="gip-tag mr-1" id="gipTypeTag"></span>
            <span class="gip-clean" id="gipClean"></span>
            <span id="gipThreats"></span>
        </div>
        <div id="gipRows"></div>
    </div></div>
</div>
<script>
(function(){
    var form=document.getElementById('gipForm');
    var err=document.getElementById('gipErr');
    var card=document.getElementById('gipCard');
    form.addEventListener('submit',function(e){
        e.preventDefault();
        err.classList.add('d-none');
        card.classList.add('d-none');
        fetch('index.php',{method:'POST',body:new FormData(form)})
            .then(function(r){return r.json();})
            .then(function(d){
                if(d.error){ err.textContent=d.error; err.classList.remove('d-none'); return; }
                var t=document.getElementById('gipTypeTag');
                t.textContent=d.type==='ipv6'?'v6':'v4';
                t.style.background='rgba(88,101,242,.12)';
                t.style.border='1px solid rgba(88,101,242,.35)';
                t.style.color='#6872f2';
                var clean=document.getElementById('gipClean');
                clean.textContent=d.threats.length?'':'No threat flags on this IP.';
                var th=document.getElementById('gipThreats');
                th.innerHTML=d.threats.map(function(s){return '<span class="gip-tag">'+s+'</span> ';}).join('');
                var rows=[
                    ['IP Address', d.ip+(d.auto?' (your public IP)':'')],
                    ['Continent', d.continent||'—'],
                    ['Country', (d.flag?d.flag.emoji+' ':'')+(d.country||'—')+' ('+(d.country_code||'—')+')'],
                    ['Region', d.region||'—'],
                    ['City', d.city||'—'],
                    ['Postal', d.postal||'—'],
                    ['Coordinates', (d.lat!==null&&d.lon!==null)?d.lat+', '+d.lon:'—'],
                    ['Timezone', d.timezone+' (UTC'+(d.utc_offset>=0?'+':'')+d.utc_offset+')'],
                    ['ISP', d.isp||'—'],
                    ['Organization', d.org||'—'],
                    ['ASN', d.asn||'—'],
                    ['Domain', d.domain||'—']
                ];
                document.getElementById('gipRows').innerHTML=rows.map(function(r){
                    return '<div class="gip-line"><span class="gip-k">'+r[0]+'</span><span class="gip-v">'+esc(r[1])+'</span></div>';
                }).join('');
                card.classList.remove('d-none');
            })
            .catch(function(){ err.textContent='Network error. Try again.'; err.classList.remove('d-none'); });
    });
    function esc(s){
        var d=document.createElement('div');
        d.appendChild(document.createTextNode(s==null||s==='—'?'—':String(s)));
        return d.innerHTML;
    }
})();
</script>
<?php page_footer(); ?>