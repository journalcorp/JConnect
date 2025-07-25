<?php
session_start();
include_once '../includes/header.php';
include_once '../includes/menu.php';

// ตรวจสอบสิทธิ์การเข้าถึง
if (!isset($_SESSION['division']) || !in_array($_SESSION['division'], ['JD001', 'JD003'])) {
    echo '<div style="text-align:center;margin-top:50px;"><h3>คุณไม่มีสิทธิ์เข้าถึงหน้านี้</h3></div>';
    exit;
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Accounting Dashboard - ฝ่ายบัญชี</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Prompt', sans-serif;
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
        }
        .dashboard-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        .header-section {
            background: linear-gradient(135deg, #27ae60 0%, #229954 100%);
            color: white;
            padding: 2rem;
            border-radius: 15px;
            margin-bottom: 30px;
            box-shadow: 0 8px 25px rgba(39, 174, 96, 0.3);
        }
        .stats-card {
            background: white;
            border-radius: 15px;
            padding: 1.5rem;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            transition: transform 0.3s ease;
            margin-bottom: 20px;
            border-left: 4px solid #27ae60;
        }
        .stats-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.15);
        }
        .stats-icon {
            font-size: 2.5rem;
            color: #27ae60;
            margin-bottom: 1rem;
        }
        .stats-value {
            font-size: 2rem;
            font-weight: 700;
            color: #2c3e50;
            margin-bottom: 0.5rem;
        }
        .stats-label {
            color: #7f8c8d;
            font-size: 0.9rem;
            font-weight: 500;
        }
        .action-card {
            background: white;
            border-radius: 15px;
            padding: 1.5rem;
            text-align: center;
            transition: all 0.3s ease;
            text-decoration: none;
            color: inherit;
            display: block;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        .action-card:hover {
            transform: translateY(-5px);
            text-decoration: none;
            color: inherit;
            box-shadow: 0 10px 25px rgba(0,0,0,0.15);
        }
        .action-icon {
            font-size: 3rem;
            color: #27ae60;
            margin-bottom: 1rem;
        }
        .action-title {
            font-size: 1.1rem;
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 0.5rem;
        }
        .action-desc {
            color: #7f8c8d;
            font-size: 0.9rem;
        }
        .recent-activity {
            background: white;
            border-radius: 15px;
            padding: 1.5rem;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        .activity-item {
            padding: 1rem;
            border-bottom: 1px solid #ecf0f1;
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        .activity-item:last-child {
            border-bottom: none;
        }
        .activity-icon {
            background: #27ae60;
            color: white;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <!-- Header Section -->
        <div class="header-section">
            <h1><i class="bi bi-calculator me-3"></i>Accounting Dashboard</h1>
            <p class="mb-0">ระบบจัดการฝ่ายบัญชี - ยินดีต้อนรับ <?php echo htmlspecialchars($_SESSION['first_name_th'] . ' ' . $_SESSION['last_name_th']); ?></p>
        </div>

        <!-- Statistics Cards -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="stats-card">
                    <div class="stats-icon">
                        <i class="bi bi-receipt"></i>
                    </div>
                    <div class="stats-value">247</div>
                    <div class="stats-label">คำขอเบิกจ่ายรอดำเนินการ</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card">
                    <div class="stats-icon">
                        <i class="bi bi-currency-dollar"></i>
                    </div>
                    <div class="stats-value">฿125,430</div>
                    <div class="stats-label">ยอดเบิกจ่ายเดือนนี้</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card">
                    <div class="stats-icon">
                        <i class="bi bi-check-circle"></i>
                    </div>
                    <div class="stats-value">89</div>
                    <div class="stats-label">รายการที่อนุมัติแล้ว</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card">
                    <div class="stats-icon">
                        <i class="bi bi-exclamation-triangle"></i>
                    </div>
                    <div class="stats-value">12</div>
                    <div class="stats-label">รายการเกินงบประมาณ</div>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="row mb-4">
            <div class="col-12">
                <h3 class="mb-3"><i class="bi bi-lightning-fill me-2"></i>การดำเนินการด่วน</h3>
            </div>
            <div class="col-md-3">
                <a href="accounting_form.php" class="action-card">
                    <div class="action-icon">
                        <i class="bi bi-plus-circle"></i>
                    </div>
                    <div class="action-title">สร้างคำขอเบิกจ่าย</div>
                    <div class="action-desc">เพิ่มคำขอเบิกจ่ายใหม่</div>
                </a>
            </div>
            <div class="col-md-3">
                <a href="expense_approval.php" class="action-card">
                    <div class="action-icon">
                        <i class="bi bi-check-square"></i>
                    </div>
                    <div class="action-title">อนุมัติค่าใช้จ่าย</div>
                    <div class="action-desc">ตรวจสอบและอนุมัติรายการ</div>
                </a>
            </div>
            <div class="col-md-3">
                <a href="financial_reports.php" class="action-card">
                    <div class="action-icon">
                        <i class="bi bi-graph-up"></i>
                    </div>
                    <div class="action-title">รายงานการเงิน</div>
                    <div class="action-desc">ดูรายงานและสถิติ</div>
                </a>
            </div>
            <div class="col-md-3">
                <a href="budget_management.php" class="action-card">
                    <div class="action-icon">
                        <i class="bi bi-pie-chart"></i>
                    </div>
                    <div class="action-title">จัดการงบประมาณ</div>
                    <div class="action-desc">ติดตามและควบคุมงบประมาณ</div>
                </a>
            </div>
        </div>

        <!-- Recent Activity -->
        <div class="row">
            <div class="col-md-8">
                <div class="recent-activity">
                    <h4 class="mb-3"><i class="bi bi-clock-history me-2"></i>กิจกรรมล่าสุด</h4>
                    
                    <div class="activity-item">
                        <div class="activity-icon">
                            <i class="bi bi-plus"></i>
                        </div>
                        <div>
                            <div class="fw-semibold">คำขอเบิกค่าเดินทาง</div>
                            <small class="text-muted">สร้างโดย นาย สมชาย ใจดี - 5 นาทีที่แล้ว</small>
                        </div>
                    </div>

                    <div class="activity-item">
                        <div class="activity-icon">
                            <i class="bi bi-check"></i>
                        </div>
                        <div>
                            <div class="fw-semibold">อนุมัติค่าอุปกรณ์สำนักงาน</div>
                            <small class="text-muted">อนุมัติโดย คุณ วิรุณ ผู้จัดการ - 15 นาทีที่แล้ว</small>
                        </div>
                    </div>

                    <div class="activity-item">
                        <div class="activity-icon">
                            <i class="bi bi-file-text"></i>
                        </div>
                        <div>
                            <div class="fw-semibold">สร้างรายงานการเงินรายเดือน</div>
                            <small class="text-muted">สร้างโดย ระบบอัตโนมัติ - 1 ชั่วโมงที่แล้ว</small>
                        </div>
                    </div>

                    <div class="activity-item">
                        <div class="activity-icon">
                            <i class="bi bi-exclamation"></i>
                        </div>
                        <div>
                            <div class="fw-semibold">เตือนงบประมาณเกินแผน</div>
                            <small class="text-muted">แผนก IT เกินงบ 15% - 2 ชั่วโมงที่แล้ว</small>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-4">
                <div class="recent-activity">
                    <h4 class="mb-3"><i class="bi bi-calendar-check me-2"></i>ปฏิทินบัญชี</h4>
                    
                    <div class="activity-item">
                        <div class="activity-icon" style="background: #e74c3c;">
                            <i class="bi bi-calendar-date"></i>
                        </div>
                        <div>
                            <div class="fw-semibold">ปิดบัญชีประจำเดือน</div>
                            <small class="text-muted">วันที่ 31 ม.ค. 2568</small>
                        </div>
                    </div>

                    <div class="activity-item">
                        <div class="activity-icon" style="background: #f39c12;">
                            <i class="bi bi-file-earmark-ruled"></i>
                        </div>
                        <div>
                            <div class="fw-semibold">ยื่นแบบ ภงด.1</div>
                            <small class="text-muted">วันที่ 31 มี.ค. 2568</small>
                        </div>
                    </div>

                    <div class="activity-item">
                        <div class="activity-icon" style="background: #3498db;">
                            <i class="bi bi-graph-up"></i>
                        </div>
                        <div>
                            <div class="fw-semibold">ประเมินงบประมาณปีใหม่</div>
                            <small class="text-muted">วันที่ 15 ก.พ. 2568</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
