<?php
require_once __DIR__ . '/_bootstrap.php';
kubatapp_require_api('mjesta_delete.php');

header('Content-Type: application/json; charset=utf-8');
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

require_once __DIR__ . '/config.php';

$in = json_decode(file_get_contents('php://input'), true) ?: [];
$id = (int)($in['id'] ?? ($_GET['id'] ?? 0));

if ($id <= 0) {
    http_response_code(422);
    echo json_encode([
        'ok'     => false,
        'errors' => ['Nevažeći ID.'],
    ]);
    exit;
}

try {
    $stmt = $conn->prepare('DELETE FROM mjesta WHERE id = ?');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $deleted = $stmt->affected_rows;
    $stmt->close();

    echo json_encode([
        'ok'      => true,
        'deleted' => $deleted,
    ]);
} catch (mysqli_sql_exception $e) {
    http_response_code(400);
    echo json_encode([
        'ok'    => false,
        'error' => $e->getMessage(),
    ]);
}