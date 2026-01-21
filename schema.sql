-- Схема базы данных для проекта анализа ДТП
-- MySQL 5.7+ / MariaDB 10.2+ с поддержкой spatial типов
-- Примечание: SRID 4326 задается при вставке данных через ST_SRID()

-- Создание базы данных (раскомментируй если нужно)
-- CREATE DATABASE IF NOT EXISTS dtp_analysis CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
-- USE dtp_analysis;

-- Таблица с данными о ДТП
CREATE TABLE IF NOT EXISTS accidents (
  id BIGINT PRIMARY KEY COMMENT 'ID из исходного датасета',
  dt DATETIME NULL COMMENT 'Дата и время ДТП',
  lat DOUBLE NOT NULL COMMENT 'Широта',
  lon DOUBLE NOT NULL COMMENT 'Долгота',
  geom POINT NOT NULL COMMENT 'Геометрия точки (WGS84, SRID задается при вставке)',
  category VARCHAR(100) NULL COMMENT 'Категория ДТП (Столкновение, Наезд и т.д.)',
  severity VARCHAR(50) NULL COMMENT 'Тяжесть (Легкий, Средний, Тяжелый, Смертельный)',
  region VARCHAR(100) NULL COMMENT 'Район Москвы',
  light VARCHAR(100) NULL COMMENT 'Время суток (Светлое/Темное)',
  address VARCHAR(255) NULL COMMENT 'Адрес',
  tags JSON NULL COMMENT 'Теги (массив строк)',
  weather JSON NULL COMMENT 'Погодные условия (массив строк)',
  nearby JSON NULL COMMENT 'Ближайшие объекты (массив строк)',
  vehicles JSON NULL COMMENT 'Информация о ТС (массив объектов)',
  extra JSON NULL COMMENT 'Все остальные поля из properties',
  
  -- Индексы
  KEY idx_dt (dt) COMMENT 'Индекс по дате для фильтрации по времени',
  KEY idx_category (category) COMMENT 'Индекс по категории',
  KEY idx_severity (severity) COMMENT 'Индекс по тяжести',
  KEY idx_region (region) COMMENT 'Индекс по району',
  SPATIAL INDEX idx_geom (geom) COMMENT 'Spatial индекс для быстрых геозапросов'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Таблица с данными о дорожно-транспортных происшествиях';

-- Таблица для агрегации по сетке (для быстрого отображения "горячих" участков)
CREATE TABLE IF NOT EXISTS grid_stats (
  cell_id VARCHAR(50) PRIMARY KEY COMMENT 'ID ячейки сетки (формат: zoom_size_lat_lon)',
  zoom_level TINYINT NOT NULL COMMENT 'Уровень зума (для разных размеров сетки)',
  cell_size INT NOT NULL COMMENT 'Размер ячейки в метрах',
  center_lat DOUBLE NOT NULL COMMENT 'Широта центра ячейки',
  center_lon DOUBLE NOT NULL COMMENT 'Долгота центра ячейки',
  geom POLYGON NULL COMMENT 'Геометрия ячейки (опционально, SRID задается при вставке)',
  accident_count INT DEFAULT 0 COMMENT 'Количество ДТП в ячейке',
  severe_count INT DEFAULT 0 COMMENT 'Количество тяжелых ДТП',
  fatal_count INT DEFAULT 0 COMMENT 'Количество смертельных ДТП',
  last_updated DATETIME NULL COMMENT 'Дата последнего обновления статистики',
  
  SPATIAL INDEX idx_geom (geom) COMMENT 'Spatial индекс для геозапросов'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Агрегированная статистика ДТП по сетке для быстрого отображения';

-- Представление для быстрого получения статистики по периодам (опционально)
-- Можно использовать для кэширования популярных запросов
CREATE OR REPLACE VIEW accidents_recent AS
SELECT 
  id, dt, lat, lon, geom, category, severity, region, light, address
FROM accidents
WHERE dt >= DATE_SUB(NOW(), INTERVAL 90 DAY);

