<?php
require_once __DIR__ . '/../../functions.php';

start_session();
page_header('Random Generator');
?>
<div class="container" style="max-width: 820px;">
    <h1 class="h4 mb-1 reveal in-view">Random Generator</h1>
    <p class="text-secondary mb-4 reveal in-view">Coin flips, dice, numbers, strings, choices and lottery picks — generated locally with the browser's crypto RNG.</p>

    <div class="alert alert-secondary reveal in-view">
        <strong>Fair randomness:</strong> results are pulled from <code>crypto.getRandomValues()</code>, the same OS-level source used for security keys, so no seed can make them predictable and no pattern repeats. If you need genuine cryptographically random output (passwords, tokens), the <a class="text-info" href="../uid/">UID Generator</a> is the better stop.
    </div>

    <div class="row g-4">

        <div class="col-md-6 reveal in-view"><div class="card h-100"><div class="card-body">
            <h3 class="h6 mb-3">🪙 Coin & Dice</h3>
            <div class="d-flex flex-wrap gap-2 mb-3">
                <button class="btn btn-outline-light btn-sm" onclick="flip()">Flip coin</button>
                <button class="btn btn-outline-light btn-sm" onclick="rollDice(4)">d4</button>
                <button class="btn btn-outline-light btn-sm" onclick="rollDice(6)">d6</button>
                <button class="btn btn-outline-light btn-sm" onclick="rollDice(8)">d8</button>
                <button class="btn btn-outline-light btn-sm" onclick="rollDice(12)">d12</button>
                <button class="btn btn-outline-light btn-sm" onclick="rollDice(20)">d20</button>
            </div>
            <div class="display-5" id="coin-dice">&nbsp;</div>
            <div class="text-secondary small mt-1" id="coin-dice-info"></div>
        </div></div></div>

        <div class="col-md-6 reveal in-view"><div class="card h-100"><div class="card-body">
            <h3 class="h6 mb-3">🔢 Random number</h3>
            <div class="row g-2">
                <div class="col-5"><input id="rmin" type="number" class="form-control" value="1"></div>
                <div class="col-2 text-center text-secondary pt-2">to</div>
                <div class="col-5"><input id="rmax" type="number" class="form-control" value="100"></div>
            </div>
            <button class="btn btn-primary btn-sm mt-2" onclick="genNum()">Generate</button>
            <div class="mt-3 output-xl" id="num-out">&nbsp;</div>
        </div></div></div>

        <div class="col-md-6 reveal"><div class="card h-100"><div class="card-body">
            <h3 class="h6 mb-3">🔤 Random string</h3>
            <div class="row g-2">
                <div class="col-7">
                    <select id="rcharset" class="form-select">
                        <option value="hex">Hexadecimal (0-9 a-f)</option>
                        <option value="alnum" selected>Letters + digits</option>
                        <option value="lower">Lowercase letters</option>
                        <option value="upper">Uppercase letters</option>
                        <option value="symbols">Alphanumeric + symbols</option>
                    </select>
                </div>
                <div class="col-5"><input id="rlen" type="number" class="form-control" value="12" min="1" max="256"></div>
            </div>
            <button class="btn btn-primary btn-sm mt-2" onclick="genString()">Generate</button>
            <div class="mt-3 output-xl break-all" id="str-out">&nbsp;</div>
        </div></div></div>

        <div class="col-md-6 reveal"><div class="card h-100"><div class="card-body">
            <h3 class="h6 mb-3">🎯 Choice & lottery</h3>
            <label class="form-label small">Options (one per line)</label>
            <textarea id="rlist" class="form-control" rows="3" style="font-family:'JetBrains Mono',monospace;font-size:.85rem;" placeholder="rocks&#10;paper&#10;scissors">rocks
paper
scissors</textarea>
            <div class="mt-2 d-flex gap-2">
                <button class="btn btn-primary btn-sm" onclick="pickOne()">Pick one</button>
                <button class="btn btn-outline-light btn-sm" onclick="lottery(6, 49)">Lottery 6/49</button>
            </div>
            <div class="mt-3 output-xl" id="choice-out">&nbsp;</div>
        </div></div></div>

        <div class="col-md-6 reveal"><div class="card h-100"><div class="card-body">
            <h3 class="h6 mb-3">📅 Random date</h3>
            <div class="row g-2">
                <div class="col-5"><input id="rdate1" type="date" class="form-control"></div>
                <div class="col-2 text-center text-secondary pt-2">to</div>
                <div class="col-5"><input id="rdate2" type="date" class="form-control"></div>
            </div>
            <button class="btn btn-primary btn-sm mt-2" onclick="genDate()">Generate</button>
            <div class="mt-3 output-xl" id="date-out">&nbsp;</div>
        </div></div></div>

        <div class="col-md-6 reveal"><div class="card h-100"><div class="card-body">
            <h3 class="h6 mb-3">🎨 Random color</h3>
            <div class="d-flex gap-2 align-items-center">
                <input id="color-out" class="form-control" style="max-width:160px;font-family:'JetBrains Mono',monospace;" value="#5865f2" readonly>
                <button class="btn btn-primary btn-sm" onclick="genColor()">Generate</button>
                <button class="btn btn-outline-light btn-sm" onclick="copyVal('color-out')">Copy</button>
            </div>
            <div class="form-text mt-2">Any of 16.7M colors — handy for test palettes.</div>
            <div class="mt-3 output-xl" id="color-swatch" style="height:3rem;border-radius:10px;border:1px solid var(--line);background:#5865f2;"></div>
        </div></div></div>

        <div class="col-md-6 reveal"><div class="card h-100"><div class="card-body">
            <h3 class="h6 mb-3">🌐 Random IPv4</h3>
            <button class="btn btn-primary btn-sm" onclick="genIp()">Generate</button>
            <div class="mt-3 output-xl" id="ip-out">&nbsp;</div>
            <div class="form-text mt-1">Public-address range only — no private/reserved blocks.</div>
        </div></div></div>

    </div>
