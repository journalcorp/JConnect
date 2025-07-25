<?php
// filepath: c:\xampp\htdocs\web-portal-uat\pages\evaluation_detail.php
include_once '../includes/header.php';
include_once '../includes/menu.php';
require_once '../config.php';

$conn = dbConnect();

$emp_as = isset($_GET['emp_as']) ? $_GET['emp_as'] : '';
$emp_pe = isset($_GET['emp_pe']) ? $_GET['emp_pe'] : '';

if (empty($emp_as) && empty($emp_pe)) {
    echo '<div class="container mt-4"><div class="alert alert-danger">ไม่พบรหัสพนักงาน</div></div>';
    exit;
}

// กำหนดว่าจะแสดงข้อมูลแบบไหน
$display_mode = '';
$employee_id = '';
if (!empty($emp_as)) {
    $display_mode = 'assessor'; // แสดงข้อมูลผู้ประเมิน
    $employee_id = $emp_as;
} else {
    $display_mode = 'evaluated'; // แสดงข้อมูลผู้ถูกประเมิน
    $employee_id = $emp_pe;
}

// ดึงข้อมูลพนักงาน พร้อม SectionName
$sql_emp = "SELECT 
                e.employee_id, 
                e.first_name_th, 
                e.last_name_th, 
                e.position_th, 
                e.department, 
                s.SectionName 
            FROM employees e
            LEFT JOIN section s ON e.department = s.SectionID
            WHERE e.employee_id = ?";
$result_emp = sqlsrv_query($conn, $sql_emp, array($employee_id));
if ($result_emp === false) {
    die("Query failed: " . print_r(sqlsrv_errors(), true));
}
$emp = sqlsrv_fetch_array($result_emp, SQLSRV_FETCH_ASSOC);
sqlsrv_free_stmt($result_emp);

