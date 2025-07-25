<!DOCTYPE html>
<html lang="th">
<head>
  <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>JOURNAL</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <!-- เพิ่มลิงก์ Font Awesome ในส่วน <head> -->
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
  <style>
    body { font-family: 'Prompt', sans-serif; margin: 0; }
    .top-bar {
      background-color: #f1ece3; /* สีพื้นหลัง */
      /*padding: 20px 0;  เพิ่มระยะห่างด้านบนและล่าง */
      text-align: center; /* จัดให้อยู่ตรงกลาง */
    }

    .top-bar .logo {
      max-width: 150px; /* กำหนดความกว้างสูงสุดของโลโก้ */
      height: auto; /* รักษาอัตราส่วนของรูปภาพ */
    }

    .top-bar .tagline {
      font-size: 14px; /* ขนาดตัวอักษร */
      color: #555; /* สีข้อความ */
      margin-top: 10px; /* ระยะห่างระหว่างโลโก้กับข้อความ */
      font-weight: 500; /* น้ำหนักตัวอักษร */
      letter-spacing: 1px; /* ระยะห่างระหว่างตัวอักษร */
    }

    .menu-bar {
      background-color: #ffffff;
      border-top: 1px solid #ddd;
      border-bottom: 1px solid #ddd;
    }
    .menu-bar ul {
      margin: 0;
      padding: 0;
      list-style: none;
      display: flex;
      justify-content: center;
      flex-wrap: wrap;
    }
    .menu-bar li {
      margin: 10px 15px;
    }
    .menu-bar a {
      text-decoration: none;
      font-weight: 600;
      color: #2b2b2b;
      font-size: 14px;
      letter-spacing: 1px;
    }
    .menu-bar a:hover {
      text-decoration: underline;
    }
    

    .hero-section h2 {
      font-size: 2.5rem;
      font-weight: bold;
    }
    .hero-section p {
      font-size: 1.25rem;
      margin-bottom: 0rem;
    }
    .hero-section a {
      color: #eaeaea;
      text-decoration: underline;
      font-weight: 500;
    }
    .product-row {
      display: flex; /* ใช้ Flexbox */
  justify-content: center; /* จัดให้อยู่ตรงกลางแนวนอน */
  align-items: center; /* จัดให้อยู่ตรงกลางแนวตั้ง */
   /*min-height: 100vh; กำหนดความสูงขั้นต่ำให้เต็มหน้าจอ */
  padding: 40px 0; /* ระยะห่างด้านบนและล่าง */
  text-align: center;
  background-color: #fff;
    }
    .product-row img {
      width: 500px;
      margin-bottom: 10px;
    }
    .product-row p {
      font-weight: 600;
      font-size: 14px;
    }
    .our-scent-section {
      padding: 60px 0;
      background-color: #fff;
    }
    .our-scent-section .desc {
      text-align: center;
    }
    .our-scent-section h6 {
      font-size: 12px;
      letter-spacing: 1px;
      color: #888;
    }
    .our-scent-section h3 {
      color: #e48d95;
      font-size: 16px;
      font-weight: bold;
      margin-bottom: 1rem;
    }
    .our-scent-section p {
      font-size: 14px;
      color: #333;
      line-height: 1.8;
    }
    .image-grid {
      padding: 40px 0;
      background: #fdfdfd;
    }
    .image-grid img {
      width: 100%;
      height: auto;
      margin-bottom: 15px;
    }
    .image-grid .caption {
      font-size: 14px;
      text-align: center;
      font-weight: 500;
    }
    .hero-section {
      width: 100%;
      height: auto;
      background-color: #f6f6f6;
      display: flex;
      justify-content: center;
      align-items: center;
      flex-direction: column;
      padding: 60px 20px;
      text-align: center;
      background-image: url('./img/BannerCRM01.jpg');
      background-size: contain; /* เปลี่ยนจาก cover เป็น contain */
      background-repeat: no-repeat; /* ป้องกันการซ้ำของภาพ */
      background-position: center; /* จัดตำแหน่งภาพให้อยู่ตรงกลาง */
      color: #fff;
    }
  
    /* สำหรับหน้าจอขนาดใหญ่ (PC) */
    @media (min-width: 768px) {
      .menu-bar ul {
          flex-direction: row;
          justify-content: center;
      }
      .hero-section {
          padding: 60px 20px;
          background-size: cover;
          background-position: center;
      }
      .product-row img {
          max-width: 200px; /* ขนาดรูปภาพสำหรับ PC */
      }
      .our-scent-section .desc {
          text-align: left;
      }
      .image-grid img {
          max-width: 300px;
      }
      .hamburger-menu {
    display: none; /* ซ่อนปุ่ม Hamburger บนหน้าจอขนาดใหญ่ */
  }
    }

    /* สำหรับหน้าจอขนาดกลาง (แท็บเล็ต) */
    @media (min-width: 768px) and (max-width: 1023px) {
      .video-section video {
        max-width: 600px; /* จำกัดความกว้างสูงสุด */
        height: auto; /* รักษาอัตราส่วน */
      }
      .product-row .row {
        flex-direction: row; /* เรียงแนวนอน */
        justify-content: space-around;
      }
      .product-row img {
        max-width: 150px; /* ขนาดรูปภาพสำหรับแท็บเล็ต */
      }
    }

    /* สำหรับหน้าจอขนาดเล็ก (มือถือ) */
    @media (max-width: 767px) {
      .top-bar h1 {
          font-size: 22px;
      }
      .menu-bar {
        display: none; /* ซ่อนเมนู */
      }
      .hero-section {
          padding: 20px;
          background-size: contain;
      }
      .product-row {
        display: flex; /* ใช้ Flexbox */
        justify-content: center; /* จัดให้อยู่ตรงกลางแนวนอน */
        align-items: center; /* จัดให้อยู่ตรงกลางแนวตั้ง */
        flex-direction: column; /* เรียงเนื้อหาในแนวตั้ง */
        min-height: 30vh; /* ให้ความสูงเต็มหน้าจอ */
        text-align: center; /* จัดข้อความให้อยู่ตรงกลาง */
      }
      .product-row img {
        max-width: 150px; /* ลดขนาดรูปภาพให้เหมาะสม */
        margin-bottom: 10px; /* เพิ่มระยะห่างระหว่างรูปภาพ */
      }
      .product-row p {
        font-size: 14px; /* ขนาดตัวอักษรที่เหมาะสม */
      }
      .product-row .row {
        flex-direction: column; /* เปลี่ยนการเรียงเป็นแนวตั้ง */
        align-items: center; /* จัดให้อยู่ตรงกลาง */
      }
      .product-row .col {
        max-width: 100%; /* ให้แต่ละคอลัมน์ใช้พื้นที่เต็ม */
        margin-bottom: 20px; /* เพิ่มระยะห่างระหว่างผลิตภัณฑ์ */
      }
      .product-row img {
        max-width: 1%; /* ลดขนาดรูปภาพให้เหมาะสม */
      }
      .product-row .row {
        display: -webkit-box; /* ใช้ Flexbox */
        flex-wrap: nowrap; /* ไม่ให้แถวห่อ */
        overflow-x: auto; /* เพิ่มการเลื่อนแนวนอน */
        justify-content: flex-start; /* จัดเรียงจากซ้ายไปขวา */
        gap: 20px; /* เพิ่มระยะห่างระหว่างผลิตภัณฑ์ */
        padding: 10px; /* เพิ่ม Padding เพื่อให้เลื่อนง่ายขึ้น */
      }
      .product-row .col {
        flex: 0 0 auto; /* ให้คอลัมน์มีขนาดตามเนื้อหา */
        width: auto; /* ปรับขนาดตามเนื้อหา */
        text-align: center; /* จัดข้อความให้อยู่ตรงกลาง */
      }
      .product-row img {
        max-width: 120px; /* ลดขนาดรูปภาพให้เหมาะสม */
      }
    }

    /* Hamburger Menu Styles */
    .hamburger-menu {
  position: absolute; /* ตำแหน่งสัมพันธ์กับ Header */
  top: 20px; /* ระยะห่างจากด้านบน */
  left: 20px; /* ระยะห่างจากด้านซ้าย */
  font-size: 24px;
  cursor: pointer;
  z-index: 1000; /* ให้อยู่เหนือองค์ประกอบอื่น */
  color: #2b2b2b;
}

    /* Slide Menu Styles */
    .slide-menu {
      position: fixed;
      top: 0;
      left: -250px;
      width: 250px;
      height: 100%;
      background-color: #ffffff;
      box-shadow: 2px 0 5px rgba(0, 0, 0, 0.2);
      transition: left 0.3s ease;
      z-index: 999;
      padding: 20px;
    }

    .slide-menu.open {
      left: 0;
    }

    .slide-menu ul {
      list-style: none;
      padding: 0;
      margin: 0;
    }

    .slide-menu li {
      margin: 15px 0;
    }

    .slide-menu a {
      text-decoration: none;
      font-weight: 600;
      color: #2b2b2b;
      font-size: 14px;
      letter-spacing: 1px;
    }

    .slide-menu a:hover {
      text-decoration: underline;
    }

    /* Close Button Styles */
    .close-menu {
      font-size: 24px;
      cursor: pointer;
      position: absolute;
      top: 20px;
      right: 20px;
      z-index: 1001;
      color: #2b2b2b;
    }

    .responsive-image img {
      width: 100%; /* ให้ภาพขยายเต็มความกว้างของ container */
      height: auto; /* รักษาอัตราส่วนของภาพ */
      max-height: 600px; /* กำหนดความสูงสูงสุด */
      object-fit: contain; /* ปรับให้ภาพอยู่ใน container โดยไม่ถูกครอบ */
    }

    .video-section {
      width: 100%; /* ให้ container ของวิดีโอขยายเต็มความกว้าง */
      display: flex;
      justify-content: center;
      align-items: center;
      background-color: #f6f6f6; /* เพิ่มพื้นหลัง (ถ้าต้องการ) */
      padding: 0; /* ลบ Padding */
      margin: 0; /* ลบ Margin */
      overflow: hidden; /* ป้องกันการล้นของวิดีโอ */
    }

    .video-section video {
      width: 100%; /* ให้วิดีโอขยายเต็มความกว้างของ container */
      height: auto; /* รักษาอัตราส่วนของวิดีโอ */
      max-height: 600px; /* กำหนดความสูงสูงสุด */
      object-fit: contain; /* ปรับให้วิดีโออยู่ใน container โดยไม่ถูกครอบ */
    }

    /* สำหรับหน้าจอขนาดใหญ่ (PC) */
    @media (min-width: 1024px) {
      .video-section video {
        width: 1920px; /* ขยายเต็มความกว้างของหน้าจอ */
        height: 600px; /* รักษาอัตราส่วน */
      }
      .product-row .row {
        display: flex; /* ใช้ Flexbox */
        flex-wrap: nowrap; /* ไม่ให้แถวห่อ */
        justify-content: center; /* จัดให้อยู่ตรงกลาง */
        gap: 20px; /* เพิ่มระยะห่างระหว่างผลิตภัณฑ์ */
      }

      .product-row .col {
        flex: 0 0 auto; /* ให้คอลัมน์มีขนาดตามเนื้อหา */
        width: auto; /* ปรับขนาดตามเนื้อหา */
        text-align: center; /* จัดข้อความให้อยู่ตรงกลาง */
      }

      .product-row img {
        max-width: 250px; /* ลดขนาดรูปภาพให้เหมาะสม */
      }
    }

    /* สำหรับหน้าจอขนาดกลาง (แท็บเล็ต) */
    @media (min-width: 768px) and (max-width: 1023px) {
      .video-section video {
        max-width: 600px; /* จำกัดความกว้างสูงสุด */
        height: auto; /* รักษาอัตราส่วน */
      }
      .product-row .row {
        flex-direction: row; /* เรียงแนวนอน */
        justify-content: space-around;
      }
      .product-row img {
        max-width: 150px; /* ขนาดรูปภาพสำหรับแท็บเล็ต */
      }
    }

    /* สำหรับหน้าจอขนาดเล็ก (มือถือ) */
    @media (max-width: 767px) {
      .top-bar h1 {
          font-size: 22px;
      }
      .menu-bar {
        display: none; /* ซ่อนเมนู */
      }
      .hero-section {
          padding: 20px;
          background-size: contain;
      }
      .product-row {
        display: flex; /* ใช้ Flexbox */
        justify-content: center; /* จัดให้อยู่ตรงกลางแนวนอน */
        align-items: center; /* จัดให้อยู่ตรงกลางแนวตั้ง */
        flex-direction: column; /* เรียงเนื้อหาในแนวตั้ง */
        min-height: 30vh; /* ให้ความสูงเต็มหน้าจอ */
        text-align: center; /* จัดข้อความให้อยู่ตรงกลาง */
      }
      .product-row img {
        max-width: 150px; /* ลดขนาดรูปภาพให้เหมาะสม */
        margin-bottom: 10px; /* เพิ่มระยะห่างระหว่างรูปภาพ */
      }
      .product-row p {
        font-size: 14px; /* ขนาดตัวอักษรที่เหมาะสม */
      }
      .product-row .row {
        flex-direction: column; /* เปลี่ยนการเรียงเป็นแนวตั้ง */
        align-items: center; /* จัดให้อยู่ตรงกลาง */
      }
      .product-row .col {
        max-width: 100%; /* ให้แต่ละคอลัมน์ใช้พื้นที่เต็ม */
        margin-bottom: 20px; /* เพิ่มระยะห่างระหว่างผลิตภัณฑ์ */
      }
      .product-row img {
        max-width: 1%; /* ลดขนาดรูปภาพให้เหมาะสม */
      }
      .product-row .row {
        display: -webkit-box; /* ใช้ Flexbox */
        flex-wrap: nowrap; /* ไม่ให้แถวห่อ */
        overflow-x: auto; /* เพิ่มการเลื่อนแนวนอน */
        justify-content: flex-start; /* จัดเรียงจากซ้ายไปขวา */
        gap: 20px; /* เพิ่มระยะห่างระหว่างผลิตภัณฑ์ */
        padding: 10px; /* เพิ่ม Padding เพื่อให้เลื่อนง่ายขึ้น */
      }
      .product-row .col {
        flex: 0 0 auto; /* ให้คอลัมน์มีขนาดตามเนื้อหา */
        width: auto; /* ปรับขนาดตามเนื้อหา */
        text-align: center; /* จัดข้อความให้อยู่ตรงกลาง */
      }
      .product-row img {
        max-width: 120px; /* ลดขนาดรูปภาพให้เหมาะสม */
      }
    }

    /* Store Section - Responsive Styles */
