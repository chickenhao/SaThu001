<?php
// chat_load_messages.php
session_start();
require 'config.php';

header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'not_login']);
    exit;
}

$conversationId = isset($_GET['conversation_id']) ? (int)$_GET['conversation_id'] : 0;
if ($conversationId <= 0) {
    echo json_encode(['success' => false, 'error' => 'invalid_conversation']);
    exit;
}

$stmt = $conn->prepare("
    SELECT id, conversation_id, sender, message, created_at
    FROM chat_messages
    WHERE conversation_id = ?
    ORDER BY created_at ASC, id ASC
");
if (!$stmt) {
    echo json_encode(['success' => false, 'error' => $conn->error]);
    exit;
}
$stmt->bind_param('i', $conversationId);
$stmt->execute();
$result   = $stmt->get_result();
$messages = [];

while ($row = $result->fetch_assoc()) {
    $messages[] = [
        'id'              => (int)$row['id'],
        'conversation_id' => (int)$row['conversation_id'],
        'sender'          => $row['sender'],     // 'customer' hoặc 'admin'
        'content'         => $row['message'],
        'created_at'      => $row['created_at'],
    ];
}
$stmt->close();

echo json_encode(['success' => true, 'messages' => $messages]);
