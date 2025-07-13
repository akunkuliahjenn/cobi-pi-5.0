
<?php
// config/email_config.php
// Konfigurasi email untuk forgot password

return [
    'smtp' => [
        'host' => 'smtp.gmail.com', // Untuk Gmail
        'port' => 587,
        'username' => 'your-email@gmail.com', // Ganti dengan email Gmail Anda
        'password' => 'your-app-password',    // App Password Gmail (bukan password biasa!)
        'encryption' => 'tls',
        'from_email' => 'your-email@gmail.com',
        'from_name' => 'Corner Bites SIA'
    ],
    'settings' => [
        'token_expiry_hours' => 1, // Token expired dalam 1 jam
        'max_reset_attempts' => 3, // Max 3 kali reset per hari
    ]
];
?>
