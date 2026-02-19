<?php
/**
 * API: Validate Data
 * Engineering Utility Monitoring System (EUMS)
 */

// Set headers for JSON response and CORS
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
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
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        $input = $_POST;
    }
    
    $validationType = $input['validation_type'] ?? '';
    $response = ['success' => true, 'valid' => true, 'errors' => []];
    
    switch ($validationType) {
        case 'machine_code':
            validateMachineCode($db, $input, $response);
            break;
            
        case 'meter_code':
            validateMeterCode($db, $input, $response);
            break;
            
        case 'document_no':
            validateDocumentNo($db, $input, $response);
            break;
            
        case 'reading_value':
            validateReadingValue($db, $input, $response);
            break;
            
        case 'date_range':
            validateDateRange($input, $response);
            break;
            
        case 'inspection_data':
            validateInspectionData($db, $input, $response);
            break;
            
        case 'import_file':
            validateImportFile($input, $response);
            break;
            
        case 'batch_data':
            validateBatchData($db, $input, $response);
            break;
            
        case 'user_input':
            validateUserInput($input, $response);
            break;
            
        default:
            validateGenericData($input, $response);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    $response = [
        'success' => false,
        'valid' => false,
        'message' => 'Internal server error',
        'debug' => $e->getMessage()
    ];
    
    error_log("Validation API Error: " . $e->getMessage());
}

echo json_encode($response, JSON_UNESCAPED_UNICODE);

/**
 * Validate machine code uniqueness
 */
function validateMachineCode($db, $input, &$response) {
    $code = $input['code'] ?? '';
    $id = $input['id'] ?? 0;
    $module = $input['module'] ?? 'air';
    
    if (empty($code)) {
        $response['valid'] = false;
        $response['errors'][] = 'กรุณาระบุรหัสเครื่องจักร';
        return;
    }
    
    try {
        switch ($module) {
            case 'air':
                $sql = "SELECT COUNT(*) as count FROM mc_air WHERE machine_code = ?";
                if ($id) {
                    $sql .= " AND id != ?";
                }
                $stmt = $db->prepare($sql);
                $params = $id ? [$code, $id] : [$code];
                $stmt->execute($params);
                break;
                
            case 'boiler':
                $sql = "SELECT COUNT(*) as count FROM mc_boiler WHERE machine_code = ?";
                if ($id) {
                    $sql .= " AND id != ?";
                }
                $stmt = $db->prepare($sql);
                $params = $id ? [$code, $id] : [$code];
                $stmt->execute($params);
                break;
                
            default:
                $response['valid'] = false;
                $response['errors'][] = 'โมดูลไม่ถูกต้อง';
                return;
        }
        
        $result = $stmt->fetch();
        
        if ($result['count'] > 0) {
            $response['valid'] = false;
            $response['errors'][] = 'รหัสเครื่องจักรนี้มีอยู่ในระบบแล้ว';
        }
        
    } catch (PDOException $e) {
        $response['valid'] = false;
        $response['errors'][] = 'Database error: ' . $e->getMessage();
    }
}

/**
 * Validate meter code uniqueness
 */
function validateMeterCode($db, $input, &$response) {
    $code = $input['code'] ?? '';
    $id = $input['id'] ?? 0;
    
    if (empty($code)) {
        $response['valid'] = false;
        $response['errors'][] = 'กรุณาระบุรหัสมิเตอร์';
        return;
    }
    
    try {
        $sql = "SELECT COUNT(*) as count FROM mc_mdb_water WHERE meter_code = ?";
        if ($id) {
            $sql .= " AND id != ?";
        }
        
        $stmt = $db->prepare($sql);
        $params = $id ? [$code, $id] : [$code];
        $stmt->execute($params);
        
        $result = $stmt->fetch();
        
        if ($result['count'] > 0) {
            $response['valid'] = false;
            $response['errors'][] = 'รหัสมิเตอร์นี้มีอยู่ในระบบแล้ว';
        }
        
    } catch (PDOException $e) {
        $response['valid'] = false;
        $response['errors'][] = 'Database error: ' . $e->getMessage();
    }
}

/**
 * Validate document number uniqueness
 */
