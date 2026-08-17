<?php
require_once __DIR__ . '/../../functions.php';

start_session();
page_header('HTTP Request Builder');
?>
<div class="container" style="max-width: 960px;">
    <h1 class="h4 mb-1 reveal in-view">⚡ HTTP Request Builder</h1>
    <p class="text-secondary mb-3 reveal in-view">Compose an HTTP request visually and generate ready-to-run <strong>curl</strong>, <strong>JavaScript fetch</strong> and <strong>Python requests</strong> code for it. Perfect for pen-test prep, manual API testing, or building the exact request a script needs. Everything is generated locally in your browser — nothing is sent anywhere by this page.</p>

    <div class="card reveal in-view"><div class="card-body">
        <div class="row g-3">
            <div class="col-md-2">
                <label class="form-label">Method</label>
                <select id="req-method" class="form-select" onchange="genReq()">
                    <option>GET</option>
                    <option>POST</option>
                    <option>PUT</option>
                    <option>PATCH</option>
                    <option>DELETE</option>
                    <option>HEAD</option>
                    <option>OPTIONS</option>
                </select>
            </div>
            <div class="col-md-10">
                <label class="form-label">URL</label>
                <input id="req-url" class="form-control" value="https://example.com/api/v1/data" oninput="genReq()">
            </div>
        </div>

        <div class="row g-3 mt-1">
            <div class="col-md-6">
                <label class="form-label">Headers (one per line: <code>Name: value</code>)</label>
                <textarea id="req-headers" class="form-control" rows="5" style="font-family:'JetBrains Mono',monospace;font-size:.85rem;" oninput="genReq()">Accept: application/json
User-Agent: KevBin-ReqBuilder</textarea>
            </div>
            <div class="col-md-6">
                <label class="form-label">Body (raw, for POST/PUT/PATCH)</label>
                <textarea id="req-body" class="form-control" rows="5" style="font-family:'JetBrains Mono',monospace;font-size:.85rem;" placeholder='{"key":"value"}' oninput="genReq()"></textarea>
            </div>
        </div>

        <div class="mt-3 d-flex flex-wrap gap-2 align-items-center">
            <label class="form-check-label small"><input type="checkbox" id="req-json" class="form-check-input me-1" onchange="genReq()"> Add JSON content-type + pretty body</label>
            <label class="form-check-label small"><input type="checkbox" id="req-insecure" class="form-check-input me-1" onchange="genReq()"> Ignore TLS errors (-k)</label>
            <label class="form-check-label small"><input type="checkbox" id="req-follow" class="form-check-input me-1" onchange="genReq()"> Follow redirects (-L)</label>
        </div>
    </div></div>

    <div class="mt-4 reveal in-view">
        <ul class="nav nav-tabs" id="req-tabs" role="tablist">
            <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tab-curl" type="button">curl</button></li>
            <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-fetch" type="button">JavaScript fetch</button></li>
            <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-python" type="button">Python requests</button></li>
        </ul>
        <div class="tab-content">
            <div class="tab-pane fade show active" id="tab-curl">
                <pre class="paste-content mt-2" style="max-height:360px;" id="out-curl"></pre>
            </div>
            <div class="tab-pane fade" id="tab-fetch">
                <pre class="paste-content mt-2" style="max-height:360px;" id="out-fetch"></pre>
            </div>
            <div class="tab-pane fade" id="tab-python">
                <pre class="paste-content mt-2" style="max-height:360px;" id="out-python"></pre>
            </div>
        </div>
        <button class="btn btn-outline-light btn-sm mt-2" onclick="copyCurrent()">Copy active tab</button>
        <button class="btn btn-outline-light btn-sm mt-2" onclick="runHere()">Run in browser (fetch)</button>
    </div>
</div>

