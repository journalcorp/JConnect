<?php
// Test file to save sample activity data with attachment settings
include '../config.php';

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>Test Data Saving - Activity with Attachment Settings</h2>";

// Test data
$test_activity_name = "ทดสอบกิจกรรม - " . date('Y-m-d H:i:s');
$test_start_date = date('Y-m-d');
$test_end_date = date('Y-m-d', strtotime('+7 days'));
$test_score = 100;
$test_description = "กิจกรรมทดสอบระบบการแนบไฟล์";

// Test attachment settings
$test_attachment_settings = json_encode([
    'require_attachment' => true,
    'attachment_description' => 'กรุณาแนบไฟล์เอกสารประกอบการดำเนินงาน',
    'allow_multiple_files' => true,
    'is_file_required' => true
]);

echo "<h3>Test Data:</h3>";
echo "<pre>";
echo "Activity Name: " . $test_activity_name . "\n";
echo "Start Date: " . $test_start_date . "\n";
echo "End Date: " . $test_end_date . "\n";
echo "Score: " . $test_score . "\n";
echo "Description: " . $test_description . "\n";
echo "Attachment Settings: " . $test_attachment_settings . "\n";
echo "</pre>";

if ($conn) {
    echo "<h3>Database Connection: Success</h3>";
    
    // Check if attachment_settings column exists
    $check_column_sql = "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'activities' AND COLUMN_NAME = 'attachment_settings'";
    $check_stmt = sqlsrv_query($conn, $check_column_sql);
    $column_exists = sqlsrv_fetch_array($check_stmt);
    sqlsrv_free_stmt($check_stmt);
    
    if ($column_exists) {
        echo "<h3>Column Check: attachment_settings column exists</h3>";
        
        // Insert with attachment settings
        $sql = "INSERT INTO activities (name, start_date, end_date, score, description, attachment_settings) VALUES (?, ?, ?, ?, ?, ?)";
        $params = array($test_activity_name, $test_start_date, $test_end_date, $test_score, $test_description, $test_attachment_settings);
        
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
            
            echo "<h3 style='color: green;'>✅ Test Successful!</h3>";
            echo "<p>Activity ID: " . $activity_id . "</p>";
            
            // Verify the saved data
            $verify_sql = "SELECT * FROM activities WHERE id = ?";
            $verify_stmt = sqlsrv_query($conn, $verify_sql, array($activity_id));
            
            if ($verify_stmt && $row = sqlsrv_fetch_array($verify_stmt, SQLSRV_FETCH_ASSOC)) {
                echo "<h3>Saved Data Verification:</h3>";
                echo "<pre>";
                print_r($row);
                echo "</pre>";
                
                // Decode and display attachment settings
                if ($row['attachment_settings']) {
                    $settings = json_decode($row['attachment_settings'], true);
                    echo "<h3>Attachment Settings (Decoded):</h3>";
                    echo "<pre>";
                    print_r($settings);
                    echo "</pre>";
                }
            }
            sqlsrv_free_stmt($verify_stmt);
            
        } else {
            echo "<h3 style='color: red;'>❌ Insert Failed</h3>";
            $errors = sqlsrv_errors();
            if ($errors) {
                echo "<pre>";
                print_r($errors);
                echo "</pre>";
            }
        }
        
    } else {
        echo "<h3 style='color: orange;'>⚠️ Column attachment_settings does not exist</h3>";
        echo "<p>Please run the database_updates.sql script first.</p>";
        
        // Try inserting without attachment settings
        $sql = "INSERT INTO activities (name, start_date, end_date, score, description) VALUES (?, ?, ?, ?, ?)";
        $params = array($test_activity_name, $test_start_date, $test_end_date, $test_score, $test_description);
        
        $stmt = sqlsrv_query($conn, $sql, $params);
        
        if ($stmt) {
            sqlsrv_free_stmt($stmt);
            echo "<h3 style='color: green;'>✅ Basic Insert Successful (without attachment settings)</h3>";
            
            // Save settings to log file
            $log_dir = '../logs/';
            if (!file_exists($log_dir)) {
                mkdir($log_dir, 0777, true);
            }
            $log_file = $log_dir . 'attachment_settings_' . date('Y-m-d') . '.log';
            $log_data = date('Y-m-d H:i:s') . " - Test Activity: $test_activity_name - Settings: $test_attachment_settings\n";
            file_put_contents($log_file, $log_data, FILE_APPEND);
            echo "<p>Attachment settings saved to log file: " . $log_file . "</p>";
        } else {
            echo "<h3 style='color: red;'>❌ Basic Insert Failed</h3>";
            $errors = sqlsrv_errors();
            if ($errors) {
                echo "<pre>";
                print_r($errors);
                echo "</pre>";
            }
        }
    }
    
} else {
    echo "<h3 style='color: red;'>❌ Database Connection Failed</h3>";
    echo "<p>Please check your database configuration in config.php</p>";
}
?>

<style>
body {
    font-family: Arial, sans-serif;
    margin: 20px;
    background-color: #f5f5f5;
}
pre {
    background-color: #f8f9fa;
    padding: 15px;
    border: 1px solid #ddd;
    border-radius: 5px;
    overflow-x: auto;
}
h2, h3 {
    color: #333;
}
</style>
