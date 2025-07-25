<?php
require_once '../vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

echo "<h1>📋 เทมเพลต Excel สำหรับ Import ข้อมูล</h1>";
echo "<p>ดาวน์โหลดเทมเพลต Excel เพื่อใช้ในการ import ข้อมูลประเมิน</p>";

// ตรวจสอบเทมเพลตที่มีอยู่
$existing_templates = [
    '../assets/templates/template.xlsx' => 'เทมเพลตหลัก (Official)',
    'hr_evaluation_import_template.xlsx' => 'เทมเพลต HR Evaluation',
    'test_import_sample.xlsx' => 'เทมเพลตตัวอย่าง',
    'test_import_realdata.xlsx' => 'เทมเพลตข้อมูลจริง'
];

echo "<h2>📥 เทมเพลตที่มีอยู่</h2>";
echo "<div style='margin-bottom: 30px;'>";

foreach ($existing_templates as $file => $name) {
    if (file_exists($file)) {
        $filesize = number_format(filesize($file) / 1024, 2);
        $modified = date('Y-m-d H:i:s', filemtime($file));
        
        echo "<div style='border: 1px solid #ddd; padding: 15px; margin: 10px 0; border-radius: 5px; background-color: #f9f9f9;'>";
        echo "<h4 style='margin: 0 0 10px 0; color: #007bff;'>📄 $name</h4>";
        echo "<p style='margin: 5px 0; color: #666;'>ขนาดไฟล์: {$filesize} KB | แก้ไขล่าสุด: $modified</p>";
        echo "<a href='$file' download class='btn btn-primary' style='padding: 8px 15px; background-color: #007bff; color: white; text-decoration: none; border-radius: 3px; margin-right: 10px;'>💾 ดาวน์โหลด</a>";
        echo "<a href='#' onclick=\"window.open('view_excel.php?file=" . urlencode($file) . "', '_blank')\" class='btn btn-info' style='padding: 8px 15px; background-color: #17a2b8; color: white; text-decoration: none; border-radius: 3px;'>👁️ ดูตัวอย่าง</a>";
        echo "</div>";
    }
}

echo "</div>";

// สร้างเทมเพลตใหม่
echo "<h2>🆕 สร้างเทมเพลตใหม่</h2>";

