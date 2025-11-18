<?php
// Prima JSON iz obracun.js i upisuje zapis u tablicu obračuna.
//
// Očekuje minimalno:
//  - datum (YYYY-MM-DD)
//  - partner_id
// Ostala polja (vozilo_id, opis, fakt_eur, ... , ukupno) se spremaju
// SAMO ako odgovarajuće kolone postoje u tablici.
//
// Radi s tablicom obracun_vozila ili obracun (uzima prvu koja postoji).

header('Content-Type: application/json; charset=utf-8');
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$DB_HOST = 'localhost';
$DB_USER = 'root';
$DB_PASS = '';
$DB_NAME = 'kubatapp';

$TABLE_CANDIDATES = ['obracun_vozila', 'obracun'];

function jdie(string $msg, int $code = 200): void {
    http_response_code($code);
    echo json_encode(['ok' => false, 'error' => $msg], JSON_UNESCAPED_UNICODE);
    exit;
}
function jok(array $extra = []): void {
    echo json_encode(['ok' => true] + $extra, JSON_UNESCAPED_UNICODE);
    exit;
}

// --- provjera metode ---
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    jdie('Koristi POST.');
}

// --- čitanje JSON body ---
$raw = file_get_contents('php://input');
$in = json_decode($raw, true);
if (!is_array($in)) {
    jdie('Neispravan JSON body.');
}

// --- osnovna polja iz JS-a ---
$datum      = trim((string)($in['datum'] ?? ''));
$partner_id = (int)($in['partner_id'] ?? 0);

if ($datum === '') {
    jdie('Datum je obavezan.');
}
if ($partner_id <= 0) {
    jdie('Partner nije odabran.');
}

// ostala polja (mogu ali ne moraju postojati u tablici)
$fieldsInput = [
    'vozilo_id'          => $in['vozilo_id']          ?? null,
    'opis'               => $in['opis']               ?? '',
    'fakt_eur'           => $in['fakt_eur']           ?? 0,
    'fakt_km'            => $in['fakt_km']            ?? 0,
    'zut_trans_ino'      => $in['zut_trans_ino']      ?? 0,
    'zut_ost_tr'         => $in['zut_ost_tr']         ?? 0,
    'zut_u_zemlji'       => $in['zut_u_zemlji']       ?? 0,
    'ulaz_pdv'           => $in['ulaz_pdv']           ?? 0,
    'spedicija'          => $in['spedicija']          ?? 0,
    'granica'            => $in['granica']            ?? 0,
    'ino_usluga'         => $in['ino_usluga']         ?? 0,
    'transport'          => $in['transport']          ?? 0,
    'ostalo'             => $in['ostalo']             ?? 0,
    'terminal'           => $in['terminal']           ?? 0,
    'kvc'                => $in['kvc']                ?? 0,
    'usluga'             => $in['usluga']             ?? 0,
    'ukupno'             => $in['ukupno']             ?? 0,
];

// tipovi za bind_param po polju
$fieldTypes = [
    'vozilo_id'          => 'i',
    'opis'               => 's',
    'fakt_eur'           => 'd',
    'fakt_km'            => 'd',
    'zut_trans_ino'      => 'd',
    'zut_ost_tr'         => 'd',
    'zut_u_zemlji'       => 'd',
    'ulaz_pdv'           => 'd',
    'spedicija'          => 'd',
    'granica'            => 'd',
    'ino_usluga'         => 'd',
    'transport'          => 'd',
    'ostalo'             => 'd',
    'terminal'           => 'd',
    'kvc'                => 'd',
    'usluga'             => 'd',
    'ukupno'             => 'd',
];

try {
    // --- konekcija ---
    $db = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
    $db->set_charset('utf8mb4');

    // --- pronađi tablicu ---
    $table = null;
    foreach ($TABLE_CANDIDATES as $t) {
        $res = $db->query("SHOW TABLES LIKE '".$db->real_escape_string($t)."'");
        if ($res && $res->num_rows > 0) {
            $table = $t;
            break;
        }
    }
    if (!$table) {
        jdie("Nije pronađena tablica za obračune (obracun_vozila ili obracun).");
    }

    // --- pročitaj strukturu tablice ---
    $cols = [];
    $rs = $db->query("SHOW COLUMNS FROM `$table`");
    while ($c = $rs->fetch_assoc()) {
        $cols[strtolower($c['Field'])] = $c['Field'];
    }

    // moraju postojati kolone za datum i partner_id
    $colDatum = $cols['datum']      ?? $cols['datum_obracuna'] ?? null;
    $colPart  = $cols['partner_id'] ?? $cols['id_partner']     ?? null;

    if (!$colDatum || !$colPart) {
        jdie("Tablica `$table` mora imati kolone za datum i partner_id.");
    }

    // --- priprema inserta ---
    $fields = [];
    $marks  = [];
    $types  = '';
    $vals   = [];

    // obavezna polja
    $fields[] = $colDatum;
    $marks[]  = '?';
    $types   .= 's';
    $vals[]   = $datum;

    $fields[] = $colPart;
    $marks[]  = '?';
    $types   .= 'i';
    $vals[]   = $partner_id;

    // opcionalna polja: koristimo SAMO ako kolona postoji u tablici
    foreach ($fieldsInput as $name => $value) {
        $lname = strtolower($name);
        if (isset($cols[$lname])) {
            $col = $cols[$lname];
            $t   = $fieldTypes[$name] ?? 's';

            // null handling
            if ($value === '' || $value === null) {
                // NULL ide bez placeholdere? Ne, zbog prepared statementa idemo s placeholderom i NULL vrijednošću.
                $fields[] = $col;
                $marks[]  = '?';
                $types   .= $t;
                $vals[]   = null;
            } else {
                $fields[] = $col;
                $marks[]  = '?';
                $types   .= $t;
                if ($t === 'i') {
                    $vals[] = (int)$value;
                } elseif ($t === 'd') {
                    $vals[] = (float)$value;
                } else {
                    $vals[] = (string)$value;
                }
            }
        }
    }

    if (!$fields) {
        jdie('Nema polja za spremanje.');
    }

    $sql = "INSERT INTO `$table` (`".implode('`,`',$fields)."`) VALUES (".implode(',',$marks).")";
    $st = $db->prepare($sql);
    $st->bind_param($types, ...$vals);
    $st->execute();

    $newId = $db->insert_id ?: null;
    jok(['id' => $newId, 'table' => $table]);

} catch (mysqli_sql_exception $e) {
    jdie('DB greška: '.$e->getMessage(), 500);
}