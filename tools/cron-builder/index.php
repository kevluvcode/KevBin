<?php
require_once __DIR__ . '/../../functions.php';
start_session();
$GLOBALS['_meta'] = [
    'description' => 'Free online cron job builder with visual dropdowns, live expression output, human-readable description, and next 10 execution times. Build cron schedules visually.',
    'keywords' => 'cron builder, cron job builder, cron expression builder, crontab builder, schedule builder',
];
page_header('Cron Job Builder');
?>
<div class="container" style="max-width:1060px;">
    <h1 class="h4 mb-1 reveal in-view">Cron Job Builder</h1>
    <p class="text-secondary mb-3 reveal in-view">Build cron expressions visually with dropdowns and checkboxes — see the expression, human-readable description and next 10 run times update live.</p>

    <div class="row g-3 mb-3 reveal in-view">
        <div class="col-md-7">
            <div class="card h-100"><div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h2 class="h6 mb-0">Schedule Builder</h2>
                    <div class="d-flex gap-2 align-items-center">
                        <select id="cb-preset-sel" class="form-select form-select-sm" style="width:auto;" onchange="applySchedulePreset(this.value)">
                            <option value="">Common Schedules…</option>
                            <option value="*/5 * * * *">Every 5 minutes</option>
                            <option value="0 * * * *">Every hour</option>
                            <option value="0 0 * * *">Daily at midnight</option>
                            <option value="0 9 * * 1-5">Weekdays at 9 AM</option>
                            <option value="0 2 * * 0">Every Sunday at 3 AM</option>
                            <option value="0 0 1 * *">1st of every month</option>
                            <option value="0 0 1 1,4,7,10 *">First day of quarter</option>
                            <option value="0 0 * * 0">Every Sunday at midnight</option>
                            <option value="0 */2 * * *">Every 2 hours</option>
                            <option value="0 9,17 * * 1-5">Weekdays at 9 AM and 5 PM</option>
                            <option value="*/15 * * * *">Every 15 minutes</option>
                            <option value="0 3 * * 0">Weekly backup (Sun 3 AM)</option>
                            <option value="0 1 * * 1">Weekly cleanup (Mon 1 AM)</option>
                            <option value="*/10 * * * *">Every 10 minutes (monitoring)</option>
                            <option value="0 0 1 * *">Monthly report (1st midnight)</option>
                            <option value="0 */6 * * *">Every 6 hours</option>
                            <option value="0 0 * * 1-5">Daily at midnight (weekdays)</option>
                            <option value="30 4 * * *">Daily at 4:30 AM</option>
                            <option value="0 22 * * 5">Friday at 10 PM</option>
                            <option value="0 8,12,18 * * *">8 AM, noon, 6 PM daily</option>
                            <option value="0 0 1,15 * *">1st and 15th of month</option>
                        </select>
                    </div>
                </div>

                <div id="cb-fields" class="mb-3"></div>

                <div class="d-flex flex-wrap gap-1 mb-3" id="cb-quick-presets"></div>

                <div class="mb-2">
                    <label class="form-label small text-secondary mb-1">Generated Expression</label>
                    <div class="input-group">
                        <input id="cb-expr" class="form-control text-center" style="font-family:'JetBrains Mono',monospace;font-size:1.1rem;" readonly>
                        <button class="btn btn-outline-light" onclick="copyText('cb-expr','cb-copied')">Copy</button>
                    </div>
                    <div id="cb-copied" class="form-text text-success" style="height:1.2em;"></div>
                </div>

                <div class="mb-2">
                    <label class="form-label small text-secondary mb-1">Crontab Line (with comment)</label>
                    <div class="input-group">
                        <input id="cb-crontab" class="form-control" style="font-family:'JetBrains Mono',monospace;font-size:.85rem;" readonly>
                        <button class="btn btn-outline-light" onclick="copyText('cb-crontab','cb-crontab-copied')">Copy</button>
                    </div>
                    <div id="cb-crontab-copied" class="form-text text-success" style="height:1.2em;"></div>
                </div>
            </div></div>
        </div>

        <div class="col-md-5">
            <div class="card h-100"><div class="card-body d-flex flex-column">
                <h2 class="h6 mb-2">Description</h2>
                <div id="cb-description" class="alert p-2 mb-3" style="background:var(--panel);border:1px solid var(--line);min-height:48px;"></div>

                <h2 class="h6 mb-2">Next 10 Executions</h2>
                <div id="cb-next" class="flex-grow-1" style="max-height:340px;overflow-y:auto;"></div>

                <div id="cb-validation" class="mt-2 small"></div>
            </div></div>
        </div>
    </div>