@media (max-width: 767px) {
  .store-section .row {
    display: flex;
    flex-direction: column; /* เปลี่ยนการเรียงเป็นแนวตั้ง */
  }

  .store-section .col-md-6.text-center {
    order: -1; /* ย้ายรูปภาพไปอยู่ด้านบน */
    margin-bottom: 20px; /* เพิ่มระยะห่างด้านล่าง */
  }
}

    p {
      margin-top: 0;
      margin-bottom: 0rem;
    }

    .carousel img {
  max-height: 800px; /* กำหนดความสูงสูงสุด */
  object-fit: cover; /* ครอบรูปภาพให้เต็มพื้นที่ */
}

.custom-image {
  max-width: 100%; /* ให้รูปภาพขยายเต็มความกว้างของ container */
  height: auto; /* รักษาอัตราส่วนของรูปภาพ */
  max-height: 500px; /* กำหนดความสูงสูงสุด */
  object-fit: contain; /* ปรับให้รูปภาพอยู่ใน container โดยไม่ถูกครอบ */
  margin: 0 auto; /* จัดให้อยู่ตรงกลาง */
  border-radius: 10px; /* เพิ่มมุมโค้งเพื่อความสวยงาม */
  box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1); /* เพิ่มเงาเพื่อความโดดเด่น */
}

