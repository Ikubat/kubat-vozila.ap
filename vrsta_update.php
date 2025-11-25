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

kubatapp_require_api('vrsta_update.php');

// vrsta_update.php
// Ažurira postojeću vrstu vozila u tablici vrsta_vozila.
// Očekuje (POST ili JSON):
// { "id": 3, "naziv": "Putničko vozilo", "oznaka": "M1" }

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
    $naziv = null;
    $oznaka = null;

    if ($method === 'POST' && stripos($ct, 'application/json') !== false) {
        $raw = file_get_contents('php://input');
        $in  = json_decode($raw, true);
        if (!is_array($in)) jdie('Neispravan JSON.');

        $id     = (int)($in['id'] ?? 0);
        if (isset($in['naziv']))  $naziv  = trim((string)$in['naziv']);
        if (isset($in['oznaka'])) $oznaka = trim((string)$in['oznaka']);
    } elseif ($method === 'POST') {
        $id     = (int)($_POST['id'] ?? 0);
        if (isset($_POST['naziv']))  $naziv  = trim((string)$_POST['naziv']);
        if (isset($_POST['oznaka'])) $oznaka = trim((string)$_POST['oznaka']);
    } else {
        // GET test: ?id=1&naziv=Novo&oznaka=X
        $id     = (int)($_GET['id'] ?? 0);
        if (isset($_GET['naziv']))  $naziv  = trim((string)$_GET['naziv']);
        if (isset($_GET['oznaka'])) $oznaka = trim((string)$_GET['oznaka']);
    }

    if ($id <= 0) jdie('ID je obavezan.');

    if ($naziv !== null && $naziv === '') jdie('Naziv ne može biti prazan.');
    // oznaka može biti prazna, ali ako nije poslana ne diramo

    $db = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
    $db->set_charset('utf8mb4');

    // postoji li ta vrsta?
    $st = $db->prepare("SELECT id FROM vrsta_vozila WHERE id = ?");
    $st->bind_param('i', $id);
    $st->execute();
    $row = $st->get_result()->fetch_assoc();
    if (!$row) jdie('Vrsta ne postoji.');

    // složi SET dio
    $sets = [];
    $vals = [];
    $types = '';

    if ($naziv !== null) {
        $sets[] = "naziv = ?";
        $vals[] = $naziv;
        $types .= 's';
    }
    if ($oznaka !== null) {
        $sets[] = "oznaka = ?";
        $vals[] = $oznaka;
        $types .= 's';
    }

    if (!$sets) {
        jdie('Nema promjena za spremiti.');
    }

    $sql = "UPDATE vrsta_vozila SET ".implode(', ', $sets)." WHERE id = ?";
    $vals[] = $id;
    $types .= 'i';

    $st = $db->prepare($sql);
    $st->bind_param($types, ...$vals);
    $st->execute();

    jok();

} catch (mysqli_sql_exception $e) {
    jdie('DB greška: '.$e->getMessage(), 500);
}
