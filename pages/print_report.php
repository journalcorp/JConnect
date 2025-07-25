<?php
// เปิดการแสดงข้อผิดพลาด
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// รวมไฟล์ config
include_once '../config.php';

// เชื่อมต่อฐานข้อมูล
$conn = dbConnect();
if (!$conn) {
    die("Connection failed: " . print_r(sqlsrv_errors(), true));
}

// ดึงข้อมูลจาก form_score_details พร้อมข้อมูลผู้ประเมินและผู้ถูกประเมิน
$sql = "
    SELECT 
        fsd.TransactionID,
        fsd.category,
        fsd.score,
        fsd.reason,
        fs.emp_as,
        fs.emp_pe,
        fs.FormID,
        e1.first_name_th AS as_first_name,
        e1.last_name_th AS as_last_name,
        e1.position_th AS as_position,
        e2.first_name_th AS pe_first_name,
        e2.last_name_th AS pe_last_name,
        e2.position_th AS pe_position,
        d1.DeptName AS as_dept,
        d2.DeptName AS pe_dept
    FROM form_score_details fsd
    INNER JOIN form_score fs ON fsd.TransactionID = fs.TransactionID
    INNER JOIN employees e1 ON fs.emp_as = e1.employee_id
    INNER JOIN employees e2 ON fs.emp_pe = e2.employee_id
    LEFT JOIN dept d1 ON e1.division = d1.DeptID
    LEFT JOIN dept d2 ON e2.division = d2.DeptID
    WHERE fs.status_form = 'enable'
    ORDER BY fs.emp_pe, fs.emp_as, fsd.category
";

$result = sqlsrv_query($conn, $sql);
if (!$result) {
    die("Query failed: " . print_r(sqlsrv_errors(), true));
}

// จัดกลุ่มข้อมูลตามผู้ถูกประเมิน
$data = [];
$category_names = [
    'j_score' => 'Joyfulness (ความสุข)',
    'o_score' => 'Openness (การเปิดใจ)',
    'u_score' => 'Us (การทำงานเป็นทีม)',
    'r_score' => 'Respect (การเคารพ)',
    'n_score' => 'Never Give Up (ความมุ่งมั่น)',
    'a_score' => 'Accountability (ความรับผิดชอบ)',
    'l_score' => 'Lifelong Learning (การเรียนรู้ตลอดชีวิต)'
];

while ($row = sqlsrv_fetch_array($result, SQLSRV_FETCH_ASSOC)) {
    $emp_pe = $row['emp_pe'];
    
    if (!isset($data[$emp_pe])) {
        $data[$emp_pe] = [
            'info' => [
                'name' => $row['pe_first_name'] . ' ' . $row['pe_last_name'],
                'position' => $row['pe_position'],
                'dept' => $row['pe_dept'],
                'emp_id' => $emp_pe
            ],
            'evaluators' => [],
            'scores_summary' => []
        ];
    }
    
    $emp_as = $row['emp_as'];
    if (!isset($data[$emp_pe]['evaluators'][$emp_as])) {
        $data[$emp_pe]['evaluators'][$emp_as] = [
            'name' => $row['as_first_name'] . ' ' . $row['as_last_name'],
            'position' => $row['as_position'],
            'dept' => $row['as_dept'],
            'scores' => []
        ];
    }
    
    $data[$emp_pe]['evaluators'][$emp_as]['scores'][$row['category']] = [
        'score' => $row['score'],
        'reason' => $row['reason']
    ];
    
    // สะสมคะแนนสำหรับหาค่าเฉลี่ย
    if (!isset($data[$emp_pe]['scores_summary'][$row['category']])) {
        $data[$emp_pe]['scores_summary'][$row['category']] = [];
    }
    $data[$emp_pe]['scores_summary'][$row['category']][] = $row['score'];
}

// คำนวณค่าเฉลี่ยสำหรับแต่ละหมวดหมู่
foreach ($data as $emp_pe => &$emp_data) {
    foreach ($emp_data['scores_summary'] as $category => $scores) {
        $avg = array_sum($scores) / count($scores);
        $emp_data['scores_summary'][$category] = [
            'average' => round($avg, 2),
            'count' => count($scores),
            'scores' => $scores
        ];
    }
}

