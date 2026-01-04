<?php
session_start();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require 'config.php';

// Bắt buộc đăng nhập
if (!isset($_SESSION['username'])) {
    header("Location: dangnhap.php");
    exit;
}

$ho_ten = $_SESSION['ho_ten'] ?? $_SESSION['username'];

// Lấy danh sách danh mục (cho menu "Lựa chọn Đẳng cấp")
$categories = [];

$sqlCat = "SELECT * FROM danhmuc ORDER BY id ASC";
$resCat = $conn->query($sqlCat);
if ($resCat) {
    while ($row = $resCat->fetch_assoc()) {
        $categories[] = $row;
    }
}
?>
<!doctype html>
<html lang="vi">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>Khoảnh Khắc Hoàng Gia - Danisa</title>

  <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@400;500;600;700&display=swap" rel="stylesheet">
  <!-- CSS chung của trang chủ (header, footer, search, account...) -->
  <link rel="stylesheet" href="trangchu.css">

  <!-- CSS riêng cho trang Khoảnh Khắc Hoàng Gia -->
  <style>
    .kkhg-hero {
      background: url('image/anhnen3.jpg') center/cover no-repeat;
      min-height: 650px;
      display: flex;
      flex-direction: column;
      justify-content: flex-start;
      align-items: center;
      padding: 20px 20px 60px 20px;
      text-align: center;
      color: white;
    }

    .gallery {
      width: 80%;
      max-width: 900px;
      margin: 40px auto 60px auto;
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 30px;
    }

    .gallery-item{
      position: relative;
      overflow: hidden;
    }

    .gallery-item img {
      width: 100%;
      height: 260px;
      object-fit: cover;
      display: block;
      transition: transform .4s ease, filter .4s ease;
    }

    /* Ảnh 1 & 4 full hàng */
    .gallery-item:nth-child(1),
    .gallery-item:nth-child(4){
      grid-column: span 2;
    }

    .info-box{
      position:absolute;
      top: 50%;
      left: -340px;                /* Ẩn ngoài khung bên trái */
      transform: translateY(-50%);
      width: 250px;
      padding: 20px 24px;
      background: rgba(0, 23, 63, 0.85);
      border: 1px solid rgba(255, 215, 0, 0.7);
      border-radius: 8px;
      color:#fff;
      display:flex;
      flex-direction:column;
      align-items:flex-start;
      gap:12px;
      opacity:0;
      transition: all 0.4s ease;
    }

    .info-box h4{
      margin:0;
      font-size:18px;
      letter-spacing:1px;
    }

    .info-btn{
      display:inline-block;
      padding:8px 18px;
      border-radius:4px;
      background:orange;
      color:#fff;
      text-decoration:none;
      font-weight:600;
      font-size:14px;
      border:none;
      cursor:pointer;
      transition: background .3s ease, transform .2s ease;
    }
    .info-btn:hover{
      background:#ffb000;
      transform: translateY(-1px);
    }

    .gallery-item:hover .info-box{
      left: 30px;
      opacity:1;
    }
    .gallery-item:hover img{
      transform: scale(1.03);
      filter: brightness(0.75);
    }
  </style>
</head>
<body>

<!-- LOGO -->
<?php include 'header_front.php'; ?>
<!-- PHẦN NỘI DUNG KHOẢNH KHẮC HOÀNG GIA -->
<section class="kkhg-hero">

  <div class="gallery">
    <!-- ẢNH 1: Khoảnh khắc hoàng gia / Món Quà Tình Yêu -->
    <div class="gallery-item">
      <img src="image/img1.png" alt="Món Quà Tình Yêu">
      <div class="info-box">
        <h4>Món Quà Tình Yêu</h4>
        <a href="tinhyeu.php" class="info-btn">KHÁM PHÁ</a>
      </div>
    </div>

    <!-- ẢNH 2: Món Quà Hạnh Phúc -->
    <div class="gallery-item">
      <img src="image/img2.png" alt="Món Quà Hạnh Phúc">
      <div class="info-box">
        <h4>Món Quà Hạnh Phúc</h4>
        <a href="hanhphuc.php" class="info-btn">KHÁM PHÁ</a>
      </div>
    </div>

    <!-- ẢNH 3: Món Quà Tri Ân -->
    <div class="gallery-item">
      <img src="image/img3.png" alt="Món Quà Tri Ân">
      <div class="info-box">
        <h4>Món Quà Tri Ân</h4>
        <a href="trian.php" class="info-btn">KHÁM PHÁ</a>
      </div>
    </div>

    <!-- ẢNH 4: Món Quà Đẳng Cấp -->
    <div class="gallery-item">
      <img src="image/img4.png" alt="Món Quà Đẳng Cấp">
      <div class="info-box">
        <h4>Món Quà Đẳng Cấp</h4>
        <a href="dangcap.php" class="info-btn">KHÁM PHÁ</a>
      </div>
    </div>
  </div>

</section>

<footer>
  <div class="linklien">
    <a href="#" style="color:white; text-decoration:none; margin:0 8px;">Đã được bảo lưu mọi quyền</a> |
    <a href="lienhelienhe.php" style="color:white; text-decoration:none; margin:0 8px;">Liên hệ với chúng tôi</a> |
    <a href="dieukien.php" style="color:white; text-decoration:none; margin:0 8px;">Điều khoản và Điều kiện</a> |
    <a href="chinhsach.php" style="color:white; text-decoration:none; margin:0 8px;">Chính sách bảo mật</a>
  </div>
</footer>

<script>
document.addEventListener("DOMContentLoaded", function() {
  // Hiển thị tên user từ localStorage (frontend)
  const currentUser = JSON.parse(localStorage.getItem("currentUser"));
  const accountDropdown = document.querySelector(".account-dropdown");

  if (currentUser && accountDropdown) {
    const nameTag = document.createElement("span");
    nameTag.textContent = currentUser.username;
    nameTag.style.color = "white";
    nameTag.style.marginLeft = "8px";
    nameTag.style.fontSize = "17px";
    nameTag.style.fontWeight = "600";
    accountDropdown.insertBefore(nameTag, accountDropdown.querySelector(".account-menu"));

    const menuLinks = accountDropdown.querySelectorAll(".account-menu a");
    if (menuLinks.length >= 2) {
      menuLinks[0].style.display = "none";
      menuLinks[1].style.display = "none";
    }
  }

  const logoutLink = document.querySelector(".account-menu a:last-child");
  if (logoutLink) {
    logoutLink.addEventListener("click", function(e) {
      e.preventDefault();
      localStorage.removeItem("currentUser");
      alert("Đăng xuất thành công!");
      window.location.href = "dangnhap.php";
    });
  }

  // Xổ menu ở header bằng hover
  const dropdowns = document.querySelectorAll(".dropdown");

  dropdowns.forEach(drop => {
    const menu = drop.querySelector(".dropdown-content");
    if (!menu) return;

    // Rê chuột vào thì mở
    drop.addEventListener("mouseenter", function () {
      // Tắt các dropdown khác
      document.querySelectorAll(".dropdown-content.show").forEach(dc => {
        if (dc !== menu) dc.classList.remove("show");
      });
      menu.classList.add("show");
    });

    // Rê chuột ra ngoài thì đóng
    drop.addEventListener("mouseleave", function () {
      menu.classList.remove("show");
    });
  });
});
</script>

</body>
</html>
