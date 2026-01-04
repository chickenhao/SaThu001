<?php
session_start();
require 'config.php';

// Kiểm tra đăng nhập - nếu chưa đăng nhập thì redirect
if (empty($_SESSION['user_id'])) {
    header('Location: dangnhap.php?redirect=donhang.php');
    exit;
}

// ----------------- CẤU HÌNH TRẠNG THÁI ĐƠN HÀNG -----------------
// Map với enum trong database: 'pending','paid','shipping','completed','cancelled'
$statuses = [
    'all'           => ['label' => 'Tất cả',          'db' => null],
    'cho_xac_nhan'  => ['label' => 'Chờ xác nhận',    'db' => 'pending'],
    'da_thanh_toan' => ['label' => 'Đã thanh toán',   'db' => 'paid'],
    'dang_giao'     => ['label' => 'Đang giao hàng',  'db' => 'shipping'],
    'da_giao'       => ['label' => 'Đã giao',         'db' => 'completed'],
    'da_huy'        => ['label' => 'Đã hủy',          'db' => 'cancelled'],
];

$currentStatusSlug = $_GET['status'] ?? 'all';
if (!isset($statuses[$currentStatusSlug])) {
    $currentStatusSlug = 'all';
}

// ----------------- XỬ LÝ HỦY ĐƠN HÀNG -----------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel_order'])) {
    $orderId = isset($_POST['order_id']) ? (int)$_POST['order_id'] : 0;
    $cancelReason = trim($_POST['cancel_reason'] ?? '');

    // Chỉ xử lý nếu có user đăng nhập và ID hợp lệ
    if (!empty($_SESSION['user_id']) && $orderId > 0) {
        $userId = (int)$_SESSION['user_id'];

        // Đơn chỉ được hủy khi đang ở trạng thái 'pending' (chờ xử lý)
        $sqlCancel = "
            UPDATE donhang
            SET trang_thai = 'cancelled', ly_do_huy = ?
            WHERE id = ?
              AND user_id = ?
              AND trang_thai = 'pending'
        ";

        $stmtCancel = $conn->prepare($sqlCancel);
        if ($stmtCancel) {
            $stmtCancel->bind_param('sii', $cancelReason, $orderId, $userId);
            $stmtCancel->execute();
            $stmtCancel->close();
        }

        error_log("DEBUG donhang.php - Cancel order #$orderId by user $userId, reason: $cancelReason");
    }

    // Sau khi hủy, chuyển sang tab "Đã hủy"
    header("Location: donhang.php?status=da_huy");
    exit;
}

// ----------------- LẤY DANH SÁCH ĐƠN HÀNG -----------------
$sql = "SELECT * FROM donhang";
$where = [];
$params = [];
$types  = "";

// Debug
error_log("DEBUG donhang.php - user_id from session: " . (isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'NOT SET'));
error_log("DEBUG donhang.php - SESSION data: " . print_r($_SESSION, true));

// Lọc theo user đăng nhập - chỉ hiển thị đơn hàng của user đó
if (!empty($_SESSION['user_id']) && (int)$_SESSION['user_id'] > 0) {
    $user_id_filter = (int)$_SESSION['user_id'];
    $where[] = "user_id = ?";
    $types  .= "i";
    $params[] = $user_id_filter;

    error_log("DEBUG donhang.php - Filtering by user_id: " . $user_id_filter);
} else {
    // Nếu chưa đăng nhập, không hiển thị đơn hàng nào
    $where[] = "1 = 0";
    error_log("DEBUG donhang.php - No user_id in session, showing no orders");
}

if ($currentStatusSlug !== 'all' && $statuses[$currentStatusSlug]['db'] !== null) {
    $where[] = "trang_thai = ?";
    $types  .= "s";
    $params[] = $statuses[$currentStatusSlug]['db'];
}

if (!empty($where)) {
    $sql .= " WHERE " . implode(" AND ", $where);
}
$sql .= " ORDER BY created_at DESC";

