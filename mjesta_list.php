<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/config.php';

try {
    $stmt = $pdo->query("SELECT id, naziv_mjesta AS naziv FROM mjesta ORDER BY naziv_mjesta");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'ok'   => true,
        'data' => $rows,
    ], JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'ok'    => false,
        'error' => 'Greška u dohvaćanju podataka: ' . $e->getMessage()
    ]);
}