<?php
/**
 * ฟังก์ชันสำหรับการ redirect ที่ปลอดภัย
 * แก้ไขปัญหาการ redirect ไปยัง IP ที่ผิด
 */

function safeRedirect($path) {
    // ตรวจสอบว่ารันจาก CLI หรือไม่
    if (php_sapi_name() === 'cli') {
        echo "CLI Mode: Would redirect to " . $path . "\n";
        return;
    }
    
    // ตรวจสอบว่า path เป็น absolute URL หรือไม่
    if (strpos($path, 'http://') === 0 || strpos($path, 'https://') === 0) {
        header("Location: " . $path);
        exit;
    }
    
    // สร้าง base URL จาก HTTP_HOST ปัจจุบัน
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    
    // ตรวจสอบว่าอยู่ใน subdirectory หรือไม่
    $request_uri = $_SERVER['REQUEST_URI'] ?? '/';
    $script_name = $_SERVER['SCRIPT_NAME'] ?? '/index.php';
    
    // หาค่า base path
    $base_path = dirname($script_name);
    if ($base_path === '/' || $base_path === '\\') {
        $base_path = '';
    }
    
    // สร้าง URL ที่สมบูรณ์
    $full_url = $protocol . '://' . $host . $base_path . '/' . ltrim($path, '/');
    
    header("Location: " . $full_url);
    exit;
}

function getCurrentBaseUrl() {
    // ตรวจสอบว่ารันจาก CLI หรือไม่
    if (php_sapi_name() === 'cli') {
        return 'http://192.168.246.236/webportal';
    }
    
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    
    // อนุญาตให้ใช้ Public IP และ Local IP
    $allowed_hosts = [
        '203.154.130.236',  // Public IP
        '192.168.246.236',  // Local IP
        'localhost',
        '127.0.0.1'
    ];
    
    // ถ้า host ไม่อยู่ในรายการที่อนุญาต ให้ใช้ Local IP เป็น default
    if (!in_array($host, $allowed_hosts)) {
        $host = '192.168.246.236';
    }
    
    $script_name = $_SERVER['SCRIPT_NAME'] ?? '/webportal/index.php';
    $base_path = dirname($script_name);
    
    if ($base_path === '/' || $base_path === '\\') {
        $base_path = '';
    }
    
    return $protocol . '://' . $host . $base_path;
}

function getCorrectUrl($relativePath) {
    return getCurrentBaseUrl() . '/' . ltrim($relativePath, '/');
}
?>
