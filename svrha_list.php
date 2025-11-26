<?php
// Wrapper za /api/ putanju – prosljeđuje na glavni svrha_list.php
require_once __DIR__ . '/../svrha_list.php';

// /kubatapp/api/svrha_list.php
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
    $colId     = $cols['id']           ?? $cols['id_svrha']     ?? null;
    $colNaziv  = $cols['naziv']        ?? $cols['svrha']        ?? null;
    $colVrPrih = $cols['vrsta_prihoda']?? $cols['vrsta_prihoda_sifra'] ?? null;
    $colBudzet = $cols['budzetska']    ?? $cols['budzetska_org_sifra'] ?? null;
    $colPoziv  = $cols['poziv_na_broj']?? $cols['opci_poziv']  ?? null;

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
            . ($colVrPrih ? ", `$colVrPrih` AS vrsta_prihoda_sifra" : ", NULL AS vrsta_prihoda_sifra")
            . ($colBudzet ? ", `$colBudzet` AS budzetska_org_sifra" : ", NULL AS budzetska_org_sifra")
            . ($colPoziv ? ", `$colPoziv` AS poziv_na_broj_default" : ", NULL AS poziv_na_broj_default")
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
            'vrsta_prihoda_sifra' => $r['vrsta_prihoda_sifra'],
            'budzetska_org_sifra' => $r['budzetska_org_sifra'],
            'poziv_na_broj_default' => $r['poziv_na_broj_default'],
        ];
    }

    echo json_encode([
        'ok'   => true,
        'data' => $rows
    ], JSON_UNESCAPED_UNICODE);
} catch (mysqli_sql_exception $e) {
    jdie('DB greška: ' . $e->getMessage(), 500);
}
