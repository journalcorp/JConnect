<?php
session_start();
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: 0"); // ป้องกันการแคช
include '../includes/header.php';
include '../includes/menu.php';
include '../config.php';

// ตรวจสอบว่ามีการส่งค่า emp_as และ emp_pe มาหรือไม่
if (!isset($_GET['emp_as']) || empty($_GET['emp_as']) || !isset($_GET['emp_pe']) || empty($_GET['emp_pe'])) {
    die("ไม่พบข้อมูลผู้ประเมินหรือผู้ถูกประเมิน");
}

// รับค่า emp_as และ emp_pe จาก URL
$emp_as = $_GET['emp_as'];
$emp_pe = $_GET['emp_pe'];

// เชื่อมต่อฐานข้อมูล
$conn = dbConnect();
if (!$conn) {
    $errors = sqlsrv_errors();
    die("ไม่สามารถเชื่อมต่อฐานข้อมูลได้: " . print_r($errors, true));
}

// ดึงข้อมูลผู้ประเมิน (emp_as)
$sql = "SELECT CONCAT(e.first_name_th, ' ', e.last_name_th) AS FullName, 
               e.position_th, 
               d.DeptName
        FROM employees e
        LEFT JOIN dept d ON e.division = d.DeptID
        WHERE e.employee_id = ?";
$stmt = sqlsrv_query($conn, $sql, array($emp_as));

if ($stmt === false) {
    $errors = sqlsrv_errors();
    die("Query failed: " . print_r($errors, true));
}

if (!sqlsrv_has_rows($stmt)) {
    die("ไม่พบข้อมูลผู้ประเมินในฐานข้อมูล");
}

$assessor = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
sqlsrv_free_stmt($stmt);
// ดึงข้อมูลผู้ถูกประเมิน (emp_pe)
$sqlEmpPe = "SELECT CONCAT(e.first_name_th, ' ', e.last_name_th) AS FullName, 
                    e.position_th, 
                    d.DeptName
             FROM employees e
             LEFT JOIN dept d ON e.division = d.DeptID
             WHERE e.employee_id = ?";

$stmtEmpPe = sqlsrv_query($conn, $sqlEmpPe, array($emp_pe));

if ($stmtEmpPe === false) {
    $errors = sqlsrv_errors();
    die("Query failed: " . print_r($errors, true));
}

if (!sqlsrv_has_rows($stmtEmpPe)) {
    die("ไม่พบข้อมูลผู้ถูกประเมินในฐานข้อมูล");
}

$employee = sqlsrv_fetch_array($stmtEmpPe, SQLSRV_FETCH_ASSOC);
sqlsrv_free_stmt($stmtEmpPe);

// ดึงข้อมูล FormID
$sqlFormID = "SELECT TOP 1 FormID FROM form_score WHERE emp_as = ?";
$stmtFormID = sqlsrv_query($conn, $sqlFormID, array($emp_as));

if ($stmtFormID === false) {
    $errors = sqlsrv_errors();
    die("Query failed: " . print_r($errors, true));
}

$form = sqlsrv_fetch_array($stmtFormID, SQLSRV_FETCH_ASSOC);
$FormID = $form ? $form['FormID'] : null;
sqlsrv_free_stmt($stmtFormID);

// รับค่า TransactionID จาก URL
$TransactionID = $_GET['TransactionID'] ?? null;

if (!$TransactionID) {
    die("ไม่พบข้อมูล TransactionID");
}

// ตรวจสอบสถานะของแบบฟอร์ม
$sqlCheckStatus = "SELECT status_form FROM form_score WHERE TransactionID = ?";
$stmtCheckStatus = sqlsrv_query($conn, $sqlCheckStatus, array($TransactionID));

if ($stmtCheckStatus === false) {
    $errors = sqlsrv_errors();
    die("Query failed: " . print_r($errors, true));
}

$rowCheckStatus = sqlsrv_fetch_array($stmtCheckStatus, SQLSRV_FETCH_ASSOC);

if ($rowCheckStatus && $rowCheckStatus['status_form'] === 'enable') {
    header("Location: all_report_emp.php");
    exit;
}

sqlsrv_free_stmt($stmtCheckStatus);
sqlsrv_close($conn);
?>



