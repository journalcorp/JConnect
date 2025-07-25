<?php
ob_start(); // เริ่มการบัฟเฟอร์เอาต์พุต
session_start();
include_once '../includes/header.php';
include_once '../includes/menu.php';
include_once '../config.php'; // ไฟล์สำหรับการเชื่อมต่อฐานข้อมูล

$conn = dbConnect();

// ตรวจสอบว่า employee_id มีอยู่ใน Session หรือไม่
if (!isset($_SESSION['employee_id'])) {
    die("ไม่พบ employee_id ใน Session");
}

$employeeID = $_SESSION['employee_id'];

// ตรวจสอบว่า activity_id ถูกส่งมาหรือไม่
if (!isset($_GET['id'])) {
    die("ไม่พบ ID กิจกรรม");
}

$activityID = $_GET['id'];

// ดึงข้อมูลกิจกรรมจาก ID
$sql = "SELECT * FROM activities WHERE id = ?";
$params = array($activityID);
$stmt = sqlsrv_query($conn, $sql, $params);

$activity = null;
if ($stmt && $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $activity = $row;
}
if ($stmt) {
    sqlsrv_free_stmt($stmt);
}

// ตรวจสอบว่าพบกิจกรรมหรือไม่
if (!$activity) {
    die("ไม่พบกิจกรรมที่ระบุ");
}

// ตรวจสอบว่าผู้ใช้เข้าร่วมกิจกรรมนี้แล้วหรือยัง
$checkSql = "SELECT COUNT(*) as count FROM activity_participants WHERE employee_id = ? AND activity_id = ?";
$checkParams = array($employeeID, $activityID);
$checkStmt = sqlsrv_query($conn, $checkSql, $checkParams);

$alreadyJoined = false;
if ($checkStmt && $row = sqlsrv_fetch_array($checkStmt, SQLSRV_FETCH_ASSOC)) {
    $alreadyJoined = ($row['count'] > 0);
}
if ($checkStmt) {
    sqlsrv_free_stmt($checkStmt);
}

$message = '';

