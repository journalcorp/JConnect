<?php
// Simple Save Form - Fixed Version
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: application/json; charset=utf-8');

// ตรวจสอบ method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'ต้องใช้ POST method เท่านั้น']);
    exit;
}

// อ่านข้อมูล JSON
$input = file_get_contents('php://input');
if (empty($input)) {
    echo json_encode(['success' => false, 'message' => 'ไม่มีข้อมูลส่งมา']);
    exit;
}

$data = json_decode($input, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    echo json_encode(['success' => false, 'message' => 'ข้อมูล JSON ไม่ถูกต้อง: ' . json_last_error_msg()]);
    exit;
}

// ตรวจสอบข้อมูลที่จำเป็น
$required_fields = ['form', 'emp_as', 'emp_pe', 'evaluation_period', 'start_date', 'end_date'];
foreach ($required_fields as $field) {
    if (empty($data[$field])) {
        echo json_encode(['success' => false, 'message' => "ไม่มีข้อมูล: $field"]);
        exit;
    }
}

if (!is_array($data['emp_pe']) || count($data['emp_pe']) === 0) {
    echo json_encode(['success' => false, 'message' => 'ต้องมีผู้ถูกประเมินอย่างน้อย 1 คน']);
    exit;
}

try {
    include '../config.php';
    
    $conn = dbConnect();
    if (!$conn) {
        throw new Exception('ไม่สามารถเชื่อมต่อฐานข้อมูลได้');
    }

    // ตรวจสอบพนักงานผู้ประเมิน
    $sql_check_emp = "SELECT employee_id, first_name_th, last_name_th FROM employees WHERE employee_id = ?";
    $stmt_check = sqlsrv_query($conn, $sql_check_emp, array($data['emp_as']));
    
    if (!$stmt_check) {
        throw new Exception('ไม่สามารถตรวจสอบพนักงานผู้ประเมินได้');
    }
    
    $emp_as_data = sqlsrv_fetch_array($stmt_check, SQLSRV_FETCH_ASSOC);
    sqlsrv_free_stmt($stmt_check);
    
    if (!$emp_as_data) {
        throw new Exception('ไม่พบพนักงานผู้ประเมิน ID: ' . $data['emp_as']);
    }

    // บันทึกข้อมูลการประเมิน
    $success_count = 0;
    $errors = [];
    
    foreach ($data['emp_pe'] as $emp_pe_id) {
        // ตรวจสอบผู้ถูกประเมิน
        $sql_check_pe = "SELECT employee_id, first_name_th, last_name_th FROM employees WHERE employee_id = ?";
        $stmt_check_pe = sqlsrv_query($conn, $sql_check_pe, array($emp_pe_id));
        
        if ($stmt_check_pe) {
            $emp_pe_data = sqlsrv_fetch_array($stmt_check_pe, SQLSRV_FETCH_ASSOC);
            sqlsrv_free_stmt($stmt_check_pe);
            
            if ($emp_pe_data) {
                // บันทึกลงตาราง activities
                $activity_name = "การประเมิน {$data['evaluation_period']} - {$data['form']}";
                $description = "ผู้ประเมิน: {$emp_as_data['first_name_th']} {$emp_as_data['last_name_th']} ({$data['emp_as']}) " .
                             "ผู้ถูกประเมิน: {$emp_pe_data['first_name_th']} {$emp_pe_data['last_name_th']} ($emp_pe_id) " .
                             "ระยะเวลา: {$data['start_date']} ถึง {$data['end_date']}";
                $score = 10;
                
                $sql_insert = "INSERT INTO activities (activity_name, description, score, created_by) VALUES (?, ?, ?, ?)";
                $stmt_insert = sqlsrv_query($conn, $sql_insert, array($activity_name, $description, $score, $data['emp_as']));
                
                if ($stmt_insert) {
                    sqlsrv_free_stmt($stmt_insert);
                    $success_count++;
                } else {
                    $errors[] = "ไม่สามารถบันทึกข้อมูลสำหรับ $emp_pe_id ได้";
                }
            } else {
                $errors[] = "ไม่พบพนักงาน $emp_pe_id";
            }
        } else {
            $errors[] = "ไม่สามารถตรวจสอบพนักงาน $emp_pe_id ได้";
        }
    }
    
    sqlsrv_close($conn);
    
    if ($success_count > 0) {
        $message = "บันทึกข้อมูลสำเร็จ $success_count รายการ";
        if (!empty($errors)) {
            $message .= " (มีข้อผิดพลาด: " . implode(', ', $errors) . ")";
        }
        echo json_encode(['success' => true, 'message' => $message, 'count' => $success_count]);
    } else {
        echo json_encode(['success' => false, 'message' => 'ไม่สามารถบันทึกข้อมูลได้: ' . implode(', ', $errors)]);
    }
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'เกิดข้อผิดพลาด: ' . $e->getMessage()]);
}
?>
