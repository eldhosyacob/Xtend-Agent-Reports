<?php
require_once('../config/session.php');

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

// Fetch all users to create a lookup map in PHP
$usersQuery = "SELECT username, real_name, department, user_type FROM users";
$usersStmt = $db->query($usersQuery);
$usersList = $usersStmt->fetchAll(PDO::FETCH_ASSOC);

$userMap = [];
foreach ($usersList as $u) {
    $realNameKey = strtoupper(trim($u['real_name']));
    
    // Index strictly by real_name to align with the users' actual names
    if ($realNameKey !== '') {
        $userMap[$realNameKey] = $u;
    }
}

// Get range filter, default to today
$range = isset($_GET['range']) ? trim($_GET['range']) : 'today';
if (!in_array($range, ['today', 'week', 'month', 'year'])) {
    $range = 'today';
}

$dateCondition = "`date` = CURDATE()";
if ($range === 'week') {
    $dateCondition = "`date` >= DATE_SUB(CURDATE(), INTERVAL WEEKDAY(CURDATE()) DAY)";
} elseif ($range === 'month') {
    $dateCondition = "`date` >= DATE_FORMAT(CURDATE(), '%Y-%m-01')";
} elseif ($range === 'year') {
    $dateCondition = "`date` >= DATE_FORMAT(CURDATE(), '%Y-01-01')";
}

// 1. Total Records Count (All Time)
$totalStmt = $db->query("SELECT COUNT(*) as total FROM `$tableName`");
$totalRecords = (int)($totalStmt->fetch()['total'] ?? 0);

// 2. Timeframe Records Count
$timeframeStmt = $db->query("SELECT COUNT(*) as timeframe_count FROM `$tableName` WHERE $dateCondition");
$timeframeRecords = (int)($timeframeStmt->fetch()['timeframe_count'] ?? 0);

// 3. Status Breakdown
$allowedStatuses = [
    'Closed',
    'Pending',
    'Under Observation',
    'Escalated',
    'Escalated to Presales',
    'Closed-Device Replaced'
];

$statusQuery = "
    SELECT `support_status` as status, COUNT(*) as count 
    FROM `$tableName` 
    WHERE `support_status` IN ('Closed', 'Pending', 'Under Observation', 'Escalated', 'Escalated to Presales', 'Closed-Device Replaced') 
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
    'Closed-Device Replaced'
];

$statusBreakdown = [];
foreach ($orderedStatuses as $statusName) {
    $statusBreakdown[] = [
        'status' => $statusName,
        'count' => $statusMap[$statusName]
    ];
}

// 4. Agent Performance & Activity Details
$agentQuery = "
    SELECT 
        `agent` as agent_name, 
        COUNT(*) as total_records, 
        SUM(CASE WHEN $dateCondition THEN 1 ELSE 0 END) as timeframe_records, 
        MAX(`date`) as last_active 
    FROM `$tableName` 
    GROUP BY `agent` 
    ORDER BY timeframe_records DESC, total_records DESC
";
$agentStmt = $db->query($agentQuery);
$agentsData = $agentStmt->fetchAll(PDO::FETCH_ASSOC);

$agentsActiveTimeframe = 0;
$agentsList = [];
$othersTotal = 0;
$othersTimeframe = 0;
$othersLastActive = null;

foreach ($agentsData as $row) {
    $agentName = trim($row['agent_name']);
    $agentNameKey = strtoupper($agentName);
    
    $timeframeCount = (int)$row['timeframe_records'];
    
    // Only include this agent if they exist in the users table
    if (isset($userMap[$agentNameKey])) {
        $matchedUser = $userMap[$agentNameKey];
        if ($timeframeCount > 0) {
            $agentsActiveTimeframe++;
        }

        $details = [
            'agent_name' => $matchedUser['real_name'] ?: $agentName,
            'username' => $matchedUser['username'] ?: '-',
            'role' => $matchedUser['user_type'] ?: 'Agent',
            'department' => $matchedUser['department'] ?: $deptLabel,
            'total_records' => (int)$row['total_records'],
            'timeframe_records' => $timeframeCount,
            'last_active' => $row['last_active']
        ];

        $agentsList[] = $details;
    } else {
        // Accumulate stats for any unregistered names
        $othersTotal += (int)$row['total_records'];
        $othersTimeframe += $timeframeCount;
        
        if ($row['last_active']) {
            if ($othersLastActive === null || strcmp($row['last_active'], $othersLastActive) > 0) {
                $othersLastActive = $row['last_active'];
            }
        }
    }
}

// Append "Others" row if we accumulated any unregistered records
if ($othersTotal > 0) {
    if ($othersTimeframe > 0) {
        $agentsActiveTimeframe++;
    }
    
    $agentsList[] = [
        'agent_name' => 'Others',
        'username' => '-',
        'role' => '-',
        'department' => $deptLabel,
        'total_records' => $othersTotal,
        'timeframe_records' => $othersTimeframe,
        'last_active' => $othersLastActive ?: '-'
    ];
}

$activeAgentsCount = count($agentsList);

// Send response
echo json_encode([
    'success' => true,
    'department' => $deptLabel,
    'range' => $range,
    'summary' => [
        'total_records' => $totalRecords,
        'timeframe_records' => $timeframeRecords,
        'active_agents' => $activeAgentsCount,
        'agents_active_timeframe' => $agentsActiveTimeframe
    ],
    'status_breakdown' => $statusBreakdown,
    'agent_details' => $agentsList
]);
?>
