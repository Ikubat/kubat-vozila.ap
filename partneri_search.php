<?php
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

if ($q !== '') {
    $like = '%' . $q . '%';
    $whereParts = [
        'p.ime LIKE ?',
        'p.prezime LIKE ?',
        'p.kontakt LIKE ?',
        'p.email LIKE ?',
        'p.adresa LIKE ?',
        'm.naziv_mjesta LIKE ?'
    ];
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
$sqlData = "
    SELECT
      p.id, p.ime, p.prezime, p.kontakt, p.email, p.adresa, p.mjesto_id,
      m.naziv_mjesta AS mjesto_naz
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