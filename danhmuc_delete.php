<?php
session_start();
require 'config.php';

// Kiểm tra quyền truy cập - chỉ admin mới được xóa danh mục
if (empty($_SESSION['user_id'])) {
    header('Location: dangnhap.php?redirect=quanlyadmin.php');
    exit;
}

$userRole = $_SESSION['role'] ?? 'customer';
if ($userRole !== 'admin') {
    header('Location: quanlyadmin.php');
    exit;
}

// Kiểm tra ID hợp lệ
if (!isset($_GET['id']) || empty(trim($_GET['id']))) {
    header('Location: quanlyadmin.php?page=danhmuc&error=invalid_id');
    exit;
}

$id = (int)trim($_GET['id']);

// Kiểm tra xem danh mục có đang được sử dụng trong sản phẩm không
$checkStmt = $conn->prepare("SELECT COUNT(*) AS count FROM sanpham WHERE category = (SELECT name FROM danhmuc WHERE id = ?)");
if ($checkStmt) {
    $checkStmt->bind_param("i", $id);
    $checkStmt->execute();
    $result = $checkStmt->get_result();
    $row = $result->fetch_assoc();
    
    if ($row && $row['count'] > 0) {
        // Có sản phẩm đang sử dụng danh mục này
        header('Location: quanlyadmin.php?page=danhmuc&error=category_in_use&count=' . $row['count']);
        exit;
    }
}

// Xóa danh mục
$stmt = $conn->prepare("DELETE FROM danhmuc WHERE id = ?");
if ($stmt) {
    $stmt->bind_param("i", $id);
    if ($stmt->execute()) {
        header('Location: quanlyadmin.php?page=danhmuc');
        exit;
    } else {
        header('Location: quanlyadmin.php?page=danhmuc&error=delete_failed');
        exit;
    }
} else {
    header('Location: quanlyadmin.php?page=danhmuc&error=prepare_failed');
    exit;
}
?>

