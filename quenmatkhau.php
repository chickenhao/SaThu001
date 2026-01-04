<?php
session_start();
require 'config.php';

// Load PHPMailer nếu có
$phpmailer_available = false;
if (file_exists(__DIR__ . "/PHPMailer/src/PHPMailer.php")) {
    require __DIR__ . "/PHPMailer/src/Exception.php";
    require __DIR__ . "/PHPMailer/src/PHPMailer.php";
    require __DIR__ . "/PHPMailer/src/SMTP.php";
    $phpmailer_available = true;
}

// Cấu hình SMTP
define("SMTP_HOST", "smtp.gmail.com");
define("SMTP_PORT", 587);
define("SMTP_USER", "skyticket.work@gmail.com");
define("SMTP_PASS", "dxknxhgvyeoamens");        // <-- Cần cập nhật App Password từ Gmail
define("MAIL_FROM_NAME", "banh");

// Hàm gửi email OTP
function send_otp_email($to, $otp) {
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
        $mail->Subject = "Mã OTP đặt lại mật khẩu";
        $mail->Body = "
            <div style='font-family:Arial;line-height:1.6'>
                <h3>Mã OTP đặt lại mật khẩu</h3>
                <p>Mã OTP của bạn là: <b style='font-size:20px;letter-spacing:2px'>{$otp}</b></p>
                <p>Mã có hiệu lực trong <b>5 phút</b>.</p>
                <p>Nếu bạn không yêu cầu, hãy bỏ qua email này.</p>
            </div>
        ";
        $mail->AltBody = "Mã OTP đặt lại mật khẩu của bạn là: {$otp}\nMã có hiệu lực trong 5 phút.";
        
        $mail->send();
        return [true, ""];
    } catch (Exception $e) {
        return [false, $mail->ErrorInfo];
    }
}

$error = "";
$step = isset($_GET['step']) ? $_GET['step'] : 'email'; // email, otp, reset
$email = isset($_GET['email']) ? trim($_GET['email']) : '';

// Bước 1: Nhập email và gửi OTP
if ($_SERVER["REQUEST_METHOD"] === "POST" && $step === 'email') {
    $email = trim($_POST["email"] ?? "");
    
    if ($email === "") {
        $error = "Vui lòng nhập email.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Email không hợp lệ.";
    } else {
        // Kiểm tra email có tồn tại không
        $stmt = $conn->prepare("SELECT id, otp_last_sent_at FROM users WHERE email = ? LIMIT 1");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        $stmt->close();
        
        if (!$user) {
            $error = "Email không tồn tại trong hệ thống.";
        } else {
            // Kiểm tra cooldown (60 giây)
            $RESEND_COOLDOWN = 60;
            if (!empty($user['otp_last_sent_at'])) {
                $last = strtotime($user['otp_last_sent_at']);
                if ($last !== false && (time() - $last) < $RESEND_COOLDOWN) {
                    $remain = $RESEND_COOLDOWN - (time() - $last);
                    $error = "Bạn vừa yêu cầu OTP. Vui lòng thử lại sau {$remain} giây.";
                } else {
                    // Gửi OTP
                    $otp = (string)random_int(100000, 999999);
                    $otp_hash = password_hash($otp, PASSWORD_DEFAULT);
                    $otp_expires_at = date("Y-m-d H:i:s", time() + 300); // 5 phút
                    $now = date("Y-m-d H:i:s");
                    
                    $updateStmt = $conn->prepare("UPDATE users SET otp_code = ?, otp_expires_at = ?, otp_last_sent_at = ? WHERE id = ?");
                    $updateStmt->bind_param("sssi", $otp_hash, $otp_expires_at, $now, $user['id']);
                    $updateStmt->execute();
                    $updateStmt->close();
                    
                    // Gửi email
                    [$email_sent, $email_error] = send_otp_email($email, $otp);
                    
                    if ($email_sent) {
                        header("Location: quenmatkhau.php?step=otp&email=" . urlencode($email));
                        exit;
                    } else {
                        $error = "Không thể gửi email. Lỗi: " . htmlspecialchars($email_error);
                    }
                }
            } else {
                // Lần đầu gửi
                $otp = (string)random_int(100000, 999999);
                $otp_hash = password_hash($otp, PASSWORD_DEFAULT);
                $otp_expires_at = date("Y-m-d H:i:s", time() + 300);
                $now = date("Y-m-d H:i:s");
                
                $updateStmt = $conn->prepare("UPDATE users SET otp_code = ?, otp_expires_at = ?, otp_last_sent_at = ? WHERE id = ?");
                $updateStmt->bind_param("sssi", $otp_hash, $otp_expires_at, $now, $user['id']);
                $updateStmt->execute();
                $updateStmt->close();
                
                [$email_sent, $email_error] = send_otp_email($email, $otp);
                
                if ($email_sent) {
                    header("Location: quenmatkhau.php?step=otp&email=" . urlencode($email));
                    exit;
                } else {
                    $error = "Không thể gửi email. Lỗi: " . htmlspecialchars($email_error);
                }
            }
        }
    }
}

