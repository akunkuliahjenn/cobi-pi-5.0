<?php
// config/email_config.php
// Konfigurasi email untuk forgot password

return [
    'smtp' => [
        'host' => 'smtp.gmail.com', // Untuk Gmail
        'port' => 587,
        'username' => 'akunkuliah.jennieferr293@gmail.com', // Email Gmail Anda
        'password' => 'cacx axhi kgqr hadu',    // App Password Gmail dari Google Account
        'encryption' => 'tls',
        'from_email' => 'akunkuliah.jennieferr293@gmail.com',
        'from_name' => 'Aplikasi Kalkulator HPP System'
    ],
    'settings' => [
        'token_expiry_hours' => 1, // Token expired dalam 1 jam
        'max_reset_attempts' => 3, // Max 3 kali reset per hari
    ]
];
?>