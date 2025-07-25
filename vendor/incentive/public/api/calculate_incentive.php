// public/api/calculate_incentive.php
<?php
// public/api/calculate_incentive.php

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *'); // Allow CORS for development
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// --- Database Connection (Using dblocal.php as per your other APIs) ---
require_once 'dblocal.php'; // Adjust path if necessary

if (!isset($conn) || $conn === false) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Database connection failed. Check dblocal.php.']);
    exit();
}

// --- Helper function to fetch data from external API ---
function fetchDataFromApi($url, $method = 'GET', $data = null) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_TIMEOUT, 300); // 5 minutes timeout

    if ($method === 'POST' && $data) {
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    }

    $response = curl_exec($ch);
    if (curl_errno($ch)) {
        throw new Exception("cURL error: " . curl_error($ch));
    }
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode >= 400) {
        $errorData = json_decode($response, true);
        throw new Exception("API request failed with status " . $httpCode . ": " . ($errorData['message'] ?? 'Unknown error response from API'));
    }

    $data = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception("Failed to decode JSON from API: " . json_last_error_msg() . " Raw response: " . $response);
    }
    return $data;
}

// --- Get current period (or from request parameters if needed) ---
$currentMonth = date('n'); // Current month (1-12)
$currentYear = date('Y');   // Current year
$currentDate = date('Y-m-d'); // Current date for target date range check

// Optional: Allow specifying month/year from request (e.g., /api/calculate_incentive.php?month=1&year=2024)
if (isset($_GET['month']) && isset($_GET['year'])) {
    $inputMonth = (int)$_GET['month'];
    $inputYear = (int)$_GET['year'];
    // Basic validation for month/year
    if ($inputMonth >= 1 && $inputMonth <= 12 && $inputYear >= 2000 && $inputYear <= (date('Y') + 1)) {
        $currentMonth = $inputMonth;
        $currentYear = $inputYear;
    }
}
$periodStartDate = date('Y-m-01', strtotime("$currentYear-$currentMonth-01"));
$periodEndDate = date('Y-m-t', strtotime("$currentYear-$currentMonth-01"));


// Start SQL Server Transaction
if (sqlsrv_begin_transaction($conn) === false) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Failed to begin database transaction.']);
    exit();
}

