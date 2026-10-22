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

// Ambil Daftar Cabang
$cabang_all = [];
$q_cab = mysqli_query($conn, "SELECT id, nama_cabang FROM cabang ORDER BY id ASC");
while ($c = mysqli_fetch_assoc($q_cab)) {
    $cabang_all[$c['id']] = $c['nama_cabang'];
}

// =======================================================================
// HANDLER AKSI-AKSI (TAMBAH, EDIT, HAPUS KARYAWAN) DENGAN CSRF & PREPARED
// =======================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token()) {
        $alert_msg = "Sesi telah kedaluwarsa. Silakan refresh halaman.";
        $alert_type = "danger";
    } else {

        // 1. Tambah Karyawan Baru
        if (isset($_POST['tambah'])) {
            $nama      = trim((string)$_POST['nama']);
            $jabatan   = trim((string)$_POST['jabatan']);
            $status    = trim((string)$_POST['status_kerja']);
            $pin       = trim((string)$_POST['pin']) ?: '1234';
            $cabang_id = !empty($_POST['cabang_id']) ? (int)$_POST['cabang_id'] : 1;

            if ($nama !== '') {
                $pin_hash = password_hash($pin, PASSWORD_BCRYPT);
                $stmt = mysqli_prepare($conn, "INSERT INTO users (nama, jabatan, status_kerja, pin, cabang_id) VALUES (?, ?, ?, ?, ?)");
                mysqli_stmt_bind_param($stmt, "ssssi", $nama, $jabatan, $status, $pin_hash, $cabang_id);
                mysqli_stmt_execute($stmt);

                $alert_msg = "Karyawan <strong>" . e($nama) . "</strong> berhasil ditambahkan.";
                $alert_type = "success";
            }
        }

        // 2. Edit Karyawan
        elseif (isset($_POST['edit'])) {
            $id        = (int)$_POST['id'];
            $nama      = trim((string)$_POST['nama']);
            $jabatan   = trim((string)$_POST['jabatan']);
            $status    = trim((string)$_POST['status_kerja']);
            $cabang_id = !empty($_POST['cabang_id']) ? (int)$_POST['cabang_id'] : 1;
            $reset_pin = !empty($_POST['reset_pin_default']);
            $pin_baru  = $reset_pin ? '1234' : trim((string)$_POST['pin']);

            if ($nama !== '') {
                if ($pin_baru !== '') {
                    $pin_hash = password_hash($pin_baru, PASSWORD_BCRYPT);
                    $stmt = mysqli_prepare($conn, "UPDATE users SET nama = ?, jabatan = ?, status_kerja = ?, cabang_id = ?, pin = ? WHERE id = ?");
                    mysqli_stmt_bind_param($stmt, "sssisi", $nama, $jabatan, $status, $cabang_id, $pin_hash, $id);
                } else {
                    $stmt = mysqli_prepare($conn, "UPDATE users SET nama = ?, jabatan = ?, status_kerja = ?, cabang_id = ? WHERE id = ?");
                    mysqli_stmt_bind_param($stmt, "sssii", $nama, $jabatan, $status, $cabang_id, $id);
                }
                mysqli_stmt_execute($stmt);

                $alert_msg = "Data karyawan berhasil diperbarui." . ($reset_pin ? " PIN direset ke <strong>1234</strong>." : "");
                $alert_type = "success";
            }
        }

        // 3. Hapus Karyawan
        elseif (isset($_POST['hapus'])) {
            $id = (int)$_POST['id'];
            $stmt = mysqli_prepare($conn, "DELETE FROM users WHERE id = ?");
            mysqli_stmt_bind_param($stmt, "i", $id);
            mysqli_stmt_execute($stmt);

            $alert_msg = "Data karyawan telah dihapus dari sistem.";
            $alert_type = "success";
        }
    }
}

