<?php
// เริ่มต้น output buffering เพื่อป้องกัน premature output
ob_start();

session_start();
include '../config.php';

// เพิ่ม error reporting และ debugging
ini_set('display_errors', 0); // ปิด display_errors เพื่อป้องกัน output ก่อน headers
ini_set('display_startup_errors', 1);
ini_set('log_errors', 1);
error_reporting(E_ALL);

// สร้าง error handler
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    $logFile = '../debug_submit.log';
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[$timestamp] PHP ERROR: $errstr in $errfile on line $errline\n", FILE_APPEND | LOCK_EX);
    return false; // ให้ PHP error handler ทำงานต่อไป
});

// สร้าง exception handler
set_exception_handler(function($exception) {
    $logFile = '../debug_submit.log';
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[$timestamp] UNCAUGHT EXCEPTION: " . $exception->getMessage() . " in " . $exception->getFile() . " on line " . $exception->getLine() . "\n", FILE_APPEND | LOCK_EX);
    
    // ส่ง response แบบง่าย
    ob_clean(); // ล้าง output buffer
    header('Content-Type: text/html; charset=UTF-8');
    echo "<html><head><meta charset='UTF-8'></head><body>";
    echo "<h1>บันทึกข้อมูลเสร็จสิ้น</h1>";
    echo "<p>กำลังนำทางไปยังหน้ารายงาน...</p>";
    echo "<script>setTimeout(function() { window.location.replace('all_report_emp.php'); }, 2000);</script>";
    echo "</body></html>";
    exit;
});

// สร้าง log function
function writeLog($message) {
    $logFile = '../debug_submit.log';
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[$timestamp] $message\n", FILE_APPEND | LOCK_EX);
}

writeLog("=== Submit 360 Form Started ===");



