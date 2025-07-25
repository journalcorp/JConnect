<?php
// ตรวจสอบสถานะเซสชันก่อนเริ่มต้น
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ตรวจสอบว่าผู้ใช้ล็อกอินแล้วหรือยัง
if (!isset($_SESSION['employee_id'])) {
    header("Location: ../index.php");
    exit;
}

// เชื่อมต่อฐานข้อมูล
include '../config.php';

// จัดการการเชื่อมต่อฐานข้อมูลที่อาจล้มเหลว
try {
    $conn = dbConnect();
    $database_available = ($conn !== null);
} catch (Exception $e) {
    error_log("Menu.php - Database connection failed: " . $e->getMessage());
    $conn = null;
    $database_available = false;
}

// ดึงข้อมูล DeptName จากตาราง Dept โดยเทียบกับ division ของ employee (ถ้าฐานข้อมูลพร้อม)
$deptName = 'แผนกทั่วไป'; // ค่าเริ่มต้น

if ($database_available && $conn) {
    try {
        $sqlDept = "SELECT d.DeptName 
                    FROM employees e
                    LEFT JOIN dept d ON e.division = d.DeptID
                    WHERE e.employee_id = ?";

        // ใช้ SQLSRV syntax
        $params = array($_SESSION['employee_id']);
        $stmtDept = sqlsrv_query($conn, $sqlDept, $params);

        if ($stmtDept && sqlsrv_has_rows($stmtDept)) {
            $rowDept = sqlsrv_fetch_array($stmtDept, SQLSRV_FETCH_ASSOC);
            $_SESSION['department_name'] = $rowDept['DeptName']; // เก็บ DeptName ในเซสชัน
            $deptName = $rowDept['DeptName'];
        } else {
            $_SESSION['department_name'] = 'แผนกทั่วไป'; // กรณีไม่มีข้อมูล
        }
    } catch (Exception $e) {
        error_log("Menu.php - Error fetching department: " . $e->getMessage());
        $_SESSION['department_name'] = 'แผนกทั่วไป';
    }
} else {
    // ฐานข้อมูลไม่พร้อม ใช้ค่าเริ่มต้น
    $_SESSION['department_name'] = 'แผนกทั่วไป';
}

// ปิด statement
if ($stmtDept) {
    sqlsrv_free_stmt($stmtDept);
}

// ดึงคะแนนสะสมจากตาราง employees
$employeeID = $_SESSION['employee_id']; // ใช้ employee_id จาก Session
$sql = "SELECT point FROM employees WHERE employee_id = ?";

// ใช้ SQLSRV syntax
$params = array($employeeID);
$stmt = sqlsrv_query($conn, $sql, $params);

