<?php
// database/setup_password_reset.php
// Script untuk setup tabel password reset

require_once __DIR__ . '/../config/db.php';

echo "🔧 Setting up Password Reset Table...\n";

try {
    // Drop tabel lama jika ada
    $db->exec("DROP TABLE IF EXISTS password_resets");
    $db->exec("DROP TABLE IF EXISTS password_reset_tokens");

    // Buat tabel password_reset_tokens yang baru
    $createTable = "
    CREATE TABLE IF NOT EXISTS password_reset_tokens (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        email VARCHAR(255) NOT NULL,
        token VARCHAR(100) NOT NULL UNIQUE,
        expires_at DATETIME NOT NULL,
        used TINYINT(1) DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )";

    $db->exec($createTable);

    // Buat index untuk performa
    $db->exec("CREATE INDEX IF NOT EXISTS idx_token ON password_reset_tokens(token)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_email ON password_reset_tokens(email)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_expires ON password_reset_tokens(expires_at)");

    echo "✅ Tabel password_reset_tokens berhasil dibuat!\n";

    // Verifikasi struktur tabel
    $result = $db->query("PRAGMA table_info(password_reset_tokens)");
    echo "\n📋 Struktur tabel password_reset_tokens:\n";
    while ($row = $result->fetch()) {
        echo "- {$row['name']} ({$row['type']})\n";
    }

    echo "\n🎉 Setup password reset selesai!\n";

} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>