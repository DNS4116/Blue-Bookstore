<?php
session_start();
require_once __DIR__ . '/koneksi.php';

// Verifikasi role admin
if (empty($_SESSION['user_id']) || ($_SESSION['user_role'] ?? '') !== 'admin') {
    header('Location: index.php');
    exit;
}

$pesan = '';
$jenisPesan = '';

// 1. UPDATE STATUS TRANSAKSI
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['aksi']) && $_POST['aksi'] === 'update_status') {
    $id_order = (int) ($_POST['id_order'] ?? 0);
    $status_baru = trim($_POST['status'] ?? '');
    $status_valid = ['Lunas', 'Diproses', 'Selesai', 'Dibatalkan', 'Menunggu Pembayaran'];

    if ($id_order > 0 && in_array($status_baru, $status_valid, true)) {
        // Ambil id_payment dari order
        $ord = $koneksi->query("SELECT id_payment FROM `order` WHERE id_order = $id_order")->fetch_assoc();
        if ($ord) {
            $id_payment = (int) $ord['id_payment'];
            $stmt = $koneksi->prepare("UPDATE payment SET status = ? WHERE id_payment = ?");
            $stmt->bind_param("si", $status_baru, $id_payment);
            if ($stmt->execute()) {
                $pesan = "Status transaksi #$id_order berhasil diperbarui menjadi '$status_baru'.";
                $jenisPesan = "success";
            } else {
                $pesan = "Gagal memperbarui status: " . $stmt->error;
                $jenisPesan = "error";
            }
            $stmt->close();
        }
    }
}

