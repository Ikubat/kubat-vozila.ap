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

kubatapp_require_api('partneri_list.php');

header('Content-Type: application/json; charset=utf-8');
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

require_once __DIR__ . '/config.php';

try {
    $sql = "
        SELECT
            p.id,
            p.ime,
            p.prezime,
            p.kontakt,
            p.email,
            p.adresa,
            p.mjesto_id,
            COALESCE(p.vrsta_partnera, p.vrsta) AS vrsta_partnera,
            COALESCE(p.id_broj, p.idbroj) AS id_broj,
            m.naziv_mjesta AS mjesto
        FROM partneri p
        LEFT JOIN mjesta m ON m.id = p.mjesto_id
        ORDER BY p.prezime ASC, p.ime ASC, p.id ASC
    ";

    $res = $conn->query($sql);
    $rows = $res->fetch_all(MYSQLI_ASSOC);
    $res->free();

    kubatapp_json_response([
        'ok'   => true,
        'data' => $rows,
    ]);
} catch (Throwable $e) {
    kubatapp_json_response([
        'ok'    => false,
        'error' => 'Greška u dohvaćanju partnera: ' . $e->getMessage(),
    ], 500);
}