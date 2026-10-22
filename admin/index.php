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

// 1. Ambil Tarif Gaji per Menit dari Database
$q_gaji = mysqli_query($conn, "SELECT nilai FROM settings WHERE nama_setting='gaji_per_menit'");
$gaji_per_menit = 250;
if ($q_gaji && $r_g = mysqli_fetch_assoc($q_gaji)) {
    $gaji_per_menit = (int)$r_g['nilai'];
}

// 2. Ambil Daftar Semua Cabang untuk Filter & Form
$cabang_all = [];
$q_cab = mysqli_query($conn, "SELECT * FROM cabang ORDER BY id ASC");
while ($c = mysqli_fetch_assoc($q_cab)) {
    $cabang_all[$c['id']] = $c;
}

// =======================================================================
// HANDLER AKSI-AKSI POST (DILENGKAPI CSRF GUARD & PREPARED STATEMENTS)
// =======================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token()) {
        $alert_msg = "Sesi form kedaluwarsa. Silakan ulangi aksi Anda.";
        $alert_type = "danger";
    } else {

        // A. Simpan Tarif Gaji
        if (isset($_POST['simpan_gaji'])) {
            $gaji_baru = (int)$_POST['gaji_baru'];
            $stmt = mysqli_prepare($conn, "INSERT INTO settings (nama_setting, nilai) VALUES ('gaji_per_menit', ?) ON DUPLICATE KEY UPDATE nilai = ?");
            mysqli_stmt_bind_param($stmt, "ss", $gaji_baru, $gaji_baru);
            mysqli_stmt_execute($stmt);
            $gaji_per_menit = $gaji_baru;
            $alert_msg = "Tarif gaji per menit berhasil diperbarui.";
            $alert_type = "success";
        }

        // B. Simpan Input Absensi Manual (Owner)
        elseif (isset($_POST['simpan_manual'])) {
            $u_id   = (int)$_POST['user_id_man'];
            $cab_id = (int)$_POST['cabang_id_man'];
            $tgl    = trim($_POST['tgl_manual']);
            $jenis  = trim($_POST['jenis_manual']);
            $ket    = trim($_POST['ket_man']);
            $st_kerja = isset($_POST['status_kerja_man']) ? trim($_POST['status_kerja_man']) : '';

            // Validasi status kerja yang diperbolehkan
            if (!in_array($st_kerja, ['Fulltime', 'Parttime', 'Lembur'])) {
                // Fallback ambil status karyawan dari tabel users jika tidak terdefinisi
                $stmt_u = mysqli_prepare($conn, "SELECT status_kerja FROM users WHERE id = ?");
                mysqli_stmt_bind_param($stmt_u, "i", $u_id);
                mysqli_stmt_execute($stmt_u);
                $res_u = mysqli_stmt_get_result($stmt_u);
                $st_kerja = ($r = mysqli_fetch_assoc($res_u)) ? $r['status_kerja'] : 'Fulltime';
            }

            if ($jenis === 'Hadir') {
                $jam_m = trim($_POST['jam_m_manual']) ?: "07:00:00";
                $jam_p = !empty($_POST['jam_p_manual']) ? trim($_POST['jam_p_manual']) : null;
                $shift_label = !empty($_POST['shift_manual']) ? trim($_POST['shift_manual']) : ($st_kerja === 'Lembur' ? 'Shift Lembur Penuh' : 'Input Manual');

                $stmt_ins = mysqli_prepare($conn, "INSERT INTO attendance 
                    (user_id, cabang_id, tanggal, shift, jam_masuk, jam_pulang, keterangan, status_kerja, terlambat_menit, foto_masuk) 
                    VALUES (?, ?, ?, ?, ?, ?, 'Diabsen Owner (Manual)', ?, 0, '')");
                mysqli_stmt_bind_param($stmt_ins, "iisssss", $u_id, $cab_id, $tgl, $shift_label, $jam_m, $jam_p, $st_kerja);
                mysqli_stmt_execute($stmt_ins);
            } else {
                $stmt_ins = mysqli_prepare($conn, "INSERT INTO attendance 
                    (user_id, cabang_id, tanggal, shift, jam_masuk, keterangan, status_kerja, terlambat_menit, foto_masuk) 
                    VALUES (?, ?, ?, 'Izin', '08:00:00', ?, 'Izin', 0, '')");
                mysqli_stmt_bind_param($stmt_ins, "iisss", $u_id, $cab_id, $tgl, $ket);
                mysqli_stmt_execute($stmt_ins);
            }

            $alert_msg = "Presensi manual (" . e($st_kerja) . ") berhasil dicatat.";
            $alert_type = "success";
        }

        // C. Koreksi / Edit Data Absensi (Ubah Status Lembur/Fulltime, Jam, Cabang)
        elseif (isset($_POST['simpan_edit_absen'])) {
            $id_edit   = (int)$_POST['id_absen_edit'];
            $st_kerja  = trim($_POST['status_kerja_edit']);
            $shift_edt = trim($_POST['shift_edit']);
            $jam_m_edt = trim($_POST['jam_m_edit']) ?: "07:00:00";
            $jam_p_edt = !empty($_POST['jam_p_edit']) ? trim($_POST['jam_p_edit']) : null;
            $cab_edt   = (int)$_POST['cabang_id_edit'];
            $ket_edt   = trim($_POST['ket_edit']);

            if (in_array($st_kerja, ['Fulltime', 'Parttime', 'Lembur', 'Izin'])) {
                $stmt_upd = mysqli_prepare($conn, "UPDATE attendance SET status_kerja = ?, shift = ?, jam_masuk = ?, jam_pulang = ?, cabang_id = ?, keterangan = ? WHERE id = ?");
                mysqli_stmt_bind_param($stmt_upd, "ssssisi", $st_kerja, $shift_edt, $jam_m_edt, $jam_p_edt, $cab_edt, $ket_edt, $id_edit);
                mysqli_stmt_execute($stmt_upd);
                $alert_msg = "Data absensi berhasil diperbarui menjadi status: " . e($st_kerja) . ".";
                $alert_type = "success";
            }
        }

        // D. Hapus Data Absensi
        elseif (isset($_POST['aksi_hapus_absen'])) {
            $id_del = (int)$_POST['id_absen'];
            $stmt = mysqli_prepare($conn, "DELETE FROM attendance WHERE id = ?");
            mysqli_stmt_bind_param($stmt, "i", $id_del);
            mysqli_stmt_execute($stmt);
            $alert_msg = "Catatan absensi berhasil dihapus.";
            $alert_type = "success";
        }

        // E. Ubah Kata Sandi Admin (Bcrypt Hashing)
        elseif (isset($_POST['simpan_pass_admin'])) {
            $pass_lama = (string)$_POST['pass_lama'];
            $pass_baru = (string)$_POST['pass_baru'];
            $admin_id  = isset($_SESSION['admin_id']) ? (int)$_SESSION['admin_id'] : 1;

            $stmt_a = mysqli_prepare($conn, "SELECT password FROM admin WHERE id = ?");
            mysqli_stmt_bind_param($stmt_a, "i", $admin_id);
            mysqli_stmt_execute($stmt_a);
            $res_a = mysqli_stmt_get_result($stmt_a);

            if ($data_adm = mysqli_fetch_assoc($res_a)) {
                $db_pass = $data_adm['password'];
                if (password_verify($pass_lama, $db_pass) || $pass_lama === $db_pass) {
                    $new_hash = password_hash($pass_baru, PASSWORD_BCRYPT);
                    $stmt_upd = mysqli_prepare($conn, "UPDATE admin SET password = ? WHERE id = ?");
                    mysqli_stmt_bind_param($stmt_upd, "si", $new_hash, $admin_id);
                    mysqli_stmt_execute($stmt_upd);

                    $alert_msg = "Password admin berhasil diubah.";
                    $alert_type = "success";
                } else {
                    $alert_msg = "Password lama salah!";
                    $alert_type = "danger";
                }
            }
        }

        // E. Ubah Pengaturan Radius & Koordinat Cabang
        elseif (isset($_POST['update_cabang'])) {
            $c_id   = (int)$_POST['cabang_id'];
            $c_nama = trim($_POST['nama_cabang']);
            $c_lat  = (float)$_POST['latitude'];
            $c_lng  = (float)$_POST['longitude'];
            $c_rad  = (int)$_POST['radius_meter'];

            $stmt_c = mysqli_prepare($conn, "UPDATE cabang SET nama_cabang = ?, latitude = ?, longitude = ?, radius_meter = ? WHERE id = ?");
            mysqli_stmt_bind_param($stmt_c, "sddii", $c_nama, $c_lat, $c_lng, $c_rad, $c_id);
            mysqli_stmt_execute($stmt_c);

            // Refresh cabang list
            $cabang_all[$c_id]['nama_cabang'] = $c_nama;
            $cabang_all[$c_id]['latitude'] = $c_lat;
            $cabang_all[$c_id]['longitude'] = $c_lng;
            $cabang_all[$c_id]['radius_meter'] = $c_rad;

            $alert_msg = "Pengaturan cabang berhasil disimpan.";
            $alert_type = "success";
        }
    }
}

// =======================================================================
// FILTER TANGGAL, CABANG, & KARYAWAN
// =======================================================================
$tgl_awal       = isset($_GET['tgl_awal']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['tgl_awal']) ? $_GET['tgl_awal'] : date('Y-m-01');
$tgl_akhir      = isset($_GET['tgl_akhir']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['tgl_akhir']) ? $_GET['tgl_akhir'] : date('Y-m-d');
$filter_cabang  = isset($_GET['cabang']) && $_GET['cabang'] !== '' ? (int)$_GET['cabang'] : 0;
$selected_users = isset($_GET['users']) && is_array($_GET['users']) ? array_map('intval', $_GET['users']) : [];

// Bangun Klausa SQL Dinamis Aman
$where_clauses = ["attendance.tanggal BETWEEN '$tgl_awal' AND '$tgl_akhir'"];

if ($filter_cabang > 0) {
    $where_clauses[] = "attendance.cabang_id = $filter_cabang";
}

if (!empty($selected_users)) {
    $ids_clean = implode(',', $selected_users);
    $where_clauses[] = "attendance.user_id IN ($ids_clean)";
}

$sql_where = implode(' AND ', $where_clauses);

// =======================================================================
// STATISTIK RINGKASAN (1 QUERY OPTIMIZED)
// =======================================================================
$res_stat = mysqli_query($conn, "SELECT 
    COUNT(DISTINCT users.id) as tot_karyawan,
    SUM(CASE WHEN attendance.shift != 'Izin' THEN 1 ELSE 0 END) as tot_hadir,
    SUM(CASE WHEN attendance.shift != 'Izin' AND attendance.status_kerja = 'Fulltime' THEN 1 ELSE 0 END) as tot_ft,
    SUM(CASE WHEN attendance.shift != 'Izin' AND attendance.status_kerja = 'Parttime' THEN 1 ELSE 0 END) as tot_pt,
    SUM(CASE WHEN attendance.shift != 'Izin' AND attendance.status_kerja = 'Lembur' THEN 1 ELSE 0 END) as tot_lb,
    SUM(CASE WHEN attendance.terlambat_menit > 0 THEN 1 ELSE 0 END) as tot_telat,
    SUM(CASE WHEN attendance.shift = 'Izin' THEN 1 ELSE 0 END) as tot_izin
    FROM users 
    LEFT JOIN attendance ON users.id = attendance.user_id AND $sql_where");

$stat = mysqli_fetch_assoc($res_stat);
$total_karyawan = (int)mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM users"))['c'];
$total_hadir = (int)($stat['tot_hadir'] ?? 0);
$total_ft    = (int)($stat['tot_ft'] ?? 0);
$total_pt    = (int)($stat['tot_pt'] ?? 0);
$total_lb    = (int)($stat['tot_lb'] ?? 0);
$total_telat = (int)($stat['tot_telat'] ?? 0);
$total_izin  = (int)($stat['tot_izin'] ?? 0);

// Pre-Agregasi Jumlah Kehadiran Karyawan (Solusi Mutlak N+1 Problem)
$map_hadir_karyawan = [];
$q_map = mysqli_query($conn, "SELECT user_id, COUNT(*) as jml FROM attendance WHERE shift != 'Izin' AND $sql_where GROUP BY user_id");
while ($m = mysqli_fetch_assoc($q_map)) {
    $map_hadir_karyawan[$m['user_id']] = (int)$m['jml'];
}

// Query Utama Data Kehadiran
$query_main = "SELECT attendance.*, users.nama, cabang.nama_cabang 
               FROM attendance 
               JOIN users ON attendance.user_id = users.id 
               LEFT JOIN cabang ON attendance.cabang_id = cabang.id
               WHERE $sql_where 
               ORDER BY attendance.tanggal DESC, attendance.jam_masuk DESC";
$result_main = mysqli_query($conn, $query_main);

$data_all = [];
$data_ft  = [];
$data_pt  = [];
$data_lb  = [];

while ($row = mysqli_fetch_assoc($result_main)) {
    $data_all[] = $row;
    if ($row['status_kerja'] === 'Fulltime') $data_ft[] = $row;
    elseif ($row['status_kerja'] === 'Parttime') $data_pt[] = $row;
    elseif ($row['status_kerja'] === 'Lembur') $data_lb[] = $row;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dasbor Pengelola | E-Presensi</title>
    
    <!-- Typography & CSS -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Outfit:wght@500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <style>
        :root {
            /* Vibrant Brand & Culinary Theme */
            --brand-gold-start: #D97706;
            --brand-gold-mid: #F59E0B;
            --brand-gold-end: #FBBF24;
            --brand-deep: #78350F;
            --brand-amber-light: #FFFBEB;
            --brand-amber-border: #FCD34D;
            
            /* Backgrounds & Surfaces */
            --bg-body: #FFFDF9;
            --surface-card: #FFFFFF;
            --text-dark: #1F160E;
            --text-muted: #6B7280;
            --border-warm: rgba(245, 158, 11, 0.22);
        }

        body {
            background-color: #FFFDF8;
            font-family: 'Plus Jakarta Sans', sans-serif;
            color: var(--text-dark);
            min-height: 100dvh;
        }

        /* Top Brand Navigation Bar */
        .admin-nav {
            background: #FFFFFF;
            border-bottom: 2px solid #FDE68A;
            padding: 12px 24px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
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
            box-shadow: 0 2px 6px rgba(245, 158, 11, 0.12);
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
            box-shadow: 0 2px 8px rgba(217, 119, 6, 0.25);
        }

        .btn-nav-outline {
            border: 1.5px solid #FCD34D;
            color: #78350F;
            background: #FFFDF9;
            font-weight: 700;
            border-radius: 10px;
            transition: all 0.2s ease;
        }

        .btn-nav-outline:hover {
            background: #FEF3C7;
            color: #92400E;
            border-color: #F59E0B;
        }

        .btn-nav-danger {
            background: #E11D48;
            color: #FFFFFF;
            border: none;
            font-weight: 700;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(225, 29, 72, 0.25);
            transition: all 0.2s ease;
        }

        .btn-nav-danger:hover {
            background: #BE123C;
            color: #FFFFFF;
        }

        /* VIBRANT SOLID KPI CARDS (NO GRADIENTS) */
        .kpi-card {
            border-radius: 20px;
            padding: 22px 20px;
            color: #FFFFFF;
            position: relative;
            height: 100%;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .kpi-card:hover {
            transform: translateY(-2px);
        }

        .kpi-indigo {
            background: #4338CA;
            box-shadow: 0 6px 16px rgba(67, 56, 202, 0.25);
        }

        .kpi-emerald {
            background: #059669;
            box-shadow: 0 6px 16px rgba(5, 150, 105, 0.25);
        }

        .kpi-rose {
            background: #E11D48;
            box-shadow: 0 6px 16px rgba(225, 29, 72, 0.25);
        }

        .kpi-amber {
            background: #D97706;
            box-shadow: 0 6px 16px rgba(217, 119, 6, 0.25);
        }

        .kpi-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 12px;
        }

        .kpi-tag {
            font-size: 0.76rem;
            text-transform: uppercase;
            letter-spacing: 0.07em;
            font-weight: 800;
            color: rgba(255, 255, 255, 0.9);
        }

        .kpi-icon-pill {
            width: 36px;
            height: 36px;
            border-radius: 10px;
            background: rgba(255, 255, 255, 0.18);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1rem;
            color: #FFFFFF;
        }

        .kpi-num {
            font-family: 'Outfit', sans-serif;
            font-size: 2.6rem;
            font-weight: 800;
            letter-spacing: -0.03em;
            font-variant-numeric: tabular-nums;
            margin: 0;
            line-height: 1;
            color: #FFFFFF;
        }

        .kpi-badge {
            font-size: 0.74rem;
            font-weight: 700;
            padding: 4px 10px;
            border-radius: 20px;
            background: rgba(255, 255, 255, 0.2);
            color: #FFFFFF;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }

        /* Filter Box */
        .filter-panel {
            background: #FFFFFF;
            border: 1.5px solid #FDE68A;
            border-radius: 20px;
            padding: 20px 22px;
            margin-bottom: 24px;
            box-shadow: 0 4px 14px rgba(217, 119, 6, 0.05);
        }

        .filter-title {
            font-size: 0.76rem;
            text-transform: uppercase;
            letter-spacing: 0.07em;
            font-weight: 800;
            color: #92400E;
            margin-bottom: 6px;
            display: block;
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

        /* Vibrant Solid Action Buttons */
        .btn-filter-action {
            background: #D97706;
            color: #FFFFFF;
            border: none;
            font-weight: 800;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(217, 119, 6, 0.25);
            transition: all 0.2s ease;
        }

        .btn-filter-action:hover {
            background: #B45309;
            color: #FFFFFF;
        }

        .btn-manual-action {
            background: #4F46E5;
            color: #FFFFFF;
            border: none;
            font-weight: 700;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(79, 70, 229, 0.25);
            transition: all 0.2s ease;
        }

        .btn-manual-action:hover {
            background: #4338CA;
            color: #FFFFFF;
        }

        .btn-excel-action {
            background: #059669;
            color: #FFFFFF;
            border: none;
            font-weight: 700;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(5, 150, 105, 0.25);
            transition: all 0.2s ease;
        }

        .btn-excel-action:hover {
            background: #047857;
            color: #FFFFFF;
        }

        .btn-rate-action {
            background: #FFFBEB;
            color: #92400E;
            border: 1.5px solid #FCD34D;
            font-weight: 700;
            border-radius: 10px;
            transition: all 0.2s ease;
        }

        .btn-rate-action:hover {
            background: #FEF3C7;
            color: #78350F;
        }

        /* Clean Table Architecture with Warm Gold Highlights */
        .table-panel {
            background: #FFFFFF;
            border: 1.5px solid #FDE68A;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.03);
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

        /* Vibrant High-Contrast Badges */
        .badge-soft {
            padding: 5px 10px;
            border-radius: 8px;
            font-size: 0.74rem;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }

        .badge-green { background: #ECFDF5; color: #065F46; border: 1.5px solid #A7F3D0; }
        .badge-red { background: #FFF1F2; color: #9F1239; border: 1.5px solid #FDA4AF; }
        .badge-yellow { background: #FFFBEB; color: #92400E; border: 1.5px solid #FDE68A; }
        .badge-blue { background: #EFF6FF; color: #1E40AF; border: 1.5px solid #BFDBFE; }
        .badge-purple { background: #FAF5FF; color: #6B21A8; border: 1.5px solid #E9D5FF; }
        .badge-stone { background: #F8FAFC; color: #334155; border: 1.5px solid #CBD5E1; }

        /* Custom Tabs */
        .nav-pills .nav-link {
            border-radius: 10px;
            font-size: 0.86rem;
            font-weight: 700;
            padding: 8px 18px !important;
            color: #78350F !important;
            background: #FFFBEB;
            border: 1px solid #FDE68A;
            margin-right: 6px;
            transition: all 0.2s ease;
        }

        .nav-pills .nav-link:hover {
            background: #FEF3C7;
        }

        .nav-pills .nav-link.active {
            background: #D97706 !important;
            color: #FFFFFF !important;
            border-color: #D97706;
            box-shadow: 0 2px 8px rgba(217, 119, 6, 0.25);
        }
    </style>
</head>
<body>

    <div class="admin-layout">
        <?php require __DIR__ . '/sidebar.php'; ?>
        
        <div class="admin-main">
            <?php renderMobileTopbar('Rekap Presensi'); ?>
            
            <div class="admin-content">

        <!-- Feedback Alert -->
        <?php if (!empty($alert_msg)): ?>
            <div class="alert alert-<?= $alert_type ?> alert-dismissible fade show d-flex align-items-center mb-4" role="alert" style="border-radius: 14px; border: 1.5px solid currentColor;">
                <i class="fas <?= $alert_type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle' ?> me-2 fs-5"></i>
                <div class="fw-semibold"><?= e($alert_msg) ?></div>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <!-- VIBRANT RADIANT KPI CARDS -->
        <div class="row g-3 mb-4">
            <div class="col-md-3">
                <div class="kpi-card kpi-indigo">
                    <div class="kpi-header">
                        <span class="kpi-tag">Total Pegawai</span>
                        <div class="kpi-icon-pill">
                            <i class="fas fa-users"></i>
                        </div>
                    </div>
                    <div class="d-flex align-items-baseline justify-content-between">
                        <h2 class="kpi-num"><?= $total_karyawan ?></h2>
                        <span class="kpi-badge"><i class="fas fa-circle-check"></i> Aktif</span>
                    </div>
                </div>
            </div>

            <div class="col-md-3">
                <div class="kpi-card kpi-emerald">
                    <div class="kpi-header">
                        <span class="kpi-tag">Kehadiran Periode</span>
                        <div class="kpi-icon-pill">
                            <i class="fas fa-user-check"></i>
                        </div>
                    </div>
                    <div class="d-flex align-items-baseline justify-content-between">
                        <h2 class="kpi-num"><?= $total_hadir ?></h2>
                        <span class="kpi-badge">FT: <?= $total_ft ?> &bull; PT: <?= $total_pt ?></span>
                    </div>
                </div>
            </div>

            <div class="col-md-3">
                <div class="kpi-card kpi-rose">
                    <div class="kpi-header">
                        <span class="kpi-tag">Keterlambatan</span>
                        <div class="kpi-icon-pill">
                            <i class="fas fa-user-clock"></i>
                        </div>
                    </div>
                    <div class="d-flex align-items-baseline justify-content-between">
                        <h2 class="kpi-num"><?= $total_telat ?></h2>
                        <span class="kpi-badge"><i class="fas fa-triangle-exclamation"></i> Kali Tercatat</span>
                    </div>
                </div>
            </div>

            <div class="col-md-3">
                <div class="kpi-card kpi-amber">
                    <div class="kpi-header">
                        <span class="kpi-tag">Izin / Sakit</span>
                        <div class="kpi-icon-pill">
                            <i class="fas fa-file-medical"></i>
                        </div>
                    </div>
                    <div class="d-flex align-items-baseline justify-content-between">
                        <h2 class="kpi-num"><?= $total_izin ?></h2>
                        <span class="kpi-badge"><i class="fas fa-envelope-open-text"></i> Pengajuan</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- FILTER & ACTION TOOLBAR -->
        <div class="filter-panel">
            <form method="GET" action="index.php" class="row g-2 align-items-end">
                <div class="col-md-2">
                    <label class="form-label small fw-bold text-muted mb-1">Dari Tanggal</label>
                    <input type="date" name="tgl_awal" class="form-control form-control-sm" value="<?= e($tgl_awal) ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label small fw-bold text-muted mb-1">Sampai Tanggal</label>
                    <input type="date" name="tgl_akhir" class="form-control form-control-sm" value="<?= e($tgl_akhir) ?>">
                </div>

                <!-- Filter Cabang -->
                <div class="col-md-3">
                    <label class="form-label small fw-bold text-muted mb-1">Pilih Cabang Outlet</label>
                    <select name="cabang" class="form-select form-select-sm">
                        <option value="0">-- Semua Cabang Outlet --</option>
                        <?php foreach ($cabang_all as $cab): ?>
                            <option value="<?= $cab['id'] ?>" <?= $filter_cabang === (int)$cab['id'] ? 'selected' : '' ?>>
                                <?= e($cab['nama_cabang']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Tombol Aksi Toolbar -->
                <div class="col-md-5 d-flex gap-2">
                    <button type="submit" class="btn btn-sm btn-filter-action flex-fill py-2">
                        <i class="fas fa-filter me-1"></i> Saring Data
                    </button>
                    <a href="index.php" class="btn btn-sm btn-rate-action d-flex align-items-center justify-content-center px-3" title="Reset Filter">
                        <i class="fas fa-rotate-left"></i>
                    </a>
                    <button type="button" class="btn btn-sm btn-manual-action px-3" data-bs-toggle="modal" data-bs-target="#modalManual">
                        <i class="fas fa-user-pen me-1"></i> Manual
                    </button>
                    <?php
                    $export_params = $_GET;
                    $export_url = "export.php?" . http_build_query($export_params);
                    ?>
                    <a href="<?= e($export_url) ?>" class="btn btn-sm btn-excel-action px-3" target="_blank">
                        <i class="fas fa-file-excel me-1"></i> Excel
                    </a>
                    <button type="button" class="btn btn-sm btn-rate-action px-3" data-bs-toggle="modal" data-bs-target="#modalGaji">
                        <i class="fas fa-coins me-1 text-warning"></i> Rp <?= $gaji_per_menit ?>/m
                    </button>
                </div>
            </form>
        </div>

        <!-- MAIN TABLE PANEL -->
        <div class="table-panel">
            <div class="p-3 border-bottom d-flex align-items-center justify-content-between flex-wrap gap-2">
                <ul class="nav nav-pills" id="pills-tab" role="tablist">
                    <li class="nav-item">
                        <button class="nav-link active" data-bs-toggle="pill" data-bs-target="#tab-semua" type="button">Semua (<?= count($data_all) ?>)</button>
                    </li>
                    <li class="nav-item">
                        <button class="nav-link" data-bs-toggle="pill" data-bs-target="#tab-ft" type="button">Fulltime (<?= count($data_ft) ?>)</button>
                    </li>
                    <li class="nav-item">
                        <button class="nav-link" data-bs-toggle="pill" data-bs-target="#tab-pt" type="button">Parttime (<?= count($data_pt) ?>)</button>
                    </li>
                    <li class="nav-item">
                        <button class="nav-link" data-bs-toggle="pill" data-bs-target="#tab-lb" type="button">Lembur (<?= count($data_lb) ?>)</button>
                    </li>
                </ul>

                <span class="text-muted small">
                    Periode: <strong><?= date('d M Y', strtotime($tgl_awal)) ?></strong> s/d <strong><?= date('d M Y', strtotime($tgl_akhir)) ?></strong>
                </span>
            </div>

            <?php
            if (!function_exists('renderTableContent')) {
                function renderTableContent($rows, $gaji_per_menit, $map_hadir) {
                if (empty($rows)) {
                    echo '<div class="p-5 text-center text-muted small">Tidak ada data absensi untuk filter yang dipilih.</div>';
                    return;
                }
                echo '<div class="table-responsive"><table class="table mb-0 datatable-absen w-100">';
                echo '<thead><tr>';
                echo '<th>Tgl</th><th>Karyawan</th><th>Cabang</th><th>Shift</th><th>Masuk</th><th>Pulang</th><th>Durasi</th><th class="text-end">Estimasi Gaji</th><th class="text-center">Aksi</th>';
                echo '</tr></thead><tbody>';

                foreach ($rows as $r) {
                    $is_izin = ($r['shift'] === 'Izin');
                    $tot_menit = 0;
                    $gaji = 0;

                    if ($r['jam_pulang'] && !$is_izin) {
                        $diff = strtotime($r['jam_pulang']) - strtotime($r['jam_masuk']);
                        if ($diff > 0) {
                            $tot_menit = (int)floor($diff / 60);
                            $gaji = $tot_menit * $gaji_per_menit;
                        }
                    }

                    $jml_hadir = $map_hadir[$r['user_id']] ?? 1;

                    echo '<tr>';
                    // Tanggal
                    echo '<td class="fw-semibold font-monospace" style="font-size:0.84rem;">' . date('d/m/Y', strtotime($r['tanggal'])) . '</td>';
                    
                    // Karyawan & Status
                    echo '<td>';
                    echo '<div class="fw-bold text-dark">' . e($r['nama']) . '</div>';
                    echo '<div class="d-flex gap-1 mt-1">';
                    if ($r['status_kerja'] === 'Fulltime') echo '<span class="badge-soft badge-green">Fulltime</span>';
                    elseif ($r['status_kerja'] === 'Parttime') echo '<span class="badge-soft badge-yellow">Parttime</span>';
                    elseif ($r['status_kerja'] === 'Lembur') echo '<span class="badge-soft badge-red">Lembur</span>';
                    else echo '<span class="badge-soft badge-stone">' . e($r['status_kerja']) . '</span>';
                    echo '<span class="badge-soft badge-stone">' . $jml_hadir . ' Hadir</span>';
                    echo '</div>';
                    echo '</td>';

                    // Cabang
                    $nama_cab = $r['nama_cabang'] ? e($r['nama_cabang']) : 'Outlet Erlangga (Pusat)';
                    echo '<td><span class="badge-soft badge-blue"><i class="fas fa-store me-1"></i>' . $nama_cab . '</span></td>';

                    // Shift / Alasan
                    echo '<td>';
                    if ($is_izin) {
                        echo '<span class="badge-soft badge-yellow mb-1"><i class="fas fa-file-medical me-1"></i>Izin / Sakit</span>';
                        echo '<div class="small text-muted" style="max-width:200px;">' . e($r['keterangan']) . '</div>';
                    } else {
                        echo '<div class="small fw-semibold">' . e($r['shift']) . '</div>';
                        if ($r['terlambat_menit'] > 0) {
                            echo '<span class="badge-soft badge-red mt-1"><i class="fas fa-clock me-1"></i>Telat ' . $r['terlambat_menit'] . 'm</span>';
                        }
                    }
                    echo '</td>';

                    // Jam Masuk
                    echo '<td class="font-monospace">' . ($is_izin ? '-' : substr($r['jam_masuk'], 0, 5)) . '</td>';

                    // Jam Pulang
                    echo '<td class="font-monospace">';
                    if ($is_izin) echo '-';
                    elseif ($r['jam_pulang']) echo substr($r['jam_pulang'], 0, 5);
                    else echo '<span class="badge-soft badge-stone">Belum</span>';
                    echo '</td>';

                    // Durasi
                    echo '<td>';
                    if ($tot_menit > 0) {
                        $jam = floor($tot_menit / 60);
                        $sisa = $tot_menit % 60;
                        echo '<span class="fw-semibold">' . $jam . 'j ' . $sisa . 'm</span> <small class="text-muted">(' . $tot_menit . 'm)</small>';
                    } else {
                        echo '<span class="text-muted">-</span>';
                    }
                    echo '</td>';

                    // Estimasi Gaji
                    echo '<td class="text-end fw-bold text-success font-monospace">';
                    if ($gaji > 0) echo 'Rp ' . number_format($gaji, 0, ',', '.');
                    else echo '<span class="text-muted">-</span>';
                    echo '</td>';

                    // Aksi & Foto
                    echo '<td class="text-center">';
                    echo '<div class="d-inline-flex gap-1">';
                    if (!empty($r['foto_masuk'])) {
                        echo '<button type="button" class="btn btn-sm btn-outline-secondary" onclick="previewFoto(\'../uploads/' . e($r['foto_masuk']) . '\', \'Foto Masuk - ' . e($r['nama']) . '\')" title="Lihat Foto Masuk"><i class="fas fa-image"></i></button>';
                    }
                    if (!empty($r['foto_pulang'])) {
                        echo '<button type="button" class="btn btn-sm btn-outline-secondary" onclick="previewFoto(\'../uploads/' . e($r['foto_pulang']) . '\', \'Foto Pulang - ' . e($r['nama']) . '\')" title="Lihat Foto Pulang"><i class="fas fa-camera"></i></button>';
                    }
                    
                    // Tombol Koreksi / Edit Status & Shift (Misal: Ubah Fulltime ke Lembur)
                    $edit_payload = [
                        'id' => (int)$r['id'],
                        'nama' => (string)$r['nama'],
                        'tanggal' => (string)$r['tanggal'],
                        'cabang_id' => (int)$r['cabang_id'],
                        'status_kerja' => (string)$r['status_kerja'],
                        'shift' => (string)$r['shift'],
                        'jam_masuk' => substr((string)$r['jam_masuk'], 0, 5),
                        'jam_pulang' => !empty($r['jam_pulang']) ? substr((string)$r['jam_pulang'], 0, 5) : '',
                        'keterangan' => (string)($r['keterangan'] ?? '')
                    ];
                    echo '<button type="button" class="btn btn-sm btn-outline-primary" title="Ubah Tipe Kerja / Shift" onclick=\'bukaModalEdit(' . json_encode($edit_payload, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) . ')\'><i class="fas fa-pen-to-square"></i></button>';

                    echo '<form method="POST" onsubmit="return confirm(\'Hapus catatan absensi ini secara permanen?\')" style="display:inline;">';
                    echo csrf_field();
                    echo '<input type="hidden" name="aksi_hapus_absen" value="1">';
                    echo '<input type="hidden" name="id_absen" value="' . (int)$r['id'] . '">';
                    echo '<button type="submit" class="btn btn-sm btn-outline-danger" title="Hapus"><i class="fas fa-trash"></i></button>';
                    echo '</form>';
                    echo '</div>';
                    echo '</td>';

                    echo '</tr>';
                }
                echo '</tbody></table></div>';
            }
        }
            ?>

            <div class="tab-content" id="pills-tabContent">
                <div class="tab-pane fade show active" id="tab-semua">
                    <?php renderTableContent($data_all, $gaji_per_menit, $map_hadir_karyawan); ?>
                </div>
                <div class="tab-pane fade" id="tab-ft">
                    <?php renderTableContent($data_ft, $gaji_per_menit, $map_hadir_karyawan); ?>
                </div>
                <div class="tab-pane fade" id="tab-pt">
                    <?php renderTableContent($data_pt, $gaji_per_menit, $map_hadir_karyawan); ?>
                </div>
                <div class="tab-pane fade" id="tab-lb">
                    <?php renderTableContent($data_lb, $gaji_per_menit, $map_hadir_karyawan); ?>
                </div>
            </div>
        </div>

            </div> <!-- /.admin-content -->
        </div> <!-- /.admin-main -->
    </div> <!-- /.admin-layout -->

    <!-- MODAL ATUR TARIF GAJI -->
    <div class="modal fade" id="modalGaji" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered modal-sm">
            <div class="modal-content" style="border-radius: 16px; border: 1px solid var(--color-border);">
                <div class="modal-header border-bottom py-3">
                    <h6 class="modal-title fw-bold text-dark"><i class="fas fa-coins me-2 text-warning"></i>Tarif Gaji per Menit</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <?= csrf_field() ?>
                    <input type="hidden" name="simpan_gaji" value="1">
                    <div class="modal-body p-4 text-center">
                        <label class="small text-muted fw-semibold mb-2">Nominal Rupiah / Menit</label>
                        <input type="number" name="gaji_baru" class="form-control text-center fs-3 fw-bold text-success font-monospace" value="<?= $gaji_per_menit ?>" required>
                        <small class="text-muted d-block mt-2">Contoh: Rp 15.000 / Jam = <strong>250</strong> (15000 / 60)</small>
                    </div>
                    <div class="modal-footer border-top p-3">
                        <button type="submit" class="btn btn-filter-action w-100 fw-bold py-2">Simpan Perubahan</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- MODAL INPUT PRESENSI MANUAL OLEH OWNER -->
    <div class="modal fade" id="modalManual" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content" style="border-radius: 20px; border: 1.5px solid #FDE68A;">
                <div class="modal-header border-bottom py-3" style="background: #FEF3C7;">
                    <h6 class="modal-title fw-bold text-dark"><i class="fas fa-user-pen me-2 text-warning"></i>Input Presensi Manual</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <?= csrf_field() ?>
                    <input type="hidden" name="simpan_manual" value="1">
                    <div class="modal-body p-4">
                        <div class="mb-3">
                            <label class="small fw-bold text-muted mb-1">Pilih Karyawan</label>
                            <select name="user_id_man" id="user_id_man" class="form-select" required onchange="syncEmployeeStatus(this)">
                                <option value="">-- Pilih Karyawan --</option>
                                <?php
                                $q_all_u = mysqli_query($conn, "SELECT id, nama, status_kerja, cabang_id FROM users ORDER BY nama ASC");
                                while ($u = mysqli_fetch_assoc($q_all_u)) {
                                    echo '<option value="' . $u['id'] . '" data-status="' . e($u['status_kerja']) . '" data-cabang="' . (int)$u['cabang_id'] . '">' . e($u['nama']) . ' (' . e($u['status_kerja']) . ')</option>';
                                }
                                ?>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="small fw-bold text-muted mb-1">Cabang Penugasan</label>
                            <select name="cabang_id_man" id="cabang_id_man" class="form-select" required>
                                <?php foreach ($cabang_all as $cab): ?>
                                    <option value="<?= $cab['id'] ?>"><?= e($cab['nama_cabang']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="row g-2 mb-3">
                            <div class="col-6">
                                <label class="small fw-bold text-muted mb-1">Tanggal</label>
                                <input type="date" name="tgl_manual" class="form-control" value="<?= date('Y-m-d') ?>" required>
                            </div>
                            <div class="col-6">
                                <label class="small fw-bold text-muted mb-1">Status Kehadiran</label>
                                <select name="jenis_manual" id="jenis_manual" class="form-select" onchange="toggleManualInputs()">
                                    <option value="Hadir">Hadir Bekerja</option>
                                    <option value="Izin">Izin / Sakit</option>
                                </select>
                            </div>
                        </div>

                        <!-- TIPE KERJA: LEMBUR / FULLTIME / PARTTIME -->
                        <div id="box-tipe-kerja-manual" class="mb-3">
                            <label class="small fw-bold text-muted mb-1 d-flex justify-content-between align-items-center">
                                <span>Tipe Penugasan / Status Kerja</span>
                                <span class="badge bg-danger-subtle text-danger border border-danger-subtle" style="font-size: 0.7rem;">Penting</span>
                            </label>
                            <div class="d-flex gap-2">
                                <div class="flex-fill">
                                    <input type="radio" class="btn-check" name="status_kerja_man" id="st_lembur" value="Lembur" autocomplete="off" onchange="onStatusKerjaChange('Lembur')">
                                    <label class="btn btn-outline-danger w-100 fw-bold py-2 small d-flex flex-column align-items-center" for="st_lembur">
                                        <i class="fas fa-business-time mb-1"></i> Lembur
                                    </label>
                                </div>
                                <div class="flex-fill">
                                    <input type="radio" class="btn-check" name="status_kerja_man" id="st_fulltime" value="Fulltime" autocomplete="off" checked onchange="onStatusKerjaChange('Fulltime')">
                                    <label class="btn btn-outline-success w-100 fw-bold py-2 small d-flex flex-column align-items-center" for="st_fulltime">
                                        <i class="fas fa-briefcase mb-1"></i> Fulltime
                                    </label>
                                </div>
                                <div class="flex-fill">
                                    <input type="radio" class="btn-check" name="status_kerja_man" id="st_parttime" value="Parttime" autocomplete="off" onchange="onStatusKerjaChange('Parttime')">
                                    <label class="btn btn-outline-warning w-100 fw-bold py-2 small d-flex flex-column align-items-center" for="st_parttime">
                                        <i class="fas fa-clock mb-1"></i> Parttime
                                    </label>
                                </div>
                            </div>
                            <div class="form-text mt-1 text-muted" style="font-size: 0.75rem;">
                                Pilih <strong>Lembur</strong> jika pegawai masuk shift tambahan/lembur agar rekapitulasi gaji tepat.
                            </div>
                        </div>

                        <!-- PILIHAN SHIFT -->
                        <div id="box-shift-manual" class="mb-3">
                            <label class="small fw-bold text-muted mb-1">Pilihan Shift</label>
                            <select name="shift_manual" id="shift_manual" class="form-select" onchange="onShiftChange(this)">
                                <?php
                                $q_sh_manual = mysqli_query($conn, "SELECT * FROM shift_settings ORDER BY id ASC");
                                $sh_count = 0;
                                while ($sm = mysqli_fetch_assoc($q_sh_manual)):
                                    $sh_count++;
                                    $sm_masuk = substr($sm['jam_masuk'], 0, 5);
                                    $sm_pulang = substr($sm['jam_pulang'], 0, 5);
                                    $sm_kd = strtoupper($sm['kode_shift']);
                                    $sm_tipe = str_starts_with($sm_kd, 'LB') ? 'Lembur' : (str_starts_with($sm_kd, 'PT') ? 'Parttime' : 'Fulltime');
                                ?>
                                    <option value="<?= e($sm['nama_shift']) ?>" data-tipe="<?= $sm_tipe ?>" data-masuk="<?= $sm_masuk ?>" data-pulang="<?= $sm_pulang ?>" <?= $sh_count === 1 ? 'selected' : '' ?>>
                                        <?= e($sm['nama_shift']) ?> (<?= $sm_masuk ?> - <?= $sm_pulang ?>)
                                    </option>
                                <?php endwhile; ?>
                                <?php if ($sh_count === 0): ?>
                                    <option value="Shift Operasional" data-tipe="all" data-masuk="14:00" data-pulang="21:30" selected>Shift Operasional (14:00 - 21:30)</option>
                                <?php endif; ?>
                                <option value="Input Manual" data-tipe="all" data-masuk="14:00" data-pulang="21:30">Jam Bebas / Kustom (Manual)</option>
                            </select>
                        </div>

                        <div id="box-jam-manual" class="row g-2 mb-3">
                            <div class="col-6">
                                <label class="small fw-bold text-muted mb-1">Jam Masuk</label>
                                <input type="time" name="jam_m_manual" id="jam_m_manual" class="form-control" value="14:00">
                            </div>
                            <div class="col-6">
                                <label class="small fw-bold text-muted mb-1">Jam Pulang (Opsional)</label>
                                <input type="time" name="jam_p_manual" id="jam_p_manual" class="form-control" value="21:30">
                            </div>
                        </div>

                        <div id="box-izin-manual" class="mb-3" style="display: none;">
                            <label class="small fw-bold text-muted mb-1">Alasan Izin / Keterangan</label>
                            <textarea name="ket_man" class="form-control" rows="2" placeholder="Tuliskan keterangan izin..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer border-top p-3">
                        <button type="submit" class="btn btn-filter-action w-100 fw-bold py-2">Simpan Presensi</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- MODAL EDIT / KOREKSI DATA PRESENSI -->
    <div class="modal fade" id="modalEditAbsen" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content" style="border-radius: 20px; border: 1.5px solid #FCD34D;">
                <div class="modal-header border-bottom py-3" style="background: #FEF3C7;">
                    <h6 class="modal-title fw-bold text-dark"><i class="fas fa-pen-to-square me-2 text-warning"></i>Koreksi Data Presensi</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <?= csrf_field() ?>
                    <input type="hidden" name="simpan_edit_absen" value="1">
                    <input type="hidden" name="id_absen_edit" id="edt_id_absen">
                    <div class="modal-body p-4">
                        <div class="mb-3">
                            <label class="small fw-bold text-muted mb-1">Karyawan & Tanggal</label>
                            <input type="text" id="edt_karyawan_info" class="form-control bg-light fw-bold text-dark" readonly>
                        </div>

                        <div class="mb-3">
                            <label class="small fw-bold text-muted mb-1">Cabang Penugasan</label>
                            <select name="cabang_id_edit" id="edt_cabang_id" class="form-select" required>
                                <?php foreach ($cabang_all as $cab): ?>
                                    <option value="<?= $cab['id'] ?>"><?= e($cab['nama_cabang']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="small fw-bold text-muted mb-1">Tipe / Status Kerja</label>
                            <select name="status_kerja_edit" id="edt_status_kerja" class="form-select" required>
                                <option value="Lembur">Lembur</option>
                                <option value="Fulltime">Fulltime</option>
                                <option value="Parttime">Parttime</option>
                                <option value="Izin">Izin / Sakit</option>
                            </select>
                            <div class="form-text" style="font-size: 0.75rem;">Ubah ke <strong>Lembur</strong> jika pegawai sebelumnya salah tercatat Fulltime.</div>
                        </div>

                        <div class="mb-3">
                            <label class="small fw-bold text-muted mb-1">Keterangan Shift</label>
                            <input type="text" name="shift_edit" id="edt_shift" class="form-control" placeholder="Contoh: Shift Lembur Penuh, Shift Pagi (Fulltime)" required>
                        </div>

                        <div class="row g-2 mb-3">
                            <div class="col-6">
                                <label class="small fw-bold text-muted mb-1">Jam Masuk</label>
                                <input type="time" name="jam_m_edit" id="edt_jam_m" class="form-control" required>
                            </div>
                            <div class="col-6">
                                <label class="small fw-bold text-muted mb-1">Jam Pulang</label>
                                <input type="time" name="jam_p_edit" id="edt_jam_p" class="form-control">
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="small fw-bold text-muted mb-1">Catatan / Keterangan</label>
                            <input type="text" name="ket_edit" id="edt_ket" class="form-control" placeholder="Catatan tambahan (opsional)">
                        </div>
                    </div>
                    <div class="modal-footer border-top p-3 d-flex gap-2">
                        <button type="button" class="btn btn-light flex-fill" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-filter-action flex-fill fw-bold py-2">Simpan Perubahan</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- MODAL KELOLA & LIHAT PETA CABANG -->
    <div class="modal fade" id="modalCabang" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content" style="border-radius: 20px; border: 1.5px solid #FDE68A;">
                <div class="modal-header border-bottom py-3" style="background: #FEF3C7;">
                    <h6 class="modal-title fw-bold text-dark"><i class="fas fa-store me-2 text-warning"></i>Cabang Resmi Outlet</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="row g-4">
                        <?php foreach ($cabang_all as $cab): ?>
                            <div class="col-md-6">
                                <div class="p-3 rounded-4 h-100" style="background:#FFFDF9; border: 1.5px solid #FDE68A; box-shadow: 0 4px 14px rgba(217, 119, 6, 0.06);">
                                    <h6 class="fw-bold mb-1" style="color: #78350F;"><?= e($cab['nama_cabang']) ?></h6>
                                    <p class="small text-muted mb-2"><i class="fas fa-location-dot me-1 text-warning"></i><?= e($cab['alamat']) ?></p>
                                    
                                    <!-- Google Maps Embed -->
                                    <div class="mb-3" style="border-radius: 12px; overflow: hidden; height: 180px; border: 1px solid #FDE68A;">
                                        <?= $cab['embed_maps'] ?: '<div class="p-4 text-center bg-white text-muted">Maps tidak tersedia</div>' ?>
                                    </div>

                                    <form method="POST">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="update_cabang" value="1">
                                        <input type="hidden" name="cabang_id" value="<?= $cab['id'] ?>">
                                        <input type="hidden" name="nama_cabang" value="<?= e($cab['nama_cabang']) ?>">
                                        
                                        <div class="row g-2 mb-2">
                                            <div class="col-6">
                                                <label class="small fw-semibold text-muted">Latitude</label>
                                                <input type="text" name="latitude" class="form-control form-control-sm font-monospace" value="<?= $cab['latitude'] ?>" required>
                                            </div>
                                            <div class="col-6">
                                                <label class="small fw-semibold text-muted">Longitude</label>
                                                <input type="text" name="longitude" class="form-control form-control-sm font-monospace" value="<?= $cab['longitude'] ?>" required>
                                            </div>
                                        </div>

                                        <div class="mb-3">
                                            <label class="small fw-semibold text-muted">Radius Toleransi Absen (Meter)</label>
                                            <div class="input-group input-group-sm">
                                                <input type="number" name="radius_meter" class="form-control font-monospace" value="<?= $cab['radius_meter'] ?>" required min="20" max="1000">
                                                <span class="input-group-text bg-light fw-bold text-dark">Meter</span>
                                            </div>
                                        </div>

                                        <button type="submit" class="btn btn-sm btn-rate-action w-100 fw-bold py-2">
                                            <i class="fas fa-floppy-disk me-1"></i> Simpan Koordinat
                                        </button>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- MODAL GANTI PASSWORD ADMIN -->
    <div class="modal fade" id="modalPassAdmin" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered modal-sm">
            <div class="modal-content" style="border-radius: 20px; border: 1.5px solid #FDE68A;">
                <div class="modal-header border-bottom py-3" style="background: #FEF3C7;">
                    <h6 class="modal-title fw-bold text-dark"><i class="fas fa-key me-2 text-warning"></i>Ubah Kata Sandi</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <?= csrf_field() ?>
                    <input type="hidden" name="simpan_pass_admin" value="1">
                    <div class="modal-body p-4">
                        <div class="mb-3">
                            <label class="small fw-bold text-muted mb-1">Kata Sandi Lama</label>
                            <input type="password" name="pass_lama" class="form-control" required>
                        </div>
                        <div class="mb-2">
                            <label class="small fw-bold text-muted mb-1">Kata Sandi Baru</label>
                            <input type="password" name="pass_baru" class="form-control" required minlength="6">
                        </div>
                    </div>
                    <div class="modal-footer border-top p-3">
                        <button type="submit" class="btn btn-filter-action w-100 fw-bold py-2">Perbarui Kata Sandi</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>

    <script>
        $(document).ready(function() {
            $('.datatable-absen').DataTable({
                "order": [[0, "desc"]],
                "pageLength": 25,
                "language": {
                    "search": "Cari data:",
                    "lengthMenu": "Tampilkan _MENU_ data",
                    "info": "Menampilkan _START_ s/d _END_ dari _TOTAL_ presensi",
                    "paginate": { "previous": "Sebelumnya", "next": "Berikutnya" }
                }
            });
        });

        function toggleManualInputs() {
            const jenis = document.getElementById('jenis_manual').value;
            const boxJam = document.getElementById('box-jam-manual');
            const boxTipe = document.getElementById('box-tipe-kerja-manual');
            const boxShift = document.getElementById('box-shift-manual');
            const boxIzin = document.getElementById('box-izin-manual');
            if (jenis === 'Hadir') {
                if (boxJam) boxJam.style.display = 'flex';
                if (boxTipe) boxTipe.style.display = 'block';
                if (boxShift) boxShift.style.display = 'block';
                if (boxIzin) boxIzin.style.display = 'none';
            } else {
                if (boxJam) boxJam.style.display = 'none';
                if (boxTipe) boxTipe.style.display = 'none';
                if (boxShift) boxShift.style.display = 'none';
                if (boxIzin) boxIzin.style.display = 'block';
            }
        }

        function syncEmployeeStatus(select) {
            const opt = select.options[select.selectedIndex];
            if (!opt || !opt.dataset) return;
            const status = opt.dataset.status;
            const cabang = opt.dataset.cabang;
            
            if (cabang && document.getElementById('cabang_id_man')) {
                document.getElementById('cabang_id_man').value = cabang;
            }
            
            if (status === 'Parttime') {
                const r = document.getElementById('st_parttime');
                if (r) { r.checked = true; onStatusKerjaChange('Parttime'); }
            } else if (status === 'Lembur') {
                const r = document.getElementById('st_lembur');
                if (r) { r.checked = true; onStatusKerjaChange('Lembur'); }
            } else {
                const r = document.getElementById('st_fulltime');
                if (r) { r.checked = true; onStatusKerjaChange('Fulltime'); }
            }
        }

        function onStatusKerjaChange(status) {
            const shiftSelect = document.getElementById('shift_manual');
            const jamM = document.getElementById('jam_m_manual');
            const jamP = document.getElementById('jam_p_manual');
            if (!shiftSelect) return;

            let found = false;
            for (let i = 0; i < shiftSelect.options.length; i++) {
                const opt = shiftSelect.options[i];
                if (opt.dataset.tipe === status) {
                    shiftSelect.selectedIndex = i;
                    if (jamM && opt.dataset.masuk) jamM.value = opt.dataset.masuk;
                    if (jamP && opt.dataset.pulang) jamP.value = opt.dataset.pulang;
                    found = true;
                    break;
                }
            }

            if (!found && shiftSelect.options.length > 0) {
                const opt = shiftSelect.options[0];
                if (jamM && opt.dataset.masuk) jamM.value = opt.dataset.masuk;
                if (jamP && opt.dataset.pulang) jamP.value = opt.dataset.pulang;
            }
        }

        function onShiftChange(select) {
            const opt = select.options[select.selectedIndex];
            if (!opt) return;
            const masuk = opt.dataset.masuk;
            const pulang = opt.dataset.pulang;
            const tipe = opt.dataset.tipe;
            if (masuk && document.getElementById('jam_m_manual')) document.getElementById('jam_m_manual').value = masuk;
            if (pulang && document.getElementById('jam_p_manual')) document.getElementById('jam_p_manual').value = pulang;
            if (tipe && (tipe === 'Fulltime' || tipe === 'Parttime' || tipe === 'Lembur')) {
                const r = document.getElementById('st_' + tipe.toLowerCase());
                if (r) r.checked = true;
            }
        }

        function bukaModalEdit(data) {
            document.getElementById('edt_id_absen').value = data.id;
            document.getElementById('edt_karyawan_info').value = data.nama + ' (' + data.tanggal + ')';
            document.getElementById('edt_cabang_id').value = data.cabang_id;
            document.getElementById('edt_status_kerja').value = data.status_kerja;
            document.getElementById('edt_shift').value = data.shift;
            document.getElementById('edt_jam_m').value = data.jam_masuk;
            document.getElementById('edt_jam_p').value = data.jam_pulang || '';
            document.getElementById('edt_ket').value = data.keterangan || '';
            
            const modalEl = document.getElementById('modalEditAbsen');
            const bsModal = new bootstrap.Modal(modalEl);
            bsModal.show();
        }

        function previewFoto(url, title) {
            Swal.fire({
                title: title,
                imageUrl: url,
                imageAlt: 'Bukti Presensi',
                showCloseButton: true,
                confirmButtonColor: '#D97706',
                confirmButtonText: 'Tutup'
            });
        }
    </script>
</body>
</html>