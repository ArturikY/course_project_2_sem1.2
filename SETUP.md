# Инструкция по настройке проекта

## Шаг 1: Конвертация GeoJSON → NDJSON

Конвертация запущена в фоне. Проверьте результат:

```bash
# Проверка наличия файла
dir moskva.ndjson

# Или через Python
python -c "import os; print('Exists:', os.path.exists('moskva.ndjson')); print('Size MB:', round(os.path.getsize('moskva.ndjson')/1024/1024, 2) if os.path.exists('moskva.ndjson') else 0)"
```

Если файл не создался, запустите вручную:

```bash
python convert_to_ndjson.py
```

или

```bash
python convert_simple.py
```

Ожидаемый результат: файл `moskva.ndjson` размером ~190-200 MB

## Шаг 2: Настройка базы данных

1. Запустите XAMPP (Apache + MySQL)
2. Откройте phpMyAdmin: http://localhost/phpmyadmin
3. Импортируйте `schema.sql`:
   - Выберите базу данных (или создайте новую `dtp_analysis`)
   - Вкладка "Импорт" → выберите `schema.sql` → "Вперед"

Или выполните SQL вручную:

```sql
CREATE DATABASE IF NOT EXISTS dtp_analysis CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE dtp_analysis;
-- Затем скопируйте содержимое schema.sql
```

## Шаг 3: Импорт данных

Откройте терминал в папке проекта и выполните:

```bash
php import_ndjson.php
```

Или с параметрами:

```bash
php import_ndjson.php moskva.ndjson dtp_analysis
```

**Важно:** 
- Убедитесь, что файл `moskva.ndjson` существует
- Проверьте настройки подключения в `import_ndjson.php` (хост, пользователь, пароль)
- Импорт может занять несколько минут (91,748 записей)

## Шаг 4: Проверка импорта

В phpMyAdmin выполните:

```sql
-- Количество записей
SELECT COUNT(*) as total FROM accidents;

-- Диапазон дат
SELECT MIN(dt) as first, MAX(dt) as last FROM accidents;

-- Статистика по категориям
SELECT category, COUNT(*) as cnt 
FROM accidents 
GROUP BY category 
ORDER BY cnt DESC 
LIMIT 10;

-- Статистика по районам
SELECT region, COUNT(*) as cnt 
FROM accidents 
GROUP BY region 
ORDER BY cnt DESC 
LIMIT 10;

-- Проверка spatial индекса
SELECT id, lat, lon, ST_AsText(geom) as geom_text 
FROM accidents 
LIMIT 5;
```

Ожидаемый результат:
- **total**: ~91,748 записей
- **first/last**: даты в диапазоне данных
- Геометрия должна быть в формате `POINT(lon lat)`

## Возможные проблемы

### Ошибка "File not found"
- Убедитесь, что файл `moskva.ndjson` находится в той же папке, что и скрипт
- Проверьте путь в `import_ndjson.php`

### Ошибка подключения к БД
- Проверьте, что MySQL запущен в XAMPP
- Измените настройки в `import_ndjson.php`:
  ```php
  'host' => 'localhost',
  'user' => 'root',
  'pass' => 'ваш_пароль',
  ```

### Ошибка "Table already exists"
- Это нормально, если таблица уже создана
- Скрипт использует `INSERT ... ON DUPLICATE KEY UPDATE`, так что можно запускать повторно

### Долгий импорт
- Это нормально для 91k+ записей
- Скрипт показывает прогресс каждые 10,000 записей
- Ожидаемое время: 2-5 минут в зависимости от производительности

## Следующие шаги

После успешного импорта можно:
1. Создать API endpoints для получения данных
2. Создать frontend с картой
3. Реализовать функционал сравнения маршрутов

