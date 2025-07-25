<?php
header('Content-Type: text/html; charset=UTF-8');
session_start();
include_once '../includes/header.php';
include_once '../includes/menu.php';
include_once '../config.php';
include_once '../includes/email_config.php';

// ฟังก์ชันส่งอีเมลแจ้งผู้อนุมัติ (ปรับปรุงแล้ว)
function sendApprovalNotificationEmail($conn, $expense_request_id, $approver_level1, $voucher_no, $requester_name, $total_amount, $expense_type) {
    try {
        // ดึงข้อมูลอีเมลของผู้อนุมัติลำดับ 1
        $approver_email = null;
        $approver_name = '';
        
        if ($conn && $approver_level1) {
            if (is_resource($conn)) {
                // SQLSRV Connection
                $sql_email = "SELECT email, first_name_th, last_name_th FROM dbo.employees WHERE employee_id = ?";
                $result_email = sqlsrv_query($conn, $sql_email, array($approver_level1));
                if ($result_email) {
                    $approver_data = sqlsrv_fetch_array($result_email, SQLSRV_FETCH_ASSOC);
                    if ($approver_data) {
                        $approver_email = $approver_data['email'];
                        $approver_name = trim($approver_data['first_name_th'] . ' ' . $approver_data['last_name_th']);
                    }
                }
            } else if (is_object($conn)) {
                $conn_type = get_class($conn);
                if (strpos($conn_type, 'PDO') !== false) {
                    // PDO Connection
                    $sql_email = "SELECT email, first_name_th, last_name_th FROM dbo.employees WHERE employee_id = ?";
                    $stmt_email = $conn->prepare($sql_email);
                    $stmt_email->execute(array($approver_level1));
                    $approver_data = $stmt_email->fetch(PDO::FETCH_ASSOC);
                    if ($approver_data) {
                        $approver_email = $approver_data['email'];
                        $approver_name = trim($approver_data['first_name_th'] . ' ' . $approver_data['last_name_th']);
                    }
                }
            }
        }
        
        // ตรวจสอบอีเมล
        if (empty($approver_email)) {
            error_log("Email notification skipped: No email found for approver " . $approver_level1);
            return false;
        }
        
        // ตรวจสอบว่าอีเมลมีรูปแบบที่ถูกต้อง
        if (!filter_var($approver_email, FILTER_VALIDATE_EMAIL)) {
            error_log("Email notification skipped: Invalid email format for " . $approver_email);
            return false;
        }
        
        // สร้างเนื้อหาอีเมล
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
        
        $expense_type_th = $expense_types[$expense_type] ?? 'อื่นๆ';
        $subject = "[แจ้งเตือน] คำขอเบิกจ่ายรออนุมัติ - เลขที่ " . $voucher_no;
        
        // สร้าง URL สำหรับการอนุมัติ
        $approval_url = APPROVAL_SYSTEM_URL . "?voucher=" . urlencode($voucher_no);
        
        // สร้างเนื้อหาอีเมล HTML
        $html_message = createApprovalEmailTemplate($approver_name, $voucher_no, $requester_name, $expense_type_th, $total_amount, $approval_url);
        
        // สร้างเนื้อหาอีเมล Text (สำหรับ fallback)
        $text_message = "
เรียน คุณ{$approver_name}

คำขอเบิกจ่ายใหม่รออนุมัติจากท่าน

รายละเอียดคำขอ:
- เลขที่ใบเบิก: {$voucher_no}
- ผู้ขอเบิก: {$requester_name}
- ประเภทค่าใช้จ่าย: {$expense_type_th}
- จำนวนเงิน: " . number_format($total_amount, 2) . " บาท
- วันที่สร้างคำขอ: " . date('d/m/Y H:i:s') . "

กรุณาเข้าสู่ระบบเพื่อพิจารณาอนุมัติคำขอนี้: {$approval_url}

ขอบคุณครับ/ค่ะ
ระบบจัดการเบิกจ่าย
        ";
        
        // ใช้ฟังก์ชันส่งอีเมลที่ปรับปรุงแล้ว
        $email_sent = sendApprovalEmail(
            $approver_email, 
            $approver_name, 
            $voucher_no, 
            $requester_name, 
            $expense_type_th, 
            $total_amount
        );
        
        if ($email_sent) {
            error_log("Email notification sent successfully to: " . $approver_email . " for voucher: " . $voucher_no);
            return true;
        } else {
            error_log("Failed to send email notification to: " . $approver_email . " for voucher: " . $voucher_no);
            return false;
        }
        
    } catch (Exception $e) {
        error_log("Error sending email notification: " . $e->getMessage());
        return false;
    }
}

// ตรวจสอบสิทธิ์การเข้าถึง
if (!isset($_SESSION['division']) || !in_array($_SESSION['division'], ['JD001', 'JD003'])) {
    echo '<div style="text-align:center;margin-top:50px;"><h3>คุณไม่มีสิทธิ์เข้าถึงหน้านี้</h3></div>';
    exit;
}

// สำหรับการทดสอบ: ตั้งค่า session หากไม่มี
if (!isset($_SESSION['employee_id'])) {
    $_SESSION['employee_id'] = 'TEST001';  // ค่าสำหรับทดสอบ
    $_SESSION['employee_name'] = 'ผู้ทดสอบระบบ';
    $_SESSION['department'] = 'IT';
    $_SESSION['division'] = 'JD001';
}

// ดึงข้อมูลผู้ใช้จาก session
$user_id = $_SESSION['employee_id'] ?? null;
$user_name = $_SESSION['user_name'] ?? null;
$user_first_name_th = $_SESSION['first_name_th'] ?? null;
$user_last_name_th = $_SESSION['last_name_th'] ?? null;
$user_employee_id = $_SESSION['employee_id'] ?? null;
$user_approver_id = $_SESSION['approver_id'] ?? null;
$user_role = $_SESSION['role'] ?? null;

// สร้างการเชื่อมต่อฐานข้อมูลจาก config.php
try {
    $conn = dbConnect();
    $database_available = true;
    error_log("Database connection successful using config.php");
} catch (Exception $e) {
    $database_available = false;
    error_log("Database connection failed: " . $e->getMessage());
    // ใช้ mock data หากเชื่อมต่อไม่ได้
}

// ดึงข้อมูล approver_id ของผู้ใช้ที่ล็อกอินอยู่
$current_user_approver_id = null;
if ($database_available && isset($_SESSION['employee_id'])) {
    $sql_user_approver = "SELECT approver_id FROM dbo.employees WHERE employee_id = ?";
    
    try {
        if (is_resource($conn)) {
            // SQLSRV Connection
            $stmt_user = sqlsrv_prepare($conn, $sql_user_approver, array($_SESSION['employee_id']));
            if ($stmt_user && sqlsrv_execute($stmt_user)) {
                $user_data = sqlsrv_fetch_array($stmt_user, SQLSRV_FETCH_ASSOC);
                if ($user_data) {
                    $current_user_approver_id = $user_data['approver_id'];
                }
            }
        } else if (is_object($conn)) {
            $conn_type = get_class($conn);
            if (strpos($conn_type, 'PDO') !== false) {
                // PDO Connection
                $stmt_user = $conn->prepare($sql_user_approver);
                $stmt_user->execute([$_SESSION['employee_id']]);
                $user_data = $stmt_user->fetch(PDO::FETCH_ASSOC);
                if ($user_data) {
                    $current_user_approver_id = $user_data['approver_id'];
                }
            } else if (strpos($conn_type, 'mysqli') !== false) {
                // MySQLi Connection
                $stmt_user = $conn->prepare($sql_user_approver);
                $stmt_user->bind_param('s', $_SESSION['employee_id']);
                $stmt_user->execute();
                $result_user = $stmt_user->get_result();
                $user_data = $result_user->fetch_assoc();
                if ($user_data) {
                    $current_user_approver_id = $user_data['approver_id'];
                }
            }
        }
    } catch (Exception $e) {
        error_log("Error fetching user approver_id: " . $e->getMessage());
        $current_user_approver_id = 1; // Default for testing
    }
} else {
    $current_user_approver_id = 1; // Default for testing when database not available
}

// ดึงข้อมูลแผนกจากตาราง dept
$sql_departments = "SELECT DeptID, DeptName FROM dbo.dept ORDER BY DeptName";
$departments_list = [];

// ดึงข้อมูลผู้อนุมัติจากตาราง approvals และ employees
$sql_approvers = "
    SELECT DISTINCT 
        e.employee_id, 
        e.first_name_th, 
        e.last_name_th, 
        e.position_th,
        a.ApprovalName,
        a.ApprovalID
    FROM dbo.employees e
    LEFT JOIN dbo.approvals a ON e.approver_id = a.ApprovalID
    WHERE e.division IN ('JD001', 'JD003') 
    AND (a.ApprovalLevel1 IS NOT NULL OR a.ApprovalLevel2 IS NOT NULL OR a.ApprovalLevel3 IS NOT NULL)
    ORDER BY e.first_name_th
";

$approvers_list = [];
$approval_structures = [];

// ตรวจสอบว่าฐานข้อมูลพร้อมใช้งานหรือไม่
if ($database_available) {
    try {
        // ตรวจสอบประเภทการเชื่อมต่อฐานข้อมูล
        error_log("DEBUG: Connection type check - is_resource: " . (is_resource($conn) ? 'true' : 'false'));
        error_log("DEBUG: Connection type check - is_object: " . (is_object($conn) ? 'true' : 'false'));
        if (is_object($conn)) {
            $conn_type = get_class($conn);
            error_log("DEBUG: Connection class: " . $conn_type);
        }
    
    if (is_resource($conn)) {
        // SQLSRV Connection (resource) - ดึงข้อมูลแผนก
        $result_departments = sqlsrv_query($conn, $sql_departments);
        if ($result_departments) {
            while ($department = sqlsrv_fetch_array($result_departments, SQLSRV_FETCH_ASSOC)) {
                $departments_list[] = $department;
            }
        }
        
        // ดึงข้อมูลผู้อนุมัติ
        $result_approvers = sqlsrv_query($conn, $sql_approvers);
        if ($result_approvers) {
            while ($approver = sqlsrv_fetch_array($result_approvers, SQLSRV_FETCH_ASSOC)) {
                $approvers_list[] = $approver;
            }
        }
        
        // ดึงข้อมูลโครงสร้างการอนุมัติพร้อมแผนกที่เกี่ยวข้อง
        $sql_approval_structure = "
            SELECT DISTINCT
                a.ApprovalID,
                a.ApprovalName,
                a.ApprovalLevel1,
                a.ApprovalLevel2,
                a.ApprovalLevel3,
                a.level1_amount_limit,
                a.level2_amount_limit,
                a.level3_amount_limit,
                e1.employee_id as level1_id, e1.first_name_th as level1_first, e1.last_name_th as level1_last, e1.position_th as level1_position,
                e2.employee_id as level2_id, e2.first_name_th as level2_first, e2.last_name_th as level2_last, e2.position_th as level2_position,
                e3.employee_id as level3_id, e3.first_name_th as level3_first, e3.last_name_th as level3_last, e3.position_th as level3_position,
                STRING_AGG(DISTINCT emp.division, ',') as related_departments
            FROM dbo.approvals a
            LEFT JOIN dbo.employees e1 ON a.ApprovalLevel1 = e1.employee_id
            LEFT JOIN dbo.employees e2 ON a.ApprovalLevel2 = e2.employee_id
            LEFT JOIN dbo.employees e3 ON a.ApprovalLevel3 = e3.employee_id
            LEFT JOIN dbo.employees emp ON emp.approver_id = a.ApprovalID
            GROUP BY a.ApprovalID, a.ApprovalName, a.ApprovalLevel1, a.ApprovalLevel2, a.ApprovalLevel3,
                     a.level1_amount_limit, a.level2_amount_limit, a.level3_amount_limit,
                     e1.employee_id, e1.first_name_th, e1.last_name_th, e1.position_th,
                     e2.employee_id, e2.first_name_th, e2.last_name_th, e2.position_th,
                     e3.employee_id, e3.first_name_th, e3.last_name_th, e3.position_th
            ORDER BY a.ApprovalID
        ";
        
        $result_structure = sqlsrv_query($conn, $sql_approval_structure);
        if ($result_structure) {
            while ($structure = sqlsrv_fetch_array($result_structure, SQLSRV_FETCH_ASSOC)) {
                $approval_structures[] = array(
                    'ApprovalID' => $structure['ApprovalID'],
                    'ApprovalName' => $structure['ApprovalName'],
                    'ApprovalLevel1' => $structure['ApprovalLevel1'],
                    'ApprovalLevel2' => $structure['ApprovalLevel2'],
                    'ApprovalLevel3' => $structure['ApprovalLevel3'],
                    'level1_amount_limit' => $structure['level1_amount_limit'],
                    'level2_amount_limit' => $structure['level2_amount_limit'],
                    'level3_amount_limit' => $structure['level3_amount_limit'],
                    'level1_id' => $structure['level1_id'],
                    'level1_first' => $structure['level1_first'],
                    'level1_last' => $structure['level1_last'],
                    'level1_position' => $structure['level1_position'] ?? '',
                    'level2_id' => $structure['level2_id'],
                    'level2_first' => $structure['level2_first'],
                    'level2_last' => $structure['level2_last'],
                    'level2_position' => $structure['level2_position'] ?? '',
                    'level3_id' => $structure['level3_id'],
                    'level3_first' => $structure['level3_first'],
                    'level3_last' => $structure['level3_last'],
                    'level3_position' => $structure['level3_position'] ?? '',
                    'related_departments' => $structure['related_departments'] ?? ''
                );
            }
        }
        
    } else if (is_object($conn)) {
        $conn_type = get_class($conn);
        
        if (strpos($conn_type, 'PDO') !== false) {
            // PDO Connection - ดึงข้อมูลแผนก
            $sql_departments_pdo = "SELECT DeptID, DeptName FROM dbo.dept ORDER BY DeptName";
            $stmt_dept = $conn->prepare($sql_departments_pdo);
            $stmt_dept->execute();
            $departments_list = $stmt_dept->fetchAll(PDO::FETCH_ASSOC);
            
            // ดึงข้อมูลผู้อนุมัติ
            $stmt = $conn->prepare($sql_approvers);
            $stmt->execute();
            $approvers_list = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // ดึงข้อมูลโครงสร้างการอนุมัติพร้อมแผนกที่เกี่ยวข้อง
            $sql_approval_structure = "
                SELECT DISTINCT
                    a.ApprovalID,
                    a.ApprovalName,
                    a.ApprovalLevel1,
                    a.ApprovalLevel2,
                    a.ApprovalLevel3,
                    a.level1_amount_limit,
                    a.level2_amount_limit,
                    a.level3_amount_limit,
                    e1.employee_id as level1_id, e1.first_name_th as level1_first, e1.last_name_th as level1_last, e1.position_th as level1_position,
                    e2.employee_id as level2_id, e2.first_name_th as level2_first, e2.last_name_th as level2_last, e2.position_th as level2_position,
                    e3.employee_id as level3_id, e3.first_name_th as level3_first, e3.last_name_th as level3_last, e3.position_th as level3_position
                FROM dbo.approvals a
                LEFT JOIN dbo.Employees e1 ON a.ApprovalLevel1 = e1.employee_id
                LEFT JOIN dbo.Employees e2 ON a.ApprovalLevel2 = e2.employee_id
                LEFT JOIN dbo.Employees e3 ON a.ApprovalLevel3 = e3.employee_id
                ORDER BY a.ApprovalID
            ";
            
            $stmt_structure = $conn->prepare($sql_approval_structure);
            if (!$stmt_structure) {
                error_log("DEBUG: PDO prepare failed: " . print_r($conn->errorInfo(), true));
            } else {
                $stmt_structure->execute();
                if ($stmt_structure->errorCode() !== '00000') {
                    error_log("DEBUG: PDO execute failed: " . print_r($stmt_structure->errorInfo(), true));
                }
                $approval_structures_raw = $stmt_structure->fetchAll(PDO::FETCH_ASSOC);
                
                // DEBUG: ดูข้อมูลที่ดึงมาได้
                error_log("DEBUG: PDO approval_structures_raw count = " . count($approval_structures_raw));
            }
            
            // แปลงข้อมูลให้มีรูปแบบที่ใช้งานง่าย
            $approval_structures = array();
            foreach ($approval_structures_raw as $structure) {
                error_log("DEBUG: Processing ApprovalID = " . $structure['ApprovalID']);
                $approval_structures[] = array(
                    'ApprovalID' => $structure['ApprovalID'],
                    'ApprovalName' => $structure['ApprovalName'],
                    'ApprovalLevel1' => $structure['ApprovalLevel1'],
                    'ApprovalLevel2' => $structure['ApprovalLevel2'],
                    'ApprovalLevel3' => $structure['ApprovalLevel3'],
                    'level1_amount_limit' => $structure['level1_amount_limit'],
                    'level2_amount_limit' => $structure['level2_amount_limit'],
                    'level3_amount_limit' => $structure['level3_amount_limit'],
                    'level1_id' => $structure['level1_id'],
                    'level1_first' => $structure['level1_first'],
                    'level1_last' => $structure['level1_last'],
                    'level1_position' => $structure['level1_position'] ?? '',
                    'level2_id' => $structure['level2_id'],
                    'level2_first' => $structure['level2_first'],
                    'level2_last' => $structure['level2_last'],
                    'level2_position' => $structure['level2_position'] ?? '',
                    'level3_id' => $structure['level3_id'],
                    'level3_first' => $structure['level3_first'],
                    'level3_last' => $structure['level3_last'],
                    'level3_position' => $structure['level3_position'] ?? '',
                    'related_departments' => $structure['related_departments'] ?? ''
                );
            }
            
        } else if (strpos($conn_type, 'mysqli') !== false) {
            // MySQLi Connection - ดึงข้อมูลแผนก
            $sql_departments_mysqli = "SELECT DeptID, DeptName FROM dept ORDER BY DeptName";
            $result_departments = $conn->query($sql_departments_mysqli);
            if ($result_departments) {
                while ($department = $result_departments->fetch_assoc()) {
                    $departments_list[] = $department;
                }
            }
            
            // ดึงข้อมูลผู้อนุมัติ
            $result_approvers = $conn->query($sql_approvers);
            if ($result_approvers) {
                while ($approver = $result_approvers->fetch_assoc()) {
                    $approvers_list[] = $approver;
                }
            }
            
            // ดึงข้อมูลโครงสร้างการอนุมัติ
            $sql_approval_structure = "
                SELECT 
                    a.ApprovalID,
                    a.ApprovalName,
                    e1.employee_id as level1_id, e1.first_name_th as level1_first, e1.last_name_th as level1_last, e1.position_th as level1_position,
                    e2.employee_id as level2_id, e2.first_name_th as level2_first, e2.last_name_th as level2_last, e2.position_th as level2_position,
                    e3.employee_id as level3_id, e3.first_name_th as level3_first, e3.last_name_th as level3_last, e3.position_th as level3_position
                FROM approvals a
                LEFT JOIN Employees e1 ON a.ApprovalLevel1 = e1.employee_id
                LEFT JOIN Employees e2 ON a.ApprovalLevel2 = e2.employee_id
                LEFT JOIN Employees e3 ON a.ApprovalLevel3 = e3.employee_id
                ORDER BY a.ApprovalID
            ";
            
            $result_structure = $conn->query($sql_approval_structure);
            if ($result_structure) {
                while ($structure = $result_structure->fetch_assoc()) {
                    $approval_structures[] = $structure;
                }
            }
        }
    }
} catch (Exception $e) {
    error_log("Error fetching approvers: " . $e->getMessage());
    $approvers_list = [];
    $approval_structures = [];
    $departments_list = [];
}
} else {
    // ใช้ข้อมูล mock เมื่อฐานข้อมูลไม่พร้อม
    error_log("Database not available, using mock data");
    
    $departments_list = [
        ['DeptID' => 'IT', 'DeptName' => 'สารสนเทศ'],
        ['DeptID' => 'HR', 'DeptName' => 'ทรัพยากรบุคคล'],
        ['DeptID' => 'FIN', 'DeptName' => 'การเงิน'],
        ['DeptID' => 'MKT', 'DeptName' => 'การตลาด']
    ];
    
    $approval_structures = [
        [
            'ApprovalID' => 1,
            'ApprovalName' => 'โครงสร้างการอนุมัติทั่วไป (Mock)',
            'ApprovalLevel1' => 'TEST001',
            'ApprovalLevel2' => 'ADMIN001',
            'ApprovalLevel3' => null,
            'level1_amount_limit' => 10000.00,
            'level2_amount_limit' => 50000.00,
            'level3_amount_limit' => 100000.00,
            'level1_id' => 'TEST001',
            'level1_first' => 'ผู้ทดสอบ',
            'level1_last' => 'ระบบ',
            'level1_position' => 'พนักงานทดสอบ',
            'level2_id' => 'ADMIN001',
            'level2_first' => 'ผู้ดูแล',
            'level2_last' => 'ระบบ',
            'level2_position' => 'ผู้ดูแลระบบ',
            'level3_id' => null,
            'level3_first' => null,
            'level3_last' => null,
            'level3_position' => null,
            'related_departments' => 'JD001,JD003'
        ]
    ];
}

