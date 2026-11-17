<?php
session_start();
require_once __DIR__ . '/koneksi.php';

// Verifikasi role admin
if (empty($_SESSION['user_id']) || ($_SESSION['user_role'] ?? '') !== 'admin') {
    header('Location: index.php');
    exit;
}

// 1. Statistik Ringkasan
$totalBuku = $koneksi->query("SELECT COUNT(*) AS total, IFNULL(SUM(stok), 0) AS total_stok FROM tb_buku")->fetch_assoc();
$totalPenulis = $koneksi->query("SELECT COUNT(*) AS total FROM penulis")->fetch_assoc();
$totalCustomer = $koneksi->query("SELECT COUNT(*) AS total FROM users WHERE role = 'customer'")->fetch_assoc();
$totalTransaksi = $koneksi->query("SELECT COUNT(*) AS total, IFNULL(SUM(totalHarga), 0) AS pendapatan FROM `order`")->fetch_assoc();

// 2. Daftar Transaksi Terbaru (5 transaksi terakhir)
$transaksiTerbaru = $koneksi->query("
    SELECT o.id_order, o.tanggal, o.totalHarga, u.nama AS customer_nama, u.email AS customer_email, p.metode, p.status
    FROM `order` o
    JOIN users u ON o.id_customer = u.id
    JOIN payment p ON o.id_payment = p.id_payment
    ORDER BY o.tanggal DESC, o.id_order DESC
    LIMIT 5
");

// 3. Buku dengan Stok Rendah / Buku Terbaru (5 buku)
$bukuTerbaru = $koneksi->query("
    SELECT b.id_buku, b.judul, b.harga, b.stok, p.nama AS penulis_nama, c.nama AS kategori_nama
    FROM tb_buku b
    LEFT JOIN penulis p ON b.id_penulis = p.id_penulis
    LEFT JOIN category c ON b.id_category = c.id_category
    ORDER BY b.id_buku DESC
    LIMIT 5
");
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Admin | Blue BookStore</title>
    <link rel="stylesheet" href="css/global.css">
    <link rel="stylesheet" href="css/navbar.css">
    <link rel="stylesheet" href="css/admin.css">
</head>
<body>
    <?php include __DIR__ . '/navbar/admin_navbar.php'; ?>

    <div class="container">
        <div class="page-header">
            <div>
                <h1>Dashboard Admin</h1>
                <p>Selamat datang kembali, <strong><?= e($_SESSION['user_nama'] ?? 'Admin') ?></strong>. Ringkasan operasional toko Anda hari ini.</p>
            </div>
            <div class="quick-actions">
                <a href="admin_buku.php?action=tambah" class="btn btn-primary">+ Tambah Buku</a>
                <a href="admin_penulis.php?action=tambah" class="btn btn-secondary">+ Tambah Penulis</a>
            </div>
        </div>

        <!-- 4 Kotak Ringkasan Statistik -->
        <section class="stats-grid">
            <div class="stat-card">
                <span class="label">Total Buku</span>
                <span class="value"><?= (int) $totalBuku['total'] ?></span>
                <span class="desc"><?= (int) $totalBuku['total_stok'] ?> total unit tersedia</span>
            </div>
            <div class="stat-card">
                <span class="label">Total Penulis</span>
                <span class="value"><?= (int) $totalPenulis['total'] ?></span>
                <span class="desc">Penulis terdaftar di toko</span>
            </div>
            <div class="stat-card">
                <span class="label">Total Transaksi</span>
                <span class="value"><?= (int) $totalTransaksi['total'] ?></span>
                <span class="desc">Pesanan yang dibuat oleh user</span>
            </div>
            <div class="stat-card">
                <span class="label">Total Pendapatan</span>
                <span class="value val-success-lg"><?= formatRupiah($totalTransaksi['pendapatan']) ?></span>
                <span class="desc"><?= (int) $totalCustomer['total'] ?> customer terdaftar</span>
            </div>
        </section>

        <!-- 2 Kolom Ringkasan: Transaksi Terbaru & Buku Terbaru -->
        <div class="grid-two">
            <!-- Kolom Transaksi Terbaru -->
            <div class="card">
                <div class="card-header">
                    <h2>Transaksi Terbaru User</h2>
                    <a href="admin_transaksi.php">Lihat Semua &rarr;</a>
                </div>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Pelanggan</th>
                                <th>Total</th>
                                <th>Metode</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($transaksiTerbaru->num_rows === 0): ?>
                                <tr>
                                    <td colspan="5" class="empty-state">Belum ada transaksi dari customer.</td>
                                </tr>
                            <?php else: ?>
                                <?php while ($trx = $transaksiTerbaru->fetch_assoc()): ?>
                                    <tr>
                                        <td><strong>#<?= (int) $trx['id_order'] ?></strong></td>
                                        <td>
                                            <?= e($trx['customer_nama']) ?><br>
                                            <small class="text-muted"><?= e($trx['customer_email']) ?></small>
                                        </td>
                                        <td><strong><?= formatRupiah($trx['totalHarga']) ?></strong></td>
                                        <td><?= e($trx['metode']) ?></td>
                                        <td>
                                            <?php
                                             $st = strtolower($trx['status']);
                                             $badgeClass = 'badge-success';
                                             if ($st === 'menunggu' || $st === 'diproses') $badgeClass = 'badge-warning';
                                             if ($st === 'dibatalkan') $badgeClass = 'badge-danger';
                                            ?>
                                            <span class="badge <?= $badgeClass ?>"><?= e($trx['status']) ?></span>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Kolom Buku Terbaru -->
            <div class="card">
                <div class="card-header">
                    <h2>Katalog Buku Terbaru</h2>
                    <a href="admin_buku.php">Kelola Buku &rarr;</a>
                </div>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>Judul Buku</th>
                                <th>Penulis</th>
                                <th>Harga</th>
                                <th>Stok</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($bukuTerbaru->num_rows === 0): ?>
                                <tr>
                                    <td colspan="4" class="empty-state">Belum ada buku terdaftar.</td>
                                </tr>
                            <?php else: ?>
                                <?php while ($bk = $bukuTerbaru->fetch_assoc()): ?>
                                    <tr>
                                        <td>
                                            <strong><?= e($bk['judul']) ?></strong><br>
                                            <small class="text-muted"><?= e($bk['kategori_nama'] ?? 'Umum') ?></small>
                                        </td>
                                        <td><?= e($bk['penulis_nama'] ?? '-') ?></td>
                                        <td><?= formatRupiah($bk['harga']) ?></td>
                                        <td>
                                            <?php if ($bk['stok'] > 5): ?>
                                                <span class="badge badge-success"><?= (int) $bk['stok'] ?></span>
                                            <?php elseif ($bk['stok'] > 0): ?>
                                                <span class="badge badge-warning"><?= (int) $bk['stok'] ?></span>
                                            <?php else: ?>
                                                <span class="badge badge-danger">Habis</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
