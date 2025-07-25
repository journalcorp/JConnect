<?php
header('Content-Type: text/html; charset=UTF-8');
session_start();
include_once '../includes/header.php';
include_once '../includes/menu.php';
include_once '../config.php';

// ตรวจสอบสิทธิ์การเข้าถึง
if (!isset($_SESSION['division']) || !in_array($_SESSION['division'], ['JD001', 'JD003'])) {
    echo '<div style="text-align:center;margin-top:50px;"><h3>คุณไม่มีสิทธิ์เข้าถึงหน้านี้</h3></div>';
    exit;
}

// ดึงข้อมูลผู้ใช้จาก session
$user_employee_id = $_SESSION['employee_id'] ?? null;
$user_division = $_SESSION['division'] ?? null;
$user_role = $_SESSION['role'] ?? 'employee';

// สร้างการเชื่อมต่อฐานข้อมูล
try {
    $conn = dbConnect();
    $database_available = true;
} catch (Exception $e) {
    $database_available = false;
    error_log("Database connection failed: " . $e->getMessage());
}

// ตรวจสอบว่าผู้ใช้เป็น IT หรือไม่ - JD001 ทุก role สามารถเห็นข้อมูลทั้งหมดได้
$is_it_user = ($user_division === 'JD001');

// ดึงข้อมูลสิทธิ์การอนุมัติของผู้ใช้
$user_approval_levels = [];
if ($database_available && $user_employee_id) {
    try {
        if (is_resource($conn)) {
            // SQLSRV Connection
            $sql_user_approvals = "
                SELECT DISTINCT a.ApprovalID, a.ApprovalName,
                    CASE 
                        WHEN a.ApprovalLevel1 = ? THEN 1
                        WHEN a.ApprovalLevel2 = ? THEN 2
                        WHEN a.ApprovalLevel3 = ? THEN 3
                        ELSE 0
                    END as approval_level
                FROM dbo.approvals a
                WHERE a.ApprovalLevel1 = ? OR a.ApprovalLevel2 = ? OR a.ApprovalLevel3 = ?
            ";
            $params = [$user_employee_id, $user_employee_id, $user_employee_id, $user_employee_id, $user_employee_id, $user_employee_id];
            $result = sqlsrv_query($conn, $sql_user_approvals, $params);
            
            if ($result) {
                while ($row = sqlsrv_fetch_array($result, SQLSRV_FETCH_ASSOC)) {
                    if ($row['approval_level'] > 0) {
                        $user_approval_levels[] = [
                            'approval_id' => $row['ApprovalID'],
                            'approval_name' => $row['ApprovalName'],
                            'level' => $row['approval_level']
                        ];
                    }
                }
            }
        } else if (is_object($conn)) {
            $conn_type = get_class($conn);
            if (strpos($conn_type, 'PDO') !== false) {
                // PDO Connection
                $sql_user_approvals = "
                    SELECT DISTINCT a.ApprovalID, a.ApprovalName,
                        CASE 
                            WHEN a.ApprovalLevel1 = ? THEN 1
                            WHEN a.ApprovalLevel2 = ? THEN 2
                            WHEN a.ApprovalLevel3 = ? THEN 3
                            ELSE 0
                        END as approval_level
                    FROM dbo.approvals a
                    WHERE a.ApprovalLevel1 = ? OR a.ApprovalLevel2 = ? OR a.ApprovalLevel3 = ?
                ";
                $stmt = $conn->prepare($sql_user_approvals);
                $stmt->execute([$user_employee_id, $user_employee_id, $user_employee_id, $user_employee_id, $user_employee_id, $user_employee_id]);
                
                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    if ($row['approval_level'] > 0) {
                        $user_approval_levels[] = [
                            'approval_id' => $row['ApprovalID'],
                            'approval_name' => $row['ApprovalName'],
                            'level' => $row['approval_level']
                        ];
                    }
                }
            }
        }
    } catch (Exception $e) {
        error_log("Error fetching user approval levels: " . $e->getMessage());
    }
}

// สร้าง WHERE clause สำหรับการกรองข้อมูล
$where_clause = "";
$params_filter = [];

if ($is_it_user) {
    // IT สามารถเห็นทั้งหมด - ไม่มีการจำกัด
    $where_clause = "WHERE 1=1";
} else if (!empty($user_approval_levels)) {
    // ผู้อนุมัติเห็นเฉพาะคำขอที่ตัวเองต้องอนุมัติ
    $approval_conditions = [];
    
    foreach ($user_approval_levels as $approval) {
        if ($approval['level'] == 1) {
            $approval_conditions[] = "(er.approver_level1 = ? AND er.current_approval_level = 1 AND er.status_level1 = 'Pending')";
            $params_filter[] = $user_employee_id;
        } else if ($approval['level'] == 2) {
            $approval_conditions[] = "(er.approver_level2 = ? AND er.current_approval_level = 2 AND er.status_level2 = 'Pending')";
            $params_filter[] = $user_employee_id;
        } else if ($approval['level'] == 3) {
            $approval_conditions[] = "(er.approver_level3 = ? AND er.current_approval_level = 3 AND er.status_level3 = 'Pending')";
            $params_filter[] = $user_employee_id;
        }
    }
    
    if (!empty($approval_conditions)) {
        $where_clause = "WHERE (" . implode(" OR ", $approval_conditions) . ")";
    } else {
        $where_clause = "WHERE 1=0"; // ไม่มีสิทธิ์เห็นอะไรเลย
    }
} else {
    // ไม่มีสิทธิ์อนุมัติ - ไม่เห็นอะไรเลย
    $where_clause = "WHERE 1=0";
}

