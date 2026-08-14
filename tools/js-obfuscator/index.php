<?php
require_once __DIR__ . '/../../functions.php';

start_session();
page_header('HTML & JS Obfuscator');
?>
<div class="container" style="max-width: 1150px;">
    <h1 class="h4 mb-1 reveal in-view">🕶 HTML &amp; JavaScript Obfuscator</h1>
    <p class="text-secondary mb-3 reveal in-view">Hardened in-browser obfuscator with 6 strength levels. Rewrites your code into minified, encoded, name-mangled, junk-filled output — nothing you paste ever leaves this page.</p>

    <div class="alert alert-secondary reveal in-view">
        <strong>How it works:</strong> the tool only rewrites text, it never executes your code. Level 1 minifies, level 2 escapes strings/numbers, level 3 moves strings into an XOR-decoded pool, level 4 renames your variables &amp; functions (safe: object keys, destructuring and properties are never touched), level 5 hides the whole script behind an eval/Function wrapper, level 6 adds junk injection and an anti-debug timer. HTML mode does the same for the page itself, encoding the entire document into a <code>document.write()</code> decoder.
    </div>

    <ul class="nav nav-pills mb-3 reveal in-view" id="obf-tabs">
        <li class="nav-item"><a class="nav-link active" data-tab="js" href="#js">📜 JavaScript</a></li>
        <li class="nav-item"><a class="nav-link" data-tab="html" href="#html">🌐 HTML page</a></li>
    </ul>

    <div class="row g-3">
        <div class="col-md-6 reveal in-view">
            <label class="form-label">Input</label>
            <textarea id="in-js" class="form-control obf-in" rows="16" style="font-family:'JetBrains Mono',monospace;font-size:.8rem;">const secret = "hello kevbin";
var total = 0;
function add(a, b) {
    return a + b;
}
for (let i = 0; i < 3; i++) {
    total += add(i, i * 2);
}
console.log(secret, total);</textarea>
            <textarea id="in-html" class="form-control obf-in d-none" rows="16" style="font-family:'JetBrains Mono',monospace;font-size:.8rem;"><!DOCTYPE html>
