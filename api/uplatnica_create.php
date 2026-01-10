<?php
// 1) Uvijek prvo bootstrap
require_once __DIR__ . '/../_bootstrap.php';

// 2) Ovdje se učitava config s $conn konekcijom
require_once __DIR__ . '/config.php';

header('Content-Type: application/json; charset=utf-8');
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// Ako u config.php nije definiran $T_UPLATNICE, koristi 'uplatnice'
$T_UPLATNICE = $T_UPLATNICE ?? 'uplatnice';

function jdie($m, $c = 400) {
    http_response_code($c);
    echo json_encode(['ok' => false, 'error' => $m], JSON_UNESCAPED_UNICODE);
    exit;
}
function jok($x = []) {
    echo json_encode(['ok' => true] + $x, JSON_UNESCAPED_UNICODE);
    exit;
}

// ---- ULAZ ----
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$ct     = $_SERVER['CONTENT_TYPE'] ?? '';

$data = [];

if ($method === 'POST' && stripos($ct, 'application/json') !== false) {
    $raw = file_get_contents('php://input');
    $in  = json_decode($raw, true);
    if (!is_array($in)) jdie('Neispravan JSON body');
    $data = $in;
} elseif ($method === 'POST') {
    $data = $_POST;
} else {
    // GET test
    $data = $_GET;
}

$uplatilac_id        = isset($data['uplatilac_id']) ? (int)$data['uplatilac_id'] : 0;
$primatelj_id        = isset($data['primatelj_id']) ? (int)$data['primatelj_id'] : 0;
$svrha_id            = isset($data['svrha_id']) && $data['svrha_id'] !== '' ? (int)$data['svrha_id'] : null;
$svrha               = trim((string)($data['svrha']  ?? ''));
$svrha1              = trim((string)($data['svrha1'] ?? ''));
$uplatilac_tekst     = trim((string)($data['uplatilac_tekst'] ?? ''));
$primatelj_tekst     = trim((string)($data['primatelj_tekst'] ?? ''));
$uplatilac_kontakt   = trim((string)($data['uplatilac_kontakt'] ?? ''));
$uplatilac_adresa    = trim((string)($data['uplatilac_adresa'] ?? ''));
$uplatilac_mjesto    = trim((string)($data['uplatilac_mjesto'] ?? ''));
$uplatilac_id_broj   = trim((string)($data['uplatilac_id_broj'] ?? ''));
$primatelj_kontakt   = trim((string)($data['primatelj_kontakt'] ?? ''));
$primatelj_adresa    = trim((string)($data['primatelj_adresa'] ?? ''));
$primatelj_mjesto    = trim((string)($data['primatelj_mjesto'] ?? ''));
$primatelj_id_broj   = trim((string)($data['primatelj_id_broj'] ?? ''));
$mjesto_uplate       = trim((string)($data['mjesto_uplate'] ?? ''));
$datum_uplate        = trim((string)($data['datum_uplate']  ?? ''));
$iznos               = isset($data['iznos']) ? (float)$data['iznos'] : 0;
$valuta              = trim((string)($data['valuta'] ?? 'KM'));
$racun_posiljaoca    = trim((string)($data['racun_posiljaoca'] ?? ''));
$racun_primatelja    = trim((string)($data['racun_primatelja'] ?? ''));
$broj_poreskog_obv   = trim((string)($data['broj_poreskog_obv'] ?? ''));
$vrsta_prihoda_sifra = trim((string)($data['vrsta_prihoda_sifra'] ?? ''));
$opcina_sifra        = trim((string)($data['opcina_sifra'] ?? ''));
$budzetska_org_sifra = trim((string)($data['budzetska_org_sifra'] ?? ''));
$poziv_na_broj       = trim((string)($data['poziv_na_broj'] ?? ''));
$napomena            = trim((string)($data['napomena'] ?? ''));
$force_duplicate     = !empty($data['force_duplicate']);

