<?php
session_start();
require 'config.php';

header('Content-Type: application/json');

// Lấy tổng tiền từ session cart
$total = 0;
if (!empty($_SESSION['cart'])) {
    foreach ($_SESSION['cart'] as $item) {
        $total += $item['price'] * $item['quantity'];
    }
}

$couponCode = strtoupper(trim($_GET['code'] ?? ''));
$response = [
    'success' => false,
    'discountAmount' => 0,
    'totalAfterDiscount' => $total,
    'message' => ''
];

if (empty($couponCode)) {
    $response['message'] = 'Vui lòng nhập mã khuyến mãi';
    echo json_encode($response);
    exit;
}

$today = date('Y-m-d');
$totalInt = (int)$total;

// Tìm mã trong bảng khuyenmai
$sqlOneCoupon = "
    SELECT *
    FROM khuyenmai
    WHERE ma = ?
      AND is_active = 1
      AND (ngay_bat_dau IS NULL OR ngay_bat_dau = '0000-00-00' OR ngay_bat_dau <= ?)
      AND (ngay_ket_thuc IS NULL OR ngay_ket_thuc = '0000-00-00' OR ngay_ket_thuc >= ?)
      AND (dieu_kien_tong_tien IS NULL OR dieu_kien_tong_tien = 0 OR dieu_kien_tong_tien <= ?)
    LIMIT 1
";

$stmtCoupon = $conn->prepare($sqlOneCoupon);
if ($stmtCoupon) {
    $stmtCoupon->bind_param('sssi', $couponCode, $today, $today, $totalInt);
    $stmtCoupon->execute();
    $resC = $stmtCoupon->get_result();
    $couponRow = $resC->fetch_assoc();
    $stmtCoupon->close();

    if ($couponRow) {
        $loai = $couponRow['loai'];
        $giaTri = (float)$couponRow['gia_tri'];
        $discountAmount = 0;

        if ($loai === 'percent') {
            $discountPercent = max(0, min(100, $giaTri));
            $discountAmount = $total * $discountPercent / 100;
        } else {
            $discountAmount = $giaTri;
            if ($discountAmount > $total) {
                $discountAmount = $total;
            }
        }

        $totalAfterDiscount = $total - $discountAmount;

        $response['success'] = true;
        $response['discountAmount'] = $discountAmount;
        $response['totalAfterDiscount'] = $totalAfterDiscount;
        $response['message'] = 'Áp dụng mã khuyến mãi thành công!';
    } else {
        $response['message'] = 'Mã khuyến mãi không hợp lệ hoặc không áp dụng cho đơn hàng này.';
    }
} else {
    $response['message'] = 'Lỗi hệ thống khi kiểm tra mã khuyến mãi.';
}

echo json_encode($response);
?>

