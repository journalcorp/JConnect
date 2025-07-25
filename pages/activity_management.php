<?php
session_start();
include_once '../includes/header.php';
include_once '../includes/menu.php';
include_once '../config.php'; // ไฟล์สำหรับการเชื่อมต่อฐานข้อมูล

// สร้างการเชื่อมต่อฐานข้อมูล
$conn = dbConnect();

// ตรวจสอบว่า employee_id มีอยู่ใน Session หรือไม่
if (!isset($_SESSION['employee_id'])) {
    die("ไม่พบ employee_id ใน Session");
}

$employeeID = $_SESSION['employee_id'];

// ฟังก์ชันลบกิจกรรม
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_activity'])) {
    $activity_id = $_POST['activity_id'];
    
    $sql = "DELETE FROM activities WHERE id = ?";
    $params = array($activity_id);
    $stmt = sqlsrv_query($conn, $sql, $params);
    
    if ($stmt === false) {
        die(print_r(sqlsrv_errors(), true));
    }
    sqlsrv_free_stmt($stmt);
    
    // Redirect เพื่อป้องกัน resubmit
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

// ดึงข้อมูลกิจกรรมทั้งหมด พร้อม pagination
$records_per_page = 10;
$current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($current_page - 1) * $records_per_page;

// นับจำนวนกิจกรรมทั้งหมด
$count_sql = "SELECT COUNT(*) as total FROM activities";
$count_stmt = sqlsrv_query($conn, $count_sql);
$total_activities = 0;
if ($count_stmt && $count_row = sqlsrv_fetch_array($count_stmt, SQLSRV_FETCH_ASSOC)) {
    $total_activities = $count_row['total'];
    sqlsrv_free_stmt($count_stmt);
}

$total_pages = ceil($total_activities / $records_per_page);

$sql = "SELECT * FROM activities ORDER BY start_date DESC OFFSET $offset ROWS FETCH NEXT $records_per_page ROWS ONLY";
$stmt = sqlsrv_query($conn, $sql);

