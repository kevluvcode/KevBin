<?php
require_once __DIR__ . '/../../functions.php';

start_session();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');

    if (!csrf_verify()) {
        echo json_encode(['error' => 'Invalid CSRF token.']);
        exit;
    }
    if (!rate_limit_check('emailrep', 8, 60)) {
        echo json_encode(['error' => 'Rate limit reached. Wait a moment.']);
        exit;
    }

    $email = strtolower(trim((string)($_POST['email'] ?? '')));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 254) {
        echo json_encode(['error' => 'Enter a valid email address.']);
        exit;
    }
    log_activity('tool_email_reputation', $email);

    $domain = strtolower(substr($email, strpos($email, '@') + 1));

    // ---- 1. Syntax + MX presence (real inbox possibility) ----
    $mx = [];
    $mxCount = 0;
    $mxHosts = @dns_get_record($domain, DNS_MX);
    if (is_array($mxHosts)) {
        foreach ($mxHosts as $m) {
            if (!empty($m['host']) && !empty($m['target'])) {
                $mx[] = $m['target'];
                $mxCount++;
            }
        }
        $mx = array_slice($mx, 0, 5);
    }

    // ---- 2. Disposable-address detection (local list + kickbox free API) ----
    $disposableList = [
        'mailinator.com','10minutemail.com','guerrillamail.com','tempmail.com','temp-mail.org',
        'sharklasers.com','yopmail.com','throwawaymail.com','maildrop.cc','trashmail.com','getnada.com',
        'mailnesia.com','33mail.com','spamgourmet.com','mytemp.email','fakeinbox.com','mailcatch.com',
        'dispostable.com','mintemail.com','emailondeck.com','moakt.com','dropmail.me','temp-mail.io',
        'guerrillamail.info','grr.la','spam4.me','tmail.ws','inboxkitten.com','fakemailgenerator.com',
        'tempinbox.com','owlymail.com','tmailor.com','tempyouremail.com','crazymailing.com','luxusmail.org',
        '1secmail.com','inboxbear.com','tuamaeaquelaursa.com','zbymail.com','thealthclub.com','luxusmail.org',
    ];
    $isDisposable = in_array($domain, $disposableList, true);
    $kickboxResult = null;
    if (!$isDisposable) {
        $kb = @file_get_contents('https://open.kickbox.com/v1/disposable/' . rawurlencode($email), false,
            stream_context_create(['http' => ['timeout' => 8, 'user_agent' => 'SolarOS-EmailRep/1.0']]));
        if (is_string($kb)) {
            $d = json_decode($kb, true);
            if (is_array($d) && isset($d['disposable'])) {
                $isDisposable = (bool)$d['disposable'];
                $kickboxResult = $isDisposable ? 'yes' : 'no';
            }
        }
    }

    // ---- 3. Info-stealer log hits via Hudson Rock free index ----
    $stealer = ['found' => false, 'total' => 0, 'services' => 0, 'samples' => []];
    $ch = curl_init('https://cavalier.hudsonrock.com/api/json/v2/osint-tools/search-by-email?' . http_build_query(['email' => $email]));
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 12,
        CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => 0,
        CURLOPT_USERAGENT => 'SolarOS-EmailRep/1.0',
    ]);
    $body = curl_exec($ch);
    curl_close($ch);
    $hr = json_decode((string)$body, true);
    if (is_array($hr) && !empty($hr['stealers'])) {
        $stealer['found'] = true;
        $stealer['total'] = count($hr['stealers']);
        $stealer['services'] = (int)($hr['total_user_services'] ?? 0) + (int)($hr['total_corporate_services'] ?? 0);
        foreach (array_slice($hr['stealers'], 0, 4) as $s) {
            $stealer['samples'][] = [
                'date' => substr((string)($s['date_compromised'] ?? ''), 0, 10),
                'os' => $s['operating_system'] ?? '',
                'computer' => $s['computer_name'] ?? '',
            ];
        }
    }

    // ---- 4. Verdict ----
    $flags = [];
    if ($stealer['found']) $flags[] = 'info-stealer logs';
    if ($isDisposable) $flags[] = 'disposable';
    if ($mxCount === 0 && !$isDisposable) $flags[] = 'no MX / unreachable';
    if ($domain === 'example.com' || in_array($domain, ['gmail.com', 'yahoo.com', 'outlook.com', 'hotmail.com', 'protonmail.com', 'icloud.com', 'aol.com'], true)) {
        if ($stealer['found']) {
            $verdict = 'Compromised';
            $cls = 'neg';
        } else {
            $verdict = 'Clean (nothing found)';
            $cls = 'pos';
        }
    } elseif ($stealer['found']) {
        $verdict = 'Compromised';
        $cls = 'neg';
    } elseif ($isDisposable) {
        $verdict = 'Disposable';
        $cls = 'warn';
    } elseif ($mxCount === 0) {
        $verdict = 'Likely invalid';
        $cls = 'warn';
    } else {
        $verdict = 'No negative signals';
        $cls = 'pos';
    }

    echo json_encode([
        'email' => $email,
        'verdict' => $verdict,
        'verdict_cls' => $cls,
        'flags' => $flags,
        'disposable' => $isDisposable,
        'disposable_source' => $kickboxResult ? ('kickbox:' . $kickboxResult) : 'local list',
        'mx_count' => $mxCount,
        'mx' => $mx,
        'stealer' => $stealer,
    ]);
    exit;
}

