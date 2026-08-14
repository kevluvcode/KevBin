<?php
// KevBin API bypass proxy.
// The public API (api.php) sits behind InfinityFree's aes.js anti-bot challenge,
// which blocks plain curl/Python/etc. but not real browsers. This page fetches
// api.php server-side (solving the challenge once, caching the cookie) and hands
// back clean JSON to ANY caller — no cookie, no special User-Agent needed.
//
// Usage (plain GET, works from browsers, curl, JS fetch, Python, ...):
//   https://kevbin.ct.ws/api_proxy.php?action=stats
//   https://kevbin.ct.ws/api_proxy.php?action=pastes&limit=10
//   https://kevbin.ct.ws/api_proxy.php?action=paste&id=abcdef12
//   https://kevbin.ct.ws/api_proxy.php?action=stats&callback=myFunc   (JSONP)
//
// Responses may be up to ~15 seconds stale (cached), so it's friendly to
// auto-refreshers too. Rate limited generously per IP.

require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/api_client.php';

start_session();

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: *');
header('Cache-Control: max-age=15');
header('X-Content-Type-Options: nosniff');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

function proxy_out(array $data, ?string $cb): void
{
    echo e_jsonp($data, $cb);
    exit;
}

function e_jsonp(array $data, ?string $cb): string
{
    $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($cb === null) {
        return $json;
    }
    return $cb . '(' . $json . ');';
}

$callback = null;
if (isset($_GET['callback'])) {
    $cb = (string)$_GET['callback'];
    if (!preg_match('/^[A-Za-z_$][A-Za-z0-9_$.]*$/', $cb)) {
        http_response_code(400);
        echo e_jsonp(['error' => 'Invalid callback name.'], null);
        exit;
    }
    // JSONP must be delivered as JS, not JSON.
    header('Content-Type: application/javascript; charset=utf-8');
    $callback = $cb;
}

$allowed = ['pastes', 'paste', 'stats', 'online', 'tools', 'user'];
$action = (string)($_GET['action'] ?? 'stats');
if (!in_array($action, $allowed, true)) {
    http_response_code(404);
    proxy_out(['error' => 'Unknown action. Allowed: ' . implode(', ', $allowed) . '.', 'hint' => 'Use the /api_proxy.php bypass (no anti-bot cookie needed).'], $callback);
}

if (!rate_limit_check('apiproxy', (int)($GLOBALS['CFG']['api_proxy_rate_limit'] ?? 120), (int)($GLOBALS['CFG']['rate_window_seconds'] ?? 600))) {
    http_response_code(429);
    proxy_out(['error' => 'Rate limit reached for the API proxy. Try again in a bit.'], $callback);
}

// Build the query string to forward using only the allowed parameters.
$query = '';
if ($action === 'pastes' && isset($_GET['limit'])) {
    $query = 'limit=' . max(1, min(100, (int)$_GET['limit']));
} elseif ($action === 'paste' && isset($_GET['id'])) {
    $id = (string)$_GET['id'];
    if (preg_match('/^[A-Za-z0-9]+$/', $id)) {
        $query = 'id=' . rawurlencode($id);
    }
}

log_activity('apiproxy', $action . ' ' . $query . ' from ' . client_ip());

// Fetch api.php directly, server-side: solves the aes.js challenge once and
// caches the __test cookie, so callers never need one. (Never recurse into
// api_proxy.php itself — that used to deadlock the proxy.)
$result = kevbin_direct($action, $query);
if ($result === null) {
    http_response_code(502);
    proxy_out(['error' => 'Upstream API unreachable. Try again in a few seconds.'], $callback);
}
if (isset($result['error'])) {
    http_response_code(404);
}
proxy_out($result, $callback);