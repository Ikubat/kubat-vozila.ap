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

kubatapp_require_api('marka_distinct.php');

header('Content-Type: application/json; charset=utf-8');
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

require_once __DIR__ . '/config.php';

try {
    $tableCandidates = ['marka_vozila', 'marke', 'marka'];
    $table = null;
    $colNaziv = null;

    foreach ($tableCandidates as $candidate) {
        try {
            $res = $conn->query("SHOW COLUMNS FROM `$candidate`");
            $cols = $res->fetch_all(MYSQLI_ASSOC);
            $res->free();
        } catch (Throwable $e) {
            continue; // tablica ne postoji
        }

        if (!$cols) {
            continue;
        }

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

    $sql = sprintf(
        'SELECT DISTINCT `%1$s` AS naziv FROM `%2$s` WHERE TRIM(`%1$s`) <> "" ORDER BY `%1$s` ASC',
        $conn->real_escape_string($colNaziv),
        $conn->real_escape_string($table)
    );

    $res = $conn->query($sql);
    $rows = $res->fetch_all(MYSQLI_ASSOC);
    $res->free();

    $nazivi = array_map(static fn($row) => $row['naziv'], $rows);

    echo json_encode($nazivi, JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'ok'    => false,
        'error' => 'Greška pri dohvaćanju marki: ' . $e->getMessage(),
    ], JSON_UNESCAPED_UNICODE);
}