if (isset($_GET['create']) && $_GET['create'] == 'new') {
    try {
        echo "<h3>📝 กำลังสร้างเทมเพลตใหม่...</h3>";
        
        // สร้าง Spreadsheet ใหม่
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        
        // ตั้งชื่อ worksheet
        $sheet->setTitle('HR Evaluation Template');
        
        // สร้าง header
        $headers = [
            'A1' => 'FormID',
            'B1' => 'emp_as', 
            'C1' => 'emp_pe',
            'D1' => 'question_1',
            'E1' => 'question_2',
            'F1' => 'question_3',
            'G1' => 'question_4', 
            'H1' => 'question_5',
            'I1' => 'start_date',
            'J1' => 'end_date'
        ];
        
        // ใส่ header และจัดรูปแบบ
        foreach ($headers as $cell => $value) {
            $sheet->setCellValue($cell, $value);
            $sheet->getStyle($cell)->getFill()
                ->setFillType(Fill::FILL_SOLID)
                ->getStartColor()->setRGB('4472C4');
            $sheet->getStyle($cell)->getFont()
                ->setBold(true)
                ->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('FFFFFF'));
            $sheet->getStyle($cell)->getAlignment()
                ->setHorizontal(Alignment::HORIZONTAL_CENTER);
        }
        
        // เพิ่มคำอธิบาย
        $descriptions = [
            'A2' => 'F005',
            'B2' => 'รหัสผู้ประเมิน',
            'C2' => 'รหัสผู้ถูกประเมิน', 
            'D2' => '1-5',
            'E2' => '1-5',
            'F2' => '1-5',
            'G2' => '1-5',
            'H2' => '1-5',
            'I2' => 'YYYY-MM-DD',
            'J2' => 'YYYY-MM-DD'
        ];
        
        foreach ($descriptions as $cell => $value) {
            $sheet->setCellValue($cell, $value);
            $sheet->getStyle($cell)->getFill()
                ->setFillType(Fill::FILL_SOLID)
                ->getStartColor()->setRGB('E7E6E6');
            $sheet->getStyle($cell)->getFont()->setItalic(true);
            $sheet->getStyle($cell)->getAlignment()
                ->setHorizontal(Alignment::HORIZONTAL_CENTER);
        }
        
        // เพิ่มข้อมูลตัวอย่าง
        $sample_data = [
            ['F005', 'EMP001', 'EMP002', 5, 4, 3, 4, 5, '2024-01-01', '2024-12-31'],
            ['F005', 'EMP003', 'EMP004', 4, 5, 4, 3, 4, '2024-01-01', '2024-12-31'],
            ['F005', 'EMP005', 'EMP006', 3, 4, 5, 4, 3, '2024-01-01', '2024-12-31']
        ];
        
        for ($i = 0; $i < count($sample_data); $i++) {
            $row = $i + 3; // เริ่มจาก row 3
            for ($j = 0; $j < count($sample_data[$i]); $j++) {
                $col = chr(65 + $j); // A, B, C, ...
                $sheet->setCellValue($col . $row, $sample_data[$i][$j]);
                
                // สีสลับแถว
                if ($i % 2 == 0) {
                    $sheet->getStyle($col . $row)->getFill()
                        ->setFillType(Fill::FILL_SOLID)
                        ->getStartColor()->setRGB('F2F2F2');
                }
            }
        }
        
        // ปรับความกว้างคอลัมน์
        foreach (range('A', 'J') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }
        
        // เพิ่ม border
        $range = 'A1:J' . (3 + count($sample_data));
        $sheet->getStyle($range)->getBorders()->getAllBorders()
            ->setBorderStyle(Border::BORDER_THIN);
        
        // เพิ่มคำอธิบายด้านล่าง
        $note_row = 3 + count($sample_data) + 2;
        $sheet->setCellValue('A' . $note_row, 'คำอธิบาย:');
        $sheet->getStyle('A' . $note_row)->getFont()->setBold(true);
        
        $notes = [
            'FormID: รหัสแบบฟอร์ม (เช่น F005)',
            'emp_as: รหัสพนักงานผู้ประเมิน',
            'emp_pe: รหัสพนักงานผู้ถูกประเมิน', 
            'question_1-5: คะแนนประเมิน (1-5)',
            'start_date/end_date: วันที่เริ่มต้น/สิ้นสุดการประเมิน (รูปแบบ YYYY-MM-DD)'
        ];
        
        foreach ($notes as $index => $note) {
            $sheet->setCellValue('A' . ($note_row + 1 + $index), '• ' . $note);
        }
        
        // บันทึกไฟล์
        $filename = 'HR_Evaluation_Template_' . date('Y-m-d') . '.xlsx';
        $filepath = __DIR__ . '/' . $filename;
        
        $writer = new Xlsx($spreadsheet);
        $writer->save($filepath);
        
        echo "<div style='background-color: #d4edda; border: 1px solid #c3e6cb; color: #155724; padding: 15px; border-radius: 5px; margin: 20px 0;'>";
        echo "<h4>✅ สร้างเทมเพลตสำเร็จ!</h4>";
        echo "<p><strong>ชื่อไฟล์:</strong> $filename</p>";
        echo "<p><strong>ขนาดไฟล์:</strong> " . number_format(filesize($filepath) / 1024, 2) . " KB</p>";
        echo "<p><a href='$filename' download class='btn btn-success' style='padding: 10px 20px; background-color: #28a745; color: white; text-decoration: none; border-radius: 5px;'>💾 ดาวน์โหลดเทมเพลตใหม่</a></p>";
        echo "</div>";
        
        // แสดงตัวอย่างข้อมูล
        echo "<h4>📋 ตัวอย่างข้อมูลในเทมเพลต:</h4>";
        echo "<table border='1' style='border-collapse: collapse; width: 100%; margin: 20px 0;'>";
        echo "<tr style='background-color: #4472C4; color: white;'>";
        foreach ($headers as $header) {
            echo "<th style='padding: 10px; text-align: center;'>$header</th>";
        }
        echo "</tr>";
        
        echo "<tr style='background-color: #E7E6E6; font-style: italic;'>";
        foreach ($descriptions as $desc) {
            echo "<td style='padding: 8px; text-align: center;'>$desc</td>";
        }
        echo "</tr>";
        
        foreach ($sample_data as $index => $row) {
            $bg_color = ($index % 2 == 0) ? '#F2F2F2' : '#FFFFFF';
            echo "<tr style='background-color: $bg_color;'>";
            foreach ($row as $cell) {
                echo "<td style='padding: 8px; text-align: center;'>$cell</td>";
            }
            echo "</tr>";
        }
        echo "</table>";
        
    } catch (Exception $e) {
        echo "<div style='background-color: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; padding: 15px; border-radius: 5px; margin: 20px 0;'>";
        echo "<h4>❌ เกิดข้อผิดพลาด</h4>";
        echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
        echo "</div>";
    }
} else {
    echo "<div style='border: 1px solid #ddd; padding: 20px; border-radius: 5px; background-color: #f8f9fa;'>";
    echo "<p>คลิกปุ่มด้านล่างเพื่อสร้างเทมเพลต Excel ใหม่พร้อมตัวอย่างข้อมูล</p>";
    echo "<a href='?create=new' class='btn btn-success' style='padding: 12px 25px; background-color: #28a745; color: white; text-decoration: none; border-radius: 5px; font-size: 16px;'>🆕 สร้างเทมเพลตใหม่</a>";
    echo "</div>";
}

