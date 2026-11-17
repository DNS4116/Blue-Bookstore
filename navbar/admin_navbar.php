<?php
/**
 * Navbar Khusus Admin - Blue BookStore
 * Cukup ubah file ini jika ingin mengubah struktur navigasi admin secara global.
 */
$currentFile = $currentNav ?? basename($_SERVER['PHP_SELF']);
?>
<header class="header-admin">
    <div class="navbar">
        <a href="admin.php" class="brand">
            BLUE BOOKSTORE <span>ADMIN</span>
        </a>
        <ul class="nav-links">
            <li><a href="admin.php" class="<?= $currentFile === 'admin.php' ? 'active' : '' ?>">Dashboard</a></li>
            <li><a href="admin_buku.php" class="<?= $currentFile === 'admin_buku.php' ? 'active' : '' ?>">Kelola Buku</a></li>
            <li><a href="admin_penulis.php" class="<?= $currentFile === 'admin_penulis.php' ? 'active' : '' ?>">Kelola Penulis</a></li>
            <li><a href="admin_transaksi.php" class="<?= $currentFile === 'admin_transaksi.php' ? 'active' : '' ?>">Riwayat Transaksi</a></li>
            <li><a href="logout.php" class="logout-btn">Logout</a></li>
        </ul>
    </div>
</header>
