
<?php
// auth/verify_otp.php
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

// Pastikan ada email dari halaman sebelumnya
if (!isset($_SESSION['otp_email'])) {
    header("Location: /cornerbites-sia/auth/forgot_password.php");
    exit();
}

$email = $_SESSION['otp_email'];
$message = '';
$message_type = '';

// Handle resend OTP
if (isset($_POST['resend_otp'])) {
    $emailService = new EmailService();
    $result = $emailService->sendOTPResetEmail($email);
    
    if ($result['success']) {
        $message = 'Kode OTP baru telah dikirim ke email Anda.';
        $message_type = 'success';
    } else {
        $message = $result['message'];
        $message_type = 'error';
    }
}

// Handle OTP verification
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['otp_code'])) {
    $otpCode = trim($_POST['otp_code'] ?? '');

    if (empty($otpCode)) {
        $message = 'Kode OTP harus diisi!';
        $message_type = 'error';
    } elseif (strlen($otpCode) !== 6 || !is_numeric($otpCode)) {
        $message = 'Kode OTP harus 6 digit angka!';
        $message_type = 'error';
    } else {
        $emailService = new EmailService();
        $result = $emailService->verifyOTP($email, $otpCode);

        if ($result['success']) {
            // Set session untuk reset password
            $_SESSION['reset_token'] = $result['reset_token'];
            $_SESSION['reset_user_id'] = $result['user_id'];
            
            // Clear OTP session
            unset($_SESSION['otp_email'], $_SESSION['otp_user_id']);
            
            header("Location: /cornerbites-sia/auth/reset_password_new.php");
            exit();
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
    <title>Verifikasi OTP - Corner Bites SIA</title>
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
        .otp-input { 
            font-family: monospace; 
            letter-spacing: 0.5em; 
            text-align: center; 
        }
    </style>
</head>
<body class="gradient-bg min-h-screen flex items-center justify-center p-4">
    <div class="bg-white/20 backdrop-blur-lg border border-white/30 rounded-3xl shadow-2xl w-full max-w-md p-8">
        <div class="text-center mb-8">
            <div class="inline-flex items-center justify-center w-16 h-16 bg-white/20 rounded-2xl mb-4">
                <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
            </div>
            <h1 class="text-3xl font-bold text-white mb-2">Verifikasi OTP</h1>
            <p class="text-white/80 text-sm">Masukkan kode 6 digit yang dikirim ke:</p>
            <p class="text-white font-semibold text-sm mt-1"><?php echo htmlspecialchars($email); ?></p>
        </div>

        <?php if ($message): ?>
            <div class="<?php echo $message_type === 'success' ? 'bg-green-500/30 border-green-400/50 text-green-100' : 'bg-red-500/30 border-red-400/50 text-red-100'; ?> px-4 py-3 rounded-xl mb-6 backdrop-blur-sm border">
                <span class="font-medium"><?php echo htmlspecialchars($message); ?></span>
            </div>
        <?php endif; ?>

        <form method="POST" class="space-y-6">
            <div>
                <label for="otp_code" class="block text-sm font-medium text-white/90 mb-2 text-center">Kode OTP</label>
                <div class="relative">
                    <input type="text" id="otp_code" name="otp_code" required maxlength="6" pattern="[0-9]{6}"
                           class="otp-input w-full px-4 py-4 bg-white/20 border border-white/30 rounded-xl text-white text-2xl placeholder-white/60 transition duration-300 focus:outline-none focus:ring-2 focus:ring-indigo-400 focus:bg-white/30"
                           placeholder="000000"
                           oninput="this.value = this.value.replace(/[^0-9]/g, '').slice(0, 6)">
                </div>
                <p class="text-white/60 text-xs mt-2 text-center">Kode berlaku selama 5 menit</p>
            </div>

            <button type="submit" 
                    class="w-full py-3 px-6 text-white font-semibold rounded-xl shadow-lg transition duration-300 bg-gradient-to-r from-indigo-500 to-purple-600 hover:from-indigo-600 hover:to-purple-700 focus:outline-none focus:ring-4 focus:ring-indigo-300/50">
                Verifikasi Kode
            </button>
        </form>

        <!-- Resend OTP -->
        <div class="text-center mt-6">
            <p class="text-white/70 text-sm mb-3">Tidak menerima kode?</p>
            <form method="POST" class="inline">
                <button type="submit" name="resend_otp" 
                        class="text-white font-semibold hover:underline bg-transparent border-0 cursor-pointer text-sm">
                    Kirim Ulang Kode
                </button>
            </form>
        </div>

        <div class="text-center mt-6 pt-6 border-t border-white/20">
            <a href="/cornerbites-sia/auth/forgot_password.php" class="text-white/70 text-sm hover:text-white">
                ← Kembali ke input email
            </a>
        </div>
    </div>

    <script>
        // Auto focus input
        document.getElementById('otp_code').focus();

        // Auto submit ketika 6 digit terisi
        document.getElementById('otp_code').addEventListener('input', function(e) {
            if (e.target.value.length === 6) {
                // Auto submit setelah delay singkat
                setTimeout(() => {
                    e.target.form.submit();
                }, 500);
            }
        });

        // Timer countdown 5 menit
        let timeLeft = 300; // 5 menit dalam detik
        const timer = setInterval(function() {
            const minutes = Math.floor(timeLeft / 60);
            const seconds = timeLeft % 60;
            
            // Update display jika ada element timer
            const timerElement = document.querySelector('.timer-display');
            if (timerElement) {
                timerElement.textContent = `${minutes}:${seconds.toString().padStart(2, '0')}`;
            }
            
            timeLeft--;
            
            if (timeLeft < 0) {
                clearInterval(timer);
                alert('Kode OTP sudah kedaluwarsa. Silakan minta kode baru.');
            }
        }, 1000);
    </script>
</body>
</html>
