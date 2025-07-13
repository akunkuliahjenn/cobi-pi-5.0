<?php
// admin/dashboard.php
// Dashboard admin yang fokus pada statistik pengguna dan manajemen sistem

require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../config/db.php';

// Pastikan hanya admin yang bisa mengakses
if ($_SESSION['user_role'] !== 'admin') {
    header("Location: /cornerbites-sia/pages/dashboard.php");
    exit();
}

// Inisialisasi variabel
$totalUsers = 0;
$totalAdmins = 0;
$totalRegularUsers = 0;
$recentUsers = [];

try {
    $conn = $db;

    // Statistik Pengguna
    $stmt = $conn->query("SELECT id, username, role, created_at FROM users ORDER BY created_at DESC");
    $users = $stmt->fetchAll();

    $totalUsers = count($users);
    $totalAdmins = count(array_filter($users, function($user) { return $user['role'] === 'admin'; }));
    $totalRegularUsers = count(array_filter($users, function($user) { return $user['role'] === 'user'; }));

    // 5 pengguna terbaru
    $recentUsers = array_slice($users, 0, 5);

} catch (PDOException $e) {
    error_log("Error di Admin Dashboard: " . $e->getMessage());
}

// Log aktivitas terbaru
$activityLogs = [];
$userGrowthData = [];
$roleComposition = [];

