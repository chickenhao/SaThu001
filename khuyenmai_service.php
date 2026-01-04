<?php
function getCouponByCode(mysqli $conn, string $code) {
  $stmt = $conn->prepare("SELECT * FROM khuyenmai WHERE ma = ? LIMIT 1");
  $stmt->bind_param("s", $code);
  $stmt->execute();
  return $stmt->get_result()->fetch_assoc();
}

function isCouponExpired(array $km): bool {
  if (!empty($km['ngay_bat_dau']) && strtotime($km['ngay_bat_dau']) > time()) return true; // chưa tới ngày
  if (!empty($km['ngay_ket_thuc']) && strtotime($km['ngay_ket_thuc']) < time()) return true; // hết hạn
  return false;
}

function validateCoupon(mysqli $conn, array $km, float $orderTotal, int $itemsCount, ?int $userId): array {
  if ((int)$km['is_active'] !== 1) {
    return [false, "Mã giảm giá đang tắt."];
  }

  if (isCouponExpired($km)) {
    return [false, "Mã giảm giá đã hết hạn hoặc chưa tới thời gian áp dụng."];
  }

  $minTotal = (float)($km['dieu_kien_tong_tien'] ?? 0);
  if ($orderTotal < $minTotal) {
    return [false, "Đơn hàng chưa đạt tối thiểu " . number_format($minTotal,0,',','.') . "₫."];
  }

  if (!empty($km['min_items']) && $itemsCount < (int)$km['min_items']) {
    return [false, "Đơn hàng phải có tối thiểu " . (int)$km['min_items'] . " sản phẩm."];
  }

  if (!empty($km['usage_limit'])) {
    if ((int)$km['used_count'] >= (int)$km['usage_limit']) {
      return [false, "Mã giảm giá đã đạt giới hạn lượt sử dụng."];
    }
  }

  // cấp riêng cho 1 user
  if (!empty($km['assigned_user_id']) && $userId) {
    if ((int)$km['assigned_user_id'] !== (int)$userId) {
      return [false, "Mã giảm giá này không áp dụng cho tài khoản của bạn."];
    }
  } elseif (!empty($km['assigned_user_id']) && !$userId) {
    return [false, "Bạn cần đăng nhập để dùng mã này."];
  }

  // giới hạn theo user
  if (!empty($km['per_user_limit']) && $userId) {
    $stmt = $conn->prepare("
      SELECT COUNT(*) AS c
      FROM khuyenmai_usage
      WHERE khuyenmai_id = ? AND user_id = ?
    ");
    $stmt->bind_param("ii", $km['id'], $userId);
    $stmt->execute();
    $c = (int)($stmt->get_result()->fetch_assoc()['c'] ?? 0);
    if ($c >= (int)$km['per_user_limit']) {
      return [false, "Bạn đã dùng mã này đủ số lần cho phép."];
    }
  }

  return [true, "OK"];
}

function calcDiscount(array $km, float $orderTotal): float {
  $val = (float)($km['gia_tri'] ?? 0);
  $discount = 0;

  if ($km['loai'] === 'percent') {
    $discount = $orderTotal * ($val / 100);
    if (!empty($km['max_discount'])) {
      $discount = min($discount, (float)$km['max_discount']);
    }
  } else { // amount
    $discount = $val;
  }

  // không cho âm / không vượt quá tổng đơn
  $discount = max(0, min($discount, $orderTotal));
  return $discount;
}
