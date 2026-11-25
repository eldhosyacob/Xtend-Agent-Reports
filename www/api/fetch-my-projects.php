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
$userStmt = $db->prepare("SELECT id, username, real_name, department FROM users WHERE id = :id LIMIT 1");
$userStmt->execute(['id' => $userId]);
$userData = $userStmt->fetch();

$userDepartment = '';
if ($userData) {
    if (!empty($userData['username'])) {
        $username = trim($userData['username']);
    }
    if (!empty($userData['real_name'])) {
        $realName = trim($userData['real_name']);
    }
    if (!empty($userData['department'])) {
        $userDepartment = trim($userData['department']);
    }
}

// Build list of valid identifier strings for current user
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

// Filters
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$category = isset($_GET['category']) ? trim($_GET['category']) : '';
$priority = isset($_GET['priority']) ? trim($_GET['priority']) : '';
$statusParam = isset($_GET['status']) ? $_GET['status'] : null;

// Build placeholders for fetching subprojects assigned to current user
$asgUserPlaceholders = [];
$cbUserPlaceholders = [];
$stUserPlaceholders = [];
$spParams = [];

foreach ($userNames as $idx => $name) {
    $asgKey = 'asg_uname_' . $idx;
    $asgUserPlaceholders[] = ':' . $asgKey;
    $spParams[$asgKey] = $name;

    $cbKey = 'cb_uname_' . $idx;
    $cbUserPlaceholders[] = ':' . $cbKey;
    $spParams[$cbKey] = $name;

    $stKey = 'st_uname_' . $idx;
    $stUserPlaceholders[] = ':' . $stKey;
    $spParams[$stKey] = $name;
}

if (empty($asgUserPlaceholders)) {
    $asgUserPlaceholders = ["''"];
    $cbUserPlaceholders = ["''"];
    $stUserPlaceholders = ["''"];
}

$asgUserInClause = implode(', ', $asgUserPlaceholders);
$cbUserInClause = implode(', ', $cbUserPlaceholders);
$stUserInClause = implode(', ', $stUserPlaceholders);

$assigneeOrConditions = [
    "LOWER(TRIM(sp.asignee)) = 'all'",
    "sp.asignee IN ({$asgUserInClause})"
];

foreach ($userNames as $idx => $name) {
    $findKey = 'sp_asg_find_' . $idx;
    $assigneeOrConditions[] = "FIND_IN_SET(:{$findKey}, REPLACE(sp.asignee, ', ', ',')) > 0";
    $spParams[$findKey] = $name;

    $likeKey = 'sp_asg_like_' . $idx;
    $assigneeOrConditions[] = "sp.asignee LIKE :{$likeKey}";
    $spParams[$likeKey] = '%' . $name . '%';
}

$assigneeMatchClause = implode(' OR ', $assigneeOrConditions);

// Step 1: Fetch all subprojects assigned to the logged-in user
$spSql = "SELECT sp.id, sp.project_id, sp.sub_project_id, sp.sub_project_name, sp.date, sp.project_name,
                 sp.product_name, sp.product_sub_category, sp.created_by, sp.created_at,
                 sp.edited_by, sp.edited_at, sp.category, sp.department, sp.assigned_by, sp.asignee,
                 sp.priority, sp.project_status, sp.project_description,
                 sp.client_dealer_specific, sp.client_dealer_name,
                 sp.product_serial_no, sp.lcd_id, sp.hardware_id, sp.software_version, sp.hasp_id,
                 (
                     SELECT COALESCE(SUM(TIME_TO_SEC(pr.total_time)), 0)
                     FROM `project_reports` pr
                     WHERE pr.project_id = sp.sub_project_id
                        OR pr.subtask_id LIKE CONCAT(sp.sub_project_id, '_%')
                 ) AS total_seconds,
                 (
                     SELECT COUNT(*)
                     FROM `project_subtasks` pst
                     WHERE pst.sub_project_id = sp.sub_project_id
                        OR pst.project_id = sp.sub_project_id
                        OR pst.subtask_id LIKE CONCAT(sp.sub_project_id, '_%')
                 ) AS tasks_count
          FROM `sub_projects` sp
          WHERE ({$assigneeMatchClause} OR sp.created_by IN ({$cbUserInClause}) OR EXISTS (
              SELECT 1 FROM `project_subtasks` pst
              WHERE (pst.sub_project_id = sp.sub_project_id OR pst.subtask_id LIKE CONCAT(sp.sub_project_id, '_T%') OR (pst.project_id = sp.sub_project_id AND (pst.sub_project_id IS NULL OR pst.sub_project_id = '')))
                AND pst.assigned_to IN ({$stUserInClause})
          ))
          ORDER BY sp.id DESC";

