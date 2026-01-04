<?php
/**
 * File tạo URL thanh toán VNPay
 * File này được gọi từ thanhtoan.php khi người dùng chọn thanh toán online
 */

session_start();
require 'config.php';
require 'vnp_config.php';

// Kiểm tra dữ liệu từ POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: thanhtoan.php');
    exit;
}

// Lấy thông tin từ form
$name = trim($_POST['name'] ?? '');
$phone = trim($_POST['phone'] ?? '');
$addr = trim($_POST['address'] ?? '');
$couponInput = strtoupper(trim($_POST['coupon'] ?? ''));
$paymentMethod = $_POST['payment_method'] ?? '';

// Kiểm tra giỏ hàng
if (empty($_SESSION['cart'])) {
    header('Location: giohang.php?error=Giỏ hàng trống');
    exit;
}

$cartData = $_SESSION['cart'];

// Tính tổng tiền
$total = 0;
foreach ($cartData as $item) {
    $total += $item['price'] * $item['quantity'];
}

// Xử lý mã khuyến mãi nếu có
$discountAmount = 0;
$totalAfterDiscount = $total;

if ($couponInput !== '') {
    $today = date('Y-m-d');
    $sqlCoupon = "
        SELECT *
        FROM khuyenmai
        WHERE ma = ?
          AND is_active = 1
          AND (ngay_bat_dau IS NULL OR ngay_bat_dau = '0000-00-00' OR ngay_bat_dau <= ?)
          AND (ngay_ket_thuc IS NULL OR ngay_ket_thuc = '0000-00-00' OR ngay_ket_thuc >= ?)
          AND (dieu_kien_tong_tien IS NULL OR dieu_kien_tong_tien = 0 OR dieu_kien_tong_tien <= ?)
        LIMIT 1
    ";
    $stmtCoupon = $conn->prepare($sqlCoupon);
    if ($stmtCoupon) {
        $totalInt = (int)$total;
        $stmtCoupon->bind_param('sssi', $couponInput, $today, $today, $totalInt);
        $stmtCoupon->execute();
        $resC = $stmtCoupon->get_result();
        $couponRow = $resC->fetch_assoc();
        $stmtCoupon->close();

        if ($couponRow) {
            $loai = $couponRow['loai'];
            $giaTri = (float)$couponRow['gia_tri'];

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
        }
    }
}

