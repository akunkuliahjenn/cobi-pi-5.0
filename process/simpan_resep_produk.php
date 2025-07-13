<?php
// process/simpan_resep_produk.php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/user_middleware.php';
require_once __DIR__ . '/../config/db.php';

// Function untuk menghitung HPP berdasarkan resep
function calculateHPPForProduct($conn, $product_id, $production_yield = 1, $production_time_hours = 1) {
    $totalBahanBaku = 0;
    $laborCostPerBatch = 0;
    $overheadCostPerBatch = 0;

    // 1. BIAYA BAHAN BAKU dengan user isolation
    $stmtRecipes = $conn->prepare("
        SELECT pr.quantity_used, rm.purchase_price_per_unit, rm.default_package_quantity, pr.unit_measurement
        FROM product_recipes pr
        JOIN raw_materials rm ON pr.raw_material_id = rm.id
        WHERE pr.product_id = ? AND pr.user_id = ? AND rm.user_id = ?
    ");
    $stmtRecipes->execute([$product_id, $_SESSION['user_id'], $_SESSION['user_id']]);
    $allRecipeItems = $stmtRecipes->fetchAll(PDO::FETCH_ASSOC);

    foreach ($allRecipeItems as $item) {
        if ($item['default_package_quantity'] > 0) {
            $costPerUnit = $item['purchase_price_per_unit'] / $item['default_package_quantity'];
            $costPerItem = $costPerUnit * $item['quantity_used'];
        } else {
            $costPerItem = $item['purchase_price_per_unit'] * $item['quantity_used'];
        }
        $totalBahanBaku += $costPerItem;
    }

    // 2. BIAYA TENAGA KERJA MANUAL dengan user isolation
    $stmtManualLabor = $conn->prepare("
        SELECT plm.total_cost
        FROM product_labor_manual plm
        JOIN labor_costs lc ON plm.labor_id = lc.id
        WHERE plm.product_id = ? AND plm.user_id = ? AND lc.user_id = ? AND lc.is_active = 1
    ");
    $stmtManualLabor->execute([$product_id, $_SESSION['user_id'], $_SESSION['user_id']]);
    $manualLaborCosts = $stmtManualLabor->fetchAll(PDO::FETCH_ASSOC);

    $laborCostPerBatch = 0;
    foreach ($manualLaborCosts as $labor) {
        $laborCostPerBatch += $labor['total_cost'];
    }

    // 3. BIAYA OVERHEAD MANUAL dengan user isolation
    $stmtManualOverhead = $conn->prepare("
        SELECT pom.final_amount
        FROM product_overhead_manual pom
        JOIN overhead_costs oc ON pom.overhead_id = oc.id
        WHERE pom.product_id = ? AND pom.user_id = ? AND oc.user_id = ? AND oc.is_active = 1
    ");
    $stmtManualOverhead->execute([$product_id, $_SESSION['user_id'], $_SESSION['user_id']]);
    $manualOverheadCosts = $stmtManualOverhead->fetchAll(PDO::FETCH_ASSOC);

    $overheadCostPerBatch = 0;
    foreach ($manualOverheadCosts as $overhead) {
        $overheadCostPerBatch += $overhead['final_amount'];
    }

    // 4. TOTAL HPP
    $totalCostPerBatch = $totalBahanBaku + $laborCostPerBatch + $overheadCostPerBatch;
    $hppPerUnit = $production_yield > 0 ? $totalCostPerBatch / $production_yield : 0;

    return $hppPerUnit;
}


try {
    $conn = $db;

    // Handle GET requests (untuk delete dari link)
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $action = $_GET['action'] ?? null;
        $id = $_GET['id'] ?? null;
        $product_id = $_GET['product_id'] ?? null;

        if ($action === 'delete' && $id && $product_id) {
            $stmt = $conn->prepare("DELETE FROM product_recipes WHERE id = ? AND product_id = ?");
            $stmt->execute([$id, $product_id]);

            // Auto-update HPP setelah hapus item
            $productStmt = $conn->prepare("SELECT production_yield, production_time_hours FROM products WHERE id = ?");
            $productStmt->execute([$product_id]);
            $product = $productStmt->fetch(PDO::FETCH_ASSOC);

            if ($product) {
                $hppPerUnit = calculateHPPForProduct($conn, $product_id, $product['production_yield'], $product['production_time_hours']);
                $updateStmt = $conn->prepare("UPDATE products SET cost_price = ? WHERE id = ?");
                $updateStmt->execute([$hppPerUnit, $product_id]);
            }

            $_SESSION['resep_message'] = ['text' => 'Item berhasil dihapus dari resep.', 'type' => 'success'];
            header("Location: ../pages/resep_produk.php?product_id=" . $product_id);
            exit;
        }
    }

    // Handle POST requests
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = $_POST['action'] ?? null;
        $product_id = $_POST['product_id'] ?? null;

        if (!$product_id) {
            throw new Exception('Product ID tidak ditemukan');
        }

        switch ($action) {
            case 'add_bahan':
            case 'add_kemasan':
                $raw_material_id = $_POST['raw_material_id'] ?? null;
                $quantity_used = $_POST['quantity_used'] ?? null;
                $unit_measurement = $_POST['unit_measurement'] ?? null;

                if (!$raw_material_id || !$quantity_used || !$unit_measurement) {
                    throw new Exception('Data tidak lengkap untuk menambah item');
                }

                $checkStmt = $conn->prepare("SELECT id FROM product_recipes WHERE product_id = ? AND raw_material_id = ?");
                $checkStmt->execute([$product_id, $raw_material_id]);
                if ($checkStmt->fetchColumn()) {
                    throw new Exception('Bahan/kemasan ini sudah ada dalam resep. Silakan edit yang sudah ada.');
                }

                $stmt = $conn->prepare("INSERT INTO product_recipes (product_id, raw_material_id, quantity_used, unit_measurement, user_id) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$product_id, $raw_material_id, $quantity_used, $unit_measurement, $_SESSION['user_id']]);

                // Auto-update HPP setelah menambah item
                $productStmt = $conn->prepare("SELECT production_yield, production_time_hours FROM products WHERE id = ?");
                $productStmt->execute([$product_id]);
                $product = $productStmt->fetch(PDO::FETCH_ASSOC);

                if ($product) {
                    $hppPerUnit = calculateHPPForProduct($conn, $product_id, $product['production_yield'], $product['production_time_hours']);
                    $updateStmt = $conn->prepare("UPDATE products SET cost_price = ? WHERE id = ?");
                    $updateStmt->execute([$hppPerUnit, $product_id]);
                }

                $_SESSION['resep_message'] = ['text' => 'Item berhasil ditambahkan ke resep.', 'type' => 'success'];
                break;

            case 'edit':
                $recipe_id = $_POST['recipe_id'] ?? null;
                $raw_material_id = $_POST['raw_material_id'] ?? null;
                $quantity_used = $_POST['quantity_used'] ?? null;
                $unit_measurement = $_POST['unit_measurement'] ?? null;

                if (!$recipe_id || !$raw_material_id || !$quantity_used || !$unit_measurement) {
                    throw new Exception('Data tidak lengkap untuk update resep.');
                }

                 // Get current recipe data to check if material is being changed
                $currentStmt = $conn->prepare("SELECT raw_material_id, quantity_used FROM product_recipes WHERE id = ? AND product_id = ?");
                $currentStmt->execute([$recipe_id, $product_id]);
                $currentRecipe = $currentStmt->fetch(PDO::FETCH_ASSOC);

                if (!$currentRecipe) {
                    throw new Exception('Data resep tidak ditemukan');
                }

                // If raw material is being changed, check for duplicates
                if ($currentRecipe['raw_material_id'] != $raw_material_id) {
                    $checkStmt = $conn->prepare("SELECT id FROM product_recipes WHERE product_id = ? AND raw_material_id = ? AND id != ?");
                    $checkStmt->execute([$product_id, $raw_material_id, $recipe_id]);
                    if ($checkStmt->fetchColumn()) {
                        throw new Exception('Bahan/kemasan ini sudah ada dalam resep. Pilih bahan/kemasan yang berbeda.');
                    }
                }

                $stmt = $conn->prepare("UPDATE product_recipes SET raw_material_id = ?, quantity_used = ?, unit_measurement = ? WHERE id = ? AND product_id = ?");
                $stmt->execute([$raw_material_id, $quantity_used, $unit_measurement, $recipe_id, $product_id]);

                 // Auto-update HPP setelah edit item
                $productStmt = $conn->prepare("SELECT production_yield, production_time_hours FROM products WHERE id = ?");
                $productStmt->execute([$product_id]);
                $product = $productStmt->fetch(PDO::FETCH_ASSOC);

                if ($product) {
                    $hppPerUnit = calculateHPPForProduct($conn, $product_id, $product['production_yield'], $product['production_time_hours']);
                    $updateStmt = $conn->prepare("UPDATE products SET cost_price = ? WHERE id = ?");
                    $updateStmt->execute([$hppPerUnit, $product_id]);
                }

                $_SESSION['resep_message'] = ['text' => 'Item resep berhasil diupdate.', 'type' => 'success'];
                break;

            case 'add_manual_overhead':
                $overhead_id = $_POST['overhead_id'] ?? null;
                if (!$overhead_id) { throw new Exception('ID overhead tidak ditemukan.'); }

                $checkStmt = $conn->prepare("SELECT id FROM product_overhead_manual WHERE product_id = ? AND overhead_id = ?");
                $checkStmt->execute([$product_id, $overhead_id]);
                if ($checkStmt->fetchColumn()) { throw new Exception('Overhead ini sudah ditambahkan.'); }

                $overheadStmt = $conn->prepare("SELECT name, amount, allocation_method FROM overhead_costs WHERE id = ? AND is_active = 1");
                $overheadStmt->execute([$overhead_id]);
                $overhead = $overheadStmt->fetch(PDO::FETCH_ASSOC);
                if (!$overhead) { throw new Exception('Data overhead tidak ditemukan.'); }

                $productStmt = $conn->prepare("SELECT production_yield, production_time_hours FROM products WHERE id = ?");
                $productStmt->execute([$product_id]);
                $product = $productStmt->fetch(PDO::FETCH_ASSOC);

                $productionYield = $product['production_yield'] ?? 1;
                $productionTimeHours = $product['production_time_hours'] ?? 1;

                // Calculate final amount based on allocation method
                $finalAmount = 0;
                $allocationMethod = $overhead['allocation_method'] ?? 'fixed';

                switch ($allocationMethod) {
                    case 'percentage':
                        $finalAmount = $overhead['amount'];
                        break;
                    case 'per_unit':
                        $finalAmount = $overhead['amount'] * $productionYield;
                        break;
                    case 'per_hour':
                        $finalAmount = $overhead['amount'] * $productionTimeHours;
                        break;
                    case 'fixed':
                    default:
                        $finalAmount = $overhead['amount'];
                        break;
                }

                $stmt = $conn->prepare("INSERT INTO product_overhead_manual (product_id, overhead_id, custom_amount, final_amount, user_id) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$product_id, $overhead_id, $overhead['amount'], $finalAmount, $_SESSION['user_id']]);

                // Auto-update HPP setelah edit item
                $productStmt = $conn->prepare("SELECT production_yield, production_time_hours FROM products WHERE id = ?");
                $productStmt->execute([$product_id]);
                $product = $productStmt->fetch(PDO::FETCH_ASSOC);

                if ($product) {
                    $hppPerUnit = calculateHPPForProduct($conn, $product_id, $product['production_yield'], $product['production_time_hours']);
                    $updateStmt = $conn->prepare("UPDATE products SET cost_price = ? WHERE id = ?");
                    $updateStmt->execute([$hppPerUnit, $product_id]);
                }


                $_SESSION['resep_message'] = ['text' => 'Overhead berhasil ditambahkan ke resep.', 'type' => 'success'];
                break;

            case 'add_manual_labor':
                $labor_id = $_POST['labor_id'] ?? null;
                if (!$labor_id) { throw new Exception('ID tenaga kerja tidak ditemukan.'); }

                $checkStmt = $conn->prepare("SELECT id FROM product_labor_manual WHERE product_id = ? AND labor_id = ?");
                $checkStmt->execute([$product_id, $labor_id]);
                if ($checkStmt->fetchColumn()) { throw new Exception('Tenaga kerja ini sudah ditambahkan.'); }

                $laborStmt = $conn->prepare("SELECT hourly_rate FROM labor_costs WHERE id = ? AND is_active = 1");
                $laborStmt->execute([$labor_id]);
                $labor = $laborStmt->fetch(PDO::FETCH_ASSOC);
                if (!$labor) { throw new Exception('Data tenaga kerja tidak ditemukan.'); }

                $productStmt = $conn->prepare("SELECT production_time_hours FROM products WHERE id = ?");
                $productStmt->execute([$product_id]);
                $product = $productStmt->fetch(PDO::FETCH_ASSOC);

                $productionTimeHours = $product['production_time_hours'] ?? 1;
                $totalCost = $labor['hourly_rate'] * $productionTimeHours;

                $stmt = $conn->prepare("INSERT INTO product_labor_manual (product_id, labor_id, custom_hourly_rate, custom_hours, total_cost, user_id) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([$product_id, $labor_id, $labor['hourly_rate'], $productionTimeHours, $totalCost, $_SESSION['user_id']]);

                // Auto-update HPP setelah edit item
                $productStmt = $conn->prepare("SELECT production_yield, production_time_hours FROM products WHERE id = ?");
                $productStmt->execute([$product_id]);
                $product = $productStmt->fetch(PDO::FETCH_ASSOC);

                if ($product) {
                    $hppPerUnit = calculateHPPForProduct($conn, $product_id, $product['production_yield'], $product['production_time_hours']);
                    $updateStmt = $conn->prepare("UPDATE products SET cost_price = ? WHERE id = ?");
                    $updateStmt->execute([$hppPerUnit, $product_id]);
                }

                $_SESSION['resep_message'] = ['text' => 'Tenaga kerja berhasil ditambahkan ke resep.', 'type' => 'success'];
                break;

            case 'delete_recipe':
                $recipe_id = $_POST['recipe_id'] ?? null;
                if (!$recipe_id) { throw new Exception('ID resep tidak ditemukan.'); }

                $stmt = $conn->prepare("DELETE FROM product_recipes WHERE id = ? AND product_id = ?");
                $stmt->execute([$recipe_id, $product_id]);

                // Auto-update HPP setelah hapus item
                $productStmt = $conn->prepare("SELECT production_yield, production_time_hours FROM products WHERE id = ?");
                $productStmt->execute([$product_id]);
                $product = $productStmt->fetch(PDO::FETCH_ASSOC);

                if ($product) {
                    $hppPerUnit = calculateHPPForProduct($conn, $product_id, $product['production_yield'], $product['production_time_hours']);
                    $updateStmt = $conn->prepare("UPDATE products SET cost_price = ? WHERE id = ?");
                    $updateStmt->execute([$hppPerUnit, $product_id]);
                }


                $_SESSION['resep_message'] = ['text' => 'Item berhasil dihapus dari resep.', 'type' => 'success'];
                break;

            case 'update_product_info':
                $production_yield = $_POST['production_yield'] ?? 1;
                $production_time_hours = $_POST['production_time_hours'] ?? 1;
                $sale_price = $_POST['sale_price'] ?? 0;

                 // Hitung HPP terbaru setelah update info produk
                $hppPerUnit = calculateHPPForProduct($conn, $product_id, $production_yield, $production_time_hours);

                // Update product dengan cost_price yang baru dihitung
                $stmt = $conn->prepare("UPDATE products SET production_yield = ?, production_time_hours = ?, sale_price = ?, cost_price = ? WHERE id = ?");
                $stmt->execute([$production_yield, $production_time_hours, $sale_price, $hppPerUnit, $product_id]);

                $_SESSION['resep_message'] = ['text' => 'Informasi produk berhasil diupdate.', 'type' => 'success'];
                break;

            case 'delete_manual_overhead':
                $overhead_manual_id = $_POST['overhead_manual_id'] ?? null;
                if (!$overhead_manual_id) { throw new Exception('ID overhead manual tidak ditemukan.'); }

                $stmt = $conn->prepare("DELETE FROM product_overhead_manual WHERE id = ? AND product_id = ?");
                $stmt->execute([$overhead_manual_id, $product_id]);

                // Auto-update HPP setelah edit item
                $productStmt = $conn->prepare("SELECT production_yield, production_time_hours FROM products WHERE id = ?");
                $productStmt->execute([$product_id]);
                $product = $productStmt->fetch(PDO::FETCH_ASSOC);

                if ($product) {
                    $hppPerUnit = calculateHPPForProduct($conn, $product_id, $product['production_yield'], $product['production_time_hours']);
                    $updateStmt = $conn->prepare("UPDATE products SET cost_price = ? WHERE id = ?");
                    $updateStmt->execute([$hppPerUnit, $product_id]);
                }

                $_SESSION['resep_message'] = ['text' => 'Overhead berhasil dihapus dari resep.', 'type' => 'success'];
                break;

            case 'delete_manual_labor':
                $labor_manual_id = $_POST['labor_manual_id'] ?? null;
                if (!$labor_manual_id) { throw new Exception('ID tenaga kerja manual tidak ditemukan.'); }

                $stmt = $conn->prepare("DELETE FROM product_labor_manual WHERE id = ? AND product_id = ?");
                $stmt->execute([$labor_manual_id, $product_id]);

                // Auto-update HPP setelah edit item
                $productStmt = $conn->prepare("SELECT production_yield, production_time_hours FROM products WHERE id = ?");
                $productStmt->execute([$product_id]);
                $product = $productStmt->fetch(PDO::FETCH_ASSOC);

                if ($product) {
                    $hppPerUnit = calculateHPPForProduct($conn, $product_id, $product['production_yield'], $product['production_time_hours']);
                    $updateStmt = $conn->prepare("UPDATE products SET cost_price = ? WHERE id = ?");
                    $updateStmt->execute([$hppPerUnit, $product_id]);
                }

                $_SESSION['resep_message'] = ['text' => 'Tenaga kerja berhasil dihapus dari resep.', 'type' => 'success'];
                break;

            default:
                throw new Exception('Action tidak valid');
        }

        // Redirect kembali ke halaman resep
        header("Location: ../pages/resep_produk.php?product_id=" . $product_id);
        exit;
    }

} catch (PDOException $e) {
    error_log("Database error in simpan_resep_produk.php: " . $e->getMessage());
    $_SESSION['resep_message'] = ['text' => 'Terjadi kesalahan database: ' . $e->getMessage(), 'type' => 'error'];
} catch (Exception $e) {
    error_log("General error in simpan_resep_produk.php: " . $e->getMessage());
    $_SESSION['resep_message'] = ['text' => $e->getMessage(), 'type' => 'error'];
}

// Fallback redirect jika terjadi error sebelum product_id di-set
$redirect_product_id = isset($product_id) ? $product_id : '';
header("Location: ../pages/resep_produk.php" . ($redirect_product_id ? '?product_id=' . $redirect_product_id : ''));
exit;

?>