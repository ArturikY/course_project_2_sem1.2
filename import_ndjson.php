<?php
/**
 * Скрипт импорта данных из NDJSON файла в MySQL
 * Использование: php import_ndjson.php [ndjson_file] [db_name]
 */

// Настройки подключения к БД (измени под свой XAMPP)
$db_config = [
    'host' => 'localhost',
    'user' => 'root',
    'pass' => '', // Обычно пустой пароль в XAMPP
    'charset' => 'utf8mb4'
];

// Параметры командной строки
$ndjson_file = $argv[1] ?? __DIR__ . '/moskva.ndjson';
$db_name = $argv[2] ?? 'dtp_analysis';

// Проверка файла
if (!file_exists($ndjson_file)) {
    die("Ошибка: файл $ndjson_file не найден\n");
}

echo "Импорт данных из: $ndjson_file\n";
echo "База данных: $db_name\n\n";

// Функция для получения PDO соединения
function getPDO($db_config, $db_name) {
    $dsn = "mysql:host={$db_config['host']};charset={$db_config['charset']}";
    $pdo = new PDO($dsn, $db_config['user'], $db_config['pass'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::MYSQL_ATTR_LOCAL_INFILE => true,
    ]);
    $pdo->exec("USE `$db_name`");
    // Увеличиваем max_allowed_packet для сессии
    try {
        $pdo->exec("SET SESSION max_allowed_packet = 67108864"); // 64MB
    } catch (PDOException $e) {
        // Игнорируем если нет прав
    }
    return $pdo;
}

