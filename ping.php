<<?php
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

kubatapp_require_api('ping.php');

header('Content-Type: application/json; charset=utf-8');
echo json_encode(['ok' => true, 'ts' => time()]);