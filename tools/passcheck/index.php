<?php
require_once __DIR__ . '/../../functions.php';

start_session();
page_header('Password Strength');
?>
<div class="container" style="max-width: 900px;">
    <h1 class="h4 mb-1 reveal in-view">🛡️ Password Strength Checker</h1>
    <p class="text-secondary mb-4 reveal in-view">Entropy, pattern checks, a small list of known-awful passwords and a rough "time to crack" guess. Everything runs locally — the password never leaves your browser.</p>

    <div class="card mb-4 reveal in-view"><div class="card-body">
        <div class="row g-2 align-items-center mb-3">
            <div class="col-md-8">
                <input id="pw" class="form-control form-control-lg" type="password" placeholder="Type a password…" oninput="check()" style="font-family:'JetBrains Mono',monospace;">
            </div>
            <div class="col-md-4 d-flex gap-2 align-items-center">
                <button class="btn btn-outline-light" onclick="toggle()">👁</button>
                <button class="btn btn-outline-light" onclick="gen()">🎲 Generate strong</button>
            </div>
        </div>
        <div class="progress mb-2" style="height:14px;">
            <div id="bar" class="progress-bar" role="progressbar" style="width:0%;background:#5865f2;"></div>
        </div>
        <div class="d-flex justify-content-between align-items-center mb-3">
            <span id="score-label" class="text-secondary small">Waiting…</span>
            <span id="crack" class="text-secondary small"></span>
        </div>
        <div id="issues"></div>
    </div></div>

    <div class="card reveal in-view"><div class="card-body">
        <h2 class="h6 mb-2">How it's judged</h2>
        <ul class="text-secondary small mb-0 ps-3">
            <li><strong>Entropy</strong> = length × log₂(charset size). More random ≠ more complex-looking.</li>
            <li><strong>Charsets</strong>: lower, upper, digits, symbols. Mixed charsets raise the bit count.</li>
            <li><strong>Patterns</strong> — run detection (aaaa, 12345), dictionary words, dates and keyboard rows (qwerty) lower the score.</li>
            <li><strong>Time to crack</strong> assumes 10⁹ guesses/sec offline GPU attack — real-world numbers vary wildly.</li>
        </ul>
    </div></div>
</div>

<script>
var common = ['password','123456','12345678','qwerty','abc123','monkey','dragon','111111','letmein','trustno1','admin','welcome','iloveyou','sunshine','princess','football','baseball','superman','batman','dragon','shadow','master','666666','123123','password1','qwerty123','michael','jennifer','12345','1234567','123456789','1234567890','p@ssw0rd','hunter2'];
function entropy(pw) {
    if (!pw) return 0;
    var sets = 0;
    if (/[a-z]/.test(pw)) sets++;
    if (/[A-Z]/.test(pw)) sets++;
    if (/[0-9]/.test(pw)) sets++;
    if (/[^a-zA-Z0-9]/.test(pw)) sets++;
    var pool = [26, 26, 10, 33];
    var size = 0;
    for (var i = 0; i < 4; i++) if (sets > i) size += pool[i];
    if (sets === 1 && /^[0-9]+$/.test(pw)) size = 10;
    return Math.max(2 * pw.length * Math.log2(size), pw.length * Math.log2(size));
}
function check() {
    var pw = document.getElementById('pw').value;
    var low = pw.toLowerCase();
    var bits = entropy(pw);
    var issues = [];
    var score = bits;

    if (pw.length < 8) issues.push('Too short — under 8 characters.');
    if (pw.length >= 20) issues.push('Long — this alone is a good sign.');
    if (low === pw) issues.push('All lowercase.');
    if (pw.length > 0 && /^[a-z0-9]+$/i.test(pw) && !/[A-Z]/.test(pw) && !/[^a-zA-Z0-9]/.test(pw)) issues.push('Add a symbol — digits+letters alone guess fast.');
    if (/(.)\1{2,}/.test(pw)) { score -= 20; issues.push('Repeated character run.'); }
    if (/(?:0123456789|1234567890|987654321|abcdefg|qwerty|asdfgh|zxcvbn|iloveyou)/.test(low)) { score -= 30; issues.push('Keyboard row or "abc…" pattern.'); }
    if (/^\d+$/.test(pw)) { score -= 30; issues.push('Numbers only.'); }
    if (/(19|20)\d\d/.test(pw)) { score -= 20; issues.push('Contains a year.'); }
    if (common.indexOf(low) !== -1) { score = 0; issues.push('This password is on a well-known "worst passwords" list.'); }
    var words = ['password','qwerty','admin','love','name','hello','secret','dragon','monkey','football','welcome'];
    for (var i = 0; i < words.length; i++) {
        if (low.indexOf(words[i]) !== -1) { score -= 15; issues.push('Contains a dictionary word ("' + words[i] + '").'); }
        if (score < 10) break;
    }

    score = Math.max(0, score);
    var scoreFrac = Math.min(1, score / 128);
    var bar = document.getElementById('bar');
    bar.style.width = Math.round(scoreFrac * 100) + '%';
    bar.style.background = score < 35 ? '#dc3545' : (score < 70 ? '#ffc107' : '#26d07c');

    var label;
    if (pw === '') label = 'Waiting…';
    else if (score < 35) label = 'Weak — people like this are why hack lists exist.';
    else if (score < 70) label = 'Okay. Better than most, worse than you think.';
    else if (score < 110) label = 'Strong.';
    else label = 'Bunker-grade.';
    document.getElementById('score-label').textContent = label + '  (' + Math.round(score) + ' points, ~' + Math.round(bits) + ' bits entropy)';

    if (!pw) { document.getElementById('crack').textContent = ''; document.getElementById('issues').innerHTML = ''; return; }
    var guessesPerSec = 1e9;
    var seconds = Math.pow(2, bits) / 2 / guessesPerSec;
    var crack = seconds < 1 ? 'cracked instantly' : (seconds < 60 ? Math.round(seconds) + ' seconds' : (seconds < 3600 ? Math.round(seconds / 60) + ' minutes' : (seconds < 86400 ? Math.round(seconds / 3600) + ' hours' : (seconds < 31557600 ? Math.round(seconds / 86400) + ' days' : Math.round(seconds / 31557600) + ' years'))));
    document.getElementById('crack').textContent = '⏱ offline GPU: ' + crack;
    document.getElementById('issues').innerHTML = issues.length
        ? issues.map(function (i) { return '<div class="alert alert-secondary py-1 px-2 small mb-1">' + i + '</div>'; }).join('')
        : '<div class="alert alert-success py-1 px-2 small mb-0">No red flags.</div>';
}
function toggle() { var t = document.getElementById('pw'); t.type = t.type === 'password' ? 'text' : 'password'; }
function gen() {
    var chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz23456789!@#$%^&*?';
    var a = new Uint32Array(16);
    crypto.getRandomValues(a);
    var out = '';
    for (var i = 0; i < 20; i++) out += chars[a[i] % chars.length];
    document.getElementById('pw').value = out;
    check();
}
</script>
<?php page_footer(); ?>