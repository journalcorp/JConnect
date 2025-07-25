<?php
// public/api/api_save_target.php

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once 'dblocal.php';

if (!isset($conn) || $conn === false) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection failed.']);
    exit();
}

// รับข้อมูลจาก request body (JSON)
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!$data) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid JSON input.']);
    exit();
}

// ดึงข้อมูลที่ส่งมา
$userCode = $data['user_code'] ?? null;
$userName = $data['user_name'] ?? null;
$targetID = $data['target_id'] ?? null;
$detailID = $data['detail_id'] ?? null;
$targetName = $data['target_name'] ?? null;
$startDate = $data['start_date'] ?? null;
$endDate = $data['end_date'] ?? null;
$detailType = $data['detail_type'] ?? 'Employee';
$relatedId = $data['related_id'] ?? null;
$relatedName = $data['related_name'] ?? null;
$detailTargetAmount = $data['detail_target_amount'] ?? null;


if (empty($userCode) || empty($userName) || empty($targetName) || empty($startDate) || empty($endDate) || empty($detailType) || !is_numeric($detailTargetAmount) || $detailTargetAmount <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing or invalid required fields.']);
    exit();
}

// แปลงวันที่ให้อยู่ในรูปแบบที่ SQL Server ต้องการ (YYYY-MM-DD)
$startDate = date('Y-m-d', strtotime($startDate));
$endDate = date('Y-m-d', strtotime($endDate));

try {
    sqlsrv_begin_transaction($conn);

    if ($targetID && $detailID) {
        // --- UPDATE Existing Target ---
        // อัปเดตข้อมูลใน SaleTargets
        $updateTargetSql = "
            UPDATE dbo.SaleTargets
            SET
                TargetName = ?,
                TargetDescription = ?,
                StartDate = ?,
                EndDate = ?,
                OverallTargetAmount = ?,
                UpdatedBy = ?,
                UpdatedAt = GETDATE()
            WHERE
                TargetID = ?;
        ";
        $paramsTarget = [
            $targetName,
            "Updated target for employee: " . $userName . " (" . $userCode . ")",
            $startDate,
            $endDate,
            $detailTargetAmount, // OverallTargetAmount (สมมติว่าใช้ค่าจาก detail)
            'System_User', // UpdatedBy
            $targetID
        ];

        $stmtTarget = sqlsrv_query($conn, $updateTargetSql, $paramsTarget);
        if ($stmtTarget === false) {
            throw new Exception("Error updating SaleTargets: " . print_r(sqlsrv_errors(), true));
        }

        // อัปเดตข้อมูลใน SaleTargetDetails
        $updateDetailSql = "
            UPDATE dbo.SaleTargetDetails
            SET
                DetailType = ?,
                RelatedID = ?,
                RelatedName = ?,
                DetailTargetAmount = ?
            WHERE
                DetailID = ? AND TargetID = ?;
        ";
        $paramsDetail = [
            $detailType,
            $relatedId,
            $relatedName,
            $detailTargetAmount,
            $detailID,
            $targetID
        ];

        $stmtDetail = sqlsrv_query($conn, $updateDetailSql, $paramsDetail);
        if ($stmtDetail === false) {
            throw new Exception("Error updating SaleTargetDetails: " . print_r(sqlsrv_errors(), true));
        }

        sqlsrv_commit($conn);
        echo json_encode(['success' => true, 'message' => 'Target updated successfully.']);

    } else {
        // --- INSERT New Target ---
        $insertTargetSql = "
            INSERT INTO dbo.SaleTargets (
                TargetName, TargetDescription, TargetType, StartDate, EndDate, 
                OverallTargetAmount, TargetStatus, CreatedBy, CreatedAt, UpdatedBy, UpdatedAt
            ) VALUES (
                ?, ?, ?, ?, ?, 
                ?, ?, ?, GETDATE(), ?, GETDATE()
            );
            SELECT SCOPE_IDENTITY() AS TargetID;
        ";
        $paramsTarget = [
            $targetName,
            "Target for employee: " . $userName . " (" . $userCode . ")",
            'EmployeeIndividual', // TargetType
            $startDate,
            $endDate,
            $detailTargetAmount,
            'Active',
            'System_User',
            'System_User'
        ];

        $stmtTarget = sqlsrv_query($conn, $insertTargetSql, $paramsTarget);
        if ($stmtTarget === false) {
            throw new Exception("Error inserting into SaleTargets: " . print_r(sqlsrv_errors(), true));
        }

        sqlsrv_next_result($stmtTarget);
        $row = sqlsrv_fetch_array($stmtTarget, SQLSRV_FETCH_ASSOC);
        $newTargetID = $row['TargetID'];

        if (!$newTargetID) {
            throw new Exception("Failed to retrieve new TargetID.");
        }

        $insertDetailSql = "
            INSERT INTO dbo.SaleTargetDetails (
                TargetID, DetailType, RelatedID, RelatedName, DetailTargetAmount
            ) VALUES (
                ?, ?, ?, ?, ?
            );
        ";
        $paramsDetail = [
            $newTargetID,
            $detailType,
            $relatedId,
            $relatedName,
            $detailTargetAmount
        ];

        $stmtDetail = sqlsrv_query($conn, $insertDetailSql, $paramsDetail);
        if ($stmtDetail === false) {
            throw new Exception("Error inserting into SaleTargetDetails: " . print_r(sqlsrv_errors(), true));
        }

        sqlsrv_commit($conn);
        echo json_encode(['success' => true, 'message' => 'Target saved successfully.', 'target_id' => $newTargetID]);
    }

} catch (Exception $e) {
    sqlsrv_rollback($conn);
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
} finally {
    if (isset($conn)) {
        sqlsrv_close($conn);
    }
}
?>