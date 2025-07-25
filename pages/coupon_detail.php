<?php
session_start();
include_once '../includes/header.php';
include_once '../includes/menu.php';
include_once '../config.php';

$conn = dbConnect();

// ตรวจสอบว่ามีการส่ง id ของคูปองมาหรือไม่
if (!isset($_GET['id'])) {
    die("<p class='text-danger text-center'>ไม่พบข้อมูลคูปอง</p>");
}

$couponID = intval($_GET['id']);

// ดึงข้อมูลคูปองจากฐานข้อมูล
$sql = "SELECT coupon_code, description, points_required, expiry_date, usage_limit, usage_count, budget FROM coupons WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $couponID);
$stmt->execute();
$result = $stmt->get_result();
$coupon = $result->fetch_assoc();

if (!$coupon) {
    die("<p class='text-danger text-center'>ไม่พบข้อมูลคูปอง</p>");
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>รายละเอียดคูปอง</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<div class="container mt-5">
    <h1 class="text-center">รายละเอียดคูปอง</h1>
    <table class="table table-bordered">
        <tr>
            <th>รหัสคูปอง</th>
            <td><?php echo htmlspecialchars($coupon['coupon_code']); ?></td>
        </tr>
        <tr>
            <th>คำอธิบาย</th>
            <td><?php echo htmlspecialchars($coupon['description']); ?></td>
        </tr>
        <tr>
            <th>คะแนนที่ต้องใช้</th>
            <td><?php echo htmlspecialchars($coupon['points_required']); ?></td>
        </tr>
        <tr>
            <th>วันหมดอายุ</th>
            <td><?php echo htmlspecialchars($coupon['expiry_date']); ?></td>
        </tr>
        <tr>
            <th>จำนวนสิทธิการใช้คูปอง</th>
            <td><?php echo htmlspecialchars($coupon['usage_limit'] ?? 'ไม่ระบุ'); ?></td>
        </tr>
        <tr>
            <th>จำนวนการใช้คูปอง</th>
            <td><?php echo htmlspecialchars($coupon['usage_count']); ?></td>
        </tr>
        <tr>
            <th>งบประมาณคูปอง</th>
            <td><?php echo htmlspecialchars($coupon['budget'] ?? 'ไม่ระบุ'); ?></td>
        </tr>
    </table>
    <a href="manage_coupons.php" class="btn btn-secondary mt-3 w-100">กลับไปหน้าจัดการคูปอง</a>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>