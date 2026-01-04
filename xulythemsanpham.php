<?php
require 'config.php';

if ($_SERVER["REQUEST_METHOD"] != "POST") {
    header('Location: quanlyadmin.php?page=sanpham');
    exit;
}

// Lấy dữ liệu từ form
$id           = $_POST['id']           ?? '';
$name         = $_POST['name']         ?? '';
$category     = $_POST['category']     ?? '';
$import_price = $_POST['import_price'] ?? 0;   // giá nhập
$sale_price   = $_POST['sale_price']   ?? 0;   // giá bán
$price        = $_POST['price']        ?? 0;   // giá khuyến mãi
$quantity     = $_POST['quantity']     ?? 0;
$description  = $_POST['description']  ?? '';

// Ép kiểu và CHẶN ÂM
$import_price = max(0, (float)$import_price);
$sale_price   = max(0, (float)$sale_price);
$price        = max(0, (float)$price);
$quantity     = max(0, (int)$quantity);

// Kiểm tra ID / tên cơ bản
if (trim($id) === '' || trim($name) === '') {
    echo "<script>alert('ID và Tên sản phẩm không được để trống!'); history.back();</script>";
    exit;
}

// Tạo thư mục uploads nếu chưa có
if (!is_dir("uploads")) {
    mkdir("uploads", 0777, true);
}

$imagePath = ""; // Đường dẫn lưu trong DB

// ===== XỬ LÝ ẢNH UPLOAD =====
if (isset($_FILES['image']) && $_FILES['image']['error'] !== UPLOAD_ERR_NO_FILE) {

    if ($_FILES['image']['error'] === UPLOAD_ERR_OK) {

        $fileName = $_FILES['image']['name'];
        $fileTmp  = $_FILES['image']['tmp_name'];
        $fileSize = $_FILES['image']['size'];

        // Lấy phần mở rộng file
        $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

        // Các đuôi file cho phép
        $allowExt = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

        if (!in_array($ext, $allowExt)) {
            echo "<script>alert('❌ Định dạng ảnh không hợp lệ (chỉ jpg, jpeg, png, gif, webp)!'); history.back();</script>";
            exit;
        }

        // Giới hạn dung lượng: 5MB
        if ($fileSize > 5 * 1024 * 1024) {
            echo "<script>alert('❌ Ảnh quá lớn, tối đa 5MB!'); history.back();</script>";
            exit;
        }

        // Đặt tên file mới tránh trùng
        $newName  = time() . '_' . uniqid() . '.' . $ext;
        $target   = 'uploads/' . $newName;

        if (move_uploaded_file($fileTmp, $target)) {
            $imagePath = $target;  // Lưu đường dẫn vào DB
        } else {
            echo "<script>alert('❌ Không thể lưu file ảnh lên server!'); history.back();</script>";
            exit;
        }
    } else {
        echo "<script>alert('❌ Lỗi upload ảnh!'); history.back();</script>";
        exit;
    }
} else {
    // Không upload ảnh => để trống hoặc ảnh mặc định
    $imagePath = '';
}

// ===== LƯU VÀO DATABASE =====
// Thêm cột status với giá trị mặc định
$status = $quantity > 0 ? 'còn hàng' : 'hết hàng';

// YÊU CẦU BẢNG sanpham CÓ CÁC CỘT:
// id, name, category, import_price, sale_price, price, quantity, image, description, status
$sql = "INSERT INTO sanpham 
        (id, name, category, import_price, sale_price, price, quantity, image, description, status)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

$stmt = $conn->prepare($sql);

if (!$stmt) {
    die("Lỗi prepare: " . $conn->error);
}

// 3 string, 3 double, 1 int, 3 string  => 'sssdddisss'
$stmt->bind_param(
    "sssdddisss",
    $id,
    $name,
    $category,
    $import_price,
    $sale_price,
    $price,
    $quantity,
    $imagePath,
    $description,
    $status
);

if ($stmt->execute()) {
    // Redirect về trang quản lý sản phẩm trong admin
    header('Location: quanlyadmin.php?page=sanpham');
    exit;
} else {
    echo "❌ Lỗi khi thêm sản phẩm: " . $stmt->error;
}

$stmt->close();