.row {
    --bs-gutter-x: -0.5rem; /* ลดระยะห่างแนวนอน */
    --bs-gutter-y: 0; /* ไม่มีระยะห่างแนวตั้ง */
}

.store-section {
  padding: 40px 0;
}

.store-section h3 {
  font-size: 24px;
  font-weight: bold;
  margin-bottom: 20px;
}

.store-list {
  list-style: none;
  padding: 0;
  margin: 0;
}

.store-list li {
  margin-bottom: 10px;
}

.store-link {
  font-size: 16px; /* ขนาดตัวอักษร */
  font-weight: bold; /* ทำให้ตัวอักษรหนา */
  /*color: #e48d95;  สีข้อความ */
  text-decoration: none; /* ลบขีดเส้นใต้ */
  transition: color 0.3s ease; /* เพิ่มเอฟเฟกต์เมื่อโฮเวอร์ */
}

.store-link:hover {
  color: #d9534f; /* เปลี่ยนสีเมื่อโฮเวอร์ */
  text-decoration: underline; /* เพิ่มขีดเส้นใต้เมื่อโฮเวอร์ */
}

.store-image {
  max-width: 100%;
  height: auto;
  border-radius: 10px;
  box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
}

.social-icons {
  margin-top: 20px;
}

.social-icons a {
  margin-right: 10px;
  font-size: 24px; /* ขนาดไอคอน */
  color: #333; /* สีของไอคอน */
  text-decoration: none; /* ลบขีดเส้นใต้ */
}

.social-icons a:hover {
  color: #e48d95; /* สีเมื่อโฮเวอร์ */
}

.footer-section {
  background-color: #f1ece3; /* สีพื้นหลัง */
  padding: 20px 0; /* ระยะห่างด้านบนและล่าง */
  text-align: center; /* จัดข้อความให้อยู่ตรงกลาง */
}

.footer-title {
  font-size: 18px; /* ขนาดตัวอักษร */
  font-weight: bold; /* น้ำหนักตัวอักษร */
  color: #2b2b2b; /* สีข้อความ */
  margin: 0; /* ลบระยะห่าง */
}

.footer-tagline {
  font-size: 14px; /* ขนาดตัวอักษร */
  color: #555; /* สีข้อความ */
  margin: 5px 0 0; /* ระยะห่างด้านบน */
  letter-spacing: 1px; /* ระยะห่างระหว่างตัวอักษร */
}

.product-title {
  color: #333; /* สีข้อความ */
  font-weight: 600; /* น้ำหนักตัวอักษร */
  font-size: 14px; /* ขนาดตัวอักษร */
  text-decoration: none; /* ลบขีดเส้นใต้ */
  margin: 0; /* ลบระยะห่าง */
}

.product-title a {
  color: #333; /* เปลี่ยนสีข้อความ */
  text-decoration: none; /* ลบขีดเส้นใต้ */
  font-weight: normal; /* เปลี่ยนน้ำหนักตัวอักษร */
}

