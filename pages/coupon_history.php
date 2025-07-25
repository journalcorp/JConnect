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

// ดึงข้อมูลประวัติการแลกคูปองจากฐานข้อมูล
$employeeID = $_SESSION['employee_id']; // ใช้ employee_id จาก Session
$sqlHistory = "SELECT c.coupon_code AS coupon_code, c.description, c.points_required, mc.redeemed_at, mc.status 
               FROM my_coupons mc
               INNER JOIN coupons c ON mc.coupon_id = c.id
               WHERE mc.employee_id = ?";
$stmtHistory = $conn->prepare($sqlHistory);
$stmtHistory->bind_param("i", $employeeID);
$stmtHistory->execute();
$resultHistory = $stmtHistory->get_result();
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ประวัติการแลกคูปอง</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
            font-family: 'Prompt', sans-serif;
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
            font-size: 1.5rem;
            text-align: center;
            border-radius: 12px 12px 0 0;
        }
        .table th {
            background-color: #f1f3f5;
            font-weight: bold;
            text-align: center;
        }
        .table td {
            text-align: center;
        }
        .status-used {
            color: #28a745;
            font-weight: bold;
        }
        .status-unused {
            color: #dc3545;
            font-weight: bold;
        }
    </style>
</head>
<body>
<div class="container mt-5">
    <div class="card">
        <div class="card-header">
            <i class="bi bi-ticket-perforated"></i> ประวัติการแลกคูปอง
        </div>
        <div class="card-body">
            <?php if ($resultHistory->num_rows > 0) { ?>
                <table class="table table-bordered table-hover">
                    <thead>
                    <tr>
                        <th>รหัสคูปอง</th>
                        <th>คำอธิบาย</th>
                        <th>คะแนนที่ใช้</th>
                        <th>วันที่แลก</th>
                        <th>สถานะ</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php while ($history = $resultHistory->fetch_assoc()) { ?>
                        <tr>
                            <td><?php echo htmlspecialchars($history['coupon_code']); ?></td>
                            <td><?php echo htmlspecialchars($history['description']); ?></td>
                            <td><?php echo htmlspecialchars($history['points_required']); ?></td>
                            <td><?php echo htmlspecialchars($history['redeemed_at']); ?></td>
                            <td>
                                <?php 
                                // แสดงสถานะคูปองจาก status
                                if ($history['status'] === 'used') {
                                    echo '<span class="status-used">ใช้แล้ว</span>';
                                } elseif ($history['status'] === 'unused') {
                                    echo '<span class="status-unused">ยังไม่ได้ใช้</span>';
                                } else {
                                    echo '<span class="text-secondary">สถานะไม่ทราบ</span>';
                                }
                                ?>
                            </td>
                        </tr>
                    <?php } ?>
                    </tbody>
                </table>
            <?php } else { ?>
                <p class="text-center text-danger">ยังไม่มีประวัติการแลกคูปอง</p>
            <?php } ?>
            <a href="dashboard.php" class="btn btn-secondary mt-3 w-100">
                <i class="bi bi-arrow-left-circle"></i> กลับไปหน้าหลัก
            </a>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>