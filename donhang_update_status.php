<?php
session_start();
require 'config.php';

if (empty($_SESSION['user_id'])) {
  header('Location: dangnhap.php');
  exit;
}
$role = $_SESSION['role'] ?? 'customer';
if ($role !== 'admin' && $role !== 'staff') {
  header('Location: trangchu.php');
  exit;
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$newStatus = $_GET['status'] ?? '';
$allow = ['pending','paid','shipping','completed','cancelled'];
if ($id <= 0 || !in_array($newStatus, $allow, true)) {
  header('Location: quanlyadmin.php?page=donhang');
  exit;
}

$conn->begin_transaction();

try {
  // Lấy trạng thái cũ + is_exported
  $stmt = $conn->prepare("SELECT trang_thai, is_exported FROM donhang WHERE id=? FOR UPDATE");
  $stmt->bind_param("i", $id);
  $stmt->execute();
  $old = $stmt->get_result()->fetch_assoc();
  $stmt->close();

  if (!$old) throw new Exception("Không tìm thấy đơn hàng.");

  // Update trạng thái
  $stmt = $conn->prepare("UPDATE donhang SET trang_thai=? WHERE id=?");
  $stmt->bind_param("si", $newStatus, $id);
  if (!$stmt->execute()) throw new Exception("Update trạng thái thất bại.");
  $stmt->close();

  $exportStatuses = ['paid','shipping','completed'];

  // Nếu đơn chuyển sang trạng thái cần xuất kho và chưa xuất
  if (in_array($newStatus, $exportStatuses, true) && (int)$old['is_exported'] === 0) {

    // Lấy chi tiết đơn
    $stmt = $conn->prepare("
      SELECT sanpham_id, so_luong
      FROM donhang_chitiet
      WHERE donhang_id=?
      FOR UPDATE
    ");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    if (!$items) throw new Exception("Đơn hàng không có sản phẩm.");

    // 1) Check tồn kho đủ trước
    foreach ($items as $it) {
      $pid = (string)$it['sanpham_id'];
      $qtyNeed = (int)$it['so_luong'];

      $stmt = $conn->prepare("SELECT quantity, name FROM sanpham WHERE id=? FOR UPDATE");
      $stmt->bind_param("s", $pid);
      $stmt->execute();
      $sp = $stmt->get_result()->fetch_assoc();
      $stmt->close();

      if (!$sp) throw new Exception("Không tìm thấy sản phẩm ID: {$pid}");
      $stock = (int)$sp['quantity'];
      if ($qtyNeed > $stock) {
        throw new Exception("Không đủ tồn kho: {$sp['name']} (còn {$stock}, cần {$qtyNeed})");
      }
    }

    // 2) Trừ tồn kho
    foreach ($items as $it) {
      $pid = (string)$it['sanpham_id'];
      $qtyNeed = (int)$it['so_luong'];

      $stmt = $conn->prepare("UPDATE sanpham SET quantity = GREATEST(quantity - ?, 0) WHERE id=?");
      $stmt->bind_param("is", $qtyNeed, $pid);
      if (!$stmt->execute()) throw new Exception("Trừ tồn kho thất bại (SP {$pid}).");
      $stmt->close();
    }

    // 3) Đánh dấu đã xuất
    $stmt = $conn->prepare("UPDATE donhang SET is_exported=1, exported_at=NOW() WHERE id=?");
    $stmt->bind_param("i", $id);
    if (!$stmt->execute()) throw new Exception("Không cập nhật is_exported.");
    $stmt->close();
  }

  // (Tuỳ chọn) Nếu huỷ đơn sau khi đã xuất → hoàn kho
  if ($newStatus === 'cancelled' && (int)$old['is_exported'] === 1) {
    $stmt = $conn->prepare("SELECT sanpham_id, so_luong FROM donhang_chitiet WHERE donhang_id=? FOR UPDATE");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    foreach ($items as $it) {
      $pid = (string)$it['sanpham_id'];
      $qty = (int)$it['so_luong'];

      $stmt = $conn->prepare("UPDATE sanpham SET quantity = quantity + ? WHERE id=?");
      $stmt->bind_param("is", $qty, $pid);
      if (!$stmt->execute()) throw new Exception("Hoàn kho thất bại (SP {$pid}).");
      $stmt->close();
    }

    $stmt = $conn->prepare("UPDATE donhang SET is_exported=0, exported_at=NULL WHERE id=?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();
  }

  $conn->commit();
  header('Location: quanlyadmin.php?page=donhang');
  exit;

} catch (Exception $e) {
  $conn->rollback();
  // Bạn có thể set session flash message nếu muốn
  $_SESSION['nhaphang_error'] = $e->getMessage();
  header('Location: quanlyadmin.php?page=donhang');
  exit;
}
