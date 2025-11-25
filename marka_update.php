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

kubatapp_require_api('marka_update.php');

// Ažuriranje postojeće marke u marka_vozila.
// Očekuje (JSON ili POST):␊
// { "id": 5, "naziv": "...", "model": "...", "vrsta_id": 2 }
//
// Radi i ako tablica nema "model" ili "vrsta_id" - ažurira samo ono što postoji.

header('Content-Type: application/json; charset=utf-8');
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
ini_set('html_errors', 0);

// Prisilno sve PHP greške pretvaramo u JSON odgovor umjesto HTML-a
set_error_handler(function ($severity, $message, $file, $line) {
    throw new ErrorException($message, 0, $severity, $file, $line);
});
set_exception_handler(function (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'ok'    => false,
        'error' => 'Server error: ' . $e->getMessage(),
    ], JSON_UNESCAPED_UNICODE);
    exit;
});
register_shutdown_function(function () {
    $err = error_get_last();
    if ($err && in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        http_response_code(500);
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
        }
        echo json_encode([
            'ok'    => false,
            'error' => 'Fatal error: ' . $err['message'],
        ], JSON_UNESCAPED_UNICODE);
    }
});

require_once __DIR__ . '/config.php';
␊
// Fallback nazivi tablica ako nisu definirani u okruženju
$T_MARKA = $T_MARKA ?? 'marka_vozila';

function jdie($m, $c = 400) {
    http_response_code($c);
    echo json_encode(['ok' => false, 'error' => $m], JSON_UNESCAPED_UNICODE);
    exit;
}
function jok($x = []) {
    echo json_encode(['ok' => true] + $x, JSON_UNESCAPED_UNICODE);␊
    exit;
}

// ---- UČITAVANJE PODATAKA ----␊
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$ct = $_SERVER['CONTENT_TYPE'] ?? '';

$id = 0;
$naziv = null;
$model = null;
$vrstaId = null;
$hasVrsta = false;
$optionalValues = [
    'serija'     => null,
    'oblik'      => null,
    'vrata'      => null,
    'mjenjac'    => null,
    'pogon'      => null,
    'snaga'      => null,
    'zapremina'  => null,
    'god_modela' => null,
    'god_kraj'   => null,
    'kataloska'  => null,
];
$optProvided = [];

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
    foreach ($optionalValues as $k => $_) {
        if (array_key_exists($k, $in)) {
            $optProvided[$k] = true;
            $val = $in[$k];
            if (in_array($k, ['vrata','snaga','zapremina','god_modela','god_kraj'], true)) {
                $optionalValues[$k] = $val !== '' && $val !== null ? (int)$val : null;
            } elseif ($k === 'kataloska') {
                $optionalValues[$k] = $val !== '' && $val !== null ? (float)$val : null;
            } else {
                $optionalValues[$k] = $val !== null ? trim((string)$val) : null;
            }
        }
    }
} elseif ($method === 'POST') {
    $id    = (int)($_POST['id'] ?? 0);
    if (isset($_POST['naziv']))   $naziv   = trim((string)$_POST['naziv']);
    if (isset($_POST['model']))   $model   = trim((string)$_POST['model']);
    if (isset($_POST['vrsta_id'])) {
        $hasVrsta = true;
        $vrstaId = $_POST['vrsta_id'] !== '' ? (int)$_POST['vrsta_id'] : null;
    }
    foreach ($optionalValues as $k => $_) {
        if (isset($_POST[$k])) {
            $optProvided[$k] = true;
            $val = $_POST[$k];
            if (in_array($k, ['vrata','snaga','zapremina','god_modela','god_kraj'], true)) {
                $optionalValues[$k] = $val !== '' ? (int)$val : null;
            } elseif ($k === 'kataloska') {
                $optionalValues[$k] = $val !== '' ? (float)$val : null;
            } else {
                $optionalValues[$k] = $val !== null ? trim((string)$val) : null;
            }
        }
    }
} else {
    // GET test: ?id=5&naziv=NovoIme&model=X&vrsta_id=2␊
    $id    = (int)($_GET['id'] ?? 0);
    if (isset($_GET['naziv']))   $naziv   = trim((string)$_GET['naziv']);
    if (isset($_GET['model']))   $model   = trim((string)$_GET['model']);
    if (isset($_GET['vrsta_id'])) {
        $hasVrsta = true;
        $vrstaId = $_GET['vrsta_id'] !== '' ? (int)$_GET['vrsta_id'] : null;
    }
    foreach ($optionalValues as $k => $_) {
        if (isset($_GET[$k])) {
            $optProvided[$k] = true;
            $val = $_GET[$k];
            if (in_array($k, ['vrata','snaga','zapremina','god_modela','god_kraj'], true)) {
                $optionalValues[$k] = $val !== '' ? (int)$val : null;
            } elseif ($k === 'kataloska') {
                $optionalValues[$k] = $val !== '' ? (float)$val : null;
            } else {
                $optionalValues[$k] = $val !== null ? trim((string)$val) : null;
            }
        }
    }
}

if ($id <= 0) jdie('ID je obavezan.');

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
    
    if (!$colId) jdie("Tablica `$T_MARKA` nema ID kolonu.");

        // ako postoji kolona za model i klijent ju je poslao, ne dopuštamo prazan string␊
    if ($colModel && $model !== null && $model === '') {
        jdie('Model ne može biti prazan.');
    }


    // postoji li zapis?␊
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

    $optionalCols = [
        'serija'     => [$colSerija, 's'],
        'oblik'      => [$colOblik, 's'],
        'vrata'      => [$colVrata, 'i'],
        'mjenjac'    => [$colMjenjac, 's'],
        'pogon'      => [$colPogon, 's'],
        'snaga'      => [$colSnaga, 'i'],
        'zapremina'  => [$colZapremina, 'i'],
        'god_modela' => [$colGodModela, 'i'],
        'god_kraj'   => [$colGodKraj, 'i'],
        'kataloska'  => [$colKataloska, 'd'],
    ];

    foreach ($optionalCols as $key => [$col, $type]) {
        if ($col && ($optProvided[$key] ?? false)) {
            $val = $optionalValues[$key];
            if ($val !== null) {
                $sets[] = "`$col` = ?";
                $vals[] = $val;
                $types .= $type;
            } else {
                $sets[] = "`$col` = NULL";
            }
        }
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