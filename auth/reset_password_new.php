
<?php
// auth/reset_password_new.php
require_once __DIR__ . '/../config/auth_config.php';
require_once __DIR__ . '/../includes/email_service.php';

secureSessionStart();

// Jika sudah login, redirect ke dashboard
if (isset($_SESSION['user_id'])) {
    $role = $_SESSION['user_role'] ?? 'user';
    $dashboard_path = ($role === 'admin') ? '/cornerbites-sia/admin/dashboard.php' : '/cornerbites-sia/pages/dashboard.php';
    header("Location: " . $dashboard_path);
    exit();
}

// Pastikan ada token dari verifikasi OTP
if (!isset($_SESSION['reset_token']) || !isset($_SESSION['reset_user_id'])) {
    header("Location: /cornerbites-sia/auth/forgot_password.php");
    exit();
}

$resetToken = $_SESSION['reset_token'];
$message = '';
$message_type = '';

// Proses reset password
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $newPassword = trim($_POST['new_password'] ?? '');
    $confirmPassword = trim($_POST['confirm_password'] ?? '');

    if (empty($newPassword) || empty($confirmPassword)) {
        $message = 'Semua field harus diisi!';
        $message_type = 'error';
    } elseif (strlen($newPassword) < 6) {
        $message = 'Password minimal 6 karakter!';
        $message_type = 'error';
    } elseif ($newPassword !== $confirmPassword) {
        $message = 'Konfirmasi password tidak cocok!';
        $message_type = 'error';
    } else {
        $emailService = new EmailService();
        $result = $emailService->resetPasswordWithToken($resetToken, $newPassword);
        
        if ($result['success']) {
            // Clear all reset sessions
            unset($_SESSION['reset_token'], $_SESSION['reset_user_id'], $_SESSION['otp_email'], $_SESSION['otp_user_id']);
            
            $message = 'Password berhasil direset! Silakan login dengan password baru.';
            $message_type = 'success';
            
            // Redirect ke login setelah 3 detik
            header("refresh:3;url=/cornerbites-sia/auth/login.php");
        } else {
            $message = $result['message'];
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
    <title>Reset Password - Corner Bites SIA</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
        .gradient-bg { 
            background: linear-gradient(135deg, #667eea 0%, #764ba2 50%, #805ad5 100%); 
            background-size: 400% 400%; 
            animation: gradientShift 15s ease infinite; 
        }
        @keyframes gradientShift { 
            0% { background-position: 0% 50%; } 
            50% { background-position: 100% 50%; } 
            100% { background-position: 0% 50%; } 
        }
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
            <h1 class="text-3xl font-bold text-white mb-2">Buat Password Baru</h1>
            <p class="text-white/80 text-sm">Masukkan password baru untuk akun Anda</p>
        </div>

        <?php if ($message): ?>
            <div class="<?php echo $message_type === 'success' ? 'bg-green-500/30 border-green-400/50 text-green-100' : 'bg-red-500/30 border-red-400/50 text-red-100'; ?> px-4 py-3 rounded-xl mb-6 backdrop-blur-sm border">
                <span class="font-medium"><?php echo htmlspecialchars($message); ?></span>
                <?php if ($message_type === 'success'): ?>
                    <div class="mt-2">
                        <div class="flex items-center">
                            <svg class="animate-spin -ml-1 mr-3 h-4 w-4 text-green-200" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            <span class="text-sm">Mengarahkan ke login...</span>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php if ($message_type !== 'success'): ?>
            <form method="POST" class="space-y-6">
                <div>
                    <label for="new_password" class="block text-sm font-medium text-white/90 mb-2">Password Baru</label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <svg class="h-5 w-5 text-white/60" fill="none" stroke="currentColor" viewBox="0 0 24 24">
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
                            <svg class="h-5 w-5 text-white/60" fill="none" stroke="currentColor" viewBox="0 0 24 24">
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

    <script>
        // Password strength checker
        document.getElementById('new_password')?.addEventListener('input', function(e) {
            const password = e.target.value;
            // Bisa ditambahkan indikator kekuatan password
        });

        // Konfirmasi password match checker
        document.getElementById('confirm_password')?.addEventListener('input', function(e) {
            const password = document.getElementById('new_password').value;
            const confirm = e.target.value;
            
            if (confirm && password !== confirm) {
                e.target.setCustomValidity('Password tidak cocok');
                e.target.style.borderColor = '#ef4444';
            } else {
                e.target.setCustomValidity('');
                e.target.style.borderColor = '';
            }
        });

        // Focus pada field pertama
        document.getElementById('new_password')?.focus();
    </script>
</body>
</html>
