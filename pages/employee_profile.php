<?php
// เปิด error reporting สำหรับ debug
error_reporting(E_ALL);
ini_set('display_errors', 1);

// เริ่มต้น session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include_once '../config.php';
include_once '../includes/header.php';
include_once '../includes/menu.php';

// ตรวจสอบว่าผู้ใช้ล็อกอินแล้วหรือยัง
if (!isset($_SESSION['employee_id'])) {
    header("Location: ../index.php");
    exit;
}

// ดึงข้อมูลพนักงานจากฐานข้อมูล
$conn = dbConnect();
if (!$conn) {
    die("Database connection failed");
}

$sql = "SELECT 
            e.employee_id, 
            e.prefix_th, 
            e.first_name_th, 
            e.last_name_th, 
            e.position_th, 
            e.division, 
            d.DeptName, 
            s.SectionName,
            e.start_date, 
            l.LevelName
        FROM employees e
        LEFT JOIN dept d ON e.division = d.DeptID
        LEFT JOIN section s ON e.division = s.DeptID
        LEFT JOIN level l ON e.approval_line = l.LevelID
        WHERE e.employee_id = ?";

// ใช้ SQLSRV syntax
$params = array($_SESSION['employee_id']);
$stmt = sqlsrv_query($conn, $sql, $params);

if ($stmt === false) {
    die("SQL error: " . print_r(sqlsrv_errors(), true));
}

$employee = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
sqlsrv_free_stmt($stmt);

// ดึงข้อมูลผู้อนุมัติ
$sqlApprovers = "
SELECT 
    a.ApprovalLevel1,
    a.ApprovalLevel2,
    a.ApprovalLevel3,
    e1.first_name_th AS ApproverLevel1FirstName,
    e1.last_name_th AS ApproverLevel1LastName,
    e2.first_name_th AS ApproverLevel2FirstName,
    e2.last_name_th AS ApproverLevel2LastName,
    e3.first_name_th AS ApproverLevel3FirstName,
    e3.last_name_th AS ApproverLevel3LastName
FROM 
    approvals a
LEFT JOIN 
    employees e1 ON a.ApprovalLevel1 = e1.employee_id
LEFT JOIN 
    employees e2 ON a.ApprovalLevel2 = e2.employee_id
LEFT JOIN 
    employees e3 ON a.ApprovalLevel3 = e3.employee_id
WHERE 
    a.ApprovalID = (
        SELECT approver_id 
        FROM employees 
        WHERE employee_id = ?
    )
";

// ใช้ SQLSRV syntax
$paramsApprovers = array($_SESSION['employee_id']);
$stmtApprovers = sqlsrv_query($conn, $sqlApprovers, $paramsApprovers);

if ($stmtApprovers === false) {
    // ถ้า query ไม่ได้ ให้ใช้ default values
    $approvers = null;
} else {
    $approvers = sqlsrv_fetch_array($stmtApprovers, SQLSRV_FETCH_ASSOC);
    sqlsrv_free_stmt($stmtApprovers);
}

// ดึงข้อมูลคะแนนเฉลี่ยจาก form_score_summary และจำนวน emp_pe จาก form_score
$sql = "
    SELECT 
        (AVG(fss.avg_j_score) / (SELECT COUNT(fs.emp_pe) FROM form_score fs WHERE fs.emp_pe = ? AND fs.j_score IS NOT NULL)) AS avg_j_score,
        (AVG(fss.avg_o_score) / (SELECT COUNT(fs.emp_pe) FROM form_score fs WHERE fs.emp_pe = ? AND fs.o_score IS NOT NULL)) AS avg_o_score,
        (AVG(fss.avg_u_score) / (SELECT COUNT(fs.emp_pe) FROM form_score fs WHERE fs.emp_pe = ? AND fs.u_score IS NOT NULL)) AS avg_u_score,
        (AVG(fss.avg_r_score) / (SELECT COUNT(fs.emp_pe) FROM form_score fs WHERE fs.emp_pe = ? AND fs.r_score IS NOT NULL)) AS avg_r_score,
        (AVG(fss.avg_n_score) / (SELECT COUNT(fs.emp_pe) FROM form_score fs WHERE fs.emp_pe = ? AND fs.n_score IS NOT NULL)) AS avg_n_score,
        (AVG(fss.avg_a_score) / (SELECT COUNT(fs.emp_pe) FROM form_score fs WHERE fs.emp_pe = ? AND fs.a_score IS NOT NULL)) AS avg_a_score,
        (AVG(fss.avg_l_score) / (SELECT COUNT(fs.emp_pe) FROM form_score fs WHERE fs.emp_pe = ? AND fs.l_score IS NOT NULL)) AS avg_l_score,
        (SELECT COUNT(fs.emp_pe) FROM form_score fs WHERE fs.emp_pe = ? AND fs.j_score IS NOT NULL) AS emp_count
    FROM form_score_summary fss
    WHERE fss.emp_pe = ?
";

// ดึงข้อมูลรายละเอียดการประเมิน
$sqlDetails = "
    SELECT 
        fs.emp_as,
        fs.FormID,
        f.FormName,
        fs.j_score,
        fs.o_score,
        fs.u_score,
        fs.r_score,
        fs.n_score,
        fs.a_score,
        fs.l_score,
        fs.created_date,
        e.first_name_th,
        e.last_name_th,
        e.position_th
    FROM form_score fs
    LEFT JOIN form f ON fs.FormID = f.FormID
    LEFT JOIN employees e ON fs.emp_as = e.employee_id
    WHERE fs.emp_pe = ? 
    AND fs.status_form = 'enable'
    ORDER BY fs.created_date DESC
";

// ดึงข้อมูลรายละเอียดการประเมิน
$paramsDetails = array($_SESSION['employee_id']);
$stmtDetails = sqlsrv_query($conn, $sqlDetails, $paramsDetails);

$evaluation_details = [];
if ($stmtDetails) {
    while ($row = sqlsrv_fetch_array($stmtDetails, SQLSRV_FETCH_ASSOC)) {
        $evaluation_details[] = $row;
    }
    sqlsrv_free_stmt($stmtDetails);
}

// คำนวณอายุงาน (Length of Service)
if (isset($employee['start_date']) && $employee['start_date']) {
    // Handle datetime object from SQLSRV
    if ($employee['start_date'] instanceof DateTime) {
        $start_date = $employee['start_date'];
    } else {
        $start_date = new DateTime($employee['start_date']);
    }
    $formatted_start_date = $start_date->format('d/m/Y');
    $current_date = new DateTime();
    $interval = $start_date->diff($current_date);
    $length_of_service = $interval->y . ' ปี ' . $interval->m . ' เดือน ' . $interval->d . ' วัน ';
} else {
    $formatted_start_date = 'N/A';
    $length_of_service = 'N/A';
}

// ใช้ SQLSRV syntax
$paramsScore = array(
    $_SESSION['employee_id'], $_SESSION['employee_id'], $_SESSION['employee_id'], 
    $_SESSION['employee_id'], $_SESSION['employee_id'], $_SESSION['employee_id'], 
    $_SESSION['employee_id'], $_SESSION['employee_id'], $_SESSION['employee_id']
);

$stmtScore = sqlsrv_query($conn, $sql, $paramsScore);

