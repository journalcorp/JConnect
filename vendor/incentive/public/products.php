<?php
// public/products.php

// (สมมติว่ามีการตรวจสอบสิทธิ์ผู้จัดการแล้ว)

// เชื่อมต่อฐานข้อมูล
include 'db.php';

// session_start();
// if (!isset($_SESSION['username'])) {
//     header("Location: login.php");
//     exit();
// }
// ตั้งค่าเริ่มต้น
$startDate = $_GET['start_date'] ?? date('Y-m-01');
$endDate = $_GET['end_date'] ?? date('Y-m-01');
$page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
$limit = 30;
$offset = ($page - 1) * $limit;

// นับจำนวนทั้งหมด
$sqlCount = "
SELECT COUNT(*) AS total
FROM TCNMPdt AS A
LEFT JOIN TCNMPdt_L AS B WITH (NOLOCK) ON B.FTPdtCode = A.FTPdtCode
LEFT JOIN TCNMPdtGrp_L AS C WITH (NOLOCK) ON C.FTPgpChain = A.FTPgpChain
LEFT JOIN TCNMPdtType_L AS D WITH (NOLOCK) ON D.FTPtyCode = A.FTPtyCode
LEFT JOIN TCNMPdtBrand_L AS E WITH (NOLOCK) ON E.FTPbnCode = A.FTPbnCode
LEFT JOIN TCNMPdtModel_L AS F WITH (NOLOCK) ON F.FTPmoCode = A.FTPmoCode
LEFT JOIN TCNMPdtPackSize AS G WITH (NOLOCK) ON G.FTPdtCode = A.FTPdtCode
LEFT JOIN TCNMPdtColor_L AS L WITH (NOLOCK) ON L.FTClrCode = G.FTClrCode
LEFT JOIN TCNMPdtSize_L AS M WITH (NOLOCK) ON M.FTPszCode = G.FTPszCode
LEFT JOIN TCNMPdtBar AS N WITH (NOLOCK) ON N.FTPdtCode = A.FTPdtCode
LEFT JOIN TCNMPdtUnit_L AS O WITH (NOLOCK) ON G.FTPunCode = O.FTPunCode
-- WHERE เงื่อนไขวันที่สามารถใส่ได้หากมีคอลัมน์ เช่น A.FDCreateOn BETWEEN ? AND ?
";

// Parameters for sqlCount query
$paramsCount = [];
// Add parameters if WHERE clause is uncommented, e.g., $paramsCount = [$startDate, $endDate];
$stmtCount = sqlsrv_query($conn, $sqlCount, $paramsCount);
if ($stmtCount === false) {
    echo "Error in counting products: " . print_r(sqlsrv_errors(), true);
    $totalRows = 0;
} else {
    $totalRows = sqlsrv_fetch_array($stmtCount)['total'];
}
$totalPages = ceil($totalRows / $limit);

// ดึงข้อมูลจริง
$sql = "
SELECT
    A.FTPdtCode AS รหัสสินค้า,
    B.FTPdtName AS ชื่อสินค้า,
    N.FTBarCode AS บาร์โค๊ดสินค้า,
    O.FTPunName AS หน่วยนับ,
    C.FTPgpName AS กลุ่มสินค้า,
    D.FTPtyName AS ประเภทสินค้า,
    E.FTPbnName AS [ประเภทสินค้า(หลัก)],
    F.FTPmoName AS [ประเภทสินค้า(ย่อย)],
    L.FTClrName AS กลิ่น,
    M.FTPszName AS ขนาด