<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>แบบฟอร์มประเมินค่านิยมองค์กร</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    
    <style>
        * {
            box-sizing: border-box;
        }

        body {
            font-family: 'Prompt', sans-serif;
            background: linear-gradient(135deg, 
                #ffffff 0%, #f8f9fa 25%, #e9ecef 50%, 
                #dee2e6 75%, #ced4da 100%);
            margin: 0;
            padding: 20px 0;
            color: #212529;
            min-height: 100vh;
            position: relative;
            overflow-x: hidden;
            display: flex;
            flex-direction: column;
        }

        /* Main content should expand to fill available space */
        .container {
            flex: 1;
        }

        /* Footer should stick to bottom */
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

        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: 
                radial-gradient(circle at 20% 80%, rgba(0, 0, 0, 0.02) 0%, transparent 50%),
                radial-gradient(circle at 80% 20%, rgba(0, 0, 0, 0.03) 0%, transparent 50%),
                url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="grid" width="8" height="8" patternUnits="userSpaceOnUse"><path d="M 8 0 L 0 0 L 0 8" fill="none" stroke="rgba(0,0,0,0.05)" stroke-width="0.5"/></pattern></defs><rect width="100" height="100" fill="url(%23grid)"/></svg>');
            pointer-events: none;
            z-index: 0;
        }

        @keyframes gradientShift {
            0% { background-position: 0% 50%; }
            25% { background-position: 100% 50%; }
            50% { background-position: 100% 100%; }
            75% { background-position: 0% 100%; }
            100% { background-position: 0% 50%; }
        }

        @keyframes shimmerBackground {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.8; }
        }

        @keyframes colorPulse {
            0%, 100% { 
                filter: hue-rotate(0deg) brightness(1);
            }
            50% { 
                filter: hue-rotate(60deg) brightness(1.1);
            }
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

        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.05); }
        }

        @keyframes shimmer {
            0% { transform: translateX(-100%) translateY(-100%) rotate(45deg); }
            100% { transform: translateX(100%) translateY(100%) rotate(45deg); }
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 15px;
            position: relative;
            z-index: 1;
        }

        .main-card {
            background: linear-gradient(145deg, 
                rgba(255, 255, 255, 0.98) 0%, 
                rgba(248, 249, 250, 0.95) 50%,
                rgba(241, 243, 244, 0.92) 100%);
            backdrop-filter: blur(25px);
            border-radius: 24px;
            box-shadow: 
                0 25px 50px rgba(0, 0, 0, 0.1),
                0 0 0 1px rgba(0, 0, 0, 0.05),
                inset 0 1px 0 rgba(255, 255, 255, 0.8),
                0 10px 25px rgba(0, 0, 0, 0.05);
            overflow: hidden;
            position: relative;
            animation: fadeInUp 1s ease 0.2s both;
            margin-bottom: 30px;
        }

        .main-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(135deg, 
                rgba(248, 249, 250, 0.3) 0%, 
                rgba(233, 236, 239, 0.2) 50%, 
                rgba(222, 226, 230, 0.1) 100%);
            pointer-events: none;
            z-index: 0;
        }

        .card-header {
            background: linear-gradient(135deg, 
                #343a40 0%, 
                #495057 50%, 
                #6c757d 100%);
            color: white;
            padding: 40px;
            text-align: center;
            position: relative;
            overflow: hidden;
            border-bottom: 3px solid rgba(108, 117, 125, 0.3);
        }

        .card-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, 
                transparent, 
                rgba(255, 255, 255, 0.1), 
                transparent);
            animation: shimmer 3s ease infinite;
        }
        }

        .card-header::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: linear-gradient(45deg, transparent, rgba(255,255,255,0.15), transparent);
            animation: shimmer 3s infinite;
        }

        .card-header h1 {
            font-size: 2.2rem;
            font-weight: 800;
            margin: 0;
            text-shadow: 0 4px 8px rgba(0, 0, 0, 0.3);
            position: relative;
            z-index: 2;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 15px;
        }

        .card-header h1 i {
            font-size: 2.5rem;
            filter: drop-shadow(0 2px 4px rgba(0,0,0,0.3));
            animation: pulse 2s infinite;
        }

        .card-body {
            padding: 40px;
            position: relative;
        }

        /* Date Section */
        .date-section {
            background: linear-gradient(135deg, 
                #f8f9fa 0%, 
                #e9ecef 100%);
            border: 2px solid #6c757d;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 35px;
            text-align: center;
            position: relative;
            overflow: hidden;
            animation: fadeInUp 0.8s ease 0.2s both;
        }

        .date-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, #6c757d, #495057, #343a40);
        }

        .date-section h5 {
            color: #495057;
            font-weight: 700;
            margin: 0;
            font-size: 1.3rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        .date-section h5 i {
            font-size: 1.5rem;
            animation: pulse 2s infinite;
        }

        /* Info Sections */
        .info-section {
            background: linear-gradient(145deg, 
                rgba(255, 255, 255, 0.95) 0%, 
                rgba(248, 249, 250, 0.9) 100%);
            border: 1px solid rgba(108, 117, 125, 0.2);
            border-radius: 16px;
            padding: 30px;
            margin-bottom: 25px;
            box-shadow: 
                0 8px 25px rgba(0, 0, 0, 0.08),
                inset 0 1px 0 rgba(255, 255, 255, 0.8);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
            animation: fadeInUp 0.8s ease 0.4s both;
        }

        .info-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, 
                #6c757d 0%, 
                #495057 50%, 
                #343a40 100%);
            border-radius: 16px 16px 0 0;
        }

        .info-section:hover {
            transform: translateY(-5px);
            box-shadow: 
                0 15px 35px rgba(0, 0, 0, 0.12),
                inset 0 1px 0 rgba(255, 255, 255, 0.8);
            border-color: rgba(108, 117, 125, 0.3);
        }
                inset 0 1px 0 rgba(255, 255, 255, 0.6);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
            animation: fadeInUp 0.8s ease 0.4s both;
        }

        .info-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 5px;
            background: linear-gradient(90deg, #667eea, #764ba2, #f093fb);
            border-radius: 20px 20px 0 0;
        }

        .info-section:hover {
            transform: translateY(-8px);
            box-shadow: 
                0 20px 40px rgba(0, 0, 0, 0.15),
                inset 0 1px 0 rgba(255, 255, 255, 0.6);
            border-color: rgba(102, 126, 234, 0.4);
        }

        .info-section h4 {
            color: #343a40;
            font-weight: 700;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 2px solid #e9ecef;
            display: flex;
            align-items: center;
            font-size: 1.4rem;
            position: relative;
        }

        .info-section h4::after {
            content: '';
            position: absolute;
            bottom: -2px;
            left: 0;
            width: 60px;
            height: 2px;
            background: linear-gradient(90deg, #6c757d, #495057);
            border-radius: 1px;
        }

        .info-section h4 i {
            margin-right: 15px;
            color: #667eea;
            font-size: 1.6rem;
            padding: 8px;
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.1), rgba(118, 75, 162, 0.1));
            border-radius: 50%;
            animation: pulse 2s infinite;
        }

        .info-row {
            display: flex;
            margin-bottom: 18px;
            align-items: center;
            padding: 12px 0;
            border-bottom: 1px solid rgba(236, 240, 241, 0.5);
            transition: all 0.2s ease;
        }

        .info-row:hover {
            background: rgba(108, 117, 125, 0.05);
            border-radius: 8px;
            padding: 12px 15px;
            margin: 0 -15px 18px -15px;
        }

        .info-row:last-child {
            border-bottom: none;
        }

        .info-label {
            font-weight: 700;
            color: #6c757d;
            min-width: 140px;
            margin-right: 20px;
            font-size: 1rem;
            position: relative;
        }

        .info-label::before {
            content: '●';
            color: #495057;
            margin-right: 8px;
            font-size: 0.8rem;
        }

        .info-value {
            color: #343a40;
            flex: 1;
            font-weight: 500;
            font-size: 1rem;
        }

        .badge {
            background: linear-gradient(135deg, #6c757d, #495057);
            color: white;
            padding: 8px 16px;
            border-radius: 25px;
            font-size: 0.95rem;
            font-weight: 600;
            box-shadow: 0 4px 15px rgba(108, 117, 125, 0.3);
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: all 0.3s ease;
        }

        .badge:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.4);
        }

        .badge::before {
            content: '👤';
            font-size: 0.9rem;
        }

        /* Evaluation Section */
        .evaluation-section {
            background: linear-gradient(135deg, rgba(248, 249, 250, 0.8) 0%, rgba(233, 236, 239, 0.9) 100%);
            border: 2px solid rgba(102, 126, 234, 0.2);
            border-radius: 25px;
            padding: 35px;
            margin-bottom: 35px;
            position: relative;
            overflow: hidden;
            animation: fadeInUp 0.8s ease 0.6s both;
        }

        .evaluation-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 6px;
            background: linear-gradient(90deg, #667eea, #764ba2, #f093fb, #f5576c, #4facfe);
            background-size: 400% 400%;
            animation: gradientShift 8s ease infinite;
        }

        .evaluation-section h3 {
            color: #2c3e50;
            font-weight: 800;
            text-align: center;
            margin-bottom: 40px;
            font-size: 1.8rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 15px;
            position: relative;
        }

        .evaluation-section h3 i {
            font-size: 2rem;
            color: #f39c12;
            animation: pulse 2s infinite;
            filter: drop-shadow(0 2px 4px rgba(243, 156, 18, 0.3));
        }

        .evaluation-section h3::after {
            content: '';
            position: absolute;
            bottom: -10px;
            left: 50%;
            transform: translateX(-50%);
            width: 100px;
            height: 4px;
            background: linear-gradient(90deg, #6c757d, #495057);
            border-radius: 2px;
        }

        .rating-item {
            background: linear-gradient(145deg, 
                rgba(255, 255, 255, 0.95) 0%, 
                rgba(248, 249, 250, 0.98) 100%);
            border: 2px solid rgba(108, 117, 125, 0.15);
            border-radius: 16px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 
                0 8px 25px rgba(0, 0, 0, 0.08),
                inset 0 1px 0 rgba(255, 255, 255, 0.8);
            transition: all 0.4s ease;
            position: relative;
            overflow: hidden;
        }

        .rating-item::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--category-color), rgba(255,255,255,0.3));
            transition: all 0.3s ease;
        }

        .rating-item:nth-child(1) { --category-color: #6c757d; }
        .rating-item:nth-child(2) { --category-color: #495057; }
        .rating-item:nth-child(3) { --category-color: #343a40; }
        .rating-item:nth-child(4) { --category-color: #6c757d; }
        .rating-item:nth-child(5) { --category-color: #495057; }
        .rating-item:nth-child(6) { --category-color: #343a40; }
        .rating-item:nth-child(7) { --category-color: #6c757d; }

        .rating-item:hover {
            transform: translateY(-8px) scale(1.02);
            box-shadow: 
                0 15px 35px rgba(0, 0, 0, 0.12),
                inset 0 1px 0 rgba(255, 255, 255, 0.8);
            border-color: rgba(102, 126, 234, 0.3);
        }

        .rating-item:hover::before {
            height: 6px;
            background: linear-gradient(90deg, var(--category-color), var(--category-color));
        }

        .rating-item h5 {
            color: #2c3e50;
            font-weight: 800;
            margin-bottom: 25px;
            font-size: 1.5rem;
            display: flex;
            align-items: center;
            position: relative;
        }

        .rating-item .category-letter {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, var(--category-color), rgba(255,255,255,0.2));
            color: white;
            width: 50px;
            height: 50px;
            border-radius: 50%;
            font-weight: 900;
            margin-right: 20px;
            font-size: 1.2rem;
            box-shadow: 
                0 8px 20px rgba(0,0,0,0.15),
                inset 0 1px 0 rgba(255,255,255,0.3);
            transition: all 0.3s ease;
            position: relative;
            overflow: visible;
            z-index: 2;
            flex-direction: column;
            gap: 2px;
        }

        /* เพิ่ม CSS เฉพาะสำหรับแต่ละ category */
        .rating-item:nth-child(1) .category-letter .category-text { content: "J"; }
        .rating-item:nth-child(2) .category-letter .category-text { content: "O"; }
        .rating-item:nth-child(3) .category-letter .category-text { content: "U"; }
        .rating-item:nth-child(4) .category-letter .category-text { content: "R"; }
        .rating-item:nth-child(5) .category-letter .category-text { content: "N"; }
        .rating-item:nth-child(6) .category-letter .category-text { content: "A"; }
        .rating-item:nth-child(7) .category-letter .category-text { content: "L"; }
        
        /* เพิ่ม CSS เฉพาะสำหรับ Lifelong Learning (L) */
        .rating-item:nth-child(7) .category-letter .category-text {
            font-family: 'Arial', 'Helvetica', sans-serif !important;
            font-size: 1rem !important;
            font-weight: 900 !important;
            text-transform: uppercase !important;
            color: white !important;
            text-shadow: 0 2px 4px rgba(0,0,0,0.4) !important;
            letter-spacing: 0.5px !important;
        }

        .rating-item .category-letter .category-icon {
            font-size: 1rem;
            line-height: 1;
            margin-bottom: -2px;
        }

        .rating-item .category-letter .category-text {
            font-size: 0.9rem !important;
            font-weight: 900 !important;
            line-height: 1 !important;
            color: white !important;
            text-shadow: 0 1px 2px rgba(0,0,0,0.3) !important;
            letter-spacing: 0.5px !important;
            display: inline-block !important;
            min-width: 12px !important;
            text-align: center !important;
            text-transform: uppercase !important;
            visibility: visible !important;
            opacity: 1 !important;
        }

        .rating-item .category-letter::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: linear-gradient(45deg, transparent, rgba(255,255,255,0.2), transparent);
            animation: shimmer 3s infinite;
            z-index: -1;
        }

        .rating-item:hover .category-letter {
            transform: scale(1.1);
            box-shadow: 
                0 12px 25px rgba(0,0,0,0.2),
                inset 0 1px 0 rgba(255,255,255,0.4);
        }

        .rating-scale {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin: 25px 0;
            background: linear-gradient(135deg, 
                rgba(248, 249, 250, 0.8) 0%, 
                rgba(233, 236, 239, 0.9) 100%);
            padding: 25px;
            border-radius: 15px;
            border: 2px solid rgba(108, 117, 125, 0.1);
            position: relative;
            overflow: hidden;
        }

        .rating-scale::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, #6c757d, #495057, #343a40, #212529, #000000);
        }

        .rating-scale .scale-label {
            color: #495057;
            font-weight: 700;
            font-size: 1rem;
            padding: 8px 16px;
            background: rgba(255, 255, 255, 0.8);
            border-radius: 20px;
            border: 2px solid rgba(108, 117, 125, 0.2);
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
        }

        .rating-scale .scale-label:hover {
            transform: scale(1.05);
            box-shadow: 0 6px 15px rgba(0, 0, 0, 0.15);
        }

        .rating-options {
            display: flex;
            gap: 20px;
            justify-content: center;
            flex: 1;
            margin: 0 25px;
        }

        .rating-option {
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
            min-width: 70px;
            cursor: pointer;
            transition: all 0.3s ease;
            padding: 10px;
            border-radius: 15px;
            position: relative;
        }

        .rating-option::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.1), rgba(118, 75, 162, 0.1));
            border-radius: 15px;
            opacity: 0;
            transition: all 0.3s ease;
        }

        .rating-option:hover::before {
            opacity: 1;
        }

        .rating-option:hover {
            transform: translateY(-5px) scale(1.1);
        }

        .rating-option input[type="radio"] {
            display: none;
        }

        .rating-option .star-icon {
            width: 32px;
            height: 32px;
            margin-bottom: 10px;
            cursor: pointer;
            font-size: 28px;
            color: #ddd;
            transition: all 0.3s ease;
            position: relative;
            z-index: 1;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .rating-option input[type="radio"]:checked + .star-icon {
            color: #ffc107;
            text-shadow: 0 0 10px rgba(255, 193, 7, 0.5);
            transform: scale(1.1);
        }

        .rating-option .star-icon:hover {
            color: #ffc107;
            transform: scale(1.2);
            text-shadow: 0 0 15px rgba(255, 193, 7, 0.7);
        }

        .rating-option:hover .star-icon {
            animation: starPulse 0.5s ease;
        }

        @keyframes starPulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.3); }
            100% { transform: scale(1.1); }
        }

        /* เพิ่ม effect สำหรับดาวที่ถูกเลือก */
        .rating-option input[type="radio"]:checked + .star-icon {
            animation: starGlow 2s ease infinite;
        }

        @keyframes starGlow {
            0%, 100% { 
                color: #ffc107;
                text-shadow: 0 0 10px rgba(255, 193, 7, 0.5);
            }
            50% { 
                color: #ffed4e;
                text-shadow: 0 0 20px rgba(255, 193, 7, 0.8);
            }
        }

        .rating-option label {
            font-size: 1.1rem;
            color: #495057;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s ease;
            z-index: 1;
            position: relative;
        }

        .rating-option:hover label {
            color: #343a40;
            transform: scale(1.1);
        }

        .reason-section {
            margin-top: 25px;
            animation: fadeInUp 0.5s ease;
        }

        .reason-section label {
            font-weight: 700;
            color: #555;
            margin-bottom: 12px;
            display: block;
            font-size: 1.1rem;
            position: relative;
            padding-left: 20px;
        }

        .reason-section label::before {
           
            position: absolute;
            left: 0;
            top: 0;
            font-size: 1rem;
        }

        .reason-section textarea {
            width: 100%;
            border: 3px solid #6c757d;
            border-radius: 15px;
            padding: 18px;
            font-family: 'Prompt', sans-serif;
            font-size: 1rem;
            transition: all 0.3s ease;
            resize: vertical;
            min-height: 120px;
            background: linear-gradient(135deg, rgba(255,255,255,0.9) 0%, rgba(248,250,252,0.95) 100%);
            box-shadow: inset 0 2px 4px rgba(0,0,0,0.06);
        }

        .reason-section textarea:focus {
            outline: none;
            box-shadow: 
                0 0 0 4px rgba(108, 117, 125, 0.2),
                inset 0 2px 4px rgba(0,0,0,0.06);
            transform: scale(1.02);
        }

        .reason-section textarea[required] {
            border-color: #dc3545;
        }

        .reason-section textarea[required]:focus {
            box-shadow: 
                0 0 0 4px rgba(220, 53, 69, 0.2),
                inset 0 2px 4px rgba(0,0,0,0.06);
        }

        .reason-section textarea:disabled {
            background: linear-gradient(135deg, rgba(248, 249, 250, 0.8) 0%, rgba(233, 236, 239, 0.9) 100%);
            cursor: not-allowed;
            border-color: #ddd;
        }

        .reason-section textarea::placeholder {
            color: #999;
            font-style: italic;
        }

        /* สีเขียวเมื่อกรอกครบ 10 ตัวอักษร */
        .reason-section textarea.valid-length {
            border-color: #28a745 !important;
            background: linear-gradient(135deg, rgba(40, 167, 69, 0.05) 0%, rgba(255,255,255,0.95) 100%);
            box-shadow: 
                0 0 0 4px rgba(40, 167, 69, 0.2),
                inset 0 2px 4px rgba(0,0,0,0.06);
        }

        .reason-section textarea.valid-length:focus {
            box-shadow: 
                0 0 0 4px rgba(40, 167, 69, 0.3),
                inset 0 2px 4px rgba(0,0,0,0.06);
            transform: scale(1.02);
        }

        /* Submit Section */
        .submit-section {
            display: flex;
            gap: 20px;
            justify-content: center;
            padding: 40px;
            background: linear-gradient(135deg, rgba(248, 249, 250, 0.8) 0%, rgba(233, 236, 239, 0.9) 100%);
            border-radius: 25px;
            margin-top: 40px;
            position: relative;
            overflow: hidden;
            animation: fadeInUp 0.8s ease 0.8s both;
        }

        .submit-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 5px;
            background: linear-gradient(90deg, #6c757d, #495057, #343a40);
            background-size: 400% 400%;
            animation: gradientShift 5s ease infinite;
        }

        .btn-submit {
            background: linear-gradient(135deg, #6c757d, #495057, #343a40);
            border: none;
            color: white;
            padding: 18px 45px;
            font-size: 1.2rem;
            font-weight: 700;
            border-radius: 30px;
            cursor: pointer;
            transition: all 0.4s ease;
            flex: 1;
            max-width: 250px;
            position: relative;
            overflow: hidden;
            box-shadow: 
                0 8px 25px rgba(108, 117, 125, 0.3),
                inset 0 1px 0 rgba(255, 255, 255, 0.3);
        }

        .btn-submit::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.3), transparent);
            transition: left 0.5s ease;
        }

        .btn-submit:hover::before {
            left: 100%;
        }

        .btn-submit:hover {
            transform: translateY(-5px) scale(1.05);
            box-shadow: 
                0 15px 35px rgba(108, 117, 125, 0.4),
                inset 0 1px 0 rgba(255, 255, 255, 0.4);
        }

        .btn-submit:active {
            transform: translateY(-2px) scale(1.02);
        }

        .btn-submit:disabled {
            opacity: 0.6 !important;
            cursor: not-allowed !important;
            transform: none !important;
            pointer-events: none;
        }

        .btn-submit:disabled:hover {
            transform: none !important;
            box-shadow: 0 8px 25px rgba(108, 117, 125, 0.3) !important;
        }

        .btn-cancel {
            background: linear-gradient(135deg, #6c757d, #495057, #343a40);
            border: 2px solid #495057;
            color: white;
            padding: 18px 45px;
            font-size: 1.2rem;
            font-weight: 700;
            border-radius: 30px;
            cursor: pointer;
            transition: all 0.4s ease;
            text-decoration: none;
            text-align: center;
            flex: 1;
            max-width: 250px;
            position: relative;
            overflow: hidden;
            box-shadow: 
                0 8px 25px rgba(108, 117, 125, 0.3),
                inset 0 1px 0 rgba(255, 255, 255, 0.3);
        }

        .btn-cancel::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.3), transparent);
            transition: left 0.5s ease;
        }

        .btn-cancel:hover::before {
            left: 100%;
        }

        .btn-cancel:hover {
            transform: translateY(-5px) scale(1.05);
            box-shadow: 
                0 15px 35px rgba(108, 117, 125, 0.4),
                inset 0 1px 0 rgba(255, 255, 255, 0.4);
            color: white;
            text-decoration: none;
            border-color: #343a40;
        }

        .btn-cancel:active {
            transform: translateY(-2px) scale(1.02);
        }

        .is-invalid {
            border-color: #dc3545 !important;
            box-shadow: 0 0 0 4px rgba(220, 53, 69, 0.3) !important;
            animation: shake 0.5s ease-in-out;
        }

        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-5px); }
            75% { transform: translateX(5px); }
        }

        /* Loading Animation */
        .loading {
            position: relative;
        }

        .loading::after {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 20px;
            height: 20px;
            margin: -10px 0 0 -10px;
            border: 2px solid rgba(255,255,255,0.3);
            border-top: 2px solid white;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        /* Success Animation */
        @keyframes success {
            0% { transform: scale(1); }
            50% { transform: scale(1.1); background: #28a745; }
            100% { transform: scale(1); }
        }

        /* Responsive */
        @media (max-width: 768px) {
            .card-header h1 {
                font-size: 1.6rem;
                flex-direction: column;
                gap: 10px;
            }

            .card-body {
                padding: 25px;
            }
            
            .info-row {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .info-label {
                min-width: auto;
                margin-bottom: 8px;
            }
            
            .rating-scale {
                flex-direction: column;
                gap: 20px;
                padding: 20px;
            }
            
            .rating-options {
                margin: 0;
                gap: 15px;
            }
            
            .submit-section {
                flex-direction: column;
                padding: 30px;
            }
            
            .btn-submit, .btn-cancel {
                max-width: none;
            }

            .rating-item {
                padding: 25px;
            }

            .rating-item .category-letter {
                width: 45px;
                height: 45px;
                font-size: 1rem;
                margin-right: 15px;
            }

            .rating-item .category-letter .category-icon {
                font-size: 0.8rem;
            }

            .rating-item .category-letter .category-text {
                font-size: 0.7rem !important;
                font-weight: 900 !important;
                color: white !important;
                text-shadow: 0 1px 2px rgba(0,0,0,0.3) !important;
                letter-spacing: 0.3px !important;
                display: inline-block !important;
                min-width: 8px !important;
                text-align: center !important;
                text-transform: uppercase !important;
                visibility: visible !important;
                opacity: 1 !important;
            }
        }

        @media (max-width: 480px) {
            .container {
                padding: 0 2px;
            }
            .main-card {
                margin-bottom: 15px;
                border-radius: 12px;
            }
            .card-header {
                padding: 18px 5px;
                font-size: 1.1rem;
            }
            .card-body {
                padding: 10px 2px;
            }
            .info-section, .evaluation-section {
                padding: 10px 5px;
                border-radius: 10px;
            }
            .info-section h4, .evaluation-section h3 {
                font-size: 1.1rem;
                padding-bottom: 8px;
            }
            .info-row {
                flex-direction: column;
                align-items: flex-start;
                padding: 6px 0;
                font-size: 0.95rem;
            }
            .info-label {
                min-width: unset;
                margin-bottom: 4px;
                font-size: 0.95rem;
            }
            .info-value {
                font-size: 0.95rem;
            }
            .rating-item {
                padding: 10px 5px;
                margin-bottom: 15px;
                border-radius: 10px;
            }
            .rating-item h5 {
                font-size: 1.1rem;
                margin-bottom: 10px;
            }
            .rating-item .category-letter {
                width: 32px;
                height: 32px;
                font-size: 0.9rem;
                margin-right: 10px;
            }
            .rating-scale {
                flex-direction: column;
                gap: 10px;
                padding: 10px 2px;
            }
            .rating-options {
                flex-direction: row;
                gap: 8px;
                margin: 0;
            }
            .rating-option {
                min-width: 38px;
                padding: 4px;
            }
            .rating-option .star-icon {
                width: 22px;
                height: 22px;
                font-size: 18px;
                margin-bottom: 4px;
            }
            .rating-option label {
                font-size: 0.95rem;
            }
            .reason-section {
                margin-top: 10px;
            }
            .reason-section label {
                font-size: 0.95rem;
                padding-left: 16px;
            }
            .reason-section textarea {
                min-height: 60px;
                font-size: 0.95rem;
                padding: 10px;
                border-radius: 8px;
            }
            .submit-section {
                flex-direction: column;
                gap: 10px;
                padding: 15px 2px;
                border-radius: 10px;
            }
            .btn-submit, .btn-cancel {
                max-width: none;
                width: 100%;
                font-size: 1rem;
                padding: 12px 0;
                border-radius: 20px;
            }
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="main-card">
            <div class="card-header">
                <h1><i class="bi bi-clipboard-check"></i> แบบฟอร์มประเมินค่านิยมองค์กร ประจำปี 2568</h1>
            </div>
            
            <div class="card-body">
                <!-- Date Section -->
                <div class="date-section">
                    <h5><i class="bi bi-calendar3"></i> วันที่ประเมิน: <?php echo date('d/m/Y'); ?></h5>
                </div>

                <!-- Employee Info Section -->
                <div class="info-section">
                    <h4><i class="bi bi-person-circle"></i> ข้อมูลผู้ถูกประเมิน</h4>
                    
                    <div class="info-row">
                        <span class="info-label">ชื่อ-นามสกุล:</span>
                        <span class="info-value"><?php echo htmlspecialchars($employee['FullName']); ?></span>
                    </div>
                    
                    <div class="info-row">
                        <span class="info-label">ตำแหน่ง:</span>
                        <span class="info-value"><?php echo htmlspecialchars($employee['position_th']); ?></span>
                    </div>
                    
                    <div class="info-row">
                        <span class="info-label">แผนก:</span>
                        <span class="info-value"><?php echo htmlspecialchars($employee['DeptName']); ?></span>
                    </div>
                    
                    <div class="info-row">
                        <span class="info-label">รหัสพนักงาน:</span>
                        <span class="info-value">
                            <span class="badge"><?php echo htmlspecialchars($emp_pe); ?></span>
                        </span>
                    </div>
                </div>

                <!-- Assessor Info Section -->
                <!-- <div class="info-section">
                    <h4><i class="bi bi-person-check"></i> ข้อมูลผู้ประเมิน</h4>
                    
                    <div class="info-row">
                        <span class="info-label">ชื่อ-นามสกุล:</span>
                        <span class="info-value"><?php echo htmlspecialchars($assessor['FullName']); ?></span>
                    </div>
                    
                    <div class="info-row">
                        <span class="info-label">ตำแหน่ง:</span>
                        <span class="info-value"><?php echo htmlspecialchars($assessor['position_th']); ?></span>
                    </div>
                    
                    <div class="info-row">
                        <span class="info-label">แผนก:</span>
                        <span class="info-value"><?php echo htmlspecialchars($assessor['DeptName']); ?></span>
                    </div>
                    
                    <div class="info-row">
                        <span class="info-label">รหัสพนักงาน:</span>
                        <span class="info-value">
                            <span class="badge"><?php echo htmlspecialchars($emp_as); ?></span>
                        </span>
                    </div>
                </div> -->

                <!-- Evaluation Form -->
                <form action="submit_360_form.php" method="POST" id="evaluationForm">
                    <input type="hidden" name="TransactionID" value="<?php echo htmlspecialchars($TransactionID); ?>">
                    <input type="hidden" name="FormID" value="<?php echo htmlspecialchars($FormID); ?>">
                    <input type="hidden" name="emp_as" value="<?php echo htmlspecialchars($emp_as); ?>">
                    <input type="hidden" name="emp_pe" value="<?php echo htmlspecialchars($emp_pe); ?>">

                    <div class="evaluation-section">
                        <h3><i class="bi bi-star-fill"></i> แบบประเมินค่านิยมองค์กร JOURNAL</h3>

                        <?php
                        $categories = [
                            'j_score' => ['letter' => 'J', 'name' => 'Joyfulness', 'desc' => 'ความสุข ความสนุกสนาน และความเพลิดเพลินในการทำงาน', 'icon' => '😊'],
                            'o_score' => ['letter' => 'O', 'name' => 'Openness', 'desc' => 'การเปิดใจรับฟังความคิดเห็นและแนวคิดใหม่ๆ', 'icon' => '🌟'],
                            'u_score' => ['letter' => 'U', 'name' => 'Us', 'desc' => 'การทำงานเป็นทีม และการให้ความสำคัญกับส่วนรวม', 'icon' => '🤝'],
                            'r_score' => ['letter' => 'R', 'name' => 'Respect', 'desc' => 'การเคารพในความแตกต่างและศักดิ์ศรีของผู้อื่น', 'icon' => '🙏'],
                            'n_score' => ['letter' => 'N', 'name' => 'Never Give Up', 'desc' => 'ความมุ่งมั่น ไม่ยอมแพ้ ต่อสู้กับอุปสรรค', 'icon' => '💪'],
                            'a_score' => ['letter' => 'A', 'name' => 'Accountability', 'desc' => 'ความรับผิดชอบต่อหน้าที่และผลงาน', 'icon' => '✋'],
                            'l_score' => ['letter' => 'L', 'name' => 'Lifelong Learning', 'desc' => 'การเรียนรู้และพัฒนาตนเองอย่างต่อเนื่อง', 'icon' => '📚']
                        ];

                        foreach ($categories as $key => $category): ?>
                            <div class="rating-item">
                                <h5>
                                    <span class="category-letter" title="<?php echo $category['name']; ?>">
                                        <span class="category-icon"><?php echo $category['icon']; ?></span>
                                        <span class="category-text"><?php echo $category['letter']; ?></span>
                                    </span>
                                    <?php echo $category['name']; ?>
                                </h5>
                                <p class="text-muted mb-3"><?php echo $category['desc']; ?></p>
                                
                                <div class="rating-scale">
                                    <span class="scale-label">1 ดาว</span>
                                    <div class="rating-options">
                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                            <div class="rating-option">
                                                <input type="radio" id="<?php echo $key . '_' . $i; ?>" 
                                                       name="<?php echo $key; ?>" value="<?php echo $i; ?>" 
                                                       onclick="toggleReasonInput(this, '<?php echo $key; ?>_reason')" required>
                                                <i class="bi bi-star-fill star-icon" onclick="selectRating('<?php echo $key . '_' . $i; ?>')"></i>
                                                <label for="<?php echo $key . '_' . $i; ?>"><?php echo $i; ?></label>
                                            </div>
                                        <?php endfor; ?>
                                    </div>
                                    <span class="scale-label">5 ดาว</span>
                                </div>

                                <div class="reason-section">
                                    <label for="<?php echo $key; ?>_reason">เหตุผล / ข้อเสนอแนะ <span style="color: #6c757d; font-weight: normal;">(สามารถกรอกได้เสมอ แต่จำเป็นสำหรับคะแนน 1, 2 และ 5)</span></label>
                                    <textarea id="<?php echo $key; ?>_reason" name="<?php echo $key; ?>_reason" 
                                              placeholder="กรุณาอธิบายเหตุผลในเชิงสร้างสรรค์... (กรุณาเลือกคะแนนก่อน)"></textarea>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="submit-section">
                        <button type="button" class="btn-submit" onclick="validateForm(event)">
                            <i class="bi bi-check-circle"></i> บันทึกแบบฟอร์ม
                        </button>
                        <a href="all_report_emp.php" class="btn-cancel">
                            <i class="bi bi-x-circle"></i> ยกเลิก
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>



    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // ฟังก์ชันสำหรับการเลือกดาว
        function selectRating(radioId) {
            const radio = document.getElementById(radioId);
            radio.checked = true;
            
            // เรียกฟังก์ชัน toggle reason input
            const reasonId = radioId.replace(/_\d+$/, '_reason');
            toggleReasonInput(radio, reasonId);
            
            // ลบคลาส is-invalid ถ้ามี
            radio.classList.remove('is-invalid');
        }

        // ฟังก์ชันสำหรับเปิด/ปิด textarea สำหรับเหตุผล
        function toggleReasonInput(radio, reasonId) {
            const reasonInput = document.getElementById(reasonId);
            const value = parseInt(radio.value);
            
            // เปิด textarea ให้กรอกได้เสมอ
            reasonInput.disabled = false;
            reasonInput.style.borderColor = '#6c757d';
            
            // ตั้งค่า required เฉพาะคะแนน 1, 2, และ 5
            if (value === 1 || value === 2 || value === 5) {
                reasonInput.required = true;
                reasonInput.style.borderColor = '#dc3545'; // สีแดงเพื่อบ่งบอกว่าจำเป็น
                reasonInput.placeholder = 'กรุณาอธิบายเหตุผลในเชิงสร้างสรรค์... (จำเป็นต้องกรอก)';
            } else {
                reasonInput.required = false;
                reasonInput.style.borderColor = '#6c757d'; // สีเทาปกติ
                reasonInput.placeholder = 'กรุณาอธิบายเหตุผลในเชิงสร้างสรรค์... (ไม่บังคับ)';
            }
        }

        // ฟังก์ชันตรวจสอบความถูกต้องของฟอร์ม
        function validateForm(event) {
            event.preventDefault();
            console.log("Form validation started");

            // ตรวจสอบว่าฟอร์มถูกส่งไปแล้วหรือไม่
            const submitBtn = event.target;
            if (submitBtn.disabled) {
                console.log("Form already submitted");
                return false;
            }

            let isValid = true;
            const categories = ['j_score', 'o_score', 'u_score', 'r_score', 'n_score', 'a_score', 'l_score'];
            
            // ล้างข้อผิดพลาดเก่า
            document.querySelectorAll('.is-invalid').forEach(element => {
                element.classList.remove('is-invalid');
            });

            // ตรวจสอบแต่ละหมวดหมู่
            for (const category of categories) {
                // ตรวจสอบการเลือกคะแนน
                const selectedRadio = document.querySelector(`input[name="${category}"]:checked`);
                if (!selectedRadio) {
                    isValid = false;
                    console.log(`Missing score for ${category}`);
                    // เพิ่มสีแดงให้ radio buttons
                    document.querySelectorAll(`input[name="${category}"]`).forEach(radio => {
                        radio.classList.add('is-invalid');
                    });
                } else {
                    // ตรวจสอบ textarea ถ้าจำเป็น
                    const reasonTextarea = document.getElementById(`${category}_reason`);
                    const score = parseInt(selectedRadio.value);
                    const reasonValue = reasonTextarea.value.trim();
                    
                    // ตรวจสอบว่าต้องกรอกเหตุผลหรือไม่ (คะแนน 1, 2, 5)
                    if ((score === 1 || score === 2 || score === 5) && reasonValue === '') {
                        isValid = false;
                        console.log(`Missing reason for ${category} with score ${score}`);
                        reasonTextarea.classList.add('is-invalid');
                    }
                    // ตรวจสอบความยาวของความคิดเห็น (ถ้ามีการกรอก)
                    else if (reasonValue !== '' && reasonValue.length < 10) {
                        isValid = false;
                        console.log(`Comment too short for ${category}: ${reasonValue.length} characters (minimum 10)`);
                        reasonTextarea.classList.add('is-invalid');
                    } else {
                        console.log(`${category}: score=${score}, reason=${reasonValue ? 'provided' : 'not provided'}, length=${reasonValue.length}`);
                    }
                }
            }

            if (!isValid) {
                console.log("Form validation failed");
                alert("กรุณากรอกข้อมูลให้ครบถ้วน:\n- เลือกคะแนนในทุกหมวดหมู่\n- กรอกเหตุผลสำหรับคะแนน 1, 2, และ 5\n- ความคิดเห็นต้องมีอย่างน้อย 10 ตัวอักษร");
                
                // เลื่อนไปยังข้อผิดพลาดแรก
                const firstError = document.querySelector('.is-invalid');
                if (firstError) {
                    firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }
                return false;
            }

            console.log("Form validation passed");
            
            // Debug: แสดงข้อมูลที่จะส่ง
            const formData = new FormData(document.getElementById('evaluationForm'));
            console.log("=== Form Data Debug ===");
            console.log("Form method:", document.getElementById('evaluationForm').method);
            console.log("Form action:", document.getElementById('evaluationForm').action);
            console.log("Total form fields:", formData.size);
            
            console.log("Form data to be sent:");
            for (let [key, value] of formData.entries()) {
                console.log(`${key}: "${value}" (type: ${typeof value})`);
            }
            console.log("=== End Form Data Debug ===");
            
            // ตรวจสอบค่าคะแนนโดยเฉพาะ
            const scores = ['j_score', 'o_score', 'u_score', 'r_score', 'n_score', 'a_score', 'l_score'];
            console.log("=== Score Validation ===");
            for (const score of scores) {
                const value = formData.get(score);
                console.log(`${score}: "${value}" (exists: ${value !== null}, valid: ${value >= 1 && value <= 5})`);
            }
            console.log("=== End Score Validation ===");
            
            // แสดงการยืนยัน
            if (confirm("คุณแน่ใจหรือไม่ว่าต้องการบันทึกการประเมินนี้?")) {
                console.log("User confirmed submission");
                
                // ลบ event listener ของ beforeunload ก่อน
                window.onbeforeunload = null;
                
                // แสดงสถานะกำลังส่ง
                submitBtn.innerHTML = '<i class="bi bi-hourglass-split"></i> กำลังบันทึก...';
                submitBtn.disabled = true;
                submitBtn.style.opacity = '0.6';
                submitBtn.style.cursor = 'not-allowed';
                
                console.log("Submitting form...");
                // ส่งฟอร์มโดยไม่ disable controls ก่อน
                document.getElementById('evaluationForm').submit();
                
                // หลังจากส่งฟอร์มแล้วค่อย disable controls เพื่อป้องกันการส่งซ้ำ
                setTimeout(() => {
                    // ป้องกันการกดปุ่มอื่นๆ
                    document.querySelectorAll('input[type="radio"], textarea').forEach(element => {
                        element.disabled = true;
                    });
                }, 500);
            } else {
                console.log("User cancelled submission");
            }
        }

        // ป้องกันการย้อนกลับ
        function preventBackNavigation() {
            history.pushState(null, null, location.href);
            window.onpopstate = function () {
                history.pushState(null, null, location.href);
                alert("ไม่สามารถย้อนกลับไปแก้ไขข้อมูลได้");
            };
        }

        // ป้องกันการโหลดหน้าใหม่
        window.onbeforeunload = function () {
            return "คุณแน่ใจหรือไม่ว่าต้องการออกจากหน้านี้? ข้อมูลที่ยังไม่ได้บันทึกจะหายไป";
        };

        // เมื่อโหลดหน้าแล้ว
        window.onload = function () {
            preventBackNavigation();
            console.log("แบบฟอร์มประเมินโหลดสำเร็จ");
            
            // ตรวจสอบว่าฟอร์มมีอยู่หรือไม่
            const form = document.getElementById('evaluationForm');
            if (!form) {
                console.error("ไม่พบฟอร์ม evaluationForm");
                return;
            }
            
            // ตรวจสอบ action ของฟอร์ม
            console.log("Form action:", form.action);
            console.log("Form method:", form.method);
            
            // เปิดใช้งาน textarea ทั้งหมดตั้งแต่เริ่มต้น
            document.querySelectorAll('.reason-section textarea').forEach(textarea => {
                textarea.disabled = false;
                textarea.style.borderColor = '#6c757d';
                textarea.placeholder = 'กรุณาอธิบายเหตุผลในเชิงสร้างสรรค์... (ไม่บังคับ)';
            });
            
            // เพิ่ม hover effects ให้ rating options
            document.querySelectorAll('.rating-option').forEach(option => {
                option.addEventListener('mouseenter', function() {
                    this.style.transform = 'scale(1.1)';
                });
                
                option.addEventListener('mouseleave', function() {
                    this.style.transform = 'scale(1)';
                });
            });

            // เพิ่ม event listener สำหรับดาว
            document.querySelectorAll('.star-icon').forEach(star => {
                star.addEventListener('mouseenter', function() {
                    this.style.transform = 'scale(1.3)';
                    this.style.textShadow = '0 0 20px rgba(255, 193, 7, 0.8)';
                });
                
                star.addEventListener('mouseleave', function() {
                    const radio = this.previousElementSibling;
                    if (!radio.checked) {
                        this.style.transform = 'scale(1)';
                        this.style.textShadow = 'none';
                    }
                });
            });

            // เพิ่ม event listener สำหรับ textarea ตรวจสอบความยาว 10 ตัวอักษร
            document.querySelectorAll('textarea[id$="_reason"]').forEach(textarea => {
                textarea.addEventListener('input', function() {
                    const length = this.value.trim().length;
                    
                    // ลบ class เก่า
                    this.classList.remove('valid-length', 'is-invalid');
                    
                    if (length >= 10) {
                        // เพิ่มสีเขียวถ้าครบ 10 ตัวอักษร
                        this.classList.add('valid-length');
                    } else if (length > 0 && length < 10) {
                        // เพิ่มสีแดงถ้ากรอกแล้วแต่ยังไม่ครบ 10 ตัวอักษร
                        this.classList.add('is-invalid');
                    }
                });
            });

            // เพิ่ม event listener สำหรับ submit form
            form.addEventListener('submit', function(e) {
                console.log("Form submit event triggered");
                
                // ป้องกันการส่งฟอร์มซ้ำ
                if (this.dataset.submitting === 'true') {
                    console.log("Form already being submitted");
                    e.preventDefault();
                    return false;
                }
                
                this.dataset.submitting = 'true';
                console.log("Form data:", new FormData(this));
                
                // ตรวจสอบว่าฟอร์มจะถูกส่งไปที่ไหน
                console.log("Submitting to:", this.action);
            });
        };
    </script>

    <!-- Footer -->
    <footer>
        <div class="container">
            <small>© 2025 Web Portal All rights reserved</small>
        </div>
    </footer>

</body>

</html>