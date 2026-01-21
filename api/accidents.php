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

require_once __DIR__ . '/db.php';

$pdo = getDB();

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
    // Построение запроса
    $sql = "SELECT 
        id, dt, lat, lon, 
        category, severity, region, light, address,
        tags, weather, nearby, vehicles
    FROM accidents
    WHERE lat BETWEEN ? AND ? 
      AND lon BETWEEN ? AND ?";
    
    $params = [$minLat, $maxLat, $minLon, $maxLon];
    
    // Фильтр по дате
    // Приоритет: параметр days, затем from
    if ($days && $days > 0) {
        // Используем количество дней назад
        $sql .= " AND dt >= DATE_SUB(NOW(), INTERVAL ? DAY)";
        $params[] = $days;
    } elseif ($from) {
        $fromDate = strtotime($from);
        $currentDate = time();
        // Если дата валидна и не в будущем
        if ($fromDate !== false && $fromDate <= $currentDate) {
            $sql .= " AND dt >= ?";
            $params[] = $from . ' 00:00:00';
        }
        // Если дата в будущем, просто не применяем фильтр (показываем все данные)
    }
    // Если нет фильтра по дате, показываем все данные (или можно добавить дефолтный фильтр)
    // Для теста уберем фильтр по дате, если days не указан
    if (!$days && !$from) {
        // Не добавляем фильтр - показываем все данные
    }
    if ($to) {
        $toDate = strtotime($to);
        $currentDate = time();
        if ($toDate !== false && $toDate <= $currentDate) {
            $sql .= " AND dt <= ?";
            $params[] = $to . ' 23:59:59';
        }
    }
    
    // LIMIT нельзя использовать как параметр в prepared statements в MariaDB
    // Используем прямое значение, но проверяем его безопасность
    $limit = max(1, min(10000, (int)$limit)); // Ограничиваем от 1 до 10000
    $sql .= " ORDER BY dt DESC LIMIT " . $limit;
    
    // Логирование для отладки (можно убрать в продакшене)
    // error_log("SQL: $sql");
    // error_log("Params: " . print_r($params, true));
    
    $stmt = $pdo->prepare($sql);
    if (!$stmt) {
        throw new Exception("SQL prepare failed: " . implode(", ", $pdo->errorInfo()));
    }
    
    $result = $stmt->execute($params);
    if (!$result) {
        throw new Exception("SQL execute failed: " . implode(", ", $stmt->errorInfo()));
    }
    
    $results = $stmt->fetchAll();
    
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
