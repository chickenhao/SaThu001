-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Máy chủ: 127.0.0.1
-- Thời gian đã tạo: Th12 26, 2025 lúc 09:40 AM
-- Phiên bản máy phục vụ: 10.4.32-MariaDB
-- Phiên bản PHP: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Cơ sở dữ liệu: `dangky_db`
--

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `chat_messages`
--

CREATE TABLE `chat_messages` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `sender_type` enum('user','admin') NOT NULL DEFAULT 'user',
  `message` text NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Đang đổ dữ liệu cho bảng `chat_messages`
--

INSERT INTO `chat_messages` (`id`, `user_id`, `sender_type`, `message`, `created_at`) VALUES
(1, 12, 'user', 'xin hỗ trợ tôi với ad', '2025-12-10 14:11:04'),
(2, 12, 'user', 'akkkkk', '2025-12-10 14:24:58'),
(3, 12, 'admin', 'a', '2025-12-10 15:06:47'),
(4, 12, 'admin', 'a', '2025-12-10 15:06:51'),
(5, 12, 'user', 'em yêu anh', '2025-12-10 16:29:38'),
(6, 12, 'admin', 'anh yêu em :)', '2025-12-10 16:30:01'),
(7, 12, 'user', 'yêu a ko', '2025-12-11 08:12:10'),
(8, 12, 'admin', 'e có', '2025-12-11 08:12:55'),
(9, 9, 'user', 'chào admin', '2025-12-11 08:28:41'),
(10, 9, 'admin', 'chào bạn!', '2025-12-11 08:29:51'),
(11, 9, 'user', 'aaa', '2025-12-11 08:33:13'),
(12, 9, 'admin', 'bbbb', '2025-12-11 08:33:23'),
(13, 9, 'admin', 'bb', '2025-12-11 08:38:28'),
(14, 9, 'admin', 'ag', '2025-12-11 08:38:33'),
(15, 9, 'admin', 'ac', '2025-12-11 08:38:36'),
(16, 12, 'user', 'làm người yêu anh nhé', '2025-12-11 08:46:14'),
(17, 12, 'admin', 'em đồng ý', '2025-12-11 08:46:27'),
(18, 12, 'admin', ':)', '2025-12-11 08:46:45'),
(19, 12, 'admin', 'tim', '2025-12-11 08:47:08'),
(20, 12, '', 'ứ', '2025-12-11 09:45:48'),
(21, 9, '', 'aaaa', '2025-12-11 09:51:28'),
(22, 9, 'admin', 'aaaaaa', '2025-12-11 09:57:40'),
(23, 9, 'user', 'aaaaaaaaaaa', '2025-12-11 10:06:15'),
(24, 6, 'user', 'alo', '2025-12-20 05:49:10');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `danhmuc`
--

CREATE TABLE `danhmuc` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `image` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Đang đổ dữ liệu cho bảng `danhmuc`
--

INSERT INTO `danhmuc` (`id`, `name`, `description`, `image`, `created_at`) VALUES
(1, 'Bánh Quy Bơ', 'Dòng bánh quy bơ cổ điển', NULL, '2025-11-29 18:30:56'),
(2, 'Bánh Quy Bơ Truyền Thống', 'Phiên bản cao cấp, hộp sang trọng', NULL, '2025-11-29 18:30:56'),
(4, 'Bánh Quy Bơ Nho Khô', 'Dòng bánh quy bơ cổ điển', NULL, '2025-11-29 18:31:40'),
(5, 'Bánh Quy Có Nhân', 'Phiên bản cao cấp, hộp sang trọng', NULL, '2025-11-29 18:31:40'),
(7, 'Bánh Quy Bơ Nhân Dứa', 'a', NULL, '2025-12-04 10:46:03'),
(8, 'Bánh Quy Bơ Vị So-Co-La', '', NULL, '2025-12-09 02:13:23');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `diachi_nhanhang`
--

CREATE TABLE `diachi_nhanhang` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `ho_ten_nhan` varchar(255) NOT NULL,
  `phone_nhan` varchar(20) DEFAULT NULL,
  `dia_chi` text NOT NULL,
  `mac_dinh` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Đang đổ dữ liệu cho bảng `diachi_nhanhang`
--

INSERT INTO `diachi_nhanhang` (`id`, `user_id`, `ho_ten_nhan`, `phone_nhan`, `dia_chi`, `mac_dinh`, `created_at`, `updated_at`) VALUES
(7, 12, 'hán minh tùng lâm', '0988888888', 'yb', 1, '2025-12-10 15:48:21', '2025-12-10 17:04:18'),
(8, 9, 'dương thanh hào', '0373302685', 'Bắc Giang', 1, '2025-12-11 10:22:14', '2025-12-11 10:22:14'),
(9, 14, 'hán minh tùng lâm', '', 'số 13 hồ tùng mậu', 1, '2025-12-20 05:35:47', '2025-12-20 05:37:01'),
(10, 14, 'hán minh tùng lâm', '', 'số 128a hồ tùng mậu', 0, '2025-12-20 05:36:20', '2025-12-20 05:37:01'),
(11, 1, 'hán minh tùng lâm', '0999888888', 'yb', 1, '2025-12-20 19:30:43', '2025-12-20 19:37:04');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `donhang`
--

