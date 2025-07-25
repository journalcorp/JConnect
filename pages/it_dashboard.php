<?php
header('Content-Type: text/html; charset=UTF-8');
session_start();
include_once '../includes/header.php';
include_once '../includes/menu.php';
include_once '../config.php';

// ตรวจสอบสิทธิ์การเข้าถึง - เฉพาะ IT เท่านั้น
if (!isset($_SESSION['division']) || $_SESSION['division'] !== 'JD001' || 
    !in_array($_SESSION['role'] ?? 'employee', ['admin', 'hr', 'manager'])) {
    echo '<div style="text-align:center;margin-top:50px;"><h3>คุณไม่มีสิทธิ์เข้าถึงหน้านี้ (เฉพาะ IT Admin)</h3></div>';
    exit;
}

// ดึงข้อมูลผู้ใช้จาก session
$user_employee_id = $_SESSION['employee_id'] ?? null;
$user_division = $_SESSION['division'] ?? null;

// สร้างการเชื่อมต่อฐานข้อมูล
try {
    $conn = dbConnect();
    $database_available = true;
} catch (Exception $e) {
    $database_available = false;
    error_log("Database connection failed: " . $e->getMessage());
}

// ข้อมูลสถิติ
$stats = [
    'total_requests' => 0,
    'pending_requests' => 0,
    'approved_requests' => 0,
    'rejected_requests' => 0,
    'total_amount' => 0,
    'approved_amount' => 0,
    'pending_amount' => 0,
    'today_requests' => 0,
    'this_month_requests' => 0
];

// ข้อมูลแดshboard
$recent_requests = [];
$approval_summary = [];
$monthly_stats = [];

