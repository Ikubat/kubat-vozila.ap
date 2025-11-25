<?php
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

kubatapp_require_api('marka_create.php');

// Dodavanje nove marke u tablicu marka_vozila.
// Očekuje (idealno JSON POST):
// { "naziv": "...", "model": "...", "vrsta_id": 2 }
//
// Radi i ako tvoja tablica NEMA kolonu "model" ili "vrsta_id" - automatski se prilagodi.

header('Content-Type: application/json; charset=utf-8');
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

require_once __DIR__ . '/config.php';

// Fallback nazivi tablica ako nisu definirani u okruženju␊
$T_MARKA = $T_MARKA ?? 'marka_vozila';

function jdie($m, $c = 400) {
    http_response_code($c);
    echo json_encode(['ok' => false, 'error' => $m], JSON_UNESCAPED_UNICODE);
    exit;
}
function jok($x = []) {
    echo json_encode(['ok' => true] + $x, JSON_UNESCAPED_UNICODE);
    exit;
}

// ---- UČITAVANJE PODATAKA ----␊
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$ct = $_SERVER['CONTENT_TYPE'] ?? '';

$naziv = '';
$model = '';
$vrstaId = null;
$serija = '';
$oblik = '';
$vrata = null;
$mjenjac = '';
$pogon = '';
$snaga = null;
$zapremina = null;
$godModela = null;
$godKraj = null;
$kataloska = null;

if ($method === 'POST' && stripos($ct, 'application/json') !== false) {
    $raw = file_get_contents('php://input');
    $in = json_decode($raw, true);
    if (!is_array($in)) jdie('Neispravan JSON body');
    $naziv  = trim((string)($in['naziv'] ?? ''));
    $model  = trim((string)($in['model'] ?? ''));
    if (array_key_exists('vrsta_id', $in) && $in['vrsta_id'] !== '' && $in['vrsta_id'] !== null) {
        $vrstaId = (int)$in['vrsta_id'];
    }
    $serija    = trim((string)($in['serija'] ?? ''));
    $oblik     = trim((string)($in['oblik'] ?? ''));
    $mjenjac   = trim((string)($in['mjenjac'] ?? ''));
    $pogon     = trim((string)($in['pogon'] ?? ''));
    $vrata     = isset($in['vrata']) && $in['vrata'] !== '' ? (int)$in['vrata'] : null;
    $snaga     = isset($in['snaga']) && $in['snaga'] !== '' ? (int)$in['snaga'] : null;
    $zapremina = isset($in['zapremina']) && $in['zapremina'] !== '' ? (int)$in['zapremina'] : null;
    $godModela = isset($in['god_modela']) && $in['god_modela'] !== '' ? (int)$in['god_modela'] : null;
    $godKraj   = isset($in['god_kraj']) && $in['god_kraj'] !== '' ? (int)$in['god_kraj'] : null;
    $kataloska = isset($in['kataloska']) && $in['kataloska'] !== '' ? (float)$in['kataloska'] : null;
} elseif ($method === 'POST') {
    $naziv  = trim((string)($_POST['naziv'] ?? ''));
    $model  = trim((string)($_POST['model'] ?? ''));
    if (isset($_POST['vrsta_id']) && $_POST['vrsta_id'] !== '') {
        $vrstaId = (int)$_POST['vrsta_id'];
    }
    $serija    = trim((string)($_POST['serija'] ?? ''));
    $oblik     = trim((string)($_POST['oblik'] ?? ''));
    $mjenjac   = trim((string)($_POST['mjenjac'] ?? ''));
    $pogon     = trim((string)($_POST['pogon'] ?? ''));␊
    $vrata     = isset($_POST['vrata']) && $_POST['vrata'] !== '' ? (int)$_POST['vrata'] : null;
    $snaga     = isset($_POST['snaga']) && $_POST['snaga'] !== '' ? (int)$_POST['snaga'] : null;
    $zapremina = isset($_POST['zapremina']) && $_POST['zapremina'] !== '' ? (int)$_POST['zapremina'] : null;
    $godModela = isset($_POST['god_modela']) && $_POST['god_modela'] !== '' ? (int)$_POST['god_modela'] : null;
    $godKraj   = isset($_POST['god_kraj']) && $_POST['god_kraj'] !== '' ? (int)$_POST['god_kraj'] : null;
    $kataloska = isset($_POST['kataloska']) && $_POST['kataloska'] !== '' ? (float)$_POST['kataloska'] : null;
} else {
    // GET test: ?naziv=VW&model=Golf&vrsta_id=2
    $naziv  = trim((string)($_GET['naziv'] ?? ''));
    $model  = trim((string)($_GET['model'] ?? ''));
    if (isset($_GET['vrsta_id']) && $_GET['vrsta_id'] !== '') {
        $vrstaId = (int)$_GET['vrsta_id'];
    }
    $serija    = trim((string)($_GET['serija'] ?? ''));
    $oblik     = trim((string)($_GET['oblik'] ?? ''));
    $mjenjac   = trim((string)($_GET['mjenjac'] ?? ''));
    $pogon     = trim((string)($_GET['pogon'] ?? ''));
    $vrata     = isset($_GET['vrata']) && $_GET['vrata'] !== '' ? (int)$_GET['vrata'] : null;
    $snaga     = isset($_GET['snaga']) && $_GET['snaga'] !== '' ? (int)$_GET['snaga'] : null;
    $zapremina = isset($_GET['zapremina']) && $_GET['zapremina'] !== '' ? (int)$_GET['zapremina'] : null;
    $godModela = isset($_GET['god_modela']) && $_GET['god_modela'] !== '' ? (int)$_GET['god_modela'] : null;
    $godKraj   = isset($_GET['god_kraj']) && $_GET['god_kraj'] !== '' ? (int)$_GET['god_kraj'] : null;
    $kataloska = isset($_GET['kataloska']) && $_GET['kataloska'] !== '' ? (float)$_GET['kataloska'] : null;
}

