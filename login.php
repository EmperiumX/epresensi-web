<?php
ob_start();
require_once __DIR__ . '/config/session.php';
require_once __DIR__ . '/config/database.php';

if (isset($_SESSION['admin']) && $_SESSION['admin'] === true) {
    header("Location: admin/index.php");
    exit;
}

$error = "";

// Rate limiting sederhana berbasis sesi
if (!isset($_SESSION['login_attempts'])) {
    $_SESSION['login_attempts'] = 0;
    $_SESSION['last_attempt_time'] = time();
}

// Reset percobaan setelah 15 menit
if (time() - $_SESSION['last_attempt_time'] > 900) {
    $_SESSION['login_attempts'] = 0;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    if (!verify_csrf_token()) {
        $error = "Token keamanan sesi kedaluwarsa. Silakan refresh halaman.";
    } elseif ($_SESSION['login_attempts'] >= 8) {
        $error = "Terlalu banyak percobaan gagal. Silakan tunggu 15 menit.";
    } else {
        $username = trim((string)$_POST['username']);
        $password = (string)$_POST['password'];

        if ($username === '' || $password === '') {
            $error = "Username dan password wajib diisi.";
        } else {
            // 1. Cek Akun Admin di tabel 'admin'
            $stmt = mysqli_prepare($conn, "SELECT id, username, password FROM admin WHERE username = ? LIMIT 1");
            mysqli_stmt_bind_param($stmt, "s", $username);
            mysqli_stmt_execute($stmt);
            $res = mysqli_stmt_get_result($stmt);

            $login_success = false;

            if ($row = mysqli_fetch_assoc($res)) {
                $db_pass = $row['password'];
                // Cek apakah password cocok (Bcrypt hash ATAU fallback plain-text untuk transisi aman)
                if (password_verify($password, $db_pass) || $password === $db_pass) {
                    // Jika masih plain-text, otomatis rehash ke Bcrypt modern
                    if (password_needs_rehash($db_pass, PASSWORD_BCRYPT) || $password === $db_pass) {
                        $new_hash = password_hash($password, PASSWORD_BCRYPT);
                        $stmt_rehash = mysqli_prepare($conn, "UPDATE admin SET password = ? WHERE id = ?");
                        mysqli_stmt_bind_param($stmt_rehash, "si", $new_hash, $row['id']);
                        mysqli_stmt_execute($stmt_rehash);
                    }

                    $login_success = true;
                    session_regenerate_id(true);
                    $_SESSION['admin'] = true;
                    $_SESSION['admin_id'] = $row['id'];
                    $_SESSION['admin_user'] = $row['username'];
                    $_SESSION['role'] = 'owner';
                    $_SESSION['login_attempts'] = 0;

                    header("Location: admin/index.php");
                    exit;
                }
            }

            // 2. Cek apakah ada Karyawan yang diberi hak akses Admin (tabel 'users')
            if (!$login_success) {
                $stmt_u = mysqli_prepare($conn, "SELECT id, nama, pin, akses_admin FROM users WHERE nama = ? LIMIT 1");
                mysqli_stmt_bind_param($stmt_u, "s", $username);
                mysqli_stmt_execute($stmt_u);
                $res_u = mysqli_stmt_get_result($stmt_u);

                if ($u = mysqli_fetch_assoc($res_u)) {
                    if ((int)$u['akses_admin'] === 1) {
                        if (password_verify($password, $u['pin']) || $password === $u['pin']) {
                            if (password_needs_rehash($u['pin'], PASSWORD_BCRYPT) || $password === $u['pin']) {
                                $new_pin_hash = password_hash($password, PASSWORD_BCRYPT);
                                $stmt_rehash_pin = mysqli_prepare($conn, "UPDATE users SET pin = ? WHERE id = ?");
                                mysqli_stmt_bind_param($stmt_rehash_pin, "si", $new_pin_hash, $u['id']);
                                mysqli_stmt_execute($stmt_rehash_pin);
                            }

                            $login_success = true;
                            session_regenerate_id(true);
                            $_SESSION['admin'] = true;
                            $_SESSION['admin_id'] = $u['id'];
                            $_SESSION['admin_user'] = $u['nama'];
                            $_SESSION['role'] = 'karyawan_admin';
                            $_SESSION['login_attempts'] = 0;

                            header("Location: admin/index.php");
                            exit;
                        }
                    }
                }
            }

            if (!$login_success) {
                $_SESSION['login_attempts']++;
                $_SESSION['last_attempt_time'] = time();
                $error = "Kombinasi kredensial tidak valid.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Portal Pengelola | E-Presensi</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Outfit:wght@500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        :root {
            --bg-body: #FFFDF9;
            --brand-deep: #78350F;
            --brand-primary: #D97706;
            --brand-gold-mid: #F59E0B;
            --text-dark: #1F160E;
        }

        body {
            background-color: var(--bg-body);
            font-family: 'Plus Jakarta Sans', sans-serif;
            color: var(--text-dark);
            min-height: 100dvh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
            margin: 0;
        }

        .auth-card {
            background: rgba(255, 255, 255, 0.94);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border-radius: 26px;
            padding: 40px 32px;
            border: 1.5px solid #FDE68A;
            box-shadow: 0 16px 40px -8px rgba(217, 119, 6, 0.16);
            width: 100%;
            max-width: 420px;
            transition: all 0.3s ease;
        }

        .brand-badge {
            width: 68px;
            height: 68px;
            border-radius: 20px;
            background: #FFFBEB;
            border: 2px solid #FCD34D;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 16px;
            padding: 6px;
            box-shadow: 0 4px 14px rgba(245, 158, 11, 0.15);
        }

        .brand-badge img {
            width: 100%;
            height: 100%;
            object-fit: contain;
        }

        .brand-title {
            font-family: 'Outfit', sans-serif;
            font-weight: 800;
            font-size: 1.35rem;
            color: var(--brand-deep);
            letter-spacing: -0.01em;
            margin-bottom: 4px;
        }

        .brand-subtitle {
            font-size: 0.78rem;
            color: #B45309;
            font-weight: 600;
            margin-bottom: 24px;
        }

        .form-label {
            font-size: 0.76rem;
            text-transform: uppercase;
            letter-spacing: 0.07em;
            font-weight: 800;
            color: #92400E;
            margin-bottom: 6px;
        }

        .form-control {
            border: 1.5px solid #FDE68A;
            border-radius: 12px;
            padding: 12px 14px;
            font-size: 0.92rem;
            color: var(--text-dark);
            background: #FFFDF9;
            transition: all 0.2s ease;
        }

        .form-control:focus {
            background: #FFFFFF;
            border-color: #D97706;
            box-shadow: 0 0 0 4px rgba(217, 119, 6, 0.15);
        }

        .btn-brand {
            background: #D97706;
            color: #FFFFFF;
            border: none;
            border-radius: 14px;
            padding: 14px;
            font-family: 'Outfit', sans-serif;
            font-weight: 800;
            font-size: 1.05rem;
            width: 100%;
            transition: all 0.25s cubic-bezier(0.16, 1, 0.3, 1);
            box-shadow: 0 4px 14px rgba(217, 119, 6, 0.25);
        }

        .btn-brand:hover {
            background: #B45309;
            color: #FFFFFF;
            transform: translateY(-2px);
            box-shadow: 0 6px 18px rgba(217, 119, 6, 0.35);
        }

        .btn-brand:active {
            background: #92400E;
            transform: scale(0.98);
        }

        .alert-custom {
            border-radius: 12px;
            padding: 10px 14px;
            font-size: 0.85rem;
            background: #FFF1F2;
            color: #9F1239;
            border: 1.5px solid #FDA4AF;
            font-weight: 600;
        }

        .btn-return-link {
            color: #B45309;
            font-weight: 700;
            font-size: 0.84rem;
            text-decoration: none;
            transition: all 0.2s ease;
        }

        .btn-return-link:hover {
            color: #78350F;
            text-decoration: underline;
        }
    </style>
</head>
<body>

    <div class="auth-card">
        <div class="text-center">
            <div class="brand-badge">
                <img src="assets/img/logo.png" alt="E-Presensi">
            </div>
            <h1 class="brand-title">Portal Pengelola</h1>
            <p class="brand-subtitle">E-Presensi &bull; Sistem Presensi Multi-Outlet</p>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-custom mb-3 d-flex align-items-center">
                <i class="fas fa-exclamation-circle me-2 fs-5"></i>
                <div><?= e($error) ?></div>
            </div>
        <?php endif; ?>

        <form method="POST" autocomplete="off">
            <?= csrf_field() ?>

            <div class="mb-3">
                <label class="form-label">Username / Nama</label>
                <div class="input-group">
                    <input type="text" name="username" class="form-control" placeholder="Masukkan username admin" required autofocus>
                </div>
            </div>

            <div class="mb-4">
                <label class="form-label">Kata Sandi / PIN</label>
                <input type="password" name="password" class="form-control" placeholder="••••••••" required>
            </div>

            <button type="submit" name="login" class="btn btn-brand">
                <i class="fas fa-arrow-right-to-bracket me-2"></i> Masuk ke Dasbor
            </button>
        </form>

        <div class="text-center mt-4 pt-3 border-top" style="border-color: #FDE68A !important;">
            <a href="index.php" class="btn-return-link d-inline-flex align-items-center">
                <i class="fas fa-arrow-left me-1"></i> Kembali ke Form Absensi
            </a>
        </div>
    </div>

</body>
</html>