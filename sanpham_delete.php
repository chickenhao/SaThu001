<?php
session_start();
require 'config.php';

// Tắt chế độ ném exception của MySQLi (nếu config.php đã bật trước đó)
if (function_exists('mysqli_report')) {
    mysqli_report(MYSQLI_REPORT_OFF);
}

// Trả về JSON cho phía JavaScript
header('Content-Type: application/json; charset=utf-8');

// ===== 1. Kiểm tra đăng nhập & quyền =====
$role = $_SESSION['role'] ?? 'customer';
if (empty($_SESSION['user_id']) || !in_array($role, ['admin', 'staff'])) {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'message' => 'Bạn không có quyền thực hiện chức năng này.'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// ===== 2. Lấy ID sản phẩm từ POST =====
$id = $_POST['id'] ?? '';
$id = trim($id);

if ($id === '') {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Thiếu ID sản phẩm cần xóa.'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// ===== 3. Thử xoá cứng sản phẩm khỏi bảng sanpham =====
$stmt = $conn->prepare('DELETE FROM sanpham WHERE id = ?');
if (!$stmt) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Lỗi hệ thống: không chuẩn bị được câu lệnh SQL.'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$stmt->bind_param('s', $id);
$ok = $stmt->execute();

if (!$ok) {
    // 1451: Cannot delete or update a parent row: a foreign key constraint fails
    if ($conn->errno == 1451) {
        // ===== 4. Fallback: không xoá được thì chuyển sang trạng thái "hết hàng" =====
        $newStatus = 'hết hàng';
        $stmt2 = $conn->prepare('UPDATE sanpham SET status = ? WHERE id = ?');
        if ($stmt2) {
            $stmt2->bind_param('ss', $newStatus, $id);
            $ok2 = $stmt2->execute();

            if ($ok2 && $stmt2->affected_rows > 0) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Sản phẩm đang được sử dụng trong đơn hàng nên không thể xoá hoàn toàn. Hệ thống đã chuyển trạng thái sản phẩm sang "hết hàng".'
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }
        }

        // Nếu update cũng lỗi:
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Sản phẩm đang được sử dụng trong đơn hàng nên không thể xoá. Đồng thời cập nhật trạng thái "hết hàng" cũng thất bại, vui lòng kiểm tra lại database.'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Lỗi DELETE khác
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Lỗi khi xoá sản phẩm: ' . $conn->error
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// Không có dòng nào bị xoá (ID không tồn tại)
if ($stmt->affected_rows === 0) {
    http_response_code(404);
    echo json_encode([
        'success' => false,
        'message' => 'Không tìm thấy sản phẩm cần xóa.'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// ===== 5. Xoá thành công hoàn toàn =====
echo json_encode([
    'success' => true,
    'message' => 'Đã xóa sản phẩm khỏi hệ thống.'
], JSON_UNESCAPED_UNICODE);
