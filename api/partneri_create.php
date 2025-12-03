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

    $cols = [];
    $res = $conn->query('SHOW COLUMNS FROM partneri');
    while ($c = $res->fetch_assoc()) {
        $cols[strtolower($c['Field'])] = $c['Field'];
    }

    $fIme       = $cols['ime'] ?? null;
    $fPrezime   = $cols['prezime'] ?? null;
    $fNaziv     = $cols['naziv'] ?? null;
    $fKontakt   = $cols['kontakt'] ?? $cols['telefon'] ?? $cols['tel'] ?? null;
    $fEmail     = $cols['email'] ?? $cols['mail'] ?? null;
    $fAdresa    = $cols['adresa'] ?? null;
    $fMjestoId  = $cols['mjesto_id'] ?? $cols['id_mjesta'] ?? null;
    $fVrsta     = $cols['vrsta_partnera'] ?? $cols['vrsta'] ?? null;
    $fIdBroj    = $cols['id_broj'] ?? $cols['idbroj'] ?? $cols['id_broj_partnera'] ?? null;
    $fBrojRac   = $cols['broj_racuna'] ?? $cols['brojracuna'] ?? null;

    $fields = [];
    $placeholders = [];
    $types = '';
    $values = [];

    $addField = function (string $name, $value, string $type = 's') use (&$fields, &$placeholders, &$types, &$values) {
        $fields[] = "`$name`";
        $placeholders[] = '?';
        $types .= $type;
        $values[] = $value;
    };

    if ($fIme) $addField($fIme, $ime);
    if ($fPrezime) $addField($fPrezime, $prezime);
    if ($fNaziv && !$fIme && !$fPrezime) $addField($fNaziv, trim($ime . ' ' . $prezime));
    if ($fVrsta) $addField($fVrsta, $vrsta);
    if ($fIdBroj) $addField($fIdBroj, $idBroj);
    if ($fBrojRac) $addField($fBrojRac, $brojRacuna);
    if ($fKontakt) $addField($fKontakt, $kontakt);
    if ($fEmail) $addField($fEmail, $email);
    if ($fAdresa) $addField($fAdresa, $adresa);
    if ($fMjestoId) $addField($fMjestoId, $mjesto_id !== null ? $mjesto_id : null, 'i');

    if (!$fields) {
        throw new RuntimeException('Tablica partneri nema očekivane kolone.');
    }

    $sql = 'INSERT INTO partneri (' . implode(', ', $fields) . ') VALUES (' . implode(', ', $placeholders) . ')';
    $st = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($st, $types, ...$values);
    mysqli_stmt_execute($st);

    $respond(true, ['id' => mysqli_insert_id($conn)]);
} catch (Throwable $e) {
    $respond(false, ['error' => 'Greška na serveru: ' . $e->getMessage()], 500);
}