FROM TCNMPdt AS A
LEFT JOIN TCNMPdt_L AS B WITH (NOLOCK) ON B.FTPdtCode = A.FTPdtCode
LEFT JOIN TCNMPdtGrp_L AS C WITH (NOLOCK) ON C.FTPgpChain = A.FTPgpChain
LEFT JOIN TCNMPdtType_L AS D WITH (NOLOCK) ON D.FTPtyCode = A.FTPtyCode
LEFT JOIN TCNMPdtBrand_L AS E WITH (NOLOCK) ON E.FTPbnCode = A.FTPbnCode
LEFT JOIN TCNMPdtModel_L AS F WITH (NOLOCK) ON F.FTPmoCode = A.FTPmoCode
LEFT JOIN TCNMPdtPackSize AS G WITH (NOLOCK) ON G.FTPdtCode = A.FTPdtCode
LEFT JOIN TCNMPdtColor_L AS L WITH (NOLOCK) ON L.FTClrCode = G.FTClrCode
LEFT JOIN TCNMPdtSize_L AS M WITH (NOLOCK) ON M.FTPszCode = G.FTPszCode
LEFT JOIN TCNMPdtBar AS N WITH (NOLOCK) ON N.FTPdtCode = A.FTPdtCode
LEFT JOIN TCNMPdtUnit_L AS O WITH (NOLOCK) ON G.FTPunCode = O.FTPunCode
-- WHERE A.FDCreateOn BETWEEN ? AND ?
ORDER BY A.FTPdtCode
OFFSET ? ROWS FETCH NEXT ? ROWS ONLY
";

$params = [$offset, $limit];
$stmt = sqlsrv_query($conn, $sql, $params);
if ($stmt === false) {
    echo "Error in fetching products: " . print_r(sqlsrv_errors(), true);
}

// --- สิ้นสุดข้อมูลจำลอง (ข้อมูลจริงจะถูกดึงจาก DB แทน) ---
?>

