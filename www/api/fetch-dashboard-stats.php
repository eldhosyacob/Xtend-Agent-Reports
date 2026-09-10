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

// Update current user's last activity since they are active on the dashboard
try {
    $updateActivity = $db->prepare("UPDATE users SET last_activity = NOW() WHERE id = :id");
    $updateActivity->execute(['id' => $_SESSION['id']]);
} catch (PDOException $e) {
    error_log("Failed to update last activity in dashboard stats API: " . $e->getMessage());
}

// Get requested department, default to voice_logger
$dept = isset($_GET['department']) ? trim($_GET['department']) : 'voice_logger';

// Validate department and map to appropriate table
$tableName = '';
if ($dept === 'voice_logger' || strcasecmp($dept, 'Voice Logger') === 0) {
    $tableName = 'support_details';
    $deptLabel = 'Voice Logger';
} elseif ($dept === 'ivr' || strcasecmp($dept, 'IVR') === 0) {
    $tableName = 'ivr_details';
    $deptLabel = 'IVR';
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid department specified: ' . htmlspecialchars($dept)
    ]);
    exit;
}

// Normalized department value for DB matching (e.g. 'voice logger' or 'ivr')
$normalizedDept = strtolower(str_replace('_', ' ', $dept));

// Fetch all users in the corresponding department (including 'manager' which is common to all departments)
$usersQuery = "SELECT id, username, real_name, department, user_type, last_activity, (last_activity IS NOT NULL AND last_activity >= NOW() - INTERVAL 600 MINUTE) as is_online FROM users WHERE LOWER(REPLACE(department, '_', ' ')) = :dept OR LOWER(REPLACE(department, '_', ' ')) = 'manager'";
$usersStmt = $db->prepare($usersQuery);
$usersStmt->execute(['dept' => $normalizedDept]);
$usersList = $usersStmt->fetchAll(PDO::FETCH_ASSOC);

$totalAgentsCount = count($usersList);
$onlineAgentsCount = 0;
foreach ($usersList as $u) {
    if (!empty($u['is_online'])) {
        $onlineAgentsCount++;
    }
}

// Get range filter, default to today
$range = isset($_GET['range']) ? trim($_GET['range']) : 'today';
$fromDate = isset($_GET['from_date']) ? trim($_GET['from_date']) : '';
$toDate = isset($_GET['to_date']) ? trim($_GET['to_date']) : '';

if (!in_array($range, ['today', 'week', 'month', 'year', 'custom'])) {
    $range = 'today';
}

$dateCondition = "`date` = CURDATE()";
if ($range === 'week') {
    $dateCondition = "`date` >= DATE_SUB(CURDATE(), INTERVAL WEEKDAY(CURDATE()) DAY)";
} elseif ($range === 'month') {
    $dateCondition = "`date` >= DATE_FORMAT(CURDATE(), '%Y-%m-01')";
} elseif ($range === 'year') {
    $dateCondition = "`date` >= DATE_FORMAT(CURDATE(), '%Y-01-01')";
} elseif ($range === 'custom') {
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $fromDate) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $toDate)) {
        if ($fromDate === $toDate) {
            $dateCondition = "`date` = " . $db->quote($fromDate);
        } else {
            $dateCondition = "`date` BETWEEN " . $db->quote($fromDate) . " AND " . $db->quote($toDate);
        }
    } else {
        $range = 'today';
        $dateCondition = "`date` = CURDATE()";
    }
}

// 1. Total Records Count (All Time)
$totalStmt = $db->query("SELECT COUNT(*) as total FROM `$tableName`");
$totalRecords = (int)($totalStmt->fetch()['total'] ?? 0);

// 2. Timeframe Records Count
$timeframeStmt = $db->query("SELECT COUNT(*) as timeframe_count FROM `$tableName` WHERE $dateCondition");
$timeframeRecords = (int)($timeframeStmt->fetch()['timeframe_count'] ?? 0);

// 2b. Timeframe Support Hours
$timeframeHoursStmt = $db->query("SELECT SUM(COALESCE(TIME_TO_SEC(`total_time`), 0)) as total_sec FROM `$tableName` WHERE $dateCondition");
$totalSupportSec = (int)($timeframeHoursStmt->fetch()['total_sec'] ?? 0);
$h = floor($totalSupportSec / 3600);
$m = floor(($totalSupportSec % 3600) / 60);
$s = $totalSupportSec % 60;
$formattedSupportHours = sprintf('%02d:%02d:%02d', $h, $m, $s);

// 3. Status Breakdown
$allowedStatuses = [
    'Closed',
    'Pending',
    'Under Observation',
    'Escalated',
    'Escalated to Presales',
    'Closed-Device Replaced',
    'Closed-No Response'
];

$statusQuery = "
    SELECT `support_status` as status, COUNT(*) as count 
    FROM `$tableName` 
    WHERE `support_status` IN ('Closed', 'Pending', 'Under Observation', 'Escalated', 'Escalated to Presales', 'Closed-Device Replaced', 'Closed-No Response') 
      AND $dateCondition
    GROUP BY `support_status`
";
$statusStmt = $db->query($statusQuery);
$dbStatuses = $statusStmt->fetchAll(PDO::FETCH_ASSOC);

