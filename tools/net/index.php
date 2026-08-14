<?php
require_once __DIR__ . '/../../functions.php';

start_session();
page_header('IP Subnet Calculator');
?>
<div class="container" style="max-width: 900px;">
    <h1 class="h4 mb-1 reveal in-view">IP & Subnet Calculator</h1>
    <p class="text-secondary mb-4 reveal in-view">Calculate network, broadcast, range, CIDR and binary breakdown for any IPv4 address — with an explanation of each field.</p>

    <div class="card reveal in-view">
        <div class="card-body">
            <div class="row g-2 mb-3">
                <div class="col-md-5">
                    <label class="form-label small text-secondary">IP address</label>
                    <input id="ip-in" class="form-control" style="font-family:'JetBrains Mono',monospace;font-size:.9rem;" value="192.168.1.42">
                </div>
                <div class="col-md-4">
                    <label class="form-label small text-secondary">Prefix / CIDR</label>
                    <div class="input-group">
                        <input id="pfx-in" type="number" class="form-control" style="font-family:'JetBrains Mono',monospace;font-size:.9rem;" value="24" min="0" max="32">
                        <button class="btn btn-primary" onclick="calc()">Calculate</button>
                    </div>
                </div>
            </div>

            <div id="ip-out" class="row g-2"></div>

            <div class="alert alert-secondary small mt-3 mb-0">
                <strong>What does each field mean?</strong><br>
                • <strong>Address</strong> — the IP you entered.<br>
                • <strong>Network</strong> — the first address of the subnet; used as the subnet identifier.<br>
                • <strong>Broadcast</strong> — the last address; sends to every host on the subnet.<br>
                • <strong>Usable hosts</strong> — addresses you can assign to devices (network & broadcast excluded).<br>
                • <strong>Subnet mask</strong> — defines which part of the address is the network ("prefix") vs. the host.<br>
                • <strong>CIDR</strong> — shorthand: IP + "/prefix". The prefix is the count of 1-bits in the mask.
            </div>
        </div>
    </div>
</div>

<script>
function $(id) { return document.getElementById(id); }
function ipToInt(ip) {
    var p = ip.split('.').map(Number);
    if (p.length !== 4 || p.some(function (n) { return n < 0 || n > 255 || isNaN(n); })) return null;
    return ((p[0] << 24) | (p[1] << 16) | (p[2] << 8) | p[3]) >>> 0;
}
function intToIp(v) {
    return [(v >>> 24) & 255, (v >>> 16) & 255, (v >>> 8) & 255, v & 255].join('.');
}
function bin(n) {
    return ('0'.repeat(32) + n.toString(2)).slice(-32).replace(/(.{8})/g, '$1.').slice(0, -1);
}

function calc() {
    var ipVal = $('ip-in').value.trim();
    var pfx = parseInt($('pfx-in').value, 10);
    if (isNaN(pfx) || pfx < 0 || pfx > 32) { pfx = 24; }
    var ip = ipToInt(ipVal);
    var out = $('ip-out');
    if (ip === null) { out.innerHTML = '<div class="col-12 text-danger small">Invalid IPv4 address.</div>'; return; }

    var mask = pfx === 0 ? 0 : (0xFFFFFFFF << (32 - pfx)) >>> 0;
    var network = (ip & mask) >>> 0;
    var broadcast = (network | (~mask >>> 0)) >>> 0;
    var wildcard = (~mask >>> 0) >>> 0;
    var hosts = 1 << (32 - pfx);
    var usable = hosts > 2 ? hosts - 2 : 0;

    var rows = [
        ['Address', ipVal, 'The entered IP as a 32-bit value.'],
        ['Subnet mask', intToIp(mask) + ' / ' + pfx, 'Bits set to 1 = network, 0 = host.'],
        ['Wildcard', intToIp(wildcard), 'Inverse mask — used in access control lists.'],
        ['Network', intToIp(network), 'First address of the subnet; also the route address.'],
        ['Broadcast', intToIp(broadcast), 'Last address; targets all hosts on the subnet.'],
        ['First usable', usable ? intToIp((network + 1) >>> 0) : '—', 'First assignable host address.'],
        ['Last usable', usable ? intToIp((broadcast - 1) >>> 0) : '—', 'Last assignable host address.'],
        ['Usable hosts', String(usable), 'Assignable addresses (hosts less 2 for network/broadcast).'],
        ['Total addresses', String(hosts), '2^(32-prefix): whole subnet size including network/broadcast.']
    ];
    var html = '';
    rows.forEach(function (r) {
        html += '<div class="col-12 col-md-6"><div class="d-flex justify-content-between align-items-center" style="border:1px solid var(--line);border-radius:10px;padding:.55rem .8rem;margin-bottom:.4rem;">' +
            '<div><div class="text-secondary small">' + r[0] + '</div>' +
            '<div class="text-secondary small" style="font-size:.7rem;">' + r[2] + '</div></div>' +
            '<code class="ms-2" style="font-family:JetBrains Mono,monospace;font-size:.85rem;">' + r[1] + '</code></div></div>';
    });
    html += '<div class="col-12 mt-1"><div class="text-secondary small mb-1">Binary representation</div>' +
        '<div style="font-family:JetBrains Mono,monospace;font-size:.75rem;word-break:break-all;" class="p-2" style="background:#0b0b0b;border:1px solid var(--line);border-radius:10px;">' +
        '<span class="text-secondary">IP </span>' + bin(ip) + '<br>' +
        '<span class="text-secondary">NET</span>' + bin(network) + '<br>' +
        '<span class="text-secondary">BC </span>' + bin(broadcast) + '</div></div>';
    out.innerHTML = html;
}

calc();
</script>
<?php page_footer(); ?>