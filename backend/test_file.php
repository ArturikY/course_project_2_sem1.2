<?php
/**
 * Тестовый скрипт для проверки загрузки файла
 */

ini_set('display_errors', 1);
error_reporting(E_ALL);
ini_set('memory_limit', '512M');
ini_set('max_execution_time', '300');

header('Content-Type: text/plain; charset=utf-8');

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/data_loader.php';

try {
    $config = require __DIR__ . '/config.php';
    
    echo "=== ТЕСТ ЗАГРУЗКИ ДАННЫХ ===\n\n";
    echo "Источник данных: " . $config['data_source'] . "\n";
    
    if ($config['data_source'] === 'file') {
        $filePath = $config['file']['ndjson_path'];
        echo "Путь к файлу: $filePath\n";
        echo "Реальный путь: " . (realpath($filePath) ?: 'не найден') . "\n";
        echo "Файл существует: " . (file_exists($filePath) ? 'ДА' : 'НЕТ') . "\n";
        echo "Файл читаемый: " . (is_readable($filePath) ? 'ДА' : 'НЕТ') . "\n";
        
        if (file_exists($filePath)) {
            $size = filesize($filePath);
            echo "Размер файла: " . round($size / 1024 / 1024, 2) . " MB\n";
        }
        
        echo "\nПопытка загрузки данных...\n";
        $loader = new DataLoader($config);
        
        // Тестовая загрузка небольшой области
        $bbox = "37.3,55.5,37.8,56.0";
        $start = microtime(true);
        $data = $loader->getAccidents($bbox, null, null, 100);
        $time = microtime(true) - $start;
        
        echo "Загружено записей: " . count($data) . "\n";
        echo "Время загрузки: " . round($time, 2) . " сек\n";
        
        if (count($data) > 0) {
            echo "\nПервая запись:\n";
            print_r($data[0]);
        }
    } else {
        echo "Режим БД - проверка подключения...\n";
        require_once __DIR__ . '/db.php';
        $pdo = getDB();
        echo "Подключение к БД успешно\n";
    }
    
    echo "\n=== ТЕСТ ЗАВЕРШЕН ===\n";
    
} catch (Exception $e) {
    echo "ОШИБКА: " . $e->getMessage() . "\n";
    echo "Файл: " . $e->getFile() . "\n";
    echo "Строка: " . $e->getLine() . "\n";
    echo "Трассировка:\n" . $e->getTraceAsString() . "\n";
}
