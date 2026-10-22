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

// =======================================================================
// HANDLER UPDATE, TAMBAH, HAPUS SHIFT DENGAN CSRF & PREPARED STATEMENTS
// =======================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token()) {
        $alert_msg = "Sesi telah kedaluwarsa. Silakan refresh halaman.";
        $alert_type = "danger";
    } else {

        // 1. Update Jam Shift
        if (isset($_POST['simpan'])) {
            $id     = (int)$_POST['id'];
            $nama   = trim((string)$_POST['nama_shift']);
            $masuk  = trim((string)$_POST['masuk']);
            $pulang = trim((string)$_POST['pulang']);

            $stmt = mysqli_prepare($conn, "UPDATE shift_settings SET nama_shift = ?, jam_masuk = ?, jam_pulang = ? WHERE id = ?");
            mysqli_stmt_bind_param($stmt, "sssi", $nama, $masuk, $pulang, $id);
            mysqli_stmt_execute($stmt);

            $alert_msg = "Aturan jam shift berhasil diperbarui.";
            $alert_type = "success";
        }

        // 2. Tambah Shift Baru
        elseif (isset($_POST['tambah'])) {
            $kode   = strtoupper(trim((string)$_POST['kode_shift']));
            $nama   = trim((string)$_POST['nama_shift']);
            $masuk  = trim((string)$_POST['jam_masuk']);
            $pulang = trim((string)$_POST['jam_pulang']);

            if ($kode !== '' && $nama !== '') {
                $stmt = mysqli_prepare($conn, "INSERT INTO shift_settings (kode_shift, nama_shift, jam_masuk, jam_pulang) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE nama_shift = VALUES(nama_shift), jam_masuk = VALUES(jam_masuk), jam_pulang = VALUES(jam_pulang)");
                mysqli_stmt_bind_param($stmt, "ssss", $kode, $nama, $masuk, $pulang);
                mysqli_stmt_execute($stmt);

                $alert_msg = "Shift baru berhasil ditambahkan.";
                $alert_type = "success";
            }
        }

        // 3. Hapus Shift
        elseif (isset($_POST['hapus'])) {
            $id = (int)$_POST['id'];
            $stmt = mysqli_prepare($conn, "DELETE FROM shift_settings WHERE id = ?");
            mysqli_stmt_bind_param($stmt, "i", $id);
            mysqli_stmt_execute($stmt);

            $alert_msg = "Shift berhasil dihapus.";
            $alert_type = "success";
        }
    }
}

$shifts = mysqli_query($conn, "SELECT * FROM shift_settings ORDER BY kode_shift ASC");
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pengaturan Shift Kerja | E-Presensi</title>
    
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

        .btn-filter-action {
            background: #D97706;
            color: #FFFFFF;
            border: none;
            font-weight: 800;
            border-radius: 12px;
            box-shadow: 0 4px 14px rgba(217, 119, 6, 0.25);
            transition: all 0.2s ease;
        }

        .btn-filter-action:hover {
            background: #B45309;
            color: #FFFFFF;
            transform: translateY(-1px);
        }

        .form-control {
            border: 1.5px solid #FDE68A;
            border-radius: 10px;
            background: #FFFDF9;
            color: var(--text-dark);
            font-weight: 600;
        }

        .form-control:focus {
            background: #FFFFFF;
            border-color: #D97706;
            box-shadow: 0 0 0 3px rgba(217, 119, 6, 0.15);
        }

        .badge-shift-ft {
            background: #EEF2FF;
            color: #3730A3;
            border: 1.5px solid #C7D2FE;
            font-weight: 800;
            padding: 5px 10px;
            border-radius: 8px;
        }

        .badge-shift-pt {
            background: #FEF3C7;
            color: #92400E;
            border: 1.5px solid #FDE68A;
            font-weight: 800;
            padding: 5px 10px;
            border-radius: 8px;
        }

        .badge-shift-lb {
            background: #FAF5FF;
            color: #6B21A8;
            border: 1.5px solid #E9D5FF;
            font-weight: 800;
            padding: 5px 10px;
            border-radius: 8px;
        }
    </style>
