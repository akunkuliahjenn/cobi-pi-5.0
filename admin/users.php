<?php
// admin/users.php
// Kelola pengguna - hanya admin yang bisa mengakses

require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../config/db.php';

// Pastikan hanya admin yang bisa mengakses
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: /cornerbites-sia/pages/dashboard.php");
    exit();
}

// Inisialisasi variabel
$users = [];
$totalUsers = 0;
$totalAdmins = 0;
$totalRegularUsers = 0;

// Pengaturan pagination
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$limit = isset($_GET['limit']) ? max(5, min(100, (int)$_GET['limit'])) : 10;
$offset = ($page - 1) * $limit;

// Filter pencarian
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$roleFilter = isset($_GET['role']) ? trim($_GET['role']) : '';

try {
    $conn = $db;

    // Debug: Cek koneksi database
    if (!$conn) {
        throw new Exception("Koneksi database gagal");
    }

    // Test koneksi dengan query sederhana
    $testQuery = $conn->query("SELECT 1");
    if (!$testQuery) {
        throw new Exception("Koneksi database tidak responsif");
    }

    // Statistik umum - ambil dulu sebelum filter
    $stmtTotalUsers = $conn->prepare("SELECT COUNT(*) FROM users");
    $stmtTotalUsers->execute();
    $totalUsers = (int)$stmtTotalUsers->fetchColumn();

    $stmtTotalAdmins = $conn->prepare("SELECT COUNT(*) FROM users WHERE role = 'admin'");
    $stmtTotalAdmins->execute();
    $totalAdmins = (int)$stmtTotalAdmins->fetchColumn();

    $stmtTotalRegularUsers = $conn->prepare("SELECT COUNT(*) FROM users WHERE role = 'user'");
    $stmtTotalRegularUsers->execute();
    $totalRegularUsers = (int)$stmtTotalRegularUsers->fetchColumn();

    // Base query untuk menghitung total dengan filter
    $baseQuery = "SELECT COUNT(*) FROM users WHERE 1=1";
    $params = [];

    // Tambahkan filter pencarian
    if (!empty($search)) {
        $baseQuery .= " AND username LIKE ?";
        $params[] = '%' . $search . '%';
    }

    // Tambahkan filter role
    if (!empty($roleFilter)) {
        $baseQuery .= " AND role = ?";
        $params[] = $roleFilter;
    }

    // Hitung total users dengan filter
    $stmt = $conn->prepare($baseQuery);
    if (!$stmt->execute($params)) {
        throw new Exception("Gagal menjalankan query count users");
    }
    $totalFilteredUsers = (int)$stmt->fetchColumn();

    // Query untuk mendapatkan data users dengan pagination
    $userQuery = "SELECT id, username, role, created_at FROM users WHERE 1=1";
    $userParams = [];

    if (!empty($search)) {
        $userQuery .= " AND username LIKE ?";
        $userParams[] = '%' . $search . '%';
    }

    if (!empty($roleFilter)) {
        $userQuery .= " AND role = ?";
        $userParams[] = $roleFilter;
    }

    $userQuery .= " ORDER BY created_at DESC LIMIT ?, ?";
    $userParams[] = (int)$offset;
    $userParams[] = (int)$limit;

    $stmt = $conn->prepare($userQuery);

    // Bind parameters with explicit types for LIMIT clause
    for ($i = 0; $i < count($userParams); $i++) {
        if ($i >= count($userParams) - 2) { // Last two parameters are for LIMIT
            $stmt->bindValue($i + 1, $userParams[$i], PDO::PARAM_INT);
        } else {
            $stmt->bindValue($i + 1, $userParams[$i], PDO::PARAM_STR);
        }
    }

    if (!$stmt->execute()) {
        throw new Exception("Gagal menjalankan query select users");
    }
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

    error_log("DEBUG: Total users found: " . $totalUsers . ", Admin: " . $totalAdmins . ", Regular: " . $totalRegularUsers);
    error_log("DEBUG: Total filtered users: " . $totalFilteredUsers);
    error_log("DEBUG: Users data count: " . count($users));
    error_log("DEBUG: Search: '$search', Role Filter: '$roleFilter'");

    // Debug tambahan untuk memastikan data users
    if (empty($users)) {
        error_log("WARNING: No users found with current filters");
        // Coba query tanpa filter untuk debug
        $debugStmt = $conn->query("SELECT COUNT(*) FROM users");
        $debugCount = $debugStmt->fetchColumn();
        error_log("DEBUG: Total users without filter: " . $debugCount);
    }

} catch (Exception $e) {
    error_log("Error di Users Admin: " . $e->getMessage());
    error_log("Error trace: " . $e->getTraceAsString());
    $users = [];
    $totalUsers = 0;
    $totalAdmins = 0;
    $totalRegularUsers = 0;
    $totalFilteredUsers = 0;
}

// Hitung total halaman
$totalPages = ceil($totalFilteredUsers / $limit);

// Pesan sukses atau error
$message = '';
$message_type = '';
if (isset($_SESSION['user_management_message'])) {
    $message = $_SESSION['user_management_message']['text'];
    $message_type = $_SESSION['user_management_message']['type'];
    unset($_SESSION['user_management_message']);
}
?>