</div>

<style>
.output-xl { font-family:'JetBrains Mono',monospace; font-size:1.5rem; min-height:2rem; }
.break-all { word-break: break-all; }
</style>

<script>
function ri(max) {
    // uniform int in [0, max)
    var x = new Uint32Array(1);
    crypto.getRandomValues(x);
    return x[0] % max;
}
function flip() {
    var r = ri(2);
    document.getElementById('coin-dice').textContent = r ? '🎲... heads' : '🪙... tails';
    document.getElementById('coin-dice-info').textContent = 'fair 50/50';
}
function rollDice(sides) {
    var r = ri(sides) + 1;
    document.getElementById('coin-dice').textContent = '⚀⚁⚂⚃⚄⚅' + '  ' + r + ' / ' + sides;
    document.getElementById('coin-dice-info').textContent = 'fair d' + sides + ' roll';
}
function genNum() {
    var lo = parseInt(document.getElementById('rmin').value, 10);
    var hi = parseInt(document.getElementById('rmax').value, 10);
    if (isNaN(lo) || isNaN(hi) || hi < lo) { document.getElementById('num-out').textContent = 'set min ≤ max'; return; }
    var span = hi - lo + 1;
    var r = (span <= 0x7fffffff) ? lo + ri(span) : lo + Math.floor(Math.random() * span);
    document.getElementById('num-out').textContent = r;
}
function genString() {
    var sets = {
        hex: '0123456789abcdef',
        alnum: 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789',
        lower: 'abcdefghijklmnopqrstuvwxyz',
        upper: 'ABCDEFGHIJKLMNOPQRSTUVWXYZ',
        symbols: 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*()-_=+[]{}'
    };
    var cs = sets[document.getElementById('rcharset').value] || sets.alnum;
    var n = parseInt(document.getElementById('rlen').value, 10);
    if (isNaN(n) || n < 1) n = 1;
    if (n > 256) n = 256;
    var s = '';
    var buf = new Uint32Array(n);
    crypto.getRandomValues(buf);
    for (var i = 0; i < n; i++) s += cs[buf[i] % cs.length];
    document.getElementById('str-out').textContent = s;
}
function pickOne() {
    var lines = document.getElementById('rlist').value.split(/\n+/).map(function (s) { return s.trim(); }).filter(Boolean);
    if (!lines.length) { document.getElementById('choice-out').textContent = 'add some options first'; return; }
    document.getElementById('choice-out').textContent = lines[ri(lines.length)];
}
function lottery(count, max) {
    var pool = [];
    for (var i = 1; i <= max; i++) pool.push(i);
    for (var i = pool.length - 1; i > 0; i--) {
        var j = Math.floor(Math.random() * (i + 1));
        var t = pool[i]; pool[i] = pool[j]; pool[j] = t;
    }
    document.getElementById('choice-out').textContent = pool.slice(0, count).sort(function (a, b) { return a - b; }).join('  ');
}
function genDate() {
    var d1 = document.getElementById('rdate1').value || '2000-01-01';
    var d2 = document.getElementById('rdate2').value || new Date().toISOString().slice(0, 10);
    var t1 = Date.parse(d1), t2 = Date.parse(d2);
    if (isNaN(t1) || isNaN(t2) || t2 < t1) { document.getElementById('date-out').textContent = 'set start ≤ end'; return; }
    var span = t2 - t1;
    var r = t1 + Math.floor(Math.random() * (span + 86400000));
    document.getElementById('date-out').textContent = new Date(r).toISOString().slice(0, 10);
}
function genColor() {
    var b = ri(0x1000000);
    var hex = '#' + ('00000' + b.toString(16)).slice(-6);
    document.getElementById('color-out').value = hex;
    document.getElementById('color-swatch').style.background = hex;
}
function genIp() {
    var a = [0, 0, 0, 0];
    for (var i = 0; i < 4; i++) a[i] = ri(256);
    if (a[0] === 127 || a[0] === 0 || a[0] === 169 || a[0] >= 240) a[0] = ri(200) + 10;
    if (a[0] === 10) a[0] = 11;
    if (a[0] === 100 && a[1] === 64) a[1] = 65;
    if (a[0] === 192 && a[1] === 168) a[1] = 169;
    if (a[0] === 172 && a[1] >= 16 && a[1] <= 31) a[1] = 32;
    document.getElementById('ip-out').textContent = a.join('.');
}
function copyVal(id) {
    var el = document.getElementById(id);
    el.select();
    if (navigator.clipboard) navigator.clipboard.writeText(el.value); else document.execCommand('copy');
}
(function () {
    var n = new Date();
    document.getElementById('rdate1').value = '2000-01-01';
    document.getElementById('rdate2').value = n.toISOString().slice(0, 10);
})();
</script>
<?php page_footer(); ?>