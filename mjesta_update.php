<?php
// Ažurira zapis u tablici `mjesta`.
//
// Prihvaća JSON ili klasični POST. Podržava više naziva kolona
// (naziv/mjesto/naziv_mjesta i sifra/porezna_sifra) jednako kao mjesta_create.php.

header('Content-Type: application/json; charset=utf-8');
require __DIR__ . '/db.php';

function jdie($msg, $code = 200) {
    http_response_code($code);
    echo json_encode(['ok' => false, 'error' => $msg], JSON_UNESCAPED_UNICODE);
    exit;
}

function jok($extra = []) {
    echo json_encode(['ok' => true] + $extra, JSON_UNESCAPED_UNICODE);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($method !== 'POST') {
    jdie('Ovaj endpoint služi za ažuriranje mjesta (POST).');
}

$ct = $_SERVER['CONTENT_TYPE'] ?? '';

// Podrška za JSON ili klasični POST, jednako kao u create endpointu
if (stripos($ct, 'application/json') !== false) {
    $in = json_decode(file_get_contents('php://input'), true) ?: [];
    $id      = (int)($in['id'] ?? 0);
    $naziv   = trim((string)($in['naziv'] ?? $in['m_naziv'] ?? $in['naziv_mjesta'] ?? ''));
    $sifra   = trim((string)($in['sifra'] ?? $in['m_sifra'] ?? $in['porezna_sifra'] ?? ''));
    $kanton  = trim((string)($in['kanton'] ?? $in['m_kanton'] ?? ''));
} else {
    $id      = (int)($_POST['id'] ?? 0);
    $naziv   = trim((string)($_POST['naziv'] ?? $_POST['m_naziv'] ?? $_POST['naziv_mjesta'] ?? ''));
    $sifra   = trim((string)($_POST['sifra'] ?? $_POST['m_sifra'] ?? $_POST['porezna_sifra'] ?? ''));
    $kanton  = trim((string)($_POST['kanton'] ?? $_POST['m_kanton'] ?? ''));
}

if ($id <= 0) {
    jdie('ID nedostaje.');
}

if ($naziv === '') {
    jdie('Naziv mjesta je obavezan.');
}

try {
    // Čitanje stvarnih naziva kolona u tablici
    $cols = [];
    foreach ($pdo->query('SHOW COLUMNS FROM mjesta') as $c) {
        $cols[strtolower($c['Field'])] = $c['Field'];
    }

    $colId = $cols['id'] ?? 'id';
    $colNaziv =
        $cols['naziv']
        ?? $cols['mjesto']
        ?? $cols['naziv_mjesta']
        ?? null;

    if (!$colNaziv) {
        jdie("Tablica 'mjesta' mora imati kolonu 'naziv' ili 'mjesto' ili 'naziv_mjesta'.");
    }

    $colSifra  = $cols['sifra']  ?? ($cols['porezna_sifra'] ?? null);
    $colKanton = $cols['kanton'] ?? null;

    $fields = ["`$colNaziv` = ?"];
    $params = [$naziv];

    if ($colSifra !== null && $sifra !== '') {
        $fields[] = "`$colSifra` = ?";
        $params[] = $sifra;
    }

    if ($colKanton !== null && $kanton !== '') {
        $fields[] = "`$colKanton` = ?";
        $params[] = $kanton;
    }

    $params[] = $id;

    $sql = "UPDATE mjesta SET " . implode(', ', $fields) . " WHERE `$colId` = ?";
    $st  = $pdo->prepare($sql);
    $st->execute($params);

    jok(['id' => $id]);
} catch (Exception $e) {
    jdie('Greška pri ažuriranju: ' . $e->getMessage());
}
