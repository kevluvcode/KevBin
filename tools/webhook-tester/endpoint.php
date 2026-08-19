<?php
$id = trim((string)($_GET['id'] ?? ''));

if ($id === '' || !preg_match('/^[a-f0-9]{16,64}$/i', $id)) {
    http_response_code(400);
    header('Content-Type: application/json');
    header('Access-Control-Allow-Origin: *');
    echo json_encode(['error' => 'invalid or missing id']);
    exit;
}

$dir = sys_get_temp_dir();
$fileName = $dir . '/wh_' . md5($id);

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$uri = $_SERVER['REQUEST_URI'] ?? '/';
$fullUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http')
    . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . $uri;

$queryParams = $_GET;
unset($queryParams['id']);

$headers = [];
foreach ($_SERVER as $key => $val) {
    if (strpos($key, 'HTTP_') === 0) {
        $name = str_replace('_', '-', strtolower(substr($key, 5)));
        $headers[$name] = $val;
    }
}
if (isset($_SERVER['CONTENT_TYPE'])) {
    $headers['content-type'] = $_SERVER['CONTENT_TYPE'];
}
if (isset($_SERVER['CONTENT_LENGTH'])) {
    $headers['content-length'] = $_SERVER['CONTENT_LENGTH'];
}

$body = file_get_contents('php://input');
$bodySize = strlen($body);

$ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
$timestamp = date('c');

$requests = [];
if (file_exists($fileName)) {
    $raw = file_get_contents($fileName);
    $decoded = json_decode($raw, true);
    if (is_array($decoded)) {
        $requests = $decoded;
    }
}

$rateWindow = 3600;
$now = time();
$requests = array_filter($requests, function ($r) use ($now, $rateWindow) {
    $ts = strtotime($r['timestamp'] ?? '');
    return ($ts !== false && ($now - $ts) < $rateWindow);
});
$requests = array_values($requests);

if (count($requests) >= 100) {
    header('Content-Type: application/json');
    header('Access-Control-Allow-Origin: *');
    http_response_code(429);
    echo json_encode(['error' => 'rate limit exceeded']);
    exit;
}

$requests[] = [
    'id' => count($requests) + 1,
    'method' => $method,
    'url' => $fullUrl,
    'query_params' => $queryParams,
    'headers' => $headers,
    'body' => $body,
    'body_size' => $bodySize,
    'ip' => $ip,
    'timestamp' => $timestamp,
    'content_type' => $headers['content-type'] ?? '',
];

file_put_contents($fileName, json_encode($requests), LOCK_EX);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, PATCH, OPTIONS, HEAD');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

if ($method === 'OPTIONS') {
    http_response_code(204);
    exit;
}

echo json_encode(['status' => 'received']);