// Bước 2: Xác thực OTP
if ($_SERVER["REQUEST_METHOD"] === "POST" && $step === 'otp') {
    $otp = trim($_POST["otp"] ?? "");
    
    if ($email === "" || $otp === "") {
        $error = "Vui lòng nhập đầy đủ email và OTP.";
    } else {
        $stmt = $conn->prepare("SELECT id, otp_code, otp_expires_at FROM users WHERE email = ? LIMIT 1");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        $stmt->close();
        
        if (!$user) {
            $error = "Email không tồn tại.";
        } elseif (empty($user['otp_code']) || empty($user['otp_expires_at'])) {
            $error = "Bạn chưa yêu cầu OTP hoặc OTP đã bị xóa.";
        } else {
            $exp = strtotime($user['otp_expires_at']);
            if ($exp === false || time() > $exp) {
                $error = "OTP đã hết hạn. Vui lòng yêu cầu lại.";
            } elseif (!password_verify($otp, $user['otp_code'])) {
                $error = "OTP không đúng!";
            } else {
                // OTP đúng, chuyển đến bước đặt lại mật khẩu
                $_SESSION['reset_password_user_id'] = $user['id'];
                $_SESSION['reset_password_email'] = $email;
                header("Location: quenmatkhau.php?step=reset&email=" . urlencode($email));
                exit;
            }
        }
    }
}

// Bước 3: Đặt lại mật khẩu
if ($_SERVER["REQUEST_METHOD"] === "POST" && $step === 'reset') {
    $password = $_POST["password"] ?? "";
    $password2 = $_POST["password2"] ?? "";
    $user_id = isset($_SESSION['reset_password_user_id']) ? (int)$_SESSION['reset_password_user_id'] : 0;
    
    if ($user_id <= 0) {
        $error = "Phiên đặt lại mật khẩu không hợp lệ. Vui lòng bắt đầu lại.";
        $step = 'email';
    } elseif ($password === "" || $password2 === "") {
        $error = "Vui lòng nhập đầy đủ mật khẩu.";
    } elseif ($password !== $password2) {
        $error = "Mật khẩu nhập lại không trùng khớp.";
    } elseif (strlen($password) < 6) {
        $error = "Mật khẩu phải có ít nhất 6 ký tự.";
    } else {
        $password_hash = password_hash($password, PASSWORD_DEFAULT);
        
        $updateStmt = $conn->prepare("UPDATE users SET password = ?, otp_code = NULL, otp_expires_at = NULL WHERE id = ?");
        $updateStmt->bind_param("si", $password_hash, $user_id);
        
        if ($updateStmt->execute()) {
            unset($_SESSION['reset_password_user_id']);
            unset($_SESSION['reset_password_email']);
                header("Location: dangky.php?success=2");
            exit;
        } else {
            $error = "Lỗi khi cập nhật mật khẩu.";
        }
        $updateStmt->close();
    }
}
?>
<!doctype html>
<html lang="vi">
<head>
  <meta charset="utf-8">
  <title>Quên mật khẩu</title>
  <style>
    body {
      font-family: Arial, sans-serif;
      background-image: url("https://tieudung.kinhtedothi.vn/dataimages/202401/09/huge/danisa-x-pmq---tet-2024---hinh-1_1704796718.jpg"); /* đổi link nếu muốn */
      background-size: cover;
      background-position: center;
      background-repeat: no-repeat;
      height: 100vh;
      display: flex;
      justify-content: center;
      align-items: center;
      margin: 0;
    }

    form#forgotForm {
      background: rgba(255, 255, 255, 0.55);
      backdrop-filter: blur(12px);
      border: 1px solid rgba(255,255,255,0.35);
      padding: 22px;
      border-radius: 14px;
