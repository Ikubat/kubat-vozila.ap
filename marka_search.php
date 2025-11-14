<?php
// marka_search.php (robustan)
// Lista marki iz tablice marka_vozila + vrsta_vozila, bez pretpostavke da postoji "model" kolona.

header('Content-Type: application/json; charset=utf-8');
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$DB_HOST = 'localhost';
$DB_USER = 'root';
$DB_PASS = '';
$DB_NAME = 'kubatapp';

$T_MARKA = 'marka_vozila';
$T_VRSTA = 'vrsta_vozila';

function jdie($m, $c = 500) {
    http_response_code($c);
    echo json_encode(['ok' => false, 'error' => $m], JSON_UNESCAPED_UNICODE);
    exit;
}
function jout($d) {
    echo json_encode($d, JSON_UNESCAPED_UNICODE);
    exit;
}

// ulaz
$q     = isset($_GET['q']) ? trim((string)$_GET['q']) : '';
$page  = max(1, (int)($_GET['page'] ?? 1));
$pp    = max(1, (int)($_GET['page_size'] ?? 50));
$off   = ($page - 1) * $pp;

try {
    $db = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
    $db->set_charset('utf8mb4');

    // --- detektuj kolone u marka_vozila ---
    $cols = [];
    $rs = $db->query("SHOW COLUMNS FROM `$T_MARKA`");
    while ($c = $rs->fetch_assoc()) {
        $cols[strtolower($c['Field'])] = $c['Field'];
    }

    if (!$cols) {
        jdie("Tablica `$T_MARKA` ne postoji ili nema kolona.");
    }

    // mapiranje: šta god postoji koristimo
    $colId     = $cols['id']          ?? $cols['id_marka']  ?? null;
    $colNaziv  = $cols['naziv']       ?? $cols['marka']     ?? $cols['naziv_marka'] ?? null;
    $colModel  = $cols['model']       ?? $cols['tip']       ?? $cols['naziv_modela'] ?? null; // opcionalno
    $colVrsta  = $cols['vrsta_id']    ?? $cols['id_vrsta']  ?? $cols['vrsta'] ?? null;       // opcionalno

    if (!$colId || !$colNaziv) {
        jdie("Tablica `$T_MARKA` nema očekivane kolone (id, naziv).");
    }

    // bazni SELECT s aliasima koje frontend očekuje
    $sel = [];
    $sel[] = "m.`$colId`    AS id";
    $sel[] = "m.`$colNaziv` AS naziv";
    if ($colModel) {
        $sel[] = "m.`$colModel` AS model";
    } else {
        $sel[] = "'' AS model";
    }

    if ($colVrsta) {
        $sel[] = "m.`$colVrsta` AS vrsta_id";
        $joinVrsta = "LEFT JOIN `$T_VRSTA` v ON v.id = m.`$colVrsta`";
    } else {
        $sel[] = "NULL AS vrsta_id";
        $joinVrsta = "LEFT JOIN `$T_VRSTA` v ON 1=0"; // nema veze, ali struktura ostaje ista
    }

    $sel[] = "v.naziv  AS vrsta_naz";
    $sel[] = "v.oznaka AS vrsta_oznaka";
    $select = implode(",\n       ", $sel);

    // --- WHERE za pretragu ---
    $where = '1=1';
    $params = [];
    $types  = '';

    if ($q !== '') {
        $likeParts = [];

        // tražimo po nazivu marke
        $likeParts[] = "m.`$colNaziv` LIKE CONCAT('%',?,'%')";
        $params[] = $q; $types .= 's';

        // po modelu, ako postoji
        if ($colModel) {
            $likeParts[] = "m.`$colModel` LIKE CONCAT('%',?,'%')";
            $params[] = $q; $types .= 's';
        }

        // po nazivu vrste
        $likeParts[] = "v.naziv LIKE CONCAT('%',?,'%')";
        $params[] = $q; $types .= 's';

        // po oznaci vrste
        $likeParts[] = "IFNULL(v.oznaka,'') LIKE CONCAT('%',?,'%')";
        $params[] = $q; $types .= 's';

        $where = '(' . implode(' OR ', $likeParts) . ')';
    }

    // --- total ---
    if ($params) {
        $sqlCount = "SELECT COUNT(*) c
                     FROM `$T_MARKA` m
                     $joinVrsta
                     WHERE $where";
        $st = $db->prepare($sqlCount);
        $st->bind_param($types, ...$params);
        $st->execute();
        $total = (int)$st->get_result()->fetch_assoc()['c'];
    } else {
        $r = $db->query("SELECT COUNT(*) c FROM `$T_MARKA`");
        $total = (int)$r->fetch_assoc()['c'];
    }

    $pages = max(1, (int)ceil($total / $pp));

    // --- data ---
    $sqlData = "SELECT
       $select
       FROM `$T_MARKA` m
       $joinVrsta
       WHERE $where
       ORDER BY m.`$colNaziv` ASC " . ($colModel ? ", m.`$colModel` ASC " : "") . "
       LIMIT $pp OFFSET $off";

    if ($params) {
        $st = $db->prepare($sqlData);
        $st->bind_param($types, ...$params);
        $st->execute();
        $rs = $st->get_result();
    } else {
        $rs = $db->query($sqlData);
    }

    $rows = [];
    while ($r = $rs->fetch_assoc()) {
        $rows[] = [
            'id'           => (int)$r['id'],
            'naziv'        => $r['naziv'],
            'model'        => $r['model'],              // može biti '' ako kolona ne postoji
            'vrsta_id'     => $r['vrsta_id'] !== null ? (int)$r['vrsta_id'] : null,
            'vrsta_naz'    => $r['vrsta_naz'] ?? '',
            'vrsta_oznaka' => $r['vrsta_oznaka'] ?? ''
        ];
    }

    jout([
        'ok'        => true,
        'data'      => $rows,
        'total'     => $total,
        'pages'     => $pages,
        'page'      => $page,
        'page_size' => $pp
    ]);

} catch (mysqli_sql_exception $e) {
    jdie('DB greška: '.$e->getMessage(), 500);
}
