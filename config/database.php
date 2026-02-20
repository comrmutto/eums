<?php
/**
 * Database Configuration File
 * Engineering Utility Monitoring System (EUMS)
 */

// เปิด error reporting เพื่อดู error จริง
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Database configuration for different environments
$db_config = [
    'development' => [
        'host' => 'db',
        'username' => 'eums',
        'password' => 'eums_13792846',
        'database' => 'eums',
        'port' => 3306,
        'charset' => 'utf8', // เปลี่ยนจาก utf8mb4 เป็น utf8
        'collation' => 'utf8_unicode_ci', // เปลี่ยนจาก utf8mb4_unicode_ci เป็น utf8_unicode_ci
        'driver' => 'mysql',
        'options' => [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8 COLLATE utf8_unicode_ci"
        ]
    ],
    'production' => [
        'host' => 'db',
        'username' => 'eums',
        'password' => 'eums_13792846',
        'database' => 'eums',
        'port' => 3306,
        'charset' => 'utf8',
        'collation' => 'utf8_unicode_ci',
        'driver' => 'mysql',
        'options' => [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    ],
    'testing' => [
        'host' => 'db',
        'username' => 'eums',
        'password' => 'eums_13792846',
        'database' => 'eums',
        'port' => 3306,
        'charset' => 'utf8',
        'collation' => 'utf8_unicode_ci',
        'driver' => 'mysql',
        'options' => [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    ]
];

// Select environment
$environment = 'development';

// Store configuration
$GLOBALS['db_connection_config'] = $db_config[$environment];
$GLOBALS['db_tables'] = [
    'documents' => 'documents',
    'users' => 'users',
    'mc_air' => 'mc_air',
    'air_inspection_standards' => 'air_inspection_standards',
    'air_daily_records' => 'air_daily_records',
    'mc_mdb_water' => 'mc_mdb_water',
    'meter_daily_readings' => 'meter_daily_readings',
    'lpg_inspection_items' => 'lpg_inspection_items',
    'lpg_daily_records' => 'lpg_daily_records',
    'mc_boiler' => 'mc_boiler',
    'boiler_daily_records' => 'boiler_daily_records',
    'electricity_summary' => 'electricity_summary',
    'monthly_summaries' => 'monthly_summaries'
];

// Return configuration array
return [
    'connection' => $db_config[$environment],
    'tables' => $GLOBALS['db_tables'],
    'logging' => [
        'enabled' => true,
        'log_file' => __DIR__ . '/../logs/sql_queries.log',
        'slow_query_threshold' => 1.0
    ]
];

// Database connection class
class Database {
    private static $instance = null;
    private $connection;
    private $config;
    private $error = null;
    
    private function __construct() {
        $this->config = $GLOBALS['db_connection_config'];
        $this->connect();
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function connect() {
        try {
            // Log connection attempt
            error_log("Attempting to connect to database: " . $this->config['database']);
            
            $dsn = "mysql:host={$this->config['host']};port={$this->config['port']};dbname={$this->config['database']};charset={$this->config['charset']}";
            
            error_log("DSN: " . $dsn);
            
            $this->connection = new PDO(
                $dsn,
                $this->config['username'],
                $this->config['password'],
                $this->config['options']
            );
            
            error_log("Database connection successful");
            
        } catch (PDOException $e) {
            $this->error = $e->getMessage();
            error_log("Database connection failed: " . $e->getMessage());
            error_log("Error code: " . $e->getCode());
            
            // ในโหมด development ให้แสดง error จริง
            if ($this->config['host'] == 'db' || $this->config['host'] == '127.0.0.1') {
                die("Database Error: " . $e->getMessage());
            } else {
                die("ไม่สามารถเชื่อมต่อฐานข้อมูลได้ กรุณาติดต่อผู้ดูแลระบบ");
            }
        }
    }
    
    public function getConnection() {
        try {
            if ($this->connection) {
                // Test connection
                $this->connection->query("SELECT 1");
            }
        } catch (PDOException $e) {
            error_log("Connection lost, attempting to reconnect...");
            $this->connect();
        }
        
        return $this->connection;
    }
    
    public function getError() {
        return $this->error;
    }
    
    private function __clone() {}
    
    public function __wakeup() {
        throw new Exception("Cannot unserialize singleton");
    }
}
?>