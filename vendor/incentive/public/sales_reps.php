<?php
// public/sales_reps.php

// (สมมติว่ามีการตรวจสอบสิทธิ์ผู้จัดการแล้ว)

// เชื่อมต่อฐานข้อมูล (db.php ไม่ได้ใช้ในหน้านี้โดยตรง แต่ต้องมีถ้าไฟล์อื่นๆ ใช้)
// ตรวจสอบให้แน่ใจว่า db.php อยู่ในโฟลเดอร์ที่ถูกต้องเมื่อเทียบกับ sales_reps.php
// หาก sales_reps.php อยู่ใน public และ db.php อยู่นอก public (เช่น root directory)
// ควรจะเป็น require_once '../db.php';
// แต่ในตัวอย่างนี้สมมติว่า db.php อยู่ใน public หรือเข้าถึงได้จากที่นี่โดยตรง
include 'db.php'; 

// session_start();
// if (!isset($_SESSION['username'])) {
//     header("Location: login.php");
//     exit();
// }

// --- กำหนดค่า Pagination สำหรับหน้านี้ ---
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 30; // กำหนดจำนวนรายการต่อหน้า

// --- ดึงข้อมูลพนักงานขายจาก API พร้อม Pagination parameters ---
$salesReps = [];
$totalRecords = 0;
$currentPage = 1;
$perPage = $limit;
$errorMessage = null;

// สร้าง URL สำหรับ API โดยรวมพารามิเตอร์ page และ limit
// ตรวจสอบ URL ของ API ของคุณให้ถูกต้องตามโครงสร้างโปรเจกต์
// เช่น 'http://localhost/your_project_folder/public/api/get_sales_reps.php'
$api_url = 'http://localhost/incentive/public/api/api_get_sales_reps.php?page=' . $page . '&limit=' . $limit; 

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $api_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$response = curl_exec($ch);

if (curl_errno($ch)) {
    error_log('cURL error: ' . curl_error($ch));
    $errorMessage = 'ไม่สามารถเชื่อมต่อกับ API ได้: ' . curl_error($ch);
} else {
    $data = json_decode($response, true);
    if ($data && isset($data['success']) && $data['success']) {
        $salesReps = $data['data'] ?? [];
        $totalRecords = $data['total_records'] ?? 0;
        $currentPage = $data['current_page'] ?? $page;
        $perPage = $data['per_page'] ?? $limit;
    } else {
        error_log('API error: ' . ($data['message'] ?? 'Unknown API error'));
        $errorMessage = 'API ส่งคืนข้อผิดพลาด: ' . ($data['message'] ?? 'ไม่ทราบข้อผิดพลาด');
    }
}
curl_close($ch);

// คำนวณจำนวนหน้าทั้งหมด
$totalPages = ceil($totalRecords / $perPage);

?>

<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>จัดการพนักงาน - ระบบ Incentive</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['Prompt', 'sans-serif'],
                    }
                }
            }
        }
    </script>

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>

