<?php
/**
 * File xử lý callback từ VNPay sau khi thanh toán
 * VNPay sẽ redirect về file này với các tham số kết quả thanh toán
 */

session_start();
require 'config.php';
require 'vnp_config.php';

// Lấy dữ liệu từ VNPay
$vnp_SecureHash = $_GET['vnp_SecureHash'] ?? '';
$vnp_TxnRef = $_GET['vnp_TxnRef'] ?? '';
$vnp_ResponseCode = $_GET['vnp_ResponseCode'] ?? '';
$vnp_TransactionStatus = $_GET['vnp_TransactionStatus'] ?? '';
$vnp_Amount = $_GET['vnp_Amount'] ?? '';
$vnp_TransactionNo = $_GET['vnp_TransactionNo'] ?? '';
$vnp_BankCode = $_GET['vnp_BankCode'] ?? '';
$vnp_OrderInfo = $_GET['vnp_OrderInfo'] ?? '';

// Lấy tất cả các tham số từ VNPay
$inputData = array();
foreach ($_GET as $key => $value) {
    if (substr($key, 0, 4) == "vnp_") {
        $inputData[$key] = $value;
    }
}

// Lưu chữ ký từ VNPay
$vnp_SecureHash_FromVNPay = $inputData['vnp_SecureHash'] ?? '';

// Xóa vnp_SecureHash và vnp_SecureHashType khỏi mảng để verify (phải xóa trước khi tạo hash)
unset($inputData['vnp_SecureHash']);
unset($inputData['vnp_SecureHashType']);

// Verify chữ ký
$isValid = vnp_verify_hash($inputData, $vnp_SecureHash_FromVNPay);

// Debug mode (chỉ bật khi cần thiết, tắt khi deploy production)
$debug_mode = true; // Đặt thành true để xem thông tin debug
if ($debug_mode) {
    // Tạo hash để so sánh (giống như trong hàm verify)
    $vnp_HashSecret = VNP_HASH_SECRET;
    $debugData = $inputData;
    ksort($debugData);
    $i = 0;
    $hashdata = '';
    foreach ($debugData as $key => $value) {
        if ($value === null) {
            continue;
        }
        $valueStr = (string)$value;
        if ($i == 1) {
            $hashdata .= '&' . urlencode($key) . "=" . urlencode($valueStr);
        } else {
            $hashdata .= urlencode($key) . "=" . urlencode($valueStr);
            $i = 1;
        }
    }
    $calculatedHash = hash_hmac('sha512', $hashdata, $vnp_HashSecret);
    
    error_log("=== VNPay Debug ===");
    error_log("SecureHash from VNPay: " . $vnp_SecureHash_FromVNPay);
    error_log("Calculated Hash: " . $calculatedHash);
    error_log("Hash Match: " . (strtoupper($calculatedHash) === strtoupper($vnp_SecureHash_FromVNPay) ? 'YES' : 'NO'));
    error_log("Input Data: " . print_r($inputData, true));
    error_log("Hashdata String: " . $hashdata);
    error_log("Is Valid: " . ($isValid ? 'YES' : 'NO'));
}

// Lấy đơn hàng từ vnp_TxnRef (format: donhang_id_timestamp)
$orderId = null;
if (!empty($vnp_TxnRef)) {
    $parts = explode('_', $vnp_TxnRef);
    if (!empty($parts[0])) {
        $orderId = (int)$parts[0];
    }
}

$success = false;
$message = '';
$orderStatus = '';

