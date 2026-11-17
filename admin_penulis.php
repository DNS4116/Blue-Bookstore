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

// 1. PROSES POST (TAMBAH / EDIT / HAPUS)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $aksi = $_POST['aksi'] ?? '';

    // TAMBAH PENULIS
    if ($aksi === 'tambah') {
        $nama = trim($_POST['nama'] ?? '');
        if ($nama === '') {
            $pesan = 'Nama penulis tidak boleh kosong.';
            $jenisPesan = 'error';
        } else {
            $stmt = $koneksi->prepare("INSERT INTO penulis (nama) VALUES (?)");
            $stmt->bind_param("s", $nama);
            if ($stmt->execute()) {
                $pesan = "Penulis '$nama' berhasil ditambahkan!";
                $jenisPesan = "success";
            } else {
                $pesan = "Gagal menambahkan penulis: " . $stmt->error;
                $jenisPesan = "error";
            }
            $stmt->close();
        }
    }

    // EDIT PENULIS
    if ($aksi === 'edit') {
        $id_penulis = (int) ($_POST['id_penulis'] ?? 0);
        $nama = trim($_POST['nama'] ?? '');
        if ($id_penulis <= 0 || $nama === '') {
            $pesan = 'Nama penulis tidak boleh kosong.';
            $jenisPesan = 'error';
        } else {
            $stmt = $koneksi->prepare("UPDATE penulis SET nama = ? WHERE id_penulis = ?");
            $stmt->bind_param("si", $nama, $id_penulis);
            if ($stmt->execute()) {
                $pesan = "Nama penulis berhasil diperbarui!";
                $jenisPesan = "success";
            } else {
                $pesan = "Gagal memperbarui penulis: " . $stmt->error;
                $jenisPesan = "error";
            }
            $stmt->close();
        }
    }

    // HAPUS PENULIS
    if ($aksi === 'hapus') {
        $id_penulis = (int) ($_POST['id_penulis'] ?? 0);
        if ($id_penulis > 0) {
            // Cek apakah penulis masih memiliki buku
            $cekBuku = $koneksi->query("SELECT COUNT(*) AS total FROM tb_buku WHERE id_penulis = $id_penulis")->fetch_assoc()['total'];
            if ($cekBuku > 0) {
                $pesan = "Penulis tidak dapat dihapus karena masih memiliki $cekBuku buku terdaftar di katalog. Hapus atau pindahkan buku terlebih dahulu.";
                $jenisPesan = "error";
            } else {
                $p = $koneksi->query("SELECT nama FROM penulis WHERE id_penulis = $id_penulis")->fetch_assoc();
                $koneksi->query("DELETE FROM penulis WHERE id_penulis = $id_penulis");
                $pesan = "Penulis '" . ($p['nama'] ?? '') . "' berhasil dihapus.";
                $jenisPesan = "success";
            }
        }
    }
}

// 2. DATA EDIT
$editPenulis = null;
if (isset($_GET['edit'])) {
    $idEdit = (int) $_GET['edit'];
    $resEdit = $koneksi->query("SELECT * FROM penulis WHERE id_penulis = $idEdit");
    if ($resEdit && $resEdit->num_rows > 0) {
        $editPenulis = $resEdit->fetch_assoc();
    }
}

// 3. DAFTAR PENULIS + JUMLAH BUKU
$keyword = trim($_GET['q'] ?? '');
$sql = "
    SELECT p.id_penulis, p.nama, COUNT(b.id_buku) AS jumlah_buku
    FROM penulis p
    LEFT JOIN tb_buku b ON p.id_penulis = b.id_penulis
";

if ($keyword !== '') {
    $kSafe = $koneksi->real_escape_string($keyword);
    $sql .= " WHERE p.nama LIKE '%$kSafe%' ";
}

$sql .= " GROUP BY p.id_penulis, p.nama ORDER BY p.nama ASC ";
$hasilPenulis = $koneksi->query($sql);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Penulis | Admin Blue BookStore</title>
    <link rel="stylesheet" href="css/global.css">
    <link rel="stylesheet" href="css/navbar.css">
    <link rel="stylesheet" href="css/admin.css">
