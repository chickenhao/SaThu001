<?php
session_start();
if (!isset($_SESSION["role"]) || $_SESSION["role"] !== "admin") {
    header("Location: dangnhap.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="vi">
<head><meta charset="UTF-8"><title>Admin</title></head>
<body>
<h1>Xin chào Admin <?php echo htmlspecialchars($_SESSION["username"]); ?></h1>
<p><a href="logout.php">Đăng xuất</a></p>
</body>
</html>
