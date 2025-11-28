<?php
/**
 * GLOBALNI CONFIG ZA BAZU (XAMPP + PRODUKCIJA)
 *
 * Ovaj fajl:
 * - automatski detektuje da li si na lokalnom (XAMPP) ili na hostingu
 * - postavlja odgovarajuće podatke za bazu
 * - pravi $conn (mysqli) konekciju koju koristiš svuda u aplikaciji
 */

/* ============================
   1. DETEKCIJA OKRUŽENJA
   ============================ */

// Podrazumijevamo da je lokalno ako je host "localhost" ili "127.0.0.1"
$httpHost = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'cli';

$isLocal =
    $httpHost === 'localhost' ||
    $httpHost === '127.0.0.1' ||
    $httpHost === 'localhost:80' ||
    $httpHost === 'localhost:8080';

/* ============================
   2. POSTAVKE ZA BAZU
   ============================ */

if ($isLocal) {
    // ------------------------------

    // LOKALNO (XAMPP)

    // ------------------------------

    define('DB_HOST', 'localhost');
    define('DB_USER', 'root');
    define('DB_PASS', '');
    define('DB_NAME', 'kubatapp'); // ime tvoje lokalne baze


    // Full error reporting lokalno; ne prikazuj PHP warninge u JSON API-ima
    error_reporting(E_ALL);
    ini_set('display_errors', defined('KUBATAPP_JSON_API') ? 0 : 1);

} else {
    // ------------------------------
    // PRODUKCIJA (SERVER / HOSTING)
    // ------------------------------
    /**
     * OVDJE ĆEŠ POPUNITI PODATKE OD HOSTINGA:
     * - DB_USER: MySQL korisnik
     * - DB_PASS: lozinka tog korisnika
     * - DB_NAME: ime baze na serveru (često ima prefiks, npr. acc_kubatapp)
     */

    define('DB_HOST', 'localhost');      // obično ostaje 'localhost'
    define('DB_USER', 'PROD_DB_USER');   // npr. uvoz_kubatuser
    define('DB_PASS', 'PROD_DB_PASS');   // npr. JakaLozinka123!
    define('DB_NAME', 'PROD_DB_NAME');   // npr. uvoz_kubatapp

    // Na produkciji je bolje da se greške ne prikazuju korisniku
    error_reporting(E_ALL);
    ini_set('display_errors', 0);
}

/* ============================
   3. KONEKCIJA (MYSQLI)
   ============================ */

$conn = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);

if (!$conn) {
    http_response_code(500);

    $message = 'Neuspjela konekcija na bazu: ' . mysqli_connect_error();
    if (defined('KUBATAPP_JSON_API')) {
        echo json_encode([
            'ok'    => false,
            'error' => $message,
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    die($message);
}

mysqli_set_charset($conn, 'utf8mb4');

// Kompatibilni $DB_* varijable za stare skripte koje ih očekuju.
$DB_HOST = DB_HOST;
$DB_USER = DB_USER;
$DB_PASS = DB_PASS;
$DB_NAME = DB_NAME;