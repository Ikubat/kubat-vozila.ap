<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/config.php';

$respond = function (bool $ok, array $extra = [], int $status = 200) {
    http_response_code($status);
    echo json_encode(['ok' => $ok] + $extra, JSON_UNESCAPED_UNICODE);
    exit;
};

try {
    $in = json_decode(file_get_contents('php://input'), true) ?: [];

    $ime       = trim($in['ime'] ?? '');
    $prezime   = trim($in['prezime'] ?? '');
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

    $sql = "INSERT INTO partneri (ime, prezime, kontakt, email, adresa, mjesto_id)
            VALUES (?, ?, ?, ?, ?, ?)";
    $st = $pdo->prepare($sql);
    $ok = $st->execute([$ime, $prezime, $kontakt, $email, $adresa, $mjesto_id]);

    $respond($ok, ['id' => $pdo->lastInsertId()]);
} catch (Throwable $e) {
    $respond(false, ['error' => 'Greška na serveru: ' . $e->getMessage()], 500);
}
