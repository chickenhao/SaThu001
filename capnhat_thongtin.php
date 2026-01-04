<?php
session_start();
require 'config.php';

// kiểm tra đăng nhập
if (!isset($_SESSION['currentUser'])) {
    // nếu chưa login thì cho về trang đăng nhập
    header('Location: dangnhap.php');
    exit;
}

$currentUser = $_SESSION['currentUser'];
$username = $currentUser['username'] ?? '';

if ($username === '') {
    die('Không xác định được tài khoản. Vui lòng đăng nhập lại.');
}

// Lấy dữ liệu từ form
$name   = trim($_POST['name']    ?? '');
$email  = trim($_POST['email']   ?? '');
$phone  = trim($_POST['phone']   ?? '');
$gender = trim($_POST['gender']  ?? '');
$addr   = trim($_POST['address'] ?? '');

// Lưu vào bảng khachhang
// Dùng INSERT ... ON DUPLICATE KEY UPDATE để:
// - nếu chưa có username -> thêm mới
// - nếu đã có -> cập nhật
$sql = "
  INSERT INTO khachhang (username, name, email, phone, address, gender)
  VALUES (?, ?, ?, ?, ?, ?)
  ON DUPLICATE KEY UPDATE
    name = VALUES(name),
    email = VALUES(email),
    phone = VALUES(phone),
    address = VALUES(address),
    gender = VALUES(gender)
";

$stmt = $conn->prepare($sql);
$stmt->bind_param('ssssss', $username, $name, $email, $phone, $addr, $gender);
$stmt->execute();
$stmt->close();

// Cập nhật lại session cho đồng bộ (để chỗ khác dùng $_SESSION vẫn thấy mới)
$_SESSION['currentUser']['name']    = $name;
$_SESSION['currentUser']['email']   = $email;
$_SESSION['currentUser']['phone']   = $phone;
$_SESSION['currentUser']['address'] = $addr;

// Quay lại trang thông tin khách hàng (kèm thông báo nếu muốn)
header('Location: thongtin_khach.php');
exit;
