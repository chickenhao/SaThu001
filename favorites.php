<?php
session_start();
require 'config.php';

// Lấy user_id từ session
$userId = null;
if (isset($_SESSION['user_id'])) {
    $userId = (int)$_SESSION['user_id'];
} elseif (isset($_SESSION['currentUser']['id'])) {
    $userId = (int)$_SESSION['currentUser']['id'];
} elseif (isset($_SESSION['currentUser']['user_id'])) {
    $userId = (int)$_SESSION['currentUser']['user_id'];
}

// Kiểm tra đăng nhập
if (!$userId || $userId <= 0) {
    header('Location: dangnhap.php?redirect=favorites.php');
    exit;
}

// Lấy danh sách sản phẩm yêu thích
$favoriteProducts = [];
$stmt = $conn->prepare("
    SELECT sp.id, sp.name, sp.price, sp.image, sp.description, sp.status
    FROM favorites f
    INNER JOIN sanpham sp ON f.product_id = sp.id
    WHERE f.user_id = ?
    ORDER BY f.created_at DESC
");
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    if (!empty($row['image'])) {
        $row['image'] = normalizeImagePath($row['image']);
    }
    $favoriteProducts[] = $row;
}
$stmt->close();

// Lấy danh sách product_id đã yêu thích để hiển thị trạng thái
$favoriteProductIds = [];
foreach ($favoriteProducts as $fp) {
    $favoriteProductIds[] = $fp['id'];
}
?>
<!doctype html>
<html lang="vi">
<head>
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width,initial-scale=1" />
<title>Sản phẩm yêu thích - Danisa</title>

<link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="style.css">
<link rel="stylesheet" href="trangchu.css">
<link rel="stylesheet" href="banhquybo.css">

<style>
body {
  margin: 0;
  font-family: 'Cormorant Garamond', serif;
  /* HÌNH NỀN CHUNG CHO TRANG SẢN PHẨM YÊU THÍCH - ĐỒNG BỘ VỚI GIỎ HÀNG */
  background: url('image/anhnen3.jpg') center/cover no-repeat fixed;
  min-height: 100vh;
}

.favorites-container {
  max-width: 1200px;
  margin: 40px auto;
  padding: 30px;
  background: rgba(255, 255, 255, 0.95);
  border-radius: 16px;
  box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
}

.favorites-header {
  text-align: center;
  margin-bottom: 40px;
}

.favorites-header h1 {
  font-size: 2.5em;
  color: #333;
  margin-bottom: 10px;
}

.favorites-count {
  color: #666;
  font-size: 1.1em;
}

.favorites-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
  gap: 30px;
  margin-top: 30px;
}

.favorite-product-card {
  background: white;
  border-radius: 12px;
  overflow: hidden;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
  transition: transform 0.3s ease, box-shadow 0.3s ease;
  position: relative;
}

.favorite-product-card:hover {
  transform: translateY(-5px);
  box-shadow: 0 8px 20px rgba(0, 0, 0, 0.15);
}

.favorite-product-card .product-image {
  width: 100%;
  height: 250px;
  object-fit: cover;
  background: #f5f5f5;
}

.favorite-product-card .product-info {
  padding: 20px;
}

.favorite-product-card .product-name {
  font-size: 1.2em;
  font-weight: 600;
  color: #333;
  margin-bottom: 10px;
  min-height: 50px;
}

.favorite-product-card .product-price {
  font-size: 1.3em;
  color: #d32f2f;
  font-weight: 700;
  margin-bottom: 15px;
}

.favorite-product-card .product-actions {
  display: flex;
  gap: 10px;
  margin-top: 15px;
}

.favorite-product-card .btn {
  flex: 1;
  padding: 10px 20px;
  border: none;
  border-radius: 6px;
  cursor: pointer;
  font-size: 0.95em;
  transition: all 0.3s ease;
}

.favorite-product-card .btn-order {
  background: #d32f2f;
  color: white;
}

.favorite-product-card .btn-order:hover {
  background: #b71c1c;
}

.favorite-product-card .btn-cart {
  background: #f5f5f5;
  color: #333;
  border: 1px solid #ddd;
}

.favorite-product-card .btn-cart:hover {
  background: #e0e0e0;
}

.favorite-product-card .favorite-btn {
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
}

.favorite-product-card .favorite-btn.active {
  background: rgba(255, 255, 255, 1);
}

.favorite-product-card .favorite-btn .heart-icon {
  font-size: 20px;
}

.empty-favorites {
  text-align: center;
  padding: 60px 20px;
  color: #666;
}

.empty-favorites-icon {
  font-size: 80px;
  margin-bottom: 20px;
}

.empty-favorites h2 {
  font-size: 1.8em;
  margin-bottom: 10px;
  color: #333;
}

.empty-favorites p {
  font-size: 1.1em;
  margin-bottom: 30px;
}

.empty-favorites .btn-browse {
  display: inline-block;
  padding: 12px 30px;
  background: #d32f2f;
  color: white;
  text-decoration: none;
  border-radius: 6px;
  transition: background 0.3s ease;
}

