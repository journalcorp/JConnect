<?php
session_start();
include_once '../includes/header.php';
include_once '../includes/menu.php';
include_once '../config.php';

// ตรวจสอบสิทธิ์การเข้าถึง
if (!isset($_SESSION['division']) || !in_array($_SESSION['division'], ['JD001', 'JD003'])) {
    echo '<div style="text-align:center;margin-top:50px;"><h3>คุณไม่มีสิทธิ์เข้าถึงหน้านี้</h3></div>';
    exit;
}

// สร้างการเชื่อมต่อฐานข้อมูล
$conn = dbConnect();

// ตัวแปรสำหรับการค้นหาและกรอง
$search = $_GET['search'] ?? '';
$status_filter = $_GET['status'] ?? '';
$page = max(1, intval($_GET['page'] ?? 1));
$limit = 10;
$offset = ($page - 1) * $limit;

// สร้าง SQL Query สำหรับดึงข้อมูล
$where_conditions = [];
$params = [];

if (!empty($search)) {
    $where_conditions[] = "(voucher_no LIKE ? OR pay_to LIKE ? OR department LIKE ?)";
    $search_param = '%' . $search . '%';
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

if (!empty($status_filter)) {
    $where_conditions[] = "overall_status = ?";
    $params[] = $status_filter;
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

try {
    $conn_type = get_class($conn);
    
    if (strpos($conn_type, 'sqlsrv') !== false || is_resource($conn)) {
        // SQLSRV Connection
        // นับจำนวนรายการทั้งหมด
        $count_sql = "SELECT COUNT(*) as total FROM ExpenseRequests $where_clause";
        $count_result = sqlsrv_query($conn, $count_sql, $params);
        $count_row = sqlsrv_fetch_array($count_result, SQLSRV_FETCH_ASSOC);
        $total_records = $count_row['total'];
        
        // ดึงข้อมูลรายการ
        $sql = "
            SELECT TOP $limit 
                er.id, er.voucher_no, er.pay_to, er.department, er.total_amount,
                er.overall_status, er.created_at, er.voucher_type, er.expense_type,
                c.company_name_th,
                e.first_name_th + ' ' + e.last_name_th as requester_name
            FROM ExpenseRequests er
            LEFT JOIN Companies c ON er.company_id = c.id
            LEFT JOIN Employees e ON er.requester_id = e.employee_id
            $where_clause
            ORDER BY er.created_at DESC
            OFFSET $offset ROWS
        ";
        
        $result = sqlsrv_query($conn, $sql, $params);
        $expense_requests = [];
        if ($result) {
            while ($row = sqlsrv_fetch_array($result, SQLSRV_FETCH_ASSOC)) {
                $expense_requests[] = $row;
            }
        }
        
    } else if (strpos($conn_type, 'PDO') !== false) {
        // PDO Connection
        // นับจำนวนรายการทั้งหมด
        $count_sql = "SELECT COUNT(*) as total FROM ExpenseRequests $where_clause";
        $count_stmt = $conn->prepare($count_sql);
        $count_stmt->execute($params);
        $count_row = $count_stmt->fetch(PDO::FETCH_ASSOC);
        $total_records = $count_row['total'];
        
        // ดึงข้อมูลรายการ
        $sql = "
            SELECT 
                er.id, er.voucher_no, er.pay_to, er.department, er.total_amount,
                er.overall_status, er.created_at, er.voucher_type, er.expense_type,
                c.company_name_th,
                CONCAT(e.first_name_th, ' ', e.last_name_th) as requester_name
            FROM ExpenseRequests er
            LEFT JOIN Companies c ON er.company_id = c.id
            LEFT JOIN Employees e ON er.requester_id = e.employee_id
            $where_clause
            ORDER BY er.created_at DESC
            LIMIT $limit OFFSET $offset
        ";
        
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        $expense_requests = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } else {
        // MySQLi Connection
        // สร้าง prepared statement สำหรับ MySQLi
        $param_types = str_repeat('s', count($params));
        
        // นับจำนวนรายการทั้งหมด
        $count_sql = "SELECT COUNT(*) as total FROM ExpenseRequests $where_clause";
        if (!empty($params)) {
            $count_stmt = $conn->prepare($count_sql);
            $count_stmt->bind_param($param_types, ...$params);
            $count_stmt->execute();
            $count_result = $count_stmt->get_result();
            $count_row = $count_result->fetch_assoc();
        } else {
            $count_result = $conn->query($count_sql);
            $count_row = $count_result->fetch_assoc();
        }
        $total_records = $count_row['total'];
        
        // ดึงข้อมูลรายการ
        $sql = "
            SELECT 
                er.id, er.voucher_no, er.pay_to, er.department, er.total_amount,
                er.overall_status, er.created_at, er.voucher_type, er.expense_type,
                c.company_name_th,
                CONCAT(e.first_name_th, ' ', e.last_name_th) as requester_name
            FROM ExpenseRequests er
            LEFT JOIN Companies c ON er.company_id = c.id
            LEFT JOIN Employees e ON er.requester_id = e.employee_id
            $where_clause
            ORDER BY er.created_at DESC
            LIMIT $limit OFFSET $offset
        ";
        
        if (!empty($params)) {
            $stmt = $conn->prepare($sql);
            $stmt->bind_param($param_types, ...$params);
            $stmt->execute();
            $result = $stmt->get_result();
            $expense_requests = $result->fetch_all(MYSQLI_ASSOC);
        } else {
            $result = $conn->query($sql);
            $expense_requests = $result->fetch_all(MYSQLI_ASSOC);
        }
    }
    
} catch (Exception $e) {
    $error_message = "เกิดข้อผิดพลาดในการดึงข้อมูล: " . $e->getMessage();
    $expense_requests = [];
    $total_records = 0;
}

$total_pages = ceil($total_records / $limit);

// ฟังก์ชันแปลงสถานะ
function getStatusBadge($status) {
    switch ($status) {
        case 'Pending':
            return '<span class="badge bg-warning">รอการอนุมัติ</span>';
        case 'Approved':
            return '<span class="badge bg-success">อนุมัติแล้ว</span>';
        case 'Rejected':
            return '<span class="badge bg-danger">ไม่อนุมัติ</span>';
        case 'Completed':
            return '<span class="badge bg-info">เสร็จสิ้น</span>';
        default:
            return '<span class="badge bg-secondary">' . $status . '</span>';
    }
}

// ฟังก์ชันแปลงประเภทใบเบิก
function getVoucherTypeText($type) {
    return $type === 'advance' ? 'เบิกเงิน' : 'โอนเงิน';
}

// ฟังก์ชันแปลงประเภทค่าใช้จ่าย
function getExpenseTypeText($type) {
    $types = [
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
    return $types[$type] ?? $type;
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>รายการคำขอเบิกจ่าย - ฝ่ายบัญชี</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Prompt', sans-serif;
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
        }
        .main-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        .card {
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            border: none;
            background: white;
        }
        .card-header {
            background: linear-gradient(135deg, #27ae60 0%, #229954 100%);
            color: white;
            border-radius: 20px 20px 0 0 !important;
            padding: 2rem;
            border: none;
        }
        .btn-primary {
            background: linear-gradient(135deg, #27ae60 0%, #229954 100%);
            border: none;
            border-radius: 10px;
            padding: 8px 20px;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(39, 174, 96, 0.4);
        }
        .table {
            border-radius: 10px;
            overflow: hidden;
        }
        .table th {
            background-color: #f8f9fa;
            border-color: #dee2e6;
            font-weight: 600;
            color: #2c3e50;
        }
        .search-box {
            background: #f8f9fa;
            border-radius: 15px;
            padding: 1.5rem;
            margin-bottom: 2rem;
        }
        .pagination .page-link {
            border-radius: 8px;
            margin: 0 2px;
            border: none;
            color: #27ae60;
        }
        .pagination .page-item.active .page-link {
            background-color: #27ae60;
            border-color: #27ae60;
        }
        .alert {
            border-radius: 10px;
            border: none;
        }
    </style>
</head>
<body>
    <div class="main-container">
        <div class="card">
            <div class="card-header">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h3 class="mb-0"><i class="bi bi-list-check me-2"></i>รายการคำขอเบิกจ่าย</h3>
                        <p class="mb-0 mt-2 opacity-75">จัดการและติดตามสถานะคำขอเบิกจ่าย</p>
                    </div>
                    <a href="accounting_form.php" class="btn btn-light">
                        <i class="bi bi-plus me-2"></i>สร้างคำขอใหม่
                    </a>
                </div>
            </div>
            <div class="card-body p-4">
                <?php if (isset($error_message)): ?>
                    <div class="alert alert-danger" role="alert">
                        <i class="bi bi-exclamation-triangle me-2"></i><?php echo $error_message; ?>
                    </div>
                <?php endif; ?>

                <!-- Search and Filter -->
                <div class="search-box">
                    <form method="GET" action="">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="search" class="form-label">ค้นหา</label>
                                <input type="text" class="form-control" id="search" name="search" 
                                       placeholder="ค้นหาเลขที่ใบเบิก, ชื่อผู้รับ, หรือแผนก" 
                                       value="<?php echo htmlspecialchars($search); ?>">
                            </div>
                            <div class="col-md-4">
                                <label for="status" class="form-label">สถานะ</label>
                                <select class="form-select" id="status" name="status">
                                    <option value="">ทุกสถานะ</option>
                                    <option value="Pending" <?php echo $status_filter === 'Pending' ? 'selected' : ''; ?>>รอการอนุมัติ</option>
                                    <option value="Approved" <?php echo $status_filter === 'Approved' ? 'selected' : ''; ?>>อนุมัติแล้ว</option>
                                    <option value="Rejected" <?php echo $status_filter === 'Rejected' ? 'selected' : ''; ?>>ไม่อนุมัติ</option>
                                    <option value="Completed" <?php echo $status_filter === 'Completed' ? 'selected' : ''; ?>>เสร็จสิ้น</option>
                                </select>
                            </div>
                            <div class="col-md-2 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="bi bi-search"></i> ค้นหา
                                </button>
                            </div>
                        </div>
                    </form>
                </div>

                <!-- Results Summary -->
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div class="text-muted">
                        แสดง <?php echo count($expense_requests); ?> รายการ จากทั้งหมด <?php echo $total_records; ?> รายการ
                    </div>
                    <a href="accounting_dashboard.php" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-left me-2"></i>กลับหน้าหลัก
                    </a>
                </div>

                <!-- Table -->
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>เลขที่ใบเบิก</th>
                                <th>ผู้ขอเบิก</th>
                                <th>ชื่อผู้รับ/แผนก</th>
                                <th>ประเภท</th>
                                <th>จำนวนเงิน</th>
                                <th>สถานะ</th>
                                <th>วันที่สร้าง</th>
                                <th>จัดการ</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($expense_requests)): ?>
                                <tr>
                                    <td colspan="8" class="text-center py-4">
                                        <i class="bi bi-inbox display-4 text-muted"></i>
                                        <p class="text-muted mt-2">ไม่พบข้อมูลคำขอเบิกจ่าย</p>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($expense_requests as $request): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars($request['voucher_no']); ?></strong>
                                            <br><small class="text-muted"><?php echo getVoucherTypeText($request['voucher_type']); ?></small>
                                        </td>
                                        <td>
                                            <?php echo htmlspecialchars($request['requester_name'] ?? 'ไม่ระบุ'); ?>
                                            <br><small class="text-muted"><?php echo htmlspecialchars($request['company_name_th'] ?? ''); ?></small>
                                        </td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($request['pay_to']); ?></strong>
                                            <?php if (!empty($request['department'])): ?>
                                                <br><small class="text-muted"><?php echo htmlspecialchars($request['department']); ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <small><?php echo getExpenseTypeText($request['expense_type']); ?></small>
                                        </td>
                                        <td>
                                            <strong class="text-primary">
                                                ฿<?php echo number_format($request['total_amount'], 2); ?>
                                            </strong>
                                        </td>
                                        <td><?php echo getStatusBadge($request['overall_status']); ?></td>
                                        <td>
                                            <?php 
                                            if ($request['created_at']) {
                                                if (is_object($request['created_at'])) {
                                                    // SQLSRV DateTime object
                                                    echo $request['created_at']->format('d/m/Y H:i');
                                                } else {
                                                    // String format
                                                    echo date('d/m/Y H:i', strtotime($request['created_at']));
                                                }
                                            }
                                            ?>
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <a href="expense_detail.php?id=<?php echo $request['id']; ?>" 
                                                   class="btn btn-outline-primary" title="ดูรายละเอียด">
                                                    <i class="bi bi-eye"></i>
                                                </a>
                                                <?php if ($request['overall_status'] === 'Pending'): ?>
                                                    <a href="accounting_form.php?edit=<?php echo $request['id']; ?>" 
                                                       class="btn btn-outline-warning" title="แก้ไข">
                                                        <i class="bi bi-pencil"></i>
                                                    </a>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                    <nav aria-label="Page navigation" class="mt-4">
                        <ul class="pagination justify-content-center">
                            <!-- Previous Page -->
                            <?php if ($page > 1): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?page=<?php echo ($page - 1); ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status_filter); ?>">
                                        <i class="bi bi-chevron-left"></i>
                                    </a>
                                </li>
                            <?php endif; ?>

                            <!-- Page Numbers -->
                            <?php
                            $start_page = max(1, $page - 2);
                            $end_page = min($total_pages, $page + 2);
                            
                            for ($i = $start_page; $i <= $end_page; $i++):
                            ?>
                                <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status_filter); ?>">
                                        <?php echo $i; ?>
                                    </a>
                                </li>
                            <?php endfor; ?>

                            <!-- Next Page -->
                            <?php if ($page < $total_pages): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?page=<?php echo ($page + 1); ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status_filter); ?>">
                                        <i class="bi bi-chevron-right"></i>
                                    </a>
                                </li>
                            <?php endif; ?>
                        </ul>
                    </nav>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

<?php
// ปิดการเชื่อมต่อฐานข้อมูล
if ($conn) {
    $conn_type = get_class($conn);
    if (strpos($conn_type, 'sqlsrv') !== false || is_resource($conn)) {
        sqlsrv_close($conn);
    } else if (strpos($conn_type, 'mysqli') !== false) {
        $conn->close();
    }
}
?>