// ดึงข้อมูลการประเมินตาม mode ที่เลือก
if ($display_mode == 'assessor') {
    // แสดงการประเมินที่ emp_as ต้องทำ
    $sql_eval = "
        SELECT 
            fah.emp_as,
            fah.emp_pe,
            ISNULL(ef.evaluation_period, 'ไม่ระบุ') AS evaluation_period,
            fah.start_date,
            fah.end_date,
            fs.status_form,
            fah.FormID AS form_id,
            fah.FormName,
            e1.first_name_th AS emp_as_first,
            e1.last_name_th AS emp_as_last,
            e2.first_name_th AS emp_pe_first,
            e2.last_name_th AS emp_pe_last
        FROM form_all_hr fah
        LEFT JOIN employees e1 ON fah.emp_as = e1.employee_id
        LEFT JOIN employees e2 ON fah.emp_pe = e2.employee_id
        LEFT JOIN evaluation_forms ef ON CONCAT('F', RIGHT('00' + CAST(fah.FormID AS VARCHAR), 3)) = ef.form_id AND fah.emp_as = ef.emp_as AND fah.emp_pe = ef.emp_pe
        LEFT JOIN form_score fs ON CONCAT('F', RIGHT('00' + CAST(fah.FormID AS VARCHAR), 3)) = fs.FormID AND fah.emp_as = fs.emp_as AND fah.emp_pe = fs.emp_pe
        WHERE fah.emp_as = ? AND fah.status_form = 'active'
        ORDER BY fah.start_date DESC
    ";
    $result_eval = sqlsrv_query($conn, $sql_eval, array($employee_id));
    
    // นับจำนวนคนที่ต้องประเมินทั้งหมด
    $sql_total = "
        SELECT COUNT(*) AS total 
        FROM form_all_hr 
        WHERE emp_as = ? AND status_form = 'active'
    ";
    $result_total = sqlsrv_query($conn, $sql_total, array($employee_id));
    
    // นับจำนวนคนที่ประเมินแล้ว
    $sql_done = "
        SELECT COUNT(*) AS done
        FROM form_all_hr fah
        LEFT JOIN form_score fs ON CONCAT('F', RIGHT('00' + CAST(fah.FormID AS VARCHAR), 3)) = fs.FormID AND fah.emp_as = fs.emp_as AND fah.emp_pe = fs.emp_pe
        WHERE fah.emp_as = ? AND fah.status_form = 'active' AND fs.status_form = 'enable'
    ";
    $result_done = sqlsrv_query($conn, $sql_done, array($employee_id));
} else {
    // แสดงการประเมินที่ emp_pe ได้รับ
    $sql_eval = "
        SELECT 
            fah.emp_as,
            fah.emp_pe,
            ISNULL(ef.evaluation_period, 'ไม่ระบุ') AS evaluation_period,
            fah.start_date,
            fah.end_date,
            fs.status_form,
            fah.FormID AS form_id,
            fah.FormName,
            e1.first_name_th AS emp_as_first,
            e1.last_name_th AS emp_as_last,
            e2.first_name_th AS emp_pe_first,
            e2.last_name_th AS emp_pe_last
        FROM form_all_hr fah
        LEFT JOIN employees e1 ON fah.emp_as = e1.employee_id
        LEFT JOIN employees e2 ON fah.emp_pe = e2.employee_id
        LEFT JOIN evaluation_forms ef ON CONCAT('F', RIGHT('00' + CAST(fah.FormID AS VARCHAR), 3)) = ef.form_id AND fah.emp_as = ef.emp_as AND fah.emp_pe = ef.emp_pe
        LEFT JOIN form_score fs ON CONCAT('F', RIGHT('00' + CAST(fah.FormID AS VARCHAR), 3)) = fs.FormID AND fah.emp_as = fs.emp_as AND fah.emp_pe = fs.emp_pe
        WHERE fah.emp_pe = ? AND fah.status_form = 'active'
        ORDER BY fah.start_date DESC
    ";
    $result_eval = sqlsrv_query($conn, $sql_eval, array($employee_id));
    
    // นับจำนวนการประเมินที่ emp_pe ได้รับทั้งหมด
    $sql_total = "
        SELECT COUNT(*) AS total 
        FROM form_all_hr 
        WHERE emp_pe = ? AND status_form = 'active'
    ";
    $result_total = sqlsrv_query($conn, $sql_total, array($employee_id));
    
    // นับจำนวนการประเมินที่เสร็จแล้ว
    $sql_done = "
        SELECT COUNT(*) AS done
        FROM form_all_hr fah
        LEFT JOIN form_score fs ON CONCAT('F', RIGHT('00' + CAST(fah.FormID AS VARCHAR), 3)) = fs.FormID AND fah.emp_as = fs.emp_as AND fah.emp_pe = fs.emp_pe
        WHERE fah.emp_pe = ? AND fah.status_form = 'active' AND fs.status_form = 'enable'
    ";
    $result_done = sqlsrv_query($conn, $sql_done, array($employee_id));
}
if ($result_eval === false) {
    die("Query failed: " . print_r(sqlsrv_errors(), true));
}

// นับจำนวนทั้งหมด
if ($result_total === false) {
    die("Query failed: " . print_r(sqlsrv_errors(), true));
}
$total_row = sqlsrv_fetch_array($result_total, SQLSRV_FETCH_ASSOC);
$total_count = $total_row['total'] ?? 0;
sqlsrv_free_stmt($result_total);

