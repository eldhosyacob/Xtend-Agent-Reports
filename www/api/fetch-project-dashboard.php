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

// Update current user's last activity
$updateActivity = $db->prepare("UPDATE users SET last_activity = NOW() WHERE id = :id");
$updateActivity->execute(['id' => $_SESSION['id']]);

// Department filter (voice_logger or ivr)
$dept = isset($_GET['department']) ? trim($_GET['department']) : 'voice_logger';
if ($dept === 'ivr' || strcasecmp($dept, 'IVR') === 0) {
    $deptLabel = 'IVR';
    $normalizedDept = 'ivr';
} else {
    $deptLabel = 'Voice Logger';
    $normalizedDept = 'voice logger';
}

// Range filter ('today', 'week', 'month', 'year', 'custom')
$range = isset($_GET['range']) ? trim($_GET['range']) : 'today';
$fromDate = isset($_GET['from_date']) ? trim($_GET['from_date']) : '';
$toDate = isset($_GET['to_date']) ? trim($_GET['to_date']) : '';

if (!in_array($range, ['today', 'week', 'month', 'year', 'custom'], true)) {
    $range = 'today';
}

$dateParams = [];
$dateCondition = "pr.`date` = CURDATE()";
$agentDateCondition1 = "pr.`date` = CURDATE()";
$agentDateCondition2 = "pr.`date` = CURDATE()";
$agentDateParams = [];

if ($range === 'week') {
    $dateCondition = "pr.`date` >= DATE_SUB(CURDATE(), INTERVAL WEEKDAY(CURDATE()) DAY)";
    $agentDateCondition1 = $dateCondition;
    $agentDateCondition2 = $dateCondition;
} elseif ($range === 'month') {
    $dateCondition = "pr.`date` >= DATE_FORMAT(CURDATE(), '%Y-%m-01')";
    $agentDateCondition1 = $dateCondition;
    $agentDateCondition2 = $dateCondition;
} elseif ($range === 'year') {
    $dateCondition = "pr.`date` >= DATE_FORMAT(CURDATE(), '%Y-01-01')";
    $agentDateCondition1 = $dateCondition;
    $agentDateCondition2 = $dateCondition;
} elseif ($range === 'custom') {
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $fromDate) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $toDate)) {
        if ($fromDate === $toDate) {
            $dateCondition = "pr.`date` = :from_date";
            $dateParams['from_date'] = $fromDate;

            $agentDateCondition1 = "pr.`date` = :ag_from_1";
            $agentDateCondition2 = "pr.`date` = :ag_from_2";
            $agentDateParams['ag_from_1'] = $fromDate;
            $agentDateParams['ag_from_2'] = $fromDate;
        } else {
            $dateCondition = "pr.`date` BETWEEN :from_date AND :to_date";
            $dateParams['from_date'] = $fromDate;
            $dateParams['to_date'] = $toDate;

            $agentDateCondition1 = "pr.`date` BETWEEN :ag_from_1 AND :ag_to_1";
            $agentDateCondition2 = "pr.`date` BETWEEN :ag_from_2 AND :ag_to_2";
            $agentDateParams['ag_from_1'] = $fromDate;
            $agentDateParams['ag_to_1'] = $toDate;
            $agentDateParams['ag_from_2'] = $fromDate;
            $agentDateParams['ag_to_2'] = $toDate;
        }
    } else {
        $range = 'today';
        $dateCondition = "pr.`date` = CURDATE()";
        $agentDateCondition1 = "pr.`date` = CURDATE()";
        $agentDateCondition2 = "pr.`date` = CURDATE()";
    }
}

