<?php
session_start();

// Simulate form submission data
$test_data = [
    'activity_name' => 'ทดสอบกิจกรรม - ' . date('Y-m-d H:i:s'),
    'start_date' => date('Y-m-d'),
    'end_date' => date('Y-m-d', strtotime('+7 days')),
    'score' => 85,
    'description' => 'ทดสอบการบันทึกข้อมูลกิจกรรมพร้อมการตั้งค่าไฟล์แนบ - ' . date('Y-m-d H:i:s'),
    'require_attachment' => 1,
    'attachment_description' => 'กรุณาแนบไฟล์เอกสารประกอบการดำเนินงาน เช่น แผนการทำงาน รายงานความคืบหน้า',
    'allow_multiple_files' => 1,
    'is_file_required' => 1
];

echo "<!DOCTYPE html>";
echo "<html lang='th'>";
echo "<head>";
echo "<meta charset='UTF-8'>";
echo "<meta name='viewport' content='width=device-width, initial-scale=1.0'>";
echo "<title>ทดสอบการบันทึกข้อมูล</title>";
echo "<link href='https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css' rel='stylesheet'>";
echo "<link href='https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap' rel='stylesheet'>";
echo "<style>";
echo "body { font-family: 'Prompt', sans-serif; background: #f8f9fa; padding: 2rem 0; }";
echo ".container { max-width: 800px; }";
echo ".test-card { background: white; border-radius: 15px; box-shadow: 0 10px 30px rgba(0,0,0,0.1); padding: 2rem; margin-bottom: 2rem; }";
echo ".test-header { background: linear-gradient(135deg, #28a745 0%, #20c997 100%); color: white; padding: 1.5rem; border-radius: 10px; margin-bottom: 2rem; text-align: center; }";
echo "</style>";
echo "</head>";
echo "<body>";

echo "<div class='container'>";
echo "<div class='test-header'>";
echo "<h2><i class='bi bi-flask'></i> ทดสอบการบันทึกข้อมูลกิจกรรม</h2>";
echo "<p class='mb-0'>ผลการทดสอบ POST ข้อมูลไปยัง add_activity.php</p>";
echo "</div>";

include '../config.php';

