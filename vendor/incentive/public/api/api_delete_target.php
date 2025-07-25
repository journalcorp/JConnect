<?php
/**
 * api_delete_target.php
 *
 * API endpoint สำหรับลบข้อมูลเป้าหมาย (target) และรายละเอียดเป้าหมาย (target_detail)
 *
 * - รับค่า target_id และ detail_id ผ่าน JSON POST body
 * - ลบรายการจาก target_details ก่อน
 * - ตรวจสอบว่า target_id นั้นมีรายละเอียดอื่นเหลืออยู่หรือไม่
 * - หากไม่เหลือรายละเอียดแล้ว จะลบรายการจาก targets ด้วย
 * - ใช้ Transaction เพื่อให้มั่นใจว่าข้อมูลถูกลบอย่างถูกต้องหรือไม่มีการเปลี่ยนแปลงเลยหากเกิดข้อผิดพลาด
 * - ส่งคืน JSON response แสดงสถานะความสำเร็จหรือล้มเหลว
 */

// กำหนด Content-Type ของ HTTP Response เป็น application/json
// เพื่อให้ Browser/Client รู้ว่าข้อมูลที่ส่งกลับมาเป็นรูปแบบ JSON
header('Content-Type: application/json');

// *** การตั้งค่า CORS (Cross-Origin Resource Sharing) ***
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

// *** ดักจับ Preflight Request (HTTP OPTIONS Method) ของ CORS ***
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// *** การรวมไฟล์เชื่อมต่อฐานข้อมูล (dblocal.php) ***
require_once 'dblocal.php';

// ตรวจสอบการเชื่อมต่อฐานข้อมูล
if (!isset($conn) || $conn === false) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection failed. Check dblocal.php.']);
    exit();
}

// รับข้อมูล JSON ที่ส่งมาใน Request Body
$input = file_get_contents('php://input');
$data = json_decode($input, true);

// ตรวจสอบว่าได้รับค่า 'target_id' และ 'detail_id' จาก JSON หรือไม่
if (!isset($data['target_id']) || !isset($data['detail_id'])) {
    http_response_code(400); // Bad Request
    echo json_encode(['success' => false, 'message' => 'Missing target_id or detail_id in request payload.']);
    exit();
}

// กำหนดค่าที่ได้รับจาก JSON ไปยังตัวแปร
$target_id = $data['target_id'];
$detail_id = $data['detail_id'];

// *** เริ่มต้น Transaction สำหรับ SQL Server ***
// การจัดการ transaction ใน SQLSRV ต้องใช้ฟังก์ชันแยก
if (sqlsrv_begin_transaction($conn) === false) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to begin transaction: ' . print_r(sqlsrv_errors(), true)]);
    exit();
}

try {
    // 1. ลบข้อมูลจากตาราง 'target_details' ก่อน
    $sql_delete_detail = "DELETE FROM dbo.SaleTargetDetails WHERE DetailID = ? AND TargetID = ?";
    $params_delete_detail = array($detail_id, $target_id);

    $stmt_delete_detail = sqlsrv_query($conn, $sql_delete_detail, $params_delete_detail);

    if ($stmt_delete_detail === false) {
        throw new Exception("Failed to delete from SaleTargetDetails: " . print_r(sqlsrv_errors(), true));
    }

    $rows_affected_detail = sqlsrv_rows_affected($stmt_delete_detail);

    if ($rows_affected_detail === false || $rows_affected_detail === 0) {
        // หากไม่พบข้อมูลที่ตรงกับเงื่อนไขการลบ หรือ affected_rows เป็น 0
        http_response_code(404);
        throw new Exception("Target detail with ID " . $detail_id . " for Target ID " . $target_id . " not found or already deleted. No rows affected.");
    }

    // 2. ตรวจสอบว่ายังมีรายละเอียด (SaleTargetDetails) อื่นๆ ที่ผูกกับ target_id นี้เหลืออยู่หรือไม่
    $sql_check_details = "SELECT COUNT(*) AS RemainingDetailsCount FROM dbo.SaleTargetDetails WHERE TargetID = ?";
    $params_check_details = array($target_id);

    $stmt_check_details = sqlsrv_query($conn, $sql_check_details, $params_check_details);

    if ($stmt_check_details === false) {
        throw new Exception("Failed to check remaining details: " . print_r(sqlsrv_errors(), true));
    }

    $remaining_details_row = sqlsrv_fetch_array($stmt_check_details, SQLSRV_FETCH_ASSOC);
    $remaining_details_count = $remaining_details_row['RemainingDetailsCount'];

    // ถ้า $remaining_details_count เป็น 0 (หมายความว่าไม่เหลือรายละเอียดใดๆ สำหรับ target_id นี้แล้ว)
    // ให้ทำการลบเป้าหมายหลัก (target) จากตาราง 'SaleTargets' ด้วย
    if ($remaining_details_count == 0) {
        $sql_delete_target = "DELETE FROM dbo.SaleTargets WHERE TargetID = ?";
        $params_delete_target = array($target_id);

        $stmt_delete_target = sqlsrv_query($conn, $sql_delete_target, $params_delete_target);

        if ($stmt_delete_target === false) {
            throw new Exception("Failed to delete from SaleTargets table: " . print_r(sqlsrv_errors(), true));
        }
        
        $rows_affected_target = sqlsrv_rows_affected($stmt_delete_target);
        if ($rows_affected_target === false || $rows_affected_target === 0) {
            // แม้ว่าจะลบ detail ไปแล้ว แต่ target หลักไม่ถูกลบ อาจเกิดจากไม่มี target ID นั้นจริง ๆ
            throw new Exception("Target with ID " . $target_id . " not found or already deleted (after all details removed).");
        }
    }

    // หากทุกขั้นตอนภายใน try block สำเร็จทั้งหมด
    // ให้ยืนยัน Transaction (Commit) เพื่อบันทึกการเปลี่ยนแปลงลงในฐานข้อมูลอย่างถาวร
    sqlsrv_commit($conn);
    echo json_encode(['success' => true, 'message' => 'Target detail and associated target (if no other details exist) deleted successfully.']);

} catch (Exception $e) {
    // หากเกิดข้อผิดพลาดใดๆ ภายใน try block
    // ให้ยกเลิก Transaction (Rollback) เพื่อย้อนกลับการเปลี่ยนแปลงทั้งหมด
    if ($conn) {
        sqlsrv_rollback($conn);
    }
    // ส่ง JSON response แสดงความล้มเหลวพร้อม message จาก Exception
    if (http_response_code() === 200) { // หากยังเป็น 200 (OK) อยู่ แสดงว่า Error ไม่ได้ถูกตั้งค่าไปแล้ว
        http_response_code(500); // Internal Server Error
    }
    echo json_encode(['success' => false, 'message' => 'Database operation failed: ' . $e->getMessage()]);
} finally {
    // ปิดการเชื่อมต่อฐานข้อมูล
    if (isset($conn)) {
        sqlsrv_close($conn);
    }
}
?>