.product-title a:hover {
  color: #e48d95; /* เปลี่ยนสีเมื่อโฮเวอร์ */
  text-decoration: none; /* ยังคงไม่มีขีดเส้นใต้เมื่อโฮเวอร์ */
}

a {
  text-decoration: none; /* ลบขีดเส้นใต้ */
  color: inherit; /* ใช้สีเดียวกับข้อความปกติ */
}

a:hover {
  text-decoration: none; /* ยังคงไม่มีขีดเส้นใต้เมื่อโฮเวอร์ */
}

.carousel-control-prev,
.carousel-control-next {
  width: 60px; /* ขนาดปุ่ม */
  height: 60px;
  background-color: rgba(0, 0, 0, 0.5); /* พื้นหลังโปร่งแสง */
  border-radius: 50%; /* ทำให้ปุ่มเป็นวงกลม */
  display: flex;
  justify-content: center;
  align-items: center;
  box-shadow: 0 4px 8px rgba(0, 0, 0, 0.3); /* เพิ่มเงา */
  transition: transform 0.3s ease, background-color 0.3s ease; /* เอฟเฟกต์เมื่อโฮเวอร์ */
  top: 50%; /* จัดให้อยู่ตรงกลางแนวตั้ง */
  transform: translateY(-50%); /* ปรับตำแหน่งให้ตรงกลาง */
}

.carousel-control-prev:hover,
.carousel-control-next:hover {
  background-color: rgba(0, 0, 0, 0.8); /* เปลี่ยนสีพื้นหลังเมื่อโฮเวอร์ */
  transform: translateY(-50%) scale(1.1); /* ขยายขนาดเล็กน้อยเมื่อโฮเวอร์ */
}

.carousel-control-prev-icon,
.carousel-control-next-icon {
  width: 30px; /* ขนาดไอคอน */
  height: 30px;
  filter: invert(1); /* เปลี่ยนสีไอคอนเป็นสีขาว */
}
.social-icons {
  display: flex;
  justify-content: center;
  gap: 15px; /* ระยะห่างระหว่างไอคอน */
  margin-top: 20px;
}

.social-icons a {
  display: inline-block;
}

.social-icon {
  width: 50px; /* กำหนดขนาดไอคอน */
  height: 50px;
  border-radius: 50%; /* ทำให้กรอบเป็นวงกลม */
  object-fit: cover; /* ปรับให้รูปภาพอยู่ในกรอบโดยไม่ถูกครอบ */
  border: 2px solid #ddd; /* เพิ่มเส้นขอบ */
  transition: transform 0.3s ease, border-color 0.3s ease; /* เพิ่มเอฟเฟกต์เมื่อโฮเวอร์ */
}

.social-icon:hover {
  transform: scale(1.1); /* ขยายขนาดเล็กน้อยเมื่อโฮเวอร์ */
  border-color: #e48d95; /* เปลี่ยนสีขอบเมื่อโฮเวอร์ */
}

.product-image {
  position: relative;
  width: 100%;
  height: auto;
}

.new-icon {
  position: absolute;
  top: 10px;
  right: 10px;
  background-color: #e48d95; /* สีพื้นหลัง */
  color: #fff; /* สีข้อความ */
  font-size: 12px; /* ขนาดตัวอักษร */
  font-weight: bold; /* น้ำหนักตัวอักษร */
  padding: 5px 10px; /* ระยะห่างภายใน */
  border-radius: 5px; /* มุมโค้ง */
  box-shadow: 0 2px 5px rgba(0, 0, 0, 0.2); /* เพิ่มเงา */
  animation: blink 1s infinite; /* เพิ่ม Animation */
}

/* Animation สำหรับการกระพริบ */
@keyframes blink {
  0%, 100% {
    opacity: 1; /* แสดงเต็ม */
  }
  50% {
    opacity: 0; /* ซ่อน */
  }
}
  </style>
</head>
<body>
   <!-- Google Analytics -->
<script async src="https://www.googletagmanager.com/gtag/js?id=G-PKRFPM8SZB"></script>
<script>
  window.dataLayer = window.dataLayer || [];
  function gtag(){dataLayer.push(arguments);}
  gtag('js', new Date());
  gtag('config', 'G-PKRFPM8SZB', {
    'anonymize_ip': true, // ปิดการระบุตัวตน IP
    'send_page_view': true // ส่งข้อมูลการดูหน้าเว็บ
  });
</script>
  <!-- End Google Analytics -->

<!-- Header -->
<header>
  <div class="top-bar">
    <img src="./img/Journal_Logo No Icon_program title for watermark copy 2.png" alt="JOURNAL Logo" class="logo">
    <p class="tagline">WHERE WONDER BEGINS</p>
  </div>
  <div class="menu-bar">
    <ul>
      <li><a href="https://www.journal-boutique.com/product/lists?category=body?utm_source=webeventbody&utm_medium=web&utm_campaign=eventpp" onclick="gtag('event', 'Menu_BODY_OIL', { 'event_category': 'Menu', 'event_label': 'BODY OIL' });" target="_blank">BODY OIL</a></li>
      <li><a href="https://www.journal-boutique.com/product/lists?category=perfume?utm_source=webeventperfume&utm_medium=web&utm_campaign=eventpp" onclick="gtag('event', 'Menu_PERFUME', { 'event_category': 'Menu', 'event_label': 'PERFUME' });" target="_blank">PERFUME</a></li>
      <li><a href="https://www.journal-boutique.com/product/lists?category=bodycare?utm_source=webeventbodycare&utm_medium=web&utm_campaign=eventpp" onclick="gtag('event', 'Menu_BODY CARE', { 'event_category': 'Menu', 'event_label': 'BODY CARE' });" target="_blank">BODY CARE</a></li>
      <li><a href="https://www.journal-boutique.com/?utm_source=webeventhome&utm_medium=web&utm_campaign=eventpp" onclick="gtag('event', 'Menu_HOME', { 'event_category': 'Menu', 'event_label': 'HOME' });" target="_blank">HOME</a></li>
      <li><a href="https://www.journal-boutique.com/product/collections?utm_source=webeventcollections&utm_medium=web&utm_campaign=eventpp" onclick="gtag('event', 'Menu_COLLECTION', { 'event_category': 'Menu', 'event_label': 'COLLECTION' });" target="_blank">COLLECTION</a></li>
      <li><a href="https://www.journal.co.th/index.html?utm_source=webeventoutstore&utm_medium=web&utm_campaign=eventpp" onclick="gtag('event', 'Menu_OUR_STORES', { 'event_category': 'Menu', 'event_label': 'OUR STORES' });" target="_blank">OUR STORES</a></li>
      <li><a href="https://www.journal-boutique.com/about?utm_source=webeventcollections&utm_medium=web&utm_campaign=eventpp" onclick="gtag('event', 'Menu_ABOUT_US', { 'event_category': 'Menu', 'event_label': 'ABOUT US' });" target="_blank">ABOUT US</a></li>
    </ul>
  </div>
  <!-- Hamburger Menu Button -->
  <div class="hamburger-menu" onclick="toggleMenu()">
    ☰
  </div>