try {
    // Ambil log aktivitas terbaru dari tabel activity_logs jika ada
    try {
        $stmt = $conn->query("SELECT 
            username, 
            activity_type, 
            activity_description, 
            created_at
            FROM activity_logs 
            ORDER BY created_at DESC 
            LIMIT 10");
        
        $activities = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($activities as $activity) {
            $activityLogs[] = [
                'type' => $activity['activity_type'],
                'message' => $activity['activity_description'],
                'time' => $activity['created_at'],
                'icon' => $activity['activity_type'] === 'login' ? 'login' : 'user-plus'
            ];
        }
    } catch (PDOException $e) {
        // Jika tabel activity_logs belum ada, gunakan data users sebagai fallback
        $stmt = $conn->query("SELECT 
            u.username, 
            u.role, 
            u.created_at, 
            u.last_login_at,
            CASE 
                WHEN u.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 'register'
                WHEN u.last_login_at >= DATE_SUB(NOW(), INTERVAL 1 DAY) THEN 'login'
                ELSE 'active'
            END as activity_type
            FROM users u 
            ORDER BY COALESCE(u.last_login_at, u.created_at) DESC 
            LIMIT 10");

        $activities = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($activities as $activity) {
            $timeAgo = $activity['created_at'];
            if ($activity['activity_type'] == 'register') {
                $activityLogs[] = [
                    'type' => 'register',
                    'message' => 'User ' . $activity['username'] . ' baru saja mendaftar',
                    'time' => $timeAgo,
                    'icon' => 'user-plus'
                ];
            } else if ($activity['activity_type'] == 'login' && $activity['last_login_at']) {
                $activityLogs[] = [
                    'type' => 'login',
                    'message' => 'User ' . $activity['username'] . ' baru saja login',
                    'time' => $activity['last_login_at'],
                    'icon' => 'login'
                ];
            }
        }
    }

    // Data untuk grafik pertumbuhan pengguna (7 hari terakhir)
    for ($i = 6; $i >= 0; $i--) {
        $date = date('Y-m-d', strtotime("-$i days"));
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM users WHERE DATE(created_at) = ?");
        $stmt->execute([$date]);
        $count = $stmt->fetchColumn();

        $userGrowthData[] = [
            'date' => date('M j', strtotime($date)),
            'count' => $count
        ];
    }

    // Data untuk grafik komposisi role
    $roleComposition = [
        'admin' => $totalAdmins,
        'user' => $totalRegularUsers
    ];

} catch (PDOException $e) {
    error_log("Error getting activity logs: " . $e->getMessage());
}

// Pesan sukses atau error
$message = '';
$message_type = '';
if (isset($_SESSION['admin_message'])) {
    $message = $_SESSION['admin_message']['text'];
    $message_type = $_SESSION['admin_message']['type'];
    unset($_SESSION['admin_message']);
}
?>

<?php include_once __DIR__ . '/../includes/header.php'; ?>
<div class="flex h-screen bg-gradient-to-br from-gray-50 to-gray-100 font-sans">
    <?php include_once __DIR__ . '/../includes/sidebar.php'; ?>

    <div class="flex-1 flex flex-col overflow-hidden">
        <header class="bg-white shadow-sm border-b border-gray-200">
            <div class="px-6 py-4">
                <h1 class="text-2xl font-bold text-gray-900">Admin Dashboard</h1>
            </div>
        </header>

        <main class="flex-1 overflow-x-hidden overflow-y-auto bg-gradient-to-br from-gray-50 to-gray-100 p-6">
            <div class="max-w-7xl mx-auto space-y-6">

                <?php if ($message): ?>
                    <div class="<?php echo $message_type === 'success' ? 'bg-green-50 border-green-200 text-green-700' : 'bg-red-50 border-red-200 text-red-700'; ?> border rounded-lg p-4 shadow-sm">
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

                <!-- Cards Statistik Utama -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <!-- Total Pengguna -->
                    <div class="bg-white rounded-xl shadow-lg p-6 border border-gray-100 transform hover:scale-105 transition duration-200 ease-in-out">
                        <div class="flex items-center justify-between">
                            <div>
                                <h3 class="text-lg font-semibold text-gray-700">Total Pengguna</h3>
                                <p class="text-3xl font-bold text-blue-600 mt-2"><?php echo $totalUsers; ?></p>
                                <p class="text-sm text-gray-500 mt-1">Terdaftar di sistem</p>
                            </div>
                            <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center">
                                <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0z"></path>
                                </svg>
                            </div>
                        </div>
                    </div>

                    <!-- Admin -->
                    <div class="bg-white rounded-xl shadow-lg p-6 border border-gray-100 transform hover:scale-105 transition duration-200 ease-in-out">
                        <div class="flex items-center justify-between">
                            <div>
                                <h3 class="text-lg font-semibold text-gray-700">Administrator</h3>
                                <p class="text-3xl font-bold text-purple-600 mt-2"><?php echo $totalAdmins; ?></p>
                                <p class="text-sm text-gray-500 mt-1">Akun admin aktif</p>
                            </div>
                            <div class="w-12 h-12 bg-purple-100 rounded-lg flex items-center justify-center">
                                <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 0 00-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path>
                                </svg>
                            </div>
                        </div>
                    </div>

                    <!-- User Biasa -->
                    <div class="bg-white rounded-xl shadow-lg p-6 border border-gray-100 transform hover:scale-105 transition duration-200 ease-in-out">
                        <div class="flex items-center justify-between">
                            <div>
                                <h3 class="text-lg font-semibold text-gray-700">Pengguna Biasa</h3>
                                <p class="text-3xl font-bold text-green-600 mt-2"><?php echo $totalRegularUsers; ?></p>
                                <p class="text-sm text-gray-500 mt-1">Akun user aktif</p>
                            </div>
                            <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center">
                                <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                                </svg>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Log Aktivitas Terbaru -->
                <div class="bg-white rounded-xl shadow-lg p-6 border border-gray-100">
                    <h3 class="text-xl font-semibold text-gray-800 mb-4">Log Aktivitas Terbaru</h3>
                    <div class="space-y-3 max-h-64 overflow-y-auto">
                        <?php if (!empty($activityLogs)): ?>
                            <?php foreach ($activityLogs as $log): ?>
                                <div class="flex items-center space-x-3 p-3 bg-gray-50 rounded-lg">
                                    <div class="w-8 h-8 bg-blue-100 rounded-lg flex items-center justify-center">
                                        <?php if ($log['icon'] == 'user-plus'): ?>
                                            <svg class="w-4 h-4 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                                            </svg>
                                        <?php else: ?>
                                            <svg class="w-4 h-4 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1"></path>
                                            </svg>
                                        <?php endif; ?>
                                    </div>
                                    <div class="flex-1">
                                        <p class="text-sm text-gray-700"><?php echo htmlspecialchars($log['message']); ?></p>
                                        <p class="text-xs text-gray-500"><?php echo date('d M Y H:i', strtotime($log['time'])); ?></p>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="text-center py-8 text-gray-500">
                                <svg class="w-12 h-12 mx-auto mb-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
                                </svg>
                                <p>Belum ada aktivitas terbaru</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                
            </div>
        </main>
    </div>
</div>


<script src="/cornerbites-sia/assets/js/admin.js"></script>

<?php include_once __DIR__ . '/../includes/footer.php'; ?>