try {
    // --- 0. Fetch Incentive Rules and Tiers ---
    // Make sure this URL is correct for your api_get_incentive_rules.php
    $rulesApiUrl = 'http://localhost/incentive/public/api/api_get_incentive_rules.php';
    $rulesResponse = fetchDataFromApi($rulesApiUrl);
    
    if (!$rulesResponse['success']) {
        throw new Exception("Failed to fetch incentive rules: " . ($rulesResponse['message'] ?? 'Unknown error from rules API'));
    }
    
    $incentiveRulesConfig = [];
    foreach ($rulesResponse['rules'] as $rule) {
        $incentiveRulesConfig[$rule['rule_key']] = $rule['rule_value'];
    }
    $incentiveTiers = $rulesResponse['tiers'];
    $perBillIncentiveRules = [];
    foreach ($rulesResponse['per_bill_rules'] as $rule) {
        $perBillIncentiveRules[$rule['rule_key']] = $rule['rule_value'];
    }

    // Sort tiers by min_percentage_of_target in descending order to find the highest matching tier first
    usort($incentiveTiers, function ($a, $b) {
        return $b['min_percentage_of_target'] <=> $a['min_percentage_of_target'];
    });

    // Default values if rules not found or to be used as fallback
    $defaultBaseRate = $incentiveRulesConfig['default_base_rate'] ?? 0.025;
    $productIncentiveRate = $incentiveRulesConfig['product_incentive_rate'] ?? 0.005;
    $productIncentiveMinSales = $incentiveRulesConfig['product_incentive_min_sales'] ?? 100000;
    $noSalesNotes = $incentiveRulesConfig['no_sales_notes'] ?? 'ไม่มียอดขายในงวดนี้';

    // Per-bill incentive rule values
    $perBillThreshold = $perBillIncentiveRules['per_bill_threshold'] ?? 0;
    $perBillFixedAmount = $perBillIncentiveRules['per_bill_fixed_amount'] ?? 0;
    $perBillPercentageRate = $perBillIncentiveRules['per_bill_percentage_rate'] ?? 0;
    $perBillType = $perBillIncentiveRules['per_bill_type'] ?? 'fixed';


    // --- 1. Fetch Sales Representatives ---
    // Adjust URL as needed, ensure limit is high enough or implement pagination here if too many reps
    $salesRepsApiUrl = 'http://localhost/incentive/public/api/api_get_sales_reps.php?limit=9999';
    $salesRepsResponse = fetchDataFromApi($salesRepsApiUrl);

    if (!$salesRepsResponse['success']) {
        throw new Exception("Failed to fetch sales reps: " . ($salesRepsResponse['message'] ?? 'Unknown error from sales reps API'));
    }

    $salesReps = [];
    foreach ($salesRepsResponse['data'] as $rep) {
        $salesReps[$rep['user_code']] = $rep['user_name'];
    }

    // --- 2. Fetch Sales Data for the Period ---
    // Make sure this URL is correct for your api_get_sales_report.php
    $salesDataApiUrl = "http://localhost/incentive/public/api/api_get_sales_report.php?start_date={$periodStartDate}&end_date={$periodEndDate}";
    $salesDataResponse = fetchDataFromApi($salesDataApiUrl);

    if (!$salesDataResponse['success']) {
        throw new Exception("Failed to fetch sales data: " . ($salesDataResponse['message'] ?? 'Unknown error from sales data API'));
    }

    $salesData = $salesDataResponse['data'];

    // Group sales data by sales rep and then by document number (bill)
    $salesByRepAndBill = [];
    foreach ($salesData as $sale) {
        $salesByRepAndBill[$sale['user_code']]['total_sales'] = ($salesByRepAndBill[$sale['user_code']]['total_sales'] ?? 0) + $sale['net_sale_amount'];
        $salesByRepAndBill[$sale['user_code']]['bills'][$sale['doc_no']][] = $sale; // Store full sale data per bill
    }

    // --- 3. Fetch Employee Targets for the Period ---
    // This API call needs to be done for the specified period.
    $employeeTargetsApiUrl = "http://localhost/incentive/public/api/api_get_employee_targets.php?month={$currentMonth}&year={$currentYear}";
    $employeeTargetsResponse = fetchDataFromApi($employeeTargetsApiUrl);

    if (!$employeeTargetsResponse['success']) {
        throw new Exception("Failed to fetch employee targets: " . ($employeeTargetsResponse['message'] ?? 'Unknown error from employee targets API'));
    }

    $employeeTargets = [];
    foreach ($employeeTargetsResponse['data'] as $target) {
        $employeeTargets[$target['user_code']] = $target['target_amount'];
    }

    // --- 4. Calculate and Save Incentives for Each Sales Representative ---
    foreach ($salesReps as $userCode => $repName) {
        $actualSales = $salesByRepAndBill[$userCode]['total_sales'] ?? 0;
        $targetAmount = $employeeTargets[$userCode] ?? 0; // Default to 0 if no target set

        $employeeTargetResponse = $employeeTargets[$userCode] ?? null;
        if ($targetAmount === null) {
            error_log("No target found for {$userCode}: " . ($employeeTargetResponse['message'] ?? 'No message'));
        }

        $baseIncentive = 0;
        $productIncentive = 0;
        $totalIncentive = 0;
        $notes = '';
        $status = 'Calculated';
        $targetAchievementPercent = 0;

        // --- Incentive Calculation Logic (Existing) ---
        if ($actualSales > 0) {
            $targetAchievementPercent = ($targetAmount > 0) ? ($actualSales / $targetAmount) * 100 : 0;
            $matchedTier = null;
            if (!empty($incentiveTiers)) {
                foreach ($incentiveTiers as $tier) {
                    if ($targetAchievementPercent >= $tier['min_percentage_of_target']) {
                        $matchedTier = $tier;
                        break; // Found the highest matching tier
                    }
                }
            }

            $selectedBaseRate = $defaultBaseRate;
            if ($matchedTier) {
                $selectedBaseRate = $matchedTier['base_rate'];
                $notes = $matchedTier['notes'];
            } else if ($targetAmount > 0 && $targetAchievementPercent < $incentiveTiers[count($incentiveTiers)-1]['min_percentage_of_target']) {
                $notes = "ไม่ถึงเป้าหมาย ($targetAchievementPercent%)";
            } else if ($targetAmount == 0) {
                 $notes = "ไม่มีเป้าหมาย";
            }

            $baseIncentive = $actualSales * $selectedBaseRate;

            // Product Incentive calculation (existing)
            if ($actualSales >= $productIncentiveMinSales) {
                // Assuming product incentive applies to total actual sales once minimum is met
                $productIncentive = $actualSales * $productIncentiveRate;
            }

            $totalIncentive = $baseIncentive + $productIncentive;
        } else {
            $notes = $noSalesNotes;
            $status = 'No Sales';
        }

        // --- New: Per-Bill Incentive Calculation ---
        $perBillIncentive = 0;
        $billsForRep = $salesByRepAndBill[$userCode]['bills'] ?? [];

        foreach ($billsForRep as $docNo => $billLineItems) {
            $billTotal = array_sum(array_column($billLineItems, 'net_sale_amount'));

            if ($billTotal >= $perBillThreshold) {
                if ($perBillType === 'fixed') {
                    $perBillIncentive += $perBillFixedAmount;
                    $notes .= (!empty($notes) ? '; ' : '') . "Bill {$docNo} (฿{$billTotal}): Fixed incentive ฿{$perBillFixedAmount}";
                } elseif ($perBillType === 'percentage') {
                    $incentiveFromBill = $billTotal * ($perBillPercentageRate / 100);
                    $perBillIncentive += $incentiveFromBill;
                    $notes .= (!empty($notes) ? '; ' : '') . "Bill {$docNo} (฿{$billTotal}): Percentage incentive ฿{$incentiveFromBill} ({$perBillPercentageRate}%)";
                }
            }
        }
        $totalIncentive += $perBillIncentive;


        // --- Upsert into incentive_results ---
        $sqlUpsert = "
            MERGE INTO incentive_results AS Target
            USING (VALUES (?, ?, ?, ?)) AS Source (sales_rep_code, period_month, period_year, created_at)
            ON Target.sales_rep_code = Source.sales_rep_code
            AND Target.period_month = Source.period_month
            AND Target.period_year = Source.period_year
            WHEN MATCHED THEN
                UPDATE SET
                    sales_rep_name = ?,
                    target_sales = ?,
                    actual_sales = ?,
                    target_achievement_percent = ?,
                    base_incentive = ?,
                    product_incentive = ?,
                    incentive_amount = ?,
                    status = ?,
                    notes = ?,
                    updated_at = GETDATE()
            WHEN NOT MATCHED THEN
                INSERT (sales_rep_code, sales_rep_name, period_month, period_year, target_sales, actual_sales, target_achievement_percent, base_incentive, product_incentive, incentive_amount, status, notes, created_at, updated_at)
                VALUES (Source.sales_rep_code, ?, Source.period_month, Source.period_year, ?, ?, ?, ?, ?, ?, ?, ?, GETDATE(), GETDATE());
        ";

        // Prepare parameters for upsert
        $paramsUpsert = [
            $userCode,
            $currentMonth,
            $currentYear,
            $currentDate, // For MERGE WHEN NOT MATCHED (created_at)
            $repName, // sales_rep_name
            round($targetAmount, 2), // target_sales
            round($actualSales, 2), // actual_sales
            round($targetAchievementPercent, 2), // target_achievement_percent
            round($baseIncentive, 2), // base_incentive
            round($productIncentive, 2), // product_incentive
            round($totalIncentive, 2), // incentive_amount (total)
            $status, // status
            $notes, // notes
            // Parameters for INSERT part
            $repName, // sales_rep_name
            round($targetAmount, 2), // target_sales
            round($actualSales, 2), // actual_sales
            round($targetAchievementPercent, 2), // target_achievement_percent
            round($baseIncentive, 2), // base_incentive
            round($productIncentive, 2), // product_incentive
            round($totalIncentive, 2), // incentive_amount (total)
            $status, // status
            $notes // notes
        ];

        $stmtUpsert = sqlsrv_query($conn, $sqlUpsert, $paramsUpsert);

        if ($stmtUpsert === false) {
            throw new Exception("Failed to upsert incentive result for {$repName}: " . print_r(sqlsrv_errors(), true));
        }
        sqlsrv_free_stmt($stmtUpsert); // Free the statement for the next iteration
    }

    // Commit the transaction if all operations were successful
    sqlsrv_commit($conn);
    echo json_encode(['status' => 'success', 'message' => 'Incentive calculation completed and saved.']);

} catch (Exception $e) {
    // Rollback the transaction on error
    if (isset($conn) && $conn !== false) {
        sqlsrv_rollback($conn);
    }
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Incentive calculation failed: ' . $e->getMessage()]);
} finally {
    if (isset($conn) && $conn !== false) {
        sqlsrv_close($conn);
    }
}