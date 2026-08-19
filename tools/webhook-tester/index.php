<?php
require_once __DIR__ . '/../../functions.php';

start_session();
$GLOBALS['_meta'] = [
    'description' => 'Free webhook tester — get a unique URL that captures and inspects incoming HTTP requests in real time. View headers, body, query params and more with auto-refresh.',
    'keywords' => 'webhook tester, HTTP request inspector, API debugger, webhook inspector, request catcher',
];

$action = $_GET['action'] ?? '';
$webhookId = $_GET['id'] ?? '';

if ($action === 'create') {
    $webhookId = bin2hex(random_bytes(16));
    header('Location: ' . url('tools/webhook-tester/index.php?id=' . $webhookId));
    exit;
}

if ($action === 'clear' && $webhookId !== '') {
    csrf_verify_or_fail();
    $file = sys_get_temp_dir() . '/wh_' . md5($webhookId);
    if (file_exists($file)) {
        unlink($file);
    }
    header('Location: ' . url('tools/webhook-tester/index.php?id=' . $webhookId));
    exit;
}

if ($action === 'fetch' && $webhookId !== '') {
    header('Content-Type: application/json');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    $file = sys_get_temp_dir() . '/wh_' . md5($webhookId);
    $requests = [];
    if (file_exists($file)) {
        $raw = file_get_contents($file);
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            $requests = $decoded;
        }
    }
    $requests = array_reverse($requests);
    echo json_encode(['requests' => $requests, 'count' => count($requests)]);
    exit;
}

if ($action === 'export' && $webhookId !== '') {
    $file = sys_get_temp_dir() . '/wh_' . md5($webhookId);
    $requests = [];
    if (file_exists($file)) {
        $raw = file_get_contents($file);
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            $requests = $decoded;
        }
    }
    header('Content-Type: application/json');
    header('Content-Disposition: attachment; filename="webhook-requests-' . e($webhookId) . '.json"');
    echo json_encode($requests, JSON_PRETTY_PRINT);
    exit;
}

page_header('Webhook Tester');

