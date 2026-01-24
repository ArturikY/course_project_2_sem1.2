-- Схема базы данных для проекта анализа ДТП
-- MySQL 5.7+ / MariaDB 10.2+ с поддержкой spatial типов
-- Примечание: SRID 4326 задается при вставке данных через ST_SRID()

-- Таблица с данными о ДТП
CREATE TABLE IF NOT EXISTS accidents (
  id BIGINT PRIMARY KEY,
  dt DATETIME NULL,
  lat DOUBLE NOT NULL,
  lon DOUBLE NOT NULL,
  geom POINT NOT NULL,
  category VARCHAR(100) NULL,
  severity VARCHAR(50) NULL,
  region VARCHAR(100) NULL,
  light VARCHAR(100) NULL,
  address VARCHAR(255) NULL,
  tags JSON NULL,
  weather JSON NULL,
  nearby JSON NULL,
  vehicles JSON NULL,
  extra JSON NULL,
  KEY idx_dt (dt),
  KEY idx_category (category),
  KEY idx_severity (severity),
  KEY idx_region (region),
  SPATIAL INDEX idx_geom (geom)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Таблица для агрегации по сетке (для быстрого отображения "горячих" участков)
-- Примечание: geom может быть NULL, поэтому SPATIAL индекс создается только если geom NOT NULL
CREATE TABLE IF NOT EXISTS grid_stats (
  cell_id VARCHAR(50) PRIMARY KEY,
  zoom_level TINYINT NOT NULL,
  cell_size INT NOT NULL,
  center_lat DOUBLE NOT NULL,
  center_lon DOUBLE NOT NULL,
  geom POLYGON NULL,
  accident_count INT DEFAULT 0,
  severe_count INT DEFAULT 0,
  fatal_count INT DEFAULT 0,
  last_updated DATETIME NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Создание SPATIAL индекса только для записей с NOT NULL геометрией
-- Выполните этот запрос отдельно после заполнения таблицы данными с геометрией
-- CREATE SPATIAL INDEX idx_geom ON grid_stats (geom);

-- Представление для быстрого получения статистики по периодам
CREATE OR REPLACE VIEW accidents_recent AS
SELECT 
  id, dt, lat, lon, geom, category, severity, region, light, address
FROM accidents
WHERE dt >= DATE_SUB(NOW(), INTERVAL 90 DAY);

