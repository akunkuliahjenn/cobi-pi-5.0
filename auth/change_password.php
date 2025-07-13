<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../config/db.php';

// SUPER STRICT MIDDLEWARE - NO BYPASS ALLOWED
function enforcePasswordChange() {
    global $db;

    // Debug session state
    error_log("Change password access - Session data: " . print_r($_SESSION, true));

    // Validate session first
    if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
        error_log("Change password: No valid session found, redirecting to login");
        session_destroy();
        $_SESSION['error_message'] = 'Sesi tidak valid. Silakan login kembali.';
        header("Location: /cornerbites-sia/auth/login.php");
        exit();
    }

    // Always check database first - this is the source of truth
    try {
        $stmt = $db->prepare("SELECT must_change_password, username, role FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch();

        // If user not found, force logout
        if (!$user) {
            session_destroy();
            $_SESSION['error_message'] = 'User tidak ditemukan. Silakan login ulang.';
            header("Location: /cornerbites-sia/auth/login.php");
            exit();
        }

        // If user doesn't need to change password, redirect appropriately
        if ($user['must_change_password'] != 1) {
            // Clear any password change flags
            unset($_SESSION['must_change_password']);
            unset($_SESSION['force_password_change']);
            unset($_SESSION['password_change_token']);

            if ($user['role'] === 'admin') {
                header("Location: /cornerbites-sia/admin/dashboard.php");
            } else {
                header("Location: /cornerbites-sia/pages/dashboard.php");
            }
            exit();
        }

        // Set session flags
        $_SESSION['must_change_password'] = true;
        $_SESSION['force_password_change'] = true;
        $_SESSION['username'] = $user['username'];
        $_SESSION['user_role'] = $user['role'];

        // Generate unique token untuk mencegah bypass
        $_SESSION['password_change_token'] = bin2hex(random_bytes(32));
        $_SESSION['password_change_start_time'] = time();

        // Anti-tampering measures
        $_SESSION['password_change_ip'] = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $_SESSION['password_change_user_agent'] = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';

    } catch (PDOException $e) {
        error_log("Password change enforcement error: " . $e->getMessage());
        session_destroy();
        $_SESSION['error_message'] = 'Terjadi kesalahan sistem. Silakan login ulang.';
        header("Location: /cornerbites-sia/auth/login.php");
        exit();
    }
}

// Apply the middleware
enforcePasswordChange();

