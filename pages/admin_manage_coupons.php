<?php
session_start();
include_once '../includes/header.php';
include_once '../includes/menu.php';
include_once '../config.php';

$conn = dbConnect();

// ตรวจสอบว่า session มี division และเป็น JD011 หรือ JD001
if (!isset($_SESSION['division']) || !in_array($_SESSION['division'], ['JD011', 'JD001'])) {
    die("คุณไม่มีสิทธิ์เข้าถึงหน้านี้");
}

// ตัวแปรสำหรับฟิลเตอร์
$searchEmployeeID = $_GET['employee_id'] ?? '';
$searchName = $_GET['name'] ?? '';
// $searchDept = $_GET['dept'] ?? '';
$searchCoupon = $_GET['coupon'] ?? '';

// สร้างเงื่อนไขการค้นหา
$whereConditions = [];
$params = [];

if (!empty($searchEmployeeID)) {
    $whereConditions[] = "mc.employee_id LIKE ?";
    $params[] = "%$searchEmployeeID%";
}
if (!empty($searchName)) {
    $whereConditions[] = "(e.first_name_th LIKE ? OR e.last_name_th LIKE ?)";
    $params[] = "%$searchName%";
    $params[] = "%$searchName%";
}

if (!empty($searchCoupon)) {
    $whereConditions[] = "c.coupon_code LIKE ?";
    $params[] = "%$searchCoupon%";
}

// รวมเงื่อนไขการค้นหา
$whereSQL = !empty($whereConditions) ? "WHERE " . implode(" AND ", $whereConditions) : "";

// ดึงข้อมูลคูปองที่พนักงานแลกไว้
$sqlCoupons = "SELECT mc.id AS my_coupon_id, mc.employee_id, c.coupon_code, c.description, mc.status, mc.redeemed_at, e.first_name_th, e.last_name_th
               FROM my_coupons mc
               INNER JOIN coupons c ON mc.coupon_id = c.id
               INNER JOIN employees e ON mc.employee_id = e.employee_id
               $whereSQL
               ORDER BY mc.redeemed_at DESC";

$resultCoupons = sqlsrv_query($conn, $sqlCoupons, $params);

