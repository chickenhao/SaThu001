<?php
session_start();

require 'config.php';

$currentUser = isset($_SESSION["currentUser"]) ? $_SESSION["currentUser"] : null;

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
<title>Danisa Trên Các Quốc Gia</title>

<link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="trangchu.css">

<style>
  /* Phần global map (nếu sau này dùng) */
  .global-section {
    padding: 60px 0 40px;
    background: #f3f4f6;
  }
  .global-container {
    max-width: 1100px;
    margin: 0 auto;
    padding: 0 16px;
    display: grid;
    grid-template-columns: minmax(0, 1.1fr) minmax(0, 1fr);
    gap: 32px;
    align-items: center;
  }
  .global-map img {
    width: 100%;
    border-radius: 16px;
    box-shadow: 0 18px 45px rgba(15,23,42,0.25);
    object-fit: cover;
  }
  .global-content h2 {
    font-family: "Cormorant Garamond", serif;
    font-size: 36px;
    letter-spacing: 0.04em;
    text-transform: uppercase;
    margin-bottom: 16px;
    color: #111827;
  }
  .global-content p {
    font-size: 15px;
    line-height: 1.8;
    color: #4b5563;
    margin-bottom: 20px;
  }
  .explore-btn {
    display: inline-block;
    padding: 10px 22px;
    border-radius: 999px;
    border: 1px solid #b45309;
    color: #b45309;
    font-size: 13px;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    text-decoration: none;
    transition: all 0.2s ease;
  }
  .explore-btn:hover {
    background: #b45309;
    color: #fff;
  }

  /* Dropdown mượt */
  .dropdown-content {
    position: absolute;
    top: 100%;
    left: 0;
    background-color: #111827;
    min-width: 260px;
    box-shadow: 0 10px 24px rgba(0,0,0,0.35);
    border-radius: 10px;
    overflow: hidden;
    z-index: 1000;
    padding: 4px 0;

    opacity: 0;
    visibility: hidden;
    transform: translateY(8px);
    pointer-events: none;
    transition: opacity 0.22s ease, transform 0.22s ease;
  }
  .dropdown-content.show {
    opacity: 1;
    visibility: visible;
    transform: translateY(0);
    pointer-events: auto;
  }

  /* --- PHẦN: DANH SÁCH QUỐC GIA + TÌM KIẾM --- */
  .countries-section {
    padding: 40px 0 80px;
    background: #0c2340;
    color: #f9fafb;
  }
  .countries-container {
    max-width: 1100px;
    margin: 0 auto;
    padding: 0 16px;
  }
  .countries-header {
    text-align: center;
    margin-bottom: 28px;
  }
  .countries-header h3 {
    font-family: "Cormorant Garamond", serif;
    font-size: 32px;
    text-transform: uppercase;
    letter-spacing: 0.09em;
    margin-bottom: 10px;
  }
  .countries-header p {
    font-size: 14px;
    line-height: 1.8;
    max-width: 700px;
    margin: 0 auto;
    color: #e5e7eb;
  }

  .countries-search {
    margin-bottom: 24px;
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 12px;
    justify-content: space-between;
  }
  .countries-search-input {
    flex: 1 1 220px;
    position: relative;
  }
  .countries-search-input input {
    width: 100%;
    padding: 9px 34px 9px 12px;
    border-radius: 999px;
    border: 1px solid rgba(249,250,251,0.12);
    background: rgba(15,23,42,0.85);
    color: #f9fafb;
    font-size: 14px;
    outline: none;
  }
  .countries-search-input input::placeholder {
    color: #9ca3af;
  }
  .countries-search-input svg {
    position: absolute;
    right: 10px;
    top: 50%;
    transform: translateY(-50%);
    pointer-events: none;
  }
  .country-count-text {
    font-size: 13px;
    color: #d1d5db;
  }

  .country-result-text {
    font-size: 13px;
    color: #9ca3af;
    margin-bottom: 14px;
  }

  .countries-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(210px, 1fr));
    gap: 16px;
  }
  .country-card {
    background: radial-gradient(circle at top left, rgba(251,191,36,0.13), transparent 55%);
    border-radius: 14px;
    padding: 13px 14px 14px;
    border: 1px solid rgba(249,250,251,0.06);
    box-shadow: 0 10px 30px rgba(15,23,42,0.35);
  }
  .country-card-header {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 4px;
  }
  .country-flag {
    font-size: 22px;
  }
  .country-name {
    font-weight: 600;
    font-size: 15px;
  }
  .country-region {
    font-size: 12px;
    color: #d1d5db;
  }
  .country-note {
    font-size: 12px;
    color: #e5e7eb;
    margin-top: 6px;
    line-height: 1.6;
  }

  @media (max-width: 768px) {
    .global-container {
      grid-template-columns: 1fr;
    }
    .global-section {
      padding-top: 36px;
    }
    .countries-header h3 {
      font-size: 26px;
    }
  }