<body class="bg-gray-50 font-sans leading-normal tracking-normal">
    <div class="flex h-screen">
        <aside class="sidebar w-64 bg-white shadow-lg p-4 shadow-lg">
            <div class="mb-8">
                <h1 class="text-2xl font-bold text-blue-600 border-b pb-2 mb-4">ระบบ Incentive</h1>
                <p class="text-sm text-blue-700">สำหรับผู้จัดการ</p>
            </div>
            <nav>
                <ul>
                    <li class="mb-3">
                        <a href="index.php"
                            class="flex items-center text-gray-700 font-semibold hover:text-white hover:bg-gray-700 p-3 rounded-lg transition duration-200">
                            <i class="fas fa-tachometer-alt mr-3"></i> Dashboard
                        </a>
                    </li>
                    <li class="mb-3">
                        <a href="sales_reps.php"
                            class="flex items-center text-white bg-gray-700 p-3 rounded-lg transition duration-200"> <i
                                class="fas fa-users mr-3"></i> จัดการพนักงาน
                        </a>
                    </li>
                    <li class="mb-3">
                        <a href="products.php"
                            class="flex items-center text-gray-700 font-semibold hover:text-white hover:bg-gray-700 p-3 rounded-lg transition duration-200">
                            <i class="fas fa-box-open mr-3"></i> จัดการสินค้า
                        </a>
                    </li>
                    <li class="mb-3">
                        <a href="incentive_reports.php"
                            class="flex items-center text-gray-700 font-semibold hover:text-white hover:bg-gray-700 p-3 rounded-lg transition duration-200">
                            <i class="fas fa-chart-line mr-3"></i> รายงาน Incentive
                        </a>
                    </li>
                    <li class="mb-3">
                        <a href="incentive_settings.php"
                            class="flex items-center text-gray-700 font-semibold hover:text-white hover:bg-gray-700 p-3 rounded-lg transition duration-200">
                            <i class="fas fa-cogs mr-3"></i> ตั้งค่า Incentive
                        </a>
                    </li>
                    <li class="mt-auto">
                        <a href="#"
                            class="flex items-center text-gray-700 font-semibold hover:text-white hover:bg-gray-700 p-3 rounded-lg transition duration-200"
                            onclick="alert('ฟังก์ชัน Logout'); return false;">
                            <i class="fas fa-sign-out-alt mr-3"></i> ออกจากระบบ
                        </a>
                    </li>
                </ul>
            </nav>
        </aside>

        <div class="flex-1 p-8 bg-gray-50">
            <div class="flex justify-between items-center mb-6">
                <h1 class="text-3xl font-semibold text-gray-800">จัดการพนักงานขาย</h1>
                <div class="flex justify-center mb-6">
                    <img src="img/logo.png" alt="Logo" class="w-[200px] h-[29px]" onerror="this.style.display='none'">
                </div>
                <button disabled id="addSalesRepBtn"
                    class="bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded-lg shadow-sm transition duration-200 flex items-center">
                    <i class="fas fa-plus mr-2"></i> เพิ่มพนักงานใหม่
                </button>
            </div>

            <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-200 mb-8">
                <?php if (isset($errorMessage)): ?>
                    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">
                        <strong class="font-bold">ข้อผิดพลาด!</strong>
                        <span class="block sm:inline"><?= htmlspecialchars($errorMessage); ?></span>
                    </div>
                <?php endif; ?>
                <div class="overflow-x-auto">
                    <table class="min-w-full leading-normal">
                        <thead>
                            <tr>
                                <th
                                    class="px-5 py-3 border-b border-gray-200 bg-gray-50 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                    รหัสพนักงาน
                                </th>
                                <th
                                    class="px-5 py-3 border-b border-gray-200 bg-gray-50 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                    ชื่อพนักงาน
                                </th>
                                <th
                                    class="px-5 py-3 border-b border-gray-200 bg-gray-50 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                    สาขา
                                </th>
                                <th
                                    class="px-5 py-3 border-b border-gray-200 bg-gray-50 text-right text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                    จัดการเป้าหมาย
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($salesReps)): ?>
                                <tr>
                                    <td colspan="4"
                                        class="px-5 py-4 border-b border-gray-200 bg-white text-sm text-center text-gray-600">
                                        ไม่พบข้อมูลพนักงานขาย
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($salesReps as $rep): ?>
                                    <tr id="salesRep-<?php echo htmlspecialchars($rep['user_code']); ?>">
                                        <td class="px-5 py-4 border-b border-gray-200 bg-white text-sm">
                                            <p class="text-gray-800 whitespace-no-wrap">
                                                <?php echo htmlspecialchars($rep['user_code']); ?></p>
                                        </td>
                                        <td class="px-5 py-4 border-b border-gray-200 bg-white text-sm">
                                            <p class="text-gray-800 whitespace-no-wrap employee-name">
                                                <?php echo htmlspecialchars($rep['user_name']); ?></p>
                                        </td>
                                        <td class="px-5 py-4 border-b border-gray-200 bg-white text-sm">
                                            <p class="text-gray-800 whitespace-no-wrap">
                                                <?php echo htmlspecialchars($rep['branch_name'] ?? 'ไม่ระบุสาขา'); ?></p>
                                        </td>
                                        <td class="px-5 py-4 border-b border-gray-200 bg-white text-sm text-right">
                                            <button data-id="<?php echo htmlspecialchars($rep['user_code']); ?>"
                                                data-name="<?php echo htmlspecialchars($rep['user_name']); ?>"
                                                class="edit-btn text-blue-600 hover:text-blue-800 font-medium mr-3">
                                                ตั้งค่า
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <?php if ($totalPages > 1): ?>
                    <div class="px-5 py-5 bg-white border-t flex flex-col xs:flex-row items-center xs:justify-between">
                        <span class="text-xs xs:text-sm text-gray-900">
                            แสดง <?php echo min(($currentPage - 1) * $perPage + 1, $totalRecords); ?> ถึง
                            <?php echo min($currentPage * $perPage, $totalRecords); ?> จาก
                            <?php echo $totalRecords; ?> รายการ
                        </span>
                        <div class="inline-flex mt-2 xs:mt-0">
                            <button
                                class="text-sm bg-gray-300 hover:bg-gray-400 text-gray-800 font-semibold py-2 px-4 rounded-l-lg <?php echo ($currentPage <= 1) ? 'opacity-50 cursor-not-allowed' : ''; ?>"
                                <?php echo ($currentPage <= 1) ? 'disabled' : ''; ?>
                                onclick="window.location.href='sales_reps.php?page=<?php echo $currentPage - 1; ?>&limit=<?php echo $limit; ?>'">
                                ก่อนหน้า
                            </button>
                            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                <button
                                    class="text-sm bg-gray-300 hover:bg-gray-400 text-gray-800 font-semibold py-2 px-4 <?php echo ($i === $currentPage) ? 'bg-blue-600 text-white hover:bg-blue-700' : ''; ?>"
                                    onclick="window.location.href='sales_reps.php?page=<?php echo $i; ?>&limit=<?php echo $limit; ?>'">
                                    <?php echo $i; ?>
                                </button>
                            <?php endfor; ?>
                            <button
                                class="text-sm bg-gray-300 hover:bg-gray-400 text-gray-800 font-semibold py-2 px-4 rounded-r-lg <?php echo ($currentPage >= $totalPages) ? 'opacity-50 cursor-not-allowed' : ''; ?>"
                                <?php echo ($currentPage >= $totalPages) ? 'disabled' : ''; ?>
                                onclick="window.location.href='sales_reps.php?page=<?php echo $currentPage + 1; ?>&limit=<?php echo $limit; ?>'">
                                ถัดไป
                            </button>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <div id="salesRepModal"
                class="fixed inset-0 bg-gray-600 bg-opacity-50 flex items-center justify-center z-50 hidden">
                <div class="bg-white p-8 rounded-xl shadow-lg w-full max-w-2xl"> <div class="flex justify-between items-center mb-6">
                        <h2 id="modalTitle" class="text-2xl font-semibold text-gray-800">จัดการเป้าหมายพนักงาน: <span id="employeeNameInModal"></span></h2>
                        <button id="closeModalBtn" class="text-gray-500 hover:text-gray-700 text-2xl">&times;</button>
                    </div>
                    
                    <input type="hidden" id="salesRepUserCode" name="user_code">
                    <input type="hidden" id="salesRepUserName" name="user_name">

                    <div id="existingTargetsSection" class="mb-6">
                        <h3 class="text-xl font-medium text-gray-700 mb-4">เป้าหมายปัจจุบัน:</h3>
                        <div class="overflow-x-auto">
                            <table class="min-w-full leading-normal">
                                <thead>
                                    <tr>
                                        <th class="px-5 py-3 border-b border-gray-200 bg-gray-50 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                            ชื่อเป้าหมาย
                                        </th>
                                        <th class="px-5 py-3 border-b border-gray-200 bg-gray-50 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                            ช่วงเวลา
                                        </th>
                                        <th class="px-5 py-3 border-b border-gray-200 bg-gray-50 text-right text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                            ยอดเป้าหมาย
                                        </th>
                                        <th class="px-5 py-3 border-b border-gray-200 bg-gray-50 text-right text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                            การจัดการ
                                        </th>
                                    </tr>
                                </thead>
                                <tbody id="targetsTableBody">
                                    <tr>
                                        <td colspan="4" class="px-5 py-4 border-b border-gray-200 bg-white text-sm text-center text-gray-600">
                                            กำลังโหลดเป้าหมาย...
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <button id="addNewTargetBtn" class="mt-4 bg-green-600 hover:bg-green-700 text-white font-medium py-2 px-4 rounded-lg shadow-sm transition duration-200 flex items-center">
                            <i class="fas fa-plus mr-2"></i> เพิ่มเป้าหมายใหม่
                        </button>
                    </div>

                    <div id="targetFormSection" class="hidden"> <h3 id="targetFormTitle" class="text-xl font-medium text-gray-700 mb-4">เพิ่มเป้าหมายใหม่:</h3>
                        <form id="salesRepForm">
                            <input type="hidden" id="formUserCode" name="user_code">
                            <input type="hidden" id="formUserName" name="user_name">
                            <input type="hidden" id="formTargetID" name="target_id">   <input type="hidden" id="formDetailID" name="detail_id">   <div class="mb-4">
                                <label for="target_name" class="block text-gray-700 text-sm font-medium mb-2">ชื่อเป้าหมาย:</label>
                                <input type="text" id="target_name" name="target_name"
                                    class="shadow-sm appearance-none border border-gray-300 rounded-lg w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:ring-2 focus:ring-blue-500"
                                    placeholder="เช่น เป้าหมายยอดขายเดือน ม.ค. 2568" required>
                            </div>

                            <div class="mb-4">
                                <label for="start_date" class="block text-gray-700 text-sm font-medium mb-2">วันที่เริ่มต้น:</label>
                                <input type="date" id="start_date" name="start_date"
                                    class="shadow-sm appearance-none border border-gray-300 rounded-lg w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:ring-2 focus:ring-blue-500"
                                    required>
                            </div>

                            <div class="mb-4">
                                <label for="end_date" class="block text-gray-700 text-sm font-medium mb-2">วันที่สิ้นสุด:</label>
                                <input type="date" id="end_date" name="end_date"
                                    class="shadow-sm appearance-none border border-gray-300 rounded-lg w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:ring-2 focus:ring-blue-500"
                                    required>
                            </div>
                            
                            <div class="mb-4">
                                <label for="detail_type" class="block text-gray-700 text-sm font-medium mb-2">ประเภทรายละเอียดเป้าหมาย:</label>
                                <select id="detail_type" name="detail_type"
                                    class="shadow-sm appearance-none border border-gray-300 rounded-lg w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:ring-2 focus:ring-blue-500"
                                    required>
                                    <option value="Employee">พนักงาน (เป้าหมายรวม)</option>
                                    </select>
                                <p class="text-xs text-gray-500 mt-1">
                                    *ขณะนี้รองรับเฉพาะ "พนักงาน" (เป้าหมายรวมของพนักงาน)
                                </p>
                            </div>

                            <div class="mb-6">
                                <label for="detail_target_amount"
                                    class="block text-gray-700 text-sm font-medium mb-2">ยอดเป้าหมายสำหรับรายละเอียด:</label>
                                <input type="number" id="detail_target_amount" name="detail_target_amount"
                                    class="shadow-sm appearance-none border border-gray-300 rounded-lg w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:ring-2 focus:ring-blue-500"
                                    step="0.01" required>
                            </div>

                            <div class="flex justify-end">
                                <button type="button" id="cancelTargetFormBtn"
                                    class="bg-gray-300 hover:bg-gray-400 text-gray-800 font-medium py-2 px-4 rounded-lg mr-2 transition duration-200">ยกเลิก</button>
                                <button type="submit"
                                    class="bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded-lg shadow-sm transition duration-200">บันทึกเป้าหมาย</button>
                            </div>
                        </form>
                    </div>

                </div>
            </div>

        </div>
    </div>

    <script src="js/sales_reps_manager.js"></script>
</body>

</html>