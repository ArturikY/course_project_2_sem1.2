<?php
/**
 * Конфигурация для API
 */

return [
    'database' => [
        'host' => 'localhost',
        'dbname' => 'dtp_analysis',
        'user' => 'root',
        'pass' => '', // Измените если нужно
        'charset' => 'utf8mb4'
    ],
    
    'api' => [
        'default_period_days' => 30, // Период по умолчанию для фильтрации
        'max_bbox_size' => 0.1, // Максимальный размер bbox (градусы)
        'grid_size_meters' => 250, // Размер ячейки сетки в метрах для анализа
        'hotspot_threshold' => 5 // Минимальное количество ДТП для "горячей" зоны
    ]
];

