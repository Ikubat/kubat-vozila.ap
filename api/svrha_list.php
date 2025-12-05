<?php
require_once __DIR__ . '/../_bootstrap.php';
require_once __DIR__ . '/config.php';

header('Content-Type: application/json; charset=utf-8');
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// Ako nije definisano u config.php → koristi default ime tablice
$T_SVRHA = $T_SVRHA ?? 'svrhe_uplate';

function jdie($m, $c = 400) {
    kubatapp_json_error($m, $c);
    exit;
}
function jout($d) {
    kubatapp_json_response($d);
    exit;
}

try {
    $db = $conn;

    // učitaj kolone
    $cols = [];
    $rs = $db->query("SHOW COLUMNS FROM `$T_SVRHA`");
    while ($c = $rs->fetch_assoc()) {
        $cols[strtolower($c['Field'])] = $c['Field'];
    }
    if (!$cols) jdie("Tablica `$T_SVRHA` ne postoji.", 500);

    // mapiranje kolona
    $colId     = $cols['id']       ?? $cols['id_svrha'] ?? null;
    $colNaziv  = $cols['naziv']    ?? $cols['svrha']    ?? null;
    $colVrPrih = $cols['vrsta_prihoda'] ?? $cols['vrsta_prihoda_sifra'] ?? null;
    $colBudzet = $cols['budzetska'] ?? $cols['budzetska_org_sifra'] ?? null;
    $colPoziv  = $cols['poziv_na_broj'] ?? $cols['opci_poziv'] ?? null;

    if (!$colId || !$colNaziv) {
        jdie("Tablica `$T_SVRHA` mora imati barem ID i naziv.");
    }

    // ulaz
    $q    = trim((string)($_GET['q'] ?? ''));
    $all  = isset($_GET['all']) ? (int)$_GET['all'] : 0;

    $page = max(1, (int)($_GET['page'] ?? 1));
    $pp   = max(1, (int)($_GET['page_size'] ?? 50));
    $off  = ($page - 1) * $pp;

    // WHERE
    $where = "1=1";
    $params = [];
    $types  = "";

    if ($q !== '') {
        $where = "($colNaziv LIKE CONCAT('%',?,'%'))";
        $params[] = $q;
        $types .= 's';
    }

    // total count
    if ($params) {
        $st = $db->prepare("SELECT COUNT(*) c FROM `$T_SVRHA` WHERE $where");
        $st->bind_param($types, ...$params);
        $st->execute();
        $total = (int)$st->get_result()->fetch_assoc()['c'];
    } else {
        $total = (int)$db->query("SELECT COUNT(*) c FROM `$T_SVRHA`")->fetch_assoc()['c'];
    }

    if ($all) {
        // bez paginacije
        $limitSql = "";
    } else {
        $limitSql = " LIMIT $pp OFFSET $off ";
    }

    // SELECT
    $sql =
        "SELECT 
            `$colId`     AS id,
            `$colNaziv`  AS naziv" .
        ($colVrPrih ? ", `$colVrPrih` AS vrsta_prihoda" : "") .
        ($colBudzet ? ", `$colBudzet` AS budzetska" : "") .
        ($colPoziv  ? ", `$colPoziv`  AS poziv_na_broj" : "") .
        " FROM `$T_SVRHA`
          WHERE $where
          ORDER BY `$colNaziv` ASC
          $limitSql";

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
            'id'             => (int)$r['id'],
            'naziv'          => $r['naziv'],
            'vrsta_prihoda'  => $r['vrsta_prihoda'] ?? '',
            'budzetska'      => $r['budzetska'] ?? '',
            'poziv_na_broj'  => $r['poziv_na_broj'] ?? '',
        ];
    }

    jout([
        'ok'        => true,
        'data'      => $rows,
        'total'     => $total,
        'page'      => $page,
        'page_size' => $pp
    ]);

} catch (mysqli_sql_exception $e) {
    jdie("DB greška: " . $e->getMessage(), 500);
}