</div>

<script>
var CB_FIELDS = [
    { key:'minute', label:'Minute', min:0, max:59, names:null },
    { key:'hour', label:'Hour', min:0, max:23, names:null },
    { key:'dom', label:'Day of Month', min:1, max:31, names:null },
    { key:'month', label:'Month', min:1, max:12, names:{1:'Jan',2:'Feb',3:'Mar',4:'Apr',5:'May',6:'Jun',7:'Jul',8:'Aug',9:'Sep',10:'Oct',11:'Nov',12:'Dec'} },
    { key:'dow', label:'Day of Week', min:0, max:7, names:{0:'Sun',1:'Mon',2:'Tue',3:'Wed',4:'Thu',5:'Fri',6:'Sat',7:'Sun'} }
];

var QUICK_PRESETS = [
    ['* * * * *', 'Every minute'],
    ['0 * * * *', 'Every hour'],
    ['0 0 * * *', 'Every day'],
    ['0 9 * * 1', 'Mon 9 AM'],
    ['0 0 1 * *', '1st of month'],
    ['*/15 * * * *', 'Every 15 min'],
    ['0 9,17 * * 1-5', 'Weekdays 9&5'],
    ['0 3 * * 0', 'Sun 3 AM'],
    ['0 0 1 1,4,7,10 *', 'Quarter start'],
    ['custom', 'Custom…']
];

var DOW_NAMES = {sun:0,mon:1,tue:2,wed:3,thu:4,fri:5,sat:6};
var MON_NAMES = {jan:1,feb:2,mar:3,apr:4,may:5,jun:6,jul:7,aug:8,sep:9,oct:10,nov:11,dec:12};
var MON_FULL = ['','January','February','March','April','May','June','July','August','September','October','November','December'];
var DOW_FULL = ['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'];

function buildFieldUI() {
    var container = document.getElementById('cb-fields');
    var html = '<div class="row g-2">';
    CB_FIELDS.forEach(function (f, fi) {
        html += '<div class="col-md-4 col-lg">';
        html += '<div class="border rounded p-2" style="border-color:var(--line)!important;background:var(--panel);">';
        html += '<div class="d-flex justify-content-between align-items-center mb-1">';
        html += '<span class="small fw-semibold">' + f.label + '</span>';
        html += '<span class="badge text-bg-secondary" style="font-size:.65rem;">' + f.min + '–' + f.max + '</span>';
        html += '</div>';
        html += '<select id="cb-mode-' + f.key + '" class="form-select form-select-sm mb-1" onchange="onModeChange(\'' + f.key + '\')">';
        html += '<option value="every">Every *</option>';
        html += '<option value="specific">Specific</option>';
        html += '<option value="range">Range</option>';
        html += '<option value="step">Step</option>';
        html += '</select>';
        html += '<div id="cb-opts-' + f.key + '"></div>';
        html += '</div>';
        html += '</div>';
    });
    html += '</div>';
    container.innerHTML = html;
    CB_FIELDS.forEach(function (f) { onModeChange(f.key); });
}

