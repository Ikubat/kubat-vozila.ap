<?php
header('Content-Type: application/json; charset=utf-8');

// prilagodi putanju do konekcije
require_once __DIR__ . '/config.php';

$q = isset($_GET['q']) ? trim($_GET['q']) : '';

$sql    = "SELECT id, naziv_mjesta, porezna_sifra, kanton FROM mjesta";
$params = [];

if ($q !== '') {
    $sql .= " WHERE naziv_mjesta    LIKE :q
              OR porezna_sifra      LIKE :q
              OR kanton             LIKE :q";
    $params[':q'] = "%{$q}%";
}

$sql .= " ORDER BY naziv_mjesta";

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($rows);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'ok'    => false,
        'error' => $e->getMessage()
    ]);
}
