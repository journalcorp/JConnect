<?php
session_start();
include_once '../includes/header.php';
include_once '../includes/menu.php';
include_once '../config.php';

// สร้างการเชื่อมต่อฐานข้อมูล
$conn = dbConnect();

// ตรวจสอบว่า employee_id มีอยู่ใน Session หรือไม่
if (!isset($_SESSION['employee_id'])) {
    die("ไม่พบ employee_id ใน Session");
}

// ตรวจสอบ ID กิจกรรม
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: activity_management.php");
    exit();
}

$activity_id = (int)$_GET['id'];
$employeeID = $_SESSION['employee_id'];

// ฟังก์ชันแก้ไขกิจกรรม
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_activity'])) {
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
        'require_attachment' => (bool)$require_attachment,
        'attachment_description' => $attachment_description,
        'allow_multiple_files' => (bool)$allow_multiple_files,
        'is_file_required' => (bool)$is_file_required
    ], JSON_UNESCAPED_UNICODE);

    // Check if attachment_settings column exists
    $check_column_sql = "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'activities' AND COLUMN_NAME = 'attachment_settings'";
    $check_stmt = sqlsrv_query($conn, $check_column_sql);
    $column_exists = sqlsrv_fetch_array($check_stmt);
    sqlsrv_free_stmt($check_stmt);
    
    if ($column_exists) {
        $sql = "UPDATE activities SET name = ?, start_date = ?, end_date = ?, score = ?, description = ?, attachment_settings = ? WHERE id = ?";
        $params = array($activity_name, $start_date, $end_date, $score, $description, $attachment_settings, $activity_id);
    } else {
        $sql = "UPDATE activities SET name = ?, start_date = ?, end_date = ?, score = ?, description = ? WHERE id = ?";
        $params = array($activity_name, $start_date, $end_date, $score, $description, $activity_id);
    }
    
    $stmt = sqlsrv_query($conn, $sql, $params);
    
    if ($stmt) {
        sqlsrv_free_stmt($stmt);
        $success_message = "แก้ไขกิจกรรมเรียบร้อยแล้ว";
    } else {
        $error_message = "เกิดข้อผิดพลาดในการแก้ไขกิจกรรม";
        $errors = sqlsrv_errors();
        if ($errors) {
            $error_message .= " - " . $errors[0]['message'];
        }
    }
}

// ดึงข้อมูลกิจกรรม
$sql = "SELECT * FROM activities WHERE id = ?";
$stmt = sqlsrv_query($conn, $sql, array($activity_id));

if ($stmt === false || !($activity = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC))) {
    sqlsrv_free_stmt($stmt);
    header("Location: activity_management.php");
    exit();
}

// ดึงข้อมูลผู้เข้าร่วมกิจกรรม (สมมติว่ามีตาราง activity_participants)
$participants_sql = "
    SELECT ap.*, e.name as employee_name, e.email, e.department 
    FROM activity_participants ap 
    LEFT JOIN employees e ON ap.employee_id = e.id 
    WHERE ap.activity_id = ?
    ORDER BY ap.joined_date DESC
";
$participants_stmt = sqlsrv_query($conn, $participants_sql, array($activity_id));
$participants = [];
if ($participants_stmt) {
    while ($participant = sqlsrv_fetch_array($participants_stmt, SQLSRV_FETCH_ASSOC)) {
        $participants[] = $participant;
    }
    sqlsrv_free_stmt($participants_stmt);
}

// Format dates
$start_date = $activity['start_date'];
$end_date = $activity['end_date'];

if ($start_date instanceof DateTime) {
    $formatted_start_date = $start_date->format('d/m/Y');
    $edit_start_date = $start_date->format('Y-m-d');
} else {
    $formatted_start_date = date('d/m/Y', strtotime($start_date));
    $edit_start_date = date('Y-m-d', strtotime($start_date));
}

if ($end_date instanceof DateTime) {
    $formatted_end_date = $end_date->format('d/m/Y');
    $edit_end_date = $end_date->format('Y-m-d');
} else {
    $formatted_end_date = date('d/m/Y', strtotime($end_date));
    $edit_end_date = date('Y-m-d', strtotime($end_date));
}

