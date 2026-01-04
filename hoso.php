<?php
session_start();

// Bật hiển thị lỗi (chỉ dùng trên localhost/dev)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require 'config.php';

// ================== BẮT BUỘC ĐĂNG NHẬP ==================
if (empty($_SESSION['user_id'])) {
    header('Location: dangnhap.php');
    exit;
}
$user_id = (int)$_SESSION['user_id'];

$errors  = [];
$success = "";

// ================== XỬ LÝ ĐẶT ĐỊA CHỈ MẶC ĐỊNH ==================
if (isset($_GET['set_default'])) {
    $addrId = (int)$_GET['set_default'];

    // Kiểm tra địa chỉ có thuộc user không
    $check = $conn->prepare("SELECT id FROM diachi_nhanhang WHERE id = ? AND user_id = ?");
    if ($check) {
        $check->bind_param('ii', $addrId, $user_id);
        $check->execute();
        $resCheck = $check->get_result();
        if ($resCheck && $resCheck->num_rows > 0) {
            // Bỏ mặc định tất cả địa chỉ khác
            $conn->query("UPDATE diachi_nhanhang SET mac_dinh = 0 WHERE user_id = {$user_id}");
            // Đặt địa chỉ này là mặc định
            $stmtSet = $conn->prepare("UPDATE diachi_nhanhang SET mac_dinh = 1 WHERE id = ? AND user_id = ?");
            if ($stmtSet) {
                $stmtSet->bind_param('ii', $addrId, $user_id);
                $stmtSet->execute();
                $stmtSet->close();
                $success = "Đã cập nhật địa chỉ mặc định.";
            }
        }
        $check->close();
    } else {
        $errors[] = "Lỗi prepare set_default: " . $conn->error;
    }
}

// ================== XỬ LÝ FORM POST ==================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $formType = $_POST['form_type'] ?? '';

    // -------- Cập nhật hồ sơ cá nhân vào bảng users --------
    if ($formType === 'profile') {
        $ho_ten    = trim($_POST['ho_ten'] ?? '');
        $gioi_tinh = $_POST['gioi_tinh'] ?? 'Khác';
        $ngay_sinh = $_POST['ngay_sinh'] ?? '';
        $phone     = trim($_POST['phone'] ?? '');
        $email     = trim($_POST['email'] ?? '');
        $thong_tin = trim($_POST['thong_tin'] ?? '');

        if ($ho_ten === '') {
            $errors[] = 'Vui lòng nhập họ tên.';
        }
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Email không hợp lệ.';
        }

        if (empty($errors)) {
            // Nếu ngày sinh rỗng thì set NULL
            $dobParam = null;
            if ($ngay_sinh !== '') {
                $dobParam = $ngay_sinh; // yyyy-mm-dd
            }

            $sql = "UPDATE users
                    SET ho_ten = ?, gioi_tinh = ?, ngay_sinh = ?, phone = ?, email = ?, thong_tin = ?, updated_at = NOW()
                    WHERE id = ?";
            $stmt = $conn->prepare($sql);
            if (!$stmt) {
                $errors[] = "Lỗi prepare (profile): " . $conn->error;
            } else {
                $stmt->bind_param(
                    'ssssssi',
                    $ho_ten,
                    $gioi_tinh,
                    $dobParam,
                    $phone,
                    $email,
                    $thong_tin,
                    $user_id
                );
                if ($stmt->execute()) {
                    $success = "Cập nhật hồ sơ thành công.";
                } else {
                    $errors[] = "Lỗi khi cập nhật hồ sơ: " . $stmt->error;
                }
                $stmt->close();
            }
        }
    }

    // -------- Thêm địa chỉ nhận hàng --------
    if ($formType === 'address_add') {
        $ho_ten_nhan = trim($_POST['ho_ten_nhan'] ?? '');
        $phone_nhan  = trim($_POST['phone_nhan'] ?? '');
        $dia_chi     = trim($_POST['dia_chi'] ?? '');
        $is_default  = isset($_POST['is_default']) ? 1 : 0;

        if ($ho_ten_nhan === '' || $dia_chi === '') {
            $errors[] = 'Vui lòng nhập đầy đủ tên người nhận và địa chỉ.';
        }

        if (empty($errors)) {
            if ($is_default) {
                // Bỏ mặc định các địa chỉ khác
                $conn->query("UPDATE diachi_nhanhang SET mac_dinh = 0 WHERE user_id = {$user_id}");
            }

            $stmt = $conn->prepare("
                INSERT INTO diachi_nhanhang (user_id, ho_ten_nhan, phone_nhan, dia_chi, mac_dinh, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, NOW(), NOW())
            ");
            if (!$stmt) {
                $errors[] = "Lỗi prepare (address_add): " . $conn->error;
            } else {
                $stmt->bind_param('isssi', $user_id, $ho_ten_nhan, $phone_nhan, $dia_chi, $is_default);
                if ($stmt->execute()) {
                    $success = "Thêm địa chỉ nhận hàng thành công.";
                } else {
                    $errors[] = "Không thể thêm địa chỉ: " . $stmt->error;
                }
                $stmt->close();
            }
        }
    }
}

