<?php
require_once __DIR__ . '/../../functions.php';

start_session();
$GLOBALS['_meta'] = [
    'description' => 'Free online Base64 encoder/decoder. Encode any text or binary to Base64 and decode back. 100% client-side — nothing is uploaded.',
    'keywords' => 'base64, base64 decoder, base64 encoder, decode base64, encode base64, base64 online',
];
page_header('Base64 Decoder — Encode & Decode Base64 Online');
?>
<div class="container" style="max-width: 980px;">
    <h1 class="h4 mb-2 reveal in-view">Base64 Decoder / Encoder</h1>
    <p class="text-secondary mb-1 reveal in-view">Free, browser-only Base64 encoder and decoder. Paste Base64 to decode it back to text, or paste plain text to encode it — both directions, live, with a copy button. Nothing you type ever leaves your device.</p>
    <p class="text-secondary mb-4 reveal in-view">Base64 is a binary-to-text encoding used everywhere on the web: data URIs, email attachments, API pagination tokens and JWT signatures. This tool handles UTF-8 (emoji and accents included) and gives you the standard <code>+/</code> alphabet.</p>

    <div class="card reveal in-view">
        <div class="card-body">
            <div class="d-flex flex-wrap gap-2 align-items-center mb-3">
                <select id="b64-mode" class="form-select" style="max-width:180px;" onchange="runB64()">
                    <option value="decode">Decode Base64 → text</option>
                    <option value="encode">Encode text → Base64</option>
                </select>
                <button class="btn btn-outline-light btn-sm" onclick="swapB64()">⇅ Swap</button>
            </div>
            <label class="form-label small text-secondary">Input</label>
            <textarea id="b64-in" class="form-control mb-2" rows="5" placeholder="Paste Base64 or text here…" oninput="runB64()"></textarea>
            <label class="form-label small text-secondary">Output</label>
            <div class="input-group">
                <textarea id="b64-out" class="form-control" rows="5" readonly style="font-family:'JetBrains Mono',monospace;font-size:.85rem;"></textarea>
                <button class="btn btn-outline-light" onclick="copyB64()">Copy</button>
            </div>
            <div id="b64-err" class="form-text mt-2 text-danger d-none"></div>
        </div>
    </div>

    <h2 class="h6 mt-4 reveal in-view" style="border-bottom:1px solid var(--line);padding-bottom:.5rem;">What is Base64?</h2>
    <p class="text-secondary small reveal in-view">Base64 encodes arbitrary bytes into 64 printable ASCII characters (A–Z, a–z, 0–9, <code>+</code> and <code>/</code>) so binary data can travel safely through text-only channels — emails, JSON, HTTP headers and URLs. Every 3 input bytes become 4 Base64 characters, with <code>=</code> padding when needed. It is an <em>encoding</em>, not encryption: decoding Base64 reveals the original data instantly.</p>
    <h2 class="h6 mt-4 reveal in-view" style="border-bottom:1px solid var(--line);padding-bottom:.5rem;">Example</h2>
    <p class="text-secondary small reveal in-view">Encode <code>Hello, KevBin!</code> → <code>SGVsbG8sIEtldkJpbiE=</code>. Paste the encoded string back into the decoder to recover the original text. Try it with your own secrets to see how quickly they can be read by anyone.</p>
</div>

<script>
function $b(id) { return document.getElementById(id); }
function runB64() {
    var mode = $b('b64-mode').value, inp = $b('b64-in').value, out = $b('b64-out'), err = $b('b64-err');
    err.classList.add('d-none');
    if (!inp) { out.value = ''; return; }
    try {
        if (mode === 'decode') {
            var bin = atob(inp.replace(/\s+/g, ''));
            var bytes = new Uint8Array(bin.length);
            for (var i = 0; i < bin.length; i++) bytes[i] = bin.charCodeAt(i);
            out.value = new TextDecoder().decode(bytes);
        } else {
            var bytes2 = new TextEncoder().encode(inp);
            var bin2 = '';
            for (var j = 0; j < bytes2.length; j++) bin2 += String.fromCharCode(bytes2[j]);
            out.value = btoa(bin2);
        }
    } catch (e) { out.value = ''; err.textContent = 'Invalid Base64 input — check for typos or missing padding (=).'; err.classList.remove('d-none'); }
}
function swapB64() {
    var tmp = $b('b64-in').value;
    $b('b64-in').value = $b('b64-out').value;
    $b('b64-out').value = '';
    $b('b64-mode').value = $b('b64-mode').value === 'decode' ? 'encode' : 'decode';
    runB64();
}
function copyB64() { var t = $b('b64-out'); t.select(); document.execCommand('copy'); }
</script>
<?php page_footer(); ?>