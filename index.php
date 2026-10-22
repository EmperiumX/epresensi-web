<?php
require_once __DIR__ . '/config/session.php';
require_once __DIR__ . '/config/database.php';

// Ambil Seluruh Data Cabang Aktif
$cabang_list = [];
$q_cabang = mysqli_query($conn, "SELECT id, nama_cabang, alamat, latitude, longitude, radius_meter, embed_maps FROM cabang WHERE is_active = 1 ORDER BY id ASC");
while ($c = mysqli_fetch_assoc($q_cabang)) {
    $cabang_list[] = [
        'id'           => (int)$c['id'],
        'nama'         => $c['nama_cabang'],
        'alamat'       => $c['alamat'],
        'lat'          => (float)$c['latitude'],
        'lng'          => (float)$c['longitude'],
        'radius_meter' => (int)$c['radius_meter']
    ];
}

// Ambil Aturan Shift dari Database
$aturan_shift = [];
$q_shift = mysqli_query($conn, "SELECT * FROM shift_settings ORDER BY kode_shift ASC");
while ($s = mysqli_fetch_assoc($q_shift)) {
    $aturan_shift[$s['kode_shift']] = [
        'kode'         => $s['kode_shift'],
        'nama'         => $s['nama_shift'],
        'jam_masuk'    => substr($s['jam_masuk'], 0, 5),
        'jam_pulang'   => substr($s['jam_pulang'], 0, 5),
        'masuk_menit'  => (int)substr($s['jam_masuk'], 0, 2) * 60 + (int)substr($s['jam_masuk'], 3, 2),
        'pulang_menit' => (int)substr($s['jam_pulang'], 0, 2) * 60 + (int)substr($s['jam_pulang'], 3, 2)
    ];
}

