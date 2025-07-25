<?php
// เปิด error reporting สำหรับ debug
error_reporting(E_ALL);
ini_set('display_errors', 1);

// เริ่มต้น session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include_once '../includes/header.php';
include_once '../includes/menu.php';
include_once '../config.php'; // ไฟล์สำหรับการเชื่อมต่อฐานข้อมูล

// สร้างการเชื่อมต่อฐานข้อมูล
$conn = dbConnect();

// ตรวจสอบว่า employee_id มีอยู่ใน Session หรือไม่
if (!isset($_SESSION['employee_id'])) {
    header('Location: ../index.php');
    exit;
}

$employeeID = $_SESSION['employee_id'];

// ฟังก์ชันเพิ่มกิจกรรม
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_activity'])) {
    $activity_name = $_POST['activity_name'];
    $activity_date = $_POST['activity_date'];
    $activity_description = $_POST['activity_description'];

    $sql = "INSERT INTO activities (name, start_date, description) VALUES (?, ?, ?)";
    $params = array($activity_name, $activity_date, $activity_description);
    $stmt = sqlsrv_query($conn, $sql, $params);
    
    if ($stmt) {
        sqlsrv_free_stmt($stmt);
        $_SESSION['message'] = "เพิ่มกิจกรรมเรียบร้อยแล้ว";
    } else {
        $_SESSION['message'] = "เกิดข้อผิดพลาดในการเพิ่มกิจกรรม";
    }
    header('Location: activity_dashboard.php');
    exit;
}

// ฟังก์ชันแปลงวันที่เป็นภาษาไทย
function formatDateThai($date) {
    if (!$date) return 'N/A';
    
    $months = [
        "01" => "มกราคม", "02" => "กุมภาพันธ์", "03" => "มีนาคม",
        "04" => "เมษายน", "05" => "พฤษภาคม", "06" => "มิถุนายน",
        "07" => "กรกฎาคม", "08" => "สิงหาคม", "09" => "กันยายน",
        "10" => "ตุลาคม", "11" => "พฤศจิกายน", "12" => "ธันวาคม"
    ];

    try {
        if (is_object($date)) {
            $dateObj = $date;
        } else {
            $dateObj = new DateTime($date);
        }
        
        $day = $dateObj->format('d');
        $month = $months[$dateObj->format('m')];
        $year = $dateObj->format('Y') + 543;

        return "$day $month $year";
    } catch (Exception $e) {
        return "N/A";
    }
}

// เริ่มต้นตัวแปร
$activities = [];
$searchParams = [];
$whereConditions = [];

// สร้าง SQL query หลัก - ดึงข้อมูลกิจกรรมทั้งหมด
$sql = "SELECT a.id, a.name, a.description, a.start_date, a.end_date, a.score,
               CASE 
                   WHEN ap.approved IS NULL THEN NULL
                   WHEN ap.approved = 1 THEN 1
                   ELSE 0
               END AS approved,
               ap.joined_at
        FROM activities a
        LEFT JOIN activity_participants ap ON a.id = ap.activity_id AND ap.employee_id = ?";

// เพิ่ม employee_id เป็น parameter แรก (แปลงเป็น int)
$searchParams[] = (int)$_SESSION['employee_id'];

// เพิ่มเงื่อนไขการค้นหา
if (!empty($_GET['search_name'])) {
    $whereConditions[] = "a.name LIKE ?";
    $searchParams[] = '%' . $_GET['search_name'] . '%';
}

if (!empty($_GET['search_start_date'])) {
    $whereConditions[] = "CAST(a.start_date AS DATE) >= ?";
    $searchParams[] = $_GET['search_start_date'];
}

// เพิ่ม WHERE clause ถ้ามีเงื่อนไข
if (!empty($whereConditions)) {
    $sql .= " WHERE " . implode(" AND ", $whereConditions);
}

$sql .= " ORDER BY a.start_date DESC";

// ดึงข้อมูลกิจกรรม
$stmt = sqlsrv_query($conn, $sql, $searchParams);

