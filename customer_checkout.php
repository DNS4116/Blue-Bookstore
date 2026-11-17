<?php
session_start();
require_once __DIR__ . '/koneksi.php';

// Verifikasi role customer
if (empty($_SESSION['user_id']) || ($_SESSION['user_role'] ?? '') !== 'customer') {
    header('Location: index.php');
    exit;
}

// Cek apakah keranjang memiliki barang
if (empty($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
    header('Location: customer_cart.php');
    exit;
}

$pesan = '';
$jenisPesan = '';

// Ambil data buku terbaru & hitung total
$totalBelanja = 0;
$totalItems = 0;
$cartDetails = [];

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
        $qty = (int) $item['qty'];
        $subtotal = $qty * (float) $dbItem['harga'];
        $totalBelanja += $subtotal;
        $totalItems += $qty;

        $cartDetails[] = [
            'id_buku' => $id,
            'judul' => $dbItem['judul'],
            'penulis' => $dbItem['penulis_nama'] ?? '-',
            'harga' => (float) $dbItem['harga'],
            'stok' => (int) $dbItem['stok'],
            'qty' => $qty,
            'subtotal' => $subtotal
        ];
    }
}

// PROSES TRANSAKSI CHECKOUT (QRIS ONLY)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['aksi']) && $_POST['aksi'] === 'proses_checkout') {
    $metode = 'QRIS';

    // Mulai Transaksi Database
    $koneksi->begin_transaction();
    try {
        // 1. Validasi stok real-time
        foreach ($cartDetails as $c) {
            $idB = $c['id_buku'];
            $qB = $c['qty'];
            $cek = $koneksi->query("SELECT stok, judul FROM tb_buku WHERE id_buku = $idB FOR UPDATE")->fetch_assoc();
            if (!$cek || (int)$cek['stok'] < $qB) {
                throw new Exception("Stok untuk buku '" . ($cek['judul'] ?? 'Buku') . "' tidak mencukupi (sisa: " . ($cek['stok'] ?? 0) . ").");
            }
        }

        // 2. Simpan ke tabel payment (Metode QRIS & Status Lunas)
        $statusBayar = 'Lunas';
        $stmtPay = $koneksi->prepare("INSERT INTO payment (metode, jumlah, status) VALUES (?, ?, ?)");
        $stmtPay->bind_param("sds", $metode, $totalBelanja, $statusBayar);
        $stmtPay->execute();
        $id_payment = $stmtPay->insert_id;
        $stmtPay->close();

        // 3. Simpan ke tabel order
        $id_customer = (int) $_SESSION['user_id'];
        $stmtOrder = $koneksi->prepare("INSERT INTO `order` (id_customer, id_payment, tanggal, totalHarga) VALUES (?, ?, NOW(), ?)");
        $stmtOrder->bind_param("iid", $id_customer, $id_payment, $totalBelanja);
        $stmtOrder->execute();
        $id_order = $stmtOrder->insert_id;
        $stmtOrder->close();

        // 4. Simpan ke tabel orderdetail & kurangi stok buku
        $stmtDetail = $koneksi->prepare("INSERT INTO orderdetail (id_order, id_buku, jumlah, harga, subtotal) VALUES (?, ?, ?, ?, ?)");
        $stmtStok = $koneksi->prepare("UPDATE tb_buku SET stok = stok - ? WHERE id_buku = ?");

        foreach ($cartDetails as $item) {
            $stmtDetail->bind_param("iiidd", $id_order, $item['id_buku'], $item['qty'], $item['harga'], $item['subtotal']);
            $stmtDetail->execute();

            $stmtStok->bind_param("ii", $item['qty'], $item['id_buku']);
            $stmtStok->execute();
        }

        $stmtDetail->close();
        $stmtStok->close();

        // Commit transaksi
        $koneksi->commit();

        // Kosongkan keranjang belanja
        $_SESSION['cart'] = [];

        // Alihkan ke invoice resmi
        header("Location: customer_invoice.php?order_id=$id_order");
        exit;

    } catch (Exception $e) {
        $koneksi->rollback();
        $pesan = "Gagal memproses pembayaran: " . $e->getMessage();
        $jenisPesan = "error";
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pembayaran QRIS | Blue BookStore</title>
    <link rel="stylesheet" href="css/global.css">
    <link rel="stylesheet" href="css/navbar.css">
    <link rel="stylesheet" href="css/customer.css">
</head>
<body>
    <?php include __DIR__ . '/navbar/customer_navbar.php'; ?>

    <div class="container">
        <div class="page-header">
            <h1>Pembayaran Pesanan (QRIS Only)</h1>
            <p>Scan kode QRIS resmi di bawah menggunakan aplikasi perbankan atau e-wallet apa saja.</p>
        </div>

        <?php if ($pesan !== ''): ?>
            <div class="alert alert-error">
                <span><?= e($pesan) ?></span>
            </div>
        <?php endif; ?>

        <form action="customer_checkout.php" method="post" id="checkout-form">
            <input type="hidden" name="aksi" value="proses_checkout">
            <input type="hidden" name="metode" value="QRIS">

            <div class="checkout-grid">
                <!-- FORM QRIS DAN INFORMASI PEMBELI -->
                <div>
                    <!-- Data Pelanggan -->
                    <div class="card">
                        <div class="card-header">
                            <h2>1. Data Pemesan</h2>
                        </div>
                        <div class="card-body">
                            <div class="info-row">
                                <span>Nama Pembeli</span>
                                <strong><?= e($_SESSION['user_nama'] ?? '') ?></strong>
                            </div>
                            <div class="info-row">
                                <span>Alamat Email</span>
                                <strong><?= e($_SESSION['user_email'] ?? '') ?></strong>
                            </div>
                            <div class="info-row">
                                <span>Metode Bayar</span>
                                <strong><span class="tag-qris">QRIS</span> (Satu-satunya Metode Pembayaran)</strong>
                            </div>
                        </div>
                    </div>

                    <!-- KARTU PEMBAYARAN QRIS (EXCLUSIVE) -->
                    <div class="card">
                        <div class="card-header">
                            <h2>2. Kode Pembayaran QRIS</h2>
                        </div>
                        <div class="card-body">
                            <div class="qris-card">
                                <div class="qris-badge-top">QRIS</div>
                                <div class="qris-merchant">BLUE BOOKSTORE INDONESIA</div>
                                <div class="qris-nmid">NMID: ID102026101299108 · Standar Pembayaran Nasional</div>

                                <!-- Ilustrasi Kode QRIS SVG Beresolusi Tinggi -->
                                <div class="qris-code-container" title="Scan QRIS Blue BookStore">
                                    <svg viewBox="0 0 100 100" xmlns="http://www.w3.org/2000/svg">
                                        <!-- Background -->
                                        <rect width="100" height="100" fill="#ffffff" />
                                        
                                        <!-- Corner 1 (Top Left) -->
                                        <rect x="6" y="6" width="26" height="26" fill="#0f172a" rx="2" />
                                        <rect x="10" y="10" width="18" height="18" fill="#ffffff" rx="1" />
                                        <rect x="14" y="14" width="10" height="10" fill="#0f172a" rx="1" />

                                        <!-- Corner 2 (Top Right) -->
                                        <rect x="68" y="6" width="26" height="26" fill="#0f172a" rx="2" />
                                        <rect x="72" y="10" width="18" height="18" fill="#ffffff" rx="1" />
                                        <rect x="76" y="14" width="10" height="10" fill="#0f172a" rx="1" />

                                        <!-- Corner 3 (Bottom Left) -->
                                        <rect x="6" y="68" width="26" height="26" fill="#0f172a" rx="2" />
                                        <rect x="10" y="72" width="18" height="18" fill="#ffffff" rx="1" />
                                        <rect x="14" y="76" width="10" height="10" fill="#0f172a" rx="1" />

                                        <!-- Patterns -->
                                        <rect x="36" y="8" width="6" height="6" fill="#0f172a" />
                                        <rect x="46" y="8" width="6" height="6" fill="#0f172a" />
                                        <rect x="56" y="8" width="6" height="6" fill="#0f172a" />

                                        <rect x="38" y="18" width="6" height="6" fill="#0f172a" />
                                        <rect x="48" y="18" width="6" height="6" fill="#0f172a" />
                                        <rect x="58" y="18" width="6" height="6" fill="#0f172a" />

                                        <rect x="8" y="38" width="6" height="6" fill="#0f172a" />
                                        <rect x="18" y="38" width="6" height="6" fill="#0f172a" />
                                        <rect x="28" y="38" width="6" height="6" fill="#0f172a" />

                                        <rect x="8" y="48" width="6" height="6" fill="#0f172a" />
                                        <rect x="18" y="48" width="6" height="6" fill="#0f172a" />
                                        <rect x="28" y="48" width="6" height="6" fill="#0f172a" />

                                        <rect x="8" y="58" width="6" height="6" fill="#0f172a" />
                                        <rect x="28" y="58" width="6" height="6" fill="#0f172a" />

                                        <!-- Center QRIS Badge -->
                                        <rect x="38" y="38" width="24" height="24" fill="#0284c7" rx="4" />
                                        <text x="50" y="53" font-size="7" font-weight="900" fill="#ffffff" text-anchor="middle" font-family="Arial, sans-serif">QRIS</text>

                                        <!-- Bottom Right Pattern -->
                                        <rect x="68" y="40" width="6" height="6" fill="#0f172a" />
                                        <rect x="80" y="40" width="6" height="6" fill="#0f172a" />
                                        <rect x="74" y="50" width="6" height="6" fill="#0f172a" />
                                        <rect x="86" y="50" width="6" height="6" fill="#0f172a" />
                                        <rect x="68" y="62" width="6" height="6" fill="#0f172a" />
                                        <rect x="80" y="62" width="6" height="6" fill="#0f172a" />

                                        <rect x="38" y="70" width="6" height="6" fill="#0f172a" />
                                        <rect x="48" y="70" width="6" height="6" fill="#0f172a" />
                                        <rect x="58" y="70" width="6" height="6" fill="#0f172a" />

                                        <rect x="38" y="82" width="6" height="6" fill="#0f172a" />
                                        <rect x="48" y="82" width="6" height="6" fill="#0f172a" />
                                        <rect x="58" y="82" width="6" height="6" fill="#0f172a" />
                                        <rect x="70" y="74" width="6" height="6" fill="#0f172a" />
                                        <rect x="82" y="74" width="6" height="6" fill="#0f172a" />
                                        <rect x="76" y="86" width="6" height="6" fill="#0f172a" />
                                        <rect x="86" y="86" width="6" height="6" fill="#0f172a" />
                                    </svg>
                                </div>

                                <div class="qris-total-box">
                                    <span>Total Nominal Tagihan:</span>
                                    <strong><?= formatRupiah($totalBelanja) ?></strong>
                                </div>

                                <div class="steps-box">
                                    <h3>Cara Pembayaran QRIS:</h3>
                                    <ol>
                                        <li>Buka aplikasi m-Banking (BCA, Mandiri, BRI, BNI, dll) atau E-Wallet (GoPay, OVO, DANA, ShopeePay).</li>
                                        <li>Pilih menu <strong>Scan / Bayar QRIS</strong>.</li>
                                        <li>Arahkan kamera ke kode QRIS di atas.</li>
                                        <li>Pastikan penerima adalah <strong>BLUE BOOKSTORE INDONESIA</strong> dan nominal sesuai: <strong><?= formatRupiah($totalBelanja) ?></strong>.</li>
                                        <li>Selesaikan pembayaran, lalu klik tombol konfirmasi di bawah.</li>
                                    </ol>
                                </div>

                                <div class="supported-apps">
                                    <span class="app-pill">BCA Mobile</span>
                                    <span class="app-pill">Livin' Mandiri</span>
                                    <span class="app-pill">BRImo</span>
                                    <span class="app-pill">BNI Mobile</span>
                                    <span class="app-pill">GoPay</span>
                                    <span class="app-pill">OVO</span>
                                    <span class="app-pill">DANA</span>
                                    <span class="app-pill">ShopeePay</span>
                                    <span class="app-pill">+ Semua Bank</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- RINGKASAN PESANAN KANAN -->
                <div class="card">
                    <div class="card-header">
                        <h2>Ringkasan Item Belanja</h2>
                    </div>
                    <div class="card-body">
                        <?php foreach ($cartDetails as $c): ?>
                            <div class="summary-item">
                                <div>
                                    <strong><?= e($c['judul']) ?></strong><br>
                                    <small class="text-muted"><?= (int) $c['qty'] ?> pcs &times; <?= formatRupiah($c['harga']) ?></small>
                                </div>
                                <span class="fw-semibold"><?= formatRupiah($c['subtotal']) ?></span>
                            </div>
                        <?php endforeach; ?>

                        <div class="border-dashed-top">
                            <div class="d-flex justify-between mb-2 fs-sm text-muted">
                                <span>Total Item</span>
                                <strong><?= $totalItems ?> buku</strong>
                            </div>
                            <div class="d-flex justify-between mb-2 fs-sm text-muted">
                                <span>Metode Pembayaran</span>
                                <strong class="tag-qris">QRIS</strong>
                            </div>
                            <div class="d-flex justify-between mb-2 fs-sm text-muted">
                                <span>Ongkos Kirim</span>
                                <span class="free-tag">GRATIS</span>
                            </div>
                            <div class="total-highlight-row">
                                <span>Total Bayar</span>
                                <span><?= formatRupiah($totalBelanja) ?></span>
                            </div>
                        </div>

                        <button type="submit" class="btn-pay" onclick="return confirm('Konfirmasi pembayaran QRIS sebesar <?= formatRupiah($totalBelanja) ?>?');">
                            ✓ Konfirmasi Pembayaran QRIS
                        </button>

                        <div class="mt-3 text-center">
                            <a href="customer_cart.php" class="checkout-back-link">&larr; Kembali ke Keranjang</a>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
</body>
</html>
