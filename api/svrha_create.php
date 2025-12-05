<?php
require_once __DIR__ . '/../_bootstrap.php';
require_once __DIR__ . '/config.php';

header('Content-Type: application/json; charset=utf-8');
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// Ako nije definiran u config.php, koristi default
$T_SVRHA = $T_SVRHA ?? 'svrhe_uplate';

function jdie($m, $c = 400) {
    kubatapp_json_error($m, $c);
}

function jok($x = []) {
    kubatapp_json_response(['ok' => true] + $x);
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$ct     = $_SERVER['CONTENT_TYPE'] ?? '';

$data = [];

// JSON ili POST input
if ($method === 'POST' && stripos($ct, 'application/json') !== false) {
    $raw = file_get_contents('php://input');
    $in  = json_decode($raw, true);
    if (!is_array($in)) jdie('Neispravan JSON body');
    $data = $in;
} elseif ($method === 'POST') {
    $data = $_POST;
} else {
    // GET test
    $data = $_GET;
}

// Polja
$naziv  = trim((string)($data['naziv'] ?? ''));
$vrPrih = trim((string)($data['vrsta_prihoda_sifra'] ?? ''));
$budzet = trim((string)($data['budzetska_org_sifra'] ?? ''));
$poziv  = trim((string)($data['poziv_na_broj_default'] ?? ($data['poziv_na_broj'] ?? '')));

if ($naziv === '') jdie('Naziv je obavezan.');

try {
    // koristimo konekciju iz config.php
    $db = $conn;

    // čitanje kolona
    $cols = [];
    $rs = $db->query("SHOW COLUMNS FROM `$T_SVRHA`");
    while ($c = $rs->fetch_assoc()) {
        $cols[strtolower($c['Field'])] = $c['Field'];
    }

    if (!$cols) jdie("Tablica `$T_SVRHA` ne postoji.", 500);

    $colId     = $cols['id']           ?? $cols['id_svrha'] ?? null;
    $colNaziv  = $cols['naziv']        ?? $cols['svrha']    ?? null;
    $colVrPrih = $cols['vrsta_prihoda'] ?? $cols['vrsta_prihoda_sifra'] ?? null;
    $colBudzet = $cols['budzetska']    ?? $cols['budzetska_org_sifra'] ?? null;
    $colPoziv  = $cols['poziv_na_broj'] ?? $cols['opci_poziv'] ?? null;

    if (!$colNaziv) jdie("Tablica `$T_SVRHA` nema kolonu naziv.", 500);

    // priprema INSERT-a
    $fields = ["`$colNaziv`"];
    $ph     = ['?'];
    $vals   = [$naziv];
    $types  = 's';

    $optional = [
        [$colVrPrih, $vrPrih, 's'],
        [$colBudzet, $budzet, 's'],
        [$colPoziv,  $poziv,  's'],
    ];

    foreach ($optional as [$col, $val, $type]) {
        if ($col && $val !== '') {
            $fields[] = "`$col`";
            $ph[]     = '?';
            $vals[]   = $val;
            $types   .= $type;
        }
    }

    $sql = "INSERT INTO `$T_SVRHA` (" . implode(',', $fields) . ") VALUES (" . implode(',', $ph) . ")";
    $st  = $db->prepare($sql);
    $st->bind_param($types, ...$vals);
    $st->execute();

    $newId = $colId ? (int)$db->insert_id : null;

    jok(['id' => $newId]);

} catch (mysqli_sql_exception $e) {
    jdie('DB greška: ' . $e->getMessage(), 500);
}
