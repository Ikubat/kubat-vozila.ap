<?php
$bootstrapPath = __DIR__ . '/_bootstrap.php';
if (!is_file($bootstrapPath)) {
    $bootstrapPath = dirname(__DIR__) . '/_bootstrap.php';
}
if (!is_file($bootstrapPath)) {
    if (!headers_sent()) {
        header('Content-Type: application/json; charset=utf-8');
    }
    http_response_code(500);
    echo json_encode([
        'ok'    => false,
        'error' => 'API bootstrap nije pronađen.',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

require_once $bootstrapPath;

kubatapp_require_api('partneri_delete.php');

declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

require_once __DIR__ . '/config.php';

try {
    $raw = file_get_contents('php://input');
    $in  = json_decode($raw, true);

    if (!is_array($in) || empty($in['id'])) {
        echo json_encode(['error' => 'ID nije poslan.']);
        exit;
    }

    $id = (int)$in['id'];
    if ($id <= 0) {
        echo json_encode(['error' => 'ID nije ispravan.']);
        exit;
    }

    $stmt = $conn->prepare('DELETE FROM partneri WHERE id = ?');
    $stmt->bind_param('i', $id);
    $stmt->execute();

    if ($stmt->affected_rows > 0) {
        echo json_encode(['ok' => true]);
    } else {
        echo json_encode(['error' => 'Ne postoji ili je već obrisan.']);
    }

    $stmt->close();
} catch (mysqli_sql_exception $e) {
    echo json_encode(['error' => 'Brisanje nije uspjelo: ' . $e->getMessage()]);
}