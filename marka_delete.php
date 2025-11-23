<?php
// Briše marku iz tablice marka_vozila po ID-u.
//
// Prihvaća:
// - JSON POST: { "id": 5 }
// - ili form POST: id=5
// - ili GET: ?id=5 (korisno za test u browseru)
//
// Vraća:
// {"ok":true}
// ili
// {"ok":false,"error":"..."} po potrebi

header('Content-Type: application/json; charset=utf-8');
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

require_once __DIR__ . '/config.php';

// Fallback nazivi tablica ako nisu definirani u okruženju
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

// ---- UČITAVANJE ID-a ----
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$ct = $_SERVER['CONTENT_TYPE'] ?? '';

$id = 0;

if ($method === 'POST' && stripos($ct, 'application/json') !== false) {
    $raw = file_get_contents('php://input');
    $in  = json_decode($raw, true);
    if (!is_array($in)) jdie('Neispravan JSON body');
    $id = (int)($in['id'] ?? 0);
} elseif ($method === 'POST') {
    $id = (int)($_POST['id'] ?? 0);
} else {
    // GET test: ?id=5
    $id = (int)($_GET['id'] ?? 0);
}

if ($id <= 0) {
    jdie('ID je obavezan.');
}

// ---- DB & STRUKTURA ----
try {
    $db = $conn;

    // autodetekcija naziva ID kolone
    $cols = [];
    $rs = $db->query("SHOW COLUMNS FROM `$T_MARKA`");
    while ($c = $rs->fetch_assoc()) {
        $cols[strtolower($c['Field'])] = $c['Field'];
    }
    if (!$cols) {
        jdie("Tablica `$T_MARKA` ne postoji.");
    }

    $colId = $cols['id'] ?? $cols['id_marka'] ?? null;
    if (!$colId) {
        jdie("Tablica `$T_MARKA` nema ID kolonu (id / id_marka).");
    }

    // postoji li taj zapis?
    $st = $db->prepare("SELECT `$colId` FROM `$T_MARKA` WHERE `$colId`=?");
    $st->bind_param('i', $id);
    $st->execute();
    if (!$st->get_result()->fetch_assoc()) {
        jdie('Marka ne postoji.');
    }

    // pokušaj brisanja
    $st = $db->prepare("DELETE FROM `$T_MARKA` WHERE `$colId`=?");
    $st->bind_param('i', $id);
    $st->execute();

    jok();

} catch (mysqli_sql_exception $e) {
    // ako FK blokira brisanje, poruka će doći ovdje
    jdie('DB greška: ' . $e->getMessage(), 500);
}
