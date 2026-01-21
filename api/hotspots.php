<?php
/**
 * API endpoint для получения "горячих" участков (опасных зон)
 * GET /api/hotspots.php?bbox=minLon,minLat,maxLon,maxLat&period=30d&threshold=5
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/config.php';

$pdo = getDB();
$config = require __DIR__ . '/config.php';

// Параметры запроса
$bbox = isset($_GET['bbox']) ? $_GET['bbox'] : null;
$period = isset($_GET['period']) ? $_GET['period'] : '30d'; // 7d, 30d, 90d, 365d
$threshold = isset($_GET['threshold']) ? (int)$_GET['threshold'] : $config['api']['hotspot_threshold'];
$gridSize = isset($_GET['grid']) ? (int)$_GET['grid'] : $config['api']['grid_size_meters'];

// Валидация bbox
if (!$bbox) {
    http_response_code(400);
    die(json_encode(['error' => 'bbox parameter is required']));
}

$bbox_parts = explode(',', $bbox);
if (count($bbox_parts) !== 4) {
    http_response_code(400);
    die(json_encode(['error' => 'Invalid bbox format']));
}

$minLon = (float)$bbox_parts[0];
$minLat = (float)$bbox_parts[1];
$maxLon = (float)$bbox_parts[2];
$maxLat = (float)$bbox_parts[3];

// Парсим период
$days = 30;
if (preg_match('/(\d+)d/', $period, $matches)) {
    $days = (int)$matches[1];
}

$dateFrom = date('Y-m-d', strtotime("-$days days"));

try {
    // Простой анализ по сетке: делим bbox на квадраты и считаем ДТП в каждом
    // Размер ячейки в градусах (примерно)
    $latStep = $gridSize / 111000; // 1 градус ≈ 111 км
    $lonStep = $gridSize / (111000 * cos(deg2rad(($minLat + $maxLat) / 2)));
    
    $cells = [];
    $lat = $minLat;
    
    while ($lat < $maxLat) {
        $lon = $minLon;
        while ($lon < $maxLon) {
            $cellMinLat = $lat;
            $cellMaxLat = min($lat + $latStep, $maxLat);
            $cellMinLon = $lon;
            $cellMaxLon = min($lon + $lonStep, $maxLon);
            
            // Подсчет ДТП в ячейке
            $sql = "SELECT COUNT(*) as cnt, 
                    SUM(CASE WHEN severity IN ('Тяжелый', 'Смертельный') THEN 1 ELSE 0 END) as severe_cnt
                    FROM accidents
                    WHERE lat BETWEEN ? AND ? 
                      AND lon BETWEEN ? AND ?
                      AND dt >= ?";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$cellMinLat, $cellMaxLat, $cellMinLon, $cellMaxLon, $dateFrom]);
            $result = $stmt->fetch();
            
            $count = (int)$result['cnt'];
            $severeCount = (int)$result['severe_cnt'];
            
            if ($count >= $threshold) {
                $centerLat = ($cellMinLat + $cellMaxLat) / 2;
                $centerLon = ($cellMinLon + $cellMaxLon) / 2;
                
                // Определяем уровень опасности
                $riskLevel = 'low';
                if ($count >= 20) {
                    $riskLevel = 'high';
                } elseif ($count >= 10) {
                    $riskLevel = 'medium';
                }
                
                $cells[] = [
                    'type' => 'Feature',
                    'geometry' => [
                        'type' => 'Polygon',
                        'coordinates' => [[
                            [$cellMinLon, $cellMinLat],
                            [$cellMaxLon, $cellMinLat],
                            [$cellMaxLon, $cellMaxLat],
                            [$cellMinLon, $cellMaxLat],
                            [$cellMinLon, $cellMinLat]
                        ]]
                    ],
                    'properties' => [
                        'count' => $count,
                        'severe_count' => $severeCount,
                        'risk_level' => $riskLevel,
                        'center' => [$centerLon, $centerLat]
                    ]
                ];
            }
            
            $lon += $lonStep;
        }
        $lat += $latStep;
    }
    
    $geojson = [
        'type' => 'FeatureCollection',
        'features' => $cells
    ];
    
    echo json_encode($geojson, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}