if ($naziv === '') jdie('Naziv je obavezan.');

// ---- DB & STRUKTURA ----␊
try {
    $db = $conn;

    $cols = [];
    $rs = $db->query("SHOW COLUMNS FROM `$T_MARKA`");
    while ($c = $rs->fetch_assoc()) {
        $cols[strtolower($c['Field'])] = $c['Field'];
    }
    if (!$cols) jdie("Tablica `$T_MARKA` ne postoji.");

    $colId        = $cols['id']         ?? $cols['id_marka']  ?? null;
    $colNaziv     = $cols['naziv']      ?? $cols['marka']     ?? $cols['naziv_marka'] ?? null;
    $colModel     = $cols['model']      ?? $cols['tip']       ?? null;
    $colVrsta     = $cols['vrsta_id']   ?? $cols['id_vrsta']  ?? $cols['vrsta'] ?? null;
    $colSerija    = $cols['serija']     ?? null;
    $colOblik     = $cols['oblik']      ?? null;
    $colVrata     = $cols['vrata']      ?? null;
    $colMjenjac   = $cols['mjenjac']    ?? null;
    $colPogon     = $cols['pogon']      ?? null;
    $colSnaga     = $cols['snaga']      ?? null;
    $colZapremina = $cols['zapremina']  ?? null;
    $colGodModela = $cols['god_modela'] ?? $cols['godina_od'] ?? $cols['god_od'] ?? null;
    $colGodKraj   = $cols['god_kraj']   ?? $cols['godina_do'] ?? $cols['god_do'] ?? null;
    $colKataloska = $cols['kataloska']  ?? null;
    
    if (!$colNaziv) jdie("Tablica `$T_MARKA` nema kolonu za naziv.");

    // ---- PRIPREMA INSERTA ----
    $fields = [];
    $ph     = [];
    $vals   = [];
    $types  = '';

    // naziv je uvijek tu␊
    $fields[] = "`$colNaziv`";
    $ph[]     = '?';
    $vals[]   = $naziv;
    $types   .= 's';

    // model ako postoji kolona␊
    if ($colModel && $model !== '') {
        $fields[] = "`$colModel`";
        $ph[]     = '?';
        $vals[]   = $model;
        $types   .= 's';
    }

    $optionalFields = [
        [$colSerija, $serija, 's'],
        [$colOblik, $oblik, 's'],
        [$colVrata, $vrata, 'i'],
        [$colMjenjac, $mjenjac, 's'],
        [$colPogon, $pogon, 's'],
        [$colSnaga, $snaga, 'i'],
        [$colZapremina, $zapremina, 'i'],
        [$colGodModela, $godModela, 'i'],
        [$colGodKraj, $godKraj, 'i'],
        [$colKataloska, $kataloska, 'd']
    ];

    foreach ($optionalFields as [$col, $val, $type]) {
        if ($col && $val !== null && ($type !== 's' || $val !== '')) {
            $fields[] = "`$col`";
            $ph[]     = '?';
            $vals[]   = $val;
            $types   .= $type;
        }
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

