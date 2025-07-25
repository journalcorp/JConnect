<?php
header('Content-Type: text/html; charset=UTF-8');
session_start();
include_once '../includes/header.php';
include_once '../includes/menu.php';
include_once '../config.php';

// ตรวจสอบสิทธิ์การเข้าถึง - เฉพาะ IT เท่านั้น
if (!isset($_SESSION['division']) || $_SESSION['division'] !== 'JD001') {
    echo '<div style="text-align:center;margin-top:50px;"><h3>คุณไม่มีสิทธิ์เข้าถึงหน้านี้</h3><p>เฉพาะแผนก IT เท่านั้น</p></div>';
    exit;
}

// สร้างการเชื่อมต่อฐานข้อมูล
$conn = dbConnect();

// ประมวลผลแบบฟอร์ม
$success_message = '';
$error_message = '';

// ดึงข้อมูลแผนกทั้งหมด
function getDepartments($conn) {
    $departments = [];
    try {
        if (is_resource($conn)) {
            $sql = "SELECT DeptID, DeptName FROM dbo.dept ORDER BY DeptName";
            $result = sqlsrv_query($conn, $sql);
            if ($result) {
                while ($row = sqlsrv_fetch_array($result, SQLSRV_FETCH_ASSOC)) {
                    $departments[] = $row;
                }
            }
        } else if (is_object($conn)) {
            $sql = "SELECT DeptID, DeptName FROM dept ORDER BY DeptName";
            $stmt = $conn->prepare($sql);
            $stmt->execute();
            $departments = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    } catch (Exception $e) {
        error_log("Error fetching departments: " . $e->getMessage());
    }
    return $departments;
}

// ดึงข้อมูลพนักงานทั้งหมด
function getEmployees($conn) {
    $employees = [];
    try {
        if (is_resource($conn)) {
            $sql = "SELECT employee_id, first_name_th, last_name_th, position_th, division FROM dbo.Employees ORDER BY first_name_th";
            $result = sqlsrv_query($conn, $sql);
            if ($result) {
                while ($row = sqlsrv_fetch_array($result, SQLSRV_FETCH_ASSOC)) {
                    $employees[] = $row;
                }
            }
        } else if (is_object($conn)) {
            $sql = "SELECT employee_id, first_name_th, last_name_th, position_th, division FROM Employees ORDER BY first_name_th";
            $stmt = $conn->prepare($sql);
            $stmt->execute();
            $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    } catch (Exception $e) {
        error_log("Error fetching employees: " . $e->getMessage());
    }
    return $employees;
}

// ดึงข้อมูลโครงสร้างการอนุมัติ
function getApprovalStructures($conn) {
    $structures = [];
    try {
        if (is_resource($conn)) {
            $sql = "
                SELECT 
                    a.ApprovalID,
                    a.ApprovalName,
                    a.DeptID,
                    a.ApprovalLevel1,
                    a.ApprovalLevel2,
                    a.ApprovalLevel3,
                    a.level1_amount_limit,
                    a.level2_amount_limit,
                    a.level3_amount_limit,
                    d.DeptName,
                    e1.first_name_th as level1_first, e1.last_name_th as level1_last, e1.position_th as level1_position,
                    e2.first_name_th as level2_first, e2.last_name_th as level2_last, e2.position_th as level2_position,
                    e3.first_name_th as level3_first, e3.last_name_th as level3_last, e3.position_th as level3_position
                FROM dbo.approvals a
                LEFT JOIN dbo.dept d ON a.DeptID = d.DeptID
                LEFT JOIN dbo.Employees e1 ON a.ApprovalLevel1 = e1.employee_id
                LEFT JOIN dbo.Employees e2 ON a.ApprovalLevel2 = e2.employee_id
                LEFT JOIN dbo.Employees e3 ON a.ApprovalLevel3 = e3.employee_id
                ORDER BY a.ApprovalID
            ";
            $result = sqlsrv_query($conn, $sql);
            if ($result) {
                while ($row = sqlsrv_fetch_array($result, SQLSRV_FETCH_ASSOC)) {
                    $structures[] = $row;
                }
            }
        } else if (is_object($conn)) {
            $sql = "
                SELECT 
                    a.ApprovalID,
                    a.ApprovalName,
                    a.DeptID,
                    a.ApprovalLevel1,
                    a.ApprovalLevel2,
                    a.ApprovalLevel3,
                    a.level1_amount_limit,
                    a.level2_amount_limit,
                    a.level3_amount_limit,
                    d.DeptName,
                    e1.first_name_th as level1_first, e1.last_name_th as level1_last, e1.position_th as level1_position,
                    e2.first_name_th as level2_first, e2.last_name_th as level2_last, e2.position_th as level2_position,
                    e3.first_name_th as level3_first, e3.last_name_th as level3_last, e3.position_th as level3_position
                FROM approvals a
                LEFT JOIN dept d ON a.DeptID = d.DeptID
                LEFT JOIN Employees e1 ON a.ApprovalLevel1 = e1.employee_id
                LEFT JOIN Employees e2 ON a.ApprovalLevel2 = e2.employee_id
                LEFT JOIN Employees e3 ON a.ApprovalLevel3 = e3.employee_id
                ORDER BY a.ApprovalID
            ";
            $stmt = $conn->prepare($sql);
            $stmt->execute();
            $structures = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    } catch (Exception $e) {
        error_log("Error fetching approval structures: " . $e->getMessage());
    }
    return $structures;
}

// ประมวลผลการส่งแบบฟอร์ม
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        $action = $_POST['action'];
        
        try {
            if ($action === 'create') {
                // สร้างโครงสร้างการอนุมัติใหม่
                $approval_name = $_POST['approval_name'] ?? '';
                $dept_id = $_POST['dept_id'] ?? '';
                $level1 = $_POST['level1'] ?? null;
                $level2 = $_POST['level2'] ?? null;
                $level3 = $_POST['level3'] ?? null;
                $level1_amount = $_POST['level1_amount'] ?? null;
                $level2_amount = $_POST['level2_amount'] ?? null;
                $level3_amount = $_POST['level3_amount'] ?? null;
                
                // แปลงค่าว่างเป็น null
                $level1 = empty($level1) ? null : $level1;
                $level2 = empty($level2) ? null : $level2;
                $level3 = empty($level3) ? null : $level3;
                $level1_amount = empty($level1_amount) ? null : (float)$level1_amount;
                $level2_amount = empty($level2_amount) ? null : (float)$level2_amount;
                $level3_amount = empty($level3_amount) ? null : (float)$level3_amount;
                
                if (!empty($approval_name) && !empty($dept_id)) {
                    if (is_resource($conn)) {
                        $sql = "INSERT INTO dbo.approvals (ApprovalName, DeptID, ApprovalLevel1, ApprovalLevel2, ApprovalLevel3, level1_amount_limit, level2_amount_limit, level3_amount_limit) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
                        $params = [$approval_name, $dept_id, $level1, $level2, $level3, $level1_amount, $level2_amount, $level3_amount];
                        $result = sqlsrv_query($conn, $sql, $params);
                        
                        if ($result) {
                            $success_message = "สร้างโครงสร้างการอนุมัติเรียบร้อยแล้ว";
                        } else {
                            $errors = sqlsrv_errors();
                            throw new Exception("ไม่สามารถสร้างได้: " . print_r($errors, true));
                        }
                    } else if (is_object($conn)) {
                        $sql = "INSERT INTO approvals (ApprovalName, DeptID, ApprovalLevel1, ApprovalLevel2, ApprovalLevel3, level1_amount_limit, level2_amount_limit, level3_amount_limit) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
                        $stmt = $conn->prepare($sql);
                        $result = $stmt->execute([$approval_name, $dept_id, $level1, $level2, $level3, $level1_amount, $level2_amount, $level3_amount]);
                        
                        if ($result) {
                            $success_message = "สร้างโครงสร้างการอนุมัติเรียบร้อยแล้ว";
                        } else {
                            throw new Exception("ไม่สามารถสร้างได้");
                        }
                    }
                } else {
                    $error_message = "กรุณากรอกชื่อโครงสร้างและเลือกแผนก";
                }
                
            } elseif ($action === 'update') {
                // อัพเดทโครงสร้างการอนุมัติ
                $approval_id = $_POST['approval_id'] ?? '';
                $approval_name = $_POST['approval_name'] ?? '';
                $dept_id = $_POST['dept_id'] ?? '';
                $level1 = $_POST['level1'] ?? null;
                $level2 = $_POST['level2'] ?? null;
                $level3 = $_POST['level3'] ?? null;
                $level1_amount = $_POST['level1_amount'] ?? null;
                $level2_amount = $_POST['level2_amount'] ?? null;
                $level3_amount = $_POST['level3_amount'] ?? null;
                
                // แปลงค่าว่างเป็น null
                $level1 = empty($level1) ? null : $level1;
                $level2 = empty($level2) ? null : $level2;
                $level3 = empty($level3) ? null : $level3;
                $level1_amount = empty($level1_amount) ? null : (float)$level1_amount;
                $level2_amount = empty($level2_amount) ? null : (float)$level2_amount;
                $level3_amount = empty($level3_amount) ? null : (float)$level3_amount;
                
                if (!empty($approval_id) && !empty($approval_name)) {
                    if (is_resource($conn)) {
                        $sql = "UPDATE dbo.approvals SET ApprovalName = ?, DeptID = ?, ApprovalLevel1 = ?, ApprovalLevel2 = ?, ApprovalLevel3 = ?, level1_amount_limit = ?, level2_amount_limit = ?, level3_amount_limit = ? WHERE ApprovalID = ?";
                        $params = [$approval_name, $dept_id, $level1, $level2, $level3, $level1_amount, $level2_amount, $level3_amount, $approval_id];
                        $result = sqlsrv_query($conn, $sql, $params);
                        
                        if ($result) {
                            $success_message = "อัพเดทโครงสร้างการอนุมัติเรียบร้อยแล้ว";
                        } else {
                            $errors = sqlsrv_errors();
                            throw new Exception("ไม่สามารถอัพเดทได้: " . print_r($errors, true));
                        }
                    } else if (is_object($conn)) {
                        $sql = "UPDATE approvals SET ApprovalName = ?, DeptID = ?, ApprovalLevel1 = ?, ApprovalLevel2 = ?, ApprovalLevel3 = ?, level1_amount_limit = ?, level2_amount_limit = ?, level3_amount_limit = ? WHERE ApprovalID = ?";
                        $stmt = $conn->prepare($sql);
                        $result = $stmt->execute([$approval_name, $dept_id, $level1, $level2, $level3, $level1_amount, $level2_amount, $level3_amount, $approval_id]);
                        
                        if ($result) {
                            $success_message = "อัพเดทโครงสร้างการอนุมัติเรียบร้อยแล้ว";
                        } else {
                            throw new Exception("ไม่สามารถอัพเดทได้");
                        }
                    }
                } else {
                    $error_message = "ข้อมูลไม่ครบถ้วน";
                }
                
            } elseif ($action === 'delete') {
                // ลบโครงสร้างการอนุมัติ
                $approval_id = $_POST['approval_id'] ?? '';
                
                if (!empty($approval_id)) {
                    if (is_resource($conn)) {
                        $sql = "DELETE FROM dbo.approvals WHERE ApprovalID = ?";
                        $params = [$approval_id];
                        $result = sqlsrv_query($conn, $sql, $params);
                        
                        if ($result) {
                            $success_message = "ลบโครงสร้างการอนุมัติเรียบร้อยแล้ว";
                        } else {
                            $errors = sqlsrv_errors();
                            throw new Exception("ไม่สามารถลบได้: " . print_r($errors, true));
                        }
                    } else if (is_object($conn)) {
                        $sql = "DELETE FROM approvals WHERE ApprovalID = ?";
                        $stmt = $conn->prepare($sql);
                        $result = $stmt->execute([$approval_id]);
                        
                        if ($result) {
                            $success_message = "ลบโครงสร้างการอนุมัติเรียบร้อยแล้ว";
                        } else {
                            throw new Exception("ไม่สามารถลบได้");
                        }
                    }
                } else {
                    $error_message = "ไม่พบรหัสโครงสร้างการอนุมัติ";
                }
            }
        } catch (Exception $e) {
            $error_message = "เกิดข้อผิดพลาด: " . $e->getMessage();
        }
    }
}

// ดึงข้อมูลสำหรับแสดงผล
$departments = getDepartments($conn);
$employees = getEmployees($conn);
$approval_structures = getApprovalStructures($conn);
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>จัดการสิทธิการอนุมัติ - IT Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --success-gradient: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
            --warning-gradient: linear-gradient(135deg, #f7971e 0%, #ffd200 100%);
            --danger-gradient: linear-gradient(135deg, #ff6b6b 0%, #ee5a6f 100%);
            --info-gradient: linear-gradient(135deg, #74b9ff 0%, #0984e3 100%);
            --light-gradient: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            --shadow-light: 0 5px 15px rgba(0, 0, 0, 0.08);
            --shadow-medium: 0 10px 30px rgba(0, 0, 0, 0.12);
            --shadow-hover: 0 15px 40px rgba(102, 126, 234, 0.15);
            --border-radius: 15px;
            --border-radius-large: 20px;
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        * {
            box-sizing: border-box;
        }

        body {
            font-family: 'Prompt', sans-serif;
            background-color: #f8f9fa;
            min-height: 100vh;
            margin: 0;
            padding: 0;
        }

        .admin-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 2rem;
        }

        .card {
            border-radius: var(--border-radius-large);
            box-shadow: var(--shadow-medium);
            border: none;
            background: white;
            margin-bottom: 2rem;
            overflow: hidden;
            transition: var(--transition);
        }

        .card:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-hover);
        }

        .card-header {
            background: var(--primary-gradient);
            color: white;
            border-radius: var(--border-radius-large) var(--border-radius-large) 0 0 !important;
            padding: 2.5rem;
            border: none;
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
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="grain" width="100" height="100" patternUnits="userSpaceOnUse"><circle cx="25" cy="25" r="1" fill="rgba(255,255,255,0.1)"/><circle cx="75" cy="75" r="1" fill="rgba(255,255,255,0.1)"/><circle cx="50" cy="10" r="0.5" fill="rgba(255,255,255,0.05)"/></pattern></defs><rect width="100" height="100" fill="url(%23grain)"/></svg>');
            pointer-events: none;
        }

        .admin-title {
            display: flex;
            align-items: center;
            margin-bottom: 0;
            position: relative;
            z-index: 1;
        }

        .admin-title i {
            font-size: 2.5rem;
            margin-right: 1rem;
            background: linear-gradient(45deg, #fff, #f0f0f0);
            background-clip: text;
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            filter: drop-shadow(0 2px 4px rgba(0,0,0,0.2));
        }

        .admin-title h2 {
            margin: 0;
            font-weight: 700;
            font-size: 1.8rem;
        }

        .card-body {
            padding: 2.5rem;
        }

        .form-section {
            background: linear-gradient(135deg, #f8f9ff 0%, #f0f2ff 100%);
            border-radius: var(--border-radius);
            padding: 2rem;
            margin-bottom: 2rem;
            border: 1px solid rgba(102, 126, 234, 0.1);
        }

        .form-label {
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 0.75rem;
            display: flex;
            align-items: center;
            font-size: 0.95rem;
        }

        .form-label i {
            margin-right: 0.5rem;
            color: #667eea;
            font-size: 1.1rem;
        }

        .form-control, .form-select {
            border-radius: var(--border-radius);
            border: 2px solid #e9ecef;
            padding: 15px 18px;
            transition: var(--transition);
            font-size: 0.95rem;
            background: white;
            box-shadow: 0 2px 4px rgba(0,0,0,0.02);
        }

        .form-control:focus, .form-select:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1), 0 4px 12px rgba(102, 126, 234, 0.15);
            transform: translateY(-1px);
        }

        .input-group {
            position: relative;
        }

        .input-group .form-control {
            padding-left: 45px;
        }

        .input-group-text {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: #667eea;
            z-index: 5;
            font-size: 1.1rem;
        }

        .btn {
            border-radius: var(--border-radius);
            padding: 12px 24px;
            font-weight: 600;
            transition: var(--transition);
            border: none;
            position: relative;
            overflow: hidden;
            text-transform: none;
            font-size: 0.95rem;
        }

        .btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transition: left 0.5s;
        }

        .btn:hover::before {
            left: 100%;
        }

        .btn-primary {
            background: var(--primary-gradient);
            color: white;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.4);
        }

        .btn-success {
            background: var(--success-gradient);
            color: white;
            box-shadow: 0 4px 15px rgba(17, 153, 142, 0.3);
        }

        .btn-warning {
            background: var(--warning-gradient);
            color: #333;
            box-shadow: 0 4px 15px rgba(247, 151, 30, 0.3);
        }

        .btn-danger {
            background: var(--danger-gradient);
            color: white;
            box-shadow: 0 4px 15px rgba(255, 107, 107, 0.3);
        }

        .btn-info {
            background: var(--info-gradient);
            color: white;
            box-shadow: 0 4px 15px rgba(116, 185, 255, 0.3);
        }

        .btn-sm {
            padding: 8px 16px;
            font-size: 0.85rem;
        }

        .alert {
            border-radius: var(--border-radius);
            border: none;
            padding: 1.25rem 1.75rem;
            margin-bottom: 1.5rem;
            box-shadow: var(--shadow-light);
            position: relative;
            overflow: hidden;
        }

        .alert::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            bottom: 0;
            width: 4px;
            background: currentColor;
        }

        .alert-success {
            background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%);
            color: #155724;
        }

        .alert-danger {
            background: linear-gradient(135deg, #f8d7da 0%, #f5c6cb 100%);
            color: #721c24;
        }

        .alert-info {
            background: linear-gradient(135deg, #d1ecf1 0%, #bee5eb 100%);
            color: #0c5460;
        }

        .section-title {
            color: #667eea;
            font-weight: 700;
            margin-bottom: 2rem;
            display: flex;
            align-items: center;
            font-size: 1.4rem;
            position: relative;
            padding-bottom: 1rem;
        }

        .section-title::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 60px;
            height: 3px;
            background: var(--primary-gradient);
            border-radius: 2px;
        }

        .section-title i {
            margin-right: 0.75rem;
            font-size: 1.5rem;
            background: var(--primary-gradient);
            background-clip: text;
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .structure-card {
            border: 2px solid #f1f3f4;
            border-radius: var(--border-radius);
            padding: 2rem;
            margin-bottom: 1.5rem;
            transition: var(--transition);
            background: white;
            position: relative;
            overflow: hidden;
        }

        .structure-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 4px;
            height: 100%;
            background: var(--primary-gradient);
            transform: scaleY(0);
            transition: var(--transition);
        }

        .structure-card:hover {
            border-color: #667eea;
            box-shadow: var(--shadow-hover);
            transform: translateY(-3px);
        }

        .structure-card:hover::before {
            transform: scaleY(1);
        }

        .approval-level {
            background: linear-gradient(135deg, #e8f5e8 0%, #f0f8f0 100%);
            border-left: 4px solid #28a745;
            padding: 1rem;
            border-radius: 0 var(--border-radius) var(--border-radius) 0;
            margin-bottom: 0.75rem;
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 0.75rem;
            transition: var(--transition);
            box-shadow: var(--shadow-light);
        }

        .approval-level:hover {
            transform: translateX(5px);
            box-shadow: var(--shadow-medium);
        }

        .employee-badge {
            background: linear-gradient(135deg, #e3f2fd 0%, #f3e5f5 100%);
            border: 1px solid rgba(102, 126, 234, 0.2);
            border-radius: 25px;
            padding: 0.6rem 1.2rem;
            display: inline-flex;
            align-items: center;
            margin: 0.25rem;
            font-size: 0.9rem;
            font-weight: 500;
            transition: var(--transition);
            box-shadow: var(--shadow-light);
        }

        .employee-badge:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-medium);
        }

        .employee-badge i {
            margin-right: 0.5rem;
            color: #667eea;
        }

        .action-buttons {
            display: flex;
            gap: 0.75rem;
            flex-wrap: wrap;
            align-items: center;
        }

        .badge {
            font-size: 0.8rem;
            padding: 0.5em 1em;
            border-radius: 20px;
            font-weight: 600;
            box-shadow: var(--shadow-light);
        }

        .badge.bg-primary {
            background: var(--primary-gradient) !important;
        }

        .badge.bg-success {
            background: var(--success-gradient) !important;
        }

        .badge.bg-warning {
            background: var(--warning-gradient) !important;
            color: #333 !important;
        }

        .badge.bg-secondary {
            background: linear-gradient(135deg, #6c757d 0%, #5a6268 100%) !important;
        }

        .modal-content {
            border-radius: var(--border-radius-large);
            border: none;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }

        .modal-header {
            background: var(--primary-gradient);
            color: white;
            border-radius: var(--border-radius-large) var(--border-radius-large) 0 0;
            border: none;
            padding: 1.5rem 2rem;
        }

        .modal-title {
            font-weight: 700;
        }

        .btn-close {
            filter: invert(1);
            opacity: 0.8;
        }

        .modal-body {
            padding: 2rem;
        }

        .modal-footer {
            border: none;
            padding: 1.5rem 2rem;
            background: #f8f9fa;
        }

        .loading-spinner {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid rgba(255,255,255,.3);
            border-radius: 50%;
            border-top-color: #fff;
            animation: spin 1s ease-in-out infinite;
            margin-right: 10px;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        .form-floating {
            position: relative;
        }

        .form-floating > .form-control,
        .form-floating > .form-select {
            height: calc(3.5rem + 2px);
            padding: 1rem 0.75rem;
        }

        .form-floating > label {
            position: absolute;
            top: 0;
            left: 0;
            height: 100%;
            padding: 1rem 0.75rem;
            pointer-events: none;
            border: 1px solid transparent;
            transform-origin: 0 0;
            transition: opacity .1s ease-in-out,transform .1s ease-in-out;
        }

        @media (max-width: 768px) {
            .admin-container {
                padding: 1rem;
            }
            
            .card-body {
                padding: 1.5rem;
            }
            
            .action-buttons {
                justify-content: center;
            }
            
            .structure-card {
                padding: 1.5rem 1rem;
            }
            
            .btn {
                width: 100%;
                margin-bottom: 0.5rem;
            }
            
            .approval-level {
                flex-direction: column;
                align-items: flex-start;
            }
        }

        .fade-in {
            animation: fadeIn 0.5s ease-in;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .slide-in {
            animation: slideIn 0.3s ease-out;
        }

        @keyframes slideIn {
            from { transform: translateX(-20px); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }

        /* Approval Conditions Styling */
        .approval-conditions {
            background: rgba(255, 255, 255, 0.3);
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 1rem;
        }

        .condition-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.2);
        }

        .condition-item:last-child {
            border-bottom: none;
        }

        .condition-item strong {
            min-width: 200px;
            font-size: 0.9rem;
        }

        .condition-item .text-dark {
            flex: 1;
            font-size: 0.9rem;
        }
    </style>
</head>
<body>
    <div class="admin-container fade-in">
        <!-- Header -->
        <div class="card">
            <div class="card-header">
                <div class="admin-title">
                    <i class="bi bi-shield-lock"></i>
                    <div>
                        <h2>จัดการสิทธิการอนุมัติ</h2>
                        <p class="mb-0 mt-2 opacity-75">IT Administration - จัดการโครงสร้างการอนุมัติสำหรับระบบเบิกจ่าย</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Success/Error Messages -->
        <?php if ($success_message): ?>
            <div class="alert alert-success slide-in" role="alert">
                <i class="bi bi-check-circle me-2"></i><?php echo $success_message; ?>
            </div>
        <?php endif; ?>

        <?php if ($error_message): ?>
            <div class="alert alert-danger slide-in" role="alert">
                <i class="bi bi-exclamation-triangle me-2"></i><?php echo $error_message; ?>
            </div>
        <?php endif; ?>

        <!-- Create New Approval Structure -->
        <div class="card fade-in">
            <div class="card-body">
                <h4 class="section-title">
                    <i class="bi bi-plus-circle"></i>
                    สร้างโครงสร้างการอนุมัติใหม่
                </h4>
                
                <div class="form-section">
                    <form method="POST" action="" id="createForm">
                        <input type="hidden" name="action" value="create">
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-4">
                                    <label for="approval_name" class="form-label">
                                        <i class="bi bi-tag"></i>
                                        ชื่อโครงสร้างการอนุมัติ <span class="text-danger">*</span>
                                    </label>
                                    <input type="text" class="form-control" id="approval_name" name="approval_name" required
                                           placeholder="เช่น การอนุมัติแผนก IT">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-4">
                                    <label for="dept_id" class="form-label">
                                        <i class="bi bi-building"></i>
                                        แผนก <span class="text-danger">*</span>
                                    </label>
                                    <select class="form-select" id="dept_id" name="dept_id" required>
                                        <option value="">เลือกแผนก</option>
                                        <?php foreach ($departments as $dept): ?>
                                            <option value="<?php echo htmlspecialchars($dept['DeptID']); ?>">
                                                <?php echo htmlspecialchars($dept['DeptName']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-4">
                                <div class="mb-4">
                                    <label for="level1" class="form-label">
                                        <i class="bi bi-person-badge"></i>
                                        ผู้อนุมัติระดับ 1
                                    </label>
                                    <select class="form-select" id="level1" name="level1">
                                        <option value="">ไม่มี</option>
                                        <?php foreach ($employees as $emp): ?>
                                            <option value="<?php echo htmlspecialchars($emp['employee_id']); ?>">
                                                <?php echo htmlspecialchars($emp['first_name_th'] . ' ' . $emp['last_name_th']); ?>
                                                (<?php echo htmlspecialchars($emp['position_th']); ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="mb-4">
                                    <label for="level1_amount" class="form-label">
                                        <i class="bi bi-currency-dollar"></i>
                                        วงเงินอนุมัติสูงสุด (บาท)
                                    </label>
                                    <input type="number" class="form-control" id="level1_amount" name="level1_amount" 
                                           placeholder="เช่น 10,000" step="0.01" min="0">
                                    <small class="text-muted">ระบุ 0 หรือเว้นว่างหากไม่จำกัดวงเงิน</small>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-4">
                                    <label for="level2" class="form-label">
                                        <i class="bi bi-person-badge"></i>
                                        ผู้อนุมัติระดับ 2
                                    </label>
                                    <select class="form-select" id="level2" name="level2">
                                        <option value="">ไม่มี</option>
                                        <?php foreach ($employees as $emp): ?>
                                            <option value="<?php echo htmlspecialchars($emp['employee_id']); ?>">
                                                <?php echo htmlspecialchars($emp['first_name_th'] . ' ' . $emp['last_name_th']); ?>
                                                (<?php echo htmlspecialchars($emp['position_th']); ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="mb-4">
                                    <label for="level2_amount" class="form-label">
                                        <i class="bi bi-currency-dollar"></i>
                                        วงเงินอนุมัติสูงสุด (บาท)
                                    </label>
                                    <input type="number" class="form-control" id="level2_amount" name="level2_amount" 
                                           placeholder="เช่น 50,000" step="0.01" min="0">
                                    <small class="text-muted">ระบุ 0 หรือเว้นว่างหากไม่จำกัดวงเงิน</small>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-4">
                                    <label for="level3" class="form-label">
                                        <i class="bi bi-person-badge"></i>
                                        ผู้อนุมัติระดับ 3
                                    </label>
                                    <select class="form-select" id="level3" name="level3">
                                        <option value="">ไม่มี</option>
                                        <?php foreach ($employees as $emp): ?>
                                            <option value="<?php echo htmlspecialchars($emp['employee_id']); ?>">
                                                <?php echo htmlspecialchars($emp['first_name_th'] . ' ' . $emp['last_name_th']); ?>
                                                (<?php echo htmlspecialchars($emp['position_th']); ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="mb-4">
                                    <label for="level3_amount" class="form-label">
                                        <i class="bi bi-currency-dollar"></i>
                                        วงเงินอนุมัติสูงสุด (บาท)
                                    </label>
                                    <input type="number" class="form-control" id="level3_amount" name="level3_amount" 
                                           placeholder="เช่น 100,000" step="0.01" min="0">
                                    <small class="text-muted">ระบุ 0 หรือเว้นว่างหากไม่จำกัดวงเงิน</small>
                                </div>
                            </div>
                        </div>

                        <div class="text-center">
                            <button type="submit" class="btn btn-primary btn-lg">
                                <i class="bi bi-plus-circle me-2"></i>
                                สร้างโครงสร้างการอนุมัติ
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Existing Approval Structures -->
        <div class="card fade-in">
            <div class="card-body">
                <h4 class="section-title">
                    <i class="bi bi-list-check"></i>
                    โครงสร้างการอนุมัติที่มีอยู่
                </h4>

                <?php if (empty($approval_structures)): ?>
                    <div class="alert alert-info slide-in">
                        <i class="bi bi-info-circle me-2"></i>
                        ยังไม่มีโครงสร้างการอนุมัติในระบบ กรุณาสร้างโครงสร้างใหม่
                    </div>
                <?php else: ?>
                    <div class="row">
                        <?php foreach ($approval_structures as $index => $structure): ?>
                            <div class="col-lg-6 mb-4">
                                <div class="structure-card slide-in" style="animation-delay: <?php echo $index * 0.1; ?>s">
                                    <div class="d-flex justify-content-between align-items-start mb-3">
                                        <div class="flex-grow-1">
                                            <h5 class="text-primary mb-2 d-flex align-items-center">
                                                <i class="bi bi-diagram-3 me-2"></i>
                                                <?php echo htmlspecialchars($structure['ApprovalName']); ?>
                                            </h5>
                                            <div class="d-flex gap-3 mb-3">
                                                <span class="badge bg-primary">
                                                    <i class="bi bi-hash me-1"></i>
                                                    รหัส: <?php echo htmlspecialchars($structure['ApprovalID']); ?>
                                                </span>
                                                <span class="badge bg-info">
                                                    <i class="bi bi-building me-1"></i>
                                                    <?php echo htmlspecialchars($structure['DeptName'] ?? 'ไม่ระบุแผนก'); ?>
                                                </span>
                                            </div>
                                        </div>
                                        <div class="action-buttons">
                                            <button type="button" class="btn btn-warning btn-sm" 
                                                    onclick="editStructure(<?php echo htmlspecialchars(json_encode($structure)); ?>)"
                                                    title="แก้ไขโครงสร้าง">
                                                <i class="bi bi-pencil"></i>
                                            </button>
                                            <form method="POST" action="" class="d-inline" 
                                                  onsubmit="return confirm('คุณแน่ใจหรือไม่ที่จะลบโครงสร้างการอนุมัตินี้?\n\nการกระทำนี้ไม่สามารถย้อนกลับได้')">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="approval_id" value="<?php echo htmlspecialchars($structure['ApprovalID']); ?>">
                                                <button type="submit" class="btn btn-danger btn-sm" title="ลบโครงสร้าง">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                    
                                    <div class="approval-levels">
                                        <?php if (!empty($structure['ApprovalLevel1'])): ?>
                                            <div class="approval-level">
                                                <div class="d-flex align-items-center flex-wrap gap-2">
                                                    <strong class="text-success">
                                                        <i class="bi bi-1-circle-fill me-1"></i>
                                                        ระดับ 1:
                                                    </strong>
                                                    <span class="employee-badge">
                                                        <i class="bi bi-person me-1"></i>
                                                        <?php echo htmlspecialchars($structure['level1_first'] . ' ' . $structure['level1_last']); ?>
                                                        <small class="text-muted">(<?php echo htmlspecialchars($structure['level1_position']); ?>)</small>
                                                    </span>
                                                    <?php if (!empty($structure['level1_amount_limit']) && $structure['level1_amount_limit'] > 0): ?>
                                                        <span class="badge bg-success">
                                                            <i class="bi bi-currency-dollar me-1"></i>
                                                            วงเงิน: <?php echo number_format($structure['level1_amount_limit'], 2); ?> บาท
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="badge bg-secondary">
                                                            <i class="bi bi-infinity me-1"></i>
                                                            ไม่จำกัดวงเงิน
                                                        </span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <?php if (!empty($structure['ApprovalLevel2'])): ?>
                                            <div class="approval-level">
                                                <div class="d-flex align-items-center flex-wrap gap-2">
                                                    <strong class="text-warning">
                                                        <i class="bi bi-2-circle-fill me-1"></i>
                                                        ระดับ 2:
                                                    </strong>
                                                    <span class="employee-badge">
                                                        <i class="bi bi-person me-1"></i>
                                                        <?php echo htmlspecialchars($structure['level2_first'] . ' ' . $structure['level2_last']); ?>
                                                        <small class="text-muted">(<?php echo htmlspecialchars($structure['level2_position']); ?>)</small>
                                                    </span>
                                                    <?php if (!empty($structure['level2_amount_limit']) && $structure['level2_amount_limit'] > 0): ?>
                                                        <span class="badge bg-warning">
                                                            <i class="bi bi-currency-dollar me-1"></i>
                                                            วงเงิน: <?php echo number_format($structure['level2_amount_limit'], 2); ?> บาท
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="badge bg-secondary">
                                                            <i class="bi bi-infinity me-1"></i>
                                                            ไม่จำกัดวงเงิน
                                                        </span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <?php if (!empty($structure['ApprovalLevel3'])): ?>
                                            <div class="approval-level">
                                                <div class="d-flex align-items-center flex-wrap gap-2">
                                                    <strong class="text-danger">
                                                        <i class="bi bi-3-circle-fill me-1"></i>
                                                        ระดับ 3:
                                                    </strong>
                                                    <span class="employee-badge">
                                                        <i class="bi bi-person me-1"></i>
                                                        <?php echo htmlspecialchars($structure['level3_first'] . ' ' . $structure['level3_last']); ?>
                                                        <small class="text-muted">(<?php echo htmlspecialchars($structure['level3_position']); ?>)</small>
                                                    </span>
                                                    <?php if (!empty($structure['level3_amount_limit']) && $structure['level3_amount_limit'] > 0): ?>
                                                        <span class="badge bg-danger">
                                                            <i class="bi bi-currency-dollar me-1"></i>
                                                            วงเงิน: <?php echo number_format($structure['level3_amount_limit'], 2); ?> บาท
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="badge bg-secondary">
                                                            <i class="bi bi-infinity me-1"></i>
                                                            ไม่จำกัดวงเงิน
                                                        </span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <?php if (empty($structure['ApprovalLevel1']) && empty($structure['ApprovalLevel2']) && empty($structure['ApprovalLevel3'])): ?>
                                            <div class="alert alert-warning mb-0">
                                                <i class="bi bi-exclamation-triangle me-2"></i>
                                                <strong>ยังไม่มีการกำหนดผู้อนุมัติ</strong>
                                                <br><small>กรุณาแก้ไขเพื่อเพิ่มผู้อนุมัติอย่างน้อย 1 ระดับ</small>
                                            </div>
                                        <?php else: ?>
                                            <!-- เงื่อนไขการอนุมัติตามวงเงิน -->
                                            <div class="alert alert-info mb-0 mt-3">
                                                <h6 class="alert-heading mb-2">
                                                    <i class="bi bi-info-circle me-2"></i>
                                                    เงื่อนไขการอนุมัติตามวงเงิน
                                                </h6>
                                                <div class="approval-conditions">
                                                    <?php
                                                    // รวบรวมข้อมูลการอนุมัติและจัดเรียงตามวงเงิน
                                                    $approvalLevels = [];
                                                    
                                                    if (!empty($structure['ApprovalLevel1'])) {
                                                        $approvalLevels[] = [
                                                            'level' => 1,
                                                            'name' => $structure['level1_first'] . ' ' . $structure['level1_last'],
                                                            'position' => $structure['level1_position'],
                                                            'amount_limit' => $structure['level1_amount_limit'] ?? 0
                                                        ];
                                                    }
                                                    
                                                    if (!empty($structure['ApprovalLevel2'])) {
                                                        $approvalLevels[] = [
                                                            'level' => 2,
                                                            'name' => $structure['level2_first'] . ' ' . $structure['level2_last'],
                                                            'position' => $structure['level2_position'],
                                                            'amount_limit' => $structure['level2_amount_limit'] ?? 0
                                                        ];
                                                    }
                                                    
                                                    if (!empty($structure['ApprovalLevel3'])) {
                                                        $approvalLevels[] = [
                                                            'level' => 3,
                                                            'name' => $structure['level3_first'] . ' ' . $structure['level3_last'],
                                                            'position' => $structure['level3_position'],
                                                            'amount_limit' => $structure['level3_amount_limit'] ?? 0
                                                        ];
                                                    }
                                                    
                                                    // จัดเรียงตามวงเงิน (น้อยไปมาก)
                                                    usort($approvalLevels, function($a, $b) {
                                                        // ถ้าไม่จำกัดวงเงิน (0 หรือ null) ให้อยู่สุดท้าย
                                                        if ($a['amount_limit'] == 0 && $b['amount_limit'] > 0) return 1;
                                                        if ($b['amount_limit'] == 0 && $a['amount_limit'] > 0) return -1;
                                                        if ($a['amount_limit'] == 0 && $b['amount_limit'] == 0) return $a['level'] - $b['level'];
                                                        return $a['amount_limit'] - $b['amount_limit'];
                                                    });
                                                    
                                                    $previousLimit = 0;
                                                    foreach ($approvalLevels as $index => $approver): ?>
                                                        <div class="condition-item mb-2">
                                                            <?php if ($approver['amount_limit'] > 0): ?>
                                                                <strong class="text-primary">
                                                                    <?php if ($previousLimit > 0): ?>
                                                                        วงเงิน <?php echo number_format($previousLimit + 0.01, 2); ?> - <?php echo number_format($approver['amount_limit'], 2); ?> บาท:
                                                                    <?php else: ?>
                                                                        วงเงิน 0 - <?php echo number_format($approver['amount_limit'], 2); ?> บาท:
                                                                    <?php endif; ?>
                                                                </strong>
                                                                <span class="text-dark">
                                                                    อนุมัติโดย <?php echo htmlspecialchars($approver['name']); ?>
                                                                    <small class="text-muted">(<?php echo htmlspecialchars($approver['position']); ?>)</small>
                                                                </span>
                                                                <?php $previousLimit = $approver['amount_limit']; ?>
                                                            <?php else: ?>
                                                                <strong class="text-success">
                                                                    วงเงินเกิน <?php echo $previousLimit > 0 ? number_format($previousLimit, 2) : "0"; ?> บาท:
                                                                </strong>
                                                                <span class="text-dark">
                                                                    อนุมัติโดย <?php echo htmlspecialchars($approver['name']); ?>
                                                                    <small class="text-muted">(<?php echo htmlspecialchars($approver['position']); ?>)</small>
                                                                </span>
                                                            <?php endif; ?>
                                                        </div>
                                                    <?php endforeach; ?>
                                                </div>
                                                <hr class="my-2">
                                                <small class="text-muted">
                                                    <i class="bi bi-lightbulb me-1"></i>
                                                    <strong>วิธีใช้:</strong> เมื่อส่งคำขออนุมัติ ระบบจะตรวจสอบ "รวมเงินทั้งสิ้น" และส่งไปให้ผู้อนุมัติที่เหมาะสมตามวงเงินที่กำหนดไว้
                                                </small>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Edit Modal -->
    <div class="modal fade" id="editModal" tabindex="-1" aria-labelledby="editModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editModalLabel">
                        <i class="bi bi-pencil-square me-2"></i>
                        แก้ไขโครงสร้างการอนุมัติ
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" action="" id="editForm">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="update">
                        <input type="hidden" name="approval_id" id="edit_approval_id">
                        
                        <div class="form-section">
                            <h6 class="text-primary mb-3">
                                <i class="bi bi-info-circle me-2"></i>
                                ข้อมูลพื้นฐาน
                            </h6>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-4">
                                        <label for="edit_approval_name" class="form-label">
                                            <i class="bi bi-tag"></i>
                                            ชื่อโครงสร้างการอนุมัติ
                                        </label>
                                        <input type="text" class="form-control" id="edit_approval_name" name="approval_name" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-4">
                                        <label for="edit_dept_id" class="form-label">
                                            <i class="bi bi-building"></i>
                                            แผนก
                                        </label>
                                        <select class="form-select" id="edit_dept_id" name="dept_id">
                                            <option value="">เลือกแผนก</option>
                                            <?php foreach ($departments as $dept): ?>
                                                <option value="<?php echo htmlspecialchars($dept['DeptID']); ?>">
                                                    <?php echo htmlspecialchars($dept['DeptName']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="form-section">
                            <h6 class="text-primary mb-3">
                                <i class="bi bi-people me-2"></i>
                                ผู้อนุมัติและวงเงิน
                            </h6>
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="card border-success mb-3">
                                        <div class="card-header bg-success text-white">
                                            <h6 class="mb-0">
                                                <i class="bi bi-1-circle-fill me-2"></i>
                                                ระดับ 1
                                            </h6>
                                        </div>
                                        <div class="card-body">
                                            <div class="mb-3">
                                                <label for="edit_level1" class="form-label">ผู้อนุมัติ</label>
                                                <select class="form-select" id="edit_level1" name="level1">
                                                    <option value="">ไม่มี</option>
                                                    <?php foreach ($employees as $emp): ?>
                                                        <option value="<?php echo htmlspecialchars($emp['employee_id']); ?>">
                                                            <?php echo htmlspecialchars($emp['first_name_th'] . ' ' . $emp['last_name_th']); ?>
                                                            (<?php echo htmlspecialchars($emp['position_th']); ?>)
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            <div class="mb-3">
                                                <label for="edit_level1_amount" class="form-label">วงเงิน (บาท)</label>
                                                <input type="number" class="form-control" id="edit_level1_amount" name="level1_amount" 
                                                       placeholder="เช่น 10,000" step="0.01" min="0">
                                                <small class="text-muted">เว้นว่างหากไม่จำกัดวงเงิน</small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="card border-warning mb-3">
                                        <div class="card-header bg-warning text-dark">
                                            <h6 class="mb-0">
                                                <i class="bi bi-2-circle-fill me-2"></i>
                                                ระดับ 2
                                            </h6>
                                        </div>
                                        <div class="card-body">
                                            <div class="mb-3">
                                                <label for="edit_level2" class="form-label">ผู้อนุมัติ</label>
                                                <select class="form-select" id="edit_level2" name="level2">
                                                    <option value="">ไม่มี</option>
                                                    <?php foreach ($employees as $emp): ?>
                                                        <option value="<?php echo htmlspecialchars($emp['employee_id']); ?>">
                                                            <?php echo htmlspecialchars($emp['first_name_th'] . ' ' . $emp['last_name_th']); ?>
                                                            (<?php echo htmlspecialchars($emp['position_th']); ?>)
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            <div class="mb-3">
                                                <label for="edit_level2_amount" class="form-label">วงเงิน (บาท)</label>
                                                <input type="number" class="form-control" id="edit_level2_amount" name="level2_amount" 
                                                       placeholder="เช่น 50,000" step="0.01" min="0">
                                                <small class="text-muted">เว้นว่างหากไม่จำกัดวงเงิน</small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="card border-danger mb-3">
                                        <div class="card-header bg-danger text-white">
                                            <h6 class="mb-0">
                                                <i class="bi bi-3-circle-fill me-2"></i>
                                                ระดับ 3
                                            </h6>
                                        </div>
                                        <div class="card-body">
                                            <div class="mb-3">
                                                <label for="edit_level3" class="form-label">ผู้อนุมัติ</label>
                                                <select class="form-select" id="edit_level3" name="level3">
                                                    <option value="">ไม่มี</option>
                                                    <?php foreach ($employees as $emp): ?>
                                                        <option value="<?php echo htmlspecialchars($emp['employee_id']); ?>">
                                                            <?php echo htmlspecialchars($emp['first_name_th'] . ' ' . $emp['last_name_th']); ?>
                                                            (<?php echo htmlspecialchars($emp['position_th']); ?>)
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            <div class="mb-3">
                                                <label for="edit_level3_amount" class="form-label">วงเงิน (บาท)</label>
                                                <input type="number" class="form-control" id="edit_level3_amount" name="level3_amount" 
                                                       placeholder="เช่น 100,000" step="0.01" min="0">
                                                <small class="text-muted">เว้นว่างหากไม่จำกัดวงเงิน</small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="bi bi-x-circle me-2"></i>
                            ยกเลิก
                        </button>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-circle me-2"></i>
                            บันทึกการแก้ไข
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Enhanced JavaScript for better user experience
        document.addEventListener('DOMContentLoaded', function() {
            // Add loading states to buttons
            const forms = document.querySelectorAll('form');
            forms.forEach(form => {
                form.addEventListener('submit', function(e) {
                    const submitBtn = form.querySelector('button[type="submit"]');
                    if (submitBtn && !submitBtn.disabled) {
                        const originalText = submitBtn.innerHTML;
                        submitBtn.innerHTML = '<span class="loading-spinner"></span>กำลังประมวลผล...';
                        submitBtn.disabled = true;
                        
                        // Re-enable after 3 seconds as fallback
                        setTimeout(() => {
                            submitBtn.innerHTML = originalText;
                            submitBtn.disabled = false;
                        }, 3000);
                    }
                });
            });

            // Add smooth scrolling to alerts
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                setTimeout(() => {
                    alert.style.opacity = '0';
                    alert.style.transform = 'translateY(-20px)';
                    setTimeout(() => {
                        alert.remove();
                    }, 500);
                }, 5000);
            });

            // Format currency inputs
            const currencyInputs = document.querySelectorAll('input[type="number"]');
            currencyInputs.forEach(input => {
                input.addEventListener('input', function(e) {
                    // Add thousand separators for display
                    let value = e.target.value;
                    if (value) {
                        // Update placeholder text dynamically
                        if (parseFloat(value) > 0) {
                            e.target.title = 'จำนวนเงิน: ' + parseFloat(value).toLocaleString('th-TH') + ' บาท';
                        }
                    }
                });
            });
        });

        function editStructure(structure) {
            // Show modal with enhanced animation
            const modal = new bootstrap.Modal(document.getElementById('editModal'), {
                backdrop: 'static',
                keyboard: false
            });
            
            // Populate form fields with data
            document.getElementById('edit_approval_id').value = structure.ApprovalID;
            document.getElementById('edit_approval_name').value = structure.ApprovalName;
            document.getElementById('edit_dept_id').value = structure.DeptID || '';
            document.getElementById('edit_level1').value = structure.ApprovalLevel1 || '';
            document.getElementById('edit_level2').value = structure.ApprovalLevel2 || '';
            document.getElementById('edit_level3').value = structure.ApprovalLevel3 || '';
            document.getElementById('edit_level1_amount').value = structure.level1_amount_limit || '';
            document.getElementById('edit_level2_amount').value = structure.level2_amount_limit || '';
            document.getElementById('edit_level3_amount').value = structure.level3_amount_limit || '';
            
            // Add focus to first input after modal is shown
            modal._element.addEventListener('shown.bs.modal', function() {
                document.getElementById('edit_approval_name').focus();
            });
            
            modal.show();
        }

        // Add hover effects and tooltips
        document.querySelectorAll('.structure-card').forEach(card => {
            card.addEventListener('mouseenter', function() {
                this.style.transform = 'translateY(-5px)';
            });
            
            card.addEventListener('mouseleave', function() {
                this.style.transform = 'translateY(0)';
            });
        });

        // Add confirmation for delete with details
        document.querySelectorAll('form[onsubmit*="confirm"]').forEach(form => {
            form.addEventListener('submit', function(e) {
                e.preventDefault();
                
                const approvalName = this.closest('.structure-card').querySelector('h5').textContent.trim();
                
                if (confirm(`คุณแน่ใจหรือไม่ที่จะลบโครงสร้างการอนุมัติ?\n\n"${approvalName}"\n\n⚠️ การกระทำนี้ไม่สามารถย้อนกลับได้`)) {
                    this.submit();
                }
            });
        });

        // Auto-save form data to localStorage
        const createForm = document.getElementById('createForm');
        if (createForm) {
            const inputs = createForm.querySelectorAll('input, select');
            inputs.forEach(input => {
                // Load saved data
                const savedValue = localStorage.getItem('approval_form_' + input.name);
                if (savedValue && input.value === '') {
                    input.value = savedValue;
                }
                
                // Save data on change
                input.addEventListener('change', function() {
                    localStorage.setItem('approval_form_' + this.name, this.value);
                });
            });
            
            // Clear saved data on successful form submission
            createForm.addEventListener('submit', function() {
                inputs.forEach(input => {
                    localStorage.removeItem('approval_form_' + input.name);
                });
            });
        }
    </script>
</body>
</html>
