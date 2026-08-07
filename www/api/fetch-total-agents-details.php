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

// Build user search and department conditions
$whereConditions = ["(LOWER(REPLACE(department, '_', ' ')) = :dept OR LOWER(REPLACE(department, '_', ' ')) = 'manager')"];
$params = ['dept' => $normalizedDept];

if (isset($_GET['search']) && trim($_GET['search']) !== '') {
    $searchVal = '%' . trim($_GET['search']) . '%';
    $whereConditions[] = "(`username` LIKE :s1 OR `real_name` LIKE :s2 OR `user_type` LIKE :s3)";
    $params['s1'] = $searchVal;
    $params['s2'] = $searchVal;
    $params['s3'] = $searchVal;
}

$whereClause = implode(' AND ', $whereConditions);

// Fetch all matching users
$usersQuery = "SELECT id, username, real_name, department, user_type, last_activity, 
               (last_activity IS NOT NULL AND last_activity >= NOW() - INTERVAL 10 MINUTE) as is_online 
               FROM users WHERE $whereClause ORDER BY real_name ASC";
$usersStmt = $db->prepare($usersQuery);
$usersStmt->execute($params);
$usersList = $usersStmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch aggregate metrics from department details table per agent
$agentMetricsQuery = "
    SELECT 
        `agent` as agent_name, 
        COUNT(*) as total_records, 
        SUM(CASE WHEN `date` = CURDATE() THEN 1 ELSE 0 END) as records_today, 
        MAX(`date`) as last_record_date 
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

// Build response agents array
$allowed_extensions = ['png', 'jpg', 'jpeg', 'gif', 'webp'];
$agents = [];

foreach ($usersList as $u) {
    $realNameKey = strtoupper(trim($u['real_name']));
    $matched = $metricsMap[$realNameKey] ?? null;

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

    $agents[] = [
        'id' => $u['id'],
        'real_name' => $u['real_name'] ?: $u['username'],
        'username' => $u['username'],
        'role' => $u['user_type'] ?: 'Agent',
        'department' => $u['department'] ?: $deptLabel,
        'is_online' => (bool)$u['is_online'],
        'last_activity' => $u['last_activity'],
        'total_records' => $matched ? (int)$matched['total_records'] : 0,
        'records_today' => $matched ? (int)$matched['records_today'] : 0,
        'last_record_date' => $matched ? $matched['last_record_date'] : null,
        'profile_photo_url' => $profile_photo_url
    ];
}

// Sort agents: Online first, then total records DESC, then real name ASC
usort($agents, function($a, $b) {
    if ($a['is_online'] !== $b['is_online']) {
        return $b['is_online'] <=> $a['is_online'];
    }
    if ($a['total_records'] !== $b['total_records']) {
        return $b['total_records'] <=> $a['total_records'];
    }
    return strcasecmp($a['real_name'], $b['real_name']);
});

// Pagination
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$limit = isset($_GET['limit']) ? max(1, min(100, (int)$_GET['limit'])) : 15;
$totalAgents = count($agents);
$totalPages = max(1, ceil($totalAgents / $limit));
$offset = ($page - 1) * $limit;

$paginatedAgents = array_slice($agents, $offset, $limit);

echo json_encode([
    'success' => true,
    'department' => $deptLabel,
    'total' => $totalAgents,
    'page' => $page,
    'limit' => $limit,
    'pages' => $totalPages,
    'agents' => $paginatedAgents
]);
?>
