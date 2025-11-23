<?php
header('Content-Type: application/json; charset=utf-8');
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

require_once __DIR__ . '/config.php';

$q = isset($_GET['q']) ? trim($_GET['q']) : '';

$sql = "SELECT id, naziv_mjesta, porezna_sifra, kanton FROM mjesta";
$args = [];
$types = '';

if ($q !== '') {
    $like = '%' . $q . '%';
    $sql .= " WHERE naziv_mjesta LIKE ? OR porezna_sifra LIKE ? OR kanton LIKE ?";
    $args = [$like, $like, $like];
    $types = 'sss';
}

$sql .= " ORDER BY naziv_mjesta";

try {
    $stmt = $conn->prepare($sql);
    if ($args) {
        $stmt->bind_param($types, ...$args);
    }
    $stmt->execute();
    $res = $stmt->get_result();
    $rows = $res->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    echo json_encode($rows, JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'ok'    => false,
        'error' => 'Greška u dohvaćanju podataka: ' . $e->getMessage(),
    ], JSON_UNESCAPED_UNICODE);
}
