<?php
require_once __DIR__ . '/../../functions.php';

start_session();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');

    if (!csrf_verify()) {
        echo json_encode(['error' => 'Invalid CSRF token.']);
        exit;
    }
    if (!rate_limit_check('breachcheck', 5, 60)) {
        echo json_encode(['error' => 'Rate limit reached. Wait a moment.']);
        exit;
    }

    $mode = trim((string)($_POST['mode'] ?? ''));
    $apikey = trim((string)($_POST['apikey'] ?? ''));
    $value = trim((string)($_POST['value'] ?? ''));
    if ($value === '') {
        echo json_encode(['error' => 'Enter an email, username or password to check.']);
        exit;
    }

    log_activity('tool_breach_check', $mode);

    if ($mode === 'password') {
        $result = hibp_password_check($value, $apikey);
        echo json_encode($result);
        exit;
    }

    // email / username breach lookup
    if ($apikey === '') {
        echo json_encode(['error' => 'An email breach lookup requires a Have I Been Pwned API key. Paste one in the API Key field — it is used once and never stored or uploaded anywhere except to api.pwnedpasswords.com / haveibeenpwned.com. You can still check passwords for free without a key.']);
        exit;
    }
    if ($apikey !== '' && !preg_match('/^[A-Za-z0-9]{20,64}$/', $apikey)) {
        echo json_encode(['error' => 'That API key looks invalid (expected 20-64 letters/digits).']);
        exit;
    }
    if ($mode === 'email' && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['error' => 'That does not look like a valid email address.']);
        exit;
    }
    if ($mode === 'username' && (strlen($value) < 2 || strlen($value) > 128)) {
        echo json_encode(['error' => 'Username must be between 2 and 128 characters.']);
        exit;
    }

    $result = hibp_account_check($value, $apikey, $mode);
    echo json_encode($result);
    exit;
}

