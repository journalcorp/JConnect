<?php
include '../config.php';

// ตั้งค่า session และ header หลังจาก include config
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}


// ตรวจสอบการล็อกอิน
if (!isset($_SESSION['employee_id'])) {
    // ส่ง path กลับไป login (index.php) ด้วย redirect param
    $redirect = urlencode('pages/color_personality.php');
    header("Location: http://localhost/webportal/?redirect=$redirect");
    exit;
}

$employee_id = $_SESSION['employee_id'];

// เชื่อมต่อฐานข้อมูล พร้อมตรวจสอบ error และชนิด connection (รองรับ SQLSRV, PDO, MySQLi)
$conn = null;
try {
    if (!function_exists('dbConnect')) {
        throw new Exception('ไม่พบฟังก์ชัน dbConnect()');
    }
    $conn = dbConnect();
    if (!$conn) {
        throw new Exception('ไม่สามารถเชื่อมต่อฐานข้อมูลได้');
    }
    $is_sqlsrv = is_resource($conn);
    $is_pdo = (is_object($conn) && $conn instanceof PDO);
    $is_mysqli = (is_object($conn) && get_class($conn) === 'mysqli');
    if (!$is_sqlsrv && !$is_pdo && !$is_mysqli) {
        echo '<div style="color:red;text-align:center;margin-top:30px;">'
            . 'ไม่รู้จักชนิดการเชื่อมต่อฐานข้อมูล (connection type not supported)</div>';
        error_log('Color Test: Unknown DB connection type');
        exit;
    }
    if (!$is_sqlsrv) {
        echo '<div style="color:red;text-align:center;margin-top:30px;">'
            . 'ขออภัย ระบบนี้รองรับเฉพาะ SQL Server (sqlsrv) สำหรับแบบทดสอบนี้เท่านั้น</div>';
        error_log('Color Test: Only SQLSRV supported for this test page');
        exit;
    }
} catch (Exception $e) {
    echo '<div style="color:red;text-align:center;margin-top:30px;">'
        . 'เกิดข้อผิดพลาด: ' . htmlspecialchars($e->getMessage()) . '</div>';
    error_log('Color Test DB Error: ' . $e->getMessage());
    exit;
}

// ดึงข้อมูลรอบการประเมินปัจจุบัน
$currentYear = date('Y');
$currentMonth = (int)date('n');
$currentRound = $currentYear . '-' . ($currentMonth <= 6 ? '1' : '2');

// ตรวจสอบว่าเคยทำแบบประเมินในรอบนี้แล้วหรือไม่
$sqlCheck = "SELECT * FROM color_personality_results 
             WHERE employee_id = ? AND evaluation_round = ?";

