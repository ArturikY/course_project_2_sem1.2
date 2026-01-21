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
        'default_period_days' => 30,
        'max_bbox_size' => 0.1,
        'grid_size_meters' => 250,
        'hotspot_threshold' => 5
    ]
];