$statusMap = array_fill_keys($allowedStatuses, 0);
foreach ($dbStatuses as $row) {
    $statusMap[$row['status']] = (int)$row['count'];
}

$orderedStatuses = [
    'Closed',
    'Pending',
    'Under Observation',
    'Escalated',
    'Escalated to Presales',
    'Closed-Device Replaced',
    'Closed-No Response'
];

$statusBreakdown = [];
foreach ($orderedStatuses as $statusName) {
    $statusBreakdown[] = [
        'status' => $statusName,
        'count' => $statusMap[$statusName]
    ];
}

// 4. Fetch Support/IVR record metrics and total time for agents in this department
$agentQuery = "
    SELECT 
        `agent` as agent_name, 
        COUNT(*) as total_records, 
        SUM(CASE WHEN $dateCondition THEN 1 ELSE 0 END) as timeframe_records, 
        SUM(COALESCE(TIME_TO_SEC(`total_time`), 0)) as total_seconds,
        SUM(CASE WHEN $dateCondition THEN COALESCE(TIME_TO_SEC(`total_time`), 0) ELSE 0 END) as timeframe_seconds,
        MAX(`date`) as last_active 
    FROM `$tableName` 
    GROUP BY `agent`
";
$agentStmt = $db->query($agentQuery);
$recordsData = $agentStmt->fetchAll(PDO::FETCH_ASSOC);

$recordsMap = [];
foreach ($recordsData as $r) {
    $key = strtoupper(trim($r['agent_name']));
    if ($key !== '') {
        $recordsMap[$key] = $r;
    }
}

// Build agent details list showing users and active contributors
$agentsList = [];
$allowed_extensions = ['png', 'jpg', 'jpeg', 'gif', 'webp'];
$processedKeys = [];

foreach ($usersList as $matchedUser) {
    if (empty($matchedUser['is_online'])) {
        continue;
    }

    $realNameKey = strtoupper(trim($matchedUser['real_name']));
    $usernameKey = strtoupper(trim($matchedUser['username']));
    
    $matchedRecord = $recordsMap[$realNameKey] ?? ($recordsMap[$usernameKey] ?? null);

    $timeframeCount = $matchedRecord ? (int)$matchedRecord['timeframe_records'] : 0;
    $totalCount = $matchedRecord ? (int)$matchedRecord['total_records'] : 0;
    $lastActive = $matchedRecord ? $matchedRecord['last_active'] : null;
    $tfSec = $matchedRecord ? (int)$matchedRecord['timeframe_seconds'] : 0;
    $tfH = floor($tfSec / 3600);
    $tfM = floor(($tfSec % 3600) / 60);
    $tfS = $tfSec % 60;
    $timeframeSupportTime = sprintf('%02d:%02d:%02d', $tfH, $tfM, $tfS);

    // Resolve profile picture
    $profile_photo_url = 'images/default-avatar.png';
    if (!empty($matchedUser['id'])) {
        foreach ($allowed_extensions as $ext) {
            $photo_path = __DIR__ . '/../images/profile/' . $matchedUser['id'] . '.' . $ext;
            if (file_exists($photo_path)) {
                $profile_photo_url = 'images/profile/' . $matchedUser['id'] . '.' . $ext . '?t=' . filemtime($photo_path);
                break;
            }
        }
    }

    $agentsList[] = [
        'agent_name' => $matchedUser['real_name'] ?: $matchedUser['username'],
        'username' => $matchedUser['username'] ?: '-',
        'role' => $matchedUser['user_type'] ?: 'Agent',
        'department' => $matchedUser['department'] ?: $deptLabel,
        'total_records' => $totalCount,
        'timeframe_records' => $timeframeCount,
        'timeframe_support_time' => $timeframeSupportTime,
        'last_active' => $lastActive ?: ($matchedUser['last_activity'] ? date('Y-m-d', strtotime($matchedUser['last_activity'])) : '-'),
        'profile_photo_url' => $profile_photo_url,
        'is_online' => true,
        'last_activity' => $matchedUser['last_activity']
    ];
}

// Sort agents by timeframe_records DESC, timeframe_seconds DESC, total_records DESC, agent_name ASC
usort($agentsList, function($a, $b) {
    if ($a['timeframe_records'] !== $b['timeframe_records']) {
        return $b['timeframe_records'] <=> $a['timeframe_records'];
    }
    if ($a['timeframe_support_time'] !== $b['timeframe_support_time']) {
        return strcmp($b['timeframe_support_time'], $a['timeframe_support_time']);
    }
    if ($a['total_records'] !== $b['total_records']) {
        return $b['total_records'] <=> $a['total_records'];
    }
    return strcasecmp($a['agent_name'], $b['agent_name']);
});

// Send response
echo json_encode([
    'success' => true,
    'department' => $deptLabel,
    'range' => $range,
    'summary' => [
        'total_records' => $totalRecords,
        'timeframe_records' => $timeframeRecords,
        'total_agents' => $totalAgentsCount,
        'online_agents' => $onlineAgentsCount,
        'active_agents' => $totalAgentsCount,
        'agents_active_timeframe' => $onlineAgentsCount,
        'total_support_hours' => $formattedSupportHours,
        'total_support_seconds' => $totalSupportSec
    ],
    'status_breakdown' => $statusBreakdown,
    'agent_details' => $agentsList
]);
?>
