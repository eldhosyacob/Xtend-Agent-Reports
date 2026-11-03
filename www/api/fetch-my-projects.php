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

$userId = (int)$_SESSION['id'];
$username = trim($_SESSION['username'] ?? '');
$realName = trim($_SESSION['real_name'] ?? ($_SESSION['full_name'] ?? ''));

// Fetch user info from database to ensure up-to-date identifiers
$userStmt = $db->prepare("SELECT id, username, real_name FROM users WHERE id = :id LIMIT 1");
$userStmt->execute(['id' => $userId]);
$userData = $userStmt->fetch();

if ($userData) {
    if (!empty($userData['username'])) {
        $username = trim($userData['username']);
    }
    if (!empty($userData['real_name'])) {
        $realName = trim($userData['real_name']);
    }
}

// Build list of valid identifier strings for current user (exact & lowercased)
$userNames = array_values(array_unique(array_filter([
    $username,
    $realName
])));

if (!empty($realName)) {
    $parts = explode(' ', $realName);
    if (!empty($parts[0])) {
        $userNames[] = trim($parts[0]);
    }
}
$userNames = array_values(array_unique(array_filter($userNames)));
$userNamesLower = array_map('strtolower', $userNames);

// Filters
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$category = isset($_GET['category']) ? trim($_GET['category']) : '';
$priority = isset($_GET['priority']) ? trim($_GET['priority']) : '';
$status = isset($_GET['status']) ? trim($_GET['status']) : '';

// Build parameterized query to find projects assigned to the user OR where user has assigned subtasks
$pUserPlaceholders = [];
$stUserPlaceholders = [];
$queryParams = [];

foreach ($userNames as $idx => $name) {
    $pKey = 'p_uname_' . $idx;
    $pUserPlaceholders[] = ':' . $pKey;
    $queryParams[$pKey] = $name;

    $stKey = 'st_uname_' . $idx;
    $stUserPlaceholders[] = ':' . $stKey;
    $queryParams[$stKey] = $name;
}

if (empty($pUserPlaceholders)) {
    $pUserPlaceholders = ["''"];
    $stUserPlaceholders = ["''"];
}

$pUserInClause = implode(', ', $pUserPlaceholders);
$stUserInClause = implode(', ', $stUserPlaceholders);

$assigneeOrConditions = [
    "LOWER(TRIM(p.asignee)) = 'all'",
    "p.asignee IN ({$pUserInClause})"
];

foreach ($userNames as $idx => $name) {
    $findKey = 'p_asg_find_' . $idx;
    $assigneeOrConditions[] = "FIND_IN_SET(:{$findKey}, REPLACE(p.asignee, ', ', ',')) > 0";
    $queryParams[$findKey] = $name;

    $likeKey = 'p_asg_like_' . $idx;
    $assigneeOrConditions[] = "p.asignee LIKE :{$likeKey}";
    $queryParams[$likeKey] = '%' . $name . '%';
}

$assigneeMatchClause = implode(' OR ', $assigneeOrConditions);

$whereConditions = [
    "({$assigneeMatchClause} OR EXISTS (
        SELECT 1 FROM `project_subtasks` pst 
        WHERE pst.project_id = p.project_id 
          AND pst.assigned_to IN ({$stUserInClause})
    ))"
];

if ($search !== '') {
    $stSearchUserPlaceholders = [];
    foreach ($userNames as $idx => $name) {
        $stSearchKey = 'st_s_uname_' . $idx;
        $stSearchUserPlaceholders[] = ':' . $stSearchKey;
        $queryParams[$stSearchKey] = $name;
    }
    if (empty($stSearchUserPlaceholders)) {
        $stSearchUserPlaceholders = ["''"];
    }
    $stSearchInClause = implode(', ', $stSearchUserPlaceholders);

    $whereConditions[] = "(
        p.project_name LIKE :search1 
        OR p.project_id LIKE :search2 
        OR p.asignee LIKE :search3 
        OR p.created_by LIKE :search4
        OR EXISTS (
            SELECT 1 FROM `project_subtasks` st_search 
            WHERE st_search.project_id = p.project_id 
              AND st_search.assigned_to IN ({$stSearchInClause})
              AND (st_search.title LIKE :search5 OR st_search.subtask_id LIKE :search6)
        )
    )";
    $queryParams['search1'] = '%' . $search . '%';
    $queryParams['search2'] = '%' . $search . '%';
    $queryParams['search3'] = '%' . $search . '%';
    $queryParams['search4'] = '%' . $search . '%';
    $queryParams['search5'] = '%' . $search . '%';
    $queryParams['search6'] = '%' . $search . '%';
}

if ($category !== '') {
    $whereConditions[] = "p.category = :category";
    $queryParams['category'] = $category;
}

if ($priority !== '') {
    $whereConditions[] = "p.priority = :priority";
    $queryParams['priority'] = $priority;
}

