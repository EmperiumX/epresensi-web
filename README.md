# Sistem E-Presensi & Manajemen Kehadiran Pegawai Multi-Cabang

Aplikasi web presensi pegawai modern berbasis **Mobile-First** dengan verifikasi **Kamera Selfie (HTML5 WebP Compression)**, validasi **PIN Keamanan**, dukungan multi-cabang outlet, perhitungan menit kerja & keterlambatan presisi, serta dasbor pengelola dengan rekapitulasi data kehadiran dan estimasi gaji.

---

## 🌟 Fitur Utama

### 1. Multi-Cabang & Verifikasi Kehadiran Fleksibel
- **Dukungan Multi-Outlet:** Mendukung banyak cabang operasional dengan pengaturan koordinat dan radius yang dapat dikonfigurasi.
- **Validasi Cepat (Foto Selfie & PIN):** Sistem saat ini dioptimalkan untuk absensi mandiri yang cepat menggunakan foto selfie langsung dari peramban dan PIN keamanan pegawai tanpa kendala sinyal GPS.
- **Dukungan Geofencing GPS Modular:** Modul deteksi lokasi (formula Haversine) siap diaktifkan kembali sesuai kebutuhan operasional.

### 2. Kamera HTML5 & Kompresi WebP
- Menggunakan native HTML5 `navigator.mediaDevices.getUserMedia` dengan panduan bingkai wajah (*circular selfie mask*).
- Fitur rotasi kamera (depan/belakang).
- Kompresi canvas client-side ke format **WebP (Quality 0.82)** sebelum pengiriman, mereduksi ukuran file hingga **95%** (~70 KB per foto).

### 3. Presisi Jam Kerja & Shift
- Perhitungan menit keterlambatan akurat tanpa pemotongan digit menit.
- Dukungan untuk shift malam yang melewati tengah malam (*overnight shift*).
- Tipe kehadiran: **Fulltime**, **Parttime**, dan **Lembur**.

### 4. Keamanan Tingkat Lanjut (Security Hardened)
- **Zero SQL Injection:** Seluruh interaksi database menggunakan *Prepared Statements* (`mysqli_prepare`).
- **Enkripsi Kredensial:** Password admin dan PIN pegawai dienkripsi menggunakan algoritma **Bcrypt** (`password_hash`).
- **Proteksi CSRF:** Seluruh form dilindungi token sesi anti pemalsuan permintaan antar-situs.
- **Isolasi Sesi Privat:** File sesi dipindahkan dari webroot publik ke direktori privat sistem dengan flag cookie `HttpOnly` dan `SameSite=Lax`.
- **Proteksi Berkas Unggahan:** Direktori upload dilindungi konfigurasi webserver yang melarang eksekusi script PHP.
- **Autentikasi Ekspor Data:** Endpoint ekspor laporan spreadsheet diwajibkan otentikasi pengelola dan diproteksi dari *CSV/Formula Injection*.

### 5. Dasbor Pengelola (Admin Panel)
- **Desain Minimalis & Tabular Data:** Tampilan bersih, kontras tinggi, angka tabular, dan bebas dari distorsi visual.
- **Optimalisasi Performa:** Menghilangkan beban query berulang (N+1 query problem) dengan sistem pre-agregasi data.
- **Penyaringan Laporan:** Saring data kehadiran berdasarkan rentang tanggal, cabang outlet, atau pegawai tertentu.
- **Modal Peta Cabang:** Dilengkapi pratinjau embed Google Maps interaktif untuk masing-masing outlet.
- **Ekspor Excel:** Menghasilkan rekapitulasi kehadiran dan rincian gaji harian lengkap dengan identitas cabang.

---

## 🛠️ Tech Stack

- **Backend:** PHP 8.x (Native Procedural Modern & Clean Architecture)
- **Database:** MySQL / MariaDB (InnoDB, Charset utf8mb4)
- **Frontend:** HTML5, Vanilla JavaScript (ES6+), Bootstrap 5.3, DataTables, SweetAlert2
- **Desain & UI:** Minimalist Utilitarian & Double-Bezel Architecture
- **Peta & Lokasi:** Google Maps Embed API & Geolocation API (Haversine Formula)