$baseUrl = url('tools/webhook-tester/endpoint.php');
$webhookUrl = $webhookId !== '' ? $baseUrl . '?id=' . $webhookId : '';
?>
<style>
.wh-url-box{background:#1a1a2e;border:1px solid #333;border-radius:8px;padding:1rem;display:flex;align-items:center;gap:.5rem;flex-wrap:wrap}
.wh-url-box input{flex:1;min-width:0;background:#0d0d1a;border:1px solid #444;color:#e0e0e0;padding:.5rem .75rem;border-radius:6px;font-family:monospace;font-size:.85rem}
.wh-url-box .btn{white-space:nowrap}
.method-badge{display:inline-block;padding:2px 8px;border-radius:4px;font-size:.75rem;font-weight:700;text-transform:uppercase;min-width:52px;text-align:center}
.method-GET{background:#2ecc71;color:#000}
.method-POST{background:#3498db;color:#fff}
.method-PUT{background:#f1c40f;color:#000}
.method-DELETE{background:#e74c3c;color:#fff}
.method-PATCH{background:#9b59b6;color:#fff}
.method-OPTIONS{background:#7f8c8d;color:#fff}
.method-HEAD{background:#95a5a6;color:#000}
.wh-card{background:#16162a;border:1px solid #2a2a4a;border-radius:8px;margin-bottom:.5rem;transition:border-color .2s}
.wh-card:hover{border-color:#5865f2}
.wh-card-header{padding:.75rem 1rem;cursor:pointer;display:flex;align-items:center;gap:.75rem;flex-wrap:wrap}
.wh-card-header:hover{background:rgba(88,101,242,.08);border-radius:8px 8px 0 0}
.wh-card-body{display:none;padding:1rem;border-top:1px solid #2a2a4a}
.wh-card-body.open{display:block}
.wh-card-body pre{background:#0d0d1a;border:1px solid #333;border-radius:6px;padding:.75rem;overflow-x:auto;font-size:.8rem;max-height:400px;overflow-y:auto;white-space:pre-wrap;word-break:break-all}
.wh-card-body table{width:100%;font-size:.8rem}
.wh-card-body table th{text-align:left;padding:4px 8px;color:#aaa;border-bottom:1px solid #333;white-space:nowrap}
.wh-card-body table td{padding:4px 8px;border-bottom:1px solid #1a1a2e;word-break:break-all}
.wh-tab{cursor:pointer;padding:4px 12px;border-radius:4px;font-size:.75rem;border:1px solid #444;background:transparent;color:#aaa}
.wh-tab.active{background:#5865f2;color:#fff;border-color:#5865f2}
.wh-stats{display:flex;gap:1.5rem;flex-wrap:wrap}
.wh-stat{background:#16162a;border:1px solid #2a2a4a;border-radius:8px;padding:.75rem 1.25rem;text-align:center}
.wh-stat .num{font-size:1.5rem;font-weight:700;color:#5865f2}
.wh-stat .lbl{font-size:.75rem;color:#aaa;text-transform:uppercase}
</style>

<div class="container" style="max-width:960px;">
    <h1 class="h4 mb-2 reveal in-view">Webhook Tester</h1>
    <p class="text-secondary mb-3 reveal in-view">Get a unique URL that captures and inspects incoming HTTP requests in real time. Send any HTTP method to the endpoint and watch the requests appear instantly.</p>

    <?php if ($webhookId === ''): ?>
        <div class="card mb-3 reveal in-view"><div class="card-body text-center py-5">
            <h2 class="h5 mb-3">Create a Webhook Endpoint</h2>
            <p class="text-secondary mb-3">Generate a unique webhook URL to start capturing requests.</p>
            <a href="<?= e(url('tools/webhook-tester/index.php?action=create')) ?>" class="btn btn-primary btn-lg">Generate Webhook URL</a>
        </div></div>
    <?php else: ?>
        <div class="wh-stats mb-3 reveal in-view">
            <div class="wh-stat"><div class="num" id="requestCount">0</div><div class="lbl">Requests</div></div>
            <div class="wh-stat"><div class="num" id="lastMethod">-</div><div class="lbl">Last Method</div></div>
            <div class="wh-stat"><div class="num" id="lastTime">-</div><div class="lbl">Last Seen</div></div>
        </div>

        <div class="card mb-3 reveal in-view"><div class="card-body">
            <h2 class="h6 mb-3">Your Webhook URL</h2>
            <div class="wh-url-box">
                <input type="text" id="webhookUrlInput" value="<?= e($webhookUrl) ?>" readonly onclick="this.select()">
                <button type="button" class="btn btn-sm btn-outline-light" onclick="copyUrl()">Copy</button>
                <a href="<?= e(url('tools/webhook-tester/index.php?id=' . $webhookId . '&action=create')) ?>" class="btn btn-sm btn-outline-warning" title="New URL">New</a>
            </div>
        </div></div>

        <div class="d-flex justify-content-between align-items-center mb-2">
            <div class="d-flex gap-2 align-items-center">
                <span class="text-secondary small" id="autoRefreshStatus">Auto-refresh: ON</span>
                <button type="button" class="btn btn-sm btn-outline-secondary" id="toggleRefresh" onclick="toggleAutoRefresh()">Pause</button>
            </div>
            <div class="d-flex gap-2">
                <a href="<?= e(url('tools/webhook-tester/index.php?action=export&id=' . $webhookId)) ?>" class="btn btn-sm btn-outline-info">Export JSON</a>
                <form method="post" action="<?= e(url('tools/webhook-tester/index.php?action=clear&id=' . $webhookId)) ?>" style="display:inline" onsubmit="return confirm('Clear all captured requests?');">
                    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                    <button type="submit" class="btn btn-sm btn-outline-danger">Clear All</button>
                </form>
            </div>
        </div>

        <div id="requestsList">
            <div class="text-center py-4 text-secondary">Loading...</div>
        </div>

        <p class="text-secondary small mt-3 mb-4">Requests are stored temporarily in server temp storage and expire after 1 hour. Rate limit: 100 requests per hour per webhook.</p>
    <?php endif; ?>
</div>

<script>
(function(){
    var webhookId = <?= json_encode($webhookId) ?>;
    var baseUrl = <?= json_encode(url('tools/webhook-tester/index.php')) ?>;
    var autoRefresh = true;
    var refreshTimer = null;
    var listEl = document.getElementById('requestsList');

    if (!webhookId || !listEl) return;

    function copyUrl(){
        var inp = document.getElementById('webhookUrlInput');
        if(!inp) return;
        inp.select();
        if(navigator.clipboard && navigator.clipboard.writeText){
            navigator.clipboard.writeText(inp.value).then(function(){
                var btn = inp.parentElement.querySelector('.btn-outline-light');
                if(btn){var old=btn.textContent;btn.textContent='Copied!';setTimeout(function(){btn.textContent=old;},1500);}
            });
        } else {
            document.execCommand('copy');
        }
    }
    window.copyUrl = copyUrl;

    function toggleAutoRefresh(){
        autoRefresh = !autoRefresh;
        var st = document.getElementById('autoRefreshStatus');
        var btn = document.getElementById('toggleRefresh');
        if(autoRefresh){
            if(st) st.textContent='Auto-refresh: ON';
            if(btn) btn.textContent='Pause';
            startPolling();
        } else {
            if(st) st.textContent='Auto-refresh: OFF';
            if(btn) btn.textContent='Resume';
            if(refreshTimer){clearInterval(refreshTimer);refreshTimer=null;}
        }
    }
    window.toggleAutoRefresh = toggleAutoRefresh;

    function formatBody(body, contentType){
        if(!body) return '<em class="text-secondary">(empty)</em>';
        if(contentType && contentType.indexOf('json') !== -1){
            try{
                var obj = JSON.parse(body);
                return escapeHtml(JSON.stringify(obj, null, 2));
            }catch(e){}
        }
        return escapeHtml(body);
    }

    function escapeHtml(s){
        var d=document.createElement('div');d.appendChild(document.createTextNode(s));return d.innerHTML;
    }

    function buildHeadersTable(headers){
        if(!headers || Object.keys(headers).length===0) return '<em class="text-secondary">No headers</em>';
        var html='<table><thead><tr><th>Header</th><th>Value</th></tr></thead><tbody>';
        for(var k in headers){
            html+='<tr><td style="color:#5865f2;white-space:nowrap">'+escapeHtml(k)+'</td><td>'+escapeHtml(headers[k])+'</td></tr>';
        }
        html+='</tbody></table>';
        return html;
    }

    function buildQueryTable(qp){
        if(!qp || Object.keys(qp).length===0) return '<em class="text-secondary">No query parameters</em>';
        var html='<table><thead><tr><th>Key</th><th>Value</th></tr></thead><tbody>';
        for(var k in qp){
            html+='<tr><td style="color:#f1c40f;white-space:nowrap">'+escapeHtml(k)+'</td><td>'+escapeHtml(String(qp[k]))+'</td></tr>';
        }
        html+='</tbody></table>';
        return html;
    }

    function buildCard(r, idx){
        var method = (r.method||'GET').toUpperCase();
        var ts = r.timestamp ? new Date(r.timestamp).toLocaleString() : '-';
        var bodySize = r.body_size || 0;
        var sizeStr = bodySize > 1024 ? (bodySize/1024).toFixed(1)+' KB' : bodySize+' B';
        var cardId = 'wh-card-'+idx;

        var html = '<div class="wh-card" id="'+cardId+'">';
        html += '<div class="wh-card-header" onclick="toggleCard(\''+cardId+'\')">';
        html += '<span class="method-badge method-'+method+'">'+escapeHtml(method)+'</span>';
        html += '<span class="text-secondary small">'+escapeHtml(ts)+'</span>';
        html += '<span class="text-secondary small ms-auto">IP: '+escapeHtml(r.ip||'-')+'</span>';
        html += '<span class="text-secondary small">'+escapeHtml(sizeStr)+'</span>';
        html += '</div>';
        html += '<div class="wh-card-body" id="'+cardId+'-body">';

        html += '<div class="d-flex gap-2 mb-3">';
        html += '<button class="wh-tab active" onclick="showTab(this,\''+cardId+'-headers\')">Headers</button>';
        html += '<button class="wh-tab" onclick="showTab(this,\''+cardId+'-body-tab\')">Body</button>';
        html += '<button class="wh-tab" onclick="showTab(this,\''+cardId+'-query\')">Query Params</button>';
        html += '</div>';

        html += '<div id="'+cardId+'-headers">'+buildHeadersTable(r.headers)+'</div>';
        html += '<div id="'+cardId+'-body-tab" style="display:none"><pre>'+formatBody(r.body, r.content_type)+'</pre></div>';
        html += '<div id="'+cardId+'-query" style="display:none">'+buildQueryTable(r.query_params)+'</div>';

        html += '</div></div>';
        return html;
    }

    function showTab(btn, panelId){
        var card = btn.closest('.wh-card-body');
        if(!card) return;
        card.querySelectorAll('.wh-tab').forEach(function(t){t.classList.remove('active');});
        btn.classList.add('active');
        ['headers','body-tab','query'].forEach(function(s){
            var el = card.querySelector('[id$="-'+s.replace('-tab','')+'"]') || card.querySelector('[id="'+panelId+'"]');
            if(el) el.style.display='none';
        });
        var target = document.getElementById(panelId);
        if(target) target.style.display='';
    }
    window.showTab = showTab;

    function toggleCard(cardId){
        var body = document.getElementById(cardId+'-body');
        if(!body) return;
        body.classList.toggle('open');
    }
    window.toggleCard = toggleCard;

    var lastCount = 0;

    function fetchRequests(){
        var xhr = new XMLHttpRequest();
        xhr.open('GET', baseUrl+'?action=fetch&id='+webhookId+'&_='+Date.now(), true);
        xhr.onreadystatechange = function(){
            if(xhr.readyState !== 4) return;
            if(xhr.status !== 200) return;
            try{
                var data = JSON.parse(xhr.responseText);
                var requests = data.requests || [];
                var count = data.count || 0;

                document.getElementById('requestCount').textContent = count;

                if(requests.length > 0){
                    var last = requests[0];
                    document.getElementById('lastMethod').textContent = last.method || '-';
                    var d = new Date(last.timestamp);
                    document.getElementById('lastTime').textContent = d.toLocaleTimeString();
                } else {
                    document.getElementById('lastMethod').textContent = '-';
                    document.getElementById('lastTime').textContent = '-';
                }

                if(count !== lastCount){
                    lastCount = count;
                    if(requests.length === 0){
                        listEl.innerHTML = '<div class="text-center py-4 text-secondary">No requests yet. Send an HTTP request to your webhook URL.</div>';
                    } else {
                        var html = '';
                        for(var i=0;i<requests.length;i++){
                            html += buildCard(requests[i], i);
                        }
                        listEl.innerHTML = html;
                    }
                }
            }catch(e){}
        };
        xhr.send();
    }

    function startPolling(){
        fetchRequests();
        if(refreshTimer) clearInterval(refreshTimer);
        refreshTimer = setInterval(fetchRequests, 2000);
    }

    startPolling();
})();
</script>
<?php page_footer(); ?>