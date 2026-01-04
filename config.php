<?php
$servername = "localhost";
$usernameDB = "root";
$passwordDB = "";      // Nếu bạn chưa đặt mật khẩu MySQL thì để rỗng
$dbname     = "dangky_db";  // TÊN DATABASE MỚI

$conn = new mysqli($servername, $usernameDB, $passwordDB, $dbname);
if ($conn->connect_error) {
    die("Lỗi kết nối MySQL: " . $conn->connect_error);
}
$conn->set_charset("utf8mb4");

/**
 * Chuẩn hóa đường dẫn ảnh để hiển thị đúng trên mọi máy
 * Loại bỏ đường dẫn tuyệt đối, localhost, và đảm bảo đường dẫn tương đối
 * 
 * @param string $imagePath Đường dẫn ảnh từ database
 * @return string Đường dẫn ảnh đã được chuẩn hóa
 */
function normalizeImagePath($imagePath) {
    if (empty($imagePath) || $imagePath === '0' || $imagePath === 'null') {
        return '';
    }
    
    // Loại bỏ khoảng trắng đầu cuối
    $imagePath = trim($imagePath);
    
    // Nếu đã là đường dẫn tương đối (bắt đầu với image/ hoặc uploads/), trả về luôn
    if (preg_match('/^(image\/|uploads\/)/', $imagePath)) {
        return $imagePath;
    }
    
    // Loại bỏ đường dẫn tuyệt đối (http://, https://, C:\, D:\, etc.)
    // Loại bỏ localhost URLs
    $imagePath = preg_replace('/^(https?:\/\/[^\/]+|file:\/\/\/|C:|D:|E:|F:)/i', '', $imagePath);
    
    // Loại bỏ các ký tự backslash và normalize thành forward slash
    $imagePath = str_replace('\\', '/', $imagePath);
    
    // Loại bỏ các slash thừa ở đầu
    $imagePath = ltrim($imagePath, '/\\');
    
    // Nếu đường dẫn chứa image/ hoặc uploads/, giữ lại phần từ đó trở đi
    if (preg_match('/(image\/.+|uploads\/.+)$/i', $imagePath, $matches)) {
        return $matches[1];
    }
    
    // Nếu đường dẫn chỉ là tên file, thử tìm trong image/ trước, sau đó uploads/
    if (preg_match('/^[^\/]+\.(jpg|jpeg|png|gif|webp)$/i', $imagePath)) {
        // Kiểm tra file có tồn tại trong image/ không
        if (file_exists('image/' . $imagePath)) {
            return 'image/' . $imagePath;
        }
        // Kiểm tra file có tồn tại trong uploads/ không
        if (file_exists('uploads/' . $imagePath)) {
            return 'uploads/' . $imagePath;
        }
        // Nếu không tìm thấy, trả về image/ mặc định
        return 'image/' . $imagePath;
    }
    
    // Trả về đường dẫn đã được làm sạch
    return $imagePath;
}
?>
