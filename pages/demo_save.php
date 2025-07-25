<?php
header('Content-Type: text/html; charset=UTF-8');
session_start();

// ตั้งค่า session ทดสอบ
$_SESSION['employee_id'] = '110001'; // ใช้ employee_id ที่มีจริงในระบบ
$_SESSION['division'] = 'JD001';

// จำลองการส่งข้อมูลฟอร์ม
$_POST = [
    'company_branch_form' => 'main',
    'voucher_no' => 'PCV-' . date('Ymd') . '-TEST',
    'voucher_type' => 'advance',
    'request_title' => 'ทดสอบบันทึกข้อมูล - บริษัท ABC จำกัด',
    'department' => 'ฝ่ายบัญชี',
    'cost_center' => 'ACC001',
    'request_date' => date('Y-m-d'),
    'expense_type' => 'office_supplies',
    'amount' => '2500.00',
    'description' => 'ทดสอบการบันทึกข้อมูลระบบเบิกจ่าย - ซื้อเครื่องเขียนสำนักงาน',
    'approver_level1' => '0',
    'approver_level2' => '0',
    'approver_level3' => '0',
    'expense_items' => [
        'กระดาษ A4',
        'ปากกา',
        'แฟ้มเอกสาร',
        'คลิป'
    ],
    'expense_amounts' => [
        '500.00',
        '300.00',
        '1200.00',
        '500.00'
    ],
    'expense_notes' => [
        'แพ็ค 5 รีม',
        'โหล 2 โหล',
        'แฟ้มสี 50 เล่ม',
        'คลิปหนีบกระดาษ'
    ]
];

// เรียกใช้ไฟล์ฟอร์มจริง
$_SERVER['REQUEST_METHOD'] = 'POST';

echo "<!DOCTYPE html>";
echo "<html lang='th'>";
echo "<head>";
echo "<meta charset='UTF-8'>";
echo "<title>ทดลองบันทึกข้อมูลฟอร์มเบิกจ่าย</title>";
echo "<style>";
echo "body { font-family: 'Sarabun', 'Tahoma', sans-serif; margin: 20px; }";
echo ".test-info { background: #e7f3ff; border: 1px solid #b3d9ff; padding: 15px; margin: 10px 0; border-radius: 5px; }";
echo ".test-data { background: #f8f9fa; border: 1px solid #dee2e6; padding: 15px; margin: 10px 0; border-radius: 5px; }";
echo "table { border-collapse: collapse; width: 100%; margin: 10px 0; }";
echo "th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }";
echo "th { background-color: #f2f2f2; }";
echo "</style>";
echo "</head>";
echo "<body>";

echo "<h1>ทดลองบันทึกข้อมูลฟอร์มเบิกจ่าย</h1>";

echo "<div class='test-info'>";
echo "<h3>📝 ข้อมูลทดสอบที่จะบันทึก</h3>";
echo "<p><strong>Session:</strong> employee_id = " . $_SESSION['employee_id'] . ", division = " . $_SESSION['division'] . "</p>";
echo "<p><strong>วิธีการ:</strong> จำลองการส่งฟอร์ม POST ด้วยข้อมูลทดสอบ</p>";
echo "</div>";

echo "<div class='test-data'>";
echo "<h4>ข้อมูลฟอร์มที่ส่ง:</h4>";
echo "<table>";
echo "<tr><th>ฟิลด์</th><th>ค่า</th></tr>";
foreach ($_POST as $key => $value) {
    if (is_array($value)) {
        echo "<tr><td>" . $key . "</td><td>" . implode(', ', $value) . "</td></tr>";
    } else {
        echo "<tr><td>" . $key . "</td><td>" . $value . "</td></tr>";
    }
}
echo "</table>";
echo "</div>";

echo "<div class='test-info'>";
echo "<h3>🔄 กำลังเรียกใช้ระบบฟอร์มจริง...</h3>";
echo "</div>";

// บังคับให้แสดงผลทันที
ob_flush();
flush();

