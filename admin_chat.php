<?php
session_start();
include 'config.php';

// TODO: kiểm tra quyền admin tuỳ hệ thống của bạn
// Ví dụ:
// if (empty($_SESSION['is_admin'])) { die('Không có quyền truy cập'); }

// Lấy danh sách user đang có tin nhắn
$userListSql = "
    SELECT u.user_id, u.ten_nguoi_dung
    FROM (
        SELECT DISTINCT user_id
        FROM chat_messages
    ) c
    LEFT JOIN users u ON u.id = c.user_id
    ORDER BY c.user_id ASC
";
$userList = [];
if ($res = $conn->query($userListSql)) {
    while ($row = $res->fetch_assoc()) {
        $userList[] = $row;
    }
}

// Lấy user đang chọn
$currentUserId = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;

// Lấy tin nhắn của user đang chọn
$messages = [];
if ($currentUserId > 0) {
    $msgSql = "SELECT id, sender_type, message,
                      DATE_FORMAT(created_at, '%H:%i %d/%m') AS time
               FROM chat_messages
               WHERE user_id = ?
               ORDER BY id ASC";
    if ($stmt = $conn->prepare($msgSql)) {
        $stmt->bind_param("i", $currentUserId);
        $stmt->execute();
        $rs = $stmt->get_result();
        while ($row = $rs->fetch_assoc()) {
            $messages[] = $row;
        }
        $stmt->close();
    }
}
?>
<!doctype html>
<html lang="vi">
<head>
<meta charset="utf-8">
<title>Quản lý chat khách hàng</title>
<style>
body {
  font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
  margin: 0;
  padding: 0;
  background: #f3f4f6;
}
.wrapper {
  display: flex;
  height: 100vh;
}
.sidebar {
  width: 250px;
  border-right: 1px solid #e5e7eb;
  background: #111827;
  color: #fff;
  overflow-y: auto;
}
.sidebar h2 {
  margin: 0;
  padding: 12px 14px;
  font-size: 16px;
  border-bottom: 1px solid #374151;
}
.user-item {
  padding: 10px 14px;
  border-bottom: 1px solid #1f2937;
}
.user-item a {
  color: #e5e7eb;
  text-decoration: none;
}
.user-item.active {
  background: #1f2937;
}
.main {
  flex: 1;
  display: flex;
  flex-direction: column;
}
.main-header {
  padding: 10px 14px;
  border-bottom: 1px solid #e5e7eb;
  background: #fff;
}
.chat-area {
  flex: 1;
  padding: 10px;
  overflow-y: auto;
  background: #e5e7eb;
}
.chat-message {
  margin-bottom: 8px;
  max-width: 75%;
  display: flex;
  flex-direction: column;
}
.chat-message.user {
  margin-right: auto;
}
.chat-message.admin {
  margin-left: auto;
}
.chat-bubble {
  padding: 6px 8px;
  border-radius: 8px;
  font-size: 14px;
}
.chat-message.user .chat-bubble {
  background: #fff;
}
.chat-message.admin .chat-bubble {
  background: #3b82f6;
  color: #fff;
}
.chat-time {
  font-size: 11px;
  opacity: 0.7;
  margin-top: 2px;
}
.chat-form {
  padding: 8px;
  border-top: 1px solid #e5e7eb;
  background: #fff;
  display: flex;
  gap: 6px;
}
.chat-form textarea {
  flex: 1;
  min-height: 40px;
  resize: none;
  font-size: 14px;
}
.chat-form button {
  padding: 8px 14px;
  border: none;
  background: #1d4ed8;
  color: #fff;
  border-radius: 6px;
  cursor: pointer;
}
.chat-form button:disabled {
  opacity: 0.6;
}
</style>
</head>
<body>

<div class="wrapper">
  <div class="sidebar">
    <h2>Khách đang chat</h2>
    <?php if (empty($userList)): ?>
      <div class="user-item">Chưa có khách nào.</div>
    <?php else: ?>
      <?php foreach ($userList as $u): ?>
        <?php
          $uid = (int)$u['user_id'];
          $name = $u['ten_nguoi_dung'] ?? ('User #' . $uid);

          // nếu bảng users không có, bạn có thể đổi sang lấy từ bảng khachhang, v.v...
          if (!$name) $name = 'User #' . $uid;
        ?>
        <div class="user-item <?= $uid === $currentUserId ? 'active' : ''; ?>">
          <a href="?user_id=<?= $uid; ?>">
            <?= htmlspecialchars($name); ?> (ID: <?= $uid; ?>)
          </a>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>

  <div class="main">
    <div class="main-header">
      <?php if ($currentUserId > 0): ?>
        Đang chat với khách ID: <?= $currentUserId; ?>
      <?php else: ?>
        Chọn một khách ở bên trái để xem cuộc trò chuyện.
      <?php endif; ?>
    </div>

    <div class="chat-area" id="chat-area">
      <?php if ($currentUserId > 0): ?>
        <?php if (empty($messages)): ?>
          <div>Chưa có tin nhắn nào.</div>
        <?php else: ?>
          <?php foreach ($messages as $m): ?>
            <div class="chat-message <?= $m['sender_type'] === 'admin' ? 'admin' : 'user'; ?>">
              <div class="chat-bubble">
                <?= nl2br(htmlspecialchars($m['message'])); ?>
              </div>
              <div class="chat-time"><?= htmlspecialchars($m['time']); ?></div>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      <?php else: ?>
        <div style="padding:10px;">Chưa chọn khách.</div>
      <?php endif; ?>
    </div>

    <?php if ($currentUserId > 0): ?>
      <form class="chat-form" method="post" action="admin_chat_send.php">
        <input type="hidden" name="user_id" value="<?= $currentUserId; ?>">
        <textarea name="message" placeholder="Nhập nội dung trả lời cho khách..." required></textarea>
        <button type="submit">Gửi</button>
      </form>
    <?php endif; ?>
  </div>
</div>

<script>
// auto scroll xuống cuối
const chatArea = document.getElementById('chat-area');
if (chatArea) {
  chatArea.scrollTop = chatArea.scrollHeight;
}
</script>

</body>
</html>
