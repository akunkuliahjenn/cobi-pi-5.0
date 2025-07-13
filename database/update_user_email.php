
<?php
// database/update_user_email.php
// Script untuk update email user yang belum memiliki email

require_once __DIR__ . '/../config/db.php';

try {
    // Update email untuk user testo
    $stmt = $db->prepare("UPDATE users SET email = ? WHERE username = ?");
    $result = $stmt->execute(['akunkuliah.jennieferr293@gmail.com', 'testo']);
    
    if ($result) {
        echo "✅ Email berhasil diupdate untuk user testo\n";
        
        // Verifikasi update
        $verify = $db->prepare("SELECT username, email FROM users WHERE username = ?");
        $verify->execute(['testo']);
        $user = $verify->fetch();
        
        if ($user) {
            echo "Username: " . $user['username'] . "\n";
            echo "Email: " . $user['email'] . "\n";
        }
    } else {
        echo "❌ Gagal mengupdate email\n";
    }
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