// เรียกใช้ไฟล์ฟอร์มจริง
try {
    echo "<div style='border: 2px solid #28a745; padding: 20px; margin: 20px 0; border-radius: 10px;'>";
    echo "<h3 style='color: #28a745; margin-top: 0;'>📋 ผลลัพธ์จากระบบฟอร์มจริง:</h3>";
    
    // Capture the output
    ob_start();
    include 'accounting_form.php';
    $form_output = ob_get_clean();
    
    // แสดงเฉพาะข้อความสำคัญ
    if (strpos($form_output, 'สร้างคำขอเบิกจ่ายเรียบร้อยแล้ว') !== false) {
        echo "<div style='background: #d4edda; border: 1px solid #c3e6cb; color: #155724; padding: 15px; border-radius: 5px; margin: 10px 0;'>";
        echo "<h4>✅ บันทึกข้อมูลสำเร็จ!</h4>";
        
        // ดึงเลขที่ใบเบิก
        preg_match('/เลขที่ใบเบิก: ([^<]+)/', $form_output, $matches);
        if (isset($matches[1])) {
            echo "<p><strong>เลขที่ใบเบิก:</strong> " . trim($matches[1]) . "</p>";
        }
        
        echo "<p>ข้อมูลได้ถูกบันทึกลงในฐานข้อมูล webportal เรียบร้อยแล้ว</p>";
        echo "</div>";
        
        // ตรวจสอบข้อมูลในฐานข้อมูล
        echo "<h4>🔍 ตรวจสอบข้อมูลในฐานข้อมูล:</h4>";
        
        include_once '../config.php';
        $conn = dbConnect();
        
        if (is_resource($conn)) {
            // ค้นหาข้อมูลที่เพิ่งบันทึก
            $voucher_no = $_POST['voucher_no'];
            $sql = "SELECT TOP 1 er.*, c.company_name_th 
                   FROM dbo.ExpenseRequests er 
                   LEFT JOIN dbo.Companies c ON er.company_id = c.id 
                   WHERE er.voucher_no = ? 
                   ORDER BY er.created_at DESC";
            $result = sqlsrv_query($conn, $sql, [$voucher_no]);
            
            if ($result && sqlsrv_has_rows($result)) {
                $data = sqlsrv_fetch_array($result, SQLSRV_FETCH_ASSOC);
                
                echo "<table>";
                echo "<tr><th>ฟิลด์</th><th>ค่าที่บันทึก</th></tr>";
                echo "<tr><td>ID</td><td>" . $data['id'] . "</td></tr>";
                echo "<tr><td>เลขที่ใบเบิก</td><td>" . $data['voucher_no'] . "</td></tr>";
                echo "<tr><td>บริษัท</td><td>" . $data['company_name_th'] . "</td></tr>";
                echo "<tr><td>จ่ายให้</td><td>" . $data['pay_to'] . "</td></tr>";
                echo "<tr><td>แผนก</td><td>" . $data['department'] . "</td></tr>";
                echo "<tr><td>จำนวนเงิน</td><td>" . number_format($data['total_amount'], 2) . " บาท</td></tr>";
                echo "<tr><td>สถานะ</td><td>" . $data['overall_status'] . "</td></tr>";
                echo "<tr><td>วันที่สร้าง</td><td>" . $data['created_at']->format('Y-m-d H:i:s') . "</td></tr>";
                echo "</table>";
                
                // แสดงรายการค่าใช้จ่าย
                $sql_items = "SELECT * FROM dbo.ExpenseItems WHERE expense_request_id = ?";
                $result_items = sqlsrv_query($conn, $sql_items, [$data['id']]);
                
                echo "<h5>รายการค่าใช้จ่าย:</h5>";
                echo "<table>";
                echo "<tr><th>รายการ</th><th>จำนวนเงิน</th><th>หมายเหตุ</th></tr>";
                while ($item = sqlsrv_fetch_array($result_items, SQLSRV_FETCH_ASSOC)) {
                    echo "<tr>";
                    echo "<td>" . $item['item_description'] . "</td>";
                    echo "<td>" . number_format($item['amount'], 2) . " บาท</td>";
                    echo "<td>" . $item['notes'] . "</td>";
                    echo "</tr>";
                }
                echo "</table>";
                
            } else {
                echo "<p style='color: orange;'>⚠️ ไม่พบข้อมูลในฐานข้อมูล</p>";
            }
        }
        
    } else if (strpos($form_output, 'เกิดข้อผิดพลาด') !== false) {
        echo "<div style='background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; padding: 15px; border-radius: 5px; margin: 10px 0;'>";
        echo "<h4>❌ เกิดข้อผิดพลาด</h4>";
        
        // ดึงข้อความ error
        preg_match('/เกิดข้อผิดพลาด[^<]+/', $form_output, $error_matches);
        if (isset($error_matches[0])) {
            echo "<p>" . $error_matches[0] . "</p>";
        }
        echo "</div>";
    } else {
        echo "<p style='color: blue;'>ℹ️ ระบบทำงานปกติ แต่ไม่มีข้อความยืนยันการบันทึก</p>";
    }
    
    echo "</div>";
    
} catch (Exception $e) {
    echo "<div style='background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; padding: 15px; border-radius: 5px; margin: 10px 0;'>";
    echo "<h4>❌ เกิดข้อผิดพลาดในการทดสอบ</h4>";
    echo "<p>Error: " . $e->getMessage() . "</p>";
    echo "</div>";
}

echo "<div style='margin-top: 30px; padding: 20px; background: #f8f9fa; border-radius: 10px;'>";
echo "<h3>🔗 ลิงก์ที่เกี่ยวข้อง</h3>";
echo "<p>";
echo "<a href='accounting_form.php'>→ ไปยังฟอร์มเบิกจ่ายจริง</a><br>";
echo "<a href='../test_db.php'>→ ทดสอบการเชื่อมต่อฐานข้อมูล</a><br>";
echo "<a href='../test_thai.php'>→ ทดสอบการแสดงผลภาษาไทย</a>";
echo "</p>";
echo "</div>";

echo "</body>";
echo "</html>";
?>
