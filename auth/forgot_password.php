
<?php
// auth/forgot_password.php
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

$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');

    if (empty($email)) {
        $message = 'Email harus diisi!';
        $message_type = 'error';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = 'Format email tidak valid!';
        $message_type = 'error';
    } else {
        $emailService = new EmailService();
        $result = $emailService->sendOTPResetEmail($email);

        if ($result['success']) {
            // Set session untuk lanjut ke OTP
            $_SESSION['otp_email'] = $email;
            $_SESSION['otp_user_id'] = $result['user_id'];
            header("Location: /cornerbites-sia/auth/verify_otp.php");
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
    <title>Lupa Password - Corner Bites SIA</title>
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
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 12a4 4 0 10-8 0 4 4 0 008 0zm0 0v1.5a2.5 2.5 0 005 0V12a9 9 0 10-9 9m4.5-1.206a8.959 8.959 0 01-4.5 1.207"></path>
                </svg>
            </div>
            <h1 class="text-3xl font-bold text-white mb-2">Reset Password</h1>
            <p class="text-white/80 text-sm">Masukkan alamat email untuk mendapat kode verifikasi</p>
        </div>

        <?php if ($message): ?>
            <div class="<?php echo $message_type === 'success' ? 'bg-green-500/30 border-green-400/50 text-green-100' : 'bg-red-500/30 border-red-400/50 text-red-100'; ?> px-4 py-3 rounded-xl mb-6 backdrop-blur-sm border">
                <span class="font-medium"><?php echo htmlspecialchars($message); ?></span>
            </div>
        <?php endif; ?>

        <form method="POST" class="space-y-6">
            <div>
                <label for="email" class="block text-sm font-medium text-white/90 mb-2">Alamat Email</label>
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <svg class="h-5 w-5 text-white/60" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 12a4 4 0 10-8 0 4 4 0 008 0zm0 0v1.5a2.5 2.5 0 005 0V12a9 9 0 10-9 9m4.5-1.206a8.959 8.959 0 01-4.5 1.207"></path>
                        </svg>
                    </div>
                    <input type="email" id="email" name="email" required
                           value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"
                           class="w-full pl-10 pr-4 py-3 bg-white/20 border border-white/30 rounded-xl text-white placeholder-white/60 transition duration-300 focus:outline-none focus:ring-2 focus:ring-indigo-400 focus:bg-white/30"
                           placeholder="masukkan@email.com">
                </div>
            </div>

            <button type="submit" 
                    class="w-full py-3 px-6 text-white font-semibold rounded-xl shadow-lg transition duration-300 bg-gradient-to-r from-indigo-500 to-purple-600 hover:from-indigo-600 hover:to-purple-700 focus:outline-none focus:ring-4 focus:ring-indigo-300/50">
                Kirim Kode Verifikasi
            </button>
        </form>

        <div class="text-center mt-6 pt-6 border-t border-white/20">
            <p class="text-white/70 text-sm">
                Ingat password? 
                <a href="/cornerbites-sia/auth/login.php" class="text-white font-semibold hover:underline ml-1">Kembali ke Login</a>
            </p>
        </div>
    </div>
</body>
</html>
