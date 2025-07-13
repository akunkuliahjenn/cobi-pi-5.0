<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../config/db.php';

if ($_SESSION['user_role'] !== 'admin') {
    header("Location: /cornerbites-sia/pages/dashboard.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $user_id = $_POST['user_id'];
    $new_password = $_POST['new_password'];

    // Prevent admin from resetting their own password
    if ($user_id == $_SESSION['user_id']) {
        $_SESSION['reset_message'] = ['text' => 'Anda tidak dapat mereset password sendiri.', 'type' => 'error'];
        header("Location: /cornerbites-sia/admin/users.php");
        exit();
    }

    // Validate new password
    if (empty($new_password) || strlen($new_password) < 6) {
        $_SESSION['reset_message'] = ['text' => 'Password temporary minimal 6 karakter.', 'type' => 'error'];
        header("Location: /cornerbites-sia/admin/users.php");
        exit();
    }

    try {
        // Get user info first
        $userStmt = $db->prepare("SELECT username FROM users WHERE id = ?");
        $userStmt->execute([$user_id]);
        $user_info = $userStmt->fetch();

        if (!$user_info) {
            $_SESSION['reset_message'] = ['text' => 'User tidak ditemukan.', 'type' => 'error'];
            header("Location: /cornerbites-sia/admin/users.php");
            exit();
        }

        // Hash password dengan method yang sama seperti debug
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

        // Update password and force change - pastikan query sama dengan debug
        $stmt = $db->prepare("UPDATE users SET password = ?, must_change_password = 1 WHERE id = ?");
        $result = $stmt->execute([$hashed_password, $user_id]);

        if ($result) {
            // Verify the update worked - cek dengan metode yang sama seperti debug
            $verifyStmt = $db->prepare("SELECT password, must_change_password FROM users WHERE id = ?");
            $verifyStmt->execute([$user_id]);
            $updated_user = $verifyStmt->fetch();

            // Test password verification
            $verify_test = password_verify($new_password, $updated_user['password']);

            if (!$updated_user || !$verify_test) {
                $_SESSION['reset_message'] = ['text' => 'Gagal memperbarui password. Verification test failed.', 'type' => 'error'];
                header("Location: /cornerbites-sia/admin/users.php");
                exit();
            }

            // Log reset password activity
            require_once __DIR__ . '/../includes/activity_logger.php';
            logActivity($_SESSION['user_id'], $_SESSION['username'], 'reset_password', 'Admin mereset password untuk User ' . $user_info['username'], $db);

            // Password berhasil direset
            $_SESSION['reset_message'] = [
                'text' => "Password berhasil direset untuk user: {$user_info['username']}. Password sementara: <strong>{$new_password}</strong> (User harus login dan ganti password)", 
                'type' => 'success'
            ];
        } else {
            $_SESSION['reset_message'] = ['text' => 'Gagal memperbarui password di database.', 'type' => 'error'];
        }
    } catch (PDOException $e) {
        $_SESSION['reset_message'] = ['text' => 'Error: ' . $e->getMessage(), 'type' => 'error'];
    }

    header("Location: /cornerbites-sia/admin/users.php");
    exit();
}
?>
