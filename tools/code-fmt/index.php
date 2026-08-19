<?php
require_once __DIR__ . '/../../functions.php';

start_session();
$GLOBALS['_meta'] = [
    'description' => 'Free online CSS and JavaScript minifier and beautifier. Format or compress CSS and JS code instantly in your browser with zero uploads.',
    'keywords' => 'css minifier, js minifier, css beautifier, js beautifier, code formatter, minify css, minify javascript, format css, format javascript, online code tool',
];
page_header('CSS / JS Minifier & Beautifier — Format or Compress Code Instantly');
?>
<div class="container" style="max-width: 980px;">
    <h1 class="h4 mb-2 reveal in-view">CSS / JS Minifier & Beautifier</h1>
    <p class="text-secondary mb-1 reveal in-view">Paste any CSS or JavaScript code and instantly switch between readable, indented formatting and stripped-down minified output. Everything runs locally in your browser — nothing is uploaded.</p>
    <p class="text-secondary mb-4 reveal in-view">Beautify mode adds clean indentation so you can read and debug code quickly. Minify mode strips comments, whitespace and unnecessary characters to reduce file size for production. Statistics show you exactly how much space you save.</p>

    <div class="card reveal in-view">
        <div class="card-body">
            <div class="d-flex flex-wrap gap-2 align-items-center mb-2">
                <select id="cf-lang" class="form-select" style="max-width:140px;">
                    <option value="css">CSS</option>
                    <option value="js">JavaScript</option>
                </select>
                <div class="btn-group" role="group">
                    <input type="radio" class="btn-check" name="cf-mode" id="cf-mode-beautify" value="beautify" checked>
                    <label class="btn btn-outline-light btn-sm" for="cf-mode-beautify">Beautify</label>
                    <input type="radio" class="btn-check" name="cf-mode" id="cf-mode-minify" value="minify">
                    <label class="btn btn-outline-light btn-sm" for="cf-mode-minify">Minify</label>
                </div>
                <button class="btn btn-primary btn-sm" onclick="cfProcess()">Process</button>
                <button class="btn btn-outline-light btn-sm" onclick="cfCopy()">Copy</button>
                <button class="btn btn-outline-light btn-sm" onclick="cfClear()">Clear</button>
            </div>

            <label class="form-label small text-secondary">Input Code</label>
            <textarea id="cf-in" class="form-control mb-2" rows="10" style="font-family:'JetBrains Mono',monospace;font-size:.8rem;" placeholder="Paste CSS or JavaScript here…"></textarea>

            <label class="form-label small text-secondary">Output</label>
            <textarea id="cf-out" class="form-control" rows="10" readonly style="font-family:'JetBrains Mono',monospace;font-size:.8rem;"></textarea>

            <div class="d-flex gap-4 mt-2" id="cf-stats" style="display:none;">
                <small class="text-secondary">Original: <strong id="cf-stat-orig">0</strong> bytes</small>
                <small class="text-secondary">Processed: <strong id="cf-stat-new">0</strong> bytes</small>
                <small class="text-secondary">Savings: <strong id="cf-stat-pct">0%</strong></small>
            </div>
            <div id="cf-msg" class="form-text mt-2"></div>
        </div>
    </div>

    <h2 class="h6 mt-4 reveal in-view" style="border-bottom:1px solid var(--line);padding-bottom:.5rem;">CSS Beautifier</h2>
    <p class="text-secondary small reveal in-view">Adds newlines after opening braces and before closing braces, indents with 2 spaces, puts each property on its own line, keeps selectors on their own line, handles media queries and @keyframes properly, and removes empty rules.</p>

    <h2 class="h6 mt-4 reveal in-view" style="border-bottom:1px solid var(--line);padding-bottom:.5rem;">CSS Minifier</h2>
    <p class="text-secondary small reveal in-view">Strips comments, collapses whitespace, removes trailing semicolons before closing braces, shortens hex color codes like #ffffff to #fff, removes empty rules, and consolidates duplicate media queries.</p>

    <h2 class="h6 mt-4 reveal in-view" style="border-bottom:1px solid var(--line);padding-bottom:.5rem;">JavaScript Beautifier</h2>
    <p class="text-secondary small reveal in-view">Adds proper 2-space indentation, newlines after opening braces and before closing braces, handles for/while loops, if/else blocks, function declarations, arrow functions, try/catch/finally, and never breaks inside string literals.</p>

    <h2 class="h6 mt-4 reveal in-view" style="border-bottom:1px solid var(--line);padding-bottom:.5rem;">JavaScript Minifier</h2>
    <p class="text-secondary small reveal in-view">Removes single-line and multi-line comments while preserving URLs inside strings, collapses whitespace (keeping spaces needed for word separation), and correctly handles string literals and regex literals.</p>
