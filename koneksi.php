<?php
$host = "localhost";
$user = "root";
$pass = "";
$db = "blue_bookstore";

// Membuat koneksi PHP ke database MySQL pada XAMPP.
$koneksi = new mysqli($host, $user, $pass, $db);

if ($koneksi->connect_error) {
    die("Koneksi database gagal: " . $koneksi->connect_error);
}

$koneksi->set_charset("utf8mb4");

// 1. Pastikan tabel users tersedia
$koneksi->query("CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nama VARCHAR(100) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin', 'customer') NOT NULL DEFAULT 'customer',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// 2. Pastikan tabel penulis tersedia
$koneksi->query("CREATE TABLE IF NOT EXISTS penulis (
    id_penulis INT AUTO_INCREMENT PRIMARY KEY,
    nama VARCHAR(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// 3. Pastikan tabel category tersedia
$koneksi->query("CREATE TABLE IF NOT EXISTS category (
    id_category INT AUTO_INCREMENT PRIMARY KEY,
    nama VARCHAR(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// 4. Pastikan tabel tb_buku tersedia
$koneksi->query("CREATE TABLE IF NOT EXISTS tb_buku (
    id_buku INT AUTO_INCREMENT PRIMARY KEY,
    judul VARCHAR(255) NOT NULL,
    id_penulis INT NOT NULL,
    harga DECIMAL(12,2) NOT NULL,
    stok INT NOT NULL DEFAULT 0,
    id_category INT NOT NULL,
    deskripsi TEXT NULL,
    gambar VARCHAR(255) NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// Periksa kolom deskripsi dan gambar pada tb_buku jika tabel lama
$colDesk = $koneksi->query("SHOW COLUMNS FROM `tb_buku` LIKE 'deskripsi'");
if ($colDesk && $colDesk->num_rows === 0) {
    $koneksi->query("ALTER TABLE `tb_buku` ADD COLUMN `deskripsi` TEXT NULL AFTER `stok`");
}
if ($colDesk) $colDesk->free();

$colGbr = $koneksi->query("SHOW COLUMNS FROM `tb_buku` LIKE 'gambar'");
if ($colGbr && $colGbr->num_rows === 0) {
    $koneksi->query("ALTER TABLE `tb_buku` ADD COLUMN `gambar` VARCHAR(255) NULL AFTER `deskripsi`");
}
if ($colGbr) $colGbr->free();

// 5. Pastikan tabel payment tersedia
$koneksi->query("CREATE TABLE IF NOT EXISTS payment (
    id_payment INT AUTO_INCREMENT PRIMARY KEY,
    metode VARCHAR(50) NOT NULL,
    jumlah DECIMAL(12,2) NOT NULL,
    status VARCHAR(50) NOT NULL DEFAULT 'Lunas'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// 6. Pastikan tabel order tersedia
$koneksi->query("CREATE TABLE IF NOT EXISTS `order` (
    id_order INT AUTO_INCREMENT PRIMARY KEY,
    id_customer INT NOT NULL,
    id_payment INT NOT NULL,
    tanggal DATETIME DEFAULT CURRENT_TIMESTAMP,
    totalHarga DECIMAL(12,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// 7. Pastikan tabel orderdetail tersedia
$koneksi->query("CREATE TABLE IF NOT EXISTS orderdetail (
    id_order INT NOT NULL,
    id_buku INT NOT NULL,
    jumlah INT NOT NULL,
    harga DECIMAL(12,2) NOT NULL,
    subtotal DECIMAL(12,2) NOT NULL,
    PRIMARY KEY (id_order, id_buku)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// Pastikan foreign key order mengarah ke users(id)
$fkCheck = $koneksi->query("
    SELECT CONSTRAINT_NAME, REFERENCED_TABLE_NAME 
    FROM information_schema.KEY_COLUMN_USAGE 
    WHERE TABLE_SCHEMA = 'blue_bookstore' 
      AND TABLE_NAME = 'order' 
      AND COLUMN_NAME = 'id_customer' 
      AND REFERENCED_TABLE_NAME IS NOT NULL
");
if ($fkCheck && $row = $fkCheck->fetch_assoc()) {
    if ($row['REFERENCED_TABLE_NAME'] !== 'users') {
        $cName = $row['CONSTRAINT_NAME'];
        $koneksi->query("ALTER TABLE `order` DROP FOREIGN KEY `$cName`");
        $koneksi->query("ALTER TABLE `order` ADD CONSTRAINT `fk_order_users` FOREIGN KEY (`id_customer`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE");
    }
}
if ($fkCheck) $fkCheck->free();

// Helper functions global
if (!function_exists('e')) {
    function e($value): string {
        return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('formatRupiah')) {
    function formatRupiah($angka): string {
        return 'Rp ' . number_format((float) $angka, 0, ',', '.');
    }
}

if (!function_exists('getCartCount')) {
    function getCartCount(): int {
        if (!isset($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
            return 0;
        }
        $count = 0;
        foreach ($_SESSION['cart'] as $item) {
            $count += (int) ($item['qty'] ?? 0);
        }
        return $count;
    }
}

// -------------------------------------------------------------
// Konfigurasi & Helper Argon2id untuk Keamanan Password
// -------------------------------------------------------------
if (!defined('ARGON2_OPTIONS')) {
    define('ARGON2_OPTIONS', [
        'memory_cost' => 65536, // 64 MB
        'time_cost'   => 4,     // 4 iterasi
        'threads'     => 1      // 1 thread
    ]);
}

if (!function_exists('hashPasswordArgon2id')) {
    function hashPasswordArgon2id(string $password): string {
        if (defined('PASSWORD_ARGON2ID')) {
            return password_hash($password, PASSWORD_ARGON2ID, ARGON2_OPTIONS);
        }
        return password_hash($password, PASSWORD_DEFAULT);
    }
}

if (!function_exists('verifyPasswordArgon2id')) {
    function verifyPasswordArgon2id(string $password, string $hash): bool {
        return password_verify($password, $hash);
    }
}

if (!function_exists('needsRehashToArgon2id')) {
    function needsRehashToArgon2id(string $hash): bool {
        if (defined('PASSWORD_ARGON2ID')) {
            return password_needs_rehash($hash, PASSWORD_ARGON2ID, ARGON2_OPTIONS);
        }
        return password_needs_rehash($hash, PASSWORD_DEFAULT);
    }
}

// Pastikan akun default terdaftar dan menggunakan hash Argon2id
$cekAdmin = $koneksi->query("SELECT id, password FROM users WHERE email = 'admin@bluebookstore.id'");
if ($cekAdmin && $adminRow = $cekAdmin->fetch_assoc()) {
    if (needsRehashToArgon2id($adminRow['password'])) {
        $argonAdmin = hashPasswordArgon2id('admin');
        $idA = (int) $adminRow['id'];
        $stmtUpAdmin = $koneksi->prepare("UPDATE users SET password = ? WHERE id = ?");
        $stmtUpAdmin->bind_param("si", $argonAdmin, $idA);
        $stmtUpAdmin->execute();
        $stmtUpAdmin->close();
    }
} elseif ($cekAdmin && $cekAdmin->num_rows === 0) {
    $argonAdmin = hashPasswordArgon2id('admin');
    $koneksi->query("INSERT INTO users (nama, email, password, role) VALUES ('IniAdmin', 'admin@bluebookstore.id', '$argonAdmin', 'admin')");
}
if ($cekAdmin) $cekAdmin->free();

$cekUser = $koneksi->query("SELECT id, password FROM users WHERE email = 'user@gmail.com'");
if ($cekUser && $userRow = $cekUser->fetch_assoc()) {
    if (needsRehashToArgon2id($userRow['password'])) {
        $argonUser = hashPasswordArgon2id('user');
        $idU = (int) $userRow['id'];
        $stmtUpUser = $koneksi->prepare("UPDATE users SET password = ? WHERE id = ?");
        $stmtUpUser->bind_param("si", $argonUser, $idU);
        $stmtUpUser->execute();
        $stmtUpUser->close();
    }
} elseif ($cekUser && $cekUser->num_rows === 0) {
    $argonUser = hashPasswordArgon2id('user');
    $koneksi->query("INSERT INTO users (nama, email, password, role) VALUES ('AkuUser', 'user@gmail.com', '$argonUser', 'customer')");
}
if ($cekUser) $cekUser->free();
?>