sqlsrv_close($conn);
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>รายงานผลการประเมิน 360 องศา</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        body {
            font-family: 'Prompt', sans-serif;
            background: #ffffff;
            color: #333;
            line-height: 1.6;
        }
        
        .report-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 2rem;
            text-align: center;
            margin-bottom: 2rem;
        }
        
        .employee-card {
            background: #f8f9fa;
            border-left: 5px solid #007bff;
            padding: 1.5rem;
            margin-bottom: 2rem;
            border-radius: 0 10px 10px 0;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .score-table {
            border-collapse: collapse;
            width: 100%;
            margin-bottom: 1.5rem;
        }
        
        .score-table th {
            background: linear-gradient(135deg, #6c757d 0%, #495057 100%);
            color: white;
            padding: 12px;
            text-align: center;
            font-weight: 600;
        }
        
        .score-table td {
            padding: 10px;
            border: 1px solid #dee2e6;
            text-align: center;
            vertical-align: middle;
        }
        
        .score-badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 50px;
            color: white;
            font-weight: bold;
            min-width: 30px;
        }
        
        .score-1 { background: #dc3545; }
        .score-2 { background: #fd7e14; }
        .score-3 { background: #ffc107; color: #000; }
        .score-4 { background: #20c997; }
        .score-5 { background: #198754; }
        
        .average-row {
            background: #e9ecef;
            font-weight: bold;
        }
        
        .category-header {
            background: linear-gradient(135deg, #17a2b8 0%, #138496 100%);
            color: white;
            padding: 10px;
            font-weight: 600;
            text-align: center;
        }
        
        .reason-text {
            max-width: 300px;
            word-wrap: break-word;
            font-size: 0.9rem;
            line-height: 1.4;
        }
        
        .summary-card {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
            padding: 1.5rem;
            border-radius: 10px;
            margin-bottom: 1.5rem;
            text-align: center;
        }
        
        .print-btn {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1000;
        }
        
        @media print {
            .print-btn {
                display: none;
            }
            .employee-card {
                page-break-inside: avoid;
                margin-bottom: 1rem;
            }
            .report-header {
                margin-bottom: 1rem;
                padding: 1rem;
            }
        }
        
        .evaluator-section {
            margin-bottom: 2rem;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            overflow: hidden;
        }
        
        .evaluator-header {
            background: #f1f3f4;
            padding: 1rem;
            border-bottom: 1px solid #dee2e6;
        }
        
        .no-data {
            text-align: center;
            padding: 3rem;
            color: #6c757d;
        }
    </style>
</head>
<body>
    <button class="btn btn-primary print-btn" onclick="window.print()">
        <i class="bi bi-printer"></i> พิมพ์รายงาน
    </button>

    <div class="container-fluid">
        <div class="report-header">
            <h1><i class="bi bi-clipboard-data"></i> รายงานผลการประเมิน 360 องศา</h1>
            <p class="mb-0">ระบบประเมินค่านิยมองค์กร JOURNAL</p>
            <small>วันที่พิมพ์: <?php echo date('d/m/Y H:i:s'); ?></small>
        </div>

        <?php if (empty($data)): ?>
            <div class="no-data">
                <i class="bi bi-exclamation-circle" style="font-size: 3rem; color: #ffc107;"></i>
                <h3>ไม่มีข้อมูลการประเมิน</h3>
                <p>ยังไม่มีการประเมินที่เสร็จสิ้นในระบบ</p>
            </div>
        <?php else: ?>
            <?php foreach ($data as $emp_pe => $emp_data): ?>
                <div class="employee-card">
                    <div class="row align-items-center mb-3">
                        <div class="col-md-8">
                            <h3 class="mb-1">
                                <i class="bi bi-person-circle text-primary"></i>
                                <?php echo htmlspecialchars($emp_data['info']['name']); ?>
                            </h3>
                            <p class="mb-0">
                                <strong>รหัสพนักงาน:</strong> <?php echo htmlspecialchars($emp_data['info']['emp_id']); ?> |
                                <strong>ตำแหน่ง:</strong> <?php echo htmlspecialchars($emp_data['info']['position'] ?: 'ไม่ระบุ'); ?> |
                                <strong>แผนก:</strong> <?php echo htmlspecialchars($emp_data['info']['dept'] ?: 'ไม่ระบุ'); ?>
                            </p>
                        </div>
                        <div class="col-md-4 text-end">
                            <div class="summary-card">
                                <h5 class="mb-1">จำนวนผู้ประเมิน</h5>
                                <h2 class="mb-0"><?php echo count($emp_data['evaluators']); ?> คน</h2>
                            </div>
                        </div>
                    </div>

                    <!-- ตารางสรุปคะแนนเฉลี่ย -->
                    <div class="evaluator-section">
                        <div class="category-header">
                            <h5 class="mb-0"><i class="bi bi-bar-chart"></i> สรุปคะแนนเฉลี่ยแต่ละหมวดหมู่</h5>
                        </div>
                        <div class="table-responsive">
                            <table class="score-table">
                                <thead>
                                    <tr>
                                        <th style="width: 40%;">หมวดหมู่</th>
                                        <th style="width: 15%;">คะแนนเฉลี่ย</th>
                                        <th style="width: 15%;">จำนวนผู้ประเมิน</th>
                                        <th style="width: 30%;">คะแนนทั้งหมด</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $total_avg = 0;
                                    $category_count = 0;
                                    foreach ($category_names as $category => $name): 
                                        if (isset($emp_data['scores_summary'][$category])):
                                            $summary = $emp_data['scores_summary'][$category];
                                            $total_avg += $summary['average'];
                                            $category_count++;
                                    ?>
                                        <tr>
                                            <td class="text-start"><strong><?php echo htmlspecialchars($name); ?></strong></td>
                                            <td>
                                                <span class="score-badge score-<?php echo round($summary['average']); ?>">
                                                    <?php echo $summary['average']; ?>
                                                </span>
                                            </td>
                                            <td><?php echo $summary['count']; ?></td>
                                            <td>
                                                <?php 
                                                foreach ($summary['scores'] as $score) {
                                                    echo "<span class='score-badge score-$score' style='margin: 2px;'>$score</span> ";
                                                }
                                                ?>
                                            </td>
                                        </tr>
                                    <?php 
                                        endif;
                                    endforeach; 
                                    
                                    // คำนวณคะแนนรวมเฉลี่ย
                                    $overall_avg = $category_count > 0 ? round($total_avg / $category_count, 2) : 0;
                                    ?>
                                    <tr class="average-row">
                                        <td class="text-start"><strong>คะแนนรวมเฉลี่ย</strong></td>
                                        <td>
                                            <span class="score-badge score-<?php echo round($overall_avg); ?>" style="font-size: 1.1rem;">
                                                <?php echo $overall_avg; ?>
                                            </span>
                                        </td>
                                        <td colspan="2">
                                            <strong>จาก <?php echo count($emp_data['evaluators']); ?> ผู้ประเมิน</strong>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- รายละเอียดการประเมินแต่ละคน -->
                    <?php foreach ($emp_data['evaluators'] as $emp_as => $evaluator): ?>
                        <div class="evaluator-section">
                            <div class="evaluator-header">
                                <h6 class="mb-0">
                                    <i class="bi bi-person-badge"></i>
                                    ผู้ประเมิน: <?php echo htmlspecialchars($evaluator['name']); ?>
                                    <small class="text-muted">
                                        (<?php echo htmlspecialchars($evaluator['position'] ?: 'ไม่ระบุตำแหน่ง'); ?> - 
                                        <?php echo htmlspecialchars($evaluator['dept'] ?: 'ไม่ระบุแผนก'); ?>)
                                    </small>
                                </h6>
                            </div>
                            <div class="table-responsive">
                                <table class="score-table">
                                    <thead>
                                        <tr>
                                            <th style="width: 30%;">หมวดหมู่</th>
                                            <th style="width: 10%;">คะแนน</th>
                                            <th style="width: 60%;">เหตุผล/ข้อเสนอแนะ</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($category_names as $category => $name): ?>
                                            <?php if (isset($evaluator['scores'][$category])): ?>
                                                <?php $score_data = $evaluator['scores'][$category]; ?>
                                                <tr>
                                                    <td class="text-start"><?php echo htmlspecialchars($name); ?></td>
                                                    <td>
                                                        <span class="score-badge score-<?php echo $score_data['score']; ?>">
                                                            <?php echo $score_data['score']; ?>
                                                        </span>
                                                    </td>
                                                    <td class="text-start reason-text">
                                                        <?php 
                                                        echo $score_data['reason'] ? 
                                                            htmlspecialchars($score_data['reason']) : 
                                                            '<em class="text-muted">ไม่มีความคิดเห็น</em>';
                                                        ?>
                                                    </td>
                                                </tr>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>

            <!-- สรุปภาพรวม -->
            <div class="summary-card">
                <h4><i class="bi bi-graph-up"></i> สรุปภาพรวมการประเมิน</h4>
                <div class="row mt-3">
                    <div class="col-md-4">
                        <h5>จำนวนผู้ถูกประเมิน</h5>
                        <h2><?php echo count($data); ?> คน</h2>
                    </div>
                    <div class="col-md-4">
                        <h5>จำนวนการประเมินทั้งหมด</h5>
                        <h2>
                            <?php 
                            $total_evaluations = 0;
                            foreach ($data as $emp_data) {
                                $total_evaluations += count($emp_data['evaluators']);
                            }
                            echo $total_evaluations;
                            ?> ครั้ง
                        </h2>
                    </div>
                    <div class="col-md-4">
                        <h5>ค่าเฉลี่ยโดยรวม</h5>
                        <h2>
                            <?php
                            $grand_total = 0;
                            $grand_count = 0;
                            foreach ($data as $emp_data) {
                                foreach ($emp_data['scores_summary'] as $summary) {
                                    $grand_total += $summary['average'];
                                    $grand_count++;
                                }
                            }
                            $grand_average = $grand_count > 0 ? round($grand_total / $grand_count, 2) : 0;
                            echo $grand_average;
                            ?> / 5.00
                        </h2>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