</div>

<script>
function cf$(id) { return document.getElementById(id); }
function cfRadio() { return document.querySelector('input[name="cf-mode"]:checked').value; }

function cfProcess() {
    var lang = cf$('cf-lang').value;
    var mode = cfRadio();
    var src = cf$('cf-in').value;
    if (!src) { cf$('cf-out').value = ''; cf$('cf-msg').textContent = ''; cf$('cf-stats').style.display = 'none'; return; }
    var out = '';
    try {
        if (lang === 'css' && mode === 'beautify') out = cfBeautifyCSS(src);
        else if (lang === 'css' && mode === 'minify') out = cfMinifyCSS(src);
        else if (lang === 'js' && mode === 'beautify') out = cfBeautifyJS(src);
        else if (lang === 'js' && mode === 'minify') out = cfMinifyJS(src);
    } catch (e) { out = ''; cf$('cf-msg').textContent = 'Error: ' + e.message; cf$('cf-msg').className = 'form-text mt-2 text-danger'; }
    cf$('cf-out').value = out;
    var origBytes = new TextEncoder().encode(src).length;
    var newBytes = new TextEncoder().encode(out).length;
    var pct = origBytes > 0 ? Math.round((1 - newBytes / origBytes) * 100) : 0;
    cf$('cf-stat-orig').textContent = origBytes.toLocaleString();
    cf$('cf-stat-new').textContent = newBytes.toLocaleString();
    cf$('cf-stat-pct').textContent = (pct >= 0 ? pct : 0) + '%';
    cf$('cf-stats').style.display = 'flex';
    cf$('cf-msg').textContent = '';
}

function cfCopy() {
    var t = cf$('cf-out');
    t.select();
    if (navigator.clipboard) navigator.clipboard.writeText(t.value); else document.execCommand('copy');
}

function cfClear() {
    cf$('cf-in').value = '';
    cf$('cf-out').value = '';
    cf$('cf-msg').textContent = '';
    cf$('cf-stats').style.display = 'none';
}

function cfProtect(src, regex, tokens) {
    return src.replace(regex, function (m) { tokens.push(m); return '\u0001' + (tokens.length - 1) + '\u0002'; });
}
function cfRestore(src, tokens) {
    return src.replace(/\u0001(\d+)\u0002/g, function (_, i) { return tokens[i]; });
}

function cfMinifyCSS(src) {
    var tokens = [];
    var out = cfProtect(src, /("(?:\\.|[^"\\])*"|'(?:\\.|[^'\\])*')/g, tokens);
    out = out.replace(/\/\*[\s\S]*?\*\//g, '');
    out = out.replace(/\s+/g, ' ');
    out = out.replace(/\s*([{};:>,+~])\s*/g, '$1');
    out = out.replace(/;\}/g, '}');
    out = out.replace(/;\s*$/g, '');
    out = out.replace(/#([0-9a-fA-F])\1([0-9a-fA-F])\2([0-9a-fA-F])\3(?:([0-9a-fA-F])\4)?/g, function (_, r, g, b, a) {
        return '#' + r + g + b + (a !== undefined ? a : '');
    });
    out = out.replace(/\{([^{}]*)\}/g, function (m, inner) {
        if (inner.trim() === '') return '';
        return '{' + inner + '}';
    });
    var mediaBlocks = [];
    out = out.replace(/@media[^{]*\{([^{}]*(?:\{[^{}]*\}[^{}]*)*)\}/g, function (m) { mediaBlocks.push(m); return ''; });
    mediaBlocks.sort();
    var deduped = [];
    for (var i = 0; i < mediaBlocks.length; i++) {
        if (i === 0 || mediaBlocks[i] !== mediaBlocks[i - 1]) deduped.push(mediaBlocks[i]);
    }
    out = deduped.join('') + out;
    out = cfRestore(out, tokens);
    return out.trim();
}

