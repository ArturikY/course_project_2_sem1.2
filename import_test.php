<?php
/**
 * Тестовый импорт первых N записей из NDJSON файла в MySQL
 * Использование: php import_test.php [ndjson_file] [db_name] [limit]
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
$limit = isset($argv[3]) ? (int)$argv[3] : 100; // По умолчанию 100 записей

// Проверка файла
if (!file_exists($ndjson_file)) {
    die("Ошибка: файл $ndjson_file не найден\n");
}

echo "=== ТЕСТОВЫЙ ИМПОРТ ===\n";
echo "Файл: $ndjson_file\n";
echo "База данных: $db_name\n";
echo "Лимит записей: $limit\n\n";

// Подключение к MySQL
try {
    $dsn = "mysql:host={$db_config['host']};charset={$db_config['charset']}";
    $pdo_temp = new PDO($dsn, $db_config['user'], $db_config['pass'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
    $pdo_temp->exec("CREATE DATABASE IF NOT EXISTS `$db_name` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    
    $pdo = new PDO($dsn, $db_config['user'], $db_config['pass'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
    $pdo->exec("USE `$db_name`");
    
    // Увеличиваем max_allowed_packet для сессии
    try {
        $pdo->exec("SET SESSION max_allowed_packet = 67108864"); // 64MB
    } catch (PDOException $e) {
        // Игнорируем если нет прав
    }
    
    echo "Подключение к БД успешно\n";
} catch (PDOException $e) {
    die("Ошибка подключения к БД: " . $e->getMessage() . "\n");
}

// Создание таблицы (если не существует)
$schema_file = __DIR__ . '/schema.sql';
if (file_exists($schema_file)) {
    echo "Проверка таблиц...\n";
    $schema = file_get_contents($schema_file);
    $schema = preg_replace('/^--.*$/m', '', $schema);
    $schema = preg_replace('/CREATE DATABASE.*?;/i', '', $schema);
    $schema = preg_replace('/USE.*?;/i', '', $schema);
    
    $statements = array_filter(array_map('trim', explode(';', $schema)));
    foreach ($statements as $stmt) {
        if (!empty($stmt)) {
            try {
                $pdo->exec($stmt);
            } catch (PDOException $e) {
                if (strpos($e->getMessage(), 'already exists') === false) {
                    echo "Предупреждение: " . $e->getMessage() . "\n";
                }
            }
        }
    }
    echo "Таблицы готовы\n\n";
}

// Настройки импорта
$total = 0;
$errors = 0;
$start_time = microtime(true);

// Открытие файла
$handle = fopen($ndjson_file, 'r');
if (!$handle) {
    die("Не удалось открыть файл $ndjson_file\n");
}

echo "Начало импорта (первые $limit записей)...\n\n";

$line_num = 0;
$inserted_count = 0;

// Подготовленный запрос для одной записи
$sql = "INSERT INTO accidents
    (id, dt, lat, lon, category, severity, region, light, address, tags, weather, nearby, vehicles, extra, geom)
    VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,ST_SRID(POINT(?, ?), 4326))
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

$stmt = $pdo->prepare($sql);

while (($line = fgets($handle)) !== false && $inserted_count < $limit) {
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
    
    // Поле extra = NULL (убрано для уменьшения размера)
    $extra = null;
    
    // Вставка одной записи
    try {
        $params = [
            $id, $dt, $lat, $lon, $category, $severity, $region, $light, $address,
            $tags, $weather, $nearby, $vehicles, $extra,
            $lon, $lat
        ];
        
        $stmt->execute($params);
        $inserted_count++;
        $total++;
        
        if ($inserted_count % 10 == 0) {
            echo "Импортировано: $inserted_count/$limit записей\n";
        }
    } catch (PDOException $e) {
        $errors++;
        echo "Ошибка вставки записи ID $id (строка $line_num): " . $e->getMessage() . "\n";
        if ($errors > 10) {
            echo "Слишком много ошибок, остановка...\n";
            break;
        }
    }
}

fclose($handle);

$elapsed = microtime(true) - $start_time;
echo "\n";
echo "========================================\n";
echo "ТЕСТОВЫЙ ИМПОРТ ЗАВЕРШЕН!\n";
echo "Успешно импортировано: $total записей\n";
echo "Ошибок: $errors\n";
echo "Время: " . round($elapsed, 2) . " секунд\n";
if ($total > 0) {
    echo "Скорость: " . round($total / $elapsed, 1) . " записей/сек\n";
}
echo "========================================\n";

// Проверка данных в БД
if ($total > 0) {
    echo "\nПроверка данных в БД:\n";
    try {
        $check = $pdo->query("SELECT COUNT(*) as cnt FROM accidents");
        $result = $check->fetch(PDO::FETCH_ASSOC);
        echo "Всего записей в таблице: " . $result['cnt'] . "\n";
        
        $sample = $pdo->query("SELECT id, dt, category, severity, region FROM accidents LIMIT 5");
        echo "\nПримеры записей:\n";
        while ($row = $sample->fetch(PDO::FETCH_ASSOC)) {
            echo "  ID: {$row['id']}, Дата: {$row['dt']}, Категория: {$row['category']}, Район: {$row['region']}\n";
        }
    } catch (PDOException $e) {
        echo "Ошибка проверки: " . $e->getMessage() . "\n";
    }
}

