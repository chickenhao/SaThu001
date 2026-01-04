-- SQL để cập nhật trường role và thêm status vào bảng users
-- Bảng users đã có cột role với enum('user','admin'), cần sửa thành enum('admin','staff','customer')

-- 1. Sửa enum của cột role từ ('user','admin') thành ('admin','staff','customer')
-- Trước tiên, cập nhật các giá trị 'user' thành 'customer'
UPDATE `users` SET `role` = 'customer' WHERE `role` = 'user';

-- Sau đó sửa enum của cột role
ALTER TABLE `users` 
MODIFY COLUMN `role` ENUM('admin', 'staff', 'customer') NOT NULL DEFAULT 'customer';

-- 2. Thêm cột status nếu chưa tồn tại (để quản lý khóa/mở tài khoản)
-- Kiểm tra xem cột status đã tồn tại chưa bằng cách chạy:
-- SHOW COLUMNS FROM `users` LIKE 'status';
-- Nếu không có kết quả, thì chạy:
ALTER TABLE `users` 
ADD COLUMN `status` ENUM('active', 'locked') NOT NULL DEFAULT 'active' 
AFTER `role`;

-- 3. Đảm bảo tất cả user hiện có có status = 'active'
UPDATE `users` 
SET `status` = 'active' 
WHERE `status` IS NULL OR `status` = '';

-- 4. Thêm cột last_login để lưu thời gian đăng nhập cuối cùng
ALTER TABLE `users` 
ADD COLUMN `last_login` DATETIME NULL DEFAULT NULL 
AFTER `status`;

-- 5. Ví dụ: Cập nhật một số user thành admin hoặc staff
-- UPDATE `users` SET `role` = 'admin' WHERE `username` = 'admin';
-- UPDATE `users` SET `role` = 'staff' WHERE `id` = 2;

