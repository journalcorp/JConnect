<?php
// PHPMailer autoload and namespace imports (must be at the top)
require_once __DIR__ . '/../vendor/autoload.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// filepath: c:\inetpub\wwwroot\webportal\pages\send_email.php
header('Content-Type: application/json; charset=utf-8');

// เปิดการแสดงข้อผิดพลาด
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

include_once '../config.php';

// ===== การกำหนดค่าเมล (Email Configuration) =====
// สามารถแก้ไขค่าเหล่านี้ตามเมลของบริษัทคุณ
define('SENDER_EMAIL', 'no-reply@journal.co.th');        // เมลผู้ส่ง
define('SENDER_NAME', 'แผนกทรัพยากรบุคคล');          // ชื่อผู้ส่ง
define('COMPANY_NAME', 'journal corp');           // ชื่อบริษัท
// =================================================

// ตรวจสอบว่าเป็น POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Method not allowed'
    ]);
    exit;
}

// ตรวจสอบ action
$action = $_POST['action'] ?? '';
if ($action !== 'send_reminder_email') {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid action'
    ]);
    exit;
}

// รับข้อมูลจาก form
$recipient_id = $_POST['recipient_id'] ?? '';
$recipient_name = $_POST['recipient_name'] ?? '';
$form_name = $_POST['form_name'] ?? '';
$emp_pe = $_POST['emp_pe'] ?? '';  // เพิ่มข้อมูลผู้ถูกประเมิน

// ตรวจสอบข้อมูลที่จำเป็น
if (empty($recipient_id) || empty($recipient_name) || empty($form_name)) {
    echo json_encode([
        'success' => false,
        'message' => 'ข้อมูลไม่ครบถ้วน'
    ]);
    exit;
}

