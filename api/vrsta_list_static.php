<?php
// vrsta_list.php — JSON API (mysqli) | all=1, paginacija, q (pretraga), status (opcija)

require_once __DIR__ . '/../_bootstrap.php';
require_once __DIR__ . '/config.php';

header('Content-Type: application/json; charset=utf-8');
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// ======= MAPIRANJE TABLICE/KOLONA (po potrebi prilagodi) =======
// Ako u config.php imaš $T_VRSTA, koristi njega, inače default 'vrsta'
$tableName = $T_VRSTA ?? 'vrsta';      // npr. 'vrsta_vozila'
$colId     = 'id';                     // npr. 'id_vrste'
$colNaziv  = 'naziv';                  // npr. 'naziv_vrste'
$colOznaka = 'oznaka';                 // npr. 'oznaka_vrste'
// Ako NEMAŠ kolonu za aktivnost, ostavi na '' (prazno)
$colAktivan = '';                      // npr. 'aktivan' ili ''

// ======= POMOĆNE =======
function fail($msg, $code = 500){
    http_response_code($code);
    echo json_encode(['ok' => false, 'error' => $msg], JSON_UNESCAPED_UNICODE);
    exit;
}

// ======= KONEKCIJA (iz config.php) =======
if (!isset($conn) || !($conn instanceof mysqli)) {
    fail('DB konekcija ($conn) nije dostupna iz config.php', 500);
}
$db = $conn;
$db->set_charset('utf8mb4');

// ======= PARAMETRI =======
$src      = $_REQUEST;
$q        = isset($src['q']) ? trim((string)$src['q']) : '';
$status   = isset($src['status']) ? (string)$src['status'] : ''; // '1','0','all' (samo ako $colAktivan != '')
$allFlag  = isset($src['all']) ? (string)$src['all'] : '';
$page     = isset($src['page']) ? max(1, (int)$src['page']) : 1;

// page_size ili limit (fallback)
if (isset($src['page_size']))      $pageSize = (int)$src['page_size'];
elseif (isset($src['limit']))      $pageSize = (int)$src['limit'];
else                               $pageSize = 50;

if ($pageSize <= 0) $pageSize = 50;
$pageSize = min($pageSize, 5000);

// ======= WHERE SASTAVLJANJE =======
$where = [];
$args  = [];
$types = '';

// pretraga po nazivu/oznaci
if ($q !== '') {
    $where[] = "(`$colNaziv` LIKE ? OR `$colOznaka` LIKE ?)";
    $args[]  = "%{$q}%";
    $args[]  = "%{$q}%";
    $types  .= 'ss';
}

// status (ako postoji kolona)
if ($colAktivan !== '' && $status !== '' && strtolower($status) !== 'all') {
    $where[] = "`$colAktivan` = ?";
    $args[]  = (int)$status;
    $types  .= 'i';
}

$whereSql = $where ? (' WHERE '.implode(' AND ', $where)) : '';

// helper za prepared execute
function run_stmt(mysqli $db, string $sql, string $types = '', array $args = []): mysqli_stmt {
    $st = $db->prepare($sql);
    if (!$st) fail('SQL prepare error: '.$db->error.' | SQL='.$sql);
    if ($types && $args) {
        $st->bind_param($types, ...$args);
    }
    if (!$st->execute()) fail('SQL exec error: '.$st->error.' | SQL='.$sql);
    return $st;
}

// ======= SELECT polja (AS id/naziv/oznaka za frontend) =======
$selectFields = "`$colId` AS id, `$colNaziv` AS naziv, `$colOznaka` AS oznaka";

// ======= ALL=1 → bez paginacije =======
if ($allFlag === '1' || strtolower($allFlag) === 'true') {
    $sql = "SELECT $selectFields FROM `$tableName` $whereSql ORDER BY `$colNaziv` ASC";
    $st  = run_stmt($db, $sql, $types, $args);
    $rows = $st->get_result()->fetch_all(MYSQLI_ASSOC);
    echo json_encode($rows, JSON_UNESCAPED_UNICODE);
    exit;
}

// ======= PAGINACIJA =======
// ukupno
$sqlCnt = "SELECT COUNT(*) AS cnt FROM `$tableName` $whereSql";
$stCnt  = run_stmt($db, $sqlCnt, $types, $args);
$total  = (int) ($stCnt->get_result()->fetch_assoc()['cnt'] ?? 0);

$pages = max(1, (int)ceil($total / $pageSize));
if ($page > $pages) $page = $pages;
$offset = ($page - 1) * $pageSize;

$sql = "SELECT $selectFields
        FROM `$tableName`
        $whereSql
        ORDER BY `$colNaziv` ASC
        LIMIT ? OFFSET ?";
$types2 = $types.'ii';
$args2  = array_merge($args, [$pageSize, $offset]);

$st   = run_stmt($db, $sql, $types2, $args2);
$data = $st->get_result()->fetch_all(MYSQLI_ASSOC);

echo json_encode([
    'ok'        => true,
    'total'     => $total,
    'pages'     => $pages,
    'page'      => $page,
    'page_size' => $pageSize,
    'data'      => $data
], JSON_UNESCAPED_UNICODE);
