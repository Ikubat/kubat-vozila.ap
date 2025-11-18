<?php
header('Content-Type: application/json; charset=utf-8');
require __DIR__ . '/db.php';

$q = isset($_GET['q']) ? trim($_GET['q']) : '';
$params = [];

$sql = "SELECT id, naziv_mjesta, porezna_sifra, kanton
        FROM mjesta";

if ($q !== '') {
    $sql .= " WHERE naziv_mjesta LIKE :q
              OR porezna_sifra LIKE :q
              OR kanton LIKE :q";
    $params[':q'] = "%$q%";
}

$sql .= " ORDER BY naziv_mjesta LIMIT 100";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));