if ($stmtScore === false) {
    // ถ้า query ไม่ได้ ให้ใช้ default values
    $average_scores = array(
        'avg_j_score' => 0,
        'avg_o_score' => 0,
        'avg_u_score' => 0,
        'avg_r_score' => 0,
        'avg_n_score' => 0,
        'avg_a_score' => 0,
        'avg_l_score' => 0,
        'emp_count' => 0
    );
} else {
    $average_scores = sqlsrv_fetch_array($stmtScore, SQLSRV_FETCH_ASSOC);
    if (!$average_scores) {
        $average_scores = array(
            'avg_j_score' => 0,
            'avg_o_score' => 0,
            'avg_u_score' => 0,
            'avg_r_score' => 0,
            'avg_n_score' => 0,
            'avg_a_score' => 0,
            'avg_l_score' => 0,
            'emp_count' => 0
        );
    }
    sqlsrv_free_stmt($stmtScore);
}

// ดึงข้อมูลคูปองของพนักงาน พร้อมสถานะ
// $sqlCoupons = "SELECT c.coupon_code, c.description, c.points_required, mc.redeemed_at, mc.status 
//                FROM my_coupons mc
//                INNER JOIN coupons c ON mc.coupon_id = c.id
//                WHERE mc.employee_id = ?
//                ORDER BY mc.redeemed_at DESC";
// $stmtCoupons = sqlsrv_query($conn, $sqlCoupons, array($_SESSION['employee_id']));
// if ($stmtCoupons) {
//     $resultCoupons = sqlsrv_fetch_array($stmtCoupons, SQLSRV_FETCH_ASSOC);
//     sqlsrv_free_stmt($stmtCoupons);
// }

// ดึงผลการประเมินบุคลิกภาพสีล่าสุด
$colorResult = null;
$colorDescriptions = [
'purple' => [
    'name'        => 'Purple (STRATEGIC THINKING)',
'description'  => "• ฝึกการสื่อสารแนวคิดให้เข้าใจง่าย\n"
               . "• หากคะแนนแข็งแรงใน EXECUTING ช่วยวางแผนให้เป็นจริง\n"
               . "• ใช้เวลาเดี่ยวและวิเคราะห์อย่างมีระบบ"
],
'yellow' => [
    'name'        => 'Yellow (INFLUENCING)',
    'description'  => "• ฝึกการฟังและ empathy เพื่อเพิ่มประสิทธิภาพการชักจูง\n"
                   . "• หากคะแนนแข็งแรงใน EXECUTING มาเป็นทีม\n"
                   . "• ใช้ energy ในการสร้างแรงบันดาลใจให้กับคนอื่น"
],
'blue' => [
    'name'        => 'Blue (RELATIONSHIP BUILDING)',
    'description'  => "• พัฒนาทักษะการให้ feedback ที่สร้างสรรค์\n"
                   . "• ช่วยทีมเข้าใจคนในทีมและรับมือกับการจัดการทีม\n"
                   . "• ระวังการให้ความช่วยเหลือมากเกินไปจนหมดพลัง"
],
'green' => [
    'name'        => 'Green (EXECUTING)',
    'description'  => "• หาพันธมิตรที่แข็งแกร่งใน INFLUENCING และ RELATIONSHIP BUILDING\n"
                   . "• ใช้ความเป็นระบบเป็นจุดแข็งในการทำงาน\n"
                   . "• สร้าง processes และ systems ที่คนอื่นสามารถใช้ได้"
],
];
$sqlColor = "SELECT TOP 1 * FROM color_personality_results WHERE employee_id = ? ORDER BY created_at DESC";
$stmtColor = sqlsrv_query($conn, $sqlColor, array($_SESSION['employee_id']));
if ($stmtColor && ($row = sqlsrv_fetch_array($stmtColor, SQLSRV_FETCH_ASSOC))) {
    $colorResult = $row;
    $colorResult['scores'] = json_decode($colorResult['scores'], true);
}
if ($stmtColor) sqlsrv_free_stmt($stmtColor);

