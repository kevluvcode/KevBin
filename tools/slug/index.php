<?php
require_once __DIR__ . '/../../functions.php';

start_session();
page_header('Slug Generator');
?>
<div class="container" style="max-width: 800px;">
    <h1 class="h4 mb-1 reveal in-view">🔗 Slug Generator</h1>
    <p class="text-secondary mb-4 reveal in-view">Turn any text into a clean URL slug or filename. Accents are converted, everything else stripped. Runs 100% in your browser.</p>

    <div class="card reveal">
        <div class="card-body">
            <div class="mb-3">
                <label class="form-label">Input text</label>
                <input id="slug-in" class="form-control" style="font-size:.95rem;"
                    value="Héllo Wörld! This Is a Test — 100% #Slug" oninput="makeSlug()">
            </div>
            <div class="row g-3 mb-3">
                <div class="col-md-6">
                    <label class="form-label">Separator</label>
                    <select id="slug-sep" class="form-select" onchange="makeSlug()">
                        <option value="-">Hyphen (-)</option>
                        <option value="_">Underscore (_)</option>
                        <option value="">None (concatenated)</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Max length</label>
                    <input id="slug-max" class="form-control" type="number" min="1" max="200" value="60" oninput="makeSlug()">
                </div>
            </div>
            <div class="form-check mb-3">
                <input class="form-check-input" type="checkbox" id="slug-lower" checked onchange="makeSlug()">
                <label class="form-check-label" for="slug-lower">Lowercase</label>
            </div>
            <label class="form-label">Result</label>
            <div class="d-flex gap-2">
                <input id="slug-out" class="form-control" readonly style="font-family:'JetBrains Mono',monospace;font-size:.9rem;">
                <button class="btn btn-outline-light btn-sm text-nowrap" onclick="copySlug()">Copy</button>
            </div>
            <div id="slug-chars" class="text-secondary small mt-2"></div>
        </div>
    </div>
</div>
<script>
function makeSlug() {
    var s = $('slug-in').value;
    var sep = $('slug-sep').value;
    var max = Math.max(1, parseInt($('slug-max').value, 10) || 60);
    var out = s
        .normalize('NFKD')
        .replace(/[\u0300-\u036f]/g, '')
        .replace(/[^\p{L}\p{N}\s-]/gu, '')
        .trim();
    if (sep === '-') out = out.replace(/\s+/g, '-').replace(/-+/g, '-');
    else if (sep === '_') out = out.replace(/\s+/g, '_').replace(/_+/g, '_');
    else out = out.replace(/\s+/g, '');
    out = out.replace(/^[-_]+|[-_]+$/g, '');
    if ($('slug-lower').checked) out = out.toLowerCase();
    out = out.slice(0, max).replace(/[-_]+$/g, '');
    $('slug-out').value = out;
    $('slug-chars').textContent = out.length + ' characters';
}
function copySlug() {
    var t = $('slug-out');
    t.select();
    if (navigator.clipboard) navigator.clipboard.writeText(t.value);
    else document.execCommand('copy');
}
makeSlug();
function $(id) { return document.getElementById(id); }
</script>
<?php page_footer(); ?>