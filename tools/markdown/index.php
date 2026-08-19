<?php
require_once __DIR__ . '/../../functions.php';

start_session();
$GLOBALS['_meta'] = [
    'description' => 'Free online Markdown Live Preview editor. Write Markdown on the left and see the rendered HTML instantly on the right. Supports headers, bold, italic, code, tables, lists, links, images and more. 100% client-side.',
    'keywords' => 'markdown preview, markdown editor, live markdown, markdown to html, markdown online',
];
page_header('Markdown Live Preview');
?>
<style>
#md-input {
    min-height: 520px;
    resize: vertical;
    font-family: 'JetBrains Mono', 'Fira Code', 'Consolas', monospace;
    font-size: .88rem;
    line-height: 1.6;
    background: #1a1d23;
    color: #e0e0e0;
    border: 1px solid #333;
}
#md-input:focus {
    background: #1e2128;
    border-color: #5865f2;
    color: #f0f0f0;
    box-shadow: 0 0 0 .2rem rgba(88,101,242,.25);
}
#md-toolbar .btn {
    font-size: .78rem;
    padding: .25rem .55rem;
    border-color: #444;
    color: #ccc;
    background: #2a2d35;
}
#md-toolbar .btn:hover {
    background: #3a3d45;
    color: #fff;
    border-color: #5865f2;
}
#md-preview {
    min-height: 520px;
    background: #1a1d23;
    border: 1px solid #333;
    border-radius: .375rem;
    padding: 1.25rem 1.5rem;
    overflow-y: auto;
    color: #e0e0e0;
    line-height: 1.7;
}
#md-preview h1 { font-size: 1.85rem; font-weight: 700; margin: 0 0 .8rem; padding-bottom: .4rem; border-bottom: 2px solid #5865f2; color: #f0f0f0; }
#md-preview h2 { font-size: 1.5rem; font-weight: 700; margin: 1.4rem 0 .6rem; padding-bottom: .35rem; border-bottom: 1px solid #444; color: #f0f0f0; }
#md-preview h3 { font-size: 1.25rem; font-weight: 600; margin: 1.2rem 0 .5rem; color: #f0f0f0; }
#md-preview h4 { font-size: 1.1rem; font-weight: 600; margin: 1rem 0 .4rem; color: #e8e8e8; }
#md-preview h5 { font-size: 1rem; font-weight: 600; margin: .9rem 0 .4rem; color: #e0e0e0; }
#md-preview h6 { font-size: .9rem; font-weight: 600; margin: .8rem 0 .4rem; color: #ccc; text-transform: uppercase; letter-spacing: .5px; }
#md-preview p { margin: 0 0 .8rem; }
#md-preview strong { color: #f5f5f5; font-weight: 700; }
#md-preview em { font-style: italic; }
#md-preview del { text-decoration: line-through; opacity: .7; }
#md-preview a { color: #5865f2; text-decoration: underline; text-underline-offset: 2px; }
#md-preview a:hover { color: #7b8ef5; }
#md-preview img { max-width: 100%; border-radius: 6px; margin: .5rem 0; }
#md-preview blockquote {
    margin: .8rem 0;
    padding: .6rem 1rem;
    border-left: 4px solid #5865f2;
    background: #22252d;
    border-radius: 0 6px 6px 0;
    color: #bbb;
}
#md-preview blockquote p:last-child { margin-bottom: 0; }
#md-preview code {
    font-family: 'JetBrains Mono', 'Fira Code', monospace;
    background: #2a2d35;
    padding: .15rem .4rem;
    border-radius: 4px;
    font-size: .88em;
    color: #e06c75;
}
#md-preview pre {
    margin: .8rem 0;
    padding: 1rem;
    background: #161820;
    border: 1px solid #333;
    border-radius: 8px;
    overflow-x: auto;
}
#md-preview pre code {
    background: none;
    padding: 0;
    color: #abb2bf;
    font-size: .85rem;
    line-height: 1.6;
}
#md-preview ul, #md-preview ol {
    margin: .5rem 0 .8rem 1.5rem;
    padding: 0;
}
#md-preview li { margin-bottom: .25rem; }
#md-preview hr {
    border: none;
    height: 2px;
    background: linear-gradient(90deg, transparent, #5865f2, transparent);
    margin: 1.5rem 0;
}
#md-preview table {
    width: 100%;
    border-collapse: collapse;
    margin: .8rem 0;
}
#md-preview th, #md-preview td {
    border: 1px solid #444;
    padding: .5rem .75rem;
    text-align: left;
}
#md-preview th {
    background: #22252d;
    font-weight: 600;
    color: #f0f0f0;
}
#md-preview tr:nth-child(even) td {
    background: #1e2128;
}
#md-preview tr:hover td {
    background: #262a33;
}
</style>