</header>

<!-- Slide Menu -->
<div id="slide-menu" class="slide-menu">
  <div class="close-menu" onclick="toggleMenu()">✖</div>
  <ul>
      <li><a href="https://www.journal-boutique.com/product/lists?category=body?utm_source=webeventbody&utm_medium=web&utm_campaign=eventpp" onclick="gtag('event', 'Menu_BODY_OIL', { 'event_category': 'Menu', 'event_label': 'BODY OIL' });" target="_blank">BODY OIL</a></li>
      <li><a href="https://www.journal-boutique.com/product/lists?category=perfume?utm_source=webeventperfume&utm_medium=web&utm_campaign=eventpp" onclick="gtag('event', 'Menu_PERFUME', { 'event_category': 'Menu', 'event_label': 'PERFUME' });" target="_blank">PERFUME</a></li>
      <li><a href="https://www.journal-boutique.com/product/lists?category=bodycare?utm_source=webeventbodycare&utm_medium=web&utm_campaign=eventpp" onclick="gtag('event', 'Menu_BODY CARE', { 'event_category': 'Menu', 'event_label': 'BODY CARE' });" target="_blank">BODY CARE</a></li>
      <li><a href="https://www.journal-boutique.com/?utm_source=webeventhome&utm_medium=web&utm_campaign=eventpp" onclick="gtag('event', 'Menu_HOME', { 'event_category': 'Menu', 'event_label': 'HOME' });" target="_blank">HOME</a></li>
      <li><a href="https://www.journal-boutique.com/product/collections?utm_source=webeventcollections&utm_medium=web&utm_campaign=eventpp" onclick="gtag('event', 'Menu_COLLECTION', { 'event_category': 'Menu', 'event_label': 'COLLECTION' });" target="_blank">COLLECTION</a></li>
      <li><a href="https://www.journal.co.th/index.html?utm_source=webeventoutstore&utm_medium=web&utm_campaign=eventpp" onclick="gtag('event', 'Menu_OUR_STORES', { 'event_category': 'Menu', 'event_label': 'OUR STORES' });" target="_blank">OUR STORES</a></li>
      <li><a href="https://www.journal-boutique.com/about?utm_source=webeventcollections&utm_medium=web&utm_campaign=eventpp" onclick="gtag('event', 'Menu_ABOUT_US', { 'event_category': 'Menu', 'event_label': 'ABOUT US' });" target="_blank">ABOUT US</a></li>
    </ul>
</div>



<!-- Hero Section -->

<div id="carouselExample" class="carousel slide" data-bs-ride="carousel">
  <div class="carousel-inner">
    <!-- วิดีโอ -->
    <div class="carousel-item active">
      <video autoplay loop muted playsinline class="d-block w-100">
        <source src="./img/Journal Longform.mp4" type="video/mp4">
        Your browser does not support the video tag.
      </video>
    </div>
    <!-- รูปภาพ 1 -->
    <div class="carousel-item">
      <a href="https://www.journal-boutique.com/product/lists?category=body?utm_source=webeventbody&utm_medium=web&utm_campaign=eventpp" 
     onclick="gtag('event', 'click_BODYOIL1', { 'event_category': 'Carousel', 'event_label': 'BODY OIL Banner 1' });" target="_blank">
        <img src="./img/eventpp1.jpg" class="d-block w-100" alt="JOURNAL BODY OIL Banner 1">
      </a>
    </div>
    <!-- รูปภาพ 2 -->
    <div class="carousel-item">
      <a href="https://www.journal-boutique.com/product/lists?category=body?utm_source=webeventbody&utm_medium=web&utm_campaign=eventpp" 
     onclick="gtag('event', 'lick_BODYOIL2', { 'event_category': 'Carousel', 'event_label': 'BODY OIL Banner 2' });" target="_blank">
        <img src="./img/eventpp2.jpg" class="d-block w-100" alt="JOURNAL BODY OIL Banner 2">
      </a>
    </div>
  </div>
  <!-- ปุ่มเลื่อน -->
  <button class="carousel-control-prev" type="button" data-bs-target="#carouselExample" data-bs-slide="prev">
    <span class="carousel-control-prev-icon" aria-hidden="true"></span>
    <span class="visually-hidden">Previous</span>
  </button>
  <button class="carousel-control-next" type="button" data-bs-target="#carouselExample" data-bs-slide="next">
    <span class="carousel-control-next-icon" aria-hidden="true"></span>
    <span class="visually-hidden">Next</span>
  </button>
</div>
 


<!-- Product Preview Row -->
 
