<?php
session_start();
require 'config.php';

header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
  echo json_encode(['success' => false, 'message' => 'Không có quyền.']); exit;
}

$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
if ($id <= 0) {
  echo json_encode(['success' => false, 'message' => 'ID không hợp lệ.']); exit;
}

// lấy trạng thái hiện tại
$stmt = $conn->prepare("SELECT status FROM km_sanpham WHERE id=? LIMIT 1");
$stmt->bind_param("i", $id);
$stmt->execute();
$rs = $stmt->get_result();
$row = $rs ? $rs->fetch_assoc() : null;
$stmt->close();

if (!$row) {
  echo json_encode(['success' => false, 'message' => 'Không tìm thấy khuyến mãi.']); exit;
}

$cur = $row['status'] === 'active' ? 'active' : 'inactive';
$new = $cur === 'active' ? 'inactive' : 'active';

$upd = $conn->prepare("UPDATE km_sanpham SET status=? WHERE id=?");
$upd->bind_param("si", $new, $id);
$ok = $upd->execute();
$upd->close();

if (!$ok) {
  echo json_encode(['success' => false, 'message' => 'Cập nhật thất bại.']); exit;
}

echo json_encode(['success' => true, 'new_status' => $new]);
