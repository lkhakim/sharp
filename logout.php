<?php
/**
 * SHARP - Logout Handler
 * Menghapus session dan redirect ke halaman login.
 */

require_once 'config.php';
session_start();

// 1. Ambil data session sebelum dihapus untuk pencatatan log (opsional)
$user_id = $_SESSION['user_id'] ?? null;
$username = $_SESSION['nama'] ?? 'Unknown';

// 2. Catat log aktivitas logout jika user_id tersedia
if ($user_id && isset($db)) {
    // Fungsi ini memanggil fungsi global yang ada di config.php
    catatLogAktivitas($db, $user_id, $username, 'Autentikasi', 'User melakukan logout dari sistem');
}

// 3. Hapus semua data session
$_SESSION = array();

// 4. Jika menggunakan cookie session, hapus juga cookienya
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// 5. Hancurkan session secara total
session_destroy();

// 6. Redirect kembali ke halaman login
header("Location: login.php");
exit;
?>