
<?php
session_start();
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/user_middleware.php';
require_once __DIR__ . '/../config/db.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        $conn = $db;
        $type = $_POST['type'] ?? '';

        if ($type == 'delete_overhead') {
            $overhead_id = $_POST['overhead_id'] ?? '';
            
            if (empty($overhead_id)) {
                throw new Exception("ID overhead tidak valid!");
            }

            // Soft delete menggunakan middleware
            $dataToUpdate = [
                'is_active' => 0,
                'updated_at' => date('Y-m-d H:i:s')
            ];
            $whereClause = 'id = :id';
            $whereParams = [':id' => $overhead_id];

            if (updateWithUserId($conn, 'overhead_costs', $dataToUpdate, $whereClause, $whereParams)) {
                $_SESSION['overhead_message'] = [
                    'text' => 'Biaya overhead berhasil dihapus!',
                    'type' => 'success'
                ];
                echo json_encode([
                    'success' => true,
                    'message' => 'Biaya overhead berhasil dihapus!'
                ]);
            } else {
                throw new Exception("Data overhead tidak ditemukan!");
            }

        } elseif ($type == 'delete_labor') {
            $labor_id = $_POST['labor_id'] ?? '';
            
            if (empty($labor_id)) {
                throw new Exception("ID tenaga kerja tidak valid!");
            }

            // Soft delete menggunakan middleware
            $dataToUpdate = [
                'is_active' => 0,
                'updated_at' => date('Y-m-d H:i:s')
            ];
            $whereClause = 'id = :id';
            $whereParams = [':id' => $labor_id];

            if (updateWithUserId($conn, 'labor_costs', $dataToUpdate, $whereClause, $whereParams)) {
                $_SESSION['overhead_message'] = [
                    'text' => 'Data tenaga kerja berhasil dihapus!',
                    'type' => 'success'
                ];
                echo json_encode([
                    'success' => true,
                    'message' => 'Data tenaga kerja berhasil dihapus!'
                ]);
            } else {
                throw new Exception("Data tenaga kerja tidak ditemukan!");
            }

        } else {
            throw new Exception("Tipe data tidak valid!");
        }

    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    } catch (PDOException $e) {
        error_log("Database error in hapus_overhead.php: " . $e->getMessage());
        echo json_encode([
            'success' => false,
            'message' => 'Terjadi kesalahan sistem. Silakan coba lagi.'
        ]);
    }
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Method tidak valid!'
    ]);
}
exit;
?>
