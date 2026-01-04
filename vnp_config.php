<?php
/**
 * Cấu hình VNPay
 * 
 * LƯU Ý: Bạn cần đăng ký tài khoản tại https://sandbox.vnpayment.vn/
 * và lấy các thông tin sau:
 * - TmnCode: Mã website của bạn
 * - SecretKey: Khóa bảo mật
 */

// Cấu hình VNPay
define('VNP_URL', 'https://sandbox.vnpayment.vn/paymentv2/vpcpay.html'); // URL thanh toán (sandbox)
// define('VNP_URL', 'https://www.vnpayment.vn/paymentv2/vpcpay.html'); // URL thanh toán (production)

// Thông tin từ VNPay (Sandbox)
define('VNP_TMN_CODE', 'F1OPFH7C'); // Terminal ID / Mã Website
define('VNP_HASH_SECRET', 'BMHLFAYJRM4JKK0A8SNAXBMMTPLEHXHZ'); // Secret Key / Chuỗi bí mật

// URL callback sau khi thanh toán
// Tự động phát hiện URL base của website
if (!function_exists('getBaseUrl')) {
    function getBaseUrl() {
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $script = $_SERVER['SCRIPT_NAME'] ?? '';
        $path = dirname($script);
        
        // Loại bỏ các thư mục không cần thiết
        $path = str_replace('\\', '/', $path);
        if ($path === '/' || $path === '\\') {
            $path = '';
        }
        
        return $protocol . $host . $path;
    }
}

// Tự động tạo URL callback
// Nếu URL tự động không đúng, hãy comment dòng dưới và uncomment một trong các URL thủ công bên dưới
$baseUrl = getBaseUrl();
// Tạo URL callback - KHÔNG encode ở đây, sẽ được encode khi tạo hashdata
$returnUrl = $baseUrl . '/vnp_return.php';
define('VNP_RETURN_URL', $returnUrl);

// ===== CÁC TÙY CHỌN URL THỦ CÔNG (nếu tự động không hoạt động) =====
// Bỏ comment một trong các dòng dưới và comment dòng define('VNP_RETURN_URL', $returnUrl); ở trên
// LƯU Ý: Nếu đường dẫn có khoảng trắng (ví dụ: "Danisa-main (2)"), VNPay có thể không chấp nhận
// Giải pháp: Sử dụng URL thủ công với encode đúng cách hoặc đổi tên thư mục không có khoảng trắng

// define('VNP_RETURN_URL', 'http://localhost/tung_lam/Danisa-main%20(2)/Danisa-main/Danisa-main/vnp_return.php');
// define('VNP_RETURN_URL', 'http://localhost/Danisa/vnp_return.php');
// define('VNP_RETURN_URL', 'http://localhost/htdocs/Danisa/vnp_return.php');
// define('VNP_RETURN_URL', 'http://127.0.0.1/Danisa/vnp_return.php');

// Để tìm URL đúng, truy cập: http://localhost/Danisa/check_vnpay_url.php

// Cấu hình khác
define('VNP_VERSION', '2.1.0');
define('VNP_COMMAND', 'pay');
define('VNP_CURRENCY_CODE', 'VND');
define('VNP_LOCALE', 'vn'); // vn hoặc en

/**
 * Hàm tạo chữ ký (hash) cho VNPay
 */
function vnp_create_hash($data) {
    ksort($data);
    $query = '';
    $i = 0;
    $hashdata = '';
    
    foreach ($data as $key => $value) {
        if ($i == 1) {
            $hashdata .= '&' . urlencode($key) . "=" . urlencode($value);
        } else {
            $hashdata .= urlencode($key) . "=" . urlencode($value);
            $i = 1;
        }
        $query .= urlencode($key) . "=" . urlencode($value) . '&';
    }
    
    $vnp_url = $query;
    if (defined('VNP_HASH_SECRET') && VNP_HASH_SECRET !== '') {
        $vnpSecureHash = hash_hmac('sha512', $hashdata, VNP_HASH_SECRET);
        $vnp_url .= 'vnp_SecureHash=' . $vnpSecureHash;
    }
    
    return $vnpSecureHash ?? '';
}

/**
 * Hàm kiểm tra chữ ký từ VNPay callback
 * 
 * @param array $data Mảng chứa tất cả các tham số vnp_* (đã loại bỏ vnp_SecureHash và vnp_SecureHashType)
 * @param string $secureHash Chữ ký từ VNPay (vnp_SecureHash)
 * @return bool True nếu chữ ký hợp lệ, False nếu không hợp lệ
 */
function vnp_verify_hash($data, $secureHash) {
    if (empty($secureHash)) {
        return false;
    }
    
    $vnp_HashSecret = VNP_HASH_SECRET;
    
    // Loại bỏ các tham số không cần thiết khi tạo hash
    if (isset($data['vnp_SecureHash'])) {
        unset($data['vnp_SecureHash']);
    }
    if (isset($data['vnp_SecureHashType'])) {
        unset($data['vnp_SecureHashType']);
    }
    
    // KHÔNG loại bỏ các giá trị rỗng - VNPay yêu cầu giữ lại tất cả tham số
    // Chỉ loại bỏ các giá trị null (không có trong mảng)
    
    // Sắp xếp mảng theo key
    ksort($data);
    
    // Tạo chuỗi hashdata
    $i = 0;
    $hashdata = '';
    
    foreach ($data as $key => $value) {
        // Chỉ bỏ qua nếu giá trị là null (không tồn tại)
        if ($value === null) {
            continue;
        }
        
        // Chuyển đổi giá trị thành chuỗi (kể cả số 0 và chuỗi rỗng)
        $valueStr = (string)$value;
        
        if ($i == 1) {
            $hashdata .= '&' . urlencode($key) . "=" . urlencode($valueStr);
        } else {
            $hashdata .= urlencode($key) . "=" . urlencode($valueStr);
            $i = 1;
        }
    }
    
    // Tạo chữ ký từ hashdata
    $vnpSecureHash = hash_hmac('sha512', $hashdata, $vnp_HashSecret);
    
    // So sánh chữ ký (không phân biệt hoa thường)
    return strtoupper($vnpSecureHash) === strtoupper($secureHash);
}

