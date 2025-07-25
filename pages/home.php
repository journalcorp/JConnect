<?php
// เปิด error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// เริ่มต้น session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// เชื่อมต่อฐานข้อมูลก่อน
include_once '../config.php';

include_once '../includes/header.php';
include_once '../includes/menu.php';

// ตรวจสอบว่า user ได้ล็อกอินหรือยัง
if (!isset($_SESSION['employee_id'])) {
    die("กรุณาเข้าสู่ระบบก่อนใช้งานหน้านี้");
}

// ดึง employee_id ของผู้ที่ล็อกอิน
$employee_id = $_SESSION['employee_id'];

// เชื่อมต่อฐานข้อมูล
$conn = dbConnect();

// ดึงข้อมูลแบบฟอร์มที่ต้องทำการประเมิน พร้อมข้อมูล emp_pe และ emp_as (แบบง่าย)
$sql = "SELECT fs.FormID, f.FormName, f.editlink, fs.emp_as, fs.emp_pe, fs.status_form, fs.TransactionID,
               CONCAT(e.first_name_th, ' ', e.last_name_th) AS emp_pe_name
        FROM form_score fs
        INNER JOIN form f ON fs.FormID = f.FormID
        LEFT JOIN employees e ON fs.emp_pe = e.employee_id
        WHERE fs.emp_as = ? AND (fs.status_form = 'disable' OR fs.status_form = 'enable')";

// ใช้ SQLSRV syntax
$forms = [];
$params = array($employee_id);
$stmt = sqlsrv_query($conn, $sql, $params);

if ($stmt) {
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $forms[] = $row;
    }
    sqlsrv_free_stmt($stmt);
}

// ดึงข้อมูลวันหยุดจากตาราง public_holidays
$sqlHolidays = "SELECT holiday_date, holiday_name FROM public_holidays ORDER BY holiday_date ASC";
$stmtHolidays = sqlsrv_query($conn, $sqlHolidays);

$holidays = [];
if ($stmtHolidays) {
    while ($row = sqlsrv_fetch_array($stmtHolidays, SQLSRV_FETCH_ASSOC)) {
        $holidays[] = $row;
    }
    sqlsrv_free_stmt($stmtHolidays);
}

