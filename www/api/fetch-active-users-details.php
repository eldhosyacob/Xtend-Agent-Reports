<?php
require_once __DIR__ . '/../config/session.php';

header('Content-Type: application/json');

// Only allow GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    echo json_encode([
        'success' => false,
        'message' => 'Method not allowed'
    ]);
    exit;
}

// Check session authentication
if (!isset($_SESSION['id']) || !isset($_SESSION['username'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized: Please log in first'
    ]);
    exit;
}

// Include database configuration
require_once __DIR__ . '/../config/database.php';
$db = getDatabaseConnection();

if (!$db) {
    echo json_encode([
        'success' => false,
        'message' => 'Database connection failed'
    ]);
    exit;
}

// Get department parameter
$dept = isset($_GET['department']) ? trim($_GET['department']) : 'voice_logger';
$tableName = 'support_details';
$deptLabel = 'Voice Logger';

if ($dept === 'ivr' || strcasecmp($dept, 'IVR') === 0) {
    $tableName = 'ivr_details';
    $deptLabel = 'IVR';
}

$normalizedDept = strtolower(str_replace('_', ' ', $dept));

// Get range filter
$range = isset($_GET['range']) ? trim($_GET['range']) : 'today';
if (!in_array($range, ['today', 'week', 'month', 'year'])) {
    $range = 'today';
}

$dateSql = "`date` = CURDATE()";
if ($range === 'week') {
    $dateSql = "`date` >= DATE_SUB(CURDATE(), INTERVAL WEEKDAY(CURDATE()) DAY)";
} elseif ($range === 'month') {
    $dateSql = "`date` >= DATE_FORMAT(CURDATE(), '%Y-%m-01')";
} elseif ($range === 'year') {
    $dateSql = "`date` >= DATE_FORMAT(CURDATE(), '%Y-01-01')";
}

// Build user conditions (all logged-in users matching Agent Performance Details on dashboard)
$whereConditions = [
    "(LOWER(REPLACE(department, '_', ' ')) = :dept OR LOWER(REPLACE(department, '_', ' ')) = 'manager')",
    "(last_activity IS NOT NULL AND last_activity >= NOW() - INTERVAL 600 MINUTE)"
];
$params = ['dept' => $normalizedDept];

if (isset($_GET['search']) && trim($_GET['search']) !== '') {
    $searchVal = '%' . trim($_GET['search']) . '%';
    $whereConditions[] = "(`username` LIKE :s1 OR `real_name` LIKE :s2 OR `user_type` LIKE :s3)";
    $params['s1'] = $searchVal;
    $params['s2'] = $searchVal;
    $params['s3'] = $searchVal;
}

$whereClause = implode(' AND ', $whereConditions);

// Fetch online users
$usersQuery = "SELECT id, username, real_name, department, user_type, last_activity 
               FROM users WHERE $whereClause ORDER BY real_name ASC";
$usersStmt = $db->prepare($usersQuery);
$usersStmt->execute($params);
$usersList = $usersStmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch record metrics for the timeframe
$agentMetricsQuery = "
    SELECT 
        `agent` as agent_name, 
        COUNT(*) as total_records, 
        SUM(CASE WHEN $dateSql THEN 1 ELSE 0 END) as timeframe_records, 
        MAX(`date`) as last_active_record 
    FROM `$tableName` 
    GROUP BY `agent`
";
$metricsStmt = $db->query($agentMetricsQuery);
$metricsData = $metricsStmt->fetchAll(PDO::FETCH_ASSOC);

$metricsMap = [];
foreach ($metricsData as $m) {
    $key = strtoupper(trim($m['agent_name']));
    if ($key !== '') {
        $metricsMap[$key] = $m;
    }
}

$allowed_extensions = ['png', 'jpg', 'jpeg', 'gif', 'webp'];
$activeUsers = [];

foreach ($usersList as $u) {
    $realNameKey = strtoupper(trim($u['real_name']));
    $usernameKey = strtoupper(trim($u['username']));
    $matched = $metricsMap[$realNameKey] ?? ($metricsMap[$usernameKey] ?? null);

    $profile_photo_url = 'images/default-avatar.png';
    if (!empty($u['id'])) {
        foreach ($allowed_extensions as $ext) {
            $photo_path = __DIR__ . '/../images/profile/' . $u['id'] . '.' . $ext;
            if (file_exists($photo_path)) {
                $profile_photo_url = 'images/profile/' . $u['id'] . '.' . $ext . '?t=' . filemtime($photo_path);
                break;
            }
        }
    }

    $activeUsers[] = [
        'id' => $u['id'],
        'real_name' => $u['real_name'] ?: $u['username'],
        'username' => $u['username'],
        'role' => $u['user_type'] ?: 'Agent',
        'department' => $u['department'] ?: $deptLabel,
        'is_online' => true,
        'last_activity' => $u['last_activity'],
        'timeframe_records' => $matched ? (int)$matched['timeframe_records'] : 0,
        'total_records' => $matched ? (int)$matched['total_records'] : 0,
        'last_record_date' => $matched ? $matched['last_active_record'] : null,
        'profile_photo_url' => $profile_photo_url
    ];
}

// Sort active users: Timeframe records DESC, total records DESC, real name ASC
usort($activeUsers, function($a, $b) {
    if ($a['timeframe_records'] !== $b['timeframe_records']) {
        return $b['timeframe_records'] <=> $a['timeframe_records'];
    }
    if ($a['total_records'] !== $b['total_records']) {
        return $b['total_records'] <=> $a['total_records'];
    }
    return strcasecmp($a['real_name'], $b['real_name']);
});

// Pagination
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$limit = isset($_GET['limit']) ? max(1, min(100, (int)$_GET['limit'])) : 15;
$totalActive = count($activeUsers);
$totalPages = max(1, ceil($totalActive / $limit));
$offset = ($page - 1) * $limit;

$paginatedUsers = array_slice($activeUsers, $offset, $limit);

echo json_encode([
    'success' => true,
    'department' => $deptLabel,
    'range' => $range,
    'total' => $totalActive,
    'page' => $page,
    'limit' => $limit,
    'pages' => $totalPages,
    'active_users' => $paginatedUsers
]);
?>
