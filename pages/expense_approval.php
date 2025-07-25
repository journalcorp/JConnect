<?php
header('Content-Type: text/html; charset=UTF-8');
session_start();
include_once '../includes/header.php';
include_once '../includes/menu.php';
include_once '../config.php';

// สำหรับการทดสอบ: ตั้งค่า session หากไม่มี
if (!isset($_SESSION['employee_id'])) {
    $_SESSION['employee_id'] = 'TEST001';
    $_SESSION['employee_name'] = 'ผู้ทดสอบระบบ';
    $_SESSION['department'] = 'IT';
    $_SESSION['division'] = 'JD001';
}

// ตรวจสอบสิทธิ์การเข้าถึงจะทำหลังจากตรวจสอบระดับการอนุมัติแล้ว

// สร้างการเชื่อมต่อฐานข้อมูล
try {
    $conn = dbConnect();
    $database_available = ($conn !== null && is_resource($conn));
    if ($database_available) {
        error_log("Database connection successful for expense approval");
    } else {
        error_log("Database connection returned null - using offline mode");
        $database_available = false;
    }
} catch (Exception $e) {
    $database_available = false;
    $conn = null;
    error_log("Database connection failed: " . $e->getMessage());
}

// ดึงข้อมูลผู้ใช้และสิทธิ์การอนุมัติ
$current_user_id = $_SESSION['employee_id'];
$current_user_approval_level = null;
$approval_structures = [];

if ($database_available) {
    try {
        // ดึงข้อมูลผู้ใช้และระดับการอนุมัติ
        $sql_user_level = "
            SELECT 
                e.employee_id,
                e.first_name_th,
                e.last_name_th,
                e.position_th,
                a.ApprovalID,
                a.ApprovalName,
                CASE 
                    WHEN a.ApprovalLevel1 = ? THEN 1
                    WHEN a.ApprovalLevel2 = ? THEN 2
                    WHEN a.ApprovalLevel3 = ? THEN 3
                    ELSE 0
                END as user_approval_level
            FROM dbo.employees e
            LEFT JOIN dbo.approvals a ON (a.ApprovalLevel1 = e.employee_id OR a.ApprovalLevel2 = e.employee_id OR a.ApprovalLevel3 = e.employee_id)
            WHERE e.employee_id = ?
        ";
        
        if (is_resource($conn)) {
            // SQLSRV Connection
            $params = [$current_user_id, $current_user_id, $current_user_id, $current_user_id];
            $result_user = sqlsrv_query($conn, $sql_user_level, $params);
            
            if ($result_user) {
                while ($user_info = sqlsrv_fetch_array($result_user, SQLSRV_FETCH_ASSOC)) {
                    if ($user_info['user_approval_level'] > 0) {
                        $current_user_approval_level = $user_info['user_approval_level'];
                        error_log("User {$current_user_id} has approval level: {$current_user_approval_level}");
                        break;
                    }
                }
            }
        }
        
        // หากไม่พบสิทธิ์การอนุมัติ ให้ตั้งเป็น 1 สำหรับการทดสอบ
        if (!$current_user_approval_level) {
            $current_user_approval_level = 1;
            error_log("No approval level found for user {$current_user_id}, defaulting to level 1 for testing");
        }
        
    } catch (Exception $e) {
        error_log("Error fetching user approval level: " . $e->getMessage());
        $current_user_approval_level = 1; // Default for testing
    }
} else {
    $current_user_approval_level = 1; // Default for testing
}

// ตรวจสอบสิทธิ์การเข้าถึงหลังจากได้ระดับการอนุมัติแล้ว
if (!$current_user_approval_level) {
    echo '<div style="text-align:center;margin-top:50px;">
            <h3>คุณไม่มีสิทธิ์อนุมัติในระบบ</h3>
            <p>กรุณาติดต่อผู้ดูแลระบบเพื่อตั้งค่าสิทธิ์การอนุมัติ</p>
            <p><small>User ID: ' . htmlspecialchars($current_user_id) . '</small></p>
          </div>';
    exit;
}

// ประมวลผลการอนุมัติ/ปฏิเสธ
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    $request_id = $_POST['request_id'] ?? 0;
    $approval_level = $_POST['approval_level'] ?? 0;
    $comments = trim($_POST['comments'] ?? '');
    
    if ($database_available && $request_id > 0 && $approval_level > 0) {
        try {
            if (is_resource($conn)) {
                sqlsrv_begin_transaction($conn);
                
                $status = ($action === 'approve') ? 'Approved' : 'Rejected';
                $current_date = date('Y-m-d H:i:s');
                
                // อัปเดตสถานะการอนุมัติ
                $sql_update = "
                    UPDATE dbo.ExpenseRequests 
                    SET status_level{$approval_level} = ?,
                        approved_date_level{$approval_level} = GETDATE(),
                        remarks = ?
                    WHERE id = ?
                ";
                
                $params_update = [$status, $comments, $request_id];
                $result_update = sqlsrv_query($conn, $sql_update, $params_update);
                
                if (!$result_update) {
                    throw new Exception("ไม่สามารถอัปเดตสถานะได้");
                }
                
                // อัปเดต overall_status และ current_approval_level
                if ($action === 'approve') {
                    // ตรวจสอบว่าต้องส่งต่อไประดับถัดไปหรือไม่
                    $sql_check_next = "
                        SELECT 
                            CASE 
                                WHEN ? = 1 AND approver_level2 IS NOT NULL THEN 2
                                WHEN ? = 2 AND approver_level3 IS NOT NULL THEN 3
                                ELSE 0
                            END as next_level
                        FROM dbo.ExpenseRequests 
                        WHERE id = ?
                    ";
                    
                    $result_next = sqlsrv_query($conn, $sql_check_next, [$approval_level, $approval_level, $request_id]);
                    $next_info = sqlsrv_fetch_array($result_next, SQLSRV_FETCH_ASSOC);
                    
                    if ($next_info['next_level'] > 0) {
                        // ส่งต่อไประดับถัดไป
                        $sql_update_overall = "
                            UPDATE dbo.ExpenseRequests 
                            SET current_approval_level = ?, overall_status = 'Pending'
                            WHERE id = ?
                        ";
                        sqlsrv_query($conn, $sql_update_overall, [$next_info['next_level'], $request_id]);
                        $message = "อนุมัติเรียบร้อย ส่งต่อไปยังผู้อนุมัติระดับ " . $next_info['next_level'];
                    } else {
                        // อนุมัติสมบูรณ์
                        $sql_update_overall = "
                            UPDATE dbo.ExpenseRequests 
                            SET overall_status = 'Approved'
                            WHERE id = ?
                        ";
                        sqlsrv_query($conn, $sql_update_overall, [$request_id]);
                        $message = "อนุมัติคำขอเบิกจ่ายเรียบร้อยแล้ว";
                    }
                } else {
                    // ปฏิเสธ
                    $sql_update_overall = "
                        UPDATE dbo.ExpenseRequests 
                        SET overall_status = 'Rejected'
                        WHERE id = ?
                    ";
                    sqlsrv_query($conn, $sql_update_overall, [$request_id]);
                    $message = "ปฏิเสธคำขอเบิกจ่ายเรียบร้อยแล้ว";
                }
                
                sqlsrv_commit($conn);
                
            }
        } catch (Exception $e) {
            if (is_resource($conn)) {
                sqlsrv_rollback($conn);
            }
            $message = "เกิดข้อผิดพลาด: " . $e->getMessage();
        }
    }
}

// ดึงรายการคำขอที่รอการอนุมัติ
$pending_requests = [];
if ($database_available && $current_user_approval_level) {
    try {
        // SQL ที่ปรับปรุงแล้วเพื่อให้ทำงานได้ดีขึ้น
        $sql_pending = "
            SELECT 
                er.id,
                er.voucher_no,
                er.pay_to,
                er.department,
                er.expense_type,
                er.total_amount,
                er.description,
                er.created_at,
                er.current_approval_level,
                er.overall_status,
                er.remarks,
                er.requester_id,
                ISNULL(e.first_name_th + ' ' + e.last_name_th, er.requester_id) as requester_name,
                ISNULL(e.position_th, 'ไม่ระบุ') as requester_position,
                (SELECT COUNT(*) FROM dbo.ExpenseItems ei WHERE er.id = ei.expense_request_id) as items_count
            FROM dbo.ExpenseRequests er
            LEFT JOIN dbo.employees e ON er.requester_id = e.employee_id
            WHERE er.current_approval_level = ? 
            AND er.overall_status = 'Pending'
            ORDER BY er.created_at ASC
        ";
        
        if (is_resource($conn)) {
            $result_pending = sqlsrv_query($conn, $sql_pending, [$current_user_approval_level]);
            
            if ($result_pending) {
                while ($row = sqlsrv_fetch_array($result_pending, SQLSRV_FETCH_ASSOC)) {
                    // แปลง datetime object เป็น string
                    if (isset($row['created_at']) && $row['created_at'] instanceof DateTime) {
                        $row['created_at'] = $row['created_at']->format('Y-m-d H:i:s');
                    }
                    $pending_requests[] = $row;
                }
                error_log("Found " . count($pending_requests) . " pending requests for approval level {$current_user_approval_level}");
            } else {
                error_log("SQL Error in pending requests query: " . print_r(sqlsrv_errors(), true));
            }
        }
        
    } catch (Exception $e) {
        error_log("Error fetching pending requests: " . $e->getMessage());
    }
} else {
    // ฐานข้อมูลไม่พร้อม - ใช้ข้อมูลจำลองสำหรับการทดสอบ
    if (!$database_available) {
        error_log("Using mock data - database not available");
        $pending_requests = [
            [
                'id' => 1,
                'voucher_no' => 'VN000001',
                'pay_to' => 'บริษัท ตัวอย่าง จำกัด',
                'department' => 'แผนกการเงิน',
                'expense_type' => 'travel',
                'total_amount' => 1500.00,
                'description' => 'ค่าเดินทางไปประชุมที่สำนักงานใหญ่',
                'created_at' => date('Y-m-d H:i:s', strtotime('-1 hour')),
                'current_approval_level' => 1,
                'overall_status' => 'Pending',
                'remarks' => '',
                'requester_id' => 'EMP001',
                'requester_name' => 'นายตัวอย่าง ทดสอบ',
                'requester_position' => 'นักวิเคราะห์',
                'items_count' => 3
            ],
            [
                'id' => 2,
                'voucher_no' => 'VN000002',
                'pay_to' => 'ร้านเครื่องเขียน ABC',
                'department' => 'แผนกบุคคล',
                'expense_type' => 'office_supplies',
                'total_amount' => 750.00,
                'description' => 'ซื้อเครื่องเขียนสำนักงาน',
                'created_at' => date('Y-m-d H:i:s', strtotime('-2 hours')),
                'current_approval_level' => 1,
                'overall_status' => 'Pending',
                'remarks' => '',
                'requester_id' => 'EMP002',
                'requester_name' => 'นางสาวตัวอย่าง ใหม่',
                'requester_position' => 'เจ้าหน้าที่บุคคล',
                'items_count' => 2
            ]
        ];
    }
}

