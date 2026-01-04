<?php
session_start();
require 'config.php';

// Load PHPMailer nếu có
$phpmailer_available = false;
$phpmailer_error = "";
if (file_exists(__DIR__ . "/PHPMailer/src/PHPMailer.php")) {
    try {
        require __DIR__ . "/PHPMailer/src/Exception.php";
        require __DIR__ . "/PHPMailer/src/PHPMailer.php";
        require __DIR__ . "/PHPMailer/src/SMTP.php";
        $phpmailer_available = true;
    } catch (Exception $e) {
        $phpmailer_error = $e->getMessage();
    }
} else {
    $phpmailer_error = "File PHPMailer/src/PHPMailer.php không tồn tại";
}

// Cấu hình SMTP (cần cập nhật với thông tin email của bạn)
define("SMTP_HOST", "smtp.gmail.com");
define("SMTP_PORT", 587);
define("SMTP_USER", "skyticket.work@gmail.com");
define("SMTP_PASS", "dxknxhgvyeoamens");        // <-- Cần cập nhật App Password từ Gmail
define("MAIL_FROM_NAME", "banh");

// Hàm gửi email OTP
function send_otp_email($to, $otp, $purpose = 'xác thực tài khoản') {
    global $phpmailer_available;
    
    if (!$phpmailer_available) {
        return [false, "PHPMailer chưa được cài đặt"];
    }
    
    try {
        $mail = new PHPMailer\PHPMailer\PHPMailer(true);
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USER;
        $mail->Password   = SMTP_PASS;
        $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = SMTP_PORT;
        $mail->CharSet = "UTF-8";
        
        $mail->setFrom(SMTP_USER, MAIL_FROM_NAME);
        $mail->addAddress($to);
        
        $mail->isHTML(true);
        $mail->Subject = "Mã OTP " . $purpose;
        $mail->Body = "
            <div style='font-family:Arial;line-height:1.6'>
                <h3>Mã OTP " . htmlspecialchars($purpose) . "</h3>
                <p>Mã OTP của bạn là: <b style='font-size:20px;letter-spacing:2px'>{$otp}</b></p>
                <p>Mã có hiệu lực trong <b>5 phút</b>.</p>
                <p>Nếu bạn không yêu cầu, hãy bỏ qua email này.</p>
            </div>
        ";
        $mail->AltBody = "Mã OTP " . $purpose . " của bạn là: {$otp}\nMã có hiệu lực trong 5 phút.";
        
        $mail->send();
        return [true, ""];
    } catch (Exception $e) {
        return [false, $mail->ErrorInfo];
    }
}

