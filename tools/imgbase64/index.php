<?php
require_once __DIR__ . '/../../functions.php';

start_session();
page_header('Image to Base64');
?>
<div class="container" style="max-width: 800px;">
    <h1 class="h4 mb-1 reveal in-view">🖼 Image ↔ Base64</h1>
    <p class="text-secondary mb-4 reveal in-view">Encode an image to a base64 data URI for embedding in HTML/CSS, or decode a data URI back into a downloadable file. Everything happens in your browser.</p>

    <div class="card reveal">
        <div class="card-body">
            <h2 class="h6 mb-3">Encode image → data URI</h2>
            <div class="d-flex gap-2 align-items-center mb-2">
                <input id="bi-file" class="form-control" type="file" accept="image/*" onchange="encodeImg()">
                <label class="form-check-label small text-nowrap"><input class="form-check-input" type="checkbox" id="bi-compress" onchange="if(window._biFile)encodeImg()"> Resize to 800px max</label>
            </div>
            <div class="d-flex gap-2 mb-2 flex-wrap align-items-center">
                <img id="bi-preview" style="max-height:120px;border-radius:8px;border:1px solid var(--line);display:none;">
                <div id="bi-size" class="text-secondary small"></div>
            </div>
            <textarea id="bi-out" class="form-control mb-2" rows="5" readonly style="font-family:'JetBrains Mono',monospace;font-size:.75rem;" placeholder="data URI appears here"></textarea>
            <div class="d-flex gap-2 flex-wrap">
                <button class="btn btn-outline-light btn-sm" onclick="biCopy()">Copy</button>
                <a id="bi-dl" class="btn btn-outline-light btn-sm" download="image.datauri.txt" style="pointer-events:none;opacity:.5;">Download .txt</a>
            </div>

            <hr style="border-color:var(--line);">
            <h2 class="h6 mb-3">Decode data URI → file</h2>
            <textarea id="bi-in" class="form-control mb-2" rows="4" style="font-family:'JetBrains Mono',monospace;font-size:.75rem;" placeholder="Paste a data:image/...;base64,... URI and press Decode"></textarea>
            <div class="d-flex gap-2 flex-wrap">
                <button class="btn btn-primary btn-sm" onclick="decodeImg()">Decode</button>
                <a id="bi-dld" class="btn btn-outline-light btn-sm" download="decoded-image" style="pointer-events:none;opacity:.5;">Download image</a>
                <span id="bi-decmsg" class="text-secondary small align-self-center"></span>
            </div>
        </div>
    </div>
</div>
<script>
window._biFile = null;
function encodeImg() {
    var f = $('bi-file').files[0];
    if (!f) return;
    window._biFile = f;
    var reader = new FileReader();
    reader.onload = function (ev) {
        var dataUri = ev.target.result;
        if ($('bi-compress').checked) {
            var img = new Image();
            img.onload = function () {
                var maxW = 800;
                var scale = Math.min(1, maxW / img.width);
                var cv = document.createElement('canvas');
                cv.width = Math.round(img.width * scale);
                cv.height = Math.round(img.height * scale);
                cv.getContext('2d').drawImage(img, 0, 0, cv.width, cv.height);
                dataUri = cv.toDataURL('image/jpeg', 0.85);
                showData(dataUri, true);
            };
            img.src = dataUri;
        } else {
            showData(dataUri, false);
        }
    };
    reader.readAsDataURL(f);
}
function showData(uri, resized) {
    $('bi-out').value = uri;
    $('bi-preview').src = uri;
    $('bi-preview').style.display = 'inline-block';
    var kb = Math.round(uri.length * 0.75 / 1024);
    $('bi-size').textContent = '~' + kb + ' KB' + (resized ? ' (resized / recompressed)' : ' (original)');
    $('bi-dl').href = URL.createObjectURL(new Blob([uri], { type: 'text/plain' }));
    $('bi-dl').style.pointerEvents = 'auto';
    $('bi-dl').style.opacity = '1';
}
function biCopy() {
    var t = $('bi-out');
    t.select();
    if (navigator.clipboard) navigator.clipboard.writeText(t.value);
    else document.execCommand('copy');
}
function decodeImg() {
    var uri = $('bi-in').value.trim();
    var msg = $('bi-decmsg');
    var match = uri.match(/^data:([\w/+-]+);base64,(.*)$/s);
    if (!match) { msg.textContent = 'Not a valid base64 data URI.'; return; }
    var mime = match[1];
    var b64 = match[2];
    var bin = atob(b64);
    var bytes = new Uint8Array(bin.length);
    for (var i = 0; i < bin.length; i++) bytes[i] = bin.charCodeAt(i);
    var blob = new Blob([bytes], { type: mime });
    var url = URL.createObjectURL(blob);
    var ext = (mime.split('/')[1] || 'png').replace('jpeg', 'jpg').replace('svg+xml', 'svg');
    var a = $('bi-dld');
    a.href = url;
    a.download = 'decoded-image.' + ext;
    a.style.pointerEvents = 'auto';
    a.style.opacity = '1';
    msg.textContent = 'OK — ' + mime + ', ' + Math.round(blob.size / 1024) + ' KB.';
}
function $(id) { return document.getElementById(id); }
</script>
<?php page_footer(); ?>