// Ambil Seluruh Data Karyawan
$q_users = mysqli_query($conn, "SELECT users.*, cabang.nama_cabang 
    FROM users 
    LEFT JOIN cabang ON users.cabang_id = cabang.id 
    ORDER BY users.id DESC");
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Pegawai | E-Presensi</title>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Outfit:wght@500;600;700;800&display=swap" rel="stylesheet">
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
        .badge-yellow { background: #FFFBEB; color: #92400E; border: 1.5px solid #FDE68A; }
        .badge-blue { background: #EFF6FF; color: #1E40AF; border: 1.5px solid #BFDBFE; }

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

        .form-control, .form-select {
            border: 1.5px solid #FDE68A;
            border-radius: 10px;
            background: #FFFDF9;
            color: var(--text-dark);
            font-weight: 600;
        }

        .form-control:focus, .form-select:focus {
            background: #FFFFFF;
            border-color: #D97706;
            box-shadow: 0 0 0 3px rgba(217, 119, 6, 0.15);
        }
    </style>
</head>
<body>

    <div class="admin-layout">
        <?php require __DIR__ . '/sidebar.php'; ?>
        
        <div class="admin-main">
            <?php renderMobileTopbar('Data Karyawan'); ?>
            
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
                <h4 class="fw-bold mb-0" style="color: #78350F; font-family: 'Outfit', sans-serif;">Daftar Karyawan</h4>
                <small class="text-muted fw-semibold">Kelola data pegawai, hak cabang penugasan, dan token PIN.</small>
            </div>
            <button type="button" class="btn btn-filter-action fw-bold px-3 py-2" data-bs-toggle="modal" data-bs-target="#modalTambah">
                <i class="fas fa-user-plus me-1"></i> Tambah Karyawan
            </button>
        </div>

        <div class="table-panel">
            <div class="table-responsive">
                <table class="table mb-0 align-middle">
                    <thead>
                        <tr>
                            <th style="width: 50px;">No</th>
                            <th>Nama Lengkap</th>
                            <th>Jabatan</th>
                            <th>Status Kerja</th>
                            <th>Cabang Penugasan</th>
                            <th>Status PIN</th>
                            <th class="text-center" style="width: 120px;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $no = 1;
                        if (mysqli_num_rows($q_users) === 0): ?>
                            <tr><td colspan="7" class="text-center py-5 text-muted">Belum ada data karyawan.</td></tr>
                        <?php endif;

                        while ($u = mysqli_fetch_assoc($q_users)): ?>
                            <tr>
                                <td class="font-monospace text-muted"><?= $no++ ?></td>
                                <td class="fw-bold text-dark"><?= e($u['nama']) ?></td>
                                <td class="text-muted"><?= e($u['jabatan']) ?></td>
                                <td>
                                    <?php if ($u['status_kerja'] === 'Fulltime'): ?>
                                        <span class="badge-soft badge-green">Fulltime</span>
                                    <?php elseif ($u['status_kerja'] === 'Parttime'): ?>
                                        <span class="badge-soft badge-yellow">Parttime</span>
                                    <?php else: ?>
                                        <span class="badge-soft badge-red">Lembur</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge-soft badge-blue">
                                        <i class="fas fa-store me-1"></i><?= e($u['nama_cabang'] ?: 'Outlet Pusat') ?>
                                    </span>
                                </td>
                                <td>
                                    <?php
                                    $p_val = $u['pin'] ?? '';
                                    if (strlen($p_val) >= 60 && strpos($p_val, '$') === 0): ?>
                                        <span class="badge-soft badge-green"><i class="fas fa-shield-halved me-1"></i>Terenkripsi</span>
                                    <?php elseif ($p_val === '1234'): ?>
                                        <span class="badge-soft badge-yellow"><i class="fas fa-key me-1"></i>Default (1234)</span>
                                    <?php elseif (strlen($p_val) < 60 && strpos($p_val, '$') === 0): ?>
                                        <span class="badge-soft badge-red"><i class="fas fa-triangle-exclamation me-1"></i>Korup (Perlu Edit)</span>
                                    <?php else: ?>
                                        <span class="badge-soft badge-blue"><i class="fas fa-lock me-1"></i>Kustom</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <div class="d-inline-flex gap-1">
                                        <button class="btn btn-sm btn-rate-action" data-bs-toggle="modal" data-bs-target="#modalEdit<?= $u['id'] ?>">
                                            <i class="fas fa-pen-to-square"></i>
                                        </button>
                                        <form method="POST" onsubmit="return confirm('Hapus karyawan <?= e($u['nama']) ?> secara permanen?')" style="display:inline;">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="hapus" value="1">
                                            <input type="hidden" name="id" value="<?= $u['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>

                            <!-- MODAL EDIT KARYAWAN -->
                            <div class="modal fade" id="modalEdit<?= $u['id'] ?>" tabindex="-1">
                                <div class="modal-dialog modal-dialog-centered">
                                    <div class="modal-content" style="border-radius: 20px; border: 1.5px solid #FDE68A;">
                                        <div class="modal-header border-bottom py-3" style="background: #FEF3C7;">
                                            <h6 class="modal-title fw-bold text-dark"><i class="fas fa-user-pen me-2 text-warning"></i>Edit Pegawai: <?= e($u['nama']) ?></h6>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                        </div>
                                        <form method="POST">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="edit" value="1">
                                            <input type="hidden" name="id" value="<?= $u['id'] ?>">
                                            <div class="modal-body p-4">
                                                <div class="mb-3">
                                                    <label class="small fw-bold text-muted mb-1">Nama Lengkap</label>
                                                    <input type="text" name="nama" class="form-control" value="<?= e($u['nama']) ?>" required>
                                                </div>
                                                <div class="mb-3">
                                                    <label class="small fw-bold text-muted mb-1">Jabatan</label>
                                                    <input type="text" name="jabatan" class="form-control" value="<?= e($u['jabatan']) ?>" required>
                                                </div>
                                                <div class="row g-2 mb-3">
                                                    <div class="col-6">
                                                        <label class="small fw-bold text-muted mb-1">Status Kepegawaian</label>
                                                        <select name="status_kerja" class="form-select">
                                                            <option value="Fulltime" <?= $u['status_kerja'] === 'Fulltime' ? 'selected' : '' ?>>Fulltime</option>
                                                            <option value="Parttime" <?= $u['status_kerja'] === 'Parttime' ? 'selected' : '' ?>>Parttime</option>
                                                            <option value="Lembur" <?= $u['status_kerja'] === 'Lembur' ? 'selected' : '' ?>>Lembur</option>
                                                        </select>
                                                    </div>
                                                    <div class="col-6">
                                                        <label class="small fw-bold text-muted mb-1">Cabang Penugasan</label>
                                                        <select name="cabang_id" class="form-select">
                                                            <?php foreach ($cabang_all as $c_id => $c_name): ?>
                                                                <option value="<?= $c_id ?>" <?= (int)$u['cabang_id'] === $c_id ? 'selected' : '' ?>><?= e($c_name) ?></option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    </div>
                                                </div>
                                                <div class="mb-2">
                                                    <label class="small fw-bold text-muted mb-1">Ubah PIN Keamanan <small>(Kosongkan jika tidak diubah)</small></label>
                                                    <div class="input-group">
                                                        <input type="password" name="pin" id="pinEdit<?= $u['id'] ?>" class="form-control font-monospace" placeholder="4-6 digit angka" maxlength="6" inputmode="numeric">
                                                        <button type="button" class="btn btn-outline-secondary" onclick="togglePass('pinEdit<?= $u['id'] ?>')">
                                                            <i class="fas fa-eye" id="icon-pinEdit<?= $u['id'] ?>"></i>
                                                        </button>
                                                    </div>
                                                    <div class="form-check mt-2">
                                                        <input class="form-check-input" type="checkbox" name="reset_pin_default" value="1" id="resetPin<?= $u['id'] ?>" onchange="const inp = document.getElementById('pinEdit<?= $u['id'] ?>'); if(this.checked){ inp.value='1234'; inp.type='text'; }">
                                                        <label class="form-check-label small text-muted" for="resetPin<?= $u['id'] ?>">
                                                            Reset ke PIN Default (<strong>1234</strong>)
                                                        </label>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="modal-footer border-top p-3">
                                                <button type="submit" class="btn btn-filter-action w-100 fw-bold py-2">Simpan Perubahan</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>

            </div> <!-- /.admin-content -->
        </div> <!-- /.admin-main -->
    </div> <!-- /.admin-layout -->

    <!-- MODAL TAMBAH KARYAWAN -->
    <div class="modal fade" id="modalTambah" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content" style="border-radius: 20px; border: 1.5px solid #FDE68A;">
                <div class="modal-header border-bottom py-3" style="background: #FEF3C7;">
                    <h6 class="modal-title fw-bold text-dark"><i class="fas fa-user-plus me-2 text-warning"></i>Tambah Pegawai Baru</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <?= csrf_field() ?>
                    <input type="hidden" name="tambah" value="1">
                    <div class="modal-body p-4">
                        <div class="mb-3">
                            <label class="small fw-bold text-muted mb-1">Nama Lengkap</label>
                            <input type="text" name="nama" class="form-control" placeholder="Nama karyawan..." required>
                        </div>
                        <div class="mb-3">
                            <label class="small fw-bold text-muted mb-1">Jabatan / Posisi</label>
                            <input type="text" name="jabatan" class="form-control" placeholder="Contoh: Staff Operasional, Kasir" required>
                        </div>
                        <div class="row g-2 mb-3">
                            <div class="col-6">
                                <label class="small fw-bold text-muted mb-1">Status Kepegawaian</label>
                                <select name="status_kerja" class="form-select">
                                    <option value="Fulltime">Fulltime</option>
                                    <option value="Parttime">Parttime</option>
                                    <option value="Lembur">Lembur</option>
                                </select>
                            </div>
                            <div class="col-6">
                                <label class="small fw-bold text-muted mb-1">Cabang Penugasan</label>
                                <select name="cabang_id" class="form-select">
                                    <?php foreach ($cabang_all as $c_id => $c_name): ?>
                                        <option value="<?= $c_id ?>"><?= e($c_name) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="mb-2">
                            <label class="small fw-bold text-muted mb-1">PIN Keamanan Awal</label>
                            <div class="input-group">
                                <input type="password" name="pin" id="pinTambah" class="form-control font-monospace" placeholder="Default: 1234" maxlength="6" value="1234" inputmode="numeric">
                                <button type="button" class="btn btn-outline-secondary" onclick="togglePass('pinTambah')">
                                    <i class="fas fa-eye" id="icon-pinTambah"></i>
                                </button>
                            </div>
                            <small class="text-muted d-block mt-1">Gunakan 4–6 digit angka (Default: <strong>1234</strong>).</small>
                        </div>
                    </div>
                    <div class="modal-footer border-top p-3">
                        <button type="submit" class="btn btn-filter-action w-100 fw-bold py-2">Tambahkan Pegawai</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    function togglePass(inputId) {
        const input = document.getElementById(inputId);
        const icon = document.getElementById('icon-' + inputId);
        if (!input) return;
        if (input.type === 'password') {
            input.type = 'text';
            if (icon) { icon.classList.remove('fa-eye'); icon.classList.add('fa-eye-slash'); }
        } else {
            input.type = 'password';
            if (icon) { icon.classList.remove('fa-eye-slash'); icon.classList.add('fa-eye'); }
        }
    }
    </script>
</body>
</html>