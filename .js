// js/form.js

document.addEventListener('DOMContentLoaded', function () {

  // ========== PHẦN ĐĂNG KÝ (OTP + PASSWORD) ==========
  let generatedOTP = "";

  const sendOtpBtn         = document.getElementById('sendOtp');
  const phoneInput         = document.getElementById('phone');
  const registerForm       = document.getElementById('registerForm');
  const regPasswordInput   = document.getElementById('password');
  const regConfirmPassword = document.getElementById('confirmPassword');
  const otpInputField      = document.getElementById('otpInput');

  if (sendOtpBtn && phoneInput) {
    sendOtpBtn.addEventListener('click', function () {
      const phone = phoneInput.value.trim();
      const phoneRegex = /^[0-9]{9,11}$/;

      if (!phoneRegex.test(phone)) {
        alert("Vui lòng nhập số điện thoại hợp lệ!");
        return;
      }

      generatedOTP = Math.floor(100000 + Math.random() * 900000).toString();
      alert("Mã OTP của bạn là: " + generatedOTP);
    });
  }

  if (registerForm && regPasswordInput && regConfirmPassword && otpInputField) {
    registerForm.addEventListener('submit', function (e) {
      const password        = regPasswordInput.value;
      const confirmPassword = regConfirmPassword.value;
      const otpInput        = otpInputField.value.trim();

      if (password !== confirmPassword) {
        alert("Mật khẩu không trùng khớp!");
        e.preventDefault();
        return;
      }

      if (!generatedOTP || otpInput !== generatedOTP) {
        alert("Mã OTP không đúng hoặc chưa gửi!");
        e.preventDefault();
      }
    });
  }

  // ========== PHẦN ĐĂNG NHẬP ==========
  const loginForm      = document.getElementById('loginForm');
  const loginUserInput = document.getElementById('username');
  const loginPassInput = document.getElementById('password');

  if (loginForm && loginUserInput && loginPassInput) {
    loginForm.addEventListener('submit', function (e) {
      const username = loginUserInput.value.trim();
      const password = loginPassInput.value;

      if (!username || !password) {
        e.preventDefault();
        alert('Vui lòng nhập đầy đủ tên đăng nhập và mật khẩu!');
      }
    });
  }
});
