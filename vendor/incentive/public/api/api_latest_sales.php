<?php

set_time_limit(60);

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

include '../db.php';


$sql = "
SELECT TOP 10
    A.FDCreateOn AS doc_date,
    B.FTXshDocNo AS doc_no,
    C.FTBchName AS branch,
    D.FTUsrName AS staff,
    Q.FTXshCstName AS customer_name,
    A.FTXsdPdtName AS product_name,
    CAST(A.FCXsdQty AS DECIMAL(18,2)) AS quantity,
    CAST(A.FCXsdNetAfHD AS DECIMAL(18,2)) AS net_total
FROM TPSTSalHD AS B
LEFT JOIN (
    SELECT FTXshDocNo, FTBchCode, FTXshRefInt
    FROM TPSTSalHD
    WHERE FNXshDocType = 9 AND ISNULL(FTXshRefInt , '') <> ''
) AS BB ON BB.FTXshRefInt = B.FTXshDocNo AND B.FTBchCode = BB.FTBchCode
LEFT JOIN TPSTSalDT AS A WITH (NOLOCK) ON B.FTXshDocNo = A.FTXshDocNo
LEFT JOIN TCNMBranch_L AS C WITH (NOLOCK) ON B.FTBchCode = C.FTBchCode
LEFT JOIN TCNMUser_L AS D WITH (NOLOCK) ON B.FTCreateBy = D.FTUsrCode
LEFT JOIN TPSTSalHDCst AS Q WITH (NOLOCK) ON A.FTXshDocNo = Q.FTXshDocNo
WHERE 1=1
    AND B.FNXshDocType = 1
    AND ISNULL(BB.FTXshDocNo, '') = ''
    AND A.FTXsdStaPdt = 1
    AND A.FCXsdNetAfHD > 0 -- เพิ่มเงื่อนไขนี้
ORDER BY A.FDCreateOn DESC
";

$stmt = sqlsrv_query($conn, $sql);

if ($stmt === false) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'เกิดข้อผิดพลาดในการดึงข้อมูลจากฐานข้อมูล: ' . print_r(sqlsrv_errors(), true)
    ]);
    exit;
}

$data = [];
while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    if (isset($row['doc_date']) && $row['doc_date'] instanceof DateTime) {
        $row['doc_date'] = $row['doc_date']->format('Y-m-d H:i:s');
    }
    $data[] = $row;
}

echo json_encode([
    'success' => true,
    'data' => $data,
    'count' => count($data),
    'message' => 'ดึงข้อมูล 10 รายการยอดขายล่าสุดสำเร็จ'
]);
exit;
?>