// ako nije poslano, pokušaj dohvatiti općinu prema uplatilacu
function kubatapp_fetch_partner_opcina(mysqli $db, int $partnerId): string {
    if ($partnerId <= 0) return '';

    try {
        $cols = [];
        $rs = $db->query('SHOW COLUMNS FROM partneri');
        while ($c = $rs->fetch_assoc()) {
            $cols[strtolower($c['Field'])] = $c['Field'];
        }

        $fId       = $cols['id'] ?? ($cols['id_partner'] ?? null);
        $fOpcina   = $cols['opcina_sifra'] ?? ($cols['opcina'] ?? null);
        $fMjestoId = $cols['mjesto_id'] ?? ($cols['id_mjesta'] ?? null);
        $fPorezna  = $cols['porezna_sifra'] ?? null;

        if (!$fId) return '';

        $select = ["`$fId` AS id"];
        if ($fOpcina)  $select[] = "`$fOpcina` AS opcina_sifra";
        if ($fPorezna) $select[] = "`$fPorezna` AS porezna_sifra";
        if ($fMjestoId) $select[] = "`$fMjestoId` AS mjesto_id";

        $sql = 'SELECT ' . implode(', ', $select) . " FROM partneri WHERE `$fId` = ? LIMIT 1";
        $st  = $db->prepare($sql);
        $st->bind_param('i', $partnerId);
        $st->execute();
        $row = $st->get_result()->fetch_assoc();
        if (!$row) return '';

        $candidate = ($row['porezna_sifra'] ?? '') ?: ($row['opcina_sifra'] ?? '');
        if ($candidate !== '' || !$fMjestoId || empty($row['mjesto_id'])) {
            return trim((string)$candidate);
        }

        // povuci poreznu šifru iz tablice mjesta
        $mCols = [];
        $rsM = $db->query('SHOW COLUMNS FROM mjesta');
        while ($c = $rsM->fetch_assoc()) {
            $mCols[strtolower($c['Field'])] = $c['Field'];
        }

        $fMjId     = $mCols['id'] ?? null;
        $fMjPorez  = $mCols['porezna_sifra'] ?? ($mCols['sifra'] ?? null);

        if (!$fMjId || !$fMjPorez) return '';

        $sqlMj = "SELECT `$fMjPorez` AS porezna_sifra FROM mjesta WHERE `$fMjId` = ? LIMIT 1";
        $stMj  = $db->prepare($sqlMj);
        $mjId  = (int)$row['mjesto_id'];
        $stMj->bind_param('i', $mjId);
        $stMj->execute();
        $mj = $stMj->get_result()->fetch_assoc();
        return $mj ? trim((string)($mj['porezna_sifra'] ?? '')) : '';
    } catch (Throwable $e) {
        return '';
    }
}

if ($opcina_sifra === '') {
    $opcina_sifra = kubatapp_fetch_partner_opcina($conn, $uplatilac_id);
}

// minimalne provjere
if ($uplatilac_id <= 0)     jdie('Uplatilac je obavezan.');
if ($primatelj_id <= 0)     jdie('Primatelj je obavezan.');
if ($svrha === '')          jdie('Svrha uplate je obavezna.');
if ($datum_uplate === '')   jdie('Datum je obavezan.');
if ($iznos <= 0)            jdie('Iznos mora biti veći od 0.');
if ($racun_primatelja === '') jdie('Račun primatelja je obavezan.');

