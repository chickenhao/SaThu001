<?php
session_start();
require 'config.php';

if (empty($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin','staff'])) {
    header('Location: dangnhap.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: quanlyadmin.php?page=nhaphang');
    exit;
}

// KHÔNG dùng intval ở đây nữa
$sanpham_id_raw = $_POST['sanpham_id'] ?? '';
$so_luong       = isset($_POST['so_luong']) ? (int)$_POST['so_luong'] : 0;
$import_price   = isset($_POST['import_price']) ? (int)$_POST['import_price'] : 0;
$ghi_chu        = trim($_POST['ghi_chu'] ?? '');

// Nếu ID là string (SP01, B001, …) thì giữ nguyên:
$sanpham_id = trim($sanpham_id_raw);

// Validate
if ($sanpham_id === '' || $so_luong <= 0 || $import_price <= 0) {
    $_SESSION['nhaphang_error'] = "Bạn chưa chọn sản phẩm hợp lệ hoặc nhập thiếu dữ liệu.";
    header('Location: quanlyadmin.php?page=nhaphang');
    exit;
}

// Debug nếu cần
// error_log("sanpham_id={$sanpham_id}, so_luong={$so_luong}, import_price={$import_price}");

try {
    $conn->begin_transaction();

    // 1. Kiểm tra sản phẩm có tồn tại không
    $stmt = $conn->prepare("SELECT id FROM sanpham WHERE id = ?");
    $stmt->bind_param("s", $sanpham_id);   // dùng "s" vì là string
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res->num_rows === 0) {
        $conn->rollback();
        $_SESSION['nhaphang_error'] = "Sản phẩm không tồn tại.";
        header('Location: quanlyadmin.php?page=nhaphang');
        exit;
    }
    $stmt->close();

    // 2. Thêm phiếu nhập
    $stmt = $conn->prepare("
        INSERT INTO nhaphang (sanpham_id, so_luong, import_price, ghi_chu, created_at)
        VALUES (?, ?, ?, ?, NOW())
    ");
    $stmt->bind_param("siis", $sanpham_id, $so_luong, $import_price, $ghi_chu);
    $stmt->execute();
    $stmt->close();

    // 3. Cập nhật tồn kho + giá nhập mới cho sản phẩm
    $stmt = $conn->prepare("
        UPDATE sanpham
        SET quantity = quantity + ?, import_price = ?
        WHERE id = ?
    ");
    $stmt->bind_param("iis", $so_luong, $import_price, $sanpham_id);
    $stmt->execute();
    $stmt->close();

    $conn->commit();

    header('Location: quanlyadmin.php?page=nhaphang&msg=import_ok');
    exit;

} catch (Exception $e) {
    $conn->rollback();
    $_SESSION['nhaphang_error'] = "Lỗi lưu nhập hàng: " . $e->getMessage();
    header('Location: quanlyadmin.php?page=nhaphang');
    exit;
}
