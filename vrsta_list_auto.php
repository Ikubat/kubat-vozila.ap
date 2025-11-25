<?php
require_once __DIR__ . '/_bootstrap.php';
kubatapp_require_api('vrsta_list_auto.php');

// GET parametri:
//   table=vrsta_vozila (default)   -> ime tabele
//   all=1                          -> vrati sve bez paginacije
//   page=1&page_size=50            -> paginacija (ignoriše se ako all=1)

header('Content-Type: application/json; charset=utf-8');
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

function jdie($msg, $code=500){ http_response_code($code); echo json_encode(['ok'=>false,'error'=>$msg], JSON_UNESCAPED_UNICODE); exit; }
function jout($data){ echo json_encode($data, JSON_UNESCAPED_UNICODE); exit; }
require_once __DIR__ . '/config.php';

// ======= DB KONFIG (prilagodi po potrebi) =======
$DB_HOST='localhost';
$DB_USER='root';
$DB_PASS='';
$DB_NAME='kubatapp';

// ======= Parametri =======
$table   = isset($_GET['table']) && $_GET['table'] !== '' ? $_GET['table'] : 'vrsta_vozila';
$all     = isset($_GET['all']) ? $_GET['all'] : null;
$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = max(1, (int)($_GET['page_size'] ?? 50));

try{
  $db = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
  $db->set_charset('utf8mb4');

  // provjeri da tabela postoji
  $st = $db->prepare("SELECT COUNT(*) c FROM information_schema.TABLES WHERE TABLE_SCHEMA=? AND TABLE_NAME=?");
  $st->bind_param('ss', $DB_NAME, $table);
  $st->execute();
  $exists = (int)$st->get_result()->fetch_assoc()['c'];
  if(!$exists) jdie("Tabela '$table' ne postoji u bazi '$DB_NAME'.", 400);

  // detektuj kolone
  $cols = [];
  $rs = $db->query("SHOW COLUMNS FROM `$table`");
  while($c = $rs->fetch_assoc()){ $cols[strtolower($c['Field'])] = $c['Field']; }

  // mapiranja za id / naziv / oznaka
  $colId  = $cols['id']          ?? $cols['id_vrste']      ?? $cols['vrsta_id']  ?? null;
  $colNaz = $cols['naziv']       ?? $cols['naziv_vrste']   ?? $cols['name']      ?? null;
  $colOzn = $cols['oznaka']      ?? $cols['oznaka_vrste']  ?? $cols['code']      ?? null;

  if(!$colId){  jdie("Nisam našao ID kolonu u '$table' (probaj 'id', 'id_vrste', 'vrsta_id').", 400); }
  if(!$colNaz){ jdie("Nisam našao NAZIV kolonu u '$table' (probaj 'naziv' ili 'naziv_vrste').", 400); }
  // oznaka je opcionalna

  // bazni SELECT
  $select = "`$colId` AS _id, `$colNaz` AS _naziv".($colOzn?(", `$colOzn` AS _oznaka"):"");
  $sql = "SELECT $select FROM `$table` ORDER BY `$colNaz` ASC";

  if($all){ // sve
    $out = [];
    $rs = $db->query($sql);
    while($r = $rs->fetch_assoc()){
      $out[] = ['id'=>(int)$r['_id'], 'naziv'=>$r['_naziv'], 'oznaka'=>$r['_oznaka'] ?? ''];
    }
    jout($out);
  }else{
    // paginacija
    // total
    $rsT = $db->query("SELECT COUNT(*) c FROM `$table`");
    $total = (int)$rsT->fetch_assoc()['c'];
    $pages = max(1, (int)ceil($total / $perPage));
    $offset = ($page-1)*$perPage;

    $rs = $db->query($sql . " LIMIT $perPage OFFSET $offset");
    $rows = [];
    while($r = $rs->fetch_assoc()){
      $rows[] = ['id'=>(int)$r['_id'], 'naziv'=>$r['_naziv'], 'oznaka'=>$r['_oznaka'] ?? ''];
    }
    jout(['data'=>$rows, 'total'=>$total, 'pages'=>$pages, 'page'=>$page, 'page_size'=>$perPage]);
  }

}catch(mysqli_sql_exception $e){
  jdie("DB greška: ".$e->getMessage(), 500);
}catch(Exception $e){
  jdie("Neočekivana greška: ".$e->getMessage(), 500);
}
