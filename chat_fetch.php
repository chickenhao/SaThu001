<?php
// chat_fetch.php
session_start();
header('Content-Type: application/json; charset=utf-8');

require 'config.php';

if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'Bạn chưa đăng nhập.'
    ]);
    exit;
}

$role = $_SESSION['role'] ?? 'customer';

// Nếu là admin/staff và truyền ?user_id=... → xem cuộc chat với khách đó
if (in_array($role, ['admin', 'staff'], true) && isset($_GET['user_id'])) {
    $user_id = (int) $_GET['user_id'];
} else {
    // Nếu là khách → luôn dùng chính id của mình
    $user_id = (int) $_SESSION['user_id'];
}

if ($user_id <= 0) {
    echo json_encode([
        'success' => false,
        'message' => 'Thiếu user_id.'
    ]);
    exit;
}

// Lấy TẤT CẢ tin nhắn của user này (cả customer/admin/staff)
$stmt = $conn->prepare("
    SELECT id, user_id, sender_type, sender_id, message, is_read, created_at
    FROM chat_messages
    WHERE user_id = ?
    ORDER BY created_at ASC, id ASC
");
if (!$stmt) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Lỗi SQL: ' . $conn->error
    ]);
    exit;
}

$stmt->bind_param('i', $user_id);
$stmt->execute();
$res = $stmt->get_result();

$messages = [];
while ($row = $res->fetch_assoc()) {
    $messages[] = [
        'id'          => (int)$row['id'],
        'user_id'     => (int)$row['user_id'],
        'sender_type' => $row['sender_type'],
        'sender_id'   => (int)$row['sender_id'],
        'message'     => $row['message'],
        'is_read'     => (int)$row['is_read'],
        'created_at'  => $row['created_at'],
    ];
}
$stmt->close();

// Nếu là admin/staff → đánh dấu đã đọc tin của khách
if (in_array($role, ['admin', 'staff'], true)) {
    $conn->query("
        UPDATE chat_messages
        SET is_read = 1
        WHERE user_id = {$user_id} AND sender_type = 'customer'
    ");
}

echo json_encode([
    'success'  => true,
    'messages' => $messages
]);
exit;
