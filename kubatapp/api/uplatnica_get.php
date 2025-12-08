<?php

// uplatnica_get.php — dohvat jedne uplatnice po ID-u

require_once __DIR__ . '/config.php';

header('Content-Type: application/json; charset=utf-8');
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
ini_set('html_errors', 0);

// Pretvori sve PHP greške u JSON umjesto HTML-a
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

// Fallback naziv tablice ako nije definiran u config.php
$T_UPLATNICE = $T_UPLATNICE ?? 'uplatnice';

function jdie($m, $c = 400)
{
    http_response_code($c);
    echo json_encode(['ok' => false, 'error' => $m], JSON_UNESCAPED_UNICODE);
    exit;
}
function jout($d)
{
    echo json_encode($d, JSON_UNESCAPED_UNICODE);
    exit;
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    jdie('Nedostaje ID uplatnice.', 400);
}

try {
    $db = $conn;

    // --- detektuj kolone u tablici uplatnice ---
    $cols = [];
    $rs = $db->query("SHOW COLUMNS FROM `$T_UPLATNICE`");
    while ($c = $rs->fetch_assoc()) {
        $cols[strtolower($c['Field'])] = $c['Field'];
    }
    if (!$cols) {
        jdie("Tablica `$T_UPLATNICE` ne postoji ili nema kolona.", 500);
    }

    // helper za dohvat kolone (uzima prvu postojeću varijantu)
    $col = function (array $names) use ($cols) {
        foreach ($names as $name) {
            $key = strtolower($name);
            if (isset($cols[$key])) return $cols[$key];
        }
        return null;
    };

    // mapiranje kolona (uzimaš šta god postoji)
    $colId           = $col(['id']);
    $colUplatilacId  = $col(['uplatilac_id']);
    $colUplatilac    = $col(['uplatilac', 'uplatilac_naziv']);
    $colUplatilacTxt = $col(['uplatilac_tekst']);
    $colAdresa       = $col(['adresa']);
    $colTelefon      = $col(['telefon']);
    $colSvrhaId      = $col(['svrha_id']);
    $colSvrha        = $col(['svrha']);
    $colSvrha1       = $col(['svrha1']);
    $colPrimateljId  = $col(['primatelj_id']);
    $colPrimatelj    = $col(['primatelj', 'primatelj_naziv']);
    $colPrimateljTxt = $col(['primatelj_tekst']);
    $colMjesto       = $col(['mjesto_uplate', 'mjesto']);
    $colRacunPos     = $col(['racun_posiljaoca', 'racun_platioca']);
    $colRacunPrim    = $col(['racun_primatelja', 'racun_primaoca']);
    $colIznos        = $col(['iznos']);
    $colValuta       = $col(['valuta']);
    $colDatum        = $col(['datum_uplate', 'datum']);
    $colPoziv        = $col(['poziv_na_broj', 'poziv']);
    $colPorezni      = $col(['broj_poreskog_obv', 'porezni_broj']);
    $colVrstaPrihoda = $col(['vrsta_prihoda_sifra', 'vrsta_prihoda']);
    $colOpcina       = $col(['opcina_sifra', 'opcina']);
    $colBudzet       = $col(['budzetska_org_sifra', 'budzetska']);

    if (!$colId) {
        jdie("Tablica `$T_UPLATNICE` nema ID kolonu.", 500);
    }

    // ---- SELECT (ista imena kao lista) ----
    $sel = [];
    $sel[] = "u.`$colId` AS id";

    $sel[] = $colUplatilacId
        ? "u.`$colUplatilacId` AS uplatilac_id"
        : "NULL AS uplatilac_id";

    $sel[] = $colUplatilac
        ? "u.`$colUplatilac` AS uplatilac_naziv"
        : "'' AS uplatilac_naziv";

    $sel[] = $colUplatilacTxt
        ? "u.`$colUplatilacTxt` AS uplatilac_tekst"
        : "'' AS uplatilac_tekst";

    $sel[] = $colAdresa
        ? "u.`$colAdresa` AS adresa"
        : "'' AS adresa";

    $sel[] = $colTelefon
        ? "u.`$colTelefon` AS telefon"
        : "'' AS telefon";

    $sel[] = $colSvrha
        ? "u.`$colSvrha` AS svrha"
        : "'' AS svrha";

    $sel[] = $colSvrhaId
        ? "u.`$colSvrhaId` AS svrha_id"
        : "NULL AS svrha_id";

    $sel[] = $colSvrha1
        ? "u.`$colSvrha1` AS svrha1"
        : "'' AS svrha1";

    $sel[] = $colPrimateljId
        ? "u.`$colPrimateljId` AS primatelj_id"
        : "NULL AS primatelj_id";

    $sel[] = $colPrimatelj
        ? "u.`$colPrimatelj` AS primatelj_naziv"
        : "'' AS primatelj_naziv";

    $sel[] = $colPrimateljTxt
        ? "u.`$colPrimateljTxt` AS primatelj_tekst"
        : "'' AS primatelj_tekst";

    $sel[] = $colMjesto
        ? "u.`$colMjesto` AS mjesto_uplate"
        : "'' AS mjesto_uplate";

    $sel[] = $colRacunPos
        ? "u.`$colRacunPos` AS racun_posiljaoca"
        : "'' AS racun_posiljaoca";

    $sel[] = $colRacunPrim
        ? "u.`$colRacunPrim` AS racun_primatelja"
        : "'' AS racun_primatelja";

    $sel[] = $colIznos
        ? "u.`$colIznos` AS iznos"
        : "0 AS iznos";

    $sel[] = $colValuta
        ? "u.`$colValuta` AS valuta"
        : "'KM' AS valuta";

    $sel[] = $colDatum
        ? "u.`$colDatum` AS datum_uplate"
        : "NULL AS datum_uplate";

    $sel[] = $colPoziv
        ? "u.`$colPoziv` AS poziv_na_broj"
        : "'' AS poziv_na_broj";

    // ovdje alias usklađen s rows[] -> 'broj_poreskog_obv'
    $sel[] = $colPorezni
        ? "u.`$colPorezni` AS broj_poreskog_obv"
        : "'' AS broj_poreskog_obv";

    $sel[] = $colVrstaPrihoda
        ? "u.`$colVrstaPrihoda` AS vrsta_prihoda_sifra"
        : "'' AS vrsta_prihoda_sifra";

    // ovdje alias usklađen s rows[] -> 'opcina_sifra'
    $sel[] = $colOpcina
        ? "u.`$colOpcina` AS opcina_sifra"
        : "'' AS opcina_sifra";

    $sel[] = $colBudzet
        ? "u.`$colBudzet` AS budzetska_org_sifra"
        : "'' AS budzetska_org_sifra";

    $sql = "SELECT " . implode(",\n", $sel)
         . "\nFROM `$T_UPLATNICE` u\nWHERE u.`$colId` = ?\nLIMIT 1";

    $stmt = $db->prepare($sql);
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();

    if (!$row) {
        jdie('Uplatnica nije pronađena.', 404);
    }

    jout($row);

} catch (Throwable $e) {
    jdie('Greška: ' . $e->getMessage(), 500);
}
