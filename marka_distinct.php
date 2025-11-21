header('Content-Type: application/json; charset=utf-8');

try {
    require __DIR__ . '/db.php';

    $rows = $pdo->query("SELECT DISTINCT naziv FROM marke ORDER BY naziv ASC")->fetchAll(PDO::FETCH_COLUMN);
    echo json_encode($rows, JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'ok'    => false,
        'error' => 'Greška pri dohvaćanju marki: ' . $e->getMessage(),
    ], JSON_UNESCAPED_UNICODE);
}
