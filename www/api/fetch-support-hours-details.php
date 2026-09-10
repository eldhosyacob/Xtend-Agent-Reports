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
$fromDate = isset($_GET['from_date']) ? trim($_GET['from_date']) : '';
$toDate = isset($_GET['to_date']) ? trim($_GET['to_date']) : '';

if (!in_array($range, ['today', 'week', 'month', 'year', 'custom'])) {
    $range = 'today';
}

$dateSql = "`date` = CURDATE()";
if ($range === 'week') {
    $dateSql = "`date` >= DATE_SUB(CURDATE(), INTERVAL WEEKDAY(CURDATE()) DAY)";
} elseif ($range === 'month') {
    $dateSql = "`date` >= DATE_FORMAT(CURDATE(), '%Y-%m-01')";
} elseif ($range === 'year') {
    $dateSql = "`date` >= DATE_FORMAT(CURDATE(), '%Y-01-01')";
} elseif ($range === 'custom') {
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $fromDate) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $toDate)) {
        if ($fromDate === $toDate) {
            $dateSql = "`date` = " . $db->quote($fromDate);
        } else {
            $dateSql = "`date` BETWEEN " . $db->quote($fromDate) . " AND " . $db->quote($toDate);
        }
    } else {
        $range = 'today';
        $dateSql = "`date` = CURDATE()";
    }
}

// 1. Calculate overall support time for the timeframe
$overallHoursStmt = $db->query("SELECT SUM(COALESCE(TIME_TO_SEC(`total_time`), 0)) as total_sec, COUNT(*) as total_recs FROM `$tableName` WHERE $dateSql");
$overallRow = $overallHoursStmt->fetch(PDO::FETCH_ASSOC);
$overallSec = (int)($overallRow['total_sec'] ?? 0);
$overallRecs = (int)($overallRow['total_recs'] ?? 0);

$ovH = floor($overallSec / 3600);
$ovM = floor(($overallSec % 3600) / 60);
$ovS = $overallSec % 60;
$formattedOverallHours = sprintf('%02d:%02d:%02d', $ovH, $ovM, $ovS);

// 2. Fetch users in department
$usersQuery = "SELECT id, username, real_name, department, user_type, last_activity, (last_activity IS NOT NULL AND last_activity >= NOW() - INTERVAL 10 MINUTE) as is_online FROM users WHERE LOWER(REPLACE(department, '_', ' ')) = :dept OR LOWER(REPLACE(department, '_', ' ')) = 'manager'";
$usersStmt = $db->prepare($usersQuery);
$usersStmt->execute(['dept' => $normalizedDept]);
$usersList = $usersStmt->fetchAll(PDO::FETCH_ASSOC);

// 3. Fetch agent support time metrics from table
$agentMetricsQuery = "
    SELECT 
        `agent` as agent_name, 
        COUNT(*) as total_records, 
        SUM(CASE WHEN $dateSql THEN 1 ELSE 0 END) as timeframe_records, 
        SUM(COALESCE(TIME_TO_SEC(`total_time`), 0)) as total_seconds, 
        SUM(CASE WHEN $dateSql THEN COALESCE(TIME_TO_SEC(`total_time`), 0) ELSE 0 END) as timeframe_seconds, 
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
$agentsList = [];
$processedKeys = [];

foreach ($usersList as $u) {
    $realNameKey = strtoupper(trim($u['real_name']));
    $usernameKey = strtoupper(trim($u['username']));

    $matched = $metricsMap[$realNameKey] ?? ($metricsMap[$usernameKey] ?? null);
    if ($matched) {
        $processedKeys[$realNameKey] = true;
        if ($usernameKey) $processedKeys[$usernameKey] = true;
    }

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

    $tfSec = $matched ? (int)$matched['timeframe_seconds'] : 0;
    $totSec = $matched ? (int)$matched['total_seconds'] : 0;

    $tfH = floor($tfSec / 3600);
    $tfM = floor(($tfSec % 3600) / 60);
    $tfS = $tfSec % 60;

    $totH = floor($totSec / 3600);
    $totM = floor(($totSec % 3600) / 60);
    $totS = $totSec % 60;

    $agentsList[] = [
        'id' => $u['id'],
        'agent_name' => $u['real_name'] ?: $u['username'],
        'username' => $u['username'],
        'role' => $u['user_type'] ?: 'Agent',
        'department' => $u['department'] ?: $deptLabel,
        'is_online' => !empty($u['is_online']),
        'last_activity' => $u['last_activity'],
        'timeframe_records' => $matched ? (int)$matched['timeframe_records'] : 0,
        'total_records' => $matched ? (int)$matched['total_records'] : 0,
        'timeframe_seconds' => $tfSec,
        'timeframe_support_time' => sprintf('%02d:%02d:%02d', $tfH, $tfM, $tfS),
        'total_seconds' => $totSec,
        'total_support_time' => sprintf('%02d:%02d:%02d', $totH, $totM, $totS),
        'last_record_date' => $matched ? $matched['last_active_record'] : null,
        'profile_photo_url' => $profile_photo_url
    ];
}

// Optional search filtering
$searchQuery = isset($_GET['search']) ? trim($_GET['search']) : '';
if ($searchQuery !== '') {
    $searchLower = strtolower($searchQuery);
    $agentsList = array_values(array_filter($agentsList, function($item) use ($searchLower) {
        return (strpos(strtolower($item['agent_name']), $searchLower) !== false) ||
               (strpos(strtolower($item['username']), $searchLower) !== false) ||
               (strpos(strtolower($item['role']), $searchLower) !== false);
    }));
}

// Sort active agents: timeframe_seconds DESC, timeframe_records DESC, total_seconds DESC, agent_name ASC
usort($agentsList, function($a, $b) {
    if ($a['timeframe_seconds'] !== $b['timeframe_seconds']) {
        return $b['timeframe_seconds'] <=> $a['timeframe_seconds'];
    }
    if ($a['timeframe_records'] !== $b['timeframe_records']) {
        return $b['timeframe_records'] <=> $a['timeframe_records'];
    }
    if ($a['total_seconds'] !== $b['total_seconds']) {
        return $b['total_seconds'] <=> $a['total_seconds'];
    }
    return strcasecmp($a['agent_name'], $b['agent_name']);
});

// Pagination
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$limit = isset($_GET['limit']) ? max(1, min(100, (int)$_GET['limit'])) : 15;
$totalAgents = count($agentsList);
$totalPages = max(1, ceil($totalAgents / $limit));
$offset = ($page - 1) * $limit;

$paginatedAgents = array_slice($agentsList, $offset, $limit);

echo json_encode([
    'success' => true,
    'department' => $deptLabel,
    'range' => $range,
    'total' => $totalAgents,
    'page' => $page,
    'limit' => $limit,
    'pages' => $totalPages,
    'summary' => [
        'total_support_hours' => $formattedOverallHours,
        'total_support_seconds' => $overallSec,
        'total_timeframe_records' => $overallRecs,
        'total_agents' => $totalAgents
    ],
    'agents' => $paginatedAgents
]);
?>
