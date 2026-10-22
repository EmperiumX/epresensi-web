<?php
ob_start();
require_once dirname(__DIR__) . '/config/session.php';
require_once dirname(__DIR__) . '/config/database.php';

// Proteksi Akses Admin
if (!isset($_SESSION['admin']) || $_SESSION['admin'] !== true) {
    header("Location: ../login.php");
    exit;
}

$alert_msg = "";
$alert_type = "info";

// Toggle Hak Akses via POST & CSRF Guard
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_akses'])) {
    if (!verify_csrf_token()) {
        $alert_msg = "Sesi telah kedaluwarsa. Silakan refresh halaman.";
        $alert_type = "danger";
    } else {
        $user_id = (int)$_POST['user_id'];
        $val     = (int)$_POST['val'] === 1 ? 1 : 0;

        $stmt = mysqli_prepare($conn, "UPDATE users SET akses_admin = ? WHERE id = ?");
        mysqli_stmt_bind_param($stmt, "ii", $val, $user_id);
        mysqli_stmt_execute($stmt);

        $alert_msg = "Hak akses admin berhasil diperbarui.";
        $alert_type = "success";
    }
}

// Ambil Karyawan
$q_users = mysqli_query($conn, "SELECT users.*, cabang.nama_cabang 
    FROM users 
    LEFT JOIN cabang ON users.cabang_id = cabang.id 
    ORDER BY users.nama ASC");
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hak Akses Admin | E-Presensi</title>
    
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

        /* Top Brand Navigation Bar */
        .admin-nav {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(14px);
            -webkit-backdrop-filter: blur(14px);
            border-bottom: 2px solid #FDE68A;
            padding: 12px 24px;
            box-shadow: 0 4px 20px -2px rgba(217, 119, 6, 0.08);
        }

        .brand-badge {
            width: 42px;
            height: 42px;
            border-radius: 12px;
            background: #FFFBEB;
            border: 1.5px solid #FCD34D;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 3px;
            box-shadow: 0 2px 6px rgba(245, 158, 11, 0.15);
        }

        .brand-badge img {
            width: 100%;
            height: 100%;
            object-fit: contain;
        }

        .brand-title {
            font-family: 'Outfit', sans-serif;
            font-weight: 800;
            font-size: 1.05rem;
            color: #78350F;
            line-height: 1.1;
        }

        .brand-subtitle {
            font-size: 0.72rem;
            color: #B45309;
            font-weight: 600;
        }

        .nav-link {
            font-size: 0.88rem;
            font-weight: 700;
            color: #78350F !important;
            padding: 8px 14px !important;
            border-radius: 10px;
            transition: all 0.2s ease;
        }

        .nav-link:hover {
            color: #92400E !important;
            background: #FEF3C7;
        }

        .nav-link.active {
            color: #FFFFFF !important;
            background: #D97706 !important;
            box-shadow: 0 4px 12px rgba(217, 119, 6, 0.25);
        }

        .btn-nav-danger {
            background: #E11D48;
            color: #FFFFFF;
            border: none;
            font-weight: 700;
            border-radius: 10px;
            box-shadow: 0 4px 12px rgba(225, 29, 72, 0.25);
            transition: all 0.2s ease;
        }

        .btn-nav-danger:hover {
            background: #BE123C;
            color: #FFFFFF;
        }

        .table-panel {
            background: #FFFFFF;
            border: 1.5px solid #FDE68A;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 12px 30px -4px rgba(217, 119, 6, 0.08);
        }

        .table thead th {
            background: #FEF3C7;
            color: #78350F;
            font-size: 0.78rem;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            font-weight: 800;
            padding: 14px 16px;
            border-bottom: 2px solid #FCD34D;
        }

        .table tbody td {
            padding: 14px 16px;
            font-size: 0.88rem;
            vertical-align: middle;
            border-bottom: 1px solid #FEF3C7;
            color: var(--text-dark);
        }

        .table tbody tr:hover td {
            background-color: #FFFDF7;
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
        .badge-blue { background: #EFF6FF; color: #1E40AF; border: 1.5px solid #BFDBFE; }

        .btn-rate-action {
            background: #FFFBEB;
            color: #92400E;
            border: 1.5px solid #FCD34D;
            font-weight: 700;
            border-radius: 8px;
            transition: all 0.2s ease;
        }

        .btn-rate-action:hover {
            background: #FEF3C7;
            color: #78350F;
        }
    </style>
</head>
<body>

    <div class="admin-layout">
        <?php require __DIR__ . '/sidebar.php'; ?>
        
        <div class="admin-main">
            <?php renderMobileTopbar('Hak Akses Pengelola'); ?>
            
            <div class="admin-content">

        <?php if (!empty($alert_msg)): ?>
            <div class="alert alert-<?= $alert_type ?> alert-dismissible fade show d-flex align-items-center mb-4" role="alert" style="border-radius: 14px; border: 1.5px solid currentColor;">
                <i class="fas fa-info-circle me-2 fs-5"></i>
                <div class="fw-semibold"><?= $alert_msg ?></div>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
            <div>
                <h4 class="fw-bold mb-0" style="color: #78350F; font-family: 'Outfit', sans-serif;">Izin Akses Masuk Dasbor Pengelola</h4>
                <small class="text-muted fw-semibold">Tentukan staf atau store manager cabang yang diizinkan login ke panel pengelola dengan PIN mereka.</small>
            </div>
        </div>

        <div class="table-panel">
            <div class="table-responsive">
                <table class="table mb-0 align-middle">
                    <thead>
                        <tr>
                            <th style="width: 50px;">No</th>
                            <th>Nama Pegawai</th>
                            <th>Jabatan</th>
                            <th>Cabang</th>
                            <th>Status Akses Admin</th>
                            <th class="text-center" style="width: 180px;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $no = 1;
                        while ($u = mysqli_fetch_assoc($q_users)): 
                            $has_access = (int)$u['akses_admin'] === 1;
                        ?>
                            <tr>
                                <td class="font-monospace text-muted"><?= $no++ ?></td>
                                <td class="fw-bold text-dark"><?= e($u['nama']) ?></td>
                                <td class="text-muted"><?= e($u['jabatan']) ?></td>
                                <td>
                                    <span class="badge-soft badge-blue">
                                        <i class="fas fa-store me-1"></i><?= e($u['nama_cabang'] ?: 'Outlet Erlangga (Pusat)') ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($has_access): ?>
                                        <span class="badge-soft badge-green"><i class="fas fa-check-circle me-1"></i>Diizinkan Masuk</span>
                                    <?php else: ?>
                                        <span class="badge-soft badge-red"><i class="fas fa-ban me-1"></i>Akses Dibatasi</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <form method="POST" style="display:inline;">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="toggle_akses" value="1">
                                        <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                        <input type="hidden" name="val" value="<?= $has_access ? 0 : 1 ?>">
                                        <?php if ($has_access): ?>
                                            <button type="submit" class="btn btn-sm btn-outline-danger" title="Cabut Hak Akses">
                                                <i class="fas fa-lock me-1"></i> Cabut Izin
                                            </button>
                                        <?php else: ?>
                                            <button type="submit" class="btn btn-sm btn-rate-action px-3" title="Beri Hak Akses">
                                                <i class="fas fa-key me-1 text-warning"></i> Beri Akses
                                            </button>
                                        <?php endif; ?>
                                    </form>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>

            </div> <!-- /.admin-content -->
        </div> <!-- /.admin-main -->
    </div> <!-- /.admin-layout -->

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