// Parse attachment settings
$attachment_settings = null;
if (isset($activity['attachment_settings']) && !empty($activity['attachment_settings'])) {
    $attachment_settings = json_decode($activity['attachment_settings'], true);
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>รายละเอียดกิจกรรม - <?php echo htmlspecialchars($activity['name']); ?></title>
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
            max-width: 1200px;
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
        
        .header-content {
            position: relative;
            z-index: 2;
        }
        
        .content-section {
            padding: 2.5rem;
        }
        
        .detail-card {
            background: linear-gradient(145deg, #f8f9fa 0%, #e9ecef 100%);
            border-radius: 20px;
            padding: 2rem;
            margin-bottom: 2rem;
            border: 1px solid rgba(0, 123, 255, 0.1);
            position: relative;
        }
        
        .detail-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, #007bff 0%, #6610f2 100%);
            border-radius: 20px 20px 0 0;
        }
        
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .info-item {
            background: white;
            padding: 1.5rem;
            border-radius: 15px;
            border: 1px solid #e9ecef;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        
        .info-item:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0, 123, 255, 0.1);
        }
        
        .info-label {
            font-size: 0.85rem;
            color: #6c757d;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 0.5rem;
        }
        
        .info-value {
            font-size: 1.1rem;
            font-weight: 500;
            color: #333;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #007bff 0%, #6610f2 100%);
            border: none;
            border-radius: 12px;
            padding: 0.875rem 2rem;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0, 123, 255, 0.3);
        }
        
        .btn-warning {
            background: linear-gradient(135deg, #ffc107 0%, #fd7e14 100%);
            border: none;
            border-radius: 12px;
            padding: 0.875rem 2rem;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        
        .btn-warning:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(255, 193, 7, 0.3);
        }
        
        .participants-table {
            background: white;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }
        
        .table thead th {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border: none;
            padding: 1rem;
            font-weight: 600;
            color: #495057;
            text-transform: uppercase;
            font-size: 0.8rem;
            letter-spacing: 0.5px;
        }
        
        .table tbody td {
            padding: 1rem;
            vertical-align: middle;
            border-color: #f1f3f4;
        }
        
        .table tbody tr:hover {
            background-color: #f0f8ff;
        }
        
        .badge-status {
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
        }
        
        .modal-content {
            border-radius: 20px;
            border: none;
        }
        
        .modal-header {
            background: linear-gradient(135deg, #ffc107 0%, #fd7e14 100%);
            color: white;
            border-radius: 20px 20px 0 0;
        }
        
        .form-control, .form-select {
            border-radius: 12px;
            border: 2px solid #e9ecef;
            transition: all 0.3s ease;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: #007bff;
            box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.15);
        }
        
        .attachment-option-card {
            background: linear-gradient(145deg, #f8f9ff 0%, #f0f4ff 100%);
            border: 2px solid #e7f1ff !important;
            transition: all 0.3s ease;
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
    </style>
</head>
<body>
<div class="container-fluid py-4">
    <div class="main-container">
        <!-- Header Section -->
        <div class="header-section">
            <div class="header-content">
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <h1 class="mb-2">
                            <i class="bi bi-eye me-3"></i>รายละเอียดกิจกรรม
                        </h1>
                        <p class="mb-0 opacity-75"><?php echo htmlspecialchars($activity['name']); ?></p>
                    </div>
                    <div class="col-md-4 text-end">
                        <a href="activity_management.php" class="btn btn-light btn-lg">
                            <i class="bi bi-arrow-left me-2"></i>กลับสู่รายการ
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Content Section -->
        <div class="content-section">
            <!-- Alert Messages -->
            <?php if (isset($success_message)): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="bi bi-check-circle me-2"></i><?php echo $success_message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>

            <?php if (isset($error_message)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="bi bi-exclamation-circle me-2"></i><?php echo $error_message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>

            <!-- Activity Details -->
            <div class="detail-card">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h3><i class="bi bi-info-circle me-2"></i>ข้อมูลกิจกรรม</h3>
                    <button type="button" class="btn btn-warning" data-bs-toggle="modal" data-bs-target="#editModal">
                        <i class="bi bi-pencil-square me-2"></i>แก้ไขกิจกรรม
                    </button>
                </div>

                <div class="info-grid">
                    <div class="info-item">
                        <div class="info-label">ID กิจกรรม</div>
                        <div class="info-value">
                            <span class="badge bg-secondary fs-6"><?php echo $activity['id']; ?></span>
                        </div>
                    </div>
                    
                    <div class="info-item">
                        <div class="info-label">วันที่เริ่มกิจกรรม</div>
                        <div class="info-value">
                            <i class="bi bi-calendar3 text-primary me-2"></i>
                            <?php echo $formatted_start_date; ?>
                        </div>
                    </div>
                    
                    <div class="info-item">
                        <div class="info-label">วันที่สิ้นสุด</div>
                        <div class="info-value">
                            <i class="bi bi-calendar-check text-success me-2"></i>
                            <?php echo $formatted_end_date; ?>
                        </div>
                    </div>
                    
                    <div class="info-item">
                        <div class="info-label">คะแนนที่คาดหวัง</div>
                        <div class="info-value">
                            <span class="badge bg-warning text-dark fs-6">
                                <i class="bi bi-star me-1"></i>
                                <?php echo $activity['score']; ?> คะแนน
                            </span>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-12">
                        <div class="info-item">
                            <div class="info-label">รายละเอียดกิจกรรม</div>
                            <div class="info-value" style="line-height: 1.6;">
                                <?php echo nl2br(htmlspecialchars($activity['description'])); ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Attachment Settings -->
                <?php if ($attachment_settings && $attachment_settings['require_attachment']): ?>
                <div class="row mt-3">
                    <div class="col-12">
                        <div class="info-item" style="border-left: 4px solid #17a2b8;">
                            <div class="info-label">การตั้งค่าไฟล์แนบ</div>
                            <div class="info-value">
                                <div class="d-flex align-items-center mb-2">
                                    <i class="bi bi-paperclip text-info me-2"></i>
                                    <strong class="text-info">มีการกำหนดให้แนบไฟล์</strong>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <span class="badge <?php echo $attachment_settings['is_file_required'] ? 'bg-warning text-dark' : 'bg-secondary'; ?>">
                                            <i class="bi bi-<?php echo $attachment_settings['is_file_required'] ? 'exclamation' : 'dash'; ?>-circle me-1"></i>
                                            <?php echo $attachment_settings['is_file_required'] ? 'ไฟล์จำเป็น' : 'ไฟล์ไม่จำเป็น'; ?>
                                        </span>
                                    </div>
                                    <div class="col-md-6">
                                        <span class="badge <?php echo $attachment_settings['allow_multiple_files'] ? 'bg-primary' : 'bg-secondary'; ?>">
                                            <i class="bi bi-<?php echo $attachment_settings['allow_multiple_files'] ? 'files' : 'file-earmark'; ?> me-1"></i>
                                            <?php echo $attachment_settings['allow_multiple_files'] ? 'หลายไฟล์' : 'ไฟล์เดียว'; ?>
                                        </span>
                                    </div>
                                </div>
                                
                                <?php if (!empty($attachment_settings['attachment_description'])): ?>
                                <div class="mt-2 p-2 bg-light rounded">
                                    <small class="text-muted">คำอธิบาย:</small>
                                    <div><?php echo htmlspecialchars($attachment_settings['attachment_description']); ?></div>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <!-- Participants List -->
            <div class="detail-card">
                <h3 class="mb-4">
                    <i class="bi bi-people me-2"></i>ผู้เข้าร่วมกิจกรรม 
                    <span class="badge bg-primary"><?php echo count($participants); ?> คน</span>
                </h3>

                <?php if (empty($participants)): ?>
                <div class="text-center py-5">
                    <i class="bi bi-people text-muted" style="font-size: 3rem;"></i>
                    <h5 class="text-muted mt-3">ยังไม่มีผู้เข้าร่วมกิจกรรม</h5>
                    <p class="text-muted">เมื่อมีผู้สมัครเข้าร่วมกิจกรรม รายชื่อจะแสดงที่นี่</p>
                </div>
                <?php else: ?>
                <div class="participants-table">
                    <div class="table-responsive">
                        <table class="table mb-0">
                            <thead>
                                <tr>
                                    <th>ชื่อผู้เข้าร่วม</th>
                                    <th>อีเมล</th>
                                    <th>แผนก</th>
                                    <th>วันที่เข้าร่วม</th>
                                    <th>สถานะ</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($participants as $participant): ?>
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="avatar bg-primary text-white rounded-circle d-flex align-items-center justify-content-center me-2" style="width: 40px; height: 40px;">
                                                <?php echo strtoupper(substr($participant['employee_name'] ?? 'U', 0, 1)); ?>
                                            </div>
                                            <strong><?php echo htmlspecialchars($participant['employee_name'] ?? 'ไม่ระบุชื่อ'); ?></strong>
                                        </div>
                                    </td>
                                    <td><?php echo htmlspecialchars($participant['email'] ?? 'ไม่ระบุ'); ?></td>
                                    <td><?php echo htmlspecialchars($participant['department'] ?? 'ไม่ระบุ'); ?></td>
                                    <td>
                                        <?php 
                                        if ($participant['joined_date']) {
                                            if ($participant['joined_date'] instanceof DateTime) {
                                                echo $participant['joined_date']->format('d/m/Y H:i');
                                            } else {
                                                echo date('d/m/Y H:i', strtotime($participant['joined_date']));
                                            }
                                        } else {
                                            echo 'ไม่ระบุ';
                                        }
                                        ?>
                                    </td>
                                    <td>
                                        <span class="badge-status bg-success text-white">เข้าร่วมแล้ว</span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Edit Activity Modal -->
<div class="modal fade" id="editModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-pencil-square me-2"></i>แก้ไขกิจกรรม</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-12">
                            <div class="mb-3">
                                <label class="form-label">ชื่อกิจกรรม</label>
                                <input type="text" class="form-control" name="activity_name" 
                                       value="<?php echo htmlspecialchars($activity['name']); ?>" required>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">วันที่เริ่ม</label>
                                <input type="date" class="form-control" name="start_date" 
                                       value="<?php echo $edit_start_date; ?>" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">วันที่สิ้นสุด</label>
                                <input type="date" class="form-control" name="end_date" 
                                       value="<?php echo $edit_end_date; ?>" required>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">คะแนนที่คาดหวัง</label>
                                <input type="number" class="form-control" name="score" 
                                       value="<?php echo $activity['score']; ?>" min="0" max="100" step="0.01" required>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">รายละเอียดกิจกรรม</label>
                        <textarea class="form-control" name="description" rows="4" required><?php echo htmlspecialchars($activity['description']); ?></textarea>
                    </div>

                    <!-- File Attachment Settings -->
                    <div class="mb-3">
                        <label class="form-label">การแนบไฟล์</label>
                        <div class="attachment-option-card p-3 border rounded-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="require_attachment" 
                                       name="require_attachment" value="1"
                                       <?php echo ($attachment_settings && $attachment_settings['require_attachment']) ? 'checked' : ''; ?>>
                                <label class="form-check-label fw-medium" for="require_attachment">
                                    <i class="bi bi-file-earmark-plus me-2"></i>กิจกรรมนี้ต้องการให้ผู้เข้าร่วมแนบไฟล์
                                </label>
                            </div>
                            
                            <div class="attachment-details mt-3" id="attachmentDetails" 
                                 style="display: <?php echo ($attachment_settings && $attachment_settings['require_attachment']) ? 'block' : 'none'; ?>;">
                                <div class="mb-3">
                                    <label for="attachment_description" class="form-label small">
                                        <i class="bi bi-info-circle me-1"></i>คำอธิบายเกี่ยวกับไฟล์ที่ต้องการ
                                    </label>
                                    <textarea class="form-control form-control-sm" id="attachment_description" 
                                              name="attachment_description" rows="3" 
                                              placeholder="ระบุประเภทไฟล์ที่ต้องการ เช่น รายงาน, แผนการดำเนินงาน, เอกสารประกอบการนำเสนอ..."><?php echo $attachment_settings ? htmlspecialchars($attachment_settings['attachment_description']) : ''; ?></textarea>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="allow_multiple_files" 
                                                   name="allow_multiple_files" value="1"
                                                   <?php echo ($attachment_settings && $attachment_settings['allow_multiple_files']) ? 'checked' : ''; ?>>
                                            <label class="form-check-label small" for="allow_multiple_files">
                                                อนุญาตให้แนบหลายไฟล์
                                            </label>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="is_file_required" 
                                                   name="is_file_required" value="1"
                                                   <?php echo ($attachment_settings && $attachment_settings['is_file_required']) ? 'checked' : ''; ?>>
                                            <label class="form-check-label small" for="is_file_required">
                                                การแนบไฟล์เป็นข้อบังคับ
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                    <button type="submit" name="edit_activity" class="btn btn-primary">
                        <i class="bi bi-check-lg me-1"></i>บันทึกการแก้ไข
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php
// ปิด statement และการเชื่อมต่อ
if ($stmt) {
    sqlsrv_free_stmt($stmt);
}
sqlsrv_close($conn);
?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // File attachment toggle
    const requireAttachmentCheckbox = document.getElementById('require_attachment');
    const attachmentDetails = document.getElementById('attachmentDetails');
    
    function toggleAttachmentDetails() {
        if (requireAttachmentCheckbox.checked) {
            attachmentDetails.style.display = 'block';
        } else {
            attachmentDetails.style.display = 'none';
            // Clear attachment form fields when disabled
            document.getElementById('attachment_description').value = '';
            document.getElementById('allow_multiple_files').checked = false;
            document.getElementById('is_file_required').checked = false;
        }
    }
    
    requireAttachmentCheckbox.addEventListener('change', toggleAttachmentDetails);
    
    // Auto-dismiss alerts
    setTimeout(function() {
        const alerts = document.querySelectorAll('.alert');
        alerts.forEach(alert => {
            const bsAlert = new bootstrap.Alert(alert);
            bsAlert.close();
        });
    }, 5000);
});
</script>
</body>
</html>
