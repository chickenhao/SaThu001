<?php
require 'config.php';

$pageTitle  = 'Quản lý đơn hàng';
$activePage = 'donhang';

// Lấy danh sách đơn hàng + 1 sản phẩm đại diện + tổng số lượng
$sql = "
    SELECT 
        d.id,
        d.ho_ten,
        d.tong_tien,
        d.trang_thai,
        d.created_at,
        COALESCE(SUM(ct.so_luong), 0) AS total_quantity,
        MIN(sp.name) AS first_product_name
    FROM donhang d
    LEFT JOIN donhang_chitiet ct ON d.id = ct.donhang_id
    LEFT JOIN sanpham sp ON ct.sanpham_id = sp.id
    GROUP BY d.id
    ORDER BY d.created_at DESC
";

$result = $conn->query($sql);
if (!$result) {
    die('Lỗi truy vấn: ' . $conn->error);
}

include 'header.php';
?>

<h2>Danh sách đơn hàng</h2>

<table>
  <thead>
    <tr>
      <th>Mã đơn</th>
      <th>Ngày tạo</th>
      <th>Khách hàng</th>
      <th>Sản phẩm</th>
      <th>Số lượng</th>
      <th>Tổng tiền</th>
      <th>Trạng thái</th>
      <th>Hành động</th>
    </tr>
  </thead>
  <tbody>
    <?php if ($result->num_rows > 0): ?>
      <?php while ($row = $result->fetch_assoc()): ?>
        <?php
          $id          = (int)$row['id'];
          $created     = $row['created_at'];
          $customer    = $row['ho_ten'] ?: '—';
          $productName = $row['first_product_name'] ?: 'Chưa có chi tiết';
          $qty         = (int)$row['total_quantity'];
          $total       = (float)$row['tong_tien'];
          $curStatus   = $row['trang_thai']; // giá trị hiện tại trong DB
        ?>
        <tr>
          <td><?= $id ?></td>
          <td><?= htmlspecialchars($created) ?></td>
          <td><?= htmlspecialchars($customer) ?></td>
          <td><?= htmlspecialchars($productName) ?></td>
          <td><?= $qty ?></td>
          <td><?= number_format($total, 0, ',', '.') ?>₫</td>
          <td>
            <form method="post" action="donhang_update_status.php" style="display:flex; gap:4px; align-items:center;">
              <input type="hidden" name="id" value="<?= $id ?>">
              <select name="trang_thai" style="padding:2px 4px; font-size:13px;">
                <option value="Chờ xử lý"   <?= ($curStatus == 'Chờ xử lý'   || $curStatus == 'pending')   ? 'selected' : '' ?>>Chờ xử lý</option>
                <option value="Đã thanh toán" <?= ($curStatus == 'Đã thanh toán' || $curStatus == 'paid')      ? 'selected' : '' ?>>Đã thanh toán</option>
                <option value="Đang giao"    <?= ($curStatus == 'Đang giao'    || $curStatus == 'shipping')  ? 'selected' : '' ?>>Đang giao</option>
                <option value="Hoàn tất"     <?= ($curStatus == 'Hoàn tất'     || $curStatus == 'completed') ? 'selected' : '' ?>>Hoàn tất</option>
                <option value="Đã hủy"       <?= ($curStatus == 'Đã hủy'       || $curStatus == 'cancelled') ? 'selected' : '' ?>>Đã hủy</option>
              </select>
              <button type="submit" class="btn btn-sm">Lưu</button>
            </form>
          </td>
          <td>
            <a href="donhang_view.php?id=<?= $id ?>" class="btn btn-sm">Xem</a>
            <!-- Nếu muốn xóa:
            <a href="donhang_delete.php?id=<?= $id ?>"
               class="btn btn-sm btn-danger"
               onclick="return confirm('Xóa đơn hàng #<?= $id ?>?');">
               Xóa
            </a>
            -->
          </td>
        </tr>
      <?php endwhile; ?>
    <?php else: ?>
      <tr>
        <td colspan="8" style="text-align:center;">Chưa có đơn hàng nào.</td>
      </tr>
    <?php endif; ?>
  </tbody>
</table>

<?php include 'footer.php'; ?>
