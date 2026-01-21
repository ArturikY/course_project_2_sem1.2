<?php
/**
 * Подключение к базе данных
 */

// Отключаем вывод ошибок
ini_set('display_errors', 0);
error_reporting(E_ALL);

function getDB() {
    static $pdo = null;
    
    if ($pdo === null) {
        $config = [
            'host' => 'localhost',
            'dbname' => 'dtp_analysis',
            'user' => 'root',
            'pass' => '',
            'charset' => 'utf8mb4'
        ];
        
        try {
            $dsn = "mysql:host={$config['host']};dbname={$config['dbname']};charset={$config['charset']}";
            $pdo = new PDO($dsn, $config['user'], $config['pass'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
        } catch (PDOException $e) {
            http_response_code(500);
            header('Content-Type: application/json; charset=utf-8');
            die(json_encode(['error' => 'Database connection failed: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE));
        }
    }
    
    return $pdo;
}
