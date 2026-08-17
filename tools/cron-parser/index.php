<?php
require_once __DIR__ . '/../../functions.php';

start_session();
$GLOBALS['_meta'] = [
    'description' => 'Free online cron expression parser and explainer. Turn any 5- or 6-field cron expression into plain English and compute the next 5 run times. Validate cron syntax instantly.',
    'keywords' => 'cron parser, cron expression, crontab, cron explainer, cron validator, next cron time',
];
page_header('Cron Expression Parser & Explainer');
?>
<div class="container" style="max-width: 980px;">
    <h1 class="h4 mb-2 reveal in-view">Cron Expression Parser & Explainer</h1>
    <p class="text-secondary mb-1 reveal in-view">Paste a cron expression and this free tool translates it into plain English, computes the next 5 execution times in your local timezone, and validates the syntax field by field. Works for standard 5-field and the optional 6-field (with seconds) Vixie cron format.</p>
    <p class="text-secondary mb-4 reveal in-view">Cron schedules jobs on Linux servers and cloud platforms (cron jobs, Lambda/schedules, GitHub Actions managed runners). Memory-holing the syntax is easy; this explainer removes all guesswork — before you deploy a <code>0 0 * * *</code> that was supposed to be daily at midnight.</p>

    <div class="card reveal in-view">
        <div class="card-body">
            <div class="input-group mb-3">
                <input id="cron-expr" class="form-control" style="font-family:'JetBrains Mono',monospace;font-size:1rem;text-align:center;" value="*/5 * * * *" oninput="parseCron()">
                <button class="btn btn-outline-light" onclick="parseCron()">Parse</button>
            </div>
            <div class="d-flex flex-wrap gap-3 align-items-center small text-secondary mb-2">
                <label class="form-check"><input class="form-check-input" type="checkbox" id="cron-secs" onchange="parseCron()"> Include seconds (6 fields)</label>
                <span class="form-text">Fields: minute hour day-of-month month day-of-week [second]</span>
            </div>

            <div class="alert p-3 mb-0" id="cron-human" style="border:1px solid var(--line);background:var(--panel);"></div>

            <div id="cron-next" class="row g-1 mt-3"></div>
            <div class="form-text mt-2" id="cron-help"></div>
        </div>
    </div>

    <h2 class="h6 mt-4 reveal in-view" style="border-bottom:1px solid var(--line);padding-bottom:.5rem;">Cron field reference</h2>
    <p class="text-secondary small reveal in-view">
        <code>minute</code> 0–59 · <code>hour</code> 0–23 · <code>day-of-month</code> 1–31 · <code>month</code> 1–12 or JAN–DEC · <code>day-of-week</code> 0–7 (0 and 7 = Sunday) or SUN–SAT.<br>
        Operators: <code>*</code> every value, <code>*/n</code> every n-th, <code>a,b,c</code> list, <code>a-b</code> range, <code>a-b/n</code> range step, <code>L</code> last day / <code>W</code> weekday and <code>#n</code> nth weekday (Vixie extensions shown but treated permissively).
    </p>
</div>

<script>
function $c(id) { return document.getElementById(id); }
var MON = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
var DOW = ['Sun','Mon','Tue','Wed','Thu','Fri','Sat','Sun'];

function parseField(expr, min, max, dep) {
    // returns a Set of allowed ints for a cron field
    var out = new Set();
    expr.split(',').forEach(function (part) {
        part = part.trim();
        if (part.indexOf('#') !== -1 || part === 'L' || /^L-\d+$/.test(part) || part.indexOf('W') !== -1) {
            // broad Vixie extension — mark as allowed across range (permissive)
            for (var i = min; i <= max; i++) out.add(i);
            dep.push(part);
            return;
        }
        var step = 1, m = part.match(/^(.+?)\/(\d+)$/);
        if (m) { part = m[1]; step = +m[2]; }
        var a, b;
        if (part === '*') { a = min; b = max; }
        else if (part.indexOf('-') !== -1) { var r = part.split('-'); a = +r[0]; b = +r[1]; }
        else { a = b = +part; }
        if (isNaN(a) || isNaN(b) || a < min || b > max || step < 1) throw new Error('field out of range or bad syntax: "' + part + '"');
        for (var v = a; v <= b; v += step) out.add(v);
    });
    return out;
}

function buildSchedule(expr, withSecs) {
    var parts = expr.trim().split(/\s+/);
    var dep = [];
    var fields = withSecs ? 6 : 5;
    if (parts.length !== fields) throw new Error('expected ' + fields + ' fields, got ' + parts.length);
    var minute, hour, dom, month, dow, second;
    if (withSecs) {
        second = parseField(parts[0], 0, 59, dep); minute = parseField(parts[1], 0, 59, dep); hour = parseField(parts[2], 0, 23, dep);
        dom = parseField(parts[3], 1, 31, dep); month = parseField(parts[4], 1, 12, dep); dow = parseField(parts[5], 0, 7, dep);
    } else {
        minute = parseField(parts[0], 0, 59, dep); hour = parseField(parts[1], 0, 23, dep);
        dom = parseField(parts[2], 1, 31, dep); month = parseField(parts[3], 1, 12, dep); dow = parseField(parts[4], 0, 7, dep);
        second = new Set([0]);
    }
    if (dow.has(7) && !dow.has(0)) { dow.delete(7); dow.add(0); }
    return { minute: minute, hour: hour, dom: dom, month: month, dow: dow, second: second, deps: dep };
}