<?php include_once __DIR__ . '/../includes/header.php'; ?>
<div class="flex h-screen bg-gradient-to-br from-gray-50 to-gray-100 font-sans">
    <?php include_once __DIR__ . '/../includes/sidebar.php'; ?>

    <div class="flex-1 flex flex-col overflow-hidden">
        <header class="bg-white shadow-sm border-b border-gray-200">
            <div class="px-6 py-4">
                <h1 class="text-2xl font-bold text-gray-900">Kelola Pengguna</h1>
                <p class="text-gray-600 mt-1">Manajemen pengguna sistem administrasi</p>
            </div>
        </header>

        <main class="flex-1 overflow-x-hidden overflow-y-auto bg-gradient-to-br from-gray-50 to-gray-100 p-6">
            <div class="max-w-7xl mx-auto space-y-6">

                <?php if ($message): ?>
                    <div class="<?php echo $message_type === 'success' ? 'bg-green-50 border-green-200 text-green-700' : 'bg-red-50 border-red-200 text-red-700'; ?> border rounded-lg p-4 shadow-sm alert-auto-hide">
                        <div class="flex items-center">
                            <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                <?php if ($message_type === 'success'): ?>
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                                <?php else: ?>
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"></path>
                                <?php endif; ?>
                            </svg>
                            <?php echo htmlspecialchars($message); ?>
                        </div>
                    </div>
                <?php endif; ?>



                <!-- Users Management -->
                <div class="bg-white rounded-xl shadow-lg border border-gray-100">
                    <div class="p-6">
                        <div class="flex flex-col md:flex-row md:items-center md:justify-between mb-6">
                            <h2 class="text-xl font-bold text-gray-900 mb-4 md:mb-0">Daftar Pengguna</h2>
                            <div class="flex flex-col md:flex-row space-y-2 md:space-y-0 md:space-x-4">
                                <div class="relative">
                                    <input type="text" id="searchUser" placeholder="Cari pengguna..." 
                                           value="<?php echo htmlspecialchars($search); ?>"
                                           class="w-full md:w-64 pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center">
                                        <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                                        </svg>
                                    </div>
                                </div>
                                <select id="filterRole" class="px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                                    <option value="">Semua Role</option>
                                    <option value="admin" <?php echo $roleFilter === 'admin' ? 'selected' : ''; ?>>Admin</option>
                                    <option value="user" <?php echo $roleFilter === 'user' ? 'selected' : ''; ?>>User</option>
                                </select>
                            </div>
                        </div>

                        <?php if (!empty($users)): ?>
                            <div class="overflow-x-auto">
                                <table class="min-w-full table-auto">
                                    <thead>
                                        <tr class="bg-gray-50">
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Username</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Role</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tanggal Daftar</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-200">
                                        <?php foreach ($users as $user): ?>
                                            <tr class="hover:bg-gray-50">
                                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                                    <?php echo htmlspecialchars($user['id']); ?>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <div class="flex items-center">
                                                        <div class="w-10 h-10 bg-gradient-to-r from-blue-400 to-purple-500 rounded-full flex items-center justify-center mr-3">
                                                            <span class="text-white font-semibold text-sm">
                                                                <?php echo strtoupper(substr($user['username'], 0, 1)); ?>
                                                            </span>
                                                        </div>
                                                        <div>
                                                            <div class="text-sm font-medium text-gray-900">
                                                                <?php echo htmlspecialchars($user['username']); ?>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <span class="px-2 py-1 text-xs font-semibold rounded-full <?php echo $user['role'] === 'admin' ? 'bg-purple-100 text-purple-800' : 'bg-blue-100 text-blue-800'; ?>">
                                                        <?php echo ucfirst($user['role']); ?>
                                                    </span>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                    <?php echo date('d M Y', strtotime($user['created_at'])); ?>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                                    <div class="flex space-x-2">
                                                        <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                                            <button onclick="deleteUser(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['username']); ?>')" 
                                                                    class="text-red-600 hover:text-red-800 bg-red-50 hover:bg-red-100 px-3 py-1 rounded-md transition-colors">
                                                                Hapus
                                                            </button>
                                                        <?php else: ?>
                                                            <span class="text-green-600 text-xs font-medium px-3 py-1 bg-green-100 rounded">
                                                                (Anda)
                                                            </span>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>

                            <!-- Pagination -->
                            <?php if ($totalPages > 1): ?>
                                <div class="bg-white px-6 py-4 border-t border-gray-200">
                                    <div class="flex items-center justify-between">
                                        <div class="flex-1 flex justify-between sm:hidden">
                                            <?php if ($page > 1): ?>
                                                <a href="?page=<?php echo ($page - 1) . ($search ? '&search=' . urlencode($search) : '') . ($roleFilter ? '&role=' . urlencode($roleFilter) : '') . '&limit=' . $limit; ?>" 
                                                   class="relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                                                    Previous
                                                </a>
                                            <?php endif; ?>
                                            <?php if ($page < $totalPages): ?>
                                                <a href="?page=<?php echo ($page + 1) . ($search ? '&search=' . urlencode($search) : '') . ($roleFilter ? '&role=' . urlencode($roleFilter) : '') . '&limit=' . $limit; ?>" 
                                                   class="ml-3 relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                                                    Next
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                        <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
                                            <div>
                                                <p class="text-sm text-gray-700">
                                                    Menampilkan <span class="font-medium"><?php echo (($page - 1) * $limit) + 1; ?></span> sampai 
                                                    <span class="font-medium"><?php echo min($page * $limit, $totalFilteredUsers); ?></span> dari 
                                                    <span class="font-medium"><?php echo $totalFilteredUsers; ?></span> pengguna
                                                </p>
                                            </div>
                                            <div>
                                                <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px">
                                                    <?php if ($page > 1): ?>
                                                        <a href="?page=<?php echo ($page - 1) . ($search ? '&search=' . urlencode($search) : '') . ($roleFilter ? '&role=' . urlencode($roleFilter) : '') . '&limit=' . $limit; ?>" 
                                                           class="relative inline-flex items-center px-2 py-2 rounded-l-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
                                                            <span class="sr-only">Previous</span>
                                                            <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 20 20">
                                                                <path fill-rule="evenodd" d="M12.707 5.293a1 1 0 010 1.414L9.414 10l3.293 3.293a1 1 0 01-1.414 1.414l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 0z" clip-rule="evenodd" />
                                                            </svg>
                                                        </a>
                                                    <?php endif; ?>

                                                    <?php 
                                                    $startPage = max(1, $page - 2);
                                                    $endPage = min($totalPages, $page + 2);
                                                    for ($i = $startPage; $i <= $endPage; $i++):
                                                    ?>
                                                        <a href="?page=<?php echo $i . ($search ? '&search=' . urlencode($search) : '') . ($roleFilter ? '&role=' . urlencode($roleFilter) : '') . '&limit=' . $limit; ?>"
                                                           class="<?php echo $i == $page ? 'z-10 bg-blue-50 border-blue-500 text-blue-600' : 'bg-white border-gray-300 text-gray-500 hover:bg-gray-50'; ?> relative inline-flex items-center px-4 py-2 border text-sm font-medium">
                                                            <?php echo $i; ?>
                                                        </a>
                                                    <?php endfor; ?>

                                                    <?php if ($page < $totalPages): ?>
                                                        <a href="?page=<?php echo ($page + 1) . ($search ? '&search=' . urlencode($search) : '') . ($roleFilter ? '&role=' . urlencode($roleFilter) : '') . '&limit=' . $limit; ?>" 
                                                           class="relative inline-flex items-center px-2 py-2 rounded-r-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
                                                            <span class="sr-only">Next</span>
                                                            <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 20 20">
                                                                <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd" />
                                                            </svg>
                                                        </a>
                                                    <?php endif; ?>
                                                </nav>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>
                        <?php else: ?>
                            <div class="text-center py-12">
                                <svg class="w-16 h-16 text-gray-400 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                                </svg>
                                <h3 class="text-lg font-medium text-gray-900 mb-2">Tidak ada pengguna ditemukan</h3>
                                <p class="text-gray-500 mb-4">
                                    <?php if (!empty($search) || !empty($roleFilter)): ?>
                                        Coba ubah filter pencarian.
                                    <?php else: ?>
                                        Belum ada pengguna yang terdaftar di sistem.
                                    <?php endif; ?>
                                </p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<!-- Modal Add/Edit User -->
