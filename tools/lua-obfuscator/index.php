<?php
require_once __DIR__ . '/../../functions.php';

start_session();
page_header('Lua Obfuscator');
?>
<div class="container" style="max-width: 1100px;">
    <h1 class="h4 mb-1 reveal in-view">Lua Obfuscator</h1>
    <p class="text-secondary mb-4 reveal in-view">Hardened in-browser Lua obfuscator. Several layers of protection plus an optional load()-based virtual machine. Your code never leaves this page.</p>

    <div class="alert alert-secondary reveal in-view">
        <strong>How it works & why the output still runs:</strong> this obfuscator rewrites your script, it never executes it. It (1) strips comments, (2) renames locals, (3) encodes strings into runtime <code>string.char()</code> builders, (4) encodes numbers, (5) injects dead code, and (6) optionally wraps the result so it is rebuilt at runtime through a <code>load()</code> "VM" — the byte-chunks are shuffled and the file never contains the plain source. Since Lua is interpreted, <code>load(string.char(...))()</code> reparses valid Lua text into a running chunk at runtime.
    </div>

    <div class="row g-3">
        <div class="col-md-6 reveal in-view">
            <label class="form-label">Input</label>
            <textarea id="lua-in" class="form-control" rows="16" style="font-family:'JetBrains Mono',monospace; font-size:.85rem;" placeholder="-- your Lua script goes here&#10;local name = 'kevbin'&#10;print('hello ' .. name)">-- your Lua script goes here
local name = 'kevbin'
local count = 42
print('hello ' .. name .. ' x' .. count)</textarea>
        </div>
        <div class="col-md-6 reveal in-view">
            <label class="form-label">Output</label>
            <textarea id="lua-out" class="form-control" rows="16" readonly style="font-family:'JetBrains Mono',monospace; font-size:.85rem;"></textarea>
        </div>
    </div>

    <div class="mt-3 reveal in-view">
        <div class="row gx-4 gy-2">
            <div class="col-6 col-md-3 form-check">
                <input class="form-check-input" type="checkbox" id="opt-rename" checked>
                <label class="form-check-label" for="opt-rename">Rename variables</label>
            </div>
            <div class="col-6 col-md-3 form-check">
                <input class="form-check-input" type="checkbox" id="opt-comments" checked>
                <label class="form-check-label" for="opt-comments">Strip comments</label>
            </div>
            <div class="col-6 col-md-3 form-check">
                <input class="form-check-input" type="checkbox" id="opt-strings" checked>
                <label class="form-check-label" for="opt-strings">Encode strings</label>
            </div>
            <div class="col-6 col-md-3 form-check">
                <input class="form-check-input" type="checkbox" id="opt-numbers" checked>
                <label class="form-check-label" for="opt-numbers">Encode numbers</label>
            </div>
            <div class="col-6 col-md-3 form-check">
                <input class="form-check-input" type="checkbox" id="opt-junk" checked>
                <label class="form-check-label" for="opt-junk">Inject junk code</label>
            </div>
            <div class="col-6 col-md-3 form-check">
                <input class="form-check-input" type="checkbox" id="opt-vm" checked>
                <label class="form-check-label" for="opt-vm">VM mode (load + shuffle)</label>
            </div>
            <div class="col-6 col-md-3 form-check">
                <input class="form-check-input" type="checkbox" id="opt-whitespace" checked>
                <label class="form-check-label" for="opt-whitespace">Minify whitespace</label>
            </div>
            <div class="col-6 col-md-3 form-check">
                <input class="form-check-input" type="checkbox" id="opt-antienv" checked>
                <label class="form-check-label" for="opt-antienv">Anti-environment checks</label>
            </div>
            <div class="col-6 col-md-3 form-check">
                <input class="form-check-input" type="checkbox" id="opt-antihook" checked>
                <label class="form-check-label" for="opt-antihook">Anti-hook (debug.hook strip)</label>
            </div>
        </div>
        <div class="row mt-2">
            <div class="col-12 col-md-5">
                <label class="form-label small text-secondary mb-1">VM strength</label>
                <select class="form-select form-select-sm" id="vm-strength" style="max-width:340px;">
                    <option value="1">Level 1 — basic load() wrapper</option>
                    <option value="2" selected>Level 2 — XOR + shuffled bytes + index map</option>
                    <option value="3">Level 3 — XOR + shuffle + nibble-split bytes</option>
                    <option value="4">Level 4 — XOR + shuffle + parity split + polymorphic junk</option>
                    <option value="5">Level 5 — nested VM: the VM itself is char-code hidden</option>
                    <option value="6">Level 6 — MAX: nested VM + dual keys + triple-split + junk</option>
                </select>
            </div>
        </div>
    </div>

    <div class="mt-3 d-flex flex-wrap gap-2 reveal in-view">
        <button class="btn btn-primary" onclick="obfuscate()">Obfuscate</button>
        <button class="btn btn-outline-light" onclick="copyOut()">Copy</button>
        <button class="btn btn-outline-light" onclick="downloadOut()">Download</button>
    </div>

    <div class="text-secondary small mt-3 reveal in-view">
        Tips: keep &ldquo;Strip comments&rdquo; and &ldquo;Minify whitespace&rdquo; on for the smallest output. VM mode heavily increases size — use it for short scripts. Never paste real secrets into an obfuscator running on a website you don't control.
    </div>
