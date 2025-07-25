// public/js/sales_reps_manager.js

document.addEventListener('DOMContentLoaded', () => {
    console.log('DOMContentLoaded event fired. sales_reps_manager.js is running.');

    const salesRepModal = document.getElementById('salesRepModal');
    const closeModalBtn = document.getElementById('closeModalBtn');
    const addNewTargetBtn = document.getElementById('addNewTargetBtn'); // ปุ่มเพิ่มเป้าหมายใหม่

    const salesRepForm = document.getElementById('salesRepForm'); // ฟอร์มสำหรับเพิ่ม/แก้ไขเป้าหมาย
    const targetFormTitle = document.getElementById('targetFormTitle'); // Title ของฟอร์ม
    const cancelTargetFormBtn = document.getElementById('cancelTargetFormBtn'); // ปุ่มยกเลิกในฟอร์มเป้าหมาย

    const modalTitle = document.getElementById('modalTitle');
    const employeeNameInModal = document.getElementById('employeeNameInModal');

    const salesRepUserCodeInput = document.getElementById('salesRepUserCode'); // Hidden user_code สำหรับ Modal โดยรวม
    const salesRepUserNameInput = document.getElementById('salesRepUserName'); // Hidden user_name สำหรับ Modal โดยรวม

    // ฟิลด์ในฟอร์มเพิ่ม/แก้ไขเป้าหมาย
    const formUserCodeInput = document.getElementById('formUserCode');
    const formUserNameInput = document.getElementById('formUserName');
    const formTargetIDInput = document.getElementById('formTargetID'); // Hidden field สำหรับ TargetID (เมื่อแก้ไข)
    const formDetailIDInput = document.getElementById('formDetailID'); // Hidden field สำหรับ DetailID (เมื่อแก้ไข)
    const targetNameInput = document.getElementById('target_name');
    const startDateInput = document.getElementById('start_date');
    const endDateInput = document.getElementById('end_date');
    const detailTypeSelect = document.getElementById('detail_type');
    const detailTargetAmountInput = document.getElementById('detail_target_amount');

    const existingTargetsSection = document.getElementById('existingTargetsSection');
    const targetFormSection = document.getElementById('targetFormSection');
    const targetsTableBody = document.getElementById('targetsTableBody');

    // Function to close main modal
    closeModalBtn.addEventListener('click', () => {
        console.log('Close modal button clicked.');
        salesRepModal.classList.add('hidden');
        salesRepForm.reset(); // Clear form fields
        showExistingTargetsSection(); // กลับไปหน้าแสดงรายการเป้าหมายเมื่อปิด modal
    });

    // Handle "ยกเลิก" button in target form
    cancelTargetFormBtn.addEventListener('click', () => {
        console.log('Cancel target form button clicked.');
        showExistingTargetsSection(); // Go back to existing targets list
    });

    // Function to show the existing targets list and hide the form
    function showExistingTargetsSection() {
        console.log('Showing existing targets section.');
        existingTargetsSection.classList.remove('hidden');
        targetFormSection.classList.add('hidden');
        salesRepForm.reset(); // Clear form just in case
        targetFormTitle.textContent = "เพิ่มเป้าหมายใหม่:"; // Reset form title
        formTargetIDInput.value = ''; // Clear target_id for new entry
        formDetailIDInput.value = ''; // Clear detail_id for new entry
    }

    // Function to show the target form and hide the existing targets list
    function showTargetFormSection(isEdit = false) {
        console.log('Showing target form section. Is Edit:', isEdit);
        existingTargetsSection.classList.add('hidden');
        targetFormSection.classList.remove('hidden');
        if (isEdit) {
            targetFormTitle.textContent = "แก้ไขเป้าหมาย:";
        } else {
            targetFormTitle.textContent = "เพิ่มเป้าหมายใหม่:";
            salesRepForm.reset(); // Reset form for new entry
            formTargetIDInput.value = ''; // Clear target_id for new entry
            formDetailIDInput.value = ''; // Clear detail_id for new entry

            // Set default values for new target (e.g., current month's start/end dates)
            const today = new Date();
            const month = (today.getMonth() + 1).toString().padStart(2, '0');
            const year = today.getFullYear();
            
            // Set default Target Name
            targetNameInput.value = `เป้าหมายยอดขายเดือน ${month}/${year} ของ ${employeeNameInModal.textContent}`;
            
            // Set default Start Date (first day of current month)
            const firstDayOfMonth = new Date(today.getFullYear(), today.getMonth(), 1);
            startDateInput.value = firstDayOfMonth.toISOString().split('T')[0];
            
            // Set default End Date (last day of current month)
            const lastDayOfMonth = new Date(today.getFullYear(), today.getMonth() + 1, 0);
            endDateInput.value = lastDayOfMonth.toISOString().split('T')[0];
        }
    }

    // Handle "เพิ่มเป้าหมายใหม่" button inside modal
    addNewTargetBtn.addEventListener('click', () => {
        console.log('Add New Target button clicked.');
        showTargetFormSection(false); // Show form for new target
    });


    // Function to fetch and display existing targets
    async function fetchAndDisplayTargets(userCode, userName) {
        console.log('Fetching and displaying targets for user:', userCode);
        targetsTableBody.innerHTML = `<tr><td colspan="4" class="px-5 py-4 border-b border-gray-200 bg-white text-sm text-center text-gray-600">
                                            กำลังโหลดเป้าหมาย...
                                        </td></tr>`; // Show loading state

        try {
            // แก้ไข Path ตรงนี้: เอา public/ ออก
            const response = await fetch(`api/api_get_employee_targets.php?user_code=${userCode}`);
            const result = await response.json();

            if (response.ok && result.success) {
                console.log('Targets fetched successfully:', result.data);
                targetsTableBody.innerHTML = ''; // Clear loading state

                if (result.data.length === 0) {
                    targetsTableBody.innerHTML = `<tr><td colspan="4" class="px-5 py-4 border-b border-gray-200 bg-white text-sm text-center text-gray-600">
                                                    ไม่พบเป้าหมายสำหรับพนักงานคนนี้
                                                </td></tr>`;
                } else {
                    result.data.forEach(target => {
                        const row = document.createElement('tr');
                        row.innerHTML = `
                            <td class="px-5 py-2 border-b border-gray-200 bg-white text-sm">${target.target_name}</td>
                            <td class="px-5 py-2 border-b border-gray-200 bg-white text-sm">${target.start_date} - ${target.end_date}</td>
                            <td class="px-5 py-2 border-b border-gray-200 bg-white text-sm text-right">${parseFloat(target.detail_target_amount).toLocaleString('th-TH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</td>
                            <td class="px-5 py-2 border-b border-gray-200 bg-white text-sm text-right">
                                <button data-target-id="${target.target_id}" data-detail-id="${target.detail_id}"
                                        data-target-name="${encodeURIComponent(target.target_name)}" data-start-date="${target.start_date}"
                                        data-end-date="${target.end_date}" data-detail-type="${target.detail_type}"
                                        data-detail-target-amount="${target.detail_target_amount}"
                                        class="edit-target-btn text-blue-600 hover:text-blue-800 font-medium mr-2">แก้ไข</button>
                                <button data-target-id="${target.target_id}" data-detail-id="${target.detail_id}"
                                        class="delete-target-btn text-red-600 hover:text-red-800 font-medium">ลบ</button>
                            </td>
                        `;
                        targetsTableBody.appendChild(row);
                    });

                    // Add event listeners for edit and delete buttons on newly created rows
                    document.querySelectorAll('.edit-target-btn').forEach(button => {
                        button.addEventListener('click', (event) => {
                            console.log('Edit target button clicked for:', event.target.dataset.targetId);
                            // Populate form fields with existing target data
                            formTargetIDInput.value = event.target.dataset.targetId;
                            formDetailIDInput.value = event.target.dataset.detailId;
                            targetNameInput.value = decodeURIComponent(event.target.dataset.targetName); // Decode URL-encoded name
                            startDateInput.value = event.target.dataset.startDate;
                            endDateInput.value = event.target.dataset.endDate;
                            detailTypeSelect.value = event.target.dataset.detailType;
                            detailTargetAmountInput.value = event.target.dataset.detailTargetAmount;
                            
                            showTargetFormSection(true); // Show form for editing
                        });
                    });

                    document.querySelectorAll('.delete-target-btn').forEach(button => {
                        button.addEventListener('click', async (event) => {
                            const targetIdToDelete = event.target.dataset.targetId;
                            const detailIdToDelete = event.target.dataset.detailId;
                            console.log('Delete target button clicked for TargetID:', targetIdToDelete, 'DetailID:', detailIdToDelete);

                            if (confirm(`คุณแน่ใจหรือไม่ที่จะลบเป้าหมาย ID: ${targetIdToDelete} (Detail ID: ${detailIdToDelete})?`)) {
                                try {
                                    // เรียก API สำหรับลบเป้าหมาย - แก้ไข Path ตรงนี้: เอา public/ ออก
                                    const deleteResponse = await fetch('api/api_delete_target.php', {
                                        method: 'POST', // ใช้ POST แม้จะเป็นการลบ เพื่อส่ง body
                                        headers: { 'Content-Type': 'application/json' },
                                        body: JSON.stringify({ target_id: targetIdToDelete, detail_id: detailIdToDelete })
                                    });
                                    const deleteResult = await deleteResponse.json();
                                    if (deleteResponse.ok && deleteResult.success) {
                                        alert('ลบเป้าหมายสำเร็จ!');
                                        // รีโหลดรายการเป้าหมาย
                                        fetchAndDisplayTargets(userCode, userName);
                                    } else {
                                        alert('เกิดข้อผิดพลาดในการลบเป้าหมาย: ' + (deleteResult.message || 'ไม่ทราบข้อผิดพลาด'));
                                        console.error('Delete API Error:', deleteResult);
                                    }
                                } catch (deleteError) {
                                    console.error('Delete Fetch Error:', deleteError);
                                    alert('ไม่สามารถเชื่อมต่อกับเซิร์ฟเวอร์เพื่อลบเป้าหมายได้');
                                }
                            }
                        });
                    });
                }
            } else {
                console.error('API Error:', result.message || 'Unknown error');
                targetsTableBody.innerHTML = `<tr><td colspan="4" class="px-5 py-4 border-b border-gray-200 bg-white text-sm text-center text-red-600">
                                                ไม่สามารถโหลดเป้าหมาย: ${result.message || 'ไม่ทราบข้อผิดพลาด'}
                                            </td></tr>`;
            }
        } catch (error) {
            console.error('Fetch Error:', error);
            targetsTableBody.innerHTML = `<tr><td colspan="4" class="px-5 py-4 border-b border-gray-200 bg-white text-sm text-center text-red-600">
                                                เกิดข้อผิดพลาดในการเชื่อมต่อ: ${error.message}
                                            </td></tr>`;
        }
    }

    // Handle form submission (Add/Edit Target)
    salesRepForm.addEventListener('submit', async (event) => {
        event.preventDefault(); // Prevent default form submission
        console.log('Sales Rep Form submitted.');

        const userCode = formUserCodeInput.value;
        const userName = formUserNameInput.value;
        const targetID = formTargetIDInput.value; // จะมีค่าถ้าเป็นการแก้ไข
        const detailID = formDetailIDInput.value; // จะมีค่าถ้าเป็นการแก้ไข
        const targetName = targetNameInput.value;
        const startDate = startDateInput.value;
        const endDate = endDateInput.value;
        const detailType = detailTypeSelect.value;
        const detailTargetAmount = parseFloat(detailTargetAmountInput.value);

        // Basic validation
        if (!userCode || !userName || !targetName || !startDate || !endDate || !detailType || isNaN(detailTargetAmount) || detailTargetAmount <= 0) {
            alert('กรุณากรอกข้อมูลให้ครบถ้วนและถูกต้อง');
            return;
        }

        const targetData = {
            user_code: userCode,
            user_name: userName,
            target_id: targetID, // ส่ง TargetID และ DetailID ไปด้วย
            detail_id: detailID,
            target_name: targetName,
            start_date: startDate,
            end_date: endDate,
            detail_type: detailType,
            related_id: userCode, // Assuming user_code is RelatedID for DetailType 'Employee'
            related_name: userName, // Assuming user_name is RelatedName for DetailType 'Employee'
            detail_target_amount: detailTargetAmount
        };

        try {
            // ส่งข้อมูลไปยัง API สำหรับบันทึก/อัปเดตเป้าหมาย - แก้ไข Path ตรงนี้: เอา public/ ออก
            const response = await fetch('api/api_save_target.php', {
                method: 'POST', // ใช้ POST เพื่อสร้าง/อัปเดต
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(targetData)
            });

            const result = await response.json();

            if (response.ok && result.success) {
                alert('บันทึกเป้าหมายสำเร็จ!');
                showExistingTargetsSection(); // กลับไปแสดงรายการเป้าหมาย
                // รีโหลดรายการเป้าหมายของพนักงานปัจจุบัน
                fetchAndDisplayTargets(userCode, userName); 
            } else {
                alert('เกิดข้อผิดพลาดในการบันทึกเป้าหมาย: ' + (result.message || 'ไม่ทราบข้อผิดพลาด'));
                console.error('API Error:', result);
            }
        } catch (error) {
            console.error('Fetch Error:', error);
            alert('ไม่สามารถเชื่อมต่อกับเซิร์ฟเวอร์เพื่อบันทึกเป้าหมายได้');
        }
    });

    // Handle "จัดการเป้าหมาย" button click (จากตารางหลัก)
    document.querySelectorAll('.edit-btn').forEach(button => {
        console.log('Found an edit-btn. Attaching event listener.');
        button.addEventListener('click', async (event) => {
            console.log('Edit button clicked!');
            const userCode = event.target.dataset.id;
            const userName = event.target.dataset.name;
            console.log('User Code:', userCode, 'User Name:', userName);

            // ตั้งค่าค่าใน hidden inputs หลักของ Modal
            salesRepUserCodeInput.value = userCode;
            salesRepUserNameInput.value = userName;
            // ตั้งค่าค่าใน hidden inputs ของฟอร์มเป้าหมาย
            formUserCodeInput.value = userCode;
            formUserNameInput.value = userName;

            // แสดงชื่อพนักงานใน Modal Title
            employeeNameInModal.textContent = userName;
            modalTitle.textContent = `จัดการเป้าหมายพนักงาน: ${userName}`;

            // แสดงส่วนรายการเป้าหมายที่มีอยู่ก่อน
            showExistingTargetsSection(); 
            // โหลดและแสดงเป้าหมายที่มีอยู่ของพนักงานคนนี้
            await fetchAndDisplayTargets(userCode, userName);

            // แสดง Modal
            salesRepModal.classList.remove('hidden');
        });
    });
});