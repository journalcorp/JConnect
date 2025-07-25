<?php
session_start();
include_once '../config.php';

// ตรวจสอบการส่งข้อมูลแบบ POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // รับค่าจากฟอร์ม
    $request_title = $_POST['request_title'] ?? '';
    $distance = $_POST['distance'] ?? 0;
    $amount = $_POST['amount'] ?? 0;
    $remarks = $_POST['remarks'] ?? '';
    $start_lat = $_POST['start_lat'] ?? '';
    $start_lng = $_POST['start_lng'] ?? '';
    $end_lat = $_POST['end_lat'] ?? '';
    $end_lng = $_POST['end_lng'] ?? '';
    $employee_id = $_SESSION['employee_id'] ?? 0;

    // ตรวจสอบและอัปโหลดรูป
    $evidence_image = '';
    if (isset($_FILES['evidence_image']) && $_FILES['evidence_image']['error'] === UPLOAD_ERR_OK) {
        $targetDir = "../uploads/travel/";
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0777, true);
        }
        $fileName = uniqid('travel_') . '_' . basename($_FILES['evidence_image']['name']);
        $targetFile = $targetDir . $fileName;
        if (move_uploaded_file($_FILES['evidence_image']['tmp_name'], $targetFile)) {
            $evidence_image = $fileName;
        } else {
            die("อัปโหลดรูปไม่สำเร็จ");
        }
    } else {
        die("กรุณาอัปโหลดรูปภาพหลักฐาน");
    }

    // เชื่อมต่อฐานข้อมูล
    $conn = dbConnect();
    if (!$conn) {
        die("Database connection failed: " . mysqli_connect_error());
    }

    // เพิ่มข้อมูลลงฐานข้อมูล (ตัวอย่างตารางชื่อ travel_requests)
    $sql = "INSERT INTO travel_requests 
        (employee_id, request_title, distance, amount, remarks, start_lat, start_lng, end_lat, end_lng, evidence_image, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param(
        "isddssssss",
        $employee_id,
        $request_title,
        $distance,
        $amount,
        $remarks,
        $start_lat,
        $start_lng,
        $end_lat,
        $end_lng,
        $evidence_image
    );

    if ($stmt->execute()) {
        $stmt->close();
        $conn->close();
        header("Location: accounting_form.php?success=1");
        exit;
    } else {
        $stmt->close();
        $conn->close();
        die("เกิดข้อผิดพลาดในการบันทึกข้อมูล: " . $conn->error);
    }
} else {
    header("Location: add_request_form.php");
    exit;
}
?>
<form method="POST" action="process_travel_request.php" enctype="multipart/form-data">
    <div class="mb-3">
        <label for="request_title" class="form-label">หัวข้อคำขอ</label>
        <input type="text" class="form-control" id="request_title" name="request_title" placeholder="กรอกหัวข้อคำขอ" required>
    </div>
    <div class="mb-3">
        <label class="form-label">อัปโหลดรูปภาพหลักฐาน (ใบเสร็จ/รูปสถานที่ ฯลฯ)</label>
        <input type="file" class="form-control" name="evidence_image" accept="image/*" required>
    </div>
    <div class="mb-3">
        <label class="form-label">เลือกตำแหน่งเริ่มต้นและสิ้นสุด</label>
        <div id="map" style="height: 300px; border-radius: 12px; margin-bottom: 8px;"></div>
        <input type="hidden" name="start_lat" id="start_lat">
        <input type="hidden" name="start_lng" id="start_lng">
        <input type="hidden" name="end_lat" id="end_lat">
        <input type="hidden" name="end_lng" id="end_lng">
    </div>
    <div class="mb-3 row">
        <div class="col-6">
            <label for="distance" class="form-label">ระยะทาง (กม.)</label>
            <input type="number" class="form-control" id="distance" name="distance" step="0.01" min="0" required>
        </div>
        <div class="col-6">
            <label for="amount" class="form-label">จำนวนเงิน (บาท)</label>
            <input type="number" class="form-control" id="amount" name="amount" readonly>
        </div>
    </div>
    <div class="mb-3">
        <label for="remarks" class="form-label">หมายเหตุ</label>
        <textarea class="form-control" id="remarks" name="remarks" rows="3"></textarea>
    </div>
    <div class="d-grid gap-2 mt-4">
        <button type="submit" class="btn btn-primary btn-lg">
            <i class="bi bi-plus-circle"></i> เพิ่มคำขอ
        </button>
        <a href="accounting_form.php" class="btn btn-secondary btn-lg">
            <i class="bi bi-arrow-left-circle"></i> กลับ
        </a>
    </div>
</form>

<!-- Leaflet JS & CSS สำหรับแผนที่ -->
<link rel="stylesheet" href="https://unpkg.com/leaflet/dist/leaflet.css"/>
<script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>
<script>
let startMarker, endMarker, map;
window.onload = function() {
    map = L.map('map').setView([13.7563, 100.5018], 12); // Bangkok default
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { attribution: '' }).addTo(map);

    map.on('click', function(e) {
        if (!startMarker) {
            startMarker = L.marker(e.latlng, { draggable: true }).addTo(map).bindPopup('จุดเริ่มต้น').openPopup();
            document.getElementById('start_lat').value = e.latlng.lat;
            document.getElementById('start_lng').value = e.latlng.lng;
            startMarker.on('dragend', function(ev) {
                let pos = ev.target.getLatLng();
                document.getElementById('start_lat').value = pos.lat;
                document.getElementById('start_lng').value = pos.lng;
            });
        } else if (!endMarker) {
            endMarker = L.marker(e.latlng, { draggable: true, icon: L.icon({iconUrl: 'https://cdn-icons-png.flaticon.com/512/684/684908.png', iconSize: [32,32]}) }).addTo(map).bindPopup('จุดสิ้นสุด').openPopup();
            document.getElementById('end_lat').value = e.latlng.lat;
            document.getElementById('end_lng').value = e.latlng.lng;
            endMarker.on('dragend', function(ev) {
                let pos = ev.target.getLatLng();
                document.getElementById('end_lat').value = pos.lat;
                document.getElementById('end_lng').value = pos.lng;
            });
        }
    });
};

// คำนวณเงินอัตโนมัติ (1 กม. = 6 บาท)
document.addEventListener('DOMContentLoaded', function() {
    document.getElementById('distance').addEventListener('input', function() {
        let km = parseFloat(this.value) || 0;
        document.getElementById('amount').value = (km * 6).toFixed(2);
    });
});
</script>