function validateDocumentNo($db, $input, &$response) {
    $docNo = $input['doc_no'] ?? '';
    $id = $input['id'] ?? 0;
    
    if (empty($docNo)) {
        $response['valid'] = false;
        $response['errors'][] = 'กรุณาระบุเลขที่เอกสาร';
        return;
    }
    
    try {
        $sql = "SELECT COUNT(*) as count FROM documents WHERE doc_no = ?";
        if ($id) {
            $sql .= " AND id != ?";
        }
        
        $stmt = $db->prepare($sql);
        $params = $id ? [$docNo, $id] : [$docNo];
        $stmt->execute($params);
        
        $result = $stmt->fetch();
        
        if ($result['count'] > 0) {
            $response['valid'] = false;
            $response['errors'][] = 'เลขที่เอกสารนี้มีอยู่ในระบบแล้ว';
        }
        
    } catch (PDOException $e) {
        $response['valid'] = false;
        $response['errors'][] = 'Database error: ' . $e->getMessage();
    }
}

/**
 * Validate reading value
 */
function validateReadingValue($db, $input, &$response) {
    $meterId = $input['meter_id'] ?? 0;
    $date = $input['date'] ?? '';
    $reading = $input['reading'] ?? 0;
    $readingType = $input['reading_type'] ?? 'morning';
    
    if (!$meterId) {
        $response['valid'] = false;
        $response['errors'][] = 'กรุณาระบุมิเตอร์';
        return;
    }
    
    if (!validateDate($date)) {
        $response['valid'] = false;
        $response['errors'][] = 'รูปแบบวันที่ไม่ถูกต้อง';
        return;
    }
    
    if (!is_numeric($reading) || $reading < 0) {
        $response['valid'] = false;
        $response['errors'][] = 'ค่าที่อ่านได้ต้องเป็นตัวเลขมากกว่าหรือเท่ากับ 0';
        return;
    }
    
    try {
        // Check if reading exists for this date
        $stmt = $db->prepare("
            SELECT morning_reading, evening_reading 
            FROM meter_daily_readings 
            WHERE meter_id = ? AND record_date = ?
        ");
        $stmt->execute([$meterId, $date]);
        $existing = $stmt->fetch();
        
        if ($existing) {
            if ($readingType == 'morning' && $existing['morning_reading'] !== null) {
                $response['valid'] = false;
                $response['errors'][] = 'บันทึกค่าเช้าสำหรับวันนี้มีอยู่แล้ว';
            }
            if ($readingType == 'evening' && $existing['evening_reading'] !== null) {
                $response['valid'] = false;
                $response['errors'][] = 'บันทึกค่าเย็นสำหรับวันนี้มีอยู่แล้ว';
            }
            
            // Validate evening reading > morning reading
            if ($readingType == 'evening' && $existing['morning_reading'] !== null) {
                if ($reading <= $existing['morning_reading']) {
                    $response['valid'] = false;
                    $response['errors'][] = 'ค่าเย็นต้องมากกว่าค่าเช้า';
                }
            }
        }
        
        // Get last reading for trend validation
        $stmt = $db->prepare("
            SELECT evening_reading 
            FROM meter_daily_readings 
            WHERE meter_id = ? AND record_date < ?
            ORDER BY record_date DESC 
            LIMIT 1
        ");
        $stmt->execute([$meterId, $date]);
        $last = $stmt->fetch();
        
        if ($last && $readingType == 'morning') {
            if ($reading < $last['evening_reading']) {
                // Warning only, not error
                $response['warnings'][] = 'ค่าเช้าน้อยกว่าค่าเย็นวันก่อนหน้า โปรดตรวจสอบ';
            }
            
            // Check for abnormal increase (> 200%)
            if ($reading > $last['evening_reading'] * 3) {
                $response['warnings'][] = 'ค่าเพิ่มขึ้นผิดปกติ (มากกว่า 200%) โปรดตรวจสอบ';
            }
        }
        
    } catch (PDOException $e) {
        $response['valid'] = false;
        $response['errors'][] = 'Database error: ' . $e->getMessage();
    }
}

/**
 * Validate date range
 */
function validateDateRange($input, &$response) {
    $startDate = $input['start_date'] ?? '';
    $endDate = $input['end_date'] ?? '';
    $maxDays = $input['max_days'] ?? 365;
    
    if (empty($startDate) || empty($endDate)) {
        $response['valid'] = false;
        $response['errors'][] = 'กรุณาระบุวันที่เริ่มต้นและสิ้นสุด';
        return;
    }
    
    if (!validateDate($startDate) || !validateDate($endDate)) {
        $response['valid'] = false;
        $response['errors'][] = 'รูปแบบวันที่ไม่ถูกต้อง (ต้องเป็น YYYY-MM-DD)';
        return;
    }
    
    $start = new DateTime($startDate);
    $end = new DateTime($endDate);
    $today = new DateTime();
    
    if ($start > $end) {
        $response['valid'] = false;
        $response['errors'][] = 'วันที่เริ่มต้นต้องมาก่อนหรือเท่ากับวันที่สิ้นสุด';
        return;
    }
    
    if ($end > $today) {
        $response['valid'] = false;
        $response['errors'][] = 'วันที่สิ้นสุดต้องไม่เกินวันปัจจุบัน';
        return;
    }
    
    $interval = $start->diff($end);
    $days = $interval->days;
    
    if ($days > $maxDays) {
        $response['valid'] = false;
        $response['errors'][] = "ช่วงวันที่ต้องไม่เกิน $maxDays วัน";
        return;
    }
    
    // Check for future dates
    if ($start > $today) {
        $response['valid'] = false;
        $response['errors'][] = 'วันที่เริ่มต้นต้องไม่เป็นอนาคต';
        return;
    }
}

/**
 * Validate inspection data
 */
function validateInspectionData($db, $input, &$response) {
    $machineId = $input['machine_id'] ?? 0;
    $itemId = $input['item_id'] ?? 0;
    $value = $input['value'] ?? '';
    $date = $input['date'] ?? date('Y-m-d');
    
    if (!$machineId || !$itemId) {
        $response['valid'] = false;
        $response['errors'][] = 'ข้อมูลไม่ครบถ้วน';
        return;
    }
    
    try {
        // Get inspection standard
        $stmt = $db->prepare("
            SELECT * FROM air_inspection_standards 
            WHERE id = ? AND machine_id = ?
        ");
        $stmt->execute([$itemId, $machineId]);
        $standard = $stmt->fetch();
        
        if (!$standard) {
            $response['valid'] = false;
            $response['errors'][] = 'ไม่พบมาตรฐานการตรวจสอบ';
            return;
        }
        
        // Validate based on type
        if ($standard['min_value'] !== null && $standard['max_value'] !== null) {
            if (!is_numeric($value)) {
                $response['valid'] = false;
                $response['errors'][] = 'ค่าที่บันทึกต้องเป็นตัวเลข';
                return;
            }
            
            $numValue = floatval($value);
            
            if ($numValue < $standard['min_value'] || $numValue > $standard['max_value']) {
                $response['valid'] = false;
                $response['errors'][] = "ค่าอยู่นอกช่วงมาตรฐาน (ต้องอยู่ระหว่าง {$standard['min_value']} - {$standard['max_value']})";
            }
        }
        
        // Check for duplicate record on same date
        $stmt = $db->prepare("
            SELECT COUNT(*) as count 
            FROM air_daily_records 
            WHERE machine_id = ? AND inspection_item_id = ? AND record_date = ?
        ");
        $stmt->execute([$machineId, $itemId, $date]);
        $result = $stmt->fetch();
        
        if ($result['count'] > 0) {
            $response['valid'] = false;
            $response['errors'][] = 'บันทึกข้อมูลสำหรับวันนี้มีอยู่แล้ว';
        }
        
    } catch (PDOException $e) {
        $response['valid'] = false;
        $response['errors'][] = 'Database error: ' . $e->getMessage();
    }
}

/**
 * Validate import file
 */
function validateImportFile($input, &$response) {
    $filename = $input['filename'] ?? '';
    $fileSize = $input['size'] ?? 0;
    $fileType = $input['type'] ?? '';
    $expectedHeaders = $input['headers'] ?? [];
    
    if (empty($filename)) {
        $response['valid'] = false;
        $response['errors'][] = 'กรุณาเลือกไฟล์';
        return;
    }
    
    // Check file extension
    $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    $allowedExtensions = ['xlsx', 'xls', 'csv', 'txt'];
    
    if (!in_array($extension, $allowedExtensions)) {
        $response['valid'] = false;
        $response['errors'][] = 'ประเภทไฟล์ไม่ถูกต้อง (รองรับ: xlsx, xls, csv, txt)';
        return;
    }
    
    // Check file size (max 10MB)
    if ($fileSize > 10485760) {
        $response['valid'] = false;
        $response['errors'][] = 'ไฟล์มีขนาดใหญ่เกินไป (ไม่เกิน 10MB)';
        return;
    }
    
    // For CSV files, we could validate headers here
    if ($extension === 'csv' && !empty($expectedHeaders)) {
        // This would require reading the file
        $response['warnings'][] = 'กรุณาตรวจสอบรูปแบบไฟล์ก่อนนำเข้า';
    }
}

/**
 * Validate batch data
 */
function validateBatchData($db, $input, &$response) {
    $data = $input['data'] ?? [];
    $type = $input['batch_type'] ?? '';
    
    if (empty($data)) {
        $response['valid'] = false;
        $response['errors'][] = 'ไม่มีข้อมูล';
        return;
    }
    
    $errors = [];
    $warnings = [];
    $rowNum = 1;
    
    foreach ($data as $row) {
        switch ($type) {
            case 'meter_readings':
                $rowErrors = validateMeterReadingRow($db, $row);
                break;
                
            case 'inspection_records':
                $rowErrors = validateInspectionRow($db, $row);
                break;
                
            case 'energy_summary':
                $rowErrors = validateEnergyRow($db, $row);
                break;
                
            default:
                $rowErrors = ['ประเภทข้อมูลไม่ถูกต้อง'];
        }
        
        if (!empty($rowErrors)) {
            foreach ($rowErrors as $error) {
                $errors[] = "แถวที่ $rowNum: $error";
            }
        }
        
        $rowNum++;
    }
    
    if (!empty($errors)) {
        $response['valid'] = false;
        $response['errors'] = $errors;
    }
    
    if (!empty($warnings)) {
        $response['warnings'] = $warnings;
    }
}

/**
 * Validate meter reading row
 */
function validateMeterReadingRow($db, $row) {
    $errors = [];
    
    $required = ['meter_id', 'record_date', 'morning_reading', 'evening_reading'];
    foreach ($required as $field) {
        if (!isset($row[$field]) || $row[$field] === '') {
            $errors[] = "กรุณาระบุ $field";
        }
    }
    
    if (empty($errors)) {
        if (!is_numeric($row['morning_reading']) || $row['morning_reading'] < 0) {
            $errors[] = 'ค่าเช้าต้องเป็นตัวเลขมากกว่าหรือเท่ากับ 0';
        }
        
        if (!is_numeric($row['evening_reading']) || $row['evening_reading'] < 0) {
            $errors[] = 'ค่าเย็นต้องเป็นตัวเลขมากกว่าหรือเท่ากับ 0';
        }
        
        if ($row['evening_reading'] <= $row['morning_reading']) {
            $errors[] = 'ค่าเย็นต้องมากกว่าค่าเช้า';
        }
        
        if (!validateDate($row['record_date'])) {
            $errors[] = 'รูปแบบวันที่ไม่ถูกต้อง';
        }
    }
    
    return $errors;
}

/**
 * Validate inspection row
 */
function validateInspectionRow($db, $row) {
    $errors = [];
    
    $required = ['machine_id', 'item_id', 'record_date', 'value'];
    foreach ($required as $field) {
        if (!isset($row[$field]) || $row[$field] === '') {
            $errors[] = "กรุณาระบุ $field";
        }
    }
    
    return $errors;
}

/**
 * Validate energy row
 */
function validateEnergyRow($db, $row) {
    $errors = [];
    
    $required = ['record_date', 'ee_unit', 'cost_per_unit'];
    foreach ($required as $field) {
        if (!isset($row[$field]) || $row[$field] === '') {
            $errors[] = "กรุณาระบุ $field";
        }
    }
    
    if (empty($errors)) {
        if (!is_numeric($row['ee_unit']) || $row['ee_unit'] < 0) {
            $errors[] = 'หน่วยไฟฟ้าต้องเป็นตัวเลขมากกว่าหรือเท่ากับ 0';
        }
        
        if (!is_numeric($row['cost_per_unit']) || $row['cost_per_unit'] < 0) {
            $errors[] = 'ค่าไฟต่อหน่วยต้องเป็นตัวเลขมากกว่าหรือเท่ากับ 0';
        }
    }
    
    return $errors;
}

/**
 * Validate user input
 */
function validateUserInput($input, &$response) {
    $field = $input['field'] ?? '';
    $value = $input['value'] ?? '';
    $rules = $input['rules'] ?? [];
    
    switch ($field) {
        case 'email':
            if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
                $response['valid'] = false;
                $response['errors'][] = 'รูปแบบอีเมลไม่ถูกต้อง';
            }
            break;
            
        case 'phone':
            if (!preg_match('/^[0-9]{9,10}$/', $value)) {
                $response['valid'] = false;
                $response['errors'][] = 'รูปแบบเบอร์โทรไม่ถูกต้อง (ควรเป็น 9-10 หลัก)';
            }
            break;
            
        case 'id_card':
            if (!preg_match('/^[0-9]{13}$/', $value)) {
                $response['valid'] = false;
                $response['errors'][] = 'รูปแบบบัตรประชาชนไม่ถูกต้อง (13 หลัก)';
            }
            break;
            
        case 'number':
            if (!is_numeric($value)) {
                $response['valid'] = false;
                $response['errors'][] = 'กรุณากรอกตัวเลข';
            } elseif (isset($rules['min']) && $value < $rules['min']) {
                $response['valid'] = false;
                $response['errors'][] = "ค่าต้องไม่น้อยกว่า {$rules['min']}";
            } elseif (isset($rules['max']) && $value > $rules['max']) {
                $response['valid'] = false;
                $response['errors'][] = "ค่าต้องไม่เกิน {$rules['max']}";
            }
            break;
            
        case 'text':
            if (isset($rules['min_length']) && strlen($value) < $rules['min_length']) {
                $response['valid'] = false;
                $response['errors'][] = "ความยาวต้องไม่น้อยกว่า {$rules['min_length']} ตัวอักษร";
            }
            if (isset($rules['max_length']) && strlen($value) > $rules['max_length']) {
                $response['valid'] = false;
                $response['errors'][] = "ความยาวต้องไม่เกิน {$rules['max_length']} ตัวอักษร";
            }
            if (isset($rules['pattern']) && !preg_match($rules['pattern'], $value)) {
                $response['valid'] = false;
                $response['errors'][] = $rules['pattern_message'] ?? 'รูปแบบไม่ถูกต้อง';
            }
            break;
            
        case 'date':
            if (!validateDate($value)) {
                $response['valid'] = false;
                $response['errors'][] = 'รูปแบบวันที่ไม่ถูกต้อง (ควรเป็น YYYY-MM-DD)';
            }
            break;
            
        default:
            // Generic validation
            if (empty($value) && !empty($rules['required'])) {
                $response['valid'] = false;
                $response['errors'][] = 'กรุณากรอกข้อมูล';
            }
    }
}

/**
 * Validate generic data
 */
function validateGenericData($input, &$response) {
    $data = $input['data'] ?? [];
    $rules = $input['rules'] ?? [];
    
    foreach ($rules as $field => $rule) {
        $value = $data[$field] ?? '';
        
        if (!empty($rule['required']) && empty($value)) {
            $label = $rule['label'] ?? $field;
            $response['valid'] = false;
            $response['errors'][] = "กรุณากรอก {$label}";
            continue;
        }
        
        if (!empty($value)) {
            if (!empty($rule['type'])) {
                switch ($rule['type']) {
                    case 'email':
                        $label = $rule['label'] ?? $field;
                        if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
                            $response['valid'] = false;
                            $response['errors'][] = "{$label} รูปแบบไม่ถูกต้อง";
                        }
                        break;
                        
                    case 'numeric':
                        if (!is_numeric($value)) {
                            $response['valid'] = false;
                            $label = $rule['label'] ?? $field;
                            $response['errors'][] = "{$label} ต้องเป็นตัวเลข";
                        }
                        break;
                        
                    case 'date':
                        if (!validateDate($value)) {
                            $response['valid'] = false;
                            $label = $rule['label'] ?? $field;
                            $errorMessage = "{$label} รูปแบบไม่ถูกต้อง";
                            $response['errors'][] = $errorMessage;
                        }
                        break;
                }
            }
            
            if (!empty($rule['min']) && $value < $rule['min']) {
                $response['valid'] = false;
                $label = $rule['label'] ?? $field;
                $response['errors'][] = "{$label} ต้องไม่น้อยกว่า {$rule['min']}";
            }
            
            if (!empty($rule['max']) && $value > $rule['max']) {
                $response['valid'] = false;
                $label = $rule['label'] ?? $field;
                $response['errors'][] = "{$label} ต้องไม่เกิน {$rule['max']}";
            }
            
            if (!empty($rule['pattern']) && !preg_match($rule['pattern'], $value)) {
                $response['valid'] = false;
                $label = $rule['label'] ?? $field;
                $response['errors'][] = $rule['pattern_message'] ?? "{$label} รูปแบบไม่ถูกต้อง";
            }
        }
    }
}
?>