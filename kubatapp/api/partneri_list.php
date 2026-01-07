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
    $cols = [];
    $rs = $conn->query('SHOW COLUMNS FROM partneri');
    while ($c = $rs->fetch_assoc()) {
        $cols[strtolower($c['Field'])] = $c['Field'];
    }

    $fKontakt = $cols['kontakt'] ?? $cols['telefon'] ?? $cols['tel'] ?? null;
    $fAdresa  = $cols['adresa'] ?? null;
    $fVrsta   = $cols['vrsta'] ?? $cols['vrsta_partnera'] ?? null;
    $fIdBroj  = $cols['idbroj'] ?? $cols['id_broj'] ?? $cols['id_broj_partnera'] ?? null;

    $select = [
        'p.id',
        'p.ime',
        'p.prezime',
        $fKontakt ? "p.`$fKontakt` AS kontakt" : "'' AS kontakt",
        'p.email',
        $fAdresa ? "p.`$fAdresa` AS adresa" : "'' AS adresa",
        'p.mjesto_id',
        $fVrsta ? "p.`$fVrsta` AS vrsta" : "'' AS vrsta",
        $fIdBroj ? "p.`$fIdBroj` AS idbroj" : "'' AS idbroj",
        'm.naziv_mjesta AS mjesto'
    ];

    $sql = "
        SELECT " . implode(",\n            ", $select) . "
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
