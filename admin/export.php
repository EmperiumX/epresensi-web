<?php
require_once dirname(__DIR__) . '/config/session.php';
require_once dirname(__DIR__) . '/config/database.php';

// 1. Proteksi Autentikasi Pengelola (Wajib Login Admin)
if (!isset($_SESSION['admin']) || $_SESSION['admin'] !== true) {
    http_response_code(403);
    die("<h3>403 Akses Ditolak</h3><p>Halaman ekspor data laporan hanya dapat diakses oleh Admin yang terautentikasi.</p>");
}

// 2. Tangkap & Sanitasi Parameter GET
$tgl_awal       = isset($_GET['tgl_awal']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['tgl_awal']) ? $_GET['tgl_awal'] : date('Y-m-01');
$tgl_akhir      = isset($_GET['tgl_akhir']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['tgl_akhir']) ? $_GET['tgl_akhir'] : date('Y-m-d');
$filter_cabang  = isset($_GET['cabang']) && $_GET['cabang'] !== '' ? (int)$_GET['cabang'] : 0;
$selected_users = isset($_GET['users']) && is_array($_GET['users']) ? array_map('intval', $_GET['users']) : [];

$nama_file = "Laporan_Presensi_" . $tgl_awal . "_sd_" . $tgl_akhir . ".xls";

// 3. Bangun Klausa Where
$sql_cabang_user = "";
$sql_cabang_att  = "";
if ($filter_cabang > 0) {
    $sql_cabang_att = " AND a.cabang_id = $filter_cabang";
}

$sql_user_filter = "";
if (!empty($selected_users)) {
    $ids_clean = implode(',', $selected_users);
    $sql_user_filter = " AND u.id IN ($ids_clean)";
}

// 4. Ambil Tarif Gaji
$q_gaji = mysqli_query($conn, "SELECT nilai FROM settings WHERE nama_setting='gaji_per_menit'");
$gaji_per_menit = 250;
if ($q_gaji && $rg = mysqli_fetch_assoc($q_gaji)) {
    $gaji_per_menit = (int)$rg['nilai'];
}

// 5. Header Download Spreadsheet
header("Content-Type: application/vnd.ms-excel; charset=UTF-8");
header("Content-Disposition: attachment; filename=\"$nama_file\"");
header("Expires: 0");
header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
header("Pragma: public");

// Anti CSV / Formula Injection Helper
function safe_cell($str) {
    $str = (string)$str;
    if (in_array(substr($str, 0, 1), ['=', '+', '-', '@'])) {
        return "'" . $str;
    }
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}

