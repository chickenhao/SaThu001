<?php
session_start();
require 'config.php';

// Kiểm tra quyền truy cập - chỉ admin và staff mới được vào
if (empty($_SESSION['user_id'])) {
    header('Location: dangnhap.php?redirect=quanlyadmin.php');
    exit;
}

$userRole = $_SESSION['role'] ?? 'customer';
if ($userRole !== 'admin' && $userRole !== 'staff') {
    header('Location: trangchu.php');
    exit;
}

// ===== Thống kê tổng =====
$res_products = $conn->query("SELECT COUNT(*) AS total FROM sanpham");
$res_orders   = $conn->query("SELECT COUNT(*) AS total FROM donhang");
$res_revenue  = $conn->query("SELECT SUM(tong_tien) AS revenue
FROM donhang
WHERE trang_thai IN ('paid','shipping','completed')
");

$products_total = $res_products ? $res_products->fetch_assoc()['total']  : 0;
$orders_total   = $res_orders   ? $res_orders->fetch_assoc()['total']    : 0;
$revenue_total  = $res_revenue  ? (float)$res_revenue->fetch_assoc()['revenue'] : 0;

// ===== TÍNH TỔNG TIỀN NHẬP HÀNG & LỢI NHUẬN =====
// 1. Tổng tiền nhập từ bảng NHAPHANG (các phiếu nhập hàng)
$import_from_nhaphang = 0;
$res_import_nh = $conn->query("
    SELECT SUM(GREATEST(so_luong, 0) * import_price) AS total_import
    FROM nhaphang
");
if ($res_import_nh) {
    $row_import_nh         = $res_import_nh->fetch_assoc();
    $import_from_nhaphang  = isset($row_import_nh['total_import']) ? (float)$row_import_nh['total_import'] : 0;
}

// 2. Tổng tiền nhập của các sản phẩm mới thêm (chưa có phiếu nhập nào)
$import_from_new_products = 0;
$res_import_new = $conn->query("
    SELECT SUM(GREATEST(sp.quantity, 0) * sp.import_price) AS total_import_new
    FROM sanpham sp
    LEFT JOIN nhaphang n ON n.sanpham_id = sp.id
    WHERE n.id IS NULL
");
if ($res_import_new) {
    $row_import_new           = $res_import_new->fetch_assoc();
    $import_from_new_products = isset($row_import_new['total_import_new']) ? (float)$row_import_new['total_import_new'] : 0;
}

// 3. Tổng tiền nhập hàng = tiền nhập trong quản lý nhập hàng + tiền nhập của sản phẩm mới
$import_total = $import_from_nhaphang + $import_from_new_products;

// 4. Lợi nhuận = Doanh thu - Tổng tiền nhập hàng
$profit_total = $revenue_total - $import_total;

// ===== Lấy danh sách sản phẩm =====
$sanpham = [];
$res = $conn->query("SELECT * FROM sanpham");
if ($res) {
    while ($row = $res->fetch_assoc()) {
        // Chuẩn hóa đường dẫn ảnh để hiển thị đúng trên mọi máy
        if (!empty($row['image']) && function_exists('normalizeImagePath')) {
            $row['image'] = normalizeImagePath($row['image']);
        }
        $sanpham[] = $row;
    }
}

// ===== Lấy danh sách đơn hàng (join chi tiết để có SP + SL) =====
$donhang = [];
$res = $conn->query("
    SELECT
        d.id,
        d.ho_ten AS customer_name,
        COALESCE(SUM(ct.so_luong), 0) AS quantity,
        MIN(sp.name) AS product_name,
        d.tong_tien AS total,
        d.trang_thai AS status,
        d.created_at
    FROM donhang d
    LEFT JOIN donhang_chitiet ct ON d.id = ct.donhang_id
    LEFT JOIN sanpham sp ON ct.sanpham_id = sp.id
    GROUP BY d.id
    ORDER BY d.id DESC
");
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $donhang[] = $row;
    }
}

// ===== Lấy danh sách khuyến mãi =====
$khuyenmai = [];
$km_total  = 0;
$res_km = $conn->query("SELECT * FROM khuyenmai");
if ($res_km) {
    while ($row = $res_km->fetch_assoc()) {
        $khuyenmai[] = $row;
    }
    $km_total = count($khuyenmai);
}

// ===== Doanh thu theo ngày (tối đa 14 ngày gần nhất) =====
$revenue_by_day = [];
$res_rev_day = $conn->query("
SELECT DATE(created_at) AS order_date, SUM(tong_tien) AS total
FROM donhang
GROUP BY DATE(created_at)
ORDER BY order_date DESC
LIMIT 14

");
if ($res_rev_day) {
    while ($row = $res_rev_day->fetch_assoc()) {
        $revenue_by_day[] = [
            'date'  => $row['order_date'],
            'total' => (float)$row['total']
        ];
    }
}
// ===== Lấy danh sách KM SẢN PHẨM =====
// ===== Lấy danh sách KM SẢN PHẨM (có tên sản phẩm) =====
$km_sanpham = [];
$res_kmsp = $conn->query("
  SELECT
    km.*,
    COUNT(DISTINCT i.sanpham_id) AS product_count,
    GROUP_CONCAT(DISTINCT sp.name ORDER BY sp.name SEPARATOR ', ') AS product_names,
    GROUP_CONCAT(DISTINCT i.sanpham_id ORDER BY i.sanpham_id SEPARATOR ',') AS product_ids
  FROM km_sanpham km
  LEFT JOIN km_sanpham_items i ON i.km_id = km.id
  LEFT JOIN sanpham sp ON sp.id = i.sanpham_id
  GROUP BY km.id
  ORDER BY km.id DESC
");
if ($res_kmsp) {
  while ($row = $res_kmsp->fetch_assoc()) {
    $km_sanpham[] = $row;
  }
}

// ===== Lấy danh sách KM NGÀY LỄ =====
$km_ngayle = [];
$res_kmle = $conn->query("
  SELECT km.*, COUNT(p.sanpham_id) AS product_count
  FROM km_ngayle km
  LEFT JOIN km_ngayle_products p ON p.km_id = km.id
  GROUP BY km.id
  ORDER BY km.id DESC
");
if ($res_kmle) {
  while ($row = $res_kmle->fetch_assoc()) {
    $km_ngayle[] = $row;
  }
}


// ===== Tài khoản =====
$taikhoan = [];
$tk_total = 0;

// Đếm từ bảng users
$res_tk_total = $conn->query("SELECT COUNT(*) AS total FROM users");
if ($res_tk_total) {
    $tk_total = (int)$res_tk_total->fetch_assoc()['total'];
}

// Lấy dữ liệu từ bảng users (đã có cột role, status, phone)
$res_tk = $conn->query("
  SELECT
     id,
     username,
     COALESCE(email, '') AS email,
     COALESCE(phone, '') AS phone,
     COALESCE(role, 'customer') AS role,
     COALESCE(status, 'active') AS status,
     created_at,
     NULL AS last_login
  FROM users
  ORDER BY id DESC
");

if ($res_tk) {
    while ($row = $res_tk->fetch_assoc()) {
        $taikhoan[] = [
            'id'         => $row['id'],
            'username'   => $row['username'],
            'email'      => $row['email'] ?: '',
            'phone'      => $row['phone'] ?: '',
            'role'       => $row['role'],
            'status'     => $row['status'],
            'created_at' => $row['created_at'],
            'last_login' => $row['last_login']
        ];
    }
}

// ===== Lấy danh sách danh mục với thông tin khuyến mãi =====
$danhmuc = [];
$dm_total = 0;
$res_dm = $conn->query("SELECT * FROM danhmuc ORDER BY id DESC");
if ($res_dm) {
    while ($row = $res_dm->fetch_assoc()) {
        $danhmuc_id = $row['id'];
        
        // Lấy ngày khuyến mãi: từ km_ngayle có scope='category' và danhmuc_id trùng, hoặc scope='all'
        $promotion_dates = [];
        $stmt_km = $conn->prepare("
            SELECT start_date, end_date 
            FROM km_ngayle 
            WHERE status = 'active' 
            AND ((scope = 'category' AND danhmuc_id = ?) OR (scope = 'all'))
            ORDER BY start_date DESC
        ");
        if ($stmt_km) {
            $stmt_km->bind_param("i", $danhmuc_id);
            $stmt_km->execute();
            $res_km = $stmt_km->get_result();
            while ($km_row = $res_km->fetch_assoc()) {
                $start = $km_row['start_date'] ? date('d/m/Y', strtotime($km_row['start_date'])) : '';
                $end = $km_row['end_date'] ? date('d/m/Y', strtotime($km_row['end_date'])) : '';
                if ($start || $end) {
                    $promotion_dates[] = $start . ($end ? ' - ' . $end : '');
                }
            }
            $stmt_km->close();
        }
        
        // Lấy sản phẩm khuyến mãi: 
        // 1. Sản phẩm trong danh mục này có trong km_ngayle_products (scope='products')
        // 2. Tất cả sản phẩm trong danh mục khi có promotion scope='category' cho danh mục này
        $promotion_products = [];
        $danhmuc_name = $row['name']; // Lấy tên danh mục để so sánh với sanpham.category
        $stmt_sp = $conn->prepare("
            SELECT DISTINCT sp.name 
            FROM sanpham sp
            WHERE sp.category = ?
            AND (
                EXISTS (
                    SELECT 1 FROM km_ngayle_products kmp
                    INNER JOIN km_ngayle km ON km.id = kmp.km_id
                    WHERE kmp.sanpham_id = sp.id 
                    AND km.status = 'active'
                )
                OR EXISTS (
                    SELECT 1 FROM km_ngayle km
                    WHERE km.scope = 'category' 
                    AND km.danhmuc_id = ?
                    AND km.status = 'active'
                )
            )
            ORDER BY sp.name
        ");
        if ($stmt_sp) {
            $stmt_sp->bind_param("si", $danhmuc_name, $danhmuc_id);
            $stmt_sp->execute();
            $res_sp = $stmt_sp->get_result();
            while ($sp_row = $res_sp->fetch_assoc()) {
                $promotion_products[] = $sp_row['name'];
            }
            $stmt_sp->close();
        }
        
        // Thêm thông tin khuyến mãi vào row
        $row['promotion_dates'] = implode('; ', $promotion_dates);
        $row['promotion_products'] = implode(', ', $promotion_products);
        
        $danhmuc[] = $row;
    }
    $dm_total = count($danhmuc);
}

// ===== Lấy danh sách nhập hàng =====
$nhaphang = [];
$nhaphang_total = 0;

$res_nh = $conn->query("
    SELECT
        n.id,
        n.sanpham_id,
        sp.name AS product_name,
        n.so_luong,
        n.import_price,
        n.ghi_chu,
        n.created_at
    FROM nhaphang n
    JOIN sanpham sp ON n.sanpham_id = sp.id
    ORDER BY n.id DESC
");
if ($res_nh) {
    while ($row = $res_nh->fetch_assoc()) {
        $nhaphang[] = $row;
    }
    $nhaphang_total = count($nhaphang);
}
// ===== Lấy danh sách phiếu xuất =====
$phieuxuat = [];
$phieuxuat_total = 0;

$res_px = $conn->query("
  SELECT
    d.id AS order_id,
    d.created_at AS created_at,
    d.ho_ten AS customer_name,
    ct.sanpham_id,
    sp.name AS product_name,
    ct.so_luong,
    COALESCE(NULLIF(sp.sale_price,0), NULLIF(sp.price,0), 0) AS export_price,
    CONCAT('Xuất theo đơn #', d.id) AS ly_do,
    '' AS ghi_chu
  FROM donhang d
  JOIN donhang_chitiet ct ON ct.donhang_id = d.id
  JOIN sanpham sp ON sp.id = ct.sanpham_id
  WHERE d.trang_thai IN ('paid','shipping','completed')
  ORDER BY d.id DESC, ct.sanpham_id ASC
");
if ($res_px) {
  while ($row = $res_px->fetch_assoc()) $phieuxuat[] = $row;
  $phieuxuat_total = count($phieuxuat);
}
if (!$res_px) {
  die("SQL ERROR (phieuxuat): " . $conn->error);
}


// ===== Tồn kho lâu chưa bán (aging inventory) =====
$tonkhoAging = [];

$res_tk_aging = $conn->query("
  SELECT
    sp.id,
    sp.name,
    sp.category,
    GREATEST(COALESCE(sp.quantity,0),0) AS quantity,
    COALESCE(sp.import_price,0) AS import_price,
    COALESCE(sp.sale_price,0) AS sale_price,

    -- lần bán gần nhất (chỉ tính đơn hợp lệ)
    MAX(CASE
      WHEN d.trang_thai IN ('paid','shipping','completed') THEN d.created_at
      ELSE NULL
    END) AS last_sold_at,

    -- lần nhập đầu tiên (nếu chưa từng bán thì lấy mốc này)
    MIN(n.created_at) AS first_import_at

  FROM sanpham sp
  LEFT JOIN donhang_chitiet ct ON ct.sanpham_id = sp.id
  LEFT JOIN donhang d ON d.id = ct.donhang_id
  LEFT JOIN nhaphang n ON n.sanpham_id = sp.id
  GROUP BY sp.id
");
if ($res_tk_aging) {
  while ($row = $res_tk_aging->fetch_assoc()) {
    // Tính days_unsold phía PHP để JS dễ dùng
    $lastSold = $row['last_sold_at'];
    $firstImp = $row['first_import_at'];
    $anchor   = $lastSold ?: $firstImp; // nếu null hết thì JS sẽ xử lý
    $row['anchor_date'] = $anchor;

    $tonkhoAging[] = $row;
  }
}


// ===== Lấy danh sách chat box =====
$chat_messages = [];
$chat_conversations_total = 0;

$res_chat = $conn->query("
    SELECT
        cm.*,
        u.username,
        u.email
    FROM chat_messages cm
    LEFT JOIN users u ON cm.user_id = u.id
    ORDER BY cm.user_id, cm.created_at ASC
");

if ($res_chat) {
    $convUsers = [];
    while ($row = $res_chat->fetch_assoc()) {
        $chat_messages[] = $row;
        $uid = $row['user_id'] ?? 0;
        if (!isset($convUsers[$uid])) {
            $convUsers[$uid] = true;
        }
    }
    $chat_conversations_total = count($convUsers);
}

// ===== FLASH MESSAGE (thông báo chung) =====
$flash_message = '';
$flash_type    = ''; // 'success' hoặc 'error'

// Lỗi nhập hàng lưu trong session
if (!empty($_SESSION['nhaphang_error'])) {
    $flash_message = $_SESSION['nhaphang_error'];
    $flash_type    = 'error';
    unset($_SESSION['nhaphang_error']);
}

// Thông báo thành công từ query string
if (isset($_GET['msg']) && $_GET['msg'] === 'import_ok') {
    $flash_message = 'Đã lưu phiếu nhập hàng và cập nhật tồn kho sản phẩm.';
    $flash_type    = 'success';
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Quản Lý - Danisa</title>
  <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@400;500;600;700&display=swap" rel="stylesheet">

  <!-- Font Awesome -->
  <link rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css"
        integrity="sha512-DTOQO9RWCH3ppGqcWaEA1BIZOC6xxalwEsw9c2QQeAIftl+Vegovlnee1c9QX4TctnWMn13TZye+giMm8e2LwA=="
        crossorigin="anonymous" referrerpolicy="no-referrer" />

  <link rel="stylesheet" href="style.css">
  <style>
    /* Đảm bảo sidebar cố định - override mọi CSS khác */
    html, body {
      margin: 0 !important;
      padding: 0 !important;
      overflow-x: hidden !important;
      height: 100% !important;
    }

    .dashboard {
      position: relative !important;
      width: 100% !important;
      min-height: 100vh !important;
    }

    .sidebar {
      position: fixed !important;
      top: 0 !important;
      left: 0 !important;
      width: 250px !important;
      height: 100vh !important;
      overflow-y: auto !important;
      overflow-x: hidden !important;
      z-index: 1000 !important;
      background-color: #111827 !important;
      display: flex !important;
      flex-direction: column !important;
      padding: 20px !important;
      box-sizing: border-box !important;
    }

    .main-content {
      margin-left: 250px !important;
      width: calc(100% - 250px) !important;
      min-height: 100vh !important;
      padding: 30px !important;
      box-sizing: border-box !important;
      background-color: #fdf6f0 !important;
    }

    .sidebar .logo {
      flex-shrink: 0 !important;
    }

    .sidebar .menu {
      flex: 1 !important;
      overflow-y: auto !important;
    }

    .btn {
      display: inline-block;
      padding: 6px 12px;
      border-radius: 999px;
      border: 1px solid transparent;
      background: #e5e7eb;
      color: #111827;
      font-size: 13px;
      cursor: pointer;
      font-family: inherit;
      transition: background .15s, color .15s, border-color .15s, transform .1s;
      text-decoration: none;
    }
    .btn:hover { background:#d1d5db; transform:translateY(-1px); }
    .btn-sm { padding:4px 9px; font-size:12px; }
    .btn-primary { background:#2563eb; border-color:#2563eb; color:#fff; }
    .btn-primary:hover { background:#1d4ed8; border-color:#1d4ed8; }
    .btn-secondary { background:#f9fafb; border-color:#9ca3af; color:#111827; }
    .btn-secondary:hover { background:#e5e7eb; }
    .btn-danger { background:#fee2e2; border-color:#dc2626; color:#b91c1c; }
    .btn-danger:hover { background:#fecaca; }

    .table-header {
      display:flex;
      justify-content:space-between;
      align-items:center;
      gap:10px;
      margin-bottom:12px;
    }
    .table-header h2 { margin:0; }
    .menu-item, .card { cursor:pointer; }

    /* Style cho menu item + icon */
    .menu-item {
      display: flex;
      align-items: center;
      gap: 8px;
      padding: 8px 12px;
      border-radius: 999px;
      color: #e5e7eb;
      font-size: 14px;
    }

    .menu-item i {
      font-size: 15px;
    }

    /* icon tên trang ở header */
    .page-title-wrap {
      display: flex;
      align-items: center;
      gap: 8px;
    }

    .page-icon {
      font-size: 20px;
      color: #111827;
    }

    table td button, table td .btn {
      margin-right:4px;
      margin-bottom:3px;
    }

    .add-product-form {
      max-width:820px;
      margin:0 auto;
      background:#ffffff;
      border-radius:14px;
      padding:18px 22px 20px;
      box-shadow:0 10px 30px rgba(0,0,0,0.06);
      display:grid;
      grid-template-columns:repeat(auto-fit,minmax(260px,1fr));
      gap:14px 20px;
    }
    .add-product-form .form-group {
      display:flex;
      flex-direction:column;
      gap:4px;
      font-size:14px;
    }
    .add-product-form .form-group label { font-weight:600; }
    .add-product-form input,
    .add-product-form select,
    .add-product-form textarea {
      border-radius:8px;
      border:1px solid #d1d5db;
      padding:7px 10px;
      font-family:inherit;
      font-size:14px;
    }
    .add-product-form textarea { resize:vertical; min-height:90px; }
    .add-product-form .form-actions {
      grid-column:1/-1;
      display:flex;
      justify-content:flex-end;
      gap:8px;
      margin-top:4px;
    }
    @media (max-width:640px){
      .add-product-form { padding:14px 14px 18px; }
    }

    .pagination {
      margin-top: 20px;
      display: flex;
      justify-content: center;
      align-items: center;
      gap: 8px;
      flex-wrap: wrap;
    }
    .pagination button:disabled {
      opacity: 0.5;
      cursor: not-allowed;
    }
    .pagination .page-info {
      margin: 0 12px;
      color: #666;
      font-size: 14px;
    }

    /* ===== CSS cho phần BÁO CÁO ===== */
    .report-cards {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(210px, 1fr));
      gap: 16px;
      margin-bottom: 22px;
    }
    .report-card {
      background: #ffffff;
      border-radius: 14px;
      padding: 14px 16px;
      box-shadow: 0 10px 25px rgba(0,0,0,0.05);
    }
    .report-card-title {
      font-size: 13px;
      text-transform: uppercase;
      letter-spacing: 0.04em;
      color: #6b7280;
      margin-bottom: 6px;
    }
    .report-card-value {
      font-size: 22px;
      font-weight: 700;
      margin-bottom: 4px;
    }
    .report-card-sub {
      font-size: 12px;
      color: #9ca3af;
    }
    .report-charts {
      display: grid;
      grid-template-columns: minmax(0, 2fr) minmax(0, 1.5fr);
      gap: 18px;
      margin-bottom: 22px;
    }
    .report-chart-item {
      background: #ffffff;
      border-radius: 14px;
      padding: 14px 16px;
      box-shadow: 0 10px 25px rgba(0,0,0,0.04);
    }
    .report-chart-item h3 {
      margin: 0 0 8px;
      font-size: 15px;
    }
    .report-chart-empty {
      font-size: 13px;
      color: #9ca3af;
      text-align: center;
      padding: 32px 8px;
    }
    .report-table {
      background: #ffffff;
      border-radius: 14px;
      padding: 14px 16px 18px;
      box-shadow: 0 10px 25px rgba(0,0,0,0.04);
    }
    .report-table h3 {
      margin: 0 0 8px;
      font-size: 15px;
    }
    .report-table table {
      width: 100%;
      border-collapse: collapse;
      margin-top: 6px;
    }
    .report-table th,
    .report-table td {
      padding: 8px 6px;
      border-bottom: 1px solid #f3f4f6;
      font-size: 13px;
    }
    .report-table th {
      text-align: left;
      color: #6b7280;
      font-weight: 600;
    }
    @media (max-width: 900px) {
      .report-charts {
        grid-template-columns: 1fr;
      }
    }

    /* ===== FLASH MESSAGE ===== */
    .flash-message {
      margin-bottom: 16px;
      padding: 10px 14px;
      border-radius: 8px;
      font-size: 14px;
    }
    .flash-success {
      background: #ecfdf3;
      color: #166534;
      border: 1px solid #bbf7d0;
    }
    .flash-error {
      background: #fef2f2;
      color: #b91c1c;
      border: 1px solid #fecaca;
    }

    /* ===== CHAT BOX ===== */
    .chat-wrapper {
      display: grid;
      grid-template-columns: 260px minmax(0, 1fr);
      gap: 16px;
      align-items: stretch;
    }
    .chat-sidebar {
      background: #ffffff;
      border-radius: 14px;
      box-shadow: 0 10px 25px rgba(0,0,0,0.05);
      padding: 10px 10px 12px;
      display: flex;
      flex-direction: column;
      max-height: 520px;
      overflow: hidden;
    }
    .chat-sidebar-header {
      font-size: 14px;
      font-weight: 600;
      margin-bottom: 8px;
      display: flex;
      justify-content: space-between;
      align-items: center;
    }
    .chat-conversation-list {
      flex: 1;
      overflow-y: auto;
      margin-top: 4px;
    }
    .chat-conversation-item {
      padding: 8px 10px;
      border-radius: 10px;
      cursor: pointer;
      margin-bottom: 4px;
      transition: background .15s, transform .1s;
      display: flex;
      flex-direction: column;
      gap: 2px;
    }
    .chat-conversation-item:hover {
      background: #f3f4f6;
      transform: translateY(-1px);
    }
    .chat-conversation-item.active {
      background: #2563eb;
      color: #ffffff;
    }
    .chat-conversation-item .chat-conv-name {
      font-size: 14px;
      font-weight: 600;
    }
    .chat-conversation-item .chat-conv-last {
      font-size: 12px;
      opacity: 0.9;
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
    }
    .chat-conversation-item .chat-conv-meta {
      display: flex;
      justify-content: space-between;
      align-items: center;
      font-size: 11px;
      margin-top: 2px;
    }
    .chat-unread-badge {
      background: #ef4444;
      color: #ffffff;
      border-radius: 999px;
      padding: 1px 6px;
      font-size: 11px;
      font-weight: 600;
    }

    .chat-main {
      background: #ffffff;
      border-radius: 14px;
      box-shadow: 0 10px 25px rgba(0,0,0,0.05);
      padding: 10px 12px 12px;
      display: flex;
      flex-direction: column;
      max-height: 520px;
      overflow: hidden;
    }
    .chat-main-header {
      font-size: 14px;
      font-weight: 600;
      margin-bottom: 6px;
      border-bottom: 1px solid #f3f4f6;
      padding-bottom: 6px;
    }
    .chat-messages-list {
      flex: 1;
      overflow-y: auto;
      overflow-x: hidden;
      padding: 6px 2px;
      display: flex;
      flex-direction: column;
      gap: 6px;
      max-height: 360px; /* giới hạn chiều cao để scrollbar hiện rõ */
      scrollbar-width: thin;
      scrollbar-color: #9ca3af #f3f4f6;
    }
    .chat-messages-list::-webkit-scrollbar {
      width: 6px;
    }
    .chat-messages-list::-webkit-scrollbar-track {
      background: #f3f4f6;
      border-radius: 999px;
    }
    .chat-messages-list::-webkit-scrollbar-thumb {
      background: #9ca3af;
      border-radius: 999px;
    }

    /* hàng chứa avatar + bubble */
    .chat-msg-row {
      display: flex;
      align-items: flex-end;
      gap: 6px;
    }
    .chat-msg-row.them {
      justify-content: flex-start;
    }
    .chat-msg-row.me {
      justify-content: flex-end;
    }

    .chat-avatar {
      width: 32px;
      height: 32px;
      border-radius: 999px;
      background: #e5e7eb;
      display: flex;
      align-items: center;
      justify-content: center;
      flex-shrink: 0;
      font-size: 14px;
      color: #4b5563;
    }

    .chat-msg {
      max-width: 70%;
      padding: 6px 10px;
      border-radius: 14px;
      font-size: 13px;
      line-height: 1.4;
      word-wrap: break-word;
      word-break: break-word;
    }
    .chat-msg-them {
      background: #f3f4f6;
    }
    .chat-msg-me {
      background: #2563eb;
      color: #ffffff;
    }

    .chat-msg-meta {
      font-size: 10px;
      opacity: 0.7;
      margin-top: 2px;
    }
    .chat-reply-form {
      border-top: 1px solid #f3f4f6;
      padding-top: 6px;
      margin-top: 6px;
      display: flex;
      flex-direction: column;
      gap: 6px;
    }
    .chat-reply-form textarea {
      width: 100%;
      min-height: 60px;
      resize: vertical;
      border-radius: 8px;
      border: 1px solid #d1d5db;
      padding: 6px 8px;
      font-size: 13px;
      font-family: inherit;
    }
    .chat-reply-actions {
      display: flex;
      justify-content: flex-end;
      gap: 6px;
    }
    .chat-empty-state {
      flex: 1;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 13px;
      color: #9ca3af;
      text-align: center;
      padding: 10px;
    }
    @media (max-width: 900px) {
      .chat-wrapper {
        grid-template-columns: 1fr;
      }
      .chat-sidebar {
        max-height: none;
      }
      .chat-main {
        max-height: none;
      }
    }

    /* ===== CARD ICON + ACTIVE STATE ===== */
    .cards {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
      gap: 16px;
      margin-top: 20px;
    }

    .card {
      display: flex;
      align-items: center;
      gap: 12px;
      background: #ffffff;
      border-radius: 14px;
      padding: 12px 14px;
      box-shadow: 0 8px 20px rgba(0,0,0,0.04);
      transition: background .15s, color .15s, transform .1s, box-shadow .1s;
    }

    .card:hover {
      transform: translateY(-2px);
      box-shadow: 0 12px 28px rgba(0,0,0,0.06);
    }

    .card-icon {
      width: 40px;
      height: 40px;
      border-radius: 999px;
      background: #f3f4f6;
      display: flex;
      align-items: center;
      justify-content: center;
      flex-shrink: 0;
    }

    .card-icon i {
      font-size: 18px;
      color: #111827;
    }

    .card-content h3 {
      margin: 0;
      font-size: 15px;
    }

    .card-content p {
      margin: 2px 0 0;
      font-size: 13px;
      color: #6b7280;
    }

    .card.card-active {
      background: linear-gradient(135deg, #1f2937, #020617);
      color: #ffffff;
    }

    .card.card-active .card-icon {
      background: rgba(255,255,255,0.1);
    }

    .card.card-active .card-icon i {
      color: #f9fafb;
    }

    .card.card-active h3,
    .card.card-active p {
      color: #f9fafb;
    }
  </style>
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
  <div class="dashboard">
    <aside class="sidebar">
      <a href="trangchu.php">
        <div class="logo"><img src="image/anh1.png" alt="Logo"></div>
      </a>
      <ul class="menu">
        <li class="menu-item active" data-page="dashboard">
          <i class="fa-solid fa-gauge"></i>
          <span>Dashboard</span>
        </li>
        <li class="menu-item" data-page="sanpham">
          <i class="fa-solid fa-box-open"></i>
          <span>Quản lý sản phẩm</span>
        </li>
        <li class="menu-item" data-page="danhmuc">
          <i class="fa-solid fa-layer-group"></i>
          <span>Quản lý danh mục</span>
        </li>
        <li class="menu-item" data-page="donhang">
          <i class="fa-solid fa-receipt"></i>
          <span>Quản lý đơn hàng</span>
        </li>


<li class="menu-item has-dropdown" data-page="khuyenmai">
  <div class="menu-label">
    <i class="fa-solid fa-tags"></i>
    <span>Quản lý khuyến mãi</span>
    <i class="fa-solid fa-chevron-down arrow"></i>
  </div>

  <ul class="submenu">
<li class="submenu-item" data-page="khuyenmai_sanpham">
      <i class="fa-solid fa-box-open"></i>
      <span>Khuyến mãi sản phẩm</span>
    </li>

<li class="submenu-item" data-page="khuyenmai_le">
      <i class="fa-solid fa-gift"></i>
      <span>Khuyến mãi ngày lễ</span>
    </li>
  </ul>
</li>

<!-- ===== KHO HÀNG ===== -->
<li class="menu-item has-dropdown" data-page="khohang_group">
  <div class="menu-label">
    <i class="fa-solid fa-warehouse"></i>
    <span>Quản lý kho hàng</span>
    <i class="fa-solid fa-chevron-down arrow"></i>
  </div>

  <ul class="submenu">
<li class="submenu-item" data-page="phieunhap">
      <i class="fa-solid fa-truck-ramp-box"></i>
      <span>Phiếu nhập hàng</span>
    </li>

<li class="submenu-item" data-page="phieuxuat">
      <i class="fa-solid fa-truck-fast"></i>
      <span>Phiếu xuất hàng</span>
    </li>

<li class="submenu-item" data-page="tonkho">
      <i class="fa-solid fa-boxes-stacked"></i>
      <span>Hàng tồn kho</span>
    </li>
  </ul>
</li>


        <?php if ($userRole === 'admin'): ?>
          <li class="menu-item" data-page="taikhoan">
            <i class="fa-solid fa-users-gear"></i>
            <span>Quản lý tài khoản</span>
          </li>
        <?php endif; ?>
        <li class="menu-item" data-page="baocao">
          <i class="fa-solid fa-chart-line"></i>
          <span>Báo cáo</span>
        </li>
        <li class="menu-item" data-page="chat">
          <i class="fa-solid fa-comments"></i>
          <span>Quản lý chat box</span>
        </li>
      </ul>
    </aside>

    <main class="main-content" id="main-content">
      <?php if (!empty($flash_message)): ?>
        <div class="flash-message <?= $flash_type === 'success' ? 'flash-success' : 'flash-error' ?>">
          <?= $flash_message ?>
        </div>
      <?php endif; ?>

      <header>
        <div style="display:flex; align-items:center; gap:10px;">
          <button type="button" class="btn btn-secondary btn-sm" onclick="window.history.back();">
            ← Quay lại
          </button>

          <div class="page-title-wrap">
            <i id="page-icon" class="fa-solid fa-gauge page-icon"></i>
            <h1 id="page-title">Dashboard</h1>
          </div>
        </div>
        <div class="user-info"><?= htmlspecialchars($userRole === 'admin' ? 'Admin' : 'Nhân viên') ?></div>
      </header>

      <section class="cards">


        <div class="card" data-page="donhang">
          <div class="card-icon">
            <i class="fa-solid fa-receipt"></i>
          </div>
          <div class="card-content">
            <h3>Đơn hàng</h3>
            <p><?= $orders_total ?> đơn hàng</p>
          </div>
        </div>

        <div class="card" data-page="khuyenmai">
          <div class="card-icon">
            <i class="fa-solid fa-tags"></i>
          </div>
          <div class="card-content">
            <h3>Khuyến mãi</h3>
            <p><?= $km_total ?> mã khuyến mãi</p>
          </div>
        </div>

        <div class="card" data-page="nhaphang">
          <div class="card-icon">
            <i class="fa-solid fa-truck-loading"></i>
          </div>
          <div class="card-content">
            <h3>Nhập hàng</h3>
            <p><?= $nhaphang_total ?> phiếu nhập</p>
          </div>
        </div>

        <?php if ($userRole === 'admin'): ?>
          <div class="card" data-page="taikhoan">
            <div class="card-icon">
              <i class="fa-solid fa-users-gear"></i>
            </div>
            <div class="card-content">
              <h3>Tài khoản</h3>
              <p><?= $tk_total ?> tài khoản</p>
            </div>
          </div>
        <?php endif; ?>

        <div class="card" data-page="baocao">
          <div class="card-icon">
            <i class="fa-solid fa-chart-line"></i>
          </div>
          <div class="card-content">
            <h3>Doanh thu</h3>
            <p><?= number_format($revenue_total,0,',','.') ?>₫</p>
          </div>
        </div>
      </section>

      <section id="content-area"></section>
    </main>
  </div>

<script>
// Lưu role hiện tại để kiểm tra quyền
const currentUserRole = '<?= $userRole ?>';
const isAdmin = currentUserRole === 'admin';
const isStaff = currentUserRole === 'staff';

const cards       = document.querySelectorAll('.card');
const contentArea = document.getElementById('content-area');
const menuItems   = document.querySelectorAll('.menu-item');
const pageTitleEl = document.getElementById('page-title');
const pageIconEl  = document.getElementById('page-icon');

// Biến phân trang cho các mục
let currentProductPage = 1;
const productsPerPage = 5;

let currentOrderPage = 1;
const ordersPerPage = 5;

let currentKhuyenMaiPage = 1;
const khuyenMaiPerPage = 5;
let currentKmSPPage = 1;
const kmSPPerPage = 5;

let currentKmLePage = 1;
const kmLePerPage = 5;


let currentTaiKhoanPage = 1;
const taiKhoanPerPage = 5;

let currentDanhMucPage = 1;
const danhmucPerPage = 5;
let currentExportPage = 1;
const exportPerPage = 5;
window.filteredXuatData = null;



let currentImportPage = 1;
const importPerPage = 5;
window.filteredNhapData = null; // dữ liệu nhập hàng sau khi lọc theo tên sản phẩm

// BIỂU ĐỒ BÁO CÁO
let revenueChartInstance = null;
let statusChartInstance  = null;

function clearActiveMenu() {
  menuItems.forEach(item => item.classList.remove('active'));
}

function setPageTitle(page) {
  let title = 'Dashboard';
  let iconClass = 'fa-solid fa-gauge';

  if (page === 'sanpham') {
    title = 'Quản lý sản phẩm';
    iconClass = 'fa-solid fa-box-open';
  }
  if (page === 'danhmuc') {
    title = 'Quản lý danh mục';
    iconClass = 'fa-solid fa-layer-group';
  }
  if (page === 'donhang') {
    title = 'Quản lý đơn hàng';
    iconClass = 'fa-solid fa-receipt';
  }
if (page === 'khuyenmai') {
  title = 'Quản lý khuyến mãi';
  iconClass = 'fa-solid fa-tags';
}
if (page === 'khuyenmai_sanpham') {
  title = 'Khuyến mãi sản phẩm';
  iconClass = 'fa-solid fa-box-open';
}
if (page === 'khuyenmai_le') {
  title = 'Khuyến mãi ngày lễ';
  iconClass = 'fa-solid fa-gift';
}

if (page === 'khuyenmai_le') {
  title = 'Khuyến mãi ngày lễ';
  iconClass = 'fa-solid fa-gift';

  }
  if (page === 'nhaphang') {
    title = 'Quản lý nhập hàng';
    iconClass = 'fa-solid fa-truck-loading';
  }
  if (page === 'taikhoan') {
    title = 'Quản lý tài khoản';
    iconClass = 'fa-solid fa-users-gear';
  }
  if (page === 'baocao') {
    title = 'Báo cáo';
    iconClass = 'fa-solid fa-chart-line';
  }
  if (page === 'themsanpham') {
    title = 'Thêm sản phẩm';
    iconClass = 'fa-solid fa-plus';
  }
  if (page === 'themdanhmuc') {
    title = 'Thêm danh mục';
    iconClass = 'fa-solid fa-plus';
  }
  if (page === 'suadanhmuc') {
    title = 'Sửa danh mục';
    iconClass = 'fa-solid fa-pen-to-square';
  }
  if (page === 'themkhuyenmai') {
    title = 'Thêm khuyến mãi';
    iconClass = 'fa-solid fa-plus';
  }
  if (page === 'suataikhoan') {
    title = 'Sửa tài khoản';
    iconClass = 'fa-solid fa-user-pen';
  }
  if (page === 'themnhaphang') {
    title = 'Thêm phiếu nhập hàng';
    iconClass = 'fa-solid fa-file-circle-plus';
  }
  if (page === 'phieunhap') {
  title = 'Phiếu nhập hàng';
  iconClass = 'fa-solid fa-truck-ramp-box';
}
if (page === 'phieuxuat') {
  title = 'Phiếu xuất hàng';
  iconClass = 'fa-solid fa-truck-fast';
}
if (page === 'tonkho') {
  title = 'Hàng tồn kho';
  iconClass = 'fa-solid fa-boxes-stacked';
}

  if (page === 'chat') {
    title = 'Quản lý chat box';
    iconClass = 'fa-solid fa-comments';
  }

  if (pageTitleEl) {
    pageTitleEl.textContent = title;
  }
  if (pageIconEl) {
    pageIconEl.className = iconClass + ' page-icon';
  }
}

// Đánh dấu card tương ứng với trang hiện tại
function setActiveCard(page) {
  document.querySelectorAll('.card').forEach(card => {
    if (card.getAttribute('data-page') === page) {
      card.classList.add('card-active');
    } else {
      card.classList.remove('card-active');
    }
  });
}

cards.forEach(card => {
  card.addEventListener('click', () => {
    const page = card.getAttribute('data-page');
    clearActiveMenu();
    document.querySelector(`.menu-item[data-page="${page}"]`)?.classList.add('active');
    setPageTitle(page);
    setActiveCard(page);
    loadPage(page);
  });
});

menuItems.forEach(item => {
  item.addEventListener('click', () => {
    const page = item.getAttribute('data-page');
    clearActiveMenu();
    item.classList.add('active');
    setPageTitle(page);
    setActiveCard(page);
    loadPage(page);
  });
});

// ===== DATA từ PHP =====
const sanphamData   = <?= json_encode($sanpham) ?>;
const donhangData   = <?= json_encode($donhang) ?>;
const khuyenmaiData = <?= json_encode($khuyenmai) ?>;
const kmSanPhamData = <?= json_encode($km_sanpham) ?>;
const kmNgayLeData  = <?= json_encode($km_ngayle) ?>;

const revenueByDay  = <?= json_encode($revenue_by_day) ?>;
const taikhoanData  = <?= json_encode($taikhoan) ?>;
const danhmucData   = <?= json_encode($danhmuc) ?>;
const nhaphangData  = <?= json_encode($nhaphang) ?>;
const phieuxuatData = <?= json_encode($phieuxuat) ?>;
const tonkhoAgingData = <?= json_encode($tonkhoAging) ?>;

const chatMessagesData = <?= json_encode($chat_messages) ?>;

// Tổng số dùng cho báo cáo
const productsTotal = <?= (int)$products_total ?>;
const ordersTotal   = <?= (int)$orders_total ?>;
const revenueTotal  = <?= (float)($revenue_total ?? 0) ?>;
const importTotal   = <?= (float)($import_total ?? 0) ?>;
const profitTotal   = <?= (float)($profit_total ?? 0) ?>;

// Map trạng thái đơn
const statusMap = {
  pending:   'Chờ xử lý',
  paid:      'Đã thanh toán',
  shipping:  'Đang giao',
  completed: 'Hoàn tất',
  cancelled: 'Đã hủy'
};

function loadPage(page) {
  // Không cho staff truy cập các chức năng admin-only (tạo / sửa lớn)
  if ((page === 'themsanpham' || page === 'themkhuyenmai' || page === 'themdanhmuc' || page === 'suadanhmuc' || page === 'taikhoan' || page === 'themnhaphang') && !isAdmin) {
    alert('Bạn không có quyền truy cập chức năng này.');
    loadPage('dashboard');
    return;
  }

  if (page === 'dashboard') {
    contentArea.innerHTML = `
      <h2>Chào mừng bạn đến với trang quản trị Danisa</h2>
      <p>Chọn một chức năng ở menu bên trái để quản lý sản phẩm, đơn hàng, khuyến mãi, nhập hàng và báo cáo.</p>
    `;
  }

  // ==== THÊM SẢN PHẨM ====
  else if (page === 'themsanpham') {
    contentArea.innerHTML = `
      <div class="table-header">
        <h2>Thêm sản phẩm</h2>
      </div>
      <form id="formAddProduct" class="add-product-form"
            enctype="multipart/form-data"
            method="POST" action="xulythemsanpham.php">
        <div class="form-group">
          <label>ID sản phẩm</label>
          <input type="text" name="id" required>
        </div>
        <div class="form-group">
          <label>Tên sản phẩm</label>
          <input type="text" name="name" required>
        </div>
        <div class="form-group">
          <label>Danh mục</label>
          <select name="category">
const categoryLabel = {
  butter_cookie: 'BÁNH QUY BƠ',
  butter_traditional: 'BÁNH QUY BƠ TRUYỀN THỐNG',
  butter_choco_cashew: 'Bánh Quy Bơ Sô-Cô-La Hạt Điều',
  butter_raisin: 'Bánh Quy Bơ Nho Khô',
  filled_cookie: 'Bánh Quy Có Nhân',
  pineapple_filled: 'Bánh Quy Nhân Dứa',
  other: 'Khác'
};
          </select>
        </div>

        <div class="form-group">
          <label>Giá nhập</label>
          <input type="number" name="import_price" min="0" required>
        </div>

        <div class="form-group">
          <label>Giá bán</label>
          <input type="number" name="sale_price" min="0" required>
        </div>

        <div class="form-group">
          <label>Giá khuyến mãi</label>
          <input type="number" name="price" min="0">
        </div>

        <div class="form-group">
          <label>Số lượng</label>
          <input type="number" name="quantity" min="0" required>
        </div>
        <div class="form-group">
          <label>Ảnh sản phẩm</label>
          <input type="file" name="image" accept="image/*" required>
        </div>
        <div class="form-group" style="grid-column:1/-1;">
          <label>Mô tả</label>
          <textarea name="description"></textarea>
        </div>
        <div class="form-actions">
          <button type="button" class="btn btn-secondary" onclick="loadPage('sanpham')">Hủy</button>
          <button type="submit" class="btn btn-primary">Thêm sản phẩm</button>
        </div>
      </form>
    `;
  }

  else if (page === 'sanpham') {
    currentProductPage = 1;
    renderProductTable();
  }

  else if (page === 'danhmuc') {
    currentDanhMucPage = 1;
    renderDanhMucTable();
  }

  else if (page === 'themdanhmuc') {
    contentArea.innerHTML = `
      <div class="table-header">
        <h2>Thêm danh mục</h2>
      </div>
      <form id="formAddDanhMuc" class="add-product-form"
            method="POST" action="danhmuc_store.php">
        <div class="form-group">
          <label>Tên danh mục *</label>
          <input type="text" name="name" required placeholder="VD: Bánh Quy Bơ">
        </div>
        <div class="form-group" style="grid-column:1/-1;">
          <label>Mô tả</label>
          <textarea name="description" placeholder="Mô tả về danh mục..."></textarea>
        </div>
        <div class="form-actions">
          <button type="button" class="btn btn-secondary" onclick="loadPage('danhmuc')">Hủy</button>
          <button type="submit" class="btn btn-primary">Thêm danh mục</button>
        </div>
      </form>
    `;
  }

  else if (page === 'donhang') {
    currentOrderPage = 1;
    renderOrderTableWithPagination();
  }

  else if (page === 'khuyenmai') {
    currentKhuyenMaiPage = 1;
    renderKhuyenMaiTableWithPagination();
  }
else if (page === 'khuyenmai_sanpham') {
  currentKmSPPage = 1;
  renderKhuyenMaiSanPhamList();   // vào trang -> hiện danh sách
}
else if (page === 'khuyenmai_le') {
  currentKmLePage = 1;
  renderKhuyenMaiNgayLeList();    // vào trang -> hiện danh sách
}
  else if (page === 'khohang_group') {
    // mặc định mở Phiếu nhập
    clearActiveMenu();
    document.querySelector(`.submenu-item[data-page="phieunhap"]`)?.classList.add('active');
    setPageTitle('phieunhap');
    setActiveCard('nhaphang'); // hoặc bỏ dòng này nếu bạn không muốn active card
    loadPage('phieunhap');
    return;
  }
  else if (page === 'phieunhap') {
    currentImportPage = 1;
    window.filteredNhapData = null;
    renderNhapHangTable(); // hiển thị danh sách phiếu nhập
  }

  else if (page === 'phieuxuat') {
    currentExportPage = 1;
    window.filteredXuatData = null;
    renderPhieuXuatTable(); // hiển thị danh sách phiếu xuất
  }

  else if (page === 'tonkho') {
    renderTonKhoAging(); // hiển thị tồn kho lâu chưa bán
  }


  else if (page === 'themkhuyenmai') {
    contentArea.innerHTML = `
      <div class="table-header">
        <h2>Thêm khuyến mãi mới</h2>
      </div>
      <form class="add-product-form" method="POST" action="khuyenmai_store.php">
        <div class="form-group">
          <label>Mã khuyến mãi *</label>
          <input type="text" name="code" required placeholder="VD: DANISA10">
        </div>
        <div class="form-group" style="grid-column:1/-1;">
          <label>Mô tả</label>
          <textarea name="description" rows="2" placeholder="Mô tả ngắn..."></textarea>
        </div>
        <div class="form-group">
          <label>Loại giảm giá *</label>
          <select name="discount_type" required>
            <option value="percent">Giảm theo %</option>
            <option value="amount">Giảm số tiền cố định</option>
          </select>
        </div>
        <div class="form-group">
          <label>Giá trị giảm *</label>
          <input type="number" name="discount_value" min="1" required placeholder="VD: 10 (10%) hoặc 50000 (50.000đ)">
        </div>
        <div class="form-group">
          <label>Đơn hàng tối thiểu (₫)</label>
          <input type="number" name="min_order_value" min="0" value="0">
        </div>
        <div class="form-group">
          <label>Số lượt sử dụng tối đa</label>
          <input type="number" name="quantity" min="0" value="0">
        </div>
        <div class="form-group">
          <label>Ngày bắt đầu</label>
          <input type="date" name="start_date">
        </div>
        <div class="form-group">
          <label>Ngày kết thúc</label>
          <input type="date" name="end_date">
        </div>
        <div class="form-group" style="grid-column:1/-1;">
          <label>Cấp riêng cho khách hàng (username / email)</label>
          <input type="text" name="assigned_user" placeholder="VD: robert@gmail.com hoặc robert123">
        </div>
        <div class="form-group">
          <label>Trạng thái</label>
          <select name="status">
            <option value="active">Đang hoạt động</option>
            <option value="inactive">Ngừng</option>
          </select>
        </div>
        <div class="form-actions">
          <button type="button" class="btn btn-secondary" onclick="loadPage('khuyenmai')">Hủy</button>
          <button type="submit" class="btn btn-primary">Lưu khuyến mãi</button>
        </div>
      </form>
    `;
  }

  else if (page === 'nhaphang') {
    currentImportPage = 1;
    window.filteredNhapData = null;
    renderNhapHangTable();
  }

  else if (page === 'themnhaphang') {
    const options = sanphamData.map(sp =>
      `<option value="${sp.id}">${escapeHtml(sp.name || '')}</option>`
    ).join('');

    contentArea.innerHTML = `
      <div class="table-header">
        <h2>Thêm phiếu nhập hàng</h2>
      </div>
      <form class="add-product-form" method="POST" action="nhaphang_store.php">
        <div class="form-group">
          <label>Sản phẩm *</label>
          <select name="sanpham_id" required>
            <option value="">-- Chọn sản phẩm --</option>
            ${options}
          </select>
        </div>
        <div class="form-group">
          <label>Số lượng nhập *</label>
          <input type="number" name="so_luong" min="1" required>
        </div>
        <div class="form-group">
          <label>Giá nhập (₫) *</label>
          <input type="number" name="import_price" min="0" required>
        </div>
        <div class="form-group" style="grid-column:1/-1;">
          <label>Ghi chú</label>
          <textarea name="ghi_chu" placeholder="Ví dụ: nhập thêm dịp Tết..."></textarea>
        </div>
        <div class="form-actions">
          <button type="button" class="btn btn-secondary" onclick="loadPage('nhaphang')">Hủy</button>
          <button type="submit" class="btn btn-primary">Lưu phiếu nhập</button>
        </div>
      </form>
    `;
  }

  else if (page === 'taikhoan') {
    currentTaiKhoanPage = 1;
    renderTaiKhoanTableWithPagination();
  }

  // ====== BÁO CÁO ======
  else if (page === 'baocao') {
    contentArea.innerHTML = `
      <div class="table-header">
        <h2>Báo cáo tổng quan</h2>
      </div>
      <div id="baocao-container">
        <div class="report-cards">
          <div class="report-card">
            <div class="report-card-title">Tổng đơn hàng</div>
            <div class="report-card-value" id="reportTotalOrders">0</div>
            <div class="report-card-sub">Tất cả trạng thái</div>
          </div>

          <div class="report-card">
            <div class="report-card-title">Tổng doanh thu</div>
            <div class="report-card-value" id="reportTotalRevenue">0₫</div>
            <div class="report-card-sub">Tính theo tất cả đơn</div>
          </div>

          <div class="report-card">
            <div class="report-card-title">Tổng tiền nhập hàng</div>
            <div class="report-card-value" id="reportTotalImport">0₫</div>
            <div class="report-card-sub">Σ (giá nhập × số lượng)</div>
          </div>

          <div class="report-card">
            <div class="report-card-title">Tổng lợi nhuận</div>
            <div class="report-card-value" id="reportTotalProfit">0₫</div>
            <div class="report-card-sub">Doanh thu - tiền nhập</div>
          </div>

          <div class="report-card">
            <div class="report-card-title">Giá trị đơn trung bình</div>
            <div class="report-card-value" id="reportAvgOrder">0₫</div>
            <div class="report-card-sub">Doanh thu / số đơn</div>
          </div>

          <div class="report-card">
            <div class="report-card-title">Số khách hàng</div>
            <div class="report-card-value" id="reportTotalCustomers">0</div>
            <div class="report-card-sub">Dựa trên tên khách trong đơn</div>
          </div>
        </div>

        <div class="report-charts">
          <div class="report-chart-item">
            <h3>Doanh thu theo ngày</h3>
            <canvas id="revenueChart" height="140"></canvas>
            <div id="revenueChartEmpty" class="report-chart-empty" style="display:none;">
              Chưa có dữ liệu doanh thu theo ngày. Hệ thống sẽ tự động hiển thị khi có đơn hàng mới.
            </div>
          </div>
          <div class="report-chart-item">
            <h3>Tỷ lệ trạng thái đơn hàng</h3>
            <canvas id="statusChart" height="140"></canvas>
            <div id="statusChartEmpty" class="report-chart-empty" style="display:none;">
              Chưa có đơn hàng nào để thống kê.
            </div>
          </div>
        </div>

        <div class="report-table">
          <h3>Bảng doanh thu theo ngày (tối đa 14 ngày gần nhất)</h3>
          <table>
            <thead>
              <tr>
                <th>Ngày</th>
                <th>Doanh thu (₫)</th>
              </tr>
            </thead>
            <tbody id="revenueByDayTableBody"></tbody>
          </table>
        </div>
      </div>
    `;
    renderBaoCao();
  }

  // ====== QUẢN LÝ CHAT BOX ======
  else if (page === 'chat') {
    renderChatPage();
  }
}

// ====== HÀM QUẢN LÝ DANH MỤC ======
function editDanhMuc(id) {
  const dm = danhmucData.find(cat => String(cat.id) === String(id));
  if (!dm) {
    alert('Không tìm thấy danh mục có ID ' + id);
    return;
  }

  const contentArea = document.getElementById('content-area');
  if (!contentArea) return;

  contentArea.innerHTML = `
    <div class="table-header">
      <h2>Chỉnh sửa danh mục</h2>
    </div>
    <form class="add-product-form"
          method="POST"
          action="danhmuc_update.php">
      <input type="hidden" name="id" value="${dm.id}">
      <input type="hidden" name="redirect" value="quanlyadmin.php?page=danhmuc">

      <div class="form-group">
        <label>ID danh mục</label>
        <input type="text" value="${dm.id}" disabled>
      </div>

      <div class="form-group">
        <label>Tên danh mục *</label>
        <input type="text" name="name" required value="${(dm.name || '').replace(/"/g, '&quot;')}">
      </div>

      <div class="form-group" style="grid-column:1/-1;">
        <label>Mô tả</label>
        <textarea name="description">${(dm.description || '').replace(/</g, '&lt;').replace(/>/g, '&gt;')}</textarea>
      </div>

      <div class="form-actions">
        <button type="button" class="btn btn-secondary" onclick="loadPage('danhmuc')">Hủy</button>
        <button type="submit" class="btn btn-primary">Lưu thay đổi</button>
      </div>
    </form>
  `;

  setPageTitle('suadanhmuc');
}

function deleteDanhMuc(id) {
  if (confirm('Bạn có chắc muốn xóa danh mục ID ' + id + '?')) {
    window.location.href = 'danhmuc_delete.php?id=' + id;
  }
}
function renderPhieuXuatTable() {
  const dataToUse  = window.filteredXuatData || (phieuxuatData || []);
  const totalRows  = dataToUse.length;
  const totalPages = Math.ceil(totalRows / exportPerPage) || 1;

  const startIndex = (currentExportPage - 1) * exportPerPage;
  const endIndex   = Math.min(startIndex + exportPerPage, totalRows);
  const current    = dataToUse.slice(startIndex, endIndex);

  let html = `
    <div class="table-header">
      <h2>Phiếu xuất hàng</h2>
    </div>

    <div class="filter-bar" style="margin-bottom:12px; display:flex; flex-wrap:wrap; gap:8px; align-items:flex-end;">
      <div>
        <label style="font-size:13px; display:block;">Lọc theo tên sản phẩm</label>
        <input type="text" id="filterXuatProductName"
          placeholder="Nhập tên sản phẩm..."
          style="padding:4px 8px; border-radius:6px; border:1px solid #d1d5db; min-width:220px;"
          onkeydown="if(event.key==='Enter'){event.preventDefault();applyXuatFilter();}">
      </div>
      <div style="display:flex; gap:6px;">
        <button type="button" class="btn btn-primary btn-sm" onclick="applyXuatFilter()">Lọc</button>
        <button type="button" class="btn btn-secondary btn-sm" onclick="resetXuatFilter()">Xóa lọc</button>
      </div>
    </div>

    <div style="margin-bottom: 12px; color: #666; font-size: 14px;">
      Hiển thị ${totalRows === 0 ? 0 : (startIndex + 1)}-${endIndex} trong tổng số ${totalRows} phiếu xuất
    </div>

    <table>
      <thead>
        <tr>
          <th>Mã đơn</th>
          <th>Ngày xuất</th>
          <th>Khách hàng</th>
          <th>Sản phẩm</th>
          <th>Số lượng</th>
          <th>Giá xuất</th>
          <th>Lý do</th>
          <th>Hành động</th>
        </tr>
      </thead>
      <tbody>
  `;

  if (!current.length) {
    html += `<tr><td colspan="8" style="text-align:center; padding:20px;">Chưa có phiếu xuất nào.</td></tr>`;
  } else {
    current.forEach(row => {
      const price = Number(row.export_price || 0);
      const qty   = Number(row.so_luong || 0);
      const orderId = Number(row.order_id || 0);

      html += `
        <tr>
          <td>#${escapeHtml(String(row.order_id ?? ''))}</td>
          <td>${formatDate(row.created_at)}</td>
          <td>${escapeHtml(row.customer_name || '—')}</td>
          <td>${escapeHtml(row.product_name || '')}</td>
          <td>${qty}</td>
          <td>${price.toLocaleString()}₫</td>
          <td>${escapeHtml(row.ly_do || '')}</td>
          <td>
            <button type="button" class="btn btn-sm btn-secondary" onclick="viewOrderDetail(${orderId})">
              Xem đơn
            </button>
          </td>
        </tr>
      `;
    });
  }

  html += `
      </tbody>
    </table>
  `;

  if (totalPages > 1) {
    html += `
      <div style="margin-top: 20px; display:flex; justify-content:center; gap:8px; flex-wrap:wrap;">
        <button type="button" class="btn btn-sm ${currentExportPage === 1 ? 'btn-secondary' : 'btn-primary'}"
          onclick="changeExportPage(${currentExportPage - 1})" ${currentExportPage === 1 ? 'disabled' : ''}>← Trước</button>
        <span class="page-info" style="align-self:center;">Trang ${currentExportPage}/${totalPages}</span>
        <button type="button" class="btn btn-sm ${currentExportPage === totalPages ? 'btn-secondary' : 'btn-primary'}"
          onclick="changeExportPage(${currentExportPage + 1})" ${currentExportPage === totalPages ? 'disabled' : ''}>Sau →</button>
      </div>
    `;
  }

  contentArea.innerHTML = html;
}

function changeExportPage(page) {
  const dataToUse  = window.filteredXuatData || (phieuxuatData || []);
  const totalPages = Math.ceil(dataToUse.length / exportPerPage) || 1;
  if (page >= 1 && page <= totalPages) {
    currentExportPage = page;
    renderPhieuXuatTable();
    contentArea.scrollIntoView({ behavior: 'smooth', block: 'start' });
  }
}

function applyXuatFilter() {
  const input = document.getElementById('filterXuatProductName');
  const keyword = (input?.value || '').trim().toLowerCase();
  currentExportPage = 1;

  if (!keyword) {
    window.filteredXuatData = null;
    renderPhieuXuatTable();
    return;
  }

  window.filteredXuatData = (phieuxuatData || []).filter(r =>
    (r.product_name || '').toLowerCase().includes(keyword)
  );
  renderPhieuXuatTable();
}

function resetXuatFilter() {
  const input = document.getElementById('filterXuatProductName');
  if (input) input.value = '';
  window.filteredXuatData = null;
  currentExportPage = 1;
  renderPhieuXuatTable();
}
function renderPhieuXuatForm() {
  const options = (sanphamData || []).map(sp => {
    const qty = Math.max(0, Number(sp.quantity) || 0);
    return `<option value="${sp.id}">${escapeHtml(sp.name || '')} (Tồn: ${qty})</option>`;
  }).join('');

  contentArea.innerHTML = `
    <div class="table-header">
      <h2>Tạo phiếu xuất</h2>
    </div>

    <form class="add-product-form" method="POST" action="phieuxuat_store.php">
      <div class="form-group">
        <label>Sản phẩm *</label>
        <select name="sanpham_id" required>
          <option value="">-- Chọn sản phẩm --</option>
          ${options}
        </select>
      </div>

      <div class="form-group">
        <label>Số lượng xuất *</label>
        <input type="number" name="so_luong" min="1" required>
      </div>

      <div class="form-group">
        <label>Giá xuất (tuỳ chọn)</label>
        <input type="number" name="export_price" min="0" value="0">
      </div>

      <div class="form-group">
        <label>Lý do</label>
        <input type="text" name="ly_do" placeholder="VD: Hư hỏng / chuyển kho / tặng...">
      </div>

      <div class="form-group" style="grid-column:1/-1;">
        <label>Ghi chú</label>
        <textarea name="ghi_chu"></textarea>
      </div>

      <div class="form-actions">
        <button type="button" class="btn btn-secondary" onclick="loadPage('phieuxuat')">Hủy</button>
        <button type="submit" class="btn btn-primary">Lưu phiếu xuất</button>
      </div>
    </form>
  `;
}
function renderTonKhoAging() {
  const data = (tonkhoAgingData || []).map(row => {
    const qty = Math.max(0, Number(row.quantity) || 0);
    const anchor = row.last_sold_at || row.first_import_at || row.anchor_date || null;

    let daysUnsold = null;
    if (anchor) {
      const d = new Date(anchor);
      if (!isNaN(d.getTime())) {
        const now = new Date();
        const diff = Math.floor((now - d) / (1000*60*60*24));
        daysUnsold = Math.max(0, diff);
      }
    }
    return {
      ...row,
      quantity: qty,
      days_unsold: daysUnsold
    };
  });

  contentArea.innerHTML = `
    <div class="table-header">
      <h2>Hàng tồn kho lâu chưa bán</h2>
    </div>

    <div style="display:flex; flex-wrap:wrap; gap:10px; align-items:flex-end; margin-bottom:12px;">
      <div>
        <label style="font-size:13px; display:block;">Tối thiểu (ngày chưa bán)</label>
        <input type="number" id="agingDays" value="60" min="0"
          style="padding:4px 8px; border-radius:6px; border:1px solid #d1d5db; width:120px;">
      </div>

      <div>
        <label style="font-size:13px; display:block;">Từ khóa</label>
        <input type="text" id="agingKeyword" placeholder="Tên sản phẩm..."
          style="padding:4px 8px; border-radius:6px; border:1px solid #d1d5db; min-width:220px;">
      </div>

      <div>
        <label style="font-size:13px; display:block;">Chỉ hàng còn tồn</label>
        <select id="agingInStock" style="padding:4px 8px; border-radius:6px; border:1px solid #d1d5db;">
          <option value="1" selected>Có</option>
          <option value="0">Tất cả</option>
        </select>
      </div>

      <div style="display:flex; gap:6px;">
        <button type="button" class="btn btn-secondary btn-sm" onclick="setAgingQuick(30)">30d</button>
        <button type="button" class="btn btn-secondary btn-sm" onclick="setAgingQuick(60)">60d</button>
        <button type="button" class="btn btn-secondary btn-sm" onclick="setAgingQuick(90)">90d</button>
        <button type="button" class="btn btn-secondary btn-sm" onclick="setAgingQuick(180)">180d</button>
        <button type="button" class="btn btn-primary btn-sm" onclick="applyTonKhoAging()">Lọc</button>
      </div>
    </div>

    <div id="tonkhoAgingTableWrap"></div>
  `;

  window._tonkhoAgingCache = data;
  applyTonKhoAging();
}

function setAgingQuick(n){
  const el = document.getElementById('agingDays');
  if(el) el.value = n;
  applyTonKhoAging();
}

function applyTonKhoAging(){
  const daysMin = Number(document.getElementById('agingDays')?.value || 0);
  const kw = (document.getElementById('agingKeyword')?.value || '').trim().toLowerCase();
  const inStockOnly = String(document.getElementById('agingInStock')?.value || '1') === '1';

  let rows = (window._tonkhoAgingCache || []);

  if (inStockOnly) rows = rows.filter(r => (Number(r.quantity)||0) > 0);
  if (kw) rows = rows.filter(r => (r.name || '').toLowerCase().includes(kw));

  rows = rows.filter(r => {
    // nếu chưa có ngày bán/nhập để tính thì coi là "rất lâu" -> cho lên danh sách
    if (r.days_unsold === null || r.days_unsold === undefined) return true;
    return r.days_unsold >= daysMin;
  });

  // sort theo ngày chưa bán giảm dần
  rows.sort((a,b) => (Number(b.days_unsold)||0) - (Number(a.days_unsold)||0));

  const wrap = document.getElementById('tonkhoAgingTableWrap');
  if(!wrap) return;

  let html = `
    <div style="margin-bottom:10px; color:#6b7280; font-size:13px;">
      Tìm thấy <strong>${rows.length}</strong> sản phẩm phù hợp.
    </div>

    <table>
      <thead>
        <tr>
          <th>ID</th>
          <th>Sản phẩm</th>
          <th>Tồn</th>
          <th>Giá nhập</th>
          <th>Giá trị tồn (giá nhập)</th>
          <th>Lần bán gần nhất</th>
          <th>Ngày chưa bán</th>
        </tr>
      </thead>
      <tbody>
  `;

  if(!rows.length){
    html += `<tr><td colspan="7" style="text-align:center; padding:18px;">Không có sản phẩm thỏa điều kiện.</td></tr>`;
  } else {
    rows.forEach(r => {
      const qty = Math.max(0, Number(r.quantity)||0);
      const importPrice = Number(r.import_price||0);
      const invValue = qty * importPrice;

      html += `
        <tr>
          <td>${escapeHtml(String(r.id))}</td>
          <td><strong>${escapeHtml(r.name || '')}</strong></td>
          <td>${qty}</td>
          <td>${importPrice.toLocaleString()}₫</td>
          <td>${invValue.toLocaleString()}₫</td>
          <td>${r.last_sold_at ? formatDateTime(r.last_sold_at) : '<span style="color:#9ca3af;">Chưa từng bán</span>'}</td>
          <td>${(r.days_unsold === null || r.days_unsold === undefined) ? '—' : (Number(r.days_unsold).toLocaleString() + ' ngày')}</td>
        </tr>
      `;
    });
  }

  html += `</tbody></table>`;
  wrap.innerHTML = html;
}


// ===== HÀM PHÂN TRANG DANH MỤC =====
function renderDanhMucTable() {
  const totalDanhMuc = danhmucData.length;
  const totalPages = Math.ceil(totalDanhMuc / danhmucPerPage);

  const startIndex = (currentDanhMucPage - 1) * danhmucPerPage;
  const endIndex = Math.min(startIndex + danhmucPerPage, totalDanhMuc);
  const currentDanhMuc = danhmucData.slice(startIndex, endIndex);

  let html = `
    <div class="table-header">
      <h2>Quản lý danh mục</h2>
      ${isAdmin ? '<button type="button" class="btn btn-primary" onclick="loadPage(\'themdanhmuc\')">+ Thêm danh mục</button>' : ''}
    </div>
    <div style="margin-bottom: 12px; color: #666; font-size: 14px;">
      Hiển thị ${startIndex + 1}-${endIndex} trong tổng số ${totalDanhMuc} danh mục
    </div>
    <table>
      <thead>
        <tr>
          <th>ID</th>
          <th>Tên danh mục</th>
          <th>Mô tả</th>
          <th>Ngày tạo</th>
          <th>Ngày khuyến mãi</th>
          <th>Sản phẩm khuyến mãi</th>
          <th>Hành động</th>
        </tr>
      </thead>
      <tbody>
  `;

  if (currentDanhMuc.length === 0) {
    html += `
      <tr>
        <td colspan="7" style="text-align:center; padding:20px;">Không có danh mục nào.</td>
      </tr>
    `;
  } else {
    currentDanhMuc.forEach(item => {
      const name = escapeHtml(item.name || '');
      const desc = escapeHtml((item.description || '').substring(0, 50)) + ((item.description || '').length > 50 ? '...' : '');
      const promotionDates = escapeHtml(item.promotion_dates || '') || '—';
      const promotionProducts = escapeHtml(item.promotion_products || '') || '—';

      html += `
        <tr>
          <td>${item.id}</td>
          <td><strong>${name}</strong></td>
          <td>${desc || '—'}</td>
          <td>${formatDate(item.created_at)}</td>
          <td>${promotionDates}</td>
          <td>${promotionProducts}</td>
          <td>
            ${isAdmin ? `
              <button type="button" class="btn btn-sm btn-secondary" onclick="editDanhMuc('${item.id}')">Sửa</button>
              <button type="button" class="btn btn-sm btn-danger" onclick="deleteDanhMuc('${item.id}')">Xóa</button>
            ` : '<span style="color: #6b7280; font-size: 13px;">Chỉ xem</span>'}
          </td>
        </tr>
      `;
    });
  }

  html += `</tbody></table>`;

  if (totalPages > 1) {
    html += `
      <div style="margin-top: 20px; display: flex; justify-content: center; align-items: center; gap: 8px; flex-wrap: wrap;">
        <button
           type="button"
           class="btn btn-sm ${currentDanhMucPage === 1 ? 'btn-secondary' : 'btn-primary'}"
           onclick="changeDanhMucPage(${currentDanhMucPage - 1})"
          ${currentDanhMucPage === 1 ? 'disabled' : ''}>
          ← Trước
        </button>
    `;

    for (let i = 1; i <= totalPages; i++) {
      if (
        i === 1 ||
        i === totalPages ||
        (i >= currentDanhMucPage - 1 && i <= currentDanhMucPage + 1)
      ) {
        html += `
          <button
             type="button"
             class="btn btn-sm ${i === currentDanhMucPage ? 'btn-primary' : 'btn-secondary'}"
             onclick="changeDanhMucPage(${i})">
            ${i}
          </button>
        `;
      } else if (i === currentDanhMucPage - 2 || i === currentDanhMucPage + 2) {
        html += `<span style="padding: 0 4px;">...</span>`;
      }
    }

    html += `
        <button
           type="button"
           class="btn btn-sm ${currentDanhMucPage === totalPages ? 'btn-secondary' : 'btn-primary'}"
           onclick="changeDanhMucPage(${currentDanhMucPage + 1})"
          ${currentDanhMucPage === totalPages ? 'disabled' : ''}>
          Sau →
        </button>
      </div>
    `;
  }

  contentArea.innerHTML = html;
}

function changeDanhMucPage(page) {
  const totalPages = Math.ceil(danhmucData.length / danhmucPerPage);
  if (page >= 1 && page <= totalPages) {
    currentDanhMucPage = page;
    renderDanhMucTable();
    contentArea.scrollIntoView({ behavior: 'smooth', block: 'start' });
  }
}

// ====== HÀM PHỤ TRỢ JS ======
function escapeHtml(text) {
  if (!text) return '';
  const div = document.createElement('div');
  div.textContent = text;
  return div.innerHTML;
}

// ==== SỬA SẢN PHẨM ====
function editProduct(id){
  const p = sanphamData.find(sp => String(sp.id) === String(id));
  if (!p) {
    alert('Không tìm thấy sản phẩm có ID ' + id);
    return;
  }

  const contentArea = document.getElementById('content-area');
  if (!contentArea) return;

  contentArea.innerHTML = `
    <div class="table-header">
      <h2>Chỉnh sửa sản phẩm</h2>
    </div>
    <form class="add-product-form"
          enctype="multipart/form-data"
          method="POST"
          action="sanpham_update.php">
      <input type="hidden" name="id" value="${p.id}">
      <input type="hidden" name="old_image" value="${p.image || ''}">
      <input type="hidden" name="redirect" value="quanlyadmin.php?page=sanpham">

      <div class="form-group">
        <label>Mã sản phẩm</label>
        <input type="text" value="${p.id}" disabled>
      </div>

      <div class="form-group">
        <label>Tên sản phẩm</label>
        <input type="text" name="name" required value="${p.name || ''}">
      </div>

      <div class="form-group">
        <label>Danh mục</label>
        <select name="category">
          <option value="banh" ${p.category === 'banh' ? 'selected' : ''}>BÁNH QUY BƠ</option>
          <option value="keo"  ${p.category === 'keo'  ? 'selected' : ''}>Kẹo / sản phẩm khác</option>
          <option value="khac" ${p.category === 'khac' ? 'selected' : ''}>Khác</option>
        </select>
      </div>

      <div class="form-group">
        <label>Giá nhập</label>
        <input type="number" name="import_price" min="0" required value="${p.import_price || 0}">
      </div>

      <div class="form-group">
        <label>Giá bán</label>
        <input type="number" name="sale_price" min="0" required value="${p.sale_price || 0}">
      </div>

      <div class="form-group">
        <label>Giá khuyến mãi</label>
        <input type="number" name="price" min="0" value="${p.price || 0}">
      </div>

      <div class="form-group">
        <label>Số lượng</label>
        <input type="number" name="quantity" min="0" required value="${p.quantity || 0}">
      </div>

      <div class="form-group">
        <label>Ảnh hiện tại</label><br>
        ${p.image && p.image !== '0' && p.image !== '' ? `<img src="${escapeHtml(p.image)}" style="width:100px; height:100px; object-fit:cover; border-radius:6px;" onerror="this.style.display='none'; this.nextElementSibling.style.display='inline';"><em style="display:none;">Ảnh không tìm thấy</em>` : '<em>Chưa có ảnh</em>'}
      </div>

      <div class="form-group">
        <label>Đổi ảnh sản phẩm (nếu muốn)</label>
        <input type="file" name="image" accept="image/*">
      </div>

      <div class="form-group" style="grid-column:1/-1;">
        <label>Mô tả</label>
        <textarea name="description">${p.description || ''}</textarea>
      </div>

      <div class="form-actions">
        <button type="button" class="btn btn-secondary" onclick="loadPage('sanpham')">Hủy</button>
        <button type="submit" class="btn btn-primary">Lưu thay đổi</button>
      </div>
    </form>
  `;
}

/* ======= DELETE PRODUCT BẰNG AJAX ======= */
async function deleteProduct(id){
  if (!confirm('Bạn có chắc muốn xóa sản phẩm ' + id + '?')) return;

  const fd = new FormData();
  fd.append('id', id);

  try {
    const res = await fetch('sanpham_delete.php', {
      method: 'POST',
      body: fd,
      headers: { 'X-Requested-With': 'XMLHttpRequest' }
    });

    let payload = {};
    try { payload = await res.json(); } catch (_) {}

    if (!res.ok || payload.success === false) {
      alert(payload.message || 'Xóa thất bại. Vui lòng thử lại.');
      return;
    }

    // Xóa khỏi mảng dữ liệu hiện tại
    const idx = sanphamData.findIndex(sp => String(sp.id) === String(id));
    if (idx !== -1) {
      sanphamData.splice(idx, 1);
    }

    // Điều chỉnh lại trang nếu cần
    const totalPages = Math.ceil(sanphamData.length / productsPerPage) || 1;
    if (currentProductPage > totalPages) currentProductPage = totalPages;

    // Render lại bảng
    renderProductTable();

    // Cập nhật con số trên card "Sản phẩm"
    updateProductCountCard();

    alert(payload.message || 'Đã xóa sản phẩm.');

  } catch (err) {
    console.error(err);
    alert('Có lỗi mạng hoặc máy chủ. Vui lòng thử lại.');
  }
}
async function toggleKmSanPham(id){
  if (!confirm('Đổi trạng thái khuyến mãi #' + id + '?')) return;

  try {
    const res = await fetch('km_sanpham_toggle.php', {
      method: 'POST',
      headers: { 'Content-Type':'application/x-www-form-urlencoded', 'X-Requested-With':'XMLHttpRequest' },
      body: 'id=' + encodeURIComponent(id)
    });

    const payload = await res.json().catch(()=> ({}));
    if (!res.ok || payload.success !== true) {
      alert(payload.message || 'Đổi trạng thái thất bại.');
      return;
    }

    // cập nhật lại mảng data tại chỗ
    const km = kmSanPhamData.find(x => String(x.id) === String(id));
    if (km) km.status = payload.new_status;

    renderKhuyenMaiSanPhamList(); // render lại list ngay
  } catch (e) {
    console.error(e);
    alert('Lỗi mạng/máy chủ.');
  }
}

/* ============================================ */

function updateProductCountCard(){
  const card = document.querySelector('.card[data-page="sanpham"] p');
  if (card) {
    card.textContent = sanphamData.length + ' sản phẩm';
  }
}

// ===== HÀM PHÂN TRANG SẢN PHẨM =====
function renderProductTable() {
  const totalProducts = sanphamData.length;
  const totalPages = Math.ceil(totalProducts / productsPerPage);

  const startIndex = (currentProductPage - 1) * productsPerPage;
  const endIndex = Math.min(startIndex + productsPerPage, totalProducts);
  const currentProducts = sanphamData.slice(startIndex, endIndex);

  let html = `
    <div class="table-header">
      <h2>Quản lý sản phẩm</h2>
      ${isAdmin ? '<button type="button" class="btn btn-primary" onclick="loadPage(\'themsanpham\')">+ Thêm sản phẩm</button>' : ''}
    </div>
    <div style="margin-bottom: 12px; color: #666; font-size: 14px;">
      Hiển thị ${totalProducts === 0 ? 0 : (startIndex + 1)}-${endIndex} trong tổng số ${totalProducts} sản phẩm
    </div>
    <table>
      <thead>
        <tr>
          <th>Mã</th>
          <th>Ảnh</th>
          <th>Tên sản phẩm</th>
          <th>Giá nhập</th>
          <th>Giá bán</th>
          <th>Số lượng</th>
          <th>Trạng thái</th>
          <th>Hành động</th>
        </tr>
      </thead>
      <tbody>
  `;

  if (currentProducts.length === 0) {
    html += `
      <tr>
        <td colspan="8" style="text-align:center; padding:20px;">Không có sản phẩm nào.</td>
      </tr>
    `;
  } else {
    currentProducts.forEach(item => {
      const img = (item.image && item.image !== '0' && item.image !== '') ? item.image : '';
      const isHetHang = (item.status === 'hết hàng');
      const importPrice = item.import_price ? Number(item.import_price) : 0;
      const salePrice   = item.sale_price   ? Number(item.sale_price)   : (item.price ? Number(item.price) : 0);
      const quantity    = Math.max(0, Number(item.quantity) || 0);

      const statusText = item.status || (quantity > 0 ? 'còn hàng' : 'hết hàng');

      html += `
        <tr>
          <td>${item.id}</td>
          <td>${img ? `<img src="${escapeHtml(img)}" style="width:80px; height:80px; object-fit:cover; border-radius:4px;" onerror="this.style.display='none'">` : '<span style="color:#9ca3af;">Chưa có ảnh</span>'}</td>
          <td>${item.name}</td>
          <td>${importPrice.toLocaleString()}₫</td>
          <td>${salePrice.toLocaleString()}₫</td>
          <td>${quantity}</td>
          <td>${statusText}</td>
          <td>
            ${isAdmin ? `
              <button type="button" class="btn btn-sm btn-secondary" onclick="editProduct('${item.id}')">Sửa</button>
              <button type="button" class="btn btn-sm btn-danger" onclick="deleteProduct('${item.id}')">Xóa</button>
              ${
                isHetHang
                  ? `<button type="button" class="btn btn-sm btn-primary" onclick="setProductStatus('${item.id}','còn hàng')">Đưa về còn hàng</button>`
                  : `<button type="button" class="btn btn-sm btn-secondary" onclick="setProductStatus('${item.id}','hết hàng')">Đánh dấu hết hàng</button>`
              }
            ` : '<span style="color: #6b7280; font-size: 13px;">Chỉ xem</span>'}
          </td>
        </tr>
      `;
    });
  }

  html += `</tbody></table>`;

  if (totalPages > 1) {
    html += `
      <div style="margin-top: 20px; display: flex; justify-content: center; align-items: center; gap: 8px; flex-wrap: wrap;">
        <button
           type="button"
           class="btn btn-sm ${currentProductPage === 1 ? 'btn-secondary' : 'btn-primary'}"
           onclick="changeProductPage(${currentProductPage - 1})"
          ${currentProductPage === 1 ? 'disabled' : ''}>
          ← Trước
        </button>
    `;

    for (let i = 1; i <= totalPages; i++) {
      if (
        i === 1 ||
        i === totalPages ||
        (i >= currentProductPage - 1 && i <= currentProductPage + 1)
      ) {
        html += `
          <button
             type="button"
             class="btn btn-sm ${i === currentProductPage ? 'btn-primary' : 'btn-secondary'}"
             onclick="changeProductPage(${i})">
            ${i}
          </button>
        `;
      } else if (i === currentProductPage - 2 || i === currentProductPage + 2) {
        html += `<span style="padding: 0 4px;">...</span>`;
      }
    }

    html += `
        <button
           type="button"
           class="btn btn-sm ${currentProductPage === totalPages ? 'btn-secondary' : 'btn-primary'}"
           onclick="changeProductPage(${currentProductPage + 1})"
          ${currentProductPage === totalPages ? 'disabled' : ''}>
          Sau →
        </button>
      </div>
    `;
  }

  contentArea.innerHTML = html;
}

function changeProductPage(page) {
  const totalPages = Math.ceil(sanphamData.length / productsPerPage);
  if (page >= 1 && page <= totalPages) {
    currentProductPage = page;
    renderProductTable();
    contentArea.scrollIntoView({ behavior: 'smooth', block: 'start' });
  }
}

function setProductStatus(id, status){
  if(confirm('Bạn có chắc muốn chuyển trạng thái sản phẩm ' + id + ' sang "' + status + '"?')){
    window.location.href = 'sanpham_set_status.php?id=' + encodeURIComponent(id) + '&status=' + encodeURIComponent(status);
  }
}

function formatDate(str) {
  if (!str) return '';
  const d = new Date(str);
  if (isNaN(d.getTime())) return str;
  const day   = ('0' + d.getDate()).slice(-2);
  const month = ('0' + (d.getMonth()+1)).slice(-2);
  const year  = d.getFullYear();
  return day + '/' + month + '/' + year;
}
function formatDateTime(str){
  if(!str) return '—';
  const d = new Date(str);
  if(isNaN(d.getTime())) return str;
  const pad = n => ('0'+n).slice(-2);
  return `${pad(d.getDate())}/${pad(d.getMonth()+1)}/${d.getFullYear()} ${pad(d.getHours())}:${pad(d.getMinutes())}`;
}
function mapRole(role){
  return role === 'admin' ? 'Quản trị'
       : role === 'staff' ? 'Nhân viên'
       : 'Khách';
}
function mapTkStatus(st){
  return st === 'active' ? 'Hoạt động' : 'Khoá';
}

function toggleKhuyenMai(id) {
  if (confirm('Đổi trạng thái khuyến mãi ID ' + id + '?')) {
    window.location.href = 'khuyenmai_toggle.php?id=' + id;
  }
}
function deleteKhuyenMai(id) {
  if (confirm('Bạn có chắc muốn xóa khuyến mãi ID ' + id + '?')) {
    window.location.href = 'khuyenmai_delete.php?id=' + id;
  }
}

// render bảng đơn hàng
function renderOrderTable(data) {
  const tbody = document.getElementById('orderTableBody');
  if (!tbody) return;
  let rows = '';
  data.forEach(item => {
    const statusText = statusMap[item.status] || (item.status || '');
    const isPending = item.status === 'pending';
    rows += `
      <tr>
        <td>${item.id}</td>
        <td>${item.customer_name || '—'}</td>
        <td>${item.product_name || '—'}</td>
        <td>${item.quantity || 0}</td>
        <td>${Number(item.total).toLocaleString()}₫</td>
        <td>${statusText}</td>
        <td>
          <button type="button" class="btn btn-sm btn-secondary" onclick="viewOrderDetail(${item.id})">Xem chi tiết</button>
          ${isPending ? `<button type="button" class="btn btn-sm btn-primary" onclick="confirmOrder(${item.id})">Xác nhận</button>` : ''}
        </td>
      </tr>
    `;
  });
  if (!rows) {
    rows = `<tr><td colspan="7" style="text-align:center;">Không có đơn thỏa điều kiện lọc.</td></tr>`;
  }
  tbody.innerHTML = rows;
}

function applyOrderFilter() {
  currentOrderPage = 1;
  const st   = document.getElementById('filterStatus').value;
  const from = document.getElementById('filterFrom').value;
  const to   = document.getElementById('filterTo').value;

  const filtered = donhangData.filter(o => {
    let ok = true;
    const d = o.created_at ? o.created_at.substr(0, 10) : null;

    if (st && o.status !== st) ok = false;
    if (from && (!d || d < from)) ok = false;
    if (to   && (!d || d > to))   ok = false;
    return ok;
  });

  window.filteredOrderData = filtered;
  renderOrderTableWithPagination();
}

function resetOrderFilter() {
  currentOrderPage = 1;
  const st   = document.getElementById('filterStatus');
  const from = document.getElementById('filterFrom');
  const to   = document.getElementById('filterTo');
  if (st) st.value = '';
  if (from) from.value = '';
  if (to) to.value = '';
  window.filteredOrderData = null;
  renderOrderTableWithPagination();
}

function viewOrderDetail(orderId) {
  const order = donhangData.find(o => o.id == orderId);
  if (!order) {
    alert('Không tìm thấy đơn hàng');
    return;
  }

  const contentArea = document.getElementById('content-area');
  if (!contentArea) return;

  contentArea.innerHTML = `
    <div class="table-header">
      <h2>Chi tiết đơn hàng #${order.id}</h2>
      <button type="button" class="btn btn-secondary" onclick="loadPage('donhang')">← Quay lại</button>
    </div>
    <div style="background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
      <h3>Thông tin đơn hàng</h3>
      <table style="width: 100%; margin-top: 10px;">
        <tr>
          <td style="padding: 8px; font-weight: 600; width: 200px;">Mã đơn:</td>
          <td style="padding: 8px;">#${order.id}</td>
        </tr>
        <tr>
          <td style="padding: 8px; font-weight: 600;">Khách hàng:</td>
          <td style="padding: 8px;">${order.customer_name || '—'}</td>
        </tr>
        <tr>
          <td style="padding: 8px; font-weight: 600;">Sản phẩm:</td>
          <td style="padding: 8px;">${order.product_name || '—'}</td>
        </tr>
        <tr>
          <td style="padding: 8px; font-weight: 600;">Số lượng:</td>
          <td style="padding: 8px;">${order.quantity || 0}</td>
        </tr>
        <tr>
          <td style="padding: 8px; font-weight: 600;">Tổng tiền:</td>
          <td style="padding: 8px; font-weight: 700; color: #e65c00;">${Number(order.total).toLocaleString()}₫</td>
        </tr>
        <tr>
          <td style="padding: 8px; font-weight: 600;">Trạng thái:</td>
          <td style="padding: 8px;">${statusMap[order.status] || order.status || '—'}</td>
        </tr>
        <tr>
          <td style="padding: 8px; font-weight: 600;">Ngày tạo:</td>
          <td style="padding: 8px;">${order.created_at || '—'}</td>
        </tr>
      </table>
      ${order.status === 'pending' ? `
        <div style="margin-top: 20px;">
          <button type="button" class="btn btn-primary" onclick="confirmOrder(${order.id})">Xác nhận đơn hàng</button>
        </div>
      ` : ''}
    </div>
  `;

  setPageTitle('donhang');
}

function confirmOrder(orderId) {
  if (!confirm('Bạn có chắc muốn xác nhận đơn hàng #' + orderId + '?')) {
    return;
  }
  window.location.href = 'donhang_update_status.php?id=' + orderId + '&status=paid';
}

// ====== User table render & actions ======
function renderUserTable(data){
  const tbody = document.getElementById('userTableBody');
  if(!tbody) return;
  let rows = '';
  data.forEach(u => {
    rows += `
      <tr>
        <td>${u.id}</td>
        <td>${u.username}</td>
        <td>${u.phone || ''}</td>
        <td>${mapRole(u.role)}</td>
        <td>${mapTkStatus(u.status)}</td>
        <td>${formatDateTime(u.created_at)}</td>
        <td>${formatDateTime(u.last_login)}</td>
        <td>
          <button type="button" class="btn btn-sm btn-secondary" onclick="gotoEditUser(${u.id})">Sửa</button>
          <button type="button" class="btn btn-sm btn-secondary" onclick="gotoUserDetail(${u.id})">Chi tiết</button>
          <button type="button" class="btn btn-sm btn-secondary" onclick="resetPwd(${u.id})">Đổi mật khẩu</button>
          <button type="button" class="btn btn-sm ${u.status==='active'?'btn-danger':'btn-secondary'}" onclick="toggleUser(${u.id})">
            ${u.status==='active' ? 'Khoá' : 'Mở khoá'}
          </button>
          <button type="button" class="btn btn-sm btn-danger" onclick="deleteUser(${u.id})">Xóa</button>
        </td>
      </tr>
    `;
  });
  if(!rows){
    rows = `<tr><td colspan="8" style="text-align:center;">Không có tài khoản.</td></tr>`;
  }
  tbody.innerHTML = rows;
}
function applyUserFilter(){
  currentTaiKhoanPage = 1;
  const r  = document.getElementById('filterRole')?.value || '';
  const st = document.getElementById('filterTkStatus')?.value || '';
  const filtered = taikhoanData.filter(u => {
    let ok = true;
    if (r  && u.role   !== r)  ok = false;
    if (st && u.status !== st) ok = false;
    return ok;
  });
  window.filteredUserData = filtered;
  renderTaiKhoanTableWithPagination();
}
function resetUserFilter(){
  currentTaiKhoanPage = 1;
  const r  = document.getElementById('filterRole');
  const st = document.getElementById('filterTkStatus');
  if(r) r.value = '';
  if(st) st.value = '';
  window.filteredUserData = null;
  renderTaiKhoanTableWithPagination();
}
function gotoEditUser(id){
  const u = taikhoanData.find(user => String(user.id) === String(id));
  if (!u) {
    alert('Không tìm thấy tài khoản có ID ' + id);
    return;
  }

  const contentArea = document.getElementById('content-area');
  if (!contentArea) return;

  contentArea.innerHTML = `
    <div class="table-header">
      <h2>Chỉnh sửa tài khoản</h2>
    </div>
    <form class="add-product-form"
          method="POST"
          action="taikhoan_update.php">
      <input type="hidden" name="id" value="${u.id}">
      <input type="hidden" name="redirect" value="quanlyadmin.php?page=taikhoan">

      <div class="form-group">
        <label>ID tài khoản</label>
        <input type="text" value="${u.id}" disabled>
      </div>

      <div class="form-group">
        <label>Username</label>
        <input type="text" name="username" required value="${u.username || ''}">
      </div>

      <div class="form-group">
        <label>Số điện thoại</label>
        <input type="text" name="phone" required value="${u.phone || ''}">
      </div>

      <div class="form-group">
        <label>Email</label>
        <input type="email" name="email" value="${u.email || ''}">
      </div>

      <div class="form-group">
        <label>Quyền</label>
        <select name="role">
          <option value="customer" ${(u.role === 'customer' || u.role === 'user') ? 'selected' : ''}>Khách</option>
          <option value="staff" ${u.role === 'staff' ? 'selected' : ''}>Nhân viên</option>
          <option value="admin" ${u.role === 'admin' ? 'selected' : ''}>Quản trị</option>
        </select>
      </div>

      <div class="form-group">
        <label>Trạng thái</label>
        <select name="status">
          <option value="active" ${(u.status === 'active' || !u.status) ? 'selected' : ''}>Hoạt động</option>
          <option value="locked" ${u.status === 'locked' ? 'selected' : ''}>Khoá</option>
        </select>
      </div>

      <div class="form-actions">
        <button type="button" class="btn btn-secondary" onclick="loadPage('taikhoan')">Hủy</button>
        <button type="submit" class="btn btn-primary">Lưu thay đổi</button>
      </div>
    </form>
  `;

  setPageTitle('suataikhoan');
}
function gotoUserDetail(id){
  const u = taikhoanData.find(user => String(user.id) === String(id));
  if (!u) {
    alert('Không tìm thấy tài khoản có ID ' + id);
    return;
  }

  const contentArea = document.getElementById('content-area');
  if (!contentArea) return;

  contentArea.innerHTML = `
    <div class="table-header">
      <h2>Hồ sơ tài khoản</h2>
      <button type="button" class="btn btn-secondary" onclick="loadPage('taikhoan')">← Quay lại</button>
    </div>
    <div style="
      max-width: 640px;
      background: #ffffff;
      border-radius: 14px;
      padding: 16px 18px;
      box-shadow: 0 10px 25px rgba(0,0,0,0.05);
    ">
      <h3 style="margin-top:0; margin-bottom:12px;">Thông tin chung</h3>
      <table style="width:100%; border-collapse:collapse;">
        <tr>
          <td style="padding:6px 4px; width:180px; font-weight:600;">ID tài khoản</td>
          <td style="padding:6px 4px;">${u.id}</td>
        </tr>
        <tr>
          <td style="padding:6px 4px; font-weight:600;">Username</td>
          <td style="padding:6px 4px;">${escapeHtml(u.username || '')}</td>
        </tr>
        <tr>
          <td style="padding:6px 4px; font-weight:600;">Số điện thoại</td>
          <td style="padding:6px 4px;">${escapeHtml(u.phone || 'Chưa cập nhật')}</td>
        </tr>
        <tr>
          <td style="padding:6px 4px; font-weight:600;">Email</td>
          <td style="padding:6px 4px;">${escapeHtml(u.email || 'Chưa cập nhật')}</td>
        </tr>
        <tr>
          <td style="padding:6px 4px; font-weight:600;">Quyền</td>
          <td style="padding:6px 4px;">${mapRole(u.role)}</td>
        </tr>
        <tr>
          <td style="padding:6px 4px; font-weight:600;">Trạng thái</td>
          <td style="padding:6px 4px;">${mapTkStatus(u.status)}</td>
        </tr>
        <tr>
          <td style="padding:6px 4px; font-weight:600;">Ngày tạo</td>
          <td style="padding:6px 4px;">${formatDateTime(u.created_at)}</td>
        </tr>
        <tr>
          <td style="padding:6px 4px; font-weight:600;">Đăng nhập gần nhất</td>
          <td style="padding:6px 4px;">${formatDateTime(u.last_login)}</td>
        </tr>
      </table>
    </div>
  `;

  setPageTitle('taikhoan');
}
function resetPwd(id){
  if(confirm('Đặt lại mật khẩu cho tài khoản #' + id + '?')){
    window.location.href = 'taikhoan_resetpwd.php?id=' + id;
  }
}
function toggleUser(id){
  if(confirm('Đổi trạng thái khoá/mở cho tài khoản #' + id + '?')){
    window.location.href = 'taikhoan_toggle.php?id=' + id;
  }
}
function deleteUser(id){
  if(confirm('Xoá tài khoản #' + id + '?')){
    window.location.href = 'taikhoan_delete.php?id=' + id;
  }
}

// ===== HÀM PHÂN TRANG ĐƠN HÀNG =====
function renderOrderTableWithPagination() {
  const dataToUse = window.filteredOrderData || donhangData;
  const totalOrders = dataToUse.length;
  const totalPages = Math.ceil(totalOrders / ordersPerPage);

  const startIndex = (currentOrderPage - 1) * ordersPerPage;
  const endIndex = Math.min(startIndex + ordersPerPage, totalOrders);
  const currentOrders = dataToUse.slice(startIndex, endIndex);

let html = `
  <div class="table-header">
    <h2>Danh sách đơn hàng</h2>
  </div>

  <div class="filter-bar" style="margin-bottom:12px; display:flex; flex-wrap:wrap; gap:8px; align-items:flex-end;">
    <div>
      <label style="font-size:13px; display:block;">Trạng thái</label>
      <select id="filterStatus" style="padding:4px 8px; border-radius:6px; border:1px solid #d1d5db;">
        <option value="">Tất cả</option>
        <option value="pending">Chờ xử lý</option>
        <option value="paid">Đã thanh toán</option>
        <option value="shipping">Đang giao</option>
        <option value="completed">Hoàn tất</option>
        <option value="cancelled">Đã hủy</option>
      </select>
    </div>
    <div>
      <label style="font-size:13px; display:block;">Từ ngày</label>
      <input type="date" id="filterFrom" style="padding:4px 8px; border-radius:6px; border:1px solid #d1d5db;">
    </div>
    <div>
      <label style="font-size:13px; display:block;">Đến ngày</label>
      <input type="date" id="filterTo" style="padding:4px 8px; border-radius:6px; border:1px solid #d1d5db;">
    </div>
    <div style="display:flex; gap:6px;">
      <button type="button" class="btn btn-primary btn-sm" onclick="applyOrderFilter()">Lọc</button>
      <button type="button" class="btn btn-secondary btn-sm" onclick="resetOrderFilter()">Xóa lọc</button>
    </div>
  </div>

  <div style="margin-bottom: 12px; color: #666; font-size: 14px;">
    Hiển thị ${totalOrders === 0 ? 0 : (startIndex + 1)}-${endIndex} trong tổng số ${totalOrders} đơn hàng
  </div>

  <table>
    <thead>
      <tr>
        <th>Mã đơn</th>
        <th>Khách hàng</th>
        <th>Sản phẩm</th>
        <th>Số lượng</th>
        <th>Tổng tiền</th>
        <th>Trạng thái</th>
        <th>Hành động</th>
      </tr>
    </thead>
    <tbody id="orderTableBody"></tbody>
  </table>
`;

  contentArea.innerHTML = html;
  renderOrderTable(currentOrders);

  if (totalPages > 1) {
    let paginationHtml = `
      <div style="margin-top: 20px; display: flex; justify-content: center; align-items: center; gap: 8px; flex-wrap: wrap;">
        <button
           type="button"
           class="btn btn-sm ${currentOrderPage === 1 ? 'btn-secondary' : 'btn-primary'}"
           onclick="changeOrderPage(${currentOrderPage - 1})"
          ${currentOrderPage === 1 ? 'disabled' : ''}>
          ← Trước
        </button>
    `;

    for (let i = 1; i <= totalPages; i++) {
      if (i === 1 || i === totalPages || (i >= currentOrderPage - 1 && i <= currentOrderPage + 1)) {
        paginationHtml += `
          <button
             type="button"
             class="btn btn-sm ${i === currentOrderPage ? 'btn-primary' : 'btn-secondary'}"
             onclick="changeOrderPage(${i})">
            ${i}
          </button>
        `;
      } else if (i === currentOrderPage - 2 || i === currentOrderPage + 2) {
        paginationHtml += `<span style="padding: 0 4px;">...</span>`;
      }
    }

    paginationHtml += `
        <button
           type="button"
           class="btn btn-sm ${currentOrderPage === totalPages ? 'btn-secondary' : 'btn-primary'}"
           onclick="changeOrderPage(${currentOrderPage + 1})"
          ${currentOrderPage === totalPages ? 'disabled' : ''}>
          Sau →
        </button>
      </div>
    `;
    contentArea.innerHTML += paginationHtml;
  }
}

function changeOrderPage(page) {
  const dataToUse = window.filteredOrderData || donhangData;
  const totalPages = Math.ceil(dataToUse.length / ordersPerPage);
  if (page >= 1 && page <= totalPages) {
    currentOrderPage = page;
    renderOrderTableWithPagination();
    contentArea.scrollIntoView({ behavior: 'smooth', block: 'start' });
  }
}

// ===== HÀM PHÂN TRANG KHUYẾN MÃI =====
function renderKhuyenMaiTableWithPagination() {
  const totalKM = khuyenmaiData.length;
  const totalPages = Math.ceil(totalKM / khuyenMaiPerPage);

  const startIndex = (currentKhuyenMaiPage - 1) * khuyenMaiPerPage;
  const endIndex = Math.min(startIndex + khuyenMaiPerPage, totalKM);
  const currentKM = khuyenmaiData.slice(startIndex, endIndex);

  let html = `
    <div class="table-header">
      <h2>Quản lý khuyến mãi</h2>
      ${isAdmin ? '<button type="button" class="btn btn-primary" onclick="loadPage(\'themkhuyenmai\')">+ Thêm khuyến mãi</button>' : ''}
    </div>

    <div style="margin-bottom: 12px; color: #666; font-size: 14px;">
      Hiển thị ${totalKM === 0 ? 0 : (startIndex + 1)}-${endIndex} trong tổng số ${totalKM} khuyến mãi
    </div>

    <table>
      <thead>
        <tr>
          <th>ID</th>
          <th>Mã</th>
          <th>Mô tả</th>
          <th>Loại</th>
          <th>Giá trị</th>
          <th>Đơn tối thiểu</th>
          <th>Thời gian</th>
          <th>Cấp cho</th>
          <th>Lượt dùng</th>
          <th>Trạng thái</th>
          <th>Hành động</th>
        </tr>
      </thead>
      <tbody>
  `;

  if (currentKM.length === 0) {
    html += `
      <tr>
        <td colspan="11" style="text-align:center;">Chưa có khuyến mãi nào.</td>
      </tr>
    `;
  } else {
    currentKM.forEach(item => {
      const typeText  = item.loai === 'percent' ? 'Giảm %' : 'Giảm tiền';
      const valueText = item.loai === 'percent'
        ? (Number(item.gia_tri) || 0) + '%'
        : (Number(item.gia_tri) || 0).toLocaleString() + '₫';
      const minOrder  = (Number(item.dieu_kien_tong_tien) || 0).toLocaleString() + '₫';
      const start     = item.ngay_bat_dau ? formatDate(item.ngay_bat_dau) : '—';
      const end       = item.ngay_ket_thuc ? formatDate(item.ngay_ket_thuc) : '—';
      const userText  = 'Tất cả khách';
      const used      = '0/∞';
      const statusTxt = item.is_active == 1 ? 'Đang hoạt động' : 'Ngừng';
      const toggleLbl = item.is_active == 1 ? 'Tắt' : 'Bật';

      html += `
        <tr>
          <td>${item.id}</td>
          <td><strong>${item.ma}</strong></td>
          <td>${item.ten || ''}</td>
          <td>${typeText}</td>
          <td>${valueText}</td>
          <td>${minOrder}</td>
          <td>${start} - ${end}</td>
          <td>${userText}</td>
          <td>${used}</td>
          <td>${statusTxt}</td>
          <td>
            ${isAdmin ? `
              <button type="button" class="btn btn-sm btn-secondary" onclick="toggleKhuyenMai(${item.id})">
                ${toggleLbl}
              </button>
              <button type="button" class="btn btn-sm btn-danger" onclick="deleteKhuyenMai(${item.id})">Xóa</button>
            ` : '<span style="color: #6b7280; font-size: 13px;">Chỉ xem</span>'}
          </td>
        </tr>
      `;
    });
  }

  html += `</tbody></table>`;

  if (totalPages > 1) {
    html += `
      <div style="margin-top: 20px; display: flex; justify-content: center; align-items: center; gap: 8px; flex-wrap: wrap;">
        <button
           type="button"
           class="btn btn-sm ${currentKhuyenMaiPage === 1 ? 'btn-secondary' : 'btn-primary'}"
           onclick="changeKhuyenMaiPage(${currentKhuyenMaiPage - 1})"
          ${currentKhuyenMaiPage === 1 ? 'disabled' : ''}>
          ← Trước
        </button>
    `;

    for (let i = 1; i <= totalPages; i++) {
      if (i === 1 || i === totalPages || (i >= currentKhuyenMaiPage - 1 && i <= currentKhuyenMaiPage + 1)) {
        html += `
          <button
             type="button"
             class="btn btn-sm ${i === currentKhuyenMaiPage ? 'btn-primary' : 'btn-secondary'}"
             onclick="changeKhuyenMaiPage(${i})">
            ${i}
          </button>
        `;
      } else if (i === currentKhuyenMaiPage - 2 || i === currentKhuyenMaiPage + 2) {
        html += `<span style="padding: 0 4px;">...</span>`;
      }
    }

    html += `
        <button
           type="button"
           class="btn btn-sm ${currentKhuyenMaiPage === totalPages ? 'btn-secondary' : 'btn-primary'}"
           onclick="changeKhuyenMaiPage(${currentKhuyenMaiPage + 1})"
          ${currentKhuyenMaiPage === totalPages ? 'disabled' : ''}>
          Sau →
        </button>
      </div>
    `;
  }

  contentArea.innerHTML = html;
}
function editKmSanPham(id){
  const km = kmSanPhamData.find(x => String(x.id) === String(id));
  if (!km) { alert('Không tìm thấy khuyến mãi #' + id); return; }

  const selectedIds = String(km.product_ids || '')
    .split(',')
    .map(s => s.trim())
    .filter(Boolean);

  const productsHtml = (sanphamData || []).map(sp => {
    const checked = selectedIds.includes(String(sp.id)) ? 'checked' : '';
    return `
      <label style="display:flex; gap:8px; align-items:center; padding:6px 8px; border:1px solid #e5e7eb; border-radius:10px;">
        <input type="checkbox" name="product_ids[]" value="${escapeHtml(String(sp.id))}" ${checked}>
        <span><strong>${escapeHtml(sp.name || '')}</strong> — ID: ${escapeHtml(String(sp.id))}</span>
      </label>
    `;
  }).join('');

  contentArea.innerHTML = `
    <div class="table-header">
      <h2>Sửa khuyến mãi sản phẩm #${km.id}</h2>
    </div>

    <form class="add-product-form" method="POST" action="km_sanpham_update.php">
      <input type="hidden" name="id" value="${km.id}">

      <div class="form-group" style="grid-column:1/-1;">
        <label>Tên chương trình *</label>
        <input type="text" name="name" required value="${escapeHtml(km.name || '')}">
      </div>

      <div class="form-group">
        <label>Kiểu giảm *</label>
        <select name="discount_type" required>
          <option value="percent" ${km.discount_type==='percent'?'selected':''}>Giảm theo %</option>
          <option value="amount" ${km.discount_type==='amount'?'selected':''}>Giảm số tiền</option>
        </select>
      </div>

      <div class="form-group">
        <label>Giá trị giảm *</label>
        <input type="number" name="discount_value" min="0" required value="${Number(km.discount_value||0)}">
      </div>

      <div class="form-group">
        <label>Giảm tối đa (nếu giảm %)</label>
        <input type="number" name="max_discount" min="0" value="${Number(km.max_discount||0)}">
      </div>

      <div class="form-group">
        <label>Số lượng tối thiểu (min_qty)</label>
        <input type="number" name="min_qty" min="1" value="${Number(km.min_qty||1)}">
      </div>

      <div class="form-group">
        <label>Đơn tối thiểu (₫)</label>
        <input type="number" name="min_order_value" min="0" value="${Number(km.min_order_value||0)}">
      </div>

      <div class="form-group">
        <label>Ưu tiên (priority)</label>
        <input type="number" name="priority" value="${Number(km.priority||0)}">
      </div>

      <div class="form-group">
        <label>Trạng thái</label>
        <select name="status">
          <option value="active" ${km.status==='active'?'selected':''}>Đang hoạt động</option>
          <option value="inactive" ${km.status!=='active'?'selected':''}>Ngừng</option>
        </select>
      </div>

      <div class="form-group">
        <label>Ngày bắt đầu</label>
        <input type="date" name="start_date" value="${(km.start_date||'').slice(0,10)}">
      </div>

      <div class="form-group">
        <label>Ngày kết thúc</label>
        <input type="date" name="end_date" value="${(km.end_date||'').slice(0,10)}">
      </div>

      <div class="form-group" style="grid-column:1/-1;">
        <label>Chọn sản phẩm áp dụng *</label>
        <div style="display:grid; grid-template-columns:repeat(auto-fit,minmax(260px,1fr)); gap:10px; max-height:260px; overflow:auto; padding:8px; border:1px solid #e5e7eb; border-radius:12px;">
          ${productsHtml || `<div style="color:#9ca3af;">Chưa có sản phẩm để chọn.</div>`}
        </div>
      </div>

      <div class="form-actions">
        <button type="button" class="btn btn-secondary" onclick="renderKhuyenMaiSanPhamList()">Quay lại</button>
        <button type="submit" class="btn btn-primary">Lưu</button>
      </div>
    </form>
  `;
}

function changeKhuyenMaiPage(page) {
  const totalPages = Math.ceil(khuyenmaiData.length / khuyenMaiPerPage);
  if (page >= 1 && page <= totalPages) {
    currentKhuyenMaiPage = page;
    renderKhuyenMaiTableWithPagination();
    contentArea.scrollIntoView({ behavior: 'smooth', block: 'start' });
  }
}

// ====== QUẢN LÝ NHẬP HÀNG ======
function renderNhapHangTable() {
  const dataToUse  = window.filteredNhapData || nhaphangData;
  const totalRows  = dataToUse.length;
  const totalPages = Math.ceil(totalRows / importPerPage) || 1;

  const startIndex = (currentImportPage - 1) * importPerPage;
  const endIndex   = Math.min(startIndex + importPerPage, totalRows);
  const current    = dataToUse.slice(startIndex, endIndex);

  let html = `
    <div class="table-header">
      <h2>Quản lý nhập hàng</h2>
      <button type="button" class="btn btn-primary" onclick="loadPage('themnhaphang')">+ Thêm phiếu nhập</button>
    </div>

    <div class="filter-bar" style="margin-bottom:12px; display:flex; flex-wrap:wrap; gap:8px; align-items:flex-end;">
      <div>
        <label style="font-size:13px; display:block;">Lọc theo tên sản phẩm</label>
        <input
          type="text"
          id="filterNhapProductName"
          placeholder="Nhập tên sản phẩm..."
          style="padding:4px 8px; border-radius:6px; border:1px solid #d1d5db; min-width:220px;"
          onkeydown="if(event.key==='Enter'){event.preventDefault();applyNhapFilter();}"
        >
      </div>
      <div style="display:flex; gap:6px;">
        <button type="button" class="btn btn-primary btn-sm" onclick="applyNhapFilter()">Lọc</button>
        <button type="button" class="btn btn-secondary btn-sm" onclick="resetNhapFilter()">Xóa lọc</button>
      </div>
    </div>

    <div style="margin-bottom: 12px; color: #666; font-size: 14px;">
      Hiển thị ${totalRows === 0 ? 0 : (startIndex + 1)}-${endIndex} trong tổng số ${totalRows} phiếu nhập
    </div>
    <table>
      <thead>
        <tr>
          <th>ID</th>
          <th>Ngày nhập</th>
          <th>Sản phẩm</th>
          <th>Số lượng</th>
          <th>Giá nhập</th>
          <th>Thành tiền</th>
          <th>Ghi chú</th>
          <th>Hành động</th>
        </tr>
      </thead>
      <tbody>
  `;

  if (!current.length) {
    html += `
      <tr>
        <td colspan="8" style="text-align:center; padding:20px;">Chưa có phiếu nhập nào.</td>
      </tr>
    `;
  } else {
    current.forEach(row => {
      const price  = Number(row.import_price || 0);
      const qty    = Number(row.so_luong || 0);
      const total  = price * qty;

      html += `
        <tr>
          <td>${row.id}</td>
          <td>${formatDate(row.created_at)}</td>
          <td>${escapeHtml(row.product_name || '')}</td>
          <td>${qty}</td>
          <td>${price.toLocaleString()}₫</td>
          <td>${total.toLocaleString()}₫</td>
          <td>${row.ghi_chu ? escapeHtml(row.ghi_chu) : '—'}</td>
          <td>
            <button type="button" class="btn btn-sm btn-secondary" onclick="editNhapHang(${row.id})">Sửa</button>
            <button type="button" class="btn btn-sm btn-danger" onclick="deleteNhapHang(${row.id})">Xóa</button>
          </td>
        </tr>
      `;
    });
  }

  html += `</tbody></table>`;

  if (totalPages > 1) {
    html += `
      <div style="margin-top: 20px; display: flex; justify-content: center; align-items: center; gap: 8px; flex-wrap: wrap;">
        <button
           type="button"
           class="btn btn-sm ${currentImportPage === 1 ? 'btn-secondary' : 'btn-primary'}"
           onclick="changeImportPage(${currentImportPage - 1})"
          ${currentImportPage === 1 ? 'disabled' : ''}>
          ← Trước
        </button>
    `;

    for (let i = 1; i <= totalPages; i++) {
      if (i === 1 || i === totalPages || (i >= currentImportPage - 1 && i <= currentImportPage + 1)) {
        html += `
          <button
             type="button"
             class="btn btn-sm ${i === currentImportPage ? 'btn-primary' : 'btn-secondary'}"
             onclick="changeImportPage(${i})">
            ${i}
          </button>
        `;
      } else if (i === currentImportPage - 2 || i === currentImportPage + 2) {
        html += `<span style="padding: 0 4px;">...</span>`;
      }
    }


    html += `
        <button
           type="button"
           class="btn btn-sm ${currentImportPage === totalPages ? 'btn-secondary' : 'btn-primary'}"
           onclick="changeImportPage(${currentImportPage + 1})"
          ${currentImportPage === totalPages ? 'disabled' : ''}>
          Sau →
        </button>
      </div>
    `;
  }

  contentArea.innerHTML = html;
}

// Lọc nhập hàng theo tên sản phẩm
function applyNhapFilter() {
  const input = document.getElementById('filterNhapProductName');
  if (!input) return;

  const keyword = input.value.trim().toLowerCase();
  currentImportPage = 1;

  if (!keyword) {
    window.filteredNhapData = null;
    renderNhapHangTable();
    return;
  }


  window.filteredNhapData = nhaphangData.filter(row => {
    const name = (row.product_name || '').toLowerCase();
    return name.includes(keyword);
  });

  renderNhapHangTable();
}

function resetNhapFilter() {
  const input = document.getElementById('filterNhapProductName');
  if (input) input.value = '';
  window.filteredNhapData = null;
  currentImportPage = 1;
  renderNhapHangTable();
}

function changeImportPage(page) {
  const dataToUse  = window.filteredNhapData || nhaphangData;
  const totalPages = Math.ceil(dataToUse.length / importPerPage) || 1;

  if (page >= 1 && page <= totalPages) {
    currentImportPage = page;
    renderNhapHangTable();
    contentArea.scrollIntoView({ behavior: 'smooth', block: 'start' });
  }
}

function editNhapHang(id) {
  const row = nhaphangData.find(x => String(x.id) === String(id));
  if (!row) {
    alert('Không tìm thấy phiếu nhập');
    return;
  }

  contentArea.innerHTML = `
    <div class="table-header">
      <h2>Sửa phiếu nhập hàng</h2>
    </div>
    <form class="add-product-form" method="POST" action="nhaphang_update.php">
      <input type="hidden" name="id" value="${row.id}">
      <div class="form-group">
        <label>Sản phẩm</label>
        <input type="text" value="${escapeHtml(row.product_name || '')}" disabled>
      </div>
      <div class="form-group">
        <label>Số lượng nhập *</label>
        <input type="number" name="so_luong" min="1" required value="${row.so_luong}">
      </div>
      <div class="form-group">
        <label>Giá nhập (₫) *</label>
        <input type="number" name="import_price" min="0" required value="${row.import_price}">
      </div>
      <div class="form-group" style="grid-column:1/-1;">
        <label>Ghi chú</label>
        <textarea name="ghi_chu">${row.ghi_chu ? row.ghi_chu : ''}</textarea>
      </div>
      <div class="form-actions">
        <button type="button" class="btn btn-secondary" onclick="loadPage('nhaphang')">Hủy</button>
        <button type="submit" class="btn btn-primary">Lưu thay đổi</button>
      </div>
    </form>
  `;
}

function deleteNhapHang(id) {
  if (!confirm('Bạn có chắc muốn xóa phiếu nhập #' + id + ' ?')) return;
  window.location.href = 'nhaphang_delete.php?id=' + encodeURIComponent(id);
}

// ===== HÀM PHÂN TRANG TÀI KHOẢN =====
function renderTaiKhoanTableWithPagination() {
  const dataToUse = window.filteredUserData || taikhoanData;
  const totalUsers = dataToUse.length;
  const totalPages = Math.ceil(totalUsers / taiKhoanPerPage);

  const startIndex = (currentTaiKhoanPage - 1) * taiKhoanPerPage;
  const endIndex = Math.min(startIndex + taiKhoanPerPage, totalUsers);
  const currentUsers = dataToUse.slice(startIndex, endIndex);

  let html = `
    <div class="table-header">
      <h2>Quản lý tài khoản</h2>
    </div>

    <div class="filter-bar" style="margin-bottom:12px; display:flex; flex-wrap:wrap; gap:8px; align-items:flex-end;">
      <div>
        <label style="font-size:13px; display:block;">Quyền</label>
        <select id="filterRole" style="padding:4px 8px; border-radius:6px; border:1px solid #d1d5db;">
          <option value="">Tất cả</option>
          <option value="admin">Quản trị</option>
          <option value="staff">Nhân viên</option>
          <option value="customer">Khách</option>
        </select>
      </div>
      <div>
        <label style="font-size:13px; display:block;">Trạng thái</label>
        <select id="filterTkStatus" style="padding:4px 8px; border-radius:6px; border:1px solid #d1d5db;">
          <option value="">Tất cả</option>
          <option value="active">Hoạt động</option>
          <option value="locked">Khoá</option>
        </select>
      </div>
      <div style="display:flex; gap:6px;">
        <button type="button" class="btn btn-primary btn-sm" onclick="applyUserFilter()">Lọc</button>
        <button type="button" class="btn btn-secondary btn-sm" onclick="resetUserFilter()">Xóa lọc</button>
      </div>
    </div>

    <div style="margin-bottom: 12px; color: #666; font-size: 14px;">
      Hiển thị ${totalUsers === 0 ? 0 : (startIndex + 1)}-${endIndex} trong tổng số ${totalUsers} tài khoản
    </div>

    <table>
      <thead>
        <tr>
          <th>ID</th>
          <th>Username</th>
          <th>Số điện thoại</th>
          <th>Quyền</th>
          <th>Trạng thái</th>
          <th>Tạo lúc</th>
          <th>Đăng nhập cuối</th>
          <th>Hành động</th>
        </tr>
      </thead>
      <tbody id="userTableBody"></tbody>
    </table>
  `;

  contentArea.innerHTML = html;
  renderUserTable(currentUsers);

  if (totalPages > 1) {
    let paginationHtml = `
      <div style="margin-top: 20px; display: flex; justify-content: center; align-items: center; gap: 8px; flex-wrap: wrap;">
        <button
           type="button"
           class="btn btn-sm ${currentTaiKhoanPage === 1 ? 'btn-secondary' : 'btn-primary'}"
           onclick="changeTaiKhoanPage(${currentTaiKhoanPage - 1})"
          ${currentTaiKhoanPage === 1 ? 'disabled' : ''}>
          ← Trước
        </button>
    `;

    for (let i = 1; i <= totalPages; i++) {
      if (i === 1 || i === totalPages || (i >= currentTaiKhoanPage - 1 && i <= currentTaiKhoanPage + 1)) {
        paginationHtml += `
          <button
             type="button"
             class="btn btn-sm ${i === currentTaiKhoanPage ? 'btn-primary' : 'btn-secondary'}"
             onclick="changeTaiKhoanPage(${i})">
            ${i}
          </button>
        `;
      } else if (i === currentTaiKhoanPage - 2 || i === currentTaiKhoanPage + 2) {
        paginationHtml += `<span style="padding: 0 4px;">...</span>`;
      }
    }

    paginationHtml += `
        <button
           type="button"
           class="btn btn-sm ${currentTaiKhoanPage === totalPages ? 'btn-secondary' : 'btn-primary'}"
           onclick="changeTaiKhoanPage(${currentTaiKhoanPage + 1})"
          ${currentTaiKhoanPage === totalPages ? 'disabled' : ''}>
          Sau →
        </button>
      </div>
    `;
    contentArea.innerHTML += paginationHtml;
  }
}

function changeTaiKhoanPage(page) {
  const dataToUse = window.filteredUserData || taikhoanData;
  const totalPages = Math.ceil(dataToUse.length / taiKhoanPerPage);
  if (page >= 1 && page <= totalPages) {
    currentTaiKhoanPage = page;
    renderTaiKhoanTableWithPagination();
    contentArea.scrollIntoView({ behavior: 'smooth', block: 'start' });
  }
}

// ====== HÀM BÁO CÁO ======
function renderBaoCao() {
  const totalOrders = donhangData.length;

  let totalRevenue  = revenueTotal && !isNaN(revenueTotal) ? revenueTotal : 0;
  if (!totalRevenue) {
    totalRevenue = donhangData.reduce((sum, o) => sum + Number(o.total || 0), 0);
  }

  let totalImport = importTotal && !isNaN(importTotal) ? importTotal : 0;

  let totalProfit = totalRevenue - totalImport;

  const avgOrder = totalOrders > 0 ? totalRevenue / totalOrders : 0;

  const customerSet = new Set();
  donhangData.forEach(o => { if (o.customer_name) customerSet.add(o.customer_name); });
  const totalCustomers = customerSet.size;

  const elOrders    = document.getElementById('reportTotalOrders');
  const elRevenue   = document.getElementById('reportTotalRevenue');
  const elImport    = document.getElementById('reportTotalImport');
  const elProfit    = document.getElementById('reportTotalProfit');
  const elAvgOrder  = document.getElementById('reportAvgOrder');
  const elCus       = document.getElementById('reportTotalCustomers');

  if (elOrders)   elOrders.textContent   = totalOrders.toLocaleString();
  if (elRevenue)  elRevenue.textContent  = totalRevenue.toLocaleString() + '₫';
  if (elImport)   elImport.textContent   = totalImport.toLocaleString() + '₫';
  if (elProfit)   elProfit.textContent   = totalProfit.toLocaleString() + '₫';
  if (elAvgOrder) elAvgOrder.textContent = (Number(avgOrder.toFixed(0)) || 0).toLocaleString() + '₫';
  if (elCus)      elCus.textContent      = totalCustomers.toLocaleString();

  const tbody = document.getElementById('revenueByDayTableBody');
  if (tbody) {
    if (revenueByDay && revenueByDay.length > 0) {
      let rows = '';
      revenueByDay.forEach(row => {
        rows += `
          <tr>
            <td>${formatDate(row.date)}</td>
            <td>${Number(row.total).toLocaleString()}₫</td>
          </tr>
        `;
      });
      tbody.innerHTML = rows;
    } else {
      tbody.innerHTML = `
        <tr>
          <td colspan="2" style="text-align:center; color:#9ca3af; padding:12px 4px;">
            Chưa có dữ liệu doanh thu theo ngày.
          </td>
        </tr>
      `;
    }
  }

  const revCanvas = document.getElementById('revenueChart');
  const revEmpty  = document.getElementById('revenueChartEmpty');
  if (revCanvas) {
    if (revenueChartInstance) revenueChartInstance.destroy();

    if (revenueByDay && revenueByDay.length > 0) {
      const labels = revenueByDay.map(r => formatDate(r.date));
      const data   = revenueByDay.map(r => r.total);

      revenueChartInstance = new Chart(revCanvas.getContext('2d'), {
        type: 'line',
        data: {
          labels,
          datasets: [{
            label: 'Doanh thu (₫)',
            data,
            tension: 0.3,
            fill: false
          }]
        },
        options: {
          plugins: {
            legend: { display: false }
          },
          scales: {
            y: {
              ticks: {
                callback: function(value) {
                  try {
                    return Number(value).toLocaleString() + '₫';
                  } catch(e) {
                    return value;
                  }
                }
              }
            }
          }
        }
      });

      if (revEmpty) revEmpty.style.display = 'none';
    } else {
      if (revEmpty) revEmpty.style.display = 'block';
    }
  }

  const statusCanvas = document.getElementById('statusChart');
  const statusEmpty  = document.getElementById('statusChartEmpty');
  if (statusCanvas) {
    if (statusChartInstance) statusChartInstance.destroy();

    if (donhangData && donhangData.length > 0) {
      const statusCounts = { pending:0, paid:0, shipping:0, completed:0, cancelled:0, other:0 };
      donhangData.forEach(o => {
        if (statusCounts.hasOwnProperty(o.status)) {
          statusCounts[o.status]++;
        } else {
          statusCounts.other++;
        }
      });

      const keys = Object.keys(statusCounts).filter(k => statusCounts[k] > 0);
      if (keys.length > 0) {
        const labels = keys.map(k => statusMap[k] || 'Khác');
        const data   = keys.map(k => statusCounts[k]);

        statusChartInstance = new Chart(statusCanvas.getContext('2d'), {
          type: 'doughnut',
          data: {
            labels,
            datasets: [{
              data
            }]
          },
          options: {
            plugins: {
              legend: {
                position: 'bottom'
              }
            }
          }
        });

        if (statusEmpty) statusEmpty.style.display = 'none';
      } else {
        if (statusEmpty) statusEmpty.style.display = 'block';
      }
    } else {
      if (statusEmpty) statusEmpty.style.display = 'block';
    }
  }
}

// ====== QUẢN LÝ CHAT BOX ======
let chatConversations = [];
let currentChatUserId = null;

function renderChatPage() {
  const convMap = new Map();

  chatMessagesData.forEach(m => {
    const uid = m.user_id || 0;
    if (!convMap.has(uid)) {
      convMap.set(uid, {
        user_id: uid,
        username: m.username || (uid ? ('Khách #' + uid) : 'Khách lẻ'),
        email: m.email || '',
        messages: [],
        last_time: m.created_at,
        unread: 0
      });
    }
    const conv = convMap.get(uid);
    conv.messages.push(m);
    if (m.created_at > conv.last_time) {
      conv.last_time = m.created_at;
    }
    if ((m.sender_type === 'customer') && Number(m.is_read) === 0) {
      conv.unread += 1;
    }
  });

  chatConversations = Array.from(convMap.values()).sort((a, b) =>
    new Date(b.last_time || 0) - new Date(a.last_time || 0)
  );

  if (!currentChatUserId && chatConversations.length > 0) {
    currentChatUserId = chatConversations[0].user_id;
  }

  let html = `
    <div class="table-header">
      <h2>Quản lý chat box</h2>
    </div>
    <div class="chat-wrapper">
      <div class="chat-sidebar">
        <div class="chat-sidebar-header">
          <span>Danh sách cuộc trò chuyện</span>
          <span style="font-size:12px; color:#6b7280;">${chatConversations.length} cuộc</span>
        </div>
        <div id="chatConversationList" class="chat-conversation-list"></div>
      </div>
      <div class="chat-main">
        <div id="chatMainInner" style="display:flex; flex-direction:column; flex:1;">
        </div>
      </div>
    </div>
  `;

  contentArea.innerHTML = html;

  renderConversationList();
  if (currentChatUserId !== null) {
    renderChatMessages(currentChatUserId);
  } else {
    const main = document.getElementById('chatMainInner');
    if (main) {
      main.innerHTML = `
        <div class="chat-main-header">Chi tiết chat</div>
        <div class="chat-empty-state">
          Chưa có cuộc trò chuyện nào. Khi khách sử dụng chat trên website, lịch sử chat sẽ xuất hiện tại đây.
        </div>
      `;
    }
  }
}

function renderConversationList() {
  const listEl = document.getElementById('chatConversationList');
  if (!listEl) return;

  if (chatConversations.length === 0) {
    listEl.innerHTML = `
      <div class="chat-empty-state" style="padding:8px;">
        Không có cuộc trò chuyện nào.
      </div>
    `;
    return;
  }

  let html = '';
  chatConversations.forEach(conv => {
    const isActive = String(conv.user_id) === String(currentChatUserId);
    const lastMsg = conv.messages[conv.messages.length - 1];
    const lastText = lastMsg ? (lastMsg.message || '') : '';
    const lastTime = lastMsg ? formatDateTime(lastMsg.created_at) : '';

    html += `
      <div class="chat-conversation-item ${isActive ? 'active' : ''}" data-user-id="${conv.user_id}">
        <div class="chat-conv-name">${escapeHtml(conv.username || '')}</div>
        <div class="chat-conv-last">${escapeHtml(lastText).substring(0, 40)}${lastText.length > 40 ? '…' : ''}</div>
        <div class="chat-conv-meta">
          <span>${escapeHtml(lastTime)}</span>
          ${conv.unread > 0 ? `<span class="chat-unread-badge">${conv.unread}</span>` : ''}
        </div>
      </div>
    `;
  });

  listEl.innerHTML = html;

  listEl.querySelectorAll('.chat-conversation-item').forEach(item => {
    item.addEventListener('click', () => {
      const uid = item.getAttribute('data-user-id');
      currentChatUserId = uid;
      renderConversationList();
      renderChatMessages(uid);
    });
  });
}

function renderChatMessages(userId) {
  const main = document.getElementById('chatMainInner');
  if (!main) return;

  const conv = chatConversations.find(c => String(c.user_id) === String(userId));
  if (!conv) {
    main.innerHTML = `
      <div class="chat-main-header">Chi tiết chat</div>
      <div class="chat-empty-state">
        Không tìm thấy hội thoại cho user ID ${userId}.
      </div>
    `;
    return;
  }

  let msgHtml = '';
  conv.messages.forEach(m => {
    const isMe = (m.sender_type === 'admin' || m.sender_type === 'staff');
    const rowCls  = isMe ? 'chat-msg-row me' : 'chat-msg-row them';
    const bubbleCls = isMe ? 'chat-msg chat-msg-me' : 'chat-msg chat-msg-them';
    const who  = isMe ? (m.sender_type === 'admin' ? 'Admin' : 'Nhân viên') : (conv.username || 'Khách');
    const iconClass = isMe ? 'fa-solid fa-user-shield' : 'fa-regular fa-user';

    msgHtml += `
      <div class="${rowCls}">
        ${isMe ? '' : `
          <div class="chat-avatar">
            <i class="${iconClass}"></i>
          </div>
        `}
        <div class="${bubbleCls}">
          <div>${escapeHtml(m.message || '')}</div>
          <div class="chat-msg-meta">${escapeHtml(who)} • ${escapeHtml(formatDateTime(m.created_at))}</div>
        </div>
        ${isMe ? `
          <div class="chat-avatar">
            <i class="${iconClass}"></i>
          </div>
        ` : ''}
      </div>
    `;
  });

  main.innerHTML = `
    <div class="chat-main-header">
      Chat với ${escapeHtml(conv.username || '')}
      ${conv.email ? ` <span style="font-size:12px; color:#6b7280;">(${escapeHtml(conv.email)})</span>` : ''}
    </div>
    <div class="chat-messages-list" id="chatMessagesList">
      ${msgHtml || '<div class="chat-empty-state">Chưa có tin nhắn nào.</div>'}
    </div>
    ${(isAdmin || isStaff) ? `
    <form id="chatReplyForm" class="chat-reply-form" onsubmit="sendChatReply(event, '${conv.user_id}')">
      <textarea name="message" placeholder="Nhập nội dung trả lời..."></textarea>
      <div class="chat-reply-actions">
        <button type="button" class="btn btn-secondary btn-sm" onclick="loadPage('chat')">Hủy</button>
        <button type="submit" class="btn btn-primary btn-sm">Gửi</button>
      </div>
    </form>
    ` : `
    <div class="chat-empty-state" style="border-top:1px solid #f3f4f6; margin-top:6px; padding-top:6px;">
      Bạn không có quyền gửi tin nhắn (chỉ admin / nhân viên).
    </div>
    `}
  `;

  const list = document.getElementById('chatMessagesList');
  if (list) {
    list.scrollTop = list.scrollHeight;
  }
}

// ===== HÀM GỬI TIN CHAT =====
async function sendChatReply(e, userId) {
  e.preventDefault();
  const form = e.target;
  const text = form.message.value.trim();
  if (!text) return;

  const fd = new FormData();
  fd.append('user_id', userId);
  fd.append('message', text);

  // Admin và Nhân viên đều gửi với sender_type = 'admin' (quyền như admin)
  const senderType = (isAdmin || isStaff) ? 'admin' : 'customer';
  fd.append('sender_type', senderType);

  try {
    const res = await fetch('chat_send.php', {
      method: 'POST',
      body: fd,
      headers: {
        'X-Requested-With': 'XMLHttpRequest'
      }
    });

    let payload = {};
    try { payload = await res.json(); } catch (_) {}

    if (!res.ok || !payload || payload.success !== true) {
      console.error('SEND_ERROR:', payload);
      alert((payload && payload.message) || 'Gửi tin nhắn thất bại.');
      return;
    }

    const newMsg = payload.message;
    if (!newMsg) {
      alert('Server không trả dữ liệu tin nhắn.');
      return;
    }

    form.message.value = '';

    chatMessagesData.push(newMsg);
    currentChatUserId = userId;
    renderChatPage();
  } catch (err) {
    console.error(err);
    alert('Có lỗi mạng hoặc máy chủ. Vui lòng thử lại.');
  }
}

// Kiểm tra nếu có tham số trong URL để tự động load trang tương ứng
const urlParams = new URLSearchParams(window.location.search);
const autoPage = urlParams.get('page');
if (autoPage) {
  clearActiveMenu();
  document.querySelector(`.menu-item[data-page="${autoPage}"]`)?.classList.add('active');
  setPageTitle(autoPage);
  setActiveCard(autoPage);
  loadPage(autoPage);
} else {
  loadPage('dashboard');
}
document.querySelectorAll('.submenu-item').forEach(item => {
  item.addEventListener('click', function (e) {
    e.stopPropagation();
    const page = this.getAttribute('data-page');
    setPageTitle(page);

    // Nếu submenu thuộc khuyến mãi thì active card khuyến mãi
    const parentMenu = this.closest('.menu-item.has-dropdown')?.getAttribute('data-page');
    if (parentMenu === 'khuyenmai') setActiveCard('khuyenmai');
    else if (parentMenu === 'khohang_group') setActiveCard('nhaphang'); // hoặc bỏ
    else setActiveCard(page);

    loadPage(page);
  });
});


// ===== Dropdown hover + click submenu không bị trigger menu-item cha =====
document.querySelectorAll('.menu-item.has-dropdown .submenu-item').forEach(sub => {
  sub.addEventListener('click', (e) => {
    e.stopPropagation(); // tránh click bị tính là click vào menu-item cha
  });
});
function renderKhuyenMaiSanPhamForm() {
  // danh sách sản phẩm (checkbox)
  const productsHtml = (sanphamData || []).map(sp => `
    <label style="display:flex; gap:8px; align-items:center; padding:6px 8px; border:1px solid #e5e7eb; border-radius:10px;">
      <input type="checkbox" name="product_ids[]" value="${escapeHtml(String(sp.id))}">
      <span><strong>${escapeHtml(sp.name || '')}</strong> — ID: ${escapeHtml(String(sp.id))}</span>
    </label>
  `).join('');

  contentArea.innerHTML = `
    <div class="table-header">
      <h2>Khuyến mãi sản phẩm</h2>
    </div>

    <div style="margin-bottom:10px; color:#6b7280; font-size:13px;">

    </div>

<form class="add-product-form" method="POST" action="km_sanpham_store.php">
      <div class="form-group" style="grid-column:1/-1;">
        <label>Tên chương trình *</label>
        <input type="text" name="name" required placeholder="VD: Giảm 10% Danisa 200g">
      </div>

      <div class="form-group">
        <label>Kiểu giảm *</label>
        <select name="discount_type" required>
          <option value="percent">Giảm theo %</option>
          <option value="amount">Giảm số tiền</option>
        </select>
      </div>

      <div class="form-group">
        <label>Giá trị giảm *</label>
        <input type="number" name="discount_value" min="0" required placeholder="VD: 10 hoặc 50000">
      </div>

      <div class="form-group">
        <label>Giảm tối đa (nếu giảm %)</label>
        <input type="number" name="max_discount" min="0" placeholder="VD: 100000">
      </div>

      <div class="form-group">
        <label>Số lượng tối thiểu (min_qty)</label>
        <input type="number" name="min_qty" min="1" value="1">
      </div>

      <div class="form-group">
        <label>Đơn tối thiểu (₫)</label>
        <input type="number" name="min_order_value" min="0" value="0">
      </div>

      <div class="form-group">
        <label>Ưu tiên (priority)</label>
        <input type="number" name="priority" value="0">
      </div>

      <div class="form-group">
        <label>Trạng thái</label>
        <select name="status">
          <option value="active">Đang hoạt động</option>
          <option value="inactive">Ngừng</option>
        </select>
      </div>

      <div class="form-group">
        <label>Ngày bắt đầu</label>
        <input type="date" name="start_date">
      </div>

      <div class="form-group">
        <label>Ngày kết thúc</label>
        <input type="date" name="end_date">
      </div>

      <div class="form-group" style="grid-column:1/-1;">
        <label>Chọn sản phẩm áp dụng *</label>
        <div style="display:grid; grid-template-columns:repeat(auto-fit,minmax(260px,1fr)); gap:10px; max-height:260px; overflow:auto; padding:8px; border:1px solid #e5e7eb; border-radius:12px;">
          ${productsHtml || `<div style="color:#9ca3af;">Chưa có sản phẩm để chọn.</div>`}
        </div>
      </div>

      <div class="form-actions">
        <button type="button" class="btn btn-secondary" onclick="loadPage('khuyenmai')">Quay lại</button>
        <button type="submit" class="btn btn-primary">Thêm</button>
      </div>
    </form>
  `;
}
function renderKhuyenMaiNgayLeForm() {
  const categoriesHtml = (danhmucData || []).map(dm =>
    `<option value="${escapeHtml(String(dm.id))}">${escapeHtml(dm.name || '')}</option>`
  ).join('');

  const productsHtml = (sanphamData || []).map(sp => `
    <label style="display:flex; gap:8px; align-items:center; padding:6px 8px; border:1px solid #e5e7eb; border-radius:10px;">
      <input type="checkbox" name="product_ids[]" value="${escapeHtml(String(sp.id))}">
      <span><strong>${escapeHtml(sp.name || '')}</strong></span>
    </label>
  `).join('');

  contentArea.innerHTML = `
    <div class="table-header">
      <h2>Khuyến mãi ngày lễ</h2>
    </div>

    <div style="margin-bottom:10px; color:#6b7280; font-size:13px;">
    </div>

<form class="add-product-form" method="POST" action="km_ngayle_store.php">
      <div class="form-group" style="grid-column:1/-1;">
        <label>Tên chiến dịch / ngày lễ *</label>
        <input type="text" name="name" required placeholder="VD: Noel 2025">
      </div>

      <div class="form-group" style="grid-column:1/-1;">
        <label>Mô tả (banner/ghi chú)</label>
        <textarea name="description" placeholder="VD: Giảm giá toàn shop dịp Noel..."></textarea>
      </div>

      <div class="form-group">
        <label>Phạm vi áp dụng *</label>
        <select name="scope" id="kmleScope" onchange="toggleKmLeScope()" required>
          <option value="all">Toàn bộ sản phẩm</option>
          <option value="category">Theo danh mục</option>
          <option value="products">Chọn sản phẩm</option>
        </select>
      </div>

      <div class="form-group" id="kmleCategoryWrap" style="display:none;">
        <label>Chọn danh mục</label>
        <select name="danhmuc_id">
          <option value="">-- Chọn danh mục --</option>
          ${categoriesHtml}
        </select>
      </div>

      <div class="form-group">
        <label>Kiểu giảm *</label>
        <select name="discount_type" required>
          <option value="percent">Giảm theo %</option>
          <option value="amount">Giảm số tiền</option>
        </select>
      </div>

      <div class="form-group">
        <label>Giá trị giảm *</label>
        <input type="number" name="discount_value" min="0" required placeholder="VD: 15 hoặc 70000">
      </div>

      <div class="form-group">
        <label>Giảm tối đa (nếu giảm %)</label>
        <input type="number" name="max_discount" min="0" placeholder="VD: 150000">
      </div>

      <div class="form-group">
        <label>Đơn tối thiểu (₫)</label>
        <input type="number" name="min_order_value" min="0" value="0">
      </div>

      <div class="form-group">
        <label>Tự động áp dụng?</label>
        <select name="is_auto" id="kmleIsAuto" onchange="toggleKmLeCode()">
          <option value="1">Có (Auto apply)</option>
          <option value="0">Không (Dùng mã)</option>
        </select>
      </div>

      <div class="form-group" id="kmleCodeWrap" style="display:none;">
        <label>Mã khuyến mãi</label>
        <input type="text" name="code" placeholder="VD: NOEL2025">
      </div>

      <div class="form-group">
        <label>Ưu tiên (priority)</label>
        <input type="number" name="priority" value="0">
      </div>

      <div class="form-group">
        <label>Trạng thái</label>
        <select name="status">
          <option value="active">Đang hoạt động</option>
          <option value="inactive">Ngừng</option>
        </select>
      </div>

      <div class="form-group">
        <label>Ngày bắt đầu</label>
        <input type="date" name="start_date">
      </div>

      <div class="form-group">
        <label>Ngày kết thúc</label>
        <input type="date" name="end_date">
      </div>

      <div class="form-group" id="kmleProductsWrap" style="grid-column:1/-1; display:none;">
        <label>Chọn sản phẩm áp dụng</label>
        <div style="display:grid; grid-template-columns:repeat(auto-fit,minmax(260px,1fr)); gap:10px; max-height:260px; overflow:auto; padding:8px; border:1px solid #e5e7eb; border-radius:12px;">
          ${productsHtml || `<div style="color:#9ca3af;">Chưa có sản phẩm để chọn.</div>`}
        </div>
      </div>

      <div class="form-actions">
        <button type="button" class="btn btn-secondary" onclick="loadPage('khuyenmai')">Quay lại</button>
        <button type="submit" class="btn btn-primary">Thêm </button>
      </div>
    </form>
  `;
  // set trạng thái ban đầu
  toggleKmLeScope();
  toggleKmLeCode();
}

function toggleKmLeScope() {
  const scope = document.getElementById('kmleScope')?.value;
  const catWrap = document.getElementById('kmleCategoryWrap');
  const prodWrap = document.getElementById('kmleProductsWrap');
  if (!catWrap || !prodWrap) return;

  catWrap.style.display = (scope === 'category') ? 'block' : 'none';
  prodWrap.style.display = (scope === 'products') ? 'block' : 'none';
}

function toggleKmLeCode() {
  const isAuto = document.getElementById('kmleIsAuto')?.value;
  const codeWrap = document.getElementById('kmleCodeWrap');
  if (!codeWrap) return;
  codeWrap.style.display = (String(isAuto) === '0') ? 'block' : 'none';
}
function formatDiscountText(type, value, max) {
  const v = Number(value || 0);
  const m = Number(max || 0);
  if (type === 'percent') {
    return v + '%'+ (m > 0 ? ` (tối đa ${m.toLocaleString()}₫)` : '');
  }
  return v.toLocaleString() + '₫';
}

function renderKhuyenMaiSanPhamList() {
  const data = kmSanPhamData || [];
  const total = data.length;
  const totalPages = Math.ceil(total / kmSPPerPage) || 1;

  const startIndex = (currentKmSPPage - 1) * kmSPPerPage;
  const endIndex = Math.min(startIndex + kmSPPerPage, total);
  const current = data.slice(startIndex, endIndex);

  let html = `
    <div class="table-header">
      <h2>Danh sách khuyến mãi sản phẩm</h2>
      ${isAdmin ? `<button type="button" class="btn btn-primary" onclick="renderKhuyenMaiSanPhamForm()">+ Thêm khuyến mãi</button>` : ''}
    </div>

    <div style="margin-bottom: 12px; color: #666; font-size: 14px;">
      Hiển thị ${total === 0 ? 0 : (startIndex + 1)}-${endIndex} trong tổng số ${total} khuyến mãi
    </div>

    <table>
      <thead>
        <tr>
          <th>ID</th>
          <th>Tên chương trình</th>
          <th>Giảm</th>
          <th>Sản phẩm giảm giá</th>
          <th>Điều kiện</th>
          <th>Thời gian</th>
          <th>Trạng thái</th>
          <th>Hành động</th>
        </tr>
      </thead>
      <tbody>
  `;

  if (!current.length) {
    html += `<tr><td colspan="8" style="text-align:center; padding:20px;">Chưa có khuyến mãi sản phẩm nào.</td></tr>`;
  } else {
    current.forEach(km => {
      const start = km.start_date ? formatDate(km.start_date) : '—';
      const end   = km.end_date ? formatDate(km.end_date) : '—';
      const statusTxt = (km.status === 'active') ? 'Đang hoạt động' : 'Ngừng';
      const toggleLbl = (km.status === 'active') ? 'Tắt' : 'Bật';
      const minQty = Number(km.min_qty || 1);
      const minOrder = Number(km.min_order_value || 0);

      const productNames = (km.product_names || '').trim();
      const productShow = productNames ? escapeHtml(productNames) : (Number(km.product_count || 0) + ' sản phẩm');

      html += `
        <tr>
          <td>${km.id}</td>
          <td><strong>${escapeHtml(km.name || '')}</strong></td>
          <td>${formatDiscountText(km.discount_type, km.discount_value, km.max_discount)}</td>
          <td style="max-width:320px;">
            <div style="white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">${productShow}</div>
          </td>
          <td>Min qty: ${minQty} • Đơn tối thiểu: ${minOrder.toLocaleString()}₫</td>
          <td>${start} - ${end}</td>
          <td>${statusTxt}</td>
          <td>
            ${isAdmin ? `
              <button type="button" class="btn btn-sm btn-secondary" onclick="editKmSanPham(${km.id})">Sửa</button>
              <button type="button" class="btn btn-sm ${km.status==='active'?'btn-danger':'btn-primary'}" onclick="toggleKmSanPham(${km.id})">${toggleLbl}</button>
            ` : '<span style="color:#6b7280; font-size:13px;">Chỉ xem</span>'}
          </td>
        </tr>
      `;
    });
  }

  html += `</tbody></table>`;

  if (totalPages > 1) {
    html += `
      <div class="pagination">
        <button type="button" class="btn btn-sm ${currentKmSPPage === 1 ? 'btn-secondary' : 'btn-primary'}"
          onclick="changeKmSPPage(${currentKmSPPage - 1})" ${currentKmSPPage === 1 ? 'disabled' : ''}>
          ← Trước
        </button>

        <span class="page-info">Trang ${currentKmSPPage}/${totalPages}</span>

        <button type="button" class="btn btn-sm ${currentKmSPPage === totalPages ? 'btn-secondary' : 'btn-primary'}"
          onclick="changeKmSPPage(${currentKmSPPage + 1})" ${currentKmSPPage === totalPages ? 'disabled' : ''}>
          Sau →
        </button>
      </div>
    `;
  }

  contentArea.innerHTML = html;
}

function changeKmSPPage(page) {
  const totalPages = Math.ceil((kmSanPhamData || []).length / kmSPPerPage) || 1;
  if (page >= 1 && page <= totalPages) {
    currentKmSPPage = page;
    renderKhuyenMaiSanPhamList();
    contentArea.scrollIntoView({ behavior: 'smooth', block: 'start' });
  }
}
function scopeText(scope) {
  if (scope === 'all') return 'Toàn bộ sản phẩm';
  if (scope === 'category') return 'Theo danh mục';
  if (scope === 'products') return 'Chọn sản phẩm';
  return scope || '—';
}

function renderKhuyenMaiNgayLeList() {
  const data = kmNgayLeData || [];
  const total = data.length;
  const totalPages = Math.ceil(total / kmLePerPage) || 1;

  const startIndex = (currentKmLePage - 1) * kmLePerPage;
  const endIndex = Math.min(startIndex + kmLePerPage, total);
  const current = data.slice(startIndex, endIndex);

  let html = `
    <div class="table-header">
      <h2>Danh sách khuyến mãi ngày lễ</h2>
      ${isAdmin ? `<button type="button" class="btn btn-primary" onclick="renderKhuyenMaiNgayLeForm()">+ Thêm khuyến mãi</button>` : ''}
    </div>

    <div style="margin-bottom: 12px; color: #666; font-size: 14px;">
      Hiển thị ${total === 0 ? 0 : (startIndex + 1)}-${endIndex} trong tổng số ${total} khuyến mãi
    </div>

    <table>
      <thead>
        <tr>
          <th>ID</th>
          <th>Tên chiến dịch</th>
          <th>Phạm vi</th>
          <th>Giảm</th>
          <th>Tự động/Mã</th>
          <th>Áp dụng</th>
          <th>Thời gian</th>
          <th>Trạng thái</th>
        </tr>
      </thead>
      <tbody>
  `;

  if (!current.length) {
    html += `
      <tr>
        <td colspan="8" style="text-align:center; padding:20px;">
          Chưa có khuyến mãi ngày lễ nào.
        </td>
      </tr>
    `;
  } else {
    current.forEach(km => {
      const start = km.start_date ? formatDate(km.start_date) : '—';
      const end   = km.end_date ? formatDate(km.end_date) : '—';
      const statusTxt = (km.status === 'active') ? 'Đang hoạt động' : 'Ngừng';

      const autoTxt = (Number(km.is_auto) === 1)
        ? 'Tự động'
        : ('Mã: ' + escapeHtml(km.code || '—'));

      // Nếu scope=products → hiện count sản phẩm
      // Nếu scope=category → hiện ID danh mục (bạn có thể map tên danh mục nếu muốn)
      let applyTxt = '—';
      if (km.scope === 'products') applyTxt = `${Number(km.product_count || 0)} sản phẩm`;
      if (km.scope === 'category') applyTxt = `Danh mục ID: ${km.danhmuc_id || '—'}`;
      if (km.scope === 'all') applyTxt = 'Toàn shop';

      html += `
        <tr>
          <td>${km.id}</td>
          <td><strong>${escapeHtml(km.name || '')}</strong></td>
          <td>${scopeText(km.scope)}</td>
          <td>${formatDiscountText(km.discount_type, km.discount_value, km.max_discount)}</td>
          <td>${autoTxt}</td>
          <td>${applyTxt}</td>
          <td>${start} - ${end}</td>
          <td>${statusTxt}</td>
        </tr>
      `;
    });
  }

  html += `</tbody></table>`;

  if (totalPages > 1) {
    html += `
      <div class="pagination">
        <button type="button" class="btn btn-sm ${currentKmLePage === 1 ? 'btn-secondary' : 'btn-primary'}"
          onclick="changeKmLePage(${currentKmLePage - 1})" ${currentKmLePage === 1 ? 'disabled' : ''}>
          ← Trước
        </button>

        <span class="page-info">Trang ${currentKmLePage}/${totalPages}</span>

        <button type="button" class="btn btn-sm ${currentKmLePage === totalPages ? 'btn-secondary' : 'btn-primary'}"
          onclick="changeKmLePage(${currentKmLePage + 1})" ${currentKmLePage === totalPages ? 'disabled' : ''}>
          Sau →
        </button>
      </div>
    `;
  }

  contentArea.innerHTML = html;
}

function changeKmLePage(page) {
  const totalPages = Math.ceil((kmNgayLeData || []).length / kmLePerPage) || 1;
  if (page >= 1 && page <= totalPages) {
    currentKmLePage = page;
    renderKhuyenMaiNgayLeList();
    contentArea.scrollIntoView({ behavior: 'smooth', block: 'start' });
  }
}


</script>
</body>
</html>
