<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// รวมไฟล์ header และ menu
include_once '../includes/header.php';
include_once '../includes/menu.php';
include '../config.php';

// ตรวจสอบสถานะของ session ก่อนเริ่มต้น
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ดึงข้อมูลพนักงานทั้งหมด
$conn = dbConnect();
$sqlEmployees = "SELECT employee_id, CONCAT(first_name_th, ' ', last_name_th) AS FullName FROM employees";
$resultEmployees = sqlsrv_query($conn, $sqlEmployees);

if (!$resultEmployees) {
    die("Query failed: " . print_r(sqlsrv_errors(), true));
}

// ดึงข้อมูลฟอร์มทั้งหมด
$sqlForms = "SELECT FormID, FormName FROM form";
$resultForms = sqlsrv_query($conn, $sqlForms);

if (!$resultForms) {
    // หากตาราง form ไม่มี ให้ใช้ข้อมูลจำลอง
    $forms_data = [
        ['FormID' => 'F001', 'FormName' => 'แบบฟอร์มประเมินผลงาน'],
        ['FormID' => 'F005', 'FormName' => 'แบบฟอร์ม 360 องศา']
    ];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>เลือกผู้ทำแบบประเมินและผู้ถูกประเมิน</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="../assets/css/styles.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- Select2 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />

    <!-- เพิ่ม jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

    <!-- Select2 JS -->
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <!-- Custom JS -->
    <script src="../assets/js/scripts.js"></script>
</head>
<style>
        body {
            font-family: 'Prompt', sans-serif;
            background-color: #f8f9fa; /* สีพื้นหลังเทาอ่อน */
            color: #212529; /* สีข้อความ */
        }
        .navbar {
            background-color: #ffffff; /* สีขาว */
            border-bottom: 1px solid #dee2e6; /* เส้นขอบด้านล่าง */
        }
        .navbar .navbar-brand {
            font-weight: 600;
            color: #212529;
        }
        .navbar .navbar-brand:hover {
            color: #495057;
        }
        .employee-info {
            background-color: #ffffff;
            border: 1px solid #dee2e6;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }
        h1 {
            font-size: 1.75rem;
            font-weight: 600;
        }
    </style>

<body>
<div class="container mt-5">
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h3 class="mb-0">เลือกแบบฟอร์ม</h3>
            </div>
            <div class="card-body">
                <form id="evaluationForm">
                    <!-- เลือกฟอร์มที่จะประเมิน -->
                    <div class="mb-4">
                        <label for="form" class="form-label">เลือกฟอร์ม</label>
                        <select class="form-select" id="form" name="form" required onchange="toggleSections()">
                            <option value="" disabled selected>-- เลือกฟอร์ม --</option>
                            <?php 
                            if ($resultForms) {
                                while ($row = sqlsrv_fetch_array($resultForms, SQLSRV_FETCH_ASSOC)) {
                                    echo '<option value="' . $row['FormID'] . '">' . htmlspecialchars($row['FormName']) . '</option>';
                                }
                            } else {
                                // ใช้ข้อมูลจำลองหากตารางไม่มี
                                foreach ($forms_data as $form) {
                                    echo '<option value="' . $form['FormID'] . '">' . htmlspecialchars($form['FormName']) . '</option>';
                                }
                            }
                            ?>
                        </select>
                    </div>

                    <!-- เลือกผู้ทำแบบประเมิน -->
                     <!-- รอบการประเมิน, วันที่เริ่มต้น-สิ้นสุด, และเป้าหมายการประเมิน -->
                    <div id="f005Details" style="display: none;">
                        <div class="mb-4">
                            <label for="evaluation_period" class="form-label">รอบการประเมิน</label>
                            <select class="form-select" id="evaluation_period" name="evaluation_period" required>
                                <option value="" disabled selected>-- เลือกรอบการประเมิน --</option>
                                <option value="Q1 <?php echo date('Y'); ?>">Q1 <?php echo date('Y'); ?></option>
                                <option value="Q2 <?php echo date('Y'); ?>">Q2 <?php echo date('Y'); ?></option>
                                <option value="Q3 <?php echo date('Y'); ?>">Q3 <?php echo date('Y'); ?></option>
                                <option value="Q4 <?php echo date('Y'); ?>">Q4 <?php echo date('Y'); ?></option>
                            </select>
                        </div>

                        <div class="mb-4">
                            <label for="start_date" class="form-label">วันที่เริ่มต้นการประเมิน</label>
                            <input type="date" class="form-control" id="start_date" name="start_date" required lang="th">
                        </div>

                        <div class="mb-4">
                            <label for="end_date" class="form-label">วันที่สิ้นสุดการประเมิน</label>
                            <input type="date" class="form-control" id="end_date" name="end_date" required lang="th">
                        </div>

                    <div class="mb-4" id="empAsSection" style="display: none;">
                        <label for="emp_as" class="form-label">เลือกผู้ทำแบบประเมิน</label>
                        <select class="form-select" id="emp_as" name="emp_as" required>
                            <option value="" disabled selected>-- เลือกผู้ทำแบบประเมิน --</option>
                            <?php 
                            // สร้าง array เก็บข้อมูลพนักงานเพื่อใช้ซ้ำ
                            $employees = [];
                            while ($row = sqlsrv_fetch_array($resultEmployees, SQLSRV_FETCH_ASSOC)) {
                                $employees[] = $row;
                            }
                            
                            foreach ($employees as $row): ?>
                                <option value="<?php echo $row['employee_id']; ?>">
                                    <?php echo htmlspecialchars($row['employee_id'] . ' - ' . $row['FullName']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    </div>

                    <!-- เลือกผู้ถูกประเมิน -->
                    <div class="mb-4" id="evaluateeSection" style="display: none;">
                        <label for="evaluatee" class="form-label">เลือกผู้ถูกประเมิน</label>
                        <select class="form-select" id="evaluatee" name="evaluatee">
                            <option value="" disabled selected>-- เลือกผู้ถูกประเมิน --</option>
                            <?php
                            // ใช้ array ที่เก็บไว้แล้วสำหรับการเลือกผู้ถูกประเมิน
                            foreach ($employees as $row): ?>
                                <option value="<?php echo $row['employee_id']; ?>"><?php echo htmlspecialchars($row['FullName']); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <button type="button" class="btn btn-primary mt-2" onclick="addEvaluatee()">เพิ่มผู้ถูกประเมิน</button>
                    </div>

                    <!-- รายชื่อผู้ถูกประเมิน -->
                    <div class="mb-4" id="evaluateeListSection" style="display: none;">
                        <label class="form-label">รายชื่อผู้ถูกประเมิน (สูงสุด 10 คน)</label>
                        <ul id="evaluateeList" class="list-group">
                            <!-- รายชื่อผู้ถูกประเมินจะแสดงที่นี่ -->
                        </ul>
                    </div>

                    <!-- ปุ่มบันทึกและยกเลิก -->
                    <div class="d-flex justify-content-between">
                        <button type="submit" class="btn btn-success">บันทึกข้อมูล</button>
                        <button type="button" class="btn btn-secondary" onclick="cancelEvaluation()">ยกเลิก</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        const evaluateeList = document.getElementById('evaluateeList');
        const evaluateeSelect = document.getElementById('evaluatee');

        function addEvaluatee() {
            if (evaluateeList.children.length >= 10) {
                alert('ไม่สามารถเพิ่มผู้ถูกประเมินได้เกิน 10 คน');
                return;
            }

            const selectedOption = evaluateeSelect.options[evaluateeSelect.selectedIndex];
            if (!selectedOption || selectedOption.value === "") {
                alert('กรุณาเลือกผู้ถูกประเมิน');
                return;
            }

            // ตรวจสอบว่าผู้ถูกประเมินนี้ถูกเพิ่มไปแล้วหรือยัง
            const existingItems = Array.from(evaluateeList.children).map(item => item.dataset.id);
            if (existingItems.includes(selectedOption.value)) {
                alert('ผู้ถูกประเมินนี้ถูกเพิ่มไปแล้ว');
                return;
            }

            // เพิ่มผู้ถูกประเมินในรายการ
            const li = document.createElement('li');
            li.className = 'list-group-item d-flex justify-content-between align-items-center';
            li.dataset.id = selectedOption.value;
            li.textContent = selectedOption.text;

            const removeButton = document.createElement('button');
            removeButton.className = 'btn btn-danger btn-sm';
            removeButton.textContent = 'ลบ';
            removeButton.onclick = () => li.remove();

            li.appendChild(removeButton);
            evaluateeList.appendChild(li);
        }

        document.getElementById('evaluationForm').addEventListener('submit', function (e) {
            e.preventDefault();

            const emp_as = document.getElementById('emp_as').value;
            const form = document.getElementById('form').value;
            const emp_pe = Array.from(document.querySelectorAll('#evaluateeList li')).map(item => item.dataset.id); // ดึงค่า dataset.id จาก <li>
            const evaluation_period = document.getElementById('evaluation_period').value;
            const start_date = document.getElementById('start_date').value;
            const end_date = document.getElementById('end_date').value;

            if (!emp_as || !form || emp_pe.length === 0 || !evaluation_period || !start_date || !end_date) {
                alert(
            `กรุณากรอกข้อมูลให้ครบถ้วน:\n` +
            `emp_as: ${emp_as || 'ไม่ได้เลือก'}\n` +
            `form: ${form || 'ไม่ได้เลือก'}\n` +
            `emp_pe: ${emp_pe.length > 0 ? emp_pe.join(', ') : 'ไม่มีผู้ถูกประเมิน'}\n` +
            `evaluation_period: ${evaluation_period || 'ไม่ได้เลือก'}\n` +
            `start_date: ${start_date || 'ไม่ได้เลือก'}\n` +
            `end_date: ${end_date || 'ไม่ได้เลือก'}`
        );
                return;
            }

            fetch('save_form_score.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    form: form,
                    emp_as: emp_as,
                    emp_pe: emp_pe, // ส่ง emp_pe ที่ดึงจาก #evaluateeList
                    evaluation_period: evaluation_period,
                    start_date: start_date,
                    end_date: end_date
                })
            })
            .then(response => {
                console.log('Response:', response); // ตรวจสอบการตอบกลับ
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                console.log('Data:', data); // ตรวจสอบข้อมูล JSON ที่ได้รับ
                if (data.success) {
                    alert(data.message);
                    window.location.href = 'performance_review.php';
                } else {
                    alert(data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('เกิดข้อผิดพลาดในการบันทึกข้อมูล: ' + error.message);
            });
        });

        function cancelEvaluation() {
            if (confirm('คุณต้องการยกเลิกการทำรายการนี้หรือไม่?')) {
                window.location.href = 'performance_review.php'; // เปลี่ยนเส้นทางไปยังหน้า performance_review
            }
        }

        function toggleSections() {
            const formSelect = document.getElementById('form');
            const empAsSection = document.getElementById('empAsSection');
            const evaluateeSection = document.getElementById('evaluateeSection');
            const evaluateeListSection = document.getElementById('evaluateeListSection');
            const f005Details = document.getElementById('f005Details');

            // แสดงหรือซ่อนส่วนของผู้ทำแบบประเมินและรายละเอียดเพิ่มเติมตาม FormID
            if (formSelect.value === 'F005') {
                empAsSection.style.display = 'block';
                evaluateeSection.style.display = 'block';
                evaluateeListSection.style.display = 'block';
                f005Details.style.display = 'block'; // แสดงส่วนรายละเอียดเพิ่มเติม
            } else {
                empAsSection.style.display = 'none';
                evaluateeSection.style.display = 'none';
                evaluateeListSection.style.display = 'none';
                f005Details.style.display = 'none'; // ซ่อนส่วนรายละเอียดเพิ่มเติม
            }
        }
    </script>

    <script>
        $(document).ready(function() {
            // เปิดใช้งาน Select2 สำหรับ #emp_as
            $('#emp_as').select2({
                placeholder: "-- เลือกผู้ทำแบบประเมิน --",
                allowClear: true,
                width: '100%' // ปรับให้ Select2 ขยายเต็มความกว้าง
            });

            // เปิดใช้งาน Select2 สำหรับ #evaluatee
            $('#evaluatee').select2({
                placeholder: "-- เลือกผู้ถูกประเมิน --",
                allowClear: true,
                width: '100%' // ปรับให้ Select2 ขยายเต็มความกว้าง
            });
        });
    </script>

    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php
// ปิดการเชื่อมต่อฐานข้อมูล
if ($resultEmployees) sqlsrv_free_stmt($resultEmployees);
if ($resultForms) sqlsrv_free_stmt($resultForms);
if ($conn) sqlsrv_close($conn);
?>