// Fetch users belonging to this department (or manager)
$usersStmt = $db->prepare("
    SELECT id, username, real_name, department, user_type, project_only, last_activity,
           (last_activity IS NOT NULL AND last_activity >= NOW() - INTERVAL 600 MINUTE) as is_online 
    FROM users 
    WHERE LOWER(REPLACE(department, '_', ' ')) = :dept OR LOWER(REPLACE(department, '_', ' ')) = 'manager'
    ORDER BY real_name ASC, username ASC
");
$usersStmt->execute(['dept' => $normalizedDept]);
$usersList = $usersStmt->fetchAll(PDO::FETCH_ASSOC);

$totalAgentsCount = count($usersList);
$onlineAgentsCount = 0;
foreach ($usersList as $u) {
    if (!empty($u['is_online'])) {
        $onlineAgentsCount++;
    }
}

// Base joins and conditions for project_reports filtered by agent department
$userJoin = "LEFT JOIN `users` u ON (LOWER(TRIM(pr.`agent_name`)) = LOWER(TRIM(u.`real_name`)) OR LOWER(TRIM(pr.`agent_name`)) = LOWER(TRIM(u.`username`)))";
$deptWhere = "(LOWER(REPLACE(u.`department`, '_', ' ')) = :dept OR LOWER(REPLACE(u.`department`, '_', ' ')) = 'manager')";
$baseDeptParams = ['dept' => $normalizedDept];

// 1. Total Project Reports Count for this department
$totalQuery = "SELECT COUNT(*) as total FROM `project_reports` pr $userJoin WHERE $deptWhere";
$totalStmt = $db->prepare($totalQuery);
$totalStmt->execute($baseDeptParams);
$totalReports = (int)($totalStmt->fetch()['total'] ?? 0);

// 2. Timeframe Project Reports Count
$timeframeQuery = "SELECT COUNT(*) as timeframe_count FROM `project_reports` pr $userJoin WHERE $dateCondition AND $deptWhere";
$timeframeStmt = $db->prepare($timeframeQuery);
$timeframeStmt->execute(array_merge($dateParams, $baseDeptParams));
$timeframeReports = (int)($timeframeStmt->fetch()['timeframe_count'] ?? 0);

// 3. Timeframe Total Time (Sum of total_time in HH:MM:SS)
$timeframeTimeQuery = "SELECT SUM(COALESCE(TIME_TO_SEC(pr.`total_time`), 0)) as total_sec FROM `project_reports` pr $userJoin WHERE $dateCondition AND $deptWhere";
$timeframeTimeStmt = $db->prepare($timeframeTimeQuery);
$timeframeTimeStmt->execute(array_merge($dateParams, $baseDeptParams));
$totalTimeSec = (int)($timeframeTimeStmt->fetch()['total_sec'] ?? 0);

$h = floor($totalTimeSec / 3600);
$m = floor(($totalTimeSec % 3600) / 60);
$s = $totalTimeSec % 60;
$formattedTotalTime = sprintf('%02d:%02d:%02d', $h, $m, $s);

// 4. Status Breakdown: completed, pending, in progress, on hold
$statusQuery = "
    SELECT LOWER(TRIM(pr.`status`)) as st, COUNT(*) as count 
    FROM `project_reports` pr 
    $userJoin 
    WHERE $dateCondition AND $deptWhere
    GROUP BY LOWER(TRIM(pr.`status`))
";
$statusStmt = $db->prepare($statusQuery);
$statusStmt->execute(array_merge($dateParams, $baseDeptParams));
$statusRows = $statusStmt->fetchAll(PDO::FETCH_ASSOC);

$statusCounts = [
    'Completed' => 0,
    'Pending' => 0,
    'In Progress' => 0,
    'On Hold' => 0
];

foreach ($statusRows as $row) {
    $st = strtolower(trim($row['st'] ?? ''));
    if ($st === 'completed') {
        $statusCounts['Completed'] += (int)$row['count'];
    } elseif ($st === 'in progress' || $st === 'inprogress') {
        $statusCounts['In Progress'] += (int)$row['count'];
    } elseif ($st === 'pending') {
        $statusCounts['Pending'] += (int)$row['count'];
    } elseif ($st === 'on hold' || $st === 'onhold' || $st === 'hold') {
        $statusCounts['On Hold'] += (int)$row['count'];
    }
}

$statusBreakdown = [
    ['status' => 'Completed', 'count' => $statusCounts['Completed']],
    ['status' => 'Pending', 'count' => $statusCounts['Pending']],
    ['status' => 'In Progress', 'count' => $statusCounts['In Progress']],
    ['status' => 'On Hold', 'count' => $statusCounts['On Hold']]
];

// 5. Agent Performance Details: Today Reports, Total Time, Last Report
$agentQuery = "
    SELECT 
        pr.`agent_name`, 
        COUNT(*) as total_reports, 
        SUM(CASE WHEN $agentDateCondition1 THEN 1 ELSE 0 END) as timeframe_reports, 
        SUM(COALESCE(TIME_TO_SEC(pr.`total_time`), 0)) as total_seconds,
        SUM(CASE WHEN $agentDateCondition2 THEN COALESCE(TIME_TO_SEC(pr.`total_time`), 0) ELSE 0 END) as timeframe_seconds,
        MAX(pr.`date`) as last_report
    FROM `project_reports` pr
    $userJoin
    WHERE $deptWhere
    GROUP BY pr.`agent_name`
";
$agentStmt = $db->prepare($agentQuery);
$agentStmt->execute(array_merge($agentDateParams, $baseDeptParams));
$recordsData = $agentStmt->fetchAll(PDO::FETCH_ASSOC);

$recordsMap = [];
$activeAgentsInTimeframe = 0;
foreach ($recordsData as $r) {
    $key = strtoupper(trim($r['agent_name'] ?? ''));
    if ($key !== '') {
        $recordsMap[$key] = $r;
        if (!empty($r['timeframe_reports']) && (int)$r['timeframe_reports'] > 0) {
            $activeAgentsInTimeframe++;
        }
    }
}

// Build agents list with user details and profile avatars
$agentsList = [];
$allowed_extensions = ['png', 'jpg', 'jpeg', 'gif', 'webp'];
$matchedUserKeys = [];

foreach ($usersList as $user) {
    if (empty($user['is_online'])) {
        continue;
    }

    $realNameKey = strtoupper(trim($user['real_name'] ?? ''));
    $usernameKey = strtoupper(trim($user['username'] ?? ''));

    $matchedRecord = null;
    if ($realNameKey !== '' && isset($recordsMap[$realNameKey])) {
        $matchedRecord = $recordsMap[$realNameKey];
        $matchedUserKeys[$realNameKey] = true;
    } elseif ($usernameKey !== '' && isset($recordsMap[$usernameKey])) {
        $matchedRecord = $recordsMap[$usernameKey];
        $matchedUserKeys[$usernameKey] = true;
    }

    $timeframeCount = $matchedRecord ? (int)$matchedRecord['timeframe_reports'] : 0;
    $totalCount = $matchedRecord ? (int)$matchedRecord['total_reports'] : 0;
    $lastReportDate = $matchedRecord ? ($matchedRecord['last_report'] ?: null) : null;
    $tfSec = $matchedRecord ? (int)$matchedRecord['timeframe_seconds'] : 0;
    $tfH = floor($tfSec / 3600);
    $tfM = floor(($tfSec % 3600) / 60);
    $tfS = $tfSec % 60;
    $timeframeTotalTime = sprintf('%02d:%02d:%02d', $tfH, $tfM, $tfS);

    // Profile photo resolution
    $profile_photo_url = '../images/default-avatar.png';
    if (!empty($user['id'])) {
        foreach ($allowed_extensions as $ext) {
            $photo_path = __DIR__ . '/../images/profile/' . $user['id'] . '.' . $ext;
            if (file_exists($photo_path)) {
                $profile_photo_url = '../images/profile/' . $user['id'] . '.' . $ext . '?t=' . filemtime($photo_path);
                break;
            }
        }
    }

    $agentsList[] = [
        'agent_name' => $user['real_name'] ?: $user['username'],
        'username' => $user['username'] ?: '-',
        'role' => ucfirst($user['user_type'] ?: 'Agent'),
        'department' => $user['department'] ?: $deptLabel,
        'total_reports' => $totalCount,
        'total_records' => $totalCount,
        'timeframe_reports' => $timeframeCount,
        'timeframe_records' => $timeframeCount,
        'timeframe_total_time' => $timeframeTotalTime,
        'timeframe_support_time' => $timeframeTotalTime,
        'timeframe_seconds' => $tfSec,
        'last_report' => $lastReportDate ?: ($user['last_activity'] ? date('Y-m-d', strtotime($user['last_activity'])) : '-'),
        'profile_photo_url' => $profile_photo_url,
        'is_online' => true,
        'last_activity' => $user['last_activity']
    ];
}

// Sort agents by timeframe_reports DESC, timeframe_seconds DESC, total_reports DESC, agent_name ASC
usort($agentsList, function($a, $b) {
    if ($a['timeframe_reports'] !== $b['timeframe_reports']) {
        return $b['timeframe_reports'] <=> $a['timeframe_reports'];
    }
    if ($a['timeframe_seconds'] !== $b['timeframe_seconds']) {
        return $b['timeframe_seconds'] <=> $a['timeframe_seconds'];
    }
    if ($a['total_reports'] !== $b['total_reports']) {
        return $b['total_reports'] <=> $a['total_reports'];
    }
    return strcasecmp($a['agent_name'], $b['agent_name']);
});

echo json_encode([
    'success' => true,
    'department' => $deptLabel,
    'range' => $range,
    'summary' => [
        'total_reports' => $totalReports,
        'total_records' => $totalReports,
        'timeframe_reports' => $timeframeReports,
        'timeframe_records' => $timeframeReports,
        'total_agents' => $totalAgentsCount,
        'online_agents' => $onlineAgentsCount,
        'active_agents' => $totalAgentsCount,
        'agents_active_timeframe' => $onlineAgentsCount,
        'total_time' => $formattedTotalTime,
        'total_support_hours' => $formattedTotalTime,
        'total_seconds' => $totalTimeSec,
        'total_support_seconds' => $totalTimeSec
    ],
    'status_breakdown' => $statusBreakdown,
    'agent_details' => $agentsList
]);