if ($isValid) {
    // Kiểm tra mã phản hồi
    // vnp_ResponseCode = '00' nghĩa là thanh toán thành công
    if ($vnp_ResponseCode == '00' && $vnp_TransactionStatus == '00') {
        // Thanh toán thành công
        if ($orderId) {
            // Cập nhật trạng thái đơn hàng
            $conn->begin_transaction();
            
            try {
                // Cập nhật trạng thái đơn hàng thành "paid"
                $stmt = $conn->prepare("
                    UPDATE donhang 
                    SET trang_thai = 'paid',
                        updated_at = NOW()
                    WHERE id = ? AND trang_thai = 'pending'
                ");
                
                if ($stmt) {
                    $stmt->bind_param('i', $orderId);
                    if ($stmt->execute()) {
                        // Kiểm tra xem có cột vnp_transaction_no không (nếu đã thêm vào DB)
                        // Nếu chưa có thì bỏ qua, nếu có thì cập nhật
                        $checkColumn = $conn->query("SHOW COLUMNS FROM donhang LIKE 'vnp_transaction_no'");
                        if ($checkColumn && $checkColumn->num_rows > 0) {
                            $stmt2 = $conn->prepare("
                                UPDATE donhang 
                                SET vnp_transaction_no = ?,
                                    vnp_txn_ref = ?
                                WHERE id = ?
                            ");
                            if ($stmt2) {
                                $stmt2->bind_param('ssi', $vnp_TransactionNo, $vnp_TxnRef, $orderId);
                                $stmt2->execute();
                                $stmt2->close();
                            }
                        }
                        
                        $conn->commit();
                        $success = true;
                        $message = 'Thanh toán thành công! Đơn hàng của bạn đã được xác nhận.';
                        $orderStatus = 'paid';
                        
                        // Xóa giỏ hàng
                        if (isset($_SESSION['cart'])) {
                            $_SESSION['cart'] = [];
                        }
                    } else {
                        throw new Exception('Không thể cập nhật đơn hàng: ' . $stmt->error);
                    }
                    $stmt->close();
                } else {
                    throw new Exception('Lỗi prepare: ' . $conn->error);
                }
            } catch (Exception $e) {
                $conn->rollback();
                $message = 'Lỗi cập nhật đơn hàng: ' . $e->getMessage();
            }
        } else {
            $message = 'Không tìm thấy thông tin đơn hàng.';
        }
    } else {
        // Thanh toán thất bại
        $message = 'Thanh toán thất bại. Mã lỗi: ' . $vnp_ResponseCode;
        if ($vnp_ResponseCode == '07') {
            $message = 'Giao dịch bị nghi ngờ (liên quan tới lừa đảo, giao dịch bất thường).';
        } elseif ($vnp_ResponseCode == '09') {
            $message = 'Thẻ/Tài khoản chưa đăng ký dịch vụ InternetBanking.';
        } elseif ($vnp_ResponseCode == '10') {
            $message = 'Xác thực thông tin thẻ/tài khoản không đúng. Quá 3 lần.';
        } elseif ($vnp_ResponseCode == '11') {
            $message = 'Giao dịch đã quá thời gian chờ thanh toán (15 phút). Vui lòng quay lại trang thanh toán và tạo đơn hàng mới.';
        } elseif ($vnp_ResponseCode == '12') {
            $message = 'Thẻ/Tài khoản bị khóa.';
        } elseif ($vnp_ResponseCode == '51') {
            $message = 'Tài khoản không đủ số dư để thực hiện giao dịch.';
        } elseif ($vnp_ResponseCode == '65') {
            $message = 'Tài khoản đã vượt quá hạn mức giao dịch trong ngày.';
        } elseif ($vnp_ResponseCode == '75') {
            $message = 'Ngân hàng thanh toán đang bảo trì.';
        } elseif ($vnp_ResponseCode == '79') {
            $message = 'Nhập sai mật khẩu thanh toán quá số lần quy định.';
        }
    }
} else {
    // Chữ ký không hợp lệ
    $message = 'Chữ ký không hợp lệ. Giao dịch có thể bị giả mạo.';
}

?>
<!doctype html>
<html lang="vi">
<head>
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width,initial-scale=1" />
<title>Kết quả thanh toán - Danisa</title>
<link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="trangchu.css">
<style>
  .payment-result {
    background: url('image/anhnen3.jpg') center/cover no-repeat;
    min-height: 650px;
    display: flex;
    justify-content: center;
    align-items: center;
    padding: 40px 20px;
  }
  .result-container {
    background: rgba(255,255,255,0.95);
    border-radius: 12px;
    padding: 40px;
    max-width: 600px;
    width: 100%;
    text-align: center;
    box-shadow: 0 10px 30px rgba(0,0,0,0.25);
  }
  .result-icon {
    font-size: 64px;
    margin-bottom: 20px;
  }
  .result-icon.success {
    color: #10b981;
  }
  .result-icon.error {
    color: #ef4444;
  }
  .result-title {
    font-size: 24px;
    font-weight: 600;
    margin-bottom: 15px;
    color: #111827;
  }
  .result-message {
    font-size: 16px;
    color: #6b7280;
    margin-bottom: 30px;
    line-height: 1.6;
  }
  .result-info {
    background: #f9fafb;
    border-radius: 8px;
    padding: 20px;
    margin-bottom: 30px;
    text-align: left;
  }
  .result-info-item {
    display: flex;
    justify-content: space-between;
    margin-bottom: 10px;
    font-size: 14px;
  }
  .result-info-item:last-child {
    margin-bottom: 0;
  }
  .result-info-label {
    color: #6b7280;
    font-weight: 500;
  }
  .result-info-value {
    color: #111827;
    font-weight: 600;
  }
  .result-actions {
    display: flex;
    gap: 15px;
    justify-content: center;
  }
  .btn {
    padding: 12px 24px;
    border-radius: 8px;
    text-decoration: none;
    font-weight: 600;
    font-size: 14px;
    display: inline-block;
    transition: all 0.3s;
  }
  .btn-primary {
    background: #1e40af;
    color: #fff;
  }
  .btn-primary:hover {
    background: #1d3a94;
  }
  .btn-secondary {
    background: #e5e7eb;
    color: #374151;
  }
  .btn-secondary:hover {
    background: #d1d5db;
  }
</style>
</head>
<body>
<?php include 'header_front.php'; ?>

<section class="payment-result">
  <div class="result-container">
    <?php if ($success): ?>
      <div class="result-icon success">✓</div>
      <h1 class="result-title">Thanh toán thành công!</h1>
      <p class="result-message"><?= htmlspecialchars($message) ?></p>
      
      <?php if ($orderId): ?>
      <div class="result-info">
        <div class="result-info-item">
          <span class="result-info-label">Mã đơn hàng:</span>
          <span class="result-info-value">#<?= $orderId ?></span>
        </div>
        <?php if (!empty($vnp_TransactionNo)): ?>
        <div class="result-info-item">
          <span class="result-info-label">Mã giao dịch VNPay:</span>
          <span class="result-info-value"><?= htmlspecialchars($vnp_TransactionNo) ?></span>
        </div>
        <?php endif; ?>
        <?php if (!empty($vnp_BankCode)): ?>
        <div class="result-info-item">
          <span class="result-info-label">Ngân hàng:</span>
          <span class="result-info-value"><?= htmlspecialchars($vnp_BankCode) ?></span>
        </div>
        <?php endif; ?>
      </div>
      <?php endif; ?>
      
      <div class="result-actions">
        <a href="trangchu.php" class="btn btn-primary">Về trang chủ</a>
        <?php if ($orderId): ?>
        <a href="donhang_view.php?id=<?= $orderId ?>" class="btn btn-secondary">Xem đơn hàng</a>
        <?php endif; ?>
      </div>
    <?php else: ?>
      <div class="result-icon error">✗</div>
      <h1 class="result-title">Thanh toán thất bại</h1>
      <p class="result-message"><?= htmlspecialchars($message) ?></p>
      
      <?php if ($orderId): ?>
      <div class="result-info">
        <div class="result-info-item">
          <span class="result-info-label">Mã đơn hàng:</span>
          <span class="result-info-value">#<?= $orderId ?></span>
        </div>
        <div class="result-info-item">
          <span class="result-info-label">Mã lỗi:</span>
          <span class="result-info-value"><?= htmlspecialchars($vnp_ResponseCode) ?></span>
        </div>
      </div>
      <?php endif; ?>
      
      <div class="result-actions">
        <a href="thanhtoan.php" class="btn btn-primary">Thử lại thanh toán</a>
        <a href="trangchu.php" class="btn btn-secondary">Về trang chủ</a>
      </div>
    <?php endif; ?>
  </div>
</section>

<footer style="
  background:#111827;
  color:white;
  text-align:center;
  padding: 20px 0;
  font-size:12px;
  line-height:1.8;
  font-weight:500;
">
  <div class="linklien">
    <a href="#" style="color:white; text-decoration:none; margin:0 8px;">Đã được bảo lưu mọi quyền</a> |
    <a href="lienhelienhe.php" style="color:white; text-decoration:none; margin:0 8px;">Liên hệ với chúng tôi</a> |
    <a href="dieukien.php" style="color:white; text-decoration:none; margin:0 8px;">Điều khoản và Điều kiện</a> |
    <a href="chinhsach.php" style="color:white; text-decoration:none; margin:0 8px;">Chính sách bảo mật</a>
  </div>
</footer>
</body>
</html>