if ($database_available) {
    try {
        if (is_resource($conn)) {
            // SQLSRV Connection
            
            // สถิติทั่วไป
            $sql_stats = "
                SELECT 
                    COUNT(*) as total_requests,
                    SUM(CASE WHEN overall_status = 'Pending' THEN 1 ELSE 0 END) as pending_requests,
                    SUM(CASE WHEN overall_status = 'Approved' THEN 1 ELSE 0 END) as approved_requests,
                    SUM(CASE WHEN overall_status = 'Rejected' THEN 1 ELSE 0 END) as rejected_requests,
                    SUM(total_amount) as total_amount,
                    SUM(CASE WHEN overall_status = 'Approved' THEN total_amount ELSE 0 END) as approved_amount,
                    SUM(CASE WHEN overall_status = 'Pending' THEN total_amount ELSE 0 END) as pending_amount,
                    SUM(CASE WHEN CAST(created_at AS DATE) = CAST(GETDATE() AS DATE) THEN 1 ELSE 0 END) as today_requests,
                    SUM(CASE WHEN YEAR(created_at) = YEAR(GETDATE()) AND MONTH(created_at) = MONTH(GETDATE()) THEN 1 ELSE 0 END) as this_month_requests
                FROM dbo.ExpenseRequests
            ";
            
            $result_stats = sqlsrv_query($conn, $sql_stats);
            if ($result_stats) {
                $stats = sqlsrv_fetch_array($result_stats, SQLSRV_FETCH_ASSOC);
            }
            
            // คำขอล่าสุด
            $sql_recent = "
                SELECT TOP 10
                    er.id,
                    er.voucher_no,
                    er.pay_to,
                    er.total_amount,
                    er.overall_status,
                    er.created_at,
                    req.first_name_th + ' ' + req.last_name_th as requester_name
                FROM dbo.ExpenseRequests er
                LEFT JOIN dbo.employees req ON er.requester_id = req.employee_id
                ORDER BY er.created_at DESC
            ";
            
            $result_recent = sqlsrv_query($conn, $sql_recent);
            if ($result_recent) {
                while ($row = sqlsrv_fetch_array($result_recent, SQLSRV_FETCH_ASSOC)) {
                    $created_at = $row['created_at'];
                    if ($created_at instanceof DateTime) {
                        $row['created_at_formatted'] = $created_at->format('d/m/Y H:i:s');
                    } else {
                        $row['created_at_formatted'] = date('d/m/Y H:i:s', strtotime($row['created_at']));
                    }
                    $recent_requests[] = $row;
                }
            }
            
            // สถิติผู้อนุมัติ
            $sql_approvers = "
                SELECT 
                    app.first_name_th + ' ' + app.last_name_th as approver_name,
                    COUNT(*) as total_approvals,
                    SUM(CASE WHEN er.status_level1 = 'Approved' AND er.approver_level1 = app.employee_id THEN 1 ELSE 0 END) as level1_approved,
                    SUM(CASE WHEN er.status_level2 = 'Approved' AND er.approver_level2 = app.employee_id THEN 1 ELSE 0 END) as level2_approved,
                    SUM(CASE WHEN er.status_level3 = 'Approved' AND er.approver_level3 = app.employee_id THEN 1 ELSE 0 END) as level3_approved,
                    SUM(CASE WHEN er.status_level1 = 'Rejected' AND er.approver_level1 = app.employee_id THEN 1 ELSE 0 END) as level1_rejected,
                    SUM(CASE WHEN er.status_level2 = 'Rejected' AND er.approver_level2 = app.employee_id THEN 1 ELSE 0 END) as level2_rejected,
                    SUM(CASE WHEN er.status_level3 = 'Rejected' AND er.approver_level3 = app.employee_id THEN 1 ELSE 0 END) as level3_rejected
                FROM dbo.employees app
                INNER JOIN dbo.ExpenseRequests er ON (
                    app.employee_id = er.approver_level1 OR 
                    app.employee_id = er.approver_level2 OR 
                    app.employee_id = er.approver_level3
                )
                GROUP BY app.employee_id, app.first_name_th, app.last_name_th
                ORDER BY total_approvals DESC
            ";
            
            $result_approvers = sqlsrv_query($conn, $sql_approvers);
            if ($result_approvers) {
                while ($row = sqlsrv_fetch_array($result_approvers, SQLSRV_FETCH_ASSOC)) {
                    $approval_summary[] = $row;
                }
            }
            
            // สถิติรายเดือน (6 เดือนล่าสุด)
            $sql_monthly = "
                SELECT 
                    YEAR(created_at) as year,
                    MONTH(created_at) as month,
                    COUNT(*) as request_count,
                    SUM(total_amount) as total_amount,
                    SUM(CASE WHEN overall_status = 'Approved' THEN 1 ELSE 0 END) as approved_count,
                    SUM(CASE WHEN overall_status = 'Approved' THEN total_amount ELSE 0 END) as approved_amount
                FROM dbo.ExpenseRequests
                WHERE created_at >= DATEADD(MONTH, -6, GETDATE())
                GROUP BY YEAR(created_at), MONTH(created_at)
                ORDER BY YEAR(created_at) DESC, MONTH(created_at) DESC
            ";
            
            $result_monthly = sqlsrv_query($conn, $sql_monthly);
            if ($result_monthly) {
                while ($row = sqlsrv_fetch_array($result_monthly, SQLSRV_FETCH_ASSOC)) {
                    $row['month_name'] = [
                        1 => 'ม.ค.', 2 => 'ก.พ.', 3 => 'มี.ค.', 4 => 'เม.ย.',
                        5 => 'พ.ค.', 6 => 'มิ.ย.', 7 => 'ก.ค.', 8 => 'ส.ค.',
                        9 => 'ก.ย.', 10 => 'ต.ค.', 11 => 'พ.ย.', 12 => 'ธ.ค.'
                    ][$row['month']];
                    $monthly_stats[] = $row;
                }
            }
            
        } else if (is_object($conn)) {
            $conn_type = get_class($conn);
            if (strpos($conn_type, 'PDO') !== false) {
                // PDO Connection - คล้ายกับ SQLSRV แต่ใช้ syntax ของ PDO
                
                // สถิติทั่วไป
                $sql_stats = "
                    SELECT 
                        COUNT(*) as total_requests,
                        SUM(CASE WHEN overall_status = 'Pending' THEN 1 ELSE 0 END) as pending_requests,
                        SUM(CASE WHEN overall_status = 'Approved' THEN 1 ELSE 0 END) as approved_requests,
                        SUM(CASE WHEN overall_status = 'Rejected' THEN 1 ELSE 0 END) as rejected_requests,
                        SUM(total_amount) as total_amount,
                        SUM(CASE WHEN overall_status = 'Approved' THEN total_amount ELSE 0 END) as approved_amount,
                        SUM(CASE WHEN overall_status = 'Pending' THEN total_amount ELSE 0 END) as pending_amount,
                        SUM(CASE WHEN DATE(created_at) = CURDATE() THEN 1 ELSE 0 END) as today_requests,
                        SUM(CASE WHEN YEAR(created_at) = YEAR(NOW()) AND MONTH(created_at) = MONTH(NOW()) THEN 1 ELSE 0 END) as this_month_requests
                    FROM dbo.ExpenseRequests
                ";
                
                $stmt_stats = $conn->prepare($sql_stats);
                $stmt_stats->execute();
                $stats = $stmt_stats->fetch(PDO::FETCH_ASSOC);
                
                // คำขอล่าสุด
                $sql_recent = "
                    SELECT 
                        er.id,
                        er.voucher_no,
                        er.pay_to,
                        er.total_amount,
                        er.overall_status,
                        er.created_at,
                        CONCAT(req.first_name_th, ' ', req.last_name_th) as requester_name
                    FROM dbo.ExpenseRequests er
                    LEFT JOIN dbo.employees req ON er.requester_id = req.employee_id
                    ORDER BY er.created_at DESC
                    LIMIT 10
                ";
                
                $stmt_recent = $conn->prepare($sql_recent);
                $stmt_recent->execute();
                
                while ($row = $stmt_recent->fetch(PDO::FETCH_ASSOC)) {
                    $row['created_at_formatted'] = date('d/m/Y H:i:s', strtotime($row['created_at']));
                    $recent_requests[] = $row;
                }
                
                // สถิติผู้อนุมัติ
                $sql_approvers = "
                    SELECT 
                        CONCAT(app.first_name_th, ' ', app.last_name_th) as approver_name,
                        COUNT(*) as total_approvals,
                        SUM(CASE WHEN er.status_level1 = 'Approved' AND er.approver_level1 = app.employee_id THEN 1 ELSE 0 END) as level1_approved,
                        SUM(CASE WHEN er.status_level2 = 'Approved' AND er.approver_level2 = app.employee_id THEN 1 ELSE 0 END) as level2_approved,
                        SUM(CASE WHEN er.status_level3 = 'Approved' AND er.approver_level3 = app.employee_id THEN 1 ELSE 0 END) as level3_approved,
                        SUM(CASE WHEN er.status_level1 = 'Rejected' AND er.approver_level1 = app.employee_id THEN 1 ELSE 0 END) as level1_rejected,
                        SUM(CASE WHEN er.status_level2 = 'Rejected' AND er.approver_level2 = app.employee_id THEN 1 ELSE 0 END) as level2_rejected,
                        SUM(CASE WHEN er.status_level3 = 'Rejected' AND er.approver_level3 = app.employee_id THEN 1 ELSE 0 END) as level3_rejected
                    FROM dbo.employees app
                    INNER JOIN dbo.ExpenseRequests er ON (
                        app.employee_id = er.approver_level1 OR 
                        app.employee_id = er.approver_level2 OR 
                        app.employee_id = er.approver_level3
                    )
                    GROUP BY app.employee_id, app.first_name_th, app.last_name_th
                    ORDER BY total_approvals DESC
                ";
                
                $stmt_approvers = $conn->prepare($sql_approvers);
                $stmt_approvers->execute();
                $approval_summary = $stmt_approvers->fetchAll(PDO::FETCH_ASSOC);
                
                // สถิติรายเดือน (6 เดือนล่าสุด)
                $sql_monthly = "
                    SELECT 
                        YEAR(created_at) as year,
                        MONTH(created_at) as month,
                        COUNT(*) as request_count,
                        SUM(total_amount) as total_amount,
                        SUM(CASE WHEN overall_status = 'Approved' THEN 1 ELSE 0 END) as approved_count,
                        SUM(CASE WHEN overall_status = 'Approved' THEN total_amount ELSE 0 END) as approved_amount
                    FROM dbo.ExpenseRequests
                    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
                    GROUP BY YEAR(created_at), MONTH(created_at)
                    ORDER BY YEAR(created_at) DESC, MONTH(created_at) DESC
                ";
                
                $stmt_monthly = $conn->prepare($sql_monthly);
                $stmt_monthly->execute();
                
                while ($row = $stmt_monthly->fetch(PDO::FETCH_ASSOC)) {
                    $row['month_name'] = [
                        1 => 'ม.ค.', 2 => 'ก.พ.', 3 => 'มี.ค.', 4 => 'เม.ย.',
                        5 => 'พ.ค.', 6 => 'มิ.ย.', 7 => 'ก.ค.', 8 => 'ส.ค.',
                        9 => 'ก.ย.', 10 => 'ต.ค.', 11 => 'พ.ย.', 12 => 'ธ.ค.'
                    ][$row['month']];
                    $monthly_stats[] = $row;
                }
            }
        }
    } catch (Exception $e) {
        error_log("Error fetching dashboard data: " . $e->getMessage());
    }
}

