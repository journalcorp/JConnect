<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
header('Content-Type: application/json; charset=utf-8');

// เพิ่ม logging function
function writeLog($message) {
    $logFile = '../debug_save.log';
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[$timestamp] $message\n", FILE_APPEND | LOCK_EX);
}

session_start();
include '../config.php';

writeLog("=== Save Form Score Started ===");

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Method not allowed');
    }

    // อ่านข้อมูล JSON
    $rawInput = file_get_contents('php://input');
    writeLog("Raw input: " . $rawInput);
    
    $data = json_decode($rawInput, true);
    if (!$data) {
        throw new Exception('Invalid JSON input');
    }

    // ตรวจสอบข้อมูลที่จำเป็น
    $form = $data['form'] ?? null;
    $emp_as = $data['emp_as'] ?? null;
    $emp_pe = $data['emp_pe'] ?? [];
    $evaluation_period = $data['evaluation_period'] ?? null;
    $start_date = $data['start_date'] ?? null;
    $end_date = $data['end_date'] ?? null;

    writeLog("Parsed - form: $form, emp_as: $emp_as, emp_pe count: " . count($emp_pe));

    if (!$form || !$emp_as || empty($emp_pe)) {
        throw new Exception('ข้อมูลไม่ครบถ้วน: ต้องมี form, emp_as, และ emp_pe');
    }

    // เชื่อมต่อฐานข้อมูล
    $conn = dbConnect();
    if (!$conn) {
        $errors = sqlsrv_errors();
        writeLog("DB connection failed: " . print_r($errors, true));
        throw new Exception('ไม่สามารถเชื่อมต่อฐานข้อมูลได้');
    }

    writeLog("Database connected successfully");

    // ตรวจสอบ FormID
    $sqlCheckForm = "SELECT COUNT(*) AS count FROM form WHERE FormID = ?";
    $result = sqlsrv_query($conn, $sqlCheckForm, array($form));
    
    if ($result === false) {
        $errors = sqlsrv_errors();
        writeLog("Form check failed: " . print_r($errors, true));
        throw new Exception('เกิดข้อผิดพลาดในการตรวจสอบ FormID');
    }
    
    $row = sqlsrv_fetch_array($result, SQLSRV_FETCH_ASSOC);
    sqlsrv_free_stmt($result);
    
    if (!$row || $row['count'] == 0) {
        writeLog("FormID not found: $form");
        
        // แสดง FormID ที่มีอยู่
        $sqlGetForms = "SELECT TOP 5 FormID FROM form";
        $resultForms = sqlsrv_query($conn, $sqlGetForms);
        $availableForms = [];
        if ($resultForms) {
            while ($formRow = sqlsrv_fetch_array($resultForms, SQLSRV_FETCH_ASSOC)) {
                $availableForms[] = $formRow['FormID'];
            }
            sqlsrv_free_stmt($resultForms);
        }
        
        $message = "FormID '$form' ไม่มีในระบบ";
        if (!empty($availableForms)) {
            $message .= " (FormID ที่มี: " . implode(', ', $availableForms) . ")";
        }
        throw new Exception($message);
    }

    writeLog("FormID validation passed");

    // ประมวลผลข้อมูลแต่ละพนักงาน
    $processedCount = 0;
    
    foreach ($emp_pe as $emp_pe_item) {
        writeLog("Processing employee: $emp_pe_item");
        
        // ตรวจสอบว่ามีข้อมูลอยู่แล้วหรือไม่
        $sqlCheck = "SELECT COUNT(*) AS count FROM form_score WHERE FormID = ? AND emp_as = ? AND emp_pe = ?";
        $result = sqlsrv_query($conn, $sqlCheck, array($form, $emp_as, $emp_pe_item));
        
        if ($result === false) {
            throw new Exception('ไม่สามารถตรวจสอบข้อมูลใน form_score ได้');
        }
        
        $row = sqlsrv_fetch_array($result, SQLSRV_FETCH_ASSOC);
        $exists = $row['count'] > 0;
        sqlsrv_free_stmt($result);
        
        writeLog("Employee $emp_pe_item exists: " . ($exists ? 'yes' : 'no'));
        
        // บันทึกหรืออัพเดทข้อมูล
        if ($exists) {
            $sql = "UPDATE form_score SET status_form = 'disable', start_date = ?, end_date = ? WHERE FormID = ? AND emp_as = ? AND emp_pe = ?";
            $params = array($start_date, $end_date, $form, $emp_as, $emp_pe_item);
            writeLog("Updating existing record for $emp_pe_item");
        } else {
            $sql = "INSERT INTO form_score (FormID, emp_as, emp_pe, status_form, start_date, end_date) VALUES (?, ?, ?, 'disable', ?, ?)";
            $params = array($form, $emp_as, $emp_pe_item, $start_date, $end_date);
            writeLog("Inserting new record for $emp_pe_item");
        }
        
        $result = sqlsrv_query($conn, $sql, $params);
        if ($result === false) {
            $errors = sqlsrv_errors();
            writeLog("SQL failed for $emp_pe_item: " . print_r($errors, true));
            throw new Exception("ไม่สามารถบันทึก form_score สำหรับ $emp_pe_item ได้");
        }
        sqlsrv_free_stmt($result);
        
        writeLog("form_score operation successful for $emp_pe_item");
        $processedCount++;
    }

    // ประมวลผลขั้นตอนต่อไป (TransactionID, summary, evaluation_forms)
    foreach ($emp_pe as $emp_pe_item) {
        // ดึง TransactionID
        $sqlGetTxnId = "SELECT TransactionID FROM form_score WHERE FormID = ? AND emp_as = ? AND emp_pe = ?";
        $result = sqlsrv_query($conn, $sqlGetTxnId, array($form, $emp_as, $emp_pe_item));
        
        if ($result === false) {
            throw new Exception("ไม่สามารถดึง TransactionID สำหรับ $emp_pe_item ได้");
        }
        
        $row = sqlsrv_fetch_array($result, SQLSRV_FETCH_ASSOC);
        $transactionID = $row['TransactionID'] ?? null;
        sqlsrv_free_stmt($result);
        
        if (!$transactionID) {
            throw new Exception("ไม่พบ TransactionID สำหรับ $emp_pe_item");
        }
        
        writeLog("Got TransactionID $transactionID for $emp_pe_item");
        
        // ตรวจสอบและบันทึก form_score_summary
        $sqlCheckSummary = "SELECT COUNT(*) AS count FROM form_score_summary WHERE TransactionID = ?";
        $result = sqlsrv_query($conn, $sqlCheckSummary, array($transactionID));
        
        if ($result === false) {
            throw new Exception("ไม่สามารถตรวจสอบ form_score_summary ได้");
        }
        
        $row = sqlsrv_fetch_array($result, SQLSRV_FETCH_ASSOC);
        $summaryExists = $row['count'] > 0;
        sqlsrv_free_stmt($result);
        
        if (!$summaryExists) {
            $sqlInsertSummary = "INSERT INTO form_score_summary (TransactionID, FormID, emp_pe) VALUES (?, ?, ?)";
            $result = sqlsrv_query($conn, $sqlInsertSummary, array($transactionID, $form, $emp_pe_item));
            
            if ($result === false) {
                throw new Exception("ไม่สามารถบันทึก form_score_summary ได้");
            }
            sqlsrv_free_stmt($result);
            writeLog("Inserted form_score_summary for $emp_pe_item");
        }
        
        // ตรวจสอบและบันทึก evaluation_forms
        $sqlCheckEval = "SELECT COUNT(*) AS count FROM evaluation_forms WHERE TransactionID = ? AND form_id = ? AND emp_as = ? AND emp_pe = ?";
        $result = sqlsrv_query($conn, $sqlCheckEval, array($transactionID, $form, $emp_as, $emp_pe_item));
        
        if ($result === false) {
            throw new Exception("ไม่สามารถตรวจสอบ evaluation_forms ได้");
        }
        
        $row = sqlsrv_fetch_array($result, SQLSRV_FETCH_ASSOC);
        $evalExists = $row['count'] > 0;
        sqlsrv_free_stmt($result);
        
        if ($evalExists) {
            $sqlUpdateEval = "UPDATE evaluation_forms SET evaluation_period = ?, start_date = ?, end_date = ?, status = 'enable' WHERE TransactionID = ? AND form_id = ? AND emp_as = ? AND emp_pe = ?";
            $result = sqlsrv_query($conn, $sqlUpdateEval, array($evaluation_period, $start_date, $end_date, $transactionID, $form, $emp_as, $emp_pe_item));
        } else {
            $sqlInsertEval = "INSERT INTO evaluation_forms (TransactionID, form_id, emp_as, emp_pe, evaluation_period, start_date, end_date, status) VALUES (?, ?, ?, ?, ?, ?, ?, 'enable')";
            $result = sqlsrv_query($conn, $sqlInsertEval, array($transactionID, $form, $emp_as, $emp_pe_item, $evaluation_period, $start_date, $end_date));
        }
        
        if ($result === false) {
            throw new Exception("ไม่สามารถบันทึก evaluation_forms สำหรับ $emp_pe_item ได้");
        }
        sqlsrv_free_stmt($result);
        writeLog("evaluation_forms operation successful for $emp_pe_item");
    }

    sqlsrv_close($conn);
    
    writeLog("All operations completed successfully. Processed $processedCount employees.");
    echo json_encode([
        'success' => true, 
        'message' => "บันทึกข้อมูลสำเร็จ! ประมวลผล $processedCount พนักงาน",
        'processed_count' => $processedCount
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    writeLog("Error: " . $e->getMessage());
    
    if (isset($conn)) {
        sqlsrv_close($conn);
    }
    
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}

writeLog("=== Save Form Score Ended ===");
?>
