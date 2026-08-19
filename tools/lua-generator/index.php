<?php
require_once __DIR__ . '/../../functions.php';

start_session();
page_header('Lua Code Generator');
// Pure client-side generation — no eval, no network. Default CSP is fine.
?>
<div class="container" style="max-width: 1000px;">
    <h1 class="h4 mb-1 reveal in-view">⚙️ Lua Code Generator</h1>
    <p class="text-secondary mb-3 reveal in-view">Pick a script blueprint, fill in your options and get clean, ready-to-run Lua. Everything runs in your browser — the generated code is yours, nothing is uploaded.</p>

    <div class="card mb-3 reveal in-view"><div class="card-body">
        <div class="row g-3">
            <div class="col-md-4">
                <label class="form-label" for="gen-kind">Blueprint</label>
                <select id="gen-kind" class="form-select">
                    <option value="webhook">Discord webhook messenger</option>
                    <option value="http">HTTP client (socket.getheaders)</option>
                    <option value="files">File utilities (io)</option>
                    <option value="tables">Table helpers</option>
                    <option value="game">Game loop skeleton</option>
                    <option value="math">Math / stats helpers</option>
                </select>
            </div>
            <div class="col-md-8" id="gen-fields"></div>
        </div>
        <div class="d-flex gap-2 mt-3 flex-wrap align-items-center">
            <button class="btn btn-primary" type="button" onclick="generate()">⚡ Generate</button>
            <button class="btn btn-outline-light" type="button" onclick="copyOut()">📋 Copy</button>
            <button class="btn btn-outline-light" type="button" onclick="downloadOut()">⬇ Download .lua</button>
            <span class="text-secondary small ms-auto" id="gen-feedback"></span>
        </div>
    </div></div>

    <div class="card reveal"><div class="card-body">
        <div class="d-flex align-items-center justify-content-between mb-2">
            <h2 class="h6 mb-0">Generated script</h2>
            <span class="text-secondary small"><span id="gen-lines">0</span> lines · Lua 5.1+</span>
        </div>
        <textarea id="gen-out" class="form-control" rows="18" spellcheck="false" readonly
            style="font-family:'JetBrains Mono',monospace;font-size:.8rem;background:#0b0b0b;border:1px solid var(--line);">Select a blueprint and hit Generate. Output lands here.</textarea>
    </div></div>

    <p class="text-secondary small mt-3">🔒 Ethics: generators produce general-purpose code. A webhook messenger is a standard discord.py-style utility — spamming or abusing webhooks breaks Discord's ToS, so use it only on your own servers. All other blueprints are pure Lua standard-library code that runs anywhere.</p>
</div>

