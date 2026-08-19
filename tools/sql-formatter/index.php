<?php
require_once __DIR__ . '/../../functions.php';

start_session();
$GLOBALS['_meta'] = [
    'description' => 'Free online SQL formatter and beautifier. Instantly format, indent, and syntax-highlight messy SQL queries. Minify or copy with one click. 100% in your browser.',
    'keywords' => 'sql formatter, sql beautifier, sql pretty print, format sql query, sql indent, sql minifier, online sql tool',
];
page_header('SQL Formatter & Beautifier — Format, Minify & Highlight SQL');
?>
<div class="container" style="max-width: 980px;">
    <h1 class="h4 mb-2 reveal in-view">SQL Formatter & Beautifier</h1>
    <p class="text-secondary mb-1 reveal in-view">Paste a messy, minified or hand-written SQL query and get clean, properly indented output with keywords uppercased. Useful when copying queries from logs, API responses or cramped admin panels.</p>
    <p class="text-secondary mb-4 reveal in-view">The formatter recognises all common SQL keywords — SELECT, JOIN, WHERE, GROUP BY, ORDER BY, CREATE TABLE, and more — then rewrites them in uppercase, adds line breaks before major clauses, indents sub-queries and aligns parentheses. Everything runs locally in your browser; no query data is transmitted.</p>

    <div class="card reveal in-view">
        <div class="card-body">
            <div class="d-flex flex-wrap gap-2 align-items-center mb-2">
                <button class="btn btn-primary btn-sm" onclick="fmtSql()">Format</button>
                <button class="btn btn-outline-light btn-sm" onclick="minSql()">Minify</button>
                <button class="btn btn-outline-light btn-sm" onclick="copySql()">Copy</button>
                <button class="btn btn-outline-light btn-sm" onclick="clearSql()">Clear</button>
                <div class="form-check form-switch ms-2">
                    <input class="form-check-input" type="checkbox" id="sql-highlight-toggle" checked onchange="fmtSql()">
                    <label class="form-check-label small text-secondary" for="sql-highlight-toggle">Syntax highlight</label>
                </div>
            </div>
            <form method="post" onsubmit="return false;">
                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                <label class="form-label small text-secondary">Input SQL</label>
                <textarea id="sql-in" class="form-control mb-2" rows="6" style="font-family:'JetBrains Mono',monospace;font-size:.85rem;" placeholder="SELECT * FROM users WHERE id = 1 AND status = 'active' ORDER BY name;"></textarea>
                <label class="form-label small text-secondary">Formatted SQL</label>
                <div class="input-group">
                    <textarea id="sql-out" class="form-control" rows="12" readonly style="font-family:'JetBrains Mono',monospace;font-size:.85rem;"></textarea>
                    <button class="btn btn-outline-light" onclick="copySql()">Copy</button>
                </div>
                <div id="sql-highlight-box" class="mt-2 p-3" style="border:1px solid var(--line);border-radius:8px;background:#0b0b0b;font-family:'JetBrains Mono',monospace;font-size:.85rem;white-space:pre-wrap;word-break:break-word;min-height:40px;display:none;"></div>
            </form>
            <div id="sql-msg" class="form-text mt-2"></div>
        </div>
    </div>

    <h2 class="h6 mt-4 reveal in-view" style="border-bottom:1px solid var(--line);padding-bottom:.5rem;">Why use a SQL formatter?</h2>
    <p class="text-secondary small reveal in-view">Database clients, ORMs and logs often output SQL as a single unreadable line. Formatting adds consistent indentation and line breaks so nested sub-queries, JOIN chains and WHERE clauses become visually clear. Uppercasing keywords makes the structure scannable at a glance, and minifying is handy when you need to paste a query into a URL or log entry without line breaks.</p>

    <h2 class="h6 mt-4 reveal in-view" style="border-bottom:1px solid var(--line);padding-bottom:.5rem;">Example</h2>
    <p class="text-secondary small reveal in-view">Paste <code style="font-family:'JetBrains Mono',monospace;font-size:.8rem;">select u.id, u.name, o.total from users u inner join orders o on u.id = o.user_id where o.total > 100 and u.status = 'active' order by o.total desc limit 10;</code> and click <strong>Format</strong> — keywords are uppercased, JOINs are indented, and each column gets its own line.</p>
