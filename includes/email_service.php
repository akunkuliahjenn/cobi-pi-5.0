php
<?php
// includes/email_service.php
// Service untuk mengirim email reset password

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
     * Generate dan kirim password reset token
     */
    public function sendPasswordResetEmail($email) {
        try {
            // Cek apakah email terdaftar
            $stmt = $this->db->prepare("SELECT id, username FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if (!$user) {
                return ['success' => false, 'message' => 'Email tidak ditemukan dalam sistem'];
            }

            // Hapus token lama yang belum digunakan untuk email ini
            $stmt = $this->db->prepare("DELETE FROM password_reset_tokens WHERE email = ? AND used = 0");
            $stmt->execute([$email]);

            // Generate reset token yang lebih panjang
            $token = bin2hex(random_bytes(40));
            $expires_at = date('Y-m-d H:i:s', strtotime('+24 hours')); // Extended to 24 hours

            // Simpan token ke database dengan user_id
            $stmt = $this->db->prepare("INSERT INTO password_reset_tokens (user_id, email, token, expires_at, used) VALUES (?, ?, ?, ?, 0)");
            $stmt->execute([$user['id'], $email, $token, $expires_at]);

            // Kirim email dengan URL yang sesuai untuk produksi
            $subject = 'Reset Password - Kalkulator HPP';
            $resetLink = "https://" . $_SERVER['HTTP_HOST'] . "/cornerbites-sia/auth/reset_password.php?token=" . $token;

            $body = $this->getPasswordResetTemplate($user['username'], $resetLink);

            $result = $this->sendEmail($email, $user['username'], $subject, $body);

            if ($result['success']) {
                error_log("Password reset email sent successfully for user: " . $user['username'] . " to email: " . $email);
                error_log("Reset token: " . $token);
                error_log("Reset link: " . $resetLink);
            }

            return $result;

        } catch (Exception $e) {
            error_log("Error sending password reset email: " . $e->getMessage());
            return ['success' => false, 'message' => 'Gagal mengirim email reset password'];
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
            $updateStmt = $this->db->prepare("UPDATE users SET password = ? WHERE id = ?");
            $updateStmt->execute([$hashedPassword, $tokenData['user_id']]);

            // Tandai token sebagai used
            $markUsedStmt = $this->db->prepare("UPDATE password_reset_tokens SET used = 1 WHERE token = ?");
            $markUsedStmt->execute([$token]);

            // Log activity
            $logStmt = $this->db->prepare("
                INSERT INTO activity_logs (user_id, username, activity_type, activity_description, user_agent) 
                VALUES (?, ?, 'password_reset', 'User reset password via email', ?)
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
     * Template email reset password
     */
    private function getResetEmailTemplate($username, $resetLink, $expiryHours) {
        return "
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: #4f46e5; color: white; padding: 20px; text-align: center; }
                .content { background: #f9f9f9; padding: 30px; }
                .button { display: inline-block; background: #4f46e5; color: white; padding: 12px 30px; text-decoration: none; border-radius: 5px; margin: 20px 0; }
                .footer { font-size: 12px; color: #666; margin-top: 30px; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>Reset Password - Kalkulator HPP</h1>
                </div>
                <div class='content'>
                    <h2>Halo, {$username}!</h2>
                    <p>Kami menerima permintaan untuk reset password akun Anda.</p>
                    <p>Klik tombol di bawah ini untuk reset password:</p>
                    <p><a href='{$resetLink}' class='button'>Reset Password</a></p>
                    <p>Atau salin link ini ke browser Anda:</p>
                    <p><code>{$resetLink}</code></p>
                    <p><strong>Penting:</strong></p>
                    <ul>
                        <li>Link ini akan kedaluwarsa dalam {$expiryHours} jam</li>
                        <li>Link hanya bisa digunakan sekali</li>
                        <li>Jika Anda tidak meminta reset password, abaikan email ini</li>
                    </ul>
                </div>
                <div class='footer'>
                    <p>Email ini dikirim otomatis dari sistem Kalkulator HPP.</p>
                    <p>Jangan reply email ini.</p>
                </div>
            </div>
        </body>
        </html>
        ";
    }

    private function getPasswordResetTemplate($username, $resetLink) {
        return "
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 0; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
                .content { background: #f9f9f9; padding: 30px; border-radius: 0 0 10px 10px; }
                .button { display: inline-block; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 15px 30px; text-decoration: none; border-radius: 8px; margin: 20px 0; font-weight: bold; }
                .footer { font-size: 12px; color: #666; margin-top: 30px; text-align: center; }
                .info-box { background: #fff3cd; border: 1px solid #ffeaa7; padding: 15px; border-radius: 5px; margin: 20px 0; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>🔒 Reset Password</h1>
                    <p>Kalkulator HPP - Sistem Informasi Akuntansi</p>
                </div>
                <div class='content'>
                    <h2>Halo, {$username}!</h2>
                    <p>Kami menerima permintaan untuk reset password akun Anda di sistem <strong>Kalkulator HPP</strong>. Untuk keamanan akun Anda, silakan klik tombol di bawah ini untuk membuat password baru.</p>
                    
                    <p style='text-align: center;'>
                        <a href='{$resetLink}' class='button'>🔗 Reset Password Sekarang</a>
                    </p>
                    
                    <p>Atau salin link berikut ke browser Anda:</p>
                    <p style='background: #f5f5f5; padding: 10px; border-radius: 5px; word-break: break-all;'>{$resetLink}</p>
                    
                    <div class='info-box'>
                        <h4>⚠️ Informasi Keamanan:</h4>
                        <ul>
                            <li>Link ini akan kedaluwarsa dalam <strong>24 jam</strong></li>
                            <li>Link hanya bisa digunakan <strong>sekali</strong></li>
                            <li>Jika Anda tidak meminta reset password, <strong>abaikan email ini</strong></li>
                            <li>Untuk keamanan, jangan bagikan link ini kepada siapapun</li>
                        </ul>
                    </div>
                </div>
                <div class='footer'>
                    <p><strong>Kalkulator HPP</strong></p>
                    <p>Email ini dikirim otomatis dari sistem. Jangan membalas email ini.</p>
                    <p>© 2025 Kalkulator HPP. All rights reserved.</p>
                </div>
            </div>
        </body>
        </html>
        ";
    }
}
?>