// ฟังก์ชันเข้าร่วมกิจกรรมพร้อมอัปโหลดรูปภาพหลายรูป
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['join_activity'])) {
    // ตรวจสอบว่าเข้าร่วมแล้วหรือยัง
    if ($alreadyJoined) {
        $message = "คุณได้เข้าร่วมกิจกรรมนี้แล้ว!";
    } else {
        $upload_dir = '../uploads/'; // โฟลเดอร์สำหรับเก็บรูปภาพ
        $uploaded_files = []; // เก็บชื่อไฟล์ที่อัปโหลดสำเร็จ

        // สร้างโฟลเดอร์ uploads หากไม่มี
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }

        // ตรวจสอบว่ามีการอัปโหลดไฟล์หรือไม่
        if (isset($_FILES['proof']['name']) && count($_FILES['proof']['name']) > 0) {
            for ($i = 0; $i < count($_FILES['proof']['name']); $i++) {
                // ข้ามไฟล์เปล่า
                if (empty($_FILES['proof']['name'][$i])) {
                    continue;
                }
                
                $original_file_name = basename($_FILES['proof']['name'][$i]);
                $file_type = strtolower(pathinfo($original_file_name, PATHINFO_EXTENSION));
                $base_name = pathinfo($original_file_name, PATHINFO_FILENAME); // ชื่อไฟล์ไม่รวมส่วนขยาย
                $upload_ok = 1;

                // ตรวจสอบประเภทไฟล์ (อนุญาตเฉพาะ JPG, JPEG, PNG)
                if (!in_array($file_type, ['jpg', 'jpeg', 'png'])) {
                    $message = "อนุญาตเฉพาะไฟล์ JPG, JPEG, PNG เท่านั้น!";
                    $upload_ok = 0;
                }

                if ($upload_ok) {
                    // สร้างชื่อไฟล์ใหม่ที่ไม่ซ้ำ
                    $unique_filename = 'activity_' . $activityID . '_emp_' . $employeeID . '_' . time() . '_' . $i . '.' . $file_type;
                    $upload_file = $upload_dir . $unique_filename;

                    // อัปโหลดไฟล์
                    if (move_uploaded_file($_FILES['proof']['tmp_name'][$i], $upload_file)) {
                        $uploaded_files[] = $unique_filename; // เก็บเฉพาะชื่อไฟล์
                    } else {
                        $message = "เกิดข้อผิดพลาดในการอัปโหลดรูปภาพ!";
                    }
                }
            }

            // บันทึกข้อมูลการเข้าร่วมกิจกรรมพร้อมรูปภาพในฐานข้อมูล
            if (!empty($uploaded_files)) {
                foreach ($uploaded_files as $file) {
                    // สร้าง ID ใหม่โดยหา MAX ID และเพิ่ม 1
                    $maxIdSql = "SELECT ISNULL(MAX(id), 0) + 1 AS new_id FROM activity_participants";
                    $maxIdStmt = sqlsrv_query($conn, $maxIdSql);
                    
                    $newId = 1;
                    if ($maxIdStmt && $row = sqlsrv_fetch_array($maxIdStmt, SQLSRV_FETCH_ASSOC)) {
                        $newId = $row['new_id'];
                    }
                    if ($maxIdStmt) {
                        sqlsrv_free_stmt($maxIdStmt);
                    }
                    
                    // ใช้คอลัมน์ที่มีอยู่จริงในตาราง พร้อมระบุ id และ joined_at
                    $sql = "INSERT INTO activity_participants (id, employee_id, activity_id, proof_image, approved, joined_at) VALUES (?, ?, ?, ?, 0, GETDATE())";
                    $params = array($newId, $employeeID, $activityID, $file);
                    $stmt = sqlsrv_query($conn, $sql, $params);
                    
                    if ($stmt) {
                        sqlsrv_free_stmt($stmt);
                    } else {
                        // แสดง error ถ้ามี
                        $errors = sqlsrv_errors();
                        $message = "เกิดข้อผิดพลาดในการบันทึกข้อมูล: " . print_r($errors, true);
                        break;
                    }
                }

                if (empty($message) || strpos($message, 'เกิดข้อผิดพลาด') === false) {
                    // เพิ่มข้อความแจ้งเตือนและเปลี่ยนเส้นทางกลับไปหน้า activity_dashboard
                    $_SESSION['message'] = "เข้าร่วมกิจกรรมแล้ว รอการอนุมัติ!";
                    header("Location: activity_dashboard.php");
                    exit();
                }
            } else {
                $message = "ไม่สามารถอัปโหลดไฟล์ได้!";
            }
        } else {
            $message = "กรุณาอัปโหลดรูปภาพ!";
        }
    }
}

ob_end_flush(); // ส่งข้อมูลที่บัฟเฟอร์ไปยังเบราว์เซอร์

