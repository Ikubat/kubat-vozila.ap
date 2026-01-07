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
    // --- pročitaj strukturu tablice partneri ---
    $cols = [];
    $rs = $conn->query('SHOW COLUMNS FROM partneri');
    while ($c = $rs->fetch_assoc()) {
        $cols[strtolower($c['Field'])] = $c['Field'];
    }

    // fleksibilne mape kolona
    $fVrsta   = $cols['vrsta_partnera'] ?? $cols['vrsta'] ?? null;
    $fIdBroj  = $cols['id_broj'] ?? $cols['idbroj'] ?? $cols['id_broj_partnera'] ?? null;
    $fBrojR   = $cols['broj_racuna'] ?? $cols['brojracuna'] ?? null;
    $fPorezni = $cols['porezni_broj'] ?? $cols['porezni'] ?? null;
    $fOpcina  = $cols['opcina_sifra'] ?? $cols['opcina'] ?? null;
    $fKontakt = $cols['kontakt'] ?? $cols['telefon'] ?? $cols['tel'] ?? null;
    $fAdresa  = $cols['adresa'] ?? null;

    // ako postoji kolona za općinu u partnerima -> koristi nju
    // ako ne postoji -> koristi poreznu šifru iz tablice mjesta
    $colOpcinaAlias = $fOpcina
        ? "p.`$fOpcina` AS opcina_sifra"
        : "m.porezna_sifra AS opcina_sifra";

    $select = [
        'p.id',
        'p.ime',
        'p.prezime',
        $fKontakt ? "p.`$fKontakt` AS kontakt" : "'' AS kontakt",
        'p.email',
        $fAdresa ? "p.`$fAdresa` AS adresa" : "'' AS adresa",
        'p.mjesto_id',

        $fVrsta   ? "p.`$fVrsta` AS vrsta_partnera" : "'' AS vrsta_partnera",
        $fIdBroj  ? "p.`$fIdBroj` AS id_broj"       : "'' AS id_broj",
        $fBrojR   ? "p.`$fBrojR` AS broj_racuna"    : "'' AS broj_racuna",
        $fPorezni ? "p.`$fPorezni` AS porezni_broj" : "'' AS porezni_broj",

        // ovdje je sad uvijek opcina_sifra (ili iz partnera ili iz mjesta)
        $colOpcinaAlias,

        // dodatno, možeš i dalje imati raw poreznu iz mjesta ako ti treba negdje
        'm.naziv_mjesta AS mjesto',
        'm.porezna_sifra AS mjesto_porezna_sifra'
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
