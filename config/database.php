<?php
/**
 * StudentFlow - Database connection (PDO)
 * Adjust credentials here for your local XAMPP setup.
 */

define('DB_HOST', getenv('SF_DB_HOST') ?: '127.0.0.1');
define('DB_NAME', getenv('SF_DB_NAME') ?: 'studentflow');
define('DB_USER', getenv('SF_DB_USER') ?: 'root');
define('DB_PASS', getenv('SF_DB_PASS') !== false ? getenv('SF_DB_PASS') : '');

try {
    $pdo = new PDO(
        'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );
} catch (PDOException $ex) {
    http_response_code(500);
    error_log('StudentFlow DB error: ' . $ex->getMessage());
    exit('We could not connect to the database. Make sure MySQL is running and you imported database/studentflow.sql.');
}

/** Get the shared PDO connection. */
function db(): PDO
{
    global $pdo;
    return $pdo;
}
