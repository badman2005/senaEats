<?php
declare(strict_types=1);
// Override via environment variables in production (never commit real credentials).
function db(): PDO {
    static $pdo = null;
    if ($pdo) return $pdo;
    $dsn = sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4',
        getenv('SE_DB_HOST') ?: '127.0.0.1', getenv('SE_DB_NAME') ?: 'senaeats');
    $pdo = new PDO($dsn, getenv('SE_DB_USER') ?: 'senaeats', getenv('SE_DB_PASS') ?: 'change_me', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,   // real prepared statements
    ]);
    return $pdo;
}
