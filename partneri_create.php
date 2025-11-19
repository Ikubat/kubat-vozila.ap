<?php
header('Content-Type: application/json; charset=utf-8');␊
require __DIR__ . '/db.php';

$in = json_decode(file_get_contents('php://input'), true) ?: [];

$ime       = trim($in['ime'] ?? '');
$prezime   = trim($in['prezime'] ?? '');
$kontakt   = trim($in['kontakt'] ?? '');
$email     = trim($in['email'] ?? '');
$adresa    = trim($in['adresa'] ?? '');
$mjesto_id = isset($in['mjesto_id']) && $in['mjesto_id'] !== '' ? (int)$in['mjesto_id'] : null;

$err = [];
if ($ime === '')     $err[] = 'Ime je obavezno.';
if ($prezime === '') $err[] = 'Prezime je obavezno.';
if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) $err[] = 'Email nije ispravan.';

if ($err) {
    echo json_encode(['ok' => false, 'errors' => $err]);
    exit;
}

$sql = "INSERT INTO partneri (ime, prezime, kontakt, email, adresa, mjesto_id)
        VALUES (?, ?, ?, ?, ?, ?)";
$st = $pdo->prepare($sql);
$ok = $st->execute([$ime, $prezime, $kontakt, $email, $adresa, $mjesto_id]);

echo json_encode(['ok' => $ok, 'id' => $pdo->lastInsertId()]);
