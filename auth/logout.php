<?php
// auth/logout.php
// File ini untuk proses logout pengguna.

// Memulai session jika belum dimulai
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Log logout activity before destroying session
if (isset($_SESSION['user_id']) && isset($_SESSION['username'])) {
    require_once __DIR__ . '/../includes/activity_logger.php';
    require_once __DIR__ . '/../config/db.php';
    logActivity($_SESSION['user_id'], $_SESSION['username'], 'logout', 'User ' . $_SESSION['username'] . ' baru saja logout', $db);
}

// Hapus semua session
$_SESSION = array();

// Hapus session cookie
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// HAPUS JWT COOKIE dengan beberapa metode untuk memastikan terhapus
// Metode 1: Hapus dengan path yang sama seperti saat set
setcookie('auth_token', '', [
    'expires' => time() - 3600,
    'path' => '/cornerbites-sia/',
    'httponly' => true,
    'samesite' => 'Lax'
]);

// Metode 2: Hapus dengan path root juga untuk memastikan
setcookie('auth_token', '', [
    'expires' => time() - 3600,
    'path' => '/',
    'httponly' => true,
    'samesite' => 'Lax'
]);

// Metode 3: Hapus tanpa path
setcookie('auth_token', '', time() - 3600);

// Destroy session
session_destroy();

// Redirect ke halaman login setelah logout
header("Location: /cornerbites-sia/auth/login.php");
exit(); // Penting untuk menghentikan eksekusi skrip
?>