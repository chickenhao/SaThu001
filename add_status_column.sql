-- Migration: Thêm cột status vào bảng sanpham
-- Chạy file này trong phpMyAdmin hoặc MySQL command line

ALTER TABLE `sanpham` 
ADD COLUMN `status` VARCHAR(50) DEFAULT 'còn hàng' 
AFTER `quantity`;

-- Cập nhật tất cả sản phẩm hiện có thành 'còn hàng' nếu chưa có giá trị
UPDATE `sanpham` SET `status` = 'còn hàng' WHERE `status` IS NULL OR `status` = '';

