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

// Buat folder uploads jika belum ada
$uploadDir = __DIR__ . '/uploads/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

// 1. PROSES POST (TAMBAH / EDIT / HAPUS)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $aksi = $_POST['aksi'] ?? '';

    // TAMBAH BUKU
    if ($aksi === 'tambah') {
        $judul = trim($_POST['judul'] ?? '');
        $id_penulis = (int) ($_POST['id_penulis'] ?? 0);
        $id_category = (int) ($_POST['id_category'] ?? 0);
        $harga = (float) ($_POST['harga'] ?? 0);
        $stok = (int) ($_POST['stok'] ?? 0);
        $deskripsi = trim($_POST['deskripsi'] ?? '');
        $namaGambar = '';

        if ($judul === '' || $id_penulis <= 0 || $id_category <= 0 || $harga <= 0) {
            $pesan = 'Judul, Penulis, Kategori, dan Harga harus diisi dengan benar.';
            $jenisPesan = 'error';
        } else {
            // Upload gambar jika ada
            if (!empty($_FILES['gambar']['name']) && $_FILES['gambar']['error'] === UPLOAD_ERR_OK) {
                $ext = strtolower(pathinfo($_FILES['gambar']['name'], PATHINFO_EXTENSION));
                $allowed = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
                if (in_array($ext, $allowed, true)) {
                    $namaGambar = 'book_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
                    move_uploaded_file($_FILES['gambar']['tmp_name'], $uploadDir . $namaGambar);
                }
            }

            $stmt = $koneksi->prepare("INSERT INTO tb_buku (judul, id_penulis, id_category, harga, stok, deskripsi, gambar) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("siidiss", $judul, $id_penulis, $id_category, $harga, $stok, $deskripsi, $namaGambar);
            if ($stmt->execute()) {
                $pesan = "Buku '$judul' berhasil ditambahkan!";
                $jenisPesan = "success";
            } else {
                $pesan = "Gagal menambahkan buku: " . $stmt->error;
                $jenisPesan = "error";
            }
            $stmt->close();
        }
    }

    // EDIT BUKU
    if ($aksi === 'edit') {
        $id_buku = (int) ($_POST['id_buku'] ?? 0);
        $judul = trim($_POST['judul'] ?? '');
        $id_penulis = (int) ($_POST['id_penulis'] ?? 0);
        $id_category = (int) ($_POST['id_category'] ?? 0);
        $harga = (float) ($_POST['harga'] ?? 0);
        $stok = (int) ($_POST['stok'] ?? 0);
        $deskripsi = trim($_POST['deskripsi'] ?? '');

        if ($id_buku <= 0 || $judul === '' || $id_penulis <= 0 || $id_category <= 0 || $harga <= 0) {
            $pesan = 'Semua data buku wajib diisi dengan benar.';
            $jenisPesan = 'error';
        } else {
            // Cek gambar lama
            $bukuLama = $koneksi->query("SELECT gambar FROM tb_buku WHERE id_buku = $id_buku")->fetch_assoc();
            $namaGambar = $bukuLama['gambar'] ?? '';

            if (!empty($_FILES['gambar']['name']) && $_FILES['gambar']['error'] === UPLOAD_ERR_OK) {
                $ext = strtolower(pathinfo($_FILES['gambar']['name'], PATHINFO_EXTENSION));
                $allowed = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
                if (in_array($ext, $allowed, true)) {
                    // Hapus gambar lama jika ada
                    if ($namaGambar !== '' && file_exists($uploadDir . $namaGambar)) {
                        @unlink($uploadDir . $namaGambar);
                    }
                    $namaGambar = 'book_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
                    move_uploaded_file($_FILES['gambar']['tmp_name'], $uploadDir . $namaGambar);
                }
            }

            $stmt = $koneksi->prepare("UPDATE tb_buku SET judul = ?, id_penulis = ?, id_category = ?, harga = ?, stok = ?, deskripsi = ?, gambar = ? WHERE id_buku = ?");
            $stmt->bind_param("siidissi", $judul, $id_penulis, $id_category, $harga, $stok, $deskripsi, $namaGambar, $id_buku);
            if ($stmt->execute()) {
                $pesan = "Data buku '$judul' berhasil diperbarui!";
                $jenisPesan = "success";
            } else {
                $pesan = "Gagal memperbarui buku: " . $stmt->error;
                $jenisPesan = "error";
            }
            $stmt->close();
        }
    }

    // HAPUS BUKU
    if ($aksi === 'hapus') {
        $id_buku = (int) ($_POST['id_buku'] ?? 0);
        if ($id_buku > 0) {
            // Ambil gambar untuk dihapus
            $bk = $koneksi->query("SELECT judul, gambar FROM tb_buku WHERE id_buku = $id_buku")->fetch_assoc();
            if ($bk) {
                if (!empty($bk['gambar']) && file_exists($uploadDir . $bk['gambar'])) {
                    @unlink($uploadDir . $bk['gambar']);
                }
                $koneksi->query("DELETE FROM tb_buku WHERE id_buku = $id_buku");
                $pesan = "Buku '" . $bk['judul'] . "' berhasil dihapus.";
                $jenisPesan = "success";
            }
        }
    }
}