function onModeChange(key) {
    var field = CB_FIELDS.filter(function(f){return f.key===key;})[0];
    var mode = document.getElementById('cb-mode-' + key).value;
    var box = document.getElementById('cb-opts-' + key);
    if (mode === 'every') {
        box.innerHTML = '<div class="form-text">Matches every ' + field.label.toLowerCase() + '</div>';
    } else if (mode === 'specific') {
        var html = '<div class="d-flex flex-wrap gap-0">';
        var step2 = (field.max - field.min > 30) ? 5 : 1;
        for (var v = field.min; v <= field.max; v += step2) {
            html += '<label class="form-check me-2 mb-0" style="font-size:.75rem;"><input class="form-check-input cb-check" type="checkbox" value="' + v + '" data-field="' + key + '" onchange="updateExpr()">';
            var disp = v;
            if (field.names && field.names[v]) disp = field.names[v];
            html += ' ' + disp + '</label>';
        }
        html += '</div>';
        html += '<div class="mt-1 d-flex gap-1">';
        html += '<button type="button" class="btn btn-outline-secondary btn-sm" style="font-size:.65rem;padding:0 .4rem;" onclick="selectRange(\'' + key + '\',true)">All</button>';
        html += '<button type="button" class="btn btn-outline-secondary btn-sm" style="font-size:.65rem;padding:0 .4rem;" onclick="selectRange(\'' + key + '\',false)">None</button>';
        if (key === 'dow') {
            html += '<button type="button" class="btn btn-outline-secondary btn-sm" style="font-size:.65rem;padding:0 .4rem;" onclick="selectWeekdays()">Wkdays</button>';
            html += '<button type="button" class="btn btn-outline-secondary btn-sm" style="font-size:.65rem;padding:0 .4rem;" onclick="selectWeekends()">Wkends</button>';
        }
        html += '</div>';
        box.innerHTML = html;
    } else if (mode === 'range') {
        var opts = '';
        var selVals = [];
        for (var v2 = field.min; v2 <= field.max; v2++) selVals.push(v2);
        var sOpts = selVals.map(function(v){
            var d = (field.names && field.names[v]) ? field.names[v] : v;
            return '<option value="' + v + '">' + d + '</option>';
        }).join('');
        opts = '<div class="d-flex align-items-center gap-1">';
        opts += '<select id="cb-range-' + key + '-from" class="form-select form-select-sm" style="width:auto;font-size:.75rem;" onchange="updateExpr()">' + sOpts + '</select>';
        opts += '<span class="small">to</span>';
        opts += '<select id="cb-range-' + key + '-to" class="form-select form-select-sm" style="width:auto;font-size:.75rem;" onchange="updateExpr()">' + sOpts + '</select>';
        opts += '</div>';
        box.innerHTML = opts;
        var fromEl = document.getElementById('cb-range-' + key + '-from');
        var toEl = document.getElementById('cb-range-' + key + '-to');
        fromEl.value = field.min;
        toEl.value = Math.min(field.min + (field.max > 20 ? 4 : 2), field.max);
    } else if (mode === 'step') {
        var opts2 = '';
        var sOpts2 = [];
        for (var v3 = field.min; v3 <= field.max; v3++) sOpts2.push(v3);
        var html2 = '<div class="d-flex align-items-center gap-1">';
        html2 += '<select id="cb-step-' + key + '-base" class="form-select form-select-sm" style="width:auto;font-size:.75rem;" onchange="updateExpr()">';
        html2 += '<option value="*">Every (start 0)</option>';
        for (var v4 = field.min; v4 <= field.max; v4++) {
            var d2 = (field.names && field.names[v4]) ? field.names[v4] : v4;
            html2 += '<option value="' + v4 + '">Start ' + d2 + '</option>';
        }
        html2 += '</select>';
        html2 += '<span class="small">/</span>';
        html2 += '<select id="cb-step-' + key + '-val" class="form-select form-select-sm" style="width:60px;font-size:.75rem;" onchange="updateExpr()">';
        for (var s = 1; s <= field.max - field.min + 1; s++) {
            html2 += '<option value="' + s + '"' + (s === 5 ? ' selected' : '') + '>' + s + '</option>';
        }
        html2 += '</select></div>';
        html2 += '<div class="form-text">every ' + field.label.toLowerCase() + '</div>';
        box.innerHTML = html2;
    }
    updateExpr();
}

