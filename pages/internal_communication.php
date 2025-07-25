<?php

session_start();
include_once '../includes/header.php';
include_once '../includes/menu.php';

if (!isset($_SESSION['employee_id'])) {
    header("Location: ../index.php");
    exit;
}

// ดึงข้อมูลพนักงานและชื่อแผนกจากฐานข้อมูล
$conn = dbConnect();
$sql = "SELECT 
            e.employee_id, 
            e.prefix_th, 
            e.first_name_th, 
            e.last_name_th, 
            e.position_th, 
            d.DeptName 
        FROM employees e
        LEFT JOIN Dept d ON e.division = d.DeptID";
$result = $conn->query($sql);

if (!$result) {
    die("Query failed: " . $conn->error);
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ติดต่อสื่อสารภายใน - Web Portal</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="../assets/bootstrap/css/bootstrap.min.css">
    <link rel="stylesheet" href="../assets/css/styles.css">
    <link rel="stylesheet" href="../assets/css/styles.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <script src="../assets/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/scripts.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<style>
        body {
            font-family: 'Prompt', sans-serif;
            background-color: #f8f9fa; /* สีพื้นหลังเทาอ่อน */
            color: #212529; /* สีข้อความ */
        }
        .navbar {
            background-color: #ffffff; /* สีขาว */
            border-bottom: 1px solid #dee2e6; /* เส้นขอบด้านล่าง */
        }
        .navbar .navbar-brand {
            font-weight: 600;
            color: #212529;
        }
        .navbar .navbar-brand:hover {
            color: #495057;
        }
        .employee-info {
            background-color: #ffffff;
            border: 1px solid #dee2e6;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }
        h1 {
            font-size: 1.75rem;
            font-weight: 600;
        }
    </style>

<body>
     <!-- Page Content -->
     <div class="container mt-4">
        <h1>ติดต่อสื่อสารภายใน</h1>

        <!-- รายชื่อพนักงาน/แผนก -->
        <div class="card mt-4">
            <div class="card-header bg-primary text-white">
                รายชื่อพนักงาน/แผนก
            </div>
            <div class="card-body">
                <table class="table table-bordered">
                    <thead class="table-light">
                        <tr>
                            <th>#</th>
                            <th>ชื่อพนักงาน</th>
                            <th>ตำแหน่ง</th>
                            <th>แผนก</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($result->num_rows > 0): ?>
                            <?php while ($row = $result->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($row['employee_id']); ?></td>
                                    <td><?php echo htmlspecialchars($row['prefix_th'] . ' ' . $row['first_name_th'] . ' ' . $row['last_name_th']); ?></td>
                                    <td><?php echo htmlspecialchars($row['position_th']); ?></td>
                                    <td><?php echo htmlspecialchars($row['DeptName']); ?></td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="4" class="text-center">ไม่พบข้อมูลพนักงาน</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- ติดต่อหน่วยงานต่าง ๆ -->
        <div class="card mt-4">
            <div class="card-header bg-success text-white">
                ติดต่อหน่วยงานต่าง ๆ
            </div>
            <div class="card-body">
                <ul>
                    <li><strong>ฝ่ายบุคคล:</strong> hr@example.com, 02-123-4567</li>
                    <li><strong>ฝ่ายไอที:</strong> it@example.com, 02-987-6543</li>
                    <li><strong>ฝ่ายการเงิน:</strong> finance@example.com, 02-456-7890</li>
                </ul>
            </div>
        </div>

        <!-- ระบบส่งข้อความ -->
        <div class="card mt-4">
            <div class="card-header bg-warning text-dark">
                ระบบส่งข้อความ
            </div>
            <div class="card-body">
                <form action="send_message.php" method="POST">
                    <div class="mb-3">
                        <label for="recipient" class="form-label">ถึง</label>
                        <select class="form-select" id="recipient" name="recipient" required>
                            <option value="john.doe@example.com">John Doe</option>
                            <option value="jane.smith@example.com">Jane Smith</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="message" class="form-label">ข้อความ</label>
                        <textarea class="form-control" id="message" name="message" rows="3" required></textarea>
                    </div>
                    <button type="submit" class="btn btn-primary">ส่งข้อความ</button>
                </form>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>