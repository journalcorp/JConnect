// public/js/manager.js

document.addEventListener('DOMContentLoaded', () => {
    console.log('Manager Dashboard script loaded!');

    const calculateIncentiveButton = document.getElementById('calculateIncentiveBtn');
    if (calculateIncentiveButton) {
        calculateIncentiveButton.addEventListener('click', async () => {
            alert('กำลังคำนวณ Incentive สำหรับรอบปัจจุบัน...');
            // ในระบบจริง:
            // try {
            //     const response = await fetch('/api/incentives/payouts/run_batch_calculation', {
            //         method: 'POST',
            //         headers: {
            //             'Content-Type': 'application/json',
            //             'Authorization': 'Bearer ' + localStorage.getItem('jwt_token') // สมมติว่าเก็บ token ไว้
            //         },
            //     });
            //     const data = await response.json();
            //     if (response.ok) {
            //         alert('คำนวณ Incentive สำเร็จ! ' + data.message);
            //         // อาจจะ refresh หน้าจอ หรือ update ตาราง
            //         window.location.reload();
            //     } else {
            //         alert('เกิดข้อผิดพลาดในการคำนวณ: ' + data.message);
            //     }
            // } catch (error) {
            //     console.error('Error:', error);
            //     alert('ไม่สามารถเชื่อมต่อกับเซิร์ฟเวอร์ได้');
            // }
        });
    }

    document.querySelectorAll('button').forEach(button => {
        if (button.textContent.includes('ดูรายละเอียด') || button.textContent.includes('แก้ไข')) {
            button.addEventListener('click', (event) => {
                // ตัวอย่าง: ถ้าต้องการดึง ID จากแถว
                // const row = event.target.closest('tr');
                // const repId = row.dataset.repId; // ต้องเพิ่ม data-rep-id="ID" ใน HTML
                // console.log('Button clicked for ID:', repId);
            });
        }
    });
});