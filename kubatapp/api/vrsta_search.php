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

kubatapp_require_api('vrsta_search.php');

header('Content-Type: application/json; charset=utf-8');
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
require_once __DIR__ . '/config.php';

$q = isset($_GET['q']) ? trim($_GET['q']) : '';
$sql = "SELECT id, naziv, oznaka FROM vrsta_vozila";
$args = [];
$types = '';

if ($q !== '') {
    $sql .= " WHERE naziv LIKE ? OR oznaka LIKE ?";
    $like = '%' . $q . '%';
    $args = [$like, $like];
    $types = 'ss';
}
$sql .= " ORDER BY oznaka ASC LIMIT 200";

$st = $conn->prepare($sql);
if ($args) {
    $st->bind_param($types, ...$args);
}
$st->execute();
$res = $st->get_result();
echo json_encode($res->fetch_all(MYSQLI_ASSOC), JSON_UNESCAPED_UNICODE);
$st->close();