function formatDateThai($date) {
    $thaiDays = [
        'อาทิตย์', 'จันทร์', 'อังคาร', 'พุธ', 'พฤหัสบดี', 'ศุกร์', 'เสาร์'
    ];
    $thaiMonths = [
        1 => 'มกราคม', 'กุมภาพันธ์', 'มีนาคม', 'เมษายน', 'พฤษภาคม', 'มิถุนายน',
        'กรกฎาคม', 'สิงหาคม', 'กันยายน', 'ตุลาคม', 'พฤศจิกายน', 'ธันวาคม'
    ];
    $timestamp = strtotime($date);
    $day = $thaiDays[date('w', $timestamp)]; // ดึงชื่อวัน
    $dateNum = date('j', $timestamp); // ดึงวันที่
    $month = $thaiMonths[date('n', $timestamp)]; // ดึงชื่อเดือน
    $year = date('Y', $timestamp) + 543; // แปลงปีเป็น พ.ศ.
    return "$dateNum $month $year";
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- Bootstrap CSS จาก CDN -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <!-- ฟอนต์ Prompt -->
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@400;600&display=swap" rel="stylesheet">
    <!-- styles.css -->
    <!-- <link rel="stylesheet" href="../assets/css/styles.css"> -->
    <title>Home - Web Portal</title>
    <style>
        :root {
            --primary-color: #6366f1;
            --primary-dark: #4f46e5;
            --secondary-color: #10b981;
            --accent-color: #f59e0b;
            --danger-color: #ef4444;
            --warning-color: #f97316;
            --success-color: #22c55e;
            --info-color: #3b82f6;
            
            --gray-50: #f9fafb;
            --gray-100: #f3f4f6;
            --gray-200: #e5e7eb;
            --gray-300: #d1d5db;
            --gray-400: #9ca3af;
            --gray-500: #6b7280;
            --gray-600: #4b5563;
            --gray-700: #374151;
            --gray-800: #1f2937;
            --gray-900: #111827;
            
            --white: #ffffff;
            --black: #000000;
            
            /* Desktop Variables */
            --primary-gray: #2c3e50;
            --light-gray: #ecf0f1;
            --medium-gray: #95a5a6;
            --dark-gray: #34495e;
            --card-shadow: 0 8px 32px rgba(0,0,0,0.08);
            --card-shadow-hover: 0 16px 48px rgba(0,0,0,0.12);
            --border-radius: 24px;
            --border-radius-small: 16px;
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            --gradient-primary: linear-gradient(135deg, var(--primary-gray) 0%, var(--dark-gray) 100%);
            --gradient-card: linear-gradient(135deg, var(--white) 0%, #f8f9fa 100%);
            --gradient-bg: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            
            /* Mobile App Variables - Clean White Theme */
            --app-bg: #ffffff;
            --card-bg: #ffffff;
            --mobile-card-shadow: 0 1px 4px rgba(0, 0, 0, 0.04), 0 0 8px rgba(0, 0, 0, 0.02);
            --mobile-card-shadow-hover: 0 2px 8px rgba(0, 0, 0, 0.08), 0 0 16px rgba(0, 0, 0, 0.04);
            --mobile-border-radius: 12px;
            --mobile-border-radius-lg: 16px;
            --mobile-border-radius-sm: 8px;
            --mobile-border-radius-xs: 6px;
            
            /* Mobile Gradients - Clean White Theme */
            --mobile-gradient-primary: linear-gradient(135deg, #f8f9fa 0%, #ffffff 100%);
            --mobile-gradient-secondary: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);
            --mobile-gradient-success: linear-gradient(135deg, #e8f5e8 0%, #f0f8f0 100%);
            --mobile-gradient-warning: linear-gradient(135deg, #fff3cd 0%, #fef8e6 100%);
            --mobile-gradient-danger: linear-gradient(135deg, #f8d7da 0%, #fde8ea 100%);
            --mobile-gradient-bg: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);
            --mobile-gradient-card: linear-gradient(135deg, #ffffff 0%, #ffffff 100%);
            
            /* Mobile Clean Colors */
            --mobile-primary: #2c3e50;
            --mobile-secondary: #6c757d;
            --mobile-light: #f8f9fa;
            --mobile-lighter: #ffffff;
            --mobile-dark: #212529;
            --mobile-darker: #000000;
            --mobile-accent: #007bff;
            --mobile-success: #28a745;
            --mobile-warning: #ffc107;
            --mobile-danger: #dc3545;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body, h1, h2, h3, h4, h5, h6, label, input, button, .form-label, .form-control, .card, .card-header, .card-title, .card-text {
            font-family: 'Prompt', sans-serif !important;
        }

        /* ===========================================
           DESKTOP STYLES (PC/Tablet Landscape)
           =========================================== */
        @media (min-width: 768px) {
            body {
                background: var(--gradient-bg);
                min-height: 100vh;
                position: relative;
                color: var(--primary-gray);
                line-height: 1.6;
                display: flex;
                flex-direction: column;
            }

            body::before {
                content: '';
                position: fixed;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="grid" width="10" height="10" patternUnits="userSpaceOnUse"><path d="M 10 0 L 0 0 0 10" fill="none" stroke="rgba(255,255,255,0.03)" stroke-width="1"/></pattern></defs><rect width="100%" height="100%" fill="url(%23grid)"/></svg>');
                pointer-events: none;
                z-index: -1;
            }

            .container {
                position: relative;
                z-index: 1;
                max-width: 1200px;
                margin: 0 auto;
                padding: 0 20px;
                flex: 1;
            }

            /* Hide mobile elements on desktop */
            .mobile-only {
                display: none !important;
            }

            /* Welcome Header */
            .welcome-header {
                background: var(--gradient-card);
                border-radius: var(--border-radius);
                padding: 2.5rem 3rem;
                margin-bottom: 2.5rem;
                box-shadow: var(--card-shadow);
                border: 1px solid rgba(255, 255, 255, 0.8);
                position: relative;
                overflow: hidden;
            }

            .welcome-header::before {
                content: '';
                position: absolute;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                background: linear-gradient(45deg, transparent 48%, rgba(255,255,255,0.1) 50%, transparent 52%);
                pointer-events: none;
                animation: shimmer 3s infinite;
            }

            @keyframes shimmer {
                0% { transform: translateX(-100%); }
                100% { transform: translateX(100%); }
            }

            .welcome-header h2 {
                font-size: 2.2rem;
                font-weight: 300;
                color: var(--primary-gray);
                margin: 0 0 0.75rem 0;
                position: relative;
                z-index: 2;
                letter-spacing: -0.5px;
            }

            .welcome-header p {
                font-size: 1.1rem;
                color: var(--medium-gray);
                margin: 0;
                font-weight: 300;
                position: relative;
                z-index: 2;
            }

            /* Card Grid */
            .card-grid {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(480px, 1fr));
                gap: 2rem;
                margin-bottom: 2rem;
                align-items: start;
            }

            /* Cards */
            .card {
                background: var(--white);
                border-radius: var(--border-radius);
                box-shadow: var(--card-shadow);
                border: 1px solid rgba(255, 255, 255, 0.8);
                overflow: hidden;
                transition: var(--transition);
                height: 100%;
                display: flex;
                flex-direction: column;
            }

            .card:hover {
                transform: translateY(-8px);
                box-shadow: var(--card-shadow-hover);
            }

            .card-header {
                background: var(--gradient-primary);
                color: var(--white);
                border: none;
                padding: 1.75rem 2.25rem;
                font-weight: 500;
                font-size: 1.15rem;
                position: relative;
                overflow: hidden;
                flex-shrink: 0;
            }

            .card-header::before {
                content: '';
                position: absolute;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                background: linear-gradient(45deg, transparent 48%, rgba(255,255,255,0.1) 50%, transparent 52%);
                pointer-events: none;
                transform: translateX(-100%);
                transition: transform 0.6s ease;
            }

            .card:hover .card-header::before {
                transform: translateX(100%);
            }

            .card-header i {
                margin-right: 0.75rem;
                font-size: 1.3rem;
            }

            .card-header .btn-outline-dark {
                background: rgba(255, 255, 255, 0.15);
                border: 1px solid rgba(255, 255, 255, 0.3);
                color: var(--white);
                border-radius: 20px;
                padding: 0.5rem 1.25rem;
                font-size: 0.85rem;
                font-weight: 500;
                transition: var(--transition);
            }

            .card-header .btn-outline-dark:hover {
                background: var(--white);
                color: var(--primary-gray);
                transform: translateY(-2px);
                box-shadow: 0 4px 12px rgba(255, 255, 255, 0.3);
            }

            .card-body {
                padding: 2.25rem;
                background: var(--white);
                flex: 1;
                display: flex;
                flex-direction: column;
                min-height: 300px;
                max-height: 600px;
                overflow: hidden;
            }

            .card-body.scrollable {
                overflow-y: auto;
            }

            /* Desktop Info Cards Container */
            .info-cards {
                display: grid;
                gap: 1.25rem;
                flex: 1;
                overflow-y: auto;
                padding-right: 0.5rem;
                align-content: start;
            }

            /* Desktop Task Cards */
            .desktop-task-card {
                background: var(--gradient-card);
                border: 1px solid rgba(236, 240, 241, 0.6);
                border-radius: var(--border-radius-small);
                padding: 1.75rem;
                margin-bottom: 1.25rem;
                transition: var(--transition);
                position: relative;
                overflow: hidden;
            }

            .desktop-task-card::before {
                content: '';
                position: absolute;
                top: 0;
                left: 0;
                width: 5px;
                height: 100%;
                background: var(--gradient-primary);
                border-radius: 0 3px 3px 0;
            }

            .desktop-task-card:hover {
                transform: translateX(8px);
                box-shadow: 0 8px 24px rgba(0,0,0,0.1);
                border-color: rgba(236, 240, 241, 0.8);
            }

            .desktop-task-card h6 {
                color: var(--primary-gray);
                font-weight: 600;
                margin-bottom: 0.75rem;
                font-size: 1rem;
            }

            .desktop-task-card h6 i {
                color: var(--medium-gray);
                margin-right: 0.5rem;
                font-size: 1.1rem;
            }

            .desktop-task-card p {
                color: var(--medium-gray);
                font-size: 0.9rem;
                margin-bottom: 1.25rem;
                line-height: 1.5;
            }

            .desktop-task-card .small {
                font-size: 0.85rem;
                line-height: 1.4;
            }

            /* Desktop Buttons */
            .btn-warning {
                background: var(--gradient-primary);
                color: var(--white);
                border: none;
                border-radius: 12px;
                padding: 0.75rem 1.5rem;
                font-weight: 500;
                transition: var(--transition);
                font-size: 0.9rem;
                letter-spacing: 0.3px;
            }

            .btn-warning:hover {
                color: var(--white);
                transform: translateY(-2px);
                box-shadow: 0 8px 20px rgba(44, 62, 80, 0.25);
                background: linear-gradient(135deg, var(--dark-gray) 0%, var(--primary-gray) 100%);
            }

            .btn-success {
                background: linear-gradient(135deg, #27ae60 0%, #2ecc71 100%);
                color: var(--white);
                border: none;
                border-radius: 12px;
                padding: 0.75rem 1.5rem;
                font-weight: 500;
                font-size: 0.9rem;
                opacity: 0.8;
                cursor: not-allowed;
            }

            .btn-success:disabled {
                background: linear-gradient(135deg, #95a5a6 0%, #7f8c8d 100%);
                color: var(--white);
                opacity: 0.6;
            }

            /* Desktop Badges */
            .badge {
                font-size: 0.75rem;
                padding: 0.4rem 0.8rem;
                border-radius: 20px;
                font-weight: 500;
                letter-spacing: 0.3px;
            }

            .badge.bg-warning {
                background: linear-gradient(135deg, #f39c12 0%, #e67e22 100%) !important;
                color: var(--white) !important;
            }

            .badge.bg-success {
                background: linear-gradient(135deg, #27ae60 0%, #2ecc71 100%) !important;
                color: var(--white) !important;
            }

            /* Footer for Desktop */
            footer {
                margin-top: auto;
                background: #2c3e50;
                color: white;
                padding: 1rem 0;
                text-align: center;
                border-top: none;
            }

            footer small {
                font-size: 0.875rem;
                opacity: 0.9;
            }
        }

        /* ===========================================
           MOBILE STYLES (Phones/Small Tablets)
           =========================================== */
        @media (max-width: 767px) {
            body {
                background: var(--mobile-lighter);
                min-height: 100vh;
                color: var(--mobile-dark);
                line-height: 1.6;
                overflow-x: hidden;
                padding-bottom: 80px;
            }

            /* Hide desktop elements on mobile */
            .desktop-only {
                display: none !important;
            }

            /* Mobile App Container */
            .app-container {
                max-width: 100%;
                margin: 0 auto;
                background: var(--white);
                min-height: 100vh;
                position: relative;
            }

            /* Status Bar Simulation - Clean White Theme */
            .status-bar {
                height: 44px;
                background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);
                display: flex;
                align-items: center;
                justify-content: space-between;
                padding: 0 20px;
                color: var(--mobile-dark);
                font-size: 14px;
                font-weight: 500;
                border-bottom: 1px solid #e9ecef;
            }

            .status-bar .time {
                font-weight: 600;
            }

            .status-bar .indicators {
                display: flex;
                gap: 5px;
                align-items: center;
            }

            .status-bar .battery {
                width: 24px;
                height: 12px;
                border: 1px solid var(--mobile-dark);
                border-radius: 2px;
                position: relative;
            }

            .status-bar .battery::after {
                content: '';
                position: absolute;
                right: -3px;
                top: 3px;
                width: 2px;
                height: 6px;
                background: var(--mobile-dark);
                border-radius: 0 1px 1px 0;
            }

            .status-bar .battery-fill {
                width: 80%;
                height: 100%;
                background: var(--mobile-success);
                border-radius: 1px;
            }

            /* App Header - Clean White Theme */
            .app-header {
                background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);
                padding: 30px 20px;
                color: var(--mobile-dark);
                position: relative;
                overflow: hidden;
                border-bottom: 1px solid #e9ecef;
            }

            .app-header::before {
                content: '';
                position: absolute;
                top: -50%;
                right: -20%;
                width: 200px;
                height: 200px;
                background: rgba(44, 62, 80, 0.03);
                border-radius: 50%;
                animation: float 6s ease-in-out infinite;
            }

            @keyframes float {
                0%, 100% { transform: translateY(0px); }
                50% { transform: translateY(-20px); }
            }

            .app-header h1 {
                font-size: 28px;
                font-weight: 600;
                margin-bottom: 8px;
                position: relative;
                z-index: 2;
                color: var(--mobile-dark);
            }

            .app-header p {
                font-size: 16px;
                opacity: 0.7;
                position: relative;
                z-index: 2;
                color: var(--mobile-secondary);
            }

            .app-header .profile-section {
                display: flex;
                align-items: center;
                gap: 15px;
                margin-top: 20px;
                position: relative;
                z-index: 2;
            }

            .app-header .avatar {
                width: 60px;
                height: 60px;
                background: linear-gradient(135deg, var(--mobile-accent) 0%, #0056b3 100%);
                border-radius: 50%;
                display: flex;
                align-items: center;
                justify-content: center;
                font-size: 24px;
                font-weight: 600;
                color: white;
                backdrop-filter: blur(10px);
                border: 3px solid rgba(255, 255, 255, 0.9);
                box-shadow: 0 4px 12px rgba(0, 123, 255, 0.15);
            }

            .app-header .user-info h3 {
                font-size: 18px;
                font-weight: 600;
                margin-bottom: 4px;
                color: var(--mobile-dark);
            }

            .app-header .user-info p {
                font-size: 14px;
                opacity: 0.7;
                margin: 0;
                color: var(--mobile-secondary);
            }

            /* Content Area - Clean White Theme */
            .app-content {
                padding: 25px 20px;
                background: var(--mobile-lighter);
            }

            /* Quick Stats */
            .quick-stats {
                display: grid;
                grid-template-columns: repeat(3, 1fr);
                gap: 15px;
                margin-bottom: 25px;
            }

            .stat-card {
                background: var(--card-bg);
                padding: 25px 20px;
                border-radius: var(--mobile-border-radius);
                text-align: center;
                box-shadow: var(--mobile-card-shadow);
                transition: var(--transition);
                border: 1px solid #f0f0f0;
            }

            .stat-card:hover {
                transform: translateY(-3px);
                box-shadow: var(--mobile-card-shadow-hover);
                border-color: #e0e0e0;
            }

            .stat-card .stat-number {
                font-size: 32px;
                font-weight: 700;
                margin-bottom: 8px;
                color: var(--mobile-accent);
            }

            .stat-card .stat-label {
                font-size: 13px;
                color: var(--mobile-secondary);
                font-weight: 500;
            }

            /* Section Header - Clean White Theme */
            .section-header {
                display: flex;
                align-items: center;
                justify-content: space-between;
                margin-bottom: 20px;
                padding-bottom: 12px;
                border-bottom: 1px solid #f0f0f0;
            }

            .section-header h2 {
                font-size: 22px;
                font-weight: 600;
                color: var(--mobile-dark);
                margin: 0;
            }

            .section-header .view-all {
                color: var(--mobile-accent);
                text-decoration: none;
                font-size: 14px;
                font-weight: 500;
                display: flex;
                align-items: center;
                gap: 5px;
            }

            .section-header .view-all:hover {
                color: #0056b3;
            }

            /* Task Cards */
            .task-list {
                margin-bottom: 25px;
            }

            .task-item {
                background: var(--card-bg);
                border-radius: var(--mobile-border-radius);
                padding: 25px;
                margin-bottom: 15px;
                box-shadow: var(--mobile-card-shadow);
                transition: var(--transition);
                border: 1px solid #f0f0f0;
                position: relative;
                overflow: hidden;
            }

            .task-item::before {
                content: '';
                position: absolute;
                left: 0;
                top: 0;
                bottom: 0;
                width: 4px;
                background: var(--mobile-accent);
            }

            .task-item.completed::before {
                background: var(--mobile-success);
            }

            .task-item:hover {
                transform: translateY(-2px);
                box-shadow: var(--mobile-card-shadow-hover);
                border-color: #e0e0e0;
            }

            .task-header {
                display: flex;
                align-items: flex-start;
                justify-content: space-between;
                margin-bottom: 15px;
            }

            .task-title {
                font-size: 16px;
                font-weight: 600;
                color: var(--mobile-dark);
                margin-bottom: 5px;
                line-height: 1.4;
            }

            .task-subtitle {
                font-size: 14px;
                color: var(--mobile-secondary);
                margin-bottom: 8px;
            }

            .task-details {
                font-size: 13px;
                color: var(--mobile-secondary);
                line-height: 1.5;
            }

            .task-status {
                display: flex;
                align-items: center;
                gap: 8px;
                margin-top: 15px;
            }
            /* Mobile Badges - Clean White Theme */
            .badge.pending {
                background: linear-gradient(135deg, var(--mobile-warning) 0%, #e0a800 100%);
                color: white;
                padding: 8px 16px;
                border-radius: var(--mobile-border-radius-xs);
                font-size: 12px;
                font-weight: 600;
                text-transform: uppercase;
                letter-spacing: 0.5px;
                box-shadow: 0 2px 4px rgba(255, 193, 7, 0.2);
            }

            .badge.completed {
                background: linear-gradient(135deg, var(--mobile-success) 0%, #1e7e34 100%);
                color: white;
                padding: 8px 16px;
                border-radius: var(--mobile-border-radius-xs);
                font-size: 12px;
                font-weight: 600;
                text-transform: uppercase;
                letter-spacing: 0.5px;
                box-shadow: 0 2px 4px rgba(40, 167, 69, 0.2);
            }

            .badge.waiting {
                background: linear-gradient(135deg, var(--mobile-danger) 0%, #c82333 100%);
                color: white;
                padding: 8px 16px;
                border-radius: var(--mobile-border-radius-xs);
                font-size: 12px;
                font-weight: 600;
                text-transform: uppercase;
                letter-spacing: 0.5px;
            /* Mobile Buttons - Clean White Theme */
            .btn-mobile {
                padding: 14px 24px;
                border-radius: var(--mobile-border-radius);
                font-size: 14px;
                font-weight: 600;
                border: none;
                cursor: pointer;
                transition: var(--transition);
                display: inline-flex;
                align-items: center;
                gap: 8px;
                text-decoration: none;
                min-height: 48px;
            }

            .btn-primary {
                background: linear-gradient(135deg, var(--mobile-accent) 0%, #0056b3 100%);
                color: white;
                box-shadow: 0 2px 8px rgba(0, 123, 255, 0.2);
            }

            .btn-primary:hover {
                transform: translateY(-2px);
                box-shadow: 0 4px 16px rgba(0, 123, 255, 0.3);
            }

            .btn-success {
                background: linear-gradient(135deg, var(--mobile-success) 0%, #1e7e34 100%);
                color: white;
                box-shadow: 0 2px 8px rgba(40, 167, 69, 0.2);
            }

            .btn-disabled {
                background: #f8f9fa;
                color: var(--mobile-secondary);
                cursor: not-allowed;
                border: 1px solid #e9ecef;
            }btn-disabled {
                background: var(--mobile-light);
                color: var(--mobile-secondary);
                cursor: not-allowed;
            }

            .info-card {
                background: var(--card-bg);
                border-radius: var(--mobile-border-radius);
                padding: 25px;
                box-shadow: var(--mobile-card-shadow);
                border: 1px solid #f0f0f0;
                transition: var(--transition);
            }

            .info-card:hover {
                transform: translateY(-2px);
                box-shadow: var(--mobile-card-shadow-hover);
                border-color: #e0e0e0;
            }   border: 1px solid var(--mobile-light);
                transition: var(--transition);
            }

            .info-card:hover {
                transform: translateY(-2px);
                box-shadow: var(--mobile-card-shadow-hover);
            }

            .info-card-header {
                display: flex;
                align-items: center;
                gap: 10px;
                margin-bottom: 15px;
            }

            .info-card-header .icon {
                width: 40px;
                height: 40px;
            .info-card-header .icon.primary {
                background: linear-gradient(135deg, var(--mobile-accent) 0%, #0056b3 100%);
                box-shadow: 0 2px 8px rgba(0, 123, 255, 0.2);
            }

            .info-card-header .icon.success {
                background: linear-gradient(135deg, var(--mobile-success) 0%, #1e7e34 100%);
                box-shadow: 0 2px 8px rgba(40, 167, 69, 0.2);
            }

            .info-card-header .icon.warning {
                background: linear-gradient(135deg, var(--mobile-warning) 0%, #e0a800 100%);
                box-shadow: 0 2px 8px rgba(255, 193, 7, 0.2);
            }

            .info-card-header .icon.success {
                background: var(--mobile-gradient-secondary);
            }

            .info-card-header .icon.warning {
                background: var(--mobile-gradient-warning);
            }

            .info-card-header h3 {
                font-size: 16px;
                font-weight: 600;
                color: var(--mobile-dark);
                margin: 0;
            }

            .info-card-content {
                color: var(--mobile-secondary);
                font-size: 14px;
                line-height: 1.6;
            }

            .info-stats {
                display: grid;
                grid-template-columns: repeat(3, 1fr);
                gap: 15px;
                margin: 15px 0;
            }

            .info-stat {
                text-align: center;
            }

            .info-stat .number {
            /* Progress Bar - Clean White Theme */
            .progress-container {
                margin: 20px 0;
            }

            .progress-label {
                display: flex;
                justify-content: space-between;
                font-size: 14px;
                color: var(--mobile-secondary);
                margin-bottom: 10px;
                font-weight: 500;
            }

            .progress-bar-container {
                height: 10px;
                background: #f0f0f0;
                border-radius: 10px;
                overflow: hidden;
            }

            .progress-bar {
                height: 100%;
                background: linear-gradient(135deg, var(--mobile-accent) 0%, #0056b3 100%);
                border-radius: 10px;
                transition: width 0.5s ease;
            }   height: 8px;
                background: var(--mobile-light);
                border-radius: 10px;
                overflow: hidden;
            }

            /* Floating Action Button - Clean White Theme */
            .fab {
                position: fixed;
                bottom: 90px;
                right: 20px;
                width: 56px;
                height: 56px;
                background: linear-gradient(135deg, var(--mobile-accent) 0%, #0056b3 100%);
                border-radius: 50%;
                border: none;
                color: white;
                font-size: 20px;
                box-shadow: 0 4px 16px rgba(0, 123, 255, 0.3);
                cursor: pointer;
                transition: var(--transition);
                z-index: 1000;
            }

            .fab:hover {
                transform: scale(1.1);
                box-shadow: 0 6px 20px rgba(0, 123, 255, 0.4);
            }   border: none;
                color: white;
            /* Footer for Mobile - Clean White Theme */
            footer {
                background: linear-gradient(135deg, #f8f9fa 0%, #ffffff 100%);
                color: var(--mobile-dark);
                padding: 20px 0;
                text-align: center;
                border-top: 1px solid #e9ecef;
            }

            footer small {
                font-size: 12px;
                opacity: 0.7;
            }* Footer for Mobile - Gray Theme */
            footer {
                background: var(--mobile-dark);
                color: white;
                padding: 15px 0;
                text-align: center;
                border-top: none;
            }

            footer small {
                font-size: 12px;
                opacity: 0.85;
            }
        }

        /* ===========================================
           SHARED STYLES
           =========================================== */
        
        /* Loading States */
        .loading {
            position: relative;
            overflow: hidden;
        }

        .loading::after {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.6), transparent);
            animation: loading 1.5s infinite;
        }

        @keyframes loading {
            0% { left: -100%; }
            100% { left: 100%; }
        }

        /* Animations */
        .fade-in {
            animation: fadeIn 0.6s ease-out;
        }

        .slide-up {
            animation: slideUp 0.5s ease-out;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        /* Dark mode support - Clean White Theme */
        @media (prefers-color-scheme: dark) {
            :root {
                --mobile-lighter: #f8f9fa;
                --mobile-light: #ffffff;
                --card-bg: #ffffff;
                --mobile-dark: #2c3e50;
                --mobile-secondary: #6c757d;
                --mobile-primary: #007bff;
            }
        }

        /* Dark mode support - Gray Theme */
        @media (prefers-color-scheme: dark) {
            :root {
                --mobile-lighter: #212529;
                --mobile-light: #343a40;
                --card-bg: #495057;
                --mobile-dark: #f8f9fa;
                --mobile-secondary: #adb5bd;
                --mobile-primary: #6c757d;
            }
        }
    </style>
</head>
<body>
<!-- Desktop Layout (PC/Tablet) -->
<div class="desktop-only">
    <div class="container py-4">
        <!-- Welcome Header -->
        <div class="welcome-header">
            <h2><i class="bi bi-house-door"></i> ยินดีต้อนรับ, <?php echo htmlspecialchars($_SESSION['first_name_th']); ?>!</h2>
            <p>จัดการงานและติดตามความคืบหน้าของการประเมินผลการปฏิบัติงาน</p>
        </div>

        <!-- Card Grid -->
        <div class="card-grid">
            <!-- Forms Card -->
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span><i class="bi bi-clipboard-check"></i> งานที่ต้องทำ</span>
                    <a href="all_report_emp.php" class="btn btn-outline-dark btn-sm">ดูทั้งหมด</a>
                </div>
                <div class="card-body scrollable">
                    <div class="info-cards">
                        <?php if (!empty($forms)): ?>
                            <?php foreach ($forms as $form): ?>
                                <?php
                                // ตรวจสอบว่ายังไม่ถึงวันที่ประเมินหรือไม่ และดึง start_date
                                $canEvaluate = true;
                                $startDate = null;
                                if (!empty($form['FormID'])) {
                                    $sqlDate = "SELECT start_date FROM form_score WHERE FormID = ? AND emp_as = ? AND emp_pe = ? AND TransactionID = ?";
                                    $paramsDate = array($form['FormID'], $form['emp_as'], $form['emp_pe'], $form['TransactionID']);
                                    $stmtDate = sqlsrv_query($conn, $sqlDate, $paramsDate);
                                    if ($stmtDate && $rowDate = sqlsrv_fetch_array($stmtDate, SQLSRV_FETCH_ASSOC)) {
                                        $startDate = $rowDate['start_date'];
                                    }
                                    if ($startDate) {
                                        $now = new DateTime();
                                        $start = is_object($startDate) ? $startDate : new DateTime($startDate);
                                        if ($now < $start) {
                                            $canEvaluate = false;
                                        }
                                    }
                                    if ($stmtDate) sqlsrv_free_stmt($stmtDate);
                                }
                                ?>
                                <div class="desktop-task-card">
                                    <h6><i class="bi bi-file-earmark-text"></i> <?php echo htmlspecialchars($form['FormName']); ?></h6>
                                    <p><strong>ผู้ถูกประเมิน:</strong> <?php echo htmlspecialchars($form['emp_pe_name'] ?? 'ไม่พบข้อมูล'); ?></p>
                                    <div class="small text-muted mb-3">
                                        รหัสพนักงาน: <?php echo htmlspecialchars($form['emp_pe'] ?? 'N/A'); ?>
                                        <?php if ($startDate): ?>
                                            <br>วันเริ่มประเมิน: <?php echo date('d/m/Y', is_object($startDate) ? $startDate->getTimestamp() : strtotime($startDate)); ?>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="d-flex justify-content-between align-items-center">
                                        <?php if ($form['status_form'] === 'disable'): ?>
                                            <span class="badge bg-warning">รอดำเนินการ</span>
                                            <?php if ($canEvaluate): ?>
                                                <a href="360_form.php?emp_as=<?php echo htmlspecialchars($form['emp_as']); ?>&emp_pe=<?php echo htmlspecialchars($form['emp_pe']); ?>&TransactionID=<?php echo htmlspecialchars($form['TransactionID']); ?>" 
                                                   class="btn btn-warning btn-sm" onclick="closeSidebar()">
                                                    <i class="bi bi-pencil-square"></i> ทำแบบประเมิน
                                                </a>
                                            <?php else: ?>
                                                <button class="btn btn-secondary btn-sm" disabled>
                                                    <i class="bi bi-clock"></i> รอประเมิน
                                                </button>
                                            <?php endif; ?>
                                        <?php elseif ($form['status_form'] === 'enable'): ?>
                                            <span class="badge bg-success">เสร็จสิ้น</span>
                                            <button class="btn btn-success btn-sm" disabled>
                                                <i class="bi bi-check-circle"></i> เสร็จสิ้นแล้ว
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="desktop-task-card">
                                <h6><i class="bi bi-info-circle"></i> ไม่มีงานที่ต้องทำ</h6>
                                <p>คุณได้ทำการประเมินครบถ้วนแล้ว</p>
                                <span class="badge bg-success">งานทั้งหมดเสร็จสิ้น</span>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Info Card -->
            <div class="card">
                <div class="card-header">
                    <i class="bi bi-info-circle"></i> ข้อมูลระบบ
                </div>
                <div class="card-body">
                    <div class="mb-4">
                        <h5 class="mb-3"><i class="bi bi-person-badge"></i> ข้อมูลผู้ใช้</h5>
                        <div class="row mb-3">
                            <div class="col-6">
                                <strong>รหัสพนักงาน:</strong><br>
                                <span class="text-primary"><?php echo htmlspecialchars($_SESSION['employee_id']); ?></span>
                            </div>
                            <div class="col-6">
                                <strong>ชื่อ-นามสกุล:</strong><br>
                                <span class="text-primary"><?php echo htmlspecialchars($_SESSION['first_name_th']); ?> <?php echo htmlspecialchars($_SESSION['last_name_th']); ?></span>
                            </div>
                        </div>
                        <p class="mb-0">
                            <strong>เข้าสู่ระบบล่าสุด:</strong> 
                            <span class="text-success"><?php echo date('d/m/Y H:i'); ?> น.</span>
                        </p>
                    </div>

                    <div class="mb-4">
                        <h5 class="mb-3"><i class="bi bi-graph-up"></i> สถิติการทำงาน</h5>
                        <div class="row text-center">
                            <div class="col-4">
                                <div class="h3 text-primary mb-1"><?php echo count($forms); ?></div>
                                <small class="text-muted">ทั้งหมด</small>
                            </div>
                            <div class="col-4">
                                <div class="h3 text-success mb-1"><?php echo count(array_filter($forms, function($f) { return $f['status_form'] === 'enable'; })); ?></div>
                                <small class="text-muted">เสร็จสิ้น</small>
                            </div>
                            <div class="col-4">
                                <div class="h3 text-warning mb-1"><?php echo count(array_filter($forms, function($f) { return $f['status_form'] === 'disable'; })); ?></div>
                                <small class="text-muted">รอดำเนินการ</small>
                            </div>
                        </div>
                        
                        <?php 
                        $total = count($forms);
                        $completed = count(array_filter($forms, function($f) { return $f['status_form'] === 'enable'; }));
                        $percentage = $total > 0 ? round(($completed / $total) * 100) : 0;
                        ?>
                        <div class="mt-3">
                            <div class="d-flex justify-content-between small mb-1">
                                <span>ความคืบหน้า</span>
                                <span><?php echo $percentage; ?>%</span>
                            </div>
                            <div class="progress" style="height: 8px;">
                                <div class="progress-bar bg-success" role="progressbar" style="width: <?php echo $percentage; ?>%"></div>
                            </div>
                        </div>
                    </div>

                    <div>
                        <h5 class="mb-3"><i class="bi bi-shield-check"></i> ความปลอดภัย</h5>
                        <div class="row mb-3">
                            <div class="col-6">
                                <strong>สถานะระบบ:</strong><br>
                                <span class="text-success">
                                    <i class="bi bi-check-circle-fill"></i> ปลอดภัย
                                </span>
                            </div>
                            <div class="col-6">
                                <strong>เซสชั่น:</strong><br>
                                <span class="text-info">
                                    <i class="bi bi-clock-fill"></i> ใช้งานได้
                                </span>
                            </div>
                        </div>
                        <p class="small text-muted mb-0">
                            <strong>คำแนะนำ:</strong> เก็บรักษาข้อมูลการเข้าสู่ระบบอย่างปลอดภัย และออกจากระบบเมื่อเสร็จสิ้นการใช้งาน
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Mobile Layout (Phones/Small Tablets) -->
<div class="mobile-only">
    <!-- Mobile App Container -->
    <div class="app-container">
        <!-- Status Bar -->
        <div class="status-bar">
            <div class="time" id="currentTime">09:41</div>
            <div class="indicators">
                <i class="bi bi-wifi"></i>
                <i class="bi bi-reception-4"></i>
                <div class="battery">
                    <div class="battery-fill"></div>
                </div>
            </div>
        </div>

        <!-- App Header -->
        <div class="app-header">
            <h1><i class="bi bi-house-door-fill"></i> หน้าหลัก</h1>
            <p>จัดการงานและติดตามความคืบหน้า</p>
            
            <div class="profile-section">
                <div class="avatar">
                    <?php 
                    $initials = mb_substr($_SESSION['first_name_th'], 0, 1, 'UTF-8');
                    if (isset($_SESSION['last_name_th'])) {
                        $initials .= mb_substr($_SESSION['last_name_th'], 0, 1, 'UTF-8');
                    }
                    echo $initials;
                    ?>
                </div>
                <div class="user-info">
                    <h3><?php echo htmlspecialchars($_SESSION['first_name_th']); ?> <?php echo htmlspecialchars($_SESSION['last_name_th']); ?></h3>
                    <p>รหัส: <?php echo htmlspecialchars($_SESSION['employee_id']); ?></p>
                </div>
            </div>
        </div>

        <!-- App Content -->
        <div class="app-content">
            <!-- Quick Stats -->
            <div class="quick-stats">
                <div class="stat-card fade-in">
                    <div class="stat-number"><?php echo count($forms); ?></div>
                    <div class="stat-label">งานทั้งหมด</div>
                </div>
                <div class="stat-card fade-in">
                    <div class="stat-number"><?php echo count(array_filter($forms, function($f) { return $f['status_form'] === 'enable'; })); ?></div>
                    <div class="stat-label">เสร็จสิ้น</div>
                </div>
                <div class="stat-card fade-in">
                    <div class="stat-number"><?php echo count(array_filter($forms, function($f) { return $f['status_form'] === 'disable'; })); ?></div>
                    <div class="stat-label">รอดำเนินการ</div>
                </div>
            </div>

            <!-- Tasks Section -->
            <div class="section-header">
                <h2><i class="bi bi-list-task"></i> งานที่ต้องทำ</h2>
                <a href="all_report_emp.php" class="view-all">
                    ดูทั้งหมด <i class="bi bi-arrow-right"></i>
                </a>
            </div>

            <div class="task-list">
                <?php if (!empty($forms)): ?>
                    <?php foreach ($forms as $index => $form): ?>
                        <?php
                        // ตรวจสอบว่ายังไม่ถึงวันที่ประเมินหรือไม่ และดึง start_date
                        $canEvaluate = true;
                        $startDate = null;
                        if (!empty($form['FormID'])) {
                            $sqlDate = "SELECT start_date FROM form_score WHERE FormID = ? AND emp_as = ? AND emp_pe = ? AND TransactionID = ?";
                            $paramsDate = array($form['FormID'], $form['emp_as'], $form['emp_pe'], $form['TransactionID']);
                            $stmtDate = sqlsrv_query($conn, $sqlDate, $paramsDate);
                            if ($stmtDate && $rowDate = sqlsrv_fetch_array($stmtDate, SQLSRV_FETCH_ASSOC)) {
                                $startDate = $rowDate['start_date'];
                            }
                            if ($startDate) {
                                $now = new DateTime();
                                $start = is_object($startDate) ? $startDate : new DateTime($startDate);
                                if ($now < $start) {
                                    $canEvaluate = false;
                                }
                            }
                            if ($stmtDate) sqlsrv_free_stmt($stmtDate);
                        }
                        ?>
                        <div class="task-item <?php echo $form['status_form'] === 'enable' ? 'completed' : ''; ?> slide-up" style="animation-delay: <?php echo $index * 0.1; ?>s;">
                            <div class="task-header">
                                <div>
                                    <div class="task-title"><?php echo htmlspecialchars($form['FormName']); ?></div>
                                    <div class="task-subtitle">ผู้ถูกประเมิน: <?php echo htmlspecialchars($form['emp_pe_name'] ?? 'ไม่พบข้อมูล'); ?></div>
                                    <div class="task-details">
                                        รหัส: <?php echo htmlspecialchars($form['emp_pe'] ?? 'N/A'); ?>
                                        <?php if ($startDate): ?>
                                            <br>วันเริ่มประเมิน: <?php echo date('d/m/Y', is_object($startDate) ? $startDate->getTimestamp() : strtotime($startDate)); ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="task-status">
                                <?php if ($form['status_form'] === 'disable'): ?>
                                    <span class="badge pending">รอดำเนินการ</span>
                                    <?php if ($canEvaluate): ?>
                                        <a href="360_form.php?emp_as=<?php echo htmlspecialchars($form['emp_as']); ?>&emp_pe=<?php echo htmlspecialchars($form['emp_pe']); ?>&TransactionID=<?php echo htmlspecialchars($form['TransactionID']); ?>" 
                                           class="btn-mobile btn-primary" onclick="closeSidebar()">
                                            <i class="bi bi-pencil-square"></i>
                                            ทำแบบประเมิน
                                        </a>
                                    <?php else: ?>
                                        <button class="btn-mobile btn-disabled" disabled>
                                            <i class="bi bi-clock"></i>
                                            รอประเมิน
                                        </button>
                                    <?php endif; ?>
                                <?php elseif ($form['status_form'] === 'enable'): ?>
                                    <span class="badge completed">เสร็จสิ้น</span>
                                    <button class="btn-mobile btn-success" disabled>
                                        <i class="bi bi-check-circle"></i>
                                        เสร็จสิ้นแล้ว
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="task-item">
                        <div class="task-header">
                            <div>
                                <div class="task-title"><i class="bi bi-info-circle"></i> ไม่มีงานที่ต้องทำ</div>
                                <div class="task-details">คุณได้ทำการประเมินครบถ้วนแล้ว</div>
                            </div>
                        </div>
                        <div class="task-status">
                            <span class="badge completed">งานทั้งหมดเสร็จสิ้น</span>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Info Section -->
            <div class="section-header">
                <h2><i class="bi bi-info-circle"></i> ข้อมูลระบบ</h2>
            </div>

            <div class="info-grid">
                <!-- User Info Card -->
                <div class="info-card fade-in">
                    <div class="info-card-header">
                        <div class="icon primary">
                            <i class="bi bi-person-badge"></i>
                        </div>
                        <h3>ข้อมูลผู้ใช้</h3>
                    </div>
                    <div class="info-card-content">
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px;">
                            <div>
                                <strong>รหัสพนักงาน:</strong><br>
                                <?php echo htmlspecialchars($_SESSION['employee_id']); ?>
                            </div>
                            <div>
                                <strong>ชื่อ-นามสกุล:</strong><br>
                                <?php echo htmlspecialchars($_SESSION['first_name_th']); ?> <?php echo htmlspecialchars($_SESSION['last_name_th']); ?>
                            </div>
                        </div>
                        <strong>เข้าสู่ระบบล่าสุด:</strong> 
                        <span style="color: var(--mobile-primary);"><?php echo date('d/m/Y H:i'); ?> น.</span>
                    </div>
                </div>

                <!-- Statistics Card -->
                <div class="info-card fade-in">
                    <div class="info-card-header">
                        <div class="icon success">
                            <i class="bi bi-graph-up"></i>
                        </div>
                        <h3>สถิติการทำงาน</h3>
                    </div>
                    <div class="info-card-content">
                        <div class="info-stats">
                            <div class="info-stat">
                                <div class="number"><?php echo count($forms); ?></div>
                                <div class="label">ทั้งหมด</div>
                            </div>
                            <div class="info-stat">
                                <div class="number"><?php echo count(array_filter($forms, function($f) { return $f['status_form'] === 'enable'; })); ?></div>
                                <div class="label">เสร็จสิ้น</div>
                            </div>
                            <div class="info-stat">
                                <div class="number"><?php echo count(array_filter($forms, function($f) { return $f['status_form'] === 'disable'; })); ?></div>
                                <div class="label">รอดำเนินการ</div>
                            </div>
                        </div>
                        
                        <?php 
                        $total = count($forms);
                        $completed = count(array_filter($forms, function($f) { return $f['status_form'] === 'enable'; }));
                        $percentage = $total > 0 ? round(($completed / $total) * 100) : 0;
                        ?>
                        <div class="progress-container">
                            <div class="progress-label">
                                <span>ความคืบหน้า</span>
                                <span><?php echo $percentage; ?>%</span>
                            </div>
                            <div class="progress-bar-container">
                                <div class="progress-bar" style="width: <?php echo $percentage; ?>%"></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Security Card -->
                <div class="info-card fade-in">
                    <div class="info-card-header">
                        <div class="icon warning">
                            <i class="bi bi-shield-check"></i>
                        </div>
                        <h3>ความปลอดภัย</h3>
                    </div>
                    <div class="info-card-content">
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px;">
                            <div>
                                <strong>สถานะระบบ:</strong><br>
                                <span style="color: var(--mobile-primary);">
                                    <i class="bi bi-check-circle-fill"></i> ปลอดภัย
                                </span>
                            </div>
                            <div>
                                <strong>เซสชั่น:</strong><br>
                                <span style="color: var(--mobile-secondary);">
                                    <i class="bi bi-clock-fill"></i> ใช้งานได้
                                </span>
                            </div>
                        </div>
                        <strong>คำแนะนำ:</strong> 
                        เก็บรักษาข้อมูลการเข้าสู่ระบบอย่างปลอดภัย และออกจากระบบเมื่อเสร็จสิ้นการใช้งาน
                    </div>
                </div>
            </div>
            
            <!-- Bottom Safe Area -->
            <div class="bottom-safe-area"></div>
        </div>
    </div>

    <!-- Floating Action Button -->
    <button class="fab" onclick="scrollToTop()">
        <i class="bi bi-arrow-up"></i>
    </button>
</div>

<footer>
    <div class="desktop-only" style="padding: 0 20px;">
        <small>© 2025 Web Portal - ระบบจัดการการประเมินผลการปฏิบัติงาน</small>
    </div>
    <div class="mobile-only" style="padding: 0 20px;">
        <small>© 2025 Web Portal - Mobile App Interface</small>
    </div>
</footer>

<!-- Bootstrap JS Bundle -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Enhanced functionality for both Desktop and Mobile
document.addEventListener('DOMContentLoaded', function() {
    // Check if mobile or desktop
    const isMobile = window.innerWidth <= 767;
    
    // Common functions for both layouts
    window.closeSidebar = function() {
        const sidebar = document.querySelector('.offcanvas.show');
        if(sidebar) {
            const offcanvas = bootstrap.Offcanvas.getInstance(sidebar);
            if (offcanvas) {
                offcanvas.hide();
            }
        }
    }
    
    // Mobile-specific functionality
    if (isMobile) {
        // Update current time in status bar
        function updateStatusBarTime() {
            const now = new Date();
            const timeString = now.toLocaleTimeString('th-TH', {
                hour: '2-digit',
                minute: '2-digit',
                hour12: false
            });
            
            const timeElement = document.getElementById('currentTime');
            if (timeElement) {
                timeElement.textContent = timeString;
            }
        }
        
        // Update time every minute
        updateStatusBarTime();
        setInterval(updateStatusBarTime, 60000);
        
        // Scroll to top function
        window.scrollToTop = function() {
            window.scrollTo({
                top: 0,
                behavior: 'smooth'
            });
        }
        
        // FAB visibility based on scroll
        let lastScrollTop = 0;
        const fab = document.querySelector('.fab');
        
        window.addEventListener('scroll', function() {
            const scrollTop = window.pageYOffset || document.documentElement.scrollTop;
            
            if (fab) {
                if (scrollTop > 200) {
                    fab.style.display = 'flex';
                    if (scrollTop > lastScrollTop) {
                        // Scrolling down
                        fab.style.transform = 'scale(0.8)';
                    } else {
                        // Scrolling up
                        fab.style.transform = 'scale(1)';
                    }
                } else {
                    fab.style.display = 'none';
                }
            }
            
            lastScrollTop = scrollTop;
        });
        
        // Loading state for evaluation buttons
        const evalButtons = document.querySelectorAll('.btn-primary');
        evalButtons.forEach(button => {
            if (!button.disabled) {
                button.addEventListener('click', function(e) {
                    const originalHTML = this.innerHTML;
                    
                    // Show loading state
                    this.innerHTML = '<i class="bi bi-hourglass-split"></i> กำลังโหลด...';
                    this.disabled = true;
                    this.classList.add('loading');
                    
                    // Simulate loading
                    setTimeout(() => {
                        this.classList.remove('loading');
                    }, 500);
                });
            }
        });
        
        // Progressive loading animation for cards
        const cards = document.querySelectorAll('.stat-card, .task-item, .info-card');
        const observerOptions = {
            threshold: 0.1,
            rootMargin: '0px 0px -50px 0px'
        };
        
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.style.opacity = '1';
                    entry.target.style.transform = 'translateY(0)';
                }
            });
        }, observerOptions);
        
        cards.forEach(card => {
            card.style.opacity = '0';
            card.style.transform = 'translateY(20px)';
            card.style.transition = 'all 0.6s cubic-bezier(0.4, 0, 0.2, 1)';
            observer.observe(card);
        });
        
        // Touch feedback for buttons
        const buttons = document.querySelectorAll('.btn-mobile, .stat-card, .task-item, .info-card');
        buttons.forEach(button => {
            button.addEventListener('touchstart', function() {
                this.style.transform = 'scale(0.95)';
            });
            
            button.addEventListener('touchend', function() {
                setTimeout(() => {
                    this.style.transform = '';
                }, 100);
            });
        });
        
        // Haptic feedback simulation
        function vibrate(pattern = [10]) {
            if ('vibrate' in navigator) {
                navigator.vibrate(pattern);
            }
        }
        
        // Add vibration to important actions
        const importantButtons = document.querySelectorAll('.btn-primary, .fab');
        importantButtons.forEach(button => {
            button.addEventListener('click', () => vibrate([10]));
        });
        
        // Pull-to-refresh simulation
        let startY = 0;
        let isPulling = false;
        const pullThreshold = 100;
        
        document.addEventListener('touchstart', function(e) {
            if (window.scrollY === 0) {
                startY = e.touches[0].clientY;
                isPulling = false;
            }
        });
        
        document.addEventListener('touchmove', function(e) {
            if (window.scrollY === 0 && startY > 0) {
                const currentY = e.touches[0].clientY;
                const pullDistance = currentY - startY;
                
                if (pullDistance > 0 && pullDistance < pullThreshold) {
                    isPulling = true;
                }
            }
        });
        
        document.addEventListener('touchend', function(e) {
            if (isPulling && window.scrollY === 0) {
                const currentY = e.changedTouches[0].clientY;
                const pullDistance = currentY - startY;
                
                if (pullDistance >= pullThreshold) {
                    // Refresh action
                    vibrate([10, 50, 10]);
                    
                    // Show refresh indicator
                    const header = document.querySelector('.app-header');
                    if (header) {
                        const refreshIndicator = document.createElement('div');
                        refreshIndicator.style.cssText = `
                            position: absolute;
                            top: 10px;
                            left: 50%;
                            transform: translateX(-50%);
                            background: rgba(255, 255, 255, 0.2);
                            color: white;
                            padding: 8px 16px;
                            border-radius: 20px;
                            font-size: 14px;
                            backdrop-filter: blur(10px);
                            z-index: 1000;
                        `;
                        refreshIndicator.innerHTML = '<i class="bi bi-arrow-clockwise"></i> กำลังอัพเดท...';
                        header.appendChild(refreshIndicator);
                        
                        // Simulate refresh
                        setTimeout(() => {
                            refreshIndicator.remove();
                            location.reload();
                        }, 1500);
                    }
                }
            }
            
            startY = 0;
            isPulling = false;
        });
        
        // Battery status simulation
        if ('getBattery' in navigator) {
            navigator.getBattery().then(function(battery) {
                const batteryFill = document.querySelector('.battery-fill');
                if (batteryFill) {
                    const level = Math.round(battery.level * 100);
                    batteryFill.style.width = level + '%';
                    
                    // Color coding
                    if (level > 50) {
                        batteryFill.style.background = 'white';
                    } else if (level > 20) {
                        batteryFill.style.background = '#f39c12';
                    } else {
                        batteryFill.style.background = '#e74c3c';
                    }
                }
            });
        }
        
        // Network status indicator
        function updateNetworkStatus() {
            const isOnline = navigator.onLine;
            const indicators = document.querySelector('.indicators');
            
            if (!isOnline && indicators) {
                const offlineIndicator = document.createElement('i');
                offlineIndicator.className = 'bi bi-wifi-off';
                offlineIndicator.style.color = '#e74c3c';
                offlineIndicator.id = 'offline-indicator';
                
                const wifiIcon = indicators.querySelector('.bi-wifi');
                if (wifiIcon && !document.getElementById('offline-indicator')) {
                    indicators.insertBefore(offlineIndicator, wifiIcon);
                    wifiIcon.style.display = 'none';
                }
            } else {
                const offlineIndicator = document.getElementById('offline-indicator');
                const wifiIcon = indicators.querySelector('.bi-wifi');
                if (offlineIndicator) {
                    offlineIndicator.remove();
                    if (wifiIcon) wifiIcon.style.display = '';
                }
            }
        }
        
        window.addEventListener('online', updateNetworkStatus);
        window.addEventListener('offline', updateNetworkStatus);
        updateNetworkStatus();
    }
    
    // Desktop-specific functionality
    else {
        // Enhanced hover effects for desktop cards
        const desktopCards = document.querySelectorAll('.card, .desktop-task-card');
        desktopCards.forEach(card => {
            card.addEventListener('mouseenter', function() {
                this.style.transform = 'translateY(-8px)';
            });
            
            card.addEventListener('mouseleave', function() {
                this.style.transform = 'translateY(0)';
            });
        });
        
        // Desktop button loading states
        const desktopButtons = document.querySelectorAll('.btn-warning');
        desktopButtons.forEach(button => {
            if (!button.disabled) {
                button.addEventListener('click', function(e) {
                    const originalHTML = this.innerHTML;
                    
                    // Show loading state
                    this.innerHTML = '<i class="bi bi-hourglass-split"></i> กำลังโหลด...';
                    this.disabled = true;
                    
                    // Simulate loading
                    setTimeout(() => {
                        this.innerHTML = originalHTML;
                        this.disabled = false;
                    }, 1000);
                });
            }
        });
        
        // Smooth animations for desktop
        const fadeElements = document.querySelectorAll('.welcome-header, .card');
        fadeElements.forEach((element, index) => {
            element.style.opacity = '0';
            element.style.transform = 'translateY(30px)';
            element.style.transition = 'all 0.6s ease';
            
            setTimeout(() => {
                element.style.opacity = '1';
                element.style.transform = 'translateY(0)';
            }, index * 200);
        });
    }
    
    // Common functionality for both desktop and mobile
    
    // Theme detection and adaptation
    const prefersDark = window.matchMedia('(prefers-color-scheme: dark)');
    
    function handleThemeChange(e) {
        if (e.matches) {
            document.body.classList.add('dark-theme');
        } else {
            document.body.classList.remove('dark-theme');
        }
    }
    
    prefersDark.addListener(handleThemeChange);
    handleThemeChange(prefersDark);
    
    // Smooth scroll for internal navigation
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function (e) {
            e.preventDefault();
            const target = document.querySelector(this.getAttribute('href'));
            if (target) {
                target.scrollIntoView({
                    behavior: 'smooth',
                    block: 'start'
                });
            }
        });
    });
    
    // Performance monitoring
    if ('performance' in window) {
        window.addEventListener('load', function() {
            setTimeout(() => {
                const perfData = performance.getEntriesByType('navigation')[0];
                if (perfData && perfData.loadEventEnd - perfData.navigationStart > 3000) {
                    console.log('Slow loading detected, consider optimization');
                }
            }, 0);
        });
    }
    
    // Memory usage monitoring (if available)
    if ('memory' in performance) {
        setInterval(() => {
            const memInfo = performance.memory;
            if (memInfo.usedJSHeapSize > memInfo.jsHeapSizeLimit * 0.9) {
                console.warn('High memory usage detected');
            }
        }, 30000);
    }
    
    // Service Worker registration for PWA-like experience
    if ('serviceWorker' in navigator && isMobile) {
        window.addEventListener('load', function() {
            console.log('PWA features ready for mobile');
        });
    }
});

// Global utility functions
window.mobileUtils = {
    showToast: function(message, type = 'info') {
        // Only show toasts on mobile
        if (window.innerWidth <= 767) {
            const toast = document.createElement('div');
            toast.style.cssText = `
                position: fixed;
                top: 60px;
                left: 50%;
                transform: translateX(-50%);
                background: ${type === 'success' ? 'var(--success-color)' : type === 'error' ? 'var(--danger-color)' : 'var(--info-color)'};
                color: white;
                padding: 12px 20px;
                border-radius: 25px;
                font-size: 14px;
                font-weight: 500;
                z-index: 10000;
                max-width: calc(100% - 40px);
                text-align: center;
                box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
                backdrop-filter: blur(10px);
                animation: slideDown 0.3s ease, slideUp 0.3s ease 2.7s forwards;
            `;
            toast.textContent = message;
            document.body.appendChild(toast);
            
            setTimeout(() => {
                if (toast.parentNode) {
                    toast.remove();
                }
            }, 3000);
        }
    },
    
    hapticFeedback: function(type = 'light') {
        const patterns = {
            light: [10],
            medium: [20],
            heavy: [30],
            success: [10, 50, 10],
            error: [50, 100, 50]
        };
        
        if ('vibrate' in navigator && window.innerWidth <= 767) {
            navigator.vibrate(patterns[type] || patterns.light);
        }
    }
};

// Add CSS for toast animations
const style = document.createElement('style');
style.textContent = `
    @keyframes slideDown {
        from {
            opacity: 0;
            transform: translateX(-50%) translateY(-20px);
        }
        to {
            opacity: 1;
            transform: translateX(-50%) translateY(0);
        }
    }
    
    @keyframes slideUp {
        from {
            opacity: 1;
            transform: translateX(-50%) translateY(0);
        }
        to {
            opacity: 0;
            transform: translateX(-50%) translateY(-20px);
        }
    }
`;
document.head.appendChild(style);

// Handle window resize to switch between layouts
window.addEventListener('resize', function() {
    // Refresh page if switching between mobile and desktop
    const currentIsMobile = window.innerWidth <= 767;
    const wasInitializedForMobile = document.querySelector('.mobile-only') !== null;
    
    if ((currentIsMobile && !wasInitializedForMobile) || (!currentIsMobile && wasInitializedForMobile)) {
        // Don't auto-refresh, just log the change
        console.log('Layout change detected:', currentIsMobile ? 'Mobile' : 'Desktop');
    }
});
</script>
</body>
</html>