<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>จัดการสินค้า - ระบบ Incentive</title>
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
                            class="flex items-center text-gray-700 font-semibold hover:text-white hover:bg-gray-700 p-3 rounded-lg transition duration-200">
                            <i class="fas fa-users mr-3"></i> จัดการพนักงาน
                        </a>
                    </li>
                    <li class="mb-3">
                        <a href="products.php"
                            class="flex items-center text-white bg-gray-700  p-3 rounded-lg transition duration-200"> <i
                                class="fas fa-box-open mr-3"></i> จัดการสินค้า
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
                <h1 class="text-3xl font-semibold text-gray-800">จัดการสินค้า</h1>
                <div class="flex justify-center mb-6">
                    <img src="img/logo.png" alt="Logo" class="w-[200px] h-[29px]" onerror="this.style.display='none'">
                </div>
                <button disabled id="addProductBtn"
                    class="bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded-lg shadow-sm transition duration-200 flex items-center">
                    <i class="fas fa-plus mr-2"></i> เพิ่มสินค้าใหม่
                </button>
            </div>

            <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-200 mb-8">
                <div class="overflow-x-auto">
                    <table class="min-w-full leading-normal">
                        <thead>
                            <tr>
                                <th
                                    class="px-5 py-3 border-b border-gray-200 bg-gray-50 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                    ลำดับ
                                </th>
                                <th
                                    class="px-5 py-3 border-b border-gray-200 bg-gray-50 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                    รหัสสินค้า
                                </th>
                                <th
                                    class="px-5 py-3 border-b border-gray-200 bg-gray-50 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                    ชื่อสินค้า
                                </th>
                                <th
                                    class="px-5 py-3 border-b border-gray-200 bg-gray-50 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                    บาร์โค๊ดสินค้า
                                </th>
                                <th
                                    class="px-5 py-3 border-b border-gray-200 bg-gray-50 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                    หน่วยนับ
                                </th>
                                <th
                                    class="px-5 py-3 border-b border-gray-200 bg-gray-50 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                    กลุ่มสินค้า
                                </th>
                                <th
                                    class="px-5 py-3 border-b border-gray-200 bg-gray-50 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                    ประเภทสินค้า
                                </th>
                                <th
                                    class="px-5 py-3 border-b border-gray-200 bg-gray-50 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                    ประเภทสินค้า(หลัก)
                                </th>
                                <th
                                    class="px-5 py-3 border-b border-gray-200 bg-gray-50 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                    ประเภทสินค้า(ย่อย)
                                </th>
                                <th
                                    class="px-5 py-3 border-b border-gray-200 bg-gray-50 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                    กลิ่น
                                </th>
                                <th
                                    class="px-5 py-3 border-b border-gray-200 bg-gray-50 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                    ขนาด
                                </th>
                                <th class="px-5 py-3 border-b border-gray-200 bg-gray-50"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($stmt === false || sqlsrv_has_rows($stmt) === false): ?>
                                <tr>
                                    <td colspan="12"
                                        class="px-5 py-4 border-b border-gray-200 bg-white text-sm text-center text-gray-600">
                                        ไม่พบข้อมูลสินค้า
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php $index = $offset + 1; ?>
                                <?php while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)): ?>
                                    <tr class="hover:bg-gray-100">
                                        <td class="px-5 py-4 border-b border-gray-200 bg-white text-sm text-center"><?= $index++ ?></td>
                                        <td class="px-5 py-4 border-b border-gray-200 bg-white text-sm product-code">
                                            <p class="text-gray-800 whitespace-no-wrap"><?= htmlspecialchars($row['รหัสสินค้า'] ?? ''); ?></p>
                                        </td>
                                        <td class="px-5 py-4 border-b border-gray-200 bg-white text-sm product-name">
                                            <p class="text-gray-800 whitespace-no-wrap"><?= htmlspecialchars($row['ชื่อสินค้า'] ?? ''); ?></p>
                                        </td>
                                        <td class="px-5 py-4 border-b border-gray-200 bg-white text-sm barcode">
                                            <p class="text-gray-800 whitespace-no-wrap"><?= htmlspecialchars($row['บาร์โค๊ดสินค้า'] ?? ''); ?></p>
                                        </td>
                                        <td class="px-5 py-4 border-b border-gray-200 bg-white text-sm unit">
                                            <p class="text-gray-800 whitespace-no-wrap"><?= htmlspecialchars($row['หน่วยนับ'] ?? ''); ?></p>
                                        </td>
                                        <td class="px-5 py-4 border-b border-gray-200 bg-white text-sm group">
                                            <p class="text-gray-800 whitespace-no-wrap"><?= htmlspecialchars($row['กลุ่มสินค้า'] ?? ''); ?></p>
                                        </td>
                                        <td class="px-5 py-4 border-b border-gray-200 bg-white text-sm type">
                                            <p class="text-gray-800 whitespace-no-wrap"><?= htmlspecialchars($row['ประเภทสินค้า'] ?? ''); ?></p>
                                        </td>
                                        <td class="px-5 py-4 border-b border-gray-200 bg-white text-sm main-type">
                                            <p class="text-gray-800 whitespace-no-wrap"><?= htmlspecialchars($row['ประเภทสินค้า(หลัก)'] ?? ''); ?></p>
                                        </td>
                                        <td class="px-5 py-4 border-b border-gray-200 bg-white text-sm sub-type">
                                            <p class="text-gray-800 whitespace-no-wrap"><?= htmlspecialchars($row['ประเภทสินค้า(ย่อย)'] ?? ''); ?></p>
                                        </td>
                                        <td class="px-5 py-4 border-b border-gray-200 bg-white text-sm scent">
                                            <p class="text-gray-800 whitespace-no-wrap"><?= htmlspecialchars($row['กลิ่น'] ?? ''); ?></p>
                                        </td>
                                        <td class="px-5 py-4 border-b border-gray-200 bg-white text-sm size">
                                            <p class="text-gray-800 whitespace-no-wrap"><?= htmlspecialchars($row['ขนาด'] ?? ''); ?></p>
                                        </td>
                                        <td class="px-5 py-4 border-b border-gray-200 bg-white text-sm text-right">
                                            <button data-id="<?= htmlspecialchars($row['รหัสสินค้า'] ?? ''); ?>"
                                                class="edit-btn text-blue-600 hover:text-blue-800 font-medium mr-3">
                                                แก้ไข
                                            </button>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <div class="mt-6 flex justify-center space-x-2">
                    <?php
                    $totalPagesToShow = 15; // จำนวนหน้าที่จะแสดงใกล้กับหน้าปัจจุบัน
                    $startPage = max(1, $page - floor($totalPagesToShow / 2));
                    $endPage = min($totalPages, $startPage + $totalPagesToShow - 1);

                    // กรณีท้ายตารางน้อยกว่าหน้าควรแสดง
                    if ($endPage - $startPage + 1 < $totalPagesToShow) {
                        $startPage = max(1, $endPage - $totalPagesToShow + 1);
                    }

                    // ปุ่ม Previous
                    if ($page > 1): ?>
                        <a href="?start_date=<?= $startDate ?>&end_date=<?= $endDate ?>&page=<?= $page - 1 ?>"
                            class="px-3 py-1 border rounded bg-gray-200 hover:bg-gray-300">«</a>
                    <?php endif;

                    // แสดงหมายเลขหน้า
                    for ($i = $startPage; $i <= $endPage; $i++): ?>
                        <a href="?start_date=<?= $startDate ?>&end_date=<?= $endDate ?>&page=<?= $i ?>"
                            class="px-3 py-1 border rounded <?= $i == $page ? 'bg-blue-600 text-white' : 'bg-white hover:bg-gray-100' ?>">
                            <?= $i ?>
                        </a>
                    <?php endfor;

                    // ปุ่ม Next
                    if ($page < $totalPages): ?>
                        <a href="?start_date=<?= $startDate ?>&end_date=<?= $endDate ?>&page=<?= $page + 1 ?>"
                            class="px-3 py-1 border rounded bg-gray-200 hover:bg-gray-300">»</a>
                    <?php endif;
                    ?>
                </div>
            </div>

            <div id="productModal"
                class="fixed inset-0 bg-gray-600 bg-opacity-50 flex items-center justify-center z-50 hidden">
                <div class="bg-white p-8 rounded-xl shadow-lg w-full max-w-md">
                    <div class="flex justify-between items-center mb-6">
                        <h2 id="modalTitle" class="text-2xl font-semibold text-gray-800">เพิ่มสินค้าใหม่</h2>
                        <button id="closeModalBtn" class="text-gray-500 hover:text-gray-700 text-2xl">&times;</button>
                    </div>
                    <form id="productForm">
                        <input type="hidden" id="productId">
                        <div class="mb-4">
                            <label for="productName"
                                class="block text-gray-700 text-sm font-medium mb-2">ชื่อสินค้า:</label>
                            <input type="text" id="productName" name="name"
                                class="shadow-sm appearance-none border border-gray-300 rounded-lg w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:ring-2 focus:ring-blue-500"
                                required>
                        </div>
                        <div class="mb-4">
                            <label for="productGroup"
                                class="block text-gray-700 text-sm font-medium mb-2">กลุ่มสินค้า:</label>
                            <input type="text" id="productGroup" name="group"
                                class="shadow-sm appearance-none border border-gray-300 rounded-lg w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:ring-2 focus:ring-blue-500">
                        </div>
                        <div class="mb-6">
                            <label for="specialIncentivePercent" class="block text-gray-700 text-sm font-medium mb-2">%
                                Incentive พิเศษ:</label>
                            <input type="number" id="specialIncentivePercent" name="special_incentive_percent"
                                class="shadow-sm appearance-none border border-gray-300 rounded-lg w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:ring-2 focus:ring-blue-500"
                                step="0.1" required>
                        </div>
                        <div class="flex justify-end">
                            <button type="button" id="cancelFormBtn"
                                class="bg-gray-300 hover:bg-gray-400 text-gray-800 font-medium py-2 px-4 rounded-lg mr-2 transition duration-200">ยกเลิก</button>
                            <button type="submit"
                                class="bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded-lg shadow-sm transition duration-200">บันทึก</button>
                        </div>
                    </form>
                </div>
            </div>

        </div>
    </div>

    <script src="js/product_manager.js"></script>
</body>

</html>