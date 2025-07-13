
<?php
// process/hapus_user.php
// File ini menangani logika penghapusan pengguna oleh admin.

require_once __DIR__ . '/../includes/auth_check.php'; // Pastikan user sudah login dan role admin
require_once __DIR__ . '/../config/db.php'; // Sertakan file koneksi database

// Pastikan hanya admin yang bisa mengakses
if ($_SESSION['user_role'] !== 'admin') {
    $_SESSION['user_management_message'] = ['text' => 'Anda tidak memiliki izin untuk melakukan tindakan ini.', 'type' => 'error'];
    header("Location: /cornerbites-sia/pages/dashboard.php");
    exit();
}

// Proses hapus pengguna
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['user_id'])) {
    $user_id_to_delete = (int) $_POST['user_id'];

    // Validasi ID
    if (empty($user_id_to_delete) || $user_id_to_delete <= 0) {
        $_SESSION['user_management_message'] = ['text' => 'ID pengguna tidak valid.', 'type' => 'error'];
        header("Location: /cornerbites-sia/admin/users.php");
        exit();
    }

    // Cek agar admin tidak menghapus akunnya sendiri
    if ($user_id_to_delete === (int)$_SESSION['user_id']) {
        $_SESSION['user_management_message'] = ['text' => 'Anda tidak bisa menghapus akun Anda sendiri!', 'type' => 'error'];
        header("Location: /cornerbites-sia/admin/users.php");
        exit();
    }

    try {
        $conn = $db;
        $conn->beginTransaction(); // Mulai transaksi database

        // Cek apakah user yang akan dihapus ada
        $stmtCheck = $conn->prepare("SELECT id, username FROM users WHERE id = ?");
        $stmtCheck->execute([$user_id_to_delete]);
        $userToDelete = $stmtCheck->fetch();

        if (!$userToDelete) {
            $_SESSION['user_management_message'] = ['text' => 'Pengguna yang akan dihapus tidak ditemukan.', 'type' => 'error'];
            header("Location: /cornerbites-sia/admin/users.php");
            exit();
        }

        // Hapus atau update semua data terkait pengguna
        // 1. Hapus semua transaksi terkait
        $stmtDeleteTransactions = $conn->prepare("DELETE FROM transactions WHERE user_id = ?");
        $stmtDeleteTransactions->execute([$user_id_to_delete]);

        // 2. Hapus transaction_items yang terkait (jika ada)
        $stmtDeleteTransactionItems = $conn->prepare("DELETE FROM transaction_items WHERE transaction_id IN (SELECT id FROM transactions WHERE user_id = ?)");
        $stmtDeleteTransactionItems->execute([$user_id_to_delete]);

        // 3. Hapus data produk, bahan baku, dan data lainnya
        $tablesToClean = ['products', 'raw_materials', 'product_recipes', 'overhead_costs', 'labor_costs'];
        foreach ($tablesToClean as $table) {
            $stmtClean = $conn->prepare("DELETE FROM `$table` WHERE user_id = ?");
            $stmtClean->execute([$user_id_to_delete]);
        }

        // 4. Hapus pengguna dari database
        $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
        if ($stmt->execute([$user_id_to_delete])) {
            $conn->commit();
            $_SESSION['user_management_message'] = ['text' => 'Pengguna "' . htmlspecialchars($userToDelete['username']) . '" berhasil dihapus beserta semua datanya!', 'type' => 'success'];
        } else {
            $conn->rollBack();
            $_SESSION['user_management_message'] = ['text' => 'Gagal menghapus pengguna.', 'type' => 'error'];
        }

    } catch (PDOException $e) {
        $conn->rollBack();
        error_log("Error hapus user: " . $e->getMessage());
        $_SESSION['user_management_message'] = ['text' => 'Terjadi kesalahan sistem saat menghapus pengguna: ' . $e->getMessage(), 'type' => 'error'];
    }

    header("Location: /cornerbites-sia/admin/users.php");
    exit();

} elseif ($_SERVER['REQUEST_METHOD'] == 'GET' && isset($_GET['id'])) {
    // Backward compatibility untuk metode GET (jika masih ada link yang menggunakan GET)
    $user_id_to_delete = (int) $_GET['id'];

    // Validasi ID
    if (empty($user_id_to_delete) || $user_id_to_delete <= 0) {
        $_SESSION['user_management_message'] = ['text' => 'ID pengguna tidak valid.', 'type' => 'error'];
        header("Location: /cornerbites-sia/admin/users.php");
        exit();
    }

    // Cek agar admin tidak menghapus akunnya sendiri
    if ($user_id_to_delete === (int)$_SESSION['user_id']) {
        $_SESSION['user_management_message'] = ['text' => 'Anda tidak bisa menghapus akun Anda sendiri!', 'type' => 'error'];
        header("Location: /cornerbites-sia/admin/users.php");
        exit();
    }

    try {
        $conn = $db;
        $conn->beginTransaction();

        // Cek apakah user yang akan dihapus ada
        $stmtCheck = $conn->prepare("SELECT id, username FROM users WHERE id = ?");
        $stmtCheck->execute([$user_id_to_delete]);
        $userToDelete = $stmtCheck->fetch();

        if (!$userToDelete) {
            $_SESSION['user_management_message'] = ['text' => 'Pengguna yang akan dihapus tidak ditemukan.', 'type' => 'error'];
            header("Location: /cornerbites-sia/admin/users.php");
            exit();
        }

        // Hapus atau update semua data terkait pengguna
        $stmtDeleteTransactions = $conn->prepare("DELETE FROM transactions WHERE user_id = ?");
        $stmtDeleteTransactions->execute([$user_id_to_delete]);

        // Hapus data dari tabel lainnya
        $tablesToClean = ['products', 'raw_materials', 'product_recipes', 'overhead_costs', 'labor_costs'];
        foreach ($tablesToClean as $table) {
            try {
                $stmtClean = $conn->prepare("DELETE FROM `$table` WHERE user_id = ?");
                $stmtClean->execute([$user_id_to_delete]);
            } catch (PDOException $e) {
                // Abaikan error jika tabel tidak ada
                continue;
            }
        }

        // Hapus pengguna dari database
        $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
        if ($stmt->execute([$user_id_to_delete])) {
            $conn->commit();
            $_SESSION['user_management_message'] = ['text' => 'Pengguna "' . htmlspecialchars($userToDelete['username']) . '" berhasil dihapus!', 'type' => 'success'];
        } else {
            $conn->rollBack();
            $_SESSION['user_management_message'] = ['text' => 'Gagal menghapus pengguna.', 'type' => 'error'];
        }

    } catch (PDOException $e) {
        $conn->rollBack();
        error_log("Error hapus user: " . $e->getMessage());
        $_SESSION['user_management_message'] = ['text' => 'Terjadi kesalahan sistem saat menghapus pengguna.', 'type' => 'error'];
    }

    header("Location: /cornerbites-sia/admin/users.php");
    exit();

} else {
    // Jika diakses tanpa ID atau metode tidak valid
    $_SESSION['user_management_message'] = ['text' => 'Permintaan tidak valid untuk menghapus pengguna.', 'type' => 'error'];
    header("Location: /cornerbites-sia/admin/users.php");
    exit();
}
?>