try {
    // ovdje sada SIGURNO postoji $conn iz config.php
    $db = $conn;

    // učitaj strukturu tablice
    $cols = [];
    $rs = $db->query("SHOW COLUMNS FROM `$T_UPLATNICE`");
    while ($c = $rs->fetch_assoc()) {
        $cols[strtolower($c['Field'])] = $c['Field'];
    }

    if (!$cols) {
        jdie("Tablica `$T_UPLATNICE` ne postoji.", 500);
    }

    $colOrDefault = function (string $key, string $fallback) use ($cols) {
        return $cols[strtolower($key)] ?? $fallback;
    };

    $colUplatilacTxt = $cols['uplatilac_tekst'] ?? null;
    $colPrimateljTxt = $cols['primatelj_tekst'] ?? null;
    $colUplatilacKontakt = $cols['uplatilac_kontakt'] ?? null;
    $colUplatilacAdresa  = $cols['uplatilac_adresa'] ?? null;
    $colUplatilacMjesto  = $cols['uplatilac_mjesto'] ?? null;
    $colUplatilacIdBroj  = $cols['uplatilac_id_broj'] ?? null;
    $colPrimateljKontakt = $cols['primatelj_kontakt'] ?? null;
    $colPrimateljAdresa  = $cols['primatelj_adresa'] ?? null;
    $colPrimateljMjesto  = $cols['primatelj_mjesto'] ?? null;
    $colPrimateljIdBroj  = $cols['primatelj_id_broj'] ?? null;
    $colPoziv = $cols['poziv_na_broj'] ?? ($cols['poziv'] ?? null);

    $warning = '';
    if ($colPoziv && $poziv_na_broj !== '') {
        $checkSql = "SELECT id FROM `$T_UPLATNICE` WHERE `$colPoziv` = ? LIMIT 1";
        $stCheck = $db->prepare($checkSql);
        $stCheck->bind_param('s', $poziv_na_broj);
        $stCheck->execute();
        $dup = $stCheck->get_result()->fetch_assoc();
        if ($dup) {
            $warning = 'Upozorenje: poziv na broj već postoji u bazi (ID #' . $dup['id'] . ').';
        }
    }
    if ($warning !== '' && !$force_duplicate) {
        echo json_encode([
            'ok' => false,
            'warning' => $warning,
            'requires_confirm' => true
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $fields = [
        ['name' => $colOrDefault('uplatilac_id', 'uplatilac_id'),        'type' => 'i', 'value' => $uplatilac_id],
        ['name' => $colOrDefault('primatelj_id', 'primatelj_id'),        'type' => 'i', 'value' => $primatelj_id],
        ['name' => $colOrDefault('svrha_id', 'svrha_id'),                'type' => 'i', 'value' => $svrha_id],
        ['name' => $colOrDefault('svrha', 'svrha'),                      'type' => 's', 'value' => $svrha],
        ['name' => $colOrDefault('svrha1', 'svrha1'),                    'type' => 's', 'value' => $svrha1],
        ['name' => $colOrDefault('mjesto_uplate', 'mjesto_uplate'),      'type' => 's', 'value' => $mjesto_uplate],
        ['name' => $colOrDefault('datum_uplate', 'datum_uplate'),        'type' => 's', 'value' => $datum_uplate],
        ['name' => $colOrDefault('iznos', 'iznos'),                      'type' => 'd', 'value' => $iznos],
        ['name' => $colOrDefault('valuta', 'valuta'),                    'type' => 's', 'value' => $valuta],
        ['name' => $colOrDefault('racun_posiljaoca', 'racun_posiljaoca'),'type' => 's', 'value' => $racun_posiljaoca],
        ['name' => $colOrDefault('racun_primatelja', 'racun_primatelja'),'type' => 's', 'value' => $racun_primatelja],
        ['name' => $colOrDefault('broj_poreskog_obv', 'broj_poreskog_obv'),'type' => 's','value' => $broj_poreskog_obv],
        ['name' => $colOrDefault('vrsta_prihoda_sifra', 'vrsta_prihoda_sifra'),'type' => 's','value' => $vrsta_prihoda_sifra],
        ['name' => $colOrDefault('opcina_sifra', 'opcina_sifra'),        'type' => 's', 'value' => $opcina_sifra],
        ['name' => $colOrDefault('budzetska_org_sifra', 'budzetska_org_sifra'),'type' => 's','value' => $budzetska_org_sifra],
        ['name' => $colOrDefault('poziv_na_broj', 'poziv_na_broj'),      'type' => 's', 'value' => $poziv_na_broj],
        ['name' => $colOrDefault('napomena', 'napomena'),                'type' => 's', 'value' => $napomena],
    ];

    if ($colUplatilacTxt) {
        $fields[] = ['name' => $colUplatilacTxt, 'type' => 's', 'value' => $uplatilac_tekst];
    }
    if ($colPrimateljTxt) {
        $fields[] = ['name' => $colPrimateljTxt, 'type' => 's', 'value' => $primatelj_tekst];
    }
    if ($colUplatilacKontakt) {
        $fields[] = ['name' => $colUplatilacKontakt, 'type' => 's', 'value' => $uplatilac_kontakt];
    }
    if ($colUplatilacAdresa) {
        $fields[] = ['name' => $colUplatilacAdresa, 'type' => 's', 'value' => $uplatilac_adresa];
    }
    if ($colUplatilacMjesto) {
        $fields[] = ['name' => $colUplatilacMjesto, 'type' => 's', 'value' => $uplatilac_mjesto];
    }
    if ($colUplatilacIdBroj) {
        $fields[] = ['name' => $colUplatilacIdBroj, 'type' => 's', 'value' => $uplatilac_id_broj];
    }
    if ($colPrimateljKontakt) {
        $fields[] = ['name' => $colPrimateljKontakt, 'type' => 's', 'value' => $primatelj_kontakt];
    }
    if ($colPrimateljAdresa) {
        $fields[] = ['name' => $colPrimateljAdresa, 'type' => 's', 'value' => $primatelj_adresa];
    }
    if ($colPrimateljMjesto) {
        $fields[] = ['name' => $colPrimateljMjesto, 'type' => 's', 'value' => $primatelj_mjesto];
    }
    if ($colPrimateljIdBroj) {
        $fields[] = ['name' => $colPrimateljIdBroj, 'type' => 's', 'value' => $primatelj_id_broj];
    }

    $colNames     = array_map(fn($f) => '`' . $f['name'] . '`', $fields);
    $placeholders = array_fill(0, count($fields), '?');
    $types        = implode('', array_column($fields, 'type'));
    $values       = array_column($fields, 'value');

    $sql = "INSERT INTO `$T_UPLATNICE`
            (" . implode(',', $colNames) . ")
            VALUES (" . implode(',', $placeholders) . ")";

    $st = $db->prepare($sql);
    $st->bind_param($types, ...$values);
    $st->execute();

    $newId = (int)$db->insert_id;
    $payload = ['id' => $newId];
    if ($warning !== '') {
        $payload['warning'] = $warning;
    }
    jok($payload);

} catch (mysqli_sql_exception $e) {
    jdie('DB greška: ' . $e->getMessage(), 500);
}
