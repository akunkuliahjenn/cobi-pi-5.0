<?php
// process/simpan_overhead.php
// File ini menangani logika penyimpanan/pembaruan overhead dan labor costs

ini_set('display_errors', 1);
error_reporting(E_ALL);

if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../includes/auth_check.php'; // Pastikan user sudah login
require_once __DIR__ . '/../includes/user_middleware.php'; // Sertakan middleware untuk isolasi data
require_once __DIR__ . '/../config/db.php'; // Sertakan file koneksi database

try {
    $conn = $db; // Menggunakan koneksi $db dari db.php

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $type = $_POST['type'] ?? '';

        if ($type === 'overhead') {
            // --- Proses Tambah/Edit Overhead ---
            $overhead_id = $_POST['overhead_id'] ?? null;
            $name = trim($_POST['name'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $amount_input = trim($_POST['amount'] ?? '');
            $amount = (float) str_replace(['.', ','], ['', '.'], $amount_input);
            $allocation_method = trim($_POST['allocation_method'] ?? 'per_batch');
            $estimated_uses = (int) ($_POST['estimated_uses'] ?? 1);

            // Validasi dasar
            if (empty($name) || $amount <= 0 || $estimated_uses <= 0) {
                $_SESSION['overhead_message'] = ['text' => 'Nama, jumlah biaya, dan estimasi pemakaian harus diisi dengan benar.', 'type' => 'error'];
                header("Location: /cornerbites-sia/pages/overhead_management.php");
                exit();
            }

            if ($overhead_id) {
                // Update Overhead menggunakan middleware
                $dataToUpdate = [
                    'name' => $name,
                    'description' => $description,
                    'amount' => $amount,
                    'allocation_method' => $allocation_method,
                    'estimated_uses' => $estimated_uses,
                    'updated_at' => date('Y-m-d H:i:s')
                ];
                $whereClause = 'id = :id';
                $whereParams = [':id' => $overhead_id];

                if (updateWithUserId($conn, 'overhead_costs', $dataToUpdate, $whereClause, $whereParams)) {
                    $_SESSION['overhead_message'] = ['text' => 'Biaya overhead berhasil diperbarui!', 'type' => 'success'];
                } else {
                    $_SESSION['overhead_message'] = ['text' => 'Gagal memperbarui biaya overhead.', 'type' => 'error'];
                }
            } else {
                // Tambah Overhead Baru menggunakan middleware
                $dataToInsert = [
                    'name' => $name,
                    'description' => $description,
                    'amount' => $amount,
                    'allocation_method' => $allocation_method,
                    'estimated_uses' => $estimated_uses,
                    'is_active' => 1,
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s')
                    // user_id akan ditambahkan secara otomatis oleh fungsi insertWithUserId
                ];

                if (insertWithUserId($conn, 'overhead_costs', $dataToInsert)) {
                    $_SESSION['overhead_message'] = ['text' => 'Biaya overhead baru berhasil ditambahkan!', 'type' => 'success'];
                } else {
                    $_SESSION['overhead_message'] = ['text' => 'Gagal menambahkan biaya overhead baru.', 'type' => 'error'];
                }
            }

        } elseif ($type === 'labor') {
            // --- Proses Tambah/Edit Labor ---
            $labor_id = $_POST['labor_id'] ?? null;
            $position_name = trim($_POST['position_name'] ?? '');
            $hourly_rate_input = trim($_POST['hourly_rate'] ?? '');
            $hourly_rate = (float) str_replace(['.', ','], ['', '.'], $hourly_rate_input);

            // Validasi dasar
            if (empty($position_name) || $hourly_rate <= 0) {
                $_SESSION['overhead_message'] = ['text' => 'Nama posisi dan upah per jam harus diisi dengan benar.', 'type' => 'error'];
                header("Location: /cornerbites-sia/pages/overhead_management.php");
                exit();
            }

            if ($labor_id) {
                // Update Labor menggunakan middleware
                $dataToUpdate = [
                    'position_name' => $position_name,
                    'hourly_rate' => $hourly_rate,
                    'updated_at' => date('Y-m-d H:i:s')
                ];
                $whereClause = 'id = :id';
                $whereParams = [':id' => $labor_id];

                if (updateWithUserId($conn, 'labor_costs', $dataToUpdate, $whereClause, $whereParams)) {
                    $_SESSION['overhead_message'] = ['text' => 'Data tenaga kerja berhasil diperbarui!', 'type' => 'success'];
                } else {
                    $_SESSION['overhead_message'] = ['text' => 'Gagal memperbarui data tenaga kerja.', 'type' => 'error'];
                }
            } else {
                // Tambah Labor Baru menggunakan middleware
                $dataToInsert = [
                    'position_name' => $position_name,
                    'hourly_rate' => $hourly_rate,
                    'is_active' => 1,
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s')
                    // user_id akan ditambahkan secara otomatis oleh fungsi insertWithUserId
                ];

                if (insertWithUserId($conn, 'labor_costs', $dataToInsert)) {
                    $_SESSION['overhead_message'] = ['text' => 'Data tenaga kerja baru berhasil ditambahkan!', 'type' => 'success'];
                } else {
                    $_SESSION['overhead_message'] = ['text' => 'Gagal menambahkan data tenaga kerja baru.', 'type' => 'error'];
                }
            }
        }

        header("Location: /cornerbites-sia/pages/overhead_management.php");
        exit();

    } else {
        // Jika diakses langsung tanpa POST yang valid, redirect
        header("Location: /cornerbites-sia/pages/overhead_management.php");
        exit();
    }

} catch (PDOException $e) {
    error_log("Error simpan overhead/labor: " . $e->getMessage());
    $_SESSION['overhead_message'] = ['text' => 'Terjadi kesalahan sistem: ' . $e->getMessage(), 'type' => 'error'];
    header("Location: /cornerbites-sia/pages/overhead_management.php");
    exit();
}
?>