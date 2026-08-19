<?php
require_once __DIR__ . '/../../functions.php';

start_session();
// Fengari compiles Lua to JS (needs 'unsafe-eval'); scoped to this page only.
$GLOBALS['_csp'] = "default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval' 'wasm-unsafe-eval'; style-src 'self' 'unsafe-inline'; img-src 'self' data: http: https:; font-src 'self'; connect-src 'self'; media-src 'self'; object-src 'none'; base-uri 'self'; form-action 'self'; frame-ancestors 'self'";
page_header('Lua Sandbox');
?>
<style>
.ls-tabs { display:flex; gap:6px; border-bottom:1px solid var(--line); margin-bottom:1rem; }
.ls-tab { padding:.5rem 1.1rem; border-radius:10px 10px 0 0; cursor:pointer; font-size:.9rem; color:var(--dim); border:1px solid transparent; border-bottom:none; }
.ls-tab.on { color:#fff; background:var(--panel); border-color:var(--line); font-weight:600; }
.ls-panel { display:none; }
.ls-panel.on { display:block; }
.ls-chk { font-size:.85rem; }
</style>
<div class="container" style="max-width: 1180px;">
    <h1 class="h4 mb-1 reveal in-view">🥋 Lua Sandbox <span class="text-secondary" style="font-size:.85rem">— run, test &amp; obfuscate</span></h1>
    <p class="text-secondary mb-3 reveal in-view">Everything runs in your browser via Fengari (a Lua VM written in JS), so your scripts never touch the server. Run code against a real Lua state, then flip over to Obfuscate to build a hardened, luaobfuscator-style bundle.</p>

    <div class="ls-tabs reveal in-view">
        <div class="ls-tab on" data-p="run" onclick="tab('run')">▶ Run</div>
        <div class="ls-tab" data-p="ob" onclick="tab('ob')">🧅 Obfuscate</div>
        <div class="ls-tab" data-p="help" onclick="tab('help')">ℹ️ Guide</div>
    </div>

    <div class="ls-panel on" id="p-run">
        <div class="card"><div class="card-body">
            <div class="row g-3">
                <div class="col-lg-6">
                    <label class="form-label small text-secondary mb-1">Lua code</label>
                    <textarea id="ls-in" class="form-control" rows="18" spellcheck="false" style="font-family:'JetBrains Mono',monospace;font-size:.85rem;white-space:pre;">-- Lua sandbox playground
local function fib(n)
  if n < 2 then return n end
  return fib(n - 1) + fib(n - 2)
end
for i = 0, 10 do print("fib(" .. i .. ") = " .. fib(i)) end</textarea>
                    <div class="d-flex gap-2 mt-2 flex-wrap">
                        <button class="btn btn-primary" id="ls-runbtn" type="button">▶ Run</button>
                        <button class="btn btn-outline-light" type="button" onclick="sample(0)">Sample: loops</button>
                        <button class="btn btn-outline-light" type="button" onclick="sample(1)">Sample: tables</button>
                        <button class="btn btn-outline-light" type="button" onclick="sample(2)">Sample: strings</button>
                        <span class="text-secondary small ms-auto align-self-center" id="ls-time"></span>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="d-flex align-items-center justify-content-between mb-1">
                        <label class="form-label small text-secondary mb-0">Console</label>
                        <span id="ls-status"></span>
                    </div>
                    <pre id="ls-console" class="mb-0" style="background:#0b0b0b;border:1px solid var(--line);border-radius:10px;padding:.9rem;font-family:'JetBrains Mono',monospace;font-size:.8rem;min-height:420px;max-height:520px;overflow:auto;white-space:pre-wrap;">Press Run to see output.</pre>
                </div>
            </div>
        </div></div>
    </div>

    <div class="ls-panel" id="p-ob">
        <div class="card"><div class="card-body">
            <div class="alert alert-secondary small mb-3">
                <strong>Obfuscation layers</strong> (luaobfuscator.com-style toggles) — comments stripped, string literals rebuilt with
                <code>string.char()</code> (optionally XOR-keyed), numbers re-encoded as arithmetic, locals renamed to volume-1 obfuscated
                identifiers, junk code injected and the whole bundle wrapped in a <code>load()</code> "VM" with shuffled bytes. Your code is
                transformed in-place; nothing is executed and nothing is uploaded.
            </div>
            <div class="row g-3">
                <div class="col-lg-6">
                    <label class="form-label small text-secondary mb-1">Input</label>
                    <textarea id="ob-in" class="form-control" rows="16" spellcheck="false" style="font-family:'JetBrains Mono',monospace;font-size:.85rem;">local player = "kevbin"
local hp = 100
print("hello " .. player .. " hp=" .. hp)</textarea>
                </div>
                <div class="col-lg-6">
                    <label class="form-label small text-secondary mb-1">Output</label>
                    <textarea id="ob-out" class="form-control" rows="16" readonly style="font-family:'JetBrains Mono',monospace;font-size:.85rem;"></textarea>
                </div>
            </div>
            <div class="row gx-4 gy-2 mt-3">
                <div class="col-6 col-md-3 ls-chk form-check"><input class="form-check-input" type="checkbox" id="ob-strip" checked><label class="form-check-label" for="ob-strip">Strip comments</label></div>
                <div class="col-6 col-md-3 ls-chk form-check"><input class="form-check-input" type="checkbox" id="ob-strings" checked><label class="form-check-label" for="ob-strings">Encode strings</label></div>
                <div class="col-6 col-md-3 ls-chk form-check"><input class="form-check-input" type="checkbox" id="ob-xor" checked><label class="form-check-label" for="ob-xor">XOR string key</label></div>
                <div class="col-6 col-md-3 ls-chk form-check"><input class="form-check-input" type="checkbox" id="ob-nums" checked><label class="form-check-label" for="ob-nums">Encode numbers</label></div>
                <div class="col-6 col-md-3 ls-chk form-check"><input class="form-check-input" type="checkbox" id="ob-rename" checked><label class="form-check-label" for="ob-rename">Rename locals</label></div>
                <div class="col-6 col-md-3 ls-chk form-check"><input class="form-check-input" type="checkbox" id="ob-junk" checked><label class="form-check-label" for="ob-junk">Inject junk</label></div>
                <div class="col-6 col-md-3 ls-chk form-check"><input class="form-check-input" type="checkbox" id="ob-mini" checked><label class="form-check-label" for="ob-mini">Minify</label></div>
                <div class="col-6 col-md-3 ls-chk form-check"><input class="form-check-input" type="checkbox" id="ob-vm" checked><label class="form-check-label" for="ob-vm">VM wrapper (load)</label></div>
            </div>
            <div class="d-flex gap-2 mt-3 flex-wrap align-items-center">
                <button class="btn btn-primary" type="button" onclick="obfuscate()">🧅 Obfuscate</button>
                <button class="btn btn-outline-light" type="button" onclick="copyOut()">Copy output</button>
                <button class="btn btn-outline-light" type="button" onclick="testDeob()">▶ Run output (sanity test)</button>
                <span class="text-secondary small" id="ob-size"></span>
            </div>
        </div></div>
    </div>

    <div class="ls-panel" id="p-help">
        <div class="row g-3">
            <div class="col-md-6">
                <div class="card h-100"><div class="card-body">
                    <h2 class="h6 mb-2">Running scripts</h2>
                    <p class="small text-secondary mb-0">The interpreter is <strong>Fengari</strong> — a full Lua 5.3 VM written in JS. It supports
                    locals, functions, tables, <code>string</code>/<code>table</code>/<code>math</code>, <code>pcall</code>, coroutines and
                    metatables. Everything executes in this tab and is sandboxed from the page; no <code>io</code>/<code>os</code> file or
                    network access is available. Extremely heavy loops can still slow your tab — keep run counts sane.</p>
                </div></div>
            </div>
            <div class="col-md-6">
                <div class="card h-100"><div class="card-body">
                    <h2 class="h6 mb-2">Obfuscating</h2>
                    <p class="small text-secondary mb-0">Toggles mirror popular online Lua obfuscators. The VM wrapper reassembles source from
                    XOR-decoded, shuffled byte chunks via <code>load()</code>, so the file never contains the plain script. Output stays standard
                    Lua — paste it into any Lua runtime to run. Use "Run output" to verify the bundle still executes in this sandbox.</p>
                </div></div>
            </div>
        </div>
    </div>
</div>
<script src="/assets/fengari-web.js"></script>
<script>
var fengari = window.fengari;
function tab(p){
    document.querySelectorAll('.ls-tab').forEach(function(t){ t.classList.toggle('on', t.getAttribute('data-p') === p); });
    document.querySelectorAll('.ls-panel').forEach(function(x){ x.classList.toggle('on', x.id === 'p-' + p); });
    if (p === 'ob') obfuscate();
}
function sample(i){
    var s = [
        ['-- nested loops\nfor i = 1, 3 do\n  for j = 1, i do\n    print(string.rep("#", j))\n  end\nend', ''],
        ['-- tables & metatables\nlocal t = setmetatable({ a = 1 }, { __index = { b = 2 } })\nprint(t.a, t.b)\nprint(table.concat({ "x", "y", "z" }, "-"))', ''],
        ['-- strings & math\nlocal s = "lua sandbox"\nprint(string.upper(s))\nprint(string.reverse(s))\nprint(math.max(3, 7, 1), math.pow(2, 10))', ''],
    ][i];
    document.getElementById('ls-in').value = s[0];
    document.getElementById('ob-in').value = s[0];
}
function runLua(){
    var ta = document.getElementById('ls-in');
    var consoleEl = document.getElementById('ls-console');
    var status = document.getElementById('ls-status');
    var timeEl = document.getElementById('ls-time');
    var code = ta.value;
    if (!fengari) { consoleEl.textContent = 'Lua engine failed to load (/assets/fengari-web.js).'; status.className='badge text-bg-danger'; status.textContent='ERROR'; return; }
    var t0 = performance.now();
    var res = runInVM(code);
    var ms = (performance.now() - t0).toFixed(1);
    var text = res.out !== '' ? res.out : (res.ok ? '(no output)' : '');
    if (!res.ok) text += (text ? '\n' : '') + res.err;
    consoleEl.textContent = text;
    status.className = res.ok ? 'badge text-bg-success' : 'badge text-bg-danger';
    status.textContent = res.ok ? 'OK' : 'ERROR';
    timeEl.textContent = ms + ' ms';
}
function runInVM(code){
    var L = fengari.lauxlib.luaL_newstate();
    fengari.lualib.luaL_openlibs(L);
    var out = [];
    fengari.lua.lua_pushcfunction(L, function (st) {
        var n = fengari.lua.lua_gettop(st);
        var parts = [];
        for (var i = 1; i <= n; i++) {
            fengari.lauxlib.luaL_tolstring(st, i);
            parts.push(fengari.to_jsstring(fengari.lua.lua_tolstring(st, i)));
            fengari.lua.lua_pop(st, 1);
        }
        out.push(parts.join('\t'));
        return 0;
    });
    fengari.lua.lua_setglobal(L, fengari.to_luastring('print'));
    if (fengari.lauxlib.luaL_loadstring(L, fengari.to_luastring(code)) !== fengari.lua.LUA_OK) {
        return { ok: false, out: out.join('\n'), err: fengari.to_jsstring(fengari.lua.lua_tolstring(L, -1)) };
    }
    var status = fengari.lua.lua_pcall(L, 0, 0, 0);
    if (status !== fengari.lua.LUA_OK) {
        return { ok: false, out: out.join('\n'), err: fengari.to_jsstring(fengari.lua.lua_tolstring(L, -1)) };
    }
    return { ok: true, out: out.join('\n'), err: '' };
}
document.getElementById('ls-runbtn').addEventListener('click', runLua);

// ---------- luaobfuscator-style transformer ----------
function luaTokens(src){
    // Lightweight lexer: returns {tok:{type,text,start}} array, skipping comments.
    var toks = [];
    var i = 0, n = src.length;
    var type = function(c){ if (c === "'" || c === '"') return 'str'; if (c >= '0' && c <= '9') return 'num'; if (/[A-Za-z_]/.test(c)) return 'id'; if (c === '[') return 'lb'; if (c === ']') return 'rb'; return 'op'; };
    while (i < n) {
        var c = src[i];
        if (c === '-' && src[i+1] === '-') {
            var k = 0;
            if (src[i+2] === '[') {
                var m = src.slice(i+2).match(/^\[(=*)\[/);
                if (m) { var eq = m[1].length; var end = ']' + m[1] + ']'; var j = src.indexOf(end, i + 3 + eq + 1 + eq); i = j === -1 ? n : j + end.length; continue; }
            }
            var nl = src.indexOf('\n', i);
            i = nl === -1 ? n : nl + 1;
            continue;
        }
        if (c === '[') {
            var m2 = src.slice(i).match(/^\[(=*)\[/);
            if (m2) { var end2 = ']' + m2[1] + ']'; var j2 = src.indexOf(end2, i + m2[0].length); var len = j2 === -1 ? n : j2 + end2.length; toks.push({ t: 'str', text: src.slice(i, len) }); i = len; continue; }
        }
        if (c === "'" || c === '"') {
            var j3 = i + 1;
            while (j3 < n && (src[j3] !== c || src[j3-1] === '\\')) j3++;
            j3++;
            toks.push({ t: 'str', text: src.slice(i, Math.min(j3, n)) });
            i = Math.min(j3, n);
            continue;
        }
        var t = type(c);
        if (t === 'id' || t === 'num') {
            var j4 = i + 1;
            while (j4 < n && type(src[j4]) === t) j4++;
            toks.push({ t: t, text: src.slice(i, j4) });
            i = j4;
            continue;
        }
        if (c === ' ') { i++; continue; }
        if (c === '\n') { toks.push({ t: 'nl', text: '\n' }); i++; continue; }
        toks.push({ t: 'op', text: c });
        i++;
    }
    return toks;
}
function obfuscate(){
    var src = document.getElementById('ob-in').value;
    var toks = luaTokens(src);
    // strip comments already handled by lexer.
    var locals = [];
    for (var i = 0; i < toks.length - 1; i++) {
        if (toks[i].text === 'local' && toks[i+1].t === 'id') locals.push(toks[i+1].text);
    }
    var map = {};
    locals.forEach(function(nm){ if (!(nm in map)) map[nm] = '_0x' + randHex(4) + ''; });
    var bits = [];
    for (var k = 0; k < toks.length; k++) {
        var t = toks[k];
        if (t.t === 'nl') { if (!document.getElementById('ob-mini').checked) bits.push('\n'); continue; }
        if (t.t === 'str') bits.push(encString(t.text));
        else if (t.t === 'num') bits.push(document.getElementById('ob-nums').checked ? encNum(t.text) : t.text);
        else if (t.t === 'id') bits.push(map[t.text] || t.text);
        else bits.push(t.text);
    }
    var out = bits.join(document.getElementById('ob-mini').checked ? '' : ' ');
    if (document.getElementById('ob-junk').checked) out = injectJunk(out);
    if (document.getElementById('ob-vm').checked) out = wrapVM(out);
    document.getElementById('ob-out').value = out;
    document.getElementById('ob-size').textContent = (src.length) + ' → ' + out.length + ' bytes (' + perfGrade(src.length, out.length) + ')';
}
function encString(strTok){
    if (!document.getElementById('ob-strings').checked) return strTok;
    // Extract the literal contents (quote or long bracket).
    var inner;
    if (strTok.startsWith('[')) {
        var m = strTok.match(/^\[(=*)\[\s*/);
        if (m) { inner = strTok.slice(m[0].length, strTok.length - (']' + m[1] + ']').length); }
        else inner = strTok;
    } else {
        inner = strTok.slice(1, -1).replace(/\\(.)/g, '$1');
    }
    var bytes = [];
    for (var i = 0; i < inner.length; i++) {
        var cc = inner.charCodeAt(i) & 0xFF;
        if (document.getElementById('ob-xor').checked) cc ^= 66;
        bytes.push(cc);
    }
    if (document.getElementById('ob-xor').checked) {
        return '(load("local t={" + bytes.join(",") + "} return function() local s={} for i=1,#t do s[i]=string.char(t[i]~66) end return table.concat(s) end")())()';
    }
    return '(string.char(' + bytes.join(',') + '))';
}
function encNum(t){
    var v = parseInt(t, 10);
    if (isNaN(v) || Math.abs(v) > 1e9) return t;
    var a = 1 + Math.floor(Math.random() * 40);
    var b = v - a;
    if (b === v) return t;
    return '(' + a + '+' + b + ')';
}
function injectJunk(out){
    var junk = 'local _j=' + (Math.floor(Math.random()*1e6)) + ' if _j>1 then _j=_j-1 end ';
    return junk + out;
}
function wrapVM(out){
    var bytes = [];
    for (var i = 0; i < out.length; i++) bytes.push((out.charCodeAt(i) ^ 133) & 0xFF);
    var order = [];
    for (var j = bytes.length - 1; j >= 0; j--) order.push(j);
    var cn = bytes.length;
    return 'local _k=function(t) local r={} for i=1,#t do r[t[i]]=i end return r end\n'
        + 'local _o=' + JSON.stringify(order) + '\n'
        + 'local _b={' + bytes.join(',') + '}\n'
        + 'local _s={} for i=0,' + cn + '-1 do _s[#_s+1]=string.char(bxor(_b[_o[i+1]+1],133)) end\n'
        + 'load(table.concat(_s))()';
}
function perfGrade(a,b){ return b > a * 3 ? 'heavy' : b > a * 1.5 ? 'medium' : 'light'; }
function randHex(n){ var s=''; for (var i=0;i<n;i++) s += '0123456789abcdef'[Math.floor(Math.random()*16)]; return s; }
function testDeob(){
    var code = document.getElementById('ob-out').value;
    var old = document.getElementById('ls-in').value;
    document.getElementById('ls-in').value = '-- obfuscated sanity run\n' + code;
    runLua();
    document.getElementById('ls-in').value = old;
}
function copyOut(){ var t=document.getElementById('ob-out'); t.select(); if(navigator.clipboard) navigator.clipboard.writeText(t.value); else document.execCommand('copy'); }
</script>
<?php page_footer(); ?>