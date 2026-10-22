<?php
ob_start();
require_once __DIR__ . '/config/session.php';
require_once __DIR__ . '/config/database.php';

if (!isset($_SESSION['admin']) || $_SESSION['admin'] !== true) {
    header("Location: login.php");
    exit;
}

// Redirect ke dasbor admin karena modal ubah password sudah terintegrasi rapi di dasbor utama
header("Location: admin/index.php");
exit;