function selectRange(key, all) {
    var cbs = document.querySelectorAll('.cb-check[data-field="' + key + '"]');
    cbs.forEach(function (cb) { cb.checked = all; });
    updateExpr();
}

function selectWeekdays() {
    var cbs = document.querySelectorAll('.cb-check[data-field="dow"]');
    cbs.forEach(function (cb) { cb.checked = (parseInt(cb.value) >= 1 && parseInt(cb.value) <= 5); });
    updateExpr();
}

function selectWeekends() {
    var cbs = document.querySelectorAll('.cb-check[data-field="dow"]');
    cbs.forEach(function (cb) { cb.checked = (parseInt(cb.value) === 0 || parseInt(cb.value) === 6 || parseInt(cb.value) === 7); });
    updateExpr();
}

function buildFieldExpr(key) {
    var field = CB_FIELDS.filter(function(f){return f.key===key;})[0];
    var mode = document.getElementById('cb-mode-' + key).value;
    if (mode === 'every') return '*';
    if (mode === 'specific') {
        var cbs = document.querySelectorAll('.cb-check[data-field="' + key + '"]:checked');
        var vals = [];
        cbs.forEach(function (cb) { vals.push(parseInt(cb.value)); });
        vals.sort(function (a, b) { return a - b; });
        if (vals.length === 0) return '*';
        return vals.join(',');
    }
    if (mode === 'range') {
        var from = parseInt(document.getElementById('cb-range-' + key + '-from').value);
        var to = parseInt(document.getElementById('cb-range-' + key + '-to').value);
        if (from === field.min && to === field.max) return '*';
        if (from > to) { var tmp = from; from = to; to = tmp; }
        return from + '-' + to;
    }
    if (mode === 'step') {
        var base = document.getElementById('cb-step-' + key + '-base').value;
        var step = document.getElementById('cb-step-' + key + '-val').value;
        if (base === '*') {
            return '*/' + step;
        }
        return base + '/' + step;
    }
    return '*';
}

function updateExpr() {
    var parts = [];
    CB_FIELDS.forEach(function (f) { parts.push(buildFieldExpr(f.key)); });
    var expr = parts.join(' ');
    document.getElementById('cb-expr').value = expr;
    var descText = describeExpression(parts);
    var descEl = document.getElementById('cb-description');
    descEl.textContent = descText;
    var valid = validateExpression(parts);
    var valEl = document.getElementById('cb-validation');
    if (valid.ok) {
        descEl.style.borderColor = 'rgba(83,101,242,.5)';
        valEl.innerHTML = '<span class="text-success">&#10003; Valid cron expression</span>';
    } else {
        descEl.style.borderColor = 'rgba(220,53,69,.5)';
        valEl.innerHTML = '<span class="text-danger">&#10007; ' + valid.err + '</span>';
    }
    var crontab = expr + ' # ' + descText;
    document.getElementById('cb-crontab').value = crontab;
    showNextRuns(parts);
}

function validateExpression(parts) {
    if (parts.length !== 5) return { ok:false, err:'Expected 5 fields' };
    var limits = [[0,59],[0,23],[1,31],[1,12],[0,7]];
    var labels = ['minute','hour','day of month','month','day of week'];
    for (var i = 0; i < 5; i++) {
        var f = parts[i].trim();
        if (!f) return { ok:false, err:labels[i] + ' is empty' };
        var err = validateField(f, limits[i][0], limits[i][1]);
        if (err) return { ok:false, err:labels[i] + ': ' + err };
    }
    if (parts[3].indexOf('*') === -1 && parts[2].indexOf('*') === -1 && parts[4].indexOf('*') === -1) {
        if (parts[2] !== '*' && parts[4] !== '*' && parts[3] !== '*') {
            return { ok:true, warn:'day, month, and weekday are all specified — job may not run as expected' };
        }
    }
    return { ok:true };
}

