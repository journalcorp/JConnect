<?php
// ไฟล์บันทึกข้อมูลแบบง่ายที่สุด - ไม่ต้องพึ่งพาฐานข้อมูล
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json; charset=utf-8');

try {
    // เริ่ม session
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    // ตั้งค่า employee_id สำหรับทดสอบ
    if (!isset($_SESSION['employee_id'])) {
        $_SESSION['employee_id'] = 'TEST001';
    }

    $employee_id = $_SESSION['employee_id'];

    // ตรวจสอบ method
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo json_encode(['success' => false, 'message' => 'Method not POST: ' . $_SERVER['REQUEST_METHOD']]);
        exit;
    }

    // รับข้อมูล JSON
    $jsonInput = file_get_contents('php://input');
    $inputData = json_decode($jsonInput, true);

    if (!$inputData) {
        echo json_encode(['success' => false, 'message' => 'Invalid JSON: ' . substr($jsonInput, 0, 200)]);
        exit;
    }

    // ตรวจสอบข้อมูลที่จำเป็น
    $dominant_color = $inputData['dominant_color'] ?? '';
    if (empty($dominant_color)) {
        echo json_encode(['success' => false, 'message' => 'Missing dominant_color']);
        exit;
    }

    // เตรียมข้อมูล
    $currentYear = date('Y');
    $currentMonth = (int)date('n');
    $evaluation_round = $currentYear . '-' . ($currentMonth <= 6 ? '1' : '2');
    
    $scores = $inputData['scores'] ?? [];
    $purple_score = (int)($scores['purple'] ?? 0);
    $yellow_score = (int)($scores['yellow'] ?? 0);
    $blue_score = (int)($scores['blue'] ?? 0);
    $green_score = (int)($scores['green'] ?? 0);

    // บันทึกลงไฟล์แทน (สำหรับทดสอบ)
    $resultData = [
        'employee_id' => $employee_id,
        'evaluation_round' => $evaluation_round,
        'scores' => $scores,
        'dominant_color' => $dominant_color,
        'timestamp' => date('Y-m-d H:i:s'),
        'raw_data' => $inputData
    ];

    $filename = 'color_test_results.json';
    $existingData = [];
    
    if (file_exists($filename)) {
        $existing = file_get_contents($filename);
        $existingData = json_decode($existing, true) ?: [];
    }

    // เพิ่มข้อมูลใหม่
    $existingData[] = $resultData;

    // บันทึกลงไฟล์
    if (file_put_contents($filename, json_encode($existingData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE))) {
        echo json_encode([
            'success' => true, 
            'message' => 'บันทึกสำเร็จ (ลงไฟล์)', 
            'saved_data' => $resultData
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'ไม่สามารถบันทึกไฟล์ได้']);
    }

} catch (Exception $e) {
    echo json_encode([
        'success' => false, 
        'message' => 'Exception: ' . $e->getMessage(), 
        'file' => $e->getFile(), 
        'line' => $e->getLine()
    ]);
} catch (Error $e) {
    echo json_encode([
        'success' => false, 
        'message' => 'Error: ' . $e->getMessage(), 
        'file' => $e->getFile(), 
        'line' => $e->getLine()
    ]);
}
?>