function cfBeautifyCSS(src) {
    var tokens = [];
    var out = cfProtect(src, /("(?:\\.|[^"\\])*"|'(?:\\.|[^'\\])*')/g, tokens);
    out = out.replace(/\/\*[\s\S]*?\*\//g, '');
    out = out.replace(/\s+/g, ' ').trim();
    out = out.replace(/\s*([{}])\s*/g, ' $1 ');
    var lines = out.split(/\s+\{\s+/);
    out = lines.join(' {');
    var result = '';
    var indent = 0;
    var blocks = out.replace(/\s*{\s*/g, ' { ').replace(/\s*}\s*/g, ' } ').trim();
    var parts = blocks.split(/\s+/);
    var insideBlock = false;
    var selectors = [];
    var currentSelector = '';
    for (var i = 0; i < parts.length; i++) {
        var p = parts[i];
        if (p === '{') {
            result += ' {\n';
            indent++;
            insideBlock = true;
        } else if (p === '}') {
            if (result.length > 0 && result.charAt(result.length - 1) !== '\n') result += '\n';
            indent--;
            result += '  '.repeat(indent < 0 ? 0 : indent) + '}\n';
            insideBlock = false;
        } else if (insideBlock) {
            result += '  '.repeat(indent) + p + '\n';
        } else {
            if (p.charAt(p.length - 1) === ',') {
                currentSelector += p + ' ';
            } else {
                currentSelector += p;
                result += '  '.repeat(indent) + currentSelector + '\n';
                currentSelector = '';
            }
        }
    }
    out = cfRestore(result, tokens);
    out = out.replace(/\{(\s*)\}/g, '');
    out = out.replace(/\n{3,}/g, '\n\n');
    return out.trim();
}

function cfMinifyJS(src) {
    var tokens = [];
    var out = cfProtect(src, /('(?:\\.|[^'\\])*'|"(?:\\.|[^"\\])*"|`(?:\\.|[^`\\])*`)|\/\*[\s\S]*?\*\/|\/\/[^\n]*/g, tokens);
    out = out.replace(/^\s+|\s+$/gm, '');
    out = out.replace(/\n{2,}/g, '\n');
    out = out.replace(/([,;{}()\[\]])\s+/g, '$1');
    out = out.replace(/\s+([,;{}()\[\]])/g, '$1');
    out = out.replace(/\s+([+\-*\/%<>=!&|?:])\s+/g, '$1');
    out = out.replace(/\s+([+\-*\/%<>=!&|?:])(\S)/g, '$1$2');
    out = out.replace(/(\S)\s+([+\-*\/%<>=!&|?:])/g, '$1$2');
    out = cfRestore(out, tokens);
    return out.replace(/\s+/g, ' ').trim();
}

function cfBeautifyJS(src) {
    var tokens = [];
    var out = cfProtect(src, /('(?:\\.|[^'\\])*'|"(?:\\.|[^"\\])*"|`(?:\\.|[^`\\])*`)|\/\*[\s\S]*?\*\/|\/\/[^\n]*/g, tokens);
    var indent = 0;
    var result = '';
    var lines = out.split('\n');
    var isStr = false;
    for (var li = 0; li < lines.length; li++) {
        var line = lines[li].replace(/^\s+/, '').replace(/\s+$/, '');
        if (!line) continue;
        var opens = 0;
        var closes = 0;
        var inStr = false;
        var strCh = '';
        for (var ci = 0; ci < line.length; ci++) {
            var ch = line.charAt(ci);
            if (inStr) {
                if (ch === '\\') { ci++; continue; }
                if (ch === strCh) inStr = false;
            } else {
                if (ch === '"' || ch === "'" || ch === '`') { inStr = true; strCh = ch; continue; }
                if (ch === '/' && ci + 1 < line.length && line.charAt(ci + 1) === '/') break;
                if (ch === '{') opens++;
                if (ch === '}') closes++;
            }
        }
        if (closes > 0) {
            indent -= closes;
            if (indent < 0) indent = 0;
        }
        result += '  '.repeat(indent) + line + '\n';
        if (opens > 0) indent += opens;
    }
    out = cfRestore(result, tokens);
    return out.trim();
}

function cfUpdateModeLabel() {
    var mode = cfRadio();
    var lang = cf$('cf-lang').value;
    var label = mode === 'beautify' ? 'Beautified' : 'Minified';
    var langLabel = lang === 'css' ? 'CSS' : 'JavaScript';
    var statsNew = cf$('cf-stat-new');
    if (statsNew) statsNew.parentElement.querySelector('small').firstChild.textContent = label + ' ' + langLabel + ': ';
    var statsOrig = cf$('cf-stat-orig');
    if (statsOrig) statsOrig.parentElement.querySelector('small').firstChild.textContent = 'Original: ';
}

document.querySelectorAll('input[name="cf-mode"]').forEach(function (el) { el.addEventListener('change', cfUpdateModeLabel); });
cf$('cf-lang').addEventListener('change', cfUpdateModeLabel);
</script>
<?php page_footer(); ?>
