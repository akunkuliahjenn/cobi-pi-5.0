
<?php
// includes/activity_logger.php
// System untuk logging aktivitas user

function logActivity($user_id, $username, $activity_type, $description, $db = null) {
    if (!$db) {
        require_once __DIR__ . '/../config/db.php';
        $db = $GLOBALS['db'];
    }
    
    try {
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
        
        $stmt = $db->prepare("INSERT INTO activity_logs (user_id, username, activity_type, activity_description, user_agent, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
        $stmt->execute([$user_id, $username, $activity_type, $description, $user_agent]);
        
    } catch (PDOException $e) {
        error_log("Error logging activity: " . $e->getMessage());
    }
}

function getActivityLogs($page = 1, $limit = 10, $search = '', $date_filter = '', $db = null) {
    if (!$db) {
        require_once __DIR__ . '/../config/db.php';
        $db = $GLOBALS['db'];
    }
    
    try {
        $offset = ($page - 1) * $limit;
        
        $where_conditions = [];
        $params = [];
        
        if (!empty($search)) {
            $where_conditions[] = "(username LIKE ? OR activity_description LIKE ?)";
            $params[] = '%' . $search . '%';
            $params[] = '%' . $search . '%';
        }
        
        if (!empty($date_filter)) {
            $where_conditions[] = "DATE(created_at) = ?";
            $params[] = $date_filter;
        }
        
        $where_clause = !empty($where_conditions) ? ' WHERE ' . implode(' AND ', $where_conditions) : '';
        
        // Get total count
        $count_sql = "SELECT COUNT(*) FROM activity_logs" . $where_clause;
        $count_stmt = $db->prepare($count_sql);
        $count_stmt->execute($params);
        $total = $count_stmt->fetchColumn();
        
        // Get logs
        $sql = "SELECT * FROM activity_logs" . $where_clause . " ORDER BY created_at DESC LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;
        
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return [
            'logs' => $logs,
            'total' => $total,
            'total_pages' => ceil($total / $limit)
        ];
        
    } catch (PDOException $e) {
        error_log("Error getting activity logs: " . $e->getMessage());
        return ['logs' => [], 'total' => 0, 'total_pages' => 0];
    }
}
?>