CREATE TABLE `donhang` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `ho_ten` varchar(150) NOT NULL,
  `phone` varchar(20) NOT NULL,
  `email` varchar(150) DEFAULT NULL,
  `dia_chi` varchar(255) NOT NULL,
  `tong_tien` decimal(12,2) NOT NULL,
  `trang_thai` varchar(50) NOT NULL DEFAULT 'Chờ xác nhận',
  `ly_do_huy` varchar(255) DEFAULT NULL,
  `ly_do_tra_hang` text DEFAULT NULL,
  `phuong_thuc_thanh_toan` varchar(50) DEFAULT NULL,
  `vnp_transaction_no` varchar(50) DEFAULT NULL COMMENT 'Mã giao dịch VNPay',
  `vnp_txn_ref` varchar(100) DEFAULT NULL COMMENT 'Mã tham chiếu giao dịch VNPay',
  `ghi_chu` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL,
  `is_exported` tinyint(1) NOT NULL DEFAULT 0,
  `exported_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Đang đổ dữ liệu cho bảng `donhang`
--

INSERT INTO `donhang` (`id`, `user_id`, `ho_ten`, `phone`, `email`, `dia_chi`, `tong_tien`, `trang_thai`, `ly_do_huy`, `ly_do_tra_hang`, `phuong_thuc_thanh_toan`, `vnp_transaction_no`, `vnp_txn_ref`, `ghi_chu`, `created_at`, `updated_at`, `is_exported`, `exported_at`) VALUES
(38, 1, 'tlam', '0973464843', '', 'nnn', 450000.00, 'paid', NULL, NULL, 'Thanh toán online qua VNPay', NULL, NULL, '', '2025-12-09 03:22:28', '2025-12-09 16:59:45', 0, NULL),
(39, 12, 'tlamdz', '0373302685', '', 'yb', 121944000.00, 'paid', NULL, NULL, 'Thanh toán khi nhận hàng (COD)', NULL, NULL, '', '2025-12-09 16:57:26', '2025-12-09 16:58:24', 0, NULL),
(40, 12, 'tlamdz', '0373302685', '', 'yb', 30872000.00, 'cancelled', 'a', NULL, 'Thanh toán khi nhận hàng (COD)', NULL, NULL, '', '2025-12-10 04:39:53', '2025-12-10 04:39:53', 0, NULL),
(41, 12, 'tlamdz', '0373302685', '', 'a', 450000.00, 'cancelled', 'Không muốn nhận hàng nữa', NULL, 'Thanh toán khi nhận hàng (COD)', NULL, NULL, '', '2025-12-10 08:08:20', '2025-12-10 08:08:20', 0, NULL),
(42, 12, 'tlamdz', '0373302685', '', 'aaa', 450000.00, 'cancelled', 'Thay đổi địa chỉ / số điện thoại nhận hàng', NULL, 'Thanh toán khi nhận hàng (COD)', NULL, NULL, '', '2025-12-10 08:21:01', '2025-12-10 08:21:01', 0, NULL),
(43, 12, 'hán minh tùng lâm', '0373302685', '', 'yb', 900000.00, 'paid', NULL, NULL, 'Thanh toán khi nhận hàng (COD)', NULL, NULL, '', '2025-12-10 09:44:56', '2025-12-10 09:59:18', 0, NULL),
(44, 1, 'tlam', '0973464843', '', '', 1400000.00, 'pending', NULL, NULL, 'Thanh toán online qua VNPay', NULL, NULL, '', '2025-12-11 01:13:35', '2025-12-11 01:13:35', 0, NULL),
(45, 1, 'tlam', '0973464843', '', '', 1400000.00, 'pending', NULL, NULL, 'Thanh toán online qua VNPay', NULL, NULL, '', '2025-12-11 01:16:19', '2025-12-11 01:16:19', 0, NULL),
(46, 9, 'hao98', '0373302685', '', 'aa', 4086000.00, 'pending', NULL, NULL, 'Thanh toán khi nhận hàng (COD)', NULL, NULL, '', '2025-12-11 01:31:17', '2025-12-11 01:31:17', 0, NULL),
(47, 12, 'hán minh tùng lâm', '0373302685', '', 'yb', 4086000.00, 'pending', NULL, NULL, 'Thanh toán online qua VNPay', NULL, NULL, '', '2025-12-11 01:50:00', '2025-12-11 01:50:00', 0, NULL),
(48, 9, 'Dương Thanh Hào', '0373302685', '', 'Bắc Giang', 2043000000.00, 'paid', NULL, NULL, 'Thanh toán khi nhận hàng (COD)', NULL, NULL, '', '2025-12-11 03:22:45', '2025-12-11 03:23:23', 0, NULL),
(49, 14, 'khachhang1', '0973464843', '', 'số 13 hồ tùng mậu', 900000.00, 'paid', NULL, NULL, 'Thanh toán khi nhận hàng (COD)', NULL, NULL, '', '2025-12-19 22:38:14', '2025-12-19 22:45:42', 0, NULL),
(50, 1, 'tlam', '0973464843', '', '23 hồ tùng mậu', 3120000000.00, 'pending', NULL, NULL, 'Thanh toán khi nhận hàng (COD)', NULL, NULL, '', '2025-12-20 02:02:58', '2025-12-20 02:02:58', 0, NULL),
(51, 14, 'khachhang1', '0973464843', '', 'số 13 hồ tùng mậu', 450000.00, 'paid', NULL, NULL, 'Thanh toán khi nhận hàng (COD)', NULL, NULL, '', '2025-12-20 02:08:27', '2025-12-20 02:10:21', 0, NULL),
(52, 14, 'khachhang1', '0973464843', '', 'số 13 hồ tùng mậu', 1532000.00, 'cancelled', 'Tìm được sản phẩm khác phù hợp hơn', NULL, 'Thanh toán khi nhận hàng (COD)', NULL, NULL, '', '2025-12-20 02:14:00', '2025-12-20 02:14:00', 0, NULL);

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `donhang_chitiet`
--

CREATE TABLE `donhang_chitiet` (
  `id` int(11) NOT NULL,
  `donhang_id` int(11) NOT NULL,
  `sanpham_id` varchar(50) NOT NULL,
  `so_luong` int(11) NOT NULL,
  `don_gia` decimal(10,2) NOT NULL,
  `thanh_tien` decimal(12,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Đang đổ dữ liệu cho bảng `donhang_chitiet`
--

INSERT INTO `donhang_chitiet` (`id`, `donhang_id`, `sanpham_id`, `so_luong`, `don_gia`, `thanh_tien`) VALUES
(69, 38, 'TL004', 1, 450000.00, 450000.00),
(70, 39, 'TL004', 8, 2043000.00, 16344000.00),
(71, 39, 'TL001', 50, 2043000.00, 102150000.00),
(72, 39, 'BQ980', 2, 1725000.00, 3450000.00),
(73, 40, 'TL004', 2, 2043000.00, 4086000.00),
(74, 40, 'TL002', 1, 1362000.00, 1362000.00),
(75, 40, 'QB7652', 4, 6356000.00, 25424000.00),
(76, 41, 'TL004', 1, 450000.00, 450000.00),
(77, 42, 'TL004', 1, 450000.00, 450000.00),
(78, 43, 'TL004', 2, 450000.00, 900000.00),
(79, 44, 'QB7652', 1, 1400000.00, 1400000.00),
(80, 45, 'QB7652', 1, 1400000.00, 1400000.00),
(81, 46, 'TL004', 2, 2043000.00, 4086000.00),
(82, 47, 'TL004', 2, 2043000.00, 4086000.00),
(83, 48, 'TL004', 1000, 2043000.00, 2043000000.00),
(84, 49, 'TL004', 2, 450000.00, 900000.00),
(85, 50, 'BQ784', 4000, 780000.00, 3120000000.00),
(86, 51, 'TL004', 1, 450000.00, 450000.00),
(87, 52, 'TL004', 1, 1532000.00, 1532000.00);

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `favorites`
--

CREATE TABLE `favorites` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `hoso`
--

CREATE TABLE `hoso` (
  `id` int(11) NOT NULL,
  `taikhoan_id` int(11) NOT NULL,
  `ho_ten` varchar(255) DEFAULT NULL,
  `gioi_tinh` enum('Nam','Nữ','Khác') DEFAULT 'Khác',
  `ngay_sinh` date DEFAULT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `thong_tin` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `kho_tonkho`
--

CREATE TABLE `kho_tonkho` (
  `sanpham_id` varchar(50) NOT NULL,
  `qty_on_hand` int(11) NOT NULL DEFAULT 0,
  `qty_reserved` int(11) NOT NULL DEFAULT 0,
  `last_in_at` datetime DEFAULT NULL,
  `last_out_at` datetime DEFAULT NULL,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `khuyenmai`
--

CREATE TABLE `khuyenmai` (
  `id` int(11) NOT NULL,
  `ma` varchar(50) NOT NULL,
  `ten` varchar(255) NOT NULL,
  `loai` enum('percent','amount') NOT NULL DEFAULT 'percent',
  `gia_tri` decimal(10,2) NOT NULL,
  `dieu_kien_tong_tien` decimal(12,2) DEFAULT 0.00,
  `ngay_bat_dau` datetime DEFAULT NULL,
  `ngay_ket_thuc` datetime DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `usage_limit` int(11) DEFAULT NULL,
  `used_count` int(11) NOT NULL DEFAULT 0,
  `per_user_limit` int(11) DEFAULT NULL,
  `assigned_user_id` int(11) DEFAULT NULL,
  `auto_expire` tinyint(1) NOT NULL DEFAULT 1,
  `max_discount` decimal(12,2) DEFAULT NULL,
  `min_items` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Đang đổ dữ liệu cho bảng `khuyenmai`
--

INSERT INTO `khuyenmai` (`id`, `ma`, `ten`, `loai`, `gia_tri`, `dieu_kien_tong_tien`, `ngay_bat_dau`, `ngay_ket_thuc`, `is_active`, `created_at`, `usage_limit`, `used_count`, `per_user_limit`, `assigned_user_id`, `auto_expire`, `max_discount`, `min_items`) VALUES
(3, 'KKHA099', '', 'amount', 500000.00, 900000.00, '2025-12-05 00:00:00', '2025-12-07 23:59:59', 0, '2025-12-04 16:26:08', NULL, 0, NULL, NULL, 1, NULL, NULL),
(4, 'VN998', 'ad', 'amount', 70000.00, 900000.00, '2026-12-05 00:00:00', '2026-12-06 23:59:59', 1, '2025-12-05 16:34:54', NULL, 0, NULL, NULL, 1, NULL, NULL),
(5, 'HH777', '', 'amount', 78000.00, 777777.00, '2026-12-05 00:00:00', '2005-12-14 23:59:59', 0, '2025-12-05 16:36:44', NULL, 0, NULL, NULL, 1, NULL, NULL);

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `khuyenmai_usage`
--

CREATE TABLE `khuyenmai_usage` (
  `id` int(11) NOT NULL,
  `khuyenmai_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `donhang_id` int(11) NOT NULL,
  `discount_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `km_ngayle`
--

CREATE TABLE `km_ngayle` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `scope` enum('all','category','products') NOT NULL DEFAULT 'all',
  `danhmuc_id` int(11) DEFAULT NULL,
  `discount_type` enum('percent','amount') NOT NULL,
  `discount_value` decimal(12,2) NOT NULL DEFAULT 0.00,
  `max_discount` decimal(12,2) DEFAULT NULL,
  `min_order_value` decimal(12,2) NOT NULL DEFAULT 0.00,
  `is_auto` tinyint(1) NOT NULL DEFAULT 1,
  `code` varchar(50) DEFAULT NULL,
  `priority` int(11) NOT NULL DEFAULT 0,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Đang đổ dữ liệu cho bảng `km_ngayle`
--

INSERT INTO `km_ngayle` (`id`, `name`, `description`, `scope`, `danhmuc_id`, `discount_type`, `discount_value`, `max_discount`, `min_order_value`, `is_auto`, `code`, `priority`, `status`, `start_date`, `end_date`, `created_at`, `updated_at`) VALUES
(1, 'No Em', '', 'all', 5, 'amount', 30000.00, 28000.00, 500000.00, 1, '0', 0, 'active', '2025-12-20', '2025-12-22', '2025-12-20 17:07:16', '2025-12-20 17:07:16');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `km_ngayle_products`
--

CREATE TABLE `km_ngayle_products` (
  `id` int(11) NOT NULL,
  `km_id` int(11) NOT NULL,
  `sanpham_id` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `km_sanpham`
--

CREATE TABLE `km_sanpham` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `discount_type` enum('percent','amount') NOT NULL,
  `discount_value` decimal(12,2) NOT NULL DEFAULT 0.00,
  `max_discount` decimal(12,2) DEFAULT NULL,
  `min_qty` int(11) NOT NULL DEFAULT 1,
  `min_order_value` decimal(12,2) NOT NULL DEFAULT 0.00,
  `priority` int(11) NOT NULL DEFAULT 0,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Đang đổ dữ liệu cho bảng `km_sanpham`
--

INSERT INTO `km_sanpham` (`id`, `name`, `discount_type`, `discount_value`, `max_discount`, `min_qty`, `min_order_value`, `priority`, `status`, `start_date`, `end_date`, `created_at`, `updated_at`) VALUES
(1, 'ngày lễ', 'amount', 30000.00, 20000.00, 100, 100.00, 100, 'active', '2025-12-20', '2026-01-20', '2025-12-20 16:11:41', '2025-12-20 16:11:41'),
(2, 'ngày lễ', 'amount', 30000.00, 20000.00, 100, 100.00, 100, 'active', '2025-12-20', '2026-01-20', '2025-12-20 16:12:03', '2025-12-20 17:04:29');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `km_sanpham_items`
--

CREATE TABLE `km_sanpham_items` (
  `id` int(11) NOT NULL,
  `km_id` int(11) NOT NULL,
  `sanpham_id` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Đang đổ dữ liệu cho bảng `km_sanpham_items`
--

INSERT INTO `km_sanpham_items` (`id`, `km_id`, `sanpham_id`) VALUES
(1, 1, 'BQ980'),
(2, 2, 'BQ980');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `lienhe`
--

CREATE TABLE `lienhe` (
  `id` int(11) NOT NULL,
  `ho_ten` varchar(150) NOT NULL,
  `email` varchar(150) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `tieu_de` varchar(255) NOT NULL,
  `noi_dung` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `nhaphang`
--

CREATE TABLE `nhaphang` (
  `id` int(11) NOT NULL,
  `sanpham_id` varchar(50) NOT NULL,
  `so_luong` int(11) NOT NULL,
  `import_price` decimal(15,2) NOT NULL DEFAULT 0.00,
  `ghi_chu` varchar(255) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Đang đổ dữ liệu cho bảng `nhaphang`
--

INSERT INTO `nhaphang` (`id`, `sanpham_id`, `so_luong`, `import_price`, `ghi_chu`, `created_at`, `updated_at`) VALUES
(4, 'TL004', 900, 800000.00, 'g', '2025-12-10 15:44:28', '2025-12-10 15:44:28'),
(5, 'TL001', 11234, 300000.00, 'dfghjkjbvc', '2025-12-11 08:18:53', '2025-12-11 08:18:53'),
(6, 'BQ784', 1000, 150000.00, 'hết hàng', '2025-12-20 08:58:58', '2025-12-20 08:58:58'),
(7, 'BQ784', 2000, 300000.00, '', '2025-12-20 08:59:58', '2025-12-20 08:59:58');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `password_resets`
--

CREATE TABLE `password_resets` (
  `id` int(11) NOT NULL,
  `email` varchar(150) NOT NULL,
  `token` varchar(255) NOT NULL,
  `expires_at` datetime NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `phieuxuat`
--

CREATE TABLE `phieuxuat` (
  `id` int(11) NOT NULL,
  `sanpham_id` varchar(50) NOT NULL,
  `so_luong` int(11) NOT NULL,
  `export_price` decimal(15,2) DEFAULT 0.00,
  `ly_do` varchar(255) DEFAULT NULL,
  `ghi_chu` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `product_ratings`
--

CREATE TABLE `product_ratings` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `rating` tinyint(4) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `promo_bundle`
--

CREATE TABLE `promo_bundle` (
  `id` int(11) NOT NULL,
  `name` varchar(191) NOT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `start_date` datetime DEFAULT NULL,
  `end_date` datetime DEFAULT NULL,
  `discount_type` enum('percent','amount') NOT NULL DEFAULT 'percent',
  `discount_value` decimal(12,2) NOT NULL DEFAULT 0.00,
  `apply_on` enum('cheapest','specific','order') NOT NULL DEFAULT 'specific',
  `target_product_id` varchar(50) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `promo_bundle_items`
--

CREATE TABLE `promo_bundle_items` (
  `id` int(11) NOT NULL,
  `bundle_id` int(11) NOT NULL,
  `product_id` varchar(50) NOT NULL,
  `required_qty` int(11) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `sanpham`
--

CREATE TABLE `sanpham` (
  `id` varchar(50) NOT NULL,
  `name` varchar(255) NOT NULL,
  `category` varchar(100) NOT NULL,
  `price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `quantity` int(11) NOT NULL DEFAULT 0,
  `status` varchar(50) DEFAULT 'còn hàng',
  `image` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `import_price` int(11) DEFAULT 0,
  `sale_price` int(11) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Đang đổ dữ liệu cho bảng `sanpham`
