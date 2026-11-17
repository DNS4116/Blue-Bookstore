<?php
session_start();
require_once __DIR__ . '/koneksi.php';

// Verifikasi role customer
if (empty($_SESSION['user_id']) || ($_SESSION['user_role'] ?? '') !== 'customer') {
    header('Location: index.php');
    exit;
}

$pesan = '';
$jenisPesan = '';

// Inisialisasi Cart jika belum ada
if (!isset($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

// 1. PROSES TAMBAH BUKU KE KERANJANG
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['aksi']) && $_POST['aksi'] === 'add_to_cart') {
    $id_buku = (int) ($_POST['id_buku'] ?? 0);
    $qty = max(1, (int) ($_POST['qty'] ?? 1));

    if ($id_buku > 0) {
        $resBuku = $koneksi->query("SELECT id_buku, judul, harga, stok, gambar FROM tb_buku WHERE id_buku = $id_buku");
        if ($resBuku && $resBuku->num_rows > 0) {
            $buku = $resBuku->fetch_assoc();
            $stokTersedia = (int) $buku['stok'];

            if ($stokTersedia <= 0) {
                $pesan = "Maaf, stok buku '" . $buku['judul'] . "' sedang habis.";
                $jenisPesan = "error";
            } else {
                $qtySekarang = $_SESSION['cart'][$id_buku]['qty'] ?? 0;
                $qtyBaru = $qtySekarang + $qty;

                if ($qtyBaru > $stokTersedia) {
                    $pesan = "Jumlah yang diminta melebihi sisa stok (" . $stokTersedia . " tersedia).";
                    $jenisPesan = "error";
                } else {
                    $_SESSION['cart'][$id_buku] = [
                        'id_buku' => $buku['id_buku'],
                        'judul' => $buku['judul'],
                        'harga' => (float) $buku['harga'],
                        'gambar' => $buku['gambar'],
                        'qty' => $qtyBaru
                    ];
                    $pesan = "Buku '" . $buku['judul'] . "' berhasil ditambahkan ke keranjang belanja!";
                    $jenisPesan = "success";
                }
            }
        }
    }
}

// 2. FILTER & PENCARIAN
$keyword = trim($_GET['q'] ?? '');
$filterKat = (int) ($_GET['kategori'] ?? 0);

$daftarKategori = $koneksi->query("SELECT id_category, nama FROM category ORDER BY nama ASC");

$queryBuku = "
    SELECT b.*, p.nama AS penulis_nama, c.nama AS kategori_nama
    FROM tb_buku b
    LEFT JOIN penulis p ON b.id_penulis = p.id_penulis
    LEFT JOIN category c ON b.id_category = c.id_category
    WHERE 1=1
";

if ($keyword !== '') {
    $kSafe = $koneksi->real_escape_string($keyword);
    $queryBuku .= " AND (b.judul LIKE '%$kSafe%' OR p.nama LIKE '%$kSafe%' OR b.deskripsi LIKE '%$kSafe%') ";
}

if ($filterKat > 0) {
    $queryBuku .= " AND b.id_category = $filterKat ";
}

$queryBuku .= " ORDER BY b.id_buku DESC ";
$hasilBuku = $koneksi->query($queryBuku);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Katalog Buku | Blue BookStore</title>
    <link rel="stylesheet" href="css/global.css">
    <link rel="stylesheet" href="css/navbar.css">
    <link rel="stylesheet" href="css/customer.css">
