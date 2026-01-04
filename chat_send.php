<?php
// chat_send.php – API gửi tin nhắn cho admin/staff trong trang quanlyadmin.php

session_start();
require 'config.php';

// Luôn trả JSON
header('Content-Type: application/json; charset=UTF-8');

// Chỉ cho phép POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Phương thức không hợp lệ (chỉ cho phép POST).'
    ]);
    exit;
}

// Bắt buộc phải đăng nhập
if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'Bạn chưa đăng nhập.'
    ]);
    exit;
}

// Chỉ admin & staff mới được gửi
$role = $_SESSION['role'] ?? 'customer';
if ($role !== 'admin' && $role !== 'staff') {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'message' => 'Bạn không có quyền gửi tin nhắn (chỉ admin / staff).'
    ]);
    exit;
}

// Lấy dữ liệu từ POST
$user_id = isset($_POST['user_id']) ? (int)$_POST['user_id'] : 0;
$message = trim($_POST['message'] ?? '');
$sender_type = $_POST['sender_type'] ?? ($role === 'admin' ? 'admin' : 'staff');

// Validate cơ bản
if ($user_id <= 0) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Thiếu hoặc sai user_id.'
    ]);
    exit;
}

if ($message === '') {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Nội dung tin nhắn không được để trống.'
    ]);
    exit;
}

// Chỉ chấp nhận 3 kiểu sender_type
$allowed_senders = ['customer', 'admin', 'staff'];
if (!in_array($sender_type, $allowed_senders, true)) {
    $sender_type = ($role === 'admin') ? 'admin' : 'staff';
}

// Xem bảng chat_messages có cột is_read không
$hasIsRead = false;
$checkCol = $conn->query("SHOW COLUMNS FROM chat_messages LIKE 'is_read'");
if ($checkCol && $checkCol->num_rows > 0) {
    $hasIsRead = true;
}
if ($checkCol) {
    $checkCol->free();
}

// Chuẩn bị câu lệnh INSERT giống với cấu trúc bảng
if ($hasIsRead) {
    // Bảng có cột is_read
    $stmt = $conn->prepare("
        INSERT INTO chat_messages (user_id, message, sender_type, is_read, created_at)
        VALUES (?,?,?,?,NOW())
    ");
    if (!$stmt) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Lỗi chuẩn bị câu lệnh (prepare): ' . $conn->error
        ]);
        exit;
    }
    $is_read = 1; // tin nhắn do admin gửi coi như đã đọc
    $stmt->bind_param("issi", $user_id, $message, $sender_type, $is_read);
} else {
    // Bảng KHÔNG có cột is_read (giống với admin_chat_send.php cũ)
    $stmt = $conn->prepare("
        INSERT INTO chat_messages (user_id, sender_type, message, created_at)
        VALUES (?,?,?,NOW())
    ");
    if (!$stmt) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Lỗi chuẩn bị câu lệnh (prepare): ' . $conn->error
        ]);
        exit;
    }
    $stmt->bind_param("iss", $user_id, $sender_type, $message);
}

// Thực thi
if (!$stmt->execute()) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Lỗi khi lưu tin nhắn: ' . $stmt->error
    ]);
    $stmt->close();
    exit;
}

$newId = $stmt->insert_id;
$stmt->close();

// Tạo dữ liệu tin nhắn trả về cho JS
$now = date('Y-m-d H:i:s');
$response = [
    'success' => true,
    'message' => 'Gửi tin nhắn thành công.',
    'message_data' => [
        'id'          => $newId,
        'user_id'     => $user_id,
        'message'     => $message,
        'sender_type' => $sender_type,
        'is_read'     => $hasIsRead ? 1 : null,
        'created_at'  => $now,
        'username'    => $_SESSION['username'] ?? '',
        'email'       => ''
    ]
];

echo json_encode($response);
exit;
