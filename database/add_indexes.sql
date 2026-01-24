-- Добавление индексов для оптимизации запросов hotspots
-- Выполните этот скрипт в phpMyAdmin или через командную строку MySQL

USE dtp_analysis;

-- Составной индекс на lat и lon для ускорения запросов по координатам
CREATE INDEX IF NOT EXISTS idx_lat_lon ON accidents (lat, lon);

-- Альтернативный вариант: отдельные индексы (если составной не поддерживается)
-- CREATE INDEX IF NOT EXISTS idx_lat ON accidents (lat);
-- CREATE INDEX IF NOT EXISTS idx_lon ON accidents (lon);

-- Индекс на dt для фильтрации по дате (если еще не создан)
-- CREATE INDEX IF NOT EXISTS idx_dt ON accidents (dt);

-- Проверка индексов
SHOW INDEX FROM accidents;

