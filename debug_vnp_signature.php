<?php
/**
 * File debug để kiểm tra chữ ký VNPay trước khi gửi đi
 * File này sẽ mô phỏng cách tạo chữ ký giống như trong vnp_create_payment.php
 */

require 'vnp_config.php';

// Mô phỏng dữ liệu giống như khi tạo thanh toán
date_default_timezone_set('Asia/Ho_Chi_Minh');

$donhang_id = 123; // ID đơn hàng test
$totalAfterDiscount = 50000; // 50,000 VND

$vnp_TxnRef = $donhang_id . '_' . time();
$vnp_Amount = (int)($totalAfterDiscount * 100);
$vnp_OrderInfo = 'Thanh toan don hang #' . $donhang_id;
$vnp_OrderType = 'other';
$vnp_IpAddr = '127.0.0.1';
$vnp_CreateDate = date('YmdHis');

// Tạo mảng dữ liệu giống như trong vnp_create_payment.php
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

// Tạo chữ ký
ksort($inputData);
$query = '';
$hashdata = '';
$i = 0;

foreach ($inputData as $key => $value) {
    $valueStr = (string)$value;
    
    if ($i == 1) {
        $hashdata .= '&' . urlencode($key) . "=" . urlencode($valueStr);
    } else {
        $hashdata .= urlencode($key) . "=" . urlencode($valueStr);
        $i = 1;
    }
    $query .= urlencode($key) . "=" . urlencode($valueStr) . '&';
}

$vnpSecureHash = hash_hmac('sha512', $hashdata, VNP_HASH_SECRET);
$vnp_Url = VNP_URL . "?" . $query . 'vnp_SecureHash=' . $vnpSecureHash;

?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Debug VNPay Signature</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 1200px;
            margin: 20px auto;
            padding: 20px;
            background: #f5f5f5;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1 {
            color: #333;
            border-bottom: 2px solid #4CAF50;
            padding-bottom: 10px;
        }
        .info-box {
            background: #e8f5e9;
            border-left: 4px solid #4CAF50;
            padding: 15px;
            margin: 20px 0;
        }
        .code {
            background: #f4f4f4;
            padding: 10px;
            border-radius: 4px;
            font-family: monospace;
            margin: 10px 0;
            word-break: break-all;
            font-size: 12px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        table th, table td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }
        table th {
            background: #4CAF50;
            color: white;
        }
        .btn {
            display: inline-block;
            padding: 10px 20px;
            background: #4CAF50;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            margin-top: 10px;
        }
        .btn:hover {
            background: #45a049;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔍 Debug VNPay Signature (Trước khi gửi)</h1>
        
        <div class="info-box">
            <h3>Thông tin cấu hình:</h3>
            <p><strong>TMN Code:</strong> <?= htmlspecialchars(VNP_TMN_CODE) ?></p>
            <p><strong>Secret Key:</strong> <?= htmlspecialchars(VNP_HASH_SECRET) ?></p>
            <p><strong>Return URL:</strong> <?= htmlspecialchars(VNP_RETURN_URL) ?></p>
        </div>
        
        <h3>Dữ liệu gửi đi:</h3>
        <table>
            <tr>
                <th>Tham số</th>
                <th>Giá trị</th>
            </tr>
            <?php foreach ($inputData as $key => $value): ?>
            <tr>
                <td><?= htmlspecialchars($key) ?></td>
                <td class="code"><?= htmlspecialchars($value) ?></td>
            </tr>
            <?php endforeach; ?>
        </table>
        
        <h3>Chuỗi hashdata (để tạo chữ ký):</h3>
        <div class="code"><?= htmlspecialchars($hashdata) ?></div>
        
        <h3>Chữ ký được tạo:</h3>
        <div class="code"><?= htmlspecialchars($vnpSecureHash) ?></div>
        
        <h3>URL thanh toán đầy đủ:</h3>
        <div class="code" style="max-height: 200px; overflow-y: auto;"><?= htmlspecialchars($vnp_Url) ?></div>
        
        <div class="info-box">
            <h3>💡 Hướng dẫn:</h3>
            <p>1. Kiểm tra xem chuỗi hashdata có đúng format không</p>
            <p>2. So sánh chữ ký với chữ ký từ VNPay (nếu có)</p>
            <p>3. Kiểm tra URL callback có đúng không</p>
            <p>4. Nhấn nút bên dưới để test thanh toán (sẽ redirect đến VNPay)</p>
            <a href="<?= htmlspecialchars($vnp_Url) ?>" class="btn" target="_blank">Test thanh toán VNPay</a>
        </div>
        
        <div class="info-box">
            <h3>⚠️ Lưu ý:</h3>
            <ul>
                <li>Đây là môi trường test, sử dụng thẻ test để thanh toán</li>
                <li>Nếu gặp lỗi "Sai chữ ký", kiểm tra lại Secret Key và cách tạo hashdata</li>
                <li>Đảm bảo URL callback có thể truy cập được</li>
            </ul>
        </div>
    </div>
</body>
</html>

