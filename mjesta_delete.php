<?php
header('Content-Type: application/json; charset=utf-8');
require __DIR__ . '/db.php';

$in = json_decode(file_get_contents('php://input'), true) ?: [];
$id = (int)($in['id'] ?? ($_GET['id'] ?? 0));

if ($id <= 0) {
    http_response_code(422);
    echo json_encode([
        'ok'     => false,
        'errors' => ['Nevažeći ID.']
    ]);
    exit;
}

try {
    $stmt = $pdo->prepare("DELETE FROM mjesta WHERE id=?");
    $stmt->execute([$id]);
    echo json_encode([
        'ok'      => true,
        'deleted' => $stmt->rowCount()
    ]);
} catch (PDOException $e) {
    // Ako kasnije dodamo FK na partnere, ovdje može pasti – vratimo poruku
    http_response_code(400);
    echo json_encode([
        'ok'    => false,
        'error' => $e->getMessage()
    ]);
}
