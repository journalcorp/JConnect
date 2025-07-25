<?php
// public/index.php

// (สมมติว่ามีการตรวจสอบสิทธิ์ผู้จัดการแล้ว)

// --- ข้อมูลจำลอง (ในระบบจริงจะมาจากการเรียก API) ---
// $salesReps = [
//     [
//         'id' => 1,
//         'name' => 'สมชาย ขายเก่ง',
//         'target_sales' => 500000,
//         'actual_sales' => 450000,
//         'base_incentive_percent' => 3,
//         'total_incentive' => 13500 // 450000 * 0.03
//     ],
//     [
//         'id' => 2,
//         'name' => 'สมหญิง ใจดี',
//         'target_sales' => 600000,
//         'actual_sales' => 620000,
//         'base_incentive_percent' => 3.5,
//         'total_incentive' => 21700 // 620000 * 0.035 + (อาจมี special incentive)
//     ],
//     [
//         'id' => 3,
//         'name' => 'มานะ มุ่งมั่น',
//         'target_sales' => 400000,
//         'actual_sales' => 380000,
//         'base_incentive_percent' => 2.8,
//         'total_incentive' => 10640 // 380000 * 0.028
//     ],
// ];

// $products = [
//     ['id' => 101, 'name' => 'Smartphone Pro', 'price' => 25000, 'special_incentive_percent' => 1.5],
//     ['id' => 102, 'name' => 'Laptop X1', 'price' => 40000, 'special_incentive_percent' => 2.0],
//     ['id' => 103, 'name' => 'Smartwatch Lite', 'price' => 8000, 'special_incentive_percent' => 0],
// ];

// $recentTransactions = [
//     ['id' => 1, 'sales_rep_name' => 'สมหญิง ใจดี', 'product_name' => 'Laptop X1', 'amount' => 40000, 'date' => '2025-06-01'],
//     ['id' => 2, 'sales_rep_name' => 'สมชาย ขายเก่ง', 'product_name' => 'Smartphone Pro', 'amount' => 25000, 'date' => '2025-06-02'],
//     ['id' => 3, 'sales_rep_name' => 'มานะ มุ่งมั่น', 'product_name' => 'Smartwatch Lite', 'amount' => 8000, 'date' => '2025-06-02'],
// ];
// --- สิ้นสุดข้อมูลจำลอง ---
?>

