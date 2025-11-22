<?php
header('Content-Type: application/json; charset=utf-8');

try {
    require_once __DIR__ . '/config.php';

    // — detektuj koju tablicu / kolonu za naziv imamo —
    $tableCandidates = ['marka_vozila', 'marke', 'marka'];
    $table = null;
    $colNaziv = null;

    foreach ($tableCandidates as $candidate) {
        try {
            $cols = $pdo->query("SHOW COLUMNS FROM `$candidate`")->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            continue; // tablica ne postoji
        }

        if (!$cols) continue;

        $map = [];
        foreach ($cols as $c) {
            $map[strtolower($c['Field'])] = $c['Field'];
        }

        $colNaziv = $map['naziv'] ?? $map['marka'] ?? $map['naziv_marka'] ?? null;
        if ($colNaziv) {
            $table = $candidate;
            break;
        }
    }

    if (!$table || !$colNaziv) {
        throw new RuntimeException('Nije pronađena tablica s nazivom marke.');
    }

    $stmt = $pdo->query("SELECT DISTINCT `$colNaziv` AS naziv FROM `$table` WHERE TRIM(`$colNaziv`) <> '' ORDER BY `$colNaziv` ASC");
    $rows = $stmt->fetchAll(PDO::FETCH_COLUMN);

    echo json_encode($rows, JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'ok'    => false,
        'error' => 'Greška pri dohvaćanju marki: ' . $e->getMessage(),
    ], JSON_UNESCAPED_UNICODE);
}