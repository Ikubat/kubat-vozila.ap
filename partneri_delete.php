<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

require __DIR__ . '/../db.php'; // u ovom db.php MORA postojati $pdo (PDO konekcija)

try {
    $raw = file_get_contents('php://input');
    $in  = json_decode($raw, true);

    if (!is_array($in) || empty($in['id'])) {
        echo json_encode(['error' => 'ID nije poslan.']);
        exit;
    }

    $id = (int)$in['id'];
    if ($id <= 0) {
        echo json_encode(['error' => 'ID nije ispravan.']);
        exit;
    }

    $stmt = $pdo->prepare('DELETE FROM partneri WHERE id = :id');
    $stmt->execute([':id' => $id]);

    if ($stmt->rowCount() > 0) {
        echo json_encode(['ok' => true]);
    } else {
        echo json_encode(['error' => 'Ne postoji ili je već obrisan.']);
    }
} catch (PDOException $e) {
    echo json_encode(['error' => 'Brisanje nije uspjelo: ' . $e->getMessage()]);
}