// 2. DATA UNTUK FORM (PENULIS & KATEGORI)
$daftarPenulis = $koneksi->query("SELECT id_penulis, nama FROM penulis ORDER BY nama ASC");
$daftarKategori = $koneksi->query("SELECT id_category, nama FROM category ORDER BY nama ASC");

// 3. JIKA SEDANG EDIT
$editBuku = null;
if (isset($_GET['edit'])) {
    $idEdit = (int) $_GET['edit'];
    $resEdit = $koneksi->query("SELECT * FROM tb_buku WHERE id_buku = $idEdit");
    if ($resEdit && $resEdit->num_rows > 0) {
        $editBuku = $resEdit->fetch_assoc();
    }
}

// 4. FILTER DAN SEARCH BUKU
$keyword = trim($_GET['q'] ?? '');
$filterKat = (int) ($_GET['kategori'] ?? 0);

$queryBuku = "
    SELECT b.*, p.nama AS penulis_nama, c.nama AS kategori_nama
    FROM tb_buku b
    LEFT JOIN penulis p ON b.id_penulis = p.id_penulis
    LEFT JOIN category c ON b.id_category = c.id_category
    WHERE 1=1
";

if ($keyword !== '') {
    $kSafe = $koneksi->real_escape_string($keyword);
    $queryBuku .= " AND (b.judul LIKE '%$kSafe%' OR p.nama LIKE '%$kSafe%') ";
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
    <title>Kelola Buku | Admin Blue BookStore</title>
    <link rel="stylesheet" href="css/global.css">
    <link rel="stylesheet" href="css/navbar.css">
    <link rel="stylesheet" href="css/admin.css">
</head>
<body>
    <?php include __DIR__ . '/navbar/admin_navbar.php'; ?>

    <div class="container">
        <div class="page-header">
            <div>
                <h1>Kelola Buku</h1>
                <p>Tambah buku baru, perbarui informasi buku, atur stok dan harga, atau hapus buku.</p>
            </div>
            <?php if ($editBuku): ?>
                <a href="admin_buku.php" class="btn btn-secondary">&larr; Batal Edit & Tambah Buku Baru</a>
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
                    <h2><?= $editBuku ? 'Edit Data Buku' : 'Tambah Buku Baru' ?></h2>
                </div>
                <div class="card-body">
                    <form action="admin_buku.php" method="post" enctype="multipart/form-data">
                        <input type="hidden" name="aksi" value="<?= $editBuku ? 'edit' : 'tambah' ?>">
                        <?php if ($editBuku): ?>
                            <input type="hidden" name="id_buku" value="<?= (int) $editBuku['id_buku'] ?>">
                        <?php endif; ?>

                        <div class="form-group">
                            <label for="judul">Judul Buku *</label>
                            <input type="text" id="judul" name="judul" class="form-control" placeholder="Contoh: Laskar Pelangi" value="<?= e($editBuku['judul'] ?? '') ?>" required>
                        </div>

                        <div class="form-group">
                            <label for="id_penulis">Penulis *</label>
                            <select id="id_penulis" name="id_penulis" class="form-control" required>
                                <option value="">-- Pilih Penulis --</option>
                                <?php 
                                $daftarPenulis->data_seek(0);
                                while ($p = $daftarPenulis->fetch_assoc()): 
                                ?>
                                    <option value="<?= (int) $p['id_penulis'] ?>" <?= ($editBuku && $editBuku['id_penulis'] == $p['id_penulis']) ? 'selected' : '' ?>>
                                        <?= e($p['nama']) ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                            <small class="text-muted fs-xs">Penulis belum ada? <a href="admin_penulis.php" class="text-accent">Tambah di sini</a></small>
                        </div>

                        <div class="form-group">
                            <label for="id_category">Kategori *</label>
                            <select id="id_category" name="id_category" class="form-control" required>
                                <option value="">-- Pilih Kategori --</option>
                                <?php 
                                $daftarKategori->data_seek(0);
                                while ($c = $daftarKategori->fetch_assoc()): 
                                ?>
                                    <option value="<?= (int) $c['id_category'] ?>" <?= ($editBuku && $editBuku['id_category'] == $c['id_category']) ? 'selected' : '' ?>>
                                        <?= e($c['nama']) ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>

                        <div class="row-inputs">
                            <div class="form-group">
                                <label for="harga">Harga (Rp) *</label>
                                <input type="number" id="harga" name="harga" class="form-control" placeholder="85000" min="1000" step="500" value="<?= e($editBuku['harga'] ?? '') ?>" required>
                            </div>
                            <div class="form-group">
                                <label for="stok">Stok Buku *</label>
                                <input type="number" id="stok" name="stok" class="form-control" placeholder="10" min="0" value="<?= e($editBuku['stok'] ?? 10) ?>" required>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="deskripsi">Deskripsi / Sinopsis</label>
                            <textarea id="deskripsi" name="deskripsi" class="form-control" placeholder="Tuliskan gambaran singkat mengenai isi buku ini..."><?= e($editBuku['deskripsi'] ?? '') ?></textarea>
                        </div>

                        <div class="form-group">
                            <label for="gambar">Gambar Cover (Opsional)</label>
                            <input type="file" id="gambar" name="gambar" class="form-control" accept="image/*">
                            <?php if (!empty($editBuku['gambar'])): ?>
                                <small class="text-muted d-block mt-1">
                                    Gambar saat ini: <code><?= e($editBuku['gambar']) ?></code> (Biarkan kosong jika tidak ingin mengubah)
                                </small>
                            <?php endif; ?>
                        </div>

                        <button type="submit" class="btn btn-primary">
                            <?= $editBuku ? 'Simpan Perubahan' : '+ Tambahkan Buku' ?>
                        </button>
                    </form>
                </div>
            </div>

            <!-- PANEL TABEL DAFTAR BUKU -->
            <div class="card">
                <div class="card-header">
                    <h2>Daftar Koleksi Buku (<?= (int) $hasilBuku->num_rows ?>)</h2>
                </div>
                <div class="card-body">
                    <!-- Filter & Pencarian -->
                    <form method="get" action="admin_buku.php" class="filter-bar">
                        <input type="text" name="q" placeholder="Cari judul buku atau penulis..." value="<?= e($keyword) ?>">
                        <select name="kategori">
                            <option value="0">Semua Kategori</option>
                            <?php 
                            $daftarKategori->data_seek(0);
                            while ($c = $daftarKategori->fetch_assoc()): 
                            ?>
                                <option value="<?= (int) $c['id_category'] ?>" <?= ($filterKat == $c['id_category']) ? 'selected' : '' ?>>
                                    <?= e($c['nama']) ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                        <button type="submit" class="btn btn-secondary">Filter</button>
                        <?php if ($keyword !== '' || $filterKat > 0): ?>
                            <a href="admin_buku.php" class="btn btn-secondary">Reset</a>
                        <?php endif; ?>
                    </form>

                    <div class="table-responsive">
                        <table>
                            <thead>
                                <tr>
                                    <th>Cover</th>
                                    <th>Informasi Buku</th>
                                    <th>Harga</th>
                                    <th>Stok</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($hasilBuku->num_rows === 0): ?>
                                    <tr>
                                        <td colspan="5" class="table-empty-cell">
                                            Tidak ada buku ditemukan.
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php while ($b = $hasilBuku->fetch_assoc()): ?>
                                        <tr>
                                            <td>
                                                <?php if (!empty($b['gambar']) && file_exists($uploadDir . $b['gambar'])): ?>
                                                    <img src="uploads/<?= e($b['gambar']) ?>" alt="<?= e($b['judul']) ?>" class="book-thumb">
                                                <?php else: ?>
                                                    <div class="book-thumb-placeholder">BOOK</div>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <strong class="fs-base"><?= e($b['judul']) ?></strong><br>
                                                <small class="text-muted">Penulis: <?= e($b['penulis_nama'] ?? '-') ?></small><br>
                                                <span class="badge badge-category"><?= e($b['kategori_nama'] ?? 'Umum') ?></span>
                                            </td>
                                            <td>
                                                <strong><?= formatRupiah($b['harga']) ?></strong>
                                            </td>
                                            <td>
                                                <?php if ($b['stok'] > 5): ?>
                                                    <span class="badge badge-success"><?= (int) $b['stok'] ?> unit</span>
                                                <?php elseif ($b['stok'] > 0): ?>
                                                    <span class="badge badge-warning"><?= (int) $b['stok'] ?> unit</span>
                                                <?php else: ?>
                                                    <span class="badge badge-danger">Habis</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div class="actions-cell">
                                                    <a href="admin_buku.php?edit=<?= (int) $b['id_buku'] ?>" class="btn btn-warning" title="Edit Buku">Edit</a>
                                                    <form action="admin_buku.php" method="post" onsubmit="return confirm('Apakah Anda yakin ingin menghapus buku \'<?= addslashes(e($b['judul'])) ?>\'?');" class="d-inline">
                                                        <input type="hidden" name="aksi" value="hapus">
                                                        <input type="hidden" name="id_buku" value="<?= (int) $b['id_buku'] ?>">
                                                        <button type="submit" class="btn btn-danger" title="Hapus Buku">Hapus</button>
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
