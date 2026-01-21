# Проект анализа ДТП в Москве

Система для визуализации и анализа дорожно-транспортных происшествий с возможностью сравнения маршрутов по безопасности.

## Структура проекта

- `moskva.geojson` - исходный датасет с ДТП (GeoJSON FeatureCollection, ~189 MB, 91,748 записей)
- `moskva.ndjson` - конвертированный формат (Newline Delimited JSON, по одной записи на строку)
- `schema.sql` - SQL схема базы данных MySQL
- `convert_to_ndjson.py` - скрипт конвертации GeoJSON → NDJSON
- `import_ndjson.php` - скрипт импорта данных в MySQL
- `convert_simple.py` - упрощенная версия конвертера

## Установка и настройка

### 1. Конвертация GeoJSON в NDJSON

```bash
python convert_to_ndjson.py
```

или

```bash
python convert_simple.py
```

Результат: файл `moskva.ndjson` (каждая строка = одна фича)

### 2. Создание базы данных

1. Откройте phpMyAdmin (http://localhost/phpmyadmin)
2. Импортируйте файл `schema.sql` или выполните SQL команды вручную
3. База данных будет создана автоматически при импорте (или создайте вручную: `dtp_analysis`)

### 3. Импорт данных

```bash
php import_ndjson.php [путь_к_ndjson] [имя_бд]
```

Пример:
```bash
php import_ndjson.php moskva.ndjson dtp_analysis
```

По умолчанию:
- Файл: `moskva.ndjson`
- БД: `dtp_analysis`
- Хост: `localhost`
- Пользователь: `root`
- Пароль: `` (пустой, стандартно для XAMPP)

**Важно:** Измените настройки подключения в `import_ndjson.php` если у вас другие параметры.

### 4. Проверка импорта

```sql
SELECT COUNT(*) FROM accidents;
SELECT MIN(dt), MAX(dt) FROM accidents;
SELECT category, COUNT(*) as cnt FROM accidents GROUP BY category ORDER BY cnt DESC;
```

## Структура базы данных

### Таблица `accidents`
Основная таблица с данными о ДТП:
- `id` - уникальный идентификатор
- `dt` - дата и время
- `lat`, `lon` - координаты
- `geom` - геометрия точки (POINT, SRID 4326) с spatial индексом
- `category` - категория ДТП
- `severity` - тяжесть (Легкий, Средний, Тяжелый, Смертельный)
- `region` - район Москвы
- `light` - время суток
- `address` - адрес
- `tags`, `weather`, `nearby`, `vehicles` - JSON поля с дополнительной информацией
- `extra` - все остальные поля из исходного датасета

### Таблица `grid_stats`
Агрегированная статистика по сетке (для быстрого отображения "горячих" участков)

## Следующие шаги

1. ✅ Конвертация данных в NDJSON
2. ✅ Создание схемы БД
3. ✅ Импорт данных
4. ⏳ Создание API endpoints (PHP)
5. ⏳ Frontend (HTML/CSS/JS) с картой
6. ⏳ Функционал сравнения маршрутов

## Технологии

- **Backend:** PHP 7.4+
- **Database:** MySQL 8.0+ (с поддержкой spatial типов)
- **Frontend:** HTML, CSS, JavaScript
- **Карта:** Leaflet или MapLibre GL JS
