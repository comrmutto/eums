<?php
/**
 * API: Get Machines Data
 * Engineering Utility Monitoring System (EUMS)
 */

// Set headers for JSON response and CORS
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Start session
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check authentication
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

// Load required files
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

try {
    $db = getDB();
    $response = ['success' => true, 'data' => []];
    
    // Get request method and parameters
    $method = $_SERVER['REQUEST_METHOD'];
    $action = $_GET['action'] ?? $_POST['action'] ?? '';
    $module = $_GET['module'] ?? $_POST['module'] ?? '';
    
    switch ($method) {
        case 'GET':
            handleGetRequest($db, $action, $module, $response);
            break;
            
        case 'POST':
            handlePostRequest($db, $action, $module, $response);
            break;
            
        default:
            http_response_code(405);
            $response['success'] = false;
            $response['message'] = 'Method not allowed';
    }
    
} catch (Exception $e) {
    http_response_code(500);
    $response = [
        'success' => false,
        'message' => 'Internal server error',
        'debug' => $e->getMessage()
    ];
    
    // Log error
    error_log("API Error: " . $e->getMessage());
}

echo json_encode($response, JSON_UNESCAPED_UNICODE);

/**
 * Handle GET requests
 */
function handleGetRequest($db, $action, $module, &$response) {
    switch ($module) {
        case 'air':
            getAirCompressorMachines($db, $action, $response);
            break;
            
        case 'energy':
            getEnergyWaterMeters($db, $action, $response);
            break;
            
        case 'lpg':
            getLPGItems($db, $action, $response);
            break;
            
        case 'boiler':
            getBoilerMachines($db, $action, $response);
            break;
            
        case 'summary':
            getSummaryData($db, $action, $response);
            break;
            
        default:
            getAllMachines($db, $response);
    }
}

/**
 * Handle POST requests
 */
function handlePostRequest($db, $action, $module, &$response) {
    switch ($action) {
        case 'get_by_ids':
            getMachinesByIds($db, $_POST['ids'] ?? [], $response);
            break;
            
        case 'get_by_type':
            getMachinesByType($db, $_POST['type'] ?? '', $response);
            break;
            
        case 'search':
            searchMachines($db, $_POST['keyword'] ?? '', $_POST['module'] ?? '', $response);
            break;
            
        case 'get_details':
            getMachineDetails($db, $_POST['id'] ?? 0, $_POST['module'] ?? '', $response);
            break;
            
        default:
            $response['success'] = false;
            $response['message'] = 'Invalid action';
    }
}

/**
 * Get Air Compressor machines
 */