if ($stmt) {
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $activities[] = $row;
    }
    sqlsrv_free_stmt($stmt);
    
    // Debug: แสดงจำนวนข้อมูลที่ดึงมาได้
    if (isset($_GET['debug']) && $_GET['debug'] == '1') {
        echo "<div class='alert alert-info'>พบข้อมูลกิจกรรม: " . count($activities) . " รายการ</div>";
    }
} else {
    // หากไม่สามารถดึงข้อมูลได้ ให้ลองดึงข้อมูลพื้นฐานจากตาราง activities
    $simpleSql = "SELECT id, name, description, start_date, end_date, score FROM activities";
    $simpleStmt = sqlsrv_query($conn, $simpleSql);
    
    if ($simpleStmt) {
        while ($row = sqlsrv_fetch_array($simpleStmt, SQLSRV_FETCH_ASSOC)) {
            $row['approved'] = null; // ไม่มีสถานะการเข้าร่วม
            $activities[] = $row;
        }
        sqlsrv_free_stmt($simpleStmt);
    }
    
    // แสดง error เฉพาะเมื่อมี debug parameter
    if (isset($_GET['debug']) && $_GET['debug'] == '1') {
        echo "<div class='alert alert-danger'>Error ในการดึงข้อมูล: " . print_r(sqlsrv_errors(), true) . "</div>";
        echo "<div class='alert alert-warning'>SQL Query: " . htmlspecialchars($sql) . "</div>";
        echo "<div class='alert alert-info'>Parameters: " . print_r($searchParams, true) . "</div>";
    }
}

