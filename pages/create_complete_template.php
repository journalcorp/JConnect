<?php
// สร้างเทมเพลต Excel ที่สมบูรณ์สำหรับการ import
require_once '../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Font;
use PhpOffice\PhpSpreadsheet\Style\Fill;

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();

// ตั้งชื่อ Sheet
$sheet->setTitle('HR Evaluation Import');

// Header พร้อมรายละเอียด
$headers = [
    'A1' => 'FormID',
    'B1' => 'emp_as', 
    'C1' => 'emp_pe',
    'D1' => 'evaluation_period',
    'E1' => 'start_date',
    'F1' => 'end_date'
];

// คำอธิบาย Header
$descriptions = [
    'A2' => 'รหัสฟอร์ม (บังคับ)',
    'B2' => 'รหัสผู้ประเมิน (บังคับ)',
    'C2' => 'รหัสผู้ถูกประเมิน (บังคับ)',
    'D2' => 'รอบการประเมิน (ไม่บังคับ)',
    'E2' => 'วันที่เริ่ม (YYYY-MM-DD)',
    'F2' => 'วันที่สิ้นสุด (YYYY-MM-DD)'
];

// ตัวอย่างข้อมูล
$examples = [
    'A3' => 'EVAL2025001',
    'B3' => '110001',
    'C3' => '110002',
    'D3' => '2025',
    'E3' => '2025-01-01',
    'F3' => '2025-12-31'
];

$examples2 = [
    'A4' => 'EVAL2025002',
    'B4' => '110003',
    'C4' => '110004',
    'D4' => 'Q1-2025',
    'E4' => '2025-01-01',
    'F4' => '2025-03-31'
];

$examples3 = [
    'A5' => 'EVAL2025003',
    'B5' => '110005',
    'C5' => '110006',  
    'D5' => 'ไตรมาส 1',
    'E5' => '2025-01-01',
    'F5' => '2025-03-31'
];

// ใส่ Header
foreach ($headers as $cell => $value) {
    $sheet->setCellValue($cell, $value);
}

// ใส่คำอธิบาย
foreach ($descriptions as $cell => $value) {
    $sheet->setCellValue($cell, $value);
}

// ใส่ตัวอย่างข้อมูล
foreach ($examples as $cell => $value) {
    $sheet->setCellValue($cell, $value);
}

foreach ($examples2 as $cell => $value) {
    $sheet->setCellValue($cell, $value);
}

foreach ($examples3 as $cell => $value) {
    $sheet->setCellValue($cell, $value);
}

// จัดรูปแบบ Header
$headerStyle = [
    'font' => [
        'bold' => true,
        'color' => ['rgb' => 'FFFFFF']
    ],
    'fill' => [
        'fillType' => Fill::FILL_SOLID,
        'color' => ['rgb' => '4472C4']
    ],
    'alignment' => [
        'horizontal' => Alignment::HORIZONTAL_CENTER,
        'vertical' => Alignment::VERTICAL_CENTER
    ]
];

$sheet->getStyle('A1:F1')->applyFromArray($headerStyle);

// จัดรูปแบบคำอธิบาย
$descStyle = [
    'font' => [
        'italic' => true,
        'color' => ['rgb' => '666666']
    ],
    'fill' => [
        'fillType' => Fill::FILL_SOLID,
        'color' => ['rgb' => 'F2F2F2']
    ]
];

$sheet->getStyle('A2:F2')->applyFromArray($descStyle);

// จัดรูปแบบตัวอย่าง
$exampleStyle = [
    'fill' => [
        'fillType' => Fill::FILL_SOLID,
        'color' => ['rgb' => 'E8F4FD']
    ]
];

$sheet->getStyle('A3:F5')->applyFromArray($exampleStyle);

// ปรับความกว้างคอลัมน์
$sheet->getColumnDimension('A')->setWidth(15);
$sheet->getColumnDimension('B')->setWidth(15);
$sheet->getColumnDimension('C')->setWidth(15);
$sheet->getColumnDimension('D')->setWidth(20);
$sheet->getColumnDimension('E')->setWidth(15);
$sheet->getColumnDimension('F')->setWidth(15);

// เพิ่ม Sheet คำแนะนำ
$instructionSheet = $spreadsheet->createSheet();
$instructionSheet->setTitle('คำแนะนำ');

$instructions = [
    'A1' => 'คำแนะนำการใช้งาน Excel Template สำหรับ HR Evaluation Import',
    'A3' => '1. ข้อมูลที่บังคับต้องมี:',
    'A4' => '   - FormID: รหัสฟอร์มประเมิน (เช่น EVAL2025001, F001)',
    'A5' => '   - emp_as: รหัสผู้ประเมิน (เช่น 110001, EMP001)',
    'A6' => '   - emp_pe: รหัสผู้ถูกประเมิน (เช่น 110002, EMP002)',
    'A8' => '2. ข้อมูลที่ไม่บังคับ (จะใช้ค่า Default ถ้าไม่ระบุ):',
    'A9' => '   - evaluation_period: รอบการประเมิน (Default: "ไม่ระบุ")',
    'A10' => '   - start_date: วันที่เริ่ม รูปแบบ YYYY-MM-DD (Default: วันที่ปัจจุบัน)',
    'A11' => '   - end_date: วันที่สิ้นสุด รูปแบบ YYYY-MM-DD (Default: วันที่ปัจจุบัน)',
    'A13' => '3. ข้อมูลที่จะถูกสร้างอัตโนมัติ:',
    'A14' => '   - form_all_hr.status_form = "active"',
    'A15' => '   - form_all_hr.FormName = "แบบประเมิน"',
    'A16' => '   - evaluation_forms.form_title = "แบบประเมินผลการปฏิบัติงาน - [FormID]"',
    'A17' => '   - evaluation_forms.status = "active"',
    'A18' => '   - evaluation_forms.created_date = เวลาปัจจุบัน',
    'A20' => '4. ตารางที่จะได้รับข้อมูล:',
    'A21' => '   - form_score: สถานะฟอร์ม',
    'A22' => '   - form_all_hr: ข้อมูลฟอร์มทั้งหมด',
    'A23' => '   - evaluation_forms: แบบประเมิน',
    'A24' => '   - upload_logs: ประวัติการอัปโหลด',
    'A26' => '5. หมายเหตุ:',
    'A27' => '   - ลบแถวตัวอย่างก่อนอัปโหลดข้อมูลจริง',
    'A28' => '   - ข้อมูลที่ซ้ำจะถูกอัปเดต ไม่ใช่เพิ่มใหม่',
    'A29' => '   - ตรวจสอบรหัสพนักงานให้ถูกต้องก่อนอัปโหลด'
];

