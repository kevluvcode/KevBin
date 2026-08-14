<?php
require_once __DIR__ . '/../../functions.php';

start_session();
page_header('CSS Gradient Generator');
?>
<div class="container" style="max-width: 900px;">
    <h1 class="h4 mb-1 reveal in-view">🎨 CSS Gradient Generator</h1>
    <p class="text-secondary mb-4 reveal in-view">Build a linear or radial CSS gradient with a live preview, then copy the code. Nothing leaves your browser.</p>

    <div class="card reveal">
        <div class="card-body">
            <div class="row g-4">
                <div class="col-md-5">
                    <div class="mb-3">
                        <label class="form-label">Type</label>
                        <select id="gd-type" class="form-select" onchange="makeGrad()">
                            <option value="linear">Linear</option>
                            <option value="radial">Radial</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Angle (linear): <b id="gd-angle-val">135</b>°</label>
                        <input type="range" class="form-range" id="gd-angle" min="0" max="360" value="135" oninput="$('gd-angle-val').textContent=this.value;makeGrad()">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Color 1</label>
                        <input id="gd-c1" class="form-control" type="color" value="#5865f2" oninput="makeGrad()">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Color 2</label>
                        <input id="gd-c2" class="form-control" type="color" value="#9146ff" oninput="makeGrad()">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Color 3 (optional)</label>
                        <input id="gd-c3" class="form-control" type="color" value="#ff0000" onchange="makeGrad()">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Stops</label>
                        <div class="d-flex gap-2">
                            <input id="gd-s2" class="form-control" type="number" min="0" max="100" value="100" oninput="makeGrad()">
                            <input id="gd-s3" class="form-control" type="number" min="0" max="100" value="50" placeholder="3rd stop %" oninput="makeGrad()">
                        </div>
                        <div class="form-text">2nd stop % · 3rd stop %</div>
                    </div>
                </div>
                <div class="col-md-7">
                    <label class="form-label">Preview</label>
                    <div id="gd-preview" style="height:220px;border-radius:12px;border:1px solid var(--line);"></div>
                    <label class="form-label mt-3">CSS</label>
                    <div class="d-flex gap-2">
                        <textarea id="gd-css" class="form-control" rows="3" readonly style="font-family:'JetBrains Mono',monospace;font-size:.8rem;"></textarea>
                        <button class="btn btn-outline-light btn-sm text-nowrap align-self-start" onclick="copyCss()">Copy</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<script>
function makeGrad() {
    var type = $('gd-type').value;
    var c1 = $('gd-c1').value, c2 = $('gd-c2').value;
    var s2 = Math.max(0, Math.min(100, parseInt($('gd-s2').value, 10) || 100));
    var use3 = $('gd-c3').value !== '#ff0000';
    var css;
    if (use3) {
        var s3 = Math.max(0, Math.min(100, parseInt($('gd-s3').value, 10) || 50));
        var parts = [c1 + ' 0%', c3() + ' ' + s3 + '%', c2 + ' ' + s2 + '%'];
        if (s2 <= s3) parts = [c1 + ' 0%', c2 + ' ' + s2 + '%', c3() + ' ' + s3 + '%'];
        css = type === 'linear'
            ? 'linear-gradient(' + $('gd-angle').value + 'deg, ' + parts.join(', ') + ')'
            : 'radial-gradient(circle, ' + parts.join(', ') + ')';
    } else {
        css = type === 'linear'
            ? 'linear-gradient(' + $('gd-angle').value + 'deg, ' + c1 + ', ' + c2 + ' ' + s2 + '%)'
            : 'radial-gradient(circle, ' + c1 + ', ' + c2 + ' ' + s2 + '%)';
    }
    function c3() { return $('gd-c3').value; }
    $('gd-preview').style.background = css;
    $('gd-css').value = 'background: ' + css + ';';
}
function copyCss() {
    var t = $('gd-css');
    t.select();
    if (navigator.clipboard) navigator.clipboard.writeText(t.value);
    else document.execCommand('copy');
}
// force 3rd color to be "used" only via change event (default red = off)
(function () {
    var c3 = $('gd-c3');
    var last = '#ff0000';
    c3.oninput = function () {
        if (c3.value === '#ff0000') { last = '#ff0000'; }
        makeGrad();
    };
})();
makeGrad();
function $(id) { return document.getElementById(id); }
</script>
<?php page_footer(); ?>