<html>
<head><title>Sample</title>
<script>
function greet(name) {
    return "hi " + name;
}
console.log(greet("kevbin"));
</script>
</head>
<body>
<!-- this comment disappears -->
<h1>Hello</h1>
<p>Welcome to my page.</p>
</body>
</html></textarea>
        </div>
        <div class="col-md-6 reveal in-view">
            <label class="form-label">Output</label>
            <textarea id="out" class="form-control" rows="16" readonly style="font-family:'JetBrains Mono',monospace;font-size:.8rem;"></textarea>
        </div>
    </div>

    <div class="mt-3 reveal in-view">
        <div class="row gx-4 gy-2">
            <div class="col-12 col-md-5">
                <label class="form-label small text-secondary mb-1">Strength level</label>
                <select class="form-select form-select-sm" id="level">
                    <option value="1">Level 1 — compact: strip comments + minify</option>
                    <option value="2">Level 2 — + escape strings (\x/\u) and hex numbers</option>
                    <option value="3" selected>Level 3 — + XOR string decoder pool</option>
                    <option value="4">Level 4 — + safe variable/function renaming</option>
                    <option value="5">Level 5 — + eval/Function wrapper (source hidden)</option>
                    <option value="6">Level 6 — MAX: + junk injection + anti-debug</option>
                </select>
            </div>
        </div>
        <div class="row gx-4 gy-2 mt-1">
            <div class="col-6 col-md-3 form-check">
                <input class="form-check-input" type="checkbox" id="opt-strings">
                <label class="form-check-label" for="opt-strings">Encode strings</label>
            </div>
            <div class="col-6 col-md-3 form-check">
                <input class="form-check-input" type="checkbox" id="opt-numbers">
                <label class="form-check-label" for="opt-numbers">Encode numbers (hex)</label>
            </div>
            <div class="col-6 col-md-3 form-check">
                <input class="form-check-input" type="checkbox" id="opt-decoder">
                <label class="form-check-label" for="opt-decoder">XOR string decoder</label>
            </div>
            <div class="col-6 col-md-3 form-check">
                <input class="form-check-input" type="checkbox" id="opt-rename">
                <label class="form-check-label" for="opt-rename">Rename variables</label>
            </div>
            <div class="col-6 col-md-3 form-check">
                <input class="form-check-input" type="checkbox" id="opt-junk">
                <label class="form-check-label" for="opt-junk">Junk code</label>
            </div>
            <div class="col-6 col-md-3 form-check">
                <input class="form-check-input" type="checkbox" id="opt-antidebug">
                <label class="form-check-label" for="opt-antidebug">Anti-debug timer</label>
            </div>
            <div class="col-6 col-md-3 form-check">
                <input class="form-check-input" type="checkbox" id="opt-vm">
                <label class="form-check-label" for="opt-vm">VM wrapper (eval)</label>
            </div>
            <div class="col-6 col-md-3 form-check">
                <input class="form-check-input" type="checkbox" id="opt-scripts">
                <label class="form-check-label" for="opt-scripts">Obfuscate &lt;script&gt; blocks</label>
            </div>
        </div>
    </div>

    <div class="mt-3 d-flex flex-wrap gap-2 reveal in-view">
        <button class="btn btn-primary" onclick="doObfuscate()">Obfuscate</button>
        <button class="btn btn-outline-light" onclick="copyOut()">Copy</button>
        <button class="btn btn-outline-light" onclick="downloadOut()">Download</button>
    </div>

    <div id="warnings" class="mt-3"></div>

    <p class="text-secondary small mt-3 reveal in-view mb-0">Tips: level 5/6 output needs <code>unsafe-eval</code> in your CSP (or no CSP). The VM wrapper runs code in its own scope — don't use it if your script shares globals with other scripts. Level 4+ renames identifiers: inline event handlers like <code>onclick="foo()"</code> are not renamed — test your output. Never paste real secrets into an obfuscator running on a website you don't control.</p>
</div>

<script>
var KEYWORDS = new Set(['break','case','catch','class','const','continue','debugger','default','delete','do','else','enum','export','extends','false','finally','for','function','if','implements','import','in','instanceof','interface','let','new','null','package','private','protected','public','return','static','super','switch','this','throw','true','try','typeof','var','void','while','with','yield','await','async','of','get','set','arguments','undefined','NaN','Infinity','globalThis','window','document','console','Math','JSON','Object','Array','String','Number','Boolean','Date','RegExp','Error','Promise','Function','eval','parseInt','parseFloat','isNaN','isFinite','encodeURIComponent','decodeURIComponent','setTimeout','setInterval','clearInterval','requestAnimationFrame','localStorage','sessionStorage','navigator','location','fetch','XMLHttpRequest','alert','confirm','prompt','atob','btoa']);
var REGEX_AFTER = new Set(['return','typeof','case','in','of','new','delete','void','do','else','yield','await','instanceof','throw','extends','this','super','function','class','default']);
var OBJECT_AFTER_OPS = new Set(['(','=', ':', ',', '[', '?', '!', '&', '|', '+', '-', '*', '/', '%', '^', '~', '=>', '&&', '||', '??', 'var', 'let', 'const']);
var OBJECT_AFTER_IDENTS = new Set(['return','yield','in','of','case','new','delete','typeof','void','extends']);
var OPS = new Set(['??=','**=','<<=','>>=','>>>=','&&=','||=','>>>','===','!==','...','??','?.','=>','**','++','--','<<','>>','<=','>=','==','!=','&&','||','+=','-=','*=','/=','%=','&=','|=','^=','{','}','(',')','[',']',';',',',':','.','?','+','-','*','%','&','|','^','!','~','<','>','=','#','@']);
var CLOSE_EMIT = ')]}],;:.';
var OPEN_EMIT = '([{.,;:';

