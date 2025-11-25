<?php
require_once __DIR__ . '/_bootstrap.php';
kubatapp_require_api('vrsta_delete.php');

// Briše vrstu vozila ako je ne koristi nijedna marka_vozila.
// Očekuje: { "id": 3 } (POST JSON) ili klasični POST id=3.

header('Content-Type: application/json; charset=utf-8');
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
require_once __DIR__ . '/config.php';

$DB_HOST = 'localhost';
$DB_USER = 'root';
$DB_PASS = '';
$DB_NAME = 'kubatapp';

function jdie($m, $c = 400) {
    http_response_code($c);
    echo json_encode(['ok' => false, 'error' => $m], JSON_UNESCAPED_UNICODE);
    exit;
}
function jok($x = []) {
    echo json_encode(['ok' => true] + $x, JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    $ct = $_SERVER['CONTENT_TYPE'] ?? '';

    $id = 0;

    if ($method === 'POST' && stripos($ct, 'application/json') !== false) {
        $raw = file_get_contents('php://input');
        $in  = json_decode($raw, true);
        if (!is_array($in)) jdie('Neispravan JSON.');
        $id = (int)($in['id'] ?? 0);
    } elseif ($method === 'POST') {
        $id = (int)($_POST['id'] ?? 0);
    } else {
        $id = (int)($_GET['id'] ?? 0);
    }

    if ($id <= 0) jdie('ID je obavezan.');

    $db = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
    $db->set_charset('utf8mb4');

    // postoji?
    $st = $db->prepare("SELECT id FROM vrsta_vozila WHERE id = ?");
    $st->bind_param('i', $id);
    $st->execute();
    $row = $st->get_result()->fetch_assoc();
    if (!$row) jdie('Vrsta ne postoji.');

    // koristi li je neka marka?
    $st = $db->prepare("SELECT COUNT(*) AS c FROM marka_vozila WHERE vrsta_id = ?");
    $st->bind_param('i', $id);
    $st->execute();
    $c = (int)$st->get_result()->fetch_assoc()['c'];

    if ($c > 0) {
        jdie("Vrstu nije moguće obrisati jer je povezana s $c marka(e/).");
    }

    // obriši
    $st = $db->prepare("DELETE FROM vrsta_vozila WHERE id = ?");
    $st->bind_param('i', $id);
    $st->execute();

    jok();

} catch (mysqli_sql_exception $e) {
    jdie('DB greška: '.$e->getMessage(), 500);
}
