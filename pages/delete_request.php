<?php
session_start();
include_once '../config.php';

// ตรวจสอบว่ามี RequestID ที่ต้องการลบหรือไม่
if (!isset($_GET['id'])) {
    header("Location: accounting_form.php");
    exit;
}

$request_id = intval($_GET['id']);

// เชื่อมต่อฐานข้อมูล
$conn = dbConnect();

// ตรวจสอบว่ามีคำขอนี้อยู่จริงหรือไม่
$sql_check = "SELECT * FROM Requests WHERE RequestID = ?";
$stmt_check = $conn->prepare($sql_check);
$stmt_check->bind_param("i", $request_id);
$stmt_check->execute();
$result = $stmt_check->get_result();

if ($result->num_rows === 0) {
    // ไม่พบข้อมูล
    $stmt_check->close();
    $conn->close();
    header("Location: accounting_form.php?msg=notfound");
    exit;
}

// ลบข้อมูล
$sql_delete = "DELETE FROM Requests WHERE RequestID = ?";
$stmt_delete = $conn->prepare($sql_delete);
$stmt_delete->bind_param("i", $request_id);

if ($stmt_delete->execute()) {
    $stmt_delete->close();
    $conn->close();
    header("Location: accounting_form.php?msg=deleted");
    exit;
} else {
    $stmt_delete->close();
    $conn->close();
    header("Location: accounting_form.php?msg=error");
    exit;
}
?>