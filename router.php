<?php
/**
 * Роутер для PHP встроенного сервера
 * Перенаправляет запросы на соответствующие файлы
 */

$requestUri = $_SERVER['REQUEST_URI'];
$requestPath = parse_url($requestUri, PHP_URL_PATH);

// Убираем начальный слэш
$requestPath = ltrim($requestPath, '/');

// Если запрос к корню или index.html, перенаправляем на frontend/index.html
if ($requestPath === '' || $requestPath === 'index.html' || $requestPath === '/') {
    $file = __DIR__ . '/frontend/index.html';
    if (file_exists($file)) {
        header('Content-Type: text/html; charset=utf-8');
        readfile($file);
        exit;
    }
}

// Если запрос к frontend файлам
if (strpos($requestPath, 'frontend/') === 0) {
    $file = __DIR__ . '/' . $requestPath;
    if (file_exists($file)) {
        $ext = pathinfo($file, PATHINFO_EXTENSION);
        if ($ext === 'php') {
            include $file;
        } else {
            $mimeTypes = [
                'html' => 'text/html',
                'css' => 'text/css',
                'js' => 'application/javascript',
                'json' => 'application/json',
            ];
            $mime = $mimeTypes[$ext] ?? 'text/plain';
            header('Content-Type: ' . $mime . '; charset=utf-8');
            readfile($file);
        }
        exit;
    }
}

// Если запрос к backend API
if (strpos($requestPath, 'backend/') === 0) {
    $file = __DIR__ . '/' . $requestPath;
    if (file_exists($file)) {
        include $file;
        exit;
    }
}

// Если файл существует, отдаем его
$file = __DIR__ . '/' . $requestPath;
if (file_exists($file) && is_file($file)) {
    $ext = pathinfo($file, PATHINFO_EXTENSION);
    $mimeTypes = [
        'html' => 'text/html',
        'css' => 'text/css',
        'js' => 'application/javascript',
        'json' => 'application/json',
        'png' => 'image/png',
        'jpg' => 'image/jpeg',
        'gif' => 'image/gif',
    ];
    $mime = $mimeTypes[$ext] ?? 'application/octet-stream';
    header('Content-Type: ' . $mime);
    readfile($file);
    exit;
}

// 404 - файл не найден
http_response_code(404);
echo "404 - File not found: " . htmlspecialchars($requestPath);