$spStmt = $db->prepare($spSql);
$spStmt->execute($spParams);
$assignedSubProjects = $spStmt->fetchAll(PDO::FETCH_ASSOC);

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

if (!function_exists('resolveUserPhoto')) {
    function resolveUserPhoto($name, $map) {
        $key = strtolower(trim($name ?? ''));
        return isset($map[$key]) ? $map[$key] : '../images/default-avatar.png';
    }
}

// Group assigned subprojects by parent project_id
$subProjectsByParent = [];
foreach ($assignedSubProjects as $sp) {
    $parentPid = trim($sp['project_id'] ?? '');
    if ($parentPid === '') {
        continue;
    }

    $sec = (int)($sp['total_seconds'] ?? 0);
    $h = floor($sec / 3600);
    $m = floor(($sec % 3600) / 60);
    $s = $sec % 60;
    $sp['total_time'] = sprintf('%02d:%02d:%02d', $h, $m, $s);
    $sp['tasks_count'] = (int)($sp['tasks_count'] ?? 0);
    $sp['asignee_photo'] = resolveUserPhoto($sp['asignee'], $userPhotoMap);
    $sp['created_by_photo'] = resolveUserPhoto($sp['created_by'], $userPhotoMap);
    $sp['edited_by_photo'] = resolveUserPhoto($sp['edited_by'], $userPhotoMap);

    if (!isset($subProjectsByParent[$parentPid])) {
        $subProjectsByParent[$parentPid] = [];
    }
    $subProjectsByParent[$parentPid][] = $sp;
}

$emptyStats = [
    'total' => 0,
    'assigned' => 0,
    'in_progress' => 0,
    'pending' => 0,
    'hold' => 0,
    'completed' => 0,
    'total_projects' => 0
];

if (empty($subProjectsByParent)) {
    echo json_encode([
        'success' => true,
        'data' => [],
        'count' => 0,
        'stats' => $emptyStats,
        'current_user' => [
            'id' => $userId,
            'username' => $username,
            'real_name' => $realName,
            'department' => $userDepartment,
            'matched_names' => $userNames
        ]
    ]);
    exit;
}

// Step 2: Fetch parent projects that have assigned subprojects
$parentIds = array_keys($subProjectsByParent);
$parentPlaceholders = [];
$projectParams = [];

foreach ($parentIds as $idx => $pid) {
    $pKey = 'parent_pid_' . $idx;
    $parentPlaceholders[] = ':' . $pKey;
    $projectParams[$pKey] = $pid;
}

$whereConditions = [
    'p.project_id IN (' . implode(', ', $parentPlaceholders) . ')'
];

if ($category !== '') {
    $whereConditions[] = 'p.category = :category';
    $projectParams['category'] = $category;
}

if ($priority !== '') {
    $whereConditions[] = 'p.priority = :priority';
    $projectParams['priority'] = $priority;
}

$whereSql = 'WHERE ' . implode(' AND ', $whereConditions);

$projSql = "SELECT p.id, p.project_id, p.date, p.project_name,
                   p.product_name, p.product_sub_category, p.created_by, p.created_at,
                   p.edited_by, p.edited_at, p.category, p.department, p.assigned_by, p.asignee,
                   p.priority, p.project_status, p.project_description,
                   p.client_dealer_specific, p.client_dealer_name,
                   p.product_serial_no, p.lcd_id, p.hardware_id, p.software_version, p.hasp_id
            FROM `projects` p
            {$whereSql}
            ORDER BY p.id DESC";

$projStmt = $db->prepare($projSql);
$projStmt->execute($projectParams);
$projects = $projStmt->fetchAll(PDO::FETCH_ASSOC);