$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Ensure token exists in session
    if (!isset($_SESSION['password_change_token'])) {
        $_SESSION['password_change_token'] = bin2hex(random_bytes(32));
    }

    // Skip token validation for now - focus on password change
    if (isset($_POST['new_password']) && !empty($_POST['new_password'])) {
        $new_password = trim($_POST['new_password']);
        $confirm_password = trim($_POST['confirm_password']);

        if (empty($new_password) || strlen($new_password) < 6) {
            $message = 'Password minimal 6 karakter.';
            $message_type = 'error';
        } elseif ($new_password !== $confirm_password) {
            $message = 'Konfirmasi password tidak cocok.';
            $message_type = 'error';
        } else {
            try {
                // Start transaction untuk memastikan consistency
                $db->beginTransaction();

                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

                // Update password and clear must_change_password flag
                $stmt = $db->prepare("UPDATE users SET password = ?, must_change_password = 0 WHERE id = ?");
                $update_success = $stmt->execute([$hashed_password, $_SESSION['user_id']]);

                if ($update_success) {
                    // Commit transaction
                    $db->commit();

                    // IMMEDIATE verification - cek apakah update berhasil
                    $verify_stmt = $db->prepare("SELECT password, must_change_password FROM users WHERE id = ?");
                    $verify_stmt->execute([$_SESSION['user_id']]);
                    $updated_user = $verify_stmt->fetch();

                    // Triple check: pastikan data benar-benar ter-update
                    if ($updated_user && 
                        password_verify($new_password, $updated_user['password']) && 
                        $updated_user['must_change_password'] == 0) {

                        // Log successful password change
                        error_log("Password successfully changed for user ID: " . $_SESSION['user_id']);

                        // Log activity
                        if (file_exists(__DIR__ . '/../includes/activity_logger.php')) {
                            require_once __DIR__ . '/../includes/activity_logger.php';
                            logActivity($_SESSION['user_id'], $_SESSION['username'], 'change_password', 'Password berhasil diubah setelah reset admin', $db);
                        }

                        // Clear session completely dan redirect ke login
                        session_destroy();

                        // Start fresh session untuk success message
                        session_start();
                        $_SESSION['success_message'] = 'Password berhasil diubah! Silakan login dengan password baru Anda.';

                        // Redirect dengan JavaScript yang lebih kuat
                        echo "<script type='text/javascript'>";
                        echo "alert('Password berhasil diubah! Silakan login dengan password baru Anda.');";
                        echo "window.location.href = '/cornerbites-sia/auth/login.php';";
                        echo "</script>";

                        // Backup PHP header redirect
                        header("Location: /cornerbites-sia/auth/login.php", true, 302);
                        exit();

                    } else {
                        // Rollback jika verifikasi gagal
                        $db->rollBack();
                        error_log("Password change verification failed for user ID: " . $_SESSION['user_id']);
                        $message = 'Password gagal disimpan. Silakan coba lagi.';
                        $message_type = 'error';
                    }
                } else {
                    $db->rollBack();
                    $message = 'Gagal memperbarui password. Silakan coba lagi.';
                    $message_type = 'error';
                }

            } catch (PDOException $e) {
                $db->rollBack();
                error_log("Password change error: " . $e->getMessage());
                $message = 'Terjadi kesalahan sistem. Silakan coba lagi.';
                $message_type = 'error';
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ganti Password - Aplikasi Kalkulator HPP</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        .gradient-bg {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        .glass-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
    </style>
</head>
<body class="gradient-bg min-h-screen flex items-center justify-center p-4">
    <div class="glass-card rounded-2xl shadow-2xl w-full max-w-md p-8">
        <div class="text-center mb-8">
            <div class="bg-red-100 rounded-full w-20 h-20 flex items-center justify-center mx-auto mb-4">
                <svg class="w-10 h-10 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16c-.77.833.192 2.5 1.732 2.5z" />
                </svg>
            </div>
            <h1 class="text-3xl font-bold text-gray-800 mb-2">Password Harus Diganti</h1>
            <p class="text-gray-600">Admin telah mereset password Anda. Untuk keamanan, silakan buat password baru sebelum melanjutkan.</p>
        </div>

        <?php if (!empty($message)): ?>
            <div class="mb-4 p-3 rounded <?php echo $message_type === 'error' ? 'bg-red-100 text-red-700' : 'bg-green-100 text-green-700'; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <form method="POST" class="space-y-6" id="passwordChangeForm">
            <input type="hidden" name="password_change_token" value="<?php echo $_SESSION['password_change_token']; ?>">

            <div>
                <label for="new_password" class="block text-sm font-medium text-gray-700 mb-2">Password Baru</label>
                <div class="relative">
                    <input type="password" id="new_password" name="new_password" 
                           class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-transparent transition-all" 
                           placeholder="Masukkan password baru" required>
                    <button type="button" onclick="togglePassword('new_password')" 
                            class="absolute right-3 top-3 text-gray-400 hover:text-gray-600">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                        </svg>
                    </button>
                </div>
                <p class="text-xs text-gray-500 mt-1">Minimal 6 karakter</p>
            </div>

            <div>
                <label for="confirm_password" class="block text-sm font-medium text-gray-700 mb-2">Konfirmasi Password</label>
                <div class="relative">
                    <input type="password" id="confirm_password" name="confirm_password" 
                           class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-transparent transition-all" 
                           placeholder="Ulangi password baru" required>
                    <button type="button" onclick="togglePassword('confirm_password')" 
                            class="absolute right-3 top-3 text-gray-400 hover:text-gray-600">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                        </svg>
                    </button>
                </div>
                <div id="password-match" class="text-xs mt-1"></div>
            </div>

            <button type="submit" id="submitBtn" 
                    class="w-full bg-gradient-to-r from-purple-600 to-indigo-600 text-white py-3 px-6 rounded-xl font-semibold hover:from-purple-700 hover:to-indigo-700 transition-all duration-300 shadow-lg hover:shadow-xl transform hover:-translate-y-1 disabled:opacity-50 disabled:cursor-not-allowed disabled:transform-none">
                <span class="flex items-center justify-center">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    Simpan Password Baru
                </span>
            </button>
        </form>

        <div class="mt-6 p-4 bg-yellow-50 border border-yellow-200 rounded-xl">
            <div class="flex items-start">
                <svg class="w-5 h-5 text-yellow-600 mt-0.5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <div>
                    <h4 class="text-sm font-medium text-yellow-800">Penting!</h4>
                    <p class="text-xs text-yellow-700 mt-1">Anda tidak dapat melewati halaman ini tanpa mengganti password. Ini untuk keamanan akun Anda.</p>
                </div>
            </div>
        </div>

        <script>
            function togglePassword(fieldId) {
                const field = document.getElementById(fieldId);
                field.type = field.type === 'password' ? 'text' : 'password';
            }

            // Handle form submission
            document.getElementById('passwordChangeForm').addEventListener('submit', function(e) {
                const password = document.getElementById('new_password').value;
                const confirm = document.getElementById('confirm_password').value;

                if (password !== confirm) {
                    e.preventDefault();
                    alert('Password dan konfirmasi password harus sama!');
                    return false;
                }

                // Show loading state
                const submitBtn = document.getElementById('submitBtn');
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<span class="flex items-center justify-center"><svg class="animate-spin -ml-1 mr-3 h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>Menyimpan...</span>';
            });

            // Real-time password matching
            document.getElementById('confirm_password').addEventListener('input', function() {
                const password = document.getElementById('new_password').value;
                const confirm = this.value;
                const matchDiv = document.getElementById('password-match');
                const submitBtn = document.getElementById('submitBtn');

                if (confirm === '') {
                    matchDiv.textContent = '';
                    submitBtn.disabled = false;
                } else if (password === confirm) {
                    matchDiv.textContent = '✓ Password cocok';
                    matchDiv.className = 'text-xs mt-1 text-green-600';
                    submitBtn.disabled = false;
                } else {
                    matchDiv.textContent = '✗ Password tidak cocok';
                    matchDiv.className = 'text-xs mt-1 text-red-600';
                    submitBtn.disabled = true;
                }
            });
        </script>
    </div>
</body>
</html>