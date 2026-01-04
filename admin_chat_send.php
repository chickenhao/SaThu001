<?php
session_start();
include 'config.php';

// TODO: kiểm tra quyền admin nếu cần
// if (empty($_SESSION['is_admin'])) { die('Không có quyền.'); }

$userId  = isset($_POST['user_id']) ? (int)$_POST['user_id'] : 0;
$message = isset($_POST['message']) ? trim($_POST['message']) : '';

if ($userId <= 0 || $message === '') {
    die('Thiếu user_id hoặc nội dung.');
}

$senderType = 'admin';

$sql = "INSERT INTO chat_messages (user_id, sender_type, message, created_at)
        VALUES (?, ?, ?, NOW())";
if ($stmt = $conn->prepare($sql)) {
    $stmt->bind_param("iss", $userId, $senderType, $message);
    $stmt->execute();
    $stmt->close();
}

// quay lại lại trang admin_chat
header('Location: admin_chat.php?user_id=' . $userId);
exit;