</div>

<script>
var SQL_KEYWORDS = [
    'SELECT','FROM','WHERE','AND','OR','NOT','IN','IS','NULL','AS','ON',
    'JOIN','LEFT JOIN','RIGHT JOIN','INNER JOIN','OUTER JOIN','CROSS JOIN',
    'LEFT OUTER JOIN','RIGHT OUTER JOIN','FULL OUTER JOIN','FULL JOIN',
    'LEFT INNER JOIN','RIGHT INNER JOIN',
    'ORDER BY','GROUP BY','HAVING','LIMIT','OFFSET',
    'INSERT INTO','VALUES','UPDATE','SET','DELETE FROM',
    'CREATE TABLE','ALTER TABLE','DROP TABLE','CREATE INDEX','DROP INDEX',
    'CREATE DATABASE','ALTER DATABASE','DROP DATABASE',
    'UNION','UNION ALL','EXCEPT','INTERSECT',
    'CASE','WHEN','THEN','ELSE','END',
    'BETWEEN','LIKE','EXISTS','NOT EXISTS','IN','NOT IN',
    'PRIMARY KEY','FOREIGN KEY','REFERENCES','DEFAULT','AUTO_INCREMENT',
    'INT','INTEGER','BIGINT','SMALLINT','TINYINT','FLOAT','DOUBLE',
    'DECIMAL','NUMERIC','VARCHAR','CHAR','TEXT','BLOB','DATE','DATETIME',
    'TIMESTAMP','BOOLEAN','BOOL','SERIAL','UUID','JSON','JSONB',
    'DISTINCT','TOP','ASC','DESC','TOP',
    'IF','IF NOT EXISTS','IF EXISTS',
    'TRUNCATE','REPLACE','MERGE','USING',
    'COMMIT','ROLLBACK','BEGIN','START TRANSACTION',
    'GRANT','REVOKE','DENY',
    'INDEX','TABLE','DATABASE','VIEW','TRIGGER','PROCEDURE','FUNCTION',
    'WITH','RECURSIVE','RETURNING',
    'FETCH','NEXT','ROWS','ONLY','FIRST','LAST',
    'OFFSET','LIMIT','FOR UPDATE','FOR SHARE','NOWAIT',
    'CONFLICT','DO NOTHING','DO UPDATE SET',
    'CASCADE','RESTRICT','NO ACTION','SET NULL','SET DEFAULT',
    'PARTITION','RANGE','LIST','HASH','VALUES IN',
    'COLUMN','CONSTRAINT','CHECK','UNIQUE','NOT NULL',
    'ADD','RENAME','TO','ENABLE','DISABLE','VALIDATE',
    'ENGINE','CHARSET','COLLATE','ROW_FORMAT',
    'EXPLAIN','ANALYZE','DESCRIBE','SHOW',
    'USE','SET NAMES','SET CHARSET','SET AUTOCOMMIT',
    'LIKE','ILIKE','SIMILAR TO','REGEXP','RLIKE',
    'COALESCE','NULLIF','CAST','CONVERT','EXTRACT',
    'COUNT','SUM','AVG','MIN','MAX',
    'UPPER','LOWER','TRIM','LTRIM','RTRIM','SUBSTRING','LENGTH','LEN',
    'CONCAT','CONCAT_WS','REPLACE','REVERSE','REPEAT',
    'NOW','CURRENT_TIMESTAMP','CURRENT_DATE','CURRENT_TIME',
    'DATE_ADD','DATE_SUB','DATEDIFF','DATE_FORMAT',
    'ROUND','CEIL','CEILING','FLOOR','ABS','MOD','POWER','SQRT',
    'ABS','SIGN','RAND','PI',
    'ROW_NUMBER','RANK','DENSE_RANK','NTILE','LAG','LEAD',
    'FIRST_VALUE','LAST_VALUE','NTH_VALUE',
    'OVER','PARTITION BY','ROWS BETWEEN','RANGE BETWEEN',
    'UNBOUNDED PRECEDING','UNBOUNDED FOLLOWING','CURRENT ROW',
    'PRECEDING','FOLLOWING',
    'PIVOT','UNPIVOT','LATERAL',
    'INSERT','DELETE','DROP','CREATE','ALTER','TRUNCATE'
];

