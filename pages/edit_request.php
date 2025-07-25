<?php
session_start();
include_once '../includes/header.php';
include_once '../includes/menu.php';
include_once '../config.php'; // ไฟล์สำหรับการเชื่อมต่อฐานข้อมูล

// ตรวจสอบว่าผู้ใช้ล็อกอินแล้วหรือยัง
if (!isset($_SESSION['employee_id'])) {
    header("Location: ../index.php");
    exit;
}

// ตรวจสอบว่ามีการส่ง ID ของคำขอมา
if (!isset($_GET['id']) || empty($_GET['id'])) {
    die("ไม่พบคำขอที่ต้องการแก้ไข");
}

$requestID = intval($_GET['id']);

// เชื่อมต่อฐานข้อมูล
$conn = dbConnect();
if (!$conn) {
    die("Database connection failed: " . mysqli_connect_error());
}

// ดึงข้อมูลคำขอจากฐานข้อมูล
$sql = "SELECT RequestTitle, RequestDate, Amount, Remarks FROM Requests WHERE RequestID = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $requestID);
$stmt->execute();
$result = $stmt->get_result();
$request = $result->fetch_assoc();
$stmt->close();

if (!$request) {
    die("ไม่พบคำขอที่ต้องการแก้ไข");
}

// อัปเดตข้อมูลเมื่อส่งแบบฟอร์ม
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $requestTitle = $_POST['request_title'];
    $requestDate = $_POST['request_date'];
    $amount = $_POST['amount'];
    $remarks = $_POST['remarks'];

    $updateSql = "UPDATE Requests SET RequestTitle = ?, RequestDate = ?, Amount = ?, Remarks = ? WHERE RequestID = ?";
    $stmt = $conn->prepare($updateSql);
    $stmt->bind_param("ssdsi", $requestTitle, $requestDate, $amount, $remarks, $requestID);

    if ($stmt->execute()) {
        header("Location: accounting_form.php?success=1");
        exit;
    } else {
        echo "เกิดข้อผิดพลาดในการอัปเดตข้อมูล: " . $conn->error;
    }
    $stmt->close();
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>แก้ไขคำขอ - Web Portal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body {
            font-family: 'Prompt', sans-serif;
            background-color: #f8f9fa;
        }
    </style>
</head>
<body>
<div class="container mt-5">
    <h1 class="text-center mb-4">แก้ไขคำขอ</h1>
    <div class="card">
        <div class="card-body">
            <form action="" method="POST">
                <div class="mb-3">
                    <label for="request_title" class="form-label">ชื่อคำขอ</label>
                    <input type="text" id="request_title" name="request_title" class="form-control" value="<?php echo htmlspecialchars($request['RequestTitle']); ?>" required>
                </div>
                <div class="mb-3">
                    <label for="request_date" class="form-label">วันที่ส่งคำขอ</label>
                    <input type="datetime-local" id="request_date" name="request_date" class="form-control" value="<?php echo date('Y-m-d\TH:i', strtotime($request['RequestDate'])); ?>" required>
                </div>
                <div class="mb-3">
                    <label for="amount" class="form-label">จำนวนเงิน</label>
                    <input type="number" id="amount" name="amount" class="form-control" step="0.01" value="<?php echo htmlspecialchars($request['Amount']); ?>" required>
                </div>
                <div class="mb-3">
                    <label for="remarks" class="form-label">หมายเหตุ</label>
                    <textarea id="remarks" name="remarks" class="form-control" rows="4"><?php echo htmlspecialchars($request['Remarks']); ?></textarea>
                </div>
                <button type="submit" class="btn btn-success w-100 mb-3">บันทึกการเปลี่ยนแปลง</button>
            </form>
            <a href="accounting_form.php" class="btn btn-secondary w-100">
                <i class="bi bi-arrow-left-circle"></i> ย้อนกลับ
            </a>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>