// Lưu đơn hàng tạm thời với trạng thái "pending" (chưa thanh toán)
$user_id = !empty($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : NULL;
$statusDefault = 'pending';
$paymentText = 'Thanh toán online qua VNPay';
$email = '';
$ghi_chu = '';

$conn->begin_transaction();

try {
    // Lưu đơn hàng
    if ($user_id !== NULL && $user_id > 0) {
        $stmt = $conn->prepare("
            INSERT INTO donhang (
                user_id,
                ho_ten,
                phone,
                email,
                dia_chi,
                tong_tien,
                trang_thai,
                phuong_thuc_thanh_toan,
                ghi_chu,
                created_at,
                updated_at
            ) VALUES (
                ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW()
            )
        ");
        
        if (!$stmt) {
            throw new Exception('Lỗi hệ thống (prepare donhang): ' . $conn->error);
        }
        
        $stmt->bind_param(
            'issssdsss',
            $user_id,
            $name,
            $phone,
            $email,
            $addr,
            $totalAfterDiscount,
            $statusDefault,
            $paymentText,
            $ghi_chu
        );
    } else {
        $stmt = $conn->prepare("
            INSERT INTO donhang (
                user_id,
                ho_ten,
                phone,
                email,
                dia_chi,
                tong_tien,
                trang_thai,
                phuong_thuc_thanh_toan,
                ghi_chu,
                created_at,
                updated_at
            ) VALUES (
                NULL, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW()
            )
        ");
        
        if (!$stmt) {
            throw new Exception('Lỗi hệ thống (prepare donhang): ' . $conn->error);
        }
        
        $stmt->bind_param(
            'ssssdsss',
            $name,
            $phone,
            $email,
            $addr,
            $totalAfterDiscount,
            $statusDefault,
            $paymentText,
            $ghi_chu
        );
    }

    if (!$stmt->execute()) {
        throw new Exception('Không thể lưu đơn hàng: ' . $stmt->error);
    }

    $donhang_id = $conn->insert_id;
    $stmt->close();

    // Lưu chi tiết đơn hàng
    $sql_ct = "
        INSERT INTO donhang_chitiet (
            donhang_id,
            sanpham_id,
            so_luong,
            don_gia,
            thanh_tien
        ) VALUES (?,?,?,?,?)
    ";
    $stmt_ct = $conn->prepare($sql_ct);
    if (!$stmt_ct) {
        throw new Exception('Lỗi hệ thống (prepare donhang_chitiet): ' . $conn->error);
    }

    $stmtCheckId = $conn->prepare("SELECT id FROM sanpham WHERE id = ? LIMIT 1");
    $stmtCheckName = $conn->prepare("SELECT id FROM sanpham WHERE name = ? LIMIT 1");

    if (!$stmtCheckId || !$stmtCheckName) {
        throw new Exception('Lỗi hệ thống (prepare sanpham): ' . $conn->error);
    }

    $discountPercent = ($total > 0) ? ($discountAmount / $total) * 100 : 0;

    foreach ($cartData as $item) {
        $sanpham_id_cart = isset($item['id']) ? (string)$item['id'] : '';
        $sanpham_id_real = '';

        if ($sanpham_id_cart !== '') {
            $stmtCheckId->bind_param('s', $sanpham_id_cart);
            $stmtCheckId->execute();
            $stmtCheckId->store_result();

            if ($stmtCheckId->num_rows > 0) {
                $sanpham_id_real = $sanpham_id_cart;
            }
        }

        if ($sanpham_id_real === '') {
            $nameSp = $item['name'] ?? '';
            $stmtCheckName->bind_param('s', $nameSp);
            $stmtCheckName->execute();
            $stmtCheckName->bind_result($idFound);
            if ($stmtCheckName->fetch()) {
                $sanpham_id_real = $idFound;
            }
            $stmtCheckName->free_result();
        }

        if ($sanpham_id_real === '') {
            throw new Exception('Sản phẩm "' . ($item['name'] ?? '') . '" không tồn tại.');
        }

        $qty = (int)$item['quantity'];
        $don_gia = (float)$item['price'];
        $lineTotal = $don_gia * $qty;
        $lineAfterDisc = $lineTotal * (1 - $discountPercent / 100);

        $stmt_ct->bind_param(
            'isidd',
            $donhang_id,
            $sanpham_id_real,
            $qty,
            $don_gia,
            $lineAfterDisc
        );

        if (!$stmt_ct->execute()) {
            throw new Exception('Không thể lưu chi tiết đơn hàng: ' . $stmt_ct->error);
        }
    }

    $stmt_ct->close();
    $stmtCheckId->close();
    $stmtCheckName->close();

    $conn->commit();

    // Lưu thông tin đơn hàng vào session để dùng khi callback
    $_SESSION['pending_order_id'] = $donhang_id;
    $_SESSION['pending_order_total'] = $totalAfterDiscount;

    // Tạo dữ liệu thanh toán VNPay
    // Đảm bảo timezone đúng (VNPay yêu cầu timezone Việt Nam)
    date_default_timezone_set('Asia/Ho_Chi_Minh');
    
    $vnp_TxnRef = $donhang_id . '_' . time(); // Mã đơn hàng duy nhất
    $vnp_Amount = (int)($totalAfterDiscount * 100); // VNPay yêu cầu số tiền tính bằng xu (VND * 100), đảm bảo là số nguyên
    $vnp_OrderInfo = 'Thanh toan don hang #' . $donhang_id;
    $vnp_OrderType = 'other';
    
    // Lấy IP address (xử lý trường hợp đằng sau proxy)
    $vnp_IpAddr = $_SERVER['REMOTE_ADDR'];
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
        $vnp_IpAddr = trim($ips[0]);
    }
    // Nếu là localhost, dùng IP mặc định
    if ($vnp_IpAddr === '127.0.0.1' || $vnp_IpAddr === '::1' || empty($vnp_IpAddr)) {
        $vnp_IpAddr = '127.0.0.1'; // VNPay sandbox chấp nhận localhost
    }
    
    $vnp_CreateDate = date('YmdHis'); // Format: YYYYMMDDHHmmss (ví dụ: 20251227133500)

    // Tạo mảng dữ liệu gửi đến VNPay
    $inputData = array(
        "vnp_Version" => VNP_VERSION,
        "vnp_TmnCode" => VNP_TMN_CODE,
        "vnp_Amount" => $vnp_Amount,
        "vnp_Command" => VNP_COMMAND,
        "vnp_CreateDate" => $vnp_CreateDate,
        "vnp_CurrCode" => VNP_CURRENCY_CODE,
        "vnp_IpAddr" => $vnp_IpAddr,
        "vnp_Locale" => VNP_LOCALE,
        "vnp_OrderInfo" => $vnp_OrderInfo,
        "vnp_OrderType" => $vnp_OrderType,
        "vnp_ReturnUrl" => VNP_RETURN_URL,
        "vnp_TxnRef" => $vnp_TxnRef,
    );

    // Lưu vnp_TxnRef vào session để verify sau
    $_SESSION['vnp_TxnRef'] = $vnp_TxnRef;

    // Tạo chữ ký theo chuẩn VNPay
    // Bước 1: Sắp xếp các tham số theo key
    ksort($inputData);
    
    // Bước 2: Tạo chuỗi hashdata (dùng để tạo chữ ký)
    $hashdata = '';
    $i = 0;
    foreach ($inputData as $key => $value) {
        // Chuyển đổi giá trị thành chuỗi
        $valueStr = (string)$value;
        
        if ($i == 1) {
            $hashdata .= '&' . urlencode($key) . "=" . urlencode($valueStr);
        } else {
            $hashdata .= urlencode($key) . "=" . urlencode($valueStr);
            $i = 1;
        }
    }
    
    // Bước 3: Tạo chữ ký bằng HMAC SHA512
    $vnpSecureHash = hash_hmac('sha512', $hashdata, VNP_HASH_SECRET);
    
    // Bước 4: Tạo query string cho URL
    $query = '';
    foreach ($inputData as $key => $value) {
        $valueStr = (string)$value;
        $query .= urlencode($key) . "=" . urlencode($valueStr) . '&';
    }
    
    // Bước 5: Tạo URL thanh toán đầy đủ
    $vnp_Url = VNP_URL . "?" . $query . 'vnp_SecureHash=' . $vnpSecureHash;

    // Redirect đến VNPay
    header('Location: ' . $vnp_Url);
    exit;

} catch (Exception $e) {
    $conn->rollback();
    header('Location: thanhtoan.php?error=' . urlencode('Lỗi: ' . $e->getMessage()));
    exit;
}

