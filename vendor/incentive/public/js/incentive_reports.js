// public/js/incentive_reports.js

document.addEventListener('DOMContentLoaded', () => {
    const runCalculationBtn = document.getElementById('runCalculationBtn');
    const exportExcelBtn = document.getElementById('exportExcelBtn');
    const incentiveTableBody = document.getElementById('incentiveTableBody');
    const incentiveTableContainer = document.getElementById('incentiveTableContainer');
    const totalPayoutCurrentMonthSpan = document.getElementById('totalPayoutCurrentMonth');
    const totalSalesCurrentMonthSpan = document.getElementById('totalSalesCurrentMonth');
    const lastCalculationDateSpan = document.getElementById('lastCalculationDate');

    function toggleLoader(show) {
        const loaderId = 'calculationLoader';
        let loader = document.getElementById(loaderId);

        if (show) {
            if (!loader) {
                loader = document.createElement('div');
                loader.id = loaderId;
                loader.className = 'fixed inset-0 bg-gray-600 bg-opacity-75 flex items-center justify-center z-50';
                loader.innerHTML = `
                    <div class="flex flex-col items-center">
                        <div class="loader ease-linear rounded-full border-8 border-t-8 border-gray-200 h-32 w-32 mb-4"></div>
                        <p class="text-white text-xl font-semibold">กำลังเรียกใช้การคำนวณ Incentive... โปรดรอสักครู่</p>
                    </div>
                `;
                document.body.appendChild(loader);

                const style = document.createElement('style');
                style.innerHTML = `
                    .loader {
                        border-top-color: #3498db;
                        -webkit-animation: spin 1.5s linear infinite;
                        animation: spin 1.5s linear infinite;
                    }
                    @-webkit-keyframes spin {
                        0% { -webkit-transform: rotate(0deg); }
                        100% { -webkit-transform: rotate(360deg); }
                    }
                    @keyframes spin {
                        0% { transform: rotate(0deg); }
                        100% { transform: rotate(360deg); }
                    }
                `;
                document.head.appendChild(style);
            }
            loader.classList.remove('hidden');
        } else {
            if (loader) {
                loader.classList.add('hidden');
            }
        }
    }

    async function fetchDataFromApi(url) {
        console.log(`[fetchDataFromApi] Fetching data from: ${url}`);
        try {
            const response = await fetch(url);
            if (!response.ok) {
                const errorText = await response.text();
                console.error(`[fetchDataFromApi] API fetch failed for ${url}: HTTP Status ${response.status}`, errorText);
                throw new Error(`HTTP error! status: ${response.status}, message: ${errorText}`);
            }
            const data = await response.json();
            if (!data.success) {
                console.error(`[fetchDataFromApi] API response indicates failure for ${url}:`, data.message);
                throw new Error(data.message || 'Unknown API error');
            }
            console.log(`[fetchDataFromApi] Successfully fetched data from: ${url}`, data);
            return data;
        } catch (error) {
            console.error(`[fetchDataFromApi] Error during API fetch for ${url}:`, error);
            throw error; // Re-throw to be caught by the calling function's catch block
        }
    }

    async function fetchAndDisplayReport() {
        console.log('[fetchAndDisplayReport] Starting fetchAndDisplayReport function...');
        toggleLoader(true);
        incentiveTableContainer.classList.add('hidden');
        incentiveTableBody.innerHTML = '';
        totalPayoutCurrentMonthSpan.textContent = '0.00';
        totalSalesCurrentMonthSpan.textContent = '0.00';
        lastCalculationDateSpan.textContent = 'N/A';

        try {
            const today = new Date();
            const currentMonthStart = new Date(today.getFullYear(), today.getMonth(), 1).toISOString().slice(0, 10);
            const currentMonthEnd = new Date(today.getFullYear(), today.getMonth() + 1, 0).toISOString().slice(0, 10);
            const currentMonth = today.getMonth() + 1;
            const currentYear = today.getFullYear();

            // 0. Fetch Incentive Rules and Tiers
            const rulesApiUrl = 'api/api_get_incentive_rules.php';
            console.log(`[fetchAndDisplayReport] Calling fetchDataFromApi for rules: ${rulesApiUrl}`);
            const rulesResponse = await fetchDataFromApi(rulesApiUrl);
            console.log('[fetchAndDisplayReport] Incentive Rules Response received:', rulesResponse); // LOGGING THE FULL RESPONSE

            const incentiveRulesConfig = {};
            // Check if rulesResponse.rules exists before iterating
            if (!rulesResponse.rules) {
                throw new Error("Incentive Rules API response is missing 'rules' property.");
            }
            rulesResponse.rules.forEach(rule => {
                incentiveRulesConfig[rule.rule_key] = rule.rule_value;
            });

            // Check if rulesResponse.tiers exists
            if (!rulesResponse.tiers) {
                throw new Error("Incentive Rules API response is missing 'tiers' property.");
            }
            const incentiveTiers = rulesResponse.tiers;

            // Check if rulesResponse.per_bill_rules exists
            if (!rulesResponse.per_bill_rules) {
                throw new Error("Incentive Rules API response is missing 'per_bill_rules' property.");
            }
            const perBillIncentiveRules = {};
            rulesResponse.per_bill_rules.forEach(rule => {
                perBillIncentiveRules[rule.rule_key] = rule.rule_value;
            });

            incentiveTiers.sort((a, b) => b.min_percentage_of_target - a.min_percentage_of_target);

            const defaultBaseRate = incentiveRulesConfig.default_base_rate ?? 0.025;
            const productIncentiveRate = incentiveRulesConfig.product_incentive_rate ?? 0.005;
            const productIncentiveMinSales = incentiveRulesConfig.product_incentive_min_sales ?? 100000;
            const noSalesNotes = incentiveRulesConfig.no_sales_notes ?? 'ไม่มียอดขายในงวดนี้';

            const perBillThreshold = perBillIncentiveRules.per_bill_threshold ?? 0;
            const perBillFixedAmount = perBillIncentiveRules.per_bill_fixed_amount ?? 0;
            const perBillPercentageRate = perBillIncentiveRules.per_bill_percentage_rate ?? 0;
            const perBillType = perBillIncentiveRules.per_bill_type ?? 'fixed';
            console.log('[fetchAndDisplayReport] Incentive Rules Configured:', { defaultBaseRate, productIncentiveRate, productIncentiveMinSales, noSalesNotes, perBillThreshold, perBillFixedAmount, perBillPercentageRate, perBillType });


            // 1. Fetch Sales Representatives
            const salesRepsApiUrl = 'api/api_get_sales_reps.php?limit=9999';
            console.log(`[fetchAndDisplayReport] Calling fetchDataFromApi for sales reps: ${salesRepsApiUrl}`);
            const salesRepsResponse = await fetchDataFromApi(salesRepsApiUrl);
            console.log('[fetchAndDisplayReport] Sales Reps Response received:', salesRepsResponse); // LOGGING THE FULL RESPONSE

            // Check if salesRepsResponse.data exists before iterating
            if (!salesRepsResponse.data) {
                throw new Error("Sales Reps API response is missing 'data' property.");
            }
            const salesReps = {};
            salesRepsResponse.data.forEach(rep => {
                salesReps[rep.user_code] = rep;
            });
            console.log('[fetchAndDisplayReport] Processed Sales Reps:', salesReps);


            // 2. Fetch Sales Data for the Period
            const salesDataApiUrl = `api/api_get_sales_report.php?start_date=${currentMonthStart}&end_date=${currentMonthEnd}`;
            console.log(`[fetchAndDisplayReport] Calling fetchDataFromApi for sales data: ${salesDataApiUrl}`);
            const salesDataResponse = await fetchDataFromApi(salesDataApiUrl);
            console.log('[fetchAndDisplayReport] Sales Data Response received:', salesDataResponse); // LOGGING THE FULL RESPONSE

            // Check if salesDataResponse.data exists
            if (!salesDataResponse.data) {
                throw new Error("Sales Data API response is missing 'data' property.");
            }
            const salesData = salesDataResponse.data;
            console.log('[fetchAndDisplayReport] Processed Sales Data:', salesData);


            const salesByRepAndBill = {};
            salesData.forEach(sale => {
                if (!salesByRepAndBill[sale.user_code]) {
                    salesByRepAndBill[sale.user_code] = { total_sales: 0, bills: {} };
                }
                salesByRepAndBill[sale.user_code].total_sales += sale.net_sale_amount;
                if (!salesByRepAndBill[sale.user_code].bills[sale.doc_no]) {
                    salesByRepAndBill[sale.user_code].bills[sale.doc_no] = [];
                }
                salesByRepAndBill[sale.user_code].bills[sale.doc_no].push(sale);
            });
            console.log('[fetchAndDisplayReport] Sales by Rep and Bill (grouped):', salesByRepAndBill);

            // 3. Fetch Employee Targets for the Period
            const employeeTargetsApiUrl = `api/api_get_employee_targets.php?month=${currentMonth}&year=${currentYear}`;
            console.log(`[fetchAndDisplayReport] Calling fetchDataFromApi for employee targets: ${employeeTargetsApiUrl}`);
            const employeeTargetsResponse = await fetchDataFromApi(employeeTargetsApiUrl);
            console.log('[fetchAndDisplayReport] Employee Targets Response received:', employeeTargetsResponse); // LOGGING THE FULL RESPONSE

            // Check if employeeTargetsResponse.data exists
            if (!employeeTargetsResponse.data) {
                throw new Error("Employee Targets API response is missing 'data' property.");
            }
            const employeeTargets = {};
            employeeTargetsResponse.data.forEach(target => {
                employeeTargets[target.user_code] = target.target_amount;
            });
            console.log('[fetchAndDisplayReport] Processed Employee Targets:', employeeTargets);

            // --- Client-side Incentive Calculation and Rendering ---
            let totalIncentivePayoutOverall = 0;
            let totalSalesOverall = 0;
            const detailedIncentives = [];
            console.log('[fetchAndDisplayReport] Starting client-side incentive calculation...');

            for (const userCode in salesReps) {
                const repName = salesReps[userCode].user_name;
                const actualSales = salesByRepAndBill[userCode]?.total_sales ?? 0;
                const targetAmount = employeeTargets[userCode] ?? 0;

                let baseIncentive = 0;
                let productIncentive = 0;
                let totalIncentive = 0;
                let notes = '';
                let status = 'Calculated';
                let targetAchievementPercent = 0;

                if (actualSales > 0) {
                    targetAchievementPercent = (targetAmount > 0) ? (actualSales / targetAmount) * 100 : 0;
                    let matchedTier = null;
                    if (incentiveTiers.length > 0) {
                        for (const tier of incentiveTiers) {
                            if (targetAchievementPercent >= tier.min_percentage_of_target) {
                                matchedTier = tier;
                                break;
                            }
                        }
                    }

                    let selectedBaseRate = defaultBaseRate;
                    if (matchedTier) {
                        selectedBaseRate = matchedTier.base_rate;
                        notes = matchedTier.notes;
                    } else if (targetAmount > 0 && incentiveTiers.length > 0 && targetAchievementPercent < incentiveTiers[incentiveTiers.length - 1].min_percentage_of_target) {
                        notes = `ไม่ถึงเป้าหมาย (${targetAchievementPercent.toFixed(2)}%)`;
                    } else if (targetAmount === 0) {
                        notes = "ไม่มีเป้าหมาย";
                    }

                    baseIncentive = actualSales * selectedBaseRate;

                    if (actualSales >= productIncentiveMinSales) {
                        productIncentive = actualSales * productIncentiveRate;
                    }

                    totalIncentive = baseIncentive + productIncentive;
                } else {
                    notes = noSalesNotes;
                    status = 'No Sales';
                }

                // Per-Bill Incentive Calculation (Client-side)
                let perBillIncentive = 0;
                const billsForRep = salesByRepAndBill[userCode]?.bills ?? {};

                for (const docNo in billsForRep) {
                    const billLineItems = billsForRep[docNo];
                    const billTotal = billLineItems.reduce((sum, item) => sum + item.net_sale_amount, 0);

                    if (billTotal >= perBillThreshold) {
                        if (perBillType === 'fixed') {
                            perBillIncentive += perBillFixedAmount;
                            notes += (notes !== '' ? '; ' : '') + `Bill ${docNo} (฿${billTotal.toFixed(2)}): Fixed incentive ฿${perBillFixedAmount.toFixed(2)}`;
                        } else if (perBillType === 'percentage') {
                            const incentiveFromBill = billTotal * (perBillPercentageRate / 100);
                            perBillIncentive += incentiveFromBill;
                            notes += (notes !== '' ? '; ' : '') + `Bill ${docNo} (฿${billTotal.toFixed(2)}): Percentage incentive ฿${incentiveFromBill.toFixed(2)} (${perBillPercentageRate}%)`;
                        }
                    }
                }
                totalIncentive += perBillIncentive;


                detailedIncentives.push({
                    sales_rep_name: repName,
                    period: `${currentYear}-${String(currentMonth).padStart(2, '0')}`,
                    actual_sales: actualSales,
                    target_sales: targetAmount,
                    target_achievement_percent: targetAchievementPercent,
                    base_incentive: baseIncentive,
                    product_incentive: productIncentive,
                    total_incentive: totalIncentive,
                    status: status,
                    notes: notes
                });

                totalIncentivePayoutOverall += totalIncentive;
                totalSalesOverall += actualSales;
            }
            console.log('[fetchAndDisplayReport] Detailed Incentives Calculated:', detailedIncentives);

            // Render table rows
            detailedIncentives.forEach(incentive => {
                const row = incentiveTableBody.insertRow();
                let statusClass = '';
                switch (incentive.status) {
                    case 'Calculated':
                        statusClass = 'bg-green-100 text-green-700';
                        break;
                    case 'No Sales':
                        statusClass = 'bg-red-100 text-red-700';
                        break;
                    default:
                        statusClass = 'bg-yellow-100 text-yellow-700';
                        break;
                }

                row.innerHTML = `
                    <td class="px-5 py-4 border-b border-gray-200 bg-white text-sm">
                        <p class="text-gray-800 whitespace-no-wrap">${incentive.sales_rep_name}</p>
                    </td>
                    <td class="px-5 py-4 border-b border-gray-200 bg-white text-sm">
                        <p class="text-gray-800 whitespace-no-wrap">${incentive.period}</p>
                    </td>
                    <td class="px-5 py-4 border-b border-gray-200 bg-white text-sm text-right">
                        <p class="text-gray-600 whitespace-no-wrap">฿ ${incentive.actual_sales.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</p>
                    </td>
                    <td class="px-5 py-4 border-b border-gray-200 bg-white text-sm text-right">
                        <p class="text-gray-600 whitespace-no-wrap">฿ ${incentive.target_sales.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</p>
                    </td>
                    <td class="px-5 py-4 border-b border-gray-200 bg-white text-sm text-right">
                        <p class="text-gray-600 whitespace-no-wrap">${incentive.target_achievement_percent.toFixed(2)} %</p>
                    </td>
                    <td class="px-5 py-4 border-b border-gray-200 bg-white text-sm text-right">
                        <p class="text-gray-600 whitespace-no-wrap">฿ ${incentive.base_incentive.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</p>
                    </td>
                    <td class="px-5 py-4 border-b border-gray-200 bg-white text-sm text-right">
                        <p class="text-gray-600 whitespace-no-wrap">฿ ${incentive.product_incentive.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</p>
                    </td>
                    <td class="px-5 py-4 border-b border-gray-200 bg-white text-sm text-right">
                        <p class="text-gray-600 whitespace-no-wrap">฿ ${incentive.total_incentive.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</p>
                    </td>
                    <td class="px-5 py-4 border-b border-gray-200 bg-white text-sm">
                        <span class="relative inline-block px-3 py-1 font-medium leading-tight ${statusClass} rounded-full">
                            <span class="absolute inset-0 opacity-50 rounded-full"></span>
                            <span class="relative">${incentive.status}</span>
                        </span>
                    </td>
                    <td class="px-5 py-4 border-b border-gray-200 bg-white text-sm text-right">
                        <p class="text-gray-600 whitespace-no-wrap">${incentive.notes}</p>
                    </td>
                `;
            });

            // Update summary cards
            totalPayoutCurrentMonthSpan.textContent = totalIncentivePayoutOverall.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            totalSalesCurrentMonthSpan.textContent = totalSalesOverall.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            lastCalculationDateSpan.textContent = new Date().toISOString().slice(0, 10);
            console.log('[fetchAndDisplayReport] Summary Cards Updated:', { totalIncentivePayoutOverall, totalSalesOverall, lastCalculationDate: lastCalculationDateSpan.textContent });

            incentiveTableContainer.classList.remove('hidden');
            console.log('[fetchAndDisplayReport] Incentive table container is now visible.');

        } catch (error) {
            console.error('[fetchAndDisplayReport] Caught error in main function:', error);
            alert('เกิดข้อผิดพลาดในการโหลดข้อมูลรายงาน: ' + error.message);
        } finally {
            toggleLoader(false);
            console.log('[fetchAndDisplayReport] fetchAndDisplayReport function finished.');
        }
    }

    if (runCalculationBtn) {
        runCalculationBtn.addEventListener('click', async () => {
            console.log('[Run Calculation Button] clicked.');
            toggleLoader(true);
            try {
                console.log('[Run Calculation Button] Calling calculate_incentive.php...');
                const response = await fetch('api/calculate_incentive.php', { method: 'GET' });
                if (!response.ok) {
                    const errorData = await response.json(); // Attempt to parse JSON error
                    console.error('[Run Calculation Button] Server-side calculation API failed:', errorData);
                    throw new Error(errorData.message || `HTTP error! status: ${response.status}`);
                }
                const result = await response.json();
                console.log('[Run Calculation Button] Server-side calculation result:', result);
                if (result.status === 'success') {
                    alert('คำนวณ Incentive สำเร็จ! กำลังโหลดรายงาน...');
                    await fetchAndDisplayReport();
                } else {
                    throw new Error(result.message || 'Server calculation failed.');
                }
            } catch (error) {
                console.error('[Run Calculation Button] Failed to run incentive calculation:', error);
                alert('เกิดข้อผิดพลาดในการคำนวณ Incentive: ' + error.message);
            } finally {
                toggleLoader(false);
                console.log('[Run Calculation Button] handler finished.');
            }
        });
    }

    if (exportExcelBtn) {
        exportExcelBtn.addEventListener('click', () => {
            console.log('[Export Excel Button] clicked.');
            let csv = [];
            const rows = document.querySelectorAll('#incentiveTable thead tr, #incentiveTable tbody tr');

            for (let i = 0; i < rows.length; i++) {
                const row = rows[i];
                const cols = row.querySelectorAll('td, th');
                let rowData = [];

                for (let j = 0; j < cols.length; j++) {
                    let data = cols[j].innerText.replace(/(\r\n|\n|\r)/gm, '').replace(/(\s\s)/gm, ' ');
                    data = data.replace(/"/g, '""');
                    data = data.replace('฿ ', '').replace(/,/g, '');
                    rowData.push(`"${data}"`);
                }
                csv.push(rowData.join(','));
            }

            const csvString = csv.join('\n');
            const blob = new Blob([csvString], { type: 'text/csv;charset=utf-8;' });
            const url = URL.createObjectURL(blob);
            const link = document.createElement('a');
            link.setAttribute('href', url);
            link.setAttribute('download', 'incentive_report.csv');
            link.style.visibility = 'hidden';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
            console.log('[Export Excel Button] CSV Export triggered.');
        });
    }

    incentiveTableContainer.classList.add('hidden');
    totalPayoutCurrentMonthSpan.textContent = '0.00';
    totalSalesCurrentMonthSpan.textContent = '0.00';
    lastCalculationDateSpan.textContent = 'N/A';
    console.log('[DOMContentLoaded] Initial state set: table hidden, summaries reset.');
});