<?php
require_once __DIR__ . '/../../functions.php';

start_session();
page_header('Decrypter');
?>
<div class="container" style="max-width: 1000px;">
    <h1 class="h4 mb-1 reveal in-view">🔓 Cipher Decrypter</h1>
    <p class="text-secondary mb-4 reveal in-view">Throws a pile of encodings and classic ciphers at whatever you paste — base64, hex, ROT13, Caesar brute-force, XOR, Morse, binary and more — and ranks what decodes into the most "real text". All local in your browser.</p>

    <div class="card mb-3 reveal in-view"><div class="card-body">
        <label class="form-label">Encrypted / encoded text</label>
        <textarea id="dec-in" class="form-control mb-2" rows="5" style="font-family:'JetBrains Mono',monospace;font-size:.8rem;" placeholder="Paste base64, hex, ROT13, Caesar, XOR, Morse, reversed text, URL encoding, …"></textarea>
        <div class="d-flex gap-2 flex-wrap align-items-center">
            <button class="btn btn-primary" onclick="crack()">Try to decrypt</button>
            <label class="form-check-label small"><input type="checkbox" id="dec-deep" class="form-check-input me-1">Also try nested decodes (e.g. base64 of base64)</label>
        </div>
    </div></div>

    <div id="dec-out"></div>

    <div class="alert alert-secondary reveal in-view">
        <small>⚠️ <strong>Honest limits:</strong> real encryption (AES, RSA, bcrypt…) is computationally
        infeasible to break without the key — no website can do it either. This tool recovers text that was
        <em>obfuscated</em> with classic ciphers or simple encodings, which is what most "encrypted" pastebin
        junk actually is. If nothing sensible comes out, the text is likely truly encrypted.</small>
    </div>
</div>

