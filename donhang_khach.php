<?php
session_start();
require 'config.php';

// Lấy user hiện tại (nếu có đăng nhập)
$currentUser = isset($_SESSION['currentUser']) ? $_SESSION['currentUser'] : null;
$currentName = '';
if ($currentUser) {
    // Tùy bạn lưu gì trong session, mình thử lấy username hoặc name
    $currentName = $currentUser['username'] ?? ($currentUser['name'] ?? '');
}

// Lọc theo trạng thái
$filter = isset($_GET['trangthai']) ? $_GET['trangthai'] : 'all';

// Map trạng thái hiển thị
$statusTabs = [
    'all'          => 'Tất cả',
    'cho_xac_nhan' => 'Chờ xác nhận',    // tương ứng status = 'Chờ xử lý' / 'Chờ xác nhận'
    'dang_chuan_bi'=> 'Đang chuẩn bị',
    'dang_giao'    => 'Đang giao',
    'da_giao'      => 'Đã giao',
    'da_huy'       => 'Đã hủy',
];

// Lấy đơn từ DB
$params = [];
$sql = "SELECT * FROM donhang WHERE 1=1";

if ($currentName !== '') {
    $sql .= " AND customer_name = ?";
    $params[] = $currentName;
}

// áp dụng filter trạng thái
switch ($filter) {
    case 'cho_xac_nhan':
        // tùy bạn đặt status trong DB là 'Chờ xử lý' hay 'Chờ xác nhận'
        $sql .= " AND (status = 'Chờ xử lý' OR status = 'Chờ xác nhận')";
        break;
    case 'dang_chuan_bi':
        $sql .= " AND status = 'Đang chuẩn bị'";
        break;
    case 'dang_giao':
        $sql .= " AND status = 'Đang giao'";
        break;
    case 'da_giao':
        $sql .= " AND status = 'Đã giao'";
        break;
    case 'da_huy':
        $sql .= " AND status = 'Đã hủy'";
        break;
    default:
        // all: không thêm điều kiện
        break;
}

