
<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/user_middleware.php';

// Handle AJAX request for ranking pagination with search
if (isset($_GET['ajax']) && $_GET['ajax'] === 'ranking') {
    $ranking_page = isset($_GET['ranking_page']) ? max((int)$_GET['ranking_page'], 1) : 1;
    $ranking_limit = isset($_GET['ranking_limit']) ? max((int)$_GET['ranking_limit'], 5) : 5;
    $ranking_offset = ($ranking_page - 1) * $ranking_limit;
    $search_ranking = isset($_GET['search_ranking']) ? trim($_GET['search_ranking']) : '';

    try {
        $conn = $db;

        // Build search condition dengan user isolation
        $where_condition = "cost_price > 0 AND cost_price IS NOT NULL";
        $params = [];

        if (!empty($search_ranking)) {
            $where_condition .= " AND name LIKE :search";
            $params['search'] = '%' . $search_ranking . '%';
        }

        // Count total products for pagination dengan user isolation
        $total_products_ranking = countWithUserId($conn, 'products', $where_condition, $params);
        $total_ranking_pages = ceil($total_products_ranking / $ranking_limit);

        // Get ranking data dengan user isolation
        $ranking_stmt = selectWithUserId($conn, 'products', 
            'name, cost_price, 
             COALESCE(sale_price, 0) as sale_price, 
             (COALESCE(sale_price, 0) - cost_price) as profit, 
             CASE 
               WHEN COALESCE(sale_price, 0) > 0 THEN ((COALESCE(sale_price, 0) - cost_price) / COALESCE(sale_price, 0) * 100)
               ELSE 0 
             END as margin,
             CASE 
               WHEN COALESCE(sale_price, 0) = 0 THEN \'Belum Set Harga\'
               WHEN COALESCE(sale_price, 0) > cost_price THEN \'Menguntungkan\' 
               ELSE \'Rugi\' 
             END as status', 
            $where_condition, 
            $params, 
            'cost_price DESC', 
            "$ranking_offset, $ranking_limit"
        );
        $profitabilityRanking = $ranking_stmt->fetchAll(PDO::FETCH_ASSOC);

        ob_start();
        ?>
        <div class="mb-6 grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Pencarian</label>
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                        </svg>
                    </div>
                    <input type="text" id="search_ranking_input" 
                           placeholder="Cari nama produk..." 
                           value="<?php echo htmlspecialchars($search_ranking); ?>"
                           onkeyup="searchRanking(this.value)"
                           class="block w-full pl-10 pr-3 py-3 border border-gray-300 rounded-lg leading-5 bg-white placeholder-gray-500 focus:outline-none focus:placeholder-gray-400 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm transition-colors">
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Per Halaman</label>
                <select id="ranking_limit_select" onchange="updateRankingLimit(this.value)" 
                        class="block w-full px-3 py-3 border border-gray-300 rounded-lg leading-5 bg-white focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm transition-colors">
                    <option value="5" <?php echo $ranking_limit == 5 ? 'selected' : ''; ?>>5</option>
                    <option value="10" <?php echo $ranking_limit == 10 ? 'selected' : ''; ?>>10</option>
                    <option value="25" <?php echo $ranking_limit == 25 ? 'selected' : ''; ?>>25</option>
                    <option value="50" <?php echo $ranking_limit == 50 ? 'selected' : ''; ?>>50</option>
                </select>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full">
                <thead>
                    <tr class="bg-gray-50">
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Rank</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nama Produk</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">HPP per Unit</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Harga Jual</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Profit per Unit</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Margin (%)</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php if (!empty($profitabilityRanking)): ?>
                        <?php foreach ($profitabilityRanking as $index => $product): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                    <span class="inline-flex items-center justify-center w-8 h-8 bg-yellow-100 text-yellow-800 rounded-full text-xs font-bold">
                                        <?php echo (($ranking_page - 1) * $ranking_limit) + $index + 1; ?>
                                    </span>
                                </td>
                                <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-900 font-medium"><?php echo htmlspecialchars($product['name']); ?></td>
                                <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-900">Rp <?php echo number_format($product['cost_price'], 0, ',', '.'); ?></td>
                                <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-900">
                                    <?php if ($product['sale_price'] > 0): ?>
                                        Rp <?php echo number_format($product['sale_price'], 0, ',', '.'); ?>
                                    <?php else: ?>
                                        <span class="text-gray-400 italic">Belum diset</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-4 py-4 whitespace-nowrap text-sm font-semibold <?php echo $product['profit'] >= 0 ? 'text-green-600' : 'text-red-600'; ?>">
                                    <?php if ($product['sale_price'] > 0): ?>
                                        Rp <?php echo number_format($product['profit'], 0, ',', '.'); ?>
                                    <?php else: ?>
                                        <span class="text-gray-400">-</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-4 py-4 whitespace-nowrap text-sm font-semibold <?php echo $product['margin'] >= 15 ? 'text-green-600' : ($product['margin'] >= 5 ? 'text-yellow-600' : 'text-red-600'); ?>">
                                    <?php if ($product['sale_price'] > 0): ?>
                                        <?php echo number_format($product['margin'], 1); ?>%
                                    <?php else: ?>
                                        <span class="text-gray-400">-</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-4 py-4 whitespace-nowrap">
                                    <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full <?php echo $product['status'] == 'Menguntungkan' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                                        <?php echo $product['status']; ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="px-4 py-8 text-center text-gray-500">
                                <?php echo !empty($search_ranking) ? 'Tidak ada produk yang ditemukan dengan pencarian "' . htmlspecialchars($search_ranking) . '"' : 'Belum ada produk dengan HPP yang sudah dihitung'; ?>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination for Ranking -->
        <?php if ($total_ranking_pages > 1): ?>
        <div class="bg-white px-6 py-4 border-t border-gray-200">
            <div class="flex items-center justify-between">
                <div class="flex-1 flex justify-between sm:hidden">
                    <?php if ($ranking_page > 1): ?>
                        <button onclick="loadRankingData(<?php echo $ranking_page - 1; ?>)" 
                               class="relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">Previous</button>
                    <?php endif; ?>
                    <?php if ($ranking_page < $total_ranking_pages): ?>
                        <button onclick="loadRankingData(<?php echo $ranking_page + 1; ?>)"
                               class="ml-3 relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">Next</button>
                    <?php endif; ?>
                </div>
                <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
                    <div>
                        <p class="text-sm text-gray-700">
                            Menampilkan <span class="font-medium"><?php echo number_format((($ranking_page - 1) * $ranking_limit) + 1); ?></span> sampai 
                            <span class="font-medium"><?php echo number_format(min($ranking_page * $ranking_limit, $total_products_ranking)); ?></span> dari 
                            <span class="font-medium"><?php echo number_format($total_products_ranking); ?></span> produk
                            <?php if (!empty($search_ranking)): ?>
                                <span class="text-blue-600">(hasil pencarian)</span>
                            <?php endif; ?>
                        </p>
                    </div>
                    <div>
                        <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px" aria-label="Pagination">
                            <?php if ($ranking_page > 1): ?>
                                <button onclick="loadRankingData(<?php echo $ranking_page - 1; ?>)" 
                                       class="relative inline-flex items-center px-2 py-2 rounded-l-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
                                    <span class="sr-only">Previous</span>
                                    <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M12.707 5.293a1 1 0 010 1.414L9.414 10l3.293 3.293a1 1 0 01-1.414 1.414l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 0z" clip-rule="evenodd" />
                                    </svg>
                                </button>
                            <?php endif; ?>

                            <?php 
                            $startPage = max(1, $ranking_page - 2);
                            $endPage = min($total_ranking_pages, $ranking_page + 2);
                            for ($i = $startPage; $i <= $endPage; $i++):
                            ?>
                                <button onclick="loadRankingData(<?php echo $i; ?>)"
                                       class="<?php echo $i == $ranking_page ? 'z-10 bg-blue-50 border-blue-500 text-blue-600' : 'bg-white border-gray-300 text-gray-500 hover:bg-gray-50'; ?> relative inline-flex items-center px-4 py-2 border text-sm font-medium">
                                    <?php echo $i; ?>
                                </button>
                            <?php endfor; ?>

                            <?php if ($ranking_page < $total_ranking_pages): ?>
                                <button onclick="loadRankingData(<?php echo $ranking_page + 1; ?>)"
                                       class="relative inline-flex items-center px-2 py-2 rounded-r-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
                                    <span class="sr-only">Next</span>
                                    <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd" />
                                    </svg>
                                </button>
                            <?php endif; ?>
                        </nav>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
        <?php
        $content = ob_get_clean();
        echo $content;
        exit;
    } catch (Exception $e) {
        echo '<div class="text-center text-red-500 py-8">Error loading data: ' . htmlspecialchars($e->getMessage()) . '</div>';
        exit;
    }
}

