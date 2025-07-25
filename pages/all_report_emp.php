<?php
// เปิดการแสดงข้อผิดพลาด
ob_start();
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    $logFile = '../debug_all_report_emp.log';
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[$timestamp] ERROR: $errstr in $errfile at line $errline\n", FILE_APPEND | LOCK_EX);
    // Don't terminate for notices and warnings
    if($errno == E_NOTICE || $errno == E_WARNING || $errno == E_DEPRECATED) {
        return true;
    }
    return false;
});

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// รวมไฟล์ header และ menu
include_once '../includes/header.php';
include_once '../includes/menu.php';
include '../config.php';

// เพิ่ม logging function
function writeLog($message) {
    $logFile = '../debug_all_report_emp.log';
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[$timestamp] $message\n", FILE_APPEND | LOCK_EX);
}

// เริ่มต้น session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ตรวจสอบว่า user ได้ล็อกอินหรือยัง
if (!isset($_SESSION['employee_id'])) {
    writeLog("No employee_id in session - redirecting to login");
    die("กรุณาเข้าสู่ระบบก่อนใช้งานหน้านี้");
}

// ดึง employee_id ของผู้ที่ล็อกอิน
$employee_id = $_SESSION['employee_id'];
writeLog("Session found for employee_id: $employee_id");

// Log all session data for debugging
writeLog("Session data: " . print_r($_SESSION, true));

// เชื่อมต่อฐานข้อมูล
try {
    $conn = dbConnect();
} catch (Exception $e) {
    writeLog("Database connection error: " . $e->getMessage());
    die("เกิดข้อผิดพลาดในการเชื่อมต่อฐานข้อมูล กรุณาลองใหม่อีกครั้ง");
}

// ดึงข้อมูลแบบฟอร์มที่ต้องทำการประเมิน พร้อมข้อมูล emp_pe และ emp_as และวันที่เริ่มต้น-สิ้นสุด
$sql = "SELECT fs.FormID, f.FormName, f.editlink, fs.emp_as, fs.emp_pe, fs.status_form, fs.TransactionID,
               fs.start_date, fs.end_date, fs.CreatedDate,
               e1.first_name_th + ' ' + e1.last_name_th AS EvaluateeName,
               e2.first_name_th + ' ' + e2.last_name_th AS EvaluatorName,
               ef.evaluation_period
        FROM form_score fs
        INNER JOIN form f ON fs.FormID = f.FormID
        LEFT JOIN employees e1 ON fs.emp_as = e1.employee_id
        LEFT JOIN employees e2 ON fs.emp_pe = e2.employee_id
        LEFT JOIN evaluation_forms ef ON fs.TransactionID = ef.TransactionID 
                                     AND fs.emp_pe = ef.emp_pe
        WHERE fs.emp_as = ? AND fs.status_form IN ('disable', 'enable')
        ORDER BY fs.status_form ASC, f.FormName ASC";
        
writeLog("Executing query for employee_id: $employee_id");
$stmt = sqlsrv_query($conn, $sql, array($employee_id));

if ($stmt === false) {
    $errors = sqlsrv_errors();
    writeLog("SQL Error in all_report_emp.php: " . print_r($errors, true));
    die("เกิดข้อผิดพลาดในการดึงข้อมูล กรุณาลองใหม่อีกครั้ง");
}

$forms = [];
if ($stmt) {
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        try {
            $currentDate = new DateTime();
            $dateStatus = 'active';
            if (!empty($row['end_date'])) {
                if (is_object($row['end_date']) && $row['end_date'] instanceof DateTime) {
                    $endDate = $row['end_date'];
                } else {
                    try {
                        if (is_string($row['end_date'])) {
                            $endDate = new DateTime($row['end_date']);
                        } else if (is_array($row['end_date']) && isset($row['end_date']['date'])) {
                            $endDate = new DateTime($row['end_date']['date']);
                        } else {
                            $endDate = new DateTime('9999-12-31');
                            writeLog("Unparseable end_date format: " . print_r($row['end_date'], true));
                        }
                    } catch (Exception $e) {
                        writeLog("Error parsing end_date: " . $e->getMessage() . " - Value: " . print_r($row['end_date'], true));
                        $endDate = new DateTime('9999-12-31');
                    }
                }
                if ($currentDate > $endDate) {
                    $dateStatus = 'expired';
                }
            }
            if (!empty($row['start_date']) && $dateStatus !== 'expired') {
                if (is_object($row['start_date']) && $row['start_date'] instanceof DateTime) {
                    $startDate = $row['start_date'];
                } else {
                    try {
                        if (is_string($row['start_date'])) {
                            $startDate = new DateTime($row['start_date']);
                        } else if (is_array($row['start_date']) && isset($row['start_date']['date'])) {
                            $startDate = new DateTime($row['start_date']['date']);
                        } else {
                            $startDate = new DateTime('1900-01-01');
                            writeLog("Unparseable start_date format: " . print_r($row['start_date'], true));
                        }
                    } catch (Exception $e) {
                        writeLog("Error parsing start_date: " . $e->getMessage() . " - Value: " . print_r($row['start_date'], true));
                        $startDate = new DateTime('1900-01-01');
                    }
                }
                if ($currentDate < $startDate) {
                    $dateStatus = 'not_started';
                }
            }
            $row['date_status'] = $dateStatus;
            $forms[] = $row;
        } catch (Exception $e) {
            writeLog("Error processing form row: " . $e->getMessage());
            $row['date_status'] = 'active';
            $forms[] = $row;
        }
    }
}

sqlsrv_free_stmt($stmt);
sqlsrv_close($conn);

