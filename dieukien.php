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
<!-- BODY -->
<div class="menu">
<section style="
  background: url('image/anhnen3.jpg') center/cover no-repeat;
  min-height: 650px;
  display: flex;
  flex-direction: column;
  justify-content: flex-start; /* ✅ đẩy nội dung lên trên */
  align-items: center;
  padding: 20px 20px 60px 20px; /* ✅ giảm khoảng cách trên */
  text-align: center;
  color: white;
">
  <h2 style="
    text-align: center;
    font-size: 30px;
    font-weight: 700;
    color: gold;
    margin-bottom: 28px;
    letter-spacing: 2px;
  ">
    ĐIỀU KHOẢN VÀ ĐIỀU KIỆN
  </h2>
  <p style="
    max-width: 1200px;
    margin: auto;
    text-align: center;
    font-size: 18px;
    line-height: 1.6;
    margin-bottom: 45px;
  ">
    Vui lòng đọc kỹ các điều khoản và điều kiện (sau đây gọi là “Điều khoản và Điều kiện”) dưới đây.
    Các Điều khoản và Điều kiện này áp dụng cho tất cả người dùng, người xem và tất cả những người truy cập
    vào trang web www.royaldanisa.com (“Trang web”). Bằng việc sử dụng hoặc xem Trang web, bạn xác nhận
    rằng bạn đã đọc, hiểu, chấp nhận và đồng ý rằng buộc bản thân với các Điều khoản và Điều kiện này và
    Chính sách Quyền riêng tư dưới đây. Nếu bạn không đồng ý hoặc không muốn ngừng sử dụng Trang web này,
    vui lòng không sử dụng Trang web.
  </p>

  <div style="
    max-width: 1200px;
    margin: auto;
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 40px;
    line-height: 1.6;
    font-size: 16px;
  ">
    <div>
      <p style="font-size: 18px; font-weight: 600; color: gold;">
        1. Sử dụng Trang web và Hạn chế bản quyền
      </p>

     
    </div>
    <div style="border-left: 1px solid rgba(255,255,255,0.4); padding-left: 30px;">
       <p>
        Bản thân Trang web, chẳng hạn như kiến trúc, cách trình bày, tiêu chuẩn đồ họa
        và mọi thứ chứa trong đó cũng như tất cả tài liệu (văn bản, biểu đồ, đồ họa, logo,
        hình ảnh, hình minh họa, video...) được xuất bản trên Trang web đều là tài sản có bản quyền
        và sở hữu hợp pháp bởi Danisa hoặc được bên thứ ba cấp phép cho Danisa sử dụng.
      </p><p>
        Trang web này được cung cấp chỉ cho mục đích sử dụng cá nhân và phi thương mại của bạn.
        Trừ khi bạn sở hữu hoặc kiểm soát các quyền liên quan đến tài liệu, bạn không được phép,
        bao gồm nhưng không giới hạn ở: sao chép, chỉnh sửa, xuất bản, truyền tải hoặc phân phối
        tài liệu trên trang web này.
      </p>
    </div>

  </div>

</section>




</div>
<footer style="
  background:#111827;
  color:white;
  text-align:center;
  padding:20px 0;
  font-size:12px;
  line-height:1.8;
  font-weight:500;
">
  <div>
      <a href="#" style="color:white; text-decoration:none; margin:0 8px;">Đã được bảo lưu mọi quyền</a> |
    <a href="lienhe.php" style="color:white; text-decoration:none; margin:0 8px;">Liên hệ với chúng tôi</a> |
    <a href="dieukien.php" style="color:white; text-decoration:none; margin:0 8px;">Điều khoản và Điều kiện</a> |
    <a href="chinhsach.php" style="color:white; text-decoration:none; margin:0 8px;">Chính sách bảo mật</a>
  </div>
</footer>


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
