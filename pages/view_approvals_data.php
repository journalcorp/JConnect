<?php
header('Content-Type: text/html; charset=UTF-8');
session_start();
include_once '../includes/header.php';
include_once '../includes/menu.php';
include_once '../config.php';

// ตรวจสอบการเชื่อมต่อฐานข้อมูล
$conn = dbConnect();

if (!$conn) {
    die("ไม่สามารถเชื่อมต่อฐานข้อมูลได้");
}

// ดึงข้อมูลจากตาราง approvals พร้อม JOIN กับตารางอื่น
$approval_data = [];
try {
    if (is_resource($conn)) {
        // SQLSRV Connection
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
                e1.first_name_th as level1_first, 
                e1.last_name_th as level1_last, 
                e1.position_th as level1_position,
                e1.division as level1_division,
                e2.first_name_th as level2_first, 
                e2.last_name_th as level2_last, 
                e2.position_th as level2_position,
                e2.division as level2_division,
                e3.first_name_th as level3_first, 
                e3.last_name_th as level3_last, 
                e3.position_th as level3_position,
                e3.division as level3_division
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
                $approval_data[] = $row;
            }
        } else {
            $errors = sqlsrv_errors();
            error_log("SQLSRV Error: " . print_r($errors, true));
        }
    } else if (is_object($conn)) {
        // PDO Connection
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
                e1.first_name_th as level1_first, 
                e1.last_name_th as level1_last, 
                e1.position_th as level1_position,
                e1.division as level1_division,
                e2.first_name_th as level2_first, 
                e2.last_name_th as level2_last, 
                e2.position_th as level2_position,
                e2.division as level2_division,
                e3.first_name_th as level3_first, 
                e3.last_name_th as level3_last, 
                e3.position_th as level3_position,
                e3.division as level3_division
            FROM approvals a
            LEFT JOIN dept d ON a.DeptID = d.DeptID
            LEFT JOIN Employees e1 ON a.ApprovalLevel1 = e1.employee_id
            LEFT JOIN Employees e2 ON a.ApprovalLevel2 = e2.employee_id
            LEFT JOIN Employees e3 ON a.ApprovalLevel3 = e3.employee_id
            ORDER BY a.ApprovalID
        ";
        
        $stmt = $conn->prepare($sql);
        $stmt->execute();
        $approval_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Exception $e) {
    error_log("Error fetching approvals data: " . $e->getMessage());
}

// ดึงข้อมูลสถิติ
$total_approvals = count($approval_data);
$departments_with_approval = array_unique(array_filter(array_column($approval_data, 'DeptName')));
$total_departments = count($departments_with_approval);

