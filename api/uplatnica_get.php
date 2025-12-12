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
if (!$id) {
    jdie('Nedostaje ID uplatnice.');
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
            if (isset($cols[$key])) {
                return $cols[$key];
            }
        }
        return null;
    };

    // mapiranje kolona (uzimaš šta god postoji)
    $colId           = $col(['id']);
    $colUplatilacId  = $col(['uplatilac_id']);
    $colUplatilac    = $col(['uplatilac', 'uplatilac_naziv']);
    $colUplatilacTxt = $col(['uplatilac_tekst']);
    $colUplatilacKontakt = $col(['uplatilac_kontakt']);
    $colUplatilacAdresa  = $col(['uplatilac_adresa']);
    $colUplatilacMjesto  = $col(['uplatilac_mjesto']);
    $colUplatilacIdBroj  = $col(['uplatilac_id_broj']);
    $colAdresa       = $col(['adresa']);
    $colTelefon      = $col(['telefon']);
    $colSvrhaId      = $col(['svrha_id']);
    $colSvrha        = $col(['svrha']);
    $colSvrha1       = $col(['svrha1']);
    $colPrimateljId  = $col(['primatelj_id']);
    $colPrimatelj    = $col(['primatelj', 'primatelj_naziv']);
    $colPrimateljTxt = $col(['primatelj_tekst']);
    $colPrimateljKontakt = $col(['primatelj_kontakt']);
    $colPrimateljAdresa  = $col(['primatelj_adresa']);
    $colPrimateljMjesto  = $col(['primatelj_mjesto']);
    $colPrimateljIdBroj  = $col(['primatelj_id_broj']);
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

    $sel[] = $colUplatilacKontakt
        ? "u.`$colUplatilacKontakt` AS uplatilac_kontakt"
        : "'' AS uplatilac_kontakt";

    $sel[] = $colUplatilacAdresa
        ? "u.`$colUplatilacAdresa` AS uplatilac_adresa"
        : "'' AS uplatilac_adresa";

    $sel[] = $colUplatilacMjesto
        ? "u.`$colUplatilacMjesto` AS uplatilac_mjesto"
        : "'' AS uplatilac_mjesto";

    $sel[] = $colUplatilacIdBroj
        ? "u.`$colUplatilacIdBroj` AS uplatilac_id_broj"
        : "'' AS uplatilac_id_broj";

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

    $sel[] = $colPrimateljKontakt
        ? "u.`$colPrimateljKontakt` AS primatelj_kontakt"
        : "'' AS primatelj_kontakt";

    $sel[] = $colPrimateljAdresa
        ? "u.`$colPrimateljAdresa` AS primatelj_adresa"
        : "'' AS primatelj_adresa";

    $sel[] = $colPrimateljMjesto
        ? "u.`$colPrimateljMjesto` AS primatelj_mjesto"
        : "'' AS primatelj_mjesto";

    $sel[] = $colPrimateljIdBroj
        ? "u.`$colPrimateljIdBroj` AS primatelj_id_broj"
        : "'' AS primatelj_id_broj";

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

    $sel[] = $colPorezni
        ? "u.`$colPorezni` AS broj_poreskog_obv"
        : "'' AS broj_poreskog_obv";

    $sel[] = $colVrstaPrihoda
        ? "u.`$colVrstaPrihoda` AS vrsta_prihoda_sifra"
        : "'' AS vrsta_prihoda_sifra";

    $sel[] = $colOpcina
        ? "u.`$colOpcina` AS opcina_sifra"
        : "'' AS opcina_sifra";

    $sel[] = $colBudzet
        ? "u.`$colBudzet` AS budzetska_org_sifra"
        : "'' AS budzetska_org_sifra";

    $select = implode(",\n       ", $sel);

    $sql = "SELECT
       $select
       FROM `$T_UPLATNICE` u
       WHERE u.`$colId` = ?
       LIMIT 1";

    $st = $db->prepare($sql);
    $st->bind_param('i', $id);
    $st->execute();
    $res = $st->get_result();

    if (!$res || !$res->num_rows) {
        jdie('Uplatnica nije pronađena.', 404);
    }

    $r = $res->fetch_assoc();
    $row = [
        'id'                  => (int)$r['id'],
        'uplatilac_id'        => isset($r['uplatilac_id']) ? (int)$r['uplatilac_id'] : null,
        'uplatilac_naziv'     => $r['uplatilac_naziv'],
        'uplatilac_tekst'     => $r['uplatilac_tekst'],
        'uplatilac_kontakt'   => $r['uplatilac_kontakt'],
        'uplatilac_adresa'    => $r['uplatilac_adresa'],
        'uplatilac_mjesto'    => $r['uplatilac_mjesto'],
        'uplatilac_id_broj'   => $r['uplatilac_id_broj'],
        'adresa'              => $r['adresa'],
        'telefon'             => $r['telefon'],
        'svrha_id'            => isset($r['svrha_id']) ? (int)$r['svrha_id'] : null,
        'svrha'               => $r['svrha'],
        'svrha1'              => $r['svrha1'],
        'primatelj_id'        => isset($r['primatelj_id']) ? (int)$r['primatelj_id'] : null,
        'primatelj_naziv'     => $r['primatelj_naziv'],
        'primatelj_tekst'     => $r['primatelj_tekst'],
        'primatelj_kontakt'   => $r['primatelj_kontakt'],
        'primatelj_adresa'    => $r['primatelj_adresa'],
        'primatelj_mjesto'    => $r['primatelj_mjesto'],
        'primatelj_id_broj'   => $r['primatelj_id_broj'],
        'mjesto_uplate'       => $r['mjesto_uplate'],
        'racun_posiljaoca'    => $r['racun_posiljaoca'],
        'racun_primatelja'    => $r['racun_primatelja'],
        'iznos'               => isset($r['iznos']) ? (float)$r['iznos'] : 0,
        'valuta'              => $r['valuta'],
        'datum_uplate'        => $r['datum_uplate'],
        'poziv_na_broj'       => $r['poziv_na_broj'],
        'broj_poreskog_obv'   => $r['broj_poreskog_obv'],
        'vrsta_prihoda_sifra' => $r['vrsta_prihoda_sifra'],
        'opcina_sifra'        => $r['opcina_sifra'],
        'budzetska_org_sifra' => $r['budzetska_org_sifra'],
    ];

    jout(['ok' => true, 'data' => $row]);
} catch (mysqli_sql_exception $e) {
    jdie('DB greška: ' . $e->getMessage(), 500);
}
