<?php
session_start();
require 'config.php';

// Kiểm tra quyền truy cập - chỉ admin mới được sửa danh mục
if (empty($_SESSION['user_id'])) {
    header('Location: dangnhap.php?redirect=quanlyadmin.php');
    exit;
}

$userRole = $_SESSION['role'] ?? 'customer';
if ($userRole !== 'admin') {
    header('Location: quanlyadmin.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: quanlyadmin.php?page=danhmuc');
    exit;
}

$id = $_POST['id'] ?? '';
$name = trim($_POST['name'] ?? '');
$description = trim($_POST['description'] ?? '');

if (empty($id) || empty($name)) {
    header('Location: quanlyadmin.php?page=danhmuc&error=invalid_data');
    exit;
}

// Cập nhật database (không có ảnh)
$stmt = $conn->prepare("UPDATE danhmuc SET name = ?, description = ? WHERE id = ?");
if ($stmt) {
    $stmt->bind_param("ssi", $name, $description, $id);
    if ($stmt->execute()) {
        $redirect = $_POST['redirect'] ?? 'quanlyadmin.php?page=danhmuc';
        header('Location: ' . $redirect);
        exit;
    } else {
        header('Location: quanlyadmin.php?page=danhmuc&error=update_failed');
        exit;
    }
} else {
    header('Location: quanlyadmin.php?page=danhmuc&error=prepare_failed');
    exit;
}
?>

