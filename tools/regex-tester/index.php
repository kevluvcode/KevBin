<?php
require_once __DIR__ . '/../../functions.php';

start_session();
$GLOBALS['_meta'] = [
    'description' => 'Free online regex tester. Test ECMAScript regular expressions against live text with match highlighting, full group capture details, a flags cheat-sheet and a quick quantifier reference. 100% in your browser.',
    'keywords' => 'regex tester, regex playground, test regex, regular expression tester, regexp, pattern tester',
];
page_header('Regex Tester — Test & Debug Regular Expressions');
?>
<div class="container" style="max-width: 980px;">
    <h1 class="h4 mb-2 reveal in-view">Regex Tester</h1>
    <p class="text-secondary mb-1 reveal in-view">Paste a regular expression and some text, and watch every match light up instantly. See the full match, any capture groups, and the match positions — the fastest way to iterate on a pattern. Everything runs in your browser, so your text never leaves your machine.</p>
    <p class="text-secondary mb-4 reveal in-view">Regular expressions are the swiss-army knife of text processing: extracting emails, validating input, parsing logs, find-and-replace in one pass. This playground uses the ECMAScript engine (the same flavour as JavaScript and most modern tools), with support for flags like <code>i</code>, <code>g</code>, <code>m</code>, <code>s</code> and <code>u</code>.</p>

    <div class="card reveal in-view">
        <div class="card-body">
            <label class="form-label small text-secondary">Regular expression</label>
            <div class="input-group mb-2">
                <span class="input-group-text" style="font-family:'JetBrains Mono',monospace;">/</span>
                <input id="rgx-pattern" class="form-control" style="font-family:'JetBrains Mono',monospace;font-size:.9rem;" value="\b[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}\b" oninput="runRegex()">
                <span class="input-group-text" style="font-family:'JetBrains Mono',monospace;">/</span>
                <input id="rgx-flags" class="form-control" style="max-width:90px;font-family:'JetBrains Mono',monospace;font-size:.9rem;" value="gim" oninput="runRegex()">
            </div>
            <label class="form-label small text-secondary">Test text</label>
            <textarea id="rgx-text" class="form-control mb-2" rows="6" style="font-family:'JetBrains Mono',monospace;font-size:.85rem;" oninput="runRegex()">Contact support@kevbin.dev or sales@kevbin.io — both work 24/7!
Regex cats and US-ASCII text. domains: news.Yahoo.com vs news.yahoo.co</textarea>

            <div id="rgx-highlight" class="mb-2 p-2" style="border:1px solid var(--line);border-radius:8px;background:#0b0b0b;font-family:'JetBrains Mono',monospace;font-size:.85rem;white-space:pre-wrap;word-break:break-word;min-height:60px;"></div>

            <div class="form-text" id="rgx-info">—</div>

            <table class="table table-sm mt-3 align-middle">
                <thead><tr class="text-secondary"><th>#</th><th>Group</th><th>Match</th><th>Range</th></tr></thead>
                <tbody id="rgx-groups"></tbody>
            </table>

            <h3 class="h6 mt-3">Flag reference</h3>
            <table class="table table-sm align-middle">
                <tbody>
                    <tr><td><code>i</code></td><td class="text-secondary">case-insensitive</td><td><code>m</code></td><td class="text-secondary">^ and $ match line starts/ends</td></tr>
                    <tr><td><code>s</code></td><td class="text-secondary">. matches newlines too</td><td><code>u</code></td><td class="text-secondary">Unicode mode</td></tr>
                    <tr><td><code>g</code></td><td class="text-secondary">find all matches</td><td><code>y</code></td><td class="text-secondary">sticky (only from lastIndex)</td></tr>
                </tbody>
            </table>
            <h3 class="h6 mt-1">Common quantifiers</h3>
            <p class="text-secondary small mb-0"><code>*</code> 0+, <code>+</code> 1+, <code>?</code> 0–1, <code>{n}</code> exactly n, <code>{n,}</code> n+, <code>{n,m}</code> n–m; add <code>?</code> to any to make it lazy (<code>.*?</code>). Groups: <code>()</code> capture, <code>(?:)</code> non-capture, <code>(?&lt;name&gt;)</code> named.</p>
        </div>
    </div>
</div>

<script>
function $r(id) { return document.getElementById(id); }
function runRegex() {
    var pat = $r('rgx-pattern').value, flags = $r('rgx-flags').value;
    var text = $r('rgx-text').value, hl = $r('rgx-highlight'), info = $r('rgx-info'), gb = $r('rgx-groups');
    gb.innerHTML = ''; hl.innerHTML = '';
    try {
        var re = new RegExp(pat, flags);
        var out = '', last = 0, matches = 0;
        var lines = [];
        var m, emptyGuard = 0;
        while ((m = re.exec(text)) !== null) {
            out += escapeHtml(text.slice(last, m.index)) + '<mark style="background:#5865f2;color:#fff;border-radius:3px;padding:0 2px;">' + escapeHtml(m[0]) + '</mark>';
            last = m.index + m[0].length;
            matches++;
            var row = '<tr><td class="text-secondary">' + matches + '</td><td style="font-family:JetBrains Mono,monospace;font-size:.8rem;">' + (m.length > 1 ? Array.from({length:m.length-1}, function(_,i){return i+1;}).join(' | ') : '—') + '</td><td style="font-family:JetBrains Mono,monospace;font-size:.8rem;">' + escapeHtml(m[0]) + '</td><td class="text-secondary" style="font-family:JetBrains Mono,monospace;font-size:.8rem;">' + m.index + '</td></tr>';
            lines.push(row);
            if (m[0] === '') { re.lastIndex++; if (++emptyGuard > 100000) break; }
        }
        out += escapeHtml(text.slice(last));
        hl.innerHTML = out;
        gb.innerHTML = lines.join('');
        info.textContent = matches + ' match' + (matches !== 1 ? 'es' : '') + (matches ? ' · last match ends at index ' + last : '');
    } catch (e) {
        hl.innerHTML = '<span class="text-danger">' + escapeHtml(e.message) + '</span>';
        info.textContent = 'Invalid regular expression';
    }
}
function escapeHtml(s) { return s.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }
runRegex();
</script>
<?php page_footer(); ?>