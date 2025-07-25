<?php
// user_management.php - หน้าจัดการผู้ใช้งาน (IT Only)

session_start();
if (!isset($_SESSION['employee_id']) || $_SESSION['division'] !== 'JD001') {
    header('Location: ../index.php');
    exit;
}

include '../includes/header.php';
include '../includes/menu.php';
include '../config.php';
$conn = dbConnect();

// Pagination
$records_per_page = 25;
$current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($current_page - 1) * $records_per_page;

// นับจำนวนพนักงานทั้งหมด
$count_sql = "SELECT COUNT(*) as total FROM employees";
$count_stmt = sqlsrv_query($conn, $count_sql);
$total_employees = 0;
if ($count_stmt && $count_row = sqlsrv_fetch_array($count_stmt, SQLSRV_FETCH_ASSOC)) {
    $total_employees = $count_row['total'];
    sqlsrv_free_stmt($count_stmt);
}

// คำนวณจำนวนหน้า
$total_pages = ceil($total_employees / $records_per_page);

// ดึงข้อมูลแผนกทั้งหมดสำหรับฟิลเตอร์
$dept_sql = "SELECT DISTINCT DeptID, DeptName FROM dept WHERE DeptName IS NOT NULL ORDER BY DeptName ASC";
$dept_stmt = sqlsrv_query($conn, $dept_sql);
$departments = array();
if ($dept_stmt) {
    while ($dept_row = sqlsrv_fetch_array($dept_stmt, SQLSRV_FETCH_ASSOC)) {
        $departments[] = $dept_row;
    }
    sqlsrv_free_stmt($dept_stmt);
}