</div>

<script>
var LUA_KEYWORDS = new Set(['and','break','do','else','elseif','end','false','for','function','goto','if','in','local','nil','not','or','repeat','return','then','true','until','while','_ENV','_G','assert','collectgarbage','dofile','error','getmetatable','ipairs','load','loadfile','next','pairs','pcall','print','rawequal','rawget','rawlen','rawset','require','select','setmetatable','tonumber','tostring','type','xpcall','coroutine','debug','io','math','os','package','string','table','utf8']);

function $(id) { return document.getElementById(id); }
function isIdentStart(c) { return /[A-Za-z_]/.test(c); }
function isIdentChar(c) { return /[A-Za-z0-9_]/.test(c); }
function isDigit(c) { return c >= '0' && c <= '9'; }

function genName() {
    var abc = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    var s = '_';
    for (var i = 0; i < 6; i++) s += abc[Math.floor(Math.random() * abc.length)];
    return s;
}

function stripComments(src) {
    var out = '', i = 0, n = src.length;
    while (i < n) {
        var c = src[i];
        if (c === '-' && src[i + 1] === '-') {
            // long comment?
            if (src[i + 2] === '[') {
                var eq = '';
                var j = i + 3;
                while (j < n && src[j] === '=') { eq += '='; j++; }
                if (j < n && src[j] === '[') {
                    var endPat = ']' + eq + ']';
                    var k = src.indexOf(endPat, j + 1);
                    i = (k === -1) ? n : k + endPat.length;
                    out += ' ';
                    continue;
                }
            }
            while (i < n && src[i] !== '\n') i++;
            out += ' ';
            continue;
        }
        out += c;
        i++;
    }
    return out;
}

function longBracketAt(src, pos) {
    var j = pos + 1;
    while (j < src.length && src[j] === '=') j++;
    return j < src.length && src[j] === '[';
}

// Shared scanner: walks Lua source, calling onCode(codeChunk) for code and
// onString(literal) for each string literal (quoted or long-bracket). Comments
// are always skipped safely so they never confuse the scan. Returns rebuilt source.
function walkCode(src, onCode, onString) {
    var out = '', i = 0, n = src.length;
    while (i < n) {
        var c = src[i];
        // comment
        if (c === '-' && src[i + 1] === '-') {
            if (src[i + 2] === '[') {
                var eq = '';
                var j = i + 3;
                while (j < n && src[j] === '=') { eq += '='; j++; }
                if (j < n && src[j] === '[') {
                    var endPat = ']' + eq + ']';
                    var k = src.indexOf(endPat, j + 1);
                    out += src.substring(i, (k === -1) ? n : k + endPat.length);
                    i = (k === -1) ? n : k + endPat.length;
                    continue;
                }
            }
            while (i < n && src[i] !== '\n') i++;
            out += ' ';
            continue;
        }
        // quoted string
        if (c === '"' || c === "'") {
            var start = i, quote = c;
            i++;
            while (i < n) {
                var t = src[i];
                if (t === '\\') { i += 2; continue; }
                if (t === quote) { i++; break; }
                i++;
            }
            out += onString(src.substring(start, i));
            continue;
        }
        // long-bracket string
        if (c === '[' && longBracketAt(src, i)) {
            var eq2 = '';
            var j2 = i + 1;
            while (j2 < n && src[j2] === '=') { eq2 += '='; j2++; }
            var endPat2 = ']' + eq2 + ']';
            var k2 = src.indexOf(endPat2, j2 + 1);
            var lit = (k2 === -1) ? src.substring(i) : src.substring(i, k2 + endPat2.length);
            i += lit.length;
            out += onString(lit);
            continue;
        }
        // code run until next string/comment boundary
        var start2 = i;
        while (i < n) {
            var cc = src[i];
            if (cc === '"' || cc === "'" || cc === '[') {
                if (cc === '[' && longBracketAt(src, i)) break;
                if (cc !== '[') break;
            }
            if (cc === '-' && src[i + 1] === '-') break;
            i++;
        }
        out += onCode(src.substring(start2, i));
    }
    return out;
}

