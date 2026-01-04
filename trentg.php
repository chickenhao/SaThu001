<?php
session_start();

require 'config.php';

// Lấy tên hiển thị từ session (giống trangchu.php)
$ho_ten = $_SESSION['ho_ten'] ?? $_SESSION['username'] ?? '';

// Lấy danh sách danh mục cho menu "Lựa chọn Đẳng cấp"
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
  <title>Danisa trên các quốc gia</title>

  <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="trangchu.css">
</head>

<body>
<?php include 'header_front.php'; ?>

<!-- NỘI DUNG: TRÊN CÁC QUỐC GIA -->
<section class="global-section">
  <div class="global-container">

    <!-- Bản đồ -->
    <div class="global-map">
      <img src="image/anhtg.jpg" alt="Bản đồ thế giới">
    </div>

    <!-- Nội dung bên phải -->
    <div class="global-content">
      <h2>Trên Các Quốc Gia</h2>
      <p>
        Thương hiệu Danisa mang tính thống nhất và tính tinh tế vượt trội kế thừa từ nghệ thuật làm bánh tuyệt đỉnh.
        Danisa mang đến những khoảnh khắc tận hưởng quý giá, hân hoan trên toàn cầu.
      </p>
      <a href="cacnuoc.php" class="explore-btn">KHÁM PHÁ THÊM</a>
    </div>

  </div>
</section>

<footer style="
  background:#111827;
  color:white;
  text-align:center;
  padding: 20px 0;
  font-size:12px;
  line-height:1.8;
  font-weight:500;
">
  <div class="linklien">
    <a href="#" style="color:white; text-decoration:none; margin:0 8px;">Đã được bảo lưu mọi quyền</a> |
    <a href="lienhelienhe.php" style="color:white; text-decoration:none; margin:0 8px;">Liên hệ với chúng tôi</a> |
    <a href="dieukien.php" style="color:white; text-decoration:none; margin:0 8px;">Điều khoản và Điều kiện</a> |
    <a href="chinhsach.php" style="color:white; text-decoration:none; margin:0 8px;">Chính sách bảo mật</a>
  </div>
</footer>

<script>
document.addEventListener("DOMContentLoaded", function() {
  // Dropdown header: xổ bằng hover
  const dropdowns = document.querySelectorAll(".dropdown");

  dropdowns.forEach(drop => {
    const menu = drop.querySelector(".dropdown-content");
    if (!menu) return;

    drop.addEventListener("mouseenter", function () {
      document.querySelectorAll(".dropdown-content.show").forEach(dc => {
        if (dc !== menu) dc.classList.remove("show");
      });
      menu.classList.add("show");
    });

    drop.addEventListener("mouseleave", function () {
      menu.classList.remove("show");
    });
  });
});
</script>

</body>
</html>
