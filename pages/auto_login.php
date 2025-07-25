<?php
// ไฟล์เข้าสู่ระบบอัตโนมัติสำหรับทดสอบ
session_start();

// ตั้งค่า session สำหรับทดสอบ
$_SESSION['employee_id'] = 1;
$_SESSION['username'] = 'admin';
$_SESSION['first_name'] = 'Admin';
$_SESSION['last_name'] = 'User';
$_SESSION['role'] = 'admin';

// Redirect ไปยัง activity_dashboard.php
header('Location: activity_dashboard.php');
exit;
?>
