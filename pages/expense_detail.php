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

// ตรวจสอบว่าผู้ใช้เป็น IT หรือไม่
$is_it_user = ($user_division === 'JD001' && in_array($user_role, ['admin', 'hr', 'manager']));

// รับ request ID จาก URL
$request_id = $_GET['id'] ?? null;

if (!$request_id) {
    header('Location: approval_dashboard.php');
    exit;
}

// สร้างการเชื่อมต่อฐานข้อมูล
try {
    $conn = dbConnect();
    $database_available = true;
} catch (Exception $e) {
    $database_available = false;
    error_log("Database connection failed: " . $e->getMessage());
}

// ดึงข้อมูลคำขอเบิกจ่าย
$expense_request = null;
$expense_items = [];

if ($database_available) {
    try {
        if (is_resource($conn)) {
            // SQLSRV Connection
            $sql_request = "
                SELECT 
                    er.*,
                    req.employee_id as requester_employee_id,
                    req.first_name_th + ' ' + req.last_name_th as requester_name,
                    req.department as requester_department,
                    req.position_th as requester_position,
                    req.email as requester_email,
                    app1.first_name_th + ' ' + app1.last_name_th as approver1_name,
                    app1.position_th as approver1_position,
                    app1.email as approver1_email,
                    app2.first_name_th + ' ' + app2.last_name_th as approver2_name,
                    app2.position_th as approver2_position,
                    app2.email as approver2_email,
                    app3.first_name_th + ' ' + app3.last_name_th as approver3_name,
                    app3.position_th as approver3_position,
                    app3.email as approver3_email
                FROM dbo.ExpenseRequests er
                LEFT JOIN dbo.employees req ON er.requester_id = req.employee_id
                LEFT JOIN dbo.employees app1 ON er.approver_level1 = app1.employee_id
                LEFT JOIN dbo.employees app2 ON er.approver_level2 = app2.employee_id
                LEFT JOIN dbo.employees app3 ON er.approver_level3 = app3.employee_id
                WHERE er.id = ?
            ";
            
            $result = sqlsrv_query($conn, $sql_request, [$request_id]);
            
            if ($result) {
                $expense_request = sqlsrv_fetch_array($result, SQLSRV_FETCH_ASSOC);
            }
            
            // ดึงรายการค่าใช้จ่าย
            if ($expense_request) {
                $sql_items = "SELECT * FROM dbo.ExpenseItems WHERE expense_request_id = ? ORDER BY created_at";
                $result_items = sqlsrv_query($conn, $sql_items, [$request_id]);
                
                if ($result_items) {
                    while ($item = sqlsrv_fetch_array($result_items, SQLSRV_FETCH_ASSOC)) {
                        $expense_items[] = $item;
                    }
                }
            }
        } else if (is_object($conn)) {
            $conn_type = get_class($conn);
            if (strpos($conn_type, 'PDO') !== false) {
                // PDO Connection
                $sql_request = "
                    SELECT 
                        er.*,
                        req.employee_id as requester_employee_id,
                        CONCAT(req.first_name_th, ' ', req.last_name_th) as requester_name,
                        req.department as requester_department,
                        req.position_th as requester_position,
                        req.email as requester_email,
                        CONCAT(app1.first_name_th, ' ', app1.last_name_th) as approver1_name,
                        app1.position_th as approver1_position,
                        app1.email as approver1_email,
                        CONCAT(app2.first_name_th, ' ', app2.last_name_th) as approver2_name,
                        app2.position_th as approver2_position,
                        app2.email as approver2_email,
                        CONCAT(app3.first_name_th, ' ', app3.last_name_th) as approver3_name,
                        app3.position_th as approver3_position,
                        app3.email as approver3_email
                    FROM dbo.ExpenseRequests er
                    LEFT JOIN dbo.employees req ON er.requester_id = req.employee_id
                    LEFT JOIN dbo.employees app1 ON er.approver_level1 = app1.employee_id
                    LEFT JOIN dbo.employees app2 ON er.approver_level2 = app2.employee_id
                    LEFT JOIN dbo.employees app3 ON er.approver_level3 = app3.employee_id
                    WHERE er.id = ?
                ";
                
                $stmt = $conn->prepare($sql_request);
                $stmt->execute([$request_id]);
                $expense_request = $stmt->fetch(PDO::FETCH_ASSOC);
                
                // ดึงรายการค่าใช้จ่าย
                if ($expense_request) {
                    $sql_items = "SELECT * FROM dbo.ExpenseItems WHERE expense_request_id = ? ORDER BY created_at";
                    $stmt_items = $conn->prepare($sql_items);
                    $stmt_items->execute([$request_id]);
                    $expense_items = $stmt_items->fetchAll(PDO::FETCH_ASSOC);
                }
            }
        }
    } catch (Exception $e) {
        error_log("Error fetching expense request: " . $e->getMessage());
    }
}