</head>
<body>
    <?php include __DIR__ . '/navbar/customer_navbar.php'; ?>

    <!-- HERO BANNER -->
    <section class="hero">
        <div class="hero-wrap">
            <h1>Selamat Datang, <?= e($_SESSION['user_nama'] ?? 'Pembaca Setia') ?>!</h1>
            <p>Jelajahi berbagai judul buku berkualitas dari penulis terkemuka. Pilih buku favorit Anda dan checkout langsung dengan mudah.</p>
        </div>
    </section>

    <!-- SEARCH & FILTER BAR -->
    <div class="search-container">
        <form action="customer.php" method="get" class="search-form">
            <input type="text" name="q" class="search-input" placeholder="Cari judul buku, penulis, atau kata kunci..." value="<?= e($keyword) ?>">
            <select name="kategori" class="category-select">
                <option value="0">Semua Kategori</option>
                <?php while ($kat = $daftarKategori->fetch_assoc()): ?>
                    <option value="<?= (int) $kat['id_category'] ?>" <?= $filterKat == $kat['id_category'] ? 'selected' : '' ?>>
                        <?= e($kat['nama']) ?>
                    </option>
                <?php endwhile; ?>
            </select>
            <button type="submit" class="btn-search">Cari Buku</button>
            <?php if ($keyword !== '' || $filterKat > 0): ?>
                <a href="customer.php" class="btn-reset">Reset</a>
            <?php endif; ?>
        </form>
    </div>

    <!-- MAIN CONTENT -->
    <div class="container">
        <?php if ($pesan !== ''): ?>
            <div class="alert alert-<?= $jenisPesan ?>">
                <span><?= e($pesan) ?></span>
                <?php if ($jenisPesan === 'success'): ?>
                    <a href="customer_cart.php" class="alert-cart-link">Lihat Keranjang &rarr;</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <div class="section-header">
            <h2>Katalog Buku Pilihan</h2>
            <span>Menampilkan <?= (int) $hasilBuku->num_rows ?> koleksi buku</span>
        </div>

        <?php if ($hasilBuku->num_rows === 0): ?>
            <div class="empty-box">
                <h3 class="mb-2">Tidak Ada Buku yang Ditemukan</h3>
                <p>Coba gunakan kata kunci pencarian yang lain atau reset filter kategori Anda.</p>
            </div>
        <?php else: ?>
            <div class="books-grid">
                <?php while ($buku = $hasilBuku->fetch_assoc()): ?>
                    <?php 
                    $adaCover = !empty($buku['gambar']) && file_exists(__DIR__ . '/uploads/' . $buku['gambar']);
                    $stok = (int) $buku['stok'];
                    ?>
                    <div class="book-card">
                        <!-- Cover Buku -->
                        <div class="book-cover">
                            <?php if ($adaCover): ?>
                                <img src="uploads/<?= e($buku['gambar']) ?>" alt="<?= e($buku['judul']) ?>" loading="lazy">
                            <?php else: ?>
                                <div class="book-cover-placeholder">
                                    <span class="placeholder-logo">BLUE BOOK</span>
                                    <div class="placeholder-title"><?= e($buku['judul']) ?></div>
                                    <div class="placeholder-author"><?= e($buku['penulis_nama'] ?? 'Penulis') ?></div>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Info Buku -->
                        <div class="book-body">
                            <div class="book-category"><?= e($buku['kategori_nama'] ?? 'Umum') ?></div>
                            <h3 class="book-title"><?= e($buku['judul']) ?></h3>
                            <div class="book-author">Oleh <?= e($buku['penulis_nama'] ?? 'Anonim') ?></div>
                            <p class="book-desc"><?= e($buku['deskripsi'] ?: 'Buku berkualitas koleksi toko Blue BookStore.') ?></p>

                            <div class="book-footer">
                                <div class="book-price"><?= formatRupiah($buku['harga']) ?></div>
                                <div>
                                    <?php if ($stok > 5): ?>
                                        <span class="book-stock stock-in">Stok: <?= $stok ?></span>
                                    <?php elseif ($stok > 0): ?>
                                        <span class="book-stock stock-low">Sisa <?= $stok ?> unit</span>
                                    <?php else: ?>
                                        <span class="book-stock stock-out">Habis</span>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- Tombol Aksi -->
                            <div class="book-actions">
                                <form action="customer.php" method="post" class="d-flex gap-2">
                                    <input type="hidden" name="aksi" value="add_to_cart">
                                    <input type="hidden" name="id_buku" value="<?= (int) $buku['id_buku'] ?>">
                                    <input type="hidden" name="qty" value="1">
                                    <button type="submit" class="btn-add-cart" <?= $stok <= 0 ? 'disabled' : '' ?>>
                                        <?= $stok > 0 ? '+ Pilih / Tambah ke Keranjang' : 'Stok Kosong' ?>
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
