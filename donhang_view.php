<?php
session_start();
require 'config.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    die('Mã đơn hàng không hợp lệ.');
}

// Lấy thông tin đơn hàng
$stmt = $conn->prepare("SELECT * FROM donhang WHERE id = ?");
$stmt->bind_param('i', $id);
$stmt->execute();
$orderRes = $stmt->get_result();
$order = $orderRes->fetch_assoc();
$stmt->close();

if (!$order) {
    die('Không tìm thấy đơn hàng.');
}

function mapTrangThai($status) {
    switch ($status) {
        case 'pending':   return 'Chờ xử lý';
        case 'paid':      return 'Đã thanh toán';
        case 'shipping':  return 'Đang giao';
        case 'completed': return 'Hoàn tất';
        case 'cancelled': return 'Đã hủy';
        default:          return $status ?: 'Không rõ';
    }
}

// Lấy chi tiết đơn hàng
$stmt2 = $conn->prepare("
    SELECT 
        ct.*,
        sp.name  AS product_name,
        sp.image AS product_image
    FROM donhang_chitiet ct
    LEFT JOIN sanpham sp ON ct.sanpham_id = sp.id
    WHERE ct.donhang_id = ?
");
$stmt2->bind_param('i', $id);
$stmt2->execute();
$detailsRes = $stmt2->get_result();
$details = [];
while ($row = $detailsRes->fetch_assoc()) {
    $details[] = $row;
}
$stmt2->close();

// Lấy danh mục cho header menu
$categories = [];
$sqlCat = "SELECT * FROM danhmuc ORDER BY id ASC";
$resCat = $conn->query($sqlCat);
if ($resCat) {
    while ($row = $resCat->fetch_assoc()) {
        $categories[] = $row;
    }
}
?>
<!doctype html>
<html lang="vi">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>Chi tiết đơn hàng #<?= htmlspecialchars($order['id']) ?></title>
  <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="style.css">
  <link rel="stylesheet" href="trangchu.css">
  <style>
    body {
      font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
      background:#f9fafb;
      margin:0;
      padding:0;
      min-height: 100vh;
    }
    /* Đảm bảo header và dropdown hoạt động đúng */
    .site-header {
      position: relative;
      z-index: 100;
    }
    .dropdown-content {
      z-index: 1001 !important;
    }
    .order-content {
      padding: 20px;
      position: relative;
      z-index: 1;
    }
    .order-wrapper {
      max-width: 960px;
      margin:0 auto;
      background:#fff;
      border-radius:12px;
      box-shadow:0 10px 25px rgba(0,0,0,0.08);
      padding:20px 24px 24px;
    }
    h1 { margin-top:0; }
    .order-meta {
      display:grid;
      grid-template-columns: repeat(auto-fit,minmax(220px,1fr));
      gap:8px 24px;
      font-size:14px;
      margin-bottom:18px;
    }
    .order-meta strong { display:inline-block; min-width:110px; }
    table {
      width:100%;
      border-collapse: collapse;
      font-size:14px;
    }
    th, td {
      padding:8px 10px;
      border-bottom:1px solid #e5e7eb;
      text-align:left;
    }
    th {
      background:#f3f4f6;
      font-weight:600;
    }
    tfoot td {
      font-weight:600;
      text-align:right;
    }
    .btn-back {
      display:inline-block;
      margin-top:14px;
      padding:6px 12px;
      border-radius:999px;
      border:1px solid #d1d5db;
      background:#f9fafb;
      color:#111827;
      text-decoration:none;
      font-size:13px;
    }
    .btn-back:hover {
      background:#e5e7eb;
    }
    .product-img {
      width:50px;
      height:50px;
      object-fit:cover;
      border-radius:8px;
    }
  </style>
</head>
<body>
<?php include 'header_front.php'; ?>
<div class="order-content">
  <div class="order-wrapper">
    <h1>Chi tiết đơn hàng #<?= htmlspecialchars($order['id']) ?></h1>

    <div class="order-meta">
      <div><strong>Khách hàng:</strong> <?= htmlspecialchars($order['ho_ten'] ?: '—') ?></div>
      <div><strong>Số điện thoại:</strong> <?= htmlspecialchars($order['phone'] ?: '—') ?></div>
      <div><strong>Email:</strong> <?= htmlspecialchars($order['email'] ?: '—') ?></div>
      <div><strong>Trạng thái:</strong> <?= htmlspecialchars(mapTrangThai($order['trang_thai'])) ?></div>
      <div><strong>Thanh toán:</strong> <?= htmlspecialchars($order['phuong_thuc_thanh_toan'] ?: '—') ?></div>
      <div><strong>Ngày tạo:</strong> <?= htmlspecialchars($order['created_at']) ?></div>
      <div style="grid-column:1/-1;">
        <strong>Địa chỉ:</strong> <?= nl2br(htmlspecialchars($order['dia_chi'] ?: '—')) ?>
      </div>
      <?php if (!empty($order['ghi_chu'])): ?>
        <div style="grid-column:1/-1;">
          <strong>Ghi chú:</strong> <?= nl2br(htmlspecialchars($order['ghi_chu'])) ?>
        </div>
      <?php endif; ?>
    </div>

    <h3>Sản phẩm trong đơn</h3>
    <table>
      <thead>
        <tr>
          <th>Ảnh</th>
          <th>Sản phẩm</th>
          <th>Số lượng</th>
          <th>Đơn giá</th>
          <th>Thành tiền</th>
        </tr>
      </thead>
      <tbody>
        <?php if (count($details) === 0): ?>
          <tr>
            <td colspan="5" style="text-align:center;">Chưa có chi tiết đơn hàng.</td>
          </tr>
        <?php else: ?>
          <?php foreach ($details as $row): ?>
            <tr>
              <td>
                <?php if (!empty($row['product_image'])): ?>
                  <img src="<?= htmlspecialchars($row['product_image']) ?>" class="product-img" alt="">
                <?php endif; ?>
              </td>
              <td><?= htmlspecialchars($row['product_name'] ?: '—') ?></td>
              <td><?= (int)$row['so_luong'] ?></td>
              <td><?= number_format($row['don_gia'], 0, ',', '.') ?>₫</td>
              <td><?= number_format($row['thanh_tien'], 0, ',', '.') ?>₫</td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
      <tfoot>
        <tr>
          <td colspan="4">Tổng tiền đơn hàng</td>
          <td><?= number_format($order['tong_tien'], 0, ',', '.') ?>₫</td>
        </tr>
      </tfoot>
    </table>

    <a href="donhang.php" class="btn-back">← Quay về trang lịch sử đơn hàng</a>
  </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function() {
  // Xử lý dropdown menu - thêm class "show" khi hover
  const dropdowns = document.querySelectorAll(".dropdown");
  dropdowns.forEach(drop => {
    const menu = drop.querySelector(".dropdown-content");
    if (!menu) return;

    // Rê chuột vào thì mở menu
    drop.addEventListener("mouseenter", function () {
      // Tắt các dropdown khác
      document.querySelectorAll(".dropdown-content.show").forEach(dc => {
        if (dc !== menu) dc.classList.remove("show");
      });
      menu.classList.add("show");
    });

    // Rê chuột ra ngoài thì đóng menu
    drop.addEventListener("mouseleave", function () {
      menu.classList.remove("show");
    });
  });

  // Đảm bảo các link trong dropdown có thể click được
  const dropdownLinks = document.querySelectorAll(".dropdown-content a");
  dropdownLinks.forEach(link => {
    link.addEventListener("click", function(e) {
      // Cho phép click bình thường
      e.stopPropagation();
    });
  });
});
</script>

<footer style="
  background:#111827;
  color:white;
  text-align:center;
  padding: 20px 0;
  font-size:12px;
  line-height:1.8;
  font-weight:500;
  margin-top: 40px;
">
  <div class="linklien">
    <a href="#" style="color:white; text-decoration:none; margin:0 8px;">Đã được bảo lưu mọi quyền</a> |
    <a href="dieukien.php" style="color:white; text-decoration:none; margin:0 8px;">Điều khoản và Điều kiện</a> |
    <a href="chinhsach.php" style="color:white; text-decoration:none; margin:0 8px;">Chính sách bảo mật</a>
  </div>
</footer>
</body>
</html>
