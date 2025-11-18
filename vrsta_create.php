<?php
// vrsta_create.php - doda novu vrstu u vrsta_vozila
// prima JSON ili form-data: naziv, oznaka

header('Content-Type: application/json; charset=utf-8');
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$DB_HOST = 'localhost';
$DB_USER = 'root';
$DB_PASS = '';
$DB_NAME = 'kubatapp';

function jdie($msg, $code = 400) {
    http_response_code($code);
    echo json_encode(['ok' => false, 'error' => $msg], JSON_UNESCAPED_UNICODE);
    exit;
}
function jok($extra = []) {
    echo json_encode(['ok' => true] + $extra, JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    $ct = $_SERVER['CONTENT_TYPE'] ?? '';

    $naziv = '';
    $oznaka = '';

    if ($method === 'POST' && stripos($ct, 'application/json') !== false) {
        $raw = file_get_contents('php://input');
        $in = json_decode($raw, true);
        if (!is_array($in)) jdie('Neispravan JSON');
        $naziv = trim((string)($in['naziv'] ?? ''));
        $oznaka = trim((string)($in['oznaka'] ?? ''));
    } elseif ($method === 'POST') {
        $naziv = trim((string)($_POST['naziv'] ?? ''));
        $oznaka = trim((string)($_POST['oznaka'] ?? ''));
    } else {
        // GET test iz browsera: ?naziv=...&oznaka=...
        $naziv = trim((string)($_GET['naziv'] ?? ''));
        $oznaka = trim((string)($_GET['oznaka'] ?? ''));
    }

    if ($naziv === '') jdie('Naziv je obavezan.');
    if ($oznaka === '') {
        $oznaka = $naziv; // ako kolona ne dopušta NULL
    }

    $db = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
    $db->set_charset('utf8mb4');

    // ako već postoji ista (naziv, oznaka), vrati taj id
    $st = $db->prepare("SELECT id FROM vrsta_vozila WHERE naziv=? AND oznaka=? LIMIT 1");
    $st->bind_param('ss', $naziv, $oznaka);
    $st->execute();
    if ($row = $st->get_result()->fetch_assoc()) {
        jok(['id' => (int)$row['id'], 'note' => 'exists']);
    }

    // insert
    $st = $db->prepare("INSERT INTO vrsta_vozila (naziv, oznaka) VALUES (?, ?)");
    $st->bind_param('ss', $naziv, $oznaka);
    $st->execute();
    $id = (int)$db->insert_id;

    jok(['id' => $id]);

} catch (mysqli_sql_exception $e) {
    jdie('DB greska: ' . $e->getMessage(), 500);
}
