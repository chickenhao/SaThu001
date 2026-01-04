<?php
session_start();
require 'config.php';

echo "<h2>Debug Session và User ID</h2>";

echo "<h3>Session Data:</h3>";
echo "<pre>";
print_r($_SESSION);
echo "</pre>";

echo "<h3>User ID từ Session:</h3>";
if (isset($_SESSION['user_id'])) {
    echo "user_id = " . $_SESSION['user_id'] . " (type: " . gettype($_SESSION['user_id']) . ")<br>";
} else {
    echo "user_id KHÔNG TỒN TẠI trong session<br>";
}

echo "<h3>Kiểm tra đơn hàng trong database:</h3>";
$checkSql = "SELECT id, user_id, ho_ten, tong_tien, created_at FROM donhang ORDER BY id DESC LIMIT 10";
$result = $conn->query($checkSql);

if ($result && $result->num_rows > 0) {
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>ID</th><th>User ID</th><th>Họ tên</th><th>Tổng tiền</th><th>Ngày tạo</th></tr>";
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . $row['id'] . "</td>";
        echo "<td>" . ($row['user_id'] ?? 'NULL') . "</td>";
        echo "<td>" . htmlspecialchars($row['ho_ten']) . "</td>";
        echo "<td>" . number_format($row['tong_tien'], 0, ',', '.') . "₫</td>";
        echo "<td>" . $row['created_at'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "Không có đơn hàng nào trong database.";
}

if (isset($_SESSION['user_id'])) {
    $user_id = (int)$_SESSION['user_id'];
    echo "<h3>Đơn hàng của user_id = $user_id:</h3>";
    $userOrdersSql = "SELECT id, user_id, ho_ten, tong_tien, created_at FROM donhang WHERE user_id = ? ORDER BY id DESC";
    $stmt = $conn->prepare($userOrdersSql);
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $userResult = $stmt->get_result();
    
    if ($userResult && $userResult->num_rows > 0) {
        echo "<table border='1' cellpadding='5'>";
        echo "<tr><th>ID</th><th>User ID</th><th>Họ tên</th><th>Tổng tiền</th><th>Ngày tạo</th></tr>";
        while ($row = $userResult->fetch_assoc()) {
            echo "<tr>";
            echo "<td>" . $row['id'] . "</td>";
            echo "<td>" . $row['user_id'] . "</td>";
            echo "<td>" . htmlspecialchars($row['ho_ten']) . "</td>";
            echo "<td>" . number_format($row['tong_tien'], 0, ',', '.') . "₫</td>";
            echo "<td>" . $row['created_at'] . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "Không tìm thấy đơn hàng nào của user_id = $user_id";
    }
    $stmt->close();
}

$conn->close();
?>

