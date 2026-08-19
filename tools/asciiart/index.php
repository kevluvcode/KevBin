<?php
require_once __DIR__ . '/../../functions.php';
require_once __DIR__ . '/figlet.php';

start_session();
page_header('ASCII Art Generator');

$render = '';
$curFont = 'Standard';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $curFont = KbFiglet::sanitizeName((string)($_POST['aa_font'] ?? 'Standard'));
    $txt = (string)($_POST['aa_in'] ?? '');
    if ($txt !== '') {
        try {
            $render = KbFiglet::render($txt, $curFont);
        } catch (Throwable $e) {
            $render = 'Font error: ' . htmlspecialchars($e->getMessage());
        }
    }
}
$fonts = KbFiglet::fontList();
?>
<div class="container" style="max-width: 940px;">
    <h1 class="h4 mb-1 reveal in-view">🎨 ASCII Art Generator</h1>
    <p class="text-secondary mb-3 reveal in-view">Turn <strong>text</strong> into stylized FIGlet titles — <span id="aa-count"></span> classic fonts, all rendered server-side. Or convert any local <strong>image</strong> into black-and-white block art in your browser. Output is plain monospace text you can drop into pastes, code comments or the sign-up box.</p>

    <form method="post" class="reveal in-view">
        <div class="card"><div class="card-body">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Text to stylize (one line per block)</label>
                    <textarea id="aa-in" name="aa_in" class="form-control" rows="3" style="font-family:'JetBrains Mono',monospace;font-size:.85rem;"><?php echo htmlspecialchars($_POST['aa_in'] ?? 'KEVBIN'); ?></textarea>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Font</label>
                    <input id="aa-filter" type="search" class="form-control mb-1" placeholder="Filter font names…" autocomplete="off" oninput="filterFonts()">
                    <select id="aa-font" name="aa_font" class="form-select mb-2" onchange="this.blur()">
                        <?php foreach ($fonts as $f): ?>
                            <option value="<?php echo htmlspecialchars($f['name']); ?>" data-kb="<?php echo htmlspecialchars($f['kb']); ?>" <?php echo $f['name'] === $curFont ? 'selected' : ''; ?>><?php echo htmlspecialchars($f['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <div class="d-flex gap-2 mb-2">
                        <button type="submit" class="btn btn-primary btn-sm">Render</button>
                        <button type="button" class="btn btn-outline-light btn-sm" id="aa-copy-btn" onclick="aaCopy()">Copy</button>
                    </div>
                    <label class="form-label mb-1">Image → ASCII (optional, local only)</label>
                    <input type="file" id="aa-file" class="form-control form-control-sm" accept="image/*" onchange="aaImage(this.files[0])">
                </div>
            </div>
            <label class="form-label mt-2 d-flex justify-content-between"><span>Output (monospace)</span><span id="aa-info" class="text-secondary small"></span></label>
            <textarea id="aa-out" class="form-control" rows="10" readonly style="font-family:'JetBrains Mono',monospace;font-size:.7rem;letter-spacing:0;line-height:1.05;white-space:pre;overflow-x:auto;"><?php echo $render !== '' ? htmlspecialchars($render) : ''; ?></textarea>
        </div></div>
    </form>

    <p class="text-secondary small mt-3 mb-0">FIGlet .flf fonts render on the server, so the whole library loads instantly — faster and more reliable than shipping megabytes of JavaScript.</p>
</div>

<script>
function filterFonts() {
    var q = document.getElementById('aa-filter').value.trim().toLowerCase();
    var sel = document.getElementById('aa-font');
    var shown = 0;
    for (var i = 0; i < sel.options.length; i++) {
        var match = !q || sel.options[i].text.toLowerCase().indexOf(q) !== -1;
        sel.options[i].style.display = match ? '' : 'none';
        if (match) shown++;
    }
    document.getElementById('aa-count').textContent = '';
}
function aaCopy() {
    var el = document.getElementById('aa-out');
    var btn = document.getElementById('aa-copy-btn');
    var txt = el.value;
    if (!txt) return;
    if (navigator.clipboard) {
        navigator.clipboard.writeText(txt).then(function () { aaCopied(btn); });
    } else {
        el.select();
        document.execCommand('copy');
        aaCopied(btn);
    }
}
function aaCopied(btn) {
    if (!btn) return;
    var orig = btn.textContent;
    btn.textContent = 'Copied!';
    btn.classList.add('active');
    setTimeout(function () { btn.textContent = orig; btn.classList.remove('active'); }, 1500);
    aaInfo();
}
function aaInfo() {
    var v = document.getElementById('aa-out').value;
    var el = document.getElementById('aa-info');
    if (!v) { el.textContent = ''; return; }
    var w = 0;
    v.split('\n').forEach(function (l) { w = Math.max(w, l.length); });
    el.textContent = w + ' chars wide × ' + v.split('\n').length + ' rows';
}
function aaImage(file) {
    if (!file) return;
    var img = new Image();
    var url = URL.createObjectURL(file);
    img.onload = function () {
        var w = 110, h = Math.max(10, Math.floor(img.height / img.width * w * 0.5));
        var cv = document.createElement('canvas');
        cv.width = w; cv.height = h;
        var ctx = cv.getContext('2d');
        ctx.drawImage(img, 0, 0, w, h);
        var data = ctx.getImageData(0, 0, w, h).data;
        var ramp = ' .:-=+*#%@';
        var out = '';
        for (var y = 0; y < h; y++) {
            for (var x = 0; x < w; x++) {
                var i = (y * w + x) * 4;
                var lum = (0.299 * data[i] + 0.587 * data[i + 1] + 0.114 * data[i + 2]) / 255;
                out += ramp[Math.floor(lum * (ramp.length - 1))];
            }
            out += '\n';
        }
        URL.revokeObjectURL(url);
        document.getElementById('aa-out').value = out;
        document.getElementById('aa-info').textContent = w + '×' + h + ' image → ascii';
    };
    img.src = url;
}
document.getElementById('aa-count').textContent = document.getElementById('aa-font').options.length + ' fonts';
aaInfo();
</script>
<?php page_footer(); ?>