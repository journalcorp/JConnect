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
$sql = "SELECT coupon_code, description, points_required, expiry_date, usage_limit, budget FROM coupons WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $couponID);
$stmt->execute();
$result = $stmt->get_result();
$coupon = $result->fetch_assoc();

if (!$coupon) {
    die("<p class='text-danger text-center'>ไม่พบข้อมูลคูปอง</p>");
}

// อัปเดตข้อมูลคูปอง
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $couponCode = $_POST['coupon_code'];
    $description = $_POST['description'];
    $pointsRequired = intval($_POST['points_required']);
    $expiryDate = $_POST['expiry_date'];
    $budget = isset($_POST['budget']) && $_POST['budget'] !== '' ? intval($_POST['budget']) : null;

    $sqlUpdate = "UPDATE coupons SET coupon_code = ?, description = ?, points_required = ?, expiry_date = ?, budget = ? 
                  WHERE id = ?";
    $stmtUpdate = $conn->prepare($sqlUpdate);
    $stmtUpdate->bind_param("ssisi", $couponCode, $description, $pointsRequired, $expiryDate, $budget, $couponID);

    if ($stmtUpdate->execute()) {
        $message = "แก้ไขข้อมูลคูปองสำเร็จ!";
    } else {
        $message = "เกิดข้อผิดพลาดในการแก้ไขข้อมูลคูปอง!";
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>แก้ไขคูปอง</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<div class="container mt-5">
    <h1 class="text-center">แก้ไขคูปอง</h1>
    <?php if (isset($message)) { ?>
        <div class="alert alert-info text-center"><?php echo $message; ?></div>
    <?php } ?>
    <form method="POST">
        <div class="mb-3">
            <label for="coupon_code" class="form-label">รหัสคูปอง</label>
            <input type="text" name="coupon_code" id="coupon_code" class="form-control" value="<?php echo htmlspecialchars($coupon['coupon_code']); ?>" required>
        </div>
        <div class="mb-3">
            <label for="description" class="form-label">คำอธิบาย</label>
            <textarea name="description" id="description" class="form-control" rows="3" required><?php echo htmlspecialchars($coupon['description']); ?></textarea>
        </div>
        <div class="mb-3">
            <label for="points_required" class="form-label">คะแนนที่ต้องใช้</label>
            <input type="number" name="points_required" id="points_required" class="form-control" value="<?php echo htmlspecialchars($coupon['points_required']); ?>" required>
        </div>
        <div class="mb-3">
            <label for="expiry_date" class="form-label">วันหมดอายุ</label>
            <input type="date" name="expiry_date" id="expiry_date" class="form-control" value="<?php echo htmlspecialchars($coupon['expiry_date']); ?>" required>
        </div>
        <div class="mb-3">
            <label for="usage_limit" class="form-label">จำนวนสิทธิการใช้คูปอง (ไม่จำเป็นต้องกรอก)</label>
            <input type="number" name="usage_limit" id="usage_limit" class="form-control" min="1" 
                   value="<?php echo htmlspecialchars($coupon['usage_limit']); ?>" placeholder="กรอกจำนวนสิทธิการใช้คูปอง">
        </div>
        <div class="mb-3">
            <label for="budget" class="form-label">งบประมาณคูปอง (ไม่จำเป็นต้องกรอก)</label>
            <input type="number" name="budget" id="budget" class="form-control" min="1" 
                   value="<?php echo htmlspecialchars($coupon['budget']); ?>" placeholder="กรอกงบประมาณคูปอง">
        </div>
        <button type="submit" class="btn btn-success w-100">บันทึกการแก้ไข</button>
    </form>
    <a href="manage_coupons.php" class="btn btn-secondary mt-3 w-100">กลับไปหน้าจัดการคูปอง</a>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>