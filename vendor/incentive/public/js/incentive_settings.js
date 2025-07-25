// public/js/incentive_settings.js
document.addEventListener('DOMContentLoaded', () => {
    const generalRulesContainer = document.getElementById('generalRulesContainer');
    const targetTiersContainer = document.getElementById('targetTiersContainer');
    const perBillRulesContainer = document.getElementById('perBillRulesContainer'); // New container
    const saveRulesBtn = document.getElementById('saveRulesBtn');
    const addTierBtn = document.getElementById('addTierBtn');
    const addPerBillRuleBtn = document.getElementById('addPerBillRuleBtn'); // New button
    const loadingOverlay = document.getElementById('loadingOverlay');

    let currentRules = {}; // Store fetched general rules
    let currentTiers = []; // Store fetched tiers
    let currentPerBillRules = []; // New: Store fetched per-bill rules

    // Show loading spinner
    const showLoading = () => loadingOverlay.classList.remove('hidden');
    const hideLoading = () => loadingOverlay.classList.add('hidden');

    // Function to fetch rules from API
    async function fetchIncentiveRules() {
        showLoading();
        try {
            const response = await fetch('api/api_get_incentive_rules.php');
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            const result = await response.json();
            if (result.success) {
                currentRules = result.rules.reduce((acc, rule) => {
                    acc[rule.rule_key] = rule;
                    return acc;
                }, {});
                currentTiers = result.tiers;
                currentPerBillRules = result.per_bill_rules; // Fetch per-bill rules
                renderRules();
                renderTiers();
                renderPerBillRules(); // Render per-bill rules
            } else {
                alert('Error: ' + result.message);
            }
        } catch (error) {
            console.error('Failed to fetch incentive rules:', error);
            alert('ไม่สามารถดึงข้อมูลกฎ Incentive ได้: ' + error.message);
        } finally {
            hideLoading();
        }
    }

    // Function to render general rules
    function renderRules() {
        generalRulesContainer.innerHTML = '';

        const ruleData = [
            { key: 'default_base_rate', label: 'อัตรา Incentive พื้นฐานเริ่มต้น:', placeholder: 'เช่น 0.025', description: 'อัตรา Incentive เริ่มต้นสำหรับยอดขายที่ไม่มีเงื่อนไขพิเศษ', type: 'number', step: '0.001' },
            { key: 'product_incentive_rate', label: 'อัตรา Incentive สินค้า:', placeholder: 'เช่น 0.005', description: 'อัตรา Incentive เพิ่มเติมสำหรับยอดขายสินค้าบางประเภท', type: 'number', step: '0.001' },
            { key: 'product_incentive_min_sales', label: 'ยอดขายขั้นต่ำสำหรับ Incentive สินค้า:', placeholder: 'เช่น 100000', description: 'ยอดขายขั้นต่ำที่ต้องถึงเพื่อรับ Incentive สินค้า', type: 'number', step: '1' },
            { key: 'no_sales_notes', label: 'หมายเหตุเมื่อไม่มียอดขาย:', placeholder: 'เช่น ไม่มียอดขายในงวดนี้', description: 'ข้อความสำหรับพนักงานที่ไม่มีการขาย', type: 'text' },
        ];

        ruleData.forEach(ruleInfo => {
            const rule = currentRules[ruleInfo.key];
            const div = document.createElement('div');
            div.className = 'bg-gray-50 p-4 rounded-lg border border-gray-200';
            div.innerHTML = `
                <label for="${ruleInfo.key}" class="block text-sm font-medium text-gray-700">${ruleInfo.label}</label>
                <input type="${ruleInfo.type}" step="${ruleInfo.step || ''}" id="${ruleInfo.key}" name="${ruleInfo.key}"
                    class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm p-2 focus:ring-blue-500 focus:border-blue-500 sm:text-sm"
                    placeholder="${ruleInfo.placeholder}" value="${rule ? rule.rule_value : ''}"
                    data-rule-id="${rule ? rule.rule_id : ''}"
                    data-rule-key="${ruleInfo.key}"
                    data-data-type="${rule ? rule.data_type : ruleInfo.type === 'number' ? 'decimal' : 'string'}">
                <p class="text-xs text-gray-500 mt-1">${ruleInfo.description}</p>
            `;
            generalRulesContainer.appendChild(div);
        });
    }

    // Function to render target tiers
    function renderTiers() {
        targetTiersContainer.innerHTML = ''; // Clear existing tiers

        if (currentTiers.length === 0) {
            const noData = document.createElement('p');
            noData.className = 'text-gray-500 text-center py-4';
            noData.textContent = 'ยังไม่มีการตั้งค่า Tier เป้าหมาย';
            targetTiersContainer.appendChild(noData);
        } else {
            currentTiers.forEach(tier => {
                targetTiersContainer.appendChild(createTierElement(tier));
            });
        }
    }

    // Function to create a single tier HTML element
    function createTierElement(tier = {}) {
        const div = document.createElement('div');
        div.className = 'bg-gray-50 p-4 rounded-lg border border-gray-200 flex flex-col md:flex-row md:items-end gap-3 relative';
        div.setAttribute('data-tier-id', tier.tier_id || 'new');
        div.innerHTML = `
            <div class="flex-1">
                <label class="block text-sm font-medium text-gray-700">ชื่อ Tier</label>
                <input type="text" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm p-2 text-sm tier-name" placeholder="เช่น ถึงเป้าหมาย 100% ขึ้นไป" value="${tier.tier_name || ''}">
            </div>
            <div class="w-24">
                <label class="block text-sm font-medium text-gray-700">เป้าหมาย (%)</label>
                <input type="number" step="0.01" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm p-2 text-sm tier-min-percentage" placeholder="1.00" value="${tier.min_percentage_of_target !== undefined ? tier.min_percentage_of_target : ''}">
            </div>
            <div class="w-24">
                <label class="block text-sm font-medium text-gray-700">อัตรา (%)</label>
                <input type="number" step="0.001" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm p-2 text-sm tier-base-rate" placeholder="0.035" value="${tier.base_rate !== undefined ? tier.base_rate : ''}">
            </div>
            <div class="flex-1">
                <label class="block text-sm font-medium text-gray-700">หมายเหตุ</label>
                <input type="text" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm p-2 text-sm tier-notes" placeholder="เช่น ถึงเป้าหมายรายเดือน" value="${tier.notes || ''}">
            </div>
            <div class="flex items-center h-full pt-6 md:pt-0">
                <button type="button" class="remove-tier-btn bg-red-500 hover:bg-red-600 text-white p-2 rounded-lg shadow-sm">
                    <i class="fas fa-trash"></i>
                </button>
            </div>
        `;
        div.querySelector('.remove-tier-btn').addEventListener('click', () => {
            div.remove();
        });
        return div;
    }

    // New: Function to render per-bill rules
    function renderPerBillRules() {
        perBillRulesContainer.innerHTML = ''; // Clear existing rules

        if (currentPerBillRules.length === 0) {
            const noData = document.createElement('p');
            noData.className = 'text-gray-500 text-center py-4';
            noData.textContent = 'ยังไม่มีการตั้งค่ากฎ Incentive รายบิล';
            perBillRulesContainer.appendChild(noData);
        } else {
            // Filter to get only relevant per_bill_ rules (threshold, fixed_amount, percentage_rate, type)
            const thresholdRule = currentPerBillRules.find(rule => rule.rule_key === 'per_bill_threshold');
            const fixedAmountRule = currentPerBillRules.find(rule => rule.rule_key === 'per_bill_fixed_amount');
            const percentageRateRule = currentPerBillRules.find(rule => rule.rule_key === 'per_bill_percentage_rate');
            const typeRule = currentPerBillRules.find(rule => rule.rule_key === 'per_bill_type');

            perBillRulesContainer.appendChild(createPerBillRuleElement({
                threshold: thresholdRule ? thresholdRule.rule_value : '',
                fixed_amount: fixedAmountRule ? fixedAmountRule.rule_value : '',
                percentage_rate: percentageRateRule ? percentageRateRule.rule_value : '',
                type: typeRule ? typeRule.rule_value : 'fixed', // Default to fixed
                rule_ids: {
                    threshold: thresholdRule ? thresholdRule.rule_id : null,
                    fixed_amount: fixedAmountRule ? fixedAmountRule.rule_id : null,
                    percentage_rate: percentageRateRule ? percentageRateRule.rule_id : null,
                    type: typeRule ? typeRule.rule_id : null
                }
            }));
        }
    }

    // New: Function to create a single per-bill rule HTML element
    function createPerBillRuleElement(rule = {}) {
        const div = document.createElement('div');
        div.className = 'bg-gray-50 p-4 rounded-lg border border-gray-200 flex flex-col md:flex-row md:items-end gap-3';
        div.innerHTML = `
            <div class="w-32">
                <label class="block text-sm font-medium text-gray-700">ยอดบิลขั้นต่ำ (฿)</label>
                <input type="number" step="1" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm p-2 text-sm per-bill-threshold"
                    placeholder="เช่น 2000" value="${rule.threshold !== undefined ? rule.threshold : ''}"
                    data-rule-id="${rule.rule_ids ? rule.rule_ids.threshold : 'new'}"
                    data-rule-key="per_bill_threshold"
                    data-data-type="integer">
            </div>
            <div class="w-32">
                <label class="block text-sm font-medium text-gray-700">ประเภท Incentive</label>
                <select class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm p-2 text-sm per-bill-type"
                    data-rule-id="${rule.rule_ids ? rule.rule_ids.type : 'new'}"
                    data-rule-key="per_bill_type"
                    data-data-type="string">
                    <option value="fixed" ${rule.type === 'fixed' ? 'selected' : ''}>จำนวนคงที่ (฿)</option>
                    <option value="percentage" ${rule.type === 'percentage' ? 'selected' : ''}>เปอร์เซ็นต์ (%)</option>
                </select>
            </div>
            <div class="w-32">
                <label class="block text-sm font-medium text-gray-700">มูลค่า Incentive</label>
                <input type="number" step="0.01" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm p-2 text-sm per-bill-value"
                    placeholder="เช่น 50 (คงที่) หรือ 2.5 (เปอร์เซ็นต์)"
                    value="${rule.type === 'fixed' ? (rule.fixed_amount !== undefined ? rule.fixed_amount : '') : (rule.percentage_rate !== undefined ? rule.percentage_rate : '')}"
                    data-rule-id-fixed="${rule.rule_ids ? rule.rule_ids.fixed_amount : 'new'}"
                    data-rule-key-fixed="per_bill_fixed_amount"
                    data-rule-id-percentage="${rule.rule_ids ? rule.rule_ids.percentage_rate : 'new'}"
                    data-rule-key-percentage="per_bill_percentage_rate"
                    data-data-type="decimal">
            </div>
            <div class="flex-1">
                <label class="block text-sm font-medium text-gray-700">หมายเหตุ</label>
                <input type="text" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm p-2 text-sm per-bill-notes"
                    placeholder="เช่น เมื่อยอดบิลถึง 2000 บาท" value="${rule.notes || ''}">
            </div>
            <div class="flex items-center h-full pt-6 md:pt-0">
                <button type="button" class="remove-per-bill-rule-btn bg-red-500 hover:bg-red-600 text-white p-2 rounded-lg shadow-sm">
                    <i class="fas fa-trash"></i>
                </button>
            </div>
        `;
        div.querySelector('.remove-per-bill-rule-btn').addEventListener('click', () => {
            div.remove();
        });

        // Handle change in incentive type dropdown to update placeholder/value
        const typeSelect = div.querySelector('.per-bill-type');
        const valueInput = div.querySelector('.per-bill-value');

        typeSelect.addEventListener('change', () => {
            if (typeSelect.value === 'fixed') {
                valueInput.placeholder = 'เช่น 50 (คงที่)';
                valueInput.step = '0.01';
                valueInput.value = rule.fixed_amount !== undefined ? rule.fixed_amount : '';
            } else {
                valueInput.placeholder = 'เช่น 2.5 (เปอร์เซ็นต์)';
                valueInput.step = '0.001';
                valueInput.value = rule.percentage_rate !== undefined ? rule.percentage_rate : '';
            }
        });
        // Trigger initial update based on existing rule type
        typeSelect.dispatchEvent(new Event('change'));

        return div;
    }

    // Event listeners for adding tiers and per-bill rules
    addTierBtn.addEventListener('click', () => {
        targetTiersContainer.appendChild(createTierElement());
    });

    addPerBillRuleBtn.addEventListener('click', () => {
        // We only allow one per-bill rule for now, if more are added, we'll extend this
        if (perBillRulesContainer.children.length === 0 || (perBillRulesContainer.children.length === 1 && perBillRulesContainer.querySelector('p'))) {
            perBillRulesContainer.innerHTML = ''; // Clear "no data" message
            perBillRulesContainer.appendChild(createPerBillRuleElement());
        } else {
            alert('Currently, only one per-bill incentive rule can be added. Please edit the existing one.');
        }
    });

    // Function to collect all form data for saving
    function collectFormData() {
        const formData = {
            rules: [],
            tiers: [],
            per_bill_rules: [] // New: for per-bill rules
        };

        // Collect general rules
        generalRulesContainer.querySelectorAll('input').forEach(input => {
            const ruleId = input.getAttribute('data-rule-id');
            const ruleKey = input.getAttribute('data-rule-key');
            let ruleValue = input.value;
            const dataType = input.getAttribute('data-data-type');

            if (dataType === 'decimal' || dataType === 'integer') {
                ruleValue = parseFloat(ruleValue);
                if (isNaN(ruleValue)) ruleValue = 0; // Default to 0 if invalid number
            }

            if (ruleId && ruleKey) {
                formData.rules.push({
                    rule_id: parseInt(ruleId),
                    rule_key: ruleKey,
                    rule_value: ruleValue,
                    data_type: dataType
                });
            }
        });

        // Collect target tiers
        targetTiersContainer.querySelectorAll('[data-tier-id]').forEach(tierElement => {
            const tierId = tierElement.getAttribute('data-tier-id');
            const tierName = tierElement.querySelector('.tier-name').value;
            const minPercentage = parseFloat(tierElement.querySelector('.tier-min-percentage').value);
            const baseRate = parseFloat(tierElement.querySelector('.tier-base-rate').value);
            const notes = tierElement.querySelector('.tier-notes').value;

            const tierData = {
                tier_name: tierName,
                min_percentage_of_target: isNaN(minPercentage) ? 0 : minPercentage,
                base_rate: isNaN(baseRate) ? 0 : baseRate,
                notes: notes,
                is_active: 1 // Assuming always active when configured via UI
            };

            if (tierId !== 'new') {
                tierData.tier_id = parseInt(tierId);
            }
            formData.tiers.push(tierData);
        });

        // New: Collect per-bill rules
        perBillRulesContainer.querySelectorAll('.per-bill-threshold').forEach(input => {
            const ruleElement = input.closest('.bg-gray-50');
            const threshold = parseFloat(input.value);
            const typeSelect = ruleElement.querySelector('.per-bill-type');
            const incentiveType = typeSelect.value;
            const valueInput = ruleElement.querySelector('.per-bill-value');
            const notes = ruleElement.querySelector('.per-bill-notes').value;

            // Collect threshold rule
            formData.per_bill_rules.push({
                rule_id: input.getAttribute('data-rule-id') === 'new' ? null : parseInt(input.getAttribute('data-rule-id')),
                rule_key: 'per_bill_threshold',
                rule_name: 'Per-Bill Threshold',
                rule_value: isNaN(threshold) ? 0 : threshold,
                data_type: 'integer',
                description: 'Threshold amount for per-bill incentive',
            });

            // Collect incentive type rule
            formData.per_bill_rules.push({
                rule_id: typeSelect.getAttribute('data-rule-id') === 'new' ? null : parseInt(typeSelect.getAttribute('data-rule-id')),
                rule_key: 'per_bill_type',
                rule_name: 'Per-Bill Incentive Type',
                rule_value: incentiveType,
                data_type: 'string',
                description: 'Type of per-bill incentive (fixed/percentage)',
            });

            // Collect incentive value rule (either fixed or percentage)
            if (incentiveType === 'fixed') {
                const fixedAmount = parseFloat(valueInput.value);
                formData.per_bill_rules.push({
                    rule_id: valueInput.getAttribute('data-rule-id-fixed') === 'new' ? null : parseInt(valueInput.getAttribute('data-rule-id-fixed')),
                    rule_key: 'per_bill_fixed_amount',
                    rule_name: 'Per-Bill Fixed Amount',
                    rule_value: isNaN(fixedAmount) ? 0 : fixedAmount,
                    data_type: 'decimal',
                    description: 'Fixed amount for per-bill incentive',
                });
                // Ensure percentage_rate is effectively "removed" or set to 0 if not used
                const percentageRuleId = valueInput.getAttribute('data-rule-id-percentage');
                if (percentageRuleId && percentageRuleId !== 'new') {
                    formData.per_bill_rules.push({
                        rule_id: parseInt(percentageRuleId),
                        rule_key: 'per_bill_percentage_rate',
                        rule_name: 'Per-Bill Percentage Rate',
                        rule_value: 0, // Set to 0 if not used
                        data_type: 'decimal',
                        description: 'Percentage rate for per-bill incentive',
                    });
                }
            } else { // percentage
                const percentageRate = parseFloat(valueInput.value);
                formData.per_bill_rules.push({
                    rule_id: valueInput.getAttribute('data-rule-id-percentage') === 'new' ? null : parseInt(valueInput.getAttribute('data-rule-id-percentage')),
                    rule_key: 'per_bill_percentage_rate',
                    rule_name: 'Per-Bill Percentage Rate',
                    rule_value: isNaN(percentageRate) ? 0 : percentageRate,
                    data_type: 'decimal',
                    description: 'Percentage rate for per-bill incentive',
                });
                // Ensure fixed_amount is effectively "removed" or set to 0 if not used
                const fixedRuleId = valueInput.getAttribute('data-rule-id-fixed');
                if (fixedRuleId && fixedRuleId !== 'new') {
                    formData.per_bill_rules.push({
                        rule_id: parseInt(fixedRuleId),
                        rule_key: 'per_bill_fixed_amount',
                        rule_name: 'Per-Bill Fixed Amount',
                        rule_value: 0, // Set to 0 if not used
                        data_type: 'decimal',
                        description: 'Fixed amount for per-bill incentive',
                    });
                }
            }
            // You might want to save notes for the per-bill rule as a general rule or associate it.
            // For simplicity, we can assume notes are not directly tied to fixed/percentage rules in DB
            // If you want to save notes, you'd add a rule_key for notes in incentive_rules table
        });


        return formData;
    }

    // Event listener for Save button
    if (saveRulesBtn) {
        saveRulesBtn.addEventListener('click', async () => {
            showLoading();
            try {
                const formData = collectFormData();
                console.log('Saving Data:', formData); // For debugging

                const response = await fetch('api/api_update_incentive_rules.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify(formData),
                });

                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }

                const result = await response.json();
                if (result.success) {
                    alert('บันทึกการตั้งค่า Incentive สำเร็จ!');
                    fetchIncentiveRules(); // Re-fetch to update UI with latest saved data (e.g., new tier IDs)
                } else {
                    alert('Error: ' + result.message);
                }
            } catch (error) {
                console.error('Failed to save incentive rules:', error);
                alert('เกิดข้อผิดพลาดในการบันทึกการตั้งค่า: ' + error.message);
            } finally {
                hideLoading();
            }
        });
    }

    // Initial fetch of rules when the page loads
    fetchIncentiveRules();
});