function nextRuns(sched, count) {
    var res = [];
    var now = new Date();
    var t = new Date(now); t.setSeconds(t.getSeconds() + 1, 0);
    var guard = 0;
    while (res.length < count && guard++ < 1000000) {
        if (sched.month.has(t.getMonth() + 1) && sched.dom.has(t.getDate()) &&
            sched.dow.has(t.getDay()) && sched.hour.has(t.getHours()) &&
            sched.minute.has(t.getMinutes()) && sched.second.has(t.getSeconds())) {
            res.push(new Date(t));
        }
        t.setSeconds(t.getSeconds() + 1);
    }
    return res;
}

function describe(sched, withSecs) {
    var f = sched;
    var bits = [];
    var secTxt = f.second.size === 60 ? '' : (withSecs ? 'second ' + Array.from(f.second).join(',') : '');
    if (f.minute.size === 60 && f.hour.size === 24 && f.dom.size === 31 && f.month.size === 12 && (f.dow.size === 7 || f.dow.size === 8)) return 'Every minute' + (secTxt ? ' (' + secTxt + ')' : '');
    if (f.minute.size === 60 && f.hour.size === 24) bits.push('every minute of every hour');
    else if (f.minute.size === 60) bits.push('every minute');
    else if (f.minute.size === 1 && f.hour.size === 24) bits.push('at minute ' + Array.from(f.minute).join(',').replace(/^(\d+)$/, '$1 of every hour'));
    else {
        if (f.minute.size === 1) bits.push('at minute ' + Array.from(f.minute)[0]);
        else if (f.minute.size > 1) bits.push('at minutes ' + Array.from(f.minute).sort().join(', '));
        if (f.hour.size === 24) bits.push('every hour');
        else if (f.hour.size === 1) bits.push('at hour ' + Array.from(f.hour)[0] + ':00');
        else bits.push('at hours ' + Array.from(f.hour).sort((a,b)=>a-b).join(', '));
    }
    if (f.dom.size < 31 || f.month.size < 12 || (f.dow.size < 7 && f.dow.size > 0)) {
        var monthly = 'on day-of-month ' + (f.dom.size === 31 ? '*' : Array.from(f.dom).sort().join(','));
        var when = '';
        if (f.month.size < 12) when += ' in months ' + Array.from(f.month).sort((a,b)=>a-b).map(function(n){return MON[n-1];}).join(', ');
        if (f.dow.size === 7 || f.dow.size === 8) when += (when ? ', ' : '') + 'any day of week';
        else if (f.dow.size > 0 && f.dom.size === 31) when += (when ? ', ' : '') + 'on ' + Array.from(f.dow).sort().map(function(n){return DOW[n];}).join(' and ');
        bits.push(monthly + (when ? (' (' + when + ')') : ''));
    }
    if (secTxt) bits.unshift(secTxt);
    return bits.join(' · ');
}

function parseCron() {
    var expr = $c('cron-expr').value.trim();
    var withSecs = $c('cron-secs').checked;
    var human = $c('cron-human'), next = $c('cron-next');
    human.className = 'alert p-3 mb-0'; human.style.background = 'var(--panel)';
    next.innerHTML = '';
    $c('cron-help').textContent = expr + ' — computed in your local timezone.';
    if (!expr) { human.textContent = 'Enter a cron expression to explain it.'; human.style.borderColor = 'var(--line)'; return; }
    try {
        var sched = buildSchedule(expr, withSecs);
        human.textContent = '↻ ' + describe(sched, withSecs);
        human.style.borderColor = 'rgba(83,101,242,.5)';
        var runs = nextRuns(sched, 5);
        var rows = runs.map(function (d) {
            var yy = d.getFullYear(), mo = MON[d.getMonth()], da = d.getDate();
            var hh = String(d.getHours()).padStart(2,'0'), mm = String(d.getMinutes()).padStart(2,'0'), ss = String(d.getSeconds()).padStart(2,'0');
            return '<div class="col-md-4 col-6"><span class="badge text-bg-dark w-100" style="font-family:JetBrains Mono,monospace;font-size:.75rem;border:1px solid var(--line);">' + hh + ':' + mm + ':' + ss + ' · ' + da + ' ' + mo + ' ' + yy + '</span></div>';
        }).join('');
        next.innerHTML = rows || '<div class="col-12 form-text">No runs found in the near future — check your field ranges.</div>';
    } catch (e) {
        human.textContent = '❌ ' + e.message;
        human.style.borderColor = 'rgba(220,53,69,.5)';
    }
}
parseCron();
</script>
<?php page_footer(); ?>