<?php
require_once __DIR__ . '/../../functions.php';

start_session();
$cfg = $GLOBALS['CFG'];

$mac = trim((string)($_POST['mac'] ?? ''));
$error = null;
$vendor = null;
$oui = null;
$done = false;
$source = null;

// Small embedded OUI → vendor table (common vendors only). Kept local so the
// tool still works when the network lookup API is down, and as a fast path.
$ouiTable = [
    '00:00:0C' => 'Cisco',
    '00:00:1B' => 'Massachusetts Institute of Technology',
    '00:02:B3' => 'Cisco',
    '00:03:93' => 'HP / Hewlett-Packard',
    '00:04:AC' => 'SGI',
    '00:05:5D' => 'Dell',
    '00:0C:29' => 'VMware',
    '00:0D:B9' => 'Intel',
    '00:12:17' => 'Apple',
    '00:14:22' => 'Dell',
    '00:15:E9' => 'Meraki',
    '00:16:3E' => 'Xensource / Xen Virtual Machine',
    '00:1A:2B' => 'TP-LINK Technologies',
    '00:1B:21' => 'Intel',
    '00:1C:0F' => 'Milestone Inc.',
    '00:1E:58' => 'D-Link',
    '00:1E:73' => 'Garmin',
    '00:21:6D' => 'Amazon Technologies',
    '00:21:91' => 'D-Link',
    '00:23:54' => 'Cisco-Linksys',
    '00:25:9C' => 'Netgear',
    '00:26:AB' => 'Dell',
    '00:26:BB' => 'Sony Interactive Entertainment',
    '00:27:0E' => 'Google',
    '00:50:56' => 'VMware',
    '00:50:C2' => 'Microsoft (Xbox)',
    '00:60:2F' => 'D-Link Systems',
    '00:80:77' => 'Synology Incorporated',
    '00:8B:1E' => 'Raspberry Pi Foundation',
    '00:11:22' => 'Dell',
    '00:1F:01' => 'Globalscale Technologies (Raspberry Pi)',
    'B8:27:EB' => 'Raspberry Pi Foundation',
    'DC:A6:32' => 'Raspberry Pi Foundation',
    'E4:5F:01' => 'Raspberry Pi Trading',
    '18:2F:D9' => 'Raspberry Pi Foundation (secondary)',
    '50:F7:22' => 'Raspberry Pi Ltd.',
    '28:CD:4C' => 'Espressif (ESP-01 WiFi module)',
    '24:0A:C4' => 'Espressif Inc.',
    'F8:E7:1E' => 'Espressif Inc.',
    'EC:FA:BC' => 'Espressif Inc.',
    '60:01:94' => 'Espressif Inc.',
    '10:C5:95' => 'Espressif Inc.',
    'CC:50:E3' => 'Espressif Inc.',
    '3C:71:BF' => 'Ubiquiti Networks',
    '44:D9:E7' => 'Ubiquiti Networks',
    'DC:9F:DB' => 'Ubiquiti Networks',
    '04:18:D6' => 'Ubiquiti Networks',
    '68:72:51' => 'Huawei Technologies',
    'F8:C3:9E' => 'Huawei Technologies',
    'AC:84:C6' => 'Xiaomi Communications',
    '64:60:66' => 'Samsung Electronics',
    'D4:6A:6A' => 'Samsung Electronics',
    '9C:4E:36' => 'Samsung Electronics',
    '08:00:46' => 'Sony',
    'F8:C2:88' => 'LG Electronics',
    '04:05:05' => 'LG Electronics',
    'D8:96:95' => 'HTC Corporation',
    'A4:77:33' => 'OnePlus / OPPO',
    'B0:C5:54' => 'OnePlus Technology',
    '0C:84:66' => 'Xiaomi',
    '00:26:7E' => 'Motorola Mobility',
    'E8:69:95' => 'Motorola Mobility',
    'C4:B5:03' => 'MediaTek',
    '70:8C:BA' => 'Unknown / virtual (routinely assigned)',
];

