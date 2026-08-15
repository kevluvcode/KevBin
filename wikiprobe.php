<?php
error_reporting(E_ALL);
ini_set('display_errors', '1');
require_once __DIR__ . '/functions.php';
start_session();
header('Content-Type: text/plain');
$out = [];
try {
    $pdo = db();
    $st = $pdo->query("SHOW TABLES LIKE 'wiki%'");
    $out['wiki_tables'] = $st->fetchAll(PDO::FETCH_COLUMN);
    $st3 = $pdo->prepare("SELECT column_name FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'wiki_pages' ORDER BY ordinal_position");
    $st3->execute();
    $out['wiki_columns'] = $st3->fetchAll(PDO::FETCH_COLUMN);
    try {
        $stmt = $pdo->prepare('SELECT * FROM wiki_pages WHERE scope = ? AND owner_id IS NULL AND slug = ?');
        $stmt->execute(['community', 'home']);
        $r = $stmt->fetch();
        $out['query_community_home'] = $r !== false ? 'FOUND' : 'none';
    } catch (Throwable $t) {
        $out['query_error'] = $t->getMessage();
    }
    $st4 = $pdo->prepare("SELECT column_name FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'online' ORDER BY ordinal_position");
    $st4->execute();
    $out['online_columns'] = $st4->fetchAll(PDO::FETCH_COLUMN);
    $out['auto_migrate'] = !empty($GLOBALS['CFG']['auto_migrate']);
    $out['php'] = PHP_VERSION;
} catch (Throwable $t) {
    $out['fatal_probe'] = $t->getMessage();
}
echo json_encode($out, JSON_PRETTY_PRINT);