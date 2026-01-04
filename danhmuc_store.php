<?php
session_start();
require 'config.php';

// Kiểm tra quyền truy cập - chỉ admin mới được thêm danh mục
if (empty($_SESSION['user_id'])) {
    header('Location: dangnhap.php?redirect=quanlyadmin.php');
    exit;
}

$userRole = $_SESSION['role'] ?? 'customer';
if ($userRole !== 'admin') {
    header('Location: quanlyadmin.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');

    if (empty($name)) {
        header('Location: quanlyadmin.php?page=themdanhmuc&error=name_required');
        exit;
    }

    // Lưu danh mục vào database (không có ảnh)
    $stmt = $conn->prepare("INSERT INTO danhmuc (name, description) VALUES (?, ?)");
    if ($stmt) {
        $stmt->bind_param("ss", $name, $description);
        if ($stmt->execute()) {
            header('Location: quanlyadmin.php?page=danhmuc');
            exit;
        } else {
            header('Location: quanlyadmin.php?page=themdanhmuc&error=insert_failed');
            exit;
        }
    } else {
        header('Location: quanlyadmin.php?page=themdanhmuc&error=prepare_failed');
        exit;
    }
} else {
    header('Location: quanlyadmin.php?page=danhmuc');
    exit;
}
?>

