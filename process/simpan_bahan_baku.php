<?php // Removing stock-related features from the backend process.
// process/simpan_bahan_baku.php
// File ini menangani logika penyimpanan/pembaruan/penghapusan bahan baku.

ini_set('display_errors', 1);
error_reporting(E_ALL);

if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../includes/auth_check.php'; // Pastikan user sudah login
require_once __DIR__ . '/../includes/user_middleware.php'; // Sertakan middleware untuk isolasi data
require_once __DIR__ . '/../config/db.php'; // Sertakan file koneksi database

try {
    $conn = $db; // Menggunakan koneksi $db dari db.php

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // --- Proses Tambah/Edit Bahan Baku ---
        $bahan_baku_id = $_POST['bahan_baku_id'] ?? null;
        $name = trim($_POST['name'] ?? '');
        $brand = trim($_POST['brand'] ?? '');
        $type = trim($_POST['type'] ?? 'bahan');
        $unit = trim($_POST['unit'] ?? '');
        $purchase_size = (float) ($_POST['purchase_size'] ?? 0);
        // Clean up price input (remove Indonesian number formatting)
        $purchase_price_input = trim($_POST['purchase_price_per_unit'] ?? '');
        $purchase_price_per_unit = (float) str_replace(['.', ','], ['', '.'], $purchase_price_input);

        // Validasi dasar
        if (empty($name) || empty($unit) || $purchase_size <= 0 || $purchase_price_per_unit <= 0) {
            $_SESSION['bahan_baku_message'] = ['text' => 'Nama, satuan, ukuran beli, dan harga beli per unit (harus > 0) tidak boleh kosong.', 'type' => 'error'];
            header("Location: /cornerbites-sia/pages/bahan_baku.php");
            exit();
        }

        if ($bahan_baku_id) {
            // Update Bahan Baku menggunakan middleware
            $dataToUpdate = [
                'name' => $name,
                'brand' => $brand,
                'type' => $type,
                'unit' => $unit,
                'default_package_quantity' => $purchase_size,
                'purchase_price_per_unit' => $purchase_price_per_unit,
                'updated_at' => date('Y-m-d H:i:s')
            ];
            $whereClause = 'id = :id';
            $whereParams = [':id' => $bahan_baku_id];

            if (updateWithUserId($conn, 'raw_materials', $dataToUpdate, $whereClause, $whereParams)) {
                $_SESSION['bahan_baku_message'] = ['text' => 'Bahan baku berhasil diperbarui!', 'type' => 'success'];
            } else {
                $_SESSION['bahan_baku_message'] = ['text' => 'Gagal memperbarui bahan baku.', 'type' => 'error'];
            }
        } else {
            // Tambah Bahan Baku Baru
            // Cek duplikasi nama dan brand untuk user yang sama menggunakan middleware
            $duplicateCount = countWithUserId($conn, 'raw_materials', 'name = :name AND brand = :brand', [':name' => $name, ':brand' => $brand]);
            if ($duplicateCount > 0) {
                $_SESSION['bahan_baku_message'] = ['text' => 'Anda sudah memiliki bahan baku dengan kombinasi nama dan brand yang sama. Gunakan nama atau brand yang berbeda.', 'type' => 'error'];
                header("Location: /cornerbites-sia/pages/bahan_baku.php");
                exit();
            }

            $dataToInsert = [
                'name' => $name,
                'brand' => $brand,
                'type' => $type,
                'unit' => $unit,
                'default_package_quantity' => $purchase_size,
                'purchase_price_per_unit' => $purchase_price_per_unit
                // user_id akan ditambahkan secara otomatis oleh fungsi insertWithUserId
            ];

            try {
                if (insertWithUserId($conn, 'raw_materials', $dataToInsert)) {
                    $_SESSION['bahan_baku_message'] = ['text' => 'Bahan baku baru berhasil ditambahkan!', 'type' => 'success'];
                } else {
                    $_SESSION['bahan_baku_message'] = ['text' => 'Gagal menambahkan bahan baku baru.', 'type' => 'error'];
                }
            } catch (PDOException $e) {
                // Jika masih ada constraint error, berikan pesan yang lebih jelas
                if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
                    $_SESSION['bahan_baku_message'] = ['text' => 'Database masih memiliki constraint lama. Silakan jalankan script perbaikan database terlebih dahulu.', 'type' => 'error'];
                } else {
                    $_SESSION['bahan_baku_message'] = ['text' => 'Terjadi kesalahan sistem: ' . $e->getMessage(), 'type' => 'error'];
                }
                header("Location: /cornerbites-sia/pages/bahan_baku.php");
                exit();
            }
        }
        // Redirect dengan pesan sukses
        $_SESSION['bahan_baku_message'] = [
            'text' => 'Bahan baku berhasil ditambahkan!',
            'type' => 'success'
        ];

        // Build redirect URL with preserved limit parameters
        $redirectUrl = "../pages/bahan_baku.php";
        $params = [];

        // Preserve limit parameters if they exist in previous request
        if (isset($_POST['bahan_limit']) && !empty($_POST['bahan_limit'])) {
            $params['bahan_limit'] = $_POST['bahan_limit'];
        }
        if (isset($_POST['kemasan_limit']) && !empty($_POST['kemasan_limit'])) {
            $params['kemasan_limit'] = $_POST['kemasan_limit'];
        }

        if (!empty($params)) {
            $redirectUrl .= '?' . http_build_query($params);
        }

        header("Location: $redirectUrl");
        exit();

    } elseif ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
        // --- Proses Hapus Bahan Baku ---
        $bahan_baku_id = (int) $_GET['id'];

        // Cek apakah bahan baku ini digunakan di resep produk mana pun menggunakan middleware
        $recipeCount = countWithUserId($conn, 'product_recipes', 'raw_material_id = :raw_material_id', [':raw_material_id' => $bahan_baku_id]);
        if ($recipeCount > 0) {
            $_SESSION['bahan_baku_message'] = ['text' => 'Tidak bisa menghapus bahan baku karena sudah digunakan dalam resep produk. Hapus resep yang menggunakan bahan baku ini terlebih dahulu.', 'type' => 'error'];
            header("Location: /cornerbites-sia/pages/bahan_baku.php");
            exit();
        }

        $whereClause = 'id = :id';
        $whereParams = [':id' => $bahan_baku_id];
        if (deleteWithUserId($conn, 'raw_materials', $whereClause, $whereParams)) {
            $_SESSION['bahan_baku_message'] = ['text' => 'Bahan baku berhasil dihapus!', 'type' => 'success'];
        } else {
            $_SESSION['bahan_baku_message'] = ['text' => 'Gagal menghapus bahan baku.', 'type' => 'error'];
        }
        header("Location: /cornerbites-sia/pages/bahan_baku.php");
        exit();

    } else {
        // Jika diakses langsung tanpa POST/GET yang valid, redirect
        header("Location: /cornerbites-sia/pages/bahan_baku.php");
        exit();
    }

} catch (PDOException $e) {
    error_log("Error simpan/hapus bahan baku: " . $e->getMessage());
    $_SESSION['bahan_baku_message'] = ['text' => 'Terjadi kesalahan sistem: ' . $e->getMessage(), 'type' => 'error'];
    header("Location: /cornerbites-sia/pages/bahan_baku.php");
    exit();
}