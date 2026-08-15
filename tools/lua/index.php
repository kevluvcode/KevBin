<?php
require_once __DIR__ . '/../../functions.php';

start_session();
page_header('Lua Runner');
?>
<div class="container" style="max-width: 900px;">
    <h1 class="h4 mb-1 reveal in-view">🐚 Lua Runner</h1>
    <p class="text-secondary mb-3 reveal in-view">Execute Lua right in your browser, no server round-trip. The Lua interpreter (Fengari, a Lua VM written in JavaScript) runs your code locally — installed on this page, so nothing is sent anywhere. Prints land in the console below, errors show the line.</p>

    <div class="card mb-3 reveal in-view"><div class="card-body">
        <label class="form-label" for="lua-code">Lua code</label>
        <textarea id="lua-code" class="form-control" rows="14" maxlength="20000" spellcheck="false"
            style="font-family:'JetBrains Mono',monospace;font-size:.85rem;white-space:pre;">print("hio")</textarea>
        <div class="d-flex gap-2 mt-2 flex-wrap align-items-center">
            <button class="btn btn-primary" type="button" onclick="runLua()">▶ Run</button>
            <button type="button" class="btn btn-outline-light" onclick="loadSample(0)">Sample: loop</button>
            <button type="button" class="btn btn-outline-light" onclick="loadSample(1)">Sample: table</button>
            <button type="button" class="btn btn-outline-light" onclick="loadSample(2)">Sample: strings</button>
            <span id="lua-time" class="text-secondary small ms-auto"></span>
        </div>
    </div></div>

    <div class="card reveal"><div class="card-body">
        <div class="d-flex align-items-center justify-content-between mb-2">
            <h2 class="h6 mb-0">Console</h2>
            <span id="lua-status"></span>
        </div>
        <pre id="lua-console" class="mb-0" style="background:#0b0b0b;border:1px solid var(--line);border-radius:10px;padding:.9rem;font-family:'JetBrains Mono',monospace;font-size:.8rem;min-height:140px;max-height:420px;overflow:auto;white-space:pre-wrap;">Run some code to see the output here.</pre>
        <p class="text-secondary small mt-2 mb-0">Supports: variables, if/while/for, functions, tables, print, string &amp; math helpers (math.floor, string.upper...). Runs fully in your browser — no files, no network. Pairs/ipairs, goto and OOP are not supported by the tiny interpreter.</p>
    </div></div>
</div>
<script src="<?= e(url('assets/fengari-web.js')) ?>"></script>
<script>
var fengari = window.fengari;

function runLua() {
    var ta = document.getElementById('lua-code');
    var consoleEl = document.getElementById('lua-console');
    var status = document.getElementById('lua-status');
    var timeEl = document.getElementById('lua-time');
    var code = ta.value;
    if (!code.trim()) {
        consoleEl.textContent = '(no code)';
        status.className = '';
        status.textContent = '';
        timeEl.textContent = '';
        return;
    }
    if (!fengari) {
        consoleEl.textContent = 'Lua engine failed to load (assets/fengari-web.js).';
        status.className = 'badge text-bg-danger';
        status.textContent = 'ERROR';
        return;
    }
    var t0 = performance.now();
    var res = runLuaInVM(code);
    var ms = (performance.now() - t0).toFixed(1);
    var text = res.out !== '' ? res.out : (res.ok ? '(no output)' : '');
    if (!res.ok) text += (text ? '\n' : '') + res.err;
    consoleEl.textContent = text;
    status.className = res.ok ? 'badge text-bg-success' : 'badge text-bg-danger';
    status.textContent = res.ok ? 'OK' : 'ERROR';
    timeEl.textContent = ms + ' ms';
}

function runLuaInVM(code) {
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

function loadSample(i) {
    var samples = [
        ['-- sum 1..10\nlocal total = 0\nfor i = 1, 10 do\n  total = total + i\nend\nprint("sum 1..10 = " .. total)'],
        ['-- table + insert\nlocal t = { "a", "b" }\ntable.insert(t, "c")\nfor i = 1, #t do\n  print(i, t[i])\nend'],
        ['-- strings & math\nlocal s = "kevbin"\nprint(string.upper(s))\nprint(string.rep("-", 8))\nprint(math.floor(3.7), math.abs(-5))'],
    ];
    document.getElementById('lua-code').value = samples[i][0];
}
</script>
<?php page_footer(); ?>