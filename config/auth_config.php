<?php
// config/auth_config.php
// Konfigurasi keamanan untuk sistem autentikasi

// JWT Secret Key - GANTI INI DI PRODUCTION!
define('JWT_SECRET_KEY', 'your-super-secret-key-change-this-in-production-' . hash('sha256', 'cornerbites-sia'));

// Token expiry time (dalam detik)
define('JWT_EXPIRY_TIME', 7200); // 2 jam

// Session security settings
define('SESSION_LIFETIME', 7200); // 2 jam
define('SESSION_REGENERATE_INTERVAL', 300); // 5 menit

// Rate limiting settings
define('LOGIN_ATTEMPT_LIMIT', 5);
define('LOGIN_LOCKOUT_TIME', 300); // 5 menit

// CSRF Token settings
define('CSRF_TOKEN_EXPIRY', 3600); // 1 jam

/**
 * Generate JWT Token
 */
function generateJWT($user_id, $username, $role) {
    $header = json_encode(['typ' => 'JWT', 'alg' => 'HS256']);
    $payload = json_encode([
        'user_id' => $user_id,
        'username' => $username,
        'role' => $role,
        'iat' => time(),
        'exp' => time() + JWT_EXPIRY_TIME,
        'jti' => bin2hex(random_bytes(16)) // Unique token ID
    ]);
    
    $base64Header = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($header));
    $base64Payload = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($payload));
    
    $signature = hash_hmac('sha256', $base64Header . "." . $base64Payload, JWT_SECRET_KEY, true);
    $base64Signature = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($signature));
    
    return $base64Header . "." . $base64Payload . "." . $base64Signature;
}

/**
 * Verify JWT Token
 */
function verifyJWT($token) {
    $tokenParts = explode('.', $token);
    if (count($tokenParts) != 3) {
        return false;
    }
    
    $header = base64_decode(str_replace(['-', '_'], ['+', '/'], $tokenParts[0]));
    $payload = base64_decode(str_replace(['-', '_'], ['+', '/'], $tokenParts[1]));
    $signatureProvided = $tokenParts[2];
    
    // Verify signature
    $base64Header = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($header));
    $base64Payload = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($payload));
    $signature = hash_hmac('sha256', $base64Header . "." . $base64Payload, JWT_SECRET_KEY, true);
    $base64Signature = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($signature));
    
    if (!hash_equals($base64Signature, $signatureProvided)) {
        return false;
    }
    
    $payload = json_decode($payload, true);
    
    // Check if token is expired
    if (isset($payload['exp']) && $payload['exp'] < time()) {
        return false;
    }
    
    return $payload;
}

/**
 * Generate CSRF Token
 */
function generateCSRFToken() {
    if (!isset($_SESSION['csrf_token']) || !isset($_SESSION['csrf_token_time']) || 
        (time() - $_SESSION['csrf_token_time']) > CSRF_TOKEN_EXPIRY) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        $_SESSION['csrf_token_time'] = time();
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verify CSRF Token
 */
function verifyCSRFToken($token) {
    if (!isset($_SESSION['csrf_token']) || !isset($_SESSION['csrf_token_time'])) {
        return false;
    }
    
    if ((time() - $_SESSION['csrf_token_time']) > CSRF_TOKEN_EXPIRY) {
        return false;
    }
    
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Rate Limiting for Login Attempts
 */
function checkLoginAttempts($identifier) {
    $attempts_key = 'login_attempts_' . $identifier;
    $lockout_key = 'login_lockout_' . $identifier;
    
    // Check if currently locked out
    if (isset($_SESSION[$lockout_key]) && $_SESSION[$lockout_key] > time()) {
        return false;
    }
    
    // Clean up expired lockout
    if (isset($_SESSION[$lockout_key]) && $_SESSION[$lockout_key] <= time()) {
        unset($_SESSION[$lockout_key]);
        unset($_SESSION[$attempts_key]);
    }
    
    return true;
}

function recordLoginAttempt($identifier, $success = false) {
    $attempts_key = 'login_attempts_' . $identifier;
    $lockout_key = 'login_lockout_' . $identifier;
    
    if ($success) {
        // Clear attempts on successful login
        unset($_SESSION[$attempts_key]);
        unset($_SESSION[$lockout_key]);
        return;
    }
    
    // Increment failed attempts
    if (!isset($_SESSION[$attempts_key])) {
        $_SESSION[$attempts_key] = 0;
    }
    $_SESSION[$attempts_key]++;
    
    // Lock out if too many attempts
    if ($_SESSION[$attempts_key] >= LOGIN_ATTEMPT_LIMIT) {
        $_SESSION[$lockout_key] = time() + LOGIN_LOCKOUT_TIME;
    }
}

/**
 * Secure Session Management
 */
function secureSessionStart() {
    // Only configure session settings if session is not active
    if (session_status() == PHP_SESSION_NONE) {
        // Configure session settings before starting
        ini_set('session.cookie_httponly', 1);
        ini_set('session.use_only_cookies', 1);
        ini_set('session.cookie_secure', 0); // Set to 1 if using HTTPS
        ini_set('session.cookie_samesite', 'Lax');
        ini_set('session.gc_maxlifetime', 1800); // 30 minutes
        
        session_start();
    }

    // Regenerate session ID periodically for security
    if (!isset($_SESSION['last_regeneration'])) {
        $_SESSION['last_regeneration'] = time();
    } elseif (time() - $_SESSION['last_regeneration'] > 300) { // 5 minutes
        session_regenerate_id(true);
        $_SESSION['last_regeneration'] = time();
    }
}
?>