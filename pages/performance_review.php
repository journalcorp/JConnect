<?php

// เปิดการแสดงข้อผิดพลาด

ini_set('display_errors', 1);

ini_set('display_startup_errors', 1);

error_reporting(E_ALL);



// รวมไฟล์ header และ menu

include_once '../includes/header.php';

include_once '../includes/menu.php';



// ดึงข้อมูลแบบฟอร์มประเมินจาก table form_all_hr

$conn = dbConnect();

if (!$conn) {
    die("Connection failed: " . print_r(sqlsrv_errors(), true));
}

// Query ข้อมูลการประเมินจาก form_all_hr (แสดงแต่ละ emp_pe เพียงครั้งเดียว)
$sql2 = "
    SELECT 
        fah.emp_pe,
        ISNULL(ef.evaluation_period, 'ไม่ระบุ') AS evaluation_period,
        MIN(fah.start_date) AS start_date,
        MAX(fah.end_date) AS end_date,
        MIN(fah.FormID) AS form_id,
        MIN(fah.FormName) AS FormName,
        e2.first_name_th AS pe_first_name,
        e2.last_name_th AS pe_last_name
    FROM form_all_hr fah
    LEFT JOIN employees e2 ON fah.emp_pe = e2.employee_id
    LEFT JOIN evaluation_forms ef ON CONCAT('F', RIGHT('00' + CAST(fah.FormID AS VARCHAR), 3)) = ef.form_id AND fah.emp_pe = ef.emp_pe
    WHERE fah.status_form = 'active'
    GROUP BY fah.emp_pe, e2.first_name_th, e2.last_name_th, ef.evaluation_period
    ORDER BY MIN(fah.CreatedDate) DESC
";

$result2 = sqlsrv_query($conn, $sql2);

if (!$result2) {
    die("Query failed: " . print_r(sqlsrv_errors(), true));
}

// นับจำนวนคนที่ต้องประเมินทั้งหมด (emp_pe ไม่ซ้ำ จาก form_all_hr)
$sql_total = "SELECT COUNT(DISTINCT emp_pe) AS total FROM form_all_hr WHERE status_form = 'active'";
$result_total = sqlsrv_query($conn, $sql_total);
if ($result_total) {
    $total_row = sqlsrv_fetch_array($result_total, SQLSRV_FETCH_ASSOC);
    $total_count = $total_row['total'] ?? 0;
    sqlsrv_free_stmt($result_total);
} else {
    $total_count = 0;
}

// นับจำนวนคนที่ประเมินแล้ว (emp_pe ไม่ซ้ำ ที่มี form_score.status_form = 'enable')
$sql_done = "
    SELECT COUNT(DISTINCT fah.emp_pe) AS done
    FROM form_all_hr fah
    INNER JOIN form_score fs ON CONCAT('F', RIGHT('00' + CAST(fah.FormID AS VARCHAR), 3)) = fs.FormID AND fah.emp_as = fs.emp_as AND fah.emp_pe = fs.emp_pe
    WHERE fah.status_form = 'active' AND fs.status_form = 'enable'
";
$result_done = sqlsrv_query($conn, $sql_done);
if ($result_done) {
    $done_row = sqlsrv_fetch_array($result_done, SQLSRV_FETCH_ASSOC);
    $done_count = $done_row['done'] ?? 0;
    sqlsrv_free_stmt($result_done);
} else {
    $done_count = 0;
}

// จำนวนที่ยังไม่ประเมิน
$not_done_count = max(0, $total_count - $done_count);

// เปอร์เซ็นต์สำเร็จ
$percent = ($total_count > 0) ? round(($done_count / $total_count) * 100, 0) : 0;

// นับจำนวนการประเมินทั้งหมด (ทุกแถวที่ status_form = 'active' หรือ 'enable')
$sql_evaluation_total = "SELECT COUNT(*) AS evaluation_total FROM form_score WHERE status_form IN ('active', 'enable')";
$result_evaluation_total = sqlsrv_query($conn, $sql_evaluation_total);
$evaluation_total = 0;
if ($result_evaluation_total && $row_evaluation_total = sqlsrv_fetch_array($result_evaluation_total, SQLSRV_FETCH_ASSOC)) {
    $evaluation_total = $row_evaluation_total['evaluation_total'];
    sqlsrv_free_stmt($result_evaluation_total);
}

