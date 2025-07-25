<?php
ob_start(); // เริ่มการบัฟเฟอร์เอาต์พุต
session_start();
include_once '../includes/header.php';
include_once '../includes/menu.php';
include_once '../config.php';

$conn = dbConnect();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_activity'])) {
    $activity_name = $_POST['activity_name'];
    $start_date = $_POST['start_date'];
    $end_date = $_POST['end_date'];
    $score = $_POST['score'];
    $description = $_POST['description'];
    
    // File attachment settings
    $require_attachment = isset($_POST['require_attachment']) ? 1 : 0;
    $attachment_description = isset($_POST['attachment_description']) ? $_POST['attachment_description'] : '';
    $allow_multiple_files = isset($_POST['allow_multiple_files']) ? 1 : 0;
    $is_file_required = isset($_POST['is_file_required']) ? 1 : 0;
    
    // Create attachment settings JSON
    $attachment_settings = json_encode([
        'require_attachment' => $require_attachment,
        'attachment_description' => $attachment_description,
        'allow_multiple_files' => $allow_multiple_files,
        'is_file_required' => $is_file_required
    ]);

    // Check if attachment_settings column exists, if not use basic query
    $check_column_sql = "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'activities' AND COLUMN_NAME = 'attachment_settings'";
    $check_stmt = sqlsrv_query($conn, $check_column_sql);
    $column_exists = sqlsrv_fetch_array($check_stmt);
    sqlsrv_free_stmt($check_stmt);
    
    if ($column_exists) {
        // Column exists, use full query
        $sql = "INSERT INTO activities (name, start_date, end_date, score, description, attachment_settings) VALUES (?, ?, ?, ?, ?, ?)";
        $params = array($activity_name, $start_date, $end_date, $score, $description, $attachment_settings);
    } else {
        // Column doesn't exist, use basic query and log the attachment settings separately
        $sql = "INSERT INTO activities (name, start_date, end_date, score, description) VALUES (?, ?, ?, ?, ?)";
        $params = array($activity_name, $start_date, $end_date, $score, $description);
    }
    
    $stmt = sqlsrv_query($conn, $sql, $params);
    
    if ($stmt) {
        $activity_id = null;
        
        // Get the inserted activity ID
        $id_sql = "SELECT SCOPE_IDENTITY() as id";
        $id_stmt = sqlsrv_query($conn, $id_sql);
        if ($id_stmt && $row = sqlsrv_fetch_array($id_stmt)) {
            $activity_id = $row['id'];
        }
        sqlsrv_free_stmt($id_stmt);
        
        // If column doesn't exist, create a temporary log file
        if (!$column_exists && $require_attachment) {
            $log_dir = '../logs/';
            if (!file_exists($log_dir)) {
                mkdir($log_dir, 0777, true);
            }
            $log_file = $log_dir . 'attachment_settings_' . date('Y-m-d') . '.log';
            $log_data = date('Y-m-d H:i:s') . " - Activity ID: $activity_id - Settings: $attachment_settings\n";
            file_put_contents($log_file, $log_data, FILE_APPEND);
        }
        
        sqlsrv_free_stmt($stmt);
        header("Location: activity_management.php?added=1&activity_id=" . $activity_id);
        exit();
    } else {
        $errors = sqlsrv_errors();
        $error_message = "เกิดข้อผิดพลาดในการเพิ่มกิจกรรม";
        if ($errors) {
            $error_message .= " - " . $errors[0]['message'];
        }
    }
}
ob_end_flush(); // ปิดการบัฟเฟอร์เอาต์พุต
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>สร้างกิจกรรมใหม่ - Web Portal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Prompt', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            color: #333;
        }
        
        .main-container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.15);
            margin: 2rem auto;
            padding: 0;
            overflow: hidden;
            max-width: 900px;
            position: relative;
        }
        
        .header-section {
            background: linear-gradient(135deg, #007bff 0%, #6610f2 100%);
            color: white;
            padding: 2.5rem 2rem;
            position: relative;
            overflow: hidden;
        }
        
        .header-section::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -20%;
            width: 350px;
            height: 350px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
            z-index: 1;
        }
        
        .header-section::after {
            content: '';
            position: absolute;
            bottom: -30%;
            left: -15%;
            width: 200px;
            height: 200px;
            background: rgba(255, 255, 255, 0.08);
            border-radius: 50%;
            z-index: 1;
        }
        
        .header-content {
            position: relative;
            z-index: 2;
        }
        
        .content-section {
            padding: 2.5rem;
        }
        
        .form-container {
            background: linear-gradient(145deg, #f8f9fa 0%, #e9ecef 100%);
            border-radius: 20px;
            padding: 2.5rem;
            border: 1px solid rgba(0, 123, 255, 0.1);
            position: relative;
        }
        
        .form-container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, #007bff 0%, #6610f2 100%);
            border-radius: 20px 20px 0 0;
        }
        
        .form-group {
            margin-bottom: 1.8rem;
            position: relative;
        }
        
        .form-label {
            font-weight: 600;
            color: #495057;
            margin-bottom: 0.75rem;
            display: flex;
            align-items: center;
            font-size: 0.95rem;
        }
        
        .form-label .required {
            color: #dc3545;
            margin-left: 0.25rem;
        }
        
        .form-control, .form-select {
            border-radius: 12px;
            border: 2px solid #e9ecef;
            padding: 0.875rem 1.25rem;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            font-size: 0.95rem;
            background: white;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: #007bff;
            box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.15);
            transform: translateY(-1px);
        }
        
        .form-control:hover, .form-select:hover {
            border-color: #007bff;
        }
        
        .input-group-icon {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #6c757d;
            z-index: 10;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #007bff 0%, #6610f2 100%);
            border: none;
            border-radius: 12px;
            padding: 0.875rem 2rem;
            font-weight: 500;
            font-size: 1rem;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0, 123, 255, 0.3);
        }
        
        .btn-primary:active {
            transform: translateY(0);
        }
        
        .btn-secondary {
            border-radius: 12px;
            padding: 0.875rem 2rem;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        
        .btn-secondary:hover {
            transform: translateY(-2px);
        }
        
        .breadcrumb {
            background: transparent;
            padding: 0;
            margin-bottom: 1.5rem;
        }
        
        .breadcrumb-item a {
            color: #007bff;
            text-decoration: none;
            font-weight: 500;
        }
        
        .breadcrumb-item a:hover {
            text-decoration: underline;
        }
        
        .breadcrumb-item.active {
            color: #6c757d;
            font-weight: 500;
        }
        
        .alert {
            border-radius: 12px;
            border: none;
            margin-bottom: 2rem;
            padding: 1rem 1.25rem;
        }
        
        .alert-success {
            background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%);
            color: #155724;
        }
        
        .alert-danger {
            background: linear-gradient(135deg, #f8d7da 0%, #f5c6cb 100%);
            color: #721c24;
        }
        
        .form-text {
            font-size: 0.85rem;
            color: #6c757d;
            margin-top: 0.5rem;
        }
        
        .progress-indicator {
            display: flex;
            justify-content: space-between;
            margin-bottom: 2rem;
            padding: 0 1rem;
        }
        
        .progress-step {
            display: flex;
            flex-direction: column;
            align-items: center;
            flex: 1;
            position: relative;
        }
        
        .progress-step:not(:last-child)::after {
            content: '';
            position: absolute;
            top: 15px;
            left: 50%;
            width: 100%;
            height: 2px;
            background: #e9ecef;
            z-index: 1;
        }
        
        .progress-step.active:not(:last-child)::after {
            background: #007bff;
        }
        
        .step-circle {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            background: #e9ecef;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.8rem;
            font-weight: 600;
            color: #6c757d;
            z-index: 2;
            position: relative;
            background: white;
            border: 3px solid #e9ecef;
        }
        
        .progress-step.active .step-circle {
            background: #007bff;
            color: white;
            border-color: #007bff;
        }
        
        .step-label {
            font-size: 0.75rem;
            margin-top: 0.5rem;
            color: #6c757d;
            font-weight: 500;
        }
        
        .progress-step.active .step-label {
            color: #007bff;
        }
        
        .character-counter {
            position: absolute;
            bottom: -25px;
            right: 10px;
            font-size: 0.75rem;
            color: #6c757d;
        }
        
        .floating-label {
            position: relative;
        }
        
        .floating-label .form-control:focus + .floating-placeholder,
        .floating-label .form-control:not(:placeholder-shown) + .floating-placeholder {
            transform: translateY(-25px) scale(0.85);
            color: #007bff;
        }
        
        .floating-placeholder {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            background: white;
            padding: 0 5px;
            color: #6c757d;
            transition: all 0.3s ease;
            pointer-events: none;
            z-index: 5;
        }
        
        @media (max-width: 768px) {
            .main-container {
                margin: 1rem;
                border-radius: 15px;
            }
            
            .header-section {
                padding: 2rem 1.5rem;
            }
            
            .content-section {
                padding: 1.5rem;
            }
            
            .form-container {
                padding: 1.5rem;
            }
            
            .progress-indicator {
                margin-bottom: 1.5rem;
            }
            
            .step-label {
                display: none;
            }
        }
        
        .loading-spinner {
            display: none;
            width: 20px;
            height: 20px;
            border: 2px solid #ffffff;
            border-top: 2px solid transparent;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin-right: 0.5rem;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        .btn-primary.loading .loading-spinner {
            display: inline-block;
        }
        
        .form-validation-feedback {
            display: none;
            margin-top: 0.5rem;
            font-size: 0.85rem;
        }
        
        .form-validation-feedback.valid {
            color: #28a745;
            display: block;
        }
        
        .form-validation-feedback.invalid {
            color: #dc3545;
            display: block;
        }
        
        /* File Attachment Option */
        .attachment-option-card {
            background: linear-gradient(145deg, #f8f9ff 0%, #f0f4ff 100%);
            border: 2px solid #e7f1ff !important;
            transition: all 0.3s ease;
        }
        
        .attachment-option-card:hover {
            border-color: #007bff !important;
            box-shadow: 0 4px 15px rgba(0, 123, 255, 0.1);
        }
        
        .form-check {
            position: relative;
            padding-left: 2rem;
        }
        
        .form-check-input {
            width: 1.25em;
            height: 1.25em;
            margin-left: -2rem;
            border: 2px solid #dee2e6;
            border-radius: 0.375rem;
            transition: all 0.3s ease;
        }
        
        .form-check-input:checked {
            background-color: #007bff;
            border-color: #007bff;
        }
        
        .form-check-input:focus {
            border-color: #86b7fe;
            outline: 0;
            box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25);
        }
        
        .form-check-label {
            margin-bottom: 0;
            cursor: pointer;
            font-weight: 500;
        }
        
        .form-check-label small {
            font-size: 0.8rem;
            margin-top: 0.25rem;
        }
        
        .attachment-details {
            background: white;
            border-radius: 10px;
            padding: 1rem;
            border: 1px solid #e9ecef;
            animation: slideDown 0.3s ease;
        }
        
        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        @media (max-width: 768px) {
            .file-upload-area {
                min-height: 100px;
                padding: 1rem;
            }
            
            .file-icon {
                font-size: 1.5rem;
            }
            
            .upload-text strong {
                font-size: 0.9rem;
            }
        }
    </style>
</head>
<body>
<div class="container-fluid py-4">
    <div class="main-container">
        <!-- Header Section -->
        <div class="header-section">
            <div class="header-content">
                <div class="row align-items-center">
                    <div class="col-12">
                        <h1 class="mb-2"><i class="bi bi-plus-circle me-3"></i>สร้างกิจกรรมใหม่</h1>
                        <p class="mb-0 opacity-75">เพิ่มกิจกรรมใหม่เข้าสู่ระบบการจัดการองค์กร</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Content Section -->
        <div class="content-section">
            <!-- Breadcrumb -->
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item">
                        <a href="activity_management.php">
                            <i class="bi bi-house me-1"></i>การจัดการกิจกรรม
                        </a>
                    </li>
                    <li class="breadcrumb-item active" aria-current="page">สร้างกิจกรรมใหม่</li>
                </ol>
            </nav>

            <!-- Progress Indicator -->
            <div class="progress-indicator">
                <div class="progress-step active">
                    <div class="step-circle">1</div>
                    <div class="step-label">ข้อมูลพื้นฐาน</div>
                </div>
                <div class="progress-step">
                    <div class="step-circle">2</div>
                    <div class="step-label">รายละเอียด</div>
                </div>
                <div class="progress-step">
                    <div class="step-circle">3</div>
                    <div class="step-label">เสร็จสิ้น</div>
                </div>
            </div>

            <!-- Alert Messages -->
            <?php if (isset($error_message)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="bi bi-exclamation-circle me-2"></i>
                <?php echo $error_message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>

            <!-- Add Activity Form -->
            <div class="form-container">
                <h3 class="mb-4">
                    <i class="bi bi-file-earmark-plus me-2"></i>ข้อมูลกิจกรรม
                </h3>
                
                <form method="POST" id="activityForm">
                    <div class="row">
                        <!-- ชื่อกิจกรรม -->
                        <div class="col-md-12">
                            <div class="form-group">
                                <label for="activity_name" class="form-label">
                                    <i class="bi bi-tag me-1"></i>ชื่อกิจกรรม
                                    <span class="required">*</span>
                                </label>
                                <div class="position-relative">
                                    <input type="text" class="form-control" id="activity_name" name="activity_name" 
                                           placeholder="กรอกชื่อกิจกรรม" required maxlength="255">
                                    <div class="input-group-icon">
                                        <i class="bi bi-calendar-event"></i>
                                    </div>
                                </div>
                                <div class="form-text">
                                    <i class="bi bi-info-circle me-1"></i>ชื่อกิจกรรมที่ชัดเจนและสื่อความหมาย
                                </div>
                                <div class="form-validation-feedback" id="nameValidation"></div>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <!-- วันที่เริ่ม -->
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="start_date" class="form-label">
                                    <i class="bi bi-calendar-check me-1"></i>วันที่เริ่มกิจกรรม
                                    <span class="required">*</span>
                                </label>
                                <input type="date" class="form-control" id="start_date" name="start_date" required>
                                <div class="form-text">
                                    <i class="bi bi-clock me-1"></i>วันที่เริ่มต้นดำเนินกิจกรรม
                                </div>
                                <div class="form-validation-feedback" id="startDateValidation"></div>
                            </div>
                        </div>

                        <!-- วันที่สิ้นสุด -->
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="end_date" class="form-label">
                                    <i class="bi bi-calendar-x me-1"></i>วันที่สิ้นสุดกิจกรรม
                                    <span class="required">*</span>
                                </label>
                                <input type="date" class="form-control" id="end_date" name="end_date" required>
                                <div class="form-text">
                                    <i class="bi bi-flag me-1"></i>วันที่สิ้นสุดกิจกรรม
                                </div>
                                <div class="form-validation-feedback" id="endDateValidation"></div>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <!-- คะแนน -->
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="score" class="form-label">
                                    <i class="bi bi-award me-1"></i>คะแนนที่คาดหวัง
                                    <span class="required">*</span>
                                </label>
                                <div class="position-relative">
                                    <input type="number" class="form-control" id="score" name="score" 
                                           placeholder="0-100" required min="0" max="100" step="0.01">
                                    <div class="input-group-icon">
                                        <i class="bi bi-star"></i>
                                    </div>
                                </div>
                                <div class="form-text">
                                    <i class="bi bi-graph-up me-1"></i>คะแนนที่คาดหวังจากกิจกรรม (0-100)
                                </div>
                                <div class="form-validation-feedback" id="scoreValidation"></div>
                            </div>
                        </div>

                        <!-- สถานะ -->
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label">
                                    <i class="bi bi-info-circle me-1"></i>สถานะเริ่มต้น
                                </label>
                                <div class="form-control bg-light d-flex align-items-center">
                                    <i class="bi bi-hourglass-split me-2 text-warning"></i>
                                    <span class="text-muted">รอดำเนินการ</span>
                                </div>
                                <div class="form-text">
                                    <i class="bi bi-lightbulb me-1"></i>สถานะจะเปลี่ยนแปลงตามความคืบหน้า
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <!-- คำอธิบาย -->
                        <div class="col-md-12">
                            <div class="form-group position-relative">
                                <label for="description" class="form-label">
                                    <i class="bi bi-file-text me-1"></i>รายละเอียดกิจกรรม
                                    <span class="required">*</span>
                                </label>
                                <textarea class="form-control" id="description" name="description" 
                                          rows="4" placeholder="กรอกรายละเอียดกิจกรรม วัตถุประสงค์ และขั้นตอนการดำเนินงาน..." 
                                          required maxlength="1000"></textarea>
                                <div class="character-counter">
                                    <span id="charCount">0</span>/1000
                                </div>
                                <div class="form-text">
                                    <i class="bi bi-pencil me-1"></i>อธิบายรายละเอียด วัตถุประสงค์ และประโยชน์ที่คาดหวัง
                                </div>
                                <div class="form-validation-feedback" id="descriptionValidation"></div>
                            </div>
                        </div>
                    </div>

                    <!-- File Attachment Option -->
                    <div class="row">
                        <div class="col-md-12">
                            <div class="form-group">
                                <label class="form-label">
                                    <i class="bi bi-paperclip me-1"></i>การแนบไฟล์
                                </label>
                                
                                <div class="attachment-option-card p-3 border rounded-3">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="require_attachment" 
                                               name="require_attachment" value="1">
                                        <label class="form-check-label fw-medium" for="require_attachment">
                                            <i class="bi bi-file-earmark-plus me-2"></i>กิจกรรมนี้ต้องการให้ผู้เข้าร่วมแนบไฟล์
                                        </label>
                                    </div>
                                    
                                    <div class="attachment-details mt-3" id="attachmentDetails" style="display: none;">
                                        <div class="row">
                                            <div class="col-md-12">
                                                <label for="attachment_description" class="form-label small">
                                                    <i class="bi bi-info-circle me-1"></i>คำอธิบายเกี่ยวกับไฟล์ที่ต้องการ
                                                </label>
                                                <textarea class="form-control form-control-sm" id="attachment_description" 
                                                          name="attachment_description" rows="3" 
                                                          placeholder="ระบุประเภทไฟล์ที่ต้องการ เช่น รายงาน, แผนการดำเนินงาน, เอกสารประกอบการนำเสนอ..."></textarea>
                                                <div class="form-text">
                                                    <small>ข้อมูลนี้จะแสดงให้ผู้เข้าร่วมกิจกรรมเห็นเป็นคำแนะนำ</small>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="row mt-3">
                                            <div class="col-md-6">
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" id="allow_multiple_files" 
                                                           name="allow_multiple_files" value="1" checked>
                                                    <label class="form-check-label small" for="allow_multiple_files">
                                                        อนุญาตให้แนบหลายไฟล์
                                                    </label>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" id="is_file_required" 
                                                           name="is_file_required" value="1">
                                                    <label class="form-check-label small" for="is_file_required">
                                                        การแนบไฟล์เป็นข้อบังคับ
                                                    </label>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="alert alert-info mt-3 d-flex align-items-center">
                                        <i class="bi bi-lightbulb me-2"></i>
                                        <small>หากไม่เลือก ผู้เข้าร่วมกิจกรรมจะไม่ต้องแนบไฟล์ใดๆ</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Action Buttons -->
                    <div class="row mt-4">
                        <div class="col-12">
                            <div class="d-flex gap-3 justify-content-end">
                                <a href="activity_management.php" class="btn btn-secondary px-4">
                                    <i class="bi bi-arrow-left me-2"></i>ย้อนกลับ
                                </a>
                                <button type="submit" name="add_activity" class="btn btn-primary px-4" id="submitBtn">
                                    <div class="loading-spinner"></div>
                                    <i class="bi bi-check-lg me-2"></i>สร้างกิจกรรม
                                </button>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Elements
    const form = document.getElementById('activityForm');
    const submitBtn = document.getElementById('submitBtn');
    const nameInput = document.getElementById('activity_name');
    const startDateInput = document.getElementById('start_date');
    const endDateInput = document.getElementById('end_date');
    const scoreInput = document.getElementById('score');
    const descriptionInput = document.getElementById('description');
    const charCount = document.getElementById('charCount');
    
    // File attachment elements
    const requireAttachmentCheckbox = document.getElementById('require_attachment');
    const attachmentDetails = document.getElementById('attachmentDetails');
    
    // Progress steps
    const progressSteps = document.querySelectorAll('.progress-step');
    
    // Set default dates
    const today = new Date().toISOString().split('T')[0];
    startDateInput.value = today;
    
    // Character counter for description
    function updateCharCount() {
        const current = descriptionInput.value.length;
        charCount.textContent = current;
        
        if (current > 900) {
            charCount.parentElement.style.color = '#dc3545';
        } else if (current > 750) {
            charCount.parentElement.style.color = '#ffc107';
        } else {
            charCount.parentElement.style.color = '#6c757d';
        }
    }
    
    descriptionInput.addEventListener('input', updateCharCount);
    updateCharCount();
    
    // Real-time validation functions
    function validateName() {
        const value = nameInput.value.trim();
        const validation = document.getElementById('nameValidation');
        
        if (!value) {
            validation.textContent = 'กรุณากรอกชื่อกิจกรรม';
            validation.className = 'form-validation-feedback invalid';
            return false;
        } else if (value.length < 3) {
            validation.textContent = 'ชื่อกิจกรรมต้องมีอย่างน้อย 3 ตัวอักษร';
            validation.className = 'form-validation-feedback invalid';
            return false;
        } else {
            validation.textContent = 'ชื่อกิจกรรมถูกต้อง';
            validation.className = 'form-validation-feedback valid';
            return true;
        }
    }
    
    function validateDates() {
        const startDate = new Date(startDateInput.value);
        const endDate = new Date(endDateInput.value);
        const today = new Date();
        today.setHours(0, 0, 0, 0);
        
        const startValidation = document.getElementById('startDateValidation');
        const endValidation = document.getElementById('endDateValidation');
        
        let isValid = true;
        
        // Validate start date
        if (!startDateInput.value) {
            startValidation.textContent = 'กรุณาเลือกวันที่เริ่ม';
            startValidation.className = 'form-validation-feedback invalid';
            isValid = false;
        } else if (startDate < today) {
            startValidation.textContent = 'วันที่เริ่มไม่ควรเป็นวันที่ผ่านมาแล้ว';
            startValidation.className = 'form-validation-feedback invalid';
            isValid = false;
        } else {
            startValidation.textContent = 'วันที่เริ่มถูกต้อง';
            startValidation.className = 'form-validation-feedback valid';
        }
        
        // Validate end date
        if (!endDateInput.value) {
            endValidation.textContent = 'กรุณาเลือกวันที่สิ้นสุด';
            endValidation.className = 'form-validation-feedback invalid';
            isValid = false;
        } else if (endDate < startDate) {
            endValidation.textContent = 'วันที่สิ้นสุดต้องมาหลังวันที่เริ่ม';
            endValidation.className = 'form-validation-feedback invalid';
            isValid = false;
        } else {
            endValidation.textContent = 'วันที่สิ้นสุดถูกต้อง';
            endValidation.className = 'form-validation-feedback valid';
        }
        
        return isValid;
    }
    
    function validateScore() {
        const value = parseFloat(scoreInput.value);
        const validation = document.getElementById('scoreValidation');
        
        if (isNaN(value)) {
            validation.textContent = 'กรุณากรอกคะแนน';
            validation.className = 'form-validation-feedback invalid';
            return false;
        } else if (value < 0 || value > 100) {
            validation.textContent = 'คะแนนต้องอยู่ระหว่าง 0-100';
            validation.className = 'form-validation-feedback invalid';
            return false;
        } else {
            validation.textContent = 'คะแนนถูกต้อง';
            validation.className = 'form-validation-feedback valid';
            return true;
        }
    }
    
    function validateDescription() {
        const value = descriptionInput.value.trim();
        const validation = document.getElementById('descriptionValidation');
        
        if (!value) {
            validation.textContent = 'กรุณากรอกรายละเอียดกิจกรรม';
            validation.className = 'form-validation-feedback invalid';
            return false;
        } else if (value.length < 10) {
            validation.textContent = 'รายละเอียดควรมีอย่างน้อย 10 ตัวอักษร';
            validation.className = 'form-validation-feedback invalid';
            return false;
        } else {
            validation.textContent = 'รายละเอียดถูกต้อง';
            validation.className = 'form-validation-feedback valid';
            return true;
        }
    }
    
    function updateProgress() {
        const isNameValid = validateName();
        const areDatesValid = validateDates();
        const isScoreValid = validateScore();
        const isDescriptionValid = validateDescription();
        
        // Update progress steps
        if (isNameValid && areDatesValid) {
            progressSteps[1].classList.add('active');
        } else {
            progressSteps[1].classList.remove('active');
        }
        
        if (isNameValid && areDatesValid && isScoreValid && isDescriptionValid) {
            progressSteps[2].classList.add('active');
            submitBtn.disabled = false;
        } else {
            progressSteps[2].classList.remove('active');
            submitBtn.disabled = true;
        }
    }
    
    // File attachment toggle
    function toggleAttachmentDetails() {
        if (requireAttachmentCheckbox.checked) {
            attachmentDetails.style.display = 'block';
        } else {
            attachmentDetails.style.display = 'none';
            // Clear attachment form fields when disabled
            document.getElementById('attachment_description').value = '';
            document.getElementById('allow_multiple_files').checked = true;
            document.getElementById('is_file_required').checked = false;
        }
    }
    
    // Event listener for attachment checkbox
    requireAttachmentCheckbox.addEventListener('change', toggleAttachmentDetails);
    
    // Initialize attachment details visibility
    toggleAttachmentDetails();
    
    // Event listeners for real-time validation
    nameInput.addEventListener('input', function() {
        validateName();
        updateProgress();
    });
    
    startDateInput.addEventListener('change', function() {
        validateDates();
        updateProgress();
    });
    
    endDateInput.addEventListener('change', function() {
        validateDates();
        updateProgress();
    });
    
    scoreInput.addEventListener('input', function() {
        validateScore();
        updateProgress();
    });
    
    descriptionInput.addEventListener('input', function() {
        validateDescription();
        updateProgress();
    });
    
    // Initial validation
    updateProgress();
    
    // Form submission with loading state
    form.addEventListener('submit', function(e) {
        // Final validation
        const isValid = validateName() && validateDates() && validateScore() && validateDescription();
        
        if (!isValid) {
            e.preventDefault();
            
            // Show SweetAlert if available, otherwise use alert
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    icon: 'error',
                    title: 'ข้อมูลไม่ครบถ้วน',
                    text: 'กรุณาตรวจสอบข้อมูลให้ถูกต้องและครบถ้วน',
                    confirmButtonText: 'ตกลง'
                });
            } else {
                alert('กรุณาตรวจสอบข้อมูลให้ถูกต้องและครบถ้วน');
            }
            return false;
        }
        
        // Show loading state
        submitBtn.classList.add('loading');
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<div class="loading-spinner"></div><i class="bi bi-hourglass-split me-2"></i>กำลังสร้างกิจกรรม...';
        
        // Disable all form inputs
        const inputs = form.querySelectorAll('input, textarea, select, button');
        inputs.forEach(input => input.disabled = true);
        
        return true;
    });
    
    // Auto-dismiss alerts
    setTimeout(function() {
        const alerts = document.querySelectorAll('.alert');
        alerts.forEach(alert => {
            const bsAlert = new bootstrap.Alert(alert);
            bsAlert.close();
        });
    }, 8000);
    
    // Enhanced form interactions
    const formControls = document.querySelectorAll('.form-control');
    formControls.forEach(control => {
        control.addEventListener('focus', function() {
            this.parentElement.style.transform = 'scale(1.02)';
            this.parentElement.style.transition = 'transform 0.2s ease';
        });
        
        control.addEventListener('blur', function() {
            this.parentElement.style.transform = 'scale(1)';
        });
    });
    
    // Smooth scroll to first error
    function scrollToFirstError() {
        const firstError = document.querySelector('.form-validation-feedback.invalid');
        if (firstError) {
            firstError.scrollIntoView({ 
                behavior: 'smooth', 
                block: 'center' 
            });
        }
    }
    
    // Add keyboard shortcuts
    document.addEventListener('keydown', function(e) {
        // Ctrl + Enter to submit
        if (e.ctrlKey && e.key === 'Enter') {
            form.dispatchEvent(new Event('submit', { cancelable: true }));
        }
        
        // Escape to go back
        if (e.key === 'Escape') {
            window.location.href = 'activity_management.php';
        }
    });
});
</script>
</body>
</html>