<?php
session_start();
require_once __DIR__ . '/koneksi.php';

// Verifikasi role customer
if (empty($_SESSION['user_id']) || ($_SESSION['user_role'] ?? '') !== 'customer') {
    header('Location: index.php');
    exit;
}

if (!isset($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

$pesan = '';
$jenisPesan = '';

// 1. PROSES AKSI KERANJANG (UPDATE QTY / HAPUS ITEM / KOSONGKAN)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $aksi = $_POST['aksi'] ?? '';

    // UPDATE QTY
    if ($aksi === 'update_qty') {
        $id_buku = (int) ($_POST['id_buku'] ?? 0);
        $qty = (int) ($_POST['qty'] ?? 1);

        if ($id_buku > 0 && isset($_SESSION['cart'][$id_buku])) {
            if ($qty <= 0) {
                unset($_SESSION['cart'][$id_buku]);
                $pesan = "Buku dihapus dari keranjang.";
                $jenisPesan = "info";
            } else {
                // Periksa stok database
                $bukuDb = $koneksi->query("SELECT stok, judul FROM tb_buku WHERE id_buku = $id_buku")->fetch_assoc();
                $stokTersedia = (int) ($bukuDb['stok'] ?? 0);
                if ($qty > $stokTersedia) {
                    $_SESSION['cart'][$id_buku]['qty'] = $stokTersedia;
                    $pesan = "Jumlah untuk '" . $bukuDb['judul'] . "' disesuaikan dengan stok maksimal yang tersedia ($stokTersedia).";
                    $jenisPesan = "warning";
                } else {
                    $_SESSION['cart'][$id_buku]['qty'] = $qty;
                    $pesan = "Keranjang belanja berhasil diperbarui.";
                    $jenisPesan = "success";
                }
            }
        }
    }

    // HAPUS SATU ITEM
    if ($aksi === 'hapus_item') {
        $id_buku = (int) ($_POST['id_buku'] ?? 0);
        if ($id_buku > 0 && isset($_SESSION['cart'][$id_buku])) {
            $judul = $_SESSION['cart'][$id_buku]['judul'];
            unset($_SESSION['cart'][$id_buku]);
            $pesan = "Buku '$judul' telah dihapus dari keranjang.";
            $jenisPesan = "success";
        }
    }

    // KOSONGKAN KERANJANG
    if ($aksi === 'kosongkan') {
        $_SESSION['cart'] = [];
        $pesan = "Keranjang belanja telah dikosongkan.";
        $jenisPesan = "info";
    }
}

// 2. HITUNG TOTAL & CEK DATA TERBARU DARI DATABASE
$totalBelanja = 0;
$totalItems = 0;
$cartDetails = [];

if (!empty($_SESSION['cart'])) {
    $ids = array_map('intval', array_keys($_SESSION['cart']));
    $idList = implode(',', $ids);
    $resDb = $koneksi->query("SELECT b.id_buku, b.judul, b.harga, b.stok, b.gambar, p.nama AS penulis_nama FROM tb_buku b LEFT JOIN penulis p ON b.id_penulis = p.id_penulis WHERE b.id_buku IN ($idList)");
    
    $dbBooks = [];
    while ($r = $resDb->fetch_assoc()) {
        $dbBooks[$r['id_buku']] = $r;
    }

    foreach ($_SESSION['cart'] as $id => $item) {
        if (isset($dbBooks[$id])) {
            $dbItem = $dbBooks[$id];
            $qty = min((int) $item['qty'], (int) $dbItem['stok']);
            if ($qty <= 0) {
                // Stok habis, hapus dari cart
                unset($_SESSION['cart'][$id]);
                continue;
            }
            $_SESSION['cart'][$id]['qty'] = $qty;
            $_SESSION['cart'][$id]['harga'] = (float) $dbItem['harga'];
            $_SESSION['cart'][$id]['judul'] = $dbItem['judul'];
            $_SESSION['cart'][$id]['gambar'] = $dbItem['gambar'];

            $subtotal = $qty * (float) $dbItem['harga'];
            $totalBelanja += $subtotal;
            $totalItems += $qty;

            $cartDetails[] = [
                'id_buku' => $id,
                'judul' => $dbItem['judul'],
                'penulis' => $dbItem['penulis_nama'] ?? '-',
                'harga' => (float) $dbItem['harga'],
                'stok' => (int) $dbItem['stok'],
                'gambar' => $dbItem['gambar'],
                'qty' => $qty,
                'subtotal' => $subtotal
            ];
        } else {
            // Buku sudah tidak ada di database
            unset($_SESSION['cart'][$id]);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Keranjang Belanja | Blue BookStore</title>
    <link rel="stylesheet" href="css/global.css">
    <link rel="stylesheet" href="css/navbar.css">
    <link rel="stylesheet" href="css/customer.css">
</head>
<body>
    <?php include __DIR__ . '/navbar/customer_navbar.php'; ?>

    <div class="container">
        <div class="page-header">
            <h1>Keranjang Belanja</h1>
            <p>Periksa buku pilihan Anda sebelum melanjutkan ke pembayaran.</p>
        </div>

        <?php if ($pesan !== ''): ?>
            <div class="alert alert-<?= $jenisPesan ?>">
                <span><?= e($pesan) ?></span>
            </div>
        <?php endif; ?>

        <?php if (empty($cartDetails)): ?>
            <div class="empty-cart-box">
                <h2>Keranjang Belanja Anda Kosong</h2>
                <p>Anda belum memilih buku apapun untuk dimasukkan ke keranjang.</p>
                <a href="customer.php" class="btn btn-primary btn-auto">
                    &larr; Mulai Jelajahi & Pilih Buku
                </a>
            </div>
        <?php else: ?>
            <div class="cart-grid">
                <!-- TABEL ITEM KERANJANG -->
                <div class="card">
                    <div class="card-header">
                        <h2>Daftar Buku Terpilih (<?= count($cartDetails) ?> judul)</h2>
                        <form action="customer_cart.php" method="post" onsubmit="return confirm('Kosongkan semua item di keranjang?');">
                            <input type="hidden" name="aksi" value="kosongkan">
                            <button type="submit" class="btn btn-secondary btn-sm">Kosongkan Keranjang</button>
                        </form>
                    </div>
                    <div class="table-responsive">
                        <table>
                            <thead>
                                <tr>
                                    <th>Buku</th>
                                    <th>Harga Satuan</th>
                                    <th>Jumlah (Qty)</th>
                                    <th>Subtotal</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($cartDetails as $item): ?>
                                    <?php 
                                    $adaCover = !empty($item['gambar']) && file_exists(__DIR__ . '/uploads/' . $item['gambar']);
                                    ?>
                                    <tr>
                                        <td>
                                            <div class="d-flex gap-3 align-center">
                                                <?php if ($adaCover): ?>
                                                    <img src="uploads/<?= e($item['gambar']) ?>" alt="<?= e($item['judul']) ?>" class="book-thumb">
                                                <?php else: ?>
                                                    <div class="book-thumb-placeholder">BOOK</div>
                                                <?php endif; ?>
                                                <div>
                                                    <strong class="fs-base"><?= e($item['judul']) ?></strong><br>
                                                    <small class="text-muted"><?= e($item['penulis']) ?></small><br>
                                                    <small class="text-success">Tersedia: <?= $item['stok'] ?> stok</small>
                                                </div>
                                            </div>
                                        </td>
                                        <td><?= formatRupiah($item['harga']) ?></td>
                                        <td>
                                            <!-- Form Ubah Qty -->
                                            <form action="customer_cart.php" method="post" class="form-inline">
                                                <input type="hidden" name="aksi" value="update_qty">
                                                <input type="hidden" name="id_buku" value="<?= (int) $item['id_buku'] ?>">
                                                <div class="qty-control">
                                                    <button type="submit" name="qty" value="<?= $item['qty'] - 1 ?>" class="qty-btn">-</button>
                                                    <span class="qty-input"><?= $item['qty'] ?></span>
                                                    <button type="submit" name="qty" value="<?= $item['qty'] + 1 ?>" class="qty-btn" <?= $item['qty'] >= $item['stok'] ? 'disabled' : '' ?>>+</button>
                                                </div>
                                            </form>
                                        </td>
                                        <td><strong><?= formatRupiah($item['subtotal']) ?></strong></td>
                                        <td>
                                            <form action="customer_cart.php" method="post" onsubmit="return confirm('Hapus buku ini dari keranjang?');">
                                                <input type="hidden" name="aksi" value="hapus_item">
                                                <input type="hidden" name="id_buku" value="<?= (int) $item['id_buku'] ?>">
                                                <button type="submit" class="btn-danger-outline">Hapus</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="card-footer">
                        <a href="customer.php" class="btn btn-secondary">&larr; Tambah Buku Lainnya</a>
                    </div>
                </div>

                <!-- RINGKASAN PEMBAYARAN -->
                <div class="card">
                    <div class="card-header">
                        <h2>Ringkasan Pesanan</h2>
                    </div>
                    <div class="card-body">
                        <div class="summary-row">
                            <span>Total Jumlah Buku</span>
                            <strong><?= $totalItems ?> pcs</strong>
                        </div>
                        <div class="summary-row">
                            <span>Biaya Pengiriman</span>
                            <span class="text-success fw-semibold">GRATIS</span>
                        </div>
                        <div class="summary-row">
                            <span>PPN & Biaya Layanan</span>
                            <span class="text-success fw-semibold">Termasuk</span>
                        </div>
                        <div class="summary-total">
                            <span>Total Tagihan</span>
                            <span><?= formatRupiah($totalBelanja) ?></span>
                        </div>

                        <div class="mt-4">
                            <a href="customer_checkout.php" class="btn btn-primary">
                                Lanjut ke Pembayaran &rarr;
                            </a>
                        </div>

                        <div class="mt-3 fs-xs text-muted text-center">
                            🔒 Pembayaran instan & otomatis via QRIS (Semua Bank & E-Wallet).
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