box-shadow: 0 0 18px rgba(0,0,0,0.35);
      width: 330px;
      box-sizing: border-box;
      text-align: center;
    }

    .logo img {
      width: 85px;
      height: 85px;
      border-radius: 50%;
      background: rgba(255,255,255,0.35);
      backdrop-filter: blur(8px);
      padding: 8px;
      margin-bottom: 8px;
      box-shadow: 0 0 14px rgba(255,255,255,0.45);
    }

    h2 {
      margin: 4px 0 8px;
      font-size: 20px;
      color: #111827;
    }

    .error {
      color: red;
      font-size: 13px;
      margin-bottom: 6px;
    }

    label {
      display: block;
      margin-top: 8px;
      font-size: 13px;
      font-weight: 600;
      text-align: left;
      color: #333;
    }

    input[type="text"],
    input[type="tel"],
    input[type="password"] {
      width: 100%;
      padding: 8px;
      margin-top: 4px;
      border-radius: 5px;
      border: 1px solid #ccc;
      font-size: 14px;
      height: 38px;
      box-sizing: border-box;
    }

    button[type="submit"] {
      width: 100%;
      margin-top: 14px;
      background: #111827;
      color: white;
      border: none;
      padding: 10px;
      border-radius: 5px;
      font-size: 15px;
      cursor: pointer;
    }

    button[type="submit"]:hover {
      background: #e65c00;
    }

    a {
      color: #111827;
      font-weight: 600;
      text-decoration: none;
    }

    a:hover {
      text-decoration: underline;
    }

    p {
      font-size: 13px;
      margin-top: 10px;
    }
  </style>
</head>
<body>

<form id="forgotForm" method="post" action="">
  <div class="logo">
    <img src="image/anh1.png" alt="Logo">
  </div>

  <?php if ($step === 'email'): ?>
    <h2>Quên mật khẩu</h2>
    <p style="font-size: 13px; color: #666; margin-bottom: 15px;">Nhập email của bạn để nhận mã OTP đặt lại mật khẩu</p>
    
    <?php if (!empty($error)): ?>
      <p class="error"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></p>
    <?php endif; ?>

    <label for="email">Email đã đăng ký:</label>
    <input type="email" id="email" name="email" required
           placeholder="Nhập email của bạn"
           value="<?php echo htmlspecialchars($email, ENT_QUOTES, 'UTF-8'); ?>">

    <button type="submit">Gửi mã OTP</button>

  <?php elseif ($step === 'otp'): ?>
    <h2>Nhập mã OTP</h2>
    <p style="font-size: 13px; color: #666; margin-bottom: 15px;">Chúng tôi đã gửi mã OTP đến email: <strong><?php echo htmlspecialchars($email, ENT_QUOTES, 'UTF-8'); ?></strong></p>
    
    <?php if (!empty($error)): ?>
      <p class="error"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></p>
    <?php endif; ?>

    <input type="hidden" name="email" value="<?php echo htmlspecialchars($email, ENT_QUOTES, 'UTF-8'); ?>">
    
    <label for="otp">Mã OTP (6 số):</label>
    <input type="text" id="otp" name="otp" required
           placeholder="Nhập mã OTP"
           maxlength="6"
           pattern="[0-9]{6}"
           style="text-align: center; font-size: 18px; letter-spacing: 4px;">

    <button type="submit">Xác thực OTP</button>
    
    <p style="margin-top: 10px; font-size: 12px;">
      <a href="quenmatkhau.php?step=email&email=<?php echo urlencode($email); ?>">Gửi lại mã OTP</a>
    </p>

  <?php elseif ($step === 'reset'): ?>
    <h2>Đặt lại mật khẩu</h2>
    <p style="font-size: 13px; color: #666; margin-bottom: 15px;">Nhập mật khẩu mới cho tài khoản: <strong><?php echo htmlspecialchars($email, ENT_QUOTES, 'UTF-8'); ?></strong></p>
    
    <?php if (!empty($error)): ?>
      <p class="error"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></p>
    <?php endif; ?>

    <label for="password">Mật khẩu mới:</label>
    <input type="password" id="password" name="password" required 
           placeholder="Tối thiểu 6 ký tự" minlength="6">

    <label for="password2">Nhập lại mật khẩu mới:</label>
    <input type="password" id="password2" name="password2" required 
           placeholder="Nhập lại mật khẩu" minlength="6">

    <button type="submit">Đổi mật khẩu</button>
  <?php endif; ?>

  <p style="margin-top: 15px;"><a href="dangnhap.php">Quay lại đăng nhập</a></p>
</form>

</body>
</html>