.empty-favorites .btn-browse:hover {
  background: #b71c1c;
}
</style>
</head>
<body>

<?php include 'header_front.php'; ?>

<div class="favorites-container">
  <div class="favorites-header">
    <h1>❤️ Sản phẩm yêu thích</h1>
    <p class="favorites-count">
      Bạn có <?= count($favoriteProducts); ?> sản phẩm yêu thích
    </p>
  </div>

  <?php if (empty($favoriteProducts)): ?>
    <div class="empty-favorites">
      <div class="empty-favorites-icon">💔</div>
      <h2>Chưa có sản phẩm yêu thích</h2>
      <p>Hãy khám phá và thêm các sản phẩm bạn yêu thích vào danh sách này!</p>
      <a href="sanpham_danhmuc.php" class="btn-browse">Xem sản phẩm</a>
    </div>
  <?php else: ?>
    <div class="favorites-grid">
      <?php foreach ($favoriteProducts as $product): ?>
        <div class="favorite-product-card">
          <button class="favorite-btn active" 
                  data-product-id="<?= htmlspecialchars($product['id']); ?>"
                  onclick="toggleFavorite(this, '<?= htmlspecialchars($product['id']); ?>')"
                  title="Bỏ yêu thích">
            <span class="heart-icon">❤️</span>
          </button>
          
          <?php if (!empty($product['image'])): ?>
            <img src="<?= htmlspecialchars($product['image']); ?>" 
                 alt="<?= htmlspecialchars($product['name']); ?>" 
                 class="product-image"
                 onerror="this.src='image/anhthu1.png'">
          <?php else: ?>
            <img src="image/anhthu1.png" 
                 alt="<?= htmlspecialchars($product['name']); ?>" 
                 class="product-image">
          <?php endif; ?>
          
          <div class="product-info">
            <h3 class="product-name"><?= htmlspecialchars($product['name']); ?></h3>
            <div class="product-price">
              <?= number_format((int)$product['price'], 0, ',', '.'); ?>đ
            </div>
            <div class="product-actions">
              <button class="btn btn-order" 
                      onclick="addToCart('<?= htmlspecialchars($product['id']); ?>', '<?= htmlspecialchars(addslashes($product['name'])); ?>', <?= (int)$product['price']; ?>, '<?= htmlspecialchars($product['image']); ?>', 'buy_now')">
                Đặt hàng
              </button>
              <button class="btn btn-cart"
                      onclick="addToCart('<?= htmlspecialchars($product['id']); ?>', '<?= htmlspecialchars(addslashes($product['name'])); ?>', <?= (int)$product['price']; ?>, '<?= htmlspecialchars($product['image']); ?>', 'add')">
                Thêm vào giỏ
              </button>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<script>
// Function toggle favorite (tương tự như trong sanpham_danhmuc.php)
function toggleFavorite(btn, productId) {
  const formData = new FormData();
  formData.append('product_id', productId);

  fetch('toggle_favorite.php', {
    method: 'POST',
    body: formData
  })
  .then(response => response.json())
  .then(data => {
    if (data.status === 'success') {
      if (data.action === 'removed') {
        // Xóa card khỏi DOM
        btn.closest('.favorite-product-card').style.animation = 'fadeOut 0.3s ease';
        setTimeout(() => {
          btn.closest('.favorite-product-card').remove();
          // Cập nhật số lượng
          const count = document.querySelectorAll('.favorite-product-card').length;
          document.querySelector('.favorites-count').textContent = 
            `Bạn có ${count} sản phẩm yêu thích`;
          
          // Nếu hết sản phẩm, hiển thị empty state
          if (count === 0) {
            location.reload();
          }
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

// Function add to cart - tương tự như trong sanpham_danhmuc.php
function addToCart(productId, productName, price, image, action) {
  // action: 'add' hoặc 'buy_now'
  action = action || 'add';
  
  const params = new URLSearchParams();
  params.set('action', action);
  params.set('id', productId);
  params.set('name', productName);
  params.set('price', price);
  params.set('image', image);
  params.set('size', '200g'); // Mặc định size

  if (action === 'add') {
    // Thêm vào giỏ: request ngầm để PHP cập nhật session, KHÔNG rời trang
    fetch('giohang.php?' + params.toString(), { method: 'GET' })
      .then(() => {
        // Hiển thị thông báo
        alert('Đã thêm ' + productName + ' vào giỏ hàng!');
      })
      .catch(err => {
        console.error('Lỗi thêm giỏ hàng:', err);
        alert('Có lỗi xảy ra khi thêm vào giỏ hàng.');
      });
  } else if (action === 'buy_now') {
    // Đặt hàng: chuyển hướng sang giohang.php -> thanhtoan.php
    window.location.href = 'giohang.php?' + params.toString();
  }
}

// Thêm animation fadeOut
const style = document.createElement('style');
style.textContent = `
  @keyframes fadeOut {
    from { opacity: 1; transform: scale(1); }
    to { opacity: 0; transform: scale(0.9); }
  }
`;
document.head.appendChild(style);
</script>

<?php include 'footer.php'; ?>

</body>
</html>

