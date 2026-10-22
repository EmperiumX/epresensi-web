<?php
// =======================================================================
// BACKEND HANDLER PROSES ABSENSI & VERIFIKASI - E-PRESENSI
// Lokasi: handlers/proses_absen.php
// =======================================================================
require_once dirname(__DIR__) . '/config/session.php';
require_once dirname(__DIR__) . '/config/database.php';

// Helper URL redirect yang kompatibel baik dipanggil langsung maupun lewat root shim
function get_index_url() {
    $script_name = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
    if (strpos($script_name, '/handlers/') !== false) {
        return '../index.php';
    }
    return 'index.php';
}

// Fungsi helper redirect dengan pesan error
function redirect_error($msg) {
    header("Location: " . get_index_url() . "?err=" . urlencode($msg));
    exit;
}

// 1. Validasi Metode & Token CSRF
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: " . get_index_url());
    exit;
}

if (!verify_csrf_token()) {
    redirect_error("Sesi telah kedaluwarsa. Silakan refresh halaman dan coba kembali.");
}

// 2. Ambil Input Utama
$tipe_form   = isset($_POST['tipe_form']) ? trim($_POST['tipe_form']) : 'absen';
$user_id     = isset($_POST['user_id']) ? (int)$_POST['user_id'] : 0;
$cabang_id   = isset($_POST['cabang_id']) ? (int)$_POST['cabang_id'] : 1;
$pin_input   = isset($_POST['pin']) ? trim($_POST['pin']) : '';
$lokasi_raw  = isset($_POST['lokasi']) ? trim($_POST['lokasi']) : '';
$foto_base64 = isset($_POST['foto']) ? $_POST['foto'] : '';
$tanggal     = date('Y-m-d');
$jam_sekarang = date('H:i:s');

if ($user_id <= 0) {
    redirect_error("Nama karyawan belum dipilih.");
}

if (empty($pin_input)) {
    redirect_error("PIN keamanan wajib diisi.");
}

// 3. Verifikasi Identitas & PIN Karyawan (Prepared Statement)
$stmt_user = mysqli_prepare($conn, "SELECT id, nama, pin, status_kerja, cabang_id FROM users WHERE id = ? LIMIT 1");
mysqli_stmt_bind_param($stmt_user, "i", $user_id);
mysqli_stmt_execute($stmt_user);
$res_user = mysqli_stmt_get_result($stmt_user);
$data_user = mysqli_fetch_assoc($res_user);

if (!$data_user) {
    redirect_error("Data karyawan tidak ditemukan dalam sistem.");
}

$pin_db = trim((string)$data_user['pin']);
$is_pin_valid = false;

// Cek 1: Verifikasi via Hash Bcrypt resmi (panjang hash bcrypt valid minimal 60 karakter)
if (strlen($pin_db) >= 60 && password_verify($pin_input, $pin_db)) {
    $is_pin_valid = true;
}
// Cek 2: Verifikasi Plain-text (misal data bawaan '1234' atau PIN baru sebelum hash)
elseif ($pin_input === $pin_db) {
    $is_pin_valid = true;
}
// Cek 3: Self-healing data korup (jika PIN di database terpotong akibat limit kolom lama seperti '$2y$' atau '0', izinkan default '1234')
elseif (($pin_db === '0' || $pin_db === '' || (strpos($pin_db, '$') === 0 && strlen($pin_db) < 60)) && $pin_input === '1234') {
    $is_pin_valid = true;
}

if (!$is_pin_valid) {
    redirect_error("PIN keamanan yang Anda masukkan salah.");
}

// Rehash & Amankan PIN jika masih plain text atau baru pulih dari status korup
if (strlen($pin_db) < 60 || password_needs_rehash($pin_db, PASSWORD_BCRYPT)) {
    $hashed_pin = password_hash($pin_input, PASSWORD_BCRYPT);
    $stmt_rehash = mysqli_prepare($conn, "UPDATE users SET pin = ? WHERE id = ?");
    mysqli_stmt_bind_param($stmt_rehash, "si", $hashed_pin, $user_id);
    mysqli_stmt_execute($stmt_rehash);
}

