<?php
include_once 'config.php';

$conn = dbConnect();

echo "=== ตรวจสอบโครงสร้างตาราง activities ===" . PHP_EOL;

$sql = "SELECT TOP 1 * FROM activities";
$stmt = sqlsrv_query($conn, $sql);

if ($stmt === false) {
    echo "Error querying table:" . PHP_EOL;
    print_r(sqlsrv_errors());
} else {
    echo "Column names in activities table:" . PHP_EOL;
    
    foreach (sqlsrv_field_metadata($stmt) as $field) {
        echo "- " . $field['Name'] . " (" . $field['Type'] . ")" . PHP_EOL;
    }
    
    sqlsrv_free_stmt($stmt);
    
    // ลองดูข้อมูลตัวอย่าง
    echo PHP_EOL . "Sample data:" . PHP_EOL;
    $sql2 = "SELECT TOP 3 * FROM activities";
    $stmt2 = sqlsrv_query($conn, $sql2);
    
    if ($stmt2 !== false) {
        while ($row = sqlsrv_fetch_array($stmt2, SQLSRV_FETCH_ASSOC)) {
            print_r($row);
            break; // แสดงแค่แถวเดียว
        }
        sqlsrv_free_stmt($stmt2);
    }
}

sqlsrv_close($conn);
?>
