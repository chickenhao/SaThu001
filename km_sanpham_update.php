<?php
session_start();
require 'config.php';

if (empty($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
  header('Location: trangchu.php'); exit;
}

$id = (int)($_POST['id'] ?? 0);
if ($id <= 0) { die('ID không hợp lệ'); }

$name = trim($_POST['name'] ?? '');
$discount_type = $_POST['discount_type'] ?? 'amount';
$discount_value = (float)($_POST['discount_value'] ?? 0);
$max_discount = (float)($_POST['max_discount'] ?? 0);
$min_qty = (int)($_POST['min_qty'] ?? 1);
$min_order_value = (float)($_POST['min_order_value'] ?? 0);
$priority = (int)($_POST['priority'] ?? 0);
$status = ($_POST['status'] ?? 'inactive') === 'active' ? 'active' : 'inactive';
$start_date = $_POST['start_date'] ?? null;
$end_date = $_POST['end_date'] ?? null;

$product_ids = $_POST['product_ids'] ?? [];
if (!is_array($product_ids) || count($product_ids) === 0) {
  die('Vui lòng chọn ít nhất 1 sản phẩm.');
}

$stmt = $conn->prepare("
  UPDATE km_sanpham
  SET name=?, discount_type=?, discount_value=?, max_discount=?, min_qty=?, min_order_value=?, priority=?, status=?, start_date=?, end_date=?
  WHERE id=?
");
$stmt->bind_param(
  "ssddidisssi",
  $name, $discount_type, $discount_value, $max_discount, $min_qty, $min_order_value, $priority, $status, $start_date, $end_date, $id
);
$stmt->execute();
$stmt->close();

// reset items
$del = $conn->prepare("DELETE FROM km_sanpham_items WHERE km_id=?");
$del->bind_param("i", $id);
$del->execute();
$del->close();

$ins = $conn->prepare("INSERT INTO km_sanpham_items(km_id, sanpham_id) VALUES(?, ?)");
foreach ($product_ids as $pid) {
  $pid = (int)$pid;
  if ($pid <= 0) continue;
  $ins->bind_param("ii", $id, $pid);
  $ins->execute();
}
$ins->close();

header('Location: quanlyadmin.php?page=khuyenmai_sanpham');