page_header('Email Reputation');
?>
<style>
.er-v { display:inline-block; padding:4px 12px; border-radius:8px; font-size:.9rem; font-weight:700; }
.pos { background:rgba(38,208,124,.12); border:1px solid rgba(38,208,124,.35); color:#26d07c; }
.neg { background:rgba(231,76,60,.12); border:1px solid rgba(231,76,60,.4); color:#e74c3c; }
.warn { background:rgba(255,193,7,.1); border:1px solid rgba(255,193,7,.35); color:#ffc107; }
.er-line { display:flex; justify-content:space-between; padding:.38rem 0; border-bottom:1px solid rgba(255,255,255,.04); gap:1rem; }
.er-line:last-child { border-bottom:none; }
.er-tag { display:inline-block; padding:2px 9px; border-radius:6px; font-size:.75rem; margin:2px; background:rgba(231,76,60,.1); border:1px solid rgba(231,76,60,.35); color:#e74c3c; text-transform:capitalize; }
.er-smp { border:1px solid rgba(255,255,255,.06); border-radius:8px; padding:.45rem .6rem; margin-bottom:.4rem; font-size:.8rem; }
</style>
<div class="container" style="max-width: 800px;">
    <h1 class="h4 mb-2 reveal in-view">&#128272; Email Reputation Check</h1>
    <p class="text-secondary mb-3 reveal in-view">Free inbox sanity test: disposable-domain detection, MX deliverability, and a lookup in Hudson Rock&rsquo;s infostealer index (RedLine, Raccoon, Lumma, AZORult&hellip;) to see if the address appeared in stolen-credential logs.</p>

    <div class="card reveal in-view"><div class="card-body">
        <form id="erForm" class="mb-0">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <div class="row g-2">
                <div class="col-md-8">
                    <input class="form-control" name="email" type="email" placeholder="name@example.com" required>
                </div>
                <div class="col-md-4">
                    <button class="btn btn-primary w-100" type="submit">Check</button>
                </div>
            </div>
        </form>
    </div></div>

    <div id="erErr" class="alert alert-danger mt-3 d-none"></div>

    <div class="card mt-3 d-none" id="erCard"><div class="card-body">
        <div class="mb-3">
            <span style="font-family:monospace"><?= e($email ?? '') ?><?php /* placeholder */ ?></span> <span class="er-v" id="erVerdict"></span>
        </div>
        <div id="erFlags"></div>
        <h2 class="h6 mt-2 mb-2">Details</h2>
        <div id="erRows"></div>
        <h2 class="h6 mt-3 mb-2">Infostealer log hits</h2>
        <div id="erStealer"></div>
    </div></div>
</div>
<script>
(function(){
    var form=document.getElementById('erForm');
    var err=document.getElementById('erErr');
    var card=document.getElementById('erCard');
    form.addEventListener('submit',function(e){
        e.preventDefault();
        err.classList.add('d-none');
        card.classList.add('d-none');
        fetch('index.php',{method:'POST',body:new FormData(form)})
            .then(function(r){return r.json();})
            .then(function(d){
                if(d.error){ err.textContent=d.error; err.classList.remove('d-none'); return; }
                var v=document.getElementById('erVerdict');
                v.textContent=d.verdict;
                v.className='er-v '+d.verdict_cls;
                document.getElementById('erFlags').innerHTML=d.flags.length
                    ? d.flags.map(function(f){return '<span class="er-tag">'+esc(f)+'</span>';}).join('')
                    : '<span class="text-secondary" style="font-size:.85rem">No red flags returned for this address.</span>';
                var rows=[
                    ['Address', d.email],
                    ['Disposable / temp address', d.disposable?'Yes ('+d.disposable_source+')':'No'],
                    ['MX records (deliverability)', d.mx_count+' found — '+esc((d.mx||[]).join(', ')||'no mail server')],
                    ['Stealer-log hits', d.stealer.found? d.stealer.total+' infected device(s), ~'+d.stealer.services+' services exposed':'None found']
                ];
                document.getElementById('erRows').innerHTML=rows.map(function(r){
                    return '<div class="er-line"><span class="text-secondary" style="font-size:.82rem">'+r[0]+'</span><span style="font-size:.88rem;font-weight:500;text-align:right;word-break:break-all">'+esc(r[1])+'</span></div>';
                }).join('');
                var sh=document.getElementById('erStealer');
                if(d.stealer.found){
                    sh.innerHTML=d.stealer.samples.map(function(s){
                        return '<div class="er-smp"><strong>'+esc(s.date)+'</strong> &middot; '+esc(s.os)+' &middot; computer &ldquo;'+esc(s.computer)+'&rdquo;</div>';
                    }).join('');
                } else {
                    sh.innerHTML='<div class="text-secondary small">No record of this address in the public infostealer index.</div>';
                }
                card.classList.remove('d-none');
            })
            .catch(function(){ err.textContent='Network error. Try again.'; err.classList.remove('d-none'); });
    });
    function esc(s){ var e=document.createElement('div'); e.appendChild(document.createTextNode(s==null?'':String(s))); return e.innerHTML; }
})();
</script>
<?php page_footer(); ?>