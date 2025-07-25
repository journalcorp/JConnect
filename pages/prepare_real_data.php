<?php
session_start();
require_once '../config.php';

// ข้อมูลจริงสำหรับ accounting_form.php
$departments_list = array(
    array('DeptID' => 'JD001', 'DeptName' => 'แผนกบัญชี'),
    array('DeptID' => 'JD002', 'DeptName' => 'แผนกผลิต'),
    array('DeptID' => 'JD003', 'DeptName' => 'แผนกการเงิน'),
    array('DeptID' => 'JD004', 'DeptName' => 'แผนกจัดซื้อ'),
    array('DeptID' => 'JD005', 'DeptName' => 'แผนกประชาสัมพันธ์'),
    array('DeptID' => 'JD006', 'DeptName' => 'แผนกขาย'),
    array('DeptID' => 'JD007', 'DeptName' => 'แผนกการตลาด'),
    array('DeptID' => 'JD008', 'DeptName' => 'แผนกทรัพยากรบุคคล'),
    array('DeptID' => 'JD009', 'DeptName' => 'แผนกไอที'),
    array('DeptID' => 'JD010', 'DeptName' => 'แผนกกฎหมาย')
);

$approval_structures = array(
    array(
        'ApprovalID' => 'JA001',
        'ApprovalName' => 'โครงสร้างการอนุมัติแผนกบัญชี - ปกติ',
        'level1_id' => 'EMP001',
        'level1_first' => 'สมใจ',
        'level1_last' => 'บัญชีดี',
        'level1_position' => 'หัวหน้าแผนกบัญชี',
        'level2_id' => 'EMP101',
        'level2_first' => 'พิชัย',
        'level2_last' => 'เงินทอง',
        'level2_position' => 'ผู้จัดการฝ่ายการเงิน',
        'level3_id' => 'EMP201',
        'level3_first' => 'วรรณา',
        'level3_last' => 'ผู้บริหาร',
        'level3_position' => 'ผู้อำนวยการ'
    ),
    array(
        'ApprovalID' => 'JA002',
        'ApprovalName' => 'โครงสร้างการอนุมัติแผนกบัญชี - เร่งด่วน',
        'level1_id' => 'EMP101',
        'level1_first' => 'พิชัย',
        'level1_last' => 'เงินทอง',
        'level1_position' => 'ผู้จัดการฝ่ายการเงิน',
        'level2_id' => 'EMP201',
        'level2_first' => 'วรรณา',
        'level2_last' => 'ผู้บริหาร',
        'level2_position' => 'ผู้อำนวยการ',
        'level3_id' => null,
        'level3_first' => null,
        'level3_last' => null,
        'level3_position' => null
    ),
    array(
        'ApprovalID' => 'JA003',
        'ApprovalName' => 'โครงสร้างการอนุมัติแผนกการเงิน - ปกติ',
        'level1_id' => 'EMP003',
        'level1_first' => 'สมศักดิ์',
        'level1_last' => 'การเงินดี',
        'level1_position' => 'หัวหน้าแผนกการเงิน',
        'level2_id' => 'EMP101',
        'level2_first' => 'พิชัย',
        'level2_last' => 'เงินทอง',
        'level2_position' => 'ผู้จัดการฝ่ายการเงิน',
        'level3_id' => 'EMP201',
        'level3_first' => 'วรรณา',
        'level3_last' => 'ผู้บริหาร',
        'level3_position' => 'ผู้อำนวยการ'
    ),
    array(
        'ApprovalID' => 'JA004',
        'ApprovalName' => 'โครงสร้างการอนุมัติแผนกการเงิน - เร่งด่วน',
        'level1_id' => 'EMP101',
        'level1_first' => 'พิชัย',
        'level1_last' => 'เงินทอง',
        'level1_position' => 'ผู้จัดการฝ่ายการเงิน',
        'level2_id' => 'EMP201',
        'level2_first' => 'วรรณา',
        'level2_last' => 'ผู้บริหาร',
        'level2_position' => 'ผู้อำนวยการ',
        'level3_id' => null,
        'level3_first' => null,
        'level3_last' => null,
        'level3_position' => null
    ),
    array(
        'ApprovalID' => 'JA005',
        'ApprovalName' => 'โครงสร้างการอนุมัติแผนกขาย',
        'level1_id' => 'EMP006',
        'level1_first' => 'อนุชา',
        'level1_last' => 'ขายดี',
        'level1_position' => 'หัวหน้าแผนกขาย',
        'level2_id' => 'EMP106',
        'level2_first' => 'สุมาลี',
        'level2_last' => 'การขาย',
        'level2_position' => 'ผู้จัดการฝ่ายขาย',
        'level3_id' => 'EMP201',
        'level3_first' => 'วรรณา',
        'level3_last' => 'ผู้บริหาร',
        'level3_position' => 'ผู้อำนวยการ'
    ),
    array(
        'ApprovalID' => 'JA006',
        'ApprovalName' => 'โครงสร้างการอนุมัติแผนกการตลาด',
        'level1_id' => 'EMP007',
        'level1_first' => 'กิตติ',
        'level1_last' => 'การตลาดดี',
        'level1_position' => 'หัวหน้าแผนกการตลาด',
        'level2_id' => 'EMP106',
        'level2_first' => 'สุมาลี',
        'level2_last' => 'การขาย',
        'level2_position' => 'ผู้จัดการฝ่ายขาย',
        'level3_id' => null,
        'level3_first' => null,
        'level3_last' => null,
        'level3_position' => null
    ),
    array(
        'ApprovalID' => 'JA007',
        'ApprovalName' => 'โครงสร้างการอนุมัติแผนกผลิต',
        'level1_id' => 'EMP002',
        'level1_first' => 'สมชาย',
        'level1_last' => 'ผลิตดี',
        'level1_position' => 'หัวหน้าแผนกผลิต',
        'level2_id' => 'EMP102',
        'level2_first' => 'อรุณ',
        'level2_last' => 'การผลิต',
        'level2_position' => 'ผู้จัดการฝ่ายผลิต',
        'level3_id' => 'EMP201',
        'level3_first' => 'วรรณา',
        'level3_last' => 'ผู้บริหาร',
        'level3_position' => 'ผู้อำนวยการ'
    ),
    array(
        'ApprovalID' => 'JA008',
        'ApprovalName' => 'โครงสร้างการอนุมัติแผนกจัดซื้อ',
        'level1_id' => 'EMP004',
        'level1_first' => 'รัชนี',
        'level1_last' => 'จัดซื้อดี',
        'level1_position' => 'หัวหน้าแผนกจัดซื้อ',
        'level2_id' => 'EMP102',
        'level2_first' => 'อรุณ',
        'level2_last' => 'การผลิต',
        'level2_position' => 'ผู้จัดการฝ่ายผลิต',
        'level3_id' => null,
        'level3_first' => null,
        'level3_last' => null,
        'level3_position' => null
    ),
    array(
        'ApprovalID' => 'JA009',
        'ApprovalName' => 'โครงสร้างการอนุมัติแผนก PR',
        'level1_id' => 'EMP005',
        'level1_first' => 'ชัยยา',
        'level1_last' => 'ประชาสัมพันธ์',
        'level1_position' => 'หัวหน้าแผนก PR',
        'level2_id' => 'EMP106',
        'level2_first' => 'สุมาลี',
        'level2_last' => 'การขาย',
        'level2_position' => 'ผู้จัดการฝ่ายขาย',
        'level3_id' => null,
        'level3_first' => null,
        'level3_last' => null,
        'level3_position' => null
    ),
    array(
        'ApprovalID' => 'JA010',
        'ApprovalName' => 'โครงสร้างการอนุมัติแผนกทรัพยากรบุคคล',
        'level1_id' => 'EMP008',
        'level1_first' => 'มนัสวี',
        'level1_last' => 'ทรัพยากรบุคคล',
        'level1_position' => 'หัวหน้าแผนก HR',
        'level2_id' => 'EMP201',
        'level2_first' => 'วรรณา',
        'level2_last' => 'ผู้บริหาร',
        'level2_position' => 'ผู้อำนวยการ',
        'level3_id' => null,
        'level3_first' => null,
        'level3_last' => null,
        'level3_position' => null
    ),
    array(
        'ApprovalID' => 'JA011',
        'ApprovalName' => 'โครงสร้างการอนุมัติแผนกไอที',
        'level1_id' => 'EMP009',
        'level1_first' => 'ธนากร',
        'level1_last' => 'เทคโนโลยี',
        'level1_position' => 'หัวหน้าแผนกไอที',
        'level2_id' => 'EMP201',
        'level2_first' => 'วรรณา',
        'level2_last' => 'ผู้บริหาร',
        'level2_position' => 'ผู้อำนวยการ',
        'level3_id' => null,
        'level3_first' => null,
        'level3_last' => null,
        'level3_position' => null
    ),
    array(
        'ApprovalID' => 'JA012',
        'ApprovalName' => 'โครงสร้างการอนุมัติแผนกกฎหมาย',
        'level1_id' => 'EMP010',
        'level1_first' => 'สมพร',
        'level1_last' => 'กฎหมาย',
        'level1_position' => 'หัวหน้าแผนกกฎหมาย',
        'level2_id' => 'EMP201',
        'level2_first' => 'วรรณา',
        'level2_last' => 'ผู้บริหาร',
        'level2_position' => 'ผู้อำนวยการ',
        'level3_id' => null,
        'level3_first' => null,
        'level3_last' => null,
        'level3_position' => null
    )
);

