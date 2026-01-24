<?php
/**
 * API endpoint для получения данных о ДТП
 * GET /api/accidents.php?bbox=minLon,minLat,maxLon,maxLat&from=YYYY-MM-DD&to=YYYY-MM-DD
 */

// Отключаем вывод ошибок в HTML формате
ini_set('display_errors', 0);
error_reporting(E_ALL);

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');

// Обработка ошибок
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    http_response_code(500);
    die(json_encode(['error' => "PHP Error: $errstr in $errfile:$errline"], JSON_UNESCAPED_UNICODE));
});

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/data_loader.php';
require_once __DIR__ . '/cache_helper.php';

try {
    $config = require __DIR__ . '/config.php';
    $loader = new DataLoader($config);
} catch (Exception $e) {
    http_response_code(500);
    die(json_encode(['error' => 'Config error: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE));
}

// Параметры запроса
$bbox = isset($_GET['bbox']) ? trim($_GET['bbox']) : null;
$from = isset($_GET['from']) ? trim($_GET['from']) : null;
$to = isset($_GET['to']) ? trim($_GET['to']) : null;
$days = isset($_GET['days']) ? (int)$_GET['days'] : null; // Количество дней назад
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 1000;

// Валидация bbox
if (!$bbox) {
    http_response_code(400);
    die(json_encode(['error' => 'bbox parameter is required (format: minLon,minLat,maxLon,maxLat)'], JSON_UNESCAPED_UNICODE));
}

$bbox_parts = array_map('trim', explode(',', $bbox));
if (count($bbox_parts) !== 4) {
    http_response_code(400);
    die(json_encode(['error' => 'Invalid bbox format. Use: minLon,minLat,maxLon,maxLat'], JSON_UNESCAPED_UNICODE));
}

$minLon = (float)$bbox_parts[0];
$minLat = (float)$bbox_parts[1];
$maxLon = (float)$bbox_parts[2];
$maxLat = (float)$bbox_parts[3];

// Проверка валидности координат
if ($minLon >= $maxLon || $minLat >= $maxLat) {
    http_response_code(400);
    die(json_encode(['error' => 'Invalid bbox: min must be less than max'], JSON_UNESCAPED_UNICODE));
}

// Проверка размера bbox (опционально, можно увеличить)
if (($maxLon - $minLon) > 1.0 || ($maxLat - $minLat) > 1.0) {
    http_response_code(400);
    die(json_encode(['error' => 'Bbox too large. Maximum size: 1.0 degrees'], JSON_UNESCAPED_UNICODE));
}

try {
    // Создаем ключ кэша
    $cacheKey = "accidents:" . $bbox . ":" . ($from ?? '') . ":" . ($to ?? '') . ":" . ($days ?? '') . ":" . $limit;
    
    // Пытаемся получить из кэша
    $cached = getCache($cacheKey, $config);
    if ($cached !== null) {
        header('X-Cache: HIT');
        echo json_encode($cached, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }
    
    header('X-Cache: MISS');
    
    // Получаем данные через универсальный загрузчик
    $results = $loader->getAccidents($bbox, $from, $to, $limit);
    
    // Формируем GeoJSON
    $features = [];
    foreach ($results as $row) {
        $lon = (float)$row['lon'];
        $lat = (float)$row['lat'];
        
        $feature = [
            'type' => 'Feature',
            'geometry' => [
                'type' => 'Point',
                'coordinates' => [$lon, $lat]
            ],
            'properties' => [
                'id' => (int)$row['id'],
                'dt' => $row['dt'],
                'category' => $row['category'],
                'severity' => $row['severity'],
                'region' => $row['region'],
                'light' => $row['light'],
                'address' => $row['address'],
                'tags' => $row['tags'] ? json_decode($row['tags'], true) : null,
                'weather' => $row['weather'] ? json_decode($row['weather'], true) : null,
                'nearby' => $row['nearby'] ? json_decode($row['nearby'], true) : null,
            ]
        ];
        $features[] = $feature;
    }
    
    $geojson = [
        'type' => 'FeatureCollection',
        'features' => $features
    ];
    
    // Сохраняем в кэш
    setCache($cacheKey, $geojson, $config);
    
    echo json_encode($geojson, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
    exit;
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Error: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
    exit;
} catch (Error $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Fatal error: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
    exit;
}
