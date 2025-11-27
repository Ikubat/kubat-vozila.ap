<?php
// Common bootstrap for kubatapp JSON endpoints.
// Forces JSON responses and converts PHP errors/exceptions/fatal shutdowns to JSON
// so the frontend never receives HTML error pages.

if (!headers_sent()) {
    header('Content-Type: application/json; charset=utf-8');
}

// Swallow any accidental output (warnings, notices) so responses stay valid JSON
if (ob_get_level() === 0) {
    ob_start();
}

// Always report all errors but don't display native PHP HTML output
error_reporting(E_ALL);
ini_set('display_errors', '0');

// Mark that we're in the kubatapp JSON wrapper so config.php can tailor its output
if (!defined('KUBATAPP_JSON_API')) {
    define('KUBATAPP_JSON_API', true);
}

// Convert PHP errors to exceptions for unified handling
set_error_handler(function ($severity, $message, $file, $line) {
    // Respect @ suppression
    if (!(error_reporting() & $severity)) {
        return false;
    }
    throw new ErrorException($message, 0, $severity, $file, $line);
});

// Standard JSON response helper
// - Cleans any buffered warnings so output stays valid JSON
// - Uses INVALID_UTF8_SUBSTITUTE so loši znakovi iz baze ne razbiju JSON
// - Ako serializacija ipak zakaže, frontend će dobiti jasan JSON error
function kubatapp_json_response(array $payload, int $status = 200): void
{
    if (ob_get_length()) {
        ob_clean();
    }

    http_response_code($status);

    $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);

    if ($json === false) {
        // Posljednja zaštita: vrati validan JSON i pojasni grešku
        $fallback = [
            'ok'    => false,
            'error' => 'JSON encode neuspješan: ' . json_last_error_msg(),
        ];

        echo json_encode($fallback, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        return;
    }

    echo $json;
}

// Standard JSON error payload
function kubatapp_json_error($message, $status = 500)
{
    kubatapp_json_response([
        'ok'    => false,
        'error' => $message,
    ], $status);
}

/**
 * Locate and include the target API script.
 *
 * Supports installations where PHP endpoints live either in the repository
 * root or under an `api/` subdirectory (with JS/HTML kept elsewhere such as
 * `app/`). If the script cannot be found, the request fails with a JSON
 * error instead of a PHP warning/HTML page.
 */
function kubatapp_require_api(string $relativeScript): void
{
    // Kod tebe su svi API fajlovi u istom folderu kao _bootstrap.php (api/)
    $baseDirs = [
        __DIR__,
    ];

    foreach ($baseDirs as $base) {
        $candidate = rtrim($base, '/\\') . '/' . ltrim($relativeScript, '/\\');
        if (is_file($candidate)) {
            require_once $candidate;
            return;
        }
    }

    kubatapp_json_error('API skripta nije pronađena: ' . $relativeScript, 500);
    exit;
}

// Handle uncaught exceptions
set_exception_handler(function ($e) {
    $code = $e->getCode();
    $status = ($code >= 400 && $code < 600) ? $code : 500;
    kubatapp_json_error($e->getMessage(), $status);
});

// Handle fatal shutdowns
register_shutdown_function(function () {
    $err = error_get_last();
    if (!$err) {
        return;
    }

    $fatalTypes = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR];
    if (in_array($err['type'], $fatalTypes, true)) {
        kubatapp_json_error('Greška na serveru: ' . $err['message'], 500);
    }
});