// ดึงข้อมูลคำขอเบิกจ่าย
$expense_requests = [];
if ($database_available) {
    try {
        if (is_resource($conn)) {
            // SQLSRV Connection
            $sql_requests = "
                SELECT 
                    er.id,
                    er.voucher_no,
                    er.pay_to,
                    er.total_amount,
                    er.expense_type,
                    er.request_date,
                    er.created_at,
                    er.current_approval_level,
                    er.overall_status,
                    er.status_level1,
                    er.status_level2,
                    er.status_level3,
                    er.approver_level1,
                    er.approver_level2,
                    er.approver_level3,
                    er.remarks,
                    req.first_name_th + ' ' + req.last_name_th as requester_name,
                    req.department as requester_department,
                    app1.first_name_th + ' ' + app1.last_name_th as approver1_name,
                    app2.first_name_th + ' ' + app2.last_name_th as approver2_name,
                    app3.first_name_th + ' ' + app3.last_name_th as approver3_name
                FROM dbo.ExpenseRequests er
                LEFT JOIN dbo.employees req ON er.requester_id = req.employee_id
                LEFT JOIN dbo.employees app1 ON er.approver_level1 = app1.employee_id
                LEFT JOIN dbo.employees app2 ON er.approver_level2 = app2.employee_id
                LEFT JOIN dbo.employees app3 ON er.approver_level3 = app3.employee_id
                $where_clause
                ORDER BY er.created_at DESC
            ";
            
            $result = sqlsrv_query($conn, $sql_requests, $params_filter);
            
            if ($result) {
                while ($row = sqlsrv_fetch_array($result, SQLSRV_FETCH_ASSOC)) {
                    // แปลงวันที่
                    $created_at = $row['created_at'];
                    if ($created_at instanceof DateTime) {
                        $row['created_at_formatted'] = $created_at->format('d/m/Y H:i:s');
                    } else {
                        $row['created_at_formatted'] = date('d/m/Y H:i:s', strtotime($row['created_at']));
                    }
                    
                    $request_date = $row['request_date'];
                    if ($request_date instanceof DateTime) {
                        $row['request_date_formatted'] = $request_date->format('d/m/Y');
                    } else {
                        $row['request_date_formatted'] = date('d/m/Y', strtotime($row['request_date']));
                    }
                    
                    $expense_requests[] = $row;
                }
            }
        } else if (is_object($conn)) {
            $conn_type = get_class($conn);
            if (strpos($conn_type, 'PDO') !== false) {
                // PDO Connection
                $sql_requests = "
                    SELECT 
                        er.id,
                        er.voucher_no,
                        er.pay_to,
                        er.total_amount,
                        er.expense_type,
                        er.request_date,
                        er.created_at,
                        er.current_approval_level,
                        er.overall_status,
                        er.status_level1,
                        er.status_level2,
                        er.status_level3,
                        er.approver_level1,
                        er.approver_level2,
                        er.approver_level3,
                        er.remarks,
                        CONCAT(req.first_name_th, ' ', req.last_name_th) as requester_name,
                        req.department as requester_department,
                        CONCAT(app1.first_name_th, ' ', app1.last_name_th) as approver1_name,
                        CONCAT(app2.first_name_th, ' ', app2.last_name_th) as approver2_name,
                        CONCAT(app3.first_name_th, ' ', app3.last_name_th) as approver3_name
                    FROM dbo.ExpenseRequests er
                    LEFT JOIN dbo.employees req ON er.requester_id = req.employee_id
                    LEFT JOIN dbo.employees app1 ON er.approver_level1 = app1.employee_id
                    LEFT JOIN dbo.employees app2 ON er.approver_level2 = app2.employee_id
                    LEFT JOIN dbo.employees app3 ON er.approver_level3 = app3.employee_id
                    $where_clause
                    ORDER BY er.created_at DESC
                ";
                
                $stmt = $conn->prepare($sql_requests);
                $stmt->execute($params_filter);
                
                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    $row['created_at_formatted'] = date('d/m/Y H:i:s', strtotime($row['created_at']));
                    $row['request_date_formatted'] = date('d/m/Y', strtotime($row['request_date']));
                    $expense_requests[] = $row;
                }
            }
        }
    } catch (Exception $e) {
        error_log("Error fetching expense requests: " . $e->getMessage());
    }
}

