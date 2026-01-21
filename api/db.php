<?php
/**
 * Подключение к базе данных
 */

require_once __DIR__ . '/config.php';

$config = require __DIR__ . '/config.php';

function getDB() {
    global $config;
    static $pdo = null;
    
    if ($pdo === null) {
        try {
            $dsn = "mysql:host={$config['database']['host']};dbname={$config['database']['dbname']};charset={$config['database']['charset']}";
            $pdo = new PDO($dsn, $config['database']['user'], $config['database']['pass'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
        } catch (PDOException $e) {
            http_response_code(500);
            die(json_encode(['error' => 'Database connection failed: ' . $e->getMessage()]));
        }
    }
    
    return $pdo;
}

