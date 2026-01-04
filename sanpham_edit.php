<?php
require 'config.php';

// ID sản phẩm trong bảng sanpham là varchar (ví dụ: B123, SP001)
// nên chỉ cần kiểm tra không rỗng, KHÔNG dùng is_numeric
if (!isset($_GET['id']) || $_GET['id'] === '') {
    die('ID sản phẩm không hợp lệ');
}

// Giữ nguyên ID dạng chuỗi
$id = trim($_GET['id']);

// Lấy thông tin sản phẩm theo ID chuỗi
$stmt = $conn->prepare("SELECT * FROM sanpham WHERE id = ?");
$stmt->bind_param("s", $id);
$stmt->execute();
$result = $stmt->get_result();
$product = $result->fetch_assoc();

if (!$product) {
    die('Không tìm thấy sản phẩm');
}

$pageTitle  = 'Chỉnh sửa sản phẩm';
$activePage = 'sanpham';

include 'header.php';
?>

<h2>Chỉnh sửa sản phẩm</h2>

<form class="add-product-form"
      enctype="multipart/form-data"
      method="POST"
      action="sanpham_update.php">

  <!-- ID sản phẩm (ẩn) -->
  <input type="hidden" name="id" value="<?= $product['id'] ?>">

  <div class="form-group">
    <label>Tên sản phẩm</label>
    <input type="text" name="name" required
           value="<?= htmlspecialchars($product['name']) ?>">
  </div>

  <div class="form-group">
    <label>Danh mục</label>
    <select name="category">
      <option value="banh" <?= $product['category']=='banh'?'selected':''; ?>>Bánh</option>
      <option value="keo"  <?= $product['category']=='keo'?'selected':''; ?>>Kẹo</option>
      <option value="khac" <?= $product['category']=='khac'?'selected':''; ?>>Khác</option>
    </select>
  </div>

  <div class="form-group">
    <label>Giá sản phẩm</label>
    <input type="number" name="price" required
           value="<?= (float)$product['price'] ?>">
  </div>

  <div class="form-group">
    <label>Số lượng</label>
    <input type="number" name="quantity" required
           value="<?= (int)$product['quantity'] ?>">
  </div>

  <div class="form-group">
    <label>Ảnh sản phẩm hiện tại</label><br>
    <?php if (!empty($product['image'])): ?>
      <img src="<?= htmlspecialchars($product['image']) ?>" style="width:100px; border-radius:6px;">
    <?php else: ?>
      <em>Chưa có ảnh</em>
    <?php endif; ?>
  </div>

  <div class="form-group">
    <label>Đổi ảnh sản phẩm (nếu muốn)</label>
    <input type="file" name="image" accept="image/*">
    <!-- Lưu đường dẫn ảnh cũ để dùng lại nếu không upload mới -->
    <input type="hidden" name="old_image" value="<?= htmlspecialchars($product['image']) ?>">
  </div>

  <div class="form-group">
    <label>Mô tả</label>
    <textarea name="description"><?= htmlspecialchars($product['description']) ?></textarea>
  </div>

  <button type="submit">Lưu thay đổi</button>
  <a href="sanpham_list.php" class="btn">Quay lại</a>
</form>

<?php include 'footer.php'; ?>