// ประเภทค่าใช้จ่าย
$expense_types = [
    'travel' => 'ค่าเดินทาง',
    'office_supplies' => 'เครื่องเขียนและอุปกรณ์สำนักงาน',
    'meals' => 'ค่าอาหาร',
    'accommodation' => 'ค่าที่พัก',
    'fuel' => 'ค่าน้ำมันเชื้อเพลิง',
    'communication' => 'ค่าโทรศัพท์/อินเทอร์เน็ต',
    'maintenance' => 'ค่าซ่อมแซม',
    'training' => 'ค่าอบรม/สัมมนา',
    'others' => 'อื่นๆ'
];

// จัดการการอนุมัติ/ปฏิเสธ
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $request_id = $_POST['request_id'] ?? null;
    $action = $_POST['action']; // approve หรือ reject
    $comment = $_POST['comment'] ?? '';
    
    if ($request_id && in_array($action, ['approve', 'reject'])) {
        try {
            // ดึงข้อมูลคำขอปัจจุบัน
            $current_request = null;
            foreach ($expense_requests as $req) {
                if ($req['id'] == $request_id) {
                    $current_request = $req;
                    break;
                }
            }
            
            if ($current_request) {
                $current_level = $current_request['current_approval_level'];
                $new_status = ($action === 'approve') ? 'Approved' : 'Rejected';
                
                // อัปเดตสถานะ
                if (is_resource($conn)) {
                    sqlsrv_begin_transaction($conn);
                    
                    if ($current_level == 1) {
                        $sql_update = "UPDATE dbo.ExpenseRequests SET 
                                     status_level1 = ?, 
                                     current_approval_level = CASE WHEN ? = 'Approved' AND approver_level2 IS NOT NULL THEN 2 
                                                                  WHEN ? = 'Approved' AND approver_level2 IS NULL AND approver_level3 IS NOT NULL THEN 3
                                                                  ELSE current_approval_level END,
                                     overall_status = CASE WHEN ? = 'Rejected' THEN 'Rejected'
                                                          WHEN ? = 'Approved' AND approver_level2 IS NULL AND approver_level3 IS NULL THEN 'Approved'
                                                          ELSE overall_status END,
                                     updated_at = GETDATE()
                                     WHERE id = ?";
                        $params = [$new_status, $new_status, $new_status, $new_status, $new_status, $request_id];
                    } else if ($current_level == 2) {
                        $sql_update = "UPDATE dbo.ExpenseRequests SET 
                                     status_level2 = ?, 
                                     current_approval_level = CASE WHEN ? = 'Approved' AND approver_level3 IS NOT NULL THEN 3 
                                                                  ELSE current_approval_level END,
                                     overall_status = CASE WHEN ? = 'Rejected' THEN 'Rejected'
                                                          WHEN ? = 'Approved' AND approver_level3 IS NULL THEN 'Approved'
                                                          ELSE overall_status END,
                                     updated_at = GETDATE()
                                     WHERE id = ?";
                        $params = [$new_status, $new_status, $new_status, $new_status, $request_id];
                    } else if ($current_level == 3) {
                        $sql_update = "UPDATE dbo.ExpenseRequests SET 
                                     status_level3 = ?, 
                                     overall_status = ?,
                                     updated_at = GETDATE()
                                     WHERE id = ?";
                        $params = [$new_status, $new_status, $request_id];
                    }
                    
                    $result = sqlsrv_query($conn, $sql_update, $params);
                    
                    if ($result) {
                        sqlsrv_commit($conn);
                        $success_message = "ดำเนินการ" . ($action === 'approve' ? 'อนุมัติ' : 'ปฏิเสธ') . "เรียบร้อยแล้ว";
                        
                        // รีเฟรชหน้า
                        header("Location: " . $_SERVER['PHP_SELF'] . "?success=1");
                        exit;
                    } else {
                        sqlsrv_rollback($conn);
                        throw new Exception("ไม่สามารถอัปเดตสถานะได้");
                    }
                } else if (is_object($conn)) {
                    // PDO หรือ MySQLi
                    $conn->beginTransaction();
                    
                    if ($current_level == 1) {
                        $sql_update = "UPDATE ExpenseRequests SET 
                                     status_level1 = ?, 
                                     current_approval_level = CASE WHEN ? = 'Approved' AND approver_level2 IS NOT NULL THEN 2 
                                                                  WHEN ? = 'Approved' AND approver_level2 IS NULL AND approver_level3 IS NOT NULL THEN 3
                                                                  ELSE current_approval_level END,
                                     overall_status = CASE WHEN ? = 'Rejected' THEN 'Rejected'
                                                          WHEN ? = 'Approved' AND approver_level2 IS NULL AND approver_level3 IS NULL THEN 'Approved'
                                                          ELSE overall_status END,
                                     updated_at = NOW()
                                     WHERE id = ?";
                        $params = [$new_status, $new_status, $new_status, $new_status, $new_status, $request_id];
                    } else if ($current_level == 2) {
                        $sql_update = "UPDATE ExpenseRequests SET 
                                     status_level2 = ?, 
                                     current_approval_level = CASE WHEN ? = 'Approved' AND approver_level3 IS NOT NULL THEN 3 
                                                                  ELSE current_approval_level END,
                                     overall_status = CASE WHEN ? = 'Rejected' THEN 'Rejected'
                                                          WHEN ? = 'Approved' AND approver_level3 IS NULL THEN 'Approved'
                                                          ELSE overall_status END,
                                     updated_at = NOW()
                                     WHERE id = ?";
                        $params = [$new_status, $new_status, $new_status, $new_status, $request_id];
                    } else if ($current_level == 3) {
                        $sql_update = "UPDATE ExpenseRequests SET 
                                     status_level3 = ?, 
                                     overall_status = ?,
                                     updated_at = NOW()
                                     WHERE id = ?";
                        $params = [$new_status, $new_status, $request_id];
                    }
                    
                    $stmt = $conn->prepare($sql_update);
                    $result = $stmt->execute($params);
                    
                    if ($result) {
                        $conn->commit();
                        $success_message = "ดำเนินการ" . ($action === 'approve' ? 'อนุมัติ' : 'ปฏิเสธ') . "เรียบร้อยแล้ว";
                        
                        // รีเฟรชหน้า
                        header("Location: " . $_SERVER['PHP_SELF'] . "?success=1");
                        exit;
                    } else {
                        $conn->rollback();
                        throw new Exception("ไม่สามารถอัปเดตสถานะได้");
                    }
                }
            }
        } catch (Exception $e) {
            error_log("Error processing approval: " . $e->getMessage());
            $error_message = "เกิดข้อผิดพลาดในการดำเนินการ: " . $e->getMessage();
        }
    }
}

