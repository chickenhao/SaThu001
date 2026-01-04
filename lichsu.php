<?php
session_start();
require 'config.php';

$currentUser = isset($_SESSION["currentUser"]) ? $_SESSION["currentUser"] : null;

// Lấy danh mục cho menu "Lựa chọn Đẳng cấp"
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
<title>Trang Danisa - Công thức nổi tiếng</title>

<link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="trangchu.css">

<style>
/* ===== HERO CÔNG THỨC ===== */
.recipe-hero{
  background: url('image/anhnen3.jpg') center/cover no-repeat;
  padding: 50px 20px 80px;
  color: #fff;
}

.recipe-inner{
  max-width: 1100px;
  margin: 0 auto;
  display: flex;
  gap: 40px;
  align-items: center;
  justify-content: center;
}

/* ẢNH TO HƠN */
.recipe-inner img{
  width: 600px;         /* tăng từ 480px lên 600px */
  max-width: 100%;
  height: auto;
  display: block;
}

/* BOX CHỮ NHỎ LẠI */
.recipe-text-box{
  background: rgba(0,25,80,0.75);
  border-radius: 8px;
  padding: 22px 24px;   /* nhỏ hơn một chút */
  border: 1px solid rgba(255,215,0,0.7);
  box-shadow: 0 16px 40px rgba(0,0,0,0.4);
  max-width: 360px;     /* giảm từ 460px xuống 360px */
}

.recipe-text-box h2{
  margin: 0 0 14px;
  font-size: 20px;
  letter-spacing: 2px;
  text-transform: uppercase;
  color: #E8C16B;
}

.recipe-text-box .text-box{
  max-height: 280px;    /* thấp hơn một chút để box gọn lại */
  overflow-y: auto;
  line-height: 1.7;
  font-size: 15px;
}

/* Mobile */
@media (max-width: 820px){
  .recipe-inner{
    flex-direction: column;
  }
  .recipe-inner img{
    width: 80%;
  }
  .recipe-text-box{
    max-width: 100%;
  }
}

/* Hiệu ứng dropdown mượt – dùng class .show (phù hợp với trangchu.css) */
.dropdown-content{
  opacity: 0;
  visibility: hidden;
  transform: translateY(6px);
  transition: opacity .22s ease, transform .22s ease;
}
.dropdown-content.show{
  display: block;
  opacity: 1;
  visibility: visible;
  transform: translateY(0);
}
.recipe-text-box .text-box{
  max-height: 280px;
  overflow-y: scroll;      /* trước là auto -> luôn có thanh cuộn */
  line-height: 1.7;
  font-size: 15px;
}

/* Tùy chọn: làm thanh cuộn nhỏ & đẹp hơn (Webkit: Chrome, Edge) */
.recipe-text-box .text-box::-webkit-scrollbar{
  width: 6px;
}
.recipe-text-box .text-box::-webkit-scrollbar-track{
  background: rgba(0,0,0,0.15);
  border-radius: 999px;
}
.recipe-text-box .text-box::-webkit-scrollbar-thumb{
  background: rgba(255,255,255,0.6);
  border-radius: 999px;
}

/* Firefox */
.recipe-text-box .text-box{
  scrollbar-width: thin;
  scrollbar-color: rgba(255,255,255,0.6) rgba(0,0,0,0.15);
}

</style>
</head>
<body>

<?php include 'header_front.php'; ?>
<!-- NỘI DUNG CHÍNH -->
<section class="recipe-hero">
  <div class="recipe-inner">
    <img src="image/anhthu1.png" alt="Bánh quy bơ Danisa">

    <div class="recipe-text-box">
      <h2>LỊCH SỬ</h2>
      <div class="text-box">
  <p >
    Từ nhiều thập kỉ trước, Danisa đã được biết đến như một loại bánh quy bơ nổi tiếng chỉ dành riêng cho giới quý tộc châu Âu. Bánh quy bơ Danisa được làm ra trên nền tảng công thức truyền thống đúc kết qua nhiều thế hệ nghệ nhân làm bánh từ Đan Mạch.</br><br>
    Danisa được giám sát bởi Danish Speciality Foods ApS có trụ sở đặt tại Copenhagen, Đan Mạch. Dưới sự kiểm soát chặt chẽ của Danish Speciality Foods ApS, mỗi hộp bánh Danisa được làm ra luôn đảm bảo những tiêu chuẩn chất lượng của công thức Đan Mạch chính gốc. Chính nhờ vậy, Danisa luôn làm hài lòng những người tiêu dùng tinh tế nhất và được coi là biểu tượng của đẳng cấp, lễ hội và sang trọng.</br><br>
    Với lịch sử lâu đời, bánh quy bơ Danisa từ bao năm qua đã chinh phục khẩu vị của người dân tại hơn 60 quốc gia trên thế giới.
  </p>

      </div>
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
  <div>
    <a href="#" style="color:white; text-decoration:none; margin:0 8px;">Đã được bảo lưu mọi quyền</a> |
    <a href="lienhevoichungtoi.html" style="color:white; text-decoration:none; margin:0 8px;">Liên hệ với chúng tôi</a> |
    <a href="dieukhoandieukien.html" style="color:white; text-decoration:none; margin:0 8px;">Điều khoản và Điều kiện</a> |
    <a href="chinhsachbaomat.html" style="color:white; text-decoration:none; margin:0 8px;">Chính sách bảo mật</a>
  </div>
</footer>

<script>
document.addEventListener("DOMContentLoaded", function() {
  // Hiển thị tên user bên cạnh icon tài khoản (theo localStorage)
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
      menuLinks[0].style.display = "none"; // Đăng nhập
      menuLinks[1].style.display = "none"; // Đăng ký
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

  // Dropdown: rê chuột để xổ xuống mượt
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
  