<?php
session_start();
require 'config.php';

if (empty($_SESSION['user_id'])) { http_response_code(401); exit('Unauthorized'); }
$role = $_SESSION['role'] ?? 'customer';
if ($role !== 'admin' && $role !== 'staff') { http_response_code(403); exit('Forbidden'); }

$sanpham_id = $_POST['sanpham_id'] ?? '';
$so_luong   = (int)($_POST['so_luong'] ?? 0);
$export_price = (float)($_POST['export_price'] ?? 0);
$ly_do = trim($_POST['ly_do'] ?? '');
$ghi_chu = trim($_POST['ghi_chu'] ?? '');

if ($sanpham_id === '' || $so_luong <= 0) {
  header('Location: quanlyadmin.php?page=phieuxuat&msg=invalid');
  exit;
}

$conn->begin_transaction();
try {
  // khóa dòng sản phẩm để tránh race
  $stmt = $conn->prepare("SELECT quantity FROM sanpham WHERE id=? FOR UPDATE");
  $stmt->bind_param("s", $sanpham_id);
  $stmt->execute();
  $rs = $stmt->get_result();
  $sp = $rs->fetch_assoc();
  if (!$sp) throw new Exception("Không tìm thấy sản phẩm");

  $currentQty = (int)$sp['quantity'];
  if ($so_luong > $currentQty) throw new Exception("Xuất vượt tồn kho");

  $stmt2 = $conn->prepare("
    INSERT INTO phieuxuat(sanpham_id, so_luong, export_price, ly_do, ghi_chu)
    VALUES(?,?,?,?,?)
  ");
  $stmt2->bind_param("sidss", $sanpham_id, $so_luong, $export_price, $ly_do, $ghi_chu);
  $stmt2->execute();

  $newQty = $currentQty - $so_luong;
  $stmt3 = $conn->prepare("UPDATE sanpham SET quantity=? WHERE id=?");
  $stmt3->bind_param("is", $newQty, $sanpham_id);
  $stmt3->execute();

  $conn->commit();
  header('Location: quanlyadmin.php?page=phieuxuat&msg=export_ok');
  exit;

} catch (Exception $e) {
  $conn->rollback();
  $_SESSION['phieuxuat_error'] = $e->getMessage();
  header('Location: quanlyadmin.php?page=phieuxuat');
  exit;
}