// ตรวจสอบ success parameter
if (isset($_GET['success']) && $_GET['success'] == '1') {
    $success_message = "ดำเนินการเรียบร้อยแล้ว";
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ระบบอนุมัติการเบิกจ่าย</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            background: #f8f9fa;
            min-height: 100vh;
            font-family: 'Prompt', sans-serif;
        }
        
        .main-container {
            background: white;
            border-radius: 16px;
            box-shadow: 0 8px 25px rgba(0,0,0,0.08);
            margin: 20px auto;
            max-width: 1400px;
            overflow: hidden;
        }
        
        .header-section {
            background: linear-gradient(135deg, #6c757d, #495057);
            color: white;
            padding: 30px;
            text-align: center;
        }
        
        .status-pending { color: #fd7e14; }
        .status-approved { color: #198754; }
        .status-rejected { color: #dc3545; }
        
        .expense-card {
            border: none;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
            margin-bottom: 20px;
            overflow: hidden;
            transition: all 0.3s ease;
        }
        
        .expense-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.12);
        }
        
        .expense-card-header {
            background: linear-gradient(135deg, #f8f9fa, #e9ecef);
            border-bottom: 3px solid #6c757d;
            padding: 20px;
        }
        
        .expense-card-header.pending {
            border-bottom-color: #fd7e14;
        }
        
        .expense-card-header.approved {
            border-bottom-color: #198754;
        }
        
        .expense-card-header.rejected {
            border-bottom-color: #dc3545;
        }
        
        .approval-buttons {
            display: flex;
            gap: 10px;
            justify-content: center;
            margin-top: 15px;
        }
        
        .btn-approve {
            background: linear-gradient(135deg, #198754, #157347);
            border: none;
            color: white;
            padding: 10px 25px;
            border-radius: 25px;
            transition: all 0.3s ease;
        }
        
        .btn-reject {
            background: linear-gradient(135deg, #dc3545, #bb2d3b);
            border: none;
            color: white;
            padding: 10px 25px;
            border-radius: 25px;
            transition: all 0.3s ease;
        }
        
        .btn-approve:hover, .btn-reject:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }
        
        .user-info {
            background: rgba(255,255,255,0.15);
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
        }
        
        .approval-level-badge {
            background: linear-gradient(135deg, #6c757d, #495057);
            color: white;
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 0.85rem;
            display: inline-block;
            margin: 5px;
        }
        
        .filter-section {
            background: #f8f9fa;
            padding: 20px;
            border-bottom: 1px solid #dee2e6;
        }
        
        .no-requests {
            text-align: center;
            padding: 60px;
            color: #6c757d;
        }
    </style>
</head>
<body>
    <div class="main-container">
        <div class="header-section">
            <h1><i class="bi bi-clipboard-check"></i> ระบบอนุมัติการเบิกจ่าย</h1>
            <p class="mb-0">จัดการคำขอเบิกจ่ายที่รออนุมัติ</p>
        </div>
        
        <div class="filter-section">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <div class="user-info">
                        <h6><i class="bi bi-person-circle"></i> ข้อมูลผู้ใช้:</h6>
                        <p class="mb-1"><strong>รหัสพนักงาน:</strong> <?php echo htmlspecialchars($user_employee_id); ?></p>
                        <p class="mb-1"><strong>แผนก:</strong> <?php echo htmlspecialchars($user_division); ?></p>
                        <p class="mb-0"><strong>สิทธิ์:</strong> 
                            <?php if ($is_it_user): ?>
                                <span class="badge bg-secondary">IT Admin (เห็นทั้งหมด)</span>
                                <br><small class="text-muted mt-1">
                                    • ดูคำขอทั้งหมด: <?php echo count($expense_requests); ?> รายการ<br>
                                    • รออนุมัติ: <?php echo count(array_filter($expense_requests, function($r) { return $r['overall_status'] === 'Pending'; })); ?> รายการ<br>
                                    • อนุมัติแล้ว: <?php echo count(array_filter($expense_requests, function($r) { return $r['overall_status'] === 'Approved'; })); ?> รายการ<br>
                                    • ปฏิเสธแล้ว: <?php echo count(array_filter($expense_requests, function($r) { return $r['overall_status'] === 'Rejected'; })); ?> รายการ
                                </small>
                            <?php else: ?>
                                <span class="badge bg-secondary">ผู้อนุมัติ</span>
                            <?php endif; ?>
                        </p>
                        
                        <?php if (!empty($user_approval_levels)): ?>
                            <div class="mt-2">
                                <strong>ระดับการอนุมัติ:</strong><br>
                                <?php foreach ($user_approval_levels as $approval): ?>
                                    <span class="approval-level-badge">
                                        <?php echo htmlspecialchars($approval['approval_name']); ?> 
                                        (ระดับ <?php echo $approval['level']; ?>)
                                    </span>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="d-flex flex-column">
                        <div class="row g-2 mb-3">
                            <div class="col-sm-6">
                                <input type="text" class="form-control form-control-sm" id="searchFilter" placeholder="ค้นหา (เลขที่ใบเบิก, ผู้ขอ, จ่ายให้)">
                            </div>
                            <div class="col-sm-6">
                                <select class="form-select form-select-sm" id="statusFilter">
                                    <option value="">ทุกสถานะ</option>
                                    <option value="Pending">รออนุมัติ</option>
                                    <option value="Approved">อนุมัติแล้ว</option>
                                    <option value="Rejected">ปฏิเสธ</option>
                                </select>
                            </div>
                            <?php if ($is_it_user): ?>
                            <div class="col-sm-6">
                                <select class="form-select form-select-sm" id="levelFilter">
                                    <option value="">ทุกระดับ</option>
                                    <option value="1">ระดับ 1</option>
                                    <option value="2">ระดับ 2</option>
                                    <option value="3">ระดับ 3</option>
                                </select>
                            </div>
                            <div class="col-sm-6">
                                <select class="form-select form-select-sm" id="approverFilter">
                                    <option value="">ผู้อนุมัติทั้งหมด</option>
                                    <!-- จะเติมข้อมูลด้วย JavaScript -->
                                </select>
                            </div>
                            <?php endif; ?>
                        </div>
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="badge bg-secondary" id="resultCount">
                                <i class="bi bi-list-ul"></i> 
                                คำขอทั้งหมด: <?php echo count($expense_requests); ?> รายการ
                            </span>
                            <?php if (!$is_it_user): ?>
                                <small class="text-muted">
                                    <i class="bi bi-info-circle"></i> 
                                    แสดงเฉพาะคำขอที่ต้องอนุมัติ
                                </small>
                            <?php else: ?>
                                <small class="text-secondary">
                                    <i class="bi bi-shield-check"></i> 
                                    IT Admin: ดูข้อมูลทั้งหมด + กรองขั้นสูง
                                </small>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="container-fluid p-4">
            <?php if (isset($success_message)): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="bi bi-check-circle"></i> <?php echo $success_message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if (isset($error_message)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="bi bi-exclamation-triangle"></i> <?php echo $error_message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if (empty($expense_requests)): ?>
                <div class="no-requests">
                    <i class="bi bi-inbox" style="font-size: 4rem; color: #dee2e6;"></i>
                    <h4 class="mt-3">ไม่มีคำขอที่รออนุมัติ</h4>
                    <p>
                        <?php if ($is_it_user): ?>
                            ขณะนี้ไม่มีคำขอเบิกจ่ายในระบบ หรือคำขอทั้งหมดได้รับการอนุมัติ/ปฏิเสธแล้ว
                        <?php else: ?>
                            ขณะนี้ไม่มีคำขอเบิกจ่ายที่รอการอนุมัติจากท่าน
                        <?php endif; ?>
                    </p>
                </div>
            <?php else: ?>
                <div class="row" id="requestsContainer">
                    <?php foreach ($expense_requests as $request): ?>
                        <div class="col-lg-6 col-xl-4 mb-4 request-card" 
                             data-voucher="<?php echo strtolower($request['voucher_no']); ?>"
                             data-requester="<?php echo strtolower($request['requester_name'] ?? ''); ?>"
                             data-payto="<?php echo strtolower($request['pay_to']); ?>"
                             data-status="<?php echo $request['overall_status']; ?>"
                             data-amount="<?php echo $request['total_amount']; ?>"
                             data-current-level="<?php echo $request['current_approval_level']; ?>"
                             data-approver1="<?php echo strtolower($request['approver1_name'] ?? ''); ?>"
                             data-approver2="<?php echo strtolower($request['approver2_name'] ?? ''); ?>"
                             data-approver3="<?php echo strtolower($request['approver3_name'] ?? ''); ?>">
                            <div class="card expense-card h-100">
                                <div class="expense-card-header <?php echo strtolower($request['overall_status']); ?>">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <h6 class="card-title mb-2">
                                            <i class="bi bi-receipt"></i> 
                                            <?php echo htmlspecialchars($request['voucher_no']); ?>
                                        </h6>
                                        <span class="badge bg-<?php 
                                            echo $request['overall_status'] === 'Approved' ? 'success' : 
                                                ($request['overall_status'] === 'Rejected' ? 'danger' : 'warning'); 
                                        ?>">
                                            <?php echo htmlspecialchars($request['overall_status']); ?>
                                        </span>
                                    </div>
                                    <p class="card-text mb-1">
                                        <small class="text-muted">
                                            <i class="bi bi-calendar"></i> 
                                            <?php echo $request['created_at_formatted']; ?>
                                        </small>
                                    </p>
                                </div>
                                
                                <div class="card-body">
                                    <div class="mb-3">
                                        <h6><i class="bi bi-person"></i> ผู้ขอเบิก:</h6>
                                        <p class="mb-1"><?php echo htmlspecialchars($request['requester_name'] ?? 'ไม่ระบุ'); ?></p>
                                        <small class="text-muted"><?php echo htmlspecialchars($request['requester_department'] ?? ''); ?></small>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <h6><i class="bi bi-building"></i> จ่ายให้:</h6>
                                        <p class="mb-0"><?php echo htmlspecialchars($request['pay_to']); ?></p>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <h6><i class="bi bi-tag"></i> ประเภท:</h6>
                                        <p class="mb-0"><?php echo $expense_types[$request['expense_type']] ?? $request['expense_type']; ?></p>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <h6><i class="bi bi-currency-dollar"></i> จำนวนเงิน:</h6>
                                        <h5 class="text-secondary mb-0">
                                            <?php echo number_format($request['total_amount'], 2); ?> บาท
                                        </h5>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <h6><i class="bi bi-flow-chart"></i> สถานะการอนุมัติ:</h6>
                                        <div class="d-flex flex-column gap-2">
                                            <?php if (!empty($request['approver1_name'])): ?>
                                                <div class="d-flex justify-content-between align-items-center">
                                                    <span>ระดับ 1: <?php echo htmlspecialchars($request['approver1_name']); ?></span>
                                                    <span class="badge bg-<?php 
                                                        echo $request['status_level1'] === 'Approved' ? 'success' : 
                                                            ($request['status_level1'] === 'Rejected' ? 'danger' : 'warning'); 
                                                    ?>">
                                                        <?php echo $request['status_level1']; ?>
                                                    </span>
                                                </div>
                                            <?php endif; ?>
                                            
                                            <?php if (!empty($request['approver2_name'])): ?>
                                                <div class="d-flex justify-content-between align-items-center">
                                                    <span>ระดับ 2: <?php echo htmlspecialchars($request['approver2_name']); ?></span>
                                                    <span class="badge bg-<?php 
                                                        echo $request['status_level2'] === 'Approved' ? 'success' : 
                                                            ($request['status_level2'] === 'Rejected' ? 'danger' : 'warning'); 
                                                    ?>">
                                                        <?php echo $request['status_level2']; ?>
                                                    </span>
                                                </div>
                                            <?php endif; ?>
                                            
                                            <?php if (!empty($request['approver3_name'])): ?>
                                                <div class="d-flex justify-content-between align-items-center">
                                                    <span>ระดับ 3: <?php echo htmlspecialchars($request['approver3_name']); ?></span>
                                                    <span class="badge bg-<?php 
                                                        echo $request['status_level3'] === 'Approved' ? 'success' : 
                                                            ($request['status_level3'] === 'Rejected' ? 'danger' : 'warning'); 
                                                    ?>">
                                                        <?php echo $request['status_level3']; ?>
                                                    </span>
                                                </div>
                                            <?php endif; ?>
                                            
                                            <?php if ($is_it_user): ?>
                                                <div class="mt-2 p-2 bg-light rounded">
                                                    <small class="text-muted">
                                                        <i class="bi bi-info-circle"></i> 
                                                        <strong>ระดับปัจจุบัน:</strong> <?php echo $request['current_approval_level']; ?>
                                                        <?php if ($request['overall_status'] === 'Pending'): ?>
                                                            | <strong>รอ:</strong> <?php 
                                                            $current_approver = '';
                                                            if ($request['current_approval_level'] == 1 && !empty($request['approver1_name'])) {
                                                                $current_approver = $request['approver1_name'];
                                                            } else if ($request['current_approval_level'] == 2 && !empty($request['approver2_name'])) {
                                                                $current_approver = $request['approver2_name'];
                                                            } else if ($request['current_approval_level'] == 3 && !empty($request['approver3_name'])) {
                                                                $current_approver = $request['approver3_name'];
                                                            }
                                                            echo htmlspecialchars($current_approver);
                                                            ?>
                                                        <?php endif; ?>
                                                    </small>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    
                                    <?php if (!empty($request['remarks'])): ?>
                                        <div class="mb-3">
                                            <h6><i class="bi bi-chat-left-text"></i> หมายเหตุ:</h6>
                                            <p class="mb-0 text-muted"><?php echo htmlspecialchars($request['remarks']); ?></p>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php 
                                    // ตรวจสอบว่าผู้ใช้สามารถอนุมัติคำขอนี้ได้หรือไม่
                                    $can_approve = false;
                                    $current_level = $request['current_approval_level'];
                                    
                                    // ตรวจสอบสิทธิ์การอนุมัติ
                                    if (($current_level == 1 && $request['approver_level1'] == $user_employee_id) ||
                                        ($current_level == 2 && $request['approver_level2'] == $user_employee_id) ||
                                        ($current_level == 3 && $request['approver_level3'] == $user_employee_id)) {
                                        $can_approve = true;
                                    }
                                    
                                    // ตรวจสอบว่าคำขอยังรอการอนุมัติหรือไม่
                                    $is_pending = $request['overall_status'] === 'Pending';
                                    ?>
                                    
                                    <div class="text-center mb-3">
                                        <a href="expense_detail.php?id=<?php echo $request['id']; ?>" class="btn btn-outline-secondary btn-sm">
                                            <i class="bi bi-eye"></i> ดูรายละเอียด
                                        </a>
                                    </div>
                                    
                                    <?php if ($can_approve && $is_pending): ?>
                                        <div class="approval-buttons">
                                            <button type="button" class="btn btn-approve" onclick="approveRequest(<?php echo $request['id']; ?>, 'approve')">
                                                <i class="bi bi-check-circle"></i> อนุมัติ
                                            </button>
                                            <button type="button" class="btn btn-reject" onclick="approveRequest(<?php echo $request['id']; ?>, 'reject')">
                                                <i class="bi bi-x-circle"></i> ปฏิเสธ
                                            </button>
                                        </div>
                                    <?php elseif (!$is_pending): ?>
                                        <div class="text-center">
                                            <span class="badge bg-secondary">
                                                <i class="bi bi-check-all"></i> ดำเนินการเสร็จสิ้น
                                            </span>
                                        </div>
                                    <?php else: ?>
                                        <div class="text-center">
                                            <span class="badge bg-light text-dark">
                                                <i class="bi bi-hourglass-split"></i> รอการอนุมัติจากระดับอื่น
                                            </span>
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

    <!-- Modal สำหรับยืนยันการอนุมัติ/ปฏิเสธ -->
    <div class="modal fade" id="approvalModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="approvalModalTitle">ยืนยันการดำเนินการ</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="request_id" id="modal_request_id">
                        <input type="hidden" name="action" id="modal_action">
                        
                        <p id="modal_message">คุณต้องการดำเนินการนี้หรือไม่?</p>
                        
                        <div class="mb-3">
                            <label for="comment" class="form-label">หมายเหตุ (ถ้ามี):</label>
                            <textarea class="form-control" name="comment" id="comment" rows="3" placeholder="เพิ่มหมายเหตุ..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                        <button type="submit" class="btn" id="modal_confirm_btn">ยืนยัน</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function approveRequest(requestId, action) {
            const modal = new bootstrap.Modal(document.getElementById('approvalModal'));
            const title = document.getElementById('approvalModalTitle');
            const message = document.getElementById('modal_message');
            const confirmBtn = document.getElementById('modal_confirm_btn');
            
            document.getElementById('modal_request_id').value = requestId;
            document.getElementById('modal_action').value = action;
            
            if (action === 'approve') {
                title.textContent = 'ยืนยันการอนุมัติ';
                message.textContent = 'คุณต้องการอนุมัติคำขอเบิกจ่ายนี้หรือไม่?';
                confirmBtn.textContent = 'อนุมัติ';
                confirmBtn.className = 'btn btn-success';
            } else {
                title.textContent = 'ยืนยันการปฏิเสธ';
                message.textContent = 'คุณต้องการปฏิเสธคำขอเบิกจ่ายนี้หรือไม่?';
                confirmBtn.textContent = 'ปฏิเสธ';
                confirmBtn.className = 'btn btn-danger';
            }
            
            modal.show();
        }
        
        // ฟังก์ชันค้นหาและกรองข้อมูล
        function filterRequests() {
            const searchText = document.getElementById('searchFilter').value.toLowerCase();
            const statusFilter = document.getElementById('statusFilter').value;
            const levelFilter = document.getElementById('levelFilter') ? document.getElementById('levelFilter').value : '';
            const approverFilter = document.getElementById('approverFilter') ? document.getElementById('approverFilter').value.toLowerCase() : '';
            const requestCards = document.querySelectorAll('.request-card');
            let visibleCount = 0;
            
            requestCards.forEach(card => {
                const voucher = card.getAttribute('data-voucher') || '';
                const requester = card.getAttribute('data-requester') || '';
                const payto = card.getAttribute('data-payto') || '';
                const status = card.getAttribute('data-status') || '';
                const currentLevel = card.getAttribute('data-current-level') || '';
                const approver1 = card.getAttribute('data-approver1') || '';
                const approver2 = card.getAttribute('data-approver2') || '';
                const approver3 = card.getAttribute('data-approver3') || '';
                
                // ตรวจสอบการค้นหา
                const matchesSearch = searchText === '' || 
                    voucher.includes(searchText) || 
                    requester.includes(searchText) || 
                    payto.includes(searchText);
                
                // ตรวจสอบการกรองสถานะ
                const matchesStatus = statusFilter === '' || status === statusFilter;
                
                // ตรวจสอบการกรองระดับ (สำหรับ IT)
                const matchesLevel = levelFilter === '' || currentLevel === levelFilter;
                
                // ตรวจสอบการกรองผู้อนุมัติ (สำหรับ IT)
                const matchesApprover = approverFilter === '' || 
                    approver1.includes(approverFilter) || 
                    approver2.includes(approverFilter) || 
                    approver3.includes(approverFilter);
                
                if (matchesSearch && matchesStatus && matchesLevel && matchesApprover) {
                    card.style.display = 'block';
                    visibleCount++;
                } else {
                    card.style.display = 'none';
                }
            });
            
            // อัปเดตจำนวนผลลัพธ์
            document.getElementById('resultCount').innerHTML = 
                '<i class="bi bi-list-ul"></i> แสดง: ' + visibleCount + ' จาก <?php echo count($expense_requests); ?> รายการ';
        }
        
        // เพิ่ม event listeners
        document.getElementById('searchFilter').addEventListener('input', filterRequests);
        document.getElementById('statusFilter').addEventListener('change', filterRequests);
        
        // เพิ่ม event listeners สำหรับ IT filters
        if (document.getElementById('levelFilter')) {
            document.getElementById('levelFilter').addEventListener('change', filterRequests);
        }
        if (document.getElementById('approverFilter')) {
            document.getElementById('approverFilter').addEventListener('change', filterRequests);
        }
        
        // สร้างรายการผู้อนุมัติสำหรับ dropdown (เฉพาะ IT)
        function populateApproverFilter() {
            const approverFilter = document.getElementById('approverFilter');
            if (!approverFilter) return;
            
            const approvers = new Set();
            const requestCards = document.querySelectorAll('.request-card');
            
            requestCards.forEach(card => {
                const approver1 = card.getAttribute('data-approver1');
                const approver2 = card.getAttribute('data-approver2');
                const approver3 = card.getAttribute('data-approver3');
                
                if (approver1 && approver1 !== '') approvers.add(approver1);
                if (approver2 && approver2 !== '') approvers.add(approver2);
                if (approver3 && approver3 !== '') approvers.add(approver3);
            });
            
            // เรียงลำดับและเพิ่มใน dropdown
            Array.from(approvers).sort().forEach(approver => {
                const option = document.createElement('option');
                option.value = approver;
                option.textContent = approver;
                approverFilter.appendChild(option);
            });
        }
        
        // เรียกใช้เมื่อโหลดหน้า
        document.addEventListener('DOMContentLoaded', function() {
            populateApproverFilter();
        });
        
        // Auto dismiss alerts
        setTimeout(() => {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                const bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            });
        }, 5000);
        
        // เพิ่มการ highlight ผลลัพธ์การค้นหา
        function highlightSearchResults() {
            const searchText = document.getElementById('searchFilter').value.toLowerCase();
            if (searchText === '') return;
            
            const requestCards = document.querySelectorAll('.request-card:not([style*="display: none"])');
            requestCards.forEach(card => {
                const textElements = card.querySelectorAll('h6, p, span');
                textElements.forEach(element => {
                    if (element.innerHTML.toLowerCase().includes(searchText)) {
                        element.style.backgroundColor = '#fff3cd';
                        element.style.transition = 'background-color 0.3s ease';
                        setTimeout(() => {
                            element.style.backgroundColor = '';
                        }, 2000);
                    }
                });
            });
        }
        
        // เรียกใช้ highlight เมื่อค้นหา
        document.getElementById('searchFilter').addEventListener('input', function() {
            setTimeout(highlightSearchResults, 100);
        });
    </script>
</body>
</html>
