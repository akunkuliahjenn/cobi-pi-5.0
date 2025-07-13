<?php
// process/simpan_produk.php
// File ini menangani logika penyimpanan/pembaruan/penghapusan produk.

// Memulai session jika belum dimulai
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../includes/auth_check.php';       // Pastikan user sudah login
require_once __DIR__ . '/../includes/user_middleware.php'; // (PERUBAHAN) Sertakan middleware untuk isolasi data
require_once __DIR__ . '/../config/db.php';                // Sertakan file koneksi database

try {
    $conn = $db; // Menggunakan koneksi $db dari db.php

    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        // --- Proses Tambah/Edit Produk ---
        $product_id = $_POST['product_id'] ?? null;
        $name = trim($_POST['name'] ?? '');
        $unit_select = trim($_POST['unit'] ?? '');
        $unit_custom = trim($_POST['unit_custom'] ?? '');
        $unit = ($unit_select === 'custom') ? $unit_custom : $unit_select;
        
        // Harga jual default 0, akan diatur di halaman HPP
        $sale_price = 0;

        // Validasi dasar
        if (empty($name) || empty($unit)) {
            $_SESSION['product_message'] = ['text' => 'Nama produk dan satuan harus diisi.', 'type' => 'error'];
            header("Location: /cornerbites-sia/pages/produk.php");
            exit();
        }

        if ($product_id) {
            // --- (PERUBAHAN) Update Produk menggunakan fungsi middleware ---
            $dataToUpdate = ['name' => $name, 'unit' => $unit];
            $whereClause = 'id = :id';
            $whereParams = [':id' => $product_id];

            if (updateWithUserId($conn, 'products', $dataToUpdate, $whereClause, $whereParams)) {
                $_SESSION['product_message'] = ['text' => 'Produk berhasil diperbarui!', 'type' => 'success'];
            } else {
                $_SESSION['product_message'] = ['text' => 'Gagal memperbarui produk atau tidak ada perubahan.', 'type' => 'error'];
            }
        } else {
            // --- (PERUBAHAN) Tambah Produk Baru menggunakan fungsi middleware ---
            $dataToInsert = [
                'name' => $name,
                'unit' => $unit,
                'sale_price' => $sale_price
                // user_id akan ditambahkan secara otomatis oleh fungsi insertWithUserId
            ];

            if (insertWithUserId($conn, 'products', $dataToInsert)) {
                $_SESSION['product_message'] = ['text' => 'Produk baru berhasil ditambahkan! Selanjutnya buat resep di halaman Manajemen Resep & HPP.', 'type' => 'success'];
            } else {
                $_SESSION['product_message'] = ['text' => 'Gagal menambahkan produk baru.', 'type' => 'error'];
            }
        }

        header("Location: /cornerbites-sia/pages/produk.php");
        exit();

    } elseif ($_SERVER['REQUEST_METHOD'] == 'GET' && isset($_GET['action']) && $_GET['action'] == 'delete') {
        // --- (PERUBAHAN) Proses Hapus Produk menggunakan fungsi middleware ---
        $product_id = $_GET['id'] ?? null;

        if (empty($product_id)) {
            $_SESSION['product_message'] = ['text' => 'ID produk tidak ditemukan untuk dihapus.', 'type' => 'error'];
            header("Location: /cornerbites-sia/pages/produk.php");
            exit();
        }

        // Cek apakah produk terkait dengan resep (milik user ini)
        $recipeCount = countWithUserId($conn, 'product_recipes', 'product_id = :product_id', [':product_id' => $product_id]);
        if ($recipeCount > 0) {
            $_SESSION['product_message'] = ['text' => 'Tidak bisa menghapus produk karena sudah memiliki resep. Hapus resep terkait terlebih dahulu.', 'type' => 'error'];
            header("Location: /cornerbites-sia/pages/produk.php");
            exit();
        }
        
        $whereClause = 'id = :id';
        $whereParams = [':id' => $product_id];
        if (deleteWithUserId($conn, 'products', $whereClause, $whereParams)) {
            $_SESSION['product_message'] = ['text' => 'Produk berhasil dihapus!', 'type' => 'success'];
        } else {
            $_SESSION['product_message'] = ['text' => 'Gagal menghapus produk.', 'type' => 'error'];
        }
        header("Location: /cornerbites-sia/pages/produk.php");
        exit();

    } else {
        // Jika diakses langsung tanpa POST/GET yang valid, redirect
        header("Location: /cornerbites-sia/pages/produk.php");
        exit();
    }

} catch (PDOException $e) {
    error_log("Error simpan/hapus produk: " . $e->getMessage());
    $_SESSION['product_message'] = ['text' => 'Terjadi kesalahan sistem: ' . $e->getMessage(), 'type' => 'error'];
    header("Location: /cornerbites-sia/pages/produk.php");
    exit();
}
?>