--

INSERT INTO `sanpham` (`id`, `name`, `category`, `price`, `quantity`, `status`, `image`, `description`, `created_at`, `import_price`, `sale_price`) VALUES
('BQ784', 'Bánh Quy Bơ Vị So-Co-La', 'keo', 780000.00, 3900, 'còn hàng', '0', 'Hãy để Danisa đưa bạn đến với vùng đất của đam mê cùng hương vị bơ, sữa thượng hạng kết hợp với các nguyên liệu tinh túy nhất với nhân sô-cô-la, tất cả hòa quyện hoàn hảo tan chảy nhẹ nhàng nơi vị giác, làm xiêu lòng những tín đồ bánh ngọt.', '2025-12-09 09:53:32', 300000, 800000),
('BQ980', 'Bánh Quy Nhân Dứa', 'keo', 380000.00, 900, 'còn hàng', 'uploads/1765273930_6937f14a0870b.png', 'Những người thợ làm bánh bậc thầy của chúng tôi đã đi khắp thế giới và lựa chọn những trái cây ngon nhất để tạo ra một món ăn không thể cưỡng lại với hương vị nhiệt đới đáng ngạc nhiên. Thưởng thức sự mềm mịn của bánh quy bơ của chúng tôi kết hợp với nhân dứa thơm phức cho hương vị tan chảy dễ chịu trong miệng của bạn.', '2025-12-09 09:52:10', 80000, 400000),
('L111', 'bánh quy hạch ', 'banh', 199999.00, 39, 'còn hàng', 'uploads/1766195654_694601c67bf58.png', 'bán Quy NGon', '2025-12-20 01:53:42', 150000, 200000),
('QB7652', 'Bánh quy KKK', 'keo', 1400000.00, 900, 'còn hàng', 'uploads/1765341448_6938f90879008.png', 'bbbbbbbbbbbb', '2025-12-10 04:37:28', 800000, 1600000),
('TL001', 'Bánh Quy Bơ Truyền Thống(TO)', 'keo', 450000.00, 12134, 'còn hàng', 'uploads/1765245910_693783d64f96c.jpg', 'Để trở thành tinh hoa không thể thiếu trong những buổi tiệc tao nhã của giới quý tộc châu Âu, bánh quy bơ Danisa được đặc biệt làm ra theo công thức truyền thống của Đan Mạch, với sự kết hợp giữa các nguyên liệu bơ, sữa hảo hạng mang đến hương vị tuyệt hảo. Chính nhờ vậy, Danisa đã trở thành biểu tượng cho chất lượng và đẳng cấp của mọi thời đại.', '2025-12-09 02:05:10', 300000, 500000),
('TL002', 'Bánh Quy Bơ Truyền Thống(NHỎ)', 'banh', 300000.00, 900, 'còn hàng', 'uploads/1765245980_6937841cb57fe.png', 'Để trở thành tinh hoa không thể thiếu trong những buổi tiệc tao nhã của giới quý tộc châu Âu, bánh quy bơ Danisa được đặc biệt làm ra theo công thức truyền thống của Đan Mạch, với sự kết hợp giữa các nguyên liệu bơ, sữa hảo hạng mang đến hương vị tuyệt hảo. Chính nhờ vậy, Danisa đã trở thành biểu tượng cho chất lượng và đẳng cấp của mọi thời đại.', '2025-12-09 02:06:20', 150000, 350000),
('TL004', 'Bánh Quy Bơ Nho Khô', 'keo', 450000.00, 900, 'còn hàng', 'uploads/1765246205_693784fdebf45.png', '', '2025-12-09 02:10:05', 800000, 500000);

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `taikhoan`
--