// Initialize variables with default values
$totalProducts = 0;
$totalRawMaterials = 0;
$totalBahanBaku = 0;
$totalKemasan = 0;
$totalLaborPositions = 0;
$totalOverheadItems = 0;
$totalLaborCost = 0;
$totalOverheadCost = 0;
$totalRecipes = 0;
$avgHPP = 0;
$avgMargin = 0;
$profitableProducts = 0;
$profitabilityRanking = [];
$total_products_ranking = 0;
$total_ranking_pages = 0;

try {
    $conn = $db;

    // Total Products dengan user isolation
    $totalProducts = countWithUserId($conn, 'products');

    // Total Raw Materials (Bahan Baku + Kemasan) dengan user isolation
    $totalRawMaterials = countWithUserId($conn, 'raw_materials');

    // Total Bahan Baku only dengan user isolation
    $totalBahanBaku = countWithUserId($conn, 'raw_materials', 'type = :type', ['type' => 'bahan']);

    // Total Kemasan only dengan user isolation
    $totalKemasan = countWithUserId($conn, 'raw_materials', 'type = :type', ['type' => 'kemasan']);

    // Total Recipes Active (products that have recipes) dengan user isolation
    $stmt = $conn->prepare("SELECT COUNT(DISTINCT pr.product_id) as total FROM product_recipes pr 
                           JOIN products p ON pr.product_id = p.id 
                           WHERE p.user_id = :user_id");
    $stmt->execute(['user_id' => $_SESSION['user_id']]);
    $result = $stmt->fetch();
    $totalRecipes = $result ? ($result['total'] ?? 0) : 0;

    // Total Labor Positions and Cost (hanya yang aktif) dengan user isolation
    try {
        $stmt = $conn->query("SHOW TABLES LIKE 'labor_costs'");
        if ($stmt->rowCount() > 0) {
            $totalLaborPositions = countWithUserId($conn, 'labor_costs', 'is_active = 1');
            
            $stmt = selectWithUserId($conn, 'labor_costs', 'COALESCE(SUM(hourly_rate), 0) as total_cost', 'is_active = 1');
            $result = $stmt->fetch();
            $totalLaborCost = $result ? ($result['total_cost'] ?? 0) : 0;
        } else {
            $totalLaborPositions = 0;
            $totalLaborCost = 0;
        }
    } catch (PDOException $e) {
        error_log("Error labor query: " . $e->getMessage());
        $totalLaborPositions = 0;
        $totalLaborCost = 0;
    }

    // Total Overhead Items and Cost (hanya yang aktif) dengan user isolation
    try {
        $stmt = $conn->query("SHOW TABLES LIKE 'overhead_costs'");
        if ($stmt->rowCount() > 0) {
            $totalOverheadItems = countWithUserId($conn, 'overhead_costs', 'is_active = 1');
            
            $stmt = selectWithUserId($conn, 'overhead_costs', 'COALESCE(SUM(amount), 0) as total_cost', 'is_active = 1');
            $result = $stmt->fetch();
            $totalOverheadCost = $result ? ($result['total_cost'] ?? 0) : 0;
        } else {
            $totalOverheadItems = 0;
            $totalOverheadCost = 0;
        }
    } catch (PDOException $e) {
        error_log("Error overhead query: " . $e->getMessage());
        $totalOverheadItems = 0;
        $totalOverheadCost = 0;
    }

    // Calculate Average HPP (only products with calculated HPP) dengan user isolation
    $stmt = selectWithUserId($conn, 'products', 'AVG(cost_price) as avg_hpp', 'cost_price > 0 AND cost_price IS NOT NULL');
    $result = $stmt->fetch();
    $avgHPP = $result ? ($result['avg_hpp'] ?? 0) : 0;

    // Calculate Average Margin (only products with both cost and sale price) dengan user isolation
    $stmt = selectWithUserId($conn, 'products', 'AVG(((sale_price - cost_price) / sale_price) * 100) as avg_margin', 'sale_price > 0 AND cost_price > 0 AND sale_price IS NOT NULL AND cost_price IS NOT NULL');
    $result = $stmt->fetch();
    $avgMargin = $result ? ($result['avg_margin'] ?? 0) : 0;

    // Count Profitable Products (profit > 0) dengan user isolation
    $profitableProducts = countWithUserId($conn, 'products', 'sale_price > cost_price AND sale_price > 0 AND cost_price > 0 AND sale_price IS NOT NULL AND cost_price IS NOT NULL');

    // Pagination for profitability ranking
    $ranking_page = isset($_GET['ranking_page']) ? max((int)$_GET['ranking_page'], 1) : 1;
    $ranking_limit = 10;
    $ranking_offset = ($ranking_page - 1) * $ranking_limit;

    // Count total products with HPP for pagination dengan user isolation
    $total_products_ranking = countWithUserId($conn, 'products', 'cost_price > 0 AND cost_price IS NOT NULL');
    $total_ranking_pages = ceil($total_products_ranking / $ranking_limit);

    // Profitability Ranking with pagination (products with calculated HPP) dengan user isolation
    if ($total_products_ranking > 0) {
        $stmt = selectWithUserId($conn, 'products', 
            'name, cost_price, 
             COALESCE(sale_price, 0) as sale_price, 
             (COALESCE(sale_price, 0) - cost_price) as profit, 
             CASE 
               WHEN COALESCE(sale_price, 0) > 0 THEN ((COALESCE(sale_price, 0) - cost_price) / COALESCE(sale_price, 0) * 100)
               ELSE 0 
             END as margin,
             CASE 
               WHEN COALESCE(sale_price, 0) = 0 THEN \'Belum Set Harga\'
               WHEN COALESCE(sale_price, 0) > cost_price THEN \'Menguntungkan\' 
               ELSE \'Rugi\' 
             END as status', 
            'cost_price > 0 AND cost_price IS NOT NULL', 
            [], 
            'cost_price DESC', 
            "$ranking_offset, $ranking_limit"
        );
        $profitabilityRanking = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

} catch (PDOException $e) {
    error_log("Error di Dashboard: " . $e->getMessage());
}
?>

<?php include_once __DIR__ . '/../includes/header.php'; ?>
<div class="flex h-screen bg-gradient-to-br from-gray-50 to-gray-100 font-sans">
    <?php include_once __DIR__ . '/../includes/sidebar.php'; ?>

    <div class="flex-1 flex flex-col overflow-hidden">
        <main class="flex-1 overflow-x-hidden overflow-y-auto bg-gradient-to-br from-gray-50 to-gray-100 p-6">
            <div class="max-w-7xl mx-auto">
                <!-- Header -->
                <div class="mb-8">
                    <h1 class="text-3xl font-bold text-gray-900 mb-2">Dashboard HPP Calculator</h1>
                    <p class="text-gray-600">Analisis Harga Pokok Produksi dengan metode Full Costing</p>
                </div>

                <!-- Main Stats Cards -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                    <!-- Total Products -->
                    <div class="bg-white rounded-xl shadow-lg p-6 border border-gray-100 transform hover:scale-105 transition-transform duration-200">
                        <div class="flex items-center justify-between mb-4">
                            <div class="w-12 h-12 bg-gradient-to-r from-blue-400 to-blue-600 rounded-lg flex items-center justify-center">
                                <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path>
                                </svg>
                            </div>
                            <span class="text-xs font-semibold text-blue-600 bg-blue-100 px-2 py-1 rounded-full">Total Produk</span>
                        </div>
                        <h3 class="text-sm font-semibold text-gray-600 mb-2">Total Produk</h3>
                        <p class="text-3xl font-bold text-gray-800 mb-1"><?php echo number_format($totalProducts); ?></p>
                        <div class="flex items-center text-xs text-gray-500">
                            <svg class="w-3 h-3 mr-1 text-green-500" fill="currentColor" viewBox="0 0 20 20">
                                <path d="M10 12L8 10l1.41-1.41L10 9.17l.59-.58L12 10l-2 2z"/>
                            </svg>
                            <?php echo number_format($totalRecipes); ?> dengan resep
                        </div>
                    </div>

                    <!-- Rata-rata Margin -->
                    <div class="bg-white rounded-xl shadow-lg p-6 border border-gray-100 transform hover:scale-105 transition-transform duration-200">
                        <div class="flex items-center justify-between mb-4">
                            <div class="w-12 h-12 bg-gradient-to-r from-green-400 to-green-600 rounded-lg flex items-center justify-center">
                                <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path>
                                </svg>
                            </div>
                            <span class="text-xs font-semibold text-green-600 bg-green-100 px-2 py-1 rounded-full">Rata-rata Margin</span>
                        </div>
                        <h3 class="text-sm font-semibold text-gray-600 mb-2">Rata-rata Margin</h3>
                        <p class="text-3xl font-bold text-gray-800 mb-1"><?php echo number_format($avgMargin, 1); ?>%</p>
                        <div class="text-xs text-gray-500">
                            <?php if ($avgMargin >= 20): ?>
                                <span class="text-green-600">Sangat baik</span>
                            <?php elseif ($avgMargin >= 15): ?>
                                <span class="text-blue-600">Baik</span>
                            <?php elseif ($avgMargin >= 10): ?>
                                <span class="text-yellow-600">Cukup</span>
                            <?php else: ?>
                                <span class="text-red-600">Perlu peningkatan</span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Rata-rata HPP -->
                    <div class="bg-white rounded-xl shadow-lg p-6 border border-gray-100 transform hover:scale-105 transition-transform duration-200">
                        <div class="flex items-center justify-between mb-4">
                            <div class="w-12 h-12 bg-gradient-to-r from-purple-400 to-purple-600 rounded-lg flex items-center justify-center">
                                <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1"></path>
                                </svg>
                            </div>
                            <span class="text-xs font-semibold text-purple-600 bg-purple-100 px-2 py-1 rounded-full">Rata-rata HPP</span>
                        </div>
                        <h3 class="text-sm font-semibold text-gray-600 mb-2">Rata-rata HPP</h3>
                        <p class="text-3xl font-bold text-gray-800 mb-1">Rp <?php echo number_format($avgHPP, 0, ',', '.'); ?></p>
                        <div class="text-xs text-gray-500">Per unit produk</div>
                    </div>

                    <!-- Produk Menguntungkan -->
                    <div class="bg-white rounded-xl shadow-lg p-6 border border-gray-100 transform hover:scale-105 transition-transform duration-200">
                        <div class="flex items-center justify-between mb-4">
                            <div class="w-12 h-12 bg-gradient-to-r from-yellow-400 to-yellow-600 rounded-lg flex items-center justify-center">
                                <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"></path>
                                </svg>
                            </div>
                            <span class="text-xs font-semibold text-yellow-600 bg-yellow-100 px-2 py-1 rounded-full">Produk Profit</span>
                        </div>
                        <h3 class="text-sm font-semibold text-gray-600 mb-2">Produk Menguntungkan</h3>
                        <p class="text-3xl font-bold text-gray-800 mb-1"><?php echo number_format($profitableProducts); ?></p>
                        <div class="text-xs text-gray-500">dari <?php echo number_format($total_products_ranking); ?> produk</div>
                    </div>
                </div>

                <!-- Secondary Stats Cards -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mb-8">
                    <!-- Total Bahan Baku & Kemasan -->
                    <div class="bg-white rounded-xl shadow-lg p-6 border border-gray-100 transform hover:scale-105 transition-transform duration-200">
                        <div class="flex items-center justify-between mb-4">
                            <div class="w-12 h-12 bg-gradient-to-r from-amber-400 to-amber-600 rounded-lg flex items-center justify-center">
                                <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path>
                                </svg>
                            </div>
                            <span class="text-xs font-semibold text-amber-600 bg-amber-100 px-2 py-1 rounded-full">Bahan & Kemasan</span>
                        </div>
                        <h3 class="text-sm font-semibold text-gray-600 mb-2">Bahan Baku & Kemasan</h3>
                        <p class="text-3xl font-bold text-gray-800 mb-1"><?php echo number_format($totalRawMaterials); ?></p>
                        <div class="text-xs text-gray-500">
                            <?php echo number_format($totalBahanBaku); ?> bahan, <?php echo number_format($totalKemasan); ?> kemasan
                        </div>
                    </div>

                    <!-- Total Tenaga Kerja -->
                    <div class="bg-white rounded-xl shadow-lg p-6 border border-gray-100 transform hover:scale-105 transition-transform duration-200">
                        <div class="flex items-center justify-between mb-4">
                            <div class="w-12 h-12 bg-gradient-to-r from-orange-400 to-orange-600 rounded-lg flex items-center justify-center">
                                <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656-.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                                </svg>
                            </div>
                            <span class="text-xs font-semibold text-orange-600 bg-orange-100 px-2 py-1 rounded-full">Tenaga Kerja</span>
                        </div>
                        <h3 class="text-sm font-semibold text-gray-600 mb-2">Total Tenaga Kerja</h3>
                        <p class="text-3xl font-bold text-gray-800 mb-1"><?php echo number_format($totalLaborPositions); ?></p>
                        <div class="text-xs text-gray-500">Rp <?php echo number_format($totalLaborCost, 0, ',', '.'); ?>/jam</div>
                    </div>

                    <!-- Total Overhead -->
                    <div class="bg-white rounded-xl shadow-lg p-6 border border-gray-100 transform hover:scale-105 transition-transform duration-200">
                        <div class="flex items-center justify-between mb-4">
                            <div class="w-12 h-12 bg-gradient-to-r from-emerald-400 to-emerald-600 rounded-lg flex items-center justify-center">
                                <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                                </svg>
                            </div>
                            <span class="text-xs font-semibold text-emerald-600 bg-emerald-100 px-2 py-1 rounded-full">Overhead</span>
                        </div>
                        <h3 class="text-sm font-semibold text-gray-600 mb-2">Total Overhead</h3>
                        <p class="text-3xl font-bold text-gray-800 mb-1"><?php echo number_format($totalOverheadItems); ?></p>
                        <div class="text-xs text-gray-500">Rp <?php echo number_format($totalOverheadCost, 0, ',', '.'); ?></div>
                    </div>
                </div>

                <!-- Ranking Produk Berdasarkan Margin Keuntungan -->
                <div class="bg-white rounded-xl shadow-lg border border-gray-100 p-6 mb-8">
                    <div class="flex items-center mb-6">
                        <div class="w-8 h-8 bg-yellow-100 rounded-lg flex items-center justify-center mr-3">
                            <svg class="w-5 h-5 text-yellow-600" fill="currentColor" viewBox="0 0 20 20">
                                <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"></path>
                            </svg>
                        </div>
                        <div>
                            <h3 class="text-xl font-bold text-gray-900">Ranking Produk Berdasarkan Margin Keuntungan</h3>
                            <p class="text-sm text-gray-600">Produk dengan margin keuntungan tertinggi berdasarkan HPP</p>
                        </div>
                    </div>

                    <div id="ranking-container">
                        <div class="flex justify-center items-center py-12">
                            <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-yellow-600"></div>
                            <span class="ml-2 text-gray-600">Memuat ranking produk...</span>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<script>
let currentRankingPage = 1;
let currentRankingLimit = 10;
let currentSearchRanking = '';
let searchTimeout;

function loadRankingData(page) {
    currentRankingPage = page;
    
    const container = document.getElementById('ranking-container');
    if (!container) {
        console.error('Ranking container not found');
        return;
    }

    // Show loading
    const loadingHTML = `
        <div class="flex flex-col justify-center items-center py-12">
            <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-yellow-600 mb-3"></div>
            <span class="text-gray-600 text-sm">Memuat ranking produk...</span>
            <div class="mt-2 text-xs text-gray-400">
                ${currentSearchRanking ? `Mencari: "${currentSearchRanking}"` : 'Menampilkan semua produk'}
            </div>
        </div>
    `;
    container.innerHTML = loadingHTML;

    const url = `?ajax=ranking&ranking_page=${page}&ranking_limit=${currentRankingLimit}&search_ranking=${encodeURIComponent(currentSearchRanking)}`;

    fetch(url)
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return response.text();
        })
        .then(html => {
            container.innerHTML = html;
            
            // Re-initialize controls after AJAX load
            const newSearchInput = document.getElementById('search_ranking_input');
            const newLimitSelect = document.getElementById('ranking_limit_select');
            
            if (newSearchInput) {
                newSearchInput.value = currentSearchRanking;
                newSearchInput.addEventListener('keyup', function(e) {
                    searchRanking(this.value);
                });
            }
            
            if (newLimitSelect) {
                newLimitSelect.value = currentRankingLimit;
                newLimitSelect.addEventListener('change', function() {
                    updateRankingLimit(this.value);
                });
            }
        })
        .catch(error => {
            console.error('Error loading ranking data:', error);
            container.innerHTML = `
                <div class="text-center text-red-500 py-8">
                    <div class="mb-2">⚠️ Error loading ranking data</div>
                    <button onclick="loadRankingData(${currentRankingPage})" 
                            class="px-4 py-2 bg-red-100 hover:bg-red-200 text-red-700 rounded-lg text-sm transition-colors">
                        Coba Lagi
                    </button>
                </div>
            `;
        });
}

function updateRankingLimit(limit) {
    currentRankingLimit = parseInt(limit);
    loadRankingData(1);
}

function searchRanking(searchTerm) {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        currentSearchRanking = searchTerm.trim();
        loadRankingData(1);
    }, 300); // Real-time search dengan debounce 300ms
}

// Load initial data
document.addEventListener('DOMContentLoaded', function() {
    loadRankingData(1);
});
</script>

<?php include_once __DIR__ . '/../includes/footer.php'; ?>
