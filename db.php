<?php
// Proxy entrypoint for the shared database connection.
// Loads the kubatapp JSON bootstrap (headers + error handling) and then
// includes the real `db.php` file from either the repo root or an `api/`
// subfolder. No extra PDO creation is needed here—the underlying script
// sets up the connection and exposes `$pdo` for downstream scripts.
require_once __DIR__ . '/_bootstrap.php';
kubatapp_require_api('db.php');

$pdo = new PDO('mysql:host=localhost;dbname=kubatapp;charset=utf8','root','',[
  PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
]);
