<?php
require 'config.php';

// ID sản phẩm là varchar (ví dụ: 'B123', 'SP001')
if (!isset($_GET['id']) || empty(trim($_GET['id']))) {
    header('Location: quanlyadmin.php?page=sanpham');
    exit;
}

$id     = trim($_GET['id']);
$status = isset($_GET['status']) ? trim($_GET['status']) : '';

// Chỉ cho phép một số trạng thái hợp lệ
$allowedStatuses = ['còn hàng', 'hết hàng'];
if (!in_array($status, $allowedStatuses, true)) {
    header('Location: quanlyadmin.php?page=sanpham&error=invalid_status');
    exit;
}

$stmt = $conn->prepare("UPDATE sanpham SET status = ? WHERE id = ?");
if ($stmt) {
    $stmt->bind_param("ss", $status, $id);
    if ($stmt->execute()) {
        header('Location: quanlyadmin.php?page=sanpham');
        exit;
    }
}

header('Location: quanlyadmin.php?page=sanpham&error=update_failed');
exit;


