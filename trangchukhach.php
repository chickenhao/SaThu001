<!doctype html>
<html lang="vi">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>Trang Danisa</title>

  <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="trangchu.css">
</head>
<body>

<?php include 'header_front.php'; ?>
<div class="menu">
  <img src="image/anhnen3.jpg" alt="Ảnh nền Danisa">

  <div class="video-box">
    <video src="image/video.mp4" autoplay muted loop playsinline></video>
  </div>
</div>
<div class="sanphamthem">

  <!-- ITEM 1 -->
  <div class="item">
    <img src="image/anhthem1.png">
    <div class="explore-box">
      <h4>KHOẢNG KHẮC HOÀNG GIA</h4>
      <a href="khoangkhachoanggia.php">
      <button>KHÁM PHÁ</button>
    </div></a>
  </div>

  <!-- ITEM 2 -->
<div class="item">
  <img src="image/anhthem2.png" alt="">
  <div class="explore-box">
    <h4>LỰA CHỌN ĐẲNG CẤP</h4>
    <a href="khoangkhachoanggia.php" class="explore-btn"><button>KHÁM PHÁ</button></a>
  </div>
</div>

</div>


<script>
document.addEventListener("DOMContentLoaded", function() {
  const currentUser = JSON.parse(localStorage.getItem("currentUser"));
  const accountDropdown = document.querySelector(".account-dropdown");

  if (currentUser) {
    const nameTag = document.createElement("span");
    nameTag.textContent = currentUser.username;
    nameTag.style.color = "white";
    nameTag.style.marginLeft = "8px";
    nameTag.style.fontSize = "17px";
    nameTag.style.fontWeight = "600";
    accountDropdown.insertBefore(nameTag, accountDropdown.querySelector(".account-menu"));

    const menuLinks = accountDropdown.querySelectorAll(".account-menu a");
    menuLinks[0].style.display = "none"; 
    menuLinks[1].style.display = "none"; 
  }

  const logoutLink = document.querySelector(".account-menu a:last-child");
  logoutLink.addEventListener("click", function(e) {
    e.preventDefault();
    localStorage.removeItem("currentUser");
    alert("Đăng xuất thành công!");
    window.location.href = "dangnhap.php";
  });
});
</script>
<footer>
  <div class="linklien">
      <a href="#" style="color:white; text-decoration:none; margin:0 8px;">Đã được bảo lưu mọi quyền</a> |
    <a href="lienhe.php" style="color:white; text-decoration:none; margin:0 8px;">Liên hệ với chúng tôi</a> |
    <a href="dieukien.php" style="color:white; text-decoration:none; margin:0 8px;">Điều khoản và Điều kiện</a> |
    <a href="chinhsach.php" style="color:white; text-decoration:none; margin:0 8px;">Chính sách bảo mật</a>
  </div>
</footer>
<script>
  const options = document.querySelectorAll(".option");
  const selectedOption = document.getElementById("selectedOption");
  const result = document.getElementById("result");

  options.forEach(opt => {
    opt.addEventListener("click", () => {
      const value = opt.getAttribute("data-value");

      selectedOption.textContent = value; // Cập nhật tên trên nút
      result.textContent = value;         // Cập nhật hiển thị kết quả
    });
  });
</script>
<script>
document.addEventListener("DOMContentLoaded", function() {
  const menuButton = document.querySelector(".dropdown > a");
  const menuContent = document.querySelector(".dropdown-content");

  menuButton.addEventListener("click", function(e) {
    e.preventDefault();
    menuContent.classList.toggle("show");
  });

  // Bấm ra ngoài để đóng menu
  document.addEventListener("click", function(e) {
    if (!menuButton.contains(e.target) && !menuContent.contains(e.target)) {
      menuContent.classList.remove("show");
    }
  });
});
</script>


</body>
</html>
