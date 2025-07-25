<?php
// Import Excel - แก้ไข 500 error
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', 'import_excel_errors.log');

// เพิ่ม memory และ execution time สำหรับไฟล์ขนาดใหญ่
ini_set('memory_limit', '256M');
ini_set('max_execution_time', 300);

// เริ่ม session ก่อนอย่างอื่น (ถ้ายังไม่ได้เริ่ม)
if (session_status() == PHP_SESSION_NONE) {
    @session_start(); // ใช้ @ เพื่อซ่อน warning
}

// เปิดการบัฟเฟอร์ Output
ob_start();

// ตัวแปรสำหรับเก็บข้อความ
$message = '';
$message_type = '';
$success_count = 0;
$error_count = 0;

try {
    // เชื่อมต่อฐานข้อมูล (config.php จะจัดการ session เอง)
    require_once '../config.php';
    
    // ตรวจสอบ PhpSpreadsheet
    if (!file_exists('../vendor/autoload.php')) {
        throw new Exception("PhpSpreadsheet library not found. Please run: composer require phpoffice/phpspreadsheet");
    }
    
    require_once '../vendor/autoload.php';
    
    // ประมวลผลการอัปโหลดไฟล์
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['excelFile'])) {
        
        // ตรวจสอบการอัปโหลดไฟล์
        if ($_FILES['excelFile']['error'] !== UPLOAD_ERR_OK) {
            $error_messages = [
                UPLOAD_ERR_INI_SIZE => 'ไฟล์มีขนาดใหญ่เกินไป (php.ini)',
                UPLOAD_ERR_FORM_SIZE => 'ไฟล์มีขนาดใหญ่เกินไป (form)',
                UPLOAD_ERR_PARTIAL => 'อัปโหลดไฟล์ไม่สมบูรณ์',
                UPLOAD_ERR_NO_FILE => 'ไม่มีไฟล์ที่อัปโหลด',
                UPLOAD_ERR_NO_TMP_DIR => 'ไม่มีโฟลเดอร์ temp',
                UPLOAD_ERR_CANT_WRITE => 'ไม่สามารถเขียนไฟล์ได้',
                UPLOAD_ERR_EXTENSION => 'การอัปโหลดถูกหยุดโดย extension'
            ];
            
            $error_code = $_FILES['excelFile']['error'];
            $message = $error_messages[$error_code] ?? "เกิดข้อผิดพลาดในการอัปโหลด (รหัส: $error_code)";
            $message_type = 'error';
        } else {
            
            // ประมวลผลไฟล์ Excel
            $filePath = $_FILES['excelFile']['tmp_name'];
            
            try {
                // Debug: เริ่มประมวลผลไฟล์
                error_log("Starting Excel processing for file: " . $_FILES['excelFile']['name']);
                
                // อ่านไฟล์ Excel
                $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($filePath);
                error_log("Excel file loaded successfully");
                
                $sheet = $spreadsheet->getActiveSheet();
                $rows = $sheet->toArray();
                error_log("Excel converted to array, found " . count($rows) . " rows");
                
                // เชื่อมต่อฐานข้อมูล
                $conn = dbConnect();
                if (!$conn) {
                    throw new Exception("ไม่สามารถเชื่อมต่อฐานข้อมูลได้: " . print_r(sqlsrv_errors(), true));
                }
                error_log("Database connected successfully");
                
                $success_count = 0;
                $error_count = 0;
                
                // ประมวลผลแต่ละแถว
                foreach ($rows as $index => $row) {
                    if ($index === 0) continue; // ข้าม header
                    
                    // ตรวจสอบข้อมูลพื้นฐาน
                    if (empty($row[0]) || empty($row[1]) || empty($row[2])) {
                        $error_count++;
                        continue;
                    }
                    
                    try {                $FormID = trim($row[0]);
                $emp_as_raw = trim($row[1]);
                $emp_pe_raw = trim($row[2]);
                
                // แปลงรหัสพนักงานเป็นตัวเลข
                // รองรับทั้งแบบ string ที่เป็นตัวเลข (เช่น "210031") และแบบ USER001 format
                if (is_numeric($emp_as_raw)) {
                    $emp_as_numeric = (int)$emp_as_raw;
                    $emp_as = $emp_as_raw;
                } else if (preg_match('/(\d+)$/', $emp_as_raw, $matches)) {
                    $emp_as_numeric = (int)$matches[1];
                    $emp_as = $emp_as_raw;
                } else {
                    $emp_as_numeric = null;
                    $emp_as = $emp_as_raw;
                }
                
                if (is_numeric($emp_pe_raw)) {
                    $emp_pe_numeric = (int)$emp_pe_raw;
                    $emp_pe = $emp_pe_raw;
                } else if (preg_match('/(\d+)$/', $emp_pe_raw, $matches)) {
                    $emp_pe_numeric = (int)$matches[1];
                    $emp_pe = $emp_pe_raw;
                } else {
                    $emp_pe_numeric = null;
                    $emp_pe = $emp_pe_raw;
                }
                
                error_log("Processing: FormID=$FormID, emp_as_raw=$emp_as_raw, emp_pe_raw=$emp_pe_raw");
                error_log("Converted: emp_as_numeric=$emp_as_numeric, emp_pe_numeric=$emp_pe_numeric");
                        $evaluation_period = trim($row[3] ?? 'ไม่ระบุ');
                        $start_date = trim($row[4] ?? date('Y-m-d'));
                        $end_date = trim($row[5] ?? date('Y-m-d'));
                        
                        // Debug: Log the data being processed
                        error_log("Processing: FormID=$FormID, emp_as=$emp_as, emp_pe=$emp_pe");
                        
                        // ตรวจสอบว่ามีข้อมูลใน form_score แล้วหรือไม่
                        $sqlCheck = "SELECT COUNT(*) AS count FROM form_score WHERE FormID = ? AND emp_as = ? AND emp_pe = ?";
                        $resultCheck = sqlsrv_query($conn, $sqlCheck, array($FormID, $emp_as, $emp_pe));
                        if ($resultCheck === false) {
                            throw new Exception("Check query failed: " . print_r(sqlsrv_errors(), true));
                        }
                        
                        $rowCheck = sqlsrv_fetch_array($resultCheck, SQLSRV_FETCH_ASSOC);
                        $exists = $rowCheck['count'] > 0;
                        sqlsrv_free_stmt($resultCheck);
                        
                        error_log("Data exists check: " . ($exists ? 'Yes' : 'No') . " for FormID=$FormID, emp_as=$emp_as, emp_pe=$emp_pe");
                        
                        if ($exists) {
                            // อัปเดตข้อมูลที่มีอยู่
                            $sqlUpdate = "UPDATE form_score SET status_form = 'disable', start_date = ?, end_date = ? WHERE FormID = ? AND emp_as = ? AND emp_pe = ?";
                            $resultUpdate = sqlsrv_query($conn, $sqlUpdate, array($start_date, $end_date, $FormID, $emp_as, $emp_pe));
                            if ($resultUpdate === false) {
                                throw new Exception("Update failed: " . print_r(sqlsrv_errors(), true));
                            }
                            sqlsrv_free_stmt($resultUpdate);
                            error_log("Updated existing record for FormID=$FormID, emp_as=$emp_as, emp_pe=$emp_pe");
                        } else {
                            // เพิ่มข้อมูลใหม่
                            $sqlInsert = "INSERT INTO form_score (FormID, emp_as, emp_pe, status_form, start_date, end_date) VALUES (?, ?, ?, 'disable', ?, ?)";
                            $resultInsert = sqlsrv_query($conn, $sqlInsert, array($FormID, $emp_as, $emp_pe, $start_date, $end_date));
                            if ($resultInsert === false) {
                                throw new Exception("Insert failed: " . print_r(sqlsrv_errors(), true));
                            }
                            sqlsrv_free_stmt($resultInsert);
                            error_log("Inserted new record for FormID=$FormID, emp_as=$emp_as, emp_pe=$emp_pe");
                        }
                        
                        // ==== เริ่มการใช้โลจิกแบบ save_form_score.php ====
                        
                        // ดึง TransactionID จาก form_score ที่เพิ่งบันทึกหรืออัปเดต
                        $sqlGetTxnId = "SELECT TransactionID FROM form_score WHERE FormID = ? AND emp_as = ? AND emp_pe = ?";
                        $resultTxnId = sqlsrv_query($conn, $sqlGetTxnId, array($FormID, $emp_as, $emp_pe));
                        
                        if ($resultTxnId === false) {
                            throw new Exception("ไม่สามารถดึง TransactionID สำหรับ FormID=$FormID, emp_as=$emp_as, emp_pe=$emp_pe ได้");
                        }
                        
                        $rowTxnId = sqlsrv_fetch_array($resultTxnId, SQLSRV_FETCH_ASSOC);
                        $transactionID = $rowTxnId['TransactionID'] ?? null;
                        sqlsrv_free_stmt($resultTxnId);
                        
                        if (!$transactionID) {
                            throw new Exception("ไม่พบ TransactionID สำหรับ FormID=$FormID, emp_as=$emp_as, emp_pe=$emp_pe");
                        }
                        
                        error_log("Got TransactionID $transactionID for FormID=$FormID, emp_as=$emp_as, emp_pe=$emp_pe");
                        
                        // ตรวจสอบและบันทึก form_score_summary
                        $sqlCheckSummary = "SELECT COUNT(*) AS count FROM form_score_summary WHERE TransactionID = ?";
                        $resultCheckSummary = sqlsrv_query($conn, $sqlCheckSummary, array($transactionID));
                        
                        if ($resultCheckSummary === false) {
                            throw new Exception("ไม่สามารถตรวจสอบ form_score_summary ได้");
                        }
                        
                        $rowSummary = sqlsrv_fetch_array($resultCheckSummary, SQLSRV_FETCH_ASSOC);
                        $summaryExists = $rowSummary['count'] > 0;
                        sqlsrv_free_stmt($resultCheckSummary);
                        
                        if (!$summaryExists) {
                            $sqlInsertSummary = "INSERT INTO form_score_summary (TransactionID, FormID, emp_pe) VALUES (?, ?, ?)";
                            $resultInsertSummary = sqlsrv_query($conn, $sqlInsertSummary, array($transactionID, $FormID, $emp_pe));
                            
                            if ($resultInsertSummary === false) {
                                throw new Exception("ไม่สามารถบันทึก form_score_summary ได้");
                            }
                            sqlsrv_free_stmt($resultInsertSummary);
                            error_log("Inserted form_score_summary for TransactionID=$transactionID");
                        }
                        
                        // ตรวจสอบและบันทึก evaluation_forms (ใช้โลจิกแบบ save_form_score.php)
                        $sqlCheckEval = "SELECT COUNT(*) AS count FROM evaluation_forms WHERE TransactionID = ? AND form_id = ? AND emp_as = ? AND emp_pe = ?";
                        $resultCheckEval = sqlsrv_query($conn, $sqlCheckEval, array($transactionID, $FormID, $emp_as, $emp_pe));
                        
                        if ($resultCheckEval === false) {
                            throw new Exception("ไม่สามารถตรวจสอบ evaluation_forms ได้");
                        }
                        
                        $rowEval = sqlsrv_fetch_array($resultCheckEval, SQLSRV_FETCH_ASSOC);
                        $evalExists = $rowEval['count'] > 0;
                        sqlsrv_free_stmt($resultCheckEval);
                        
                        if ($evalExists) {
                            // อัปเดตข้อมูลเดิม
                            $sqlUpdateEval = "UPDATE evaluation_forms SET evaluation_period = ?, start_date = ?, end_date = ?, status = 'Y' WHERE TransactionID = ? AND form_id = ? AND emp_as = ? AND emp_pe = ?";
                            $resultUpdateEval = sqlsrv_query($conn, $sqlUpdateEval, array($evaluation_period, $start_date, $end_date, $transactionID, $FormID, $emp_as, $emp_pe));
                            if ($resultUpdateEval === false) {
                                throw new Exception("ไม่สามารถอัปเดต evaluation_forms ได้");
                            }
                            sqlsrv_free_stmt($resultUpdateEval);
                            error_log("Updated evaluation_forms for TransactionID=$transactionID");
                        } else {
                            // เพิ่มข้อมูลใหม่ - หา ID ถัดไป
                            $sqlMaxId = "SELECT ISNULL(MAX(id), 0) + 1 AS next_id FROM evaluation_forms";
                            $resultMaxId = sqlsrv_query($conn, $sqlMaxId);
                            if ($resultMaxId === false) {
                                throw new Exception("ไม่สามารถหา ID ถัดไปสำหรับ evaluation_forms ได้");
                            }
                            $rowMaxId = sqlsrv_fetch_array($resultMaxId, SQLSRV_FETCH_ASSOC);
                            $nextId = $rowMaxId['next_id'];
                            sqlsrv_free_stmt($resultMaxId);
                            
                            $sqlInsertEval = "INSERT INTO evaluation_forms (id, TransactionID, form_id, emp_as, emp_pe, evaluation_period, start_date, end_date, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'Y', GETDATE())";
                            $resultInsertEval = sqlsrv_query($conn, $sqlInsertEval, array($nextId, $transactionID, $FormID, $emp_as, $emp_pe, $evaluation_period, $start_date, $end_date));
                            if ($resultInsertEval === false) {
                                throw new Exception("ไม่สามารถเพิ่ม evaluation_forms ได้");
                            }
                            sqlsrv_free_stmt($resultInsertEval);
                            error_log("Inserted evaluation_forms for TransactionID=$transactionID with ID=$nextId");
                        }
                        
                        // บันทึกข้อมูลลงตาราง form_all_hr (ใช้โลจิกแบบ save_form_score.php)
                        // แปลง FormID จาก string เป็น number สำหรับตาราง form_all_hr
                        $formIdForAllHr = intval(str_replace('F', '', $FormID)); // F005 -> 5
                        
                        $sqlCheckFormAllHr = "SELECT COUNT(*) AS count FROM form_all_hr WHERE FormID = ? AND emp_as = ? AND emp_pe = ?";
                        $resultCheckFormAllHr = sqlsrv_query($conn, $sqlCheckFormAllHr, array($formIdForAllHr, $emp_as, $emp_pe));
                        
                        if ($resultCheckFormAllHr === false) {
                            throw new Exception("ไม่สามารถตรวจสอบ form_all_hr ได้");
                        }
                        
                        $rowFormAllHr = sqlsrv_fetch_array($resultCheckFormAllHr, SQLSRV_FETCH_ASSOC);
                        $formAllHrExists = $rowFormAllHr['count'] > 0;
                        sqlsrv_free_stmt($resultCheckFormAllHr);
                        
                        // ดึงข้อมูล FormName จากตาราง form
                        $sqlGetFormName = "SELECT FormName FROM form WHERE FormID = ?";
                        $resultFormName = sqlsrv_query($conn, $sqlGetFormName, array($FormID));
                        $formName = 'แบบฟอร์มประเมิน'; // default
                        if ($resultFormName) {
                            $rowFormName = sqlsrv_fetch_array($resultFormName, SQLSRV_FETCH_ASSOC);
                            if ($rowFormName) {
                                $formName = $rowFormName['FormName'];
                            }
                            sqlsrv_free_stmt($resultFormName);
                        }
                        
                        if ($formAllHrExists) {
                            // อัปเดตข้อมูลเดิม
                            $sqlUpdateFormAllHr = "UPDATE form_all_hr SET 
                                                    FormName = ?, 
                                                    start_date = ?, 
                                                    end_date = ?, 
                                                    status_form = 'active',
                                                    CreatedDate = GETDATE()
                                                   WHERE FormID = ? AND emp_as = ? AND emp_pe = ?";
                            $resultUpdateFormAllHr = sqlsrv_query($conn, $sqlUpdateFormAllHr, array($formName, $start_date, $end_date, $formIdForAllHr, $emp_as, $emp_pe));
                            if ($resultUpdateFormAllHr === false) {
                                throw new Exception("ไม่สามารถอัปเดต form_all_hr ได้");
                            }
                            sqlsrv_free_stmt($resultUpdateFormAllHr);
                            error_log("Updated form_all_hr for FormID=$formIdForAllHr");
                        } else {
                            // หา FormIDmain ถัดไป
                            $sqlMaxFormIDmain = "SELECT ISNULL(MAX(FormIDmain), 0) + 1 AS next_id FROM form_all_hr";
                            $resultMaxFormIDmain = sqlsrv_query($conn, $sqlMaxFormIDmain);
                            if ($resultMaxFormIDmain === false) {
                                throw new Exception("ไม่สามารถหา FormIDmain ถัดไปได้");
                            }
                            $rowMaxFormIDmain = sqlsrv_fetch_array($resultMaxFormIDmain, SQLSRV_FETCH_ASSOC);
                            $nextFormIDmain = $rowMaxFormIDmain['next_id'];
                            sqlsrv_free_stmt($resultMaxFormIDmain);
                            
                            // เพิ่มข้อมูลใหม่
                            $sqlInsertFormAllHr = "INSERT INTO form_all_hr (FormIDmain, FormID, FormName, Category, emp_as, emp_pe, start_date, end_date, status_form, CreatedBy, CreatedDate) 
                                                   VALUES (?, ?, ?, 'HR Evaluation', ?, ?, ?, ?, 'active', ?, GETDATE())";
                            $resultInsertFormAllHr = sqlsrv_query($conn, $sqlInsertFormAllHr, array($nextFormIDmain, $formIdForAllHr, $formName, $emp_as, $emp_pe, $start_date, $end_date, $emp_as));
                            if ($resultInsertFormAllHr === false) {
                                throw new Exception("ไม่สามารถเพิ่ม form_all_hr ได้");
                            }
                            sqlsrv_free_stmt($resultInsertFormAllHr);
                            error_log("Inserted form_all_hr for FormID=$formIdForAllHr with FormIDmain=$nextFormIDmain");
                        }
                        
                        // ==== จบการใช้โลจิกแบบ save_form_score.php ====
                        
                        $success_count++;
                        
                    } catch (Exception $e) {
                        $error_count++;
                        error_log("Error processing row " . ($index + 1) . ": " . $e->getMessage());
                        continue;
                    }
                }
                
                // บันทึกข้อมูลการอัปโหลด
                $uploadedBy = $_SESSION['employee_id'] ?? 'Unknown';
                $fileName = $_FILES['excelFile']['name'];
                
                error_log("Attempting to log upload: fileName=$fileName, uploadedBy=$uploadedBy");
                
                $sqlLogUpload = "INSERT INTO upload_logs (file_name, uploaded_by) VALUES (?, ?)";
                $resultLogUpload = sqlsrv_query($conn, $sqlLogUpload, array($fileName, $uploadedBy));
                if ($resultLogUpload !== false) {
                    sqlsrv_free_stmt($resultLogUpload);
                    error_log("Upload log saved successfully");
                } else {
                    error_log("Upload log failed: " . print_r(sqlsrv_errors(), true));
                }
                
                sqlsrv_close($conn);
                
                // สร้างข้อความผลลัพธ์
                $message = "อัปโหลดเสร็จสิ้น: สำเร็จ {$success_count} รายการ";
                if ($error_count > 0) {
                    $message .= ", ผิดพลาด {$error_count} รายการ";
                }
                $message .= "<br>ข้อมูลถูกบันทึกในตารางต่อไปนี้:";
                $message .= "<br>• form_score - สถานะฟอร์ม";
                $message .= "<br>• form_all_hr - ข้อมูลฟอร์มทั้งหมด";
                $message .= "<br>• evaluation_forms - แบบประเมิน";
                $message_type = 'success';
                
            } catch (Exception $e) {
                error_log("Excel processing error: " . $e->getMessage());
                error_log("Error trace: " . $e->getTraceAsString());
                $message = "เกิดข้อผิดพลาดในการประมวลผลไฟล์: " . $e->getMessage();
                $message_type = 'error';
            }
        }
    }

    // ดึงข้อมูลประวัติการอัปโหลด
    $upload_history = [];
    try {
        $conn = dbConnect();
        if ($conn) {
            $sqlGetUploads = "
                SELECT ul.id, ul.file_name, ul.upload_time, e.first_name_th, e.last_name_th 
                FROM upload_logs ul
                LEFT JOIN employees e ON ul.uploaded_by = e.employee_id
                ORDER BY ul.upload_time DESC
            ";
            $result = sqlsrv_query($conn, $sqlGetUploads);
            if ($result !== false) {
                while ($row = sqlsrv_fetch_array($result, SQLSRV_FETCH_ASSOC)) {
                    $upload_history[] = $row;
                }
                sqlsrv_free_stmt($result);
            }
            sqlsrv_close($conn);
        }
    } catch (Exception $e) {
        error_log("Error loading upload history: " . $e->getMessage());
    }

} catch (Exception $e) {
    error_log("System error in import_excel.php: " . $e->getMessage());
    error_log("System error trace: " . $e->getTraceAsString());
    $message = "เกิดข้อผิดพลาดระบบ: " . $e->getMessage();
    $message_type = 'error';
}

