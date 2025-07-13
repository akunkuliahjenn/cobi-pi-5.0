
<?php
// auth/reset_password.php
require_once __DIR__ . '/../config/auth_config.php';
require_once __DIR__ . '/../includes/email_service.php';

secureSessionStart();

$token = $_GET['token'] ?? '';
$message = '';
$message_type = '';
$valid_token = false;

if (empty($token)) {
    $message = 'Token tidak valid!';
    $message_type = 'error';
} else {
    $emailService = new EmailService();
    $tokenData = $emailService->verifyResetToken($token);
    
    if ($tokenData) {
        $valid_token = true;
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $new_password = $_POST['new_password'] ?? '';
            $confirm_password = $_POST['confirm_password'] ?? '';
            
            if (empty($new_password) || empty($confirm_password)) {
                $message = 'Password harus diisi!';
                $message_type = 'error';
            } elseif ($new_password !== $confirm_password) {
                $message = 'Konfirmasi password tidak sesuai!';
                $message_type = 'error';
            } elseif (strlen($new_password) < 6) {
                $message = 'Password minimal 6 karakter!';
                $message_type = 'error';
            } else {
                $result = $emailService->resetPasswordWithToken($token, $new_password);
                $message = $result['message'];
                $message_type = $result['success'] ? 'success' : 'error';
                
                if ($result['success']) {
                    $valid_token = false; // Hide form after success
                }
            }
        }
    } else {
        $message = 'Token tidak valid atau sudah kedaluwarsa!';
        $message_type = 'error';
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - Corner Bites App</title>
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

        <?php if ($valid_token): ?>
        <form method="POST" class="space-y-6">
            <div>
                <label for="new_password" class="block text-sm font-medium text-white/90 mb-2">Password Baru</label>
                <input type="password" id="new_password" name="new_password" required
                       class="w-full px-4 py-3 bg-white/20 border border-white/30 rounded-xl text-white placeholder-white/60 transition duration-300 focus:outline-none focus:ring-2 focus:ring-indigo-400 focus:bg-white/30"
                       placeholder="Masukkan password baru">
            </div>

            <div>
                <label for="confirm_password" class="block text-sm font-medium text-white/90 mb-2">Konfirmasi Password</label>
                <input type="password" id="confirm_password" name="confirm_password" required
                       class="w-full px-4 py-3 bg-white/20 border border-white/30 rounded-xl text-white placeholder-white/60 transition duration-300 focus:outline-none focus:ring-2 focus:ring-indigo-400 focus:bg-white/30"
                       placeholder="Ulangi password baru">
            </div>

            <button type="submit" 
                    class="w-full py-3 px-6 text-white font-semibold rounded-xl shadow-lg transition duration-300 bg-gradient-to-r from-indigo-500 to-purple-600 hover:from-indigo-600 hover:to-purple-700 focus:outline-none focus:ring-4 focus:ring-indigo-300/50">
                Reset Password
            </button>
        </form>
        <?php else: ?>
        <div class="text-center">
            <a href="/cornerbites-sia/auth/login.php" 
               class="inline-block py-3 px-6 text-white font-semibold rounded-xl shadow-lg transition duration-300 bg-gradient-to-r from-indigo-500 to-purple-600 hover:from-indigo-600 hover:to-purple-700">
                Kembali ke Login
            </a>
        </div>
        <?php endif; ?>
    </div>
</body>
</html>
