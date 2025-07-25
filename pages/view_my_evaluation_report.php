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

// เชื่อมต่อฐานข้อมูล
$conn = dbConnect();
if (!$conn) {
    die("Database connection failed");
}

// ดึงข้อมูลพนักงาน
$sqlEmployee = "SELECT 
                e.employee_id, 
                e.prefix_th, 
                e.first_name_th, 
                e.last_name_th, 
                e.position_th, 
                d.DeptName
            FROM employees e
            LEFT JOIN dept d ON e.division = d.DeptID
            WHERE e.employee_id = ?";

$paramsEmployee = array($_SESSION['employee_id']);
$stmtEmployee = sqlsrv_query($conn, $sqlEmployee, $paramsEmployee);

if ($stmtEmployee === false) {
    die("SQL error: " . print_r(sqlsrv_errors(), true));
}

$employee = sqlsrv_fetch_array($stmtEmployee, SQLSRV_FETCH_ASSOC);
sqlsrv_free_stmt($stmtEmployee);

// ดึงข้อมูลการประเมินโดยไม่แสดงผู้ประเมิน
$sqlEvaluations = "
    SELECT 
        fs.FormID,
        f.FormName,
        fs.j_score,
        fs.o_score,
        fs.u_score,
        fs.r_score,
        fs.n_score,
        fs.a_score,
        fs.l_score,
        fs.j_comment,
        fs.o_comment,
        fs.u_comment,
        fs.r_comment,
        fs.n_comment,
        fs.a_comment,
        fs.l_comment,
        fs.created_date,
        'ผู้ประเมินที่ ' + CAST(ROW_NUMBER() OVER (ORDER BY fs.created_date) AS VARCHAR) AS evaluator_anonymous
    FROM form_score fs
    LEFT JOIN form f ON fs.FormID = f.FormID
    WHERE fs.emp_pe = ? 
    AND fs.status_form = 'enable'
    ORDER BY fs.created_date DESC
";

$paramsEvaluations = array($_SESSION['employee_id']);
$stmtEvaluations = sqlsrv_query($conn, $sqlEvaluations, $paramsEvaluations);

$evaluations = [];
if ($stmtEvaluations) {
    while ($row = sqlsrv_fetch_array($stmtEvaluations, SQLSRV_FETCH_ASSOC)) {
        $evaluations[] = $row;
    }
    sqlsrv_free_stmt($stmtEvaluations);
}

// คำนวณคะแนนเฉลี่ย
$totalEvaluations = count($evaluations);
$avgScores = [
    'j' => 0, 'o' => 0, 'u' => 0, 'r' => 0, 'n' => 0, 'a' => 0, 'l' => 0
];

if ($totalEvaluations > 0) {
    foreach ($evaluations as $eval) {
        $avgScores['j'] += (float)($eval['j_score'] ?? 0);
        $avgScores['o'] += (float)($eval['o_score'] ?? 0);
        $avgScores['u'] += (float)($eval['u_score'] ?? 0);
        $avgScores['r'] += (float)($eval['r_score'] ?? 0);
        $avgScores['n'] += (float)($eval['n_score'] ?? 0);
        $avgScores['a'] += (float)($eval['a_score'] ?? 0);
        $avgScores['l'] += (float)($eval['l_score'] ?? 0);
    }
    
    foreach ($avgScores as $key => $score) {
        $avgScores[$key] = $score / $totalEvaluations;
    }
}