// SQLSRV connections are closed automatically when the script ends
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ข้อมูลพนักงาน - Web Portal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/styles.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        :root {
            --primary-gray: #2c3e50;
            --light-gray: #ecf0f1;
            --medium-gray: #95a5a6;
            --dark-gray: #34495e;
            --white: #ffffff;
            --card-shadow: 0 8px 32px rgba(0,0,0,0.08);
            --card-shadow-hover: 0 16px 48px rgba(0,0,0,0.12);
            --border-radius: 24px;
            --border-radius-small: 16px;
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            --gradient-primary: linear-gradient(135deg, var(--primary-gray) 0%, var(--dark-gray) 100%);
            --gradient-card: linear-gradient(135deg, var(--white) 0%, #f8f9fa 100%);
            --gradient-bg: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            --gradient-j: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);
            --gradient-o: linear-gradient(135deg, #f39c12 0%, #e67e22 100%);
            --gradient-u: linear-gradient(135deg, #9b59b6 0%, #8e44ad 100%);
            --gradient-r: linear-gradient(135deg, #3498db 0%, #2980b9 100%);
            --gradient-n: linear-gradient(135deg, #27ae60 0%, #2ecc71 100%);
            --gradient-a: linear-gradient(135deg, #f1c40f 0%, #f39c12 100%);
            --gradient-l: linear-gradient(135deg, #e67e22 0%, #d35400 100%);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Prompt', sans-serif !important;
            background: var(--gradient-bg);
            color: var(--primary-gray);
            line-height: 1.6;
            min-height: 100vh;
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
            flex: 1;
            position: relative;
            z-index: 1;
        }

        .profile-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem 1rem;
        }

        /* Header */
        .page-header {
            background: var(--gradient-card);
            border-radius: var(--border-radius);
            padding: 2.5rem 3rem;
            margin-bottom: 2.5rem;
            box-shadow: var(--card-shadow);
            border: 1px solid rgba(255, 255, 255, 0.8);
            position: relative;
            overflow: hidden;
            text-align: center;
        }

        .page-header::before {
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

        .page-header h1 {
            font-size: 2.2rem;
            font-weight: 300;
            color: var(--primary-gray);
            margin: 0;
            position: relative;
            z-index: 2;
            letter-spacing: -0.5px;
        }

        .page-header h1 i {
            margin-right: 0.75rem;
            color: var(--medium-gray);
        }

        /* Cards */
        .card {
            background: var(--white);
            border-radius: var(--border-radius);
            box-shadow: var(--card-shadow);
            border: 1px solid rgba(255, 255, 255, 0.8);
            overflow: hidden;
            transition: var(--transition);
            margin-bottom: 2rem;
        }

        .card:hover {
            transform: translateY(-4px);
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

        .card-body {
            padding: 2.25rem;
            background: var(--white);
        }

        /* Profile Info */
        .profile-info {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 2rem;
            margin-bottom: 2rem;
        }

        .info-item {
            background: var(--gradient-card);
            border: 1px solid rgba(236, 240, 241, 0.6);
            border-radius: var(--border-radius-small);
            padding: 1.5rem;
            transition: var(--transition);
            position: relative;
        }

        .info-item:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 16px rgba(0,0,0,0.08);
        }

        .info-item::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 4px;
            background: var(--gradient-primary);
            border-radius: var(--border-radius-small) var(--border-radius-small) 0 0;
        }

        .info-item strong {
            color: var(--primary-gray);
            font-weight: 600;
            display: block;
            margin-bottom: 0.5rem;
        }

        .info-item span {
            color: var(--medium-gray);
            font-size: 1.1rem;
        }

        /* Score Cards */
        .score-grid {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .score-card {
            background: var(--white);
            border-radius: var(--border-radius-small);
            padding: 1.25rem 0.75rem;
            text-align: center;
            transition: var(--transition);
            position: relative;
            overflow: hidden;
            box-shadow: var(--card-shadow);
            border: 1px solid rgba(255, 255, 255, 0.8);
        }

        .score-card:hover {
            transform: translateY(-4px) scale(1.02);
            box-shadow: var(--card-shadow-hover);
        }

        .score-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            border-radius: var(--border-radius-small) var(--border-radius-small) 0 0;
        }

        .score-card.j::before { background: var(--gradient-j); }
        .score-card.o::before { background: var(--gradient-o); }
        .score-card.u::before { background: var(--gradient-u); }
        .score-card.r::before { background: var(--gradient-r); }
        .score-card.n::before { background: var(--gradient-n); }
        .score-card.a::before { background: var(--gradient-a); }
        .score-card.l::before { background: var(--gradient-l); }

        .score-label {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--primary-gray);
            margin-bottom: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .score-value {
            font-size: 2rem;
            font-weight: 700;
            color: var(--primary-gray);
            margin-bottom: 0;
        }

        /* Buttons */
        .btn-primary {
            background: var(--gradient-primary);
            color: var(--white);
            border: none;
            border-radius: 12px;
            padding: 0.75rem 2rem;
            font-weight: 500;
            transition: var(--transition);
            font-size: 1rem;
            letter-spacing: 0.3px;
        }

        .btn-primary:hover {
            color: var(--white);
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(44, 62, 80, 0.25);
            background: linear-gradient(135deg, var(--dark-gray) 0%, var(--primary-gray) 100%);
        }

        .btn-primary i {
            margin-right: 0.5rem;
        }

        /* Stats Card */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: var(--gradient-card);
            border: 1px solid rgba(236, 240, 241, 0.6);
            border-radius: var(--border-radius-small);
            padding: 1.5rem;
            text-align: center;
            transition: var(--transition);
        }

        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 16px rgba(0,0,0,0.08);
        }

        .stat-number {
            font-size: 2rem;
            font-weight: 700;
            color: var(--primary-gray);
            margin-bottom: 0.5rem;
        }

        .stat-label {
            font-size: 0.9rem;
            color: var(--medium-gray);
            font-weight: 500;
        }

        /* Footer */
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

        /* Table Styles */
        .table-responsive {
            border-radius: var(--border-radius-small);
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.04);
        }

        .table {
            margin-bottom: 0;
            background: var(--white);
        }

        .table th {
            background: var(--gradient-primary);
            color: var(--white);
            font-weight: 500;
            border: none;
            padding: 1rem 0.75rem;
            font-size: 0.9rem;
        }

        .table td {
            padding: 0.75rem;
            vertical-align: middle;
            border-top: 1px solid rgba(236, 240, 241, 0.6);
        }

        .table-hover tbody tr:hover {
            background: rgba(248, 249, 250, 0.8);
        }

        /* Badge Styles */
        .badge {
            font-size: 0.8rem;
            padding: 0.4rem 0.6rem;
            border-radius: 6px;
            font-weight: 500;
        }

        .badge.bg-danger {
            background: var(--gradient-j) !important;
        }

        .badge.bg-warning {
            background: var(--gradient-o) !important;
            color: var(--white) !important;
        }

        .badge.bg-secondary {
            background: var(--gradient-u) !important;
        }

        .badge.bg-primary {
            background: var(--gradient-r) !important;
        }

        .badge.bg-success {
            background: var(--gradient-n) !important;
        }

        .badge.bg-info {
            background: var(--gradient-a) !important;
            color: var(--white) !important;
        }

        .badge.bg-dark {
            background: var(--gradient-l) !important;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .profile-container {
                padding: 1.5rem 1rem;
            }
            
            .page-header {
                padding: 2rem;
                margin-bottom: 2rem;
            }
            
            .page-header h1 {
                font-size: 1.8rem;
            }
            
            .card-header {
                padding: 1.5rem 1.75rem;
                font-size: 1.1rem;
            }
            
            .card-body {
                padding: 1.75rem;
            }
            
            .score-grid {
                grid-template-columns: repeat(7, 1fr);
                gap: 0.75rem;
            }
            
            .score-card {
                padding: 1rem 0.5rem;
            }
            
            .score-label {
                font-size: 1.2rem;
            }
            
            .score-value {
                font-size: 1.5rem;
            }
        }

        @media (max-width: 576px) {
            .page-header h1 {
                font-size: 1.6rem;
            }
            
            .profile-info {
                grid-template-columns: 1fr;
                gap: 1rem;
            }
            
            .score-grid {
                grid-template-columns: repeat(7, 1fr);
                gap: 0.5rem;
            }
            
            .score-card {
                padding: 0.75rem 0.25rem;
            }
            
            .score-label {
                font-size: 1rem;
                margin-bottom: 0.5rem;
            }
            
            .score-value {
                font-size: 1.2rem;
            }
        }

        /* Four Strengths SVG Characters - Layout แยกตามความเด่น */
        .characters-container {
            display: flex;
            flex-direction: column;
            gap: 20px;
            max-width: 1200px;
            width: 100%;
            margin: 0 auto;
        }
        
        .dominant-row {
            display: flex;
            justify-content: center;
            margin-bottom: 10px;
        }
        
        .others-row {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
            justify-items: center;
        }
        
        .character-card {
            border-radius: 20px;
            padding: 25px 15px;
            text-align: center;
            box-shadow: 0 12px 30px rgba(0,0,0,0.15);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
            background: white;
            cursor: pointer;
            width: 280px;
            max-width: 100%;
        }
        
        .character-card.dominant {
            transform: scale(1.1);
            box-shadow: 0 20px 40px rgba(0,0,0,0.25);
            border: 3px solid #ffd700;
            background: linear-gradient(135deg, #fff 0%, #fffbf0 100%);
        }
        
        .character-card.dominant::before {
            content: "เด่นที่สุด";
            position: absolute;
            top: 5px;
            right: 10px;
            background: #ffd700;
            color: #333;
            padding: 2px 8px;
            border-radius: 10px;
            font-size: 10px;
            font-weight: bold;
        }
        
        .character-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 20px 40px rgba(0,0,0,0.25);
        }

        .character-card.active {
            transform: translateY(-8px) scale(1.02);
            box-shadow: 0 25px 50px rgba(0,0,0,0.3);
        }

        .card-strategic {
            border-top: 6px solid #27ae60;
        }

        .card-influencing {
            border-top: 6px solid #f39c12;
        }

        .card-relationship {
            border-top: 6px solid #3498db;
        }

        .card-executing {
            border-top: 6px solid #8e44ad;
        }

        .character-svg {
            width: 100px;
            height: 120px;
            margin: 0 auto 15px;
            display: block;
            transition: transform 0.3s ease;
        }

        .character-card:hover .character-svg {
            transform: scale(1.05);
        }

        /* Text Styles */
        .strength-title {
            font-size: 16px;
            font-weight: bold;
            color: #2c3e50;
            margin-bottom: 5px;
            letter-spacing: 0.5px;
        }

        .strength-subtitle {
            font-size: 12px;
            color: #7f8c8d;
            margin-bottom: 12px;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }

        .strength-score {
            font-size: 28px;
            font-weight: bold;
            margin-bottom: 3px;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.1);
        }

        .card-strategic .strength-score { color: #27ae60; }
        .card-influencing .strength-score { color: #f39c12; }
        .card-relationship .strength-score { color: #3498db; }
        .card-executing .strength-score { color: #8e44ad; }

        .score-label {
            font-size: 10px;
            color: #95a5a6;
            letter-spacing: 1px;
            font-weight: 500;
        }

        /* Description Modal */
        .description-modal {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.7);
            display: none;
            z-index: 1000;
            align-items: center;
            justify-content: center;
            backdrop-filter: blur(5px);
        }

        .description-content {
            background: white;
            border-radius: 20px;
            padding: 30px;
            max-width: 500px;
            width: 90%;
            position: relative;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            animation: modalSlideIn 0.3s ease-out;
        }

        @keyframes modalSlideIn {
            from {
                opacity: 0;
                transform: translateY(-50px) scale(0.9);
            }
            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }

        .modal-close {
            position: absolute;
            top: 15px;
            right: 15px;
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: #95a5a6;
            transition: color 0.3s ease;
        }

        .modal-close:hover {
            color: #2c3e50;
        }

        .modal-title {
            font-size: 24px;
            font-weight: bold;
            margin-bottom: 20px;
            color: #2c3e50;
            text-align: center;
        }

        .modal-description {
            font-size: 16px;
            line-height: 1.6;
            color: #555;
            white-space: pre-line;
        }

        /* Animation */
        @keyframes float {
            0%, 100% { 
                transform: translateY(0px) rotate(0deg); 
            }
            50% { 
                transform: translateY(-5px) rotate(1deg); 
            }
        }

        .character-svg {
            animation: float 4s ease-in-out infinite;
        }

        .card-strategic .character-svg { animation-delay: 0s; }
        .card-influencing .character-svg { animation-delay: 1s; }
        .card-relationship .character-svg { animation-delay: 2s; }
        .card-executing .character-svg { animation-delay: 3s; }

        /* Responsive Design */
        @media (max-width: 992px) {
            .dominant-row {
                margin-bottom: 15px;
            }
            
            .others-row {
                grid-template-columns: repeat(2, 1fr);
                gap: 15px;
            }
            
            .character-card {
                padding: 20px 12px;
                width: 240px;
            }
            
            .character-card.dominant {
                transform: scale(1.05);
                width: 260px;
            }
            
            .character-svg {
                width: 90px;
                height: 110px;
            }
            
            .strength-title {
                font-size: 14px;
            }
            
            .strength-score {
                font-size: 24px;
            }
        }

        @media (max-width: 576px) {
            .others-row {
                grid-template-columns: repeat(2, 1fr);
                gap: 10px;
            }
            
            .character-card {
                padding: 15px 8px;
                width: 160px;
            }
            
            .character-card.dominant {
                transform: scale(1.02);
                width: 180px;
            }
            
            .character-svg {
                width: 70px;
                height: 85px;
            }
            
            .strength-title {
                font-size: 12px;
            }
            
            .strength-subtitle {
                font-size: 10px;
            }
            
            .strength-score {
                font-size: 20px;
            }
            
            .score-label {
                font-size: 9px;
            }

            .description-content {
                padding: 20px;
                max-width: 95%;
            }
            
            .modal-title {
                font-size: 20px;
            }
            
            .modal-description {
                font-size: 14px;
            }
        }

        /* Animations */
        .card {
            animation: fadeInUp 0.6s ease-out;
        }

        .card:nth-child(2) {
            animation-delay: 0.1s;
        }

        .card:nth-child(3) {
            animation-delay: 0.2s;
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
    </style>
</head>
<body>
<body>
    <div class="container">
        <div class="profile-container">
            <!-- Page Header -->
            <div class="page-header">
                <h1><i class="bi bi-person-badge"></i> ข้อมูลพนักงาน</h1>
            </div>

            <!-- ข้อมูลส่วนตัว -->
            <div class="card">
                <div class="card-header">
                    <i class="bi bi-person-lines-fill"></i> ข้อมูลส่วนตัว
                </div>
                <div class="card-body">
                    <div class="profile-info">
                        <div class="info-item">
                            <strong>รหัสพนักงาน:</strong>
                            <span><?php echo htmlspecialchars($employee['employee_id'] ?? 'ไม่ระบุ'); ?></span>
                        </div>
                        <div class="info-item">
                            <strong>ชื่อ-นามสกุล:</strong>
                            <span><?php echo htmlspecialchars(($employee['prefix_th'] ?? '') . ' ' . ($employee['first_name_th'] ?? '') . ' ' . ($employee['last_name_th'] ?? '')); ?></span>
                        </div>
                        <div class="info-item">
                            <strong>ตำแหน่ง:</strong>
                            <span><?php echo htmlspecialchars($employee['position_th'] ?? 'ไม่ระบุ'); ?></span>
                        </div>
                        <div class="info-item">
                            <strong>ระดับตำแหน่ง:</strong>
                            <span><?php echo htmlspecialchars($employee['LevelName'] ?? 'ไม่ระบุ'); ?></span>
                        </div>
                        <div class="info-item">
                            <strong>แผนก:</strong>
                            <span><?php echo htmlspecialchars($employee['DeptName'] ?? 'ไม่ระบุ'); ?></span>
                        </div>
                        <div class="info-item">
                            <strong>ฝ่าย:</strong>
                            <span><?php echo htmlspecialchars($employee['SectionName'] ?? 'ไม่ระบุ'); ?></span>
                        </div>
                        <div class="info-item">
                            <strong>วันที่เริ่มงาน:</strong>
                            <span><?php echo htmlspecialchars($formatted_start_date ?? 'ไม่ระบุ'); ?></span>
                        </div>
                        <div class="info-item">
                            <strong>อายุงาน:</strong>
                            <span><?php echo $length_of_service ?? 'ไม่ระบุ'; ?></span>
                        </div>
                        <div class="info-item">
                            <strong>ผู้บังคับบัญชา:</strong>
                            <span><?php echo htmlspecialchars(($approvers['ApproverLevel1FirstName'] ?? 'ไม่ระบุ') . ' ' . ($approvers['ApproverLevel1LastName'] ?? '')); ?></span>
                        </div>
                    </div>
                    
                    <div class="d-flex justify-content-between align-items-center">
                        <!-- <div>
                            <a href="view_my_evaluation_report.php" class="btn btn-primary me-2">
                                <i class="bi bi-file-earmark-text"></i> ดู Report การประเมิน
                            </a>
                        </div> -->
                        <a href="edit_employee.php" class="btn btn-primary">
                            <i class="bi bi-pencil-square"></i> แก้ไขข้อมูล
                        </a>
                    </div>
                </div>
            </div>

            <!-- Four Strengths Characters (เหมือน four_strengths_pixel_art.html) -->
            <div class="card">
                <div class="card-header">
                    <i class="bi bi-people-fill"></i> Four Strengths Characters
                </div>
                <div class="card-body">
                    <?php if ($colorResult): ?>
                    <div class="characters-container">
                        <?php
                        // สร้าง array ของ characters พร้อมคะแนน
                        $characters = [
                            'purple' => [
                                'score' => (int)($colorResult['scores']['purple'] ?? 0),
                                'title' => 'STRATEGIC',
                                'subtitle' => 'THINKING',
                                'class' => 'card-strategic',
                                'svg' => '<!-- Strategic SVG -->
                                <svg class="character-svg" viewBox="0 0 180 220" xmlns="http://www.w3.org/2000/svg">
                                    <!-- Head -->
                                    <circle cx="90" cy="50" r="28" fill="#fdbcb4"/>
                                    
                                    <!-- Glasses -->
                                    <rect x="65" y="40" width="50" height="20" fill="none" stroke="#2c3e50" stroke-width="3" rx="10"/>
                                    <circle cx="77" cy="50" r="12" fill="rgba(255,255,255,0.3)"/>
                                    <circle cx="103" cy="50" r="12" fill="rgba(255,255,255,0.3)"/>
                                    
                                    <!-- Eyes -->
                                    <circle cx="77" cy="50" r="3" fill="#2c3e50"/>
                                    <circle cx="103" cy="50" r="3" fill="#2c3e50"/>
                                    
                                    <!-- Thoughtful expression -->
                                    <path d="M78 63 Q90 68 102 63" fill="none" stroke="#34495e" stroke-width="2"/>
                                    
                                    <!-- Body -->
                                    <rect x="65" y="78" width="50" height="60" fill="#27ae60" rx="8"/>
                                    
                                    <!-- Analytical lines on shirt -->
                                    <rect x="70" y="95" width="40" height="2" fill="#2ecc71"/>
                                    <rect x="70" y="105" width="40" height="2" fill="#2ecc71"/>
                                    <rect x="70" y="115" width="40" height="2" fill="#2ecc71"/>
                                    
                                    <!-- Arms -->
                                    <ellipse cx="50" cy="100" rx="12" ry="25" fill="#fdbcb4"/>
                                    <ellipse cx="130" cy="100" rx="12" ry="25" fill="#fdbcb4"/>
                                    
                                    <!-- Strategic documents -->
                                    <rect x="25" y="90" width="20" height="25" fill="#ecf0f1" stroke="#34495e" stroke-width="1"/>
                                    <line x1="27" y1="95" x2="43" y2="95" stroke="#27ae60" stroke-width="1"/>
                                    <line x1="27" y1="100" x2="43" y2="100" stroke="#27ae60" stroke-width="1"/>
                                    <rect x="30" y="105" width="8" height="6" fill="none" stroke="#27ae60" stroke-width="1"/>
                                    
                                    <!-- Tools -->
                                    <rect x="135" y="95" width="15" height="2" fill="#f39c12"/>
                                    <line x1="137" y1="98" x2="148" y2="98" stroke="#95a5a6" stroke-width="2"/>
                                    
                                    <!-- Legs -->
                                    <rect x="75" y="138" width="12" height="40" fill="#2c3e50" rx="6"/>
                                    <rect x="93" y="138" width="12" height="40" fill="#2c3e50" rx="6"/>
                                    
                                    <!-- Shoes -->
                                    <ellipse cx="81" cy="185" rx="8" ry="5" fill="#34495e"/>
                                    <ellipse cx="99" cy="185" rx="8" ry="5" fill="#34495e"/>
                                </svg>'
                            ],
                            'yellow' => [
                                'score' => (int)($colorResult['scores']['yellow'] ?? 0),
                                'title' => 'INFLUENCING',
                                'subtitle' => 'LEADERSHIP',
                                'class' => 'card-influencing',
                                'svg' => '<!-- Influencing SVG -->
                                <svg class="character-svg" viewBox="0 0 180 220" xmlns="http://www.w3.org/2000/svg">
                                    <!-- Head -->
                                    <circle cx="90" cy="50" r="28" fill="#fdbcb4"/>
                                    
                                    <!-- Hair -->
                                    <path d="M58 35 Q90 18 122 35 Q122 30 118 28 Q90 15 62 28 Q58 30 58 35" fill="#8b4513"/>
                                    <path d="M55 40 Q62 35 68 40" fill="#8b4513"/>
                                    <path d="M112 40 Q118 35 125 40" fill="#8b4513"/>
                                    
                                    <!-- Eyes -->
                                    <circle cx="80" cy="47" r="4" fill="#2c3e50"/>
                                    <circle cx="100" cy="47" r="4" fill="#2c3e50"/>
                                    <circle cx="81" cy="46" r="1.5" fill="white"/>
                                    <circle cx="101" cy="46" r="1.5" fill="white"/>
                                    
                                    <!-- Confident smile -->
                                    <path d="M75 58 Q90 68 105 58" fill="none" stroke="#e74c3c" stroke-width="3" stroke-linecap="round"/>
                                    
                                    <!-- Body -->
                                    <rect x="65" y="78" width="50" height="60" fill="#f39c12" rx="8"/>
                                    
                                    <!-- Tie -->
                                    <polygon points="85,78 95,78 98,120 82,120" fill="#c0392b"/>
                                    
                                    <!-- Suit lapels -->
                                    <polygon points="65,78 80,78 75,88" fill="#e67e22"/>
                                    <polygon points="100,78 115,78 105,88" fill="#e67e22"/>
                                    
                                    <!-- Arms -->
                                    <ellipse cx="45" cy="95" rx="12" ry="20" fill="#fdbcb4" transform="rotate(-25 45 95)"/>
                                    <ellipse cx="135" cy="95" rx="12" ry="20" fill="#fdbcb4" transform="rotate(25 135 95)"/>
                                    
                                    <!-- Briefcase -->
                                    <rect x="135" y="85" width="25" height="18" fill="#8b4513" rx="2"/>
                                    <rect x="140" y="88" width="15" height="12" fill="#a0522d"/>
                                    <circle cx="160" cy="94" r="2" fill="#2c3e50"/>
                                    
                                    <!-- Motivational gestures -->
                                    <ellipse cx="25" cy="85" rx="8" ry="15" fill="#fdbcb4" transform="rotate(-45 25 85)"/>
                                    
                                    <!-- Legs -->
                                    <rect x="75" y="138" width="12" height="40" fill="#2c3e50" rx="6"/>
                                    <rect x="93" y="138" width="12" height="40" fill="#2c3e50" rx="6"/>
                                    
                                    <!-- Professional shoes -->
                                    <ellipse cx="81" cy="185" rx="9" ry="5" fill="#1a1a1a"/>
                                    <ellipse cx="99" cy="185" rx="9" ry="5" fill="#1a1a1a"/>
                                </svg>'
                            ],
                            'blue' => [
                                'score' => (int)($colorResult['scores']['blue'] ?? 0),
                                'title' => 'RELATIONSHIP',
                                'subtitle' => 'BUILDING',
                                'class' => 'card-relationship',
                                'svg' => '<!-- Relationship SVG -->
                                <svg class="character-svg" viewBox="0 0 180 220" xmlns="http://www.w3.org/2000/svg">
                                    <!-- Head -->
                                    <circle cx="90" cy="50" r="28" fill="#fdbcb4"/>
                                    
                                    <!-- Gentle hair -->
                                    <path d="M60 30 Q90 20 120 30 Q115 25 110 28 Q90 18 70 28 Q65 25 60 30" fill="#d2691e"/>
                                    
                                    <!-- Warm eyes -->
                                    <circle cx="80" cy="47" r="3" fill="#2c3e50"/>
                                    <circle cx="100" cy="47" r="3" fill="#2c3e50"/>
                                    <circle cx="81" cy="46" r="1" fill="white"/>
                                    <circle cx="101" cy="46" r="1" fill="white"/>
                                    
                                    <!-- Caring smile -->
                                    <path d="M78 58 Q90 65 102 58" fill="none" stroke="#e74c3c" stroke-width="2" stroke-linecap="round"/>
                                    
                                    <!-- Body -->
                                    <rect x="65" y="78" width="50" height="60" fill="#3498db" rx="8"/>
                                    
                                    <!-- Heart pattern on shirt -->
                                    <circle cx="80" cy="95" r="4" fill="#e74c3c"/>
                                    <circle cx="88" cy="95" r="4" fill="#e74c3c"/>
                                    <path d="M72 99 Q80 110 88 99 Q96 110 104 99" fill="#e74c3c"/>
                                    
                                    <!-- Arms -->
                                    <ellipse cx="50" cy="95" rx="12" ry="22" fill="#fdbcb4"/>
                                    <ellipse cx="130" cy="95" rx="12" ry="22" fill="#fdbcb4"/>
                                    
                                    <!-- Helping hands -->
                                    <circle cx="35" cy="90" r="8" fill="#fdbcb4"/>
                                    <circle cx="145" cy="90" r="8" fill="#fdbcb4"/>
                                    
                                    <!-- Care symbols -->
                                    <circle cx="25" cy="80" r="3" fill="#e74c3c"/>
                                    <circle cx="30" cy="85" r="2" fill="#f39c12"/>
                                    <circle cx="20" cy="88" r="2" fill="#27ae60"/>
                                    
                                    <!-- Community connection -->
                                    <circle cx="155" cy="85" r="4" fill="#9b59b6"/>
                                    <circle cx="160" cy="95" r="3" fill="#3498db"/>
                                    <line x1="155" y1="85" x2="160" y2="95" stroke="#34495e" stroke-width="1"/>
                                    
                                    <!-- Legs -->
                                    <rect x="75" y="138" width="12" height="40" fill="#2c3e50" rx="6"/>
                                    <rect x="93" y="138" width="12" height="40" fill="#2c3e50" rx="6"/>
                                    
                                    <!-- Comfortable shoes -->
                                    <ellipse cx="81" cy="185" rx="8" ry="5" fill="#8b4513"/>
                                    <ellipse cx="99" cy="185" rx="8" ry="5" fill="#8b4513"/>
                                </svg>'
                            ],
                            'green' => [
                                'score' => (int)($colorResult['scores']['green'] ?? 0),
                                'title' => 'EXECUTING',
                                'subtitle' => 'DELIVERY',
                                'class' => 'card-executing',
                                'svg' => '<!-- Executing SVG -->
                                <svg class="character-svg" viewBox="0 0 180 220" xmlns="http://www.w3.org/2000/svg">
                                    <!-- Head -->
                                    <circle cx="90" cy="50" r="28" fill="#fdbcb4"/>
                                    
                                    <!-- Hard hat -->
                                    <path d="M58 35 Q90 25 122 35 Q122 42 90 40 Q58 42 58 35" fill="#f1c40f"/>
                                    <rect x="60" y="33" width="60" height="8" fill="#f39c12"/>
                                    
                                    <!-- Eyes -->
                                    <circle cx="80" cy="49" r="2" fill="#2c3e50"/>
                                    <circle cx="100" cy="49" r="2" fill="#2c3e50"/>
                                    
                                    <!-- Determined mouth -->
                                    <line x1="82" y1="58" x2="98" y2="58" stroke="#34495e" stroke-width="2"/>
                                    
                                    <!-- Body -->
                                    <rect x="65" y="78" width="50" height="60" fill="#8e44ad" rx="8"/>
                                    
                                    <!-- Reflective strips -->
                                    <rect x="67" y="95" width="46" height="3" fill="#f1c40f"/>
                                    <rect x="67" y="115" width="46" height="3" fill="#f1c40f"/>
                                    
                                    <!-- Tool belt -->
                                    <rect x="60" y="125" width="60" height="8" fill="#8b4513"/>
                                    <rect x="70" y="127" width="5" height="10" fill="#95a5a6"/>
                                    <rect x="78" y="127" width="5" height="10" fill="#e67e22"/>
                                    <rect x="86" y="127" width="5" height="10" fill="#34495e"/>
                                    <rect x="94" y="127" width="5" height="10" fill="#c0392b"/>
                                    <rect x="102" y="127" width="5" height="10" fill="#27ae60"/>
                                    
                                    <!-- Arms -->
                                    <ellipse cx="50" cy="100" rx="12" ry="25" fill="#fdbcb4"/>
                                    <ellipse cx="130" cy="100" rx="12" ry="25" fill="#fdbcb4"/>
                                    
                                    <!-- Project plans -->
                                    <rect x="25" y="90" width="20" height="25" fill="#ecf0f1" stroke="#34495e" stroke-width="1"/>
                                    <line x1="27" y1="95" x2="43" y2="95" stroke="#9b59b6" stroke-width="1"/>
                                    <line x1="27" y1="100" x2="43" y2="100" stroke="#9b59b6" stroke-width="1"/>
                                    <rect x="30" y="105" width="8" height="6" fill="none" stroke="#9b59b6" stroke-width="1"/>
                                    <rect x="32" y="107" width="4" height="2" fill="none" stroke="#9b59b6" stroke-width="1"/>
                                    
                                    <!-- Engineering tools -->
                                    <rect x="135" y="95" width="15" height="3" fill="#95a5a6"/>
                                    <rect x="140" y="90" width="3" height="15" fill="#e67e22"/>
                                    <circle cx="148" cy="102" r="3" fill="#34495e"/>
                                    
                                    <!-- Safety equipment -->
                                    <rect x="155" y="80" width="8" height="20" fill="#e74c3c"/>
                                    <rect x="157" y="85" width="4" height="3" fill="#ecf0f1"/>
                                    
                                    <!-- Legs -->
                                    <rect x="75" y="138" width="12" height="40" fill="#2c3e50" rx="6"/>
                                    <rect x="93" y="138" width="12" height="40" fill="#2c3e50" rx="6"/>
                                    
                                    <!-- Safety boots -->
                                    <ellipse cx="81" cy="185" rx="10" ry="6" fill="#8b4513"/>
                                    <ellipse cx="99" cy="185" rx="10" ry="6" fill="#8b4513"/>
                                    <rect x="76" y="180" width="10" height="4" fill="#f39c12"/>
                                    <rect x="94" y="180" width="10" height="4" fill="#f39c12"/>
                                </svg>'
                            ]
                        ];
                        
                        // เรียงลำดับตามคะแนน (สูงสุดก่อน) - ปรับปรุงให้แน่ใจว่าการเรียงลำดับถูกต้อง
                        uasort($characters, function($a, $b) {
                            $scoreA = (int)$a['score'];
                            $scoreB = (int)$b['score'];
                            if ($scoreA == $scoreB) {
                                return 0;
                            }
                            return ($scoreA > $scoreB) ? -1 : 1;
                        });
                        
                        // แยกสีที่เด่นที่สุดกับที่เหลือ
                        $sortedKeys = array_keys($characters);
                        $dominantColor = $sortedKeys[0];
                        $dominantCharacter = $characters[$dominantColor];
                        
                        // สร้าง array สำหรับสีที่เหลือ (3 อันดับถัดไป)
                        $otherCharacters = [];
                        for ($i = 1; $i < count($sortedKeys); $i++) {
                            $otherCharacters[$sortedKeys[$i]] = $characters[$sortedKeys[$i]];
                        }
                        ?>
                        
                        <!-- แถวบน: สีที่เด่นที่สุด -->
                        <div class="dominant-row">
                            <div class="character-card <?php echo $dominantCharacter['class']; ?> dominant" data-type="<?php echo $dominantColor; ?>">
                                <?php echo $dominantCharacter['svg']; ?>
                                
                                <div class="strength-title"><?php echo $dominantCharacter['title']; ?></div>
                                <div class="strength-subtitle"><?php echo $dominantCharacter['subtitle']; ?></div>
                                <div class="strength-score"><?php echo $dominantCharacter['score']; ?></div>
                                <div class="score-label">POINTS</div>
                            </div>
                        </div>
                        
                        <!-- แถวล่าง: สีที่เหลือ 3 สี -->
                        <div class="others-row">
                            <?php foreach ($otherCharacters as $colorType => $character): ?>
                            <div class="character-card <?php echo $character['class']; ?>" data-type="<?php echo $colorType; ?>">
                                <?php echo $character['svg']; ?>
                                
                                <div class="strength-title"><?php echo $character['title']; ?></div>
                                <div class="strength-subtitle"><?php echo $character['subtitle']; ?></div>
                                <div class="strength-score"><?php echo $character['score']; ?></div>
                                <div class="score-label">POINTS</div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    
                    <!-- Description Modal -->
                    <div class="description-modal" id="descriptionModal">
                        <div class="description-content">
                            <button class="modal-close" id="modalClose">&times;</button>
                            <div class="modal-title" id="modalTitle"></div>
                            <div class="modal-description" id="modalDescription"></div>
                        </div>
                    </div>
                    <?php else: ?>
                    <div class="text-center py-5">
                        <div class="mb-4">
                            <i class="bi bi-people-fill" style="font-size: 4rem; color: #dee2e6;"></i>
                        </div>
                        <h5 class="text-muted mb-3">ยังไม่มีข้อมูล Four Strengths Characters</h5>
                        <p class="text-muted mb-4">กรุณาทำแบบทดสอบบุคลิกภาพสีก่อน เพื่อดู Characters แสดงจุดแข็งของคุณ</p>
                        <a href="color_personality.php" class="btn btn-primary btn-lg">
                            <i class="bi bi-palette"></i> ทำแบบทดสอบบุคลิกภาพสี
                        </a>
                        <div class="mt-3">
                            <small class="text-muted">แบบทดสอบใช้เวลาประมาณ 5-10 นาที</small>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- สถิติการประเมิน -->
            <div class="card">
                <div class="card-header">
                    <i class="bi bi-graph-up"></i> สถิติการประเมิน
                </div>
                <div class="card-body">
                    <div class="stats-grid">
                        <div class="stat-card">
                            <div class="stat-number"><?php echo $average_scores['emp_count'] ?? 0; ?></div>
                            <div class="stat-label">จำนวนครั้งที่ถูกประเมิน</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-number"><?php echo number_format(((float)($average_scores['avg_j_score'] ?? 0) + (float)($average_scores['avg_o_score'] ?? 0) + (float)($average_scores['avg_u_score'] ?? 0) + (float)($average_scores['avg_r_score'] ?? 0) + (float)($average_scores['avg_n_score'] ?? 0) + (float)($average_scores['avg_a_score'] ?? 0) + (float)($average_scores['avg_l_score'] ?? 0)) / 7, 2); ?></div>
                            <div class="stat-label">คะแนนเฉลี่ยรวม</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-number"><?php echo number_format(max((float)($average_scores['avg_j_score'] ?? 0), (float)($average_scores['avg_o_score'] ?? 0), (float)($average_scores['avg_u_score'] ?? 0), (float)($average_scores['avg_r_score'] ?? 0), (float)($average_scores['avg_n_score'] ?? 0), (float)($average_scores['avg_a_score'] ?? 0), (float)($average_scores['avg_l_score'] ?? 0)), 2); ?></div>
                            <div class="stat-label">คะแนนสูงสุด</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- สรุปคะแนนเฉลี่ย -->
            <div class="card">
                <div class="card-header">
                    <i class="bi bi-bar-chart-line"></i> สรุปคะแนนเฉลี่ยการประเมิน JOURNAL
                </div>
                <div class="card-body">
                    <div class="score-grid">
                        <div class="score-card j">
                            <div class="score-label">J</div>
                            <div class="score-value"><?php echo number_format($average_scores['avg_j_score'] ?? 0, 2); ?></div>
                        </div>
                        <div class="score-card o">
                            <div class="score-label">O</div>
                            <div class="score-value"><?php echo number_format($average_scores['avg_o_score'] ?? 0, 2); ?></div>
                        </div>
                        <div class="score-card u">
                            <div class="score-label">U</div>
                            <div class="score-value"><?php echo number_format($average_scores['avg_u_score'] ?? 0, 2); ?></div>
                        </div>
                        <div class="score-card r">
                            <div class="score-label">R</div>
                            <div class="score-value"><?php echo number_format($average_scores['avg_r_score'] ?? 0, 2); ?></div>
                        </div>
                        <div class="score-card n">
                            <div class="score-label">N</div>
                            <div class="score-value"><?php echo number_format($average_scores['avg_n_score'] ?? 0, 2); ?></div>
                        </div>
                        <div class="score-card a">
                            <div class="score-label">A</div>
                            <div class="score-value"><?php echo number_format($average_scores['avg_a_score'] ?? 0, 2); ?></div>
                        </div>
                        <div class="score-card l">
                            <div class="score-label">L</div>
                            <div class="score-value"><?php echo number_format($average_scores['avg_l_score'] ?? 0, 2); ?></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- รายละเอียดการประเมิน -->
            <?php if (!empty($evaluation_details)): ?>
            <div class="card">
                <div class="card-header">
                    <i class="bi bi-list-ul"></i> รายละเอียดการประเมิน
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>ผู้ประเมิน</th>
                                    <th>ตำแหน่ง</th>
                                    <th>แบบฟอร์ม</th>
                                    <th class="text-center">J</th>
                                    <th class="text-center">O</th>
                                    <th class="text-center">U</th>
                                    <th class="text-center">R</th>
                                    <th class="text-center">N</th>
                                    <th class="text-center">A</th>
                                    <th class="text-center">L</th>
                                    <th class="text-center">รวม</th>
                                    <th>วันที่</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($evaluation_details as $detail): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars(($detail['first_name_th'] ?? '') . ' ' . ($detail['last_name_th'] ?? '')); ?></td>
                                    <td><?php echo htmlspecialchars($detail['position_th'] ?? ''); ?></td>
                                    <td><?php echo htmlspecialchars($detail['FormName'] ?? ''); ?></td>
                                    <td class="text-center">
                                        <span class="badge bg-danger"><?php echo number_format($detail['j_score'] ?? 0, 1); ?></span>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge bg-warning"><?php echo number_format($detail['o_score'] ?? 0, 1); ?></span>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge bg-secondary"><?php echo number_format($detail['u_score'] ?? 0, 1); ?></span>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge bg-primary"><?php echo number_format($detail['r_score'] ?? 0, 1); ?></span>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge bg-success"><?php echo number_format($detail['n_score'] ?? 0, 1); ?></span>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge bg-info"><?php echo number_format($detail['a_score'] ?? 0, 1); ?></span>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge bg-dark"><?php echo number_format($detail['l_score'] ?? 0, 1); ?></span>
                                    </td>
                                    <td class="text-center">
                                        <strong><?php echo number_format(((float)($detail['j_score'] ?? 0) + (float)($detail['o_score'] ?? 0) + (float)($detail['u_score'] ?? 0) + (float)($detail['r_score'] ?? 0) + (float)($detail['n_score'] ?? 0) + (float)($detail['a_score'] ?? 0) + (float)($detail['l_score'] ?? 0)) / 7, 2); ?></strong>
                                    </td>
                                    <td>
                                        <?php 
                                        if (isset($detail['created_date']) && $detail['created_date']) {
                                            if ($detail['created_date'] instanceof DateTime) {
                                                echo $detail['created_date']->format('d/m/Y');
                                            } else {
                                                echo date('d/m/Y', strtotime($detail['created_date']));
                                            }
                                        } else {
                                            echo 'N/A';
                                        }
                                        ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <?php endif; ?>
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
        // Flat Design Characters Animations
        document.addEventListener('DOMContentLoaded', function() {
            // Animate cards on load
            const cards = document.querySelectorAll('.strength-card');
            cards.forEach((card, index) => {
                card.style.opacity = '0';
                card.style.transform = 'translateY(50px)';
                setTimeout(() => {
                    card.style.transition = 'all 0.6s cubic-bezier(0.4, 0, 0.2, 1)';
                    card.style.opacity = '1';
                    card.style.transform = 'translateY(0)';
                }, index * 150);
            });

            // Add hover effects to cards
            cards.forEach(card => {
                card.addEventListener('mouseenter', function() {
                    const character = this.querySelector('.character-illustration');
                    const score = this.querySelector('.strength-score');
                    
                    if (character) {
                        character.style.animation = 'characterBounce 0.6s ease-in-out';
                    }
                    
                    if (score) {
                        score.style.animation = 'scoreGlow 0.8s ease-in-out infinite alternate';
                    }
                });

                card.addEventListener('mouseleave', function() {
                    const character = this.querySelector('.character-illustration');
                    const score = this.querySelector('.strength-score');
                    
                    if (character) {
                        character.style.animation = '';
                    }
                    
                    if (score) {
                        score.style.animation = '';
                    }
                });

                // Click animation
                card.addEventListener('click', function() {
                    this.style.animation = 'cardPulse 0.3s ease-in-out';
                    setTimeout(() => {
                        this.style.animation = '';
                    }, 300);
                });
            });

            // Random character animations
            setInterval(() => {
                const characters = document.querySelectorAll('.character-illustration');
                if (characters.length > 0) {
                    const randomCharacter = characters[Math.floor(Math.random() * characters.length)];
                    randomCharacter.style.animation = 'characterWiggle 0.5s ease-in-out';
                    setTimeout(() => {
                        randomCharacter.style.animation = '';
                    }, 500);
                }
            }, 4000);

            // Add floating animation to documents and tools
            const documents = document.querySelectorAll('.document, .briefcase, .heart, .tool');
            documents.forEach((item, index) => {
                setTimeout(() => {
                    item.style.animation = 'float 3s ease-in-out infinite';
                    item.style.animationDelay = (index * 0.5) + 's';
                }, 1000);
            });
        });

        // Add CSS animations dynamically
        const style = document.createElement('style');
        style.textContent = `
            @keyframes characterBounce {
                0%, 20%, 50%, 80%, 100% { transform: translateY(0) scale(1); }
                40% { transform: translateY(-10px) scale(1.05); }
                60% { transform: translateY(-5px) scale(1.02); }
            }

            @keyframes scoreGlow {
                0% { transform: scale(1); text-shadow: 0 0 5px rgba(255,255,255,0.5); }
                100% { transform: scale(1.1); text-shadow: 0 0 20px rgba(255,255,255,0.8); }
            }

            @keyframes cardPulse {
                0% { transform: scale(1); }
                50% { transform: scale(0.98); }
                100% { transform: scale(1); }
            }

            @keyframes characterWiggle {
                0%, 100% { transform: rotate(0deg); }
                25% { transform: rotate(2deg); }
                75% { transform: rotate(-2deg); }
            }

            @keyframes float {
                0%, 100% { transform: translateY(0px); }
                50% { transform: translateY(-3px); }
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

            /* Additional character-specific animations */
            .strategic-character .glasses {
                animation: glassesShine 4s ease-in-out infinite;
            }

            @keyframes glassesShine {
                0%, 90%, 100% { box-shadow: none; }
                95% { box-shadow: 0 0 10px rgba(255,255,255,0.8); }
            }

            .influencing-character .tie {
                animation: tieWave 2s ease-in-out infinite;
            }

            @keyframes tieWave {
                0%, 100% { transform: translateX(-50%) rotate(0deg); }
                50% { transform: translateX(-50%) rotate(2deg); }
            }

            .relationship-character .heart {
                animation: heartBeat 1.5s ease-in-out infinite;
            }

            @keyframes heartBeat {
                0%, 100% { transform: rotate(-45deg) scale(1); }
                50% { transform: rotate(-45deg) scale(1.2); }
            }

            .executing-character .helmet {
                animation: helmetGlow 3s ease-in-out infinite;
            }

            @keyframes helmetGlow {
                0%, 80%, 100% { box-shadow: none; }
                90% { box-shadow: 0 0 15px rgba(255, 213, 79, 0.6); }
            }
        `;
        document.head.appendChild(style);
        
        // Color personality descriptions
        const colorDescriptions = {
            purple: {
                title: "บุคลิกภาพสีม่วง (Strategic Thinking)",
                description: "คนสีม่วงเป็นผู้ที่มีความคิดวิเคราะห์ สามารถคิดอย่างเป็นระบบ และมีวิสัยทัศน์ในการวางแผนระยะยาว ชอบการใช้เหตุผลในการตัดสินใจ และมักจะมองเห็นภาพรวมของสถานการณ์ได้ดี เป็นผู้ที่ชอบความท้าทายทางปัญญา และสามารถแก้ปัญหาเชิงซับซ้อนได้"
            },
            yellow: {
                title: "บุคลิกภาพสีเหลือง (Influencing)",
                description: "คนสีเหลืองเป็นผู้ที่มีบุคลิกภาพเปิดเผย มีความเป็นผู้นำ ชอบการติดต่อสื่อสารและการโน้มน้าวผู้อื่น มีความมั่นใจในตนเอง กล้าแสดงออก และสามารถสร้างแรงบันดาลใจให้กับคนรอบข้างได้ดี มักจะเป็นคนที่มีพลังและความกระตือรือร้นสูง"
            },
            blue: {
                title: "บุคลิกภาพสีน้ำเงิน (Relationship Building)",
                description: "คนสีน้ำเงินเป็นผู้ที่ใส่ใจในความรู้สึกของผู้อื่น มีความเห็นอกเห็นใจ ชอบการช่วยเหลือและสนับสนุนผู้อื่น เป็นคนที่เข้าใจและรับฟังผู้อื่นได้ดี สามารถสร้างความสัมพันธ์ที่ดีและรักษาไมตรีจิตได้เป็นอย่างดี มักจะเป็นคนที่อ่อนโยนและมีความอดทน"
            },
            green: {
                title: "บุคลิกภาพสีเขียว (Executing)",
                description: "คนสีเขียวเป็นผู้ที่มีความรับผิดชอบสูง ทำงานอย่างเป็นระเบียบ ชอบความแม่นยำและความปลอดภัย เป็นคนที่พึ่งพาได้ มีความพยายาม และสามารถทำงานให้สำเร็จลุล่วงได้ตามเป้าหมาย มักจะเป็นคนที่มีวินัยและความมุ่งมั่นสูง"
            }
        };
        
        // Modal functionality
        const modal = document.getElementById('descriptionModal');
        const modalClose = document.getElementById('modalClose');
        const modalTitle = document.getElementById('modalTitle');
        const modalDescription = document.getElementById('modalDescription');
        
        // Add click event to character cards
        document.querySelectorAll('.character-card').forEach(card => {
            card.addEventListener('click', function() {
                const type = this.getAttribute('data-type');
                const desc = colorDescriptions[type];
                
                if (desc) {
                    modalTitle.textContent = desc.title;
                    modalDescription.textContent = desc.description;
                    modal.style.display = 'flex';
                    
                    // Trigger animation
                    setTimeout(() => {
                        modal.querySelector('.description-content').style.transform = 'scale(1)';
                        modal.querySelector('.description-content').style.opacity = '1';
                    }, 10);
                }
            });
        });
        
        // Close modal events
        if (modalClose) {
            modalClose.addEventListener('click', closeModal);
        }
        
        if (modal) {
            modal.addEventListener('click', function(e) {
                if (e.target === modal) {
                    closeModal();
                }
            });
        }
        
        function closeModal() {
            if (modal) {
                const content = modal.querySelector('.description-content');
                if (content) {
                    content.style.transform = 'scale(0.8)';
                    content.style.opacity = '0';
                }
                
                setTimeout(() => {
                    modal.style.display = 'none';
                }, 300);
            }
        }
        
        // Keyboard support
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && modal && modal.style.display === 'flex') {
                closeModal();
            }
        });
    </script>
</body>
</html>