echo "\xEF\xBB\xBF"; // UTF-8 BOM
?>
<!DOCTYPE html>
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <style>
        body { font-family: Calibri, sans-serif; font-size: 11pt; }
        table { border-collapse: collapse; width: 100%; margin-bottom: 25px; }
        th, td { border: 1px solid #999999; padding: 6px 8px; text-align: center; }
        th { background-color: #262626; color: #FFFFFF; font-weight: bold; }
        .text-left { text-align: left; }
        .text-right { text-align: right; }
        .bg-subhead { background-color: #F2F2F2; font-weight: bold; }
        .bg-highlight { background-color: #FFF2CC; font-weight: bold; }
    </style>
</head>
<body>

    <h2 style="text-align: center; margin: 0 0 5px 0;">REKAPITULASI PRESENSI & GAJI PEGAWAI</h2>
    <h3 style="text-align: center; margin: 0 0 5px 0; color: #B45309;">SISTEM PRESENSI MULTI-OUTLET</h3>
    <p style="text-align: center; margin: 0 0 20px 0;">Periode: <b><?= date('d M Y', strtotime($tgl_awal)) ?> s/d <?= date('d M Y', strtotime($tgl_akhir)) ?></b></p>

    <!-- BAGIAN 1: RINGKASAN TOTAL KINERJA & GAJI -->
    <h4 style="margin: 0 0 8px 0;">A. RINGKASAN TOTAL KEHADIRAN & ESTIMASI GAJI</h4>
    <table>
        <thead>
            <tr>
                <th style="width: 40px;">No</th>
                <th class="text-left">Nama Pegawai</th>
                <th>Cabang Homebase</th>
                <th>Hadir (Fulltime)</th>
                <th>Hadir (Parttime)</th>
                <th>Hadir (Lembur)</th>
                <th>Terlambat</th>
                <th>Izin / Sakit</th>
                <th>Total Menit Kerja</th>
                <th class="bg-highlight">ESTIMASI GAJI (Rp <?= $gaji_per_menit ?>/m)</th>
            </tr>
        </thead>
        <tbody>
            <?php
            $q_summary = "SELECT u.id, u.nama, c.nama_cabang,
                            SUM(CASE WHEN a.status_kerja = 'Fulltime' AND a.shift != 'Izin' THEN 1 ELSE 0 END) as hadir_ft,
                            SUM(CASE WHEN a.status_kerja = 'Parttime' AND a.shift != 'Izin' THEN 1 ELSE 0 END) as hadir_pt,
                            SUM(CASE WHEN a.status_kerja = 'Lembur' AND a.shift != 'Izin' THEN 1 ELSE 0 END) as hadir_lb,
                            SUM(CASE WHEN a.shift = 'Izin' THEN 1 ELSE 0 END) as total_izin,
                            SUM(CASE WHEN a.terlambat_menit > 0 THEN 1 ELSE 0 END) as total_telat,
                            SUM(CASE WHEN a.jam_pulang IS NOT NULL AND a.shift != 'Izin' THEN TIMESTAMPDIFF(MINUTE, a.jam_masuk, a.jam_pulang) ELSE 0 END) as tot_menit
                          FROM users u
                          LEFT JOIN cabang c ON u.cabang_id = c.id
                          LEFT JOIN attendance a ON u.id = a.user_id AND (a.tanggal BETWEEN '$tgl_awal' AND '$tgl_akhir') $sql_cabang_att
                          WHERE 1=1 $sql_user_filter
                          GROUP BY u.id
                          ORDER BY u.nama ASC";

            $res_summary = mysqli_query($conn, $q_summary);
            $no_sum = 1;
            $grand_gaji = 0;

            while ($sum = mysqli_fetch_assoc($res_summary)) {
                $menit = (int)$sum['tot_menit'];
                $gaji = $menit * $gaji_per_menit;
                $grand_gaji += $gaji;
            ?>
                <tr>
                    <td><?= $no_sum++ ?></td>
                    <td class="text-left" style="font-weight: bold;"><?= safe_cell($sum['nama']) ?></td>
                    <td><?= safe_cell($sum['nama_cabang'] ?: 'Outlet Erlangga (Pusat)') ?></td>
                    <td><?= (int)$sum['hadir_ft'] ?> Hari</td>
                    <td><?= (int)$sum['hadir_pt'] ?> Hari</td>
                    <td><?= (int)$sum['hadir_lb'] ?> Hari</td>
                    <td style="<?= (int)$sum['total_telat'] > 0 ? 'color: red; font-weight: bold;' : '' ?>">
                        <?= (int)$sum['total_telat'] ?> Kali
                    </td>
                    <td><?= (int)$sum['total_izin'] ?> Hari</td>
                    <td style="font-weight: bold;"><?= $menit ?> Menit</td>
                    <td class="text-right" style="font-weight: bold; color: #1E7E34;">
                        Rp <?= number_format($gaji, 0, ',', '.') ?>
                    </td>
                </tr>
            <?php } ?>
            <tr class="bg-subhead">
                <td colspan="9" class="text-right">TOTAL PENGELUARAN ESTIMASI GAJI:</td>
                <td class="text-right" style="font-size: 1.1em; color: #1E7E34;">
                    Rp <?= number_format($grand_gaji, 0, ',', '.') ?>
                </td>
            </tr>
        </tbody>
    </table>

    <br>

    <!-- BAGIAN 2: LOG DATA PRESENSI HARIAN -->
    <h4 style="margin: 0 0 8px 0;">B. RINCIAN LOG PRESENSI HARIAN</h4>
    <table>
        <thead>
            <tr>
                <th style="width: 40px;">No</th>
                <th>Tanggal</th>
                <th class="text-left">Nama Pegawai</th>
                <th>Cabang Outlet</th>
                <th>Tipe Kehadiran</th>
                <th>Shift / Alasan</th>
                <th>Jam Masuk</th>
                <th>Jam Pulang</th>
                <th>Durasi</th>
                <th>Menit</th>
                <th>Telat (Mnt)</th>
                <th class="bg-highlight">Gaji Harian</th>
            </tr>
        </thead>
        <tbody>
            <?php
            $q_detail = "SELECT a.*, u.nama, c.nama_cabang 
                         FROM attendance a
                         JOIN users u ON a.user_id = u.id 
                         LEFT JOIN cabang c ON a.cabang_id = c.id
                         WHERE (a.tanggal BETWEEN '$tgl_awal' AND '$tgl_akhir') $sql_cabang_att
                         ORDER BY a.tanggal ASC, a.jam_masuk ASC";
            $res_detail = mysqli_query($conn, $q_detail);
            $no_det = 1;

            while ($row = mysqli_fetch_assoc($res_detail)) {
                $durasi_str = "-";
                $menit_harian = 0;
                $gaji_harian = 0;
                $is_iz = ($row['shift'] === 'Izin');

                if ($row['jam_pulang'] && !$is_iz) {
                    $sec = strtotime($row['jam_pulang']) - strtotime($row['jam_masuk']);
                    if ($sec > 0) {
                        $menit_harian = (int)floor($sec / 60);
                        $durasi_str = floor($sec / 3600) . "j " . floor(($sec % 3600) / 60) . "m";
                        $gaji_harian = $menit_harian * $gaji_per_menit;
                    }
                }
            ?>
                <tr>
                    <td><?= $no_det++ ?></td>
                    <td><?= date('d/m/Y', strtotime($row['tanggal'])) ?></td>
                    <td class="text-left" style="font-weight: bold;"><?= safe_cell($row['nama']) ?></td>
                    <td><?= safe_cell($row['nama_cabang'] ?: 'Outlet Erlangga (Pusat)') ?></td>
                    <td><?= safe_cell($row['status_kerja']) ?></td>
                    <td><?= $is_iz ? safe_cell($row['keterangan']) : safe_cell($row['shift']) ?></td>
                    <td><?= $is_iz ? '-' : substr($row['jam_masuk'], 0, 5) ?></td>
                    <td><?= $is_iz ? '-' : ($row['jam_pulang'] ? substr($row['jam_pulang'], 0, 5) : 'Belum') ?></td>
                    <td><?= $durasi_str ?></td>
                    <td><?= $menit_harian > 0 ? $menit_harian : '-' ?></td>
                    <td style="<?= (int)$row['terlambat_menit'] > 0 ? 'color: red; font-weight: bold;' : '' ?>">
                        <?= (int)$row['terlambat_menit'] ?>
                    </td>
                    <td class="text-right" style="color: #1E7E34; font-weight: bold;">
                        <?= $gaji_harian > 0 ? 'Rp ' . number_format($gaji_harian, 0, ',', '.') : '-' ?>
                    </td>
                </tr>
            <?php } ?>
        </tbody>
    </table>

</body>
</html>