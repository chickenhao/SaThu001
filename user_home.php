<?php
session_start();
if (!isset($_SESSION["role"])) {
    header("Location: dangnhap.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="vi">
<head><meta charset="UTF-8"><title>Trang người dùng</title></head>
<body>
<h1>Xin chào <?php echo htmlspecialchars($_SESSION["username"]); ?> (<?php echo htmlspecialchars($_SESSION["role"]); ?>)</h1>
<p><a href="logout.php">Đăng xuất</a></p>
</body>
</html>