function collectLocals(src) {
    var names = new Set();
    var clean = function (raw) {
        var m = raw.replace(/\s+/g, ' ').trim();
        if (m === '' || m === '...') return;
        if (/^[A-Za-z_][A-Za-z0-9_]*$/.test(m) && !LUA_KEYWORDS.has(m)) names.add(m);
    };
    walkCode(src,
        function (code) {
            var m;
            // local function Name(...)
            var reFn = /\blocal\s+function\s+([A-Za-z_][A-Za-z0-9_]*)/g;
            while ((m = reFn.exec(code)) !== null) clean(m[1]);
            // local a, b, c = ...  (captures EVERY declared name)
            var reLoc = /\blocal\s+([A-Za-z_][A-Za-z0-9_]*(\s*,\s*[A-Za-z_][A-Za-z0-9_]*)*)/g;
            while ((m = reLoc.exec(code)) !== null) {
                m[1].split(',').forEach(function (part) { clean(part); });
            }
            // function(...) parameters — anonymous or named
            var reParams = /\bfunction\b[^=]*?\(([^)]*)\)/g;
            while ((m = reParams.exec(code)) !== null) {
                m[1].split(',').forEach(function (part) { clean(part); });
            }
            // loop variables: for i=1,10 or for _, v in pairs(t)
            var reFor = /\bfor\s+([A-Za-z_][A-Za-z0-9_]*(\s*,\s*[A-Za-z_][A-Za-z0-9_]*)*)\s*[=in]\b/g;
            while ((m = reFor.exec(code)) !== null) {
                m[1].split(',').forEach(function (part) { clean(part); });
            }
            return code;
        },
        function (lit) { return lit; });
    return names;
}

// Rename locals only inside code (strings/comments untouched); skip field accesses.
function renameLocals(src) {
    var names = collectLocals(src);
    var map = {};
    names.forEach(function (n) { map[n] = genName(); });
    if (Object.keys(map).length === 0) return src;
    return walkCode(src,
        function (code) {
            var out = '', i = 0, n = code.length, chunks = [];
            while (i < n) {
                var c = code[i];
                if (isIdentStart(c)) {
                    var start = i;
                    while (i < n && isIdentChar(code[i])) i++;
                    var ident = code.substring(start, i);
                    var prev = start > 0 ? code[start - 1] : '';
                    if (map[ident] !== undefined && prev !== '.' && prev !== ':') out += map[ident];
                    else out += ident;
                    continue;
                }
                out += c;
                i++;
            }
            return out;
        },
        function (lit) { return lit; });
}

