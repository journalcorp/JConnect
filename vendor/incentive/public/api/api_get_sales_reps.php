<?php
// public/api/get_sales_reps.php
ini_set('display_errors', 1); // เปิดการแสดงผลข้อผิดพลาด
error_reporting(E_ALL);     // แสดงข้อผิดพลาดทุกประเภท

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once '../db.php';

if (!isset($conn) || $conn === false) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection failed.']);
    exit();
}

try {
    // --- รับพารามิเตอร์สำหรับ Pagination ---
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 30; // Default 30 รายการต่อหน้า

    // ตรวจสอบค่า page และ limit ไม่ให้ติดลบหรือเป็นศูนย์
    if ($page < 1) $page = 1;
    if ($limit < 1) $limit = 30; // กำหนดค่า default ถ้า invalid

    $offset = ($page - 1) * $limit;

    // --- 1. นับจำนวนรายการทั้งหมด (Distinct Users) สำหรับ Pagination ---
    $countSql = "
        SELECT COUNT(DISTINCT UL.FTUsrCode) AS total_records
        FROM TCNMUser_L AS UL WITH (NOLOCK)
        LEFT JOIN TPSTSalHD AS SH WITH (NOLOCK) ON UL.FTUsrCode = SH.FTCreateBy
        LEFT JOIN TCNMBranch_L AS BL WITH (NOLOCK) ON SH.FTBchCode = BL.FTBchCode AND BL.FNLngID = 1
        WHERE BL.FTBchName IS NOT NULL;
    ";
    $countStmt = sqlsrv_query($conn, $countSql);
    if ($countStmt === false) {
        throw new Exception("Count query failed: " . print_r(sqlsrv_errors(), true));
    }
    $totalRow = sqlsrv_fetch_array($countStmt, SQLSRV_FETCH_ASSOC);
    $totalRecords = $totalRow['total_records'];

    // --- 2. ดึงข้อมูลพนักงานและสาขาทั้งหมดสำหรับพนักงานที่อยู่ในหน้าปัจจุบัน ---
    // ใช้ CTE หรือ Subquery เพื่อเลือก FTUsrCode ที่ต้องการในหน้าปัจจุบันก่อน
    // จากนั้นค่อย JOIN กลับไปดึงสาขาที่เกี่ยวข้องทั้งหมดของ FTUsrCode เหล่านั้น
    $sql = "
        WITH PagedUsers AS (
            SELECT DISTINCT UL.FTUsrCode, UL.FTUsrName
            FROM TCNMUser_L AS UL WITH (NOLOCK)
            LEFT JOIN TPSTSalHD AS SH WITH (NOLOCK) ON UL.FTUsrCode = SH.FTCreateBy
            LEFT JOIN TCNMBranch_L AS BL WITH (NOLOCK) ON SH.FTBchCode = BL.FTBchCode AND BL.FNLngID = 1
            WHERE BL.FTBchName IS NOT NULL
            ORDER BY UL.FTUsrName ASC, UL.FTUsrCode ASC -- ต้องมี ORDER BY สำหรับ OFFSET/FETCH
            OFFSET ? ROWS FETCH NEXT ? ROWS ONLY
        )
        SELECT
            PU.FTUsrCode AS user_code,
            PU.FTUsrName AS user_name,
            BL.FTBchName AS branch_name
        FROM PagedUsers AS PU
        LEFT JOIN TPSTSalHD AS SH WITH (NOLOCK) ON PU.FTUsrCode = SH.FTCreateBy
        LEFT JOIN TCNMBranch_L AS BL WITH (NOLOCK) ON SH.FTBchCode = BL.FTBchCode AND BL.FNLngID = 1 AND BL.FNLngID = 1
        WHERE BL.FTBchName IS NOT NULL
        ORDER BY PU.FTUsrName ASC, BL.FTBchName ASC;
    ";

    $stmt = sqlsrv_query($conn, $sql, array($offset, $limit));

    if ($stmt === false) {
        throw new Exception("SQL query failed: " . print_r(sqlsrv_errors(), true));
    }

    $groupedSalesReps = [];
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $userCode = $row['user_code'];
        $userName = $row['user_name'];
        $branchName = $row['branch_name'];

        // ถ้ายังไม่มี user_code นี้ใน array ให้สร้างโครงสร้างขึ้นมา
        if (!isset($groupedSalesReps[$userCode])) {
            $groupedSalesReps[$userCode] = [
                'user_code' => $userCode,
                'user_name' => $userName,
                'branches' => [] // เก็บชื่อสาขาใน array
            ];
        }
        // เพิ่มชื่อสาขาเข้าไปใน array ของ user นั้นๆ
        if (!empty($branchName)) {
            $groupedSalesReps[$userCode]['branches'][$branchName] = $branchName; // ใช้ชื่อสาขาเป็น key เพื่อป้องกันสาขาซ้ำ
        }
    }

    $finalSalesReps = [];
    foreach ($groupedSalesReps as $userCode => $data) {
        // นำชื่อสาขาที่เก็บไว้ใน array มารวมกันด้วย ", "
        $branchNames = array_values($data['branches']); // ดึงเฉพาะค่า (ชื่อสาขา) ออกมา
        $combinedBranchName = !empty($branchNames) ? implode(', ', $branchNames) : 'N/A'; // หรือ 'ไม่ระบุ'

        $finalSalesReps[] = [
            'user_code' => $data['user_code'],
            'user_name' => $data['user_name'],
            'branch_name' => $combinedBranchName // ใช้ชื่อสาขาที่รวมกันแล้ว
        ];
    }

    // ส่งข้อมูลกลับเป็น JSON พร้อม total_records และ current_page
    echo json_encode([
        'success' => true,
        'data' => $finalSalesReps,
        'total_records' => $totalRecords,
        'current_page' => $page,
        'per_page' => $limit
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
} finally {
    if (isset($conn)) {
        sqlsrv_close($conn);
    }
}
?>