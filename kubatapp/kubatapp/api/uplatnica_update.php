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

    $sql = "UPDATE `$T_UPLATNICE`
            SET uplatilac_id        = ?,
                primatelj_id        = ?,
                svrha_id            = ?,
                svrha               = ?,
                svrha1              = ?,
                mjesto_uplate       = ?,
                datum_uplate        = ?,
                iznos               = ?,
                valuta              = ?,
                racun_posiljaoca    = ?,
                racun_primatelja    = ?,
                broj_poreskog_obv   = ?,
                vrsta_prihoda_sifra = ?,
                opcina_sifra        = ?,
                budzetska_org_sifra = ?,
                poziv_na_broj       = ?,
                napomena            = ?
            WHERE id = ?";

    $st = $db->prepare($sql);
    $st->bind_param(
      'iiissssdsdsssssssi',
      $uplatilac_id,
      $primatelj_id,
      $svrha_id,
      $svrha,
      $svrha1,
      $mjesto_uplate,
      $datum_uplate,
      $iznos,
      $valuta,
      $racun_posiljaoca,
      $racun_primatelja,
      $broj_poreskog_obv,
      $vrsta_prihoda_sifra,
      $opcina_sifra,
      $budzetska_org_sifra,
      $poziv_na_broj,
      $napomena,
      $id
    );
    $st->execute();

    jok();

} catch (mysqli_sql_exception $e) {
    jdie('DB greška: '.$e->getMessage(), 500);
}