// นับจำนวนที่เสร็จแล้ว
if ($result_done === false) {
    die("Query failed: " . print_r(sqlsrv_errors(), true));
}
$done_row = sqlsrv_fetch_array($result_done, SQLSRV_FETCH_ASSOC);
$done_count = $done_row['done'] ?? 0;
sqlsrv_free_stmt($result_done);
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>รายละเอียดข้อมูลการประเมิน</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <style>
        body { font-family: 'Prompt', sans-serif; background: #f6f8fa; }
        .table th, .table td { vertical-align: middle; text-align: center; }
        /* ปรับ CSS เฉพาะส่วน Dashboard */
        .dashboard-card {
            border: none;
            border-radius: 1.5rem;
            box-shadow: 0 2px 16px 0 rgba(0,0,0,0.07);
            transition: transform 0.2s;
            min-height: 180px;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1.5rem 0.5rem;
        }
        .dashboard-card:hover {
            transform: translateY(-4px) scale(1.03);
        }
        .dashboard-icon {
            font-size: 2.8rem;
            margin-bottom: 0.7rem;
            display: block;
        }
        .dashboard-card h6 {
            font-size: 1.1rem;
            margin-bottom: 0.2rem;
            font-weight: 600;
        }
        .dashboard-card h2 {
            font-size: 2.3rem;
            font-weight: 700;
            margin-bottom: 0;
        }
        @media (max-width: 991.98px) {
            .dashboard-card {
                min-height: 140px;
                padding: 1rem 0.5rem;
            }
            .dashboard-icon { font-size: 2rem; }
            .dashboard-card h2 { font-size: 1.5rem; }
        }
        @media (max-width: 575.98px) {
            .dashboard-card {
                min-height: 100px;
                padding: 0.7rem 0.2rem;
            }
            .dashboard-icon { font-size: 1.3rem; }
            .dashboard-card h2 { font-size: 1.1rem; }
        }
        .profile-box {
            background: #fff;
            border-radius: 1rem;
            box-shadow: 0 2px 8px 0 rgba(0,0,0,0.06);
            padding: 1.5rem 2rem;
            margin-bottom: 2rem;
        }
        .badge {
            font-size: 1rem;
            padding: 0.6em 1.2em;
        }
        .table thead th {
            background: #e9ecef;
        }
        .btn-secondary {
            border-radius: 2rem;
            padding-left: 2rem;
            padding-right: 2rem;
        }
    </style>
</head>
<body>
<div class="container mt-4">
    <h2 class="mb-4 fw-bold"><i class="bi bi-clipboard-data"></i> รายละเอียดข้อมูลการประเมิน</h2>
    <?php if ($emp): ?>
        <div class="profile-box d-flex align-items-center gap-4">
            <div>
                <i class="bi bi-person-circle" style="font-size:3rem;color:#0d6efd"></i>
            </div>
            <div>
                <div><strong>รหัสพนักงาน:</strong> <?php echo htmlspecialchars($emp['employee_id']); ?></div>
                <div><strong>ชื่อ-นามสกุล:</strong> <?php echo htmlspecialchars($emp['first_name_th'] . ' ' . $emp['last_name_th']); ?></div>
                <div><strong>ตำแหน่ง:</strong> <?php echo htmlspecialchars($emp['position_th']); ?></div>
                <div><strong>แผนก:</strong>
                    <?php echo (!empty($emp['SectionName'])) ? htmlspecialchars($emp['SectionName']) : '-'; ?>
                </div>
                <div><strong>ประเภท:</strong> 
                    <span class="badge <?php echo ($display_mode == 'assessor') ? 'bg-primary' : 'bg-success'; ?>">
                        <?php echo ($display_mode == 'assessor') ? 'ผู้ประเมิน' : 'ผู้ถูกประเมิน'; ?>
                    </span>
                </div>
            </div>
        </div>
    <?php else: ?>
        <div class="alert alert-warning">ไม่พบข้อมูลพนักงาน</div>
    <?php endif; ?>

    <!-- Dashboard การประเมิน -->
    <div class="row mb-4 g-4">
        <div class="col-md-3">
            <div class="card dashboard-card text-bg-primary text-center">
                <div class="card-body">
                    <div class="dashboard-icon"><i class="bi bi-people-fill"></i></div>
                    <h6 class="card-title mb-1"><?php echo ($display_mode == 'assessor') ? 'จำนวนที่ต้องประเมิน' : 'จำนวนการประเมิน'; ?></h6>
                    <h2 class="fw-bold"><?php echo $total_count; ?></h2>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card dashboard-card text-bg-success text-center">
                <div class="card-body">
                    <div class="dashboard-icon"><i class="bi bi-check-circle-fill"></i></div>
                    <h6 class="card-title mb-1"><?php echo ($display_mode == 'assessor') ? 'ประเมินแล้ว' : 'ถูกประเมินแล้ว'; ?></h6>
                    <h2 class="fw-bold"><?php echo $done_count; ?></h2>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card dashboard-card text-bg-warning text-center">
                <div class="card-body">
                    <div class="dashboard-icon"><i class="bi bi-exclamation-circle-fill"></i></div>
                    <h6 class="card-title mb-1"><?php echo ($display_mode == 'assessor') ? 'ยังไม่ประเมิน' : 'ยังไม่ถูกประเมิน'; ?></h6>
                    <h2 class="fw-bold"><?php echo ($total_count - $done_count); ?></h2>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card dashboard-card text-bg-info text-center">
                <div class="card-body">
                    <div class="dashboard-icon"><i class="bi bi-bar-chart-fill"></i></div>
                    <h6 class="card-title mb-1">เปอร์เซ็นต์สำเร็จ</h6>
                    <h2 class="fw-bold">
                        <?php echo $total_count > 0 ? round(($done_count / $total_count) * 100, 1) : 0; ?>%
                    </h2>
                </div>
            </div>
        </div>
    </div>

    <div class="d-flex justify-content-between align-items-center mt-4 mb-3">
        <h5 class="fw-semibold mb-0"><i class="bi bi-list-task"></i> รายการการประเมินที่เกี่ยวข้อง</h5>
        <button class="btn btn-primary" onclick="sendBulkReminderEmail()" id="bulkEmailBtn">
            <i class="bi bi-envelope-fill"></i> ส่งเมลแจ้งเตือนทั้งหมด
        </button>
    </div>
    <div class="table-responsive shadow-sm rounded-4">
    <table class="table table-bordered table-hover align-middle mb-0">
        <thead>
            <tr>
                <th>ชื่อแบบฟอร์ม</th>
                <th>วันที่เริ่ม</th>
                <th>วันที่สิ้นสุด</th>
                <th><?php echo ($display_mode == 'assessor') ? 'ผู้ที่ต้องประเมิน' : 'ผู้ประเมิน'; ?></th>
                <th>สถานะ</th>
                <th style="width:120px;">การจัดการ</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($result_eval): ?>
                <?php while ($row = sqlsrv_fetch_array($result_eval, SQLSRV_FETCH_ASSOC)): ?>
                    <tr>
                        <td class="text-start"><?php echo htmlspecialchars($row['FormName']);?> <span class="text-muted">- <?php echo htmlspecialchars($row['evaluation_period']);?></span></td>
                        <td><?php 
                            $start_date = $row['start_date'];
                            if (is_object($start_date) && get_class($start_date) === 'DateTime') {
                                echo $start_date->format('d/m/Y');
                            } else {
                                echo date('d/m/Y', strtotime($start_date));
                            }
                        ?></td>
                        <td><?php 
                            $end_date = $row['end_date'];
                            if (is_object($end_date) && get_class($end_date) === 'DateTime') {
                                echo $end_date->format('d/m/Y');
                            } else {
                                echo date('d/m/Y', strtotime($end_date));
                            }
                        ?></td>
                        <td>
                            <?php 
                            if ($display_mode == 'assessor') {
                                // แสดงชื่อผู้ที่ต้องประเมิน (emp_pe)
                                echo htmlspecialchars($row['emp_pe_first'] . ' ' . $row['emp_pe_last']);
                                echo '<br><span class="text-muted small">ID: ' . htmlspecialchars($row['emp_pe']) . '</span>';
                            } else {
                                // แสดงชื่อผู้ประเมิน (emp_as)
                                echo htmlspecialchars($row['emp_as_first'] . ' ' . $row['emp_as_last']);
                                echo '<br><span class="text-muted small">ID: ' . htmlspecialchars($row['emp_as']) . '</span>';
                            }
                            ?>
                        </td>
                        <td>
                            <?php
                            $today = date('Y-m-d');
                            $end_date_str = is_object($row['end_date']) ? $row['end_date']->format('Y-m-d') : $row['end_date'];
                            if (strtotime($end_date_str) < strtotime($today)) {
                                echo '<span class="badge bg-danger"><i class="bi bi-x-circle"></i> หมดเขตการประเมิน</span>';
                            } elseif ($row['status_form'] === 'enable') {
                                echo '<span class="badge bg-success"><i class="bi bi-check-circle"></i> ทำแบบประเมินแล้ว</span>';
                            } elseif ($row['status_form'] === 'disable') {
                                echo '<span class="badge bg-warning text-dark"><i class="bi bi-exclamation-circle"></i> ยังไม่ทำแบบประเมิน</span>';
                            } else {
                                echo '<span class="badge bg-secondary"><i class="bi bi-question-circle"></i> ไม่ทราบสถานะ</span>';
                            }
                            ?>
                        </td>
                        <td>
                            <?php
                            // กำหนดผู้รับเมล (คนที่ต้องทำแบบประเมิน - เสมอเป็น emp_as)
                            $recipient_id = $row['emp_as'];  // ผู้ประเมินคือคนที่ต้องทำแบบประเมิน
                            $recipient_name = $row['emp_as_first'] . ' ' . $row['emp_as_last'];
                            
                            // แสดงปุ่มส่งเมลเฉพาะเมื่อยังไม่ทำแบบประเมินและยังไม่หมดเขต
                            $today = date('Y-m-d');
                            $end_date_str = is_object($row['end_date']) ? $row['end_date']->format('Y-m-d') : $row['end_date'];
                            $is_expired = strtotime($end_date_str) < strtotime($today);
                            
                            if ($row['status_form'] !== 'enable' && !$is_expired) {
                                echo '<button class="btn btn-warning btn-sm" onclick="sendReminderEmail(\'' . 
                                     htmlspecialchars($recipient_id) . '\', \'' . 
                                     htmlspecialchars($recipient_name) . '\', \'' . 
                                     htmlspecialchars($row['FormName']) . '\', \'' . 
                                     htmlspecialchars($row['emp_pe']) . '\')" title="ส่งเมลแจ้งเตือนไปยัง ' . htmlspecialchars($recipient_name) . '">
                                     <i class="bi bi-envelope"></i> ส่งเมล
                                     </button>';
                            } elseif ($row['status_form'] === 'enable') {
                                echo '<span class="text-success small"><i class="bi bi-check-circle"></i> เสร็จแล้ว</span>';
                            } elseif ($is_expired) {
                                echo '<span class="text-danger small"><i class="bi bi-x-circle"></i> หมดเขต</span>';
                            } else {
                                echo '<span class="text-muted small">-</span>';
                            }
                            ?>
                        </td>
                    </tr>
                <?php endwhile; ?>
                <?php sqlsrv_free_stmt($result_eval); ?>
            <?php else: ?>
                <tr>
                    <td colspan="6" class="text-center">ไม่พบข้อมูลการประเมิน</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
    </div>
    <a href="performance_review.php" class="btn btn-secondary mt-4"><i class="bi bi-arrow-left"></i> ย้อนกลับ</a>
</div>

<!-- Modal สำหรับแสดงผลการส่งเมล -->
<div class="modal fade" id="emailModal" tabindex="-1" aria-labelledby="emailModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="emailModalLabel">ส่งเมลแจ้งเตือน</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="emailModalBody">
                <!-- เนื้อหาจะถูกเพิ่มผ่าน JavaScript -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ปิด</button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
function sendReminderEmail(recipientId, recipientName, formName, empPe) {
    // แสดง loading
    const modalBody = document.getElementById('emailModalBody');
    modalBody.innerHTML = '<div class="text-center"><div class="spinner-border" role="status"><span class="visually-hidden">กำลังส่งเมล...</span></div><p class="mt-2">กำลังส่งเมลแจ้งเตือนไปยัง ' + recipientName + '...</p></div>';
    
    // แสดง modal
    const modal = new bootstrap.Modal(document.getElementById('emailModal'));
    modal.show();
    
    // สร้าง FormData สำหรับส่งข้อมูล
    const formData = new FormData();
    formData.append('action', 'send_reminder_email');
    formData.append('recipient_id', recipientId);
    formData.append('recipient_name', recipientName);
    formData.append('form_name', formName);
    formData.append('emp_pe', empPe);
    
    // ส่ง AJAX request
    fetch('send_email.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            modalBody.innerHTML = `
                <div class="alert alert-success">
                    <i class="bi bi-check-circle-fill"></i> ส่งเมลแจ้งเตือนสำเร็จ!
                </div>
                <p><strong>ผู้รับเมล:</strong> ${recipientName} (${recipientId})</p>
                <p><strong>อีเมล:</strong> ${data.recipient_email || 'ไม่แสดง'}</p>
                <p><strong>แบบฟอร์ม:</strong> ${formName}</p>
                <p><strong>เวลาส่ง:</strong> ${new Date().toLocaleString('th-TH')}</p>
            `;
        } else {
            modalBody.innerHTML = `
                <div class="alert alert-danger">
                    <i class="bi bi-exclamation-triangle-fill"></i> เกิดข้อผิดพลาดในการส่งเมล
                </div>
                <p><strong>ข้อผิดพลาด:</strong> ${data.message || 'ไม่ทราบสาเหตุ'}</p>
            `;
        }
    })
    .catch(error => {
        console.error('Error:', error);
        modalBody.innerHTML = `
            <div class="alert alert-danger">
                <i class="bi bi-exclamation-triangle-fill"></i> เกิดข้อผิดพลาดในการเชื่อมต่อ
            </div>
            <p><strong>ข้อผิดพลาด:</strong> ${error.message}</p>
        `;
    });
}

function sendBulkReminderEmail() {
    // รวบรวมข้อมูลทุกคนที่ยังไม่ได้ทำแบบประเมิน
    const pendingAssessments = [];
    const rows = document.querySelectorAll('tbody tr');
    
    rows.forEach(row => {
        const btn = row.querySelector('button[onclick*="sendReminderEmail"]');
        if (btn) {
            const onclickValue = btn.getAttribute('onclick');
            const matches = onclickValue.match(/sendReminderEmail\('([^']+)',\s*'([^']+)',\s*'([^']+)',\s*'([^']*)'\)/);
            if (matches) {
                pendingAssessments.push({
                    recipientId: matches[1],
                    recipientName: matches[2],
                    formName: matches[3],
                    empPe: matches[4]
                });
            }
        }
    });
    
    if (pendingAssessments.length === 0) {
        alert('ไม่มีการประเมินที่ต้องส่งเมลแจ้งเตือน');
        return;
    }
    
    // แสดง loading
    const modalBody = document.getElementById('emailModalBody');
    modalBody.innerHTML = `
        <div class="text-center">
            <div class="spinner-border" role="status">
                <span class="visually-hidden">กำลังส่งเมล...</span>
            </div>
            <p class="mt-2">กำลังส่งเมลแจ้งเตือนไปยัง ${pendingAssessments.length} คน...</p>
            <div class="progress mt-3">
                <div class="progress-bar" role="progressbar" style="width: 0%" id="emailProgress"></div>
            </div>
        </div>
    `;
    
    // แสดง modal
    const modal = new bootstrap.Modal(document.getElementById('emailModal'));
    modal.show();
    
    // ส่งเมลทีละคน
    let successCount = 0;
    let errorCount = 0;
    let processedCount = 0;
    const results = [];
    
    const sendNext = async (index) => {
        if (index >= pendingAssessments.length) {
            // แสดงผลสรุป
            modalBody.innerHTML = `
                <div class="alert ${errorCount === 0 ? 'alert-success' : 'alert-warning'}">
                    <i class="bi bi-check-circle-fill"></i> ส่งเมลแจ้งเตือนเสร็จสิ้น!
                </div>
                <div class="row">
                    <div class="col-md-6">
                        <p><strong>ส่งสำเร็จ:</strong> <span class="text-success">${successCount} คน</span></p>
                    </div>
                    <div class="col-md-6">
                        <p><strong>ส่งไม่สำเร็จ:</strong> <span class="text-danger">${errorCount} คน</span></p>
                    </div>
                </div>
                <div class="mt-3">
                    <h6>รายละเอียด:</h6>
                    <div style="max-height: 200px; overflow-y: auto;">
                        ${results.map(r => `
                            <div class="small ${r.success ? 'text-success' : 'text-danger'}">
                                <i class="bi bi-${r.success ? 'check' : 'x'}-circle"></i> 
                                ${r.name} - ${r.success ? 'สำเร็จ' : r.error}
                            </div>
                        `).join('')}
                    </div>
                </div>
            `;
            return;
        }
        
        const assessment = pendingAssessments[index];
        
        try {
            const formData = new FormData();
            formData.append('action', 'send_reminder_email');
            formData.append('recipient_id', assessment.recipientId);
            formData.append('recipient_name', assessment.recipientName);
            formData.append('form_name', assessment.formName);
            formData.append('emp_pe', assessment.empPe);
            
            const response = await fetch('send_email.php', {
                method: 'POST',
                body: formData
            });
            
            const data = await response.json();
            
            if (data.success) {
                successCount++;
                results.push({
                    name: assessment.recipientName,
                    success: true
                });
            } else {
                errorCount++;
                results.push({
                    name: assessment.recipientName,
                    success: false,
                    error: data.message
                });
            }
        } catch (error) {
            errorCount++;
            results.push({
                name: assessment.recipientName,
                success: false,
                error: error.message
            });
        }
        
        processedCount++;
        const progress = (processedCount / pendingAssessments.length) * 100;
        document.getElementById('emailProgress').style.width = progress + '%';
        
        // รอ 500ms ก่อนส่งคนต่อไป เพื่อไม่ให้ spam server
        setTimeout(() => sendNext(index + 1), 500);
    };
    
    sendNext(0);
}
</script>
</body>
</html>