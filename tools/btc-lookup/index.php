<?php
require_once __DIR__ . '/../../functions.php';

start_session();

function btc_sat($n)
{
    return number_format($n / 1e8, 8) . ' BTC';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');

    if (!csrf_verify()) {
        echo json_encode(['error' => 'Invalid CSRF token.']);
        exit;
    }
    if (!rate_limit_check('btclock', 6, 60)) {
        echo json_encode(['error' => 'Rate limit reached. Wait a moment.']);
        exit;
    }

    $addr = trim((string)($_POST['address'] ?? ''));
    if ($addr === '') {
        echo json_encode(['error' => 'Enter a Bitcoin address.']);
        exit;
    }
    if (!preg_match('/^[13][a-km-zA-HJ-NP-Z1-9]{25,34}$/', $addr)) {
        echo json_encode(['error' => 'That does not look like a valid legacy/p2sh Bitcoin address.']);
        exit;
    }
    log_activity('tool_btc_lookup', substr($addr, 0, 12) . '…');

    $data = http_get('https://blockchain.info/rawaddr/' . rawurlencode($addr) . '?limit=0&offset=0', 12);
    if ($data === null) {
        echo json_encode(['error' => 'blockchain.info did not respond. Try again shortly.']);
        exit;
    }
    $j = json_decode($data, true);
    if (!$j || isset($j['error'])) {
        echo json_encode(['error' => 'Lookup failed' . (isset($j['error']) ? ': ' . $j['error'] : ' — address may be invalid or the API refused the request.')]);
        exit;
    }

    echo json_encode([
        'address' => $j['address'],
        'balance_btc' => btc_sat((int)$j['final_balance']),
        'received_btc' => btc_sat((int)$j['total_received']),
        'sent_btc' => btc_sat((int)$j['total_sent']),
        'balance_sat' => $j['final_balance'],
        'received_sat' => $j['total_received'],
        'sent_sat' => $j['total_sent'],
        'transactions' => $j['n_tx'],
    ]);
    exit;
}

page_header('Bitcoin Address Lookup');
?>
<div class="container" style="max-width: 760px;">
    <h1 class="h4 mb-2 reveal in-view">&#11088; Bitcoin Address Lookup</h1>
    <p class="text-secondary mb-3 reveal in-view">Paste a Bitcoin address (legacy or P2SH) to see its current balance, total received and sent, and transaction count via the public blockchain.info API.</p>

    <div class="card reveal in-view"><div class="card-body">
        <form id="btForm" class="mb-0">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <div class="row g-2">
                <div class="col-md-8">
                    <input class="form-control" name="address" placeholder="1BvBMSEYstWetqTFn5Au4m4GFg7xJaNVN2" required>
                </div>
                <div class="col-md-4">
                    <button class="btn btn-primary w-100" type="submit">Look up</button>
                </div>
            </div>
        </form>
    </div></div>

    <div id="btErr" class="alert alert-danger mt-3 d-none"></div>
    <div class="card mt-3 d-none" id="btCard"><div class="card-body" id="btBody"></div></div>
</div>
<script>
(function(){
    var form=document.getElementById('btForm');
    var err=document.getElementById('btErr');
    var card=document.getElementById('btCard');
    form.addEventListener('submit',function(e){
        e.preventDefault();
        err.classList.add('d-none');
        card.classList.add('d-none');
        var btn=form.querySelector('button'); btn.disabled=true;
        fetch('index.php',{method:'POST',body:new FormData(form)})
            .then(function(r){return r.json();})
            .then(function(d){
                btn.disabled=false;
                if(d.error){ err.textContent=d.error; err.classList.remove('d-none'); return; }
                var h='<div class="mb-3"><code>'+esc(d.address)+'</code></div>';
                h+='<table class="table table-sm tbl mb-0"><tbody>';
                h+='<tr><td class="text-secondary" style="width:150px">Balance</td><td><strong class="text-success">'+esc(d.balance_btc)+'</strong> <span class="small text-secondary">('+fmt(d.balance_sat)+' sat)</span></td></tr>';
                h+='<tr><td class="text-secondary">Total received</td><td>'+esc(d.received_btc)+' <span class="small text-secondary">('+fmt(d.received_sat)+' sat)</span></td></tr>';
                h+='<tr><td class="text-secondary">Total sent</td><td>'+esc(d.sent_btc)+' <span class="small text-secondary">('+fmt(d.sent_sat)+' sat)</span></td></tr>';
                h+='<tr><td class="text-secondary">Transactions</td><td>'+esc(d.transactions)+'</td></tr>';
                h+='</tbody></table>';
                h+='<div class="mt-3 small text-secondary"><a href="https://blockchain.info/address/'+esc(d.address)+'" target="_blank">Open full history on blockchain.info</a></div>';
                document.getElementById('btBody').innerHTML=h;
                card.classList.remove('d-none');
            })
            .catch(function(){ btn.disabled=false; err.textContent='Network error. Try again.'; err.classList.remove('d-none'); });
    });
    function row(k,v){ return '<tr><td class="text-secondary" style="width:150px">'+k+'</td><td>'+v+'</td></tr>'; }
    function fmt(n){ return Number(n).toLocaleString(); }
    function esc(s){ var e=document.createElement('div'); e.appendChild(document.createTextNode(s==null?'':String(s))); return e.innerHTML; }
})();
</script>
<?php page_footer(); ?>