var SQL_KEYWORDS_SET = {};
SQL_KEYWORDS.forEach(function(k) { SQL_KEYWORDS_SET[k] = true; });

var SQL_JOIN_KEYWORDS = {
    'JOIN':1,'LEFT JOIN':1,'RIGHT JOIN':1,'INNER JOIN':1,'OUTER JOIN':1,
    'CROSS JOIN':1,'LEFT OUTER JOIN':1,'RIGHT OUTER JOIN':1,
    'FULL OUTER JOIN':1,'FULL JOIN':1,'LEFT INNER JOIN':1,'RIGHT INNER JOIN':1
};

var SQL_CLAUSE_STARTERS = {
    'SELECT':1,'FROM':1,'WHERE':1,'AND':1,'OR':1,'ORDER BY':1,
    'GROUP BY':1,'HAVING':1,'LIMIT':1,'OFFSET':1,'UNION':1,'UNION ALL':1,
    'EXCEPT':1,'INTERSECT':1,'SET':1,'VALUES':1,'INTO':1,
    'ON':1,'WHEN':1,'THEN':1,'ELSE':1,'END':1,
    'RETURNING':1,'FOR UPDATE':1,'FOR SHARE':1,
    'DO NOTHING':1,'DO UPDATE SET':1
};

function $s(id) { return document.getElementById(id); }

function tokenizeSql(sql) {
    var tokens = [];
    var i = 0;
    var len = sql.length;

    while (i < len) {
        if (sql[i] === ' ' || sql[i] === '\t' || sql[i] === '\n' || sql[i] === '\r') {
            var ws = '';
            while (i < len && (sql[i] === ' ' || sql[i] === '\t' || sql[i] === '\n' || sql[i] === '\r')) {
                ws += sql[i];
                i++;
            }
            tokens.push({ type: 'whitespace', value: ws });
            continue;
        }

        if (sql[i] === '-' && i + 1 < len && sql[i + 1] === '-') {
            var lineComment = '';
            while (i < len && sql[i] !== '\n') {
                lineComment += sql[i];
                i++;
            }
            tokens.push({ type: 'comment', value: lineComment });
            continue;
        }

        if (sql[i] === '/' && i + 1 < len && sql[i + 1] === '*') {
            var blockComment = '/*';
            i += 2;
            while (i < len && !(sql[i] === '*' && i + 1 < len && sql[i + 1] === '/')) {
                blockComment += sql[i];
                i++;
            }
            if (i < len) {
                blockComment += '*/';
                i += 2;
            }
            tokens.push({ type: 'comment', value: blockComment });
            continue;
        }

        if (sql[i] === "'") {
            var str = "'";
            i++;
            while (i < len) {
                if (sql[i] === "'" && i + 1 < len && sql[i + 1] === "'") {
                    str += "''";
                    i += 2;
                } else if (sql[i] === "'") {
                    str += "'";
                    i++;
                    break;
                } else {
                    str += sql[i];
                    i++;
                }
            }
            tokens.push({ type: 'string', value: str });
            continue;
        }

        if (sql[i] === '"') {
            var dq = '"';
            i++;
            while (i < len && sql[i] !== '"') {
                dq += sql[i];
                i++;
            }
            if (i < len) {
                dq += '"';
                i++;
            }
            tokens.push({ type: 'identifier', value: dq });
            continue;
        }

        if (sql[i] === '`') {
            var bt = '`';
            i++;
            while (i < len && sql[i] !== '`') {
                bt += sql[i];
                i++;
            }
            if (i < len) {
                bt += '`';
                i++;
            }
            tokens.push({ type: 'identifier', value: bt });
            continue;
        }

        if (sql[i] >= '0' && sql[i] <= '9') {
            var num = '';
            while (i < len && ((sql[i] >= '0' && sql[i] <= '9') || sql[i] === '.')) {
                num += sql[i];
                i++;
            }
            tokens.push({ type: 'number', value: num });
            continue;
        }

        if (sql[i] === '(' || sql[i] === ')') {
            tokens.push({ type: sql[i] === '(' ? 'open_paren' : 'close_paren', value: sql[i] });
            i++;
            continue;
        }

        if (sql[i] === ',' || sql[i] === ';') {
            tokens.push({ type: sql[i] === ',' ? 'comma' : 'semicolon', value: sql[i] });
            i++;
            continue;
        }

        if (sql[i] === '=' || sql[i] === '<' || sql[i] === '>' || sql[i] === '!' || sql[i] === '+' || sql[i] === '-' || sql[i] === '*' || sql[i] === '/' || sql[i] === '%' || sql[i] === '|') {
            var op = sql[i];
            i++;
            if (i < len && (sql[i] === '=' || sql[i] === '>' || sql[i] === '<')) {
                op += sql[i];
                i++;
            }
            tokens.push({ type: 'operator', value: op });
            continue;
        }

        if (sql[i] === '.') {
            tokens.push({ type: 'dot', value: '.' });
            i++;
            continue;
        }

        if (sql[i] === ':') {
            var bind = ':';
            i++;
            while (i < len && ((sql[i] >= 'a' && sql[i] <= 'z') || (sql[i] >= 'A' && sql[i] <= 'Z') || (sql[i] >= '0' && sql[i] <= '9') || sql[i] === '_')) {
                bind += sql[i];
                i++;
            }
            tokens.push({ type: 'bind', value: bind });
            continue;
        }

        if (sql[i] === '?') {
            tokens.push({ type: 'bind', value: '?' });
            i++;
            continue;
        }

        var word = '';
        while (i < len && ((sql[i] >= 'a' && sql[i] <= 'z') || (sql[i] >= 'A' && sql[i] <= 'Z') || (sql[i] >= '0' && sql[i] <= '9') || sql[i] === '_' || sql[i] === '$')) {
            word += sql[i];
            i++;
        }
        if (word) {
            tokens.push({ type: 'word', value: word });
            continue;
        }

        tokens.push({ type: 'other', value: sql[i] });
        i++;
    }

    return tokens;
}

