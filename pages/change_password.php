<?php
session_start();
include '../config.php';
include_once '../includes/redirect_helper.php';

if (!isset($_SESSION['employee_id'])) {
    safeRedirect('../index.php');
}

// เชื่อมต่อฐานข้อมูล
$conn = dbConnect();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new_password = $_POST['new_password'];
    $employee_id = $_SESSION['employee_id'];

    $sql = "UPDATE employees SET pws_user = ?, is_first_login = 0 WHERE employee_id = ?";
    $params = array($new_password, $employee_id);
    $stmt = sqlsrv_query($conn, $sql, $params);

    if ($stmt) {
        sqlsrv_free_stmt($stmt);
        safeRedirect('home.php');
    } else {
        $error = "เกิดข้อผิดพลาดในการเปลี่ยนรหัสผ่าน";
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>เปลี่ยนรหัสผ่าน - Web Portal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- ฟอนต์ Prompt -->
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@400;600&display=swap" rel="stylesheet">
    <style>
        body, h1, h2, h3, h4, h5, h6, label, input, button, .form-label, .form-control {
            font-family: 'Prompt', sans-serif !important;
        }
        body {
            background: linear-gradient(135deg, #f8fafc 0%, #e0e7ef 100%);
            min-height: 100vh;
        }
        .card {
            border-radius: 1.5rem;
            box-shadow: 0 4px 24px 0 rgba(80, 112, 255, 0.07);
            border: none;
            background: #fff;
        }
        .btn-success {
            background: linear-gradient(90deg, #e0e7ef 0%, #f8fafc 100%);
            color: #333;
            border: 1px solid #d1d5db;
            font-weight: 600;
            letter-spacing: 1px;
            transition: background 0.2s, color 0.2s;
        }
        .btn-success:hover {
            background: #e0e7ef;
            color: #222;
        }
        .form-control:focus {
            border-color: #bfc9d9;
            box-shadow: 0 0 0 0.2rem rgba(80, 112, 255, 0.08);
        }
        .container {
            max-width: 420px;
        }
        .fw-bold {
            color: #222;
        }
    </style>
</head>
<body>
    <div class="container mt-5">
        <h1 class="text-center mb-4 fw-bold">เปลี่ยนรหัสผ่าน</h1>
        <div class="card shadow-lg">
            <div class="card-body p-4">
                <form action="" method="POST">
                    <div class="mb-3">
                        <label for="new_password" class="form-label fw-semibold">รหัสผ่านใหม่</label>
                        <input type="password" id="new_password" name="new_password" class="form-control" placeholder="กรอกรหัสผ่านใหม่" required>
                    </div>
                    <button type="submit" class="btn btn-success w-100 py-2 mt-2">บันทึกการเปลี่ยนแปลง</button>
                </form>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>