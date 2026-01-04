<?php
session_start();
include 'config.php';

// lấy currentUser từ SESSION nếu bạn có dùng
$currentUser = isset($_SESSION["currentUser"]) ? $_SESSION["currentUser"] : null;

// Lấy user_id để kiểm tra favorites
$userId = null;
if (isset($_SESSION['user_id'])) {
    $userId = (int)$_SESSION['user_id'];
} elseif (isset($_SESSION['currentUser']['id'])) {
    $userId = (int)$_SESSION['currentUser']['id'];
} elseif (isset($_SESSION['currentUser']['user_id'])) {
    $userId = (int)$_SESSION['currentUser']['user_id'];
}

// Lấy danh sách sản phẩm yêu thích của user
$favoriteProductIds = [];
if ($userId && $userId > 0) {
    $stmt_fav = $conn->prepare("SELECT product_id FROM favorites WHERE user_id = ?");
    $stmt_fav->bind_param("i", $userId);
    $stmt_fav->execute();
    $result_fav = $stmt_fav->get_result();
    while ($row_fav = $result_fav->fetch_assoc()) {
        $favoriteProductIds[] = $row_fav['product_id'];
    }
    $stmt_fav->close();
}

// Lấy tham số danh mục (nếu có) để lọc sản phẩm
$categoryFilter = isset($_GET['danhmuc_id']) ? (int)$_GET['danhmuc_id'] : 0;

// Lấy tham số tìm kiếm (nếu có)
$searchKeyword = isset($_GET['search']) ? trim($_GET['search']) : '';

/**
 * Chỉ thêm điều kiện lọc nếu cột tồn tại để tránh lỗi "Unknown column"
 */
function columnExists(mysqli $conn, string $table, string $column): bool {
    // Lấy tên database hiện tại
    $dbRes = $conn->query("SELECT DATABASE() AS db");
    if (!$dbRes) return false;
    $dbRow = $dbRes->fetch_assoc();
    $dbName = $dbRow ? $dbRow['db'] : '';
    if (!$dbName) return false;

    $sql = "SELECT COUNT(*) AS cnt
            FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = ?
              AND TABLE_NAME   = ?
              AND COLUMN_NAME  = ?";
    if (!$stmt = $conn->prepare($sql)) return false;
    $stmt->bind_param("sss", $dbName, $table, $column);
    $stmt->execute();
    $rs = $stmt->get_result();
    $row = $rs ? $rs->fetch_assoc() : null;
    $stmt->close();
    return !empty($row) && (int)$row['cnt'] > 0;
}

// ====== Lấy danh sách sản phẩm từ bảng sanpham (an toàn theo schema) ======
$products = [];
$where    = [];
$params   = [];
$types    = "";

// Có cột status -> lọc theo trạng thái hiển thị (tuỳ bạn quy ước: 'còn hàng'/'active'...)
if (columnExists($conn, 'sanpham', 'status')) {
    $where[] = "status = 'còn hàng'";
}

// Có cột is_deleted -> loại sản phẩm xóa mềm
if (columnExists($conn, 'sanpham', 'is_deleted')) {
    $where[] = "(is_deleted = 0 OR is_deleted IS NULL)";
}

// Có cột deleted_at -> loại sản phẩm xóa mềm
if (columnExists($conn, 'sanpham', 'deleted_at')) {
    $where[] = "deleted_at IS NULL";
}

// Có cột danhmuc_id và có tham số lọc danh mục
if ($categoryFilter > 0 && columnExists($conn, 'sanpham', 'danhmuc_id')) {
    $where[]  = "danhmuc_id = ?";
    $params[] = $categoryFilter;
    $types   .= "i";
}

// Lọc theo từ khóa tìm kiếm (tìm trong tên và mô tả)
if (!empty($searchKeyword)) {
    $where[]  = "(name LIKE ? OR description LIKE ?)";
    $searchPattern = '%' . $searchKeyword . '%';
    $params[] = $searchPattern;
    $params[] = $searchPattern;
    $types   .= "ss";
}

// Tạo query
$sql = "SELECT id, name, price, image, description FROM sanpham";
if (!empty($where)) {
    $sql .= " WHERE " . implode(" AND ", $where);
}
$sql .= " ORDER BY id DESC";

// Chuẩn bị & thực thi
$stmt = $conn->prepare($sql);

// Bind param nếu có
if ($stmt && $types !== "") {
    $stmt->bind_param($types, ...$params);
}

if ($stmt && $stmt->execute()) {
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        // Chuẩn hóa đường dẫn ảnh để hiển thị đúng trên mọi máy
        if (!empty($row['image'])) {
            $row['image'] = normalizeImagePath($row['image']);
        }
        
        // Lấy thông tin đánh giá cho sản phẩm này
        $productId = $row['id'];
        $stmt_rating = $conn->prepare("SELECT AVG(rating) AS avg_rating, COUNT(*) AS total_ratings FROM product_ratings WHERE product_id = ?");
        $stmt_rating->bind_param("s", $productId);
        $stmt_rating->execute();
        $result_rating = $stmt_rating->get_result();
        $rating_data = $result_rating->fetch_assoc();
        $stmt_rating->close();
        
        $row['avg_rating'] = $rating_data ? round((float)$rating_data['avg_rating'], 1) : 0;
        $row['total_ratings'] = $rating_data ? (int)$rating_data['total_ratings'] : 0;
        
        $products[] = $row;
    }
    $stmt->close();
} else {
    // Fallback: nếu prepare/execute lỗi vì lý do khác, lấy tất cả để không vỡ trang
    $fallback = $conn->query("SELECT id, name, price, image, description FROM sanpham ORDER BY id DESC");
    if ($fallback) {
        while ($row = $fallback->fetch_assoc()) {
            if (!empty($row['image'])) {
                $row['image'] = normalizeImagePath($row['image']);
            }
            
            // Lấy thông tin đánh giá
            $productId = $row['id'];
            $stmt_rating = $conn->prepare("SELECT AVG(rating) AS avg_rating, COUNT(*) AS total_ratings FROM product_ratings WHERE product_id = ?");
            $stmt_rating->bind_param("s", $productId);
            $stmt_rating->execute();
            $result_rating = $stmt_rating->get_result();
            $rating_data = $result_rating->fetch_assoc();
            $stmt_rating->close();
            
            $row['avg_rating'] = $rating_data ? round((float)$rating_data['avg_rating'], 1) : 0;
            $row['total_ratings'] = $rating_data ? (int)$rating_data['total_ratings'] : 0;
            
            $products[] = $row;
        }
    }
}

// ====== Chọn sản phẩm nổi bật làm sản phẩm chính (lấy sản phẩm mới nhất) ======
$featuredProduct = !empty($products) ? $products[0] : null;

$featuredNewPrice = $featuredProduct ? (int)$featuredProduct['price'] : 95000;
$featuredOldPrice = (int)($featuredNewPrice * 1.2);

$featuredImg   = $featuredProduct && !empty($featuredProduct['image'])
    ? normalizeImagePath($featuredProduct['image'])
    : 'image/anhthu1.png';

$featuredName  = $featuredProduct && !empty($featuredProduct['name'])
    ? $featuredProduct['name']
    : 'BÁNH QUY BƠ DỨA';

$featuredDesc  = $featuredProduct && !empty($featuredProduct['description'])
    ? $featuredProduct['description']
    : 'Những người thợ làm bánh bậ thầy của chúng tôi đã đi khắp thế giới và lựa
      chọn những trái cây ngon nhất để tạo ra một món ăn không thể cưỡng lại...';

// Lấy thông tin đánh giá cho sản phẩm chính
$featuredAvgRating = 0;
$featuredTotalRatings = 0;
if ($featuredProduct) {
    $featuredAvgRating = isset($featuredProduct['avg_rating']) ? $featuredProduct['avg_rating'] : 0;
    $featuredTotalRatings = isset($featuredProduct['total_ratings']) ? $featuredProduct['total_ratings'] : 0;
}
?>
<!doctype html>
<html lang="vi">
<head>
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width,initial-scale=1" />
<title>Trang Danisa</title>