// Mock data หากไม่มีฐานข้อมูล
if (!$database_available) {
    $stats = [
        'total_requests' => 25,
        'pending_requests' => 8,
        'approved_requests' => 15,
        'rejected_requests' => 2,
        'total_amount' => 125000,
        'approved_amount' => 98000,
        'pending_amount' => 24000,
        'today_requests' => 3,
        'this_month_requests' => 12
    ];
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>แดชบอร์ดรายงาน IT - ระบบเบิกจ่าย</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
            max-width: 1400px;
            overflow: hidden;
        }
        
        .header-section {
            background: linear-gradient(135deg, #6f42c1, #e83e8c);
            color: white;
            padding: 30px;
            text-align: center;
        }
        
        .stats-card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
            overflow: hidden;
            transition: all 0.3s ease;
            height: 100%;
        }
        
        .stats-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 40px rgba(0,0,0,0.15);
        }
        
        .stats-card.primary {
            background: linear-gradient(135deg, #007bff, #0056b3);
            color: white;
        }
        
        .stats-card.success {
            background: linear-gradient(135deg, #28a745, #20c997);
            color: white;
        }
        
        .stats-card.warning {
            background: linear-gradient(135deg, #ffc107, #fd7e14);
            color: white;
        }
        
        .stats-card.danger {
            background: linear-gradient(135deg, #dc3545, #c82333);
            color: white;
        }
        
        .stats-card.info {
            background: linear-gradient(135deg, #17a2b8, #138496);
            color: white;
        }
        
        .stats-card.secondary {
            background: linear-gradient(135deg, #6c757d, #495057);
            color: white;
        }
        
        .stats-number {
            font-size: 2.5rem;
            font-weight: bold;
            margin-bottom: 10px;
        }
        
        .stats-label {
            font-size: 1rem;
            opacity: 0.9;
        }
        
        .stats-icon {
            font-size: 3rem;
            opacity: 0.8;
            position: absolute;
            top: 20px;
            right: 20px;
        }
        
        .chart-container {
            position: relative;
            height: 300px;
            margin: 20px 0;
        }
        
        .dashboard-section {
            background: white;
            border-radius: 15px;
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
            margin-bottom: 20px;
            overflow: hidden;
        }
        
        .section-header {
            background: linear-gradient(135deg, #f8f9fa, #e9ecef);
            border-bottom: 3px solid #007bff;
            padding: 20px;
            font-weight: 600;
        }
        
        .table-responsive {
            border-radius: 10px;
            overflow: hidden;
        }
        
        .btn-dashboard {
            background: linear-gradient(135deg, #007bff, #0056b3);
            border: none;
            color: white;
            padding: 10px 25px;
            border-radius: 25px;
            transition: all 0.3s ease;
            margin: 5px;
        }
        
        .btn-dashboard:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.2);
            color: white;
        }
        
        .status-approved { color: #28a745; }
        .status-pending { color: #ffc107; }
        .status-rejected { color: #dc3545; }
    </style>
</head>
<body>
    <div class="main-container">
        <div class="header-section">
            <h1><i class="bi bi-graph-up-arrow"></i> แดชบอร์ดรายงาน IT</h1>
            <p class="mb-0">ภาพรวมระบบเบิกจ่ายและการอนุมัติ</p>
        </div>
        
        <div class="container-fluid p-4">
            <!-- สถิติหลัก -->
            <div class="row mb-4">
                <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
                    <div class="card stats-card primary">
                        <div class="card-body position-relative">
                            <div class="stats-icon">
                                <i class="bi bi-receipt-cutoff"></i>
                            </div>
                            <div class="stats-number"><?php echo number_format($stats['total_requests'] ?? 0); ?></div>
                            <div class="stats-label">คำขอทั้งหมด</div>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
                    <div class="card stats-card warning">
                        <div class="card-body position-relative">
                            <div class="stats-icon">
                                <i class="bi bi-hourglass-split"></i>
                            </div>
                            <div class="stats-number"><?php echo number_format($stats['pending_requests'] ?? 0); ?></div>
                            <div class="stats-label">รออนุมัติ</div>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
                    <div class="card stats-card success">
                        <div class="card-body position-relative">
                            <div class="stats-icon">
                                <i class="bi bi-check-circle"></i>
                            </div>
                            <div class="stats-number"><?php echo number_format($stats['approved_requests'] ?? 0); ?></div>
                            <div class="stats-label">อนุมัติแล้ว</div>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
                    <div class="card stats-card danger">
                        <div class="card-body position-relative">
                            <div class="stats-icon">
                                <i class="bi bi-x-circle"></i>
                            </div>
                            <div class="stats-number"><?php echo number_format($stats['rejected_requests'] ?? 0); ?></div>
                            <div class="stats-label">ปฏิเสธ</div>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
                    <div class="card stats-card info">
                        <div class="card-body position-relative">
                            <div class="stats-icon">
                                <i class="bi bi-calendar-today"></i>
                            </div>
                            <div class="stats-number"><?php echo number_format($stats['today_requests'] ?? 0); ?></div>
                            <div class="stats-label">วันนี้</div>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
                    <div class="card stats-card secondary">
                        <div class="card-body position-relative">
                            <div class="stats-icon">
                                <i class="bi bi-calendar-month"></i>
                            </div>
                            <div class="stats-number"><?php echo number_format($stats['this_month_requests'] ?? 0); ?></div>
                            <div class="stats-label">เดือนนี้</div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- สถิติทางการเงิน -->
            <div class="row mb-4">
                <div class="col-lg-4 col-md-4 mb-3">
                    <div class="card stats-card primary">
                        <div class="card-body text-center">
                            <div class="stats-number"><?php echo number_format($stats['total_amount'] ?? 0, 2); ?></div>
                            <div class="stats-label">ยอดรวมทั้งหมด (บาท)</div>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-4 col-md-4 mb-3">
                    <div class="card stats-card success">
                        <div class="card-body text-center">
                            <div class="stats-number"><?php echo number_format($stats['approved_amount'] ?? 0, 2); ?></div>
                            <div class="stats-label">ยอดที่อนุมัติ (บาท)</div>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-4 col-md-4 mb-3">
                    <div class="card stats-card warning">
                        <div class="card-body text-center">
                            <div class="stats-number"><?php echo number_format($stats['pending_amount'] ?? 0, 2); ?></div>
                            <div class="stats-label">ยอดรออนุมัติ (บาท)</div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="row">
                <!-- กราฟสถิติ -->
                <div class="col-lg-6 mb-4">
                    <div class="dashboard-section">
                        <div class="section-header">
                            <h5><i class="bi bi-pie-chart"></i> สัดส่วนสถานะคำขอ</h5>
                        </div>
                        <div class="p-4">
                            <div class="chart-container">
                                <canvas id="statusChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- กราฟรายเดือน -->
                <div class="col-lg-6 mb-4">
                    <div class="dashboard-section">
                        <div class="section-header">
                            <h5><i class="bi bi-bar-chart"></i> สถิติรายเดือน</h5>
                        </div>
                        <div class="p-4">
                            <div class="chart-container">
                                <canvas id="monthlyChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="row">
                <!-- คำขอล่าสุด -->
                <div class="col-lg-8 mb-4">
                    <div class="dashboard-section">
                        <div class="section-header d-flex justify-content-between align-items-center">
                            <h5><i class="bi bi-clock-history"></i> คำขอล่าสุด</h5>
                            <a href="approval_dashboard.php" class="btn btn-dashboard btn-sm">
                                <i class="bi bi-eye"></i> ดูทั้งหมด
                            </a>
                        </div>
                        <div class="p-4">
                            <?php if (empty($recent_requests)): ?>
                                <div class="text-center text-muted py-5">
                                    <i class="bi bi-inbox" style="font-size: 3rem;"></i>
                                    <h6 class="mt-3">ไม่มีคำขอในระบบ</h6>
                                </div>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-striped">
                                        <thead class="table-dark">
                                            <tr>
                                                <th>เลขที่ใบเบิก</th>
                                                <th>ผู้ขอเบิก</th>
                                                <th>จ่ายให้</th>
                                                <th>จำนวนเงิน</th>
                                                <th>สถานะ</th>
                                                <th>วันที่</th>
                                                <th>ดำเนินการ</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($recent_requests as $request): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($request['voucher_no']); ?></td>
                                                    <td><?php echo htmlspecialchars($request['requester_name'] ?? 'ไม่ระบุ'); ?></td>
                                                    <td><?php echo htmlspecialchars($request['pay_to']); ?></td>
                                                    <td class="text-end"><?php echo number_format($request['total_amount'], 2); ?></td>
                                                    <td>
                                                        <span class="badge bg-<?php 
                                                            echo $request['overall_status'] === 'Approved' ? 'success' : 
                                                                ($request['overall_status'] === 'Rejected' ? 'danger' : 'warning'); 
                                                        ?>">
                                                            <?php echo $request['overall_status']; ?>
                                                        </span>
                                                    </td>
                                                    <td><?php echo $request['created_at_formatted']; ?></td>
                                                    <td>
                                                        <a href="expense_detail.php?id=<?php echo $request['id']; ?>" 
                                                           class="btn btn-outline-primary btn-sm">
                                                            <i class="bi bi-eye"></i>
                                                        </a>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <!-- สถิติผู้อนุมัติ -->
                <div class="col-lg-4 mb-4">
                    <div class="dashboard-section">
                        <div class="section-header">
                            <h5><i class="bi bi-people"></i> สถิติผู้อนุมัติ</h5>
                        </div>
                        <div class="p-4">
                            <?php if (empty($approval_summary)): ?>
                                <div class="text-center text-muted py-5">
                                    <i class="bi bi-person-x" style="font-size: 3rem;"></i>
                                    <h6 class="mt-3">ไม่มีข้อมูลผู้อนุมัติ</h6>
                                </div>
                            <?php else: ?>
                                <?php foreach (array_slice($approval_summary, 0, 5) as $approver): ?>
                                    <div class="mb-3 p-3 border rounded">
                                        <h6 class="mb-2"><?php echo htmlspecialchars($approver['approver_name']); ?></h6>
                                        <div class="row text-center">
                                            <div class="col-4">
                                                <small class="text-muted">อนุมัติ</small><br>
                                                <span class="badge bg-success">
                                                    <?php echo ($approver['level1_approved'] + $approver['level2_approved'] + $approver['level3_approved']); ?>
                                                </span>
                                            </div>
                                            <div class="col-4">
                                                <small class="text-muted">ปฏิเสธ</small><br>
                                                <span class="badge bg-danger">
                                                    <?php echo ($approver['level1_rejected'] + $approver['level2_rejected'] + $approver['level3_rejected']); ?>
                                                </span>
                                            </div>
                                            <div class="col-4">
                                                <small class="text-muted">รวม</small><br>
                                                <span class="badge bg-primary">
                                                    <?php echo $approver['total_approvals']; ?>
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- ปุ่มเมนู -->
            <div class="text-center mt-4">
                <a href="approval_dashboard.php" class="btn btn-dashboard">
                    <i class="bi bi-clipboard-check"></i> ระบบอนุมัติ
                </a>
                <a href="accounting_form.php" class="btn btn-dashboard">
                    <i class="bi bi-plus-circle"></i> สร้างคำขอใหม่
                </a>
                <a href="#" class="btn btn-dashboard" onclick="exportReport()">
                    <i class="bi bi-download"></i> ส่งออกรายงาน
                </a>
                <a href="#" class="btn btn-dashboard" onclick="refreshData()">
                    <i class="bi bi-arrow-clockwise"></i> รีเฟรชข้อมูล
                </a>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // กราฟสัดส่วนสถานะ
        const statusChart = new Chart(document.getElementById('statusChart'), {
            type: 'doughnut',
            data: {
                labels: ['รออนุมัติ', 'อนุมัติแล้ว', 'ปฏิเสธ'],
                datasets: [{
                    data: [
                        <?php echo $stats['pending_requests'] ?? 0; ?>,
                        <?php echo $stats['approved_requests'] ?? 0; ?>,
                        <?php echo $stats['rejected_requests'] ?? 0; ?>
                    ],
                    backgroundColor: [
                        '#ffc107',
                        '#28a745',
                        '#dc3545'
                    ],
                    borderWidth: 2,
                    borderColor: '#fff'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            padding: 20,
                            usePointStyle: true
                        }
                    }
                }
            }
        });
        
        // กราฟรายเดือน
        const monthlyChart = new Chart(document.getElementById('monthlyChart'), {
            type: 'bar',
            data: {
                labels: [
                    <?php 
                    if (!empty($monthly_stats)) {
                        foreach (array_reverse($monthly_stats) as $stat) {
                            echo "'" . $stat['month_name'] . " " . ($stat['year'] + 543) . "',";
                        }
                    }
                    ?>
                ],
                datasets: [{
                    label: 'จำนวนคำขอ',
                    data: [
                        <?php 
                        if (!empty($monthly_stats)) {
                            foreach (array_reverse($monthly_stats) as $stat) {
                                echo $stat['request_count'] . ",";
                            }
                        }
                        ?>
                    ],
                    backgroundColor: '#007bff',
                    borderColor: '#0056b3',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1
                        }
                    }
                },
                plugins: {
                    legend: {
                        display: false
                    }
                }
            }
        });
        
        function exportReport() {
            alert('ฟีเจอร์ส่งออกรายงานจะพัฒนาในเวอร์ชันต่อไป');
        }
        
        function refreshData() {
            location.reload();
        }
        
        // Auto refresh every 5 minutes
        setTimeout(() => {
            location.reload();
        }, 300000);
    </script>
</body>
</html>