function validateField(f, min, max) {
    var parts = f.split(',');
    for (var p = 0; p < parts.length; p++) {
        var part = parts[p].trim();
        if (!part) return 'empty value';
        var stepParts = part.split('/');
        var base = stepParts[0];
        var step = stepParts.length > 1 ? stepParts[1] : null;
        if (step !== null) {
            if (isNaN(parseInt(step, 10)) || parseInt(step, 10) < 1) return 'bad step: ' + step;
        }
        if (base === '*') continue;
        var rangeParts = base.split('-');
        if (rangeParts.length === 2) {
            var a = parseInt(rangeParts[0], 10);
            var b = parseInt(rangeParts[1], 10);
            if (isNaN(a) || isNaN(b)) return 'bad range: ' + base;
            if (a < min || b > max) return 'out of range: ' + base;
            if (a > b) return 'range reversed: ' + base;
        } else {
            var v = parseInt(base, 10);
            if (isNaN(v)) return 'not a number: ' + base;
            if (v < min || v > max) return 'out of range: ' + v;
        }
    }
    return null;
}

function expandField(f, min, max) {
    var out = {};
    var parts = f.split(',');
    for (var p = 0; p < parts.length; p++) {
        var part = parts[p].trim();
        var step = 1;
        var base = part;
        if (base.indexOf('/') !== -1) {
            var sp = base.split('/');
            base = sp[0];
            step = parseInt(sp[1], 10) || 1;
        }
        var lo, hi;
        if (base === '*') { lo = min; hi = max; }
        else if (base.indexOf('-') !== -1) { var rr = base.split('-'); lo = parseInt(rr[0],10); hi = parseInt(rr[1],10); }
        else { lo = hi = parseInt(base, 10); }
        if (isNaN(lo) || isNaN(hi)) continue;
        for (var v = lo; v <= hi; v += step) out[v] = true;
    }
    return Object.keys(out).map(Number);
}

function describeExpression(parts) {
    var minuteVals = expandField(parts[0], 0, 59);
    var hourVals = expandField(parts[1], 0, 23);
    var domVals = expandField(parts[2], 1, 31);
    var monthVals = expandField(parts[3], 1, 12);
    var dowVals = expandField(parts[4], 0, 7);

    if (minuteVals.length === 60 && hourVals.length === 24 && domVals.length === 31 && monthVals.length === 12 && (dowVals.length === 7 || dowVals.length === 8)) return 'Every minute';

    var bits = [];

    var timePart = '';
    if (minuteVals.length === 60 && hourVals.length === 24) {
        timePart = 'every minute';
    } else if (minuteVals.length === 60) {
        timePart = 'every minute of ' + formatHours(hourVals);
    } else if (hourVals.length === 24 && minuteVals.length === 1) {
        timePart = 'at ' + pad(minuteVals[0]) + ' of every hour';
    } else if (minuteVals.length === 1 && hourVals.length === 1) {
        timePart = 'at ' + pad(hourVals[0]) + ':' + pad(minuteVals[0]);
    } else if (minuteVals.length === 1 && hourVals.length > 1) {
        timePart = 'at ' + pad(minuteVals[0]) + ' past ' + formatHours(hourVals);
    } else if (minuteVals.length > 1 && hourVals.length === 1) {
        timePart = 'at minutes ' + minuteVals.sort(numSort).join(', ') + ' of ' + formatHourSingle(hourVals[0]);
    } else {
        timePart = 'at minutes ' + minuteVals.sort(numSort).join(', ') + ' of ' + formatHours(hourVals);
    }
    bits.push(timePart);

    if (domVals.length < 31 || monthVals.length < 12 || (dowVals.length < 7 && dowVals.length > 0)) {
        var when = [];
        if (domVals.length < 31 && dowVals.length < 7 && dowVals.length > 0) {
            when.push('on ' + formatDow(dowVals) + ' of ' + formatDom(domVals));
        } else if (domVals.length < 31 && dowVals.length >= 7) {
            when.push('on day' + (domVals.length === 1 ? '' : 's') + ' ' + domVals.sort(numSort).join(', '));
        } else if (domVals.length === 31 && dowVals.length < 7 && dowVals.length > 0) {
            when.push('on ' + formatDow(dowVals));
        } else if (domVals.length < 31) {
            when.push('on day' + (domVals.length === 1 ? '' : 's') + ' ' + domVals.sort(numSort).join(', '));
        }
        if (monthVals.length < 12) when.push('in ' + formatMonths(monthVals));
        if (when.length) bits.push(when.join(' in '));
    }

    return bits.join(' ');
}

