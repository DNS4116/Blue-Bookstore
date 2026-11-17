<?php
/**
 * Navbar Khusus Customer / User - Blue BookStore
 * Cukup ubah file ini jika ingin mengubah struktur navigasi customer secara global.
 */
$currentFile = $currentNav ?? basename($_SERVER['PHP_SELF']);
$cartCount = function_exists('getCartCount') ? getCartCount() : 0;
?>
<header class="header-customer">
    <div class="navbar">
        <a href="customer.php" class="brand">
            BLUE BOOKSTORE <span class="brand-badge">USER</span>
        </a>
        <ul class="nav-links">
            <li><a href="customer.php" class="<?= $currentFile === 'customer.php' ? 'active' : '' ?>">Katalog Buku</a></li>
            <li>
                <a href="customer_cart.php" class="<?= in_array($currentFile, ['customer_cart.php', 'customer_checkout.php'], true) ? 'active' : '' ?>">
                    Keranjang
                    <span class="cart-badge"><?= $cartCount ?></span>
                </a>
            </li>
            <li><a href="customer_transaksi.php" class="<?= $currentFile === 'customer_transaksi.php' ? 'active' : '' ?>">Riwayat Pesanan</a></li>
            <li><a href="logout.php" class="logout-btn">Logout</a></li>
        </ul>
    </div>
</header>
