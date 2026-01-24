# BACKEND

Серверная часть веб-приложения (API).

## Файлы

- **config.php** - Конфигурация системы
- **db.php** - Подключение к базе данных
- **data_loader.php** - Универсальный загрузчик данных
- **accidents.php** - API для получения данных о ДТП
- **hotspots.php** - API для получения опасных зон
- **route_history.php** - API для истории маршрутов
- **auth.php** - API для авторизации пользователей
- **cache_helper.php** - Утилиты для кэширования

## Технологии

- PHP 7.4+
- MySQL 8.0+
- PDO для работы с БД

## Endpoints

- `GET /backend/accidents.php` - Получение данных о ДТП
- `GET /backend/hotspots.php` - Получение опасных зон
- `GET /backend/route_history.php` - Получение истории маршрутов
- `POST /backend/route_history.php` - Добавление маршрута в историю
- `DELETE /backend/route_history.php` - Удаление маршрута из истории
- `GET /backend/auth.php?action=check` - Проверка авторизации
- `POST /backend/auth.php?action=login` - Вход в систему
- `POST /backend/auth.php?action=register` - Регистрация
- `POST /backend/auth.php?action=logout` - Выход из системы

