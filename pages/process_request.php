<?php
session_start();
include_once '../config.php'; // ไฟล์สำหรับการเชื่อมต่อฐานข้อมูล

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $conn = dbConnect();

    date_default_timezone_set('Asia/Bangkok');

    // รับค่าจากฟอร์ม
    $requestTitle = $_POST['request_title'];
    $requesterID = $_SESSION['employee_id']; // ใช้ ID ของผู้ใช้งานที่ล็อกอิน
    $requestDate = date('Y-m-d H:i:s');
    $amount = (float) $_POST['amount'];
    $remarks = $_POST['remarks'];


    // ดึง approver_id ของ employee
    $sqlApproverID = "SELECT approver_id FROM employees WHERE employee_id = ?";
    $paramsApproverID = array($requesterID);
    $stmtApproverID = sqlsrv_prepare($conn, $sqlApproverID, $paramsApproverID);
    $approverID = null;
    if ($stmtApproverID && sqlsrv_execute($stmtApproverID)) {
        $rowApproverID = sqlsrv_fetch_array($stmtApproverID, SQLSRV_FETCH_ASSOC);
        if ($rowApproverID && isset($rowApproverID['approver_id'])) {
            $approverID = $rowApproverID['approver_id'];
        }
    }

    // ดึง ApproverLevel1,2,3 จาก approvals
    $approver1 = null;
    $approver2 = null;
    $approver3 = null;
    if ($approverID) {
        $sqlApprovers = "SELECT ApprovalLevel1, ApprovalLevel2, ApprovalLevel3 FROM approvals WHERE ApprovalID = ?";
        $paramsApprovers = array($approverID);
        $stmtApprovers = sqlsrv_prepare($conn, $sqlApprovers, $paramsApprovers);
        if ($stmtApprovers && sqlsrv_execute($stmtApprovers)) {
            $rowApprovers = sqlsrv_fetch_array($stmtApprovers, SQLSRV_FETCH_ASSOC);
            if ($rowApprovers) {
                $approver1 = !empty($rowApprovers['ApprovalLevel1']) ? $rowApprovers['ApprovalLevel1'] : null;
                $approver2 = !empty($rowApprovers['ApprovalLevel2']) ? $rowApprovers['ApprovalLevel2'] : null;
                $approver3 = !empty($rowApprovers['ApprovalLevel3']) ? $rowApprovers['ApprovalLevel3'] : null;
            }
        }
    }


    // สถานะผู้อนุมัติ
    $statusApprover1 = $approver1 ? 'Pending' : null;
    $statusApprover2 = $approver2 ? 'Pending' : null;
    $statusApprover3 = $approver3 ? 'Pending' : null;

    // ถ้าไม่มี ApproverLevel1 ห้าม insert และแจ้งเตือน
    if (!$approver1) {
        header("Location: add_request_form.php?error=" . urlencode('ไม่พบผู้อนุมัติระดับ 1 ในระบบ กรุณาติดต่อ HR หรือผู้ดูแลระบบ'));
        exit;
    }

    // ตรวจสอบจำนวนเงิน
    if ($amount < 10000) {
        // กรณีจำนวนเงินต่ำกว่า 10,000
        $approver2 = '';
        $statusApprover2 = '';
        $approver3 = '';
        $statusApprover3 = '';
    }

    // ดึง PositionID และ approval_line ของผู้ใช้งานที่ล็อกอิน

    $sqlPosition = "SELECT approval_line FROM employees WHERE employee_id = ?";
    $paramsPosition = array($requesterID);
    $stmtPosition = sqlsrv_prepare($conn, $sqlPosition, $paramsPosition);
    if (!$stmtPosition) {
        die("Query failed: " . print_r(sqlsrv_errors(), true));
    }
    if (!sqlsrv_execute($stmtPosition)) {
        die("Query execute failed: " . print_r(sqlsrv_errors(), true));
    }
    $rowPosition = sqlsrv_fetch_array($stmtPosition, SQLSRV_FETCH_ASSOC);
    if ($rowPosition && isset($rowPosition['approval_line'])) {
        $approvalLine = $rowPosition['approval_line'];
        // ดึง ApprovalLevel จากตาราง positions โดยใช้ PositionID และ approval_line
        // หมายเหตุ: ต้องมี $positionID ด้วย (ถ้าไม่มีให้กำหนดค่า default หรือดึงจาก session/employee)
        $positionID = 1; // ตัวอย่าง: กำหนดค่า default (ควรแก้ไขให้ดึงค่าจริง)
        $sqlApprovalLevel = "SELECT ApprovalLevel FROM positions WHERE PositionID = ? AND ApprovalLevel = ?";
        $paramsApprovalLevel = array($positionID, $approvalLine);
        $stmtApprovalLevel = sqlsrv_prepare($conn, $sqlApprovalLevel, $paramsApprovalLevel);
        if ($stmtApprovalLevel && sqlsrv_execute($stmtApprovalLevel)) {
            $rowApprovalLevel = sqlsrv_fetch_array($stmtApprovalLevel, SQLSRV_FETCH_ASSOC);
            if ($rowApprovalLevel && isset($rowApprovalLevel['ApprovalLevel'])) {
                $currentApprovalLevel = $rowApprovalLevel['ApprovalLevel'];
            } else {
                $currentApprovalLevel = 1;
            }
        } else {
            $currentApprovalLevel = 1;
        }
    } else {
        $currentApprovalLevel = 1;
    }

    // บันทึกข้อมูลลงฐานข้อมูล
    $sql = "INSERT INTO Requests (RequestTitle, RequesterID, RequestDate, Amount, Remarks, ApproverLevel1, StatusApprover1, ApproverLevel2, StatusApprover2, ApproverLevel3, StatusApprover3, CurrentApprovalLevel) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $params = array($requestTitle, $requesterID, $requestDate, $amount, $remarks, $approver1, $statusApprover1, $approver2, $statusApprover2, $approver3, $statusApprover3, $currentApprovalLevel);
    $stmt = sqlsrv_prepare($conn, $sql, $params);
    if (!$stmt) {
        die("Prepare failed: " . print_r(sqlsrv_errors(), true));
    }
    if (sqlsrv_execute($stmt)) {
        header("Location: accounting_form.php?success=1");
        exit;
    } else {
        header("Location: add_request_form.php?error=" . urlencode(print_r(sqlsrv_errors(), true)));
        exit;
    }
}

// เมื่อ Approver1,2,3 อนุมัติแล้ว
if (
    $row['StatusApprover1'] === 'Approved' &&
    $row['StatusApprover2'] === 'Approved' &&
    $row['StatusApprover3'] === 'Approved'
) {
    // ส่งต่อไปฝ่ายบัญชี
    $sql = "UPDATE Requests SET StatusAccounting = 'Pending' WHERE RequestID = ?";
    // ... prepare & execute ...
}