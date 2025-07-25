<?php
session_start();
header('Content-Type: application/json');
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: 0");

include '../config.php';

// ตรวจสอบการล็อกอิน
if (!isset($_SESSION['employee_id'])) {
    echo json_encode(['success' => false, 'message' => 'กรุณาล็อกอินใหม่']);
    exit;
}

$employee_id = $_SESSION['employee_id'];

// ตรวจสอบ POST data
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

try {
    $conn = dbConnect();
    
    // รับข้อมูลจาก POST
    $evaluation_round = $_POST['evaluation_round'] ?? '';
    $red_score = (int)($_POST['red_score'] ?? 0);
    $blue_score = (int)($_POST['blue_score'] ?? 0);
    $yellow_score = (int)($_POST['yellow_score'] ?? 0);
    $green_score = (int)($_POST['green_score'] ?? 0);
    $dominant_color = $_POST['dominant_color'] ?? '';
    $answers = $_POST['answers'] ?? '';
    
    // ตรวจสอบข้อมูลที่จำเป็น
    if (empty($evaluation_round) || empty($dominant_color)) {
        echo json_encode(['success' => false, 'message' => 'ข้อมูลไม่ครบถ้วน']);
        exit;
    }
    
    // สร้างตารางถ้ายังไม่มี (รองรับทั้ง SQL Server และ MySQL)
    if (is_resource($conn)) {
        // SQL Server
        $createTableSQL = "
        IF NOT EXISTS (SELECT * FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_NAME = 'color_personality_results')
        BEGIN
            CREATE TABLE color_personality_results (
                id INT IDENTITY(1,1) PRIMARY KEY,
                employee_id NVARCHAR(50) NOT NULL,
                evaluation_round NVARCHAR(10) NOT NULL,
                red_score INT DEFAULT 0,
                blue_score INT DEFAULT 0,
                yellow_score INT DEFAULT 0,
                green_score INT DEFAULT 0,
                dominant_color NVARCHAR(10) NOT NULL,
                answers NTEXT,
                created_at DATETIME DEFAULT GETDATE(),
                updated_at DATETIME DEFAULT GETDATE(),
                UNIQUE(employee_id, evaluation_round)
            )
        END";
        
        $result = sqlsrv_query($conn, $createTableSQL);
        if (!$result) {
            throw new Exception('ไม่สามารถสร้างตารางได้: ' . print_r(sqlsrv_errors(), true));
        }
        
        // ตรวจสอบว่ามีข้อมูลอยู่แล้วหรือไม่
        $checkSQL = "SELECT id FROM color_personality_results 
                     WHERE employee_id = ? AND evaluation_round = ?";
        $checkStmt = sqlsrv_query($conn, $checkSQL, array($employee_id, $evaluation_round));
        
        if (!$checkStmt) {
            throw new Exception('ไม่สามารถตรวจสอบข้อมูลได้: ' . print_r(sqlsrv_errors(), true));
        }
        
        $existing = sqlsrv_fetch_array($checkStmt, SQLSRV_FETCH_ASSOC);
        sqlsrv_free_stmt($checkStmt);
        
        if ($existing) {
            // อัพเดทข้อมูลเดิม
            $updateSQL = "UPDATE color_personality_results 
                         SET red_score = ?, blue_score = ?, yellow_score = ?, green_score = ?, 
                             dominant_color = ?, answers = ?, updated_at = GETDATE()
                         WHERE employee_id = ? AND evaluation_round = ?";
            $updateStmt = sqlsrv_query($conn, $updateSQL, array(
                $red_score, $blue_score, $yellow_score, $green_score,
                $dominant_color, $answers, $employee_id, $evaluation_round
            ));
            
            if (!$updateStmt) {
                throw new Exception('ไม่สามารถอัพเดทข้อมูลได้: ' . print_r(sqlsrv_errors(), true));
            }
            
            sqlsrv_free_stmt($updateStmt);
            echo json_encode(['success' => true, 'message' => 'อัพเดทผลการทดสอบเรียบร้อยแล้ว']);
            
        } else {
            // เพิ่มข้อมูลใหม่
            $insertSQL = "INSERT INTO color_personality_results 
                         (employee_id, evaluation_round, red_score, blue_score, yellow_score, green_score, 
                          dominant_color, answers) 
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
            $insertStmt = sqlsrv_query($conn, $insertSQL, array(
                $employee_id, $evaluation_round, $red_score, $blue_score, $yellow_score, $green_score,
                $dominant_color, $answers
            ));
            
            if (!$insertStmt) {
                throw new Exception('ไม่สามารถบันทึกข้อมูลได้: ' . print_r(sqlsrv_errors(), true));
            }
            
            sqlsrv_free_stmt($insertStmt);
            echo json_encode(['success' => true, 'message' => 'บันทึกผลการทดสอบเรียบร้อยแล้ว']);
        }
        
    } else {
        // MySQL/PDO
        $createTableSQL = "
        CREATE TABLE IF NOT EXISTS color_personality_results (
            id INT AUTO_INCREMENT PRIMARY KEY,
            employee_id VARCHAR(50) NOT NULL,
            evaluation_round VARCHAR(10) NOT NULL,
            red_score INT DEFAULT 0,
            blue_score INT DEFAULT 0,
            yellow_score INT DEFAULT 0,
            green_score INT DEFAULT 0,
            dominant_color VARCHAR(10) NOT NULL,
            answers TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY unique_employee_round (employee_id, evaluation_round)
        )";
        
        if ($conn instanceof PDO) {
            // PDO
            $result = $conn->exec($createTableSQL);
            
            // ตรวจสอบว่ามีข้อมูลอยู่แล้วหรือไม่
            $checkSQL = "SELECT id FROM color_personality_results 
                         WHERE employee_id = ? AND evaluation_round = ?";
            $checkStmt = $conn->prepare($checkSQL);
            $checkStmt->execute([$employee_id, $evaluation_round]);
            $existing = $checkStmt->fetch(PDO::FETCH_ASSOC);
            
            if ($existing) {
                // อัพเดทข้อมูลเดิม
                $updateSQL = "UPDATE color_personality_results 
                             SET red_score = ?, blue_score = ?, yellow_score = ?, green_score = ?, 
                                 dominant_color = ?, answers = ?, updated_at = NOW()
                             WHERE employee_id = ? AND evaluation_round = ?";
                $updateStmt = $conn->prepare($updateSQL);
                $updateStmt->execute([
                    $red_score, $blue_score, $yellow_score, $green_score,
                    $dominant_color, $answers, $employee_id, $evaluation_round
                ]);
                
                echo json_encode(['success' => true, 'message' => 'อัพเดทผลการทดสอบเรียบร้อยแล้ว']);
                
            } else {
                // เพิ่มข้อมูลใหม่
                $insertSQL = "INSERT INTO color_personality_results 
                             (employee_id, evaluation_round, red_score, blue_score, yellow_score, green_score, 
                              dominant_color, answers) 
                             VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
                $insertStmt = $conn->prepare($insertSQL);
                $insertStmt->execute([
                    $employee_id, $evaluation_round, $red_score, $blue_score, $yellow_score, $green_score,
                    $dominant_color, $answers
                ]);
                
                echo json_encode(['success' => true, 'message' => 'บันทึกผลการทดสอบเรียบร้อยแล้ว']);
            }
            
        } else {
            // MySQLi
            $result = $conn->query($createTableSQL);
            
            // ตรวจสอบว่ามีข้อมูลอยู่แล้วหรือไม่
            $checkSQL = "SELECT id FROM color_personality_results 
                         WHERE employee_id = ? AND evaluation_round = ?";
            $checkStmt = $conn->prepare($checkSQL);
            $checkStmt->bind_param("ss", $employee_id, $evaluation_round);
            $checkStmt->execute();
            $result = $checkStmt->get_result();
            $existing = $result->fetch_assoc();
            
            if ($existing) {
                // อัพเดทข้อมูลเดิม
                $updateSQL = "UPDATE color_personality_results 
                             SET red_score = ?, blue_score = ?, yellow_score = ?, green_score = ?, 
                                 dominant_color = ?, answers = ?, updated_at = NOW()
                             WHERE employee_id = ? AND evaluation_round = ?";
                $updateStmt = $conn->prepare($updateSQL);
                $updateStmt->bind_param("iiiissss", 
                    $red_score, $blue_score, $yellow_score, $green_score,
                    $dominant_color, $answers, $employee_id, $evaluation_round
                );
                $updateStmt->execute();
                
                echo json_encode(['success' => true, 'message' => 'อัพเดทผลการทดสอบเรียบร้อยแล้ว']);
                
            } else {
                // เพิ่มข้อมูลใหม่
                $insertSQL = "INSERT INTO color_personality_results 
                             (employee_id, evaluation_round, red_score, blue_score, yellow_score, green_score, 
                              dominant_color, answers) 
                             VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
                $insertStmt = $conn->prepare($insertSQL);
                $insertStmt->bind_param("ssiiisss", 
                    $employee_id, $evaluation_round, $red_score, $blue_score, $yellow_score, $green_score,
                    $dominant_color, $answers
                );
                $insertStmt->execute();
                
                echo json_encode(['success' => true, 'message' => 'บันทึกผลการทดสอบเรียบร้อยแล้ว']);
            }
        }
    }
    
} catch (Exception $e) {
    error_log("Color Test Save Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
} finally {
    if (isset($conn)) {
        if (is_resource($conn)) {
            sqlsrv_close($conn);
        } elseif ($conn instanceof PDO) {
            $conn = null;
        } elseif ($conn instanceof mysqli) {
            $conn->close();
        }
    }
}
?>
