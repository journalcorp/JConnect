<?php
session_start();
include_once '../includes/header.php';
include_once '../includes/menu.php';
include_once '../config.php';

$conn = dbConnect();

// ตรวจสอบว่า employee_id มีอยู่ใน Session หรือไม่
if (!isset($_SESSION['employee_id'])) {
    die("กรุณาเข้าสู่ระบบก่อนใช้งาน");
}

$employeeID = $_SESSION['employee_id'];

// ดึงข้อมูลคูปองที่ผู้ใช้แลกไว้
$sqlMyCoupons = "SELECT c.coupon_code, c.description, c.expiry_date, mc.redeemed_at 
                 FROM my_coupons mc
                 INNER JOIN coupons c ON mc.coupon_id = c.id
                 WHERE mc.employee_id = ?";
$stmtMyCoupons = $conn->prepare($sqlMyCoupons);
$stmtMyCoupons->bind_param("s", $employeeID);
$stmtMyCoupons->execute();
$resultMyCoupons = $stmtMyCoupons->get_result();
$stmtMyCoupons->close();
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>คูปองของฉัน</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
        }
        .card {
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
    </style>
</head>
<body>
<div class="container mt-5">
    <div class="card p-4">
        <h1 class="mb-4 text-center">คูปองของฉัน</h1>
        <?php if ($resultMyCoupons->num_rows > 0) { ?>
            <table class="table table-bordered">
                <thead>
                <tr>
                    <th>รหัสคูปอง</th>
                    <th>คำอธิบาย</th>
                    <th>วันหมดอายุ</th>
                    <th>วันที่แลก</th>
                </tr>
                </thead>
                <tbody>
                <?php while ($coupon = $resultMyCoupons->fetch_assoc()) { ?>
                    <tr>
                        <td><?php echo htmlspecialchars($coupon['coupon_code']); ?></td>
                        <td><?php echo htmlspecialchars($coupon['description']); ?></td>
                        <td><?php echo htmlspecialchars($coupon['expiry_date']); ?></td>
                        <td><?php echo htmlspecialchars($coupon['redeemed_at']); ?></td>
                    </tr>
                <?php } ?>
                </tbody>
            </table>
        <?php } else { ?>
            <p class="text-center">ยังไม่มีคูปองที่แลกไว้</p>
        <?php } ?>
        <a href="dashboard.php" class="btn btn-secondary mt-3 w-100">กลับไปหน้าหลัก</a>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>