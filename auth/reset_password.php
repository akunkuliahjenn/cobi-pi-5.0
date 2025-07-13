
<?php
// auth/reset_password.php - DEPRECATED
// File ini tidak diperlukan lagi karena menggunakan sistem temporary password via email
// Redirect ke halaman login

header("Location: /cornerbites-sia/auth/login.php");
exit();

// Jika sudah login, redirect ke dashboard
if (isset($_SESSION['user_id'])) {
    $role = $_SESSION['user_role'] ?? 'user';
    $dashboard_path = ($role === 'admin') ? '/cornerbites-sia/admin/dashboard.php' : '/cornerbites-sia/pages/dashboard.php';
    header("Location: " . $dashboard_path);
    exit();
}

$message = '';
$message_type = '';
$token = $_GET['token'] ?? '';
$valid_token = false;
$user_email = '';

// Validasi token
if (!empty($token)) {
    try {
        $stmt = $db->prepare("SELECT email FROM password_reset_tokens WHERE token = ? AND expires_at > ? AND used = 0");
        $stmt->execute([$token, date('Y-m-d H:i:s')]);
        $reset_request = $stmt->fetch();
        
        if ($reset_request) {
            $valid_token = true;
            $user_email = $reset_request['email'];
        } else {
            // Debug: Cek apakah token ada tapi expired
            $stmt_debug = $db->prepare("SELECT email, expires_at, used FROM password_reset_tokens WHERE token = ?");
            $stmt_debug->execute([$token]);
            $debug_info = $stmt_debug->fetch();
            
            if ($debug_info) {
                if ($debug_info['used'] == 1) {
                    $message = 'Link reset password sudah pernah digunakan!';
                } elseif ($debug_info['expires_at'] <= date('Y-m-d H:i:s')) {
                    $message = 'Link reset password sudah kedaluwarsa! Silakan buat permintaan baru.';
                } else {
                    $message = 'Token tidak valid!';
                }
            } else {
                $message = 'Token tidak ditemukan!';
            }
            $message_type = 'error';
        }
    } catch (PDOException $e) {
        error_log("Error validating reset token: " . $e->getMessage());
        $message = 'Terjadi kesalahan sistem!';
        $message_type = 'error';
    }
} else {
    $message = 'Token reset password tidak ditemukan!';
    $message_type = 'error';
}

// Proses reset password
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $valid_token) {
    $new_password = trim($_POST['new_password'] ?? '');
    $confirm_password = trim($_POST['confirm_password'] ?? '');

    if (empty($new_password) || empty($confirm_password)) {
        $message = 'Semua field harus diisi!';
        $message_type = 'error';
    } elseif (strlen($new_password) < 6) {
        $message = 'Password minimal 6 karakter!';
        $message_type = 'error';
    } elseif ($new_password !== $confirm_password) {
        $message = 'Konfirmasi password tidak cocok!';
        $message_type = 'error';
    } else {
        try {
            $db->beginTransaction();
            
            // Update password user
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $db->prepare("UPDATE users SET password = ?, must_change_password = 0 WHERE email = ?");
            $result = $stmt->execute([$hashed_password, $user_email]);
            
            if ($result) {
                // Tandai token sebagai sudah digunakan
                $stmt = $db->prepare("UPDATE password_reset_tokens SET used = 1 WHERE token = ?");
                $stmt->execute([$token]);
                
                $db->commit();
                
                $message = 'Password berhasil direset! Silakan login dengan password baru.';
                $message_type = 'success';
                
                // Redirect ke login setelah 3 detik
                header("refresh:3;url=/cornerbites-sia/auth/login.php");
            } else {
                $db->rollBack();
                $message = 'Gagal mereset password!';
                $message_type = 'error';
            }
        } catch (PDOException $e) {
            $db->rollBack();
            error_log("Error resetting password: " . $e->getMessage());
            $message = 'Terjadi kesalahan sistem!';
            $message_type = 'error';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - Kalkulator HPP</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
        .gradient-bg { background: linear-gradient(135deg, #667eea 0%, #764ba2 50%, #805ad5 100%); background-size: 400% 400%; animation: gradientShift 15s ease infinite; }
        @keyframes gradientShift { 0% { background-position: 0% 50%; } 50% { background-position: 100% 50%; } 100% { background-position: 0% 50%; } }
    </style>
</head>
<body class="gradient-bg min-h-screen flex items-center justify-center p-4">
    <div class="bg-white/20 backdrop-blur-lg border border-white/30 rounded-3xl shadow-2xl w-full max-w-md p-8">
        <div class="text-center mb-8">
            <div class="inline-flex items-center justify-center w-16 h-16 bg-white/20 rounded-2xl mb-4">
                <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
                </svg>
            </div>
            <h1 class="text-3xl font-bold text-white mb-2">Reset Password</h1>
            <p class="text-white/80 text-sm">Masukkan password baru Anda</p>
        </div>

        <?php if ($message): ?>
            <div class="<?php echo $message_type === 'success' ? 'bg-green-500/30 border-green-400/50 text-green-100' : 'bg-red-500/30 border-red-400/50 text-red-100'; ?> px-4 py-3 rounded-xl mb-6 backdrop-blur-sm">
                <span class="font-medium"><?php echo htmlspecialchars($message); ?></span>
            </div>
        <?php endif; ?>

        <?php if ($valid_token && $message_type !== 'success'): ?>
            <form method="POST" class="space-y-6">
                <div>
                    <label for="new_password" class="block text-sm font-medium text-white/90 mb-2">Password Baru</label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
                            </svg>
                        </div>
                        <input type="password" id="new_password" name="new_password" required minlength="6"
                               class="w-full pl-10 pr-4 py-3 bg-white/20 border border-white/30 rounded-xl text-white placeholder-white/60 transition duration-300 focus:outline-none focus:ring-2 focus:ring-indigo-400 focus:bg-white/30"
                               placeholder="Minimal 6 karakter">
                    </div>
                </div>

                <div>
                    <label for="confirm_password" class="block text-sm font-medium text-white/90 mb-2">Konfirmasi Password</label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
                            </svg>
                        </div>
                        <input type="password" id="confirm_password" name="confirm_password" required minlength="6"
                               class="w-full pl-10 pr-4 py-3 bg-white/20 border border-white/30 rounded-xl text-white placeholder-white/60 transition duration-300 focus:outline-none focus:ring-2 focus:ring-indigo-400 focus:bg-white/30"
                               placeholder="Ulangi password baru">
                    </div>
                </div>

                <button type="submit" 
                        class="w-full py-3 px-6 text-white font-semibold rounded-xl shadow-lg transition duration-300 bg-gradient-to-r from-indigo-500 to-purple-600 hover:from-indigo-600 hover:to-purple-700 focus:outline-none focus:ring-4 focus:ring-indigo-300/50">
                    Reset Password
                </button>
            </form>
        <?php endif; ?>

        <div class="text-center mt-6 pt-6 border-t border-white/20">
            <p class="text-white/70 text-sm">
                <a href="/cornerbites-sia/auth/login.php" class="text-white font-semibold hover:underline">Kembali ke Login</a>
            </p>
        </div>
    </div>
</body>
</html>