function matchMultiWordKeyword(tokens, idx) {
    var t = tokens[idx];
    if (t.type !== 'word') return null;
    var val = t.value.toUpperCase();
    var combined = val;

    if (idx + 2 < tokens.length) {
        var skipWs1 = tokens[idx + 1];
        var t2 = tokens[idx + 2];
        if (skipWs1.type === 'whitespace' && t2.type === 'word') {
            var triple = combined + ' ' + t2.value.toUpperCase();
            var skipWs2 = tokens[idx + 3];
            var t3 = tokens[idx + 4];
            if (idx + 4 < tokens.length && skipWs2.type === 'whitespace' && t3 && t3.type === 'word') {
                var quad = triple + ' ' + t3.value.toUpperCase();
                if (SQL_KEYWORDS_SET[quad]) {
                    return { keyword: quad, length: 5 };
                }
            }
            if (SQL_KEYWORDS_SET[triple]) {
                return { keyword: triple, length: 3 };
            }
        }
    }

    if (idx + 2 < tokens.length) {
        var sw1 = tokens[idx + 1];
        var tw = tokens[idx + 2];
        if (sw1.type === 'whitespace' && tw.type === 'word') {
            var pair = combined + ' ' + tw.value.toUpperCase();
            if (SQL_KEYWORDS_SET[pair]) {
                return { keyword: pair, length: 3 };
            }
        }
    }

    return null;
}

