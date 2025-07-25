<?php
// เพิ่ม error reporting สำหรับ debug
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

// ตรวจสอบการโหลดไฟล์ config.php
try {
    include 'config.php'; // รวมไฟล์ config.php เพื่อใช้ฟังก์ชัน verifyLogin()
    include_once 'includes/redirect_helper.php'; // รวมไฟล์ helper สำหรับ redirect
} catch (Exception $e) {
    die("ไม่สามารถโหลด config.php: " . $e->getMessage());
}

// Check if the user is already logged in
// รับ redirect param ถ้ามี
$redirectTarget = '';
if (isset($_GET['redirect'])) {
    $redirectTarget = $_GET['redirect'];
}
if (isset($_SESSION['employee_id']) && !isset($_GET['force_login'])) {
    // ถ้ามี session อยู่แล้ว และไม่ได้บังคับให้แสดงหน้า login
    // ถ้ามี redirect param ให้เด้งไปหน้านั้น
    if (!empty($redirectTarget)) {
        safeRedirect($redirectTarget);
    } else {
        safeRedirect('pages/home.php');
    }
}

// Handle login form submission
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $employee_id = $_POST['employee_id'] ?? '';
    $password = $_POST['password'] ?? '';
    $redirectTarget = $_POST['redirect'] ?? $redirectTarget;

    if (empty($employee_id) || empty($password)) {
        $error = 'กรุณากรอกข้อมูลให้ครบถ้วน.';
    } else {
        try {
            // ตรวจสอบว่าฟังก์ชัน verifyLogin มีอยู่หรือไม่
            if (!function_exists('verifyLogin')) {
                throw new Exception('ไม่พบฟังก์ชัน verifyLogin');
            }
            if (verifyLogin($employee_id, $password)) {
                // ตรวจสอบสถานะการเข้าสู่ระบบครั้งแรก
                if (isset($_SESSION['is_first_login']) && $_SESSION['is_first_login'] == 1) {
                    safeRedirect('pages/change_password.php');
                } else {
                    if (!empty($redirectTarget)) {
                        safeRedirect($redirectTarget);
                    } else {
                        safeRedirect('pages/home.php');
                    }
                }
            } else {
                $error = 'รหัสพนักงานหรือรหัสผ่านไม่ถูกต้อง.';
            }
        } catch (Exception $e) {
            $error = 'เกิดข้อผิดพลาดในระบบ: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>เข้าสู่ระบบ - Web Portal</title>
    <!-- Google Fonts: Prompt -->
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        :root {
            --primary-gray: #2c3e50;
            --light-gray: #ecf0f1;
            --medium-gray: #95a5a6;
            --dark-gray: #34495e;
            --white: #ffffff;
            --shadow: 0 15px 35px rgba(0,0,0,0.1);
            --border-radius: 20px;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            font-family: 'Prompt', sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
        }

        body::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1000 1000"><defs><radialGradient id="a" cx="50%" cy="50%"><stop offset="0%" style="stop-color:%23ffffff;stop-opacity:0.1"/><stop offset="100%" style="stop-color:%23ffffff;stop-opacity:0"/></radialGradient></defs><rect width="100%" height="100%" fill="url(%23a)"/></svg>') center/cover;
            pointer-events: none;
        }

        .container {
            position: relative;
            z-index: 1;
        }

        .login-card {
            border: none;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            background: var(--white);
            overflow: hidden;
            backdrop-filter: blur(10px);
            width: 100%;
            max-width: 420px;
            margin: 0 auto;
        }

        .login-header {
            background: linear-gradient(135deg, var(--primary-gray) 0%, var(--dark-gray) 100%);
            color: var(--white);
            padding: 2rem 1.5rem;
            text-align: center;
            position: relative;
        }

        .login-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(45deg, transparent 30%, rgba(255,255,255,0.1) 50%, transparent 70%);
            pointer-events: none;
        }

        .login-header h1 {
            font-size: 1.75rem;
            margin: 0;
            font-weight: 300;
            letter-spacing: 0.5px;
            position: relative;
            z-index: 2;
        }

        .login-header p {
            margin: 0.5rem 0 0 0;
            font-size: 0.9rem;
            opacity: 0.8;
            font-weight: 300;
        }

        .card-body {
            padding: 2.5rem 2rem;
        }

        .logo-container {
            text-align: center;
            margin-bottom: 2rem;
        }

        .logo-container img {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            border: 4px solid var(--white);
            transition: transform 0.3s ease;
        }

        .logo-container img:hover {
            transform: scale(1.05);
        }

        .welcome-text {
            text-align: center;
            margin-bottom: 2rem;
            color: var(--medium-gray);
            font-size: 0.95rem;
            font-weight: 300;
            line-height: 1.6;
        }

        .form-group {
            margin-bottom: 1.5rem;
            position: relative;
        }

        .form-label {
            font-weight: 500;
            color: var(--primary-gray);
            margin-bottom: 0.5rem;
            font-size: 0.9rem;
            letter-spacing: 0.3px;
        }

        .form-control {
            border: 2px solid #e9ecef;
            border-radius: 12px;
            padding: 0.875rem 1rem;
            font-size: 0.95rem;
            transition: all 0.3s ease;
            background: var(--white);
            color: var(--primary-gray);
        }

        .form-control:focus {
            border-color: var(--primary-gray);
            box-shadow: 0 0 0 0.2rem rgba(44, 62, 80, 0.1);
            outline: none;
            transform: translateY(-1px);
        }

        .form-control::placeholder {
            color: var(--medium-gray);
            font-weight: 300;
        }

        .btn-login {
            background: linear-gradient(135deg, var(--primary-gray) 0%, var(--dark-gray) 100%);
            border: none;
            border-radius: 12px;
            padding: 1rem 2rem;
            font-size: 1rem;
            font-weight: 500;
            letter-spacing: 0.5px;
            color: var(--white);
            width: 100%;
            transition: all 0.3s ease;
            margin-top: 0.5rem;
        }

        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(44, 62, 80, 0.2);
            background: linear-gradient(135deg, var(--dark-gray) 0%, var(--primary-gray) 100%);
        }

        .btn-login:active {
            transform: translateY(0);
        }

        .login-footer {
            text-align: center;
            padding: 1.5rem 2rem;
            background: #f8f9fa;
            border-top: 1px solid #e9ecef;
            font-size: 0.8rem;
            color: var(--medium-gray);
            font-weight: 300;
        }

        .error-modal .modal-content {
            border: none;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
        }

        .error-modal .modal-header {
            background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);
            border: none;
            border-radius: var(--border-radius) var(--border-radius) 0 0;
            color: var(--white);
        }

        .error-modal .modal-title {
            font-weight: 500;
            font-size: 1.1rem;
        }

        .error-modal .modal-body {
            padding: 2rem;
            color: var(--primary-gray);
            font-size: 0.95rem;
            line-height: 1.6;
        }

        .error-modal .modal-footer {
            border: none;
            padding: 1rem 2rem 2rem;
        }

        .error-modal .btn-secondary {
            background: var(--medium-gray);
            border: none;
            border-radius: 8px;
            padding: 0.5rem 1.5rem;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .error-modal .btn-secondary:hover {
            background: var(--primary-gray);
            transform: translateY(-1px);
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .login-card {
                margin: 1rem;
                max-width: none;
            }
            
            .card-body {
                padding: 2rem 1.5rem;
            }
            
            .login-header {
                padding: 1.5rem 1rem;
            }
            
            .logo-container img {
                width: 100px;
                height: 100px;
            }
        }

        /* Loading animation */
        .btn-login:disabled {
            opacity: 0.7;
            cursor: not-allowed;
        }

        /* Subtle animations */
        .login-card {
            animation: slideIn 0.6s ease-out;
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Input focus effects */
        .form-group {
            position: relative;
        }

        .form-group::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(45deg, transparent 30%, rgba(44, 62, 80, 0.05) 50%, transparent 70%);
            border-radius: 12px;
            opacity: 0;
            transition: opacity 0.3s ease;
            pointer-events: none;
        }

        .form-group:focus-within::before {
            opacity: 1;
        }
    </style>
</head>
<body>
    <div class="container vh-100 d-flex justify-content-center align-items-center">
        <div class="card login-card">
            <div class="login-header">
                <h1>Web Portal</h1>
                <!-- <p>ระบบจัดการองค์กร</p> -->
            </div>
            <div class="card-body">
                <!-- Logo Section -->
                <div class="logo-container">
                    <img src="assets/images/Logo-journal.jpg" alt="Logo" class="img-fluid">
                </div>
                
                <!-- Welcome Text -->
                <!-- <div class="welcome-text">
                    <p>ยินดีต้อนรับเข้าสู่ระบบ<br>กรุณาเข้าสู่ระบบเพื่อดำเนินการต่อ</p>
                </div> -->
                
                <!-- Login Form -->
                <form method="POST" action="" id="loginForm">
                    <?php if (!empty($redirectTarget)): ?>
                        <input type="hidden" name="redirect" value="<?php echo htmlspecialchars($redirectTarget); ?>">
                    <?php endif; ?>
                    <div class="form-group">
                        <label for="employee_id" class="form-label">รหัสพนักงาน</label>
                        <input type="text" class="form-control" id="employee_id" name="employee_id" placeholder="กรอกรหัสพนักงาน" required>
                    </div>
                    <div class="form-group">
                        <label for="password" class="form-label">รหัสผ่าน</label>
                        <input type="password" class="form-control" id="password" name="password" placeholder="กรอกรหัสผ่าน" required>
                    </div>
                    <button type="submit" class="btn btn-login" id="loginBtn">
                        เข้าสู่ระบบ
                    </button>
                </form>
            </div>
            <div class="login-footer">
                <small>© 2025 Web Portal System. สงวนลิขสิทธิ์</small>
            </div>
        </div>
    </div>

    <!-- Error Modal -->
    <div class="modal fade error-modal" id="errorModal" tabindex="-1" aria-labelledby="errorModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="errorModalLabel">
                        <i class="bi bi-exclamation-triangle-fill me-2"></i>
                        เกิดข้อผิดพลาด
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-0"><?php echo htmlspecialchars($error); ?></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="bi bi-x-circle me-1"></i>
                        ปิด
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Enhanced login form functionality
        document.addEventListener('DOMContentLoaded', function() {
            const loginForm = document.getElementById('loginForm');
            const loginBtn = document.getElementById('loginBtn');
            const employeeInput = document.getElementById('employee_id');
            const passwordInput = document.getElementById('password');
            
            // Add loading state to login button
            loginForm.addEventListener('submit', function(e) {
                loginBtn.disabled = true;
                loginBtn.innerHTML = '<i class="bi bi-hourglass-split me-2"></i>กำลังเข้าสู่ระบบ...';
                
                // Re-enable button after 5 seconds as fallback
                setTimeout(function() {
                    loginBtn.disabled = false;
                    loginBtn.innerHTML = 'เข้าสู่ระบบ';
                }, 5000);
            });
            
            // Add focus effects
            [employeeInput, passwordInput].forEach(input => {
                input.addEventListener('focus', function() {
                    this.parentElement.classList.add('focused');
                });
                
                input.addEventListener('blur', function() {
                    this.parentElement.classList.remove('focused');
                });
            });
            
            // Auto-focus on first input
            employeeInput.focus();
        });
        
        // Enhanced error modal functionality
        <?php if (!empty($error)): ?>
        function showErrorModal() {
            try {
                const errorModalElement = document.getElementById('errorModal');
                if (errorModalElement && typeof bootstrap !== 'undefined' && bootstrap.Modal) {
                    const errorModal = new bootstrap.Modal(errorModalElement, {
                        backdrop: 'static',
                        keyboard: false
                    });
                    errorModal.show();
                    
                    // Reset login button when modal is shown
                    const loginBtn = document.getElementById('loginBtn');
                    if (loginBtn) {
                        loginBtn.disabled = false;
                        loginBtn.innerHTML = 'เข้าสู่ระบบ';
                    }
                } else {
                    setTimeout(showErrorModal, 200);
                }
            } catch (e) {
                console.error('Error showing modal:', e);
                alert('<?php echo addslashes($error); ?>');
            }
        }
        
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', function() {
                setTimeout(showErrorModal, 100);
            });
        } else {
            setTimeout(showErrorModal, 100);
        }
        <?php endif; ?>
    </script>
</body>
</html>