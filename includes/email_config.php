<?php
// การตั้งค่าอีเมลสำหรับระบบแจ้งเตือน
require_once __DIR__ . '/../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

// ตั้งค่า SMTP สำหรับ Office365
define('MAIL_HOST', 'smtp.office365.com'); // เซิร์ฟเวอร์ SMTP
define('MAIL_PORT', 587); // พอร์ต SMTP
define('MAIL_USERNAME', 'noreply@journal.co.th'); // อีเมลผู้ส่ง
define('MAIL_PASSWORD', 'xywtmklhnmhmdmsl'); // รหัสผ่านอีเมล
define('MAIL_ENCRYPTION', 'tls'); // การเข้ารหัส

// ข้อมูลผู้ส่ง
define('MAIL_FROM_EMAIL', 'noreply@journal.co.th');
define('MAIL_FROM_NAME', 'ระบบจัดการเบิกจ่าย');

// เปิด/ปิดการส่งอีเมล
define('EMAIL_ENABLED', true); // เปลี่ยนเป็น false เพื่อปิดการส่งอีเมล

// ลิงก์สำหรับการอนุมัติ (URL ของระบบ)
define('APPROVAL_SYSTEM_URL', 'http://localhost/webportal/pages/expense_approval.php');

// ฟังก์ชันส่งอีเมลที่ปรับปรุงแล้วด้วย PHPMailer
function sendAdvancedEmail($to, $subject, $message, $isHtml = false, $recipient_name = '') {
    if (!EMAIL_ENABLED) {
        error_log("Email sending is disabled in config");
        return false;
    }
    
    try {
        $mail = new PHPMailer(true);
        
        // ตั้งค่า SMTP
        $mail->isSMTP();
        $mail->Host = MAIL_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = MAIL_USERNAME;
        $mail->Password = MAIL_PASSWORD;
        $mail->SMTPSecure = MAIL_ENCRYPTION;
        $mail->Port = MAIL_PORT;
        $mail->CharSet = 'UTF-8';
        
        // ปิด SSL verification สำหรับ development (ไม่แนะนำใน production)
        $mail->SMTPOptions = array(
            'ssl' => array(
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            )
        );
        
        // ตั้งค่าผู้ส่งและผู้รับ
        $mail->setFrom(MAIL_FROM_EMAIL, MAIL_FROM_NAME, false);
        $mail->addAddress($to, $recipient_name);
        
        // ตั้งค่าเนื้อหา
        $mail->Subject = $subject;
        $mail->isHTML($isHtml);
        $mail->Body = $message;
        
        // ถ้าเป็น HTML ให้เพิ่ม alt body เป็น text
        if ($isHtml) {
            $mail->AltBody = strip_tags($message);
        }
        
        // ส่งอีเมล
        $mail->send();
        error_log("PHPMailer: Email sent successfully to: " . $to);
        return true;
        
    } catch (Exception $e) {
        error_log("PHPMailer Error: Failed to send email to: " . $to . " - " . $mail->ErrorInfo);
        return false;
    }
}

// ฟังก์ชันส่งอีเมลหลายคนพร้อมกัน
function sendBulkEmails($recipients, $subject, $message, $isHtml = false) {
    if (!EMAIL_ENABLED) {
        error_log("Bulk email sending is disabled in config");
        return false;
    }
    
    $success_count = 0;
    $failed_emails = [];
    
    foreach ($recipients as $recipient) {
        $email = $recipient['email'] ?? '';
        $name = $recipient['name'] ?? '';
        
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $failed_emails[] = $email . ' (invalid email format)';
            continue;
        }
        
        $result = sendAdvancedEmail($email, $subject, $message, $isHtml, $name);
        
        if ($result) {
            $success_count++;
        } else {
            $failed_emails[] = $email;
        }
        
        // หน่วงเวลาเล็กน้อยเพื่อไม่ให้ spam SMTP server
        usleep(100000); // 0.1 วินาที
    }
    
    error_log("Bulk email results: {$success_count} sent, " . count($failed_emails) . " failed");
    if (!empty($failed_emails)) {
        error_log("Failed emails: " . implode(', ', $failed_emails));
    }
    
    return [
        'success_count' => $success_count,
        'failed_count' => count($failed_emails),
        'failed_emails' => $failed_emails,
        'total_sent' => $success_count > 0
    ];
}

// ฟังก์ชันตรวจสอบการตั้งค่าอีเมล
function validateEmailConfig() {
    $errors = [];
    
    if (!defined('EMAIL_ENABLED') || !EMAIL_ENABLED) {
        $errors[] = "Email system is disabled";
    }
    
    if (!defined('MAIL_HOST') || empty(MAIL_HOST)) {
        $errors[] = "MAIL_HOST is not configured";
    }
    
    if (!defined('MAIL_USERNAME') || empty(MAIL_USERNAME)) {
        $errors[] = "MAIL_USERNAME is not configured";
    }
    
    if (!defined('MAIL_PASSWORD') || empty(MAIL_PASSWORD)) {
        $errors[] = "MAIL_PASSWORD is not configured";
    }
    
    if (!class_exists('PHPMailer\\PHPMailer\\PHPMailer')) {
        $errors[] = "PHPMailer class not found";
    }
    
    if (!extension_loaded('openssl')) {
        $errors[] = "OpenSSL extension is required but not loaded";
    }
    
    return [
        'valid' => empty($errors),
        'errors' => $errors
    ];
}