// ตรวจสอบสิทธิ์การเข้าถึงคำขอนี้
if ($expense_request) {
    $can_view = false;
    
    if ($is_it_user) {
        $can_view = true; // IT เห็นทั้งหมด
    } else {
        // ตรวจสอบว่าผู้ใช้เป็นผู้อนุมัติของคำขอนี้หรือไม่
        if ($expense_request['approver_level1'] == $user_employee_id ||
            $expense_request['approver_level2'] == $user_employee_id ||
            $expense_request['approver_level3'] == $user_employee_id) {
            $can_view = true;
        }
    }
    
    if (!$can_view) {
        echo '<div style="text-align:center;margin-top:50px;"><h3>คุณไม่มีสิทธิ์เข้าถึงคำขอนี้</h3></div>';
        exit;
    }
} else {
    echo '<div style="text-align:center;margin-top:50px;"><h3>ไม่พบคำขอเบิกจ่ายที่ระบุ</h3></div>';
    exit;
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

// แปลงวันที่
$created_at_formatted = '';
$request_date_formatted = '';
$updated_at_formatted = '';

if ($expense_request) {
    $created_at = $expense_request['created_at'];
    if ($created_at instanceof DateTime) {
        $created_at_formatted = $created_at->format('d/m/Y H:i:s');
    } else {
        $created_at_formatted = date('d/m/Y H:i:s', strtotime($created_at));
    }
    
    $request_date = $expense_request['request_date'];
    if ($request_date instanceof DateTime) {
        $request_date_formatted = $request_date->format('d/m/Y');
    } else {
        $request_date_formatted = date('d/m/Y', strtotime($request_date));
    }
    
    if (!empty($expense_request['updated_at'])) {
        $updated_at = $expense_request['updated_at'];
        if ($updated_at instanceof DateTime) {
            $updated_at_formatted = $updated_at->format('d/m/Y H:i:s');
        } else {
            $updated_at_formatted = date('d/m/Y H:i:s', strtotime($updated_at));
        }
    }
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>รายละเอียดคำขอเบิกจ่าย - <?php echo htmlspecialchars($expense_request['voucher_no'] ?? ''); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            font-family: 'Sarabun', sans-serif;
        }
        
        .main-container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            margin: 20px auto;
            max-width: 1200px;
            overflow: hidden;
        }
        
        .header-section {
            background: linear-gradient(135deg, #28a745, #20c997);
            color: white;
            padding: 30px;
            text-align: center;
        }
        
        .info-card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
            margin-bottom: 20px;
            overflow: hidden;
        }
        
        .info-card-header {
            background: linear-gradient(135deg, #f8f9fa, #e9ecef);
            border-bottom: 3px solid #007bff;
            padding: 20px;
            font-weight: 600;
        }
        
        .status-pending { color: #ffc107; }
        .status-approved { color: #28a745; }
        .status-rejected { color: #dc3545; }
        
        .approval-timeline {
            position: relative;
            padding: 20px 0;
        }
        
        .timeline-item {
            position: relative;
            padding: 20px 0 20px 60px;
            border-left: 3px solid #e9ecef;
        }
        
        .timeline-item:last-child {
            border-left: none;
        }
        
        .timeline-icon {
            position: absolute;
            left: -12px;
            top: 25px;
            width: 24px;
            height: 24px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
        }
        
        .timeline-icon.pending {
            background: #ffc107;
            color: white;
        }
        
        .timeline-icon.approved {
            background: #28a745;
            color: white;
        }
        
        .timeline-icon.rejected {
            background: #dc3545;
            color: white;
        }
        
        .items-table {
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
        }
        
        .table-header {
            background: linear-gradient(135deg, #007bff, #0056b3);
            color: white;
        }
        
        .btn-back {
            background: linear-gradient(135deg, #6c757d, #495057);
            border: none;
            color: white;
            padding: 10px 25px;
            border-radius: 25px;
            transition: all 0.3s ease;
        }
        
        .btn-back:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.2);
            color: white;
        }
        
        .amount-highlight {
            background: linear-gradient(135deg, #28a745, #20c997);
            color: white;
            padding: 15px;
            border-radius: 10px;
            text-align: center;
            font-size: 1.5rem;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="main-container">
        <div class="header-section">
            <h1><i class="bi bi-receipt-cutoff"></i> รายละเอียดคำขอเบิกจ่าย</h1>
            <h4 class="mb-0"><?php echo htmlspecialchars($expense_request['voucher_no']); ?></h4>
        </div>
        
        <div class="container-fluid p-4">
            <div class="mb-4">
                <a href="approval_dashboard.php" class="btn btn-back">
                    <i class="bi bi-arrow-left"></i> กลับไปหน้าอนุมัติ
                </a>
            </div>
            
            <div class="row">
                <!-- ข้อมูลพื้นฐาน -->
                <div class="col-lg-8">
                    <div class="card info-card">
                        <div class="info-card-header">
                            <h5><i class="bi bi-info-circle"></i> ข้อมูลพื้นฐาน</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="fw-bold text-muted">เลขที่ใบเบิก:</label>
                                    <p class="mb-0"><?php echo htmlspecialchars($expense_request['voucher_no']); ?></p>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="fw-bold text-muted">ประเภทใบเบิก:</label>
                                    <p class="mb-0"><?php echo htmlspecialchars($expense_request['voucher_type']); ?></p>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="fw-bold text-muted">วันที่ขอเบิก:</label>
                                    <p class="mb-0"><?php echo $request_date_formatted; ?></p>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="fw-bold text-muted">วันที่สร้างคำขอ:</label>
                                    <p class="mb-0"><?php echo $created_at_formatted; ?></p>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="fw-bold text-muted">จ่ายให้:</label>
                                    <p class="mb-0"><?php echo htmlspecialchars($expense_request['pay_to']); ?></p>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="fw-bold text-muted">แผนก:</label>
                                    <p class="mb-0"><?php echo htmlspecialchars($expense_request['department'] ?? 'ไม่ระบุ'); ?></p>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="fw-bold text-muted">Cost Center:</label>
                                    <p class="mb-0"><?php echo htmlspecialchars($expense_request['cost_center'] ?? 'ไม่ระบุ'); ?></p>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="fw-bold text-muted">ประเภทค่าใช้จ่าย:</label>
                                    <p class="mb-0"><?php echo $expense_types[$expense_request['expense_type']] ?? $expense_request['expense_type']; ?></p>
                                </div>
                                <?php if (!empty($expense_request['description'])): ?>
                                <div class="col-12 mb-3">
                                    <label class="fw-bold text-muted">รายละเอียดเพิ่มเติม:</label>
                                    <p class="mb-0"><?php echo htmlspecialchars($expense_request['description']); ?></p>
                                </div>
                                <?php endif; ?>
                                <?php if (!empty($expense_request['remarks'])): ?>
                                <div class="col-12 mb-3">
                                    <label class="fw-bold text-muted">หมายเหตุ:</label>
                                    <p class="mb-0"><?php echo htmlspecialchars($expense_request['remarks']); ?></p>
                                </div>
                                <?php endif; ?>
                                <?php if (!empty($updated_at_formatted)): ?>
                                <div class="col-12 mb-3">
                                    <label class="fw-bold text-muted">อัปเดตล่าสุด:</label>
                                    <p class="mb-0"><?php echo $updated_at_formatted; ?></p>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- ข้อมูลผู้ขอเบิก -->
                    <div class="card info-card">
                        <div class="info-card-header">
                            <h5><i class="bi bi-person"></i> ข้อมูลผู้ขอเบิก</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="fw-bold text-muted">รหัสพนักงาน:</label>
                                    <p class="mb-0"><?php echo htmlspecialchars($expense_request['requester_employee_id'] ?? 'ไม่ระบุ'); ?></p>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="fw-bold text-muted">ชื่อ-นามสกุล:</label>
                                    <p class="mb-0"><?php echo htmlspecialchars($expense_request['requester_name'] ?? 'ไม่ระบุ'); ?></p>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="fw-bold text-muted">ตำแหน่ง:</label>
                                    <p class="mb-0"><?php echo htmlspecialchars($expense_request['requester_position'] ?? 'ไม่ระบุ'); ?></p>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="fw-bold text-muted">แผนก:</label>
                                    <p class="mb-0"><?php echo htmlspecialchars($expense_request['requester_department'] ?? 'ไม่ระบุ'); ?></p>
                                </div>
                                <?php if (!empty($expense_request['requester_email'])): ?>
                                <div class="col-md-6 mb-3">
                                    <label class="fw-bold text-muted">อีเมล:</label>
                                    <p class="mb-0">
                                        <a href="mailto:<?php echo htmlspecialchars($expense_request['requester_email']); ?>">
                                            <?php echo htmlspecialchars($expense_request['requester_email']); ?>
                                        </a>
                                    </p>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- สถานะและจำนวนเงิน -->
                <div class="col-lg-4">
                    <div class="card info-card">
                        <div class="info-card-header">
                            <h5><i class="bi bi-currency-dollar"></i> จำนวนเงิน</h5>
                        </div>
                        <div class="card-body">
                            <div class="amount-highlight">
                                <?php echo number_format($expense_request['total_amount'], 2); ?> บาท
                            </div>
                        </div>
                    </div>
                    
                    <div class="card info-card">
                        <div class="info-card-header">
                            <h5><i class="bi bi-diagram-3"></i> สถานะการอนุมัติ</h5>
                        </div>
                        <div class="card-body">
                            <div class="text-center mb-3">
                                <span class="badge bg-<?php 
                                    echo $expense_request['overall_status'] === 'Approved' ? 'success' : 
                                        ($expense_request['overall_status'] === 'Rejected' ? 'danger' : 'warning'); 
                                ?> fs-6">
                                    <?php echo htmlspecialchars($expense_request['overall_status']); ?>
                                </span>
                            </div>
                            
                            <div class="approval-timeline">
                                <?php if (!empty($expense_request['approver1_name'])): ?>
                                <div class="timeline-item">
                                    <div class="timeline-icon <?php echo strtolower($expense_request['status_level1']); ?>">
                                        <?php if ($expense_request['status_level1'] === 'Approved'): ?>
                                            <i class="bi bi-check"></i>
                                        <?php elseif ($expense_request['status_level1'] === 'Rejected'): ?>
                                            <i class="bi bi-x"></i>
                                        <?php else: ?>
                                            <i class="bi bi-clock"></i>
                                        <?php endif; ?>
                                    </div>
                                    <div>
                                        <h6 class="mb-1">ระดับ 1: <?php echo htmlspecialchars($expense_request['approver1_name']); ?></h6>
                                        <p class="mb-1 text-muted"><?php echo htmlspecialchars($expense_request['approver1_position'] ?? ''); ?></p>
                                        <span class="badge bg-<?php 
                                            echo $expense_request['status_level1'] === 'Approved' ? 'success' : 
                                                ($expense_request['status_level1'] === 'Rejected' ? 'danger' : 'warning'); 
                                        ?>">
                                            <?php echo $expense_request['status_level1']; ?>
                                        </span>
                                    </div>
                                </div>
                                <?php endif; ?>
                                
                                <?php if (!empty($expense_request['approver2_name'])): ?>
                                <div class="timeline-item">
                                    <div class="timeline-icon <?php echo strtolower($expense_request['status_level2']); ?>">
                                        <?php if ($expense_request['status_level2'] === 'Approved'): ?>
                                            <i class="bi bi-check"></i>
                                        <?php elseif ($expense_request['status_level2'] === 'Rejected'): ?>
                                            <i class="bi bi-x"></i>
                                        <?php else: ?>
                                            <i class="bi bi-clock"></i>
                                        <?php endif; ?>
                                    </div>
                                    <div>
                                        <h6 class="mb-1">ระดับ 2: <?php echo htmlspecialchars($expense_request['approver2_name']); ?></h6>
                                        <p class="mb-1 text-muted"><?php echo htmlspecialchars($expense_request['approver2_position'] ?? ''); ?></p>
                                        <span class="badge bg-<?php 
                                            echo $expense_request['status_level2'] === 'Approved' ? 'success' : 
                                                ($expense_request['status_level2'] === 'Rejected' ? 'danger' : 'warning'); 
                                        ?>">
                                            <?php echo $expense_request['status_level2']; ?>
                                        </span>
                                    </div>
                                </div>
                                <?php endif; ?>
                                
                                <?php if (!empty($expense_request['approver3_name'])): ?>
                                <div class="timeline-item">
                                    <div class="timeline-icon <?php echo strtolower($expense_request['status_level3']); ?>">
                                        <?php if ($expense_request['status_level3'] === 'Approved'): ?>
                                            <i class="bi bi-check"></i>
                                        <?php elseif ($expense_request['status_level3'] === 'Rejected'): ?>
                                            <i class="bi bi-x"></i>
                                        <?php else: ?>
                                            <i class="bi bi-clock"></i>
                                        <?php endif; ?>
                                    </div>
                                    <div>
                                        <h6 class="mb-1">ระดับ 3: <?php echo htmlspecialchars($expense_request['approver3_name']); ?></h6>
                                        <p class="mb-1 text-muted"><?php echo htmlspecialchars($expense_request['approver3_position'] ?? ''); ?></p>
                                        <span class="badge bg-<?php 
                                            echo $expense_request['status_level3'] === 'Approved' ? 'success' : 
                                                ($expense_request['status_level3'] === 'Rejected' ? 'danger' : 'warning'); 
                                        ?>">
                                            <?php echo $expense_request['status_level3']; ?>
                                        </span>
                                    </div>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- รายการค่าใช้จ่าย -->
            <div class="card info-card">
                <div class="info-card-header">
                    <h5><i class="bi bi-list-ul"></i> รายการค่าใช้จ่าย</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($expense_items)): ?>
                        <div class="text-center text-muted py-5">
                            <i class="bi bi-inbox" style="font-size: 3rem;"></i>
                            <h6 class="mt-3">ไม่มีรายการค่าใช้จ่าย</h6>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-striped items-table">
                                <thead class="table-header">
                                    <tr>
                                        <th width="5%">#</th>
                                        <th width="50%">รายการ</th>
                                        <th width="20%">จำนวนเงิน</th>
                                        <th width="25%">หมายเหตุ</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $total_calculated = 0;
                                    foreach ($expense_items as $index => $item): 
                                        $total_calculated += $item['amount'];
                                    ?>
                                        <tr>
                                            <td><?php echo $index + 1; ?></td>
                                            <td><?php echo htmlspecialchars($item['item_description']); ?></td>
                                            <td class="text-end">
                                                <span class="fw-bold">
                                                    <?php echo number_format($item['amount'], 2); ?> บาท
                                                </span>
                                            </td>
                                            <td>
                                                <?php echo !empty($item['notes']) ? htmlspecialchars($item['notes']) : '<span class="text-muted">-</span>'; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                                <tfoot class="table-header">
                                    <tr>
                                        <th colspan="2" class="text-end">รวมทั้งสิ้น:</th>
                                        <th class="text-end">
                                            <?php echo number_format($total_calculated, 2); ?> บาท
                                        </th>
                                        <th></th>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                        
                        <?php if (abs($total_calculated - $expense_request['total_amount']) > 0.01): ?>
                            <div class="alert alert-warning mt-3">
                                <i class="bi bi-exclamation-triangle"></i>
                                <strong>แจ้งเตือน:</strong> ยอดรวมในรายการ (<?php echo number_format($total_calculated, 2); ?> บาท) 
                                ไม่ตรงกับยอดรวมที่ระบุ (<?php echo number_format($expense_request['total_amount'], 2); ?> บาท)
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="text-center mt-4">
                <a href="approval_dashboard.php" class="btn btn-back">
                    <i class="bi bi-arrow-left"></i> กลับไปหน้าอนุมัติ
                </a>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