---

## 🚀 Panduan Instalasi & Menjalankan Lokal

### 1. Kebutuhan Sistem
- PHP 8.0 atau versi lebih baru (dengan ekstensi `mysqli`, `session`, `gd` / `fileinfo`).
- MySQL 5.7+ atau MariaDB 10.3+.
- Web server Apache / Nginx atau PHP Built-in Server.

### 2. Salin Konfigurasi Environment
Salin berkas template konfigurasi:
```bash
cp .env.example .env
```
Sesuaikan kredensial basis data pada berkas `.env`:
```env
DB_HOST=127.0.0.1
DB_PORT=3306
DB_USER=root
DB_PASS=root
DB_NAME=db_absen
APP_ENV=local
```

### 3. Inisialisasi Basis Data
Impor skema basis data beserta data cabang awal:
```bash
mysql -u root -p db_absen < database/schema.sql
```

### 4. Jalankan Server Pengembangan
Jalankan server bawaan PHP:
```bash
php -S 127.0.0.1:8080
```
Buka peramban di `http://127.0.0.1:8080`:
- **Portal Presensi Pegawai:** `http://127.0.0.1:8080/index.php`
- **Dasbor Pengelola (Admin):** `http://127.0.0.1:8080/login.php`
  - *Username default:* `admin`
  - *Password default:* `admin123`

---

## 📁 Struktur Direktori

```
├── .env.example              # Template konfigurasi environment
├── .htaccess                 # Aturan keamanan Apache & blokade file sensitif
├── .gitignore                # Daftar berkas yang dikecualikan dari repositori
├── index.php                 # Portal presensi mandiri pegawai (Mobile-First)
├── login.php                 # Autentikasi pengelola dengan proteksi rate limit
├── riwayat.php               # Portal mandiri riwayat & jam kerja pegawai
├── profil.php                # Pengarah akun pengelola
│
├── admin/                    # Modul Dasbor & Manajemen Pengelola
│   ├── index.php             # Dasbor utama rekapitulasi presensi & filter cabang
│   ├── karyawan.php          # Manajemen data pegawai & penugasan cabang
│   ├── setting_jam.php       # Konfigurasi jadwal shift & jam kerja
│   ├── akses.php             # Manajemen hak akses admin staf cabang
│   └── export.php            # Ekspor laporan Excel terproteksi otentikasi
│
├── assets/                   # Aset Statis Publik
│   └── img/
│       └── logo.png          # Lambang visual aplikasi presensi
│
├── config/                   # Konfigurasi Sistem & Basis Data Terproteksi
│   ├── .htaccess             # Proteksi blokir akses langsung HTTP
│   ├── database.php          # Koneksi DB, .env loader, auto-migrasi, & Haversine
│   └── session.php           # Konfigurasi sesi privat & helper proteksi CSRF
│
├── database/                 # Berkas Skema Basis Data
│   ├── .htaccess             # Proteksi blokir unduhan berkas SQL
│   └── schema.sql            # DDL skema tabel & seed data awal cabang
│
├── handlers/                 # Pemroses Logika Backend
│   ├── proses_absen.php      # Pemrosesan presensi, foto wajah, & verifikasi
│   └── logout.php            # Pembersihan sesi terverifikasi
│
├── uploads/                  # Direktori penyimpanan foto bukti presensi (WebP)
│   ├── .htaccess             # Proteksi eksekusi skrip di folder upload
│   └── .gitkeep
│
└── [admin|proses|export|...].php  # Shims backward-compatibility transparan
```

---

## 🔒 Catatan Keamanan Produksi

1. Pastikan berkas `.env` memiliki izin baca terbatas (`chmod 600 .env`) dan tidak diunggah ke repositori publik.
2. Saat deployment ke server cPanel / VPS, pastikan SSL (HTTPS) aktif agar peramban ponsel dapat mengizinkan akses kamera dan fitur perangkat secara aman.
3. Segera perbarui kata sandi admin default pada panel dasbor setelah instalasi pertama kali.

