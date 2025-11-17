<?php
require __DIR__ . '/../marka_update.php';
// Ažuriranje postojeće marke u marka_vozila.
// Očekuje (JSON ili POST):
// { "id": 5, "naziv": "...", "model": "...", "vrsta_id": 2 }
//
// Radi i ako tablica nema "model" ili "vrsta_id" - ažurira samo ono što postoji.

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

$id = 0;
$naziv = null;
$model = null;
$vrstaId = null;
$hasVrsta = false;

if ($method === 'POST' && stripos($ct, 'application/json') !== false) {
    $raw = file_get_contents('php://input');
    $in = json_decode($raw, true);
    if (!is_array($in)) jdie('Neispravan JSON body');
    $id      = (int)($in['id'] ?? 0);
    $naziv   = array_key_exists('naziv', $in) ? trim((string)$in['naziv']) : null;
    $model   = array_key_exists('model', $in) ? trim((string)$in['model']) : null;
    if (array_key_exists('vrsta_id', $in)) {
        $hasVrsta = true;
        $vrstaId = ($in['vrsta_id'] !== '' && $in['vrsta_id'] !== null) ? (int)$in['vrsta_id'] : null;
    }
} elseif ($method === 'POST') {
    $id    = (int)($_POST['id'] ?? 0);
    if (isset($_POST['naziv']))   $naziv   = trim((string)$_POST['naziv']);
    if (isset($_POST['model']))   $model   = trim((string)$_POST['model']);
    if (isset($_POST['vrsta_id'])) {
        $hasVrsta = true;
        $vrstaId = $_POST['vrsta_id'] !== '' ? (int)$_POST['vrsta_id'] : null;
    }
} else {
    // GET test: ?id=5&naziv=NovoIme&model=X&vrsta_id=2
    $id    = (int)($_GET['id'] ?? 0);
    if (isset($_GET['naziv']))   $naziv   = trim((string)$_GET['naziv']);
    if (isset($_GET['model']))   $model   = trim((string)$_GET['model']);
    if (isset($_GET['vrsta_id'])) {
        $hasVrsta = true;
        $vrstaId = $_GET['vrsta_id'] !== '' ? (int)$_GET['vrsta_id'] : null;
    }
}

if ($id <= 0) jdie('ID je obavezan.');

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

    if (!$colId) jdie("Tablica `$T_MARKA` nema ID kolonu.");

        // ako postoji kolona za model i klijent ju je poslao, ne dopuštamo prazan string
    if ($colModel && $model !== null && $model === '') {
        jdie('Model ne može biti prazan.');
    }


    // postoji li zapis?
    $st = $db->prepare("SELECT * FROM `$T_MARKA` WHERE `$colId`=?");
    $st->bind_param('i', $id);
    $st->execute();
    $row = $st->get_result()->fetch_assoc();
    if (!$row) jdie('Marka ne postoji.');

    // priprema SET dijela
    $sets  = [];
    $vals  = [];
    $types = '';

    if ($colNaziv && $naziv !== null) {
        if ($naziv === '') jdie('Naziv ne može biti prazan.');
        $sets[] = "`$colNaziv` = ?";
        $vals[] = $naziv;
        $types .= 's';
    }

    if ($colModel && $model !== null) {
        $sets[] = "`$colModel` = ?";
        $vals[] = $model;
        $types .= 's';
    }

    if ($colVrsta && $hasVrsta) {
        if ($vrstaId !== null && $vrstaId > 0) {
            $sets[] = "`$colVrsta` = ?";
            $vals[] = $vrstaId;
            $types .= 'i';
        } else {
            $sets[] = "`$colVrsta` = NULL";
        }
    }

    if (!$sets) {
        jdie('Nema polja za ažuriranje.');
    }

    $sql = "UPDATE `$T_MARKA` SET " . implode(', ', $sets) . " WHERE `$colId` = ?";
    $vals[] = $id;
    $types .= 'i';

    $st = $db->prepare($sql);
    $st->bind_param($types, ...$vals);
    $st->execute();

    jok();

} catch (mysqli_sql_exception $e) {
    jdie('DB greška: ' . $e->getMessage(), 500);
}
