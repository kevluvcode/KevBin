<?php
// KevBin public API client — handles InfinityFree's aes.js anti-bot challenge
// automatically so scripted clients get real JSON instead of the challenge page.
//
// Usage:
//   require 'api_client.php';
//   $stats  = kevbin_api('stats');                      // site counters
//   $online = kevbin_api('online');                     // live online count
//   $list   = kevbin_api('pastes', 'limit=10');         // latest pastes
//   $one    = kevbin_api('paste', 'id=abcdef12');       // one public paste
//
// kevbin_api() prefers the challenge-free bypass proxy (api_proxy.php) and only
// falls back to solving the aes.js challenge when the proxy is unreachable.
// Returns an assoc array (JSON decoded) or null if the API is unreachable.

const KEVBIN_API_BASE = 'https://kevbin.ct.ws/api.php';
const KEVBIN_API_PROXY = 'https://kevbin.ct.ws/api_proxy.php';
const KEVBIN_UA = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Safari/537.36';

// Raw GET with optional cookie. Returns body or null.
function kevbin_http_get(string $url, string $cookie = ''): ?string
{
    $header = "User-Agent: " . KEVBIN_UA . "\r\nAccept: application/json, text/plain\r\n";
    if ($cookie !== '') {
        $header .= "Cookie: __test=" . $cookie . "\r\n";
    }
    $ctx = stream_context_create([
        'http' => [
            'method' => 'GET',
            'timeout' => 20,
            'header' => $header,
            'ignore_errors' => true,
        ],
        'ssl' => ['verify_peer' => false, 'verify_peer_name' => false],
    ]);
    $body = @file_get_contents($url, false, $ctx);
    return is_string($body) ? $body : null;
}

// Solves the InfinityFree JS challenge: fetch it, extract the AES params and
// compute the __test cookie (AES-128-CBC, zero padding — matches slowAES).
function kevbin_solve_cookie(): ?string
{
    $page = null;
    for ($i = 0; $i < 3; $i++) {
        $page = kevbin_http_get(KEVBIN_API_BASE . '?action=stats');
        if ($page !== null && strpos($page, 'toNumbers') !== false) {
            break;
        }
        usleep(300000);
    }
    if ($page === null || preg_match_all('/toNumbers\("([0-9a-f]{32})"\)/', $page, $m) !== 3) {
        return null;
    }
    if (!function_exists('openssl_decrypt')) {
        return null; // PHP built without OpenSSL — cannot solve the challenge.
    }
    $a = hex2bin($m[1][0]);
    $b = hex2bin($m[1][1]);
    $c = hex2bin($m[1][2]);
    $plain = openssl_decrypt($c, 'aes-128-cbc', $a, OPENSSL_RAW_DATA | OPENSSL_ZERO_PADDING, $b);
    return is_string($plain) && $plain !== '' ? bin2hex($plain) : null;
}

// Fetches one API action, preferring the no-challenge proxy and falling back to
// solving the aes.js challenge against api.php directly.
function kevbin_api(string $action, string $query = ''): ?array
{
    $url = KEVBIN_API_PROXY . '?action=' . rawurlencode($action);
    if ($query !== '') {
        $url .= '&' . ltrim($query, '&');
    }
    for ($i = 0; $i < 3; $i++) {
        $body = kevbin_http_get($url);
        if ($body !== null) {
            $json = json_decode($body, true);
            if (is_array($json)) {
                return $json;
            }
        }
        // Proxy hiccup — brief pause, then retry.
        usleep(250000);
    }

    return kevbin_direct($action, $query);
}

// Fetches api.php directly, solving InfinityFree's aes.js challenge server-side
// and caching the __test cookie so only the first call pays the cost. Returns
// the decoded JSON or null if the API is unreachable.
function kevbin_direct(string $action, string $query = ''): ?array
{
    $cookieFile = sys_get_temp_dir() . '/kevbin_test_cookie_' . md5(__FILE__);
    $cookie = is_file($cookieFile) ? (string)@file_get_contents($cookieFile) : '';
    if ($cookie === '' || (int)@filemtime($cookieFile) < time() - 6 * 3600) {
        $cookie = kevbin_solve_cookie() ?? '';
        if ($cookie !== '') {
            @file_put_contents($cookieFile, $cookie);
        }
    }

    $url = KEVBIN_API_BASE . '?action=' . rawurlencode($action);
    if ($query !== '') {
        $url .= '&' . ltrim($query, '&');
    }

    for ($i = 0; $i < 4; $i++) {
        $body = kevbin_http_get($url, $cookie);
        if ($body !== null && strpos($body, 'aes.js') === false) {
            $json = json_decode($body, true);
            if (is_array($json)) {
                return $json;
            }
        }
        // Challenged, dropped connection or non-JSON -> re-solve and retry.
        $cookie = kevbin_solve_cookie() ?? '';
        if ($cookie !== '') {
            @file_put_contents($cookieFile, $cookie);
        }
        usleep(300000);
    }
    return null;
}

// Convenience: direct call through the no-challenge proxy, JSON decoded.
// Works from any PHP script, no OpenSSL required, no cookies.
function kevbin_proxy(string $action, string $query = ''): ?array
{
    $url = KEVBIN_API_PROXY . '?action=' . rawurlencode($action);
    if ($query !== '') {
        $url .= '&' . ltrim($query, '&');
    }
    $body = kevbin_http_get($url);
    if ($body === null) {
        return null;
    }
    $json = json_decode($body, true);
    return is_array($json) ? $json : null;
}