// กรองโครงสร้างการอนุมัติให้แสดงเฉพาะที่ตรงกับ approver_id ของผู้ใช้ที่ล็อกอิน
if ($current_user_approver_id) {
    // ใช้วิธีง่ายๆ ในการค้นหา
    $found_structure = null;
    foreach ($approval_structures as $structure) {
        if ($structure['ApprovalID'] === $current_user_approver_id) {
            $found_structure = $structure;
            break;
        }
    }
    
    // ถ้าไม่พบ ให้สร้างข้อมูลจากฐานข้อมูลโดยตรง
    if (!$found_structure && $current_user_approver_id) {
        try {
            if ($conn instanceof PDO) {
                // PDO connection
                $sql_direct = "SELECT 
                    ApprovalID, ApprovalName, ApprovalLevel1, ApprovalLevel2, ApprovalLevel3,
                    level1_amount_limit, level2_amount_limit, level3_amount_limit
                    FROM dbo.approvals WHERE ApprovalID = ?";
                $stmt_direct = $conn->prepare($sql_direct);
                $stmt_direct->execute([$current_user_approver_id]);
                $approval_data = $stmt_direct->fetch(PDO::FETCH_ASSOC);
            } else {
                // SQLSRV connection
                $sql_direct = "SELECT 
                    ApprovalID, ApprovalName, ApprovalLevel1, ApprovalLevel2, ApprovalLevel3,
                    level1_amount_limit, level2_amount_limit, level3_amount_limit
                    FROM dbo.approvals WHERE ApprovalID = ?";
                $params = array($current_user_approver_id);
                $stmt_direct = sqlsrv_query($conn, $sql_direct, $params);
                $approval_data = $stmt_direct ? sqlsrv_fetch_array($stmt_direct, SQLSRV_FETCH_ASSOC) : false;
            }
            
            if ($approval_data) {
                $level1_info = $level2_info = $level3_info = null;
                
                // ดึงข้อมูล Level 1
                if (!empty($approval_data['ApprovalLevel1'])) {
                    $sql_emp1 = "SELECT first_name_th, last_name_th, position_th FROM dbo.Employees WHERE employee_id = ?";
                    $stmt_emp1 = sqlsrv_query($conn, $sql_emp1, array($approval_data['ApprovalLevel1']));
                    $level1_info = $stmt_emp1 ? sqlsrv_fetch_array($stmt_emp1, SQLSRV_FETCH_ASSOC) : null;
                }
                
                // ดึงข้อมูล Level 2
                if (!empty($approval_data['ApprovalLevel2'])) {
                    $sql_emp2 = "SELECT first_name_th, last_name_th, position_th FROM dbo.Employees WHERE employee_id = ?";
                    $stmt_emp2 = sqlsrv_query($conn, $sql_emp2, array($approval_data['ApprovalLevel2']));
                    $level2_info = $stmt_emp2 ? sqlsrv_fetch_array($stmt_emp2, SQLSRV_FETCH_ASSOC) : null;
                }
                
                // ดึงข้อมูล Level 3
                if (!empty($approval_data['ApprovalLevel3'])) {
                    $sql_emp3 = "SELECT first_name_th, last_name_th, position_th FROM dbo.Employees WHERE employee_id = ?";
                    $stmt_emp3 = sqlsrv_query($conn, $sql_emp3, array($approval_data['ApprovalLevel3']));
                    $level3_info = $stmt_emp3 ? sqlsrv_fetch_array($stmt_emp3, SQLSRV_FETCH_ASSOC) : null;
                }
                
                $found_structure = array(
                    'ApprovalID' => $approval_data['ApprovalID'],
                    'ApprovalName' => $approval_data['ApprovalName'],
                    'ApprovalLevel1' => $approval_data['ApprovalLevel1'],
                    'ApprovalLevel2' => $approval_data['ApprovalLevel2'],
                    'ApprovalLevel3' => $approval_data['ApprovalLevel3'],
                    'level1_amount_limit' => $approval_data['level1_amount_limit'],
                    'level2_amount_limit' => $approval_data['level2_amount_limit'],
                    'level3_amount_limit' => $approval_data['level3_amount_limit'],
                    'level1_id' => $approval_data['ApprovalLevel1'],
                    'level1_first' => $level1_info['first_name_th'] ?? '',
                    'level1_last' => $level1_info['last_name_th'] ?? '',
                    'level1_position' => $level1_info['position_th'] ?? '',
                    'level2_id' => $approval_data['ApprovalLevel2'],
                    'level2_first' => $level2_info['first_name_th'] ?? '',
                    'level2_last' => $level2_info['last_name_th'] ?? '',
                    'level2_position' => $level2_info['position_th'] ?? '',
                    'level3_id' => $approval_data['ApprovalLevel3'],
                    'level3_first' => $level3_info['first_name_th'] ?? '',
                    'level3_last' => $level3_info['last_name_th'] ?? '',
                    'level3_position' => $level3_info['position_th'] ?? '',
                    'related_departments' => ''
                );
            }
        } catch (Exception $e) {
            error_log("Error in direct approval query: " . $e->getMessage());
        }
    }
    
    $approval_structures = $found_structure ? [$found_structure] : [];
} else {
    error_log("DEBUG: current_user_approver_id is empty");
}

// ดึงข้อมูลประเภทค่าใช้จ่าย
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

