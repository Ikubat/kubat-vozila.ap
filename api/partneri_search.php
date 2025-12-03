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

kubatapp_require_api('partneri_search.php');

header('Content-Type: application/json; charset=utf-8');
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
require_once __DIR__ . '/config.php';

$q        = isset($_GET['q']) ? trim($_GET['q']) : '';
$page     = max(1, (int)($_GET['page'] ?? 1));
$pageSize = min(100, max(1, (int)($_GET['page_size'] ?? 20)));
$offset   = ($page - 1) * $pageSize;

$where = '';
$whereParts = [];
$args = [];
$types = '';

// Struktura tablice kako bismo koristili dostupne kolone (stara ili nova imena)
$cols = [];
$rs = $conn->query('SHOW COLUMNS FROM partneri');
while ($c = $rs->fetch_assoc()) {
    $cols[strtolower($c['Field'])] = $c['Field'];
}

$fVrsta  = $cols['vrsta_partnera'] ?? $cols['vrsta'] ?? null;
$fIdBroj = $cols['id_broj'] ?? $cols['idbroj'] ?? $cols['id_broj_partnera'] ?? null;
$fBrojR  = $cols['broj_racuna'] ?? $cols['brojracuna'] ?? null;

if ($q !== '') {
    $like = '%' . $q . '%';
    $whereParts = [
        'p.ime LIKE ?',
        'p.prezime LIKE ?'
    ];

    if ($fVrsta)  $whereParts[] = "p.`$fVrsta` LIKE ?";
    if ($fIdBroj) $whereParts[] = "p.`$fIdBroj` LIKE ?";
    if ($fBrojR)  $whereParts[] = "p.`$fBrojR` LIKE ?";

    $whereParts = array_merge($whereParts, [
        'p.kontakt LIKE ?',
        'p.email LIKE ?',
        'p.adresa LIKE ?',
        'm.naziv_mjesta LIKE ?'
    ]);
    $where = 'WHERE ' . implode(' OR ', $whereParts);
    $args = array_fill(0, count($whereParts), $like);
    $types = str_repeat('s', count($whereParts));
}

// 1) Ukupan broj
$sqlTotal = "
    SELECT COUNT(*)
    FROM partneri p
    LEFT JOIN mjesta m ON m.id = p.mjesto_id
    $where
";
$stTot = $conn->prepare($sqlTotal);
if ($args) {
    $stTot->bind_param($types, ...$args);
}
$stTot->execute();
$stTot->bind_result($total);
$stTot->fetch();
$stTot->close();

// 2) Podaci za trenutnu stranicu
$select = [
    'p.id',
    'p.ime',
    'p.prezime',
];

if ($fVrsta)  $select[] = "p.`$fVrsta` AS vrsta_partnera"; else $select[] = "'' AS vrsta_partnera";
if ($fIdBroj) $select[] = "p.`$fIdBroj` AS id_broj"; else $select[] = "'' AS id_broj";
if ($fBrojR)  $select[] = "p.`$fBrojR` AS broj_racuna"; else $select[] = "'' AS broj_racuna";

$select = array_merge($select, [
    'p.kontakt',
    'p.email',
    'p.adresa',
    'p.mjesto_id',
    'm.naziv_mjesta AS mjesto_naz'
]);

$sqlData = "

    SELECT\n\n      " . implode(', ', $select) . "

    FROM partneri p

    LEFT JOIN mjesta m ON m.id = p.mjesto_id
    $where
    ORDER BY p.prezime ASC, p.ime ASC
    LIMIT ? OFFSET ?
";
$st = $conn->prepare($sqlData);
$dataTypes = $types . 'ii';
$dataArgs = array_merge($args, [$pageSize, $offset]);
$st->bind_param($dataTypes, ...$dataArgs);
$st->execute();
$res = $st->get_result();
$rows = $res->fetch_all(MYSQLI_ASSOC);
$st->close();

// 3) Odgovor
echo json_encode([
    'data'      => $rows,
    'total'     => (int)$total,
    'page'      => $page,
    'page_size' => $pageSize,
    'pages'     => (int)ceil($total / $pageSize),
], JSON_UNESCAPED_UNICODE);