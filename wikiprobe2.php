<?php
error_reporting(E_ALL);
ini_set('display_errors', '1');
require_once __DIR__ . '/functions.php';
start_session();
$probe_get = isset($argv[1]) && $argv[1] === 'h'
    ? ['scope' => 'community', 'slug' => 'home', 'action' => 'history']
    : ['scope' => 'community', 'slug' => 'home'];
$_GET = $probe_get;
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = '/wiki.php?' . http_build_query($probe_get);
set_exception_handler(function (Throwable $t) {
    header('Content-Type: text/plain');
    echo "EXC: " . $t->getMessage() . " in " . $t->getFile() . ":" . $t->getLine() . "\n";
    echo "TRACE:\n" . $t->getTraceAsString() . "\n";
    exit;
});
include __DIR__ . '/wiki.php';
echo "\n---RENDERED-OK---\n";