function isDigit(c){ return c >= '0' && c <= '9'; }
function isIdentStart(c){ return /[A-Za-z_$]/.test(c); }
function isIdentChar(c){ return /[A-Za-z0-9_$]/.test(c); }
function hex2(v){ return ('0' + v.toString(16)).slice(-2); }
function hex4(v){ return ('000' + v.toString(16)).slice(-4); }
function randStr(len){
    var s = '', abc = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    for (var i = 0; i < len; i++) s += abc[Math.floor(Math.random() * abc.length)];
    return s;
}
function freshName(avoid){
    var abc = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    var n;
    do {
        n = '';
        for (var i = 0; i < 6; i++) n += abc[Math.floor(Math.random() * 52)];
    } while (avoid.has(n) || KEYWORDS.has(n));
    return n;
}

function tokenize(src){
    var tokens = [];
    var i = 0, n = src.length;
    var prev = null;
    function add(t, v){ tokens.push({t: t, v: v}); prev = tokens[tokens.length - 1]; }

    function scanString(quote){
        var j = i + 1;
        while (j < n){
            var c = src[j];
            if (c === '\\'){ j += 2; continue; }
            if (c === quote) break;
            if (c === '\n') break;
            j++;
        }
        var end = (j < n && src[j] === quote) ? j + 1 : j;
        add('str', src.slice(i, end));
        i = end;
    }

    function scanTemplate(){
        var j = i + 1;
        var text = '`';
        while (true){
            if (j >= n) break;
            var c = src[j];
            if (c === '\\'){ text += src.slice(j, j + 2); j += 2; continue; }
            if (c === '`'){ text += '`'; j++; break; }
            if (c === '$' && src[j + 1] === '{'){
                if (text !== ''){ add('tpl', text + '${'); text = ''; }
                else add('tpl', '${');
                j += 2;
                var depth = 0;
                while (j < n){
                    i = j;
                    scanOne();
                    j = i;
                    if (prev.t === 'op' && prev.v === '{') depth++;
                    else if (prev.t === 'op' && prev.v === '}'){
                        if (depth === 0){
                            tokens[tokens.length - 1] = { t: 'tpl', v: '}' };
                            prev = tokens[tokens.length - 1];
                            break;
                        }
                        depth--;
                    }
                }
                continue;
            }
            text += c;
            j++;
        }
        if (text !== '') add('tpl', text);
        i = j;
    }

    function scanRegex(){
        var j = i + 1;
        var inClass = false;
        while (j < n){
            var c = src[j];
            if (c === '\\'){ j += 2; continue; }
            if (c === '\n') return false;
            if (c === '[') inClass = true;
            else if (c === ']') inClass = false;
            else if (c === '/' && !inClass) break;
            j++;
        }
        if (j >= n) return false;
        var end = j + 1;
        while (end < n && /[a-z]/i.test(src[end])) end++;
        add('regex', src.slice(i, end));
        i = end;
        return true;
    }

    function scanOne(){
        while (i < n && (src[i] === ' ' || src[i] === '\t' || src[i] === '\n' || src[i] === '\r')) i++;
        if (i >= n){ prev = null; return; }
        var c = src[i];
        if (c === '/' && src[i + 1] === '/'){
            while (i < n && src[i] !== '\n') i++;
            prev = null;
            return;
        }
        if (c === '/' && src[i + 1] === '*'){
            var e = src.indexOf('*/', i + 2);
            i = (e === -1) ? n : e + 2;
            prev = null;
            return;
        }
        if (c === '"' || c === "'"){ scanString(c); return; }
        if (c === '`'){ scanTemplate(); return; }
        if (c === '/'){
            if (src[i + 1] === '='){ add('op', '/='); i += 2; return; }
            var allowed = prev === null || (prev.t === 'op' && prev.v !== ')' && prev.v !== ']' && prev.v !== '++' && prev.v !== '--') || (prev.t === 'ident' && REGEX_AFTER.has(prev.v));
            if (allowed && scanRegex()) return;
            add('op', '/');
            i++;
            return;
        }
        if (isDigit(c) || (c === '.' && isDigit(src[i + 1]))){
            var j = i;
            if (c === '0' && /[xXbBoO]/.test(src[j + 1] || '')){
                var rad = src[j + 1].toLowerCase();
                j += 2;
                var pat = rad === 'x' ? /[0-9a-fA-F]/ : (rad === 'b' ? /[01]/ : /[0-7]/);
                while (j < n && (pat.test(src[j]) || src[j] === '_')) j++;
                add('num', src.slice(i, j));
                i = j;
                return;
            }
            while (j < n && (isDigit(src[j]) || src[j] === '_')) j++;
            if (src[j] === '.' && isDigit(src[j + 1])){
                j++;
                while (j < n && (isDigit(src[j]) || src[j] === '_')) j++;
            }
            if ((src[j] === 'e' || src[j] === 'E') && (isDigit(src[j + 1]) || ((src[j + 1] === '+' || src[j + 1] === '-') && isDigit(src[j + 2])))){
                j = (src[j + 1] === '+' || src[j + 1] === '-') ? j + 2 : j + 1;
                while (j < n && (isDigit(src[j]) || src[j] === '_')) j++;
            }
            add('num', src.slice(i, j));
            i = j;
            return;
        }
        if (isIdentStart(c)){
            var j = i;
            while (j < n && isIdentChar(src[j])) j++;
            add('ident', src.slice(i, j));
            i = j;
            return;
        }
        for (var L = 4; L >= 1; L--){
            var cand = src.substr(i, L);
            if (OPS.has(cand)){ add('op', cand); i += L; return; }
        }
        add('op', c);
        i++;
    }

    while (i < n){
        scanOne();
        if (prev === null && i < n){
            var k = i;
            while (k < n && (src[k] === ' ' || src[k] === '\t' || src[k] === '\n' || src[k] === '\r')) k++;
            i = k;
        }
    }
    return tokens;
}

