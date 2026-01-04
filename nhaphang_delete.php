<?php
session_start();
require 'config.php';

if (empty($_SESSION['user_id'])) {
    header('Location: dangnhap.php?redirect=quanlyadmin.php?page=nhaphang');
    exit;
}

$userRole = $_SESSION['role'] ?? 'customer';
if ($userRole !== 'admin' && $userRole !== 'staff') {
    header('Location: trangchu.php');
    exit;
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    header('Location: quanlyadmin.php?page=nhaphang');
    exit;
}

$conn->begin_transaction();

try {
    // Lấy phiếu nhập
    $stmt = $conn->prepare("SELECT sanpham_id, so_luong, import_price FROM nhaphang WHERE id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($row) {
        $sanpham_id = (int)$row['sanpham_id'];
        $qty        = (int)$row['so_luong'];

        // Trừ lại số lượng trong bảng sanpham (tuỳ bạn có muốn trừ hay không)
        $stmt2 = $conn->prepare("UPDATE sanpham SET quantity = quantity - ? WHERE id = ?");
        $stmt2->bind_param('ii', $qty, $sanpham_id);
        $stmt2->execute();
        $stmt2->close();
    }

    // Xóa phiếu nhập
    $stmt3 = $conn->prepare("DELETE FROM nhaphang WHERE id = ?");
    $stmt3->bind_param('i', $id);
    $stmt3->execute();
    $stmt3->close();

    $conn->commit();
    header('Location: quanlyadmin.php?page=nhaphang&msg=delete_ok');
    exit;

} catch (Exception $e) {
    $conn->rollback();
    header('Location: quanlyadmin.php?page=nhaphang&error=server_error');
    exit;
}