// ดึงรายการที่อนุมัติ/ปฏิเสธแล้ว (30 วันล่าสุด)
$processed_requests = [];
if ($database_available && $current_user_approval_level) {
    try {
        $sql_processed = "
            SELECT TOP 50
                er.id,
                er.voucher_no,
                er.pay_to,
                er.total_amount,
                er.overall_status,
                er.created_at,
                CASE 
                    WHEN ? = 1 THEN er.status_level1
                    WHEN ? = 2 THEN er.status_level2
                    WHEN ? = 3 THEN er.status_level3
                END as my_status,
                CASE 
                    WHEN ? = 1 THEN er.approved_date_level1
                    WHEN ? = 2 THEN er.approved_date_level2
                    WHEN ? = 3 THEN er.approved_date_level3
                END as my_approved_date,
                e.first_name_th + ' ' + e.last_name_th as requester_name
            FROM dbo.ExpenseRequests er
            LEFT JOIN dbo.employees e ON er.requester_id = e.employee_id
            WHERE (
                (? = 1 AND er.status_level1 IS NOT NULL AND er.status_level1 != 'Pending') OR
                (? = 2 AND er.status_level2 IS NOT NULL AND er.status_level2 != 'Pending') OR
                (? = 3 AND er.status_level3 IS NOT NULL AND er.status_level3 != 'Pending')
            )
            AND er.created_at >= DATEADD(day, -30, GETDATE())
            ORDER BY 
                CASE 
                    WHEN ? = 1 THEN er.approved_date_level1
                    WHEN ? = 2 THEN er.approved_date_level2
                    WHEN ? = 3 THEN er.approved_date_level3
                END DESC
        ";
        
        $params_processed = array_fill(0, 11, $current_user_approval_level);
        
        if (is_resource($conn)) {
            $result_processed = sqlsrv_query($conn, $sql_processed, $params_processed);
            
            if ($result_processed) {
                while ($row = sqlsrv_fetch_array($result_processed, SQLSRV_FETCH_ASSOC)) {
                    // แปลง datetime objects เป็น strings
                    if (isset($row['created_at']) && $row['created_at'] instanceof DateTime) {
                        $row['created_at'] = $row['created_at']->format('Y-m-d H:i:s');
                    }
                    if (isset($row['my_approved_date']) && $row['my_approved_date'] instanceof DateTime) {
                        $row['my_approved_date'] = $row['my_approved_date']->format('Y-m-d H:i:s');
                    }
                    $processed_requests[] = $row;
                }
            }
        }
        
    } catch (Exception $e) {
        error_log("Error fetching processed requests: " . $e->getMessage());
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
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>อนุมัติคำขอเบิกจ่าย - ระบบเบิกจ่าย</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: #667eea;
            --secondary-color: #764ba2;
            --success-color: #10ac84;
            --warning-color: #f39c12;
            --danger-color: #ee5a52;
            --info-color: #3742fa;
            --light-color: #f8f9fa;
            --dark-color: #2d3436;
        }

        body {
            font-family: 'Prompt', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            position: relative;
        }
        
        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: 
                radial-gradient(circle at 20% 50%, rgba(120, 119, 198, 0.3) 0%, transparent 50%),
                radial-gradient(circle at 80% 20%, rgba(255, 255, 255, 0.1) 0%, transparent 50%),
                radial-gradient(circle at 40% 80%, rgba(120, 119, 198, 0.2) 0%, transparent 50%);
            pointer-events: none;
            z-index: -1;
        }
        
        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
            position: relative;
            z-index: 1;
        }
        
        .card {
            border-radius: 20px;
            box-shadow: 
                0 20px 25px -5px rgba(0, 0, 0, 0.1),
                0 10px 10px -5px rgba(0, 0, 0, 0.04);
            border: none;
            margin-bottom: 25px;
            backdrop-filter: blur(20px);
            background: rgba(255, 255, 255, 0.95);
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            overflow: hidden;
            position: relative;
        }
        
        .card::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.3), transparent);
            transition: left 0.6s;
        }
        
        .card:hover {
            transform: translateY(-8px) scale(1.02);
            box-shadow: 
                0 32px 64px -12px rgba(0, 0, 0, 0.25),
                0 16px 32px -8px rgba(0, 0, 0, 0.15);
        }
        
        .card:hover::before {
            left: 100%;
        }
        
        .card-header {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            color: white;
            border-radius: 20px 20px 0 0 !important;
            padding: 2.5rem;
            border: none;
            position: relative;
            overflow: hidden;
        }
        
        .card-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(45deg, transparent 0%, rgba(255,255,255,0.1) 50%, transparent 100%);
            transform: translateX(-100%);
            transition: transform 0.8s ease;
        }
        
        .card:hover .card-header::before {
            transform: translateX(100%);
        }
        
        .card-header h5 {
            margin: 0;
            font-weight: 600;
            font-size: 1.4rem;
            text-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .btn-approve {
            background: linear-gradient(135deg, var(--success-color) 0%, #1dd1a1 100%);
            border: none;
            color: white;
            border-radius: 15px;
            padding: 12px 24px;
            font-weight: 600;
            font-size: 0.9rem;
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            box-shadow: 0 6px 20px rgba(16, 172, 132, 0.4);
            position: relative;
            overflow: hidden;
        }
        
        .btn-approve::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.3), transparent);
            transition: left 0.6s;
        }
        
        .btn-approve:hover {
            transform: translateY(-3px) scale(1.05);
            box-shadow: 0 12px 35px rgba(16, 172, 132, 0.6);
            background: linear-gradient(135deg, #0e9c75 0%, #1abc9c 100%);
        }
        
        .btn-approve:hover::before {
            left: 100%;
        }
        
        .btn-reject {
            background: linear-gradient(135deg, var(--danger-color) 0%, #ff6b6b 100%);
            border: none;
            color: white;
            border-radius: 15px;
            padding: 12px 24px;
            font-weight: 600;
            font-size: 0.9rem;
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            box-shadow: 0 6px 20px rgba(238, 90, 82, 0.4);
            position: relative;
            overflow: hidden;
        }
        
        .btn-reject::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.3), transparent);
            transition: left 0.6s;
        }
        
        .btn-reject:hover {
            transform: translateY(-3px) scale(1.05);
            box-shadow: 0 12px 35px rgba(238, 90, 82, 0.6);
            background: linear-gradient(135deg, #e74c3c 0%, #ff5252 100%);
        }
        
        .btn-reject:hover::before {
            left: 100%;
        }
        
        .btn-detail {
            background: linear-gradient(135deg, var(--info-color) 0%, #5f27cd 100%);
            border: none;
            color: white;
            border-radius: 15px;
            padding: 10px 20px;
            font-weight: 600;
            font-size: 0.85rem;
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            box-shadow: 0 6px 20px rgba(55, 66, 250, 0.4);
            position: relative;
            overflow: hidden;
        }
        
        .btn-detail::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.3), transparent);
            transition: left 0.6s;
        }
        
        .btn-detail:hover {
            transform: translateY(-3px) scale(1.05);
            box-shadow: 0 12px 35px rgba(55, 66, 250, 0.6);
            background: linear-gradient(135deg, #2f3542 0%, #40407a 100%);
        }
        
        .btn-detail:hover::before {
            left: 100%;
        }
        .status-pending {
            background: linear-gradient(135deg, #f39c12 0%, #f1c40f 100%);
            color: #2c3e50;
            padding: 6px 16px;
            border-radius: 25px;
            font-weight: 600;
            font-size: 0.85rem;
            box-shadow: 0 3px 10px rgba(243, 156, 18, 0.3);
            border: 2px solid rgba(241, 196, 15, 0.3);
        }
        
        .status-approved {
            background: linear-gradient(135deg, #10ac84 0%, #1dd1a1 100%);
            color: white;
            padding: 8px 18px;
            border-radius: 25px;
            font-weight: 700;
            font-size: 0.85rem;
            box-shadow: 0 6px 20px rgba(16, 172, 132, 0.4);
            border: 2px solid rgba(29, 209, 161, 0.3);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            animation: success-glow 3s infinite;
            position: relative;
            overflow: hidden;
        }
        
        .status-approved::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.3), transparent);
            transition: left 0.8s ease;
        }
        
        .status-approved:hover::before {
            left: 100%;
        }
        
        @keyframes success-glow {
            0%, 100% { 
                box-shadow: 0 6px 20px rgba(16, 172, 132, 0.4);
            }
            50% { 
                box-shadow: 0 10px 30px rgba(16, 172, 132, 0.6);
            }
        }
        
        .status-rejected {
            background: linear-gradient(135deg, #ee5a52 0%, #ff6b6b 100%);
            color: white;
            padding: 8px 18px;
            border-radius: 25px;
            font-weight: 700;
            font-size: 0.85rem;
            box-shadow: 0 6px 20px rgba(238, 90, 82, 0.4);
            border: 2px solid rgba(255, 107, 107, 0.3);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            animation: danger-pulse 3s infinite;
            position: relative;
            overflow: hidden;
        }
        
        .status-rejected::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.3), transparent);
            transition: left 0.8s ease;
        }
        
        .status-rejected:hover::before {
            left: 100%;
        }
        
        @keyframes danger-pulse {
            0%, 100% { 
                box-shadow: 0 6px 20px rgba(238, 90, 82, 0.4);
            }
            50% { 
                box-shadow: 0 10px 30px rgba(238, 90, 82, 0.6);
            }
        }
        
        .status-pending {
            background: linear-gradient(135deg, #f39c12 0%, #f1c40f 100%);
            color: #2c3e50;
            padding: 8px 18px;
            border-radius: 25px;
            font-weight: 700;
            font-size: 0.85rem;
            box-shadow: 0 6px 20px rgba(243, 156, 18, 0.4);
            border: 2px solid rgba(241, 196, 15, 0.3);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            animation: warning-blink 2s infinite;
            position: relative;
            overflow: hidden;
        }
        
        .status-pending::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.3), transparent);
            transition: left 0.8s ease;
        }
        
        .status-pending:hover::before {
            left: 100%;
        }
        
        @keyframes warning-blink {
            0%, 100% { 
                box-shadow: 0 6px 20px rgba(243, 156, 18, 0.4);
                opacity: 1;
            }
            50% { 
                box-shadow: 0 10px 30px rgba(243, 156, 18, 0.6);
                opacity: 0.8;
            }
        }
            font-size: 0.85rem;
            box-shadow: 0 3px 10px rgba(16, 172, 132, 0.3);
            border: 2px solid rgba(29, 209, 161, 0.3);
        }
        
        .status-rejected {
            background: linear-gradient(135deg, #ee5a52 0%, #ff6b6b 100%);
            color: white;
            padding: 6px 16px;
            border-radius: 25px;
            font-weight: 600;
            font-size: 0.85rem;
            box-shadow: 0 3px 10px rgba(238, 90, 82, 0.3);
            border: 2px solid rgba(255, 107, 107, 0.3);
        }
        .voucher-no {
            font-family: 'Courier New', monospace;
            font-weight: bold;
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            color: white;
            padding: 12px 20px;
            border-radius: 15px;
            font-size: 1rem;
            letter-spacing: 1.5px;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
            border: 2px solid rgba(255, 255, 255, 0.2);
            position: relative;
            overflow: hidden;
        }
        
        .voucher-no::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transition: left 0.6s;
        }
        
        .request-row:hover .voucher-no::before {
            left: 100%;
        }
        
        .amount {
            font-weight: 800;
            color: var(--success-color);
            font-size: 1.4rem;
            background: linear-gradient(135deg, var(--success-color) 0%, #00cec9 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            text-shadow: 0 2px 4px rgba(16, 172, 132, 0.3);
        }
        
        .amount-container {
            text-align: right;
            padding: 15px;
            background: linear-gradient(135deg, rgba(255,255,255,0.9) 0%, rgba(240,248,255,0.9) 100%);
            border-radius: 15px;
            border: 2px solid rgba(16, 172, 132, 0.2);
            box-shadow: 0 4px 15px rgba(16, 172, 132, 0.1);
            transition: all 0.3s ease;
        }
        
        .amount-container:hover {
            transform: scale(1.05);
            box-shadow: 0 8px 25px rgba(16, 172, 132, 0.2);
        }
        
        .approval-level {
            background: linear-gradient(135deg, #8b5cf6 0%, #a855f7 100%);
            color: white;
            padding: 8px 20px;
            border-radius: 25px;
            font-weight: 600;
            font-size: 0.9rem;
            box-shadow: 0 4px 15px rgba(139, 92, 246, 0.3);
            border: 2px solid rgba(168, 85, 247, 0.3);
        }
        .table {
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
            background: white;
        }
        
        .table th {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            border: none;
            font-weight: 700;
            color: white;
            padding: 1.5rem 1.2rem;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            text-shadow: 0 2px 4px rgba(0,0,0,0.2);
            position: relative;
        }
        
        .table th::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 100%;
            height: 2px;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.5), transparent);
        }
        
        .table td {
            padding: 1.5rem 1.2rem;
            border: none;
            border-bottom: 1px solid rgba(0,0,0,0.05);
            vertical-align: middle;
            background: white;
            transition: all 0.3s ease;
        }
        
        .table-hover tbody tr {
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            cursor: pointer;
        }
        
        .table-hover tbody tr:hover {
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.05) 0%, rgba(118, 75, 162, 0.05) 100%);
            transform: scale(1.02);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
            border-radius: 15px;
        }
        
        .user-info {
            background: linear-gradient(135deg, rgba(255,255,255,0.95) 0%, rgba(240,248,255,0.95) 100%);
            border-radius: 25px;
            padding: 2rem;
            box-shadow: 
                0 20px 25px -5px rgba(0, 0, 0, 0.1),
                0 10px 10px -5px rgba(0, 0, 0, 0.04);
            border: 1px solid rgba(255, 255, 255, 0.3);
            backdrop-filter: blur(20px);
            position: relative;
            overflow: hidden;
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        }
        
        .user-info::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(102, 126, 234, 0.1), transparent);
            transition: left 0.8s ease;
        }
        
        .user-info:hover {
            transform: translateY(-5px);
            box-shadow: 
                0 32px 64px -12px rgba(0, 0, 0, 0.25),
                0 16px 32px -8px rgba(0, 0, 0, 0.15);
        }
        
        .user-info:hover::before {
            left: 100%;
        }
        
        .avatar-circle {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem;
            font-size: 2rem;
            color: white;
            font-weight: 700;
            box-shadow: 0 15px 35px rgba(102, 126, 234, 0.4);
            border: 4px solid rgba(255, 255, 255, 0.3);
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            position: relative;
            overflow: hidden;
        }
        
        .avatar-circle::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.3), transparent);
            transition: left 0.6s ease;
        }
        
        .avatar-circle:hover {
            transform: scale(1.1) rotate(10deg);
            box-shadow: 0 25px 50px rgba(102, 126, 234, 0.6);
        }
        
        .avatar-circle:hover::before {
            left: 100%;
        }
            padding: 2.5rem;
            margin-bottom: 2rem;
            border: 2px solid rgba(102, 126, 234, 0.2);
            position: relative;
            overflow: hidden;
            backdrop-filter: blur(15px);
            box-shadow: 0 15px 35px rgba(0,0,0,0.1);
        }
        
        .user-info::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 100%;
            height: 100%;
            background: radial-gradient(circle, rgba(102, 126, 234, 0.1) 0%, transparent 70%);
            border-radius: 50%;
        }
        
        .user-info h5 {
            color: var(--primary-color);
            font-weight: 700;
            margin-bottom: 1.5rem;
            font-size: 1.3rem;
        }
        
        .user-info p {
            margin-bottom: 0.8rem;
            color: var(--dark-color);
            font-size: 1rem;
        }
        
        .user-info .approval-level {
            background: linear-gradient(135deg, var(--info-color) 0%, var(--primary-color) 100%);
            color: white;
            padding: 8px 20px;
            border-radius: 25px;
            font-weight: 600;
            font-size: 0.9rem;
            display: inline-block;
            box-shadow: 0 4px 15px rgba(55, 66, 250, 0.3);
            animation: pulse-glow 2s infinite;
        }
        
        @keyframes pulse-glow {
            0%, 100% { box-shadow: 0 4px 15px rgba(55, 66, 250, 0.3); }
            50% { box-shadow: 0 8px 25px rgba(55, 66, 250, 0.5); }
        }
        
        .stats-card {
            background: linear-gradient(135deg, rgba(255,248,225,0.95) 0%, rgba(255,236,179,0.95) 100%);
            border-radius: 25px;
            padding: 2.5rem;
            text-align: center;
            margin-bottom: 1rem;
            border: 2px solid rgba(255, 193, 7, 0.3);
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            position: relative;
            overflow: hidden;
            backdrop-filter: blur(15px);
            box-shadow: 0 15px 35px rgba(255, 193, 7, 0.2);
        }
        
        .stats-card::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 100%;
            height: 100%;
            background: radial-gradient(circle, rgba(255, 193, 7, 0.2) 0%, transparent 70%);
            border-radius: 50%;
        }
        
        .stats-card:hover {
            transform: translateY(-8px) scale(1.05);
            box-shadow: 0 25px 50px rgba(255, 193, 7, 0.4);
        }
        
        .stats-card h6 {
            color: var(--warning-color);
            font-weight: 600;
            margin-bottom: 1rem;
            font-size: 1rem;
        }
        
        .stats-card h3 {
            font-size: 3rem;
            font-weight: 800;
            margin: 0.5rem 0;
            background: linear-gradient(135deg, var(--warning-color), #e67e22);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            text-shadow: 0 4px 8px rgba(243, 156, 18, 0.3);
        }
        .expense-type-badge {
            background: linear-gradient(135deg, #e91e63 0%, #f06292 100%);
            color: white;
            padding: 10px 18px;
            border-radius: 25px;
            font-size: 0.8rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
            box-shadow: 0 6px 20px rgba(233, 30, 99, 0.4);
            border: 2px solid rgba(255, 255, 255, 0.2);
            display: inline-block;
            animation: pulse-badge 3s infinite;
            position: relative;
            overflow: hidden;
        }
        
        .expense-type-badge::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.3), transparent);
            transition: left 0.6s;
        }
        
        .request-row:hover .expense-type-badge::before {
            left: 100%;
        }
        
        @keyframes pulse-badge {
            0%, 100% { 
                box-shadow: 0 6px 20px rgba(233, 30, 99, 0.4);
                transform: scale(1);
            }
            50% { 
                box-shadow: 0 10px 30px rgba(233, 30, 99, 0.6);
                transform: scale(1.05);
            }
        }
        
        .amount-display {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 12px 20px;
            border-radius: 25px;
            font-size: 1.1rem;
            font-weight: 800;
            text-align: center;
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.4);
            border: 3px solid rgba(255, 255, 255, 0.2);
            position: relative;
            overflow: hidden;
            animation: amount-pulse 3s infinite;
            transform-style: preserve-3d;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        .amount-display::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.4), transparent);
            transition: left 0.8s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        .amount-display:hover {
            transform: translateY(-5px) scale(1.05);
            box-shadow: 0 15px 40px rgba(102, 126, 234, 0.6);
        }
        
        .amount-display:hover::before {
            left: 100%;
        }
        
        @keyframes amount-pulse {
            0%, 100% { 
                box-shadow: 0 8px 25px rgba(102, 126, 234, 0.4);
                border-color: rgba(255, 255, 255, 0.2);
            }
            50% { 
                box-shadow: 0 12px 35px rgba(102, 126, 234, 0.6);
                border-color: rgba(255, 255, 255, 0.4);
            }
        }
        
        .date-circle {
            width: 70px;
            height: 70px;
            background: linear-gradient(135deg, #ffeaa7 0%, #fdcb6e 100%);
            border-radius: 50%;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            margin: 0 auto;
            font-weight: 700;
            color: #2d3436;
            box-shadow: 0 10px 25px rgba(253, 203, 110, 0.4);
            border: 3px solid rgba(255, 255, 255, 0.3);
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            position: relative;
            overflow: hidden;
        }
        
        .date-circle::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.3), transparent);
            transition: left 0.6s ease;
        }
        
        .date-circle:hover {
            transform: scale(1.1) rotate(5deg);
            box-shadow: 0 20px 40px rgba(253, 203, 110, 0.6);
        }
        
        .date-circle:hover::before {
            left: 100%;
        }
        
        .date-day {
            font-size: 1.2rem;
            line-height: 1;
        }
        
        .date-month {
            font-size: 0.7rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            line-height: 1;
        }
            color: white;
            border-radius: 15px 15px 0 0;
            border: none;
        }
        
        .modal-content {
            border-radius: 20px;
            border: none;
            box-shadow: 0 20px 40px rgba(0,0,0,0.15);
        }
        
        .nav-pills .nav-link {
            border-radius: 25px;
            padding: 12px 25px;
            font-weight: 600;
            margin: 0 5px;
            transition: all 0.3s ease;
            border: 2px solid transparent;
        }
        
        .nav-pills .nav-link.active {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
            transform: translateY(-2px);
        }
        
        .nav-pills .nav-link:not(.active) {
            background: rgba(255,255,255,0.8);
            color: #6c757d;
            border-color: rgba(102, 126, 234, 0.2);
        }
        
        .nav-pills .nav-link:not(.active):hover {
            background: rgba(102, 126, 234, 0.1);
            color: #667eea;
            transform: translateY(-1px);
        }
        
        .alert {
            border-radius: 15px;
            border: none;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
        }
        
        .alert-info {
            background: linear-gradient(135deg, #e3f2fd 0%, #bbdefb 100%);
            color: #1565c0;
            border-left: 5px solid #2196f3;
        }
        
        .badge {
            padding: 8px 16px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 0.8rem;
        }
        
        .badge.bg-secondary {
            background: linear-gradient(135deg, #6c757d 0%, #495057 100%) !important;
        }
        
        .expense-type-badge {
            background: linear-gradient(135deg, #e91e63 0%, #f06292 100%);
            color: white;
            padding: 10px 18px;
            border-radius: 25px;
            font-size: 0.8rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
            box-shadow: 0 6px 20px rgba(233, 30, 99, 0.4);
            border: 2px solid rgba(255, 255, 255, 0.2);
            display: inline-block;
            animation: pulse-badge 3s infinite;
            position: relative;
            overflow: hidden;
        }
        
        .expense-type-badge::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.3), transparent);
            transition: left 0.6s;
        }
        
        .request-row:hover .expense-type-badge::before {
            left: 100%;
        }
        
        @keyframes pulse-badge {
            0%, 100% { 
                box-shadow: 0 6px 20px rgba(233, 30, 99, 0.4);
                transform: scale(1);
            }
            50% { 
                box-shadow: 0 10px 30px rgba(233, 30, 99, 0.6);
                transform: scale(1.05);
            }
        }
        
        /* Animations */
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .card {
            animation: fadeInUp 0.6s ease-out;
        }
        
        .card:nth-child(2) { animation-delay: 0.1s; }
        .card:nth-child(3) { animation-delay: 0.2s; }
        .card:nth-child(4) { animation-delay: 0.3s; }
        
        /* Custom scrollbar */
        .table-responsive::-webkit-scrollbar {
            height: 8px;
        }
        
        .table-responsive::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 10px;
        }
        
        .table-responsive::-webkit-scrollbar-thumb {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 10px;
        }
        
        .table-responsive::-webkit-scrollbar-thumb:hover {
            background: linear-gradient(135deg, #5a67d8 0%, #6b46c1 100%);
        }
        
        /* New styles for enhanced UI */
        .avatar-circle {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            font-size: 1.1rem;
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.4);
            border: 3px solid rgba(255, 255, 255, 0.3);
            transition: all 0.3s ease;
        }
        
        .avatar-circle:hover {
            transform: scale(1.1);
            box-shadow: 0 10px 30px rgba(102, 126, 234, 0.6);
        }
        
        .date-circle {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: linear-gradient(135deg, rgba(59, 130, 246, 0.1) 0%, rgba(147, 51, 234, 0.1) 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 1.3rem;
            color: var(--primary-color);
            margin: 0 auto 10px;
            border: 3px solid rgba(102, 126, 234, 0.3);
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.2);
            transition: all 0.3s ease;
        }
        
        .date-circle:hover {
            transform: scale(1.1);
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            color: white;
            border-color: rgba(255, 255, 255, 0.5);
        }
        
        .amount-container {
            text-align: right;
            padding: 10px;
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border-radius: 12px;
            border: 2px solid rgba(0, 184, 148, 0.2);
        }
        
        .request-row {
            transition: all 0.3s ease;
        }
        
        .request-row:hover {
            background: linear-gradient(135deg, #fff8e1 0%, #f3e5ab 100%);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .action-buttons .btn {
            min-width: 45px;
            height: 35px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .request-icon {
            opacity: 0.7;
            transition: all 0.3s ease;
        }
        
        .request-row:hover .request-icon {
            opacity: 1;
            transform: scale(1.1);
        }
        
        .requester-info, .request-details {
            padding: 8px 0;
        }
        
        .expense-type-badge {
            display: inline-block;
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0% { box-shadow: 0 0 0 0 rgba(233, 30, 99, 0.7); }
            70% { box-shadow: 0 0 0 10px rgba(233, 30, 99, 0); }
            100% { box-shadow: 0 0 0 0 rgba(233, 30, 99, 0); }
        }
        
        .detail-modal .modal-body {
            max-height: 70vh;
            overflow-y: auto;
        }
        
        .detail-item {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border-radius: 10px;
            padding: 1rem;
            margin-bottom: 1rem;
            border-left: 4px solid #667eea;
        }
        
        .detail-item h6 {
            color: #2d3436;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }
        
        .detail-item .value {
            color: #636e72;
            font-size: 0.95rem;
        }

        /* Detail Modal Styling */
        .detail-container {
            padding: 20px 0;
        }

        .detail-item {
            background: rgba(255, 255, 255, 0.9);
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
            border-left: 4px solid var(--primary-color);
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
            transition: all 0.3s ease;
        }

        .detail-item:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.12);
        }

        .detail-item h6 {
            color: var(--primary-color);
            margin-bottom: 15px;
            font-weight: 600;
            display: flex;
            align-items: center;
        }

        .detail-item .value {
            color: #495057;
            line-height: 1.6;
        }

        .voucher-no {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            font-weight: bold;
            font-size: 1.1em;
        }

        .amount-container {
            text-align: right;
        }

        .amount {
            font-size: 1.2em;
            font-weight: bold;
            color: var(--primary-color);
        }

        .amount-container small {
            display: block;
            font-size: 0.8em;
        }

        /* Status enhancements */
        .status-pending {
            background: linear-gradient(135deg, #ffeaa7, #fdcb6e);
            color: #e17055;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 0.85em;
            font-weight: 600;
        }

        .status-approved {
            background: linear-gradient(135deg, #a8e6cf, #81c784);
            color: #2e7d32;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 0.85em;
            font-weight: 600;
        }

        .status-rejected {
            background: linear-gradient(135deg, #ffb3b3, #ff8a80);
            color: #c62828;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 0.85em;
            font-weight: 600;
        }

        /* Modal enhancements */
        .modal-content {
            border: none;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 20px 60px rgba(0,0,0,0.15);
        }

        .modal-header {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            border: none;
            padding: 25px 30px;
        }

        .modal-body {
            padding: 30px;
            max-height: 70vh;
            overflow-y: auto;
        }

        .modal-footer {
            border: none;
            padding: 20px 30px;
            background: rgba(248,249,250,0.8);
        }

        /* Custom scrollbar for modal */
        .modal-body::-webkit-scrollbar {
            width: 8px;
        }

        .modal-body::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 10px;
        }

        .modal-body::-webkit-scrollbar-thumb {
            background: linear-gradient(var(--primary-color), var(--secondary-color));
            border-radius: 10px;
        }

        /* Table enhancements in modal */
        .detail-item .table {
            margin-bottom: 0;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
        }

        .detail-item .table th {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            border: none;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.85em;
            letter-spacing: 0.5px;
        }

        .detail-item .table td {
            border-color: rgba(0,0,0,0.05);
            vertical-align: middle;
            padding: 15px;
        }

        .detail-item .table-hover tbody tr:hover {
            background-color: rgba(74, 144, 226, 0.05);
            transform: scale(1.002);
            transition: all 0.2s ease;
        }

        .detail-item .table tfoot {
            background: linear-gradient(135deg, rgba(74, 144, 226, 0.1), rgba(156, 39, 176, 0.1));
        }

        .detail-item .table tfoot td {
            font-weight: 600;
            color: var(--primary-color);
            border-top: 2px solid var(--primary-color);
        }

        /* Loading animation */
        .spinner-border {
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        /* Row click effect */
        .request-row {
            transition: all 0.3s ease;
            cursor: pointer;
        }

        .request-row:hover {
            background: linear-gradient(135deg, rgba(74, 144, 226, 0.05), rgba(156, 39, 176, 0.05));
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
        }

        /* Alert styling */
        .alert {
            border: none;
            border-radius: 20px;
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
            backdrop-filter: blur(10px);
            position: relative;
            overflow: hidden;
            animation: slideInUp 0.8s ease;
        }
        
        .alert::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.3), transparent);
            transition: left 0.8s ease;
        }
        
        .alert:hover::before {
            left: 100%;
        }

        .alert-danger {
            background: linear-gradient(135deg, rgba(255, 235, 238, 0.95), rgba(255, 205, 210, 0.95));
            color: #c62828;
            border-left: 5px solid #f44336;
            box-shadow: 0 8px 25px rgba(244, 67, 54, 0.2);
        }

        .alert-success {
            background: linear-gradient(135deg, rgba(232, 245, 232, 0.95), rgba(200, 230, 201, 0.95));
            color: #2e7d32;
            border-left: 5px solid #4caf50;
            box-shadow: 0 8px 25px rgba(76, 175, 80, 0.2);
        }
        
        .alert-info {
            background: linear-gradient(135deg, rgba(227, 242, 253, 0.95), rgba(187, 222, 251, 0.95));
            color: #1565c0;
            border-left: 5px solid #2196f3;
            box-shadow: 0 8px 25px rgba(33, 150, 243, 0.2);
        }
        
        .alert-warning {
            background: linear-gradient(135deg, rgba(255, 248, 225, 0.95), rgba(255, 236, 179, 0.95));
            color: #ef6c00;
            border-left: 5px solid #ff9800;
            box-shadow: 0 8px 25px rgba(255, 152, 0, 0.2);
        }

        /* Responsive design */
        @media (max-width: 768px) {
            .detail-item {
                padding: 15px;
                margin-bottom: 15px;
            }
            
            .modal-body {
                padding: 20px 15px;
            }
            
            .amount {
                font-size: 1.1em;
            }
        }

        /* Animation keyframes */
        @keyframes slideInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes fadeInScale {
            from {
                opacity: 0;
                transform: scale(0.9);
            }
            to {
                opacity: 1;
                transform: scale(1);
            }
        }
        
        @keyframes shimmer {
            0% {
                background-position: -200px 0;
            }
            100% {
                background-position: calc(200px + 100%) 0;
            }
        }
        
        @keyframes float {
            0%, 100% {
                transform: translateY(0px);
            }
            50% {
                transform: translateY(-10px);
            }
        }
        
        @keyframes bounceIn {
            0%, 20%, 40%, 60%, 80%, 100% {
                animation-timing-function: cubic-bezier(0.215, 0.610, 0.355, 1.000);
            }
            0% {
                opacity: 0;
                transform: scale3d(.3, .3, .3);
            }
            20% {
                transform: scale3d(1.1, 1.1, 1.1);
            }
            40% {
                transform: scale3d(.9, .9, .9);
            }
            60% {
                opacity: 1;
                transform: scale3d(1.03, 1.03, 1.03);
            }
            80% {
                transform: scale3d(.97, .97, .97);
            }
            100% {
                opacity: 1;
                transform: scale3d(1, 1, 1);
            }
        }

        .detail-item {
            animation: slideInUp 0.6s ease;
        }

        .modal-content {
            animation: bounceIn 0.6s ease;
        }
        
        .card {
            animation: fadeInScale 0.8s ease;
        }
        
        /* Loading states */
        .loading-shimmer {
            background: linear-gradient(90deg, #f0f0f0 25%, #e0e0e0 50%, #f0f0f0 75%);
            background-size: 200px 100%;
            animation: shimmer 2s infinite linear;
        }
        
        /* Enhanced hover effects */
        .nav-pills .nav-link {
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            border-radius: 15px;
            padding: 12px 20px;
            font-weight: 600;
            position: relative;
            overflow: hidden;
        }
        
        .nav-pills .nav-link::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.3), transparent);
            transition: left 0.6s ease;
        }
        
        .nav-pills .nav-link:hover::before {
            left: 100%;
        }
        
        .nav-pills .nav-link.active {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.3);
            transform: translateY(-2px);
        }
        
        /* Improved table responsiveness */
        @media (max-width: 1200px) {
            .amount-display {
                padding: 8px 12px;
                font-size: 0.9rem;
            }
            
            .date-circle {
                width: 50px !important;
                height: 50px !important;
                font-size: 0.7rem !important;
            }
            
            .avatar-circle {
                width: 60px;
                height: 60px;
                font-size: 1.5rem;
            }
        }
        
        /* Enhanced table cell styling */
        .table td {
            vertical-align: middle;
            border-bottom: 1px solid rgba(0,0,0,0.08);
            padding: 1rem 0.8rem;
            font-size: 0.95rem;
        }
        
        .table th {
            vertical-align: middle;
            padding: 1.2rem 0.8rem;
            white-space: nowrap;
            font-size: 0.9rem;
        }
        
        /* Compact table styling for better space utilization */
        .voucher-no {
            font-family: 'Courier New', monospace;
            font-weight: bold;
            color: var(--primary-color);
        }
        
        .request-icon {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 30px;
            height: 30px;
            border-radius: 8px;
            background: rgba(102, 126, 234, 0.1);
        }
        
        /* Responsive badge sizing */
        .expense-type-badge {
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: 100px;
            display: inline-block;
        }
        
        /* Mobile responsiveness */
        @media (max-width: 992px) {
            .container {
                padding: 10px;
            }
            
            .table th, .table td {
                padding: 0.6rem 0.4rem;
                font-size: 0.85rem;
            }
            
            .avatar-circle {
                width: 30px !important;
                height: 30px !important;
                font-size: 0.7rem !important;
            }
            
            .date-circle {
                width: 35px !important;
                height: 35px !important;
                font-size: 0.6rem !important;
            }
            
            .amount-display {
                padding: 6px 10px !important;
                font-size: 0.8rem !important;
            }
            
            .expense-type-badge {
                padding: 4px 8px !important;
                font-size: 0.7rem !important;
                max-width: 80px;
            }
            
            .btn-sm {
                padding: 4px 6px !important;
                font-size: 0.7rem !important;
            }
            
            .status-approved, .status-rejected, .status-pending {
                font-size: 0.7rem !important;
                padding: 4px 8px !important;
            }
        }
        
        @media (max-width: 768px) {
            .table-responsive {
                border-radius: 15px;
                box-shadow: 0 8px 25px rgba(0,0,0,0.1);
            }
            
            .table th {
                font-size: 0.75rem;
                padding: 0.8rem 0.3rem;
            }
            
            .table td {
                font-size: 0.8rem;
                padding: 0.7rem 0.3rem;
            }
            
            .voucher-no {
                font-size: 0.8rem !important;
            }
            
            .avatar-circle {
                width: 25px !important;
                height: 25px !important;
                font-size: 0.6rem !important;
            }
            
            .date-circle {
                width: 30px !important;
                height: 30px !important;
                font-size: 0.55rem !important;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Beautiful Header Section -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="text-center py-4">
                    <h1 class="display-4 fw-bold text-white mb-2" style="text-shadow: 0 4px 8px rgba(0,0,0,0.3);">
                        <i class="bi bi-clipboard-check me-3"></i>
                        ระบบอนุมัติคำขอเบิกจ่าย
                    </h1>
                    <p class="lead text-white-50 mb-0" style="text-shadow: 0 2px 4px rgba(0,0,0,0.2);">
                        จัดการและอนุมัติคำขอเบิกจ่ายอย่างมีประสิทธิภาพ
                    </p>
                    <div class="mt-3">
                        <span class="badge bg-light text-dark px-3 py-2 rounded-pill">
                            <i class="bi bi-calendar-date me-1"></i>
                            <?php echo date('d/m/Y H:i'); ?>
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <!-- User Info และสถิติ -->
        <div class="row">
            <div class="col-md-8">
                <div class="user-info">
                    <div class="row">
                        <div class="col-md-3 text-center">
                            <div class="avatar-circle">
                                <?php 
                                $user_name = $_SESSION['employee_name'] ?? 'ผู้ทดสอบระบบ';
                                echo strtoupper(mb_substr($user_name, 0, 1, 'UTF-8')); 
                                ?>
                            </div>
                        </div>
                        <div class="col-md-9">
                            <h5 class="mb-3">
                                <i class="bi bi-person-check me-2"></i>
                                ข้อมูลผู้อนุมัติ
                            </h5>
                            <div class="row">
                                <div class="col-md-6">
                                    <p class="mb-2"><strong><i class="bi bi-person me-2"></i>ชื่อ:</strong> <?php echo htmlspecialchars($_SESSION['employee_name'] ?? 'ผู้ทดสอบระบบ'); ?></p>
                                    <p class="mb-2"><strong><i class="bi bi-credit-card me-2"></i>รหัสพนักงาน:</strong> <?php echo htmlspecialchars($current_user_id); ?></p>
                                </div>
                                <div class="col-md-6">
                                    <p class="mb-2">
                                        <strong><i class="bi bi-shield-check me-2"></i>ระดับการอนุมัติ:</strong> 
                                        <span class="approval-level">ระดับ <?php echo $current_user_approval_level ?? 'ไม่ระบุ'; ?></span>
                                    </p>
                                    <p class="mb-0">
                                        <strong><i class="bi bi-list-check me-2"></i>คำขอรอการอนุมัติ:</strong> 
                                        <span class="text-warning fw-bold"><?php echo count($pending_requests); ?> รายการ</span>
                                    </p>
                                </div>
                            </div>
                            <div class="mt-3 pt-2 border-top">
                                <small class="text-muted">
                                    <strong><i class="bi bi-database me-1"></i>สถานะฐานข้อมูล:</strong> 
                                    <?php echo $database_available ? '<span class="text-success">✅ เชื่อมต่อแล้ว</span>' : '<span class="text-danger">❌ ไม่เชื่อมต่อ</span>'; ?>
                                </small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stats-card">
                    <h6><i class="bi bi-clock-history me-2"></i>รอการอนุมัติ</h6>
                    <h3 class="text-warning"><?php echo count($pending_requests); ?></h3>
                    <small>รายการ</small>
                </div>
            </div>
        </div>

        <?php if (!empty($message)): ?>
            <div class="alert alert-info alert-dismissible fade show" role="alert">
                <i class="bi bi-info-circle me-2"></i>
                <?php echo htmlspecialchars($message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if (!$database_available): ?>
            <div class="alert alert-warning alert-dismissible fade show" role="alert">
                <i class="bi bi-exclamation-triangle me-2"></i>
                <strong>โหมดออฟไลน์:</strong> ไม่สามารถเชื่อมต่อฐานข้อมูลได้ในขณะนี้ 
                กำลังแสดงข้อมูลตัวอย่างเพื่อการทดสอบ
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Tabs -->
        <ul class="nav nav-pills mb-3" id="approvalTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="pending-tab" data-bs-toggle="pill" data-bs-target="#pending" type="button" role="tab">
                    <i class="bi bi-clock me-2"></i>รอการอนุมัติ (<?php echo count($pending_requests); ?>)
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="processed-tab" data-bs-toggle="pill" data-bs-target="#processed" type="button" role="tab">
                    <i class="bi bi-check-circle me-2"></i>ดำเนินการแล้ว (<?php echo count($processed_requests); ?>)
                </button>
            </li>
        </ul>

        <div class="tab-content" id="approvalTabsContent">
            <!-- Tab: รอการอนุมัติ -->
            <div class="tab-pane fade show active" id="pending" role="tabpanel">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="bi bi-list-check me-2"></i>
                            คำขอเบิกจ่ายที่รอการอนุมัติ
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($pending_requests)): ?>
                            <div class="text-center py-5">
                                <i class="bi bi-inbox display-4 text-muted"></i>
                                <h5 class="text-muted mt-3">ไม่มีคำขอที่รอการอนุมัติ</h5>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th style="width: 15%;">เลขที่ใบเบิก</th>
                                            <th style="width: 18%;">ผู้ขอเบิก</th>
                                            <th style="width: 25%;">รายละเอียด</th>
                                            <th style="width: 12%;">ประเภท</th>
                                            <th style="width: 12%;" class="text-center">จำนวนเงิน</th>
                                            <th style="width: 10%;" class="text-center">วันที่สร้าง</th>
                                            <th style="width: 8%;" class="text-center">การดำเนินการ</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($pending_requests as $request): ?>
                                            <tr data-request-id="<?php echo $request['id']; ?>" class="request-row">
                                                <td style="width: 15%;">
                                                    <div class="d-flex align-items-center">
                                                        <div class="request-icon me-2">
                                                            <i class="bi bi-receipt text-primary fs-5"></i>
                                                        </div>
                                                        <div>
                                                            <div class="voucher-no fw-bold text-primary" style="font-size: 0.95rem;"><?php echo htmlspecialchars($request['voucher_no']); ?></div>
                                                            <small class="text-muted" style="font-size: 0.8rem;">ID: <?php echo $request['id']; ?></small>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td style="width: 18%;">
                                                    <div class="requester-info">
                                                        <div class="d-flex align-items-center mb-1">
                                                            <div class="avatar-circle me-2" style="width: 40px; height: 40px; font-size: 0.9rem;">
                                                                <?php echo strtoupper(substr($request['requester_name'] ?? 'N', 0, 1)); ?>
                                                            </div>
                                                            <div>
                                                                <div class="fw-bold text-dark" style="font-size: 0.95rem;"><?php echo htmlspecialchars($request['requester_name'] ?? 'ไม่ระบุ'); ?></div>
                                                                <small class="text-muted d-block" style="font-size: 0.8rem;">
                                                                    <i class="bi bi-hash me-1"></i><?php echo htmlspecialchars($request['requester_id'] ?? ''); ?>
                                                                </small>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td style="width: 25%;">
                                                    <div class="request-details">
                                                        <div class="fw-bold text-dark mb-1" style="font-size: 0.95rem;"><?php echo htmlspecialchars(mb_substr($request['pay_to'], 0, 40, 'UTF-8')); ?><?php echo mb_strlen($request['pay_to'], 'UTF-8') > 40 ? '...' : ''; ?></div>
                                                        <div class="d-flex flex-wrap gap-1 mb-1">
                                                            <span class="badge bg-light text-dark" style="font-size: 0.75rem;">
                                                                <i class="bi bi-building me-1"></i><?php echo htmlspecialchars(mb_substr($request['department'], 0, 15, 'UTF-8')); ?>
                                                            </span>
                                                            <span class="badge bg-info" style="font-size: 0.75rem;">
                                                                <i class="bi bi-list-ul me-1"></i><?php echo $request['items_count']; ?> รายการ
                                                            </span>
                                                        </div>
                                                        <small class="text-muted" style="font-size: 0.8rem;">
                                                            <i class="bi bi-card-text me-1"></i>
                                                            <?php echo htmlspecialchars(mb_substr($request['description'] ?? '', 0, 35, 'UTF-8')) . (mb_strlen($request['description'] ?? '', 'UTF-8') > 35 ? '...' : ''); ?>
                                                        </small>
                                                    </div>
                                                </td>
                                                <td style="width: 12%;" class="text-center">
                                                    <span class="expense-type-badge" style="font-size: 0.8rem; padding: 8px 12px;">
                                                        <?php 
                                                        $type_name = $expense_types[$request['expense_type']] ?? $request['expense_type'];
                                                        echo mb_substr($type_name, 0, 8, 'UTF-8') . (mb_strlen($type_name, 'UTF-8') > 8 ? '..' : '');
                                                        ?>
                                                    </span>
                                                </td>
                                                <td style="width: 12%;" class="text-center">
                                                    <div class="amount-display" style="padding: 10px 15px; font-size: 1rem;">
                                                        ฿<?php echo number_format($request['total_amount'], 0); ?>
                                                    </div>
                                                </td>
                                                <td style="width: 10%;" class="text-center">
                                                    <div class="date-info">
                                                        <div class="date-circle" style="width: 50px; height: 50px; font-size: 0.8rem;">
                                                            <div class="date-day">
                                                                <?php 
                                                                $date = $request['created_at'];
                                                                if ($date) {
                                                                    $timestamp = strtotime($date);
                                                                    echo date('d', $timestamp);
                                                                }
                                                                ?>
                                                            </div>
                                                            <div class="date-month">
                                                                <?php 
                                                                if ($date) {
                                                                    echo date('M', $timestamp);
                                                                }
                                                                ?>
                                                            </div>
                                                        </div>
                                                        <small class="text-muted mt-1 d-block" style="font-size: 0.75rem;">
                                                            <?php 
                                                            if ($date) {
                                                                echo date('H:i', $timestamp);
                                                            }
                                                            ?>
                                                        </small>
                                                    </div>
                                                </td>
                                                <td style="width: 8%;" class="text-center">
                                                    <div class="action-buttons d-flex flex-column gap-1">
                                                        <button class="btn btn-detail btn-sm" style="padding: 6px 10px; font-size: 0.8rem;" onclick="viewRequestDetails(<?php echo $request['id']; ?>)">
                                                            <i class="bi bi-eye"></i>
                                                        </button>
                                                        <div class="d-flex gap-1">
                                                            <button class="btn btn-approve btn-sm" style="padding: 6px 8px; font-size: 0.8rem;" onclick="showApprovalModal(<?php echo $request['id']; ?>, 'approve', '<?php echo htmlspecialchars($request['voucher_no']); ?>')">
                                                                <i class="bi bi-check-lg"></i>
                                                            </button>
                                                            <button class="btn btn-reject btn-sm" style="padding: 6px 8px; font-size: 0.8rem;" onclick="showApprovalModal(<?php echo $request['id']; ?>, 'reject', '<?php echo htmlspecialchars($request['voucher_no']); ?>')">
                                                                <i class="bi bi-x-lg"></i>
                                                            </button>
                                                        </div>
                                                    </div>
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

            <!-- Tab: ดำเนินการแล้ว -->
            <div class="tab-pane fade" id="processed" role="tabpanel">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="bi bi-check2-all me-2"></i>
                            คำขอที่ดำเนินการแล้ว (30 วันล่าสุด)
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($processed_requests)): ?>
                            <div class="text-center py-5">
                                <i class="bi bi-archive display-4 text-muted"></i>
                                <h5 class="text-muted mt-3">ไม่มีรายการที่ดำเนินการแล้ว</h5>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th style="width: 15%;">เลขที่ใบเบิก</th>
                                            <th style="width: 20%;">ผู้ขอเบิก</th>
                                            <th style="width: 20%;">ชื่อผู้รับเงิน</th>
                                            <th style="width: 12%;" class="text-center">จำนวนเงิน</th>
                                            <th style="width: 12%;" class="text-center">สถานะที่อนุมัติ</th>
                                            <th style="width: 13%;" class="text-center">สถานะรวม</th>
                                            <th style="width: 8%;" class="text-center">วันที่อนุมัติ</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($processed_requests as $request): ?>
                                            <tr class="request-row">
                                                <td style="width: 15%;">
                                                    <div class="d-flex align-items-center">
                                                        <div class="request-icon me-2">
                                                            <i class="bi bi-receipt text-success fs-5"></i>
                                                        </div>
                                                        <div>
                                                            <div class="voucher-no fw-bold text-success" style="font-size: 0.95rem;"><?php echo htmlspecialchars($request['voucher_no']); ?></div>
                                                            <small class="text-muted" style="font-size: 0.8rem;">ID: <?php echo $request['id']; ?></small>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td style="width: 20%;">
                                                    <div class="requester-info">
                                                        <div class="d-flex align-items-center">
                                                            <div class="avatar-circle me-2" style="width: 40px; height: 40px; font-size: 0.9rem;">
                                                                <?php echo strtoupper(substr($request['requester_name'] ?? 'N', 0, 1)); ?>
                                                            </div>
                                                            <div class="fw-bold text-dark" style="font-size: 0.95rem;"><?php echo htmlspecialchars($request['requester_name'] ?? 'ไม่ระบุ'); ?></div>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td style="width: 20%;">
                                                    <div class="fw-bold text-dark" style="font-size: 0.95rem;">
                                                        <?php echo htmlspecialchars(mb_substr($request['pay_to'], 0, 25, 'UTF-8')); ?><?php echo mb_strlen($request['pay_to'], 'UTF-8') > 25 ? '...' : ''; ?>
                                                    </div>
                                                </td>
                                                <td style="width: 12%;" class="text-center">
                                                    <div class="amount-display" style="padding: 8px 14px; font-size: 0.9rem;">
                                                        ฿<?php echo number_format($request['total_amount'], 0); ?>
                                                    </div>
                                                </td>
                                                <td style="width: 12%;" class="text-center">
                                                    <?php if ($request['my_status'] === 'Approved'): ?>
                                                        <span class="status-approved" style="font-size: 0.8rem; padding: 6px 10px;">
                                                            <i class="bi bi-check-circle me-1"></i>อนุมัติ
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="status-rejected" style="font-size: 0.8rem; padding: 6px 10px;">
                                                            <i class="bi bi-x-circle me-1"></i>ปฏิเสธ
                                                        </span>
                                                    <?php endif; ?>
                                                </td>
                                                <td style="width: 13%;" class="text-center">
                                                    <?php if ($request['overall_status'] === 'Approved'): ?>
                                                        <span class="status-approved" style="font-size: 0.8rem; padding: 6px 10px;">
                                                            <i class="bi bi-check-circle-fill me-1"></i>สมบูรณ์
                                                        </span>
                                                    <?php elseif ($request['overall_status'] === 'Rejected'): ?>
                                                        <span class="status-rejected" style="font-size: 0.8rem; padding: 6px 10px;">
                                                            <i class="bi bi-x-circle-fill me-1"></i>ปฏิเสธ
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="status-pending" style="font-size: 0.8rem; padding: 6px 10px;">
                                                            <i class="bi bi-clock-fill me-1"></i>รอดำเนินการ
                                                        </span>
                                                    <?php endif; ?>
                                                </td>
                                                <td style="width: 8%;" class="text-center">
                                                    <div class="date-info">
                                                        <?php if ($request['my_approved_date']): ?>
                                                            <?php $timestamp = strtotime($request['my_approved_date']); ?>
                                                            <div class="date-circle" style="width: 45px; height: 45px; font-size: 0.7rem;">
                                                                <div class="date-day"><?php echo date('d', $timestamp); ?></div>
                                                                <div class="date-month"><?php echo date('M', $timestamp); ?></div>
                                                            </div>
                                                            <small class="text-info d-block mt-1" style="font-size: 0.7rem;">
                                                                <i class="bi bi-clock me-1"></i><?php echo date('H:i', $timestamp); ?>
                                                            </small>
                                                        <?php else: ?>
                                                            <span class="text-muted">-</span>
                                                        <?php endif; ?>
                                                    </div>
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
        </div>
    </div>

    <!-- Modal: รายละเอียดคำขอ -->
    <div class="modal fade" id="detailModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="bi bi-file-text me-2"></i>รายละเอียดคำขอเบิกจ่าย
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="detailContent">
                    <!-- Content will be loaded here -->
                </div>
            </div>
        </div>
    </div>

    <!-- Modal: อนุมัติ/ปฏิเสธ -->
    <div class="modal fade" id="approvalModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="approvalModalTitle">
                        <i class="bi bi-check-circle me-2"></i>ยืนยันการอนุมัติ
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" id="approvalForm">
                    <div class="modal-body">
                        <input type="hidden" name="request_id" id="modalRequestId">
                        <input type="hidden" name="action" id="modalAction">
                        <input type="hidden" name="approval_level" value="<?php echo $current_user_approval_level; ?>">
                        
                        <div class="alert alert-info">
                            <strong>เลขที่ใบเบิก:</strong> <span id="modalVoucherNo"></span><br>
                            <strong>การดำเนินการ:</strong> <span id="modalActionText"></span>
                        </div>
                        
                        <div class="mb-3">
                            <label for="comments" class="form-label">หมายเหตุ:</label>
                            <textarea class="form-control" name="comments" id="comments" rows="3" placeholder="ระบุหมายเหตุ (ถ้ามี)"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                        <button type="submit" class="btn" id="modalSubmitBtn">ยืนยัน</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function viewRequestDetails(requestId) {
            console.log('Viewing details for request ID:', requestId);
            
            // แสดง loading สวยงาม
            document.getElementById('detailContent').innerHTML = `
                <div class="text-center py-5">
                    <div class="spinner-border text-primary" style="width: 3rem; height: 3rem;" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <h5 class="mt-3 text-muted">กำลังโหลดรายละเอียด...</h5>
                    <small class="text-muted">Request ID: ${requestId}</small>
                </div>
            `;
            
            // เปิด modal
            const detailModal = new bootstrap.Modal(document.getElementById('detailModal'));
            detailModal.show();
            
            // ดึงข้อมูลรายละเอียดจาก API
            const url1 = `get_expense_details.php?request_id=${requestId}`;
            const url2 = `view_expense_data.php?request_id=${requestId}`;
            
            console.log('Fetching from URLs:', url1, url2);
            
            Promise.all([
                fetch(url1).then(response => {
                    console.log('Details response status:', response.status);
                    if (!response.ok) {
                        throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                    }
                    return response.json();
                }),
                fetch(url2).then(response => {
                    console.log('Data response status:', response.status);
                    if (!response.ok) {
                        throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                    }
                    return response.json();
                })
            ])
            .then(data => {
                console.log('Received data:', data);
                const [detailData, requestData] = data;
                
                console.log('Detail data:', detailData);
                console.log('Request data:', requestData);
                
                if (detailData && detailData.success !== false) {
                    let html = `
                        <div class="detail-container">
                            <div class="row mb-4">
                                <div class="col-md-6">
                                    <div class="detail-item">
                                        <h6><i class="bi bi-receipt me-2"></i>ข้อมูลการขอเบิก</h6>
                                        <div class="value">
                                            <strong>เลขที่ใบเบิก:</strong> <span class="voucher-no">${requestData.voucher_no || 'N/A'}</span><br>
                                            <strong>ผู้ขอเบิก:</strong> ${requestData.requester_name || 'ไม่ระบุ'}<br>
                                            <strong>แผนก:</strong> ${requestData.department || 'ไม่ระบุ'}<br>
                                            <strong>จ่ายให้:</strong> ${requestData.pay_to || 'ไม่ระบุ'}
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="detail-item">
                                        <h6><i class="bi bi-clock me-2"></i>ข้อมูลเวลา</h6>
                                        <div class="value">
                                            <strong>วันที่สร้าง:</strong> ${new Date(requestData.created_at || Date.now()).toLocaleDateString('th-TH')}<br>
                                            <strong>เวลา:</strong> ${new Date(requestData.created_at || Date.now()).toLocaleTimeString('th-TH')}<br>
                                            <strong>สถานะ:</strong> <span class="status-pending">รอการอนุมัติ</span><br>
                                            <strong>ระดับปัจจุบัน:</strong> ระดับ ${requestData.current_approval_level || '1'}
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="detail-item">
                                <h6><i class="bi bi-list-ul me-2"></i>รายการค่าใช้จ่าย</h6>
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead class="table-light">
                                            <tr>
                                                <th width="5%">#</th>
                                                <th width="50%">รายการ</th>
                                                <th width="20%" class="text-end">จำนวนเงิน</th>
                                                <th width="25%">หมายเหตุ</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                    `;
                    
                    detailData.items.forEach((item, index) => {
                        html += `
                            <tr>
                                <td><span class="badge bg-primary">${index + 1}</span></td>
                                <td>
                                    <strong>${item.item_description}</strong>
                                    <br><small class="text-muted">รหัสรายการ: ${item.id || 'N/A'}</small>
                                </td>
                                <td class="text-end">
                                    <div class="amount-container">
                                        <div class="amount">${parseFloat(item.amount).toLocaleString()}</div>
                                        <small class="text-muted">บาท</small>
                                    </div>
                                </td>
                                <td>
                                    <small class="text-muted">${item.notes || '-'}</small>
                                </td>
                            </tr>
                        `;
                    });
                    
                    html += `
                                        </tbody>
                                        <tfoot class="table-info">
                                            <tr>
                                                <td colspan="2"><strong><i class="bi bi-calculator me-2"></i>รวมทั้งหมด</strong></td>
                                                <td class="text-end">
                                                    <div class="amount-container">
                                                        <div class="amount">${detailData.total.toLocaleString()}</div>
                                                        <small class="text-muted">บาท</small>
                                                    </div>
                                                </td>
                                                <td><strong>${detailData.items.length} รายการ</strong></td>
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>
                            </div>
                            
                            <div class="row mt-4">
                                <div class="col-md-6">
                                    <div class="detail-item">
                                        <h6><i class="bi bi-info-circle me-2"></i>ข้อมูลเพิ่มเติม</h6>
                                        <div class="value">
                                            <strong>ประเภทใบสำคัญ:</strong> ${requestData.voucher_type || 'ไม่ระบุ'}<br>
                                            <strong>ประเภทค่าใช้จ่าย:</strong> ${requestData.expense_type || 'ไม่ระบุ'}<br>
                                            <strong>คำอธิบาย:</strong> ${requestData.description || 'ไม่มี'}
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="detail-item">
                                        <h6><i class="bi bi-graph-up me-2"></i>สถิติ</h6>
                                        <div class="value">
                                            <strong>จำนวนรายการ:</strong> ${detailData.items.length} รายการ<br>
                                            <strong>ค่าเฉลี่ย:</strong> ${(detailData.total / detailData.items.length).toLocaleString()} บาท/รายการ<br>
                                            <strong>รายการที่แพงที่สุด:</strong> ${Math.max(...detailData.items.map(i => parseFloat(i.amount))).toLocaleString()} บาท
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    `;
                    
                    document.getElementById('detailContent').innerHTML = html;
                } else {
                    console.log('Detail data error or not successful');
                    document.getElementById('detailContent').innerHTML = `
                        <div class="alert alert-warning">
                            <i class="bi bi-exclamation-triangle me-2"></i>
                            <strong>ไม่สามารถดึงข้อมูลรายละเอียดได้</strong><br>
                            <small>อาจเป็นเพราะข้อมูลยังไม่พร้อม หรือเกิดข้อผิดพลาดชั่วคราว</small><br>
                            <small class="text-muted">Request ID: ${requestId}</small><br>
                            <small class="text-muted">Error: ${detailData.message || 'ไม่ทราบสาเหตุ'}</small><br>
                            <div class="mt-3">
                                <button onclick="viewRequestDetails(${requestId})" class="btn btn-primary btn-sm">
                                    <i class="bi bi-arrow-clockwise me-1"></i>ลองใหม่
                                </button>
                                <button onclick="location.reload()" class="btn btn-secondary btn-sm">
                                    <i class="bi bi-arrow-clockwise me-1"></i>รีเฟรชหน้า
                                </button>
                            </div>
                        </div>
                    `;
                }
            })
            .catch(error => {
                console.error('Error details:', error);
                console.error('Request ID:', requestId);
                
                document.getElementById('detailContent').innerHTML = `
                    <div class="alert alert-danger">
                        <i class="bi bi-wifi-off me-2"></i>
                        <strong>เกิดข้อผิดพลาดในการเชื่อมต่อ</strong><br>
                        <small>ไม่สามารถเรียกดูรายละเอียดได้ในขณะนี้</small><br>
                        <small class="text-muted">Request ID: ${requestId}</small><br>
                        <small class="text-muted">Error: ${error.message || 'ไม่ทราบสาเหตุ'}</small><br>
                        <div class="mt-3">
                            <button onclick="viewRequestDetails(${requestId})" class="btn btn-primary btn-sm">
                                <i class="bi bi-arrow-clockwise me-1"></i>ลองใหม่
                            </button>
                        </div>
                    </div>
                `;
            });
        }

        // Alias for backward compatibility
        function viewDetails(requestId) {
            viewRequestDetails(requestId);
        }

        function showApprovalModal(requestId, action, voucherNo) {
            document.getElementById('modalRequestId').value = requestId;
            document.getElementById('modalAction').value = action;
            document.getElementById('modalVoucherNo').textContent = voucherNo;
            
            const modal = document.getElementById('approvalModal');
            const title = document.getElementById('approvalModalTitle');
            const actionText = document.getElementById('modalActionText');
            const submitBtn = document.getElementById('modalSubmitBtn');
            
            if (action === 'approve') {
                title.innerHTML = '<i class="bi bi-check-circle me-2"></i>ยืนยันการอนุมัติ';
                actionText.innerHTML = '<span class="status-approved">อนุมัติคำขอ</span>';
                submitBtn.textContent = 'อนุมัติ';
                submitBtn.className = 'btn btn-success';
            } else {
                title.innerHTML = '<i class="bi bi-x-circle me-2"></i>ยืนยันการปฏิเสธ';
                actionText.innerHTML = '<span class="status-rejected">ปฏิเสธคำขอ</span>';
                submitBtn.textContent = 'ปฏิเสธ';
                submitBtn.className = 'btn btn-danger';
            }
            
            new bootstrap.Modal(modal).show();
        }

        // Enhanced page interactions
        document.addEventListener('DOMContentLoaded', function() {
            // Add click animation to request rows
            document.querySelectorAll('.request-row').forEach(row => {
                row.addEventListener('click', function(e) {
                    if (!e.target.closest('.action-buttons')) {
                        const requestId = this.dataset.requestId;
                        if (requestId) {
                            viewRequestDetails(requestId);
                        }
                    }
                });
                
                // Add hover effect
                row.style.cursor = 'pointer';
            });
            
            // Add smooth scroll for tables
            document.querySelectorAll('.table-responsive').forEach(table => {
                table.style.scrollBehavior = 'smooth';
            });
        });

        // Auto refresh with visual indicator
        let refreshCounter = 30;
        const refreshInterval = setInterval(() => {
            if (document.visibilityState === 'visible') {
                refreshCounter--;
                
                // Update page title with countdown
                if (refreshCounter > 0) {
                    document.title = `(${refreshCounter}s) อนุมัติคำขอเบิกจ่าย - ระบบเบิกจ่าย`;
                } else {
                    document.title = 'อนุมัติคำขอเบิกจ่าย - ระบบเบิกจ่าย';
                    location.reload();
                }
            }
        }, 1000);

        // Pause auto-refresh when modal is open
        document.addEventListener('shown.bs.modal', function() {
            clearInterval(refreshInterval);
        });
    </script>
</body>
</html>
