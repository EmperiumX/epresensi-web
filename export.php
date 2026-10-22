<?php
// =======================================================================
// BACKWARD COMPATIBILITY REDIRECT
// File ini mengarahkan ke handler export baru di admin/export.php
// =======================================================================
$query = !empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : '';
header("Location: admin/export.php" . $query);
exit;