// ================== LẤY HỒ SƠ TỪ BẢNG USERS ==================
$stmt = $conn->prepare("SELECT ho_ten, gioi_tinh, ngay_sinh, phone, email, thong_tin FROM users WHERE id = ?");
if (!$stmt) {
    die("Lỗi prepare lấy hồ sơ: " . $conn->error);
}
$stmt->bind_param('i', $user_id);
$stmt->execute();
$resultProfile = $stmt->get_result();
$profile = $resultProfile->fetch_assoc();
$stmt->close();

$ho_ten    = $profile['ho_ten']    ?? '';
$gioi_tinh = $profile['gioi_tinh'] ?? 'Khác';
$ngay_sinh = $profile['ngay_sinh'] ?? '';
$phone     = $profile['phone']     ?? '';
$email     = $profile['email']     ?? '';
$thong_tin = $profile['thong_tin'] ?? '';

// ================== LẤY DANH SÁCH ĐỊA CHỈ ==================
$addresses = [];
$stmt = $conn->prepare("
    SELECT id, ho_ten_nhan, phone_nhan, dia_chi, mac_dinh
    FROM diachi_nhanhang
    WHERE user_id = ?
    ORDER BY mac_dinh DESC, id DESC
");
if ($stmt) {
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $resAddr = $stmt->get_result();
    while ($row = $resAddr->fetch_assoc()) {
        $addresses[] = $row;
    }
    $stmt->close();
} else {
    $errors[] = "Lỗi prepare lấy danh sách địa chỉ: " . $conn->error;
}

// ================== LẤY DANH MỤC CHO MENU (GIỐNG TRANG CHỦ) ==================
$categories = [];
$sqlCat = "SELECT * FROM danhmuc ORDER BY id ASC";
$resCat = $conn->query($sqlCat);
if ($resCat) {
    while ($row = $resCat->fetch_assoc()) {
        $categories[] = $row;
    }
}
?>
<!doctype html>
<html lang="vi">
<head>
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width,initial-scale=1" />
<title>Hồ sơ tài khoản - Danisa</title>

<link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="trangchu.css">

<style>
  body { margin: 0; font-family: 'Cormorant Garamond', serif; }
  .profile-section {
    background: url('image/anhnen3.jpg') center/cover no-repeat;
    min-height: 650px; display: flex; justify-content: center; align-items: flex-start;
    padding: 40px 20px 80px;
  }
  .profile-container {
    width: 100%; max-width: 1000px; background: rgba(255,255,255,0.97);
    border-radius: 14px; padding: 24px 26px 28px; box-shadow: 0 10px 30px rgba(0,0,0,0.25);
    display: grid; grid-template-columns: 3fr 2fr; gap: 20px;
  }
  .profile-left h1 { margin: 0 0 12px; font-size: 24px; text-transform: uppercase; color: #111827; }
  .profile-sub { font-size: 13px; color: #6b7280; margin-bottom: 14px; }
  .message-block { margin-bottom: 12px; font-size: 13px; }
  .message-block ul { margin: 0; padding-left: 16px; color: #b91c1c; }
  .message-success { color: #15803d; font-weight: 600; }
  form.profile-form label { display: block; font-weight: 600; font-size: 13px; margin-top: 8px; margin-bottom: 4px; color: #111827; }
  form.profile-form input, form.profile-form textarea, form.profile-form select {
    width: 100%; padding: 8px 10px; border-radius: 8px; border: 1px solid #d1d5db; font-family: inherit; font-size: 14px; box-sizing: border-box;
  }
  form.profile-form textarea { resize: vertical; min-height: 70px; }
  .profile-form button { margin-top: 14px; padding: 9px 18px; border-radius: 8px; border: none; background: #111827; color: #fff; font-weight: 600; cursor: pointer; font-size: 14px; }
  .profile-form button:hover { background: #e65c00; }
  .profile-right h2 { margin: 0 0 10px; font-size: 20px; color: #111827; }
  .address-list { max-height: 260px; overflow-y: auto; margin-bottom: 16px; }
  .address-card { border-radius: 10px; border: 1px solid #e5e7eb; padding: 10px 12px; font-size: 13px; margin-bottom: 8px; background: #f9fafb; }
  .address-name { font-weight: 600; margin-bottom: 2px; }
  .address-phone { color: #374151; }
  .address-text { margin-top: 3px; color: #4b5563; }
  .address-default-badge { display: inline-block; margin-top: 6px; padding: 3px 8px; border-radius: 999px; background: #dcfce7; color: #166534; font-size: 11px; font-weight: 600; }
  .set-default-link { display: inline-block; margin-top: 6px; font-size: 12px; color: #1e40af; cursor: pointer; text-decoration: none; }
  .set-default-link:hover { text-decoration: underline; }
  .address-form-block h3 { margin: 0 0 8px; font-size: 16px; color: #111827; }
  .address-form-block form label { display: block; font-weight: 600; font-size: 13px; margin-top: 6px; margin-bottom: 4px; }
  .address-form-block form input, .address-form-block form textarea {
    width: 100%; padding: 7px 9px; border-radius: 8px; border: 1px solid #d1d5db; font-size: 13px; box-sizing: border-box;
  }
  .address-form-block form textarea { resize: vertical; min-height: 60px; }
  .address-form-block .address-default-check { display: flex; align-items: center; margin-top: 6px; font-size: 13px; }
  .address-form-block .address-default-check input { margin-right: 6px; }
  .address-form-block button { margin-top: 10px; padding: 8px 16px; border-radius: 8px; border: none; background: #111827; color: #fff; font-weight: 600; cursor: pointer; font-size: 13px; }
  .address-form-block button:hover { background: #e65c00; }
  @media (max-width: 768px) { .profile-container { grid-template-columns: 1fr; } }
  .dropdown-content.show { display: block; opacity: 1; visibility: visible; transform: translateY(0); }
</style>
</head>
<body>
<?php include 'header_front.php'; ?>

<section class="profile-section">
  <div class="profile-container">
    <!-- LEFT: HỒ SƠ CÁ NHÂN -->
    <div class="profile-left">
      <h1>Hồ sơ cá nhân</h1>
      <div class="profile-sub">Cập nhật thông tin tài khoản và thông tin liên hệ của bạn.</div>

      <div class="message-block">
        <?php if (!empty($errors)): ?>
          <ul>
            <?php foreach ($errors as $e): ?>
              <li><?= htmlspecialchars($e) ?></li>
            <?php endforeach; ?>
          </ul>
        <?php elseif ($success): ?>
          <div class="message-success"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>
      </div>

      <form method="post" class="profile-form">
        <input type="hidden" name="form_type" value="profile">

        <label>Họ tên *</label>
        <input type="text" name="ho_ten" value="<?= htmlspecialchars($ho_ten) ?>" required>

        <label>Giới tính</label>
        <select name="gioi_tinh">
          <option value="Nam"   <?= $gioi_tinh === 'Nam' ? 'selected' : '' ?>>Nam</option>
          <option value="Nữ"    <?= $gioi_tinh === 'Nữ' ? 'selected' : '' ?>>Nữ</option>
          <option value="Khác"  <?= $gioi_tinh === 'Khác' ? 'selected' : '' ?>>Khác</option>
        </select>

        <label>Ngày sinh</label>
        <input type="date" name="ngay_sinh" value="<?= htmlspecialchars($ngay_sinh) ?>">

        <label>Điện thoại</label>
        <input type="text" name="phone" value="<?= htmlspecialchars($phone) ?>">

        <label>Email *</label>
        <input type="email" name="email" value="<?= htmlspecialchars($email) ?>" required>

        <label>Thông tin cá nhân</label>
        <textarea name="thong_tin" placeholder="Giới thiệu ngắn về bản thân, ghi chú thêm..."><?= htmlspecialchars($thong_tin) ?></textarea>

        <button type="submit">Lưu hồ sơ</button>
      </form>
    </div>

    <!-- RIGHT: ĐỊA CHỈ NHẬN HÀNG -->
    <div class="profile-right">
      <h2>Địa chỉ nhận hàng</h2>

      <div class="address-list">
        <?php if (empty($addresses)): ?>
          <div style="font-size:13px; color:#4b5563;">Bạn chưa có địa chỉ nhận hàng nào. Hãy thêm một địa chỉ bên dưới.</div>
        <?php else: ?>
          <?php foreach ($addresses as $addr): ?>
            <div class="address-card">
              <div class="address-name"><?= htmlspecialchars($addr['ho_ten_nhan']) ?></div>
              <div class="address-phone">SĐT: <?= htmlspecialchars($addr['phone_nhan']) ?></div>
              <div class="address-text"><?= nl2br(htmlspecialchars($addr['dia_chi'])) ?></div>

              <?php if ($addr['mac_dinh']): ?>
                <div class="address-default-badge">Địa chỉ mặc định</div>
              <?php else: ?>
                <a class="set-default-link" href="hoso.php?set_default=<?= (int)$addr['id'] ?>">Đặt làm mặc định</a>
              <?php endif; ?>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>

      <div class="address-form-block">
        <h3>Thêm địa chỉ mới</h3>
        <form method="post">
          <input type="hidden" name="form_type" value="address_add">

          <label>Tên người nhận *</label>
          <input type="text" name="ho_ten_nhan" placeholder="Họ tên người nhận" required>

          <label>Số điện thoại</label>
          <input type="text" name="phone_nhan" placeholder="SĐT người nhận">

          <label>Địa chỉ chi tiết *</label>
          <textarea name="dia_chi" placeholder="Số nhà, đường, phường/xã, quận/huyện, tỉnh/thành" required></textarea>

          <div class="address-default-check">
            <input type="checkbox" id="is_default" name="is_default">
            <label for="is_default" style="margin:0;">Đặt làm địa chỉ mặc định</label>
          </div>

          <button type="submit">Thêm địa chỉ</button>
        </form>
      </div>
    </div>
  </div>
</section>

<script>
document.addEventListener("DOMContentLoaded", function() {
  // ===== Dropdown hover =====
  const dropdowns = document.querySelectorAll(".dropdown");
  dropdowns.forEach(function (drop) {
    const menu = drop.querySelector(".dropdown-content");
    if (!menu) return;
    drop.addEventListener("mouseenter", function () {
      document.querySelectorAll(".dropdown-content.show").forEach(function(dc) {
        if (dc !== menu) dc.classList.remove("show");
      });
      menu.classList.add("show");
    });
    drop.addEventListener("mouseleave", function () {
      menu.classList.remove("show");
    });
  });
});
</script>

<footer style="
  background:#111827; color:white; text-align:center; padding: 20px 0;
  font-size:12px; line-height:1.8; font-weight:500;
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
