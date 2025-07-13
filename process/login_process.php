<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth_config.php';
require_once __DIR__ . '/../includes/activity_logger.php';

// Start secure session
secureSessionStart();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: /cornerbites-sia/auth/login.php");
    exit();
}

// Validate CSRF token
$csrf_token = $_POST['csrf_token'] ?? '';
if (!verifyCSRFToken($csrf_token)) {
    $_SESSION['login_error'] = 'Token keamanan tidak valid. Silakan coba lagi.';
    header("Location: /cornerbites-sia/auth/login.php");
    exit();
}

$username = trim($_POST['username'] ?? '');
$password = $_POST['password'] ?? '';

// Basic validation
if (empty($username) || empty($password)) {
    $_SESSION['login_error'] = 'Username dan password tidak boleh kosong.';
    header("Location: /cornerbites-sia/auth/login.php");
    exit();
}

// Check rate limiting
$client_identifier = $_SERVER['REMOTE_ADDR'] . '_' . $username;
if (!checkLoginAttempts($client_identifier)) {
    $_SESSION['login_error'] = 'Terlalu banyak percobaan login. Silakan coba lagi dalam 5 menit.';
    header("Location: /cornerbites-sia/auth/login.php");
    exit();
}

try {
    // Get user from database
    $stmt = $db->prepare("SELECT id, username, password, role, must_change_password FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password'])) {
        // Record failed attempt
        recordLoginAttempt($client_identifier, false);
        logActivity(null, $username, 'LOGIN_FAILED', "Failed login attempt for username: $username");

        $_SESSION['login_error'] = 'Username atau password salah.';
        header("Location: /cornerbites-sia/auth/login.php");
        exit();
    }

    // Successful login - clear failed attempts
    recordLoginAttempt($client_identifier, true);

    // Generate JWT token
    $jwt_token = generateJWT($user['id'], $user['username'], $user['role']);

    // Set session data
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['user_role'] = $user['role'];
    $_SESSION['auth_token'] = $jwt_token;
    $_SESSION['login_time'] = time();

    // HANYA SIMPAN JWT TOKEN KE COOKIES - BUKAN DATA TERPISAH
    setcookie('auth_token', $jwt_token, [
        'expires' => time() + JWT_EXPIRY_TIME,
        'path' => '/cornerbites-sia/',
        'domain' => '',
        'secure' => false, // Set true jika menggunakan HTTPS
        'httponly' => true, // Mencegah akses via JavaScript
        'samesite' => 'Lax'
    ]);

    // Log successful login
    logActivity($user['id'], $user['username'], 'LOGIN_SUCCESS', "User {$user['username']} logged in successfully");

    // Check if password change is required
    if ($user['must_change_password'] == 1) {
        $_SESSION['must_change_password'] = true;
        $_SESSION['force_password_change'] = true;
        $_SESSION['password_change_token'] = bin2hex(random_bytes(32));
        header("Location: /cornerbites-sia/auth/change_password.php");
        exit();
    }

    // Redirect based on role
    if ($user['role'] === 'admin') {
        header("Location: /cornerbites-sia/admin/dashboard.php");
    } else {
        header("Location: /cornerbites-sia/pages/dashboard.php");
    }
    exit();

} catch (Exception $e) {
    error_log("Login error: " . $e->getMessage());
    $_SESSION['login_error'] = 'Terjadi kesalahan sistem. Silakan coba lagi.';
    header("Location: /cornerbites-sia/auth/login.php");
    exit();
}
?>