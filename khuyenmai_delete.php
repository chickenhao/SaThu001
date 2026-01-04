<?php
require 'config.php';

// Chỉ cho phép dùng GET với id là số
if (!isset($_GET['id']) || !ctype_digit($_GET['id'])) {
    header('Location: quanlyadmin.php?page=khuyenmai');
    exit;
}

$id = (int)$_GET['id'];

// Xóa khuyến mãi
$stmt = $conn->prepare("DELETE FROM khuyenmai WHERE id = ?");
if (!$stmt) {
    die('Lỗi hệ thống: ' . $conn->error);
}
$stmt->bind_param('i', $id);
$stmt->execute();
$stmt->close();

// Redirect về trang quản lý khuyến mãi trong admin
header('Location: quanlyadmin.php?page=khuyenmai');
exit;