// ดึงข้อมูลพนักงานแบบ pagination
$sql = "SELECT e.employee_id, e.first_name_th, e.last_name_th, e.position_th, e.division, ISNULL(d.DeptName, N'-') AS division_name 
        FROM employees e 
        LEFT JOIN dept d ON e.division = d.DeptID 
        ORDER BY e.employee_id ASC 
        OFFSET $offset ROWS 
        FETCH NEXT $records_per_page ROWS ONLY";
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
    <title>จัดการผู้ใช้งาน - Web Portal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Prompt', sans-serif;
            background: #ffffff;
            min-height: 100vh;
            color: #333;
        }
        
        .main-container {
            background: rgba(255, 255, 255, 1);
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.08);
            margin: 2rem auto;
            padding: 0;
            overflow: hidden;
            border: 1px solid #e9ecef;
        }
        
        .header-section {
            background: linear-gradient(135deg, #0984e3 0%, #74b9ff 100%);
            color: white;
            padding: 2rem;
            position: relative;
            overflow: hidden;
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
            font-size: 2.5rem;
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
        
        .search-section {
            margin-bottom: 2rem;
        }
        
        .search-box {
            position: relative;
        }
        
        .filter-box {
            position: relative;
        }
        
        .search-input {
            border-radius: 50px;
            border: 2px solid #e9ecef;
            padding: 0.75rem 1rem 0.75rem 3rem;
            transition: all 0.3s ease;
        }
        
        .filter-select {
            border-radius: 50px;
            border: 2px solid #e9ecef;
            padding: 0.75rem 1rem;
            transition: all 0.3s ease;
            background-color: white;
        }
        
        .search-input:focus, .filter-select:focus {
            border-color: #0984e3;
            box-shadow: 0 0 0 0.2rem rgba(9, 132, 227, 0.25);
        }
        
        .search-icon {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: #6c757d;
        }
        
        .table-container {
            background: white;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
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
        
        .employee-info {
            display: contents;
            align-items: center;
            /* gap: 0.75rem; */
            min-height: 60px;
            border: none;
            background: transparent;
        }
        
        .employee-avatar {
            width: 45px;
            height: 45px;
            background: linear-gradient(135deg, #74b9ff 0%, #0984e3 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            font-size: 1rem;
            flex-shrink: 0;
        }
        
        .employee-details {
            flex: 1;
            min-width: 0;
        }
        
        .employee-details h6 {
            margin: 0;
            font-weight: 600;
            color: #2d3436;
            font-size: 1rem;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: 200px;
        }
        
        .employee-id {
            font-size: 0.85rem;
            color: #636e72;
            margin: 0;
            font-weight: 500;
        }
        
        .position-badge {
            background: linear-gradient(135deg, #00b894 0%, #00a085 100%);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
            display: inline-block;
            max-width: 150px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        .division-info {
            background: #f1f2f6;
            border-radius: 10px;
            padding: 0.5rem 0.75rem;
            font-size: 0.85rem;
            min-height: 50px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }
        
        .division-code {
            font-weight: 600;
            color: #2d3436;
            font-size: 0.9rem;
        }
        
        .division-name {
            color: #636e72;
            margin-top: 0.25rem;
            font-size: 0.8rem;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        .action-buttons {
            display: flex;
            gap: 0.5rem;
            justify-content: center;
            flex-wrap: wrap;
        }
        
        .btn-action {
            border-radius: 20px;
            padding: 0.5rem 1rem;
            font-size: 0.8rem;
            font-weight: 500;
            transition: all 0.3s ease;
            border: none;
            min-width: 80px;
        }
        
        .btn-edit {
            background: linear-gradient(135deg, #0984e3 0%, #74b9ff 100%);
            color: white;
        }
        
        .btn-edit:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(9, 132, 227, 0.4);
            color: white;
        }
        
        .btn-disable {
            background: linear-gradient(135deg, #fd79a8 0%, #e84393 100%);
            color: white;
        }
        
        .btn-disable:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(232, 67, 147, 0.4);
            color: white;
        }
        
        .btn-add {
            background: linear-gradient(135deg, #00b894 0%, #00a085 100%);
            color: white;
        }
        
        .btn-add:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 184, 148, 0.4);
            color: white;
        }
        
        .no-data {
            text-align: center;
            padding: 3rem;
            color: #636e72;
        }
        
        .no-data i {
            font-size: 4rem;
            margin-bottom: 1rem;
            opacity: 0.5;
            color: #74b9ff;
        }
        
        .pagination-section {
            background: white;
            border-radius: 15px;
            padding: 1.5rem;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }
        
        .pagination-info {
            display: flex;
            align-items: center;
            height: 100%;
        }
        
        .pagination .page-link {
            border: none;
            color: #0984e3;
            padding: 0.5rem 0.75rem;
            margin: 0 0.25rem;
            border-radius: 8px;
            transition: all 0.3s ease;
            font-weight: 500;
        }
        
        .pagination .page-link:hover {
            background: linear-gradient(135deg, #0984e3 0%, #74b9ff 100%);
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(9, 132, 227, 0.3);
        }
        
        .pagination .page-item.active .page-link {
            background: linear-gradient(135deg, #0984e3 0%, #74b9ff 100%);
            color: white;
            border: none;
            box-shadow: 0 5px 15px rgba(9, 132, 227, 0.3);
        }
        
        .pagination .page-item.disabled .page-link {
            color: #6c757d;
            background: #f8f9fa;
            border: none;
        }
        
        @media (max-width: 768px) {
            .main-container {
                margin: 1rem;
                border-radius: 15px;
            }
            
            .header-section {
                padding: 1.5rem;
            }
            
            .content-section {
                padding: 1rem;
            }
            
            .table tbody td {
                padding: 0.75rem 0.5rem;
                font-size: 0.9rem;
            }
            
            .action-buttons {
                flex-direction: column;
                gap: 0.25rem;
            }
            
            .employee-details h6 {
                max-width: 120px;
                font-size: 0.9rem;
            }
            
            .employee-avatar {
                width: 35px;
                height: 35px;
                font-size: 0.8rem;
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
                        <h1 class="mb-2"><i class="bi bi-people-fill me-3"></i>จัดการผู้ใช้งาน</h1>
                        <p class="mb-0 opacity-75">ระบบจัดการข้อมูลพนักงานและผู้ใช้งานในองค์กร</p>
                    </div>
                    <div class="col-md-4">
                        <div class="stats-card">
                            <div class="stats-number"><?php echo number_format($total_employees); ?></div>
                            <div class="stats-label">พนักงานทั้งหมด</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Content Section -->
        <div class="content-section">
            <!-- Search Section -->
            <div class="search-section">
                <div class="row">
                    <div class="col-md-4">
                        <div class="search-box">
                            <i class="bi bi-search search-icon"></i>
                            <input type="text" class="form-control search-input" id="searchInput" placeholder="ค้นหาพนักงาน...">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="filter-box">
                            <select class="form-select filter-select" id="departmentFilter">
                                <option value="">ทุกแผนก</option>
                                <?php foreach ($departments as $dept) { ?>
                                    <option value="<?php echo htmlspecialchars($dept['DeptID']); ?>">
                                        <?php echo htmlspecialchars($dept['DeptName']); ?>
                                    </option>
                                <?php } ?>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <button class="btn btn-outline-secondary w-100" id="clearFilters">
                            <i class="bi bi-x-circle me-1"></i>ล้างฟิลเตอร์
                        </button>
                    </div>
                    <div class="col-md-3 text-end">
                        <button class="btn btn-action btn-add">
                            <i class="bi bi-plus-circle me-2"></i>เพิ่มผู้ใช้งาน
                        </button>
                    </div>
                </div>
            </div>

            <!-- Table Section -->
            <div class="table-container">
                <div class="table-responsive">
                    <table class="table align-middle" id="employeeTable">
                        <thead>
                            <tr>
                                <th style="width: 25%;">ข้อมูลพนักงาน</th>
                                <th style="width: 20%;">ตำแหน่ง</th>
                                <th style="width: 25%;">หน่วยงาน</th>
                                <th style="width: 30%;" class="text-center">การจัดการ</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($stmt && sqlsrv_has_rows($stmt)) {
                                while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) { 
                                    // สร้างตัวอักษรแรกสำหรับ avatar
                                    $initial = mb_substr($row['first_name_th'], 0, 1, 'UTF-8');
                                    ?>
                                    <tr class="employee-row" 
                                        data-search="<?php echo htmlspecialchars(strtolower($row['employee_id'] . ' ' . $row['first_name_th'] . ' ' . $row['last_name_th'] . ' ' . $row['position_th'] . ' ' . $row['division'] . ' ' . $row['division_name'])); ?>"
                                        data-department="<?php echo htmlspecialchars($row['division']); ?>">
                                        <td>
                                            <div class="employee-info">
                                               
                                                <div class="employee-details">
                                                    <h6><?php echo htmlspecialchars($row['first_name_th'] . ' ' . $row['last_name_th']); ?></h6>
                                                   
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="position-badge">
                                                <?php echo htmlspecialchars($row['position_th']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="division-info">
                                                
                                                <div class="division-name"><?php echo htmlspecialchars($row['division_name']); ?></div>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="action-buttons">
                                                <button class="btn btn-action btn-edit" title="แก้ไขข้อมูล">
                                                    <i class="bi bi-pencil-square me-1"></i>แก้ไข
                                                </button>
                                                <button class="btn btn-action btn-disable" title="ปิดใช้งาน">
                                                    <i class="bi bi-person-x me-1"></i>ปิดใช้งาน
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                            <?php }
                            } else { ?>
                                <tr>
                                    <td colspan="4">
                                        <div class="no-data">
                                            <i class="bi bi-people"></i>
                                            <h5>ไม่พบข้อมูลผู้ใช้งาน</h5>
                                            <p>ยังไม่มีข้อมูลพนักงานในระบบ</p>
                                        </div>
                                    </td>
                                </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Pagination Section -->
            <?php if ($total_pages > 1) { ?>
            <div class="pagination-section mt-4">
                <div class="row align-items-center">
                    <div class="col-md-6">
                        <div class="pagination-info">
                            <span class="text-muted">
                                แสดง <?php echo number_format(($offset + 1)); ?> - <?php echo number_format(min($offset + $records_per_page, $total_employees)); ?> 
                                จาก <?php echo number_format($total_employees); ?> รายการ
                            </span>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <nav aria-label="Page navigation">
                            <ul class="pagination justify-content-end mb-0">
                                <!-- Previous Button -->
                                <?php if ($current_page > 1) { ?>
                                <li class="page-item">
                                    <a class="page-link" href="?page=<?php echo ($current_page - 1); ?>" aria-label="Previous">
                                        <span aria-hidden="true">&laquo;</span>
                                    </a>
                                </li>
                                <?php } else { ?>
                                <li class="page-item disabled">
                                    <span class="page-link">&laquo;</span>
                                </li>
                                <?php } ?>

                                <!-- Page Numbers -->
                                <?php
                                $start_page = max(1, $current_page - 2);
                                $end_page = min($total_pages, $current_page + 2);
                                
                                if ($start_page > 1) {
                                    echo '<li class="page-item"><a class="page-link" href="?page=1">1</a></li>';
                                    if ($start_page > 2) {
                                        echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                                    }
                                }

                                for ($i = $start_page; $i <= $end_page; $i++) {
                                    if ($i == $current_page) {
                                        echo '<li class="page-item active"><span class="page-link">' . $i . '</span></li>';
                                    } else {
                                        echo '<li class="page-item"><a class="page-link" href="?page=' . $i . '">' . $i . '</a></li>';
                                    }
                                }

                                if ($end_page < $total_pages) {
                                    if ($end_page < $total_pages - 1) {
                                        echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                                    }
                                    echo '<li class="page-item"><a class="page-link" href="?page=' . $total_pages . '">' . $total_pages . '</a></li>';
                                }
                                ?>

                                <!-- Next Button -->
                                <?php if ($current_page < $total_pages) { ?>
                                <li class="page-item">
                                    <a class="page-link" href="?page=<?php echo ($current_page + 1); ?>" aria-label="Next">
                                        <span aria-hidden="true">&raquo;</span>
                                    </a>
                                </li>
                                <?php } else { ?>
                                <li class="page-item disabled">
                                    <span class="page-link">&raquo;</span>
                                </li>
                                <?php } ?>
                            </ul>
                        </nav>
                    </div>
                </div>
            </div>
            <?php } ?>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Filter and search functionality
function filterRows() {
    const searchTerm = document.getElementById('searchInput').value.toLowerCase();
    const departmentFilter = document.getElementById('departmentFilter').value;
    const rows = document.querySelectorAll('.employee-row');
    let visibleCount = 0;
    
    rows.forEach(row => {
        const searchData = row.getAttribute('data-search');
        const departmentData = row.getAttribute('data-department');
        
        const matchesSearch = searchData.includes(searchTerm);
        const matchesDepartment = departmentFilter === '' || departmentData === departmentFilter;
        
        if (matchesSearch && matchesDepartment) {
            row.style.display = '';
            visibleCount++;
        } else {
            row.style.display = 'none';
        }
    });
    
    // Hide/show pagination when filtering
    const paginationSection = document.querySelector('.pagination-section');
    if (paginationSection) {
        if (searchTerm.length > 0 || departmentFilter !== '') {
            paginationSection.style.display = 'none';
        } else {
            paginationSection.style.display = 'block';
        }
    }
    
    return visibleCount;
}

// Search functionality
document.getElementById('searchInput').addEventListener('keyup', filterRows);

// Department filter functionality
document.getElementById('departmentFilter').addEventListener('change', filterRows);

// Clear filters functionality
document.getElementById('clearFilters').addEventListener('click', function() {
    document.getElementById('searchInput').value = '';
    document.getElementById('departmentFilter').value = '';
    filterRows();
});

// Add hover effects and animations
document.addEventListener('DOMContentLoaded', function() {
    // Check if Bootstrap is loaded
    if (typeof bootstrap === 'undefined') {
        console.error('Bootstrap is not loaded');
        return;
    }
    
    // Initialize any Bootstrap components if needed
    if (bootstrap.Offcanvas) {
        // Ensure offcanvas elements are properly initialized
        const offcanvasElements = document.querySelectorAll('.offcanvas');
        offcanvasElements.forEach(element => {
            if (!bootstrap.Offcanvas.getInstance(element)) {
                new bootstrap.Offcanvas(element);
            }
        });
    }
    
    // Animate table rows on load
    const rows = document.querySelectorAll('.employee-row');
    rows.forEach((row, index) => {
        row.style.opacity = '0';
        row.style.transform = 'translateY(20px)';
        
        setTimeout(() => {
            row.style.transition = 'all 0.5s ease';
            row.style.opacity = '1';
            row.style.transform = 'translateY(0)';
        }, index * 50); // Reduced delay for better performance with pagination
    });
});
</script>
</body>
</html>

<?php
if ($stmt) sqlsrv_free_stmt($stmt);
sqlsrv_close($conn);
// ไม่ต้อง include footer.php เพื่อหลีกเลี่ยง script conflicts
?>