// 4. Verifikasi Data Cabang (Validasi Foto Utama, GPS Dinonaktifkan Sementara)
$stmt_cab = mysqli_prepare($conn, "SELECT id, nama_cabang, latitude, longitude, radius_meter FROM cabang WHERE id = ? AND is_active = 1 LIMIT 1");
mysqli_stmt_bind_param($stmt_cab, "i", $cabang_id);
mysqli_stmt_execute($stmt_cab);
$res_cab = mysqli_stmt_get_result($stmt_cab);
$data_cab = mysqli_fetch_assoc($res_cab);

if (!$data_cab) {
    redirect_error("Cabang outlet tidak valid atau sedang nonaktif.");
}

// GPS dinonaktifkan sementara sesuai instruksi manager (validasi utama via foto selfie)
if (empty($lokasi_raw) || strpos($lokasi_raw, ',') === false) {
    $lokasi_raw = "Verifikasi Foto (" . ($data_cab['nama_cabang'] ?? 'Outlet') . ")";
}

// 5. Simpan Berkas Foto (Dukungan WebP / JPEG)
$folder_upload = dirname(__DIR__) . '/uploads/';
if (!file_exists($folder_upload)) {
    @mkdir($folder_upload, 0755, true);
}

$nama_foto = "";
if (!empty($foto_base64) && strpos($foto_base64, ';base64,') !== false) {
    list($format_header, $data_encoded) = explode(';base64,', $foto_base64, 2);
    $ext = (strpos($format_header, 'image/webp') !== false) ? '.webp' : '.jpg';
    $decoded_bytes = base64_decode($data_encoded);

    if ($decoded_bytes !== false && strlen($decoded_bytes) > 200) {
        $nama_foto = uniqid('absen_', true) . '_' . date('Ymd_His') . $ext;
        file_put_contents($folder_upload . $nama_foto, $decoded_bytes);
    } else {
        redirect_error("Hasil foto kamera tidak terbaca. Silakan ulangi pengambilan foto.");
    }
} else {
    redirect_error("Foto verifikasi wajah wajib diambil sebelum mengirim data.");
}

