-- Database: blue_bookstore
CREATE DATABASE IF NOT EXISTS blue_bookstore
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE blue_bookstore;

-- 1. Tabel users (Admin & Customer)
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nama VARCHAR(100) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin', 'customer') NOT NULL DEFAULT 'customer',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Tabel penulis
CREATE TABLE IF NOT EXISTS penulis (
    id_penulis INT AUTO_INCREMENT PRIMARY KEY,
    nama VARCHAR(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Tabel category
CREATE TABLE IF NOT EXISTS category (
    id_category INT AUTO_INCREMENT PRIMARY KEY,
    nama VARCHAR(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Tabel tb_buku
CREATE TABLE IF NOT EXISTS tb_buku (
    id_buku INT AUTO_INCREMENT PRIMARY KEY,
    judul VARCHAR(255) NOT NULL,
    id_penulis INT NOT NULL,
    id_category INT NOT NULL,
    harga DECIMAL(12,2) NOT NULL,
    stok INT NOT NULL DEFAULT 0,
    deskripsi TEXT NULL,
    gambar VARCHAR(255) NULL,
    KEY id_penulis (id_penulis),
    KEY id_category (id_category),
    CONSTRAINT fk_buku_penulis FOREIGN KEY (id_penulis) REFERENCES penulis (id_penulis) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_buku_category FOREIGN KEY (id_category) REFERENCES category (id_category) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Tabel payment
CREATE TABLE IF NOT EXISTS payment (
    id_payment INT AUTO_INCREMENT PRIMARY KEY,
    metode VARCHAR(50) NOT NULL,
    jumlah DECIMAL(12,2) NOT NULL,
    status VARCHAR(50) NOT NULL DEFAULT 'Lunas'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. Tabel order (Transaksi)
CREATE TABLE IF NOT EXISTS `order` (
    id_order INT AUTO_INCREMENT PRIMARY KEY,
    id_customer INT NOT NULL,
    id_payment INT NOT NULL,
    tanggal DATETIME DEFAULT CURRENT_TIMESTAMP,
    totalHarga DECIMAL(12,2) NOT NULL,
    KEY id_customer (id_customer),
    KEY id_payment (id_payment),
    CONSTRAINT fk_order_users FOREIGN KEY (id_customer) REFERENCES users (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_order_payment FOREIGN KEY (id_payment) REFERENCES payment (id_payment) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. Tabel orderdetail (Rincian Item Buku Transaksi)
CREATE TABLE IF NOT EXISTS orderdetail (
    id_order INT NOT NULL,
    id_buku INT NOT NULL,
    jumlah INT NOT NULL,
    harga DECIMAL(12,2) NOT NULL,
    subtotal DECIMAL(12,2) NOT NULL,
    PRIMARY KEY (id_order, id_buku),
    KEY id_buku (id_buku),
    CONSTRAINT fk_orderdetail_order FOREIGN KEY (id_order) REFERENCES `order` (id_order) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_orderdetail_buku FOREIGN KEY (id_buku) REFERENCES tb_buku (id_buku) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 8. Data Default Akun (Menggunakan Argon2id Hashing)
-- Password 'admin' dan 'user' di-hash menggunakan PHP PASSWORD_ARGON2ID
INSERT INTO users (id, nama, email, password, role) VALUES
(8, 'IniAdmin', 'admin@bluebookstore.id', '$argon2id$v=19$m=65536,t=4,p=1$Zk95Y2gveHBHVUUyMzlNbg$5TbiwTubCaXDuksU/8Po6h8bP56cPYSotNLoHbvfqP4', 'admin'),
(7, 'AkuUser', 'user@gmail.com', '$argon2id$v=19$m=65536,t=4,p=1$WDF2aVl3LzV2UGRsWG41Sg$O8xWqWbE/p7k9UomI+tX12H19kUvB81m/6KSmwM+iXo', 'customer')
ON DUPLICATE KEY UPDATE password = VALUES(password);

