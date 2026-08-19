<?php
require_once __DIR__ . '/../../functions.php';

start_session();

function tor_reverse_dnsbl($ip)
{
    $parts = explode('.', $ip);

    return count($parts) === 4 ? implode('.', array_reverse($parts)) . '.dnstor.im' : null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');

    if (!csrf_verify()) {
        echo json_encode(['error' => 'Invalid CSRF token.']);
        exit;
    }
    if (!rate_limit_check('torcheck', 6, 60)) {
        echo json_encode(['error' => 'Rate limit reached. Wait a moment.']);
        exit;
    }

    $ip = trim((string)($_POST['ip'] ?? ''));
    if ($ip === '') {
        echo json_encode(['error' => 'Enter an IP address.']);
        exit;
    }
    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) === false) {
        echo json_encode(['error' => 'Enter a valid IPv4 address (Tor checks run on the IPv4 exit list).']);
        exit;
    }
    log_activity('tool_tor_check', $ip);

    $listed = false;
    $key = null;
    $in_title = null;
    $block = null;

    // 1) Tor Project exit list (torbulkexitlist = plain IP list, fastest reliable option)
    $list = http_get('https://check.torproject.org/torbulkexitlist', 12);
    if ($list !== null) {
        $ips = preg_split('/\s+/', trim($list));
        foreach ($ips as $entry) {
            if (trim($entry) === $ip) {
                $listed = true;
                break;
            }
        }
    }

    // 2) list format with last-seen dates
    if (!$listed) {
        $exit = http_get('https://check.torproject.org/exit-addresses', 12);
        if ($exit !== null) {
            foreach (preg_split('/\r?\n/', $exit) as $line) {
                if (preg_match('/^ExitAddress\s+(' . preg_quote($ip, '/') . ')\s+(\S+)/', trim($line), $m)) {
                    $listed = true;
                    $key = $m[1];
                    $in_title = $m[2];
                    break;
                }
            }
        }
    }

    // 3) dnstor.im DNSBL cross-check
    $dnsbl = tor_reverse_dnsbl($ip);
    $dnsblListed = false;
    if ($dnsbl) {
        $rec = @dns_get_record($dnsbl, DNS_A);
        $dnsblListed = !empty($rec);
        $listed = $listed || $dnsblListed;
    }

    echo json_encode([
        'ip' => $ip,
        'tor' => $listed,
        'last_seen' => $in_title,
        'dnsbl' => $dnsblListed ? $dnsbl : null,
        'method' => $key ? 'Tor Project exit list (last seen ' . $in_title . ')' : ($dnsblListed ? 'dnstor.im DNSBL' : 'Tor Project exit list & dnstor.im DNSBL'),
    ]);
    exit;
}

page_header('Tor Exit Node Checker');
?>
<div class="container" style="max-width: 760px;">
    <h1 class="h4 mb-2 reveal in-view">&#11036; Tor Exit Node Checker</h1>
    <p class="text-secondary mb-3 reveal in-view">Is an IP address currently running as a Tor exit node? Checks the live Tor Project exit list plus the dnstor.im DNS blacklist.</p>

    <div class="card reveal in-view"><div class="card-body">
        <form id="tcForm" class="mb-0">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <div class="row g-2">
                <div class="col-md-8">
                    <input class="form-control" name="ip" placeholder="e.g. 185.220.101.34" required>
                </div>
                <div class="col-md-4">
                    <button class="btn btn-primary w-100" type="submit">Check</button>
                </div>
            </div>
        </form>
    </div></div>

    <div id="tcErr" class="alert alert-danger mt-3 d-none"></div>
    <div class="card mt-3 d-none" id="tcCard"><div class="card-body" id="tcBody"></div></div>
</div>
<script>
(function(){
    var form=document.getElementById('tcForm');
    var err=document.getElementById('tcErr');
    var card=document.getElementById('tcCard');
    form.addEventListener('submit',function(e){
        e.preventDefault();
        err.classList.add('d-none');
        card.classList.add('d-none');
        fetch('index.php',{method:'POST',body:new FormData(form)})
            .then(function(r){return r.json();})
            .then(function(d){
                if(d.error){ err.textContent=d.error; err.classList.remove('d-none'); return; }
                var h='<div class="d-flex align-items-center mb-2">';
                h+=(d.tor?'<span class="fs-3 me-2">&#9888;&#65039;</span><div><strong class="text-danger">TOR NODE — yes</strong><div class="small text-secondary">This IP can route traffic as a Tor exit.</div></div>':'<span class="fs-3 me-2">&#9989;</span><div><strong class="text-success">Not a Tor exit node</strong><div class="small text-secondary">Not found on the current exit lists.</div></div>');
                h+='</div>';
                h+='<table class="table table-sm tbl mt-2 mb-0"><tbody>';
                h+=row('IP',d.ip);
                h+=row('Method',d.method);
                if(d.last_seen) h+=row('Last seen',d.last_seen);
                if(d.dnsbl) h+=row('DNSBL',d.dnsbl+' (listed)');
                h+='</tbody></table>';
                document.getElementById('tcBody').innerHTML=h;
                card.classList.remove('d-none');
            })
            .catch(function(){ err.textContent='Network error. Try again.'; err.classList.remove('d-none'); });
    });
    function row(k,v){ return '<tr><td class="text-secondary" style="width:140px">'+k+'</td><td><code>'+esc(v)+'</code></td></tr>'; }
    function esc(s){ var e=document.createElement('div'); e.appendChild(document.createTextNode(s==null?'':String(s))); return e.innerHTML; }
})();
</script>
<?php page_footer(); ?>