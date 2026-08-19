<?php
require_once __DIR__ . '/../../functions.php';

start_session();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');

    if (!csrf_verify()) {
        echo json_encode(['error' => 'Invalid CSRF token.']);
        exit;
    }
    if (!rate_limit_check('stealercheck', 10, 60)) {
        echo json_encode(['error' => 'Rate limit reached. Wait a moment.']);
        exit;
    }

    $mode = trim((string)($_POST['mode'] ?? ''));

    log_activity('tool_stealer_check', $mode);

    if ($mode === 'password') {
        $password = trim((string)($_POST['value'] ?? ''));
        if ($password === '') {
            echo json_encode(['error' => 'Enter a password to check.']);
            exit;
        }
        echo json_encode(hibp_password_range($password));
        exit;
    }

    if ($mode === 'public') {
        $type = trim((string)($_POST['type'] ?? ''));
        $value = trim((string)($_POST['value'] ?? ''));
        if ($value === '') {
            echo json_encode(['error' => 'Enter a search value.']);
            exit;
        }
        $allowed = ['email', 'username', 'ip', 'domain'];
        if (!in_array($type, $allowed, true)) {
            echo json_encode(['error' => 'Unknown search type.']);
            exit;
        }
        echo json_encode(hr_stealer_search($type, $value));
        exit;
    }

    echo json_encode(['error' => 'Unknown mode.']);
    exit;
}

