<?php
// เคลียร์ output buffer ก่อนส่ง headers
while (ob_get_level()) {
    ob_end_clean();
}

include '../config.php';



// เชื่อมต่อฐานข้อมูล

$conn = dbConnect();

if (!$conn) {

    die("Connection failed: " . print_r(sqlsrv_errors(), true));

}    // ดึงข้อมูลจากฐานข้อมูล - แสดงแต่ละรายการการประเมินแยกตามผู้ประเมิน
    $sql = "
        SELECT 
            fsc.TransactionID,
            fsc.emp_pe,
            fsc.emp_as,
            CONCAT(e2.first_name_th, ' ', e2.last_name_th) AS emp_pe_fullname,
            e2.position_th,
            d.DeptName,
            fsc.j_score,
            fsc.o_score,
            fsc.u_score,
            fsc.r_score,
            fsc.n_score,
            fsc.a_score,
            fsc.l_score,
            (fsc.j_score + fsc.o_score + fsc.u_score + fsc.r_score + fsc.n_score + fsc.a_score + fsc.l_score) as total_scores,
            ISNULL(ef.evaluation_period, 'ไม่ระบุ') AS evaluation_period
        FROM form_score fsc
        INNER JOIN employees e2 ON fsc.emp_pe = e2.employee_id
        INNER JOIN dept d ON e2.division = d.DeptID
        LEFT JOIN evaluation_forms ef ON fsc.TransactionID = ef.TransactionID 
            AND fsc.FormID = ef.form_id 
            AND fsc.emp_as = ef.emp_as 
            AND fsc.emp_pe = ef.emp_pe
        WHERE fsc.status_form = 'enable'
        AND (fsc.j_score > 0 OR fsc.o_score > 0 OR fsc.u_score > 0 OR fsc.r_score > 0 OR fsc.n_score > 0 OR fsc.a_score > 0 OR fsc.l_score > 0)
        ORDER BY fsc.emp_pe
    ";



$result = sqlsrv_query($conn, $sql);



if (!$result) {

    die("Query failed: " . print_r(sqlsrv_errors(), true));

}



// สร้างไฟล์ CSV
$current_date = date('Y-m-d_H-i-s');
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="form_summary_simplified_' . $current_date . '.csv"');
header('Pragma: no-cache');
header('Expires: 0');
header('Cache-Control: no-cache, no-store, must-revalidate');



$output = fopen('php://output', 'w');



// เพิ่ม BOM เพื่อรองรับภาษาไทยใน Excel

fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));    // เขียนหัวตาราง
    fputcsv($output, [
        'รหัสผู้ถูกประเมิน',
        'ชื่อผู้ถูกประเมิน', 
        'ตำแหน่ง', 
        'ฝ่าย',
        'รอบการประเมิน',
        'จำนวนคะแนน Joyfulness', 'ความคิดเห็น Joyfulness',
        'จำนวนคะแนน Openness', 'ความคิดเห็น Openness',
        'จำนวนคะแนน Understanding', 'ความคิดเห็น Understanding',
        'จำนวนคะแนน Respect', 'ความคิดเห็น Respect',
        'จำนวนคะแนน Knowledge', 'ความคิดเห็น Knowledge',
        'จำนวนคะแนน Accountability', 'ความคิดเห็น Accountability',
        'จำนวนคะแนน Leadership', 'ความคิดเห็น Leadership',
        'จำนวนคะแนนรวมทั้งหมด'
    ]);

// ดึงข้อมูลทั้งหมดจากผลลัพธ์ และดึงความคิดเห็นสำหรับแต่ละ TransactionID
$rows = [];
while ($row = sqlsrv_fetch_array($result, SQLSRV_FETCH_ASSOC)) {
    // ดึงความคิดเห็นสำหรับแต่ละ TransactionID
    $commentSQL = "
        SELECT category, reason 
        FROM form_score_details 
        WHERE TransactionID = ?
    ";
    $commentResult = sqlsrv_query($conn, $commentSQL, [$row['TransactionID']]);
    
    // เริ่มต้นความคิดเห็นทั้งหมด
    $comments = [
        'j_reason' => 'ไม่มีความคิดเห็น',
        'o_reason' => 'ไม่มีความคิดเห็น',
        'u_reason' => 'ไม่มีความคิดเห็น',
        'r_reason' => 'ไม่มีความคิดเห็น',
        'n_reason' => 'ไม่มีความคิดเห็น',
        'a_reason' => 'ไม่มีความคิดเห็น',
        'l_reason' => 'ไม่มีความคิดเห็น'
    ];
    
    // ใส่ความคิดเห็นที่มี
    if ($commentResult) {
        while ($comment = sqlsrv_fetch_array($commentResult, SQLSRV_FETCH_ASSOC)) {
            if ($comment['reason'] && trim($comment['reason']) !== '') {
                switch ($comment['category']) {
                    case 'j_score':
                        $comments['j_reason'] = $comment['reason'];
                        break;
                    case 'o_score':
                        $comments['o_reason'] = $comment['reason'];
                        break;
                    case 'u_score':
                        $comments['u_reason'] = $comment['reason'];
                        break;
                    case 'r_score':
                        $comments['r_reason'] = $comment['reason'];
                        break;
                    case 'n_score':
                        $comments['n_reason'] = $comment['reason'];
                        break;
                    case 'a_score':
                        $comments['a_reason'] = $comment['reason'];
                        break;
                    case 'l_score':
                        $comments['l_reason'] = $comment['reason'];
                        break;
                }
            }
        }
    }
    
    $rows[] = array_merge($row, $comments);
}

// ตรวจสอบว่ามีข้อมูลหรือไม่
if (empty($rows)) {
    fputcsv($output, ['ไม่พบข้อมูลการประเมิน']);
} else {
    // เขียนข้อมูล
    foreach ($rows as $row) {
        fputcsv($output, [
            $row['emp_pe'],
            $row['emp_pe_fullname'],
            $row['position_th'],
            $row['DeptName'],
            $row['evaluation_period'] ?: 'ไม่ระบุ',
            number_format($row['j_score'] ?? 0, 2), $row['j_reason'] ?: 'ไม่มีความคิดเห็น',
            number_format($row['o_score'] ?? 0, 2), $row['o_reason'] ?: 'ไม่มีความคิดเห็น',
            number_format($row['u_score'] ?? 0, 2), $row['u_reason'] ?: 'ไม่มีความคิดเห็น',
            number_format($row['r_score'] ?? 0, 2), $row['r_reason'] ?: 'ไม่มีความคิดเห็น',
            number_format($row['n_score'] ?? 0, 2), $row['n_reason'] ?: 'ไม่มีความคิดเห็น',
            number_format($row['a_score'] ?? 0, 2), $row['a_reason'] ?: 'ไม่มีความคิดเห็น',
            number_format($row['l_score'] ?? 0, 2), $row['l_reason'] ?: 'ไม่มีความคิดเห็น',
            number_format($row['total_scores'] ?? 0, 2)
        ]);
    }
}

fclose($output);
sqlsrv_close($conn);
exit;
?>