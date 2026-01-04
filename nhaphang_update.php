<?php
// nhaphang_store.php
session_start();
require 'config.php';

// Kiểm tra đăng nhập + quyền (admin / staff mới được nhập hàng)
if (empty($_SESSION['user_id'])) {
    header('Location: dangnhap.php?redirect=quanlyadmin.php?page=nhaphang');
    exit;
}
$role = $_SESSION['role'] ?? 'customer';
if ($role !== 'admin' && $role !== 'staff') {
    header('Location: trangchu.php');
    exit;
}

// Chỉ chấp nhận POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: quanlyadmin.php?page=nhaphang');
    exit;
}

// Lấy dữ liệu từ form
$sanpham_id   = isset($_POST['sanpham_id'])   ? (int)$_POST['sanpham_id']   : 0;
$so_luong     = isset($_POST['so_luong'])     ? (int)$_POST['so_luong']     : 0;
$import_price = isset($_POST['import_price']) ? (float)$_POST['import_price'] : 0;
$ghi_chu      = trim($_POST['ghi_chu'] ?? '');

// ===== VALIDATE DỮ LIỆU =====
$errors = [];

if ($sanpham_id <= 0) {
    $errors[] = 'Vui lòng chọn sản phẩm hợp lệ.';
}
if ($so_luong <= 0) {
    $errors[] = 'Số lượng nhập phải lớn hơn 0.';
}
if ($import_price < 0) {
    $errors[] = 'Giá nhập phải lớn hơn hoặc bằng 0.';
}

// Nếu lỗi -> lưu vào session, quay lại form thêm phiếu nhập
if (!empty($errors)) {
    $_SESSION['nhaphang_error'] = implode('<br>', $errors);
    header('Location: quanlyadmin.php?page=themnhaphang');
    exit;
}

// ===== BẮT ĐẦU TRANSACTION =====
$conn->begin_transaction();

try {
    // 1. Thêm bản ghi vào bảng nhaphang
    $stmt = $conn->prepare("
        INSERT INTO nhaphang (sanpham_id, so_luong, import_price, ghi_chu, created_at)
        VALUES (?, ?, ?, ?, NOW())
    ");
    if (!$stmt) {
        throw new Exception('Lỗi prepare INSERT: ' . $conn->error);
    }
    $stmt->bind_param('iids', $sanpham_id, $so_luong, $import_price, $ghi_chu);
    if (!$stmt->execute()) {
        throw new Exception('Lỗi execute INSERT: ' . $stmt->error);
    }
    $stmt->close();

    // 2. Cộng tồn kho cho sản phẩm
    $stmt2 = $conn->prepare("
        UPDATE sanpham
        SET quantity = quantity + ?
        WHERE id = ?
    ");
    if (!$stmt2) {
        throw new Exception('Lỗi prepare UPDATE: ' . $conn->error);
    }
    $stmt2->bind_param('ii', $so_luong, $sanpham_id);
    if (!$stmt2->execute()) {
        throw new Exception('Lỗi execute UPDATE: ' . $stmt2->error);
    }
    $stmt2->close();

    // 3. OK -> commit
    $conn->commit();

    // Chuyển về trang quản lý nhập hàng + thông báo thành công
    header('Location: quanlyadmin.php?page=nhaphang&msg=import_ok');
    exit;

} catch (Exception $e) {
    // Có lỗi -> rollback
    $conn->rollback();

    // Ghi lỗi ra session để hiển thị
    $_SESSION['nhaphang_error'] = 'Không thể lưu phiếu nhập. Lỗi hệ thống: ' . htmlspecialchars($e->getMessage());

    // Quay lại form thêm phiếu nhập
    header('Location: quanlyadmin.php?page=themnhaphang');
    exit;
}
