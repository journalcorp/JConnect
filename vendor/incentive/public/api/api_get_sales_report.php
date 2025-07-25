<?php
/**
 * api_get_sales_for_incentive.php
 *
 * API endpoint สำหรับดึงข้อมูลยอดขายที่จำเป็นสำหรับการคำนวณ Incentive ของพนักงาน
 * โดยจะดึงเฉพาะ field ที่จำเป็นและกรองตามช่วงวันที่ เพื่อให้โหลดได้รวดเร็ว
 *
 * - รับ start_date และ end_date เป็นพารามิเตอร์ GET
 * - คืนค่าเป็น JSON array ของยอดขายแต่ละรายการพร้อม user_code และ user_name
 */

set_time_limit(180); // เพิ่มเวลาการทำงานสูงสุดของสคริปต์เป็น 180 วินาที หากข้อมูลมีขนาดใหญ่

// กำหนด HTTP Headers สำหรับ JSON response และ CORS
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *'); // อนุญาตให้ทุกโดเมนเข้าถึงได้ (สำหรับ development, ควรจำกัดใน production)
header('Access-Control-Allow-Methods: GET'); // อนุญาตเฉพาะ method GET
header('Access-Control-Allow-Headers: Content-Type'); // อนุญาต Content-Type header

// *** การตั้งค่า CORS (Cross-Origin Resource Sharing) ***
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once '../db.php'; 

// ตรวจสอบว่ามีการส่ง start_date และ end_date มาหรือไม่
if (empty($_GET['start_date']) || empty($_GET['end_date'])) {
    http_response_code(400); // 400 Bad Request
    echo json_encode([
        'success' => false,
        'message' => 'กรุณาระบุ start_date และ end_date ก่อนเรียกข้อมูลยอดขาย.'
    ]);
    exit;
}

// รับค่า start_date และ end_date จาก GET parameter
$startDate = $_GET['start_date'];
$endDate = $_GET['end_date'];

// ============================
// ดึงข้อมูลยอดขายสำหรับคำนวณ Incentive
// - ดึงเฉพาะ field ที่จำเป็นเพื่อลด overhead ในการโหลด
// - กรองเฉพาะรายการขาย (FNXshDocType = 1) และสินค้าที่ถูกต้อง (FTXsdStaPdt = 1)
// - ไม่รวมรายการที่เป็นเอกสารอ้างอิงภายใน (ISNULL(BB.FTXshDocNo, '') = '')
// ============================
$sql = "
SELECT
    -- ข้อมูลวันที่และเอกสาร
    CONVERT(VARCHAR(10), B.FDCreateOn, 121) AS sale_date, -- วันที่สร้างเอกสาร (YYYY-MM-DD)
    B.FTXshDocNo AS doc_no, -- เลขที่เอกสารขาย

    -- ข้อมูลพนักงานขาย (User ที่สร้างเอกสาร)
    D.FTUsrCode AS user_code, -- รหัสพนักงาน
    D.FTUsrName AS user_name, -- ชื่อพนักงาน

    -- ยอดขายสุทธิของแต่ละรายการสินค้า (หรือรวมตามต้องการ)
    A.FTPdtCode AS product_code,          -- รหัสสินค้า
    A.FTXsdPdtName AS product_name,      -- ชื่อสินค้า
    CAST(A.FCXsdQty AS DECIMAL(18,2)) AS quantity, -- จำนวนสินค้า
    CAST(A.FCXsdNetAfHD AS DECIMAL(18,2)) AS net_sale_amount -- ยอดขายสุทธิของรายการสินค้านั้นๆ (หลังหักส่วนลดรายการ)
    -- ถ้าต้องการยอดรวมทั้งบิล ให้ใช้ B.FCXshGrand แทน:
    -- CAST(B.FCXshGrand AS DECIMAL(18,2)) AS total_bill_amount -- ยอดรวมทั้งบิล (Grand Total)

FROM TPSTSalHD AS B WITH (NOLOCK) -- ตาราง Header ของเอกสารขาย
LEFT JOIN TPSTSalDT AS A WITH (NOLOCK) ON B.FTXshDocNo = A.FTXshDocNo AND B.FTBchCode = A.FTBchCode
LEFT JOIN TCNMUser_L AS D WITH (NOLOCK) ON B.FTCreateBy = D.FTUsrCode -- เชื่อมกับตาราง User เพื่อดึงข้อมูลพนักงาน
LEFT JOIN ( -- Subquery เพื่อกรองเอกสารอ้างอิงภายใน (หากมี)
    SELECT FTXshDocNo, FTXshRefInt, FTBchCode 
    FROM TPSTSalHD
    WHERE FNXshDocType = 9 AND ISNULL(FTXshRefInt , '') <> ''
) AS BB ON BB.FTXshRefInt = B.FTXshDocNo AND B.FTBchCode = BB.FTBchCode

WHERE 1=1
    AND B.FNXshDocType = 1  -- กรองเฉพาะเอกสารประเภทขาย (DocType 1)
    AND ISNULL(BB.FTXshDocNo, '') = '' -- ไม่รวมเอกสารที่ถูกอ้างอิงภายใน (เช่น ใบยกเลิก/คืน)
    AND A.FTXsdStaPdt = 1   -- กรองเฉพาะรายการสินค้าที่มีสถานะปกติ (ไม่ใช่สินค้าที่ถูกยกเลิกในบิล)
    AND CONVERT(VARCHAR(10), B.FDCreateOn, 121) BETWEEN ? AND ? -- กรองตามช่วงวันที่สร้างเอกสาร
    AND A.FTXsdPdtName NOT LIKE '%(NFS)' -- กรองรายการที่มี (NFS) ในชื่อสินค้า
    AND A.FCXsdNetAfHD > 0 -- *** เพิ่มเงื่อนไขนี้เพื่อกรองรายการที่ net_sale_amount เป็น 0 หรือติดลบออก ***
ORDER BY B.FDCreateOn, B.FTXshDocNo 
";

// เตรียมพารามิเตอร์สำหรับ SQL Query
$params = [$startDate, $endDate];

// ทำการ Query โดยใช้ sqlsrv_query สำหรับ SQL Server
$stmt = sqlsrv_query($conn, $sql, $params);

// ตรวจสอบข้อผิดพลาดในการ Query
if ($stmt === false) {
    http_response_code(500); // 500 Internal Server Error
    echo json_encode([
        'success' => false,
        'message' => 'เกิดข้อผิดพลาดในการดึงข้อมูลจากฐานข้อมูล: ' . print_r(sqlsrv_errors(), true)
    ]);
    exit;
}

$data = [];
// วนลูปดึงข้อมูลแต่ละแถว
while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    // แปลงข้อมูลตัวเลขให้เป็น float เพื่อให้ JavaScript จัดการได้ง่ายขึ้นและมั่นใจว่าเป็นประเภทตัวเลข
    $row['quantity'] = (float) $row['quantity'];
    $row['net_sale_amount'] = (float) $row['net_sale_amount'];
    
    $data[] = $row; // เพิ่มแถวข้อมูลเข้าใน array
}

// ปล่อยทรัพยากรของ Statement
sqlsrv_free_stmt($stmt);
// ปิดการเชื่อมต่อฐานข้อมูล
sqlsrv_close($conn); 

// ส่งข้อมูลกลับไปในรูปแบบ JSON
echo json_encode([
    'success' => true,
    'data' => $data,
    'count' => count($data),
    'message' => 'ดึงข้อมูลยอดขายสำหรับ Incentive สำเร็จ'
]);
exit;
?>