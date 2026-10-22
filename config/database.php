<?php
// =======================================================================
// KONEKSI DATABASE & ENVIRONMENT LOADER - E-PRESENSI
// Lokasi: config/database.php
// =======================================================================

// 1. Baca berkas .env dari root direktori proyek
$env_file = dirname(__DIR__) . '/.env';
if (file_exists($env_file)) {
    $lines = file($env_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || strpos($line, '#') === 0) continue;
        if (strpos($line, '=') !== false) {
            list($key, $val) = explode('=', $line, 2);
            $key = trim($key);
            $val = trim($val, " \t\n\r\0\x0B\"'");
            if (!isset($_SERVER[$key]) && !isset($_ENV[$key])) {
                putenv("$key=$val");
                $_ENV[$key] = $val;
                $_SERVER[$key] = $val;
            }
        }
    }
}

// 2. Tentukan Kredensial Database (Prioritas: ENV -> Default Local -> Fallback cPanel)
$host = getenv('DB_HOST') ?: '127.0.0.1';
$user = getenv('DB_USER') ?: 'root';
$pass = getenv('DB_PASS') !== false ? getenv('DB_PASS') : 'root';
$db   = getenv('DB_NAME') ?: 'db_absen';

// Koneksi ke Database
mysqli_report(MYSQLI_REPORT_OFF);
$conn = @mysqli_connect($host, $user, $pass, $db);

// Fallback otomatis ke kredensial cPanel jika local gagal
if (!$conn) {
    $fallback_host = 'localhost';
    $fallback_user = 'neww1518_admin';
    $fallback_pass = 'AbsenSukses2026!';
    $fallback_db   = 'neww1518_absen';
    
    $conn = @mysqli_connect($fallback_host, $fallback_user, $fallback_pass, $fallback_db);
}

if (!$conn) {
    http_response_code(500);
    die("<h3>Galat Koneksi Basis Data</h3><p>Sistem tidak dapat terhubung ke database. Silakan periksa konfigurasi pada file <code>.env</code> atau hubungi tim IT.</p>");
}

mysqli_set_charset($conn, "utf8mb4");
date_default_timezone_set('Asia/Jakarta');

// =======================================================================
// AUTO-MIGRATION PENAMBAHAN CABANG & KOLOM BARU (SAFE & IDEMPOTENT)
// =======================================================================
function jalankan_migrasi_otomatis($conn) {
    // 1. Buat Tabel Cabang jika belum ada
    $check_cabang = mysqli_query($conn, "SHOW TABLES LIKE 'cabang'");
    if (mysqli_num_rows($check_cabang) == 0) {
        $sql_cabang = "CREATE TABLE `cabang` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `nama_cabang` VARCHAR(120) NOT NULL,
            `alamat` TEXT NOT NULL,
            `latitude` DECIMAL(10, 8) NOT NULL,
            `longitude` DECIMAL(11, 8) NOT NULL,
            `radius_meter` INT NOT NULL DEFAULT 100,
            `embed_maps` TEXT NULL,
            `is_active` TINYINT(1) NOT NULL DEFAULT 1,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
        mysqli_query($conn, $sql_cabang);

        // Seed 2 Cabang Resmi Outlet
        mysqli_query($conn, "INSERT INTO `cabang` (`id`, `nama_cabang`, `alamat`, `latitude`, `longitude`, `radius_meter`, `embed_maps`, `is_active`) VALUES
            (1, 'Outlet 1 (Pusat)', 'Jl. Outlet Pusat No. 1', -6.9931522, 110.4202356, 100, '', 1),
            (2, 'Outlet 2 (Cabang)', 'Jl. Outlet Cabang No. 2', -7.0372657, 110.4552741, 100, '', 1)");
    }

    // 2. Tambah kolom cabang_id di users jika belum ada
    $check_u_cab = mysqli_query($conn, "SHOW COLUMNS FROM `users` LIKE 'cabang_id'");
    if (mysqli_num_rows($check_u_cab) == 0) {
        mysqli_query($conn, "ALTER TABLE `users` ADD COLUMN `cabang_id` INT NULL DEFAULT 1 AFTER `pin`");
    }

    // 3. Tambah kolom cabang_id di attendance jika belum ada
    $check_a_cab = mysqli_query($conn, "SHOW COLUMNS FROM `attendance` LIKE 'cabang_id'");
    if (mysqli_num_rows($check_a_cab) == 0) {
        mysqli_query($conn, "ALTER TABLE `attendance` ADD COLUMN `cabang_id` INT NULL DEFAULT 1 AFTER `user_id`");
    }

    // 4. Pastikan kolom pin di users bertipe VARCHAR(255) untuk menampung Hash Bcrypt (60 Karakter)
    $check_pin = mysqli_query($conn, "SHOW COLUMNS FROM `users` LIKE 'pin'");
    if ($col_pin = mysqli_fetch_assoc($check_pin)) {
        $type_pin = strtolower($col_pin['Type'] ?? '');
        // Jika bertipe INT atau varchar lebih pendek dari 60 (misal varchar(4), varchar(6), varchar(10))
        if (strpos($type_pin, 'int') !== false || (preg_match('/varchar\((\d+)\)/', $type_pin, $m) && (int)$m[1] < 60)) {
            @mysqli_query($conn, "ALTER TABLE `users` MODIFY COLUMN `pin` VARCHAR(255) NOT NULL DEFAULT '1234'");
        }
    }

    // 5. Otomatis perbaiki data PIN yang korup/terpotong ($2y$, 0, atau string tidak valid akibat kolom lama)
    @mysqli_query($conn, "UPDATE `users` SET `pin` = '1234' WHERE `pin` LIKE '$2y$%' AND LENGTH(`pin`) < 60");
    @mysqli_query($conn, "UPDATE `users` SET `pin` = '1234' WHERE `pin` = '0' OR `pin` = '' OR `pin` IS NULL");

    // 6. Migrasi Jam Kerja Baru: 1 Shift Operasional Tunggal (14:00 - 21:30)
    $check_new_shift = mysqli_query($conn, "SELECT COUNT(*) as jml FROM shift_settings WHERE jam_masuk = '14:00:00' AND jam_pulang = '21:30:00'");
    $sh_row = mysqli_fetch_assoc($check_new_shift);
    if ((int)($sh_row['jml'] ?? 0) === 0) {
        @mysqli_query($conn, "DELETE FROM shift_settings");
        @mysqli_query($conn, "INSERT INTO shift_settings (id, kode_shift, nama_shift, jam_masuk, jam_pulang) VALUES 
            (1, 'S1', 'Shift Operasional', '14:00:00', '21:30:00')");
    }
}
jalankan_migrasi_otomatis($conn);

// =======================================================================
// FUNGSI UTILITAS GLOBAL (KEAMANAN & GEOLOKASI)
// =======================================================================
if (!function_exists('e')) {
    function e($val) {
        return htmlspecialchars((string)$val, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('hitung_jarak_meter')) {
    /**
     * Menghitung jarak antara 2 titik koordinat (dalam satuan METER)
     * Menggunakan rumus Haversine
     */
    function hitung_jarak_meter($lat1, $lon1, $lat2, $lon2) {
        $earth_radius = 6371000; // Radius bumi dalam meter
        $dLat = deg2rad((float)$lat2 - (float)$lat1);
        $dLon = deg2rad((float)$lon2 - (float)$lon1);
        $a = sin($dLat / 2) * sin($dLat / 2) +
             cos(deg2rad((float)$lat1)) * cos(deg2rad((float)$lat2)) *
             sin($dLon / 2) * sin($dLon / 2);
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
        return round($earth_radius * $c, 2);
    }
}
