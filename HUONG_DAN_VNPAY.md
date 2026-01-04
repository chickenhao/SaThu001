# Hướng dẫn tích hợp VNPay

## Bước 1: Đăng ký tài khoản VNPay

1. Truy cập https://sandbox.vnpayment.vn/ để đăng ký tài khoản test (sandbox)
2. Hoặc https://www.vnpayment.vn/ để đăng ký tài khoản production
3. Sau khi đăng ký, bạn sẽ nhận được:
   - **TMN Code**: Mã website của bạn
   - **Secret Key**: Khóa bảo mật để tạo chữ ký

## Bước 2: Cấu hình VNPay

Mở file `vnp_config.php` và cập nhật các thông tin sau:

```php
define('VNP_TMN_CODE', 'YOUR_TMN_CODE'); // Thay bằng TMN Code của bạn
define('VNP_HASH_SECRET', 'YOUR_SECRET_KEY'); // Thay bằng Secret Key của bạn
```

**Lưu ý quan trọng:**
- Đối với môi trường test (sandbox), sử dụng URL: `https://sandbox.vnpayment.vn/paymentv2/vpcpay.html`
- Đối với môi trường production, sử dụng URL: `https://www.vnpayment.vn/paymentv2/vpcpay.html`
- File `vnp_config.php` đã được cấu hình mặc định cho sandbox

## Bước 3: Cấu hình URL Callback

Mở file `vnp_config.php` và cập nhật URL callback:

```php
define('VNP_RETURN_URL', 'http://your-domain.com/vnp_return.php');
```

**Lưu ý:**
- URL này phải là URL công khai (không phải localhost) khi deploy lên server thực
- VNPay sẽ redirect về URL này sau khi thanh toán
- Đảm bảo URL này có thể truy cập được từ internet

## Bước 4: Cập nhật Database

Chạy file SQL `add_vnpay_columns.sql` trong phpMyAdmin hoặc MySQL để thêm các cột lưu thông tin giao dịch VNPay:

```sql
ALTER TABLE `donhang` 
ADD COLUMN `vnp_transaction_no` VARCHAR(50) NULL DEFAULT NULL COMMENT 'Mã giao dịch VNPay' AFTER `phuong_thuc_thanh_toan`,
ADD COLUMN `vnp_txn_ref` VARCHAR(100) NULL DEFAULT NULL COMMENT 'Mã tham chiếu giao dịch VNPay' AFTER `vnp_transaction_no`;
```

Hoặc import file `add_vnpay_columns.sql` trực tiếp.

## Bước 5: Kiểm tra

1. Thêm sản phẩm vào giỏ hàng
2. Vào trang thanh toán
3. Chọn phương thức thanh toán: "Thanh toán online qua VNPay"
4. Điền thông tin và nhấn "Xác nhận đặt hàng"
5. Hệ thống sẽ redirect đến trang thanh toán VNPay
6. Sử dụng thẻ test để thanh toán (xem thông tin thẻ test ở bước 6)

## Bước 6: Thẻ test (Sandbox)

Khi test trên môi trường sandbox, bạn có thể sử dụng các thẻ test sau:

**Thẻ thành công:**
- Số thẻ: `9704198526191432198`
- Tên chủ thẻ: `NGUYEN VAN A`
- Ngày hết hạn: Bất kỳ ngày trong tương lai (ví dụ: `03/07`)
- CVV: `123`

**Thẻ thất bại:**
- Số thẻ: `9704198526191432199`
- Các thông tin khác tương tự

## Cấu trúc file

- `vnp_config.php`: File cấu hình VNPay (TMN Code, Secret Key, URL)
- `vnp_create_payment.php`: File tạo URL thanh toán và redirect đến VNPay
- `vnp_return.php`: File xử lý callback từ VNPay sau khi thanh toán
- `thanhtoan.php`: File thanh toán (đã được cập nhật để hỗ trợ VNPay)
- `add_vnpay_columns.sql`: File SQL để thêm cột vào database

## Luồng thanh toán

1. Người dùng chọn "Thanh toán online qua VNPay" và nhấn "Xác nhận đặt hàng"
2. Hệ thống lưu đơn hàng với trạng thái "pending"
3. Hệ thống tạo URL thanh toán VNPay và redirect người dùng
4. Người dùng thanh toán trên trang VNPay
5. VNPay redirect về `vnp_return.php` với kết quả thanh toán
6. Hệ thống verify chữ ký và cập nhật trạng thái đơn hàng:
   - Thành công: Cập nhật trạng thái thành "paid"
   - Thất bại: Giữ nguyên trạng thái "pending"

## Xử lý lỗi

Nếu gặp lỗi, kiểm tra:

1. **TMN Code và Secret Key** đã đúng chưa
2. **URL callback** có thể truy cập được từ internet chưa
3. **Database** đã có các cột `vnp_transaction_no` và `vnp_txn_ref` chưa
4. **Chữ ký (hash)** có được tạo đúng không (kiểm tra trong `vnp_config.php`)
5. **Log lỗi** trong file error log của PHP

## Chuyển từ Sandbox sang Production

Khi sẵn sàng chuyển sang production:

1. Đăng ký tài khoản production tại https://www.vnpayment.vn/
2. Cập nhật `VNP_URL` trong `vnp_config.php`:
   ```php
   define('VNP_URL', 'https://www.vnpayment.vn/paymentv2/vpcpay.html');
   ```
3. Cập nhật `VNP_TMN_CODE` và `VNP_HASH_SECRET` với thông tin production
4. Cập nhật `VNP_RETURN_URL` với URL thực tế của bạn
5. Test lại toàn bộ luồng thanh toán

## Hỗ trợ

Nếu cần hỗ trợ, liên hệ:
- VNPay Support: https://sandbox.vnpayment.vn/apis/docs/
- Tài liệu API: https://sandbox.vnpayment.vn/apis/

