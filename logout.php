<?php
session_start();
session_destroy();

// รวมไฟล์ redirect helper สำหรับ redirect ที่ปลอดภัย
include_once 'includes/redirect_helper.php';

// ใช้ safeRedirect เพื่อ redirect ไปหน้า login
safeRedirect('index.php');
?>