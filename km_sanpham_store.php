<?php
session_start();
require 'config.php';

// Chỉ admin (hoặc bạn đổi thành staff cũng được)
if (empty($_SESSION['user_id'])) {
    header('Location: dangky.php?redirect=quanlyadmin.php');
    exit;
}
$role = $_SESSION['role'] ?? 'customer';
if ($role !== 'admin') {
    header('Location: trangchu.php');
    exit;
}

function backWithError($msg) {
    $_SESSION['flash_message'] = $msg;
    $_SESSION['flash_type'] = 'error';
    header('Location: quanlyadmin.php?page=khuyenmai_sanpham');
    exit;
}
function backWithSuccess($msg) {
    $_SESSION['flash_message'] = $msg;
    $_SESSION['flash_type'] = 'success';
    header('Location: quanlyadmin.php?page=khuyenmai_sanpham');
    exit;
}

$name           = trim($_POST['name'] ?? '');
$discount_type  = $_POST['discount_type'] ?? '';
$discount_value = (float)($_POST['discount_value'] ?? 0);
$max_discount   = isset($_POST['max_discount']) && $_POST['max_discount'] !== '' ? (float)$_POST['max_discount'] : null;
$min_qty        = (int)($_POST['min_qty'] ?? 1);
$min_order_value= (float)($_POST['min_order_value'] ?? 0);
$priority       = (int)($_POST['priority'] ?? 0);
$status         = $_POST['status'] ?? 'active';
$start_date     = $_POST['start_date'] ?? null;
$end_date       = $_POST['end_date'] ?? null;
$product_ids    = $_POST['product_ids'] ?? []; // array

if ($name === '') backWithError('Vui lòng nhập tên chương trình.');
if (!in_array($discount_type, ['percent','amount'], true)) backWithError('Kiểu giảm giá không hợp lệ.');
if ($discount_value <= 0) backWithError('Giá trị giảm phải > 0.');
if ($min_qty < 1) $min_qty = 1;
if (!in_array($status, ['active','inactive'], true)) $status = 'active';

if (!is_array($product_ids) || count($product_ids) === 0) {
    backWithError('Vui lòng chọn ít nhất 1 sản phẩm áp dụng.');
}

if ($start_date === '') $start_date = null;
if ($end_date === '')   $end_date = null;
if ($start_date && $end_date && $start_date > $end_date) {
    backWithError('Ngày bắt đầu không được lớn hơn ngày kết thúc.');
}

$conn->begin_transaction();

try {
    // Insert header
    $sql = "INSERT INTO km_sanpham
            (name, discount_type, discount_value, max_discount, min_qty, min_order_value, priority, status, start_date, end_date)
            VALUES (?,?,?,?,?,?,?,?,?,?)";
    $stmt = $conn->prepare($sql);
    if (!$stmt) throw new Exception("Prepare failed: " . $conn->error);

    // bind: s s d d i d i s s s
    $stmt->bind_param(
        "ssddidisss",
        $name,
        $discount_type,
        $discount_value,
        $max_discount,
        $min_qty,
        $min_order_value,
        $priority,
        $status,
        $start_date,
        $end_date
    );
    if (!$stmt->execute()) throw new Exception("Execute failed: " . $stmt->error);

    $km_id = $stmt->insert_id;
    $stmt->close();

    // Insert items
    $stmt2 = $conn->prepare("INSERT IGNORE INTO km_sanpham_items (km_id, sanpham_id) VALUES (?, ?)");
    if (!$stmt2) throw new Exception("Prepare failed: " . $conn->error);

    foreach ($product_ids as $pid) {
        $pid = trim((string)$pid);
        if ($pid === '') continue;
        $stmt2->bind_param("is", $km_id, $pid);
        if (!$stmt2->execute()) throw new Exception("Execute failed: " . $stmt2->error);
    }
    $stmt2->close();

    $conn->commit();
    backWithSuccess('Đã tạo khuyến mãi sản phẩm thành công.');

} catch (Exception $e) {
    $conn->rollback();
    backWithError('Lỗi lưu khuyến mãi sản phẩm: ' . $e->getMessage());
}