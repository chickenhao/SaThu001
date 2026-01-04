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
    font-size: 30px;
    letter-spacing: 2px;
    font-weight: 700;
    margin-bottom: 20px;
    color: gold;
    display: inline-block;
    padding-top: 10px;
  ">
    LIÊN HỆ VỚI CHÚNG TÔI
  </h2>

  <p style="font-size: 20px; max-width: 900px; margin: 0 auto 10px;">
    Danisa hoan nghênh những yêu cầu chú đáo, sự hợp tác có ý nghĩa và bất kỳ câu hỏi nào bạn có thể có về thương hiệu và sản phẩm của chúng tôi.
  </p>

  <p style="font-size: 20px; max-width: 900px; margin: 0 auto 30px;">
    Nhóm của chúng tôi rất trân trọng mọi thắc mắc và sẽ cố gắng hết sức để phản hồi bạn kịp thời.
  </p>

  <p style="margin-bottom: 25px;">Vui lòng liên hệ với chúng tôi qua email:</p>

  <div style="display:flex; justify-content:center; gap:20px; flex-wrap:wrap;">

    <a href="mailto:erik@bresling.dk"
      style="
        padding: 12px 30px;
        border: 2px solid white;
        border-radius: 40px;
        color: white;
        text-decoration: none;
        font-size: 18px;
        transition: 0.3s;
      "
    >thanhao6605@gmail.com</a>

    <a href="mailto:mario.zhong@mayora.co.id"
      style="
        padding: 12px 30px;
        border: 2px solid white;
        border-radius: 40px;
        color: white;
        text-decoration: none;
        font-size: 18px;
        transition: 0.3s;
      "
    >lamhan310705@gmail.com</a>

  </div>
</section>

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
