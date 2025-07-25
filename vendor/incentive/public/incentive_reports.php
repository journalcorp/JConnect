<?php
// public/incentive_reports.php

// (สมมติว่ามีการตรวจสอบสิทธิ์ผู้จัดการแล้ว)

// --- Helper function to fetch data from API (kept for completeness if needed by other parts not directly for initial display) ---
function fetchDataFromApi($url) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_TIMEOUT, 300); // 5 minutes timeout for potentially large data
    $response = curl_exec($ch);
    if (curl_errno($ch)) {
        throw new Exception("cURL error: " . curl_error($ch));
    }
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode >= 400) {
        // Attempt to decode JSON for error message, fallback to generic
        $errorData = json_decode($response, true);
        throw new Exception("API request failed with status " . $httpCode . ": " . ($errorData['message'] ?? 'Unknown error'));
    }

    $data = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception("Failed to decode JSON from API: " . json_last_error_msg());
    }
    return $data;
}

// Initialize all data arrays as empty so the page loads with an empty table
// The JavaScript will handle fetching and populating these when the calculation button is clicked.
$salesReps = [];
$allSalesData = [];
$employeeTargets = [];
$incentiveRules = [];
$incentiveTiers = [];
$detailedIncentives = []; // This will be empty on initial load
$incentiveSummary = [
    'total_payout_current_month' => 0,
    'total_sales_current_month' => 0,
    'last_calculation_date' => 'N/A',
    'next_calculation_due' => date('Y-m-d', strtotime('first day of next month')) // This can still be calculated in PHP for initial display
];

// PHP logic for fetching and calculating detailed incentives and summary is removed here.
// This logic is now handled by incentive_reports.js after the "Run Calculation" button is pressed.
?>
<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>รายงาน Incentive - ระบบ Incentive</title>
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
    <style>
        .loader {
            border-top-color: #3498db;
            -webkit-animation: spin 1.5s linear infinite;
            animation: spin 1.5s linear infinite;
        }

        @-webkit-keyframes spin {
            0% {
                -webkit-transform: rotate(0deg);
            }

            100% {
                -webkit-transform: rotate(360deg);
            }
        }

        @keyframes spin {
            0% {
                transform: rotate(0deg);
            }

            100% {
                transform: rotate(360deg);
            }
        }
    </style>
</head>