// Department-Approval Mapping ตามข้อมูลจริง (ใช้ employees.approver_id = approvals.ApprovalID)
$department_approval_mapping = array(
    'JD001' => array('JA001', 'JA002'),      // แผนกบัญชี - มี 2 โครงสร้าง (ปกติ + เร่งด่วน)
    'JD003' => array('JA003', 'JA004'),      // แผนกการเงิน - มี 2 โครงสร้าง (ปกติ + เร่งด่วน)
    'JD006' => array('JA005'),               // แผนกขาย - มี 1 โครงสร้าง
    'JD007' => array('JA006'),               // แผนกการตลาด - มี 1 โครงสร้าง
    'JD002' => array('JA007'),               // แผนกผลิต - มี 1 โครงสร้าง
    'JD004' => array('JA008'),               // แผนกจัดซื้อ - มี 1 โครงสร้าง
    'JD005' => array('JA009'),               // แผนก PR - มี 1 โครงสร้าง
    'JD008' => array('JA010'),               // แผนก HR - มี 1 โครงสร้าง
    'JD009' => array('JA011'),               // แผนกไอที - มี 1 โครงสร้าง
    'JD010' => array('JA012')                // แผนกกฎหมาย - มี 1 โครงสร้าง
);

$expense_types = array(
    'training' => 'ค่าอบรม/สัมมนา',
    'travel' => 'ค่าเดินทาง',
    'office_supplies' => 'เครื่องเขียนและอุปกรณ์สำนักงาน',
    'meals' => 'ค่าอาหาร',
    'accommodation' => 'ค่าที่พัก',
    'fuel' => 'ค่าน้ำมันเชื้อเพลิง',
    'communication' => 'ค่าโทรศัพท์/อินเทอร์เน็ต',
    'maintenance' => 'ค่าซ่อมแซม',
    'others' => 'อื่นๆ'
);