echo "<h2>📖 วิธีใช้งาน</h2>";
echo "<div style='background-color: #e9ecef; padding: 20px; border-radius: 5px; margin: 20px 0;'>";
echo "<ol>";
echo "<li><strong>ดาวน์โหลดเทมเพลต</strong> - เลือกเทมเพลตที่ต้องการและดาวน์โหลด</li>";
echo "<li><strong>เติมข้อมูล</strong> - เปิดไฟล์ด้วย Excel และเติมข้อมูลตามรูปแบบ</li>";
echo "<li><strong>บันทึกไฟล์</strong> - บันทึกเป็น .xlsx หรือ .xls</li>";
echo "<li><strong>Import ข้อมูล</strong> - ใช้หน้า <a href='import_excel.php'>Import Excel</a> เพื่ออัปโหลดไฟล์</li>";
echo "</ol>";
echo "</div>";

echo "<h2>📝 รูปแบบข้อมูล</h2>";
echo "<table border='1' style='border-collapse: collapse; width: 100%; margin: 20px 0;'>";
echo "<tr style='background-color: #f8f9fa;'>";
echo "<th style='padding: 12px;'>คอลัมน์</th>";
echo "<th style='padding: 12px;'>ชื่อ</th>";
echo "<th style='padding: 12px;'>รูปแบบ</th>";
echo "<th style='padding: 12px;'>ตัวอย่าง</th>";
echo "<th style='padding: 12px;'>คำอธิบาย</th>";
echo "</tr>";

$column_info = [
    ['A', 'FormID', 'ข้อความ', 'F005', 'รหัสแบบฟอร์มประเมิน'],
    ['B', 'emp_as', 'ข้อความ', 'EMP001', 'รหัสพนักงานผู้ประเมิน (Assessor)'],
    ['C', 'emp_pe', 'ข้อความ', 'EMP002', 'รหัสพนักงานผู้ถูกประเมิน (Person Evaluated)'],
    ['D', 'question_1', 'ตัวเลข', '5', 'คะแนนคำถามที่ 1 (1-5)'],
    ['E', 'question_2', 'ตัวเลข', '4', 'คะแนนคำถามที่ 2 (1-5)'],
    ['F', 'question_3', 'ตัวเลข', '3', 'คะแนนคำถามที่ 3 (1-5)'],
    ['G', 'question_4', 'ตัวเลข', '4', 'คะแนนคำถามที่ 4 (1-5)'],
    ['H', 'question_5', 'ตัวเลข', '5', 'คะแนนคำถามที่ 5 (1-5)'],
    ['I', 'start_date', 'วันที่', '2024-01-01', 'วันที่เริ่มต้นการประเมิน'],
    ['J', 'end_date', 'วันที่', '2024-12-31', 'วันที่สิ้นสุดการประเมิน']
];