page_header('Stealer Log Checker — Search Local & Public Infostealer Logs For Your Data');
?>
<style>
.st-seg{display:none;}
.st-seg.active{display:block;}
.st-chip{display:inline-block;padding:2px 10px;border-radius:6px;font-size:.78rem;margin:2px;font-weight:500;border:1px solid;}
.st-chip.danger{background:rgba(231,76,60,.1);border-color:rgba(231,76,60,.35);color:#e74c3c;}
.st-chip.ok{background:rgba(38,208,124,.1);border-color:rgba(38,208,124,.35);color:#26d07c;}
.st-chip.info{background:rgba(88,101,242,.1);border-color:rgba(88,101,242,.35);color:#8b93f5;}
.st-chip.warn{background:rgba(255,193,7,.1);border-color:rgba(255,193,7,.35);color:#ffc107;}
.st-row{display:flex;justify-content:space-between;gap:1rem;padding:.4rem 0;border-bottom:1px solid rgba(255,255,255,.05);font-size:.86rem;align-items:center;}
.st-row:last-child{border-bottom:none;}
.st-row .lbl{color:var(--dim);}
.st-match{border:1px solid rgba(231,76,60,.25);background:rgba(231,76,60,.05);border-radius:8px;padding:.5rem .65rem;margin-bottom:.45rem;font-family:monospace;font-size:.78rem;word-break:break-all;white-space:pre-wrap;}
.st-match b{color:#ff6b6b;}
.st-result-box{display:none;}
.st-result-box.visible{display:block;}
.st-pub-item{border:1px solid var(--line);border-radius:10px;padding:.75rem .9rem;margin-bottom:.6rem;background:rgba(255,255,255,.015);}
.st-mini{color:var(--dim);font-size:.8rem;}
.st-meter{height:8px;border-radius:4px;background:rgba(255,255,255,.08);overflow:hidden;margin-top:.3rem;}
.st-meter>div{height:100%;border-radius:4px;}
.st-tbl{width:100%;border-collapse:collapse;font-size:.8rem;}
.st-tbl th,.st-tbl td{text-align:left;padding:.35rem .5rem;border-bottom:1px solid rgba(255,255,255,.06);vertical-align:top;}
.st-tbl th{color:var(--dim);font-weight:600;}
.st-tag{display:inline-block;padding:1px 8px;border-radius:4px;font-size:.72rem;margin-right:4px;margin-bottom:2px;border:1px solid var(--line);color:var(--dim);}
</style>

<div class="container" style="max-width:960px;">
    <h1 class="h4 mb-2 reveal in-view">Stealer Log Checker</h1>
    <p class="text-secondary mb-1 reveal in-view">Check if your credentials appeared in <strong>infostealer malware logs</strong> (RedLine, Raccoon, Vidar/Lumma, AZORult, StealC, etc.) — the logs behind most account-takeover and session-hijacking attacks.</p>
    <p class="text-secondary mb-4 reveal in-view">Three ways to check: <strong>(1)</strong> search a downloaded log locally in your browser — nothing is uploaded; <strong>(2)</strong> check an email / username / IP / domain against the <strong>public stealer-log dataset</strong> (Hudson Rock's free index, millions of infected-device records); <strong>(3)</strong> check a password against the HIBP Pwned Passwords list (k-anonymity — only a SHA-1 prefix leaves your browser).</p>

    <div class="d-flex gap-2 mb-3 flex-wrap">
        <button class="btn btn-outline-light btn-sm" id="seg-btn-log" onclick="switchSeg('log')">Search a log</button>
        <button class="btn btn-outline-light btn-sm" id="seg-btn-public" onclick="switchSeg('public')">Public stealer logs</button>
        <button class="btn btn-outline-light btn-sm" id="seg-btn-password" onclick="switchSeg('password')">Password check</button>
        <button class="btn btn-outline-light btn-sm" id="seg-btn-families" onclick="switchSeg('families')">Stealer families</button>
    </div>

    <div id="seg-log" class="st-seg">
        <div class="card reveal in-view"><div class="card-body">
            <h2 class="h6 mb-3">Search a downloaded log (100% local, nothing uploaded)</h2>
            <div class="mb-2">
                <label class="form-label">Search terms <span class="text-secondary small">— one per line (email / username / password / phone / domain / IP)</span></label>
                <textarea id="st-terms" class="form-control" rows="3" placeholder="you@example.com&#10;myusername&#10;mypassword123"></textarea>
            </div>
            <div class="row g-2 mb-2">
                <div class="col-md-4">
                    <label class="form-label d-flex justify-content-between"><span>Matching</span></label>
                    <select id="st-mode" class="form-select">
                        <option value="substring">Substring (anywhere)</option>
                        <option value="word">Whole word</option>
                        <option value="exactline">Exact line</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Max lines</label>
                    <select id="st-limit" class="form-select">
                        <option value="200">200 context lines</option>
                        <option value="500">500 context lines</option>
                        <option value="1000">1,000 context lines</option>
                    </select>
                </div>
                <div class="col-md-4 d-flex align-items-end">
                    <button class="btn btn-primary w-100" onclick="runLogSearch()">Search log</button>
                </div>
            </div>
            <div class="mb-2 d-flex align-items-center justify-content-between">
                <label class="form-label mb-0">Log contents</label>
                <span class="text-secondary small" id="st-fileinfo"></span>
            </div>
            <div class="mb-2">
                <label class="form-label" for="st-file">or choose a log file</label>
                <input type="file" id="st-file" class="form-control" accept=".txt,.log,.json,.csv">
            </div>
            <div class="mb-2">
                <label class="form-label">Paste log text</label>
                <textarea id="st-log" class="form-control" rows="10" placeholder="Paste the stealer log / combo list / dump here..."></textarea>
            </div>
            <div class="d-flex gap-2 flex-wrap">
                <button class="btn btn-outline-light btn-sm" onclick="runLogSniff()">Sniff &amp; inventory log</button>
                <button class="btn btn-outline-light btn-sm" onclick="extractCredentials()">Extract credentials</button>
            </div>
            <p class="text-secondary small mt-2 mb-0">Sniff &amp; inventory attempts to identify the stealer family from the log's structure and counts what type of data it contains. Extract pulls out every unique URL, login, password, email, crypto wallet and Discord/Telegram token it can find.</p>
        </div></div>
    </div>

    <div id="seg-public" class="st-seg">
        <div class="card reveal in-view"><div class="card-body">
            <h2 class="h6 mb-3">Public stealer-log search (Hudson Rock free index)</h2>
            <p class="text-secondary small mb-3">Queries Hudson Rock's free infostealer index — billions of records from RedLine, Raccoon, Vidar/Lumma, StealC, AZORult and more. Shows you which infected-device logs contain your email, username, IP or domain. Your query goes from this server to <span style="font-family:monospace">cavalier.hudsonrock.com</span> — the same endpoint their free OSINT tools use.</p>
            <div class="d-flex gap-2 mb-3 flex-wrap">
                <button class="btn btn-outline-light btn-sm" id="st-seg-btn-email" onclick="switchPubType('email')">Email</button>
                <button class="btn btn-outline-light btn-sm" id="st-seg-btn-username" onclick="switchPubType('username')">Username</button>
                <button class="btn btn-outline-light btn-sm" id="st-seg-btn-ip" onclick="switchPubType('ip')">IP address</button>
                <button class="btn btn-outline-light btn-sm" id="st-seg-btn-domain" onclick="switchPubType('domain')">Domain</button>
            </div>
            <form id="f-public" onsubmit="return doPublicSearch(event)">
                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="type" id="st-pub-type" value="email">
                <div class="row g-2">
                    <div class="col-md-7">
                        <input class="form-control" name="value" id="st-pub-value" type="text" autocomplete="off" placeholder="you@example.com">
                    </div>
                    <div class="col-md-5">
                        <button class="btn btn-primary w-100" type="submit">Search public logs</button>
                    </div>
                </div>
            </form>
        </div></div>
    </div>

    <div id="seg-password" class="st-seg">
        <div class="card reveal in-view"><div class="card-body">
            <h2 class="h6 mb-3">Password against public dumps</h2>
            <form id="f-password" onsubmit="return doPasswordCheck(event)">
                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                <div class="row g-2">
                    <div class="col-md-7">
                        <input class="form-control" name="value" type="password" autocomplete="off" placeholder="Password to check" required>
                    </div>
                    <div class="col-md-5">
                        <button class="btn btn-primary w-100" type="submit">Check</button>
                    </div>
                </div>
                <p class="text-secondary small mt-2 mb-0">Queries api.pwnedpasswords.com with only the first 5 chars of the SHA-1 hash. The full password never leaves your browser.</p>
            </form>
        </div></div>
    </div>

    <div id="seg-families" class="st-seg">
        <div class="card reveal in-view"><div class="card-body">
            <h2 class="h6 mb-3">Common infostealer families</h2>
            <div id="st-families"></div>
        </div></div>
    </div>

    <div id="st-error" class="alert alert-danger mt-3 d-none reveal in-view"></div>

    <div id="st-result-log" class="st-result-box mt-4">
        <div class="row g-3 mb-3">
            <div class="col-md-4">
                <div class="card"><div class="card-body text-center">
                    <div class="text-secondary small text-uppercase" style="letter-spacing:1px;font-size:.75rem;">Matches</div>
                    <div class="mt-1" style="font-size:1.6rem;font-weight:700;" id="st-matches">--</div>
                </div></div>
            </div>
            <div class="col-md-4">
                <div class="card"><div class="card-body text-center">
                    <div class="text-secondary small text-uppercase" style="letter-spacing:1px;font-size:.75rem;">Lines scanned</div>
                    <div class="mt-1" style="font-size:1.6rem;font-weight:700;" id="st-lines">--</div>
                </div></div>
            </div>
            <div class="col-md-4">
                <div class="card"><div class="card-body text-center">
                    <div class="text-secondary small text-uppercase" style="letter-spacing:1px;font-size:.75rem;">Verdict</div>
                    <div class="mt-1" style="font-size:1.6rem;font-weight:700;" id="st-verdict">--</div>
                </div></div>
            </div>
        </div>
        <div class="card mb-3"><div class="card-body">
            <h2 class="h6 mb-3">Found lines (context)</h2>
            <div id="st-matches-list"></div>
        </div></div>
        <div class="card"><div class="card-body">
            <h2 class="h6 mb-3">What to do now</h2>
            <div id="st-recs"></div>
        </div></div>
    </div>

    <div id="st-result-sniff" class="st-result-box mt-4">
        <div class="card"><div class="card-body">
            <h2 class="h6 mb-3">Log inventory</h2>
            <div id="st-sniff-content"></div>
        </div></div>
    </div>

    <div id="st-result-creds" class="st-result-box mt-4">
        <div class="card mb-3"><div class="card-body">
            <h2 class="h6 mb-3">Extracted credentials <span class="text-secondary small">(unique values)</span></h2>
            <div id="st-creds-rows"></div>
        </div></div>
        <div class="card"><div class="card-body">
            <h2 class="h6 mb-3">Extracted tokens &amp; wallets</h2>
            <div id="st-creds-extra"></div>
        </div></div>
    </div>

    <div id="st-result-pub" class="st-result-box mt-4">
        <div class="row g-3 mb-3" id="st-pub-cards"></div>
        <div class="card"><div class="card-body">
            <div id="st-pub-content"></div>
        </div></div>
    </div>

    <div id="st-result-pw" class="st-result-box mt-4">
        <div class="card"><div class="card-body">
            <div id="st-pw-content"></div>
        </div></div>
    </div>
</div>

<script>
(function(){
    var CUR_PUB='email';

    function switchSeg(mode){
        ['log','public','password','families'].forEach(function(m){
            var el=document.getElementById('seg-'+m);
            if(el) el.classList.toggle('active', m===mode);
        });
    }
    window.switchSeg=switchSeg;

    function switchPubType(t){
        CUR_PUB=t;
        document.getElementById('st-pub-type').value=t;
        var ph={email:'you@example.com',username:'username',ip:'8.8.8.8',domain:'example.com'};
        var ph2={email:'Search an email address',username:'Search a username (login)',ip:'Search an IP address',domain:'Search a domain (e.g. example.com)'};
        document.getElementById('st-pub-value').placeholder=ph[t];
        ['email','username','ip','domain'].forEach(function(m){
            var btn=document.getElementById('st-seg-btn-'+m);
            if(btn) btn.classList.toggle('w-25', m===t);
        });
    }
    window.switchPubType=switchPubType;

    function esc(s){
        var d=document.createElement('div');d.appendChild(document.createTextNode(s==null?'':s));return d.innerHTML;
    }

    var FAMILIES=[
        ['RedLine Stealer','Commodity stealer sold on forums. Harvests browser passwords, cookies, autofill, crypto wallets, Telegram session files. Usually paired with a builder panel.','High'],
        ['Raccoon Stealer','Modular stealer (libraries for passwords, cookies, wallets, files). Subscribers rent access; logs delivered to builder/drop panel.','High'],
        ['Vidar / Lumma / StealC','StealC and Lumma are current mainstream stealers; target browser data, 2FA session cookies, crypto extensions, and credit-card autofill.','High'],
        ['AZORult','Older widely distributed stealer used to harvest credentials + files and often drops ransomware.','High'],
        ['Agent Tesla','Keylogger + stealer spread via phishing attachments (Word/Excel macros, ISO).','Medium'],
        ['Formbook / XLoader','Keylogger + formgrabber, sold as malware-as-a-service; steals saved credentials and clipboard.','Medium'],
        ['Predator / CryptBot','Chrome/Edge-targeting stealer distributed through cracked software and fake update pages.','High'],
        ['Danabot','Banking trojan + stealer focused on browser passwords and crypto wallets in some campaigns.','Medium']
    ];

    function renderFamilies(){
        var h='';
        FAMILIES.forEach(function(f){
            h+='<div class="st-row"><div class="lbl"><strong>'+esc(f[0])+'</strong><div class="text-secondary small" style="max-width:560px;">'+esc(f[1])+'</div></div><span class="st-chip danger">'+esc(f[2])+'</span></div>';
        });
        document.getElementById('st-families').innerHTML=h;
    }

    var fileInput=document.getElementById('st-file');
    fileInput.addEventListener('change',function(){
        var f=fileInput.files[0];
        if(!f) return;
        document.getElementById('st-fileinfo').textContent=f.name+' ('+((f.size/1024).toFixed(1))+' KB)';
        var reader=new FileReader();
        reader.onload=function(ev){
            var text=String(ev.target.result||'');
            if(text.length>3000000){text=text.slice(0,3000000);}
            document.getElementById('st-log').value=text;
            document.getElementById('st-fileinfo').textContent+=' — loaded';
        };
        reader.readAsText(f);
    });

    function getLogAndTerms(){
        var log=document.getElementById('st-log').value||'';
        var terms=(document.getElementById('st-terms').value||'').split(/\r?\n/).map(function(t){return t.trim();}).filter(Boolean);
        return {log:log,terms:terms};
    }

    function showErr(msg){
        var errBox=document.getElementById('st-error');
        errBox.textContent=msg;errBox.classList.remove('d-none');
    }

    function matchLine(line, term, mode){
        if(mode==='exactline') return line.toLowerCase()===term.toLowerCase();
        if(mode==='word'){
            var re=new RegExp('(^|[^a-zA-Z0-9@._-])'+term.replace(/[.*+?^${}()|[\]\\]/g,'\\$&')+'($|[^a-zA-Z0-9@._-])','i');
            return re.test(line);
        }
        return line.toLowerCase().indexOf(term.toLowerCase())!==-1;
    }

    function runLogSearch(){
        var errBox=document.getElementById('st-error');
        var resultBox=document.getElementById('st-result-log');
        errBox.classList.add('d-none');
        resultBox.classList.remove('visible');

        var L=getLogAndTerms();
        var mode=document.getElementById('st-mode').value;
        var maxShow=parseInt(document.getElementById('st-limit').value||'200',10);

        if(L.terms.length===0){showErr('Add at least one search term (email, username, password, IP...).');return;}
        if(L.log.trim()===''){showErr('Paste a log or load a file first.');return;}

        var lines=L.log.split(/\r?\n/);
        var total=lines.length;
        var found=[];

        L.terms.forEach(function(term){
            if(term.length<2) return;
            for(var i=0;i<lines.length;i++){
                if(matchLine(lines[i],term,mode)){
                    found.push({term:term, line:lines[i], ln:i+1});
                }
            }
        });
        var seen={};
        found=found.filter(function(m){ if(seen[m.ln]) return false; seen[m.ln]=true; return true; });

        var matches=found.length;
        document.getElementById('st-matches').textContent=matches;
        document.getElementById('st-lines').textContent=total.toLocaleString();
        var verdictEl=document.getElementById('st-verdict');
        if(matches>0){verdictEl.textContent='COMPROMISED';verdictEl.style.color='#e74c3c';}
        else{verdictEl.textContent='NO MATCH';verdictEl.style.color='#26d07c';}

        var listHtml='';
        if(found.length===0){
            listHtml='<div class="text-secondary small">No matching lines found for your search terms in the provided log.</div>';
        }else{
            found.slice(0,maxShow).forEach(function(m){
                var hl=esc(m.line);
                try{
                    var q=esc(m.term);
                    hl=hl.split(q).join('<b>'+q+'</b>');
                }catch(e){}
                listHtml+='<div class="st-match"><span class="st-chip info">line '+m.ln+'</span> '+hl+'</div>';
            });
            if(found.length>maxShow) listHtml+='<div class="text-secondary small mt-2">… and '+(found.length-maxShow)+' more matching line(s).</div>';
        }
        document.getElementById('st-matches-list').innerHTML=listHtml;

        var recHtml='';
        if(matches>0){
            recHtml+='<div class="st-row"><span class="lbl">&#9888; Change every password that appears in the matched lines immediately — credentials tied to your email/username have appeared in stealer logs.</span></div>';
            recHtml+='<div class="st-row"><span class="lbl">&#9888; Rotate the password you searched for — matching credentials have been exposed to log collectors.</span></div>';
            recHtml+='<div class="st-row"><span class="lbl">&#128274; Enable 2FA (app-based) on every account; your session cookies may also be stolen — log out of all devices.</span></div>';
            recHtml+='<div class="st-row"><span class="lbl">&#128270; Do not reuse the leaked password anywhere.</span></div>';
        }else{
            recHtml+='<div class="st-row"><span class="lbl">&#9989; No matches found in this log. Still change any password you suspect and enable 2FA for safety.</span></div>';
        }
        document.getElementById('st-recs').innerHTML=recHtml;

        resultBox.classList.add('visible');
        document.querySelectorAll('#st-result-log .reveal').forEach(function(el){el.classList.add('in-view');});
    }
    window.runLogSearch=runLogSearch;

    function sniffFamily(text){
        var markers=[
            ['RedLine','RedLine Stealer',['==========','--------------------------','XOR PASSWORD','AUTO FILL DATA','CREDIT CARD DATA','StealingFile']],
            ['Lumma','LummaC2',['"device":"','"os_info":"','"hardware_id":','"build_id":"']],
            ['Raccoon','Raccoon V2',['"Version":"','"Farms":"','"FilesGrabber":']],
            ['Vidar','Vidar',['============================','[+] ','Vidar','------------- Cookies']],
            ['StealC','StealC',['"url":"','"login":"','"password":"']],
            ['Azorult','AZORult',['[Files]','[Passwords]','[Autofill]','Azorult']],
            ['AgentTesla','Agent Tesla',['===== Screens =====','===== Clipboard =====','===== Logs =====']],
            ['Predator','Predator AIO',['Predator','.html (hook)']],
            ['CryptBot','CryptBot',['cookies.sqlite','Web Data','Local State']]
        ];
        var hits={};
        markers.forEach(function(m){
            var count=0;
            m[2].forEach(function(k){
                var idx=0;
                while(true){
                    idx=text.indexOf(k,idx);
                    if(idx===-1) break;
                    count++; idx+=k.length;
                }
            });
            if(count>0) hits[m[0]]=count;
        });
        if(Object.keys(hits).length===0){
            if(/^(https?:\/\/[^\s|:]+[:|]|^[^\s@]+@[^\s]+\.[^\s]+[:|]?)/im.test(text)) return {family:'Generic / combo list', score:3, hits:{Generic:1}};
            return {family:'Unknown format', score:0, hits:{}};
        }
        var best=null; var bestScore=0;
        Object.keys(hits).forEach(function(f){ if(hits[f]>bestScore){bestScore=hits[f];best=f;} });
        var famName=best; markers.forEach(function(m){ if(m[0]===best) famName=m[1]; });
        return {family:famName, score:Math.min(100,bestScore*10+20), hits:hits};
    }

    function runLogSniff(){
        var errBox=document.getElementById('st-error');
        var box=document.getElementById('st-result-sniff');
        errBox.classList.add('d-none'); box.classList.remove('visible');
        var L=getLogAndTerms();
        if(L.log.trim()===''){showErr('Paste a log or load a file first.');return;}

        var text=L.log;
        var verdict=sniffFamily(text);

        var lines=text.split(/\r?\n/);
        var count={url:0,email:0,ip:0,wallet:0,discord:0,telegram:0,cc:0,json:0,base64:0};
        var seen={};

        function bump(k){ if(!seen[k]) seen[k]={}; }

        var emailRe=/([a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,})/g,m;
        while((m=emailRe.exec(text))!==null){count.email++;}

        var ipRe=/(\b(?:[0-9]{1,3}\.){3}[0-9]{1,3}\b)/g;
        while((m=ipRe.exec(text))!==null){count.ip++;}

        var urlRe=/(https?:\/\/[^\s"'<>]+)/g;
        while((m=urlRe.exec(text))!==null){count.url++;}

        var discRe=/([a-zA-Z0-9]{24}\.[a-zA-Z0-9]{6}\.[a-zA-Z0-9]{27})/g;
        while((m=discRe.exec(text))!==null){count.discord++;}

        var telRe=/([0-9]{8,10}:[A-Za-z0-9_-]{35})/g;
        while((m=telRe.exec(text))!==null){count.telegram++;}

        var walRe=/(\b(bc1[a-zA-HJ-NP-Z0-9]{25,39}|0x[a-fA-F0-9]{40}|[13][a-km-zA-HJ-NP-Z1-9]{25,34})\b)/g;
        while((m=walRe.exec(text))!==null){count.wallet++;}

        var ccRe=/(\b(?:4[0-9]{12}(?:[0-9]{3})?|5[1-5][0-9]{14}|3[47][0-9]{13}|6(?:011|5[0-9]{2})[0-9]{12})\b)/g;
        while((m=ccRe.exec(text))!==null){count.cc++;}

        var jsonPairs=(text.match(/"(url|login|password|host|username|email|pass)"/gi)||[]).length;
        count.json=jsonPairs;
        count.base64=(text.match(/[A-Za-z0-9+/]{40,}={0,2}/g)||[]).length;

        var risk=((count.cc>0)?1:0)+((count.wallet>0)?1:0)+((count.discord>0)?1:0)+((count.telegram>0)?1:0)+((count.email>20)?1:0);
        var riskLbl=risk>=3?'Very sensitive':(risk>=2?'Sensitive':(risk>=1?'Moderate':'Low'));

        var h='<div class="st-row"><span class="lbl">Detected stealer family</span><span class="st-chip '+(verdict.score>0?'danger':'info')+'">'+esc(verdict.family)+'</span></div>';
        h+='<div class="st-row"><span class="lbl">Confidence</span><div style="flex:1;max-width:180px;"><div class="st-meter"><div style="width:'+verdict.score+'%;background:'+(verdict.score>50?'#e74c3c':(verdict.score>20?'#ffc107':'#8b93f5'))+'"></div></div></div><span class="text-secondary small">'+verdict.score+'%</span></div>';

        var rows=[
            ['Lines processed',lines.length.toLocaleString()],
            ['Email addresses found',count.email],
            ['IP addresses found',count.ip],
            ['URL / host entries',count.url],
            ['Crypto wallet addresses',count.wallet],
            ['Credit-card numbers',count.cc],
            ['Discord tokens',count.discord],
            ['Telegram tokens',count.telegram],
            ['Login/password JSON fields',count.json],
            ['Base64 blobs',count.base64]
        ];
        rows.forEach(function(r){
            var cls = (r[0].indexOf('wallet')!==-1||r[0].indexOf('card')!==-1||r[0].indexOf('Discord')!==-1||r[0].indexOf('Telegram')!==-1) ? (r[1]>0?'danger':'ok') : (r[1]>0?'info':'ok');
            h+='<div class="st-row"><span class="lbl">'+esc(r[0])+'</span><span class="st-chip '+cls+'">'+esc(r[1])+'</span></div>';
        });
        h+='<div class="st-row"><span class="lbl">Overall sensitivity</span><span class="st-chip '+(risk>=2?'danger':(risk>=1?'warn':'ok'))+'">'+riskLbl+'</span></div>';
        if(Object.keys(verdict.hits).length>0){
            h+='<div class="mt-2"><span class="text-secondary small">Indicators found:</span><div>';
            Object.keys(verdict.hits).forEach(function(k){
                h+='<span class="st-tag">'+esc(k)+' \u00d7'+verdict.hits[k]+'</span>';
            });
            h+='</div></div>';
        }

        document.getElementById('st-sniff-content').innerHTML=h;
        box.classList.add('visible');
        document.querySelectorAll('#st-result-sniff .reveal').forEach(function(el){el.classList.add('in-view');});
    }
    window.runLogSniff=runLogSniff;

    function extractCredentials(){
        var errBox=document.getElementById('st-error');
        var box=document.getElementById('st-result-creds');
        errBox.classList.add('d-none'); box.classList.remove('visible');
        var L=getLogAndTerms();
        if(L.log.trim()===''){showErr('Paste a log or load a file first.');return;}

        var text=L.log;
        var urlLogin=new Map();
        var emails=new Set();
        var tokens={discord:[],telegram:[]};
        var wallets=new Set();

        var emailRe=/([a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,})/g,m;
        while((m=emailRe.exec(text))!==null){ emails.add(m[1].toLowerCase()); }

        var discRe=/([a-zA-Z0-9]{24}\.[a-zA-Z0-9]{6}\.[a-zA-Z0-9]{27})/g;
        while((m=discRe.exec(text))!==null){ tokens.discord.push(m[1]); }
        var telRe=/([0-9]{8,10}:[A-Za-z0-9_-]{35})/g;
        while((m=telRe.exec(text))!==null){ tokens.telegram.push(m[1]); }
        var walRe=/(\b(bc1[a-zA-HJ-NP-Z0-9]{25,39}|0x[a-fA-F0-9]{40}|[13][a-km-zA-HJ-NP-Z1-9]{25,34})\b)/g;
        while((m=walRe.exec(text))!==null){ wallets.add(m[1]); }

        // patterns: url:login:password | url|login|password | csv "url","login","password"
        text.split(/\r?\n/).forEach(function(line){
            var parts=null;
            var sep=null;
            if(/http(s?):\/\/[^ ]*[|:][^ |:]*[|:][^ |]*/.test(line)){ sep = line.indexOf(':password')!==-1?':':null; }
            if(/^https?:\/\/[^|"]+[|][^|]+[|][^|]+$/.test(line)){ parts=line.split('|'); }
            else if(/^https?:\/\/[^:"]+:[^:]+:[^:]+$/.test(line) && (line.match(/:/g)||[]).length===3){ parts=line.split(':'); parts=[parts[0]+':'+parts[1], parts[2], parts[3]]; }
            if(parts && parts.length===3){
                var key=parts[0].replace(/^https?:\/\//,'').split('/')[0];
                var login=parts[1], pass=parts[2];
                if(pass.length>1 && pass.length<200 && login.length<200){
                    if(!urlLogin.has(key)) urlLogin.set(key,new Map());
                    var mp=urlLogin.get(key);
                    if(!mp.has(login)) mp.set(login,{count:0,passwords:new Set()});
                    var rec=mp.get(login);
                    rec.count++;
                    rec.passwords.add(pass);
                }
            }
        });

        var arr=[];
        urlLogin.forEach(function(mp,host){
            mp.forEach(function(rec,login){
                arr.push({host:host,login:login,count:rec.count,passwords:Array.from(rec.passwords).slice(0,5)});
            });
        });
        arr.sort(function(a,b){return b.count-a.count;});

        var h='';
        if(arr.length===0){
            h+='<div class="text-secondary small">No <span style="font-family:monospace">url:login:password</span> style records found.</div>';
        }else{
            h+='<table class="st-tbl"><thead><tr><th>Host</th><th>Login</th><th>Times</th><th>Sample password(s)</th></tr></thead><tbody>';
            arr.slice(0,150).forEach(function(r){
                var pwds=r.passwords.map(function(p){return '<span style="font-family:monospace;color:var(--dim)">'+esc(p)+'</span>';}).join(', ');
                h+='<tr><td>'+esc(r.host)+'</td><td>'+esc(r.login)+'</td><td>'+r.count+'</td><td style="word-break:break-all;max-width:260px;">'+pwds+'</td></tr>';
            });
            h+='</tbody></table>';
            if(arr.length>150) h+='<div class="text-secondary small mt-2">… and '+(arr.length-150)+' more unique login records.</div>';
        }
        document.getElementById('st-creds-rows').innerHTML=h;

        var e='';
        if(emails.size>0){ e+='<div class="st-row"><span class="lbl">Unique emails</span><span class="st-chip info">'+emails.size+'</span></div><div class="mb-2">'+Array.from(emails).slice(0,60).map(function(x){return '<span class="st-tag">'+esc(x)+'</span>';}).join('')+(emails.size>60?' <span class="text-secondary small">+ '+ (emails.size-60)+' more</span>':'')+'</div>'; }
        if(arr.length===0 && emails.size===0){ e+='<div class="text-secondary small">Nothing structured detected. Very minimal or already-extracted log.</div>'; }
        if(tokens.discord.length>0){ e+='<div class="st-row"><span class="lbl">Discord tokens</span><span class="st-chip danger">'+tokens.discord.length+'</span></div><div class="mb-2">'+tokens.discord.slice(0,10).map(function(x){return '<span class="st-tag">'+esc(x)+'</span>';}).join('')+'</div>'; }
        if(tokens.telegram.length>0){ e+='<div class="st-row"><span class="lbl">Telegram tokens</span><span class="st-chip danger">'+tokens.telegram.length+'</span></div><div class="mb-2">'+tokens.telegram.slice(0,10).map(function(x){return '<span class="st-tag">'+esc(x)+'</span>';}).join('')+'</div>'; }
        if(wallets.size>0){ e+='<div class="st-row"><span class="lbl">Crypto wallets</span><span class="st-chip danger">'+wallets.size+'</span></div><div class="mb-2">'+Array.from(wallets).slice(0,10).map(function(x){return '<span class="st-tag">'+esc(x)+'</span>';}).join('')+'</div>'; }
        document.getElementById('st-creds-extra').innerHTML=e;

        box.classList.add('visible');
        document.querySelectorAll('#st-result-creds .reveal').forEach(function(el){el.classList.add('in-view');});
    }
    window.extractCredentials=extractCredentials;

    function doPublicSearch(e){
        e.preventDefault();
        var form=e.target;
        var errBox=document.getElementById('st-error');
        var box=document.getElementById('st-result-pub');
        errBox.classList.add('d-none'); box.classList.remove('visible');

        var fd=new FormData(form);
        fd.append('mode','public');
        var type=document.getElementById('st-pub-type').value;

        var btn=form.querySelector('button[type=submit]');
        var old=btn.innerHTML;btn.disabled=true;btn.innerHTML='Searching public index...';

        fetch('index.php',{method:'POST',body:fd})
            .then(function(r){return r.json();})
            .then(function(d){
                btn.disabled=false;btn.innerHTML=old;
                if(d.error){showErr(d.error);return;}
                renderPublic(d,type);
            })
            .catch(function(err){
                btn.disabled=false;btn.innerHTML=old;
                showErr(err.message||'Network error. Try again.');
            });
        return false;
    }
    window.doPublicSearch=doPublicSearch;

    function renderPublic(d,type){
        var cards='';
        var content='';
        var pi=d.public_check;
        if(!pi){ document.getElementById('st-pub-content').innerHTML='<div class="text-secondary small">No data returned.</div>'; return; }

        if(pi.domain){
            var dom=pi.domain;
            var total=parseInt(dom.total||0,10);
            var users=parseInt(dom.users||0,10);
            var employees=parseInt(dom.employees||0,10);
            var third=parseInt(dom.third_parties||0,10);

            cards+=statCard('Records', total.toLocaleString(), total>0?'danger':'ok', 'log lines across infected devices');
            cards+=statCard('User creds', users.toLocaleString(), users>0?'danger':'ok', 'consumer credentials in logs');
            cards+=statCard('Employee creds', employees.toLocaleString(), employees>0?'danger':'ok', 'corporate credentials in logs');

            var h='';
            h+='<div class="st-row"><span class="lbl">Total exposure verdict</span><span class="st-chip '+(total>0?'danger':'ok')+'">'+(total>0?'Present in stealer logs':'No records found')+'</span></div>';
            if(third>0) h+='<div class="st-row"><span class="lbl">Third-party (related domains)</span><span class="st-chip info">'+third.toLocaleString()+'</span></div>';

            var families=dom.stealerFamilies||null;
            if(families){
                var famArr=Object.keys(families).filter(function(k){return k!=='total';}).map(function(k){return {name:k,count:families[k]};}).sort(function(a,b){return b.count-a.count;}).slice(0,12);
                h+='<div class="st-row"><span class="lbl">Top stealer families</span></div><div class="mb-2">'+famArr.map(function(f){return '<span class="st-tag">'+esc(f.name)+' \u00d7'+f.count.toLocaleString()+'</span>';}).join('')+'</div>';
            }
            if(dom.applications && dom.applications.length){
                h+='<div class="st-row"><span class="lbl">Highest-value apps</span><span class="st-tag">'+dom.applications.map(function(a){return esc(a.keyword||a);}).join('</span><span class="st-tag">')+'</span></div>';
            }
            if(dom.last_user_compromised) h+='<div class="st-row"><span class="lbl">Last user compromise</span><span class="st-chip info">'+esc(String(dom.last_user_compromised).slice(0,16).replace('T',' '))+'</span></div>';

            h+='<div class="text-secondary small mt-3">Hudson Rock free index \u2014 counts only, individual accounts stay gated to protect privacy. Re-search after ~10 min for fresher numbers.</div>';

            var stats=dom.stats||null;
            if(dom.data && dom.data.all_urls && dom.data.all_urls.length){
                h+='<div class="mt-3"><h6 class="mb-2">Most-compromised URLs for this domain</h6><table class="st-tbl"><thead><tr><th>URL</th><th>Type</th><th>Occurrences</th></tr></thead><tbody>';
                dom.data.all_urls.slice(0,25).forEach(function(u){
                    h+='<tr><td style="word-break:break-all;max-width:380px;">'+esc(u.url)+'</td><td>'+esc(u.type.toLowerCase()==='employee'?'Employee':'User')+'</td><td>'+u.occurrence+'</td></tr>';
                });
                h+='</tbody></table></div>';
            }
            content=h;
        } else {
            var stealers=pi.stealers||[];
            var corp=parseInt(pi.total_corporate_services||0,10);
            var user=parseInt(pi.total_user_services||0,10);

            cards+=statCard('Exposed', stealers.length>0?'YES':'NO', stealers.length>0?'danger':'ok', stealers.length>0?(stealers.length+' infected-device log(s)'):'no infected-device logs found');
            cards+=statCard('Corporate', corp.toLocaleString(), corp>0?'danger':'ok', 'corporate service logins at risk');
            cards+=statCard('User-level', user.toLocaleString(), user>0?'danger':'ok', 'consumer service logins at risk');

            var h='';
            if(stealers.length===0){
                h='<div class="text-secondary small">Good news \u2014 no infected-device stealer log in Hudson Rock\u2019s free index contains this '+esc(type)+'. Keep using unique passwords and 2FA anyway.</div>';
            }else{
                h+='<div class="text-secondary small mb-3">This value appeared in '+stealers.length+' infected-device log(s). Spread assumes every saved credential on those machines is exposed.</div>';
                stealers.forEach(function(s){
                    var date=String(s.date_compromised||'Unknown').slice(0,16).replace('T',' ');
                    var fam=s.stealer_family||'Generic Stealer';
                    h+='<div class="st-pub-item">';
                    h+='<div class="d-flex justify-content-between align-items-center mb-1"><strong>'+esc(s.computer_name||'Unknown device')+'</strong><span class="st-chip danger">'+esc(fam)+'</span></div>';
                    h+='<div class="st-mini">Infected '+esc(date)+' \u00b7 '+esc(s.operating_system||'Unknown OS')+' \u00b7 IP '+esc(s.ip||'?')+'</div>';
                    if(s.malware_path && s.malware_path!=='Not Found') h+='<div class="st-mini">Payload: '+esc(s.malware_path)+'</div>';
                    var skipV=function(a){return (a||[]).filter(function(x){return !/^\*+$/.test(x);});};
                    var logins=skipV(s.top_logins).slice(0,5);
                    if(logins.length){
                        h+='<div class="st-mini mt-1">Top associated logins:</div><div>'+logins.map(function(x){return '<span class="st-tag">'+esc(x)+'</span>';}).join('')+'</div>';
                    }
                    if(parseInt(s.total_corporate_services||0,10)>0) h+='<div class="st-mini">'+parseInt(s.total_corporate_services,10)+' corporate service credentials exposed.</div>';
                    if(parseInt(s.total_user_services||0,10)>0) h+='<div class="st-mini">'+parseInt(s.total_user_services,10)+' user credentials exposed.</div>';
                    h+='</div>';
                });
                h+='<div class="st-row mt-2"><span class="lbl">Recommended action</span><span class="st-chip danger">Treat every account on these devices as compromised \u2014 rotate all passwords, log out all sessions, enable 2FA</span></div>';
            }
            content=h;
        }

        document.getElementById('st-pub-cards').innerHTML=cards;
        document.getElementById('st-pub-content').innerHTML=content;
        var box=document.getElementById('st-result-pub');
        box.classList.add('visible');
        document.querySelectorAll('#st-result-pub .reveal').forEach(function(el){el.classList.add('in-view');});
    }

    function statCard(label,val,cls,note){
        return '<div class="col-md-4"><div class="card"><div class="card-body text-center">'+
            '<div class="text-secondary small text-uppercase" style="letter-spacing:1px;font-size:.75rem;">'+esc(label)+'</div>'+
            '<div class="mt-1" style="font-size:1.5rem;font-weight:700;color:'+(cls==='danger'?'#e74c3c':(cls==='warn'?'#ffc107':'#26d07c'))+'">'+esc(val)+'</div>'+
            '<div class="text-secondary small">'+esc(note)+'</div>'+
            '</div></div></div>';
    }

    function doPasswordCheck(e){
        e.preventDefault();
        var form=e.target;
        var errBox=document.getElementById('st-error');
        var pwBox=document.getElementById('st-result-pw');
        errBox.classList.add('d-none');
        pwBox.classList.remove('visible');

        var fd=new FormData(form);
        fd.append('mode','password');

        var btn=form.querySelector('button[type=submit]');
        var old=btn.innerHTML;btn.disabled=true;btn.innerHTML='Checking...';

        fetch('index.php',{method:'POST',body:fd})
            .then(function(r){return r.json();})
            .then(function(d){
                btn.disabled=false;btn.innerHTML=old;
                if(d.error){showErr(d.error);return;}
                var n=(d.password_range?d.password_range.count:0)||0;
                var verdict=n>0?'Breached':'Not found in dumps';
                var color=n>0?'#e74c3c':'#26d07c';
                var html='<div class="text-center py-2" style="font-size:1.5rem;font-weight:700;color:'+color+'">'+esc(verdict)+'</div>';
                html+='<div class="st-row"><span class="lbl">Times seen in public dumps</span><span class="st-chip '+(n>0?'danger':'ok')+'">'+n.toLocaleString()+'</span></div>';
                html+='<div class="st-row"><span class="lbl">Common status</span><span class="st-chip '+(n>10000?'danger':(n>100?'warn':'info'))+'">'+(n>10000?'Very common — burned':(n>100?'Common — risky':'Uncommon'))+'</span></div>';
                document.getElementById('st-pw-content').innerHTML=html;
                pwBox.classList.add('visible');
            })
            .catch(function(err){
                btn.disabled=false;btn.innerHTML=old;
                showErr(err.message||'Network error. Try again.');
            });
        return false;
    }
    window.doPasswordCheck=doPasswordCheck;

    switchPubType('email');
    renderFamilies();
})();
</script>
<?php page_footer(); ?>

<?php
function hibp_password_range(string $password): array {
    $sha = strtoupper(hash('sha1', $password));
    $prefix = substr($sha, 0, 5);
    $suffix = substr($sha, 5);

    $ch = curl_init('https://api.pwnedpasswords.com/range/' . $prefix);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 12,
        CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => 0,
        CURLOPT_USERAGENT => 'KevBin-StealerCheck',
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

    return ['password_range' => ['count' => $count]];
}

/**
 * Query Hudson Rock's free infostealer OSINT index.
 * Valid $types: email, username, ip, domain.
 */
function hr_stealer_search(string $type, string $value): array {
    // basic per-type validation
    if ($type === 'email' && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
        return ['error' => 'That does not look like a valid email address.'];
    }
    if ($type === 'ip' && !filter_var($value, FILTER_VALIDATE_IP)) {
        return ['error' => 'That does not look like a valid IP address.'];
    }
    if ($type === 'username' && (strlen($value) < 1 || strlen($value) > 128)) {
        return ['error' => 'Username must be between 1 and 128 characters.'];
    }
    if ($type === 'domain' && !preg_match('/^([a-zA-Z0-9]([a-zA-Z0-9\-]{0,61}[a-zA-Z0-9])?\.)+[a-zA-Z]{2,}$/', $value)) {
        return ['error' => 'Enter a bare domain like example.com (no http://).'];
    }

    $query = [$type => $value];
    if ($type === 'domain') $query['limit'] = 5;
    $url = 'https://cavalier.hudsonrock.com/api/json/v2/osint-tools/search-by-' . $type . '?' . http_build_query($query);

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => 0,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_USERAGENT => 'Mozilla/5.0 (KevBin StealerCheck)',
        CURLOPT_HTTPHEADER => ['Accept: application/json'],
    ]);
    $body = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $err = curl_error($ch);
    curl_close($ch);

    if ($body === false) {
        return ['error' => 'Public stealer-log service unavailable' . ($err !== '' ? ' (' . $err . ')' : '') . '. Try again shortly.'];
    }

    $decoded = json_decode((string)$body, true);
    if (!is_array($decoded)) {
        $httpErr = ($code >= 400) ? ' (HTTP ' . $code . ')' : '';
        return ['error' => 'Unexpected response from the public index' . $httpErr . '.'];
    }

    if ($type === 'domain') {
        return [
            'public_check' => [
                'domain' => [
                    'total' => $decoded['total'] ?? 0,
                    'totalStealers' => $decoded['totalStealers'] ?? 0,
                    'users' => $decoded['users'] ?? 0,
                    'employees' => $decoded['employees'] ?? 0,
                    'third_parties' => $decoded['third_parties'] ?? 0,
                    'totalUrls' => $decoded['totalUrls'] ?? 0,
                    'last_user_compromised' => $decoded['last_user_compromised'] ?? '',
                    'last_employee_compromised' => $decoded['last_employee_compromised'] ?? '',
                    'stealerFamilies' => $decoded['stealerFamilies'] ?? null,
                    'applications' => $decoded['applications'] ?? [],
                    'data' => $decoded['data'] ?? [],
                    'stats' => $decoded['stats'] ?? null,
                ],
            ],
        ];
    }

    $stealers = [];
    foreach ((array)($decoded['stealers'] ?? []) as $s) {
        if (!is_array($s)) continue;
        $stealers[] = [
            'total_corporate_services' => (int)($s['total_corporate_services'] ?? 0),
            'total_user_services' => (int)($s['total_user_services'] ?? 0),
            'date_compromised' => (string)($s['date_compromised'] ?? ''),
            'stealer_family' => (string)($s['stealer_family'] ?? ''),
            'computer_name' => (string)($s['computer_name'] ?? ''),
            'operating_system' => (string)($s['operating_system'] ?? ''),
            'malware_path' => (string)($s['malware_path'] ?? ''),
            'ip' => (string)($s['ip'] ?? ''),
            'antiviruses' => (array)($s['antiviruses'] ?? []),
            'top_passwords' => array_slice(array_map('strval', (array)($s['top_passwords'] ?? [])), 0, 5),
            'top_logins' => array_slice(array_map('strval', (array)($s['top_logins'] ?? [])), 0, 5),
        ];
    }

    return [
        'public_check' => [
            'type' => $type,
            'stealers' => array_slice($stealers, 0, 12),
            'total_corporate_services' => (int)($decoded['total_corporate_services'] ?? 0),
            'total_user_services' => (int)($decoded['total_user_services'] ?? 0),
        ],
    ];
}