<div class="product-row">
  <div class="container">
    <div class="row row-cols-1 row-cols-sm-2 row-cols-md-4 g-4 justify-content-center">
      <div class="col text-center">
        <a href="https://www.journal-boutique.com/product/Body%20Oil%20180%20ml/legacy/TLBO180ml?utm_source=webeventbodyTLBO180ml&utm_medium=web&utm_campaign=eventpp" 
           onclick="gtag('event', 'Product_THE_LEGACY_BODY_OIL', { 'event_category': 'Product', 'event_label': 'THE LEGACY BODY OIL' });" target="_blank">
          <img src="./img/THELEGACYBodOil.png" alt="THE LEGACY BODY OIL">
          <p class="product-title">THE LEGACY<br>BODY OIL</p>
        </a>
      </div>
      <div class="col text-center">
        <a href="https://www.journal-boutique.com/product/Body%20Oil%20180%20ml/first_love/180ml?utm_source=webeventfirst_love180ml&utm_medium=web&utm_campaign=eventpp" 
           onclick="gtag('event', 'Product_FIRST_LOVE_BODY_OIL', { 'event_category': 'Product', 'event_label': 'FIRST LOVE BODY OIL' });" target="_blank">
          <img src="./img/FIRSTLOVEBodyOil.png" alt="FIRST LOVE BODY OIL">
          <p class="product-title">FIRST LOVE<br>BODY OIL</p>
        </a>
      </div>
      <div class="col text-center">
        <a href="https://www.journal-boutique.com/product/Body%20Oil%20180%20ml/nang_ram/nangrambodyoil?utm_source=webevennang_ram180&utm_medium=web&utm_campaign=eventpp" 
           onclick="gtag('event', 'Product_NANG_RAM_BODY_OIL', { 'event_category': 'Product', 'event_label': 'NANG RAM BODY OIL' });" target="_blank">
          <img src="./img/NANGRAMBodyoil.png" alt="NANG RAM BODY OIL">
          <p class="product-title">NANG RAM<br>BODY OIL</p>
        </a>
      </div>
      <div class="col text-center">
        <a href="https://www.journal-boutique.com/product/Body%20Oil%20180%20ml/promise/180ml?utm_source=webeventpromise180&utm_medium=web&utm_campaign=eventpp" 
           onclick="gtag('event', 'Product_PROMISE_BODY_OIL', { 'event_category': 'Product', 'event_label': 'PROMISE BODY OIL' });" target="_blank">
          <img src="./img/PROMISEBodyOil.png" alt="PROMISE BODY OIL">
          <p class="product-title">PROMISE<br>BODY OIL</p>
        </a>
      </div>
      <div class="col text-center">
        <a href="https://www.journal-boutique.com/product/Body%20Oil%20180%20ml/charm/CMBO?utm_source=webeventcharm180&utm_medium=web&utm_campaign=eventpp" 
           onclick="gtag('event', 'Product_CHARM_BODY_OIL', { 'event_category': 'Product', 'event_label': 'CHARM BODY OIL' });" target="_blank">
          <img src="./img/CHARMBodyoil.png" alt="CHARM BODY OIL">
          <p class="product-title">CHARM<br>BODY OIL</p>
        </a>
      </div>
      <div class="col text-center position-relative">
        <a href="https://www.journal-boutique.com/product/Body%20Oil%20180%20ml/Forever%20Love/FORBO180ML" 
           onclick="gtag('event', 'Product_FOREVER_LOVE_BODY_OIL', { 'event_category': 'Product', 'event_label': 'FOREVER LOVE BODY OIL' });" target="_blank">
          <img src="./img/forever01.png" alt="FOREVER LOVE BODY OIL" class="product-image">
          <p class="product-title">FOREVER LOVE<br>BODY OIL</p>
        </a>
        <!-- ไอคอนสินค้าใหม่ -->
        <span class="new-icon">NEW</span>
      </div>
    </div>
  </div>
</div>

<div id="customCarousel" class="carousel slide">
  <div class="carousel-inner">
    <!-- รูปภาพ 1 -->
    <div class="carousel-item active">
      <img src="./img/UI-LandingPage-02.jpg" class="d-block w-100" alt="JOURNAL BODY OIL Banner 1">
    </div>
    <!-- รูปภาพ 2 -->
    <div class="carousel-item">
      <img src="./img/UI-LandingPage-03.jpg" class="d-block w-100" alt="JOURNAL BODY OIL Banner 2">
    </div>
    <!-- รูปภาพ 3 -->
    <div class="carousel-item">
      <img src="./img/UI-LandingPage-04.jpg" class="d-block w-100" alt="JOURNAL BODY OIL Banner 3">
    </div>
  </div>
  <!-- ปุ่มเลื่อน -->
  <button id="customPrev" class="carousel-control-prev" type="button">
    <span class="carousel-control-prev-icon" aria-hidden="true"></span>
    <span class="visually-hidden">Previous</span>
  </button>
  <button id="customNext" class="carousel-control-next" type="button">
    <span class="carousel-control-next-icon" aria-hidden="true"></span>
    <span class="visually-hidden">Next</span>
  </button>
</div>



<!-- Image Grid Section -->
<div class="image-grid-section">
  <div class="container">
    <div class="row">
      <!-- รูปใหญ่ -->
      <div class="col-md-6">
        <a href="https://www.journal-boutique.com/search-result?keyword=body+oil" >
        <img src="./img/band1.png" alt="Large Image" class="img-fluid large-image">
        </a>
      </div>
      <!-- รูปเล็ก 4 รูป -->
      <div class="col-md-6">
        <div class="row">
          <div class="col-6">
            <div class="small-image-container">
              <img src="./img/band2.png" alt="Small Image 1" class="img-fluid small-image">
              <!-- <p class="image-caption">BODY OIL</p> -->
            </div>
          </div>
          <div class="col-6">
            <div class="small-image-container">
              <img src="./img/band3.png" alt="Small Image 2" class="img-fluid small-image">
              <!-- <p class="image-caption">BODY OIL SUNSCREEN</p> -->
            </div>
          </div>
          <div class="col-6">
            <div class="small-image-container">
              <img src="./img/band4.png" alt="Small Image 3" class="img-fluid small-image">
              <!-- <p class="image-caption">MILK BATH AND BODY OIL</p> -->
            </div>
          </div>
          <div class="col-6">
            <div class="small-image-container">
              <img src="./img/band5.png" alt="Small Image 4" class="img-fluid small-image">
              <!-- <p class="image-caption">SHOWER OIL</p> -->
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Product Row -->
<div class="product-row">
  <div class="container">
    <div class="row row-cols-1 row-cols-sm-2 row-cols-md-4 g-4 justify-content-center">
      <div class="col text-center">
        <a href="https://www.journal-boutique.com/product/lists?category=body&sub_category=Body+Oil+180+ml" 
           onclick="gtag('event', 'category_BODY_OIL', { 'event_category': 'Product', 'event_label': 'BODY OIL' });" target="_blank">
          <img src="./img/THELEGACYBodOil.png" alt="THE LEGACY BODY OIL">
          <p class="product-title">BODY OIL</p>
        </a>
      </div>
      <div class="col text-center">
        <a href="https://www.journal-boutique.com/product/lists?category=body&sub_category=Body-Oil-Sunscreen" 
           onclick="gtag('event', 'category_BODY_OIL_SUNSCREEN', { 'event_category': 'Product', 'event_label': 'BODY OIL SUNSCREEN' });" target="_blank">
          <img src="./img/THELEGACY.png" alt="THE LEGACY BODY OIL SUNSCREEN">
          <p class="product-title">BODY OIL SUNSCREEN</p>
        </a>
      </div>
      <div class="col text-center">
        <a href="https://www.journal-boutique.com/product/lists?category=body&sub_category=Milk+Bath+%26+Body+Oil" 
           onclick="gtag('event', 'category_MILKBATH&BODY_OIL', { 'event_category': 'Product', 'event_label': 'THE LEGACY MILK BATH & BODY OIL' });" target="_blank">
          <img src="./img/THELEGACYMilk.png" alt="THE LEGACY MILK BATH & BODY OIL">
          <p class="product-title">MILK BATH & BODY OIL</p>
        </a>
      </div>
      <div class="col text-center">
        <a href="https://www.journal-boutique.com/product/lists?category=body&sub_category=Shower-Gel" 
           onclick="gtag('event', 'category_SHOWER_OIL', { 'event_category': 'Product', 'event_label': 'SHOWER OIL' });" target="_blank">
          <img src="./img/THELEGACYShow.png" alt="SHOWER OIL">
          <p class="product-title">SHOWER OIL</p>
        </a>
      </div>
    </div>
  </div>