// Ambil Daftar Karyawan
$q_users = mysqli_query($conn, "SELECT id, nama, status_kerja, cabang_id FROM users ORDER BY nama ASC");
$users = [];
while ($u = mysqli_fetch_assoc($q_users)) {
    $users[] = $u;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>E-Presensi Pegawai | Sistem Absensi Multi-Outlet</title>
    
    <!-- Typography & Icons -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Outfit:wght@500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <style>
        :root {
            /* Vibrant Brand & Culinary Theme */
            --brand-gold-start: #D97706;
            --brand-gold-mid: #F59E0B;
            --brand-gold-end: #FBBF24;
            --brand-deep: #92400E;
            --brand-amber-light: #FFFBEB;
            --brand-amber-border: #FCD34D;
            
            /* Status Colors */
            --emerald-vibrant: #10B981;
            --emerald-deep: #059669;
            --emerald-light: #ECFDF5;
            --rose-vibrant: #F43F5E;
            --rose-deep: #E11D48;
            --rose-light: #FFF1F2;
            --indigo-vibrant: #6366F1;
            
            /* Backgrounds & Surfaces */
            --bg-body: #FFFDF9;
            --surface-card: #FFFFFF;
            --text-dark: #1E1B18;
            --text-muted: #6B7280;
            --border-light: rgba(245, 158, 11, 0.16);
        }

        body {
            background-color: #FFFDF8;
            font-family: 'Plus Jakarta Sans', sans-serif;
            color: var(--text-dark);
            min-height: 100dvh;
            margin: 0;
            padding: 0;
            -webkit-tap-highlight-color: transparent;
        }

        /* Top Brand Navigation Bar */
        .top-navbar {
            background: #FFFFFF;
            border-bottom: 1px solid var(--border-light);
            padding: 14px 20px;
            position: sticky;
            top: 0;
            z-index: 100;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.03);
        }

        .brand-badge {
            width: 44px;
            height: 44px;
            border-radius: 12px;
            background: #FFFBEB;
            border: 1.5px solid #FDE68A;
            display: flex;
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

        .brand-name {
            font-family: 'Outfit', sans-serif;
            font-size: 1.15rem;
            font-weight: 800;
            color: #78350F;
            margin: 0;
            line-height: 1.1;
            letter-spacing: -0.01em;
        }

        .brand-subtitle {
            font-size: 0.72rem;
            color: #B45309;
            font-weight: 600;
            margin: 0;
        }

        .btn-admin-pill {
            background: #FFFFFF;
            color: #78350F;
            border: 1.5px solid #FCD34D;
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: all 0.2s ease;
            box-shadow: 0 2px 6px rgba(217, 119, 6, 0.06);
            text-decoration: none;
        }

        .btn-admin-pill:hover {
            background: #FEF3C7;
            color: #92400E;
        }

        /* Main Container */
        .app-container {
            max-width: 490px;
            margin: 0 auto;
            padding: 18px 16px 45px;
        }

        /* HERO CLOCK CARD (Solid Rich Amber) */
        .hero-clock-card {
            background: #B45309;
            border-radius: 24px;
            padding: 24px 20px;
            color: #FFFFFF;
            position: relative;
            box-shadow: 0 10px 24px -4px rgba(180, 83, 9, 0.35);
            margin-bottom: 20px;
            text-align: center;
        }

        .live-clock {
            font-family: 'Outfit', sans-serif;
            font-size: 3rem;
            font-weight: 800;
            letter-spacing: -0.02em;
            line-height: 1;
            margin-bottom: 6px;
            font-variant-numeric: tabular-nums;
            color: #FFFFFF;
            text-shadow: 0 3px 12px rgba(0, 0, 0, 0.25);
        }

        .live-date {
            font-size: 0.88rem;
            color: #FEF3C7;
            font-weight: 600;
            letter-spacing: 0.01em;
            margin-bottom: 12px;
        }

        .operational-pill {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: rgba(255, 255, 255, 0.18);
            backdrop-filter: blur(8px);
            padding: 4px 14px;
            border-radius: 20px;
            font-size: 0.74rem;
            font-weight: 700;
            color: #FFF;
            border: 1px solid rgba(255, 255, 255, 0.25);
        }

        .beacon-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: #34D399;
            box-shadow: 0 0 10px #34D399;
            animation: pulse-beacon 1.8s infinite;
        }

        @keyframes pulse-beacon {
            0% { transform: scale(0.9); opacity: 0.8; }
            50% { transform: scale(1.3); opacity: 1; }
            100% { transform: scale(0.9); opacity: 0.8; }
        }

        /* Segmented Mode Bar */
        .mode-nav-pill {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            background: #F3E8D8;
            padding: 4px;
            border-radius: 16px;
            margin-bottom: 20px;
            gap: 5px;
            border: 1px solid rgba(217, 119, 6, 0.15);
        }

        .mode-tab {
            border: none;
            background: transparent;
            padding: 10px 0;
            border-radius: 12px;
            font-size: 0.86rem;
            font-weight: 700;
            color: #92400E;
            transition: all 0.25s cubic-bezier(0.16, 1, 0.3, 1);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
        }

        .mode-tab.active {
            background: #FFFFFF;
            color: #78350F;
            box-shadow: 0 4px 14px rgba(217, 119, 6, 0.15);
        }

        /* Main Form Card */
        .card-surface {
            background: rgba(255, 255, 255, 0.94);
            backdrop-filter: blur(12px);
            border-radius: 24px;
            padding: 26px 22px;
            border: 1px solid rgba(245, 158, 11, 0.2);
            box-shadow: 0 12px 30px -4px rgba(217, 119, 6, 0.08);
        }

        .input-title {
            font-size: 0.76rem;
            text-transform: uppercase;
            letter-spacing: 0.07em;
            font-weight: 800;
            color: #92400E;
            margin-bottom: 7px;
            display: block;
        }

        .form-select, .form-control {
            border: 1.5px solid #FDE68A;
            border-radius: 12px;
            padding: 12px 14px;
            font-size: 0.92rem;
            color: var(--text-dark);
            background: #FFFDF9;
            transition: all 0.25s ease;
        }

        .form-select:focus, .form-control:focus {
            background: #FFFFFF;
            border-color: #D97706;
            box-shadow: 0 0 0 4px rgba(217, 119, 6, 0.15);
        }

        /* Branch Selector Cards (Vibrant & Distinct) */
        .branch-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
            margin-bottom: 16px;
        }

        .branch-card {
            border: 1.5px solid #FDE68A;
            border-radius: 14px;
            padding: 12px 12px;
            cursor: pointer;
            transition: all 0.25s ease;
            background: #FFFDF9;
            text-align: left;
            position: relative;
        }

        .branch-card.selected {
            border-color: #D97706;
            background: #FEF3C7;
            box-shadow: 0 4px 12px rgba(217, 119, 6, 0.15);
        }

        .branch-card-header {
            display: flex;
            align-items: center;
            gap: 6px;
            margin-bottom: 3px;
        }

        .branch-icon {
            font-size: 0.95rem;
            color: #D97706;
        }

        .branch-card.selected .branch-icon {
            color: #92400E;
        }

        .branch-name-title {
            font-size: 0.82rem;
            font-weight: 800;
            color: #78350F;
            line-height: 1.2;
        }

        .branch-dist-badge {
            font-size: 0.72rem;
            font-weight: 700;
            color: #B45309;
            display: flex;
            align-items: center;
            gap: 4px;
            margin-top: 4px;
        }

        /* Action Switcher: Masuk vs Pulang */
        .action-selector {
            display: grid;
            grid-template-columns: 1fr 1fr;
            background: #FEF3C7;
            padding: 5px;
            border-radius: 14px;
            gap: 6px;
            margin-bottom: 16px;
            border: 1px solid #FDE68A;
        }

        .action-btn {
            padding: 11px 0;
            text-align: center;
            border-radius: 10px;
            font-size: 0.9rem;
            font-weight: 800;
            cursor: pointer;
            transition: all 0.2s ease;
            color: #92400E;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
        }

        .action-btn.active-masuk {
            background: #059669;
            color: #FFFFFF;
            box-shadow: 0 4px 12px rgba(5, 150, 105, 0.3);
        }

        .action-btn.active-pulang {
            background: #E11D48;
            color: #FFFFFF;
            box-shadow: 0 4px 12px rgba(225, 29, 72, 0.3);
        }

        /* Modern Camera Viewfinder */
        .camera-viewport-frame {
            width: 100%;
            height: 255px;
            background: #18181B;
            border-radius: 18px;
            position: relative;
            overflow: hidden;
            margin-bottom: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: inset 0 0 20px rgba(0, 0, 0, 0.4);
            border: 2px solid #FCD34D;
        }

        #camera-video, #camera-canvas {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transform: scaleX(-1);
        }

        .camera-overlay-mask {
            position: absolute;
            inset: 0;
            border: 2.5px dashed #F59E0B;
            margin: 20px 38px;
            border-radius: 50%;
            pointer-events: none;
            display: flex;
            align-items: flex-end;
            justify-content: center;
            padding-bottom: 14px;
            box-shadow: 0 0 0 9999px rgba(0, 0, 0, 0.32);
        }

        .camera-overlay-tag {
            background: rgba(180, 83, 9, 0.85);
            backdrop-filter: blur(4px);
            color: #FFFFFF;
            font-size: 0.7rem;
            font-weight: 700;
            padding: 4px 10px;
            border-radius: 8px;
            letter-spacing: 0.05em;
        }

        .btn-flip-cam {
            position: absolute;
            top: 12px;
            right: 12px;
            background: rgba(0, 0, 0, 0.6);
            backdrop-filter: blur(6px);
            color: #FBBF24;
            border: 1px solid rgba(251, 191, 36, 0.4);
            width: 38px;
            height: 38px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.95rem;
            z-index: 10;
            transition: all 0.2s ease;
        }

        .btn-flip-cam:hover {
            background: #B45309;
            color: #FFFFFF;
        }

        /* Geofence Alert Pill */
        .geofence-alert-box {
            border-radius: 12px;
            padding: 10px 14px;
            font-size: 0.84rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 16px;
        }

        .geo-valid {
            background: #ECFDF5;
            color: #065F46;
            border: 1.5px solid #6EE7B7;
        }

        .geo-invalid {
            background: #FFF1F2;
            color: #9F1239;
            border: 1.5px solid #FDA4AF;
        }

        .geo-loading {
            background: #FFFBEB;
            color: #92400E;
            border: 1.5px solid #FCD34D;
        }

        /* VIBRANT SOLID CTA BUTTON */
        .btn-cta-submit {
            background: #D97706;
            color: #FFFFFF;
            border: none;
            border-radius: 14px;
            padding: 15px;
            font-family: 'Outfit', sans-serif;
            font-weight: 800;
            font-size: 1.05rem;
            letter-spacing: 0.02em;
            width: 100%;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            box-shadow: 0 6px 18px rgba(217, 119, 6, 0.35);
        }

        .btn-cta-submit:hover:not(:disabled) {
            background: #B45309;
            transform: translateY(-2px);
            box-shadow: 0 8px 22px rgba(217, 119, 6, 0.45);
        }

        .btn-cta-submit:active:not(:disabled) {
            background: #92400E;
            transform: scale(0.98);
        }

        .btn-cta-submit:disabled {
            background: #D1D5DB;
            color: #9CA3AF;
            box-shadow: none;
            cursor: not-allowed;
            transform: none;
        }
    </style>