<script>
function esc(x) { return String(x).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }
function printableRatio(s) {
    if (!s) return 0;
    var ok = 0, total = 0;
    for (var i = 0; i < s.length && i < 4000; i++) {
        var c = s.charCodeAt(i);
        total++;
        if ((c >= 32 && c <= 126) || c === 9 || c === 10 || c === 13 || c >= 160) ok++;
    }
    return ok / total;
}
function wordScore(s) {
    if (!s) return 0;
    var words = s.toLowerCase().match(/[a-z']{3,}/g) || [];
    var common = ['the','and','you','that','was','for','are','with','this','have','from','your','not','can','but','all','one','what','were','when','there','their','them','they','she','him','her','who','will','would','about','because','hello','password','secret','please','people','with','just','like','make','know','time','back','look','come','say','well','over','even','find','own','old','give','need','help','code'];
    var hits = 0;
    for (var i = 0; i < words.length; i++) if (common.indexOf(words[i]) !== -1) hits++;
    return hits + Math.min(3, words.length / 8);
}
function render(name, text, score, extra) {
    var box = document.createElement('div');
    box.className = 'card mb-2';
    box.innerHTML = '<div class="card-body py-2"><div class="d-flex justify-content-between align-items-center flex-wrap gap-2">' +
        '<strong class="small">' + name + '</strong>' +
        '<span class="badge ' + (score > 2 ? 'bg-success' : (score > 0.5 ? 'bg-warning text-dark' : 'bg-secondary')) + '">score ' + score.toFixed(2) + '</span></div>' +
        '<pre class="small mt-1 mb-0" style="white-space:pre-wrap;word-break:break-word;max-height:180px;overflow:auto;">' + esc(text.slice(0, 3000)) + '</pre>' +
        (extra ? '<div class="text-secondary small mt-1">' + extra + '</div>' : '') + '</div>';
    return box;
}
function tryDecode(s) {
    var results = [];
    var clean = s.trim();

    // --- encodings ---
    var b64 = clean.replace(/\s+/g, '');
    if (/^[A-Za-z0-9+/]+={0,3}$/.test(b64) && b64.length % 4 === 0 && b64.length >= 8) {
        try { var d = atob(b64); results.push(['Base64', d, printableRatio(d)]); } catch (e) {}
    }
    var b64url = clean.replace(/-/g, '+').replace(/_/g, '/');
    if (/^[A-Za-z0-9+/]+={0,3}$/.test(b64url) && b64url.length % 4 === 0 && b64url.length >= 8) {
        try { var d2 = atob(b64url); if (printableRatio(d2) > printableRatio(clean) * 0.9) results.push(['Base64 (URL-safe)', d2, printableRatio(d2)]); } catch (e) {}
    }
    if (/^(?:[0-9a-f]{2}\s*)+$/i.test(clean)) {
        var h = clean.replace(/\s+/g, '');
        var hexOut = '';
        for (var i = 0; i + 1 < h.length; i += 2) hexOut += String.fromCharCode(parseInt(h.substr(i, 2), 16));
        results.push(['Hex', hexOut, printableRatio(hexOut)]);
        var xored = xorBytes(hexToBytes(h), 0);
        results.push(['Hex XOR 0x00 → text?', xored, printableRatio(xored)]);
    }
    if (/^[01\s]+$/.test(clean) && clean.replace(/\s/g, '').length % 8 === 0) {
        var bits = clean.replace(/\s/g, '');
        var binOut = '';
        for (var j = 0; j < bits.length; j += 8) binOut += String.fromCharCode(parseInt(bits.substr(j, 8), 2));
        results.push(['Binary (8-bit)', binOut, printableRatio(binOut)]);
    }
    if (/^[0-7\s]+$/.test(clean)) {
        var oct = clean.replace(/\s+/g, ' ').trim().split(' ').map(function (x) { return String.fromCharCode(parseInt(x, 8)); }).join('');
        if (oct.length > 1 && /[a-zA-Z0-9 .!?]/.test(oct)) results.push(['Octal', oct, printableRatio(oct)]);
    }
    try { var dec = decodeURIComponent(clean.replace(/\+/g, ' ')); if (dec !== clean && printableRatio(dec) > 0.5) results.push(['URL-encoded', dec, printableRatio(dec)]); } catch (e) {}
    try {
        var ent = clean.replace(/&#x([0-9a-f]+);/gi, function (_, h) { return String.fromCharCode(parseInt(h, 16)); })
                       .replace(/&#(\d+);/g, function (_, d) { return String.fromCharCode(parseInt(d, 10)); })
                       .replace(/&(amp|lt|gt|quot|apos|nbsp|copy|reg);/g, function (_, n) {
                           return { amp: '&', lt: '<', gt: '>', quot: '"', apos: "'", nbsp: ' ', copy: '©', reg: '®' }[n];
                       });
        if (ent !== clean && printableRatio(ent) > 0.5) results.push(['HTML entities', ent, printableRatio(ent)]);
    } catch (e) {}

    // --- classic ciphers ---
    var rot13 = clean.replace(/[a-zA-Z]/g, function (ch) {
        var c = ch.charCodeAt(0);
        return String.fromCharCode(((c <= 90 ? 65 : 97) + ((c - (c <= 90 ? 65 : 97) + 13) % 26)));
    });
    if (rot13 !== clean && printableRatio(rot13) > 0.5) results.push(['ROT13', rot13, printableRatio(rot13)]);

    var bestCaesar = null, bestCaesarScore = -1;
    for (var shift = 1; shift < 26; shift++) {
        var out = '';
        for (var k = 0; k < clean.length; k++) {
            var ch = clean.charCodeAt(k);
            if (ch >= 65 && ch <= 90) out += String.fromCharCode(((ch - 65 + shift) % 26) + 65);
            else if (ch >= 97 && ch <= 122) out += String.fromCharCode(((ch - 97 + shift) % 26) + 97);
            else out += clean[k];
        }
        var sc = wordScore(out) + printableRatio(out);
        if (sc > bestCaesarScore) { bestCaesarScore = sc; bestCaesar = out; }
    }
    if (bestCaesarScore > 1.5) results.push(['Caesar (best shift)', bestCaesar, bestCaesarScore]);

    var atbash = clean.replace(/[a-zA-Z]/g, function (ch) {
        var c = ch.charCodeAt(0);
        return String.fromCharCode(c <= 90 ? 90 - (c - 65) : 122 - (c - 97));
    });
    if (atbash !== clean) results.push(['Atbash', atbash, wordScore(atbash) + printableRatio(atbash)]);

    var rev = clean.split('').reverse().join('');
    if (rev !== clean) results.push(['Reversed', rev, wordScore(rev) + printableRatio(rev)]);

    // --- single-byte XOR brute force ---
    var bytes = null;
    if (/^[A-Za-z0-9+/]+={0,3}$/.test(b64) && b64.length % 4 === 0) {
        try { bytes = Array.from(atob(b64), function (c) { return c.charCodeAt(0); }); } catch (e) {}
    } else if (/^(?:[0-9a-f]{2}\s*)+$/i.test(clean)) {
        bytes = hexToBytes(clean.replace(/\s+/g, ''));
    } else {
        bytes = Array.from(clean, function (c) { return c.charCodeAt(0); });
    }
    if (bytes && bytes.length > 1) {
        var bestXor = null, bestXorScore = -1, bestXorKey = 0;
        for (var key = 0; key < 256; key++) {
            var xo = xorBytes(bytes, key);
            var xScore = wordScore(xo) + printableRatio(xo) * 2;
            if (xScore > bestXorScore) { bestXorScore = xScore; bestXor = xo; bestXorKey = key; }
        }
        if (bestXorScore > 2.5) results.push(['XOR single-byte (key 0x' + bestXorKey.toString(16).padStart(2, '0') + ')', bestXor, bestXorScore]);
    }

    // --- morse ---
    var morseMap = { '.-': 'a', '-...': 'b', '-.-.': 'c', '-..': 'd', '.': 'e', '..-.': 'f', '--.': 'g', '....': 'h', '..': 'i', '.---': 'j', '-.-': 'k', '.-..': 'l', '--': 'm', '-.': 'n', '---': 'o', '.--.': 'p', '--.-': 'q', '.-.': 'r', '...': 's', '-': 't', '..-': 'u', '...-': 'v', '.--': 'w', '-..-': 'x', '-.--': 'y', '--..': 'z', '-----': '0', '.----': '1', '..---': '2', '...--': '3', '....-': '4', '.....': '5', '-....': '6', '--...': '7', '---..': '8', '----.': '9' };
    if (/^[.\-\s\/]+$/.test(clean) && clean.indexOf(' ') !== -1) {
        var morseOut = '';
        var okMorse = true;
        clean.trim().split(/\s{2,}|\//).forEach(function (w) {
            if (!okMorse) return;
            w.trim().split(' ').forEach(function (sym) {
                if (sym === '') return;
                if (morseMap[sym]) morseOut += morseMap[sym];
                else { okMorse = false; return; }
            });
            if (okMorse) morseOut += ' ';
        });
        if (okMorse && morseOut.replace(/\s/g, '').length > 1) results.push(['Morse', morseOut.trim(), wordScore(morseOut) + printableRatio(morseOut)]);
    }
    return results;
}
function hexToBytes(h) { var a = []; for (var i = 0; i + 1 < h.length; i += 2) a.push(parseInt(h.substr(i, 2), 16)); return a; }
function xorBytes(bytes, key) { var s = ''; for (var i = 0; i < bytes.length; i++) s += String.fromCharCode(bytes[i] ^ key); return s; }
function crack() {
    var input = document.getElementById('dec-in').value;
    var out = document.getElementById('dec-out');
    out.innerHTML = '';
    if (!input.trim()) { out.innerHTML = '<div class="alert alert-secondary">Paste something first.</div>'; return; }

    var results = tryDecode(input);
    var deep = document.getElementById('dec-deep').checked;
    if (deep) {
        var layer = input, seen = [input];
        for (var depth = 0; depth < 3; depth++) {
            var next = null, nextName = null, nextScore = -1;
            tryDecode(layer).forEach(function (r) {
                if (seen.indexOf(r[1]) === -1 && r[2] > nextScore) { next = r[1]; nextName = r[0]; nextScore = r[2]; }
            });
            if (!next || next === layer) break;
            seen.push(next);
            layer = next;
            results.push(['(nested ' + (depth + 1) + ') ' + nextName, next, nextScore]);
        }
    }

    results.sort(function (a, b) { return b[2] - a[2]; });
    var top = results.length ? results[0] : null;
    if (!top) { out.innerHTML = '<div class="alert alert-warning">No encoding or classic cipher produced readable text. This is probably real encryption with a key — see the note below.</div>'; return; }

    var heading = document.createElement('div');
    heading.className = 'mb-3';
    heading.innerHTML = top[2] > 2
        ? '<span class="badge bg-success">Best guess: ' + esc(top[0]) + '</span>'
        : '<span class="badge bg-warning text-dark">Weak signal — nothing clearly readable</span>';
    out.appendChild(heading);

    var resultsSeen = [];
    for (var i = 0; i < results.length; i++) {
        var r = results[i];
        if (resultsSeen.indexOf(r[1]) !== -1) continue;
        resultsSeen.push(r[1]);
        out.appendChild(render(r[0], r[1], r[2], ''));
        if (out.children.length > 14) break;
    }
}
</script>
<?php page_footer(); ?>