try {
    // เชื่อมต่อฐานข้อมูล
    $conn = dbConnect();
    if (!$conn) {
        throw new Exception('ไม่สามารถเชื่อมต่อฐานข้อมูลได้');
    }

    // ดึงข้อมูลอีเมลของผู้รับ
    $sql_email = "SELECT email, first_name_th, last_name_th FROM employees WHERE employee_id = ?";
    $result_email = sqlsrv_query($conn, $sql_email, array($recipient_id));
    
    if (!$result_email) {
        throw new Exception('ไม่สามารถดึงข้อมูลอีเมลได้');
    }
    
    $email_data = sqlsrv_fetch_array($result_email, SQLSRV_FETCH_ASSOC);
    sqlsrv_free_stmt($result_email);
    
    if (!$email_data || empty($email_data['email'])) {
        throw new Exception('ไม่พบอีเมลของผู้รับ (รหัส: ' . $recipient_id . ')');
    }
    
    $recipient_email = $email_data['email'];
    
    // ดึงข้อมูลผู้ถูกประเมิน (ถ้ามี)
    $emp_pe_name = '';
    if (!empty($emp_pe)) {
        $sql_pe = "SELECT first_name_th, last_name_th FROM employees WHERE employee_id = ?";
        $result_pe = sqlsrv_query($conn, $sql_pe, array($emp_pe));
        if ($result_pe) {
            $pe_data = sqlsrv_fetch_array($result_pe, SQLSRV_FETCH_ASSOC);
            if ($pe_data) {
                $emp_pe_name = $pe_data['first_name_th'] . ' ' . $pe_data['last_name_th'];
            }
            sqlsrv_free_stmt($result_pe);
        }
    }
    
    // สร้างเนื้อหาเมล
    $subject = "แจ้งเตือน: กรุณาทำแบบประเมิน - " . $form_name;
    $body = "
    <html>
    <head>
        <meta charset='UTF-8'>
        <style>
            body { font-family: 'Tahoma', sans-serif; line-height: 1.6; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #ddd; }
            .header { background-color: #007bff; color: white; padding: 15px; text-align: center; border-radius: 5px 5px 0 0; }
            .content { background-color: #f8f9fa; padding: 20px; }
            .info-box { background-color: white; padding: 15px; margin: 10px 0; border-left: 4px solid #007bff; }
            .footer { background-color: #6c757d; color: white; padding: 10px; text-align: center; font-size: 12px; border-radius: 0 0 5px 5px; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h2>📋 แจ้งเตือนการประเมิน</h2>
            </div>
            <div class='content'>
                <p>เรียน <strong>คุณ{$recipient_name}</strong>,</p>
                <p>คุณมีแบบประเมินที่ยังไม่ได้ทำการประเมิน กรุณาดำเนินการให้เสร็จสิ้นภายในกำหนดเวลา</p>
                
                <div class='info-box'>
                    <h4>📝 รายละเอียดการประเมิน</h4>
                    <ul>
                        <li><strong>ชื่อแบบฟอร์ม:</strong> {$form_name}</li>
                        <li><strong>ผู้ประเมิน:</strong> {$recipient_name} ({$recipient_id})</li>";
    
    if (!empty($emp_pe_name)) {
        $body .= "<li><strong>ผู้ถูกประเมิน:</strong> {$emp_pe_name} ({$emp_pe})</li>";
    }
    
    $body .= "
                    </ul>
                </div>
                <div style='margin:18px 0 18px 0; text-align:center;'>
                    <a href='http://203.154.130.236/webportal/pages/all_report_emp.php' style='display:inline-block;padding:12px 24px;background:#007bff;color:#fff;text-decoration:none;border-radius:6px;font-weight:bold;font-size:16px;' target='_blank'>🎯 คลิกเพื่อเข้าแบบประเมิน</a>
                </div>
                <p><strong>🔔 การดำเนินการ:</strong></p>
                <ol>
                    <li>เข้าสู่ระบบ Web Portal</li>
                    <li>ไปที่เมนูการประเมิน</li>
                    <li>ค้นหาแบบฟอร์มที่ต้องทำ</li>
                    <li>ทำการประเมินให้เสร็จสิ้น</li>
                </ol>
                
                <p>⚠️ <em>หากไม่ทำการประเมินภายในกำหนดเวลา อาจส่งผลต่อกระบวนการประเมินโดยรวม</em></p>
                
                <p>หากมีข้อสงสัยสามารถติดต่อแผนก HR ได้</p>
                <p>ขอบคุณครับ/ค่ะ</p>
            </div>
            <div class='footer'>
                <p>🌐 ระบบ Web Portal - " . COMPANY_NAME . " | ส่งเมลอัตโนมัติ - " . date('d/m/Y H:i:s') . "</p>
                <p style='font-size: 10px; margin-top: 5px;'>*** กรุณาอย่าตอบกลับเมลนี้ ***</p>
            </div>
        </div>
    </body>
    </html>
    ";
    
    // ใช้ PHPMailer หรือ mail() function
    // สำหรับตัวอย่างนี้จะใช้ PHPMailer
    


    $mail = new PHPMailer(true);
    $mail->isSMTP();
    $mail->Host = 'smtp.office365.com';
    $mail->SMTPAuth = true;
    $mail->Username = 'noreply@journal.co.th';
    $mail->Password = 'xywtmklhnmhmdmsl';
    $mail->SMTPSecure = 'tls';
    $mail->Port = 587;
    $mail->CharSet = 'UTF-8';

    $mail->setFrom('noreply@journal.co.th', SENDER_NAME, false);
    $mail->addAddress($recipient_email, $recipient_name);
    $mail->Subject = $subject;
    $mail->isHTML(true);
    $mail->Body = $body;

    $mail->send();
    
    // บันทึกล็อกการส่งเมล
    $sql_log = "INSERT INTO email_logs (recipient_id, recipient_email, subject, sent_at, status) VALUES (?, ?, ?, GETDATE(), 'sent')";
    sqlsrv_query($conn, $sql_log, array($recipient_id, $recipient_email, $subject));
    
    echo json_encode([
        'success' => true,
        'message' => 'ส่งเมลแจ้งเตือนสำเร็จ',
        'recipient_email' => $recipient_email
    ]);
    
    // ปิดการเชื่อมต่อ
    sqlsrv_close($conn);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
