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

$projectId = isset($_GET['project_id']) ? trim($_GET['project_id']) : '';

if ($projectId === '') {
    echo json_encode([
        'success' => false,
        'message' => 'Project ID is required'
    ]);
    exit;
}

// Fetch project record by project_id or numeric id
$projectStmt = $db->prepare("
    SELECT id, project_id, date, project_name, product_name, product_sub_category, created_by, created_at, edited_by, edited_at, category, department, assigned_by, asignee, priority, project_status, project_description, client_dealer_specific, client_dealer_name, product_serial_no, lcd_id, hardware_id, software_version, hasp_id 
    FROM `projects` 
    WHERE project_id = :project_id OR id = :numeric_id
    LIMIT 1
");

$projectStmt->execute([
    'project_id' => $projectId,
    'numeric_id' => is_numeric($projectId) ? (int)$projectId : 0
]);

$project = $projectStmt->fetch();

if (!$project) {
    echo json_encode([
        'success' => false,
        'message' => 'Project not found'
    ]);
    exit;
}

// Fetch sum of total_time across all records for this project
$projectTimeStmt = $db->prepare("
    SELECT COALESCE(SUM(TIME_TO_SEC(pr.total_time)), 0) AS total_seconds
    FROM `project_reports` pr
    WHERE pr.project_id = :pid_canonical OR pr.project_id = :pid_num OR pr.project_id = :pid_raw
");
$projectTimeStmt->execute([
    'pid_canonical' => $project['project_id'],
    'pid_num' => (string)$project['id'],
    'pid_raw' => $projectId
]);
$timeRow = $projectTimeStmt->fetch();
$totalProjectSeconds = (int)($timeRow['total_seconds'] ?? 0);
$projH = floor($totalProjectSeconds / 3600);
$projM = floor(($totalProjectSeconds % 3600) / 60);
$projS = $totalProjectSeconds % 60;
$project['total_seconds'] = $totalProjectSeconds;
$project['total_time'] = sprintf('%02d:%02d:%02d', $projH, $projM, $projS);

// Fetch all subtasks for this project (status resolved from latest project report if available)
$subtasksStmt = $db->prepare("
    SELECT pst.id, pst.project_id, pst.subtask_id, pst.title, pst.description, pst.assigned_to, pst.priority,
           CASE 
               WHEN LOWER(TRIM(pst.status)) = 'completed' THEN 'Completed'
               ELSE COALESCE(
                   (
                       SELECT pr.status 
                       FROM `project_reports` pr 
                       WHERE (pr.subtask_id = pst.subtask_id OR (pr.subtask_id = CAST(pst.id AS CHAR) AND pr.project_id = pst.project_id))
                         AND pr.status IS NOT NULL 
                         AND TRIM(pr.status) != ''
                       ORDER BY pr.id DESC 
                       LIMIT 1
                   ),
                   pst.status,
                   'Pending'
               )
           END AS status,
           pst.status AS subtask_table_status,
           (
               SELECT pr.id 
               FROM `project_reports` pr 
               WHERE (pr.subtask_id = pst.subtask_id OR (pr.subtask_id = CAST(pst.id AS CHAR) AND pr.project_id = pst.project_id))
                 AND pr.date = CURDATE()
               ORDER BY pr.id DESC 
               LIMIT 1
           ) AS today_report_id,
           (
               SELECT COALESCE(SUM(TIME_TO_SEC(pr.total_time)), 0)
               FROM `project_reports` pr
               WHERE (pr.subtask_id = pst.subtask_id OR (pr.subtask_id = CAST(pst.id AS CHAR) AND pr.project_id = pst.project_id))
           ) AS total_seconds,
           pst.due_date, pst.created_by, pst.created_at, pst.completed_at
    FROM `project_subtasks` pst
    WHERE pst.project_id = :project_id
    ORDER BY CASE WHEN (
        CASE 
            WHEN LOWER(TRIM(pst.status)) = 'completed' THEN 'Completed'
            ELSE COALESCE(
                (
                    SELECT pr.status 
                    FROM `project_reports` pr 
                    WHERE (pr.subtask_id = pst.subtask_id OR (pr.subtask_id = CAST(pst.id AS CHAR) AND pr.project_id = pst.project_id))
                      AND pr.status IS NOT NULL 
                      AND TRIM(pr.status) != ''
                    ORDER BY pr.id DESC 
                    LIMIT 1
                ),
                pst.status,
                'Pending'
            )
        END
    ) = 'Completed' THEN 1 ELSE 0 END ASC, pst.id DESC
");

$subtasksStmt->execute([
    'project_id' => $project['project_id']
]);

$subtasks = $subtasksStmt->fetchAll();

// Optional filter: only subtasks assigned to the current logged-in user
$mySubtasksOnly = isset($_GET['my_subtasks_only']) && in_array(strtolower(trim($_GET['my_subtasks_only'])), ['1', 'true', 'yes'], true);

if ($mySubtasksOnly) {
    $userId = (int)$_SESSION['id'];
    $currentUsername = trim($_SESSION['username'] ?? '');
    $currentRealName = trim($_SESSION['real_name'] ?? ($_SESSION['full_name'] ?? ''));

    // Fetch user info from database to ensure fresh identifiers
    $userStmt = $db->prepare("SELECT id, username, real_name FROM users WHERE id = :id LIMIT 1");
    $userStmt->execute(['id' => $userId]);
    $userData = $userStmt->fetch();

    if ($userData) {
        if (!empty($userData['username'])) {
            $currentUsername = trim($userData['username']);
        }
        if (!empty($userData['real_name'])) {
            $currentRealName = trim($userData['real_name']);
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

    $subtasks = array_values(array_filter($subtasks, function ($st) use ($userNamesLower) {
        $stAssigneeLower = strtolower(trim($st['assigned_to'] ?? ''));
        return in_array($stAssigneeLower, $userNamesLower, true);
    }));

    // Recalculate project total time based on assigned subtasks only
    $assignedTotalSeconds = 0;
    foreach ($subtasks as $st) {
        $assignedTotalSeconds += (int)($st['total_seconds'] ?? 0);
    }
    $h = floor($assignedTotalSeconds / 3600);
    $m = floor(($assignedTotalSeconds % 3600) / 60);
    $s = $assignedTotalSeconds % 60;
    $project['total_seconds'] = $assignedTotalSeconds;
    $project['total_time'] = sprintf('%02d:%02d:%02d', $h, $m, $s);
}

// Resolve profile photos for users
$allowed_extensions = ['png', 'jpg', 'jpeg', 'gif', 'webp'];
$usersStmt = $db->query("SELECT id, username, real_name FROM users");
$allUsers = $usersStmt->fetchAll();

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
$project['edited_by_photo'] = resolveUserPhoto($project['edited_by'], $userPhotoMap);

foreach ($subtasks as &$st) {
    $sec = (int)($st['total_seconds'] ?? 0);
    $h = floor($sec / 3600);
    $m = floor(($sec % 3600) / 60);
    $s = $sec % 60;
    $st['total_time'] = sprintf('%02d:%02d:%02d', $h, $m, $s);
    $st['assigned_to_photo'] = resolveUserPhoto($st['assigned_to'], $userPhotoMap);
    $st['created_by_photo'] = resolveUserPhoto($st['created_by'], $userPhotoMap);
}
unset($st);

// Fetch project attachments from file-uploads/project/
$projectAttachments = [];
$projectUploadDir = __DIR__ . '/../file-uploads/project/';
if (is_dir($projectUploadDir)) {
    $cleanCanonicalPid = preg_replace('/[^a-zA-Z0-9_-]/', '_', $project['project_id'] ?? '');
    $cleanNumPid = preg_replace('/[^a-zA-Z0-9_-]/', '_', (string)($project['id'] ?? ''));
    $pFiles = scandir($projectUploadDir);
    foreach ($pFiles as $f) {
        if ($f === '.' || $f === '..') continue;
        $matches = false;
        $matchedPrefix = '';
        if (!empty($cleanCanonicalPid) && strpos($f, $cleanCanonicalPid . '_') === 0) {
            $matches = true;
            $matchedPrefix = $cleanCanonicalPid . '_';
        } elseif (!empty($cleanNumPid) && strpos($f, $cleanNumPid . '_') === 0) {
            $matches = true;
            $matchedPrefix = $cleanNumPid . '_';
        }

        if ($matches) {
            $rest = substr($f, strlen($matchedPrefix));
            $restWithoutReportId = preg_replace('/^\d+_/', '', $rest);
            $origName = preg_replace('/^[a-f0-9]{13}_/', '', $restWithoutReportId);
            $filePath = $projectUploadDir . $f;
            $sizeBytes = file_exists($filePath) ? filesize($filePath) : 0;
            $units = ['B', 'KB', 'MB', 'GB'];
            $pow = $sizeBytes ? floor(log($sizeBytes, 1024)) : 0;
            $pow = min($pow, count($units) - 1);
            $sizeFormatted = round($sizeBytes / pow(1024, $pow), 2) . ' ' . $units[$pow];
            $projectAttachments[] = [
                'filename' => $f,
                'name' => $origName,
                'size' => $sizeFormatted,
                'url' => '../file-uploads/project/' . rawurlencode($f)
            ];
        }
    }
}
$project['attachments'] = $projectAttachments;

$userRole = strtolower(trim($_SESSION['role'] ?? ($_SESSION['user_type'] ?? '')));
$isAdmin = ($userRole === 'admin');

$canCreateSubtask = $isAdmin;
if (!$canCreateSubtask) {
    $currentUsername = trim($_SESSION['username'] ?? '');
    $currentRealName = trim($_SESSION['real_name'] ?? ($_SESSION['full_name'] ?? ''));
    $userNamesLower = array_map('strtolower', array_values(array_filter([$currentUsername, $currentRealName])));

    $projectAssignee = trim($project['asignee'] ?? '');
    $projectAssigneeLower = strtolower($projectAssignee);

    if ($projectAssigneeLower === 'all') {
        $canCreateSubtask = true;
    } else {
        $assignees = array_map('trim', explode(',', $projectAssigneeLower));
        foreach ($userNamesLower as $uname) {
            if (in_array($uname, $assignees, true)) {
                $canCreateSubtask = true;
                break;
            }
        }
    }

    if (!$canCreateSubtask && !empty($project['created_by'])) {
        $creatorLower = strtolower(trim($project['created_by']));
        if (in_array($creatorLower, $userNamesLower, true)) {
            $canCreateSubtask = true;
        }
    }
}

echo json_encode([
    'success' => true,
    'server_date' => date('Y-m-d'),
    'project' => $project,
    'subtasks' => $subtasks,
    'subtasks_count' => count($subtasks),
    'user_photos' => $userPhotoMap,
    'is_admin' => $isAdmin,
    'can_create_subtask' => $canCreateSubtask
]);