function formatSql(sql) {
    var tokens = tokenizeSql(sql);
    var indent = 0;
    var result = [];
    var i = 0;
    var inSelectList = false;
    var selectListDepth = 0;
    var parenDepth = 0;

    var majorKeywords = {
        'SELECT':1, 'FROM':1, 'WHERE':1, 'GROUP BY':1, 'ORDER BY':1,
        'HAVING':1, 'LIMIT':1, 'OFFSET':1, 'SET':1, 'VALUES':1,
        'RETURNING':1, 'FOR UPDATE':1, 'FOR SHARE':1
    };

    var joinKeywords = SQL_JOIN_KEYWORDS;

    var breakBefore = {
        'AND':1, 'OR':1, 'WHEN':1, 'THEN':1, 'ELSE':1, 'END':1,
        'UNION':1, 'UNION ALL':1, 'EXCEPT':1, 'INTERSECT':1,
        'ON':1, 'DO NOTHING':1, 'DO UPDATE SET':1
    };

    var afterKeywords = {
        'SELECT':1, 'INTO':1, 'UPDATE':1, 'DELETE FROM':1,
        'CREATE TABLE':1, 'ALTER TABLE':1, 'DROP TABLE':1,
        'CREATE INDEX':1, 'DROP INDEX':1,
        'CREATE DATABASE':1, 'ALTER DATABASE':1, 'DROP DATABASE':1
    };

    var endKeywords = { 'END':1 };

    function addIndent() {
        result.push('  '.repeat(indent));
    }

    function peekNextNonWs(startIdx) {
        for (var j = startIdx; j < tokens.length; j++) {
            if (tokens[j].type !== 'whitespace' && tokens[j].type !== 'comment') {
                return tokens[j];
            }
        }
        return null;
    }

    function prevNonWsToken() {
        for (var j = result.length - 1; j >= 0; j--) {
            if (result[j].type !== 'whitespace' && result[j].type !== 'newline') {
                return result[j];
            }
        }
        return null;
    }

    var prevTokenType = 'start';

    while (i < tokens.length) {
        var token = tokens[i];

        if (token.type === 'comment') {
            addIndent();
            result.push({ type: 'comment', value: token.value });
            result.push({ type: 'newline', value: '\n' });
            prevTokenType = 'comment';
            i++;
            continue;
        }

        if (token.type === 'whitespace') {
            i++;
            continue;
        }

        var multiMatch = matchMultiWordKeyword(tokens, i);

        if (multiMatch) {
            var kw = multiMatch.keyword;
            var tokenLen = multiMatch.length;
            var upperKw = kw;

            if (breakBefore[kw]) {
                if (prevTokenType !== 'start' && prevTokenType !== 'newline') {
                    result.push({ type: 'newline', value: '\n' });
                }
            } else if (majorKeywords[kw]) {
                if (prevTokenType !== 'start' && prevTokenType !== 'newline') {
                    result.push({ type: 'newline', value: '\n' });
                }
            }

            if (endKeywords[kw] && indent > 0) {
                indent--;
            }

            addIndent();
            result.push({ type: 'keyword', value: upperKw });

            var consumed = tokenLen - 1;
            for (var c = 0; c < consumed; c++) {
                i++;
                if (tokens[i].type === 'whitespace') {
                    result.push({ type: 'space', value: ' ' });
                } else {
                    result.push({ type: 'keyword', value: tokens[i].value.toUpperCase() });
                }
            }

            if (majorKeywords[kw]) {
                result.push({ type: 'newline', value: '\n' });
                indent++;
                inSelectList = (kw === 'SELECT');
            }

            if (joinKeywords[kw]) {
                result.push({ type: 'newline', value: '\n' });
                inSelectList = false;
            }

            if (kw === 'FROM' && inSelectList) {
                inSelectList = false;
            }

            prevTokenType = 'keyword';
            i++;
            continue;
        }

        if (token.type === 'word') {
            var upperVal = token.value.toUpperCase();

            if (SQL_KEYWORDS_SET[upperVal]) {
                var isJoin = joinKeywords[upperVal];
                var isMajor = majorKeywords[upperVal];
                var isBreakBefore = breakBefore[upperVal];
                var isEnd = endKeywords[upperVal];

                if (isBreakBefore) {
                    if (prevTokenType !== 'start' && prevTokenType !== 'newline') {
                        result.push({ type: 'newline', value: '\n' });
                    }
                } else if (isMajor || isJoin) {
                    if (prevTokenType !== 'start' && prevTokenType !== 'newline') {
                        result.push({ type: 'newline', value: '\n' });
                    }
                }

                if (isEnd && indent > 0) {
                    indent--;
                }

                addIndent();
                result.push({ type: 'keyword', value: upperVal });

                if (isMajor) {
                    result.push({ type: 'newline', value: '\n' });
                    indent++;
                    inSelectList = (upperVal === 'SELECT');
                }

                if (isJoin) {
                    result.push({ type: 'newline', value: '\n' });
                    inSelectList = false;
                }

                if (upperVal === 'FROM' && inSelectList) {
                    inSelectList = false;
                }

                if (upperVal === 'WHEN' && indent > 0) {
                    indent--;
                }

                prevTokenType = 'keyword';
                i++;
                continue;
            }

            if (upperVal === 'SET' && prevTokenType === 'keyword') {
                /* already handled above */
            }

            addIndent();
            result.push({ type: 'identifier', value: token.value });

            var nx = peekNextNonWs(i + 1);
            if (inSelectList && nx && nx.type === 'comma') {
                result.push({ type: 'newline', value: '\n' });
                prevTokenType = 'identifier';
                i++;
                continue;
            }

            prevTokenType = 'identifier';
            i++;
            continue;
        }

        if (token.type === 'open_paren') {
            var prev = prevNonWsToken();
            var prevIsKw = prev && prev.type === 'keyword';

            if (prevIsKw) {
                result.push({ type: 'space', value: ' ' });
            } else {
                addIndent();
            }

            result.push({ type: 'paren', value: '(' });
            parenDepth++;

            var nx2 = peekNextNonWs(i + 1);
            if (nx2 && nx2.type !== 'close_paren') {
                result.push({ type: 'newline', value: '\n' });
                indent++;
            }

            prevTokenType = 'open_paren';
            i++;
            continue;
        }

        if (token.type === 'close_paren') {
            if (parenDepth > 0) parenDepth--;
            if (indent > 0) indent--;
            result.push({ type: 'newline', value: '\n' });
            addIndent();
            result.push({ type: 'paren', value: ')' });

            var afterP = peekNextNonWs(i + 1);
            if (afterP && afterP.type !== 'semicolon' && afterP.type !== 'comma' && afterP.type !== 'close_paren') {
                var upAfter = afterP.value ? afterP.value.toUpperCase() : '';
                if (!SQL_KEYWORDS_SET[upAfter] && upAfter !== ';') {
                    result.push({ type: 'space', value: ' ' });
                }
            }

            prevTokenType = 'close_paren';
            i++;
            continue;
        }

        if (token.type === 'comma') {
            result.push({ type: 'comma', value: ',' });
            result.push({ type: 'newline', value: '\n' });
            prevTokenType = 'comma';
            i++;
            continue;
        }

        if (token.type === 'semicolon') {
            result.push({ type: 'semicolon', value: ';' });
            result.push({ type: 'newline', value: '\n' });
            inSelectList = false;
            prevTokenType = 'semicolon';
            i++;
            continue;
        }

        if (token.type === 'operator') {
            result.push({ type: 'space', value: ' ' });
            result.push({ type: 'operator', value: token.value });
            result.push({ type: 'space', value: ' ' });
            prevTokenType = 'operator';
            i++;
            continue;
        }

        if (token.type === 'dot') {
            result.push({ type: 'dot', value: '.' });
            prevTokenType = 'dot';
            i++;
            continue;
        }

        if (token.type === 'string' || token.type === 'number' || token.type === 'identifier' || token.type === 'bind') {
            var tval = (token.type === 'identifier') ? token.value : (token.type === 'string' ? token.value : (token.type === 'number' ? token.value : token.value.toUpperCase()));
            result.push({ type: token.type, value: tval });
            prevTokenType = token.type;
            i++;
            continue;
        }

        result.push({ type: token.type, value: token.value });
        prevTokenType = token.type;
        i++;
    }

    return result;
}