// นับจำนวนการประเมินที่ประเมินไปแล้ว (status_form = 'enable')
$sql_done_evaluation_total = "SELECT COUNT(*) AS done_evaluation_total FROM form_score WHERE status_form = 'enable'";
$result_done_evaluation_total = sqlsrv_query($conn, $sql_done_evaluation_total);
$done_evaluation_total = 0;
if ($result_done_evaluation_total && $row_done_evaluation_total = sqlsrv_fetch_array($result_done_evaluation_total, SQLSRV_FETCH_ASSOC)) {
    $done_evaluation_total = $row_done_evaluation_total['done_evaluation_total'];
    sqlsrv_free_stmt($result_done_evaluation_total);
}

// นับจำนวนการประเมินทั้งหมดจาก form_all_hr
$sql_allhr_total = "SELECT COUNT(*) AS allhr_total FROM form_all_hr WHERE status_form = 'active'";
$result_allhr_total = sqlsrv_query($conn, $sql_allhr_total);
$allhr_total = 0;
if ($result_allhr_total && $row_allhr_total = sqlsrv_fetch_array($result_allhr_total, SQLSRV_FETCH_ASSOC)) {
    $allhr_total = $row_allhr_total['allhr_total'];
    sqlsrv_free_stmt($result_allhr_total);
}
?>



<!DOCTYPE html>

<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>จัดการแบบฟอร์มสำหรับ HR - Web Portal</title>

    <!-- Bootstrap CSS -->

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <link rel="stylesheet" href="../assets/css/styles.css">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">

    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">

</head>