// ----------------- PHÂN TRANG -----------------
$itemsPerPage = 3;
$currentPage = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;

// Đếm tổng số đơn hàng
$countSql = "SELECT COUNT(*) as total FROM donhang";
if (!empty($where)) {
    $countSql .= " WHERE " . implode(" AND ", $where);
}

$countStmt = $conn->prepare($countSql);
if ($countStmt) {
    if ($types !== "") {
        $countStmt->bind_param($types, ...$params);
    }
    $countStmt->execute();
    $countResult = $countStmt->get_result();
    $totalOrders = $countResult->fetch_assoc()['total'] ?? 0;
    $countStmt->close();
} else {
    $totalOrders = 0;
}

$totalPages = max(1, ceil($totalOrders / $itemsPerPage));
$currentPage = min($currentPage, $totalPages);
$offset = ($currentPage - 1) * $itemsPerPage;

// Thêm LIMIT, OFFSET
$sql .= " LIMIT ? OFFSET ?";
$types .= "ii";
$params[] = $itemsPerPage;
$params[] = $offset;

// Debug
error_log("DEBUG donhang.php - SQL: " . $sql);
error_log("DEBUG donhang.php - Params: " . print_r($params, true));
error_log("DEBUG donhang.php - Types: " . $types);
error_log("DEBUG donhang.php - Page: $currentPage / $totalPages, Total: $totalOrders");

$stmt = $conn->prepare($sql);
if (!$stmt) {
    error_log("DEBUG donhang.php - SQL Error: " . $conn->error);
}

