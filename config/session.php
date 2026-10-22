<?php
// =======================================================================
// KONFIGURASI SESI AMAN & CSRF GUARD - E-PRESENSI
// Lokasi: config/session.php
// =======================================================================

if (session_status() === PHP_SESSION_NONE) {
    // 1. Tentukan direktori penyimpanan sesi privat (di luar webroot)
    $system_temp = sys_get_temp_dir();
    $secure_session_path = $system_temp . '/epresensi_sessions';
    if (!file_exists($secure_session_path)) {
        @mkdir($secure_session_path, 0700, true);
    }
    if (is_writable($secure_session_path)) {
        session_save_path($secure_session_path);
    }

    // 2. Cookie Hardening (HttpOnly, SameSite Lax, Secure jika HTTPS)
    $is_https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') 
                || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443)
                || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');

    session_set_cookie_params([
        'lifetime' => 86400 * 3, // 3 Hari
        'path'     => '/',
        'domain'   => '',
        'secure'   => $is_https,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);

    session_start();
}

// =======================================================================
// CSRF TOKEN HELPERS
// =======================================================================
if (!function_exists('csrf_token')) {
    function csrf_token() {
        if (empty($_SESSION['_csrf_token'])) {
            $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['_csrf_token'];
    }
}

if (!function_exists('csrf_field')) {
    function csrf_field() {
        return '<input type="hidden" name="_token" value="' . htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') . '">';
    }
}

if (!function_exists('verify_csrf_token')) {
    function verify_csrf_token($token = null) {
        if ($token === null) {
            $token = isset($_POST['_token']) ? $_POST['_token'] : (isset($_SERVER['HTTP_X_CSRF_TOKEN']) ? $_SERVER['HTTP_X_CSRF_TOKEN'] : '');
        }
        $valid = !empty($_SESSION['_csrf_token']) && hash_equals($_SESSION['_csrf_token'], (string)$token);
        return $valid;
    }
}
