<?php
header('Content-Type: application/json; charset=utf-8');
require __DIR__ . '/db.php';

$rows = $pdo->query("SELECT DISTINCT naziv FROM marke ORDER BY naziv ASC")->fetchAll(PDO::FETCH_COLUMN);
echo json_encode($rows, JSON_UNESCAPED_UNICODE);

