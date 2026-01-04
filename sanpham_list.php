<?php
require 'config.php';

$pageTitle  = 'Quản lý đơn hàng';
$activePage = 'donhang';

// Lấy dữ liệu đơn hàng
$sql    = "SELECT * FROM donhang ORDER BY id DESC";
$result = $conn->query($sql);

if (!$result) {
    die("Lỗi truy vấn dữ liệu: " . $conn->error);
}

include 'header.php';
?>

<div style="margin-bottom: 15px;">
  <!-- Nếu bạn có trang thêm đơn tay thì dùng link; còn không có thể bỏ nút này -->
  <!-- <a href="donhang_add.php" class="btn">Thêm đơn hàng</a> -->
</div>

<table>
  <thead>
    <tr>
      <th>Mã đơn</th>
      <th>Họ tên</th>
      <th>Số điện thoại</th>
      <th>Email</th>
      <th>Địa chỉ</th>
      <th>Tổng tiền</th>
      <th>Trạng thái</th>
      <th>Thanh toán</th>
      <th>Ngày tạo</th>
      <th>Hành động</th>
    </tr>
  </thead>
  <tbody>
    <?php if ($result->num_rows > 0): ?>
      <?php while ($row = $result->fetch_assoc()): ?>
        <tr>
          <td><?= (int)$row['id'] ?></td>
          <td><?= htmlspecialchars($row['ho_ten'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
          <td><?= htmlspecialchars($row['phone'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
          <td><?= htmlspecialchars($row['email'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
          <td><?= htmlspecialchars($row['dia_chi'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
          <td><?= number_format((float)($row['tong_tien'] ?? 0), 0, ',', '.') ?>₫</td>
          <td><?= htmlspecialchars($row['trang_thai'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
          <td><?= htmlspecialchars($row['phuong_thuc_thanh_toan'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
          <td>
            <?php
              // Hiển thị created_at đẹp hơn nếu có
              if (!empty($row['created_at'])) {
                  $time = strtotime($row['created_at']);
                  echo date('d/m/Y H:i', $time);
              }
            ?>
          </td>
          <td>
            <!-- Tùy bạn có các trang chi tiết / sửa / xóa hay không -->
            <!-- Ví dụ: xem chi tiết đơn -->
            <!-- <a href="donhang_view.php?id=<?= $row['id'] ?>" class="btn btn-small">Xem</a> -->

            <!-- Nếu muốn cho phép xóa đơn -->
            <!--
            <a href="donhang_delete.php?id=<?= $row['id'] ?>"
               class="btn btn-small btn-danger"
               onclick="return confirm('Bạn có chắc muốn xóa đơn hàng này?');">
               Xóa
            </a>
            -->
          </td>
        </tr>
      <?php endwhile; ?>
    <?php else: ?>
      <tr>
        <td colspan="10" style="text-align:center;">Chưa có đơn hàng nào.</td>
      </tr>
    <?php endif; ?>
  </tbody>
</table>

<?php include 'footer.php'; ?>