</head>
<body>

    <div class="admin-layout">
        <?php require __DIR__ . '/sidebar.php'; ?>
        
        <div class="admin-main">
            <?php renderMobileTopbar('Atur Shift'); ?>
            
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
                <h4 class="fw-bold mb-0" style="color: #78350F; font-family: 'Outfit', sans-serif;">Aturan Jam Kerja & Toleransi Shift</h4>
                <small class="text-muted fw-semibold">Konfigurasi jadwal masuk, batas toleransi telat, dan syarat kepulangan presensi.</small>
            </div>
            <button type="button" class="btn btn-filter-action fw-bold px-3 py-2" data-bs-toggle="modal" data-bs-target="#modalTambahShift">
                <i class="fas fa-plus me-1"></i> Tambah Shift
            </button>
        </div>

        <div class="table-panel">
            <div class="table-responsive">
                <table class="table mb-0 align-middle">
                    <thead>
                        <tr>
                            <th style="width: 120px;">Kode Shift</th>
                            <th>Nama Shift</th>
                            <th style="width: 180px;">Jam Masuk (Batas Telat)</th>
                            <th style="width: 180px;">Jam Pulang (Syarat Pulang)</th>
                            <th class="text-center" style="width: 150px;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($s = mysqli_fetch_assoc($shifts)): 
                            $kd = strtoupper($s['kode_shift']);
                            $badge_class = str_starts_with($kd, 'FT') ? 'badge-shift-ft' : (str_starts_with($kd, 'PT') ? 'badge-shift-pt' : 'badge-shift-lb');
                        ?>
                            <tr>
                                <td>
                                    <span class="<?= $badge_class ?> font-monospace"><?= e($s['kode_shift']) ?></span>
                                </td>
                                <td>
                                    <form method="POST" id="form-shift-<?= $s['id'] ?>">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="simpan" value="1">
                                        <input type="hidden" name="id" value="<?= $s['id'] ?>">
                                        <input type="text" name="nama_shift" class="form-control form-control-sm fw-semibold" value="<?= e($s['nama_shift']) ?>" required>
                                </td>
                                <td>
                                        <input type="time" name="masuk" class="form-control form-control-sm font-monospace" value="<?= e($s['jam_masuk']) ?>" required>
                                </td>
                                <td>
                                        <input type="time" name="pulang" class="form-control form-control-sm font-monospace" value="<?= e($s['jam_pulang']) ?>" required>
                                </td>
                                <td class="text-center">
                                    <div class="d-inline-flex gap-1">
                                        <button type="submit" class="btn btn-sm btn-filter-action px-3" title="Simpan Perubahan">
                                            <i class="fas fa-save me-1"></i> Simpan
                                        </button>
                                    </form>
                                        <form method="POST" onsubmit="return confirm('Hapus shift <?= e($s['nama_shift']) ?>?')" style="display:inline;">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="hapus" value="1">
                                            <input type="hidden" name="id" value="<?= $s['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger" title="Hapus Shift">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="mt-4 p-4 rounded-4" style="background:#FFFDF9; border: 1.5px solid #FDE68A; box-shadow: 0 4px 14px rgba(217, 119, 6, 0.06);">
            <h6 class="fw-bold mb-2" style="color: #78350F;"><i class="fas fa-circle-info me-2 text-warning"></i>Panduan Jam Kerja Operasional:</h6>
            <ul class="mb-0 ps-3 small fw-semibold text-muted">
                <li>Sistem saat ini dikonfigurasi dengan <strong>1 Shift Operasional Tunggal</strong> (Pukul <strong>14:00</strong> s/d <strong>21:30</strong>) yang berlaku untuk seluruh staf.</li>
                <li>Jika ingin menambahkan variasi shift terpisah di masa mendatang, awali kode dengan <strong>FT</strong> untuk Fulltime, <strong>PT</strong> untuk Parttime, atau <strong>LB</strong> untuk Lembur.</li>
            </ul>
        </div>

            </div> <!-- /.admin-content -->
        </div> <!-- /.admin-main -->
    </div> <!-- /.admin-layout -->

    <!-- MODAL TAMBAH SHIFT -->
    <div class="modal fade" id="modalTambahShift" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content" style="border-radius: 20px; border: 1.5px solid #FDE68A;">
                <div class="modal-header border-bottom py-3" style="background: #FEF3C7;">
                    <h6 class="modal-title fw-bold text-dark"><i class="fas fa-business-time me-2 text-warning"></i>Tambah Jadwal Shift Baru</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <?= csrf_field() ?>
                    <input type="hidden" name="tambah" value="1">
                    <div class="modal-body p-4">
                        <div class="mb-3">
                            <label class="small fw-bold text-muted mb-1">Kode Shift (Awalan FT, PT, atau LB)</label>
                            <input type="text" name="kode_shift" class="form-control font-monospace" placeholder="Contoh: FT3, PT2, LB2" required maxlength="10">
                        </div>
                        <div class="mb-3">
                            <label class="small fw-bold text-muted mb-1">Nama Shift</label>
                            <input type="text" name="nama_shift" class="form-control" placeholder="Contoh: Shift Siang Fulltime" required>
                        </div>
                        <div class="row g-2 mb-2">
                            <div class="col-6">
                                <label class="small fw-bold text-muted mb-1">Jam Masuk</label>
                                <input type="time" name="jam_masuk" class="form-control font-monospace" required value="08:00">
                            </div>
                            <div class="col-6">
                                <label class="small fw-bold text-muted mb-1">Jam Pulang</label>
                                <input type="time" name="jam_pulang" class="form-control font-monospace" required value="16:00">
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer border-top p-3">
                        <button type="submit" class="btn btn-filter-action w-100 fw-bold py-2">Tambahkan Shift</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>