function encodeNumbers(src) {
    return walkCode(src,
        function (code) {
            var out = '', i = 0, n = code.length;
            while (i < n) {
                var c = code[i];
                if (isDigit(c)) {
                    var start = i;
                    // Digits glued to an identifier (Level7_Power, x2y) or a float
                    // tail (.5) must never be rewritten — leave them verbatim.
                    var prev = i > 0 ? code[i - 1] : '';
                    if (prev === '.' || isIdentStart(prev)) {
                        while (i < n && isIdentChar(code[i])) i++;
                        out += code.substring(start, i);
                        continue;
                    }
                    while (i < n && isDigit(code[i])) i++;
                    var after = i < n ? code[i] : '';
                    if (after === '.') {
                        // float 1.5 or 1. — copy digits, then consume float tail
                        var j = i;
                        if (j < n && code[j] === '.') {
                            j++;
                            while (j < n && isDigit(code[j])) j++;
                            if (j < n && (code[j] === 'e' || code[j] === 'E')) {
                                j++;
                                if (j < n && (code[j] === '+' || code[j] === '-')) j++;
                                while (j < n && isDigit(code[j])) j++;
                            }
                        }
                        out += code.substring(start, j);
                        i = j;
                        continue;
                    }
                    if (after === 'x' || after === 'X') {
                        i++;
                        while (i < n && /[0-9a-fA-F]/.test(code[i])) i++;
                        out += code.substring(start, i);
                        continue;
                    }
                    var num = code.substring(start, i);
                    out += /^\d+$/.test(num) ? '(' + num + '+0)' : num;
                    continue;
                }
                out += c;
                i++;
            }
            return out;
        },
        function (lit) { return lit; });
}

function unescapeLua(inner) {
    // Convert Lua escape sequences in a string literal to real characters.
    var out = '', i = 0;
    while (i < inner.length) {
        var ch = inner[i];
        if (ch === '\\') {
            if (i + 1 < inner.length) {
                var nx = inner[i + 1];
                var map = { 'n': '\n', 't': '\t', 'r': '\r', 'a': '\x07', 'b': '\b', 'f': '\f', 'v': '\x0B', '0': '\0' };
                if (nx === 'x') {
                    out += String.fromCharCode(parseInt(inner.substr(i + 2, 2), 16) || 0);
                    i += 4;
                } else if (/[0-7]/.test(nx)) {
                    var k = i + 1, num = '';
                    while (k < inner.length && k < i + 4 && /[0-7]/.test(inner[k])) { num += inner[k]; k++; }
                    out += String.fromCharCode(parseInt(num, 8));
                    i = k;
                } else if (map[nx] !== undefined) {
                    out += map[nx];
                    i += 2;
                } else {
                    out += nx;
                    i += 2;
                }
            } else { i++; }
        } else {
            out += ch;
            i++;
        }
    }
    return out;
}

// Proper UTF-8 byte encoding (JS chars / UTF-16 -> UTF-8 bytes). Lua strings are
// byte strings, so string.char(utf8 bytes) reproduces the exact original text on
// any Lua version — emoji and non-ASCII survive instead of being truncated.
function utf8Bytes(str) {
    var out = [];
    for (var i = 0; i < str.length; i++) {
        var c = str.charCodeAt(i);
        if (c < 128) {
            out.push(c);
        } else if (c < 2048) {
            out.push(192 | (c >> 6), 128 | (c & 63));
        } else if (c >= 0xD800 && c <= 0xDBFF && i + 1 < str.length) {
            var lo = str.charCodeAt(i + 1);
            if (lo >= 0xDC00 && lo <= 0xDFFF) {
                var cp = 0x10000 + ((c - 0xD800) << 10) + (lo - 0xDC00);
                out.push(240 | (cp >> 18), 128 | ((cp >> 12) & 63), 128 | ((cp >> 6) & 63), 128 | (cp & 63));
                i++;
            } else {
                out.push(0xEF, 0xBF, 0xBD); // lone surrogate -> U+FFFD
            }
        } else if (c >= 0xDC00 && c <= 0xDFFF) {
            out.push(0xEF, 0xBF, 0xBD);
        } else {
            out.push(224 | (c >> 12), 128 | ((c >> 6) & 63), 128 | (c & 63));
        }
    }
    return out;
}