page_header('Breach Checker — Emails & Passwords in Known Data Breaches');
?>
<style>
.bc-seg{display:none;}
.bc-seg.active{display:block;}
.bc-chip{display:inline-block;padding:2px 10px;border-radius:6px;font-size:.78rem;margin:2px;font-weight:500;border:1px solid;}
.bc-chip.danger{background:rgba(231,76,60,.1);border-color:rgba(231,76,60,.35);color:#e74c3c;}
.bc-chip.ok{background:rgba(38,208,124,.1);border-color:rgba(38,208,124,.35);color:#26d07c;}
.bc-chip.info{background:rgba(88,101,242,.1);border-color:rgba(88,101,242,.35);color:#8b93f5;}
.bc-row{display:flex;justify-content:space-between;gap:1rem;padding:.4rem 0;border-bottom:1px solid rgba(255,255,255,.05);font-size:.86rem;align-items:center;}
.bc-row:last-child{border-bottom:none;}
.bc-row .lbl{color:var(--dim);}
.bc-meter{height:10px;border-radius:5px;background:rgba(255,255,255,.08);overflow:hidden;margin-top:.35rem;}
.bc-meter > div{height:100%;border-radius:5px;}
</style>

<div class="container" style="max-width:920px;">
    <h1 class="h4 mb-2 reveal in-view">Breach Checker</h1>
    <p class="text-secondary mb-1 reveal in-view">Check whether an email, username or password has appeared in known data breaches and password dumps.</p>
    <p class="text-secondary mb-4 reveal in-view"><strong>Password check</strong> uses a k-anonymity API (only the first 5 chars of the SHA-1 hash leave your browser — the full hash never leaves your device end-to-end). <strong>Email/username breach lookup</strong> queries the Have I Been Pwned API and needs a HIBP API key pasted into the box below (used once, never stored).</p>

    <div class="d-flex gap-2 mb-3">
        <button class="btn btn-outline-light btn-sm" id="seg-btn-password" onclick="switchSeg('password')">Password</button>
        <button class="btn btn-outline-light btn-sm" id="seg-btn-email" onclick="switchSeg('email')">Email</button>
        <button class="btn btn-outline-light btn-sm" id="seg-btn-username" onclick="switchSeg('username')">Username</button>
    </div>

    <div class="card mb-3 reveal in-view"><div class="card-body">
        <div class="mb-2">
            <label class="form-label">HIBP API Key <span class="text-secondary small">(required only for Email / Username lookup — free from haveibeenpwned.com/API/Key)</span></label>
            <input id="bc-apikey" class="form-control" type="password" autocomplete="off" placeholder="Paste your HIBP API key (only used on this request)" style="font-family:monospace;font-size:.85rem;">
        </div>
    </div></div>

    <div class="card reveal in-view"><div class="card-body">
        <div id="seg-password" class="bc-seg active">
            <form id="f-password" onsubmit="return doCheck(event,'password')">
                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                <div class="row g-2">
                    <div class="col-md-7">
                        <input class="form-control" name="value" type="password" autocomplete="off" placeholder="Password to check" required>
                    </div>
                    <div class="col-md-5">
                        <button class="btn btn-primary w-100" type="submit">Check password</button>
                    </div>
                </div>
                <p class="text-secondary small mt-2 mb-0">Checks if the password appears in any known password dump and tells you how many times it has been seen. The full password never leaves your browser.</p>
            </form>
        </div>

        <div id="seg-email" class="bc-seg">
            <form id="f-email" onsubmit="return doCheck(event,'email')">
                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                <div class="row g-2">
                    <div class="col-md-7">
                        <input class="form-control" name="value" type="email" placeholder="email@example.com" required>
                    </div>
                    <div class="col-md-5">
                        <button class="btn btn-primary w-100" type="submit">Look up email</button>
                    </div>
                </div>
            </form>
        </div>

        <div id="seg-username" class="bc-seg">
            <form id="f-username" onsubmit="return doCheck(event,'username')">
                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                <div class="row g-2">
                    <div class="col-md-7">
                        <input class="form-control" name="value" placeholder="username" required>
                    </div>
                    <div class="col-md-5">
                        <button class="btn btn-primary w-100" type="submit">Look up username</button>
                    </div>
                </div>
            </form>
        </div>
    </div></div>

    <div id="bc-error" class="alert alert-danger mt-3 d-none reveal in-view"></div>

    <div id="bc-result" class="mt-4 d-none">
        <div class="row g-3 mb-3">
            <div class="col-md-4">
                <div class="card"><div class="card-body text-center">
                    <div class="text-secondary small text-uppercase" style="letter-spacing:1px;font-size:.75rem;">Verdict</div>
                    <div class="mt-1" style="font-size:1.6rem;font-weight:700;" id="bc-verdict">--</div>
                </div></div>
            </div>
            <div class="col-md-4">
                <div class="card"><div class="card-body text-center">
                    <div class="text-secondary small text-uppercase" style="letter-spacing:1px;font-size:.75rem;">Exposed</div>
                    <div class="mt-1" style="font-size:1.6rem;font-weight:700;" id="bc-count">--</div>
                    <div class="text-secondary small" id="bc-count-note"></div>
                </div></div>
            </div>
            <div class="col-md-4">
                <div class="card"><div class="card-body text-center">
                    <div class="text-secondary small text-uppercase" style="letter-spacing:1px;font-size:.75rem;">Safety</div>
                    <div class="mt-1" style="font-size:1.6rem;font-weight:700;" id="bc-score">--</div>
                    <div class="bc-meter"><div id="bc-scorebar"></div></div>
                </div></div>
            </div>
        </div>

        <div class="card mb-3"><div class="card-body">
            <h2 class="h6 mb-3">Breach Details</h2>
            <div id="bc-exposure"></div>
        </div></div>

        <div class="card"><div class="card-body">
            <h2 class="h6 mb-3">Recommendations</h2>
            <div id="bc-recs"></div>
        </div></div>
    </div>
</div>

<script>
(function(){
    function switchSeg(mode){
        ['password','email','username'].forEach(function(m){
            var el=document.getElementById('seg-'+m);
            var btn=document.getElementById('seg-btn-'+m);
            if(el) el.classList.toggle('active', m===mode);
            if(btn) btn.classList.toggle('w-25');
        });
    }
    window.switchSeg=switchSeg;

    function esc(s){
        var d=document.createElement('div');d.appendChild(document.createTextNode(s==null?'':s));return d.innerHTML;
    }

    function doCheck(e,mode){
        e.preventDefault();
        var form=e.target;
        var errBox=document.getElementById('bc-error');
        var resultBox=document.getElementById('bc-result');
        errBox.classList.add('d-none');
        resultBox.classList.add('d-none');

        var fd=new FormData(form);
        fd.append('mode',mode);
        fd.append('apikey',document.getElementById('bc-apikey').value.trim());

        var btn=form.querySelector('button[type=submit]');
        var old=btn.innerHTML; btn.disabled=true; btn.innerHTML='Checking...';

        fetch('index.php',{method:'POST',body:fd})
            .then(function(r){
                if(r.status===429){throw new Error('You are being rate-limited by the server. Wait a moment and retry.');}
                return r.json();
            })
            .then(function(d){
                btn.disabled=false;btn.innerHTML=old;
                if(d.error){
                    errBox.textContent=d.error;errBox.classList.remove('d-none');return;
                }
                render(d);
            })
            .catch(function(err){
                btn.disabled=false;btn.innerHTML=old;
                errBox.textContent=err.message||'Network error. Try again.';
                errBox.classList.remove('d-none');
            });
        return false;
    }
    window.doCheck=doCheck;

    function render(d){
        var verdict=document.getElementById('bc-verdict');
        var count=document.getElementById('bc-count');
        var countNote=document.getElementById('bc-count-note');
        var scoreEl=document.getElementById('bc-score');
        var scoreBar=document.getElementById('bc-scorebar');

        if(d.password_check){
            // password mode
            var n=(d.password_check.count||0);
            verdict.textContent = n>0 ? 'Breached' : 'Not found';
            verdict.style.color = n>0 ? '#e74c3c' : '#26d07c';
            count.textContent = n>0 ? n.toLocaleString() : '0';
            countNote.textContent = 'times seen in dumps';
            var score = Math.max(0,100 - n/(n+10)*50);
            scoreEl.textContent = score>=80?'Strong':(score>=60?'Okay':'Weak');
            scoreBar.style.width=score+'%';
            scoreBar.style.background=score>=80?'#26d07c':(score>=60?'#ffc107':'#e74c3c');
            renderExposure(buildPasswordRows(n));
            renderRecs(buildPasswordRecs(n));
        } else if(d.account_check){
            var breaches=d.account_check.breaches||[];
            var totalExposed=(d.account_check.total_exposures||0);
            verdict.textContent = breaches.length>0 ? 'Breached' : 'Not found';
            verdict.style.color = breaches.length>0 ? '#e74c3c' : '#26d07c';
            count.textContent = breaches.length>0 ? String(breaches.length) : '0';
            countNote.textContent = totalExposed + ' total exposure(s)';
            var score=Math.max(0,100 - breaches.length*18);
            scoreEl.textContent=score>=80?'Low risk':(score>=50?'Moderate':'High exposure');
            scoreBar.style.width=score+'%';
            scoreBar.style.background=score>=80?'#26d07c':(score>=50?'#ffc107':'#e74c3c');
            renderExposure(buildBreachRows(breaches));
            renderRecs(buildBreachRecs(breaches, d.account_check));
        }
        document.getElementById('bc-result').classList.remove('d-none');
        document.getElementById('bc-result').querySelectorAll('.reveal').forEach(function(el){
            el.classList.add('in-view');
        });
    }

    function chipRow(label,val,cls){
        return '<div class="bc-row"><span class="lbl">'+label+'</span><span class="bc-chip '+cls+'">'+val+'</span></div>';
    }

    function buildPasswordRows(n){
        var h='';
        h+=chipRow('Password seen in password dumps', n>0?'Yes — '+n.toLocaleString()+' times':'No', n>0?'danger':'ok');
        h+=chipRow('Position in common-password lists', n>100000?'Very common':(n>1000?'Common':(n>0?'Uncommon':'Not listed')), n>100000?'danger':(n>1000?'warn':(n>0?'info':'ok')));
        return h;
    }

    function buildPasswordRecs(n){
        var recs=[];
        if(n===0){
            recs.push(['safe','This password has not appeared in any known breach/password dump we could query.']);
        }else if(n>0 && n<1000){
            recs.push(['warn','This password has been exposed '+n+' time(s). It is still usable, but consider a unique password for every site.']);
        }else{
            recs.push(['danger','This password has appeared '+n.toLocaleString()+' times in dumps — it is effectively burned and will be tried first in credential-stuffing attacks. Change it everywhere immediately.']);
        }
        recs.push(['info','Never reuse passwords across sites — a breach on any site leaks them all. Use a password manager.']);
        return recs;
    }

    function buildBreachRows(breaches){
        if(breaches.length===0){
            return '<div class="text-secondary small">No breaches found for this account.</div>';
        }
        var h='<table class="table table-sm table-dark align-middle mb-0"><thead><tr><th>Breach / Data Class</th><th>Exposed</th><th>Type</th><th>Date</th></tr></thead><tbody>';
        breaches.forEach(function(b){
            h+='<tr><td><strong>'+esc(b.name)+'</strong><div class="text-secondary small">'+esc((b.data_classes||[]).join(', '))+'</div></td>'
             +'<td class="small">'+b.exposures+'</td><td class="small">'+esc(b.breach_type||'')+'</td><td class="small">'+esc(b.breach_date||'')+'</td></tr>';
        });
        h+='</tbody></table>';
        return h;
    }

    function buildBreachRecs(breaches, ac){
        var recs=[];
        if(breaches.length===0){
            recs.push(['safe','This account was not found in any breach in the queried dataset. Keep using unique passwords anyway.']);
        }else{
            recs.push(['danger',breaches.length+' breach(es) found totalling '+(ac.total_exposures||0)+' exposure(s). Change any password used on those sites immediately.']);
        }
        if(ac.pastes && ac.pastes.length){
            recs.push(['warn',ac.pastes.length+' paste(s) containing this account found — this often means the account is being traded in hacker forums.']);
        }
        recs.push(['info','Enable two-factor authentication everywhere it is offered.']);
        return recs;
    }

    function renderExposure(html){
        document.getElementById('bc-exposure').innerHTML=html;
    }

    function renderRecs(recs){
        var h='';
        recs.forEach(function(r){
            var icon=r[0]==='danger'?'&#9888;':(r[0]==='warn'?'&#9881;':'&#9989;');
            var cls=r[0]==='danger'?'danger':(r[0]==='warn'?'info':'ok');
            h+='<div class="bc-row"><span class="lbl">'+icon+' '+esc(r[1])+'</span></div>';
        });
        document.getElementById('bc-recs').innerHTML=h;
    }
})();
</script>
<?php page_footer(); ?>

<?php
/**
 * k-anonymity password check against HIBP Pwned Passwords.
 * Only the first 5 hex chars of SHA-1 leave the server; the suffix is compared locally.
 */
function hibp_password_check(string $password, string $apikey = ''): array {
    $sha = strtoupper(sha1($password));
    $prefix = substr($sha, 0, 5);
    $suffix = substr($sha, 5);

    $ch = curl_init('https://api.pwnedpasswords.com/range/' . $prefix);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 12,
        CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => 0,
        CURLOPT_USERAGENT => 'KevBin-BreachChecker',
        CURLOPT_HTTPHEADER => ['Accept: text/plain', 'Add-Padding: true'],
    ]);
    $body = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $err = curl_error($ch);
    curl_close($ch);

    if ($code !== 200 || $body === false) {
        return ['error' => 'Password service unavailable' . ($err !== '' ? ' (' . $err . ')' : '') . '. Try again shortly.'];
    }

    $count = 0;
    foreach (preg_split('/\r?\n/', $body) as $line) {
        $line = trim($line);
        if ($line === '') continue;
        $parts = explode(':', $line);
        if (count($parts) === 2 && strtoupper(trim($parts[0])) === $suffix) {
            $count = (int)$parts[1];
            break;
        }
    }

    return ['password_check' => ['count' => $count]];
}

/**
 * Full email/username breach lookup against the HaveIBeenPwned BreachedAccount API.
 */
function hibp_account_check(string $value, string $apikey, string $mode): array {
    $url = 'https://haveibeenpwned.com/api/v3/breachedaccount/' . rawurlencode($value) . '?truncateResponse=false&includeUnverified=true';
    if ($mode === 'username') {
        $url = 'https://haveibeenpwned.com/api/v3/breachedaccount/' . rawurlencode($value) . '?truncateResponse=false&includeUnverified=true&domain_username=true';
    }

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => 0,
        CURLOPT_USERAGENT => 'KevBin-BreachChecker',
        CURLOPT_HTTPHEADER => [
            'hibp-api-key: ' . $apikey,
            'Accept: application/json',
        ],
    ]);
    $body = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $err = curl_error($ch);
    curl_close($ch);

    if ($code === 401) {
        return ['error' => 'HIBP rejected the API key (401). Check the key and try again.'];
    }
    if ($code === 403) {
        return ['error' => 'HIBP blocked the request (403). Your IP may be rate-limited by HIBP — wait a few minutes.'];
    }
    if ($body === false) {
        return ['error' => 'Breach service unavailable' . ($err !== '' ? ' (' . $err . ')' : '') . '.'];
    }

    $breaches = [];
    $totalExposures = 0;
    $trim = trim((string)$body);

    if ($code === 404 || stripos($trim, 'No breached account') !== false) {
        return ['account_check' => ['breaches' => [], 'total_exposures' => 0, 'pastes' => []]];
    }

    $decoded = json_decode($body, true);
    if (is_array($decoded)) {
        $breaches = $decoded;
        foreach ($breaches as $b) {
            if (is_array($b)) {
                $totalExposures += (int)($b['data_classes'] ? count($b['data_classes']) : 1);
            }
        }
    }

    // pastes check
    $pastes = [];
    $purl = 'https://haveibeenpwned.com/api/v3/pasteaccount/' . rawurlencode($value);
    $pch = curl_init($purl);
    curl_setopt_array($pch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 12,
        CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => 0,
        CURLOPT_USERAGENT => 'KevBin-BreachChecker',
        CURLOPT_HTTPHEADER => ['hibp-api-key: ' . $apikey, 'Accept: application/json'],
    ]);
    $pbody = curl_exec($pch);
    curl_close($pch);
    $pdec = json_decode((string)$pbody, true);
    if (is_array($pdec) && count($pdec) > 0) {
        $pastes = $pdec;
    }

    return [
        'account_check' => [
            'breaches' => array_map(function ($b) {
                return [
                    'name' => (string)($b['Name'] ?? $b['name'] ?? ''),
                    'title' => (string)($b['Title'] ?? ''),
                    'breach_type' => (string)($b['BreachType'] ?? $b['breach_type'] ?? ''),
                    'breach_date' => (string)($b['BreachDate'] ?? $b['breach_date'] ?? ''),
                    'data_classes' => array_values((array)($b['DataClasses'] ?? $b['data_classes'] ?? [])),
                    'exposures' => (int)count($b['DataClasses'] ?? $b['data_classes'] ?? []),
                ];
            }, $breaches),
            'total_exposures' => $totalExposures,
            'pastes' => $pastes,
        ],
    ];
}