if ($types !== "") {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

$orders = [];
while ($row = $result->fetch_assoc()) {
    $orders[] = $row;
}
$stmt->close();

error_log("DEBUG donhang.php - Found " . count($orders) . " orders");
if (count($orders) > 0) {
    error_log("DEBUG donhang.php - First order user_id: " . ($orders[0]['user_id'] ?? 'NULL'));
}

// ----------------- LẤY CHI TIẾT SẢN PHẨM CỦA CÁC ĐƠN -----------------
$orderItems = [];

if (!empty($orders)) {
    $ids = array_column($orders, 'id');
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $typesItems   = str_repeat('i', count($ids));

    $sqlItems = "
        SELECT 
            ct.*,
            sp.name AS sanpham_name,
            sp.image AS sanpham_image
        FROM donhang_chitiet ct
        LEFT JOIN sanpham sp ON ct.sanpham_id = sp.id
        WHERE ct.donhang_id IN ($placeholders)
        ORDER BY ct.id ASC
    ";
    $stmt2 = $conn->prepare($sqlItems);
    $stmt2->bind_param($typesItems, ...$ids);
    $stmt2->execute();
    $res2 = $stmt2->get_result();

    while ($row = $res2->fetch_assoc()) {
        $orderItems[$row['donhang_id']][] = $row;
    }
    $stmt2->close();
}
?>
<!doctype html>
<html lang="vi">
<head>
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width,initial-scale=1" />
<title>Đơn mua - Danisa</title>

<link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="trangchu.css">
<link rel="stylesheet" href="banhquybo.css">

<style>
  * {
    box-sizing: border-box;
  }

  body {
    margin: 0;
    padding: 0;
    font-family: 'Cormorant Garamond', serif;
    background: #f5f5f5;
  }

  .logo-section {
    background: white !important;
    text-align: center !important;
    padding: 20px 0 0 !important;
    box-shadow: 0 1px 4px rgba(0,0,0,0.05) !important;
    position: relative !important;
    z-index: 100 !important;
  }

  .logo-section img {
    height: 80px !important;
    width: 100% !important;
    display: block !important;
    margin: 0 !important;
  }

  .site-header {
    background: #111827 !important;
    box-shadow: 0 1px 6px rgba(15,23,42,0.06) !important;
    position: sticky !important;
    top: 0 !important;
    z-index: 1000 !important;
  }

  .dropdown {
    position: relative !important;
    z-index: 1001 !important;
    pointer-events: auto !important;
  }

  .dropdown > a {
    pointer-events: auto !important;
    cursor: pointer !important;
  }

  .dropdown-content {
    z-index: 1002 !important;
    pointer-events: auto !important;
  }

  .dropdown-content a {
    pointer-events: auto !important;
    cursor: pointer !important;
  }

  .account-dropdown {
    position: relative !important;
    z-index: 1001 !important;
    pointer-events: auto !important;
  }

  .account-menu {
    z-index: 1002 !important;
    pointer-events: auto !important;
  }

  .account-menu a {
    pointer-events: auto !important;
    cursor: pointer !important;
  }

  .site-header,
  .header-inner,
  .nav,
  .nav a {
    pointer-events: auto !important;
  }

  .header-inner {
    max-width: 1280px !important;
    margin: 0 auto !important;
    height: 72px !important;
    display: flex !important;
    align-items: center !important;
    justify-content: space-between !important;
    padding: 0 40px !important;
    position: relative !important;
    gap: 16px !important;
  }

  .orders-section {
    background: url('image/anhnen3.jpg') center/cover no-repeat;
    min-height: calc(100vh - 172px);
    display: flex;
    justify-content: center;
    align-items: flex-start;
    padding: 40px 20px 80px;
    color: #111827;
    margin-top: 0;
    position: relative;
    z-index: 1;
  }

  .orders-container {
    width: 100%;
    max-width: 1000px;
    background: rgba(255,255,255,0.98);
    border-radius: 14px;
    padding: 30px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.25);
    margin: 0 auto;
    position: relative;
    z-index: 1;
    pointer-events: auto;
  }

  .orders-header {
    display: flex;
    justify-content: space-between;
    align-items: baseline;
    margin-bottom: 20px;
    padding-bottom: 15px;
    border-bottom: 1px solid #e5e7eb;
  }

  .orders-header h1 {
    margin: 0;
    font-size: 28px;
    font-weight: 700;
    text-transform: uppercase;
    color: #111827;
  }

  .orders-header span {
    font-size: 13px;
    color: #6b7280;
    font-weight: 400;
  }

  .order-tabs {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    margin-bottom: 16px;
  }

  .order-tab {
    padding: 7px 12px;
    border-radius: 999px;
    border: 1px solid #e5e7eb;
    font-size: 13px;
    cursor: pointer;
    background: #f9fafb;
    color: #374151;
    text-decoration: none;
  }

  .order-tab.active {
    background: #111827;
    color: #fff;
    border-color: #111827;
  }

  .order-tab:hover {
    background: #e5e7eb;
    border-color: #d1d5db;
  }

  .order-tab.active:hover {
    background: #111827;
    border-color: #111827;
  }

  .order-list-empty {
    font-size: 15px;
    color: #4b5563;
    padding: 40px 20px;
    text-align: center;
    background: #f9fafb;
    border-radius: 8px;
    border: 1px dashed #d1d5db;
  }

  .order-card {
    border-radius: 12px;
    border: 1px solid #e5e7eb;
    padding: 14px 16px;
    margin-bottom: 12px;
    background: #fff;
  }

  .order-card-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 10px;
  }

  .order-code {
    font-weight: 600;
    font-size: 14px;
  }

  .order-date {
    font-size: 12px;
    color: #6b7280;
  }

  .order-status-badge {
    padding: 4px 10px;
    border-radius: 999px;
    font-size: 12px;
    font-weight: 600;
    white-space: nowrap;
  }

  .status-cho_xac_nhan { background:#fee2e2; color:#b91c1c; }
  .status-da_thanh_toan { background:#fef3c7; color:#92400e; }
  .status-dang_giao     { background:#dbeafe; color:#1d4ed8; }
  .status-da_giao       { background:#dcfce7; color:#166534; }
  .status-da_huy        { background:#f3e8ff; color:#6b21a8; }

  .order-info-line {
    font-size: 13px;
    color: #4b5563;
    margin-bottom: 2px;
  }

  .order-items-table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 8px;
    font-size: 13px;
  }

  .order-items-table th,
  .order-items-table td {
    border-bottom: 1px solid #e5e7eb;
    padding: 8px 12px;
    text-align: left;
    vertical-align: middle;
  }

  .order-items-table th {
    font-weight: 600;
    color: #374151;
    background: #f9fafb;
  }

  .order-items-table td.text-right { text-align: right !important; }
  .order-items-table th.text-right { text-align: right !important; }
  .order-items-table th.text-center { text-align: center !important; }
  .order-items-table td.text-center { text-align: center !important; }

  .order-items-table img {
    flex-shrink: 0;
  }

  .order-card-footer {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-top: 8px;
    font-size: 13px;
  }

  .order-total {
    font-weight: 700;
    font-size: 14px;
  }

  .order-items-wrapper {
    margin-top: 6px;
  }

  .order-card-actions {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
    justify-content: flex-end;
  }

  .order-cancel-btn {
    font-size: 12px;
    padding: 6px 12px;
    border-radius: 999px;
    border: 1px solid #b91c1c;
    background: #b91c1c;
    color: #fff;
    cursor: pointer;
  }

  .order-cancel-btn:hover {
    background: #dc2626;
    border-color: #dc2626;
  }

  /* Box chọn lý do hủy */
  .cancel-reason-box {
    display: none;
    margin-top: 8px;
    padding: 10px 12px;
    border-radius: 8px;
    border: 1px solid #e5e7eb;
    background: #f9fafb;
    max-width: 320px;
  }

  .cancel-reason-box label {
    display: block;
    font-size: 12px;
    margin-bottom: 6px;
    color: #374151;
    font-weight: 600;
  }

  .cancel-reason-select {
    width: 100%;
    padding: 6px 8px;
    border-radius: 6px;
    border: 1px solid #d1d5db;
    font-size: 13px;
    margin-bottom: 6px;
  }

  .cancel-reason-other {
    width: 100%;
    padding: 6px 8px;
    border-radius: 6px;
    border: 1px solid #d1d5db;
    font-size: 13px;
    margin-bottom: 6px;
    display: none;
  }

  .cancel-reason-actions {
    display: flex;
    gap: 6px;
    justify-content: flex-end;
  }

  .cancel-reason-confirm,
  .cancel-reason-cancel {
    border-radius: 999px;
    border: 1px solid #111827;
    padding: 5px 10px;
    font-size: 12px;
    cursor: pointer;
    background: #111827;
    color: #fff;
  }

  .cancel-reason-cancel {
    background: #e5e7eb;
    color: #111827;
    border-color: #d1d5db;
  }

  .cancel-reason-cancel:hover {
    background: #d1d5db;
  }

  .cancel-reason-confirm:hover {
    background: #e65c00;
    border-color: #e65c00;
  }

  /* PHÂN TRANG */
  .pagination-wrapper {
    margin-top: 30px;
    padding-top: 20px;
    border-top: 1px solid #e5e7eb;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 15px;
  }

  .pagination-info {
    font-size: 13px;
    color: #6b7280;
  }

  .pagination {
    display: flex;
    align-items: center;
    gap: 6px;
    flex-wrap: wrap;
    justify-content: center;
  }

  .pagination-btn {
    padding: 8px 14px;
    border-radius: 8px;
    border: 1px solid #e5e7eb;
    background: #fff;
    color: #374151;
    text-decoration: none;
    font-size: 13px;
    font-weight: 500;
    transition: all 0.2s;
    cursor: pointer;
  }

  .pagination-btn.disabled {
    opacity: 0.5;
    cursor: not-allowed;
    background: #f9fafb;
  }

  .pagination-btn:hover:not(.disabled) {
    background: #f9fafb;
    border-color: #d1d5db;
    color: #111827;
  }

  .pagination-number {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 36px;
    height: 36px;
    padding: 0 8px;
    border-radius: 8px;
    border: 1px solid #e5e7eb;
    background: #fff;
    color: #374151;
    text-decoration: none;
    font-size: 13px;
    font-weight: 500;
    transition: all 0.2s;
  }

  .pagination-number:hover {
    background: #f9fafb;
    border-color: #d1d5db;
    color: #111827;
  }

  .pagination-number.active {
    background: #111827;
    border-color: #111827;
    color: #fff;
    cursor: default;
  }

  .pagination-dots {
    padding: 0 4px;
    color: #9ca3af;
    font-size: 13px;
  }

  @media (max-width: 768px) {
    .orders-section {
      padding: 20px 10px 40px;
    }

    .orders-container {
      padding: 20px 15px;
    }

    .orders-header {
      flex-direction: column;
      gap: 10px;
      align-items: flex-start;
    }

    .orders-header h1 {
      font-size: 22px;
    }

    .order-tabs {
      gap: 4px;
    }

    .order-tab {
      font-size: 12px;
      padding: 6px 10px;
    }

    .order-card-footer {
      flex-direction: column;
      align-items: flex-start;
      gap: 4px;
    }

    .order-card-header {
      flex-direction: column;
      gap: 10px;
    }

    .pagination {
      gap: 4px;
    }

    .pagination-btn,
    .pagination-number {
      font-size: 12px;
      padding: 6px 10px;
      min-width: 32px;
      height: 32px;
    }
  }
</style>
</head>
<body>
<?php include 'header_front.php'; ?>

<section class="orders-section">
  <div class="orders-container">
    <div class="orders-header">
      <h1>Đơn mua của bạn</h1>
      <span>Chọn trạng thái để xem chi tiết đơn hàng</span>
    </div>

    <div class="order-tabs">
      <?php foreach ($statuses as $slug => $info): ?>
        <a
          class="order-tab <?= $slug === $currentStatusSlug ? 'active' : '' ?>"
          href="?status=<?= htmlspecialchars($slug) ?>&page=1"
        >
          <?= htmlspecialchars($info['label']) ?>
        </a>
      <?php endforeach; ?>
    </div>

    <?php if (empty($orders)): ?>
      <div class="order-list-empty">
        <?php if ($currentStatusSlug === 'all'): ?>
          Bạn chưa có đơn hàng nào.
        <?php else: ?>
          Không có đơn hàng nào ở trạng thái "<strong><?= htmlspecialchars($statuses[$currentStatusSlug]['label']) ?></strong>".
        <?php endif; ?>
      </div>
    <?php else: ?>
      <?php foreach ($orders as $order): ?>
        <?php
          $statusSlugForClass = 'all';
          foreach ($statuses as $slug => $st) {
            if ($st['db'] !== null && $st['db'] == $order['trang_thai']) {
              $statusSlugForClass = $slug;
              break;
            }
          }
          $items = $orderItems[$order['id']] ?? [];
        ?>
        <div class="order-card">
          <div class="order-card-header">
            <div>
              <div class="order-code">Mã đơn #<?= (int)$order['id'] ?></div>
              <div class="order-date">
                Ngày đặt: <?= !empty($order['created_at']) ? date('d/m/Y H:i', strtotime($order['created_at'])) : '' ?>
              </div>
              <div class="order-info-line">
                Người nhận: <?= htmlspecialchars($order['ho_ten']) ?> |
                SĐT: <?= htmlspecialchars($order['phone']) ?>
              </div>
              <div class="order-info-line">
                Địa chỉ: <?= htmlspecialchars($order['dia_chi']) ?>
              </div>
              <?php if ($order['trang_thai'] === 'cancelled' && !empty($order['ly_do_huy'])): ?>
                <div class="order-info-line" style="color:#b91c1c; font-style: italic;">
                  Lý do hủy: <?= htmlspecialchars($order['ly_do_huy']) ?>
                </div>
              <?php endif; ?>
            </div>
            <div>
              <span class="order-status-badge status-<?= htmlspecialchars($statusSlugForClass) ?>">
                <?= htmlspecialchars($statuses[$statusSlugForClass]['label'] ?? $order['trang_thai']) ?>
              </span>
            </div>
          </div>

          <div class="order-items-wrapper">
            <?php if (empty($items)): ?>
              <div style="font-size:13px; color:#6b7280;">Đơn hàng chưa có chi tiết sản phẩm.</div>
            <?php else: ?>
              <table class="order-items-table">
                <thead>
                  <tr>
                    <th style="width: 35%;">Sản phẩm</th>
                    <th style="width: 8%; text-align: center;">SL</th>
                    <th style="width: 15%; text-align: right;">Đơn giá</th>
                    <th style="width: 15%; text-align: right;">Giảm giá</th>
                    <th style="width: 17%; text-align: right;">Thành tiền</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($items as $it): ?>
                    <?php
                      $so_luong = (int)$it['so_luong'];
                      $don_gia = (float)$it['don_gia'];
                      $thanh_tien = (float)$it['thanh_tien'];
                      $tong_truoc_giam = $don_gia * $so_luong;
                      $giam_gia = $tong_truoc_giam - $thanh_tien;
                    ?>
                    <tr>
                      <td>
                        <div style="display: flex; align-items: center; gap: 8px;">
                          <?php if (!empty($it['sanpham_image'])): ?>
                            <img src="<?= htmlspecialchars($it['sanpham_image']) ?>"
                                 alt="<?= htmlspecialchars($it['sanpham_name'] ?? 'Sản phẩm') ?>"
                                 style="width: 40px; height: 40px; object-fit: cover; border-radius: 4px;">
                          <?php endif; ?>
                          <span>
                            <?= htmlspecialchars($it['sanpham_name'] ?? 'Sản phẩm #' . (int)$it['sanpham_id']) ?>
                          </span>
                        </div>
                      </td>
                      <td style="text-align: center;"><?= $so_luong ?></td>
                      <td class="text-right">
                        <?= number_format($don_gia, 0, ',', '.') ?>₫
                      </td>
                      <td class="text-right" style="color: #dc2626;">
                        <?= $giam_gia > 0 ? '-' . number_format($giam_gia, 0, ',', '.') . '₫' : '0₫' ?>
                      </td>
                      <td class="text-right" style="font-weight: 600;">
                        <?= number_format($thanh_tien, 0, ',', '.') ?>₫
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            <?php endif; ?>
          </div>

          <div class="order-card-footer">
            <div>
              <div class="order-total">
                Tổng thanh toán: <?= number_format($order['tong_tien'], 0, ',', '.') ?>₫
              </div>
              <div class="order-info-line">
                Thanh toán: <?= htmlspecialchars($order['phuong_thuc_thanh_toan']) ?>
              </div>
            </div>

            <div class="order-card-actions">
              <?php if ($order['trang_thai'] === 'pending'): ?>
                <form method="post" class="cancel-order-form" style="display:inline;">
                  <input type="hidden" name="order_id" value="<?= (int)$order['id'] ?>">
                  <input type="hidden" name="cancel_reason" class="cancel-reason-input">
                  <input type="hidden" name="cancel_order" value="1">

                  <button type="button" class="order-cancel-btn">
                    Hủy đơn hàng
                  </button>

                  <div class="cancel-reason-box">
                    <label>Chọn lý do hủy đơn:</label>
                    <select class="cancel-reason-select">
                      <option value="">-- Chọn lý do --</option>
                      <option value="Không muốn nhận hàng nữa">Không muốn nhận hàng nữa</option>
                      <option value="Đặt nhầm sản phẩm / số lượng">Đặt nhầm sản phẩm / số lượng</option>
                      <option value="Thay đổi địa chỉ / số điện thoại nhận hàng">Thay đổi địa chỉ / số điện thoại nhận hàng</option>
                      <option value="Tìm được sản phẩm khác phù hợp hơn">Tìm được sản phẩm khác phù hợp hơn</option>
                      <option value="Người bán giao hàng chậm">Người bán giao hàng chậm</option>
                      <option value="other">Lý do khác</option>
                    </select>

                    <input type="text" class="cancel-reason-other" placeholder="Nhập lý do khác (bắt buộc)">

                    <div class="cancel-reason-actions">
                      <button type="button" class="cancel-reason-cancel">Đóng</button>
                      <button type="button" class="cancel-reason-confirm">Xác nhận hủy</button>
                    </div>
                  </div>
                </form>
              <?php endif; ?>
            </div>
          </div>
        </div>
      <?php endforeach; ?>

      <?php if ($totalPages > 1): ?>
        <div class="pagination-wrapper">
          <div class="pagination-info">
            Hiển thị <?= count($orders) ?> / <?= $totalOrders ?> đơn hàng
          </div>
          <div class="pagination">
            <?php
              $baseUrl = "?status=" . urlencode($currentStatusSlug);

              if ($currentPage > 1):
            ?>
              <a href="<?= $baseUrl ?>&page=<?= $currentPage - 1 ?>" class="pagination-btn prev">
                ← Trước
              </a>
            <?php else: ?>
              <span class="pagination-btn prev disabled">← Trước</span>
            <?php endif; ?>

            <?php
              $startPage = max(1, $currentPage - 2);
              $endPage = min($totalPages, $currentPage + 2);

              if ($startPage > 1):
            ?>
              <a href="<?= $baseUrl ?>&page=1" class="pagination-number">1</a>
              <?php if ($startPage > 2): ?>
                <span class="pagination-dots">...</span>
              <?php endif; ?>
            <?php endif; ?>

            <?php for ($i = $startPage; $i <= $endPage; $i++): ?>
              <?php if ($i == $currentPage): ?>
                <span class="pagination-number active"><?= $i ?></span>
              <?php else: ?>
                <a href="<?= $baseUrl ?>&page=<?= $i ?>" class="pagination-number"><?= $i ?></a>
              <?php endif; ?>
            <?php endfor; ?>

            <?php if ($endPage < $totalPages): ?>
              <?php if ($endPage < $totalPages - 1): ?>
                <span class="pagination-dots">...</span>
              <?php endif; ?>
              <a href="<?= $baseUrl ?>&page=<?= $totalPages ?>" class="pagination-number"><?= $totalPages ?></a>
            <?php endif; ?>

            <?php if ($currentPage < $totalPages): ?>
              <a href="<?= $baseUrl ?>&page=<?= $currentPage + 1 ?>" class="pagination-btn next">
                Sau →
              </a>
            <?php else: ?>
              <span class="pagination-btn next disabled">Sau →</span>
            <?php endif; ?>
          </div>
        </div>
      <?php endif; ?>
    <?php endif; ?>

  </div>
</section>

<script>
document.addEventListener("DOMContentLoaded", function() {
  const currentUser = JSON.parse(localStorage.getItem("currentUser"));
  const accountDropdown = document.querySelector(".account-dropdown");
  if (currentUser && accountDropdown) {
    const nameTag = document.createElement("span");
    nameTag.textContent = currentUser.username;
    nameTag.style.color = "white";
    nameTag.style.marginLeft = "8px";
    nameTag.style.fontSize = "17px";
    nameTag.style.fontWeight = "600";
    accountDropdown.insertBefore(nameTag, accountDropdown.querySelector(".account-menu"));
    const menuLinks = accountDropdown.querySelectorAll(".account-menu a");
    if (menuLinks.length >= 2) {
      menuLinks[0].style.display = "none";
      menuLinks[1].style.display = "none";
    }
  }

  const logoutLink = document.getElementById("logout-link");
  if (logoutLink) {
    logoutLink.addEventListener("click", function(e) {
      e.preventDefault();
      localStorage.removeItem("currentUser");
      alert("Đăng xuất thành công!");
      window.location.href = "dangnhap.php";
    });
  }

  const dropdowns = document.querySelectorAll(".dropdown");
  dropdowns.forEach(drop => {
    const menu = drop.querySelector(".dropdown-content");
    if (!menu) return;

    drop.addEventListener("mouseenter", function () {
      document.querySelectorAll(".dropdown-content.show").forEach(dc => {
        if (dc !== menu) dc.classList.remove("show");
      });
      menu.classList.add("show");
    });

    drop.addEventListener("mouseleave", function () {
      menu.classList.remove("show");
    });
  });

  document.querySelectorAll(".dropdown-content a").forEach(link => {
    link.style.pointerEvents = "auto";
    link.style.cursor = "pointer";
  });

  const siteHeader = document.querySelector(".site-header");
  if (siteHeader) {
    siteHeader.style.pointerEvents = "auto";
  }

  document.querySelectorAll(".dropdown").forEach(drop => {
    drop.style.pointerEvents = "auto";
  });

  // ==== XỬ LÝ HỦY ĐƠN HÀNG VỚI LIST LÝ DO ====
  document.querySelectorAll(".cancel-order-form").forEach(function(form) {
    const btnToggle = form.querySelector(".order-cancel-btn");
    const box = form.querySelector(".cancel-reason-box");
    const select = form.querySelector(".cancel-reason-select");
    const inputOther = form.querySelector(".cancel-reason-other");
    const btnConfirm = form.querySelector(".cancel-reason-confirm");
    const btnClose = form.querySelector(".cancel-reason-cancel");
    const hiddenReason = form.querySelector(".cancel-reason-input");

    if (!btnToggle || !box || !select || !btnConfirm || !btnClose || !hiddenReason) return;

    // Mở / đóng khung chọn lý do
    btnToggle.addEventListener("click", function() {
      if (box.style.display === "block") {
        box.style.display = "none";
      } else {
        box.style.display = "block";
      }
    });

    // Hiện ô nhập nếu chọn "Lý do khác"
    select.addEventListener("change", function() {
      if (select.value === "other") {
        inputOther.style.display = "block";
      } else {
        inputOther.style.display = "none";
        inputOther.value = "";
      }
    });

    // Đóng khung, không hủy
    btnClose.addEventListener("click", function() {
      box.style.display = "none";
    });

    // Xác nhận hủy
    btnConfirm.addEventListener("click", function() {
      const val = select.value;

      if (!val) {
        alert("Vui lòng chọn lý do hủy đơn.");
        return;
      }

      let reasonText = "";

      if (val === "other") {
        if (!inputOther.value.trim()) {
          alert("Vui lòng nhập lý do.");
          return;
        }
        reasonText = inputOther.value.trim();
      } else {
        // Lấy text hiển thị của option
        reasonText = select.options[select.selectedIndex].text;
      }

      hiddenReason.value = reasonText;
      form.submit();
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
">
  <div class="linklien">
    <a href="#" style="color:white; text-decoration:none; margin:0 8px;">Đã được bảo lưu mọi quyền</a> |
    <a href="lienhelienhe.php" style="color:white; text-decoration:none; margin:0 8px;">Liên hệ với chúng tôi</a> |
    <a href="dieukien.php" style="color:white; text-decoration:none; margin:0 8px;">Điều khoản và Điều kiện</a> |
    <a href="chinhsach.php" style="color:white; text-decoration:none; margin:0 8px;">Chính sách bảo mật</a>
  </div>
</footer>
</body>
</html>