<div id="userModal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50">
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="bg-white rounded-xl shadow-2xl w-full max-w-md">
            <div class="p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4" id="modalTitle">Tambah Pengguna</h3>
                <form id="userForm" action="/cornerbites-sia/process/kelola_user.php" method="POST">
                    <input type="hidden" name="user_id" id="user_id_to_edit" value="">
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Username</label>
                            <input type="text" name="username" id="username" required 
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>

                    <div>
                        <label for="email" class="block text-sm font-medium text-gray-700 mb-2">Email</label>
                        <input type="email" id="email" name="email" 
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <p class="text-xs text-gray-500 mt-1">Diperlukan untuk fitur reset password</p>
                    </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Password</label>
                            <input type="password" name="password" id="password" 
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <p class="text-xs text-gray-500 mt-1" id="passwordHelp">Minimal 6 karakter. Kosongkan jika tidak ingin mengubah password.</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Role</label>
                            <select name="role" id="role" required 
                                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                                <option value="user">User</option>
                                <option value="admin">Admin</option>
                            </select>
                        </div>
                    </div>
                    <div class="flex justify-end space-x-3 mt-6">
                        <button type="button" onclick="hideUserModal()" 
                                class="px-4 py-2 text-gray-700 bg-gray-100 hover:bg-gray-200 rounded-lg transition-colors">
                            Batal
                        </button>
                        <button type="submit" 
                                class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg transition-colors">
                            Simpan
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>



<script src="/cornerbites-sia/assets/js/admin.js"></script>
<script src="/cornerbites-sia/assets/js/users.js"></script>

<?php include_once __DIR__ . '/../includes/footer.php'; ?>