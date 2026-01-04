<?php
require 'config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: quanlyadmin.php?page=khuyenmai');
    exit;
}

// Lấy dữ liệu từ form
$code           = strtoupper(trim($_POST['code'] ?? ''));          // mã KM (VD: DANISA10)
$description    = trim($_POST['description'] ?? '');               // mô tả / tên chương trình
$discount_type  = $_POST['discount_type'] ?? 'percent';           // 'percent' hoặc 'amount'
$discount_value = (float)($_POST['discount_value'] ?? 0);          // giá trị giảm
$min_order      = (float)($_POST['min_order_value'] ?? 0);         // điều kiện tổng tiền
$start_date     = $_POST['start_date'] ?: null;                    // yyyy-mm-dd hoặc datetime
$end_date       = $_POST['end_date']   ?: null;                    // yyyy-mm-dd hoặc datetime
$status         = $_POST['status'] ?? 'active';                    // 'active' hoặc 'inactive'

// Các field này hiện KHÔNG có trong bảng khuyenmai, nên tạm bỏ:
// $quantity      = (int)($_POST['quantity'] ?? 0);
// $assigned_user = trim($_POST['assigned_user'] ?? '');

if ($code === '' || $discount_value <= 0) {
    die('Mã khuyến mãi và giá trị giảm không hợp lệ.');
}

// Chuẩn hóa date nếu chỉ gửi dạng yyyy-mm-dd
if (!empty($start_date) && strlen($start_date) === 10) {
    $start_date .= ' 00:00:00';
}
if (!empty($end_date) && strlen($end_date) === 10) {
    $end_date .= ' 23:59:59';
}

// Map status -> is_active (tinyint(1))
$is_active = ($status === 'active') ? 1 : 0;

// INSERT theo đúng cấu trúc bảng khuyenmai:
// ma, ten, loai, gia_tri, dieu_kien_tong_tien, ngay_bat_dau, ngay_ket_thuc, is_active
$stmt = $conn->prepare("
    INSERT INTO khuyenmai (
        ma,
        ten,
        loai,
        gia_tri,
        dieu_kien_tong_tien,
        ngay_bat_dau,
        ngay_ket_thuc,
        is_active
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
");

if (!$stmt) {
    die('Lỗi prepare: ' . $conn->error);
}

// ma (s), ten (s), loai (s), gia_tri (d), dieu_kien_tong_tien (d),
// ngay_bat_dau (s), ngay_ket_thuc (s), is_active (i)
$stmt->bind_param(
    'sssddssi',
    $code,           // ma
    $description,    // ten
    $discount_type,  // loai
    $discount_value, // gia_tri
    $min_order,      // dieu_kien_tong_tien
    $start_date,     // ngay_bat_dau
    $end_date,       // ngay_ket_thuc
    $is_active       // is_active
);

if (!$stmt->execute()) {
    die('Lỗi execute: ' . $stmt->error);
}

$stmt->close();

// Redirect về trang quản lý khuyến mãi trong admin
header('Location: quanlyadmin.php?page=khuyenmai');
exit;
