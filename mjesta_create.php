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

kubatapp_require_api('mjesta_create.php');

// Dodaje novo mjesto u tablicu `mjesta`.
//
// Prihvaća POST JSON ili klasični POST:
// { "naziv": "...", "sifra": "...", "kanton": "..." }
//
// Radi i ako se kolona zove naziv, mjesto ili naziv_mjesta.

header('Content-Type: application/json; charset=utf-8');
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

require_once __DIR__ . '/config.php';

function jdie($msg, $code = 200) {
    http_response_code($code);
    echo json_encode(['ok' => false, 'error' => $msg], JSON_UNESCAPED_UNICODE);
    exit;
}
function jok($extra = []) {
    echo json_encode(['ok' => true] + $extra, JSON_UNESCAPED_UNICODE);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($method !== 'POST') {
    jdie('Ovaj endpoint služi za dodavanje mjesta (POST).');
}

$ct = $_SERVER['CONTENT_TYPE'] ?? '';

$naziv = '';
$sifra = '';
$kanton = '';

// JSON
if (stripos($ct, 'application/json') !== false) {
    $raw = file_get_contents('php://input');
    $in = json_decode($raw, true);
    if (!is_array($in)) {
        jdie('Neispravan JSON.');
    }
    $naziv  = trim((string)($in['naziv']  ?? $in['m_naziv']  ?? $in['naziv_mjesta'] ?? ''));
    $sifra  = trim((string)($in['sifra']  ?? $in['m_sifra']  ?? $in['porezna_sifra'] ?? ''));
    $kanton = trim((string)($in['kanton'] ?? $in['m_kanton'] ?? ''));

// Klasični POST
} else {
    $naziv  = trim((string)($_POST['naziv']  ?? $_POST['m_naziv']  ?? $_POST['naziv_mjesta'] ?? ''));
    $sifra  = trim((string)($_POST['sifra']  ?? $_POST['m_sifra']  ?? $_POST['porezna_sifra'] ?? ''));
    $kanton = trim((string)($_POST['kanton'] ?? $_POST['m_kanton'] ?? ''));␊
}

if ($naziv === '') {
    jdie('Naziv mjesta je obavezan.');
}

try {
    $db = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
    $db->set_charset('utf8mb4');

    // Provjera tablice
    $chk = $db->query("SHOW TABLES LIKE 'mjesta'");
    if ($chk->num_rows === 0) {
        jdie("Tablica 'mjesta' ne postoji u bazi.");
    }

    // Kolone u tablici
    $colsRes = $db->query("SHOW COLUMNS FROM mjesta");
    $cols = [];
    while ($c = $colsRes->fetch_assoc()) {
        $cols[strtolower($c['Field'])] = $c['Field'];
    }

    $colId = $cols['id'] ?? 'id';

    // >>> OVDJE JE KLJUČ: prihvaćamo više naziva kolone za naziv mjesta
    $colNaziv =
        $cols['naziv']
        ?? $cols['mjesto']
        ?? $cols['naziv_mjesta']
        ?? null;

    if (!$colNaziv) {
        jdie("Tablica 'mjesta' mora imati kolonu 'naziv' ili 'mjesto' ili 'naziv_mjesta'.");
    }

    $colSifra  = $cols['sifra']  ?? ($cols['porezna_sifra'] ?? null);
    $colKanton = $cols['kanton'] ?? null;

    // Provjera duplikata po nazivu
    $sqlDup = "SELECT `$colId` FROM mjesta WHERE `$colNaziv` = ? LIMIT 1";
    $st = $db->prepare($sqlDup);
    $st->bind_param('s', $naziv);
    $st->execute();
    $du = $st->get_result()->fetch_assoc();
    if ($du) {
        jok(['id' => (int)$du[$colId], 'msg' => 'Mjesto već postoji.']);
    }

    // Slaganje INSERT-a
    $fields = [$colNaziv];
    $marks  = ['?'];
    $types  = 's';
    $vals   = [$naziv];

    if ($colSifra && $sifra !== '') {
        $fields[] = $colSifra;
        $marks[]  = '?';
        $types   .= 's';
        $vals[]   = $sifra;
    }
    if ($colKanton && $kanton !== '') {
        $fields[] = $colKanton;
        $marks[]  = '?';
        $types   .= 's';
        $vals[]   = $kanton;
    }

    $sql = "INSERT INTO mjesta (`" . implode("`,`", $fields) . "`) VALUES (" . implode(',', $marks) . ")";
    $st = $db->prepare($sql);
    $st->bind_param($types, ...$vals);
    $st->execute();

    $newId = $db->insert_id ?: null;
    jok(['id' => $newId]);

} catch (mysqli_sql_exception $e) {
    jdie('DB greška: ' . $e->getMessage());
}