if (function_exists('dbConnect')) {
    $conn = dbConnect();
    
    if ($conn) {
        echo "<div class='test-card'>";
        echo "<h4 class='text-success'><i class='bi bi-check-circle'></i> เชื่อมต่อฐานข้อมูลสำเร็จ</h4>";
        
        // Extract test data
        $activity_name = $test_data['activity_name'];
        $start_date = $test_data['start_date'];
        $end_date = $test_data['end_date'];
        $score = $test_data['score'];
        $description = $test_data['description'];
        
        // File attachment settings
        $require_attachment = $test_data['require_attachment'];
        $attachment_description = $test_data['attachment_description'];
        $allow_multiple_files = $test_data['allow_multiple_files'];
        $is_file_required = $test_data['is_file_required'];
        
        // Create attachment settings JSON
        $attachment_settings = json_encode([
            'require_attachment' => (bool)$require_attachment,
            'attachment_description' => $attachment_description,
            'allow_multiple_files' => (bool)$allow_multiple_files,
            'is_file_required' => (bool)$is_file_required
        ], JSON_UNESCAPED_UNICODE);

        echo "<h5>📊 ข้อมูลที่จะบันทึก:</h5>";
        echo "<table class='table table-striped'>";
        echo "<tr><td><strong>ชื่อกิจกรรม:</strong></td><td>{$activity_name}</td></tr>";
        echo "<tr><td><strong>วันที่เริ่ม:</strong></td><td>{$start_date}</td></tr>";
        echo "<tr><td><strong>วันที่สิ้นสุด:</strong></td><td>{$end_date}</td></tr>";
        echo "<tr><td><strong>คะแนน:</strong></td><td>{$score}</td></tr>";
        echo "<tr><td><strong>รายละเอียด:</strong></td><td>{$description}</td></tr>";
        echo "<tr><td><strong>การตั้งค่าไฟล์แนบ:</strong></td><td><pre style='background:#f8f9fa;padding:10px;border-radius:5px;'>{$attachment_settings}</pre></td></tr>";
        echo "</table>";

        // Check if attachment_settings column exists
        $check_column_sql = "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'activities' AND COLUMN_NAME = 'attachment_settings'";
        $check_stmt = sqlsrv_query($conn, $check_column_sql);
        $column_exists = sqlsrv_fetch_array($check_stmt);
        sqlsrv_free_stmt($check_stmt);
        
        if ($column_exists) {
            echo "<p class='text-success'><i class='bi bi-check-circle'></i> คอลัมน์ attachment_settings พบแล้ว - ใช้ query แบบเต็ม</p>";
            $sql = "INSERT INTO activities (name, start_date, end_date, score, description, attachment_settings) VALUES (?, ?, ?, ?, ?, ?)";
            $params = array($activity_name, $start_date, $end_date, $score, $description, $attachment_settings);
        } else {
            echo "<p class='text-warning'><i class='bi bi-exclamation-triangle'></i> คอลัมน์ attachment_settings ไม่พบ - ใช้ query แบบพื้นฐาน</p>";
            $sql = "INSERT INTO activities (name, start_date, end_date, score, description) VALUES (?, ?, ?, ?, ?)";
            $params = array($activity_name, $start_date, $end_date, $score, $description);
        }
        
        echo "<h5>💾 กำลังบันทึกข้อมูล...</h5>";
        
        $stmt = sqlsrv_query($conn, $sql, $params);
        
        if ($stmt) {
            // Get the inserted activity ID
            $id_sql = "SELECT SCOPE_IDENTITY() as id";
            $id_stmt = sqlsrv_query($conn, $id_sql);
            $activity_id = null;
            if ($id_stmt && $row = sqlsrv_fetch_array($id_stmt)) {
                $activity_id = $row['id'];
            }
            sqlsrv_free_stmt($id_stmt);
            sqlsrv_free_stmt($stmt);
            
            echo "<div class='alert alert-success'>";
            echo "<h5><i class='bi bi-check-circle'></i> บันทึกข้อมูลสำเร็จ!</h5>";
            echo "<p><strong>Activity ID:</strong> {$activity_id}</p>";
            echo "</div>";
            
            // If column doesn't exist, log attachment settings
            if (!$column_exists && $require_attachment) {
                $log_dir = '../logs/';
                if (!file_exists($log_dir)) {
                    mkdir($log_dir, 0777, true);
                }
                $log_file = $log_dir . 'attachment_settings_' . date('Y-m-d') . '.log';
                $log_data = date('Y-m-d H:i:s') . " - Activity ID: $activity_id - Settings: $attachment_settings\n";
                file_put_contents($log_file, $log_data, FILE_APPEND);
                echo "<p class='text-info'><i class='bi bi-file-text'></i> บันทึกการตั้งค่าไฟล์แนบลงไฟล์ log: {$log_file}</p>";
            }
            
            // Verify saved data
            echo "<h5>🔍 ตรวจสอบข้อมูลที่บันทึก:</h5>";
            $verify_sql = "SELECT * FROM activities WHERE id = ?";
            $verify_stmt = sqlsrv_query($conn, $verify_sql, array($activity_id));
            
            if ($verify_stmt && $row = sqlsrv_fetch_array($verify_stmt, SQLSRV_FETCH_ASSOC)) {
                echo "<table class='table table-bordered'>";
                foreach ($row as $key => $value) {
                    if ($key === 'attachment_settings' && $value) {
                        $decoded = json_decode($value, true);
                        echo "<tr><td><strong>{$key}:</strong></td><td>";
                        if ($decoded) {
                            echo "<pre style='background:#e8f5e8;padding:10px;border-radius:5px;margin:0;'>";
                            echo json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
                            echo "</pre>";
                        } else {
                            echo htmlspecialchars($value);
                        }
                        echo "</td></tr>";
                    } else {
                        $display_value = is_object($value) ? $value->format('Y-m-d H:i:s') : htmlspecialchars($value);
                        echo "<tr><td><strong>{$key}:</strong></td><td>{$display_value}</td></tr>";
                    }
                }
                echo "</table>";
            }
            sqlsrv_free_stmt($verify_stmt);
            
        } else {
            echo "<div class='alert alert-danger'>";
            echo "<h5><i class='bi bi-x-circle'></i> เกิดข้อผิดพลาดในการบันทึก</h5>";
            $errors = sqlsrv_errors();
            if ($errors) {
                echo "<pre>" . print_r($errors, true) . "</pre>";
            }
            echo "</div>";
        }
        
        echo "</div>";
        
    } else {
        echo "<div class='test-card'>";
        echo "<h4 class='text-danger'><i class='bi bi-x-circle'></i> ไม่สามารถเชื่อมต่อฐานข้อมูลได้</h4>";
        echo "</div>";
    }
} else {
    echo "<div class='test-card'>";
    echo "<h4 class='text-danger'><i class='bi bi-x-circle'></i> ฟังก์ชัน dbConnect() ไม่พบ</h4>";
    echo "</div>";
}

echo "<div class='test-card'>";
echo "<h5>🔗 ลิงก์ทดสอบเพิ่มเติม:</h5>";
echo "<div class='d-flex gap-2 flex-wrap'>";
echo "<a href='add_activity.php' class='btn btn-primary' target='_blank'><i class='bi bi-plus-circle'></i> เพิ่มกิจกรรมใหม่</a>";
echo "<a href='activity_management.php' class='btn btn-success' target='_blank'><i class='bi bi-list-ul'></i> ดูรายการกิจกรรม</a>";
echo "<a href='test_form.php' class='btn btn-warning' target='_blank'><i class='bi bi-flask'></i> ทดสอบฟอร์ม</a>";
echo "<a href='db_test.php' class='btn btn-info' target='_blank'><i class='bi bi-database'></i> ทดสอบฐานข้อมูล</a>";
echo "</div>";
echo "</div>";

echo "</div>";
echo "<link href='https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css' rel='stylesheet'>";
echo "</body>";
echo "</html>";
?>