</div>

<!-- Store Section -->
<div class="store-section">
  <div class="container">
    <div class="row align-items-center">
      <!-- รายการที่ตั้งร้านค้า -->
      <div class="col-md-6">
        <h3>
  <a href="https://www.journal.co.th/index.html" target="_blank" class="store-link" 
     onclick="gtag('event', 'navigate_OUR_STORE', { 'event_category': 'OUR STORE', 'event_label': 'OUR STORE' });">
    OUR STORE
  </a>
</h3>
        <ul class="store-list">
          <li>
            <a href="https://maps.app.goo.gl/eX383pVSqxa8pdoK6" target="_blank" aria-label="Navigate to ONENIMMAN" class="store-link" 
               onclick="gtag('event', 'store_ONENIMMAN', { 'event_category': 'Store Location', 'event_label': 'ONENIMMAN' });">
              1. สาขาโครงการวันนิมมาน เชียงใหม่
            </a>
          </li>
          <li>
            <a href="https://maps.app.goo.gl/BMoaaEbMteoFTVA6A" target="_blank" aria-label="Navigate to MAYA" class="store-link" 
               onclick="gtag('event', 'store_MAYA', { 'event_category': 'Store Location', 'event_label': 'MAYA' });">
              2. สาขาศูนย์การค้าเมญ่า เชียงใหม่ ชั้น G
            </a>
          </li>
          <li>
            <a href="https://maps.app.goo.gl/5Gt6qy9FHqUoXc5S8" target="_blank" aria-label="Navigate to CENTRAL CHIANGMAI AIRPORT" class="store-link" 
               onclick="gtag('event', 'store_CENTRAL_CHIANGMAI_AIRPORT', { 'event_category': 'Store Location', 'event_label': 'CENTRAL CHIANGMAI AIRPORT' });">
              3. สาขาเซ็นทรัลพลาซา เชียงใหม่ แอร์พอร์ต
            </a>
          </li>
          <li>
            <a href="https://maps.app.goo.gl/kDiiqfPoyah5YGE18" target="_blank" aria-label="Navigate to SIAM SQUARE ONE" class="store-link" 
               onclick="gtag('event', 'store_SIAM_SQUARE_ONE', { 'event_category': 'Store Location', 'event_label': 'SIAM SQUARE ONE' });">
              4. สาขาสยามสแควร์วัน ชั้น 3
            </a>
          </li>
          <li>
            <a href="https://maps.app.goo.gl/93CkVzB4RuKWs6aS9" target="_blank" aria-label="Navigate to CENTRAL WORLD" class="store-link" 
               onclick="gtag('event', 'store_CENTRAL_WORLD', { 'event_category': 'Store Location', 'event_label': 'CENTRAL WORLD' });">
              5. สาขาเซ็นทรัลเวิลด์ ชั้น 2
            </a>
          </li>
          <li>
            <a href="https://maps.app.goo.gl/9D8gNNLs1JqXXEeP8" target="_blank" aria-label="Navigate to CENTRAL RAMA 9" class="store-link" 
               onclick="gtag('event', 'store_CENTRAL_RAMA_9', { 'event_category': 'Store Location', 'event_label': 'CENTRAL RAMA 9' });">
              6. สาขาเซ็นทรัลพระราม 9 ชั้น 1
            </a>
          </li>
          <li>
            <a href="https://maps.app.goo.gl/FxcrwywpC67Tr1Gz6" target="_blank" aria-label="Navigate to ICON SIAM" class="store-link" 
               onclick="gtag('event', 'store_ICON_SIAM', { 'event_category': 'Store Location', 'event_label': 'ICON SIAM' });">
              7. สาขาไอคอนสยาม ชั้น 2
            </a>
          </li>
          <li>
            <a href="https://maps.app.goo.gl/gipnqbbymLZuNghT9" target="_blank" aria-label="Navigate to CENTRAL WESTGATE" class="store-link" 
               onclick="gtag('event', 'store_CENTRAL_WESTGATE', { 'event_category': 'Store Location', 'event_label': 'CENTRAL WESTGATE' });">
              8. สาขาเซ็นทรัลเวสต์เกต ชั้น 1
            </a>
          </li>
          <li>
            <a href="https://maps.app.goo.gl/f83PX4MLVjFDkpob9" target="_blank" aria-label="Navigate to SIAM CENTER" class="store-link" 
               onclick="gtag('event', 'store_SIAM_CENTER', { 'event_category': 'Store Location', 'event_label': 'SIAM CENTER' });">
              9. สาขาสยามเซ็นเตอร์ ชั้น M
            </a>
          </li>
          <li>
            <a href="https://maps.app.goo.gl/ksDZwV7NKG28N1EZA" target="_blank" aria-label="Navigate to EMSPHERE" class="store-link" 
               onclick="gtag('event', 'store_EMSPHERE', { 'event_category': 'Store Location', 'event_label': 'EMSPHERE' });">
              10. สาขาเอ็มสเฟียร์ ชั้น 1
            </a>
          </li>
          <li>
            <a href="https://maps.app.goo.gl/aXDhd8LDW9crMcMc6" target="_blank" aria-label="Navigate to CENTRAL LADPRAO" class="store-link" 
               onclick="gtag('event', 'store_CENTRAL_LADPRAO', { 'event_category': 'Store Location', 'event_label': 'CENTRAL LADPRAO' });">
              11. สาขาเซ็นทรัลลาดพร้าว ชั้น 2
            </a>
          </li>
          <li>
            <a href="https://maps.app.goo.gl/XTJXyHiSKbaokKSSA" target="_blank" aria-label="Navigate to MEGA BANGNA" class="store-link" 
               onclick="gtag('event', 'store_MEGA_BANGNA', { 'event_category': 'Store Location', 'event_label': 'MEGA BANGNA' });">
              12. สาขาเมกาบางนา ชั้น 2
            </a>
          </li>
          <li>
            <a href="https://maps.app.goo.gl/AwtWXkcqTBrQkb5Q6" target="_blank" aria-label="Navigate to TERMINAL 21 ASOK" class="store-link" 
               onclick="gtag('event', 'store_TERMINAL_21_ASOK', { 'event_category': 'Store Location', 'event_label': 'TERMINAL 21 ASOK' });">
              13. สาขาทอร์มินอล 21 อโศก ชั้น 3
            </a>
          </li>
          <li>
            <a href="https://maps.app.goo.gl/Nrajikt5Y6t5sLVAA" target="_blank" aria-label="Navigate to TERMINAL 21 PATTAYA" class="store-link" 
               onclick="gtag('event', 'store_TERMINAL_21_PATTAYA', { 'event_category': 'Store Location', 'event_label': 'TERMINAL 21 PATTAYA' });">
              14. สาขาเทอร์มินอล 21 พัทยา ชั้น 1
            </a>
          </li>
          <li>
            <a href="https://maps.app.goo.gl/r4D1LNXDenq6TmtN8" target="_blank" aria-label="Navigate to CENTRAL FESTIVAL PATTAYA" class="store-link" 
               onclick="gtag('event', 'store_CENTRAL_FESTIVAL_PATTAYA', { 'event_category': 'Store Location', 'event_label': 'CENTRAL FESTIVAL PATTAYA' });">
              15. สาขาเซ็นทรัล พัทยา ชั้น 2
            </a>
          </li>
          
        </ul>
        <!-- Social Icons Section -->