<link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@400;500;600;700&display=swap" rel="stylesheet">

<!-- Dùng chung giao diện header/trang chủ -->
<link rel="stylesheet" href="style.css">
<link rel="stylesheet" href="trangchu.css">
<link rel="stylesheet" href="banhquybo.css">

<style>
/* *** CSS cho hiệu ứng ảnh bay vào giỏ + giỏ rung *** */
.flying-img {
  position: fixed;
  z-index: 9999;
  pointer-events: none;
  transition: all 0.8s ease-in-out;
}

.cart-bounce {
  animation: cartBounce 0.3s ease-in-out;
}

@keyframes cartBounce {
  0%   { transform: scale(1); }
  30%  { transform: scale(1.2); }
  60%  { transform: scale(0.9); }
  100% { transform: scale(1); }
}

/* CSS cho nút yêu thích */
.favorite-btn {
  position: absolute;
  top: 10px;
  right: 10px;
  background: rgba(255, 255, 255, 0.9);
  border: none;
  border-radius: 50%;
  width: 40px;
  height: 40px;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  z-index: 10;
  transition: all 0.3s ease;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
  opacity: 1;
  visibility: visible;
}

.favorite-btn:hover {
  background: rgba(255, 255, 255, 1);
  transform: scale(1.1);
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
}

.favorite-btn .heart-icon {
  font-size: 20px;
  transition: transform 0.3s ease;
}

.favorite-btn.active .heart-icon {
  transform: scale(1.2);
  filter: drop-shadow(0 0 4px rgba(255, 0, 0, 0.5));
}

.favorite-btn:active .heart-icon {
  transform: scale(0.9);
}

/* Khi bỏ yêu thích, nút tim sẽ biến mất */
.favorite-btn.removing {
  opacity: 0;
  visibility: hidden;
  transform: scale(0);
  transition: opacity 0.3s ease, transform 0.3s ease, visibility 0.3s ease;
}

.product-box {
  position: relative;
}

/* CSS cho nút yêu thích trong header */
.search-input .favorites-btn {
  background: none;
  border: none;
  color: var(--muted);
  padding: 4px;
  cursor: pointer;
  transition: all 0.3s ease;
  text-decoration: none;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  margin-left: 4px;
}

.search-input .favorites-btn:hover {
  color: #e91e63;
  transform: scale(1.1);
}

.search-input .favorites-btn svg {
  transition: all 0.3s ease;
}

.search-input .favorites-btn:hover svg {
  fill: #e91e63;
}

/* Animation cho tim bay */
.flying-heart {
  position: fixed;
  font-size: 30px;
  z-index: 10000;
  pointer-events: none;
  transition: none;
}

@keyframes flyToFavorites {
  0% {
    opacity: 1;
    transform: scale(1) rotate(0deg);
  }
  50% {
    opacity: 0.8;
    transform: scale(1.2) rotate(180deg);
  }
  100% {
    opacity: 0;
    transform: scale(0.3) rotate(360deg);
  }
}

/* Toast nhỏ báo đã thêm vào giỏ */
.cart-toast {
  position: fixed;
  right: 20px;
 bottom: 20px;
  background: rgba(0,0,0,0.8);
  color: #fff;
  padding: 10px 16px;
  border-radius: 6px;
  font-size: 14px;
  z-index: 10000;
  opacity: 0;
  transform: translateY(20px);
  transition: all 0.3s ease;
}
.cart-toast.show {
  opacity: 1;
  transform: translateY(0);
}

/* Bố cục 2 cột: ảnh bên trái, box thông tin bên phải */
.main-product-layout {
  width: 100%;
  max-width: 1200px;
  margin: 20px auto 40px auto;
  display: flex;
  align-items: flex-start;
  justify-content: flex-start;
}

/* Ảnh bên trái, cách lề 60px */
#main-product-image {
  margin-left: 60px;
  width: 550px;
  height: 650px;
  object-fit: cover;
}

/* Box thông tin bên phải */
.main-product-layout .side-box {
  position: static !important;
  margin-left: 40px;
  background: rgba(255,255,255,0.95);
  color: #111;
  padding: 20px 30px;
  border-radius: 10px;
  max-width: 480px;
  text-align: left;
}

#main-product-desc {
  margin-top: 10px;
  line-height: 1.6;
}

.price-line {
  margin-top: 15px;
}

.buttons {
  margin-top: 18px;
}

.rating {
  margin-top: 14px;
}

/* ===== CHỌN SIZE BÁNH ===== */
.size-select {
  margin-top: 16px;
}

.size-label {
  display: inline-block;
  margin-right: 10px;
  font-weight: 600;
  font-size: 15px;
  color: #374151;
}

.size-options {
  display: inline-flex;
  flex-wrap: wrap;
  gap: 8px;
}

.size-option {
  min-width: 70px;
  padding: 6px 10px;
  border-radius: 999px;
  border: 1px solid #d1d5db;
  background: #ffffff;
  color: #111827;
  font-size: 14px;
  cursor: pointer;
  transition: 0.2s ease;
}

.size-option.active {
  background: #1e40af;
  color: #ffffff;
  border-color: #1e40af;
}

.size-option:hover:not(.active) {
  background: #f3f4f6;
}

/* ====== CHAT WIDGET HỖ TRỢ ====== */
.chat-widget-toggle {
  position: fixed;
  right: 20px;
  bottom: 20px;
  width: 56px;
  height: 56px;
  border-radius: 999px;
  background: #1e40af;
  color: #fff;
  border: none;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 26px;
  cursor: pointer;
  box-shadow: 0 10px 25px rgba(0,0,0,0.3);
  z-index: 9998;
}

/* Badge số tin nhắn chưa đọc ở nút chat */
.chat-unread-badge {
  position: absolute;
  top: -4px;
  right: -4px;
  min-width: 18px;
  height: 18px;
  padding: 0 4px;
  border-radius: 999px;
  background: #ef4444;
  color: #fff;
  font-size: 11px;
  display: none;
  align-items: center;
  justify-content: center;
  box-shadow: 0 0 0 2px #f9fafb;
}

.chat-widget-toggle span {
  font-size: 26px;
}

/* Khung chat */
.chat-widget-box {
  position: fixed;
  right: 20px;
  bottom: 90px;
  width: 320px;
  max-height: 450px;
  background: #f9fafb;
  border-radius: 12px;
  box-shadow: 0 10px 25px rgba(0,0,0,0.35);
  display: none;
  flex-direction: column;
  overflow: hidden;
  z-index: 9999;
  font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
}

