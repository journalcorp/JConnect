<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ทดสอบการบันทึกกิจกรรม</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Prompt', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 2rem 0;
        }
        .test-container {
            background: white;
            border-radius: 15px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.1);
            padding: 2rem;
            max-width: 800px;
            margin: 0 auto;
        }
        .test-header {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
            padding: 1.5rem;
            border-radius: 10px;
            margin-bottom: 2rem;
        }
        .form-section {
            background: #f8f9fa;
            padding: 1.5rem;
            border-radius: 10px;
            margin-bottom: 1rem;
        }
        .btn-test {
            background: linear-gradient(135deg, #007bff 0%, #6610f2 100%);
            border: none;
            color: white;
            padding: 12px 30px;
            border-radius: 8px;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        .btn-test:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0,123,255,0.3);
            color: white;
        }
        .test-info {
            background: #e3f2fd;
            border: 1px solid #2196f3;
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 1rem;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="test-container">
            <div class="test-header text-center">
                <h2><i class="bi bi-flask"></i> ทดสอบการบันทึกข้อมูลกิจกรรม</h2>
                <p class="mb-0">ทดสอบฟีเจอร์การแนบไฟล์และการบันทึกข้อมูลในฐานข้อมูล</p>
            </div>

            <div class="test-info">
                <h5><i class="bi bi-info-circle"></i> ข้อมูลการทดสอบ</h5>
                <p><strong>วันที่ทดสอบ:</strong> <?php echo date('Y-m-d H:i:s'); ?></p>
                <p><strong>เซิร์ฟเวอร์:</strong> localhost</p>
                <p><strong>ฐานข้อมูล:</strong> SQL Server</p>
            </div>

            <div class="form-section">
                <h5>🧪 ทดสอบที่ 1: การบันทึกกิจกรรมแบบไม่มีไฟล์แนบ</h5>
                <form action="add_activity.php" method="POST" target="_blank">
                    <div class="row">
                        <div class="col-md-6">
                            <label class="form-label">ชื่อกิจกรรม</label>
                            <input type="text" class="form-control" name="activity_name" 
                                   value="ทดสอบกิจกรรม - <?php echo date('H:i:s'); ?>" readonly>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">วันที่เริ่ม</label>
                            <input type="date" class="form-control" name="start_date" 
                                   value="<?php echo date('Y-m-d'); ?>" readonly>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">วันที่สิ้นสุด</label>
                            <input type="date" class="form-control" name="end_date" 
                                   value="<?php echo date('Y-m-d', strtotime('+7 days')); ?>" readonly>
                        </div>
                    </div>
                    <div class="row mt-3">
                        <div class="col-md-6">
                            <label class="form-label">คะแนน</label>
                            <input type="number" class="form-control" name="score" value="85" readonly>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">การแนบไฟล์</label>
                            <div class="form-control bg-light">ไม่ต้องการ</div>
                        </div>
                    </div>
                    <div class="mt-3">
                        <label class="form-label">รายละเอียด</label>
                        <textarea class="form-control" name="description" rows="2" readonly>ทดสอบการบันทึกกิจกรรมแบบไม่มีไฟล์แนบ - <?php echo date('Y-m-d H:i:s'); ?></textarea>
                    </div>
                    <button type="submit" name="add_activity" class="btn btn-test mt-3">
                        <i class="bi bi-play-fill"></i> ทดสอบบันทึก (ไม่มีไฟล์แนบ)
                    </button>
                </form>
            </div>

            <div class="form-section">
                <h5>🧪 ทดสอบที่ 2: การบันทึกกิจกรรมแบบมีไฟล์แนบ</h5>
                <form action="add_activity.php" method="POST" target="_blank">
                    <div class="row">
                        <div class="col-md-6">
                            <label class="form-label">ชื่อกิจกรรม</label>
                            <input type="text" class="form-control" name="activity_name" 
                                   value="ทดสอบกิจกรรม (มีไฟล์แนบ) - <?php echo date('H:i:s'); ?>" readonly>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">วันที่เริ่ม</label>
                            <input type="date" class="form-control" name="start_date" 
                                   value="<?php echo date('Y-m-d'); ?>" readonly>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">วันที่สิ้นสุด</label>
                            <input type="date" class="form-control" name="end_date" 
                                   value="<?php echo date('Y-m-d', strtotime('+14 days')); ?>" readonly>
                        </div>
                    </div>
                    <div class="row mt-3">
                        <div class="col-md-6">
                            <label class="form-label">คะแนน</label>
                            <input type="number" class="form-control" name="score" value="95" readonly>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">การแนบไฟล์</label>
                            <div class="form-control bg-success text-white">✓ ต้องการ</div>
                        </div>
                    </div>
                    <div class="mt-3">
                        <label class="form-label">รายละเอียด</label>
                        <textarea class="form-control" name="description" rows="2" readonly>ทดสอบการบันทึกกิจกรรมแบบมีไฟล์แนบ พร้อมการตั้งค่าต่าง ๆ - <?php echo date('Y-m-d H:i:s'); ?></textarea>
                    </div>
                    
                    <!-- Hidden attachment settings -->
                    <input type="hidden" name="require_attachment" value="1">
                    <input type="hidden" name="attachment_description" value="กรุณาแนบไฟล์เอกสารประกอบการดำเนินงาน เช่น แผนการทำงาน, รายงานความคืบหน้า, หรือเอกสารสนับสนุนอื่น ๆ">
                    <input type="hidden" name="allow_multiple_files" value="1">
                    <input type="hidden" name="is_file_required" value="1">
                    
                    <div class="mt-3 p-3 bg-info bg-opacity-10 border border-info rounded">
                        <h6 class="text-info"><i class="bi bi-gear"></i> การตั้งค่าไฟล์แนบ:</h6>
                        <ul class="mb-0 small">
                            <li>✓ ต้องการให้แนบไฟล์</li>
                            <li>✓ อนุญาตหลายไฟล์</li>
                            <li>✓ การแนบไฟล์เป็นข้อบังคับ</li>
                            <li>📝 คำอธิบาย: "กรุณาแนบไฟล์เอกสารประกอบการดำเนินงาน..."</li>
                        </ul>
                    </div>
                    
                    <button type="submit" name="add_activity" class="btn btn-test mt-3">
                        <i class="bi bi-play-fill"></i> ทดสอบบันทึก (มีไฟล์แนบ)
                    </button>
                </form>
            </div>

            <div class="form-section">
                <h5>🔍 ดูผลการทดสอบ</h5>
                <div class="d-flex gap-2 flex-wrap">
                    <a href="activity_management.php" class="btn btn-outline-primary" target="_blank">
                        <i class="bi bi-list-ul"></i> ดูรายการกิจกรรม
                    </a>
                    <a href="test_activity_save.php" class="btn btn-outline-success" target="_blank">
                        <i class="bi bi-server"></i> ทดสอบฐานข้อมูล
                    </a>
                    <a href="add_activity.php" class="btn btn-outline-warning" target="_blank">
                        <i class="bi bi-plus-circle"></i> ฟอร์มเพิ่มกิจกรรม
                    </a>
                </div>
            </div>

            <div class="alert alert-info">
                <h6><i class="bi bi-lightbulb"></i> วิธีการทดสอบ:</h6>
                <ol class="mb-0">
                    <li>คลิกปุ่ม "ทดสอบบันทึก" ในแต่ละส่วน</li>
                    <li>ระบบจะเปิดหน้าต่างใหม่แสดงผลการบันทึก</li>
                    <li>ตรวจสอบใน "ดูรายการกิจกรรม" ว่าข้อมูลถูกบันทึกหรือไม่</li>
                    <li>สังเกตการแสดง badge ไฟล์แนบในรายการ</li>
                    <li>คลิกปุ่ม ℹ️ เพื่อดูรายละเอียดการตั้งค่าไฟล์แนบ</li>
                </ol>
            </div>
        </div>
    </div>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
</body>
</html>