</head>
<body>

    <!-- Top Brand Navbar -->
    <header class="top-navbar">
        <div class="d-flex align-items-center justify-content-between">
            <div class="d-flex align-items-center gap-3">
                <div class="brand-badge">
                    <img src="assets/img/logo.png" alt="Logo E-Presensi">
                </div>
                <div>
                    <h1 class="brand-name">E-Presensi Karyawan</h1>
                    <p class="brand-subtitle">Sistem Kehadiran Multi-Outlet</p>
                </div>
            </div>
            <div>
                <a href="login.php" class="btn-admin-pill">
                    <i class="fas fa-user-shield text-warning"></i> Admin
                </a>
            </div>
        </div>
    </header>

    <main class="app-container">

        <!-- Radiant Clock Card -->
        <div class="hero-clock-card">
            <div class="live-clock" id="display-clock">00:00:00</div>
            <div class="live-date" id="display-date">Memuat kalender...</div>
            <div class="operational-pill">
                <span class="beacon-dot"></span>
                <span>Outlet Beroperasi &bull; Siap Melayani</span>
            </div>
        </div>

        <!-- Mode Nav (Absen, Izin, Histori) -->
        <div class="mode-nav-pill">
            <button type="button" class="mode-tab active" id="btn-mode-absen" onclick="setMode('absen')">
                <i class="fas fa-fingerprint"></i> Absensi
            </button>
            <button type="button" class="mode-tab" id="btn-mode-izin" onclick="setMode('izin')">
                <i class="fas fa-file-medical"></i> Izin / Sakit
            </button>
            <button type="button" class="mode-tab" onclick="window.location.href='riwayat.php'">
                <i class="fas fa-clock-rotate-left"></i> Histori
            </button>
        </div>

        <!-- FORM UTAMA -->
        <form action="handlers/proses_absen.php" method="POST" id="form-absensi">
            <?= csrf_field() ?>
            <input type="hidden" name="tipe_form" id="tipe_form" value="absen">
            <input type="hidden" name="cabang_id" id="cabang_id" value="<?= $cabang_list[0]['id'] ?? 1 ?>">
            <input type="hidden" name="lokasi" id="lokasi" value="">
            <input type="hidden" name="foto" id="foto" value="">
            <input type="hidden" name="aksi" id="aksi" value="masuk">

            <div class="card-surface">
                
                <!-- 1. Pilihan Cabang (2 Cabang Resmi) -->
                <div class="mb-3">
                    <label class="input-title">Pilih Cabang Outlet</label>
                    <div class="branch-grid">
                        <?php foreach ($cabang_list as $idx => $cab): ?>
                            <div class="branch-card <?= $idx === 0 ? 'selected' : '' ?>" 
                                 id="branch-card-<?= $cab['id'] ?>" 
                                 onclick="selectBranch(<?= $cab['id'] ?>)">
                                <div class="branch-card-header">
                                    <i class="fas fa-store-alt branch-icon"></i>
                                    <span class="branch-name-title"><?= e($cab['nama']) ?></span>
                                </div>
                                <div class="branch-dist-badge" id="dist-label-<?= $cab['id'] ?>">
                                    <i class="fas fa-check-circle text-success"></i> <span>Cabang Aktif</span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- 2. Pilih Nama Karyawan -->
                <div class="mb-3">
                    <label class="input-title" for="user_id">Nama Karyawan</label>
                    <select name="user_id" id="user_id" class="form-select" required onchange="handleUserChange()">
                        <option value="">-- Sentuh untuk memilih nama Anda --</option>
                        <?php foreach ($users as $u): ?>
                            <option value="<?= $u['id'] ?>" 
                                    data-cabang="<?= $u['cabang_id'] ?>"
                                    data-status="<?= $u['status_kerja'] ?>">
                                <?= e($u['nama']) ?> &bull; <?= e($u['status_kerja']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- 3. PIN Keamanan Karyawan -->
                <div class="mb-3">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <label class="input-title mb-0" for="pin">PIN Keamanan</label>
                        <small class="text-muted" style="cursor: pointer;" onclick="togglePinView()">
                            <i class="fas fa-eye text-warning" id="pin-eye-icon"></i> <span id="pin-eye-text">Lihat PIN</span>
                        </small>
                    </div>
                    <input type="password" name="pin" id="pin" class="form-control text-center fw-bold fs-4" 
                           placeholder="••••" maxlength="6" inputmode="numeric" required 
                           style="letter-spacing: 8px;">
                </div>

                <!-- SEKSI KHUSUS ABSENSI MASUK / PULANG -->
                <div id="section-absen">
                    
                    <!-- Toggle Masuk / Pulang -->
                    <div class="action-selector">
                        <div class="action-btn active-masuk" id="tab-masuk" onclick="setAction('masuk')">
                            <i class="fas fa-arrow-right-to-bracket"></i> ABSEN MASUK
                        </div>
                        <div class="action-btn" id="tab-pulang" onclick="setAction('pulang')">
                            <i class="fas fa-arrow-right-from-bracket"></i> ABSEN PULANG
                        </div>
                    </div>

                    <!-- Status Kerja & Jam Shift -->
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="input-title" for="status_kerja">Tipe Kehadiran</label>
                            <select name="status_kerja" id="status_kerja" class="form-select" onchange="filterShifts()">
                                <option value="Fulltime">Fulltime</option>
                                <option value="Parttime">Parttime</option>
                                <option value="Lembur">Lembur</option>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="input-title" for="shift_pilih">Pilihan Shift</label>
                            <select name="shift_pilih" id="shift_pilih" class="form-select" onchange="validateShiftTimes()">
                                <!-- Dynamic Options -->
                            </select>
                        </div>
                    </div>

                    <!-- Feedback Alert Shift -->
                    <div id="shift-feedback-box" class="alert py-2 px-3 small text-center mb-3 fw-bold" style="border-radius: 12px;">
                        ...
                    </div>
                </div>

                <!-- SEKSI KHUSUS IZIN / SAKIT -->
                <div id="section-izin" style="display: none;">
                    <div class="mb-3">
                        <label class="input-title" for="keterangan_izin">Alasan / Keterangan Izin</label>
                        <textarea name="keterangan" id="keterangan_izin" class="form-control" rows="3" 
                                  placeholder="Contoh: Sakit demam berobat ke klinik, urusan keluarga mendesak"></textarea>
                    </div>
                </div>

                <!-- Kamera HTML5 -->
                <div class="mb-2">
                    <label class="input-title d-flex justify-content-between">
                        <span id="cam-label">Verifikasi Wajah (Kamera)</span>
                        <span class="text-muted fw-normal" style="font-size: 0.7rem;">Arahkan wajah di dalam lingkaran</span>
                    </label>
                    <div class="camera-viewport-frame">
                        <video id="camera-video" autoplay playsinline muted></video>
                        <canvas id="camera-canvas" style="display: none;"></canvas>
                        
                        <div class="camera-overlay-mask">
                            <span class="camera-overlay-tag"><i class="fas fa-face-smile me-1"></i>PANDUAN WAJAH</span>
                        </div>

                        <button type="button" class="btn-flip-cam" onclick="toggleCameraFacing()" title="Putar Kamera">
                            <i class="fas fa-camera-rotate"></i>
                        </button>
                    </div>
                </div>

                <!-- Status Validasi Presensi (Mode Foto Wajah) -->
                <div id="geofence-alert" class="geofence-alert-box geo-valid">
                    <span><i class="fas fa-camera text-success me-2"></i>Validasi Foto Wajah Aktif</span>
                    <span class="badge bg-success">Siap Absen</span>
                </div>

                <!-- Tombol Submit Utama -->
                <button type="button" id="btn-submit" class="btn-cta-submit" onclick="handleSubmit()">
                    <i class="fas fa-circle-check"></i> <span id="btn-text">KIRIM ABSEN MASUK</span>
                </button>

            </div>
        </form>

    </main>

    <!-- Client-side Logic -->
    <script>
        const cabangDB = <?= json_encode($cabang_list) ?>;
        const aturanDB = <?= json_encode($aturan_shift) ?>;
        
        let currentMode = 'absen';
        let currentAction = 'masuk';
        let selectedCabangId = cabangDB[0]?.id || 1;
        let userCoords = null;
        let isGeofenceValid = true; // GPS bypass, validasi via foto selfie
        let facingMode = 'user';
        let videoStream = null;

        // 1. Live Clock
        function updateClock() {
            const now = new Date();
            document.getElementById('display-clock').innerText = now.toLocaleTimeString('id-ID', { hour12: false });
            document.getElementById('display-date').innerText = now.toLocaleDateString('id-ID', {
                weekday: 'long', day: 'numeric', month: 'long', year: 'numeric'
            });
            if (currentMode === 'absen') validateShiftTimes();
        }
        setInterval(updateClock, 1000);
        updateClock();

        // 2. Camera HTML5 MediaDevices
        async function startCamera() {
            if (videoStream) {
                videoStream.getTracks().forEach(track => track.stop());
            }
            try {
                videoStream = await navigator.mediaDevices.getUserMedia({
                    video: {
                        facingMode: facingMode,
                        width: { ideal: 640 },
                        height: { ideal: 480 }
                    },
                    audio: false
                });
                const videoEl = document.getElementById('camera-video');
                videoEl.srcObject = videoStream;
            } catch (err) {
                console.warn("Kamera tidak dapat diakses:", err);
            }
        }

        function toggleCameraFacing() {
            facingMode = (facingMode === 'user') ? 'environment' : 'user';
            startCamera();
        }

        // 3. Shift Filtering & Precise Timing
        function filterShifts() {
            const status = document.getElementById('status_kerja').value;
            const selectEl = document.getElementById('shift_pilih');
            selectEl.innerHTML = "";

            let count = 0;
            const totalShifts = Object.keys(aturanDB).length;

            // Jika hanya ada 1 shift operasional di database, gunakan langsung untuk semua status pegawai
            if (totalShifts === 1) {
                for (const kode in aturanDB) {
                    const shift = aturanDB[kode];
                    const opt = document.createElement('option');
                    opt.value = shift.kode;
                    opt.textContent = `${shift.nama} (${shift.jam_masuk} - ${shift.jam_pulang})`;
                    selectEl.appendChild(opt);
                    count++;
                }
            } else {
                for (const kode in aturanDB) {
                    const shift = aturanDB[kode];
                    let match = false;
                    if (status === 'Fulltime' && kode.startsWith('FT')) match = true;
                    else if (status === 'Parttime' && kode.startsWith('PT')) match = true;
                    else if (status === 'Lembur' && kode.startsWith('LB')) match = true;

                    if (match) {
                        const opt = document.createElement('option');
                        opt.value = shift.kode;
                        opt.textContent = `${shift.nama} (${shift.jam_masuk} - ${shift.jam_pulang})`;
                        selectEl.appendChild(opt);
                        count++;
                    }
                }

                // Fallback: Jika tidak ada shift dengan prefix spesifik, tampilkan seluruh shift aktif
                if (count === 0) {
                    for (const kode in aturanDB) {
                        const shift = aturanDB[kode];
                        const opt = document.createElement('option');
                        opt.value = shift.kode;
                        opt.textContent = `${shift.nama} (${shift.jam_masuk} - ${shift.jam_pulang})`;
                        selectEl.appendChild(opt);
                        count++;
                    }
                }
            }

            if (count === 0) {
                selectEl.innerHTML = `<option value="">-- Tidak ada shift --</option>`;
            }

            validateShiftTimes();
        }

        function validateShiftTimes() {
            if (currentMode !== 'absen') return;
            const shiftKode = document.getElementById('shift_pilih').value;
            const shift = aturanDB[shiftKode];
            const feedbackBox = document.getElementById('shift-feedback-box');

            if (!shift) {
                feedbackBox.className = 'alert alert-secondary py-2 px-3 small text-center mb-3 fw-bold';
                feedbackBox.innerHTML = `Pilih shift kerja.`;
                return;
            }

            const now = new Date();
            const currentTotalMinutes = now.getHours() * 60 + now.getMinutes();

            if (currentAction === 'masuk') {
                if (currentTotalMinutes > shift.masuk_menit) {
                    const lateMin = currentTotalMinutes - shift.masuk_menit;
                    feedbackBox.className = 'alert alert-danger py-2 px-3 small text-center mb-3 fw-bold';
                    feedbackBox.innerHTML = `<i class="fas fa-clock text-danger me-1"></i> Terlambat <strong>${lateMin} menit</strong> (Batas Masuk: ${shift.jam_masuk})`;
                } else {
                    feedbackBox.className = 'alert alert-success py-2 px-3 small text-center mb-3 fw-bold';
                    feedbackBox.innerHTML = `<i class="fas fa-check-circle text-success me-1"></i> Tepat Waktu (Jadwal Masuk: ${shift.jam_masuk})`;
                }
                updateSubmitState();
            } else {
                if (currentTotalMinutes < shift.pulang_menit && shift.pulang_menit - currentTotalMinutes < 720) {
                    feedbackBox.className = 'alert alert-warning py-2 px-3 small text-center mb-3 fw-bold';
                    feedbackBox.innerHTML = `<i class="fas fa-lock text-warning me-1"></i> Belum Jam Pulang (Buka Pukul ${shift.jam_pulang})`;
                } else {
                    feedbackBox.className = 'alert alert-success py-2 px-3 small text-center mb-3 fw-bold';
                    feedbackBox.innerHTML = `<i class="fas fa-check-circle text-success me-1"></i> Waktu Pulang Terbuka (Jadwal: ${shift.jam_pulang})`;
                }
                updateSubmitState();
            }
        }

        // 4. Geolocation & Haversine Distance
        function hitungJarakMeter(lat1, lon1, lat2, lon2) {
            const R = 6371000;
            const dLat = (lat2 - lat1) * Math.PI / 180;
            const dLon = (lon2 - lon1) * Math.PI / 180;
            const a = Math.sin(dLat / 2) * Math.sin(dLat / 2) +
                      Math.cos(lat1 * Math.PI / 180) * Math.cos(lat2 * Math.PI / 180) *
                      Math.sin(dLon / 2) * Math.sin(dLon / 2);
            const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
            return Math.round(R * c);
        }

        // 4. Geolocation (Bypass / Opsional - Tidak Memblokir Presensi)
        function checkGeolocation() {
            if (!navigator.geolocation) return;

            navigator.geolocation.getCurrentPosition(
                (pos) => {
                    const lat = pos.coords.latitude;
                    const lng = pos.coords.longitude;
                    userCoords = { lat, lng };
                    document.getElementById('lokasi').value = `${lat},${lng}`;
                },
                (err) => {
                    // Fallback tenang tanpa popup error atau blokir tombol
                    const selectedCab = cabangDB.find(c => c.id === selectedCabangId);
                    document.getElementById('lokasi').value = `Presensi Foto (${selectedCab?.nama || 'Outlet'})`;
                },
                { enableHighAccuracy: false, timeout: 5000, maximumAge: 60000 }
            );
        }

        function selectBranch(cabId) {
            selectedCabangId = cabId;
            document.getElementById('cabang_id').value = cabId;

            cabangDB.forEach(cab => {
                const card = document.getElementById(`branch-card-${cab.id}`);
                if (card) {
                    if (cab.id === cabId) card.classList.add('selected');
                    else card.classList.remove('selected');
                }
            });

            const selectedCab = cabangDB.find(c => c.id === cabId);
            if (!userCoords) {
                document.getElementById('lokasi').value = `Presensi Foto (${selectedCab?.nama || 'Outlet'})`;
            }
        }

        function updateGeofenceUI(valid, text, subtext = '') {
            const pill = document.getElementById('geofence-alert');
            if (pill) {
                pill.className = "geofence-alert-box geo-valid";
                pill.innerHTML = `<span><i class="fas fa-camera text-success me-2"></i>Validasi Foto Wajah Aktif</span><span class="badge bg-success">Siap Absen</span>`;
            }
        }

        function updateSubmitState() {
            const btn = document.getElementById('btn-submit');
            if (btn) btn.disabled = false;
        }

        // 5. Mode & Action Toggles
        function setMode(mode) {
            currentMode = mode;
            document.getElementById('tipe_form').value = mode;

            const btnAbsen = document.getElementById('btn-mode-absen');
            const btnIzin = document.getElementById('btn-mode-izin');
            const secAbsen = document.getElementById('section-absen');
            const secIzin = document.getElementById('section-izin');
            const btnText = document.getElementById('btn-text');
            const camLabel = document.getElementById('cam-label');

            if (mode === 'absen') {
                btnAbsen.classList.add('active');
                btnIzin.classList.remove('active');
                secAbsen.style.display = 'block';
                secIzin.style.display = 'none';
                btnText.innerText = currentAction === 'masuk' ? "KIRIM ABSEN MASUK" : "KIRIM ABSEN PULANG";
                camLabel.innerText = "Verifikasi Wajah (Kamera)";
            } else {
                btnAbsen.classList.remove('active');
                btnIzin.classList.add('active');
                secAbsen.style.display = 'none';
                secIzin.style.display = 'block';
                btnText.innerText = "AJUKAN SURAT IZIN / SAKIT";
                camLabel.innerText = "Foto Bukti Izin / Surat Dokter";
            }
            updateSubmitState();
        }

        function setAction(act) {
            currentAction = act;
            document.getElementById('aksi').value = act;
            const tabMasuk = document.getElementById('tab-masuk');
            const tabPulang = document.getElementById('tab-pulang');
            const btnText = document.getElementById('btn-text');

            if (act === 'masuk') {
                tabMasuk.className = "action-btn active-masuk";
                tabPulang.className = "action-btn";
                btnText.innerText = "KIRIM ABSEN MASUK";
            } else {
                tabMasuk.className = "action-btn";
                tabPulang.className = "action-btn active-pulang";
                btnText.innerText = "KIRIM ABSEN PULANG";
            }
            validateShiftTimes();
        }

        function handleUserChange() {
            const selectEl = document.getElementById('user_id');
            const selectedOpt = selectEl.options[selectEl.selectedIndex];
            if (!selectedOpt || !selectedOpt.value) return;

            const userStatus = selectedOpt.getAttribute('data-status');
            const userCabang = parseInt(selectedOpt.getAttribute('data-cabang'));

            if (userStatus) {
                document.getElementById('status_kerja').value = userStatus;
                filterShifts();
            }

            if (userCabang && cabangDB.some(c => c.id === userCabang)) {
                selectBranch(userCabang);
            }
        }

        function togglePinView() {
            const pinInp = document.getElementById('pin');
            const icon = document.getElementById('pin-eye-icon');
            const text = document.getElementById('pin-eye-text');
            if (!pinInp) return;
            if (pinInp.type === 'password') {
                pinInp.type = 'text';
                if (icon) icon.className = 'fas fa-eye-slash text-warning';
                if (text) text.innerText = 'Sembunyikan';
            } else {
                pinInp.type = 'password';
                if (icon) icon.className = 'fas fa-eye text-warning';
                if (text) text.innerText = 'Lihat PIN';
            }
        }

        // 6. Capture WebP Snapshot and Submit
        function handleSubmit() {
            const userId = document.getElementById('user_id').value;
            const pin = document.getElementById('pin').value;

            if (!userId) {
                Swal.fire({ icon: 'warning', title: 'Nama Pegawai Wajib Dipilih', confirmButtonColor: '#D97706' });
                return;
            }
            if (!pin || pin.length < 4) {
                Swal.fire({ icon: 'warning', title: 'Masukkan PIN Keamanan Anda', confirmButtonColor: '#D97706' });
                return;
            }

            const video = document.getElementById('camera-video');
            const canvas = document.getElementById('camera-canvas');
            canvas.width = 480;
            canvas.height = 360;
            const ctx = canvas.getContext('2d');
            
            if (facingMode === 'user') {
                ctx.translate(canvas.width, 0);
                ctx.scale(-1, 1);
            }
            ctx.drawImage(video, 0, 0, canvas.width, canvas.height);

            const photoData = canvas.toDataURL('image/webp', 0.82);
            document.getElementById('foto').value = photoData;

            Swal.fire({
                title: 'Menyimpan Presensi...',
                text: 'Memverifikasi foto wajah dan data presensi.',
                allowOutsideClick: false,
                didOpen: () => { Swal.showLoading(); }
            });

            document.getElementById('form-absensi').submit();
        }

        window.addEventListener('DOMContentLoaded', () => {
            filterShifts();
            startCamera();
            checkGeolocation();

            <?php if (isset($_GET['status']) && $_GET['status'] === 'sukses'): ?>
                Swal.fire({
                    icon: 'success',
                    title: 'Presensi Berhasil Dicatat!',
                    text: 'Terima kasih, presensi Anda berhasil disimpan!',
                    timer: 2500,
                    showConfirmButton: false
                }).then(() => {
                    window.history.replaceState(null, null, window.location.pathname);
                });
            <?php elseif (isset($_GET['err'])): ?>
                Swal.fire({
                    icon: 'error',
                    title: 'Gagal Memproses Presensi',
                    text: <?= json_encode((string)$_GET['err']) ?>,
                    confirmButtonColor: '#D97706'
                }).then(() => {
                    window.history.replaceState(null, null, window.location.pathname);
                });
            <?php endif; ?>
        });
    </script>
</body>
</html>