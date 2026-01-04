<?php
/**
 * Script để thêm cột role và status vào bảng users
 * Chạy file này một lần để cập nhật database
 */

require 'config.php';

echo "<h2>Đang thêm cột role và status vào bảng users...</h2>";

$errors = [];
$success = [];

// 1. Kiểm tra và cập nhật enum của cột role
$checkRole = $conn->query("SHOW COLUMNS FROM users LIKE 'role'");
if ($checkRole && $checkRole->num_rows > 0) {
    // Cột role đã tồn tại, cần sửa enum
    echo "<p style='color: blue;'>ℹ️ Cột 'role' đã tồn tại, đang cập nhật enum...</p>";
    
    // Trước tiên, cập nhật các giá trị 'user' thành 'customer'
    $updateOldRole = "UPDATE `users` SET `role` = 'customer' WHERE `role` = 'user'";
    if ($conn->query($updateOldRole)) {
        echo "<p style='color: green;'>✅ Đã cập nhật các role 'user' thành 'customer'.</p>";
    }
    
    // Sau đó sửa enum
    $sqlRole = "ALTER TABLE `users` 
                MODIFY COLUMN `role` ENUM('admin', 'staff', 'customer') NOT NULL DEFAULT 'customer'";
    
    if ($conn->query($sqlRole)) {
        echo "<p style='color: green;'>✅ Đã cập nhật enum của cột 'role' thành công!</p>";
        $success[] = "role";
    } else {
        echo "<p style='color: red;'>❌ Lỗi khi cập nhật enum role: " . $conn->error . "</p>";
        $errors[] = "role";
    }
} else {
    // Cột role chưa tồn tại, thêm mới
    $sqlRole = "ALTER TABLE `users` 
                ADD COLUMN `role` ENUM('admin', 'staff', 'customer') NOT NULL DEFAULT 'customer' 
                AFTER `email`";
    
    if ($conn->query($sqlRole)) {
        echo "<p style='color: green;'>✅ Đã thêm cột 'role' vào bảng users thành công!</p>";
        $success[] = "role";
    } else {
        echo "<p style='color: red;'>❌ Lỗi khi thêm cột role: " . $conn->error . "</p>";
        $errors[] = "role";
    }
}

// 2. Kiểm tra và thêm cột status
$checkStatus = $conn->query("SHOW COLUMNS FROM users LIKE 'status'");
if ($checkStatus && $checkStatus->num_rows > 0) {
    echo "<p style='color: orange;'>⚠️ Cột 'status' đã tồn tại trong bảng users.</p>";
} else {
    $sqlStatus = "ALTER TABLE `users` 
                  ADD COLUMN `status` ENUM('active', 'locked') NOT NULL DEFAULT 'active' 
                  AFTER `role`";
    
    if ($conn->query($sqlStatus)) {
        echo "<p style='color: green;'>✅ Đã thêm cột 'status' vào bảng users thành công!</p>";
        $success[] = "status";
    } else {
        echo "<p style='color: red;'>❌ Lỗi khi thêm cột status: " . $conn->error . "</p>";
        $errors[] = "status";
    }
}

// 3. Kiểm tra và thêm cột last_login
$checkLastLogin = $conn->query("SHOW COLUMNS FROM users LIKE 'last_login'");
if ($checkLastLogin && $checkLastLogin->num_rows > 0) {
    echo "<p style='color: orange;'>⚠️ Cột 'last_login' đã tồn tại trong bảng users.</p>";
} else {
    $sqlLastLogin = "ALTER TABLE `users` 
                     ADD COLUMN `last_login` DATETIME NULL DEFAULT NULL 
                     AFTER `status`";
    
    if ($conn->query($sqlLastLogin)) {
        echo "<p style='color: green;'>✅ Đã thêm cột 'last_login' vào bảng users thành công!</p>";
        $success[] = "last_login";
    } else {
        echo "<p style='color: red;'>❌ Lỗi khi thêm cột last_login: " . $conn->error . "</p>";
        $errors[] = "last_login";
    }
}

// 4. Cập nhật role cho các user hiện có
if (empty($errors)) {
    $updateRole = "UPDATE `users` 
                   SET `role` = 'customer' 
                   WHERE `role` = 'user' OR `role` = '' OR `role` IS NULL";
    if ($conn->query($updateRole)) {
        echo "<p style='color: green;'>✅ Đã cập nhật role cho các user hiện có.</p>";
    }
    
    // Đảm bảo tất cả user có status = 'active'
    $updateStatus = "UPDATE `users` 
                     SET `status` = 'active' 
                     WHERE `status` IS NULL OR `status` = ''";
    if ($conn->query($updateStatus)) {
        echo "<p style='color: green;'>✅ Đã cập nhật status cho các user hiện có thành 'active'.</p>";
    }
}

echo "<hr>";
echo "<p><a href='quanlyadmin.php?page=taikhoan'>← Quay lại trang quản lý tài khoản</a></p>";
echo "<p><strong>Lưu ý:</strong> Sau khi chạy thành công, bạn có thể xóa file này để bảo mật.</p>";

$conn->close();
?>

