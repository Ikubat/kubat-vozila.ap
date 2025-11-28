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

kubatapp_require_api('marka_search.php');

// Lista marki iz tablice marka_vozila + vrsta_vozila, bez pretpostavke da postoji "model" kolona.

header('Content-Type: application/json; charset=utf-8');
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

require_once __DIR__ . '/config.php';

// Fallback nazivi tablica ako nisu definirani u okruženju
$T_MARKA = $T_MARKA ?? 'marka_vozila';
$T_VRSTA = $T_VRSTA ?? 'vrsta_vozila';

function jdie($m, $c = 500) {
    http_response_code($c);
    echo json_encode(['ok' => false, 'error' => $m], JSON_UNESCAPED_UNICODE);
    exit;
}
function jout($d) {
    echo json_encode($d, JSON_UNESCAPED_UNICODE);
    exit;
}

// ulaz
$q           = isset($_GET['q']) ? trim((string)$_GET['q']) : '';
$page        = max(1, (int)($_GET['page'] ?? 1));
$pp          = max(1, (int)($_GET['page_size'] ?? 50));
$off         = ($page - 1) * $pp;
$fNaziv      = isset($_GET['naziv']) ? trim((string)$_GET['naziv']) : '';
$fModel      = isset($_GET['model']) ? trim((string)$_GET['model']) : '';
$fSerija     = isset($_GET['serija']) ? trim((string)$_GET['serija']) : '';
$fVrsta      = isset($_GET['vrsta']) ? trim((string)$_GET['vrsta']) : '';
$fOblik      = isset($_GET['oblik']) ? trim((string)$_GET['oblik']) : '';
$fPogon      = isset($_GET['pogon']) ? trim((string)$_GET['pogon']) : '';
$fMjenjac    = isset($_GET['mjenjac']) ? trim((string)$_GET['mjenjac']) : '';
$fVrata      = (isset($_GET['vrata']) && $_GET['vrata'] !== '') ? (int)$_GET['vrata'] : null;
$fSnaga      = (isset($_GET['snaga']) && $_GET['snaga'] !== '') ? (float)$_GET['snaga'] : null;
$fZapremina  = (isset($_GET['zapremina']) && $_GET['zapremina'] !== '') ? (float)$_GET['zapremina'] : null;
$fGodModela  = (isset($_GET['god_modela']) && $_GET['god_modela'] !== '') ? (int)$_GET['god_modela'] : null;
$fGodKraj    = (isset($_GET['god_kraj']) && $_GET['god_kraj'] !== '') ? (int)$_GET['god_kraj'] : null;
$fKataloska  = (isset($_GET['kataloska']) && $_GET['kataloska'] !== '') ? (float)$_GET['kataloska'] : null;

