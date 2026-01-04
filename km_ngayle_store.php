<?php
session_start();
require 'config.php';

if (empty($_SESSION['user_id'])) {
    header('Location: dangnhap.php?redirect=quanlyadmin.php');
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
    header('Location: quanlyadmin.php?page=khuyenmai_le');
    exit;
}
function backWithSuccess($msg) {
    $_SESSION['flash_message'] = $msg;
    $_SESSION['flash_type'] = 'success';
    header('Location: quanlyadmin.php?page=khuyenmai_le');
    exit;
}

$name           = trim($_POST['name'] ?? '');
$description    = trim($_POST['description'] ?? '');
$scope          = $_POST['scope'] ?? 'all'; // all|category|products
$danhmuc_id     = isset($_POST['danhmuc_id']) && $_POST['danhmuc_id'] !== '' ? (int)$_POST['danhmuc_id'] : null;

$discount_type  = $_POST['discount_type'] ?? '';
$discount_value = (float)($_POST['discount_value'] ?? 0);
$max_discount   = isset($_POST['max_discount']) && $_POST['max_discount'] !== '' ? (float)$_POST['max_discount'] : null;
$min_order_value= (float)($_POST['min_order_value'] ?? 0);

$is_auto        = (int)($_POST['is_auto'] ?? 1); // 1 auto, 0 code
$code           = trim($_POST['code'] ?? '');

$priority       = (int)($_POST['priority'] ?? 0);
$status         = $_POST['status'] ?? 'active';
$start_date     = $_POST['start_date'] ?? null;
$end_date       = $_POST['end_date'] ?? null;

$product_ids    = $_POST['product_ids'] ?? []; // array if scope=products

if ($name === '') backWithError('Vui lòng nhập tên chiến dịch/ngày lễ.');
if (!in_array($scope, ['all','category','products'], true)) backWithError('Phạm vi áp dụng không hợp lệ.');

if (!in_array($discount_type, ['percent','amount'], true)) backWithError('Kiểu giảm giá không hợp lệ.');
if ($discount_value <= 0) backWithError('Giá trị giảm phải > 0.');
if (!in_array($status, ['active','inactive'], true)) $status = 'active';

if ($start_date === '') $start_date = null;
if ($end_date === '')   $end_date = null;
if ($start_date && $end_date && $start_date > $end_date) {
    backWithError('Ngày bắt đầu không được lớn hơn ngày kết thúc.');
}

if ($scope === 'category' && !$danhmuc_id) {
    backWithError('Bạn chọn phạm vi theo danh mục thì phải chọn danh mục.');
}

if ($scope === 'products') {
    if (!is_array($product_ids) || count($product_ids) === 0) {
        backWithError('Bạn chọn phạm vi theo sản phẩm thì phải chọn ít nhất 1 sản phẩm.');
    }
}

if ($is_auto === 0 && $code === '') {
    backWithError('Bạn chọn dùng mã thì phải nhập mã khuyến mãi.');
}

$conn->begin_transaction();

try {
    $sql = "INSERT INTO km_ngayle
            (name, description, scope, danhmuc_id, discount_type, discount_value, max_discount, min_order_value, is_auto, code, priority, status, start_date, end_date)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)";
    $stmt = $conn->prepare($sql);
    if (!$stmt) throw new Exception("Prepare failed: " . $conn->error);

    // bind: s s s i s d d d i s i s s s
    $stmt->bind_param(
        "sssissdddissss",
        $name,
        $description,
        $scope,
        $danhmuc_id,
        $discount_type,
        $discount_value,
        $max_discount,
        $min_order_value,
        $is_auto,
        $code,
        $priority,
        $status,
        $start_date,
        $end_date
    );

    if (!$stmt->execute()) throw new Exception("Execute failed: " . $stmt->error);
    $km_id = $stmt->insert_id;
    $stmt->close();

    if ($scope === 'products') {
        $stmt2 = $conn->prepare("INSERT IGNORE INTO km_ngayle_products (km_id, sanpham_id) VALUES (?, ?)");
        if (!$stmt2) throw new Exception("Prepare failed: " . $conn->error);

        foreach ($product_ids as $pid) {
            $pid = trim((string)$pid);
            if ($pid === '') continue;
            $stmt2->bind_param("is", $km_id, $pid);
            if (!$stmt2->execute()) throw new Exception("Execute failed: " . $stmt2->error);
        }
        $stmt2->close();
    }

    $conn->commit();
    backWithSuccess('Đã tạo khuyến mãi ngày lễ thành công.');

} catch (Exception $e) {
    $conn->rollback();
    backWithError('Lỗi lưu khuyến mãi ngày lễ: ' . $e->getMessage());
}
