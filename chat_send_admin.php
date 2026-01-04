<?php
// chat_send_admin.php
session_start();
require 'config.php';

header('Content-Type: application/json; charset=utf-8');

// Chỉ cho admin / staff gửi
$role = $_SESSION['role'] ?? 'customer';
if (!in_array($role, ['admin', 'staff'])) {
    echo json_encode(['success' => false, 'error' => 'no_permission']);
    exit;
}

$conversationId = isset($_POST['conversation_id']) ? (int)$_POST['conversation_id'] : 0;
$message        = trim($_POST['message'] ?? '');

if ($conversationId <= 0 || $message === '') {
    echo json_encode(['success' => false, 'error' => 'invalid_input']);
    exit;
}

// KIỂM TRA CUỘC TRÒ CHUYỆN CÓ TỒN TẠI KHÔNG
$stmt = $conn->prepare("SELECT id FROM chat_conversations WHERE id = ?");
if (!$stmt) {
    echo json_encode(['success' => false, 'error' => $conn->error]);
    exit;
}
$stmt->bind_param('i', $conversationId);
$stmt->execute();
$stmt->store_result();
if ($stmt->num_rows === 0) {
    $stmt->close();
    echo json_encode(['success' => false, 'error' => 'conversation_not_found']);
    exit;
}
$stmt->close();

// CHÈN TIN NHẮN MỚI CỦA ADMIN
$stmt = $conn->prepare("
    INSERT INTO chat_messages (conversation_id, sender, message, created_at)
    VALUES (?, 'admin', ?, NOW())
");
if (!$stmt) {
    echo json_encode(['success' => false, 'error' => $conn->error]);
    exit;
}
$stmt->bind_param('is', $conversationId, $message);
$ok = $stmt->execute();
if (!$ok) {
    $err = $stmt->error;
    $stmt->close();
    echo json_encode(['success' => false, 'error' => $err]);
    exit;
}
$messageId = $stmt->insert_id;
$stmt->close();

// CẬP NHẬT LAST MESSAGE CHO conversation
$stmt = $conn->prepare("
    UPDATE chat_conversations
    SET last_message = ?, last_sender = 'admin', last_at = NOW()
    WHERE id = ?
");
if ($stmt) {
    $stmt->bind_param('si', $message, $conversationId);
    $stmt->execute();
    $stmt->close();
}

// Trả JSON cho JS
echo json_encode([
    'success' => true,
    'message' => [
        'id'              => $messageId,
        'conversation_id' => $conversationId,
        'sender'          => 'admin',
        'content'         => $message,
        'created_at'      => date('Y-m-d H:i:s'),
    ]
]);
