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

// ดึงคะแนนสะสมของพนักงาน
$sql = "SELECT point FROM employees WHERE employee_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $employeeID);
$stmt->execute();
$result = $stmt->get_result();
$employee = $result->fetch_assoc();
$stmt->close();

$availablePoints = $employee['point'];

// ตรวจสอบการแลกคะแนน
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['redeem'])) {
    $redeemPoints = intval($_POST['redeem_points']);

    if ($redeemPoints > $availablePoints) {
        $message = "คะแนนสะสมไม่เพียงพอสำหรับการแลก!";
    } elseif ($redeemPoints <= 0) {
        $message = "กรุณาใส่คะแนนที่ต้องการแลกให้ถูกต้อง!";
    } else {
        // หักคะแนนสะสม
        $sqlUpdatePoints = "UPDATE employees SET point = point - ? WHERE employee_id = ?";
        $stmtUpdatePoints = $conn->prepare($sqlUpdatePoints);
        $stmtUpdatePoints->bind_param("is", $redeemPoints, $employeeID);
        $stmtUpdatePoints->execute();
        $stmtUpdatePoints->close();

        // บันทึกการแลกคะแนน
        $sqlInsertRedeem = "INSERT INTO redeem_history (employee_id, points_redeemed, redeemed_at) VALUES (?, ?, NOW())";
        $stmtInsertRedeem = $conn->prepare($sqlInsertRedeem);
        $stmtInsertRedeem->bind_param("si", $employeeID, $redeemPoints);
        $stmtInsertRedeem->execute();
        $stmtInsertRedeem->close();

        $message = "แลกคะแนนสำเร็จ!";
        $availablePoints -= $redeemPoints; // อัปเดตคะแนนที่เหลือ
    }
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>แลกคะแนนสะสม</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body {
            font-family: 'Prompt', sans-serif;
            background-color: #f8f9fa;
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
            font-size: 1.2rem;
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
        <div class="card-header text-center">
            <i class="bi bi-stars"></i> แลกคะแนนสะสม
        </div>
        <div class="card-body">
            <?php if (isset($message)) { ?>
                <div class="alert alert-info text-center"><?php echo $message; ?></div>
            <?php } ?>
            <p class="text-center"><strong>คะแนนสะสมที่มี:</strong> <span class="text-primary"><?php echo htmlspecialchars($availablePoints); ?></span> คะแนน</p>
            <form method="POST">
                <div class="mb-3">
                    <label for="redeem_points" class="form-label">จำนวนคะแนนที่ต้องการแลก</label>
                    <input type="number" name="redeem_points" id="redeem_points" class="form-control" min="1" required>
                </div>
                <button type="submit" name="redeem" class="btn btn-success w-100"><i class="bi bi-arrow-repeat"></i> แลกคะแนน</button>
            </form>
            <a href="activity_dashboard.php" class="btn btn-secondary mt-3 w-100"><i class="bi bi-arrow-left-circle"></i> กลับไปหน้ากิจกรรม</a>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>