<style>
    /* Mobile App Look (เฉพาะมือถือ) */
    @media (max-width: 575.98px) {
        body {
            background: #f4f6fb;
            padding: 0;
        }
        .container {
            padding: 0 2px;
            max-width: 100vw;
        }
        .dashboard-card {
            min-height: 90px;
            border-radius: 1.2rem;
            box-shadow: 0 2px 12px 0 rgba(0,0,0,0.09);
            margin-bottom: 10px;
            width: 100vw;
            max-width: 100vw;
            padding: 0.7rem 0.2rem;
        }
        .dashboard-icon {
            font-size: 1.5rem;
        }
        .card-header, .card-body {
            padding-left: 0.5rem;
            padding-right: 0.5rem;
        }
        .table-responsive {
            padding: 0;
            margin: 0;
        }
        .table th, .table td {
            font-size: 0.95rem;
            padding: 0.3rem 0.2rem;
        }
        .btn, .btn-info, .btn-primary, .btn-success, .btn-warning {
            font-size: 1rem;
            padding: 0.5rem 1.2rem;
            border-radius: 1.2rem;
        }
        .navbar, .menu, .sidebar {
            display: none !important;
        }
        h1.text-center {
            font-size: 1.2rem;
            margin-bottom: 0.7rem;
        }
        .card.mt-4 {
            margin-top: 0.7rem !important;
        }
        .card {
            border-radius: 1.2rem !important;
        }
        .table {
            border-radius: 1.2rem !important;
        }
        .badge {
            font-size: 0.9rem;
            padding: 0.2rem 0.7rem;
        }
    }

    body {

        font-family: 'Prompt', sans-serif;

        background-color: #f8f9fa;

        color: #212529;

    }

    .navbar {

        background-color: #ffffff;

        border-bottom: 1px solid #dee2e6;

    }

    .navbar .navbar-brand {

        font-weight: 600;

        color: #212529;

    }

    .navbar .navbar-brand:hover {

        color: #495057;

    }

    .card-header {

        font-size: 1.25rem;

        font-weight: 600;

    }

    .btn-sm i {

        margin-right: 5px;

    }

    .table th, .table td {

        vertical-align: middle;

        text-align: center;

    }

    .table th {

        background-color: #f1f1f1;

    }

    .card.mt-4.shadow-lg.border-0.rounded-4 {

        margin-top: 1.5rem;

        border: none;

        border-radius: 1.5rem;

        overflow: hidden;

        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);

    }

    .card-header.bg-gradient.bg-secondary.text-white.text-center.rounded-top-4 {

        background: linear-gradient(90deg, #6c757d 0%, #343a40 100%);

        padding: 1rem;

        border-top-left-radius: 1.5rem;

        border-top-right-radius: 1.5rem;

        font-size: 1.3rem;

    }

    .table-responsive {

        overflow-x: auto;

    }

    .table.table-bordered.table-hover.align-middle.mb-0.rounded-4.overflow-hidden {

        border-collapse: separate;

        border-spacing: 0;

        width: 100%;

    }

    .table-light {

        background-color: #f8f9fa;

    }

    .table th {

        background-color: #f1f1f1;

        font-weight: 600;

    }

    .btn-info {

        color: #fff;

        background: linear-gradient(90deg, #36c6f0 0%, #007bff 100%);

        border: none;

        transition: 0.2s;

    }

    .btn-info:hover {

        background: linear-gradient(90deg, #007bff 0%, #36c6f0 100%);

        color: #fff;

        transform: scale(1.05);

    }

    .badge.bg-info-subtle {

        background: #e7f7fd;

        color: #0d6efd;

        font-size: 1rem;

    }

    .badge.bg-warning-subtle {

        background: #fff7e6;

        color: #ff9800;

        font-size: 1rem;

    }

    .dashboard-card {
        border-radius: 1.5rem;
        min-height: 180px;
        box-shadow: 0 2px 16px 0 rgba(0,0,0,0.07);
        transition: transform 0.2s;
        border: none;
    }
    .dashboard-card:hover {
        transform: translateY(-4px) scale(1.03);
    }
    .dashboard-icon {
        font-size: 2.7rem;
    }
    @media (max-width: 991.98px) {
        .dashboard-card { min-height: 130px; }
        .dashboard-icon { font-size: 2rem; }
    }
    @media (max-width: 575.98px) {
        .dashboard-card { min-height: 100px; }
        .dashboard-icon { font-size: 1.3rem; }
    }

</style>



<body>

<div class="container mt-4">

    <h1 class="text-center mb-4">จัดการแบบฟอร์มสำหรับ HR</h1>

    <!-- ปุ่มเพิ่มแบบประเมิน -->
    <div class="d-flex justify-content-end mb-3">
        <a href="hr_select_evaluation.php" class="btn btn-primary me-2">
            <i class="bi bi-plus-circle"></i> เพิ่มแบบฟอร์ม
        </a>
        <a href="import_excel.php" class="btn btn-success me-2">
            <i class="bi bi-file-earmark-spreadsheet"></i> Import ข้อมูล
        </a>
        <a href="print_report.php" class="btn btn-warning me-2" target="_blank">
            <i class="bi bi-printer"></i> Print Report
        </a>
        <a href="export_excel_report.php" class="btn btn-info">
            <i class="bi bi-file-earmark-excel"></i> Export Excel
        </a>
    </div>



    <!-- Dashboard จำนวนคนที่ประเมินแล้ว/ทั้งหมด -->
    <div class="row mb-4 g-4">
        
        
        
        <!-- Dashboard จำนวนการประเมินทั้งหมด (จาก form_all_hr) -->
        <div class="col-12 col-sm-6 col-lg-3">
            <div class="card dashboard-card text-center" style="background:#6c757d;color:#fff;">
                <div class="card-body d-flex flex-column justify-content-center align-items-center">
                    <div class="dashboard-icon mb-2"><i class="bi bi-journal-text"></i></div>
                    <div class="fw-semibold">จำนวนการประเมินทั้งหมด</div>
                    <div class="fs-1 fw-bold"><?php echo $allhr_total; ?></div>
                </div>
            </div>
        </div>
        
        <!-- Dashboard จำนวนการประเมินที่ประเมินไปแล้ว -->
        <div class="col-12 col-sm-6 col-lg-3">
            <div class="card dashboard-card text-center" style="background:#28a745;color:#fff;">
                <div class="card-body d-flex flex-column justify-content-center align-items-center">
                    <div class="dashboard-icon mb-2"><i class="bi bi-journal-check"></i></div>
                    <div class="fw-semibold">ประเมินเสร็จสิ้น</div>
                    <div class="fs-1 fw-bold"><?php echo $done_evaluation_total; ?></div>
                </div>
            </div>
        </div>
        
        <!-- Dashboard จำนวนการประเมินที่ยังไม่ได้ทำการประเมิน (form_score ที่ status_form = 'disable') -->
        <div class="col-12 col-sm-6 col-lg-3">
            <div class="card dashboard-card text-center" style="background:#ffc107;color:#000;">
                <div class="card-body d-flex flex-column justify-content-center align-items-center">
                    <div class="dashboard-icon mb-2"><i class="bi bi-journal-x"></i></div>
                    <div class="fw-semibold">ยังไม่ได้ทำการประเมิน</div>
                    <div class="fs-1 fw-bold">
                        <?php
                        // นับจำนวนแบบประเมินที่ยังไม่ได้ทำ (form_score ที่ status_form = 'disable')
                        $sql_not_done_evaluation = "SELECT COUNT(*) AS not_done_evaluation FROM form_score WHERE status_form = 'disable'";
                        $result_not_done_evaluation = sqlsrv_query($conn, $sql_not_done_evaluation);
                        $not_done_evaluation = 0;
                        if ($result_not_done_evaluation && $row_not_done_evaluation = sqlsrv_fetch_array($result_not_done_evaluation, SQLSRV_FETCH_ASSOC)) {
                            $not_done_evaluation = $row_not_done_evaluation['not_done_evaluation'];
                            sqlsrv_free_stmt($result_not_done_evaluation);
                        }
                        echo $not_done_evaluation;
                        ?>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Dashboard เปอร์เซ็นต์ความสำเร็จของการทำแบบประเมิน -->
        <div class="col-12 col-sm-6 col-lg-3">
            <div class="card dashboard-card text-center" style="background:#36c6f0;color:#fff;">
                <div class="card-body d-flex flex-column justify-content-center align-items-center">
                    <div class="dashboard-icon mb-2"><i class="bi bi-percent"></i></div>
                    <div class="fw-semibold">เปอร์เซ็นต์ความสำเร็จ</div>
                    <div class="fs-1 fw-bold">
                        <?php
                        // คำนวณเปอร์เซ็นต์สำเร็จจากจำนวนที่ประเมินไปแล้ว / จำนวนทั้งหมด
                        $success_percent = ($allhr_total > 0) ? round(($done_evaluation_total / $allhr_total) * 100, 0) : 0;
                        echo $success_percent . '%';
                        ?>
                    </div>
                </div>
            </div>
        </div>
        
    </div>

    <!-- ตารางแสดงข้อมูลการประเมิน -->

    <div class="card mt-4 shadow-lg border-0 rounded-4">

        <div class="card-header bg-gradient bg-secondary text-white text-center rounded-top-4" style="font-size:1.3rem;">

            <i class="bi bi-clipboard-data"></i> ข้อมูลการประเมิน

        </div>

        <div class="card-body bg-light rounded-bottom-4">

            <div class="table-responsive">

                <table class="table table-bordered table-hover align-middle mb-0 rounded-4 overflow-hidden">

                    <thead class="table-light">

                        <tr>

                            <th style="width:60px;">ลำดับ</th>

                            <th>ชื่อ - นามสกุลผู้ถูกประเมิน</th>

                            <th>ชื่อแบบฟอร์ม</th>

                            <th>วันที่เริ่ม</th>

                            <th>วันที่สิ้นสุด</th>

                            <th>ประเมินแล้ว/ทั้งหมด</th>

                            <th style="width:120px;">ดูรายละเอียด</th>

                        </tr>

                    </thead>

                    <tbody>

                        <?php if ($result2): $i=1; ?>

                            <?php while ($row = sqlsrv_fetch_array($result2, SQLSRV_FETCH_ASSOC)): ?>

                                <tr>

                                    <td class="fw-semibold"><?php echo $i++; ?></td>

                                    <td class="text-start">

                                        <i class="bi bi-person-circle text-primary"></i>

                                        <?php echo htmlspecialchars($row['pe_first_name'] . ' ' . $row['pe_last_name']); ?>
<span class="text-muted small">- <?php echo htmlspecialchars($row['emp_pe']); ?></span>

                                    </td>

                                    <td>

                                        <i class="bi bi-file-earmark-text"></i>

                                        <?php echo htmlspecialchars($row['FormName']); ?>

                                        <span class="text-muted small">(<?php echo htmlspecialchars($row['evaluation_period']); ?>)</span>

                                    </td>

                                    <td>

                                        <span class="badge bg-info-subtle text-dark px-3 py-2">

                                            <i class="bi bi-calendar-event"></i>

                                        <?php 
                                        $date_value = $row['start_date'];
                                        if (is_object($date_value) && get_class($date_value) === 'DateTime') {
                                            echo $date_value->format('d/m/Y');
                                        } else {
                                            echo date('d/m/Y', strtotime($date_value));
                                        }
                                        ?>

                                        </span>

                                    </td>

                                    <td>

                                        <span class="badge bg-warning-subtle text-dark px-3 py-2">

                                            <i class="bi bi-calendar-check"></i>

                                        <?php 
                                        $date_value = $row['end_date'];
                                        if (is_object($date_value) && get_class($date_value) === 'DateTime') {
                                            echo $date_value->format('d/m/Y');
                                        } else {
                                            echo date('d/m/Y', strtotime($date_value));
                                        }
                                        ?>

                                        </span>

                                    </td>

                                    <td>
                                        <?php
                                        $emp_pe = $row['emp_pe'];
                                        // จำนวนคนที่ต้องประเมินทั้งหมด (emp_as ไม่ซ้ำใน form_all_hr)
                                        $sql_total = "SELECT COUNT(DISTINCT emp_as) AS total FROM form_all_hr WHERE emp_pe = ? AND status_form = 'active'";
                                        $params_total = array($emp_pe);
                                        $result_total = sqlsrv_query($conn, $sql_total, $params_total);
                                        $total = 0;
                                        if ($result_total && $row_total = sqlsrv_fetch_array($result_total, SQLSRV_FETCH_ASSOC)) {
                                            $total = $row_total['total'];
                                            sqlsrv_free_stmt($result_total);
                                        }

                                        // จำนวนคนที่ประเมินแล้ว (emp_as ที่มี record ใน form_score ที่ status_form = 'enable' สำหรับ emp_pe นี้)
                                        $sql_done = "SELECT COUNT(DISTINCT fs.emp_as) AS done
                                            FROM form_score fs
                                            WHERE fs.emp_pe = ? AND fs.status_form = 'enable'";
                                        $params_done = [$emp_pe];
                                        $result_done = sqlsrv_query($conn, $sql_done, $params_done);
                                        $done = 0;
                                        if ($result_done && $row_done = sqlsrv_fetch_array($result_done, SQLSRV_FETCH_ASSOC)) {
                                            $done = $row_done['done'];
                                            sqlsrv_free_stmt($result_done);
                                        }
                                        echo "<span class='fw-bold text-success'>$done</span> / <span class='fw-bold'>$total</span> คน";
                                        ?>
                                    </td>

                                    <td>

                                        <a href="evaluation_detail.php?emp_pe=<?php echo urlencode($row['emp_pe']); ?>" class="btn btn-info btn-sm rounded-pill px-3">

                                            <i class="bi bi-eye"></i> ดูรายละเอียด

                                        </a>

                                    </td>

                                </tr>

                            <?php endwhile; ?>

                        <?php else: ?>

                            <tr>

                                <td colspan="7" class="text-center text-muted">ไม่มีข้อมูล</td>

                            </tr>

                        <?php endif; ?>

                    </tbody>

                </table>

            </div>

        </div>

    </div>



<!-- Bootstrap JS -->

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

</body>

</html>