// เปลี่ยนสถานะคูปอง
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $myCouponID = intval($_POST['my_coupon_id']);
    $newStatus = $_POST['status'];

    if (in_array($newStatus, ['used', 'unused'])) {
        $sqlUpdateStatus = "UPDATE my_coupons SET status = ? WHERE id = ?";
        $stmtUpdateStatus = sqlsrv_query($conn, $sqlUpdateStatus, array($newStatus, $myCouponID));
        
        if ($stmtUpdateStatus) {
            $message = "อัปเดตสถานะคูปองสำเร็จ!";
            sqlsrv_free_stmt($stmtUpdateStatus);
        } else {
            $errors = sqlsrv_errors();
            $message = "เกิดข้อผิดพลาดในการอัปเดตสถานะ: " . (isset($errors[0]['message']) ? $errors[0]['message'] : 'ไม่ทราบสาเหตุ');
        }
    } else {
        $message = "สถานะไม่ถูกต้อง!";
    }
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>จัดการคูปอง - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body {
            font-family: 'Prompt', sans-serif;
            background-color: #f8f9fa;
        }
        .container {
            max-width: 1200px;
        }
        .card {
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        .card-header {
            background-color: #007bff;
            color: #fff;
            font-weight: bold;
            font-size: 1.2rem;
            border-radius: 10px 10px 0 0;
        }
        .table th {
            background-color: #f1f3f5;
            font-weight: bold;
        }
        .badge-used {
            background-color: #dc3545;
        }
        .badge-unused {
            background-color: #28a745;
        }
        .form-control {
            border-radius: 8px;
        }
        .btn-primary {
            background-color: #007bff;
            border-color: #007bff;
        }
        .btn-primary:hover {
            background-color: #0056b3;
            border-color: #0056b3;
        }
        .btn-secondary {
            background-color: #6c757d;
            border-color: #6c757d;
        }
        .btn-secondary:hover {
            background-color: #5a6268;
            border-color: #5a6268;
        }
    </style>
</head>
<body>
<div class="container mt-5">
    <div class="card">
        <div class="card-header text-center">
            <i class="bi bi-ticket-fill"></i> จัดการคูปอง
        </div>
        <div class="card-body">
            <?php if (isset($message)) { ?>
                <div class="alert alert-info"><?php echo $message; ?></div>
            <?php } ?>

            <!-- ฟอร์มค้นหา -->
            <form method="GET" class="mb-4">
                <div class="card shadow-sm">
                    <div class="card-header bg-primary text-white">
                        <i class="bi bi-funnel-fill"></i> ฟิลเตอร์ค้นหา
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label for="employee_id" class="form-label">รหัสพนักงาน</label>
                                <input type="text" name="employee_id" id="employee_id" class="form-control" placeholder="กรอกรหัสพนักงาน" value="<?php echo htmlspecialchars($searchEmployeeID); ?>">
                            </div>
                            <div class="col-md-4">
                                <label for="name" class="form-label">ชื่อ-นามสกุล</label>
                                <input type="text" name="name" id="name" class="form-control" placeholder="กรอกชื่อ-นามสกุล" value="<?php echo htmlspecialchars($searchName); ?>">
                            </div>
                            <div class="col-md-4">
                                <label for="coupon" class="form-label">คูปอง</label>
                                <input type="text" name="coupon" id="coupon" class="form-control" placeholder="กรอกรหัสคูปอง" value="<?php echo htmlspecialchars($searchCoupon); ?>">
                            </div>
                        </div>
                        <div class="mt-4 text-end">
                            <button type="submit" class="btn btn-primary"><i class="bi bi-search"></i> ค้นหา</button>
                            <a href="admin_manage_coupons.php" class="btn btn-secondary"><i class="bi bi-arrow-clockwise"></i> รีเซ็ต</a>
                        </div>
                    </div>
                </div>
            </form>

            <!-- ตารางแสดงข้อมูล -->
            <div class="table-responsive">
                <table class="table table-bordered table-hover">
                    <thead>
                    <tr>
                        <th>รหัสคูปอง</th>
                        <th>คำอธิบาย</th>
                        <th>รหัสพนักงาน</th>
                        <th>ชื่อ-นามสกุล</th>
                        <th>สถานะ</th>
                        <th>วันที่แลก</th>
                        <th>การดำเนินการ</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php 
                    if ($resultCoupons) {
                        while ($coupon = sqlsrv_fetch_array($resultCoupons, SQLSRV_FETCH_ASSOC)) { 
                    ?>
                        <tr>
                            <td><?php echo htmlspecialchars($coupon['coupon_code']); ?></td>
                            <td><?php echo htmlspecialchars($coupon['description']); ?></td>
                            <td><?php echo htmlspecialchars($coupon['employee_id']); ?></td>
                            <td><?php echo htmlspecialchars($coupon['first_name_th'] . ' ' . $coupon['last_name_th']); ?></td>
                            <td>
                                <span class="badge <?php echo $coupon['status'] === 'used' ? 'badge-used' : 'badge-unused'; ?>">
                                    <?php echo $coupon['status'] === 'used' ? 'ใช้แล้ว' : 'ยังไม่ได้ใช้'; ?>
                                </span>
                            </td>
                            <td>
                                <?php 
                                $date = $coupon['redeemed_at'];
                                if ($date instanceof DateTime) {
                                    echo $date->format('d/m/Y H:i:s');
                                } else {
                                    echo htmlspecialchars($date);
                                }
                                ?>
                            </td>
                            <td>
                                <form method="POST">
                                    <input type="hidden" name="my_coupon_id" value="<?php echo $coupon['my_coupon_id']; ?>">
                                    <select name="status" class="form-select form-select-sm" required>
                                        <option value="unused" <?php echo $coupon['status'] === 'unused' ? 'selected' : ''; ?>>ยังไม่ได้ใช้</option>
                                        <option value="used" <?php echo $coupon['status'] === 'used' ? 'selected' : ''; ?>>ใช้แล้ว</option>
                                    </select>
                                    <button type="submit" name="update_status" class="btn btn-primary btn-sm mt-2"><i class="bi bi-save"></i> อัปเดตสถานะ</button>
                                </form>
                            </td>
                        </tr>
                    <?php 
                        }
                        sqlsrv_free_stmt($resultCoupons);
                    } else {
                        echo '<tr><td colspan="7" class="text-center">ไม่พบข้อมูลคูปอง หรือเกิดข้อผิดพลาดในการดึงข้อมูล</td></tr>';
                    }
                    ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

<?php
// ปิดการเชื่อมต่อฐานข้อมูล
if ($conn) {
    sqlsrv_close($conn);
}
?>