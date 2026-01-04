<?php
require 'config.php';

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    header('Location: quanlyadmin.php?page=khuyenmai');
    exit;
}

// Lấy trạng thái hiện tại (is_active: 1 = active, 0 = inactive)
$stmt = $conn->prepare("SELECT is_active FROM khuyenmai WHERE id = ?");
$stmt->bind_param('i', $id);
$stmt->execute();
$stmt->bind_result($is_active);

if ($stmt->fetch()) {
    $stmt->close();

    // Đảo trạng thái: 1 -> 0, 0 -> 1
    $newActive = $is_active ? 0 : 1;

    $stmt2 = $conn->prepare("UPDATE khuyenmai SET is_active = ? WHERE id = ?");
    $stmt2->bind_param('ii', $newActive, $id);
    $stmt2->execute();
    $stmt2->close();
} else {
    $stmt->close();
}

// Redirect về trang quản lý khuyến mãi trong admin
header('Location: quanlyadmin.php?page=khuyenmai');
exit;