// ฟังก์ชันสำหรับแปลงวันที่เป็นรูปแบบภาษาไทย
function formatDateThai($date) {
    if (!$date) return 'N/A';
    
    $months = [
        "01" => "มกราคม", "02" => "กุมภาพันธ์", "03" => "มีนาคม",
        "04" => "เมษายน", "05" => "พฤษภาคม", "06" => "มิถุนายน",
        "07" => "กรกฎาคม", "08" => "สิงหาคม", "09" => "กันยายน",
        "10" => "ตุลาคม", "11" => "พฤศจิกายน", "12" => "ธันวาคม"
    ];

    try {
        // ตรวจสอบว่าเป็น DateTime object อยู่แล้วหรือไม่
        if (is_object($date) && $date instanceof DateTime) {
            $dateObj = $date;
        } else {
            $dateObj = new DateTime($date);
        }
        
        $day = $dateObj->format('d');
        $month = $months[$dateObj->format('m')];
        $year = $dateObj->format('Y') + 543; // เพิ่ม 543 เพื่อแปลงเป็นปี พ.ศ.

        return "$day $month $year";
    } catch (Exception $e) {
        return 'N/A';
    }
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>เข้าร่วมกิจกรรม</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body {
            font-family: 'Prompt', sans-serif;
            background-color: #f8f9fa;
        }
        .container {
            max-width: 800px;
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
        .btn-secondary {
            background-color: #6c757d;
            border-color: #6c757d;
        }
        .btn-secondary:hover {
            background-color: #5a6268;
            border-color: #5a6268;
        }
        .btn-success {
            background-color: #28a745;
            border-color: #28a745;
        }
        .btn-success:hover {
            background-color: #218838;
            border-color: #218838;
        }
        .form-control {
            border-radius: 8px;
        }
        .alert {
            font-size: 1rem;
            font-weight: 500;
        }
    </style>
    <script>
        // ฟังก์ชันเพิ่มช่องอัปโหลดรูปภาพ
        function addFileInput() {
            const container = document.getElementById('file-input-container');
            const newInput = document.createElement('input');
            newInput.type = 'file';
            newInput.name = 'proof[]';
            newInput.className = 'form-control mb-2';
            container.appendChild(newInput);
        }
    </script>
</head>
<body>
<div class="container mt-5">
    <div class="card">
        <div class="card-header text-center">
            <i class="bi bi-calendar-event"></i> เข้าร่วมกิจกรรม
        </div>
        <div class="card-body">
            <!-- แสดงข้อความ -->
            <?php if (isset($message) && !empty($message)) { ?>
                <div class="alert <?php echo (strpos($message, 'เข้าร่วมแล้ว') !== false || strpos($message, 'ได้เข้าร่วม') !== false) ? 'alert-success' : 'alert-danger'; ?> text-center">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php } ?>

            <?php if ($alreadyJoined) { ?>
                <div class="alert alert-info text-center">
                    <i class="bi bi-info-circle"></i> คุณได้เข้าร่วมกิจกรรมนี้แล้ว
                </div>
            <?php } ?>

            <!-- แสดงรายละเอียดกิจกรรม -->
            <div class="mb-4">
                <h3 class="text-primary">รายละเอียดกิจกรรม</h3>
                <p><strong>ชื่อกิจกรรม:</strong> <?php echo htmlspecialchars($activity['name']); ?></p>
                <p><strong>วันที่เริ่ม:</strong> <?php echo htmlspecialchars(formatDateThai($activity['start_date'])); ?></p>
                <p><strong>วันสิ้นสุด:</strong> <?php echo htmlspecialchars(formatDateThai($activity['end_date'])); ?></p>
                <p><strong>คะแนนที่ได้รับ:</strong> <span class="text-success"><?php echo htmlspecialchars($activity['score']); ?></span></p>
                <p><strong>คำอธิบาย:</strong> <?php echo htmlspecialchars($activity['description']); ?></p>
            </div>

            <!-- ฟอร์มเข้าร่วมกิจกรรม -->
            <?php if (!$alreadyJoined) { ?>
            <form method="POST" enctype="multipart/form-data">
                <div id="file-input-container">
                    <label for="proof" class="form-label">อัปโหลดหลักฐานการเข้าร่วม</label>
                    <input type="file" name="proof[]" id="proof" class="form-control mb-2" required accept=".jpg,.jpeg,.png">
                </div>
                <button type="button" class="btn btn-secondary mb-3" onclick="addFileInput()">
                    <i class="bi bi-plus-circle"></i> เพิ่มรูป
                </button>
                <button type="submit" name="join_activity" class="btn btn-success w-100">
                    <i class="bi bi-check-circle"></i> เข้าร่วมกิจกรรม
                </button>
            </form>
            <?php } ?>

            <a href="activity_dashboard.php" class="btn btn-secondary mt-3 w-100">
                <i class="bi bi-arrow-left-circle"></i> กลับไปหน้ากิจกรรม
            </a>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
