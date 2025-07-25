<?php
// เปิด error reporting สำหรับ debug
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include_once '../config.php';
include_once '../includes/header.php';
include_once '../includes/menu.php';

// ตรวจสอบสิทธิ์ เฉพาะ HR เท่านั้น (division JD011 หรือ JD001)
if (!isset($_SESSION['division']) || !in_array($_SESSION['division'], ['JD011', 'JD001'])) {
    echo '<div class="container mt-5"><div class="alert alert-danger">คุณไม่มีสิทธิ์เข้าถึงหน้านี้</div></div>';
    include_once '../includes/footer.php';
    exit;
}


$conn = dbConnect();
if (!$conn) die('Database connection failed');

// Get all divisions (ฝ่าย) for dropdown
$divisionOptions = [];
$divisionSql = "SELECT DeptID, DeptName FROM dept ORDER BY DeptName";
$divisionStmt = sqlsrv_query($conn, $divisionSql);
if ($divisionStmt) {
    while ($row = sqlsrv_fetch_array($divisionStmt, SQLSRV_FETCH_ASSOC)) {
        $divisionOptions[] = $row;
    }
    sqlsrv_free_stmt($divisionStmt);
}

// Get selected division from GET
$selectedDivision = isset($_GET['division']) ? $_GET['division'] : '';

// Main query, filter by division if selected
$params = [];
$sql = "
SELECT e.employee_id, e.prefix_th, e.first_name_th, e.last_name_th, d.DeptName, cpr.dominant_color
FROM employees e
LEFT JOIN dept d ON e.division = d.DeptID
LEFT JOIN (
    SELECT employee_id, dominant_color, created_at,
           ROW_NUMBER() OVER (PARTITION BY employee_id ORDER BY created_at DESC) AS rn
    FROM color_personality_results
) cpr ON e.employee_id = cpr.employee_id AND cpr.rn = 1
";
if ($selectedDivision !== '') {
    $sql .= " WHERE d.DeptID = ? ";
    $params[] = $selectedDivision;
}
$sql .= " ORDER BY d.DeptName, e.employee_id";

$stmt = sqlsrv_query($conn, $sql, $params);
$results = [];
if ($stmt) {
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $results[] = $row;
    }
    sqlsrv_free_stmt($stmt);
}

$colorMap = [
    'purple' => '#8B5CF6',
    'yellow' => '#FCD34D',
    'blue'   => '#3B82F6',
    'green'  => '#10B981',
];
$colorName = [
    'purple' => 'Purple (STRATEGIC THINKING)',
    'yellow' => 'Yellow (INFLUENCING)',
    'blue'   => 'Blue (RELATIONSHIP BUILDING)',
    'green'  => 'Green (EXECUTING)',
];

