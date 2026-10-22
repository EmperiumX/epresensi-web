<?php
// =======================================================================
// HANDLER LOGOUT PENGELOLA - E-PRESENSI
// Lokasi: handlers/logout.php
// =======================================================================
require_once dirname(__DIR__) . '/config/session.php';

// Bersihkan variabel sesi
$_SESSION = [];

// Hapus cookie sesi dari browser
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );
}

// Hancurkan sesi di server
session_destroy();

// Redirect ke halaman login yang valid
header("Location: ../login.php");
exit;