// Normalize a MAC into a canonical 6-hex-octet uppercase form.
function normalize_mac(string $mac): ?string
{
    $hex = str_replace([':', '-', '.', ' '], '', strtoupper($mac));
    if (preg_match('/^[0-9A-F]{12}$/', $hex) !== 1) {
        return null;
    }
    return implode(':', str_split($hex, 2));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $done = true;
    if (!csrf_verify()) {
        $error = 'Invalid CSRF token. Reload the page and try again.';
    } elseif (!rate_limit_check('macfind', 10, (int)$cfg['rate_window_seconds'])) {
        $error = 'Rate limit reached — wait a few minutes between lookups.';
    } else {
        $oui = normalize_mac($mac);
        if ($oui === null) {
            $error = 'That does not look like a valid MAC address. Use 6 octets, e.g. 00:11:22:33:44:55.';
        } else {
            $prefix = substr($oui, 0, 8); // "AA:BB:CC"
            log_activity('tool_macfind', $prefix);
            if (isset($ouiTable[$prefix])) {
                $vendor = $ouiTable[$prefix];
                $source = 'local';
            } else {
                // Try macvendors.com (free, rate-limited per source IP / UA).
                $resp = http_get('https://api.macvendors.com/' . rawurlencode(str_replace(':', '', $prefix)), 6);
                if ($resp !== null && $resp !== '') {
                    $v = trim($resp);
                    if (strpos(strtolower($v), 'errors') === false && strlen($v) < 200) {
                        $vendor = $v;
                        $source = 'api';
                    }
                }
                if ($vendor === null) {
                    // Fallback 2: maclookup.app HTML page (scrape the first match).
                    $html = http_get('https://maclookup.app/search/result?mac=' . rawurlencode($oui), 6);
                    if (is_string($html) && preg_match('/<span[^>]*class="[^"]*vendor[^"]*"[^>]*>(.*?)<\/span>/is', $html, $m)) {
                        $v = trim(strip_tags($m[1]));
                        if ($v !== '' && strlen($v) < 200) {
                            $vendor = html_entity_decode($v, ENT_QUOTES | ENT_HTML5, 'UTF-8');
                            $source = 'api2';
                        }
                    }
                }
            }
        }
    }
}

$markerEmoji = $vendor !== null ? '🖥️' : null;

page_header('MAC Vendor Lookup');
?>
<div class="container" style="max-width: 800px;">
    <h1 class="h4 mb-1 reveal in-view">🗂 MAC Address Lookup</h1>
    <p class="text-secondary mb-3 reveal in-view">Maps a MAC address's first three octets (OUI) to its <strong>vendor / manufacturer</strong>. The first 24 bits of any MAC are assigned to the manufacturer, so the rest is what changes. Useful when you find an unknown device on your network and want to know who made the NIC — perfect for telling your own Raspberry Pi or phone apart from an intruder.</p>

    <div class="card reveal in-view"><div class="card-body">
        <form method="post" action="index.php" class="mb-0">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <div class="input-group">
                <input class="form-control" name="mac" maxlength="32" placeholder="e.g. B8:27:EB:00:00:00 or b8-27-eb-01-02-03" value="<?= $done ? e($mac) : '' ?>" required>
                <button class="btn btn-primary" type="submit">Look up</button>
            </div>
        </form>
    </div></div>

    <?php if ($error !== null): ?>
        <div class="alert alert-danger mt-4 reveal in-view"><?= e($error) ?></div>
    <?php endif; ?>

    <?php if ($done && $error === null): ?>
        <div class="card mt-4 reveal in-view" <?= $vendor === null ? '' : 'style="border-left:6px solid #2ecc71;"' ?>>
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="text-secondary small">Canonical MAC</div>
                        <code class="fs-5"><?= e((string)$oui) ?></code>
                    </div>
                    <?php if ($vendor === null): ?>
                        <span class="badge bg-dark text-secondary">not found</span>
                    <?php else: ?>
                        <span class="badge bg-success"><?= $source === 'local' ? 'embedded table' : ($source === 'api2' ? 'via maclookup.app' : 'via macvendors.com') ?></span>
                    <?php endif; ?>
                </div>
                <hr>
                <div class="text-secondary small">OUI (vendor prefix)</div>
                <div class="d-flex align-items-center gap-3">
                    <code class="fs-5"><?= e(substr((string)$oui, 0, 8)) ?></code>
                    <?php if ($vendor !== null): ?>
                        <span class="fs-5"><?= e($vendor) ?></span>
                    <?php else: ?>
                        <span class="text-secondary">No vendor found in any source.</span>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <?php if ($vendor === null): ?>
            <p class="text-secondary small mt-3 mb-0">The embedded table only covers common consumer/vendor OUIs. Try a raw lookup on <a href="https://macvendors.com/" target="_blank" rel="noopener">macvendors.com</a> or the <a href="https://api.macvendors.com/" target="_blank" rel="noopener">IEEE registry</a>.</p>
        <?php endif; ?>
    <?php endif; ?>