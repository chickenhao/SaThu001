<?php
require 'config.php';

// Chỉ nhận yêu cầu GET có id hợp lệ
if (!isset($_GET['id']) || !ctype_digit($_GET['id'])) {
    header('Location: donhang_list.php');
    exit;
}

$donhang_id = (int)$_GET['id'];

// Bắt đầu transaction (nếu MySQL có hỗ trợ InnoDB)
$conn->begin_transaction();

try {
    // 1. Xóa chi tiết đơn hàng
    $stmtCt = $conn->prepare("DELETE FROM donhang_chitiet WHERE donhang_id = ?");
    if (!$stmtCt) {
        throw new Exception('Lỗi prepare chi tiết: ' . $conn->error);
    }
    $stmtCt->bind_param('i', $donhang_id);
    $stmtCt->execute();
    $stmtCt->close();

    // 2. Xóa bản ghi trong donhang
    $stmtDh = $conn->prepare("DELETE FROM donhang WHERE id = ?");
    if (!$stmtDh) {
        throw new Exception('Lỗi prepare đơn hàng: ' . $conn->error);
    }
    $stmtDh->bind_param('i', $donhang_id);
    $stmtDh->execute();
    $stmtDh->close();

    // Commit nếu mọi thứ ok
    $conn->commit();

} catch (Exception $e) {
    // Có lỗi -> rollback để tránh xóa dở
    $conn->rollback();
    // Bạn có thể log ra file, còn giờ tạm die cho dễ debug:
    die('Không thể xóa đơn hàng: ' . $e->getMessage());
}

// Quay lại danh sách đơn hàng
header('Location: donhang_list.php');
exit;