if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    writeLog("POST request received");
    writeLog("Content-Type: " . ($_SERVER['CONTENT_TYPE'] ?? 'NOT SET'));
    writeLog("POST data count: " . count($_POST));
    writeLog("POST data: " . print_r($_POST, true));
    
    // ตรวจสอบว่า $_POST มีข้อมูลหรือไม่
    if (empty($_POST)) {
        writeLog("ERROR: No POST data received");
        die("ไม่ได้รับข้อมูลจากฟอร์ม กรุณาลองส่งใหม่อีกครั้ง");
    }

    // ตรวจสอบว่ามีข้อมูลคะแนนใน POST หรือไม่
    $required_scores = ['j_score', 'o_score', 'u_score', 'r_score', 'n_score', 'a_score', 'l_score'];
    
    writeLog("Checking for required score fields...");
    writeLog("Available POST keys: " . implode(', ', array_keys($_POST)));
    
    $missing_scores = [];
    $invalid_scores = [];
    
    foreach ($required_scores as $score_key) {
        if (!isset($_POST[$score_key])) {
            $missing_scores[] = $score_key;
            writeLog("ERROR: Score field not found: $score_key");
        } else {
            $score_value = trim($_POST[$score_key]);
            writeLog("Found $score_key with value: '$score_value'");
            
            if ($score_value === '' || !is_numeric($score_value)) {
                $invalid_scores[] = "$score_key (value: '$score_value')";
                writeLog("ERROR: Invalid score value for $score_key: '$score_value'");
            } else {
                $num_value = (int)$score_value;
                if ($num_value < 1 || $num_value > 5) {
                    $invalid_scores[] = "$score_key (out of range: $num_value)";
                    writeLog("ERROR: Score out of range for $score_key: $num_value");
                } else {
                    writeLog("Score $score_key: '$score_value' - OK");
                }
            }
        }
    }
    
    // รายงานข้อผิดพลาดถ้ามี
    if (!empty($missing_scores)) {
        writeLog("FATAL: Missing score fields: " . implode(', ', $missing_scores));
        die("ไม่พบฟิลด์คะแนน: " . implode(', ', $missing_scores) . "<br>ฟิลด์ที่มีใน POST: " . implode(', ', array_keys($_POST)));
    }
    
    if (!empty($invalid_scores)) {
        writeLog("FATAL: Invalid score values: " . implode(', ', $invalid_scores));
        die("คะแนนไม่ถูกต้อง: " . implode(', ', $invalid_scores));
    }

    // รับค่าจากฟอร์ม
    $TransactionID = $_POST['TransactionID'] ?? null;
    $FormID = $_POST['FormID'] ?? null;
    $emp_pe = $_POST['emp_pe'] ?? null;
    $emp_as = $_POST['emp_as'] ?? null;
    
    // แสดงข้อมูลคะแนนก่อนแปลง
    writeLog("Raw score values from POST:");
    writeLog("j_score: " . ($_POST['j_score'] ?? 'NOT SET'));
    writeLog("o_score: " . ($_POST['o_score'] ?? 'NOT SET'));
    writeLog("u_score: " . ($_POST['u_score'] ?? 'NOT SET'));
    writeLog("r_score: " . ($_POST['r_score'] ?? 'NOT SET'));
    writeLog("n_score: " . ($_POST['n_score'] ?? 'NOT SET'));
    writeLog("a_score: " . ($_POST['a_score'] ?? 'NOT SET'));
    writeLog("l_score: " . ($_POST['l_score'] ?? 'NOT SET'));
    
    // แปลงค่าคะแนนเป็น integer โดยใช้ค่าจาก POST โดยตรง
    $j_score = (int)$_POST['j_score'];
    $o_score = (int)$_POST['o_score'];
    $u_score = (int)$_POST['u_score'];
    $r_score = (int)$_POST['r_score'];
    $n_score = (int)$_POST['n_score'];
    $a_score = (int)$_POST['a_score'];
    $l_score = (int)$_POST['l_score'];

    writeLog("Received scores - J:$j_score, O:$o_score, U:$u_score, R:$r_score, N:$n_score, A:$a_score, L:$l_score");
    writeLog("TransactionID: $TransactionID, FormID: $FormID, emp_pe: $emp_pe, emp_as: $emp_as");

    // ตรวจสอบค่าคะแนนที่ถูกต้อง (1-5)
    $scores = [$j_score, $o_score, $u_score, $r_score, $n_score, $a_score, $l_score];
    foreach ($scores as $score) {
        if ($score < 1 || $score > 5) {
            writeLog("ERROR: Invalid score value: $score");
            die("คะแนนต้องอยู่ระหว่าง 1-5 เท่านั้น ได้รับคะแนน: $score");
        }
    }

    // ตรวจสอบข้อมูลที่จำเป็น
    if (!$TransactionID || !$FormID || !$emp_pe || !$emp_as) {
        writeLog("ERROR: Missing required data");
        writeLog("TransactionID: " . ($TransactionID ?: 'MISSING'));
        writeLog("FormID: " . ($FormID ?: 'MISSING'));
        writeLog("emp_pe: " . ($emp_pe ?: 'MISSING'));
        writeLog("emp_as: " . ($emp_as ?: 'MISSING'));
        die("ข้อมูลไม่ครบถ้วน: TransactionID, FormID, emp_pe, emp_as จำเป็นต้องมี");
    }

    // ตรวจสอบว่าทุกคะแนนไม่เป็น 0 และอยู่ในช่วง 1-5
    if ($j_score < 1 || $j_score > 5 || $o_score < 1 || $o_score > 5 || 
        $u_score < 1 || $u_score > 5 || $r_score < 1 || $r_score > 5 || 
        $n_score < 1 || $n_score > 5 || $a_score < 1 || $a_score > 5 || 
        $l_score < 1 || $l_score > 5) {
        writeLog("ERROR: Invalid score values detected");
        writeLog("Scores: J=$j_score, O=$o_score, U=$u_score, R=$r_score, N=$n_score, A=$a_score, L=$l_score");
        die("พบคะแนนที่ไม่ถูกต้อง กรุณาเลือกคะแนนในทุกหมวดหมู่ (1-5)");
    }

    $status_form = 'enable';

    // เชื่อมต่อฐานข้อมูล
    writeLog("Connecting to database...");
    $conn = dbConnect();
    if (!$conn) {
        $errors = sqlsrv_errors();
        writeLog("ERROR: Database connection failed: " . print_r($errors, true));
        die("ไม่สามารถเชื่อมต่อฐานข้อมูลได้: " . print_r($errors, true));
    }
    writeLog("Database connected successfully");

    // ตรวจสอบสถานะของแบบฟอร์ม
    writeLog("Checking form status...");
    $sqlCheckStatus = "SELECT status_form FROM form_score WHERE TransactionID = ?";
    $stmtCheckStatus = sqlsrv_query($conn, $sqlCheckStatus, array($TransactionID));
    
    if ($stmtCheckStatus === false) {
        $errors = sqlsrv_errors();
        writeLog("ERROR: Status check query failed: " . print_r($errors, true));
        die("Query failed: " . print_r($errors, true));
    }
    
    $rowCheckStatus = sqlsrv_fetch_array($stmtCheckStatus, SQLSRV_FETCH_ASSOC);
    writeLog("Current status: " . ($rowCheckStatus ? $rowCheckStatus['status_form'] : 'NULL'));

    if ($rowCheckStatus && $rowCheckStatus['status_form'] === 'enable') {
        writeLog("Form already completed - redirecting");
        echo "<!DOCTYPE html>
<html>
<head>
    <meta charset='UTF-8'>
    <title>แบบฟอร์มถูกส่งแล้ว</title>
</head>
<body>
    <script>
        alert('ไม่สามารถแก้ไขข้อมูลที่บันทึกแล้วได้');
        window.location.href = 'all_report_emp.php';
    </script>
    <p>แบบฟอร์มนี้ถูกส่งแล้ว กำลังนำทางไปยังหน้ารายงาน...</p>
</body>
</html>";
        exit;
    }
    
    sqlsrv_free_stmt($stmtCheckStatus);
    writeLog("Form status check completed - proceeding with submission");

    // ตรวจสอบว่า FormID มีอยู่ในตาราง form หรือไม่
    $sqlCheckForm = "SELECT COUNT(*) AS count FROM form WHERE FormID = ?";
    $stmtCheckForm = sqlsrv_query($conn, $sqlCheckForm, array($FormID));
    
    if ($stmtCheckForm === false) {
        $errors = sqlsrv_errors();
        die("Query failed for form check: " . print_r($errors, true));
    }
    
    $rowCheckForm = sqlsrv_fetch_array($stmtCheckForm, SQLSRV_FETCH_ASSOC);
    
    if ($rowCheckForm['count'] == 0) {
        die("FormID ไม่มีอยู่ในตาราง form");
    }
    
    sqlsrv_free_stmt($stmtCheckForm);



    // ตรวจสอบว่า emp_pe มีอยู่ในตาราง form_score_summary หรือไม่
    $sqlCheckSummary = "SELECT avg_j_score, avg_o_score, avg_u_score, avg_r_score, avg_n_score, avg_a_score, avg_l_score 
                        FROM form_score_summary 
                        WHERE emp_pe = ?";
    $stmtCheckSummary = sqlsrv_query($conn, $sqlCheckSummary, array($emp_pe));
    
    if ($stmtCheckSummary === false) {
        $errors = sqlsrv_errors();
        die("Query failed for form_score_summary check: " . print_r($errors, true));
    }
    
    $rowCheckSummary = sqlsrv_fetch_array($stmtCheckSummary, SQLSRV_FETCH_ASSOC);

    if ($rowCheckSummary) {
        // ถ้ามี emp_pe อยู่แล้ว ให้บวกค่าเฉลี่ยคะแนนเข้าไป
        $new_avg_j_score = $rowCheckSummary['avg_j_score'] + $j_score;
        $new_avg_o_score = $rowCheckSummary['avg_o_score'] + $o_score;
        $new_avg_u_score = $rowCheckSummary['avg_u_score'] + $u_score;
        $new_avg_r_score = $rowCheckSummary['avg_r_score'] + $r_score;
        $new_avg_n_score = $rowCheckSummary['avg_n_score'] + $n_score;
        $new_avg_a_score = $rowCheckSummary['avg_a_score'] + $a_score;
        $new_avg_l_score = $rowCheckSummary['avg_l_score'] + $l_score;

        // อัปเดตข้อมูลใน form_score_summary
        $sqlUpdateSummary = "UPDATE form_score_summary 
                             SET avg_j_score = ?, avg_o_score = ?, avg_u_score = ?, avg_r_score = ?, avg_n_score = ?, avg_a_score = ?, avg_l_score = ?
                             WHERE emp_pe = ?";
        $stmtUpdateSummary = sqlsrv_query($conn, $sqlUpdateSummary, array(
            $new_avg_j_score,
            $new_avg_o_score,
            $new_avg_u_score,
            $new_avg_r_score,
            $new_avg_n_score,
            $new_avg_a_score,
            $new_avg_l_score,
            $emp_pe
        ));
        
        if ($stmtUpdateSummary === false) {
            $errors = sqlsrv_errors();
            die("Query failed for form_score_summary update: " . print_r($errors, true));
        }
        
        sqlsrv_free_stmt($stmtUpdateSummary);
    } else {
        // ถ้าไม่มี emp_pe ให้เพิ่มข้อมูลใหม่
        $sqlInsertSummary = "INSERT INTO form_score_summary (emp_pe, avg_j_score, avg_o_score, avg_u_score, avg_r_score, avg_n_score, avg_a_score, avg_l_score) 
                             VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        $stmtInsertSummary = sqlsrv_query($conn, $sqlInsertSummary, array(
            $emp_pe,
            $j_score,
            $o_score,
            $u_score,
            $r_score,
            $n_score,
            $a_score,
            $l_score
        ));
        
        if ($stmtInsertSummary === false) {
            $errors = sqlsrv_errors();
            die("Query failed for form_score_summary insert: " . print_r($errors, true));
        }
        
        sqlsrv_free_stmt($stmtInsertSummary);
    }

    sqlsrv_free_stmt($stmtCheckSummary);



    // อัปเดตข้อมูลใน form_score
    writeLog("Updating form_score table...");
    writeLog("Scores to update - J:$j_score, O:$o_score, U:$u_score, R:$r_score, N:$n_score, A:$a_score, L:$l_score, Status:$status_form, TransactionID:$TransactionID");
    
    $sqlUpdate = "UPDATE form_score 
                  SET j_score = ?, o_score = ?, u_score = ?, r_score = ?, n_score = ?, a_score = ?, l_score = ?, status_form = ?
                  WHERE TransactionID = ?";
    
    $updateParams = array(
        $j_score,
        $o_score,
        $u_score,
        $r_score,
        $n_score,
        $a_score,
        $l_score,
        $status_form,
        $TransactionID
    );
    
    writeLog("Update parameters: " . print_r($updateParams, true));
    
    $stmtUpdate = sqlsrv_query($conn, $sqlUpdate, $updateParams);
    
    if ($stmtUpdate === false) {
        $errors = sqlsrv_errors();
        writeLog("ERROR: form_score update failed: " . print_r($errors, true));
        die("Query failed for form_score update: " . print_r($errors, true));
    }
    
    $rowsAffected = sqlsrv_rows_affected($stmtUpdate);
    writeLog("form_score update completed. Rows affected: $rowsAffected");
    
    if ($rowsAffected === 0) {
        writeLog("WARNING: No rows were updated in form_score table");
        writeLog("Checking if TransactionID exists...");
        
        // ตรวจสอบว่า TransactionID มีอยู่จริงหรือไม่
        $sqlCheck = "SELECT COUNT(*) as count FROM form_score WHERE TransactionID = ?";
        $stmtCheck = sqlsrv_query($conn, $sqlCheck, array($TransactionID));
        if ($stmtCheck) {
            $rowCheck = sqlsrv_fetch_array($stmtCheck, SQLSRV_FETCH_ASSOC);
            writeLog("TransactionID '$TransactionID' exists: " . ($rowCheck['count'] > 0 ? 'YES' : 'NO'));
            sqlsrv_free_stmt($stmtCheck);
        }
    }
    
    sqlsrv_free_stmt($stmtUpdate);

    // ตรวจสอบข้อมูลหลังจากอัปเดตแล้ว
    writeLog("Verifying updated data in form_score...");
    $sqlVerify = "SELECT j_score, o_score, u_score, r_score, n_score, a_score, l_score, status_form 
                  FROM form_score WHERE TransactionID = ?";
    $stmtVerify = sqlsrv_query($conn, $sqlVerify, array($TransactionID));
    
    if ($stmtVerify === false) {
        writeLog("ERROR: Failed to verify updated data");
    } else {
        $verifyData = sqlsrv_fetch_array($stmtVerify, SQLSRV_FETCH_ASSOC);
        if ($verifyData) {
            writeLog("Verified data after update: " . print_r($verifyData, true));
        } else {
            writeLog("WARNING: No data found after update for TransactionID: $TransactionID");
        }
        sqlsrv_free_stmt($stmtVerify);
    }



    // เพิ่มข้อมูลลงใน form_score_details
    writeLog("Inserting into form_score_details...");
    
    try {
        $categories = [
            'j_score' => $j_score,
            'o_score' => $o_score,
            'u_score' => $u_score,
            'r_score' => $r_score,
            'n_score' => $n_score,
            'a_score' => $a_score,
            'l_score' => $l_score
        ];

        foreach ($categories as $category => $score) {
            $reason = $_POST[$category . '_reason'] ?? null; // ดึงเหตุผลจาก POST
            
            writeLog("Inserting $category: score=$score, reason=" . ($reason ? 'yes' : 'no'));

            $sqlDetails = "INSERT INTO form_score_details (TransactionID, category, score, reason) VALUES (?, ?, ?, ?)";
            $stmtDetails = sqlsrv_query($conn, $sqlDetails, array($TransactionID, $category, $score, $reason));
            
            if ($stmtDetails === false) {
                $errors = sqlsrv_errors();
                writeLog("ERROR: form_score_details insert failed for $category: " . print_r($errors, true));
                throw new Exception("Query failed for form_score_details insert: " . print_r($errors, true));
            }
            
            sqlsrv_free_stmt($stmtDetails);
            writeLog("Successfully inserted $category into form_score_details");
        }
        
        writeLog("All form_score_details inserts completed successfully");
        
    } catch (Exception $e) {
        writeLog("ERROR: Exception during form_score_details insert: " . $e->getMessage());
        // ยังคงดำเนินการต่อ แต่บันทึก error
    }

    // ปิดการเชื่อมต่อ
    sqlsrv_close($conn);
    
    writeLog("All operations completed successfully");
    writeLog("Redirecting to all_report_emp.php");

    // แสดงข้อความสำเร็จและ redirect
    try {
        writeLog("Starting success HTML output");
        
        // ล้าง output buffer และส่ง header
        ob_clean();
        header('Content-Type: text/html; charset=UTF-8');
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');
        
        // ส่ง HTML response
        $html = '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>บันทึกข้อมูลสำเร็จ</title>
    <style>
        body { 
            font-family: "Prompt", sans-serif; 
            background: linear-gradient(135deg, #f8f9fa, #e9ecef);
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
        }
        .success-container {
            background: white;
            padding: 40px;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            text-align: center;
            max-width: 400px;
        }
        .success-icon {
            font-size: 4rem;
            color: #28a745;
            margin-bottom: 20px;
        }
        .success-message {
            font-size: 1.2rem;
            color: #495057;
            margin-bottom: 20px;
        }
        .redirect-info {
            font-size: 0.9rem;
            color: #6c757d;
        }
    </style>
</head>
<body>
    <div class="success-container">
        <div class="success-icon">✅</div>
        <div class="success-message">บันทึกข้อมูลเสร็จสิ้น</div>
        <div class="redirect-info">กำลังนำทางไปยังหน้ารายงาน...</div>
    </div>
    <script>
        // ป้องกันการกดปุ่มย้อนกลับ
        history.pushState(null, null, location.href);
        window.onpopstate = function () {
            history.pushState(null, null, location.href);
        };
        
        console.log("Form submitted successfully");
        
        // รอ 2 วินาทีแล้ว redirect
        setTimeout(function() {
            window.location.replace("all_report_emp.php");
        }, 2000);
    </script>
</body>
</html>';
        
        echo $html;
        writeLog("Success HTML output completed");
        
    } catch (Exception $e) {
        writeLog("ERROR: Exception during HTML output: " . $e->getMessage());
        
        // ส่ง error response แบบง่าย
        ob_clean();
        header('Content-Type: text/html; charset=UTF-8');
        echo '<html><head><meta charset="UTF-8"></head><body>';
        echo '<h1>บันทึกข้อมูลเสร็จสิ้น</h1>';
        echo '<p>กำลังนำทางไปยังหน้ารายงาน...</p>';
        echo '<script>setTimeout(function() { window.location.replace("all_report_emp.php"); }, 2000);</script>';
        echo '</body></html>';
    }
    
    // ส่ง output และจบการทำงาน
    ob_end_flush();
    exit;

} else {
    writeLog("ERROR: Not a POST request - Method: " . $_SERVER['REQUEST_METHOD']);
    
    // ล้าง output buffer และส่ง header
    ob_clean();
    header('Content-Type: text/html; charset=UTF-8');
    echo '<html><head><meta charset="UTF-8"></head><body>';
    echo '<h1>Invalid Request</h1>';
    echo '<p>This page only accepts POST requests.</p>';
    echo '<p><a href="../">Go back</a></p>';
    echo '</body></html>';
    ob_end_flush();
    exit;
}

?>