foreach ($column_info as $info) {
    echo "<tr>";
    echo "<td style='padding: 10px; text-align: center; font-weight: bold;'>{$info[0]}</td>";
    echo "<td style='padding: 10px;'>{$info[1]}</td>";
    echo "<td style='padding: 10px; text-align: center;'>{$info[2]}</td>";
    echo "<td style='padding: 10px; text-align: center; font-family: monospace; background-color: #f8f9fa;'>{$info[3]}</td>";
    echo "<td style='padding: 10px;'>{$info[4]}</td>";
    echo "</tr>";
}

echo "</table>";

echo "<h2>⚠️ ข้อควรระวัง</h2>";
echo "<div style='background-color: #fff3cd; border: 1px solid #ffeaa7; color: #856404; padding: 15px; border-radius: 5px; margin: 20px 0;'>";
echo "<ul>";
echo "<li><strong>ห้ามแก้ไข header</strong> (แถวแรก) - ระบบจะใช้ชื่อคอลัมน์เหล่านี้ในการ import</li>";
echo "<li><strong>ข้อมูลว่าง</strong> - แถวที่มีข้อมูลไม่ครบจะถูกข้าม</li>";
echo "<li><strong>รูปแบบวันที่</strong> - ใช้รูปแบบ YYYY-MM-DD เท่านั้น</li>";
echo "<li><strong>คะแนน</strong> - ควรอยู่ในช่วง 1-5</li>";
echo "<li><strong>รหัสพนักงาน</strong> - ต้องตรงกับข้อมูลในระบบ</li>";
echo "</ul>";
echo "</div>";

echo "<h2>🔗 ลิงก์ที่เกี่ยวข้อง</h2>";
echo "<div style='margin: 20px 0;'>";
echo "<a href='import_excel.php' class='btn btn-primary' style='margin-right: 10px; padding: 10px 15px; background-color: #007bff; color: white; text-decoration: none; border-radius: 5px;'>📤 Import Excel</a>";
echo "<a href='monitor_form_all_hr.php' class='btn btn-info' style='margin-right: 10px; padding: 10px 15px; background-color: #17a2b8; color: white; text-decoration: none; border-radius: 5px;'>📊 ตรวจสอบข้อมูล</a>";
echo "<a href='test_complete_form_all_hr_import.php' class='btn btn-warning' style='margin-right: 10px; padding: 10px 15px; background-color: #ffc107; color: #212529; text-decoration: none; border-radius: 5px;'>🧪 ทดสอบ Import</a>";
echo "<a href='../index.php' class='btn btn-secondary' style='padding: 10px 15px; background-color: #6c757d; color: white; text-decoration: none; border-radius: 5px;'>🏠 กลับหน้าหลัก</a>";
echo "</div>";
?>

<style>
body {
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    margin: 20px;
    background-color: #f8f9fa;
    line-height: 1.6;
}

h1, h2, h3, h4 {
    color: #333;
    margin-top: 30px;
}

h1 {
    border-bottom: 3px solid #007bff;
    padding-bottom: 10px;
}

.btn {
    display: inline-block;
    font-weight: 400;
    text-align: center;
    vertical-align: middle;
    cursor: pointer;
    border: 1px solid transparent;
    font-size: 14px;
    line-height: 1.5;
    transition: all 0.2s ease-in-out;
    margin: 2px;
}

.btn:hover {
    opacity: 0.85;
    transform: translateY(-1px);
    box-shadow: 0 2px 4px rgba(0,0,0,0.2);
}

table {
    border-collapse: collapse;
    width: 100%;
    margin: 15px 0;
    background-color: white;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}

th, td {
    border: 1px solid #ddd;
    padding: 12px;
    text-align: left;
}

th {
    background-color: #f8f9fa;
    font-weight: 600;
}

tr:nth-child(even) {
    background-color: #f9f9f9;
}

.alert {
    padding: 15px;
    margin: 20px 0;
    border-radius: 5px;
    border: 1px solid;
}

.alert-success {
    background-color: #d4edda;
    border-color: #c3e6cb;
    color: #155724;
}

.alert-warning {
    background-color: #fff3cd;
    border-color: #ffeaa7;
    color: #856404;
}

.alert-danger {
    background-color: #f8d7da;
    border-color: #f5c6cb;
    color: #721c24;
}
</style>