if ($stmt === false) {
    die(print_r(sqlsrv_errors(), true));
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>การจัดการกิจกรรม - Web Portal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Prompt', sans-serif;
            background: #f8f9fa;
            color: #333;
        }
        
        .main-container {
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            margin: 2rem auto;
            padding: 0;
            overflow: hidden;
            max-width: 1200px;
        }
        
        .header-section {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
            padding: 2rem;
            position: relative;
        }
        
        .header-section::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -20%;
            width: 300px;
            height: 300px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
            z-index: 1;
        }
        
        .header-content {
            position: relative;
            z-index: 2;
        }
        
        .stats-card {
            background: rgba(255, 255, 255, 0.2);
            backdrop-filter: blur(10px);
            border-radius: 15px;
            padding: 1.5rem;
            text-align: center;
            border: 1px solid rgba(255, 255, 255, 0.3);
            transition: transform 0.3s ease;
        }
        
        .stats-card:hover {
            transform: translateY(-5px);
        }
        
        .stats-number {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }
        
        .stats-label {
            font-size: 0.9rem;
            opacity: 0.9;
            font-weight: 500;
        }
        
        .content-section {
            padding: 2rem;
        }
        
        .form-container {
            background: #f8f9fa;
            border-radius: 15px;
            padding: 2rem;
            margin-bottom: 2rem;
            border: 1px solid #e9ecef;
        }
        
        .form-control, .form-select {
            border-radius: 10px;
            border: 2px solid #e9ecef;
            transition: all 0.3s ease;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: #28a745;
            box-shadow: 0 0 0 0.2rem rgba(40, 167, 69, 0.25);
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            border: none;
            border-radius: 10px;
            padding: 0.75rem 2rem;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(40, 167, 69, 0.4);
        }
        
        .btn-lg {
            padding: 1rem 2.5rem;
            font-size: 1.1rem;
            border-radius: 12px;
        }
        
        .table-container {
            background: white;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }
        
        .table {
            margin-bottom: 0;
        }
        
        .table thead th {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border: none;
            padding: 1rem;
            font-weight: 600;
            color: #495057;
            text-transform: uppercase;
            font-size: 0.8rem;
            letter-spacing: 0.5px;
        }
        
        .table tbody td {
            padding: 1rem;
            vertical-align: middle;
            border-color: #f1f3f4;
        }
        
        .table tbody tr {
            transition: all 0.3s ease;
        }
        
        .table tbody tr:hover {
            background-color: #f0f8ff;
            transform: scale(1.01);
        }
        
        .activity-badge {
            background: linear-gradient(135deg, #007bff 0%, #6610f2 100%);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
            display: inline-block;
        }
        
        .date-badge {
            background: linear-gradient(135deg, #fd7e14 0%, #e83e8c 100%);
            color: white;
            padding: 0.25rem 0.75rem;
            border-radius: 15px;
            font-size: 0.8rem;
            font-weight: 500;
        }
        
        .action-buttons {
            display: flex;
            gap: 0.5rem;
            justify-content: center;
        }
        
        .btn-view {
            background: linear-gradient(135deg, #17a2b8 0%, #138496 100%);
            color: white;
            border: none;
            border-radius: 8px;
            padding: 0.4rem 0.8rem;
            font-size: 0.8rem;
            transition: all 0.3s ease;
        }
        
        .btn-view:hover {
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 3px 10px rgba(23, 162, 184, 0.4);
        }
        
        .btn-edit {
            background: linear-gradient(135deg, #ffc107 0%, #fd7e14 100%);
            color: white;
            border: none;
            border-radius: 8px;
            padding: 0.4rem 0.8rem;
            font-size: 0.8rem;
            transition: all 0.3s ease;
        }
        
        .btn-edit:hover {
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 3px 10px rgba(255, 193, 7, 0.4);
        }
        
        .btn-delete {
            background: linear-gradient(135deg, #dc3545 0%, #e83e8c 100%);
            color: white;
            border: none;
            border-radius: 8px;
            padding: 0.4rem 0.8rem;
            font-size: 0.8rem;
            transition: all 0.3s ease;
        }
        
        .btn-delete:hover {
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 3px 10px rgba(220, 53, 69, 0.4);
        }
        
        .search-box {
            position: relative;
            margin-bottom: 2rem;
        }
        
        .search-input {
            border-radius: 50px;
            border: 2px solid #e9ecef;
            padding: 0.75rem 1rem 0.75rem 3rem;
            transition: all 0.3s ease;
        }
        
        .search-input:focus {
            border-color: #28a745;
            box-shadow: 0 0 0 0.2rem rgba(40, 167, 69, 0.25);
        }
        
        .search-icon {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: #6c757d;
        }
        
        .pagination {
            justify-content: center;
            margin-top: 2rem;
        }
        
        .pagination .page-link {
            border: none;
            color: #28a745;
            padding: 0.5rem 0.75rem;
            margin: 0 0.25rem;
            border-radius: 8px;
            transition: all 0.3s ease;
        }
        
        .pagination .page-link:hover {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
            transform: translateY(-2px);
        }
        
        .pagination .page-item.active .page-link {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
        }
        
        .modal-content {
            border-radius: 15px;
            border: none;
        }
        
        .modal-header {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
            border-radius: 15px 15px 0 0;
        }
        
        .alert {
            border-radius: 10px;
            border: none;
        }
        
        @media (max-width: 768px) {
            .main-container {
                margin: 1rem;
                border-radius: 10px;
            }
            
            .header-section {
                padding: 1.5rem;
            }
            
            .content-section {
                padding: 1rem;
            }
            
            .action-buttons {
                flex-direction: column;
                gap: 0.25rem;
            }
        }
    </style>
</head>
<body>
<div class="container-fluid py-4">
    <div class="main-container">
        <!-- Header Section -->
        <div class="header-section">
            <div class="header-content">
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <h1 class="mb-2"><i class="bi bi-calendar-event me-3"></i>การจัดการกิจกรรม</h1>
                        <p class="mb-0 opacity-75">ระบบจัดการกิจกรรมและงานภายในองค์กร</p>
                    </div>
                    <div class="col-md-4">
                        <div class="stats-card">
                            <div class="stats-number"><?php echo number_format($total_activities); ?></div>
                            <div class="stats-label">กิจกรรมทั้งหมด</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Content Section -->
        <div class="content-section">
            <!-- Alert Messages -->
            <?php if (isset($_GET['added']) || isset($_POST['delete_activity'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="bi bi-check-circle me-2"></i>
                <?php 
                if (isset($_GET['added'])) echo "เพิ่มกิจกรรมใหม่เรียบร้อยแล้ว";
                elseif (isset($_POST['delete_activity'])) echo "ลบกิจกรรมเรียบร้อยแล้ว";
                ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>

            <!-- Add Activity Button -->
            <div class="form-container text-center">
                <h3 class="mb-4"><i class="bi bi-plus-circle me-2"></i>เพิ่มกิจกรรมใหม่</h3>
                <p class="text-muted mb-4">คลิกปุ่มด้านล่างเพื่อไปยังหน้าสร้างกิจกรรมใหม่</p>
                <a href="add_activity.php" class="btn btn-primary btn-lg">
                    <i class="bi bi-plus-lg me-2"></i>สร้างกิจกรรมใหม่
                </a>
            </div>

            <!-- Search Box -->
            <div class="search-box">
                <i class="bi bi-search search-icon"></i>
                <input type="text" class="form-control search-input" id="searchInput" placeholder="ค้นหากิจกรรม...">
            </div>

            <!-- Activities Table -->
            <div class="table-container">
                <div class="table-responsive">
                    <table class="table align-middle" id="activitiesTable">
                        <thead>
                            <tr>
                                <th style="width: 8%;">ID</th>
                                <th style="width: 25%;">ชื่อกิจกรรม</th>
                                <th style="width: 15%;">วันที่</th>
                                <th style="width: 32%;">รายละเอียด</th>
                                <th style="width: 20%;" class="text-center">การจัดการ</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) { 
                            // Format date
                            $date = $row['start_date'];
                            if ($date instanceof DateTime) {
                                $formatted_date = $date->format('d/m/Y');
                            } else {
                                $formatted_date = date('d/m/Y', strtotime($date));
                            }
                        ?>
                            <tr class="activity-row" data-search="<?php echo htmlspecialchars(strtolower($row['name'] . ' ' . $row['description'])); ?>">
                                <td><span class="badge bg-secondary"><?php echo $row['id']; ?></span></td>
                                <td>
                                    <div class="activity-badge">
                                        <i class="bi bi-calendar-event me-1"></i>
                                        <?php echo htmlspecialchars($row['name']); ?>
                                    </div>
                                    <?php 
                                    // Display attachment settings if available
                                    if (isset($row['attachment_settings']) && !empty($row['attachment_settings'])) {
                                        $settings = json_decode($row['attachment_settings'], true);
                                        if ($settings && $settings['require_attachment']) {
                                            echo '<div class="mt-1">';
                                            echo '<span class="badge bg-info text-dark" title="มีการตั้งค่าไฟล์แนบ">';
                                            echo '<i class="bi bi-paperclip me-1"></i>แนบไฟล์';
                                            if ($settings['is_file_required']) {
                                                echo ' (จำเป็น)';
                                            }
                                            echo '</span>';
                                            echo '</div>';
                                        }
                                    }
                                    ?>
                                </td>
                                <td>
                                    <span class="date-badge">
                                        <i class="bi bi-calendar3 me-1"></i>
                                        <?php echo $formatted_date; ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="text-truncate" style="max-width: 300px;" title="<?php echo htmlspecialchars($row['description']); ?>">
                                        <?php echo htmlspecialchars($row['description']); ?>
                                    </div>
                                    <?php 
                                    // Display attachment description if available
                                    if (isset($row['attachment_settings']) && !empty($row['attachment_settings'])) {
                                        $settings = json_decode($row['attachment_settings'], true);
                                        if ($settings && $settings['require_attachment'] && !empty($settings['attachment_description'])) {
                                            echo '<div class="mt-1 text-muted small">';
                                            echo '<i class="bi bi-info-circle me-1"></i>';
                                            echo htmlspecialchars($settings['attachment_description']);
                                            echo '</div>';
                                        }
                                    }
                                    ?>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <a href="activity_detail.php?id=<?php echo $row['id']; ?>" class="btn btn-view" title="ดูรายละเอียด">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                        <button type="button" class="btn btn-delete" data-bs-toggle="modal" data-bs-target="#deleteModal<?php echo $row['id']; ?>" title="ลบ">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                        <?php 
                                        // Show attachment settings details button if available
                                        if (isset($row['attachment_settings']) && !empty($row['attachment_settings'])) {
                                            echo '<button type="button" class="btn btn-info btn-sm" data-bs-toggle="modal" data-bs-target="#attachmentModal' . $row['id'] . '" title="ดูการตั้งค่าไฟล์แนบ">';
                                            echo '<i class="bi bi-info-circle"></i>';
                                            echo '</button>';
                                        }
                                        ?>
                                    </div>
                                </td>
                            </tr>

                            <!-- Delete Modal -->
                            <div class="modal fade" id="deleteModal<?php echo $row['id']; ?>" tabindex="-1">
                                <div class="modal-dialog">
                                    <div class="modal-content">
                                        <div class="modal-header bg-danger text-white">
                                            <h5 class="modal-title"><i class="bi bi-exclamation-triangle me-2"></i>ยืนยันการลบ</h5>
                                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                        </div>
                                        <div class="modal-body">
                                            <p>คุณแน่ใจหรือไม่ว่าต้องการลบกิจกรรม "<strong><?php echo htmlspecialchars($row['name']); ?></strong>"?</p>
                                            <p class="text-muted mb-0">การดำเนินการนี้ไม่สามารถย้อนกลับได้</p>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                                            <form method="POST" class="d-inline">
                                                <input type="hidden" name="activity_id" value="<?php echo $row['id']; ?>">
                                                <button type="submit" name="delete_activity" class="btn btn-danger">ลบกิจกรรม</button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Attachment Settings Modal -->
                            <?php if (isset($row['attachment_settings']) && !empty($row['attachment_settings'])): ?>
                            <div class="modal fade" id="attachmentModal<?php echo $row['id']; ?>" tabindex="-1">
                                <div class="modal-dialog">
                                    <div class="modal-content">
                                        <div class="modal-header bg-info text-dark">
                                            <h5 class="modal-title"><i class="bi bi-paperclip me-2"></i>การตั้งค่าไฟล์แนับ</h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                        </div>
                                        <div class="modal-body">
                                            <?php 
                                            $settings = json_decode($row['attachment_settings'], true);
                                            if ($settings):
                                            ?>
                                            <div class="row">
                                                <div class="col-12">
                                                    <h6 class="mb-3">รายละเอียดการตั้งค่า</h6>
                                                    
                                                    <div class="mb-3 p-3 bg-light rounded">
                                                        <div class="row">
                                                            <div class="col-6">
                                                                <label class="form-label fw-bold">อนุญาตแนบไฟล์:</label>
                                                                <span class="badge <?php echo $settings['require_attachment'] ? 'bg-success' : 'bg-secondary'; ?>">
                                                                    <i class="bi bi-<?php echo $settings['require_attachment'] ? 'check' : 'x'; ?>-circle me-1"></i>
                                                                    <?php echo $settings['require_attachment'] ? 'เปิดใช้งาน' : 'ปิดใช้งาน'; ?>
                                                                </span>
                                                            </div>
                                                            <div class="col-6">
                                                                <label class="form-label fw-bold">ไฟล์จำเป็น:</label>
                                                                <span class="badge <?php echo $settings['is_file_required'] ? 'bg-warning text-dark' : 'bg-secondary'; ?>">
                                                                    <i class="bi bi-<?php echo $settings['is_file_required'] ? 'exclamation' : 'dash'; ?>-circle me-1"></i>
                                                                    <?php echo $settings['is_file_required'] ? 'จำเป็น' : 'ไม่จำเป็น'; ?>
                                                                </span>
                                                            </div>
                                                        </div>
                                                        
                                                        <div class="row mt-2">
                                                            <div class="col-12">
                                                                <label class="form-label fw-bold">อนุญาตไฟล์หลายไฟล์:</label>
                                                                <span class="badge <?php echo $settings['allow_multiple_files'] ? 'bg-primary' : 'bg-secondary'; ?>">
                                                                    <i class="bi bi-<?php echo $settings['allow_multiple_files'] ? 'files' : 'file-earmark'; ?> me-1"></i>
                                                                    <?php echo $settings['allow_multiple_files'] ? 'อนุญาต' : 'ไฟล์เดียว'; ?>
                                                                </span>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    
                                                    <?php if (!empty($settings['attachment_description'])): ?>
                                                    <div class="mb-3">
                                                        <label class="form-label fw-bold">คำอธิบายการแนบไฟล์:</label>
                                                        <div class="p-3 bg-info bg-opacity-10 rounded border border-info border-opacity-25">
                                                            <i class="bi bi-info-circle text-info me-2"></i>
                                                            <?php echo htmlspecialchars($settings['attachment_description']); ?>
                                                        </div>
                                                    </div>
                                                    <?php endif; ?>
                                                    
                                                    <div class="mb-0">
                                                        <label class="form-label fw-bold">ข้อมูล JSON:</label>
                                                        <pre class="bg-dark text-light p-3 rounded small"><?php echo json_encode($settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE); ?></pre>
                                                    </div>
                                                </div>
                                            </div>
                                            <?php else: ?>
                                            <div class="alert alert-warning">
                                                <i class="bi bi-exclamation-triangle me-2"></i>
                                                ไม่สามารถแปลงข้อมูลการตั้งค่าได้
                                            </div>
                                            <?php endif; ?>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ปิด</button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endif; ?>
                        <?php } ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Pagination -->
            <?php if ($total_pages > 1) { ?>
            <nav aria-label="Page navigation">
                <ul class="pagination">
                    <?php if ($current_page > 1) { ?>
                    <li class="page-item">
                        <a class="page-link" href="?page=<?php echo ($current_page - 1); ?>">
                            <i class="bi bi-chevron-left"></i>
                        </a>
                    </li>
                    <?php } ?>

                    <?php for ($i = 1; $i <= $total_pages; $i++) { ?>
                    <li class="page-item <?php echo ($i == $current_page) ? 'active' : ''; ?>">
                        <a class="page-link" href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                    </li>
                    <?php } ?>

                    <?php if ($current_page < $total_pages) { ?>
                    <li class="page-item">
                        <a class="page-link" href="?page=<?php echo ($current_page + 1); ?>">
                            <i class="bi bi-chevron-right"></i>
                        </a>
                    </li>
                    <?php } ?>
                </ul>
            </nav>
            <?php } ?>
        </div>
    </div>
</div>

<?php
// ปิด statement
if ($stmt) {
    sqlsrv_free_stmt($stmt);
}
// ปิดการเชื่อมต่อ
sqlsrv_close($conn);
?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Search functionality
document.getElementById('searchInput').addEventListener('keyup', function() {
    const searchTerm = this.value.toLowerCase();
    const rows = document.querySelectorAll('.activity-row');
    
    rows.forEach(row => {
        const searchData = row.getAttribute('data-search');
        if (searchData.includes(searchTerm)) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    });
});

// Auto-dismiss alerts
setTimeout(function() {
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        const bsAlert = new bootstrap.Alert(alert);
        bsAlert.close();
    });
}, 5000);
</script>
</body>
</html>