$error = error_get_last();
if($error && ($error['type'] === E_ERROR || $error['type'] === E_PARSE || $error['type'] === E_COMPILE_ERROR)) {
    writeLog("Fatal error at the end of processing: " . print_r($error, true));
    ob_clean();
    echo '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>System Error</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 40px; line-height: 1.6; }
        .error-container { max-width: 800px; margin: 0 auto; padding: 20px; border: 1px solid #f5c6cb; border-radius: 5px; background-color: #f8d7da; }
        h1 { color: #721c24; }
        .btn { display: inline-block; background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; margin-top: 20px; }
        .btn:hover { background: #0069d9; }
    </style>
</head>
<body>
    <div class="error-container">
        <h1>ระบบขัดข้อง</h1>
        <p>ขออภัยในความไม่สะดวก ระบบไม่สามารถแสดงหน้านี้ได้ในขณะนี้</p>
        <p>กรุณาลองใหม่อีกครั้งในภายหลัง หรือติดต่อผู้ดูแลระบบ</p>
        <a class="btn" href="../index.php">กลับสู่หน้าหลัก</a>
    </div>
</body>
</html>';
    exit;
}

ob_end_flush();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>แบบฟอร์มการประเมิน - Web Portal</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-gradient: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            --secondary-gradient: linear-gradient(135deg, #6c757d 0%, #495057 100%);
            --success-gradient: linear-gradient(135deg, #198754 0%, #157347 100%);
            --warning-gradient: linear-gradient(135deg, #fd7e14 0%, #e25d06 100%);
            --danger-gradient: linear-gradient(135deg, #dc3545 0%, #bb2d3b 100%);
            --info-gradient: linear-gradient(135deg, #0dcaf0 0%, #0aa2c0 100%);
            
            --primary-color: #6c757d;
            --secondary-color: #495057;
            --text-primary: #212529;
            --text-secondary: #6c757d;
            --text-muted: #adb5bd;
            --background: #f8f9fa;
            --surface: #ffffff;
            --border: #dee2e6;
            --shadow-sm: 0 2px 4px rgba(0, 0, 0, 0.06);
            --shadow-md: 0 4px 12px rgba(0, 0, 0, 0.08);
            --shadow-lg: 0 8px 25px rgba(0, 0, 0, 0.1);
            --shadow-xl: 0 15px 35px rgba(0, 0, 0, 0.12);
            --border-radius: 12px;
            --border-radius-lg: 16px;
            --transition: all 0.3s ease;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Prompt', sans-serif !important;
            background: #f8f9fa;
            color: var(--text-primary);
            line-height: 1.6;
            min-height: 100vh;
            position: relative;
            overflow-x: hidden;
        }

        .container {
            position: relative;
            z-index: 1;
            margin-top: 2rem;
            padding: 0 1rem;
        }

        .page-header {
            background: var(--surface);
            border-radius: var(--border-radius-lg);
            padding: 3rem 2rem;
            margin-bottom: 3rem;
            box-shadow: var(--shadow-lg);
            border: 1px solid var(--border);
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .page-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, #6c757d 0%, #495057 100%);
            z-index: 1;
        }

        .page-header > * {
            position: relative;
            z-index: 1;
        }

        .page-title {
            font-size: 2.5rem;
            font-weight: 700;
            color: var(--text-primary);
            margin: 0 0 1rem 0;
            letter-spacing: -1px;
        }

        .page-title i {
            color: var(--primary-color);
            margin-right: 1rem;
            font-size: 2.2rem;
        }

        .page-subtitle {
            color: var(--text-secondary);
            font-size: 1.1rem;
            margin: 0;
            font-weight: 400;
            opacity: 0.8;
        }

        .stats-container {
            margin-bottom: 3rem;
        }

        .stats-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 1.5rem;
        }

        .stat-card {
            background: var(--surface);
            border-radius: var(--border-radius);
            padding: 2rem;
            text-align: center;
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--border);
            transition: var(--transition);
            position: relative;
            overflow: hidden;
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, #6c757d 0%, #495057 100%);
            transform: scaleX(0);
            transition: var(--transition);
        }

        .stat-card:hover {
            transform: translateY(-8px);
            box-shadow: var(--shadow-lg);
        }

        .stat-card:hover::before {
            transform: scaleX(1);
        }

        .stat-card.pending {
            border-color: #fd7e14;
        }

        .stat-card.pending::before {
            background: var(--warning-gradient);
        }

        .stat-card.completed {
            border-color: #198754;
        }

        .stat-card.completed::before {
            background: var(--success-gradient);
        }

        .stat-card.expired {
            border-color: #dc3545;
        }

        .stat-card.expired::before {
            background: var(--danger-gradient);
        }

        .stat-card.not-started {
            border-color: #0dcaf0;
        }

        .stat-card.not-started::before {
            background: var(--info-gradient);
        }

        .stat-number {
            font-size: 2.5rem;
            font-weight: 800;
            color: var(--text-primary);
            margin-bottom: 0.5rem;
        }

        .stat-label {
            font-size: 0.95rem;
            color: var(--text-secondary);
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .forms-container {
            margin-bottom: 3rem;
        }

        .forms-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(420px, 1fr));
            gap: 2rem;
        }

        .form-card {
            background: var(--surface);
            border-radius: var(--border-radius-lg);
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--border);
            overflow: hidden;
            transition: var(--transition);
            position: relative;
        }

        .form-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 5px;
            transition: var(--transition);
        }

        .form-card.status-pending::before {
            background: var(--warning-gradient);
        }

        .form-card.status-completed::before {
            background: var(--success-gradient);
        }

        .form-card.status-expired::before {
            background: var(--danger-gradient);
        }

        .form-card.status-not-started::before {
            background: var(--info-gradient);
        }

        .form-card:hover {
            transform: translateY(-6px);
            box-shadow: var(--shadow-xl);
        }

        .form-card:hover::before {
            height: 8px;
        }

        .form-card-body {
            padding: 2.5rem;
        }

        .form-title {
            font-size: 1.4rem;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            line-height: 1.3;
        }

        .form-title i {
            font-size: 1.5rem;
            color: var(--primary-color);
        }

        .form-info {
            margin-bottom: 2rem;
        }

        .form-info-item {
            display: flex;
            align-items: flex-start;
            margin-bottom: 1rem;
            padding: 1rem;
            background: var(--background);
            border-radius: var(--border-radius);
            border: 1px solid var(--border);
            transition: var(--transition);
        }

        .form-info-item:hover {
            background: var(--surface);
            box-shadow: var(--shadow-sm);
        }

        .form-info-item i {
            margin-right: 1rem;
            font-size: 1.2rem;
            width: 24px;
            text-align: center;
            margin-top: 0.1rem;
            flex-shrink: 0;
        }

        .form-info-content {
            flex: 1;
            min-width: 0;
        }

        .form-info-item strong {
            color: var(--text-primary);
            font-weight: 600;
            display: block;
            margin-bottom: 0.25rem;
            font-size: 0.9rem;
        }

        .form-info-item span {
            color: var(--text-secondary);
            font-size: 0.95rem;
            word-wrap: break-word;
        }

        .status-badge {
            position: absolute;
            top: 1.5rem;
            right: 1.5rem;
            padding: 0.5rem 1rem;
            border-radius: 25px;
            font-size: 0.8rem;
            font-weight: 700;
            color: white;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            box-shadow: var(--shadow-sm);
        }

        .status-badge.pending {
            background: var(--warning-gradient);
        }

        .status-badge.completed {
            background: var(--success-gradient);
        }

        .status-badge.expired {
            background: var(--danger-gradient);
        }

        .status-badge.not-started {
            background: var(--info-gradient);
        }

        .form-actions {
            margin-top: 2rem;
        }
        
        .btn {
            border: none;
            border-radius: var(--border-radius);
            padding: 1rem 2rem;
            font-weight: 600;
            transition: var(--transition);
            font-size: 0.95rem;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.75rem;
            cursor: pointer;
            position: relative;
            overflow: hidden;
            box-shadow: var(--shadow-sm);
        }

        .btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.3), transparent);
            transition: var(--transition);
        }

        .btn:hover::before {
            left: 100%;
        }

        .btn-primary {
            background: linear-gradient(135deg, #6c757d 0%, #495057 100%);
            color: white;
        }

        .btn-primary:hover:not(:disabled) {
            color: white;
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }

        .btn-success {
            background: var(--success-gradient);
            color: white;
        }

        .btn-success:disabled {
            background: linear-gradient(135deg, #d1d5db 0%, #9ca3af 100%);
            color: #6b7280;
            cursor: not-allowed;
            transform: none !important;
        }

        .btn-warning {
            background: var(--warning-gradient);
            color: white;
        }

        .btn-warning:hover:not(:disabled) {
            color: white;
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }

        .btn-danger {
            background: var(--danger-gradient);
            color: white;
        }

        .btn-info {
            background: var(--info-gradient);
            color: white;
        }

        .btn:disabled {
            opacity: 0.7;
            cursor: not-allowed;
            transform: none !important;
            box-shadow: var(--shadow-sm) !important;
        }

        .w-100 {
            width: 100% !important;
        }

        .alert {
            border-radius: var(--border-radius);
            padding: 2rem;
            margin-bottom: 3rem;
            border: none;
            box-shadow: var(--shadow-sm);
            position: relative;
            overflow: hidden;
        }

        .alert::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: var(--info-gradient);
        }

        .alert-info {
            background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%);
            color: var(--text-primary);
        }

        .alert-info i {
            font-size: 2rem;
            margin-right: 1rem;
            color: var(--primary-color);
        }

        .search-container {
            background: var(--surface);
            border-radius: var(--border-radius-lg);
            padding: 2rem;
            margin-bottom: 3rem;
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--border);
        }

        .search-input {
            border: 2px solid var(--border);
            border-radius: var(--border-radius);
            padding: 1rem 1.5rem 1rem 3.5rem;
            font-size: 1rem;
            transition: var(--transition);
            background: var(--background);
        }

        .search-input:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(108, 117, 125, 0.1);
            background: var(--surface);
        }

        .search-icon {
            position: absolute;
            left: 1.25rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-muted);
            font-size: 1.2rem;
        }

        .filter-buttons {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
            margin-top: 1.5rem;
        }

        .filter-btn {
            padding: 0.75rem 1.5rem;
            border: 2px solid var(--border);
            background: var(--surface);
            color: var(--text-secondary);
            border-radius: 50px;
            cursor: pointer;
            transition: var(--transition);
            font-weight: 500;
            font-size: 0.9rem;
        }

        .filter-btn:hover,
        .filter-btn.active {
            border-color: var(--primary-color);
            background: linear-gradient(135deg, #6c757d 0%, #495057 100%);
            color: white;
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }

        footer {
            margin-top: 4rem;
            background: linear-gradient(135deg, #6c757d 0%, #495057 100%);
            color: white;
            padding: 2rem 0;
            text-align: center;
            border-top: none;
        }

        footer small {
            font-size: 0.9rem;
            opacity: 0.9;
        }

        .fade-in {
            animation: fadeInUp 0.6s ease-out;
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .loading-skeleton {
            background: linear-gradient(90deg, #f0f0f0 25%, #e0e0e0 50%, #f0f0f0 75%);
            background-size: 200% 100%;
            animation: loading 1.5s infinite;
        }

        @keyframes loading {
            0% { background-position: 200% 0; }
            100% { background-position: -200% 0; }
        }

        .floating-action {
            position: fixed;
            bottom: 2rem;
            right: 2rem;
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, #6c757d 0%, #495057 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.5rem;
            box-shadow: var(--shadow-lg);
            cursor: pointer;
            transition: var(--transition);
            z-index: 1000;
        }

        .floating-action:hover {
            transform: scale(1.1);
            box-shadow: var(--shadow-xl);
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .container {
                margin-top: 1rem;
                padding: 0 0.75rem;
            }

            .page-header {
                padding: 2rem 1.5rem;
                margin-bottom: 2rem;
            }

            .page-title {
                font-size: 2rem;
            }

            .page-title i {
                font-size: 1.8rem;
            }

            .stats-row {
                grid-template-columns: repeat(2, 1fr);
                gap: 1rem;
            }

            .stat-card {
                padding: 1.5rem;
            }

            .stat-number {
                font-size: 2rem;
            }

            .forms-grid {
                grid-template-columns: 1fr;
                gap: 1.5rem;
            }

            .form-card-body {
                padding: 2rem 1.5rem;
            }

            .form-title {
                font-size: 1.2rem;
            }

            .form-info-item {
                padding: 0.875rem;
                flex-direction: column;
                align-items: flex-start;
                gap: 0.5rem;
            }

            .form-info-item i {
                margin-right: 0.5rem;
                margin-top: 0;
            }

            .btn {
                padding: 0.875rem 1.5rem;
                font-size: 0.9rem;
            }

            .search-container {
                padding: 1.5rem;
            }

            .filter-buttons {
                gap: 0.75rem;
            }

            .filter-btn {
                padding: 0.625rem 1.25rem;
                font-size: 0.85rem;
            }

            .floating-action {
                width: 50px;
                height: 50px;
                bottom: 1.5rem;
                right: 1.5rem;
                font-size: 1.25rem;
            }
        }

        @media (max-width: 576px) {
            .page-header {
                padding: 1.5rem 1rem;
            }

            .page-title {
                font-size: 1.75rem;
            }

            .stats-row {
                grid-template-columns: 1fr;
            }

            .form-card-body {
                padding: 1.5rem 1rem;
            }

            .status-badge {
                position: static;
                margin-bottom: 1rem;
                display: inline-block;
            }

            .filter-buttons {
                flex-direction: column;
            }

            .filter-btn {
                text-align: center;
            }
        }

        /* Dark mode support */
        @media (prefers-color-scheme: dark) {
            :root {
                --text-primary: #f7fafc;
                --text-secondary: #e2e8f0;
                --text-muted: #a0aec0;
                --background: #1a202c;
                --surface: #2d3748;
                --border: #4a5568;
            }
        }

        /* High contrast mode */
        @media (prefers-contrast: high) {
            .form-card {
                border-width: 2px;
            }
            
            .btn {
                border: 2px solid currentColor;
            }
        }

        /* Reduced motion */
        @media (prefers-reduced-motion: reduce) {
            * {
                animation-duration: 0.01ms !important;
                animation-iteration-count: 1 !important;
                transition-duration: 0.01ms !important;
            }
        }

        /* Print styles */
        @media print {
            .floating-action,
            .search-container,
            .filter-buttons,
            footer {
                display: none;
            }
            
            .form-card {
                break-inside: avoid;
                box-shadow: none;
                border: 1px solid #000;
            }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <!--<header class="bg-dark text-white py-3">-->
    <!--    <div class="container">-->
    <!--        <h1 class="text-center">แบบฟอร์มที่ต้องทำการประเมิน</h1>-->
    <!--    </div>-->
    <!--</header>-->

    <!-- Page Content -->
    <div class="container fade-in">
        <!-- Search and Filter Section -->
        <div class="search-container">
            <div class="position-relative">
                <i class="bi bi-search search-icon"></i>
                <input type="text" class="form-control search-input" id="searchInput" placeholder="ค้นหาแบบฟอร์มหรือชื่อผู้ถูกประเมิน...">
            </div>
            <div class="filter-buttons">
                <button class="filter-btn active" data-filter="all">
                    <i class="bi bi-list"></i> ทั้งหมด
                </button>
                <button class="filter-btn" data-filter="pending">
                    <i class="bi bi-clock"></i> รอดำเนินการ
                </button>
                <button class="filter-btn" data-filter="completed">
                    <i class="bi bi-check-circle"></i> ทำแล้ว
                </button>
                <button class="filter-btn" data-filter="expired">
                    <i class="bi bi-x-circle"></i> หมดเวลา
                </button>
                <button class="filter-btn" data-filter="not-started">
                    <i class="bi bi-pause-circle"></i> ยังไม่เริ่ม
                </button>
            </div>
        </div>

        <div class="page-header">
            <h1 class="page-title">
                <i class="bi bi-clipboard-check-fill"></i> 
                แบบฟอร์มการประเมิน
            </h1>
            <p class="page-subtitle">เลือกแบบฟอร์มที่คุณต้องการทำการประเมิน พร้อมระบบค้นหาและกรองข้อมูล</p>
        </div>

        <?php if (!empty($forms)): ?>
            <?php
            // นับสถิติ
            $pendingCount = 0;
            $completedCount = 0;
            $expiredCount = 0;
            $notStartedCount = 0;
            
            foreach ($forms as $form) {
                if ($form['status_form'] === 'disable') {
                    if ($form['date_status'] === 'expired') {
                        $expiredCount++;
                    } elseif ($form['date_status'] === 'not_started') {
                        $notStartedCount++;
                    } else {
                        $pendingCount++;
                    }
                } else {
                    $completedCount++;
                }
            }
            ?>
            
            <!-- Statistics Cards -->
            <div class="stats-container">
                <div class="stats-row">
                    <div class="stat-card pending">
                        <div class="stat-number" id="pendingCount"><?php echo $pendingCount; ?></div>
                        <div class="stat-label">
                            <i class="bi bi-clock"></i> รอดำเนินการ
                        </div>
                    </div>
                    <div class="stat-card completed">
                        <div class="stat-number" id="completedCount"><?php echo $completedCount; ?></div>
                        <div class="stat-label">
                            <i class="bi bi-check-circle"></i> ทำแล้ว
                        </div>
                    </div>
                    <div class="stat-card expired">
                        <div class="stat-number" id="expiredCount"><?php echo $expiredCount; ?></div>
                        <div class="stat-label">
                            <i class="bi bi-x-circle"></i> หมดเวลา
                        </div>
                    </div>
                    <?php if ($notStartedCount > 0): ?>
                    <div class="stat-card not-started">
                        <div class="stat-number" id="notStartedCount"><?php echo $notStartedCount; ?></div>
                        <div class="stat-label">
                            <i class="bi bi-pause-circle"></i> ยังไม่เริ่ม
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Forms Grid -->
            <div class="forms-container">
                <div class="forms-grid" id="formsGrid">
                    <?php foreach ($forms as $index => $form): ?>
                        <?php
                        $isExpired = ($form['date_status'] === 'expired');
                        $isNotStarted = ($form['date_status'] === 'not_started');
                        $isCompleted = ($form['status_form'] === 'enable');
                        
                        $cardClass = 'form-card ';
                        $statusText = '';
                        $statusClass = '';
                        $canSubmit = true;
                        $filterClass = '';
                        
                        if ($isCompleted) {
                            $cardClass .= 'status-completed';
                            $statusText = 'เสร็จสิ้นแล้ว';
                            $statusClass = 'completed';
                            $canSubmit = false;
                            $filterClass = 'completed';
                        } elseif ($isExpired) {
                            $cardClass .= 'status-expired';
                            $statusText = 'หมดเวลา';
                            $statusClass = 'expired';
                            $canSubmit = false;
                            $filterClass = 'expired';
                        } elseif ($isNotStarted) {
                            $cardClass .= 'status-not-started';
                            $statusText = 'ยังไม่เริ่ม';
                            $statusClass = 'not-started';
                            $canSubmit = false;
                            $filterClass = 'not-started';
                        } else {
                            $cardClass .= 'status-pending';
                            $statusText = 'รอดำเนินการ';
                            $statusClass = 'pending';
                            $filterClass = 'pending';
                        }
                        ?>
                        
                        <div class="<?php echo $cardClass; ?> fade-in" 
                             style="animation-delay: <?php echo $index * 0.1; ?>s"
                             data-filter="<?php echo $filterClass; ?>"
                             data-search="<?php echo strtolower($form['FormName'] . ' ' . $form['EvaluatorName']); ?>">
                            
                            <span class="status-badge <?php echo $statusClass; ?>">
                                <?php echo $statusText; ?>
                            </span>
                            
                            <div class="form-card-body">
                                <h5 class="form-title">
                                    <i class="bi bi-file-earmark-text"></i>
                                    <?php echo htmlspecialchars($form['FormName']); ?>
                                </h5>
                                
                                <div class="form-info">
                                    <div class="form-info-item">
                                        <i class="bi bi-person-circle text-primary"></i>
                                        <div class="form-info-content">
                                            <strong>ผู้ถูกประเมิน</strong>
                                            <span><?php echo htmlspecialchars($form['EvaluatorName']); ?></span>
                                        </div>
                                    </div>
                                    
                                    <!-- Evaluation Period -->
                                    <?php if (!empty($form['evaluation_period'])): ?>
                                        <div class="form-info-item">
                                            <i class="bi bi-calendar-event text-warning"></i>
                                            <div class="form-info-content">
                                                <strong>รอบการประเมิน</strong>
                                                <span class="text-warning fw-bold"><?php echo htmlspecialchars($form['evaluation_period']); ?></span>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <!-- Date Range -->
                                    <?php if ($form['start_date'] || $form['end_date']): ?>
                                        <div class="form-info-item">
                                            <i class="bi bi-calendar-range text-info"></i>
                                            <div class="form-info-content">
                                                <strong>ช่วงเวลาประเมิน</strong>
                                                <span>
                                                    <?php 
                                                    $dateText = '';
                                                    if ($form['start_date']) {
                                                        $startDateStr = '';
                                                        if (is_object($form['start_date']) && $form['start_date'] instanceof DateTime) {
                                                            $startDateStr = $form['start_date']->format('d/m/Y');
                                                        } else {
                                                            try {
                                                                if (is_string($form['start_date'])) {
                                                                    $startDateStr = date('d/m/Y', strtotime($form['start_date']));
                                                                } elseif (is_array($form['start_date']) && isset($form['start_date']['date'])) {
                                                                    $startDateStr = date('d/m/Y', strtotime($form['start_date']['date']));
                                                                } else {
                                                                    $startDateStr = 'ไม่ระบุ';
                                                                }
                                                            } catch (Exception $e) {
                                                                $startDateStr = 'ไม่ระบุ';
                                                                writeLog("Error formatting start_date: " . $e->getMessage());
                                                            }
                                                        }
                                                        $dateText .= $startDateStr;
                                                    }
                                                    if ($form['start_date'] && $form['end_date']) {
                                                        $dateText .= ' - ';
                                                    }
                                                    if ($form['end_date']) {
                                                        $endDateStr = '';
                                                        if (is_object($form['end_date']) && $form['end_date'] instanceof DateTime) {
                                                            $endDateStr = $form['end_date']->format('d/m/Y');
                                                        } else {
                                                            try {
                                                                if (is_string($form['end_date'])) {
                                                                    $endDateStr = date('d/m/Y', strtotime($form['end_date']));
                                                                } elseif (is_array($form['end_date']) && isset($form['end_date']['date'])) {
                                                                    $endDateStr = date('d/m/Y', strtotime($form['end_date']['date']));
                                                                } else {
                                                                    $endDateStr = 'ไม่ระบุ';
                                                                }
                                                            } catch (Exception $e) {
                                                                $endDateStr = 'ไม่ระบุ';
                                                                writeLog("Error formatting end_date: " . $e->getMessage());
                                                            }
                                                        }
                                                        $dateText .= $endDateStr;
                                                    }
                                                    echo $dateText;
                                                    ?>
                                                </span>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <!-- Status Info -->
                                    <div class="form-info-item">
                                        <?php if ($isExpired): ?>
                                            <i class="bi bi-exclamation-triangle text-danger"></i>
                                            <div class="form-info-content">
                                                <strong>สถานะ</strong>
                                                <span class="text-danger fw-bold">หมดเวลาการประเมิน</span>
                                            </div>
                                        <?php elseif ($isNotStarted): ?>
                                            <i class="bi bi-clock text-info"></i>
                                            <div class="form-info-content">
                                                <strong>สถานะ</strong>
                                                <span class="text-info fw-bold">ยังไม่ถึงเวลาเริ่มประเมิน</span>
                                            </div>
                                        <?php elseif ($isCompleted): ?>
                                            <i class="bi bi-check-circle text-success"></i>
                                            <div class="form-info-content">
                                                <strong>สถานะ</strong>
                                                <span class="text-success fw-bold">ทำแบบประเมินแล้ว</span>
                                            </div>
                                        <?php else: ?>
                                            <i class="bi bi-clock text-warning"></i>
                                            <div class="form-info-content">
                                                <strong>สถานะ</strong>
                                                <span class="text-warning fw-bold">พร้อมทำแบบประเมิน</span>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <div class="form-actions">
                                    <?php if ($canSubmit): ?>
                                        <a href="360_form.php?emp_as=<?php echo htmlspecialchars($form['emp_as']); ?>&emp_pe=<?php echo htmlspecialchars($form['emp_pe']); ?>&TransactionID=<?php echo htmlspecialchars($form['TransactionID']); ?>" 
                                           class="btn btn-warning w-100 evaluation-btn">
                                            <i class="bi bi-pencil-square"></i> 
                                            เริ่มทำแบบประเมิน
                                        </a>
                                    <?php elseif ($isCompleted): ?>
                                        <button class="btn btn-success w-100" disabled>
                                            <i class="bi bi-check-circle"></i> 
                                            ทำแบบประเมินแล้ว
                                        </button>
                                    <?php elseif ($isExpired): ?>
                                        <button class="btn btn-danger w-100" disabled>
                                            <i class="bi bi-x-circle"></i> 
                                            หมดเวลา
                                        </button>
                                    <?php elseif ($isNotStarted): ?>
                                        <button class="btn btn-info w-100" disabled>
                                            <i class="bi bi-clock"></i> 
                                            ยังไม่เริ่ม
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <!-- No results message -->
            <div class="alert alert-info text-center d-none" id="noResultsAlert">
                <i class="bi bi-search"></i>
                <strong>ไม่พบผลลัพธ์</strong>
                <br>
                <small>ลองเปลี่ยนคำค้นหาหรือตัวกรองข้อมูล</small>
            </div>
            
        <?php else: ?>
            <div class="alert alert-info text-center">
                <i class="bi bi-info-circle"></i> 
                <strong>ไม่มีแบบฟอร์มที่ต้องทำการประเมิน</strong>
                <br>
                <small class="mt-2 d-block">ในขณะนี้คุณไม่มีแบบฟอร์มประเมินที่รอดำเนินการ</small>
            </div>
        <?php endif; ?>
        
        <!-- Floating Action Button -->
        <div class="floating-action" onclick="scrollToTop()" title="กลับไปด้านบน">
            <i class="bi bi-arrow-up"></i>
        </div>
    </div>

    <!-- Footer -->
    <footer>
        <div class="container">
            <small>© 2025 Web Portal All rights reserved</small>
        </div>
    </footer>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Enhanced JavaScript functionality
        class FormEvaluationApp {
            constructor() {
                this.initializeApp();
                this.setupEventListeners();
                this.setupAnimations();
            }

            initializeApp() {
                // Prevent back button navigation
                history.pushState(null, null, location.href);
                window.onpopstate = () => {
                    history.pushState(null, null, location.href);
                    this.showNotification("ไม่สามารถย้อนกลับไปแก้ไขข้อมูลได้", "warning");
                };

                // Initialize search and filter
                this.searchInput = document.getElementById('searchInput');
                this.formsGrid = document.getElementById('formsGrid');
                this.noResultsAlert = document.getElementById('noResultsAlert');
                this.filterButtons = document.querySelectorAll('.filter-btn');
                this.formCards = document.querySelectorAll('.form-card');
                
                // Initialize counters
                this.counters = {
                    pending: document.getElementById('pendingCount'),
                    completed: document.getElementById('completedCount'),
                    expired: document.getElementById('expiredCount'),
                    notStarted: document.getElementById('notStartedCount')
                };

                this.currentFilter = 'all';
                this.originalCounts = this.getCurrentCounts();
            }

            setupEventListeners() {
                // Search functionality
                if (this.searchInput) {
                    this.searchInput.addEventListener('input', this.debounce((e) => {
                        this.handleSearch(e.target.value);
                    }, 300));
                }

                // Filter functionality
                this.filterButtons.forEach(btn => {
                    btn.addEventListener('click', (e) => {
                        const filter = e.target.closest('.filter-btn').dataset.filter;
                        this.handleFilter(filter);
                    });
                });

                // Form submission handling
                document.querySelectorAll('.evaluation-btn').forEach(btn => {
                    btn.addEventListener('click', this.handleEvaluationClick.bind(this));
                });

                // Floating action button
                const floatingBtn = document.querySelector('.floating-action');
                if (floatingBtn) {
                    window.addEventListener('scroll', this.throttle(() => {
                        this.handleScroll();
                    }, 100));
                }

                // Keyboard shortcuts
                document.addEventListener('keydown', this.handleKeyboardShortcuts.bind(this));

                // Touch gestures for mobile
                this.setupTouchGestures();
            }

            setupAnimations() {
                // Intersection Observer for animations
                const observer = new IntersectionObserver((entries) => {
                    entries.forEach(entry => {
                        if (entry.isIntersecting) {
                            entry.target.classList.add('animate__animated', 'animate__fadeInUp');
                        }
                    });
                }, { threshold: 0.1 });

                // Observe form cards
                this.formCards.forEach(card => {
                    observer.observe(card);
                });

                // Observe stat cards
                document.querySelectorAll('.stat-card').forEach(card => {
                    observer.observe(card);
                });
            }

            handleSearch(searchTerm) {
                const term = searchTerm.toLowerCase().trim();
                let visibleCount = 0;

                this.formCards.forEach(card => {
                    const searchData = card.dataset.search;
                    const filterMatch = this.currentFilter === 'all' || card.dataset.filter === this.currentFilter;
                    const searchMatch = !term || searchData.includes(term);
                    
                    if (filterMatch && searchMatch) {
                        card.style.display = 'block';
                        card.classList.add('animate__animated', 'animate__fadeIn');
                        visibleCount++;
                    } else {
                        card.style.display = 'none';
                    }
                });

                this.updateNoResultsDisplay(visibleCount);
                this.updateCounters();
            }

            handleFilter(filter) {
                // Update active filter button
                this.filterButtons.forEach(btn => {
                    btn.classList.remove('active');
                });
                document.querySelector(`[data-filter="${filter}"]`).classList.add('active');

                this.currentFilter = filter;
                
                // Apply filter
                const searchTerm = this.searchInput ? this.searchInput.value : '';
                this.handleSearch(searchTerm);

                // Add filter animation
                this.animateFilterChange();
            }

            handleEvaluationClick(e) {
                e.preventDefault();
                const button = e.target.closest('.evaluation-btn');
                const url = button.href;
                const formCard = button.closest('.form-card');
                const formName = formCard.querySelector('.form-title').textContent.trim();
                
                // Enhanced confirmation dialog
                this.showConfirmDialog(
                    'เริ่มทำแบบประเมิน',
                    `คุณต้องการเริ่มทำแบบประเมิน "${formName}" หรือไม่?\n\n⚠️ หมายเหตุ: เมื่อเริ่มทำแบบประเมินแล้ว กรุณาทำให้เสร็จสิ้นในครั้งเดียว`,
                    () => {
                        this.startEvaluation(button, url);
                    }
                );
            }

            startEvaluation(button, url) {
                // Show loading state
                const originalHTML = button.innerHTML;
                button.innerHTML = '<i class="bi bi-hourglass-split"></i> กำลังโหลด...';
                button.disabled = true;
                
                // Add loading animation to card
                const card = button.closest('.form-card');
                card.classList.add('loading-skeleton');
                
                // Simulate loading and redirect
                setTimeout(() => {
                    window.location.href = url;
                }, 800);
            }

            updateNoResultsDisplay(visibleCount) {
                if (this.noResultsAlert) {
                    if (visibleCount === 0 && this.formCards.length > 0) {
                        this.noResultsAlert.classList.remove('d-none');
                        this.noResultsAlert.classList.add('animate__animated', 'animate__fadeIn');
                    } else {
                        this.noResultsAlert.classList.add('d-none');
                    }
                }
            }

            updateCounters() {
                const counts = { pending: 0, completed: 0, expired: 0, notStarted: 0 };
                
                this.formCards.forEach(card => {
                    if (card.style.display !== 'none') {
                        const filter = card.dataset.filter;
                        if (filter === 'not-started') {
                            counts.notStarted++;
                        } else {
                            counts[filter]++;
                        }
                    }
                });

                // Animate counter updates
                Object.keys(counts).forEach(key => {
                    if (this.counters[key]) {
                        this.animateCounter(this.counters[key], counts[key]);
                    }
                });
            }

            getCurrentCounts() {
                const counts = { pending: 0, completed: 0, expired: 0, notStarted: 0 };
                this.formCards.forEach(card => {
                    const filter = card.dataset.filter;
                    if (filter === 'not-started') {
                        counts.notStarted++;
                    } else {
                        counts[filter]++;
                    }
                });
                return counts;
            }

            animateCounter(element, newValue) {
                const currentValue = parseInt(element.textContent);
                if (currentValue === newValue) return;

                const duration = 500;
                const steps = 20;
                const stepValue = (newValue - currentValue) / steps;
                let currentStep = 0;

                const timer = setInterval(() => {
                    currentStep++;
                    const value = Math.round(currentValue + (stepValue * currentStep));
                    element.textContent = value;

                    if (currentStep >= steps) {
                        clearInterval(timer);
                        element.textContent = newValue;
                    }
                }, duration / steps);
            }

            animateFilterChange() {
                this.formCards.forEach((card, index) => {
                    if (card.style.display !== 'none') {
                        card.style.animationDelay = `${index * 0.05}s`;
                        card.classList.add('animate__animated', 'animate__fadeInUp');
                        
                        // Remove animation classes after completion
                        setTimeout(() => {
                            card.classList.remove('animate__animated', 'animate__fadeInUp');
                        }, 1000);
                    }
                });
            }

            handleScroll() {
                const floatingBtn = document.querySelector('.floating-action');
                if (floatingBtn) {
                    if (window.scrollY > 300) {
                        floatingBtn.style.opacity = '1';
                        floatingBtn.style.transform = 'scale(1)';
                    } else {
                        floatingBtn.style.opacity = '0.7';
                        floatingBtn.style.transform = 'scale(0.8)';
                    }
                }
            }

            handleKeyboardShortcuts(e) {
                // Ctrl/Cmd + F for search
                if ((e.ctrlKey || e.metaKey) && e.key === 'f') {
                    e.preventDefault();
                    if (this.searchInput) {
                        this.searchInput.focus();
                    }
                }

                // Number keys for filters
                const filterMap = {
                    '1': 'all',
                    '2': 'pending',
                    '3': 'completed',
                    '4': 'expired',
                    '5': 'not-started'
                };

                if (e.altKey && filterMap[e.key]) {
                    e.preventDefault();
                    this.handleFilter(filterMap[e.key]);
                }

                // Escape to clear search
                if (e.key === 'Escape') {
                    if (this.searchInput) {
                        this.searchInput.value = '';
                        this.handleSearch('');
                    }
                }
            }

            setupTouchGestures() {
                // Add swipe gestures for mobile filter navigation
                let touchStartX = 0;
                let touchEndX = 0;

                document.addEventListener('touchstart', (e) => {
                    touchStartX = e.changedTouches[0].screenX;
                }, { passive: true });

                document.addEventListener('touchend', (e) => {
                    touchEndX = e.changedTouches[0].screenX;
                    this.handleSwipeGesture();
                }, { passive: true });
            }

            handleSwipeGesture() {
                const swipeThreshold = 50;
                const swipeDistance = touchEndX - touchStartX;

                if (Math.abs(swipeDistance) > swipeThreshold) {
                    const filters = ['all', 'pending', 'completed', 'expired', 'not-started'];
                    const currentIndex = filters.indexOf(this.currentFilter);
                    
                    if (swipeDistance > 0 && currentIndex > 0) {
                        // Swipe right - previous filter
                        this.handleFilter(filters[currentIndex - 1]);
                    } else if (swipeDistance < 0 && currentIndex < filters.length - 1) {
                        // Swipe left - next filter
                        this.handleFilter(filters[currentIndex + 1]);
                    }
                }
            }

            showConfirmDialog(title, message, onConfirm) {
                // Create modern confirm dialog
                const result = confirm(`${title}\n\n${message}`);
                if (result && onConfirm) {
                    onConfirm();
                }
            }

            showNotification(message, type = 'info') {
                // Create toast notification
                const toast = document.createElement('div');
                toast.className = `toast align-items-center text-white bg-${type} border-0`;
                toast.setAttribute('role', 'alert');
                toast.innerHTML = `
                    <div class="d-flex">
                        <div class="toast-body">${message}</div>
                        <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
                    </div>
                `;

                // Add to page
                let toastContainer = document.querySelector('.toast-container');
                if (!toastContainer) {
                    toastContainer = document.createElement('div');
                    toastContainer.className = 'toast-container position-fixed top-0 end-0 p-3';
                    document.body.appendChild(toastContainer);
                }

                toastContainer.appendChild(toast);
                const bsToast = new bootstrap.Toast(toast);
                bsToast.show();

                // Remove after hiding
                toast.addEventListener('hidden.bs.toast', () => {
                    toast.remove();
                });
            }

            // Utility functions
            debounce(func, wait) {
                let timeout;
                return function executedFunction(...args) {
                    const later = () => {
                        clearTimeout(timeout);
                        func(...args);
                    };
                    clearTimeout(timeout);
                    timeout = setTimeout(later, wait);
                };
            }

            throttle(func, limit) {
                let inThrottle;
                return function() {
                    const args = arguments;
                    const context = this;
                    if (!inThrottle) {
                        func.apply(context, args);
                        inThrottle = true;
                        setTimeout(() => inThrottle = false, limit);
                    }
                }
            }
        }

        // Global functions for backward compatibility
        function scrollToTop() {
            window.scrollTo({
                top: 0,
                behavior: 'smooth'
            });
        }

        // Initialize app when DOM is ready
        document.addEventListener('DOMContentLoaded', () => {
            window.formEvaluationApp = new FormEvaluationApp();
            
            // Add keyboard shortcut hints
            console.log('🎯 Keyboard Shortcuts:');
            console.log('Ctrl/Cmd + F: Focus search');
            console.log('Alt + 1-5: Quick filter');
            console.log('Escape: Clear search');
        });

        // Performance optimization
        window.addEventListener('load', () => {
            // Preload critical resources
            const criticalImages = document.querySelectorAll('img[data-src]');
            criticalImages.forEach(img => {
                img.src = img.dataset.src;
            });

            // Initialize service worker if available
            if ('serviceWorker' in navigator) {
                navigator.serviceWorker.register('/sw.js').catch(console.error);
            }

            console.log('📱 Form Evaluation App fully loaded and optimized');
        });

        // Error handling
        window.addEventListener('error', (e) => {
            console.error('App Error:', e.error);
            if (window.formEvaluationApp) {
                window.formEvaluationApp.showNotification('เกิดข้อผิดพลาด กรุณาลองใหม่อีกครั้ง', 'danger');
            }
        });

        // Network status handling
        window.addEventListener('online', () => {
            if (window.formEvaluationApp) {
                window.formEvaluationApp.showNotification('เชื่อมต่ออินเทอร์เน็ตแล้ว', 'success');
            }
        });

        window.addEventListener('offline', () => {
            if (window.formEvaluationApp) {
                window.formEvaluationApp.showNotification('ไม่มีการเชื่อมต่ออินเทอร์เน็ต', 'warning');
            }
        });
    </script>
</body>
</html>