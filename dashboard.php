<?php
require 'config.php';

$pageTitle  = 'Dashboard';
$activePage = 'dashboard';

// Lấy thống kê
$res_products = $conn->query("SELECT COUNT(*) AS total FROM sanpham");
$res_orders   = $conn->query("SELECT COUNT(*) AS total FROM donhang");
$res_revenue  = $conn->query("SELECT SUM(tong_tien) AS revenue FROM donhang"); // SỬA Ở ĐÂY

$products_total = $res_products ? $res_products->fetch_assoc()['total']   : 0;
$orders_total   = $res_orders   ? $res_orders->fetch_assoc()['total']     : 0;

if ($res_revenue) {
    $row = $res_revenue->fetch_assoc();
    $revenue_total = $row['revenue'] ?? 0;
} else {
    $revenue_total = 0;
}

include 'header.php';
?>

<section class="cards">
  <div class="card">
    <h3>Sản phẩm</h3>
    <p><?= number_format($products_total, 0, ',', '.') ?> sản phẩm</p>
  </div>
  <div class="card">
    <h3>Đơn hàng</h3>
    <p><?= number_format($orders_total, 0, ',', '.') ?> đơn hàng</p>
  </div>
  <div class="card">
    <h3>Doanh thu</h3>
    <p><?= number_format($revenue_total, 0, ',', '.') ?>₫</p>
  </div>
</section>

<?php include 'footer.php'; ?>
