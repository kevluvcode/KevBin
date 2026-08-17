<?php
require_once __DIR__ . '/../../functions.php';

start_session();
$GLOBALS['_meta'] = [
    'description' => 'Free online IPv4 subnet calculator. Compute network address, broadcast address, usable host range, subnet mask, wildcard and CIDR notation for any IP, instantly, with a clear binary breakdown.',
    'keywords' => 'subnet calculator, ip calculator, cidr calculator, subnet mask, network calculator, vlsm',
];
page_header('IP Subnet Calculator — CIDR, Network & Host Ranges');
?>
<div class="container" style="max-width: 980px;">
    <h1 class="h4 mb-2 reveal in-view">IP Subnet Calculator</h1>
    <p class="text-secondary mb-1 reveal in-view">Enter an IPv4 address and a prefix length (or subnet mask) and get the full picture instantly: network address, broadcast address, first and last usable host, total hosts, wildcard mask and an ASCII binary breakdown. Free to use, no sign-up, nothing leaves your browser.</p>
    <p class="text-secondary mb-4 reveal in-view">Subnetting splits a network into smaller, manageable address ranges — essential for VLANs, cloud VPC design and firewall rules. This calculator works out all the math so you can plan address space without memorizing the tables.</p>

    <div class="card reveal in-view">
        <div class="card-body">
            <div class="row g-3 mb-3">
                <div class="col-md-5">
                    <label class="form-label small text-secondary">IP address</label>
                    <input id="snet-ip" class="form-control" style="font-family:'JetBrains Mono',monospace;font-size:.9rem;" value="192.168.1.0" oninput="calcSubnet()">
                </div>
                <div class="col-md-3">
                    <label class="form-label small text-secondary">Prefix / CIDR</label>
                    <input id="snet-prefix" class="form-control" type="number" min="0" max="32" value="24" oninput="calcSubnet()" style="font-family:'JetBrains Mono',monospace;font-size:.9rem;">
                </div>
                <div class="col-md-4">
                    <label class="form-label small text-secondary">Subnet mask (auto / editable)</label>
                    <input id="snet-mask" class="form-control" style="font-family:'JetBrains Mono',monospace;font-size:.9rem;" oninput="calcFromMask()">
                </div>
            </div>

            <table class="table table-sm align-middle">
                <tbody>
                    <tr><td class="text-secondary">Network address</td><td class="text-end" style="font-family:'JetBrains Mono',monospace;" id="r-net">–</td></tr>
                    <tr><td class="text-secondary">Broadcast address</td><td class="text-end" style="font-family:'JetBrains Mono',monospace;" id="r-bcast">–</td></tr>
                    <tr><td class="text-secondary">First usable host</td><td class="text-end" style="font-family:'JetBrains Mono',monospace;" id="r-first">–</td></tr>
                    <tr><td class="text-secondary">Last usable host</td><td class="text-end" style="font-family:'JetBrains Mono',monospace;" id="r-last">–</td></tr>
                    <tr><td class="text-secondary">Usable hosts</td><td class="text-end" style="font-family:'JetBrains Mono',monospace;" id="r-hosts">–</td></tr>
                    <tr><td class="text-secondary">Wildcard mask</td><td class="text-end" style="font-family:'JetBrains Mono',monospace;" id="r-wild">–</td></tr>
                    <tr><td class="text-secondary">Binary network</td><td class="text-end" style="font-family:'JetBrains Mono',monospace;font-size:.75rem;" id="r-bin">–</td></tr>
                    <tr><td class="text-secondary">IP class (historical /26)</td><td class="text-end" style="font-family:'JetBrains Mono',monospace;" id="r-class">–</td></tr>
                </tbody>
            </table>
        </div>
    </div>

    <h2 class="h6 mt-4 reveal in-view" style="border-bottom:1px solid var(--line);padding-bottom:.5rem;">How subnet masks work</h2>
    <p class="text-secondary small reveal in-view">The prefix length (/24, /28…) is the number of leading <em>1</em> bits in the subnet mask. The mask is ANDed with the IP to get the network address; the broadcast is the network with all host bits set to <em>1</em>. Usable hosts = 2<sup>host-bits</sup> − 2 (the network and broadcast addresses are reserved). For /32 only the single IP is usable; for /31 (point-to-point) both addresses are usable in practice.</p>
</div>

<script>
function $s(id) { return document.getElementById(id); }
function ipToInt(ip) {
    var p = ip.split('.').map(function (n) { return parseInt(n, 10); });
    if (p.length !== 4 || p.some(function (n) { return isNaN(n) || n < 0 || n > 255; })) return null;
    return ((p[0] << 24) | (p[1] << 16) | (p[2] << 8) | p[3]) >>> 0;
}
function intToIp(n) { return [(n >>> 24) & 255, (n >>> 16) & 255, (n >>> 8) & 255, n & 255].join('.'); }
function maskFromPrefix(b) { return b <= 0 ? 0 : (b >= 32 ? 0xffffffff : (0xffffffff << (32 - b)) >>> 0); }
function prefixFromMask(m) { m = m >>> 0; var b = 0; while (m) { b += m & 1; m = Math.floor(m / 2); } return b; }
function render(ip, prefix) {
    if (prefix === null) return;
    var mask = maskFromPrefix(prefix);
    var ipInt = ipToInt(ip);
    $s('snet-ip').value = ip;
    $s('snet-prefix').value = prefix;
    $s('snet-mask').value = intToIp(mask);
    var net = (ipInt & mask) >>> 0;
    var bcast = (ipInt | (~mask >>> 0)) >>> 0;
    var first = (net + 1) >>> 0, last = (bcast - 1) >>> 0;
    var total = (bcast - net + 1) >>> 0;
    var usable = Math.max(0, total - 2);
    $s('r-net').textContent = intToIp(net);
    $s('r-bcast').textContent = intToIp(bcast);
    $s('r-first').textContent = prefix >= 31 ? 'n/a' : intToIp(first);
    $s('r-last').textContent = prefix >= 31 ? 'n/a' : intToIp(last);
    $s('r-hosts').textContent = prefix === 32 ? '1 (single host)' : prefix === 31 ? '2 (point-to-point)' : usable.toLocaleString();
    $s('r-wild').textContent = intToIp(~mask >>> 0);
    $s('r-bin').textContent = [net, mask, bcast].map(function (n) {
        return [24, 16, 8, 0].map(function (s) { return ((n >>> s) & 255).toString(2).padStart(8, '0'); }).join('.');
    }).join('  ');

    var firstOct = (ipInt >>> 24) & 255, cls = '?';
    if (firstOct < 128) cls = 'A /8'; else if (firstOct < 192) cls = 'B /16'; else if (firstOct < 224) cls = 'C /24'; else if (firstOct < 240) cls = 'D (multicast)'; else cls = 'E (reserved)';
    $s('r-class').textContent = cls + ' · current /' + prefix;
}
function calcSubnet() {
    var ip = $s('snet-ip').value.trim();
    var prefix = parseInt($s('snet-prefix').value, 10);
    if (isNaN(prefix)) prefix = 24;
    prefix = Math.max(0, Math.min(32, prefix));
    if (ipToInt(ip) === null) return;
    render(ip, prefix);
}
function calcFromMask() {
    var m = ipToInt($s('snet-mask').value.trim());
    if (m === null) return;
    var prefix = prefixFromMask(m);
    var ip = $s('snet-ip').value.trim();
    if (ipToInt(ip) === null) return;
    render(ip, prefix);
}
calcSubnet();
</script>
<?php page_footer(); ?>