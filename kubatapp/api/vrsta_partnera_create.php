<?php
$bootstrapPath = dirname(__DIR__) . '/_bootstrap.php';
if (!is_file($bootstrapPath)) {
    $bootstrapPath = __DIR__ . '/_bootstrap.php';
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

kubatapp_require_api('vrsta_partnera_create.php');

header('Content-Type: application/json; charset=utf-8');
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
require_once __DIR__ . '/config.php';

$respond = function (bool $ok, array $extra = [], int $status = 200) {
    http_response_code($status);
    echo json_encode(['ok' => $ok] + $extra, JSON_UNESCAPED_UNICODE);
    exit;
};

try {
    $raw = file_get_contents('php://input');
    $in = json_decode($raw, true);
    if (!is_array($in)) {
        $respond(false, ['error' => 'Neispravan input (JSON očekivan).'], 400);
    }

    $naziv = trim($in['naziv'] ?? ($in['name'] ?? ''));
    if ($naziv === '') {
        $respond(false, ['error' => 'Naziv vrste je obavezan.'], 400);
    }

    $db = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
    $db->set_charset('utf8mb4');

    $db->query("CREATE TABLE IF NOT EXISTS vrsta_partnera (\n        id INT AUTO_INCREMENT PRIMARY KEY,\n        naziv VARCHAR(190) NOT NULL UNIQUE\n    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $chk = $db->prepare("SELECT id FROM vrsta_partnera WHERE naziv = ? LIMIT 1");
    $chk->bind_param('s', $naziv);
    $chk->execute();
    $chk->bind_result($existingId);
    if ($chk->fetch()) {
        $respond(true, ['id' => (int)$existingId, 'exists' => true]);
    }
    $chk->close();

    $st = $db->prepare("INSERT INTO vrsta_partnera (naziv) VALUES (?)");
    $st->bind_param('s', $naziv);
    $st->execute();

    $respond(true, ['id' => $db->insert_id]);
} catch (Throwable $e) {
    $respond(false, ['error' => 'Greška pri spremanju vrste partnera: ' . $e->getMessage()], 500);
}
