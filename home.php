<?php
session_start();

if (empty($_SESSION['user_id']) || !in_array($_SESSION['user_role'] ?? '', ['admin', 'customer'], true)) {
    header('Location: index.php');
    exit;
}

header('Location: ' . ($_SESSION['user_role'] === 'admin' ? 'admin.php' : 'customer.php'));
exit;
?>
