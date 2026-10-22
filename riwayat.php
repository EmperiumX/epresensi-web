<?php
require_once __DIR__ . '/config/session.php';
require_once __DIR__ . '/config/database.php';

$error = "";
$data_user = null;
$history_rows = [];
$total_hari = 0;
$total_jam_kerja = 0;
$sisa_menit = 0;

// Rate limiting pengecekan PIN
if (!isset($_SESSION['riwayat_attempts'])) {
    $_SESSION['riwayat_attempts'] = 0;
    $_SESSION['riwayat_time'] = time();
}
if (time() - $_SESSION['riwayat_time'] > 600) {
    $_SESSION['riwayat_attempts'] = 0;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cek'])) {
    if (!verify_csrf_token()) {
        $error = "Sesi telah kedaluwarsa. Silakan refresh halaman.";
    } elseif ($_SESSION['riwayat_attempts'] >= 8) {
        $error = "Terlalu banyak percobaan salah. Silakan tunggu 10 menit.";
    } else {
        $user_id   = (int)$_POST['user_id'];
        $pin_input = trim((string)$_POST['pin']);

        if ($user_id > 0 && $pin_input !== '') {
            $stmt = mysqli_prepare($conn, "SELECT users.*, cabang.nama_cabang 
                FROM users 
                LEFT JOIN cabang ON users.cabang_id = cabang.id 
                WHERE users.id = ? LIMIT 1");
            mysqli_stmt_bind_param($stmt, "i", $user_id);
            mysqli_stmt_execute($stmt);
            $res = mysqli_stmt_get_result($stmt);
            if ($u = mysqli_fetch_assoc($res)) {
                $pin_db = trim((string)$u['pin']);
                $is_pin_valid = false;
                if (strlen($pin_db) >= 60 && password_verify($pin_input, $pin_db)) {
                    $is_pin_valid = true;
                } elseif ($pin_input === $pin_db) {
                    $is_pin_valid = true;
                } elseif (($pin_db === '0' || $pin_db === '' || (strpos($pin_db, '$') === 0 && strlen($pin_db) < 60)) && $pin_input === '1234') {
                    $is_pin_valid = true;
                }

                if ($is_pin_valid) {
                    $_SESSION['riwayat_attempts'] = 0;
                    $data_user = $u;
                    $bulan_ini = date('Y-m');

                    // 1. Total Hari Hadir
                    $stmt_tot = mysqli_prepare($conn, "SELECT COUNT(*) as jml FROM attendance WHERE user_id = ? AND tanggal LIKE ? AND shift != 'Izin'");
                    $like_bulan = $bulan_ini . '%';
                    mysqli_stmt_bind_param($stmt_tot, "is", $user_id, $like_bulan);
                    mysqli_stmt_execute($stmt_tot);
                    $total_hari = (int)mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_tot))['jml'];

                    // 2. Total Jam Kerja (Khusus Parttime)
                    $total_detik = 0;
                    $stmt_jam = mysqli_prepare($conn, "SELECT jam_masuk, jam_pulang FROM attendance WHERE user_id = ? AND tanggal LIKE ? AND jam_pulang IS NOT NULL AND shift != 'Izin'");
                    mysqli_stmt_bind_param($stmt_jam, "is", $user_id, $like_bulan);
                    mysqli_stmt_execute($stmt_jam);
                    $res_jam = mysqli_stmt_get_result($stmt_jam);

                    while ($rj = mysqli_fetch_assoc($res_jam)) {
                        $diff = strtotime($rj['jam_pulang']) - strtotime($rj['jam_masuk']);
                        if ($diff > 0) $total_detik += $diff;
                    }

                    $total_jam_kerja = floor($total_detik / 3600);
                    $sisa_menit = floor(($total_detik % 3600) / 60);

                    // 3. Detail Riwayat
                    $stmt_hist = mysqli_prepare($conn, "SELECT attendance.*, cabang.nama_cabang 
                        FROM attendance 
                        LEFT JOIN cabang ON attendance.cabang_id = cabang.id 
                        WHERE attendance.user_id = ? AND attendance.tanggal LIKE ? 
                        ORDER BY attendance.tanggal DESC, attendance.jam_masuk DESC");
                    mysqli_stmt_bind_param($stmt_hist, "is", $user_id, $like_bulan);
                    mysqli_stmt_execute($stmt_hist);
                    $res_hist = mysqli_stmt_get_result($stmt_hist);

                    while ($rh = mysqli_fetch_assoc($res_hist)) {
                        $history_rows[] = $rh;
                    }

                } else {
                    $_SESSION['riwayat_attempts']++;
                    $_SESSION['riwayat_time'] = time();
                    $error = "PIN keamanan yang Anda masukkan salah.";
                }
            } else {
                $error = "Data pegawai tidak ditemukan.";
            }
        }
    }
}

