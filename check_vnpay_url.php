<?php
/**
 * File helper để kiểm tra URL callback VNPay
 * Truy cập file này trong trình duyệt để xem URL callback đúng
 */

// Bật hiển thị lỗi
error_reporting(E_ALL);
ini_set('display_errors', 1);

?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kiểm tra URL VNPay Callback</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 800px;
            margin: 50px auto;
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
        .url-box {
            background: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 15px;
            margin: 20px 0;
            word-break: break-all;
        }
        .code {
            background: #f4f4f4;
            padding: 10px;
            border-radius: 4px;
            font-family: monospace;
            margin: 10px 0;
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
        .error {
            background: #ffebee;
            border-left: 4px solid #f44336;
            padding: 15px;
            margin: 20px 0;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔍 Kiểm tra URL Callback VNPay</h1>
        
        <?php
        // Lấy thông tin URL
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
        $host = $_SERVER['HTTP_HOST'];
        $script = $_SERVER['SCRIPT_NAME'];
        $path = dirname($script);
        $path = str_replace('\\', '/', $path);
        if ($path === '/' || $path === '\\') {
            $path = '';
        }
        
        $baseUrl = $protocol . $host . $path;
        $returnUrl = $baseUrl . '/vnp_return.php';
        
        // Kiểm tra file vnp_return.php có tồn tại không
        $returnFile = __DIR__ . '/vnp_return.php';
        $fileExists = file_exists($returnFile);
        ?>
        
        <div class="info-box">
            <h3>📋 Thông tin hiện tại:</h3>
            <p><strong>Protocol:</strong> <?= $protocol ?></p>
            <p><strong>Host:</strong> <?= $host ?></p>
            <p><strong>Script Path:</strong> <?= $script ?></p>
            <p><strong>Directory Path:</strong> <?= $path ?></p>
        </div>
        
        <div class="url-box">
            <h3>✅ URL Callback được phát hiện:</h3>
            <div class="code"><?= htmlspecialchars($returnUrl) ?></div>
        </div>
        
        <?php if ($fileExists): ?>
            <div class="info-box">
                <h3>✅ File vnp_return.php tồn tại</h3>
                <p>Đường dẫn file: <code><?= htmlspecialchars($returnFile) ?></code></p>
            </div>
        <?php else: ?>
            <div class="error">
                <h3>❌ File vnp_return.php không tồn tại!</h3>
                <p>Đường dẫn kiểm tra: <code><?= htmlspecialchars($returnFile) ?></code></p>
                <p>Vui lòng đảm bảo file <code>vnp_return.php</code> nằm trong cùng thư mục với file này.</p>
            </div>
        <?php endif; ?>
        
        <div class="info-box">
            <h3>📝 Hướng dẫn cập nhật:</h3>
            <p>1. Mở file <code>vnp_config.php</code></p>
            <p>2. Tìm dòng có <code>define('VNP_RETURN_URL', ...)</code></p>
            <p>3. Thay thế bằng URL bên dưới:</p>
            <div class="code">define('VNP_RETURN_URL', '<?= htmlspecialchars($returnUrl) ?>');</div>
            
            <p><strong>Hoặc</strong> nếu URL trên không hoạt động, thử các URL sau:</p>
            <ul>
                <li><code>http://localhost/Danisa/vnp_return.php</code></li>
                <li><code>http://localhost/htdocs/Danisa/vnp_return.php</code></li>
                <li><code>http://127.0.0.1/Danisa/vnp_return.php</code></li>
            </ul>
        </div>
        
        <div class="info-box">
            <h3>🧪 Test URL:</h3>
            <p>Nhấn vào nút bên dưới để kiểm tra xem URL callback có hoạt động không:</p>
            <a href="<?= htmlspecialchars($returnUrl) ?>" class="btn" target="_blank">Test URL Callback</a>
            <p style="margin-top: 10px; font-size: 12px; color: #666;">
                Nếu thấy trang "Kết quả thanh toán" hoặc không có lỗi 404, URL đã đúng!
            </p>
        </div>
        
        <div class="info-box">
            <h3>⚠️ Lưu ý quan trọng:</h3>
            <ul>
                <li>URL callback phải có thể truy cập được từ internet (không phải localhost) khi deploy lên server thực</li>
                <li>Khi test trên localhost, VNPay có thể không gọi được URL callback</li>
                <li>Để test đầy đủ, bạn có thể sử dụng <strong>ngrok</strong> hoặc công cụ tương tự để tạo URL công khai</li>
            </ul>
        </div>
    </div>
</body>
</html>

