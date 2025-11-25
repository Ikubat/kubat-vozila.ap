<?php
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

kubatapp_require_api('partneri_list.php');

// partneri_list.php
// Robusna lista partnera za frontend (partneri.js).
// Podržava:
// - tekstualno polje mjesta (partneri.mjesto / grad / city)
// - ili FK mjesto_id -> tablica mjesta (mjesta.id / mjesta.naziv)

header('Content-Type: application/json; charset=utf-8');
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

require_once __DIR__ . '/config.php';

$T_PARTNERI = 'partneri';
$T_MJESTA   = 'mjesta';

function out($payload, $code = 200) {
    http_response_code($code);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $db = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
    $db->set_charset('utf8mb4');

    // --- postoji li partneri ---
    $check = $db->query("SHOW TABLES LIKE '$T_PARTNERI'");
    if ($check->num_rows === 0) {
        out(['ok' => false, 'error' => "Tablica '$T_PARTNERI' ne postoji."], 500);
    }

    // --- učitaj kolone partnera ---
    $colsRes = $db->query("SHOW COLUMNS FROM `$T_PARTNERI`");
    $pcols = [];
    while ($c = $colsRes->fetch_assoc()) {
        $pcols[strtolower($c['Field'])] = $c['Field'];
    }
    if (!$pcols) {
        out(['ok' => false, 'error' => "Ne mogu pročitati strukturu tablice '$T_PARTNERI'."], 500);
    }

    // helper za mapiranje iz partneri.*
    $pickP = function (...$names) use ($pcols) {
        foreach ($names as $n) {
            $ln = strtolower($n);
            if (isset($pcols[$ln])) return $pcols[$ln];
        }
        return null;
    };

    $p_id        = $pickP('id', 'id_partner', 'partner_id');
    $p_ime       = $pickP('ime', 'first_name');
    $p_prezime   = $pickP('prezime', 'last_name');
    $p_naziv     = $pickP('naziv', 'naziv_partner', 'name', 'full_name');
    $p_adresa    = $pickP('adresa', 'ulica', 'address');
    $p_mjestoTxt = $pickP('mjesto', 'grad', 'city');           // tekstualno mjesto
    $p_mjestoFK  = $pickP('mjesto_id', 'id_mjesta', 'mjesto_fk'); // FK na mjesta
    $p_tel       = $pickP('kontakt', 'kontakt_osoba', 'telefon', 'tel', 'mobitel', 'phone');
    $p_email     = $pickP('email', 'mail', 'e_mail');

    if (!$p_id) {
        out(['ok' => false, 'error' => "Tablica '$T_PARTNERI' nema ID kolonu (npr. id, id_partner)."], 500);
    }

    // --- provjera tablice mjesta (za mjesto_id) ---
    $hasMjesta = false;
    $m_id = $m_naziv = null;

    if ($p_mjestoFK) {
        $chkM = $db->query("SHOW TABLES LIKE '$T_MJESTA'");
        if ($chkM->num_rows > 0) {
            $mColsRes = $db->query("SHOW COLUMNS FROM `$T_MJESTA`");
            $mcols = [];
            while ($c = $mColsRes->fetch_assoc()) {
                $mcols[strtolower($c['Field'])] = $c['Field'];
            }
            $pickM = function (...$names) use ($mcols) {
                foreach ($names as $n) {
                    $ln = strtolower($n);
                    if (isset($mcols[$ln])) return $mcols[$ln];
                }
                return null;
            };
            $m_id    = $pickM('id', 'id_mjesta');
            $m_naziv = $pickM('naziv', 'mjesto', 'naziv_mjesta', 'name', 'city');
            if ($m_id && $m_naziv) {
                $hasMjesta = true;
            }
        }
    }

    // --- slaganje SELECT-a ---
    $select = [];
    $select[] = "p.`$p_id` AS id";

    if ($p_ime)       $select[] = "p.`$p_ime` AS ime_raw";
    if ($p_prezime)   $select[] = "p.`$p_prezime` AS prezime_raw";
    if ($p_naziv)     $select[] = "p.`$p_naziv` AS naziv_raw";
    if ($p_adresa)    $select[] = "p.`$p_adresa` AS adresa_raw";
    if ($p_mjestoTxt) $select[] = "p.`$p_mjestoTxt` AS mjesto_txt_raw";
    if ($p_tel)       $select[] = "p.`$p_tel` AS tel_raw";
    if ($p_email)     $select[] = "p.`$p_email` AS email_raw";

    if ($hasMjesta && $p_mjestoFK) {
        $select[] = "p.`$p_mjestoFK` AS mjesto_id_raw";
        $select[] = "m.`$m_naziv` AS mjesto_fk_naziv";
    }

    // fallback: ako baš ništa osim ID-a i naziv/ime ne postoji, i to je ok
    if (count($select) === 1 && $p_naziv) {
        $select[] = "p.`$p_naziv` AS naziv_raw";
    }

    // ako je nekim slučajem ostalo samo id -> barem vrati id
    if (count($select) === 1) {
        $sql = "SELECT " . implode(', ', $select) . " FROM `$T_PARTNERI` ORDER BY p.`$p_id`";
    } else {
        // order by prefer naziv / ime / prezime / id
        $orderCol = $p_naziv ?: $p_ime ?: $p_prezime ?: $p_id;

        if ($hasMjesta && $p_mjestoFK) {
            // JOIN varijanta
            $sql = "SELECT " . implode(', ', $select) . "
                    FROM `$T_PARTNERI` p
                    LEFT JOIN `$T_MJESTA` m ON p.`$p_mjestoFK` = m.`$m_id`
                    ORDER BY p.`$orderCol`";
        } else {
            // bez join-a
            $sql = "SELECT " . implode(', ', $select) . "
                    FROM `$T_PARTNERI` p
                    ORDER BY p.`$orderCol`";
        }
    }

    $rs = $db->query($sql);

    $data = [];
    while ($row = $rs->fetch_assoc()) {
        $id = (int)$row['id'];

        $ime     = isset($row['ime_raw']) ? trim((string)$row['ime_raw']) : '';
        $prezime = isset($row['prezime_raw']) ? trim((string)$row['prezime_raw']) : '';
        $naziv   = isset($row['naziv_raw']) ? trim((string)$row['naziv_raw']) : '';

        // ako nema ime/prezime, probaj iz naziva
        if (!$ime && !$prezime && $naziv !== '') {
            $parts = preg_split('/\s+/', $naziv);
            if (count($parts) === 1) {
                $ime = $parts[0];
            } else {
                $ime = array_shift($parts);
                $prezime = implode(' ', $parts);
            }
        }

        $adresa = isset($row['adresa_raw']) ? trim((string)$row['adresa_raw']) : '';

        // mjesto: prioritet je JOIN (mjesto_fk_naziv), pa tekstualno, pa prazno
        $mjesto = '';
        if (isset($row['mjesto_fk_naziv']) && $row['mjesto_fk_naziv'] !== null && $row['mjesto_fk_naziv'] !== '') {
            $mjesto = trim((string)$row['mjesto_fk_naziv']);
        } elseif (isset($row['mjesto_txt_raw'])) {
            $mjesto = trim((string)$row['mjesto_txt_raw']);
        }

        $tel   = isset($row['tel_raw'])   ? trim((string)$row['tel_raw'])   : '';
        $email = isset($row['email_raw']) ? trim((string)$row['email_raw']) : '';

        $data[] = [
            'id'      => $id,
            'ime'     => $ime,
            'prezime' => $prezime,
            'kontakt' => $tel,
            'email'   => $email,
            'adresa'  => $adresa,
            'mjesto'  => $mjesto,
        ];
    }

    out(['ok' => true, 'data' => $data]);

} catch (mysqli_sql_exception $e) {
    out(['ok' => false, 'error' => 'DB greška: ' . $e->getMessage()], 500);
}