// ตรวจสอบว่าตาราง activities มีข้อมูลหรือไม่
if (empty($activities) && empty($_GET['search_name']) && empty($_GET['search_start_date'])) {
    // ตรวจสอบว่าตารางมีข้อมูลหรือไม่
    $checkSql = "SELECT COUNT(*) as total FROM activities";
    $checkStmt = sqlsrv_query($conn, $checkSql);
    if ($checkStmt) {
        $checkRow = sqlsrv_fetch_array($checkStmt, SQLSRV_FETCH_ASSOC);
        if ($checkRow['total'] == 0) {
            $_SESSION['no_data'] = true;
        }
        sqlsrv_free_stmt($checkStmt);
    }
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Activity Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body {
            font-family: 'Prompt', sans-serif;
            background-color: #f8f9fa;
        }
        .container {
            max-width: 1200px;
        }
        .card {
            border-radius: 12px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            border: none;
        }
        .card-header {
            background-color: #007bff;
            color: #fff;
            font-weight: bold;
            font-size: 1.2rem;
            border-radius: 12px 12px 0 0;
        }
        .table th {
            background-color: #f1f3f5;
            font-weight: bold;
        }
        .btn-primary {
            background-color: #007bff;
            border-color: #007bff;
        }
        .btn-primary:hover {
            background-color: #0056b3;
            border-color: #0056b3;
        }
        .btn-success {
            background-color: #28a745;
            border-color: #28a745;
        }
        .btn-success:hover {
            background-color: #218838;
            border-color: #218838;
        }
        .btn-info {
            background-color: #17a2b8;
            border-color: #17a2b8;
        }
        .btn-info:hover {
            background-color: #138496;
            border-color: #138496;
        }
    </style>
</head>
<body>
<div class="container mt-5">
    <div class="card">
        <div class="card-header text-center">
            <i class="bi bi-calendar-event"></i> ตารางกิจกรรม
        </div>
        <div class="card-body">
           

            <!-- ฟอร์มค้นหากิจกรรม -->
            <form method="GET" class="mb-4">
                <div class="row g-3">
                    <div class="col-md-4">
                        <input type="text" name="search_name" class="form-control" placeholder="ค้นหาชื่อกิจกรรม" value="<?php echo htmlspecialchars($_GET['search_name'] ?? ''); ?>">
                    </div>
                    <div class="col-md-4">
                        <input type="date" name="search_start_date" class="form-control" placeholder="วันที่เริ่ม" value="<?php echo htmlspecialchars($_GET['search_start_date'] ?? ''); ?>">
                    </div>
                    <div class="col-md-4 text-end">
                        <button type="submit" class="btn btn-primary"><i class="bi bi-search"></i> ค้นหา</button>
                        <a href="add_activity.php" class="btn btn-primary"><i class="bi bi-plus-circle"></i> เพิ่มกิจกรรม</a>
                        <?php if (isset($_GET['debug'])): ?>
                            <a href="?" class="btn btn-secondary btn-sm">ปิด Debug</a>
                        <?php else: ?>
                            <a href="?debug=1" class="btn btn-outline-secondary btn-sm">Debug</a>
                        <?php endif; ?>
                    </div>
                </div>
            </form>

            <!-- ตารางกิจกรรม -->
            <h3 class="text-center mb-4">รายการกิจกรรม</h3>
            <div class="table-responsive">
                <table class="table table-bordered table-hover">
                    <thead>
                    <tr>
    <th>ชื่อกิจกรรม</th>
    <th>วันที่เริ่ม</th>
    <th>วันสิ้นสุด</th>
    <th>คะแนนที่ได้รับ</th>
    <th>สถานะการเข้าร่วม</th>
    <th>การดำเนินการ</th>
</tr>
                    </thead>
                    <tbody>
                    <?php if (!empty($activities)): ?>
                        <?php foreach ($activities as $row): ?>
<tr>
    <td><?php echo htmlspecialchars($row['name']); ?></td>
    <td><?php echo htmlspecialchars(formatDateThai($row['start_date'])); ?></td>
    <td><?php echo htmlspecialchars(formatDateThai($row['end_date'])); ?></td>
    <td><?php echo htmlspecialchars($row['score']); ?></td>
    <td>
        <?php 
        if ($row['approved'] === null) {
        echo '<span class="text-danger">ยังไม่ได้เข้าร่วม</span>';
    } elseif ($row['approved'] == 1) {
        echo '<span class="text-success">อนุมัติแล้ว</span>';
    } elseif ($row['approved'] == 0) {
        echo '<span class="text-warning">รอการอนุมัติ</span>';
    }
        ?>
    </td>
    <td>
        <a href="activity_detail.php?id=<?php echo $row['id']; ?>" class="btn btn-info btn-sm text-white">
            <i class="bi bi-eye"></i> ดูรายละเอียด
        </a>
        <?php if ($row['approved'] === null) { ?>
            <a href="join_activity.php?id=<?php echo $row['id']; ?>" class="btn btn-success btn-sm">
                <i class="bi bi-check-circle"></i> เข้าร่วม
            </a>
        <?php } elseif ($row['approved'] == 1) { ?>
            <button class="btn btn-secondary btn-sm" disabled>
                <i class="bi bi-check-circle"></i> เข้าร่วมแล้ว
            </button>
        <?php } elseif ($row['approved'] == 0) { ?>
            <button class="btn btn-secondary btn-sm" disabled>
                <i class="bi bi-check-circle"></i> รอการอนุมัติ
            </button>
        <?php } ?>
    </td>
</tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" class="text-center">
                                <?php if (isset($_SESSION['no_data'])): ?>
                                    <div class="alert alert-warning mb-0">
                                        <i class="bi bi-exclamation-triangle"></i> ไม่มีกิจกรรมในระบบ
                                        <br><a href="setup_activities_data.php" class="btn btn-primary btn-sm mt-2">
                                            <i class="bi bi-plus-circle"></i> เพิ่มข้อมูลตัวอย่าง
                                        </a>
                                    </div>
                                    <?php unset($_SESSION['no_data']); ?>
                                <?php else: ?>
                                    <i class="bi bi-search"></i> ไม่พบข้อมูลกิจกรรมที่ตรงกับเงื่อนไขการค้นหา
                                    <br><small class="text-muted">ลองปรับเปลี่ยนคำค้นหาหรือวันที่</small>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

<?php
// แสดงข้อความ session (ถ้ามี)
if (isset($_SESSION['message'])) {
    echo '<div class="alert alert-info text-center mt-3">';
    echo $_SESSION['message'];
    unset($_SESSION['message']);
    echo '</div>';
}

// ปิดการเชื่อมต่อฐานข้อมูล
if ($conn) {
    sqlsrv_close($conn);
}
?>
