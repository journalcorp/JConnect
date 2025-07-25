<?php
// เปิดการแสดงข้อผิดพลาด
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// รวมไฟล์ header และ menu
include_once '../includes/header.php';
include_once '../includes/menu.php';



?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ข่าวสาร - Web Portal</title>
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
        <h1>ข่าวสาร</h1>

        <!-- ประกาศจากฝ่ายบุคคลหรือฝ่ายบริหาร -->
        <div class="card mt-4">
            <div class="card-header bg-primary text-white">
                ประกาศจากฝ่ายบุคคลหรือฝ่ายบริหาร
            </div>
            <div class="card-body">
                <ul>
                    <li>ประกาศวันหยุดประจำปี: 13-15 เมษายน</li>
                    <li>การปรับปรุงระบบจะเกิดขึ้นในวันที่ 25 เมษายน</li>
                    <li>แจ้งเตือนการส่งเอกสารภายในวันที่ 30 เมษายน</li>
                </ul>
            </div>
        </div>

        <!-- ข่าวอบรม สัมมนา กิจกรรมองค์กร -->
        <div class="card mt-4">
            <div class="card-header bg-success text-white">
                ข่าวอบรม สัมมนา กิจกรรมองค์กร
            </div>
            <div class="card-body">
                <ul>
                    <li>กิจกรรมอบรมพนักงานใหม่: 20 เมษายน</li>
                    <li>สัมมนา "การพัฒนาทักษะการขาย" วันที่ 22 เมษายน</li>
                    <li>กิจกรรม CSR: ปลูกป่า วันที่ 28 เมษายน</li>
                </ul>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>