function getAirCompressorMachines($db, $action, &$response) {
    try {
        switch ($action) {
            case 'list':
                $stmt = $db->query("
                    SELECT * FROM mc_air 
                    WHERE status = 1 
                    ORDER BY machine_code
                ");
                $response['data'] = $stmt->fetchAll();
                $response['count'] = count($response['data']);
                break;
                
            case 'with_standards':
                $stmt = $db->query("
                    SELECT m.*, COUNT(s.id) as standard_count 
                    FROM mc_air m
                    LEFT JOIN air_inspection_standards s ON m.id = s.machine_id
                    WHERE m.status = 1
                    GROUP BY m.id
                    ORDER BY m.machine_code
                ");
                $response['data'] = $stmt->fetchAll();
                break;
                
            case 'details':
                $id = $_GET['id'] ?? 0;
                $stmt = $db->prepare("
                    SELECT m.*, 
                           GROUP_CONCAT(s.inspection_item) as inspection_items,
                           GROUP_CONCAT(s.standard_value) as standard_values
                    FROM mc_air m
                    LEFT JOIN air_inspection_standards s ON m.id = s.machine_id
                    WHERE m.id = ?
                    GROUP BY m.id
                ");
                $stmt->execute([$id]);
                $response['data'] = $stmt->fetch();
                break;
                
            default:
                $stmt = $db->query("SELECT * FROM mc_air ORDER BY machine_code");
                $response['data'] = $stmt->fetchAll();
        }
    } catch (PDOException $e) {
        $response['success'] = false;
        $response['message'] = 'Database error: ' . $e->getMessage();
    }
}

/**
 * Get Energy & Water meters
 */
function getEnergyWaterMeters($db, $action, &$response) {
    try {
        switch ($action) {
            case 'electricity':
                $stmt = $db->query("
                    SELECT * FROM mc_mdb_water 
                    WHERE meter_type = 'electricity' AND status = 1
                    ORDER BY meter_code
                ");
                $response['data'] = $stmt->fetchAll();
                break;
                
            case 'water':
                $stmt = $db->query("
                    SELECT * FROM mc_mdb_water 
                    WHERE meter_type = 'water' AND status = 1
                    ORDER BY meter_code
                ");
                $response['data'] = $stmt->fetchAll();
                break;
                
            case 'with_readings':
                $date = $_GET['date'] ?? date('Y-m-d');
                $stmt = $db->prepare("
                    SELECT m.*, 
                           r.morning_reading, 
                           r.evening_reading,
                           r.usage_amount
                    FROM mc_mdb_water m
                    LEFT JOIN meter_daily_readings r 
                        ON m.id = r.meter_id AND r.record_date = ?
                    WHERE m.status = 1
                    ORDER BY m.meter_type, m.meter_code
                ");
                $stmt->execute([$date]);
                $response['data'] = $stmt->fetchAll();
                break;
                
            default:
                $stmt = $db->query("
                    SELECT * FROM mc_mdb_water 
                    ORDER BY meter_type, meter_code
                ");
                $response['data'] = $stmt->fetchAll();
        }
    } catch (PDOException $e) {
        $response['success'] = false;
        $response['message'] = 'Database error: ' . $e->getMessage();
    }
}

/**
 * Get LPG inspection items
 */
function getLPGItems($db, $action, &$response) {
    try {
        switch ($action) {
            case 'numbers':
                $stmt = $db->query("
                    SELECT * FROM lpg_inspection_items 
                    WHERE item_type = 'number'
                    ORDER BY item_no
                ");
                $response['data'] = $stmt->fetchAll();
                break;
                
            case 'enums':
                $stmt = $db->query("
                    SELECT * FROM lpg_inspection_items 
                    WHERE item_type = 'enum'
                    ORDER BY item_no
                ");
                $response['data'] = $stmt->fetchAll();
                break;
                
            case 'with_standards':
                $stmt = $db->query("
                    SELECT *, 
                           JSON_EXTRACT(enum_options, '$') as options 
                    FROM lpg_inspection_items 
                    ORDER BY item_no
                ");
                $response['data'] = $stmt->fetchAll();
                break;
                
            default:
                $stmt = $db->query("
                    SELECT * FROM lpg_inspection_items 
                    ORDER BY item_no
                ");
                $response['data'] = $stmt->fetchAll();
        }
    } catch (PDOException $e) {
        $response['success'] = false;
        $response['message'] = 'Database error: ' . $e->getMessage();
    }
}

/**
 * Get Boiler machines
 */
function getBoilerMachines($db, $action, &$response) {
    try {
        switch ($action) {
            case 'active':
                $stmt = $db->query("
                    SELECT * FROM mc_boiler 
                    WHERE status = 1 
                    ORDER BY machine_code
                ");
                $response['data'] = $stmt->fetchAll();
                break;
                
            case 'with_readings':
                $date = $_GET['date'] ?? date('Y-m-d');
                $stmt = $db->prepare("
                    SELECT m.*, 
                           r.steam_pressure,
                           r.steam_temperature,
                           r.feed_water_level,
                           r.fuel_consumption,
                           r.operating_hours
                    FROM mc_boiler m
                    LEFT JOIN boiler_daily_records r 
                        ON m.id = r.machine_id AND r.record_date = ?
                    WHERE m.status = 1
                    ORDER BY m.machine_code
                ");
                $stmt->execute([$date]);
                $response['data'] = $stmt->fetchAll();
                break;
                
            default:
                $stmt = $db->query("SELECT * FROM mc_boiler ORDER BY machine_code");
                $response['data'] = $stmt->fetchAll();
        }
    } catch (PDOException $e) {
        $response['success'] = false;
        $response['message'] = 'Database error: ' . $e->getMessage();
    }
}

/**
 * Get Summary data
 */
function getSummaryData($db, $action, &$response) {
    try {
        switch ($action) {
            case 'monthly':
                $year = $_GET['year'] ?? date('Y');
                $stmt = $db->prepare("
                    SELECT * FROM electricity_summary 
                    WHERE YEAR(record_date) = ?
                    ORDER BY record_date
                ");
                $stmt->execute([$year]);
                $response['data'] = $stmt->fetchAll();
                break;
                
            case 'comparison':
                $year1 = $_GET['year1'] ?? date('Y');
                $year2 = $_GET['year2'] ?? (date('Y') - 1);
                
                $stmt = $db->prepare("
                    SELECT 
                        MONTH(record_date) as month,
                        SUM(CASE WHEN YEAR(record_date) = ? THEN ee_unit ELSE 0 END) as year1_usage,
                        SUM(CASE WHEN YEAR(record_date) = ? THEN ee_unit ELSE 0 END) as year2_usage,
                        SUM(CASE WHEN YEAR(record_date) = ? THEN total_cost ELSE 0 END) as year1_cost,
                        SUM(CASE WHEN YEAR(record_date) = ? THEN total_cost ELSE 0 END) as year2_cost
                    FROM electricity_summary
                    WHERE YEAR(record_date) IN (?, ?)
                    GROUP BY MONTH(record_date)
                    ORDER BY month
                ");
                $stmt->execute([$year1, $year2, $year1, $year2, $year1, $year2]);
                $response['data'] = $stmt->fetchAll();
                break;
                
            default:
                $stmt = $db->query("
                    SELECT * FROM electricity_summary 
                    ORDER BY record_date DESC 
                    LIMIT 12
                ");
                $response['data'] = $stmt->fetchAll();
        }
    } catch (PDOException $e) {
        $response['success'] = false;
        $response['message'] = 'Database error: ' . $e->getMessage();
    }
}

/**
 * Get all machines from all modules
 */
function getAllMachines($db, &$response) {
    try {
        $allMachines = [];
        
        // Get Air Compressor machines
        $stmt = $db->query("
            SELECT 'air' as module, id, machine_code, machine_name, 'Air Compressor' as type 
            FROM mc_air WHERE status = 1
        ");
        $allMachines = array_merge($allMachines, $stmt->fetchAll());
        
        // Get Energy & Water meters
        $stmt = $db->query("
            SELECT 'energy' as module, id, meter_code as machine_code, 
                   meter_name as machine_name, meter_type as type 
            FROM mc_mdb_water WHERE status = 1
        ");
        $allMachines = array_merge($allMachines, $stmt->fetchAll());
        
        // Get Boiler machines
        $stmt = $db->query("
            SELECT 'boiler' as module, id, machine_code, machine_name, 'Boiler' as type 
            FROM mc_boiler WHERE status = 1
        ");
        $allMachines = array_merge($allMachines, $stmt->fetchAll());
        
        $response['data'] = $allMachines;
        $response['count'] = count($allMachines);
        
    } catch (PDOException $e) {
        $response['success'] = false;
        $response['message'] = 'Database error: ' . $e->getMessage();
    }
}

/**
 * Get machines by IDs
 */
function getMachinesByIds($db, $ids, &$response) {
    if (empty($ids)) {
        $response['data'] = [];
        return;
    }
    
    try {
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        
        $stmt = $db->prepare("
            SELECT 'air' as module, id, machine_code, machine_name, 'Air Compressor' as type 
            FROM mc_air WHERE id IN ($placeholders)
            UNION ALL
            SELECT 'energy' as module, id, meter_code as machine_code, 
                   meter_name as machine_name, meter_type as type 
            FROM mc_mdb_water WHERE id IN ($placeholders)
            UNION ALL
            SELECT 'boiler' as module, id, machine_code, machine_name, 'Boiler' as type 
            FROM mc_boiler WHERE id IN ($placeholders)
        ");
        
        // Duplicate IDs for each union
        $params = array_merge($ids, $ids, $ids);
        $stmt->execute($params);
        
        $response['data'] = $stmt->fetchAll();
        
    } catch (PDOException $e) {
        $response['success'] = false;
        $response['message'] = 'Database error: ' . $e->getMessage();
    }
}

/**
 * Get machines by type
 */
function getMachinesByType($db, $type, &$response) {
    try {
        switch ($type) {
            case 'air':
                $stmt = $db->query("SELECT * FROM mc_air WHERE status = 1");
                break;
            case 'electricity':
            case 'water':
                $stmt = $db->prepare("
                    SELECT * FROM mc_mdb_water 
                    WHERE meter_type = ? AND status = 1
                ");
                $stmt->execute([$type]);
                break;
            case 'boiler':
                $stmt = $db->query("SELECT * FROM mc_boiler WHERE status = 1");
                break;
            default:
                $response['data'] = [];
                return;
        }
        
        $response['data'] = $stmt->fetchAll();
        
    } catch (PDOException $e) {
        $response['success'] = false;
        $response['message'] = 'Database error: ' . $e->getMessage();
    }
}

/**
 * Search machines by keyword
 */
function searchMachines($db, $keyword, $module, &$response) {
    if (empty($keyword)) {
        $response['data'] = [];
        return;
    }
    
    try {
        $searchTerm = "%$keyword%";
        
        switch ($module) {
            case 'air':
                $stmt = $db->prepare("
                    SELECT * FROM mc_air 
                    WHERE machine_code LIKE ? 
                       OR machine_name LIKE ? 
                       OR brand LIKE ?
                    ORDER BY machine_code
                    LIMIT 50
                ");
                $stmt->execute([$searchTerm, $searchTerm, $searchTerm]);
                break;
                
            case 'energy':
                $stmt = $db->prepare("
                    SELECT * FROM mc_mdb_water 
                    WHERE meter_code LIKE ? 
                       OR meter_name LIKE ? 
                       OR location LIKE ?
                    ORDER BY meter_code
                    LIMIT 50
                ");
                $stmt->execute([$searchTerm, $searchTerm, $searchTerm]);
                break;
                
            case 'boiler':
                $stmt = $db->prepare("
                    SELECT * FROM mc_boiler 
                    WHERE machine_code LIKE ? 
                       OR machine_name LIKE ? 
                       OR brand LIKE ?
                    ORDER BY machine_code
                    LIMIT 50
                ");
                $stmt->execute([$searchTerm, $searchTerm, $searchTerm]);
                break;
                
            default:
                // Search all modules
                $stmt = $db->prepare("
                    (SELECT 'air' as module, id, machine_code, machine_name 
                     FROM mc_air 
                     WHERE machine_code LIKE ? OR machine_name LIKE ?
                     LIMIT 20)
                    UNION ALL
                    (SELECT 'energy' as module, id, meter_code as machine_code, meter_name as machine_name 
                     FROM mc_mdb_water 
                     WHERE meter_code LIKE ? OR meter_name LIKE ?
                     LIMIT 20)
                    UNION ALL
                    (SELECT 'boiler' as module, id, machine_code, machine_name 
                     FROM mc_boiler 
                     WHERE machine_code LIKE ? OR machine_name LIKE ?
                     LIMIT 20)
                ");
                $stmt->execute([$searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm]);
        }
        
        $response['data'] = $stmt->fetchAll();
        $response['count'] = count($response['data']);
        
    } catch (PDOException $e) {
        $response['success'] = false;
        $response['message'] = 'Database error: ' . $e->getMessage();
    }
}

/**
 * Get machine details by ID
 */
function getMachineDetails($db, $id, $module, &$response) {
    if (!$id) {
        $response['success'] = false;
        $response['message'] = 'Machine ID is required';
        return;
    }
    
    try {
        switch ($module) {
            case 'air':
                $stmt = $db->prepare("
                    SELECT m.*, 
                           COUNT(r.id) as total_records,
                           AVG(r.actual_value) as avg_value,
                           MAX(r.record_date) as last_record_date
                    FROM mc_air m
                    LEFT JOIN air_daily_records r ON m.id = r.machine_id
                    WHERE m.id = ?
                    GROUP BY m.id
                ");
                $stmt->execute([$id]);
                break;
                
            case 'energy':
                $stmt = $db->prepare("
                    SELECT m.*, 
                           COUNT(r.id) as total_readings,
                           AVG(r.usage_amount) as avg_usage,
                           MAX(r.record_date) as last_reading_date
                    FROM mc_mdb_water m
                    LEFT JOIN meter_daily_readings r ON m.id = r.meter_id
                    WHERE m.id = ?
                    GROUP BY m.id
                ");
                $stmt->execute([$id]);
                break;
                
            case 'boiler':
                $stmt = $db->prepare("
                    SELECT m.*, 
                           COUNT(r.id) as total_records,
                           AVG(r.steam_pressure) as avg_pressure,
                           AVG(r.fuel_consumption) as avg_fuel,
                           MAX(r.record_date) as last_record_date
                    FROM mc_boiler m
                    LEFT JOIN boiler_daily_records r ON m.id = r.machine_id
                    WHERE m.id = ?
                    GROUP BY m.id
                ");
                $stmt->execute([$id]);
                break;
                
            default:
                $response['success'] = false;
                $response['message'] = 'Invalid module';
                return;
        }
        
        $data = $stmt->fetch();
        
        if ($data) {
            $response['data'] = $data;
        } else {
            $response['success'] = false;
            $response['message'] = 'Machine not found';
        }
        
    } catch (PDOException $e) {
        $response['success'] = false;
        $response['message'] = 'Database error: ' . $e->getMessage();
    }
}
?>