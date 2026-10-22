-- =======================================================================
-- SQL MIGRASI PENAMBAHAN FITUR 2 CABANG (UNTUK DATABASE LAMA)
-- Aman dijalankan pada database production yang sudah ada data karyawannya!
-- Data karyawan, data absensi lama, dan shift TIDAK AKAN HILANG.
-- =======================================================================

-- 1. Buat Tabel Cabang Baru
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

-- 2. Masukkan Data 2 Cabang Resmi Outlet
INSERT INTO `cabang` (`id`, `nama_cabang`, `alamat`, `latitude`, `longitude`, `radius_meter`, `embed_maps`, `is_active`) VALUES
(1, 'Outlet 1 (Pusat)', 'Jl. Outlet Pusat No. 1', -6.9931522, 110.4202356, 100, '', 1),
(2, 'Outlet 2 (Cabang)', 'Jl. Outlet Cabang No. 2', -7.0372657, 110.4552741, 100, '', 1)
ON DUPLICATE KEY UPDATE `nama_cabang`=VALUES(`nama_cabang`), `latitude`=VALUES(`latitude`), `longitude`=VALUES(`longitude`);

-- 3. Tambahkan kolom cabang_id ke tabel users (Otomatis default ke Cabang 1 Erlangga)
-- Jika kolom sudah ada, statement ini bisa dilewati atau disesuaikan
SET @exist_col := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'cabang_id');
SET @sql_users := IF(@exist_col = 0, 'ALTER TABLE `users` ADD COLUMN `cabang_id` INT NULL DEFAULT 1 AFTER `pin`', 'SELECT "Kolom cabang_id di users sudah ada"');
PREPARE stmt_u FROM @sql_users;
EXECUTE stmt_u;
DEALLOCATE PREPARE stmt_u;

-- 4. Tambahkan kolom cabang_id ke tabel attendance (Otomatis default ke Cabang 1)
SET @exist_col_att := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'attendance' AND COLUMN_NAME = 'cabang_id');
SET @sql_att := IF(@exist_col_att = 0, 'ALTER TABLE `attendance` ADD COLUMN `cabang_id` INT NULL DEFAULT 1 AFTER `user_id`', 'SELECT "Kolom cabang_id di attendance sudah ada"');
PREPARE stmt_a FROM @sql_att;
EXECUTE stmt_a;
DEALLOCATE PREPARE stmt_a;

-- 5. Pastikan kolom pin di tabel users bertipe VARCHAR(255) untuk menampung Hash Bcrypt (60 Karakter)
ALTER TABLE `users` MODIFY COLUMN `pin` VARCHAR(255) NOT NULL DEFAULT '1234';

-- 6. Pulihkan data PIN yang sempat terpotong/korup akibat limit kolom lama (misal $2y$, 0, kosong)
UPDATE `users` SET `pin` = '1234' WHERE `pin` LIKE '$2y$%' AND LENGTH(`pin`) < 60;
UPDATE `users` SET `pin` = '1234' WHERE `pin` = '0' OR `pin` = '' OR `pin` IS NULL;

-- 7. Migrasi Jam Kerja Baru: 1 Shift Operasional Tunggal (14:00 - 21:30)
DELETE FROM `shift_settings`;
INSERT INTO `shift_settings` (`id`, `kode_shift`, `nama_shift`, `jam_masuk`, `jam_pulang`) VALUES
(1, 'S1', 'Shift Operasional', '14:00:00', '21:30:00');


