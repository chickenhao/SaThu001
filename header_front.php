<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Chuẩn bị dữ liệu cho header
$headerCategories = [];
if (isset($categories) && is_array($categories) && !empty($categories)) {
    $headerCategories = $categories;
} elseif (isset($conn) && $conn instanceof mysqli) {
    $resultHeaderCat = $conn->query("SELECT * FROM danhmuc ORDER BY id ASC");
    if ($resultHeaderCat) {
        while ($row = $resultHeaderCat->fetch_assoc()) {
            $headerCategories[] = $row;
        }
        $resultHeaderCat->free();
    }
}

$headerCartExtraClass = isset($headerCartExtraClass) ? trim($headerCartExtraClass) : '';
$headerDisplayName = $_SESSION['ho_ten']
    ?? $_SESSION['fullname']
    ?? $_SESSION['username']
    ?? '';
$headerIsLoggedIn = !empty($_SESSION['user_id']);
$headerIsAdmin    = !empty($_SESSION['role']) && $_SESSION['role'] === 'admin';
$headerIsStaff    = !empty($_SESSION['role']) && $_SESSION['role'] === 'staff';
$headerCanManage  = $headerIsAdmin || $headerIsStaff; // Admin hoặc Staff đều có quyền quản lý
?>

<div class="logo-section">
  <a href="trangchu.php">
    <img src="image/anhll.png" alt="Logo Danisa" />
  </a>
</div>

<header class="site-header">
  <div class="header-inner">

    <!-- NAV LEFT -->
    <nav class="nav left">
      <div class="dropdown">
        <a href="#">Bảo Tồn Di Sản</a>
        <div class="dropdown-content">
          <a href="lichsu.php">Lịch sử</a>
          <a href="congthuc.php">Công thức nổi tiếng</a>
        </div>
      </div>

      <div class="dropdown">
        <a href="khoangkhachoanggia.php">Khoảnh khắc Hoàng Gia</a>
        <div class="dropdown-content">
          <a href="tinhyeu.php">Món Quà Tình Yêu</a>
          <a href="trian.php">Món Quà Tri Ân</a>
          <a href="hanhphuc.php">Món Quà Hạnh Phúc</a>
          <a href="dangcap.php">Món Quà Đẳng Cấp</a>
        </div>
      </div>
    </nav>

    <!-- NAV RIGHT -->
    <nav class="nav right">
      <div class="dropdown">
        <a href="#">Lựa chọn Đẳng cấp</a>
        <div class="dropdown-content">
          <!-- Trang liệt kê tất cả sản phẩm -->
          <a href="sanpham_danhmuc.php">Tất cả sản phẩm</a>
          <?php if (!empty($headerCategories)): ?>
            <?php foreach ($headerCategories as $cat): ?>
              <!-- Lọc sản phẩm theo danh mục, truyền id danh mục -->
              <a href="sanpham_danhmuc.php?danhmuc_id=<?= (int)($cat['id'] ?? 0) ?>">
                <?php
                  $catName = $cat['name']
                    ?? $cat['ten_danh_muc']
                    ?? $cat['ten']
                    ?? ('Danh mục ' . ($cat['id'] ?? ''));
                  echo htmlspecialchars($catName, ENT_QUOTES, 'UTF-8');
                ?>
              </a>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>
      </div>

      <div class="dropdown">
        <a href="#">Sự Hiện Diện Toàn Cầu</a>
        <div class="dropdown-content">
          <a href="trentg.php">Danisa trên các quốc gia</a>
          <a href="tintuc.php">Tin tức và sự kiện</a>
        </div>
      </div>
    </nav>

    <!-- SEARCH + ACCOUNT -->
    <div class="header-right">
      <div class="search-input">
        <svg width="18" height="18" viewBox="0 0 24 24">
          <path d="M21 21l-4.35-4.35" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/>
          <circle cx="11" cy="11" r="5" stroke="currentColor" stroke-width="1.6" fill="none"/>
        </svg>
        <input type="search" id="product-search-input" placeholder="Tìm sản phẩm..." />
        <a href="giohang.php" class="cart-btn <?= htmlspecialchars($headerCartExtraClass, ENT_QUOTES, 'UTF-8') ?>" aria-label="Giỏ hàng">
          <svg width="20" height="20" viewBox="0 0 24 24">
            <path d="M6 6h15l-1.5 9h-12L4 2H2" stroke="currentColor" stroke-width="1.6" fill="none" stroke-linecap="round"/>
            <circle cx="10" cy="20" r="1" fill="currentColor"/>
            <circle cx="18" cy="20" r="1" fill="currentColor"/>
          </svg>
        </a>
        <?php if ($headerIsLoggedIn): ?>
          <a href="favorites.php" class="favorites-btn" aria-label="Sản phẩm yêu thích" id="header-favorites-btn">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6">
              <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/>
            </svg>
          </a>
        <?php endif; ?>
      </div>

      <div class="account-dropdown">
        <button class="icon-btn" aria-label="Tài khoản">
          <svg width="20" height="20" viewBox="0 0 24 24">
            <circle cx="12" cy="8" r="4" stroke="currentColor" stroke-width="1.6" fill="none" />
            <path d="M4 20a8 8 0 0 1 16 0" stroke="currentColor" stroke-width="1.6" fill="none" stroke-linecap="round" />
          </svg>
        </button>
        <div class="account-menu">
          <?php if ($headerIsLoggedIn): ?>
            <span style="color:white; font-weight:600; display:block; padding:6px 0; margin-left: 15px;">
              <?= htmlspecialchars($headerDisplayName, ENT_QUOTES, 'UTF-8') ?>
            </span>
            <?php if ($headerCanManage): ?>
              <a href="quanlyadmin.php">Quản lý sản phẩm</a>
            <?php endif; ?>
            <a href="hoso.php">Tài khoản của tôi</a>
            <a href="favorites.php">❤️ Sản phẩm yêu thích</a>
            <a href="donhang.php">Lịch sử đơn hàng</a>
            <hr style="border:0; border-top:1px solid rgba(255,255,255,0.2); margin:4px 0;">
            <a href="logout.php">Đăng xuất</a>
          <?php else: ?>
            <a href="dangnhap.php">Đăng nhập</a>
            <a href="dangky.php">Đăng ký</a>
          <?php endif; ?>
        </div>
      </div>
    </div>
    <!-- 
    tạm thời nút đăng nhập đang chuyên tiếp bị lỗi , e check rồi sửa thành dangnhap.php là đc nhé 
    xong rồi đấy , e có hỏi gì không ?
    -->
    

  </div>
</header>

