<?php
require_once __DIR__ . '/_bootstrap.php';
kubatapp_require_api('partneri_update.php');

// partneri_update.php
// Ažurira postojeći zapis u `partneri`.
// Podržava:
//  - ime, prezime (ili naziv ako tablica tako radi)
//  - kontakt/telefon
//  - email
//  - adresa
//  - mjesto_id (FK) ili mjesto (tekst)
//
// Važno: ne briše mjesto ako ga frontend ne šalje.

header('Content-Type: application/json; charset=utf-8');
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

require_once __DIR__ . '/config.php';

function jdie($m, $c=400){
  http_response_code($c);
  echo json_encode(['ok'=>false,'error'=>$m], JSON_UNESCAPED_UNICODE);
  exit;
}
function jok($more=[]){
  echo json_encode(['ok'=>true]+$more, JSON_UNESCAPED_UNICODE);
  exit;
}

try {
  $db = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
  $db->set_charset('utf8mb4');

  $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
  $ct = $_SERVER['CONTENT_TYPE'] ?? '';

  if ($method !== 'POST') jdie('Koristi POST.');

  if (stripos($ct, 'application/json') !== false) {
    $raw = file_get_contents('php://input');
    $in = json_decode($raw, true);
  } else {
    $in = $_POST;
  }
  if (!is_array($in)) jdie('Neispravan input.');

  $id = (int)($in['id'] ?? 0);
  if ($id <= 0) jdie('ID je obavezan.');

  // polja iz inputa (null = nije poslano, "" = poslano prazno)
  $ime       = array_key_exists('ime', $in)       ? trim((string)$in['ime'])       : null;
  $prezime   = array_key_exists('prezime', $in)   ? trim((string)$in['prezime'])   : null;
  $kontakt   = array_key_exists('kontakt', $in)   ? trim((string)$in['kontakt'])   : null;
  $email     = array_key_exists('email', $in)     ? trim((string)$in['email'])     : null;
  $adresa    = array_key_exists('adresa', $in)    ? trim((string)$in['adresa'])    : null;

  $hasMjId   = array_key_exists('mjesto_id', $in);
  $mjestoId  = $hasMjId && $in['mjesto_id'] !== '' ? (int)$in['mjesto_id'] : null;

  $hasMjTxt  = array_key_exists('mjesto', $in);
  $mjestoTxt = $hasMjTxt ? trim((string)$in['mjesto']) : null;

  // struktura tablice
  $cols = [];
  $rs = $db->query("SHOW COLUMNS FROM `$TABLE`");
  while ($c = $rs->fetch_assoc()) {
    $cols[strtolower($c['Field'])] = $c['Field'];
  }

  $f_id        = $cols['id']        ?? $cols['id_partner'] ?? null;
  $f_ime       = $cols['ime']       ?? null;
  $f_prezime   = $cols['prezime']   ?? null;
  $f_naziv     = $cols['naziv']     ?? null;
  $f_adresa    = $cols['adresa']    ?? null;
  $f_mjesto    = $cols['mjesto']    ?? null;                 // tekstualno
  $f_mjesto_id = $cols['mjesto_id'] ?? $cols['id_mjesta'] ?? null; // FK
  $f_tel       = $cols['telefon']   ?? $cols['kontakt'] ?? $cols['tel'] ?? null;
  $f_email     = $cols['email']     ?? $cols['mail'] ?? null;

  if (!$f_id) jdie("Tablica '$TABLE' nema ID kolonu.");

  // postoji li zapis
  $chk = $db->prepare("SELECT * FROM `$TABLE` WHERE `$f_id` = ?");
  $chk->bind_param('i', $id);
  $chk->execute();
  $cur = $chk->get_result()->fetch_assoc();
  if (!$cur) jdie('Partner ne postoji.');

  $sets  = [];
  $vals  = [];
  $types = '';

  // ime/prezime
  if ($f_ime !== null && $ime !== null) {
    $sets[] = "`$f_ime` = ?";
    $vals[] = $ime;
    $types .= 's';
  }
  if ($f_prezime !== null && $prezime !== null) {
    $sets[] = "`$f_prezime` = ?";
    $vals[] = $prezime;
    $types .= 's';
  }

  // naziv - ako nema posebnih ime/prezime kolona
  if ($f_naziv && !$f_ime && !$f_prezime && ($ime !== null || $prezime !== null)) {
    $curIme     = $ime     !== null ? $ime     : ($cur[$f_ime]     ?? '');
    $curPrezime = $prezime !== null ? $prezime : ($cur[$f_prezime] ?? '');
    $naziv = trim($curIme . ' ' . $curPrezime);
    if ($naziv === '') $naziv = $cur[$f_naziv] ?? '';
    $sets[] = "`$f_naziv` = ?";
    $vals[] = $naziv;
    $types .= 's';
  }

  // kontakt / email / adresa
  if ($f_tel !== null && $kontakt !== null) {
    $sets[] = "`$f_tel` = ?";
    $vals[] = $kontakt;
    $types .= 's';
  }
  if ($f_email !== null && $email !== null) {
    $sets[] = "`$f_email` = ?";
    $vals[] = $email;
    $types .= 's';
  }
  if ($f_adresa !== null && $adresa !== null) {
    $sets[] = "`$f_adresa` = ?";
    $vals[] = $adresa;
    $types .= 's';
  }

  // ----- Mjesto logika -----
  // Ako postoji mjesto_id kolona i klient je poslao mjesto_id:
  if ($f_mjesto_id && $hasMjId) {
    if ($mjestoId && $mjestoId > 0) {
      $sets[] = "`$f_mjesto_id` = ?";
      $vals[] = $mjestoId;
      $types .= 'i';
    } else {
      // ako želiš ovdje obrisati FK, otkomentiraj:
      // $sets[] = "`$f_mjesto_id` = NULL";
    }
  }

  // Ako nema mjesto_id ili ga nije dirao, ali je poslao tekstualno mjesto:
  if ($f_mjesto && $hasMjTxt && $mjestoTxt !== '' && $mjestoTxt !== null) {
    $sets[] = "`$f_mjesto` = ?";
    $vals[] = $mjestoTxt;
    $types .= 's';
  }

  // VAŽNO:
  // - ako NIJE poslao ni mjesto_id ni mjesto -> ne diramo postojeće mjesto

  if (!$sets) jdie('Nema polja za ažuriranje.');

  $sql = "UPDATE `$TABLE` SET ".implode(', ', $sets)." WHERE `$f_id` = ?";
  $vals[] = $id;
  $types .= 'i';

  $st = $db->prepare($sql);
  $st->bind_param($types, ...$vals);
  $st->execute();

  jok();

} catch (mysqli_sql_exception $e) {
  jdie('DB greška: '.$e->getMessage(), 500);
}