<script>
function parseHeaders(txt) {
    return txt.split('\n').map(function (l) { return l.split(':', 2); })
        .filter(function (p) { return p.length === 2 && p[0].trim() !== ''; })
        .map(function (p) { return { name: p[0].trim(), value: p[1].trim() }; });
}
function doJSONPretty() {
    var b = document.getElementById('req-body');
    if (!b.value.trim()) return;
    try {
        b.value = JSON.stringify(JSON.parse(b.value), null, 2);
    } catch (e) { /* leave as-is */ }
}
function genReq() {
    var method = document.getElementById('req-method').value;
    var url = document.getElementById('req-url').value.trim();
    var headers = parseHeaders(document.getElementById('req-headers').value);
    var body = document.getElementById('req-body').value;
    var isJson = document.getElementById('req-json').checked;
    var insecure = document.getElementById('req-insecure').checked;
    var follow = document.getElementById('req-follow').checked;
    if (isJson) {
        doJSONPretty();
        body = document.getElementById('req-body').value;
        if (!headers.some(function (h) { return h.name.toLowerCase() === 'content-type'; })) {
            headers.push({ name: 'Content-Type', value: 'application/json' });
        }
    }

    // ——— curl ———
    var curl = 'curl -X ' + method + " '" + url.replace(/'/g, "'\\''") + "'";
    if (insecure) curl += ' \\\n  -k';
    if (follow) curl += ' \\\n  -L';
    headers.forEach(function (h) {
        curl += ' \\\n  -H ' + "'" + h.name + ': ' + h.value.replace(/'/g, "'\\''") + "'";
    });
    if (body !== '') {
        curl += ' \\\n  --data ' + "'" + body.replace(/'/g, "'\\''") + "'";
    }
    document.getElementById('out-curl').textContent = curl;

    // ——— fetch ———
    var fetchOpt = "{\n";
    var ind = '    ';
    fetchOpt += ind + 'method: ' + JSON.stringify(method) + ',\n';
    if (headers.length) {
        fetchOpt += ind + 'headers: ' + JSON.stringify(headers.reduce(function (o, h) { o[h.name] = h.value; return o; }, {}), null, 4).replace(/^/gm, ind).replace(new RegExp(ind + '$'), '') + ',\n';
    }
    if (body !== '') {
        fetchOpt += ind + 'body: ' + JSON.stringify(body) + ',\n';
    }
    fetchOpt += "}\n";
    var fetchJs = "fetch('" + url.replace(/'/g, "\\'") + "', " + fetchOpt + ")\n  .then(r => r.text())\n  .then(t => console.log(t))\n  .catch(e => console.error(e));";
    document.getElementById('out-fetch').textContent = fetchJs;

    // ——— python ———
    var pyHead = "import requests\n\n";
    if (insecure) pyHead = "import requests\nimport urllib3\nurllib3.disable_warnings()\n\n";
    var pyHeaders = "headers = " + (headers.length
        ? JSON.stringify(headers.reduce(function (o, h) { o[h.name] = h.value; return o; }), null, 2)
        : '{}') + "\n";
    var pyCall = "r = requests.request(\n    " + JSON.stringify(method) + ",\n    " + JSON.stringify(url) + ",\n    headers=headers";
    if (insecure) pyCall += ",\n    verify=False";
    if (body !== '') {
        if (isJson) {
            pyCall += ",\n    json=" + JSON.stringify(JSON.parse(body), null, 2);
        } else {
            pyCall += ",\n    data=" + JSON.stringify(body);
        }
    }
    pyCall += ",\n)\n";
    var pyTail = "print(r.status_code)\nprint(r.text)\n";
    var py = pyHead + pyHeaders + "\n" + pyCall + pyTail;
    document.getElementById('out-python').textContent = py;
}
function copyCurrent() {
    var active = document.querySelector('#req-tabs .nav-link.active');
    var pre = document.querySelector(active.getAttribute('data-bs-target'));
    var range = document.createRange();
    range.selectNodeContents(pre);
    var sel = window.getSelection();
    sel.removeAllRanges(); sel.addRange(range);
    document.execCommand('copy');
    sel.removeAllRanges();
}
function runHere() {
    var method = document.getElementById('req-method').value;
    var url = document.getElementById('req-url').value.trim();
    var headers = parseHeaders(document.getElementById('req-headers').value);
    var body = document.getElementById('req-body').value;
    var isJson = document.getElementById('req-json').checked;
    var init = { method: method, headers: headers.reduce(function (o, h) { o[h.name] = h.value; return o; }, {}) };
    if (['POST','PUT','PATCH'].indexOf(method) >= 0 && body !== '') {
        if (isJson) { init.body = JSON.stringify(JSON.parse(body)); try { init.headers['Content-Type'] = init.headers['Content-Type'] || 'application/json'; } catch (e) {} }
        else { init.body = body; }
    }
    fetch(url, init).then(function (r) {
        return r.text().then(function (t) {
            return 'Status ' + r.status + ' ' + r.statusText + '\n\n' + t.slice(0, 4000);
        });
    }).then(function (txt) {
        alert(txt);
    }).catch(function (e) {
        alert('Request failed (likely CORS is blocking the target): ' + e.message);
    });
}
genReq();
</script>
<?php page_footer(); ?>