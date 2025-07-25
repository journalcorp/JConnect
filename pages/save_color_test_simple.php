<?php
header('Content-Type: application/json; charset=utf-8');

// Simple debug version
try {
    session_start();
    
    if (!isset($_SESSION['employee_id'])) {
        echo json_encode(['success' => false, 'message' => 'ไม่มี session']);
        exit;
    }
    
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo json_encode(['success' => false, 'message' => 'ไม่ใช่ POST method', 'method' => $_SERVER['REQUEST_METHOD'], 'data' => $_POST]);
        exit;
    }
    
    // รับข้อมูล
    $employee_id = $_SESSION['employee_id'];
    $purple_score = $_POST['purple_score'] ?? 0;
    $yellow_score = $_POST['yellow_score'] ?? 0;
    $blue_score = $_POST['blue_score'] ?? 0;
    $green_score = $_POST['green_score'] ?? 0;
    $dominant_color = $_POST['dominant_color'] ?? '';
    
    // Mock save (ไม่ได้บันทึกจริง)
    echo json_encode([
        'success' => true, 
        'message' => 'Mock: บันทึกเรียบร้อย',
        'data' => [
            'employee_id' => $employee_id,
            'purple_score' => $purple_score,
            'yellow_score' => $yellow_score,
            'blue_score' => $blue_score,
            'green_score' => $green_score,
            'dominant_color' => $dominant_color
        ]
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>
