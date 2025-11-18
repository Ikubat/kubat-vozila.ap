<?php
// Dodavanje nove marke u tablicu marka_vozila.
// Očekuje (idealno JSON POST):
// { "naziv": "...", "model": "...", "vrsta_id": 2 }
//
// Radi i ako tvoja tablica NEMA kolonu "model" ili "vrsta_id" - automatski se prilagodi.

header('Content-Type: application/json; charset=utf-8');
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$DB_HOST = 'localhost';
$DB_USER = 'root';
$DB_PASS = '';
$DB_NAME = 'kubatapp';

$T_MARKA = 'marka_vozila';

function jdie($m, $c = 400) {
    http_response_code($c);
    echo json_encode(['ok' => false, 'error' => $m], JSON_UNESCAPED_UNICODE);
    exit;
}
function jok($x = []) {
    echo json_encode(['ok' => true] + $x, JSON_UNESCAPED_UNICODE);
    exit;
}

// ---- UČITAVANJE PODATAKA ----
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$ct = $_SERVER['CONTENT_TYPE'] ?? '';

$naziv = '';
$model = '';
$vrstaId = null;

if ($method === 'POST' && stripos($ct, 'application/json') !== false) {
    $raw = file_get_contents('php://input');
    $in = json_decode($raw, true);
    if (!is_array($in)) jdie('Neispravan JSON body');
    $naziv  = trim((string)($in['naziv'] ?? ''));
    $model  = trim((string)($in['model'] ?? ''));
    if (array_key_exists('vrsta_id', $in) && $in['vrsta_id'] !== '' && $in['vrsta_id'] !== null) {
        $vrstaId = (int)$in['vrsta_id'];
    }
} elseif ($method === 'POST') {
    $naziv  = trim((string)($_POST['naziv'] ?? ''));
    $model  = trim((string)($_POST['model'] ?? ''));
    if (isset($_POST['vrsta_id']) && $_POST['vrsta_id'] !== '') {
        $vrstaId = (int)$_POST['vrsta_id'];
    }
} else {
    // GET test: ?naziv=VW&model=Golf&vrsta_id=2
    $naziv  = trim((string)($_GET['naziv'] ?? ''));
    $model  = trim((string)($_GET['model'] ?? ''));
    if (isset($_GET['vrsta_id']) && $_GET['vrsta_id'] !== '') {
        $vrstaId = (int)$_GET['vrsta_id'];
    }
}

if ($naziv === '') jdie('Naziv je obavezan.');

// ---- DB & STRUKTURA ----
try {
    $db = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
    $db->set_charset('utf8mb4');

    $cols = [];
    $rs = $db->query("SHOW COLUMNS FROM `$T_MARKA`");
    while ($c = $rs->fetch_assoc()) {
        $cols[strtolower($c['Field'])] = $c['Field'];
    }
    if (!$cols) jdie("Tablica `$T_MARKA` ne postoji.");

    $colId    = $cols['id']         ?? $cols['id_marka']  ?? null;
    $colNaziv = $cols['naziv']      ?? $cols['marka']     ?? $cols['naziv_marka'] ?? null;
    $colModel = $cols['model']      ?? $cols['tip']       ?? null;
    $colVrsta = $cols['vrsta_id']   ?? $cols['id_vrsta']  ?? $cols['vrsta'] ?? null;

    if (!$colNaziv) jdie("Tablica `$T_MARKA` nema kolonu za naziv.");

    // ---- PRIPREMA INSERTA ----
    $fields = [];
    $ph     = [];
    $vals   = [];
    $types  = '';

    // naziv je uvijek tu
    $fields[] = "`$colNaziv`";
    $ph[]     = '?';
    $vals[]   = $naziv;
    $types   .= 's';

    // model ako postoji kolona
    if ($colModel && $model !== '') {
        $fields[] = "`$colModel`";
        $ph[]     = '?';
        $vals[]   = $model;
        $types   .= 's';
    }

    // vrsta_id ako postoji kolona i vrijednost
    if ($colVrsta && $vrstaId !== null && $vrstaId > 0) {
        $fields[] = "`$colVrsta`";
        $ph[]     = '?';
        $vals[]   = $vrstaId;
        $types   .= 'i';
    }

    if (!$fields) jdie('Nema polja za spremiti.');

    $sql = "INSERT INTO `$T_MARKA` (" . implode(',', $fields) . ")
            VALUES (" . implode(',', $ph) . ")";
    $st = $db->prepare($sql);
    $st->bind_param($types, ...$vals);
    $st->execute();

    $newId = (int)$db->insert_id;
    jok(['id' => $newId]);

} catch (mysqli_sql_exception $e) {
    jdie('DB greška: ' . $e->getMessage(), 500);
}
