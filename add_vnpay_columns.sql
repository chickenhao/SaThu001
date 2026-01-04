-- File SQL để thêm các cột lưu thông tin giao dịch VNPay vào bảng donhang
-- Chạy file này trong phpMyAdmin hoặc MySQL để cập nhật cấu trúc bảng

ALTER TABLE `donhang` 
ADD COLUMN `vnp_transaction_no` VARCHAR(50) NULL DEFAULT NULL COMMENT 'Mã giao dịch VNPay' AFTER `phuong_thuc_thanh_toan`,
ADD COLUMN `vnp_txn_ref` VARCHAR(100) NULL DEFAULT NULL COMMENT 'Mã tham chiếu giao dịch VNPay' AFTER `vnp_transaction_no`;

-- Tạo index để tìm kiếm nhanh theo mã giao dịch
CREATE INDEX `idx_vnp_transaction_no` ON `donhang` (`vnp_transaction_no`);
CREATE INDEX `idx_vnp_txn_ref` ON `donhang` (`vnp_txn_ref`);

