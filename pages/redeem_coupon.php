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

$employeeID = $_SESSION['employee_id']; // รหัสพนักงานจาก Session

// ดึงคะแนนสะสมจากตาราง employees
$sql = "SELECT point FROM employees WHERE employee_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $employeeID);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();
$availablePoints = $row['point']; // คะแนนสะสม

// ดึงข้อมูลคูปอง
$sqlCoupons = "SELECT * FROM coupons WHERE expiry_date >= CURDATE()";
$resultCoupons = $conn->query($sqlCoupons);

// ตรวจสอบการแลกคูปอง
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['redeem_coupon'])) {
    $couponID = intval($_POST['coupon_id']);
    $pointsRequired = intval($_POST['points_required']);

    if ($pointsRequired > $availablePoints) {
        $message = "คะแนนสะสมไม่เพียงพอสำหรับการแลกคูปอง!";
    } else {
        // หักคะแนนจาก employees
        $sqlDeductPoints = "UPDATE employees SET point = point - ? WHERE employee_id = ?";
        $stmtDeductPoints = $conn->prepare($sqlDeductPoints);
        $stmtDeductPoints->bind_param("is", $pointsRequired, $employeeID);
        if ($stmtDeductPoints->execute()) {
            // บันทึกคูปองลงใน my_coupons
            $sqlInsertMyCoupon = "INSERT INTO my_coupons (employee_id, coupon_id, redeemed_at) VALUES (?, ?, NOW())";
            $stmtInsertMyCoupon = $conn->prepare($sqlInsertMyCoupon);
            $stmtInsertMyCoupon->bind_param("si", $employeeID, $couponID);
            if ($stmtInsertMyCoupon->execute()) {
                $message = "แลกคูปองสำเร็จ!";
                $availablePoints -= $pointsRequired; // อัปเดตคะแนนที่เหลือ
            } else {
                $message = "เกิดข้อผิดพลาดในการบันทึกคูปอง: " . $stmtInsertMyCoupon->error;
            }
            $stmtInsertMyCoupon->close();
        } else {
            $message = "เกิดข้อผิดพลาดในการหักคะแนน: " . $stmtDeductPoints->error;
        }
        $stmtDeductPoints->close();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['coupon_id'])) {
    $couponID = intval($_POST['coupon_id']);

    // อัปเดตจำนวนการใช้คูปอง
    $sqlUpdateUsage = "UPDATE coupons SET usage_count = usage_count + 1 WHERE id = ?";
    $stmtUpdateUsage = $conn->prepare($sqlUpdateUsage);
    $stmtUpdateUsage->bind_param("i", $couponID);
    $stmtUpdateUsage->execute();
    $stmtUpdateUsage->close();

    // เพิ่มการดำเนินการอื่นๆ เช่น การบันทึกประวัติการใช้คูปอง
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>แลกคูปอง</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body {
            font-family: 'Prompt', sans-serif;
            background-color: #f8f9fa;
        }
        .container {
            max-width: 900px;
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
            font-size: 1.2rem;
            border-radius: 12px 12px 0 0;
        }
        .table th {
            background-color: #f1f3f5;
            font-weight: bold;
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
        .alert {
            font-size: 1rem;
            font-weight: 500;
        }
    </style>
</head>
<body>
<div class="container mt-5">
    <div class="card">
        <div class="card-header text-center">
            <i class="bi bi-ticket-fill"></i> แลกคูปอง
        </div>
        <div class="card-body">
            <?php if (isset($message)) { ?>
                <div class="alert alert-info text-center"><?php echo $message; ?></div>
            <?php } ?>
            <p class="text-center">
                <strong>คะแนนสะสมที่มี:</strong> 
                <span class="text-primary"><?php echo htmlspecialchars($availablePoints); ?></span> คะแนน
            </p>
            <h3 class="mt-4 text-center">รายการคูปอง</h3>
            <div class="table-responsive">
                <table class="table table-bordered table-hover">
                    <thead>
                    <tr>
                        <th>รหัสคูปอง</th>
                        <th>คำอธิบาย</th>
                        <th>คะแนนที่ต้องใช้</th>
                        <th>วันหมดอายุ</th>
                        <th>การดำเนินการ</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php while ($coupon = $resultCoupons->fetch_assoc()) { ?>
                        <tr>
                            <td><?php echo htmlspecialchars($coupon['coupon_code']); ?></td>
                            <td><?php echo htmlspecialchars($coupon['description']); ?></td>
                            <td><?php echo htmlspecialchars($coupon['points_required']); ?></td>
                            <td><?php echo htmlspecialchars($coupon['expiry_date']); ?></td>
                            <td>
                                <form method="POST">
                                    <input type="hidden" name="coupon_id" value="<?php echo $coupon['id']; ?>">
                                    <input type="hidden" name="points_required" value="<?php echo $coupon['points_required']; ?>">
                                    <button type="submit" name="redeem_coupon" class="btn btn-success btn-sm"><i class="bi bi-arrow-repeat"></i> แลกคูปอง</button>
                                </form>
                            </td>
                        </tr>
                    <?php } ?>
                    </tbody>
                </table>
            <!-- </div>
            <a href="dashboard.php" class="btn btn-secondary mt-3 w-100"><i class="bi bi-arrow-left-circle"></i> กลับไปหน้าหลัก</a>
        </div> -->
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>