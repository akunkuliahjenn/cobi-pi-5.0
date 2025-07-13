
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
            // Cek apakah email ada di database
            $stmt = $this->db->prepare("SELECT id, username, email FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch();
            
            if (!$user) {
                return ['success' => false, 'message' => 'Email tidak ditemukan dalam sistem.'];
            }
            
            // Generate secure token
            $token = bin2hex(random_bytes(32));
            $expires_at = date('Y-m-d H:i:s', strtotime('+' . $this->config['settings']['token_expiry_hours'] . ' hours'));
            
            // Hapus token lama untuk user ini
            $deleteStmt = $this->db->prepare("DELETE FROM password_reset_tokens WHERE user_id = ?");
            $deleteStmt->execute([$user['id']]);
            
            // Simpan token baru
            $insertStmt = $this->db->prepare("
                INSERT INTO password_reset_tokens (user_id, email, token, expires_at) 
                VALUES (?, ?, ?, ?)
            ");
            $insertStmt->execute([$user['id'], $email, $token, $expires_at]);
            
            // Kirim email
            $resetLink = "http://localhost/cornerbites-sia/auth/reset_password.php?token=" . $token;
            $emailSent = $this->sendEmail(
                $email,
                $user['username'],
                'Reset Password - Corner Bites SIA',
                $this->getResetEmailTemplate($user['username'], $resetLink, $this->config['settings']['token_expiry_hours'])
            );
            
            if ($emailSent) {
                return ['success' => true, 'message' => 'Link reset password telah dikirim ke email Anda.'];
            } else {
                return ['success' => false, 'message' => 'Gagal mengirim email. Silakan coba lagi.'];
            }
            
        } catch (Exception $e) {
            error_log("Password reset email error: " . $e->getMessage());
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
                WHERE prt.token = ? AND prt.expires_at > NOW() AND prt.used = 0
            ");
            $stmt->execute([$token]);
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
            return true;
        } catch (Exception $e) {
            error_log("Email send error: {$mail->ErrorInfo}");
            return false;
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
                    <h1>Reset Password - Corner Bites SIA</h1>
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
                    <p>Email ini dikirim otomatis dari sistem Corner Bites SIA.</p>
                    <p>Jangan reply email ini.</p>
                </div>
            </div>
        </body>
        </html>
        ";
    }
}
?>
