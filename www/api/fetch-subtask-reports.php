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
          AND (pst.project_id = :pid1 OR p.id = :pid2)
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

$whereClauses = [
    "(pr.subtask_id = :sid_str OR pr.subtask_id = :sid_num_str OR pr.subtask_id = :sid_raw)"
];
$queryParams = [
    'sid_str' => $canonicalSubtaskString,
    'sid_num_str' => (string)$canonicalSubtaskIdNum,
    'sid_raw' => $subtaskId
];

if (!empty($canonicalProjectId)) {
    $whereClauses[] = "(pr.project_id = :pid_canonical OR pr.project_id = :pid_raw)";
    $queryParams['pid_canonical'] = $canonicalProjectId;
    $queryParams['pid_raw'] = $projectId !== '' ? $projectId : $canonicalProjectId;
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
