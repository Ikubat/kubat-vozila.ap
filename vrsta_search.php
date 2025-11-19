<?php
header('Content-Type: application/json; charset=utf-8');␊
require __DIR__ . '/db.php';

$q = isset($_GET['q']) ? trim($_GET['q']) : '';
$sql = "SELECT id, naziv, oznaka FROM vrsta_vozila";
$args = [];

if ($q !== '') {
  $sql .= " WHERE naziv LIKE :q OR oznaka LIKE :q";
  $args[':q'] = '%'.$q.'%';
}
$sql .= " ORDER BY oznaka ASC LIMIT 200";

$st = $pdo->prepare($sql);
$st->execute($args);
echo json_encode($st->fetchAll(PDO::FETCH_ASSOC), JSON_UNESCAPED_UNICODE);
