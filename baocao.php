<?php
require 'config.php';

$pageTitle  = 'Báo cáo';
$activePage = 'baocao';

/* ====== TỔNG QUAN ====== */

// Tổng số đơn hàng
$res_orders = $conn->query("
    SELECT COUNT(*) AS total_order 
    FROM donhang
");
$orders_total = $res_orders ? (int)($res_orders->fetch_assoc()['total_order'] ?? 0) : 0;

// Tổng doanh thu (dùng cột tong_tien trong donhang)
$res_revenue = $conn->query("
    SELECT SUM(tong_tien) AS total_revenue 
    FROM donhang
");
$row_rev      = $res_revenue ? $res_revenue->fetch_assoc() : ['total_revenue' => 0];
$revenue_total = (float)($row_rev['total_revenue'] ?? 0);

// Tổng số sản phẩm đã bán (từ bảng donhang_chitiet, cột so_luong)
$res_items_sold = $conn->query("
    SELECT SUM(so_luong) AS items_sold 
    FROM donhang_chitiet
");
$row_items  = $res_items_sold ? $res_items_sold->fetch_assoc() : ['items_sold' => 0];
$items_sold = (int)($row_items['items_sold'] ?? 0);

/* ====== ĐƠN HÀNG THEO TRẠNG THÁI ====== */
/*
 * Bảng donhang trong code admin của bạn có các cột:
 * - tong_tien
 * - trang_thai
 * - created_at
 * nên ở đây dùng đúng: trang_thai, tong_tien
 */
$res_status = $conn->query("
    SELECT 
        trang_thai AS status, 
        COUNT(*) AS so_don, 
        SUM(tong_tien) AS doanh_thu
    FROM donhang
    GROUP BY trang_thai
");

// Map trạng thái để hiển thị tiếng Việt đẹp hơn
$statusLabel = [
    'pending'   => 'Chờ xử lý',
    'paid'      => 'Đã thanh toán',
    'shipping'  => 'Đang giao',
    'completed' => 'Hoàn tất',
    'cancelled' => 'Đã hủy',
];

/* ====== TOP 5 SẢN PHẨM BÁN CHẠY ====== */
/*
 * Dùng bảng donhang_chitiet (so_luong, sanpham_id)
 * join với bảng sanpham (id, name, price)
 * Do không có cột total chi tiết, ta tính doanh thu = so_luong * price
 */
$res_top_products = $conn->query("
    SELECT 
        sp.id   AS product_id,
        sp.name AS product_name,
        SUM(ct.so_luong) AS tong_so_luong,
        SUM(ct.so_luong * sp.price) AS tong_doanh_thu
    FROM donhang_chitiet ct
    JOIN sanpham sp ON ct.sanpham_id = sp.id
    GROUP BY sp.id, sp.name
    ORDER BY tong_so_luong DESC
    LIMIT 5
");

include 'header.php';
?>

<h2>Tổng quan</h2>
<section class="cards">
  <div class="card">
    <h3>Tổng doanh thu</h3>
    <p><?= number_format($revenue_total, 0, ',', '.') ?>₫</p>
  </div>
  <div class="card">
    <h3>Tổng số đơn hàng</h3>
    <p><?= number_format($orders_total, 0, ',', '.') ?> đơn</p>
  </div>
  <div class="card">
    <h3>Tổng số sản phẩm đã bán</h3>
    <p><?= number_format($items_sold, 0, ',', '.') ?> sản phẩm</p>
  </div>
</section>

<h2>Đơn hàng theo trạng thái</h2>
<table>
  <thead>
    <tr>
      <th>Trạng thái</th>
      <th>Số đơn</th>
      <th>Doanh thu</th>
    </tr>
  </thead>
  <tbody>
    <?php if ($res_status && $res_status->num_rows > 0): ?>
      <?php while ($row = $res_status->fetch_assoc()): ?>
        <?php 
          $statusKey = $row['status'] ?? '';
          $label     = $statusLabel[$statusKey] ?? ($statusKey ?: '—');
        ?>
        <tr>
          <td><?= htmlspecialchars($label) ?></td>
          <td><?= (int)$row['so_don'] ?></td>
          <td><?= number_format((float)$row['doanh_thu'], 0, ',', '.') ?>₫</td>
        </tr>
      <?php endwhile; ?>
    <?php else: ?>
      <tr>
        <td colspan="3" style="text-align:center;">Chưa có dữ liệu đơn hàng.</td>
      </tr>
    <?php endif; ?>
  </tbody>
</table>

<h2>Top 5 sản phẩm bán chạy</h2>
<table>
  <thead>
    <tr>
      <th>ID sản phẩm</th>
      <th>Tên sản phẩm</th>
      <th>Tổng số lượng bán</th>
      <th>Tổng doanh thu</th>
    </tr>
  </thead>
  <tbody>
    <?php if ($res_top_products && $res_top_products->num_rows > 0): ?>
      <?php while ($row = $res_top_products->fetch_assoc()): ?>
        <tr>
          <td><?= htmlspecialchars($row['product_id']) ?></td>
          <td><?= htmlspecialchars($row['product_name']) ?></td>
          <td><?= (int)$row['tong_so_luong'] ?></td>
          <td><?= number_format((float)$row['tong_doanh_thu'], 0, ',', '.') ?>₫</td>
        </tr>
      <?php endwhile; ?>
    <?php else: ?>
      <tr>
        <td colspan="4" style="text-align:center;">Chưa có dữ liệu sản phẩm bán ra.</td>
      </tr>
    <?php endif; ?>
  </tbody>
</table>

<?php include 'footer.php'; ?>
