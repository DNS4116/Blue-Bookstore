# -*- coding: utf-8 -*-
import os
import docx
from docx.shared import Inches, Pt, RGBColor
from docx.enum.text import WD_ALIGN_PARAGRAPH
from docx.enum.table import WD_TABLE_ALIGNMENT
from docx.oxml import OxmlElement, parse_xml
from docx.oxml.ns import nsdecls, qn

def set_cell_background(cell, hex_color):
    shading_elm = parse_xml(f'<w:shd {nsdecls("w")} w:fill="{hex_color}"/>')
    cell._tc.get_or_add_tcPr().append(shading_elm)

def set_cell_margins(cell, top=100, bottom=100, left=150, right=150):
    tcPr = cell._tc.get_or_add_tcPr()
    tcMar = OxmlElement('w:tcMar')
    for m, val in [('w:top', top), ('w:bottom', bottom), ('w:left', left), ('w:right', right)]:
        node = OxmlElement(m)
        node.set(qn('w:w'), str(val))
        node.set(qn('w:type'), 'dxa')
        tcMar.append(node)
    tcPr.append(tcMar)

def create_document():
    doc = docx.Document()

    # Set page margins to 1 inch
    sections = doc.sections
    for section in sections:
        section.top_margin = Inches(1)
        section.bottom_margin = Inches(1)
        section.left_margin = Inches(1)
        section.right_margin = Inches(1)

    # Base styles
    normal_style = doc.styles['Normal']
    normal_style.font.name = 'Calibri'
    normal_style.font.size = Pt(10.5)
    normal_style.font.color.rgb = RGBColor(15, 23, 42) # Slate 900

    primary_color = RGBColor(7, 89, 133)   # Navy Blue #075985
    accent_color = RGBColor(2, 132, 199)   # Blue #0284c7
    dark_color = RGBColor(15, 23, 42)
    muted_color = RGBColor(100, 116, 139) # #64748b

    # Helper functions
    def add_title_block(title, subtitle, meta_info):
        p_title = doc.add_paragraph()
        p_title.alignment = WD_ALIGN_PARAGRAPH.CENTER
        p_title.paragraph_format.space_before = Pt(36)
        p_title.paragraph_format.space_after = Pt(12)
        run_title = p_title.add_run(title)
        run_title.font.name = 'Arial'
        run_title.font.size = Pt(24)
        run_title.font.bold = True
        run_title.font.color.rgb = primary_color

        p_sub = doc.add_paragraph()
        p_sub.alignment = WD_ALIGN_PARAGRAPH.CENTER
        p_sub.paragraph_format.space_after = Pt(24)
        run_sub = p_sub.add_run(subtitle)
        run_sub.font.name = 'Calibri'
        run_sub.font.size = Pt(13)
        run_sub.font.italic = True
        run_sub.font.color.rgb = muted_color

        # Meta table / box
        table = doc.add_table(rows=len(meta_info), cols=2)
        table.alignment = WD_TABLE_ALIGNMENT.CENTER
        for i, (k, v) in enumerate(meta_info):
            cell_k = table.rows[i].cells[0]
            cell_v = table.rows[i].cells[1]
            cell_k.width = Inches(2.2)
            cell_v.width = Inches(4.3)
            set_cell_background(cell_k, 'F1F5F9')
            set_cell_background(cell_v, 'FFFFFF')
            set_cell_margins(cell_k, 60, 60, 120, 120)
            set_cell_margins(cell_v, 60, 60, 120, 120)

            pk = cell_k.paragraphs[0]
            pk.paragraph_format.space_after = Pt(2)
            rk = pk.add_run(k)
            rk.font.bold = True
            rk.font.size = Pt(9.5)
            rk.font.color.rgb = primary_color

            pv = cell_v.paragraphs[0]
            pv.paragraph_format.space_after = Pt(2)
            rv = pv.add_run(v)
            rv.font.size = Pt(9.5)

        doc.add_page_break()

    def add_h1(text):
        p = doc.add_paragraph()
        p.paragraph_format.space_before = Pt(20)
        p.paragraph_format.space_after = Pt(8)
        p.paragraph_format.keep_with_next = True
        run = p.add_run(text)
        run.font.name = 'Arial'
        run.font.size = Pt(16)
        run.font.bold = True
        run.font.color.rgb = primary_color
        return p

    def add_h2(text):
        p = doc.add_paragraph()
        p.paragraph_format.space_before = Pt(14)
        p.paragraph_format.space_after = Pt(6)
        p.paragraph_format.keep_with_next = True
        run = p.add_run(text)
        run.font.name = 'Arial'
        run.font.size = Pt(13)
        run.font.bold = True
        run.font.color.rgb = accent_color
        return p

    def add_h3(text):
        p = doc.add_paragraph()
        p.paragraph_format.space_before = Pt(10)
        p.paragraph_format.space_after = Pt(4)
        p.paragraph_format.keep_with_next = True
        run = p.add_run(text)
        run.font.name = 'Calibri'
        run.font.size = Pt(11)
        run.font.bold = True
        run.font.color.rgb = dark_color
        return p

    def add_p(text, bold_prefix=None, italic=False):
        p = doc.add_paragraph()
        p.paragraph_format.space_after = Pt(6)
        p.paragraph_format.line_spacing = 1.15
        if bold_prefix:
            r_pre = p.add_run(bold_prefix + ' ')
            r_pre.font.bold = True
        r = p.add_run(text)
        if italic:
            r.font.italic = True
        return p

    def add_bullet(text, bold_prefix=None):
        p = doc.add_paragraph(style='List Bullet')
        p.paragraph_format.space_after = Pt(3)
        p.paragraph_format.line_spacing = 1.15
        if bold_prefix:
            r_pre = p.add_run(bold_prefix + ' ')
            r_pre.font.bold = True
        p.add_run(text)
        return p

    def add_callout(text, title="CATATAN TEKNIS PENTING", callout_type="info"):
        tbl = doc.add_table(rows=1, cols=1)
        tbl.alignment = WD_TABLE_ALIGNMENT.CENTER
        cell = tbl.cell(0, 0)
        cell.width = Inches(6.5)

        bg_col = "F0F9FF" if callout_type == "info" else "FEF3C7"
        border_col = primary_color if callout_type == "info" else RGBColor(180, 83, 9)

        set_cell_background(cell, bg_col)
        set_cell_margins(cell, top=140, bottom=140, left=180, right=180)

        p = cell.paragraphs[0]
        p.paragraph_format.space_after = Pt(4)
        r_t = p.add_run(f"📌 {title}: ")
        r_t.font.bold = True
        r_t.font.size = Pt(9.5)
        r_t.font.color.rgb = border_col

        r_body = p.add_run(text)
        r_body.font.size = Pt(9.5)

        p_after = doc.add_paragraph()
        p_after.paragraph_format.space_after = Pt(4)

    def add_code_block(code_text, caption=None):
        if caption:
            p_cap = doc.add_paragraph()
            p_cap.paragraph_format.space_before = Pt(6)
            p_cap.paragraph_format.space_after = Pt(2)
            p_cap.paragraph_format.keep_with_next = True
            r_cap = p_cap.add_run(f"Snippet Kode: {caption}")
            r_cap.font.name = 'Calibri'
            r_cap.font.size = Pt(9)
            r_cap.font.bold = True
            r_cap.font.color.rgb = primary_color

        tbl = doc.add_table(rows=1, cols=1)
        tbl.alignment = WD_TABLE_ALIGNMENT.CENTER
        cell = tbl.cell(0, 0)
        cell.width = Inches(6.5)
        set_cell_background(cell, 'F8FAFC')
        set_cell_margins(cell, top=100, bottom=100, left=140, right=140)

        p = cell.paragraphs[0]
        p.paragraph_format.space_after = Pt(0)
        p.paragraph_format.line_spacing = 1.05

        run = p.add_run(code_text.strip())
        run.font.name = 'Consolas'
        run.font.size = Pt(8.5)
        run.font.color.rgb = RGBColor(30, 41, 59)

        p_after = doc.add_paragraph()
        p_after.paragraph_format.space_after = Pt(6)

    def add_table_data(headers, data, col_widths=None):
        tbl = doc.add_table(rows=len(data) + 1, cols=len(headers))
        tbl.alignment = WD_TABLE_ALIGNMENT.CENTER

        # Header row
        hdr_cells = tbl.rows[0].cells
        for idx, h in enumerate(headers):
            cell = hdr_cells[idx]
            if col_widths and idx < len(col_widths):
                cell.width = col_widths[idx]
            set_cell_background(cell, '075985')
            set_cell_margins(cell, 100, 100, 140, 140)
            p = cell.paragraphs[0]
            p.paragraph_format.space_after = Pt(0)
            run = p.add_run(h)
            run.font.name = 'Arial'
            run.font.bold = True
            run.font.size = Pt(9)
            run.font.color.rgb = RGBColor(255, 255, 255)

        # Data rows
        for r_idx, row in enumerate(data):
            row_cells = tbl.rows[r_idx + 1].cells
            bg_color = 'FFFFFF' if r_idx % 2 == 0 else 'F8FAFC'
            for c_idx, val in enumerate(row):
                cell = row_cells[c_idx]
                if col_widths and c_idx < len(col_widths):
                    cell.width = col_widths[c_idx]
                set_cell_background(cell, bg_color)
                set_cell_margins(cell, 80, 80, 120, 120)
                p = cell.paragraphs[0]
                p.paragraph_format.space_after = Pt(0)
                p.paragraph_format.line_spacing = 1.1
                run = p.add_run(str(val))
                run.font.size = Pt(8.5)

        p_after = doc.add_paragraph()
        p_after.paragraph_format.space_after = Pt(6)

    # ==========================
    # 1. COVER PAGE
    # ==========================
    add_title_block(
        title="DOKUMENTASI TEKNIS & KODE SUMBER\nPROYEK BLUE BOOKSTORE",
        subtitle="Analisis Komprehensif Arsitektur Aplikasi, Alur Autentikasi, Alur Pembelian Pelanggan, Modul Transaksi QRIS, serta Panel Manajemen Administrator",
        meta_info=[
            ("Nama Proyek", "Blue BookStore (E-Commerce Penjualan Buku Modern)"),
            ("Stack Teknologi", "PHP Native 8.x, MySQL/MariaDB, Apache/XAMPP, CSS3"),
            ("Lingkungan Kerja", "F:\\Program Files\\XAMPP\\htdocs\\blue-BookStore"),
            ("Basis Data", "MySQL / MariaDB (`blue_bookstore`)"),
            ("Kategori Dokumentasi", "Software Architecture & Code Walkthrough Document"),
            ("Target Pengguna", "Pengembang Sistem, Reviewer Kode, dan Tim Penguji Aplikasi"),
            ("Status Rilis", "Versi 1.0 (Stabil & Terintegrasi QRIS)")
        ]
    )

    # ==========================
    # 2. DAFTAR ISI & STRUKTUR DOKUMEN
    # ==========================
    add_h1("DAFTAR ISI DAN STRUKTUR DOKUMENTASI")
    add_p("Dokumentasi teknis ini disusun secara hierarkis dan kronologis mengikuti urutan siklus hidup (lifecycle) penggunaan aplikasi yang sebenarnya, mulai dari registrasi dan login, alur transaksi belanja customer dari katalog hingga invoice, hingga pengelolaan katalog buku, penulis, dan riwayat pesanan oleh administrator.")

    doc_index = [
        ("BAB I", "Ringkasan Eksekutif & Struktur Direktori Proyek"),
        ("BAB II", "Skema Basis Data & Relasi Entitas (Database Schema)"),
        ("BAB III", "Konfigurasi Koneksi & Database Engine (koneksi.php)"),
        ("BAB IV", "Modul Autentikasi: Login, Registrasi & Manajemen Sesi (index.php, logout.php, home.php)"),
        ("BAB V", "Alur Customer: Dari Katalog, Keranjang, Transaksi Hingga Invoice"),
        ("  5.1", "Halaman Utama & Katalog Buku Customer (customer.php)"),
        ("  5.2", "Manajemen Keranjang Belanja & Sinkronisasi Stok (customer_cart.php)"),
        ("  5.3", "Alur Checkout & Transaksi QRIS Eksklusif (customer_checkout.php)"),
        ("  5.4", "Penerbitan Bukti Pembayaran / Invoice Cetak (customer_invoice.php)"),
        ("  5.5", "Riwayat Pesanan Pelanggan (customer_transaksi.php)"),
        ("BAB VI", "Alur Administrator: Dashboard, Pengelolaan Katalog & Pemantauan"),
        ("  6.1", "Dashboard Operasional & Ringkasan Metrik KPI (admin.php)"),
        ("  6.2", "Manajemen Katalog Buku & Upload Gambar Cover (admin_buku.php)"),
        ("  6.3", "Manajemen Penulis & Proteksi Foreign Key (admin_penulis.php)"),
        ("  6.4", "Pemantauan & Pembaruan Status Transaksi Toko (admin_transaksi.php)"),
        ("BAB VII", "Standar Keamanan, Integritas Data & Best Practices"),
        ("BAB VIII", "Panduan Pengujian Sistem & Akun Demo Cepat")
    ]
    add_table_data(
        ["Bagian / Bab", "Topik Pembahasan"],
        doc_index,
        [Inches(1.8), Inches(4.7)]
    )

    # ==========================
    # BAB I
    # ==========================
    add_h1("BAB I: RINGKASAN EKSEKUTIF & STRUKTUR DIREKTORI PROYEK")
    add_p("Blue BookStore merupakan aplikasi web e-commerce penjualan buku berbasis PHP Native dan database relasional MySQL. Aplikasi ini mengusung arsitektur multi-role dengan hak akses yang terisolasi secara ketat antara peran 'customer' (pembaca/pembeli) dan peran 'admin' (pengelola katalog dan transaksi toko).")

    add_h2("1.1 Struktur Berkas dan Direktori Proyek")
    add_p("Seluruh komponen proyek tersimpan dalam folder `blue-BookStore` di direktori htdocs XAMPP dengan pemisahan fungsionalitas yang sangat terstruktur:")

    dir_structure = [
        ("index.php", "Halaman gerbang autentikasi (Login dan Registrasi akun pengguna)."),
        ("koneksi.php", "Konfigurasi koneksi MySQLi, auto-creation tabel, helper format, dan validasi FK."),
        ("customer.php", "Katalog buku utama untuk customer dengan fitur filter kategori dan pencarian keyword."),
        ("customer_cart.php", "Manajemen keranjang belanja (tambah, kurangi qty, hapus item, validasi stok live)."),
        ("customer_checkout.php", "Halaman pembayaran QRIS eksklusif dengan database transaction ACID."),
        ("customer_invoice.php", "Halaman nota / invoice resmi yang siap dicetak (print-ready) oleh pembeli."),
        ("customer_transaksi.php", "Daftar riwayat belanja dan status transaksi milik customer yang sedang login."),
        ("admin.php", "Dashboard eksekutif admin dengan statistik metrik (KPI) toko dan feed ringkasan."),
        ("admin_buku.php", "Modul CRUD buku lengkap dengan upload gambar cover, harga, stok, dan sinopsis."),
        ("admin_penulis.php", "Modul CRUD data master penulis buku dengan proteksi integritas relasi referensial."),
        ("admin_transaksi.php", "Pusat pemantauan seluruh transaksi masuk toko dan utilitas ubah status pesanan."),
        ("logout.php", "Pembersih sesi aman dengan penghancuran session cookie dan redireksi ke index."),
        ("home.php", "Router cerdas untuk mengarahkan pengguna terautentikasi ke panel yang sesuai role-nya."),
        ("database.sql", "Berkas DDL SQL pembuatan basis data awal dan constraint relasional tabel."),
        ("uploads/", "Direktori penyimpanan berkas gambar cover buku yang diunggah administrator.")
    ]
    add_table_data(["Nama Berkas / Folder", "Peran & Fungsionalitas"], dir_structure, [Inches(2.2), Inches(4.3)])

    # ==========================
    # BAB II
    # ==========================
    add_h1("BAB II: SKEMA BASIS DATA & RELASI ENTITAS (DATABASE SCHEMA)")
    add_p("Basis data `blue_bookstore` menggunakan engine InnoDB dengan konfigurasi karakter `utf8mb4` untuk mendukung seluruh format teks internasional. Integritas data dijaga menggunakan relasi Foreign Key dengan aturan Cascade pada referensi kunci primer.")

    add_h2("2.1 Tabel-Tabel Utama Basis Data")
    db_tables = [
        ("users", "Menyimpan kredensial pengguna, nama, email unik, hash password BCRYPT, dan role ('admin'/'customer')."),
        ("penulis", "Menyimpan data master nama penulis / pengarang buku."),
        ("category", "Menyimpan kategori klasifikasi buku (misal: Fiksi, Sains, Teknologi, dsb)."),
        ("tb_buku", "Katalog buku: judul, harga, stok, deskripsi, tautan gambar, berelasi ke penulis dan category."),
        ("payment", "Pencatatan pembayaran: metode (QRIS), total nominal rupiah, dan status pembayaran ('Lunas', dsb)."),
        ("order", "Header transaksi penjualan: id_customer (FK users), id_payment (FK payment), tanggal order, totalHarga."),
        ("orderdetail", "Detail item pesanan: id_order, id_buku, jumlah (qty), harga satuan, dan kalkulasi subtotal.")
    ]
    add_table_data(["Tabel Database", "Deskripsi & Peran Relasional"], db_tables, [Inches(1.8), Inches(4.7)])

    add_h2("2.2 Diagram Alur Relasi Entitas (ERD Logic)")
    add_bullet("Setiap `tb_buku` terikat dengan 1 `penulis` (id_penulis) dan 1 `category` (id_category).", "Relasi Buku:")
    add_bullet("Setiap `order` dimiliki oleh 1 `users` (id_customer) dan memiliki 1 `payment` (id_payment).", "Relasi Order:")
    add_bullet("Tabel `orderdetail` menghubungkan `order` dan `tb_buku` dengan relasi Many-to-Many (composite key `id_order`, `id_buku`), memastikan setiap buku yang dibeli tercatat riwayat harga dan jumlahnya saat transaksi terjadi.", "Relasi Detail:")

    add_callout(
        "Tabel `order` dan `orderdetail` menggunakan mekanisme snapshot harga. Kolom `harga` disimpan di tabel orderdetail saat transaksi terjadi, sehingga perubahan harga buku di tb_buku pada masa depan tidak akan merusak riwayat transaksi keuangan masa lalu.",
        "INTEGRITAS LAPORAN KEUANGAN",
        "info"
    )

    # ==========================
    # BAB III
    # ==========================
    add_h1("BAB III: FONDASI SISTEM & MANAJEMEN KONEKSI (koneksi.php)")
    add_p("Berkas `koneksi.php` bertindak sebagai Single Point of Truth untuk komunikasi database. Berkas ini tidak hanya menginisialisasi objek MySQLi, namun juga secara otomatis mengeksekusi DDL pencegahan (auto-migration) untuk memastikan seluruh tabel dan foreign key telah ada dan selaras dengan versi terbaru sistem.")

    add_h2("3.1 Inisialisasi Koneksi & Auto-DDL Engine")
    add_p("Kode baris 1 s/d 15 membuat koneksi MySQLi ke database `blue_bookstore` di localhost XAMPP. Jika koneksi gagal, sistem akan menghentikan eksekusi via `die()` dengan menampilkan pesan kesalahan yang aman.")

    add_code_block("""<?php
$host = "localhost";
$user = "root";
$pass = "";
$db   = "blue_bookstore";

$koneksi = new mysqli($host, $user, $pass, $db);
if ($koneksi->connect_error) {
    die("Koneksi database gagal: " . $koneksi->connect_error);
}
$koneksi->set_charset("utf8mb4");
""", "Inisialisasi MySQLi di koneksi.php")

    add_h2("3.2 Fitur Auto-Migration & Schema Repair")
    add_p("Pada baris 16 s/d 106, `koneksi.php` memeriksa apakah tabel `users`, `penulis`, `category`, `tb_buku`, `payment`, `order`, dan `orderdetail` telah tersedia. Jika belum, query `CREATE TABLE IF NOT EXISTS` dijalankan secara otomatis. Selain itu, sistem memeriksa keberadaan kolom `deskripsi` dan `gambar` pada `tb_buku` serta memvalidasi apakah constraint foreign key `order.id_customer` mengarah secara tepat ke `users.id`.")

    add_h2("3.3 Fungsi Helper Global")
    add_bullet("Melakukan enkapsulasi htmlspecialchars dengan parameter ENT_QUOTES dan UTF-8 untuk menetralkan potensi serangan Cross-Site Scripting (XSS).", "e($value):")
    add_bullet("Mengonversi angka floating/decimal menjadi format mata uang Rupiah standar Indonesia (misal: 'Rp 85.000').", "formatRupiah($angka):")
    add_bullet("Menghitung total kuantitas buku yang tersimpan di dalam array sesi $_SESSION['cart'].", "getCartCount():")

    add_code_block("""if (!function_exists('e')) {
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
        if (!isset($_SESSION['cart']) || !is_array($_SESSION['cart'])) return 0;
        $count = 0;
        foreach ($_SESSION['cart'] as $item) {
            $count += (int) ($item['qty'] ?? 0);
        }
        return $count;
    }
}
""", "Fungsi Helper Global pada koneksi.php")

    # ==========================
    # BAB IV
    # ==========================
    add_h1("BAB IV: MODUL AUTENTIKASI: LOGIN, REGISTRASI & MANAJEMEN SESI")
    add_p("Autentikasi pada Blue BookStore diimplementasikan di `index.php` dengan tata letak visual modern berupa Split Panel (Brand Panel di sebelah kiri dan Form Panel interaktif di sebelah kanan). Form ini mendukung peralihan dinamis tanpa reload halaman antara form Login dan Sign Up.")

    add_h2("4.1 Registrasi Akun Customer Baru (Sign Up)")
    add_p("Alur registrasi akun customer diawali saat pengguna mengirimkan form dengan `aksi = 'signup'`. Sistem menerapkan validasi bertingkat sebelum menyimpan data ke database:")
    add_bullet("Pembersihan string nama lengkap menggunakan trim().", "Langkah 1 (Sanitasi):")
    add_bullet("Validasi format email menggunakan filter_var($email, FILTER_VALIDATE_EMAIL).", "Langkah 2 (Validasi Email):")
    add_bullet("Enkripsi kata sandi menggunakan fungsi bawaan PHP password_hash($password, PASSWORD_DEFAULT) yang menggunakan algoritma BCRYPT yang sangat kuat dan tahan terhadap serangan brute force.", "Langkah 3 (Hashing):")
    add_bullet("Eksekusi query penyisipan menggunakan Prepared Statement MySQLi untuk mencegah SQL Injection.", "Langkah 4 (Prepared Statement):")
    add_bullet("Penanganan duplikasi email: Jika pengguna mendaftar dengan email yang telah terdaftar, sistem menangkap mysqli_sql_exception dengan kode error 1062 (Duplicate Key) dan memberikan pesan kesalahan yang ramah.", "Langkah 5 (Exception Handling):")

    add_code_block("""if ($aksi === 'signup') {
    $formAktif = 'signup';
    if ($nama === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || $password === '') {
        $pesan = 'Nama, email, dan password wajib diisi dengan benar.';
        $jenisPesan = 'error';
    } else {
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $koneksi->prepare("INSERT INTO users (nama, email, password, role) VALUES (?, ?, ?, 'customer')");
        $stmt->bind_param('sss', $nama, $email, $passwordHash);
        try {
            $stmt->execute();
            $pesan = 'Pendaftaran berhasil! Silakan Login dengan akun baru Anda.';
            $jenisPesan = 'success';
            $formAktif = 'login';
        } catch (mysqli_sql_exception $exception) {
            $pesan = $exception->getCode() === 1062
                ? 'Email tersebut sudah terdaftar.'
                : 'Pendaftaran gagal. Silakan coba lagi.';
            $jenisPesan = 'error';
        }
        $stmt->close();
    }
}
""", "Logika Registrasi Akun di index.php")

    add_h2("4.2 Login Akun & Routing Berbasis Peran (RBAC)")
    add_p("Ketika form Login disubmit (`aksi = 'login'`), sistem melakukan verifikasi kredensial:")
    add_bullet("Mencari record user berdasarkan email menggunakan Prepared Statement (`SELECT id, nama, password, role FROM users WHERE email = ? LIMIT 1`).", "1. Query Akun:")
    add_bullet("Memverifikasi kecocokan kata sandi plaintext dengan hash tersimpan menggunakan `password_verify($password, $passwordHash)`.", "2. Verifikasi Hash:")
    add_bullet("Mencegah serangan Session Fixation dengan memanggil `session_regenerate_id(true)`.", "3. Regenerasi Sesi:")
    add_bullet("Menyimpan identitas penting ke sesi: `$_SESSION['user_id']`, `$_SESSION['user_nama']`, `$_SESSION['user_email']`, dan `$_SESSION['user_role']`.", "4. Penulisan Sesi:")
    add_bullet("Redireksi cerdas: Jika role adalah 'admin', pengguna diarahkan ke `admin.php`. Jika role adalah 'customer', pengguna diarahkan ke `customer.php`.", "5. Redireksi Role:")

    add_code_block("""if (
    $stmt->num_rows === 1
    && password_verify($password, $passwordHash)
    && in_array($rolePengguna, ['admin', 'customer'], true)
) {
    session_regenerate_id(true);
    $_SESSION['user_id']    = $idPengguna;
    $_SESSION['user_nama']  = $namaPengguna;
    $_SESSION['user_email'] = $email;
    $_SESSION['user_role']  = $rolePengguna;

    header('Location: ' . ($rolePengguna === 'admin' ? 'admin.php' : 'customer.php'));
    exit;
} else {
    $pesan = 'Email atau password salah.';
    $jenisPesan = 'error';
}
""", "Logika Verifikasi Login & Pengisian Sesi")

    add_h2("4.3 Sesi Proteksi, Logout & Gateway (logout.php & home.php)")
    add_p("Untuk mencegah kebocoran sesi saat pengguna keluar:")
    add_bullet("`logout.php` mengosongkan array `$_SESSION = []`, menghapus cookie sesi di sisi browser klien dengan mengatur masa berlaku ke waktu lampau (`time() - 42000`), dan memanggil `session_destroy()` sebelum mengarahkan ke `index.php`.", "Mekanisme Logout:")
    add_bullet("`home.php` bertindak sebagai route gateway tengah. Jika pengguna belum login, ia di-redirect ke `index.php`. Jika telah login, pengguna langsung dipantulkan ke dashboard perannya masing-masing.", "Mekanisme Gateway:")

    # ==========================
    # BAB V
    # ==========================
    add_h1("BAB V: ALUR CUSTOMER: DARI KATALOG, KERANJANG, TRANSAKSI HINGGA INVOICE")
    add_p("Bab ini mendokumentasikan seluruh rangkaian perjalanan pengguna customer (Customer Journey), mulai dari pertama kali masuk ke toko, mencari buku, memasukkan ke keranjang, menyesuaikan kuantitas, melakukan pembayaran QRIS terenkapsulasi transaksi ACID, hingga melihat nota pembayaran dan riwayat pesanan.")

    add_h2("5.1 Halaman Awal Customer / Katalog Buku (customer.php)")
    add_p("Halaman `customer.php` adalah etalase belanja utama bagi pelanggan. Halaman ini dilengkapi dengan validasi hak akses di baris 5 s/d 9:")
    add_code_block("""if (empty($_SESSION['user_id']) || ($_SESSION['user_role'] ?? '') !== 'customer') {
    header('Location: index.php');
    exit;
}
""", "Proteksi Hak Akses Customer")

    add_h3("A. Fitur Pencarian & Filter Kategori")
    add_p("Katalog mendukung multi-filter secara simultan. Pengguna dapat mencari buku berdasarkan nama judul, nama penulis, atau kata kunci deskripsi, sekaligus memfilter berdasarkan kategori buku:")
    add_code_block("""$keyword = trim($_GET['q'] ?? '');
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
    $queryBuku .= " AND (b.judul LIKE '%$kSafe%' OR p.nama LIKE '%$kSafe%' OR b.deskripsi LIKE '%$kSafe%') ";
}
if ($filterKat > 0) {
    $queryBuku .= " AND b.id_category = $filterKat ";
}
$queryBuku .= " ORDER BY b.id_buku DESC ";
$hasilBuku = $koneksi->query($queryBuku);
""", "Query Dinamis Katalog Buku Customer")

    add_h3("B. Logika Penambahan ke Keranjang (Add to Cart)")
    add_p("Ketika pembeli mengklik tombol '+ Pilih / Tambah ke Keranjang', form mengirimkan `id_buku` dan `qty` ke `customer.php`:")
    add_bullet("Sistem mengecek ketersediaan buku di tabel `tb_buku`.", "1. Cek Record:")
    add_bullet("Sistem memverifikasi apakah sisa stok > 0. Jika habis, pesan kesalahan dikembalikan.", "2. Validasi Stok:")
    add_bullet("Sistem mengakumulasi kuantitas baru dengan yang sudah ada di `$_SESSION['cart'][$id_buku]`. Jika total akumulasi melebihi stok gudang, sistem membatasi penambahan dan memperingatkan pengguna.", "3. Batas Akumulasi:")
    add_bullet("Data buku disimpan ke dalam array sesi `$_SESSION['cart'][$id_buku]`.", "4. Simpan ke Sesi:")

    add_h2("5.2 Manajemen Keranjang Belanja (customer_cart.php)")
    add_p("Halaman `customer_cart.php` bertugas mengelola daftar buku yang dipilih pembeli sebelum pembayaran. Halaman ini memiliki mekanisme sinkronisasi langsung dengan stok fisik di database:")
    add_bullet("Tombol minus (-) dan plus (+) pada tabel keranjang mengirimkan aksi `update_qty`. Jika kuantitas diatur ke 0, item buku langsung dihapus dari keranjang.", "Pembaruan Kuantitas (Update Qty):")
    add_bullet("Menghapus satu buku tertentu dari sesi belanja pembeli.", "Hapus Item (hapus_item):")
    add_bullet("Menghapus seluruh isi `$_SESSION['cart']` dalam satu klik.", "Kosongkan Keranjang (kosongkan):")
    add_bullet("Setiap kali halaman keranjang dimuat, sistem mengambil id buku di keranjang dan mengeksekusi query database untuk memastikan harga terkini dan memastikan bahwa stok buku yang diminta tidak melebihi stok yang ada di database. Jika buku telah dihapus oleh admin di database, item tersebut secara otomatis dibersihkan dari keranjang.", "Live Database Synchronization:")

    add_code_block("""// Sinkronisasi live stok dan harga buku dengan database
foreach ($_SESSION['cart'] as $id => $item) {
    if (isset($dbBooks[$id])) {
        $dbItem = $dbBooks[$id];
        $qty = min((int) $item['qty'], (int) $dbItem['stok']);
        if ($qty <= 0) {
            unset($_SESSION['cart'][$id]);
            continue;
        }
        $_SESSION['cart'][$id]['qty'] = $qty;
        $_SESSION['cart'][$id]['harga'] = (float) $dbItem['harga'];
        $subtotal = $qty * (float) $dbItem['harga'];
        $totalBelanja += $subtotal;
        $totalItems += $qty;
        // dimasukkan ke array cartDetails untuk dirender ke tabel
    }
}
""", "Live Database Sync di customer_cart.php")

    add_h2("5.3 Alur Checkout & Transaksi QRIS Eksklusif (customer_checkout.php)")
    add_p("Halaman `customer_checkout.php` merupakan inti pemrosesan transaksi. Blue BookStore mengadopsi standar sistem pembayaran modern berbasis QRIS (Quick Response Code Indonesian Standard) sebagai metode pembayaran resmi eksklusif yang mendukung seluruh bank (BCA, Mandiri, BRI, BNI) dan dompet digital (GoPay, OVO, DANA, ShopeePay).")

    add_h3("A. Tampilan Kode QRIS & Komponen Visual")
    add_p("Sistem menampilkan kode QRIS berstandar nasional dalam format grafik vektor SVG beresolusi tinggi, lengkap dengan identitas Merchant (BLUE BOOKSTORE INDONESIA), NMID (ID102026101299108), rincian tagihan rupiah, panduan langkah scan, serta pill badges aplikasi pembayaran yang didukung.")

    add_h3("B. Eksekusi Transaksi Database Terenkapsulasi (ACID Transaction)")
    add_p("Untuk mencegah kegagalan data atau race condition saat beberapa customer membeli buku yang sama di waktu bersamaan, proses checkout dieksekusi di dalam Database Transaction dengan locking `FOR UPDATE`:")
    add_bullet("Memulai transaksi: `$koneksi->begin_transaction()`.", "Langkah 1 (Begin Transaction):")
    add_bullet("Validasi Stok Real-Time dengan Lock: Melakukan query `SELECT stok, judul FROM tb_buku WHERE id_buku = ? FOR UPDATE`. Jika stok kurang dari kuantitas belanja, sistem langsung melempar Exception.", "Langkah 2 (Pessimistic Lock & Validasi):")
    add_bullet("Simpan ke Tabel Payment: Menyisipkan metode 'QRIS', jumlah total belanja, dan status 'Lunas' ke tabel `payment`.", "Langkah 3 (Tabel Payment):")
    add_bullet("Simpan ke Tabel Order: Menyisipkan `id_customer`, `id_payment`, tanggal saat ini (`NOW()`), dan `totalHarga` ke tabel `order`.", "Langkah 4 (Tabel Order):")
    add_bullet("Simpan ke Tabel Orderdetail & Potong Stok: Melakukan looping untuk setiap buku di keranjang, memasukkan rincian harga snapshot ke `orderdetail`, dan memotong stok gudang secara atomik (`UPDATE tb_buku SET stok = stok - ? WHERE id_buku = ?`).", "Langkah 5 (Detail & Deduct Stock):")
    add_bullet("Komit & Bersihkan Sesi: Jika seluruh langkah berhasil, transaksi dikomit (`$koneksi->commit()`), keranjang dikosongkan (`$_SESSION['cart'] = []`), dan pelanggan diarahkan ke halaman invoice.", "Langkah 6 (Commit & Redirect):")
    add_bullet("Rollback jika Terjadi Galat: Jika timbul kesalahan atau stok habis, `$koneksi->rollback()` dijalankan sehingga seluruh data dikembalikan ke kondisi semula tanpa adanya data korup.", "Langkah 7 (Rollback on Error):")

    add_code_block("""// Eksekusi ACID Transaction pada customer_checkout.php
$koneksi->begin_transaction();
try {
    // 1. Validasi stok real-time dengan lock baris
    foreach ($cartDetails as $c) {
        $idB = $c['id_buku'];
        $qB  = $c['qty'];
        $cek = $koneksi->query("SELECT stok, judul FROM tb_buku WHERE id_buku = $idB FOR UPDATE")->fetch_assoc();
        if (!$cek || (int)$cek['stok'] < $qB) {
            throw new Exception("Stok untuk buku '" . ($cek['judul'] ?? 'Buku') . "' tidak mencukupi.");
        }
    }

    // 2. Simpan pembayaran QRIS
    $stmtPay = $koneksi->prepare("INSERT INTO payment (metode, jumlah, status) VALUES (?, ?, ?)");
    $stmtPay->bind_param("sds", $metode, $totalBelanja, $statusBayar);
    $stmtPay->execute();
    $id_payment = $stmtPay->insert_id;
    $stmtPay->close();

    // 3. Simpan header order
    $id_customer = (int) $_SESSION['user_id'];
    $stmtOrder = $koneksi->prepare("INSERT INTO `order` (id_customer, id_payment, tanggal, totalHarga) VALUES (?, ?, NOW(), ?)");
    $stmtOrder->bind_param("iid", $id_customer, $id_payment, $totalBelanja);
    $stmtOrder->execute();
    $id_order = $stmtOrder->insert_id;
    $stmtOrder->close();

    // 4. Simpan orderdetail & kurangi stok buku
    $stmtDetail = $koneksi->prepare("INSERT INTO orderdetail (id_order, id_buku, jumlah, harga, subtotal) VALUES (?, ?, ?, ?, ?)");
    $stmtStok   = $koneksi->prepare("UPDATE tb_buku SET stok = stok - ? WHERE id_buku = ?");

    foreach ($cartDetails as $item) {
        $stmtDetail->bind_param("iiidd", $id_order, $item['id_buku'], $item['qty'], $item['harga'], $item['subtotal']);
        $stmtDetail->execute();

        $stmtStok->bind_param("ii", $item['qty'], $item['id_buku']);
        $stmtStok->execute();
    }
    $stmtDetail->close();
    $stmtStok->close();

    // Commit transaksi atomik
    $koneksi->commit();
    $_SESSION['cart'] = [];
    header("Location: customer_invoice.php?order_id=$id_order");
    exit;

} catch (Exception $e) {
    $koneksi->rollback();
    $pesan = "Gagal memproses pembayaran: " . $e->getMessage();
}
""", "Implementasi ACID Database Transaction pada Proses Checkout")

    add_h2("5.4 Penerbitan Bukti Pembayaran / Invoice Cetak (customer_invoice.php)")
    add_p("Setelah transaksi checkout berhasil, pembeli diarahkan ke `customer_invoice.php?order_id=...`. Berkas ini bertindak sebagai bukti pembayaran sah:")
    add_bullet("Keamanan Kepemilikan: Query SQL memverifikasi bahwa `id_order` yang diakses benar-benar milik `id_customer` yang sedang login (`WHERE o.id_order = ? AND o.id_customer = ?`). Hal ini mencegah pengguna lain melihat transaksi yang bukan hak miliknya (Insecure Direct Object Reference / IDOR).", "Proteksi IDOR:")
    add_bullet("Informasi Lengkap: Menampilkan nomor pesanan (#id_order), tanggal transaksi, data pembeli, metode pembayaran (QRIS), status (Lunas), rincian buku yang dibeli, harga satuan, dan grand total tagihan.", "Detail Faktur:")
    add_bullet("Fitur Cetak Struk: Tersedia tombol '🖨 Cetak Struk / Invoice' yang memanggil `window.print()`. CSS halaman dilengkapi dengan media query `@media print` sehingga elemen navigasi dan tombol aksi disembunyikan saat dicetak ke printer atau diekspor ke format PDF.", "Print Media Optimization:")

    add_h2("5.5 Riwayat Transaksi Pelanggan (customer_transaksi.php)")
    add_p("Halaman `customer_transaksi.php` menyajikan seluruh riwayat pembelian buku yang pernah dilakukan oleh pembeli yang sedang aktif:")
    add_bullet("Mengambil riwayat transaksi dari tabel `order` yang digabungkan dengan `payment` dan difilter berdasarkan `id_customer` pengguna.", "Query Riwayat:")
    add_bullet("Mengelompokkan data detail buku yang dibeli pada setiap pesanan menggunakan tabel perantara `orderdetail` dan `tb_buku`.", "Relasi Agregasi:")
    add_bullet("Menampilkan kartu pesanan dengan badge status (Lunas, Diproses, dsb) serta tautan cepat untuk membuka kembali invoice resmi pembelian.", "Akses Nota Kembali:")

    # ==========================
    # BAB VI
    # ==========================
    add_h1("BAB VI: ALUR ADMINISTRATOR: DASHBOARD, PENGELOLAAN KATALOG & PEMANTAUAN")
    add_p("Bab ini mendokumentasikan antarmuka dan logika backend operasional toko yang diperuntukkan bagi Administrator toko buku.")

    add_h2("6.1 Dashboard Utama Admin (admin.php)")
    add_p("Halaman `admin.php` memberikan ikhtisar performa bisnis toko secara komprehensif bagi admin:")
    add_bullet("Total Buku & Total Unit Stok Fisik Tersedia (`COUNT(*)` & `SUM(stok)` dari tb_buku).", "1. Metrik Total Buku:")
    add_bullet("Total Penulis terdaftar di katalog toko (`COUNT(*)` dari penulis).", "2. Metrik Total Penulis:")
    add_bullet("Total Transaksi yang berhasil dilakukan (`COUNT(*)` dari order).", "3. Metrik Total Pesanan:")
    add_bullet("Total Pendapatan Finansial Toko (`SUM(totalHarga)` dari order) dan Total Customer terdaftar.", "4. Metrik Total Omset:")
    add_bullet("Tabel Feed 5 Transaksi Terkini beserta pelanggan dan status pembayaran.", "5. Quick Feed Transaksi:")
    add_bullet("Tabel Feed 5 Buku Terbaru beserta penulis, harga, dan indikator status stok.", "6. Quick Feed Katalog:")

    add_code_block("""// Agregasi Metrik KPI pada admin.php
$totalBuku      = $koneksi->query("SELECT COUNT(*) AS total, IFNULL(SUM(stok), 0) AS total_stok FROM tb_buku")->fetch_assoc();
$totalPenulis   = $koneksi->query("SELECT COUNT(*) AS total FROM penulis")->fetch_assoc();
$totalCustomer  = $koneksi->query("SELECT COUNT(*) AS total FROM users WHERE role = 'customer'")->fetch_assoc();
$totalTransaksi = $koneksi->query("SELECT COUNT(*) AS total, IFNULL(SUM(totalHarga), 0) AS pendapatan FROM `order`")->fetch_assoc();
""", "Perhitungan KPI Toko di Dashboard Admin")

    add_h2("6.2 Manajemen Katalog & Edit Buku (admin_buku.php)")
    add_p("Halaman `admin_buku.php` menyediakan fasilitas pengelolaan buku terlengkap yang mencakup Create, Read, Update, dan Delete (CRUD), pengaturan harga, stok gudang, deskripsi sinopsis, serta pengunggahan berkas gambar cover.")

    add_h3("A. Fitur Tambah Buku Baru")
    add_p("Admin mengisi form data buku (Judul, Penulis, Kategori, Harga, Stok, Deskripsi, dan Cover). Sistem mengecek keabsahan nilai input dan memindahkan berkas cover yang diunggah ke folder `uploads/`:")
    add_bullet("Validasi ekstensi berkas gambar yang diizinkan: JPG, JPEG, PNG, WEBP, GIF.", "Validasi Gambar:")
    add_bullet("Penamaan unik gambar: Menggunakan format `book_<timestamp>_<randomhex>.<ext>` guna mencegah penimpaan (overwrite) berkas dengan nama yang sama.", "Sanitasi Berkas:")
    add_bullet("Penyimpanan record ke database menggunakan Prepared Statement MySQLi (`INSERT INTO tb_buku`).", "Prepared Query:")

    add_h3("B. Fitur Edit / Pembaruan Data Buku")
    add_p("Ketika admin mengklik tombol 'Edit' pada daftar buku, parameter `?edit=<id_buku>` dikirim via GET. Form akan secara otomatis terisi dengan data buku yang dipilih:")
    add_bullet("Admin dapat mengubah judul, penulis, kategori, harga, dan stok.", "Ubah Atribut:")
    add_bullet("Manajemen Gambar Cover: Jika admin mengunggah gambar baru, sistem secara otomatis menghapus berkas gambar lama di direktori `uploads/` menggunakan fungsi `unlink()` untuk menghemat ruang penyimpanan server.", "Pembersihan Berkas Usang:")
    add_bullet("Pembaruan data dijalankan menggunakan query `UPDATE tb_buku SET ... WHERE id_buku = ?`.", "Update Eksekusi:")

    add_code_block("""// Logika Penggantian Gambar Cover saat Edit Buku
if (!empty($_FILES['gambar']['name']) && $_FILES['gambar']['error'] === UPLOAD_ERR_OK) {
    $ext = strtolower(pathinfo($_FILES['gambar']['name'], PATHINFO_EXTENSION));
    $allowed = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
    if (in_array($ext, $allowed, true)) {
        // Hapus berkas fisik gambar lama jika ada di server
        if ($namaGambar !== '' && file_exists($uploadDir . $namaGambar)) {
            @unlink($uploadDir . $namaGambar);
        }
        // Generate nama file cover baru yang unik dan aman
        $namaGambar = 'book_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        move_uploaded_file($_FILES['gambar']['tmp_name'], $uploadDir . $namaGambar);
    }
}
""", "Pengelolaan Berkas Gambar pada admin_buku.php")

    add_h3("C. Fitur Hapus Buku")
    add_p("Saat tombol hapus diklik, admin diminta konfirmasi dialog JavaScript. Jika dikonfirmasi, sistem memeriksa apakah buku memiliki berkas gambar cover fisik di folder uploads, menghapus gambar tersebut dari storage, dan menghapus record buku dari database via query `DELETE FROM tb_buku WHERE id_buku = ?`.")

    add_h2("6.3 Manajemen Penulis (admin_penulis.php)")
    add_p("Halaman `admin_penulis.php` mengelola data master penulis/pengarang buku yang dipajang di katalog toko.")
    add_bullet("Admin dapat menambahkan pengarang baru dan mengedit nama pengarang yang telah ada.", "CRUD Penulis:")
    add_bullet("Proteksi Integritas Relasional (Foreign Key Guard): Saat admin berupaya menghapus seorang penulis, sistem terlebih dahulu memeriksa apakah penulis tersebut masih memiliki karya buku yang terdaftar di `tb_buku` (`SELECT COUNT(*) FROM tb_buku WHERE id_penulis = ?`). Jika masih terdapat buku, aksi penghapusan dibatalkan dan sistem memunculkan pesan peringatan agar admin memindahkan atau menghapus buku-buku penulis tersebut terlebih dahulu. Hal ini menjamin konsistensi basis data dan mencegah orphan records.", "Integritas Relasional:")

    add_code_block("""// Proteksi Integritas Referensial saat Menghapus Penulis
if ($aksi === 'hapus') {
    $id_penulis = (int) ($_POST['id_penulis'] ?? 0);
    if ($id_penulis > 0) {
        $cekBuku = $koneksi->query("SELECT COUNT(*) AS total FROM tb_buku WHERE id_penulis = $id_penulis")->fetch_assoc()['total'];
        if ($cekBuku > 0) {
            $pesan = "Penulis tidak dapat dihapus karena masih memiliki $cekBuku buku terdaftar di katalog. Hapus atau pindahkan buku terlebih dahulu.";
            $jenisPesan = "error";
        } else {
            $koneksi->query("DELETE FROM penulis WHERE id_penulis = $id_penulis");
            $pesan = "Penulis berhasil dihapus.";
            $jenisPesan = "success";
        }
    }
}
""", "Proteksi Foreign Key Guard di admin_penulis.php")

    add_h2("6.4 Pemantauan & Riwayat Transaksi Toko (admin_transaksi.php)")
    add_p("Halaman `admin_transaksi.php` merupakan pusat kontrol pengawasan seluruh order yang masuk dari pelanggan.")

    add_h3("A. Filter & Pencarian Pesanan")
    add_p("Administrator dapat mencari transaksi berdasarkan ID Order tertentu, nama pelanggan, alamat email pembeli, atau menyaring transaksi berdasarkan status pembayaran (Lunas, Diproses, Selesai, Dibatalkan, Menunggu Pembayaran).")

    add_h3("B. Rincian Detail Transaksi")
    add_p("Ketika admin mengklik 'Lihat Rincian' (`?detail=<id_order>`), panel detail pesanan akan terbuka dan menyajikan rincian lengkap mengenai: nama pemesan, email, waktu pesanan, metode bayar (QRIS), total nominal, serta tabel rincian seluruh judul buku yang dipesan beserta kuantitas dan subtotalnya.")

    add_h3("C. Pembaruan Status Pesanan Real-Time")
    add_p("Admin dapat memperbarui status pesanan secara langsung melalui form dropdown status pada panel detail. Sistem akan mengupdate kolom `status` pada tabel `payment` yang terhubung dengan pesanan tersebut sehingga pelanggan dapat langsung melihat perkembangan status pesanannya pada halaman riwayat pesanan mereka.")

    add_code_block("""// Pembaruan Status Transaksi oleh Admin
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['aksi']) && $_POST['aksi'] === 'update_status') {
    $id_order = (int) ($_POST['id_order'] ?? 0);
    $status_baru = trim($_POST['status'] ?? '');
    $status_valid = ['Lunas', 'Diproses', 'Selesai', 'Dibatalkan', 'Menunggu Pembayaran'];

    if ($id_order > 0 && in_array($status_baru, $status_valid, true)) {
        $ord = $koneksi->query("SELECT id_payment FROM `order` WHERE id_order = $id_order")->fetch_assoc();
        if ($ord) {
            $id_payment = (int) $ord['id_payment'];
            $stmt = $koneksi->prepare("UPDATE payment SET status = ? WHERE id_payment = ?");
            $stmt->bind_param("si", $status_baru, $id_payment);
            $stmt->execute();
            $stmt->close();
            $pesan = "Status transaksi #$id_order berhasil diperbarui menjadi '$status_baru'.";
            $jenisPesan = "success";
        }
    }
}
""", "Logika Update Status Pesanan di admin_transaksi.php")

    # ==========================
    # BAB VII
    # ==========================
    add_h1("BAB VII: IMPLEMENTASI KEAMANAN & BEST PRACTICES")
    add_p("Aplikasi Blue BookStore dirancang dengan mematuhi standar rekayasa perangkat lunak modern untuk mengantisipasi celah keamanan web umum (OWASP Top 10):")

    sec_rules = [
        ("Proteksi SQL Injection", "Seluruh query manipulasi data (INSERT, UPDATE, DELETE, SELECT sensitif) menggunakan Prepared Statements dengan parameter binding (`bind_param`). Data dari pengguna tidak pernah disambung langsung ke dalam string query SQL mentah."),
        ("Pengamanan Kata Sandi", "Menggunakan standar industri BCRYPT melalui fungsi `password_hash()` dan `password_verify()`. Kata sandi pengguna tidak pernah disimpan dalam format teks polos (plaintext)."),
        ("Penanggulangan Cross-Site Scripting (XSS)", "Seluruh variabel dinamis yang dicetak ke antarmuka HTML dibungkus dengan fungsi helper `e()`, yang mengaplikasikan `htmlspecialchars($val, ENT_QUOTES, 'UTF-8')` untuk mencegah injeksi skrip berbahaya."),
        ("Pencegahan Session Hijacking & Fixation", "Saat login berhasil dilakukan, ID sesi di-refresh menggunakan `session_regenerate_id(true)`. Selain itu, saat logout, cookie sesi browser dinetralkan dengan expire time lampau."),
        ("Proteksi Insecure Direct Object Reference (IDOR)", "Pada halaman `customer_invoice.php`, query pembacaan nota pesanan selalu mengikatkan ID pengguna yang sedang login (`AND o.id_customer = ?`), sehingga pengguna tidak dapat mengintip struk pesanan milik pembeli lain."),
        ("Integritas Transaksi Atomik (ACID)", "Proses checkout terenkapsulasi di dalam blok `$koneksi->begin_transaction()`, `$koneksi->commit()`, dan `$koneksi->rollback()`, didukung pessimistic row locking (`FOR UPDATE`) guna mencegah korupsi stok buku.")
    ]
    add_table_data(["Aspek Keamanan", "Implementasi Teknis pada Kode"], sec_rules, [Inches(2.2), Inches(4.3)])

    # ==========================
    # BAB VIII
    # ==========================
    add_h1("BAB VIII: PANDUAN PENGUJIAN SISTEM & AKUN DEMO CEPAT")
    add_p("Untuk mempermudah proses evaluasi dan presentasi sistem, pengembang telah menyematkan fitur pengisian akun cepat (Quick Demo Autofill) pada halaman login `index.php`.")

    demo_creds = [
        ("Akun Administrator", "admin@bluebookstore.id", "admin", "admin.php (Dashboard Manajemen, CRUD Buku, CRUD Penulis, Riwayat Transaksi)"),
        ("Akun Customer", "user@gmail.com", "user", "customer.php (Katalog Buku, Keranjang Belanja, Checkout QRIS, Riwayat Pesanan)")
    ]
    add_table_data(["Peran Akun", "Alamat Email", "Password", "Akses Panel"], demo_creds, [Inches(1.5), Inches(2.0), Inches(1.0), Inches(2.0)])

    add_h2("8.1 Skenario Pengujian Alur Customer (User Testing)")
    add_bullet("Buka `http://localhost/blue-BookStore/index.php` pada browser.", "Langkah 1:")
    add_bullet("Gunakan tombol 'Isi Akun User' atau buat akun baru melalui tombol 'Sign Up'.", "Langkah 2:")
    add_bullet("Setelah masuk ke `customer.php`, gunakan fitur pencarian atau filter kategori untuk mencari buku.", "Langkah 3:")
    add_bullet("Klik tombol '+ Pilih / Tambah ke Keranjang' pada salah satu buku yang memiliki stok.", "Langkah 4:")
    add_bullet("Buka halaman keranjang (`customer_cart.php`), coba ubah jumlah kuantitas buku menggunakan tombol minus atau plus.", "Langkah 5:")
    add_bullet("Klik 'Lanjut ke Pembayaran &rarr;' menuju halaman `customer_checkout.php`.", "Langkah 6:")
    add_bullet("Simak kode QRIS yang tampil, lalu klik '✓ Konfirmasi Pembayaran QRIS'.", "Langkah 7:")
    add_bullet("Perhatikan halaman `customer_invoice.php` yang terbit secara instan dan klik '🖨 Cetak Struk' untuk menguji mode print.", "Langkah 8:")
    add_bullet("Buka menu 'Riwayat Pesanan' (`customer_transaksi.php`) untuk memastikan transaksi baru telah tercatat dengan status 'Lunas'.", "Langkah 9:")

    add_h2("8.2 Skenario Pengujian Alur Administrator (Admin Testing)")
    add_bullet("Logout dari akun customer dan login menggunakan tombol 'Isi Akun Admin'.", "Langkah 1:")
    add_bullet("Periksa dasbor `admin.php`: verifikasi apakah metrik total transaksi, total pendapatan, dan daftar transaksi terbaru mencerminkan transaksi customer yang baru saja dilakukan.", "Langkah 2:")
    add_bullet("Buka menu 'Kelola Buku' (`admin_buku.php`): coba lakukan penambahan buku baru lengkap dengan gambar cover, lalu coba edit harga atau stoknya.", "Langkah 3:")
    add_bullet("Buka menu 'Kelola Penulis' (`admin_penulis.php`): tambahkan penulis baru, lalu coba hapus penulis yang masih memiliki buku untuk menguji proteksi Foreign Key Guard.", "Langkah 4:")
    add_bullet("Buka menu 'Riwayat Transaksi' (`admin_transaksi.php`): cari pesanan pelanggan, klik 'Lihat Rincian', dan coba ubah status pesanan menjadi 'Diproses' atau 'Selesai'.", "Langkah 5:")

    # Output file path
    output_path = os.path.abspath("Dokumentasi_Kode_Blue_BookStore.docx")
    doc.save(output_path)
    print(f"Document successfully created at: {output_path}")

if __name__ == "__main__":
    create_document()
