<?php
// Server-side supplemental hash endpoint for the Hash Generator tool.
// Used ONLY for algorithms WebCrypto can't do in-browser (SHA-3 family,
// RIPEMD, Whirlpool, CRC32, GOST). Whitelist + input cap only. No auth needed.

require_once __DIR__ . '/../../functions.php';
start_session();

header('Content-Type: application/json; charset=utf-8');
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'POST only']);
    exit;
}

// Minimal anti-abuse: soft IP throttle.
$minGap = 0.4;
$key = 'hash_api_' . ($_SERVER['REMOTE_ADDR'] ?? '0');
if (function_exists('apcu_fetch')) {
    $last = apcu_fetch($key);
    if ($last !== false && (microtime(true) - $last) < $minGap) {
        http_response_code(429);
        echo json_encode(['error' => 'Too fast — wait a moment.']);
        exit;
    }
    apcu_store($key, microtime(true), 5);
}

$in = json_decode(file_get_contents('php://input'), true);
if (!is_array($in)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON']);
    exit;
}

$data = $in['data'] ?? '';
$algo = (string)($in['algo'] ?? '');
if (strlen($data) > 262144) {  // 256 KB cap
    http_response_code(413);
    echo json_encode(['error' => 'Input too large (max 256 KB).']);
    exit;
}

$allowed = [
    'sha3-224', 'sha3-256', 'sha3-384', 'sha3-512',
    'ripemd128', 'ripemd160', 'ripemd256', 'ripemd320',
    'whirlpool', 'crc32', 'crc32b', 'fnv132', 'fnv1a32',
    'fnv164', 'fnv1a64', 'gost', 'gost-crypto', 'adler32', 'tiger128,3',
];
if (!in_array($algo, $allowed, true) || !in_array($algo, hash_algos(), true)) {
    http_response_code(400);
    echo json_encode(['error' => 'Algorithm not allowed']);
    exit;
}

echo json_encode(['algo' => $algo, 'hash' => hash($algo, $data)]);