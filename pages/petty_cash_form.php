<?php
// petty_cash_form.php - ฟอร์มใบเบิกเงินสดย่อย
session_start();
if (!isset($_SESSION['employee_id'])) {
    header('Location: ../index.php');
    exit;
}
include_once '../includes/header.php';
include_once '../includes/menu.php';
?>
<style>
:root {
    --primary-gray: #2c3e50;
    --light-gray: #ecf0f1;
    --medium-gray: #95a5a6;
    --dark-gray: #34495e;
    --white: #ffffff;
    --card-shadow: 0 8px 32px rgba(0,0,0,0.08);
    --card-shadow-hover: 0 16px 48px rgba(0,0,0,0.12);
    --border-radius: 24px;
    --border-radius-small: 16px;
    --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    --gradient-primary: linear-gradient(135deg, var(--primary-gray) 0%, var(--dark-gray) 100%);
    --gradient-card: linear-gradient(135deg, var(--white) 0%, #f8f9fa 100%);
    --gradient-bg: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
}
body {
    background: var(--gradient-bg);
    color: var(--primary-gray);
}
.container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 0 20px;
}
.card {
    background: var(--white);
    border-radius: var(--border-radius);
    box-shadow: var(--card-shadow);
    border: 1px solid rgba(255, 255, 255, 0.8);
    overflow: hidden;
    transition: var(--transition);
    margin-bottom: 2rem;
}
.card-header {
    background: var(--gradient-primary);
    color: var(--white);
    border: none;
    padding: 1.75rem 2.25rem;
    font-weight: 500;
    font-size: 1.15rem;
}
.card-body {
    padding: 2.25rem;
    background: var(--white);
}
</style>
<div class="container mt-4">
  <div class="card" style="max-width:900px;margin:0 auto;">
    <div class="card-header d-flex align-items-center">
      <i class="bi bi-file-earmark-text me-2"></i> ใบเบิกเงินสดย่อย / Petty Cash Voucher
    </div>
    <div class="card-body">
      <form id="pettyCashForm" method="post" action="petty_cash_form_pdf.php" target="_blank">
        <div class="row mb-3 align-items-end">
          <div class="col-md-8 mb-2 mb-md-0">
            <label class="form-label">No.</label>
            <input type="text" name="doc_no" class="form-control d-inline-block w-auto ms-1" style="width:120px;display:inline-block;" />
            <div class="form-check form-check-inline ms-3">
              <input class="form-check-input" type="checkbox" name="pay_type[]" value="เงินสด" id="cash">
              <label class="form-check-label" for="cash">เงินสด</label>
            </div>
            <div class="form-check form-check-inline">
              <input class="form-check-input" type="checkbox" name="pay_type[]" value="โอนเงิน" id="transfer">
              <label class="form-check-label" for="transfer">โอนเงิน</label>
            </div>
          </div>
        </div>
        <div class="row mb-3 align-items-end">
          <div class="col-md-6 mb-2 mb-md-0">
            <label class="form-label">เลขที่บัญชี</label>
            <input type="text" name="account_no" class="form-control d-inline-block w-auto" style="width:180px;display:inline-block;" />
          </div>
          <div class="col-md-6 text-end">
            <label class="form-label">วันที่</label>
            <input type="date" name="doc_date" class="form-control d-inline-block w-auto" style="width:180px;display:inline-block;" />
          </div>
        </div>
        <div class="row mb-3 align-items-end">
          <div class="col-md-8 mb-2 mb-md-0">
            <label class="form-label">จ่ายให้/Pay to</label>
            <input type="text" name="pay_to" class="form-control d-inline-block w-auto" style="width:220px;display:inline-block;" />
            <label class="form-label ms-2">แผนก/ฝ่าย</label>
            <input type="text" name="department" class="form-control d-inline-block w-auto" style="width:150px;display:inline-block;" />
          </div>
          <div class="col-md-4">
            <label class="form-label">Cost</label>
            <input type="text" name="cost" class="form-control d-inline-block w-auto" style="width:120px;display:inline-block;" />
          </div>
        </div>
        <div class="table-responsive mb-3">
          <table class="table table-bordered align-middle bg-white" id="itemsTable">
            <thead>
              <tr>
                <th style="width:70%;">รายการ</th>
                <th style="width:30%;">จำนวนเงิน</th>
              </tr>
            </thead>
            <tbody>
              <?php for($i=0;$i<7;$i++): ?>
              <tr>
                <td><input type="text" name="item_desc[]" class="form-control" /></td>
                <td><input type="number" name="item_amount[]" class="form-control text-end" step="0.01" min="0" /></td>
              </tr>
              <?php endfor; ?>
            </tbody>
            <tfoot>
              <tr>
                <th class="text-end">จำนวนเงินรวม</th>
                <th><input type="number" name="total_amount" class="form-control text-end" step="0.01" min="0" readonly /></th>
              </tr>
            </tfoot>
          </table>
        </div>
        <div class="row mb-4">
          <div class="col text-center">
            <div class="d-inline-block mx-3">ผู้รับเงิน<br><input type="text" name="receiver" class="form-control" style="width:150px;display:inline-block;" /></div>
            <div class="d-inline-block mx-3">ผู้จ่ายเงิน<br><input type="text" name="payer" class="form-control" style="width:150px;display:inline-block;" /></div>
            <div class="d-inline-block mx-3">ผู้อนุมัติ<br><input type="text" name="approver" class="form-control" style="width:150px;display:inline-block;" /></div>
          </div>
        </div>
        <div class="text-center mt-4">
          <button type="submit" class="btn btn-primary px-5 py-2 fw-bold"><i class="bi bi-file-earmark-pdf"></i> บันทึกและดาวน์โหลด PDF</button>
        </div>
      </form>
    </div>
  </div>
</div>
<script>
// คำนวณยอดรวมอัตโนมัติ
const itemsTable = document.getElementById('itemsTable');
if(itemsTable) {
    itemsTable.addEventListener('input', function() {
        let sum = 0;
        document.querySelectorAll('input[name="item_amount[]"]').forEach(function(input) {
            sum += parseFloat(input.value) || 0;
        });
        document.querySelector('input[name="total_amount"]').value = sum.toFixed(2);
    });
}
</script>
<?php include '../includes/footer.php'; ?>
