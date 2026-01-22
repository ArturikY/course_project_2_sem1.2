<?php
/**
 * API endpoint для получения "горячих" участков (опасных зон)
 * GET /api/hotspots.php?bbox=minLon,minLat,maxLon,maxLat&period=30d&threshold=5
 */

// Отключаем вывод ошибок в HTML формате
ini_set('display_errors', 0);
error_reporting(E_ALL);

// Устанавливаем заголовки сразу
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');

// Обработка ошибок PHP
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    http_response_code(500);
    die(json_encode(['error' => "PHP Error: $errstr in $errfile:$errline"], JSON_UNESCAPED_UNICODE));
});

// Обработка фатальных ошибок
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        http_response_code(500);
        echo json_encode(['error' => 'Fatal error: ' . $error['message']], JSON_UNESCAPED_UNICODE);
        exit;
    }
});

try {
    require_once __DIR__ . '/config.php';
    require_once __DIR__ . '/data_loader.php';
    require_once __DIR__ . '/cache_helper.php';
} catch (Exception $e) {
    http_response_code(500);
    die(json_encode(['error' => 'Require error: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE));
} catch (Error $e) {
    http_response_code(500);
    die(json_encode(['error' => 'Require fatal error: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE));
}

try {
    $config = require __DIR__ . '/config.php';
    $loader = new DataLoader($config);
} catch (Exception $e) {
    http_response_code(500);
    die(json_encode(['error' => 'Config error: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE));
} catch (Error $e) {
    http_response_code(500);
    die(json_encode(['error' => 'Config fatal error: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE));
}

// Параметры запроса
$bbox = isset($_GET['bbox']) ? trim($_GET['bbox']) : null;
$period = isset($_GET['period']) ? trim($_GET['period']) : '30d';
$threshold = isset($_GET['threshold']) ? (int)$_GET['threshold'] : 5;
$gridSize = isset($_GET['grid']) ? (int)$_GET['grid'] : 1000;

// Валидация bbox
if (!$bbox) {
    http_response_code(400);
    die(json_encode(['error' => 'bbox parameter is required'], JSON_UNESCAPED_UNICODE));
}

$bbox_parts = array_map('trim', explode(',', $bbox));
if (count($bbox_parts) !== 4) {
    http_response_code(400);
    die(json_encode(['error' => 'Invalid bbox format'], JSON_UNESCAPED_UNICODE));
}

$minLon = (float)$bbox_parts[0];
$minLat = (float)$bbox_parts[1];
$maxLon = (float)$bbox_parts[2];
$maxLat = (float)$bbox_parts[3];

try {
    // Создаем ключ кэша
    $cacheKey = "hotspots:" . $bbox . ":" . $period . ":" . $threshold . ":" . $gridSize;
    
    // Пытаемся получить из кэша
    $cached = getCache($cacheKey, $config);
    if ($cached !== null) {
        header('X-Cache: HIT');
        echo json_encode($cached, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }
    
    header('X-Cache: MISS');
    
    // Получаем статистику через универсальный загрузчик
    $cellsData = $loader->getHotspotsStats($bbox, $period, $threshold, $gridSize);
    
    $cells = [];
    foreach ($cellsData as $cell) {
        $count = $cell['count'];
        $severeCount = $cell['severeCount'];
        
        $centerLat = ($cell['minLat'] + $cell['maxLat']) / 2;
        $centerLon = ($cell['minLon'] + $cell['maxLon']) / 2;
        
        // Радиус круга = половина размера ячейки сетки
        // gridSize определяет радиус круга (как было раньше)
        $radiusMeters = $gridSize / 2;
        
        // Рассчитываем площадь зоны в квадратных метрах (π * r²)
        $areaMeters = M_PI * $radiusMeters * $radiusMeters;
        
        // Рассчитываем плотность ДТП (количество на квадратный метр)
        // Для удобства используем коэффициент на 1000 м² (на 0.001 км²)
        $densityPer1000m2 = ($count / $areaMeters) * 1000;
        
        // Определяем уровень опасности по плотности (коэффициент на 1000 м²)
        // Пороги: low 0.1-0.2, medium 0.2-0.3, high > 0.3
        $riskLevel = 'low';
        if ($densityPer1000m2 >= 0.3) {
            $riskLevel = 'high';
        } elseif ($densityPer1000m2 >= 0.2) {
            $riskLevel = 'medium';
        } elseif ($densityPer1000m2 >= 0.1) {
            $riskLevel = 'low';
        } else {
            // Пропускаем зоны с плотностью < 0.1
            continue;
        }
        
        // Зоны с низким уровнем опасности отображаем только при gridSize <= 500м
        if ($riskLevel === 'low' && $gridSize > 500) {
            continue;
        }
        
        // Для круглых зон возвращаем Point с радиусом в properties
        $cells[] = [
            'type' => 'Feature',
            'geometry' => [
                'type' => 'Point',
                'coordinates' => [$centerLon, $centerLat]
            ],
            'properties' => [
                'count' => $count,
                'severe_count' => $severeCount,
                'risk_level' => $riskLevel,
                'density_per_1000m2' => round($densityPer1000m2, 4), // плотность на 1000 м²
                'area_m2' => round($areaMeters, 2), // площадь в м²
                'center' => [$centerLon, $centerLat],
                'radius' => $radiusMeters, // фиксированный радиус в метрах (не зависит от gridSize)
                'bbox' => [
                    'minLon' => $cell['minLon'],
                    'minLat' => $cell['minLat'],
                    'maxLon' => $cell['maxLon'],
                    'maxLat' => $cell['maxLat']
                ]
            ]
        ];
    }
    
    $geojson = [
        'type' => 'FeatureCollection',
        'features' => $cells
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