function renderTokens(tokens) {
    var lines = [''];
    for (var i = 0; i < tokens.length; i++) {
        var t = tokens[i];
        if (t.type === 'newline') {
            lines.push('');
        } else if (t.type === 'keyword') {
            lines[lines.length - 1] += t.value;
        } else if (t.type === 'string') {
            lines[lines.length - 1] += t.value;
        } else if (t.type === 'comment') {
            lines[lines.length - 1] += t.value;
        } else if (t.type === 'identifier') {
            lines[lines.length - 1] += t.value;
        } else if (t.type === 'number') {
            lines[lines.length - 1] += t.value;
        } else if (t.type === 'bind') {
            lines[lines.length - 1] += t.value;
        } else {
            lines[lines.length - 1] += t.value;
        }
    }

    var trimmed = [];
    for (var j = 0; j < lines.length; j++) {
        var l = lines[j].replace(/[ \t]+$/, '');
        if (l === '' && trimmed.length > 0 && trimmed[trimmed.length - 1] === '') continue;
        trimmed.push(l);
    }

    while (trimmed.length > 0 && trimmed[trimmed.length - 1] === '') {
        trimmed.pop();
    }

    return trimmed.join('\n');
}

function escapeHtml(s) {
    return s.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}

function highlightSql(sql) {
    var tokens = tokenizeSql(sql);
    var html = '';

    for (var i = 0; i < tokens.length; i++) {
        var t = tokens[i];
        var esc = escapeHtml(t.value);

        if (t.type === 'keyword') {
            var kw = t.value.toUpperCase();
            if (SQL_KEYWORDS_SET[kw] || kw === 'AS') {
                html += '<span style="color:#c586c0;font-weight:600;">' + esc + '</span>';
            } else {
                html += '<span style="color:#9cdcfe;">' + esc + '</span>';
            }
        } else if (t.type === 'string') {
            html += '<span style="color:#ce9178;">' + esc + '</span>';
        } else if (t.type === 'number') {
            html += '<span style="color:#b5cea8;">' + esc + '</span>';
        } else if (t.type === 'comment') {
            html += '<span style="color:#6a9955;font-style:italic;">' + esc + '</span>';
        } else if (t.type === 'operator') {
            html += '<span style="color:#d4d4d4;">' + esc + '</span>';
        } else if (t.type === 'identifier') {
            html += '<span style="color:#9cdcfe;">' + esc + '</span>';
        } else if (t.type === 'bind') {
            html += '<span style="color:#dc566d;font-weight:600;">' + esc + '</span>';
        } else if (t.type === 'open_paren' || t.type === 'close_paren') {
            html += '<span style="color:#ffd700;">' + esc + '</span>';
        } else if (t.type === 'comma') {
            html += '<span style="color:#d4d4d4;">' + esc + '</span>';
        } else if (t.type === 'semicolon') {
            html += '<span style="color:#569cd6;">' + esc + '</span>';
        } else {
            html += esc;
        }
    }

    return html;
}