// Attach assigned subprojects and apply search filter
$matchedProjects = [];
foreach ($projects as $proj) {
    $pid = trim($proj['project_id'] ?? '');
    $childSubProjects = $subProjectsByParent[$pid] ?? [];

    if ($search !== '') {
        $matchesSearch = false;
        $projectSearchFields = [
            $proj['project_id'] ?? '',
            $proj['project_name'] ?? '',
            $proj['product_name'] ?? '',
            $proj['product_sub_category'] ?? '',
            $proj['client_dealer_name'] ?? '',
            $proj['created_by'] ?? '',
            $proj['project_description'] ?? ''
        ];

        foreach ($projectSearchFields as $fieldVal) {
            if ($fieldVal !== '' && stripos($fieldVal, $search) !== false) {
                $matchesSearch = true;
                break;
            }
        }

        if (!$matchesSearch) {
            foreach ($childSubProjects as $sp) {
                $spSearchFields = [
                    $sp['sub_project_id'] ?? '',
                    $sp['sub_project_name'] ?? '',
                    $sp['department'] ?? '',
                    $sp['assigned_by'] ?? '',
                    $sp['asignee'] ?? '',
                    $sp['project_description'] ?? ''
                ];
                foreach ($spSearchFields as $spVal) {
                    if ($spVal !== '' && stripos($spVal, $search) !== false) {
                        $matchesSearch = true;
                        break 2;
                    }
                }
            }
        }

        if (!$matchesSearch) {
            continue;
        }
    }

    $spStats = [
        'total' => count($childSubProjects),
        'assigned' => 0,
        'in_progress' => 0,
        'pending' => 0,
        'hold' => 0,
        'completed' => 0
    ];

    foreach ($childSubProjects as $sp) {
        $spStatusLower = strtolower(trim($sp['project_status'] ?? 'assigned'));
        if ($spStatusLower === 'assigned') {
            $spStats['assigned']++;
        } elseif ($spStatusLower === 'in progress') {
            $spStats['in_progress']++;
        } elseif ($spStatusLower === 'pending') {
            $spStats['pending']++;
        } elseif ($spStatusLower === 'on hold' || $spStatusLower === 'hold') {
            $spStats['hold']++;
        } elseif ($spStatusLower === 'completed') {
            $spStats['completed']++;
        }
    }

    $proj['asignee_photo'] = resolveUserPhoto($proj['asignee'], $userPhotoMap);
    $proj['created_by_photo'] = resolveUserPhoto($proj['created_by'], $userPhotoMap);
    $proj['edited_by_photo'] = resolveUserPhoto($proj['edited_by'], $userPhotoMap);
    $proj['subprojects'] = $childSubProjects;
    $proj['subprojects_count'] = count($childSubProjects);
    $proj['subprojects_stats'] = $spStats;

    $matchedProjects[] = $proj;
}

// Compute status summary stats across matched projects (before status filter)
$projectStats = $emptyStats;
$projectStats['total'] = count($matchedProjects);
$projectStats['total_projects'] = count($matchedProjects);

foreach ($matchedProjects as $proj) {
    $stLower = strtolower(trim($proj['project_status'] ?? 'assigned'));
    if ($stLower === 'assigned') {
        $projectStats['assigned']++;
    } elseif ($stLower === 'in progress') {
        $projectStats['in_progress']++;
    } elseif ($stLower === 'pending') {
        $projectStats['pending']++;
    } elseif ($stLower === 'on hold' || $stLower === 'hold') {
        $projectStats['hold']++;
    } elseif ($stLower === 'completed') {
        $projectStats['completed']++;
    }
}

// Apply status filter on parent project status
$filteredProjects = $matchedProjects;
if ($statusParam !== null && $statusParam !== '') {
    $statusList = [];
    if (is_array($statusParam)) {
        $statusList = array_values(array_filter(array_map('trim', $statusParam)));
    } elseif (is_string($statusParam)) {
        $statusTrimmed = trim($statusParam);
        if ($statusTrimmed !== '' && $statusTrimmed !== '__none__' && strtolower($statusTrimmed) !== 'all') {
            $statusList = array_values(array_filter(array_map('trim', explode(',', $statusTrimmed))));
        }
    }

    if ($statusParam === '__none__' || (is_array($statusParam) && empty($statusList))) {
        $filteredProjects = [];
    } elseif (!empty($statusList)) {
        $allowedStatusesLower = array_map('strtolower', $statusList);
        $filteredProjects = array_values(array_filter($matchedProjects, function ($proj) use ($allowedStatusesLower) {
            $stLower = strtolower(trim($proj['project_status'] ?? 'assigned'));
            if (in_array($stLower, $allowedStatusesLower, true)) {
                return true;
            }
            if ($stLower === 'hold' && in_array('on hold', $allowedStatusesLower, true)) {
                return true;
            }
            return false;
        }));
    }
}

echo json_encode([
    'success' => true,
    'data' => array_values($filteredProjects),
    'count' => count($filteredProjects),
    'stats' => $projectStats,
    'current_user' => [
        'id' => $userId,
        'username' => $username,
        'real_name' => $realName,
        'department' => $userDepartment,
        'matched_names' => $userNames
    ]
]);
