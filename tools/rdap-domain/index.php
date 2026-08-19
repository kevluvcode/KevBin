<?php
require_once __DIR__ . '/../../functions.php';

start_session();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');

    if (!csrf_verify()) {
        echo json_encode(['error' => 'Invalid CSRF token.']);
        exit;
    }
    if (!rate_limit_check('rdap', 8, 60)) {
        echo json_encode(['error' => 'Rate limit reached. Wait a moment.']);
        exit;
    }

    $domain = strtolower(trim((string)($_POST['domain'] ?? '')));
    $domain = preg_replace('#^https?://#i', '', $domain);
    $domain = preg_replace('#[/\s].*$#', '', $domain);
    $domain = trim($domain, '.');
    if ($domain === '' || strlen($domain) > 253 || !preg_match('/^(?=.{1,253}$)([a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$/i', $domain)) {
        echo json_encode(['error' => 'Enter a valid domain name (e.g. example.com).']);
        exit;
    }
    log_activity('tool_rdap', $domain);

    $ch = curl_init('https://rdap.org/domain/' . rawurlencode($domain));
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 4,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => 0,
        CURLOPT_USERAGENT => 'SolarOS-RDAP/1.0',
        CURLOPT_HTTPHEADER => ['Accept: application/rdap+json, application/json'],
    ]);
    $body = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);

    $d = json_decode((string)$body, true);
    if (!is_array($d) || $code !== 200) {
        $msg = is_array($d) && !empty($d['errorTitle']) ? $d['errorTitle'] : ('RDAP lookup failed (HTTP ' . $code . ').');
        echo json_encode(['error' => $msg]);
        exit;
    }

    $events = [];
    foreach (($d['events'] ?? []) as $ev) {
        $events[$ev['eventAction']] = substr((string)($ev['eventDate'] ?? ''), 0, 10);
    }

    $entities = [];
    foreach (($d['entities'] ?? []) as $ent) {
        $role = $ent['roles'][0] ?? 'other';
        $name = $ent['vcardArray'][1][0] ?? null;
        $label = '';
        if (is_array($name)) {
            foreach ($name as $cell) {
                if (is_array($cell) && isset($cell[3]) && count($cell) >= 4) {
                    $label = (string)$cell[3];
                    break;
                }
            }
            if ($label !== '' && is_array($name) && isset($name[0])) {
                // vcard fn is first array element -> [0][3]
            }
        }
        if ($label === '' && is_array($name) && isset($name[3])) {
            $label = $name[3];
        }
        if ($label === '') {
            if (is_array($name) && isset($name[0]) && isset($name[0][3])) {
                $label = $name[0][3];
            }
        }
        $entities[] = ['role' => $role, 'name' => $label];
    }

    $status = array_map(function ($s) {
        return str_replace('_', ' ', $s);
    }, (array)($d['status'] ?? []));

    $ns = [];
    foreach (($d['nameservers'] ?? []) as $n) {
        $ns[] = $n['ldhName'] ?? ($n['unicodeName'] ?? '');
    }
    $ns = array_filter($ns);

    echo json_encode([
        'domain' => $d['ldhName'] ?? $domain,
        'handle' => $d['handle'] ?? '',
        'status' => $status,
        'events' => $events,
        'nameservers' => array_values(array_slice($ns, 0, 12)),
        'entities' => $entities,
        'dnssec' => !empty($d['secureDNS']['delegationSigned']) ? 'DNSSEC signed' : 'Not signed',
        'registry' => $d['port43'] ?? '',
    ]);
    exit;
}

page_header('RDAP Domain Lookup');
?>
<style>
.rd-line { display:flex; justify-content:space-between; padding:.38rem 0; border-bottom:1px solid rgba(255,255,255,.04); gap:1rem; }
.rd-line:last-child { border-bottom:none; }
.rd-k { color:var(--dim); font-size:.82rem; flex:0 0 auto; }
.rd-v { font-size:.88rem; font-weight:500; text-align:right; word-break:break-all; }
.rd-status { display:inline-block; padding:2px 8px; border-radius:6px; font-size:.74rem; margin:2px; background:rgba(88,101,242,.1); border:1px solid rgba(88,101,242,.3); color:#6872f2; text-transform:capitalize; }
</style>
<div class="container" style="max-width: 820px;">
    <h1 class="h4 mb-2 reveal in-view">&#128256; RDAP Domain Lookup</h1>
    <p class="text-secondary mb-3 reveal in-view">WHOIS successor: pulls registration dates, registrar, DNSSEC, nameservers and domain status codes straight from the registry RDAP servers (via rdap.org).</p>

    <div class="card reveal in-view"><div class="card-body">
        <form id="rdForm" class="mb-0">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <div class="row g-2">
                <div class="col-md-8">
                    <input class="form-control" name="domain" placeholder="example.com" required>
                </div>
                <div class="col-md-4">
                    <button class="btn btn-primary w-100" type="submit">Lookup</button>
                </div>
            </div>
        </form>
    </div></div>

    <div id="rdErr" class="alert alert-danger mt-3 d-none"></div>

    <div class="card mt-3 d-none" id="rdCard"><div class="card-body">
        <div class="mb-3"><div id="rdStatus"></div></div>
        <div id="rdRows"></div>
    </div></div>
</div>
<script>
(function(){
    var form=document.getElementById('rdForm');
    var err=document.getElementById('rdErr');
    var card=document.getElementById('rdCard');
    form.addEventListener('submit',function(e){
        e.preventDefault();
        err.classList.add('d-none');
        card.classList.add('d-none');
        fetch('index.php',{method:'POST',body:new FormData(form)})
            .then(function(r){return r.json();})
            .then(function(d){
                if(d.error){ err.textContent=d.error; err.classList.remove('d-none'); return; }
                var st=document.getElementById('rdStatus');
                st.innerHTML=d.status.length? d.status.map(function(s){return '<span class="rd-status">'+esc(s)+'</span>';}).join('') : '<span class="text-secondary small">No status codes returned.</span>';
                var ev={};
                (d.events||{}).forEach ? null : null;
                var rows=[
                    ['Domain', d.domain],
                    ['Registry / port43', d.registry||'—'],
                    ['DNSSEC', d.dnssec],
                    ['Registered', d.events.registration||'—'],
                    ['Last changed', d.events.last_changed||'—'],
                    ['Expiration', d.events.expiration||'—'],
                    ['Nameservers', (d.nameservers||[]).join(', ')||'—']
                ];
                (d.entities||[]).forEach(function(en){
                    rows.push([cap(en.role), en.name||'—']);
                });
                document.getElementById('rdRows').innerHTML=rows.map(function(r){
                    return '<div class="rd-line"><span class="rd-k">'+r[0]+'</span><span class="rd-v">'+esc(r[1])+'</span></div>';
                }).join('');
                card.classList.remove('d-none');
            })
            .catch(function(){ err.textContent='Network error. Try again.'; err.classList.remove('d-none'); });
    });
    function cap(s){ return s.charAt(0).toUpperCase()+s.slice(1).replace(/_/g,' '); }
    function esc(s){ var e=document.createElement('div'); e.appendChild(document.createTextNode(s==null||s==='—'?'—':String(s))); return e.innerHTML; }
})();
</script>
<?php page_footer(); ?>