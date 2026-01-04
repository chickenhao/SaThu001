<?php
session_start();
echo "Xin chào ADMIN: " . ($_SESSION['fullname'] ?? '');
?>