</head>
<body>
    <?php include __DIR__ . '/navbar/admin_navbar.php'; ?>

    <div class="container">
        <div class="page-header">
            <div>
                <h1>Kelola Penulis</h1>
                <p>Tambah penulis baru, perbarui nama penulis, dan pantau buku karya masing-masing penulis.</p>
            </div>
            <?php if ($editPenulis): ?>
                <a href="admin_penulis.php" class="btn btn-secondary">&larr; Batal & Tambah Penulis Baru</a>
            <?php endif; ?>
        </div>

        <?php if ($pesan !== ''): ?>
            <div class="alert alert-<?= $jenisPesan ?>">
                <span><?= e($pesan) ?></span>
            </div>
        <?php endif; ?>

        <div class="content-grid">
            <!-- PANEL FORM (TAMBAH / EDIT) -->
            <div class="card">
                <div class="card-header">
                    <h2><?= $editPenulis ? 'Edit Nama Penulis' : 'Tambah Penulis Baru' ?></h2>
                </div>
                <div class="card-body">
                    <form action="admin_penulis.php" method="post">
                        <input type="hidden" name="aksi" value="<?= $editPenulis ? 'edit' : 'tambah' ?>">
                        <?php if ($editPenulis): ?>
                            <input type="hidden" name="id_penulis" value="<?= (int) $editPenulis['id_penulis'] ?>">
                        <?php endif; ?>

                        <div class="form-group">
                            <label for="nama">Nama Lengkap Penulis *</label>
                            <input type="text" id="nama" name="nama" class="form-control" placeholder="Contoh: Andrea Hirata" value="<?= e($editPenulis['nama'] ?? '') ?>" required>
                        </div>

                        <button type="submit" class="btn btn-primary">
                            <?= $editPenulis ? 'Simpan Perubahan' : '+ Tambah Penulis' ?>
                        </button>
                    </form>
                </div>
            </div>

            <!-- PANEL TABEL PENULIS -->
            <div class="card">
                <div class="card-header">
                    <h2>Daftar Penulis (<?= (int) $hasilPenulis->num_rows ?>)</h2>
                </div>
                <div class="card-body">
                    <form method="get" action="admin_penulis.php" class="filter-bar">
                        <input type="text" name="q" placeholder="Cari nama penulis..." value="<?= e($keyword) ?>">
                        <button type="submit" class="btn btn-secondary">Cari</button>
                        <?php if ($keyword !== ''): ?>
                            <a href="admin_penulis.php" class="btn btn-secondary">Reset</a>
                        <?php endif; ?>
                    </form>

                    <div class="table-responsive">
                        <table>
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Nama Penulis</th>
                                    <th>Koleksi Buku</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($hasilPenulis->num_rows === 0): ?>
                                    <tr>
                                        <td colspan="4" class="table-empty-cell">
                                            Tidak ada penulis ditemukan.
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php while ($p = $hasilPenulis->fetch_assoc()): ?>
                                        <tr>
                                            <td><strong>#<?= (int) $p['id_penulis'] ?></strong></td>
                                            <td>
                                                <strong class="fs-base"><?= e($p['nama']) ?></strong>
                                            </td>
                                            <td>
                                                <span class="badge badge-info"><?= (int) $p['jumlah_buku'] ?> buku</span>
                                            </td>
                                            <td>
                                                <div class="actions-cell">
                                                    <a href="admin_penulis.php?edit=<?= (int) $p['id_penulis'] ?>" class="btn btn-warning" title="Edit Penulis">Edit</a>
                                                    <form action="admin_penulis.php" method="post" onsubmit="return confirm('Apakah Anda yakin ingin menghapus penulis \'<?= addslashes(e($p['nama'])) ?>\'?');" class="d-inline">
                                                        <input type="hidden" name="aksi" value="hapus">
                                                        <input type="hidden" name="id_penulis" value="<?= (int) $p['id_penulis'] ?>">
                                                        <button type="submit" class="btn btn-danger" title="Hapus Penulis">Hapus</button>
                                                    </form>
                                                </div>
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
    </div>
</body>
</html>
