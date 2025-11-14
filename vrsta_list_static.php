<?php
// vrsta_list.php — JSON API (mysqli) | all=1, paginacija, q (pretraga), status (opcija)
header('Content-Type: application/json; charset=utf-8');

// ======= DEBUG (možeš ugasiti u produkciji) =======
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// ======= KONFIGURACIJA BAZE (XAMPP default) =======
$DB_HOST = 'localhost';
$DB_USER = 'root';   // XAMPP default
$DB_PASS = '';       // XAMPP default (prazno)
$DB_NAME = 'kubatapp'; // 🔁 PROMIJENI ako ti je druga baza

// ======= MAPIRANJE TABLICE/KOLONA (po potrebi prilagodi) =======
const TABLE_NAME   = 'vrsta';  // npr. 'sifr_vrsta'
const COL_ID       = 'id';     // npr. 'id_vrste'
const COL_NAZIV    = 'naziv';  // npr. 'naziv_vrste'
const COL_OZNAKA   = 'oznaka'; // npr. 'oznaka_vrste'
// Ako NEMAŠ kolonu za aktivnost, postavi na '' (prazno)
const COL_AKTIVAN  = '';       // npr. 'aktivan' ili '' ako ne postoji

// ======= POMOĆNE =======
function fail($msg, $code=500){
  http_response_code($code);
  echo json_encode(['ok'=>false, 'error'=>$msg], JSON_UNESCAPED_UNICODE);
  exit;
}

// ======= KONEKCIJA =======
$mysqli = @new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
if ($mysqli->connect_errno) fail('DB connect error: '.$mysqli->connect_error);
$mysqli->set_charset('utf8mb4');

// ======= PARAMETRI =======
$src      = $_REQUEST;
$q        = isset($src['q']) ? trim((string)$src['q']) : '';
$status   = isset($src['status']) ? (string)$src['status'] : ''; // '1','0','all' (koristi se samo ako COL_AKTIVAN != '')
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
  $where[] = "(".COL_NAZIV." LIKE ? OR ".COL_OZNAKA." LIKE ?)";
  $args[]  = "%{$q}%";
  $args[]  = "%{$q}%";
  $types  .= 'ss';
}

// status (ako postoji kolona)
if (COL_AKTIVAN !== '' && $status !== '' && strtolower($status) !== 'all') {
  $where[] = COL_AKTIVAN." = ?";
  $args[]  = (int)$status;
  $types  .= 'i';
}

$whereSql = $where ? (' WHERE '.implode(' AND ', $where)) : '';

// helper za prepared execute
function run_stmt($mysqli, $sql, $types='', $args=[]){
  $st = $mysqli->prepare($sql);
  if (!$st) fail('SQL prepare error: '.$mysqli->error.' | SQL='.$sql);
  if ($types && $args) {
    $st->bind_param($types, ...$args);
  }
  if (!$st->execute()) fail('SQL exec error: '.$st->error.' | SQL='.$sql);
  return $st;
}

// ======= SELECT polja (AS id/naziv/oznaka za frontend) =======
$selectFields = COL_ID." AS id, ".COL_NAZIV." AS naziv, ".COL_OZNAKA." AS oznaka";

// ======= ALL=1 → bez paginacije =======
if ($allFlag === '1' || strtolower($allFlag) === 'true') {
  $sql = "SELECT $selectFields FROM ".TABLE_NAME." $whereSql ORDER BY ".COL_NAZIV." ASC";
  $st  = run_stmt($mysqli, $sql, $types, $args);
  $rows = $st->get_result()->fetch_all(MYSQLI_ASSOC);
  echo json_encode($rows, JSON_UNESCAPED_UNICODE);
  exit;
}

// ======= PAGINACIJA =======
// ukupno
$sqlCnt = "SELECT COUNT(*) AS cnt FROM ".TABLE_NAME." $whereSql";
$stCnt  = run_stmt($mysqli, $sqlCnt, $types, $args);
$total  = (int) ($stCnt->get_result()->fetch_assoc()['cnt'] ?? 0);

$pages = max(1, (int)ceil($total / $pageSize));
if ($page > $pages) $page = $pages;
$offset = ($page - 1) * $pageSize;

$sql = "SELECT $selectFields
        FROM ".TABLE_NAME."
        $whereSql
        ORDER BY ".COL_NAZIV." ASC
        LIMIT ? OFFSET ?";
$types2 = $types.'ii';
$args2  = array_merge($args, [$pageSize, $offset]);

$st   = run_stmt($mysqli, $sql, $types2, $args2);
$data = $st->get_result()->fetch_all(MYSQLI_ASSOC);

echo json_encode([
  'ok'        => true,
  'total'     => $total,
  'pages'     => $pages,
  'page'      => $page,
  'page_size' => $pageSize,
  'data'      => $data
], JSON_UNESCAPED_UNICODE);

