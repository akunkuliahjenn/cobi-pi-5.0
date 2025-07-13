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
     * Generate dan kirim temporary password via email dengan verifikasi username
     */
    public function sendPasswordResetEmailWithUsernameVerification($username, $email) {
        try {
            // Cek apakah kombinasi username dan email cocok
            $stmt = $this->db->prepare("SELECT id, username FROM users WHERE username = ? AND email = ?");
            $stmt->execute([$username, $email]);
            $user = $stmt->fetch();

            if (!$user) {
                return ['success' => false, 'message' => 'Kombinasi username dan email tidak ditemukan atau tidak cocok'];
            }

            // Generate temporary password (8 karakter random)
            $tempPassword = $this->generateTempPassword();
            $hashedTempPassword = password_hash($tempPassword, PASSWORD_DEFAULT);

            // Update user dengan temporary password dan set must_change_password = 1
            $stmt = $this->db->prepare("UPDATE users SET password = ?, must_change_password = 1 WHERE id = ?");
            $stmt->execute([$hashedTempPassword, $user['id']]);

            // Log activity
            $logStmt = $this->db->prepare("
                INSERT INTO activity_logs (user_id, username, activity_type, activity_description, user_agent) 
                VALUES (?, ?, 'password_reset_email', 'Temporary password sent via email with username verification', ?)
            ");
            $logStmt->execute([
                $user['id'], 
                $user['username'], 
                $_SERVER['HTTP_USER_AGENT'] ?? ''
            ]);

            // Kirim email dengan temporary password
            $subject = 'Password Temporary - Kalkulator HPP';
            $body = $this->getTempPasswordEmailTemplate($user['username'], $tempPassword);

            $result = $this->sendEmail($email, $user['username'], $subject, $body);

            if ($result['success']) {
                error_log("Temporary password sent successfully for user: " . $user['username'] . " to email: " . $email);
                error_log("Temporary password: " . $tempPassword);
            }

            return $result;

        } catch (Exception $e) {
            error_log("Error sending temporary password email: " . $e->getMessage());
            return ['success' => false, 'message' => 'Gagal mengirim email password temporary'];
        }
    }

    /**
     * Generate dan kirim temporary password via email (method lama untuk backward compatibility)
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

            // Generate temporary password (8 karakter random)
            $tempPassword = $this->generateTempPassword();
            $hashedTempPassword = password_hash($tempPassword, PASSWORD_DEFAULT);

            // Update user dengan temporary password dan set must_change_password = 1
            $stmt = $this->db->prepare("UPDATE users SET password = ?, must_change_password = 1 WHERE id = ?");
            $stmt->execute([$hashedTempPassword, $user['id']]);

            // Log activity
            $logStmt = $this->db->prepare("
                INSERT INTO activity_logs (user_id, username, activity_type, activity_description, user_agent) 
                VALUES (?, ?, 'password_reset_email', 'Temporary password sent via email', ?)
            ");
            $logStmt->execute([
                $user['id'], 
                $user['username'], 
                $_SERVER['HTTP_USER_AGENT'] ?? ''
            ]);

            // Kirim email dengan temporary password
            $subject = 'Password Temporary - Kalkulator HPP';
            $body = $this->getTempPasswordEmailTemplate($user['username'], $tempPassword);

            $result = $this->sendEmail($email, $user['username'], $subject, $body);

            if ($result['success']) {
                error_log("Temporary password sent successfully for user: " . $user['username'] . " to email: " . $email);
                error_log("Temporary password: " . $tempPassword);
            }

            return $result;

        } catch (Exception $e) {
            error_log("Error sending temporary password email: " . $e->getMessage());
            return ['success' => false, 'message' => 'Gagal mengirim email password temporary'];
        }
    }

    /**
     * Generate temporary password
     */
    private function generateTempPassword($length = 8) {
        $characters = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $tempPassword = '';
        
        for ($i = 0; $i < $length; $i++) {
            $tempPassword .= $characters[rand(0, strlen($characters) - 1)];
        }
        
        return $tempPassword;
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

    private function getTempPasswordEmailTemplate($username, $tempPassword) {
        return "
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 0; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
                .content { background: #f9f9f9; padding: 30px; border-radius: 0 0 10px 10px; }
                .password-box { background: #fff; border: 2px solid #667eea; padding: 20px; border-radius: 8px; margin: 20px 0; text-align: center; }
                .password-text { font-size: 24px; font-weight: bold; color: #667eea; letter-spacing: 3px; margin: 10px 0; }
                .footer { font-size: 12px; color: #666; margin-top: 30px; text-align: center; }
                .info-box { background: #fff3cd; border: 1px solid #ffeaa7; padding: 15px; border-radius: 5px; margin: 20px 0; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>🔑 Password Temporary</h1>
                    <p>Aplikasi Kalkulator HPP - Sistem Kalkulasi Harga Pokok Produksi</p>
                </div>
                <div class='content'>
                    <h2>Halo, {$username}!</h2>
                    <p>Kami telah membuatkan password temporary untuk akun Anda di sistem <strong>Aplikasi Kalkulator HPP</strong>.</p>
                    
                    <div class='password-box'>
                        <p><strong>Password Temporary Anda:</strong></p>
                        <div class='password-text'>{$tempPassword}</div>
                        <p><small>Salin password di atas untuk login</small></p>
                    </div>
                    
                    <div class='info-box'>
                        <h4>📋 Langkah Selanjutnya:</h4>
                        <ol>
                            <li>Login ke sistem menggunakan username dan password temporary di atas</li>
                            <li>Sistem akan meminta Anda mengganti password</li>
                            <li>Buat password baru yang kuat dan mudah Anda ingat</li>
                            <li>Setelah mengganti password, Anda dapat menggunakan sistem seperti biasa</li>
                        </ol>
                    </div>
                    
                    <div class='info-box'>
                        <h4>⚠️ Informasi Keamanan:</h4>
                        <ul>
                            <li>Password temporary ini <strong>hanya untuk sekali login</strong></li>
                            <li>Anda <strong>WAJIB</strong> mengganti password setelah login</li>
                            <li>Jangan bagikan password ini kepada siapapun</li>
                            <li>Jika Anda tidak meminta reset password, segera hubungi administrator</li>
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
}
?>