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

// Session authentication check
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

$projectId = isset($_GET['project_id']) ? trim($_GET['project_id']) : (isset($_GET['id']) ? trim($_GET['id']) : '');

if ($projectId === '') {
    echo json_encode([
        'success' => false,
        'message' => 'Project ID is required'
    ]);
    exit;
}

// Fetch project record
$projectStmt = $db->prepare("
    SELECT id, project_id, date, project_name, created_by, created_at, edited_by, edited_at, category, department, assigned_by, asignee, priority, project_status, project_description, client_dealer_specific, client_dealer_name, product_serial_no, lcd_id, hardware_id, software_version, hasp_id
    FROM `projects`
    WHERE project_id = :project_id OR id = :numeric_id
    LIMIT 1
");

$projectStmt->execute([
    'project_id' => $projectId,
    'numeric_id' => is_numeric($projectId) ? (int)$projectId : 0
]);

$project = $projectStmt->fetch(PDO::FETCH_ASSOC);

if (!$project) {
    echo json_encode([
        'success' => false,
        'message' => 'Project not found'
    ]);
    exit;
}

// Fetch user profile photos
$allowed_extensions = ['png', 'jpg', 'jpeg', 'gif', 'webp'];
$usersStmt = $db->query("SELECT id, username, real_name FROM users");
$allUsers = $usersStmt ? $usersStmt->fetchAll(PDO::FETCH_ASSOC) : [];

$userPhotoMap = [];
foreach ($allUsers as $u) {
    $photo = '../images/default-avatar.png';
    foreach ($allowed_extensions as $ext) {
        $path = __DIR__ . '/../images/profile/' . $u['id'] . '.' . $ext;
        if (file_exists($path)) {
            $photo = '../images/profile/' . $u['id'] . '.' . $ext . '?t=' . filemtime($path);
            break;
        }
    }
    if (!empty($u['username'])) {
        $userPhotoMap[strtolower(trim($u['username']))] = $photo;
    }
    if (!empty($u['real_name'])) {
        $userPhotoMap[strtolower(trim($u['real_name']))] = $photo;
        $nameParts = explode(' ', trim($u['real_name']));
        if (!empty($nameParts[0])) {
            $userPhotoMap[strtolower($nameParts[0])] = $photo;
        }
    }
}

function resolveUserPhoto($name, $map) {
    $key = strtolower(trim($name ?? ''));
    return isset($map[$key]) ? $map[$key] : '../images/default-avatar.png';
}

$project['asignee_photo'] = resolveUserPhoto($project['asignee'], $userPhotoMap);
$project['created_by_photo'] = resolveUserPhoto($project['created_by'], $userPhotoMap);
$project['assigned_by_photo'] = resolveUserPhoto($project['assigned_by'], $userPhotoMap);

// Fetch all subtasks for this project along with reports aggregates:
// - Start Date: date of first project report with the corresponding subtask_id
// - End Date: date of last project report with the corresponding subtask_id
// - Duration: sum of total_time of all project reports with corresponding subtask_id
// - Status: status of last project report with the corresponding subtask_id
$subtasksStmt = $db->prepare("
    SELECT 
        pst.id,
        pst.project_id,
        pst.subtask_id,
        pst.title,
        pst.description,
        pst.assigned_to,
        pst.priority,
        pst.status AS subtask_initial_status,
        pst.due_date,
        pst.created_by,
        pst.created_at,
        (
            SELECT MIN(pr.date)
            FROM `project_reports` pr
            WHERE (pr.subtask_id = pst.subtask_id OR (pr.subtask_id = CAST(pst.id AS CHAR) AND pr.project_id = pst.project_id))
        ) AS start_date,
        (
            SELECT MAX(pr.date)
            FROM `project_reports` pr
            WHERE (pr.subtask_id = pst.subtask_id OR (pr.subtask_id = CAST(pst.id AS CHAR) AND pr.project_id = pst.project_id))
        ) AS end_date,
        (
            SELECT COALESCE(SUM(TIME_TO_SEC(pr.total_time)), 0)
            FROM `project_reports` pr
            WHERE (pr.subtask_id = pst.subtask_id OR (pr.subtask_id = CAST(pst.id AS CHAR) AND pr.project_id = pst.project_id))
        ) AS total_seconds,
        (
            SELECT COUNT(pr.id)
            FROM `project_reports` pr
            WHERE (pr.subtask_id = pst.subtask_id OR (pr.subtask_id = CAST(pst.id AS CHAR) AND pr.project_id = pst.project_id))
        ) AS report_count,
        COALESCE(
            (
                SELECT pr.status
                FROM `project_reports` pr
                WHERE (pr.subtask_id = pst.subtask_id OR (pr.subtask_id = CAST(pst.id AS CHAR) AND pr.project_id = pst.project_id))
                  AND pr.status IS NOT NULL AND TRIM(pr.status) != ''
                ORDER BY pr.date DESC, pr.id DESC
                LIMIT 1
            ),
            pst.status,
            'Pending'
        ) AS status
    FROM `project_subtasks` pst
    WHERE pst.project_id = :project_id
    ORDER BY pst.id ASC
");

$subtasksStmt->execute([
    'project_id' => $project['project_id']
]);
$subtasks = $subtasksStmt->fetchAll(PDO::FETCH_ASSOC);

$totalSubtasks = count($subtasks);
$totalSeconds = 0;
$counts = [
    'completed' => 0,
    'in_progress' => 0,
    'pending' => 0,
    'on_hold' => 0
];

foreach ($subtasks as $idx => &$st) {
    $st['sl_no'] = $idx + 1;
    $sec = (int)($st['total_seconds'] ?? 0);
    $totalSeconds += $sec;
    $h = floor($sec / 3600);
    $m = floor(($sec % 3600) / 60);
    $s = $sec % 60;
    $st['duration'] = sprintf('%02d:%02d:%02d', $h, $m, $s);

    $st['assigned_to_photo'] = resolveUserPhoto($st['assigned_to'], $userPhotoMap);
    $st['created_by_photo'] = resolveUserPhoto($st['created_by'], $userPhotoMap);

    $statusLower = strtolower(trim($st['status'] ?? 'pending'));
    if ($statusLower === 'completed') {
        $counts['completed']++;
    } elseif ($statusLower === 'in progress') {
        $counts['in_progress']++;
    } elseif ($statusLower === 'on hold' || $statusLower === 'hold') {
        $counts['on_hold']++;
    } else {
        $counts['pending']++;
    }
}
unset($st);

$totalH = floor($totalSeconds / 3600);
$totalM = floor(($totalSeconds % 3600) / 60);
$totalS = $totalSeconds % 60;
$totalDurationFormatted = sprintf('%02d:%02d:%02d', $totalH, $totalM, $totalS);

$project['total_duration'] = $totalDurationFormatted;
$project['total_seconds'] = $totalSeconds;

echo json_encode([
    'success' => true,
    'project' => $project,
    'stats' => [
        'total_subtasks' => $totalSubtasks,
        'completed' => $counts['completed'],
        'in_progress' => $counts['in_progress'],
        'pending' => $counts['pending'],
        'on_hold' => $counts['on_hold'],
        'total_duration' => $totalDurationFormatted,
        'total_seconds' => $totalSeconds
    ],
    'subtasks' => $subtasks
]);
