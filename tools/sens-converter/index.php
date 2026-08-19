<?php
require_once __DIR__ . '/../../functions.php';

start_session();
page_header('Sens Converter');
?>
<style>
.sg-chip { display:inline-block; padding:2px 10px; border-radius:99px; font-size:.78rem; margin:2px; cursor:pointer; border:1px solid var(--line); background:rgba(255,255,255,.03); transition:all .15s ease; }
.sg-chip:hover { border-color:#5865f2; color:#fff; }
.sg-chip.on { background:linear-gradient(135deg,rgba(88,101,242,.2),rgba(145,70,255,.14)); border-color:#5865f2; color:#fff; }
.sg-approx { font-size:.7rem; color:var(--dim); }
</style>
<div class="container" style="max-width: 980px;">
    <h1 class="h4 mb-2 reveal in-view">🎯 Sensitivity Converter &amp; Best Sens Calculator</h1>
    <p class="text-secondary mb-3 reveal in-view">Convert your mouse sensitivity between games by matching the 360&deg;-turn distance, and work out the best sensitivity for a target cm/360 or eDPI. Yaw values come from the community conversion database and are editable — including the approximate Roblox / Minecraft mappings.</p>

    <div class="card reveal in-view"><div class="card-body">
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label small text-secondary mb-1">From game</label>
                <select class="form-select" id="gFrom"></select>
                <label class="form-label small text-secondary mt-2 mb-1">Sensitivity</label>
                <input class="form-control" id="sens" type="number" step="0.001" min="0.001" value="2.0">
                <label class="form-label small text-secondary mt-2 mb-1">DPI</label>
                <input class="form-control" id="dpi" type="number" step="50" min="50" value="800">
                <label class="form-label small text-secondary mt-2 mb-1">Yaw (deg/unit) — editable <span class="sg-approx" id="yawFromNote"></span></label>
                <input class="form-control" id="yawFrom" type="number" step="0.0001" value="0.022">
            </div>
            <div class="col-md-6">
                <label class="form-label small text-secondary mb-1">To game</label>
                <select class="form-select" id="gTo"></select>
                <label class="form-label small text-secondary mt-2 mb-1">Converted sensitivity</label>
                <div class="input-group">
                    <input class="form-control form-control-lg text-success fw-bold" id="outSens" readonly>
                    <button class="btn btn-outline-light" type="button" onclick="copyOut()">Copy</button>
                </div>
                <label class="form-label small text-secondary mt-2 mb-1">Yaw (deg/unit) — editable <span class="sg-approx" id="yawToNote"></span></label>
                <input class="form-control" id="yawTo" type="number" step="0.0001" value="0.07">
            </div>
        </div>
        <div class="form-check mt-3">
            <input class="form-check-input" type="checkbox" id="fovScale" checked>
            <label class="form-check-label" for="fovScale">Scale by FOV (multiplies across vertical FOV difference)</label>
        </div>
        <div class="row g-2 mt-1">
            <div class="col-md-6"><label class="form-label small text-secondary mb-1">From FOV (vertical)</label><input class="form-control" id="fovA" type="number" step="1" value="90"></div>
            <div class="col-md-6"><label class="form-label small text-secondary mb-1">To FOV (vertical)</label><input class="form-control" id="fovB" type="number" step="1" value="103"></div>
        </div>
        <div id="robNote" class="alert alert-warning mt-3 mb-0 small d-none">Roblox doesn't expose a uniform yaw rate — its in-game camera sensitivity is roughly linear, and the value above is the community's best estimate. Fine-tune it from your own testing.</div>
    </div></div>

    <div class="card mt-3 reveal in-view"><div class="card-body">
        <h2 class="h6 mb-2">Results</h2>
        <div class="row g-2 text-center">
            <div class="col-6 col-md-3"><div class="p-3" style="background:rgba(88,101,242,.08);border:1px solid var(--line);border-radius:12px;"><div class="fs-5 fw-bold" id="r360">—</div><div class="small text-secondary">cm / 360&deg;</div></div></div>
            <div class="col-6 col-md-3"><div class="p-3" style="background:rgba(38,208,124,.06);border:1px solid var(--line);border-radius:12px;"><div class="fs-5 fw-bold" id="reDPI">—</div><div class="small text-secondary">eDPI (sens &times; DPI)</div></div></div>
            <div class="col-6 col-md-3"><div class="p-3" style="background:rgba(255,255,255,.03);border:1px solid var(--line);border-radius:12px;"><div class="fs-5 fw-bold" id="rinches">—</div><div class="small text-secondary">in / 360&deg;</div></div></div>
            <div class="col-6 col-md-3"><div class="p-3" style="background:rgba(255,255,255,.03);border:1px solid var(--line);border-radius:12px;"><div class="fs-5 fw-bold" id="rcounts">—</div><div class="small text-secondary">counts / 360&deg;</div></div></div>
        </div>
    </div></div>

    <div class="card mt-3 reveal in-view"><div class="card-body">
        <h2 class="h6 mb-2">🧪 Best-sensitivity presets <span class="text-secondary small">— for your DPI, hitting a target turn distance</span></h2>
        <p class="small text-secondary mb-2">Widely-used rough targets for hipfire: <strong>Tactical</strong> (CS/Valorant-style) ~30–40 cm, <strong>Arena</strong> (Apex/Fortnite) ~25–30 cm, <strong>Fast</strong> ~18–24 cm. Results below use the <em>to-game</em> yaw.</p>
        <div class="row g-2">
            <div class="col-md-4 col-6">🪖 Tactical (35 cm)
                <input class="form-control form-control-sm mt-1 mb-1" id="outTac" readonly>
            </div>
            <div class="col-md-4 col-6">⚡ Arena (27 cm)
                <input class="form-control form-control-sm mt-1 mb-1" id="outArena" readonly>
            </div>
            <div class="col-md-4 col-6">🔥 Fast (20 cm)
                <input class="form-control form-control-sm mt-1 mb-1" id="outFast" readonly>
            </div>
        </div>
        <label class="form-label small text-secondary mt-2 mb-1">Or aim for a custom cm/360:</label>
        <div class="input-group" style="max-width:340px;">
            <input class="form-control" id="customCm" type="number" step="0.5" min="1" value="30">
            <button class="btn btn-primary" id="btnCustom" type="button">Compute sens</button>
            <input class="form-control" id="outCustom" readonly style="max-width:150px;">
        </div>
    </div></div>

    <div class="card mt-3 reveal in-view"><div class="card-body">
        <h2 class="h6 mb-2">🗂 Games reference</h2>
        <div class="row g-2" id="chipBox"></div>
        <p class="small text-secondary mt-3 mb-0">Formula: 360&deg; distance (cm) = 914.4 &divide; (sens &times; yaw &times; DPI). Converting <em>Game A → Game B</em> keeps that distance equal, then optionally normalizes vertical FOV. Values marked "approx" are community estimates — the yaw inputs are always editable above.</p>
    </div></div>
</div>
<script>
var GAMES = [
    { n: 'Counter-Strike 2 / CS:GO', y: 0.022, f: 106, a: 0 },
    { n: 'Valorant', y: 0.07, f: 103, a: 0 },
    { n: 'Apex Legends', y: 0.022, f: 106, a: 0 },
    { n: 'Overwatch 2', y: 0.0066, f: 103, a: 0 },
    { n: 'Fortnite', y: 0.0112, f: 102, a: 0 },
    { n: 'Team Fortress 2', y: 0.022, f: 90, a: 0 },
    { n: 'Call of Duty (MW2019+)', y: 0.022, f: 80, a: 0 },
    { n: 'PUBG', y: 0.022, f: 90, a: 0 },
    { n: 'Rainbow Six Siege', y: 0.0055, f: 90, a: 1 },
    { n: 'Rust', y: 0.0045, f: 90, a: 1 },
    { n: 'Battlefield (2042 / V)', y: 0.022, f: 90, a: 0 },
    { n: 'Minecraft (Java, slider 0.5)', y: 0.024, f: 90, a: 1 },
    { n: 'Roblox', y: 0.06, f: 90, a: 2 },
    { n: 'osu!', y: 0.0035, f: 90, a: 1 },
];
var selFrom = document.getElementById('gFrom');
var selTo = document.getElementById('gTo');
function fill(){
    var opts = GAMES.map(function(g,i){ return '<option value="'+i+'">'+g.n+(g.a?' (approx)':'')+'</option>'; }).join('');
    selFrom.innerHTML = opts; selTo.innerHTML = opts;
    selFrom.value = 0; selTo.value = 1;
    chips();
}
function chips(){
    var box = document.getElementById('chipBox');
    box.innerHTML = GAMES.map(function(g,i){
        return '<span class="sg-chip" onclick="setPair('+i+')">'+esc(g.n)+'</span>';
    }).join('');
}
function setPair(i){
    selFrom.value = i;
    selTo.value = i === GAMES.length-1 ? i-1 : i+1;
    sync();
}
function noteFor(g){
    if (g.a === 2) return ' — Roblox, community estimate';
    if (g.a === 1) return ' — approx';
    return '';
}
function sync(){
    var a = GAMES[+selFrom.value], b = GAMES[+selTo.value];
    document.getElementById('yawFrom').value = a.y;
    document.getElementById('yawTo').value = b.y;
    document.getElementById('yawFromNote').textContent = noteFor(a);
    document.getElementById('yawToNote').textContent = noteFor(b);
    document.getElementById('fovA').value = a.f;
    document.getElementById('fovB').value = b.f;
    document.getElementById('robNote').classList.toggle('d-none', !(a.a === 2 || b.a === 2));
    calc();
}
function cm360(sens, yaw, dpi){
    if (!sens || !yaw || !dpi) return 0;
    var cm = 914.4 / (sens * yaw * dpi);
    return cm > 0 ? cm : 0;
}
function calc(){
    var sens = parseFloat(document.getElementById('sens').value) || 0;
    var dpi = parseFloat(document.getElementById('dpi').value) || 0;
    var yA = parseFloat(document.getElementById('yawFrom').value) || 0;
    var yB = parseFloat(document.getElementById('yawTo').value) || 0;
    var fA = parseFloat(document.getElementById('fovA').value) || 90;
    var fB = parseFloat(document.getElementById('fovB').value) || 90;
    var scale = document.getElementById('fovScale').checked ? (fB / fA) : 1;
    var cmA = cm360(sens, yA, dpi);
    var conv = yA && yB ? (cmA > 0 ? (914.4 / (cmA * yB * dpi)) : 0) * scale : 0;
    var cmB = cm360(conv, yB, dpi);
    document.getElementById('outSens').value = conv > 0 ? conv.toFixed(4) : '';
    document.getElementById('r360').textContent = cmB > 0 ? cmB.toFixed(2) + ' cm' : '—';
    document.getElementById('rinches').textContent = cmB > 0 ? (cmB / 2.54).toFixed(1) + ' in' : '—';
    document.getElementById('rcounts').textContent = conv > 0 ? Math.round(360 / (conv * yB)) + '' : '—';
    document.getElementById('reDPI').textContent = (conv * dpi).toFixed(0);
    var presets = { outTac: 35, outArena: 27, outFast: 20 };
    for (var k in presets) {
        var s = yB ? (914.4 / (presets[k] * yB * dpi)) : 0;
        document.getElementById(k).value = s > 0 ? s.toFixed(4) : '';
    }
}
document.getElementById('btnCustom').onclick = function(){
    var cm = parseFloat(document.getElementById('customCm').value) || 30;
    var dpi = parseFloat(document.getElementById('dpi').value) || 0;
    var yB = parseFloat(document.getElementById('yawTo').value) || 0;
    var s = yB && dpi ? (914.4 / (cm * yB * dpi)) : 0;
    document.getElementById('outCustom').value = s > 0 ? s.toFixed(4) : '';
};
function copyOut(){ var t=document.getElementById('outSens'); t.select(); if(navigator.clipboard) navigator.clipboard.writeText(t.value); else document.execCommand('copy'); }
function esc(s){ var e=document.createElement('div'); e.appendChild(document.createTextNode(s==null?'':String(s))); return e.innerHTML; }
['sens','dpi','yawFrom','yawTo','fovA','fovB'].forEach(function(id){ document.getElementById(id).addEventListener('input', calc); });
document.getElementById('fovScale').addEventListener('change', calc);
selFrom.addEventListener('change', sync);
selTo.addEventListener('change', sync);
fill(); sync();
</script>
<?php page_footer(); ?>