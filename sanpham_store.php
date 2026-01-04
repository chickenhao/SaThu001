<?php
require 'config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name        = $_POST['name'] ?? '';
    $price       = $_POST['price'] ?? 0;
    $quantity    = $_POST['quantity'] ?? 0;
    $status      = $_POST['status'] ?? 'còn hàng';
    $description = $_POST['description'] ?? '';

    $category_id  = $_POST['category_id'] ?? null;
    $new_category = trim($_POST['new_category'] ?? '');

    // Xác định tên danh mục cuối cùng sẽ lưu vào sanpham.category
    $categoryName = '';

    // Nếu có nhập danh mục mới -> thêm vào bảng danhmuc + dùng luôn
    if ($new_category !== '') {
        // kiểm tra xem đã tồn tại chưa
        $stmtCheck = $conn->prepare("SELECT id, name FROM danhmuc WHERE name = ?");
        $stmtCheck->bind_param("s", $new_category);
        $stmtCheck->execute();
        $resCheck = $stmtCheck->get_result();

        if ($rowCat = $resCheck->fetch_assoc()) {
            // đã tồn tại -> dùng lại
            $categoryName = $rowCat['name'];
        } else {
            // chưa có -> insert
            $stmtCat = $conn->prepare("INSERT INTO danhmuc (name) VALUES (?)");
            $stmtCat->bind_param("s", $new_category);
            $stmtCat->execute();
            $categoryName = $new_category;
        }
    } else {
        // Không nhập danh mục mới -> lấy từ select category_id
        if (!empty($category_id)) {
            $stmtCat = $conn->prepare("SELECT name FROM danhmuc WHERE id = ?");
            $stmtCat->bind_param("i", $category_id);
            $stmtCat->execute();
            $resCat = $stmtCat->get_result();
            if ($rowCat = $resCat->fetch_assoc()) {
                $categoryName = $rowCat['name'];
            }
        }
    }

    // Nếu vì lý do gì vẫn chưa có tên danh mục -> gán 'Khác'
    if ($categoryName === '') {
        $categoryName = 'Khác';
    }

    // Xử lý upload ảnh
    $imagePath = null;
    if (!empty($_FILES['image']['name'])) {
        $targetDir = 'image/';
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0777, true);
        }
        $fileName   = time() . '_' . basename($_FILES['image']['name']);
        $targetFile = $targetDir . $fileName;

        if (move_uploaded_file($_FILES['image']['tmp_name'], $targetFile)) {
            $imagePath = $targetFile;
        }
    }

    // Lưu sản phẩm (vẫn dùng cột category dạng text như cũ)
    $stmt = $conn->prepare("
        INSERT INTO sanpham (name, category, price, quantity, image, description, status)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->bind_param(
        "ssdiiss",
        $name,
        $categoryName,
        $price,
        $quantity,
        $imagePath,
        $description,
        $status
    );
    $stmt->execute();

    header('Location: sanpham_list.php');
    exit;
} else {
    header('Location: sanpham_list.php');
    exit;
}
