<?php
// สคริปต์ลบและสร้างตารางใหม่
error_reporting(E_ALL);
ini_set('display_errors', 1);

include_once '../config.php';

$conn = dbConnect();

if (!$conn) {
    die("Connection failed: " . print_r(sqlsrv_errors(), true));
}

echo "<h2>ลบและสร้างตารางใหม่</h2>";

// ลบตาราง activity_participants ก่อน (เพราะมี foreign key)
echo "<p>กำลังลบตาราง activity_participants...</p>";
$dropTableSql = "IF OBJECT_ID('activity_participants', 'U') IS NOT NULL DROP TABLE activity_participants";
$dropStmt = sqlsrv_query($conn, $dropTableSql);
if ($dropStmt) {
    echo "<p>✅ ลบตาราง activity_participants สำเร็จ</p>";
    sqlsrv_free_stmt($dropStmt);
}

// ลบตาราง activities
echo "<p>กำลังลบตาราง activities...</p>";
$dropTableSql = "IF OBJECT_ID('activities', 'U') IS NOT NULL DROP TABLE activities";
$dropStmt = sqlsrv_query($conn, $dropTableSql);
if ($dropStmt) {
    echo "<p>✅ ลบตาราง activities สำเร็จ</p>";
    sqlsrv_free_stmt($dropStmt);
}

// สร้างตาราง activities ใหม่
echo "<p>กำลังสร้างตาราง activities ใหม่...</p>";
$createTableSql = "
CREATE TABLE activities (
    id int IDENTITY(1,1) PRIMARY KEY,
    name nvarchar(255) NOT NULL,
    description nvarchar(1000),
    start_date datetime,
    end_date datetime,
    score int DEFAULT 0,
    created_at datetime DEFAULT GETDATE(),
    updated_at datetime DEFAULT GETDATE()
)";

$createStmt = sqlsrv_query($conn, $createTableSql);
if ($createStmt) {
    echo "<p>✅ สร้างตาราง activities สำเร็จ</p>";
    sqlsrv_free_stmt($createStmt);
} else {
    echo "<p>❌ ไม่สามารถสร้างตาราง activities ได้: " . print_r(sqlsrv_errors(), true) . "</p>";
}

// สร้างตาราง activity_participants ใหม่
echo "<p>กำลังสร้างตาราง activity_participants ใหม่...</p>";
$createParticipantsSql = "
CREATE TABLE activity_participants (
    id int IDENTITY(1,1) PRIMARY KEY,
    activity_id int NOT NULL,
    employee_id int NOT NULL,
    joined_date datetime DEFAULT GETDATE(),
    approved bit DEFAULT 0,
    approved_by int NULL,
    approved_date datetime NULL,
    notes nvarchar(500),
    created_at datetime DEFAULT GETDATE(),
    FOREIGN KEY (activity_id) REFERENCES activities(id),
    UNIQUE(activity_id, employee_id)
)";

$createParticipantsStmt = sqlsrv_query($conn, $createParticipantsSql);
if ($createParticipantsStmt) {
    echo "<p>✅ สร้างตาราง activity_participants สำเร็จ</p>";
    sqlsrv_free_stmt($createParticipantsStmt);
} else {
    echo "<p>❌ ไม่สามารถสร้างตาราง activity_participants ได้: " . print_r(sqlsrv_errors(), true) . "</p>";
}

sqlsrv_close($conn);

echo "<p><a href='setup_activities_data.php' class='btn btn-primary'>ไปเพิ่มข้อมูลตัวอย่าง</a></p>";
?>