if ($stmt) {
    $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
    $availablePoints = $row ? $row['point'] : 0; // คะแนนสะสม
    sqlsrv_free_stmt($stmt);
} else {
    $availablePoints = 0; // กรณีไม่สามารถดึงข้อมูลได้
}
?>
<style>
/* Modern Menu Styling */
.menu-sidebar {
    background: linear-gradient(135deg, #2c3e50 0%, #34495e 100%);
    color: white;
    box-shadow: 0 0 30px rgba(0, 0, 0, 0.3);
    border: none;
}

.menu-header {
    background: linear-gradient(135deg, #1a252f 0%, #2c3e50 100%);
    color: white;
    border-bottom: 2px solid rgba(255, 255, 255, 0.1);
    padding: 1.5rem 1.25rem;
    position: relative;
    overflow: hidden;
}

.menu-header::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: linear-gradient(45deg, transparent 48%, rgba(255,255,255,0.05) 50%, transparent 52%);
    pointer-events: none;
    animation: shimmer 3s infinite;
}

@keyframes shimmer {
    0% { transform: translateX(-100%); }
    100% { transform: translateX(100%); }
}

.menu-title {
    font-size: 1.3rem;
    font-weight: 600;
    margin: 0;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    position: relative;
    z-index: 2;
}

.menu-title::before {
    content: "🏢";
    font-size: 1.5rem;
}

.menu-close {
    background: rgba(255, 255, 255, 0.1);
    border: 1px solid rgba(255, 255, 255, 0.2);
    color: white;
    border-radius: 50%;
    width: 32px;
    height: 32px;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.3s ease;
    position: relative;
    z-index: 2;
}

.menu-close:hover {
    background: rgba(255, 255, 255, 0.2);
    transform: rotate(90deg);
}

.menu-body {
    padding: 0;
    background: linear-gradient(135deg, #2c3e50 0%, #34495e 100%);
}

.user-profile {
    padding: 1.5rem;
    background: linear-gradient(135deg, rgba(255, 255, 255, 0.1) 0%, rgba(255, 255, 255, 0.05) 100%);
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
    margin-bottom: 1rem;
}

.user-avatar {
    width: 60px;
    height: 60px;
    background: linear-gradient(135deg, #3498db 0%, #2980b9 100%);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 1rem;
    font-size: 1.5rem;
    color: white;
    font-weight: bold;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
}

.user-info {
    text-align: center;
}

.user-info p {
    margin: 0.5rem 0;
    font-size: 0.9rem;
    color: rgba(255, 255, 255, 0.9);
}

.user-info .user-name {
    font-size: 1.1rem;
    font-weight: 600;
    color: white;
    margin-bottom: 0.75rem;
}

.user-info .user-position {
    color: #3498db;
    font-weight: 500;
}

.user-info .user-points {
    background: linear-gradient(135deg, #f39c12 0%, #e67e22 100%);
    color: white;
    padding: 0.25rem 0.75rem;
    border-radius: 20px;
    font-size: 0.85rem;
    font-weight: 600;
    display: inline-block;
    margin-top: 0.5rem;
    box-shadow: 0 2px 8px rgba(243, 156, 18, 0.3);
}

.menu-list {
    list-style: none;
    padding: 0;
    margin: 0;
}

.menu-item {
    display: block;
    color: rgba(255, 255, 255, 0.9);
    text-decoration: none;
    padding: 1rem 1.5rem;
    transition: all 0.3s ease;
    border-bottom: 1px solid rgba(255, 255, 255, 0.05);
    position: relative;
    overflow: hidden;
}

.menu-item::before {
    content: '';
    position: absolute;
    left: 0;
    top: 0;
    bottom: 0;
    width: 4px;
    background: linear-gradient(135deg, #3498db 0%, #2980b9 100%);
    transform: scaleY(0);
    transition: transform 0.3s ease;
}

.menu-item:hover::before {
    transform: scaleY(1);
}

.menu-item:hover {
    background: rgba(255, 255, 255, 0.1);
    color: white;
    transform: translateX(8px);
    padding-left: 2rem;
}

.menu-item.active {
    background: rgba(52, 152, 219, 0.2);
    color: white;
    border-left: 4px solid #3498db;
}

.menu-item.active::before {
    transform: scaleY(1);
}

.menu-item i {
    margin-right: 0.75rem;
    font-size: 1.1rem;
    width: 20px;
    text-align: center;
}

.menu-item.logout {
    margin-top: 1rem;
    border-top: 1px solid rgba(255, 255, 255, 0.1);
    border-bottom: none;
    color: #e74c3c;
}

.menu-item.logout:hover {
    background: rgba(231, 76, 60, 0.1);
    color: #e74c3c;
}

.menu-item.logout::before {
    background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);
}

.menu-section {
    margin: 1rem 0;
}

.menu-section-title {
    padding: 0.5rem 1.5rem;
    font-size: 0.75rem;
    font-weight: 600;
    color: rgba(255, 255, 255, 0.6);
    text-transform: uppercase;
    letter-spacing: 0.5px;
    border-bottom: 1px solid rgba(255, 255, 255, 0.05);
    margin-bottom: 0.5rem;
}

.admin-section {
    background: rgba(155, 89, 182, 0.1);
    margin: 1rem 0;
    border-radius: 8px;
    overflow: hidden;
}

.admin-section .menu-item {
    border-bottom: 1px solid rgba(155, 89, 182, 0.1);
}

.admin-section .menu-item::before {
    background: linear-gradient(135deg, #9b59b6 0%, #8e44ad 100%);
}

.admin-section .menu-item:hover {
    background: rgba(155, 89, 182, 0.2);
}

/* Styling for Accounting Menu */
.admin-section[style*="39,174,96"] .menu-item::before {
    background: linear-gradient(135deg, #27ae60 0%, #229954 100%);
}

.admin-section[style*="39,174,96"] .menu-item:hover {
    background: rgba(39, 174, 96, 0.2);
}

.admin-section[style*="39,174,96"] .menu-item.active {
    background: rgba(39, 174, 96, 0.2);
    border-left: 4px solid #27ae60;
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .menu-sidebar {
        width: 85% !important;
    }
    
    .user-profile {
        padding: 1rem;
    }
    
    .menu-item {
        padding: 0.75rem 1.25rem;
    }
}
</style>

<div class="offcanvas offcanvas-start menu-sidebar" tabindex="-1" id="sidebar" aria-labelledby="sidebarLabel">
    <div class="offcanvas-header menu-header">
        <h5 class="offcanvas-title menu-title" id="sidebarLabel">Journal Portal</h5>
        <button type="button" class="btn-close menu-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
    </div>
    
    <div class="offcanvas-body menu-body">
        <div class="user-profile">
            <div class="user-avatar">
                <?php 
                $initials = '';
                if (isset($_SESSION['first_name_th'])) {
                    $initials = mb_substr($_SESSION['first_name_th'], 0, 1, 'UTF-8');
                }
                if (isset($_SESSION['last_name_th'])) {
                    $initials .= mb_substr($_SESSION['last_name_th'], 0, 1, 'UTF-8');
                }
                echo $initials;
                ?>
            </div>
            <div class="user-info">
                <div class="user-name"><?php echo htmlspecialchars($_SESSION['first_name_th'] . ' ' . $_SESSION['last_name_th']); ?></div>
                <div class="user-position"><?php echo htmlspecialchars($_SESSION['position_th']); ?></div>
                <p><strong>แผนก:</strong> <?php echo htmlspecialchars($_SESSION['department_name']); ?></p>
                <div class="user-points">
                    <i class="bi bi-star-fill me-1"></i><?php echo htmlspecialchars($availablePoints); ?> คะแนน
                </div>
            </div>
        </div>
        
        <nav class="menu-navigation">
            <div class="menu-section">
                <div class="menu-section-title">เมนูหลัก</div>
                <a href="home.php" class="menu-item <?php echo (basename($_SERVER['PHP_SELF']) == 'home.php') ? 'active' : ''; ?>">
                    <i class="bi bi-house-fill"></i> หน้าแรก
                </a>
                <a href="employee_profile.php" class="menu-item <?php echo (basename($_SERVER['PHP_SELF']) == 'employee_profile.php') ? 'active' : ''; ?>">
                    <i class="bi bi-person-fill"></i> ข้อมูลพนักงาน
                </a>
                <a href="all_report_emp.php" class="menu-item <?php echo (basename($_SERVER['PHP_SELF']) == 'all_report_emp.php') ? 'active' : ''; ?>">
                    <i class="bi bi-clipboard-check-fill"></i> แบบฟอร์มประเมินผล
                </a>
            </div>
            
            <?php
            // ตรวจสอบว่า session มี division หรือไม่ และแสดงเมนูเฉพาะ division ที่เป็น JD011 หรือ JD001 (HR)
            if (isset($_SESSION['division']) && ($_SESSION['division'] === 'JD011' || $_SESSION['division'] === 'JD001')) {
            ?>
                <div class="menu-section admin-section">
                    <div class="menu-section-title">เมนูสำหรับ HR</div>
                    <a href="performance_review.php" class="menu-item <?php echo (basename($_SERVER['PHP_SELF']) == 'performance_review.php') ? 'active' : ''; ?>">
                        <i class="bi bi-gear-fill"></i> จัดการแบบฟอร์มสำหรับ HR
                    </a>
                    <a href="activity_management.php" class="menu-item <?php echo (basename($_SERVER['PHP_SELF']) == 'activity_management.php') ? 'active' : ''; ?>">
                        <i class="bi bi-calendar-event"></i> การจัดการกิจกรรม
                    </a>
                    <a href="color_personality_report.php" class="menu-item <?php echo (basename($_SERVER['PHP_SELF']) == 'color_personality_report.php') ? 'active' : ''; ?>">
                        <i class="bi bi-palette"></i> รายงานบุคลิกภาพสี (HR)
                    </a>
                    <!-- <a href="admin_manage_coupons.php" class="menu-item <?php echo (basename($_SERVER['PHP_SELF']) == 'admin_manage_coupons.php') ? 'active' : ''; ?>">
                        <i class="bi bi-ticket-perforated-fill"></i> การจัดการคูปอง
                    </a>
                </div> -->
                </div>
            <?php
            }
            // เมนูสำหรับ IT (ตัวอย่างใช้ division JD999)
            if (isset($_SESSION['division']) && $_SESSION['division'] === 'JD001') {
            ?>
                <div class="menu-section admin-section" style="background:rgba(52,152,219,0.08);">
                    <div class="menu-section-title"><i class="bi bi-laptop"></i> เมนูสำหรับ IT</div>
                    <a href="approval_management.php" class="menu-item <?php echo (basename($_SERVER['PHP_SELF']) == 'approval_management.php') ? 'active' : ''; ?>">
                        <i class="bi bi-shield-lock"></i> จัดการสิทธิการอนุมัติ
                    </a>
                    <a href="view_approvals_data.php" class="menu-item <?php echo (basename($_SERVER['PHP_SELF']) == 'view_approvals_data.php') ? 'active' : ''; ?>">
                        <i class="bi bi-table"></i> ดูข้อมูลตาราง Approvals
                    </a>
                    <a href="it_dashboard.php" class="menu-item <?php echo (basename($_SERVER['PHP_SELF']) == 'it_dashboard.php') ? 'active' : ''; ?>">
                        <i class="bi bi-speedometer2"></i> IT Dashboard
                    </a>
                    <a href="user_management.php" class="menu-item <?php echo (basename($_SERVER['PHP_SELF']) == 'user_management.php') ? 'active' : ''; ?>">
                        <i class="bi bi-people-fill"></i> จัดการผู้ใช้ระบบ
                    </a>
                    <a href="system_logs.php" class="menu-item <?php echo (basename($_SERVER['PHP_SELF']) == 'system_logs.php') ? 'active' : ''; ?>">
                        <i class="bi bi-journal-text"></i> System Logs
                    </a>
                </div>
            <?php
            }
            
            // เมนูสำหรับฝ่ายบัญชี (ตัวอย่างใช้ division JD003)
            // if (isset($_SESSION['division']) && $_SESSION['division'] === 'JD003') {
                if (isset($_SESSION['division']) && ($_SESSION['division'] === 'JD003' || $_SESSION['division'] === 'JD001')) {
            ?>
                <div class="menu-section admin-section" style="background:rgba(39,174,96,0.08);">
                    <div class="menu-section-title"><i class="bi bi-calculator"></i> เมนูสำหรับฝ่ายบัญชี</div>
                    <a href="accounting_form.php" class="menu-item <?php echo (basename($_SERVER['PHP_SELF']) == 'accounting_form.php') ? 'active' : ''; ?>">
                        <i class="bi bi-receipt"></i> ฟอร์มคำขอเบิกจ่าย
                    </a>
                    <a href="approval_dashboard.php" class="menu-item <?php echo (basename($_SERVER['PHP_SELF']) == 'approval_dashboard.php') ? 'active' : ''; ?>">
                        <i class="bi bi-clipboard-check"></i> ระบบอนุมัติ
                    </a>
                    <a href="it_dashboard.php" class="menu-item <?php echo (basename($_SERVER['PHP_SELF']) == 'it_dashboard.php') ? 'active' : ''; ?>">
                        <i class="bi bi-graph-up-arrow"></i> แดชบอร์ดรายงาน
                    </a>
                    <a href="financial_reports.php" class="menu-item <?php echo (basename($_SERVER['PHP_SELF']) == 'financial_reports.php') ? 'active' : ''; ?>">
                        <i class="bi bi-graph-up"></i> รายงานการเงิน
                    </a>
                    <a href="budget_management.php" class="menu-item <?php echo (basename($_SERVER['PHP_SELF']) == 'budget_management.php') ? 'active' : ''; ?>">
                        <i class="bi bi-currency-dollar"></i> จัดการงบประมาณ
                    </a>
                    <a href="invoice_management.php" class="menu-item <?php echo (basename($_SERVER['PHP_SELF']) == 'invoice_management.php') ? 'active' : ''; ?>">
                        <i class="bi bi-file-earmark-text"></i> จัดการใบแจ้งหนี้
                    </a>
                    <a href="tax_documents.php" class="menu-item <?php echo (basename($_SERVER['PHP_SELF']) == 'tax_documents.php') ? 'active' : ''; ?>">
                        <i class="bi bi-file-earmark-ruled"></i> เอกสารภาษี
                    </a>
                </div>
            <?php
            }
            
            // เมนูสำหรับผู้อนุมัติ (ไม่ใช่ IT หรือบัญชี แต่มีสิทธิ์อนุมัติ)
            if (isset($_SESSION['division']) && !in_array($_SESSION['division'], ['JD001', 'JD003'])) {
                // ตรวจสอบว่าผู้ใช้มีสิทธิ์อนุมัติหรือไม่จากฐานข้อมูล
                $has_approval_rights = false;
                if ($database_available && $conn) {
                    try {
                        $sql_check_approver = "
                            SELECT COUNT(*) as approval_count
                            FROM dbo.approvals a
                            WHERE a.ApprovalLevel1 = ? OR a.ApprovalLevel2 = ? OR a.ApprovalLevel3 = ?
                        ";
                        $params = [$_SESSION['employee_id'], $_SESSION['employee_id'], $_SESSION['employee_id']];
                        $result = sqlsrv_query($conn, $sql_check_approver, $params);
                        
                        if ($result) {
                            $row = sqlsrv_fetch_array($result, SQLSRV_FETCH_ASSOC);
                            $has_approval_rights = ($row['approval_count'] > 0);
                        }
                    } catch (Exception $e) {
                        error_log("Error checking approval rights: " . $e->getMessage());
                    }
                }
                
                if ($has_approval_rights) {
            ?>
                <div class="menu-section admin-section" style="background:rgba(255,193,7,0.08);">
                    <div class="menu-section-title"><i class="bi bi-person-check"></i> เมนูผู้อนุมัติ</div>
                    <a href="approval_dashboard.php" class="menu-item <?php echo (basename($_SERVER['PHP_SELF']) == 'approval_dashboard.php') ? 'active' : ''; ?>">
                        <i class="bi bi-clipboard-check"></i> ระบบอนุมัติ
                    </a>
                </div>
            <?php
                }
            }
            ?>
            
            <div class="menu-section">
                <div class="menu-section-title">ระบบอื่น ๆ</div>
                <a href="#" onclick="document.getElementById('incentiveForm').submit(); return false;" class="menu-item">
                    <i class="bi bi-gift-fill"></i> ระบบ Incentive
                </a>
                <form id="incentiveForm" action="http://203.154.130.236/incentive/public/index.php" method="post" target="_blank" style="display:none;">
                    <input type="hidden" name="employee_id" value="<?php echo htmlspecialchars($_SESSION['employee_id']); ?>">
                    <input type="hidden" name="first_name_th" value="<?php echo htmlspecialchars($_SESSION['first_name_th']); ?>">
                    <input type="hidden" name="last_name_th" value="<?php echo htmlspecialchars($_SESSION['last_name_th']); ?>">
                    <input type="hidden" name="position_th" value="<?php echo htmlspecialchars($_SESSION['position_th']); ?>">
                    <input type="hidden" name="department_name" value="<?php echo htmlspecialchars($_SESSION['department_name']); ?>">
                </form>
            </div>
            
            <div class="menu-section">
                <a href="../logout.php" class="menu-item logout">
                    <i class="bi bi-box-arrow-right"></i> ออกจากระบบ
                </a>
            </div>
        </nav>
    </div>
</div>