function rebuild(tokens, names){
    var out = '';
    var prev = null;
    var prevIsTpl = false;
    for (var i = 0; i < tokens.length; i++){
        var tk = tokens[i];
        var v = tk.t === 'ident' && names && names[tk.v] ? names[tk.v] : tk.v;
        if (v === '') continue;
        if (prev !== null){
            var b0 = v[0];
            var aLast = prev[prev.length - 1];
            var noSpace = prevIsTpl || CLOSE_EMIT.indexOf(b0) !== -1 || OPEN_EMIT.indexOf(aLast) !== -1;
            if (b0 === '.' && isDigit(aLast)) noSpace = false;
            if (!noSpace) out += ' ';
        }
        out += v;
        prev = v;
        prevIsTpl = tk.t === 'tpl';
    }
    return out;
}

function collectRenamePlan(tokens){
    var declared = new Set();
    var keys = new Set();
    var allIdents = new Set();
    var stack = [];
    for (var i = 0; i < tokens.length; i++){
        var tk = tokens[i];
        var pv = i > 0 ? tokens[i - 1] : null;
        var nv = i < tokens.length - 1 ? tokens[i + 1] : null;
        if (tk.t === 'ident'){
            allIdents.add(tk.v);
            var ctx = stack.length ? stack[stack.length - 1] : 'block';
            if (pv && pv.t === 'op' && (pv.v === 'var' || pv.v === 'let' || pv.v === 'const')) declared.add(tk.v);
            else if (pv && pv.t === 'ident' && pv.v === 'function') declared.add(tk.v);
            else if (pv && pv.t === 'op' && pv.v === '*' && i > 1 && tokens[i - 2].t === 'ident' && tokens[i - 2].v === 'function') declared.add(tk.v);
            if (pv && pv.t === 'op' && pv.v === '#') keys.add(tk.v);
            if (nv && nv.t === 'op' && nv.v === ':') keys.add(tk.v);
            if (ctx === 'object'){
                if (pv && pv.t === 'op' && (pv.v === '{' || pv.v === ',')) keys.add(tk.v);
                if (nv && nv.t === 'op' && nv.v === '(') keys.add(tk.v);
            }
            if (pv && pv.t === 'ident' && (pv.v === 'export' || pv.v === 'default')) keys.add(tk.v);
            if (pv && pv.t === 'ident' && pv.v === 'function' && i >= 2 && tokens[i - 2].t === 'ident' && (tokens[i - 2].v === 'export' || tokens[i - 2].v === 'default')) keys.add(tk.v);
        } else if (tk.t === 'op'){
            if (tk.v === '{'){
                var obj = false;
                if (pv){
                    if (pv.t === 'op') obj = OBJECT_AFTER_OPS.has(pv.v);
                    else if (pv.t === 'ident') obj = OBJECT_AFTER_IDENTS.has(pv.v);
                }
                stack.push(obj ? 'object' : 'block');
            } else if (tk.v === '}'){
                stack.pop();
            }
        }
    }
    var map = {};
    for (var name of declared){
        if (!keys.has(name) && !KEYWORDS.has(name)){
            var nm = freshName(allIdents);
            map[name] = nm;
            allIdents.add(nm);
        }
    }
    return map;
}

