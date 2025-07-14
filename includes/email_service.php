
<?php
// includes/email_service.php
// Service untuk mengirim email reset password dengan OTP

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/db.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

class EmailService {
    private $config;
    private $db;

    public function __construct() {
        $this->config = require __DIR__ . '/../config/email_config.php';
        global $db;
        $this->db = $db;
    }

    /**
     * Generate dan kirim OTP via email
     */
    public function sendOTPResetEmail($email) {
        try {
            // Cek apakah email terdaftar
            $stmt = $this->db->prepare("SELECT id, username FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if (!$user) {
                return ['success' => false, 'message' => 'Email tidak ditemukan dalam sistem'];
            }

            // Generate OTP 6 digit
            $otpCode = $this->generateOTP();
            $otpHash = password_hash($otpCode, PASSWORD_DEFAULT);
            
            // Set expiry 5 menit dari sekarang
            $expiresAt = date('Y-m-d H:i:s', time() + 300); // 5 menit

            // DEBUG: Log OTP generation
            error_log("=== OTP GENERATION DEBUG ===");
            error_log("User ID: " . $user['id']);
            error_log("Email: " . $email);
            error_log("OTP Code: " . $otpCode);
            error_log("OTP Hash: " . $otpHash);
            error_log("Expires At: " . $expiresAt);
            error_log("Current Time: " . date('Y-m-d H:i:s'));

            // Hapus OTP lama yang belum digunakan untuk user ini
            $deleteStmt = $this->db->prepare("DELETE FROM otp_tokens WHERE user_id = ? AND used = 0");
            $deleteStmt->execute([$user['id']]);
            error_log("Old OTP tokens deleted for user_id: " . $user['id']);

            // Simpan OTP ke database
            $stmt = $this->db->prepare("
                INSERT INTO otp_tokens (user_id, email, otp_code, otp_hash, expires_at, used, attempt_count, max_attempts) 
                VALUES (?, ?, ?, ?, ?, 0, 0, 3)
            ");
            $stmt->execute([$user['id'], $email, $otpCode, $otpHash, $expiresAt]);

            // Log activity
            $logStmt = $this->db->prepare("
                INSERT INTO activity_logs (user_id, username, activity_type, activity_description, user_agent) 
                VALUES (?, ?, 'otp_reset_request', 'OTP reset password request sent to email', ?)
            ");
            $logStmt->execute([
                $user['id'], 
                $user['username'], 
                $_SERVER['HTTP_USER_AGENT'] ?? ''
            ]);

            // Kirim email dengan OTP
            $subject = 'Kode Verifikasi Reset Password - Kalkulator HPP';
            $body = $this->getOTPEmailTemplate($user['username'], $otpCode);

            $result = $this->sendEmail($email, $user['username'], $subject, $body);

            if ($result['success']) {
                error_log("OTP sent successfully for user: " . $user['username'] . " to email: " . $email);
                error_log("OTP Code: " . $otpCode); // Untuk debugging, hapus di production
                return [
                    'success' => true, 
                    'message' => 'Kode OTP telah dikirim ke email Anda. Kode berlaku selama 5 menit.',
                    'email' => $email,
                    'user_id' => $user['id']
                ];
            }

            return $result;

        } catch (Exception $e) {
            error_log("Error sending OTP email: " . $e->getMessage());
            return ['success' => false, 'message' => 'Gagal mengirim kode OTP'];
        }
    }

    /**
     * Verifikasi OTP
     */
    public function verifyOTP($email, $otpCode) {
        try {
            // DEBUG: Log verification attempt
            error_log("=== OTP VERIFICATION DEBUG ===");
            error_log("Email: " . $email);
            error_log("Input OTP: " . $otpCode);
            error_log("Current Time: " . date('Y-m-d H:i:s'));

            // Cari OTP yang valid
            $stmt = $this->db->prepare("
                SELECT ot.*, u.username 
                FROM otp_tokens ot
                JOIN users u ON ot.user_id = u.id
                WHERE ot.email = ? AND ot.used = 0 AND ot.expires_at > ? AND ot.attempt_count < ot.max_attempts
                ORDER BY ot.created_at DESC 
                LIMIT 1
            ");
            $stmt->execute([$email, date('Y-m-d H:i:s')]);
            $otpData = $stmt->fetch();

            // DEBUG: Log database result
            if ($otpData) {
                error_log("OTP Found in DB:");
                error_log("- OTP Hash: " . $otpData['otp_hash']);
                error_log("- Expires At: " . $otpData['expires_at']);
                error_log("- Attempt Count: " . $otpData['attempt_count']);
                error_log("- Max Attempts: " . $otpData['max_attempts']);
                error_log("- Used: " . $otpData['used']);
            } else {
                error_log("NO OTP FOUND in database!");
                
                // Check if any OTP exists for this email
                $debugStmt = $this->db->prepare("SELECT * FROM otp_tokens WHERE email = ? ORDER BY created_at DESC LIMIT 3");
                $debugStmt->execute([$email]);
                $allOtp = $debugStmt->fetchAll();
                error_log("All OTP for email " . $email . ": " . json_encode($allOtp));
            }

            if (!$otpData) {
                return ['success' => false, 'message' => 'Kode OTP tidak valid atau sudah kedaluwarsa'];
            }

            // Verifikasi OTP
            error_log("=== PASSWORD VERIFICATION ===");
            error_log("Input OTP: " . $otpCode);
            error_log("Stored Hash: " . $otpData['otp_hash']);
            $verifyResult = password_verify($otpCode, $otpData['otp_hash']);
            error_log("Verification Result: " . ($verifyResult ? 'TRUE' : 'FALSE'));
            
            if (!$verifyResult) {
                // Increment attempt count
                $updateStmt = $this->db->prepare("UPDATE otp_tokens SET attempt_count = attempt_count + 1 WHERE id = ?");
                $updateStmt->execute([$otpData['id']]);

                $remainingAttempts = $otpData['max_attempts'] - ($otpData['attempt_count'] + 1);
                
                if ($remainingAttempts <= 0) {
                    return ['success' => false, 'message' => 'Terlalu banyak percobaan. Silakan minta kode OTP baru.'];
                }
                
                return ['success' => false, 'message' => "Kode OTP salah. Sisa percobaan: $remainingAttempts"];
            }

            // OTP benar, buat token sementara untuk reset password
            $resetToken = bin2hex(random_bytes(32));
            $tokenExpiry = date('Y-m-d H:i:s', time() + 600); // 10 menit untuk reset password

            // Simpan token reset
            $tokenStmt = $this->db->prepare("
                INSERT INTO password_reset_tokens (user_id, email, token, expires_at, used) 
                VALUES (?, ?, ?, ?, 0)
            ");
            $tokenStmt->execute([$otpData['user_id'], $email, $resetToken, $tokenExpiry]);

            // Tandai OTP sebagai digunakan
            $markUsedStmt = $this->db->prepare("UPDATE otp_tokens SET used = 1 WHERE id = ?");
            $markUsedStmt->execute([$otpData['id']]);

            // Log activity
            $logStmt = $this->db->prepare("
                INSERT INTO activity_logs (user_id, username, activity_type, activity_description, user_agent) 
                VALUES (?, ?, 'otp_verified', 'OTP verified successfully for password reset', ?)
            ");
            $logStmt->execute([
                $otpData['user_id'], 
                $otpData['username'], 
                $_SERVER['HTTP_USER_AGENT'] ?? ''
            ]);

            return [
                'success' => true, 
                'message' => 'Kode OTP benar!',
                'reset_token' => $resetToken,
                'user_id' => $otpData['user_id']
            ];

        } catch (Exception $e) {
            error_log("Error verifying OTP: " . $e->getMessage());
            return ['success' => false, 'message' => 'Terjadi kesalahan sistem'];
        }
    }

    /**
     * Reset password dengan token
     */
    public function resetPasswordWithToken($token, $newPassword) {
        try {
            $tokenData = $this->verifyResetToken($token);
            if (!$tokenData) {
                return ['success' => false, 'message' => 'Token tidak valid atau sudah kedaluwarsa.'];
            }

            // Hash password baru
            $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);

            // Update password user
            $updateStmt = $this->db->prepare("UPDATE users SET password = ?, must_change_password = 0 WHERE id = ?");
            $updateStmt->execute([$hashedPassword, $tokenData['user_id']]);

            // Tandai token sebagai used
            $markUsedStmt = $this->db->prepare("UPDATE password_reset_tokens SET used = 1 WHERE token = ?");
            $markUsedStmt->execute([$token]);

            // Log activity
            $logStmt = $this->db->prepare("
                INSERT INTO activity_logs (user_id, username, activity_type, activity_description, user_agent) 
                VALUES (?, ?, 'password_reset_success', 'Password reset completed via OTP verification', ?)
            ");
            $logStmt->execute([
                $tokenData['user_id'], 
                $tokenData['username'], 
                $_SERVER['HTTP_USER_AGENT'] ?? ''
            ]);

            return ['success' => true, 'message' => 'Password berhasil direset!'];

        } catch (Exception $e) {
            error_log("Password reset error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Terjadi kesalahan sistem.'];
        }
    }

    /**
     * Verifikasi reset token
     */
    public function verifyResetToken($token) {
        try {
            $stmt = $this->db->prepare("
                SELECT prt.*, u.username, u.email 
                FROM password_reset_tokens prt
                JOIN users u ON prt.user_id = u.id
                WHERE prt.token = ? AND prt.expires_at > ? AND prt.used = 0
            ");
            $stmt->execute([$token, date('Y-m-d H:i:s')]);
            $result = $stmt->fetch();

            return $result ? $result : false;
        } catch (Exception $e) {
            error_log("Token verification error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Generate OTP 6 digit
     */
    private function generateOTP($length = 6) {
        return str_pad(random_int(0, pow(10, $length) - 1), $length, '0', STR_PAD_LEFT);
    }

    /**
     * Kirim email menggunakan PHPMailer
     */
    private function sendEmail($to, $toName, $subject, $body) {
        $mail = new PHPMailer(true);

        try {
            // Server settings
            $mail->isSMTP();
            $mail->Host = $this->config['smtp']['host'];
            $mail->SMTPAuth = true;
            $mail->Username = $this->config['smtp']['username'];
            $mail->Password = $this->config['smtp']['password'];
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = $this->config['smtp']['port'];

            // Recipients
            $mail->setFrom($this->config['smtp']['from_email'], $this->config['smtp']['from_name']);
            $mail->addAddress($to, $toName);

            // Content
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body = $body;

            $mail->send();
            return ['success' => true, 'message' => 'Email berhasil dikirim!'];
        } catch (Exception $e) {
            error_log("Email send error: {$mail->ErrorInfo}");
            return ['success' => false, 'message' => 'Gagal mengirim email. Silakan coba lagi.'];
        }
    }

    /**
     * Template email OTP
     */
    private function getOTPEmailTemplate($username, $otpCode) {
        return "
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 0; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
                .content { background: #f9f9f9; padding: 30px; border-radius: 0 0 10px 10px; }
                .otp-box { background: #fff; border: 3px solid #667eea; padding: 25px; border-radius: 10px; margin: 25px 0; text-align: center; }
                .otp-code { font-size: 36px; font-weight: bold; color: #667eea; letter-spacing: 8px; margin: 15px 0; font-family: monospace; }
                .footer { font-size: 12px; color: #666; margin-top: 30px; text-align: center; }
                .warning-box { background: #fff3cd; border: 1px solid #ffeaa7; padding: 15px; border-radius: 5px; margin: 20px 0; }
                .timer-info { background: #d1ecf1; border: 1px solid #bee5eb; padding: 15px; border-radius: 5px; margin: 20px 0; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>🔐 Verifikasi Reset Password</h1>
                    <p>Aplikasi Kalkulator HPP - Sistem Kalkulasi Harga Pokok Produksi</p>
                </div>
                <div class='content'>
                    <h2>Halo, {$username}!</h2>
                    <p>Kami menerima permintaan untuk mereset password akun Anda di sistem <strong>Aplikasi Kalkulator HPP</strong>.</p>
                    
                    <div class='otp-box'>
                        <p><strong>Kode Verifikasi OTP Anda:</strong></p>
                        <div class='otp-code'>{$otpCode}</div>
                        <p><small>Masukkan kode ini di halaman verifikasi</small></p>
                    </div>
                    
                    <div class='timer-info'>
                        <h4>⏰ Informasi Waktu:</h4>
                        <ul>
                            <li><strong>Kode berlaku selama 5 menit</strong> sejak email ini dikirim</li>
                            <li>Setelah 5 menit, Anda perlu meminta kode baru</li>
                            <li>Maksimal 3 kali percobaan memasukkan kode</li>
                        </ul>
                    </div>
                    
                    <div class='warning-box'>
                        <h4>⚠️ Informasi Keamanan:</h4>
                        <ul>
                            <li><strong>JANGAN bagikan kode ini</strong> kepada siapapun</li>
                            <li>Tim kami tidak akan pernah meminta kode OTP melalui telepon atau media lain</li>
                            <li>Jika Anda tidak meminta reset password, abaikan email ini</li>
                            <li>Segera hubungi administrator jika ada aktivitas mencurigakan</li>
                        </ul>
                    </div>
                </div>
                <div class='footer'>
                    <p><strong>Aplikasi Kalkulator HPP</strong></p>
                    <p>Email ini dikirim otomatis dari sistem. Jangan membalas email ini.</p>
                    <p>© 2025 Aplikasi Kalkulator HPP. All rights reserved.</p>
                </div>
            </div>
        </body>
        </html>
        ";
    }

    // Backward compatibility methods
    public function sendPasswordResetEmailWithUsernameVerification($username, $email) {
        return $this->sendOTPResetEmail($email);
    }

    public function sendPasswordResetEmail($email) {
        return $this->sendOTPResetEmail($email);
    }
}
?>