<script>
(function () {
    var FIELDS = {
        webhook: [
            ['wh_url', 'url', 'Webhook URL', 'https://discord.com/api/webhooks/…', true],
            ['wh_msg', 'text', 'Message text', 'Hello from Lua!', true],
            ['wh_name', 'text', 'Bot name (optional)', '', false],
            ['wh_embed', 'checkbox', 'Include styled embed', 'true'],
            ['wh_color', 'text', 'Embed color (hex)', '#5865F2', false]
        ],
        http: [
            ['http_url', 'url', 'Target URL', 'https://example.com/', true],
            ['http_method', 'select:GET POST PUT DELETE HEAD', 'Method', 'GET'],
            ['http_headers', 'text', 'Headers (one per line: Key: value)', 'User-Agent: kg-lua', false],
            ['http_body', 'text', 'Request body (for POST/PUT)', '', false],
            ['http_quiet', 'checkbox', 'Silent failures (pcall wrapper)', 'true']
        ],
        files: [
            ['file_path', 'text', 'File path', 'data.txt', true],
            ['file_mode', 'select:write read append delete', 'Operation', 'write'],
            ['file_content', 'text', 'Content (for write/append)', 'Hello, Lua!', false]
        ],
        tables: [
            ['tb_samples', 'number', 'Sample table size', '8'],
            ['tb_shuffle', 'checkbox', 'Include shuffle helper', 'true'],
            ['tb_group', 'checkbox', 'Include group-by helper', 'false']
        ],
        game: [
            ['game_title', 'text', 'Game title', 'My Lua Game', true],
            ['game_fps', 'number', 'Target FPS', '30'],
            ['game_max_entities', 'number', 'Max entities', '100']
        ],
        math: [
            ['math_n', 'number', 'Dataset size', '50'],
            ['math_stats', 'checkbox', 'mean/median/mode/stdev', 'true'],
            ['math_extra', 'checkbox', 'clamp/lerp/round helpers', 'true']
        ]
    };
    window.__genFields = FIELDS;

    function fieldHtml(f) {
        var id = 'f-' + f[0];
        if (f[1] === 'checkbox') {
            return '<div class="form-check mt-4"><input class="form-check-input" type="checkbox" id="' + id + '"' + (f[3] === 'true' ? ' checked' : '') + '><label class="form-check-label" for="' + id + '">' + esc(f[2]) + '</label></div>';
        }
        var p = f[1].split(':');
        var t = p[0], extra = p[1] || '';
        var html = '<label class="form-label" for="' + id + '">' + esc(f[2]) + '</label>';
        if (t === 'select') {
            html += '<select id="' + id + '" class="form-select">';
            extra.split(' ').forEach(function (o) { html += '<option' + (o === f[3] ? ' selected' : '') + '>' + esc(o) + '</option>'; });
            html += '</select>';
        } else {
            html += '<input id="' + id + '" type="' + t + '" class="form-control" placeholder="' + esc(f[3]) + '"' + (t === 'number' ? ' min="1"' : '') + '>';
        }
        return html;
    }

    function v(id) {
        var el = document.getElementById('f-' + id);
        if (!el) return '';
        return el.type === 'checkbox' ? el.checked : el.value.trim();
    }

    function esc(s) {
        var d = document.createElement('div');
        d.textContent = String(s);
        return d.innerHTML;
    }

    // ————— builders: each returns array of lines —————
    var B = {
        webhook: function () {
            var L = [];
            var url = v('wh_url'), msg = v('wh_msg') || 'Hello from Lua!';
            var color = (v('wh_color') || '#5865F2').replace('#', '');
            if (!url) { window.alert('Webhook URL is required.'); return null; }
            L.push('-- Discord webhook messenger (Lua 5.1+, luasocket)');
            L.push('-- API reference: https://discord.com/developers/docs/resources/webhook');
            L.push('local http = require("socket.http")');
            L.push('local ltn12 = require("ltn12")');
            L.push('local urlcode = require("socket.url")');
            L.push('');
            L.push('local webhook_url = "' + escLua(url) + '"');
            L.push('local response_body = {}');
            L.push('');
            L.push('-- payload is prebuilt JSON (nothing sensitive lives in it)');
            var payloadObj = { content: msg };
            if (v('wh_name')) payloadObj.username = v('wh_name');
            if (v('wh_embed')) {
                payloadObj.embeds = [{
                    title: 'From Lua',
                    description: 'Generated by the KevBin Lua Code Generator',
                    color: parseInt(color, 16) || 0,
                    footer: { text: new Date().toISOString().replace('T', ' ').slice(0, 16) + ' UTC' }
                }];
            }
            var json = JSON_ENC(payloadObj);
            L.push('local body = "payload_json=" .. urlcode.escape("' + escLua(json) + '")');
            L.push('');
            L.push('http.headers = { ["Content-Type"] = "application/x-www-form-urlencoded" }');
            L.push('local res, code = http.request({');
            L.push('  url = webhook_url,');
            L.push('  method = "POST",');
            L.push('  source = body,');
            L.push('  headers = http.headers,');
            L.push('  sink = ltn12.sink.table(response_body)');
            L.push('})');
            L.push('');
            L.push('if code and code >= 200 and code < 300 then');
            L.push('  print("✓ webhook delivered (HTTP " .. tostring(code) .. ")")');
            L.push('else');
            L.push('  print("✗ webhook failed: " .. tostring(code or res))');
            L.push('  print(table.concat(response_body))');
            L.push('end');
            return L;
        },
        http: function () {
            var L = [];
            var url = v('http_url'), method = v('http_method'), headers = v('http_headers'), body = v('http_body');
            if (!url) { window.alert('Target URL is required.'); return null; }
            L.push('-- Minimal HTTP client (Lua 5.1+, luasec for HTTPS)');
            L.push('local http = require("socket.http")');
            L.push('local ltn12 = require("ltn12")');
            L.push('');
            L.push('local url = "' + escLua(url) + '"');
            L.push('local method = "' + escLua(method) + '"');
            L.push('local response_body = {}');
            L.push('local payload');
            if (body !== '') L.push('payload = "' + escLua(body) + '"');
            L.push('');
            L.push('-- headers on one line (luasec quirk)');
            L.push('http.headers = {');
            headers.split('\n').forEach(function (h) {
                var i = h.indexOf(':');
                if (i > 0) L.push('  ["' + escLua(h.slice(0, i).trim()) + '"] = "' + escLua(h.slice(i + 1).trim()) + '",');
            });
            if (headers.trim() === '') L.push('  ["User-Agent"] = "kg-lua",');
            L.push('}');
            L.push('');
            if (v('http_quiet')) {
                L.push('local ok, res, code = pcall(function()');
                L.push('  return http.request({ url = url, method = method, source = payload, sink = ltn12.sink.table(response_body) })');
                L.push('end)');
                L.push('if not ok then print("request error: " .. tostring(res)) return end');
            } else {
                L.push('local res, code = http.request({ url = url, method = method, source = payload, sink = ltn12.sink.table(response_body) })');
            }
            L.push('print("HTTP " .. tostring(code) .. " — " .. tostring(res))');
            L.push('print(table.concat(response_body))');
            return L;
        },
        files: function () {
            var L = [];
            var path = v('file_path') || 'data.txt', op = v('file_mode'), content = v('file_content');
            L.push('-- File utilities (Lua 5.1+ stock io)');
            L.push('local path = "' + escLua(path) + '"');
            L.push('');
            if (op === 'write' || op === 'append') {
                L.push('local f = assert(io.open(path, "' + (op === 'append' ? 'a' : 'w') + '"))');
                L.push('f:write("' + escLua(content) + '\\n")');
                L.push('f:close()');
                L.push('print("wrote to " .. path)');
            } else if (op === 'read') {
                L.push('local f = assert(io.open(path, "r"))');
                L.push('local data = f:read("*a")');
                L.push('f:close()');
                L.push('print(data)');
            } else {
                L.push('local ok, err = os.remove(path)');
                L.push('print(ok and ("deleted " .. path) or ("could not delete: " .. tostring(err)))');
            }
            return L;
        },
        tables: function () {
            var n = Math.max(2, parseInt(v('tb_samples') || '8', 10) || 8);
            var L = [];
            var items = [];
            for (var i = 0; i < n; i++) items.push((i + 1) * 7 % 13);
            L.push('-- Table helpers + demo');
            L.push('local demo = { ' + items.join(', ') + ' }');
            L.push('');
            L.push('local function shuffle(t)');
            L.push('  for i = #t, 2, -1 do');
            L.push('    local j = math.random(i)');
            L.push('    t[i], t[j] = t[j], t[i]');
            L.push('  end');
            L.push('  return t');
            L.push('end');
            if (v('tb_group')) {
                L.push('');
                L.push('-- group(t, fn) → { [group] = {items…} }');
                L.push('local function group(t, fn)');
                L.push('  local out = {}');
                L.push('  for _, item in ipairs(t) do');
                L.push('    local k = fn(item)');
                L.push('    out[k] = out[k] or {}');
                L.push('    table.insert(out[k], item)');
                L.push('  end');
                L.push('  return out');
                L.push('end');
            }
            L.push('');
            L.push('math.randomseed(os.time())');
            L.push('print("original:", table.concat(demo, ", "))');
            if (v('tb_shuffle')) L.push('print("shuffled:", table.concat(shuffle(demo), ", "))');
            if (v('tb_group')) {
                L.push('');
                L.push('local byParity = group(demo, function(x) return x % 2 == 0 and "even" or "odd" end)');
                L.push('print("even:", table.concat(byParity.even or {}, ", "))');
                L.push('print("odd:", table.concat(byParity.odd or {}, ", "))');
            }
            return L;
        },
        game: function () {
            var title = v('game_title') || 'My Lua Game';
            var fps = Math.max(1, parseInt(v('game_fps') || '30', 10) || 30);
            var maxE = Math.max(1, parseInt(v('game_max_entities') || '100', 10) || 100);
            var L = [];
            L.push('-- "' + escLua(title) + '" — game loop skeleton (framework-agnostic)');
            L.push('local MAX_ENTITIES = ' + maxE);
            L.push('local entities = {}');
            L.push('');
            L.push('local function spawn(x, y)');
            L.push('  if #entities >= MAX_ENTITIES then return nil end');
            L.push('  local e = { x = x, y = y, vx = 0, vy = 0 }');
            L.push('  table.insert(entities, e)');
            L.push('  return e');
            L.push('end');
            L.push('');
            L.push('local function update(dt)');
            L.push('  for _, e in ipairs(entities) do');
            L.push('    e.x = e.x + e.vx * dt');
            L.push('    e.y = e.y + e.vy * dt');
            L.push('  end');
            L.push('end');
            L.push('');
            L.push('local function draw()');
            L.push('  for _, e in ipairs(entities) do');
            L.push('    -- draw entity at e.x, e.y (framework hook)');
            L.push('  end');
            L.push('end');
            L.push('');
            L.push('local dt = 1 / ' + fps);
            L.push('local running = true');
            L.push('');
            L.push('while running do');
            L.push('  update(dt)');
            L.push('  draw()');
            L.push('  os.execute("sleep 0.05")  -- naive framerate guard; use a real timer in production');
            L.push('end');
            return L;
        },
        math: function () {
            var n = Math.max(2, parseInt(v('math_n') || '50', 10) || 50);
            var L = [];
            var data = [];
            for (var i = 0; i < n; i++) data.push(Math.floor(Math.random() * 90) + 10);
            L.push('-- Math & stats helpers');
            L.push('local data = { ' + data.join(', ') + ' }');
            L.push('local function sum(t) local s = 0 for _, x in ipairs(t) do s = s + x end return s end');
            L.push('local function percent(t, p)');
            L.push('  local s = sum(t)');
            L.push('  return (p / 100) * s');
            L.push('end');
            if (v('math_extra')) {
                L.push('local function clamp(x, lo, hi) return math.max(lo, math.min(hi, x)) end');
                L.push('local function lerp(a, b, t) return a + (b - a) * t end');
                L.push('local function round(x) return math.floor(x + 0.5) end');
            }
            if (v('math_stats')) {
                L.push('');
                L.push('local mean = sum(data) / #data');
                L.push('print(string.format("mean: %.2f", mean))');
                L.push('');
                L.push('local copy = {} for _, x in ipairs(data) do table.insert(copy, x) end');
                L.push('table.sort(copy)');
                L.push('local mid = math.floor(#copy / 2)');
                L.push('local median = (#copy % 2 == 1) and copy[mid + 1] or ((copy[mid] + copy[mid + 1]) / 2)');
                L.push('print(string.format("median: %.2f", median))');
                L.push('');
                L.push('local t = {}');
                L.push('for _, x in ipairs(data) do t[x] = (t[x] or 0) + 1 end');
                L.push('local mode, top = 0, 0');
                L.push('for k, c in pairs(t) do if c > top then top, mode = c, k end end');
                L.push('print("mode: " .. tostring(mode))');
                L.push('');
                L.push('local variance = 0');
                L.push('for _, x in ipairs(data) do variance = variance + (x - mean) ^ 2 end');
                L.push('local stdev = math.sqrt(variance / #data)');
                L.push('print(string.format("stdev: %.2f", stdev))');
            }
            L.push('');
            L.push('print("dataset size: " .. #data)');
            L.push('print("sum: " .. sum(data))');
            return L;
        }
    };

    function escLua(s) {
        return String(s).replace(/\\/g, '\\\\').replace(/"/g, '\\"').replace(/\n/g, '\\n').replace(/\r/g, '');
    }

    // JSON encoder for the webhook payload (avoid requiring a JSON lib).
    var JSON_ENC = (function () {
        function enc(obj) {
            if (obj === null) return 'null';
            if (obj === true) return 'true';
            if (obj === false) return 'false';
            var t = typeof obj;
            if (t === 'number') return String(obj);
            if (t === 'string') return '"' + String(obj).replace(/\\/g, '\\\\').replace(/"/g, '\\"').replace(/\n/g, '\\n') + '"';
            if (Array.isArray(obj)) return '[' + obj.map(enc).join(',') + ']';
            var parts = [];
            for (var k in obj) if (Object.prototype.hasOwnProperty.call(obj, k)) parts.push(enc(k) + ':' + enc(obj[k]));
            return '{' + parts.join(',') + '}';
        }
        return function (o) { return enc(o); };
    })();
    window.__genJson = JSON_ENC;

    function generate() {
        var kind = document.getElementById('gen-kind').value;
        var b = B[kind];
        if (!b) return;
        var lines = b();
        if (!lines) return;
        var out = lines.join('\n') + '\n';
        document.getElementById('gen-out').value = out;
        document.getElementById('gen-lines').textContent = lines.length;
        document.getElementById('gen-feedback').textContent = '✅ generated';
        setTimeout(function () { document.getElementById('gen-feedback').textContent = ''; }, 2000);
    }
    function copyOut() {
        var ta = document.getElementById('gen-out');
        if (!ta.value || ta.value.indexOf('hit Generate') !== -1) { generate(); }
        ta.select();
        var done = function () { document.getElementById('gen-feedback').textContent = '✅ copied'; setTimeout(function () { document.getElementById('gen-feedback').textContent = ''; }, 2000); };
        if (navigator.clipboard && navigator.clipboard.writeText) navigator.clipboard.writeText(ta.value).then(done, function () { document.execCommand('copy'); done(); });
        else { document.execCommand('copy'); done(); }
    }
    function downloadOut() {
        var ta = document.getElementById('gen-out');
        if (!ta.value || ta.value.indexOf('hit Generate') !== -1) { generate(); }
        var a = document.createElement('a');
        a.href = URL.createObjectURL(new Blob([ta.value], { type: 'text/plain' }));
        a.download = 'generated.lua';
        document.body.appendChild(a);
        a.click();
        setTimeout(function () { URL.revokeObjectURL(a.href); a.remove(); }, 500);
    }

    // init fields + wire UI
    document.getElementById('gen-kind').addEventListener('change', function () {
        var k = this.value;
        var box = document.getElementById('gen-fields');
        box.innerHTML = FIELDS[k].map(fieldHtml).join('');
    });
    document.getElementById('gen-kind').dispatchEvent(new Event('change'));
    window.generate = generate;
    window.copyOut = copyOut;
    window.downloadOut = downloadOut;
})();
</script>
<?php page_footer(); ?>