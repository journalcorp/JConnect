<?php
// public/api/get_employee_targets.php

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once 'dblocal.php'; // ปรับ path ตามจริง

if (!isset($conn) || $conn === false) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection failed.']);
    exit();
}

$userCode = $_GET['user_code'] ?? null;

if (empty($userCode)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'User code is required.']);
    exit();
}

try {
    // ดึงข้อมูลเป้าหมายที่เกี่ยวข้องกับพนักงานคนนี้
    // โดยเฉพาะเป้าหมายที่มี DetailType เป็น 'Employee' และ RelatedID ตรงกับ user_code
    $sql = "
        SELECT
            ST.TargetID,
            ST.TargetName,
            ST.TargetDescription,
            ST.StartDate,
            ST.EndDate,
            STD.DetailID,
            STD.DetailType,
            STD.RelatedID,
            STD.RelatedName,
            STD.DetailTargetAmount
        FROM
            dbo.SaleTargets AS ST WITH (NOLOCK)
        JOIN
            dbo.SaleTargetDetails AS STD WITH (NOLOCK) ON ST.TargetID = STD.TargetID
        WHERE
            STD.RelatedID = ? -- ใช้ RelatedID เพื่อกรองตาม UserCode
            AND STD.DetailType = 'Employee' -- จำกัดเฉพาะเป้าหมายที่ผูกกับพนักงานโดยตรง
        ORDER BY
            ST.StartDate DESC;
    ";

    $params = array($userCode);
    $stmt = sqlsrv_query($conn, $sql, $params);

    if ($stmt === false) {
        throw new Exception("SQL query failed: " . print_r(sqlsrv_errors(), true));
    }

    $targets = [];
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        // จัดรูปแบบวันที่ให้อ่านง่าย
        $row['StartDateFormatted'] = $row['StartDate'] ? $row['StartDate']->format('Y-m-d') : null;
        $row['EndDateFormatted'] = $row['EndDate'] ? $row['EndDate']->format('Y-m-d') : null;
        
        $targets[] = [
            'target_id' => $row['TargetID'],
            'target_name' => $row['TargetName'],
            'start_date' => $row['StartDateFormatted'],
            'end_date' => $row['EndDateFormatted'],
            'detail_id' => $row['DetailID'],
            'detail_type' => $row['DetailType'],
            'related_id' => $row['RelatedID'],
            'related_name' => $row['RelatedName'],
            'detail_target_amount' => $row['DetailTargetAmount'],
        ];
    }

    echo json_encode([
        'success' => true,
        'data' => $targets
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