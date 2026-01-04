<?php
require 'config.php';

$pageTitle  = 'Quản lý đơn hàng';
$activePage = 'donhang';

// Lấy danh sách đơn hàng + 1 sản phẩm đại diện + tổng số lượng
$sql = "
    SELECT 
        d.*,
        COALESCE(SUM(ct.so_luong), 0) AS total_quantity,
        MIN(sp.name) AS first_product_name
    FROM donhang d
    LEFT JOIN donhang_chitiet ct ON d.id = ct.donhang_id
    LEFT JOIN sanpham sp ON ct.sanpham_id = sp.id
    GROUP BY d.id
    ORDER BY d.id DESC
";
$result = $conn->query($sql);
if (!$result) {
    die('Lỗi truy vấn: ' . $conn->error);
}

include 'header.php'; // header admin + sidebar
?>
<div class="table-header" style="margin-bottom:15px;">
  <h2 style="margin:0;">Danh sách đơn hàng</h2>
</div>

<table>
  <thead>
    <tr>
      <th>ID</th>
      <th>Khách hàng</th>
      <th>Sản phẩm</th>
      <th>Số lượng</th>
      <th>Tổng tiền</th>
      <th>Trạng thái</th>
      <th>Ngày tạo</th>
      <th>Hành động</th>
    </tr>
  </thead>
    <tbody>
    <?php if ($result->num_rows > 0): ?>
      <?php while ($row = $result->fetch_assoc()): ?>
        <?php
          $id       = (int)$row['id'];
          $customer = $row['ho_ten'];                 // cột trong donhang
          $product  = $row['first_product_name'];     // từ sanpham.name (nếu có join như mình gửi)
          $qty      = (int)$row['total_quantity'];    // SUM(so_luong) từ donhang_chitiet
          $total    = (float)$row['tong_tien'];       // cột trong donhang
          $status   = $row['trang_thai'];             // enum trong donhang
          $created  = $row['created_at'];

          switch ($status) {
              case 'pending':   $statusText = 'Chờ xử lý';   break;
              case 'paid':      $statusText = 'Đã thanh toán'; break;
              case 'shipping':  $statusText = 'Đang giao';   break;
              case 'completed': $statusText = 'Hoàn tất';    break;
              case 'cancelled': $statusText = 'Đã hủy';      break;
              default:          $statusText = $status ?: 'Không rõ';
          }

          if ($qty === 0) {
              $product = $product ?: 'Chưa có chi tiết';
          }
        ?>
        <tr>
          <td><?= $id ?></td>
          <td><?= htmlspecialchars($customer ?: '—') ?></td>
          <td><?= htmlspecialchars($product ?: '—') ?></td>
          <td><?= $qty ?></td>
          <td><?= number_format($total, 0, ',', '.') ?>₫</td>
          <td><?= htmlspecialchars($statusText) ?></td>
          <td><?= htmlspecialchars($created) ?></td>
          <td>
            <a href="donhang_view.php?id=<?= $id ?>" class="btn btn-small">Xem</a>
            <a href="donhang_delete.php?id=<?= $id ?>"
               class="btn btn-small btn-danger"
               onclick="return confirm('Xóa đơn hàng #<?= $id ?>?');">
               Xóa
            </a>
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
