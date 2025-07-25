<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
header('Content-Type: application/json');

session_start();
include '../config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    if (!$data) {
        echo json_encode(['success' => false, 'message' => 'Invalid JSON input']);
        exit;
    }

    $form = $data['form'] ?? null;
    $emp_as = $data['emp_as'] ?? null;
    $emp_pe = $data['emp_pe'] ?? [];
    $evaluation_period = $data['evaluation_period'] ?? null;
    $start_date = $data['start_date'] ?? null;
    $end_date = $data['end_date'] ?? null;

    if (!$form || !$emp_as || empty($emp_pe)) {
        echo json_encode(['success' => false, 'message' => 'ข้อมูลไม่ครบถ้วน']);
        exit;
    }

    $conn = dbConnect();
    if (!$conn) {
        echo json_encode(['success' => false, 'message' => 'ไม่สามารถเชื่อมต่อฐานข้อมูลได้']);
        exit;
    }

    try {
        // ตรวจสอบว่าพนักงานมีอยู่จริง
        $sqlCheckEmp = "SELECT COUNT(*) AS count FROM employees WHERE employee_id = ?";
        $stmtCheckEmp = sqlsrv_query($conn, $sqlCheckEmp, array($emp_as));
        
        if (!$stmtCheckEmp) {
            throw new Exception('ไม่สามารถตรวจสอบข้อมูลพนักงานได้');
        }
        
        $empRow = sqlsrv_fetch_array($stmtCheckEmp, SQLSRV_FETCH_ASSOC);
        sqlsrv_free_stmt($stmtCheckEmp);
        
        if ($empRow['count'] == 0) {
            throw new Exception('ไม่พบข้อมูลพนักงานผู้ประเมิน');
        }

        // บันทึกข้อมูลลงตาราง evaluation_forms (ใช้ตารางที่มีอยู่จริง)
        $success_count = 0;
        
        foreach ($emp_pe as $emp_pe_item) {
            // ตรวจสอบว่าผู้ถูกประเมินมีอยู่จริง
            $sqlCheckEmpPe = "SELECT COUNT(*) AS count FROM employees WHERE employee_id = ?";
            $stmtCheckEmpPe = sqlsrv_query($conn, $sqlCheckEmpPe, array($emp_pe_item));
            
            if ($stmtCheckEmpPe) {
                $empPeRow = sqlsrv_fetch_array($stmtCheckEmpPe, SQLSRV_FETCH_ASSOC);
                sqlsrv_free_stmt($stmtCheckEmpPe);
                
                if ($empPeRow['count'] > 0) {
                    // สร้างข้อมูลการประเมินแบบง่าย (ใช้ตาราง employees เป็นหลัก)
                    // เก็บข้อมูลในตาราง activities ชั่วคราว
                    $sqlInsert = "INSERT INTO activities (activity_name, description, score, created_by) VALUES (?, ?, ?, ?)";
                    $activity_name = "การประเมิน: $evaluation_period - $form";
                    $description = "ผู้ประเมิน: $emp_as, ผู้ถูกประเมิน: $emp_pe_item, ระยะเวลา: $start_date ถึง $end_date";
                    $score = 10; // คะแนนเริ่มต้น
                    
                    $stmtInsert = sqlsrv_query($conn, $sqlInsert, array($activity_name, $description, $score, $emp_as));
                    
                    if ($stmtInsert) {
                        sqlsrv_free_stmt($stmtInsert);
                        $success_count++;
                    }
                }
            }
        }
        
        if ($success_count > 0) {
            echo json_encode([
                'success' => true, 
                'message' => "บันทึกข้อมูลเรียบร้อยแล้ว ($success_count รายการ)\nForm: $form\nผู้ประเมิน: $emp_as\nรอบการประเมิน: $evaluation_period"
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'ไม่สามารถบันทึกข้อมูลได้']);
        }
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'เกิดข้อผิดพลาด: ' . $e->getMessage()]);
    }
    
    if ($conn) {
        sqlsrv_close($conn);
    }
    
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
?>
