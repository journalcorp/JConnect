<!DOCTYPE html>
<html lang="th">
<head>  
<link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>JOURNAL</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
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
      padding: 40px 0;
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
      .menu-bar ul {
          flex-direction: column;
          align-items: center;
      }
      .hero-section {
          padding: 20px;
          background-size: contain;
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
      display: none;
      font-size: 24px;
      cursor: pointer;
      position: absolute; /* เปลี่ยนจาก fixed เป็น absolute */
      top: 20px;
      left: 20px;
      z-index: 1000;
    }

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

    /* Show Slide Menu */
    .slide-menu.open {
      left: 0; /* เปลี่ยนจาก right เป็น left */
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
        flex-direction: row; /* เรียงแนวนอน */
        justify-content: center;
      }
      .product-row img {
        max-width: 200px; /* ขนาดรูปภาพสำหรับ PC */
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
      .video-section video {
        max-width: 100%; /* ขยายเต็มความกว้างของ container */
        height: auto; /* รักษาอัตราส่วน */
      }
    }

    @media (max-width: 767px) {
      .hamburger-menu {
        display: block;
      }
      .menu-bar {
        display: none;
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
  </style>
</head>
<body>

<!-- Header -->
<header>
  <div class="top-bar">
    <img src="./img/Journal_Logo No Icon_program title for watermark copy 2.png" alt="JOURNAL Logo" class="logo">
    <p class="tagline">WHERE WONDER BEGINS</p>
  </div>
  <div class="menu-bar">
    <ul>
      <li><a href="#">BODY OIL</a></li>
      <li><a href="#">PERFUME</a></li>
      <li><a href="#">BODY CARE</a></li>
      <li><a href="#">HOME</a></li>
      <li><a href="#">COLLECTION</a></li>
      <li><a href="#">OUR STORES</a></li>
      <li><a href="#">ABOUT US</a></li>
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
    <li><a href="#">ABOUT US</a></li>
    <li><a href="#">NEW</a></li>
    <li><a href="#">BODY OIL</a></li>
    <li><a href="#">PERFUME</a></li>
    <li><a href="#">BODY CARE</a></li>
    <li><a href="#">HOME</a></li>
    <li><a href="#">COLLECTION</a></li>
    <li><a href="#">JOURNAL</a></li>
    <li><a href="#">OUR STORES</a></li>
  </ul>
</div>

<!-- Hero Section -->
 <div class="video-section">
  <video autoplay loop muted playsinline>
    <source src="./img/Journal Longform.mp4" type="video/mp4">
    Your browser does not support the video tag.
  </video>
</div>


<!-- Product Preview Row -->
<div class="product-row">
  <div class="container">
    <div class="row row-cols-1 row-cols-sm-2 row-cols-md-4 g-4 justify-content-center">
      <div class="col text-center">
        <img src="./img/THELEGACYBodOil.png" alt="Legacy">
        <p>THE LEGACY<br>BODY OIL</p>
      </div>
      <div class="col text-center">
        <img src="./img/FIRSTLOVEBodyOil.png" alt="First Love">
        <p>FIRST LOVE<br>BODY OIL</p>
      </div>
      <div class="col text-center">
        <img src="./img/GALANGABodyOil.png" alt="Galanga">
        <p>GALANGA<br>BODY OIL</p>
      </div>
      <div class="col text-center">
        <img src="./img/PROMISEBodyOil.png" alt="Promise">
        <p>FOREVER LOVE<br>BODY OIL</p>
      </div>
      
    </div>
  </div>
</div>
<div class="col text-center">
  <img src="./img/UIPage_01.jpg" alt="First Love Scent" class="img-fluid custom-image">
</div>

<!-- Our Scent Section -->
<!-- <div class="our-scent-section">
  <div class="container">
    <div class="row align-items-center">
      <div class="col-md-6 desc">
        <h6>OUR SCENT</h6>
        <h3>A JOURNEY BACK TO <br>FIRST LOVE</h3>
        <p>
          <span>การเดินทางของรักแรก</span>
          นำคุณกลับสู่ช่วงเวลาแห่งรักแรก<br>
          ด้วยกลิ่นหอมสดชื่นของแพร์<br>
          ที่สื่อถึงความหวังและความอ่อนโยนในหัวใจ<br>
        </p>
        <a href="#">DISCOVER MORE</a>
      </div>
      <div class="col-md-6 text-center">
        <img src="first-love-scent.jpg" alt="First Love Scent" class="img-fluid">
      </div>
    </div>
  </div>
</div> -->
<!-- Image Grid Section -->
 <div class="product-row">
  <div class="container">
    <div class="row row-cols-1 row-cols-sm-2 row-cols-md-4 g-4 justify-content-center">
      <div class="col text-center">
        <img src="./img/FIRSTLOVEBodyOil.png" alt="First Love">
        <p>FIRST LOVE<br>BODY OIL</p>
      </div>
      <div class="col text-center">
        <img src="./img/GALANGABodyOil.png" alt="Galanga">
        <p>GALANGA<br>BODY OIL</p>
      </div>
      <div class="col text-center">
        <img src="./img/PROMISEBodyOil.png" alt="Promise">
        <p>PROMISE<br>BODY OIL</p>
      </div>
      <div class="col text-center">
        <img src="./img/THELEGACYBodOil.png" alt="Legacy">
        <p>THE LEGACY<br>BODY OIL</p>
      </div>
    </div>
  </div>
</div>
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
      <img src="./img/UI-LandingPage-02.JPG" class="d-block w-100" alt="JOURNAL BODY OIL Banner 1">
    </div>
    <!-- รูปภาพ 2 -->
    <div class="carousel-item">
      <img src="./img/UI-LandingPage-03.JPG" class="d-block w-100" alt="JOURNAL BODY OIL Banner 2">
    </div>
    <!-- รูปภาพ 3 -->
    <div class="carousel-item">
      <img src="./img/UI-LandingPage-04.JPG" class="d-block w-100" alt="JOURNAL BODY OIL Banner 3">
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
<!-- <div>
  <img src="./img/BannerCRM01.jpg" alt="JOURNAL BODY OIL Banner" style="width: 100%; height: auto; max-height: 600px; object-fit: cover;">
</div>
<div>
  <img src="./img/web_banner.webp" alt="JOURNAL BODY OIL Banner" >
</div> -->


<!-- <div class="image-grid">
  <div class="container">
    <div class="row row-cols-1 row-cols-sm-2 row-cols-md-4 g-4 justify-content-center">
      <div class="col text-center">
        <img src="./img/FIRSTLOVEBodyOil.png" alt="Body Oil">
        <p>FIRST LOVE<br>BODY OIL</p>
      </div>
      <div class="col text-center">
        <img src="./img/GALANGABodyOil.png" alt="Body Oil Sunscreen">
        <p>GALANGA<br>BODY OIL</p>
      </div>
      <div class="col text-center">
        <img src="./img/PROMISEBodyOil.png" alt="Milk Bath and Body Oil">
        <p>PROMISE<br>BODY OIL</p>
      </div>
      <div class="col text-center">
        <img src="./img/THELEGACYBodOil.png" alt="Shower Oil">
        <p>THE LEGACY<br>BODY OIL</p>
      </div>
    </div>
  </div>
</div> -->
<!-- Video Section -->


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
</script>
</body>
</html>
