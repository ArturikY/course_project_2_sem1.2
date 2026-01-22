<?php
/**
 * Универсальный загрузчик данных (БД или файл)
 */

require_once __DIR__ . '/config.php';

class DataLoader {
    private $config;
    private $dataSource;
    private $cache = null;
    private $cacheTime = 0;
    
    // Статический кэш для загрузки NDJSON один раз
    private static $accidentsData = null;
    private static $accidentsDataLoaded = false;
    
    public function __construct($config) {
        $this->config = $config;
        $this->dataSource = $config['data_source'];
    }
    
    /**
     * Загрузить данные ДТП из файла один раз (singleton)
     */
    private function loadAccidentsDataOnce() {
        // Позволяем сбросить кэш через GET параметр (для отладки)
        if (isset($_GET['reset_cache']) && $_GET['reset_cache'] == '1') {
            self::$accidentsData = null;
            self::$accidentsDataLoaded = false;
            error_log("[DataLoader] Кэш сброшен по запросу reset_cache=1");
        }
        
        if (self::$accidentsDataLoaded && self::$accidentsData !== null) {
            error_log(sprintf(
                "[DataLoader] Используются закэшированные данные: %d записей",
                count(self::$accidentsData)
            ));
            return self::$accidentsData;
        }
        
        $fileConfig = $this->config['file'];
        $filePath = $fileConfig['ndjson_path'];
        
        if (!file_exists($filePath) || !is_readable($filePath)) {
            throw new Exception("Файл недоступен: $filePath");
        }
        
        // Увеличиваем лимиты для больших файлов
        ini_set('memory_limit', '512M');
        ini_set('max_execution_time', '300');
        
        // Засекаем время начала загрузки
        $startTime = microtime(true);
        
        $data = [];
        $handle = fopen($filePath, 'r');
        if (!$handle) {
            throw new Exception("Не удалось открыть файл: $filePath");
        }
        
        $lineNum = 0;
        while (($line = fgets($handle)) !== false) {
            $lineNum++;
            $line = trim($line);
            if (empty($line)) continue;
            
            $feat = json_decode($line, true);
            if (!$feat) continue;
            
            $props = $feat['properties'] ?? [];
            $geometry = $feat['geometry'] ?? [];
            $coords = $geometry['coordinates'] ?? null;
            
            if (!$coords || !is_array($coords) || count($coords) < 2) {
                continue;
            }
            
            $lon = (float)$coords[0];
            $lat = (float)$coords[1];
            
            // Проверка координат (Москва)
            if ($lat < 55 || $lat > 56 || $lon < 37 || $lon > 38) {
                continue;
            }
            
            $id = isset($props['id']) ? (int)$props['id'] : null;
            if (!$id) continue;
            
            $dt = isset($props['datetime']) ? $props['datetime'] : null;
            if ($dt) {
                $dtParsed = strtotime($dt);
                if ($dtParsed !== false) {
                    $dt = date('Y-m-d H:i:s', $dtParsed);
                } else {
                    $dt = null;
                }
            }
            
            // Сохраняем только минимальные данные для оптимизации
            $data[] = [
                'id' => $id,
                'dt' => $dt,
                'lat' => $lat,
                'lon' => $lon,
                'severity' => isset($props['severity']) ? substr($props['severity'], 0, 50) : null,
                'category' => isset($props['category']) ? substr($props['category'], 0, 100) : null,
                'region' => isset($props['region']) ? substr($props['region'], 0, 100) : null,
                'light' => isset($props['light']) ? substr($props['light'], 0, 100) : null,
                'address' => isset($props['address']) ? substr($props['address'], 0, 255) : null,
                'tags' => isset($props['tags']) && is_array($props['tags']) 
                    ? json_encode($props['tags'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
                'weather' => isset($props['weather']) && is_array($props['weather']) 
                    ? json_encode($props['weather'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
                'nearby' => isset($props['nearby']) && is_array($props['nearby']) 
                    ? json_encode($props['nearby'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
            ];
        }
        
        fclose($handle);
        
        // Вычисляем время загрузки
        $loadTime = microtime(true) - $startTime;
        $dataCount = count($data);
        
        // Логируем информацию о загрузке
        error_log(sprintf(
            "[DataLoader] Загружено %d записей за %.2f сек (%.0f зап/сек)",
            $dataCount,
            $loadTime,
            $dataCount / max($loadTime, 0.001)
        ));
        
        self::$accidentsData = $data;
        self::$accidentsDataLoaded = true;
        
        return $data;
    }
    
    /**
     * Получить данные о ДТП с фильтрацией
     */
    public function getAccidents($bbox, $from = null, $to = null, $limit = 1000) {
        if ($this->dataSource === 'database') {
            return $this->getAccidentsFromDB($bbox, $from, $to, $limit);
        } else {
            return $this->getAccidentsFromFile($bbox, $from, $to, $limit);
        }
    }
    
    /**
     * Получить данные из БД
     */
    private function getAccidentsFromDB($bbox, $from, $to, $limit) {
        require_once __DIR__ . '/db.php';
        $pdo = getDB();
        
        $bbox_parts = array_map('trim', explode(',', $bbox));
        $minLon = (float)$bbox_parts[0];
        $minLat = (float)$bbox_parts[1];
        $maxLon = (float)$bbox_parts[2];
        $maxLat = (float)$bbox_parts[3];
        
        $sql = "SELECT 
            id, dt, lat, lon, 
            category, severity, region, light, address,
            tags, weather, nearby, vehicles
        FROM accidents
        WHERE lat BETWEEN ? AND ? 
          AND lon BETWEEN ? AND ?";
        
        $params = [$minLat, $maxLat, $minLon, $maxLon];
        
        if ($from) {
            $sql .= " AND dt >= ?";
            $params[] = $from . ' 00:00:00';
        }
        if ($to) {
            $sql .= " AND dt <= ?";
            $params[] = $to . ' 23:59:59';
        }
        
        $limit = max(1, min(10000, (int)$limit));
        $sql .= " ORDER BY dt DESC LIMIT " . $limit;
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Получить данные из файла (использует загруженные данные из памяти)
     */
    private function getAccidentsFromFile($bbox, $from, $to, $limit) {
        // Парсим bbox один раз
        $bbox_parts = array_map('trim', explode(',', $bbox));
        $minLon = (float)$bbox_parts[0];
        $minLat = (float)$bbox_parts[1];
        $maxLon = (float)$bbox_parts[2];
        $maxLat = (float)$bbox_parts[3];
        
        // Загружаем данные один раз
        $allData = $this->loadAccidentsDataOnce();
        
        // Фильтруем данные
        $filtered = [];
        $count = 0;
        
        foreach ($allData as $item) {
            if ($count >= $limit) break;
            
            $lat = $item['lat'];
            $lon = $item['lon'];
            
            // Быстрая проверка bbox
            if ($lat < $minLat || $lat > $maxLat || $lon < $minLon || $lon > $maxLon) {
                continue;
            }
            
            // Фильтр по дате
            if ($from && $item['dt'] && $item['dt'] < $from . ' 00:00:00') {
                continue;
            }
            if ($to && $item['dt'] && $item['dt'] > $to . ' 23:59:59') {
                continue;
            }
            
            $filtered[] = $item;
            $count++;
        }
        
        return $filtered;
    }
    
    /**
     * Загрузить данные из NDJSON файла
     */
    private function loadFromFile() {
        $fileConfig = $this->config['file'];
        $filePath = $fileConfig['ndjson_path'];
        
        // Увеличиваем лимиты для больших файлов
        ini_set('memory_limit', '512M');
        ini_set('max_execution_time', '300');
        
        // Проверка кэша
        if ($fileConfig['cache_enabled'] && $this->cache !== null) {
            $cacheAge = time() - $this->cacheTime;
            if ($cacheAge < $fileConfig['cache_ttl']) {
                return $this->cache;
            }
        }
        
        // Проверка существования файла
        if (!file_exists($filePath)) {
            $realPath = realpath($filePath);
            throw new Exception("NDJSON файл не найден: $filePath (реальный путь: " . ($realPath ?: 'не найден') . "). Проверьте путь в api/config.php");
        }
        
        if (!is_readable($filePath)) {
            throw new Exception("Файл недоступен для чтения: $filePath");
        }
        
        $data = [];
        $handle = fopen($filePath, 'r');
        
        if (!$handle) {
            throw new Exception("Не удалось открыть файл: $filePath");
        }
        
        $lineNum = 0;
        while (($line = fgets($handle)) !== false) {
            $lineNum++;
            $line = trim($line);
            if (empty($line)) continue;
            
            $feat = json_decode($line, true);
            if (!$feat) {
                // Пропускаем невалидные строки
                continue;
            }
            
            $props = $feat['properties'] ?? [];
            $geometry = $feat['geometry'] ?? [];
            $coords = $geometry['coordinates'] ?? null;
            
            if (!$coords || !is_array($coords) || count($coords) < 2) {
                continue;
            }
            
            $id = isset($props['id']) ? (int)$props['id'] : null;
            if (!$id) continue;
            
            $lon = (float)$coords[0];
            $lat = (float)$coords[1];
            
            // Проверка координат (Москва)
            if ($lat < 55 || $lat > 56 || $lon < 37 || $lon > 38) {
                continue;
            }
            
            $dt = isset($props['datetime']) ? $props['datetime'] : null;
            if ($dt) {
                $dtParsed = strtotime($dt);
                if ($dtParsed !== false) {
                    $dt = date('Y-m-d H:i:s', $dtParsed);
                } else {
                    $dt = null;
                }
            }
            
            // Функция для безопасной обрезки строк (работает с кириллицей)
            $safeSubstr = function($str, $start, $length) {
                if (function_exists('mb_substr')) {
                    return mb_substr($str, $start, $length);
                } else {
                    // Fallback на substr (может обрезать кириллицу неправильно, но работает)
                    return substr($str, $start, $length);
                }
            };
            
            $data[] = [
                'id' => $id,
                'dt' => $dt,
                'lat' => $lat,
                'lon' => $lon,
                'category' => isset($props['category']) ? $safeSubstr($props['category'], 0, 100) : null,
                'severity' => isset($props['severity']) ? $safeSubstr($props['severity'], 0, 50) : null,
                'region' => isset($props['region']) ? $safeSubstr($props['region'], 0, 100) : null,
                'light' => isset($props['light']) ? $safeSubstr($props['light'], 0, 100) : null,
                'address' => isset($props['address']) ? $safeSubstr($props['address'], 0, 255) : null,
                'tags' => isset($props['tags']) && is_array($props['tags']) 
                    ? json_encode($props['tags'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
                'weather' => isset($props['weather']) && is_array($props['weather']) 
                    ? json_encode($props['weather'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
                'nearby' => isset($props['nearby']) && is_array($props['nearby']) 
                    ? json_encode($props['nearby'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
                'vehicles' => isset($props['vehicles']) && is_array($props['vehicles']) 
                    ? json_encode($props['vehicles'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
            ];
        }
        
        fclose($handle);
        
        // Сохраняем в кэш
        if ($fileConfig['cache_enabled']) {
            $this->cache = $data;
            $this->cacheTime = time();
        }
        
        return $data;
    }
    
    /**
     * Получить статистику для опасных зон
     */
    public function getHotspotsStats($bbox, $period = '30d', $threshold = 5, $gridSize = 1000) {
        if ($this->dataSource === 'database') {
            return $this->getHotspotsFromDB($bbox, $period, $threshold, $gridSize);
        } else {
            return $this->getHotspotsFromFile($bbox, $period, $threshold, $gridSize);
        }
    }
    
    /**
     * Получить статистику из БД
     */
    private function getHotspotsFromDB($bbox, $period, $threshold, $gridSize) {
        require_once __DIR__ . '/db.php';
        $pdo = getDB();
        
        $bbox_parts = array_map('trim', explode(',', $bbox));
        $minLon = (float)$bbox_parts[0];
        $minLat = (float)$bbox_parts[1];
        $maxLon = (float)$bbox_parts[2];
        $maxLat = (float)$bbox_parts[3];
        
        // Парсим период
        $days = 30;
        if ($period === 'all') {
            $dateFrom = null;
        } elseif (preg_match('/(\d+)d/', $period, $matches)) {
            $days = (int)$matches[1];
            $dateFrom = date('Y-m-d', strtotime("-$days days"));
        } else {
            $dateFrom = null;
        }
        
        $latStep = $gridSize / 111000;
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
                
                if ($dateFrom) {
                    $sql = "SELECT COUNT(*) as cnt, 
                            SUM(CASE WHEN severity IN ('Тяжелый', 'Смертельный') THEN 1 ELSE 0 END) as severe_cnt
                            FROM accidents
                            WHERE lat BETWEEN ? AND ? 
                              AND lon BETWEEN ? AND ?
                              AND dt >= ?";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([$cellMinLat, $cellMaxLat, $cellMinLon, $cellMaxLon, $dateFrom]);
                } else {
                    $sql = "SELECT COUNT(*) as cnt, 
                            SUM(CASE WHEN severity IN ('Тяжелый', 'Смертельный') THEN 1 ELSE 0 END) as severe_cnt
                            FROM accidents
                            WHERE lat BETWEEN ? AND ? 
                              AND lon BETWEEN ? AND ?";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([$cellMinLat, $cellMaxLat, $cellMinLon, $cellMaxLon]);
                }
                
                $result = $stmt->fetch();
                $count = (int)$result['cnt'];
                $severeCount = (int)$result['severe_cnt'];
                
                if ($count >= $threshold) {
                    $cells[] = [
                        'minLat' => $cellMinLat,
                        'maxLat' => $cellMaxLat,
                        'minLon' => $cellMinLon,
                        'maxLon' => $cellMaxLon,
                        'count' => $count,
                        'severeCount' => $severeCount
                    ];
                }
                
                $lon += $lonStep;
            }
            $lat += $latStep;
        }
        
        return $cells;
    }
    
    /**
     * Получить статистику из файла (оптимизированная версия с прямым доступом к ячейкам)
     */
    private function getHotspotsFromFile($bbox, $period, $threshold, $gridSize) {
        // Парсим bbox один раз
        $bbox_parts = array_map('trim', explode(',', $bbox));
        $minLon = (float)$bbox_parts[0];
        $minLat = (float)$bbox_parts[1];
        $maxLon = (float)$bbox_parts[2];
        $maxLat = (float)$bbox_parts[3];
        
        // Парсим период один раз
        $dateFrom = null;
        if ($period !== 'all' && preg_match('/(\d+)d/', $period, $matches)) {
            $days = (int)$matches[1];
            $dateFrom = strtotime("-$days days");
        }
        
        // Вычисляем шаг сетки один раз (кэшируем cos)
        $centerLat = ($minLat + $maxLat) / 2;
        $latStep = $gridSize / 111000;
        $lonStep = $gridSize / (111000 * cos(deg2rad($centerLat)));
        
        // Вычисляем размеры сетки
        $latCells = (int)ceil(($maxLat - $minLat) / $latStep);
        $lonCells = (int)ceil(($maxLon - $minLon) / $lonStep);
        
        // Создаем двумерный массив ячеек для прямого доступа
        $cells = [];
        for ($i = 0; $i < $latCells; $i++) {
            for ($j = 0; $j < $lonCells; $j++) {
                $cellMinLat = $minLat + $i * $latStep;
                $cellMaxLat = min($cellMinLat + $latStep, $maxLat);
                $cellMinLon = $minLon + $j * $lonStep;
                $cellMaxLon = min($cellMinLon + $lonStep, $maxLon);
                
                $cells[$i][$j] = [
                    'minLat' => $cellMinLat,
                    'maxLat' => $cellMaxLat,
                    'minLon' => $cellMinLon,
                    'maxLon' => $cellMaxLon,
                    'count' => 0,
                    'severeCount' => 0
                ];
            }
        }
        
        // Загружаем данные один раз
        $allData = $this->loadAccidentsDataOnce();
        
        // Логируем количество данных для отладки
        error_log(sprintf(
            "[Hotspots] Обработка %d записей ДТП для bbox=%s, gridSize=%d, threshold=%d",
            count($allData),
            $bbox,
            $gridSize,
            $threshold
        ));
        
        $processedCount = 0;
        $inBboxCount = 0;
        
        // Распределяем ДТП по ячейкам (O(1) доступ вместо O(n))
        foreach ($allData as $item) {
            $processedCount++;
            $lat = $item['lat'];
            $lon = $item['lon'];
            
            // Быстрая проверка bbox
            if ($lat < $minLat || $lat > $maxLat || $lon < $minLon || $lon > $maxLon) {
                continue;
            }
            
            $inBboxCount++;
            
            // Фильтр по дате
            if ($dateFrom && $item['dt']) {
                $dtParsed = strtotime($item['dt']);
                if ($dtParsed === false || $dtParsed < $dateFrom) {
                    continue;
                }
            }
            
            // Вычисляем индекс ячейки напрямую (O(1) вместо O(n))
            $latIndex = (int)floor(($lat - $minLat) / $latStep);
            $lonIndex = (int)floor(($lon - $minLon) / $lonStep);
            
            // Проверяем границы массива
            if ($latIndex >= 0 && $latIndex < $latCells && 
                $lonIndex >= 0 && $lonIndex < $lonCells) {
                
                // Прямой доступ к ячейке
                $cells[$latIndex][$lonIndex]['count']++;
                
                $severity = $item['severity'] ?? '';
                if (in_array($severity, ['Тяжелый', 'Смертельный'])) {
                    $cells[$latIndex][$lonIndex]['severeCount']++;
                }
            }
        }
        
        // Фильтруем ячейки по порогу и преобразуем в одномерный массив
        $result = [];
        $cellsWithData = 0;
        $maxCount = 0;
        for ($i = 0; $i < $latCells; $i++) {
            for ($j = 0; $j < $lonCells; $j++) {
                $cell = $cells[$i][$j];
                if ($cell['count'] > 0) {
                    $cellsWithData++;
                    $maxCount = max($maxCount, $cell['count']);
                }
                if ($cell['count'] >= $threshold) {
                    $result[] = $cell;
                }
            }
        }
        
        // Логируем статистику
        error_log(sprintf(
            "[Hotspots] Статистика: обработано %d записей, в bbox %d, ячеек с данными %d, ячеек >= threshold(%d) %d, макс. ДТП в ячейке: %d",
            $processedCount,
            $inBboxCount,
            $cellsWithData,
            $threshold,
            count($result),
            $maxCount
        ));
        
        return $result;
    }
}
