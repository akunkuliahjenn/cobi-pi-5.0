<?php
// includes/password_change_middleware.php
// Middleware super strict untuk memaksa user ganti password

function enforcePasswordChangeMiddleware() {
    global $db;

    // Skip jika di halaman change_password.php, logout, atau login
    $current_page = $_SERVER['PHP_SELF'];
    $skip_pages = [
        'change_password.php',
        'logout.php',
        'login.php',
        'register.php'
    ];

    $should_skip = false;
    foreach ($skip_pages as $skip_page) {
        if (strpos($current_page, $skip_page) !== false) {
            $should_skip = true;
            break;
        }
    }

    if ($should_skip) {
        return;
    }

    // Pastikan user sudah login
    if (!isset($_SESSION['user_id'])) {
        return;
    }

    // Cek database untuk memastikan status terkini
    try {
        $stmt = $db->prepare("SELECT must_change_password, username FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch();

        // Jika user harus ganti password, paksa redirect
        if ($user && $user['must_change_password'] == 1) {
            // Set session flags minimal
            $_SESSION['must_change_password'] = true;
            $_SESSION['force_password_change'] = true;

            // Generate token jika belum ada
            if (!isset($_SESSION['password_change_token'])) {
                $_SESSION['password_change_token'] = bin2hex(random_bytes(32));
            }

            // Log attempted access
            error_log("Password change required - User {$user['username']} attempted to access {$current_page}");

            // Simple redirect without interference
            if (!headers_sent()) {
                header("Location: /cornerbites-sia/auth/change_password.php");
                exit();
            }

            // JavaScript redirect sebagai backup jika headers sudah sent
            echo "<script>window.location.href='/cornerbites-sia/auth/change_password.php';</script>";
            exit();
        }

        // Clear flags jika password tidak perlu diganti
        if (isset($_SESSION['must_change_password'])) {
            unset($_SESSION['must_change_password']);
            unset($_SESSION['force_password_change']);
            unset($_SESSION['password_change_token']);
            unset($_SESSION['password_change_required_timestamp']);
        }

    } catch (PDOException $e) {
        // Log error dan redirect ke login jika ada masalah database
        error_log("Password change middleware error: " . $e->getMessage());
        $_SESSION['error_message'] = 'Terjadi kesalahan sistem. Silakan login ulang.';
        header("Location: /cornerbites-sia/auth/login.php");
        exit();
    }
}

// Auto-apply middleware jika file ini di-include
if (session_status() == PHP_SESSION_ACTIVE && isset($_SESSION['user_id'])) {
    enforcePasswordChangeMiddleware();
}
?>