// 2. LIHAT DETAIL TRANSAKSI
$detailOrder = null;
$detailItems = [];
if (isset($_GET['detail'])) {
    $idDetail = (int) $_GET['detail'];
    $resOrder = $koneksi->query("
        SELECT o.id_order, o.tanggal, o.totalHarga, u.nama AS customer_nama, u.email AS customer_email, p.id_payment, p.metode, p.status
        FROM `order` o
        JOIN users u ON o.id_customer = u.id
        JOIN payment p ON o.id_payment = p.id_payment
        WHERE o.id_order = $idDetail
    ");
    if ($resOrder && $resOrder->num_rows > 0) {
        $detailOrder = $resOrder->fetch_assoc();
        $resItems = $koneksi->query("
            SELECT od.jumlah, od.harga, od.subtotal, b.judul, p.nama AS penulis_nama
            FROM orderdetail od
            JOIN tb_buku b ON od.id_buku = b.id_buku
            LEFT JOIN penulis p ON b.id_penulis = p.id_penulis
            WHERE od.id_order = $idDetail
        ");
        while ($it = $resItems->fetch_assoc()) {
            $detailItems[] = $it;
        }
    }
}

// 3. FILTER DAN SEARCH DAFTAR TRANSAKSI
$keyword = trim($_GET['q'] ?? '');
$filterStatus = trim($_GET['status'] ?? '');

$sql = "
    SELECT o.id_order, o.tanggal, o.totalHarga, u.nama AS customer_nama, u.email AS customer_email, p.metode, p.status,
           (SELECT COUNT(*) FROM orderdetail od WHERE od.id_order = o.id_order) AS total_item
    FROM `order` o
    JOIN users u ON o.id_customer = u.id
    JOIN payment p ON o.id_payment = p.id_payment
    WHERE 1=1
";

if ($keyword !== '') {
    $kSafe = $koneksi->real_escape_string($keyword);
    $sql .= " AND (u.nama LIKE '%$kSafe%' OR u.email LIKE '%$kSafe%' OR o.id_order = " . (int)$keyword . ") ";
}

if ($filterStatus !== '') {
    $sSafe = $koneksi->real_escape_string($filterStatus);
    $sql .= " AND p.status = '$sSafe' ";
}

$sql .= " ORDER BY o.tanggal DESC, o.id_order DESC ";
$hasilTransaksi = $koneksi->query($sql);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Riwayat Transaksi User | Admin Blue BookStore</title>
    <link rel="stylesheet" href="css/global.css">
    <link rel="stylesheet" href="css/navbar.css">
    <link rel="stylesheet" href="css/admin.css">
</head>
<body>
    <?php include __DIR__ . '/navbar/admin_navbar.php'; ?>

    <div class="container">
        <div class="page-header">
            <div>
                <h1>Riwayat Transaksi Pengguna</h1>
                <p>Pantau seluruh aktivitas pembelian buku oleh user, rincian pembayaran, dan kelola status order.</p>
            </div>
        </div>

        <?php if ($pesan !== ''): ?>
            <div class="alert alert-<?= $jenisPesan ?>">
                <span><?= e($pesan) ?></span>
            </div>
        <?php endif; ?>

        <!-- PANEL RINCIAN TRANSAKSI (JIKA DIPILIH) -->
        <?php if ($detailOrder): ?>
            <div class="detail-box">
                <div class="detail-box-header">
                    <h2>
                        Detail Pesanan #<?= (int) $detailOrder['id_order'] ?>
                    </h2>
                    <a href="admin_transaksi.php" class="btn btn-secondary">&times; Tutup Rincian</a>
                </div>

                <div class="detail-meta">
                    <div class="detail-meta-item">
                        <strong>Nama Pelanggan</strong>
                        <span><?= e($detailOrder['customer_nama']) ?></span>
                    </div>
                    <div class="detail-meta-item">
                        <strong>Email</strong>
                        <span><?= e($detailOrder['customer_email']) ?></span>
                    </div>
                    <div class="detail-meta-item">
                        <strong>Waktu Transaksi</strong>
                        <span><?= e($detailOrder['tanggal']) ?></span>
                    </div>
                    <div class="detail-meta-item">
                        <strong>Metode Pembayaran</strong>
                        <span><?= e($detailOrder['metode']) ?></span>
                    </div>
                    <div class="detail-meta-item">
                        <strong>Total Pembayaran</strong>
                        <span class="text-success"><?= formatRupiah($detailOrder['totalHarga']) ?></span>
                    </div>
                    <div class="detail-meta-item">
                        <strong>Status Saat Ini</strong>
                        <span>
                            <?php
                            $st = strtolower($detailOrder['status']);
                            $bClass = 'badge-success';
                            if ($st === 'diproses' || $st === 'menunggu pembayaran') $bClass = 'badge-warning';
                            if ($st === 'dibatalkan') $bClass = 'badge-danger';
                            ?>
                            <span class="badge <?= $bClass ?>"><?= e($detailOrder['status']) ?></span>
                        </span>
                    </div>
                </div>

                <h3 class="detail-subheading">Daftar Item Buku yang Dipesan:</h3>
                <div class="table-responsive detail-table-card">
                    <table>
                        <thead>
                            <tr>
                                <th>Judul Buku</th>
                                <th>Penulis</th>
                                <th>Harga Satuan</th>
                                <th>Jumlah (Qty)</th>
                                <th>Subtotal</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($detailItems as $item): ?>
                                <tr>
                                    <td><strong><?= e($item['judul']) ?></strong></td>
                                    <td><?= e($item['penulis_nama'] ?? '-') ?></td>
                                    <td><?= formatRupiah($item['harga']) ?></td>
                                    <td><?= (int) $item['jumlah'] ?> pcs</td>
                                    <td><strong><?= formatRupiah($item['subtotal']) ?></strong></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Form Ubah Status -->
                <form action="admin_transaksi.php<?= isset($_GET['q']) ? '?q=' . urlencode($_GET['q']) : '' ?>" method="post" class="status-form-bar">
                    <input type="hidden" name="aksi" value="update_status">
                    <input type="hidden" name="id_order" value="<?= (int) $detailOrder['id_order'] ?>">
                    <strong class="fs-sm">Ubah Status Pesanan:</strong>
                    <select name="status" class="status-select">
                        <option value="Lunas" <?= $detailOrder['status'] === 'Lunas' ? 'selected' : '' ?>>Lunas</option>
                        <option value="Diproses" <?= $detailOrder['status'] === 'Diproses' ? 'selected' : '' ?>>Diproses</option>
                        <option value="Selesai" <?= $detailOrder['status'] === 'Selesai' ? 'selected' : '' ?>>Selesai</option>
                        <option value="Dibatalkan" <?= $detailOrder['status'] === 'Dibatalkan' ? 'selected' : '' ?>>Dibatalkan</option>
                        <option value="Menunggu Pembayaran" <?= $detailOrder['status'] === 'Menunggu Pembayaran' ? 'selected' : '' ?>>Menunggu Pembayaran</option>
                    </select>
                    <button type="submit" class="btn btn-primary">Simpan Status</button>
                </form>
            </div>
        <?php endif; ?>

        <!-- TABEL SEMUA TRANSAKSI -->
        <div class="card">
            <div class="card-header">
                <h2>Semua Transaksi Masuk (<?= (int) $hasilTransaksi->num_rows ?>)</h2>
            </div>
            <div class="card-body">
                <form method="get" action="admin_transaksi.php" class="filter-bar">
                    <input type="text" name="q" placeholder="Cari nama pelanggan, email, atau ID Order..." value="<?= e($keyword) ?>">
                    <select name="status">
                        <option value="">Semua Status</option>
                        <option value="Lunas" <?= $filterStatus === 'Lunas' ? 'selected' : '' ?>>Lunas</option>
                        <option value="Diproses" <?= $filterStatus === 'Diproses' ? 'selected' : '' ?>>Diproses</option>
                        <option value="Selesai" <?= $filterStatus === 'Selesai' ? 'selected' : '' ?>>Selesai</option>
                        <option value="Dibatalkan" <?= $filterStatus === 'Dibatalkan' ? 'selected' : '' ?>>Dibatalkan</option>
                    </select>
                    <button type="submit" class="btn btn-secondary">Filter</button>
                    <?php if ($keyword !== '' || $filterStatus !== ''): ?>
                        <a href="admin_transaksi.php" class="btn btn-secondary">Reset</a>
                    <?php endif; ?>
                </form>

                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>ID Order</th>
                                <th>Tanggal & Waktu</th>
                                <th>Pelanggan</th>
                                <th>Total Belanja</th>
                                <th>Metode Bayar</th>
                                <th>Status</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($hasilTransaksi->num_rows === 0): ?>
                                <tr>
                                    <td colspan="7" class="table-empty-cell">
                                        Belum ada riwayat transaksi yang cocok.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php while ($t = $hasilTransaksi->fetch_assoc()): ?>
                                    <tr>
                                        <td><strong>#<?= (int) $t['id_order'] ?></strong></td>
                                        <td><?= e($t['tanggal']) ?></td>
                                        <td>
                                            <strong><?= e($t['customer_nama']) ?></strong><br>
                                            <small class="text-muted"><?= e($t['customer_email']) ?></small>
                                        </td>
                                        <td>
                                            <strong class="text-primary"><?= formatRupiah($t['totalHarga']) ?></strong><br>
                                            <small class="text-muted"><?= (int) $t['total_item'] ?> judul buku</small>
                                        </td>
                                        <td><?= e($t['metode']) ?></td>
                                        <td>
                                            <?php
                                            $st = strtolower($t['status']);
                                            $bClass = 'badge-success';
                                            if ($st === 'diproses' || $st === 'menunggu pembayaran') $bClass = 'badge-warning';
                                            if ($st === 'dibatalkan') $bClass = 'badge-danger';
                                            ?>
                                            <span class="badge <?= $bClass ?>"><?= e($t['status']) ?></span>
                                        </td>
                                        <td>
                                            <a href="admin_transaksi.php?detail=<?= (int) $t['id_order'] ?>" class="btn btn-info">
                                                Lihat Rincian
                                            </a>
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
