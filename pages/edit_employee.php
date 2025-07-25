<?php
// เปิด error reporting สำหรับ debug
error_reporting(E_ALL);
ini_set('display_errors', 1);

// เริ่มต้น session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ตรวจสอบว่าผู้ใช้ล็อกอินแล้วหรือยัง
if (!isset($_SESSION['employee_id'])) {
    header("Location: ../index.php");
    exit;
}

include_once '../config.php';

// เชื่อมต่อฐานข้อมูล
$conn = dbConnect();
if (!$conn) {
    die("Database connection failed");
}

// ดึงข้อมูลพนักงาน
$sql = "SELECT first_name_th, last_name_th, email FROM employees WHERE employee_id = ?";
$params = array((string)$_SESSION['employee_id']);
$stmt = sqlsrv_query($conn, $sql, $params);

if ($stmt === false) {
    die("SQL error: " . print_r(sqlsrv_errors(), true));
}

$employee = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
sqlsrv_free_stmt($stmt);

// อัปเดตข้อมูลเมื่อส่งแบบฟอร์ม
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pws_user = !empty($_POST['pws_user']) ? trim($_POST['pws_user']) : null;
    $confirm_password = !empty($_POST['confirm_password']) ? trim($_POST['confirm_password']) : null;

    if ($pws_user && $confirm_password) {
        // ตรวจสอบว่ารหัสผ่านตรงกัน
        if ($pws_user === $confirm_password) {
            // ตรวจสอบความยาวรหัสผ่าน
            if (strlen($pws_user) >= 4) {
                $updateSql = "UPDATE employees SET pws_user = ? WHERE employee_id = ?";
                // แปลง employee_id เป็น string เพื่อความแน่ใจ
                $updateParams = array($pws_user, (string)$_SESSION['employee_id']);
                $updateStmt = sqlsrv_query($conn, $updateSql, $updateParams);

                if ($updateStmt !== false) {
                    // ตรวจสอบว่า update สำเร็จจริงหรือไม่
                    $rowsAffected = sqlsrv_rows_affected($updateStmt);
                    sqlsrv_free_stmt($updateStmt);
                    
                    if ($rowsAffected > 0) {
                        // Redirect with success message
                        header("Location: employee_profile.php?success=1");
                        exit();
                    } else {
                        $error_message = "ไม่พบข้อมูลพนักงานที่ต้องการอัพเดต";
                    }
                } else {
                    $errors = sqlsrv_errors();
                    $error_message = "เกิดข้อผิดพลาดในการอัปเดตข้อมูล: ";
                    if ($errors) {
                        foreach ($errors as $error) {
                            $error_message .= $error['message'] . " ";
                        }
                    }
                }
            } else {
                $error_message = "รหัสผ่านต้องมีอย่างน้อย 4 ตัวอักษร";
            }
        } else {
            $error_message = "รหัสผ่านไม่ตรงกัน กรุณาตรวจสอบอีกครั้ง";
        }
    } else {
        $error_message = "กรุณากรอกรหัสผ่านและยืนยันรหัสผ่าน";
    }
}

// SQLSRV connections are closed automatically when the script ends

// Include header และ menu หลังจากการประมวลผลแล้ว
include_once '../includes/header.php';
include_once '../includes/menu.php';
?>