CREATE TABLE `taikhoan` (
  `id` int(11) NOT NULL,
  `username` varchar(100) NOT NULL,
  `email` varchar(190) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role` enum('admin','staff','customer') NOT NULL DEFAULT 'customer',
  `status` enum('active','locked') NOT NULL DEFAULT 'active',
  `last_login` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `tintuc`
--

CREATE TABLE `tintuc` (
  `id` int(11) NOT NULL,
  `tieu_de` varchar(255) NOT NULL,
  `slug` varchar(255) DEFAULT NULL,
  `anh` varchar(255) DEFAULT NULL,
  `tom_tat` text DEFAULT NULL,
  `noi_dung` longtext DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `email` varchar(150) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `role` enum('admin','staff','customer') NOT NULL DEFAULT 'customer',
  `status` varchar(20) NOT NULL DEFAULT 'pending',
  `last_login` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `ho_ten` varchar(255) DEFAULT NULL,
  `gioi_tinh` varchar(10) DEFAULT 'Khác',
  `ngay_sinh` date DEFAULT NULL,
  `thong_tin` text DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  `email_verified_at` datetime DEFAULT NULL,
  `verify_token` varchar(64) DEFAULT NULL,
  `otp_code` varchar(255) DEFAULT NULL,
  `otp_expires_at` datetime DEFAULT NULL,
  `otp_last_sent_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Đang đổ dữ liệu cho bảng `users`
--

INSERT INTO `users` (`id`, `username`, `password`, `email`, `phone`, `role`, `status`, `last_login`, `created_at`, `ho_ten`, `gioi_tinh`, `ngay_sinh`, `thong_tin`, `updated_at`, `email_verified_at`, `verify_token`, `otp_code`, `otp_expires_at`, `otp_last_sent_at`) VALUES
(1, 'tlam', '$2y$10$Qw6wHYzDi3YI5EWn2YbHh.ziYBlw4Xem6yHG8veXzbvULWR9lQwZe', 'lamhan122@gmail.com', '0973464843', 'admin', 'active', '2025-12-26 15:38:51', '2025-11-30 16:16:27', 'hán minh tùng lâm', 'Nam', '2005-07-31', '', '2025-12-20 19:30:25', NULL, NULL, NULL, NULL, NULL),
(2, 'ggg', '$2y$10$Qw6wHYzDi3YI5EWn2YbHh.ziYBlw4Xem6yHG8veXzbvULWR9lQwZe', NULL, '09844432342', 'admin', 'active', NULL, '2025-12-01 17:52:54', NULL, 'Khác', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(4, 'tlam123', '$2y$10$Qw6wHYzDi3YI5EWn2YbHh.ziYBlw4Xem6yHG8veXzbvULWR9lQwZe', NULL, '0373302685', 'staff', 'active', '2025-12-10 11:36:03', '2025-12-01 17:55:09', NULL, 'Khác', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(5, '12345', '$2y$10$Qw6wHYzDi3YI5EWn2YbHh.ziYBlw4Xem6yHG8veXzbvULWR9lQwZe', NULL, '0373302685', 'customer', 'active', NULL, '2025-12-03 17:36:59', NULL, 'Khác', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(6, '123456', '$2y$10$1IvdjGN5a4/PSrvLOXYk7.hhrrRpgJAmmVhINq5lTVXi8OomAk97G', NULL, '0373302685', 'staff', 'active', '2025-12-20 05:45:11', '2025-12-03 17:57:27', NULL, 'Khác', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(7, 'hao123', '$2y$10$VQ5XJwVQl0D4cDSE8lBin.RqKg9LqHiixDXftvfzktOmJ4XI6Cw7S', NULL, '0999999998', 'staff', 'active', '2025-12-10 15:25:26', '2025-12-04 02:52:53', NULL, 'Khác', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(8, 'haook', '$2y$10$B78fJUWAqJYONl0Mi0zwbu2.ZqvzIIQgiJ.S8eCA/PIeT13u/kjri', NULL, '0386060605', '', 'active', '2025-12-09 10:52:23', '2025-12-04 17:13:00', NULL, 'Khác', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(9, 'hao98', '$2y$10$02IUa1clHrhSEIa8R8VoPOdmkYEpR/ZPk57o9tIXhoJm7Dzhl4rOO', 'thanhao6605@gmail.com', '0373302685', 'customer', 'active', '2025-12-19 22:52:51', '2025-12-05 16:43:00', 'Dương Thanh Hào', 'Nam', '2005-06-06', 'lịch lãm bảnh củ tỏi', '2025-12-11 10:21:13', NULL, NULL, NULL, NULL, NULL),
(12, 'tlamdz', '$2y$10$5FRc8TzFTOBOgzsNbNPBtuZ/xD3Q/qBs88Wyo8OjfmfMtqW45yWi.', 'lamhan1122@gmail.com', '0373302685', 'customer', 'active', '2025-12-11 09:46:39', '2025-12-09 01:38:32', 'hán minh tùng lâm', 'Nam', '2005-07-31', 'đẹp trai lịch lãm', '2025-12-10 15:48:12', NULL, NULL, NULL, NULL, NULL),
(13, 'nhanvien1', '$2y$10$zytrVKtY2gYv5EZ91G9aEOQVSFiDzicxkTM.0F.HGeX3MKlUVJe9K', NULL, '0373302685', 'staff', 'locked', '2025-12-20 05:27:52', '2025-12-19 22:27:34', NULL, 'Khác', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(14, 'khachhang1', '$2y$10$t6/2WoFCUs3w79mN7l6VVeKtXwABfvwVbVciBhmfKOukDVNyppXYO', NULL, '0973464843', 'customer', 'active', '2025-12-20 09:08:02', '2025-12-19 22:29:18', NULL, 'Khác', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(15, 'hao789', '$2y$10$iiW7fSXl4qBqBBLyE7F1..DJRsfYOtm0qU44DOj68hevZS/o98oC6', 'haoduongthanh5@gmail.com', '0987654321', 'customer', 'pending', NULL, '2025-12-20 06:48:51', NULL, 'Khác', NULL, NULL, NULL, NULL, 'fc7de2cddfb4abb0d7d84ab76d8d6fa0c08fe57f704910005549899b6e5257b3', NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Cấu trúc đóng vai cho view `v_tonkho_aging`
-- (See below for the actual view)
--
CREATE TABLE `v_tonkho_aging` (
`sanpham_id` varchar(50)
,`qty_on_hand` int(11)
,`qty_reserved` int(11)
,`last_in_at` datetime
,`last_out_at` datetime
,`days_since_last_out` int(7)
,`days_since_last_in` int(7)
);

-- --------------------------------------------------------

--
-- Cấu trúc đóng vai cho view `v_tonkho_unsold`
-- (See below for the actual view)
--
CREATE TABLE `v_tonkho_unsold` (
`sanpham_id` varchar(50)
,`name` varchar(255)
,`qty_on_hand` int(11)
,`last_sold_at` timestamp
,`days_since_last_sold` int(7)
);

-- --------------------------------------------------------

--
-- Cấu trúc cho view `v_tonkho_aging`
--
DROP TABLE IF EXISTS `v_tonkho_aging`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_tonkho_aging`  AS SELECT `tk`.`sanpham_id` AS `sanpham_id`, `tk`.`qty_on_hand` AS `qty_on_hand`, `tk`.`qty_reserved` AS `qty_reserved`, `tk`.`last_in_at` AS `last_in_at`, `tk`.`last_out_at` AS `last_out_at`, to_days(curdate()) - to_days(cast(`tk`.`last_out_at` as date)) AS `days_since_last_out`, to_days(curdate()) - to_days(cast(`tk`.`last_in_at` as date)) AS `days_since_last_in` FROM `kho_tonkho` AS `tk` ;

-- --------------------------------------------------------

--
-- Cấu trúc cho view `v_tonkho_unsold`
--
DROP TABLE IF EXISTS `v_tonkho_unsold`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_tonkho_unsold`  AS SELECT `sp`.`id` AS `sanpham_id`, `sp`.`name` AS `name`, greatest(coalesce(`sp`.`quantity`,0),0) AS `qty_on_hand`, max(`d`.`created_at`) AS `last_sold_at`, to_days(curdate()) - to_days(cast(max(`d`.`created_at`) as date)) AS `days_since_last_sold` FROM ((`sanpham` `sp` left join `donhang_chitiet` `ct` on(`ct`.`sanpham_id` = `sp`.`id`)) left join `donhang` `d` on(`d`.`id` = `ct`.`donhang_id` and `d`.`trang_thai` in ('paid','shipping','completed'))) GROUP BY `sp`.`id`, `sp`.`name`, `sp`.`quantity` ;

--
-- Chỉ mục cho các bảng đã đổ
--

--
-- Chỉ mục cho bảng `chat_messages`
--
ALTER TABLE `chat_messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_id` (`user_id`);

--
-- Chỉ mục cho bảng `danhmuc`
--
ALTER TABLE `danhmuc`
  ADD PRIMARY KEY (`id`);

--
-- Chỉ mục cho bảng `diachi_nhanhang`
--
ALTER TABLE `diachi_nhanhang`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_diachi_user` (`user_id`);

--
-- Chỉ mục cho bảng `donhang`
--
ALTER TABLE `donhang`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_donhang_user` (`user_id`),
  ADD KEY `idx_vnp_transaction_no` (`vnp_transaction_no`),
  ADD KEY `idx_vnp_txn_ref` (`vnp_txn_ref`);

--
-- Chỉ mục cho bảng `donhang_chitiet`
--
ALTER TABLE `donhang_chitiet`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_ctdh_donhang` (`donhang_id`),
  ADD KEY `fk_ctdh_sanpham` (`sanpham_id`);

--
-- Chỉ mục cho bảng `favorites`
--
ALTER TABLE `favorites`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_fav` (`user_id`,`product_id`),
  ADD KEY `idx_user` (`user_id`),
  ADD KEY `idx_product` (`product_id`);

--
-- Chỉ mục cho bảng `hoso`
--
ALTER TABLE `hoso`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_hoso_taikhoan` (`taikhoan_id`);

--
-- Chỉ mục cho bảng `kho_tonkho`
--
ALTER TABLE `kho_tonkho`
  ADD PRIMARY KEY (`sanpham_id`),
  ADD KEY `idx_tonkho_last_out` (`last_out_at`),
  ADD KEY `idx_tonkho_last_in` (`last_in_at`);

--
-- Chỉ mục cho bảng `khuyenmai`
--
ALTER TABLE `khuyenmai`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `ma` (`ma`),
  ADD KEY `idx_khuyenmai_ma` (`ma`),
  ADD KEY `idx_khuyenmai_active_dates` (`is_active`,`ngay_bat_dau`,`ngay_ket_thuc`),
  ADD KEY `idx_khuyenmai_assigned_user` (`assigned_user_id`);

--
-- Chỉ mục cho bảng `khuyenmai_usage`
--
ALTER TABLE `khuyenmai_usage`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_km_usage_km` (`khuyenmai_id`),
  ADD KEY `idx_km_usage_user` (`user_id`),
  ADD KEY `idx_km_usage_order` (`donhang_id`);

--
-- Chỉ mục cho bảng `km_ngayle`
--
ALTER TABLE `km_ngayle`
  ADD PRIMARY KEY (`id`);

--
-- Chỉ mục cho bảng `km_ngayle_products`
--
ALTER TABLE `km_ngayle_products`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_kmle_product` (`km_id`,`sanpham_id`);

--
-- Chỉ mục cho bảng `km_sanpham`
--
ALTER TABLE `km_sanpham`
  ADD PRIMARY KEY (`id`);

--
-- Chỉ mục cho bảng `km_sanpham_items`
--
ALTER TABLE `km_sanpham_items`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_km_product` (`km_id`,`sanpham_id`);

--
-- Chỉ mục cho bảng `lienhe`
--
ALTER TABLE `lienhe`
  ADD PRIMARY KEY (`id`);

--
-- Chỉ mục cho bảng `nhaphang`
--
ALTER TABLE `nhaphang`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_nhaphang_sanpham` (`sanpham_id`);

--
-- Chỉ mục cho bảng `password_resets`
--
ALTER TABLE `password_resets`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_email` (`email`);

--
-- Chỉ mục cho bảng `phieuxuat`
--
ALTER TABLE `phieuxuat`
  ADD PRIMARY KEY (`id`),
  ADD KEY `sanpham_id` (`sanpham_id`),
  ADD KEY `created_at` (`created_at`);

--
-- Chỉ mục cho bảng `product_ratings`
--
ALTER TABLE `product_ratings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_rate` (`user_id`,`product_id`),
  ADD KEY `idx_product` (`product_id`),
  ADD KEY `idx_user` (`user_id`);

--
-- Chỉ mục cho bảng `promo_bundle`
--
ALTER TABLE `promo_bundle`
  ADD PRIMARY KEY (`id`);

--
-- Chỉ mục cho bảng `promo_bundle_items`
--
ALTER TABLE `promo_bundle_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `bundle_id` (`bundle_id`),
  ADD KEY `product_id` (`product_id`);

--
-- Chỉ mục cho bảng `sanpham`
--
ALTER TABLE `sanpham`
  ADD PRIMARY KEY (`id`);

--
-- Chỉ mục cho bảng `taikhoan`
--
ALTER TABLE `taikhoan`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Chỉ mục cho bảng `tintuc`
--
ALTER TABLE `tintuc`
  ADD PRIMARY KEY (`id`);

--
-- Chỉ mục cho bảng `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- AUTO_INCREMENT cho các bảng đã đổ
--

--
-- AUTO_INCREMENT cho bảng `chat_messages`
--
ALTER TABLE `chat_messages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=25;

--
-- AUTO_INCREMENT cho bảng `danhmuc`
--
ALTER TABLE `danhmuc`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT cho bảng `diachi_nhanhang`
--
ALTER TABLE `diachi_nhanhang`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT cho bảng `donhang`
--
ALTER TABLE `donhang`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=53;

--
-- AUTO_INCREMENT cho bảng `donhang_chitiet`
--
ALTER TABLE `donhang_chitiet`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=88;

--
-- AUTO_INCREMENT cho bảng `favorites`
--
ALTER TABLE `favorites`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT cho bảng `hoso`
--
ALTER TABLE `hoso`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT cho bảng `khuyenmai`
--
ALTER TABLE `khuyenmai`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT cho bảng `khuyenmai_usage`
--
ALTER TABLE `khuyenmai_usage`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT cho bảng `km_ngayle`
--
ALTER TABLE `km_ngayle`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT cho bảng `km_ngayle_products`
--
ALTER TABLE `km_ngayle_products`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT cho bảng `km_sanpham`
--
ALTER TABLE `km_sanpham`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT cho bảng `km_sanpham_items`
--
ALTER TABLE `km_sanpham_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT cho bảng `lienhe`
--
ALTER TABLE `lienhe`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT cho bảng `nhaphang`
--
ALTER TABLE `nhaphang`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT cho bảng `password_resets`
--
ALTER TABLE `password_resets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT cho bảng `phieuxuat`
--
ALTER TABLE `phieuxuat`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT cho bảng `product_ratings`
--
ALTER TABLE `product_ratings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT cho bảng `promo_bundle`
--
ALTER TABLE `promo_bundle`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT cho bảng `promo_bundle_items`
--
ALTER TABLE `promo_bundle_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT cho bảng `taikhoan`
--
ALTER TABLE `taikhoan`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT cho bảng `tintuc`
--
ALTER TABLE `tintuc`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT cho bảng `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- Các ràng buộc cho các bảng đã đổ
--

--
-- Các ràng buộc cho bảng `diachi_nhanhang`
--
ALTER TABLE `diachi_nhanhang`
  ADD CONSTRAINT `fk_diachi_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Các ràng buộc cho bảng `donhang`
--
ALTER TABLE `donhang`
  ADD CONSTRAINT `fk_donhang_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Các ràng buộc cho bảng `donhang_chitiet`
--
ALTER TABLE `donhang_chitiet`
  ADD CONSTRAINT `fk_ctdh_donhang` FOREIGN KEY (`donhang_id`) REFERENCES `donhang` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_ctdh_sanpham` FOREIGN KEY (`sanpham_id`) REFERENCES `sanpham` (`id`) ON UPDATE CASCADE;

--
-- Các ràng buộc cho bảng `hoso`
--
ALTER TABLE `hoso`
  ADD CONSTRAINT `fk_hoso_taikhoan` FOREIGN KEY (`taikhoan_id`) REFERENCES `taikhoan` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Các ràng buộc cho bảng `khuyenmai_usage`
--
ALTER TABLE `khuyenmai_usage`
  ADD CONSTRAINT `fk_km_usage_km` FOREIGN KEY (`khuyenmai_id`) REFERENCES `khuyenmai` (`id`) ON DELETE CASCADE;

--
-- Các ràng buộc cho bảng `km_ngayle_products`
--
ALTER TABLE `km_ngayle_products`
  ADD CONSTRAINT `km_ngayle_products_ibfk_1` FOREIGN KEY (`km_id`) REFERENCES `km_ngayle` (`id`) ON DELETE CASCADE;

--
-- Các ràng buộc cho bảng `km_sanpham_items`
--
ALTER TABLE `km_sanpham_items`
  ADD CONSTRAINT `km_sanpham_items_ibfk_1` FOREIGN KEY (`km_id`) REFERENCES `km_sanpham` (`id`) ON DELETE CASCADE;

--
-- Các ràng buộc cho bảng `nhaphang`
--
ALTER TABLE `nhaphang`
  ADD CONSTRAINT `fk_nhaphang_sanpham` FOREIGN KEY (`sanpham_id`) REFERENCES `sanpham` (`id`) ON DELETE CASCADE;

--
-- Các ràng buộc cho bảng `phieuxuat`
--
ALTER TABLE `phieuxuat`
  ADD CONSTRAINT `fk_phieuxuat_sanpham` FOREIGN KEY (`sanpham_id`) REFERENCES `sanpham` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
-- Script để sửa cột product_id trong bảng favorites từ int sang varchar
-- Chạy script này trong phpMyAdmin hoặc MySQL command line

ALTER TABLE `favorites` 
MODIFY COLUMN `product_id` VARCHAR(50) NOT NULL;

-- Script để sửa cột product_id trong bảng product_ratings từ int sang varchar
-- Chạy script này trong phpMyAdmin hoặc MySQL command line

ALTER TABLE `product_ratings` 
MODIFY COLUMN `product_id` VARCHAR(50) NOT NULL;

