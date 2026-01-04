<?php
// =======================
// verify_otp.php
// - Nhập OTP
// - Check hết hạn
// - Verify thành công: status=active, email_verified_at=NOW()
// - Xóa otp_code, otp_expires_at
// =======================

session_start();

// ===== DB =====
$servername = "localhost";
$usernameDB = "root";
$passwordDB = "";
$dbname     = "dangky_db";

$conn = new mysqli($servername, $usernameDB, $passwordDB, $dbname);
if ($conn->connect_error) die("Kết nối DB lỗi: " . $conn->connect_error);
$conn->set_charset("utf8mb4");

// ===== Helpers =====
function h($s){ return htmlspecialchars($s ?? "", ENT_QUOTES, "UTF-8"); }
function is_valid_email($email){ return filter_var($email, FILTER_VALIDATE_EMAIL); }

$email = trim($_GET["email"] ?? ($_POST["email"] ?? ""));
$message = "";
$success = false;

if ($email !== "" && !is_valid_email($email)) {
  $message = "❌ Email không hợp lệ.";
  $email = "";
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
  $otp = trim($_POST["otp"] ?? "");

  if ($email === "" || $otp === "") {
    $message = "❌ Vui lòng nhập đầy đủ email và OTP.";
  } else {
    $stmt = $conn->prepare("SELECT id, status, email_verified_at, otp_code, otp_expires_at 
                            FROM users WHERE email = ? LIMIT 1");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $res = $stmt->get_result();

    if (!($u = $res->fetch_assoc())) {
      $message = "❌ Email không tồn tại.";
    } else {
      if (!empty($u["email_verified_at"]) && $u["status"] === "active") {
        $message = "✅ Tài khoản đã xác thực rồi. Bạn có thể đăng nhập.";
        $success = true;
      } else {
        if (empty($u["otp_code"]) || empty($u["otp_expires_at"])) {
          $message = "❌ Bạn chưa yêu cầu OTP hoặc OTP đã bị xóa. Hãy gửi OTP lại.";
        } else {
          $exp = strtotime($u["otp_expires_at"]);
          if ($exp === false || time() > $exp) {
            $message = "❌ OTP đã hết hạn. Hãy gửi OTP lại.";
          } else {
            // So sánh OTP với hash
            if (!password_verify($otp, $u["otp_code"])) {
              $message = "❌ OTP không đúng!";
            } else {
              $uid = (int)$u["id"];
              $up = $conn->prepare("UPDATE users
                                    SET email_verified_at = NOW(),
                                        status='active',
                                        otp_code=NULL,
                                        otp_expires_at=NULL
                                    WHERE id=?");
              $up->bind_param("i", $uid);
              $up->execute();

              $message = "✅ Xác thực OTP thành công! Bạn có thể đăng nhập.";
              $success = true;
            }
          }
        }
      }
    }
  }
}
?>
<!doctype html>
<html lang="vi">
<head>
  <meta charset="utf-8">
  <title>Xác thực OTP</title>
  <style>
    body{font-family:Arial;background:#f3f4f6;height:100vh;display:flex;justify-content:center;align-items:center;margin:0}
    .box{background:#fff;padding:22px;border-radius:12px;box-shadow:0 10px 25px rgba(0,0,0,.12);width:420px;box-sizing:border-box}
    label{display:block;margin-top:10px;font-size:13px;font-weight:700}
    input{width:100%;padding:10px;border:1px solid #d1d5db;border-radius:10px;box-sizing:border-box;margin-top:6px}
    button{margin-top:12px;background:#111827;color:#fff;border:0;padding:10px 12px;border-radius:10px;cursor:pointer;width:100%}
    a{color:#111827;font-weight:700;text-decoration:none}
    a:hover{text-decoration:underline}
    .msg{margin-top:10px;font-size:14px;line-height:1.5}
  </style>
</head>
<body>
<div class="box">
  <h2 style="margin:0 0 8px;">Xác thực OTP</h2>

  <?php if ($message): ?>
    <div class="msg"><?php echo h($message); ?></div>
  <?php endif; ?>

  <?php if (!$success): ?>
    <form method="POST">
      <label>Email</label>
      <input name="email" value="<?php echo h($email); ?>" placeholder="Nhập email" required>

      <label>OTP (6 số)</label>
      <input name="otp" placeholder="123456" required>

      <button type="submit">Xác thực</button>
    </form>

    <?php if ($email): ?>
      <p style="margin-top:12px;">
        <a href="send_otp.php?email=<?php echo urlencode($email); ?>">Gửi lại OTP</a>
      </p>
    <?php else: ?>
      <p style="margin-top:12px;">
        <a href="send_otp.php">Gửi OTP</a>
      </p>
    <?php endif; ?>
  <?php else: ?>
    <p style="margin-top:12px;"><a href="dangky.php">→ Đi tới đăng nhập</a></p>
  <?php endif; ?>
</div>
</body>
</html>