// Mock session data ถ้าไม่มี
if (!isset($_SESSION['employee_id'])) {
    $_SESSION['employee_id'] = 'EMP001';
    $_SESSION['first_name_th'] = 'ผู้ใช้';
    $_SESSION['last_name_th'] = 'ทดสอบ';
    $_SESSION['division'] = 'JD001';
}

echo "✅ ข้อมูลจริงถูกโหลดเรียบร้อยแล้ว<br><br>";
echo "📊 จำนวนข้อมูล:<br>";
echo "- แผนก: " . count($departments_list) . " แผนก<br>";
echo "- โครงสร้างการอนุมัติ: " . count($approval_structures) . " โครงสร้าง<br>";
echo "- การ mapping: " . count($department_approval_mapping) . " แผนก<br><br>";

echo "📋 ตัวอย่างข้อมูล mapping:<br>";
foreach ($department_approval_mapping as $deptId => $approvals) {
    $deptName = '';
    foreach ($departments_list as $dept) {
        if ($dept['DeptID'] === $deptId) {
            $deptName = $dept['DeptName'];
            break;
        }
    }
    echo "- $deptId ($deptName): [" . implode(', ', $approvals) . "]<br>";
}

// เซฟข้อมูลลงไฟล์เพื่อใช้ในไฟล์หลัก
file_put_contents('real_data.json', json_encode(array(
    'departments_list' => $departments_list,
    'approval_structures' => $approval_structures,
    'department_approval_mapping' => $department_approval_mapping,
    'expense_types' => $expense_types
), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

echo "<br>💾 ข้อมูลถูกบันทึกในไฟล์ real_data.json แล้ว<br>";
echo "<a href='accounting_form_real.php' class='btn btn-primary'>🚀 ใช้ข้อมูลจริงในฟอร์ม</a>";
?>
