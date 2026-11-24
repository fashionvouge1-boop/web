<?php
/**
 * PDO database connection (singleton).
 * Usage:  $pdo = db();
 */
require_once __DIR__ . '/config.php';

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', DB_HOST, DB_PORT, DB_NAME);

    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];

    $sslCa = getenv('DB_SSL_CA');
    if ($sslCa && is_file($sslCa)) {
        $options[PDO::MYSQL_ATTR_SSL_CA] = $sslCa;
        $options[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = true;
    } elseif (getenv('DB_SSL') === '1') {
        $options[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = false;
    }

    try {
        $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
    } catch (PDOException $e) {
        http_response_code(500);
        exit('<h2 style="font-family:sans-serif">Database connection failed</h2>'
            . '<p style="font-family:sans-serif">' . htmlspecialchars($e->getMessage()) . '</p>'
            . '<p style="font-family:sans-serif">Check the credentials in <code>config/config.php</code> '
            . 'and make sure you imported <code>database.sql</code>.</p>');
    }

    return $pdo;
}
