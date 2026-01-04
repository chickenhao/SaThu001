<?php
session_start();

// Kết nối DB để (nếu cần) dùng danh mục giống trang chủ
require 'config.php';

// Khởi tạo giỏ hàng nếu chưa có
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

// Hàm thêm / cộng dồn sản phẩm vào giỏ
function addToCart($id, $name, $price, $image, $qty = 1)
{
    if (!isset($_SESSION['cart'][$id])) {
        $_SESSION['cart'][$id] = [
            'id'       => $id,
            'name'     => $name,
            'price'    => (int)$price,
            'image'    => $image,
            'quantity' => 0
        ];
    }
    $_SESSION['cart'][$id]['quantity'] += $qty;
}

// Xử lý các action từ GET (add, buy_now, clear, remove)
if (isset($_GET['action']) && isset($_GET['id'])) {
    $id    = $_GET['id'];
    $name  = $_GET['name']  ?? '';
    $price = isset($_GET['price']) ? (int)$_GET['price'] : 0;
    $image = $_GET['image'] ?? '';

    switch ($_GET['action']) {
        case 'add':
            addToCart($id, $name, $price, $image, 1);
            header('Location: giohang.php');
            exit;

        case 'buy_now':
            addToCart($id, $name, $price, $image, 1);
            header('Location: thanhtoan.php');
            exit;
    }
}

// Xóa toàn bộ giỏ
if (isset($_GET['clear'])) {
    unset($_SESSION['cart']);
    $_SESSION['cart'] = [];
    header('Location: giohang.php');
    exit;
}

// Xóa 1 sản phẩm
if (isset($_GET['remove'])) {
    $id = $_GET['remove'];
    if (isset($_SESSION['cart'][$id])) {
        unset($_SESSION['cart'][$id]);
    }
    header('Location: giohang.php');
    exit;
}

// TỰ ĐỘNG CẬP NHẬT SỐ LƯỢNG KHI POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['qty']) && is_array($_POST['qty'])) {
        foreach ($_POST['qty'] as $id => $qty) {
            $qty = (int)$qty;
            if ($qty <= 0) {
                unset($_SESSION['cart'][$id]);
            } else {
                if (isset($_SESSION['cart'][$id])) {
                    $_SESSION['cart'][$id]['quantity'] = $qty;
                }
            }
        }
    }
    header('Location: giohang.php');
    exit;
}

$cart  = $_SESSION['cart'];
$total = 0;

// (Không bắt buộc) Lấy danh mục nếu muốn dùng dynamic như trang chủ
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
    <title>Giỏ hàng</title>
    <meta name="viewport" content="width=device-width,initial-scale=1" />
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@400;500;600;700&display=swap" rel="stylesheet">
    <!-- CSS header/menu giống trang chủ -->
    <link rel="stylesheet" href="gohang.css">
    <style>
        body {
            margin:0;
            font-family: "Cormorant Garamond", serif;
            /* HÌNH NỀN CHUNG CHO TRANG GIỎ HÀNG */
            background: url('image/anhnen3.jpg') center/cover no-repeat fixed;
        }
        .cart-page-wrapper {
            width: 100%;
            display: flex;
            justify-content: center;
        }
        .cart-container {
            max-width: 1100px;
            width: 100%;
            margin: 40px auto 60px;
            background: rgba(255,255,255,0.95);
            padding:20px 24px;
            border-radius:16px;
            box-shadow:0 10px 40px rgba(0,0,0,0.2);

            /* Làm box GIỎ HÀNG dài hơn + có thanh cuộn mượt */
            min-height: 420px;
            max-height: 75vh;      /* tối đa 75% chiều cao màn hình */
            overflow-y: auto;      /* bật cuộn dọc khi nhiều sản phẩm */

            /* Scrollbar cho Firefox */
            scrollbar-width: thin;
            scrollbar-color: rgba(15,23,42,0.35) transparent;
        }

        /* Scrollbar mờ cho Chrome/Safari/Edge */
        .cart-container::-webkit-scrollbar {
            width: 8px;
        }
        .cart-container::-webkit-scrollbar-track {
            background: transparent;       /* trong suốt, không gây rối */
        }
        .cart-container::-webkit-scrollbar-thumb {
            background: rgba(15,23,42,0.25);  /* màu đen mờ */
            border-radius: 999px;
        }
        .cart-container::-webkit-scrollbar-thumb:hover {
            background: rgba(15,23,42,0.45);  /* đậm hơn chút khi hover */
        }

        .cart-title {
            font-size:26px;
            font-weight:700;
            margin-bottom:15px;
            text-align:center;
            color:#111827;
        }
        table.cart-table {
            width:100%;
            border-collapse:collapse;
            margin-top:10px;
        }
        table.cart-table th,
        table.cart-table td {
            padding:10px 8px;
            border-bottom:1px solid #e5e7eb;
            text-align:center;
        }
        table.cart-table th {
            background:#f9fafb;
            font-weight:600;
        }
        .cart-img {
            width: 30px !important;
            height: 30px !important;
            object-fit: cover;
            border-radius: 4px;
            display: block;
            margin: 0 auto;   /* canh giữa dưới chữ "Hình" */
        }

        .cart-product-name {
            font-size:16px;
            font-weight:600;
        }
        .cart-qty-input {
            width:70px;
            padding:4px;
            text-align:center;
        }
        .price-cell {
            white-space:nowrap;
        }
        .remove-link {
            color:#dc2626;
            text-decoration:none;
            font-size:13px;
        }
        .remove-link:hover {
            text-decoration:underline;
        }
        .cart-empty {
            text-align:center;
            padding:30px 10px;
            font-size:16px;
        }
        .cart-actions {
            margin-top:20px;
            display:flex;
            justify-content:space-between;
            align-items:center;
            flex-wrap:wrap;
            gap:10px;
        }
        .btn-link {
            display:inline-block;
            padding:8px 16px;
            border-radius:999px;
            text-decoration:none;
            font-size:14px;
            border:1px solid transparent;
        }
        .btn-secondary {
            border-color:#9ca3af;
            color:#111827;
            background:#f9fafb;
        }
        .btn-danger {
            border-color:#dc2626;
            color:#b91c1c;
            background:#fee2e2;
        }
        .btn-primary {
            border-color:#2563eb;
            color:#fff;
            background:#2563eb;
        }
        .btn-primary:hover {
            opacity: 0.9;
        }
        .cart-total {
            font-size:16px;
            font-weight:700;
            margin-right:10px;
        }
        @media (max-width:768px){
            table.cart-table th:nth-child(1),
            table.cart-table td:nth-child(1) {
                display:none;
            }
        }
    </style>
