<?php
/**
 * API endpoint для получения данных о ДТП
 * GET /api/accidents.php?bbox=minLon,minLat,maxLon,maxLat&from=YYYY-MM-DD&to=YYYY-MM-DD
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');

require_once __DIR__ . '/db.php';

$pdo = getDB();

// Параметры запроса
$bbox = isset($_GET['bbox']) ? $_GET['bbox'] : null;
$from = isset($_GET['from']) ? $_GET['from'] : null;
$to = isset($_GET['to']) ? $_GET['to'] : null;
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 1000;

// Валидация bbox
if (!$bbox) {
    http_response_code(400);
    die(json_encode(['error' => 'bbox parameter is required (format: minLon,minLat,maxLon,maxLat)']));
}

$bbox_parts = explode(',', $bbox);
if (count($bbox_parts) !== 4) {
    http_response_code(400);
    die(json_encode(['error' => 'Invalid bbox format. Use: minLon,minLat,maxLon,maxLat']));
}

$minLon = (float)$bbox_parts[0];
$minLat = (float)$bbox_parts[1];
$maxLon = (float)$bbox_parts[2];
$maxLat = (float)$bbox_parts[3];

// Проверка размера bbox
if (($maxLon - $minLon) > 0.1 || ($maxLat - $minLat) > 0.1) {
    http_response_code(400);
    die(json_encode(['error' => 'Bbox too large. Maximum size: 0.1 degrees']));
}

try {
    // Построение запроса
    $sql = "SELECT 
        id, dt, lat, lon, 
        ST_AsText(geom) as geom_text,
        category, severity, region, light, address,
        tags, weather, nearby, vehicles
    FROM accidents
    WHERE lat BETWEEN ? AND ? 
      AND lon BETWEEN ? AND ?";
    
    $params = [$minLat, $maxLat, $minLon, $maxLon];
    
    // Фильтр по дате
    if ($from) {
        $sql .= " AND dt >= ?";
        $params[] = $from . ' 00:00:00';
    }
    if ($to) {
        $sql .= " AND dt <= ?";
        $params[] = $to . ' 23:59:59';
    }
    
    $sql .= " ORDER BY dt DESC LIMIT ?";
    $params[] = $limit;
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $results = $stmt->fetchAll();
    
    // Формируем GeoJSON
    $features = [];
    foreach ($results as $row) {
        // Парсим координаты из geom_text (POINT(lon lat))
        preg_match('/POINT\(([\d.]+) ([\d.]+)\)/', $row['geom_text'], $matches);
        $lon = isset($matches[1]) ? (float)$matches[1] : $row['lon'];
        $lat = isset($matches[2]) ? (float)$matches[2] : $row['lat'];
        
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
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}