if ($status !== '') {
    $whereConditions[] = "p.project_status = :status";
    $queryParams['status'] = $status;
}

$whereSql = 'WHERE ' . implode(' AND ', $whereConditions);

$sql = "SELECT p.id, p.project_id, p.date, p.project_name, p.product_name, p.product_sub_category, p.created_by, p.created_at, 
               p.edited_by, p.edited_at, p.category, p.department, p.assigned_by, p.asignee, 
               p.priority, p.project_status, p.project_description,
               p.client_dealer_specific, p.client_dealer_name,
               p.product_serial_no, p.lcd_id, p.hardware_id, p.software_version, p.hasp_id 
        FROM `projects` p
        {$whereSql} 
        ORDER BY p.id DESC";

$stmt = $db->prepare($sql);
$stmt->execute($queryParams);
$projects = $stmt->fetchAll();

// Resolve profile photos mapping
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

// Fetch subtasks for all matched projects (filtered for the logged-in user only)
$overallStats = [
    'total_projects' => count($projects),
    'total_tasks' => 0,
    'my_tasks' => 0,
    'pending_tasks' => 0,
    'in_progress_tasks' => 0,
    'completed_tasks' => 0
];

$subtasksStmt = $db->prepare("
    SELECT pst.id, pst.project_id, pst.subtask_id, pst.title, pst.description, pst.assigned_to, pst.priority,
           COALESCE(
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
           ) AS status,
           pst.due_date, pst.created_by, pst.created_at, pst.completed_at
    FROM `project_subtasks` pst
    WHERE pst.project_id = :project_id
    ORDER BY CASE WHEN (
        COALESCE(
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
    ) = 'Completed' THEN 1 ELSE 0 END ASC, pst.id DESC
");

foreach ($projects as &$p) {
    $p['asignee_photo'] = resolveUserPhoto($p['asignee'], $userPhotoMap);
    $p['created_by_photo'] = resolveUserPhoto($p['created_by'], $userPhotoMap);
    $p['edited_by_photo'] = resolveUserPhoto($p['edited_by'], $userPhotoMap);

    $asigneeLower = strtolower(trim($p['asignee'] ?? ''));
    if ($asigneeLower === 'all') {
        $p['is_project_assignee'] = true;
    } else {
        $assigneeList = array_map('trim', explode(',', $asigneeLower));
        $p['is_project_assignee'] = false;
        foreach ($userNamesLower as $un) {
            if (in_array($un, $assigneeList, true)) {
                $p['is_project_assignee'] = true;
                break;
            }
        }
    }

    $subtasksStmt->execute(['project_id' => $p['project_id']]);
    $rawSubtasks = $subtasksStmt->fetchAll();

    // Only display subtasks assigned for the logged in user (or all if assigned to all)
    $subtasks = array_values(array_filter($rawSubtasks, function ($st) use ($userNamesLower) {
        $stAssigneeLower = strtolower(trim($st['assigned_to'] ?? ''));
        if ($stAssigneeLower === 'all') return true;
        return in_array($stAssigneeLower, $userNamesLower, true);
    }));

    $pTotalTasks = count($subtasks);
    $pPending = 0;
    $pProgress = 0;
    $pCompleted = 0;

    foreach ($subtasks as &$st) {
        $st['assigned_to_photo'] = resolveUserPhoto($st['assigned_to'], $userPhotoMap);
        $st['created_by_photo'] = resolveUserPhoto($st['created_by'], $userPhotoMap);
        $st['is_my_task'] = true;

        $stStatus = strtolower(trim($st['status'] ?? ''));
        if ($stStatus === 'pending') {
            $pPending++;
            $overallStats['pending_tasks']++;
        } elseif ($stStatus === 'in progress') {
            $pProgress++;
            $overallStats['in_progress_tasks']++;
        } elseif ($stStatus === 'completed') {
            $pCompleted++;
            $overallStats['completed_tasks']++;
        }

        $overallStats['my_tasks']++;
        $overallStats['total_tasks']++;
    }
    unset($st);

    $p['subtasks'] = $subtasks;
    $p['stats'] = [
        'total' => $pTotalTasks,
        'my_tasks' => $pTotalTasks,
        'pending' => $pPending,
        'in_progress' => $pProgress,
        'completed' => $pCompleted,
        'progress_percent' => $pTotalTasks > 0 ? round(($pCompleted / $pTotalTasks) * 100) : ($p['project_status'] === 'Completed' ? 100 : 0)
    ];
}
unset($p);

echo json_encode([
    'success' => true,
    'data' => $projects,
    'count' => count($projects),
    'stats' => $overallStats,
    'current_user' => [
        'id' => $userId,
        'username' => $username,
        'real_name' => $realName,
        'matched_names' => $userNames
    ]
]);
