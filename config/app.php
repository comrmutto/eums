<?php
/**
 * Application Configuration File
 * Engineering Utility Monitoring System (EUMS)
 */

// Application settings
$app_config = [
    // Application info
    'app' => [
        'name' => 'Engineering Utility Monitoring System',
        'short_name' => 'EUMS',
        'version' => '1.0.0',
        'environment' => 'development', // development, production, testing
        'debug' => true,
        'timezone' => 'Asia/Bangkok',
        'language' => 'th',
        'date_format' => 'd/m/Y',
        'datetime_format' => 'd/m/Y H:i:s'
    ],
    
    // Paths configuration
    'paths' => [
        'base_url' => 'http://localhost/eums',
        'assets' => '/eums/assets',
        'uploads' => '/eums/uploads',
        'modules' => '/eums/modules',
        'includes' => '/eums/includes'
    ],
    
    // Upload settings
    'upload' => [
        'max_size' => 10485760, // 10MB in bytes
        'allowed_extensions' => ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'xlsx', 'xls'],
        'upload_path' => __DIR__ . '/../uploads/',
        'thumbnail_path' => __DIR__ . '/../uploads/thumbnails/'
    ],
    
    // Module configurations
    'modules' => [
        'air_compressor' => [
            'enabled' => true,
            'name' => 'Air Compressor',
            'icon' => 'fas fa-compress',
            'route' => '/modules/air-compressor/',
            'table_prefix' => 'air_'
        ],
        'energy_water' => [
            'enabled' => true,
            'name' => 'Energy & Water',
            'icon' => 'fas fa-tint',
            'route' => '/modules/energy-water/',
            'table_prefix' => 'meter_'
        ],
        'lpg' => [
            'enabled' => true,
            'name' => 'LPG',
            'icon' => 'fas fa-fire',
            'route' => '/modules/lpg/',
            'table_prefix' => 'lpg_'
        ],
        'boiler' => [
            'enabled' => true,
            'name' => 'Boiler',
            'icon' => 'fas fa-industry',
            'route' => '/modules/boiler/',
            'table_prefix' => 'boiler_'
        ],
        'summary' => [
            'enabled' => true,
            'name' => 'Summary Electricity',
            'icon' => 'fas fa-chart-line',
            'route' => '/modules/summary-electricity/',
            'table_prefix' => 'summary_'
        ]
    ],
    
    // Chart configurations
    'charts' => [
        'default_type' => 'line',
        'colors' => [
            'primary' => '#007bff',
            'success' => '#28a745',
            'warning' => '#ffc107',
            'danger' => '#dc3545',
            'info' => '#17a2b8'
        ],
        'refresh_interval' => 300000, // 5 minutes in milliseconds
        'date_range_presets' => [
            'today' => 'วันนี้',
            'yesterday' => 'เมื่อวาน',
            'this_week' => 'สัปดาห์นี้',
            'last_week' => 'สัปดาห์ที่แล้ว',
            'this_month' => 'เดือนนี้',
            'last_month' => 'เดือนที่แล้ว',
            'this_year' => 'ปีนี้'
        ]
    ],
    
    // Pagination settings
    'pagination' => [
        'per_page' => 20,
        'max_links' => 5
    ],
    
    // Session configuration
    'session' => [
        'name' => 'eums_session',
        'lifetime' => 7200, // 2 hours in seconds
        'path' => '/',
        'domain' => '',
        'secure' => false,
        'httponly' => true
    ],
    
    // Cache configuration
    'cache' => [
        'enabled' => true,
        'driver' => 'file', // file, redis, memcached
        'path' => __DIR__ . '/../cache/',
        'lifetime' => 3600 // 1 hour in seconds
    ],
    
    // Logging configuration
    'logging' => [
        'enabled' => true,
        'path' => __DIR__ . '/../logs/',
        'level' => 'debug', // debug, info, warning, error
        'max_files' => 30
    ],
    
    // Email configuration (for notifications)
    'mail' => [
        'enabled' => false,
        'driver' => 'smtp',
        'host' => 'smtp.gmail.com',
        'port' => 587,
        'username' => '',
        'password' => '',
        'encryption' => 'tls',
        'from_address' => 'noreply@eums.com',
        'from_name' => 'EUMS System'
    ],
    
    // API settings
    'api' => [
        'enabled' => true,
        'rate_limit' => 100, // requests per minute
        'token_expiry' => 86400, // 24 hours in seconds
        'allowed_origins' => [
            'http://localhost:3000',
            'http://localhost:5000'
        ]
    ],
    
    // Security settings
    'security' => [
        'password_min_length' => 8,
        'password_hash_algo' => PASSWORD_DEFAULT,
        'csrf_protection' => true,
        'xss_protection' => true,
        'sql_injection_protection' => true
    ],
    
    // Report settings
    'reports' => [
        'export_formats' => ['pdf', 'excel', 'csv'],
        'max_export_rows' => 10000,
        'default_report_period' => 'monthly'
    ],
    
    // Notification settings
    'notifications' => [
        'enabled' => true,
        'methods' => ['email', 'system'],
        'threshold_warning' => 80, // percentage
        'threshold_critical' => 95 // percentage
    ]
];

// Return the configuration array
return $app_config;
?>