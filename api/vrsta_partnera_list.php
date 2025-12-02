<?php
$bootstrapPath = dirname(__DIR__) . '/_bootstrap.php';
if (!is_file($bootstrapPath)) {
    $bootstrapPath = __DIR__ . '/_bootstrap.php';
}
if (!is_file($bootstrapPath)) {
    if (!headers_sent()) {
        header('Content-Type: application/json; charset=utf-8');
    }
    http_response_code(500);
    echo json_encode([
        'ok'    => false,
        'error' => 'API bootstrap nije pronađen.',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

require_once $bootstrapPath;

kubatapp_require_api('vrsta_partnera_list.php');

header('Content-Type: application/json; charset=utf-8');
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
require_once __DIR__ . '/config.php';

try {
    $db = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
    $db->set_charset('utf8mb4');

    $db->query("CREATE TABLE IF NOT EXISTS vrsta_partnera (\n        id INT AUTO_INCREMENT PRIMARY KEY,\n        naziv VARCHAR(190) NOT NULL UNIQUE\n    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $res = $db->query("SELECT id, naziv FROM vrsta_partnera ORDER BY naziv ASC");
    $rows = $res->fetch_all(MYSQLI_ASSOC);
    $res->free();

    kubatapp_json_response([
        'ok'   => true,
        'data' => $rows,
    ]);
} catch (Throwable $e) {
    kubatapp_json_response([
        'ok'    => false,
        'error' => 'Greška u dohvaćanju vrsta partnera: ' . $e->getMessage(),
    ], 500);
}