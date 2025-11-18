<?php
header('Content-Type: application/json; charset=utf-8');
require __DIR__ . '/db.php';

$q         = isset($_GET['q']) ? trim($_GET['q']) : '';
$page      = max(1, (int)($_GET['page'] ?? 1));
$pageSize  = min(100, max(1, (int)($_GET['page_size'] ?? 20)));
$offset    = ($page - 1) * $pageSize;

// Validacija za stranice i veličinu stranice
if ($page < 1) $page = 1;
if ($pageSize < 1) $pageSize = 20;

// Slaganje LIKE za pretraživanje
$like = '%' . $q . '%';

$whereParts = [];
$args = [];

if ($q !== '') {
    $whereParts[] = "p.ime LIKE :q";
    $whereParts[] = "p.prezime LIKE :q";
    $whereParts[] = "p.kontakt LIKE :q";
    $whereParts[] = "p.email LIKE :q";
    $whereParts[] = "p.adresa LIKE :q";
    $whereParts[] = "m.naziv_mjesta LIKE :q";
    $where = "WHERE " . implode(" OR ", $whereParts);
    $args[':q'] = $like;
} else {
    $where = '';
}

/* 1) Ukupan broj */
$sqlTotal = "
  SELECT COUNT(*)
  FROM partneri p
  LEFT JOIN mjesta m ON m.id = p.mjesto_id
  $where
";
$stTot = $pdo->prepare($sqlTotal);
foreach ($args as $k => $v) $stTot->bindValue($k, $v, PDO::PARAM_STR);
$stTot->execute();
$total = (int)$stTot->fetchColumn();

/* 2) Podaci za trenutnu stranu */
$sqlData = "
  SELECT
    p.id, p.ime, p.prezime, p.kontakt, p.email, p.adresa, p.mjesto_id,
    m.naziv_mjesta AS mjesto_naz
  FROM partneri p
  LEFT JOIN mjesta m ON m.id = p.mjesto_id
  $where
  ORDER BY p.prezime ASC, p.ime ASC
  LIMIT :lim OFFSET :off
";
$st = $pdo->prepare($sqlData);
foreach ($args as $k => $v) $st->bindValue($k, $v, PDO::PARAM_STR);
$st->bindValue(':lim', $pageSize, PDO::PARAM_INT);
$st->bindValue(':off', $offset, PDO::PARAM_INT);
$st->execute();
$rows = $st->fetchAll(PDO::FETCH_ASSOC);

/* 3) Odgovor */
echo json_encode([
  'data'      => $rows,
  'total'     => $total,
  'page'      => $page,
  'page_size' => $pageSize,
  'pages'     => (int)ceil($total / $pageSize),
], JSON_UNESCAPED_UNICODE);

