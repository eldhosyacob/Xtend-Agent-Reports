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

require_once __DIR__ . '/../config/database.php';
$db = getDatabaseConnection();

if (!$db) {
    echo json_encode([
        'success' => false,
        'message' => 'Database connection failed'
    ]);
    exit;
}

$subtaskId = trim($_GET['subtask_id'] ?? $_GET['id'] ?? '');
$projectId = trim($_GET['project_id'] ?? '');

if ($subtaskId === '') {
    echo json_encode([
        'success' => false,
        'message' => 'Subtask ID is required'
    ]);
    exit;
}

// 1. Fetch subtask details along with project info
$subtaskNumericId = is_numeric($subtaskId) ? (int)$subtaskId : 0;
$subtaskStmt = $db->prepare("
    SELECT 
        pst.id,
        pst.project_id,
        pst.sub_project_id,
        pst.subtask_id,
        pst.title,
        pst.description,
        pst.assigned_to,
        pst.priority,
        pst.status,
        pst.due_date,
        pst.created_by,
        pst.created_at,
        pst.completed_at,
        p.project_name,
        p.category AS project_category,
        p.priority AS project_priority,
        p.project_status
    FROM `project_subtasks` pst
    LEFT JOIN `projects` p 
        ON (pst.project_id = p.project_id OR pst.project_id = CAST(p.id AS CHAR))
    WHERE pst.subtask_id = :sid1 OR pst.id = :sid2
    LIMIT 1
");
$subtaskStmt->execute([
    'sid1' => $subtaskId,
    'sid2' => $subtaskNumericId
]);
$subtask = $subtaskStmt->fetch(PDO::FETCH_ASSOC);

// If not found by direct match, try resolving project_id if provided
if (!$subtask && $projectId !== '') {
    $fallbackStmt = $db->prepare("
        SELECT 
            pst.id,
            pst.project_id,
            pst.sub_project_id,
            pst.subtask_id,
            pst.title,
            pst.description,
            pst.assigned_to,
            pst.priority,
            pst.status,
            pst.due_date,
            pst.created_by,
            pst.created_at,
            pst.completed_at,
            p.project_name,
            p.category AS project_category,
            p.priority AS project_priority,
            p.project_status
        FROM `project_subtasks` pst
        LEFT JOIN `projects` p 
            ON (pst.project_id = p.project_id OR pst.project_id = CAST(p.id AS CHAR))
        WHERE (pst.subtask_id = :sid1 OR pst.id = :sid2)
          AND (pst.project_id = :pid1 OR pst.sub_project_id = :pid1 OR p.id = :pid2)
        LIMIT 1
    ");
    $fallbackStmt->execute([
        'sid1' => $subtaskId,
        'sid2' => $subtaskNumericId,
        'pid1' => $projectId,
        'pid2' => is_numeric($projectId) ? (int)$projectId : 0
    ]);
    $subtask = $fallbackStmt->fetch(PDO::FETCH_ASSOC);
}

// Canonical subtask identifiers
$canonicalSubtaskString = $subtaskId;
$canonicalSubtaskIdNum = $subtaskNumericId;
$canonicalProjectId = $projectId;

if ($subtask) {
    if (!empty($subtask['subtask_id'])) {
        $canonicalSubtaskString = $subtask['subtask_id'];
    }
    if (!empty($subtask['id'])) {
        $canonicalSubtaskIdNum = (int)$subtask['id'];
    }
    if (!empty($subtask['project_id'])) {
        $canonicalProjectId = $subtask['project_id'];
    }
}

// Resolve subproject details
$resolvedSubProjectId = '';
if ($subtask && !empty($subtask['sub_project_id'])) {
    $resolvedSubProjectId = trim($subtask['sub_project_id']);
} elseif ($subtask && !empty($subtask['project_id']) && strpos($subtask['project_id'], '_SP') !== false) {
    $resolvedSubProjectId = trim($subtask['project_id']);
} elseif (!empty($projectId) && strpos($projectId, '_SP') !== false) {
    $resolvedSubProjectId = trim($projectId);
} elseif (!empty($canonicalSubtaskString) && strpos($canonicalSubtaskString, '_SP') !== false) {
    $resolvedSubProjectId = preg_replace('/_T\d+$/', '', $canonicalSubtaskString);
}

$subProjectRow = null;
if (!empty($resolvedSubProjectId)) {
    $spStmt = $db->prepare("
        SELECT id, project_id, sub_project_id, sub_project_name, asignee, created_by 
        FROM `sub_projects` 
        WHERE sub_project_id = :spid OR id = :num_id 
        LIMIT 1
    ");
    $spStmt->execute([
        'spid' => $resolvedSubProjectId,
        'num_id' => is_numeric($resolvedSubProjectId) ? (int)$resolvedSubProjectId : 0
    ]);
    $subProjectRow = $spStmt->fetch(PDO::FETCH_ASSOC);
    if ($subProjectRow && $subtask) {
        $subtask['sub_project_id'] = $subProjectRow['sub_project_id'];
        $subtask['sub_project_name'] = $subProjectRow['sub_project_name'];
        $subtask['sub_project_asignee'] = $subProjectRow['asignee'];
    }
}

// Fetch user info from database to ensure fresh identifiers
$userId = (int)($_SESSION['id'] ?? 0);
$currentUsername = trim($_SESSION['username'] ?? '');
$currentRealName = trim($_SESSION['real_name'] ?? ($_SESSION['full_name'] ?? ''));

if ($userId > 0 && $db) {
    $userStmt = $db->prepare("SELECT id, username, real_name FROM users WHERE id = :id LIMIT 1");
    $userStmt->execute(['id' => $userId]);
    $userData = $userStmt->fetch(PDO::FETCH_ASSOC);
    if ($userData) {
        if (!empty($userData['username'])) {
            $currentUsername = trim($userData['username']);
        }
        if (!empty($userData['real_name'])) {
            $currentRealName = trim($userData['real_name']);
        }
    }
}

$userNames = array_values(array_unique(array_filter([
    $currentUsername,
    $currentRealName
])));
if (!empty($currentRealName)) {
    $parts = explode(' ', $currentRealName);
    if (!empty($parts[0])) {
        $userNames[] = trim($parts[0]);
    }
}
$userNamesLower = array_map('strtolower', array_values(array_unique(array_filter($userNames))));

if (!function_exists('checkIsUserAssigned')) {
    function checkIsUserAssigned($assigneeString, $userNamesLower) {
        $clean = strtolower(trim((string)$assigneeString));
        if ($clean === '' || $clean === 'na' || $clean === 'n/a') {
            return false;
        }
        if ($clean === 'all') {
            return true;
        }
        $parts = array_map('trim', explode(',', $clean));
        foreach ($userNamesLower as $uname) {
            if ($uname !== '' && in_array($uname, $parts, true)) {
                return true;
            }
        }
        return false;
    }
}

$isSubprojectAssignedUser = false;
if ($subProjectRow) {
    $isSubprojectAssignedUser = checkIsUserAssigned($subProjectRow['asignee'] ?? '', $userNamesLower);
} else {
    // If not a subproject, check project asignee
    $projAssignee = '';
    if (!empty($subtask['project_id'])) {
        $pStmt = $db->prepare("SELECT asignee FROM `projects` WHERE project_id = :pid OR id = :num_id LIMIT 1");
        $pStmt->execute([
            'pid' => $subtask['project_id'],
            'num_id' => is_numeric($subtask['project_id']) ? (int)$subtask['project_id'] : 0
        ]);
        $projAssignee = $pStmt->fetchColumn() ?: '';
    }
    $isSubprojectAssignedUser = checkIsUserAssigned($projAssignee, $userNamesLower);
}

$whereClauses = [
    "(pr.subtask_id = :sid_str OR pr.subtask_id = :sid_num_str OR pr.subtask_id = :sid_raw)"
];
$queryParams = [
    'sid_str' => $canonicalSubtaskString,
    'sid_num_str' => (string)$canonicalSubtaskIdNum,
    'sid_raw' => $subtaskId
];

$possibleProjectIds = array_values(array_unique(array_filter([
    $canonicalProjectId,
    $projectId,
    $resolvedSubProjectId,
    $subtask['project_id'] ?? '',
    $subtask['sub_project_id'] ?? ''
])));

if (!empty($possibleProjectIds)) {
    $pidPlaceholders = [];
    foreach ($possibleProjectIds as $pIdx => $pidVal) {
        $key = 'pid_' . $pIdx;
        $pidPlaceholders[] = ':' . $key;
        $queryParams[$key] = $pidVal;
    }
    $whereClauses[] = "(pr.project_id IN (" . implode(', ', $pidPlaceholders) . "))";
}

$whereSql = implode(' AND ', $whereClauses);

$reportsStmt = $db->prepare("
    SELECT 
        pr.id,
        pr.project_id,
        pr.subtask_id,
        pr.agent_name,
        pr.department,
        pr.date,
        pr.product_name,
        pr.product_sub_category,
        pr.product_type,
        pr.assigned_by,
        pr.product_id,
        pr.software_version,
        pr.lcd_id,
        pr.hardware_id,
        pr.hasp_id,
        pr.client_name,
        pr.youtrack_id,
        pr.received_from,
        pr.sent_to,
        pr.testing_description,
        pr.start_time,
        pr.end_time,
        pr.total_time,
        pr.status,
        pr.created_at,
        pr.updated_at
    FROM `project_reports` pr
    WHERE $whereSql
    ORDER BY pr.date DESC, pr.id DESC
");

$reportsStmt->execute($queryParams);
$reports = $reportsStmt->fetchAll(PDO::FETCH_ASSOC);

// 3. Calculate statistics
$totalCount = count($reports);
$pendingCount = 0;
$inProgressCount = 0;
$onHoldCount = 0;
$completedCount = 0;
$totalSeconds = 0;

foreach ($reports as $r) {
    $st = strtolower(trim($r['status'] ?? 'pending'));
    if ($st === 'completed') {
        $completedCount++;
    } elseif ($st === 'in progress') {
        $inProgressCount++;
    } elseif ($st === 'on hold' || $st === 'hold') {
        $onHoldCount++;
    } else {
        $pendingCount++;
    }

    if (!empty($r['total_time'])) {
        $parts = explode(':', $r['total_time']);
        if (count($parts) === 3) {
            $totalSeconds += ((int)$parts[0] * 3600) + ((int)$parts[1] * 60) + (int)$parts[2];
        }
    }
}

$formattedTotalHours = sprintf(
    '%02d:%02d:%02d',
    floor($totalSeconds / 3600),
    floor(($totalSeconds % 3600) / 60),
    $totalSeconds % 60
);

// Resolve latest status from latest report for subtask if available
$latestStatus = $subtask['status'] ?? 'Pending';
if (!empty($reports[0]['status'])) {
    $latestStatus = $reports[0]['status'];
}
if ($subtask) {
    $subtask['latest_status'] = $latestStatus;
}

echo json_encode([
    'success' => true,
    'server_date' => date('Y-m-d'),
    'subtask' => $subtask,
    'subtask_id' => $canonicalSubtaskString,
    'project_id' => $canonicalProjectId,
    'sub_project_id' => $resolvedSubProjectId,
    'is_subproject_assigned_user' => $isSubprojectAssignedUser,
    'reports' => $reports,
    'stats' => [
        'total' => $totalCount,
        'pending' => $pendingCount,
        'in_progress' => $inProgressCount,
        'on_hold' => $onHoldCount,
        'completed' => $completedCount,
        'total_time' => $formattedTotalHours,
        'total_seconds' => $totalSeconds
    ]
]);
