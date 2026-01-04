<?php
/**
 * Script để thêm cột status vào bảng sanpham
 * Chạy file này một lần để cập nhật database
 */

require 'config.php';

echo "<h2>Đang thêm cột status vào bảng sanpham...</h2>";

// Kiểm tra xem cột status đã tồn tại chưa
$checkColumn = $conn->query("SHOW COLUMNS FROM sanpham LIKE 'status'");

if ($checkColumn && $checkColumn->num_rows > 0) {
    echo "<p style='color: orange;'>⚠️ Cột 'status' đã tồn tại trong bảng sanpham. Không cần thêm nữa.</p>";
} else {
    // Thêm cột status
    $sql = "ALTER TABLE `sanpham` 
            ADD COLUMN `status` VARCHAR(50) DEFAULT 'còn hàng' 
            AFTER `quantity`";
    
    if ($conn->query($sql)) {
        echo "<p style='color: green;'>✅ Đã thêm cột 'status' vào bảng sanpham thành công!</p>";
        
        // Cập nhật tất cả sản phẩm hiện có thành 'còn hàng'
        $updateSql = "UPDATE `sanpham` SET `status` = 'còn hàng' WHERE `status` IS NULL OR `status` = ''";
        if ($conn->query($updateSql)) {
            echo "<p style='color: green;'>✅ Đã cập nhật trạng thái cho tất cả sản phẩm hiện có thành 'còn hàng'.</p>";
        } else {
            echo "<p style='color: red;'>⚠️ Lỗi khi cập nhật dữ liệu: " . $conn->error . "</p>";
        }
    } else {
        echo "<p style='color: red;'>❌ Lỗi khi thêm cột: " . $conn->error . "</p>";
    }
}

echo "<hr>";
echo "<p><a href='quanlyadmin.php'>← Quay lại trang quản lý admin</a></p>";
echo "<p><strong>Lưu ý:</strong> Sau khi chạy thành công, bạn có thể xóa file này để bảo mật.</p>";

$conn->close();
?>