try {
    $stmtCheck = sqlsrv_query($conn, $sqlCheck, array($employee_id, $currentRound));
    $existingResult = null;
    if ($stmtCheck === false) {
        $errors = function_exists('sqlsrv_errors') ? sqlsrv_errors() : [];
        $errMsg = 'Query error: ' . print_r($errors, true);
        echo '<div style="color:red;text-align:center;margin-top:30px;">'.htmlspecialchars($errMsg).'</div>';
        error_log('Color Test Query Error: ' . $errMsg);
        exit;
    }
    if ($stmtCheck) {
        $existingResult = sqlsrv_fetch_array($stmtCheck, SQLSRV_FETCH_ASSOC);
        sqlsrv_free_stmt($stmtCheck);
    }
    if ($existingResult) {
        // แสดงผลลัพธ์เดิม
        $scores = json_decode($existingResult['scores'], true);
        
        // หาสีที่ได้คะแนนสูงสุดและสีรอง
        $maxScore = max($scores);
        $dominantColors = array_filter($scores, function($score) use ($maxScore) {
            return $score == $maxScore;
        });
        
        // หากมีหลายสีที่คะแนนเท่ากัน หรือสีรองใกล้เคียง (ห่างกัน 1-2 คะแนน)
        $secondHighestScore = 0;
        foreach ($scores as $color => $score) {
            if ($score < $maxScore && $score > $secondHighestScore) {
                $secondHighestScore = $score;
            }
        }
        
        $isMultiColor = count($dominantColors) > 1 || ($maxScore - $secondHighestScore <= 2 && $secondHighestScore > 0);
        
        // คำอธิบายสีบุคลิกภาพ (Gallup Strength Coach)
        $colorDescriptions = [
            'purple' => [
                'name' => 'Purple (STRATEGIC THINKING)',
                'description' => "\xE2\x80\xA2 ฝึกการสื่อสารแนวคิดให้เข้าใจง่าย\n\xE2\x80\xA2 หากคะแนนแข็งแรงใน EXECUTING ช่วยวางแผนให้เป็นจริง\n\xE2\x80\xA2 ใช้เวลาเดี่ยวและวิเคราะห์อย่างมีระบบ"
            ],
            'yellow' => [
                'name' => 'Yellow (INFLUENCING)',
                'description' => "\xE2\x80\xA2 ฝึกการฟังและ empathy เพื่อเพิ่มประสิทธิภาพการชักจูง\n\xE2\x80\xA2 หากคะแนนแข็งแรงใน EXECUTING มาเป็นทีม\n\xE2\x80\xA2 ใช้ energy ในการสร้างแรงบันดาลใจให้กับคนอื่น"
            ],
            'blue' => [
                'name' => 'Blue (RELATIONSHIP BUILDING)',
                'description' => "\xE2\x80\xA2 พัฒนาทักษะการให้ feedback ที่สร้างสรรค์\n\xE2\x80\xA2 ช่วยทีมเข้าใจคนในทีมและรับมือกับการจัดการทีม\n\xE2\x80\xA2 ระวังการให้ความช่วยเหลือมากเกินไปจนหมดพลัง"
            ],
            'green' => [
                'name' => 'Green (EXECUTING)',
                'description' => "\xE2\x80\xA2 หาพันธมิตรที่แข็งแกร่งใน INFLUENCING และ RELATIONSHIP BUILDING\n\xE2\x80\xA2 ใช้ความเป็นระบบเป็นจุดแข็งในการทำงาน\n\xE2\x80\xA2 สร้าง processes และ systems ที่คนอื่นสามารถใช้ได้"
            ]
        ];
        
        if ($isMultiColor) {
            // แสดงผลแบบ Multi-color
            $primaryColor = array_keys($dominantColors)[0];
            $secondaryColors = [];
            
            if (count($dominantColors) > 1) {
                // กรณีคะแนนเท่ากัน
                $secondaryColors = array_slice(array_keys($dominantColors), 1);
            } else {
                // กรณีสีรองใกล้เคียง
                foreach ($scores as $color => $score) {
                    if ($color != $primaryColor && $score == $secondHighestScore) {
                        $secondaryColors[] = $color;
                    }
                }
            }
            
            $dominantColor = $primaryColor;
            $isMultiColorResult = true;
            
        } else {
            // แสดงผลแบบปกติ (สีเดียว)
            $dominantColor = array_keys($dominantColors)[0];
            $isMultiColorResult = false;
        }
        ?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ผลการประเมินบุคลิกภาพสี</title>
    <!-- ฟอนต์ Prompt จาก Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Prompt', 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; margin: 0; padding: 24px; background: #fafbfc; }
        .container { max-width: 600px; margin: 0 auto; background: #fff; padding: 32px 20px 28px 20px; border-radius: 18px; box-shadow: none; border: 1.5px solid #ececec; }
        .header { text-align: center; margin-bottom: 32px; }
        .header h1 { font-weight: 700; letter-spacing: 0.5px; color: #5B21B6; margin-bottom: 4px; font-size: 2.1rem; }
        .header p { color: #888; font-size: 1.08rem; margin-bottom: 0; }
        .result-card { background: #f6f6fa; color: #222; padding: 28px 18px 20px 18px; border-radius: 16px; margin-bottom: 32px; text-align: center; border: 1.5px solid #ececec; box-shadow: none; display: flex; flex-direction: column; align-items: center; }
        .color-purple { border-left: 6px solid #8B5CF6; }
        .color-yellow { border-left: 6px solid #FCD34D; }
        .color-blue { border-left: 6px solid #3B82F6; }
        .color-green { border-left: 6px solid #10B981; }
        .result-card h2 { font-size: 1.18rem; color: #5B21B6; margin-bottom: 6px; font-weight: 600; letter-spacing: 0.2px; }
        .result-card h1 { font-size: 1.45rem; margin-bottom: 10px; color: #333; font-weight: 700; letter-spacing: 0.5px; text-shadow: none; }
        .result-card .desc-box { background: #fff; border-radius: 10px; padding: 14px 16px; margin: 0 auto; max-width: 420px; font-size: 1.04rem; text-align: left; color: #444; border: 1px solid #ececec; box-shadow: none; white-space: pre-line; margin-bottom: 0; }
        .section-title { font-size: 1.15rem; color: #5B21B6; font-weight: 600; margin-bottom: 10px; margin-top: 0; letter-spacing: 0.1px; text-align: left; }
        .score-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 14px 18px; margin: 0 0 18px 0; }
        .score-item { background: #f8f9fa; padding: 12px 6px; border-radius: 10px; text-align: center; border: 1.5px solid #ececec; min-height: 80px; display: flex; flex-direction: column; justify-content: center; align-items: center; }
        .score-item h4 { margin-bottom: 4px; font-size: 1.01rem; font-weight: 600; letter-spacing: 0.2px; }
        .score-purple h4 { color: #8B5CF6; }
        .score-yellow h4 { color: #F59E0B; }
        .score-blue h4 { color: #3B82F6; }
        .score-green h4 { color: #10B981; }
        .score-item .score-val { font-size: 1.25rem; font-weight: 700; color: #222; letter-spacing: 0.5px; }
        .score-item .score-label { font-size: 0.93rem; font-weight: 400; color: #888; }
        .summary-meta { display: flex; flex-direction: row; justify-content: space-between; align-items: center; margin-top: 18px; margin-bottom: 0; font-size: 1.01rem; color: #666; }
        .summary-meta strong { color: #444; font-weight: 600; }
        .btn { background: #5B21B6; color: #fff; padding: 12px 32px; border: none; border-radius: 24px; cursor: pointer; text-decoration: none; display: inline-block; margin: 10px 5px; font-size: 1.08rem; font-family: 'Prompt', sans-serif; font-weight: 600; transition: background 0.2s; box-shadow: none; }
        .btn:hover { background: #7c3aed; }
        @media (max-width: 600px) {
            .container { padding: 12px 2vw; }
            .result-card { padding: 16px 4vw; }
            .score-grid { gap: 10px; }
        }
    </style>
</head>
<body>
        <div class="container">
            <div class="header">
                <h1>🎨 ผลการประเมินบุคลิกภาพสี</h1>
                <p>รอบการประเมิน: <b><?php echo $currentRound; ?></b></p>
            </div>
            
            <?php if ($isMultiColorResult): ?>
                <!-- กรณีมีหลายสี แสดง 2 กล่องแยกกัน -->
                <div class="result-card color-<?php echo $dominantColor; ?>">
                    <h2>บุคลิกภาพหลักของคุณคือ</h2>
                    <h1><?php echo $colorDescriptions[$dominantColor]['name']; ?></h1>
                    <div class="desc-box">
                        <?php echo nl2br(htmlspecialchars($colorDescriptions[$dominantColor]['description'])); ?>
                    </div>
                </div>
                
                <?php foreach ($secondaryColors as $secColor): ?>
                <div class="result-card color-<?php echo $secColor; ?>" style="margin-top: 20px;">
                    <h2>บุคลิกภาพเสริมของคุณคือ</h2>
                    <h1><?php echo $colorDescriptions[$secColor]['name']; ?></h1>
                    <div class="desc-box">
                        <?php echo nl2br(htmlspecialchars($colorDescriptions[$secColor]['description'])); ?>
                    </div>
                </div>
                <?php endforeach; ?>
                
            <?php else: ?>
                <!-- กรณีสีเดียว -->
                <div class="result-card color-<?php echo $dominantColor; ?>">
                    <h2>บุคลิกภาพเด่นของคุณคือ</h2>
                    <h1><?php echo $colorDescriptions[$dominantColor]['name']; ?></h1>
                    <div class="desc-box">
                        <?php echo nl2br(htmlspecialchars($colorDescriptions[$dominantColor]['description'])); ?>
                    </div>
                </div>
            <?php endif; ?>
            <div style="margin-bottom:24px;">
                <div class="section-title">คะแนนรายสี</div>
                <div class="score-grid">
                    <?php foreach ($scores as $color => $score): ?>
                        <div class="score-item score-<?php echo $color; ?>">
                            <h4><?php echo $colorDescriptions[$color]['name']; ?></h4>
                            <div class="score-val"><?php echo $score; ?> <span class="score-label">คะแนน</span></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="summary-meta">
                <div><strong>วันที่ทำการประเมิน:</strong> 
                <?php 
                $createdAt = $existingResult['created_at'] ?? null;
                if ($createdAt instanceof DateTime) {
                    echo $createdAt->format('d/m/Y H:i:s');
                } elseif (is_object($createdAt) && method_exists($createdAt, 'format')) {
                    echo $createdAt->format('d/m/Y H:i:s');
                } elseif (is_string($createdAt)) {
                    echo date('d/m/Y H:i:s', strtotime($createdAt));
                } else {
                    echo '-';
                }
                ?>
                </div>
                <div><a href="home.php" class="btn">กลับหน้าหลัก</a></div>
            </div>
        </div>
</body>
</html>
        <?php
        exit;
    }
} catch (Exception $e) {
    echo '<div style="color:red;text-align:center;margin-top:30px;">'.htmlspecialchars('เกิดข้อผิดพลาด: '.$e->getMessage()).'</div>';
    error_log('Color Test Exception: ' . $e->getMessage());
    exit;
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>แบบประเมินบุคลิกภาพสี</title>
    <!-- ฟอนต์ Prompt จาก Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Prompt', 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; }
        .container { max-width: 900px; margin: 0 auto; padding: 20px; font-family: 'Prompt', 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        .test-card { background: white; border-radius: 20px; padding: 30px; box-shadow: 0 10px 30px rgba(0,0,0,0.1); font-family: 'Prompt', 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        .header { text-align: center; margin-bottom: 30px; font-family: 'Prompt', 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        .header h1 { color: #333; margin-bottom: 10px; font-family: 'Prompt', 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        .timer-display { background: linear-gradient(135deg, #ff6b6b, #ee5a52); color: white; padding: 15px 30px; border-radius: 50px; font-size: 24px; font-weight: bold; display: inline-block; margin: 20px 0; font-family: 'Prompt', 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        .progress-container { background: #e9ecef; border-radius: 25px; height: 12px; margin: 20px 0; overflow: hidden; }
        .progress-bar { background: linear-gradient(90deg, #4CAF50, #45a049); height: 100%; transition: width 0.5s cubic-bezier(0.4,0,0.2,1); border-radius: 25px; }
        .question-card { background: #f8f9fa; border-radius: 15px; padding: 25px; margin: 20px 0; border-left: 5px solid #007bff; font-family: 'Prompt', 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        .question-text { font-size: 20px; font-weight: 600; color: #333; margin-bottom: 20px; font-family: 'Prompt', 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        .options-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; }
        .option { background: white; border: 2px solid #dee2e6; border-radius: 12px; padding: 20px; cursor: pointer; transition: all 0.3s ease; text-align: center; font-family: 'Prompt', 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        .option:hover { transform: translateY(-2px); box-shadow: 0 5px 15px rgba(0,0,0,0.1); }
        .option.selected { border-color: #007bff; background: #e3f2fd; }
        /* ไม่แสดงสีในกล่องตัวเลือก */
        .option-purple, .option-yellow, .option-blue, .option-green { border-left: none !important; }
        .nav-buttons { display: flex; justify-content: space-between; align-items: center; margin-top: 30px; }
        .btn { padding: 12px 24px; border: none; border-radius: 8px; cursor: pointer; font-size: 16px; font-weight: 600; transition: all 0.3s ease; font-family: 'Prompt', 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        .btn-primary { background: #007bff; color: white; }
        .btn-primary:hover { background: #0056b3; }
        .btn-secondary { background: #6c757d; color: white; }
        .btn-secondary:hover { background: #545b62; }
        .btn:disabled { background: #ccc; cursor: not-allowed; }
        .start-screen { text-align: center; padding: 50px 30px; font-family: 'Prompt', 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        .start-btn { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px 40px; font-size: 20px; border-radius: 50px; border: none; cursor: pointer; transition: transform 0.3s ease; font-family: 'Prompt', 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        .start-btn:hover { transform: scale(1.05); }
        .hidden { display: none; }
        .question-counter { background: #e9ecef; padding: 10px 20px; border-radius: 20px; font-weight: 600; color: #495057; font-family: 'Prompt', 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
    </style>
</head>
<body>
    <div class="container">
        <div class="test-card">
            <!-- หน้าเริ่มต้น -->
            <div id="startScreen" class="start-screen">
                <h1>🎨 แบบประเมินบุคลิกภาพสี</h1>
                <p style="font-size: 18px; color: #666; margin: 20px 0;">แบบประเมินนี้จะใช้เวลา 30 ข้อ ข้อละ 10 วินาที</p>
                <p style="color: #666; margin-bottom: 40px;">กรุณาเลือกตัวเลือกที่ตรงกับตัวคุณมากที่สุด<br>หากหมดเวลาจะข้ามไปข้อถัดไปอัตโนมัติ</p>
                <button class="start-btn" onclick="startTest()">เริ่มทำแบบทดสอบ</button>
            </div>

            <!-- หน้าคำถาม -->
            <div id="testScreen" class="hidden">
                <div class="header">
                    <h1>🎨 แบบประเมินบุคลิกภาพสี</h1>
                    <div class="timer-display" id="timer">10</div>
                    <div class="question-counter">
                        <span>ข้อที่ <span id="currentQ">1</span> จาก 30</span>
                    </div>
                </div>

                <div class="progress-container">
                    <div class="progress-bar" id="progressBar" style="width: 0%"></div>
                </div>

                <div class="question-card">
                    <div class="question-text" id="questionText"></div>
                    <div class="options-grid" id="optionsContainer"></div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // ข้อมูลคำถาม (ชุดใหม่ 20 ข้อ)
        // คำอธิบายแต่ละสี (ใช้ใน JS)
        const colorDescriptions = {
            purple: {
                name: "Purple (EXECUTING)",
                description: "\u2022 หาพันธมิตรที่แข็งแกร่งใน INFLUENCING และ RELATIONSHIP BUILDING\n\u2022 ใช้ความเป็นระบบเป็นจุดแข็งในการทำงาน\n\u2022 สร้าง processes และ systems ที่คนอื่นสามารถใช้ได้"
                
            },
            yellow: {
                name: "Yellow (INFLUENCING)",
                description: "\u2022 ฝึกการฟังและ empathy เพื่อเพิ่มประสิทธิภาพการชักจูง\n\u2022 หาคนทีแข็งแกร่งใน EXECUTING มาเป็นทีม\n\u2022 ใช้ energy ในการสร้างแรงบันดาลใจให้กับคนอื่น"
            },
            blue: {
                name: "Blue (RELATIONSHIP BUILDING)",
                description: "\u2022 พัฒนาทักษะการให้ feedback ที่สร้างสรรค์\n\u2022 ใช้ความเข้าใจในคนเป็นเครื่องมือในการจัดการทีม\n\u2022 ระวังการให้ความช่วยเหลือมากเกินไปจนหมดพลัง"
            },
            green: {
                name: "Green (STRATEGIC THINKING)",
                description: "\u2022 ฝึกการสื่อสารแนวคิดให้เข้าใจง่าย\n\u2022 หาคนทีแข็งแกร่งใน EXECUTING มาช่วยทําแผนให้เป็นจริง\n\u2022 ใช้เวลาคิดและวิเคราะห์อย่างมีระบบ"
            }
        };

        const questions = [
            // ขยายเป็น 30 ข้อ (ตัวอย่างข้อ 11-30 ใช้รูปแบบเดียวกับข้อ 1-10 สามารถแก้ไขข้อความได้ภายหลัง)
            {
                text: "ข้อ 1: คุณชอบทำงานแบบใดมากที่สุด?",
                options: {
                    purple: "วางแผนและควบคุมงานด้วยตนเอง",
                    yellow: "ทำงานร่วมกับผู้อื่นอย่างสนุกสนาน",
                    blue: "ทำงานตามขั้นตอนและรายละเอียด",
                    green: "ช่วยเหลือและสนับสนุนผู้อื่น"
                }
            },
            {
                text: "ข้อ 2: เมื่อเกิดปัญหา คุณมักจะ...",
                options: {
                    purple: "แก้ไขปัญหาด้วยตนเองอย่างรวดเร็ว",
                    yellow: "ขอคำปรึกษาจากเพื่อนร่วมงาน",
                    blue: "วิเคราะห์สาเหตุอย่างละเอียด",
                    green: "รอให้สถานการณ์คลี่คลายเอง"
                }
            },
            {
                text: "ข้อ 3: เวลาว่างคุณชอบ...",
                options: {
                    purple: "คิดโครงการใหม่ ๆ",
                    yellow: "เข้าสังคม พบปะเพื่อนฝูง",
                    blue: "อ่านหนังสือหรือหาความรู้",
                    green: "ทำกิจกรรมจิตอาสา"
                }
            },
            {
                text: "ข้อ 4: คุณให้ความสำคัญกับ...",
                options: {
                    purple: "ความสำเร็จและเป้าหมาย",
                    yellow: "ความสุขและบรรยากาศที่ดี",
                    blue: "ความถูกต้องและมาตรฐาน",
                    green: "ความสัมพันธ์และความสามัคคี"
                }
            },
            {
                text: "ข้อ 5: เมื่อมีการเปลี่ยนแปลง คุณ...",
                options: {
                    purple: "พร้อมรับและปรับตัวทันที",
                    yellow: "สร้างกำลังใจให้ผู้อื่น",
                    blue: "วางแผนรับมืออย่างรอบคอบ",
                    green: "ต้องการเวลาปรับตัว"
                }
            },
            {
                text: "ข้อ 6: คุณนิยามความสำเร็จว่า...",
                options: {
                    purple: "บรรลุเป้าหมายที่ตั้งไว้",
                    yellow: "มีความสุขกับสิ่งที่ทำ",
                    blue: "ทำงานได้อย่างถูกต้อง",
                    green: "มีความสัมพันธ์ที่ดีในทีม"
                }
            },
            {
                text: "ข้อ 7: เวลาทำงานเป็นทีม คุณ...",
                options: {
                    purple: "เป็นผู้นำและตัดสินใจ",
                    yellow: "สร้างบรรยากาศสนุกสนาน",
                    blue: "ดูแลรายละเอียดและตรวจสอบ",
                    green: "ช่วยเหลือและสนับสนุนสมาชิก"
                }
            },
            {
                text: "ข้อ 8: เมื่อได้รับคำวิจารณ์ คุณ...",
                options: {
                    purple: "นำไปปรับปรุงทันที",
                    yellow: "พูดคุยสร้างความเข้าใจ",
                    blue: "วิเคราะห์ข้อเท็จจริง",
                    green: "รับฟังและคิดทบทวน"
                }
            },
            {
                text: "ข้อ 9: คุณชอบสื่อสารแบบ...",
                options: {
                    purple: "ตรงไปตรงมา",
                    yellow: "เป็นมิตรและสนุกสนาน",
                    blue: "ใช้ข้อมูลและเหตุผล",
                    green: "อ่อนโยนและรับฟัง"
                }
            },
            {
                text: "ข้อ 10: คุณจัดการกับความเครียดโดย...",
                options: {
                    purple: "แก้ไขปัญหาทันที",
                    yellow: "พูดคุยกับเพื่อนหรือทำกิจกรรม",
                    blue: "วางแผนและจัดระเบียบใหม่",
                    green: "พักผ่อนและทำใจให้สงบ"
                }
            },
            // ข้อ 11-30 (ข้อความจริงจาก PDF)
            {
                text: "ข้อ 11: เมื่อคุณต้องทำงานที่มีความท้าทายใหม่ ๆ คุณจะ...",
                options: {
                    purple: "มุ่งมั่นและกล้าลุยทันที",
                    yellow: "ชวนเพื่อนหรือทีมมาร่วมสนุก",
                    blue: "ศึกษาข้อมูลและวางแผนก่อน",
                    green: "ขอคำแนะนำจากคนรอบข้าง"
                }
            },
            {
                text: "ข้อ 12: เวลาต้องตัดสินใจเรื่องสำคัญ คุณ...",
                options: {
                    purple: "ตัดสินใจด้วยตัวเองอย่างมั่นใจ",
                    yellow: "ขอความคิดเห็นจากหลาย ๆ คน",
                    blue: "วิเคราะห์ข้อดีข้อเสียอย่างละเอียด",
                    green: "คำนึงถึงผลกระทบต่อผู้อื่น"
                }
            },
            {
                text: "ข้อ 13: เมื่อมีปัญหาในทีม คุณ...",
                options: {
                    purple: "เสนอแนวทางแก้ไขทันที",
                    yellow: "สร้างบรรยากาศให้ผ่อนคลาย",
                    blue: "ตรวจสอบข้อมูลและหาสาเหตุ",
                    green: "รับฟังและประสานความเข้าใจ"
                }
            },
            {
                text: "ข้อ 14: คุณรู้สึกภูมิใจเมื่อ...",
                options: {
                    purple: "ได้รับความสำเร็จตามเป้าหมาย",
                    yellow: "ทุกคนในทีมมีความสุข",
                    blue: "งานออกมาถูกต้องสมบูรณ์",
                    green: "ได้ช่วยเหลือผู้อื่น"
                }
            },
            {
                text: "ข้อ 15: เวลาทำงานภายใต้แรงกดดัน คุณ...",
                options: {
                    purple: "เร่งมือและผลักดันตัวเอง",
                    yellow: "หาวิธีผ่อนคลายกับเพื่อนร่วมงาน",
                    blue: "จัดลำดับความสำคัญของงาน",
                    green: "ขอความช่วยเหลือจากทีม"
                }
            },
            {
                text: "ข้อ 16: คุณให้ความสำคัญกับ...",
                options: {
                    purple: "ผลลัพธ์และความสำเร็จ",
                    yellow: "ความสัมพันธ์ที่ดีในทีม",
                    blue: "กระบวนการและรายละเอียด",
                    green: "ความร่วมมือและการสนับสนุน"
                }
            },
            {
                text: "ข้อ 17: เวลามีงานด่วนเข้ามา คุณ...",
                options: {
                    purple: "ลงมือทำทันที",
                    yellow: "ชวนเพื่อนมาช่วยกัน",
                    blue: "วางแผนและแบ่งเวลา",
                    green: "ปรึกษาทีมก่อนเริ่มงาน"
                }
            },
            {
                text: "ข้อ 18: คุณชอบการทำงานแบบใด",
                options: {
                    purple: "อิสระและท้าทาย",
                    yellow: "สนุกสนานและมีเพื่อนร่วมงาน",
                    blue: "มีขั้นตอนชัดเจน",
                    green: "ได้ช่วยเหลือผู้อื่น"
                }
            },
            {
                text: "ข้อ 19: เมื่อเกิดความขัดแย้ง คุณ...",
                options: {
                    purple: "เผชิญหน้าและแก้ไขปัญหา",
                    yellow: "พยายามสร้างความเข้าใจ",
                    blue: "หาข้อมูลข้อเท็จจริง",
                    green: "ไกล่เกลี่ยและประนีประนอม"
                }
            },
            {
                text: "ข้อ 20: คุณได้รับแรงบันดาลใจจาก...",
                options: {
                    purple: "ความสำเร็จและเป้าหมายใหม่",
                    yellow: "การได้พบปะผู้คนใหม่ ๆ",
                    blue: "ความรู้และข้อมูลใหม่ ๆ",
                    green: "การได้ช่วยเหลือผู้อื่น"
                }
            },
            {
                text: "ข้อ 21: เวลาทำงานร่วมกับผู้อื่น คุณ...",
                options: {
                    purple: "ชอบเป็นผู้นำ",
                    yellow: "สร้างบรรยากาศสนุกสนาน",
                    blue: "รับผิดชอบในส่วนของตนเอง",
                    green: "สนับสนุนและช่วยเหลือทีม"
                }
            },
            {
                text: "ข้อ 22: คุณจัดการกับความผิดพลาดอย่างไร",
                options: {
                    purple: "แก้ไขและเดินหน้าต่อ",
                    yellow: "หัวเราะและเรียนรู้ร่วมกัน",
                    blue: "วิเคราะห์สาเหตุอย่างละเอียด",
                    green: "ให้อภัยและให้กำลังใจ"
                }
            },
            {
                text: "ข้อ 23: เวลาต้องเริ่มต้นสิ่งใหม่ ๆ คุณ...",
                options: {
                    purple: "ตื่นเต้นและพร้อมลุย",
                    yellow: "ชวนเพื่อนมาร่วมกิจกรรม",
                    blue: "ศึกษาข้อมูลก่อนลงมือ",
                    green: "ขอคำแนะนำจากผู้มีประสบการณ์"
                }
            },
            {
                text: "ข้อ 24: คุณนิยามความสำเร็จว่า...",
                options: {
                    purple: "การบรรลุเป้าหมาย",
                    yellow: "การมีความสุขร่วมกัน",
                    blue: "งานสำเร็จตามมาตรฐาน",
                    green: "ทุกคนในทีมประสบความสำเร็จ"
                }
            },
            {
                text: "ข้อ 25: เวลามีปัญหาเฉพาะหน้า คุณ...",
                options: {
                    purple: "ตัดสินใจและลงมือทันที",
                    yellow: "ขอความช่วยเหลือจากเพื่อน",
                    blue: "หาข้อมูลและวิเคราะห์",
                    green: "ปรึกษาทีมงาน"
                }
            },
            {
                text: "ข้อ 26: คุณชอบรับฟังความคิดเห็นจากผู้อื่นหรือไม่",
                options: {
                    purple: "รับฟังแต่ตัดสินใจเอง",
                    yellow: "รับฟังและแลกเปลี่ยนความคิดเห็น",
                    blue: "รับฟังและนำไปวิเคราะห์",
                    green: "รับฟังและให้ความสำคัญกับทุกคน"
                }
            },
            {
                text: "ข้อ 27: เวลาทำงานเสร็จ คุณ...",
                options: {
                    purple: "มองหาความท้าทายใหม่",
                    yellow: "ฉลองกับเพื่อนร่วมงาน",
                    blue: "ตรวจสอบความถูกต้องอีกครั้ง",
                    green: "ขอบคุณและให้กำลังใจทีม"
                }
            },
            {
                text: "ข้อ 28: คุณให้ความสำคัญกับอะไรในชีวิตการทำงาน",
                options: {
                    purple: "ความก้าวหน้าและความสำเร็จ",
                    yellow: "ความสุขและมิตรภาพ",
                    blue: "ความถูกต้องและมาตรฐาน",
                    green: "ความร่วมมือและความสามัคคี"
                }
            },
            {
                text: "ข้อ 29: เวลาต้องทำงานหลายอย่างพร้อมกัน คุณ...",
                options: {
                    purple: "จัดลำดับความสำคัญและลงมือทำ",
                    yellow: "ขอความช่วยเหลือจากทีม",
                    blue: "วางแผนและแบ่งเวลา",
                    green: "ทำงานร่วมกับผู้อื่น"
                }
            },
            {
                text: "ข้อ 30: คุณรู้สึกมีความสุขเมื่อ...",
                options: {
                    purple: "ประสบความสำเร็จในสิ่งที่ทำ",
                    yellow: "ได้อยู่กับเพื่อนและทีมงาน",
                    blue: "งานสำเร็จตามเป้าหมาย",
                    green: "ได้ช่วยเหลือและสนับสนุนผู้อื่น"
                }
            }
        ].flat();

        let currentQuestion = 0;
        let answers = {};
        let timer = 5;
        let timerInterval;
        let answeredCount = 0;

        function startTest() {
            document.getElementById('startScreen').classList.add('hidden');
            document.getElementById('testScreen').classList.remove('hidden');
            showQuestion();
            startTimer();
        }

        function showQuestion() {
            const question = questions[currentQuestion];
            document.getElementById('questionText').textContent = question.text;
            document.getElementById('currentQ').textContent = currentQuestion + 1;

            const container = document.getElementById('optionsContainer');
            container.innerHTML = '';

            // สุ่มลำดับ options ของแต่ละข้อ
            const optionColors = ['purple', 'yellow', 'blue', 'green'];
            const shuffledColors = shuffle([...optionColors]);

            shuffledColors.forEach(color => {
                const option = document.createElement('div');
                option.className = `option`;
                option.textContent = question.options[color];
                option.onclick = () => selectOption(color);

                if (answers[currentQuestion] === color) {
                    option.classList.add('selected');
                }

                container.appendChild(option);
            });

            updateProgress();
        }
// ฟังก์ชันสุ่มลำดับ array
function shuffle(array) {
    for (let i = array.length - 1; i > 0; i--) {
        const j = Math.floor(Math.random() * (i + 1));
        [array[i], array[j]] = [array[j], array[i]];
    }
    return array;
}


        function selectOption(color) {
            answers[currentQuestion] = color;
            
            // อัพเดทการนับข้อที่ตอบแล้ว
            const wasAnswered = answers[currentQuestion] !== undefined;
            if (!wasAnswered) {
                answeredCount++;
            }
            
            // แสดงการเลือก
            document.querySelectorAll('.option').forEach(opt => opt.classList.remove('selected'));
            // เพิ่ม selected ให้ option ที่เลือก (ตอนนี้ไม่มี .option-{color} แล้ว)
            // หา option ที่ตรงกับข้อความตัวเลือก
            const options = document.querySelectorAll('.option');
            options.forEach(opt => {
                if (opt.textContent === questions[currentQuestion].options[color]) {
                    opt.classList.add('selected');
                }
            });
            
            updateProgress();
            
            // เปลี่ยนไปข้อถัดไปอัตโนมัติหลังจาก 0.5 วินาที
            setTimeout(() => {
                if (currentQuestion < questions.length - 1) {
                    nextQuestion();
                } else {
                    submitTest();
                }
            }, 500);
        }

        function nextQuestion() {
            if (currentQuestion < questions.length - 1) {
                currentQuestion++;
                timer = 5;
                showQuestion();
                startTimer();
            } else {
                submitTest();
            }
        }

        function prevQuestion() {
            if (currentQuestion > 0) {
                currentQuestion--;
                timer = 5;
                showQuestion();
                startTimer();
            }
        }

        function startTimer() {
            clearInterval(timerInterval);
            document.getElementById('timer').textContent = timer;
            
            timerInterval = setInterval(() => {
                timer--;
                document.getElementById('timer').textContent = timer;
                
                if (timer <= 0) {
                    clearInterval(timerInterval);
                    
                    // ข้ามไปข้อถัดไปโดยไม่บันทึกคำตอบ
                    if (currentQuestion < questions.length - 1) {
                        currentQuestion++;
                        timer = 5;
                        showQuestion();
                        startTimer();
                    } else {
                        submitTest();
                    }
                }
            }, 1000);
        }

function updateProgress() {
    // ขยับ progress bar ตามข้อที่แสดงใน UI (ข้อที่ 1 จาก 30)
    const currentQ = parseInt(document.getElementById('currentQ').textContent, 10) || 1;
    const progressPercent = (currentQ / questions.length) * 100;
    document.getElementById('progressBar').style.width = progressPercent + '%';
}

        function updateButtons() {
            // ไม่มีปุ่มก่อนหน้า/ถัดไปอีกต่อไป
        }

        function submitTest() {
            clearInterval(timerInterval);
            
            // คำนวณคะแนน (ไม่นับข้อที่ข้าม)
            const scores = { purple: 0, yellow: 0, blue: 0, green: 0 };
            
            Object.values(answers).forEach(color => {
                scores[color]++;
            });
            
            // หาสีที่ได้คะแนนสูงสุดและสีที่มีคะแนนใกล้เคียง
            const maxScore = Math.max(...Object.values(scores));
            const dominantColors = Object.keys(scores).filter(color => scores[color] === maxScore);
            
            // หาสีรองที่ใกล้เคียง (ห่างกัน 1-2 คะแนน)
            let secondHighestScore = 0;
            Object.values(scores).forEach(score => {
                if (score < maxScore && score > secondHighestScore) {
                    secondHighestScore = score;
                }
            });
            
            const secondaryColors = Object.keys(scores).filter(color => 
                !dominantColors.includes(color) && 
                scores[color] === secondHighestScore && 
                (maxScore - secondHighestScore <= 2) && 
                secondHighestScore > 0
            );
            
            // รวมสีที่จะบันทึก (สีหลัก + สีรอง)
            const allDominantColors = [...dominantColors, ...secondaryColors];
            const primaryColor = dominantColors[0]; // สีหลักสำหรับ UI
            
            // ส่งข้อมูลไปบันทึก
            const data = {
                employee_id: '<?php echo $employee_id; ?>',
                evaluation_round: '<?php echo $currentRound; ?>',
                scores: scores,
                dominant_color: primaryColor,
                all_dominant_colors: allDominantColors, // ส่งสีทั้งหมดที่เป็น dominant
                answered_questions: answeredCount,
                total_questions: questions.length
            };
            
            fetch('save_color_test.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data)
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok: ' + response.status);
                }
                return response.json();
            })
            .then(result => {
                if (result.success) {
                    alert('บันทึกผลการประเมินเรียบร้อยแล้ว!');
                    location.reload();
                } else {
                    alert('เกิดข้อผิดพลาด: ' + result.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('เกิดข้อผิดพลาดในการส่งข้อมูล: ' + error.message);
            });
        }
    </script>
</body>
</html>
