
<?php
// database/create_password_resets_table.php
require_once __DIR__ . '/../config/db.php';

try {
    // Buat tabel password_resets jika belum ada
    $sql = "CREATE TABLE IF NOT EXISTS password_resets (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        email VARCHAR(255) NOT NULL,
        token VARCHAR(255) NOT NULL UNIQUE,
        expires_at DATETIME NOT NULL,
        used TINYINT(1) DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        INDEX idx_token (token),
        INDEX idx_email (email),
        INDEX idx_expires (expires_at)
    )";
    
    $db->exec($sql);
    echo "✅ Tabel password_resets berhasil dibuat!\n";
    
    // Cek apakah ada data di password_reset_tokens yang perlu dipindah
    $checkOldTable = "SHOW TABLES LIKE 'password_reset_tokens'";
    $result = $db->query($checkOldTable);
    
    if ($result && $result->rowCount() > 0) {
        // Pindahkan data dari password_reset_tokens ke password_resets
        $migrateData = "
            INSERT INTO password_resets (user_id, email, token, expires_at, used, created_at)
            SELECT user_id, email, token, expires_at, used, created_at
            FROM password_reset_tokens
            WHERE NOT EXISTS (
                SELECT 1 FROM password_resets WHERE password_resets.token = password_reset_tokens.token
            )
        ";
        
        $db->exec($migrateData);
        echo "✅ Data dari password_reset_tokens berhasil dipindahkan!\n";
    }
    
} catch (PDOException $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>