foreach ($instructions as $cell => $value) {
    $instructionSheet->setCellValue($cell, $value);
}

// จัดรูปแบบ Header ใน Sheet คำแนะนำ
$instructionSheet->getStyle('A1')->applyFromArray([
    'font' => [
        'bold' => true,
        'size' => 14,
        'color' => ['rgb' => '2F5496']
    ]
]);

// จัดรูปแบบหัวข้อย่อย
$subHeaderCells = ['A3', 'A8', 'A13', 'A20', 'A26'];
foreach ($subHeaderCells as $cell) {
    $instructionSheet->getStyle($cell)->applyFromArray([
        'font' => [
            'bold' => true,
            'color' => ['rgb' => '70AD47']
        ]
    ]);
}

// ปรับความกว้างคอลัมน์
$instructionSheet->getColumnDimension('A')->setWidth(80);

// กลับไป Sheet แรก
$spreadsheet->setActiveSheetIndex(0);

// บันทึกไฟล์
$writer = new Xlsx($spreadsheet);
$filename = 'hr_evaluation_import_template.xlsx';
$writer->save($filename);

echo "<h2>สร้างเทมเพลต Excel สำหรับ HR Evaluation Import เรียบร้อย</h2>";
echo "<p>ไฟล์: <strong>$filename</strong></p>";
echo "<p><a href='$filename' class='btn btn-success' download>ดาวน์โหลดเทมเพลต</a></p>";

echo "<h3>คุณสมบัติของเทมเพลต:</h3>";
echo "<ul>";
echo "<li>✅ มี 2 Sheet: 'HR Evaluation Import' และ 'คำแนะนำ'</li>";
echo "<li>✅ Header ที่มีสีสันและคำอธิบาย</li>";
echo "<li>✅ ตัวอย่างข้อมูล 3 รายการ</li>";
echo "<li>✅ คำแนะนำการใช้งานแบบละเอียด</li>";
echo "<li>✅ รองรับการ import เข้า 3 ตาราง: form_score, form_all_hr, evaluation_forms</li>";
echo "</ul>";

echo "<h3>ข้อมูลที่ต้องการในไฟล์ Excel:</h3>";
echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
echo "<tr style='background-color: #4472C4; color: white;'>";
echo "<th>Column</th><th>Field Name</th><th>คำอธิบาย</th><th>บังคับ</th><th>ตัวอย่าง</th>";
echo "</tr>";
echo "<tr><td>A</td><td>FormID</td><td>รหัสฟอร์มประเมิน</td><td>✓</td><td>EVAL2025001, F001</td></tr>";
echo "<tr><td>B</td><td>emp_as</td><td>รหัสผู้ประเมิน</td><td>✓</td><td>110001, EMP001</td></tr>";
echo "<tr><td>C</td><td>emp_pe</td><td>รหัสผู้ถูกประเมิน</td><td>✓</td><td>110002, EMP002</td></tr>";
echo "<tr><td>D</td><td>evaluation_period</td><td>รอบการประเมิน</td><td>-</td><td>2025, Q1-2025, ไตรมาส 1</td></tr>";
echo "<tr><td>E</td><td>start_date</td><td>วันที่เริ่ม (YYYY-MM-DD)</td><td>-</td><td>2025-01-01</td></tr>";
echo "<tr><td>F</td><td>end_date</td><td>วันที่สิ้นสุด (YYYY-MM-DD)</td><td>-</td><td>2025-12-31</td></tr>";
echo "</table>";

echo "<br><br>";
echo "<a href='import_excel.php' class='btn btn-primary'>ไปหน้าอัปโหลด Excel</a> ";
echo "<a href='check_import_requirements.php' class='btn btn-info'>ตรวจสอบความต้องการ</a> ";
echo "<a href='check_imported_data.php' class='btn btn-secondary'>ตรวจสอบข้อมูล</a>";

// เพิ่ม CSS สำหรับ styling
echo "<style>
.btn {
    display: inline-block;
    padding: 10px 20px;
    margin: 5px;
    text-decoration: none;
    border-radius: 5px;
    font-weight: bold;
}
.btn-success { background-color: #28a745; color: white; }
.btn-primary { background-color: #007bff; color: white; }
.btn-info { background-color: #17a2b8; color: white; }
.btn-secondary { background-color: #6c757d; color: white; }
.btn:hover { opacity: 0.8; }
</style>";
?>
