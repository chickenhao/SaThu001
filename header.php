<?php
// $pageTitle và $activePage được set trước khi include file này
if (!isset($pageTitle))  $pageTitle  = 'Quản Lý - Danisa';
if (!isset($activePage)) $activePage = 'dashboard';
?>
<!DOCTYPE html>
<html lang="vi">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($pageTitle) ?></title>
  <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="style.css">
</head>
<body>
  <div class="dashboard">
    <aside class="sidebar">
      <div class="logo">
        <img src="image/anh1.png" alt="Logo">
      </div>
      <ul class="menu">
        <li class="menu-item <?= $activePage == 'dashboard' ? 'active' : '' ?>">
          <a href="dashboard.php">Dashboard</a>
        </li>
        <li class="menu-item <?= $activePage == 'sanpham' ? 'active' : '' ?>">
          <a href="sanpham_list.php">Quản lý sản phẩm</a>
        </li>
        <li class="menu-item <?= $activePage == 'donhang' ? 'active' : '' ?>">
          <a href="donhang_list.php">Quản lý đơn hàng</a>
        </li>
        <li class="menu-item <?= $activePage == 'baocao' ? 'active' : '' ?>">
          <a href="baocao.php">Báo cáo</a>
        </li>
      </ul>
    </aside>

    <main class="main-content">
      <header>
        <div style="display:flex; align-items:center; gap:10px;">
          <button type="button" class="btn btn-small" onclick="window.history.back();">
            ← Quay lại
          </button>
          <h1><?= htmlspecialchars($pageTitle) ?></h1>
        </div>
        <div class="user-info">Admin</div>
      </header>

      <section class="content-area">