function formatHours(vals) {
    vals = vals.sort(numSort);
    if (vals.length === 24) return 'every hour';
    if (vals.length === 1) return formatHourSingle(vals[0]);
    return 'hours ' + vals.map(function (h) { return pad(h) + ':00'; }).join(', ');
}

function formatHourSingle(h) {
    if (h === 0) return 'midnight';
    if (h === 12) return 'noon';
    if (h < 12) return h + ' AM';
    return (h - 12) + ' PM';
}

function formatDom(vals) {
    vals = vals.sort(numSort);
    if (vals.length === 31) return 'any day';
    return vals.length === 1 ? 'day ' + vals[0] : 'days ' + vals.join(', ');
}

function formatDow(vals) {
    vals = vals.sort(numSort).filter(function(v){return v<=6;});
    var unique = [];
    vals.forEach(function(v){ if(unique.indexOf(v)===-1) unique.push(v); });
    if (unique.length === 7) return 'any day of week';
    if (unique.length === 5 && unique[0]===1 && unique[4]===5) return 'weekdays';
    if (unique.length === 2 && unique[0]===0 && unique[1]===6) return 'weekends';
    var dayNames = unique.map(function (d) { return d === 0 ? 'Sunday' : d === 1 ? 'Monday' : d === 2 ? 'Tuesday' : d === 3 ? 'Wednesday' : d === 4 ? 'Thursday' : d === 5 ? 'Friday' : 'Saturday'; });
    return dayNames.join(' and ');
}

function formatMonths(vals) {
    vals = vals.sort(numSort);
    if (vals.length === 12) return 'every month';
    return vals.map(function (m) { return MON_FULL[m]; }).join(', ');
}

function pad(n) { return String(n).padStart(2, '0'); }
function numSort(a, b) { return a - b; }

function showNextRuns(parts) {
    var container = document.getElementById('cb-next');
    var minuteVals = expandField(parts[0], 0, 59);
    var hourVals = expandField(parts[1], 0, 23);
    var domVals = expandField(parts[2], 1, 31);
    var monthVals = expandField(parts[3], 1, 12);
    var dowVals = expandField(parts[4], 0, 7);
    var mSet = {}, hSet = {}, dSet = {}, moSet = {}, wSet = {};
    minuteVals.forEach(function(v){mSet[v]=true;});
    hourVals.forEach(function(v){hSet[v]=true;});
    domVals.forEach(function(v){dSet[v]=true;});
    monthVals.forEach(function(v){moSet[v]=true;});
    dowVals.forEach(function(v){wSet[v]=true;});

    var found = [];
    var now = new Date();
    var d = new Date(now);
    d.setSeconds(0, 0);
    d.setMilliseconds(0);
    d.setMinutes(d.getMinutes() + 1);
    var guard = 0;
    while (found.length < 10 && guard < 2000000) {
        if (moSet[d.getMonth() + 1] && dSet[d.getDate()] && wSet[d.getDay()] && hSet[d.getHours()] && mSet[d.getMinutes()]) {
            found.push(new Date(d));
        }
        d.setMinutes(d.getMinutes() + 1);
        guard++;
    }

    if (found.length === 0) {
        container.innerHTML = '<div class="text-secondary small p-2">No executions found in the next ~4 years.</div>';
        return;
    }

    var html = '<div class="d-flex flex-column gap-1">';
    found.forEach(function (dt, idx) {
        var mon = MON_FULL[dt.getMonth() + 1];
        var day = DOW_FULL[dt.getDay()];
        var dateStr = day + ', ' + mon + ' ' + dt.getDate() + ', ' + dt.getFullYear();
        var timeStr = pad(dt.getHours()) + ':' + pad(dt.getMinutes());
        html += '<div class="d-flex align-items-center gap-2 p-1 rounded" style="background:rgba(255,255,255,.03);border:1px solid var(--line);">';
        html += '<span class="badge text-bg-dark" style="min-width:18px;text-align:center;">' + (idx + 1) + '</span>';
        html += '<span style="font-family:\'JetBrains Mono\',monospace;font-size:.8rem;">' + timeStr + '</span>';
        html += '<span class="text-secondary small">' + dateStr + '</span>';
        html += '</div>';
    });
    html += '</div>';
    container.innerHTML = html;
}