<body class="bg-gray-50 font-sans leading-normal tracking-normal">
    <div class="min-h-screen flex flex-col">
        <nav class="bg-indigo-700 p-4 text-white shadow-md">
            <div class="container mx-auto flex justify-between items-center">
                <a href="#" class="text-2xl font-bold">ระบบ Incentive</a>
                <div class="flex space-x-4">
                    <a href="incentive_settings.php" class="hover:underline">ตั้งค่า Incentive</a>
                    <a href="incentive_reports.php" class="hover:underline">รายงาน Incentive</a>
                </div>
            </div>
        </nav>

        <div class="flex-1 p-8 bg-gray-50">
            <div class="max-w-7xl mx-auto">
                <h1 class="text-3xl font-bold text-gray-800 mb-6">รายงาน Incentive พนักงานขาย</h1>

                <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-200 mb-8 flex justify-between items-center">
                    <h2 class="text-xl font-semibold text-gray-700">เรียกใช้และ Export รายงาน</h2>
                    <div class="flex space-x-3">
                        <button id="runCalculationBtn"
                            class="bg-indigo-600 hover:bg-indigo-700 text-white font-medium py-2 px-4 rounded-lg shadow-sm transition duration-200 flex items-center">
                            <i class="fas fa-calculator mr-2"></i> เรียกใช้การคำนวณ Incentive
                        </button>
                        <button id="exportExcelBtn"
                            class="bg-green-600 hover:bg-green-700 text-white font-medium py-2 px-4 rounded-lg shadow-sm transition duration-200 flex items-center">
                            <i class="fas fa-file-excel mr-2"></i> Export to Excel
                        </button>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mb-8">
                    <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-200">
                        <h2 class="text-lg font-semibold text-gray-600 mb-2">ยอดรวม Incentive (เดือนปัจจุบัน)</h2>
                        <p class="text-3xl font-bold text-indigo-600">฿<span id="totalPayoutCurrentMonth">
                                <?php echo number_format($incentiveSummary['total_payout_current_month'], 2); ?></span></p>
                    </div>
                    <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-200">
                        <h2 class="text-lg font-semibold text-gray-600 mb-2">ยอดขายรวม (เดือนปัจจุบัน)</h2>
                        <p class="text-3xl font-bold text-green-600">฿<span id="totalSalesCurrentMonth">
                                <?php echo number_format($incentiveSummary['total_sales_current_month'], 2); ?></span></p>
                    </div>
                    <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-200">
                        <h2 class="text-lg font-semibold text-gray-600 mb-2">อัปเดตล่าสุด</h2>
                        <p class="text-xl font-semibold text-gray-700" id="lastCalculationDate">
                            <?php echo htmlspecialchars($incentiveSummary['last_calculation_date']); ?></p>
                        <p class="text-sm text-gray-500">คำนวณครั้งถัดไป: <span id="nextCalculationDue">
                                <?php echo htmlspecialchars($incentiveSummary['next_calculation_due']); ?></span></p>
                    </div>
                </div>

                <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-200" id="incentiveTableContainer">
                    <h2 class="text-xl font-semibold text-gray-700 mb-4">รายละเอียด Incentive</h2>
                    <div class="overflow-x-auto">
                        <table class="min-w-full leading-normal" id="incentiveTable">
                            <thead>
                                <tr>
                                    <th
                                        class="px-5 py-3 border-b-2 border-gray-200 bg-gray-100 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                        พนักงานขาย
                                    </th>
                                    <th
                                        class="px-5 py-3 border-b-2 border-gray-200 bg-gray-100 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                        งวด
                                    </th>
                                    <th
                                        class="px-5 py-3 border-b-2 border-gray-200 bg-gray-100 text-right text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                        ยอดขาย (฿)
                                    </th>
                                    <th
                                        class="px-5 py-3 border-b-2 border-gray-200 bg-gray-100 text-right text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                        เป้าหมาย (฿)
                                    </th>
                                    <th
                                        class="px-5 py-3 border-b-2 border-gray-200 bg-gray-100 text-right text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                        % การบรรลุเป้า
                                    </th>
                                    <th
                                        class="px-5 py-3 border-b-2 border-gray-200 bg-gray-100 text-right text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                        Base Incentive (฿)
                                    </th>
                                    <th
                                        class="px-5 py-3 border-b-2 border-gray-200 bg-gray-100 text-right text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                        Product Incentive (฿)
                                    </th>
                                    <th
                                        class="px-5 py-3 border-b-2 border-gray-200 bg-gray-100 text-right text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                        รวม Incentive (฿)
                                    </th>
                                    <th
                                        class="px-5 py-3 border-b-2 border-gray-200 bg-gray-100 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                        สถานะ
                                    </th>
                                    <th
                                        class="px-5 py-3 border-b-2 border-gray-200 bg-gray-100 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                        หมายเหตุ
                                    </th>
                                </tr>
                            </thead>
                            <tbody id="incentiveTableBody">
                                <?php if (!empty($detailedIncentives)) : // This block will not run on initial load due to empty $detailedIncentives ?>
                                    <?php foreach ($detailedIncentives as $incentive) : ?>
                                        <tr>
                                            <td class="px-5 py-4 border-b border-gray-200 bg-white text-sm">
                                                <p class="text-gray-800 whitespace-no-wrap">
                                                    <?php echo htmlspecialchars($incentive['sales_rep_name']); ?></p>
                                            </td>
                                            <td class="px-5 py-4 border-b border-gray-200 bg-white text-sm">
                                                <p class="text-gray-800 whitespace-no-wrap">
                                                    <?php echo htmlspecialchars($incentive['period']); ?></p>
                                            </td>
                                            <td class="px-5 py-4 border-b border-gray-200 bg-white text-sm text-right">
                                                <p class="text-gray-600 whitespace-no-wrap">฿
                                                    <?php echo number_format($incentive['actual_sales'], 2); ?></p>
                                            </td>
                                            <td class="px-5 py-4 border-b border-gray-200 bg-white text-sm text-right">
                                                <p class="text-gray-600 whitespace-no-wrap">฿
                                                    <?php echo number_format($incentive['target_sales'], 2); ?></p>
                                            </td>
                                            <td class="px-5 py-4 border-b border-gray-200 bg-white text-sm text-right">
                                                <p class="text-gray-600 whitespace-no-wrap">
                                                    <?php echo number_format($incentive['target_achievement_percent'], 2); ?> %</p>
                                            </td>
                                            <td class="px-5 py-4 border-b border-gray-200 bg-white text-sm text-right">
                                                <p class="text-gray-600 whitespace-no-wrap">฿
                                                    <?php echo number_format($incentive['base_incentive'], 2); ?></p>
                                            </td>
                                            <td class="px-5 py-4 border-b border-gray-200 bg-white text-sm text-right">
                                                <p class="text-gray-600 whitespace-no-wrap">฿
                                                    <?php echo number_format($incentive['product_incentive'], 2); ?></p>
                                            </td>
                                            <td class="px-5 py-4 border-b border-gray-200 bg-white text-sm text-right">
                                                <p class="text-gray-600 whitespace-no-wrap">฿
                                                    <?php echo number_format($incentive['total_incentive'], 2); ?></p>
                                            </td>
                                            <td class="px-5 py-4 border-b border-gray-200 bg-white text-sm">
                                                <?php
                                                $statusClass = '';
                                                switch ($incentive['status']) {
                                                    case 'Calculated':
                                                        $statusClass = 'bg-green-100 text-green-700';
                                                        break;
                                                    case 'No Sales':
                                                        $statusClass = 'bg-red-100 text-red-700';
                                                        break;
                                                    default:
                                                        $statusClass = 'bg-yellow-100 text-yellow-700';
                                                        break;
                                                }
                                                ?>
                                                <span
                                                    class="relative inline-block px-3 py-1 font-medium leading-tight <?php echo $statusClass; ?> rounded-full">
                                                    <span class="absolute inset-0 opacity-50 rounded-full"></span>
                                                    <span
                                                        class="relative"><?php echo htmlspecialchars($incentive['status']); ?></span>
                                                </span>
                                            </td>
                                            <td class="px-5 py-4 border-b border-gray-200 bg-white text-sm text-right">
                                                <p class="text-gray-600 whitespace-no-wrap">
                                                    <?php echo htmlspecialchars($incentive['notes']); ?></p>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        </div>
    </div>

    <script src="js/incentive_reports.js"></script>
</body>

</html>