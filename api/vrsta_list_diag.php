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

kubatapp_require_api('vrsta_list_diag.php');

// vrsta_list_diag.php — detaljna dijagnostika za vrsta_list␊
header('Content-Type: application/json; charset=utf-8');

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
require_once __DIR__ . '/config.php';

// ✏️ PRILAGODI OVO ↓↓↓ (XAMPP: root / prazna lozinka)␊
$DB_HOST = 'localhost';
$DB_USER = 'root';
$DB_PASS = '';
$DB_NAME = 'kubatapp';

// ✏️ Ako znaš točnu tablicu/kolone, upiši ovdje:␊
$TABLE   = 'vrsta';          // npr. 'sifr_vrsta'␊
$COL_ID  = 'id';             // npr. 'id_vrste'␊
$COL_NAZ = 'naziv';          // npr. 'naziv_vrste'␊
$COL_OZN = 'oznaka';         // npr. 'oznaka_vrste'␊

$out = [
  'db' => ['host'=>$DB_HOST, 'user'=>$DB_USER, 'name'=>$DB_NAME],
  'connect_ok' => null,
  'errors' => [],
  'checks' => []
];

try {
  $mysqli = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
  $mysqli->set_charset('utf8mb4');
  $out['connect_ok'] = true;

  // 1) Popis tablica koje liče na "vrsta"␊
  $res = $mysqli->query("SHOW TABLES");
  $tables = [];
  while ($r = $res->fetch_array(MYSQLI_NUM)) { $tables[] = $r[0]; }
  $out['checks']['tables_like_vrsta'] = array_values(array_filter($tables, fn($t)=> stripos($t,'vrst')!==false));

  // 2) Postoji li $TABLE␊
  $res = $mysqli->query("SHOW TABLES LIKE '".$mysqli->real_escape_string($TABLE)."'");
  $out['checks']['table_exists'] = (bool)$res->num_rows;

  // 3) Opis kolona tablice (ako postoji)␊
  if ($out['checks']['table_exists']) {
    $desc = $mysqli->query("DESCRIBE `{$TABLE}`")->fetch_all(MYSQLI_ASSOC);
    $out['checks']['describe'] = $desc;

    // 4) Pokušaj SELECT s mapiranjem kolona koje si gore naveo␊
    $sql = "SELECT `$COL_ID` AS id, `$COL_NAZ` AS naziv, `$COL_OZN` AS oznaka FROM `{$TABLE}` ORDER BY `$COL_NAZ` ASC LIMIT 10";
    $out['checks']['sample_sql'] = $sql;
    $sample = $mysqli->query($sql)->fetch_all(MYSQLI_ASSOC);
    $out['checks']['sample_rows'] = $sample;

    // 5) COUNT␊
    $cnt = $mysqli->query("SELECT COUNT(*) AS cnt FROM `{$TABLE}`")->fetch_assoc();
    $out['checks']['row_count'] = (int)$cnt['cnt'];
  } else {
    $out['errors'][] = "Tablica `{$TABLE}` ne postoji. Nađene slične: ".implode(', ', $out['checks']['tables_like_vrsta']);
  }

} catch (Throwable $e) {
  $out['connect_ok'] = false;
  $out['errors'][] = $e->getMessage();
}

echo json_encode($out, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE);
