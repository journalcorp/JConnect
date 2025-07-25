<?php
session_start();
include_once '../includes/header.php';
include_once '../includes/menu.php';
include_once '../config.php';

$conn = dbConnect();

// ดึงข้อมูลคูปองทั้งหมดจากฐานข้อมูล
$sql = "SELECT id, coupon_code, description, points_required, expiry_date, usage_limit, usage_count FROM coupons";
$result = $conn->query($sql);
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>จัดการคูปอง</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
            font-family: 'Prompt', sans-serif;
        }
        .container {
            max-width: 900px;
            margin-top: 20px;
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
        .btn-primary {
            background-color: #007bff;
            border-color: #007bff;
        }
        .btn-primary:hover {
            background-color: #0056b3;
            border-color: #0056b3;
        }
        .btn-danger {
            background-color: #dc3545;
            border-color: #dc3545;
        }
        .btn-danger:hover {
            background-color: #c82333;
            border-color: #c82333;
        }
        .btn-warning {
            background-color: #ffc107;
            border-color: #ffc107;
        }
        .btn-warning:hover {
            background-color: #e0a800;
            border-color: #e0a800;
        }
        .btn-info {
            background-color: #17a2b8;
            border-color: #17a2b8;
        }
        .btn-info:hover {
            background-color: #138496;
            border-color: #138496;
        }
    </style>
</head>
<body>
<div class="container">
    <div class="card">
        <div class="card-header">
            <i class="bi bi-ticket-perforated"></i> จัดการคูปอง
        </div>
        <div class="card-body">
            <a href="create_coupon.php" class="btn btn-primary mb-3">
                <i class="bi bi-plus-circle"></i> เพิ่มคูปองใหม่
            </a>
            <div class="table-responsive">
                <table class="table table-bordered table-hover">
                    <thead>
                    <tr>
                        <th>รหัสคูปอง</th>
                        <th>คำอธิบาย</th>
                        <th>คะแนนที่ต้องใช้</th>
                        <th>วันหมดอายุ</th>
                        <th>งบประมาณคูปอง</th>
                        <th>การดำเนินการ</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php while ($coupon = $result->fetch_assoc()) { ?>
                        <tr>
                            <td><?php echo htmlspecialchars($coupon['coupon_code']); ?></td>
                            <td><?php echo htmlspecialchars($coupon['description']); ?></td>
                            <td><?php echo htmlspecialchars($coupon['points_required']); ?></td>
                            <td><?php echo htmlspecialchars($coupon['expiry_date']); ?></td>
                            <td><?php echo htmlspecialchars($coupon['budget'] ?? 'ไม่ระบุ'); ?></td>
                            <td>
                                <div class="d-flex justify-content-center align-items-center gap-2">
                                    <a href="edit_coupon.php?id=<?php echo $coupon['id']; ?>" class="btn btn-warning btn-sm" title="แก้ไข">
                                        <i class="bi bi-pencil-square"></i>
                                    </a>
                                    <a href="coupon_detail.php?id=<?php echo $coupon['id']; ?>" class="btn btn-info btn-sm" title="รายละเอียด">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                    <form method="POST" action="delete_coupon.php" style="display:inline;">
                                        <input type="hidden" name="coupon_id" value="<?php echo $coupon['id']; ?>">
                                        <button type="submit" class="btn btn-danger btn-sm" title="ลบ">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php } ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>