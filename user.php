<?php
session_start();
require 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: dangnhap.php");
    exit();
}

$userId = $_SESSION['user_id'];

$sql = "SELECT username, email, phone, role, created_at FROM users WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();

$user = $result->fetch_assoc();
$stmt->close();
?>
<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <title>Trang User</title>
    <style>
        body { font-family: Arial, sans-serif; background:#f3f3f3; }
        .container { width: 500px; margin: 60px auto; background:#fff; padding:20px; border-radius:8px; box-shadow:0 0 8px rgba(0,0,0,.1); }
        h1 { margin-bottom:15px; }
        .item { margin-bottom:8px; }
        .label { font-weight:bold; }
        a { text-decoration:none; color:red; }
    </style>
</head>
<body>
<div class="container">
    <h1>Xin chào, <?php echo htmlspecialchars($user['username']); ?></h1>

    <div class="item">
        <span class="label">Email:</span>
        <?php echo htmlspecialchars($user['email']); ?>
    </div>
    <div class="item">
        <span class="label">Số điện thoại:</span>
        <?php echo htmlspecialchars($user['phone']); ?>
    </div>
    <div class="item">
        <span class="label">Quyền:</span>
        <?php echo htmlspecialchars($user['role']); ?>
    </div>
    <div class="item">
        <span class="label">Ngày tạo:</span>
        <?php echo htmlspecialchars($user['created_at']); ?>
    </div>

    <p style="margin-top:20px;"><a href="logout.php">Đăng xuất</a></p>
</div>
</body>
</html>
