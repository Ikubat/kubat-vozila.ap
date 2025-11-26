<?php
// Wrapper za /api/ putanju – prosljeđuje na glavni uplatnica_list.php
require_once __DIR__ . '/../uplatnica_list.php';

// uplatnica_list.php — lista uplatnica sa paginacijom i jednostavnim filterom

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

function jdie($m, $c = 400) {
    http_response_code($c);
    echo json_encode(['ok' => false, 'error' => $m], JSON_UNESCAPED_UNICODE);
    exit;
}
function jout($d) {
    echo json_encode($d, JSON_UNESCAPED_UNICODE);
    exit;
}

// ---- ulazni parametri (q + paginacija) ----
$q     = isset($_GET['q']) ? trim((string)$_GET['q']) : '';
$page  = max(1, (int)($_GET['page'] ?? 1));
$pp    = max(1, (int)($_GET['page_size'] ?? 50));
$off   = ($page - 1) * $pp;

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

    // mapiranje kolona (uzimaš šta god postoji)
    $colId           = $cols['id']             ?? null;
    $colUplatilac    = $cols['uplatilac']      ?? null;
    $colAdresa       = $cols['adresa']        ?? null;
    $colTelefon      = $cols['telefon']       ?? null;
    $colSvrha        = $cols['svrha']         ?? null;
    $colSvrha1       = $cols['svrha1']        ?? null; // ako si je dodao
    $colPrimatelj    = $cols['primatelj']     ?? null;
    $colMjesto       = $cols['mjesto']        ?? null;
    $colRacunPos     = $cols['racun_platioca']   ?? null;
    $colRacunPrim    = $cols['racun_primaoca']   ?? null;
    $colIznos        = $cols['iznos']         ?? null;
    $colValuta       = $cols['valuta']        ?? null;
    $colDatum        = $cols['datum']         ?? null;
    $colPoziv        = $cols['poziv_na_broj'] ?? null;
    $colPorezni      = $cols['porezni_broj']  ?? null; // ako si dodao
    $colVrstaPrihoda = $cols['vrsta_prihoda'] ?? null;
    $colOpcina       = $cols['opcina']        ?? null;
    $colBudzet       = $cols['budzetska']     ?? null;

    if (!$colId) {
        jdie("Tablica `$T_UPLATNICE` nema ID kolonu.", 500);
    }

    // ---- SELECT lista (aliasi koje frontend može koristiti) ----
    $sel = [];
    $sel[] = "u.`$colId` AS id";

    $sel[] = $colUplatilac
        ? "u.`$colUplatilac` AS uplatilac"
        : "'' AS uplatilac";

    $sel[] = $colAdresa
        ? "u.`$colAdresa` AS adresa"
        : "'' AS adresa";

    $sel[] = $colTelefon
        ? "u.`$colTelefon` AS telefon"
        : "'' AS telefon";

    $sel[] = $colSvrha
        ? "u.`$colSvrha` AS svrha"
        : "'' AS svrha";

    $sel[] = $colSvrha1
        ? "u.`$colSvrha1` AS svrha1"
        : "'' AS svrha1";

    $sel[] = $colPrimatelj
        ? "u.`$colPrimatelj` AS primatelj"
        : "'' AS primatelj";

    $sel[] = $colMjesto
        ? "u.`$colMjesto` AS mjesto"
        : "'' AS mjesto";

    $sel[] = $colRacunPos
        ? "u.`$colRacunPos` AS racun_platioca"
        : "'' AS racun_platioca";

    $sel[] = $colRacunPrim
        ? "u.`$colRacunPrim` AS racun_primaoca"
        : "'' AS racun_primaoca";

    $sel[] = $colIznos
        ? "u.`$colIznos` AS iznos"
        : "0 AS iznos";

    $sel[] = $colValuta
        ? "u.`$colValuta` AS valuta"
        : "'KM' AS valuta";

    $sel[] = $colDatum
        ? "u.`$colDatum` AS datum"
        : "NULL AS datum";

    $sel[] = $colPoziv
        ? "u.`$colPoziv` AS poziv_na_broj"
        : "'' AS poziv_na_broj";

    $sel[] = $colPorezni
        ? "u.`$colPorezni` AS porezni_broj"
        : "'' AS porezni_broj";

    $sel[] = $colVrstaPrihoda
        ? "u.`$colVrstaPrihoda` AS vrsta_prihoda"
        : "'' AS vrsta_prihoda";

    $sel[] = $colOpcina
        ? "u.`$colOpcina` AS opcina"
        : "'' AS opcina";

    $sel[] = $colBudzet
        ? "u.`$colBudzet` AS budzetska"
        : "'' AS budzetska";

    $select = implode(",\n       ", $sel);

    // ---- WHERE dio (jednostavan tekstualni filter) ----
    $whereParts = [];
    $params = [];
    $types  = '';

    if ($q !== '') {
        $likeParts = [];

        $addLike = function ($col) use (&$likeParts, &$params, &$types, $q) {
            if ($col) {
                $likeParts[] = "u.`$col` LIKE CONCAT('%',?,'%')";
                $params[] = $q;
                $types .= 's';
            }
        };

        $addLike($colUplatilac);
        $addLike($colSvrha);
        $addLike($colSvrha1);
        $addLike($colPrimatelj);
        $addLike($colMjesto);
        $addLike($colPoziv);

        if ($likeParts) {
            $whereParts[] = '(' . implode(' OR ', $likeParts) . ')';
        }
    }

    $where = $whereParts ? implode(' AND ', $whereParts) : '1=1';

    // ---- COUNT ----
    if ($where !== '1=1') {
        $sqlCount = "SELECT COUNT(*) AS c FROM `$T_UPLATNICE` u WHERE $where";
        $st = $db->prepare($sqlCount);
        if (!empty($params)) {
            $st->bind_param($types, ...$params);
        }
        $st->execute();
        $total = (int)$st->get_result()->fetch_assoc()['c'];
    } else {
        $r = $db->query("SELECT COUNT(*) AS c FROM `$T_UPLATNICE`");
        $total = (int)$r->fetch_assoc()['c'];
    }

    $pages = max(1, (int)ceil($total / $pp));

    // ---- DATA ----
    $sqlData = "SELECT
       $select
       FROM `$T_UPLATNICE` u
       WHERE $where
       ORDER BY u.`$colId` DESC
       LIMIT $pp OFFSET $off";

    if ($where !== '1=1') {
        $st = $db->prepare($sqlData);
        if (!empty($params)) {
            $st->bind_param($types, ...$params);
        }
        $st->execute();
        $rs = $st->get_result();
    } else {
        $rs = $db->query($sqlData);
    }

    $rows = [];
    while ($r = $rs->fetch_assoc()) {
        $rows[] = [
            'id'            => (int)$r['id'],
            'uplatilac'     => $r['uplatilac'],
            'adresa'        => $r['adresa'],
            'telefon'       => $r['telefon'],
            'svrha'         => $r['svrha'],
            'svrha1'        => $r['svrha1'],
            'primatelj'     => $r['primatelj'],
            'mjesto'        => $r['mjesto'],
            'racun_platioca'=> $r['racun_platioca'],
            'racun_primaoca'=> $r['racun_primaoca'],
            'iznos'         => isset($r['iznos']) ? (float)$r['iznos'] : 0,
            'valuta'        => $r['valuta'],
            'datum'         => $r['datum'],
            'poziv_na_broj' => $r['poziv_na_broj'],
            'porezni_broj'  => $r['porezni_broj'],
            'vrsta_prihoda' => $r['vrsta_prihoda'],
            'opcina'        => $r['opcina'],
            'budzetska'     => $r['budzetska'],
        ];
    }

    jout([
        'ok'        => true,
        'data'      => $rows,
        'total'     => $total,
        'pages'     => $pages,
        'page'      => $page,
        'page_size' => $pp
    ]);

} catch (mysqli_sql_exception $e) {
    jdie('DB greška: ' . $e->getMessage(), 500);
}
