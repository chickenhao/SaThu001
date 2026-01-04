<?php
require 'config.php';

// Lấy danh mục sản phẩm cho menu "Lựa chọn Đẳng cấp"
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
  <title>Món quà Danisa</title>

  <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="khoangkhac.css">
</head>
<body>

<?php include 'header_front.php'; ?>
<!-- HERO: 1 ảnh nền + box chữ + nút KHÁM PHÁ -->
<section class="hero-section">
  <!-- Ảnh nền hero -->
  <img src="image/img2.png" alt="Món quà Danisa" class="hero-bg">

  <div class="hero-overlay">
    <h1>Món Quà Đẳng Cấp</h1>
    <p>
      Danisa mang đến những chiếc bánh quy bơ hoàng gia,
      sang trọng và tinh tế cho mọi dịp đặc biệt.
    </p>
    <a href="danhmuc_sanpham.php" class="hero-btn">KHÁM PHÁ</a>
  </div>
</section>

<footer>
  <div>
    <a href="#" style="color:white; text-decoration:none; margin:0 8px;">Đã được bảo lưu mọi quyền</a> |
    <a href="lienhe.php" style="color:white; text-decoration:none; margin:0 8px;">Liên hệ với chúng tôi</a> |
    <a href="dieukien.php" style="color:white; text-decoration:none; margin:0 8px;">Điều khoản và Điều kiện</a> |
    <a href="chinhsachchinhsach.php" style="color:white; text-decoration:none; margin:0 8px;">Chính sách bảo mật</a>
  </div>
</footer>

</body>
</html>
