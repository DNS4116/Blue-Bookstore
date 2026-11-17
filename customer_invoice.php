<?php
session_start();
require_once __DIR__ . '/koneksi.php';

// Verifikasi customer
if (empty($_SESSION['user_id']) || ($_SESSION['user_role'] ?? '') !== 'customer') {
    header('Location: index.php');
    exit;
}

$id_order = (int) ($_GET['order_id'] ?? 0);
$id_customer = (int) $_SESSION['user_id'];

// Ambil data order milik customer yang login
$stmt = $koneksi->prepare("
    SELECT o.id_order, o.tanggal, o.totalHarga, u.nama AS customer_nama, u.email AS customer_email, p.metode, p.status
    FROM `order` o
    JOIN users u ON o.id_customer = u.id
    JOIN payment p ON o.id_payment = p.id_payment
    WHERE o.id_order = ? AND o.id_customer = ?
");
$stmt->bind_param("ii", $id_order, $id_customer);
$stmt->execute();
$resOrder = $stmt->get_result();

if ($resOrder->num_rows === 0) {
    header('Location: customer.php');
    exit;
}

$order = $resOrder->fetch_assoc();
$stmt->close();

// Ambil item buku yang dibeli
$stmtItems = $koneksi->prepare("
    SELECT od.jumlah, od.harga, od.subtotal, b.judul, p.nama AS penulis_nama
    FROM orderdetail od
    JOIN tb_buku b ON od.id_buku = b.id_buku
    LEFT JOIN penulis p ON b.id_penulis = p.id_penulis
    WHERE od.id_order = ?
");
$stmtItems->bind_param("i", $id_order);
$stmtItems->execute();
$items = $stmtItems->get_result();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice Pembayaran #<?= (int) $order['id_order'] ?> | Blue BookStore</title>
    <link rel="stylesheet" href="css/global.css">
    <link rel="stylesheet" href="css/customer.css">
</head>
<body>
    <div class="invoice-card">
        <div class="invoice-header">
            <div>
                <h1>BUKTI PEMBAYARAN</h1>
                <p>Blue BookStore · Nomor Pesanan: #<?= (int) $order['id_order'] ?></p>
            </div>
            <div>
                <span class="badge-status"><?= e($order['status']) ?></span>
            </div>
        </div>

        <div class="invoice-body">
            <div class="meta-grid">
                <div class="meta-item">
                    <strong>Waktu Transaksi</strong>
                    <span><?= e($order['tanggal']) ?></span>
                </div>
                <div class="meta-item">
                    <strong>Nama Pembeli</strong>
                    <span><?= e($order['customer_nama']) ?></span>
                </div>
                <div class="meta-item">
                    <strong>Email Pembeli</strong>
                    <span><?= e($order['customer_email']) ?></span>
                </div>
                <div class="meta-item">
                    <strong>Metode Pembayaran</strong>
                    <span><?= e($order['metode']) ?></span>
                </div>
            </div>

            <table>
                <thead>
                    <tr>
                        <th>Buku</th>
                        <th>Penulis</th>
                        <th>Harga</th>
                        <th class="text-center">Qty</th>
                        <th class="text-right">Subtotal</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($it = $items->fetch_assoc()): ?>
                        <tr>
                            <td><strong><?= e($it['judul']) ?></strong></td>
                            <td><?= e($it['penulis_nama'] ?? '-') ?></td>
                            <td><?= formatRupiah($it['harga']) ?></td>
                            <td class="text-center"><?= (int) $it['jumlah'] ?></td>
                            <td class="text-right fw-semibold"><?= formatRupiah($it['subtotal']) ?></td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>

            <div class="total-box">
                <div class="total-details">
                    <div class="total-row">
                        <span>Ongkos Kirim</span>
                        <span class="free-tag">Rp 0 (Gratis)</span>
                    </div>
                    <div class="total-row">
                        <span>Metode</span>
                        <span><?= e($order['metode']) ?></span>
                    </div>
                    <div class="total-grand">
                        <span>Total Bayar</span>
                        <span><?= formatRupiah($order['totalHarga']) ?></span>
                    </div>
                </div>
            </div>

            <div class="actions">
                <button type="button" class="btn btn-print" onclick="window.print()">
                    🖨 Cetak Struk / Invoice
                </button>
                <a href="customer_transaksi.php" class="btn btn-primary">
                    Lihat Riwayat Pesanan
                </a>
                <a href="customer.php" class="btn btn-outline">
                    &larr; Lanjut Belanja
                </a>
            </div>
        </div>
    </div>
</body>
</html>
