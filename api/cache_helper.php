<?php
/**
 * Вспомогательные функции для кэширования API ответов
 */

/**
 * Получить путь к файлу кэша
 */
function getCachePath($key, $config) {
    $cacheDir = $config['cache']['dir'] ?? __DIR__ . '/../cache';
    
    // Создаем директорию, если её нет
    if (!is_dir($cacheDir)) {
        @mkdir($cacheDir, 0755, true);
    }
    
    // Создаем безопасное имя файла из ключа
    $safeKey = md5($key);
    return $cacheDir . '/' . $safeKey . '.json';
}

/**
 * Получить данные из кэша
 */
function getCache($key, $config) {
    if (!($config['cache']['enabled'] ?? true)) {
        return null;
    }
    
    $cachePath = getCachePath($key, $config);
    $ttl = $config['cache']['ttl'] ?? 3600;
    
    if (!file_exists($cachePath)) {
        return null;
    }
    
    // Проверяем время жизни кэша
    $fileTime = filemtime($cachePath);
    if (time() - $fileTime > $ttl) {
        @unlink($cachePath);
        return null;
    }
    
    // Читаем кэш
    $content = @file_get_contents($cachePath);
    if ($content === false) {
        return null;
    }
    
    $data = json_decode($content, true);
    return $data;
}

/**
 * Сохранить данные в кэш
 */
function setCache($key, $data, $config) {
    if (!($config['cache']['enabled'] ?? true)) {
        return false;
    }
    
    $cachePath = getCachePath($key, $config);
    $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    
    return @file_put_contents($cachePath, $json) !== false;
}

/**
 * Очистить весь кэш
 */
function clearCache($config) {
    $cacheDir = $config['cache']['dir'] ?? __DIR__ . '/../cache';
    
    if (!is_dir($cacheDir)) {
        return;
    }
    
    $files = glob($cacheDir . '/*.json');
    foreach ($files as $file) {
        @unlink($file);
    }
}





