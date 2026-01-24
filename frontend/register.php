<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Регистрация - Анализ ДТП</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f5f5;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .auth-container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
            padding: 2rem;
            width: 100%;
            max-width: 400px;
        }
        
        .auth-container h1 {
            color: #333;
            margin-bottom: 0.5rem;
            font-size: 1.8rem;
        }
        
        .auth-container p {
            color: #666;
            margin-bottom: 2rem;
            font-size: 0.9rem;
        }
        
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        .form-group label {
            display: block;
            color: #333;
            margin-bottom: 0.5rem;
            font-weight: 500;
            font-size: 0.9rem;
        }
        
        .form-group input {
            width: 100%;
            padding: 0.75rem;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 1rem;
            transition: border-color 0.3s;
        }
        
        .form-group input:focus {
            outline: none;
            border-color: #667eea;
        }
        
        .form-group small {
            display: block;
            color: #999;
            font-size: 0.8rem;
            margin-top: 0.25rem;
        }
        
        .btn {
            width: 100%;
            padding: 0.75rem;
            background: #2ecc71;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.3s;
            margin-bottom: 0.75rem;
        }
        
        .btn:hover {
            background: #27ae60;
        }
        
        .btn-link {
            background: transparent;
            color: #667eea;
            border: 2px solid #667eea;
            margin-bottom: 0;
        }
        
        .btn-link:hover {
            background: #667eea;
            color: white;
        }
        
        .error {
            background: #fee;
            color: #c33;
            padding: 0.75rem;
            border-radius: 8px;
            margin-bottom: 1rem;
            font-size: 0.9rem;
            display: none;
        }
        
        .error.show {
            display: block;
        }
        
        .links {
            text-align: center;
            margin-top: 1.5rem;
            padding-top: 1.5rem;
            border-top: 1px solid #e0e0e0;
        }
        
        .links a {
            color: #667eea;
            text-decoration: none;
            font-size: 0.9rem;
        }
        
        .links a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="auth-container">
        <h1>📝 Регистрация</h1>
        <p>Создайте аккаунт для сохранения истории маршрутов</p>
        
        <div class="error" id="error"></div>
        
        <form id="registerForm">
            <div class="form-group">
                <label for="login">Логин</label>
                <input type="text" id="login" name="login" required autocomplete="username" minlength="3" maxlength="50">
                <small>От 3 до 50 символов</small>
            </div>
            
            <div class="form-group">
                <label for="password">Пароль</label>
                <input type="password" id="password" name="password" required autocomplete="new-password" minlength="6">
                <small>Не менее 6 символов</small>
            </div>
            
            <div class="form-group">
                <label for="passwordConfirm">Подтвердите пароль</label>
                <input type="password" id="passwordConfirm" name="passwordConfirm" required autocomplete="new-password">
            </div>
            
            <button type="submit" class="btn">Зарегистрироваться</button>
        </form>
        
        <button type="button" class="btn btn-link" onclick="window.location.href='index.html'">
            Продолжить без авторизации
        </button>
        
        <div class="links">
            <a href="login.php">Уже есть аккаунт? Войти</a>
        </div>
    </div>
    
    <script>
        document.getElementById('registerForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const login = document.getElementById('login').value.trim();
            const password = document.getElementById('password').value;
            const passwordConfirm = document.getElementById('passwordConfirm').value;
            const errorDiv = document.getElementById('error');
            
            errorDiv.classList.remove('show');
            
            if (!login || !password || !passwordConfirm) {
                showError('Заполните все поля');
                return;
            }
            
            if (login.length < 3 || login.length > 50) {
                showError('Логин должен быть от 3 до 50 символов');
                return;
            }
            
            if (password.length < 6) {
                showError('Пароль должен быть не менее 6 символов');
                return;
            }
            
            if (password !== passwordConfirm) {
                showError('Пароли не совпадают');
                return;
            }
            
            try {
                const formData = new FormData();
                formData.append('action', 'register');
                formData.append('login', login);
                formData.append('password', password);
                
                const response = await fetch('../backend/auth.php?action=register', {
                    method: 'POST',
                    body: formData,
                    credentials: 'include'
                });
                
                const data = await response.json();
                
                if (response.ok && data.success) {
                    // Перенаправляем на главную страницу
                    window.location.href = 'index.html';
                } else {
                    showError(data.error || 'Ошибка регистрации');
                }
            } catch (error) {
                showError('Ошибка соединения с сервером');
                console.error('Register error:', error);
            }
        });
        
        function showError(message) {
            const errorDiv = document.getElementById('error');
            errorDiv.textContent = message;
            errorDiv.classList.add('show');
        }
    </script>
</body>
</html>