// 6. Eksekusi Penyimpanan ke Basis Data
if ($tipe_form === 'izin') {
    // Mode Izin / Sakit
    $keterangan = isset($_POST['keterangan']) ? trim($_POST['keterangan']) : 'Izin tidak masuk kerja';

    // Cek duplikasi absensi pada hari ini
    $stmt_cek = mysqli_prepare($conn, "SELECT id FROM attendance WHERE user_id = ? AND tanggal = ? LIMIT 1");
    mysqli_stmt_bind_param($stmt_cek, "is", $user_id, $tanggal);
    mysqli_stmt_execute($stmt_cek);
    $res_cek = mysqli_stmt_get_result($stmt_cek);

    if (mysqli_num_rows($res_cek) > 0) {
        redirect_error("Anda sudah memiliki catatan kehadiran/izin pada hari ini.");
    }

    $stmt_insert = mysqli_prepare($conn, "INSERT INTO attendance 
        (user_id, cabang_id, tanggal, jam_masuk, foto_masuk, lokasi_masuk, shift, keterangan, status_kerja, terlambat_menit) 
        VALUES (?, ?, ?, ?, ?, ?, 'Izin', ?, 'Izin', 0)");
    
    $lokasi_simpan = $lokasi_raw ?: "Lokasi Izin";
    mysqli_stmt_bind_param($stmt_insert, "iisssss", $user_id, $cabang_id, $tanggal, $jam_sekarang, $nama_foto, $lokasi_simpan, $keterangan);
    
    if (mysqli_stmt_execute($stmt_insert)) {
        header("Location: " . get_index_url() . "?status=sukses");
        exit;
    } else {
        redirect_error("Terjadi galat database saat menyimpan izin.");
    }

} else {
    // Mode Absen Kerja (Masuk atau Pulang)
    $aksi         = isset($_POST['aksi']) && $_POST['aksi'] === 'pulang' ? 'pulang' : 'masuk';
    $status_kerja = isset($_POST['status_kerja']) ? trim($_POST['status_kerja']) : $data_user['status_kerja'];
    $shift_pilih  = isset($_POST['shift_pilih']) ? trim($_POST['shift_pilih']) : '';

    // Ambil Aturan Shift dari Database
    if (!empty($shift_pilih)) {
        $stmt_shift = mysqli_prepare($conn, "SELECT nama_shift, jam_masuk, jam_pulang FROM shift_settings WHERE kode_shift = ? LIMIT 1");
        mysqli_stmt_bind_param($stmt_shift, "s", $shift_pilih);
        mysqli_stmt_execute($stmt_shift);
        $res_shift = mysqli_stmt_get_result($stmt_shift);
        $data_shift = mysqli_fetch_assoc($res_shift);
    } else {
        $res_shift = mysqli_query($conn, "SELECT nama_shift, jam_masuk, jam_pulang FROM shift_settings ORDER BY id ASC LIMIT 1");
        $data_shift = mysqli_fetch_assoc($res_shift);
    }

    $label_shift      = $data_shift ? $data_shift['nama_shift'] : "Shift Operasional";
    $jam_masuk_jadwal = $data_shift ? $data_shift['jam_masuk'] : "14:00:00";

    if ($aksi === 'masuk') {
        // Cek duplikasi absen masuk hari ini
        $stmt_cek = mysqli_prepare($conn, "SELECT id FROM attendance WHERE user_id = ? AND tanggal = ? LIMIT 1");
        mysqli_stmt_bind_param($stmt_cek, "is", $user_id, $tanggal);
        mysqli_stmt_execute($stmt_cek);
        if (mysqli_num_rows(mysqli_stmt_get_result($stmt_cek)) > 0) {
            redirect_error("Anda sudah melakukan absen masuk pada hari ini.");
        }

        // Kalkulasi Keterlambatan Presisi
        $terlambat_menit = 0;
        $detik_jadwal = strtotime($tanggal . ' ' . $jam_masuk_jadwal);
        $detik_absen  = strtotime($tanggal . ' ' . $jam_sekarang);

        if ($detik_absen > $detik_jadwal) {
            $terlambat_menit = (int)floor(($detik_absen - $detik_jadwal) / 60);
        }

        $stmt_ins = mysqli_prepare($conn, "INSERT INTO attendance 
            (user_id, cabang_id, tanggal, jam_masuk, jam_pulang, foto_masuk, lokasi_masuk, shift, status_kerja, terlambat_menit, keterangan) 
            VALUES (?, ?, ?, ?, NULL, ?, ?, ?, ?, ?, '')");
        
        mysqli_stmt_bind_param($stmt_ins, "iissssssi", 
            $user_id, $cabang_id, $tanggal, $jam_sekarang, $nama_foto, $lokasi_raw, $label_shift, $status_kerja, $terlambat_menit);
        
        if (mysqli_stmt_execute($stmt_ins)) {
            header("Location: " . get_index_url() . "?status=sukses");
            exit;
        } else {
            redirect_error("Gagal mencatat absensi masuk ke basis data.");
        }

    } else {
        // Aksi Pulang (Mendukung Shift Malam yang lewat tengah malam)
        $kemarin = date('Y-m-d', strtotime('-1 day'));
        $stmt_cek_masuk = mysqli_prepare($conn, "SELECT id, tanggal, jam_masuk FROM attendance 
            WHERE user_id = ? AND jam_pulang IS NULL AND shift != 'Izin' AND (tanggal = ? OR tanggal = ?) 
            ORDER BY id DESC LIMIT 1");
        mysqli_stmt_bind_param($stmt_cek_masuk, "iss", $user_id, $tanggal, $kemarin);
        mysqli_stmt_execute($stmt_cek_masuk);
        $res_masuk = mysqli_stmt_get_result($stmt_cek_masuk);
        $rec_masuk = mysqli_fetch_assoc($res_masuk);

        if (!$rec_masuk) {
            redirect_error("Tidak ditemukan catatan absen masuk yang aktif, atau Anda sudah melakukan absen pulang sebelumnya.");
        }

        $att_id = (int)$rec_masuk['id'];
        $stmt_upd = mysqli_prepare($conn, "UPDATE attendance 
            SET jam_pulang = ?, foto_pulang = ?, lokasi_pulang = ? 
            WHERE id = ?");
        mysqli_stmt_bind_param($stmt_upd, "sssi", $jam_sekarang, $nama_foto, $lokasi_raw, $att_id);

        if (mysqli_stmt_execute($stmt_upd)) {
            header("Location: " . get_index_url() . "?status=sukses");
            exit;
        } else {
            redirect_error("Gagal menyimpan data absen pulang.");
        }
    }
}
