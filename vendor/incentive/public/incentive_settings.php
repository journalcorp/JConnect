<?php
// public/incentive_settings.php

// (สมมติว่ามีการตรวจสอบสิทธิ์ผู้จัดการแล้ว)

// หากต้องการให้หน้าโหลดได้โดยไม่ต้องใช้ API call ใน PHP หน้าแรก
// สามารถทำได้โดยให้ JS โหลดข้อมูลหลัง DOMContentLoaded
// แต่ถ้าอยาก pre-load ข้อมูลบางอย่างจาก PHP ก็ทำได้เช่นกัน

?>
<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ตั้งค่ากฎ Incentive - ระบบ Incentive</title>
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
        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(255, 255, 255, 0.8);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 1000;
        }

        .spinner {
            border: 4px solid rgba(0, 0, 0, 0.1);
            border-left-color: #3b82f6;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            to {
                transform: rotate(360deg);
            }
        }
    </style>
</head>

<body class="bg-gray-50 font-sans leading-normal tracking-normal">
    <div id="loadingOverlay" class="loading-overlay hidden">
        <div class="spinner"></div>
    </div>

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
                            class="flex items-center text-white bg-gray-700 p-3 rounded-lg transition duration-200">
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
                <h1 class="text-3xl font-semibold text-gray-800">ตั้งค่ากฎ Incentive</h1>
                <div class="flex justify-center mb-6">
                    <img src="img/logo.png" alt="Logo" class="w-[200px] h-[29px]"
                        onerror="this.style.display='none'">
                </div>
                <button id="saveRulesBtn"
                    class="bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded-lg shadow-sm transition duration-200 flex items-center">
                    <i class="fas fa-save mr-2"></i> บันทึกการตั้งค่า
                </button>
            </div>

            <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-200 mb-8">
                <h2 class="text-xl font-semibold text-gray-700 mb-4">กฎ Incentive ทั่วไป</h2>
                <div id="generalRulesContainer" class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="bg-gray-50 p-4 rounded-lg border border-gray-200">
                        <label for="default_base_rate" class="block text-sm font-medium text-gray-700">อัตรา Incentive พื้นฐานเริ่มต้น:</label>
                        <input type="number" step="0.001" id="default_base_rate" name="default_base_rate"
                            class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm p-2 focus:ring-blue-500 focus:border-blue-500 sm:text-sm"
                            placeholder="เช่น 0.025">
                        <p class="text-xs text-gray-500 mt-1">อัตรา Incentive เริ่มต้นสำหรับยอดขาย</p>
                    </div>

                    <div class="bg-gray-50 p-4 rounded-lg border border-gray-200">
                        <label for="product_incentive_rate" class="block text-sm font-medium text-gray-700">อัตรา Incentive สินค้า:</label>
                        <input type="number" step="0.001" id="product_incentive_rate" name="product_incentive_rate"
                            class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm p-2 focus:ring-blue-500 focus:border-blue-500 sm:text-sm"
                            placeholder="เช่น 0.005">
                        <p class="text-xs text-gray-500 mt-1">อัตรา Incentive เพิ่มเติมสำหรับยอดขายสินค้าบางประเภท</p>
                    </div>

                    <div class="bg-gray-50 p-4 rounded-lg border border-gray-200">
                        <label for="product_incentive_min_sales" class="block text-sm font-medium text-gray-700">ยอดขายขั้นต่ำสำหรับ Incentive สินค้า:</label>
                        <input type="number" step="1" id="product_incentive_min_sales" name="product_incentive_min_sales"
                            class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm p-2 focus:ring-blue-500 focus:border-blue-500 sm:text-sm"
                            placeholder="เช่น 100000">
                        <p class="text-xs text-gray-500 mt-1">ยอดขายขั้นต่ำที่ต้องถึงเพื่อรับ Incentive สินค้า</p>
                    </div>

                    <div class="bg-gray-50 p-4 rounded-lg border border-gray-200">
                        <label for="no_sales_notes" class="block text-sm font-medium text-gray-700">หมายเหตุเมื่อไม่มียอดขาย:</label>
                        <input type="text" id="no_sales_notes" name="no_sales_notes"
                            class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm p-2 focus:ring-blue-500 focus:border-blue-500 sm:text-sm"
                            placeholder="เช่น ไม่มียอดขายในงวดนี้">
                        <p class="text-xs text-gray-500 mt-1">ข้อความสำหรับพนักงานที่ไม่มีการขาย</p>
                    </div>
                </div>
            </div>

            <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-200 mb-8">
                <div class="flex justify-between items-center mb-4">
                    <h2 class="text-xl font-semibold text-gray-700">กฎ Incentive ตามเป้าหมาย (Target Tiers)</h2>
                    <button id="addTierBtn"
                        class="bg-green-500 hover:bg-green-600 text-white font-medium py-2 px-3 rounded-lg shadow-sm flex items-center">
                        <i class="fas fa-plus mr-2"></i> เพิ่ม Tier
                    </button>
                </div>
                <div id="targetTiersContainer" class="space-y-4">
                    <div class="bg-gray-50 p-4 rounded-lg border border-gray-200 flex flex-col md:flex-row md:items-end gap-3" data-tier-id="new">
                        <div class="flex-1">
                            <label class="block text-sm font-medium text-gray-700">ชื่อ Tier</label>
                            <input type="text" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm p-2 text-sm tier-name" placeholder="เช่น ถึงเป้าหมาย 100% ขึ้นไป">
                        </div>
                        <div class="w-24">
                            <label class="block text-sm font-medium text-gray-700">เป้าหมาย (%)</label>
                            <input type="number" step="0.01" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm p-2 text-sm tier-min-percentage" placeholder="1.00">
                        </div>
                        <div class="w-24">
                            <label class="block text-sm font-medium text-gray-700">อัตรา (%)</label>
                            <input type="number" step="0.001" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm p-2 text-sm tier-base-rate" placeholder="0.035">
                        </div>
                        <div class="flex-1">
                            <label class="block text-sm font-medium text-gray-700">หมายเหตุ</label>
                            <input type="text" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm p-2 text-sm tier-notes" placeholder="เช่น ถึงเป้าหมายรายเดือน">
                        </div>
                        <div class="flex items-center h-full pt-6 md:pt-0">
                            <button type="button" class="remove-tier-btn bg-red-500 hover:bg-red-600 text-white p-2 rounded-lg shadow-sm">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-200 mb-8">
                <div class="flex justify-between items-center mb-4">
                    <h2 class="text-xl font-semibold text-gray-700">กฎ Incentive รายบิล (Per-Bill Incentives)</h2>
                    <button id="addPerBillRuleBtn"
                        class="bg-purple-600 hover:bg-purple-700 text-white font-medium py-2 px-3 rounded-lg shadow-sm flex items-center">
                        <i class="fas fa-plus mr-2"></i> เพิ่มกฎรายบิล
                    </button>
                </div>
                <div id="perBillRulesContainer" class="space-y-4">
                    </div>
            </div>
            </div>
    </div>

    <script src="js/incentive_settings.js"></script>
</body>

</html>