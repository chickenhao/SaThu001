<?php
require 'config.php';

$pageTitle  = 'Thêm sản phẩm';
$activePage = 'sanpham';

// Lấy danh sách danh mục có sẵn
$categories = [];
$res = $conn->query("SELECT id, name FROM danhmuc ORDER BY name ASC");
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $categories[] = $row;
    }
}

include 'header.php';
?>

<h2>Thêm Sản Phẩm</h2>

<form class="add-product-form"
      enctype="multipart/form-data"
      method="POST"
      action="sanpham_store.php">

  <div class="form-group">
    <label>Tên sản phẩm</label>
    <input type="text" name="name" required>
  </div>

  <!-- DANH MỤC CÓ SẴN -->
  <div class="form-group">
    <label>Danh mục có sẵn</label>
    <select name="category_id" id="category-select">
      <?php foreach ($categories as $cat): ?>
        <option value="<?= $cat['id'] ?>">
          <?= htmlspecialchars($cat['name']) ?>
        </option>
      <?php endforeach; ?>
    </select>
  </div>

  <!-- THÊM DANH MỤC MỚI -->
  <div class="form-group">
    <label>Thêm danh mục mới</label>
    <div style="display:flex; gap:8px; align-items:center;">
      <!-- trường này sẽ gửi lên server để lưu vào DB -->
      <input type="text"
             name="new_category"
             id="new-category-input"
             placeholder="Nhập tên danh mục mới (nếu muốn)">
      <button type="button" class="btn btn-small" id="btn-add-category">
        Thêm danh mục
      </button>
    </div>
    <small>Nếu không nhập danh mục mới, sản phẩm sẽ dùng danh mục đã chọn ở trên.</small>
  </div>

  <div class="form-group">
    <label>Giá sản phẩm</label>
    <input type="number" name="price" required>
  </div>

  <div class="form-group">
    <label>Số lượng</label>
    <input type="number" name="quantity" required>
  </div>

  <div class="form-group">
    <label>Trạng thái</label>
    <select name="status">
      <option value="còn hàng">Còn hàng</option>
      <option value="hết hàng">Hết hàng</option>
    </select>
  </div>

  <div class="form-group">
    <label>Ảnh sản phẩm</label>
    <input type="file" name="image" accept="image/*">
  </div>

  <div class="form-group">
    <label>Mô tả</label>
    <textarea name="description"></textarea>
  </div>

  <button type="submit">Thêm sản phẩm</button>
  <a href="sanpham_list.php" class="btn">Quay lại</a>
</form>

<script>
const categorySelect   = document.getElementById('category-select');
const newCategoryInput = document.getElementById('new-category-input');
const btnAddCategory   = document.getElementById('btn-add-category');

btnAddCategory.addEventListener('click', function () {
  const name = newCategoryInput.value.trim();
  if (!name) {
    alert('Vui lòng nhập tên danh mục mới');
    return;
  }

  // Tạo option mới cho select (chỉ dùng cho hiển thị UI)
  const opt = document.createElement('option');
  // value này chỉ mang tính tạm, vì khi submit server sẽ dựa vào new_category
  opt.value = 'temp_' + Date.now();
  opt.textContent = name;

  // Thêm vào list và chọn luôn
  categorySelect.appendChild(opt);
  categorySelect.value = opt.value;

  // Thông báo nhẹ cho user
  alert('Đã thêm tạm danh mục "' + name + '" vào danh sách.\nKhi bạn bấm "Thêm sản phẩm", danh mục này sẽ được lưu vào hệ thống.');
});
</script>

<?php include 'footer.php'; ?>
  