/* header */
.chat-header {
  background: #1e40af;
  color: #fff;
  padding: 10px 12px;
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.chat-header .title {
  font-size: 14px;
  font-weight: 600;
}

.chat-header .subtitle {
  font-size: 11px;
  opacity: 0.8;
}

.chat-header .close-btn {
  border: none;
  background: transparent;
  color: #fff;
  font-size: 18px;
  cursor: pointer;
}

/* nội dung */
.chat-messages {
  flex: 1;
  padding: 8px 10px;
  background: #eef2ff;
  overflow-y: auto;
  font-size: 13px;
}

.chat-message {
  margin-bottom: 6px;
  display: flex;
  flex-direction: column;
  max-width: 85%;
}

.chat-message.user {
  margin-left: auto;
  align-items: flex-end;
}

.chat-message.admin {
  margin-right: auto;
  align-items: flex-start;
}

.chat-message-bubble {
  padding: 6px 8px;
  border-radius: 10px;
  line-height: 1.4;
  word-wrap: break-word;
}

.chat-message.user .chat-message-bubble {
  background: #3b82f6;
  color: #fff;
  border-bottom-right-radius: 2px;
}

.chat-message.admin .chat-message-bubble {
  background: #ffffff;
  color: #111827;
  border-bottom-left-radius: 2px;
}

.chat-message-time {
  font-size: 10px;
  opacity: 0.7;
  margin-top: 2px;
}

/* input */
.chat-input-area {
  border-top: 1px solid #e5e7eb;
  padding: 6px;
  background: #f9fafb;
  display: flex;
  align-items: center;
  gap: 4px;
}

.chat-input-area textarea {
  flex: 1;
  min-height: 36px;
  max-height: 80px;
  resize: none;
  padding: 4px 6px;
  font-size: 13px;
  border-radius: 6px;
  border: 1px solid #d1d5db;
  outline: none;
}

.chat-input-area textarea:focus {
  border-color: #1d4ed8;
}

.chat-input-area button {
  border: none;
  background: #1e40af;
  color: #fff;
  padding: 6px 10px;
  border-radius: 8px;
  font-size: 13px;
  cursor: pointer;
}

.chat-input-area button:disabled {
  opacity: 0.6;
  cursor: default;
}

/* trạng thái */
.chat-status {
  font-size: 11px;
  color: #6b7280;
  padding: 2px 8px 6px 8px;
}
</style>

</head>

<body>
<?php
$headerCartExtraClass = 'cart-icon';
include 'header_front.php';
?>

<!-- Dùng class riêng cho trang sản phẩm để không đụng CSS .menu của trang chủ -->
<div class="menu-sanpham">
<section style="
  background: url('image/anhnen3.jpg') center/cover no-repeat;
  min-height: 650px;
  display: flex;
  flex-direction: column;
  justify-content: flex-start;
  align-items: center;
  padding: 20px 20px 60px 20px;
  text-align: center;
  color: white;
">

  <!-- NHÓM ẢNH + THÔNG TIN CHÍNH (ẢNH TRÁI, THÔNG TIN PHẢI) -->
  <div class="main-product-layout">
    <img 
      src="<?= htmlspecialchars($featuredImg); ?>" 
      id="main-product-image"
      alt="<?= htmlspecialchars($featuredName); ?>">

    <div class="side-box content-box"
         id="main-product-box"
         data-id="<?= $featuredProduct ? (int)$featuredProduct['id'] : 1; ?>"
         data-name="<?= htmlspecialchars($featuredName); ?>"
         data-price="<?= (int)$featuredNewPrice; ?>"
         data-base-price="<?= (int)$featuredNewPrice; ?>"
         data-image="<?= htmlspecialchars($featuredImg); ?>"
         data-desc="<?= htmlspecialchars($featuredDesc); ?>"
         data-size="200g">
      <h2 id="main-product-title"><?= htmlspecialchars($featuredName); ?></h2>

      <p id="main-product-desc">
        <?= nl2br(htmlspecialchars($featuredDesc)); ?>
      </p>

      <div class="price-line">
        <span class="old-price" id="main-old-price">
          <?= number_format($featuredOldPrice, 0, ',', '.'); ?>đ
        </span>
        <span class="new-price" id="main-new-price">
          <?= number_format($featuredNewPrice, 0, ',', '.'); ?>đ
        </span>
      </div>

      <!-- CHỌN SIZE BÁNH -->
      <div class="size-select">
        <span class="size-label">Khối lượng:</span>
        <div class="size-options">
          <button type="button" class="size-option active" data-size="200g">200g</button>
          <button type="button" class="size-option" data-size="454g">454g</button>
          <button type="button" class="size-option" data-size="681g">681g</button>
          <button type="button" class="size-option" data-size="908g">908g</button>
        </div>
      </div>

      <div class="buttons">  
        <button class="btn buy-now">Đặt Hàng</button>
        <button class="btn add-cart">Thêm Vào Giỏ </button>
      </div>

      <div class="rating" id="rating-box" data-rating="0" data-product-id="<?= $featuredProduct ? htmlspecialchars($featuredProduct['id']) : ''; ?>">
        <span class="star" data-value="1">★</span>
        <span class="star" data-value="2">★</span>
        <span class="star" data-value="3">★</span>
        <span class="star" data-value="4">★</span>
        <span class="star" data-value="5">★</span>
        <span class="rating-info" id="rating-info">
          <?php if ($featuredTotalRatings > 0): ?>
            <?= number_format($featuredAvgRating, 1); ?>/5 sao (<?= $featuredTotalRatings; ?> đánh giá)
          <?php else: ?>
            Chưa có đánh giá
          <?php endif; ?>
        </span>
      </div>
    </div>
  </div>

  <!-- CAROUSEL BOX PHÍA DƯỚI – GIỮ GIAO DIỆN, CHỈ ĐỔ DỮ LIỆU TỪ DB -->
  <div class="product-wrapper">
    <div class="arrow-left">&#8249;</div>

    <div class="product-scroll">
      <?php if (empty($products)): ?>
        <p style="color:white; padding:20px;">Chưa có sản phẩm nào.</p>
      <?php else: ?>
        <?php foreach ($products as $sp): ?>
          <?php
            $newPrice = (int)$sp['price'];
            $oldPrice = (int)($newPrice * 1.2); // tự tăng 20% làm giá cũ

            // Chuẩn hóa đường dẫn ảnh để hiển thị đúng trên mọi máy
            $imgPath = !empty($sp['image']) ? normalizeImagePath($sp['image']) : '';
          ?>
          <div class="product-box"
               data-id="<?= $sp['id']; ?>"
               data-name="<?= htmlspecialchars($sp['name']); ?>"
               data-price="<?= (int)$sp['price']; ?>"
               data-base-price="<?= (int)$sp['price']; ?>"
               data-image="<?= htmlspecialchars($imgPath); ?>"
               data-desc="<?= htmlspecialchars($sp['description']); ?>"
               data-size="200g">
            <?php 
              $imgPath = !empty($sp['image']) ? normalizeImagePath($sp['image']) : '';
              $isFavorite = in_array($sp['id'], $favoriteProductIds);
            ?>
            <button class="favorite-btn <?= $isFavorite ? 'active' : ''; ?>" 
                    data-product-id="<?= htmlspecialchars($sp['id']); ?>"
                    onclick="toggleFavorite(this, '<?= htmlspecialchars($sp['id']); ?>')"
                    title="<?= $isFavorite ? 'Bỏ yêu thích' : 'Thêm vào yêu thích'; ?>">
              <span class="heart-icon">❤️</span>
            </button>
            <?php if ($imgPath): ?>
              <img src="<?= htmlspecialchars($imgPath); ?>" alt="<?= htmlspecialchars($sp['name']); ?>" onerror="this.style.display='none'">
            <?php endif; ?>
            <h3><?= htmlspecialchars($sp['name']); ?></h3>
            <p style="color:black"><?= htmlspecialchars($sp['name']); ?></p>
            <span class="old-price"><?= number_format($oldPrice, 0, ',', '.'); ?>đ</span>
            <span class="new-price"><?= number_format($newPrice, 0, ',', '.'); ?>đ</span>
            
            <!-- Hiển thị đánh giá sản phẩm -->
            <div class="product-rating" style="margin: 10px 0; display: flex; align-items: center; justify-content: center; gap: 5px;">
              <?php
                $avgRating = isset($sp['avg_rating']) ? $sp['avg_rating'] : 0;
                $totalRatings = isset($sp['total_ratings']) ? $sp['total_ratings'] : 0;
                $fullStars = floor($avgRating);
                $hasHalfStar = ($avgRating - $fullStars) >= 0.5;
              ?>
              <div style="display: flex; gap: 2px;">
                <?php for ($i = 1; $i <= 5; $i++): ?>
                  <?php if ($i <= $fullStars): ?>
                    <span style="color: #facc15; font-size: 14px;">★</span>
                  <?php elseif ($i == $fullStars + 1 && $hasHalfStar): ?>
                    <span style="color: #facc15; font-size: 14px;">☆</span>
                  <?php else: ?>
                    <span style="color: #ddd; font-size: 14px;">★</span>
                  <?php endif; ?>
                <?php endfor; ?>
              </div>
              <?php if ($totalRatings > 0): ?>
                <span style="font-size: 12px; color: #666;">
                  <?= number_format($avgRating, 1); ?> (<?= $totalRatings; ?>)
                </span>
              <?php else: ?>
                <span style="font-size: 12px; color: #999;">Chưa có đánh giá</span>
              <?php endif; ?>
            </div>
            
            <div class="btn-group">
              <button class="order-btn">Đặt hàng</button>
              <button class="cart-btn">Thêm vào giỏ</button>
            </div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>

    <div class="arrow-right">&#8250;</div>
  </div>

</section>
</div>

<!-- Toast báo đã thêm vào giỏ -->
<div id="cart-toast" class="cart-toast">Đã thêm vào giỏ hàng</div>

<script>
document.addEventListener("DOMContentLoaded", function() {
  // ===== Dropdown header giống trang chủ =====
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

  // ===== Tài khoản (dùng localStorage để hiển thị tên) =====
  const currentUser = JSON.parse(localStorage.getItem("currentUser"));
  const accountDropdown = document.querySelector(".account-dropdown");
  if (currentUser && accountDropdown) {
    const nameTag = document.createElement("span");
    nameTag.textContent = currentUser.username || currentUser.email || 'Tài khoản';
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

  // ===== RATING: cho phép click chọn sao và lưu vào database =====
  const ratingBox  = document.getElementById("rating-box");
  const ratingInfo = document.getElementById("rating-info");

  if (ratingBox && ratingInfo) {
    const stars = ratingBox.querySelectorAll(".star");
    const productId = ratingBox.dataset.productId || '';
    let currentRating = 0;
    let avgRating = 0;
    let totalRatings = 0;

    // Lấy thông tin rating từ text hiện tại
    const currentText = ratingInfo.textContent.trim();
    const ratingMatch = currentText.match(/([\d.]+)\/5 sao \((\d+) đánh giá\)/);
    if (ratingMatch) {
      avgRating = parseFloat(ratingMatch[1]);
      totalRatings = parseInt(ratingMatch[2]);
    }

    function renderStars(rating) {
      stars.forEach(star => {
        const value = Number(star.dataset.value);
        if (value <= rating) {
          star.classList.add("active");
        } else {
          star.classList.remove("active");
        }
      });
    }

    function updateRatingDisplay(userRating, avg, total) {
      renderStars(userRating);
      
      if (total > 0) {
        ratingInfo.innerHTML = `
          <span style="display: block; margin-bottom: 4px;">
            ${userRating > 0 ? `Bạn đánh giá: ${userRating}/5 sao` : ''}
          </span>
          <span style="display: block; font-size: 13px; opacity: 0.9;">
            Trung bình: ${avg.toFixed(1)}/5 sao (${total} đánh giá)
          </span>
        `;
      } else {
        if (userRating > 0) {
          ratingInfo.textContent = `Bạn đánh giá ${userRating}/5 sao`;
        } else {
          ratingInfo.textContent = "Nhấn để đánh giá";
        }
      }
    }

    // Hiển thị rating trung bình ban đầu
    updateRatingDisplay(0, avgRating, totalRatings);

    stars.forEach(star => {
      star.addEventListener("click", () => {
        const value = Number(star.dataset.value);
        currentRating = value;
        
        // Lưu vào localStorage
        localStorage.setItem("mainProductRating", String(currentRating));
        
        // Lưu vào database
        if (productId) {
          const formData = new FormData();
          formData.append('product_id', productId);
          formData.append('rating', value);

          fetch('save_rating.php', {
            method: 'POST',
            body: formData
          })
          .then(response => response.json())
          .then(data => {
            if (data.status === 'success') {
              // Cập nhật hiển thị với thông tin mới từ server
              avgRating = parseFloat(data.avg_rating) || 0;
              totalRatings = parseInt(data.total_ratings) || 0;
              updateRatingDisplay(currentRating, avgRating, totalRatings);
            } else {
              alert(data.message || 'Không thể lưu đánh giá.');
            }
          })
          .catch(error => {
            console.error('Error:', error);
            alert('Có lỗi xảy ra khi lưu đánh giá.');
          });
        } else {
          // Nếu không có productId, chỉ cập nhật UI
          updateRatingDisplay(currentRating, avgRating, totalRatings);
        }
      });
    });
  }

  // ===== Carousel scroll =====
  const scrollContainer = document.querySelector(".product-scroll");
  const arrowRight = document.querySelector(".arrow-right");
  const arrowLeft = document.querySelector(".arrow-left");

  if (scrollContainer && arrowRight && arrowLeft) {
    arrowRight.addEventListener("click", function () {
      scrollContainer.scrollBy({
        left: 300,
        behavior: "smooth"
      });
    });

    arrowLeft.addEventListener("click", function () {
      scrollContainer.scrollBy({
        left: -300,
        behavior: "smooth"
      });
    });
  }

  // ===== Tham chiếu sản phẩm chính & danh sách box =====
  const productBoxes = document.querySelectorAll(".product-box");

  const mainBox      = document.getElementById("main-product-box");
  const mainImage    = document.getElementById("main-product-image");
  const mainTitle    = document.getElementById("main-product-title");
  const mainDesc     = document.getElementById("main-product-desc");
  const mainOldPrice = document.getElementById("main-old-price");
  const mainNewPrice = document.getElementById("main-new-price");

  // *** LẤY GIỎ HÀNG TRÊN HEADER
  const cartIcon  = document.querySelector(".cart-icon");
  const cartToast = document.getElementById("cart-toast");

  // ===== CHỌN SIZE BÁNH CHO SẢN PHẨM CHÍNH =====
  let currentSize = mainBox ? (mainBox.dataset.size || "200g") : "200g";
  const sizeOptions = document.querySelectorAll(".size-option");

  function formatCurrencyVND(value) {
    const n = Number(value) || 0;
    return n.toLocaleString('vi-VN');
  }

  function parsePriceText(text) {
    if (!text) return "0";
    const digits = text.replace(/[^\d]/g, "");
    return digits || "0";
  }

  // Tính giá theo size: basePrice là giá 200g
  function updateMainPriceBySize(sizeStr) {
    if (!mainBox) return;
    const basePrice = Number(mainBox.dataset.basePrice || mainBox.dataset.price || 0);
    if (!basePrice) return;

    const baseWeight = 200; // 200g là size chuẩn
    const weight = parseInt(sizeStr) || baseWeight;

    const pricePerGram = basePrice / baseWeight;
    let newPrice = pricePerGram * weight;

    // làm tròn nghìn gần nhất
    newPrice = Math.round(newPrice / 1000) * 1000;
    let oldPrice = newPrice * 1.2;
    oldPrice = Math.round(oldPrice / 1000) * 1000;

    // cập nhật dataset cho giỏ hàng
    mainBox.dataset.price = String(newPrice);

    if (mainNewPrice) {
      mainNewPrice.textContent = formatCurrencyVND(newPrice) + 'đ';
    }
    if (mainOldPrice) {
      mainOldPrice.textContent = formatCurrencyVND(oldPrice) + 'đ';
    }
  }

  // Gán sự kiện chọn size
  if (sizeOptions.length) {
    sizeOptions.forEach(btn => {
      if (btn.dataset.size === currentSize) {
        btn.classList.add("active");
      }
      btn.addEventListener("click", () => {
        sizeOptions.forEach(b => b.classList.remove("active"));
        btn.classList.add("active");
        currentSize = btn.dataset.size;
        if (mainBox) {
          mainBox.dataset.size = currentSize;
        }
        updateMainPriceBySize(currentSize);
      });
    });
  }

  // Click icon giỏ hàng -> sang trang giỏ hàng
  if (cartIcon) {
    cartIcon.addEventListener("click", function(e) {
      e.preventDefault();
      window.location.href = "giohang.php";
    });
  }

  // Lưu trạng thái hiện tại của sản phẩm trên khung chính (phục vụ hoán đổi)
  let mainData = {
    img:      mainImage ? mainImage.getAttribute("src") : "",
    title:    mainTitle ? mainTitle.textContent : "",
    desc:     mainDesc ? mainDesc.textContent : "",
    oldPrice: mainOldPrice ? mainOldPrice.textContent : "",
    newPrice: mainNewPrice ? mainNewPrice.textContent : ""
  };

  function getProductDataFromBox(box) {
    const titleEl = box.querySelector('h3');
    const imgEl   = box.querySelector('img');
    const priceEl = box.querySelector('.new-price') || box.querySelector('.old-price');

    const id    = box.dataset.id || (titleEl ? titleEl.textContent.trim() : '');
    const name  = box.dataset.name || (titleEl ? titleEl.textContent.trim() : '');
    const image = box.dataset.image || (imgEl ? imgEl.getAttribute('src') : '');
    const rawPrice = box.dataset.price || (priceEl ? priceEl.textContent : '0');
    const price = parsePriceText(rawPrice);
    const size  = box.dataset.size || "200g";

    return { id, name, image, price, size, imgEl };
  }

  // LẤY DỮ LIỆU TỪ SẢN PHẨM CHÍNH - DÙNG DATASET
  function getProductDataFromMain() {
    const priceEl = document.getElementById('main-new-price') || document.getElementById('main-old-price');

    const id    = mainBox ? (mainBox.dataset.id || "") : "";
    const name  = mainBox ? (mainBox.dataset.name || (mainTitle ? mainTitle.textContent.trim() : "")) : (mainTitle ? mainTitle.textContent.trim() : "");
    const image = mainBox ? (mainBox.dataset.image || (mainImage ? mainImage.getAttribute('src') : '')) : (mainImage ? mainImage.getAttribute('src') : '');
    const rawPrice = mainBox ? (mainBox.dataset.price || (priceEl ? priceEl.textContent : '0')) : (priceEl ? priceEl.textContent : '0');
    const price = parsePriceText(rawPrice);
    const size  = mainBox ? (mainBox.dataset.size || currentSize || "200g") : (currentSize || "200g");

    return { id, name, image, price, size, imgEl: mainImage };
  }

  // *** HÀM ẢNH BAY VÀO GIỎ
  function flyToCart(imgEl) {
    if (!imgEl || !cartIcon) return;

    const imgRect = imgEl.getBoundingClientRect();
    const cartRect = cartIcon.getBoundingClientRect();

    const flying = imgEl.cloneNode(true);
    flying.classList.add("flying-img");
    flying.style.left = imgRect.left + "px";
    flying.style.top  = imgRect.top + "px";
    flying.style.width  = imgRect.width + "px";
    flying.style.height = imgRect.height + "px";
    document.body.appendChild(flying);

    requestAnimationFrame(() => {
      flying.style.left = (cartRect.left + cartRect.width / 2) + "px";
      flying.style.top  = (cartRect.top + cartRect.height / 2) + "px";
      flying.style.transform = "scale(0.1)";
      flying.style.opacity   = "0";
    });

    flying.addEventListener("transitionend", () => {
      flying.remove();
      cartIcon.classList.add("cart-bounce");
      setTimeout(() => cartIcon.classList.remove("cart-bounce"), 300);
    });
  }

  // *** HÀM HIỆN TOAST "Đã thêm vào giỏ"
  function showCartToast() {
    if (!cartToast) return;
    cartToast.classList.add("show");
    setTimeout(() => {
      cartToast.classList.remove("show");
    }, 1200);
  }

  // ===== GỬI SẢN PHẨM LÊN giohang.php (SESSION PHP) =====
  // product: {id, name, image, price, size}, action: 'add' | 'buy_now'
  function addToCart(product, action) {
    const params = new URLSearchParams();
    params.set('action', action);
    params.set('id', product.id);
    params.set('name', product.name);
    params.set('price', product.price);
    params.set('image', product.image);
    if (product.size) {
      params.set('size', product.size);
    }

    if (action === 'add') {
      // Thêm vào giỏ: request ngầm để PHP cập nhật session, KHÔNG rời trang
      fetch('giohang.php?' + params.toString(), { method: 'GET' })
        .catch(err => console.error('Lỗi thêm giỏ hàng:', err));
    } else if (action === 'buy_now') {
      // Đặt hàng: chuyển hướng sang giohang.php -> thanhtoan.php
      window.location.href = 'giohang.php?' + params.toString();
    }
  }

  // ===== Hoán đổi sản phẩm: box dưới <-> khung chính (click vào box) =====
  productBoxes.forEach(box => {
    box.addEventListener("click", function() {
      const imgEl      = box.querySelector("img");
      const titleEl    = box.querySelector("h3");
      const oldPriceEl = box.querySelector(".old-price");
      const newPriceEl = box.querySelector(".new-price");

      // Dataset hiện tại của main-box
      const mainDataset = mainBox ? {
        id:    mainBox.dataset.id   || "",
        name:  mainBox.dataset.name || (mainTitle ? mainTitle.textContent : ""),
        price: mainBox.dataset.price|| parsePriceText(mainNewPrice ? mainNewPrice.textContent : "0"),
        basePrice: mainBox.dataset.basePrice || mainBox.dataset.price || parsePriceText(mainNewPrice ? mainNewPrice.textContent : "0"),
        image: mainBox.dataset.image|| (mainImage ? mainImage.getAttribute("src") : ""),
        desc:  mainBox.dataset.desc || (mainDesc ? mainDesc.textContent : ""),
        size:  mainBox.dataset.size || currentSize || "200g"
      } : {};

      // Dataset hiện tại của box được click
      const boxDataset = {
        id:    box.dataset.id   || "",
        name:  box.dataset.name || (titleEl ? titleEl.textContent : ""),
        price: box.dataset.price|| parsePriceText(newPriceEl ? newPriceEl.textContent : "0"),
        basePrice: box.dataset.basePrice || box.dataset.price || parsePriceText(newPriceEl ? newPriceEl.textContent : "0"),
        image: box.dataset.image|| (imgEl ? imgEl.getAttribute("src") : ""),
        desc:  box.dataset.desc || "",
        size:  box.dataset.size || "200g"
      };

      const boxDataView = {
        img:      boxDataset.image,
        title:    boxDataset.name,
        desc:     boxDataset.desc,
        oldPrice: oldPriceEl ? oldPriceEl.textContent : "",
        newPrice: newPriceEl ? newPriceEl.textContent : ""
      };

      // 1) Hoán đổi GIAO DIỆN: main <-> box
      // --- đưa dữ liệu main hiện tại xuống box ---
      if (imgEl && mainData.img) {
        imgEl.setAttribute("src", mainData.img);
      }
      if (titleEl && mainData.title) {
        titleEl.textContent = mainData.title;
      }
      if (oldPriceEl && mainData.oldPrice) {
        oldPriceEl.textContent = mainData.oldPrice;
      }
      if (newPriceEl && mainData.newPrice) {
        newPriceEl.textContent = mainData.newPrice;
      }
      if (mainData.desc) {
        box.dataset.desc = mainData.desc;
      }

      // --- đưa dữ liệu box lên main ---
      if (boxDataView.img && mainImage) {
        mainImage.setAttribute("src", boxDataView.img);
      }
      if (boxDataView.title && mainTitle) {
        mainTitle.textContent = boxDataView.title;
      }
      if (mainDesc) {
        mainDesc.textContent = boxDataView.desc || mainDesc.textContent;
      }
      if (boxDataView.oldPrice && mainOldPrice) {
        mainOldPrice.textContent = boxDataView.oldPrice;
      }
      if (boxDataView.newPrice && mainNewPrice) {
        mainNewPrice.textContent = boxDataView.newPrice;
      }

      // 2) Hoán đổi DATASET: để thêm giỏ / đặt hàng dùng đúng ID/giá/size
      if (mainBox) {
        mainBox.dataset.id        = boxDataset.id;
        mainBox.dataset.name      = boxDataset.name;
        mainBox.dataset.price     = boxDataset.price;
        mainBox.dataset.basePrice = boxDataset.basePrice;
        mainBox.dataset.image     = boxDataset.image;
        mainBox.dataset.desc      = boxDataset.desc;
        mainBox.dataset.size      = boxDataset.size || "200g";
      }

      box.dataset.id        = mainDataset.id;
      box.dataset.name      = mainDataset.name;
      box.dataset.price     = mainDataset.price;
      box.dataset.basePrice = mainDataset.basePrice;
      box.dataset.image     = mainDataset.image;
      box.dataset.desc      = mainDataset.desc;
      box.dataset.size      = mainDataset.size || "200g";

      // 3) Cập nhật mainData (phục vụ các lần hoán đổi tiếp theo)
      mainData = {
        img:      boxDataView.img,
        title:    boxDataView.title,
        desc:     boxDataView.desc,
        oldPrice: boxDataView.oldPrice,
        newPrice: boxDataView.newPrice
      };

      // 4) Cập nhật size hiện tại & nút size + tính lại giá theo size
      currentSize = mainBox.dataset.size || "200g";
      if (sizeOptions.length) {
        sizeOptions.forEach(btn => {
          btn.classList.toggle("active", btn.dataset.size === currentSize);
        });
      }
      updateMainPriceBySize(currentSize);
    });
  });

  // ===== NÚT "Đặt hàng" VÀ "Thêm vào giỏ" TRONG BOX DƯỚI =====
  productBoxes.forEach(box => {
    const orderBtn = box.querySelector('.order-btn');
    const cartBtn  = box.querySelector('.cart-btn');
    const imgEl    = box.querySelector('img');

    if (orderBtn) {
      orderBtn.addEventListener('click', function(e) {
        e.stopPropagation();
        e.preventDefault();
        const data = getProductDataFromBox(box);
        flyToCart(imgEl);
        showCartToast();
        addToCart(data, 'buy_now'); // Đặt hàng
      });
    }

    if (cartBtn) {
      cartBtn.addEventListener('click', function(e) {
        e.stopPropagation();
        e.preventDefault();
        const data = getProductDataFromBox(box);
        flyToCart(imgEl);
        showCartToast();
        addToCart(data, 'add'); // Thêm giỏ
      });
    }
  });

  // ===== NÚT Ở SẢN PHẨM CHÍNH =====
  const mainBuyBtn  = document.querySelector('.buttons .buy-now');
  const mainCartBtn = document.querySelector('.buttons .add-cart');

  if (mainBuyBtn) {
    mainBuyBtn.addEventListener('click', function(e) {
      e.preventDefault();
      const data = getProductDataFromMain();
      flyToCart(mainImage);
      showCartToast();
      addToCart(data, 'buy_now');
    });
  }

  if (mainCartBtn) {
    mainCartBtn.addEventListener('click', function(e) {
      e.preventDefault();
      const data = getProductDataFromMain();
      flyToCart(mainImage);
      showCartToast();
      addToCart(data, 'add');
    });
  }
});
</script>

<!-- ====== JS CHAT BOX (ĐÃ XEM / CHƯA XEM + BADGE) ====== -->
<script>
document.addEventListener('DOMContentLoaded', function () {
  const chatToggleBtn = document.getElementById('chat-toggle-btn');
  const chatBox       = document.getElementById('chat-box');
  const chatCloseBtn  = document.getElementById('chat-close-btn');
  const chatMessages  = document.getElementById('chat-messages');
  const chatInput     = document.getElementById('chat-input');
  const chatSendBtn   = document.getElementById('chat-send-btn');
  const chatStatus    = document.getElementById('chat-status');
  const chatBadge     = document.getElementById('chat-unread-badge');

  let lastMessageId = 0;         // ID tin nhắn mới nhất (server trả về)
  let lastSeenId    = 0;         // ID tin nhắn cuối cùng mà user đã đọc
  let unreadCount   = 0;         // Số tin nhắn admin chưa đọc
  let pollingTimer  = null;
  let isSending     = false;

  // ===== LẤY THÔNG TIN USER ĐĂNG NHẬP TỪ localStorage =====
  let frontendUserId   = 0;
  let frontendUsername = 'Khách';

  try {
    const lsUserRaw = localStorage.getItem('currentUser');
    if (lsUserRaw) {
      const lsUser = JSON.parse(lsUserRaw);
      frontendUserId   = lsUser.id || lsUser.user_id || 0;
      frontendUsername = lsUser.username || lsUser.email || 'Khách';
    }
  } catch (e) {
    console.warn('Không đọc được currentUser từ localStorage:', e);
  }

  // Key lưu lastSeenId theo từng user
  const storageKeyLastSeen = 'chat_last_seen_id_' + frontendUserId;
  lastSeenId = Number(localStorage.getItem(storageKeyLastSeen) || '0');

  function isChatOpen() {
    return chatBox && chatBox.style.display === 'flex';
  }

  function updateUnreadBadge() {
    if (!chatBadge) return;
    if (unreadCount > 0) {
      chatBadge.textContent = unreadCount > 9 ? '9+' : unreadCount;
      chatBadge.style.display = 'flex';
    } else {
      chatBadge.style.display = 'none';
    }
  }

  function saveLastSeen() {
    localStorage.setItem(storageKeyLastSeen, String(lastSeenId));
  }

  function appendMessage(msg) {
    const wrap  = document.createElement('div');
    const bubble= document.createElement('div');
    const time  = document.createElement('div');

    wrap.classList.add('chat-message');
    if (msg.sender_type === 'user') {
      wrap.classList.add('user');
    } else {
      wrap.classList.add('admin');
    }

    bubble.classList.add('chat-message-bubble');
    bubble.textContent = msg.message;

    time.classList.add('chat-message-time');
    time.textContent = msg.time || '';

    wrap.appendChild(bubble);
    wrap.appendChild(time);
    chatMessages.appendChild(wrap);

    chatMessages.scrollTop = chatMessages.scrollHeight;
  }

  // Load tin nhắn (dùng cho cả mở chat và polling)
  function loadMessages(initial = false) {
    const url = 'chat_api.php?action=load'
      + '&last_id=' + encodeURIComponent(lastMessageId)
      + '&frontend_user_id=' + encodeURIComponent(frontendUserId);

    fetch(url)
      .then(r => r.json())
      .then(data => {
        if (data.status === 'success' && Array.isArray(data.messages)) {

          const chatCurrentlyOpen = isChatOpen();
          let newAdminMsgCount = 0;

          if (initial && data.messages.length === 0 && chatCurrentlyOpen) {
            chatStatus.textContent = 'Xin chào! Hãy để lại tin nhắn, nhân viên sẽ trả lời sớm nhất.';
          } else if (initial && data.messages.length > 0 && chatCurrentlyOpen) {
            chatStatus.textContent = 'Đang chat với hỗ trợ.';
          }

          data.messages.forEach(m => {
            // đảm bảo id là số
            const msgId = Number(m.id || 0);

            appendMessage(m);

            if (msgId > lastMessageId) {
              lastMessageId = msgId;
            }

            // Nếu chat đang đóng & tin từ admin & id > lastSeenId => là tin chưa đọc
            if (!chatCurrentlyOpen && m.sender_type === 'admin' && msgId > lastSeenId) {
              newAdminMsgCount++;
            }
          });

          // Nếu chat đang mở: coi như user đã xem hết đến lastMessageId
          if (chatCurrentlyOpen) {
            if (lastMessageId > lastSeenId) {
              lastSeenId = lastMessageId;
              saveLastSeen();
            }
            unreadCount = 0;
            updateUnreadBadge();
            if (!initial) {
              chatStatus.textContent = 'Đang chat với hỗ trợ.';
            }
          } else {
            // Chat đang đóng, có tin admin mới => cập nhật badge
            if (newAdminMsgCount > 0) {
              unreadCount += newAdminMsgCount;
              updateUnreadBadge();
              // status này user chỉ thấy khi mở box, nhưng vẫn ok
              chatStatus.textContent = 'Bạn có ' + unreadCount + ' tin nhắn mới từ hỗ trợ.';
            }
          }

        } else if (data.status === 'error' && initial) {
          chatStatus.textContent = data.message || 'Không tải được tin nhắn.';
        }
      })
      .catch(err => {
        if (initial) {
          chatStatus.textContent = 'Lỗi kết nối tới server.';
        }
        console.error('Chat load error:', err);
      });
  }

  function startPolling() {
    if (pollingTimer) return;
    pollingTimer = setInterval(function () {
      loadMessages(false);
    }, 3000);
  }

  function stopPolling() {
    if (pollingTimer) {
      clearInterval(pollingTimer);
      pollingTimer = null;
    }
  }

  function sendMessage() {
    if (isSending) return;
    const text = chatInput.value.trim();
    if (!text) return;

    isSending = true;
    chatSendBtn.disabled = true;

    const formData = new FormData();
    formData.append('action', 'send');
    formData.append('message', text);
    formData.append('frontend_user_id', frontendUserId);
    formData.append('frontend_username', frontendUsername);

    fetch('chat_api.php', {
      method: 'POST',
      body: formData
    })
      .then(r => r.json())
      .then(data => {
        isSending = false;
        chatSendBtn.disabled = false;

        if (data.status === 'success') {
          chatInput.value = '';

          // Khi user đang mở chat và gửi tin => coi như đã xem hết
          if (isChatOpen()) {
            if (lastMessageId > lastSeenId) {
              lastSeenId = lastMessageId;
              saveLastSeen();
            }
            unreadCount = 0;
            updateUnreadBadge();
          }

          loadMessages(false);

          if (chatStatus.textContent.indexOf('Xin chào') === 0) {
            chatStatus.textContent = 'Đang chat với hỗ trợ.';
          }
        } else {
          alert(data.message || 'Không gửi được tin nhắn.');
        }
      })
      .catch(err => {
        isSending = false;
        chatSendBtn.disabled = false;
        console.error('Chat send error:', err);
        alert('Lỗi kết nối khi gửi tin nhắn.');
      });
  }

  // ====== SỰ KIỆN UI ======
  if (chatToggleBtn && chatBox) {
    chatToggleBtn.addEventListener('click', function () {
      const open = isChatOpen();
      if (open) {
        // Đóng chat: chỉ ẩn box, vẫn polling để nhận thông báo
        chatBox.style.display = 'none';
      } else {
        // Mở chat
        chatBox.style.display = 'flex';

        // Khi mở chat, đánh dấu đã xem tất cả đến lastMessageId
        if (lastMessageId > lastSeenId) {
          lastSeenId = lastMessageId;
          saveLastSeen();
        }
        unreadCount = 0;
        updateUnreadBadge();

        chatStatus.textContent = 'Đang kết nối...';
        loadMessages(true);
        startPolling();
      }
    });
  }

  if (chatCloseBtn && chatBox) {
    chatCloseBtn.addEventListener('click', function () {
      chatBox.style.display = 'none';
      // không stopPolling để vẫn nhận được tin nhắn mới
    });
  }

  if (chatSendBtn) {
    chatSendBtn.addEventListener('click', function () {
      sendMessage();
    });
  }

  if (chatInput) {
    chatInput.addEventListener('keydown', function (e) {
      if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        sendMessage();
      }
    });
  }

  // Bắt đầu polling ngay khi vào trang, để khi admin nhắn trong lúc chat đang đóng
  // thì badge vẫn đếm số chưa đọc
  startPolling();
  // Lần đầu load (không mở chat, initial=false)
  loadMessages(false);

  // ====== CHỨC NĂNG TÌM KIẾM SẢN PHẨM ======
  // Đợi một chút để đảm bảo DOM đã load hoàn toàn
  setTimeout(function() {
    const searchInput = document.getElementById('product-search-input');
    
    if (!searchInput) {
      console.log('Không tìm thấy ô tìm kiếm');
      return;
    }
    
    const productScroll = document.querySelector('.product-scroll');
    let resultMessage = null;
    
    function createResultMessage() {
      if (!resultMessage && productScroll && productScroll.parentNode) {
        resultMessage = document.createElement('div');
        resultMessage.id = 'search-result-message';
        resultMessage.style.cssText = 'color: white; padding: 10px 20px; text-align: center; font-size: 14px; margin-bottom: 10px;';
        productScroll.parentNode.insertBefore(resultMessage, productScroll);
      }
      return resultMessage;
    }
    
    function filterProducts() {
      // Lấy lại danh sách product boxes mỗi lần filter
      const boxes = document.querySelectorAll('.product-box');
      
      if (boxes.length === 0) {
        console.log('Không tìm thấy sản phẩm nào để tìm kiếm');
        return;
      }
      
      const keyword = searchInput.value.trim().toLowerCase();
      console.log('Đang tìm kiếm với từ khóa:', keyword);
      console.log('Tổng số sản phẩm:', boxes.length);
      
      let visibleCount = 0;
      
      boxes.forEach((box, index) => {
        // Lấy tên sản phẩm từ dataset hoặc từ text trong h3
        let productName = '';
        if (box.dataset.name) {
          productName = box.dataset.name.toLowerCase();
        } else {
          const nameEl = box.querySelector('h3');
          if (nameEl) {
            productName = nameEl.textContent.trim().toLowerCase();
          }
        }
        
        const productDesc = (box.dataset.desc || '').toLowerCase();
        
        // Tìm kiếm trong tên sản phẩm (ưu tiên) và mô tả
        const matches = !keyword || productName.includes(keyword) || productDesc.includes(keyword);
        
        if (matches) {
          box.style.display = '';
          visibleCount++;
          if (index < 3) { // Log 3 sản phẩm đầu tiên để debug
            console.log('Sản phẩm khớp:', productName);
          }
        } else {
          box.style.display = 'none';
        }
      });
      
      console.log('Số sản phẩm hiển thị:', visibleCount);
      
      // Hiển thị thông báo kết quả
      const messageEl = createResultMessage();
      if (keyword) {
        if (visibleCount === 0) {
          if (messageEl) {
            messageEl.textContent = `Không tìm thấy sản phẩm nào với từ khóa "${keyword}"`;
            messageEl.style.color = '#ffcccc';
            messageEl.style.display = 'block';
          }
          // Hiển thị thông báo trong product-scroll
          if (productScroll) {
            let noResultMsg = productScroll.querySelector('.no-search-result');
            if (!noResultMsg) {
              noResultMsg = document.createElement('p');
              noResultMsg.className = 'no-search-result';
              noResultMsg.style.cssText = 'color: white; padding: 40px 20px; text-align: center; font-size: 16px;';
              noResultMsg.textContent = 'Không tìm thấy sản phẩm nào phù hợp.';
              productScroll.appendChild(noResultMsg);
            }
          }
        } else {
          if (messageEl) {
            messageEl.textContent = `Tìm thấy ${visibleCount} sản phẩm với từ khóa "${keyword}"`;
            messageEl.style.color = 'white';
            messageEl.style.display = 'block';
          }
          // Xóa thông báo không tìm thấy
          if (productScroll) {
            const noResultMsg = productScroll.querySelector('.no-search-result');
            if (noResultMsg) {
              noResultMsg.remove();
            }
          }
        }
      } else {
        // Xóa tất cả thông báo khi không có từ khóa
        if (messageEl) {
          messageEl.style.display = 'none';
        }
        if (productScroll) {
          const noResultMsg = productScroll.querySelector('.no-search-result');
          if (noResultMsg) {
            noResultMsg.remove();
          }
        }
      }
    }
    
    // Nếu có tham số search từ URL, điền vào và filter ngay
    const urlParams = new URLSearchParams(window.location.search);
    const searchParam = urlParams.get('search');
    if (searchParam) {
      searchInput.value = searchParam;
      setTimeout(() => {
        filterProducts();
      }, 200);
    }
    
    // Lắng nghe sự kiện input (tìm kiếm real-time khi gõ)
    searchInput.addEventListener('input', function() {
      filterProducts();
    });
    
    // Lắng nghe sự kiện keydown để xử lý Enter
    searchInput.addEventListener('keydown', function(e) {
      if (e.key === 'Enter') {
        e.preventDefault();
        filterProducts();
        // Cuộn đến sản phẩm đầu tiên
        const boxes = document.querySelectorAll('.product-box');
        for (let box of boxes) {
          if (box.style.display !== 'none') {
            box.scrollIntoView({ behavior: 'smooth', block: 'center' });
            break;
          }
        }
      }
    });
    
    // Thêm nút X để xóa tìm kiếm
    const searchForm = searchInput.closest('.search-input');
    if (searchForm) {
      let clearBtn = searchForm.querySelector('.search-clear-btn');
      if (!clearBtn) {
        clearBtn = document.createElement('button');
        clearBtn.type = 'button';
        clearBtn.className = 'search-clear-btn';
        clearBtn.innerHTML = '×';
        clearBtn.style.cssText = 'background: none; border: none; color: #666; cursor: pointer; font-size: 20px; padding: 0 8px; display: none; line-height: 1;';
        clearBtn.title = 'Xóa tìm kiếm';
        clearBtn.addEventListener('click', function(e) {
          e.stopPropagation();
          searchInput.value = '';
          filterProducts();
          searchInput.focus();
        });
        searchForm.appendChild(clearBtn);
      }
      
      // Hiện/ẩn nút X
      function toggleClearBtn() {
        if (searchInput.value.trim()) {
          clearBtn.style.display = 'block';
        } else {
          clearBtn.style.display = 'none';
        }
      }
      
      searchInput.addEventListener('input', toggleClearBtn);
      toggleClearBtn();
    }
    
    console.log('Chức năng tìm kiếm đã được khởi tạo');
  }, 300);
});

// ====== XỬ LÝ YÊU THÍCH SẢN PHẨM ======
function toggleFavorite(btn, productId) {
  // Kiểm tra đăng nhập
  <?php if (!$userId || $userId <= 0): ?>
    alert('Vui lòng đăng nhập để sử dụng tính năng yêu thích.');
    return;
  <?php endif; ?>

  const formData = new FormData();
  formData.append('product_id', productId);

  fetch('toggle_favorite.php', {
    method: 'POST',
    body: formData
  })
  .then(response => response.json())
  .then(data => {
    if (data.status === 'success') {
      if (data.action === 'added') {
        // Thêm vào yêu thích
        btn.classList.add('active');
        btn.title = 'Bỏ yêu thích';
        
        // Hiệu ứng animation tim bay đến header
        createFlyingHeart(btn);
        
        // Hiệu ứng heartbeat
        btn.querySelector('.heart-icon').style.animation = 'none';
        setTimeout(() => {
          btn.querySelector('.heart-icon').style.animation = 'heartBeat 0.5s ease';
        }, 10);
      } else {
        // Bỏ yêu thích - nút tim biến mất
        btn.classList.add('removing');
        btn.title = 'Thêm vào yêu thích';
        
        // Sau khi animation xong, xóa class active
        setTimeout(() => {
          btn.classList.remove('active', 'removing');
        }, 300);
      }
    } else {
      alert(data.message || 'Có lỗi xảy ra. Vui lòng thử lại.');
    }
  })
  .catch(error => {
    console.error('Error:', error);
    alert('Có lỗi kết nối. Vui lòng thử lại.');
  });
}

// Tạo hiệu ứng tim bay từ sản phẩm đến icon yêu thích trong header
function createFlyingHeart(sourceBtn) {
  const headerFavoritesBtn = document.getElementById('header-favorites-btn');
  if (!headerFavoritesBtn) return;

  // Lấy vị trí của nút tim nguồn
  const sourceRect = sourceBtn.getBoundingClientRect();
  const sourceX = sourceRect.left + sourceRect.width / 2;
  const sourceY = sourceRect.top + sourceRect.height / 2;

  // Lấy vị trí của icon yêu thích trong header
  const targetRect = headerFavoritesBtn.getBoundingClientRect();
  const targetX = targetRect.left + targetRect.width / 2;
  const targetY = targetRect.top + targetRect.height / 2;

  // Tạo element tim bay
  const flyingHeart = document.createElement('div');
  flyingHeart.className = 'flying-heart';
  flyingHeart.innerHTML = '❤️';
  flyingHeart.style.left = sourceX + 'px';
  flyingHeart.style.top = sourceY + 'px';
  document.body.appendChild(flyingHeart);

  // Tính toán đường bay (có thể thêm đường cong)
  const deltaX = targetX - sourceX;
  const deltaY = targetY - sourceY;
  const distance = Math.sqrt(deltaX * deltaX + deltaY * deltaY);
  const duration = Math.min(1000, distance * 2); // Tối đa 1 giây

  // Animation
  requestAnimationFrame(() => {
    flyingHeart.style.transition = `all ${duration}ms cubic-bezier(0.25, 0.46, 0.45, 0.94)`;
    flyingHeart.style.left = targetX + 'px';
    flyingHeart.style.top = targetY + 'px';
    flyingHeart.style.transform = 'scale(0.3) rotate(360deg)';
    flyingHeart.style.opacity = '0';
  });

  // Xóa element sau khi animation xong
  setTimeout(() => {
    if (flyingHeart.parentNode) {
      flyingHeart.parentNode.removeChild(flyingHeart);
    }
    
    // Hiệu ứng rung nhẹ cho icon header
    headerFavoritesBtn.style.animation = 'none';
    setTimeout(() => {
      headerFavoritesBtn.style.animation = 'headerHeartPulse 0.5s ease';
    }, 10);
  }, duration);
}

// Thêm animation cho heart
const style = document.createElement('style');
style.textContent = `
  @keyframes heartBeat {
    0%, 100% { transform: scale(1); }
    25% { transform: scale(1.3); }
    50% { transform: scale(1.1); }
    75% { transform: scale(1.2); }
  }
  
  @keyframes headerHeartPulse {
    0%, 100% { transform: scale(1); }
    50% { transform: scale(1.3); }
  }
`;
document.head.appendChild(style);
</script>

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
    <a href="lienhe.php" style="color:white; text-decoration:none; margin:0 8px;">Liên hệ với chúng tôi</a> |
    <a href="dieukien.php" style="color:white; text-decoration:none; margin:0 8px;">Điều khoản và Điều kiện</a> |
    <a href="chinhsach.php" style="color:white; text-decoration:none; margin:0 8px;">Chính sách bảo mật</a>
  </div>
</footer>

<!-- ====== CHAT HỖ TRỢ KHÁCH HÀNG (BUTTON + BOX) ====== -->
<button class="chat-widget-toggle" id="chat-toggle-btn" title="Chat với hỗ trợ">
  <span>💬</span>
  <span class="chat-unread-badge" id="chat-unread-badge">0</span>
</button>

<div class="chat-widget-box" id="chat-box">
  <div class="chat-header">
    <div>
      <div class="title">Hỗ trợ Danisa</div>
      <div class="subtitle">Nhắn tin với nhân viên &amp; admin</div>
    </div>
    <button class="close-btn" id="chat-close-btn">&times;</button>
  </div>

  <div class="chat-status" id="chat-status">
    Đang kết nối...
  </div>

  <div class="chat-messages" id="chat-messages">
    <!-- Tin nhắn sẽ được thêm bằng JS -->
  </div>

  <div class="chat-input-area">
    <textarea id="chat-input" placeholder="Nhập nội dung cần hỗ trợ..." rows="1"></textarea>
    <button id="chat-send-btn">Gửi</button>
  </div>
</div>

</body>
</html>
