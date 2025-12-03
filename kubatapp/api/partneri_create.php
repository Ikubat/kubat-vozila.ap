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

kubatapp_require_api('partneri_create.php');

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/config.php';

$respond = function (bool $ok, array $extra = [], int $status = 200) {
    http_response_code($status);
    echo json_encode(['ok' => $ok] + $extra, JSON_UNESCAPED_UNICODE);
    exit;
};

// mysqli će baciti izuzetak umjesto upozorenja
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    $in = json_decode(file_get_contents('php://input'), true) ?: [];

    $ime       = trim($in['ime'] ?? '');
    $prezime   = trim($in['prezime'] ?? '');
    $vrsta     = trim($in['vrsta_partnera'] ?? ($in['vrsta'] ?? ''));
    $idBroj    = trim($in['id_broj'] ?? '');
    $brojRacuna = trim($in['broj_racuna'] ?? '');
    $kontakt   = trim($in['kontakt'] ?? '');
    $email     = trim($in['email'] ?? '');
    $adresa    = trim($in['adresa'] ?? '');
    $mjesto_id = isset($in['mjesto_id']) && $in['mjesto_id'] !== '' ? (int)$in['mjesto_id'] : null;

    $err = [];
    if ($ime === '' && $prezime === '') $err[] = 'Ime ili prezime je obavezno.';
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) $err[] = 'Email nije ispravan.';

       if ($err) {
        $respond(false, ['error' => implode(' ', $err)]);
    }

    $sql = "INSERT INTO partneri (ime, prezime, vrsta_partnera, id_broj, broj_racuna, kontakt, email, adresa, mjesto_id)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $st = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($st, 'ssssssssi', $ime, $prezime, $vrsta, $idBroj, $brojRacuna, $kontakt, $email, $adresa, $mjesto_id);
    mysqli_stmt_execute($st);

    $respond(true, ['id' => mysqli_insert_id($conn)]);
} catch (Throwable $e) {
    $respond(false, ['error' => 'Greška na serveru: ' . $e->getMessage()], 500);
}
