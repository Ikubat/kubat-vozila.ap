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

kubatapp_require_api('uplatnica_update.php');

header('Content-Type: application/json; charset=utf-8');
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$T_UPLATNICE = $T_UPLATNICE ?? 'uplatnice';

function jdie($m, $c = 400) {
    http_response_code($c);
    echo json_encode(['ok' => false, 'error' => $m], JSON_UNESCAPED_UNICODE);
    exit;
}
function jok($x = []) {
    echo json_encode(['ok' => true] + $x, JSON_UNESCAPED_UNICODE);
    exit;
}

// ---- ULAZ ----
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$ct     = $_SERVER['CONTENT_TYPE'] ?? '';

$data = [];

if ($method === 'POST' && stripos($ct, 'application/json') !== false) {
    $raw = file_get_contents('php://input');
    $in  = json_decode($raw, true);
    if (!is_array($in)) jdie('Neispravan JSON body');
    $data = $in;
} elseif ($method === 'POST') {
    $data = $_POST;
} else {
    $data = $_GET;
}

$id = isset($data['id']) ? (int)$data['id'] : 0;
if ($id <= 0) jdie('ID je obavezan.');

$uplatilac_id        = isset($data['uplatilac_id']) ? (int)$data['uplatilac_id'] : 0;
$primatelj_id        = isset($data['primatelj_id']) ? (int)$data['primatelj_id'] : 0;
$svrha_id            = isset($data['svrha_id']) && $data['svrha_id'] !== '' ? (int)$data['svrha_id'] : null;
$svrha               = trim((string)($data['svrha']  ?? ''));
$svrha1              = trim((string)($data['svrha1'] ?? ''));
$uplatilac_tekst     = trim((string)($data['uplatilac_tekst'] ?? ''));
$primatelj_tekst     = trim((string)($data['primatelj_tekst'] ?? ''));
$mjesto_uplate       = trim((string)($data['mjesto_uplate'] ?? ''));
$datum_uplate        = trim((string)($data['datum_uplate']  ?? ''));
$iznos               = isset($data['iznos']) ? (float)$data['iznos'] : 0;
$valuta              = trim((string)($data['valuta'] ?? 'KM'));
$racun_posiljaoca    = trim((string)($data['racun_posiljaoca'] ?? ''));
$racun_primatelja    = trim((string)($data['racun_primatelja'] ?? ''));
$broj_poreskog_obv   = trim((string)($data['broj_poreskog_obv'] ?? ''));
$vrsta_prihoda_sifra = trim((string)($data['vrsta_prihoda_sifra'] ?? ''));
$opcina_sifra        = trim((string)($data['opcina_sifra'] ?? ''));
$budzetska_org_sifra = trim((string)($data['budzetska_org_sifra'] ?? ''));
$poziv_na_broj       = trim((string)($data['poziv_na_broj'] ?? ''));
$napomena            = trim((string)($data['napomena'] ?? ''));

// minimalna validacija
if ($uplatilac_id <= 0) jdie('Uplatilac je obavezan.');
if ($primatelj_id <= 0) jdie('Primatelj je obavezan.');
if ($svrha === '')      jdie('Svrha uplate je obavezna.');
if ($datum_uplate === '') jdie('Datum je obavezan.');
if ($iznos <= 0)        jdie('Iznos mora biti veći od 0.');
if ($racun_primatelja === '') jdie('Račun primatelja je obavezan.');

try {
    $db = $conn;

    // postoji li zapis?
    $st = $db->prepare("SELECT id FROM `$T_UPLATNICE` WHERE id=?");
    $st->bind_param('i', $id);
    $st->execute();
    if (!$st->get_result()->fetch_assoc()) {
        jdie('Uplatnica ne postoji.', 404);
    }

    $cols = [];
    $rs = $db->query("SHOW COLUMNS FROM `$T_UPLATNICE`");
    while ($c = $rs->fetch_assoc()) {
        $cols[strtolower($c['Field'])] = $c['Field'];
    }

    $colOrDefault = function (string $key, string $fallback) use ($cols) {
        return $cols[strtolower($key)] ?? $fallback;
    };

    $colUplatilacTxt = $cols['uplatilac_tekst'] ?? null;
    $colPrimateljTxt = $cols['primatelj_tekst'] ?? null;

    $fields = [
        ['name' => $colOrDefault('uplatilac_id', 'uplatilac_id'), 'type' => 'i', 'value' => $uplatilac_id],
        ['name' => $colOrDefault('primatelj_id', 'primatelj_id'), 'type' => 'i', 'value' => $primatelj_id],
        ['name' => $colOrDefault('svrha_id', 'svrha_id'), 'type' => 'i', 'value' => $svrha_id],
        ['name' => $colOrDefault('svrha', 'svrha'), 'type' => 's', 'value' => $svrha],
        ['name' => $colOrDefault('svrha1', 'svrha1'), 'type' => 's', 'value' => $svrha1],
        ['name' => $colOrDefault('mjesto_uplate', 'mjesto_uplate'), 'type' => 's', 'value' => $mjesto_uplate],
        ['name' => $colOrDefault('datum_uplate', 'datum_uplate'), 'type' => 's', 'value' => $datum_uplate],
        ['name' => $colOrDefault('iznos', 'iznos'), 'type' => 'd', 'value' => $iznos],
        ['name' => $colOrDefault('valuta', 'valuta'), 'type' => 's', 'value' => $valuta],
        ['name' => $colOrDefault('racun_posiljaoca', 'racun_posiljaoca'), 'type' => 's', 'value' => $racun_posiljaoca],
        ['name' => $colOrDefault('racun_primatelja', 'racun_primatelja'), 'type' => 's', 'value' => $racun_primatelja],
        ['name' => $colOrDefault('broj_poreskog_obv', 'broj_poreskog_obv'), 'type' => 's', 'value' => $broj_poreskog_obv],
        ['name' => $colOrDefault('vrsta_prihoda_sifra', 'vrsta_prihoda_sifra'), 'type' => 's', 'value' => $vrsta_prihoda_sifra],
        ['name' => $colOrDefault('opcina_sifra', 'opcina_sifra'), 'type' => 's', 'value' => $opcina_sifra],
        ['name' => $colOrDefault('budzetska_org_sifra', 'budzetska_org_sifra'), 'type' => 's', 'value' => $budzetska_org_sifra],
        ['name' => $colOrDefault('poziv_na_broj', 'poziv_na_broj'), 'type' => 's', 'value' => $poziv_na_broj],
        ['name' => $colOrDefault('napomena', 'napomena'), 'type' => 's', 'value' => $napomena],
    ];

    if ($colUplatilacTxt) {
        $fields[] = ['name' => $colUplatilacTxt, 'type' => 's', 'value' => $uplatilac_tekst];
    }
    if ($colPrimateljTxt) {
        $fields[] = ['name' => $colPrimateljTxt, 'type' => 's', 'value' => $primatelj_tekst];
    }

    $assignments = array_map(function ($f) {
        return '`' . $f['name'] . '` = ?';
    }, $fields);
    $types = implode('', array_column($fields, 'type')) . 'i';
    $values = array_column($fields, 'value');
    $values[] = $id;

    $sql = "UPDATE `$T_UPLATNICE` SET " . implode(', ', $assignments) . " WHERE id = ?";

    $st = $db->prepare($sql);
    $st->bind_param($types, ...$values);
    $st->execute();

    jok();

} catch (mysqli_sql_exception $e) {
    jdie('DB greška: '.$e->getMessage(), 500);
}