function unq(lit){
    var body = lit.slice(1, -1);
    var out = '';
    for (var i = 0; i < body.length; i++){
        var c = body[i];
        if (c !== '\\'){ out += c; continue; }
        var e = body[i + 1];
        switch (e){
            case 'n': out += '\n'; i++; break;
            case 't': out += '\t'; i++; break;
            case 'r': out += '\r'; i++; break;
            case 'b': out += '\b'; i++; break;
            case 'f': out += '\f'; i++; break;
            case 'v': out += '\v'; i++; break;
            case '0': out += '\0'; i++; break;
            case 'x': out += String.fromCharCode(parseInt(body.substr(i + 2, 2), 16) || 0); i += 3; break;
            case 'u':
                if (body[i + 2] === '{'){
                    var e2 = body.indexOf('}', i + 3);
                    var cp = parseInt(body.slice(i + 3, e2 === -1 ? body.length : e2), 16);
                    out += String.fromCodePoint(isNaN(cp) ? 0 : cp);
                    i = e2 === -1 ? body.length : e2;
                } else {
                    out += String.fromCharCode(parseInt(body.substr(i + 2, 4), 16) || 0);
                    i += 5;
                }
                break;
            default: out += e === undefined ? '' : e; i++; break;
        }
    }
    return out;
}

function escStr(s){
    var out = '"';
    for (var i = 0; i < s.length; i++){
        var c = s.charCodeAt(i);
        if (c === 34) out += '\\"';
        else if (c === 92) out += '\\\\';
        else if (c === 10) out += '\\n';
        else if (c === 13) out += '\\r';
        else if (c === 9) out += '\\t';
        else if (c === 8) out += '\\b';
        else if (c === 12) out += '\\f';
        else if (c < 32 || c === 127) out += '\\x' + hex2(c);
        else if (c > 126) out += '\\u' + hex4(c);
        else out += s[i];
    }
    return out + '"';
}

function hexEncode(s, key){
    var out = '';
    for (var i = 0; i < s.length; i++) out += hex4(s.charCodeAt(i) ^ key);
    return out;
}

function decoderPrelude(dec, avoid){
    var n1 = freshName(avoid);
    return 'var ' + n1 + '=function(k,h){var s="",i=0;for(;i<h.length;i+=4)s+=String.fromCharCode(parseInt(h.substr(i,4),16)^k);return s};';
}