</style>

</head>
<body>

<?php include 'header_front.php'; ?>
<!-- PHẦN: DANH SÁCH CÁC QUỐC GIA + TÌM KIẾM -->
<section class="countries-section" id="countries-list">
  <div class="countries-container">

    <div class="countries-header">
      <h3>Danisa Trên Các Quốc Gia</h3>
      <p>
        Danisa hiện diện ở nhiều thị trường trên khắp châu Á, châu Âu, châu Mỹ, Trung Đông và châu Phi.
        Dưới đây là một số quốc gia và khu vực tiêu biểu dựa trên thông tin từ website chính thức và các nhà phân phối.
        Bạn có thể gõ tên quốc gia để kiểm tra nhanh.
      </p>
    </div>

    <div class="countries-search">
      <div class="countries-search-input">
        <input type="text" id="countrySearch" placeholder="Tìm kiếm quốc gia (ví dụ: Việt Nam, Japan, USA)..." />
        <svg width="16" height="16" viewBox="0 0 24 24">
          <path d="M21 21l-4.35-4.35" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/>
          <circle cx="11" cy="11" r="5" stroke="currentColor" stroke-width="1.6" fill="none"/>
        </svg>
      </div>
      <div class="country-count-text">
        Danisa đã có mặt tại <strong>hơn 60–100 quốc gia</strong> trên toàn thế giới.
      </div>
    </div>

    <div id="countryResultText" class="country-result-text"></div>

    <div class="countries-grid" id="countriesGrid">
      <article class="country-card" data-name="Đan Mạch, Denmark">
        <div class="country-card-header">
          <span class="country-flag">🇩🇰</span>
          <div>
            <div class="country-name">Đan Mạch (Denmark)</div>
            <div class="country-region">Châu Âu</div>
          </div>
        </div>
        <p class="country-note">
          Đan Mạch – nguồn gốc thương hiệu Danisa và công thức bánh quy bơ chuẩn Đan Mạch (nguồn: royaldanisa.com).
        </p>
      </article>

      <article class="country-card" data-name="Vương quốc Anh, United Kingdom, Britain, UK">
        <div class="country-card-header">
          <span class="country-flag">🇬🇧</span>
          <div>
            <div class="country-name">Vương quốc Anh (United Kingdom)</div>
            <div class="country-region">Châu Âu</div>
          </div>
        </div>
        <p class="country-note">
          Được nhắc trong tài liệu Danisa là một trong các thị trường nơi thương hiệu được phân phối
          (“marketed in 60 countries, including the United States, Russia, Britain…”) – nguồn: royaldanisa.com.
        </p>
      </article>

      <article class="country-card" data-name="Nga, Russia">
        <div class="country-card-header">
          <span class="country-flag">🇷🇺</span>
          <div>
            <div class="country-name">Nga (Russia)</div>
            <div class="country-region">Châu Âu/Á-Âu</div>
          </div>
        </div>
        <p class="country-note">
          Nga cũng nằm trong nhóm thị trường được Danisa liệt kê cùng Hoa Kỳ và Anh
          (“United States, Russia, Britain, and others”) – nguồn: royaldanisa.com.
        </p>
      </article>

      <article class="country-card" data-name="Nhật Bản, Japan">
        <div class="country-card-header">
          <span class="country-flag">🇯🇵</span>
          <div>
            <div class="country-name">Nhật Bản (Japan)</div>
            <div class="country-region">Châu Á – Thái Bình Dương</div>
          </div>
        </div>
        <p class="country-note">
          Nhật Bản được nêu rõ trong thông cáo báo chí của Danisa như một trong các thị trường tiêu biểu – nguồn: PressPort.
        </p>
      </article>

      <article class="country-card" data-name="Singapore">
        <div class="country-card-header">
          <span class="country-flag">🇸🇬</span>
          <div>
            <div class="country-name">Singapore</div>
            <div class="country-region">Đông Nam Á (ASEAN)</div>
          </div>
        </div>
        <p class="country-note">
          Singapore được nhắc cùng với Japan, Vietnam, China, USA, Nigeria trong thông cáo PR về sự hiện diện toàn cầu – nguồn: PressPort.
        </p>
      </article>

      <article class="country-card" data-name="Việt Nam, Vietnam">
        <div class="country-card-header">
          <span class="country-flag">🇻🇳</span>
          <div>
            <div class="country-name">Việt Nam (Vietnam)</div>
            <div class="country-region">Đông Nam Á</div>
          </div>
        </div>
        <p class="country-note">
          Việt Nam được nhắc trực tiếp trong bài PR chính thức và xuất hiện rộng rãi trên các kênh bán lẻ nội địa
          như hệ thống siêu thị và thương mại điện tử – nguồn: PressPort, lottemart.vn.
        </p>
      </article>

      <article class="country-card" data-name="Trung Quốc, China">
        <div class="country-card-header">
          <span class="country-flag">🇨🇳</span>
          <div>
            <div class="country-name">Trung Quốc (China)</div>
            <div class="country-region">Đông Á</div>
          </div>
        </div>
        <p class="country-note">
          Danisa từng nhận giải thưởng hương vị tại thị trường Trung Quốc và được liệt kê trong nhóm các nước tiêu biểu
          – nguồn: taste-institute.com.
        </p>
      </article>

      <article class="country-card" data-name="Indonesia">
        <div class="country-card-header">
          <span class="country-flag">🇮🇩</span>
          <div>
            <div class="country-name">Indonesia</div>
            <div class="country-region">Đông Nam Á</div>
          </div>
        </div>
        <p class="country-note">
          Indonesia là nơi Mayora Group sản xuất Danisa theo công nghệ Đan Mạch để cung cấp cho nhiều thị trường toàn cầu
          – nguồn: FMCG Viet - Top FMCG Exporter in Vietnam.
        </p>
      </article>

      <article class="country-card" data-name="Malaysia">
        <div class="country-card-header">
          <span class="country-flag">🇲🇾</span>
          <div>
            <div class="country-name">Malaysia</div>
            <div class="country-region">Đông Nam Á</div>
          </div>
        </div>
        <p class="country-note">
          Danisa được bán chính thức trên các nền tảng thương mại điện tử tại Malaysia, tiêu biểu như Shopee Malaysia
          – nguồn: Shopee Malaysia.
        </p>
      </article>

      <article class="country-card" data-name="Úc, Australia">
        <div class="country-card-header">
          <span class="country-flag">🇦🇺</span>
          <div>
            <div class="country-name">Úc (Australia)</div>
            <div class="country-region">Châu Đại Dương</div>
          </div>
        </div>
        <p class="country-note">
          Sản phẩm Danisa được phân phối qua các nhà bán lẻ tại Úc, ví dụ SnackAffair – nguồn: snackaffair.com.au.
        </p>
      </article>

      <article class="country-card" data-name="Mỹ, Hoa Kỳ, United States, USA, America">
        <div class="country-card-header">
          <span class="country-flag">🇺🇸</span>
          <div>
            <div class="country-name">Hoa Kỳ (United States / USA)</div>
            <div class="country-region">Châu Mỹ</div>
          </div>
        </div>
        <p class="country-note">
          Hoa Kỳ được nhắc trong bài PR chính thức và các bài báo thương mại; Danisa xuất hiện tại nhiều hệ thống bán lẻ
          như Whole Foods, Ralphs… – nguồn: PressPort.
        </p>
      </article>

      <article class="country-card" data-name="Canada">
        <div class="country-card-header">
          <span class="country-flag">🇨🇦</span>
          <div>
            <div class="country-name">Canada</div>
            <div class="country-region">Bắc Mỹ</div>
          </div>
        </div>
        <p class="country-note">
          AFOD LTD – nhà nhập khẩu thực phẩm tại Canada – phân phối Danisa tại thị trường này
          – nguồn: AFOD LTD.
        </p>
      </article>

      <article class="country-card" data-name="Nigeria">
        <div class="country-card-header">
          <span class="country-flag">🇳🇬</span>
          <div>
            <div class="country-name">Nigeria</div>
            <div class="country-region">Châu Phi</div>
          </div>
        </div>
        <p class="country-note">
          Nigeria được Danisa nêu rõ trong thông cáo báo chí, nằm trong nhóm Japan, Singapore, Vietnam, China, USA, Nigeria
          – nguồn: PressPort.
        </p>
      </article>

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
  // Hiển thị tên user
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

  // Dropdown hover mượt
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

<!-- SCRIPT TÌM KIẾM QUỐC GIA -->
<script>
document.addEventListener("DOMContentLoaded", function () {
  const searchInput = document.getElementById("countrySearch");
  const cards = document.querySelectorAll(".country-card");
  const resultText = document.getElementById("countryResultText");

  function filterCountries() {
    const keyword = searchInput.value.trim().toLowerCase();
    let shown = 0;

    cards.forEach(card => {
      const name = card.dataset.name.toLowerCase();
      if (!keyword || name.includes(keyword)) {
        card.style.display = "block";
        shown++;
      } else {
        card.style.display = "none";
      }
    });

    if (resultText) {
      if (keyword) {
        resultText.textContent = `Tìm thấy ${shown} quốc gia/khu vực phù hợp với “${keyword}”.`;
      } else {
        resultText.textContent = `Hiển thị ${shown} quốc gia và khu vực tiêu biểu (trong số hơn 60–100 quốc gia Danisa hiện diện).`;
      }
    }
  }

  if (searchInput) {
    searchInput.addEventListener("input", filterCountries);
    filterCountries();
  }
});
</script>

</body>
</html>