// ประมวลผลฟอร์ม
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // รับข้อมูลจากฟอร์ม
    $company_branch = $_POST['company_branch_form'] ?? '';
    $voucher_no = trim($_POST['voucher_no'] ?? '');
    $voucher_type = $_POST['voucher_type'] ?? 'advance';
    $pay_to = $_POST['request_title'] ?? '';
    $department = $_POST['department'] ?? '';
    $cost_center = $_POST['cost_center'] ?? '';
    $request_date = $_POST['request_date'] ?? date('Y-m-d');
    $expense_type = $_POST['expense_type'] ?? '';
    $total_amount = $_POST['total_amount'] ?? ($_POST['amount'] ?? 0);
    $description = $_POST['description'] ?? '';
    $approver_level1 = $_POST['approver_level1'] ?? null;
    $approver_level2 = $_POST['approver_level2'] ?? null;
    $approver_level3 = $_POST['approver_level3'] ?? null;
    $requester_id = $_SESSION['employee_id'] ?? null;
    
    // ตรวจสอบเลขที่ใบเบิก
    if (empty($voucher_no)) {
        $error_message = "กรุณาระบุเลขที่ใบเบิกเงินสดย่อย";
    } else if (strlen($voucher_no) < 5) {
        $error_message = "เลขที่ใบเบิกต้องมีความยาวอย่างน้อย 5 ตัวอักษร";
    } else if (!preg_match('/^[A-Za-z0-9\-_]+$/', $voucher_no)) {
        $error_message = "เลขที่ใบเบิกสามารถใช้ได้เฉพาะตัวอักษรภาษาอังกฤษ ตัวเลข และเครื่องหมาย - หรือ _ เท่านั้น";
    }
    
    // หากมีข้อผิดพลาดในเลขที่ใบเบิก ให้หยุดการประมวลผล
    if (isset($error_message)) {
        // ไม่ต้องทำอะไรเพิ่ม จะแสดง error message ด้านล่าง
    } else {
    
    // รับข้อมูลรายการค่าใช้จ่าย
    $expense_items = $_POST['expense_items'] ?? [];
    $expense_amounts = $_POST['expense_amounts'] ?? [];
    $expense_notes = $_POST['expense_notes'] ?? [];
    
    // แมป company_branch กับ company_id
    $company_mapping = [
        'main' => 1,
        'branch1' => 2,
        'branch2' => 3,
        'subsidiary1' => 4,
        'subsidiary2' => 5
    ];
    $company_id = $company_mapping[$company_branch] ?? 1;
    
    // ตรวจสอบ session และ requester_id
    if (empty($requester_id)) {
        $error_message = "ไม่พบข้อมูลผู้ใช้ในระบบ กรุณาเข้าสู่ระบบใหม่";
        return;
    }
    
    // Debug: แสดงข้อมูลที่ได้รับ
    error_log("Debug POST data: " . print_r($_POST, true));
    error_log("Debug total_amount: " . $total_amount);
    error_log("Debug requester_id: " . $requester_id);
    error_log("Debug expense_items: " . print_r($expense_items, true));
    error_log("Debug expense_amounts: " . print_r($expense_amounts, true));
    
    // ตรวจสอบข้อมูลพื้นฐาน
    if (empty($pay_to)) {
        $error_message = "กรุณาระบุชื่อผู้รับเงิน/หน่วยงาน";
        return;
    }
    
    if (empty($expense_type)) {
        $error_message = "กรุณาเลือกประเภทค่าใช้จ่าย";
        return;
    }
    
    if ($total_amount <= 0) {
        $error_message = "กรุณาระบุจำนวนเงินที่มากกว่า 0";
        return;
    }
    
    // ตรวจสอบรายการค่าใช้จ่าย
    if (empty($expense_items) || !is_array($expense_items)) {
        $error_message = "ไม่พบรายการค่าใช้จ่าย กรุณาเพิ่มรายการอย่างน้อย 1 รายการ";
        return;
    }
    
    // ตรวจสอบว่ามีรายการที่ถูกต้องอย่างน้อย 1 รายการ
    $valid_items = 0;
    for ($i = 0; $i < count($expense_items); $i++) {
        if (!empty(trim($expense_items[$i])) && isset($expense_amounts[$i]) && $expense_amounts[$i] > 0) {
            $valid_items++;
        }
    }
    
    if ($valid_items === 0) {
        $error_message = "ไม่มีรายการค่าใช้จ่ายที่ถูกต้อง กรุณาระบุชื่อรายการและจำนวนเงินที่มากกว่า 0";
        return;
    }
    
    // ตรวจสอบข้อมูลที่จำเป็น
    if (!empty($pay_to) && !empty($expense_type) && $total_amount > 0 && $valid_items > 0) {
        
        try {
            // ตรวจสอบการเชื่อมต่อฐานข้อมูล
            if (!$conn) {
                // สำหรับการทดสอบ: ใช้ไฟล์ JSON แทนฐานข้อมูล
                
                // ตรวจสอบและบันทึกเฉพาะรายการที่ถูกต้อง
                $valid_expense_items = [];
                $valid_expense_amounts = [];
                $valid_expense_notes = [];
                
                for ($i = 0; $i < count($expense_items); $i++) {
                    if (!empty(trim($expense_items[$i])) && isset($expense_amounts[$i]) && $expense_amounts[$i] > 0) {
                        $valid_expense_items[] = trim($expense_items[$i]);
                        $valid_expense_amounts[] = floatval($expense_amounts[$i]);
                        $valid_expense_notes[] = isset($expense_notes[$i]) ? trim($expense_notes[$i]) : '';
                    }
                }
                
                if (empty($valid_expense_items)) {
                    throw new Exception("ไม่มีรายการค่าใช้จ่ายที่ถูกต้องสำหรับบันทึก");
                }
                
                $test_data = [
                    'voucher_no' => $voucher_no,
                    'pay_to' => $pay_to,
                    'total_amount' => $total_amount,
                    'expense_type' => $expense_type,
                    'expense_items' => $valid_expense_items,
                    'expense_amounts' => $valid_expense_amounts,
                    'expense_notes' => $valid_expense_notes,
                    'items_count' => count($valid_expense_items),
                    'created_at' => date('Y-m-d H:i:s'),
                    'status' => 'success'
                ];
                
                // บันทึกลงไฟล์สำหรับทดสอบ
                $test_file = '../test_data_' . date('Ymd') . '.json';
                if (file_put_contents($test_file, json_encode($test_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE))) {
                    $success_message = "สร้างคำขอเบิกจ่ายเรียบร้อยแล้ว! (โหมดทดสอบ) เลขที่ใบเบิก: " . $voucher_no . " จำนวน " . count($valid_expense_items) . " รายการ";
                    
                    // ส่งอีเมลแจ้งผู้อนุมัติในโหมดทดสอบ
                    if (!empty($approver_level1)) {
                        $requester_name = $_SESSION['employee_name'] ?? 'ผู้ทดสอบระบบ';
                        
                        // ในโหมดทดสอบ ใช้ข้อมูล mock
                        error_log("Test mode: Would send email to approver " . $approver_level1 . " for voucher " . $voucher_no);
                        $success_message .= " (โหมดทดสอบ: จำลองการส่งอีเมลแจ้งผู้อนุมัติ)";
                    }
                    
                    error_log("Test mode: Saved " . count($valid_expense_items) . " expense items to $test_file");
                } else {
                    throw new Exception("ไม่สามารถบันทึกไฟล์ทดสอบได้");
                }
                error_log("Test mode: Data saved to " . $test_file);
            } else {
            // ตรวจสอบว่าฐานข้อมูลพร้อมใช้งานหรือไม่
            if (!$database_available) {
                throw new Exception("ระบบฐานข้อมูลไม่พร้อมใช้งาน กรุณาติดต่อผู้ดูแลระบบ");
            }
            
            // ตรวจสอบการเชื่อมต่อ
            if (!$conn) {
                throw new Exception("ไม่สามารถเชื่อมต่อฐานข้อมูลได้");
            }
            
            error_log("Database connection confirmed, proceeding with data save");
            
            if (is_resource($conn)) {
                // SQLSRV Connection (resource)
                sqlsrv_begin_transaction($conn);
                
                // สร้างเลขที่ใบเบิกถ้าไม่มี
                if (empty($voucher_no)) {
                    $voucher_no = 'PCV-' . date('Ymd') . '-' . sprintf('%04d', rand(1, 9999));
                }
                
                // แปลง approver เป็น null ถ้าเป็น 0 หรือ empty
                $approver_level1 = (empty($approver_level1) || $approver_level1 == '0') ? null : $approver_level1;
                $approver_level2 = (empty($approver_level2) || $approver_level2 == '0') ? null : $approver_level2;
                $approver_level3 = (empty($approver_level3) || $approver_level3 == '0') ? null : $approver_level3;
                
                // บันทึกข้อมูลหลักของคำขอเบิกจ่าย
                $sql_insert_request = "
                    INSERT INTO dbo.ExpenseRequests 
                    (voucher_no, company_id, requester_id, pay_to, department, cost_center, 
                     request_date, voucher_type, expense_type, total_amount, description,
                     approver_level1, approver_level2, approver_level3,
                     status_level1, status_level2, status_level3, current_approval_level, 
                     overall_status, remarks, created_at, updated_at) 
                    VALUES 
                    (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 
                     'Pending', 'Pending', 'Pending', 1, 'Pending', ?, GETDATE(), GETDATE())
                ";
                
                $remarks = "คำขอเบิกจ่าย" . ($expense_types[$expense_type] ?? 'อื่นๆ') . " จำนวน " . number_format($total_amount, 2) . " บาท";
                
                $params_request = [
                    $voucher_no, $company_id, $requester_id, $pay_to, $department, $cost_center,
                    $request_date, $voucher_type, $expense_type, $total_amount, $description,
                    $approver_level1, $approver_level2, $approver_level3, $remarks
                ];
                
                // Debug: แสดงข้อมูลที่จะบันทึก
                error_log("Debug SQL params: " . print_r($params_request, true));
                
                $result_request = sqlsrv_query($conn, $sql_insert_request, $params_request);
                
                if (!$result_request) {
                    $sql_errors = sqlsrv_errors();
                    error_log("SQL Error: " . print_r($sql_errors, true));
                    throw new Exception("ไม่สามารถบันทึกข้อมูลคำขอได้: " . print_r($sql_errors, true));
                }
                
                // ดึง ID ของคำขอที่เพิ่งสร้าง - ใช้หลายวิธีเพื่อความแน่ใจ
                $expense_request_id = null;
                
                // Method 1: ใช้ SCOPE_IDENTITY()
                $sql_get_id = "SELECT SCOPE_IDENTITY() as id";
                $result_id = sqlsrv_query($conn, $sql_get_id);
                
                if ($result_id) {
                    $row_id = sqlsrv_fetch_array($result_id, SQLSRV_FETCH_ASSOC);
                    if ($row_id && !empty($row_id['id'])) {
                        $expense_request_id = $row_id['id'];
                        error_log("DEBUG: Got expense_request_id from SCOPE_IDENTITY = " . $expense_request_id);
                    }
                }
                
                // Method 2: หาก SCOPE_IDENTITY() ไม่ได้ผล ให้หาจาก voucher_no
                if (empty($expense_request_id)) {
                    error_log("SCOPE_IDENTITY() failed, trying to find by voucher_no: " . $voucher_no);
                    
                    $sql_find_by_voucher = "SELECT TOP 1 id FROM dbo.ExpenseRequests WHERE voucher_no = ? ORDER BY created_at DESC";
                    $result_voucher = sqlsrv_query($conn, $sql_find_by_voucher, array($voucher_no));
                    
                    if ($result_voucher) {
                        $row_voucher = sqlsrv_fetch_array($result_voucher, SQLSRV_FETCH_ASSOC);
                        if ($row_voucher && !empty($row_voucher['id'])) {
                            $expense_request_id = $row_voucher['id'];
                            error_log("DEBUG: Got expense_request_id from voucher_no = " . $expense_request_id);
                        }
                    }
                }
                
                // Method 3: หาก Method 2 ไม่ได้ผล ให้หาจาก pay_to และ total_amount
                if (empty($expense_request_id)) {
                    error_log("voucher_no search failed, trying by pay_to and amount");
                    
                    $sql_find_by_details = "
                        SELECT TOP 1 id 
                        FROM dbo.ExpenseRequests 
                        WHERE pay_to = ? AND total_amount = ? AND requester_id = ?
                        ORDER BY created_at DESC
                    ";
                    $result_details = sqlsrv_query($conn, $sql_find_by_details, array($pay_to, $total_amount, $requester_id));
                    
                    if ($result_details) {
                        $row_details = sqlsrv_fetch_array($result_details, SQLSRV_FETCH_ASSOC);
                        if ($row_details && !empty($row_details['id'])) {
                            $expense_request_id = $row_details['id'];
                            error_log("DEBUG: Got expense_request_id from details search = " . $expense_request_id);
                        }
                    }
                }
                
                // ตรวจสอบผลลัพธ์สุดท้าย
                if (empty($expense_request_id)) {
                    $sql_errors = sqlsrv_errors();
                    error_log("CRITICAL: All methods failed to get expense_request_id");
                    error_log("SQL Errors: " . print_r($sql_errors, true));
                    error_log("Voucher No: " . $voucher_no . ", Pay To: " . $pay_to . ", Amount: " . $total_amount);
                    throw new Exception("ไม่สามารถดึง ID ของคำขอที่เพิ่งสร้างได้ กรุณาลองใหม่อีกครั้ง หรือติดต่อผู้ดูแลระبบ");
                }
                
                error_log("SUCCESS: Final expense_request_id = " . $expense_request_id);
                
                // บันทึกรายการค่าใช้จ่ายแต่ละรายการ
                $sql_insert_item = "
                    INSERT INTO dbo.ExpenseItems 
                    (expense_request_id, item_description, amount, notes, created_at) 
                    VALUES (?, ?, ?, ?, GETDATE())
                ";
                
                // ตรวจสอบว่ามีรายการค่าใช้จ่ายหรือไม่
                if (empty($expense_items) || !is_array($expense_items)) {
                    throw new Exception("ไม่พบรายการค่าใช้จ่าย กรุณาระบุรายการอย่างน้อย 1 รายการ");
                }
                
                // ตรวจสอบว่า expense_request_id ไม่เป็น null
                if (empty($expense_request_id)) {
                    error_log("CRITICAL: expense_request_id is empty before saving items");
                    throw new Exception("ไม่สามารถดึง ID ของคำขอเบิกจ่ายได้ กรุณาลองใหม่อีกครั้ง");
                }
                
                error_log("DEBUG: About to save expense items with expense_request_id = " . $expense_request_id);
                
                $items_saved = 0;
                for ($i = 0; $i < count($expense_items); $i++) {
                    if (!empty($expense_items[$i]) && isset($expense_amounts[$i]) && $expense_amounts[$i] > 0) {
                        
                        // ตรวจสอบค่าที่จะบันทึก
                        $item_desc = trim($expense_items[$i]);
                        $item_amount = floatval($expense_amounts[$i]);
                        $item_note = isset($expense_notes[$i]) ? trim($expense_notes[$i]) : '';
                        
                        if (empty($item_desc)) {
                            continue; // ข้ามรายการที่ไม่มีชื่อ
                        }
                        
                        error_log("Saving SQLSRV expense item: $item_desc, Amount: $item_amount, Note: $item_note");
                        
                        $params_item = [
                            $expense_request_id,
                            $item_desc,
                            $item_amount,
                            $item_note
                        ];
                        
                        $result_item = sqlsrv_query($conn, $sql_insert_item, $params_item);
                        
                        if (!$result_item) {
                            $errors = sqlsrv_errors();
                            error_log("SQLSRV Item Error: " . print_r($errors, true));
                            throw new Exception("ไม่สามารถบันทึกรายการค่าใช้จ่าย '$item_desc' ได้: " . $errors[0]['message']);
                        }
                        
                        $items_saved++;
                    }
                }
                
                if ($items_saved === 0) {
                    throw new Exception("ไม่มีรายการค่าใช้จ่ายที่ถูกต้องสำหรับบันทึก กรุณาตรวจสอบข้อมูล");
                }
                
                // Commit Transaction
                sqlsrv_commit($conn);
                
            } else if (is_object($conn)) {
                $conn_type = get_class($conn);
                
                if (strpos($conn_type, 'PDO') !== false) {
                    // PDO Connection
                    $conn->beginTransaction();
                    
                    // สร้างเลขที่ใบเบิกถ้าไม่มี
                    if (empty($voucher_no)) {
                        $voucher_no = 'PCV-' . date('Ymd') . '-' . sprintf('%04d', rand(1, 9999));
                    }
                    
                    // แปลง approver เป็น null ถ้าเป็น 0 หรือ empty
                    $approver_level1 = (empty($approver_level1) || $approver_level1 == '0') ? null : $approver_level1;
                    $approver_level2 = (empty($approver_level2) || $approver_level2 == '0') ? null : $approver_level2;
                    $approver_level3 = (empty($approver_level3) || $approver_level3 == '0') ? null : $approver_level3;
                    
                    // บันทึกข้อมูลหลักของคำขอเบิกจ่าย (สำหรับ PDO)
                    // ปรับ SQL สำหรับ MySQL/PostgreSQL
                    $sql_insert_request = "
                        INSERT INTO ExpenseRequests 
                        (voucher_no, company_id, requester_id, pay_to, department, cost_center, 
                         request_date, voucher_type, expense_type, total_amount, description,
                         approver_level1, approver_level2, approver_level3,
                         status_level1, status_level2, status_level3, current_approval_level, 
                         overall_status, remarks, created_at, updated_at) 
                        VALUES 
                        (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 
                         'Pending', 'Pending', 'Pending', 1, 'Pending', ?, NOW(), NOW())
                    ";
                    
                    $remarks = "คำขอเบิกจ่าย" . ($expense_types[$expense_type] ?? 'อื่นๆ') . " จำนวน " . number_format($total_amount, 2) . " บาท";
                    
                    $params_pdo = [
                        $voucher_no, $company_id, $requester_id, $pay_to, $department, $cost_center,
                        $request_date, $voucher_type, $expense_type, $total_amount, $description,
                        $approver_level1, $approver_level2, $approver_level3, $remarks
                    ];
                    
                    // Debug: แสดงข้อมูลที่จะบันทึก
                    error_log("Debug PDO params: " . print_r($params_pdo, true));
                    
                    $stmt = $conn->prepare($sql_insert_request);
                    $result_request = $stmt->execute($params_pdo);
                    
                    if (!$result_request) {
                        $errorInfo = $stmt->errorInfo();
                        error_log("PDO Error: " . print_r($errorInfo, true));
                        throw new Exception("ไม่สามารถบันทึกข้อมูลคำขอได้: " . $errorInfo[2]);
                    }
                    
                    // ดึง ID ของคำขอที่เพิ่งสร้าง - PDO Version
                    $expense_request_id = null;
                    
                    // Method 1: ใช้ lastInsertId()
                    $expense_request_id = $conn->lastInsertId();
                    
                    if (!empty($expense_request_id)) {
                        error_log("DEBUG: PDO Got expense_request_id from lastInsertId = " . $expense_request_id);
                    } else {
                        error_log("PDO lastInsertId() returned empty, trying alternative methods");
                        
                        // Method 2: หาจาก voucher_no
                        $sql_find_by_voucher = "SELECT id FROM ExpenseRequests WHERE voucher_no = ? ORDER BY created_at DESC LIMIT 1";
                        $stmt_find = $conn->prepare($sql_find_by_voucher);
                        $stmt_find->execute([$voucher_no]);
                        $row_find = $stmt_find->fetch(PDO::FETCH_ASSOC);
                        
                        if ($row_find && !empty($row_find['id'])) {
                            $expense_request_id = $row_find['id'];
                            error_log("DEBUG: PDO Got expense_request_id from voucher_no = " . $expense_request_id);
                        } else {
                            // Method 3: หาจาก pay_to และ total_amount
                            $sql_find_by_details = "
                                SELECT id 
                                FROM ExpenseRequests 
                                WHERE pay_to = ? AND total_amount = ? AND requester_id = ?
                                ORDER BY created_at DESC LIMIT 1
                            ";
                            $stmt_details = $conn->prepare($sql_find_by_details);
                            $stmt_details->execute([$pay_to, $total_amount, $requester_id]);
                            $row_details = $stmt_details->fetch(PDO::FETCH_ASSOC);
                            
                            if ($row_details && !empty($row_details['id'])) {
                                $expense_request_id = $row_details['id'];
                                error_log("DEBUG: PDO Got expense_request_id from details search = " . $expense_request_id);
                            }
                        }
                    }
                    
                    if (empty($expense_request_id)) {
                        $errorInfo = $stmt->errorInfo();
                        error_log("CRITICAL: PDO All methods failed to get expense_request_id");
                        error_log("PDO Error Info: " . print_r($errorInfo, true));
                        error_log("Voucher No: " . $voucher_no . ", Pay To: " . $pay_to . ", Amount: " . $total_amount);
                        throw new Exception("ไม่สามารถดึง ID ของคำขอที่เพิ่งสร้างได้ (PDO) กรุณาลองใหม่อีกครั้ง");
                    }
                    
                    error_log("SUCCESS: PDO Final expense_request_id = " . $expense_request_id);
                    
                    // บันทึกรายการค่าใช้จ่ายแต่ละรายการ
                    $sql_insert_item = "
                        INSERT INTO ExpenseItems 
                        (expense_request_id, item_description, amount, notes, created_at) 
                        VALUES (?, ?, ?, ?, NOW())
                    ";
                    
                    $stmt_item = $conn->prepare($sql_insert_item);
                    
                    // ตรวจสอบว่ามีรายการค่าใช้จ่ายหรือไม่
                    if (empty($expense_items) || !is_array($expense_items)) {
                        throw new Exception("ไม่พบรายการค่าใช้จ่าย กรุณาระบุรายการอย่างน้อย 1 รายการ");
                    }
                    
                    // ตรวจสอบว่า expense_request_id ไม่เป็น null
                    if (empty($expense_request_id)) {
                        error_log("CRITICAL: PDO expense_request_id is empty before saving items");
                        throw new Exception("ไม่สามารถดึง ID ของคำขอเบิกจ่ายได้ (PDO) กรุณาลองใหม่อีกครั้ง");
                    }
                    
                    error_log("DEBUG: PDO About to save expense items with expense_request_id = " . $expense_request_id);
                    
                    $items_saved = 0;
                    for ($i = 0; $i < count($expense_items); $i++) {
                        if (!empty($expense_items[$i]) && isset($expense_amounts[$i]) && $expense_amounts[$i] > 0) {
                            
                            // ตรวจสอบค่าที่จะบันทึก
                            $item_desc = trim($expense_items[$i]);
                            $item_amount = floatval($expense_amounts[$i]);
                            $item_note = isset($expense_notes[$i]) ? trim($expense_notes[$i]) : '';
                            
                            if (empty($item_desc)) {
                                continue; // ข้ามรายการที่ไม่มีชื่อ
                            }
                            
                            error_log("Saving expense item: $item_desc, Amount: $item_amount, Note: $item_note");
                            
                            $result_item = $stmt_item->execute([
                                $expense_request_id,
                                $item_desc,
                                $item_amount,
                                $item_note
                            ]);
                            
                            if (!$result_item) {
                                $errorInfo = $stmt_item->errorInfo();
                                error_log("PDO Item Error: " . print_r($errorInfo, true));
                                throw new Exception("ไม่สามารถบันทึกรายการค่าใช้จ่าย '$item_desc' ได้: " . $errorInfo[2]);
                            }
                            
                            $items_saved++;
                        }
                    }
                    
                    if ($items_saved === 0) {
                        throw new Exception("ไม่มีรายการค่าใช้จ่ายที่ถูกต้องสำหรับบันทึก กรุณาตรวจสอบข้อมูล");
                    }
                    
                    // Commit Transaction
                    $conn->commit();
                    
                } else if (strpos($conn_type, 'mysqli') !== false) {
                    // MySQLi Connection
                    $conn->autocommit(FALSE);
                    
                    // สร้างเลขที่ใบเบิกถ้าไม่มี
                    if (empty($voucher_no)) {
                        $voucher_no = 'PCV-' . date('Ymd') . '-' . sprintf('%04d', rand(1, 9999));
                    }
                    
                    // แปลง approver เป็น null ถ้าเป็น 0 หรือ empty
                    $approver_level1 = (empty($approver_level1) || $approver_level1 == '0') ? null : $approver_level1;
                    $approver_level2 = (empty($approver_level2) || $approver_level2 == '0') ? null : $approver_level2;
                    $approver_level3 = (empty($approver_level3) || $approver_level3 == '0') ? null : $approver_level3;
                    
                    // บันทึกข้อมูลหลักของคำขอเบิกจ่าย (สำหรับ MySQLi)
                    $sql_insert_request = "
                        INSERT INTO ExpenseRequests 
                        (voucher_no, company_id, requester_id, pay_to, department, cost_center, 
                         request_date, voucher_type, expense_type, total_amount, description,
                         approver_level1, approver_level2, approver_level3,
                         status_level1, status_level2, status_level3, current_approval_level, 
                         overall_status, remarks, created_at, updated_at) 
                        VALUES 
                        (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 
                         'Pending', 'Pending', 'Pending', 1, 'Pending', ?, NOW(), NOW())
                    ";
                    
                    $remarks = "คำขอเบิกจ่าย" . ($expense_types[$expense_type] ?? 'อื่นๆ') . " จำนวน " . number_format($total_amount, 2) . " บาท";
                    
                    // Debug: แสดงข้อมูลที่จะบันทึก
                    error_log("Debug MySQLi data: voucher_no=$voucher_no, company_id=$company_id, requester_id=$requester_id");
                    
                    $stmt = $conn->prepare($sql_insert_request);
                    if (!$stmt) {
                        throw new Exception("ไม่สามารถเตรียม SQL statement ได้: " . $conn->error);
                    }
                    
                    $stmt->bind_param("sissssssdsiiis", 
                        $voucher_no, $company_id, $requester_id, $pay_to, $department, $cost_center,
                        $request_date, $voucher_type, $expense_type, $total_amount, $description,
                        $approver_level1, $approver_level2, $approver_level3, $remarks
                    );
                    
                    $result_request = $stmt->execute();
                    
                    if (!$result_request) {
                        throw new Exception("ไม่สามารถบันทึกข้อมูลคำขอได้");
                    }
                    
                    // ดึง ID ของคำขอที่เพิ่งสร้าง - MySQLi Version
                    $expense_request_id = null;
                    
                    // Method 1: ใช้ insert_id
                    $expense_request_id = $conn->insert_id;
                    
                    if (!empty($expense_request_id)) {
                        error_log("DEBUG: MySQLi Got expense_request_id from insert_id = " . $expense_request_id);
                    } else {
                        error_log("MySQLi insert_id returned empty, trying alternative methods");
                        
                        // Method 2: หาจาก voucher_no
                        $sql_find_by_voucher = "SELECT id FROM ExpenseRequests WHERE voucher_no = ? ORDER BY created_at DESC LIMIT 1";
                        $stmt_find = $conn->prepare($sql_find_by_voucher);
                        $stmt_find->bind_param('s', $voucher_no);
                        $stmt_find->execute();
                        $result_find = $stmt_find->get_result();
                        $row_find = $result_find->fetch_assoc();
                        
                        if ($row_find && !empty($row_find['id'])) {
                            $expense_request_id = $row_find['id'];
                            error_log("DEBUG: MySQLi Got expense_request_id from voucher_no = " . $expense_request_id);
                        } else {
                            // Method 3: หาจาก pay_to และ total_amount
                            $sql_find_by_details = "
                                SELECT id 
                                FROM ExpenseRequests 
                                WHERE pay_to = ? AND total_amount = ? AND requester_id = ?
                                ORDER BY created_at DESC LIMIT 1
                            ";
                            $stmt_details = $conn->prepare($sql_find_by_details);
                            $stmt_details->bind_param('sds', $pay_to, $total_amount, $requester_id);
                            $stmt_details->execute();
                            $result_details = $stmt_details->get_result();
                            $row_details = $result_details->fetch_assoc();
                            
                            if ($row_details && !empty($row_details['id'])) {
                                $expense_request_id = $row_details['id'];
                                error_log("DEBUG: MySQLi Got expense_request_id from details search = " . $expense_request_id);
                            }
                        }
                    }
                    
                    if (empty($expense_request_id)) {
                        error_log("CRITICAL: MySQLi All methods failed to get expense_request_id");
                        error_log("MySQLi Error: " . $conn->error);
                        error_log("Voucher No: " . $voucher_no . ", Pay To: " . $pay_to . ", Amount: " . $total_amount);
                        throw new Exception("ไม่สามารถดึง ID ของคำขอที่เพิ่งสร้างได้ (MySQLi) กรุณาลองใหม่อีกครั้ง");
                    }
                    
                    error_log("SUCCESS: MySQLi Final expense_request_id = " . $expense_request_id);
                    
                    // บันทึกรายการค่าใช้จ่ายแต่ละรายการ
                    $sql_insert_item = "
                        INSERT INTO ExpenseItems 
                        (expense_request_id, item_description, amount, notes, created_at) 
                        VALUES (?, ?, ?, ?, NOW())
                    ";
                    
                    $stmt_item = $conn->prepare($sql_insert_item);
                    if (!$stmt_item) {
                        throw new Exception("ไม่สามารถเตรียม SQL statement สำหรับรายการค่าใช้จ่ายได้: " . $conn->error);
                    }
                    
                    // ตรวจสอบว่ามีรายการค่าใช้จ่ายหรือไม่
                    if (empty($expense_items) || !is_array($expense_items)) {
                        throw new Exception("ไม่พบรายการค่าใช้จ่าย กรุณาระบุรายการอย่างน้อย 1 รายการ");
                    }
                    
                    // ตรวจสอบว่า expense_request_id ไม่เป็น null
                    if (empty($expense_request_id)) {
                        error_log("CRITICAL: MySQLi expense_request_id is empty before saving items");
                        throw new Exception("ไม่สามารถดึง ID ของคำขอเบิกจ่ายได้ (MySQLi) กรุณาลองใหม่อีกครั้ง");
                    }
                    
                    error_log("DEBUG: MySQLi About to save expense items with expense_request_id = " . $expense_request_id);
                    
                    $items_saved = 0;
                    for ($i = 0; $i < count($expense_items); $i++) {
                        if (!empty($expense_items[$i]) && isset($expense_amounts[$i]) && $expense_amounts[$i] > 0) {
                            
                            // ตรวจสอบค่าที่จะบันทึก
                            $item_desc = trim($expense_items[$i]);
                            $item_amount = floatval($expense_amounts[$i]);
                            $item_note = isset($expense_notes[$i]) ? trim($expense_notes[$i]) : '';
                            
                            if (empty($item_desc)) {
                                continue; // ข้ามรายการที่ไม่มีชื่อ
                            }
                            
                            error_log("Saving MySQLi expense item: $item_desc, Amount: $item_amount, Note: $item_note");
                            
                            $stmt_item->bind_param("isds", 
                                $expense_request_id,
                                $item_desc,
                                $item_amount,
                                $item_note
                            );
                            
                            $result_item = $stmt_item->execute();
                            
                            if (!$result_item) {
                                throw new Exception("ไม่สามารถบันทึกรายการค่าใช้จ่าย '$item_desc' ได้: " . $stmt_item->error);
                            }
                            
                            $items_saved++;
                        }
                    }
                    
                    if ($items_saved === 0) {
                        throw new Exception("ไม่มีรายการค่าใช้จ่ายที่ถูกต้องสำหรับบันทึก กรุณาตรวจสอบข้อมูล");
                    }
                    
                    // Commit Transaction
                    $conn->commit();
                    $conn->autocommit(TRUE);
                }
            }
            
            // ปิด else block สำหรับการเชื่อมต่อฐานข้อมูล
            }
            
            // หากยังไม่มี success_message แสดงว่าใช้ฐานข้อมูล
            if (!isset($success_message)) {
                $success_message = "สร้างคำขอเบิกจ่ายเรียบร้อยแล้ว! เลขที่ใบเบิก: " . $voucher_no;
                
                // ส่งอีเมลแจ้งผู้อนุมัติลำดับ 1
                if (!empty($approver_level1)) {
                    $requester_name = $_SESSION['employee_name'] ?? 
                                     (($user_first_name_th ?? '') . ' ' . ($user_last_name_th ?? '')) ?? 
                                     'ผู้ใช้งาน';
                    
                    $email_sent = sendApprovalNotificationEmail(
                        $conn, 
                        $expense_request_id, 
                        $approver_level1, 
                        $voucher_no, 
                        $requester_name, 
                        $total_amount, 
                        $expense_type
                    );
                    
                    if ($email_sent) {
                        $success_message .= " และส่งอีเมลแจ้งผู้อนุมัติแล้ว";
                    } else {
                        $success_message .= " (ไม่สามารถส่งอีเมลแจ้งผู้อนุมัติได้)";
                    }
                }
            }
            
        } catch (Exception $e) {
            // Log error สำหรับ debugging
            error_log("Database Error: " . $e->getMessage());
            
            // Rollback Transaction
            try {
                if (is_resource($conn)) {
                    sqlsrv_rollback($conn);
                    $sql_errors = sqlsrv_errors();
                    if ($sql_errors) {
                        error_log("SQLSRV Errors: " . print_r($sql_errors, true));
                        $error_message = "เกิดข้อผิดพลาดในการบันทึกข้อมูl: " . $e->getMessage();
                    } else {
                        $error_message = "เกิดข้อผิดพลาดในการบันทึกข้อมูล: " . $e->getMessage();
                    }
                } else if (is_object($conn)) {
                    $conn_type = get_class($conn);
                    if (strpos($conn_type, 'PDO') !== false) {
                        $conn->rollback();
                        $pdo_error = $conn->errorInfo();
                        error_log("PDO Error Info: " . print_r($pdo_error, true));
                    } else if (strpos($conn_type, 'mysqli') !== false) {
                        $conn->rollback();
                        $conn->autocommit(TRUE);
                        error_log("MySQLi Error: " . $conn->error);
                    }
                    $error_message = "เกิดข้อผิดพลาดในการบันทึกข้อมูล: " . $e->getMessage();
                }
            } catch (Exception $rollback_error) {
                error_log("Rollback Error: " . $rollback_error->getMessage());
                $error_message = "เกิดข้อผิดพลาดร้ายแรงในการบันทึกข้อมูล กรุณาติดต่อผู้ดูแลระบบ";
            }
        }
        
    } else {
        $error_message = "กรุณากรอกข้อมูลให้ครบถ้วน (ชื่อผู้รับ, ประเภทค่าใช้จ่าย, จำนวนเงิน และรายการค่าใช้จ่าย)";
    }
    
    } // ปิด if statement สำหรับการตรวจสอบเลขที่ใบเบิก
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>สร้างคำขอเบิกจ่าย - ฝ่ายบัญชี</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Prompt', sans-serif;
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
        }
        .form-container {
            max-width: 900px;
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
        .form-label {
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 0.5rem;
        }
        .form-control, .form-select {
            border-radius: 10px;
            border: 2px solid #e9ecef;
            padding: 12px 15px;
            transition: all 0.3s ease;
        }
        .form-control:focus, .form-select:focus {
            border-color: #27ae60;
            box-shadow: 0 0 0 0.2rem rgba(39, 174, 96, 0.25);
        }
        .btn-primary {
            background: linear-gradient(135deg, #27ae60 0%, #229954 100%);
            border: none;
            border-radius: 10px;
            padding: 12px 30px;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(39, 174, 96, 0.4);
        }
        .btn-secondary {
            border-radius: 10px;
            padding: 12px 30px;
            font-weight: 600;
        }
        .alert {
            border-radius: 10px;
            border: none;
            margin-bottom: 1.5rem;
        }
        .required {
            color: #e74c3c;
        }
        .expense-icon {
            font-size: 1.2rem;
            margin-right: 0.5rem;
        }
        .amount-input {
            font-size: 1.1rem;
            font-weight: 500;
        }
        .approver-section {
            background: #f8f9fa;
            border-radius: 15px;
            padding: 1.5rem;
            margin: 1rem 0;
        }
        .section-title {
            color: #27ae60;
            font-weight: 600;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
        }
        .section-title i {
            margin-right: 0.5rem;
        }
        .table th {
            background-color: #f8f9fa;
            border-color: #dee2e6;
            font-weight: 600;
        }
        .table td {
            vertical-align: middle;
        }
        .expense-row input {
            border: 1px solid #dee2e6;
            font-size: 0.9rem;
        }
        .table-responsive {
            border-radius: 10px;
            overflow: hidden;
            border: 1px solid #dee2e6;
        }
        .voucher-header {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border-radius: 15px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            border: 2px solid #dee2e6;
        }
        .company-info {
            border-left: 4px solid #27ae60;
            padding-left: 1rem;
        }
        .form-check-input:checked {
            background-color: #27ae60;
            border-color: #27ae60;
        }
        
        .approver-display {
            background-color: #f8f9fa;
            border: 2px solid #e9ecef;
            border-radius: 10px;
            font-weight: 500;
        }
        
        .approver-display:focus {
            border-color: #27ae60;
            box-shadow: 0 0 0 0.2rem rgba(39, 174, 96, 0.25);
            background-color: #fff;
        }
        
        .approval-structure-info {
            background: linear-gradient(135deg, #e8f5e8 0%, #f0f8f0 100%);
            border-left: 4px solid #27ae60;
            padding: 1rem;
            border-radius: 0 10px 10px 0;
            margin-bottom: 1rem;
        }
        
        /* PDF Print Styles */
        @media print {
            body {
                background: white !important;
                font-family: 'Prompt', sans-serif;
                font-size: 12px;
            }
            .form-container {
                max-width: none;
                margin: 0;
                padding: 0;
            }
            .voucher-header, .card {
                border: none !important;
                box-shadow: none !important;
                background: white !important;
            }
            .btn, .alert {
                display: none !important;
            }
            .card-header {
                background: white !important;
                color: black !important;
                border: none !important;
            }
            .table {
                border: 2px solid black !important;
            }
            .table th, .table td {
                border: 1px solid black !important;
                padding: 8px !important;
            }
            .expense-row input, .form-control, .form-select {
                border: none !important;
                background: transparent !important;
                font-weight: bold;
            }
            .company-info {
                border-left: none !important;
            }
            .section-title {
                color: black !important;
                font-weight: bold;
            }
            .page-break {
                page-break-before: always;
            }
        }
    </style>
</head>
<body>
    <div class="form-container">
        

        <!-- Success/Error Messages -->
        <?php if (isset($success_message)): ?>
            <div class="alert alert-success" role="alert">
                <i class="bi bi-check-circle me-2"></i><?php echo $success_message; ?>
                <div class="mt-2">
                    <a href="expense_approval.php" class="btn btn-success btn-sm me-2">
                        <i class="bi bi-eye"></i> ดูรายการคำขอ
                    </a>
                    <button type="button" class="btn btn-outline-success btn-sm" onclick="window.location.reload();">
                        <i class="bi bi-plus"></i> สร้างคำขอใหม่
                    </button>
                </div>
            </div>
        <?php endif; ?>

        <?php if (isset($error_message)): ?>
            <div class="alert alert-danger" role="alert">
                <i class="bi bi-exclamation-triangle me-2"></i><?php echo $error_message; ?>
            </div>
        <?php endif; ?>

        <!-- Main Form -->
        <div class="card">
            <div class="card-header">
                <h3 class="mb-0"><i class="bi bi-file-earmark-plus me-2"></i>แบบฟอร์มคำขอเบิกจ่าย</h3>
                <p class="mb-0 mt-2 opacity-75">กรุณากรอกข้อมูลให้ครบถ้วนและถูกต้อง</p>
            </div>
            <div class="card-body p-4">
                <form method="POST" action="">
                    <!-- ข้อมูลบริษัท/สาขา -->
                    <div class="mb-4">
                        <h5 class="section-title">
                            <i class="bi bi-building"></i>ข้อมูลบริษัท/สาขา
                        </h5>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="company_branch_form" class="form-label">
                                        เลือกบริษัท/สาขา <span class="required">*</span>
                                    </label>
                                    <select class="form-select" id="company_branch_form" name="company_branch_form" onchange="updateFormCompanyInfo()" required>
                                        <option value="">-- เลือกบริษัท/สาขา --</option>
                                        <option value="main">บริษัท เจอร์นัล คอร์ป จำกัด (สำนักงานใหญ่)</option>
                                        <option value="branch1">บริษัท เจอร์นัล คอร์ป จำกัด (สาขาที่ 1)</option>
                                        <option value="branch2">บริษัท เจอร์นัล คอร์ป จำกัด (สาขาที่ 2)</option>
                                        <option value="subsidiary1">บริษัท เจอร์นัล มีเดีย จำกัด</option>
                                        <option value="subsidiary2">บริษัท เจอร์นัล เทคโนโลยี จำกัด</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="tax_id_form" class="form-label">
                                        เลขประจำตัวผู้เสียภาษี
                                    </label>
                                    <input type="text" class="form-control" id="tax_id_form" name="tax_id_form" 
                                           placeholder="เลขประจำตัวผู้เสียภาษี" readonly>
                                </div>
                            </div>
                            <div class="col-md-12">
                                <div class="mb-3">
                                    <label for="company_address_form" class="form-label">
                                        ที่อยู่บริษัท
                                    </label>
                                    <textarea class="form-control" id="company_address_form" name="company_address_form" 
                                              rows="2" placeholder="ที่อยู่บริษัท/สาขา" readonly></textarea>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <!-- ข้อมูลพื้นฐาน -->
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="voucher_no" class="form-label">
                                    เลขที่ใบเบิกเงินสดย่อย (No.) <span class="required">*</span>
                                </label>
                                <div class="input-group">
                                    <input type="text" class="form-control" id="voucher_no" name="voucher_no" 
                                           placeholder="ระบุเลขที่ใบเบิก เช่น PCV-20250723-0001" required
                                           value="<?php echo isset($_POST['voucher_no']) ? htmlspecialchars($_POST['voucher_no']) : ''; ?>">
                                    <button type="button" class="btn btn-outline-secondary" id="generate-voucher-no" title="สร้างเลขที่อัตโนมัติ">
                                        <i class="bi bi-magic"></i>
                                    </button>
                                </div>
                                <div class="form-text">
                                    <small class="text-muted">
                                        <i class="bi bi-info-circle me-1"></i>
                                        กรอกเลขที่ใบเบิกตามต้องการ หรือคลิก <i class="bi bi-magic"></i> เพื่อสร้างอัตโนมัติ
                                    </small>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="voucher_type" class="form-label">
                                    ประเภทใบเบิก
                                </label>
                                <div class="d-flex gap-3 align-items-center">
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="voucher_type" id="advance" value="advance" checked>
                                        <label class="form-check-label" for="advance">เบิกเงิน</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="voucher_type" id="reimbursement" value="reimbursement">
                                        <label class="form-check-label" for="reimbursement">โอนเงิน</label>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-12">
                            <div class="mb-3">
                                <label for="request_title" class="form-label">
                                    จ่ายให้/Pay to <span class="required">*</span>
                                </label>
                                <input type="text" class="form-control" id="request_title" name="request_title" 
                                       placeholder="ระบุชื่อผู้รับเงิน/หน่วยงาน" required
                                       value="<?php echo isset($_POST['request_title']) ? htmlspecialchars($_POST['request_title']) : ''; ?>">
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="department" class="form-label">
                                    แผนก/ฝ่าย (Department)
                                </label>
                                <select class="form-select" id="department" name="department" onchange="updateApprovalStructuresByDepartment(); updateCostCenter();" disabled>
                                    <option value="">-- เลือกแผนก/ฝ่าย --</option>
                                    <?php foreach ($departments_list as $department): ?>
                                        <option value="<?php echo htmlspecialchars($department['DeptID']); ?>"
                                                <?php echo ($department['DeptID'] === ($_SESSION['division'] ?? '')) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($department['DeptName']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <!-- Hidden input เพื่อส่งค่าไปยัง server -->
                                <input type="hidden" id="department_hidden" name="department" value="<?php echo htmlspecialchars($_SESSION['division'] ?? ''); ?>">
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="cost_center" class="form-label">
                                    ศูนย์ต้นทุน (Cost)
                                </label>
                                <input type="text" class="form-control" id="cost_center_display" name="cost_center_display" 
                                       placeholder="ชื่อแผนก/ฝ่าย" readonly
                                       value="<?php 
                                       // หาชื่อแผนกจาก DeptID ใน session
                                       $current_dept_name = '';
                                       foreach ($departments_list as $dept) {
                                           if ($dept['DeptID'] === ($_SESSION['division'] ?? '')) {
                                               $current_dept_name = $dept['DeptName'];
                                               break;
                                           }
                                       }
                                       echo htmlspecialchars($current_dept_name);
                                       ?>">
                                <!-- Hidden input เพื่อส่งค่า DeptID ไปยัง server -->
                                <input type="hidden" id="cost_center" name="cost_center" value="<?php echo htmlspecialchars($_SESSION['division'] ?? ''); ?>">
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="request_date" class="form-label">
                                    วันที่ (Date) <span class="required">*</span>
                                </label>
                                <input type="date" class="form-control" id="request_date" name="request_date" 
                                       value="<?php echo isset($_POST['request_date']) ? $_POST['request_date'] : date('Y-m-d'); ?>" required>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="expense_type" class="form-label">
                                    ประเภทค่าใช้จ่าย <span class="required">*</span>
                                </label>
                                <select class="form-select" id="expense_type" name="expense_type" required>
                                    <option value="">-- เลือกประเภทค่าใช้จ่าย --</option>
                                    <?php foreach ($expense_types as $key => $value): ?>
                                        <option value="<?php echo $key; ?>" 
                                                <?php echo (isset($_POST['expense_type']) && $_POST['expense_type'] === $key) ? 'selected' : ''; ?>>
                                            <?php echo $value; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- ตารางรายการ -->
                    <div class="mb-4">
                        <h5 class="section-title">
                            <i class="bi bi-table"></i>รายการค่าใช้จ่าย
                        </h5>
                        <div class="table-responsive">
                            <table class="table table-bordered" id="expense-table">
                                <thead class="table-light">
                                    <tr>
                                        <th width="50%">รายการ</th>
                                        <th width="25%">จำนวนเงิน</th>
                                        <th width="15%">หมายเหตุ</th>
                                        <th width="10%">ลบ</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr class="expense-row">
                                        <td>
                                            <input type="text" class="form-control expense-description" name="expense_items[]" 
                                                   placeholder="ระบุรายการค่าใช้จ่าย" required>
                                        </td>
                                        <td>
                                            <input type="number" class="form-control expense-amount amount-input" name="expense_amounts[]" 
                                                   placeholder="0.00" step="0.01" min="0" required>
                                        </td>
                                        <td>
                                            <input type="text" class="form-control expense-note" name="expense_notes[]" 
                                                   placeholder="หมายเหตุ">
                                        </td>
                                        <td>
                                            <button type="button" class="btn btn-danger btn-sm remove-row" disabled>
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </td>
                                    </tr>
                                </tbody>
                                <tfoot>
                                    <tr>
                                        <td colspan="4">
                                            <button type="button" class="btn btn-outline-primary btn-sm" id="add-row">
                                                <i class="bi bi-plus"></i> เพิ่มรายการ
                                            </button>
                                        </td>
                                    </tr>
                                    <tr class="table-info">
                                        <td><strong>รวมเงินทั้งสิ้น</strong></td>
                                        <td>
                                            <input type="number" class="form-control amount-input" id="total_amount" name="total_amount" 
                                                   placeholder="0.00" step="0.01" min="0" readonly required>
                                        </td>
                                        <td colspan="2"></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>

                    <!-- รายละเอียดเพิ่มเติม -->
                    <div class="mb-4">
                        <label for="description" class="form-label">
                            รายละเอียดเพิ่มเติม/วัตถุประสงค์
                        </label>
                        <textarea class="form-control" id="description" name="description" rows="3" 
                                  placeholder="ระบุรายละเอียดเพิ่มเติม เช่น วัตถุประสงค์ สถานที่ ฯลฯ"><?php echo isset($_POST['description']) ? htmlspecialchars($_POST['description']) : ''; ?></textarea>
                    </div>

                    <!-- ส่วนผู้อนุมัติ -->
                    <div class="approver-section">
                        <div class="section-title">
                            <i class="bi bi-people"></i>โครงสร้างการอนุมัติสำหรับคุณ
                        </div>
                        
                        <?php if (empty($approval_structures)): ?>
                            <div class="alert alert-warning">
                                <i class="bi bi-exclamation-triangle me-2"></i>
                                ไม่พบโครงสร้างการอนุมัติสำหรับบัญชีของคุณ กรุณาติดต่อผู้ดูแลระบบ
                            </div>
                        <?php else: ?>
                            <!-- แสดงข้อมูลโครงสร้างการอนุมัติโดยอัตโนมัติ -->
                            <?php foreach ($approval_structures as $structure): ?>
                                <div class="approval-structure-info mb-4">
                                    <div class="d-flex justify-content-between align-items-center mb-3">
                                        <h6 class="mb-0">โครงสร้างการอนุมัติ: <?php echo htmlspecialchars($structure['ApprovalName']); ?></h6>
                                        <small class="text-muted">รหัส: <?php echo htmlspecialchars($structure['ApprovalID']); ?></small>
                                    </div>
                                    
                                    <!-- ส่งข้อมูลผู้อนุมัติแต่ละระดับเป็น hidden fields -->
                                    <input type="hidden" name="approval_structure" value="<?php echo htmlspecialchars($structure['ApprovalID']); ?>">
                                    <input type="hidden" name="approver_level1" value="<?php echo htmlspecialchars($structure['ApprovalLevel1'] ?? ''); ?>">
                                    <input type="hidden" name="approver_level2" value="<?php echo htmlspecialchars($structure['ApprovalLevel2'] ?? ''); ?>">
                                    <input type="hidden" name="approver_level3" value="<?php echo htmlspecialchars($structure['ApprovalLevel3'] ?? ''); ?>">
                                    
                                    <!-- พื้นที่แสดงผู้อนุมัติที่เหมาะสม (จะถูกอัปเดตด้วย JavaScript) -->
                                    <div id="dynamic-approver-display" class="text-center text-muted">
                                        <p><i class="bi bi-info-circle me-2"></i>กรุณากรอกจำนวนเงิน "รวมเงินทั้งสิ้น" เพื่อแสดงผู้อนุมัติที่เหมาะสม</p>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>

                    <!-- ปุ่มส่ง -->
                    <div class="d-flex justify-content-between">
                        <a href="accounting_dashboard.php" class="btn btn-secondary">
                            <i class="bi bi-arrow-left me-2"></i>กลับหน้าหลัก
                        </a>
                        <div class="d-flex gap-2">
                            <button type="button" class="btn btn-info" onclick="printToPDF()">
                                <i class="bi bi-printer me-2"></i>พิมพ์ PDF
                            </button>
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-send me-2"></i>ส่งคำขอเบิกจ่าย
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // ข้อมูลโครงสร้างการอนุมัติ (กรองแล้วตาม approver_id ของผู้ใช้)
        const approvalStructures = <?php echo json_encode($approval_structures, JSON_UNESCAPED_UNICODE); ?>;
        const currentUserApproverId = <?php echo json_encode($current_user_approver_id, JSON_UNESCAPED_UNICODE); ?>;
        
        // DEBUG: แสดงข้อมูลที่ได้รับจาก PHP
        console.log('🔍 DEBUG: approvalStructures from PHP:', approvalStructures);
        console.log('🔍 DEBUG: currentUserApproverId:', currentUserApproverId);
        if (approvalStructures && approvalStructures.length > 0) {
            console.log('📊 DEBUG: First structure data:', approvalStructures[0]);
            console.log('🏢 DEBUG: ApprovalName:', approvalStructures[0].ApprovalName);
            console.log('💰 DEBUG: Amount limits:', {
                level1: approvalStructures[0].level1_amount_limit,
                level2: approvalStructures[0].level2_amount_limit,
                level3: approvalStructures[0].level3_amount_limit
            });
            console.log('🔢 DEBUG: Amount limits types:', {
                level1: typeof approvalStructures[0].level1_amount_limit,
                level2: typeof approvalStructures[0].level2_amount_limit,
                level3: typeof approvalStructures[0].level3_amount_limit
            });
        } else {
            console.log('❌ DEBUG: No approval structures found or empty array');
        }
        
        // ฟังก์ชันตรวจสอบผู้อนุมัติตามวงเงินที่กำหนด
        function checkApproverByAmount(totalAmount) {
            if (!approvalStructures || approvalStructures.length === 0) {
                console.log('❌ ไม่พบโครงสร้างการอนุมัติ');
                return null;
            }
            
            const structure = approvalStructures[0]; // ใช้โครงสร้างแรก
            const amount = parseFloat(totalAmount) || 0;
            
            console.log('🔍 ตรวจสอบผู้อนุมัติสำหรับยอดเงิน:', amount);
            console.log('📋 โครงสร้างการอนุมัติ:', structure);
            console.log('🔍 Debug วงเงิน - level1_amount_limit:', structure.level1_amount_limit, 'type:', typeof structure.level1_amount_limit);
            console.log('🔍 Debug วงเงิน - level2_amount_limit:', structure.level2_amount_limit, 'type:', typeof structure.level2_amount_limit);
            console.log('🔍 Debug วงเงิน - level3_amount_limit:', structure.level3_amount_limit, 'type:', typeof structure.level3_amount_limit);
            
            // รวบรวมข้อมูลผู้อนุมัติทั้งหมด
            const approvers = [];
            
            if (structure.ApprovalLevel1) {
                // ใช้ค่าจาก level1_amount_limit โดยตรง
                let level1Limit = 0;
                if (structure.level1_amount_limit !== null && structure.level1_amount_limit !== undefined) {
                    level1Limit = parseFloat(structure.level1_amount_limit);
                    console.log('💰 Level 1 - Using database value:', level1Limit);
                }
                
                const level1Data = {
                    level: 1,
                    name: `${structure.level1_first} ${structure.level1_last}`,
                    position: structure.level1_position || '',
                    limit: level1Limit
                };
                approvers.push(level1Data);
                console.log('👤 ระดับ 1:', level1Data);
            }
            
            if (structure.ApprovalLevel2) {
                // ใช้ค่าจาก level2_amount_limit โดยตรง
                let level2Limit = 0;
                if (structure.level2_amount_limit !== null && structure.level2_amount_limit !== undefined) {
                    level2Limit = parseFloat(structure.level2_amount_limit);
                    console.log('💰 Level 2 - Using database value:', level2Limit);
                }
                
                const level2Data = {
                    level: 2,
                    name: `${structure.level2_first} ${structure.level2_last}`,
                    position: structure.level2_position || '',
                    limit: level2Limit
                };
                approvers.push(level2Data);
                console.log('👤 ระดับ 2:', level2Data);
            }
            
            if (structure.ApprovalLevel3) {
                // ใช้ค่าจาก level3_amount_limit โดยตรง
                let level3Limit = 0;
                if (structure.level3_amount_limit !== null && structure.level3_amount_limit !== undefined) {
                    level3Limit = parseFloat(structure.level3_amount_limit);
                    console.log('💰 Level 3 - Using database value:', level3Limit);
                }
                
                const level3Data = {
                    level: 3,
                    name: `${structure.level3_first} ${structure.level3_last}`,
                    position: structure.level3_position || '',
                    limit: level3Limit
                };
                approvers.push(level3Data);
                console.log('👤 ระดับ 3:', level3Data);
            }
            
            if (approvers.length === 0) {
                console.log('❌ ไม่พบผู้อนุมัติในโครงสร้าง');
                return null;
            }
            
            console.log('📊 ผู้อนุมัติทั้งหมดก่อนจัดเรียง:', approvers);
            
            // จัดเรียงตามระดับ (level) จากน้อยไปมาก
            approvers.sort((a, b) => a.level - b.level);
            console.log('📊 ผู้อนุมัติหลังจัดเรียงตามระดับ:', approvers);
            
            // หาผู้อนุมัติที่เหมาะสมตามวงเงิน
            const requiredApprovers = [];
            let approvalType = '';
            
            // ตรวจสอบการอนุมัติตามวงเงินแต่ละระดับ
            for (let i = 0; i < approvers.length; i++) {
                const approver = approvers[i];
                
                // ถ้าวงเงินของระดับนี้มากกว่าหรือเท่ากับยอดเงินที่ขอ
                if (approver.limit > 0 && amount <= approver.limit) {
                    requiredApprovers.push(approver);
                    approvalType = `level${approver.level}_sufficient`;
                    console.log(`✅ ยอดเงิน ${amount} <= วงเงินระดับ ${approver.level} (${approver.limit}) - ใช้ระดับ ${approver.level}`);
                    break; // พอแค่ระดับนี้
                } else if (approver.limit > 0) {
                    // ถ้าวงเงินไม่พอ ต้องไประดับที่สูงกว่า
                    console.log(`⚠️ ยอดเงิน ${amount} > วงเงินระดับ ${approver.level} (${approver.limit}) - ต้องใช้ระดับที่สูงกว่า`);
                }
            }
            
            // ถ้าไม่มีระดับไหนที่วงเงินพอ ให้ใช้ระดับสูงสุด
            if (requiredApprovers.length === 0) {
                // หาระดับที่มีวงเงินสูงสุด
                const highestLimitApprover = approvers.reduce((max, current) => {
                    return (current.limit > max.limit) ? current : max;
                }, approvers[0]);
                
                if (highestLimitApprover && highestLimitApprover.limit > 0) {
                    requiredApprovers.push(highestLimitApprover);
                    approvalType = `level${highestLimitApprover.level}_highest`;
                    console.log(`🔝 ใช้ระดับสูงสุด: Level ${highestLimitApprover.level} (วงเงิน: ${highestLimitApprover.limit})`);
                } else {
                    // ถ้าไม่มีใครมีวงเงินกำหนด ให้ใช้ระดับสูงสุดที่มี
                    const lastApprover = approvers[approvers.length - 1];
                    requiredApprovers.push(lastApprover);
                    approvalType = `level${lastApprover.level}_default`;
                    console.log(`📋 ใช้ระดับสูงสุดที่มี: Level ${lastApprover.level} (ไม่มีวงเงินกำหนด)`);
                }
            }
            
            if (requiredApprovers.length === 0) {
                console.log('❌ ไม่พบผู้อนุมัติที่เหมาะสม');
                return {
                    type: 'error',
                    message: 'ไม่พบผู้อนุมัติที่เหมาะสมในระบบ',
                    amount: amount
                };
            }
            
            const result = {
                type: approvalType,
                amount: amount,
                approvers: requiredApprovers,
                totalApprovers: requiredApprovers.length,
                allApprovers: approvers // เก็บข้อมูลผู้อนุมัติทั้งหมดไว้เผื่อต้องการแสดง
            };
            
            console.log('🎯 ผลลัพธ์การอนุมัติตามวงเงิน:', result);
            return result;
        }
        
        // ฟังก์ชันแสดงผู้อนุมัติทุกลำดับขั้น
        function displayRecommendedApprover() {
            // คำนวณยอดรวมโดยไม่เรียก displayRecommendedApprover() อีกครั้ง
            const totalAmount = getTotalAmount();
            console.log('💰 ยอดเงินรวม:', totalAmount);
            
            const approvalResult = checkApproverByAmount(totalAmount);
            console.log('👤 ผลการอนุมัติ:', approvalResult);
            
            const displayDiv = document.getElementById('dynamic-approver-display');
            if (!displayDiv) {
                console.log('❌ ไม่พบ element #dynamic-approver-display');
                return;
            }
            
            // ฟังก์ชันสำหรับสร้างการแสดงลำดับผู้อนุมัติถัดไป
            function createNextApproversDisplay(allApprovers, currentApprover) {
                if (!allApprovers || allApprovers.length <= 1) return '';
                
                const nextApprovers = allApprovers.filter(approver => 
                    approver.level > currentApprover.level
                ).sort((a, b) => a.level - b.level);
                
                if (nextApprovers.length === 0) return '';
                
                let nextApproversHTML = `
                    <div class="mt-3 p-3 bg-light rounded border">
                        <h6 class="text-muted mb-2">
                            <i class="bi bi-arrow-right-circle me-2"></i>ลำดับผู้อนุมัติถัดไป
                        </h6>
                `;
                
                nextApprovers.forEach((approver, index) => {
                    const limitDisplay = approver.limit > 0 ? 
                        `<small class="text-muted">วงเงิน: ${approver.limit.toLocaleString()} บาท</small>` :
                        `<small class="text-muted">ไม่มีวงเงินกำหนด</small>`;
                    
                    nextApproversHTML += `
                        <div class="d-flex align-items-center p-2 border rounded mb-2 bg-white">
                            <div class="me-3">
                                <span class="badge bg-secondary rounded-pill">Level ${approver.level}</span>
                            </div>
                            <div class="flex-grow-1">
                                <strong class="text-secondary">${approver.name}</strong>
                                ${approver.position ? `<br><small class="text-muted">${approver.position}</small>` : ''}
                            </div>
                            <div class="text-end">
                                ${limitDisplay}
                            </div>
                        </div>
                    `;
                });
                
                nextApproversHTML += `</div>`;
                return nextApproversHTML;
            }
            
            if (approvalResult && totalAmount > 0) {
                // ตรวจสอบประเภทการอนุมัติ
                if (approvalResult.type.startsWith('level') && approvalResult.type.includes('_sufficient')) {
                    // แสดงผู้อนุมัติที่เพียงพอตามวงเงิน
                    const approver = approvalResult.approvers[0];
                    const levelNumber = approver.level;
                    const nextApproversHTML = createNextApproversDisplay(approvalResult.allApprovers, approver);
                    
                    displayDiv.innerHTML = `
                        <div class="alert alert-success">
                            <h6 class="mb-3">
                                <i class="bi bi-person-check me-2"></i>ผู้อนุมัติสำหรับยอดเงิน ${totalAmount.toLocaleString()} บาท
                                <span class="badge bg-success ms-2">Level ${levelNumber} เพียงพอ</span>
                            </h6>
                            <div class="d-flex align-items-center p-3 border rounded bg-success bg-opacity-10">
                                <div class="me-3">
                                    <span class="badge bg-success rounded-pill fs-6">Level ${approver.level}</span>
                                </div>
                                <div class="flex-grow-1">
                                    <strong class="text-success fs-6">${approver.name}</strong>
                                    ${approver.position ? `<br><small class="text-muted">${approver.position}</small>` : ''}
                                </div>
                                <div class="text-end">
                                    <small class="text-muted">วงเงินอนุมัติ:</small><br>
                                    <strong class="text-success">${approver.limit.toLocaleString()} บาท</strong>
                                </div>
                            </div>
                            <div class="mt-3 p-2 bg-light rounded">
                                <small class="text-muted">
                                    <i class="bi bi-info-circle me-1"></i>
                                    <strong>เหตุผล:</strong> ยอดเงิน ${totalAmount.toLocaleString()} บาท ≤ วงเงิน Level ${levelNumber} (${approver.limit.toLocaleString()} บาท)
                                </small>
                            </div>
                            ${nextApproversHTML}
                        </div>
                    `;
                    console.log(`✅ แสดงผู้อนุมัติ Level ${levelNumber} เพียงพอ พร้อมลำดับถัดไป`);
                    
                } else if (approvalResult.type.includes('_highest')) {
                    // แสดงผู้อนุมัติระดับสูงสุด (เมื่อยอดเงินเกิน)
                    const approver = approvalResult.approvers[0];
                    const levelNumber = approver.level;
                    const nextApproversHTML = createNextApproversDisplay(approvalResult.allApprovers, approver);
                    
                    displayDiv.innerHTML = `
                        <div class="alert alert-warning">
                            <h6 class="mb-3">
                                <i class="bi bi-exclamation-triangle me-2"></i>ผู้อนุมัติสำหรับยอดเงิน ${totalAmount.toLocaleString()} บาท
                                <span class="badge bg-warning ms-2">ใช้ระดับสูงสุด</span>
                            </h6>
                            <div class="d-flex align-items-center p-3 border rounded bg-warning bg-opacity-10">
                                <div class="me-3">
                                    <span class="badge bg-warning rounded-pill fs-6">Level ${approver.level}</span>
                                </div>
                                <div class="flex-grow-1">
                                    <strong class="text-warning fs-6">${approver.name}</strong>
                                    ${approver.position ? `<br><small class="text-muted">${approver.position}</small>` : ''}
                                </div>
                                <div class="text-end">
                                    <small class="text-muted">วงเงินอนุมัติ:</small><br>
                                    <strong class="text-warning">${approver.limit.toLocaleString()} บาท</strong>
                                </div>
                            </div>
                            <div class="mt-3 p-2 bg-light rounded">
                                <small class="text-muted">
                                    <i class="bi bi-exclamation-circle me-1"></i>
                                    <strong>หมายเหตุ:</strong> ยอดเงิน ${totalAmount.toLocaleString()} บาท > วงเงิน Level ${levelNumber} (${approver.limit.toLocaleString()} บาท)<br>
                                    <strong>ผลการอนุมัติ:</strong> ต้องใช้ผู้อนุมัติระดับสูงสุดในระบบ
                                </small>
                            </div>
                            ${nextApproversHTML}
                        </div>
                    `;
                    console.log(`⚠️ แสดงผู้อนุมัติระดับสูงสุด Level ${levelNumber} พร้อมลำดับถัดไป`);
                    
                } else if (approvalResult.type.includes('_default')) {
                    // แสดงผู้อนุมัติเริ่มต้น (ไม่มีวงเงินกำหนด)
                    const approver = approvalResult.approvers[0];
                    const levelNumber = approver.level;
                    const nextApproversHTML = createNextApproversDisplay(approvalResult.allApprovers, approver);
                    
                    displayDiv.innerHTML = `
                        <div class="alert alert-info">
                            <h6 class="mb-3">
                                <i class="bi bi-info-circle me-2"></i>ผู้อนุมัติสำหรับยอดเงิน ${totalAmount.toLocaleString()} บาท
                                <span class="badge bg-info ms-2">ระดับสูงสุดที่มี</span>
                            </h6>
                            <div class="d-flex align-items-center p-3 border rounded bg-info bg-opacity-10">
                                <div class="me-3">
                                    <span class="badge bg-info rounded-pill fs-6">Level ${approver.level}</span>
                                </div>
                                <div class="flex-grow-1">
                                    <strong class="text-info fs-6">${approver.name}</strong>
                                    ${approver.position ? `<br><small class="text-muted">${approver.position}</small>` : ''}
                                </div>
                                <div class="text-end">
                                    <small class="text-warning">ไม่มีวงเงินกำหนด</small>
                                </div>
                            </div>
                            <div class="mt-3 p-2 bg-light rounded">
                                <small class="text-muted">
                                    <i class="bi bi-info-circle me-1"></i>
                                    <strong>หมายเหตุ:</strong> ไม่พบการกำหนดวงเงินอนุมัติในระบบ<br>
                                    <strong>ผลการอนุมัติ:</strong> ใช้ผู้อนุมัติระดับสูงสุดที่มีในโครงสร้าง
                                </small>
                            </div>
                            ${nextApproversHTML}
                        </div>
                    `;
                    console.log(`ℹ️ แสดงผู้อนุมัติเริ่มต้น Level ${levelNumber} พร้อมลำดับถัดไป`);
                    
                } else if (approvalResult.type === 'error') {
                    // แสดงข้อผิดพลาด
                    displayDiv.innerHTML = `
                        <div class="alert alert-danger">
                            <h6 class="mb-2">
                                <i class="bi bi-exclamation-triangle me-2"></i>ไม่สามารถอนุมัติได้
                            </h6>
                            <p class="mb-2">${approvalResult.message}</p>
                            <div class="mt-2">
                                <small class="text-muted">
                                    <strong>ยอดเงินที่ร้องขอ:</strong> ${approvalResult.amount.toLocaleString()} บาท
                                </small>
                            </div>
                            <div class="mt-3 p-2 bg-light rounded">
                                <small class="text-muted">
                                    <i class="bi bi-lightbulb me-1"></i>
                                    <strong>คำแนะนำ:</strong> ติดต่อผู้ดูแลระบบเพื่อตรวจสอบโครงสร้างการอนุมัติ
                                </small>
                            </div>
                        </div>
                    `;
                    console.log('❌ แสดงข้อผิดพลาด:', approvalResult.message);
                }
                
            } else if (totalAmount === 0) {
                displayDiv.innerHTML = `
                    <div class="text-center text-muted py-3">
                        <i class="bi bi-info-circle me-2 fs-4"></i>
                        <p class="mb-0">กรุณากรอกจำนวนเงิน "รวมเงินทั้งสิ้น" เพื่อแสดงผู้อนุมัติที่เหมาะสมตามวงเงิน</p>
                    </div>
                `;
                console.log('⚠️ ยอดเงินเป็น 0 - ไม่แสดงผู้อนุมัติ');
                
            } else {
                displayDiv.innerHTML = `
                    <div class="alert alert-warning">
                        <i class="bi bi-exclamation-triangle me-2"></i>ไม่พบข้อมูลโครงสร้างการอนุมัติในระบบ
                    </div>
                `;
                console.log('❌ ไม่พบโครงสร้างการอนุมัติ');
            }
        }
        
        // ฟังก์ชันสำหรับคำนวณยอดรวมโดยไม่แสดงผู้อนุมัติ (ป้องกัน infinite loop)
        function getTotalAmount() {
            const amountInputs = document.querySelectorAll('.expense-amount');
            let total = 0;
            
            amountInputs.forEach(input => {
                const value = parseFloat(input.value) || 0;
                total += value;
            });
            
            const totalAmountInput = document.getElementById('total_amount');
            if (totalAmountInput) {
                // หากมีการกรอกใน total_amount โดยตรง ให้ใช้ค่านั้นแทน
                const manualTotal = parseFloat(totalAmountInput.value) || 0;
                if (manualTotal > 0 && manualTotal !== total) {
                    total = manualTotal;
                }
            }
            
            return total;
        }
        
        // ฟังก์ชันสำหรับอัปเดตข้อมูลบริษัท
        function updateFormCompanyInfo() {
            const companySelect = document.getElementById('company_branch_form');
            if (!companySelect) return;
            
            const selectedValue = companySelect.value;
            console.log('Selected company:', selectedValue);
        }
        
        // ฟังก์ชันสำหรับอัปเดต Cost Center ให้เท่ากับ Department
        function updateCostCenter() {
            const departmentSelect = document.getElementById('department');
            const costCenterDisplayInput = document.getElementById('cost_center_display');
            const costCenterInput = document.getElementById('cost_center');
            const departmentHidden = document.getElementById('department_hidden');
            
            if (departmentSelect && costCenterDisplayInput && costCenterInput && departmentHidden) {
                const selectedValue = departmentSelect.value;
                const selectedText = departmentSelect.selectedOptions[0]?.text || '';
                
                // อัปเดตช่องแสดงผลให้เป็นชื่อแผนก
                costCenterDisplayInput.value = selectedText;
                // อัปเดตช่อง hidden ให้เป็น DeptID
                costCenterInput.value = selectedValue;
                departmentHidden.value = selectedValue;
                
                console.log('🔄 Cost Center updated - Display:', selectedText, 'Value:', selectedValue);
            }
        }
        
        // เรียกใช้เมื่อหน้าเว็บโหลดเสร็จ
        document.addEventListener('DOMContentLoaded', function() {
            console.log('🚀 DOM Content Loaded - Setting up approval system');
            updateCostCenter(); // อัปเดต Cost Center เมื่อโหลดหน้าเว็บ
            
            // เพิ่ม Event Listeners
            setupTableEvents();
            setupFormValidation();
            setupCompanyInfoUpdater();
            setupVoucherNumberGenerator();
            
            // แสดงผู้อนุมัติที่แนะนำครั้งแรก
            displayRecommendedApprover();
        });

        // ฟังก์ชันสำหรับตั้งค่าปุ่มสร้างเลขที่ใบเบิก
        function setupVoucherNumberGenerator() {
            const generateBtn = document.getElementById('generate-voucher-no');
            const voucherInput = document.getElementById('voucher_no');
            
            if (generateBtn && voucherInput) {
                generateBtn.addEventListener('click', function() {
                    // สร้างเลขที่ใบเบิกแบบอัตโนมัติ
                    const now = new Date();
                    const year = now.getFullYear();
                    const month = String(now.getMonth() + 1).padStart(2, '0');
                    const day = String(now.getDate()).padStart(2, '0');
                    const hours = String(now.getHours()).padStart(2, '0');
                    const minutes = String(now.getMinutes()).padStart(2, '0');
                    const seconds = String(now.getSeconds()).padStart(2, '0');
                    
                    // รูปแบบ: PCV-YYYYMMDD-HHMMSS
                    const voucherNo = `PCV-${year}${month}${day}-${hours}${minutes}${seconds}`;
                    
                    voucherInput.value = voucherNo;
                    
                    // แสดงข้อความแจ้งเตือน
                    const originalPlaceholder = voucherInput.placeholder;
                    voucherInput.placeholder = 'สร้างเลขที่อัตโนมัติแล้ว!';
                    voucherInput.classList.add('border-success');
                    
                    setTimeout(() => {
                        voucherInput.placeholder = originalPlaceholder;
                        voucherInput.classList.remove('border-success');
                    }, 2000);
                    
                    console.log('Generated voucher number:', voucherNo);
                });
            }
        }

        // ฟังก์ชัน setupTableEvents
        function setupTableEvents() {
            // Event listeners สำหรับตารางจะถูกสร้างใน bindAmountEvents และ updateRemoveButtons
            bindAmountEvents();
            updateRemoveButtons();
        }

        // ฟังก์ชัน setupFormValidation  
        function setupFormValidation() {
            // Validate form before submit
            document.querySelector('form').addEventListener('submit', function(e) {
                const totalAmount = parseFloat(document.getElementById('total_amount').value) || 0;
                if (totalAmount <= 0) {
                    e.preventDefault();
                    alert('กรุณาระบุจำนวนเงินที่มากกว่า 0');
                    return false;
                }
                
                const expenseItems = document.querySelectorAll('.expense-description');
                let hasValidItem = false;
                expenseItems.forEach(item => {
                    if (item.value.trim()) {
                        hasValidItem = true;
                    }
                });
                
                if (!hasValidItem) {
                    e.preventDefault();
                    alert('กรุณาระบุรายการค่าใช้จ่ายอย่างน้อย 1 รายการ');
                    return false;
                }
            });
        }

        // ฟังก์ชัน setupCompanyInfoUpdater
        function setupCompanyInfoUpdater() {
            // ตรวจสอบว่ามี element หรือไม่ก่อนเพิ่ม event listener
            const companyBranchSelect = document.getElementById('company_branch');
            if (companyBranchSelect) {
                companyBranchSelect.addEventListener('change', updateCompanyInfo);
            }
        }

        // ตัวแปรสำหรับจัดการแถวในตาราง
        let rowCount = 1;

        // ฟังก์ชันสำหรับ setup events ของตาราง
        function setupTableEvents() {
            // ฟังก์ชันสำหรับเพิ่มแถวในตาราง
            document.getElementById('add-row').addEventListener('click', function() {
                rowCount++;
                const tbody = document.querySelector('#expense-table tbody');
                const newRow = document.createElement('tr');
                newRow.className = 'expense-row';
                newRow.innerHTML = `
                    <td>
                        <input type="text" class="form-control expense-description" name="expense_items[]" 
                               placeholder="ระบุรายการค่าใช้จ่าย" required>
                    </td>
                    <td>
                        <input type="number" class="form-control expense-amount amount-input" name="expense_amounts[]" 
                               placeholder="0.00" step="0.01" min="0" required>
                    </td>
                    <td>
                        <input type="text" class="form-control expense-note" name="expense_notes[]" 
                               placeholder="หมายเหตุ">
                    </td>
                    <td>
                        <button type="button" class="btn btn-danger btn-sm remove-row">
                            <i class="bi bi-trash"></i>
                        </button>
                    </td>
                `;
                tbody.appendChild(newRow);
                updateRemoveButtons();
                bindAmountEvents();
            });

            // เรียกใช้ครั้งแรก
            updateRemoveButtons();
            bindAmountEvents();
        }

        // ฟังก์ชันสำหรับลบแถว
        function updateRemoveButtons() {
            const removeButtons = document.querySelectorAll('.remove-row');
            const rows = document.querySelectorAll('.expense-row');
            
            removeButtons.forEach((btn, index) => {
                btn.disabled = rows.length <= 1;
                btn.onclick = function() {
                    if (rows.length > 1) {
                        this.closest('tr').remove();
                        updateRemoveButtons();
                        calculateTotal();
                    }
                };
            });
        }

        // ฟังก์ชันสำหรับคำนวณยอดรวม
        function calculateTotal() {
            const amountInputs = document.querySelectorAll('.expense-amount');
            let total = 0;
            
            amountInputs.forEach(input => {
                const value = parseFloat(input.value) || 0;
                total += value;
            });
            
            const totalAmountInput = document.getElementById('total_amount');
            if (totalAmountInput) {
                // อัปเดตช่อง "รวมเงินทั้งสิ้น" ให้เป็นผลรวมจากช่อง "จำนวนเงิน"
                totalAmountInput.value = total.toFixed(2);
            }
            
            // แสดงผู้อนุมัติที่แนะนำตามจำนวนเงิน (เรียกเฉพาะเมื่อคำนวณจากช่องต่างๆ)
            setTimeout(() => displayRecommendedApprover(), 0);
            
            return total;
        }

        // ฟังก์ชันสำหรับ bind events ของช่องจำนวนเงิน
        function bindAmountEvents() {
            const amountInputs = document.querySelectorAll('.expense-amount');
            amountInputs.forEach(input => {
                input.removeEventListener('input', calculateTotal);
                input.addEventListener('input', calculateTotal);
            });
            
            // เพิ่ม event listener สำหรับช่อง "รวมเงินทั้งสิ้น"
            const totalAmountInput = document.getElementById('total_amount');
            if (totalAmountInput) {
                totalAmountInput.removeEventListener('input', displayRecommendedApprover);
                totalAmountInput.addEventListener('input', displayRecommendedApprover);
            }
        }

        // ฟังก์ชันสำหรับ setup form validation
        function setupFormValidation() {
            // Dynamic title based on expense type
            const expenseTypeSelect = document.getElementById('expense_type');
            if (expenseTypeSelect) {
                expenseTypeSelect.addEventListener('change', function(e) {
                    const expenseTypes = {
                        'travel': 'ค่าเดินทาง',
                        'office_supplies': 'เครื่องเขียนและอุปกรณ์สำนักงาน',
                        'meals': 'ค่าอาหาร',
                        'accommodation': 'ค่าที่พัก',
                        'fuel': 'ค่าน้ำมันเชื้อเพลิง',
                        'communication': 'ค่าโทรศัพท์/อินเทอร์เน็ต',
                        'maintenance': 'ค่าซ่อมแซม',
                        'training': 'ค่าอบรม/สัมมนา',
                        'others': 'อื่นๆ'
                    };
                    
                    // เพิ่มรายการแรกในตารางโดยอัตโนมัติ
                    if (e.target.value && expenseTypes[e.target.value]) {
                        const firstDescInput = document.querySelector('.expense-description');
                        if (firstDescInput && !firstDescInput.value) {
                            firstDescInput.value = expenseTypes[e.target.value];
                        }
                    }
                });
            }

            // Format number with commas
            document.addEventListener('input', function(e) {
                if (e.target.classList.contains('amount-input')) {
                    let value = e.target.value;
                    // Remove any non-numeric characters except decimal point
                    value = value.replace(/[^0-9.]/g, '');
                    // Ensure only one decimal point
                    const parts = value.split('.');
                    if (parts.length > 2) {
                        value = parts[0] + '.' + parts.slice(1).join('');
                    }
                    e.target.value = value;
                }
            });

            // Validate form before submit
            const form = document.querySelector('form');
            if (form) {
                form.addEventListener('submit', function(e) {
                    const totalAmount = parseFloat(document.getElementById('total_amount').value) || 0;
                    if (totalAmount <= 0) {
                        e.preventDefault();
                        alert('กรุณาระบุจำนวนเงินที่มากกว่า 0');
                        return false;
                    }
                    
                    const expenseItems = document.querySelectorAll('.expense-description');
                    let hasValidItem = false;
                    expenseItems.forEach(item => {
                        if (item.value.trim()) {
                            hasValidItem = true;
                        }
                    });
                    
                    if (!hasValidItem) {
                        e.preventDefault();
                        alert('กรุณาระบุรายการค่าใช้จ่ายอย่างน้อย 1 รายการ');
                        return false;
                    }
                });
            }
        }

        // ฟังก์ชันสำหรับอัปเดตข้อมูลบริษัท
        function setupCompanyInfoUpdater() {
            // ข้อมูลบริษัท/สาขา
            const companyData = {
                'main': {
                    name: 'บริษัท เจอร์นัล คอร์ป จำกัด (สำนักงานใหญ่)',
                    taxId: '0105562088888',
                    address: '123 ถนนสุขุมวิท แขวงคลองตัน เขตคลองเตย กรุงเทพมหานคร 10110'
                },
                'branch1': {
                    name: 'บริษัท เจอร์นัล คอร์ป จำกัด (สาขาที่ 1)',
                    taxId: '0105562088888',
                    address: '456 ถนนรัชดาภิเษก แขวงลาดยาว เขตจตุจักร กรุงเทพมหานคร 10900'
                },
                'branch2': {
                    name: 'บริษัท เจอร์นัล คอร์ป จำกัด (สาขาที่ 2)',
                    taxId: '0105562088888',
                    address: '789 ถนนพหลโยธิน แขวงสามเสนใน เขตพญาไท กรุงเทพมหานคร 10400'
                },
                'subsidiary1': {
                    name: 'บริษัท เจอร์นัล มีเดีย จำกัด',
                    taxId: '0105562099999',
                    address: '321 ถนนเพชรบุรี แขวงมักกะสัน เขตราชเทวี กรุงเทพมหานคร 10400'
                },
                'subsidiary2': {
                    name: 'บริษัท เจอร์นัล เทคโนโลยี จำกัด',
                    taxId: '0105562077777',
                    address: '654 ถนนวิภาวดี แขวงลาดยาว เขตจตุจักร กรุงเทพมหานคร 10900'
                }
            };

            // ฟังก์ชันสำหรับอัปเดตข้อมูลบริษัท
            window.updateFormCompanyInfo = function() {
                const selectedBranch = document.getElementById('company_branch_form').value;
                const taxIdInput = document.getElementById('tax_id_form');
                const addressInput = document.getElementById('company_address_form');
                
                if (selectedBranch && companyData[selectedBranch]) {
                    const data = companyData[selectedBranch];
                    if (taxIdInput) taxIdInput.value = data.taxId;
                    if (addressInput) addressInput.value = data.address;
                } else {
                    if (taxIdInput) taxIdInput.value = '';
                    if (addressInput) addressInput.value = '';
                }
            };
        }

        // ฟังก์ชันสำหรับพิมพ์ PDF
        function printToPDF() {
            // ซ่อนปุ่มทั้งหมดก่อนพิมพ์
            const buttons = document.querySelectorAll('.btn');
            buttons.forEach(btn => btn.style.display = 'none');
            
            document.body.classList.add('print-mode');
            
            setTimeout(() => {
                window.print();
                
                // แสดงปุ่มกลับมา
                buttons.forEach(btn => btn.style.display = '');
                document.body.classList.remove('print-mode');
            }, 500);
        }

        // ฟังก์ชันสำหรับเพิ่มแถวในตาราง
        document.getElementById('add-row').addEventListener('click', function() {
            rowCount++;
            const tbody = document.querySelector('#expense-table tbody');
            const newRow = document.createElement('tr');
            newRow.className = 'expense-row';
            newRow.innerHTML = `
                <td>
                    <input type="text" class="form-control expense-description" name="expense_items[]" 
                           placeholder="ระบุรายการค่าใช้จ่าย" required>
                </td>
                <td>
                    <input type="number" class="form-control expense-amount amount-input" name="expense_amounts[]" 
                           placeholder="0.00" step="0.01" min="0" required>
                </td>
                <td>
                    <input type="text" class="form-control expense-note" name="expense_notes[]" 
                           placeholder="หมายเหตุ">
                </td>
                <td>
                    <button type="button" class="btn btn-danger btn-sm remove-row">
                        <i class="bi bi-trash"></i>
                    </button>
                </td>
            `;
            tbody.appendChild(newRow);
            updateRemoveButtons();
            bindAmountEvents();
        });

        // ฟังก์ชันสำหรับลบแถว
        function updateRemoveButtons() {
            const removeButtons = document.querySelectorAll('.remove-row');
            const rows = document.querySelectorAll('.expense-row');
            
            removeButtons.forEach((btn, index) => {
                btn.disabled = rows.length <= 1;
                btn.onclick = function() {
                    if (rows.length > 1) {
                        this.closest('tr').remove();
                        updateRemoveButtons();
                        calculateTotal();
                    }
                };
            });
        }

        // ฟังก์ชันสำหรับคำนวณยอดรวม
        // ฟังก์ชันสำหรับ bind events ของช่องจำนวนเงิน (ซ้ำ)
        function bindAmountEventsSecond() {
            const amountInputs = document.querySelectorAll('.expense-amount');
            amountInputs.forEach(input => {
                input.removeEventListener('input', calculateTotal);
                input.addEventListener('input', calculateTotal);
            });
        }

        // เรียกใช้ครั้งแรก
        updateRemoveButtons();
        bindAmountEvents();

        // Dynamic title based on expense type
        document.getElementById('expense_type').addEventListener('change', function(e) {
            const expenseTypes = {
                'travel': 'ค่าเดินทาง',
                'office_supplies': 'เครื่องเขียนและอุปกรณ์สำนักงาน',
                'meals': 'ค่าอาหาร',
                'accommodation': 'ค่าที่พัก',
                'fuel': 'ค่าน้ำมันเชื้อเพลิง',
                'communication': 'ค่าโทรศัพท์/อินเทอร์เน็ต',
                'maintenance': 'ค่าซ่อมแซม',
                'training': 'ค่าอบรม/สัมมนา',
                'others': 'อื่นๆ'
            };
            
            // เพิ่มรายการแรกในตารางโดยอัตโนมัติ
            if (e.target.value && expenseTypes[e.target.value]) {
                const firstDescInput = document.querySelector('.expense-description');
                if (firstDescInput && !firstDescInput.value) {
                    firstDescInput.value = expenseTypes[e.target.value];
                }
            }
        });

        // Format number with commas
        document.addEventListener('input', function(e) {
            if (e.target.classList.contains('amount-input')) {
                let value = e.target.value;
                // Remove any non-numeric characters except decimal point
                value = value.replace(/[^0-9.]/g, '');
                // Ensure only one decimal point
                const parts = value.split('.');
                if (parts.length > 2) {
                    value = parts[0] + '.' + parts.slice(1).join('');
                }
                e.target.value = value;
            }
        });

        // ฟังก์ชันสำหรับอัปเดตข้อมูลบริษัท
        function updateCompanyInfo() {
            const companyBranch = document.getElementById('company_branch').value;
            const companyName = document.getElementById('company-name');
            const companyAddress = document.getElementById('company-address');
            const companyLocation = document.getElementById('company-location');
            const companyTax = document.getElementById('company-tax');

            const companies = {
                'main': {
                    name: 'บริษัท เจอร์นัล คอร์ป จำกัด (สำนักงานใหญ่)',
                    address: 'เลขที่ 21/3 ซอยประชาชื่น 20 ถนนประชาชื่น',
                    location: 'แขวงทุ่งสองห้อง เขตหลักสี่ กรุงเทพฯ 10140',
                    tax: 'เลขประจำตัวผู้เสียภาษี 0105559072647'
                },
                'branch1': {
                    name: 'บริษัท เจอร์นัล คอร์ป จำกัด (สาขาที่ 1)',
                    address: 'เลขที่ 123/45 อาคารไอทีสแควร์ ถนนพหลโยธิน',
                    location: 'แขวงสามเสนใน เขตพญาไท กรุงเทพฯ 10400',
                    tax: 'เลขประจำตัวผู้เสียภาษี 0105559072647'
                },
                'branch2': {
                    name: 'บริษัท เจอร์นัล คอร์ป จำกัด (สาขาที่ 2)',
                    address: 'เลขที่ 456/78 ศูนย์การค้าเซ็นทรัลเวิลด์ ถนนราชดำริ',
                    location: 'แขวงลุมพินี เขตปทุมวัน กรุงเทพฯ 10330',
                    tax: 'เลขประจำตัวผู้เสียภาษี 0105559072647'
                },
                'subsidiary1': {
                    name: 'บริษัท เจอร์นัล มีเดีย จำกัด',
                    address: 'เลขที่ 789/12 อาคารช่องนนทรี ถนนนราธิวาส',
                    location: 'แขวงช่องนนทรี เขตยานนาวา กรุงเทพฯ 10120',
                    tax: 'เลขประจำตัวผู้เสียภาษี 0105559072648'
                },
                'subsidiary2': {
                    name: 'บริษัท เจอร์นัล เทคโนโลยี จำกัด',
                    address: 'เลขที่ 321/65 อาคารไอทีทาวเวอร์ ถนนบางนา',
                    location: 'แขวงบางนา เขตบางนา กรุงเทพฯ 10260',
                    tax: 'เลขประจำตัวผู้เสียภาษี 0105559072649'
                }
            };

            const selectedCompany = companies[companyBranch];
            if (selectedCompany) {
                companyName.textContent = selectedCompany.name;
                companyAddress.textContent = selectedCompany.address;
                companyLocation.textContent = selectedCompany.location;
                companyTax.textContent = selectedCompany.tax;
            }
        }

        // ฟังก์ชันสำหรับอัปเดตข้อมูลบริษัทในฟอร์ม
        function updateFormCompanyInfo() {
            const companyBranch = document.getElementById('company_branch_form').value;
            const taxIdForm = document.getElementById('tax_id_form');
            const companyAddressForm = document.getElementById('company_address_form');

            const companies = {
                'main': {
                    address: 'เลขที่ 21/3 ซอยประชาชื่น 20 ถนนประชาชื่น แขวงทุ่งสองห้อง เขตหลักสี่ กรุงเทพฯ 10140',
                    tax: '0105559072647'
                },
                'branch1': {
                    address: 'เลขที่ 123/45 อาคารไอทีสแควร์ ถนนพหลโยธิน แขวงสามเสนใน เขตพญาไท กรุงเทพฯ 10400',
                    tax: '0105559072647'
                },
                'branch2': {
                    address: 'เลขที่ 456/78 ศูนย์การค้าเซ็นทรัลเวิลด์ ถนนราชดำริ แขวงลุมพินี เขตปทุมวัน กรุงเทพฯ 10330',
                    tax: '0105559072647'
                },
                'subsidiary1': {
                    address: 'เลขที่ 789/12 อาคารช่องนนทรี ถนนนราธิวาส แขวงช่องนนทรี เขตยานนาวา กรุงเทพฯ 10120',
                    tax: '0105559072648'
                },
                'subsidiary2': {
                    address: 'เลขที่ 321/65 อาคารไอทีทาวเวอร์ ถนนบางนา แขวงบางนา เขตบางนา กรุงเทพฯ 10260',
                    tax: '0105559072649'
                }
            };

            const selectedCompany = companies[companyBranch];
            if (selectedCompany) {
                taxIdForm.value = selectedCompany.tax;
                companyAddressForm.value = selectedCompany.address;
            } else {
                taxIdForm.value = '';
                companyAddressForm.value = '';
            }
        }

        // ฟังก์ชันสำหรับพิมพ์ PDF
        function printToPDF() {
            // ซ่อนปุ่มต่างๆ ก่อนพิมพ์
            const buttons = document.querySelectorAll('.btn, .alert');
            buttons.forEach(btn => btn.style.display = 'none');
            
            // เปลี่ยนสไตล์สำหรับการพิมพ์
            document.body.classList.add('print-mode');
            
            // สร้างหน้าต่างใหม่สำหรับพิมพ์
            const printWindow = window.open('', '_blank');
            const currentDate = new Date().toLocaleDateString('th-TH');
            
            // เตรียมข้อมูลสำหรับ PDF
            const companyName = document.getElementById('company_branch_form').selectedOptions[0]?.text || 'บริษัท เจอร์นัล คอร์ป จำกัด (สำนักงานใหญ่)';
            const companyAddress = document.getElementById('company_address_form').value || 'เลขที่ 21/3 ซอยประชาชื่น 20 ถนนประชาชื่น แขวงทุ่งสองห้อง เขตหลักสี่ กรุงเทพฯ 10140';
            const taxId = document.getElementById('tax_id_form').value || '0105559072647';
            const voucherNo = document.getElementById('voucher_no').value;
            const payTo = document.getElementById('request_title').value;
            const department = document.getElementById('department').value;
            const costCenter = document.getElementById('cost_center').value;
            const requestDate = document.getElementById('request_date').value;
            const voucherType = document.querySelector('input[name="voucher_type"]:checked').nextElementSibling.textContent;
            const totalAmount = document.getElementById('total_amount').value;
            
            // สร้างตารางรายการ
            const expenseRows = document.querySelectorAll('.expense-row');
            let expenseTableHTML = '';
            
            expenseRows.forEach((row, index) => {
                const description = row.querySelector('.expense-description').value;
                const amount = row.querySelector('.expense-amount').value;
                const note = row.querySelector('.expense-note').value;
                
                if (description && amount) {
                    expenseTableHTML += `
                        <tr>
                            <td style="border: 1px solid black; padding: 8px;">${description}</td>
                            <td style="border: 1px solid black; padding: 8px; text-align: right;">${parseFloat(amount).toLocaleString('th-TH', {minimumFractionDigits: 2})}</td>
                        </tr>
                    `;
                }
            });
            
            // เติมแถวว่างให้เต็ม 10 แถว
            const remainingRows = 8 - expenseRows.length;
            for (let i = 0; i < remainingRows; i++) {
                expenseTableHTML += `
                    <tr>
                        <td style="border: 1px solid black; padding: 20px;">&nbsp;</td>
                        <td style="border: 1px solid black; padding: 20px;">&nbsp;</td>
                    </tr>
                `;
            }
            
            const htmlContent = `
                <!DOCTYPE html>
                <html lang="th">
                <head>
                    <meta charset="UTF-8">
                    <title>ใบเบิกเงินสดย่อย - ${voucherNo}</title>
                    <style>
                        @page {
                            size: A4;
                            margin: 1cm;
                        }
                        body {
                            font-family: 'Sarabun', sans-serif;
                            font-size: 14px;
                            line-height: 1.4;
                            color: black;
                            margin: 0;
                            padding: 20px;
                        }
                        .header {
                            display: flex;
                            justify-content: space-between;
                            align-items: flex-start;
                            margin-bottom: 20px;
                            border: 2px solid black;
                            padding: 10px;
                        }
                        .company-info {
                            flex: 1;
                        }
                        .title {
                            flex: 2;
                            text-align: center;
                        }
                        .voucher-no {
                            flex: 1;
                            text-align: right;
                        }
                        .form-row {
                            margin-bottom: 10px;
                            display: flex;
                            align-items: center;
                        }
                        .form-row label {
                            font-weight: bold;
                            margin-right: 10px;
                            min-width: 120px;
                        }
                        .form-row input {
                            border-bottom: 1px solid black;
                            padding: 2px 5px;
                            margin-right: 20px;
                        }
                        .checkbox-group {
                            display: flex;
                            gap: 20px;
                            margin: 10px 0;
                        }
                        .table {
                            width: 100%;
                            border-collapse: collapse;
                            margin: 20px 0;
                        }
                        .table th, .table td {
                            border: 1px solid black;
                            padding: 8px;
                            text-align: left;
                        }
                        .table th {
                            background-color: #f0f0f0;
                            font-weight: bold;
                            text-align: center;
                        }
                        .total-row {
                            background-color: #f9f9f9;
                            font-weight: bold;
                        }
                        .signature-section {
                            margin-top: 30px;
                            display: flex;
                            justify-content: space-between;
                        }
                        .signature-box {
                            text-align: center;
                            width: 200px;
                        }
                        .signature-line {
                            border-bottom: 1px solid black;
                            height: 50px;
                            margin-bottom: 5px;
                        }
                    </style>
                </head>
                <body>
                    <div class="header">
                        <div class="company-info">
                            <strong>${companyName}</strong><br>
                            ${companyAddress}<br>
                            เลขประจำตัวผู้เสียภาษี ${taxId}
                        </div>
                        <div class="title">
                            <h2 style="margin: 0;">ใบเบิกเงินสดย่อย</h2>
                            <h3 style="margin: 0;">Petty Cash Voucher</h3>
                        </div>
                        <div class="voucher-no">
                            <strong>No. ${voucherNo}</strong><br>
                            วันที่: ${new Date(requestDate).toLocaleDateString('th-TH')}
                        </div>
                    </div>
                    
                    <div class="checkbox-group">
                        <label><input type="checkbox" ${voucherType === 'เบิกเงิน' ? 'checked' : ''}> เบิกเงิน</label>
                        <label><input type="checkbox" ${voucherType === 'โอนเงิน' ? 'checked' : ''}> โอนเงิน</label>
                    </div>
                    
                    <div class="form-row">
                        <label>จ่ายให้/Pay to:</label>
                        <span style="border-bottom: 1px solid black; flex: 1; padding: 2px;">${payTo}</span>
                        <label style="margin-left: 20px;">แผนก/ฝ่าย:</label>
                        <span style="border-bottom: 1px solid black; min-width: 150px; padding: 2px;">${department}</span>
                        <label style="margin-left: 20px;">Cost:</label>
                        <span style="border-bottom: 1px solid black; min-width: 100px; padding: 2px;">${costCenter}</span>
                    </div>
                    
                    <table class="table">
                        <thead>
                            <tr>
                                <th style="width: 70%;">รายการ</th>
                                <th style="width: 30%;">จำนวนเงิน</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${expenseTableHTML}
                            <tr class="total-row">
                                <td style="text-align: center;"><strong>รวมเงินทั้งสิ้น</strong></td>
                                <td style="text-align: right;"><strong>${parseFloat(totalAmount || 0).toLocaleString('th-TH', {minimumFractionDigits: 2})} บาท</strong></td>
                            </tr>
                        </tbody>
                    </table>
                    
                    <div class="signature-section">
                        <div class="signature-box">
                            <div class="signature-line"></div>
                            <strong>ผู้ขอเบิก</strong><br>
                            วันที่.............................
                        </div>
                        <div class="signature-box">
                            <div class="signature-line"></div>
                            <strong>ผู้จ่าย</strong><br>
                            วันที่.............................
                        </div>
                        <div class="signature-box">
                            <div class="signature-line"></div>
                            <strong>ผู้อนุมัติ</strong><br>
                            วันที่.............................
                        </div>
                    </div>
                </body>
                </html>
            `;
            
            printWindow.document.write(htmlContent);
            printWindow.document.close();
            
            setTimeout(() => {
                printWindow.print();
                printWindow.close();
                
                // แสดงปุ่มกลับมา
                buttons.forEach(btn => btn.style.display = '');
                document.body.classList.remove('print-mode');
            }, 500);
        }
        
        // เริ่มต้น events เมื่อหน้าเว็บโหลดเสร็จ
        document.addEventListener('DOMContentLoaded', function() {
            console.log('🚀 DOM Content Loaded - Setting up approval system');
            updateCostCenter(); // อัปเดต Cost Center เมื่อโหลดหน้าเว็บ
            
            // เพิ่ม Event Listeners
            setupTableEvents();
            setupFormValidation();
            setupCompanyInfoUpdater();
            
            // แสดงผู้อนุมัติที่แนะนำครั้งแรก
            displayRecommendedApprover();
        });
    </script>
</body>
</html>

<?php
// ปิดการเชื่อมต่อฐานข้อมูลหลังจากใช้งานเสร็จ
if ($conn) {
    if (is_resource($conn)) {
        // SQLSRV Connection (resource)
        sqlsrv_close($conn);
    } else if (is_object($conn)) {
        $conn_type = get_class($conn);
        if (strpos($conn_type, 'mysqli') !== false) {
            $conn->close();
        }
        // PDO จะ close อัตโนมัติเมื่อ variable out of scope
    }
}
?>