// นับจำนวนผู้อนุมัติในแต่ละระดับ
$level1_count = count(array_filter($approval_data, function($item) { return !empty($item['ApprovalLevel1']); }));
$level2_count = count(array_filter($approval_data, function($item) { return !empty($item['ApprovalLevel2']); }));
$level3_count = count(array_filter($approval_data, function($item) { return !empty($item['ApprovalLevel3']); }));
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ข้อมูลตาราง Approvals - Journal Portal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Prompt', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
        }
        .container-fluid {
            padding: 20px;
        }
        .card {
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            border: none;
            background: white;
            margin-bottom: 2rem;
        }
        .card-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 20px 20px 0 0 !important;
            padding: 2rem;
            border: none;
        }
        .page-title {
            display: flex;
            align-items: center;
            margin-bottom: 0;
        }
        .page-title i {
            font-size: 2rem;
            margin-right: 1rem;
        }
        .stats-card {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border-radius: 15px;
            padding: 1.5rem;
            text-align: center;
            height: 100%;
        }
        .stats-number {
            font-size: 2.5rem;
            font-weight: 700;
            color: #667eea;
            margin-bottom: 0.5rem;
        }
        .stats-label {
            color: #6c757d;
            font-weight: 500;
        }
        .table-responsive {
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }
        .table {
            margin-bottom: 0;
        }
        .table th {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            font-weight: 600;
            padding: 1rem;
        }
        .table td {
            padding: 1rem;
            vertical-align: middle;
            border-color: #f1f3f4;
        }
        .badge-custom {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 25px;
            font-weight: 500;
            margin: 0.2rem;
            display: inline-block;
        }
        .amount-badge {
            background: linear-gradient(135deg, #ffc107 0%, #fd7e14 100%);
            color: #333;
            padding: 0.4rem 0.8rem;
            border-radius: 20px;
            font-weight: 600;
            font-size: 0.85rem;
        }
        .no-limit {
            background: linear-gradient(135deg, #6c757d 0%, #495057 100%);
            color: white;
        }
        .approval-structure {
            background: linear-gradient(135deg, #e8f5e8 0%, #f0f8f0 100%);
            border-left: 4px solid #28a745;
            padding: 1rem;
            border-radius: 0 10px 10px 0;
            margin: 0.5rem 0;
        }
        .level-info {
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 0.5rem;
            margin-bottom: 0.5rem;
        }
        .employee-info {
            background: white;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 0.5rem 0.8rem;
            font-size: 0.9rem;
        }
        .search-box {
            background: white;
            border-radius: 10px;
            padding: 1rem;
            margin-bottom: 1rem;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }
        .btn-export {
            background: linear-gradient(135deg, #17a2b8 0%, #138496 100%);
            border: none;
            color: white;
            padding: 0.75rem 1.5rem;
            border-radius: 10px;
            font-weight: 600;
        }
        .btn-refresh {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            border: none;
            color: white;
            padding: 0.75rem 1.5rem;
            border-radius: 10px;
            font-weight: 600;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <!-- Header -->
        <div class="card">
            <div class="card-header">
                <h2 class="page-title">
                    <i class="bi bi-table"></i>
                    ข้อมูลตาราง Approvals
                </h2>
                <p class="mb-0 mt-2 opacity-75">แสดงข้อมูลโครงสร้างการอนุมัติทั้งหมดในระบบ</p>
            </div>
        </div>

        <!-- Statistics -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="stats-card">
                    <div class="stats-number"><?php echo $total_approvals; ?></div>
                    <div class="stats-label">โครงสร้างการอนุมัติทั้งหมด</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card">
                    <div class="stats-number"><?php echo $total_departments; ?></div>
                    <div class="stats-label">แผนกที่มีการอนุมัติ</div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="stats-card">
                    <div class="stats-number"><?php echo $level1_count; ?></div>
                    <div class="stats-label">อนุมัติระดับ 1</div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="stats-card">
                    <div class="stats-number"><?php echo $level2_count; ?></div>
                    <div class="stats-label">อนุมัติระดับ 2</div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="stats-card">
                    <div class="stats-number"><?php echo $level3_count; ?></div>
                    <div class="stats-label">อนุมัติระดับ 3</div>
                </div>
            </div>
        </div>

        <!-- Search and Actions -->
        <div class="search-box">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-search"></i></span>
                        <input type="text" class="form-control" id="searchInput" placeholder="ค้นหาตามชื่อโครงสร้าง, แผนก, หรือผู้อนุมัติ">
                    </div>
                </div>
                <div class="col-md-6 text-end">
                    <button class="btn btn-refresh me-2" onclick="location.reload()">
                        <i class="bi bi-arrow-clockwise me-1"></i>รีเฟรช
                    </button>
                    <button class="btn btn-export" onclick="exportToCSV()">
                        <i class="bi bi-download me-1"></i>ส่งออก CSV
                    </button>
                </div>
            </div>
        </div>

        <!-- Data Table -->
        <div class="card">
            <div class="card-body p-0">
                <?php if (empty($approval_data)): ?>
                    <div class="alert alert-info m-4">
                        <i class="bi bi-info-circle me-2"></i>
                        ไม่พบข้อมูลในตาราง approvals
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover" id="approvalsTable">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>ชื่อโครงสร้าง</th>
                                    <th>แผนก</th>
                                    <th>โครงสร้างการอนุมัติ</th>
                                    <th>วงเงินการอนุมัติ</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($approval_data as $approval): ?>
                                    <tr>
                                        <td>
                                            <span class="badge bg-primary"><?php echo htmlspecialchars($approval['ApprovalID']); ?></span>
                                        </td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($approval['ApprovalName']); ?></strong>
                                        </td>
                                        <td>
                                            <span class="badge-custom">
                                                <?php echo htmlspecialchars($approval['DeptName'] ?? 'ไม่ระบุ'); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="approval-structure">
                                                <?php if (!empty($approval['ApprovalLevel1'])): ?>
                                                    <div class="level-info">
                                                        <strong>ระดับ 1:</strong>
                                                        <span class="employee-info">
                                                            <?php echo htmlspecialchars($approval['level1_first'] . ' ' . $approval['level1_last']); ?>
                                                            <small class="text-muted">(<?php echo htmlspecialchars($approval['level1_position']); ?>)</small>
                                                        </span>
                                                    </div>
                                                <?php endif; ?>
                                                
                                                <?php if (!empty($approval['ApprovalLevel2'])): ?>
                                                    <div class="level-info">
                                                        <strong>ระดับ 2:</strong>
                                                        <span class="employee-info">
                                                            <?php echo htmlspecialchars($approval['level2_first'] . ' ' . $approval['level2_last']); ?>
                                                            <small class="text-muted">(<?php echo htmlspecialchars($approval['level2_position']); ?>)</small>
                                                        </span>
                                                    </div>
                                                <?php endif; ?>
                                                
                                                <?php if (!empty($approval['ApprovalLevel3'])): ?>
                                                    <div class="level-info">
                                                        <strong>ระดับ 3:</strong>
                                                        <span class="employee-info">
                                                            <?php echo htmlspecialchars($approval['level3_first'] . ' ' . $approval['level3_last']); ?>
                                                            <small class="text-muted">(<?php echo htmlspecialchars($approval['level3_position']); ?>)</small>
                                                        </span>
                                                    </div>
                                                <?php endif; ?>
                                                
                                                <?php if (empty($approval['ApprovalLevel1']) && empty($approval['ApprovalLevel2']) && empty($approval['ApprovalLevel3'])): ?>
                                                    <div class="text-muted">
                                                        <i class="bi bi-exclamation-triangle me-1"></i>
                                                        ยังไม่มีการกำหนดผู้อนุมัติ
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td>
                                            <?php if (!empty($approval['level1_amount_limit'])): ?>
                                                <div class="mb-1">
                                                    <small>ระดับ 1:</small>
                                                    <span class="amount-badge">
                                                        <?php echo number_format($approval['level1_amount_limit'], 2); ?> บาท
                                                    </span>
                                                </div>
                                            <?php endif; ?>
                                            
                                            <?php if (!empty($approval['level2_amount_limit'])): ?>
                                                <div class="mb-1">
                                                    <small>ระดับ 2:</small>
                                                    <span class="amount-badge">
                                                        <?php echo number_format($approval['level2_amount_limit'], 2); ?> บาท
                                                    </span>
                                                </div>
                                            <?php endif; ?>
                                            
                                            <?php if (!empty($approval['level3_amount_limit'])): ?>
                                                <div class="mb-1">
                                                    <small>ระดับ 3:</small>
                                                    <span class="amount-badge">
                                                        <?php echo number_format($approval['level3_amount_limit'], 2); ?> บาท
                                                    </span>
                                                </div>
                                            <?php endif; ?>
                                            
                                            <?php if (empty($approval['level1_amount_limit']) && empty($approval['level2_amount_limit']) && empty($approval['level3_amount_limit'])): ?>
                                                <span class="amount-badge no-limit">
                                                    ไม่จำกัดวงเงิน
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Raw Data JSON (for debugging) -->
        <div class="card">
            <div class="card-header">
                <h5><i class="bi bi-code-square me-2"></i>ข้อมูล JSON (สำหรับ Debug)</h5>
            </div>
            <div class="card-body">
                <pre style="background-color: #f8f9fa; padding: 1rem; border-radius: 5px; max-height: 300px; overflow-y: auto;">
<?php echo json_encode($approval_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE); ?>
                </pre>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Search functionality
        document.getElementById('searchInput').addEventListener('keyup', function() {
            const searchTerm = this.value.toLowerCase();
            const table = document.getElementById('approvalsTable');
            const rows = table.getElementsByTagName('tbody')[0].getElementsByTagName('tr');
            
            for (let i = 0; i < rows.length; i++) {
                const row = rows[i];
                const text = row.textContent.toLowerCase();
                
                if (text.includes(searchTerm)) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            }
        });

        // Export to CSV function
        function exportToCSV() {
            const table = document.getElementById('approvalsTable');
            let csv = [];
            
            // Headers
            const headers = [];
            const headerCells = table.querySelectorAll('thead th');
            headerCells.forEach(header => {
                headers.push('"' + header.textContent.trim() + '"');
            });
            csv.push(headers.join(','));
            
            // Data rows
            const rows = table.querySelectorAll('tbody tr');
            rows.forEach(row => {
                if (row.style.display !== 'none') {
                    const rowData = [];
                    const cells = row.querySelectorAll('td');
                    cells.forEach(cell => {
                        rowData.push('"' + cell.textContent.trim().replace(/"/g, '""') + '"');
                    });
                    csv.push(rowData.join(','));
                }
            });
            
            // Download
            const csvContent = csv.join('\n');
            const blob = new Blob(['\ufeff' + csvContent], { type: 'text/csv;charset=utf-8;' });
            const link = document.createElement('a');
            const url = URL.createObjectURL(blob);
            link.setAttribute('href', url);
            link.setAttribute('download', 'approvals_data_' + new Date().toISOString().split('T')[0] + '.csv');
            link.style.visibility = 'hidden';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }

        // Auto refresh every 5 minutes
        setInterval(function() {
            location.reload();
        }, 300000);
    </script>
</body>
</html>
