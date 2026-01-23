<?php
/**
 * API для работы с историей маршрутов
 * GET /api/route_history.php - получить историю
 * POST /api/route_history.php - добавить маршрут
 * DELETE /api/route_history.php?id=X - удалить маршрут
 */

session_start();

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, DELETE');

require_once __DIR__ . '/db.php';

// Проверка авторизации
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Необходима авторизация'], JSON_UNESCAPED_UNICODE);
    exit;
}

$userId = $_SESSION['user_id'];
$method = $_SERVER['REQUEST_METHOD'];

try {
    $pdo = getDB();
    
    switch ($method) {
        case 'GET':
            getRouteHistory($pdo, $userId);
            break;
            
        case 'POST':
            addRouteToHistory($pdo, $userId);
            break;
            
        case 'DELETE':
            deleteRouteFromHistory($pdo, $userId);
            break;
            
        default:
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed'], JSON_UNESCAPED_UNICODE);
            break;
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}

function getRouteHistory($pdo, $userId) {
    try {
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 5;
        $limit = max(1, min(50, $limit)); // Ограничиваем от 1 до 50
        
        error_log("[route_history] getRouteHistory: user_id=$userId, limit=$limit");
        
        // В MySQL LIMIT не может быть параметром, поэтому используем безопасную конкатенацию
        // $limit уже проверен и приведен к int, поэтому безопасно
        $sql = "
            SELECT id, from_address, to_address, created_at 
            FROM route_history 
            WHERE user_id = ? 
            ORDER BY created_at DESC 
            LIMIT " . (int)$limit . "
        ";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$userId]);
        $history = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        error_log("[route_history] Получено записей: " . count($history));
        
        echo json_encode([
            'success' => true,
            'history' => $history
        ], JSON_UNESCAPED_UNICODE);
    } catch (PDOException $e) {
        error_log("[route_history] Ошибка SQL: " . $e->getMessage());
        http_response_code(500);
        echo json_encode([
            'error' => 'Ошибка получения истории: ' . $e->getMessage()
        ], JSON_UNESCAPED_UNICODE);
    } catch (Exception $e) {
        error_log("[route_history] Ошибка: " . $e->getMessage());
        http_response_code(500);
        echo json_encode([
            'error' => 'Ошибка: ' . $e->getMessage()
        ], JSON_UNESCAPED_UNICODE);
    }
}

function addRouteToHistory($pdo, $userId) {
    $from = isset($_POST['from']) ? trim($_POST['from']) : '';
    $to = isset($_POST['to']) ? trim($_POST['to']) : '';
    
    // Логирование для отладки
    error_log("[route_history] addRouteToHistory: user_id=$userId, from='$from', to='$to'");
    
    if (empty($from) || empty($to)) {
        error_log("[route_history] Ошибка: пустые адреса");
        http_response_code(400);
        echo json_encode(['error' => 'Адреса обязательны'], JSON_UNESCAPED_UNICODE);
        return;
    }
    
    // Проверяем, есть ли уже такой маршрут
    $stmt = $pdo->prepare("
        SELECT id FROM route_history 
        WHERE user_id = ? AND from_address = ? AND to_address = ?
        ORDER BY created_at DESC 
        LIMIT 1
    ");
    $stmt->execute([$userId, $from, $to]);
    $existing = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($existing) {
        // Обновляем время последнего использования
        $stmt = $pdo->prepare("
            UPDATE route_history 
            SET created_at = CURRENT_TIMESTAMP 
            WHERE id = ?
        ");
        $stmt->execute([$existing['id']]);
        $routeId = $existing['id'];
    } else {
        // Добавляем новый маршрут
        $stmt = $pdo->prepare("
            INSERT INTO route_history (user_id, from_address, to_address) 
            VALUES (?, ?, ?)
        ");
        $stmt->execute([$userId, $from, $to]);
        $routeId = $pdo->lastInsertId();
        
        // Удаляем старые записи, оставляем только последние 5
        $stmt = $pdo->prepare("
            DELETE FROM route_history 
            WHERE user_id = ? 
            AND id NOT IN (
                SELECT id FROM (
                    SELECT id FROM route_history 
                    WHERE user_id = ? 
                    ORDER BY created_at DESC 
                    LIMIT 5
                ) AS temp
            )
        ");
        $stmt->execute([$userId, $userId]);
    }
    
    echo json_encode([
        'success' => true,
        'id' => $routeId
    ], JSON_UNESCAPED_UNICODE);
}

function deleteRouteFromHistory($pdo, $userId) {
    $routeId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    
    if ($routeId <= 0) {
        http_response_code(400);
        echo json_encode(['error' => 'Неверный ID маршрута'], JSON_UNESCAPED_UNICODE);
        return;
    }
    
    // Проверяем, что маршрут принадлежит пользователю
    $stmt = $pdo->prepare("SELECT id FROM route_history WHERE id = ? AND user_id = ?");
    $stmt->execute([$routeId, $userId]);
    if (!$stmt->fetch()) {
        http_response_code(404);
        echo json_encode(['error' => 'Маршрут не найден'], JSON_UNESCAPED_UNICODE);
        return;
    }
    
    // Удаляем маршрут
    $stmt = $pdo->prepare("DELETE FROM route_history WHERE id = ? AND user_id = ?");
    $stmt->execute([$routeId, $userId]);
    
    echo json_encode(['success' => true], JSON_UNESCAPED_UNICODE);
}

