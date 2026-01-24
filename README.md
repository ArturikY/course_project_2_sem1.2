# ВЕБ-ПРИЛОЖЕНИЕ ДЛЯ АНАЛИЗА ОПАСНОСТИ МАРШРУТОВ

## Структура проекта

Проект организован с четким разделением на frontend и backend:

```
course_project2/
├── frontend/              # Клиентская часть (Frontend)
│   ├── index.html        # Главная страница приложения
│   ├── login.php         # Страница входа
│   └── register.php      # Страница регистрации
│
├── backend/              # Серверная часть (Backend)
│   ├── config.php       # Конфигурация системы
│   ├── db.php            # Подключение к БД
│   ├── data_loader.php  # Универсальный загрузчик данных
│   ├── accidents.php     # API для получения ДТП
│   ├── hotspots.php      # API для получения опасных зон
│   ├── route_history.php # API для истории маршрутов
│   ├── auth.php          # API для авторизации
│   └── cache_helper.php  # Утилиты для кэширования
│
├── database/             # База данных
│   ├── schema.sql       # Основная схема БД
│   ├── schema_v2.5.sql  # Дополнения к схеме (авторизация)
│   └── add_indexes.sql  # Дополнительные индексы
│
├── scripts/              # Скрипты для работы с данными
│   ├── import_ndjson.php # Импорт данных из NDJSON в БД
│   └── import_test.php   # Тестовый импорт
│
├── cache/                # Кэш API ответов
│   └── *.json           # Файлы кэша
│
└── moskva.ndjson         # Исходные данные о ДТП
```

## Установка

1. **Настройка базы данных:**
   - Импортируйте файлы из папки `database/` в MySQL
   - Сначала `schema.sql`, затем `schema_v2.5.sql`, затем `add_indexes.sql`

2. **Импорт данных:**
   ```bash
   php scripts/import_ndjson.php
   ```

3. **Настройка конфигурации:**
   - Отредактируйте `backend/config.php`
   - Укажите источник данных: `'database'` или `'file'`
   - Настройте параметры подключения к БД

## Запуск сервера

### Вариант 1: Из корня проекта (рекомендуется)

Запустите из корня проекта:

```bash
cd D:\vs-code\study\bd2\course_project2
"C:\php\php.exe" -S localhost:8000 router.php
```

Затем откройте в браузере:
- `http://localhost:8000/` - главная страница
- `http://localhost:8000/frontend/login.php` - страница входа
- `http://localhost:8000/backend/hotspots.php` - API endpoint

### Вариант 2: Прямой доступ к файлам

Если запускаете без router.php:

```bash
"C:\php\php.exe" -S localhost:8000
```

Тогда используйте полные пути:
- `http://localhost:8000/frontend/index.html`
- `http://localhost:8000/frontend/login.php`
- `http://localhost:8000/backend/hotspots.php`

## Использование

Откройте в браузере: `http://localhost:8000/` (если используете router.php)

## Технологии

- **Frontend:** HTML5, CSS3, JavaScript (ES6+), Яндекс.Карты API 2.1
- **Backend:** PHP 7.4+, MySQL 8.0+
- **Данные:** NDJSON, GeoJSON