try {
    $db = $conn;

    // --- detektuj kolone u marka_vozila ---
    $cols = [];
    $rs = $db->query("SHOW COLUMNS FROM `$T_MARKA`");
    while ($c = $rs->fetch_assoc()) {
        $cols[strtolower($c['Field'])] = $c['Field'];
    }

    if (!$cols) {
        jdie("Tablica `$T_MARKA` ne postoji ili nema kolona.");
    }

    // mapiranje: šta god postoji koristimo
    $colId        = $cols['id']          ?? $cols['id_marka']  ?? null;
    $colNaziv     = $cols['naziv']       ?? $cols['marka']     ?? $cols['naziv_marka'] ?? null;
    $colModel     = $cols['model']       ?? $cols['tip']       ?? $cols['naziv_modela'] ?? null; // opcionalno
    $colVrsta     = $cols['vrsta_id']    ?? $cols['id_vrsta']  ?? $cols['vrsta'] ?? null;       // opcionalno
    $colSerija    = $cols['serija']      ?? null;
    $colOblik     = $cols['oblik']       ?? null;
    $colVrata     = $cols['vrata']       ?? null;
    $colMjenjac   = $cols['mjenjac']     ?? null;
    $colPogon     = $cols['pogon']       ?? null;
    $colSnaga     = $cols['snaga']       ?? null;
    $colZapremina = $cols['zapremina']   ?? null;
    $colGodModela = $cols['god_modela']  ?? $cols['godina_od'] ?? $cols['god_od'] ?? null;
    $colGodKraj   = $cols['god_kraj']    ?? $cols['godina_do'] ?? $cols['god_do'] ?? null;
    $colKataloska = $cols['kataloska']   ?? null;
    
    if (!$colId || !$colNaziv) {
        jdie("Tablica `$T_MARKA` nema očekivane kolone (id, naziv).");
    }

    // bazni SELECT s aliasima koje frontend očekuje
    $sel = [];
    $sel[] = "m.`$colId`    AS id";
    $sel[] = "m.`$colNaziv` AS naziv";
    if ($colModel) {
        $sel[] = "m.`$colModel` AS model";
    } else {
        $sel[] = "'' AS model";
    }

    if ($colVrsta) {
        $sel[] = "m.`$colVrsta` AS vrsta_id";
        $joinVrsta = "LEFT JOIN `$T_VRSTA` v ON v.id = m.`$colVrsta`";
    } else {
        $sel[] = "NULL AS vrsta_id";
        $joinVrsta = "LEFT JOIN `$T_VRSTA` v ON 1=0"; // nema veze, ali struktura ostaje ista
    }

    $sel[] = "v.naziv  AS vrsta_naz";
    $sel[] = "v.oznaka AS vrsta_oznaka";
    if ($colSerija)    $sel[] = "m.`$colSerija` AS serija";       else $sel[] = "'' AS serija";
    if ($colOblik)     $sel[] = "m.`$colOblik` AS oblik";         else $sel[] = "'' AS oblik";
    if ($colVrata)     $sel[] = "m.`$colVrata` AS vrata";         else $sel[] = "NULL AS vrata";
    if ($colMjenjac)   $sel[] = "m.`$colMjenjac` AS mjenjac";     else $sel[] = "'' AS mjenjac";
    if ($colPogon)     $sel[] = "m.`$colPogon` AS pogon";         else $sel[] = "'' AS pogon";
    if ($colSnaga)     $sel[] = "m.`$colSnaga` AS snaga";         else $sel[] = "NULL AS snaga";
    if ($colZapremina) $sel[] = "m.`$colZapremina` AS zapremina"; else $sel[] = "NULL AS zapremina";
    if ($colGodModela) $sel[] = "m.`$colGodModela` AS god_modela";else $sel[] = "NULL AS god_modela";
    if ($colGodKraj)   $sel[] = "m.`$colGodKraj` AS god_kraj";    else $sel[] = "NULL AS god_kraj";
    if ($colKataloska) $sel[] = "m.`$colKataloska` AS kataloska"; else $sel[] = "NULL AS kataloska";
    $select = implode(",\n       ", $sel);

    // --- WHERE za pretragu ---
    $whereParts = [];
    $params = [];
    $types  = '';

    $yearFilter = null;
    if ($q !== '' && preg_match('/^\d{4}$/', $q)) {
        $year = (int)$q;
        if ($year >= 1900 && $year <= 2100) {
            $yearFilter = $year;
        }
    }

    // helper: građenje uvjeta za modelske godine tako da obuhvati i mlađa godišta
    $addYearCondition = function (int $year) use (&$params, &$types, $colGodModela, $colGodKraj) {
        $yearParts = [];
        if ($colGodModela) {
            $yearParts[] = "m.`$colGodModela` >= ?";
            $params[] = $year; $types .= 'i';
        }
        if ($colGodKraj) {
            $yearParts[] = "m.`$colGodKraj` >= ?";
            $params[] = $year; $types .= 'i';
        }
        return $yearParts ? '(' . implode(' OR ', $yearParts) . ')' : null;
    };

    if ($q !== '') {
        $likeParts = [];

        // tražimo po nazivu marke
        $likeParts[] = "m.`$colNaziv` LIKE CONCAT('%',?,'%')";
        $params[] = $q; $types .= 's';

        // po modelu, ako postoji
        if ($colModel) {
            $likeParts[] = "m.`$colModel` LIKE CONCAT('%',?,'%')";
            $params[] = $q; $types .= 's';
        }

        // po nazivu vrste
        $likeParts[] = "v.naziv LIKE CONCAT('%',?,'%')";
        $params[] = $q; $types .= 's';

        // po oznaci vrste
        $likeParts[] = "IFNULL(v.oznaka,'') LIKE CONCAT('%',?,'%')";
        $params[] = $q; $types .= 's';

        // po modelskoj godini (godište >= tražene vrijednosti) uzimajući u obzir i kraj proizvodnje
        if ($yearFilter !== null) {
            $yearCond = $addYearCondition($yearFilter);
            if ($yearCond) {
                $likeParts[] = $yearCond;
            }
        }

        $whereParts[] = '(' . implode(' OR ', $likeParts) . ')';
    }

    if ($fNaziv !== '') {
        $whereParts[] = "m.`$colNaziv` LIKE CONCAT('%',?,'%')";
        $params[] = $fNaziv; $types .= 's';
    }
    if ($colModel && $fModel !== '') {
        $whereParts[] = "m.`$colModel` LIKE CONCAT('%',?,'%')";
        $params[] = $fModel; $types .= 's';
    }
    if ($colSerija && $fSerija !== '') {
        $whereParts[] = "m.`$colSerija` LIKE CONCAT('%',?,'%')";
        $params[] = $fSerija; $types .= 's';
    }
    if ($fVrsta !== '') {
        $whereParts[] = "(v.naziv LIKE CONCAT('%',?,'%') OR IFNULL(v.oznaka,'') LIKE CONCAT('%',?,'%'))";
        $params[] = $fVrsta; $types .= 's';
        $params[] = $fVrsta; $types .= 's';
    }
    if ($colOblik && $fOblik !== '') {
        $whereParts[] = "m.`$colOblik` LIKE CONCAT('%',?,'%')";
        $params[] = $fOblik; $types .= 's';
    }
    if ($colPogon && $fPogon !== '') {
        $whereParts[] = "m.`$colPogon` LIKE CONCAT('%',?,'%')";
        $params[] = $fPogon; $types .= 's';
    }
    if ($colMjenjac && $fMjenjac !== '') {
        $whereParts[] = "m.`$colMjenjac` LIKE CONCAT('%',?,'%')";
        $params[] = $fMjenjac; $types .= 's';
    }
    if ($colVrata && $fVrata !== null) {
        $whereParts[] = "m.`$colVrata` = ?";
        $params[] = $fVrata; $types .= 'i';
    }
    if ($colSnaga && $fSnaga !== null) {
        $whereParts[] = "m.`$colSnaga` = ?";
        $params[] = $fSnaga; $types .= 'd';
    }
    if ($colZapremina && $fZapremina !== null) {
        $whereParts[] = "m.`$colZapremina` = ?";
        $params[] = $fZapremina; $types .= 'd';
    }
    if ($fGodModela !== null) {
        $yearCond = $addYearCondition($fGodModela);
        if ($yearCond) {
            $whereParts[] = $yearCond;
        }
    }
    if ($colGodKraj && $fGodKraj !== null) {
        $whereParts[] = "m.`$colGodKraj` = ?";
        $params[] = $fGodKraj; $types .= 'i';
    }
    if ($colKataloska && $fKataloska !== null) {
        $whereParts[] = "m.`$colKataloska` = ?";
        $params[] = $fKataloska; $types .= 'd';
    }

    // Ako je traženje samo godine, bez teksta, a kolona postoji, primijeni filter i bez LIKE izraza
    if ($yearFilter !== null && $q === (string)$yearFilter) {
        $yearCond = $addYearCondition($yearFilter);
        if ($yearCond) {
            $whereParts[] = $yearCond;
        }
    }

    $where = $whereParts ? implode(' AND ', $whereParts) : '1=1';

    // --- total ---
    if ($params) {
        $sqlCount = "SELECT COUNT(*) c
                     FROM `$T_MARKA` m
                     $joinVrsta
                     WHERE $where";
        $st = $db->prepare($sqlCount);
        $st->bind_param($types, ...$params);
        $st->execute();
        $total = (int)$st->get_result()->fetch_assoc()['c'];
    } else {
        $r = $db->query("SELECT COUNT(*) c FROM `$T_MARKA`");
        $total = (int)$r->fetch_assoc()['c'];
    }

    $pages = max(1, (int)ceil($total / $pp));

    // --- data ---
    $sqlData = "SELECT
       $select
       FROM `$T_MARKA` m
       $joinVrsta
       WHERE $where
       ORDER BY m.`$colNaziv` ASC " . ($colModel ? ", m.`$colModel` ASC " : "") . "
       LIMIT $pp OFFSET $off";

    if ($params) {
        $st = $db->prepare($sqlData);
        $st->bind_param($types, ...$params);
        $st->execute();
        $rs = $st->get_result();
    } else {
        $rs = $db->query($sqlData);
    }

    $rows = [];
    while ($r = $rs->fetch_assoc()) {
        $rows[] = [
            'id'           => (int)$r['id'],
            'naziv'        => $r['naziv'],
            'model'        => $r['model'],              // može biti '' ako kolona ne postoji
            'vrsta_id'     => $r['vrsta_id'] !== null ? (int)$r['vrsta_id'] : null,
            'vrsta_naz'    => $r['vrsta_naz'] ?? '',
            'vrsta_oznaka' => $r['vrsta_oznaka'] ?? '',
            'serija'       => $r['serija'] ?? '',
            'oblik'        => $r['oblik'] ?? '',
            'vrata'        => isset($r['vrata']) ? (int)$r['vrata'] : null,
            'mjenjac'      => $r['mjenjac'] ?? '',
            'pogon'        => $r['pogon'] ?? '',
            'snaga'        => isset($r['snaga']) ? (float)$r['snaga'] : null,
            'zapremina'    => isset($r['zapremina']) ? (float)$r['zapremina'] : null,
            'god_modela'   => isset($r['god_modela']) ? (int)$r['god_modela'] : null,
            'god_kraj'     => isset($r['god_kraj']) ? (int)$r['god_kraj'] : null,
            'kataloska'    => isset($r['kataloska']) ? (float)$r['kataloska'] : null
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
    jdie('DB greška: '.$e->getMessage(), 500);
}