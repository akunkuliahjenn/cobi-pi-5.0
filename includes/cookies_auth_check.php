
<?php
// includes/cookie_auth_check.php
// Helper untuk mengecek JWT dari cookies browser

require_once __DIR__ . '/../config/auth_config.php';

/**
 * Cek apakah user sudah login berdasarkan JWT di cookies
 */
function isLoggedInViaCookie() {
    if (!isset($_COOKIE['auth_token'])) {
        return false;
    }
    
    $jwt_token = $_COOKIE['auth_token'];
    $payload = verifyJWT($jwt_token);
    
    if (!$payload) {
        return false;
    }
    
    // JWT token valid - semua data user sudah ter-encode di dalamnya
    return $payload;
}

/**
 * Ambil data user dari cookies (decode dari JWT)
 */
function getUserDataFromCookie() {
    $payload = isLoggedInViaCookie();
    
    if (!$payload) {
        return null;
    }
    
    return [
        'user_id' => $payload['user_id'],
        'username' => $payload['username'],
        'role' => $payload['role'],
        'issued_at' => $payload['iat'] ?? null,
        'expires_at' => $payload['exp'] ?? null,
        'token_id' => $payload['jti'] ?? null
    ];
}

/**
 * Cek apakah JWT token masih valid
 */
function isTokenValid() {
    if (!isset($_COOKIE['auth_token'])) {
        return false;
    }
    
    $payload = verifyJWT($_COOKIE['auth_token']);
    return $payload !== false;
}

/**
 * Redirect ke login jika tidak ada JWT token valid
 */
function requireValidJWTToken() {
    if (!isTokenValid()) {
        // Hapus cookie yang invalid
        if (isset($_COOKIE['auth_token'])) {
            setcookie('auth_token', '', [
                'expires' => time() - 3600,
                'path' => '/cornerbites-sia/',
                'httponly' => true
            ]);
        }
        
        header("Location: /cornerbites-sia/auth/login.php?session_expired=1");
        exit();
    }
}

/**
 * Sync JWT cookie data ke session (jika diperlukan)
 */
function syncJWTToSession() {
    $userData = getUserDataFromCookie();
    
    if ($userData) {
        $_SESSION['user_id'] = $userData['user_id'];
        $_SESSION['username'] = $userData['username'];
        $_SESSION['user_role'] = $userData['role'];
        $_SESSION['auth_token'] = $_COOKIE['auth_token'];
        return true;
    }
    
    return false;
}
?>
