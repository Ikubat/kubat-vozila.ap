<?php
header('Content-Type: application/json; charset=utf-8');
require __DIR__ . '/db.php';

try {
    $stmt = $pdo->query("SELECT id, naziv_mjesta FROM mjesta ORDER BY naziv_mjesta");
    $result = $stmt->fetchAll();
    echo json_encode($result);
} catch (Exception $e) {
    // Ako dođe do greške, vraćamo grešku u JSON
    http_response_code(500);
    echo json_encode([
        'ok'    => false,
        'error' => 'Greška u dohvaćanju podataka: ' . $e->getMessage()
    ]);
}
