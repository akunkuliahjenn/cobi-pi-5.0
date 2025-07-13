
<?php
// admin/debug_users_admin.php
// File debug khusus untuk halaman admin users

ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../config/db.php';

echo "<!DOCTYPE html>";
echo "<html><head><title>Debug Users Admin</title>";
echo "<style>body{font-family:Arial,sans-serif;margin:20px;} .success{color:green;} .error{color:red;} .info{color:blue;}</style>";
echo "</head><body>";

echo "<h1>🔍 Debug Users Admin Page</h1>";

try {
    $conn = $db;
    
    echo "<h2>Database Connection Test</h2>";
    if ($conn) {
        echo "<p class='success'>✓ Database connection successful</p>";
        
        // Test basic query
        $testStmt = $conn->query("SELECT 1 as test");
        $testResult = $testStmt->fetch();
        echo "<p class='success'>✓ Basic query works: " . $testResult['test'] . "</p>";
        
        // Test users table
        echo "<h2>Users Table Test</h2>";
        $usersCount = $conn->query("SELECT COUNT(*) FROM users")->fetchColumn();
        echo "<p class='info'>Total users in database: " . $usersCount . "</p>";
        
        $adminsCount = $conn->query("SELECT COUNT(*) FROM users WHERE role = 'admin'")->fetchColumn();
        echo "<p class='info'>Total admins: " . $adminsCount . "</p>";
        
        $regularCount = $conn->query("SELECT COUNT(*) FROM users WHERE role = 'user'")->fetchColumn();
        echo "<p class='info'>Total regular users: " . $regularCount . "</p>";
        
        // Show all users
        echo "<h2>All Users</h2>";
        $stmt = $conn->query("SELECT id, username, role, created_at FROM users ORDER BY id");
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if ($users) {
            echo "<table border='1' style='border-collapse:collapse;'>";
            echo "<tr><th>ID</th><th>Username</th><th>Role</th><th>Created At</th></tr>";
            foreach ($users as $user) {
                echo "<tr>";
                echo "<td>" . htmlspecialchars($user['id']) . "</td>";
                echo "<td>" . htmlspecialchars($user['username']) . "</td>";
                echo "<td>" . htmlspecialchars($user['role']) . "</td>";
                echo "<td>" . htmlspecialchars($user['created_at']) . "</td>";
                echo "</tr>";
            }
            echo "</table>";
        } else {
            echo "<p class='error'>No users found</p>";
        }
        
        // Test the exact same query as users.php
        echo "<h2>Test Same Query as users.php</h2>";
        
        $page = 1;
        $limit = 10;
        $offset = 0;
        $search = '';
        $roleFilter = '';
        
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

        echo "<p class='info'>Query: " . $userQuery . "</p>";
        echo "<p class='info'>Params: " . implode(', ', $userParams) . "</p>";

        $stmt = $conn->prepare($userQuery);
        
        // Bind parameters with explicit types for LIMIT clause
        for ($i = 0; $i < count($userParams); $i++) {
            if ($i >= count($userParams) - 2) { // Last two parameters are for LIMIT
                $stmt->bindValue($i + 1, $userParams[$i], PDO::PARAM_INT);
            } else {
                $stmt->bindValue($i + 1, $userParams[$i], PDO::PARAM_STR);
            }
        }
        
        $result = $stmt->execute();
        
        if ($result) {
            $testUsers = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo "<p class='success'>Query executed successfully, found " . count($testUsers) . " users</p>";
            
            if ($testUsers) {
                echo "<table border='1' style='border-collapse:collapse;'>";
                echo "<tr><th>ID</th><th>Username</th><th>Role</th><th>Created At</th></tr>";
                foreach ($testUsers as $user) {
                    echo "<tr>";
                    echo "<td>" . htmlspecialchars($user['id']) . "</td>";
                    echo "<td>" . htmlspecialchars($user['username']) . "</td>";
                    echo "<td>" . htmlspecialchars($user['role']) . "</td>";
                    echo "<td>" . htmlspecialchars($user['created_at']) . "</td>";
                    echo "</tr>";
                }
                echo "</table>";
            }
        } else {
            echo "<p class='error'>Query failed</p>";
        }
        
    } else {
        echo "<p class='error'>❌ Database connection failed</p>";
    }
    
} catch (Exception $e) {
    echo "<p class='error'>Error: " . $e->getMessage() . "</p>";
    echo "<p class='error'>File: " . $e->getFile() . " Line: " . $e->getLine() . "</p>";
}

echo "</body></html>";
?>
