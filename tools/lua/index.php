<?php
require_once __DIR__ . '/../../functions.php';
require_once __DIR__ . '/lua.php';

start_session();
$output = null;
$code = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify_or_fail();
    if (!rate_limit_check('tool_lua', 20, 600)) {
        friendly_error('Too many executions from your IP. Try again in 10 minutes.', 429);
    }
    $code = (string)($_POST['code'] ?? '');
    if (strlen($code) > 20000) {
        friendly_error('Code is too long (max 20000 characters).', 400);
    }
    if (trim($code) !== '') {
        $runner = new LuaRunner();
        $output = $runner->run($code, 4.0);
        log_activity('tool_lua', 'run');
    }
}

page_header('Lua Runner');
?>
<div class="container" style="max-width: 900px;">
    <h1 class="h4 mb-1 reveal in-view">ðŸš Lua Runner</h1>
    <p class="text-secondary mb-3 reveal in-view">Execute Lua on the server, right here. A tiny built-in Lua interpreter runs your code â€” no install needed. Prints land in the console below, errors show the line.</p>

    <div class="card mb-3 reveal in-view"><div class="card-body">
        <form method="post" action="index.php">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <label class="form-label">Lua code</label>
            <textarea class="form-control" name="code" rows="14" maxlength="20000" spellcheck="false"
                style="font-family:'JetBrains Mono',monospace;font-size:.85rem;white-space:pre;"><?= e($code) ?></textarea>
            <div class="d-flex gap-2 mt-2 flex-wrap">
                <button class="btn btn-primary" type="submit">â–¶ Run</button>
                <button type="button" class="btn btn-outline-light" onclick="loadSample(0)">Sample: loop</button>
                <button type="button" class="btn btn-outline-light" onclick="loadSample(1)">Sample: table</button>
                <button type="button" class="btn btn-outline-light" onclick="loadSample(2)">Sample: strings</button>
            </div>
        </form>
    </div></div>

    <div class="card reveal"><div class="card-body">
        <div class="d-flex align-items-center justify-content-between mb-2">
            <h2 class="h6 mb-0">Console</h2>
            <?php if ($output !== null): ?>
                <?php if ($output['ok']): ?>
                    <span class="badge text-bg-success">OK Â· <?= number_format($output['time'] * 1000, 1) ?> ms</span>
                <?php else: ?>
                    <span class="badge text-bg-danger">ERROR</span>
                <?php endif; ?>
            <?php endif; ?>
        </div>
        <pre class="mb-0" style="background:#0b0b0b;border:1px solid var(--line);border-radius:10px;padding:.9rem;font-family:'JetBrains Mono',monospace;font-size:.8rem;min-height:140px;max-height:420px;overflow:auto;white-space:pre-wrap;"><?php
            if ($output !== null) {
                echo e($output['out'] !== '' ? $output['out'] : ($output['ok'] ? '(no output)' : ''));
                if (!$output['ok']) {
                    echo "\n" . e($output['err']);
                }
            } else {
                echo e('Run some code to see the output here.');
            }
        ?></pre>
        <p class="text-secondary small mt-2 mb-0">Supports: variables, if/while/for, functions, tables, print, string &amp; math helpers (math.floor, string.upper...). No files, no network, 4s execution cap. Pairs/ipairs, goto and OOP are not supported.</p>
    </div></div>
</div>
<script>
function loadSample(i) {
    var samples = [
        ['-- sum 1..10\nlocal total = 0\nfor i = 1, 10 do\n  total = total + i\nend\nprint("sum 1..10 = " .. total)'],
        ['-- table + insert\nlocal t = { "a", "b" }\ntable.insert(t, "c")\nfor i = 1, #t do\n  print(i, t[i])\nend'],
        ['-- strings & math\nlocal s = "kevbin"\nprint(string.upper(s))\nprint(string.rep("-", 8))\nprint(math.floor(3.7), math.abs(-5))'],
    ];
    var ta = document.querySelector('textarea[name="code"]');
    ta.value = samples[i][0];
}
</script>
<?php page_footer(); ?>