// ฟังก์ชันส่งอีเมลการแจ้งเตือนการอนุมัติ (เฉพาะสำหรับระบบอนุมัติ)
function sendApprovalEmail($approver_email, $approver_name, $voucher_no, $requester_name, $expense_type_th, $total_amount) {
    $validation = validateEmailConfig();
    if (!$validation['valid']) {
        error_log("Email config validation failed: " . implode(', ', $validation['errors']));
        return false;
    }
    
    $subject = "[แจ้งเตือนการอนุมัติ] คำขอเบิกจ่าย - เลขที่ " . $voucher_no;
    $approval_url = APPROVAL_SYSTEM_URL . "?voucher=" . urlencode($voucher_no);
    
    // สร้างเนื้อหาอีเมล HTML
    $html_message = createApprovalEmailTemplate($approver_name, $voucher_no, $requester_name, $expense_type_th, $total_amount, $approval_url);
    
    // สร้างเนื้อหาอีเมล Text
    $text_message = "
เรียน คุณ{$approver_name}

คำขอเบิกจ่ายใหม่รออนุมัติจากท่าน

รายละเอียดคำขอ:
- เลขที่ใบเบิก: {$voucher_no}
- ผู้ขอเบิก: {$requester_name}
- ประเภทค่าใช้จ่าย: {$expense_type_th}
- จำนวนเงิน: " . number_format($total_amount, 2) . " บาท
- วันที่สร้างคำขอ: " . date('d/m/Y H:i:s') . "

ลิงก์สำหรับการอนุมัติ: {$approval_url}

ขอบคุณครับ/ค่ะ
ระบบจัดการเบิกจ่าย
    ";
    
    // ลองส่งอีเมล HTML ก่อน
    $result = sendAdvancedEmail($approver_email, $subject, $html_message, true, $approver_name);
    
    // ถ้าส่ง HTML ไม่ได้ ให้ลองส่งแบบ text
    if (!$result) {
        $result = sendAdvancedEmail($approver_email, $subject, $text_message, false, $approver_name);
    }
    
    return $result;
}

// สร้างเทมเพลตอีเมล HTML
function createApprovalEmailTemplate($approver_name, $voucher_no, $requester_name, $expense_type_th, $total_amount, $approval_url = '') {
    $template = '
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>แจ้งเตือนคำขอเบิกจ่าย</title>
    <style>
        body { font-family: "Sarabun", sans-serif; background-color: #f5f5f5; margin: 0; padding: 20px; }
        .container { max-width: 600px; margin: 0 auto; background-color: white; border-radius: 10px; overflow: hidden; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
        .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; text-align: center; }
        .content { padding: 30px; }
        .info-box { background-color: #f8f9fa; border-radius: 8px; padding: 20px; margin: 20px 0; border-left: 4px solid #007bff; }
        .button { display: inline-block; background: linear-gradient(135deg, #28a745, #20c997); color: white; padding: 15px 30px; text-decoration: none; border-radius: 8px; font-weight: bold; margin: 20px 0; text-align: center; }
        .footer { background-color: #f8f9fa; padding: 20px; text-align: center; font-size: 14px; color: #6c757d; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🔔 แจ้งเตือนคำขอเบิกจ่าย</h1>
            <p>คำขอใหม่รออนุมัติจากท่าน</p>
        </div>
        
        <div class="content">
            <h2>เรียน คุณ' . htmlspecialchars($approver_name) . '</h2>
            <p>มีคำขอเบิกจ่ายใหม่ที่ต้องการการอนุมัติจากท่าน</p>
            
            <div class="info-box">
                <h3>📋 รายละเอียดคำขอ</h3>
                <ul style="list-style: none; padding: 0;">
                    <li><strong>📄 เลขที่ใบเบิก:</strong> ' . htmlspecialchars($voucher_no) . '</li>
                    <li><strong>👤 ผู้ขอเบิก:</strong> ' . htmlspecialchars($requester_name) . '</li>
                    <li><strong>📂 ประเภทค่าใช้จ่าย:</strong> ' . htmlspecialchars($expense_type_th) . '</li>
                    <li><strong>💰 จำนวนเงิน:</strong> ' . number_format($total_amount, 2) . ' บาท</li>
                    <li><strong>📅 วันที่สร้างคำขอ:</strong> ' . date('d/m/Y H:i:s') . '</li>
                </ul>
            </div>';
    
    if (!empty($approval_url)) {
        $template .= '
            <div style="text-align: center;">
                <a href="' . htmlspecialchars($approval_url) . '" class="button">
                    🔍 เข้าสู่ระบบเพื่ออนุมัติ
                </a>
            </div>';
    }
    
    $template .= '
            <p><strong>หมายเหตุ:</strong> กรุณาดำเนินการอนุมัติภายในระยะเวลาที่กำหนด</p>
        </div>
        
        <div class="footer">
            <p>ขอบคุณครับ/ค่ะ<br>
            <strong>ระบบจัดการเบิกจ่าย</strong></p>
            <p><small>อีเมลนี้ถูกส่งโดยอัตโนมัติ กรุณาอย่าตอบกลับ</small></p>
        </div>
    </div>
</body>
</html>';
    
    return $template;
}
?>