function encodeStrings(src) {
    var decoder = "(function(c)local b=''for i=1,#c,2 do b=b..string.char(tonumber(c:sub(i,i+1),16))end return b end)";
    var toHex = function (inner) {
        var bytes = utf8Bytes(inner);
        var hex = '';
        for (var j = 0; j < bytes.length; j++) {
            hex += ('0' + bytes[j].toString(16)).slice(-2);
        }
        if (hex.length === 0) return "''";
        var parts = [];
        for (var p = 0; p < hex.length; p += 60) parts.push("'" + hex.substr(p, 60) + "'");
        return decoder + '(' + parts.join('..') + ')';
    };
    return walkCode(src,
        function (code) { return code; },
        function (lit) {
            if (lit.charCodeAt(0) === 91) { // long bracket [=...=[ ... ]=...]
                var eq = '';
                var j = 1;
                while (lit[j] === '=') { eq += '='; j++; }
                var inner = lit.substring(j + 1, lit.length - (eq.length + 2));
                return toHex(inner);
            }
            // quoted string literal
            var inner = unescapeLua(lit.slice(1, -1));
            return toHex(inner);
        });
}

function insertJunk(src) {
    var lines = src.split('\n');
    var out = [];
    lines.forEach(function (line) {
        var t = line.trim();
        if (t.length > 0 && /^(local|return|if|for|while|repeat|function)\b/.test(t) && Math.random() < 0.5) {
            var j = genName();
            out.push("if(_G~=_G)then local ".concat(j, "='q'", j, "='w'end"));
        }
        out.push(line);
    });
    return out.join('\n');
}

function shuffleBytes(bytes) {
    // Build a scrambled copy of bytes (E) plus an inverse map (Q).
    // P is a random permutation; E[k] = bytes[P[k]]; Q[i] = 1-based k where byte i lives in E.
    var N = bytes.length;
    var P = [];
    for (var i = 0; i < N; i++) P.push(i);
    var seed = Math.floor(Math.random() * 1000000) + 1;
    var s = seed;
    function rnd() { s = (s * 1103515245 + 12345) % 2147483648; return s / 2147483648; }
    for (var i = N - 1; i > 0; i--) {
        var j = Math.floor(rnd() * (i + 1));
        var t = P[i]; P[i] = P[j]; P[j] = t;
    }
    var E = new Array(N);
    for (var k = 0; k < N; k++) E[k] = bytes[P[k]];
    var Q = new Array(N);
    for (var k = 0; k < N; k++) Q[P[k]] = k + 1; // 1-based slot
    return { E: E, Q: Q };
}

function numTable(list, perLine) {
    // Output ONE flat Lua table { ... } (wrapped for readability, still flat).
    var out = [];
    for (var i = 0; i < list.length; i += perLine) {
        out.push(list.slice(i, i + perLine).join(','));
    }
    return '{' + out.join(',') + '}';
}

// Generates a Lua guard prelude (anti-env + anti-hook). Returns '' if neither enabled.
function antiPrelude() {
    var antiEnv = $('opt-antienv').checked;
    var antiHook = $('opt-antihook').checked;
    if (!antiEnv && !antiHook) return '';
    var parts = [];
    if (antiHook) {
        var d = genName(), h = genName(), hk = genName();
        parts.push('local ' + d + '=debug');
        parts.push('local ' + h + '=(' + d + ' and ' + d + '.gethook)');
        parts.push('if ' + h + ' and ' + h + '()~=nil then (' + d + '.sethook or function()end)(nil) end');
        var g = genName(), g1 = genName(), g2 = genName();
        parts.push('local ' + g + '=_G');
        parts.push('local ' + g1 + '=((' + g + ' or {}).print)');
        parts.push('local ' + g2 + '=((' + g + ' or {}).loadstring)');
        parts.push('if ' + g + ' and ' + g1 + ' and ((' + g + ').print)~=' + g1 + ' then error(\'hook detected\',0) end');
        parts.push('if ' + g + ' and ' + g2 + ' and ((' + g + ').loadstring)~=' + g2 + ' then error(\'hook detected\',0) end');
    }
    if (antiEnv) {
        var e1 = genName(), e2 = genName();
        parts.push('local ' + e1 + '=load or loadstring');
        parts.push('if not ' + e1 + ' then error(\'environment missing loader\',0) end');
        parts.push('local ' + e2 + '=(debug and debug.getinfo)');
        parts.push('if ' + e2 + ' and type(print)~=\'function\' then error(\'environment tampered\',0) end');
    }
    return parts.join(' ');
}

// Return a Lua expression that evaluates to k without ever writing k literally.
function keyExpr(k) {
    var a = Math.floor(Math.random() * 90) + 10;
    var b = Math.floor(Math.random() * 90) + 10;
    var c = ((k - a * b) % 256 + 256) % 256;
    return '(' + a + '*' + b + '+' + c + '+0)%256';
}

