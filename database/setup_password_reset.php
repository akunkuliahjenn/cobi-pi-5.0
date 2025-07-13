
<?php
// database/setup_password_reset.php
// Script untuk setup tabel password reset

require_once __DIR__ . '/../config/db.php';

echo "🔧 Setting up Password Reset Table...\n\n";

try {
    // SQL untuk membuat tabel password_reset_tokens (SQLite)
    $sql = "
        CREATE TABLE IF NOT EXISTS password_reset_tokens (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            email VARCHAR(255) NOT NULL,
            token VARCHAR(64) NOT NULL UNIQUE,
            expires_at DATETIME NOT NULL,
            used TINYINT(1) DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        );
        
        CREATE INDEX IF NOT EXISTS idx_token ON password_reset_tokens(token);
        CREATE INDEX IF NOT EXISTS idx_email ON password_reset_tokens(email);
        CREATE INDEX IF NOT EXISTS idx_expires ON password_reset_tokens(expires_at);
    ";
    
    // Eksekusi SQL
    $db->exec($sql);
    
    echo "✅ Tabel password_reset_tokens berhasil dibuat!\n\n";
    
    // Cek apakah tabel sudah ada
    $stmt = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='password_reset_tokens'");
    $table = $stmt->fetch();
    
    if ($table) {
        echo "✅ Tabel password_reset_tokens sudah tersedia.\n";
        
        // Cek struktur tabel
        $stmt = $db->query("PRAGMA table_info(password_reset_tokens)");
        $columns = $stmt->fetchAll();
        
        echo "📋 Struktur tabel:\n";
        foreach ($columns as $column) {
            echo "   - {$column['name']} ({$column['type']})\n";
        }
    }
    
    echo "\n🎯 Selanjutnya:\n";
    echo "1. Setup Gmail App Password\n";
    echo "2. Update config/email_config.php dengan App Password\n";
    echo "3. Test email system\n";
    
} catch (PDOException $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>