function junkBlock(avoid){
    var out = '';
    for (var i = 0; i < 3; i++){
        var fn = freshName(avoid), a = freshName(avoid), b = freshName(avoid);
        out += 'function ' + fn + '(' + a + ',' + b + '){var ' + a + '=' + b + '+0x1;if(!' + a + '){return 0x2}return ' + a + ';}\n';
    }
    var jv = freshName(avoid);
    out += 'if(!1){var ' + jv + '="' + randStr(10) + '";' + jv + '=0x' + Math.floor(Math.random() * 4096).toString(16) + ';}\n';
    return out;
}

function antiDebugBlock(avoid){
    var t = freshName(avoid), iv = freshName(avoid);
    return '(function(){var ' + t + '=0,' + iv + '=setInterval(function(){if(++' + t + '>0x8){clearInterval(' + iv + ');return}debugger;},0xfa);})();\n';
}

function wrapVM(code, avoid){
    var key = 16 + Math.floor(Math.random() * 240);
    var hex = hexEncode(code, key);
    var n1 = freshName(avoid), n2 = freshName(avoid), n3 = freshName(avoid);
    return '(function(){var ' + n1 + '="' + hex + '",' + n2 + '=function(k,h){var s="",i=0;for(;i<h.length;i+=4)s+=String.fromCharCode(parseInt(h.substr(i,4),16)^k);return s},' + n3 + '=' + n2 + '(' + key + ',' + n1 + ');try{Function(' + n3 + ')()}catch(e){eval(' + n3 + ')}})();';
}

function collectIdents(tokens){
    var set = new Set();
    for (var i = 0; i < tokens.length; i++) if (tokens[i].t === 'ident') set.add(tokens[i].v);
    return set;
}