// n random junk statements (valid Lua, dead-code). genName() starts with '_',
// so these never collide with fixed builder names like E,Q,k,Z,i.
function junkStatements(n) {
    var pool = [
        function () { return 'if 1==2 then local ' + genName() + '=0 end '; },
        function () { return 'local ' + genName() + '=7*0 '; },
        function () { return 'if 13*13~=169 then local ' + genName() + '=0 end '; },
        function () { return 'local ' + genName() + '=(' + genName() + ' or 0) '; },
        function () { return 'if (55*0)~=1 then local ' + genName() + '=7 else local ' + genName() + '=1 end '; },
        function () { return 'local ' + genName() + ',' + genName() + '=3,\'x\' '; },
        function () { return 'if 2~=3 then local ' + genName() + '=2 end '; }
    ];
    var out = [];
    for (var i = 0; i < n; i++) out.push(pool[Math.floor(Math.random() * pool.length)]());
    return out;
}

function vmWrap(src, strength, prelude) {
    // prepend guard prelude so it's hidden inside the encoded payload
    if (prelude) src = prelude + '\n' + src;
    // UTF-8-encode the payload so VM mode works with any text (Latin-1, emoji,
    // Chinese, ...) — Lua source is interpreted as a byte string by load().
    var bytes = utf8Bytes(src);
    var v1 = genName(), v2 = genName(), v3 = genName(), v4 = genName(), v5 = genName(), v6 = genName(), v7 = genName(), v8 = genName(), v9 = genName();

    if (strength === 1) {
        var chain = [];
        for (var i = 0; i < bytes.length; i += 24) {
            chain.push(v1 + '=' + v1 + '..string.char(' + bytes.slice(i, i + 24).join(',') + ')');
        }
        return '(function()local ' + v1 + '=""' + chain.join('') +
            'local ' + v2 + '=(loadstring or load)(' + v1 + ')' +
            'if ' + v2 + ' then return ' + v2 + '() end end)()';
    }

    // Levels 2-4: additive cipher with key byte, then shuffle. Decode reverses both.
    // The key itself is only ever present as an arithmetic expression, never as a literal.
    var key = Math.floor(Math.random() * 255) + 1;
    var ke = keyExpr(key);
    var xored = [];
    for (var i = 0; i < bytes.length; i++) xored.push((bytes[i] + key) % 256);
    var sh = shuffleBytes(xored);

    if (strength === 2) {
        return '(function()' +
            'local k=' + ke + ' ' + junkStatements(1).join('') +
            'local ' + v1 + '=' + numTable(sh.E, 40) +
            'local ' + v2 + '=' + numTable(sh.Q, 40) +
            'local ' + v3 + '=""' + junkStatements(1).join('') +
            'for ' + v4 + '=1,#' + v2 + ' do ' +
            v3 + '=' + v3 + '..string.char(((' + v1 + '[' + v2 + '[' + v4 + ']]-k)%256))' +
            'end ' + junkStatements(1).join('') +
            'local ' + v5 + '=(loadstring or load)(' + v3 + ')if ' + v5 + ' then return ' + v5 + '() end end)()';
    }

    if (strength === 3) {
        var hi = [], lo = [];
        for (var i = 0; i < sh.E.length; i++) { hi.push(sh.E[i] >> 4); lo.push(sh.E[i] & 15); }
        return '(function()' +
            'local k=' + ke + ' ' + junkStatements(1).join('') +
            'local ' + v1 + '=' + numTable(hi, 40) + ' ' +
            'local ' + v2 + '=' + numTable(lo, 40) + ' ' +
            'local ' + v3 + '=' + numTable(sh.Q, 40) + ' ' +
            'local ' + v4 + '="" ' + junkStatements(1).join('') +
            'local ' + v6 + '=0 ' +
            'local ' + v7 + '="" ' +
            'for ' + v5 + '=1,#' + v3 + ' do ' +
            v6 + '=' + v3 + '[' + v5 + '] ' +
            v7 + '=' + v7 + '..string.char(((' + v1 + '[' + v6 + ']*16+' + v2 + '[' + v6 + ']-k)%256))end ' +
            v4 + '=' + v7 + ' ' + junkStatements(1).join('') +
            'local ' + v8 + '=(loadstring or load)(' + v4 + ')if ' + v8 + ' then return ' + v8 + '() end end)()';
    }

    if (strength === 4) {
        // Level 4: dual-cipher variant — E split into two arrays by slot parity
        var a = [], b = [];
        for (var i = 0; i < sh.E.length; i++) {
            if ((i + 1) % 2 === 1) a.push(sh.E[i]); else b.push(sh.E[i]);
        }
        var vA = genName(), vB = genName(), vQ = genName(), vBuild = genName(),
            vAcc = genName(), vIt = genName(), vFake = genName(), vLoad = genName(), vIdx = genName();
        return '(function()' +
            'local k=' + ke + ' ' + junkStatements(1).join('') +
            'local ' + vA + '=' + numTable(a, 40) + ' ' +
            'local ' + vB + '=' + numTable(b, 40) + ' ' +
            'local ' + vQ + '=' + numTable(sh.Q, 40) + ' ' +
            'local ' + vFake + '=0 ' + junkStatements(1).join('') +
            'local ' + vBuild + '="" ' +
            'local ' + vAcc + '=0 ' + junkStatements(1).join('') +
            'local ' + vIt + '=0 ' +
            'local ' + vIdx + '=0 ' +
            'local ' + vLoad + '=0 ' +
            'if (55*0)~=1 then ' + vFake + '=7 else ' + vFake + '=' + vFake + ' end ' +
            'for ' + vIt + '=1,#' + vQ + ' do ' +
            vIdx + '=' + vQ + '[' + vIt + '] ' +
            vAcc + '=(' + vIdx + '%2)==1 and ' + vA + '[(((' + vIdx + ')+1)/2)] or ' + vB + '[' + vIdx + '/2] ' +
            vBuild + '=' + vBuild + '..string.char(((' + vAcc + '-k)%256)) ' + junkStatements(1).join('') +
            'end ' + junkStatements(1).join('') +
            vLoad + '=(loadstring or load)(' + vBuild + ')if ' + vLoad + ' then return ' + vLoad + '() end end)()';
    }

    if (strength === 5) {
        // Level 5 — nested VM: the outer wrapper only holds data tables; the VM
        // (loop + string.char + load) is itself encoded as char codes, so the
        // output file never contains a readable VM at all.
        var bp = ['local Z=""'];
        bp = bp.concat(junkStatements(2));
        bp.push('for i=1,#Q do Z=Z..string.char((E[Q[i]]-k)%256)');
        bp = bp.concat(junkStatements(1));
        bp.push('end');
        bp = bp.concat(junkStatements(1));
        bp.push('local f=(loadstring or load)(Z)if f then return f() end');
        var builderSrc = '(function(E,Q,k)' + bp.join('') + 'end)';
        var bch = [];
        for (var i = 0; i < builderSrc.length; i++) bch.push(builderSrc.charCodeAt(i));
        var chain5 = [];
        for (var i = 0; i < bch.length; i += 20) {
            chain5.push(v1 + '=' + v1 + '..string.char(' + bch.slice(i, i + 20).join(',') + ')');
        }
        return '(function()' +
            'local k=' + ke + ' ' + junkStatements(1).join('') +
            'local ' + v2 + '=' + numTable(sh.E, 40) + ' ' +
            'local ' + v3 + '=' + numTable(sh.Q, 40) + ' ' + junkStatements(1).join('') +
            'local ' + v1 + '=""' + chain5.join('') + junkStatements(1).join('') +
            'local ' + v4 + '=(loadstring or load)(' + v1 + ')' +
            'if ' + v4 + ' then return ' + v4 + '(' + v2 + ',' + v3 + ',k) end end)()';
    }

    // Level 6 — MAX: nested VM + TWO alternating keys (chosen by shuffled slot
    // parity, so key schedule and byte data are entangled) + E triple-split by
    // slot modulo 3 + polymorphic junk inside the hidden VM itself.
    var k1 = Math.floor(Math.random() * 255) + 1;
    var k2 = Math.floor(Math.random() * 255) + 1;
    var ke1 = keyExpr(k1), ke2 = keyExpr(k2);
    var N = bytes.length;
    var sh6 = shuffleBytes(bytes);
    var encE = new Array(N);
    for (var o = 0; o < N; o++) {
        var slot = sh6.Q[o];
        var kk = (slot % 2 === 0) ? k2 : k1;
        encE[slot - 1] = (bytes[o] + kk) % 256;
    }
    var A6 = [], B6 = [], C6 = [];
    for (var s = 0; s < N; s++) {
        var pos = s + 1;
        if (pos % 3 === 1) A6.push(encE[s]); else if (pos % 3 === 2) B6.push(encE[s]); else C6.push(encE[s]);
    }
    var bp6 = ['local Z=""'];
    bp6 = bp6.concat(junkStatements(2));
    bp6.push('for i=1,#Q do');
    bp6.push('local Y=Q[i] local X=(Y%3==1 and A[((Y+2)/3)] or (Y%3==2 and B[((Y+1)/3)] or C[Y/3]))');
    bp6 = bp6.concat(junkStatements(1));
    bp6.push('X=(X-((Y%2==0 and k1) or k2))%256 Z=Z..string.char(X)');
    bp6 = bp6.concat(junkStatements(1));
    bp6.push('end');
    bp6 = bp6.concat(junkStatements(2));
    bp6.push('local f=(loadstring or load)(Z)if f then return f() end');
    var builderSrc6 = '(function(A,B,C,Q,k1,k2)' + bp6.join('') + 'end)';
    var bch6 = [];
    for (var i = 0; i < builderSrc6.length; i++) bch6.push(builderSrc6.charCodeAt(i));
    var chain6 = [];
    for (var i = 0; i < bch6.length; i += 20) {
        chain6.push(v1 + '=' + v1 + '..string.char(' + bch6.slice(i, i + 20).join(',') + ')');
    }
    var vA6 = genName(), vB6 = genName(), vC6 = genName(), vQ6 = genName();
    return '(function()' +
        'local k1=' + ke1 + ' local k2=' + ke2 + ' ' + junkStatements(1).join('') +
        'local ' + vA6 + '=' + numTable(A6, 40) + ' ' +
        'local ' + vB6 + '=' + numTable(B6, 40) + ' ' +
        'local ' + vC6 + '=' + numTable(C6, 40) + ' ' +
        'local ' + vQ6 + '=' + numTable(sh6.Q, 40) + ' ' + junkStatements(1).join('') +
        'local ' + v1 + '=""' + chain6.join('') + junkStatements(2).join('') +
        'local ' + v4 + '=(loadstring or load)(' + v1 + ')' +
        'if ' + v4 + ' then return ' + v4 + '(' + vA6 + ',' + vB6 + ',' + vC6 + ',' + vQ6 + ',k1,k2) end end)()';
}

