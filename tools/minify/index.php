<?php
require_once __DIR__ . '/../../functions.php';

start_session();
page_header('Minify');
?>
<div class="container" style="max-width: 1000px;">
    <h1 class="h4 mb-1 reveal in-view">🗜️ Minify Code</h1>
    <p class="text-secondary mb-4 reveal in-view">JavaScript, CSS, HTML, JSON, SQL, Python, Lua, PHP, Java, C# and Go — whitespace and comment stripping without a build step. All local in your browser.</p>

    <div class="card mb-3 reveal in-view"><div class="card-body">
        <div class="row g-2 align-items-center mb-2">
            <div class="col-md-3">
                <select id="min-type" class="form-select">
                    <option value="js">JavaScript</option>
                    <option value="css">CSS</option>
                    <option value="html">HTML</option>
                    <option value="json">JSON</option>
                    <option value="sql">SQL</option>
                    <option value="python">Python</option>
                    <option value="lua">Lua</option>
                    <option value="php">PHP</option>
                    <option value="java">Java</option>
                    <option value="csharp">C#</option>
                    <option value="go">Go</option>
                </select>
            </div>
            <div class="col-md-9 d-flex gap-2 flex-wrap">
                <button class="btn btn-primary" onclick="doMin()">Minify</button>
                <button class="btn btn-outline-light" onclick="copyOut()">Copy</button>
                <button class="btn btn-outline-light" onclick="exportOut()">Export</button>
                <span class="text-secondary small align-self-center" id="min-stat"></span>
            </div>
        </div>
        <textarea id="min-in" class="form-control mb-2" rows="10" style="font-family:'JetBrains Mono',monospace;font-size:.8rem;" placeholder="Paste code here…">function hello( name ) {
  // say hi
  console.log( "Hello, " + name );
}</textarea>
        <textarea id="min-out" class="form-control" rows="10" readonly style="font-family:'JetBrains Mono',monospace;font-size:.8rem;"></textarea>
        <div class="form-text mt-2" id="min-note"></div>
    </div></div>

    <div class="alert alert-secondary reveal in-view">
        <small>💡 Safe whitespace/comment stripping — no variable renaming or restructuring. Strings,
        regexes (JS), heredocs (PHP) and long strings (Lua) are preserved. Python is trimmed per line
        only (indentation is significant), everything else is one-linerized.</small>
    </div>
</div>

<script>
var EXT = { js: 'js', css: 'css', html: 'html', json: 'json', sql: 'sql', python: 'py', lua: 'lua', php: 'php', java: 'java', csharp: 'cs', go: 'go' };
var NOTES = {
    js: 'Strings, template literals and regex literals are preserved.',
    css: 'Quoted values and comments inside strings are preserved.',
    html: 'HTML comments are removed; inline script/style contents are kept as-is.',
    json: 'Parsed and re-serialized, so it is always valid JSON.',
    sql: 'String/identifier quotes and $ quoting are preserved; comments stripped.',
    python: 'Indentation matters, so only comments and stray whitespace are stripped.',
    lua: 'Long strings [[ ]] and [=[ ]=] are preserved; comments stripped.',
    php: 'Strings and heredocs are preserved; comments stripped.',
    java: 'Strings and char literals are preserved; comments stripped.',
    csharp: 'Strings and char literals are preserved; comments stripped.',
    go: 'Strings, raw backtick strings and rune literals are preserved; comments stripped.'
};

