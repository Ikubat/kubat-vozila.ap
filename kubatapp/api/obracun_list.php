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

kubatapp_require_api('obracun_list.php');

// Vraća popis obračuna iz obracun_vozila bez kompliciranih joinova,
// tako da radi s bilo kojom postojećom shemom partnera/vozila.

header('Content-Type: application/json; charset=utf-8');
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

require_once __DIR__ . '/config.php';

function jdie($m, $c = 400) {
    http_response_code($c);
    echo json_encode(['ok' => false, 'error' => $m], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $db = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
    $db->set_charset('utf8mb4');

    // provjera da tablica postoji
    $chk = $db->query("SHOW TABLES LIKE 'obracun_vozila'");
    if ($chk->num_rows === 0) {
        jdie("Tablica 'obracun_vozila' ne postoji. Kreiraj je prije korištenja.");
    }

    $q = isset($_GET['q']) ? trim((string)$_GET['q']) : '';
    $limit = 200;

    // Bazni upit – uzmi sve kolone (najsigurnije dok ne definiramo točnu strukturu)
    $sql = "SELECT * FROM obracun_vozila";
    $where = [];
    $params = [];
    $types = '';

    if ($q !== '') {
        // Ako je broj -> filtriraj po ID-u
        if (ctype_digit($q)) {
            $where[] = "id = ?";
            $params[] = (int)$q;
            $types .= 'i';
        } else {
            // Ako je tekst -> probaj filtrirati po dodatnom_opisu (ako stupac postoji)
            // Da izbjegnemo grešku, prvo provjerimo postoji li kolona dodatni_opis
            $hasOpis = false;
            $resCols = $db->query("SHOW COLUMNS FROM obracun_vozila LIKE 'dodatni_opis'");
            if ($resCols && $resCols->num_rows > 0) {
                $hasOpis = true;
            }
            if ($hasOpis) {
                $where[] = "dodatni_opis LIKE ?";
                $params[] = '%'.$q.'%';
                $types .= 's';
            }
        }
    }

    if ($where) {
        $sql .= " WHERE ".implode(' AND ', $where);
    }

    $sql .= " ORDER BY id DESC LIMIT ".$limit;

    if ($params) {
        $st = $db->prepare($sql);
        $st->bind_param($types, ...$params);
        $st->execute();
        $res = $st->get_result();
    } else {
        $res = $db->query($sql);
    }

    $rows = [];
    while ($r = $res->fetch_assoc()) {
        $rows[] = $r;
    }

    echo json_encode([
        'ok'    => true,
        'total' => count($rows),
        'data'  => $rows
    ], JSON_UNESCAPED_UNICODE);

} catch (mysqli_sql_exception $e) {
    jdie('DB greška: '.$e->getMessage(), 500);
}