function minify(src) {
    return src.replace(/\n\s*\n+/g, '\n').replace(/^\s+/gm, '');
}

function obfuscate() {
    var src = $('lua-in').value;
    var out = src;
    try {
        if ($('opt-comments').checked) out = stripComments(out);
        if ($('opt-rename').checked) out = renameLocals(out);
        if ($('opt-numbers').checked) out = encodeNumbers(out);
        if ($('opt-strings').checked) out = encodeStrings(out);
        if ($('opt-junk').checked) out = insertJunk(out);
        if ($('opt-whitespace').checked) out = minify(out);
        var prelude = antiPrelude();
        if ($('opt-vm').checked) {
            var r = vmWrap(out, parseInt($('vm-strength').value, 10), prelude);
            if (r === null) return;
            out = r;
        } else if (prelude) {
            out = prelude + '\n' + out;
        }
        $('lua-out').value = out + '\n';
    } catch (e) {
        $('lua-out').value = 'Error: ' + (e && e.message ? e.message : e);
    }
}

function copyOut() {
    var t = $('lua-out');
    t.select(); document.execCommand('copy');
}
function downloadOut() {
    var t = $('lua-out').value;
    var b = new Blob([t], { type: 'text/plain' });
    var a = document.createElement('a');
    a.href = URL.createObjectURL(b);
    a.download = 'obfuscated.lua';
    a.click();
}
</script>
<?php page_footer(); ?>