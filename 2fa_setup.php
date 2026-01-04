<?php
session_start();

// bắt buộc đăng nhập
if (empty($_SESSION["user_id"])) {
  header("Location: dangnhap.php");
  exit;
}

$servername = "localhost";
$usernameDB = "root";
$passwordDB = "";
$dbname     = "dangky_db";

$conn = new mysqli($servername, $usernameDB, $passwordDB, $dbname);
if ($conn->connect_error) die("Kết nối DB lỗi: " . $conn->connect_error);
$conn->set_charset("utf8mb4");

function h($s){ return htmlspecialchars($s ?? "", ENT_QUOTES, "UTF-8"); }

// ===== Base32 generate + TOTP verify =====
function base32_encode_custom($data) {
  $alphabet = "ABCDEFGHIJKLMNOPQRSTUVWXYZ234567";
  $bits = "";
  for ($i=0; $i<strlen($data); $i++) {
    $bits .= str_pad(decbin(ord($data[$i])), 8, "0", STR_PAD_LEFT);
  }
  $out = "";
  for ($i=0; $i<strlen($bits); $i+=5) {
    $chunk = substr($bits, $i, 5);
    if (strlen($chunk) < 5) $chunk = str_pad($chunk, 5, "0", STR_PAD_RIGHT);
    $out .= $alphabet[bindec($chunk)];
  }
  return $out;
}
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
  $time = pack("N*", 0) . pack("N*", $timeSlice);
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
function new_secret_b32() {
  $raw = random_bytes(20); // 160-bit
  return base32_encode_custom($raw);
}

// ====== Load user ======
$uid = (int)$_SESSION["user_id"];
$stmt = $conn->prepare("SELECT email, twofa_enabled, twofa_secret FROM users WHERE id = ? LIMIT 1");
$stmt->bind_param("i", $uid);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

if (!$user) die("User không tồn tại.");

$message = "";

// ====== Disable 2FA ======
if (isset($_POST["disable_2fa"])) {
  $up = $conn->prepare("UPDATE users SET twofa_enabled=0, twofa_secret=NULL WHERE id=?");
  $up->bind_param("i", $uid);
  $up->execute();
  $message = "✅ Đã tắt 2FA.";
  // reload user
  $stmt->execute();
  $user = $stmt->get_result()->fetch_assoc();
}

// ====== Generate new secret for setup (store in session temp) ======
if (empty($_SESSION["2fa_setup_secret"])) {
  $_SESSION["2fa_setup_secret"] = new_secret_b32();
}
$secret = $_SESSION["2fa_setup_secret"];

// ====== Enable 2FA ======
if (isset($_POST["enable_2fa"])) {
  $code = trim($_POST["code"] ?? "");
  if (!verify_totp($secret, $code, 1)) {
    $message = "❌ Mã 2FA không đúng. Hãy thử lại.";
  } else {
    $up = $conn->prepare("UPDATE users SET twofa_enabled=1, twofa_secret=? WHERE id=?");
    $up->bind_param("si", $secret, $uid);
    $up->execute();
    unset($_SESSION["2fa_setup_secret"]);
    $message = "✅ Bật 2FA thành công!";
    // reload user
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
  }
}

// ====== QR (otpauth URI) ======
$issuer = "Danisa";
$account = $user["email"];
$otpauth = "otpauth://totp/" . rawurlencode($issuer . ":" . $account)
         . "?secret=" . rawurlencode($secret)
         . "&issuer=" . rawurlencode($issuer);

// QR image (dùng dịch vụ tạo QR đơn giản)
$qrUrl = "https://api.qrserver.com/v1/create-qr-code/?size=220x220&data=" . urlencode($otpauth);
?>
<!doctype html>
<html lang="vi">
<head>
  <meta charset="utf-8">
  <title>Thiết lập 2FA</title>
  <style>
    body{font-family:Arial;background:#f3f4f6;margin:0;padding:30px}
    .box{max-width:760px;margin:0 auto;background:#fff;border-radius:14px;padding:22px;box-shadow:0 10px 25px rgba(0,0,0,.10)}
    .row{display:flex;gap:18px;flex-wrap:wrap}
    .card{flex:1;min-width:280px;border:1px solid #e5e7eb;border-radius:12px;padding:16px}
    input{width:100%;padding:10px;border:1px solid #d1d5db;border-radius:10px;box-sizing:border-box}
    button{padding:10px 12px;border:0;border-radius:10px;background:#111827;color:#fff;cursor:pointer}
    button:hover{opacity:.9}
    a{color:#111827;font-weight:700;text-decoration:none}
    a:hover{text-decoration:underline}
    .msg{margin:10px 0;font-size:14px}
  </style>
</head>
<body>
<div class="box">
  <h2 style="margin:0 0 8px;">Thiết lập Google Authenticator (2FA)</h2>
  <div class="msg"><?php echo h($message); ?></div>

  <div class="row">
    <div class="card">
      <h3 style="margin:0 0 8px;">Trạng thái</h3>
      <p style="margin:0 0 10px;">
        2FA: <b><?php echo ((int)$user["twofa_enabled"]===1) ? "ĐANG BẬT" : "CHƯA BẬT"; ?></b>
      </p>

      <?php if ((int)$user["twofa_enabled"]===1): ?>
        <form method="POST">
          <button name="disable_2fa" value="1" type="submit">Tắt 2FA</button>
        </form>
      <?php else: ?>
        <p style="margin:0;color:#374151;font-size:14px;">
          Scan QR bằng Google Authenticator → nhập mã 6 số để bật.
        </p>
      <?php endif; ?>
    </div>

    <div class="card">
      <h3 style="margin:0 0 8px;">QR Code</h3>
      <img src="<?php echo h($qrUrl); ?>" alt="QR 2FA" style="width:220px;height:220px;border-radius:10px;border:1px solid #e5e7eb">
      <p style="font-size:13px;color:#374151;">
        Nếu không scan được, nhập secret thủ công:<br>
        <b><?php echo h($secret); ?></b>
      </p>
    </div>

    <div class="card">
      <h3 style="margin:0 0 8px;">Xác nhận bật 2FA</h3>
      <form method="POST">
        <label style="font-size:13px;font-weight:700;">Nhập mã 6 số</label>
        <input name="code" placeholder="123456" required>
        <div style="margin-top:10px;">
          <button name="enable_2fa" value="1" type="submit">Bật 2FA</button>
        </div>
      </form>
    </div>
  </div>

  <p style="margin-top:14px;"><a href="index.php">← Về trang chính</a></p>
</div>
</body>
</html>