</head>
<body>

<?php include 'header_front.php'; ?>

<!-- PHẦN NỘI DUNG GIỎ HÀNG -->
<div class="menu">
  <section style="
    background: transparent;
    min-height: 650px;
    display: flex;
    justify-content: center;
    align-items: flex-start;
    padding: 40px 20px 60px 20px;
  ">
    <div class="cart-page-wrapper">
      <div class="cart-container">
        <h1 class="cart-title">Giỏ hàng</h1>

        <?php if (empty($cart)): ?>
            <p class="cart-empty">Giỏ hàng của bạn đang trống.</p>
        <?php else: ?>
            <form method="post" action="giohang.php" class="cart-form">
                <table class="cart-table">
                    <thead>
                        <tr>
                            <th>Hình</th>
                            <th>Sản phẩm</th>
                            <th>Số lượng</th>
                            <th>Đơn giá</th>
                            <th>Thành tiền</th>
                            <th>Xóa</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($cart as $id => $item):
                            $lineTotal = $item['price'] * $item['quantity'];
                            $total    += $lineTotal;
                        ?>
                        <tr>
                            <td>
                                <?php 
                                  $itemImage = !empty($item['image']) ? normalizeImagePath($item['image']) : '';
                                ?>
                                <?php if ($itemImage): ?>
                                    <img src="<?= htmlspecialchars($itemImage) ?>" alt="" class="cart-img" onerror="this.style.display='none'">
                                <?php endif; ?>
                            </td>
                            <td class="cart-product-name">
                                <?= htmlspecialchars($item['name']) ?>
                            </td>
                            <td>
                                <input
                                    type="number"
                                    name="qty[<?= htmlspecialchars($id) ?>]"
                                    value="<?= (int)$item['quantity'] ?>"
                                    min="0"
                                    class="cart-qty-input"
                                >
                            </td>
                            <td class="price-cell">
                                <?= number_format($item['price'], 0, ',', '.') ?>₫
                            </td>
                            <td class="price-cell">
                                <?= number_format($lineTotal, 0, ',', '.') ?>₫
                            </td>
                            <td>
                                <a href="giohang.php?remove=<?= urlencode($id) ?>" class="remove-link">Xóa</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <div class="cart-actions">
                    <div>
                        <a href="trangchu.php" class="btn-link btn-secondary">← Tiếp tục mua hàng</a>
                        <a href="giohang.php?clear=1" class="btn-link btn-danger">Xóa toàn bộ giỏ</a>
                    </div>
                    <div>
                        <span class="cart-total">Tổng cộng: <?= number_format($total, 0, ',', '.') ?>₫</span>
                        <a href="thanhtoan.php" class="btn-link btn-primary">Thanh toán</a>
                    </div>
                </div>
            </form>
        <?php endif; ?>
      </div>
    </div>
  </section>
</div>

<footer style="
  background:#111827;
  color:white;
  text-align:center;
  padding: 16px 0;
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

<script>
document.addEventListener("DOMContentLoaded", function() {
  const cartForm  = document.querySelector(".cart-form");
  const qtyInputs = document.querySelectorAll(".cart-qty-input");
  if (cartForm && qtyInputs.length > 0) {
    qtyInputs.forEach(function (input) {
      input.addEventListener("change", function() {
        cartForm.submit();
      });
    });
  }
});
</script>

</body>
</html>
