<?php
if (!defined('APP_INSTALLED')) {
    $config = __DIR__ . '/config.php';
    if (!file_exists($config)) {
        http_response_code(500);
        exit('Sistem henüz kurulmamış. Lütfen install.php dosyasını çalıştırın.');
    }
    require_once $config;
}

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }
    $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
    ]);
    return $pdo;
}