function copyText(inputId, feedbackId) {
    var el = document.getElementById(inputId);
    var val = el.value;
    if (!val) return;
    if (navigator.clipboard) {
        navigator.clipboard.writeText(val).then(function () { showCopied(feedbackId); });
    } else {
        el.select();
        document.execCommand('copy');
        showCopied(feedbackId);
    }
}

function showCopied(id) {
    var el = document.getElementById(id);
    el.textContent = 'Copied!';
    setTimeout(function () { el.textContent = ''; }, 1500);
}

function applySchedulePreset(expr) {
    if (!expr) return;
    document.getElementById('cb-preset-sel').value = '';
    var parts = expr.split(' ');
    if (parts.length !== 5) return;
    applyPartsToUI(parts);
}

function applyPartsToUI(parts) {
    CB_FIELDS.forEach(function (f, i) {
        var val = parts[i];
        var expanded = expandField(val, f.min, f.max);
        var allVals = [];
        for (var v = f.min; v <= f.max; v++) allVals.push(v);
        var isAll = (expanded.length === allVals.length);
        var isStep = val.indexOf('/') !== -1 && val.indexOf(',') === -1;
        var isRange = val.indexOf('-') !== -1 && val.indexOf('/') === -1 && val.indexOf(',') === -1 && !isAll;

        if (isAll) {
            document.getElementById('cb-mode-' + f.key).value = 'every';
            onModeChange(f.key);
        } else if (isStep) {
            document.getElementById('cb-mode-' + f.key).value = 'step';
            onModeChange(f.key);
            var stepParts = val.split('/');
            var base = stepParts[0];
            var stepVal = stepParts[1];
            var baseEl = document.getElementById('cb-step-' + f.key + '-base');
            var stepEl = document.getElementById('cb-step-' + f.key + '-val');
            if (baseEl) baseEl.value = base;
            if (stepEl) stepEl.value = stepVal;
        } else if (isRange) {
            document.getElementById('cb-mode-' + f.key).value = 'range';
            onModeChange(f.key);
            var rr = val.split('-');
            var fromEl = document.getElementById('cb-range-' + f.key + '-from');
            var toEl = document.getElementById('cb-range-' + f.key + '-to');
            if (fromEl) fromEl.value = parseInt(rr[0], 10);
            if (toEl) toEl.value = parseInt(rr[1], 10);
        } else {
            document.getElementById('cb-mode-' + f.key).value = 'specific';
            onModeChange(f.key);
            var cbs = document.querySelectorAll('.cb-check[data-field="' + f.key + '"]');
            cbs.forEach(function (cb) { cb.checked = expanded.indexOf(parseInt(cb.value)) !== -1; });
        }
    });
    updateExpr();
}

function setQuickPreset(expr) {
    var parts = expr.split(' ');
    if (parts.length === 5) applyPartsToUI(parts);
}

function buildQuickPresets() {
    var box = document.getElementById('cb-quick-presets');
    QUICK_PRESETS.forEach(function (p) {
        var b = document.createElement('button');
        b.type = 'button';
        b.className = 'btn btn-outline-light btn-sm';
        b.style.fontSize = '.7rem';
        b.textContent = p[1];
        if (p[0] !== 'custom') {
            b.onclick = function () { setQuickPreset(p[0]); };
        } else {
            b.classList.add('border-dashed');
        }
        box.appendChild(b);
    });
}

buildFieldUI();
buildQuickPresets();
setQuickPreset('*/15 * * * *');
</script>
<?php page_footer(); ?>
