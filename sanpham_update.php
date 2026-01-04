<?php
require 'config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: quanlyadmin.php?page=sanpham');
    exit;
}

$id           = $_POST['id']           ?? '';
$name         = $_POST['name']         ?? '';
$category     = $_POST['category']     ?? '';
$import_price = $_POST['import_price'] ?? 0;  // giá nhập
$sale_price   = $_POST['sale_price']   ?? 0;  // giá bán
$price        = $_POST['price']        ?? 0;  // giá khuyến mãi
$quantity     = $_POST['quantity']     ?? 0;
$description  = $_POST['description']  ?? '';
$old_image    = $_POST['old_image']    ?? null;

if ($id === '') {
    die('ID không hợp lệ');
}

// Ép kiểu và CHẶN ÂM
$import_price = max(0, (float)$import_price);
$sale_price   = max(0, (float)$sale_price);
$price        = max(0, (float)$price);
$quantity     = max(0, (int)$quantity);

// Xác định status theo tồn kho
$status = $quantity > 0 ? 'còn hàng' : 'hết hàng';

/* Xử lý ảnh: nếu upload ảnh mới thì dùng ảnh mới, nếu không thì giữ ảnh cũ */
$imagePath = $old_image;

// Tạo thư mục uploads nếu chưa có
$uploadDir = 'uploads/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

if (isset($_FILES['image']) && $_FILES['image']['error'] !== UPLOAD_ERR_NO_FILE) {
    if ($_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $fileName = $_FILES['image']['name'];
        $fileTmp  = $_FILES['image']['tmp_name'];
        $fileSize = $_FILES['image']['size'];

        $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        $allowExt = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

        if (!in_array($ext, $allowExt)) {
            echo "<script>alert('❌ Định dạng ảnh không hợp lệ (chỉ jpg, jpeg, png, gif, webp)!'); history.back();</script>";
            exit;
        }

        if ($fileSize > 5 * 1024 * 1024) {
            echo "<script>alert('❌ Ảnh quá lớn, tối đa 5MB!'); history.back();</script>";
            exit;
        }

        $newName  = time() . '_' . uniqid() . '.' . $ext;
        $target   = $uploadDir . $newName;

        if (move_uploaded_file($fileTmp, $target)) {
            // Xóa ảnh cũ nếu tồn tại
            if (!empty($old_image) && file_exists($old_image)) {
                @unlink($old_image);
            }
            $imagePath = $target;
        } else {
            echo "<script>alert('❌ Không thể lưu file ảnh lên server!'); history.back();</script>";
            exit;
        }
    } else {
        echo "<script>alert('❌ Lỗi upload ảnh!'); history.back();</script>";
        exit;
    }
}

/* Cập nhật DB
   CẦN CÓ CỘT:
   import_price, sale_price, price, quantity, image, description, status trong bảng sanpham
*/
$sql = "
    UPDATE sanpham
    SET name = ?, 
        category = ?, 
        import_price = ?, 
        sale_price = ?, 
        price = ?, 
        quantity = ?, 
        image = ?, 
        description = ?,
        status = ?
    WHERE id = ?
";

$stmt = $conn->prepare($sql);
if (!$stmt) {
  die('Lỗi prepare: ' . $conn->error);
}

// name (s), category (s),
// import_price (d), sale_price (d), price (d),
// quantity (i), image (s), description (s), status (s), id (s)
$stmt->bind_param(
    "ssdddissss",
    $name,
    $category,
    $import_price,
    $sale_price,
    $price,
    $quantity,
    $imagePath,
    $description,
    $status,
    $id
);

if (!$stmt->execute()) {
    die('Lỗi khi cập nhật sản phẩm: ' . $stmt->error);
}

$stmt->close();

// Cho phép truyền trang muốn quay lại (ví dụ: quanlyadmin.php?page=sanpham)
$redirect = $_POST['redirect'] ?? 'quanlyadmin.php?page=sanpham';
header('Location: ' . $redirect);
exit;