function obfuscateJS(src, opts){
    var warnings = [];
    if (opts.decoder && !opts.strings) opts = Object.assign({}, opts, { strings: true });
    var tokens = tokenize(src);
    var strict = tokens.length > 0 && tokens[0].t === 'str' && /^['"]use strict['"]$/.test(tokens[0].v);
    var names = {};
    if (opts.rename) names = collectRenamePlan(tokens);
    var code = rebuild(tokens, names);
    var avoid = collectIdents(tokens);
    var pre = '';
    var dec = null;
    if (opts.decoder){
        var key = 16 + Math.floor(Math.random() * 240);
        dec = { key: key, count: 0 };
    }
    tokens = tokenize(code);
    var out = [];
    var prevTok = null;
    for (var i = 0; i < tokens.length; i++){
        var tk = tokens[i];
        if (tk.t === 'str'){
            var skip = prevTok && prevTok.t === 'ident' && (prevTok.v === 'import' || prevTok.v === 'export' || prevTok.v === 'from');
            var isStrict = /^['"]use strict['"]$/.test(tk.v);
            if (skip || isStrict || !opts.strings){
                out.push(tk);
            } else if (dec){
                dec.count++;
                out.push({ t: 'call', v: 'xx(' + dec.key + ',"' + hexEncode(unq(tk.v), dec.key) + '")' });
            } else {
                out.push({ t: 'str', v: escStr(unq(tk.v)) });
            }
        } else if (tk.t === 'num' && opts.numbers){
            var num = Number(tk.v.replace(/_/g, ''));
            if (Number.isFinite(num) && Number.isInteger(num)){
                out.push({ t: 'num', v: '0x' + (num < 0 ? -num : num).toString(16) });
            } else {
                out.push(tk);
            }
        } else {
            out.push(tk);
        }
        prevTok = tk;
    }
    var prelude = '';
    if (dec && dec.count > 0){
        var dn = freshName(avoid);
        prelude = 'var ' + dn + '=function(k,h){var s="",i=0;for(;i<h.length;i+=4)s+=String.fromCharCode(parseInt(h.substr(i,4),16)^k);return s};\n';
        for (var j = 0; j < out.length; j++){
            if (out[j].t === 'call') out[j].v = out[j].v.replace('xx(', dn + '(');
        }
    }
    code = rebuild(out, null);
    if (prelude !== '') pre += prelude;
    if (opts.junk) pre += junkBlock(avoid);
    if (opts.antiDebug) pre += antiDebugBlock(avoid);
    if (pre !== '') code = pre + code;
    if (strict) code = "'use strict';\n" + code;
    if (opts.vm) code = wrapVM(code, avoid);
    if (opts.rename) warnings.push('Variables/functions renamed — inline event handlers (onclick="...") are not renamed, so test the output if you use them.');
    if (opts.vm) warnings.push('The VM wrapper runs code in its own scope and needs eval (unsafe-eval) — scripts that share globals with other scripts on the page will break.');
    return { code: code, warnings: warnings };
}

function htmlMinify(html){
    var raw = new Set(['script','style','pre','textarea','xmp','iframe','noembed','noframes']);
    var out = '';
    var i = 0, n = html.length;
    while (i < n){
        var lt = html.indexOf('<', i);
        if (lt === -1){ out += html.slice(i).replace(/[ \t\r\n]+/g, ' '); break; }
        if (lt > i) out += html.slice(i, lt).replace(/[ \t\r\n]+/g, ' ');
        if (html.substr(lt, 4) === '<!--'){
            if (html.substr(lt, 9) === '<!--[if'){
                var ce = html.indexOf('-->', lt);
                ce = ce === -1 ? n : ce + 3;
                out += html.slice(lt, ce);
                i = ce;
            } else {
                var ce2 = html.indexOf('-->', lt);
                i = ce2 === -1 ? n : ce2 + 3;
            }
            continue;
        }
        var gt = html.indexOf('>', lt);
        if (gt === -1){ out += html.slice(lt); break; }
        var tag = html.slice(lt + 1, gt).trim();
        var m = /^[a-zA-Z0-9]+/.exec(tag);
        var name = m ? m[0].toLowerCase() : '';
        var closing = tag[0] === '/';
        var selfClose = /\/\s*$/.test(tag);
        out += html.slice(lt, gt + 1);
        i = gt + 1;
        if (raw.has(name) && !closing && !selfClose){
            var re = new RegExp('</' + name + '\\s*>', 'i');
            var mm = re.exec(html.slice(i));
            if (mm){
                var endPos = i + mm.index + mm[0].length;
                out += html.slice(i, endPos);
                i = endPos;
            } else {
                out += html.slice(i);
                i = n;
            }
        } else {
            while (i < n && /\s/.test(html[i])) i++;
        }
    }
    return out;
}

function obfuscateInlineScripts(html, opts){
    var re = /<script\b([^>]*)>([\s\S]*?)<\/script\s*>/gi;
    return html.replace(re, function(all, attrs, body){
        if (/\bsrc\s*=/.test(attrs)) return all;
        var r = obfuscateJS(body, Object.assign({}, opts, { vm: false }));
        return '<script' + attrs + '>' + r.code + '</script>';
    });
}

function obfuscateHTML(html, opts){
    var warnings = [];
    html = htmlMinify(html);
    if (opts.scripts) html = obfuscateInlineScripts(html, opts);
    var avoid = new Set();
    var key = 16 + Math.floor(Math.random() * 240);
    var hex = hexEncode(html, key);
    var n1 = freshName(avoid), n2 = freshName(avoid);
    var js = 'var ' + n1 + '="' + hex + '";var ' + n2 + '=function(k,h){var s="",i=0;for(;i<h.length;i+=4)s+=String.fromCharCode(parseInt(h.substr(i,4),16)^k);return s};document.write(' + n2 + '(' + key + ',' + n1 + '));';
    if (opts.vm) js = wrapVM(js, avoid);
    if (opts.vm) warnings.push('The VM wrapper needs eval (unsafe-eval) in your CSP.');
    return { code: '<script>' + js + '</script>', warnings: warnings };
}

var LEVELS = [
    null,
    { strings: false, numbers: false, decoder: false, rename: false, junk: false, antiDebug: false, vm: false, scripts: false },
    { strings: true,  numbers: true,  decoder: false, rename: false, junk: false, antiDebug: false, vm: false, scripts: false },
    { strings: true,  numbers: true,  decoder: true,  rename: false, junk: false, antiDebug: false, vm: false, scripts: true },
    { strings: true,  numbers: true,  decoder: true,  rename: true,  junk: false, antiDebug: false, vm: false, scripts: true },
    { strings: true,  numbers: true,  decoder: true,  rename: true,  junk: false, antiDebug: false, vm: true,  scripts: true },
    { strings: true,  numbers: true,  decoder: true,  rename: true,  junk: true,  antiDebug: true,  vm: true,  scripts: true }
];
var CHECKS = ['opt-strings','opt-numbers','opt-decoder','opt-rename','opt-junk','opt-antidebug','opt-vm','opt-scripts'];

function $(id){ return document.getElementById(id); }
function levelOpts(){
    return {
        strings: $('opt-strings').checked,
        numbers: $('opt-numbers').checked,
        decoder: $('opt-decoder').checked,
        rename: $('opt-rename').checked,
        junk: $('opt-junk').checked,
        antiDebug: $('opt-antidebug').checked,
        vm: $('opt-vm').checked,
        scripts: $('opt-scripts').checked
    };
}
function applyLevel(){
    var lv = LEVELS[parseInt($('level').value, 10)];
    $('opt-strings').checked = lv.strings;
    $('opt-numbers').checked = lv.numbers;
    $('opt-decoder').checked = lv.decoder;
    $('opt-rename').checked = lv.rename;
    $('opt-junk').checked = lv.junk;
    $('opt-antidebug').checked = lv.antiDebug;
    $('opt-vm').checked = lv.vm;
    $('opt-scripts').checked = lv.scripts;
}
function doObfuscate(){
    var mode = $('html-tab').classList.contains('active') ? 'html' : 'js';
    var src = mode === 'html' ? $('in-html').value : $('in-js').value;
    if (!src.trim()){ $('out').value = ''; $('warnings').innerHTML = ''; return; }
    var res = mode === 'html' ? obfuscateHTML(src, levelOpts()) : obfuscateJS(src, levelOpts());
    $('out').value = res.code;
    var w = '';
    res.warnings.forEach(function(x){ w += '<div class="alert alert-warning py-2 px-3 small mb-2">⚠️ ' + x + '</div>'; });
    $('warnings').innerHTML = w;
}
function copyOut(){
    var t = $('out');
    t.select();
    t.setSelectionRange(0, 99999999);
    document.execCommand('copy');
}
function downloadOut(){
    var t = $('out').value;
    if (!t) return;
    var a = document.createElement('a');
    a.href = 'data:text/plain;charset=utf-8,' + encodeURIComponent(t);
    a.download = 'obfuscated.js';
    a.click();
}

if (typeof document !== 'undefined'){
    var tabs = document.querySelectorAll('#obf-tabs .nav-link');
    function showTab(name){
        $('in-js').classList.toggle('d-none', name !== 'js');
        $('in-html').classList.toggle('d-none', name !== 'html');
        tabs.forEach(function (t){ t.classList.toggle('active', t.getAttribute('data-tab') === name); });
    }
    tabs.forEach(function (t){
        t.addEventListener('click', function (){
            showTab(t.getAttribute('data-tab'));
        });
    });
    $('level').addEventListener('change', applyLevel);
    CHECKS.forEach(function (id){
        $(id).addEventListener('change', function (){
            $('level').value = '-1';
        });
    });
    applyLevel();
    window.htmlTab = null;
}
</script>
<?php page_footer(); ?>