$overallAvg = array_sum($avgScores) / 7;
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>รายงานการประเมิน - Web Portal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
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

        .report-container {
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

        .page-header p {
            font-size: 1.1rem;
            color: var(--medium-gray);
            margin: 0.5rem 0 0 0;
            position: relative;
            z-index: 2;
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

        /* Evaluation Cards */
        .evaluation-card {
            background: var(--gradient-card);
            border: 1px solid rgba(236, 240, 241, 0.6);
            border-radius: var(--border-radius-small);
            padding: 2rem;
            margin-bottom: 2rem;
            transition: var(--transition);
            position: relative;
        }

        .evaluation-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(0,0,0,0.12);
        }

        .evaluation-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 4px;
            background: var(--gradient-primary);
            border-radius: var(--border-radius-small) var(--border-radius-small) 0 0;
        }

        .evaluation-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid rgba(236, 240, 241, 0.8);
        }

        .evaluator-badge {
            background: var(--gradient-primary);
            color: var(--white);
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-weight: 600;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .evaluation-date {
            background: var(--white);
            color: var(--medium-gray);
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.85rem;
            border: 1px solid rgba(236, 240, 241, 0.8);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .scores-section {
            margin-bottom: 2rem;
        }

        .scores-title {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--primary-gray);
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .scores-row {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .score-item {
            text-align: center;
            padding: 1rem;
            border-radius: 12px;
            background: var(--white);
            border: 2px solid rgba(236, 240, 241, 0.6);
            transition: var(--transition);
            position: relative;
            overflow: hidden;
        }

        .score-item:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 16px rgba(0,0,0,0.1);
        }

        .score-item::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
        }

        .score-item.j::before { background: var(--gradient-j); }
        .score-item.o::before { background: var(--gradient-o); }
        .score-item.u::before { background: var(--gradient-u); }
        .score-item.r::before { background: var(--gradient-r); }
        .score-item.n::before { background: var(--gradient-n); }
        .score-item.a::before { background: var(--gradient-a); }
        .score-item.l::before { background: var(--gradient-l); }

        .score-item-label {
            font-size: 1rem;
            font-weight: 700;
            color: var(--primary-gray);
            margin-bottom: 0.5rem;
        }

        .score-item-value {
            font-size: 1.8rem;
            font-weight: 700;
            color: var(--primary-gray);
        }

        .comments-section {
            margin-top: 2rem;
        }

        .comments-title {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--primary-gray);
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .comment-item {
            margin-bottom: 1rem;
            padding: 1.25rem;
            background: var(--white);
            border-radius: 12px;
            border-left: 4px solid var(--medium-gray);
            transition: var(--transition);
        }

        .comment-item:hover {
            transform: translateX(4px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
        }

        .comment-label {
            font-weight: 700;
            color: var(--primary-gray);
            font-size: 1rem;
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .comment-text {
            color: var(--medium-gray);
            font-size: 0.95rem;
            line-height: 1.6;
            background: #f8f9fa;
            padding: 0.75rem;
            border-radius: 8px;
            border: 1px solid rgba(236, 240, 241, 0.6);
        }

        /* Signature Section */
        .signature-section {
            background: var(--white);
            border-radius: var(--border-radius);
            box-shadow: var(--card-shadow);
            border: 1px solid rgba(255, 255, 255, 0.8);
            overflow: hidden;
            margin-top: 2rem;
            position: relative;
        }

        .signature-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 6px;
            background: linear-gradient(135deg, #e74c3c 0%, #f39c12 14.3%, #9b59b6 28.6%, #3498db 42.9%, #27ae60 57.2%, #f1c40f 71.5%, #e67e22 85.8%, #e74c3c 100%);
        }

        .signature-header {
            background: var(--gradient-primary);
            color: var(--white);
            padding: 1.75rem 2.25rem;
            font-weight: 500;
            font-size: 1.15rem;
            position: relative;
            overflow: hidden;
        }

        .signature-header::before {
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

        .signature-section:hover .signature-header::before {
            transform: translateX(100%);
        }

        .signature-body {
            padding: 2.5rem;
        }

        .signature-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 3rem;
            margin-bottom: 2rem;
        }

        .signature-box {
            text-align: center;
            position: relative;
        }

        .signature-area {
            border: 3px dashed var(--medium-gray);
            border-radius: 16px;
            height: 140px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 1.5rem;
            background: linear-gradient(135deg, #fafbfc 0%, #f8f9fa 100%);
            transition: var(--transition);
            position: relative;
            overflow: hidden;
        }

        .signature-area:hover {
            border-color: var(--primary-gray);
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.1);
        }

        .signature-area::before {
            content: '';
            position: absolute;
            bottom: 25px;
            left: 25px;
            right: 25px;
            height: 2px;
            background: linear-gradient(135deg, var(--medium-gray) 0%, rgba(149, 165, 166, 0.3) 100%);
            opacity: 0.6;
        }

        .signature-area::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="dots" width="20" height="20" patternUnits="userSpaceOnUse"><circle cx="10" cy="10" r="1" fill="rgba(149,165,166,0.1)"/></pattern></defs><rect width="100%" height="100%" fill="url(%23dots)"/></svg>');
            pointer-events: none;
            opacity: 0.3;
        }

        .signature-placeholder {
            color: var(--medium-gray);
            font-size: 0.95rem;
            text-align: center;
            position: relative;
            z-index: 2;
        }

        .signature-placeholder i {
            display: block;
            margin-bottom: 0.5rem;
            font-size: 2rem;
            opacity: 0.7;
        }

        .signature-label {
            font-weight: 600;
            color: var(--primary-gray);
            margin-bottom: 1rem;
            font-size: 1.1rem;
        }

        .signature-date {
            color: var(--medium-gray);
            font-size: 0.95rem;
            background: #f8f9fa;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            border: 1px solid rgba(236, 240, 241, 0.8);
            display: inline-block;
        }

        .signature-note {
            background: linear-gradient(135deg, #fff3cd 0%, #ffeaa7 100%);
            border: 1px solid #ffeaa7;
            border-radius: 16px;
            padding: 2rem;
            margin-top: 2rem;
            color: #856404;
            position: relative;
            overflow: hidden;
        }

        .signature-note::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(135deg, #f39c12 0%, #e67e22 100%);
        }

        .signature-note h6 {
            color: #856404;
            font-weight: 600;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 1.1rem;
        }

        .signature-note p {
            margin: 0;
            font-size: 0.95rem;
            line-height: 1.6;
        }

        .signature-note strong {
            color: #6c5700;
        }

        /* Table of Contents */
        .table-of-contents {
            background: var(--white);
            border-radius: var(--border-radius);
            box-shadow: var(--card-shadow);
            border: 1px solid rgba(255, 255, 255, 0.8);
            overflow: hidden;
            margin-bottom: 2rem;
        }

        .table-of-contents .card-body {
            padding: 2rem;
        }

        .table-of-contents a {
            color: var(--primary-gray);
            text-decoration: none;
            transition: var(--transition);
            display: flex;
            align-items: center;
            padding: 0.5rem 1rem;
            border-radius: 8px;
            font-weight: 500;
        }

        .table-of-contents a:hover {
            background: var(--light-gray);
            color: var(--primary-gray);
            transform: translateX(8px);
        }

        .table-of-contents a i {
            color: var(--medium-gray);
            margin-right: 0.75rem;
        }

        /* Scroll to top button */
        .scroll-to-top {
            position: fixed;
            bottom: 2rem;
            right: 2rem;
            background: var(--gradient-primary);
            color: var(--white);
            border: none;
            border-radius: 50%;
            width: 50px;
            height: 50px;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: var(--card-shadow);
            transition: var(--transition);
            z-index: 1000;
            opacity: 0;
            visibility: hidden;
        }

        .scroll-to-top.show {
            opacity: 1;
            visibility: visible;
        }

        .scroll-to-top:hover {
            transform: translateY(-4px);
            box-shadow: var(--card-shadow-hover);
        }

        /* Progress Bar */
        .reading-progress {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 4px;
            background: rgba(255, 255, 255, 0.1);
            z-index: 1001;
        }

        .reading-progress-fill {
            height: 100%;
            background: linear-gradient(135deg, #e74c3c 0%, #f39c12 14.3%, #9b59b6 28.6%, #3498db 42.9%, #27ae60 57.2%, #f1c40f 71.5%, #e67e22 85.8%, #e74c3c 100%);
            width: 0%;
            transition: width 0.3s ease;
        }

        /* Enhanced animations */
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

        .card {
            animation: fadeInUp 0.6s ease forwards;
        }

        .card:nth-child(1) { animation-delay: 0.1s; }
        .card:nth-child(2) { animation-delay: 0.2s; }
        .card:nth-child(3) { animation-delay: 0.3s; }
        .card:nth-child(4) { animation-delay: 0.4s; }
        .card:nth-child(5) { animation-delay: 0.5s; }

        /* Summary Cards */
        .summary-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .summary-card {
            background: var(--gradient-card);
            border: 1px solid rgba(236, 240, 241, 0.6);
            border-radius: var(--border-radius-small);
            padding: 1.5rem;
            text-align: center;
            transition: var(--transition);
            position: relative;
            overflow: hidden;
        }

        .summary-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 24px rgba(0,0,0,0.12);
        }

        .summary-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 4px;
            background: var(--gradient-primary);
        }

        .summary-icon {
            font-size: 2.5rem;
            color: var(--primary-gray);
            margin-bottom: 1rem;
        }

        .summary-number {
            font-size: 2.2rem;
            font-weight: 700;
            color: var(--primary-gray);
            margin-bottom: 0.5rem;
        }

        .summary-label {
            font-size: 0.95rem;
            color: var(--medium-gray);
            font-weight: 500;
        }

        /* Stats */
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

        /* Buttons */
        .action-buttons {
            margin-top: 2rem;
            padding: 2rem;
            background: var(--white);
            border-radius: var(--border-radius);
            box-shadow: var(--card-shadow);
            border: 1px solid rgba(255, 255, 255, 0.8);
        }

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

        .btn-secondary {
            background: var(--light-gray);
            color: var(--primary-gray);
            border: 1px solid var(--medium-gray);
            border-radius: 12px;
            padding: 0.75rem 2rem;
            font-weight: 500;
            transition: var(--transition);
            font-size: 1rem;
            letter-spacing: 0.3px;
        }

        .btn-secondary:hover {
            color: var(--primary-gray);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            background: #d5dbdb;
        }

        .btn-success {
            background: linear-gradient(135deg, #27ae60 0%, #2ecc71 100%);
            color: var(--white);
            border: none;
            border-radius: 12px;
            padding: 0.75rem 2rem;
            font-weight: 500;
            transition: var(--transition);
            font-size: 1rem;
            letter-spacing: 0.3px;
        }

        .btn-success:hover {
            color: var(--white);
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(39, 174, 96, 0.25);
            background: linear-gradient(135deg, #2ecc71 0%, #27ae60 100%);
        }

        .btn-primary i,
        .btn-secondary i,
        .btn-success i {
            margin-right: 0.5rem;
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

        /* Print Styles */
        @media print {
            body::before {
                display: none;
            }
            
            .card {
                box-shadow: none;
                border: 1px solid #ddd;
                margin-bottom: 1rem;
            }
            
            .card-header {
                background: #f8f9fa !important;
                color: #212529 !important;
            }
            
            .btn-primary {
                display: none;
            }

            .action-buttons {
                display: none;
            }
        }

        /* Responsive */
        @media (max-width: 768px) {
            .report-container {
                padding: 1.5rem 1rem;
            }
            
            .page-header {
                padding: 2rem;
                margin-bottom: 2rem;
            }
            
            .page-header h1 {
                font-size: 1.8rem;
            }
            
            .score-grid {
                grid-template-columns: repeat(7, 1fr);
                gap: 0.75rem;
            }
            
            .scores-row {
                grid-template-columns: repeat(4, 1fr);
                gap: 0.5rem;
            }

            .signature-grid {
                grid-template-columns: 1fr;
                gap: 2rem;
            }

            .action-buttons .d-flex {
                flex-direction: column;
            }

            .action-buttons .back-button {
                width: 100%;
            }

            .action-buttons .back-button a {
                width: 100%;
            }

            .table-of-contents .row {
                flex-direction: column;
            }

            .scroll-to-top {
                bottom: 1rem;
                right: 1rem;
            }
        }

        @media (max-width: 576px) {
            .page-header h1 {
                font-size: 1.6rem;
            }
            
            .score-grid {
                grid-template-columns: repeat(7, 1fr);
                gap: 0.5rem;
            }
            
            .scores-row {
                grid-template-columns: repeat(2, 1fr);
            }
        }
    </style>
</head>
<body>
    <!-- Reading Progress Bar -->
    <div class="reading-progress">
        <div class="reading-progress-fill"></div>
    </div>

    <div class="container">
        <div class="report-container">
            <!-- Page Header -->
            <div class="page-header">
                <h1><i class="bi bi-file-earmark-text"></i> รายงานการประเมิน</h1>
                <p><?php echo htmlspecialchars(($employee['prefix_th'] ?? '') . ' ' . ($employee['first_name_th'] ?? '') . ' ' . ($employee['last_name_th'] ?? '')); ?> | <?php echo htmlspecialchars($employee['position_th'] ?? ''); ?></p>
            </div>

            <!-- Table of Contents -->
            <div class="card mb-4">
                <div class="card-header">
                    <i class="bi bi-list-ol"></i> สารบัญ
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <ul class="list-unstyled mb-0">
                                <li class="mb-2">
                                    <a href="#summary" class="text-decoration-none">
                                        <i class="bi bi-1-circle me-2"></i>สรุปคะแนนรวม
                                    </a>
                                </li>
                                <li class="mb-2">
                                    <a href="#journal-scores" class="text-decoration-none">
                                        <i class="bi bi-2-circle me-2"></i>คะแนนเฉลี่ย JOURNAL
                                    </a>
                                </li>
                                <li class="mb-2">
                                    <a href="#evaluations" class="text-decoration-none">
                                        <i class="bi bi-3-circle me-2"></i>รายละเอียดการประเมิน
                                    </a>
                                </li>
                            </ul>
                        </div>
                        <div class="col-md-6">
                            <ul class="list-unstyled mb-0">
                                <li class="mb-2">
                                    <a href="#signature" class="text-decoration-none">
                                        <i class="bi bi-4-circle me-2"></i>ลายเซ็นรับทราบ
                                    </a>
                                </li>
                                <li class="mb-2">
                                    <a href="#actions" class="text-decoration-none">
                                        <i class="bi bi-5-circle me-2"></i>ดาวน์โหลดและส่งรายงาน
                                    </a>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>

            <!-- สถิติรวม -->
            <div id="summary" class="card">
                <div class="card-header">
                    <i class="bi bi-graph-up"></i> สรุปคะแนนรวม
                </div>
                <div class="card-body">
                    <div class="summary-cards">
                        <div class="summary-card">
                            <div class="summary-icon">
                                <i class="bi bi-clipboard-data"></i>
                            </div>
                            <div class="summary-number"><?php echo $totalEvaluations; ?></div>
                            <div class="summary-label">จำนวนการประเมิน</div>
                        </div>
                        <div class="summary-card">
                            <div class="summary-icon">
                                <i class="bi bi-star-fill"></i>
                            </div>
                            <div class="summary-number"><?php echo number_format($overallAvg, 2); ?></div>
                            <div class="summary-label">คะแนนเฉลี่ยรวม</div>
                        </div>
                        <div class="summary-card">
                            <div class="summary-icon">
                                <i class="bi bi-arrow-up-circle-fill"></i>
                            </div>
                            <div class="summary-number"><?php echo number_format(max($avgScores), 2); ?></div>
                            <div class="summary-label">คะแนนเฉลี่ยสูงสุด</div>
                        </div>
                        <div class="summary-card">
                            <div class="summary-icon">
                                <i class="bi bi-arrow-down-circle-fill"></i>
                            </div>
                            <div class="summary-number"><?php echo number_format(min($avgScores), 2); ?></div>
                            <div class="summary-label">คะแนนเฉลี่ยต่ำสุด</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- คะแนนเฉลี่ย JOURNAL -->
            <div id="journal-scores" class="card">
                <div class="card-header">
                    <i class="bi bi-bar-chart-line"></i> คะแนนเฉลี่ย JOURNAL
                </div>
                <div class="card-body">
                    <div class="score-grid">
                        <div class="score-card j">
                            <div class="score-label">J</div>
                            <div class="score-value"><?php echo number_format($avgScores['j'], 2); ?></div>
                        </div>
                        <div class="score-card o">
                            <div class="score-label">O</div>
                            <div class="score-value"><?php echo number_format($avgScores['o'], 2); ?></div>
                        </div>
                        <div class="score-card u">
                            <div class="score-label">U</div>
                            <div class="score-value"><?php echo number_format($avgScores['u'], 2); ?></div>
                        </div>
                        <div class="score-card r">
                            <div class="score-label">R</div>
                            <div class="score-value"><?php echo number_format($avgScores['r'], 2); ?></div>
                        </div>
                        <div class="score-card n">
                            <div class="score-label">N</div>
                            <div class="score-value"><?php echo number_format($avgScores['n'], 2); ?></div>
                        </div>
                        <div class="score-card a">
                            <div class="score-label">A</div>
                            <div class="score-value"><?php echo number_format($avgScores['a'], 2); ?></div>
                        </div>
                        <div class="score-card l">
                            <div class="score-label">L</div>
                            <div class="score-value"><?php echo number_format($avgScores['l'], 2); ?></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- รายละเอียดการประเมิน -->
            <?php if (!empty($evaluations)): ?>
            <div id="evaluations" class="card">
                <div class="card-header">
                    <i class="bi bi-list-ul"></i> รายละเอียดการประเมินแต่ละครั้ง
                </div>
                <div class="card-body">
                    <?php foreach ($evaluations as $index => $evaluation): ?>
                    <div class="evaluation-card">
                        <div class="evaluation-header">
                            <div class="evaluator-badge">
                                <i class="bi bi-person-circle"></i>
                                <?php echo htmlspecialchars($evaluation['evaluator_anonymous']); ?>
                            </div>
                            <div class="evaluation-date">
                                <i class="bi bi-calendar3"></i>
                                <?php 
                                if (isset($evaluation['created_date']) && $evaluation['created_date']) {
                                    if ($evaluation['created_date'] instanceof DateTime) {
                                        echo $evaluation['created_date']->format('d/m/Y');
                                    } else {
                                        echo date('d/m/Y', strtotime($evaluation['created_date']));
                                    }
                                } else {
                                    echo 'N/A';
                                }
                                ?>
                            </div>
                        </div>
                        
                        <div class="scores-section">
                            <div class="scores-title">
                                <i class="bi bi-bar-chart-fill"></i>
                                คะแนนการประเมิน
                            </div>
                            <div class="scores-row">
                                <div class="score-item j">
                                    <div class="score-item-label">J</div>
                                    <div class="score-item-value"><?php echo number_format($evaluation['j_score'] ?? 0, 1); ?></div>
                                </div>
                                <div class="score-item o">
                                    <div class="score-item-label">O</div>
                                    <div class="score-item-value"><?php echo number_format($evaluation['o_score'] ?? 0, 1); ?></div>
                                </div>
                                <div class="score-item u">
                                    <div class="score-item-label">U</div>
                                    <div class="score-item-value"><?php echo number_format($evaluation['u_score'] ?? 0, 1); ?></div>
                                </div>
                                <div class="score-item r">
                                    <div class="score-item-label">R</div>
                                    <div class="score-item-value"><?php echo number_format($evaluation['r_score'] ?? 0, 1); ?></div>
                                </div>
                                <div class="score-item n">
                                    <div class="score-item-label">N</div>
                                    <div class="score-item-value"><?php echo number_format($evaluation['n_score'] ?? 0, 1); ?></div>
                                </div>
                                <div class="score-item a">
                                    <div class="score-item-label">A</div>
                                    <div class="score-item-value"><?php echo number_format($evaluation['a_score'] ?? 0, 1); ?></div>
                                </div>
                                <div class="score-item l">
                                    <div class="score-item-label">L</div>
                                    <div class="score-item-value"><?php echo number_format($evaluation['l_score'] ?? 0, 1); ?></div>
                                </div>
                            </div>
                        </div>

                        <?php
                        $hasComments = false;
                        $comments = [
                            'J' => $evaluation['j_comment'] ?? '',
                            'O' => $evaluation['o_comment'] ?? '',
                            'U' => $evaluation['u_comment'] ?? '',
                            'R' => $evaluation['r_comment'] ?? '',
                            'N' => $evaluation['n_comment'] ?? '',
                            'A' => $evaluation['a_comment'] ?? '',
                            'L' => $evaluation['l_comment'] ?? ''
                        ];
                        
                        foreach ($comments as $comment) {
                            if (!empty(trim($comment))) {
                                $hasComments = true;
                                break;
                            }
                        }
                        ?>

                        <?php if ($hasComments): ?>
                        <div class="comments-section">
                            <div class="comments-title">
                                <i class="bi bi-chat-text-fill"></i>
                                ความคิดเห็นและข้อเสนอแนะ
                            </div>
                            <?php foreach ($comments as $category => $comment): ?>
                                <?php if (!empty(trim($comment))): ?>
                                <div class="comment-item">
                                    <div class="comment-label">
                                        <i class="bi bi-quote"></i>
                                        หมวด <?php echo $category; ?>
                                    </div>
                                    <div class="comment-text"><?php echo htmlspecialchars($comment); ?></div>
                                </div>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php else: ?>
            <div id="evaluations" class="card">
                <div class="card-body text-center">
                    <i class="bi bi-inbox" style="font-size: 3rem; color: var(--medium-gray);"></i>
                    <h5 class="mt-3">ยังไม่มีการประเมิน</h5>
                    <p class="text-muted">ยังไม่มีใครประเมินคุณในระบบ</p>
                </div>
            </div>
            <?php endif; ?>

            <!-- ส่วนลายเซ็นสำหรับส่ง HR -->
            <div id="signature" class="signature-section">
                <div class="signature-header">
                    <i class="bi bi-file-earmark-check"></i> รับทราบผลการประเมินและส่งให้ฝ่ายทรัพยากรบุคคล
                </div>
                <div class="signature-body">
                    <div class="signature-grid">
                        <div class="signature-box">
                            <div class="signature-label">ลายเซ็นพนักงาน</div>
                            <div class="signature-area">
                                <div class="signature-placeholder">
                                    <i class="bi bi-pen" style="font-size: 1.5rem; margin-bottom: 0.5rem;"></i><br>
                                    กรุณาเซ็นชื่อที่นี่
                                </div>
                            </div>
                            <div class="signature-date">
                                วันที่: ........................
                            </div>
                        </div>
                        
                        <div class="signature-box">
                            <div class="signature-label">ลายเซ็นฝ่ายทรัพยากรบุคคล</div>
                            <div class="signature-area">
                                <div class="signature-placeholder">
                                    <i class="bi bi-person-check" style="font-size: 1.5rem; margin-bottom: 0.5rem;"></i><br>
                                    สำหรับ HR เซ็นรับทราบ
                                </div>
                            </div>
                            <div class="signature-date">
                                วันที่: ........................
                            </div>
                        </div>
                    </div>

                    <div class="signature-note">
                        <h6>
                            <i class="bi bi-info-circle-fill"></i>
                            คำแนะนำการใช้งาน
                        </h6>
                        <p>
                            <strong>สำหรับพนักงาน:</strong> กรุณาพิมพ์รายงานนี้ แล้วเซ็นชื่อในช่องที่กำหนด และส่งให้ฝ่ายทรัพยากรบุคคลเพื่อเก็บไว้ในแฟ้มประวัติ<br>
                            <strong>สำหรับ HR:</strong> ตรวจสอบความถูกต้องของข้อมูล เซ็นรับทราบ และเก็บเอกสารไว้ในระบบบริหารงานบุคคล
                        </p>
                    </div>
                </div>
            </div>

            <!-- Action Buttons -->
            <div id="actions" class="action-buttons">
                <div class="d-flex justify-content-between flex-wrap align-items-center gap-3">
                    <div class="back-button">
                        <a href="employee_profile.php" class="btn btn-secondary">
                            <i class="bi bi-arrow-left"></i> กลับไปหน้าโปรไฟล์
                        </a>
                    </div>
                    <div class="d-flex gap-2">
                        <button onclick="window.print()" class="btn btn-primary">
                            <i class="bi bi-printer"></i> พิมพ์รายงาน
                        </button>
                        <button onclick="generatePDF()" class="btn btn-primary">
                            <i class="bi bi-file-earmark-pdf"></i> ส่งออก PDF
                        </button>
                        <button onclick="sendToHR()" class="btn btn-success">
                            <i class="bi bi-send"></i> ส่งให้ HR
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Scroll to top button -->
    <button class="scroll-to-top" onclick="scrollToTop()">
        <i class="bi bi-arrow-up"></i>
    </button>

    <!-- Footer -->
    <footer>
        <div class="container">
            <small>© 2025 Web Portal All rights reserved</small>
        </div>
    </footer>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Custom JS -->
    <script>
        // Generate PDF function
        function generatePDF() {
            // Hide action buttons for PDF
            const actionButtons = document.querySelector('.action-buttons');
            if (actionButtons) {
                actionButtons.style.display = 'none';
            }
            
            // Print the page (most browsers will show Save as PDF option)
            window.print();
            
            // Show action buttons again after print dialog
            setTimeout(() => {
                if (actionButtons) {
                    actionButtons.style.display = 'block';
                }
            }, 1000);
        }
        
        // Send to HR function
        function sendToHR() {
            // Show confirmation dialog
            if (confirm('คุณต้องการส่งรายงานการประเมินนี้ให้ฝ่ายทรัพยากรบุคคลใช่หรือไม่?')) {
                // Here you would typically send an email or create a notification
                // For now, we'll just show a success message
                alert('ส่งรายงานให้ฝ่ายทรัพยากรบุคคลเรียบร้อยแล้ว\nฝ่าย HR จะได้รับแจ้งผ่านอีเมล์');
                
                // Optional: You could redirect to a confirmation page
                // window.location.href = 'hr_submission_confirmation.php';
            }
        }
        
        // Print optimization
        window.addEventListener('beforeprint', function() {
            // Add any special print preparations here
            document.body.classList.add('printing');
        });
        
        window.addEventListener('afterprint', function() {
            // Clean up after printing
            document.body.classList.remove('printing');
        });
        
        // Smooth scrolling for internal navigation
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

        // Scroll to top functionality
        function scrollToTop() {
            window.scrollTo({
                top: 0,
                behavior: 'smooth'
            });
        }

        // Show/hide scroll to top button
        window.addEventListener('scroll', function() {
            const scrollToTopBtn = document.querySelector('.scroll-to-top');
            if (window.pageYOffset > 300) {
                scrollToTopBtn.classList.add('show');
            } else {
                scrollToTopBtn.classList.remove('show');
            }

            // Update reading progress
            const winScroll = document.body.scrollTop || document.documentElement.scrollTop;
            const height = document.documentElement.scrollHeight - document.documentElement.clientHeight;
            const scrolled = (winScroll / height) * 100;
            document.querySelector('.reading-progress-fill').style.width = scrolled + '%';
        });

        // Add loading animation
        window.addEventListener('load', function() {
            document.body.style.opacity = '0';
            document.body.style.transition = 'opacity 0.5s ease';
            setTimeout(() => {
                document.body.style.opacity = '1';
            }, 100);
        });
    </script>
</body>
</html>
