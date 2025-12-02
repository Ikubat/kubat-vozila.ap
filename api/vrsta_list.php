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

kubatapp_require_api('vrsta_list.php');

header('Content-Type: application/json; charset=utf-8');
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
require_once __DIR__ . '/config.php';

$DB_HOST = 'localhost';
$DB_USER = 'root';
$DB_PASS = '';
$DB_NAME = 'kubatapp';

try {
    // Povezivanje na bazu␊
    $db = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
    $db->set_charset('utf8mb4');

    // Jednostavan upit␊
    $sql = "SELECT id, naziv, oznaka FROM vrsta_vozila ORDER BY naziv ASC";
    $rs = $db->query($sql);

    // Polje rezultata␊
    $out = [];
    while ($r = $rs->fetch_assoc()) {
        $out[] = [
            'id'     => (int)$r['id'],
            'naziv'  => $r['naziv'],
            'oznaka' => $r['oznaka']
        ];
    }

    // Ispis u JSON formatu␊
    echo json_encode($out, JSON_UNESCAPED_UNICODE);
} catch (mysqli_sql_exception $e) {
    http_response_code(500);
    echo json_encode([
        'ok'    => false,
        'error' => 'Greška u bazi: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}