// สรุปจำนวนแต่ละสีในแต่ละฝ่าย
$summaryByDivision = [];
$summaryAll = [
    'purple' => 0,
    'yellow' => 0,
    'blue' => 0,
    'green' => 0
];
foreach ($results as $row) {
    $division = $row['DeptName'] ?? '-';
    $color = $row['dominant_color'] ?? '';
    if (!isset($summaryByDivision[$division])) {
        $summaryByDivision[$division] = [
            'purple' => 0,
            'yellow' => 0,
            'blue' => 0,
            'green' => 0
        ];
    }
    if (isset($summaryByDivision[$division][$color])) {
        $summaryByDivision[$division][$color]++;
    }
    if (isset($summaryAll[$color])) {
        $summaryAll[$color]++;
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>รายงานบุคลิกภาพสีแต่ละฝ่าย</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        html, body {
            font-family: 'Prompt', 'Segoe UI', Arial, sans-serif !important;
            background: #f6f8fa;
        }
        .color-dot {
            display:inline-block;
            width:18px;
            height:18px;
            border-radius:50%;
            margin-right:7px;
            vertical-align:middle;
            border:2px solid #fff;
            box-shadow:0 1px 3px rgba(0,0,0,0.07);
        }
        .card {
            border-radius: 1.25rem;
            box-shadow: 0 4px 24px 0 rgba(80, 80, 120, 0.08);
        }
        .table thead th {
            vertical-align: middle;
        }
        .table-sm th, .table-sm td {
            font-size: 1rem;
        }
        .table-summary th, .table-summary td {
            font-size: 1.05rem;
            font-weight: 500;
        }
        .table-summary th {
            background: #f3f4f6;
        }
        .table-summary tr td:first-child {
            font-weight: 600;
        }
        .summary-card .card-title {
            font-size: 1.15rem;
            font-weight: 600;
            color: #3B3B4F;
        }
        .summary-card {
            background: #f9fafb;
        }
        .main-header {
            font-weight: 700;
            letter-spacing: 0.5px;
            color: #2d2d3a;
        }
        .table-primary th {
            font-size: 1.08rem;
        }
        @media (max-width: 575.98px) {
            .card-body { padding: 1rem !important; }
            .table th, .table td { font-size: 0.97rem; }
            .main-header { font-size: 1.1rem; }
        }
    </style>
</head>
<body>
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-10">
            <div class="card shadow-lg border-0 rounded-4">
                <div class="card-body p-4">
                    <div class="d-flex align-items-center mb-4">
                        <i class="bi bi-palette fs-2 me-2 text-primary"></i>
                        <h2 class="mb-0 main-header">รายงานบุคลิกภาพสีแต่ละฝ่าย</h2>
                    </div>
                    <!-- Filter by Division (ฝ่าย) -->
                    <form method="get" class="row g-3 align-items-center mb-3">
                        <div class="col-auto">
                            <label for="division" class="col-form-label fw-semibold">เลือกฝ่าย:</label>
                        </div>
                        <div class="col-auto">
                            <select name="division" id="division" class="form-select" onchange="this.form.submit()">
                                <option value="">-- ทุกฝ่าย --</option>
                                <?php foreach ($divisionOptions as $division): ?>
                                    <option value="<?php echo htmlspecialchars($division['DeptID']); ?>" <?php if ($selectedDivision == $division['DeptID']) echo 'selected'; ?>>
                                        <?php echo htmlspecialchars($division['DeptName']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </form>
                    <!-- Color Legend -->
                    <div class="mb-4">
                        <span class="me-3"><span class="color-dot" style="background:#8B5CF6;"></span> <span class="fw-semibold" style="color:#8B5CF6">Purple</span></span>
                        <span class="me-3"><span class="color-dot" style="background:#FCD34D;"></span> <span class="fw-semibold" style="color:#FCD34D">Yellow</span></span>
                        <span class="me-3"><span class="color-dot" style="background:#3B82F6;"></span> <span class="fw-semibold" style="color:#3B82F6">Blue</span></span>
                        <span class="me-3"><span class="color-dot" style="background:#10B981;"></span> <span class="fw-semibold" style="color:#10B981">Green</span></span>
                    </div>
                    <!-- Summary by division and company -->
                    <div class="mb-4">
                        <div class="row g-3">
                            <div class="col-md-6 order-md-2">
                                <div class="card border-0 shadow-sm h-100 summary-card">
                                    <div class="card-body">
                                        <h5 class="card-title mb-3"><i class="bi bi-bar-chart-fill"></i> สรุปทั้งบริษัท</h5>
                                        <div class="table-responsive">
                                            <table class="table table-sm table-bordered align-middle mb-0 table-summary">
                                                <thead>
                                                    <tr>
                                                        <th class="text-center"><span class="color-dot" style="background:#8B5CF6;"></span> Purple</th>
                                                        <th class="text-center"><span class="color-dot" style="background:#FCD34D;"></span> Yellow</th>
                                                        <th class="text-center"><span class="color-dot" style="background:#3B82F6;"></span> Blue</th>
                                                        <th class="text-center"><span class="color-dot" style="background:#10B981;"></span> Green</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <tr>
                                                        <td class="text-center" style="color:#8B5CF6;"><?php echo $summaryAll['purple']; ?></td>
                                                        <td class="text-center" style="color:#FCD34D;"><?php echo $summaryAll['yellow']; ?></td>
                                                        <td class="text-center" style="color:#3B82F6;"><?php echo $summaryAll['blue']; ?></td>
                                                        <td class="text-center" style="color:#10B981;"><?php echo $summaryAll['green']; ?></td>
                                                    </tr>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6 order-md-1">
                                <div class="card border-0 shadow-sm h-100 summary-card">
                                    <div class="card-body">
                                        <h5 class="card-title mb-3"><i class="bi bi-people-fill"></i> สรุปแต่ละฝ่าย</h5>
                                        <div class="table-responsive">
                                            <table class="table table-sm table-bordered align-middle mb-0 table-summary">
                                                <thead>
                                                    <tr>
                                                        <th>ฝ่าย</th>
                                                        <th class="text-center"><span class="color-dot" style="background:#8B5CF6;"></span> Purple</th>
                                                        <th class="text-center"><span class="color-dot" style="background:#FCD34D;"></span> Yellow</th>
                                                        <th class="text-center"><span class="color-dot" style="background:#3B82F6;"></span> Blue</th>
                                                        <th class="text-center"><span class="color-dot" style="background:#10B981;"></span> Green</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($summaryByDivision as $divName => $counts): ?>
                                                    <tr>
                                                        <td><?php echo htmlspecialchars($divName); ?></td>
                                                        <td class="text-center" style="color:#8B5CF6;"><?php echo $counts['purple']; ?></td>
                                                        <td class="text-center" style="color:#FCD34D;"><?php echo $counts['yellow']; ?></td>
                                                        <td class="text-center" style="color:#3B82F6;"><?php echo $counts['blue']; ?></td>
                                                        <td class="text-center" style="color:#10B981;"><?php echo $counts['green']; ?></td>
                                                    </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- Main table -->
                    <div class="table-responsive">
                        <table class="table table-striped table-hover align-middle mb-0 shadow" id="personalityTable" style="background:#fff; border-radius:18px; overflow:hidden;">
                            <caption class="text-center pb-2 pt-3 text-secondary" style="caption-side:top; font-size:1.08rem; letter-spacing:0.5px;">รายชื่อพนักงานและสีบุคลิกภาพ</caption>
                            <thead class="table-primary text-center align-middle sticky-top" style="font-size:1.08rem; z-index:2;">
                                <tr style="border-top-left-radius:18px; border-top-right-radius:18px;">
                                    <th style="min-width:110px;">รหัสพนักงาน</th>
                                    <th style="min-width:180px;">ชื่อ-นามสกุล</th>
                                    <th style="min-width:120px;">ฝ่าย</th>
                                    <th style="min-width:170px;">สีบุคลิกภาพ</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($results as $row): ?>
                                <tr style="transition:background 0.2s;">
                                    <td class="text-center fw-medium align-middle"><?php echo htmlspecialchars($row['employee_id']); ?></td>
                                    <td class="align-middle"><?php echo htmlspecialchars(($row['prefix_th'] ?? '') . ' ' . ($row['first_name_th'] ?? '') . ' ' . ($row['last_name_th'] ?? '')); ?></td>
                                    <td class="align-middle"><?php echo htmlspecialchars($row['DeptName'] ?? '-'); ?></td>
                                    <td class="align-middle">
                                        <?php if (!empty($row['dominant_color'])): ?>
                                            <span class="color-dot me-1" style="background:<?php echo $colorMap[$row['dominant_color']] ?? '#ccc'; ?>;"></span>
                                            <span style="color:<?php echo $colorMap[$row['dominant_color']] ?? '#333'; ?>;font-weight:600;">
                                                <?php echo $colorName[$row['dominant_color']] ?? $row['dominant_color']; ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <script>
                    function filterTable() {
                        var input = document.getElementById('tableSearch');
                        var filter = input.value.toLowerCase();
                        var table = document.getElementById('personalityTable');
                        var trs = table.getElementsByTagName('tr');
                        for (var i = 1; i < trs.length; i++) {
                            var tds = trs[i].getElementsByTagName('td');
                            if (tds.length > 1) {
                                var code = tds[0].textContent || tds[0].innerText;
                                var name = tds[1].textContent || tds[1].innerText;
                                if (code.toLowerCase().indexOf(filter) > -1 || name.toLowerCase().indexOf(filter) > -1) {
                                    trs[i].style.display = '';
                                } else {
                                    trs[i].style.display = 'none';
                                }
                            }
                        }
                    }
                    </script>
                </div>
            </div>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>