<div class="container-fluid" style="max-width: 1400px;">
    <h1 class="h4 mb-2 reveal in-view">Markdown Live Preview</h1>
    <p class="text-secondary mb-3 reveal in-view">Write Markdown on the left and see the rendered output instantly on the right. Supports headers, bold, italic, strikethrough, code, blockquotes, lists, links, images, tables, horizontal rules and more. Everything runs in your browser — nothing is uploaded.</p>

    <div id="md-toolbar" class="d-flex flex-wrap gap-1 mb-2 reveal in-view">
        <button class="btn btn-sm" onclick="mdInsert('**','**')" title="Bold"><strong>B</strong></button>
        <button class="btn btn-sm" onclick="mdInsert('*','*')" title="Italic"><em>I</em></button>
        <button class="btn btn-sm" onclick="mdInsert('~~','~~')" title="Strikethrough"><del>S</del></button>
        <button class="btn btn-sm" onclick="mdInsertLine('# ')" title="Heading">H1</button>
        <button class="btn btn-sm" onclick="mdInsertLine('## ')" title="Heading 2">H2</button>
        <button class="btn btn-sm" onclick="mdInsertLine('### ')" title="Heading 3">H3</button>
        <button class="btn btn-sm" onclick="mdInsert('[','](url)')" title="Link">Link</button>
        <button class="btn btn-sm" onclick="mdInsert('![alt](',')')" title="Image">Img</button>
        <button class="btn btn-sm" onclick="mdInsert('`','`')" title="Inline Code">&lt;/&gt;</button>
        <button class="btn btn-sm" onclick="mdInsertCodeBlock()" title="Code Block">```</button>
        <button class="btn btn-sm" onclick="mdInsertLine('> ')" title="Blockquote">Quote</button>
        <button class="btn btn-sm" onclick="mdInsertLine('- ')" title="Unordered List">List</button>
        <button class="btn btn-sm" onclick="mdInsertLine('1. ')" title="Ordered List">O-List</button>
        <button class="btn btn-sm" onclick="mdInsertTable()" title="Table">Table</button>
        <button class="btn btn-sm" onclick="mdInsertLine('---')" title="Horizontal Rule">HR</button>
        <span class="border-start border-secondary mx-1" style="height:24px;align-self:center;"></span>
        <button class="btn btn-sm" onclick="mdCopyMd()" title="Copy Markdown">Copy MD</button>
        <button class="btn btn-sm" onclick="mdCopyHtml()" title="Copy HTML">Copy HTML</button>
        <button class="btn btn-sm" onclick="mdClear()" title="Clear All">Clear</button>
    </div>

    <div class="row g-3 reveal in-view">
        <div class="col-md-6">
            <label class="form-label small text-secondary mb-1">Markdown</label>
            <textarea id="md-input" class="form-control" spellcheck="false" placeholder="Start writing Markdown here..."># Hello World

This is a **live** markdown *preview*.

## Features

- **Bold text** and __also bold__
- *Italic text* and _also italic_
- ~~Strikethrough~~
- `inline code` and code blocks

### Code Block

```javascript
function greet(name) {
    return "Hello, " + name + "!";
}
```

### Blockquote

> This is a blockquote.
> It can span multiple lines.

### Lists

1. First item
2. Second item
3. Third item

- Unordered item
- Another item
  - Nested item

### Table

| Feature | Status |
|---------|--------|
| Headers | Supported |
| Bold | Supported |
| Tables | Supported |

### Links & Images

[Visit Example](https://example.com)

![Placeholder](https://via.placeholder.com/150)

---

That's it!</textarea>
        </div>
        <div class="col-md-6">
            <label class="form-label small text-secondary mb-1">Preview</label>
            <div id="md-preview"></div>
        </div>
    </div>
</div>

<script>
(function() {
    var input = document.getElementById('md-input');
    var preview = document.getElementById('md-preview');

    function esc(s) {
        return s.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    function parseMarkdown(src) {
        var lines = src.replace(/\r\n/g, '\n').split('\n');
        var out = [];
        var i = 0;

        while (i < lines.length) {
            var line = lines[i];

            if (/^```/.test(line)) {
                var lang = line.slice(3).trim();
                var code = [];
                i++;
                while (i < lines.length && !/^```/.test(lines[i])) {
                    code.push(esc(lines[i]));
                    i++;
                }
                i++;
                var langAttr = lang ? ' class="language-' + esc(lang) + '"' : '';
                out.push('<pre><code' + langAttr + '>' + code.join('\n') + '</code></pre>');
                continue;
            }

            if (/^\|(.+)\|$/.test(line)) {
                var tableLines = [];
                while (i < lines.length && /^\|(.+)\|$/.test(lines[i])) {
                    tableLines.push(lines[i]);
                    i++;
                }
                if (tableLines.length >= 2) {
                    var headerCells = tableLines[0].split('|').filter(function(c) { return c.trim() !== ''; });
                    var bodyRows = [];
                    var sepIdx = 1;
                    var isAlignRow = /^\|[\s\-:|]+\|$/.test(tableLines[1]);
                    if (isAlignRow) sepIdx = 2;
                    for (var t = sepIdx; t < tableLines.length; t++) {
                        bodyRows.push(tableLines[t].split('|').filter(function(c) { return c.trim() !== ''; }));
                    }
                    out.push('<table><thead><tr>');
                    for (var h = 0; h < headerCells.length; h++) {
                        out.push('<th>' + parseInline(headerCells[h].trim()) + '</th>');
                    }
                    out.push('</tr></thead><tbody>');
                    for (var r = 0; r < bodyRows.length; r++) {
                        out.push('<tr>');
                        for (var c = 0; c < bodyRows[r].length; c++) {
                            out.push('<td>' + parseInline(bodyRows[r][c].trim()) + '</td>');
                        }
                        out.push('</tr>');
                    }
                    out.push('</tbody></table>');
                }
                continue;
            }

            if (/^#{1,6}\s/.test(line)) {
                var match = line.match(/^(#{1,6})\s+(.*)$/);
                if (match) {
                    var level = match[1].length;
                    out.push('<h' + level + '>' + parseInline(match[2]) + '</h' + level + '>');
                    i++;
                    continue;
                }
            }

            if (/^>\s?/.test(line)) {
                var quoteLines = [];
                while (i < lines.length && /^>\s?/.test(lines[i])) {
                    quoteLines.push(lines[i].replace(/^>\s?/, ''));
                    i++;
                }
                var quoteContent = parseBlockContent(quoteLines);
                out.push('<blockquote>' + quoteContent + '</blockquote>');
                continue;
            }

            if (/^(-{3,}|\*{3,}|_{3,})\s*$/.test(line)) {
                out.push('<hr>');
                i++;
                continue;
            }

            if (/^(\d+)\.\s/.test(line)) {
                var olItems = [];
                while (i < lines.length && /^(\d+)\.\s/.test(lines[i])) {
                    olItems.push(lines[i].replace(/^\d+\.\s/, ''));
                    i++;
                }
                out.push('<ol>');
                for (var oi = 0; oi < olItems.length; oi++) {
                    out.push('<li>' + parseInline(olItems[oi]) + '</li>');
                }
                out.push('</ol>');
                continue;
            }

            if (/^(\s*)[-*+]\s/.test(line)) {
                var ulItems = [];
                while (i < lines.length && /^(\s*)[-*+]\s/.test(lines[i])) {
                    var indent = lines[i].match(/^(\s*)/)[1].length;
                    var content = lines[i].replace(/^\s*[-*+]\s/, '');
                    ulItems.push({ text: content, indent: indent });
                    i++;
                }
                out.push(buildNestedList(ulItems, 0, 0));
                continue;
            }

            if (line.trim() === '') {
                i++;
                continue;
            }

            var paraLines = [];
            while (i < lines.length && lines[i].trim() !== '' && !/^#{1,6}\s/.test(lines[i]) && !/^>\s?/.test(lines[i]) && !/^```/.test(lines[i]) && !/^\|(.+)\|$/.test(lines[i]) && !/^(-{3,}|\*{3,}|_{3,})\s*$/.test(lines[i]) && !/^(\d+)\.\s/.test(lines[i]) && !/^(\s*)[-*+]\s/.test(lines[i])) {
                paraLines.push(lines[i]);
                i++;
            }
            if (paraLines.length > 0) {
                out.push('<p>' + parseInline(paraLines.join('<br>')) + '</p>');
            }
        }

        return out.join('\n');
    }

    function parseBlockContent(blockLines) {
        var inner = [];
        for (var j = 0; j < blockLines.length; j++) {
            if (blockLines[j].trim() === '') {
                inner.push('');
            } else {
                inner.push(parseInline(blockLines[j]));
            }
        }
        return inner.map(function(l) {
            if (l === '') return '<br>';
            return '<p>' + l + '</p>';
        }).join('');
    }

    function buildNestedList(items, startIdx, baseIndent) {
        if (startIdx >= items.length) return '';
        var tag = /^\d/.test(items[startIdx].text) ? 'ol' : 'ul';
        var result = '<' + tag + '>';
        var j = startIdx;
        while (j < items.length && items[j].indent === baseIndent) {
            var subItems = [];
            var nextIdx = j + 1;
            while (nextIdx < items.length && items[nextIdx].indent > baseIndent) {
                subItems.push(items[nextIdx]);
                nextIdx++;
            }
            var nestedHtml = '';
            if (subItems.length > 0) {
                nestedHtml = buildNestedList(subItems, 0, items[j + 1].indent);
            }
            result += '<li>' + parseInline(items[j].text) + nestedHtml + '</li>';
            j = nextIdx;
        }
        result += '</' + tag + '>';
        return result;
    }

    function parseInline(text) {
        text = text.replace(/!\[([^\]]*)\]\(([^)]+)\)/g, '<img src="$2" alt="$1">');
        text = text.replace(/\[([^\]]+)\]\(([^)]+)\)/g, '<a href="$2" target="_blank" rel="noopener">$1</a>');
        text = text.replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>');
        text = text.replace(/__(.+?)__/g, '<strong>$1</strong>');
        text = text.replace(/\*(.+?)\*/g, '<em>$1</em>');
        text = text.replace(/_(.+?)_/g, '<em>$1</em>');
        text = text.replace(/~~(.+?)~~/g, '<del>$1</del>');
        text = text.replace(/`([^`]+)`/g, '<code>$1</code>');
        return text;
    }

    function update() {
        preview.innerHTML = parseMarkdown(input.value);
    }

    input.addEventListener('input', update);
    update();

    window.mdInsert = function(before, after) {
        var start = input.selectionStart;
        var end = input.selectionEnd;
        var sel = input.value.substring(start, end) || 'text';
        var replacement = before + sel + after;
        input.setRangeText(replacement, start, end, 'select');
        input.selectionStart = start + before.length;
        input.selectionEnd = start + before.length + sel.length;
        input.focus();
        update();
    };

    window.mdInsertLine = function(prefix) {
        var start = input.selectionStart;
        var val = input.value;
        var lineStart = val.lastIndexOf('\n', start - 1) + 1;
        input.setRangeText(prefix, lineStart, lineStart, 'end');
        input.focus();
        update();
    };

    window.mdInsertCodeBlock = function() {
        var start = input.selectionStart;
        var end = input.selectionEnd;
        var sel = input.value.substring(start, end) || 'code here';
        var block = '\n```\n' + sel + '\n```\n';
        input.setRangeText(block, start, end, 'end');
        input.focus();
        update();
    };

    window.mdInsertTable = function() {
        var tpl = '\n| Column 1 | Column 2 | Column 3 |\n|----------|----------|----------|\n| Cell 1   | Cell 2   | Cell 3   |\n| Cell 4   | Cell 5   | Cell 6   |\n';
        var start = input.selectionStart;
        input.setRangeText(tpl, start, start, 'end');
        input.focus();
        update();
    };

    window.mdCopyMd = function() {
        navigator.clipboard.writeText(input.value).then(function() {
            showToast('Markdown copied!');
        });
    };

    window.mdCopyHtml = function() {
        navigator.clipboard.writeText(preview.innerHTML).then(function() {
            showToast('HTML copied!');
        });
    };

    window.mdClear = function() {
        input.value = '';
        update();
    };

    function showToast(msg) {
        var t = document.createElement('div');
        t.textContent = msg;
        t.style.cssText = 'position:fixed;bottom:20px;right:20px;background:#5865f2;color:#fff;padding:8px 18px;border-radius:8px;font-size:.85rem;z-index:9999;opacity:0;transition:opacity .3s;pointer-events:none;';
        document.body.appendChild(t);
        requestAnimationFrame(function() { t.style.opacity = '1'; });
        setTimeout(function() { t.style.opacity = '0'; setTimeout(function() { t.remove(); }, 300); }, 1500);
    }

    input.addEventListener('keydown', function(e) {
        if (e.key === 'Tab') {
            e.preventDefault();
            var s = input.selectionStart;
            input.setRangeText('    ', s, s, 'end');
            update();
        }
    });
})();
</script>
<?php page_footer(); ?>
