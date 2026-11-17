<?php
session_start();
require_once __DIR__ . '/koneksi.php';

// Verifikasi customer
if (empty($_SESSION['user_id']) || ($_SESSION['user_role'] ?? '') !== 'customer') {
    header('Location: index.php');
    exit;
}

$id_customer = (int) $_SESSION['user_id'];

// Ambil semua transaksi milik customer ini
$query = "
    SELECT o.id_order, o.tanggal, o.totalHarga, p.metode, p.status
    FROM `order` o
    JOIN payment p ON o.id_payment = p.id_payment
    WHERE o.id_customer = ?
    ORDER BY o.tanggal DESC, o.id_order DESC
";
$stmt = $koneksi->prepare($query);
$stmt->bind_param("i", $id_customer);
$stmt->execute();
$hasilOrder = $stmt->get_result();

$orders = [];
while ($row = $hasilOrder->fetch_assoc()) {
    $orders[$row['id_order']] = $row;
    $orders[$row['id_order']]['items'] = [];
}
$stmt->close();

// Jika ada order, ambil semua detail itemnya
if (!empty($orders)) {
    $orderIds = implode(',', array_keys($orders));
    $resItems = $koneksi->query("
        SELECT od.id_order, od.jumlah, od.harga, od.subtotal, b.judul, b.gambar, p.nama AS penulis_nama
        FROM orderdetail od
        JOIN tb_buku b ON od.id_buku = b.id_buku
        LEFT JOIN penulis p ON b.id_penulis = p.id_penulis
        WHERE od.id_order IN ($orderIds)
    ");
    while ($it = $resItems->fetch_assoc()) {
        $orders[$it['id_order']]['items'][] = $it;
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Riwayat Pesanan Saya | Blue BookStore</title>
    <link rel="stylesheet" href="css/global.css">
    <link rel="stylesheet" href="css/navbar.css">
    <link rel="stylesheet" href="css/customer.css">
</head>
<body>
    <?php include __DIR__ . '/navbar/customer_navbar.php'; ?>

    <div class="container">
        <div class="page-header">
            <h1>Riwayat Pesanan Saya</h1>
            <p>Daftar seluruh transaksi pembelian buku yang pernah Anda lakukan di Blue BookStore.</p>
        </div>

        <?php if (empty($orders)): ?>
            <div class="empty-state">
                <h2>Belum Ada Riwayat Pesanan</h2>
                <p>Anda belum pernah melakukan pembelian buku. Temukan buku menarik di katalog kami sekarang!</p>
                <a href="customer.php" class="btn btn-primary">&larr; Jelajahi Katalog Buku</a>
            </div>
        <?php else: ?>
            <?php foreach ($orders as $ord): ?>
                <?php
                $st = strtolower($ord['status']);
                $badgeClass = 'badge-success';
                if ($st === 'menunggu pembayaran' || $st === 'diproses') $badgeClass = 'badge-warning';
                if ($st === 'dibatalkan') $badgeClass = 'badge-danger';
                ?>
                <div class="order-card">
                    <div class="order-header">
                        <div class="order-meta">
                            <div class="order-meta-item">
                                <strong>ID Pesanan</strong>
                                <span>#<?= (int) $ord['id_order'] ?></span>
                            </div>
                            <div class="order-meta-item">
                                <strong>Tanggal & Waktu</strong>
                                <span><?= e($ord['tanggal']) ?></span>
                            </div>
                            <div class="order-meta-item">
                                <strong>Metode Pembayaran</strong>
                                <span><?= e($ord['metode']) ?></span>
                            </div>
                        </div>
                        <div>
                            <span class="badge <?= $badgeClass ?>"><?= e($ord['status']) ?></span>
                        </div>
                    </div>

                    <div class="order-body">
                        <?php foreach ($ord['items'] as $it): ?>
                            <div class="order-item-row">
                                <div class="order-item-info">
                                    <strong><?= e($it['judul']) ?></strong>
                                    <span>Penulis: <?= e($it['penulis_nama'] ?? '-') ?> &bull; <?= (int) $it['jumlah'] ?> pcs &times; <?= formatRupiah($it['harga']) ?></span>
                                </div>
                                <div class="fw-bold text-dark">
                                    <?= formatRupiah($it['subtotal']) ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="order-footer">
                        <div>
                            <span class="fs-sm text-muted d-block">Total Pembayaran:</span>
                            <span class="order-total-price"><?= formatRupiah($ord['totalHarga']) ?></span>
                        </div>
                        <div>
                            <a href="customer_invoice.php?order_id=<?= (int) $ord['id_order'] ?>" class="btn btn-outline">
                                📄 Lihat Struk / Invoice
                            </a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</body>
</html>