$sql .= " ORDER BY id DESC";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    // chỉ có 1 tham số tên (s), nếu sau này có thêm thì chỉnh lại chuỗi 's'
    $stmt->bind_param(str_repeat('s', count($params)), ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

$orders = [];
while ($row = $result->fetch_assoc()) {
    $orders[] = $row;
}
$stmt->close();

// hàm render badge trạng thái
function renderStatusBadge($s) {
    $s = trim($s);
    $class = 'status-badge status-default';
    if (stripos($s, 'chờ') === 0)        $class = 'status-badge status-pending';
    if (stripos($s, 'đang chuẩn') === 0) $class = 'status-badge status-processing';
    if (stripos($s, 'đang giao') === 0)  $class = 'status-badge status-shipping';
    if (stripos($s, 'đã giao') === 0)    $class = 'status-badge status-done';
    if (stripos($s, 'hủy') !== false)    $class = 'status-badge status-cancel';

    return '<span class="'.$class.'">'.htmlspecialchars($s).'</span>';
}
?>
<!doctype html>
<html lang="vi">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>Đơn hàng của tôi - Danisa</title>

  <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="trangchu.css">

  <style>
    body {
      background:#f3f4f6;
      font-family: "Cormorant Garamond", serif;
    }
    .orders-page {
      max-width: 1100px;
      margin: 30px auto 60px;
      background:#fff;
      border-radius:16px;
      box-shadow:0 10px 40px rgba(0,0,0,0.08);
      padding:20px 24px 26px;
    }
    .orders-header {
      display:flex;
      justify-content:space-between;
      align-items:flex-end;
      flex-wrap:wrap;
      gap:10px;
      margin-bottom:14px;
    }
    .orders-header h1 {
      margin:0;
      font-size:26px;
      text-transform:uppercase;
    }
    .orders-header .sub {
      font-size:14px;
      color:#6b7280;
    }
    .status-tabs {
      display:flex;
      flex-wrap:wrap;
      gap:8px;
      margin-bottom:16px;
    }
    .status-tab {
      padding:6px 14px;
      border-radius:999px;
      border:1px solid #e5e7eb;
      background:#f9fafb;
      font-size:13px;
      cursor:pointer;
      text-decoration:none;
      color:#111827;
      transition:all .15s;
    }
    .status-tab.active {
      background:#1d4ed8;
      color:#fff;
      border-color:#1d4ed8;
      font-weight:600;
    }
    .status-tab:hover {
      background:#e5e7eb;
    }
    table.orders-table {
      width:100%;
      border-collapse:collapse;
      font-size:14px;
    }
    table.orders-table th,
    table.orders-table td {
      padding:9px 8px;
      border-bottom:1px solid #e5e7eb;
      text-align:left;
    }
    table.orders-table th {
      background:#f9fafb;
      font-weight:600;
    }
    .text-right {
      text-align:right;
      white-space:nowrap;
    }
    .empty-orders {
      text-align:center;
      padding:30px 10px;
      font-size:15px;
      color:#6b7280;
    }

    .status-badge {
      display:inline-flex;
      align-items:center;
      padding:3px 9px;
      border-radius:999px;
      font-size:12px;
      font-weight:600;
    }
    .status-default {
      background:#e5e7eb;
      color:#374151;
    }
    .status-pending {
      background:#fef9c3;
      color:#92400e;
    }
    .status-processing {
      background:#e0f2fe;
      color:#075985;
    }
    .status-shipping {
      background:#ede9fe;
      color:#5b21b6;
    }
    .status-done {
      background:#dcfce7;
      color:#166534;
    }
    .status-cancel {
      background:#fee2e2;
      color:#b91c1c;
    }

    .order-customer {
      font-size:13px;
      color:#4b5563;
    }
    .btn-link {
      border-radius:999px;
      padding:5px 10px;
      font-size:12px;
      border:1px solid #d1d5db;
      background:#f9fafb;
      text-decoration:none;
      color:#111827;
      transition:all .15s;
    }
    .btn-link:hover {
      background:#e5e7eb;
    }
  </style>
</head>
<body>

<?php include 'header_front.php'; ?>
<div class="orders-page">
  <div class="orders-header">
    <div>
      <h1>Đơn hàng của tôi</h1>
      <div class="sub">
        <?php if ($currentName): ?>
          Khách hàng: <strong><?= htmlspecialchars($currentName) ?></strong>
        <?php else: ?>
          Bạn chưa đăng nhập, danh sách có thể hiển thị tất cả đơn hàng (hoặc rỗng).
        <?php endif; ?>
      </div>
    </div>
    <div>
      <a href="trangchukhach.php" class="btn-link">← Về trang khách</a>
    </div>
  </div>

  <!-- TAB trạng thái -->
  <div class="status-tabs">
    <?php foreach ($statusTabs as $key => $label): ?>
      <a
        href="?trangthai=<?= $key ?>"
        class="status-tab <?= $filter === $key ? 'active' : '' ?>"
      >
        <?= htmlspecialchars($label) ?>
      </a>
    <?php endforeach; ?>
  </div>

  <?php if (empty($orders)): ?>
    <div class="empty-orders">
      Hiện chưa có đơn hàng nào phù hợp.
    </div>
  <?php else: ?>
    <table class="orders-table">
      <thead>
        <tr>
          <th>Mã đơn</th>
          <th>Sản phẩm</th>
          <th>Số lượng</th>
          <th>Tổng tiền</th>
          <th>Trạng thái</th>
          <th>Khách hàng</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($orders as $o): ?>
          <tr>
            <td>#<?= (int)$o['id'] ?></td>
            <td><?= htmlspecialchars($o['product_name']) ?></td>
            <td><?= (int)$o['quantity'] ?></td>
            <td class="text-right">
              <?= number_format($o['total'], 0, ',', '.') ?>₫
            </td>
            <td><?= renderStatusBadge($o['status']) ?></td>
            <td>
              <div class="order-customer">
                <?= htmlspecialchars($o['customer_name']) ?>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>
<footer style="
  background:#111827;
  color:white;
  text-align:center;
  padding: 16px 0;
  font-size:12px;
  line-height:1.8;
  font-weight:500;
  position:fixed;
  bottom:0;
  left:0;
  width:100%;
">
  <div>
    <a href="#" style="color:white; text-decoration:none; margin:0 8px;">Đã được bảo lưu mọi quyền</a> |
    <a href="lienhelienhe.php" style="color:white; text-decoration:none; margin:0 8px;">Liên hệ với chúng tôi</a> |
    <a href="dieukien.php" style="color:white; text-decoration:none; margin:0 8px;">Điều khoản và Điều kiện</a> |
    <a href="chinhsach.php" style="color:white; text-decoration:none; margin:0 8px;">Chính sách bảo mật</a>
  </div>
</footer>


</body>
</html>