<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- Bootstrap CSS จาก CDN -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <!-- ฟอนต์ Prompt -->
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@400;600&display=swap" rel="stylesheet">
    <title>แก้ไขข้อมูลพนักงาน - Web Portal</title>
    <style>
        body, h1, h2, h3, h4, h5, h6, label, input, button, .form-label, .form-control, .card, .card-header, .card-title, .card-text {
            font-family: 'Prompt', sans-serif !important;
        }
        body {
            background: linear-gradient(135deg, #f8fafc 0%, #e0e7ef 100%);
            min-height: 100vh;
        }
        .card, .task-card {
            background: #fff;
            border-radius: 1.25rem;
            box-shadow: 0 4px 24px 0 rgba(80, 112, 255, 0.07);
            border: none;
        }
        .card-header {
            background: #f3f4f6;
            color: #222;
            border-bottom: 1px solid #e0e7ef;
            font-weight: 600;
            border-radius: 1.25rem 1.25rem 0 0;
        }
        .btn-primary {
            background: linear-gradient(90deg, #f8fafc 0%, #e0e7ef 100%);
            color: #333;
            border: 1px solid #e0e7ef;
        }
        .btn-primary:hover {
            background: #e0e7ef;
            color: #222;
        }
        .btn-warning {
            background: linear-gradient(90deg, #f8fafc 0%, #e0e7ef 100%);
            color: #333;
            border: 1px solid #e0e7ef;
        }
        .btn-warning:hover {
            background: #e0e7ef;
            color: #222;
        }
        .btn-secondary {
            background: linear-gradient(90deg, #e0e7ef 0%, #f8fafc 100%);
            color: #333;
            border: 1px solid #d1d5db;
        }
        .form-control:focus {
            border-color: #4f8cff;
            box-shadow: 0 0 0 0.2rem rgba(79, 140, 255, 0.25);
        }
        .fw-bold {
            color: #4f8cff;
        }
        @media (max-width: 575.98px) {
            .card, .card-header {
                border-radius: 1rem !important;
            }
            .card-body {
                padding: 1rem !important;
            }
            h1 {
                font-size: 1.25rem;
            }
        }
    </style>
</head>
<body>

<!-- Page Content -->
<div class="container mt-4">
    <div class="row justify-content-center">
        <div class="col-md-8 col-lg-6">
            <div class="card shadow-sm">
                <div class="card-header">
                    <h1 class="text-center mb-0 fw-bold" style="color:#222;">
                        <i class="bi bi-person-gear"></i> แก้ไขข้อมูลพนักงาน
                    </h1>
                </div>
                <div class="card-body p-4">
                    
                    <!-- แสดงข้อมูลพนักงานปัจจุบัน -->
                    <?php if ($employee): ?>
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle"></i> 
                        <strong>พนักงาน:</strong> <?php echo htmlspecialchars($employee['first_name_th'] ?? 'N/A'); ?> <?php echo htmlspecialchars($employee['last_name_th'] ?? 'N/A'); ?>
                        <br><strong>Employee ID:</strong> <?php echo htmlspecialchars($_SESSION['employee_id']); ?>
                    </div>
                    <?php endif; ?>

                    <!-- แสดงข้อความ error -->
                    <?php if (isset($error_message)): ?>
                    <div class="alert alert-danger">
                        <i class="bi bi-exclamation-circle"></i> <?php echo htmlspecialchars($error_message); ?>
                    </div>
                    <?php endif; ?>

                    <!-- แสดงข้อความ success จาก URL parameter -->
                    <?php if (isset($_GET['success']) && $_GET['success'] == '1'): ?>
                    <div class="alert alert-success">
                        <i class="bi bi-check-circle"></i> อัปเดตรหัสผ่านสำเร็จแล้ว!
                    </div>
                    <?php endif; ?>

                    <!-- Debug info (เฉพาะในโหมด development) -->
                    <?php if (defined('DEV_MODE') && DEV_MODE): ?>
                    <div class="alert alert-info">
                        <small>
                            <strong>Debug:</strong> Employee ID = <?php echo htmlspecialchars($_SESSION['employee_id']); ?> 
                            (Type: <?php echo gettype($_SESSION['employee_id']); ?>)
                        </small>
                    </div>
                    <?php endif; ?>

                    <form action="" method="POST" onsubmit="return validateForm()">
                        <div class="mb-3">
                            <label for="pws_user" class="form-label">
                                <i class="bi bi-key"></i> รหัสผ่านใหม่
                            </label>
                            <input type="password" 
                                   id="pws_user" 
                                   name="pws_user" 
                                   class="form-control" 
                                   placeholder="กรอกรหัสผ่านใหม่"
                                   minlength="4"
                                   required>
                            <div class="form-text">รหัสผ่านต้องมีอย่างน้อย 4 ตัวอักษร</div>
                        </div>

                        <div class="mb-3">
                            <label for="confirm_password" class="form-label">
                                <i class="bi bi-key-fill"></i> ยืนยันรหัสผ่าน
                            </label>
                            <input type="password" 
                                   id="confirm_password" 
                                   name="confirm_password" 
                                   class="form-control" 
                                   placeholder="ยืนยันรหัสผ่านใหม่"
                                   minlength="4"
                                   required>
                        </div>

                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-warning">
                                <i class="bi bi-check-circle"></i> บันทึกการเปลี่ยนแปลง
                            </button>
                            <a href="employee_profile.php" class="btn btn-secondary">
                                <i class="bi bi-arrow-left-circle"></i> ย้อนกลับ
                            </a>
                        </div>
                    </form>

                </div>
            </div>
        </div>
    </div>
</div>

<footer>
    <?php include_once '../includes/footer.php'; ?>
</footer>

<!-- Bootstrap JS Bundle -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<script>
function validateForm() {
    const password = document.getElementById('pws_user').value;
    const confirmPassword = document.getElementById('confirm_password').value;
    
    if (password !== confirmPassword) {
        alert('รหัสผ่านไม่ตรงกัน กรุณาตรวจสอบอีกครั้ง');
        return false;
    }
    
    if (password.length < 4) {
        alert('รหัสผ่านต้องมีอย่างน้อย 4 ตัวอักษร');
        return false;
    }
    
    return confirm('คุณต้องการเปลี่ยนรหัสผ่านใช่หรือไม่?');
}

function closeSidebar() {
    var sidebar = document.querySelector('.offcanvas.show');
    if(sidebar) {
        var offcanvas = bootstrap.Offcanvas.getInstance(sidebar);
        offcanvas.hide();
    }
}
</script>

</body>
</html>