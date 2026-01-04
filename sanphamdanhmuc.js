document.addEventListener("DOMContentLoaded", function() {
  // ===== Tài khoản =====
  const currentUser = JSON.parse(localStorage.getItem("currentUser"));
  const accountDropdown = document.querySelector(".account-dropdown");
  if (currentUser && accountDropdown) {
    const nameTag = document.createElement("span");
    nameTag.textContent = currentUser.username;
    nameTag.style.color = "white";
    nameTag.style.marginLeft = "8px";
    nameTag.style.fontSize = "17px";
    nameTag.style.fontWeight = "600";
    accountDropdown.insertBefore(nameTag, accountDropdown.querySelector(".account-menu"));
    const menuLinks = accountDropdown.querySelectorAll(".account-menu a");
    if (menuLinks.length >= 2) {
      menuLinks[0].style.display = "none";
      menuLinks[1].style.display = "none";
    }
  }

  const logoutLink = document.querySelector(".account-menu a:last-child");
  if (logoutLink) {
    logoutLink.addEventListener("click", function(e) {
      e.preventDefault();
      localStorage.removeItem("currentUser");
      alert("Đăng xuất thành công!");
      window.location.href = "dangnhap.php";
    });
  }

  // ===== RATING: cho phép click chọn sao =====
  const ratingBox  = document.getElementById("rating-box");
  const ratingInfo = document.getElementById("rating-info");

  if (ratingBox && ratingInfo) {
    const stars = ratingBox.querySelectorAll(".star");
    let currentRating = Number(ratingBox.dataset.rating) || 0;

    const savedRating = Number(localStorage.getItem("mainProductRating"));
    if (savedRating) {
      currentRating = savedRating;
    }

    function renderStars(rating) {
      stars.forEach(star => {
        const value = Number(star.dataset.value);
        if (value <= rating) {
          star.classList.add("active");
        } else {
          star.classList.remove("active");
        }
      });

      if (rating > 0) {
        ratingInfo.textContent = `Bạn đánh giá ${rating}/5 sao`;
      } else {
        ratingInfo.textContent = "Nhấn để đánh giá";
      }
    }

    renderStars(currentRating);

    stars.forEach(star => {
      star.addEventListener("click", () => {
        const value = Number(star.dataset.value);
        currentRating = value;
        localStorage.setItem("mainProductRating", String(currentRating));
        renderStars(currentRating);
      });
    });
  }

  // ===== Dropdown =====
  const menuButton = document.querySelector(".dropdown > a");
  const menuContent = document.querySelector(".dropdown-content");
  if (menuButton && menuContent) {
    menuButton.addEventListener("click", function(e) {
      if (menuButton.getAttribute("href") === "#") {
        e.preventDefault();
        menuContent.classList.toggle("show");
      }
    });

    document.addEventListener("click", function(e) {
      if (!menuButton.contains(e.target) && !menuContent.contains(e.target)) {
        menuContent.classList.remove("show");
      }
    });
  }

  // ===== Carousel scroll =====
  const scrollContainer = document.querySelector(".product-scroll");
  const arrowRight = document.querySelector(".arrow-right");
  const arrowLeft = document.querySelector(".arrow-left");

  if (scrollContainer && arrowRight && arrowLeft) {
    arrowRight.addEventListener("click", function () {
      scrollContainer.scrollBy({
        left: 300,
        behavior: "smooth"
      });
    });

    arrowLeft.addEventListener("click", function () {
      scrollContainer.scrollBy({
        left: -300,
        behavior: "smooth"
      });
    });
  }

  // ===== Tham chiếu sản phẩm chính & danh sách box =====
  const productBoxes = document.querySelectorAll(".product-box");

  const mainImage    = document.getElementById("main-product-image");
  const mainTitle    = document.getElementById("main-product-title");
  const mainDesc     = document.getElementById("main-product-desc");
  const mainOldPrice = document.getElementById("main-old-price");
  const mainNewPrice = document.getElementById("main-new-price");

  // Lưu trạng thái hiện tại của sản phẩm trên khung chính (phục vụ hoán đổi)
  let mainData = {
    img:      mainImage ? mainImage.getAttribute("src") : "",
    title:    mainTitle ? mainTitle.textContent : "",
    desc:     mainDesc ? mainDesc.textContent : "",
    oldPrice: mainOldPrice ? mainOldPrice.textContent : "",
    newPrice: mainNewPrice ? mainNewPrice.textContent : ""
  };

  // ===== Hàm tiện ích xử lý dữ liệu sản phẩm =====
  function parsePriceText(text) {
    if (!text) return "0";
    // Lấy toàn bộ số trong chuỗi, bỏ . , đ / ₫
    const digits = text.replace(/[^\d]/g, "");
    return digits || "0";
  }

  function getProductDataFromBox(box) {
    const titleEl = box.querySelector('h3');
    const imgEl   = box.querySelector('img');
    const priceEl = box.querySelector('.new-price') || box.querySelector('.old-price');

    const id    = box.dataset.id || (titleEl ? titleEl.textContent.trim() : '');
    const name  = box.dataset.name || (titleEl ? titleEl.textContent.trim() : '');
    const image = box.dataset.image || (imgEl ? imgEl.getAttribute('src') : '');
    const rawPrice = box.dataset.price || (priceEl ? priceEl.textContent : '0');
    const price = parsePriceText(rawPrice);

    return { id, name, image, price };
  }

  function getProductDataFromMain() {
    const mainBox = document.getElementById('main-product-box');
    const titleEl = mainTitle;
    const priceEl = document.getElementById('main-new-price') || document.getElementById('main-old-price');

    const id    = (mainBox && mainBox.dataset.id) || (titleEl ? titleEl.textContent.trim() : '');
    const name  = (mainBox && mainBox.dataset.name) || (titleEl ? titleEl.textContent.trim() : '');
    const image = (mainBox && mainBox.dataset.image) || (mainImage ? mainImage.getAttribute('src') : '');
    const rawPrice = (mainBox && mainBox.dataset.price) || (priceEl ? priceEl.textContent : '0');
    const price = parsePriceText(rawPrice);

    return { id, name, image, price };
  }

  // Gửi sản phẩm sang giohang.php
  function addToCart(product, action) {
    const params = new URLSearchParams();
    params.set('action', action);      // 'add' hoặc 'buy_now'
    params.set('id', product.id);
    params.set('name', product.name);
    params.set('price', product.price);
    params.set('image', product.image);

    window.location.href = 'giohang.php?' + params.toString();
  }

  // ===== Hoán đổi sản phẩm: box dưới <-> khung chính (click vào box) =====
  productBoxes.forEach(box => {
    box.addEventListener("click", function() {
      const imgEl      = box.querySelector("img");
      const titleEl    = box.querySelector("h3");
      const oldPriceEl = box.querySelector(".old-price");
      const newPriceEl = box.querySelector(".new-price");

      const boxData = {
        img:      imgEl ? imgEl.getAttribute("src") : "",
        title:    titleEl ? titleEl.textContent : "",
        desc:     box.dataset.desc || "",
        oldPrice: oldPriceEl ? oldPriceEl.textContent : "",
        newPrice: newPriceEl ? newPriceEl.textContent : ""
      };

      // 1. Đẩy sản phẩm đang ở khung chính xuống box
      if (imgEl && mainData.img) {
        imgEl.setAttribute("src", mainData.img);
      }
      if (titleEl && mainData.title) {
        titleEl.textContent = mainData.title;
      }
      if (oldPriceEl && mainData.oldPrice) {
        oldPriceEl.textContent = mainData.oldPrice;
      }
      if (newPriceEl && mainData.newPrice) {
        newPriceEl.textContent = mainData.newPrice;
      }
      if (mainData.desc) {
        box.dataset.desc = mainData.desc;
      }

      // 2. Đưa sản phẩm từ box lên khung chính
      if (boxData.img && mainImage) {
        mainImage.setAttribute("src", boxData.img);
      }
      if (boxData.title && mainTitle) {
        mainTitle.textContent = boxData.title;
      }
      if (mainDesc) {
        mainDesc.textContent = boxData.desc || mainDesc.textContent;
      }
      if (boxData.oldPrice && mainOldPrice) {
        mainOldPrice.textContent = boxData.oldPrice;
      }
      if (boxData.newPrice && mainNewPrice) {
        mainNewPrice.textContent = boxData.newPrice;
      }

      // 3. Cập nhật lại mainData
      mainData = boxData;
    });
  });

  // ===== SỰ KIỆN NÚT "Đặt hàng" VÀ "Thêm vào giỏ" TRONG BOX DƯỚI =====
  productBoxes.forEach(box => {
    const orderBtn = box.querySelector('.order-btn');
    const cartBtn  = box.querySelector('.cart-btn');

    if (orderBtn) {
      orderBtn.addEventListener('click', function(e) {
        e.stopPropagation(); // không kích hoạt event click box
        e.preventDefault();
        const data = getProductDataFromBox(box);
        addToCart(data, 'buy_now');     // chuyển sang giỏ & thanh toán
      });
    }

    if (cartBtn) {
      cartBtn.addEventListener('click', function(e) {
        e.stopPropagation();
        e.preventDefault();
        const data = getProductDataFromBox(box);
        addToCart(data, 'add');         // chỉ thêm vào giỏ
      });
    }
  });

  // ===== NÚT Ở SẢN PHẨM CHÍNH =====
  const mainBuyBtn  = document.querySelector('.buttons .buy-now');
  const mainCartBtn = document.querySelector('.buttons .add-cart');

  if (mainBuyBtn) {
    mainBuyBtn.addEventListener('click', function(e) {
      e.preventDefault();
      const data = getProductDataFromMain();
      addToCart(data, 'buy_now');
    });
  }

  if (mainCartBtn) {
    mainCartBtn.addEventListener('click', function(e) {
      e.preventDefault();
      const data = getProductDataFromMain();
      addToCart(data, 'add');
    });
  }
});