function fmtSql() {
    var raw = $s('sql-in').value.trim();
    if (!raw) {
        $s('sql-out').value = '';
        $s('sql-msg').textContent = '';
        $s('sql-highlight-box').style.display = 'none';
        return;
    }

    try {
        var tokens = formatSql(raw);
        var formatted = renderTokens(tokens);
        $s('sql-out').value = formatted;

        if ($s('sql-highlight-toggle').checked) {
            var box = $s('sql-highlight-box');
            box.innerHTML = highlightSql(formatted);
            box.style.display = 'block';
        } else {
            $s('sql-highlight-box').style.display = 'none';
        }

        var m = $s('sql-msg');
        m.textContent = '\u2705 SQL formatted successfully.';
        m.className = 'form-text mt-2 text-success';
    } catch (e) {
        var m2 = $s('sql-msg');
        m2.textContent = '\u274C ' + e.message;
        m2.className = 'form-text mt-2 text-danger';
    }
}

function minSql() {
    var raw = $s('sql-in').value.trim();
    if (!raw) {
        $s('sql-out').value = '';
        $s('sql-highlight-box').style.display = 'none';
        return;
    }

    var tokens = tokenizeSql(raw);
    var minified = '';
    for (var i = 0; i < tokens.length; i++) {
        var t = tokens[i];
        if (t.type === 'whitespace') {
            minified += ' ';
        } else if (t.type === 'word') {
            minified += t.value.toUpperCase();
        } else {
            minified += t.value;
        }
    }

    minified = minified.replace(/\s+/g, ' ').trim();
    minified = minified.replace(/\s*([(),;])\s*/g, '$1');
    minified = minified.replace(/\s*([=<>!]+)\s*/g, ' $1 ');
    minified = minified.replace(/\s{2,}/g, ' ').trim();

    $s('sql-out').value = minified;
    $s('sql-highlight-box').style.display = 'none';

    var m = $s('sql-msg');
    m.textContent = '\u2705 SQL minified.';
    m.className = 'form-text mt-2 text-success';
}

function copySql() {
    var out = $s('sql-out');
    if (!out.value) return;
    out.select();
    out.setSelectionRange(0, 99999);
    document.execCommand('copy');
    var m = $s('sql-msg');
    m.textContent = '\u2705 Copied to clipboard.';
    m.className = 'form-text mt-2 text-success';
}

function clearSql() {
    $s('sql-in').value = '';
    $s('sql-out').value = '';
    $s('sql-msg').textContent = '';
    $s('sql-highlight-box').style.display = 'none';
}
</script>
<?php page_footer(); ?>
