<?php
/**
 * Конфигурация для API
 */

return [
    // Источник данных: 'database' или 'file'
    'data_source' => 'file', // Измените на 'database' для работы с БД
    
    // Настройки базы данных (используются если data_source = 'database')
    'database' => [
        'host' => 'localhost',
        'dbname' => 'dtp_analysis',
        'user' => 'root',
        'pass' => '',
        'charset' => 'utf8mb4'
    ],
    
    // Настройки файла (используются если data_source = 'file')
    'file' => [
        'ndjson_path' => __DIR__ . '/../moskva.ndjson', // Путь к NDJSON файлу
        'cache_enabled' => true, // Кэширование загруженных данных в памяти
        'cache_ttl' => 300 // Время жизни кэша в секундах (5 минут)
    ],
    
    // Настройки кэша API
    'cache' => [
        'enabled' => true,
        'dir' => __DIR__ . '/../cache', // Директория для файлового кэша
        'ttl' => 3600 // Время жизни кэша в секундах (1 час)
    ],
    
    // Общие настройки API
    'api' => [
        'default_period_days' => 30,
        'max_bbox_size' => 1.0,
        'grid_size_meters' => 1000,
        'hotspot_threshold' => 5,
        // Настройки стабильности опасных зон
        'hotspot' => [
            'fixed_radius_meters' => 400,      // Фиксированный радиус для отображения (все зоны одинакового размера)
                                                // 400м = круги не будут накладываться при gridSize >= 800м
            'standard_area_m2' => 100000,      // Эталонная площадь для расчета плотности (0.1 км²)
            'density_threshold_medium' => 0.2,  // Порог для среднего уровня опасности
            'density_threshold_high' => 0.3    // Порог для высокого уровня опасности
        ]
    ]
];
