<?php

// /kubatapp/api/svrha_list.php
$bootstrapPath = __DIR__ . '/_bootstrap.php';
if (!is_file($bootstrapPath)) {
    $bootstrapPath = dirname(__DIR__) . '/_bootstrap.php';
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

require_once __DIR__ . '/config.php';
kubatapp_require_api('svrha_list.php');

header('Content-Type: application/json; charset=utf-8');
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// AKO si u config.php definirao npr. $T_SVRHA = 'svrhe_uplate', koristi se to,
// inače pada na 'svrhe_uplate' kao default.
$T_SVRHA = $T_SVRHA ?? 'svrhe_uplate';

function jdie($m, $code = 500) {
    http_response_code($code);
    echo json_encode(['ok' => false, 'error' => $m], JSON_UNESCAPED_UNICODE);
    exit;
}

$q   = isset($_GET['q'])   ? trim((string)$_GET['q'])   : '';
$all = isset($_GET['all']) ? (int)$_GET['all']          : 0;

try {
    $db = $conn;

    // Provjeri da tablica postoji
    $cols = [];
    $rs = $db->query("SHOW COLUMNS FROM `$T_SVRHA`");
    while ($c = $rs->fetch_assoc()) {
        $cols[strtolower($c['Field'])] = $c['Field'];
    }
    if (!$cols) {
        jdie("Tablica `$T_SVRHA` ne postoji.");
    }

    // Pokušaj pogoditi nazive kolona
    $colId      = $cols['id']            ?? $cols['id_svrha']     ?? null;
    $colNaziv   = $cols['naziv']         ?? $cols['svrha']        ?? null;
    $colNaziv2  = $cols['naziv2']        ?? $cols['naziv_2']      ?? null;
    $colVrPrih  = $cols['vrsta_prihoda'] ?? $cols['vrsta_prihoda_sifra'] ?? null;
    $colBudzet  = $cols['budzetska']     ?? $cols['budzetska_org_sifra'] ?? null;
    $colPoziv   = $cols['poziv_na_broj'] ?? $cols['opci_poziv']  ?? null;

    if (!$colId || !$colNaziv) {
        jdie("Tablica `$T_SVRHA` nema očekivane kolone (id, naziv).");
    }

    $where = '1=1';
    $params = [];
    $types  = '';

    if ($q !== '') {
        $where .= " AND `$colNaziv` LIKE CONCAT('%',?,'%')";
        $params[] = $q;
        $types   .= 's';
    }

    $sql = "SELECT
                `$colId`    AS id,
                `$colNaziv` AS naziv"
            . ($colNaziv2 ? ", `$colNaziv2` AS naziv2" : ", NULL AS naziv2")
            . ($colVrPrih ? ", `$colVrPrih` AS vrsta_prihoda" : ", NULL AS vrsta_prihoda")
            . ($colBudzet ? ", `$colBudzet` AS budzetska_organizacija" : ", NULL AS budzetska_organizacija")
            . ($colPoziv ? ", `$colPoziv` AS default_poziv_na_broj" : ", NULL AS default_poziv_na_broj")
            . " FROM `$T_SVRHA`
               WHERE $where
               ORDER BY `$colNaziv` ASC";

    if ($params) {
        $st = $db->prepare($sql);
        $st->bind_param($types, ...$params);
        $st->execute();
        $rs = $st->get_result();
    } else {
        $rs = $db->query($sql);
    }

    $rows = [];
    while ($r = $rs->fetch_assoc()) {
        $rows[] = [
            'id'                  => (int)$r['id'],
            'naziv'               => $r['naziv'],
            'naziv2'              => $r['naziv2'],
            'naziv_2'             => $r['naziv2'],
            'vrsta_prihoda'       => $r['vrsta_prihoda'],
            'vrsta_prihoda_sifra' => $r['vrsta_prihoda'],
            'budzetska_organizacija' => $r['budzetska_organizacija'],
            'budzetska_org_sifra' => $r['budzetska_organizacija'],
            'default_poziv_na_broj' => $r['default_poziv_na_broj'],
            'poziv_na_broj_default' => $r['default_poziv_na_broj'],
        ];
    }

    echo json_encode([
        'ok'   => true,
        'data' => $rows
    ], JSON_UNESCAPED_UNICODE);
} catch (mysqli_sql_exception $e) {
    jdie('DB greška: ' . $e->getMessage(), 500);
}