// Ambil Karyawan untuk Dropdown
$q_all = mysqli_query($conn, "SELECT id, nama FROM users ORDER BY nama ASC");
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Riwayat Kehadiran Pegawai | E-Presensi</title>
    
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
        }

        .main-wrapper {
            max-width: 490px;
            margin: 0 auto;
            padding: 20px 16px 45px;
        }

        .btn-back-nav {
            border: 1.5px solid #FCD34D;
            color: #78350F;
            background: #FFFDF9;
            font-weight: 700;
            border-radius: 12px;
            padding: 6px 14px;
            font-size: 0.84rem;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            box-shadow: 0 2px 8px rgba(217, 119, 6, 0.08);
            transition: all 0.2s ease;
        }

        .btn-back-nav:hover {
            background: #FEF3C7;
            color: #92400E;
        }

        .auth-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(14px);
            -webkit-backdrop-filter: blur(14px);
            border-radius: 24px;
            padding: 28px 24px;
            border: 1.5px solid #FDE68A;
            box-shadow: 0 12px 32px -4px rgba(217, 119, 6, 0.12);
            margin-bottom: 20px;
        }

        .page-title {
            font-family: 'Outfit', sans-serif;
            font-weight: 800;
            font-size: 1.25rem;
            color: #78350F;
            letter-spacing: -0.01em;
            margin-bottom: 4px;
        }

        .field-label {
            font-size: 0.76rem;
            text-transform: uppercase;
            letter-spacing: 0.07em;
            font-weight: 800;
            color: #92400E;
            margin-bottom: 6px;
            display: block;
        }

        .form-select, .form-control {
            border: 1.5px solid #FDE68A;
            border-radius: 12px;
            padding: 12px 14px;
            font-size: 0.92rem;
            color: var(--text-dark);
            background: #FFFDF9;
            font-weight: 600;
            transition: all 0.2s ease;
        }

        .form-select:focus, .form-control:focus {
            background: #FFFFFF;
            border-color: #D97706;
            box-shadow: 0 0 0 4px rgba(217, 119, 6, 0.15);
        }

        .btn-brand {
            background: #D97706;
            color: #FFFFFF;
            border: none;
            border-radius: 14px;
            padding: 13px;
            font-family: 'Outfit', sans-serif;
            font-weight: 800;
            font-size: 1rem;
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

        /* Summary Accent Box */
        .summary-accent-box {
            background: #FEF3C7;
            border: 1.5px solid #FCD34D;
            border-radius: 18px;
            padding: 18px;
            margin-bottom: 20px;
            box-shadow: 0 4px 14px rgba(217, 119, 6, 0.08);
        }

        .badge-soft {
            padding: 5px 10px;
            border-radius: 8px;
            font-size: 0.74rem;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }

        .badge-green { background: #ECFDF5; color: #065F46; border: 1.5px solid #A7F3D0; }
        .badge-red { background: #FFF1F2; color: #9F1239; border: 1.5px solid #FDA4AF; }
        .badge-yellow { background: #FFFBEB; color: #92400E; border: 1.5px solid #FDE68A; }
        .badge-blue { background: #EFF6FF; color: #1E40AF; border: 1.5px solid #BFDBFE; }
    </style>
</head>
<body>

    <div class="main-wrapper">

        <!-- Header -->
        <div class="d-flex align-items-center justify-content-between mb-3">
            <a href="index.php" class="btn-back-nav">
                <i class="fas fa-arrow-left"></i> Kembali ke Form Absensi
            </a>
            <span class="small fw-bold" style="color: #78350F;">E-Presensi</span>
        </div>

        <!-- FORM PIN CEK RIWAYAT -->
        <div class="auth-card">
            <h1 class="page-title">Cek Presensi Saya</h1>
            <p class="small text-muted mb-3">Pantau kehadiran dan akumulasi jam kerja bulanan Anda.</p>

            <?php if ($error): ?>
                <div class="alert alert-danger py-2 px-3 small d-flex align-items-center mb-3" style="border-radius: 12px; border: 1.5px solid #FDA4AF; background: #FFF1F2; color: #9F1239; font-weight: 600;">
                        <i class="fas fa-triangle-exclamation me-2"></i>
                        <div><?= e($error) ?></div>
                    </div>
                <?php endif; ?>

                <form method="POST">
                    <?= csrf_field() ?>
                    <div class="mb-3">
                        <label class="field-label">Pilih Nama Anda</label>
                        <select name="user_id" class="form-select" required>
                            <option value="">-- Pilih Nama Pegawai --</option>
                            <?php while ($ru = mysqli_fetch_assoc($q_all)): ?>
                                <option value="<?= $ru['id'] ?>" <?= isset($_POST['user_id']) && (int)$_POST['user_id'] === (int)$ru['id'] ? 'selected' : '' ?>>
                                    <?= e($ru['nama']) ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="field-label">PIN Keamanan</label>
                        <input type="password" name="pin" class="form-control text-center font-monospace fs-5 fw-bold" 
                               placeholder="••••" maxlength="6" inputmode="numeric" required style="letter-spacing: 6px;">
                    </div>

                    <button type="submit" name="cek" class="btn btn-brand">
                        <i class="fas fa-eye me-1"></i> Tampilkan Riwayat
                    </button>
                </form>
        </div>

        <!-- HASIL DATA SETELAH PIN TERVERIFIKASI -->
        <?php if ($data_user): ?>
            <div class="auth-card">
                
                <div class="d-flex align-items-center justify-content-between mb-3 border-bottom pb-3" style="border-color: #FDE68A !important;">
                    <div>
                        <h5 class="fw-bold mb-0" style="color: #78350F;"><?= e($data_user['nama']) ?></h5>
                        <small class="text-muted fw-semibold"><?= e($data_user['jabatan']) ?></small>
                    </div>
                    <span class="badge-soft badge-blue">
                        <i class="fas fa-store me-1"></i><?= e($data_user['nama_cabang'] ?: 'Outlet Erlangga') ?>
                    </span>
                </div>

                <!-- Ringkasan Kehadiran Bulan Berjalan -->
                <div class="summary-accent-box text-center">
                    <span class="small text-uppercase fw-bold" style="color: #92400E; letter-spacing: 0.05em;">Akumulasi Kehadiran Bulan Ini</span>
                    <div class="fs-2 fw-bold font-monospace mt-1" style="color: #78350F;">
                        <?php if ($data_user['status_kerja'] === 'Parttime'): ?>
                            <?= $total_jam_kerja ?> <span class="fs-6 fw-bold" style="color: #B45309;">Jam</span> <?= $sisa_menit ?> <span class="fs-6 fw-bold" style="color: #B45309;">Menit</span>
                        <?php else: ?>
                            <?= $total_hari ?> <span class="fs-6 fw-bold" style="color: #B45309;">Hari Hadir</span>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Tabel Log Harian -->
                <h6 class="small fw-bold text-uppercase mb-2" style="color: #92400E; letter-spacing: 0.05em;">Riwayat Harian (<?= date('F Y') ?>)</h6>
                    
                    <?php if (empty($history_rows)): ?>
                        <p class="small text-muted text-center py-3">Belum ada catatan presensi pada bulan ini.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-sm mb-0" style="font-size:0.84rem;">
                                <thead>
                                    <tr class="text-muted">
                                        <th>Tgl</th>
                                        <th>Shift / Cabang</th>
                                        <th>Masuk</th>
                                        <th>Pulang</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($history_rows as $hr): 
                                        $is_iz = ($hr['shift'] === 'Izin');
                                    ?>
                                        <tr>
                                            <td class="font-monospace fw-semibold"><?= date('d/m', strtotime($hr['tanggal'])) ?></td>
                                            <td>
                                                <?php if ($is_iz): ?>
                                                    <span class="badge-soft badge-yellow">Izin</span>
                                                    <div class="text-muted" style="font-size:0.7rem;"><?= e($hr['keterangan']) ?></div>
                                                <?php else: ?>
                                                    <div class="fw-semibold text-dark"><?= e($hr['shift']) ?></div>
                                                    <div class="text-muted" style="font-size:0.7rem;"><?= e($hr['nama_cabang'] ?: 'Outlet Erlangga') ?></div>
                                                <?php endif; ?>
                                            </td>
                                            <td class="font-monospace">
                                                <?= $is_iz ? '-' : substr($hr['jam_masuk'], 0, 5) ?>
                                                <?php if ($hr['terlambat_menit'] > 0): ?>
                                                    <br><span class="badge-soft badge-red">+<?= $hr['terlambat_menit'] ?>m</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="font-monospace">
                                                <?= $is_iz ? '-' : ($hr['jam_pulang'] ? substr($hr['jam_pulang'], 0, 5) : '<span class="text-muted">Belum</span>') ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>

            </div>
        <?php endif; ?>

    </div>

</body>
</html>