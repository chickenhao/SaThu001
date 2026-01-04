<?php
session_start();
include 'config.php';

header('Content-Type: application/json; charset=utf-8');

/**
 * Lấy user từ SESSION với nhiều kiểu đặt tên khác nhau
 */
function getUserFromSession() {
    if (!empty($_SESSION['currentUser'])) {
        return $_SESSION['currentUser'];
    }
    if (!empty($_SESSION['user'])) {
        return $_SESSION['user'];
    }
    // Nếu bạn lưu kiểu: $_SESSION['username'], $_SESSION['user_id']
    if (!empty($_SESSION['username']) || !empty($_SESSION['user_id'])) {
        return [
            'id'       => isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0,
            'username' => isset($_SESSION['username']) ? $_SESSION['username'] : 'Khách'
        ];
    }
    return null;
}

// ==== LẤY THÔNG TIN USER ====

// 1. Ưu tiên SESSION
$currentUser = getUserFromSession();
$userId   = 0;
$username = 'Khách';

if ($currentUser) {
    if (isset($currentUser['id'])) {
        $userId = (int)$currentUser['id'];
    } elseif (isset($currentUser['user_id'])) {
        $userId = (int)$currentUser['user_id'];
    }

    if (isset($currentUser['username'])) {
        $username = $currentUser['username'];
    } elseif (isset($currentUser['email'])) {
        $username = $currentUser['email'];
    }
}

// 2. Nếu SESSION chưa có id ⇒ dùng dữ liệu gửi từ JS (localStorage)
if ($userId <= 0 && isset($_REQUEST['frontend_user_id'])) {
    $userId = (int)$_REQUEST['frontend_user_id'];
}
if (isset($_REQUEST['frontend_username']) && $_REQUEST['frontend_username'] !== '') {
    $username = $_REQUEST['frontend_username'];
}

// Nếu vẫn không có userId ⇒ coi như chưa đăng nhập
if ($userId <= 0) {
    http_response_code(401);
    echo json_encode([
        'status'  => 'error',
        'message' => 'Vui lòng đăng nhập để chat với hỗ trợ.'
    ]);
    exit;
}

$action = isset($_GET['action']) ? $_GET['action'] : (isset($_POST['action']) ? $_POST['action'] : '');

if ($action === 'send') {
    // GỬI TIN NHẮN
    $msg = isset($_POST['message']) ? trim($_POST['message']) : '';
    if ($msg === '') {
        echo json_encode(['status' => 'error', 'message' => 'Nội dung trống.']);
        exit;
    }

    // Cho phép admin dùng chung API này bằng cách gửi sender_type=admin
    $senderType = 'user';
    if (!empty($_POST['sender_type']) && $_POST['sender_type'] === 'admin') {
        $senderType = 'admin';
    }

    $sql = "INSERT INTO chat_messages (user_id, sender_type, message, created_at)
            VALUES (?, ?, ?, NOW())";
    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param("iss", $userId, $senderType, $msg);
        if ($stmt->execute()) {
            echo json_encode([
                'status' => 'success',
                'message' => 'Đã gửi',
                'id'      => $stmt->insert_id
            ]);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Không gửi được tin nhắn.']);
        }
        $stmt->close();
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Lỗi hệ thống.']);
    }
    exit;
}

if ($action === 'load') {
    // LẤY DANH SÁCH TIN NHẮN (của user hiện tại)
    $lastId = isset($_GET['last_id']) ? (int)$_GET['last_id'] : 0;

    $sql = "SELECT id, sender_type, message,
                   DATE_FORMAT(created_at, '%H:%i %d/%m') AS time
            FROM chat_messages
            WHERE user_id = ? AND id > ?
            ORDER BY id ASC";
    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param("ii", $userId, $lastId);
        $stmt->execute();
        $rs = $stmt->get_result();
        $rows = [];
        while ($r = $rs->fetch_assoc()) {
            $rows[] = $r;
        }
        $stmt->close();

        echo json_encode([
            'status'   => 'success',
            'messages' => $rows
        ]);
        exit;
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Lỗi tải tin nhắn.']);
        exit;
    }
}

// Nếu action không hợp lệ
echo json_encode(['status' => 'error', 'message' => 'Hành động không hợp lệ.']);
exit;