$message = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_register'])) {
    $fullname = trim($_POST['fullname'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['confirm_password'] ?? '';
    $username = trim($_POST['username'] ?? $email); // Tạo username từ email nếu không có

    // Validate đơn giản
    if ($fullname === '' || $email === '' || $password === '' || $confirm === '') {
        $message = "Vui lòng nhập đầy đủ thông tin.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = "Email không hợp lệ.";
    } elseif ($password !== $confirm) {
        $message = "Mật khẩu xác nhận không khớp.";
    } elseif (strlen($password) < 6) {
        $message = "Mật khẩu phải có ít nhất 6 ký tự.";
    } else {
        // Kiểm tra email đã tồn tại chưa
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ? OR username = ?");
        $stmt->bind_param("ss", $email, $username);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows > 0) {
            $message = "Email hoặc tên đăng nhập đã được sử dụng.";
        } else {
            // Mã hoá mật khẩu
            $hash = password_hash($password, PASSWORD_BCRYPT);
            
            // Tạo OTP 6 số
            $otp = (string)random_int(100000, 999999);
            $otp_hash = password_hash($otp, PASSWORD_DEFAULT);
            $otp_expires_at = date("Y-m-d H:i:s", time() + 300); // 5 phút
            $now = date("Y-m-d H:i:s");
            
            // Lưu user với status='pending' và chưa verify email
            $stmt = $conn->prepare("INSERT INTO users (username, email, password, ho_ten, role, status, otp_code, otp_expires_at, otp_last_sent_at) VALUES (?, ?, ?, ?, 'customer', 'pending', ?, ?, ?)");
            $stmt->bind_param("sssssss", $username, $email, $hash, $fullname, $otp_hash, $otp_expires_at, $now);

            if ($stmt->execute()) {
                // Gửi OTP qua email
                if ($phpmailer_available) {
                    [$email_sent, $email_error] = send_otp_email($email, $otp, 'xác thực tài khoản');
                    
                    if ($email_sent) {
                        // Chuyển đến trang xác thực OTP
                        header("Location: verify_otp.php?email=" . urlencode($email));
                        exit;
                    } else {
                        // Nếu không gửi được email, vẫn lưu user nhưng thông báo
                        $message = "Đăng ký thành công nhưng không thể gửi email. Lỗi: " . htmlspecialchars($email_error) . ". Bạn có thể <a href='send_otp.php?email=" . urlencode($email) . "'>gửi lại OTP</a>.";
                    }
                } else {
                    // PHPMailer chưa được cài đặt
                    $message = "Đăng ký thành công nhưng chưa cấu hình email. " . htmlspecialchars($phpmailer_error) . ". Bạn có thể <a href='send_otp.php?email=" . urlencode($email) . "'>gửi OTP thủ công</a>.";
                }
            } else {
                $message = "Lỗi khi đăng ký: " . $conn->error;
            }
        }

        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Đăng ký tài khoản</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            margin: 0;
        }
        .register-box {
            background: rgba(255, 255, 255, 0.95);
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.2);
            width: 400px;
            box-sizing: border-box;
        }
        h2 {
            margin: 0 0 20px;
            color: #333;
            text-align: center;
        }
        .message {
            padding: 10px;
            border-radius: 6px;
            margin-bottom: 15px;
            font-size: 14px;
        }
        .message.success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .message.error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        label {
            display: block;
            margin-top: 12px;
            font-size: 14px;
            font-weight: 600;
            color: #333;
        }
        input[type="text"],
        input[type="email"],
        input[type="password"] {
            width: 100%;
            padding: 10px;
            margin-top: 6px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 14px;
            box-sizing: border-box;
        }
        input:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        button[type="submit"] {
            width: 100%;
            margin-top: 20px;
            background: #667eea;
            color: white;
            border: none;
            padding: 12px;
            border-radius: 6px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.3s;
        }
        button[type="submit"]:hover {
            background: #5568d3;
        }
        .login-link {
            text-align: center;
            margin-top: 15px;
            font-size: 14px;
        }
        .login-link a {
            color: #667eea;
            text-decoration: none;
            font-weight: 600;
        }
        .login-link a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="register-box">
        <h2>Đăng ký tài khoản</h2>

        <?php if ($message): ?>
            <div class="message <?php echo $success ? 'success' : 'error'; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <form method="post" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>">
            <label>Họ và tên:</label>
            <input type="text" name="fullname" required placeholder="Nhập họ và tên" value="<?php echo htmlspecialchars($_POST['fullname'] ?? ''); ?>">

            <label>Tên đăng nhập (tùy chọn):</label>
            <input type="text" name="username" placeholder="Để trống sẽ dùng email" value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>">

            <label>Email:</label>
            <input type="email" name="email" required placeholder="Nhập email của bạn" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">

            <label>Mật khẩu:</label>
            <input type="password" name="password" required placeholder="Tối thiểu 6 ký tự" minlength="6">

            <label>Nhập lại mật khẩu:</label>
            <input type="password" name="confirm_password" required placeholder="Nhập lại mật khẩu" minlength="6">

            <button type="submit" name="submit_register">Đăng ký</button>
        </form>

        <div class="login-link">
            Đã có tài khoản? <a href="dangnhap.php">Đăng nhập</a>
        </div>
    </div>
</body>
</html>
