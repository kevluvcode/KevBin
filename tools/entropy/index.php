<?php
require_once __DIR__ . '/../../functions.php';

start_session();
$GLOBALS['_meta'] = [
    'description' => 'Free online password entropy calculator and visualizer. Analyze password strength in real-time with Shannon entropy, crack time estimates, pattern detection, and a built-in crypto-random password generator. 100% client-side — nothing is uploaded.',
    'keywords' => 'password entropy calculator, password strength checker, entropy bits, crack time calculator, password generator, shannon entropy, password security',
];
page_header('Password Entropy Calculator & Visualizer');
?>
<style>
    .entropy-bar-track{width:100%;height:28px;border-radius:14px;background:#1a1a2e;overflow:hidden;position:relative;border:1px solid var(--line);}
    .entropy-bar-fill{height:100%;border-radius:14px;transition:width .3s,background .3s;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:.85rem;color:#fff;text-shadow:0 1px 3px rgba(0,0,0,.6);min-width:0;}
    .pool-badge{display:inline-flex;align-items:center;gap:4px;padding:4px 10px;border-radius:8px;font-size:.78rem;font-weight:600;border:1px solid var(--line);background:#16162a;}
    .pool-badge.active{border-color:#26d07c;background:#0d2818;color:#26d07c;}
    .pool-badge.inactive{opacity:.4;}
    .crack-row{display:flex;justify-content:space-between;padding:7px 0;border-bottom:1px solid rgba(255,255,255,.06);font-size:.82rem;}
    .crack-row:last-child{border-bottom:none;}
    .crack-rate{color:var(--text-secondary,#888);}
    .crack-time{font-weight:600;font-family:'JetBrains Mono',monospace;font-size:.8rem;}
    .detail-pill{display:inline-block;padding:2px 8px;border-radius:6px;font-size:.72rem;font-weight:600;margin:2px;}
    .suggest-item{padding:6px 0;border-bottom:1px solid rgba(255,255,255,.05);font-size:.82rem;}
    .suggest-item:last-child{border-bottom:none;}
    .gen-output{font-family:'JetBrains Mono',monospace;font-size:1.1rem;word-break:break-all;min-height:2.2rem;padding:10px;border-radius:8px;background:#0d0d1a;border:1px solid var(--line);color:#c9d1d9;}
    .pw-input-wrap{position:relative;}
    .pw-input-wrap input{padding-right:48px;}
    .pw-toggle{position:absolute;right:4px;top:50%;transform:translateY(-50%);background:none;border:1px solid var(--line);border-radius:6px;color:#aaa;padding:4px 8px;cursor:pointer;font-size:.9rem;}
    .pw-toggle:hover{color:#fff;border-color:#666;}
    .section-title{font-size:.9rem;font-weight:700;color:#c9d1d9;border-bottom:1px solid var(--line);padding-bottom:8px;margin-bottom:12px;}
    #detail-issues .alert{font-size:.78rem;padding:5px 10px;margin-bottom:4px;}
    .len-slider{width:100%;accent-color:#5865f2;}
    .gen-option{display:flex;align-items:center;gap:8px;font-size:.82rem;padding:4px 0;}
    .gen-option input[type=checkbox]{accent-color:#5865f2;}
</style>

<div class="container" style="max-width:960px;">
    <h1 class="h4 mb-1 reveal in-view">Password Entropy Calculator & Visualizer</h1>
    <p class="text-secondary mb-4 reveal in-view">Analyze any password's true strength using Shannon entropy (E = L &times; log&#8322;(R)), see estimated crack times at every attack tier, detect weak patterns, and generate crypto-random passwords. Everything runs in your browser — nothing is sent anywhere.</p>

    <div class="card mb-4 reveal in-view"><div class="card-body">
        <label class="form-label small text-secondary mb-1">Enter a password to analyze</label>
        <div class="pw-input-wrap mb-3">
            <input id="pw-input" class="form-control form-control-lg" type="password" placeholder="Type or paste a password..." autocomplete="off" oninput="analyze()">
            <button class="pw-toggle" id="pw-toggle" onclick="togglePw()" title="Show / hide">&#128065;</button>
        </div>

        <div class="section-title">Entropy Strength</div>
        <div class="entropy-bar-track mb-2">
            <div class="entropy-bar-fill" id="ent-bar" style="width:0%;background:#333;"></div>
        </div>
        <div class="d-flex justify-content-between align-items-center mb-1">
            <span id="ent-label" class="text-secondary small">Enter a password to begin</span>
            <span id="ent-bits" class="text-secondary small" style="font-family:'JetBrains Mono',monospace;"></span>
        </div>

        <div class="section-title mt-3">Character Pool Breakdown</div>
        <div id="pool-box" class="d-flex flex-wrap gap-2 mb-1"></div>
        <div class="text-secondary small mt-1" id="pool-summary"></div>

        <div class="section-title mt-4">Crack Time Estimates</div>
        <div id="crack-box"></div>

        <div class="section-title mt-4">Password Strength Details</div>
        <div id="detail-pills" class="mb-2"></div>
        <div id="detail-issues"></div>
    </div></div>

    <div class="card mb-4 reveal in-view"><div class="card-body">
        <div class="section-title">Password Generator</div>
        <div class="row g-3 align-items-end">
            <div class="col-12 col-md-4">
                <label class="form-label small text-secondary">Length: <strong id="gen-len-val">20</strong></label>
                <input type="range" id="gen-len" class="len-slider" min="8" max="128" value="20" oninput="document.getElementById('gen-len-val').textContent=this.value">
            </div>
            <div class="col-12 col-md-8">
                <div class="d-flex flex-wrap">
                    <div class="gen-option"><input type="checkbox" id="gen-upper" checked><label for="gen-upper">Uppercase (A-Z)</label></div>
                    <div class="gen-option"><input type="checkbox" id="gen-lower" checked><label for="gen-lower">Lowercase (a-z)</label></div>
                    <div class="gen-option"><input type="checkbox" id="gen-digits" checked><label for="gen-digits">Digits (0-9)</label></div>
                    <div class="gen-option"><input type="checkbox" id="gen-symbols" checked><label for="gen-symbols">Symbols (!@#$...)</label></div>
                    <div class="gen-option"><input type="checkbox" id="gen-ambig"><label for="gen-ambig">Exclude ambiguous (Il1O0)</label></div>
                </div>
            </div>
        </div>
        <div class="d-flex gap-2 mt-3">
            <button class="btn btn-primary" onclick="genPassword()">Generate</button>
            <button class="btn btn-outline-light" onclick="copyGen()" id="gen-copy-btn">Copy</button>
        </div>
        <div class="gen-output mt-3" id="gen-output">Click Generate to create a password</div>
        <div class="text-secondary small mt-2" id="gen-entropy"></div>
    </div></div>

    <div class="card mb-4 reveal in-view"><div class="card-body">
        <h2 class="h6 mb-2">How entropy works</h2>
        <p class="text-secondary small mb-1"><strong>Shannon entropy</strong> = L &times; log&#8322;(R), where L is password length and R is the size of the character pool used. A password using lowercase only draws from 26 characters; mixing in uppercase, digits and symbols raises R — and thus entropy — exponentially.</p>
        <p class="text-secondary small mb-1"><strong>Crack times</strong> assume the attacker can test a fraction of the 2<sup>E</sup> total combinations per second. Real-world attacks may be slower (online rate limits) or faster (distributed GPU farms, precomputed rainbow tables for common passwords).</p>
        <p class="text-secondary small mb-0"><strong>Patterns</strong> like repeated characters, keyboard walks, dictionary words and sequential digits reduce effective entropy even if the raw bit count looks decent. The details panel flags these automatically.</p>
    </div></div>
</div>

<script>
(function(){
    var COMMON = ['password','123456','12345678','qwerty','abc123','monkey','dragon','111111','letmein','trustno1','admin','welcome','iloveyou','sunshine','princess','football','baseball','superman','batman','shadow','master','666666','123123','password1','qwerty123','michael','jennifer','12345','1234567','123456789','1234567890','p@ssw0rd','hunter2','login','passw0rd','starwars','hello','charlie','donald','access','thunder','loveme','golf','hammer','silver','phoenix','maverick','jessica','pepper','ranger','batman','killer','hockey','george','andrew','charlie','daniel','robert','basketball','matrix','matrix123','test','test123','example','summer','winter','spring','autumn','computer','internet','guitar','orange','banana'];
    var DICT = ['password','qwerty','admin','love','name','hello','secret','dragon','monkey','football','welcome','master','login','test','shadow','sunshine','princess','letmein','trustno1','iloveyou','access','hunter','michael','jennifer','jordan','robert','daniel','james','computer','internet','baseball','soccer','hockey','tomcat','matrix','batman','motorola','mustang','killer','falcon','corvette','merlin','wizard','phoenix'];
    var SEQUENTIAL = 'abcdefghijklmnopqrstuvwxyz012345678909876543210zyxwvutsrqponmlkjihgfedcba';
    var KB_ROWS = ['qwertyuiop','asdfghjkl','zxcvbnm','1234567890','qazwsx','edcrfv','tgbyhn'];

    var pools = [
        {name:'Lowercase', pat:/[a-z]/, size:26, id:'lower', code:'a-z'},
        {name:'Uppercase', pat:/[A-Z]/, size:26, id:'upper', code:'A-Z'},
        {name:'Digits', pat:/[0-9]/, size:10, id:'digits', code:'0-9'},
        {name:'Symbols', pat:/[^a-zA-Z0-9\s]/, size:33, id:'symbols', code:'!@#$...'},
        {name:'Space', pat:/\s/, size:1, id:'space', code:'space'}
    ];

    function getUnicodePool(pw) {
        var nonAscii = 0;
        for (var i = 0; i < pw.length; i++) {
            if (pw.charCodeAt(i) > 127) nonAscii++;
        }
        return nonAscii;
    }

    function detectPools(pw) {
        var detected = [];
        var unicodeCount = getUnicodePool(pw);
        for (var i = 0; i < pools.length; i++) {
            if (pools[i].pat.test(pw)) detected.push(pools[i]);
        }
        if (unicodeCount > 0) {
            var maxCode = 0;
            for (var j = 0; j < pw.length; j++) {
                var c = pw.charCodeAt(j);
                if (c > maxCode) maxCode = c;
            }
            var uSize = Math.min(Math.max(maxCode, 256), 65536) - 128;
            detected.push({name:'Unicode', pat:null, size:uSize, id:'unicode', code:'U+'});
        }
        return detected;
    }

    function calcEntropy(pw) {
        if (!pw) return 0;
        var detected = detectPools(pw);
        var R = 0;
        for (var i = 0; i < detected.length; i++) R += detected[i].size;
        if (R < 2) R = 2;
        return pw.length * Math.log2(R);
    }

    function formatTime(seconds) {
        if (!isFinite(seconds) || seconds < 0) return 'centuries+';
        if (seconds < 0.001) return 'instantly';
        if (seconds < 0.01) return '< 0.01 sec';
        if (seconds < 1) return seconds.toFixed(2) + ' sec';
        if (seconds < 60) return Math.round(seconds) + ' sec';
        if (seconds < 3600) return Math.round(seconds / 60) + ' min';
        if (seconds < 86400) return Math.round(seconds / 3600) + ' hr';
        if (seconds < 2592000) return Math.round(seconds / 86400) + ' days';
        if (seconds < 31557600) return Math.round(seconds / 2592000) + ' months';
        var yrs = seconds / 31557600;
        if (yrs < 1000) return Math.round(yrs) + ' yr';
        if (yrs < 1e6) return (yrs / 1000).toFixed(1) + 'K yr';
        if (yrs < 1e9) return (yrs / 1e6).toFixed(1) + 'M yr';
        if (yrs < 1e12) return (yrs / 1e9).toFixed(1) + 'B yr';
        return yrs.toExponential(1) + ' yr';
    }

    function strengthLabel(bits) {
        if (bits < 28) return {text:'Very Weak', color:'#dc3545'};
        if (bits < 36) return {text:'Weak', color:'#e8690a'};
        if (bits < 60) return {text:'Fair', color:'#eab308'};
        if (bits < 128) return {text:'Strong', color:'#6bcc5c'};
        return {text:'Very Strong', color:'#26d07c'};
    }

    function barColor(bits) {
        if (bits < 28) return '#dc3545';
        if (bits < 36) return '#e8690a';
        if (bits < 60) return '#eab308';
        if (bits < 128) return '#6bcc5c';
        return '#26d07c';
    }

    function detectPatterns(pw) {
        var low = pw.toLowerCase();
        var issues = [];
        var suggestions = [];

        if (pw.length === 0) return {issues:[], suggestions:[]};

        if (pw.length < 8) {
            issues.push({type:'warn', msg:'Too short — under 8 characters.'});
            suggestions.push('Use at least 12-16 characters for better security.');
        } else if (pw.length < 12) {
            issues.push({type:'info', msg:'Moderate length — 12+ characters recommended.'});
            suggestions.push('Consider using 16+ characters for strong security.');
        } else if (pw.length >= 20) {
            issues.push({type:'good', msg:'Excellent length (' + pw.length + ' chars).'});
        } else if (pw.length >= 16) {
            issues.push({type:'good', msg:'Good length (' + pw.length + ' chars).'});
        }

        if (low === pw && /[a-z]/.test(pw)) {
            issues.push({type:'warn', msg:'All lowercase — add uppercase, digits or symbols.'});
            suggestions.push('Mix character types to expand the pool.');
        }

        var detected = detectPools(pw);
        if (detected.length === 1) {
            issues.push({type:'warn', msg:'Only one character pool detected (' + detected[0].name + ').'});
            suggestions.push('Use a mix of lowercase, uppercase, digits, and symbols.');
        }

        if (/(.)\1{2,}/.test(pw)) {
            issues.push({type:'warn', msg:'Contains repeated characters (' + (pw.match(/(.)\1{2,}/) || [''])[0] + ').'});
            suggestions.push('Avoid repeating the same character 3+ times.');
        }

        for (var s = 0; s < SEQUENTIAL.length - 2; s++) {
            var seg = SEQUENTIAL.substr(s, 4);
            if (low.indexOf(seg) !== -1 || low.indexOf(seg.split('').reverse().join('')) !== -1) {
                issues.push({type:'warn', msg:'Contains sequential pattern (' + seg + '...).'});
                suggestions.push('Break up sequential runs with random characters.');
                break;
            }
        }

        for (var k = 0; k < KB_ROWS.length; k++) {
            var row = KB_ROWS[k];
            for (var m = 0; m <= row.length - 4; m++) {
                var kseg = row.substr(m, 4);
                if (low.indexOf(kseg) !== -1 || low.indexOf(kseg.split('').reverse().join('')) !== -1) {
                    issues.push({type:'warn', msg:'Contains keyboard pattern (' + kseg + '...).'});
                    suggestions.push('Avoid keyboard walks like qwerty or asdf.');
                    k = KB_ROWS.length;
                    m = row.length;
                    break;
                }
            }
        }

        for (var d = 0; d < DICT.length; d++) {
            if (low.indexOf(DICT[d]) !== -1 && DICT[d].length >= 4) {
                issues.push({type:'warn', msg:'Contains dictionary word fragment ("' + DICT[d] + '").'});
                suggestions.push('Avoid embedding common words in passwords.');
                break;
            }
        }

        if (/\b(19|20)\d\d\b/.test(pw)) {
            issues.push({type:'info', msg:'Contains a year pattern (e.g. 19xx/20xx).'});
            suggestions.push('Dates and years are easily guessable.');
        }

        if (/^\d+$/.test(pw)) {
            issues.push({type:'warn', msg:'Numbers only — extremely easy to crack.'});
            suggestions.push('Add letters and symbols.');
        }

        if (/^[a-zA-Z]+$/.test(pw)) {
            issues.push({type:'info', msg:'Letters only — add digits and symbols.'});
            suggestions.push('Include numbers and special characters.');
        }

        if (COMMON.indexOf(low) !== -1 || COMMON.indexOf(low.replace(/[^a-z]/g, '')) !== -1) {
            issues.unshift({type:'critical', msg:'This password is on a common password list!'});
            suggestions.unshift('Avoid well-known passwords entirely.');
        }

        var unique = {};
        for (var u = 0; u < pw.length; u++) unique[pw[u]] = 1;
        var uniqueCount = Object.keys(unique).length;
        var uniqueRatio = uniqueCount / pw.length;
        if (uniqueRatio < 0.5 && pw.length > 4) {
            issues.push({type:'info', msg:'Low character diversity — only ' + uniqueCount + ' unique characters in ' + pw.length + ' positions.'});
            suggestions.push('Use more distinct characters.');
        }

        if (issues.length === 0) {
            issues.push({type:'good', msg:'No common weak patterns detected.'});
        }

        if (suggestions.length === 0) {
            suggestions.push('This password looks solid!');
        }

        return {issues: issues, suggestions: suggestions, uniqueCount: uniqueCount, uniqueRatio: uniqueRatio};
    }

    function analyze() {
        var pw = document.getElementById('pw-input').value;
        var bits = calcEntropy(pw);
        var detected = detectPools(pw);
        var str = strengthLabel(bits);
        var pat = detectPatterns(pw);

        var bar = document.getElementById('ent-bar');
        var pct = Math.min(100, (bits / 160) * 100);
        if (pw.length === 0) pct = 0;
        bar.style.width = Math.max(pw.length > 0 ? 3 : 0, pct) + '%';
        bar.style.background = pw.length > 0 ? barColor(bits) : '#333';
        bar.textContent = pw.length > 0 ? Math.round(bits) + ' bits' : '';

        document.getElementById('ent-label').textContent = pw.length === 0 ? 'Enter a password to begin' : str.text + ' password';
        document.getElementById('ent-label').style.color = pw.length > 0 ? str.color : '';
        document.getElementById('ent-bits').textContent = pw.length > 0 ? Math.round(bits) + ' / 128+ bits' : '';

        var poolHTML = '';
        var poolNames = ['Lowercase','Uppercase','Digits','Symbols','Space','Unicode'];
        var allP = pools.concat([{name:'Unicode', pat:null, size:0, id:'unicode', code:'U+'}]);
        for (var i = 0; i < allP.length; i++) {
            var found = false;
            for (var j = 0; j < detected.length; j++) {
                if (detected[j].id === allP[i].id) { found = true; break; }
            }
            var cls = found ? 'pool-badge active' : 'pool-badge inactive';
            var sz = '';
            for (var k = 0; k < detected.length; k++) {
                if (detected[k].id === allP[i].id) { sz = ' (' + detected[k].size + ')'; break; }
            }
            poolHTML += '<span class="' + cls + '">' + allP[i].name + sz + '</span>';
        }
        document.getElementById('pool-box').innerHTML = poolHTML;

        var totalR = 0;
        for (var p = 0; p < detected.length; p++) totalR += detected[p].size;
        document.getElementById('pool-summary').textContent = pw.length > 0 ? 'Total pool: ' + totalR + ' characters → ' + pw.length + ' × log\u2082(' + totalR + ') = ' + bits.toFixed(1) + ' bits' : '';

        var rates = [
            {label:'Online (1K/sec)', rate:1000},
            {label:'Online (10K/sec)', rate:10000},
            {label:'Offline fast (10B/sec)', rate:1e10},
            {label:'Offline GPU (100B/sec)', rate:1e11},
            {label:'Nation-state (1T/sec)', rate:1e12}
        ];
        var crackHTML = '';
        for (var c = 0; c < rates.length; c++) {
            var combos = Math.pow(2, bits);
            var secs = combos / 2 / rates[c].rate;
            var t = formatTime(secs);
            var tc = barColor(bits);
            if (secs < 1) tc = '#dc3545';
            else if (secs < 3600) tc = '#e8690a';
            else if (secs < 86400 * 30) tc = '#eab308';
            else if (secs < 31557600) tc = '#6bcc5c';
            else tc = '#26d07c';
            crackHTML += '<div class="crack-row"><span class="crack-rate">' + rates[c].label + '</span><span class="crack-time" style="color:' + tc + '">' + t + '</span></div>';
        }
        if (pw.length === 0) crackHTML = '<div class="text-secondary small">Enter a password to see estimates</div>';
        document.getElementById('crack-box').innerHTML = crackHTML;

        var unique = pat.uniqueCount || 0;
        var variety = detected.length;
        var pills = '';
        pills += '<span class="detail-pill" style="background:#1a1a2e;color:#c9d1d9;">Length: ' + pw.length + '</span>';
        pills += '<span class="detail-pill" style="background:#1a1a2e;color:#c9d1d9;">Unique: ' + unique + '</span>';
        pills += '<span class="detail-pill" style="background:#1a1a2e;color:#c9d1d9;">Pools: ' + variety + '/' + pools.length + '</span>';
        pills += '<span class="detail-pill" style="background:' + str.color + '22;color:' + str.color + ';">' + str.text + '</span>';
        document.getElementById('detail-pills').innerHTML = pw.length > 0 ? pills : '';

        var issueHTML = '';
        for (var ii = 0; ii < pat.issues.length; ii++) {
            var ic = pat.issues[ii].type === 'good' ? 'alert-success' : (pat.issues[ii].type === 'critical' ? 'alert-danger' : (pat.issues[ii].type === 'warn' ? 'alert-warning' : 'alert-secondary'));
            issueHTML += '<div class="alert ' + ic + ' py-1 px-2 mb-1">' + pat.issues[ii].msg + '</div>';
        }
        if (pw.length > 0 && pat.suggestions.length > 0) {
            issueHTML += '<div class="section-title mt-3" style="font-size:.82rem;">Suggestions</div>';
            for (var sg = 0; sg < pat.suggestions.length; sg++) {
                issueHTML += '<div class="suggest-item">→ ' + pat.suggestions[sg] + '</div>';
            }
        }
        document.getElementById('detail-issues').innerHTML = issueHTML;
    }

    window.togglePw = function() {
        var inp = document.getElementById('pw-input');
        var btn = document.getElementById('pw-toggle');
        if (inp.type === 'password') { inp.type = 'text'; btn.innerHTML = '&#128064;'; }
        else { inp.type = 'password'; btn.innerHTML = '&#128065;'; }
    };

    window.copyGen = function() {
        var txt = document.getElementById('gen-output').textContent;
        if (!txt || txt.indexOf('Click') === 0) return;
        var btn = document.getElementById('gen-copy-btn');
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(txt).then(function() { btn.textContent = 'Copied!'; setTimeout(function(){ btn.textContent = 'Copy'; }, 1200); });
        } else {
            var ta = document.createElement('textarea');
            ta.value = txt; document.body.appendChild(ta); ta.select(); document.execCommand('copy'); document.body.removeChild(ta);
            btn.textContent = 'Copied!'; setTimeout(function(){ btn.textContent = 'Copy'; }, 1200);
        }
    };

    window.genPassword = function() {
        var len = parseInt(document.getElementById('gen-len').value, 10);
        var useUpper = document.getElementById('gen-upper').checked;
        var useLower = document.getElementById('gen-lower').checked;
        var useDigits = document.getElementById('gen-digits').checked;
        var useSymbols = document.getElementById('gen-symbols').checked;
        var excludeAmbig = document.getElementById('gen-ambig').checked;

        var upper = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        var lower = 'abcdefghijklmnopqrstuvwxyz';
        var digits = '0123456789';
        var symbols = '!@#$%^&*()-_=+[]{}|;:,.<>?/~`';
        var ambig = 'Il1O0';

        var pool = '';
        var required = [];
        if (useUpper) { pool += upper; required.push(upper); }
        if (useLower) { pool += lower; required.push(lower); }
        if (useDigits) { pool += digits; required.push(digits); }
        if (useSymbols) { pool += symbols; required.push(symbols); }

        if (!pool) { pool = lower + upper + digits + symbols; required = [lower, upper, digits, symbols]; }

        if (excludeAmbig) {
            var filtered = '';
            for (var i = 0; i < pool.length; i++) {
                if (ambig.indexOf(pool[i]) === -1) filtered += pool[i];
            }
            pool = filtered;
            if (!pool) pool = lower + upper + digits + symbols;
        }

        if (len < 1) len = 8;
        if (len > 128) len = 128;

        var chars = new Uint32Array(len);
        crypto.getRandomValues(chars);
        var pw = '';
        for (var j = 0; j < len; j++) pw += pool[chars[j] % pool.length];

        var pwArr = pw.split('');
        for (var r = 0; r < required.length && r < len; r++) {
            var rnd = new Uint32Array(1);
            crypto.getRandomValues(rnd);
            var pos = rnd[0] % len;
            var rndChar = new Uint32Array(1);
            crypto.getRandomValues(rndChar);
            pwArr[pos] = required[r][rndChar[0] % required[r].length];
        }
        pw = pwArr.join('');

        document.getElementById('gen-output').textContent = pw;
        document.getElementById('pw-input').value = pw;
        document.getElementById('pw-input').type = 'text';
        document.getElementById('pw-toggle').innerHTML = '&#128064;';

        var ent = calcEntropy(pw);
        var sLabel = strengthLabel(ent);
        document.getElementById('gen-entropy').innerHTML = 'Entropy: <strong style="color:' + sLabel.color + '">' + ent.toFixed(1) + ' bits</strong> — ' + sLabel.text;

        analyze();
    };

    analyze();
})();
</script>
<?php page_footer(); ?>
