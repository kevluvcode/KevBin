<?php
require_once __DIR__ . '/../../functions.php';

start_session();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');

    if (!csrf_verify()) {
        echo json_encode(['error' => 'Invalid CSRF token.']);
        exit;
    }
    if (!rate_limit_check('wayback', 6, 60)) {
        echo json_encode(['error' => 'Rate limit reached. Wait a moment.']);
        exit;
    }

    $url = trim((string)($_POST['url'] ?? ''));
    if ($url === '') {
        echo json_encode(['error' => 'Enter a URL.']);
        exit;
    }
    $host = parse_url($url, PHP_URL_HOST);
    if (!$host) {
        echo json_encode(['error' => 'Invalid URL.']);
        exit;
    }
    log_activity('tool_wayback', $url);

    // Closest snapshot
    $closest = null;
    $avail = http_get('https://archive.org/wayback/available?url=' . urlencode($url) . '&timestamp=' . date('Ymd'), 12);
    if ($avail !== null) {
        $j = json_decode($avail, true);
        if (isset($j['archived_snapshots']['closest']['url'])) {
            $closest = $j['archived_snapshots']['closest'];
        }
    }

    // History via CDX
    $snaps = [];
    $cdx = http_get(
        'https://web.archive.org/cdx/search/cdx?url=' . urlencode($url)
        . '&output=json&fl=timestamp,statuscode,original,digest&filter=statuscode:200&collapse=digest&limit=40',
        15
    );
    if ($cdx !== null) {
        $j = json_decode($cdx, true);
        if (is_array($j)) {
            foreach (array_slice($j, 1) as $row) {
                if (count($row) >= 2) {
                    $snaps[] = [
                        'timestamp' => $row[0],
                        'status' => (int)$row[1],
                        'original' => isset($row[2]) ? $row[2] : '',
                    ];
                }
            }
        }
    }

    echo json_encode([
        'url' => $url,
        'closest' => $closest,
        'snapshots' => $snaps,
        'count' => count($snaps),
        'live' => isset($snaps[0]['timestamp']) ? $snaps[0]['timestamp'] : null,
    ]);
    exit;
}

page_header('Wayback Machine Checker');
?>
<div class="container" style="max-width: 860px;">
    <h1 class="h4 mb-2 reveal in-view">&#129504; Wayback Machine</h1>
    <p class="text-secondary mb-3 reveal in-view">Find archived snapshots of a page from the Internet Archive — check the latest capture and browse the snapshot history of any URL.</p>

    <div class="card reveal in-view"><div class="card-body">
        <form id="wbForm" class="mb-0">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <div class="row g-2">
                <div class="col-md-8">
                    <input class="form-control" name="url" placeholder="https://example.com/page" required>
                </div>
                <div class="col-md-4">
                    <button class="btn btn-primary w-100" type="submit">Search archive</button>
                </div>
            </div>
        </form>
    </div></div>

    <div id="wbErr" class="alert alert-danger mt-3 d-none"></div>
    <div class="card mt-3 d-none" id="wbCard"><div class="card-body" id="wbBody"></div></div>
</div>
<script>
(function(){
    var form=document.getElementById('wbForm');
    var err=document.getElementById('wbErr');
    var card=document.getElementById('wbCard');
    form.addEventListener('submit',function(e){
        e.preventDefault();
        err.classList.add('d-none');
        card.classList.add('d-none');
        fetch('index.php',{method:'POST',body:new FormData(form)})
            .then(function(r){return r.json();})
            .then(function(d){
                if(d.error){ err.textContent=d.error; err.classList.remove('d-none'); return; }
                var h='<div class="small text-secondary mb-3">Found <strong>'+d.count+'+</strong> unique captures of <code>'+esc(d.url)+'</code>'+(d.live?' · latest with status 200: '+d.live:'')+'</div>';
                if(d.closest){
                    h+='<div class="alert alert-success py-2"><strong>Latest snapshot available:</strong> <a href="'+esc(d.closest.url)+'" target="_blank">'+esc(d.closest.timestamp)+'</a> <span class="badge bg-success">HTTP '+esc(d.closest.status)+'</span></div>';
                }
                h+='<table class="table table-sm tbl mb-0"><thead><tr><th>Date</th><th>Status</th><th>Original URL</th></tr></thead><tbody>';
                d.snapshots.forEach(function(s){
                    var date=s.timestamp.slice(0,4)+'-'+s.timestamp.slice(4,6)+'-'+s.timestamp.slice(6,8)+' '+s.timestamp.slice(8,10)+':'+s.timestamp.slice(10,12);
                    var src='https://web.archive.org/web/'+s.timestamp+'/'+esc(d.url);
                    h+='<tr><td class="text-nowrap"><a href="'+src+'" target="_blank" title="Open snapshot">'+date+'</a></td><td><span class="badge bg-success">'+esc(s.status)+'</span></td><td class="text-break small"><code>'+esc(s.original)+'</code></td></tr>';
                });
                h+='</tbody></table>';
                if(!d.snapshots.length && !d.closest) h+='<div class="text-secondary small">No archived captures found for this URL.</div>';
                document.getElementById('wbBody').innerHTML=h;
                card.classList.remove('d-none');
            })
            .catch(function(){ err.textContent='Network error. Try again.'; err.classList.remove('d-none'); });
    });
    function esc(s){ var e=document.createElement('div'); e.appendChild(document.createTextNode(s==null?'':String(s))); return e.innerHTML; }
})();
</script>
<?php page_footer(); ?>