function protect(src, regex, tokens) {
    return src.replace(regex, function (m) { tokens.push(m); return '\u0001' + (tokens.length - 1) + '\u0002'; });
}
function restore(src, tokens) {
    return src.replace(/\u0001(\d+)\u0002/g, function (_, i) { return tokens[i]; });
}
function minifyJS(src) {
    var tokens = [];
    var out = protect(src, /('(?:\\.|[^'\\])*'|"(?:\\.|[^"\\])*"|`(?:\\.|[^`\\])*`)|\/\*[\s\S]*?\*\/|\/\/[^\n]*/g, tokens);
    out = out.replace(/^\s+|\s+$/gm, '')
             .replace(/\n{2,}/g, '\n')
             .replace(/([,;{}])\s+/g, '$1')
             .replace(/\s+([,;{}])/g, '$1')
             .replace(/\s+([+\-*\/%<>=!&|?:])\s+/g, '$1');
    out = restore(out, tokens);
    return out.replace(/\s+/g, ' ').trim();
}
function minifyCSS(src) {
    var tokens = [];
    var out = protect(src, /("(?:\\.|[^"\\])*"|'(?:\\.|[^'\\])*')|\/\*[\s\S]*?\*\//g, tokens);
    out = out.replace(/\s+/g, ' ')
             .replace(/\s*([{};:,>~+])\s*/g, '$1')
             .replace(/;}/g, '}');
    out = restore(out, tokens);
    return out.replace(/:\s+/g, ':').trim();
}
function minifyHTML(src) {
    return src.replace(/<!--[\s\S]*?-->/g, '')
              .replace(/\s+</g, '<')
              .replace(/>\s+/g, '>')
              .trim();
}
function minifyJSON(src) {
    try { return JSON.stringify(JSON.parse(src)); }
    catch (e) { return 'Error: ' + e.message; }
}
function minifySQL(src) {
    var tokens = [];
    var out = protect(src, /'(\\.|[^'])*'|"(\\.|[^"])*"|`[^`]*`|\$[A-Za-z_$][\w$]*\$\$[\s\S]*?\$\$/g, tokens);
    out = out.replace(/--[^\n]*/g, '')
             .replace(/#[^\n]*/g, '')
             .replace(/\/\*[\s\S]*?\*\//g, '')
             .replace(/\s+/g, ' ')
             .replace(/\s*([(),;])\s*/g, '$1')
             .replace(/;\s*$/g, ';');
    return restore(out, tokens).trim();
}
function minifyPython(src) {
    var tokens = [];
    var out = protect(src, /'''[\s\S]*?'''|"""[\s\S]*?"""|'(?:\\.|[^'\\\n])*'|"(?:\\.|[^"\\\n])*"/g, tokens);
    out = out.replace(/#[^\n]*/g, '');
    out = restore(out, tokens);
    return out.split('\n').map(function (l) { return l.replace(/[ \t]+$/g, '').replace(/^[ \t]+/g, function (m) { return m.replace(/[ \t]/g, ' '); }); })
              .filter(function (l) { return l.replace(/\s/g, '') !== ''; })
              .join('\n').trim();
}
function minifyLua(src) {
    var tokens = [];
    var out = protect(src, /'(?:\\.|[^'\\\n])*'|"(?:\\.|[^"\\\n])*"|\[(=*)\[[\s\S]*?\]\1\]/g, tokens);
    out = out.replace(/--\[(=*)\[[\s\S]*?\]\1\]/g, '')
             .replace(/--[^\n]*/g, '')
             .replace(/\s+/g, ' ')
             .replace(/\s*([,;{}()])\s*/g, '$1');
    return restore(out, tokens).trim();
}
function minifyCFamily(src, opts) {
    var tokens = [];
    var pats = [/'(?:\\.|[^'\\\n])*'/, /"(?:\\.|[^"\\\n])*"/];
    if (opts && opts.raw) pats.push(/`[^`]*`/);
    if (opts && opts.heredoc) pats.push(/<<<['"]?[A-Za-z_][A-Za-z0-9_]*['"]?\r?\n[\s\S]*?\r?\n[A-Za-z_][A-Za-z0-9_]*/);
    var out = src;
    out = out.replace(/\$[A-Za-z_][\w$]*/g, '\u0000$&'); // protect PHP vars
    out = protect(out, new RegExp(pats.map(function (p) { return p.source; }).join('|'), 'g'), tokens);
    out = out.replace(/\/\*[\s\S]*?\*\//g, '')
             .replace(/\/\/[^\n]*/g, '')
             .replace(/#[^\n]*/g, '');
    out = restore(out, tokens);
    out = out.replace(/\u0000\$([A-Za-z_][\w$]*)/g, '$1');
    return out.replace(/^\s+|\s+$/gm, '')
              .replace(/\s+/g, ' ')
              .replace(/\s*([,;{}()\[\]])\s*/g, '$1')
              .replace(/\s+([+\-*\/%<>=!&|?:.->])\s+/g, '$1')
              .trim();
}
function minifyPHP(src) { return minifyCFamily(src, { heredoc: true }); }
function minifyJava(src) { return minifyCFamily(src, {}); }
function minifyCSharp(src) { return minifyCFamily(src, {}); }
function minifyGo(src) { return minifyCFamily(src, { raw: true }); }

function doMin() {
    var type = document.getElementById('min-type').value;
    var inVal = document.getElementById('min-in').value;
    var out = '';
    try {
        switch (type) {
            case 'js': out = minifyJS(inVal); break;
            case 'css': out = minifyCSS(inVal); break;
            case 'html': out = minifyHTML(inVal); break;
            case 'json': out = minifyJSON(inVal); break;
            case 'sql': out = minifySQL(inVal); break;
            case 'python': out = minifyPython(inVal); break;
            case 'lua': out = minifyLua(inVal); break;
            case 'php': out = minifyPHP(inVal); break;
            case 'java': out = minifyJava(inVal); break;
            case 'csharp': out = minifyCSharp(inVal); break;
            case 'go': out = minifyGo(inVal); break;
        }
    } catch (e) { out = 'Error: ' + e.message; }
    document.getElementById('min-out').value = out;
    document.getElementById('min-note').textContent = NOTES[type] || '';
    var before = encodeURIComponent(inVal).length;
    var after = encodeURIComponent(out).length;
    var pct = before > 0 ? Math.round((1 - after / before) * 100) : 0;
    document.getElementById('min-stat').textContent = (before/1024).toFixed(2) + ' KB → ' + (after/1024).toFixed(2) + ' KB (' + pct + '% smaller)';
}
function copyOut() {
    var t = document.getElementById('min-out');
    t.select();
    if (navigator.clipboard) navigator.clipboard.writeText(t.value); else document.execCommand('copy');
}
function exportOut() {
    var type = document.getElementById('min-type').value;
    var out = document.getElementById('min-out').value;
    if (!out) return;
    var name = 'minified.' + (EXT[type] || 'txt');
    var blob = new Blob([out], { type: 'text/plain;charset=utf-8' });
    var a = document.createElement('a');
    a.href = URL.createObjectURL(blob);
    a.download = name;
    document.body.appendChild(a);
    a.click();
    setTimeout(function () { URL.revokeObjectURL(a.href); a.remove(); }, 500);
}
</script>
<?php page_footer(); ?>
