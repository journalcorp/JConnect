<?php
session_start();
include_once '../includes/header.php';
include_once '../includes/menu.php';
include_once '../config.php';

$conn = dbConnect();

// ตรวจสอบการสร้างคูปอง
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_coupon'])) {
    $couponCode = $_POST['coupon_code'];
    $description = $_POST['description'];
    $pointsRequired = intval($_POST['points_required']);
    $expiryDate = $_POST['expiry_date'];
    $budget = isset($_POST['budget']) && $_POST['budget'] !== '' ? intval($_POST['budget']) : null;

    // ตรวจสอบข้อมูลที่กรอก
    if (empty($couponCode) || empty($description) || $pointsRequired <= 0 || empty($expiryDate)) {
        $message = "กรุณากรอกข้อมูลให้ครบถ้วน!";
    } else {
        $sqlInsert = "INSERT INTO coupons (coupon_code, description, points_required, expiry_date, budget) 
                      VALUES (?, ?, ?, ?, ?)";
        $stmtInsert = $conn->prepare($sqlInsert);
        $stmtInsert->bind_param("ssisi", $couponCode, $description, $pointsRequired, $expiryDate, $budget);

        if ($stmtInsert->execute()) {
            $message = "สร้างคูปองสำเร็จ!";
        } else {
            $message = "เกิดข้อผิดพลาดในการสร้างคูปอง!";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>สร้างคูปอง</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
            font-family: 'Prompt', sans-serif;
        }
        .container {
            max-width: 600px;
        }
        .card {
            border-radius: 12px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            border: none;
        }
        .card-header {
            background-color: #007bff;
            color: #fff;
            font-weight: bold;
            font-size: 1.5rem;
            text-align: center;
            border-radius: 12px 12px 0 0;
        }
        .btn-success {
            background-color: #28a745;
            border-color: #28a745;
        }
        .btn-success:hover {
            background-color: #218838;
            border-color: #218838;
        }
        .btn-secondary {
            background-color: #6c757d;
            border-color: #6c757d;
        }
        .btn-secondary:hover {
            background-color: #5a6268;
            border-color: #5a6268;
        }
        .form-control {
            border-radius: 8px;
        }
        .alert {
            font-size: 1rem;
            font-weight: 500;
        }
    </style>
</head>
<body>
<div class="container mt-5">
    <div class="card">
        <div class="card-header">
            <i class="bi bi-ticket-perforated"></i> สร้างคูปอง
        </div>
        <div class="card-body">
            <?php if (isset($message)) { ?>
                <div class="alert alert-info text-center"><?php echo $message; ?></div>
            <?php } ?>
            <form method="POST">
                <div class="mb-3">
                    <label for="coupon_code" class="form-label">รหัสคูปอง</label>
                    <input type="text" name="coupon_code" id="coupon_code" class="form-control" placeholder="กรอกรหัสคูปอง" required>
                </div>
                <div class="mb-3">
                    <label for="description" class="form-label">คำอธิบาย</label>
                    <textarea name="description" id="description" class="form-control" rows="3" placeholder="กรอกคำอธิบายคูปอง" required></textarea>
                </div>
                <div class="mb-3">
                    <label for="points_required" class="form-label">คะแนนที่ต้องใช้</label>
                    <input type="number" name="points_required" id="points_required" class="form-control" min="1" placeholder="กรอกคะแนนที่ต้องใช้" required>
                </div>
                <div class="mb-3">
                    <label for="expiry_date" class="form-label">วันหมดอายุ</label>
                    <input type="date" name="expiry_date" id="expiry_date" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label for="usage_limit" class="form-label">จำนวนสิทธิการใช้คูปอง (ไม่จำเป็นต้องกรอก)</label>
                    <input type="number" name="usage_limit" id="usage_limit" class="form-control" min="1" placeholder="กรอกจำนวนสิทธิการใช้คูปอง">
                </div>
                <div class="mb-3">
                    <label for="budget" class="form-label">งบประมาณคูปอง (ไม่จำเป็นต้องกรอก)</label>
                    <input type="number" name="budget" id="budget" class="form-control" min="1" placeholder="กรอกงบประมาณคูปอง">
                </div>
                <button type="submit" name="create_coupon" class="btn btn-success w-100">
                    <i class="bi bi-plus-circle"></i> สร้างคูปอง
                </button>
            </form>
            <a href="manage_coupons.php" class="btn btn-secondary mt-3 w-100">
                <i class="bi bi-arrow-left-circle"></i> กลับไปหน้าหลัก
            </a>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>