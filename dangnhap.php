<?php
session_start();

// ====== DB ======
$servername = "localhost";
$usernameDB = "root";
$passwordDB = "";
$dbname     = "dangky_db";

$conn = new mysqli($servername, $usernameDB, $passwordDB, $dbname);
if ($conn->connect_error) die("Kết nối DB lỗi: " . $conn->connect_error);
$conn->set_charset("utf8mb4");

// ===== Helpers =====
function h($s){ return htmlspecialchars($s ?? "", ENT_QUOTES, "UTF-8"); }

// ====== TOTP (Google Authenticator) - không cần thư viện ======
function base32_decode_custom($b32) {
  $alphabet = "ABCDEFGHIJKLMNOPQRSTUVWXYZ234567";
  $b32 = strtoupper(preg_replace('/[^A-Z2-7]/', '', $b32));
  $bits = "";
  for ($i=0; $i<strlen($b32); $i++) {
    $val = strpos($alphabet, $b32[$i]);
    if ($val === false) continue;
    $bits .= str_pad(decbin($val), 5, "0", STR_PAD_LEFT);
  }
  $bytes = "";
  for ($i=0; $i+8 <= strlen($bits); $i+=8) {
    $bytes .= chr(bindec(substr($bits, $i, 8)));
  }
  return $bytes;
}

function totp_code($secret_b32, $timeSlice = null) {
  if ($timeSlice === null) $timeSlice = floor(time() / 30);
  $secret = base32_decode_custom($secret_b32);
  $time = pack("N*", 0) . pack("N*", $timeSlice); // 8 bytes
  $hash = hash_hmac("sha1", $time, $secret, true);
  $offset = ord(substr($hash, -1)) & 0x0F;
  $part = substr($hash, $offset, 4);
  $value = unpack("N", $part)[1] & 0x7FFFFFFF;
  $mod = $value % 1000000;
  return str_pad((string)$mod, 6, "0", STR_PAD_LEFT);
}

function verify_totp($secret_b32, $code, $window = 1) {
  $code = preg_replace('/\D/', '', $code);
  if (strlen($code) !== 6) return false;
  $ts = floor(time() / 30);
  for ($i = -$window; $i <= $window; $i++) {
    if (totp_code($secret_b32, $ts + $i) === $code) return true;
  }
  return false;
}

// ====== LOGIC ======
$message = "";

// Nếu đã qua bước password và cần 2FA
$need2fa = isset($_SESSION["2fa_pending_user_id"]);

// Xử lý redirect sau khi đăng nhập
$redirect = $_GET['redirect'] ?? 'trangchu.php';

if ($_SERVER["REQUEST_METHOD"] === "POST") {
  if (!$need2fa) {
    // ---- BƯỚC 1: email + password ----
    $email = trim($_POST["email"] ?? "");
    $password = $_POST["password"] ?? "";

    if ($email === "" || $password === "") {
      $message = "❌ Vui lòng nhập email và mật khẩu!";
    } else {
      $stmt = $conn->prepare("SELECT id, username, password, status, email_verified_at, ho_ten, role 
                              FROM users WHERE email = ? LIMIT 1");
      $stmt->bind_param("s", $email);
      $stmt->execute();
      $res = $stmt->get_result();

      if ($u = $res->fetch_assoc()) {
        if (!password_verify($password, $u["password"])) {
          $message = "❌ Sai mật khẩu!";
        } else {
          // ✅ Chỉ yêu cầu xác thực email cho tài khoản mới (status = 'pending')
          // Các tài khoản cũ (status = 'active' dù email_verified_at = NULL) vẫn được phép đăng nhập
          if ($u["status"] === "pending" && empty($u["email_verified_at"])) {
            $message = '❌ Tài khoản chưa xác thực email. '
                     . 'Bạn có thể <a href="send_otp.php?email=' . urlencode($email) . '">gửi lại email xác thực</a>.';
          } else {
            // Login thành công - set session với đầy đủ thông tin
            $_SESSION["user_id"] = (int)$u["id"];
            $_SESSION["username"] = $u["username"];
            $_SESSION["ho_ten"] = $u["ho_ten"] ?? $u["username"];
            $_SESSION["role"] = $u["role"] ?? "customer";
            $_SESSION["email"] = $email;
            
            // Cập nhật last_login
            $updateStmt = $conn->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
            $updateStmt->bind_param("i", $u["id"]);
            $updateStmt->execute();
            $updateStmt->close();
            
            header("Location: " . htmlspecialchars($redirect));
            exit;
          }
        }
      } else {
        $message = "❌ Email không tồn tại!";
      }
    }
  } else {
    // ---- BƯỚC 2: nhập mã 2FA ----
    $code = trim($_POST["code"] ?? "");
    $uid = (int)($_SESSION["2fa_pending_user_id"] ?? 0);

    if ($uid <= 0) {
      $message = "❌ Phiên xác thực không hợp lệ. Vui lòng đăng nhập lại.";
      unset($_SESSION["2fa_pending_user_id"]);
      $need2fa = false;
    } else {
      // Bỏ phần 2FA vì không có trong database
      $message = "❌ Phiên xác thực không hợp lệ. Vui lòng đăng nhập lại.";
      unset($_SESSION["2fa_pending_user_id"]);
      $need2fa = false;
    }
  }
}
?>
<!doctype html>
<html lang="vi">
<head>
  <meta charset="utf-8">
  <title>Đăng nhập</title>
  <link rel="stylesheet" href="dangnhap.css">
</head>
<body>
<form method="POST">
  <div class="logo">
    <img src="image/anh1.png" alt="Logo">
  </div>

  <h2 style="margin:0 0 8px;"><?php echo $need2fa ? "Xác thực 2 lớp" : "Đăng nhập"; ?></h2>

  <?php if ($message): ?>
    <div class="msg" style="margin-top:10px;font-size:13px;line-height:1.4;color:red;"><?php echo $message; ?></div>
  <?php endif; ?>

  <?php if (!$need2fa): ?>
    <label>Email</label>
    <input name="email" type="text" placeholder="Nhập email" required>

    <label>Mật khẩu</label>
    <input name="password" type="password" placeholder="Nhập mật khẩu" required>

    <button type="submit">Đăng nhập</button>

    <p style="font-size:13px;margin-top:10px;">
      <a href="quenmatkhau.php">Quên mật khẩu?</a>
    </p>
    <p style="font-size:13px;margin-top:10px;">Chưa có tài khoản? <a href="register.php">Đăng ký</a></p>
  <?php else: ?>
    <label>Mã 2FA (Google Authenticator)</label>
    <input name="code" type="text" placeholder="Nhập mã 6 số" required>

    <button type="submit">Xác nhận</button>

    <p style="font-size:13px;margin-top:10px;">
      <a href="logout.php">Hủy & đăng nhập lại</a>
    </p>
  <?php endif; ?>
</form>
</body>
</html>

