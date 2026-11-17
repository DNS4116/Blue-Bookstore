<?php
session_start();
require_once __DIR__ . '/koneksi.php';

// Jika pengguna sudah login, alihkan langsung ke halaman sesuai role
if (!empty($_SESSION['user_id']) && !empty($_SESSION['user_role'])) {
    if ($_SESSION['user_role'] === 'admin') {
        header('Location: admin.php');
        exit;
    } elseif ($_SESSION['user_role'] === 'customer') {
        header('Location: customer.php');
        exit;
    }
}

$pesan = '';
$jenisPesan = '';
$formAktif = 'login';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $aksi = $_POST['aksi'] ?? '';
    $nama = trim($_POST['nama'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($aksi === 'signup') {
        $formAktif = 'signup';

        if ($nama === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || $password === '') {
            $pesan = 'Nama, email, dan password wajib diisi dengan benar.';
            $jenisPesan = 'error';
        } else {
            $passwordHash = hashPasswordArgon2id($password);
            $stmt = $koneksi->prepare("INSERT INTO users (nama, email, password, role) VALUES (?, ?, ?, 'customer')");
            $stmt->bind_param('sss', $nama, $email, $passwordHash);

            try {
                $stmt->execute();
                $pesan = 'Pendaftaran berhasil! Akun Anda diamankan dengan Argon2id. Silakan Login.';
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

    if ($aksi === 'login') {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $password === '') {
            $pesan = 'Email dan password wajib diisi dengan benar.';
            $jenisPesan = 'error';
        } else {
            $stmt = $koneksi->prepare('SELECT id, nama, password, role FROM users WHERE email = ? LIMIT 1');
            $stmt->bind_param('s', $email);
            $stmt->execute();
            $stmt->store_result();

            $idPengguna = 0;
            $namaPengguna = '';
            $passwordHash = '';
            $rolePengguna = '';

            if ($stmt->num_rows === 1) {
                $stmt->bind_result($idPengguna, $namaPengguna, $passwordHash, $rolePengguna);
                $stmt->fetch();
            }

            if (
                $stmt->num_rows === 1
                && verifyPasswordArgon2id($password, $passwordHash)
                && in_array($rolePengguna, ['admin', 'customer'], true)
            ) {
                // Periksa apakah hash perlu di-upgrade ke Argon2id
                if (needsRehashToArgon2id($passwordHash)) {
                    $newArgonHash = hashPasswordArgon2id($password);
                    $upStmt = $koneksi->prepare('UPDATE users SET password = ? WHERE id = ?');
                    $upStmt->bind_param('si', $newArgonHash, $idPengguna);
                    $upStmt->execute();
                    $upStmt->close();
                }

                session_regenerate_id(true);
                $_SESSION['user_id'] = $idPengguna;
                $_SESSION['user_nama'] = $namaPengguna;
                $_SESSION['user_email'] = $email;
                $_SESSION['user_role'] = $rolePengguna;

                header('Location: ' . ($rolePengguna === 'admin' ? 'admin.php' : 'customer.php'));
                exit;
            } else {
                $pesan = 'Email atau password salah.';
                $jenisPesan = 'error';
            }

            $stmt->close();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Blue BookStore | Login & Register</title>
    <link rel="stylesheet" href="index.css">
</head>
<body>
    <main class="auth-shell">
        <!-- Panel brand -->
        <section class="brand-panel" aria-label="Blue BookStore">
            <div class="logo">BLUE BOOKSTORE</div>
            <div class="brand-copy">
                <h1>Temukan cerita berikutnya.</h1>
                <p>Masuk untuk menjelajahi koleksi buku pilihan, melakukan pemesanan, atau mengelola katalog toko.</p>
            </div>
        </section>

        <!-- Panel Form -->
        <section class="form-panel">
            <div class="form-wrap">
                <div class="form-heading">
                    <h2 id="form-title"><?= $formAktif === 'signup' ? 'Buat akun baru' : 'Selamat datang kembali' ?></h2>
                    <p id="form-description"><?= $formAktif === 'signup' ? 'Daftar untuk mulai menikmati koleksi buku kami.' : 'Masuk ke akun Blue BookStore kamu.' ?></p>
                </div>

                <?php if ($pesan !== ''): ?>
                    <p class="message <?= htmlspecialchars($jenisPesan, ENT_QUOTES, 'UTF-8') ?>">
                        <?= htmlspecialchars($pesan, ENT_QUOTES, 'UTF-8') ?>
                    </p>
                <?php endif; ?>

                <!-- Form Login -->
                <form id="login-form" class="auth-form <?= $formAktif === 'login' ? 'active' : '' ?>" action="index.php" method="post">
                    <input type="hidden" name="aksi" value="login">
                    <div class="field">
                        <label for="login-email">Email</label>
                        <input id="login-email" name="email" type="email" placeholder="nama@email.com" autocomplete="email" required>
                    </div>
                    <div class="field">
                        <label for="login-password">Password</label>
                        <input id="login-password" name="password" type="password" placeholder="Masukkan password" autocomplete="current-password" required>
                    </div>
                    <button class="submit-button" type="submit">Login</button>
                    <p class="switch-text">Belum punya akun? <button class="switch-button" type="button" data-target="signup">Sign Up</button></p>

                    <!-- Info Akun Cepat untuk Pengujian -->
                    <div class="demo-box">
                        <strong>🔑 Akun Pengujian Cepat:</strong>
                        <div>Admin: <code>admin@bluebookstore.id</code> (pass: <code>admin</code>)</div>
                        <div>User: <code>user@gmail.com</code> (pass: <code>user</code>)</div>
                        <div class="demo-btns">
                            <button type="button" class="demo-btn" onclick="isiAkun('admin@bluebookstore.id', 'admin')">Isi Akun Admin</button>
                            <button type="button" class="demo-btn user" onclick="isiAkun('user@gmail.com', 'user')">Isi Akun User</button>
                        </div>
                        <div class="argon-badge">🛡️ Terproteksi Algoritma Argon2id (64MB, 4 iterasi)</div>
                    </div>
                </form>

                <!-- Form Sign Up -->
                <form id="signup-form" class="auth-form <?= $formAktif === 'signup' ? 'active' : '' ?>" action="index.php" method="post">
                    <input type="hidden" name="aksi" value="signup">
                    <div class="field">
                        <label for="signup-name">Nama Lengkap</label>
                        <input id="signup-name" name="nama" type="text" placeholder="Masukkan nama" autocomplete="name" required>
                    </div>
                    <div class="field">
                        <label for="signup-email">Email</label>
                        <input id="signup-email" name="email" type="email" placeholder="nama@email.com" autocomplete="email" required>
                    </div>
                    <div class="field">
                        <label for="signup-password">Password</label>
                        <input id="signup-password" name="password" type="password" placeholder="Buat password" autocomplete="new-password" required>
                    </div>
                    <button class="submit-button" type="submit">Sign Up (Daftar User)</button>
                    <p class="switch-text">Sudah punya akun? <button class="switch-button" type="button" data-target="login">Login</button></p>
                </form>
            </div>
        </section>
    </main>

    <script>
        const title = document.getElementById('form-title');
        const description = document.getElementById('form-description');
        const forms = {
            login: document.getElementById('login-form'),
            signup: document.getElementById('signup-form')
        };

        document.querySelectorAll('.switch-button').forEach((button) => {
            button.addEventListener('click', () => {
                const target = button.dataset.target;
                forms.login.classList.toggle('active', target === 'login');
                forms.signup.classList.toggle('active', target === 'signup');

                title.textContent = target === 'login' ? 'Selamat datang kembali' : 'Buat akun baru';
                description.textContent = target === 'login'
                    ? 'Masuk ke akun Blue BookStore kamu.'
                    : 'Daftar untuk mulai menikmati koleksi buku kami.';
                document.title = `Blue BookStore | ${target === 'login' ? 'Login' : 'Sign Up'}`;
            });
        });

        function isiAkun(email, pass) {
            document.getElementById('login-email').value = email;
            document.getElementById('login-password').value = pass;
        }
    </script>
</body>
</html>