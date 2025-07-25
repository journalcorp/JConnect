<?php
// ไฟล์ทดสอบการบันทึกแบบง่าย
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json; charset=utf-8');

try {
    // เริ่ม session
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    // ตั้งค่า employee_id สำหรับทดสอบ
    if (!isset($_SESSION['employee_id'])) {
        $_SESSION['employee_id'] = 'TEST001';
    }

    $employee_id = $_SESSION['employee_id'];

    // ตรวจสอบ method
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo json_encode(['success' => false, 'message' => 'Method not POST: ' . $_SERVER['REQUEST_METHOD']]);
        exit;
    }

    // รับข้อมูล JSON
    $jsonInput = file_get_contents('php://input');
    $inputData = json_decode($jsonInput, true);

    if (!$inputData) {
        echo json_encode(['success' => false, 'message' => 'Invalid JSON: ' . $jsonInput]);
        exit;
    }

    // Include config
    include '../config.php';

    // เชื่อมต่อฐานข้อมูล
    $conn = dbConnect();
    if (!$conn) {
        echo json_encode(['success' => false, 'message' => 'Database connection failed']);
        exit;
    }

    // เตรียมข้อมูล
    $currentYear = date('Y');
    $currentMonth = (int)date('n');
    $evaluation_round = $currentYear . '-' . ($currentMonth <= 6 ? '1' : '2');
    
    $scores = $inputData['scores'] ?? [];
    $purple_score = (int)($scores['purple'] ?? 0);
    $yellow_score = (int)($scores['yellow'] ?? 0);
    $blue_score = (int)($scores['blue'] ?? 0);
    $green_score = (int)($scores['green'] ?? 0);
    $dominant_color = $inputData['dominant_color'] ?? '';
    $scores_json = json_encode($scores);
    $answers = json_encode($inputData);

    if (empty($dominant_color)) {
        echo json_encode(['success' => false, 'message' => 'Missing dominant_color']);
        exit;
    }

    if (is_resource($conn)) {
        // สร้างตารางถ้ายังไม่มี (แบบง่าย)
        $createSQL = "
        IF NOT EXISTS (SELECT * FROM sys.tables WHERE name = 'color_personality_results')
        BEGIN
            CREATE TABLE color_personality_results (
                id INT IDENTITY(1,1) PRIMARY KEY,
                employee_id NVARCHAR(50) NOT NULL,
                evaluation_round NVARCHAR(20) NOT NULL,
                purple_score INT DEFAULT 0,
                yellow_score INT DEFAULT 0,
                blue_score INT DEFAULT 0,
                green_score INT DEFAULT 0,
                dominant_color NVARCHAR(20) NOT NULL,
                scores NTEXT,
                answers NTEXT,
                created_at DATETIME DEFAULT GETDATE()
            )
        END";
        
        $result = sqlsrv_query($conn, $createSQL);
        if ($result === false) {
            $errors = sqlsrv_errors();
            echo json_encode(['success' => false, 'message' => 'Create table failed', 'errors' => $errors]);
            exit;
        }
        sqlsrv_free_stmt($result);

        // ลบข้อมูลเดิม (ถ้ามี) และใส่ใหม่
        $deleteSQL = "DELETE FROM color_personality_results WHERE employee_id = ? AND evaluation_round = ?";
        $deleteStmt = sqlsrv_query($conn, $deleteSQL, array($employee_id, $evaluation_round));
        if ($deleteStmt !== false) {
            sqlsrv_free_stmt($deleteStmt);
        }

        // เพิ่มข้อมูลใหม่
        $insertSQL = "INSERT INTO color_personality_results 
                     (employee_id, evaluation_round, purple_score, yellow_score, blue_score, green_score, 
                      dominant_color, scores, answers, created_at) 
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, GETDATE())";
        
        $insertStmt = sqlsrv_query($conn, $insertSQL, array(
            $employee_id, $evaluation_round, $purple_score, $yellow_score, $blue_score, $green_score,
            $dominant_color, $scores_json, $answers
        ));

        if ($insertStmt === false) {
            $errors = sqlsrv_errors();
            echo json_encode(['success' => false, 'message' => 'Insert failed', 'errors' => $errors]);
            exit;
        }

        sqlsrv_free_stmt($insertStmt);
        sqlsrv_close($conn);

        echo json_encode(['success' => true, 'message' => 'บันทึกสำเร็จ']);

    } else {
        echo json_encode(['success' => false, 'message' => 'Not SQL Server connection']);
    }

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Exception: ' . $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine()]);
} catch (Error $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine()]);
}
?>
