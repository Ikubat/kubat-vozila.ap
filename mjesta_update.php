<?php
header('Content-Type: application/json; charset=utf-8');
require __DIR__ . '/../db.php';

$in = json_decode(file_get_contents('php://input'), true) ?: [];

$id            = (int)($in['id'] ?? 0);
$naziv_mjesta  = trim((string)($in['naziv_mjesta'] ?? ''));
$porezna_sifra = trim((string)($in['porezna_sifra'] ?? ''));
$kanton        = trim((string)($in['kanton'] ?? ''));

if ($id <= 0) {
    echo json_encode(['ok' => false, 'error' => 'ID nedostaje.']);
    exit;
}

if ($naziv_mjesta === '' || $porezna_sifra === '' || $kanton === '') {
    echo json_encode(['ok' => false, 'error' => 'Sva polja su obavezna.']);
    exit;
}

$sql = "UPDATE mjesta
        SET naziv_mjesta = ?, porezna_sifra = ?, kanton = ?
        WHERE id = ?";
$st  = $pdo->prepare($sql);
$ok  = $st->execute([$naziv_mjesta, $porezna_sifra, $kanton, $id]);

echo json_encode(['ok' => (bool)$ok, 'id' => $id]);
