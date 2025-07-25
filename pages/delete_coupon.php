<?php
session_start();
include_once '../includes/header.php';
include_once '../includes/menu.php';
include_once '../config.php';

$conn = dbConnect();

// ตรวจสอบว่ามีการส่งค่า coupon_id มาหรือไม่
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['coupon_id'])) {
    $couponID = intval($_POST['coupon_id']);

    // ลบคูปองจากฐานข้อมูล
    $sqlDelete = "DELETE FROM coupons WHERE id = ?";
    $stmtDelete = $conn->prepare($sqlDelete);
    $stmtDelete->bind_param("i", $couponID);

    if ($stmtDelete->execute()) {
        $message = "ลบคูปองสำเร็จ!";
    } else {
        $message = "เกิดข้อผิดพลาดในการลบคูปอง!";
    }

    $stmtDelete->close();
} else {
    $message = "ไม่พบข้อมูลคูปองที่ต้องการลบ!";
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ลบคูปอง</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<div class="container mt-5">
    <h1 class="text-center">ลบคูปอง</h1>
    <div class="alert alert-info text-center"><?php echo $message; ?></div>
    <a href="manage_coupons.php" class="btn btn-secondary mt-3 w-100">กลับไปหน้าจัดการคูปอง</a>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>