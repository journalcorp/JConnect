<?php
// Set charset for proper Thai display
header('Content-Type: text/html; charset=UTF-8');

// ตรวจสอบสถานะเซสชันก่อนเริ่มต้น
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ตรวจสอบว่าผู้ใช้ล็อกอินแล้วหรือยัง
if (!isset($_SESSION['employee_id'])) {
    header("Location: ../index.php");
    exit;
}

// เชื่อมต่อฐานข้อมูล
include '../config.php';
$conn = dbConnect();

// ดึงข้อมูล DeptName จากตาราง Dept โดยเทียบกับ division ของ employee
$sql = "SELECT d.DeptName 
        FROM employees e
        LEFT JOIN Dept d ON e.division = d.DeptID
        WHERE e.employee_id = ?";

// ใช้ SQLSRV syntax แทน PDO/MySQLi
$params = array($_SESSION['employee_id']);
$stmt = sqlsrv_query($conn, $sql, $params);

if ($stmt && sqlsrv_has_rows($stmt)) {
    $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
    $_SESSION['department_name'] = $row['DeptName']; // เก็บ DeptName ในเซสชัน
} else {
    $_SESSION['department_name'] = 'N/A'; // กรณีไม่มีข้อมูล
}

// ปิด statement
if ($stmt) {
    sqlsrv_free_stmt($stmt);
}
// SQLSRV connections are closed automatically when the script ends
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? htmlspecialchars($page_title, ENT_QUOTES, 'UTF-8') : 'Web Portal'; ?></title>
    <!-- <link rel="stylesheet" href="../assets/bootstrap/css/bootstrap.min.css"> -->
    <link rel="stylesheet" href="../assets/css/styles.css">
    <!-- <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet"> -->
    <!-- <script src="../assets/bootstrap/js/bootstrap.bundle.min.js"></script> -->
    <script src="../assets/js/scripts.js"></script>
    <style>
        body {
            font-family: 'Sarabun', 'Tahoma', 'Arial', sans-serif;
        }
    </style>
</head>
<body>
    <header>
        <nav class="navbar navbar-light bg-light">
            <div class="container-fluid d-flex justify-content-between align-items-center">
                <!-- Sidebar Toggle Button -->
                <button class="btn btn-outline-secondary" type="button" data-bs-toggle="offcanvas" data-bs-target="#sidebar" aria-controls="sidebar" id="sidebarToggle">
                    <span class="navbar-toggler-icon"></span>
                </button>
                <!-- Brand -->
                <a class="navbar-brand position-absolute start-50 translate-middle-x" href="../pages/home.php">
                    <img src="../img/Journal.png"
                         alt="Web Portal"
                         style="height:40px; max-width:160px; object-fit:contain;"
                         class="d-inline-block align-middle">
                </a>
            </div>
        </nav>
    </header>
    
    <script>
    // Hide sidebar toggle button if sidebar doesn't exist
    document.addEventListener('DOMContentLoaded', function() {
        const sidebarToggle = document.getElementById('sidebarToggle');
        const sidebar = document.getElementById('sidebar');
        
        if (sidebarToggle && !sidebar) {
            sidebarToggle.style.display = 'none';
        }
    });
    </script>
</body>
</html>