// ปิดการบัฟเฟอร์ Output
ob_end_flush();
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Import Excel - Fixed</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Prompt', sans-serif;
            background-color: #f8f9fa;
        }
        .container {
            margin-top: 50px;
        }
        .card {
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        .card-header {
            font-size: 1.5rem;
            font-weight: 600;
            background-color: #007bff;
            color: white;
        }
    </style>
</head>
<body>
    <div class="container">
        
        <!-- แสดงข้อความผลลัพธ์ -->
        <?php if (!empty($message)): ?>
            <div class="alert alert-<?php echo $message_type === 'success' ? 'success' : 'danger'; ?> text-center">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>
        
        <div class="card">
            <div class="card-header text-center">
                นำเข้าข้อมูลจากไฟล์ Excel
            </div>
            <div class="card-body">
                <form action="" method="POST" enctype="multipart/form-data">
                    <div class="mb-3">
                        <label for="excelFile" class="form-label">เลือกไฟล์ Excel:</label>
                        <input type="file" name="excelFile" id="excelFile" class="form-control" accept=".xlsx,.xls" required>
                    </div>
                    <div class="text-center mb-3">
                        <a href="../assets/templates/template.xlsx" class="btn btn-secondary" download>
                            ดาวน์โหลดเทมเพลต Excel
                        </a>
                    </div>
                    <div class="text-center">
                        <button type="submit" class="btn btn-primary">อัปโหลด</button>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- ประวัติการอัปโหลด -->
        <div class="container mt-5">
            <h3 class="text-center">ประวัติการอัปโหลดไฟล์</h3>
            <table class="table table-bordered table-striped">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>ชื่อไฟล์</th>
                        <th>เวลาอัปโหลด</th>
                        <th>คนอัปโหลด</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($upload_history)): ?>
                        <?php foreach ($upload_history as $row): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($row['id']); ?></td>
                                <td><?php echo htmlspecialchars($row['file_name']); ?></td>
                                <td>
                                    <?php 
                                    $upload_time = is_object($row['upload_time']) ? 
                                        $row['upload_time']->format('Y-m-d H:i:s') : 
                                        $row['upload_time'];
                                    echo htmlspecialchars($upload_time);
                                    ?>
                                </td>
                                <td>
                                    <?php 
                                    $name = trim(($row['first_name_th'] ?? '') . ' ' . ($row['last_name_th'] ?? ''));
                                    echo htmlspecialchars($name ?: 'Unknown');
                                    ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="4" class="text-center">ไม่มีข้อมูลการอัปโหลด</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <div class="text-center mt-4">
            <a href="performance_review.php" class="btn btn-outline-primary">กลับหน้าแรก</a>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