<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard ผู้จัดการ - ระบบ Incentive</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['Prompt', 'sans-serif'], // กำหนดให้ font-sans ใช้ Prompt
                    }
                }
            }
        }
    </script>

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        /* Optional: Custom scrollbar for the table body */
        .overflow-y-auto::-webkit-scrollbar {
            width: 8px;
        }
        .overflow-y-auto::-webkit-scrollbar-track {
            background: #f1f1f1;
        }
        .overflow-y-auto::-webkit-scrollbar-thumb {
            background: #888;
            border-radius: 4px;
        }
        .overflow-y-auto::-webkit-scrollbar-thumb:hover {
            background: #555;
        }

        /* Spinner styles */
        .loader {
            border: 4px solid #f3f3f3;
            border-top: 4px solid #3498db;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            animation: spin 2s linear infinite;
            margin: 20px auto; /* Center the spinner */
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
</head>

<body class="bg-gray-50 font-sans leading-normal tracking-normal">
    <div class="flex h-screen">
        <aside class="sidebar w-64 bg-white shadow-lg p-4 show">
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
            <div class="flex justify-center mb-6">
                <img src="img/logo.png" alt="Logo" class="w-[200px] h-[29px]" onerror="this.style.display='none'">
            </div>

            <!-- <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mb-8">
                <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-200">
                    <h2 class="text-xl font-medium text-gray-700 mb-2">ยอดขายรวมทั้งหมด</h2>
                    <p class="text-3xl font-bold text-blue-600">฿
                </div>
                <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-200">
                    <h2 class="text-xl font-medium text-gray-700 mb-2">Incentive ที่ต้องจ่ายรวม</h2>
                    <p class="text-3xl font-bold text-green-600">฿
                </div>
                <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-200">
                    <h2 class="text-xl font-medium text-gray-700 mb-2">พนักงานขายทั้งหมด</h2>
                    <p class="text-3xl font-bold text-gray-800">คน</p>
                </div>
            </div> -->

            <!-- <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-200 mb-8">
                <h2 class="text-xl font-semibold text-gray-700 mb-4">Performance พนักงานขาย</h2>
                <div class="overflow-x-auto">
                    <table class="min-w-full leading-normal">
                        <thead>
                            <tr>
                                <th
                                    class="px-5 py-3 border-b border-gray-200 bg-gray-50 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                    ชื่อพนักงาน
                                </th>
                                <th
                                    class="px-5 py-3 border-b border-gray-200 bg-gray-50 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                    เป้าหมาย
                                </th>
                                <th
                                    class="px-5 py-3 border-b border-gray-200 bg-gray-50 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                    ยอดขายจริง
                                </th>
                                <th
                                    class="px-5 py-3 border-b border-gray-200 bg-gray-50 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                    % Incentive พื้นฐาน
                                </th>
                                <th
                                    class="px-5 py-3 border-b border-gray-200 bg-gray-50 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                    Incentive รวม (ประมาณ)
                                </th>
                                <th
                                    class="px-5 py-3 border-b border-gray-200 bg-gray-50 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                    สถานะ
                                </th>
                                <th class="px-5 py-3 border-b border-gray-200 bg-gray-50"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($salesReps as $rep): ?>
                                <tr>
                                    <td class="px-5 py-4 border-b border-gray-200 bg-white text-sm">
                                        <p class="text-gray-800 whitespace-no-wrap">
                                            <?php echo htmlspecialchars($rep['name']); ?></p>
                                    </td>
                                    <td class="px-5 py-4 border-b border-gray-200 bg-white text-sm">
                                        <p class="text-gray-800 whitespace-no-wrap">฿
                                            <?php echo number_format($rep['target_sales']); ?></p>
                                    </td>
                                    <td class="px-5 py-4 border-b border-gray-200 bg-white text-sm">
                                        <p class="text-gray-800 whitespace-no-wrap">฿
                                            <?php echo number_format($rep['actual_sales']); ?></p>
                                    </td>
                                    <td class="px-5 py-4 border-b border-gray-200 bg-white text-sm">
                                        <p class="text-gray-800 whitespace-no-wrap">
                                            <?php echo $rep['base_incentive_percent']; ?>%</p>
                                    </td>
                                    <td class="px-5 py-4 border-b border-gray-200 bg-white text-sm">
                                        <p class="text-gray-800 whitespace-no-wrap">฿
                                            <?php echo number_format($rep['total_incentive']); ?></p>
                                    </td>
                                    <td class="px-5 py-4 border-b border-gray-200 bg-white text-sm">
                                        <?php
                                        $statusClass = ($rep['actual_sales'] >= $rep['target_sales']) ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700';
                                        $statusText = ($rep['actual_sales'] >= $rep['target_sales']) ? 'ถึงเป้า' : 'ต่ำกว่าเป้า';
                                        ?>
                                        <span
                                            class="relative inline-block px-3 py-1 font-medium leading-tight <?php echo $statusClass; ?> rounded-full">
                                            <span class="absolute inset-0 opacity-50 rounded-full"></span>
                                            <span class="relative"><?php echo $statusText; ?></span>
                                        </span>
                                    </td>
                                    <td class="px-5 py-4 border-b border-gray-200 bg-white text-sm text-right">
                                        <button class="text-blue-600 hover:text-blue-800 font-medium"
                                            onclick="alert('ดูรายละเอียดของ <?php echo htmlspecialchars($rep['name']); ?>');">
                                            ดูรายละเอียด
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div> -->

            <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-200 mb-8">
                <h2 class="text-xl font-semibold text-gray-700 mb-4">รายการขายล่าสุด</h2>
                <div class="mb-4 flex justify-end">
                    <button id="refreshButton" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline flex items-center">
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004 14v.582m.008-3.792A8.001 8.001 0 0120 10.582v.008"></path></svg>
                        รีเฟรชข้อมูล
                    </button>
                </div>

                <div class="overflow-x-auto relative shadow-md sm:rounded-lg">
                    <table class="w-full text-sm text-left text-gray-500">
                        <thead class="text-xs text-gray-700 uppercase bg-gray-50">
                            <tr>
                                <th scope="col" class="py-3 px-6">
                                    รูปภาพ
                                </th>
                                <th scope="col" class="py-3 px-6">
                                    ชื่อสินค้า
                                </th>
                                <th scope="col" class="py-3 px-6 text-right">
                                    จำนวน
                                </th>
                                <th scope="col" class="py-3 px-6 text-right">
                                    ราคาต่อหน่วย
                                </th>
                                <th scope="col" class="py-3 px-6 text-right">
                                    ยอดรวมสุทธิ
                                </th>
                                <th scope="col" class="py-3 px-6">
                                    วันที่เอกสาร
                                </th>
                                <th scope="col" class="py-3 px-6">
                                    สาขา
                                </th>
                                <th scope="col" class="py-3 px-6">
                                    พนักงาน
                                </th>
                            </tr>
                        </thead>
                        <tbody id="salesTableBody">
                            <tr>
                                <td colspan="8" class="text-center py-4 text-gray-500">กำลังโหลดข้อมูล...</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div id="loadingIndicator" class="hidden text-center mt-4 text-blue-500 font-semibold">
                    กำลังโหลดข้อมูล...
                </div>
                <div id="errorIndicator" class="hidden text-center mt-4 text-red-500 font-semibold">
                    เกิดข้อผิดพลาดในการโหลดข้อมูล.
                </div>
                </div>
        </div>
    </div>

    <script src="js/manager.js"></script>

    <script>
        const salesTableBody = document.getElementById('salesTableBody');
        const loadingIndicator = document.getElementById('loadingIndicator');
        const errorIndicator = document.getElementById('errorIndicator');
        const refreshButton = document.getElementById('refreshButton');
        const spinner = document.createElement('div'); // Create spinner element
        spinner.classList.add('loader'); // Add a class for styling

        async function fetchLatestSales() {
            salesTableBody.innerHTML = ''; // Clear existing data
            salesTableBody.appendChild(spinner); // Add spinner to the table body
            spinner.classList.remove('hidden'); // Show the spinner
            loadingIndicator.classList.remove('hidden');
            errorIndicator.classList.add('hidden');

            try {
                const response = await fetch('api/api_latest_sales.php');
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                const result = await response.json();

                if (!result.success) {
                    throw new Error(result.message || 'API returned an error.');
                }

                const salesData = result.data;

                if (salesData.length === 0) {
                    salesTableBody.innerHTML = `
                        <tr>
                            <td colspan="8" class="text-center py-4 text-gray-500">ไม่พบข้อมูลการขาย.</td>
                        </tr>
                    `;
                    return;
                }

                salesData.forEach(sale => {
                    const row = document.createElement('tr');
                    row.classList.add('bg-white', 'border-b', 'hover:bg-gray-50');

                    // ใช้ product_name โดยตรงจาก API แล้วทำการ encodeURIComponent
                    const encodedProductName = encodeURIComponent(sale.product_name);
                    const imagePath = `images/products/${encodedProductName}.png`; 

                    const quantity = parseFloat(sale.quantity);
                    const netTotal = parseFloat(sale.net_total);
                    const pricePerUnit = quantity > 0 ? (netTotal / quantity).toFixed(2) : '0.00';

                    row.innerHTML = `
                        <td class="py-4 px-6">
                            <img src="${imagePath}" alt="${sale.product_name}" class="w-16 h-16 object-cover rounded-md" onerror="this.onerror=null;this.src='https://via.placeholder.com/64x64.png?text=No+Image';" />
                        </td>
                        <td class="py-4 px-6">
                            ${sale.product_name || '-'}
                        </td>
                        <td class="py-4 px-6 text-right">
                            ${parseInt(sale.quantity).toLocaleString()}
                        </td>
                        <td class="py-4 px-6 text-right">
                            ฿${pricePerUnit}
                        </td>
                        <td class="py-4 px-6 text-right font-semibold">
                            ฿${netTotal.toFixed(2)}
                        </td>
                        <td class="py-4 px-6">
                            ${new Date(sale.doc_date).toLocaleDateString('th-TH', {
                                year: 'numeric', month: 'short', day: 'numeric',
                                hour: '2-digit', minute: '2-digit'
                            })}
                        </td>
                        <td class="py-4 px-6">
                            ${sale.branch || '-'}
                        </td>
                        <td class="py-4 px-6">
                            ${sale.staff || '-'}
                        </td>
                    `;
                    salesTableBody.appendChild(row);
                });
            } catch (error) {
                console.error('Error fetching sales data:', error);
                salesTableBody.innerHTML = `
                    <tr>
                        <td colspan="8" class="text-center py-4 text-red-500">
                            ไม่สามารถโหลดข้อมูลได้: ${error.message} <br> กรุณาตรวจสอบ Console สำหรับรายละเอียดเพิ่มเติม.
                        </td>
                    </tr>
                `;
                errorIndicator.classList.remove('hidden');
            } finally {
                spinner.classList.add('hidden'); // Hide the spinner
                loadingIndicator.classList.add('hidden');
            }
        }

        // Initial load
        fetchLatestSales();

        // Refresh button event listener
        refreshButton.addEventListener('click', fetchLatestSales);
    </script>
</body>

</html>