<<?php
require_once __DIR__ . '/_bootstrap.php';
kubatapp_require_api('ping.php');

header('Content-Type: application/json; charset=utf-8');
echo json_encode(['ok' => true, 'ts' => time()]);