<div class="social-icons">
  <a href="https://www.journal-boutique.com" aria-label="Website" target="_blank" 
     onclick="gtag('event', 'Social_Website', { 'event_category': 'Social Link', 'event_label': 'Website' });">
    <img src="./img/web.png" alt="Website" class="social-icon">
  </a>
  <a href="https://shopee.co.th/journalboutique" aria-label="Shopee" target="_blank" 
     onclick="gtag('event', 'Social_Shopee', { 'event_category': 'Social Link', 'event_label': 'Shopee' });">
    <img src="./img/shopee.png" alt="Shopee" class="social-icon">
  </a>
  <a href="https://www.lazada.co.th/shop/journal-boutique" aria-label="Lazada" target="_blank" 
     onclick="gtag('event', 'Social_Lazada', { 'event_category': 'Social Link', 'event_label': 'Lazada' });">
    <img src="./img/lazada.png" alt="Lazada" class="social-icon">
  </a>
  <a href="https://www.tiktok.com/@journalboutiqueth" aria-label="TikTok" target="_blank" 
     onclick="gtag('event', 'Social_TikTok', { 'event_category': 'Social Link', 'event_label': 'TikTok' });">
    <img src="./img/tiktok.png" alt="TikTok" class="social-icon">
  </a>
  <a href="https://shop.line.me/@journalboutique" aria-label="LINE Shopping" target="_blank" 
     onclick="gtag('event', 'Social_LINE', { 'event_category': 'Social Link', 'event_label': 'LINE Shopping' });">
    <img src="./img/line.png" alt="LINE Shopping" class="social-icon">
  </a>
</div>
      </div>
      <!-- รูปภาพ -->
       
      <div class="col-md-6 text-center">
        <img src="./img/outstore_01.png" alt="Store Image" class="img-fluid store-image">
      </div>
    </div>
  </div>
</div>


<!-- Video Section -->


<script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.8/dist/umd/popper.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
  function toggleMenu() {
  const menu = document.getElementById('slide-menu');
  const hamburger = document.querySelector('.hamburger-menu');
  
  // Toggle the 'open' class for the slide menu
  menu.classList.toggle('open');
  
  // ซ่อนหรือแสดงปุ่ม Hamburger Menu
  if (menu.classList.contains('open')) {
    hamburger.style.display = 'none'; // ซ่อนปุ่ม Hamburger
  } else {
    hamburger.style.display = 'block'; // แสดงปุ่ม Hamburger
  }
}

document.addEventListener('DOMContentLoaded', function () {
    var carouselElement = document.querySelector('#carouselExample');
    if (carouselElement) {
      var carousel = new bootstrap.Carousel(carouselElement);
      console.log('Carousel initialized successfully');
    } else {
      console.error('Carousel element not found');
    }
  });

  document.addEventListener('DOMContentLoaded', function () {
  const carousel = document.querySelector('#customCarousel');
  const items = carousel.querySelectorAll('.carousel-item');
  const prevButton = document.querySelector('#customPrev');
  const nextButton = document.querySelector('#customNext');
  let currentIndex = 0;

  // ฟังก์ชันแสดงรูปภาพตามดัชนี
  function showSlide(index) {
    items.forEach((item, i) => {
      item.classList.remove('active');
      if (i === index) {
        item.classList.add('active');
      }
    });
  }

  // ฟังก์ชันเลื่อนไปยังรูปก่อนหน้า
  prevButton.addEventListener('click', function () {
    currentIndex = (currentIndex - 1 + items.length) % items.length;
    showSlide(currentIndex);
  });

  // ฟังก์ชันเลื่อนไปยังรูปถัดไป
  nextButton.addEventListener('click', function () {
    currentIndex = (currentIndex + 1) % items.length;
    showSlide(currentIndex);
  });
});

document.addEventListener('click', function(event) {
  console.log('GA Event Triggered:', event.target);
});
</script>

<!-- Footer Section -->
 
<footer class="footer-section">
  <div class="top-bar">
    <img src="./img/Journal_Logo No Icon_program title for watermark copy 2.png" alt="JOURNAL Logo" class="logo">
    <p class="tagline">WHERE WONDER BEGINS</p>
  </div>
  <!-- <div class="container text-center">
    <p class="footer-title">JOURNAL</p>
    <p class="footer-tagline">WHERE WONDER BEGINS</p>
  </div> -->
</footer>
</body>
</html>
