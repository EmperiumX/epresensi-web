-- Skema Database Sistem E-Presensi Pegawai

CREATE TABLE IF NOT EXISTS `cabang` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `nama_cabang` VARCHAR(120) NOT NULL,
  `alamat` TEXT NOT NULL,
  `latitude` DECIMAL(10, 8) NOT NULL,
  `longitude` DECIMAL(11, 8) NOT NULL,
  `radius_meter` INT NOT NULL DEFAULT 100,
  `embed_maps` TEXT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `users` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `nama` VARCHAR(100) NOT NULL,
  `jabatan` VARCHAR(100) NOT NULL,
  `status_kerja` ENUM('Fulltime', 'Parttime', 'Lembur') NOT NULL DEFAULT 'Fulltime',
  `pin` VARCHAR(255) NOT NULL DEFAULT '1234',
  `cabang_id` INT NULL,
  `akses_admin` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_users_cabang` (`cabang_id`),
  INDEX `idx_users_nama` (`nama`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `shift_settings` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `kode_shift` VARCHAR(20) NOT NULL UNIQUE,
  `nama_shift` VARCHAR(100) NOT NULL,
  `jam_masuk` TIME NOT NULL,
  `jam_pulang` TIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `settings` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `nama_setting` VARCHAR(50) NOT NULL UNIQUE,
  `nilai` VARCHAR(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `admin` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `username` VARCHAR(50) NOT NULL UNIQUE,
  `password` VARCHAR(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `attendance` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `cabang_id` INT NULL,
  `tanggal` DATE NOT NULL,
  `jam_masuk` TIME NOT NULL,
  `jam_pulang` TIME NULL,
  `foto_masuk` VARCHAR(255) NULL,
  `foto_pulang` VARCHAR(255) NULL,
  `lokasi_masuk` VARCHAR(100) NULL,
  `lokasi_pulang` VARCHAR(100) NULL,
  `shift` VARCHAR(100) NOT NULL,
  `keterangan` TEXT NULL,
  `status_kerja` VARCHAR(50) NOT NULL,
  `terlambat_menit` INT NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_att_user_date` (`user_id`, `tanggal`),
  INDEX `idx_att_date` (`tanggal`),
  INDEX `idx_att_cabang` (`cabang_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- SEED DATA CABANG OUTLET
INSERT INTO `cabang` (`id`, `nama_cabang`, `alamat`, `latitude`, `longitude`, `radius_meter`, `embed_maps`, `is_active`) VALUES
(1, 'Outlet 1 (Pusat)', 'Jl. Outlet Pusat No. 1', -6.9931522, 110.4202356, 100, '', 1),
(2, 'Outlet 2 (Cabang)', 'Jl. Outlet Cabang No. 2', -7.0372657, 110.4552741, 100, '', 1)
ON DUPLICATE KEY UPDATE `nama_cabang`=VALUES(`nama_cabang`), `latitude`=VALUES(`latitude`), `longitude`=VALUES(`longitude`);

-- SEED SHIFT SETTINGS (1 Shift Operasional Tunggal: 14:00 - 21:30)
INSERT INTO `shift_settings` (`id`, `kode_shift`, `nama_shift`, `jam_masuk`, `jam_pulang`) VALUES
(1, 'S1', 'Shift Operasional', '14:00:00', '21:30:00')
ON DUPLICATE KEY UPDATE `nama_shift`=VALUES(`nama_shift`), `jam_masuk`=VALUES(`jam_masuk`), `jam_pulang`=VALUES(`jam_pulang`);

-- SEED SETTINGS
INSERT INTO `settings` (`nama_setting`, `nilai`) VALUES
('gaji_per_menit', '250')
ON DUPLICATE KEY UPDATE `nilai`=VALUES(`nilai`);

-- SEED ADMIN (Password default: 'admin123' di-hash dengan bcrypt, atau fallback cek plain)
INSERT INTO `admin` (`id`, `username`, `password`) VALUES
(1, 'admin', '$2y$10$wEkgk5F/mNlYm8zZc3jMWeF6fK.7lq97tWnU9.p9s1qHw7s8v3o5G')
ON DUPLICATE KEY UPDATE `username`=VALUES(`username`);

-- SEED SAMPLE EMPLOYEES (Jika belum ada)
INSERT IGNORE INTO `users` (`id`, `nama`, `jabatan`, `status_kerja`, `pin`, `cabang_id`, `akses_admin`) VALUES
(1, 'Ahmad Fauzi', 'Staff Operasional', 'Fulltime', '1234', 1, 0),
(2, 'Siti Nurhaliza', 'Kasir & Pelayanan', 'Fulltime', '1234', 1, 0),
(3, 'Budi Santoso', 'Crew Outlet', 'Parttime', '1234', 2, 0),
(4, 'Dewi Lestari', 'Barista & Kasir', 'Fulltime', '1234', 2, 0),
(5, 'Zidan Pramudya', 'Store Manager', 'Fulltime', '1234', 1, 1);