// Подключение к MySQL
try {
    // Создание БД если не существует
    $dsn = "mysql:host={$db_config['host']};charset={$db_config['charset']}";
    $pdo_temp = new PDO($dsn, $db_config['user'], $db_config['pass'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
    $pdo_temp->exec("CREATE DATABASE IF NOT EXISTS `$db_name` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    
    // Подключение к БД
    $pdo = getPDO($db_config, $db_name);
    
    echo "Подключение к БД успешно\n";
} catch (PDOException $e) {
    die("Ошибка подключения к БД: " . $e->getMessage() . "\n");
}

// Создание таблицы (если не существует)
$schema_file = __DIR__ . '/schema.sql';
if (file_exists($schema_file)) {
    echo "Создание таблиц из schema.sql...\n";
    $schema = file_get_contents($schema_file);
    // Удаляем комментарии CREATE DATABASE и USE если есть
    $schema = preg_replace('/^--.*$/m', '', $schema);
    $schema = preg_replace('/CREATE DATABASE.*?;/i', '', $schema);
    $schema = preg_replace('/USE.*?;/i', '', $schema);
    
    // Выполняем по одному запросу
    $statements = array_filter(array_map('trim', explode(';', $schema)));
    foreach ($statements as $stmt) {
        if (!empty($stmt)) {
            try {
                $pdo->exec($stmt);
            } catch (PDOException $e) {
                // Игнорируем ошибки "таблица уже существует"
                if (strpos($e->getMessage(), 'already exists') === false) {
                    echo "Предупреждение: " . $e->getMessage() . "\n";
                }
            }
        }
    }
    echo "Таблицы готовы\n\n";
}

// Настройки импорта
$batchSize = 100; // Уменьшено из-за больших JSON полей
$rows = [];
$total = 0;
$errors = 0;
$start_time = microtime(true);

// Открытие файла
$handle = fopen($ndjson_file, 'r');
if (!$handle) {
    die("Не удалось открыть файл $ndjson_file\n");
}

echo "Начало импорта...\n";
$line_num = 0;

while (($line = fgets($handle)) !== false) {
    $line_num++;
    $line = trim($line);
    if (empty($line)) continue;
    
    $feat = json_decode($line, true);
    if (!$feat || json_last_error() !== JSON_ERROR_NONE) {
        $errors++;
        if ($errors <= 5) {
            echo "Ошибка парсинга JSON на строке $line_num: " . json_last_error_msg() . "\n";
        }
        continue;
    }
    
    $props = $feat['properties'] ?? [];
    $geometry = $feat['geometry'] ?? [];
    $coords = $geometry['coordinates'] ?? null;
    
    // Проверка координат
    if (!$coords || !is_array($coords) || count($coords) < 2) {
        $errors++;
        continue;
    }
    
    // Извлечение данных
    $id = isset($props['id']) ? (int)$props['id'] : null;
    if (!$id) {
        $errors++;
        continue;
    }
    
    $dt = isset($props['datetime']) ? $props['datetime'] : null;
    // Нормализация даты
    if ($dt) {
        $dt = date('Y-m-d H:i:s', strtotime($dt));
        if ($dt === '1970-01-01 00:00:00') $dt = null;
    }
    
    $lon = (float)$coords[0];
    $lat = (float)$coords[1];
    
    // Проверка валидности координат (Москва примерно 55.5-55.9, 37.3-37.8)
    if ($lat < 55 || $lat > 56 || $lon < 37 || $lon > 38) {
        $errors++;
        continue;
    }
    
    $category = isset($props['category']) ? mb_substr($props['category'], 0, 100) : null;
    $severity = isset($props['severity']) ? mb_substr($props['severity'], 0, 50) : null;
    $region = isset($props['region']) ? mb_substr($props['region'], 0, 100) : null;
    $light = isset($props['light']) ? mb_substr($props['light'], 0, 100) : null;
    $address = isset($props['address']) ? mb_substr($props['address'], 0, 255) : null;
    
    // JSON поля
    $tags = isset($props['tags']) && is_array($props['tags']) 
        ? json_encode($props['tags'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null;
    $weather = isset($props['weather']) && is_array($props['weather']) 
        ? json_encode($props['weather'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null;
    $nearby = isset($props['nearby']) && is_array($props['nearby']) 
        ? json_encode($props['nearby'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null;
    $vehicles = isset($props['vehicles']) && is_array($props['vehicles']) 
        ? json_encode($props['vehicles'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null;
    
    // Все остальные поля в extra
    $extra = json_encode($props, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    
    $rows[] = [
        $id, $dt, $lat, $lon, $category, $severity, $region, $light, $address,
        $tags, $weather, $nearby, $vehicles, $extra
    ];
    
    // Batch insert
    if (count($rows) >= $batchSize) {
        $inserted = insertBatch($pdo, $rows, $db_config, $db_name);
        $total += $inserted;
        $rows = [];
        
        if ($total % 1000 == 0) {
            $elapsed = microtime(true) - $start_time;
            $rate = $total / $elapsed;
            echo sprintf("Обработано: %d записей (%.1f зап/сек)\n", $total, $rate);
        }
    }
}

// Остаток
if (!empty($rows)) {
    $inserted = insertBatch($pdo, $rows, $db_config, $db_name);
    $total += $inserted;
}

fclose($handle);

$elapsed = microtime(true) - $start_time;
echo "\n";
echo "========================================\n";
echo "Импорт завершен!\n";
echo "Успешно импортировано: $total записей\n";
echo "Ошибок: $errors\n";
echo "Время: " . round($elapsed, 2) . " секунд\n";
echo "Скорость: " . round($total / $elapsed, 1) . " записей/сек\n";
echo "========================================\n";

/**
 * Batch insert в БД с обработкой ошибок и переподключением
 */
function insertBatch(&$pdo, array $rows, $db_config, $db_name) {
    if (empty($rows)) return 0;
    
    // Если батч слишком большой, разбиваем пополам
    if (count($rows) > 200) {
        $mid = (int)(count($rows) / 2);
        $first = array_slice($rows, 0, $mid);
        $second = array_slice($rows, $mid);
        return insertBatch($pdo, $first, $db_config, $db_name) + 
               insertBatch($pdo, $second, $db_config, $db_name);
    }
    
    $placeholders = [];
    $params = [];
    
    foreach ($rows as $row) {
        [$id, $dt, $lat, $lon, $category, $severity, $region, $light, $address,
         $tags, $weather, $nearby, $vehicles, $extra] = $row;
        
        $placeholders[] = "(?,?,?,?,?,?,?,?,?,?,?,?,?,?,ST_SRID(POINT(?, ?), 4326))";
        
        $params[] = $id;
        $params[] = $dt;
        $params[] = $lat;
        $params[] = $lon;
        $params[] = $category;
        $params[] = $severity;
        $params[] = $region;
        $params[] = $light;
        $params[] = $address;
        $params[] = $tags;
        $params[] = $weather;
        $params[] = $nearby;
        $params[] = $vehicles;
        $params[] = $extra;
        $params[] = $lon; // POINT(lon, lat) - порядок важен!
        $params[] = $lat;
    }
    
    $sql = "INSERT INTO accidents
        (id, dt, lat, lon, category, severity, region, light, address, tags, weather, nearby, vehicles, extra, geom)
        VALUES " . implode(',', $placeholders) . "
        ON DUPLICATE KEY UPDATE 
            dt=VALUES(dt), 
            category=VALUES(category), 
            severity=VALUES(severity), 
            region=VALUES(region), 
            light=VALUES(light), 
            address=VALUES(address), 
            tags=VALUES(tags), 
            weather=VALUES(weather), 
            nearby=VALUES(nearby), 
            vehicles=VALUES(vehicles), 
            extra=VALUES(extra), 
            geom=VALUES(geom)";
    
    $maxRetries = 3;
    $retry = 0;
    
    while ($retry < $maxRetries) {
        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            return count($rows);
        } catch (PDOException $e) {
            $errorMsg = $e->getMessage();
            $errorCode = $e->getCode();
            
            // Ошибка размера пакета - разбиваем батч пополам
            if (strpos($errorMsg, 'max_allowed_packet') !== false || 
                strpos($errorMsg, 'packet bigger') !== false) {
                if (count($rows) > 10) {
                    $mid = (int)(count($rows) / 2);
                    $first = array_slice($rows, 0, $mid);
                    $second = array_slice($rows, $mid);
                    return insertBatch($pdo, $first, $db_config, $db_name) + 
                           insertBatch($pdo, $second, $db_config, $db_name);
                } else {
                    // Если даже 10 записей не влезают, вставляем по одной
                    $inserted = 0;
                    foreach ($rows as $singleRow) {
                        $inserted += insertBatch($pdo, [$singleRow], $db_config, $db_name);
                    }
                    return $inserted;
                }
            }
            
            // Ошибка "MySQL server has gone away" - переподключаемся
            if (strpos($errorMsg, 'gone away') !== false || 
                strpos($errorMsg, 'Communication link failure') !== false ||
                $errorCode == 2006 || $errorCode == '08S01') {
                $retry++;
                if ($retry < $maxRetries) {
                    echo "Переподключение к БД (попытка $retry/$maxRetries)...\n";
                    $pdo = getPDO($db_config, $db_name);
                    continue;
                }
            }
            
            // Другие ошибки - выводим и возвращаем 0
            if ($retry == 0) {
                echo "Ошибка batch insert: $errorMsg\n";
            }
            return 0;
        }
    }
    
    return 0;
}

