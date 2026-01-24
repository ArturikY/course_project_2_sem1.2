<?php
/**
 * API для авторизации пользователей
 * POST /api/auth.php?action=login|register|logout|check
 */

session_start();

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET');
header('Access-Control-Allow-Credentials: true');

require_once __DIR__ . '/db.php';

$action = isset($_GET['action']) ? $_GET['action'] : (isset($_POST['action']) ? $_POST['action'] : null);

try {
    $pdo = getDB();
    
    switch ($action) {
        case 'register':
            handleRegister($pdo);
            break;
            
        case 'login':
            handleLogin($pdo);
            break;
            
        case 'logout':
            handleLogout();
            break;
            
        case 'check':
            handleCheck();
            break;
            
        default:
            http_response_code(400);
            echo json_encode(['error' => 'Invalid action'], JSON_UNESCAPED_UNICODE);
            break;
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}

function handleRegister($pdo) {
    $login = isset($_POST['login']) ? trim($_POST['login']) : '';
    $password = isset($_POST['password']) ? $_POST['password'] : '';
    
    if (empty($login) || empty($password)) {
        http_response_code(400);
        echo json_encode(['error' => 'Логин и пароль обязательны'], JSON_UNESCAPED_UNICODE);
        return;
    }
    
    if (strlen($login) < 3 || strlen($login) > 50) {
        http_response_code(400);
        echo json_encode(['error' => 'Логин должен быть от 3 до 50 символов'], JSON_UNESCAPED_UNICODE);
        return;
    }
    
    if (strlen($password) < 6) {
        http_response_code(400);
        echo json_encode(['error' => 'Пароль должен быть не менее 6 символов'], JSON_UNESCAPED_UNICODE);
        return;
    }
    
    // Проверяем, существует ли пользователь
    $stmt = $pdo->prepare("SELECT id FROM users WHERE login = ?");
    $stmt->execute([$login]);
    if ($stmt->fetch()) {
        http_response_code(400);
        echo json_encode(['error' => 'Пользователь с таким логином уже существует'], JSON_UNESCAPED_UNICODE);
        return;
    }
    
    // Хэшируем пароль
    $passwordHash = password_hash($password, PASSWORD_DEFAULT);
    
    // Создаем пользователя
    $stmt = $pdo->prepare("INSERT INTO users (login, password_hash) VALUES (?, ?)");
    $stmt->execute([$login, $passwordHash]);
    
    $userId = $pdo->lastInsertId();
    
    // Устанавливаем сессию
    $_SESSION['user_id'] = $userId;
    $_SESSION['user_login'] = $login;
    
    echo json_encode([
        'success' => true,
        'user' => [
            'id' => $userId,
            'login' => $login
        ]
    ], JSON_UNESCAPED_UNICODE);
}

function handleLogin($pdo) {
    $login = isset($_POST['login']) ? trim($_POST['login']) : '';
    $password = isset($_POST['password']) ? $_POST['password'] : '';
    
    if (empty($login) || empty($password)) {
        http_response_code(400);
        echo json_encode(['error' => 'Логин и пароль обязательны'], JSON_UNESCAPED_UNICODE);
        return;
    }
    
    // Ищем пользователя
    $stmt = $pdo->prepare("SELECT id, login, password_hash FROM users WHERE login = ?");
    $stmt->execute([$login]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user || !password_verify($password, $user['password_hash'])) {
        http_response_code(401);
        echo json_encode(['error' => 'Неверный логин или пароль'], JSON_UNESCAPED_UNICODE);
        return;
    }
    
    // Устанавливаем сессию
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['user_login'] = $user['login'];
    
    echo json_encode([
        'success' => true,
        'user' => [
            'id' => $user['id'],
            'login' => $user['login']
        ]
    ], JSON_UNESCAPED_UNICODE);
}

function handleLogout() {
    session_destroy();
    echo json_encode(['success' => true], JSON_UNESCAPED_UNICODE);
}

function handleCheck() {
    if (isset($_SESSION['user_id']) && isset($_SESSION['user_login'])) {
        echo json_encode([
            'authenticated' => true,
            'user' => [
                'id' => $_SESSION['user_id'],
                'login' => $_SESSION['user_login']
            ]
        ], JSON_UNESCAPED_UNICODE);
    } else {
        echo json_encode(['authenticated' => false], JSON_UNESCAPED_UNICODE);
    }
}



